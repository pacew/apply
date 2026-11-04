# TODO — prepare for the 2028 festival (do in summer 2027)

Notes from the November 2026 dev cleanup. Items are roughly in priority order.

## Email flood limit

The limit is in `save.php:159-168`: count of `email_history` rows per address
`where year(sent) = year(curdate())`, with `$email_limit = 11`. So it resets on
January 1, not at the start of the festival season.

Decision, November 2026: clear the whole table by hand (`delete from
email_history`) so applicants in the categories that are still open get normal
confirmation mail for the rest of the season. The counting and reset questions
below are still open.

- [ ] Decide the reset boundary. January–February confirmation/access-code mail
      for the 2027 festival counts against calendar 2027, so addresses can enter
      the September 2027 window with a partly-used budget. In the 2026 data,
      16 applicant addresses were at the limit on calendar-year counts but only
      7 on September-onward counts. Consider `sent >= '2027-09-01'` (or a
      `fest_year` column) instead of `year(sent)`.
- [ ] Decide whether admin mail should count. `confirm.php`, `rejection.php` and
      `email.php` call `send_email()` without `no_history`, so they consume the
      applicant's budget; only `singletons.php` sets `no_history = 1`.
- [ ] Log suppressions. A suppressed application currently leaves no record
      beyond the `thanks.php?email_suppressed=1` page.
- [ ] Review the limit (11). Check who hit it during the 2027 season and whether
      bands / group coordinators filing several applications need more.
- [ ] Before the window opens, confirm the counter really is zeroed for the new
      calendar year, and that the SMTP credentials in `vars` still work.

## Roster data (neffa_pdb)

- [ ] Refresh `neffa_idx.json` in the aux dir (`./mkindex`) from neffa_pdb.
- [ ] Put `neffadb_passwd` in the aux dir (in place on ai as of Nov 2026, but
      needed on every new dev box). The `pace` branch reads it at load
      (`app.php:14-15`) for the performer DB used by performer/notify/requests
      pages. `pdb` uses `--login-path=pdb` for the same database.

## Environment / deployment

- [ ] PHP 8.5 on Ubuntu 26.04: `new DateTimeZone("US/Eastern")` (`app.php:18`)
      fatals unless `tzdata-legacy` is installed — PHP reads the system tz
      database, and the `US/*` links moved out of `tzdata`. Either install
      `tzdata-legacy` on every server (done on ai in Nov 2026) or switch the
      code to `America/New_York`.
- [ ] `app.php` requires `../PHPMailer/src`; make sure it is present on deploy
      (the dev box uses a symlink to the `libphp-phpmailer` package).
- [ ] Run `psite mkschema` after deploying a new branch. It added
      `notify.record_ok` here, which is in `schema` but unused by the code.
- [ ] psite is retiring. Known quirks, deliberately not fixed:
      `psite_session()` reads `$options` without `global $options;` (DB sessions
      never register), and `putsess()`/`clrsess()` don't fall back to
      `plain_port` when `ssl_port` is absent.

## Test before the season

- [ ] New application end-to-end, then admin login, CSV download, confirm and
      rejection emails.
- [ ] Email-limit test with a throwaway address (submit 12 times).
- [ ] Check the application-window dates in `app.php` (`mmdd_to_timestamp`
      calls) against the 2028 calendar.
- [ ] Backup/restore drill: `psite do-cron`, S3 sync, `psite restore`.

## Housekeeping

- [ ] `./reset` (`delete from notify`) if notify rows should not carry over.
- [ ] Review `email_history` growth and decide whether to prune old rows.
- [ ] Refresh `~/latest.gz` from production before doing dev work on next
      season's data.
