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



// FUND-06: Sample history for the closed custom fund, so its preserved records can be reviewed.
$pdo->exec("UPDATE funds SET archived_at = '2026-10-02'
    WHERE name = 'Riverfront Festival 2025' AND is_active = 0 AND archived_at IS NULL");

$fundId = $pdo->query("SELECT id FROM funds WHERE name = 'Riverfront Festival 2025'")->fetchColumn();
$hasHistory = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE fund_id = ?');
$hasHistory->execute([$fundId]);

if ((int) $hasHistory->fetchColumn() === 0) {
    $stmt = $pdo->prepare(
        'INSERT INTO transactions (fund_id, amount, description, transaction_date) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$fundId, 38400, 'Sponsorship receipts', '2025-08-29']);
    $stmt->execute([$fundId, -38400, 'Festival vendor payments', '2025-09-12']);
}

// MER-34: Sample records for the active funds, dated relative to today so the
// "next 30 days" totals have something to show. Each fund's commitments add up to
// its committed_funds, and a fund only gets samples if it has no records of that kind.
$day = fn(int $offset) => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . "$offset days"));

$samples = [
    'General Fund' => [
        'transactions' => [
            [-132400, 'Payroll – October', -6], [48250, 'Sales tax receipt', -10],
            [-6180, 'Utility payment', -12], [3900, 'Permit fees', -18],
            [-21760, 'Vendor payment', -25], [-2310, 'Office supplies', -40],
        ],
        'commitments' => [
            [85000, 'Road salt contract', 12], [140000, 'Payroll – November', 21],
            [35000, 'Fleet maintenance', 26], [340000, 'Building HVAC replacement', 70],
        ],
        'inflows' => [[120000, 'Property tax distribution', 14], [60000, 'State revenue sharing', 25]],
    ],
    'Special Revenue Fund' => [
        'transactions' => [[31600, 'Motor fuel tax receipt', -17]],
        'commitments' => [[150000, 'Street lighting upgrade', 18], [250000, 'Library programs', 90]],
        'inflows' => [[120000, 'Motor fuel tax distribution', 9], [80000, 'Hotel tax receipts', 23]],
    ],
    'Capital Projects Fund' => [
        'transactions' => [[-74300, 'Road resurfacing invoice', -14]],
        'commitments' => [
            [300000, 'Road resurfacing phase 2', 16], [45000, 'Bridge inspection', 28],
            [230000, 'Fire station design', 75],
        ],
        'inflows' => [[75000, 'Bond proceeds draw', 20]],
    ],
    'Debt Service Fund' => [
        'transactions' => [[-18750, 'Bond interest payment', -7]],
        'commitments' => [[300000, 'Bond principal and interest', 19]],
        'inflows' => [[40000, 'Debt service property tax levy', 20]],
    ],
    'Downtown Parks Grant' => [
        'transactions' => [[25000, 'Park grant draw', -11]],
        'commitments' => [[60000, 'Trail contractor', 15], [60000, 'Playground equipment', 45]],
        'inflows' => [[20000, 'Park grant reimbursement', 27]],
    ],
];

$tables = [
    'transactions' => ['transactions', 'transaction_date'],
    'commitments'  => ['commitments', 'commitment_date'],
    'inflows'      => ['expected_inflows', 'expected_date'],
];

foreach ($samples as $name => $kinds) {
    $find = $pdo->prepare('SELECT id FROM funds WHERE name = ?');
    $find->execute([$name]);
    $fundId = $find->fetchColumn();
    if ($fundId === false) {
        continue;
    }

    foreach ($kinds as $kind => $rows) {
        [$table, $dateColumn] = $tables[$kind];
        $existing = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE fund_id = ?");
        $existing->execute([$fundId]);
        if ((int) $existing->fetchColumn() > 0) {
            continue;
        }

        $insert = $pdo->prepare("INSERT INTO $table (fund_id, amount, description, $dateColumn) VALUES (?, ?, ?, ?)");
        foreach ($rows as [$amount, $description, $offset]) {
            $insert->execute([$fundId, $amount, $description, $day($offset)]);
        }
    }
}

echo "Done\n";
