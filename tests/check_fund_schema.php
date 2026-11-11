
<?php
// FUND-03: Read-only database verification.
require __DIR__ . '/../public/includes/db.php';

echo "=== FUNDS TABLE COLUMNS ===\n";

$columns = $pdo->query("PRAGMA table_info(funds)")
               ->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo $column['name'] . "\n";
}

echo "\n=== EXISTING FUNDS ===\n";

$stmt = $pdo->query(
    "SELECT id, name, fund_type, is_active
     FROM funds
     ORDER BY id"
);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fund) {
    echo "ID: {$fund['id']} | ";
    echo "Name: {$fund['name']} | ";
    echo "Type: {$fund['fund_type']} | ";
    echo "Active: {$fund['is_active']}\n";
}
