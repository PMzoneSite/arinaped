<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$admin = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$admin->execute([$_SESSION['user_id']]);
$admin = $admin->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_master'])) {
    $phone = normalize_phone($_POST['phone'] ?? '');
    $full_name = $_POST['full_name'];
    $password = $_POST['password'];
    $spec = $_POST['spec'];
    if (strlen($password) >= 6 && preg_match('/^\+7[0-9]{10}$/', $phone)) {
        $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO Users (phone, full_name, pwd_hash, role, spec, active_cnt) VALUES (?, ?, ?, 'master', ?, 0)");
            $stmt->execute([$phone, $full_name, $pwd_hash, $spec]);
            $success = "✅ Мастер успешно добавлен";
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $error = "❌ Пользователь с таким телефоном уже существует";
            } else {
                throw $e;
            }
        }
    } else {
        $error = "❌ Неверный формат телефона или пароль слишком короткий";
    }
    header('Location: admin_panel.php');
    exit;
}

if (isset($_GET['del_master'])) {
    $mid = $_GET['del_master'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE master_id = ? AND status IN ('approved','in_work')");
    $check->execute([$mid]);
    if ($check->fetchColumn() == 0) {
        $pdo->prepare("DELETE FROM Users WHERE id = ? AND role='master'")->execute([$mid]);
        $success = "✅ Мастер удален";
    } else {
        $error = "❌ Нельзя удалить мастера с активными заявками";
    }
    header('Location: admin_panel.php');
    exit;
}

if (isset($_POST['update_order_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare("SELECT id, status, spec, master_id FROM orders WHERE id = ? FOR UPDATE");
        $o->execute([$order_id]);
        $order = $o->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            $pdo->rollBack();
            header('Location: admin_panel.php');
            exit;
        }

            if (is_string($new_status) && substr($new_status, 0, 7) === 'assign_') {
            $masterId = (int)($_POST['master_id'] ?? explode('_', $new_status, 2)[1] ?? 0);
            $m = $pdo->prepare("SELECT id FROM Users WHERE id = ? AND role='master' AND spec = ? AND active_cnt < 4 FOR UPDATE");
            $m->execute([$masterId, $order['spec']]);
            $okMaster = $m->fetchColumn();
            if ($okMaster && $order['master_id'] === null && in_array($order['status'], ['approved','new'], true)) {
                $upd = $pdo->prepare("UPDATE orders SET master_id = ?, status = 'approved' WHERE id = ? AND master_id IS NULL");
                $upd->execute([$masterId, $order_id]);
                if ($upd->rowCount() === 1) {
                    $pdo->prepare("UPDATE Users SET active_cnt = active_cnt + 1 WHERE id = ?")->execute([$masterId]);
                }
                $success = "✅ Мастер назначен";
            }
        } else {
            $activeBefore = in_array($order['status'], ['approved','in_work'], true);
            $activeAfter = in_array($new_status, ['approved','in_work'], true);

            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);

            if ($activeBefore && !$activeAfter && !empty($order['master_id'])) {
                dec_master_active_cnt($pdo, (int)$order['master_id']);
            }
            if ($new_status === 'approved' && empty($order['master_id'])) {
                // По ТЗ: назначаем мастера после approved
                assign_master_for_order($pdo, (int)$order_id);
            }
            $success = "✅ Статус заявки обновлен";
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    header('Location: admin_panel.php');
    exit;
}

$all_orders = $pdo->query("SELECT o.*, a.city, a.street, a.house, a.apt, u.full_name as client_name, m.full_name as master_name
FROM orders o
LEFT JOIN Addresses a ON o.address_id = a.id
LEFT JOIN Users u ON o.client_id = u.id
LEFT JOIN Users m ON o.master_id = m.id
ORDER BY o.created_at DESC")->fetchAll();

$all_users = $pdo->query("SELECT id, phone, full_name, role, spec, active_cnt FROM Users ORDER BY id")->fetchAll();

$masters = $pdo->query("SELECT * FROM Users WHERE role='master' ORDER BY id")->fetchAll();

$stats = [
    'total_orders' => count($all_orders),
    'active_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('new','approved','in_work')")->fetchColumn(),
    'total_users' => count($all_users),
    'total_masters' => count($masters)
];

$pending_orders = $pdo->query("SELECT o.*, u.full_name as client_name, a.city, a.street, a.house
FROM orders o
JOIN Users u ON o.client_id = u.id
JOIN Addresses a ON o.address_id = a.id
WHERE o.status IN ('new','approved') AND o.master_id IS NULL
ORDER BY o.created_at ASC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Админ-панель | Сервис заявок</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <div class="user-info">
                <span class="user-name">👑 <?= htmlspecialchars($admin['full_name']) ?></span>
                <span>📱 <?= htmlspecialchars($admin['phone']) ?></span>
                <span>🔐 Администратор</span>
            </div>
            <div class="nav-links">
                <a href="logout.php" class="nav-link">🚪 Выйти</a>
            </div>
        </div>
        <div class="content">
            <h2>👨‍💼 Панель управления</h2>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_orders'] ?></div>
                    <div class="stat-label">Всего заявок</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active_orders'] ?></div>
                    <div class="stat-label">Активных заявок</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_users'] ?></div>
                    <div class="stat-label">Пользователей</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_masters'] ?></div>
                    <div class="stat-label">Мастеров</div>
                </div>
            </div>

            <?php if (!empty($pending_orders)): ?>
                <h3>⏳ Заявки ожидающие назначения мастера</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Клиент</th><th>Спец.</th><th>Адрес</th><th>Описание</th><th>Действие</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_orders as $order): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['client_name']) ?></td>
                                <td><?= $order['spec'] == 'plumber' ? '🔧 Сантехник' : '⚡ Электрик' ?></td>
                                <td><?= htmlspecialchars($order['city'] . ', ' . $order['street'] . ', ' . $order['house']) ?></td>
                                <td><?= htmlspecialchars(mb_substr($order['description'], 0, 40)) ?>...</td>
                                <td>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <select name="new_status" style="padding: 5px;">
                                            <option value="approved">📋 Авто-назначение</option>
                                            <?php foreach ($masters as $master): ?>
                                                <?php if ($master['spec'] == $order['spec']): ?>
                                                    <option value="assign_<?= $master['id'] ?>">👨‍🔧 Назначить: <?= htmlspecialchars($master['full_name']) ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="update_order_status" value="1">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3>📋 Все заявки</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Клиент</th><th>Мастер</th><th>Спец.</th><th>Адрес</th><th>Статус</th><th>Дата</th><th>Действие</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_orders as $order): ?>
                        <tr>
                            <td>#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['client_name']) ?></td>
                            <td><?= htmlspecialchars($order['master_name'] ?? 'Не назначен') ?></td>
                            <td><?= $order['spec'] == 'plumber' ? '🔧' : '⚡' ?></td>
                            <td><?= htmlspecialchars($order['city'] ?? '') ?></td>
                            <td>
                                <?php
                                $status_badge = '';
                                switch($order['status']) {
                                    case 'new': $status_badge = '<span class="status status-inactive">🟠 Новая</span>'; break;
                                    case 'approved': $status_badge = '<span class="status status-approved">🟢 Назначен мастер</span>'; break;
                                    case 'in_work': $status_badge = '<span class="status status-active">🟡 В работе</span>'; break;
                                    case 'done': $status_badge = '<span class="status status-closed">✅ Выполнена</span>'; break;
                                    case 'rejected': $status_badge = '<span class="status status-closed">❌ Отклонена</span>'; break;
                                    case 'cancelled': $status_badge = '<span class="status status-closed">🚫 Отменена</span>'; break;
                                    default: $status_badge = $order['status'];
                                }
                                echo $status_badge;
                                ?>
                            </td>
                            <td><?= date('d.m.Y', strtotime($order['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <select name="new_status" style="padding: 5px; font-size: 12px;">
                                        <option value="">Изменить статус</option>
                                        <option value="new">🟠 Новая</option>
                                        <option value="approved">🟢 Одобрена (назначить мастера)</option>
                                        <option value="in_work">🟡 В работе</option>
                                        <option value="done">✅ Выполнена</option>
                                        <option value="rejected">❌ Отклонена</option>
                                        <option value="cancelled">🚫 Отменена</option>
                                    </select>
                                    <input type="hidden" name="update_order_status" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3>👥 Все пользователи</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Телефон</th><th>ФИО</th><th>Роль</th><th>Спец.</th><th>Активных заявок</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= $user['phone'] ?></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= $user['role'] == 'admin' ? '👑 Админ' : ($user['role'] == 'master' ? '🔧 Мастер' : '👤 Клиент') ?></td>
                            <td><?= $user['spec'] == 'plumber' ? 'Сантехник' : ($user['spec'] == 'electrician' ? 'Электрик' : '-') ?></td>
                            <td><?= $user['active_cnt'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3>🔧 Управление мастерами</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Имя</th><th>Телефон</th><th>Спец.</th><th>Активных заявок</th><th>Действие</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($masters as $master): ?>
                        <tr>
                            <td><?= $master['id'] ?></td>
                            <td><?= htmlspecialchars($master['full_name']) ?></td>
                            <td><?= $master['phone'] ?></td>
                            <td><?= $master['spec'] == 'plumber' ? '🔧 Сантехник' : '⚡ Электрик' ?></td>
                            <td><?= $master['active_cnt'] ?></td>
                            <td>
                                <a href="?del_master=<?= $master['id'] ?>" class="btn btn-danger" style="padding: 5px 10px;" onclick="return confirm('Удалить мастера?')">🗑️ Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3>➕ Добавить нового мастера</h3>
            <form method="POST" style="max-width: 500px;">
                <div class="form-group">
                    <input type="text" name="full_name" placeholder="ФИО мастера" required>
                </div>
                <div class="form-group">
                    <input type="tel" name="phone" placeholder="+7XXXXXXXXXX" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Пароль (мин. 6 символов)" required>
                </div>
                <div class="form-group">
                    <select name="spec" required>
                        <option value="plumber">🔧 Сантехник</option>
                        <option value="electrician">⚡ Электрик</option>
                    </select>
                </div>
                <button type="submit" name="add_master" class="btn btn-primary">➕ Добавить мастера</button>
            </form>
        </div>
    </div>

    <script>
    // Автоматическое назначение мастера при выборе
    document.querySelectorAll('select[name="new_status"]').forEach(select => {
        select.addEventListener('change', function() {
            if (this.value.startsWith('assign_')) {
                let masterId = this.value.split('_')[1];
                let orderId = this.closest('form').querySelector('input[name="order_id"]').value;
                if (confirm('Назначить этого мастера на заявку?')) {
                    let form = this.closest('form');
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'master_id';
                    input.value = masterId;
                    form.appendChild(input);
                    form.submit();
                } else {
                    this.value = '';
                }
            } else if (this.value) {
                this.closest('form').submit();
            }
        });
    });
    </script>
</body>
</html>