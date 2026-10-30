"""Black-box acceptance tests. Run only against isolated test database/server (see README)."""

import os, urllib.request, urllib.parse, http.cookiejar, re

BASE = os.environ.get("TEST_BASE", "http://127.0.0.1:8099").rstrip("/")


class Browser:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar)
        )

    def request(self, path, data=None):
        req = urllib.request.Request(
            BASE + "/" + path,
            urllib.parse.urlencode(data).encode() if data is not None else None,
        )
        try:
            with self.opener.open(req) as r:
                return r.status, r.read(), r.url
        except urllib.error.HTTPError as r:
            return r.code, r.read(), r.url

    def token(self, path):
        s, b, u = self.request(path)
        m = re.search(rb'name="csrf_token" value="([^"]+)"', b)
        assert m, ("missing csrf", path, s, b[:200])
        return m.group(1).decode()


def check(value, message):
    assert value, message
    print("PASS", message)


if __name__ == "__main__":
    b = Browser()
    s, body, u = b.request("admin/products.php")
    check("auth/login.php" in u or s == 403, "guest cannot access product management")
