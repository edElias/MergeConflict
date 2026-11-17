<?php
// FUND-01 (MER-34): View fund information acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/fund_summary_data.php';
require __DIR__ . '/../public/includes/fund_info_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

$day = fn(int $offset) => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . "$offset days"));

try {
    $pdo->beginTransaction();
    echo '=== FUND-01 Acceptance Tests ===' . PHP_EOL;

    // A fresh fund, so every total below comes only from the records added here.
    $pdo->exec("INSERT INTO funds (name, total_cash, restricted_cash, committed_funds) VALUES ('Test Info Fund', 50000, 0, 12000)");
    $id = (int) $pdo->lastInsertId();

    $empty = getFundPlanningTotals($pdo, $id);
    check($empty === ['expected_inflows' => 0.0, 'expected_outflows' => 0.0], 'A fund with no planning records totals zero');
    check(getFundTransactions($pdo, $id) === ['rows' => [], 'total' => 0], 'A fund with no transactions returns an empty list');
    check(getFundCommitments($pdo, $id) === [] && getFundExpectedInflows($pdo, $id) === [], 'Empty commitment and inflow lists');

    $inflow = $pdo->prepare('INSERT INTO expected_inflows (fund_id, amount, description, expected_date) VALUES (?, ?, ?, ?)');
    $inflow->execute([$id, 1000, 'Today', $day(0)]);
    $inflow->execute([$id, 2000, 'Day 30', $day(30)]);
    $inflow->execute([$id, 4000, 'Day 31', $day(31)]);
    $inflow->execute([$id, 8000, 'Overdue', $day(-1)]);

    $commit = $pdo->prepare('INSERT INTO commitments (fund_id, amount, description, commitment_date) VALUES (?, ?, ?, ?)');
    $commit->execute([$id, 5000, 'Due soon', $day(10)]);
    $commit->execute([$id, 7000, 'Due later', $day(60)]);

    // Expected inflows and outflows cover today through the next 30 days.
    $totals = getFundPlanningTotals($pdo, $id);
    check($totals['expected_inflows'] === 3000.0, 'Expected inflows count today through day 30 only');
    check($totals['expected_outflows'] === 5000.0, 'Expected outflows are commitments due in the next 30 days');
    check(getFundPlanningTotals($pdo, $id, 90)['expected_outflows'] === 12000.0, 'A longer window includes later commitments');

    // Supporting records are available, soonest first for planning records.
    check(array_column(getFundCommitments($pdo, $id), 'description') === ['Due soon', 'Due later'], 'Commitments are listed soonest first');
    check(array_column(getFundExpectedInflows($pdo, $id), 'description') === ['Overdue', 'Today', 'Day 30', 'Day 31'],
        'Every expected inflow is listed, soonest first');
    check(array_sum(array_column(getFundCommitments($pdo, $id), 'amount')) === (float) getFundDetails($pdo, $id)['committed'],
        'Listed commitments add up to the committed amount');

    // Transactions: newest first, limited to the most recent, with the full count.
    $txn = $pdo->prepare('INSERT INTO transactions (fund_id, amount, description, transaction_date) VALUES (?, ?, ?, ?)');
    foreach (range(1, 7) as $n) {
        $txn->execute([$id, $n % 2 ? -100 * $n : 100 * $n, "Txn $n", $day(-$n)]);
    }
    $recent = getFundTransactions($pdo, $id);
    check($recent['total'] === 7 && count($recent['rows']) === 5, 'Shows the 5 most recent of all transactions');
    check(array_column($recent['rows'], 'description') === ['Txn 1', 'Txn 2', 'Txn 3', 'Txn 4', 'Txn 5'], 'Transactions are newest first');

    // Displayed information reflects the current stored data.
    $commit->execute([$id, 600, 'Added later', $day(5)]);
    check(getFundPlanningTotals($pdo, $id)['expected_outflows'] === 5600.0, 'A new commitment changes the totals right away');
    $pdo->prepare('UPDATE funds SET total_cash = 61000 WHERE id = ?')->execute([$id]);
    check((float) getFundDetails($pdo, $id)['current_balance'] === 61000.0, 'Balances read the stored values');

    // Another fund's records are never counted.
    $other = (int) $pdo->query("SELECT id FROM funds WHERE name = 'General Fund'")->fetchColumn();
    check(!in_array('Due soon', array_column(getFundCommitments($pdo, $other), 'description'), true), 'Records stay with their own fund');
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
