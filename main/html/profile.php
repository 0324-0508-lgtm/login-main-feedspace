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

$profilePic = $user['profile_picture'] ?? 'default.png';
if (strpos($profilePic, 'http') !== 0 && strpos($profilePic, 'data:') !== 0) {
    $profilePic = '../uploads/profile/' . $profilePic;
}

// ── Stats ─────────────────────────────────────────────────────
$postCountStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$postCountStmt->execute([$profileUserId]);
$postCount = $postCountStmt->fetchColumn();

$likesStmt = $pdo->prepare("
    SELECT COUNT(*) FROM post_likes pl
    JOIN posts p ON pl.post_id = p.post_id
    WHERE p.user_id = ?
");
$likesStmt->execute([$profileUserId]);
$totalLikes = $likesStmt->fetchColumn();

$viewsStmt = $pdo->prepare("SELECT COUNT(*) FROM profile_views WHERE viewed_user_id = ?");
$viewsStmt->execute([$profileUserId]);
$profileViews = (int) $viewsStmt->fetchColumn();
$viewsStmt->execute([$profileUserId]);
$viewsRow     = $viewsStmt->fetch(PDO::FETCH_ASSOC);
$profileViews = $viewsRow ? $viewsRow['views_count'] : 0;

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
/* Modal styles */
#profilePostModal .modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}
#profilePostModal .modal-overlay.show {
  display: flex;
}
#profilePostModal .modal {
  background: var(--color-white);
  border-radius: 16px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
#profilePostModal .modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border);
}
#profilePostModal .modal-body {
  padding: 16px 20px;
  flex: 1;
  overflow-y: auto;
}
#profilePostModal .modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 20px;
  border-top: 1px solid var(--color-border);
}
#profilePostModal textarea {
  width: 100%;
  border: none;
  resize: vertical;
  font-family: inherit;
  font-size: 1rem;
  outline: none;
  min-height: 100px;
}
#profilePostImagePreview {
  display: none;
  margin: 10px 0;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  border: 1px solid var(--color-border);
}
#profilePostImagePreview img {
  width: 100%;
  max-height: 300px;
  object-fit: cover;
  display: block;
}
#profilePostImagePreview button {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(0,0,0,0.6);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 0.9rem;
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
    <img src="<?php echo $profilePic; ?>" alt="Profile" id="sidebarAvatar"
         onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
    <span class="sidebar-profile-name" id="sidebarProfileName"><?php echo $displayName; ?></span>
  </a>
  <div class="sidebar-divider"></div>
  <nav class="sidebar-nav">
    <a href="feed-view.php" class=""><i class="fas fa-home"></i><span>Feed</span></a>
    <a href="announcements.html"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
    <a href="communities.php"><i class="fas fa-users"></i><span>Communities</span></a>
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

    <!-- Banner -->
    <?php if ($isOwnProfile): ?>
    <div class="profile-banner"
         style="background-image: url('https://images.unsplash.com/photo-1579546929518-9e396f3cc809?w=1200&q=80');"
         onclick="document.getElementById('bannerFileInput').click()">
    <?php else: ?>
    <div class="profile-banner"
         style="background-image: url('https://images.unsplash.com/photo-1579546929518-9e396f3cc809?w=1200&q=80');">
    <?php endif; ?>
      <?php if ($isOwnProfile): ?>
        <div class="banner-edit-hint"><i class="fas fa-camera"></i> Change Cover Photo</div>
      <?php endif; ?>
      <div class="profile-role-badge"><?php echo $role; ?></div>
    </div>
    <?php if ($isOwnProfile): ?>
      <input type="file" id="bannerFileInput" accept="image/*" onchange="uploadBanner(this)"/>
    <?php endif; ?>

    <!-- Info row: avatar | name+stats | action button -->
    <div class="profile-info-row">

      <!-- Avatar -->
      <?php if ($isOwnProfile): ?>
      <div class="profile-avatar-wrap" onclick="document.getElementById('avatarFileInput').click()">
      <?php else: ?>
      <div class="profile-avatar-wrap">
      <?php endif; ?>
        <img src="<?php echo $profilePic; ?>" class="profile-avatar" id="profileAvatar" alt="Profile"
             onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
        <?php if ($isOwnProfile): ?>
          <div class="avatar-edit-overlay"><i class="fas fa-camera"></i></div>
        <?php endif; ?>
      </div>
      <?php if ($isOwnProfile): ?>
        <input type="file" id="avatarFileInput" accept="image/*" onchange="uploadAvatar(this)"/>
      <?php endif; ?>

      <!-- Text -->
      <div class="profile-text">

        <!-- Full name + verified tick -->
        <h2 id="profileName">
          <?php echo $displayName; ?>
          <i class="fas fa-check-circle verified-badge" title="Verified"></i>
        </h2>

        <!-- College -->
        <?php if ($college): ?>
          <div class="profile-college"><i class="fas fa-graduation-cap"></i> <?php echo $college; ?></div>
        <?php endif; ?>

        <!-- Stats: posts · likes · views -->
        <div class="profile-stats-row" id="profileStats">
          <span class="stat-item" data-stat="posts">
            <strong><?php echo number_format($postCount); ?></strong>
            <span class="stat-label">Posts</span>
          </span>
          <span class="stat-divider"></span>
          <span class="stat-item" data-stat="likes">
            <strong><?php echo number_format($totalLikes); ?></strong>
            <span class="stat-label">Likes</span>
          </span>
          <span class="stat-divider"></span>
          <span class="stat-item" data-stat="views">
            <strong><?php echo number_format($profileViews); ?></strong>
            <span class="stat-label">Views</span>
          </span>
        </div>

      </div><!-- /.profile-text -->

      <!-- Action button -->
      <div class="profile-actions">
        <?php if ($isOwnProfile): ?>
          <button class="btn-edit-profile" onclick="window.location.href='edit-profile.php'">
            <i class="fas fa-pen"></i> Edit Profile
          </button>
        <?php else: ?>
          <button class="btn-follow <?php echo $isFollowing ? 'following' : ''; ?>"
                  onclick="toggleFollow(this, '<?php echo $profileUserId; ?>')">
            <i class="fas <?php echo $isFollowing ? 'fa-user-check' : 'fa-user-plus'; ?>"></i>
            <span><?php echo $isFollowing ? 'Following' : 'Follow'; ?></span>
          </button>
        <?php endif; ?>
      </div>

    </div><!-- /.profile-info-row -->

    <!-- Bio -->
    <?php if ($bio): ?>
      <div class="profile-bio"><p><?php echo nl2br($bio); ?></p></div>
    <?php endif; ?>

    <!-- Meta -->
    <div class="profile-meta">
      <span><i class="fas fa-calendar-alt"></i> Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
      <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
    </div>

  </div><!-- /.profile-header -->

  <!-- ══ CREATE POST (only on own profile) ══ -->
    <!-- ══ CREATE POST (only on own profile) ══ -->
  <?php if ($isOwnProfile): ?>
  <div class="profile-create-post">
    <div class="pcp-top">
      <img src="<?php echo $profilePic; ?>" alt="Profile" class="pcp-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
      <div class="pcp-input-wrap" onclick="openProfilePostModal()">
        <span>What is on your mind, <?php echo htmlspecialchars($user['first_name']); ?>?</span>
      </div>
    </div>
    <div class="pcp-divider"></div>
    <div class="pcp-actions">
      <button class="pcp-action" onclick="openProfilePostModalWithImage()">
        <i class="fas fa-image" style="color:#45bd62;"></i> Photo
      </button>
      <button class="pcp-action" onclick="openProfilePostModal()">
        <i class="fas fa-pen" style="color:#1877f2;"></i> Text
      </button>
      <div style="flex:1;"></div>
      <button class="pcp-submit-btn" onclick="openProfilePostModal()" style="border-radius:8px;padding:8px 20px;font-size:0.9rem;">
        <i class="fas fa-paper-plane"></i> Create Post
      </button>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ POSTS SECTION ══ -->
  <div class="profile-section-header">
    <h3><i class="fas fa-stream"></i> Posts</h3>
    <span class="post-count-badge"><?php echo $postCount; ?> total</span>
  </div>

  <div class="posts-container" id="postsContainer" data-user-id="<?php echo $profileUserId; ?>">
    <div class="posts-loading"><i class="fas fa-spinner fa-spin"></i> Loading posts...</div>
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
          <div style="font-size:0.75rem;color:#657786;">Community</div>
        </div>
      </div>
      <button onclick="closeProfilePostModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#657786;"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:16px 20px;flex:1;overflow-y:auto;">
      <textarea id="profilePostText" placeholder="What is on your mind?" rows="5" style="width:100%;border:none;resize:vertical;font-family:inherit;font-size:1rem;outline:none;min-height:100px;"></textarea>
      <div id="profilePostImagePreview">
        <img src="" id="profilePreviewImg"/>
        <button onclick="clearProfilePostImage()"><i class="fas fa-times"></i></button>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../js/base.js"></script>
<script src="../js/notifications.js"></script>
<script src="../js/profile.js"></script>

<script>
const PROFILE_DATA = {
  userId: "<?php echo $profileUserId; ?>",
  isOwnProfile: <?php echo $isOwnProfile ? 'true' : 'false'; ?>,
  displayName: "<?php echo addslashes($displayName); ?>",
  profilePic: "<?php echo addslashes($profilePic); ?>"
};

// Profile Create Post Functions
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
  fd.append('action', 'create_post');
  if (hasFile) fd.append('image', fileInput.files[0]);

  fetch('../api/users/posts/create-post.php', {
    method: 'POST',
    credentials: 'include',
    body: fd
  })
  .then(function(r){ return r.text(); })
  .then(function(text){
    console.log('Raw response:', text);
    try {
      var res = JSON.parse(text);
      if (res.success) {
        showToast('Post created!');
        closeProfilePostModal();
        loadProfilePosts(1);
      } else {
        showToast(res.error || 'Post failed', 'error');
      }
    } catch(e) {
      console.error('JSON parse error:', e);
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
  var t = document.createElement('div');
  t.className = 'toast-message toast-' + type;
  t.innerHTML = '<i class="fas fa-' + (type==='success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(function(){
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(20px)';
    setTimeout(function(){ t.remove(); }, 300);
  }, 3000);
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
        document.getElementById('navbarAvatar').src  = res.url + t;
        document.getElementById('sidebarAvatar').src = res.url + t;
        showToast('Avatar updated!');
      } else { showToast(res.error || 'Upload failed', 'error'); }
    })
    .catch(function(){ showToast('Upload failed', 'error'); });
}

function uploadBanner(input) {
  if (!input.files || !input.files[0]) return;
  var fd = new FormData(); fd.append('banner', input.files[0]);
  showToast('Uploading cover...');
  fetch('../api/profile/upload-banner.php', { method:'POST', credentials:'include', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if (res.success) {
        document.getElementById ? 0 : null;
        document.querySelector('.profile-banner').style.backgroundImage = 'url(' + res.url + '?t=' + Date.now() + ')';
        showToast('Cover photo updated!');
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
      var countEl = document.getElementById('followersCount');
      var current = parseInt(countEl.textContent.replace(/,/g, ''), 10);
      if (isFollowing) {
        btn.classList.remove('following');
        btn.innerHTML = '<i class="fas fa-user-plus"></i> <span>Follow</span>';
        countEl.textContent = (current - 1).toLocaleString();
      } else {
        btn.classList.add('following');
        btn.innerHTML = '<i class="fas fa-user-check"></i> <span>Following</span>';
        countEl.textContent = (current + 1).toLocaleString();
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

document.addEventListener('click', function(e){
  if (!e.target.closest('.nav-actions')) {
    document.querySelectorAll('.dropdown').forEach(function(d){ d.classList.remove('show'); });
  }
});
</script>
</body>
</html>