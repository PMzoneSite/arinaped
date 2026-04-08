<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = normalize_phone($_POST['phone'] ?? '');
    $full_name = $_POST['full_name'];
    $password = $_POST['password'];
    $role = 'client';
    $spec = null;

    if (strlen($password) < 6) {
        $error = "Пароль должен быть не менее 6 символов";
    } elseif (!preg_match('/^\+7[0-9]{10}$/', $phone)) {
        $error = "Телефон должен быть в формате +7XXXXXXXXXX";
    } else {
        $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO Users (phone, full_name, pwd_hash, role, spec, active_cnt) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$phone, $full_name, $pwd_hash, $role, $spec]);
            header('Location: login.php?registered=1');
            exit;
        } catch(PDOException $e) {
            $error = "Ошибка: телефон уже существует";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Регистрация | Сервис заявок</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏠 Сервис заявок</h1>
            <p>Регистрация нового пользователя</p>
        </div>
        <div class="content">
            <div style="max-width: 500px; margin: 0 auto;">
                <h2>Регистрация</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>👤 ФИО</label>
                        <input type="text" name="full_name" placeholder="Иванов Иван Иванович" required>
                    </div>
                    <div class="form-group">
                        <label>📱 Телефон</label>
                        <input type="tel" name="phone" placeholder="+7XXXXXXXXXX" required>
                    </div>
                    <div class="form-group">
                        <label>🔒 Пароль</label>
                        <input type="password" name="password" placeholder="Не менее 6 символов" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Зарегистрироваться</button>
                </form>
                <div style="text-align: center; margin-top: 20px;">
                    <p>Уже есть аккаунт? <a href="login.php" style="color: #667eea;">Войти</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>