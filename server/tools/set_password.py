#!/usr/bin/python3
"""Set the shared committee password for the NEFFA program system.

Run this on the server, as the account that owns the files:

    python3 ~/apply.neffa.org/server/tools/set_password.py

It writes the scrypt hash and a new cookie-signing secret to
~/program/config.json (mode 0600), keeping any other settings already there.
Changing the password also signs out everyone who is already logged in.

Nothing is printed except the path and a short confirmation.
"""

from __future__ import annotations

import argparse
import getpass
import json
import os
import secrets
import sys
from pathlib import Path

SERVER_DIR = Path(__file__).resolve().parent.parent
if str(SERVER_DIR) not in sys.path:
    sys.path.insert(0, str(SERVER_DIR))

import auth  # noqa: E402
import settings  # noqa: E402


def read_password(from_stdin: bool) -> str:
    if from_stdin:
        password = sys.stdin.readline().rstrip("\r\n")
        if not password:
            raise SystemExit("No password on standard input.")
        return password
    first = getpass.getpass("New committee password: ")
    if len(first) < 8:
        raise SystemExit("Use at least 8 characters; 12 or more is better.")
    second = getpass.getpass("Type it again: ")
    if first != second:
        raise SystemExit("The two passwords did not match. Nothing changed.")
    return first


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--config",
        default=str(settings.home() / "config.json"),
        help="config.json to write (default: PROGRAM_HOME/config.json)",
    )
    parser.add_argument(
        "--password-stdin",
        action="store_true",
        help="read one password line from stdin (for tests; no confirmation)",
    )
    args = parser.parse_args()

    password = read_password(args.password_stdin)
    password_hash = auth.hash_password(password)

    path = Path(args.config)
    path.parent.mkdir(parents=True, exist_ok=True)
    existing = {}
    if path.is_file():
        try:
            existing = json.loads(path.read_text(encoding="utf-8"))
        except ValueError:
            raise SystemExit("%s is not valid JSON; fix or remove it first." % path)
        if not isinstance(existing, dict):
            raise SystemExit("%s must contain a JSON object." % path)

    existing["password_hash"] = password_hash
    # A new secret invalidates every old session cookie.
    existing["cookie_secret"] = secrets.token_hex(32)

    temporary = path.with_name(".%s.tmp" % path.name)
    temporary.write_text(json.dumps(existing, indent=2) + "\n", encoding="utf-8")
    os.chmod(temporary, 0o600)
    os.replace(temporary, path)
    os.chmod(path, 0o600)

    print("Password set in %s" % path)
    print("Keep this file private. Changing the password signs everyone out.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
