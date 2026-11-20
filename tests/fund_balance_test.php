<?php
// FIN-01: Calculated fund balances acceptance tests.
// Run scripts/init_db.php first. All changes are rolled back, so the database is unchanged.
// The migration test uses its own temporary database file.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the terminal.');
}

require __DIR__ . '/../public/includes/db.php';
require __DIR__ . '/../public/includes/cash_summary_data.php';
require __DIR__ . '/../public/includes/commitment_data.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ": $message" . PHP_EOL;
    $condition ? $passed++ : $failed++;
}

function add_transaction(PDO $pdo, int $fundId, float $amount, string $date): void
{
    $pdo->prepare("INSERT INTO transactions (fund_id, amount, description, transaction_date)
                   VALUES (?, ?, 'FIN-01 test', ?)")->execute([$fundId, $amount, $date]);
}

function balance(PDO $pdo, int $fundId, ?string $asOf = null): float
{
    return (float) getFundDetails($pdo, $fundId, $asOf)['current_balance'];
}

$day = fn(int $offset) => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . "$offset days"));

try {
    $pdo->beginTransaction();
    echo '=== FIN-01 Acceptance Tests ===' . PHP_EOL;

    $id = (int) $pdo->query("SELECT id FROM funds WHERE name = 'Debt Service Fund'")->fetchColumn();
    $before = balance($pdo, $id);
    // The fund may have real (sample) transactions, so compare against its own balance 30 days ago.
    $before30 = balance($pdo, $id, $day(-30));
    $totalBefore = (float) getCashSummary($pdo)['total_cash'];

    // Current balance equals opening balance + posted inflows - posted outflows.
    add_transaction($pdo, $id, 1000, $day(-5));
    add_transaction($pdo, $id, -400, $day(-3));
    $stmt = $pdo->prepare('SELECT amount FROM transactions WHERE fund_id = ? AND transaction_date <= ?');
    $stmt->execute([$id, $day(0)]);
    $amounts = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $inflows = array_sum(array_filter($amounts, fn($a) => $a > 0));
    $outflows = -array_sum(array_filter($amounts, fn($a) => $a < 0));
    $opening = (float) getFundDetails($pdo, $id)['opening_balance'];
    check(balance($pdo, $id) === $opening + $inflows - $outflows, 'Balance = opening balance + inflows - outflows');
    check(balance($pdo, $id) === $before + 600, 'An inflow raises and an outflow lowers the balance');

    // Only posted transactions dated on or before the balance date count.
    add_transaction($pdo, $id, 5000, $day(1));
    check(balance($pdo, $id) === $before + 600, "A transaction dated after today is left out of today's balance");
    check(balance($pdo, $id, $day(1)) === $before + 5600, 'It counts once the balance date reaches its date');
    add_transaction($pdo, $id, 70, $day(0));
    check(balance($pdo, $id) === $before + 670, 'A transaction dated on the balance date counts');
    check(balance($pdo, $id, $day(-4)) === $before + 1000, 'An earlier balance date counts only earlier transactions');
    check(balance($pdo, $id, $day(-30)) === $before30, 'A balance date before every test transaction gives the starting balance');

    // Commitments and expected inflows do not change cash balances.
    $cash = balance($pdo, $id);
    $total = (float) getCashSummary($pdo)['total_cash'];
    create_commitment($pdo, [
        'name' => 'FIN-01 test commitment', 'amount' => '250', 'due_date' => $day(5),
        'fund_id' => (string) $id, 'description' => '',
    ]);
    $pdo->prepare("INSERT INTO expected_inflows (fund_id, amount, description, expected_date)
                   VALUES (?, 900, 'FIN-01 test inflow', ?)")->execute([$id, $day(5)]);
    check(balance($pdo, $id) === $cash, "A commitment and an expected inflow leave the fund's balance unchanged");
    check((float) getCashSummary($pdo)['total_cash'] === $total, 'They leave organization total cash unchanged');
    check((float) getFundDetails($pdo, $id)['committed'] > 0, 'The commitment still counts as committed');

    // Dashboard and fund pages read the same calculated balances.
    $summary = getCashSummary($pdo);
    $funds = getFundSummaries($pdo);
    check(abs((float) $summary['total_cash'] - array_sum(array_column($funds, 'current_balance'))) < 0.005,
        'Dashboard total cash = sum of active fund balances');
    check((float) $summary['total_cash'] === $totalBefore + 670, 'Dashboard total reflects the posted transactions');
    $listed = array_column($funds, null, 'id')[$id];
    check((float) $listed['current_balance'] === balance($pdo, $id), 'Funds list and fund details show the same balance');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// An existing database is migrated without changing any fund's balance today.
$file = tempnam(sys_get_temp_dir(), 'fin01');
try {
    $old = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $old->exec('PRAGMA foreign_keys = ON');
    // The funds table as scripts/init_db.php created it before FIN-01.
    $old->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL, display_name TEXT NOT NULL)');
    $old->exec('CREATE TABLE IF NOT EXISTS funds (
     id INTEGER PRIMARY KEY AUTOINCREMENT,
     name TEXT NOT NULL UNIQUE,
     total_cash REAL NOT NULL DEFAULT 0,
     restricted_cash REAL NOT NULL DEFAULT 0,
     committed_funds REAL NOT NULL DEFAULT 0,
     CHECK (total_cash >= 0),
     CHECK (restricted_cash >= 0 AND restricted_cash <= total_cash),
     CHECK (committed_funds >= 0 AND committed_funds <= total_cash)
 )');
    $old->exec('ALTER TABLE funds ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1))');
    $old->exec('CREATE TABLE transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, fund_id INTEGER NOT NULL,
        amount REAL NOT NULL, description TEXT, transaction_date TEXT NOT NULL, FOREIGN KEY (fund_id) REFERENCES funds(id))');
    $old->exec("INSERT INTO funds (name, total_cash, restricted_cash, committed_funds, is_active) VALUES
        ('Alpha', 1000, 100, 400, 1), ('Beta', 500, 0, 0, 0)");
    add_transaction($old, 1, 300, $day(-20));
    add_transaction($old, 1, -100, $day(-10));
    add_transaction($old, 1, 50, $day(3));

    ensure_schema($old);

    $columns = array_column($old->query('PRAGMA table_info(funds)')->fetchAll(), 'name');
    check(!in_array('total_cash', $columns, true) && in_array('opening_balance', $columns, true),
        'Migration: total_cash is replaced by opening_balance');
    check((float) $old->query('SELECT opening_balance FROM funds WHERE id = 1')->fetchColumn() === 800.0,
        "Migration: opening balance excludes transactions already in the old total");
    check(balance($old, 1) === 1000.0 && balance($old, 2) === 500.0, "Migration: every fund's balance today is unchanged");
    $alpha = getFundDetails($old, 1);
    check($alpha['name'] === 'Alpha' && (float) $alpha['restricted_cash'] === 100.0 && (float) $alpha['committed'] === 400.0
        && (int) getFundDetails($old, 2)['is_active'] === 0, 'Migration: other fund values are kept');
    check(count($old->query('SELECT * FROM transactions')->fetchAll()) === 3, 'Migration: transactions are kept');
    $old->exec('UPDATE funds SET committed_funds = 5000 WHERE id = 1');
    check(true, 'Migration: committed funds are no longer capped by a stored cash column');
    $rejected = function (string $sql) use ($old): bool {
        try {
            $old->exec($sql);
            return false;
        } catch (PDOException $e) {
            return true;
        }
    };
    check($rejected('UPDATE funds SET restricted_cash = -1 WHERE id = 1'), 'Migration: negative restricted cash is still rejected');
    check($rejected("INSERT INTO transactions (fund_id, amount, transaction_date) VALUES (999, 1, '2026-01-01')"),
        'Migration: transactions still must belong to an existing fund');
    $old->exec("INSERT INTO funds (name) VALUES ('Gamma')");
    check((int) $old->lastInsertId() === 3, 'Migration: new funds get the next id');
    check((int) $old->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'Migration: foreign keys are switched back on');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
} finally {
    $old = null;
    unlink($file);
}

echo PHP_EOL . "Tests passed: $passed" . PHP_EOL . "Tests failed: $failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
