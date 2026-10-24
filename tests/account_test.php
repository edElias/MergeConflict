<?php
// MER-26: Checks the account helpers used by the profile page and password reset,
// and that the users table has the profile columns. Run scripts/init_db.php first.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

try {
    require __DIR__ . '/../public/includes/account.php';

    $dbPath = __DIR__ . '/../data/app.sqlite';
    if (!is_file($dbPath)) {
        throw new RuntimeException('Database unavailable.');
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    $admin = $pdo->query("SELECT first_name, last_name, phone, role FROM users WHERE username = 'admin'")
        ->fetch(PDO::FETCH_ASSOC);

    $checks = [
        'Users table has profile columns'
            => !array_diff(['first_name', 'last_name', 'phone', 'role', 'avatar_file'], $columns),
        'Password reset attempts are tracked per account'
            => in_array('password_resets', $tables, true),
        'Mock admin has a profile'
            => $admin['first_name'] !== '' && format_phone($admin['phone']) === $admin['phone'] && $admin['role'] !== '',
        'Phone: digits are formatted'
            => format_phone('5551234567') === '(555) 123-4567',
        'Phone: punctuation and leading 1 are accepted'
            => format_phone('+1 555.123.4567') === '(555) 123-4567',
        'Phone: too few digits is rejected'
            => format_phone('555-12') === null,
        'Phone: too many digits is rejected'
            => format_phone('55512345678') === null,
        'Password: 8+ matching characters passes'
            => new_password_errors('longenough', 'longenough') === [],
        'Password: under 8 characters fails'
            => isset(new_password_errors('short', 'short')['password']),
        'Password: mismatch fails on confirm'
            => array_keys(new_password_errors('longenough', 'different')) === ['confirm'],
    ];

    $_SESSION = [];
    $token = csrf_token();
    $checks['CSRF: issued token is accepted'] = csrf_valid($token) && csrf_token() === $token;
    $checks['CSRF: wrong or missing token is rejected'] = !csrf_valid('nope') && !csrf_valid(null);

    $allPassed = true;
    foreach ($checks as $label => $passed) {
        echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
        $allPassed = $allPassed && $passed;
    }
    exit($allPassed ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Account test could not complete: ' . $error->getMessage() . "\n");
    exit(1);
}
