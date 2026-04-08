<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['pwd_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        header('Location: index.php');
        exit;
    } else {
        $error = "Неверный телефон или пароль";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Вход | Сервис заявок</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏠 Сервис заявок</h1>
            <p>Сантехника и электрика - быстро и качественно</p>
        </div>
        <div class="content">
            <div style="max-width: 400px; margin: 0 auto;">
                <h2>Вход в систему</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>📱 Номер телефона</label>
                        <input type="tel" name="phone" placeholder="+7XXXXXXXXXX" required value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>🔒 Пароль</label>
                        <input type="password" name="password" placeholder="Введите пароль" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Войти</button>
                </form>
                <div style="text-align: center; margin-top: 20px;">
                    <p>Нет аккаунта? <a href="register.php" style="color: #667eea;">Зарегистрироваться</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>