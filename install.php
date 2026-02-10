<?php
// Database installation script
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'media_site';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        avatar VARCHAR(255) DEFAULT NULL,
        role ENUM('user','admin') DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tags table (admin-defined)
    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Posts (articles/images/videos)
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) DEFAULT '',
        body TEXT DEFAULT '',
        type ENUM('image','video','article') DEFAULT 'image',
        media_url VARCHAR(500) DEFAULT NULL,
        thumbnail_url VARCHAR(500) DEFAULT NULL,
        views INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_type (type),
        INDEX idx_views (views DESC),
        INDEX idx_created (created_at DESC)
    )");

    // Post-tag relationship
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_tags (
        post_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (post_id, tag_id),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )");

    // Chat messages
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        media_url VARCHAR(500) DEFAULT NULL,
        post_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL,
        INDEX idx_created (created_at DESC)
    )");

    // Create default admin user (password: admin123)
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, display_name, role) VALUES ('admin', ?, 'Admin', 'admin')");
    $stmt->execute([$admin_pass]);

    // Create demo user (password: user123)
    $user_pass = password_hash('user123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, display_name, role) VALUES ('demo', ?, 'Demo User', 'user')");
    $stmt->execute([$user_pass]);

    // Create default tags
    $default_tags = [
        ['חדשות', 'news'], ['ספורט', 'sport'], ['טכנולוגיה', 'tech'],
        ['בידור', 'entertainment'], ['מוזיקה', 'music'], ['אוכל', 'food'],
        ['נסיעות', 'travel'], ['אופנה', 'fashion']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name, slug) VALUES (?, ?)");
    foreach ($default_tags as $tag) {
        $stmt->execute($tag);
    }

    echo "<h1>Installation successful!</h1>";
    echo "<p>Database '$dbname' created with all tables.</p>";
    echo "<p>Default admin: admin / admin123</p>";
    echo "<p>Default user: demo / user123</p>";
    echo "<p><a href='index.php'>Go to site</a></p>";

} catch (PDOException $e) {
    die("Installation failed: " . $e->getMessage());
}
