<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

try {
    $dbPath = __DIR__ . '/../data/app.sqlite';

    if (!is_file($dbPath)) {
        throw new RuntimeException('Database unavailable.');
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $statement = $pdo->prepare(
        'SELECT password_hash FROM users WHERE username = ?'
    );
    $statement->execute(['admin']);
    $hash = $statement->fetchColumn();

    if (!is_string($hash)) {
        throw new RuntimeException('Mock user unavailable.');
    }

    $count = $pdo->query(
        "SELECT COUNT(*) FROM users WHERE username = 'admin'"
    )->fetchColumn();

    $checks = [
        'Exactly one mock admin exists'
            => (int) $count === 1,
        'Stored value is a recognized password hash'
            => password_get_info($hash)['algoName'] !== 'unknown',
        'Stored value differs from the mock password'
            => $hash !== 'password123',
        'Correct password passes verification'
            => password_verify('password123', $hash),
        'Incorrect password fails verification'
            => !password_verify('WrongPassword!', $hash),
    ];

    $allPassed = true;

    foreach ($checks as $label => $passed) {
        echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
        $allPassed = $allPassed && $passed;
    }

    exit($allPassed ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, "Authentication test could not complete.\n");
    exit(1);
}