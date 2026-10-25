# MergeConflict
A local web-based public treasury dashboard for tracking funds, commitments, transactions, cash availability, alerts, and ML-assisted cash-flow forecasts.

## Running locally

Requires PHP 8.x. From the repository root:

```
php -S localhost:8000 -t public
```

Then open http://localhost:8000 (redirects to the login page).

## Project layout

```
public/              Web root served by PHP
  index.php          Entry point (redirects to login)
  login.php          Login page (MER-7)
  dashboard.php      Authenticated dashboard shell (MER-7)
  includes/          Shared header, navigation, and footer partials
  assets/css/        App styles layered on Bootstrap 5
  assets/js/         Client-side scripts
```

### Login form contract (frontend ↔ PHP auth, MER-7 / MER-9)

- `login.php` POSTs `username` and `password` to `authenticate.php`.
- On failed login, redirect to `login.php?error=1` (shows a non-specific error).
- `logout.php` ends the session and redirects to `login.php?logged_out=1`.
- Protected pages redirect to `login.php?expired=1` when there is no valid session.



## Authentication database setup

Requires PHP 8.x with PDO SQLite enabled.
From the repository root, initialize the database before starting the app:

```powershell
& "C:\xampp\php\php.exe" scripts/init_db.php
```

If PHP is on your PATH, use `php scripts/init_db.php`.

This creates `data/app.sqlite`, creates the `users` table, and adds
a mock admin with a hashed password. Running it again preserves
the existing user.

### Local mock login

- Username: `admin`
- Password: `password123`

These credentials are for local demonstration only.

### Run authentication tests

```powershell
& "C:\xampp\php\php.exe" tests/auth_test.php
```

Expected result: five PASS messages confirming:

- Exactly one mock admin exists.
- The stored value is a recognized password hash.
- The stored value differs from the mock password.
- The correct password passes verification.
- An incorrect password fails verification.

The generated database is excluded from Git. Each teammate should
initialize their own local database.
## Fund summaries (MER-29)

`funds.php` lists active standard and custom funds with their current
balance, committed amount, and projected 30-day balance. Each fund opens
`fund.php?id=…`.

Projected 30-day balances are **dummy values** in the `fund_projections`
table (`source = 'dummy'`) until the ML forecast is ready. The forecast
should overwrite those rows and set `source = 'ml'`.

Re-run `php scripts/init_db.php` after pulling to add the sample custom
funds and dummy projections; it doesn't touch other data.

```powershell
php tests/fund_summary_test.php
```

## Edit fund information (FUND-04)

The **Edit fund** button on `fund.php` opens `fund_edit.php?id=…`, where the
fund name, type (standard/custom), restriction designation
(unrestricted/restricted), and description can be changed. Balances and the
fund's transactions, commitments, and expected inflows are never modified.
Blank or duplicate names, unknown types or designations, and descriptions over
500 characters are rejected with a message and nothing is saved.

The `restriction` and `description` columns are added automatically (schema
version 2 in `public/includes/schema.php`); existing funds start as
unrestricted with no description.

```powershell
php tests/fund_edit_test.php
```

## Profile & settings and password reset (MER-26)

- `profile.php` (user menu → Profile & settings): profile photo, first/last
  name, phone number, read-only username and role, and change password.
  Photos are stored in `data/avatars/` (not in Git) and served by `avatar.php`
  to signed-in users only.
- `forgot.php` → `reset.php`: reset with a 6-digit code that expires in
  5 minutes. Five wrong codes lock that account's reset for 15 minutes
  (tracked in `password_resets`, so resending doesn't clear it). The code is still the AUTH-05 demo code `123456` until real SMS
  is added. Unknown usernames get the same screens, so the page doesn't reveal
  which accounts exist.
- Passwords must be at least 8 characters.

New columns and tables (profile fields, fund type, projections, reset
attempts) are added automatically the first time the app opens an older
database (`public/includes/schema.php`). Re-run `php scripts/init_db.php`
to get the sample data.

```powershell
php tests/account_test.php
```
