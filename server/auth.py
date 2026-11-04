"""Password hashing, signed session cookies, and the login page.

There are two independent committee passwords for now, each stored only as a
scrypt hash in config.json.  Either one signs in; they exist so one person can
change theirs without locking the other out.  The session cookie is signed with
a separate random secret.  No third-party library is needed.

The browser gets a cookie whose value is ``payload.signature``.  The payload is
a small JSON object with an expiry time; the signature is HMAC-SHA256 over the
payload.  The server keeps no session table, so a restart does not sign anyone
out and a stolen cache file cannot forge a cookie.
"""

from __future__ import annotations

import base64
import hashlib
import hmac
import html
import json
import secrets
import time
from http.cookies import CookieError, SimpleCookie
from string import Template

SCRYPT_N = 16384
SCRYPT_R = 8
SCRYPT_P = 1
SCRYPT_MAXMEM = 64 * 1024 * 1024
SESSION_VERSION = 1


def _b64_encode(raw: bytes) -> str:
    return base64.urlsafe_b64encode(raw).decode("ascii").rstrip("=")


def _b64_decode(text: str) -> bytes:
    padding = "=" * (-len(text) % 4)
    return base64.urlsafe_b64decode(text + padding)


def hash_password(password: str) -> str:
    """Return a self-describing scrypt hash, safe to store in config.json."""
    if not password:
        raise ValueError("password must not be empty")
    salt = secrets.token_bytes(16)
    derived = hashlib.scrypt(
        password.encode("utf-8"),
        salt=salt,
        n=SCRYPT_N,
        r=SCRYPT_R,
        p=SCRYPT_P,
        dklen=32,
        maxmem=SCRYPT_MAXMEM,
    )
    return "scrypt$%d$%d$%d$%s$%s" % (
        SCRYPT_N,
        SCRYPT_R,
        SCRYPT_P,
        _b64_encode(salt),
        _b64_encode(derived),
    )


def verify_password(password: str, stored: str) -> bool:
    """Check a password against a hash from hash_password.  Never raises."""
    try:
        scheme, n, r, p, salt_text, hash_text = stored.split("$")
        if scheme != "scrypt":
            return False
        derived = hashlib.scrypt(
            (password or "").encode("utf-8"),
            salt=_b64_decode(salt_text),
            n=int(n),
            r=int(r),
            p=int(p),
            dklen=len(_b64_decode(hash_text)),
            maxmem=SCRYPT_MAXMEM,
        )
        return hmac.compare_digest(derived, _b64_decode(hash_text))
    except (ValueError, TypeError):
        return False


def verify_any_password(password: str, stored_hashes) -> bool:
    """Check a password against every configured slot.

    There are two independent password slots so one person can change theirs
    without locking the other out.  Both are checked without short-circuiting.
    """
    matched = False
    for stored in stored_hashes:
        if stored and verify_password(password, stored):
            matched = True
    return matched


def new_session_token(
    secret: str,
    hours: int,
    now: float | None = None,
    name: str = "",
    role: str = "",
) -> str:
    """A signed cookie value that expires after ``hours``.

    The name and role are carried in the signed payload so /api/v1/me and the
    program-chairs UI know who is acting.  There is one superuser for now.
    """
    issued = int(now if now is not None else time.time())
    expires = issued + int(hours * 3600)
    payload = _b64_encode(
        json.dumps(
            {"v": SESSION_VERSION, "exp": expires, "u": name or "", "r": role or ""},
            separators=(",", ":"),
        ).encode()
    )
    signature = _b64_encode(
        hmac.new(secret.encode("utf-8"), payload.encode("ascii"), hashlib.sha256).digest()
    )
    return payload + "." + signature


def verify_session_token(secret: str, token: str, now: float | None = None) -> dict | None:
    """Return the payload when the signature and expiry are good, else None."""
    try:
        payload_text, signature_text = (token or "").split(".", 1)
    except ValueError:
        return None
    expected = _b64_encode(
        hmac.new(
            secret.encode("utf-8"), payload_text.encode("ascii"), hashlib.sha256
        ).digest()
    )
    if not hmac.compare_digest(expected, signature_text):
        return None
    try:
        payload = json.loads(_b64_decode(payload_text))
    except (ValueError, TypeError):
        return None
    if not isinstance(payload, dict) or payload.get("v") != SESSION_VERSION:
        return None
    try:
        expires = int(payload.get("exp", 0))
    except (TypeError, ValueError):
        return None
    if expires <= int(now if now is not None else time.time()):
        return None
    return payload


def make_cookie(
    name: str,
    value: str,
    *,
    path: str,
    max_age: int | None = None,
    secure: bool = True,
    same_site: str = "Lax",
) -> str:
    """Build a Set-Cookie header value."""
    jar = SimpleCookie()
    jar[name] = value
    morsel = jar[name]
    morsel["path"] = path or "/"
    if max_age is not None:
        morsel["max-age"] = str(int(max_age))
    morsel["httponly"] = True
    morsel["samesite"] = same_site
    if secure:
        morsel["secure"] = True
    return morsel.OutputString()


def parse_cookies(header: str | None) -> dict:
    """Turn an HTTP_COOKIE header into a plain dictionary."""
    jar = SimpleCookie()
    try:
        jar.load(header or "")
    except CookieError:
        return {}
    return {key: morsel.value for key, morsel in jar.items()}


def is_https(environ: dict) -> bool:
    """True when the request reached us over HTTPS (Apache sets HTTPS=on)."""
    if str(environ.get("HTTPS", "")).lower() in ("on", "1"):
        return True
    forwarded = environ.get("HTTP_X_FORWARDED_PROTO", "")
    return forwarded.lower() == "https"


def login_cookie_path(script_name: str) -> str:
    """Cookies are scoped to /program.py, not the whole site."""
    return script_name or "/"


LOGIN_TEMPLATE = Template("""<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NEFFA Program System</title>
<style>
:root { color-scheme: light; }
body { margin: 0; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
       background: #f4f1ea; color: #1d1d1b; }
main { max-width: 30rem; margin: 12vh auto; padding: 2rem; background: #fff;
       border: 1px solid #d8d2c4; border-radius: 8px; box-shadow: 0 2px 12px #0001; }
h1 { font-size: 1.35rem; margin: 0 0 .25rem; }
p { margin: .5rem 0 1rem; }
.hint { color: #5a564d; font-size: .92rem; }
label { display: block; font-weight: 600; margin: 1rem 0 .35rem; }
input[type=password] { width: 100%; box-sizing: border-box; padding: .65rem; font-size: 1rem;
       border: 1px solid #b9b2a2; border-radius: 6px; }
button { margin-top: 1rem; padding: .65rem 1.1rem; font-size: 1rem; font-weight: 600;
       color: #fff; background: #33546b; border: 0; border-radius: 6px; cursor: pointer; }
button:hover { background: #264355; }
.error { color: #8a1f11; background: #fbe9e6; border: 1px solid #e2b4ad;
       padding: .5rem .7rem; border-radius: 6px; }
footer { margin-top: 1.25rem; color: #6b665c; font-size: .85rem; }
</style></head>
<body><main>
<h1>NEFFA Program System</h1>
<p>This area holds the program committee's working data. Enter the committee password.</p>
<p class="hint">Your festival year and test/production choices are under Settings after sign-in.</p>
$error_html
<form method="post" action="$script/login">
  <input type="hidden" name="next" value="$next_path">
  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
  <button type="submit">Sign in</button>
</form>
<footer>NEFFA Program System. The password is shared by the program committee.</footer>
</main></body></html>""")


def login_page(
    *,
    script: str,
    next_path: str,
    error: str = "",
) -> str:
    """The password form.  Values are escaped; there is no user-supplied HTML."""
    e = html.escape
    error_html = '<p class="error" role="alert">%s</p>' % e(error) if error else ""
    return LOGIN_TEMPLATE.substitute(
        error_html=error_html,
        script=e(script),
        next_path=e(next_path),
    )
