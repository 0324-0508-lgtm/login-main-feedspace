<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/session.php';
$pdo = $conn;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$profileUserId = isset($_GET['id']) ? $_GET['id'] : $currentUserId;
$isOwnProfile = ($profileUserId === $currentUserId);

// Fetch user data
$stmt = $pdo->prepare("
    SELECT user_id, first_name, last_name, email, profile_picture, bio, role, college, created_at
    FROM users WHERE user_id = ?
");
$stmt->execute([$profileUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: feed-view.php');
    exit;
}

$displayName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
$bio         = htmlspecialchars($user['bio'] ?? '');
$role        = htmlspecialchars($user['role'] ?? 'Student');
$college     = htmlspecialchars($user['college'] ?? '');

$profilePic = $user['profile_picture'] ?? '';
if (empty($profilePic)) {
    $profilePic = 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($user['first_name'] ?? 'User');
} elseif (strpos($profilePic, 'http') !== 0 && strpos($profilePic, 'data:') !== 0) {
    $profilePic = '../../uploads/profiles/' . $profilePic;
}

// ── Stats ─────────────────────────────────────────────────────
$postCountStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND is_deleted = 0");
$postCountStmt->execute([$profileUserId]);
$postCount = $postCountStmt->fetchColumn();

$likesStmt = $pdo->prepare("
    SELECT COUNT(*) FROM post_likes pl
    JOIN posts p ON pl.post_id = p.post_id
    WHERE p.user_id = ? AND p.is_deleted = 0
");
$likesStmt->execute([$profileUserId]);
$totalLikes = $likesStmt->fetchColumn();

$viewsStmt = $pdo->prepare("SELECT COUNT(*) FROM profile_views WHERE viewed_user_id = ?");
$viewsStmt->execute([$profileUserId]);
$profileViews = (int) $viewsStmt->fetchColumn();

// ── Is the current user already following this profile? ───────
$isFollowing = false;
if (!$isOwnProfile) {
    $fchk = $pdo->prepare("SELECT 1 FROM followers WHERE follower_id = ? AND following_id = ?");
    $fchk->execute([$currentUserId, $profileUserId]);
    $isFollowing = (bool) $fchk->fetchColumn();
}

// ── Increment profile view ────────────────────────────────────
if (!$isOwnProfile) {
    $pdo->prepare("
        INSERT INTO profile_views (viewer_id, viewed_user_id) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP
    ")->execute([$currentUserId, $profileUserId]);
    $profileViews++;
}

// ── Fetch user's posts ────────────────────────────────────────
$postsStmt = $conn->prepare("
    SELECT p.*, 
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND moderation_status IN ('approved','flagged')) as comment_count,
        EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
    FROM posts p
    WHERE p.user_id = ? 
        AND p.is_deleted = 0 
        AND p.status = 'approved'
        AND p.ai_status != 'rejected'
    ORDER BY p.created_at DESC
    LIMIT 20
");
$postsStmt->execute([$currentUserId, $profileUserId]);
$profilePosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo $displayName; ?> — FeedSpace</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="../css/base.css"/>
<link rel="stylesheet" href="../css/feed.css"/>
<link rel="stylesheet" href="../css/profile.css"/>
<style>
.profile-create-post {
  background: var(--color-white);
  border: 2px solid var(--color-border);
  border-radius: 16px;
  padding: 16px 20px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
}
.pcp-top {
  display: flex;
  align-items: center;
  gap: 12px;
}
.pcp-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.pcp-input-wrap {
  flex: 1;
  background: var(--color-cream);
  border-radius: 999px;
  padding: 12px 18px;
  cursor: pointer;
  transition: background 0.2s;
}
.pcp-input-wrap:hover {
  background: #e8e8ec;
}
.pcp-input-wrap span {
  color: var(--color-mid);
  font-size: 0.95rem;
  font-weight: 600;
}
.pcp-divider {
  height: 1px;
  background: var(--color-border);
  margin: 14px 0;
}
.pcp-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pcp-action {
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
.pcp-action:hover {
  background: var(--color-cream);
}
.pcp-submit-btn {
  background: var(--color-dark);
  color: var(--color-white);
  border: none;
  border-radius: 999px;
  padding: 10px 24px;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}
.pcp-submit-btn:hover {
  background: var(--color-mid);
}
.pcp-submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.pcp-cancel-btn {
  background: none;
  border: 2px solid var(--color-border);
  color: var(--color-dark);
  border-radius: 999px;
  padding: 10px 20px;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}
.pcp-cancel-btn:hover {
  background: var(--color-cream);
}
.profile-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  padding: 0 4px;
}
.profile-section-header h3 {
  font-family: 'Poppins', sans-serif;
  font-weight: 800;
  font-size: 1.1rem;
  color: var(--color-dark);
  display: flex;
  align-items: center;
  gap: 8px;
}
.post-count-badge {
  background: var(--color-cream);
  color: var(--color-subtext);
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
}
.empty-posts {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-subtext);
  background: var(--color-white);
  border-radius: 16px;
  border: 2px dashed var(--color-border);
}
.empty-posts i {
  font-size: 3rem;
  margin-bottom: 16px;
  opacity: 0.3;
  display: block;
}
.empty-posts h3 {
  color: var(--color-text);
  margin-bottom: 8px;
  font-weight: 800;
}

/* ===== SHARED POST CARD ===== */
.shared-post-card {
    border: 1.5px solid var(--color-border);
    border-radius: 12px;
    background: var(--color-cream);
    margin-top: 10px;
    overflow: hidden;
    transition: background 0.15s ease;
}
.shared-post-card:hover {
    background: #e8e8ec;
}
.shared-post-card .sp-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px 8px 14px;
}
.shared-post-card .sp-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    background: #ddd;
}
.shared-post-card .sp-meta {
    display: flex;
    flex-direction: column;
}
.shared-post-card .sp-author {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--color-text);
}
.shared-post-card .sp-time {
    font-size: 0.76rem;
    color: var(--color-subtext);
    font-weight: 600;
}
.shared-post-card .sp-body {
    padding: 0 14px 10px 14px;
}
.shared-post-card .sp-content {
    font-size: 0.9rem;
    color: var(--color-text);
    line-height: 1.5;
    margin-bottom: 8px;
    word-break: break-word;
}
.shared-post-card .sp-image-wrap {
    width: 100%;
    border-radius: 8px;
    overflow: hidden;
    background: #ddd;
}
.shared-post-card .sp-image {
    width: 100%;
    max-height: 320px;
    object-fit: cover;
    display: block;
}
.shared-post-card .sp-unavailable {
    padding: 24px 14px;
    text-align: center;
    color: var(--color-subtext);
    font-size: 0.9rem;
    font-weight: 600;
}
.shared-post-card .sp-unavailable i {
    font-size: 1.4rem;
    margin-bottom: 6px;
    display: block;
    color: var(--color-mid);
}

/* ===== COMMENTS ===== */
.comment-section {
    border-top: 1px solid var(--color-border);
    padding: 12px 14px;
}
.comment-input-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}
.comment-input-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--color-cream);
    border: 2px solid var(--color-border);
    border-radius: 999px;
    padding: 6px 10px 6px 14px;
}
.comment-input-wrap input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-family: inherit;
    font-size: 0.84rem;
    color: var(--color-text);
}
.comment-input-wrap input::placeholder {
    color: var(--color-subtext);
}
.comment-send-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--color-dark);
    color: white;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s;
}
.comment-send-btn:hover {
    background: var(--color-mid);
}
.comments-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.comment-item {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.comment-item img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.comment-bubble {
    background: var(--color-cream);
    border-radius: 12px;
    padding: 8px 12px;
    flex: 1;
}
.comment-author {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--color-dark);
    margin-bottom: 2px;
}
.comment-text {
    font-size: 0.84rem;
    color: var(--color-text);
    line-height: 1.5;
}
</style>
</head>
<body>

<!-- ════════════════ NAVBAR ════════════════ -->
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
      <input type="text" placeholder="Search FeedSpace..."/>
    </div>
  </div>
  <div class="nav-actions">
    <button class="nav-icon-btn" onclick="toggleDropdown('notifDropdown')">
      <i class="fas fa-bell"></i><span class="badge" id="notifBadge"></span>
    </button>
    <div class="dropdown" id="notifDropdown">
      <div class="dropdown-header">Notifications</div>
      <div id="notifList"><div class="notif-item read"><div class="notif-dot read"></div><div><div class="notif-title">Loading…</div></div></div></div>
    </div>
    <div class="dropdown" id="settingsDropdown">
      <div class="dropdown-header">Settings</div>
      <div class="dropdown-item" onclick="window.location.href='profile.php'"><i class="fas fa-user-edit"></i> My Profile</div>
      <div class="dropdown-item" onclick="window.location.href='edit-profile.php'"><i class="fas fa-cog"></i> Edit Profile</div>
      <div class="dropdown-item danger" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete Account</div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item" onclick="window.location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> Log Out</div>
    </div>
  </div>
</header>

<!-- ════════════════ BODY ════════════════ -->
<div class="app-body">

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <a href="profile.php" class="sidebar-profile-entry" title="Go to profile">
    <img src="<?php echo $profilePic; ?>" alt="Profile" id="sidebarAvatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
    <span class="sidebar-profile-name" id="sidebarProfileName"><?php echo $displayName; ?></span>
  </a>
  <div class="sidebar-divider"></div>
  <nav class="sidebar-nav">
    <a href="feed-view.php"><i class="fas fa-home"></i><span>Feed</span></a>
    <a href="announcements.html"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
    <a href="community.php"><i class="fas fa-users"></i><span>Communities</span></a>
    <a href="help.html"><i class="fas fa-question-circle"></i><span>Help</span></a>
    <a href="about.html"><i class="fas fa-info-circle"></i><span>About</span></a>
  </nav>
  <div class="sidebar-bottom">
    <a href="../logout.php" class="sidebar-signout"><i class="fas fa-sign-out-alt"></i><span>Sign out</span></a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main-content">
<div class="content-center">

  <!-- ══ PROFILE CARD ══ -->
  <div class="profile-header">
    <div class="profile-banner" style="background-image: url('https://images.unsplash.com/photo-1579546929518-9e396f3cc809?w=1200&q=80');">
      <?php if ($isOwnProfile): ?>
        <div class="banner-edit-hint"><i class="fas fa-camera"></i> Change Cover Photo</div>
      <?php endif; ?>
      <div class="profile-role-badge"><?php echo $role; ?></div>
    </div>

    <div class="profile-info-row">
      <div class="profile-avatar-wrap" <?php echo $isOwnProfile ? 'onclick="document.getElementById(\'avatarFileInput\').click()"' : ''; ?>>
        <img src="<?php echo $profilePic; ?>" class="profile-avatar" id="profileAvatar" alt="Profile" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
        <?php if ($isOwnProfile): ?>
          <div class="avatar-edit-overlay"><i class="fas fa-camera"></i></div>
        <?php endif; ?>
      </div>
      <?php if ($isOwnProfile): ?>
        <input type="file" id="avatarFileInput" accept="image/*" onchange="uploadAvatar(this)" style="display:none;"/>
      <?php endif; ?>

      <div class="profile-text">
        <h2 id="profileName">
          <?php echo $displayName; ?>
          <i class="fas fa-check-circle verified-badge" title="Verified"></i>
        </h2>
        <?php if ($college): ?>
          <div class="profile-college"><i class="fas fa-graduation-cap"></i> <?php echo $college; ?></div>
        <?php endif; ?>
        <div class="profile-stats-row" id="profileStats">
          <span class="stat-item"><strong><?php echo number_format($postCount); ?></strong><span class="stat-label">Posts</span></span>
          <span class="stat-divider"></span>
          <span class="stat-item"><strong><?php echo number_format($totalLikes); ?></strong><span class="stat-label">Likes</span></span>
          <span class="stat-divider"></span>
          <span class="stat-item"><strong><?php echo number_format($profileViews); ?></strong><span class="stat-label">Views</span></span>
        </div>
      </div>

      <div class="profile-actions">
        <?php if ($isOwnProfile): ?>
          <button class="btn-edit-profile" onclick="window.location.href='edit-profile.php'"><i class="fas fa-pen"></i> Edit Profile</button>
        <?php else: ?>
          <button class="btn-follow <?php echo $isFollowing ? 'following' : ''; ?>" onclick="toggleFollow(this, '<?php echo $profileUserId; ?>')">
            <i class="fas <?php echo $isFollowing ? 'fa-user-check' : 'fa-user-plus'; ?>"></i>
            <span><?php echo $isFollowing ? 'Following' : 'Follow'; ?></span>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($bio): ?>
      <div class="profile-bio"><p><?php echo nl2br($bio); ?></p></div>
    <?php endif; ?>

    <div class="profile-meta">
      <span><i class="fas fa-calendar-alt"></i> Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
      <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
    </div>
  </div>

  <!-- ══ CREATE POST (only on own profile) ══ -->
  <?php if ($isOwnProfile): ?>
  <div class="profile-create-post">
    <div class="pcp-top">
      <img src="<?php echo $profilePic; ?>" alt="Profile" class="pcp-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
      <div class="pcp-input-wrap" onclick="openProfilePostModal()">
        <span>What's on your mind, <?php echo htmlspecialchars($user['first_name']); ?>?</span>
      </div>
    </div>
    <div class="pcp-divider"></div>
    <div class="pcp-actions">
      <button class="pcp-action" onclick="openProfilePostModalWithImage()"><i class="fas fa-image" style="color:#45bd62;"></i> Photo</button>
      <button class="pcp-action" onclick="openProfilePostModal()"><i class="fas fa-pen" style="color:#1877f2;"></i> Text</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ POSTS SECTION ══ -->
  <div class="profile-section-header">
    <h3><i class="fas fa-stream"></i> Posts</h3>
    <span class="post-count-badge"><?php echo $postCount; ?> total</span>
  </div>

  <div class="posts-container" id="postsContainer">
    <?php if (empty($profilePosts)): ?>
    <div class="empty-posts">
      <i class="fas fa-inbox"></i>
      <h3>No posts yet</h3>
      <p><?php echo $isOwnProfile ? 'Share your first post!' : 'This user hasn\'t shared anything yet.'; ?></p>
    </div>
    <?php else: ?>
      <?php foreach ($profilePosts as $post):
    $pic = !empty($post['file_url']) 
        ? (preg_match('#^https?://#i', $post['file_url']) ? $post['file_url'] : '../../uploads/posts/' . $post['file_url'])
        : null;
    
    // Handle shared post
    $sharedHtml = '';
    if (!empty($post['shared_post_id'])) {
        $origStmt = $conn->prepare("
            SELECT p.*, u.first_name, u.last_name, u.profile_picture as orig_profile_picture
            FROM posts p 
            JOIN users u ON p.user_id = u.user_id 
            WHERE p.post_id = ? AND p.is_deleted = 0
        ");
        $origStmt->execute([$post['shared_post_id']]);
        $orig = $origStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orig) {
            $origPic = !empty($orig['file_url']) 
                ? (preg_match('#^https?://#i', $orig['file_url']) ? $orig['file_url'] : '../../uploads/posts/' . $orig['file_url'])
                : null;
            $origAvatar = !empty($orig['orig_profile_picture'])
                ? '../../uploads/profiles/' . $orig['orig_profile_picture']
                : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($orig['first_name'] ?? 'User');
            
            $sharedHtml = '
            <div class="shared-post-card" onclick="window.location.href=\'feed-view.php?post=' . $orig['post_id'] . '\'" style="cursor:pointer;">
                <div class="sp-header">
                    <img src="' . htmlspecialchars($origAvatar) . '" class="sp-avatar" onerror="this.src=\'https://api.dicebear.com/7.x/adventurer/svg?seed=Default\'"/>
                    <div class="sp-meta">
                        <span class="sp-author">' . htmlspecialchars(($orig['first_name'] ?? '') . ' ' . ($orig['last_name'] ?? '')) . '</span>
                        <span class="sp-time">' . date('M d, Y', strtotime($orig['created_at'])) . '</span>
                    </div>
                </div>
                <div class="sp-body">
                    <p class="sp-content">' . htmlspecialchars($orig['content']) . '</p>
                    ' . ($origPic ? '<div class="sp-image-wrap"><img src="' . htmlspecialchars($origPic) . '" class="sp-image" onerror="this.style.display=\'none\'"/></div>' : '') . '
                </div>
            </div>';
        } else {
            $sharedHtml = '<div class="shared-post-card"><div class="sp-unavailable"><i class="fas fa-trash"></i> Original post unavailable</div></div>';
        }
    }
?>
<div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
    <div class="post-header">
        <img src="<?php echo htmlspecialchars($profilePic); ?>" class="post-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
        <div class="post-meta">
            <span class="post-author"><?php echo $displayName; ?></span>
            <span class="post-time"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
        </div>
    </div>
    <div class="post-body">
        <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
        <?php echo $sharedHtml; ?>
        <?php if ($pic && empty($post['shared_post_id'])): ?>
            <img src="<?php echo htmlspecialchars($pic); ?>" class="post-image" onerror="this.style.display='none'">
        <?php endif; ?>
    </div>
    <div class="post-footer">
        <button class="post-action-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" onclick="toggleLike(<?php echo $post['post_id']; ?>, this)">
            <i class="<?php echo $post['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
            <span><?php echo number_format($post['like_count']); ?></span>
        </button>
        <button class="post-action-btn" onclick="toggleComments(this)">
            <i class="far fa-comment"></i>
            <span><?php echo number_format($post['comment_count']); ?></span>
        </button>
        <button class="post-action-btn" onclick="openShareModal(this)">
            <i class="fas fa-share"></i>
            <span>Share</span>
        </button>
        <button class="post-action-btn report-btn" onclick="openReportModal(<?php echo $post['post_id']; ?>)">
            <i class="fas fa-exclamation-circle"></i>
        </button>
    </div>
    <!-- Comments Section -->
    <div class="comment-section" style="display:none;">
        <div class="comment-input-row">
            <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="User" style="width:30px;height:30px;border-radius:50%;object-fit:cover;"/>
            <div class="comment-input-wrap">
                <input type="text" placeholder="Write a comment..." onkeypress="if(event.key==='Enter') addComment(this)"/>
                <button class="comment-send-btn" onclick="addComment(this.previousElementSibling)"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
        <div class="comments-list"></div>
    </div>
</div>
<?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /.content-center -->
</main>
</div><!-- /.app-body -->

<!-- Create Post Modal -->
<div class="modal-overlay" id="profilePostModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:16px;width:90%;max-width:500px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;">
    <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--color-border);">
      <div style="display:flex;align-items:center;gap:10px;">
        <img src="<?php echo $profilePic; ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
        <div>
          <div style="font-weight:800;font-size:0.95rem;"><?php echo $displayName; ?></div>
          <div style="font-size:0.75rem;color:#657786;">Public</div>
        </div>
      </div>
      <button onclick="closeProfilePostModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#657786;"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:16px 20px;flex:1;overflow-y:auto;">
      <textarea id="profilePostText" placeholder="What's on your mind?" rows="5" style="width:100%;border:none;resize:vertical;font-family:inherit;font-size:1rem;outline:none;min-height:100px;"></textarea>
      <div id="profilePostImagePreview" style="display:none;margin:10px 0;border-radius:12px;overflow:hidden;position:relative;border:1px solid var(--color-border);">
        <img src="" id="profilePreviewImg" style="width:100%;max-height:300px;object-fit:cover;display:block;"/>
        <button onclick="clearProfilePostImage()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:0.9rem;"><i class="fas fa-times"></i></button>
      </div>
      <input type="file" id="profilePostImage" accept="image/*" style="display:none;" onchange="previewProfilePostImage(this)"/>
      <div style="margin-top:12px;">
        <button type="button" onclick="document.getElementById('profilePostImage').click()" style="width:100%;padding:10px;border:2px solid var(--color-border);border-radius:12px;background:#fff;cursor:pointer;font-family:inherit;font-weight:700;color:#657786;display:flex;align-items:center;justify-content:center;gap:8px;">
          <i class="fas fa-image" style="color:#45bd62;"></i> Add Photo
        </button>
      </div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:10px;padding:12px 20px;border-top:1px solid var(--color-border);">
      <button class="pcp-cancel-btn" onclick="closeProfilePostModal()">Cancel</button>
      <button class="pcp-submit-btn" id="profilePostSubmit" onclick="submitProfilePost()">Post</button>
    </div>
  </div>
</div>

<div id="toastContainer"></div>

<script src="../js/base.js"></script>
<script src="../js/notifications.js"></script>

<script>
// ===== LIKE =====
function toggleLike(postId, btn) {
    if (!btn) {
        btn = document.querySelector('.post-action-btn[data-post-id="' + postId + '"]');
    }
    if (!btn) return;
    
    fetch('../api/users/interactions/toggle-post-like.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
    })
    .then(r => r.json())
    .then(res => {
        if (!res || !res.success) throw new Error(res.error || 'Like failed');
        btn.classList.toggle('liked', res.liked);
        var icon = btn.querySelector('i');
        if (icon) icon.className = res.liked ? 'fas fa-heart' : 'far fa-heart';
        var span = btn.querySelector('span');
        if (span) span.textContent = String(res.likesCount || 0);
    })
    .catch(err => {
        console.error(err);
        showToast('Like failed', 'error');
    });
}

// ===== COMMENTS =====
function toggleComments(btn) {
    var card = btn.closest('.post-card');
    var section = card.querySelector('.comment-section');
    if (!section) return;
    var isHidden = section.style.display === 'none' || !section.style.display;
    section.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        loadComments(card.dataset.postId, section);
    }
}

function loadComments(postId, section) {
    fetch('../api/users/interactions/get-comments.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10), page: 1 })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.error || 'Failed to load comments');
        var list = section.querySelector('.comments-list');
        if (!list) return;
        list.innerHTML = '';
        
        if (!data.comments || !data.comments.length) {
            list.innerHTML = '<div style="text-align:center;color:var(--color-subtext);font-size:0.85rem;padding:12px;">No comments yet. Be the first!</div>';
            return;
        }
        
        data.comments.forEach(c => {
            var item = document.createElement('div');
            item.className = 'comment-item';
            item.innerHTML = '<img src="' + (c.profile_picture || 'https://api.dicebear.com/7.x/adventurer/svg?seed=Default') + '" onerror="this.src=\'https://api.dicebear.com/7.x/adventurer/svg?seed=Default\'"/><div class="comment-bubble"><div class="comment-author">' + escapeHtml(c.author || 'User') + '</div><div class="comment-text">' + escapeHtml(c.content) + '</div></div>';
            list.appendChild(item);
        });
    })
    .catch(err => {
        console.error(err);
        showToast('Failed to load comments', 'error');
    });
}

function addComment(input) {
    var wrap = input.closest('.comment-input-wrap');
    var text = input.value.trim();
    if (!text) {
        showToast('Write something first!', 'warning');
        return;
    }
    var card = input.closest('.post-card');
    var postId = card.dataset.postId;
    
    fetch('../api/users/interactions/add-comments.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10), content: text })
    })
    .then(r => r.json())
    .then(res => {
        if (!res || !res.success) throw new Error(res.error || 'Failed to add comment');
        showToast('Comment added!');
        input.value = '';
        loadComments(postId, input.closest('.comment-section'));
    })
    .catch(err => {
        console.error(err);
        showToast('Failed to add comment', 'error');
    });
}

// ===== SHARE =====
var _sharePostText = '';
function openShareModal(btn) {
    var card = btn.closest('.post-card');
    var postId = card && card.dataset ? card.dataset.postId : null;
    window.__pendingSharePostId = postId || null;
    var body = card.querySelector('.post-body > p');
    _sharePostText = body ? body.innerText : '';
    var preview = document.getElementById('sharePostPreview');
    if (preview) preview.textContent = _sharePostText.length > 80 ? _sharePostText.slice(0, 80) + '...' : _sharePostText;
    var ta = document.getElementById('shareText');
    if (ta) ta.value = '';
    
    // Update share modal user info
    var shareModal = document.getElementById('shareModal');
    if (shareModal) shareModal.classList.add('show');
}

function submitShare() {
    var text = document.getElementById('shareText');
    text = text ? text.value.trim() : '';
    
    if (!window.__pendingSharePostId) { 
        showToast('Error: No post selected', 'error'); 
        return; 
    }
    
    showToast('Sharing...');
    
    var form = new FormData();
    form.append('content', text);
    form.append('shared_post_id', window.__pendingSharePostId);
    
    fetch('../api/users/posts/create-post.php', {
        method: 'POST',
        credentials: 'include',
        body: form
    })
    .then(r => r.json())
    .then(res => {
        if (!res || !res.success) throw new Error(res.error || 'Share failed');
        showToast('Post shared!');
        closeModal('shareModal');
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(err => { 
        console.error(err); 
        showToast('Failed to share: ' + err.message, 'error'); 
    });
}

// ===== REPORT =====
function openReportModal(postId) {
    if (!postId) {
        console.error('[ERROR] No post ID for report');
        return;
    }
    document.getElementById('report-post-id').value = postId;
    document.getElementById('report-reason').value = '';
    document.getElementById('report-description').value = '';
    document.getElementById('report-reason-error').textContent = '';
    document.getElementById('report-desc-error').textContent = '';
    document.getElementById('reportModal').classList.add('show');
}

function submitReport() {
    const postId = document.getElementById('report-post-id').value;
    const reason = document.getElementById('report-reason').value;
    const description = document.getElementById('report-description').value.trim();
    
    document.getElementById('report-reason-error').textContent = '';
    document.getElementById('report-desc-error').textContent = '';
    
    if (!reason) {
        document.getElementById('report-reason-error').textContent = 'Please select a reason';
        document.getElementById('report-reason').focus();
        return;
    }
    
    if (reason === 'other' && !description) {
        document.getElementById('report-desc-error').textContent = 'Please provide details';
        document.getElementById('report-description').focus();
        return;
    }
    
    fetch('/login-main-feedspace/main/api/users/reports/report-post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId), reason: reason, description: description })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Report submitted successfully!', 'success');
            closeModal('reportModal');
        } else {
            throw new Error(data.error || 'Report failed');
        }
    })
    .catch(error => {
        console.error('[ERROR] Report failed:', error.message);
        showToast('Failed to submit report: ' + error.message, 'error');
    });
}

// ===== MODAL =====
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

function openProfilePostModal() {
    var modal = document.getElementById('profilePostModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    var ta = document.getElementById('profilePostText');
    if (ta) setTimeout(function(){ ta.focus(); }, 100);
}

function openProfilePostModalWithImage() {
    openProfilePostModal();
    setTimeout(function() {
        var fileInput = document.getElementById('profilePostImage');
        if (fileInput) fileInput.click();
    }, 200);
}

function closeProfilePostModal() {
    var modal = document.getElementById('profilePostModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    var ta = document.getElementById('profilePostText');
    if (ta) ta.value = '';
    clearProfilePostImage();
}

function previewProfilePostImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('profilePostImagePreview');
            var img = document.getElementById('profilePreviewImg');
            if (preview && img) {
                img.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearProfilePostImage() {
    var input = document.getElementById('profilePostImage');
    var preview = document.getElementById('profilePostImagePreview');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
}

function submitProfilePost() {
    var ta = document.getElementById('profilePostText');
    var text = ta ? ta.value.trim() : '';
    var fileInput = document.getElementById('profilePostImage');
    var hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (!text && !hasFile) {
        showToast('Write something or add a photo first!', 'error');
        return;
    }

    var btn = document.getElementById('profilePostSubmit');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Posting...';
    }

    var fd = new FormData();
    fd.append('content', text);
    if (hasFile) fd.append('image', fileInput.files[0]);

    fetch('../api/users/posts/create-post.php', {
        method: 'POST',
        credentials: 'include',
        body: fd
    })
    .then(function(r){ return r.text(); })
    .then(function(text){
        try {
            var res = JSON.parse(text);
            if (res.success) {
                showToast('Post created!');
                closeProfilePostModal();
                setTimeout(function(){ window.location.reload(); }, 500);
            } else {
                showToast(res.error || 'Post failed', 'error');
            }
        } catch(e) {
            console.error('JSON parse error:', e, text);
            showToast('Server error. Check console.', 'error');
        }
    })
    .catch(function(err){
        console.error('Fetch error:', err);
        showToast('Network error. Try again.', 'error');
    })
    .finally(function(){
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Post';
        }
    });
}

function toggleDropdown(id) {
    var d = document.getElementById(id);
    if (d) {
        document.querySelectorAll('.dropdown').forEach(function(x){ if(x.id!==id) x.classList.remove('show'); });
        d.classList.toggle('show');
    }
}

function showToast(msg, type) {
    type = type || 'success';
    var container = document.getElementById('toastContainer');
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:' + (type==='error'?'#e05263':type==='warning'?'#f5a623':'#4caf7d') + ';color:white;padding:12px 24px;border-radius:999px;font-weight:700;z-index:99999;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    t.innerHTML = '<i class="fas fa-' + (type==='success' ? 'check-circle' : type==='warning' ? 'exclamation-triangle' : 'exclamation-circle') + '"></i> ' + msg;
    container.appendChild(t);
    setTimeout(function(){
        t.style.opacity = '0';
        t.style.transition = 'opacity 0.3s';
        setTimeout(function(){ t.remove(); }, 300);
    }, 3000);
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function uploadAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var fd = new FormData(); fd.append('avatar', input.files[0]);
    showToast('Uploading avatar...');
    fetch('../api/profile/upload-avatar.php', { method:'POST', credentials:'include', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                var t = '?t=' + Date.now();
                document.getElementById('profileAvatar').src = res.url + t;
                document.getElementById('sidebarAvatar').src = res.url + t;
                showToast('Avatar updated!');
            } else { showToast(res.error || 'Upload failed', 'error'); }
        })
        .catch(function(){ showToast('Upload failed', 'error'); });
}

function toggleFollow(btn, userId) {
    var isFollowing = btn.classList.contains('following');
    var action = isFollowing ? 'unfollow' : 'follow';
    btn.disabled = true;
    fetch('../api/users/interactions/follow-user.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_user_id: userId, action: action })
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (res.success) {
            if (isFollowing) {
                btn.classList.remove('following');
                btn.innerHTML = '<i class="fas fa-user-plus"></i> <span>Follow</span>';
            } else {
                btn.classList.add('following');
                btn.innerHTML = '<i class="fas fa-user-check"></i> <span>Following</span>';
            }
            showToast(res.message || (isFollowing ? 'Unfollowed' : 'Following!'));
        } else { showToast(res.error || 'Action failed', 'error'); }
    })
    .catch(function(){ showToast('Action failed', 'error'); })
    .finally(function(){ btn.disabled = false; });
}

function confirmDelete() {
    if (confirm('Are you sure you want to delete your account? This cannot be undone!')) {
        fetch('../api/users/delete-account.php', { method:'POST', credentials:'include' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    showToast('Account deleted');
                    setTimeout(function(){ window.location.href = '../index.html'; }, 1500);
                } else { showToast(res.error || 'Delete failed', 'error'); }
            });
    }
}

// Close modals on backdrop click
document.addEventListener('click', function(e){
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
    if (!e.target.closest('.nav-actions')) {
        document.querySelectorAll('.dropdown').forEach(function(d){ d.classList.remove('show'); });
    }
});
</script>
</body>
</html>