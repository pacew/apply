# NEFFA Program System: server

This is the Python server that puts the program committee's booking canvas on
`apply.neffa.org`, backed by live data.  The canvas is still the prototype and
still saves a chair's grid in their browser.  This server replaces the
prototype's snapshot of applications with the live `apply_neffa_org` data and
the live performer list.

It is deliberately small: Python 3.12, the standard library, and the
pre-installed `MySQLdb`.  No pip, no framework, no long-running process.

## Public URL

```
https://apply.neffa.org/program.py/
```

`program.py` at the site root is a trampoline; every real file is in
`server/`.  Apache must never serve `server/`; the `.htaccess` there returns
403.  Do not remove it.

The JSON contract for Lynn's AI is in [`API.md`](API.md).

## What it serves

| URL | What |
| --- | --- |
| `/program.py/` | the built prototype page, with no embedded data |
| `/program.py/login` | the committee password form |
| `/program.py/api/v1/login` | password → token for the development frontend |
| `/program.py/api/v1/me` | superuser name/role and selected workspace |
| `/program.py/settings` | festival year and test/production choice |
| `/program.py/api/v1/settings` | JSON version of the same |
| `/program.py/apitest` | developer API console (login required) |
| `/program.py/data/config.json` | halls, days and tracks for the page |
| `/program.py/data/Applications-2027.csv` | live applications, contact details removed |
| `/program.py/data/Performers.csv` | live performer list |
| `/program.py/data/Members.csv` | live group memberships |
| `/program.py/data/Webgrid-2026.tsv` | last year's published grid, if present |
| `/program.py/data/Webgrid-2027.tsv` | this year's grid, if present |
| `/program.py/version` | small JSON for a future auto-refresh |
| `/program.py/health` | `ok`, for a quick check |

The page finds `data/` beside itself, so the relative URL works without a
`<base>` tag.  `Data as of` in the page title comes from the `Last-Modified`
header of the newest data file.

## Where the data comes from

- **Applications:** the old app's own exporter,
  `download.php?direct_download=<key>&download_csv=1&year=YYYY`.  Using the
  old exporter keeps the 95-column format exactly right.  The download key is
  read from `vars` on the server and never sent to the browser.
- **Performers and members:** `neffa_pdb` on
  `neffaprog.neffa.dreamhosters.com`, using the read-only `pace_willisson`
  account whose password is in `~/aux/neffadb_passwd`.
- **Settings:** `~/program/web/config.json`, copied from the prototype's
  `src/config.json`.
- **Grids:** the 2026 history is bundled at `server/data/Webgrid-2026.tsv`
  (copied from `~/aux/webgrid.tsv` on apply.neffa.org).  The 2027 production
  grid, if needed, is a manual export in `~/program/schedule/`.

Nothing is cached for an hour.  Each data request does one cheap version query:

```
applications: SELECT COUNT(*), MAX(ts), MAX(app_id) FROM json WHERE ...
performers:   SELECT COUNT(*), MAX(updated) FROM performers
members:      SELECT COUNT(*), MAX(updated) FROM members
```

The `json` table is append-only, so any application save changes the count.
If the version is unchanged, the cached file is served.  If it changed, the
file is rebuilt.  The cache lives in `~/program/cache/` and can be deleted at
any time.

## Runtime layout

Everything mutable is outside the web root:

```
~/program/
  config.json      two password hashes and a cookie secret, mode 0600
  cache/           generated data, mode 0700, safe to delete
  schedule/        Webgrid-2026.tsv, Webgrid-2027.tsv, Webgrid-2027-test.tsv
  web/
    index.html     the prototype built with `node tools/build.js --no-data`
    config.json    copied from the prototype's src/config.json
```

The repository contains only code: `program.py` and `server/`.

## First-time setup

On `apply.neffa.org`, as `neffapace`:

```bash
mkdir -p ~/program/{cache,schedule,web}

# Build the page on the machine that has the prototype repo, then copy these
# two files to ~/program/web/ on the server:
#   node tools/build.js --no-data
#   dist/deploy/index.html   -> ~/program/web/index.html
#   src/config.json          -> ~/program/web/config.json

python3 ~/apply.neffa.org/server/tools/set_password.py
```

Then open `https://apply.neffa.org/program.py/`.

There are **two independent password slots**, so one person can change theirs
without locking the other out.  Set slot 1 first, then slot 2 if needed:

```bash
python3 ~/apply.neffa.org/server/tools/set_password.py --slot 1
python3 ~/apply.neffa.org/server/tools/set_password.py --slot 2
```

Each run asks twice and writes one scrypt hash.  The cookie secret is kept, so
existing sessions survive a password change; add `--rotate-sessions` to sign
everyone out.  Production config stays mode 600; the Apache dev runtime needs
`--mode 644`.

## Choosing the year and environment

Use the settings page:

```
/program.py/settings
```

It sets the festival year, the applications environment (`production` or
`test`) and the scheduling environment.  Login only authenticates; it never
sets the workspace.  The choice is kept in the `neffa_program_env` cookie for
the browser, and `GET/PUT /api/v1/settings` exposes the same values to the
development frontend.

- `apply=production` reads `test_flag=0`; `apply=test` reads `test_flag=1`.
  **Test applications are not implemented yet**, because the old exporter has
  no parameter for the test flag.  The data model and URLs already allow it.
- `schedule=production` reads `Webgrid-2027.tsv`;
  `schedule=test` reads `Webgrid-2027-test.tsv`.  `Webgrid-2026.tsv` is the
  same in both.

The prototype frontend has no user icon yet.  The request to Lynn's AI is to add
one whose click goes to `/program.py/settings`.

## Local copy for Lynn

The server exposes the same files to a script, so a local prototype can have
the live data without CORS:

```bash
python3 server/tools/pull_live_data.py --out /path/to/program-v2-prototype/data
```

It asks for the committee password, logs in, and writes `config.json`,
`Applications-2027.csv`, `Performers.csv`, `Members.csv`, and the webgrid files
it finds.  It preserves the server's `Last-Modified` times, so `Data as of`
stays correct.  It uses only the standard library and works on Windows, macOS
and Linux.

- Windows: double-click `pull-live-data.bat` (Python 3 required).
- macOS: double-click `pull-live-data.command`.
- To set the password without a prompt for automation, put it in
  `NEFFA_PROGRAM_PASSWORD`.

To view the local copy, serve the prototype folder over HTTP (opening the file
by double-click skips the server-data lookup):

```bash
cd /path/to/program-v2-prototype
python3 -m http.server 8000
# open http://localhost:8000/
```

## Development on ai.local

The development copy uses Apache on **port 18411**, the same host and port as
the PHP dev app, with the program server under `/program.py/`:

```
http://ai:18411/program.py/
http://ai:18411/program.py/apitest
```

`psite install` rewrites `TMP.conf` and the vhost, so the dev CGI settings live
in a **separate Apache conf** that it does not touch.  The file is versioned
here as `server/dev-apache.conf`:

```bash
sudo cp server/dev-apache.conf /etc/apache2/conf-available/neffa-program-dev.conf
sudo a2enconf neffa-program-dev
sudo a2enmod cgi
sudo apache2ctl configtest && sudo systemctl reload apache2
```

It sets `AddHandler cgi-script .py`, `AcceptPathInfo On`,
`SetEnv PROGRAM_HOME /home/pace/program-web`, and denies HTTP access to
`server/`.  Re-run the `cp`/`a2enconf` only if the file changes.  `program.py`
runs as `www-data`.

The runtime is separate from `~/program-dev`:

```
~/program-web/
  config.json          mode 644 so www-data can read it
  web/index.html       the no-data prototype build
  web/config.json
  schedule/            2027 exports, if any
```

Apache's systemd sandbox makes `/home` read-only for CGI, so the cache must
live elsewhere.  Set it in `~/program-web/config.json`:

```json
{
  "mysql_login_path": "",
  "mysql_db": "apply-pace",
  "mysql_home": "/home/pace",
  "download_url": "http://localhost:18411/download.php",
  "cache_dir": "/tmp/neffa-program-cache",
  "allowed_origins": [
    "http://ai:18411", "http://k.pacew.org:18411"
  ]
}
```

Set the two passwords with the mode the Apache runtime needs:

```bash
cd ~/apply
python3 server/tools/set_password.py --config ~/program-web/config.json --slot 1 --mode 644
python3 server/tools/set_password.py --config ~/program-web/config.json --slot 2 --mode 644
```

The cookie secret is preserved, so setting one slot does not sign out sessions
using the other.  Add `--rotate-sessions` only when you want to sign everyone
out.

The empty login path makes the `mysql` client use the local account's default
socket.  The local PDB is not present; `pdb_password_file` fails, so
`/version`, `Performers.csv` and `Members.csv` return 503 locally.  Point it at
a local copy or use an SSH tunnel to `neffaprog.neffa.dreamhosters.com`.

A direct `server/app.py` on 18412 is still possible for API-only work, but the
primary dev URL is 18411.

The dev frontend signs in with:

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"password":"..."}' http://ai:18411/program.py/api/v1/login
```

and sends the returned token as `X-Neffa-Token` (or `Authorization: Bearer`).
CORS is allowed only for the configured origins.  `GET /api/v1/me` should return
`{"name":"Program Chair","role":"program_chair",...}`.  Login does not choose a
workspace; call `PUT /api/v1/settings` or use `http://ai:18411/program.py/settings`.

## Tests

No database, no network:

```bash
cd ~/apply.neffa.org
python3 -m unittest discover -s server/tests -v
```

## Security notes

- `server/` returns 403 by HTTP; the code is read only by the trampoline.
- `config.json` is mode 0600 and holds the password hash and cookie secret.
  Nothing secret is in git.
- The session cookie is HttpOnly, Secure, SameSite=Lax, scoped to
  `/program.py`.
- `/program.py/apitest` is a developer console behind the same login.  Set
  `"apitest": false` in `config.json` to hide it; a separate developer role can
  gate it later.
- Applicant email, phone, access codes and links are blanked; contact details
  inside free text are replaced with `[contact removed]`.  Rejection text is
  reduced to the word `rejected`; the loader only needs to know the flag.
- The cache holds names and notes.  Keep `~/program/` out of the web root and
  out of any repository.
- The public page is behind one shared committee password.  Per-user accounts
  and roles are later work.

## Current limitations

- Read-only: the page still saves the grid in the browser's localStorage.
  There is no server workspace, event store, shared version, override or
  workflow state yet.
- Two password slots and one superuser role are in place; per-user accounts,
  the user icon and track permissions are later.
- Test applications are not implemented.
- The 2027 grid file is a manual export; 2026 history is bundled.
- The page does not poll `/version` yet; reload to see new applications.
- There is a settings page for the workspace choice, but no account admin
  screen; passwords are set with the command-line tool.

## Next steps

The server is structured so the write path can be added without rewriting the
data layer:

1. A workspace save (`GET/PUT /api/v1/workspace`) with a version, a per-entity
   journal, and comments.  This is what lets the committee stop using the
   browser as the store.
2. `GET /changes?since=N` polling, so chairs see each other's edits.
3. Server-side versions and snapshots.
4. A Python rules module, with the JavaScript rules as the test oracle.
5. `webgrid.tsv` export from the stored workspace, with the golden-file test.
