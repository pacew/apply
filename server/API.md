# Program server API, version 1

Base URL:

```
https://apply.neffa.org/program.py/api/v1
```

The development server on `ai.local` runs the same app under Apache at
`http://ai:18411/program.py/api/v1`.  A direct `server/app.py` on 18412 is
optional.  CORS allows the origins in `allowed_origins` in the runtime
`config.json`.

## Authentication

One shared committee password, one superuser role for now.

```
POST /api/v1/login
Content-Type: application/json

{"password": "...", "year": 2027, "apply": "production", "schedule": "production"}

200 {"token": "...", "user": {"name": "Program Chair", "role": "program_chair"},
     "expiresIn": 43200, "year": 2027, "apply": "production", "schedule": "production"}
401 {"error": "invalid_password"}
```

The token is the same signed session token used by the browser cookie.  Send it
on later calls as either header:

```
X-Neffa-Token: <token>
Authorization: Bearer <token>
```

The production page uses the HttpOnly cookie instead and needs no header.
`X-Neffa-Token` is the reliable header under Apache CGI, which may strip
`Authorization`.

```
GET /api/v1/me
200 {"name": "Program Chair", "role": "program_chair",
     "year": 2027, "apply": "production", "schedule": "production",
     "expiresAt": 1790000000}
401 {"error": "not_signed_in"}
```

`year`, `apply` and `schedule` may be given as query parameters on `/me`; this
is how a bearer-token client selects a workspace without relying on the
`neffa_program_env` cookie.

## Implemented

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/api/v1/login` | password → token + user |
| GET | `/api/v1/me` | name, role, selected year/environments |
| GET | `/health` | `ok`, no login |
| GET | `/data/Applications-YYYY.csv` | live applications (read-only) |
| GET | `/data/Performers.csv` | live performers |
| GET | `/data/Members.csv` | live memberships |
| GET | `/data/config.json` | halls, days, tracks |
| GET | `/data/Webgrid-2026.tsv` | immutable 2026 history |
| GET | `/version` | source versions and dates |

## Developer API console

```
GET /program.py/apitest
```

An authenticated HTML page with one editable form per endpoint, a token box, and
a response panel.  It is for the single superuser now; a separate developer
role can gate it later.  Set `"apitest": false` in `config.json` to hide it.
It is deliberately a small hand-written page rather than Swagger/OpenAPI, which
would add a runtime dependency and a build step.

The login page honours `?next=` for a path inside the app, so
`/program.py/apitest` returns there after sign-in.  External URLs are ignored.

## Planned next: workspace save

One superuser first; the frontend sends the whole mutable state and the server
diffs and journals it.  See the data briefing for the full shapes.

```
GET /api/v1/workspace?year=2027&schedule=production
PUT /api/v1/workspace?year=2027&schedule=production
```

```
GET → {
  "version": 12, "year": 2027, "schedule": "production",
  "events": [{"id": 41, "day": "S", "hall": 0, "start": 870, "length": 60,
              "evids": ["T297b"], "kind": "event", "version": 3}],
  "overrides": [{"eventId": 41, "rule": "CC-01", "subjectHash": "...",
                 "reason": "...", "by": "Program Chair", "crossTrack": false}],
  "workflow": {"state": "dance_wip", "flagged": []},
  "selection": {"T297b": {"priority": "S", "status": "shortlisted",
                          "rejectionReason": null}},
  "requests": [], "matching": {"slots": [], "next": 1, "pick": "T"},
  "reviewed": {"asOf": "...", "seen": {}}
}

PUT → {"baseVersion": 12, "events": [...], "overrides": [...],
       "workflow": {...}, "selection": {...}, "requests": [...],
       "matching": {...}, "reviewed": {...}}
200 {"version": 13, "savedAt": "..."}
409 {"error": "version_conflict", "version": 14}
```

Planned supporting resources:

| Method | Path | Purpose |
| --- | --- | --- |
| GET/POST | `/api/v1/comments` | event/slot threads; `parentId` for replies |
| POST | `/api/v1/comments/{id}/resolve` | mark a debate resolved |
| GET | `/api/v1/versions` | saved snapshots, metadata |
| POST | `/api/v1/versions` | save the current workspace with a note |
| POST | `/api/v1/versions/{id}/restore` | restore, with an automatic backup |
| GET | `/api/v1/changes?since=N` | poll for changes after a sequence number |
| GET | `/api/v1/history/{type}/{id}` | all changes to one element |
| GET | `/webgrid.tsv` | generated output for the current workspace |

## Rules

- Applications, performers, members and `Webgrid-2026.tsv` are read-only inputs.
- The schedule, selection, versions, overrides, comments and journal are the
  new stored data.
- Conflicts and counts are computed, never stored.
- Every write records an entry in `program_change`: who, what, when, before/after.
- `year` and `test_flag` are part of the key for all new scheduling data.
- No local copy of the new data: the server is the store.
