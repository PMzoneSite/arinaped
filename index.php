<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];

if ($role == 'client') {
    header('Location: profile.php');
} elseif ($role == 'master') {
    header('Location: master_panel.php');
} elseif ($role == 'admin') {
    header('Location: admin_panel.php');
} else {
    header('Location: login.php');
}
exit;
?>