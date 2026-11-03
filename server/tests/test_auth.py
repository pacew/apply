"""Tests for password hashing, session cookies and the login page."""

import os
import sys
import unittest

SERVER_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if SERVER_DIR not in sys.path:
    sys.path.insert(0, SERVER_DIR)

import auth


class PasswordTests(unittest.TestCase):
    def test_hash_and_verify(self):
        stored = auth.hash_password("committee password")
        self.assertTrue(stored.startswith("scrypt$"))
        self.assertTrue(auth.verify_password("committee password", stored))

    def test_wrong_password_is_refused(self):
        stored = auth.hash_password("committee password")
        self.assertFalse(auth.verify_password("Committee Password", stored))
        self.assertFalse(auth.verify_password("", stored))

    def test_damaged_hash_is_refused(self):
        self.assertFalse(auth.verify_password("x", "not-a-hash"))
        self.assertFalse(auth.verify_password("x", "scrypt$bad"))


class SessionTests(unittest.TestCase):
    SECRET = "test-secret"

    def test_round_trip(self):
        token = auth.new_session_token(self.SECRET, 1, now=1000)
        payload = auth.verify_session_token(self.SECRET, token, now=1001)
        self.assertIsNotNone(payload)
        self.assertEqual(payload["exp"], 1000 + 3600)

    def test_expired(self):
        token = auth.new_session_token(self.SECRET, 1, now=1000)
        self.assertIsNone(auth.verify_session_token(self.SECRET, token, now=5000))

    def test_tampered(self):
        token = auth.new_session_token(self.SECRET, 1, now=1000)
        changed = token[:-1] + ("A" if token[-1] != "A" else "B")
        self.assertIsNone(auth.verify_session_token(self.SECRET, changed, now=1001))

    def test_wrong_secret(self):
        token = auth.new_session_token(self.SECRET, 1, now=1000)
        self.assertIsNone(
            auth.verify_session_token("another-secret", token, now=1001)
        )

    def test_cookie_parse(self):
        header = auth.make_cookie(
            "neffa_program", "value.here", path="/program.py", secure=True
        )
        parsed = auth.parse_cookies(header)
        self.assertEqual(parsed.get("neffa_program"), "value.here")


class LoginPageTests(unittest.TestCase):
    def test_error_is_escaped(self):
        page = auth.login_page(
            script="/program.py",
            next_path="/program.py/",
            year=2027,
            apply_env="production",
            schedule_env="production",
            error='<script>alert("x")</script>',
        )
        self.assertNotIn("<script>alert", page)
        self.assertIn("&lt;script&gt;", page)

    def test_form_action_and_values(self):
        page = auth.login_page(
            script="/program.py",
            next_path="/program.py/",
            year=2027,
            apply_env="production",
            schedule_env="production",
        )
        self.assertIn('action="/program.py/login"', page)
        self.assertIn('name="password"', page)
        self.assertIn('value="2027"', page)


if __name__ == "__main__":
    unittest.main()
