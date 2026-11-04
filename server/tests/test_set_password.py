"""Tests for the two password slots and session preservation."""

import json
import os
import subprocess
import sys
import tempfile
import unittest

SERVER_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if SERVER_DIR not in sys.path:
    sys.path.insert(0, SERVER_DIR)

import auth


class SetPasswordTests(unittest.TestCase):
    def run_tool(self, config, slot, password, *extra):
        command = [
            sys.executable,
            os.path.join(SERVER_DIR, "tools", "set_password.py"),
            "--config",
            config,
            "--password-stdin",
            "--slot",
            slot,
        ]
        command.extend(extra)
        result = subprocess.run(
            command,
            input=password + "\n",
            text=True,
            capture_output=True,
            cwd=SERVER_DIR,
            timeout=30,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        with open(config) as stream:
            return json.load(stream)

    def test_two_slots_share_the_secret(self):
        with tempfile.TemporaryDirectory() as folder:
            config = os.path.join(folder, "config.json")
            first = self.run_tool(config, "1", "first password")
            secret = first["cookie_secret"]
            second = self.run_tool(config, "2", "second password")
            self.assertTrue(auth.verify_password("first password", second["password_hash"]))
            self.assertTrue(
                auth.verify_password("second password", second["password_hash_2"])
            )
            self.assertEqual(second["cookie_secret"], secret)

    def test_rotate_sessions_replaces_the_secret(self):
        with tempfile.TemporaryDirectory() as folder:
            config = os.path.join(folder, "config.json")
            first = self.run_tool(config, "1", "first password")
            second = self.run_tool(config, "2", "second password")
            self.assertEqual(second["cookie_secret"], first["cookie_secret"])
            rotated = self.run_tool(config, "1", "new first password", "--rotate-sessions")
            self.assertNotEqual(rotated["cookie_secret"], first["cookie_secret"])
            self.assertTrue(
                auth.verify_password("second password", rotated["password_hash_2"])
            )
            self.assertTrue(
                auth.verify_password("new first password", rotated["password_hash"])
            )

    def test_mode_is_honoured(self):
        with tempfile.TemporaryDirectory() as folder:
            config = os.path.join(folder, "config.json")
            self.run_tool(config, "1", "first password", "--mode", "644")
            self.assertEqual(os.stat(config).st_mode & 0o777, 0o644)


if __name__ == "__main__":
    unittest.main()
