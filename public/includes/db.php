<?php

$dbPath = __DIR__ . '/../../data/app.sqlite';

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// FUND-02
$pdo->exec('PRAGMA foreign_keys = ON');

// Add any newer columns/tables to databases created before them.
require_once __DIR__ . '/schema.php';
ensure_schema($pdo);
