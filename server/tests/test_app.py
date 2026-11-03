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

    def call(self, method="GET", path="/", query="", form=None, cookie="", script="/program.py"):
        body = urlencode(form).encode("utf-8") if form is not None else b""
        environ = {
            "REQUEST_METHOD": method,
            "PATH_INFO": path,
            "SCRIPT_NAME": script,
            "QUERY_STRING": query,
            "CONTENT_LENGTH": str(len(body)),
            "CONTENT_TYPE": "application/x-www-form-urlencoded" if form is not None else "",
            "HTTP_COOKIE": cookie,
            "HTTPS": "on",
            "wsgi.url_scheme": "https",
            "SERVER_NAME": "apply.neffa.org",
            "SERVER_PORT": "443",
            "wsgi.input": io.BytesIO(body),
        }
        setup_testing_defaults(environ)
        environ.update(
            {
                "REQUEST_METHOD": method,
                "PATH_INFO": path,
                "SCRIPT_NAME": script,
                "QUERY_STRING": query,
                "CONTENT_LENGTH": str(len(body)),
                "HTTP_COOKIE": cookie,
                "HTTPS": "on",
                "wsgi.url_scheme": "https",
                "wsgi.input": io.BytesIO(body),
            }
        )
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


if __name__ == "__main__":
    unittest.main()
