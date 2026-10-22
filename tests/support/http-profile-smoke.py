"""Read-only HTTP smoke for the two isolated QA profiles; no browser or fixtures."""

import http.cookiejar
import html
import json
import re
import sys
import urllib.error
import urllib.request


def response(opener, url, payload=None, token=None):
    headers = {"Accept": "application/json"} if payload is not None else {}
    if token:
        headers["X-CSRF-TOKEN"] = token
    data = None
    if payload is not None:
        headers["Content-Type"] = "application/json"
        data = json.dumps(payload).encode()
    request = urllib.request.Request(url, data=data, headers=headers)
    try:
        result = opener.open(request, timeout=30)
    except urllib.error.HTTPError as error:
        result = error
    return result.code, result.read().decode()


def main(base):
    if base not in ("http://127.0.0.1:8082", "http://127.0.0.1:8083"):
        raise ValueError("Only isolated QA profiles are allowed")
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
    )
    assert response(opener, base + "/up")[0] == 200
    status, page = response(opener, base + "/login")
    assert status == 200
    match = re.search(r'name="_token"\s+value="([^"]+)"', page)
    assert match, "Missing CSRF form token"
    token = html.unescape(match[1])
    assert response(opener, base + "/login", {"email": []})[0] == 419
    for path in ("/login", "/register"):
        status, body = response(opener, base + path, {"email": []}, token)
        assert status == 422, f"Invalid email type at {path}: {status}"
        assert "email" in json.loads(body)["errors"]
    print(f"PASS: {base} /up + /login 200, CSRF 419, email array rejected 422")


if __name__ == "__main__":
    main(sys.argv[1])
