<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.html");
    exit();
}

$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ban-check.php';

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo 'Account banned';
    exit();
}

// ========== FETCH CURRENT USER FROM DATABASE ==========
$currentUserId = $user_id;

$currentUserStmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = ?");
$currentUserStmt->execute([$currentUserId]);
$currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);

$currentFirstName = $currentUser['first_name'] ?? 'User';
$currentLastName  = $currentUser['last_name'] ?? '';
$currentUserName  = trim($currentFirstName . ' ' . $currentLastName) ?: 'User';

$currentUserPic = $currentUser['profile_picture'] ?? '';
if (empty($currentUserPic)) {
    $currentUserPic = 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($currentFirstName);
} elseif (strpos($currentUserPic, 'http') !== 0 && strpos($currentUserPic, 'data:') !== 0) {
    $currentUserPic = '../uploads/profiles/' . $currentUserPic;
}
// =====================================

$community_id = intval($_GET['id'] ?? 0);

// ========== MODE: COMMUNITY DETAIL ==========
if ($community_id) {
    $stmt = $conn->prepare("SELECT c.*, u.first_name, u.last_name, u.profile_picture as creator_pic 
        FROM communities c 
        LEFT JOIN users u ON c.created_by = u.user_id 
        WHERE c.community_id = ?");
    $stmt->execute([$community_id]);
    $community = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$community) {
        ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Community Not Found — FeedSpace</title>
  <style>
    body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f5f7fa; }
    .box { text-align: center; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    h1 { color: #355872; margin-bottom: 10px; }
    p  { color: #65676b; margin-bottom: 20px; }
    a  { display: inline-block; background: #355872; color: white; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: bold; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Community Not Found</h1>
    <p>This community doesn't exist or has been removed.</p>
    <a href="community.php">← Back to Communities</a>
  </div>
</body>
</html><?php
        exit;
    }

    $creator_name = trim(($community['first_name'] ?? '') . ' ' . ($community['last_name'] ?? ''));
    if (!$creator_name) $creator_name = 'Unknown';

    $memberStmt = $conn->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
    $memberStmt->execute([$community_id, $user_id]);
    $myRole = $memberStmt->fetchColumn();

    $isCreator = ((string)$community['created_by'] === (string)$user_id);
    $isMember  = (bool)$myRole || $isCreator;
    $isAdmin   = ($myRole === 'admin') || $isCreator;

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM community_members WHERE community_id = ?");
    $countStmt->execute([$community_id]);
    $memberCount = $countStmt->fetchColumn();

    $postCountStmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE community_id = ? AND is_deleted = 0");
    $postCountStmt->execute([$community_id]);
    $postCount = $postCountStmt->fetchColumn();

    $membersStmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.profile_picture, cm.role, cm.joined_at
        FROM community_members cm
        JOIN users u ON cm.user_id = u.user_id
        WHERE cm.community_id = ?
        ORDER BY CASE WHEN cm.role = 'admin' THEN 0 ELSE 1 END, cm.joined_at DESC
        LIMIT 30
    ");
    $membersStmt->execute([$community_id]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

    $postsStmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.profile_picture,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND moderation_status IN ('approved','flagged')) as comment_count,
            EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.community_id = ? AND p.is_deleted = 0 AND p.status = 'approved'
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $postsStmt->execute([$user_id, $community_id]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

    $pageMode = 'detail';

// ========== MODE: COMMUNITIES LISTING ==========
} else {
    $community = null;

    $communitiesStmt = $conn->prepare("
        SELECT c.*, u.first_name, u.last_name, 
            (SELECT COUNT(*) FROM community_members WHERE community_id = c.community_id) as member_count,
            (SELECT COUNT(*) FROM posts WHERE community_id = c.community_id AND is_deleted = 0) as post_count
        FROM communities c
        LEFT JOIN users u ON c.created_by = u.user_id
        WHERE c.is_active = 1
        ORDER BY c.created_at DESC
    ");
    $communitiesStmt->execute();
    $communities = $communitiesStmt->fetchAll(PDO::FETCH_ASSOC);

    $joinedStmt = $conn->prepare("SELECT community_id, role FROM community_members WHERE user_id = ?");
    $joinedStmt->execute([$user_id]);
    $joinedCommunities = [];
    while ($row = $joinedStmt->fetch(PDO::FETCH_ASSOC)) {
        $joinedCommunities[$row['community_id']] = $row['role'];
    }

    $pageMode = 'listing';
}

$pageTitle = ($pageMode === 'detail' && $community)
    ? htmlspecialchars($community['name']) . ' — FeedSpace'
    : 'Communities — FeedSpace';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo $pageTitle; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
/* ============================================
   ALL CSS SELF-CONTAINED - NO EXTERNAL DEPENDENCIES
   ============================================ */

:root {
  --color-dark:    #355872;
  --color-mid:     #7AAACE;
  --color-light:   #9CD5FF;
  --color-cream:   #F7F8F0;
  --color-white:   #ffffff;
  --color-text:    #2b3a4a;
  --color-subtext: #6b7f8e;
  --color-border:  #dde8f0;
  --color-danger:  #e05263;
  --shadow-sm:     0 2px 8px rgba(53, 88, 114, 0.08);
  --shadow-md:     0 4px 16px rgba(53, 88, 114, 0.12);
  --shadow-lg:     0 8px 32px rgba(53, 88, 114, 0.16);
  --radius-sm:     8px;
  --radius-md:     12px;
  --radius-lg:     16px;
  --radius-full:   999px;
  --sidebar-w:     210px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Nunito', sans-serif;
  background-color: var(--color-cream);
  color: var(--color-text);
  min-height: 100vh;
  font-size: 15px;
  line-height: 1.5;
}

a { text-decoration: none; color: inherit; }
button { font-family: inherit; cursor: pointer; }

/* ===== NAVBAR ===== */
.navbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  height: 72px; background: var(--color-white);
  border-bottom: 2px solid var(--color-border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 28px; gap: 18px;
  box-shadow: var(--shadow-sm);
}

.nav-logo { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.nav-logo img { height: 38px; width: auto; max-width: 130px; object-fit: contain; }
.nav-logo-fallback { display: none; align-items: center; gap: 6px; }
.nav-logo-fallback .icon { font-size: 1.5rem; }
.nav-logo-fallback .text { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.15rem; color: var(--color-dark); }

.nav-actions { 
  display: flex; 
  align-items: center; 
  gap: 8px; 
  flex-shrink: 0;
  position: relative;
}

.nav-search {
  display: flex;
  align-items: center;
  flex: 1;
  justify-content: center;
}

.search-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--color-cream);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 10px 18px;
  min-width: 420px;
  max-width: 560px;
  width: 100%;
  transition: border-color 0.2s;
}

.search-bar:focus-within {
  border-color: var(--color-mid);
}

.search-bar i {
  color: var(--color-subtext);
  font-size: 0.9rem;
}

.search-bar input {
  border: none;
  background: transparent;
  outline: none;
  font-family: inherit;
  font-size: 0.9rem;
  width: 100%;
  color: var(--color-text);
}

.search-bar input::placeholder {
  color: var(--color-subtext);
}

.nav-icon-btn {
  width: 42px; height: 42px; border-radius: 50%;
  border: 2px solid var(--color-border); background: var(--color-cream);
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; color: var(--color-dark); position: relative;
  transition: background 0.2s, border-color 0.2s;
  flex-shrink: 0;
}
.nav-icon-btn:hover { background: var(--color-light); border-color: var(--color-mid); }
.nav-icon-btn .badge {
  position: absolute; top: -4px; right: -4px;
  background: var(--color-danger); color: white; font-size: 0.62rem; font-weight: 800;
  width: 17px; height: 17px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
}

.profile-chip {
  display: flex; align-items: center; gap: 8px; background: var(--color-cream);
  border: 2px solid var(--color-border); border-radius: var(--radius-full);
  padding: 6px 14px; cursor: pointer; font-weight: 700; font-size: 0.88rem;
  color: var(--color-dark); transition: background 0.2s, border-color 0.2s;
  flex-shrink: 0;
}
.profile-chip:hover { background: var(--color-light); border-color: var(--color-mid); }
.profile-chip img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }

/* ===== DROPDOWNS ===== */
.dropdown {
  position: absolute; top: 60px; right: 0; background: var(--color-white);
  border: 2px solid var(--color-border); border-radius: var(--radius-md);
  min-width: 220px; box-shadow: var(--shadow-lg); display: none; z-index: 200; overflow: hidden;
}
.dropdown.show { display: block; animation: dropIn 0.16s ease; }
@keyframes dropIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

.dropdown-header { padding: 12px 16px 8px; font-weight: 800; font-size: 0.88rem; color: var(--color-dark); border-bottom: 1px solid var(--color-border); }
.dropdown-item { padding: 10px 16px; cursor: pointer; font-size: 0.88rem; color: var(--color-text); display: flex; align-items: center; gap: 10px; transition: background 0.15s; font-weight: 600; }
.dropdown-item:hover { background: var(--color-cream); }
.dropdown-item.danger { color: var(--color-danger); }
.dropdown-item.danger:hover { background: #fff0f2; }
.dropdown-divider { border-top: 1px solid var(--color-border); }

/* ===== LAYOUT ===== */
.app-body { display: flex; padding-top: 72px; min-height: 100vh; }

/* ===== SIDEBAR ===== */
.sidebar {
  width: var(--sidebar-w);
  flex-shrink: 0;
  background: var(--color-white);
  min-height: calc(100vh - 72px);
  position: fixed;
  top: 72px;
  left: 0;
  display: flex;
  flex-direction: column;
  padding: 16px 0 16px;
  border-right: 2px solid var(--color-border);
  z-index: 90;
  overflow-y: auto;
}

.sidebar-profile-entry {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  padding: 10px 16px;
  margin: 0 8px 8px;
  border-radius: var(--radius-md);
  transition: background 0.18s;
  cursor: pointer;
}
.sidebar-profile-entry:hover {
  background: var(--color-cream);
}
.sidebar-profile-entry img {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 2px solid var(--color-border);
  flex-shrink: 0;
  object-fit: cover;
}
.sidebar-profile-name {
  font-size: 0.88rem;
  font-weight: 800;
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-divider {
  height: 1px;
  background: var(--color-border);
  margin: 4px 16px 8px;
}

.sidebar-nav {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0 8px;
  width: 100%;
}

.sidebar-nav a {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 14px;
  border-radius: var(--radius-md);
  color: var(--color-subtext);
  font-size: 0.9rem;
  font-weight: 700;
  transition: background 0.18s, color 0.18s;
  position: relative;
  white-space: nowrap;
}
.sidebar-nav a span { display: inline; }
.sidebar-nav a i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
.sidebar-nav a:hover { background: var(--color-cream); color: var(--color-dark); }
.sidebar-nav a.active {
  background: var(--color-cream);
  color: var(--color-dark);
  font-weight: 800;
}
.sidebar-nav a.active i { color: var(--color-dark); }

.sidebar-bottom {
  margin-top: auto;
  padding-top: 8px;
  border-top: 1px solid var(--color-border);
}

.sidebar-signout {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 14px;
  margin: 0 8px;
  width: calc(100% - 16px);
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.18s;
  border: none;
  background: none;
  font-family: inherit;
  text-decoration: none;
}
.sidebar-signout:hover { background: #fff0f2; }
.sidebar-signout i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }

/* ===== MAIN CONTENT ===== */
.main-content { 
  margin-left: var(--sidebar-w); 
  flex: 1; 
  padding: 28px;
  display: flex;
  justify-content: center;
}

.content-center {
  width: 100%;
  max-width: 660px;
}

/* ===== MODAL - COMPLETELY SELF-CONTAINED ===== */
.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(53, 88, 114, 0.45);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-overlay.show {
  display: flex;
  animation: fadeIn 0.18s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal {
  background: var(--color-white);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: var(--shadow-lg);
  position: relative;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border);
}

.modal-header h3 {
  font-weight: 800;
  color: var(--color-dark);
  font-size: 1rem;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1rem;
  color: var(--color-subtext);
  cursor: pointer;
  transition: color 0.15s;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.modal-close:hover {
  color: var(--color-danger);
  background: var(--color-cream);
}

.modal-body {
  padding: 16px 20px;
}

.modal-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 12px 20px;
  border-top: 1px solid var(--color-border);
}

/* ===== BUTTONS ===== */
.btn-primary {
  background: var(--color-dark);
  color: var(--color-white);
  border: none;
  padding: 8px 22px;
  border-radius: var(--radius-full);
  font-family: inherit;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover {
  background: var(--color-mid);
}

.btn-secondary {
  background: var(--color-cream);
  color: var(--color-subtext);
  border: 2px solid var(--color-border);
  padding: 8px 22px;
  border-radius: var(--radius-full);
  font-family: inherit;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: var(--color-border);
  color: var(--color-dark);
}

/* ===== POST CARD ===== */
.post-card {
  background: var(--color-white);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  margin-bottom: 14px;
  box-shadow: var(--shadow-sm);
  position: relative;
  overflow: visible;
}

.post-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 14px 0;
  position: relative;
}

.post-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid var(--color-border);
  flex-shrink: 0;
  object-fit: cover;
}

.post-meta {
  flex: 1;
}

.post-author {
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--color-text);
}

.post-time {
  color: var(--color-mid);
  font-size: 0.8rem;
}

.post-body {
  padding: 11px 14px;
  font-size: 0.9rem;
  line-height: 1.65;
  color: var(--color-text);
}

.post-image {
  width: 100%;
  max-height: 400px;
  object-fit: cover;
  border-radius: var(--radius-sm);
  margin-top: 10px;
}

.post-footer {
  display: flex;
  gap: 2px;
  padding: 7px 14px 12px;
  border-top: 1px solid var(--color-border);
  margin-top: 4px;
}

.post-action-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.84rem;
  color: var(--color-subtext);
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-weight: 600;
  transition: background 0.15s, color 0.15s;
}

.post-action-btn:hover {
  background: var(--color-cream);
  color: var(--color-dark);
}

.post-action-btn.liked {
  color: var(--color-danger);
}

/* ===== COMMUNITY HEADER ===== */
.community-header {
  background: var(--color-white);
  border: 2px solid var(--color-border);
  border-radius: 22px;
  overflow: hidden;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
}

.community-banner {
  width: 100%;
  height: 170px;
  background: linear-gradient(135deg, #355872, #7AAACE, #9CD5FF);
}

.community-info-row {
  display: flex;
  align-items: flex-end;
  gap: 24px;
  padding: 0 32px 24px;
  margin-top: -70px;
  position: relative;
}

.community-avatar-wrap {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  border: 5px solid var(--color-white);
  background: var(--color-white);
  overflow: hidden;
  flex-shrink: 0;
  box-shadow: var(--shadow-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  z-index: 2;
}

.community-text {
  flex: 1;
  padding-bottom: 8px;
}

.community-text h2 {
  font-family: 'Poppins', sans-serif;
  font-size: 1.6rem;
  font-weight: 800;
  color: white;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.community-badge {
  font-size: 0.7rem;
  background: var(--color-light);
  color: white;
  padding: 4px 12px;
  border-radius: 999px;
  font-weight: 800;
}

.community-college {
  font-size: 0.9rem;
  color: white;
  font-weight: 700;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.community-stats-row {
  display: flex;
  align-items: center;
  gap: 20px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.stat-item strong {
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--color-text);
}

.stat-label {
  font-size: 0.75rem;
  color: var(--color-subtext);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.stat-divider {
  width: 1px;
  height: 36px;
  background: var(--color-border);
}

.community-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: flex-end;
  padding-bottom: 8px;
}

.btn-join-community {
  background: var(--color-dark);
  color: var(--color-white);
  border: none;
  border-radius: var(--radius-full);
  padding: 10px 28px;
  font-family: inherit;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-join-community:hover {
  background: var(--color-mid);
  transform: translateY(-1px);
}

.btn-join-community.joined {
  background: var(--color-cream);
  color: var(--color-subtext);
  border: 2px solid var(--color-border);
}

.btn-admin-badge {
  background: linear-gradient(135deg, #fff3cd, #ffeaa7);
  color: #856404;
  border: none;
  border-radius: var(--radius-full);
  padding: 8px 20px;
  font-family: inherit;
  font-weight: 700;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-admin-action {
  background: var(--color-cream);
  color: var(--color-subtext);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 6px 16px;
  font-family: inherit;
  font-weight: 700;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-admin-action:hover {
  background: var(--color-light);
  border-color: var(--color-mid);
  color: var(--color-dark);
}

.btn-admin-danger {
  color: var(--color-danger);
  border-color: #f5c6cb;
}

.btn-admin-danger:hover {
  background: #fff0f2;
  border-color: var(--color-danger);
  color: var(--color-danger);
}

.community-bio {
  padding: 0 32px 20px;
  font-size: 0.95rem;
  color: var(--color-subtext);
  line-height: 1.6;
}

.community-meta {
  display: flex;
  gap: 20px;
  padding: 0 32px 24px;
  font-size: 0.85rem;
  color: var(--color-subtext);
  font-weight: 600;
}

.community-meta span {
  display: flex;
  align-items: center;
  gap: 6px;
}

/* ===== TABS ===== */
.community-tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 20px;
  background: var(--color-cream);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 4px;
  width: fit-content;
}

.community-tab {
  background: transparent;
  border: none;
  border-radius: var(--radius-full);
  padding: 8px 24px;
  font-family: inherit;
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--color-subtext);
  cursor: pointer;
  transition: all 0.18s;
  display: flex;
  align-items: center;
  gap: 8px;
}

.community-tab.active {
  background: var(--color-dark);
  color: var(--color-white);
}

/* ===== CREATE POST BOX ===== */
.create-post-box {
  background: var(--color-white);
  border: 2px solid var(--color-border);
  border-radius: 16px;
  padding: 16px 20px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
}

.create-post-top {
  display: flex;
  align-items: center;
  gap: 12px;
}

.create-post-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.create-post-input {
  flex: 1;
  border: none;
  background: var(--color-cream);
  border-radius: 999px;
  padding: 12px 18px;
  font-size: 0.95rem;
  color: var(--color-dark);
  outline: none;
  cursor: pointer;
  transition: background 0.2s;
  font-family: inherit;
}

.create-post-input:hover {
  background: #e8e8ec;
}

.create-post-divider {
  height: 1px;
  background: var(--color-border);
  margin: 14px 0;
}

.create-post-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.create-post-action {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--color-subtext);
  transition: background 0.15s;
  font-family: inherit;
}

.create-post-action:hover {
  background: var(--color-cream);
}

/* ===== MEMBERS ===== */
.members-section {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

.member-item {
  background: var(--color-white);
  border: 2px solid var(--color-border);
  border-radius: 16px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  transition: all 0.2s;
}

.member-item:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.member-item img {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--color-border);
}

.member-item-info {
  flex: 1;
}

.member-item-name {
  font-weight: 800;
  font-size: 0.95rem;
}

.member-item-role {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 3px 12px;
  border-radius: 999px;
  margin-top: 4px;
  text-transform: uppercase;
}

.role-admin {
  background: linear-gradient(135deg, #fff3cd, #ffeaa7);
  color: #856404;
}

.role-moderator {
  background: linear-gradient(135deg, #d1ecf1, #74b9ff);
  color: #0c5460;
}

.role-member {
  background: var(--color-cream);
  color: var(--color-subtext);
}

/* ===== PANELS ===== */
.community-panel {
  display: none;
}

.community-panel.active {
  display: block;
}

/* ===== EMPTY STATE ===== */
.empty-community {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-subtext);
  background: var(--color-white);
  border-radius: 16px;
  border: 2px dashed var(--color-border);
}

.empty-community i {
  font-size: 3rem;
  margin-bottom: 16px;
  opacity: 0.3;
  display: block;
}

.empty-community h3 {
  color: var(--color-text);
  margin-bottom: 8px;
  font-weight: 800;
}

/* ===== FORM HELPERS ===== */
.form-group {
  margin-bottom: 14px;
}

.form-label {
  display: block;
  margin-bottom: 6px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--color-dark);
}

.form-input,
.form-textarea,
.form-select {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid var(--color-border);
  border-radius: 10px;
  font-size: 0.9rem;
  font-family: inherit;
  outline: none;
  color: var(--color-text);
  background: var(--color-white);
  box-sizing: border-box;
}

.form-textarea {
  resize: vertical;
}

/* ===== TOAST ===== */
#toastContainer {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 99999;
  pointer-events: none;
}
.post-community-user {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 2px;
}

.toast-msg {
  background: #4caf7d;
  color: white;
  padding: 12px 24px;
  border-radius: 999px;
  font-weight: 700;
  white-space: nowrap;
  margin-bottom: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  animation: slideUp 0.3s ease;
}

.toast-msg.error {
  background: #e05263;
}

.post-author {
    display: flex;
    align-items: center;
    gap: 6px;
}

.post-author::before {
    content: "🌐";
    font-size: 0.9rem;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

  </style>
</head>
<body>

<!-- ========== NAVBAR ========== -->
<header class="navbar">
  <div class="nav-logo">
    <a href="feed-view.php">
      <img src="../assets/logo.png" alt="FeedSpace" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"/>
      <span class="nav-logo-fallback"><span class="icon">🏠</span><span class="text">FeedSpace</span></span>
    </a>
  </div>

  <div class="nav-search">
    <div class="search-bar">
      <i class="fas fa-search"></i>
      <input id="navSearchInput" type="text" placeholder="Search posts, people, topics..." autocomplete="off"/>
    </div>
    <div id="navSearchResults" class="nav-search-results" aria-live="polite"></div>
  </div>

  <div class="nav-actions">
    <button class="nav-icon-btn" onclick="toggleDropdown('notifDropdown')">
      <i class="fas fa-bell"></i><span class="badge" id="notifCount">3</span>
    </button>

    <div class="dropdown" id="notifDropdown">
      <div class="dropdown-header">Notifications</div>
      <div id="notifList"></div>
    </div>
  </div>
</header>

<!-- ========== BODY ========== -->
<div class="app-body">
  <aside class="sidebar">
    <a href="profile.php?id=<?php echo urlencode($currentUserId); ?>" class="sidebar-profile-entry" title="Go to profile">
      <img src="<?php echo htmlspecialchars($currentUserPic); ?>" alt="Profile" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
      <span class="sidebar-profile-name"><?php echo htmlspecialchars($currentUserName); ?></span>
    </a>

    <div class="sidebar-divider"></div>

    <nav class="sidebar-nav">
      <a href="feed-view.php"><i class="fas fa-home"></i><span>Feed</span></a>
      <a href="announcements.php"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
      <a href="community.php" class="active"><i class="fas fa-users"></i><span>Communities</span></a>
      <a href="help.php"><i class="fas fa-question-circle"></i><span>Help</span></a>
      <a href="about.html"><i class="fas fa-info-circle"></i><span>About</span></a>
    </nav>

    <div class="sidebar-bottom">
      <a href="../../api/logout.php" class="sidebar-signout">
        <i class="fas fa-sign-out-alt"></i><span>Sign out</span>
      </a>
    </div>
  </aside>

  <main class="main-content">
    <div class="content-center">

      <?php if ($pageMode === 'detail'): ?>

      <!-- ══ COMMUNITY HEADER ══ -->
      <div class="community-header">
        <div class="community-banner"></div>
        <div class="community-info-row">
          <div class="community-avatar-wrap">🌐</div>
          <div class="community-text">
            <h2>
              <?php echo htmlspecialchars($community['name']); ?>
              <span class="community-badge">COMMUNITY</span>
            </h2>
            <div class="community-college">
              <i class="fas fa-graduation-cap"></i>
              <?php echo htmlspecialchars($community['college'] ?? 'General'); ?>
            </div>
            <div class="community-stats-row">
              <span class="stat-item">
                <strong><?php echo number_format($memberCount); ?></strong>
                <span class="stat-label">Members</span>
              </span>
              <span class="stat-divider"></span>
              <span class="stat-item">
                <strong><?php echo number_format($postCount); ?></strong>
                <span class="stat-label">Posts</span>
              </span>
              <span class="stat-divider"></span>
              <span class="stat-item">
                <strong><?php echo number_format(count($posts)); ?></strong>
                <span class="stat-label">Recent</span>
              </span>
            </div>
          </div>
          <div class="community-actions">
  <?php if ($isAdmin): ?>
    <span class="btn-admin-badge"><i class="fas fa-crown"></i> You are Admin</span>
    <div style="display: flex; gap: 8px; margin-top: 8px;">
      <button class="btn-admin-action" onclick="openModal('editCommModal')">
        <i class="fas fa-pen"></i> Edit
      </button>
      <button class="btn-admin-action btn-admin-danger" onclick="confirmDeleteCommunity()">
        <i class="fas fa-trash"></i> Delete
      </button>
    </div>
  <?php elseif ($isMember): ?>
              <button class="btn-join-community joined" onclick="toggleJoin(<?php echo $community_id; ?>, this)">
                <i class="fas fa-check"></i> Joined
              </button>
            <?php else: ?>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($community['description'])): ?>
          <div class="community-bio"><?php echo nl2br(htmlspecialchars($community['description'])); ?></div>
        <?php endif; ?>
        <div class="community-meta">
          <span><i class="fas fa-user"></i> Created by <?php echo htmlspecialchars($creator_name); ?></span>
          <span><i class="fas fa-calendar"></i> <?php echo date('M Y', strtotime($community['created_at'])); ?></span>
        </div>
      </div>

      <!-- ══ TABS ══ -->
      <div class="community-tabs">
        <button class="community-tab active" onclick="switchTab('posts', this)"><i class="fas fa-file-alt"></i> Posts</button>
        <button class="community-tab" onclick="switchTab('members', this)"><i class="fas fa-users"></i> Members</button>
      </div>

      <!-- ══ POSTS PANEL ══ -->
      <div id="posts-panel" class="community-panel active">
        <?php if ($isMember): ?>
        <div class="create-post-box">
          <div class="create-post-top">
            <img src="<?php echo htmlspecialchars($currentUserPic); ?>" class="create-post-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
            <input type="text" class="create-post-input" placeholder="Write something in <?php echo htmlspecialchars($community['name']); ?>..." onclick="openModal('postModal'); setTimeout(function(){ document.getElementById('modalPostText').focus(); }, 100);">
          </div>
          <div class="create-post-divider"></div>
          <div class="create-post-actions">
            <button class="create-post-action" onclick="openPostModalWithImage()">
              <i class="fas fa-image" style="color:#45bd62;"></i> Photo
            </button>
            <button class="create-post-action" onclick="openModal('postModal')">
              <i class="fas fa-pen" style="color:#1877f2;"></i> Post
            </button>
          </div>
        </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
        <div class="empty-community">
          <i class="fas fa-inbox"></i>
          <h3>No posts yet</h3>
          <p>Be the first to share something in this community!</p>
        </div>
        <?php else: ?>
          <?php foreach ($posts as $post):
            $pic = !empty($post['profile_picture'])
              ? '../uploads/profiles/' . $post['profile_picture']
              : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($post['first_name'] ?? 'User');
            $postImg = null;
            if (!empty($post['file_url'])) {
              $postImg = preg_match('#^https?://#i', $post['file_url'])
                ? $post['file_url']
                : '../uploads/posts/' . $post['file_url'];
            }
          ?>
          <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
            <div class="post-header">
    <img src="<?php echo htmlspecialchars($pic); ?>" class="post-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
    <div class="post-meta">
        <!-- Show community name as primary, user as secondary -->
        <span class="post-author"><?php echo htmlspecialchars($community['name']); ?></span>
        <span class="post-community-user" style="font-size:0.76rem;color:var(--color-subtext);">
            <i class="fas fa-user" style="font-size:0.7rem;"></i> 
            <?php echo htmlspecialchars(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')); ?>
        </span>
        <span class="post-time"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
    </div>
</div>
            </div>
            <div class="post-body">
              <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
              <?php if ($postImg): ?>
                <img src="<?php echo htmlspecialchars($postImg); ?>" class="post-image" onerror="this.style.display='none'">
              <?php endif; ?>
            </div>
            <div class="post-footer">
              <button class="post-action-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" onclick="toggleLike(<?php echo $post['post_id']; ?>, this)">
                <i class="<?php echo $post['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                <span><?php echo number_format($post['like_count']); ?></span>
              </button>
              <button class="post-action-btn" onclick="showToast('Comments coming soon!')">
                <i class="far fa-comment"></i>
                <span><?php echo number_format($post['comment_count']); ?></span>
              </button>
              <button class="post-action-btn" onclick="showToast('Share coming soon!')">
                <i class="fas fa-share"></i>
                <span>Share</span>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- ══ MEMBERS PANEL ══ -->
      <div id="members-panel" class="community-panel">
        <?php if (empty($members)): ?>
        <div class="empty-community">
          <i class="fas fa-users"></i>
          <h3>No members yet</h3>
          <p>Be the first to join this community!</p>
        </div>
        <?php else: ?>
        <div class="members-section">
          <?php foreach ($members as $m):
            $mPic = !empty($m['profile_picture'])
              ? '../uploads/profiles/' . $m['profile_picture']
              : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($m['first_name'] ?? 'User');
          ?>
          <div class="member-item">
            <img src="<?php echo htmlspecialchars($mPic); ?>" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
            <div class="member-item-info">
              <div class="member-item-name"><?php echo htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')); ?></div>
              <span class="member-item-role role-<?php echo htmlspecialchars($m['role']); ?>"><?php echo ucfirst($m['role']); ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <!-- ══ LISTING MODE ══ -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
          <h1 style="font-family:'Poppins',sans-serif;font-size:1.8rem;font-weight:800;color:var(--color-dark);margin:0;">Communities</h1>
          <p style="color:var(--color-subtext);margin-top:4px;">Discover and join communities on FeedSpace</p>
        </div>
        <button class="btn-join-community" onclick="openModal('createCommModal')">
          <i class="fas fa-plus"></i> Create Community
        </button>
      </div>

      <?php if (empty($communities)): ?>
      <div class="empty-community">
        <i class="fas fa-users"></i>
        <h3>No communities yet</h3>
        <p>Be the first to create a community!</p>
      </div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
        <?php foreach ($communities as $c):
          $c_colors = ['#355872,#7AAACE','#7AAACE,#9CD5FF','#9CD5FF,#355872','#355872,#9CD5FF','#4a7c59,#8fbc8f','#8b4513,#d2691e'];
          $c_idx    = array_sum(array_map('ord', str_split($c['name'] ?? ''))) % count($c_colors);
          $c_grad   = $c_colors[$c_idx];
          $c_creator = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: 'Unknown';
          $isJoined  = isset($joinedCommunities[$c['community_id']]);
          $userRole  = $joinedCommunities[$c['community_id']] ?? null;
        ?>
        <div style="background:var(--color-white);border:2px solid var(--color-border);border-radius:16px;overflow:hidden;transition:transform 0.2s,box-shadow 0.2s;cursor:pointer;"
             onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='var(--shadow-md)';"
             onmouseout="this.style.transform='';this.style.boxShadow='';"
             onclick="window.location.href='community.php?id=<?php echo $c['community_id']; ?>'">
          <div style="height:100px;background:linear-gradient(135deg,<?php echo $c_grad; ?>);display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🌐</div>
          <div style="padding:20px;">
            <h3 style="font-family:'Poppins',sans-serif;font-weight:800;font-size:1.1rem;color:var(--color-dark);margin-bottom:6px;"><?php echo htmlspecialchars($c['name']); ?></h3>
            <p style="font-size:0.85rem;color:var(--color-subtext);line-height:1.5;margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($c['description'] ?? 'No description'); ?></p>
            <div style="display:flex;gap:16px;font-size:0.8rem;color:var(--color-subtext);font-weight:700;margin-bottom:12px;">
              <span><i class="fas fa-users"></i> <?php echo number_format($c['member_count']); ?></span>
              <span><i class="fas fa-file-alt"></i> <?php echo number_format($c['post_count']); ?></span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="font-size:0.8rem;color:var(--color-subtext);">by <?php echo htmlspecialchars($c_creator); ?></span>
              <?php if ($isJoined): ?>
                <span style="background:var(--color-dark);color:white;padding:6px 14px;border-radius:999px;font-size:0.75rem;font-weight:700;">
                  <?php echo $userRole === 'admin' ? '👑 Admin' : '✓ Joined'; ?>
                </span>
              <?php else: ?>
                <span style="background:var(--color-bg);color:var(--color-subtext);padding:6px 14px;border-radius:999px;font-size:0.75rem;font-weight:700;">+ Join</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>

    </div><!-- /.content-center -->
  </main>
</div><!-- /.app-body -->

<div id="toastContainer"></div>

<!-- ========== CREATE POST MODAL ========== -->
<div class="modal-overlay" id="postModal">
  <div class="modal">
    <div class="modal-header">
      <div style="display:flex;align-items:center;gap:10px;">
        <img src="<?php echo htmlspecialchars($currentUserPic); ?>" alt="User" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
        <div>
          <div style="font-weight:800;font-size:0.9rem;color:var(--color-dark);"><?php echo htmlspecialchars($currentUserName); ?></div>
          <div style="font-size:0.74rem;color:var(--color-subtext);"><?php echo htmlspecialchars($community['name'] ?? 'Community'); ?></div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal('postModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <textarea id="modalPostText" placeholder="What's on your mind?" rows="5" style="width:100%;border:2px solid var(--color-border);border-radius:var(--radius-md);padding:10px 14px;font-family:inherit;font-size:0.9rem;outline:none;resize:vertical;color:var(--color-text);background:var(--color-cream);min-height:100px;box-sizing:border-box;"></textarea>
      <div id="modalImagePreview" style="display:none;margin:10px 0;border-radius:12px;overflow:hidden;position:relative;">
        <img id="modalPreviewImg" src="" style="width:100%;max-height:250px;object-fit:cover;display:block;"/>
        <button onclick="clearModalImage()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:0.9rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
      </div>
      <input type="file" id="modalPostImage" accept="image/*" style="display:none;" onchange="previewModalImage(this)"/>
      <div style="display:flex;gap:8px;margin-top:10px;">
        <button style="background:var(--color-cream);border:2px solid var(--color-border);border-radius:var(--radius-sm);padding:7px 12px;font-family:inherit;font-size:0.82rem;font-weight:700;color:var(--color-subtext);cursor:pointer;display:flex;align-items:center;gap:5px;" onclick="document.getElementById('modalPostImage').click()">
          <i class="fas fa-image"></i> Photo
        </button>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('postModal')">Cancel</button>
      <button class="btn-primary" onclick="submitPost()">+ Create Post</button>
    </div>
  </div>
</div>

<!-- ========== DELETE COMMUNITY MODAL ========== -->
<div class="modal-overlay" id="deleteCommModal">
  <div class="modal" style="max-width: 400px;">
    <div class="modal-header">
      <h3><i class="fas fa-exclamation-triangle" style="color: var(--color-danger);"></i> Delete Community</h3>
      <button class="modal-close" onclick="closeModal('deleteCommModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="text-align: center;">
      <p style="color: var(--color-subtext); line-height: 1.6;">
        Are you sure you want to delete <strong style="color: var(--color-text);"><?php echo htmlspecialchars($community['name'] ?? 'this community'); ?></strong>?
      </p>
      <p style="color: var(--color-danger); font-size: 0.85rem; margin-top: 10px; font-weight: 700;">
        <i class="fas fa-exclamation-circle"></i> This action cannot be undone.
      </p>
    </div>
    <div class="modal-footer" style="justify-content: center;">
      <button class="btn-secondary" onclick="closeModal('deleteCommModal')">Cancel</button>
      <button class="btn-primary" onclick="submitDeleteCommunity()" style="background: var(--color-danger);">
        <i class="fas fa-trash"></i> Yes, Delete
      </button>
    </div>
  </div>
</div>

<!-- ========== CREATE COMMUNITY MODAL ========== -->
<div class="modal-overlay" id="createCommModal">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fas fa-plus-circle"></i> Create Community</h3>
      <button class="modal-close" onclick="closeModal('createCommModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Community Name *</label>
        <input type="text" id="createCommName" class="form-input" placeholder="e.g. Photography Lovers"/>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="createCommDesc" class="form-textarea" rows="3" placeholder="What is this community about?"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">College / Program *</label>
        <select id="createCommCollege" class="form-select">
          <option value="">— Select —</option>
          <option value="College of Computer Studies">College of Computer Studies</option>
          <option value="College of Arts and Sciences">College of Arts and Sciences</option>
          <option value="College of Business Administration and Accountancy">College of Business Administration and Accountancy</option>
          <option value="College of Engineering">College of Engineering</option>
          <option value="College of Criminal Justice Education">College of Criminal Justice Education</option>
          <option value="College of Teacher Education">College of Teacher Education</option>
          <option value="College of Industrial Technology">College of Industrial Technology</option>
          <option value="College of International Hospitality and Tourism Management">College of International Hospitality and Tourism Management</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('createCommModal')">Cancel</button>
      <button class="btn-primary" onclick="submitCreateCommunity()">Create Community</button>
    </div>
  </div>
</div>

<!-- ========== EDIT COMMUNITY MODAL ========== -->
<div class="modal-overlay" id="editCommModal">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fas fa-pen"></i> Edit Community</h3>
      <button class="modal-close" onclick="closeModal('editCommModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Community Name *</label>
        <input type="text" id="editCommName" class="form-input" value="<?php echo htmlspecialchars($community['name'] ?? ''); ?>"/>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="editCommDesc" class="form-textarea"><?php echo htmlspecialchars($community['description'] ?? ''); ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">College / Program *</label>
        <select id="editCommCollege" class="form-select">
          <option value="">— Select —</option>
          <?php
          $colleges = [
            'College of Computer Studies',
            'College of Arts and Sciences',
            'College of Business Administration and Accountancy',
            'College of Engineering',
            'College of Criminal Justice Education',
            'College of Teacher Education',
            'College of Industrial Technology',
            'College of International Hospitality and Tourism Management',
          ];
          foreach ($colleges as $col):
            $sel = (($community['college'] ?? '') === $col) ? 'selected' : '';
          ?>
          <option value="<?php echo htmlspecialchars($col); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($col); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('editCommModal')">Cancel</button>
      <button class="btn-primary" onclick="submitEditCommunity()">Save Changes</button>
    </div>
  </div>
</div>

<!-- ========== SCRIPTS ========== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  /* ── Dropdown Toggle ── */
  window.toggleDropdown = function(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var isShown = el.classList.contains('show');
    document.querySelectorAll('.dropdown.show').forEach(function(d) {
      d.classList.remove('show');
    });
    if (!isShown) {
      el.classList.add('show');
    }
  };

  /* Close dropdowns when clicking outside */
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.nav-actions')) {
      document.querySelectorAll('.dropdown.show').forEach(function(d) {
        d.classList.remove('show');
      });
    }
  });

  /* ── Modal Functions ── */
  window.openModal = function(id) {
    var el = document.getElementById(id);
    if (el) {
      el.classList.add('show');
      document.body.style.overflow = 'hidden';
    } else {
      console.error('openModal: element not found:', id);
    }
  };

  window.closeModal = function(id) {
    var el = document.getElementById(id);
    if (el) {
      el.classList.remove('show');
      document.body.style.overflow = '';
    }
  };

  /* Close modal on backdrop click */
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) {
        closeModal(overlay.id);
      }
    });
  });

  /* Close modal on Escape key */
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.show').forEach(function(modal) {
        closeModal(modal.id);
      });
    }
  });

  /* ── Tabs ── */
  window.switchTab = function(tab, btn) {
    document.querySelectorAll('.community-panel').forEach(function(p) { p.classList.remove('active'); });
    var panel = document.getElementById(tab + '-panel');
    if (panel) panel.classList.add('active');
    document.querySelectorAll('.community-tab').forEach(function(t) { t.classList.remove('active'); });
    if (btn) btn.classList.add('active');
  };

  /* ── Toast ── */
  window.showToast = function(msg, type) {
    type = type || 'success';
    var container = document.getElementById('toastContainer');
    var t = document.createElement('div');
    t.className = 'toast-msg' + (type === 'error' ? ' error' : '');
    t.innerHTML = '<i class=\"fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '\"></i> ' + msg;
    container.appendChild(t);
    setTimeout(function() {
      t.style.opacity = '0';
      t.style.transition = 'opacity 0.3s';
      setTimeout(function() { t.remove(); }, 300);
    }, 3000);
  };

  /* ── Join / Leave ── */
  window.toggleJoin = async function(id, btn) {
    var isJoined = btn.classList.contains('joined');
    var action = isJoined ? 'leave' : 'join';
    try {
      var form = new FormData();
      form.append('community_id', id);
      form.append('action', action);
      var res = await fetch('../api/users/communities/join-community.php', {
        method: 'POST', credentials: 'include', body: form
      });
      var data = await res.json();
      if (!data.success) throw new Error(data.error);
      btn.classList.toggle('joined');
      btn.innerHTML = isJoined
        ? '<i class=\"fas fa-plus\"></i> Join Community'
        : '<i class=\"fas fa-check\"></i> Joined';
      showToast(isJoined ? 'Left community' : 'Joined successfully!');
      setTimeout(function() { location.reload(); }, 800);
    } catch (err) {
      showToast(err.message, 'error');
    }
  };

  /* ── Like ── */
  window.toggleLike = function(postId, btn) {
    btn.classList.toggle('liked');
    var icon  = btn.querySelector('i');
    var count = btn.querySelector('span');
    if (btn.classList.contains('liked')) {
      icon.className = 'fas fa-heart';
      count.textContent = parseInt(count.textContent) + 1;
    } else {
      icon.className = 'far fa-heart';
      count.textContent = parseInt(count.textContent) - 1;
    }
  };

  /* ── Post modal helpers ── */
  window.openPostModalWithImage = function() {
    openModal('postModal');
    setTimeout(function() { document.getElementById('modalPostImage').click(); }, 100);
  };

  window.previewModalImage = function(input) {
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('modalPreviewImg').src = e.target.result;
        document.getElementById('modalImagePreview').style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    }
  };

  window.clearModalImage = function() {
    document.getElementById('modalPostImage').value = '';
    document.getElementById('modalImagePreview').style.display = 'none';
  };

  /* ── Submit post ── */
  window.submitPost = async function() {
    var ta       = document.getElementById('modalPostText');
    var text     = ta ? ta.value.trim() : '';
    var fileInput = document.getElementById('modalPostImage');
    var hasFile  = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (!text && !hasFile) { showToast('Write something or attach a photo first!', 'error'); return; }

    var btn = document.querySelector('#postModal .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Creating...'; }

    var form = new FormData();
    form.append('content', text);
    <?php if ($pageMode === 'detail'): ?>
    form.append('community_id', <?php echo $community_id; ?>);
    <?php endif; ?>
    if (hasFile) form.append('image', fileInput.files[0]);

    try {
      var res  = await fetch('../api/users/posts/create-post.php', { method: 'POST', credentials: 'include', body: form });
      var raw  = await res.text();
      var data;
      try { data = JSON.parse(raw); } catch(e) { data = null; }
      if (!res.ok || !data || !data.success) throw new Error((data && data.error) ? data.error : raw || res.statusText);
      showToast('Post shared! 🎉');
      closeModal('postModal');
      ta.value = '';
      fileInput.value = '';
      clearModalImage();
      setTimeout(function() { location.reload(); }, 500);
    } catch (err) {
      showToast('Error: ' + err.message, 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = '+ Create Post'; }
    }
  };

  /* ── Submit create community ── */
  window.submitCreateCommunity = async function() {
    var name    = document.getElementById('createCommName').value.trim();
    var desc    = document.getElementById('createCommDesc').value.trim();
    var college = document.getElementById('createCommCollege').value;

    if (!name)    { showToast('Community name is required', 'error'); document.getElementById('createCommName').focus(); return; }
    if (!college) { showToast('Please select a college', 'error'); document.getElementById('createCommCollege').focus(); return; }

    var btn = document.querySelector('#createCommModal .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Creating...'; }

    try {
      var res  = await fetch('../api/users/communities/create-community.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ name: name, description: desc, college: college })
      });
      var data = await res.json();
      if (!data.success) throw new Error(data.error || 'Creation failed');
      showToast('Community created! 🎉');
      closeModal('createCommModal');
      document.getElementById('createCommName').value = '';
      document.getElementById('createCommDesc').value = '';
      document.getElementById('createCommCollege').value = '';
      setTimeout(function() { location.reload(); }, 500);
    } catch (err) {
      showToast('Error: ' + err.message, 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Create Community'; }
    }
  };

  /* ── Submit edit community ── */
  window.submitEditCommunity = async function() {
    var name    = document.getElementById('editCommName').value.trim();
    var desc    = document.getElementById('editCommDesc').value.trim();
    var college = document.getElementById('editCommCollege').value;

    if (!name) { showToast('Community name is required', 'error'); return; }

    var btn = document.querySelector('#editCommModal .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

    try {
      var res  = await fetch('../api/users/communities/edit-community.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          community_id: <?php echo (int)$community_id; ?>,
          name: name,
          description: desc,
          college: college
        })
      });
      var data = await res.json();
      if (!data.success) throw new Error(data.error || 'Update failed');
      showToast('Community updated!');
      closeModal('editCommModal');
      setTimeout(function() { location.reload(); }, 500);
    } catch (err) {
      showToast('Error: ' + err.message, 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Save Changes'; }
    }
  };

  /* ── Delete Community ── */
  window.confirmDeleteCommunity = function() {
    openModal('deleteCommModal');
  };

  window.submitDeleteCommunity = async function() {
    var btn = document.querySelector('#deleteCommModal .btn-primary');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> Deleting...'; }

    try {
      var res = await fetch('../api/users/communities/delete-community.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          community_id: <?php echo (int)$community_id; ?>
        })
      });
      var data = await res.json();
      if (!data.success) throw new Error(data.error || 'Delete failed');
      showToast('Community deleted successfully');
      closeModal('deleteCommModal');
      setTimeout(function() { window.location.href = 'community.php'; }, 800);
    } catch (err) {
      showToast('Error: ' + err.message, 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class=\"fas fa-trash\"></i> Yes, Delete'; }
    }
  };

});
</script>

</body>
</html>