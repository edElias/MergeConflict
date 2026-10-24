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


 // FUND-02: Add active status to existing funds.
$columns = $pdo->query("PRAGMA table_info(funds)")
               ->fetchAll(PDO::FETCH_ASSOC);

$hasActiveColumn = false;

foreach ($columns as $column) {
    if ($column['name'] === 'is_active') {
        $hasActiveColumn = true;
        break;
    }
}

if (!$hasActiveColumn) {
    $pdo->exec(
        'ALTER TABLE funds
         ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1
         CHECK (is_active IN (0, 1))'
    );
}


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

// Add the newer columns/tables (profile fields, fund type, projections, reset
// attempts). Same code db.php runs automatically; see public/includes/schema.php.
ensure_schema($pdo);

// MER-26: Fill in the mock admin's profile once; later edits from the profile page are kept.
$pdo->exec("UPDATE users
    SET first_name = 'Admin', last_name = 'User', phone = '(555) 010-0000'
    WHERE username = 'admin' AND first_name = ''");

// Sample custom funds: one active (shown on the Funds page), one closed (hidden).
$stmt = $pdo->prepare(
    'INSERT OR IGNORE INTO funds
     (name, total_cash, restricted_cash, committed_funds, fund_type, is_active)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute(['Downtown Parks Grant', 250000, 250000, 120000, 'custom', 1]);
$stmt->execute(['Riverfront Festival 2025', 0, 0, 0, 'custom', 0]);

// MER-29: Projected 30-day balances — DUMMY VALUES until the ML forecast (MER-17)
// is ready; the forecast should replace these rows and set source = 'ml'.
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


 
 // FUND-02: Transactions associated with funds.
$pdo->exec('CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fund_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    description TEXT,
    transaction_date TEXT NOT NULL,
    FOREIGN KEY (fund_id) REFERENCES funds(id)
)');



 // FUND-02: Commitments associated with funds.
$pdo->exec('CREATE TABLE IF NOT EXISTS commitments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fund_id INTEGER NOT NULL,
    amount REAL NOT NULL CHECK (amount >= 0),
    description TEXT,
    commitment_date TEXT NOT NULL,
    FOREIGN KEY (fund_id) REFERENCES funds(id)
)');


 // FUND-02: Expected inflows associated with funds.
$pdo->exec('CREATE TABLE IF NOT EXISTS expected_inflows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fund_id INTEGER NOT NULL,
    amount REAL NOT NULL CHECK (amount >= 0),
    description TEXT,
    expected_date TEXT NOT NULL,
    FOREIGN KEY (fund_id) REFERENCES funds(id)
)');



echo "Done\n";
