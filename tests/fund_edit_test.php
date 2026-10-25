<?php
// FUND-04: Edit fund information acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/fund_edit_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

function fund_row(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM funds WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

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

try {
    $pdo->beginTransaction();
    echo '=== FUND-04 Acceptance Tests ===' . PHP_EOL;

    $id = (int) $pdo->query("SELECT id FROM funds WHERE name = 'Capital Projects Fund'")->fetchColumn();
    $pdo->prepare("INSERT INTO transactions (fund_id, amount, description, transaction_date)
                   VALUES (?, 250, 'Test transaction', '2026-10-25')")->execute([$id]);

    $before = fund_row($pdo, $id);
    $recordsBefore = fund_records($pdo, $id);

    $valid = [
        'name'        => 'Capital Improvements Fund',
        'fund_type'   => 'custom',
        'restriction' => 'restricted',
        'description' => 'Road and building projects.',
    ];

    // Editable information: name, type, restriction designation, description.
    check(fund_edit_errors($pdo, $id, $valid) === [], 'Valid changes pass validation');
    update_fund_info($pdo, $id, $valid);
    $after = fund_row($pdo, $id);
    foreach ($valid as $field => $value) {
        check($after[$field] === $value, "$field is updated");
    }

    // Editing does not alter transactions or balances.
    foreach (['total_cash', 'restricted_cash', 'committed_funds', 'is_active'] as $column) {
        check($after[$column] === $before[$column], "$column is unchanged");
    }
    check(fund_records($pdo, $id) === $recordsBefore, 'Transactions, commitments, and inflows are unchanged');

    // Invalid or incomplete changes are rejected with a message.
    $invalid = [
        'Blank name'                => ['name' => '   ', 'field' => 'name'],
        'Name used by another fund' => ['name' => 'general fund', 'field' => 'name'],
        'Name too long'             => ['name' => str_repeat('x', FUND_NAME_MAX + 1), 'field' => 'name'],
        'Missing type'              => ['fund_type' => '', 'field' => 'fund_type'],
        'Unknown type'              => ['fund_type' => 'other', 'field' => 'fund_type'],
        'Unknown restriction'       => ['restriction' => 'partial', 'field' => 'restriction'],
        'Description too long'      => ['description' => str_repeat('x', FUND_DESCRIPTION_MAX + 1), 'field' => 'description'],
    ];
    foreach ($invalid as $label => $change) {
        $field = $change['field'];
        unset($change['field']);
        $errors = fund_edit_errors($pdo, $id, fund_edit_input(array_merge($valid, $change)));
        check(isset($errors[$field]) && $errors[$field] !== '', "$label is rejected with a message");
    }

    check(fund_edit_errors($pdo, $id, $valid) === [], 'A fund may keep its own name');
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
