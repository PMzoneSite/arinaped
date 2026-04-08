<?php
$host = 'localhost';
$dbname = 'service_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

function normalize_phone(string $phone): string {
    $phone = trim($phone);
    $phone = preg_replace('/\s+/', '', $phone);
    return $phone;
}

function mb_len(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function ensure_default_admin(PDO $pdo): void {
    try {
        $cnt = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'admin'")->fetchColumn();
        if ((int)$cnt > 0) return;

        $phone = '+79990000001';
        $full_name = 'Администратор';
        $password = 'admin123';
        $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO Users (phone, full_name, pwd_hash, role, active_cnt) VALUES (?, ?, ?, 'admin', 0)");
        $stmt->execute([$phone, $full_name, $pwd_hash]);
    } catch (Throwable $e) {
        // Если таблиц еще нет (не импортировали БД) — просто пропускаем
    }
}

ensure_default_admin($pdo);

function assign_master_for_order(PDO $pdo, int $orderId): bool {
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, spec, status, master_id FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            if ($ownTx) $pdo->rollBack();
            return false;
        }
        if ($order['master_id'] !== null) {
            if ($ownTx) $pdo->commit();
            return true;
        }
        // По ТЗ назначение мастера происходит после статуса approved
        if ($order['status'] !== 'approved') {
            if ($ownTx) $pdo->commit();
            return false;
        }

        // По ТЗ: нет активных заявок > 3, т.е. active_cnt <= 3
        $m = $pdo->prepare("SELECT id FROM Users WHERE role = 'master' AND spec = ? AND active_cnt < 4 ORDER BY active_cnt ASC, id ASC LIMIT 1 FOR UPDATE");
        $m->execute([$order['spec']]);
        $masterId = $m->fetchColumn();
        if (!$masterId) {
            if ($ownTx) $pdo->commit();
            return false;
        }

        $u = $pdo->prepare("UPDATE orders SET master_id = ?, status = 'approved' WHERE id = ? AND master_id IS NULL");
        $u->execute([$masterId, $orderId]);
        if ($u->rowCount() === 1) {
            $pdo->prepare("UPDATE Users SET active_cnt = active_cnt + 1 WHERE id = ?")->execute([$masterId]);
        }
        if ($ownTx) $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function dec_master_active_cnt(PDO $pdo, int $masterId): void {
    $pdo->prepare("UPDATE Users SET active_cnt = IF(active_cnt > 0, active_cnt - 1, 0) WHERE id = ?")->execute([$masterId]);
}
?>