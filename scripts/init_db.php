<?php
require __DIR__ . '/../public/includes/db.php';

$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name  TEXT NOT NULL
)');

$stmt = $pdo->prepare(
    'INSERT OR IGNORE INTO users (username, password_hash, display_name) VALUES (?, ?, ?)'
);
$stmt->execute(['admin', password_hash('password123', PASSWORD_DEFAULT), 'Admin User']);

// MER-26: Profile & settings fields. Added with ALTER TABLE so existing databases pick them up.
$userColumns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
foreach ([
    'first_name'  => "TEXT NOT NULL DEFAULT ''",
    'last_name'   => "TEXT NOT NULL DEFAULT ''",
    'phone'       => "TEXT NOT NULL DEFAULT ''",
    'role'        => "TEXT NOT NULL DEFAULT 'Finance Director'",
    'avatar_file' => 'TEXT',
] as $column => $definition) {
    if (!in_array($column, $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
    }
}

// Fill in the mock admin's profile once; later edits from the profile page are kept.
$pdo->exec("UPDATE users
    SET first_name = 'Admin', last_name = 'User', phone = '(555) 010-0000'
    WHERE username = 'admin' AND first_name = ''");



 // MER-25: Fund balances for DASH-01.
 $pdo->exec('CREATE TABLE IF NOT EXISTS funds (
     id INTEGER PRIMARY KEY AUTOINCREMENT,
     name TEXT NOT NULL UNIQUE,
     total_cash REAL NOT NULL DEFAULT 0,
     restricted_cash REAL NOT NULL DEFAULT 0,
     committed_funds REAL NOT NULL DEFAULT 0,
     CHECK (total_cash >= 0),
     CHECK (restricted_cash >= 0 AND restricted_cash <= total_cash),
     CHECK (committed_funds >= 0 AND committed_funds <= total_cash)
 )');

 // Sprint 1 mock data, allocated across the four supported funds.
 $funds = [
     ['General Fund', 1500000, 200000, 600000],
     ['Special Revenue Fund', 1000000, 700000, 400000],
     ['Capital Projects Fund', 1250000, 500000, 575000],
     ['Debt Service Fund', 500000, 200000, 300000],
 ];

 $stmt = $pdo->prepare(
     'INSERT OR IGNORE INTO funds
      (name, total_cash, restricted_cash, committed_funds)
      VALUES (?, ?, ?, ?)'
 );

 foreach ($funds as $fund) {
     $stmt->execute($fund);
 }

// MER-29: Fund type and active flag for DASH-02 fund summaries.
// Added with ALTER TABLE so databases created before MER-29 pick them up too.
$fundColumns = array_column($pdo->query('PRAGMA table_info(funds)')->fetchAll(), 'name');
if (!in_array('fund_type', $fundColumns, true)) {
    $pdo->exec("ALTER TABLE funds ADD COLUMN fund_type TEXT NOT NULL DEFAULT 'standard'
        CHECK (fund_type IN ('standard', 'custom'))");
}
if (!in_array('is_active', $fundColumns, true)) {
    $pdo->exec('ALTER TABLE funds ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1
        CHECK (is_active IN (0, 1))');
}

// Sample custom funds: one active (shown on the Funds page), one closed (hidden).
$stmt = $pdo->prepare(
    'INSERT OR IGNORE INTO funds
     (name, total_cash, restricted_cash, committed_funds, fund_type, is_active)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute(['Downtown Parks Grant', 250000, 250000, 120000, 'custom', 1]);
$stmt->execute(['Riverfront Festival 2025', 0, 0, 0, 'custom', 0]);

// MER-29: Projected 30-day balance per fund.
// DUMMY VALUES until the ML forecast (MER-17) is ready; the forecast should
// replace these rows and set source = 'ml'.
$pdo->exec("CREATE TABLE IF NOT EXISTS fund_projections (
    fund_id          INTEGER PRIMARY KEY REFERENCES funds(id) ON DELETE CASCADE,
    projected_30_day REAL NOT NULL,
    source           TEXT NOT NULL DEFAULT 'dummy',
    updated_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$projections = [
    'General Fund'          => 1420000,
    'Special Revenue Fund'  => 1050000,
    'Capital Projects Fund' => 980000,
    'Debt Service Fund'     => 140000,
    'Downtown Parks Grant'  => 210000,
];

$stmt = $pdo->prepare(
    "INSERT OR IGNORE INTO fund_projections (fund_id, projected_30_day, source)
     SELECT id, ?, 'dummy' FROM funds WHERE name = ?"
);

foreach ($projections as $name => $projected) {
    $stmt->execute([$projected, $name]);
}






echo "Done\n";
