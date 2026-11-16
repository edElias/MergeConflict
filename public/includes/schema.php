<?php
// Additive schema changes (new columns/tables) that the app needs on top of the
// base tables created by scripts/init_db.php. Runs automatically from db.php, so a
// database created before these changes keeps working without re-running init_db.
// Bump SCHEMA_VERSION whenever a change is added below.

const SCHEMA_VERSION = 6;

function ensure_schema(PDO $pdo): void
{
    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() >= SCHEMA_VERSION) {
        return;
    }

    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('users', $tables, true) || !in_array('funds', $tables, true)) {
        return; // Base tables not created yet; init_db.php calls this again after creating them.
    }

    $columns = fn(string $table) => array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    $addColumns = function (string $table, array $definitions) use ($pdo, $columns): void {
        $existing = $columns($table);
        foreach ($definitions as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        }
    };

    // MER-26: Profile & settings fields.
    $addColumns('users', [
        'first_name'  => "TEXT NOT NULL DEFAULT ''",
        'last_name'   => "TEXT NOT NULL DEFAULT ''",
        'phone'       => "TEXT NOT NULL DEFAULT ''",
        'role'        => "TEXT NOT NULL DEFAULT 'Finance Director'",
        'avatar_file' => 'TEXT',
    ]);

    // MER-26: Password reset code attempts, tracked per account (not per browser
    // session) so starting over or resending a code can't reset the limit.
    $pdo->exec('CREATE TABLE IF NOT EXISTS password_resets (
        user_id           INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        code_sent_at      INTEGER NOT NULL,
        attempts          INTEGER NOT NULL DEFAULT 0,
        window_started_at INTEGER NOT NULL
    )');

    // MER-29: Fund type and active flag for DASH-02 fund summaries.
    $addColumns('funds', [
        'fund_type' => "TEXT NOT NULL DEFAULT 'standard' CHECK (fund_type IN ('standard', 'custom'))",
        'is_active' => 'INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1))',
    ]);

    // MER-29: Projected 30-day balance per fund. Holds dummy values until the ML
    // forecast (MER-17) replaces them and sets source = 'ml'.
    $pdo->exec("CREATE TABLE IF NOT EXISTS fund_projections (
        fund_id          INTEGER PRIMARY KEY REFERENCES funds(id) ON DELETE CASCADE,
        projected_30_day REAL NOT NULL,
        source           TEXT NOT NULL DEFAULT 'dummy',
        updated_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    // FUND-04: Editable fund details (restriction designation and description).
    $addColumns('funds', [
        'restriction' => "TEXT NOT NULL DEFAULT 'unrestricted' CHECK (restriction IN ('unrestricted', 'restricted'))",
        'description' => "TEXT NOT NULL DEFAULT ''",
    ]);

    //FUND-03: Store the selected category for custom funds.
    $addColumns('funds', [
        'fund_category' => "TEXT NOT NULL DEFAULT 'Other'",
        ]);

    // FUND-06: When a fund was archived. Empty for active funds, and for funds
    // archived before this column existed.
    $addColumns('funds', ['archived_at' => 'TEXT']);

    // PLAN-01: Commitment name and status. A database with no commitments table yet
    // gets these columns when scripts/init_db.php creates the table.
    if (in_array('commitments', $tables, true)) {
        $addColumns('commitments', [
            'name'   => "TEXT NOT NULL DEFAULT ''",
            'status' => "TEXT NOT NULL DEFAULT 'Planned'",
        ]);
    }

    // FIN-01: Balances are calculated from transactions (see fund_balance.php), so the
    // stored total_cash becomes opening_balance: the fund's cash before its recorded
    // transactions. It is set so every fund's balance today is unchanged.
    if (in_array('total_cash', $columns('funds'), true)) {
        migrate_total_cash_to_opening_balance($pdo, in_array('transactions', $tables, true));
    }

    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
}

// FIN-01: Renames funds.total_cash to opening_balance and drops the CHECKs that held
// restricted and committed amounts to total_cash, which a calculated balance can't
// enforce. SQLite can't drop a CHECK in place, so the table is rebuilt: create a copy
// with the new definition, copy the rows, drop the old table, rename the copy
// (https://www.sqlite.org/lang_altertable.html#otheralter). Foreign keys are off
// while the old table is gone; ids are copied unchanged, so references still match.
function migrate_total_cash_to_opening_balance(PDO $pdo, bool $hasTransactions): void
{
    $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'funds'")->fetchColumn();
    $changes = [
        'CREATE TABLE funds ('                                            => 'CREATE TABLE funds_new (',
        'total_cash REAL NOT NULL DEFAULT 0,'                             => 'opening_balance REAL NOT NULL DEFAULT 0,',
        'CHECK (total_cash >= 0),'                                        => '',
        'CHECK (restricted_cash >= 0 AND restricted_cash <= total_cash),' => 'CHECK (restricted_cash >= 0),',
        'CHECK (committed_funds >= 0 AND committed_funds <= total_cash)'  => 'CHECK (committed_funds >= 0)',
    ];
    foreach ($changes as $from => $to) {
        if (substr_count($sql, $from) !== 1) {
            throw new RuntimeException("FIN-01: Unexpected funds table definition (looking for \"$from\").");
        }
        $sql = str_replace($from, $to, $sql);
    }

    // Opening balance = old total_cash minus the transactions already counted in it.
    $posted = $hasTransactions
        ? 'COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.fund_id = funds.id AND t.transaction_date <= :today), 0)'
        : '0';
    $columns = array_column($pdo->query('PRAGMA table_info(funds)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $target = array_map(fn($c) => $c === 'total_cash' ? 'opening_balance' : $c, $columns);
    $source = array_map(fn($c) => $c === 'total_cash' ? "total_cash - $posted" : $c, $columns);

    $foreignKeys = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
    $pdo->exec('PRAGMA foreign_keys = OFF');
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $copy = $pdo->prepare('INSERT INTO funds_new (' . implode(', ', $target) . ') SELECT ' . implode(', ', $source) . ' FROM funds');
        $copy->execute($hasTransactions ? ['today' => date('Y-m-d')] : []);
        $pdo->exec('DROP TABLE funds');
        $pdo->exec('ALTER TABLE funds_new RENAME TO funds');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ' . $foreignKeys);
    }
}
