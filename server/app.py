"""WSGI application for the NEFFA program system.

Routes (the trampoline is at /program.py, so SCRIPT_NAME is /program.py):

    /                       the prototype page
    /login                  password form; POST checks it
    /logout                 clear the session cookie
    /data/                  a listing of the available data files
    /data/config.json       settings for the prototype
    /data/Applications-N.csv  live applications for year N
    /data/Performers.csv    live performer list
    /data/Members.csv       live group memberships
    /data/Webgrid-2026.tsv  last year's published grid, if present
    /data/Webgrid-2027.tsv  this year's grid, if present
    /version                small JSON the page can poll
    /health                 "ok", no login, no data

Authentication is one shared committee password.  The session cookie is signed;
see auth.py.  Data files are cached in PROGRAM_HOME/cache and rebuilt only when
the source version changes (see live_data.py).
"""

from __future__ import annotations

import html
import json
import os
import re
import time
import traceback
from email.utils import formatdate
from pathlib import Path
from urllib.parse import parse_qs, quote, unquote
from wsgiref.handlers import CGIHandler

import auth
import live_data
import settings

DATA_NAME = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]*$")
APPLICATIONS_NAME = re.compile(r"^Applications-(\d{4})\.csv$")
MAX_FORM_BYTES = 8192

SECURITY_HEADERS = (
    ("X-Content-Type-Options", "nosniff"),
    ("X-Frame-Options", "DENY"),
    ("Referrer-Policy", "no-referrer"),
)


# --------------------------------------------------------------------- responses

def _bytes(body) -> bytes:
    return body if isinstance(body, bytes) else str(body).encode("utf-8")


def _response(start_response, status, body, content_type, headers=None):
    payload = _bytes(body)
    all_headers = list(SECURITY_HEADERS)
    if headers:
        all_headers.extend(headers)
    all_headers.append(("Content-Type", content_type))
    all_headers.append(("Content-Length", str(len(payload))))
    start_response(status, all_headers)
    return [payload]


def _text(start_response, status, message, headers=None):
    return _response(
        start_response,
        status,
        message,
        "text/plain; charset=utf-8",
        [("Cache-Control", "no-store")] + list(headers or []),
    )


def _html(start_response, status, body, headers=None):
    return _response(
        start_response,
        status,
        body,
        "text/html; charset=utf-8",
        [("Cache-Control", "no-store")] + list(headers or []),
    )


def _redirect(start_response, location, cookies=None):
    headers = [("Location", location), ("Cache-Control", "no-store")]
    for cookie in cookies or []:
        headers.append(("Set-Cookie", cookie))
    return _response(
        start_response, "302 Found", b"", "text/plain; charset=utf-8", headers
    )


def _json_response(start_response, status, payload, headers=None):
    return _response(
        start_response,
        status,
        json.dumps(payload, indent=2),
        "application/json; charset=utf-8",
        [("Cache-Control", "no-store")] + list(headers or []),
    )


def _cors_headers(environ, cfg):
    """Allow the development frontend origin, and only that origin.

    Production calls are same-origin and send no Origin header, so they get no
    CORS headers.  The development frontend at localhost:18411 sends Origin and
    an X-Neffa-Token or Authorization header.
    """
    origin = environ.get("HTTP_ORIGIN", "")
    if not origin:
        return []
    allowed = (cfg or {}).get("allowed_origins") or []
    if origin not in allowed:
        return [("Vary", "Origin")]
    return [
        ("Access-Control-Allow-Origin", origin),
        ("Vary", "Origin"),
        ("Access-Control-Allow-Credentials", "true"),
    ]


# --------------------------------------------------------------- request helpers

def _query(environ) -> dict:
    return parse_qs(environ.get("QUERY_STRING", ""), keep_blank_values=True)


def _read_form(environ) -> dict:
    try:
        length = int(environ.get("CONTENT_LENGTH") or 0)
    except (TypeError, ValueError):
        length = 0
    if length <= 0 or length > MAX_FORM_BYTES:
        return {}
    raw = environ["wsgi.input"].read(length)
    try:
        values = parse_qs(raw.decode("utf-8", "replace"), keep_blank_values=True)
    except (UnicodeDecodeError, ValueError):
        return {}
    return {key: item[0] for key, item in values.items() if item}


def _json_body(environ) -> dict:
    """Read a JSON object body, or form fields as a fallback."""
    try:
        length = int(environ.get("CONTENT_LENGTH") or 0)
    except (TypeError, ValueError):
        length = 0
    if length <= 0 or length > MAX_FORM_BYTES:
        return {}
    raw = environ["wsgi.input"].read(length)
    content_type = environ.get("CONTENT_TYPE", "").lower()
    if "application/json" in content_type:
        try:
            data = json.loads(raw.decode("utf-8", "replace"))
        except (UnicodeDecodeError, ValueError):
            return {}
        return data if isinstance(data, dict) else {}
    try:
        values = parse_qs(raw.decode("utf-8", "replace"), keep_blank_values=True)
    except (UnicodeDecodeError, ValueError):
        return {}
    return {key: item[0] for key, item in values.items() if item}


def _first(values: dict, name: str, default: str = "") -> str:
    value = values.get(name, default)
    if isinstance(value, list):
        return value[0] if value else default
    return value if value is not None else default


def _script(environ) -> str:
    return environ.get("SCRIPT_NAME", "") or ""


def _base_path(environ) -> str:
    script = _script(environ)
    return (script + "/") if script else "/"


def _cookie_path(environ) -> str:
    return _script(environ) or "/"


# ------------------------------------------------------------------------ config

def _load_config_or_error(start_response):
    try:
        return settings.load_config(), None
    except settings.ConfigError as exc:
        live_data.log("config error: %s" % exc)
        return None, _text(
            start_response,
            "500 Internal Server Error",
            "The program system is not configured yet. " + str(exc),
        )


# -------------------------------------------------------------------------- auth

def _request_token(environ, cfg) -> str:
    """The session token from the cookie, or from a header for the dev frontend.

    Apache may strip Authorization from CGI, so X-Neffa-Token is the reliable
    header.  Both are accepted; the cookie wins when both are present.
    """
    cookies = auth.parse_cookies(environ.get("HTTP_COOKIE"))
    token = cookies.get(cfg["cookie_name"], "")
    if token:
        return token
    header = environ.get("HTTP_X_NEFFA_TOKEN", "") or environ.get("HTTP_AUTHORIZATION", "")
    header = (header or "").strip()
    if header.lower().startswith("bearer "):
        header = header[7:].strip()
    return header


def _current_user(environ, cfg) -> dict | None:
    token = _request_token(environ, cfg)
    return auth.verify_session_token(cfg["cookie_secret"], token)


def _is_authenticated(environ, cfg) -> bool:
    return _current_user(environ, cfg) is not None


def _env_state(environ, cfg) -> dict:
    state = {
        "year": cfg["year"],
        "apply": cfg["apply_env"],
        "schedule": cfg["schedule_env"],
    }
    raw = auth.parse_cookies(environ.get("HTTP_COOKIE")).get(cfg["env_cookie_name"])
    if raw:
        try:
            stored = json.loads(raw)
        except (TypeError, ValueError):
            stored = None
        if isinstance(stored, dict):
            for key in state:
                if key in stored:
                    state[key] = stored[key]
    return _validated_env(state, cfg)


def _validated_env(state: dict, cfg: dict) -> dict:
    try:
        year = int(state.get("year", cfg["year"]))
    except (TypeError, ValueError):
        year = cfg["year"]
    if year not in cfg["years"]:
        year = cfg["year"]
    apply_env = str(state.get("apply", cfg["apply_env"]))
    if apply_env not in settings.APPLY_ENVS:
        apply_env = cfg["apply_env"]
    schedule_env = str(state.get("schedule", cfg["schedule_env"]))
    if schedule_env not in settings.SCHEDULE_ENVS:
        schedule_env = cfg["schedule_env"]
    return {"year": year, "apply": apply_env, "schedule": schedule_env}


def _apply_env_values(state: dict, values: dict, cfg: dict) -> tuple[dict, bool]:
    """Apply year/apply/schedule values from a query or form.  Return changed."""
    new = dict(state)
    changed = False
    if "year" in values:
        try:
            year = int(_first(values, "year"))
        except (TypeError, ValueError):
            year = None
        if year in cfg["years"] and year != new["year"]:
            new["year"] = year
            changed = True
    for key, allowed in (
        ("apply", settings.APPLY_ENVS),
        ("schedule", settings.SCHEDULE_ENVS),
    ):
        if key in values:
            value = _first(values, key)
            if value in allowed and value != new[key]:
                new[key] = value
                changed = True
    return new, changed


def _env_cookie(environ, cfg, state) -> str:
    return auth.make_cookie(
        cfg["env_cookie_name"],
        json.dumps(state, separators=(",", ":")),
        path=_cookie_path(environ),
        max_age=30 * 24 * 3600,
        secure=auth.is_https(environ),
    )


def _safe_next(value, environ) -> str:
    """Only allow a next path inside this app, to avoid an open redirect."""
    base = _base_path(environ)
    path = str(value or "").strip()
    if not path or not path.startswith(base):
        return base
    if path.startswith("//") or "\\" in path or ".." in path:
        return base
    return path


def _handle_login(environ, start_response, cfg, method: str, query: dict):
    state = _env_state(environ, cfg)
    state, _ = _apply_env_values(state, query, cfg)
    next_path = _safe_next(_first(query, "next"), environ)

    if method == "GET":
        if _is_authenticated(environ, cfg):
            return _redirect(start_response, next_path)
        page = auth.login_page(
            script=_script(environ),
            next_path=next_path,
            year=state["year"],
            apply_env=state["apply"],
            schedule_env=state["schedule"],
        )
        return _html(
            start_response,
            "200 OK",
            page,
            [("Set-Cookie", _env_cookie(environ, cfg, state))],
        )

    if method != "POST":
        return _text(start_response, "405 Method Not Allowed", "Use GET or POST.")

    form = _read_form(environ)
    next_path = _safe_next(form.get("next"), environ)
    state, _ = _apply_env_values(state, form, cfg)
    password = form.get("password", "")
    if not auth.verify_password(password, cfg["password_hash"]):
        live_data.log("login failed")
        page = auth.login_page(
            script=_script(environ),
            next_path=next_path,
            year=state["year"],
            apply_env=state["apply"],
            schedule_env=state["schedule"],
            error="That password did not match.",
        )
        return _html(
            start_response,
            "401 Unauthorized",
            page,
            [("Set-Cookie", _env_cookie(environ, cfg, state))],
        )

    token = auth.new_session_token(
        cfg["cookie_secret"],
        cfg["session_hours"],
        name=cfg["actor_name"],
        role=cfg["role"],
    )
    session_cookie = auth.make_cookie(
        cfg["cookie_name"],
        token,
        path=_cookie_path(environ),
        max_age=int(cfg["session_hours"] * 3600),
        secure=auth.is_https(environ),
    )
    live_data.log("login ok")
    return _redirect(
        start_response,
        next_path,
        [session_cookie, _env_cookie(environ, cfg, state)],
    )


def _handle_logout(environ, start_response, cfg):
    cookie = auth.make_cookie(
        cfg["cookie_name"],
        "",
        path=_cookie_path(environ),
        max_age=0,
        secure=auth.is_https(environ),
    )
    return _redirect(start_response, _base_path(environ) + "login", [cookie])


# ---------------------------------------------------------------------- json api

def _handle_api_login(environ, start_response, cfg, method: str):
    """Sign in from the development frontend and return a bearer token.

    The same signed token also goes into the cookie, so the production page
    keeps working exactly as before.  The JSON token is for the cross-origin
    development frontend, where a SameSite cookie is awkward.
    """
    if method != "POST":
        return _json_response(
            start_response, "405 Method Not Allowed", {"error": "method_not_allowed"}
        )
    body = _json_body(environ)
    password = str(body.get("password", ""))
    if not auth.verify_password(password, cfg["password_hash"]):
        live_data.log("api login failed")
        return _json_response(
            start_response, "401 Unauthorized", {"error": "invalid_password"}
        )

    state = _env_state(environ, cfg)
    state, _ = _apply_env_values(state, body, cfg)
    token = auth.new_session_token(
        cfg["cookie_secret"],
        cfg["session_hours"],
        name=cfg["actor_name"],
        role=cfg["role"],
    )
    cookies = [
        auth.make_cookie(
            cfg["cookie_name"],
            token,
            path=_cookie_path(environ),
            max_age=int(cfg["session_hours"] * 3600),
            secure=auth.is_https(environ),
        ),
        _env_cookie(environ, cfg, state),
    ]
    live_data.log("api login ok")
    return _json_response(
        start_response,
        "200 OK",
        {
            "token": token,
            "user": {"name": cfg["actor_name"], "role": cfg["role"]},
            "expiresIn": int(cfg["session_hours"] * 3600),
            "year": state["year"],
            "apply": state["apply"],
            "schedule": state["schedule"],
        },
        [("Set-Cookie", cookie) for cookie in cookies],
    )


def _handle_api_me(environ, start_response, cfg):
    """Who am I, and which workspace is this browser using?"""
    user = _current_user(environ, cfg)
    if not user:
        return _json_response(
            start_response, "401 Unauthorized", {"error": "not_signed_in"}
        )
    state = _env_state(environ, cfg)
    state, _ = _apply_env_values(state, _query(environ), cfg)
    return _json_response(
        start_response,
        "200 OK",
        {
            "name": user.get("u") or cfg["actor_name"],
            "role": user.get("r") or cfg["role"],
            "year": state["year"],
            "apply": state["apply"],
            "schedule": state["schedule"],
            "expiresAt": user.get("exp"),
        },
    )


# ------------------------------------------------------------------------- pages

def _serve_page(start_response, cfg):
    page = Path(cfg["page"])
    try:
        body = page.read_bytes()
        modified = page.stat().st_mtime
    except OSError:
        return _text(
            start_response,
            "500 Internal Server Error",
            "The prototype page is not installed. Copy dist/deploy/index.html to %s."
            % page,
        )
    return _response(
        start_response,
        "200 OK",
        body,
        "text/html; charset=utf-8",
        [
            ("Cache-Control", "no-store"),
            ("Last-Modified", formatdate(modified, usegmt=True)),
        ],
    )


def _serve_apitest(environ, start_response):
    """The developer's API console.  One HTML file, no build step."""
    page = Path(__file__).resolve().parent / "apitest.html"
    try:
        body = page.read_text(encoding="utf-8")
    except OSError:
        return _text(
            start_response,
            "500 Internal Server Error",
            "The API test page is not installed.",
        )
    body = body.replace("__BASE__", html.escape(_script(environ)))
    return _response(
        start_response,
        "200 OK",
        body,
        "text/html; charset=utf-8",
        [("Cache-Control", "no-store")],
    )


def _serve_listing(start_response, cfg, state):
    names = [
        "config.json",
        "Applications-%d.csv" % state["year"],
        "Performers.csv",
        "Members.csv",
    ]
    if live_data.get_schedule(cfg, "Webgrid-2026.tsv", state["schedule"]):
        names.append("Webgrid-2026.tsv")
    if live_data.get_schedule(cfg, "Webgrid-2027.tsv", state["schedule"]):
        names.append("Webgrid-2027.tsv")
    items = "\n".join(
        '<li><a href="%s">%s</a></li>' % (quote(name), html.escape(name))
        for name in names
    )
    page = (
        "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
        "<title>Program data files</title></head><body>"
        "<h1>Program data files</h1><ul>\n%s\n</ul></body></html>" % items
    )
    return _html(start_response, "200 OK", page)


# --------------------------------------------------------------------------- data

def _serve_data(start_response, cfg, state, name: str):
    if not DATA_NAME.match(name):
        return _text(start_response, "404 Not Found", "Not found.")

    found = None
    try:
        if name == "config.json":
            found = live_data.get_config(cfg)
        elif APPLICATIONS_NAME.match(name):
            year = int(APPLICATIONS_NAME.match(name).group(1))
            # Only the year the page asked for; the loader also probes the other
            # year, and building it would cost a download.php call for nothing.
            if year != state["year"]:
                return _text(start_response, "404 Not Found", "Not found.")
            found = live_data.get_applications(cfg, year, state["apply"])
        elif name == "Performers.csv":
            found = live_data.get_performers(cfg)
        elif name == "Members.csv":
            found = live_data.get_members(cfg)
        elif name in ("Webgrid-2026.tsv", "Webgrid-2027.tsv"):
            found = live_data.get_schedule(cfg, name, state["schedule"])
        else:
            return _text(start_response, "404 Not Found", "Not found.")
    except live_data.Unsupported as exc:
        return _text(start_response, "501 Not Implemented", str(exc))
    except live_data.DataError as exc:
        live_data.log("data error for %s: %s" % (name, exc))
        return _text(
            start_response,
            "503 Service Unavailable",
            "The live data is not available right now. Try again in a moment.",
        )

    if found is None:
        return _text(start_response, "404 Not Found", "Not found.")

    headers = [
        ("Cache-Control", "no-store"),
        ("X-Data-Source", found.source),
    ]
    if found.last_modified:
        headers.append(
            ("Last-Modified", formatdate(found.last_modified, usegmt=True))
        )
    if found.source == "stale":
        headers.append(("X-Data-Stale", "1"))
    return _response(start_response, "200 OK", found.body, found.content_type, headers)


def _serve_version(start_response, cfg, state):
    try:
        snapshot = live_data.version_snapshot(
            cfg, state["year"], state["apply"], state["schedule"]
        )
    except live_data.Unsupported as exc:
        return _text(start_response, "501 Not Implemented", str(exc))
    except live_data.DataError as exc:
        live_data.log("version error: %s" % exc)
        return _text(
            start_response,
            "503 Service Unavailable",
            "The live data is not available right now.",
        )
    return _response(
        start_response,
        "200 OK",
        json.dumps(snapshot, indent=2),
        "application/json; charset=utf-8",
        [("Cache-Control", "no-store")],
    )


# ------------------------------------------------------------------------ routing

def _handle_root(environ, start_response, cfg):
    state = _env_state(environ, cfg)
    state, changed = _apply_env_values(state, _query(environ), cfg)
    if changed:
        return _redirect(
            start_response,
            _base_path(environ),
            [_env_cookie(environ, cfg, state)],
        )
    return _serve_page(start_response, cfg)


def _dispatch(environ, start_response):
    method = environ.get("REQUEST_METHOD", "GET").upper()
    path = environ.get("PATH_INFO", "") or "/"
    script = _script(environ)

    # /program.py (no trailing slash) would make the page's relative data/ URL
    # point at the site root, so send it to /program.py/ first.  Apache may give
    # us PATH_INFO="" or "/" for that request; REQUEST_URI settles it.
    request_uri = environ.get("REQUEST_URI", "").split("?", 1)[0]
    if script and (request_uri == script or (path == "" and not script.endswith("/"))):
        return _redirect(start_response, script + "/")

    if path == "/health":
        return _text(start_response, "200 OK", "ok")

    cfg, error = _load_config_or_error(start_response)
    if error is not None:
        return error

    authenticated = _is_authenticated(environ, cfg)

    if path == "/login":
        return _handle_login(environ, start_response, cfg, method, _query(environ))

    if path == "/api/v1/login":
        return _handle_api_login(environ, start_response, cfg, method)

    if path == "/api/v1/me":
        return _handle_api_me(environ, start_response, cfg)

    if not authenticated:
        if path.startswith("/data/") or path == "/version":
            return _text(start_response, "401 Unauthorized", "Sign in first.")
        if path in ("/", "/index.html", "/apitest", "/apitest/"):
            # next must include SCRIPT_NAME (/program.py) or the login's
            # safe-next check will reject it.
            script = _script(environ)
            next_path = (script + path) if script else path
            return _redirect(
                start_response,
                _base_path(environ) + "login?next=" + quote(next_path),
            )
        return _text(start_response, "401 Unauthorized", "Sign in first.")

    if path in ("/", "/index.html"):
        return _handle_root(environ, start_response, cfg)

    if path == "/logout":
        return _handle_logout(environ, start_response, cfg)

    if path in ("/apitest", "/apitest/"):
        if not cfg.get("apitest", True):
            return _text(start_response, "404 Not Found", "Not found.")
        return _serve_apitest(environ, start_response)

    state = _env_state(environ, cfg)

    if path in ("/data", "/data/"):
        return _serve_listing(start_response, cfg, state)

    if path.startswith("/data/"):
        name = unquote(path[len("/data/"):])
        return _serve_data(start_response, cfg, state, name)

    if path == "/version":
        return _serve_version(start_response, cfg, state)

    if path == "/env":
        state, _ = _apply_env_values(state, _query(environ), cfg)
        return _redirect(
            start_response,
            _base_path(environ),
            [_env_cookie(environ, cfg, state)],
        )

    return _text(start_response, "404 Not Found", "Not found.")


def application(environ, start_response):
    started = time.time()
    method = environ.get("REQUEST_METHOD", "GET").upper()
    path = environ.get("PATH_INFO", "") or "/"

    try:
        cors_cfg = settings.load_config()
    except settings.ConfigError:
        cors_cfg = None
    cors = _cors_headers(environ, cors_cfg) if cors_cfg else []

    def with_cors(status, headers):
        start_response(status, list(headers) + cors)

    try:
        # CORS preflight for the development frontend.  It needs no login and
        # no config beyond the origin allowlist.
        if method == "OPTIONS":
            return _response(
                with_cors,
                "204 No Content",
                b"",
                "text/plain; charset=utf-8",
                [
                    (
                        "Access-Control-Allow-Methods",
                        "GET, POST, PUT, PATCH, DELETE, OPTIONS",
                    ),
                    (
                        "Access-Control-Allow-Headers",
                        "Content-Type, Authorization, X-Neffa-Token",
                    ),
                    ("Access-Control-Max-Age", "600"),
                ],
            )
        return _dispatch(environ, with_cors)
    except settings.ConfigError as exc:
        live_data.log("config error: %s" % exc)
        return _text(
            with_cors,
            "500 Internal Server Error",
            "The program system is not configured yet. " + str(exc),
        )
    except Exception:
        live_data.log(
            "unhandled error: "
            + traceback.format_exc().replace("\n", " | ")[:900]
        )
        return _text(
            with_cors,
            "500 Internal Server Error",
            "The program system had an unexpected problem.",
        )
    finally:
        live_data.log(
            "%s %s %.0fms" % (method, path, (time.time() - started) * 1000.0)
        )


def run_cgi():
    """Entry point for the program.py trampoline under Apache."""
    CGIHandler().run(application)


def main():
    """Run locally with the stdlib server, for development only."""
    from wsgiref.simple_server import make_server

    port = int(os.environ.get("PROGRAM_PORT", "8000"))
    print("Serving on http://127.0.0.1:%d/  (PROGRAM_HOME=%s)" % (port, settings.home()))
    make_server("127.0.0.1", port, application).serve_forever()


if __name__ == "__main__":
    main()
