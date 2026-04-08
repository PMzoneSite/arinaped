CREATE DATABASE service_db;
USE service_db;

CREATE TABLE Users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(15) UNIQUE NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    pwd_hash VARCHAR(255) NOT NULL,
    role ENUM('client', 'master', 'admin') NOT NULL,
    spec ENUM('plumber', 'electrician') DEFAULT NULL,
    active_cnt TINYINT(3) DEFAULT 0
);

CREATE TABLE Addresses (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    street VARCHAR(50) NOT NULL,
    house VARCHAR(100) NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) DEFAULT NULL,
    postcode VARCHAR(50) DEFAULT NULL,
    country VARCHAR(50) DEFAULT NULL
);

CREATE TABLE orders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    client_id INT(11) NOT NULL,
    address_id INT(11) NOT NULL,
    master_id INT(11) DEFAULT NULL,
    spec ENUM('plumber', 'electrician') NOT NULL,
    description VARCHAR(1000) NOT NULL,
    status ENUM('active', 'inactive', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES Users(id),
    FOREIGN KEY (address_id) REFERENCES Addresses(id),
    FOREIGN KEY (master_id) REFERENCES Users(id)
);

CREATE TABLE Rejections (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    client_id INT(11) NOT NULL,
    address_id INT(11) NOT NULL,
    master_id INT(11) NOT NULL,
    spec ENUM('plumber', 'electrician') NOT NULL,
    description VARCHAR(1000) NOT NULL,
    status ENUM('active', 'inactive', 'closed') DEFAULT 'closed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO Users (phone, full_name, pwd_hash, role) VALUES ('+79990000001', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- пароль для admin: password