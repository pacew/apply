#!/usr/bin/python3
"""Set one of the two committee passwords for the NEFFA program system.

Run this on the server, as the account that owns the files:

    python3 ~/apply.neffa.org/server/tools/set_password.py --slot 1
    python3 ~/apply.neffa.org/server/tools/set_password.py --slot 2

It writes that slot's scrypt hash to ~/program/config.json, keeping the other
settings and the other slot.  The cookie-signing secret is preserved, so
existing sessions survive; add --rotate-sessions to sign everyone out.
Production uses the default mode 600; the Apache dev runtime needs --mode 644.

Nothing secret is printed.
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
    parser.add_argument(
        "--mode",
        default="600",
        help="octal mode for config.json (default 600; use 644 for the Apache dev runtime)",
    )
    parser.add_argument(
        "--slot",
        choices=("1", "2"),
        default="1",
        help="which of the two independent passwords to set (default 1)",
    )
    parser.add_argument(
        "--rotate-sessions",
        action="store_true",
        help="also generate a new cookie secret and sign everyone out",
    )
    args = parser.parse_args()
    try:
        mode = int(args.mode, 8)
    except ValueError:
        parser.error("--mode must be an octal number such as 600 or 644")
    if mode < 0 or mode > 0o777:
        parser.error("--mode must be between 0 and 777")

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

    key = "password_hash" if args.slot == "1" else "password_hash_2"
    if args.slot == "2" and not existing.get("password_hash"):
        raise SystemExit("Set password slot 1 first; the config has no slot 1.")
    existing[key] = password_hash

    # Two independent slots let one person change a password without locking
    # the other out.  Keep the cookie secret so live sessions survive a change;
    # --rotate-sessions is the explicit sign-everyone-out option.
    rotated = args.rotate_sessions or not existing.get("cookie_secret")
    if rotated:
        existing["cookie_secret"] = secrets.token_hex(32)

    temporary = path.with_name(".%s.tmp" % path.name)
    temporary.write_text(json.dumps(existing, indent=2) + "\n", encoding="utf-8")
    os.chmod(temporary, mode)
    os.replace(temporary, path)
    os.chmod(path, mode)

    print("Password slot %s set in %s (mode %03o)" % (args.slot, path, mode))
    if rotated:
        print("Sessions were signed out (new cookie secret).")
    else:
        print("Existing sessions were kept.")
    print("Keep this file private.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
