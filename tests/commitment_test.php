<?php
// PLAN-01: Create commitment acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/commitment_data.php';
require __DIR__ . '/../public/includes/cash_summary_data.php';
require __DIR__ . '/../public/includes/fund_archive_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

function commitment_count(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM commitments')->fetchColumn();
}

try {
    $pdo->beginTransaction();
    echo '=== PLAN-01 Acceptance Tests ===' . PHP_EOL;

    $fundId = (int) $pdo->query("SELECT id FROM funds WHERE name = 'General Fund'")->fetchColumn();
    $archivedId = (int) $pdo->query('SELECT id FROM funds WHERE is_active = 0')->fetchColumn();
    $fundBefore = getFundDetails($pdo, $fundId);
    $summaryBefore = getCashSummary($pdo);
    $dueBefore = getCommitmentsDueTotal($pdo);
    $uncommitted = (float) $fundBefore['current_balance'] - (float) $fundBefore['committed'];

    $valid = [
        'name'        => 'Road resurfacing contract',
        'amount'      => '1250.50',
        'due_date'    => date('Y-m-d', strtotime('+10 days')),
        'fund_id'     => (string) $fundId,
        'description' => '',
    ];

    // The record includes a name, positive amount, due date, fund, and optional description.
    check(commitment_errors($pdo, $valid) === [], 'Valid commitment with no description passes validation');
    $id = create_commitment($pdo, commitment_input($valid));
    $stmt = $pdo->prepare('SELECT * FROM commitments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    check($row['name'] === $valid['name'], 'Name is saved');
    check((float) $row['amount'] === 1250.50, 'Amount is saved');
    check($row['commitment_date'] === $valid['due_date'], 'Due date is saved');
    check((int) $row['fund_id'] === $fundId, 'Fund is saved');

    // New commitments default to Planned status.
    check($row['status'] === 'Planned', 'Status is Planned');

    // A saved commitment appears in the appropriate views and calculations.
    $listed = array_column(getCommitments($pdo), null, 'id')[$id] ?? null;
    check($listed !== null && $listed['fund_name'] === 'General Fund', 'Commitment is listed with its fund');
    check(getCommitmentsDueTotal($pdo) === $dueBefore + 1250.50, 'Commitments due in 30 days includes it');
    $fundAfter = getFundDetails($pdo, $fundId);
    check((float) $fundAfter['committed'] === (float) $fundBefore['committed'] + 1250.50, "Fund's committed total rises by the amount");
    check($fundAfter['current_balance'] === $fundBefore['current_balance'], "Fund's balance is unchanged");
    $summaryAfter = getCashSummary($pdo);
    check((float) $summaryAfter['committed_funds'] === (float) $summaryBefore['committed_funds'] + 1250.50, 'Dashboard committed funds rises by the amount');
    check((float) $summaryAfter['uncommitted_balance'] === (float) $summaryBefore['uncommitted_balance'] - 1250.50, 'Dashboard uncommitted balance falls by the amount');
    $records = array_filter(getFundRecords($pdo, $fundId), fn($r) => $r['type'] === 'Commitment');
    check(in_array($valid['name'], array_column($records, 'description'), true), "Fund's stored records show the commitment by name");

    // Invalid or incomplete submitted records are not saved.
    $countBefore = commitment_count($pdo);
    $invalid = [
        'Blank name'                  => ['name' => '   ', 'field' => 'name'],
        'Name too long'               => ['name' => str_repeat('x', COMMITMENT_NAME_MAX + 1), 'field' => 'name'],
        'Missing amount'              => ['amount' => '', 'field' => 'amount'],
        'Zero amount'                 => ['amount' => '0', 'field' => 'amount'],
        'Negative amount'             => ['amount' => '-50', 'field' => 'amount'],
        'Non-numeric amount'          => ['amount' => 'abc', 'field' => 'amount'],
        'Amount over uncommitted cash' => ['amount' => number_format($uncommitted, 2, '.', ''), 'field' => 'amount'],
        'Missing due date'            => ['due_date' => '', 'field' => 'due_date'],
        'Impossible due date'         => ['due_date' => '2026-02-30', 'field' => 'due_date'],
        'Missing fund'                => ['fund_id' => '', 'field' => 'fund_id'],
        'Unknown fund'                => ['fund_id' => '999999', 'field' => 'fund_id'],
        'Archived fund'               => ['fund_id' => (string) $archivedId, 'field' => 'fund_id'],
        'Description too long'        => ['description' => str_repeat('x', COMMITMENT_DESCRIPTION_MAX + 1), 'field' => 'description'],
    ];
    foreach ($invalid as $label => $change) {
        $field = $change['field'];
        unset($change['field']);
        $errors = commitment_errors($pdo, commitment_input(array_merge($valid, $change)));
        check(isset($errors[$field]) && $errors[$field] !== '', "$label is rejected with a message");
    }
    check(commitment_count($pdo) === $countBefore, 'Rejected submissions save nothing');

    // A commitment may use all of the fund's remaining uncommitted cash.
    $remaining = (float) $fundAfter['current_balance'] - (float) $fundAfter['committed'];
    $all = array_merge($valid, ['amount' => number_format($remaining, 2, '.', '')]);
    check(commitment_errors($pdo, $all) === [], "Amount equal to the fund's uncommitted cash is accepted");
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
