<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.html");
    exit();
}

// Find db.php
$paths = [
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../../../config/db.php',
];

$configLoaded = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) die('db.php not found');
$db = $conn ?? $pdo ?? null;
if (!$db) die('No DB connection');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch community
$community_id = intval($_GET['id'] ?? 0);
if (!$community_id) {
    header('Location: communities.php');
    exit;
}

// Fetch community
$stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name, u.profile_picture as creator_pic 
    FROM communities c 
    LEFT JOIN users u ON c.created_by = u.user_id 
    WHERE c.community_id = ?");
$stmt->execute([$community_id]);
$community = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$community) {
    header('Location: communities.php');
    exit;
}

$creator_name = trim(($community['first_name'] ?? '') . ' ' . ($community['last_name'] ?? ''));
if (!$creator_name) $creator_name = 'Unknown';

// Check membership
$memberStmt = $db->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
$memberStmt->execute([$community_id, $user_id]);
$myRole = $memberStmt->fetchColumn();

// FIX: Cast both to strings for reliable comparison
$isCreator = ((string)$community['created_by'] === (string)$user_id);
$isMember = (bool)$myRole || $isCreator;
$isAdmin = ($myRole === 'admin') || $isCreator;

// Stats
$countStmt = $db->prepare("SELECT COUNT(*) FROM community_members WHERE community_id = ?");
$countStmt->execute([$community_id]);
$memberCount = $countStmt->fetchColumn();

$postCountStmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE community_id = ? AND is_deleted = 0");
$postCountStmt->execute([$community_id]);
$postCount = $postCountStmt->fetchColumn();

// Get members
$membersStmt = $db->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.profile_picture, cm.role, cm.joined_at
    FROM community_members cm
    JOIN users u ON cm.user_id = u.user_id
    WHERE cm.community_id = ?
    ORDER BY CASE WHEN cm.role = 'admin' THEN 0 ELSE 1 END, cm.joined_at DESC
    LIMIT 30
");
$membersStmt->execute([$community_id]);
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get posts
$postsStmt = $db->prepare("
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

// Current user for sidebar
$currentStmt = $db->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = ?");
$currentStmt->execute([$user_id]);
$currentUser = $currentStmt->fetch(PDO::FETCH_ASSOC);
$currentName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
$currentPic = !empty($currentUser['profile_picture']) ? '../uploads/profiles/' . $currentUser['profile_picture'] : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($currentUser['first_name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?php echo htmlspecialchars($community['name']); ?> — FeedSpace</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="../css/base.css"/>
<link rel="stylesheet" href="../css/feed.css"/>
<style>
/* ── COMMUNITY HEADER (matches profile-header exactly) ── */
.community-header {
  background: var(--color-white);
  border: 2px solid var(--color-border);
  border-radius: 22px;
  overflow: hidden;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
}

/* Banner - clean gradient only */
.community-banner {
  width: 100%;
  height: 150px;
  background: linear-gradient(135deg, #355872, #7AAACE, #9CD5FF);
  position: relative;
}

/* Info row - matches profile-info-row */
.community-info-row {
  display: flex;
  align-items: flex-end;
  gap: 24px;
  padding: 0 32px 24px;
  position: relative;
  margin-top: 10px;
}

/* Avatar - matches profile-avatar-wrap */
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

/* Text section */
.community-text { flex: 1; padding-bottom: 8px; }
.community-text h2 {
  font-family: 'Poppins', sans-serif;
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--color-text);
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.community-badge {
  font-size: 0.7rem;
  background: var(--color-light);
  color: var(--color-dark);
  padding: 4px 12px;
  border-radius: 999px;
  font-weight: 800;
}
.community-college {
  font-size: 0.9rem;
  color: var(--color-mid);
  font-weight: 700;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Stats - matches profile-stats-row */
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

/* Actions - right side */
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

/* Bio */
.community-bio {
  padding: 0 32px 20px;
  font-size: 0.95rem;
  color: var(--color-subtext);
  line-height: 1.6;
}

/* Meta */
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

/* ── TABS ── */
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

/* ── CREATE POST ── */
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
.create-post-input:hover { background: #e8e8ec; }
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
.create-post-action:hover { background: var(--color-cream); }

/* ── MEMBERS ── */
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
.member-item-info { flex: 1; }
.member-item-name { font-weight: 800; font-size: 0.95rem; }
.member-item-role {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 3px 12px;
  border-radius: 999px;
  margin-top: 4px;
  text-transform: uppercase;
}
.role-admin { background: linear-gradient(135deg, #fff3cd, #ffeaa7); color: #856404; }
.role-moderator { background: linear-gradient(135deg, #d1ecf1, #74b9ff); color: #0c5460; }
.role-member { background: var(--color-cream); color: var(--color-subtext); }

/* ── PANELS ── */
.community-panel { display: none; }
.community-panel.active { display: block; }

/* ── EMPTY ── */
.empty-community {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-subtext);
  background: var(--color-white);
  border-radius: 16px;
  border: 2px dashed var(--color-border);
}
.empty-community i { font-size: 3rem; margin-bottom: 16px; opacity: 0.3; display: block; }
.empty-community h3 { color: var(--color-text); margin-bottom: 8px; font-weight: 800; }
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
      <i class="fas fa-bell"></i><span class="badge" id="notifBadge">3</span>
    </button>
    <div class="dropdown" id="notifDropdown">
      <div class="dropdown-header">Notifications</div>
      <div id="notifList"></div>
    </div>
    <div class="dropdown" id="settingsDropdown">
      <div class="dropdown-header">Settings</div>
      <div class="dropdown-item" onclick="window.location.href='profile.php'"><i class="fas fa-user-edit"></i> My Profile</div>
      <div class="dropdown-item" onclick="window.location.href='communities.php'"><i class="fas fa-users"></i> Communities</div>
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
    <img src="<?php echo htmlspecialchars($currentPic); ?>" alt="Profile" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
    <span class="sidebar-profile-name"><?php echo htmlspecialchars($currentName); ?></span>
  </a>
  <div class="sidebar-divider"></div>
  <nav class="sidebar-nav">
    <a href="feed-view.php"><i class="fas fa-home"></i><span>Feed</span></a>
    <a href="announcements.html"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
    <a href="communities.php" class="active"><i class="fas fa-users"></i><span>Communities</span></a>
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

  <!-- ══ COMMUNITY HEADER ══ -->
  <div class="community-header">

    <!-- Banner -->
    <div class="community-banner"></div>

    <!-- Info row -->
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
      
      <!-- /.community-text -->
     <div class="community-actions">
  <?php if ($isAdmin): ?>
    <span class="btn-admin-badge"><i class="fas fa-crown"></i> You are Admin</span>
    <button class="btn-join-community" onclick="openEditModal()" style="margin-top:8px;">
      <i class="fas fa-pen"></i> Edit Community
    </button>
  <?php elseif ($isMember): ?>

          <button class="btn-join-community joined" onclick="toggleJoin(<?php echo $community_id; ?>, this)">
            <i class="fas fa-check"></i> Joined
          </button>
        <?php else: ?>
          <button class="btn-join-community" onclick="toggleJoin(<?php echo $community_id; ?>, this)">
            <i class="fas fa-plus"></i> Join Community
          </button>
        <?php endif; ?>
      </div><!-- /.community-actions -->
    </div><!-- /.community-info-row -->

    <!-- Bio -->
    <?php if ($community['description']): ?>
      <div class="community-bio"><?php echo nl2br(htmlspecialchars($community['description'])); ?></div>
    <?php endif; ?>

    <!-- Meta -->
    <div class="community-meta">
      <span><i class="fas fa-user"></i> Created by <?php echo htmlspecialchars($creator_name); ?></span>
      <span><i class="fas fa-calendar"></i> <?php echo date('M Y', strtotime($community['created_at'])); ?></span>
    </div>

  </div><!-- /.community-header -->

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
        <img src="<?php echo htmlspecialchars($currentPic); ?>" class="create-post-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
        <input type="text" class="create-post-input" placeholder="Write something in <?php echo htmlspecialchars($community['name']); ?>..." onclick="showToast('Create post coming soon!')">
      </div>
      <div class="create-post-divider"></div>
      <div class="create-post-actions">
        <button class="create-post-action" onclick="showToast('Photo upload coming soon!')">
          <i class="fas fa-image" style="color:#45bd62;"></i> Photo
        </button>
        <button class="create-post-action" onclick="showToast('Create post coming soon!')">
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
        $pic = !empty($post['profile_picture']) ? '../uploads/profiles/' . $post['profile_picture'] : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($post['first_name'] ?? 'User');
        $postImg = null;
        if (!empty($post['file_url'])) {
          $postImg = preg_match('#^https?://#i', $post['file_url']) ? $post['file_url'] : '../uploads/posts/' . $post['file_url'];
        }
      ?>
      <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
        <div class="post-header">
          <img src="<?php echo htmlspecialchars($pic); ?>" class="post-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
          <div class="post-meta">
            <span class="post-author"><?php echo htmlspecialchars(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')); ?></span>
            <span class="post-time"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
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
  </div><!-- /#posts-panel -->

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
        $mPic = !empty($m['profile_picture']) ? '../uploads/profiles/' . $m['profile_picture'] : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($m['first_name'] ?? 'User');
      ?>
      <div class="member-item">
        <img src="<?php echo htmlspecialchars($mPic); ?>" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
        <div class="member-item-info">
          <div class="member-item-name"><?php echo htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')); ?></div>
          <span class="member-item-role role-<?php echo $m['role']; ?>"><?php echo ucfirst($m['role']); ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div><!-- /#members-panel -->

</div><!-- /.content-center -->
</main><!-- /.main-content -->
</div><!-- /.app-body -->

<div id="toastContainer"></div>

<script src="../js/base.js"></script>
<script>
function switchTab(tab, btn) {
  document.querySelectorAll('.community-panel').forEach(p => p.classList.remove('active'));
  document.getElementById(tab + '-panel').classList.add('active');
  document.querySelectorAll('.community-tab').forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');
}

function showToast(msg, type) {
  type = type || 'success';
  var t = document.createElement('div');
  t.className = 'toast-message toast-' + type;
  t.innerHTML = '<i class="fas fa-' + (type==='success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
  t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:' + (type==='success'?'#4caf7d':'#e05263') + ';color:white;padding:12px 24px;border-radius:999px;font-weight:700;z-index:9999;animation:toastIn 0.3s;';
  document.body.appendChild(t);
  setTimeout(function(){
    t.style.opacity = '0';
    setTimeout(function(){ t.remove(); }, 300);
  }, 3000);
}

async function toggleJoin(id, btn) {
  const isJoined = btn.classList.contains('joined');
  const action = isJoined ? 'leave' : 'join';
  try {
    const form = new FormData();
    form.append('community_id', id);
    form.append('action', action);
    const res = await fetch('../api/users/communities/join-community.php', {
      method: 'POST', credentials: 'include', body: form
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);
    btn.classList.toggle('joined');
    btn.innerHTML = isJoined ? '<i class="fas fa-plus"></i> Join Community' : '<i class="fas fa-check"></i> Joined';
    showToast(isJoined ? 'Left community' : 'Joined successfully!');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    showToast(err.message, 'error');
  }
}

function toggleLike(postId, btn) {
  btn.classList.toggle('liked');
  const icon = btn.querySelector('i');
  const count = btn.querySelector('span');
  if (btn.classList.contains('liked')) {
    icon.className = 'fas fa-heart';
    count.textContent = parseInt(count.textContent) + 1;
  } else {
    icon.className = 'far fa-heart';
    count.textContent = parseInt(count.textContent) - 1;
  }
}
</script>

<!-- EDIT COMMUNITY MODAL -->
<div class="modal-overlay" id="editCommModal">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fas fa-pen"></i> Edit Community</h3>
      <button class="modal-close" onclick="closeModal('editCommModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Community Name *</label>
        <input type="text" id="editCommName" class="form-input" value="<?php echo htmlspecialchars($community['name']); ?>"/>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="editCommDesc" class="form-textarea"><?php echo htmlspecialchars($community['description'] ?? ''); ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">College / Program *</label>
        <select id="editCommCollege" class="form-select">
          <option value="">— Select —</option>
          <option value="College of Computer Studies" <?php echo ($community['college'] ?? '') === 'College of Computer Studies' ? 'selected' : ''; ?>>College of Computer Studies</option>
          <option value="College of Arts and Sciences" <?php echo ($community['college'] ?? '') === 'College of Arts and Sciences' ? 'selected' : ''; ?>>College of Arts and Sciences</option>
          <option value="College of Business Administration and Accountancy" <?php echo ($community['college'] ?? '') === 'College of Business Administration and Accountancy' ? 'selected' : ''; ?>>College of Business Administration and Accountancy</option>
          <option value="College of Engineering" <?php echo ($community['college'] ?? '') === 'College of Engineering' ? 'selected' : ''; ?>>College of Engineering</option>
          <option value="College of Criminal Justice Education" <?php echo ($community['college'] ?? '') === 'College of Criminal Justice Education' ? 'selected' : ''; ?>>College of Criminal Justice Education</option>
          <option value="College of Teacher Education" <?php echo ($community['college'] ?? '') === 'College of Teacher Education' ? 'selected' : ''; ?>>College of Teacher Education</option>
          <option value="College of Industrial Technology" <?php echo ($community['college'] ?? '') === 'College of Industrial Technology' ? 'selected' : ''; ?>>College of Industrial Technology</option>
          <option value="College of International Hospitality and Tourism Management" <?php echo ($community['college'] ?? '') === 'College of International Hospitality and Tourism Management' ? 'selected' : ''; ?>>College of International Hospitality and Tourism Management</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('editCommModal')">Cancel</button>
      <button class="btn-primary" onclick="submitEditCommunity()">Save Changes</button>
    </div>
  </div>
</div>

</body>
</html>