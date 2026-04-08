<?php
require_once 'db.php';

// Данные администратора
$phone = '+79990000001';
$full_name = 'Администратор';
$password = 'admin123';

// Создаем хеш пароля
$pwd_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Удаляем старого если есть
    $pdo->prepare("DELETE FROM Users WHERE phone = ?")->execute([$phone]);
    
    // Создаем админа
    $stmt = $pdo->prepare("INSERT INTO Users (phone, full_name, pwd_hash, role, active_cnt) VALUES (?, ?, ?, 'admin', 0)");
    $stmt->execute([$phone, $full_name, $pwd_hash]);
    
    echo "✅ АДМИНИСТРАТОР СОЗДАН!<br>";
    echo "📱 Телефон: +79990000001<br>";
    echo "🔑 Пароль: admin123<br>";
    echo "<br><a href='login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 10px;'>🚀 Войти в систему</a>";
    
} catch(PDOException $e) {
    echo "❌ Ошибка: " . $e->getMessage();
}
?>