<?php
// MER-26: Serves the signed-in user's profile photo. Photos live in data/avatars,
// outside the web root, so they are only reachable through this page.
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/account.php';

$stmt = $pdo->prepare('SELECT avatar_file FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$file = $stmt->fetchColumn();

$path = is_string($file) && preg_match('/^\d+\.(jpg|png)$/', $file) ? AVATAR_DIR . '/' . $file : null;

if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . (str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
