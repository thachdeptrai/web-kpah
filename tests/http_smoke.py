#!/usr/bin/env python3
"""HTTP integration smoke tests: cookies, CSRF, redirects, public assets and private paths.
The PHP development server router simulates the canonical Apache URL mapping.
It is NOT a substitute for staging tests under XAMPP/Apache/Cloudflare.
"""
import http.cookiejar
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = "http://127.0.0.1:9098"
cookies = http.cookiejar.CookieJar()

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

# Replace the default redirect handler BEFORE making requests. Adding it later
# leaves urllib's built-in redirect handler installed and masks 302 responses.
opener = urllib.request.build_opener(
    NoRedirect, urllib.request.HTTPCookieProcessor(cookies)
)
failures = []

def request(path, *, method="GET", data=None, headers=None):
    url = BASE+path
    raw = None if data is None else urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(url, data=raw, headers=headers or {}, method=method)
    try:
        resp = opener.open(req, timeout=12)
    except urllib.error.HTTPError as e:
        resp = e
    with resp:
        return resp.code, resp.read().decode("utf-8", errors="replace"), resp.headers

def check(cond, label):
    if cond:
        print("PASS", label)
    else:
        print("FAIL", label)
        failures.append(label)

def get_csrf(html):
    m = re.search(r'name="csrf"\s+value="([a-f0-9]{64})"', html)
    return m.group(1) if m else None

for path, title in [
    ("/", "NGHIỆN KPAH"), ("/dang-nhap", "Đăng nhập"),
    ("/dang-ky", "Đăng ký"), ("/quen-mat-khau", "mật khẩu"),
    ("/quan-tri/dang-nhap", "Đăng nhập quản trị")
]:
    status, page, _ = request(path)
    check(status == 200 and title.casefold() in page.casefold(), f"GET {path}: 200 and HTML")

status, player, _ = request("/dang-nhap")
status, admin, _ = request("/quan-tri/dang-nhap")
names = set(cookie.name for cookie in cookies)
check("KPAHREG" in names and "KPAHADMIN" in names, "player and admin have isolated PHP cookies")
csrf = get_csrf(admin)
check(bool(csrf), "admin login includes a session-bound CSRF token")
if csrf:
    status, html, _ = request("/quan-tri/dang-nhap", method="POST",
        data={"csrf":"deadbeef", "username":"", "password":""},
        headers={"Sec-Fetch-Site":"same-origin"})
    check(status == 403, "invalid admin CSRF returns 403")
    status, html, _ = request("/quan-tri/dang-nhap", method="POST",
        data={"csrf":csrf, "username":"", "password":""},
        headers={"Sec-Fetch-Site":"same-origin"})
    check(status == 200 and "Phiên đăng nhập đã thay đổi" not in html,
          "valid admin CSRF accepted without contacting database")
    status, html, _ = request("/quan-tri/dang-nhap", method="POST",
        data={"csrf":csrf, "username":"", "password":""},
        headers={"Sec-Fetch-Site":"cross-site"})
    check(status == 403, "cross-site admin POST is rejected despite valid token")

for path, destination in [
    ("/index.php", "/"), ("/login.php", "/dang-nhap"),
    ("/register.php", "/dang-ky"), ("/home.php", "/tai-khoan"),
    ("/profile.php", "/tai-khoan/ho-so"),
    ("/characters.php", "/tai-khoan/nhan-vat"),
    ("/rankings.php", "/bang-xep-hang"),
]:
    status, html, headers = request(path)
    check(status == 302 and headers.get("Location") == destination,
          f"legacy URL {path} redirects to {destination}")

for path, dest in [
    ("/tai-khoan", "/dang-nhap"),
    ("/tai-khoan/ho-so", "/dang-nhap"),
    ("/tai-khoan/nhan-vat", "/dang-nhap"),
    ("/bang-xep-hang", "/dang-nhap"),
    ("/nap", "/dang-nhap"),
    ("/nap/the", "/dang-nhap"),
    ("/nap/lich-su", "/dang-nhap"),
    ("/quan-tri", "/quan-tri/dang-nhap"),
    ("/quan-tri/nap", "/quan-tri/dang-nhap"),
]:
    status, _, headers = request(path)
    loc = headers.get("Location", "")
    check(status == 302 and loc.startswith(dest),
          f"unauthenticated {path} redirects securely")

for path in [
    "/assets/css/player-portal.css","/assets/css/topup.css",
    "/assets/img/rank-guardian.svg","/assets/js/register.js"
]:
    status, body, _ = request(path)
    check(status == 200 and len(body) > 50, f"public asset {path} exists")

for path in [
    "/app/Pages/Login.php", "/app/Security/WebSecurity.php",
    "/includes/security.php", "/tools/diagnostics/Check.php",
    "/.git/config", "/database/verify_schema.sql", "/storage/.htaccess"
]:
    status, _, _ = request(path)
    check(status == 403, f"private path {path} denied")

if failures:
    print(f"FAILED: {len(failures)} HTTP checks", file=sys.stderr)
    sys.exit(1)
print("PASS: HTTP route/session/CSRF regressions")
