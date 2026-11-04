"""Live data for the prototype: applications, performers, members, settings.

The applications CSV comes from the old app's own exporter (download.php), so
the 95-column format stays exactly what the prototype expects.  Performers and
members come straight from the performer database.  Both are cached in
PROGRAM_HOME/cache and rebuilt only when the source data changes.

The staleness check is deliberately cheap:

    applications: SELECT COUNT(*), MAX(ts), MAX(app_id) FROM json ...
    performers:   SELECT COUNT(*), MAX(updated) FROM performers
    members:      SELECT COUNT(*), MAX(updated) FROM members

The json table is append-only, so any save changes the count.  Each data
request costs one or two small queries and usually serves a cache file.
"""

from __future__ import annotations

import csv
try:
    import fcntl
except ImportError:  # local tests on Windows
    fcntl = None
import io
import json
import os
import re
import subprocess
import sys
import time
import urllib.parse
import urllib.request
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path

from settings import test_flag


class DataError(RuntimeError):
    """The live source could not be read."""


class Unsupported(RuntimeError):
    """This environment is not implemented yet (for example, test applications)."""


@dataclass
class DataFile:
    body: bytes
    content_type: str
    last_modified: float
    version: str
    source: str  # "live", "cache", or "stale"


# ---------------------------------------------------------------- small helpers

EMAIL = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}")
PHONE = re.compile(r"(?:\+?1[-. ]?)?\(?\b\d{3}\)?[-. ]\d{3}[-. ]\d{4}\b")
LINK = re.compile(r"\b(?:https?://|www\.)[^\s<>\"]+", re.IGNORECASE)

# Free text that must lose contact details.  Titles and descriptions keep web
# links because they are printed in the program; notes lose links as well.
KEEP_LINKS = {"event_title", "event_desc"}
TEXT_COLUMNS = {
    "event_title",
    "event_desc",
    "notes",
    "conflicts",
    "C_notes",
    "C_extra_checks",
    "COMMITTEE_extra_checks",
    "specific_dance_style",
    "preferred_band",
    "preferred_caller",
    "sound_needs",
    "room_size",
    "props",
    "music_pref",
}


def log(message: str) -> None:
    """One line to Apache's error log; never includes data or secrets."""
    stamp = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    sys.stderr.write("%s neffa-program %s\n" % (stamp, message))
    sys.stderr.flush()


def strip_contacts(value: str, keep_links: bool) -> str:
    text = str(value or "")
    if not keep_links:
        text = LINK.sub("[contact removed]", text)
    text = EMAIL.sub("[contact removed]", text)
    text = PHONE.sub("[contact removed]", text)
    return text


def clean_field(value) -> str:
    """A value safe for a tab-separated file."""
    return (
        str(value or "")
        .replace("\t", " ")
        .replace("\r", " ")
        .replace("\n", " ")
        .strip()
    )


def _parse_timestamp(value) -> float:
    """Parse a MySQL timestamp, or return 0 when it is blank."""
    if value is None:
        return 0.0
    if isinstance(value, datetime):
        moment = value
    else:
        text = str(value).strip()
        if not text:
            return 0.0
        text = text[:19]
        try:
            moment = datetime.strptime(text, "%Y-%m-%d %H:%M:%S")
        except ValueError:
            return 0.0
    if moment.tzinfo is None:
        moment = moment.replace(tzinfo=timezone.utc)
    return moment.timestamp()


def iso_time(ts: float) -> str:
    if not ts:
        return ""
    return datetime.fromtimestamp(ts, timezone.utc).isoformat().replace("+00:00", "Z")


def _as_int(value, name: str) -> int:
    try:
        return int(value)
    except (TypeError, ValueError) as exc:
        raise ValueError("%s must be an integer" % name) from exc


# --------------------------------------------------------------- applications

def run_mysql(sql: str, cfg: dict) -> list[list[str]]:
    """Run one read-only query through the mysql client and its login path.

    The CGI environment has no HOME, and the mysql client keeps its encrypted
    login path in ~/.mylogin.cnf, so HOME is set explicitly.  SQL is built only
    from integers and fixed table names; no request text reaches it.
    """
    mysql = "/usr/bin/mysql" if Path("/usr/bin/mysql").exists() else "mysql"
    command = [mysql]
    if cfg.get("mysql_login_path"):
        command.append("--login-path=%s" % cfg["mysql_login_path"])
    command += ["-N", "-B", cfg["mysql_db"], "-e", sql]
    env = os.environ.copy()
    env["HOME"] = cfg["mysql_home"]
    try:
        result = subprocess.run(
            command, capture_output=True, text=True, timeout=30, env=env
        )
    except (OSError, subprocess.SubprocessError) as exc:
        raise DataError("the mysql client could not run: %s" % exc) from exc
    if result.returncode != 0:
        # Log only the client's first error line; never the query or its data.
        detail = result.stderr.strip().splitlines()
        log("mysql failed: %s" % (detail[0] if detail else "no detail"))
        raise DataError("the applications database query failed")
    rows = []
    for line in result.stdout.splitlines():
        if line != "":
            rows.append(line.split("\t"))
    return rows


def application_version(year: int, flag: int, cfg: dict) -> tuple[str, float]:
    sql = (
        "SELECT COUNT(*), COALESCE(MAX(ts),''), COALESCE(MAX(app_id),0) "
        "FROM `json` WHERE fest_year=%d AND test_flag=%d"
        % (_as_int(year, "year"), _as_int(flag, "test flag"))
    )
    rows = run_mysql(sql, cfg)
    if not rows or len(rows[0]) < 3:
        raise DataError("the applications database returned no version")
    count, max_ts, max_id = rows[0][0], rows[0][1], rows[0][2]
    return "%s:%s:%s" % (count, max_ts, max_id), _parse_timestamp(max_ts)


def clean_applications(text: str) -> tuple[bytes, int]:
    """Drop contact details from the old app's CSV and return clean CSV bytes."""
    reader = csv.reader(io.StringIO(text))
    rows = list(reader)
    if not rows:
        raise DataError("the applications export was empty")
    header = rows[0]
    if "app_id" not in header:
        raise DataError("the applications export has no app_id column")
    body = rows[1:]

    def index(name: str) -> int | None:
        try:
            return header.index(name)
        except ValueError:
            return None

    # Contact columns and the links/access codes the tools never show.
    blank_names = ["email", "phone", "C_rejection_notes", "P_notes"]
    for name in header:
        if name.endswith("_pcode"):
            blank_names.append(name)
        elif name.endswith("_link") and name != "app_link":
            blank_names.append(name)
    blank_indexes = [index(n) for n in blank_names]
    blank_indexes = [i for i in blank_indexes if i is not None]

    text_indexes = [(index(n), n) for n in TEXT_COLUMNS if index(n) is not None]
    rejection = index("C_rejection_reason")

    for row in body:
        for i in blank_indexes:
            if i < len(row):
                row[i] = ""
        for i, name in text_indexes:
            if i < len(row):
                row[i] = strip_contacts(row[i], keep_links=name in KEEP_LINKS)
        if rejection is not None and rejection < len(row) and row[rejection].strip():
            # The loader only needs to know that the application was rejected.
            row[rejection] = "rejected"

    output = io.StringIO()
    writer = csv.writer(output, lineterminator="\n")
    writer.writerow(header)
    writer.writerows(body)
    return output.getvalue().encode("utf-8"), len(body)


def build_applications(year: int, flag: int, cfg: dict) -> tuple[bytes, int]:
    if flag:
        raise Unsupported(
            "test application data is not available yet; the old app's export "
            "has no parameter for the test flag"
        )
    rows = run_mysql("SELECT val FROM vars WHERE var='download_key'", cfg)
    if not rows or not rows[0] or not rows[0][0]:
        raise DataError("the download key is missing from vars")
    key = rows[0][0].strip()
    query = urllib.parse.urlencode(
        {"direct_download": key, "download_csv": "1", "year": year}
    )
    request = urllib.request.Request(
        cfg["download_url"] + "?" + query,
        headers={"User-Agent": "neffa-program/1.0"},
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            raw = response.read()
            content_type = response.headers.get("Content-Type", "")
    except Exception as exc:
        # The URL contains the download key.  Never put it, or exception text
        # that may contain it, into a message that reaches a log.
        raise DataError("the applications export could not be fetched") from exc
    if "html" in content_type.lower():
        raise DataError("the applications export returned a web page, not a CSV")
    text = raw.decode("utf-8", "replace")
    body, count = clean_applications(text)
    log("built applications year=%d rows=%d" % (year, count))
    return body, count


# --------------------------------------------------------- performers, members

def _pdb_connection(cfg: dict):
    try:
        import MySQLdb  # type: ignore
    except ImportError as exc:
        raise DataError("MySQLdb is not installed for this Python") from exc
    password_path = Path(cfg["pdb_password_file"])
    try:
        password = password_path.read_text(encoding="utf-8").strip()
    except OSError as exc:
        raise DataError("the performer database password file is not readable") from exc
    try:
        return MySQLdb.connect(
            host=cfg["pdb_host"],
            port=int(cfg.get("pdb_port") or 3306),
            user=cfg["pdb_user"],
            passwd=password,
            db=cfg["pdb_db"],
            charset="utf8mb4",
            connect_timeout=10,
        )
    except Exception as exc:
        raise DataError("the performer database could not be reached: %s" % exc) from exc


def pdb_version(table: str, cfg: dict) -> tuple[str, float]:
    if table not in ("performers", "members"):
        raise ValueError("unknown table")
    connection = _pdb_connection(cfg)
    try:
        cursor = connection.cursor()
        cursor.execute("SELECT COUNT(*), COALESCE(MAX(updated),'') FROM %s" % table)
        row = cursor.fetchone()
        cursor.close()
    finally:
        connection.close()
    if not row:
        raise DataError("the performer database returned no version")
    return "%s:%s" % (row[0], row[1]), _parse_timestamp(row[1])


def build_performers(cfg: dict) -> tuple[bytes, int]:
    connection = _pdb_connection(cfg)
    try:
        cursor = connection.cursor()
        cursor.execute(
            "SELECT number, performerName, performerType FROM performers ORDER BY number"
        )
        rows = cursor.fetchall()
        cursor.close()
    finally:
        connection.close()
    lines = []
    for number, name, role in rows:
        lines.append("%s\t%s\t\t%s" % (number, clean_field(name), clean_field(role)))
    body = ("\n".join(lines) + "\n").encode("utf-8") if lines else b""
    log("built performers rows=%d" % len(lines))
    return body, len(lines)


def build_members(cfg: dict) -> tuple[bytes, int]:
    connection = _pdb_connection(cfg)
    try:
        cursor = connection.cursor()
        cursor.execute(
            "SELECT groupNumber, memberNumber, type FROM members "
            "ORDER BY groupNumber, memberNumber"
        )
        rows = cursor.fetchall()
        cursor.close()
    finally:
        connection.close()
    lines = []
    for group, member, kind in rows:
        lines.append("%s\t%s\t%s" % (group, member, clean_field(kind)))
    body = ("\n".join(lines) + "\n").encode("utf-8") if lines else b""
    log("built members rows=%d" % len(lines))
    return body, len(lines)


# ----------------------------------------------------------------------- cache

def _cache_paths(cfg: dict, stem: str) -> tuple[Path, Path]:
    cache_dir = Path(cfg["cache_dir"])
    return cache_dir / stem, cache_dir / (stem + ".json")


def _read_cache(cfg: dict, stem: str) -> tuple[bytes, dict] | None:
    body_path, meta_path = _cache_paths(cfg, stem)
    try:
        meta = json.loads(meta_path.read_text(encoding="utf-8"))
        body = body_path.read_bytes()
    except (OSError, ValueError):
        return None
    if not body:
        return None
    return body, meta


def _write_cache(cfg: dict, stem: str, body: bytes, version: str, last_modified: float) -> None:
    body_path, meta_path = _cache_paths(cfg, stem)
    cache_dir = body_path.parent
    cache_dir.mkdir(parents=True, exist_ok=True)
    temp = cache_dir / (".%s.%d.tmp" % (stem, os.getpid()))
    temp.write_bytes(body)
    os.replace(temp, body_path)
    meta = {
        "version": version,
        "last_modified": last_modified,
        "built_at": int(time.time()),
    }
    temp_meta = cache_dir / (".%s.%d.meta.tmp" % (stem, os.getpid()))
    temp_meta.write_text(json.dumps(meta), encoding="utf-8")
    os.replace(temp_meta, meta_path)


def _locked(cfg: dict):
    """A cross-process lock so two CGI requests do not build at once."""
    lock_path = Path(cfg["cache_dir"]) / ".lock"
    lock_path.parent.mkdir(parents=True, exist_ok=True)
    handle = open(lock_path, "w", encoding="utf-8")
    if fcntl is not None:
        fcntl.flock(handle, fcntl.LOCK_EX)
    return handle


def _cached_file(
    cfg: dict,
    stem: str,
    version: str,
    last_modified: float,
    builder,
    content_type: str,
) -> DataFile:
    cached = _read_cache(cfg, stem)
    if cached and cached[1].get("version") == version:
        body, meta = cached
        return DataFile(
            body=body,
            content_type=content_type,
            last_modified=float(meta.get("last_modified") or last_modified),
            version=version,
            source="cache",
        )

    lock = _locked(cfg)
    try:
        cached = _read_cache(cfg, stem)  # another request may have built it
        if cached and cached[1].get("version") == version:
            body, meta = cached
            return DataFile(
                body=body,
                content_type=content_type,
                last_modified=float(meta.get("last_modified") or last_modified),
                version=version,
                source="cache",
            )
        try:
            body, _count = builder()
        except Exception:
            if cached:
                log("build failed; serving stale %s" % stem)
                return DataFile(
                    body=cached[0],
                    content_type=content_type,
                    last_modified=float(cached[1].get("last_modified") or last_modified),
                    version=str(cached[1].get("version", "")),
                    source="stale",
                )
            raise
        _write_cache(cfg, stem, body, version, last_modified)
        return DataFile(
            body=body,
            content_type=content_type,
            last_modified=last_modified,
            version=version,
            source="live",
        )
    finally:
        if fcntl is not None:
            fcntl.flock(lock, fcntl.LOCK_UN)
        lock.close()


def get_applications(cfg: dict, year: int, apply_env: str) -> DataFile:
    flag = test_flag(apply_env)
    version, last_modified = application_version(year, flag, cfg)
    stem = "applications-%d-%s.csv" % (year, apply_env)
    return _cached_file(
        cfg,
        stem,
        version,
        last_modified,
        lambda: build_applications(year, flag, cfg),
        "text/csv; charset=utf-8",
    )


def get_performers(cfg: dict) -> DataFile:
    version, last_modified = pdb_version("performers", cfg)
    return _cached_file(
        cfg,
        "performers.tsv",
        version,
        last_modified,
        lambda: build_performers(cfg),
        "text/tab-separated-values; charset=utf-8",
    )


def get_members(cfg: dict) -> DataFile:
    version, last_modified = pdb_version("members", cfg)
    return _cached_file(
        cfg,
        "members.tsv",
        version,
        last_modified,
        lambda: build_members(cfg),
        "text/tab-separated-values; charset=utf-8",
    )


# ------------------------------------------------------------ static-ish files

def get_config(cfg: dict) -> DataFile | None:
    path = Path(cfg["web_config"])
    try:
        body = path.read_bytes()
        mtime = path.stat().st_mtime
    except OSError:
        return None
    return DataFile(
        body=body,
        content_type="application/json; charset=utf-8",
        last_modified=mtime,
        version=str(int(mtime)),
        source="file",
    )


def get_schedule(cfg: dict, name: str, schedule_env: str) -> DataFile | None:
    if name not in ("Webgrid-2026.tsv", "Webgrid-2027.tsv"):
        return None
    actual = name
    if name == "Webgrid-2027.tsv" and schedule_env == "test":
        actual = "Webgrid-2027-test.tsv"
    path = Path(cfg["schedule_dir"]) / actual
    if not path.is_file() and actual == "Webgrid-2026.tsv":
        # The 2026 grid is immutable history and is versioned with the server,
        # so it is available even when the runtime schedule folder is empty.
        path = Path(__file__).resolve().parent / "data" / "Webgrid-2026.tsv"
    try:
        body = path.read_bytes()
        mtime = path.stat().st_mtime
    except OSError:
        return None
    # Last year's grid is history; its file date is not "Data as of" for the
    # current applications.  0 means the response has no Last-Modified header.
    last_modified = 0.0 if actual == "Webgrid-2026.tsv" else mtime
    return DataFile(
        body=body,
        content_type="text/tab-separated-values; charset=utf-8",
        last_modified=last_modified,
        version="%s:%d" % (actual, int(mtime)),
        source="file",
    )


def version_snapshot(cfg: dict, year: int, apply_env: str, schedule_env: str) -> dict:
    """The small JSON the page can poll to notice that data changed."""
    app_version, app_ts = application_version(year, test_flag(apply_env), cfg)
    perf_version, perf_ts = pdb_version("performers", cfg)
    mem_version, mem_ts = pdb_version("members", cfg)
    schedule = {}
    for name in ("Webgrid-2026.tsv", "Webgrid-2027.tsv"):
        found = get_schedule(cfg, name, schedule_env)
        if found:
            schedule[name] = {
                "version": found.version,
                "asOf": iso_time(found.last_modified),
            }
    newest = max([app_ts, perf_ts, mem_ts] + [0])
    return {
        "year": year,
        "apply": apply_env,
        "schedule": schedule_env,
        "applications": {"version": app_version, "asOf": iso_time(app_ts)},
        "performers": {"version": perf_version, "asOf": iso_time(perf_ts)},
        "members": {"version": mem_version, "asOf": iso_time(mem_ts)},
        "scheduleFiles": schedule,
        "asOf": iso_time(newest),
    }
