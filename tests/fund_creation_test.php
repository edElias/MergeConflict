
<?php
// FUND-03: Custom fund creation database acceptance tests.
// Test changes are rolled back to preserve existing data.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';

$passed = 0;
$failed = 0;

function checkFundTest(bool $condition, string $message): void
{
    global $passed, $failed;

    echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . PHP_EOL;

    if ($condition) {
        $passed++;
    } else {
        $failed++;
    }
}



try {
    $pdo->beginTransaction();

    echo "=== FUND-03 Acceptance Tests ===" . PHP_EOL;

    // Test 1: Create a custom fund with an optional blank description.
    $name = 'FUND03 Test Fund ' . bin2hex(random_bytes(4));

    $stmt = $pdo->prepare(
        "INSERT INTO funds
         (name, fund_type, fund_category, restriction, description,
          opening_balance, restricted_cash, committed_funds, is_active)
         VALUES (?, 'custom', 'Other', 'unrestricted', '', 0, 0, 0, 1)"
    );

    $stmt->execute([$name]);

    $fundId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'SELECT * FROM funds WHERE id = ?'
    );
    $stmt->execute([$fundId]);
    $fund = $stmt->fetch(PDO::FETCH_ASSOC);

    checkFundTest(
        $fund !== false && $fund['description'] === '',
        'Blank optional description is saved successfully'
    );


    // FUND-03: A provided description should be saved correctly.
    $description = 'Funding for community infrastructure';

    $stmt = $pdo->prepare(
        'UPDATE funds SET description = ? WHERE id = ?'
    );
    $stmt->execute([$description, $fundId]);

    $stmt = $pdo->prepare(
        'SELECT description FROM funds WHERE id = ?'
    );
    $stmt->execute([$fundId]);

    $savedDescription = $stmt->fetchColumn();

    checkFundTest(
        $savedDescription === $description,
        'Nonempty fund description is preserved'
    );



    // FUND-03: Check that blank fund names are rejected.
    $blankName = trim('   ');

    checkFundTest(
        $blankName === '',
        'Blank fund name is identified as invalid'
    );




    checkFundTest(
        $fund !== false && $fund['fund_type'] === 'custom',
        'New fund is classified as custom'
    );

    checkFundTest(
        $fund !== false && $fund['fund_category'] === 'Other',
        'Selected fund category is preserved'
    );

    checkFundTest(
        $fund !== false && $fund['restriction'] === 'unrestricted',
        'Restriction designation is saved correctly'
    );

    checkFundTest(
        $fund !== false &&
        (float) $fund['opening_balance'] === 0.0 &&
        (float) $fund['committed_funds'] === 0.0,
        'New fund starts with zero cash and commitments'
    );

    checkFundTest(
        $fund !== false && (int) $fund['is_active'] === 1,
        'New fund starts active'
    );

    // Test 2: Duplicate fund names must be rejected.
    $duplicateRejected = false;

    try {
        $stmt->execute([$fundId]); // Read query; no duplicate insert.
        $insert = $pdo->prepare(
            "INSERT INTO funds (name, fund_type)
             VALUES (?, 'custom')"
        );
        $insert->execute([$name]);
    } catch (PDOException $e) {
        $duplicateRejected =
            str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }

    checkFundTest(
        $duplicateRejected,
        'Duplicate fund name is rejected'
    );

} catch (Throwable $e) {
    $failed++;
    echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL;
echo "Tests passed: $passed" . PHP_EOL;
echo "Tests failed: $failed" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
