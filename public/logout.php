<?php
session_start();

$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

header('Location: login.php?logged_out=1');
exit;