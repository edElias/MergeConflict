<?php
// TRANS-01 (MER-53): View transaction history acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/transaction_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

// True when the database refuses the statement as a change to a posted transaction.
function refused(PDO $pdo, string $sql): bool
{
    try {
        $pdo->exec($sql);
        return false;
    } catch (PDOException $e) {
        return str_contains($e->getMessage(), 'read-only');
    }
}

try {
    $pdo->beginTransaction();
    echo '=== TRANS-01 Acceptance Tests ===' . PHP_EOL;

    $add = $pdo->prepare("INSERT INTO funds (name, opening_balance, restricted_cash, committed_funds, is_active) VALUES (?, 0, 0, 0, ?)");
    $add->execute(['TH Active Fund', 1]);
    $active = (int) $pdo->lastInsertId();
    $add->execute(['TH Archived Fund', 1]);
    $archived = (int) $pdo->lastInsertId();

    $txn = $pdo->prepare('INSERT INTO transactions (fund_id, transaction_date, amount, category, description) VALUES (?, ?, ?, ?, ?)');
    $txn->execute([$active, '2026-03-02', 1250.50, 'Taxes', 'TH receipt']);
    $receiptId = (int) $pdo->lastInsertId();
    $txn->execute([$archived, '2026-03-01', -400, 'Supplies', 'TH payment']);

    // The archived fund's records were posted while it was active; archive it now.
    $pdo->prepare('UPDATE funds SET is_active = 0 WHERE id = ?')->execute([$archived]);

    $rows = array_column(getTransactions($pdo), null, 'description');

    // Each transaction shows its date, description, amount, type, and fund.
    $r = $rows['TH receipt'] ?? null;
    check($r !== null && $r['date'] === '2026-03-02' && (float) $r['amount'] === 1250.5
        && $r['type'] === 'Inflow' && $r['fund_name'] === 'TH Active Fund',
        'A transaction has its date, description, amount, type, and fund');
    check(($rows['TH payment']['type'] ?? null) === 'Outflow' && (float) $rows['TH payment']['amount'] === -400.0,
        'An outflow is typed as an outflow');

    // Transactions connected to archived funds remain historically accessible.
    check(isset($rows['TH payment']) && $rows['TH payment']['fund_name'] === 'TH Archived Fund',
        "An archived fund's transaction is still listed");
    check((int) $rows['TH payment']['fund_is_active'] === 0 && (int) $rows['TH receipt']['fund_is_active'] === 1,
        'The list says which funds are archived');

    // Original transaction values cannot be edited.
    check(refused($pdo, "UPDATE transactions SET amount = 1 WHERE id = $receiptId"), 'Changing an amount is refused');
    check(refused($pdo, "UPDATE transactions SET transaction_date = '2026-01-01' WHERE id = $receiptId"), 'Changing a date is refused');
    check(refused($pdo, "DELETE FROM transactions WHERE id = $receiptId"), 'Deleting a transaction is refused');
    check((float) (array_column(getTransactions($pdo), null, 'description')['TH receipt']['amount']) === 1250.5,
        'The original values are unchanged');

    // Newest first.
    $dates = array_column(getTransactions($pdo), 'date');
    $sorted = $dates;
    rsort($sorted);
    check($dates === $sorted, 'History is listed newest first');

    // No page offers to edit or delete a transaction.
    $pages = implode('', array_map('file_get_contents', glob(__DIR__ . '/../public/*.php')));
    check(!preg_match('/(UPDATE|DELETE FROM)\s+transactions/i', $pages), 'No page changes or removes transactions');
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
