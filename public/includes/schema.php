<?php
// Additive schema changes (new columns/tables) that the app needs on top of the
// base tables created by scripts/init_db.php. Runs automatically from db.php, so a
// database created before these changes keeps working without re-running init_db.
// Bump SCHEMA_VERSION whenever a change is added below.

const SCHEMA_VERSION = 2;

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

    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
}
