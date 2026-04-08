CREATE DATABASE service_db;
USE service_db;

CREATE TABLE Users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(15) UNIQUE NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    pwd_hash VARCHAR(255) NOT NULL,
    role ENUM('client', 'master', 'admin') NOT NULL,
    spec ENUM('plumber', 'electrician') DEFAULT NULL,
    active_cnt TINYINT(3) UNSIGNED NOT NULL DEFAULT 0
);

CREATE TABLE Addresses (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    city VARCHAR(60) NOT NULL,
    street VARCHAR(120) NOT NULL,
    house VARCHAR(20) NOT NULL,
    apt VARCHAR(20) DEFAULT NULL,
    entrance VARCHAR(20) DEFAULT NULL,
    floor VARCHAR(20) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    client_id INT(11) NOT NULL,
    address_id INT(11) NOT NULL,
    master_id INT(11) DEFAULT NULL,
    spec ENUM('plumber', 'electrician') NOT NULL,
    description VARCHAR(1000) NOT NULL,
    status ENUM('new','approved','in_work','done','rejected','cancelled') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES Users(id),
    FOREIGN KEY (address_id) REFERENCES Addresses(id),
    FOREIGN KEY (master_id) REFERENCES Users(id)
);

CREATE TABLE Rejections (
    order_id INT(11) PRIMARY KEY,
    reason VARCHAR(500) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

INSERT INTO Users (phone, full_name, pwd_hash, role) VALUES ('+79990000001', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- пароль для admin: password