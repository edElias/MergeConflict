<?php
// FIN-02 (MER-49): Shared financial source of truth acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/cash_summary_data.php';
require __DIR__ . '/../public/includes/fund_summary_data.php';
require __DIR__ . '/../public/includes/commitment_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

// Money compared to the cent, so float rounding never fails a check.
function same(float $a, float $b): bool
{
    return abs($a - $b) < 0.005;
}

// What the Funds page lists: [id => fund row].
function funds_page(PDO $pdo): array
{
    return array_column(getFundSummaries($pdo), null, 'id');
}

try {
    $pdo->beginTransaction();
    echo '=== FIN-02 Acceptance Tests ===' . PHP_EOL;

    // Two test funds with known balances: one restricted, one unrestricted.
    $add = $pdo->prepare("INSERT INTO funds (name, opening_balance, restricted_cash, committed_funds, restriction)
                          VALUES (?, ?, 0, 0, ?)");
    $add->execute(['FS Restricted Fund', 10000, 'restricted']);
    $restricted = (int) $pdo->lastInsertId();
    $add->execute(['FS Open Fund', 4000, 'unrestricted']);
    $open = (int) $pdo->lastInsertId();

    $before = getCashSummary($pdo);
    $funds = funds_page($pdo);

    // The Funds page and dashboard display consistent values from the same records.
    check(same((float) $before['total_cash'], array_sum(array_column($funds, 'current_balance'))),
        'Dashboard total cash equals the sum of the Funds page balances');
    check(same((float) $before['committed_funds'], array_sum(array_column($funds, 'committed'))),
        'Dashboard committed equals the sum of the Funds page committed amounts');
    $disagree = array_filter($funds, fn($f) => !same((float) $f['current_balance'],
        (float) getFundDetails($pdo, (int) $f['id'])['current_balance']));
    check($disagree === [], 'The Funds page and each fund page show the same balance');

    // Organization-wide total cash equals the active fund balances (archived excluded).
    $pdo->prepare('UPDATE funds SET is_active = 0 WHERE id = ?')->execute([$open]);
    check(same((float) getCashSummary($pdo)['total_cash'], (float) $before['total_cash'] - 4000),
        "An archived fund's balance leaves total cash");
    $pdo->prepare('UPDATE funds SET is_active = 1 WHERE id = ?')->execute([$open]);

    // Restricted and unrestricted cash come from each fund's classification.
    $byClass = ['restricted' => 0.0, 'unrestricted' => 0.0];
    foreach ($pdo->query('SELECT id, restriction FROM funds WHERE is_active = 1') as $row) {
        $byClass[$row['restriction']] += (float) getFundDetails($pdo, (int) $row['id'])['current_balance'];
    }
    check(same((float) $before['restricted_cash'], $byClass['restricted']),
        'Restricted cash is the balance of funds classified restricted');
    check(same((float) $before['unrestricted_cash'], $byClass['unrestricted']),
        'Unrestricted cash is the balance of funds classified unrestricted');
    check(same((float) $before['restricted_cash'] + (float) $before['unrestricted_cash'], (float) $before['total_cash']),
        'Restricted + unrestricted = total cash');

    // Reclassifying a fund moves its whole balance; the old placeholder value is ignored.
    $pdo->prepare("UPDATE funds SET restriction = 'restricted' WHERE id = ?")->execute([$open]);
    $after = getCashSummary($pdo);
    check(same((float) $after['restricted_cash'] - (float) $before['restricted_cash'], 4000)
        && same((float) $after['total_cash'], (float) $before['total_cash']),
        "Reclassifying a fund moves its balance to restricted without changing total cash");
    $pdo->prepare('UPDATE funds SET restricted_cash = 999999 WHERE id = ?')->execute([$restricted]);
    check(same((float) getCashSummary($pdo)['restricted_cash'], (float) $after['restricted_cash']),
        'The old hand-entered restricted_cash value no longer affects totals');

    // A posted transaction changes the Funds page and dashboard by the same amount.
    $pdo->prepare("INSERT INTO transactions (fund_id, transaction_date, amount, category, description) VALUES (?, ?, 250, 'Test', 'FS inflow')")
        ->execute([$restricted, date('Y-m-d')]);
    check(same((float) funds_page($pdo)[$restricted]['current_balance'], 10250)
        && same((float) getCashSummary($pdo)['total_cash'], (float) $after['total_cash'] + 250),
        'A transaction updates the fund and the dashboard alike');

    // Committed amounts remain distinct from actual cash.
    $cashBefore = (float) getCashSummary($pdo)['total_cash'];
    $committedBefore = (float) getCashSummary($pdo)['committed_funds'];
    create_commitment($pdo, ['fund_id' => (string) $restricted, 'name' => 'FS commitment', 'amount' => '3000',
                             'due_date' => date('Y-m-d', strtotime('+10 days')), 'description' => '']);
    $now = getCashSummary($pdo);
    check(same((float) $now['total_cash'], $cashBefore), 'A new commitment does not change total cash');
    check(same((float) $now['committed_funds'], $committedBefore + 3000), 'A new commitment raises committed');
    check(same((float) $now['uncommitted_balance'], (float) $now['total_cash'] - (float) $now['committed_funds']),
        'Uncommitted = total cash - committed');
    check(same((float) funds_page($pdo)[$restricted]['committed'], 3000), "The fund's committed amount shows on the Funds page");

    // The stored committed amount matches each fund's list of commitments.
    $mismatch = $pdo->query('SELECT f.name FROM funds f
        WHERE ABS(f.committed_funds - COALESCE((SELECT SUM(c.amount) FROM commitments c WHERE c.fund_id = f.id), 0)) > 0.005')
        ->fetchAll(PDO::FETCH_COLUMN);
    check($mismatch === [], 'Every fund\'s committed amount equals the sum of its commitments'
        . ($mismatch ? ' (differs: ' . implode(', ', $mismatch) . ')' : ''));
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
