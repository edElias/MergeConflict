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
