-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2026 at 06:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_feedspace`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_accounts`
--

CREATE TABLE `admin_accounts` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_accounts`
--

INSERT INTO `admin_accounts` (`admin_id`, `username`, `password_hash`) VALUES
(1, 'admin1_trix', 'lokilovesleevy'),
(2, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `approved_by` varchar(9) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('active','expired','hidden') DEFAULT 'active',
  `is_pinned` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_requests`
--

CREATE TABLE `announcement_requests` (
  `request_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `request_reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` varchar(9) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_requests`
--

INSERT INTO `announcement_requests` (`request_id`, `post_id`, `user_id`, `request_reason`, `status`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 19, '0324-0501', 'Please feature this welcome post for freshmen.', 'approved', '0324-0509', '2026-05-08 10:04:58', '2026-05-07 17:25:32'),
(2, 20, '0324-0502', 'Important scholarship announcement for students.', 'rejected', '0324-0509', '2026-05-08 10:04:55', '2026-05-07 17:25:37'),
(3, 24, '0324-0509', 'Admin wants this posted as official announcement.', 'rejected', '0324-0509', '2026-05-08 10:04:54', '2026-05-07 17:25:42');

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `backup_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `backup_type` enum('manual','auto') NOT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backups`
--

INSERT INTO `backups` (`backup_id`, `file_name`, `file_path`, `file_size`, `backup_type`, `status`, `created_at`) VALUES
(14, 'feedspace_manual_20260510_182919.sql.gz', '', 5874, 'manual', 'success', '2026-05-11 00:29:20'),
(15, 'feedspace_auto_20260511_144333.sql.gz', '', 5796, 'auto', 'success', '2026-05-11 20:43:34'),
(16, 'feedspace_manual_20260511_153904.sql.gz', '', 5827, 'manual', 'success', '2026-05-11 21:39:04'),
(17, 'feedspace_manual_20260512_143501.sql.gz', '', 5527, 'manual', 'success', '2026-05-12 20:35:01');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` varchar(9) DEFAULT NULL,
  `content` text NOT NULL,
  `moderation_status` enum('pending','approved','flagged','removed') DEFAULT 'pending',
  `moderation_reason` varchar(255) DEFAULT NULL,
  `toxicity_score` decimal(5,2) DEFAULT NULL,
  `moderated_by` varchar(50) DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`comment_id`, `post_id`, `user_id`, `content`, `moderation_status`, `moderation_reason`, `toxicity_score`, `moderated_by`, `moderated_at`, `created_at`) VALUES
(16, 14, '0324-0506', 'Nice laptop! Is it still available?', 'flagged', NULL, 0.02, 'Admin', '2026-05-07 16:58:22', '2026-05-07 16:45:14'),
(17, 15, '0324-0501', 'This crypto course looks suspicious.', 'flagged', NULL, 0.88, 'Admin', '2026-05-07 16:58:23', '2026-05-07 16:45:14'),
(19, 17, '0324-0509', 'Cool Arduino project!', 'flagged', NULL, 0.05, 'Admin', '2026-05-09 10:14:15', '2026-05-07 16:45:14'),
(20, 18, '0324-0502', 'Thanks for the SQL tutorial!', 'approved', NULL, 0.03, 'AI System', '2026-05-07 16:45:14', '2026-05-07 16:45:14');

-- --------------------------------------------------------

--
-- Table structure for table `communities`
--

CREATE TABLE `communities` (
  `community_id` int(11) NOT NULL,
  `user_id` int(8) DEFAULT NULL,
  `community_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `member_count` int(11) DEFAULT 0,
  `status` enum('active','archived','suspended') DEFAULT 'active',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `community_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communities`
--

INSERT INTO `communities` (`community_id`, `user_id`, `community_name`, `description`, `created_at`, `member_count`, `status`, `updated_at`, `community_picture`) VALUES
(2, 2, 'Technology', 'Tech news and discussions', '2026-05-06 14:55:25', 890, 'active', '2026-05-08 10:12:48', NULL),
(3, 3, 'Design', 'UI/UX, graphics, and design', '2026-05-06 14:55:25', 450, 'active', '2026-05-08 10:12:48', NULL),
(4, 4, 'Gaming', 'Games, esports, and more', '2026-05-06 14:55:25', 320, 'active', '2026-05-08 10:12:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `community_likes`
--

CREATE TABLE `community_likes` (
  `like_id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_members`
--

CREATE TABLE `community_members` (
  `community_id` int(11) DEFAULT NULL,
  `user_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `moderation_logs`
--

CREATE TABLE `moderation_logs` (
  `log_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `result` enum('approved','rejected') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `checked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `sender_admin_id` int(11) DEFAULT NULL,
  `sender_user_id` varchar(9) DEFAULT NULL,
  `receiver_admin_id` int(11) DEFAULT NULL,
  `receiver_user_id` varchar(9) DEFAULT NULL,
  `receiver_type` enum('admin','user') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp`
--

CREATE TABLE `otp` (
  `otp_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `type` enum('login','register','change_password') NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `post_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `community_id` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `file_type` enum('image','video','document','none') DEFAULT 'none',
  `visibility` enum('private','public') DEFAULT 'public',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_archived` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) DEFAULT 0,
  `ai_score` decimal(3,2) DEFAULT NULL,
  `ai_status` enum('safe','review','rejected') DEFAULT 'safe',
  `ai_reason` varchar(255) DEFAULT NULL,
  `is_announcement` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`post_id`, `user_id`, `community_id`, `content`, `file_url`, `file_type`, `visibility`, `created_at`, `updated_at`, `deleted_at`, `status`, `is_archived`, `is_deleted`, `ai_score`, `ai_status`, `ai_reason`, `is_announcement`) VALUES
(14, '0324-0506', NULL, 'Selling my old laptop - great condition, DM for details ??', NULL, 'none', 'public', '2026-05-03 22:05:40', '2026-05-07 10:49:01', '2026-05-07 10:49:01', 'pending', 0, 1, NULL, '', NULL, 0),
(15, '0324-0507', NULL, 'Buy my crypto course - make $10k/month easy! ??', NULL, 'none', 'private', '2026-05-03 22:05:40', '2026-05-06 14:53:56', NULL, '', 1, 0, NULL, '', NULL, 0),
(16, '0324-0508', NULL, 'Group study session tomorrow 2PM library ??', NULL, 'none', 'public', '2026-05-03 22:05:40', '2026-05-06 14:53:56', NULL, '', 1, 0, NULL, '', NULL, 0),
(17, '0324-0501', NULL, 'My latest Arduino project! Lights up with voice commands ???', 'https://example.com/images/arduino-voice.jpg', 'image', 'public', '2026-05-03 22:05:40', '2026-05-07 10:49:15', NULL, 'rejected', 0, 0, NULL, '', NULL, 0),
(18, '0324-0503', NULL, 'Quick SQL JOINs tutorial for beginners (5 mins) ???', 'https://example.com/videos/sql-joins.mp4', 'video', 'public', '2026-05-03 22:05:40', '2026-05-07 14:23:41', NULL, 'rejected', 0, 0, NULL, '', NULL, 0),
(19, '0324-0501', NULL, 'Sample post 1 for announcement request', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 09:44:13', NULL, 'pending', 1, 0, NULL, 'safe', NULL, 1),
(20, '0324-0502', NULL, 'Sample post 2 - council elections', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 09:44:29', NULL, 'pending', 1, 0, NULL, 'safe', NULL, 1),
(21, '0324-0504', NULL, 'Staff post for meeting announcement', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 00:50:01', NULL, 'pending', 0, 0, NULL, 'safe', NULL, 0),
(22, '0324-0505', NULL, 'Org charity post', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 00:50:01', NULL, 'pending', 0, 0, NULL, 'safe', NULL, 0),
(23, '0324-0506', NULL, 'Emergency post', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 00:50:01', NULL, 'pending', 0, 0, NULL, 'safe', NULL, 0),
(24, '0324-0509', NULL, 'Admin maintenance post', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 02:01:43', NULL, 'pending', 0, 0, NULL, 'safe', NULL, 1),
(25, '0324-0501', NULL, 'Freshman welcome post', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 00:50:01', NULL, 'pending', 0, 0, NULL, 'safe', NULL, 0),
(26, '0324-0502', NULL, 'Scholarship post', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 00:50:01', NULL, 'pending', 0, 0, NULL, 'safe', NULL, 0),
(27, '0324-0504', NULL, 'Cultural festival post', NULL, 'none', 'public', '2026-05-08 00:50:01', '2026-05-08 00:50:01', NULL, 'pending', 0, 0, NULL, 'safe', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `post_likes`
--

CREATE TABLE `post_likes` (
  `like_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_reports`
--

CREATE TABLE `post_reports` (
  `report_id` int(11) NOT NULL,
  `reporter_id` varchar(9) NOT NULL,
  `post_id` int(11) NOT NULL,
  `reason` enum('spam','harassment','inappropriate','fake_news','copyright','other') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `admin_action` enum('none','warning','delete_post','ban_user') DEFAULT 'none',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` varchar(9) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_reports`
--

INSERT INTO `post_reports` (`report_id`, `reporter_id`, `post_id`, `reason`, `description`, `status`, `admin_action`, `admin_notes`, `created_at`, `reviewed_by`, `reviewed_at`) VALUES
(1, '0324-0501', 1, 'spam', 'This post contains repeated spam links and ads', 'pending', 'none', NULL, '2026-05-04 02:07:38', NULL, NULL),
(2, '0324-0502', 2, 'harassment', 'User is being bullied in comments', 'reviewed', 'warning', 'Warned the commenter', '2026-05-04 02:07:38', NULL, NULL),
(3, '0324-0503', 3, 'inappropriate', 'Contains explicit language and NSFW content', 'pending', 'none', NULL, '2026-05-04 02:07:38', NULL, NULL),
(4, '0324-0504', 4, 'fake_news', 'Spreading false information about elections', 'resolved', 'delete_post', 'Post deleted, user warned', '2026-05-04 02:07:38', NULL, NULL),
(5, '0324-0505', 5, 'copyright', 'Direct copy of copyrighted article without permission', '', 'none', 'Pending legal review', '2026-05-04 02:07:38', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `session_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `token` text NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shares`
--

CREATE TABLE `shares` (
  `share_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'default.png',
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('Student','Instructor','Admin','Staff','College Org') DEFAULT 'Student',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_activity` datetime DEFAULT current_timestamp(),
  `status` enum('active','inactive','banned') DEFAULT 'active',
  `college` enum('College of Computer Studies','College of Arts and Sciences','College of Business Administration and Accountancy','College of Engineering','College of Criminal Justice Education','College of Teacher Education','College of Industrial Technology','College of International Hospitality and Tourism Management') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password_hash`, `profile_picture`, `bio`, `role`, `created_at`, `updated_at`, `last_activity`, `status`, `college`) VALUES
('0324-0501', 'Juan', 'Dela Cruz', 'juan@example.com', 'hashed123', 'default.png', 'Student profile', 'Student', '2026-05-03 20:22:45', '2026-05-11 19:35:31', '2026-05-03 20:22:45', 'active', NULL),
('0324-0502', 'Maria', 'Santos', 'maria@example.com', 'hashed123', 'default.png', 'Inactive account', 'Student', '2026-05-03 20:22:45', '2026-05-05 16:15:04', '2026-05-03 20:22:45', 'active', NULL),
('0324-0504', 'Anna', 'Lopez', 'anna@example.com', 'hashed123', 'default.png', 'Staff member', 'Staff', '2026-05-03 20:22:45', '2026-05-05 16:09:46', '2026-05-03 20:22:45', 'active', NULL),
('0324-0505', 'Mark', 'Rivera', 'mark@example.com', 'hashed123', 'default.png', 'Org account', '', '2026-05-03 20:22:45', '2026-05-05 15:54:11', '2026-05-03 20:22:45', 'banned', NULL),
('0324-0506', 'Lisa', 'Garcia', 'lisa@example.com', 'hashed123', 'default.png', 'Reported user', 'Student', '2026-05-03 20:22:45', '2026-05-05 15:46:26', '2026-05-03 20:22:45', 'banned', NULL),
('0324-0508', 'Trixie', 'Pontiga', '0324-0508@lspu.edu.ph', '$2y$10$VfvfCN7l4I35fpKdS7jzPukF.6VCkqVqntUca9hYcXKC8HoFO9s7y', 'default.png', '', 'Student', '2026-05-11 18:09:45', '2026-05-12 00:09:45', '2026-05-12 00:09:45', 'active', ''),
('0324-0509', 'Admin', 'Superuser', 'admin@feedspace.com', 'admin_hash_123', 'admin_avatar.png', 'System Administrator with full access', 'Admin', '2026-05-05 14:35:03', '2026-05-05 14:35:03', '2026-05-05 14:35:03', 'active', NULL),
('0394-8736', 'Trisa', 'Mongi', 'pontigatrishalyn@gmail.com', '$2y$10$DWwvV6xNTILdtlMyKnssB.kYhGES5BNPQxEUmM2cQe0qBcpsstYCW', 'default.png', '', 'Student', '2026-05-10 17:48:05', '2026-05-10 23:48:05', '2026-05-10 23:48:05', 'active', '');

-- --------------------------------------------------------

--
-- Table structure for table `user_bans`
--

CREATE TABLE `user_bans` (
  `id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `banned_by` varchar(9) NOT NULL,
  `reason` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_likes`
--

CREATE TABLE `user_likes` (
  `id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `liked_user_id` varchar(9) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_reports`
--

CREATE TABLE `user_reports` (
  `report_id` int(11) NOT NULL,
  `reporter_id` varchar(9) NOT NULL,
  `reported_user_id` varchar(9) NOT NULL,
  `reason` enum('spam','harassment','inappropriate','fake','other') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_reports`
--

INSERT INTO `user_reports` (`report_id`, `reporter_id`, `reported_user_id`, `reason`, `description`, `status`, `admin_notes`, `created_at`) VALUES
(4, '0324-0501', '0324-0506', 'spam', 'User is posting repeated spam content in groups', 'resolved', NULL, '2026-05-03 12:24:05'),
(5, '0324-0502', '0324-0507', 'harassment', 'User sent offensive messages to classmates', 'reviewed', 'User warned by admin', '2026-05-03 12:24:05'),
(6, '0324-0503', '0324-0508', 'inappropriate', 'Inappropriate profile content detected', 'resolved', 'Content removed and user monitored', '2026-05-03 12:24:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD UNIQUE KEY `unique_post_announcement` (`post_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `announcement_requests`
--
ALTER TABLE `announcement_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_ar_post` (`post_id`),
  ADD KEY `fk_ar_user` (`user_id`),
  ADD KEY `fk_ar_reviewer` (`reviewed_by`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`backup_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `communities`
--
ALTER TABLE `communities`
  ADD PRIMARY KEY (`community_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `community_likes`
--
ALTER TABLE `community_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`community_id`),
  ADD KEY `community_id` (`community_id`);

--
-- Indexes for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_admin_id` (`sender_admin_id`),
  ADD KEY `receiver_admin_id` (`receiver_admin_id`),
  ADD KEY `sender_user_id` (`sender_user_id`),
  ADD KEY `receiver_user_id` (`receiver_user_id`);

--
-- Indexes for table `otp`
--
ALTER TABLE `otp`
  ADD PRIMARY KEY (`otp_id`),
  ADD UNIQUE KEY `unique_user_type` (`user_id`,`type`),
  ADD KEY `otp_ibfk_1` (`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `posts_ibfk_1` (`user_id`),
  ADD KEY `fk_posts_community` (`community_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_ai_score` (`ai_score`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_deleted` (`is_deleted`);

--
-- Indexes for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`post_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `post_reports`
--
ALTER TABLE `post_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_reporter` (`reporter_id`),
  ADD KEY `idx_post` (`post_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `sessions_ibfk_1` (`user_id`);

--
-- Indexes for table `shares`
--
ALTER TABLE `shares`
  ADD PRIMARY KEY (`share_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `shares_ibfk_2` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `unique_user_id` (`user_id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `user_id_2` (`user_id`);

--
-- Indexes for table `user_bans`
--
ALTER TABLE `user_bans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_ban` (`user_id`),
  ADD KEY `banned_by` (`banned_by`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `user_likes`
--
ALTER TABLE `user_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unique_like` (`user_id`,`liked_user_id`);

--
-- Indexes for table `user_reports`
--
ALTER TABLE `user_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_reporter` (`reporter_id`),
  ADD KEY `idx_reported` (`reported_user_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_requests`
--
ALTER TABLE `announcement_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `backup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `communities`
--
ALTER TABLE `communities`
  MODIFY `community_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `community_likes`
--
ALTER TABLE `community_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp`
--
ALTER TABLE `otp`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `post_likes`
--
ALTER TABLE `post_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_reports`
--
ALTER TABLE `post_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shares`
--
ALTER TABLE `shares`
  MODIFY `share_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_bans`
--
ALTER TABLE `user_bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_likes`
--
ALTER TABLE `user_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_reports`
--
ALTER TABLE `user_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcement_admin` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_announcement_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_requests`
--
ALTER TABLE `announcement_requests`
  ADD CONSTRAINT `fk_ar_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ar_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ar_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Constraints for table `community_likes`
--
ALTER TABLE `community_likes`
  ADD CONSTRAINT `community_likes_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`community_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`sender_admin_id`) REFERENCES `admin_accounts` (`admin_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`receiver_admin_id`) REFERENCES `admin_accounts` (`admin_id`),
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notifications_ibfk_4` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `otp`
--
ALTER TABLE `otp`
  ADD CONSTRAINT `otp_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`community_id`) ON DELETE SET NULL;

--
-- Constraints for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `shares`
--
ALTER TABLE `shares`
  ADD CONSTRAINT `shares_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shares_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_bans`
--
ALTER TABLE `user_bans`
  ADD CONSTRAINT `user_bans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_bans_ibfk_2` FOREIGN KEY (`banned_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
