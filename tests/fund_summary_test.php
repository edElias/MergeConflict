<?php
// MER-29: Checks the DASH-02 fund summary queries against the local database.
// Run scripts/init_db.php first.

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

    require __DIR__ . '/../public/includes/fund_summary_data.php';

    $all = getFundSummaries($pdo);
    $standard = getFundSummaries($pdo, 'standard');
    $custom = getFundSummaries($pdo, 'custom');
    $names = array_column($all, 'name');
    $types = array_unique(array_column($all, 'fund_type'));

    $inactiveCount = (int) $pdo->query('SELECT COUNT(*) FROM funds WHERE is_active = 0')->fetchColumn();
    $first = getFundDetails($pdo, (int) $all[0]['id']);

    $checks = [
        'Active funds are listed'
            => count($all) > 0,
        'Standard funds are represented'
            => in_array('standard', $types, true),
        'Active custom funds are represented'
            => in_array('custom', $types, true),
        'Inactive funds are not listed'
            => $inactiveCount > 0 && !in_array('Riverfront Festival 2025', $names, true),
        'Each summary has current balance, committed, and projected 30-day values'
            => array_reduce($all, fn($ok, $f) => $ok
                && is_numeric($f['current_balance'])
                && is_numeric($f['committed'])
                && is_numeric($f['projected_30_day']), true),
        'Standard funds are listed before custom funds'
            => array_search('custom', array_column($all, 'fund_type'), true)
                === count($standard),
        'Type filter returns only that type'
            => count($standard) + count($custom) === count($all)
                && !in_array('custom', array_column($standard, 'fund_type'), true),
        'Unknown type filter is ignored'
            => count(getFundSummaries($pdo, 'bogus')) === count($all),
        'Fund details load for a listed fund'
            => $first !== null && $first['name'] === $all[0]['name'],
        'Fund details return null for a missing fund'
            => getFundDetails($pdo, PHP_INT_MAX) === null,
    ];

    $allPassed = true;

    foreach ($checks as $label => $passed) {
        echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
        $allPassed = $allPassed && $passed;
    }

    exit($allPassed ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, "Fund summary test could not complete: " . $error->getMessage() . "\n");
    exit(1);
}
