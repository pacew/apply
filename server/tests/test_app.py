"""End-to-end tests for the WSGI routing, auth and data endpoints.

No database and no network are used: the live-data functions are patched.
"""

import io
import json
import os
import sys
import tempfile
import unittest
from unittest import mock
from urllib.parse import urlencode
from wsgiref.util import setup_testing_defaults

SERVER_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if SERVER_DIR not in sys.path:
    sys.path.insert(0, SERVER_DIR)

import app
import auth
import live_data


class AppTests(unittest.TestCase):
    PASSWORD = "correct horse battery"

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.home = self.temp.name
        os.environ["PROGRAM_HOME"] = self.home
        os.makedirs(os.path.join(self.home, "web"))
        with open(os.path.join(self.home, "web", "index.html"), "w") as stream:
            stream.write("<html><body>page</body></html>")
        with open(os.path.join(self.home, "web", "config.json"), "w") as stream:
            stream.write('{"format":"config.json v1"}')
        with open(os.path.join(self.home, "config.json"), "w") as stream:
            json.dump(
                {
                    "password_hash": auth.hash_password(self.PASSWORD),
                    "cookie_secret": "s" * 64,
                    "year": 2027,
                    "years": [2027, 2026],
                    "apply_env": "production",
                    "schedule_env": "production",
                },
                stream,
            )
        _status, _headers, self.cookie = self.login()

    def tearDown(self):
        os.environ.pop("PROGRAM_HOME", None)
        self.temp.cleanup()

    # ------------------------------------------------------------------ helpers

    def call(
        self,
        method="GET",
        path="/",
        query="",
        form=None,
        cookie="",
        script="/program.py",
        json_body=None,
        origin="",
        token="",
        authorization="",
    ):
        if json_body is not None:
            body = json.dumps(json_body).encode("utf-8")
            content_type = "application/json"
        elif form is not None:
            body = urlencode(form).encode("utf-8")
            content_type = "application/x-www-form-urlencoded"
        else:
            body = b""
            content_type = ""
        values = {
            "REQUEST_METHOD": method,
            "PATH_INFO": path,
            "SCRIPT_NAME": script,
            "QUERY_STRING": query,
            "CONTENT_LENGTH": str(len(body)),
            "CONTENT_TYPE": content_type,
            "HTTP_COOKIE": cookie,
            "HTTP_ORIGIN": origin,
            "HTTP_X_NEFFA_TOKEN": token,
            "HTTP_AUTHORIZATION": authorization,
            "HTTPS": "on",
            "wsgi.url_scheme": "https",
            "SERVER_NAME": "apply.neffa.org",
            "SERVER_PORT": "443",
            "wsgi.input": io.BytesIO(body),
        }
        environ = dict(values)
        setup_testing_defaults(environ)
        environ.update(values)
        captured = {}

        def start_response(status, headers):
            captured["status"] = status
            captured["headers"] = headers

        result = app.application(environ, start_response)
        return captured["status"], captured["headers"], b"".join(result)

    def header(self, headers, name):
        for key, value in headers:
            if key.lower() == name.lower():
                return value
        return ""

    def cookie_value(self, headers, name="neffa_program"):
        for key, value in headers:
            if key.lower() == "set-cookie" and value.startswith(name + "="):
                return value.split(";", 1)[0]
        return ""

    def login(self, password=None, form_extra=None):
        form = {"password": password or self.PASSWORD}
        if form_extra:
            form.update(form_extra)
        status, headers, _ = self.call("POST", "/login", form=form)
        cookie = self.cookie_value(headers)
        return status, headers, cookie

    # -------------------------------------------------------------------- tests

    def test_health_needs_no_login(self):
        status, _, body = self.call("GET", "/health")
        self.assertEqual(status, "200 OK")
        self.assertEqual(body, b"ok")

    def test_root_redirects_to_login(self):
        status, headers, _ = self.call()
        self.assertEqual(status, "302 Found")
        self.assertTrue(self.header(headers, "Location").startswith("/program.py/login"))

    def test_login_page(self):
        status, _, body = self.call(path="/login")
        self.assertEqual(status, "200 OK")
        self.assertIn(b'name="password"', body)

    def test_login_failure(self):
        status, _, body = self.call("POST", "/login", form={"password": "wrong"})
        self.assertEqual(status, "401 Unauthorized")
        self.assertIn(b"did not match", body)

    def test_login_success_and_page(self):
        self.assertTrue(self.cookie.startswith("neffa_program="))
        status, _, body = self.call(cookie=self.cookie)
        self.assertEqual(status, "200 OK")
        self.assertIn(b"page", body)

    def test_logout_clears_cookie(self):
        status, headers, _ = self.call(path="/logout", cookie=self.cookie)
        self.assertEqual(status, "302 Found")
        cookie = self.cookie_value(headers)
        self.assertTrue(cookie)
        self.assertIn("Max-Age=0", self.header(headers, "Set-Cookie"))

    def test_data_needs_login(self):
        status, _, _ = self.call(path="/data/config.json")
        self.assertEqual(status, "401 Unauthorized")

    def test_config_data(self):
        status, _, body = self.call(path="/data/config.json", cookie=self.cookie)
        self.assertEqual(status, "200 OK")
        self.assertIn(b"config.json v1", body)

    def test_performers_data(self):
        data = live_data.DataFile(
            body=b"1\tA\t\tPC\n",
            content_type="text/tab-separated-values; charset=utf-8",
            last_modified=1700000000,
            version="v1",
            source="live",
        )
        with mock.patch.object(live_data, "get_performers", return_value=data):
            status, headers, body = self.call(path="/data/Performers.csv", cookie=self.cookie)
        self.assertEqual(status, "200 OK")
        self.assertEqual(body, b"1\tA\t\tPC\n")
        self.assertEqual(self.header(headers, "X-Data-Source"), "live")
        self.assertTrue(self.header(headers, "Last-Modified"))

    def test_applications_data(self):
        data = live_data.DataFile(
            body=b"BLANK,evid\n,T1a\n",
            content_type="text/csv; charset=utf-8",
            last_modified=1700000000,
            version="v1",
            source="cache",
        )
        with mock.patch.object(live_data, "get_applications", return_value=data):
            status, _, body = self.call(
                path="/data/Applications-2027.csv", cookie=self.cookie
            )
        self.assertEqual(status, "200 OK")
        self.assertIn(b"T1a", body)

    def test_stale_cache_is_marked(self):
        data = live_data.DataFile(
            body=b"1\tA\t\t\n",
            content_type="text/tab-separated-values; charset=utf-8",
            last_modified=1700000000,
            version="v1",
            source="stale",
        )
        with mock.patch.object(live_data, "get_members", return_value=data):
            status, headers, _ = self.call(path="/data/Members.csv", cookie=self.cookie)
        self.assertEqual(status, "200 OK")
        self.assertEqual(self.header(headers, "X-Data-Stale"), "1")

    def test_data_error_is_503(self):
        with mock.patch.object(
            live_data, "get_performers", side_effect=live_data.DataError("no")
        ):
            status, _, _ = self.call(path="/data/Performers.csv", cookie=self.cookie)
        self.assertEqual(status, "503 Service Unavailable")

    def test_unsupported_is_501(self):
        with mock.patch.object(
            live_data, "get_applications", side_effect=live_data.Unsupported("not yet")
        ):
            status, _, _ = self.call(
                path="/data/Applications-2027.csv", cookie=self.cookie
            )
        self.assertEqual(status, "501 Not Implemented")

    def test_unknown_data_is_404(self):
        status, _, _ = self.call(path="/data/Nope.csv", cookie=self.cookie)
        self.assertEqual(status, "404 Not Found")

    def test_path_traversal_is_404(self):
        status, _, _ = self.call(
            path="/data/..%2F..%2Fetc%2Fpasswd", cookie=self.cookie
        )
        self.assertEqual(status, "404 Not Found")

    def test_version(self):
        with mock.patch.object(
            live_data, "version_snapshot", return_value={"year": 2027}
        ):
            status, _, body = self.call(path="/version", cookie=self.cookie)
        self.assertEqual(status, "200 OK")
        self.assertEqual(json.loads(body.decode("utf-8")), {"year": 2027})

    def test_env_query_sets_cookie_and_redirects(self):
        status, headers, _ = self.call(
            path="/", query="year=2026&apply=test&schedule=test", cookie=self.cookie
        )
        self.assertEqual(status, "302 Found")
        self.assertEqual(self.header(headers, "Location"), "/program.py/")
        self.assertIn("neffa_program_env=", self.header(headers, "Set-Cookie"))

    def test_login_honours_env_form_fields(self):
        status, headers, _ = self.login(
            form_extra={"year": "2026", "apply": "test", "schedule": "test"}
        )
        self.assertEqual(status, "302 Found")
        cookies = [value for key, value in headers if key.lower() == "set-cookie"]
        self.assertTrue(any("neffa_program_env=" in value for value in cookies))

    # ------------------------------------------------------------------ json api

    def api_login(self):
        status, _, body = self.call(
            "POST", "/api/v1/login", json_body={"password": self.PASSWORD}
        )
        self.assertEqual(status, "200 OK")
        return json.loads(body.decode("utf-8"))

    def test_api_login_returns_token_and_user(self):
        data = self.api_login()
        self.assertTrue(data["token"])
        self.assertEqual(data["user"]["name"], "Program Chair")
        self.assertEqual(data["user"]["role"], "program_chair")
        self.assertEqual(data["year"], 2027)
        self.assertGreater(data["expiresIn"], 0)

    def test_api_login_wrong_password(self):
        status, _, body = self.call(
            "POST", "/api/v1/login", json_body={"password": "wrong"}
        )
        self.assertEqual(status, "401 Unauthorized")
        self.assertEqual(json.loads(body.decode("utf-8"))["error"], "invalid_password")

    def test_api_me_with_x_neffa_token(self):
        token = self.api_login()["token"]
        status, _, body = self.call("GET", "/api/v1/me", token=token)
        self.assertEqual(status, "200 OK")
        me = json.loads(body.decode("utf-8"))
        self.assertEqual(me["name"], "Program Chair")
        self.assertEqual(me["role"], "program_chair")
        self.assertEqual(me["year"], 2027)
        self.assertEqual(me["apply"], "production")
        self.assertEqual(me["schedule"], "production")

    def test_api_me_with_authorization_bearer(self):
        token = self.api_login()["token"]
        status, _, _ = self.call(
            "GET", "/api/v1/me", authorization="Bearer " + token
        )
        self.assertEqual(status, "200 OK")

    def test_api_me_without_token(self):
        status, _, body = self.call("GET", "/api/v1/me")
        self.assertEqual(status, "401 Unauthorized")
        self.assertEqual(json.loads(body.decode("utf-8"))["error"], "not_signed_in")

    def test_api_me_query_selects_workspace(self):
        token = self.api_login()["token"]
        status, _, body = self.call(
            "GET",
            "/api/v1/me",
            query="year=2026&apply=test&schedule=test",
            token=token,
        )
        self.assertEqual(status, "200 OK")
        me = json.loads(body.decode("utf-8"))
        self.assertEqual(me["year"], 2026)
        self.assertEqual(me["apply"], "test")
        self.assertEqual(me["schedule"], "test")

    def test_cors_preflight_for_dev_origin(self):
        status, headers, _ = self.call(
            "OPTIONS", "/api/v1/me", origin="http://localhost:18411"
        )
        self.assertEqual(status, "204 No Content")
        self.assertEqual(
            self.header(headers, "Access-Control-Allow-Origin"),
            "http://localhost:18411",
        )
        self.assertIn(
            "X-Neffa-Token", self.header(headers, "Access-Control-Allow-Headers")
        )

    def test_cors_disallowed_origin_gets_no_allow_header(self):
        status, headers, _ = self.call(
            "OPTIONS", "/api/v1/me", origin="http://evil.example"
        )
        self.assertEqual(status, "204 No Content")
        self.assertEqual(self.header(headers, "Access-Control-Allow-Origin"), "")

    def test_cors_on_authenticated_me(self):
        token = self.api_login()["token"]
        status, headers, _ = self.call(
            "GET",
            "/api/v1/me",
            token=token,
            origin="http://localhost:18411",
        )
        self.assertEqual(status, "200 OK")
        self.assertEqual(
            self.header(headers, "Access-Control-Allow-Origin"),
            "http://localhost:18411",
        )


if __name__ == "__main__":
    unittest.main()
