"""Run real HTTP/PDO integration against a disposable, uniquely named MySQL database."""

import os, sys, json, subprocess, socket, time, urllib.error, uuid, shutil
from pathlib import Path
from acceptance import Browser, check
import acceptance

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN", "/Applications/XAMPP/xamppfiles/bin/php")
if not Path(PHP).exists():
    PHP = shutil.which("php") or "php"
env = os.environ.copy()
env.pop("DB_DSN", None)
env["DB_NAME"] = "kopipiro_test_" + uuid.uuid4().hex[:12]
env["APP_BASE"] = ""


def helper(action, data=None):
    r = subprocess.run(
        [PHP, str(ROOT / "tests/db.php"), action],
        input=json.dumps(data) if data else None,
        capture_output=True,
        text=True,
        env=env,
        check=True,
    )
    return json.loads(r.stdout) if r.stdout.strip() else None


def query(sql, args=[]):
    return helper("query", dict(sql=sql, args=args))


def post(b, path, data):
    data = {"csrf_token": b.token("products.php"), **data}
    return b.request(path, data)


def login(who):
    b = Browser()
    post(
        b,
        "auth/login.php",
        dict(email=who + "@example.test", password=fixtures["password"]),
    )
    return b


def multipart(b, path, fields, filename, content, mime="image/png"):
    import urllib.request

    boundary = "----kopipiro" + uuid.uuid4().hex
    fields = {"csrf_token": b.token("products.php"), **fields}
    parts = []
    for k, v in fields.items():
        parts.append(
            f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
        )
    parts.append(
        f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()
        + content
        + b"\r\n"
    )
    parts.append(f"--{boundary}--\r\n".encode())
    request = urllib.request.Request(
        acceptance.BASE + "/" + path,
        b"".join(parts),
        {"Content-Type": "multipart/form-data; boundary=" + boundary},
    )
    try:
        with b.opener.open(request) as r:
            return r.status, r.read(), r.url
    except urllib.error.HTTPError as r:
        return r.code, r.read(), r.url


server = None
uploads_before = set((ROOT / "uploads/products").glob("*"))
try:
    fixtures = helper("setup")
    sock = socket.socket()
    sock.bind(("127.0.0.1", 0))
    port = sock.getsockname()[1]
    sock.close()
    acceptance.BASE = f"http://127.0.0.1:{port}"
    runtime = ROOT / "tests/.runtime"
    runtime.mkdir(exist_ok=True)
    log = open(runtime / "server.log", "w")
    server = subprocess.Popen(
        [PHP, "-S", f"127.0.0.1:{port}", "-t", str(ROOT)],
        env=env,
        stdout=log,
        stderr=log,
    )
    for _ in range(60):
        try:
            Browser().request("index.php")
            break
        except OSError:
            time.sleep(0.1)
    guest = Browser()
    s, body, u = guest.request("admin/products.php")
    check("auth/login.php" in u, "guest management blocked")
    for path in [
        "admin/dashboard.php",
        "admin/export_excel.php",
        "cashier/dashboard.php",
        "customer/orders.php",
    ]:
        check("auth/login.php" in guest.request(path)[2], "guest blocked " + path)
    check(guest.request("cart/add.php")[0] == 405, "GET mutation rejected")
    check(guest.request("cart/add.php", dict(id=1))[0] == 403, "missing CSRF rejected")
    check(
        guest.request("cart/add.php", dict(id=1, csrf_token="forged"))[0] == 403,
        "forged CSRF rejected",
    )
    # Registration ignores forged roles, hashes passwords, rejects duplicates.
    s, body, u = post(
        guest,
        "auth/register.php",
        dict(
            name="<script>test</script>",
            email="new@example.test",
            password="TestNewPassword.123",
            role="owner",
        ),
    )
    new = query("SELECT * FROM users WHERE email=?", ["new@example.test"])[0]
    check(
        new["role"] == "customer" and new["password"] != "TestNewPassword.123",
        "registration cannot escalate and password hashed",
    )
    post(
        guest,
        "auth/register.php",
        dict(
            name="duplicate", email="new@example.test", password="TestNewPassword.123"
        ),
    )
    check(
        len(query("SELECT * FROM users WHERE email=?", ["new@example.test"])) == 1,
        "duplicate email rejected",
    )
    post(
        guest,
        "auth/register.php",
        dict(name="Long password", email="long@example.test", password="a" * 73),
    )
    check(
        not query("SELECT * FROM users WHERE email=?", ["long@example.test"]),
        "password beyond bcrypt limit rejected",
    )
    check(
        "salah"
        in post(
            guest, "auth/login.php", dict(email="owner@example.test", password="wrong")
        )[1].decode(),
        "wrong password rejected",
    )
    regen = Browser()
    regen_token = regen.token("auth/login.php")
    old_session = next(c.value for c in regen.jar if c.name == "PHPSESSID")
    regen.request(
        "auth/login.php",
        dict(
            csrf_token=regen_token,
            email="customerA@example.test",
            password=fixtures["password"],
        ),
    )
    check(
        old_session != next(c.value for c in regen.jar if c.name == "PHPSESSID"),
        "login regenerates session ID",
    )
    owner = login("owner")
    ca = login("cashierA")
    cb = login("cashierB")
    customer = login("customerA")
    other = login("customerB")
    check(
        b"Ruang owner" in owner.request("admin/dashboard.php")[1],
        "owner login redirects to dashboard",
    )
    for b in [ca, customer]:
        for path in [
            "admin/products.php",
            "admin/export_excel.php",
            "admin/cashiers.php",
        ]:
            check(b.request(path)[0] == 403, "non-owner blocked " + path)
    # Cart and checkout: multiple products, forged browser totals ignored, no discount for guest.
    post(guest, "cart/add.php", dict(id=1, quantity=2))
    post(guest, "cart/add.php", dict(id=2, quantity=1))
    before = query("SELECT COUNT(*) n FROM orders")[0]["n"]
    page = guest.request("checkout.php")[1]
    import re

    key = re.search(rb'name="checkout_key" value="([^"]+)"', page).group(1).decode()
    data = dict(
        checkout_key=key,
        guest_name='=HYPERLINK("bad")',
        guest_email="guest@example.test",
        subtotal=1,
        discount_amount=999999,
        total_amount=1,
    )
    s, body, u = post(guest, "checkout.php", data)
    check(s == 200 and "order_success.php" in u, "guest checkout succeeds")
    guest_order = query("SELECT * FROM orders ORDER BY id DESC LIMIT 1")[0]
    gid = guest_order["id"]
    check(
        float(guest_order["subtotal"]) == 70000
        and float(guest_order["discount_amount"]) == 0
        and float(guest_order["total_amount"]) == 70000,
        "guest server prices and zero discount",
    )
    check(
        len(query("SELECT * FROM order_items WHERE order_id=?", [gid])) == 2,
        "all items committed",
    )
    post(guest, "checkout.php", data)
    guest.request("order_success.php?id=" + str(gid))
    check(
        query("SELECT COUNT(*) n FROM orders")[0]["n"] == before + 1,
        "resubmission and refresh do not duplicate order",
    )
    check(
        Browser().request("order_success.php?id=" + str(gid))[0] == 404,
        "guessed guest ID blocked",
    )
    check(
        Browser().request("order_success.php?id=" + str(gid) + "&token=bad")[0] == 404,
        "forged guest token blocked",
    )
    # A database error after inserting an order and its first item must roll back everything.
    rollback = Browser()
    post(rollback, "cart/add.php", dict(id=1))
    post(rollback, "cart/add.php", dict(id=2))
    key = (
        re.search(
            rb'name="checkout_key" value="([^"]+)"', rollback.request("checkout.php")[1]
        )
        .group(1)
        .decode()
    )
    count = query("SELECT COUNT(*) n FROM orders")[0]["n"]
    item_count = query("SELECT COUNT(*) n FROM order_items")[0]["n"]
    query(
        "CREATE TRIGGER test_reject_item BEFORE INSERT ON order_items FOR EACH ROW BEGIN IF NEW.product_id=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='intentional test failure'; END IF; END"
    )
    try:
        s, body, u = post(
            rollback,
            "checkout.php",
            dict(
                checkout_key=key, guest_name="Rollback test", guest_phone="08123456789"
            ),
        )
        check(
            s == 200 and b"belum tersimpan" in body,
            "database failure shows safe checkout feedback",
        )
        check(
            query("SELECT COUNT(*) n FROM orders")[0]["n"] == count
            and query("SELECT COUNT(*) n FROM order_items")[0]["n"] == item_count,
            "checkout rollback leaves no partial order or items",
        )
        check(
            b"Test Latte" in rollback.request("cart/index.php")[1]
            and b"Test Toast" in rollback.request("cart/index.php")[1],
            "failed checkout retains all cart items",
        )
    finally:
        query("DROP TRIGGER test_reject_item")
    # Customer discount and owner isolation.
    post(customer, "cart/add.php", dict(id=1, quantity=2))
    post(customer, "cart/add.php", dict(id=2))
    page = customer.request("checkout.php")[1]
    key = re.search(rb'name="checkout_key" value="([^"]+)"', page).group(1).decode()
    post(
        customer,
        "checkout.php",
        dict(checkout_key=key, total_amount=1, discount_amount=1),
    )
    co = query(
        "SELECT * FROM orders WHERE user_id=?", [fixtures["users"]["customerA"]]
    )[0]
    cid = co["id"]
    check(
        float(co["subtotal"]) == 70000
        and float(co["discount_amount"]) == 7000
        and float(co["total_amount"]) == 63000,
        "customer exact 10 percent persisted",
    )
    check(
        other.request("customer/order_detail.php?id=" + str(cid))[0] == 404,
        "other customer detail blocked",
    )
    check(
        co["order_number"].encode() not in other.request("customer/orders.php")[1],
        "other customer history isolated",
    )
    check(
        post(
            customer,
            "customer/review.php",
            dict(product_id=1, rating=5, comment="early"),
        )[0]
        == 403,
        "review before completed purchase rejected",
    )
    # Assignment and exact cashier authorization for GET and forged POST.
    check(
        post(
            ca,
            "admin/assign_order.php",
            dict(id=cid, cashier_id=fixtures["users"]["cashierA"]),
        )[0]
        == 403,
        "cashier cannot self assign",
    )
    post(
        owner,
        "admin/assign_order.php",
        dict(id=cid, cashier_id=fixtures["users"]["cashierA"]),
    )
    check(
        ca.request("cashier/order_detail.php?id=" + str(cid))[0] == 200,
        "assigned cashier can view",
    )
    check(
        cb.request("cashier/order_detail.php?id=" + str(cid))[0] == 404,
        "other cashier cannot view",
    )
    check(
        co["order_number"].encode() not in cb.request("cashier/dashboard.php")[1],
        "cashier list isolation",
    )
    check(
        post(cb, "cashier/update_status.php", dict(id=cid, status="processing"))[0]
        == 404,
        "forged other-cashier status blocked",
    )
    check(
        post(ca, "cashier/update_status.php", dict(id=cid, status="completed"))[0]
        == 409,
        "status skipping rejected",
    )
    check(
        post(ca, "cashier/update_status.php", dict(id=cid, status="cancelled"))[0]
        == 409,
        "cashier cancellation rejected",
    )
    for status in ["processing", "ready", "completed"]:
        check(
            post(ca, "cashier/update_status.php", dict(id=cid, status=status))[0]
            == 200,
            "workflow " + status,
        )
    check(
        post(owner, "cashier/update_status.php", dict(id=cid, status="cancelled"))[0]
        == 409,
        "completed cannot cancel",
    )
    check(
        post(
            owner,
            "admin/assign_order.php",
            dict(id=cid, cashier_id=fixtures["users"]["cashierB"]),
        )[0]
        == 409,
        "completed cannot reassign",
    )
    check(
        post(
            customer, "customer/review.php", dict(product_id=1, rating=9, comment="bad")
        )[0]
        == 422,
        "invalid rating rejected",
    )
    post(
        customer,
        "customer/review.php",
        dict(product_id=1, rating=5, comment="<script>alert(1)</script>"),
    )
    post(
        customer,
        "customer/review.php",
        dict(product_id=1, rating=4, comment="<script>alert(1)</script>"),
    )
    check(len(query("SELECT * FROM reviews")) == 1, "review edit prevents duplicate")
    check(
        b"&lt;script&gt;" in customer.request("product.php?id=1")[1]
        and b"4.0" in guest.request("products.php")[1],
        "review escaped and catalog aggregate real",
    )
    # Real multipart uploads and product CRUD.
    import base64

    png = base64.b64decode(
        "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII="
    )
    fields = dict(
        name="Upload Test",
        description="test",
        price="10000.00",
        category_id=1,
        is_available=1,
    )
    multipart(
        owner,
        "admin/create_product.php",
        fields,
        "evil.php",
        b"<?php echo 1; ?>",
        "image/png",
    )
    check(
        not query("SELECT * FROM products WHERE name=?", ["Upload Test"]),
        "fake image MIME rejected",
    )
    multipart(
        owner,
        "admin/create_product.php",
        fields,
        "big.png",
        png + b"x" * (2 * 1024 * 1024),
        "image/png",
    )
    check(
        not query("SELECT * FROM products WHERE name=?", ["Upload Test"]),
        "oversized upload rejected",
    )
    existing_uploads = set((ROOT / "uploads/products").glob("*"))
    query(
        "CREATE TRIGGER test_reject_product BEFORE INSERT ON products FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='intentional product failure'"
    )
    try:
        multipart(owner, "admin/create_product.php", fields, "new.png", png)
        check(
            set((ROOT / "uploads/products").glob("*")) == existing_uploads,
            "database failure cleans newly uploaded image",
        )
    finally:
        query("DROP TRIGGER test_reject_product")
    s, body, u = multipart(owner, "admin/create_product.php", fields, "unsafe.php", png)
    p = query("SELECT * FROM products WHERE name=?", ["Upload Test"])[0]
    pid = p["id"]
    old = p["image"]
    check(
        bool(re.fullmatch(r"[a-f0-9]{40}\.png", old)),
        "validated content saved with random safe extension",
    )
    check(
        Browser().request("uploads/products/" + old)[0] == 200, "uploaded image loads"
    )
    fields.update(name="Edited Test", price="12000.00")
    post(owner, "admin/edit_product.php?id=" + str(pid), fields)
    check(
        query("SELECT * FROM products WHERE id=?", [pid])[0]["image"] == old,
        "edit without upload preserves image",
    )
    multipart(owner, "admin/edit_product.php?id=" + str(pid), fields, "new.png", png)
    p = query("SELECT * FROM products WHERE id=?", [pid])[0]
    check(
        p["image"] != old and not (ROOT / "uploads/products" / old).exists(),
        "replace image and cleanup old image",
    )
    # JPEG and WebP content take their own validated safe extensions.
    image_data = json.loads(
        subprocess.check_output(
            [
                PHP,
                "-r",
                '$im=imagecreatetruecolor(2,2);ob_start();imagejpeg($im);$j=base64_encode(ob_get_clean());echo json_encode(["jpg"=>$j]);',
            ]
        )
    )
    image_data["webp"] = "UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA"
    for extension, mime in [("jpg", "image/jpeg"), ("webp", "image/webp")]:
        multipart(
            owner,
            "admin/edit_product.php?id=" + str(pid),
            fields,
            "image." + extension,
            base64.b64decode(image_data[extension]),
            mime,
        )
        check(
            query("SELECT image FROM products WHERE id=?", [pid])[0]["image"].endswith(
                "." + extension
            ),
            "valid " + extension + " upload accepted",
        )
    check(
        b"Edited Test" in guest.request("products.php")[1],
        "product updates visible in catalog",
    )
    post(owner, "admin/delete_product.php", dict(id=pid))
    check(
        not query("SELECT is_available FROM products WHERE id=?", [pid])[0][
            "is_available"
        ],
        "product soft deactivation",
    )
    check(
        b"Edited Test" not in guest.request("products.php")[1],
        "deactivated product hidden",
    )
    # Historical snapshot after purchased product changes.
    post(
        owner,
        "admin/edit_product.php?id=1",
        dict(
            name="Changed name",
            description="changed",
            price="99999.00",
            category_id=1,
            is_available=1,
        ),
    )
    item = query("SELECT * FROM order_items WHERE order_id=? AND product_id=1", [cid])[
        0
    ]
    check(
        item["product_name"] == "Test Latte" and float(item["unit_price"]) == 25000,
        "historical names and prices preserved",
    )
    # Product create validation errors stay on form.
    s, body, u = post(
        owner, "admin/create_product.php", dict(name="", price="-1", category_id=99999)
    )
    check(
        "create_product.php" in u and b"notice error" in body,
        "invalid product errors visible",
    )
    # Stale cart, quantity, availability; no partial checkout.
    fresh = Browser()
    post(fresh, "cart/add.php", dict(id=2))
    query("UPDATE products SET is_available=0 WHERE id=2")
    check(
        b"tidak tersedia" in fresh.request("checkout.php")[1],
        "stale unavailable cart detected",
    )
    check(
        post(fresh, "cart/update.php", dict(id=2, quantity="1.5"))[0] == 200,
        "invalid quantity handled with feedback",
    )
    post(fresh, "cart/remove.php", dict(id=2))
    check(
        b"Belum ada teman" in fresh.request("cart/index.php")[1],
        "unavailable item removable",
    )
    # Oversized but syntactically valid prices must leave a usable cart.
    query("UPDATE products SET price=9999999999.99,is_available=1 WHERE id=2")
    huge = Browser()
    s, body, u = post(huge, "cart/add.php", dict(id=2, quantity=2))
    check(
        s == 200 and b"Kosongkan keranjang" in body,
        "oversized cart remains recoverable",
    )
    check(
        huge.request("checkout.php")[0] == 200,
        "oversized checkout shows validation instead of 500",
    )
    page = huge.request("checkout.php")[1]
    hugekey = re.search(rb'name="checkout_key" value="([^"]+)"', page).group(1).decode()
    count = query("SELECT COUNT(*) n FROM orders")[0]["n"]
    post(
        huge,
        "checkout.php",
        dict(checkout_key=hugekey, guest_name="Huge", guest_phone="08123456789"),
    )
    check(
        query("SELECT COUNT(*) n FROM orders")[0]["n"] == count,
        "oversized order rejected before persistence",
    )
    post(huge, "cart/remove.php", dict(id=2))
    query("UPDATE products SET price=20000 WHERE id=2")
    # Categories, privileged account creation, CSRF logout.
    post(owner, "admin/categories.php", dict(name="New category"))
    category = query("SELECT * FROM categories WHERE name=?", ["New category"])[0]
    post(owner, "admin/categories.php", dict(id=category["id"], name="Renamed"))
    check(
        bool(query("SELECT * FROM categories WHERE name=?", ["Renamed"])),
        "category rename",
    )
    post(owner, "admin/categories.php", dict(id=category["id"], action="delete"))
    check(
        not query("SELECT * FROM categories WHERE id=?", [category["id"]]),
        "unused category deletion",
    )
    post(owner, "admin/categories.php", dict(id=1, action="delete"))
    check(bool(query("SELECT * FROM categories WHERE id=1")), "used category protected")
    post(
        owner,
        "admin/cashiers.php",
        dict(
            name="New Cashier",
            email="newcashier@example.test",
            password="TemporaryTestPassword123",
        ),
    )
    check(
        query("SELECT role FROM users WHERE email=?", ["newcashier@example.test"])[0][
            "role"
        ]
        == "cashier",
        "owner creates cashier",
    )
    # Completed revenue and XLSX with real rows, explicit text (formula injection defense).
    dashboard = owner.request("admin/dashboard.php")[1]
    check(
        b"Rp63.000" in dashboard and b'data-values="[63000]"' in dashboard,
        "dashboard revenue matches completed only",
    )
    check(
        owner.request("admin/dashboard.php?from=bad")[0] == 400,
        "invalid report date rejected",
    )
    check(
        b'data-values="[]"'
        in owner.request("admin/dashboard.php?from=2000-01-01&to=2000-01-02")[1],
        "chart date filtering",
    )
    s, xlsx, u = owner.request("admin/export_excel.php")
    check(s == 200 and xlsx[:2] == b"PK", "valid XLSX download")
    import zipfile, io, xml.etree.ElementTree as ET

    z = zipfile.ZipFile(io.BytesIO(xlsx))
    sheet = z.read("xl/worksheets/sheet1.xml")
    strings = z.read("xl/sharedStrings.xml")
    check(
        b"<f>" not in sheet and b"HYPERLINK" in strings,
        "XLSX customer text cannot become formula",
    )
    check(b"63000" in sheet and b"70000" in sheet, "XLSX numeric totals match orders")
    s, filtered, u = owner.request(
        "admin/export_excel.php?from=2000-01-01&to=2000-01-02"
    )
    filteredxml = zipfile.ZipFile(io.BytesIO(filtered)).read("xl/worksheets/sheet1.xml")
    check(b'<row r="2"' not in filteredxml, "XLSX honors date range")
    csv = owner.request("admin/export_excel.php?format=csv")[1]
    check(b"'=HYPERLINK" in csv, "CSV formula injection escaped")
    post(customer, "auth/logout.php", {})
    check(
        "auth/login.php" in customer.request("customer/orders.php")[2],
        "logout removes authentication",
    )
    # Fresh installation, repeatable migration and CLI account initialization.
    primary_name = env["DB_NAME"]
    env["DB_NAME"] = "kopipiro_test_" + uuid.uuid4().hex[:12]
    try:
        helper("setup_empty")
        seed = subprocess.run(
            [PHP, str(ROOT / "database/seed_catalog.php")],
            env=env,
            capture_output=True,
            text=True,
        )
        check(
            seed.returncode == 0 and len(query("SELECT * FROM products")) == 5,
            "fresh catalog initialized",
        )
        seed = subprocess.run(
            [PHP, str(ROOT / "database/seed_catalog.php")],
            env=env,
            capture_output=True,
            text=True,
        )
        check(
            seed.returncode == 1 and len(query("SELECT * FROM products")) == 5,
            "catalog seed refuses to replace existing data",
        )
        for _ in range(2):
            subprocess.run(
                [PHP, str(ROOT / "database/migrate.php")],
                env=env,
                capture_output=True,
                check=True,
            )
        check(
            len(query("SELECT * FROM products")) == 5,
            "repeat migration preserves catalog",
        )
        seed_env = {
            **env,
            "SEED_EMAIL": "cliowner@example.test",
            "SEED_NAME": "CLI Owner",
            "SEED_PASSWORD": uuid.uuid4().hex,
        }
        seeded = subprocess.run(
            [PHP, str(ROOT / "database/seed.php"), "owner"],
            env=seed_env,
            capture_output=True,
        )
        account = query("SELECT * FROM users WHERE email=?", ["cliowner@example.test"])[
            0
        ]
        check(
            seeded.returncode == 0
            and account["role"] == "owner"
            and account["password"] != seed_env["SEED_PASSWORD"],
            "CLI seed creates hashed privileged account",
        )
        repeat = subprocess.run(
            [PHP, str(ROOT / "database/seed.php"), "cashier"],
            env=seed_env,
            capture_output=True,
        )
        check(
            repeat.returncode == 1
            and query("SELECT role FROM users")[0]["role"] == "owner",
            "CLI seed never overwrites existing account",
        )
    finally:
        helper("drop")
        env["DB_NAME"] = primary_name
    print("ALL INTEGRATION CHECKS PASSED")
finally:
    if server:
        server.terminate()
        server.wait(timeout=5)
    for file in set((ROOT / "uploads/products").glob("*")) - uploads_before:
        if file.is_file():
            file.unlink()
    helper("drop")
