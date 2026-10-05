<?php
session_start();
require __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

//trim removed accidental spaces from username
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare(
    'SELECT id, password_hash, display_name FROM users WHERE username = ?'
);
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['last_activity'] = time();
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php?error=1');
exit;
