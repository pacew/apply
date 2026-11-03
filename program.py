#!/usr/bin/python3
"""Trampoline for the NEFFA program system.

Apache runs this file for /program.py/... .  All of the real code lives in
server/ beside it; this file only puts that directory on the import path and
starts the WSGI-to-CGI adapter.

Keeping the trampoline separate means the program system can be moved to its
own repository later without changing the public URL.
"""

import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SERVER = os.path.join(HERE, "server")
if SERVER not in sys.path:
    sys.path.insert(0, SERVER)

from app import run_cgi  # noqa: E402  (the path is set above on purpose)

run_cgi()
