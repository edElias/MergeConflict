<?php
// TRANS-02 (MER-54): Filter transactions acceptance tests.
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

// Descriptions of the listed test transactions, in the order shown.
function shown(PDO $pdo, array $get): array
{
    [$filters] = transaction_filters($get, getTransactionFilterFunds($pdo));
    $rows = getTransactions($pdo, $filters);
    return array_values(array_filter(array_column($rows, 'description'), fn($d) => str_starts_with($d, 'TF ')));
}

try {
    $pdo->beginTransaction();
    echo '=== TRANS-02 Acceptance Tests ===' . PHP_EOL;

    // Two test funds, one archived, so other records never affect the results.
    $add = $pdo->prepare("INSERT INTO funds (name, opening_balance, restricted_cash, committed_funds, is_active) VALUES (?, 0, 0, 0, ?)");
    $add->execute(['TF Fund A', 1]);
    $a = (int) $pdo->lastInsertId();
    $add->execute(['TF Fund B', 0]);
    $b = (int) $pdo->lastInsertId();

    $txn = $pdo->prepare('INSERT INTO transactions (fund_id, transaction_date, amount, category, description) VALUES (?, ?, ?, ?, ?)');
    $txn->execute([$a, '2026-01-05', 500, 'Taxes', 'TF A inflow Jan 5']);
    $txn->execute([$a, '2026-01-20', -200, 'Supplies', 'TF A outflow Jan 20']);
    $txn->execute([$a, '2026-02-10', -75, 'Fees 50%', 'TF A outflow Feb 10']);
    $txn->execute([$b, '2026-01-15', 900, 'Grants', 'TF B inflow Jan 15']);

    $all = shown($pdo, []);
    check($all === ['TF A outflow Feb 10', 'TF A outflow Jan 20', 'TF B inflow Jan 15', 'TF A inflow Jan 5'],
        'No filters shows the complete history, newest first');

    // Filter by fund, transaction type, and date range.
    check(shown($pdo, ['fund' => (string) $a]) === ['TF A outflow Feb 10', 'TF A outflow Jan 20', 'TF A inflow Jan 5'],
        'Fund filter shows only that fund');
    check(shown($pdo, ['fund' => (string) $b]) === ['TF B inflow Jan 15'], "An archived fund's transactions can be filtered");
    check(shown($pdo, ['type' => 'inflow']) === ['TF B inflow Jan 15', 'TF A inflow Jan 5'], 'Type filter: inflows');
    check(shown($pdo, ['type' => 'outflow']) === ['TF A outflow Feb 10', 'TF A outflow Jan 20'], 'Type filter: outflows');
    check(shown($pdo, ['from' => '2026-01-15', 'to' => '2026-01-20']) === ['TF A outflow Jan 20', 'TF B inflow Jan 15'],
        'Date range includes both end dates');
    check(shown($pdo, ['from' => '2026-02-01']) === ['TF A outflow Feb 10'], 'A start date alone works');
    check(shown($pdo, ['to' => '2026-01-10']) === ['TF A inflow Jan 5'], 'An end date alone works');

    // Filters can be combined.
    check(shown($pdo, ['fund' => (string) $a, 'type' => 'outflow', 'from' => '2026-01-01', 'to' => '2026-01-31'])
        === ['TF A outflow Jan 20'], 'Fund, type, and date range combine');
    check(shown($pdo, ['fund' => (string) $b, 'type' => 'outflow']) === [], 'A combination with no matches shows nothing');

    // Search matches description or category, and treats % literally.
    check(shown($pdo, ['q' => 'grants']) === ['TF B inflow Jan 15'], 'Search matches the category, ignoring case');
    check(shown($pdo, ['q' => 'Jan 2']) === ['TF A outflow Jan 20'], 'Search matches the description');
    check(shown($pdo, ['q' => '50%']) === ['TF A outflow Feb 10'], 'A % in the search is matched literally');

    // Clearing filters returns to the complete history.
    check(shown($pdo, ['fund' => '', 'type' => '', 'from' => '', 'to' => '', 'q' => '']) === $all,
        'Empty (cleared) filters show the complete history');

    // Invalid values are not applied, and the user is told why.
    $funds = getTransactionFilterFunds($pdo);
    [$filters, $values, $errors] = transaction_filters(['fund' => '999999', 'type' => 'refund', 'from' => '2026-13-40'], $funds);
    check($filters === [] && isset($errors['fund'], $errors['from']), 'An unknown fund or invalid date is ignored with a message');
    check($values['type'] === '', 'An unknown type is ignored');
    [$filters, , $errors] = transaction_filters(['from' => '2026-02-01', 'to' => '2026-01-01'], $funds);
    check($filters === [] && isset($errors['to']), 'An end date before the start date is not applied, with a message');
    [$filters] = transaction_filters(['q' => ['array']], $funds);
    check($filters === [], 'Non-text input is ignored');

    // Displayed results match the selected filters.
    [$filters] = transaction_filters(['type' => 'outflow', 'from' => '2026-01-01'], $funds);
    $rows = getTransactions($pdo, $filters);
    check($rows !== [] && array_filter($rows, fn($r) => $r['amount'] >= 0 || $r['date'] < '2026-01-01') === [],
        'Every displayed row matches the selected filters');
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
