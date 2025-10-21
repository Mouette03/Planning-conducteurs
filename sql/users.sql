CREATE TABLE IF NOT EXISTS pc_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    nom VARCHAR(100),
    email VARCHAR(255),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    dernier_login DATETIME,
    actif BOOLEAN DEFAULT TRUE
);