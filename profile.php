<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Получаем данные пользователя
$user = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_address'])) {
    $street = $_POST['street'];
    $house = $_POST['house'];
    $apartment = $_POST['apartment'];
    $entrance = $_POST['entrance'];
    $floor = $_POST['floor'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $postcode = $_POST['postcode'];
    $country = $_POST['country'];
    
    $stmt = $pdo->prepare("INSERT INTO Addresses (street, house, apartment, entrance, floor, city, state, postcode, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$street, $house, $apartment, $entrance, $floor, $city, $state, $postcode, $country]);
    header("Location: profile.php");
    exit;
}

if (isset($_GET['del_addr'])) {
    $pdo->prepare("DELETE FROM Addresses WHERE id = ?")->execute([$_GET['del_addr']]);
    header("Location: profile.php");
    exit;
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';
if ($filter == 'active') $status_cond = "status IN ('active', 'inactive', 'approved')";
elseif ($filter == 'closed') $status_cond = "status = 'closed'";
else $status_cond = "1";

$orders = $pdo->prepare("SELECT o.*, a.street, a.house, a.apartment, a.city FROM orders o LEFT JOIN Addresses a ON o.address_id = a.id WHERE o.client_id = ? AND $status_cond ORDER BY o.created_at DESC");
$orders->execute([$user_id]);
$orders = $orders->fetchAll();

$addresses = $pdo->query("SELECT * FROM Addresses")->fetchAll();

$active_counts = $pdo->prepare("SELECT spec, COUNT(*) as cnt FROM orders WHERE client_id = ? AND status IN ('active', 'inactive', 'approved') GROUP BY spec");
$active_counts->execute([$user_id]);
$active_map = [];
foreach ($active_counts->fetchAll() as $row) $active_map[$row['spec']] = $row['cnt'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Личный кабинет | Сервис заявок</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="nav-bar">
            <div class="user-info">
                <span class="user-name">👤 <?= htmlspecialchars($user['full_name']) ?></span>
                <span>📱 <?= htmlspecialchars($user['phone']) ?></span>
            </div>
            <div class="nav-links">
                <a href="profile.php" class="nav-link">🏠 Главная</a>
                <a href="create_order.php" class="nav-link">➕ Создать заявку</a>
                <a href="logout.php" class="nav-link">🚪 Выйти</a>
            </div>
        </div>
        <div class="content">
            <h2>Мои заявки</h2>
            
            <div class="filters">
                <a href="?filter=active" class="filter-btn <?= $filter == 'active' ? 'active' : '' ?>">В работе</a>
                <a href="?filter=closed" class="filter-btn <?= $filter == 'closed' ? 'active' : '' ?>">Выполнены</a>
                <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">Все</a>
            </div>

            <?php if (empty($orders)): ?>
                <div class="alert alert-info">У вас пока нет заявок. Создайте первую заявку!</div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Специальность</th><th>Адрес</th><th>Описание</th><th>Статус</th><th>Дата</th><th>Действие</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['spec'] == 'plumber' ? '🔧 Сантехник' : '⚡ Электрик' ?></td>
                                <td><?= htmlspecialchars($order['city'] . ', ' . $order['street'] . ', ' . $order['house'] . ($order['apartment'] ? ', кв.' . $order['apartment'] : '')) ?></td>
                                <td><?= htmlspecialchars(mb_substr($order['description'], 0, 50)) ?>...</td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($order['status']) {
                                        case 'active': $status_class = 'status-active'; $status_text = '🟡 Активна'; break;
                                        case 'inactive': $status_class = 'status-inactive'; $status_text = '🟠 В обработке'; break;
                                        case 'closed': $status_class = 'status-closed'; $status_text = '✅ Выполнена'; break;
                                        case 'approved': $status_class = 'status-approved'; $status_text = '🟢 Одобрена'; break;
                                        default: $status_class = ''; $status_text = $order['status'];
                                    }
                                    ?>
                                    <span class="status <?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <?php if ($order['status'] == 'active' || $order['status'] == 'approved'): ?>
                                        <a href="cancel_order.php?id=<?= $order['id'] ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Отменить заявку?')">Отменить</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3>📍 Мои адреса</h3>
            <div class="grid-2">
                <?php foreach ($addresses as $addr): ?>
                    <div class="address-card">
                        <div>
                            <strong><?= htmlspecialchars($addr['city']) ?></strong><br>
                            <?= htmlspecialchars($addr['street']) ?>, <?= htmlspecialchars($addr['house']) ?>
                            <?php if ($addr['apartment']): ?>, кв. <?= htmlspecialchars($addr['apartment']) ?><?php endif; ?>
                            <?php if ($addr['entrance']): ?>, под. <?= htmlspecialchars($addr['entrance']) ?><?php endif; ?>
                            <?php if ($addr['floor']): ?>, эт. <?= htmlspecialchars($addr['floor']) ?><?php endif; ?>
                        </div>
                        <a href="?del_addr=<?= $addr['id'] ?>" class="btn btn-danger" style="padding: 5px 10px;" onclick="return confirm('Удалить адрес?')">Удалить</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3>➕ Добавить новый адрес</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group"><input type="text" name="city" placeholder="Город *" required></div>
                    <div class="form-group"><input type="text" name="street" placeholder="Улица *" required></div>
                    <div class="form-group"><input type="text" name="house" placeholder="Дом *" required></div>
                    <div class="form-group"><input type="text" name="apartment" placeholder="Квартира"></div>
                    <div class="form-group"><input type="text" name="entrance" placeholder="Подъезд"></div>
                    <div class="form-group"><input type="text" name="floor" placeholder="Этаж"></div>
                    <div class="form-group"><input type="text" name="state" placeholder="Область"></div>
                    <div class="form-group"><input type="text" name="postcode" placeholder="Индекс"></div>
                    <div class="form-group"><input type="text" name="country" placeholder="Страна" value="Россия"></div>
                </div>
                <button type="submit" name="add_address" class="btn btn-primary">Добавить адрес</button>
            </form>
        </div>
    </div>
</body>
</html>