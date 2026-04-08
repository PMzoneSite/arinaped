<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

$addresses = $pdo->prepare("SELECT * FROM Addresses WHERE user_id = ? ORDER BY id DESC");
$addresses->execute([$user_id]);
$addresses = $addresses->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $address_id = (int)($_POST['address_id'] ?? 0);
    $spec = $_POST['spec'];
    $description = $_POST['description'];

    if (mb_len($description) < 10 || mb_len($description) > 1000) {
        $error = "Описание должно быть от 10 до 1000 символов";
    } else {
        $ownAddr = $pdo->prepare("SELECT COUNT(*) FROM Addresses WHERE id = ? AND user_id = ?");
        $ownAddr->execute([$address_id, $user_id]);
        if ($ownAddr->fetchColumn() == 0) {
            $error = "Выберите корректный адрес из списка";
        } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ? AND spec = ? AND status IN ('new','approved','in_work')");
        $check->execute([$user_id, $spec]);
        if ($check->fetchColumn() > 0) {
            $error = "У вас уже есть активная заявка по этой специальности";
        } else {
            // По ТЗ: после создания заявка новая, затем админ одобряет (approved) и система назначает мастера
            $stmt = $pdo->prepare("INSERT INTO orders (client_id, address_id, spec, description, status, created_at) VALUES (?, ?, ?, ?, 'new', NOW())");
            $stmt->execute([$user_id, $address_id, $spec, $description]);
            header('Location: profile.php?success=1');
            exit;
        }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Создать заявку | Сервис заявок</title>
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
                <a href="profile.php" class="nav-link">🏠 Личный кабинет</a>
                <a href="logout.php" class="nav-link">🚪 Выйти</a>
            </div>
        </div>
        <div class="content">
            <div style="max-width: 700px; margin: 0 auto;">
                <h2>➕ Создание новой заявки</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>📍 Выберите адрес</label>
                        <select name="address_id" required>
                            <option value="">-- Выберите адрес --</option>
                            <?php foreach ($addresses as $addr): ?>
                                <option value="<?= $addr['id'] ?>">
                                    <?= htmlspecialchars($addr['city'] . ', ' . $addr['street'] . ', ' . $addr['house'] . ($addr['apt'] ? ', кв.' . $addr['apt'] : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>🔧 Выберите специальность</label>
                        <select name="spec" required>
                            <option value="plumber">🔧 Сантехник</option>
                            <option value="electrician">⚡ Электрик</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>📝 Описание проблемы</label>
                        <textarea name="description" placeholder="Опишите проблему подробно. Например: протекает кран, не работает розетка, засорилась раковина..." required></textarea>
                        <small style="color: #6b7280; display: block; margin-top: 5px;">Минимум 10 символов, максимум 1000 символов</small>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">📤 Отправить заявку</button>
                        <a href="profile.php" class="btn btn-secondary" style="flex: 1; text-align: center;">❌ Отмена</a>
                    </div>
                </form>

                <div class="alert alert-info" style="margin-top: 30px;">
                    <strong>ℹ️ Информация:</strong><br>
                    • После отправки заявка будет рассмотрена администратором<br>
                    • Как только найдется свободный мастер, вы получите уведомление<br>
                    • Вы можете отменить заявку в любое время в личном кабинете
                </div>
            </div>
        </div>
    </div>
</body>
</html>