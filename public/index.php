<?php
// Entry point: signed-in users go to the dashboard, everyone else to login.
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
} else {
    header('Location: dashboard.php');
}
exit;