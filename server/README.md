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

## What it serves

| URL | What |
| --- | --- |
| `/program.py/` | the built prototype page, with no embedded data |
| `/program.py/login` | the committee password form |
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
- **Grids:** files in `~/program/schedule/`.  They are manual exports from the
  Google Sheet for now.

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
  config.json      password hash and cookie secret, mode 0600
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

The password command asks twice, writes the scrypt hash and a new cookie
secret, and prints only the path.  Changing the password signs out everyone
who is already logged in.  To change it later:

```bash
python3 ~/apply.neffa.org/server/tools/set_password.py
```

## Choosing the year and environment

The defaults are in `config.json` (`year`, `apply_env`, `schedule_env`).  A
query string sets them for the browser session:

```
/program.py/?year=2026&apply=production&schedule=production
```

- `apply=production` reads `test_flag=0`; `apply=test` reads `test_flag=1`.
  **Test applications are not implemented yet**, because the old exporter has
  no parameter for the test flag.  The data model and URLs already allow it.
- `schedule=production` reads `Webgrid-2027.tsv`;
  `schedule=test` reads `Webgrid-2027-test.tsv`.  `Webgrid-2026.tsv` is the
  same in both.

The page itself does not show a selector yet; this is deliberate while the
system is read-only.  The values are kept in the `neffa_program_env` cookie.

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
- Applicant email, phone, access codes and links are blanked; contact details
  inside free text are replaced with `[contact removed]`.  Rejection text is
  reduced to the word `rejected`; the loader only needs to know the flag.
- The cache holds names and notes.  Keep `~/program/` out of the web root and
  out of any repository.
- The public page is behind one shared committee password.  Per-user accounts
  and roles are later work.

## Current limitations

- Read-only: the page still saves the grid in the browser's localStorage.
  There is no server event store, shared version, override or workflow state
  yet.
- Test applications are not implemented.
- The 2027 grid file is a manual export.
- The page does not poll `/version` yet; reload to see new applications.
- There is no admin screen; settings and passwords are files.

## Next steps

The server is structured so the write path can be added without rewriting the
data layer:

1. An event table and per-event saves with a version check.
2. `GET /changes?since=N` polling, so chairs see each other's edits.
3. Server-side versions and snapshots.
4. A Python rules module, with the JavaScript rules as the test oracle.
5. `webgrid.tsv` export from the database, with the existing golden-file test.
