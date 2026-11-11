<?php
// FUND-05: Archive fund acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/fund_summary_data.php';
require __DIR__ . '/../public/includes/fund_archive_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

// Row counts and totals for everything attached to a fund.
function fund_records(PDO $pdo, int $id): array
{
    $records = [];
    foreach (['transactions', 'commitments', 'expected_inflows'] as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM $table WHERE fund_id = ?");
        $stmt->execute([$id]);
        $records[$table] = $stmt->fetch(PDO::FETCH_NUM);
    }
    return $records;
}

function fund_is_active(PDO $pdo, int $id): int
{
    $stmt = $pdo->prepare('SELECT is_active FROM funds WHERE id = ?');
    $stmt->execute([$id]);
    return (int) $stmt->fetchColumn();
}

// Whether the fund appears on the active-fund overview (the Funds page list).
function on_overview(PDO $pdo, int $id): bool
{
    foreach (getFundSummaries($pdo) as $fund) {
        if ((int) $fund['id'] === $id) {
            return true;
        }
    }
    return false;
}

try {
    $pdo->beginTransaction();
    echo '=== FUND-05 Acceptance Tests ===' . PHP_EOL;

    $id = (int) $pdo->query("SELECT id FROM funds WHERE name = 'Capital Projects Fund'")->fetchColumn();

    // Give the fund history, so "records remain stored" is a real comparison.
    $pdo->prepare("INSERT INTO transactions (fund_id, amount, description, transaction_date)
                   VALUES (?, 250, 'Test transaction', '2026-11-02')")->execute([$id]);
    $pdo->prepare("INSERT INTO commitments (fund_id, amount, description, commitment_date)
                   VALUES (?, 400, 'Test commitment', '2026-11-02')")->execute([$id]);
    $pdo->prepare("INSERT INTO expected_inflows (fund_id, amount, description, expected_date)
                   VALUES (?, 900, 'Test inflow', '2026-11-02')")->execute([$id]);

    $recordsBefore = fund_records($pdo, $id);
    $countsBefore = countActiveFundsByType($pdo);
    $type = getFundDetails($pdo, $id)['fund_type'];

    check(fund_is_active($pdo, $id) === 1, 'Fund starts active');
    check(on_overview($pdo, $id), 'Fund starts on the active-fund overview');

    archive_fund($pdo, $id);

    // Archived funds are removed from the active-fund overview.
    check(fund_is_active($pdo, $id) === 0, 'Archiving clears the active flag');
    check(!on_overview($pdo, $id), 'Archived fund is removed from the active-fund overview');
    check(
        countActiveFundsByType($pdo)[$type] === $countsBefore[$type] - 1,
        'Active fund count for the type drops by one'
    );

    // Associated transactions, inflows, and commitments remain stored.
    check(fund_records($pdo, $id) === $recordsBefore, 'Transactions, commitments, and inflows remain stored');

    // Phase 1 permits no permanent deletion: the fund and its history stay reachable.
    $after = getFundDetails($pdo, $id);
    check($after !== null, 'Archived fund still has a detail record');
    check($after['name'] === 'Capital Projects Fund', 'Archived fund keeps its name');
    check((float) $after['current_balance'] > 0, 'Archived fund keeps its balance');

    // Re-archiving is harmless, so a resubmitted confirmation cannot do damage.
    archive_fund($pdo, $id);
    check(fund_is_active($pdo, $id) === 0, 'Archiving an already-archived fund changes nothing');
    check(fund_records($pdo, $id) === $recordsBefore, 'Records remain intact after a repeat archive');
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
