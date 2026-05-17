<?php
// Database setup script for FeedSpace
// Creates the application database schema according to the imported feedspace dump.

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
$sql = "CREATE DATABASE IF NOT EXISTS $database DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (mysqli_query($conn, $sql)) {
    echo "Database created successfully or already exists.\n";
} else {
    die('Error creating database: ' . mysqli_error($conn));
}

mysqli_select_db($conn, $database);

$tables = [
    "CREATE TABLE IF NOT EXISTS admin_accounts (
        admin_id INT(11) NOT NULL AUTO_INCREMENT,
        username VARCHAR(100) DEFAULT NULL,
        password_hash VARCHAR(255) NOT NULL,
        PRIMARY KEY (admin_id),
        UNIQUE KEY username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS users (
        user_id VARCHAR(9) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) DEFAULT NULL,
        profile_picture VARCHAR(255) DEFAULT 'default.png',
        bio TEXT DEFAULT NULL,
        role VARCHAR(50) DEFAULT 'Student',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active','inactive','banned') DEFAULT 'active',
        college VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (user_id),
        UNIQUE KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS otp (
        otp_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) NOT NULL,
        otp_code VARCHAR(10) NOT NULL,
        type ENUM('login','register','change_password') NOT NULL,
        expires_at DATETIME NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (otp_id),
        KEY otp_ibfk_1 (user_id),
        CONSTRAINT otp_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS communities (
        community_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) DEFAULT NULL,
        community_name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        member_count INT(11) DEFAULT 0,
        status ENUM('active','archived','suspended') DEFAULT 'active',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        community_picture VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (community_id),
        KEY user_id_idx (user_id),
        CONSTRAINT fk_communities_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS community_likes (
        like_id INT(11) NOT NULL AUTO_INCREMENT,
        community_id INT(11) NOT NULL,
        user_id VARCHAR(9) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (like_id),
        UNIQUE KEY unique_like (user_id, community_id),
        KEY community_id_idx (community_id),
        CONSTRAINT fk_community_likes_community FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE,
        CONSTRAINT fk_community_likes_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS community_members (
        id INT(11) NOT NULL AUTO_INCREMENT,
        community_id INT(11) NOT NULL,
        user_id VARCHAR(9) NOT NULL,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_member (community_id, user_id),
        KEY community_members_community_idx (community_id),
        KEY community_members_user_idx (user_id),
        CONSTRAINT fk_community_members_community FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE,
        CONSTRAINT fk_community_members_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS posts (
        post_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) NOT NULL,
        community_id INT(11) DEFAULT NULL,
        content TEXT DEFAULT NULL,
        file_url VARCHAR(255) DEFAULT NULL,
        file_type ENUM('image','video','document','none') DEFAULT 'none',
        visibility ENUM('private','public') DEFAULT 'public',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME DEFAULT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        is_archived TINYINT(1) DEFAULT 0,
        is_deleted TINYINT(1) DEFAULT 0,
        ai_score DECIMAL(3,2) DEFAULT NULL,
        ai_status ENUM('safe','review','rejected') DEFAULT 'safe',
        ai_reason VARCHAR(255) DEFAULT NULL,
        is_announcement TINYINT(1) DEFAULT 0,
        PRIMARY KEY (post_id),
        KEY posts_user_id_idx (user_id),
        KEY fk_posts_community (community_id),
        KEY idx_status (status),
        KEY idx_ai_score (ai_score),
        KEY idx_created (created_at),
        KEY idx_deleted (is_deleted),
        CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_posts_community FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS comments (
        comment_id INT(11) NOT NULL AUTO_INCREMENT,
        post_id INT(11) NOT NULL,
        user_id VARCHAR(9) DEFAULT NULL,
        content TEXT NOT NULL,
        moderation_status ENUM('pending','approved','flagged','removed') DEFAULT 'pending',
        moderation_reason VARCHAR(255) DEFAULT NULL,
        toxicity_score DECIMAL(5,2) DEFAULT NULL,
        moderated_by VARCHAR(9) DEFAULT NULL,
        moderated_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (comment_id),
        KEY comments_post_id_idx (post_id),
        KEY comments_user_id_idx (user_id),
        CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS post_likes (
        like_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) NOT NULL,
        post_id INT(11) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (like_id),
        UNIQUE KEY user_post_unique (user_id, post_id),
        KEY post_likes_post_id_idx (post_id),
        CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS user_likes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) NOT NULL,
        liked_user_id VARCHAR(9) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user_like (user_id, liked_user_id),
        CONSTRAINT fk_user_likes_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_user_likes_liked_user FOREIGN KEY (liked_user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS post_reports (
        report_id INT(11) NOT NULL AUTO_INCREMENT,
        reporter_id VARCHAR(9) NOT NULL,
        post_id INT(11) NOT NULL,
        reason ENUM('spam','harassment','inappropriate','fake_news','copyright','other') NOT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',
        admin_action ENUM('none','warning','delete_post','ban_user') DEFAULT 'none',
        admin_notes TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by VARCHAR(9) DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        PRIMARY KEY (report_id),
        KEY idx_reporter (reporter_id),
        KEY idx_post (post_id),
        KEY idx_status (status),
        CONSTRAINT fk_post_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_post_reports_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        CONSTRAINT fk_post_reports_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS sessions (
        session_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) NOT NULL,
        token TEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (session_id),
        KEY sessions_user_id_idx (user_id),
        CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS shares (
        share_id INT(11) NOT NULL AUTO_INCREMENT,
        post_id INT(11) NOT NULL,
        user_id VARCHAR(9) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (share_id),
        KEY shares_post_id_idx (post_id),
        KEY shares_user_id_idx (user_id),
        CONSTRAINT fk_shares_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        CONSTRAINT fk_shares_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS user_reports (
        report_id INT(11) NOT NULL AUTO_INCREMENT,
        reporter_id VARCHAR(9) NOT NULL,
        reported_user_id VARCHAR(9) NOT NULL,
        reason ENUM('spam','harassment','inappropriate','fake','other') NOT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
        admin_notes TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (report_id),
        KEY idx_reporter (reporter_id),
        KEY idx_reported (reported_user_id),
        KEY idx_status (status),
        CONSTRAINT fk_user_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_user_reports_reported FOREIGN KEY (reported_user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS user_bans (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) NOT NULL,
        banned_by VARCHAR(9) NOT NULL,
        reason TEXT DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user_ban (user_id),
        KEY banned_by_idx (banned_by),
        KEY idx_expires (expires_at),
        CONSTRAINT fk_user_bans_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_user_bans_banned_by FOREIGN KEY (banned_by) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS backups (
        backup_id INT(11) NOT NULL AUTO_INCREMENT,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_size BIGINT(20) DEFAULT 0,
        backup_type ENUM('manual','auto') NOT NULL,
        status ENUM('success','failed') DEFAULT 'success',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (backup_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS announcements (
        announcement_id INT(11) NOT NULL AUTO_INCREMENT,
        post_id INT(11) NOT NULL,
        approved_by VARCHAR(9) DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
        status ENUM('active','expired','hidden') DEFAULT 'active',
        is_pinned TINYINT(1) DEFAULT 0,
        expires_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (announcement_id),
        UNIQUE KEY unique_post_announcement (post_id),
        KEY approved_by_idx (approved_by),
        CONSTRAINT fk_announcements_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        CONSTRAINT fk_announcements_approved_by FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS announcement_requests (
        request_id INT(11) NOT NULL AUTO_INCREMENT,
        post_id INT(11) NOT NULL,
        user_id VARCHAR(9) NOT NULL,
        request_reason TEXT DEFAULT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        reviewed_by VARCHAR(9) DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (request_id),
        KEY fk_ar_post (post_id),
        KEY fk_ar_user (user_id),
        KEY fk_ar_reviewer (reviewed_by),
        CONSTRAINT fk_ar_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        CONSTRAINT fk_ar_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_ar_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS moderation_logs (
        log_id INT(11) NOT NULL AUTO_INCREMENT,
        post_id INT(11) DEFAULT NULL,
        result ENUM('approved','rejected') DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (log_id),
        CONSTRAINT fk_moderation_logs_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS notifications (
        notif_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(9) NOT NULL,
        type ENUM('like','comment','follow') NOT NULL,
        post_id INT(11) DEFAULT NULL,
        comment_id INT(11) DEFAULT NULL,
        community_id INT(11) DEFAULT NULL,
        actor_id VARCHAR(9) NOT NULL,
        actor_username VARCHAR(100) DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (notif_id),
        KEY idx_user (user_id),
        KEY idx_post (post_id),
        KEY fk_notifications_actor (actor_id),
        KEY fk_notifications_comment (comment_id),
        KEY fk_notifications_community (community_id),
        CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id),
        CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users(user_id),
        CONSTRAINT fk_notifications_post FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        CONSTRAINT fk_notifications_comment FOREIGN KEY (comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
        CONSTRAINT fk_notifications_community FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$indexes = [
    "CREATE INDEX idx_posts_user_id ON posts(user_id)",
    "CREATE INDEX idx_posts_created_at ON posts(created_at)",
    "CREATE INDEX idx_comments_post_id ON comments(post_id)",
    "CREATE INDEX idx_comments_user_id ON comments(user_id)",
    "CREATE INDEX idx_post_likes_post_id ON post_likes(post_id)",
    "CREATE INDEX idx_community_likes_community_id ON community_likes(community_id)",
    "CREATE INDEX idx_communities_user_id ON communities(user_id)",
    "CREATE INDEX idx_notifications_user_id ON notifications(user_id)"
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
    if (mysqli_query($conn, $index)) {
        echo "Index created successfully.\n";
    } else {
        $error = mysqli_error($conn);
        if (strpos($error, 'Duplicate key name') !== false || strpos($error, 'Duplicate key name') !== false) {
            echo "Index already exists, skipping.\n";
        } else {
            echo "Error creating index: " . $error . "\n";
        }
    }
}

echo "Database setup completed!\n";
mysqli_close($conn);
?>