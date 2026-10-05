<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?expired=1');
    exit;
}

if (time() - ($_SESSION['last_activity'] ?? 0) > 600) {
    header('Location: logout.php');
    exit;
}
$_SESSION['last_activity'] = time();