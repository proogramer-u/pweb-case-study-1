<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = currentUser();

if ($user && $user['role'] !== 'customer') {
    abortRequest(403, 'Gunakan akun pelanggan atau keluar untuk memesan sebagai tamu.');
}

$errors = [];
$items = [];
$checkoutKey = $_SESSION['checkout_key'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    $submittedKey = input('checkout_key');
    // Refresh atau POST ulang harus membuka pesanan lama, bukan membuat pesanan baru.
    $existingOrder = db('SELECT id,user_id,guest_token_hash FROM orders WHERE checkout_key=?', [
        hash('sha256', $submittedKey)
    ])->fetch();
    if ($existingOrder) {
        $belongsToCustomer = $user && (int) $existingOrder['user_id'] === (int) $user['id'];
        $belongsToGuestSession = !$user && isset($_SESSION['guest_tokens'][$existingOrder['id']]);

        if ($belongsToCustomer || $belongsToGuestSession) {
            redirect('order_success.php?id=' . $existingOrder['id']);
        }
    }

    if (!hash_equals($checkoutKey, $submittedKey)) {
        abortRequest(409, 'Keranjang telah berubah. Muat ulang checkout.');
    }

    // Pelanggan memakai identitas akunnya; tamu harus mengisi kontak sendiri.
    $name = input('guest_name');
    $email = input('guest_email');
    $phone = input('guest_phone');
    if (!$user) {
        if (!$name || strlen($name) > 150) {
            $errors[] = 'Nama tamu wajib diisi (maksimal 150 karakter).';
        }
        if (!$email && !$phone) {
            $errors[] = 'Isi email atau nomor telepon.';
        }
        if ($email && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
            $errors[] = 'Email tidak valid.';
        }
        if ($phone && !preg_match('/^[0-9+() .-]{6,40}$/D', $phone)) {
            $errors[] = 'Nomor telepon tidak valid.';
        }
    }
    if (!$errors) {
        try {
            // Order dan semua item harus tersimpan bersama, atau semuanya dibatalkan.
            $pdo->beginTransaction();
            $items = cartItems(true);
            if (!$items) {
                throw new InvalidArgumentException('Keranjang kosong.');
            }
            $totals = orderTotals($items, $user['role'] ?? null);
            validateOrderStorage($totals);
            $guestToken = bin2hex(random_bytes(32));
            $orderNumber = 'KP-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
            db(
                '
                    INSERT INTO orders (
                            order_number,
                            user_id,
                            guest_name,
                            guest_email,
                            guest_phone,
                            subtotal,
                            discount_amount,
                            total_amount,
                            checkout_key,
                            guest_token_hash
                        )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ',
                [
                    $orderNumber,
                    $user['id'] ?? null,
                    $user ? null : $name,
                    $user ? null : ($email ?: null),
                    $user ? null : ($phone ?: null),
                    decimalMoney($totals['subtotal']),
                    decimalMoney($totals['discount']),
                    decimalMoney($totals['total']),
                    hash('sha256', $checkoutKey),
                    $user ? null : hash('sha256', $guestToken)
                ]
            );
            $orderId = (int) $pdo->lastInsertId();
            foreach ($items as $product) {
                db(
                    '
                        INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ',
                    [
                        $orderId,
                        $product['id'],
                        $product['name'],
                        $product['quantity'],
                        $product['price'],
                        decimalMoney(moneyCents($product['price']) * $product['quantity'])
                    ]
                );
            }
            $pdo->commit();

            // Keranjang baru dikosongkan setelah transaksi benar-benar berhasil.
            if (!$user) {
                $_SESSION['guest_tokens'][$orderId] = $guestToken;
            }
            $_SESSION['cart'] = [];
            unset($_SESSION['checkout_key']);
            redirect('order_success.php?id=' . $orderId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof InvalidArgumentException) {
                $errors[] = $exception->getMessage();
            } else {
                error_log((string) $exception);
                $errors[] = 'Pesanan belum tersimpan. Keranjang tetap aman; silakan coba lagi.';
            }
        }
    }
}

// Ambil ulang data keranjang untuk tampilan awal atau setelah validasi gagal.
try {
    $items = cartItems();
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
}

$totals = orderTotals($items, $user['role'] ?? null);
pageStart('Checkout');
?>

<h1>Sedikit lagi, siap dinikmati.</h1>
<p class="muted">Periksa pesananmu. Bayar langsung saat mengambil di kasir.</p>

<?php errors($errors); ?>

<?php if (!$items): ?>
    <div class="empty">
        <p>Keranjang kosong atau ada menu yang tidak tersedia.</p>
        <a class="button" href="<?= url('cart/index.php') ?>">Kembali ke keranjang</a>
    </div>
<?php else: ?>
    <form method="post" class="split">

        <?= csrfField() ?>
        <input type="hidden" name="checkout_key" value="<?= e($checkoutKey) ?>">
        <section class="panel">
            <h2>Detail pemesan</h2>

            <?php if ($user): ?>
                <p><?= e($user['name']) ?>
                    <br><?= e($user['email']) ?>
                </p>
                <div class="notice">Diskon pelanggan 10% otomatis diterapkan.</div>
            <?php else: ?>
                <label for="guest_name">Nama</label>
                <input id="guest_name" name="guest_name" maxlength="150" value="<?= e(input('guest_name')) ?>"
                    autocomplete="name" required>
                <label for="guest_email">Email</label>
                <input id="guest_email" name="guest_email" type="email" maxlength="190"
                    value="<?= e(input('guest_email')) ?>" autocomplete="email">
                <label for="guest_phone">Nomor telepon</label>
                <input id="guest_phone" name="guest_phone" type="tel" maxlength="40"
                    value="<?= e(input('guest_phone')) ?>" autocomplete="tel">
                <p class="help">Isi minimal email atau telepon. <a href="<?= url( 'auth/login.php' ) ?>">Masuk</a>
                    untuk diskon 10%.</p>
            <?php endif; ?>

        </section>
        <section class="panel summary">
            <h2>Pesananmu</h2>

            <?php foreach ($items as $product): ?>
                <p>
                    <span><?= e($product['name']) ?> × <?= $product['quantity'] ?>
                        <br>
                        <small><?= rupiah($product['price']) ?> / item</small>
                    </span>
                    <strong><?= rupiah(decimalMoney(moneyCents($product['price']) * $product['quantity'])) ?></strong>
                </p>
            <?php endforeach; ?>

            <hr>
            <p>
                <span>Subtotal</span>
                <span><?= rupiah(decimalMoney($totals['subtotal'])) ?></span>
            </p>
            <p>
                <span>Diskon (<?= $user ? '10' : '0' ?>%)</span>
                <span>−<?= rupiah(decimalMoney($totals['discount'])) ?></span>
            </p>
            <p class="total">
                <span>Total</span>
                <span><?= rupiah(decimalMoney($totals['total'])) ?></span>
            </p>
            <button type="submit">Buat pesanan</button>
            <p>
                <a href="<?= url('cart/index.php') ?>">Ubah keranjang</a>
            </p>
            <p class="help">Pesanan ini menggunakan pembayaran manual. Belum ada pembayaran yang
                ditagih.</p>
        </section>
    </form>
<?php endif; ?>

<?php pageEnd(); ?>
