"""Paths and settings for the NEFFA program server.

The code lives in the git repository.  The mutable state lives outside the web
root, in ``PROGRAM_HOME`` (by default ``~/program``):

    config.json     password hash, cookie secret, database settings
    cache/          generated data files, safe to delete
    schedule/       Webgrid-2026.tsv, Webgrid-2027.tsv, Webgrid-2027-test.tsv
    web/            the built prototype page and its config.json

Keeping state out of the repository means ``git pull`` can never overwrite a
password or a cache, and the web root contains no data.
"""

from __future__ import annotations

import json
import os
import pwd
from pathlib import Path

DEFAULTS = {
    "year": 2027,
    "years": [2027, 2026],
    "apply_env": "production",
    "schedule_env": "production",
    "mysql_login_path": "apply-production",
    "mysql_db": "apply_neffa_org",
    "mysql_home": None,
    "pdb_host": "neffaprog.neffa.dreamhosters.com",
    "pdb_user": "pace_willisson",
    "pdb_db": "neffa_pdb",
    "pdb_port": 3306,
    "pdb_password_file": "~/aux/neffadb_passwd",
    "download_url": "https://apply.neffa.org/download.php",
    "session_hours": 12,
    "apitest": True,
    "cookie_name": "neffa_program",
    "env_cookie_name": "neffa_program_env",
    "actor_name": "Program Chair",
    "role": "program_chair",
    "allowed_origins": [
        "http://localhost:18411",
        "http://127.0.0.1:18411",
        "http://ai:18411",
        "http://k.pacew.org:18411",
        "http://localhost:18412",
        "http://127.0.0.1:18412",
    ],
    "page_path": "web/index.html",
    "web_config": "web/config.json",
    "schedule_dir": "schedule",
    "cache_dir": "cache",
}

APPLY_ENVS = ("production", "test")
SCHEDULE_ENVS = ("production", "test")


class ConfigError(RuntimeError):
    """The server cannot start because its config is missing or incomplete."""


def account_home() -> Path:
    """The home directory of the Unix account, independent of $HOME.

    Apache's CGI environment does not set HOME.  The mysql client and the PDB
    password file both live under the account home, so find it from the user id.
    """
    try:
        return Path(pwd.getpwuid(os.getuid()).pw_dir)
    except Exception:
        return Path.home()


def home() -> Path:
    """Where config, cache, schedule files and the built page live."""
    explicit = os.environ.get("PROGRAM_HOME")
    return Path(explicit).expanduser() if explicit else account_home() / "program"


def _expand(path_value: str) -> Path:
    """Expand a leading ~/ against the account home, not $HOME."""
    if path_value.startswith("~/"):
        return account_home() / path_value[2:]
    return Path(path_value)


def test_flag(apply_env: str) -> int:
    """The json.test_flag value for an apply environment."""
    if apply_env not in APPLY_ENVS:
        raise ConfigError("unknown apply environment: %r" % (apply_env,))
    return 0 if apply_env == "production" else 1


def load_config(path: str | os.PathLike | None = None) -> dict:
    """Read config.json and return it with defaults and resolved paths.

    Raises ConfigError with a plain-English message when the file is missing or
    does not contain the two secrets.  The message never includes a secret.
    """
    home_dir = home()
    config_path = Path(path) if path else home_dir / "config.json"
    if not config_path.is_file():
        raise ConfigError(
            "No config.json at %s. Run tools/set_password.py to create it."
            % config_path
        )
    try:
        raw = json.loads(config_path.read_text(encoding="utf-8"))
    except ValueError as exc:
        raise ConfigError("config.json is not valid JSON: %s" % exc) from exc
    if not isinstance(raw, dict):
        raise ConfigError("config.json must be a JSON object")

    cfg = dict(DEFAULTS)
    cfg.update(raw)
    cfg["home"] = home_dir
    cfg["config_path"] = config_path
    cfg["page"] = home_dir / cfg["page_path"]
    cfg["web_config"] = home_dir / cfg["web_config"]
    cfg["schedule_dir"] = home_dir / cfg["schedule_dir"]
    cfg["cache_dir"] = home_dir / cfg["cache_dir"]
    cfg["pdb_password_file"] = _expand(str(cfg["pdb_password_file"]))
    if cfg.get("mysql_home") is None:
        cfg["mysql_home"] = str(account_home())

    # One superuser for now.  The name and role ride in the signed session
    # cookie and are what /api/v1/me returns.  Per-user accounts come later.
    cfg["actor_name"] = str(cfg.get("actor_name") or "Program Chair")
    cfg["role"] = str(cfg.get("role") or "program_chair")

    # Browser origins allowed to call the API with a bearer token from the
    # development frontend.  Production uses the same-origin cookie and needs
    # no entry here.
    origins = cfg.get("allowed_origins")
    if not isinstance(origins, list):
        origins = DEFAULTS["allowed_origins"]
    cfg["allowed_origins"] = [str(o).strip() for o in origins if str(o).strip()]
    # The API test page is for the developer.  It still requires the superuser
    # login; a separate developer role comes later.
    cfg["apitest"] = bool(cfg.get("apitest", True))

    for key in ("password_hash", "cookie_secret"):
        if not cfg.get(key):
            raise ConfigError(
                "config.json has no %s. Run tools/set_password.py to set it." % key
            )

    try:
        cfg["year"] = int(cfg["year"])
        cfg["years"] = [int(y) for y in cfg["years"]]
        cfg["session_hours"] = int(cfg["session_hours"])
        cfg["pdb_port"] = int(cfg.get("pdb_port") or 3306)
    except (TypeError, ValueError) as exc:
        raise ConfigError("config.json has a non-numeric year, hours or port") from exc

    # An empty login path means "use the local mysql client defaults", which is
    # how the development copy on ai.local reaches its apply-pace database.
    cfg["mysql_login_path"] = str(cfg.get("mysql_login_path") or "")

    if cfg["year"] not in cfg["years"]:
        cfg["years"].append(cfg["year"])
    if cfg["apply_env"] not in APPLY_ENVS:
        raise ConfigError("unknown apply environment: %r" % (cfg["apply_env"],))
    if cfg["schedule_env"] not in SCHEDULE_ENVS:
        raise ConfigError("unknown schedule environment: %r" % (cfg["schedule_env"],))
    return cfg
