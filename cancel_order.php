<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: profile.php');
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT id, status, master_id FROM orders WHERE id = ? AND client_id = ? FOR UPDATE");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        $pdo->rollBack();
        header('Location: profile.php');
        exit;
    }

    if (!in_array($order['status'], ['new','approved','in_work'], true)) {
        $pdo->rollBack();
        header('Location: profile.php');
        exit;
    }

    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$order_id]);
    if (!empty($order['master_id']) && in_array($order['status'], ['approved','in_work'], true)) {
        dec_master_active_cnt($pdo, (int)$order['master_id']);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

header('Location: profile.php');
exit;
?>
