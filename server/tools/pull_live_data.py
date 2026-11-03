#!/usr/bin/python3
"""Copy the live program data from apply.neffa.org into a local data/ folder.

For a local copy of Lynn's prototype: run this once, then serve the prototype
folder over HTTP (for example ``python3 -m http.server 8000``) and open it.
The page will find ``data/`` beside it and show the live applications.

The script asks for the committee password.  It never prints it, and it keeps
no copy of it.  It needs only the Python standard library, so it works on
Windows, macOS and Linux.

    python3 pull_live_data.py --out /path/to/program-v2-prototype/data

On Windows, double-click ``pull-live-data.bat``.  On macOS, double-click
``pull-live-data.command``.
"""

from __future__ import annotations

import argparse
import getpass
import os
import sys
import time
from email.utils import parsedate_to_datetime
from http.cookiejar import CookieJar
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import quote, urlencode
from urllib.request import HTTPCookieProcessor, HTTPRedirectHandler, Request, build_opener


def fetch(opener, url: str, timeout: float):
    request = Request(url, headers={"User-Agent": "neffa-pull-live-data/1.0"})
    return opener.open(request, timeout=timeout)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--base",
        default="https://apply.neffa.org/program.py",
        help="base URL of the program system",
    )
    parser.add_argument(
        "--out",
        default="data",
        help="folder to write the files into (default: ./data)",
    )
    parser.add_argument("--year", default="2027", help="festival year (default: 2027)")
    parser.add_argument(
        "--apply", default="production", choices=("production", "test"),
        help="applications environment (default: production)",
    )
    parser.add_argument(
        "--schedule", default="production", choices=("production", "test"),
        help="schedule environment (default: production)",
    )
    parser.add_argument(
        "--password-env",
        default="NEFFA_PROGRAM_PASSWORD",
        help="environment variable holding the password, if set",
    )
    parser.add_argument("--timeout", type=float, default=60.0, help="seconds per request")
    args = parser.parse_args()

    base = args.base.rstrip("/")
    password = os.environ.get(args.password_env) or getpass.getpass(
        "Program committee password: "
    )
    if not password:
        print("No password given.")
        return 2

    jar = CookieJar()
    opener = build_opener(HTTPCookieProcessor(jar), HTTPRedirectHandler())
    form = urlencode(
        {
            "password": password,
            "year": args.year,
            "apply": args.apply,
            "schedule": args.schedule,
        }
    ).encode("utf-8")
    try:
        response = opener.open(
            Request(base + "/login", data=form, method="POST"), timeout=args.timeout
        )
        page = response.read().decode("utf-8", "replace")
    except HTTPError as exc:
        if exc.code == 401:
            print("That password did not match.")
        else:
            print("Sign-in failed: HTTP %d." % exc.code)
        return 2
    except URLError as exc:
        print("Could not reach %s: %s" % (base, exc.reason))
        return 2

    if "password did not match" in page.lower():
        print("That password did not match.")
        return 2

    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)

    names = [
        "config.json",
        "Applications-%s.csv" % args.year,
        "Performers.csv",
        "Members.csv",
        "Webgrid-2026.tsv",
        "Webgrid-2027.tsv",
    ]
    required = {"config.json", "Applications-%s.csv" % args.year, "Performers.csv", "Members.csv"}
    written = 0
    missing_required = []
    for name in names:
        url = base + "/data/" + quote(name)
        try:
            response = fetch(opener, url, args.timeout)
            body = response.read()
            last_modified = response.headers.get("Last-Modified")
        except HTTPError as exc:
            if exc.code == 404:
                print("Skipped %-24s (not on the server)" % name)
                if name in required:
                    missing_required.append(name)
                continue
            print("Failed %-24s HTTP %d" % (name, exc.code))
            return 2
        except URLError as exc:
            print("Failed %-24s %s" % (name, exc.reason))
            return 2

        target = out / name
        temporary = out / (".%s.tmp" % name)
        temporary.write_bytes(body)
        os.replace(temporary, target)
        if last_modified:
            try:
                moment = parsedate_to_datetime(last_modified).timestamp()
                os.utime(target, (moment, moment))
            except (TypeError, ValueError, OverflowError):
                pass
        written += 1
        print("Wrote   %-24s %8d bytes" % (name, len(body)))

    if missing_required:
        print("Missing required files: %s" % ", ".join(missing_required))
        return 2

    print()
    print("Wrote %d files to %s" % (written, out.resolve()))
    print("To view the local copy, from the prototype folder run:")
    print("  python3 -m http.server 8000")
    print("and open http://localhost:8000/")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
