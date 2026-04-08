<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'master') {
    header('Location: login.php');
    exit;
}

$master_id = $_SESSION['user_id'];
$master = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$master->execute([$master_id]);
$master = $master->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $order_id = $_POST['order_id'];
    if ($_POST['action'] == 'accept') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'in_work' WHERE id = ? AND master_id = ? AND status = 'approved'");
        $stmt->execute([$order_id, $master_id]);
        $success = "✅ Заявка принята! Свяжитесь с клиентом.";
    } elseif ($_POST['action'] == 'reject') {
        $reason = $_POST['reason'];
        if (mb_len($reason) >= 10 && mb_len($reason) <= 500) {
            $order = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND master_id = ? AND status = 'approved'");
            $order->execute([$order_id, $master_id]);
            $ord = $order->fetch();
            if ($ord) {
                $stmt = $pdo->prepare("INSERT INTO Rejections (order_id, reason) VALUES (?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)");
                $stmt->execute([$order_id, $reason]);
                $upd = $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?");
                $upd->execute([$order_id]);
                dec_master_active_cnt($pdo, $master_id);
                $success = "❌ Заявка отклонена. Причина сохранена.";
            }
        } else {
            $error = "Причина отказа должна быть от 10 до 500 символов";
        }
    } elseif ($_POST['action'] == 'done') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'done' WHERE id = ? AND master_id = ? AND status = 'in_work'");
        $stmt->execute([$order_id, $master_id]);
        if ($stmt->rowCount() === 1) {
            dec_master_active_cnt($pdo, $master_id);
        }
        $success = "✅ Заявка отмечена как выполненная.";
    }
    header('Location: master_panel.php');
    exit;
}

$active_orders = $pdo->prepare("SELECT o.*, a.street, a.house, a.apt, a.entrance, a.floor, a.city, u.phone, u.full_name as client_name
FROM orders o
JOIN Addresses a ON o.address_id = a.id
JOIN Users u ON o.client_id = u.id
WHERE o.master_id = ? AND o.status = 'approved'
ORDER BY o.created_at ASC");
$active_orders->execute([$master_id]);
$active_orders = $active_orders->fetchAll();

$inactive_orders = $pdo->prepare("SELECT o.*, a.street, a.house, a.apt, a.city, u.phone, u.full_name as client_name
FROM orders o
JOIN Addresses a ON o.address_id = a.id
JOIN Users u ON o.client_id = u.id
WHERE o.master_id = ? AND o.status = 'in_work'
ORDER BY o.created_at DESC");
$inactive_orders->execute([$master_id]);
$inactive_orders = $inactive_orders->fetchAll();

$history = $pdo->prepare("SELECT o.*, a.street, a.house, a.apt, a.city, u.full_name as client_name
FROM orders o
JOIN Addresses a ON o.address_id = a.id
JOIN Users u ON o.client_id = u.id
WHERE o.master_id = ? AND o.status IN ('done','rejected')
ORDER BY o.created_at DESC LIMIT 20");
$history->execute([$master_id]);
$history = $history->fetchAll();

$completedStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE master_id = ? AND status = 'done'");
$completedStmt->execute([$master_id]);
$completedCount = (int)$completedStmt->fetchColumn();

$stats = [
    'active' => count($active_orders),
    'inactive' => count($inactive_orders),
    'completed' => $completedCount
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Кабинет мастера | Сервис заявок</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <div class="user-info">
                <span class="user-name">🔧 <?= htmlspecialchars($master['full_name']) ?></span>
                <span>📱 <?= htmlspecialchars($master['phone']) ?></span>
                <span>⚡ <?= $master['spec'] == 'plumber' ? 'Сантехник' : 'Электрик' ?></span>
            </div>
            <div class="nav-links">
                <a href="logout.php" class="nav-link">🚪 Выйти</a>
            </div>
        </div>
        <div class="content">
            <h2>👨‍🔧 Панель мастера</h2>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active'] ?></div>
                    <div class="stat-label">Активных заявок</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['inactive'] ?></div>
                    <div class="stat-label">В обработке</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['completed'] ?></div>
                    <div class="stat-label">Выполнено всего</div>
                </div>
            </div>

            <?php if (!empty($active_orders)): ?>
                <h3>🆕 Новые заявки</h3>
                <div class="grid-2">
                    <?php foreach ($active_orders as $order): ?>
                        <div class="order-card">
                            <h4>📋 Заявка #<?= $order['id'] ?></h4>
                            <p><span class="label">👤 Клиент:</span> <?= htmlspecialchars($order['client_name']) ?></p>
                            <p><span class="label">📱 Телефон:</span> <a href="tel:<?= $order['phone'] ?>"><?= $order['phone'] ?></a></p>
                            <p><span class="label">📍 Адрес:</span> <?= htmlspecialchars($order['city'] . ', ' . $order['street'] . ', ' . $order['house'] . ($order['apt'] ? ', кв.' . $order['apt'] : '') . ($order['entrance'] ? ', под.' . $order['entrance'] : '') . ($order['floor'] ? ', эт.' . $order['floor'] : '')) ?></p>
                            <p><span class="label">📝 Описание:</span> <?= htmlspecialchars($order['description']) ?></p>
                            <p><span class="label">📅 Создана:</span> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="action" value="accept" class="btn btn-success" style="width: 100%;" onclick="return confirm('Принять заявку?')">✅ Принять</button>
                                </form>
                                <form method="POST" style="flex: 1;" onsubmit="return false;">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="text" name="reason" id="reason_<?= $order['id'] ?>" style="display:none;">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="button" class="btn btn-danger" style="width: 100%;" onclick="var r=prompt('Укажите причину отказа (10-500 символов):'); if(r && r.length>=10 && r.length<=500){ document.getElementById('reason_<?= $order['id'] ?>').value=r; this.form.submit();} else if(r){ alert('Причина должна быть от 10 до 500 символов'); }">❌ Отклонить</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">✨ Нет новых заявок. Отдыхайте!</div>
            <?php endif; ?>

            <?php if (!empty($inactive_orders)): ?>
                <h3>🔄 Заявки в работе</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Клиент</th><th>Телефон</th><th>Адрес</th><th>Описание</th><th>Дата</th><th>Действие</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inactive_orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['client_name']) ?></td>
                                    <td><a href="tel:<?= $order['phone'] ?>"><?= $order['phone'] ?></a></td>
                                    <td><?= htmlspecialchars($order['city'] . ', ' . $order['street'] . ', ' . $order['house']) ?></td>
                                    <td><?= htmlspecialchars(mb_substr($order['description'], 0, 50)) ?>...</td>
                                    <td><?= date('d.m.Y', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" name="action" value="done" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Отметить как выполненную?')">✅ Выполнено</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($history)): ?>
                <h3>📜 История выполненных заявок</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Клиент</th><th>Адрес</th><th>Описание</th><th>Дата</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['client_name']) ?></td>
                                    <td><?= htmlspecialchars($order['city'] . ', ' . $order['street'] . ', ' . $order['house']) ?></td>
                                    <td><?= htmlspecialchars(mb_substr($order['description'], 0, 50)) ?>...</td>
                                    <td><?= date('d.m.Y', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>