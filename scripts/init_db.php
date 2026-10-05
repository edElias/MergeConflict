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

echo "Done\n";
