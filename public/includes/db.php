<?php

// FUND-06: Stored dates use the organization's local day, not UTC, which is already
// tomorrow on Central-time evenings.
date_default_timezone_set('America/Chicago');

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
