<?php
// Database setup script
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'db_feedspace';

// Connect without selecting database first
$conn = mysqli_connect($host, $user, $password);
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if (mysqli_query($conn, $sql)) {
    echo "Database created successfully or already exists.\n";
} else {
    die('Error creating database: ' . mysqli_error($conn));
}

// Select the database
mysqli_select_db($conn, $database);

// Execute each table creation and index creation separately
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        user_id VARCHAR(50) PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        bio TEXT,
        role ENUM('student', 'faculty', 'staff') NOT NULL,
        college VARCHAR(255) NOT NULL,
        profile_picture VARCHAR(255),
        cover_photo VARCHAR(255),
        is_verified BOOLEAN DEFAULT FALSE,
        otp_code VARCHAR(10),
        otp_expires TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS otp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        otp_code VARCHAR(10) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS posts (
        post_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        image_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS comments (
        comment_id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS likes (
        like_id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (post_id, user_id),
        FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS communities (
        community_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS community_members (
        member_id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_member (community_id, user_id),
        FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS shares (
        share_id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS reports (
        report_id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id VARCHAR(50) NOT NULL,
        reported_user_id VARCHAR(50),
        reported_post_id INT,
        reason TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (reported_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (reported_post_id) REFERENCES posts(post_id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        type ENUM('like', 'comment', 'share', 'follow') NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )"
];

$indexes = [
    "CREATE INDEX idx_posts_user_id ON posts(user_id)",
    "CREATE INDEX idx_posts_created_at ON posts(created_at)",
    "CREATE INDEX idx_comments_post_id ON comments(post_id)",
    "CREATE INDEX idx_comments_user_id ON comments(user_id)",
    "CREATE INDEX idx_likes_post_id ON likes(post_id)",
    "CREATE INDEX idx_likes_user_id ON likes(user_id)",
    "CREATE INDEX idx_communities_user_id ON communities(user_id)",
    "CREATE INDEX idx_notifications_user_id ON notifications(user_id)",
    "CREATE INDEX idx_reports_reporter_id ON reports(reporter_id)"
];

echo "Creating tables...\n";
foreach ($tables as $table) {
    if (mysqli_query($conn, $table)) {
        echo "Table created successfully.\n";
    } else {
        echo "Error creating table: " . mysqli_error($conn) . "\n";
    }
}

echo "Creating indexes...\n";
foreach ($indexes as $index) {
    try {
        if (mysqli_query($conn, $index)) {
            echo "Index created successfully.\n";
        } else {
            $error = mysqli_error($conn);
            if (strpos($error, 'Duplicate key name') !== false) {
                echo "Index already exists, skipping.\n";
            } else {
                echo "Error creating index: " . $error . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Index creation failed, but continuing...\n";
    }
}

echo "Database setup completed!\n";
mysqli_close($conn);
?>