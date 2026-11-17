<?php
// TRANS-07: Create transaction acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/transaction_data.php';
require __DIR__ . '/../public/includes/cash_summary_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

function transaction_row(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Every fund's balance as of today, keyed by id (archived funds included).
function all_balances(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT f.id, ' . fund_balance_sql('f') . ' AS balance FROM funds f');
    $stmt->execute(['as_of' => balance_date(null)]);
    return array_map('floatval', array_column($stmt->fetchAll(), 'balance', 'id'));
}

function rejected(PDO $pdo, string $sql): bool
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
    echo '=== TRANS-07 Acceptance Tests ===' . PHP_EOL;

    $fundId = (int) $pdo->query("SELECT id FROM funds WHERE name = 'Special Revenue Fund'")->fetchColumn();
    $archivedId = (int) $pdo->query('SELECT id FROM funds WHERE is_active = 0')->fetchColumn();
    $balancesBefore = all_balances($pdo);
    $totalBefore = (float) getCashSummary($pdo)['total_cash'];

    $inflow = [
        'fund_id'     => (string) $fundId,
        'date'        => date('Y-m-d'),
        'type'        => 'inflow',
        'amount'      => '1500.25',
        'category'    => 'Sales tax',
        'description' => 'October sales tax receipt',
    ];

    // The user selects one active fund and enters date, type, positive amount, category, and description.
    check(transaction_errors($pdo, $inflow) === [], 'Valid inflow passes validation');
    $id = create_transaction($pdo, transaction_input($inflow));
    $row = transaction_row($pdo, $id);
    check((int) $row['fund_id'] === $fundId, 'Fund is saved');
    check($row['transaction_date'] === $inflow['date'], 'Date is saved');
    check((float) $row['amount'] === 1500.25, 'Inflow is saved as a positive amount');
    check($row['category'] === 'Sales tax', 'Category is saved');
    check($row['description'] === 'October sales tax receipt', 'Description is saved');

    $outflowId = create_transaction($pdo, transaction_input(array_merge($inflow, [
        'type' => 'outflow', 'amount' => '400', 'category' => 'Utilities', 'description' => 'Water bill',
    ])));
    check((float) transaction_row($pdo, $outflowId)['amount'] === -400.0, 'Outflow is saved as a negative amount');

    // A posted transaction appears in transaction history.
    $history = array_column(getTransactions($pdo), null, 'id');
    check(isset($history[$id]) && $history[$id]['type'] === 'Inflow' && $history[$id]['fund_name'] === 'Special Revenue Fund',
        'Inflow appears in history with its type and fund');
    check(isset($history[$outflowId]) && $history[$outflowId]['type'] === 'Outflow', 'Outflow appears in history as an outflow');
    check(array_key_first($history) === $outflowId, 'History lists the newest first');

    // It updates only its associated fund.
    $balancesAfter = all_balances($pdo);
    check($balancesAfter[$fundId] === $balancesBefore[$fundId] + 1100.25, "The fund's balance moves by inflow minus outflow");
    unset($balancesBefore[$fundId], $balancesAfter[$fundId]);
    check($balancesAfter === $balancesBefore, "Every other fund's balance is unchanged");
    check((float) getCashSummary($pdo)['total_cash'] === $totalBefore + 1100.25, 'Organization total cash moves by the same amount');

    // Posted transaction values become read-only after posting.
    check(rejected($pdo, "UPDATE transactions SET amount = 1 WHERE id = $id"), 'Changing a posted amount is refused');
    check(rejected($pdo, "UPDATE transactions SET description = 'x' WHERE id = $id"), 'Changing a posted description is refused');
    check(rejected($pdo, "DELETE FROM transactions WHERE id = $id"), 'Deleting a posted transaction is refused');
    check(transaction_row($pdo, $id) === $row, 'The posted transaction is unchanged');

    // Invalid amounts, dates, transaction types, or fund references are rejected.
    $countBefore = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    $invalid = [
        'Missing amount'       => ['amount' => '', 'field' => 'amount'],
        'Zero amount'          => ['amount' => '0', 'field' => 'amount'],
        'Negative amount'      => ['amount' => '-10', 'field' => 'amount'],
        'Non-numeric amount'   => ['amount' => '12abc', 'field' => 'amount'],
        'Fractional cents'     => ['amount' => '1.005', 'field' => 'amount'],
        'Missing date'         => ['date' => '', 'field' => 'date'],
        'Malformed date'       => ['date' => '11/16/2026', 'field' => 'date'],
        'Impossible date'      => ['date' => '2026-02-30', 'field' => 'date'],
        'Missing type'         => ['type' => '', 'field' => 'type'],
        'Unsupported type'     => ['type' => 'transfer', 'field' => 'type'],
        'Missing fund'         => ['fund_id' => '', 'field' => 'fund_id'],
        'Unknown fund'         => ['fund_id' => '999999', 'field' => 'fund_id'],
        'Non-numeric fund'     => ['fund_id' => 'abc', 'field' => 'fund_id'],
        'Archived fund'        => ['fund_id' => (string) $archivedId, 'field' => 'fund_id'],
        'Missing category'     => ['category' => '  ', 'field' => 'category'],
        'Category too long'    => ['category' => str_repeat('x', TRANSACTION_CATEGORY_MAX + 1), 'field' => 'category'],
        'Missing description'  => ['description' => '', 'field' => 'description'],
        'Description too long' => ['description' => str_repeat('x', TRANSACTION_DESCRIPTION_MAX + 1), 'field' => 'description'],
    ];
    foreach ($invalid as $label => $change) {
        $field = $change['field'];
        unset($change['field']);
        $errors = transaction_errors($pdo, transaction_input(array_merge($inflow, $change)));
        check(isset($errors[$field]) && $errors[$field] !== '', "$label is rejected with a message");
    }
    check((int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn() === $countBefore, 'Rejected submissions save nothing');
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
