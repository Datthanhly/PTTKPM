"""Smoke-check the local demo server using real cookies and CSRF tokens.

Usage: python scripts/check-http.py [http://127.0.0.1:8000]
Uses demo accounts only. Override CLINIC_SMOKE_PASSWORD if needed.
"""
import http.cookiejar
import os
import re
import sys
import urllib.parse
import urllib.request
import urllib.error

base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000").rstrip("/")
if urllib.parse.urlparse(base).hostname not in {"127.0.0.1", "localhost"}:
    raise SystemExit("Only a local demo server is supported.")


def csrf(html):
    match = re.search(r'name="_token"\s+value="([^"]+)"', html)
    if not match:
        raise RuntimeError("CSRF token not found")
    return match.group(1)


def fetch(client, path, data=None):
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    with client.open(base + path, payload, timeout=15) as response:
        return response.geturl(), response.read().decode("utf-8"), response.headers


accounts = {
    "benhnhan@clinic.test": ["/lich-kham", "/lich-kham/dat-lich", "/ho-so", "/lich-su-kham", "/tai-kham"],
    "letan@clinic.test": ["/nhan-vien/lich-hen", "/benh-nhan", "/luot-kham", "/lich-kham/dat-lich"],
    "bacsi.an@clinic.test": ["/luot-kham", "/chan-doan", "/kho", "/nhan-vien/lich-hen"],
    "thungan@clinic.test": ["/luot-kham", "/bao-cao", "/nhan-vien/lich-hen"],
    "admin@clinic.test": ["/quan-tri/danh-muc", "/quan-tri/cau-hinh", "/bao-cao", "/kho", "/nhan-vien/lich-hen"],
}

try:
    for email, pages in accounts.items():
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        _, login, _ = fetch(client, "/dang-nhap")
        url, dashboard, headers = fetch(client, "/dang-nhap", {"_token": csrf(login), "email": email, "password": os.environ.get("CLINIC_SMOKE_PASSWORD", "Password@123")})
        if not url.endswith("/bang-dieu-khien") or headers.get("X-Clinic-App") != "n05-group3":
            raise RuntimeError("Demo login failed: " + email)
        for page in pages:
            url, _, _ = fetch(client, page)
            if urllib.parse.urlparse(url).path != page:
                raise RuntimeError("Unexpected redirect: " + page)
        _, home, _ = fetch(client, "/")
        can_book = email in {"benhnhan@clinic.test", "letan@clinic.test"}
        if ('href="' + base + '/lich-kham/dat-lich"' in home) != can_book:
            raise RuntimeError("Booking navigation does not match role: " + email)
        if not can_book:
            url, html, _ = fetch(client, "/lich-kham/dat-lich")
            if not url.endswith("/bang-dieu-khien") or "Đặt lịch khám dành cho tài khoản Bệnh nhân hoặc Lễ tân." not in html:
                raise RuntimeError("Booking redirect failed: " + email)
            try:
                fetch(client, "/lich-kham", {"_token": csrf(html)})
            except urllib.error.HTTPError as error:
                if error.code != 403:
                    raise
            else:
                raise RuntimeError("Unauthorized booking POST was accepted: " + email)
        fetch(client, "/dang-xuat", {"_token": csrf(dashboard)})
        print("PASS", email, "login/pages/logout")
    public = urllib.request.build_opener()
    for page in ["/", "/bac-si", "/app.css"]:
        fetch(public, page)
    print("PASS public pages and CSS")
except Exception as error:
    raise SystemExit(str(error))
