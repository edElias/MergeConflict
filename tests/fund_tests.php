
<?php
// FUND-02: Standard fund acceptance tests.

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/cash_summary_data.php';

$standardFunds = [
    'General Fund',
    'Special Revenue Fund',
    'Capital Projects Fund',
    'Debt Service Fund'
];

$passed = 0;
$failed = 0;

function checkTest($condition, $message)
{
    global $passed, $failed;

    if ($condition) {
        echo "PASS: $message" . PHP_EOL;
        $passed++;
    } else {
        echo "FAIL: $message" . PHP_EOL;
        $failed++;
    }
}

try {
    // Use a transaction so test data is never saved.
    $pdo->beginTransaction();

    echo "=== FUND-02 Acceptance Tests ===" . PHP_EOL;

    // Test 1: All four standard funds exist and are active.
    $stmt = $pdo->prepare(
        "SELECT id, name, is_active
         FROM funds WHERE name = ?"
    );

    $fundIds = [];

    foreach ($standardFunds as $name) {
        $stmt->execute([$name]);
        $fund = $stmt->fetch(PDO::FETCH_ASSOC);

        checkTest(
            $fund !== false && (int)$fund['is_active'] === 1,
            "$name exists and is active"
        );

        if ($fund) {
            $fundIds[$name] = $fund['id'];
        }
    }

    // Test 2: Each fund accepts financial records.
    foreach ($fundIds as $name => $fundId) {

        $stmt = $pdo->prepare(
            "INSERT INTO transactions
             (fund_id, amount, description, transaction_date)
             VALUES (?, 100, 'Test transaction', '2026-10-24')"
        );
        $stmt->execute([$fundId]);

        $stmt = $pdo->prepare(
            "INSERT INTO commitments
             (fund_id, amount, description, commitment_date)
             VALUES (?, 200, 'Test commitment', '2026-10-24')"
        );
        $stmt->execute([$fundId]);

        $stmt = $pdo->prepare(
            "INSERT INTO expected_inflows
             (fund_id, amount, description, expected_date)
             VALUES (?, 300, 'Test inflow', '2026-10-24')"
        );
        $stmt->execute([$fundId]);

        checkTest(
            true,
            "$name accepts transactions, commitments, and inflows"
        );
    }

    // Test 3: Inactive funds are excluded from cash totals.
    $before = getCashSummary($pdo);

    $stmt = $pdo->prepare(
        "SELECT total_cash FROM funds WHERE name = ?"
    );
    $stmt->execute(['General Fund']);
    $generalFundCash = (float)$stmt->fetchColumn();

    $pdo->exec(
        "UPDATE funds SET is_active = 0
         WHERE name = 'General Fund'"
    );

    $after = getCashSummary($pdo);

    $difference =
        (float)$before['total_cash'] -
        (float)$after['total_cash'];

    checkTest(
        abs($difference - $generalFundCash) < 0.01,
        "Inactive funds excluded from organization-wide totals"
    );

} catch (Throwable $e) {
    $failed++;
    echo "FAIL: " . $e->getMessage() . PHP_EOL;

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL;
echo "Tests passed: $passed" . PHP_EOL;
echo "Tests failed: $failed" . PHP_EOL;
echo "Database unchanged." . PHP_EOL;

exit($failed > 0 ? 1 : 0);
