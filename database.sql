CREATE DATABASE IF NOT EXISTS stock_contributor;
USE stock_contributor;

CREATE TABLE IF NOT EXISTS uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    keywords TEXT NOT NULL,
    upload_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 