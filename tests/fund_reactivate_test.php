<?php
// FUND-06: View and reactivate archived funds acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/fund_summary_data.php';
require __DIR__ . '/../public/includes/fund_archive_data.php';
require __DIR__ . '/../public/includes/format.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

function listed(array $funds, int $id): ?array
{
    foreach ($funds as $fund) {
        if ((int) $fund['id'] === $id) {
            return $fund;
        }
    }
    return null;
}

try {
    $pdo->beginTransaction();
    echo '=== FUND-06 Acceptance Tests ===' . PHP_EOL;

    $id = (int) $pdo->query("SELECT id FROM funds WHERE name = 'Capital Projects Fund'")->fetchColumn();
    $cashBefore = (float) $pdo->query('SELECT SUM(total_cash) FROM funds WHERE is_active = 1')->fetchColumn();

    // Give the fund history, so "without losing records" is a real comparison.
    $pdo->prepare("INSERT INTO transactions (fund_id, amount, description, transaction_date)
                   VALUES (?, -250, 'Test payment', '2026-11-02')")->execute([$id]);
    $pdo->prepare("INSERT INTO commitments (fund_id, amount, description, commitment_date)
                   VALUES (?, 400, 'Test commitment', '2026-11-03')")->execute([$id]);
    $pdo->prepare("INSERT INTO expected_inflows (fund_id, amount, description, expected_date)
                   VALUES (?, 900, 'Test inflow', '2026-11-04')")->execute([$id]);

    check(listed(getArchivedFunds($pdo), $id) === null, 'Active fund is not on the archived list');

    $recordsBefore = getFundRecords($pdo, $id);
    $detailsBefore = getFundDetails($pdo, $id);

    archive_fund($pdo, $id);

    // A basic list identifies each archived fund's name, type, and status.
    $row = listed(getArchivedFunds($pdo), $id);
    check($row !== null, 'Archived fund is on the archived list');
    check($row['name'] === 'Capital Projects Fund' && $row['fund_type'] === 'standard', 'List gives the name and type');
    check($row['archived_at'] === date('Y-m-d'), 'Archiving records the date');
    check(date_default_timezone_get() === 'America/Chicago', 'Dates use the local day, not UTC');

    // Repeating the archive keeps the original date.
    $pdo->prepare('UPDATE funds SET archived_at = ? WHERE id = ?')->execute(['2026-01-15', $id]);
    archive_fund($pdo, $id);
    check(getFundDetails($pdo, $id)['archived_at'] === '2026-01-15', 'Repeat archive keeps the original date');
    check(format_date('2026-01-15') === 'Jan 15, 2026', 'Dates display as "Jan 15, 2026"');
    check(format_date(null) === '—', 'A missing date displays as a dash');

    // The user can open preserved archived-fund information.
    check(getFundDetails($pdo, $id) !== null, 'Archived fund information can be opened');
    $records = getFundRecords($pdo, $id);
    check(count($records) === 3, 'Transactions, commitments, and inflows are all preserved');
    check(array_column($records, 'type') === ['Expected inflow', 'Commitment', 'Payment'], 'Records are typed, newest first');

    // The user can reactivate an archived fund, without losing records.
    reactivate_fund($pdo, $id);
    $after = getFundDetails($pdo, $id);
    check((int) $after['is_active'] === 1, 'Reactivating sets the fund active');
    check($after['archived_at'] === null, 'Reactivating clears the archive date');
    check(listed(getArchivedFunds($pdo), $id) === null, 'Reactivated fund leaves the archived list');
    check(listed(getFundSummaries($pdo), $id) !== null, 'Reactivated fund returns to the active-fund overview');
    check(getFundRecords($pdo, $id) === $recordsBefore, 'Reactivation keeps every record');
    check($after['current_balance'] === $detailsBefore['current_balance']
        && $after['committed'] === $detailsBefore['committed'], 'Reactivation keeps the balances');
    check(
        (float) $pdo->query('SELECT SUM(total_cash) FROM funds WHERE is_active = 1')->fetchColumn() === $cashBefore,
        'Reactivated fund counts toward organization totals again'
    );

    // A fund with nothing stored has an empty record list, not an error.
    check(getFundRecords($pdo, PHP_INT_MAX) === [], 'A fund with no records returns an empty list');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL . "Tests passed: $passed" . PHP_EOL . "Tests failed: $failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
