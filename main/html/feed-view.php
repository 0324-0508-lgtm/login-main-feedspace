<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth guard
if (empty($_SESSION['user_id'])) {
    header('Location: ../../index.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// ========== CURRENT USER DATA ==========
$currentUserId    = $_SESSION['user_id'] ?? '';
$currentFirstName = $_SESSION['first_name'] ?? 'User';
$currentLastName  = $_SESSION['last_name'] ?? '';
$currentUserName  = trim($currentFirstName . ' ' . $currentLastName) ?: 'User';

$currentUserPic = $_SESSION['profile_picture'] ?? '';
if (empty($currentUserPic)) {
    $currentUserPic = 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($currentFirstName);
} elseif (strpos($currentUserPic, 'http') !== 0 && strpos($currentUserPic, 'data:') !== 0) {
    $currentUserPic = '../../uploads/profiles/' . $currentUserPic;
}
// =====================================

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ban-check.php';

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo 'Account banned';
    exit();
}

// ========== FETCH CURRENT USER FROM DATABASE ==========
$currentUserId = $_SESSION['user_id'] ?? '';

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
    $currentUserPic = '../../uploads/profiles/' . $currentUserPic;
}
// =====================================

$isApiRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isApiRequest && isset($_POST['action']) && $_POST['action'] === 'get_posts') {
    header('Content-Type: application/json');
    
    $page = max(1, intval($_POST['page'] ?? 1));
    $limit = min(10, max(1, intval($_POST['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $stmt = $conn->prepare("
        SELECT
            p.post_id,
            p.content,
            p.file_url,
            p.created_at,
            p.shared_post_id,
            u.first_name,
            u.last_name,
            u.profile_picture,
            -- Original post data (if shared)
            op.post_id AS orig_post_id,
            op.content AS orig_content,
            op.file_url AS orig_file_url,
            op.created_at AS orig_created_at,
            ou.first_name AS orig_first_name,
            ou.last_name AS orig_last_name,
            ou.profile_picture AS orig_profile_picture
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN posts op ON p.shared_post_id = op.post_id AND op.is_deleted = 0
        LEFT JOIN users ou ON op.user_id = ou.user_id
        LEFT JOIN user_bans b ON u.user_id = b.user_id
            AND (b.expires_at > NOW() OR b.expires_at IS NULL)
        LEFT JOIN user_bans ob ON ou.user_id = ob.user_id
            AND (ob.expires_at > NOW() OR ob.expires_at IS NULL)
        WHERE p.is_deleted = 0
            AND p.deleted_at IS NULL
            AND p.is_archived = 0
            AND p.status = 'approved'
            AND p.ai_status != 'rejected'
            AND b.id IS NULL
            AND (p.shared_post_id IS NULL OR (op.post_id IS NOT NULL AND ob.id IS NULL))
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($posts as &$row) {
        $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $row['profile_picture'] = !empty($row['profile_picture'])
            ? '../../uploads/profiles/' . $row['profile_picture']
            : '../assets/default.jpg';
        
        if (!empty($row['file_url'])) {
            $row['image'] = preg_match('#^https?://#i', $row['file_url'])
                ? $row['file_url']
                : '../../uploads/posts/' . $row['file_url'];
        } else {
            $row['image'] = null;
        }
        
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        
        // Process original post data for shared posts
        if (!empty($row['shared_post_id'])) {
            $row['is_shared'] = true;
            $row['original'] = [
                'post_id' => $row['orig_post_id'],
                'content' => $row['orig_content'],
                'full_name' => trim(($row['orig_first_name'] ?? '') . ' ' . ($row['orig_last_name'] ?? '')),
                'profile_picture' => !empty($row['orig_profile_picture'])
                    ? '../../uploads/profiles/' . $row['orig_profile_picture']
                    : '../assets/default.jpg',
                'created_at' => date('M d, Y H:i', strtotime($row['orig_created_at'] ?? 'now')),
                'image' => null
            ];
            if (!empty($row['orig_file_url'])) {
                $row['original']['image'] = preg_match('#^https?://#i', $row['orig_file_url'])
                    ? $row['orig_file_url']
                    : '../../uploads/posts/' . $row['orig_file_url'];
            }
        } else {
            $row['is_shared'] = false;
        }
    }
    
    echo json_encode(['success' => true, 'posts' => $posts]);
    exit();
}

// Fetch posts for server-side rendering
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(10, max(1, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("
    SELECT
        p.post_id,
        p.content,
        p.file_url,
        p.created_at,
        p.shared_post_id,
        u.first_name,
        u.last_name,
        u.profile_picture,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
        EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = ?) AS user_liked,
        -- Original post data (if shared)
        op.post_id AS orig_post_id,
        op.content AS orig_content,
        op.file_url AS orig_file_url,
        op.created_at AS orig_created_at,
        ou.first_name AS orig_first_name,
        ou.last_name AS orig_last_name,
        ou.profile_picture AS orig_profile_picture
     FROM posts p
     JOIN users u ON p.user_id = u.user_id
     LEFT JOIN posts op ON p.shared_post_id = op.post_id AND op.is_deleted = 0
     LEFT JOIN users ou ON op.user_id = ou.user_id
     LEFT JOIN user_bans b ON u.user_id = b.user_id
        AND (b.expires_at > NOW() OR b.expires_at IS NULL)
     LEFT JOIN user_bans ob ON ou.user_id = ob.user_id
        AND (ob.expires_at > NOW() OR ob.expires_at IS NULL)
     WHERE p.is_deleted = 0
        AND p.deleted_at IS NULL
        AND p.is_archived = 0
        AND p.status = 'approved'
        AND p.ai_status != 'rejected'
        AND b.id IS NULL
        AND (p.shared_post_id IS NULL OR (op.post_id IS NOT NULL AND ob.id IS NULL))
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?
");

$stmt->bindValue(1, $user_id, PDO::PARAM_STR);
$stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
$stmt->execute();

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($posts as &$row) {
    $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $row['profile_picture'] = !empty($row['profile_picture'])
        ? '../../uploads/profiles/' . $row['profile_picture']
        : '../assets/default.jpg';
    
    if (!empty($row['file_url'])) {
        $row['image'] = preg_match('#^https?://#i', $row['file_url'])
            ? $row['file_url']
            : '../../uploads/posts/' . $row['file_url'];
    } else {
        $row['image'] = null;
    }
    
    $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
    $row['user_liked'] = !empty($row['user_liked']);
    
    // Process original post data for shared posts
    if (!empty($row['shared_post_id'])) {
        $row['is_shared'] = true;
        $row['original'] = [
            'post_id' => $row['orig_post_id'],
            'content' => $row['orig_content'],
            'full_name' => trim(($row['orig_first_name'] ?? '') . ' ' . ($row['orig_last_name'] ?? '')),
            'profile_picture' => !empty($row['orig_profile_picture'])
                ? '../../uploads/profiles/' . $row['orig_profile_picture']
                : '../assets/default.jpg',
            'created_at' => date('M d, Y H:i', strtotime($row['orig_created_at'] ?? 'now')),
            'image' => null
        ];
        if (!empty($row['orig_file_url'])) {
            $row['original']['image'] = preg_match('#^https?://#i', $row['orig_file_url'])
                ? $row['orig_file_url']
                : '../../uploads/posts/' . $row['orig_file_url'];
        }
    } else {
        $row['is_shared'] = false;
    }
}

$FEED_POSTS = $posts;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FeedSpace – Where Ka-Piyu Connects</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="../css/base.css"/>
  <link rel="stylesheet" href="../css/feed.css"/>
  <link rel="stylesheet" href="../css/shared-post.css">

  <style>
    .feed-layout { display: flex; gap: 24px; width: 100%; max-width: 1240px; align-items: flex-start; }
    .feed-center { flex: 1; min-width: 0; max-width: 720px; }
    .right-panel { width: 320px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; }
    .right-panel-card { background: var(--color-white); border: 2px solid var(--color-border); border-radius: 22px; padding: 18px 20px; box-shadow: var(--shadow-sm); }
    .rp-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .rp-title { font-weight: 800; font-size: 1rem; color: var(--color-text); }
    .rp-view-all { font-size: 0.8rem; font-weight: 700; color: var(--color-mid); text-decoration: none; transition: color 0.15s; }
    .rp-view-all:hover { color: var(--color-dark); }
    .ann-mini-item { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid var(--color-border); cursor: pointer; transition: background 0.14s; }
    .ann-mini-item:last-child { border-bottom: none; padding-bottom: 0; }
    .ann-mini-item:first-child { padding-top: 0; }
    .ann-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
    .ann-dot.red { background: #e05263; }
    .ann-dot.yellow { background: #f5a623; }
    .ann-dot.green { background: #4caf7d; }
    .ann-dot.blue { background: #3b82f6; }
    .ann-mini-title { font-size: 0.86rem; font-weight: 700; color: var(--color-text); line-height: 1.35; margin-bottom: 4px; }
    .ann-mini-desc { font-size: 0.79rem; color: var(--color-subtext); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .ann-mini-time { font-size: 0.74rem; color: var(--color-subtext); margin-top: 4px; font-weight: 600; }
    .trending-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--color-border); cursor: pointer; transition: background 0.14s; }
    .trending-item:last-child { border-bottom: none; }
    .trending-left { display: flex; align-items: center; gap: 8px; font-size: 0.86rem; font-weight: 700; color: var(--color-text); }
    .trending-left i { color: var(--color-mid); font-size: 0.9rem; }
    .trending-count { font-size: 0.78rem; color: var(--color-subtext); font-weight: 600; }

    /* ===== NESTED SHARED POST CARD (Facebook-style) ===== */
    .shared-post-card {
        border: 1.5px solid var(--color-border, #e4e6eb);
        border-radius: 12px;
        background: var(--color-bg-light, #f0f2f5);
        margin-top: 10px;
        overflow: hidden;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .shared-post-card:hover {
        background: var(--color-bg-hover, #e4e6eb);
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
        color: var(--color-text, #050505);
    }
    .shared-post-card .sp-time {
        font-size: 0.76rem;
        color: var(--color-subtext, #65676b);
        font-weight: 600;
    }
    .shared-post-card .sp-body {
        padding: 0 14px 10px 14px;
    }
    .shared-post-card .sp-content {
        font-size: 0.9rem;
        color: var(--color-text, #050505);
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
        color: var(--color-subtext, #65676b);
        font-size: 0.9rem;
        font-weight: 600;
    }
    .shared-post-card .sp-unavailable i {
        font-size: 1.4rem;
        margin-bottom: 6px;
        display: block;
        color: var(--color-mid, #8c939d);
    }

    /* ===== CREATE POST CARD ===== */
    .create-post-card {
      background: var(--color-white);
      border: 2px solid var(--color-border);
      border-radius: 22px;
      padding: 16px 20px;
      box-shadow: var(--shadow-sm);
      margin-bottom: 16px;
    }
    .create-post-top {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .create-post-top img {
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
    .create-post-input::placeholder {
      color: var(--color-mid);
    }
    .create-post-bottom {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid var(--color-border);
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
    .create-post-action i {
      font-size: 1.1rem;
    }
    .create-post-submit {
      margin-left: auto;
      background: var(--color-dark);
      color: var(--color-white);
      border: none;
      border-radius: 999px;
      padding: 10px 24px;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: all 0.2s;
    }
    .create-post-submit:hover {
      background: var(--color-mid);
      transform: translateY(-1px);
    }
    /* ===== POSTS LOADING ===== */
    .posts-loading {
      text-align: center;
      padding: 40px 20px;
      color: var(--color-subtext);
      font-size: 0.95rem;
    }
    .posts-loading i {
      margin-right: 8px;
      color: var(--color-accent);
    }

  </style>
</head>

<body>
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

    <div class="dropdown" id="settingsDropdown">
      <div class="dropdown-header">Settings</div>
      <div class="dropdown-item" onclick="window.location.href=''"><i class="fas fa-user-edit"></i> Edit Profile</div>
      <div class="dropdown-item danger" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete Profile</div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item" onclick="window.location.href='../../api/logout.php'"><i class="fas fa-sign-out-alt"></i> Log Out</div>
    </div>
  </div>
</header>

<div class="app-body">
  <aside class="sidebar">
    <a href="profile.php?id=<?php echo urlencode($currentUserId); ?>" class="sidebar-profile-entry" title="Go to profile">
  <img src="<?php echo htmlspecialchars($currentUserPic); ?>" alt="Profile" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
  <span class="sidebar-profile-name"><?php echo htmlspecialchars($currentUserName); ?></span>
</a>

    <div class="sidebar-divider"></div>

    <nav class="sidebar-nav">
      <a href="feed-view.php" class="active"><i class="fas fa-home"></i><span>Feed</span></a>
      <a href="announcements.html"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
      <a href="communities.html"><i class="fas fa-users"></i><span>Communities</span></a>
      <a href="help.html"><i class="fas fa-question-circle"></i><span>Help</span></a>
      <a href="about.html"><i class="fas fa-info-circle"></i><span>About</span></a>
    </nav>

    <div class="sidebar-bottom">
      <a href="../../index.html" class="sidebar-signout">
        <i class="fas fa-sign-out-alt"></i><span>Sign out</span>
      </a>
    </div>
  </aside>

  <main class="main-content">
    <div class="feed-layout">
      <div class="feed-center">
        <div class="create-post-card">
          <div class="create-post-top">
            <div class="create-post-top">
  <img src="<?php echo htmlspecialchars($currentUserPic); ?>" alt="User" id="createPostAvatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
  <input type="text" id="newPostInput" class="create-post-input" placeholder="What's happening on campus, <?php echo htmlspecialchars($currentFirstName); ?>?" onclick="openPostModal('')"/>
</div>
          </div>
          <div class="create-post-bottom">
            <button class="create-post-action" onclick="openPostModalWithImage()">
              <i class="fas fa-image" style="color:#45bd62;"></i> Photo
            </button>
            <button class="create-post-action" onclick="openPostModal()">
              <i class="fas fa-pen" style="color:#1877f2;"></i> Post
            </button>
            <button class="create-post-submit" onclick="openPostModal()">+ Create Post</button>
          </div>
        </div>

        <!-- AFTER: Dynamically loaded by feed-dynamic.js -->
<div id="feedPosts"></div>

      <div class="right-panel-card">
        <div class="rp-header">
          <span class="rp-title">Announcements</span>
          <a href="announcements.html" class="rp-view-all">View all</a>
        </div>
        <div id="announcementsMiniList"></div>
      </div>
    </div>
  </main>
</div>

<!-- Modals -->
<div class="modal-overlay" id="postModal">
  <div class="modal">
    <div class="modal-header">
      <div style="display:flex;align-items:center;gap:10px;">
  <img src="<?php echo htmlspecialchars($currentUserPic); ?>" alt="User" style="width:32px;height:32px;border-radius:50%;" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
  <div>
    <div style="font-weight:800;font-size:0.9rem;color:var(--color-dark);"><?php echo htmlspecialchars($currentUserName); ?></div>
    <div style="font-size:0.74rem;color:var(--color-subtext);">Community Name</div>
  </div>
</div>
      <button class="modal-close" onclick="closeModal('postModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="position:relative;">
      <textarea id="modalPostText" placeholder="What's on your mind?" rows="5"></textarea>
      <div id="modalImagePreview" style="display:none;margin:10px 0;border-radius:12px;overflow:hidden;position:relative;">
        <img src="" style="width:100%;max-height:250px;object-fit:cover;display:block;" id="modalPreviewImg"/>
        <button onclick="clearModalImage()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:0.9rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
      </div>
      <input type="file" id="modalPostImage" accept="image/*" style="display:none;" onchange="previewModalImage(this)"/>
      <div class="modal-attach-btns">
        <button class="modal-attach-btn" type="button" onclick="document.getElementById('modalPostImage').click()"><i class="fas fa-image"></i> Photo</button>
      </div>
      <div class="modal-footer">
        <button class="btn-primary" onclick="submitPost()">+ Create Post</button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="reportModal">
  <div class="modal">
    <div class="modal-header">
      <div class="report-modal-icon"><i class="fas fa-exclamation-circle"></i><span>REPORT POST</span></div>
      <button class="modal-close" onclick="closeModal('reportModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <textarea class="report-textarea" placeholder="Why do you want to report this post?"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-primary" onclick="submitReport()">Submit Report</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="shareModal">
  <div class="modal">
    <div class="modal-header">
      <div style="display:flex;align-items:center;gap:10px;">
        <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Trixie" alt="User" style="width:32px;height:32px;border-radius:50%;"/>
        <div>
          <div style="font-weight:800;font-size:0.9rem;color:var(--color-dark);">Trixie May Pontiga</div>
          <div style="font-size:0.74rem;color:var(--color-subtext);">Community Name</div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal('shareModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <textarea id="shareText" placeholder="Add a comment..." rows="3"></textarea>
      <div class="shared-post-preview" id="sharePreviewContainer">
        <div class="sp-author" id="sharePreviewAuthor">Trixie May Pontiga · Community Name</div>
        <div class="sp-text" id="sharePostPreview">*Shared post layout</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-primary" onclick="submitShare()">Share Post</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="announceModal">
  <div class="modal">
    <div class="modal-header">
      <div style="display:flex;align-items:center;gap:10px;">
        <i class="fas fa-bullhorn" style="font-size:1.2rem;color:var(--color-mid);"></i>
        <span style="font-weight:800;font-size:1rem;color:var(--color-dark);">Request to Announce</span>
      </div>
      <button class="modal-close" onclick="closeModal('announceModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <p style="font-size:0.87rem;color:var(--color-subtext);margin-bottom:12px;line-height:1.6;">
        Want this post to appear in the Announcements section? Tell us why it's important for the community.
      </p>
      <textarea class="report-textarea" id="announceReason" placeholder="Why should this be announced? (e.g. important event, urgent update...)" style="border-color:var(--color-border);"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-primary" onclick="submitAnnounceRequest()"><i class="fas fa-paper-plane"></i> Submit Request</button>
    </div>
  </div>
</div>


<script src="../js/base.js"></script>
<script src="../js/notifications.js"></script>
<script src="../js/announcements.js"></script>
<script src="../js/feed.js"></script>
<script src="../js/feed-dynamic.js"></script>

<script>
  // ===== SHARE MODAL LOGIC =====
  let currentSharePostId = null;
  let currentSharePostData = null;


  window.openShareModal = window.openShareModal || function(btn) {
    const card = btn.closest('.post-card');
    if (!card) return;
    currentSharePostId = card.dataset.postId;
    
    // Gather post data for preview
    const authorEl = card.querySelector('.post-author');
    const contentEl = card.querySelector('.post-body > p');
    const imgEl = card.querySelector('.post-image');
    
    currentSharePostData = {
      author: authorEl ? authorEl.textContent.trim() : 'Unknown',
      content: contentEl ? contentEl.textContent.trim() : '',
      image: imgEl ? imgEl.src : null
    };
    
    // Update preview
    const previewAuthor = document.getElementById('sharePreviewAuthor');
    const previewText = document.getElementById('sharePostPreview');
    const previewContainer = document.getElementById('sharePreviewContainer');
    
    if (previewAuthor) previewAuthor.textContent = currentSharePostData.author + ' · Community Name';
    if (previewText) previewText.textContent = currentSharePostData.content || '*Shared post layout';
    
    // Add image to preview if exists
    let existingPreviewImg = previewContainer.querySelector('.sp-preview-image');
    if (existingPreviewImg) existingPreviewImg.remove();
    
    if (currentSharePostData.image) {
      const imgWrap = document.createElement('div');
      imgWrap.className = 'sp-preview-image';
      imgWrap.style.cssText = 'margin-top:8px;border-radius:8px;overflow:hidden;';
      imgWrap.innerHTML = '<img src="' + currentSharePostData.image + '" style="width:100%;max-height:200px;object-fit:cover;display:block;" onerror="this.style.display=\'none\'"/>';
      previewContainer.appendChild(imgWrap);
    }
    
    const el = document.getElementById('shareModal');
    if (el) el.classList.add('show');
  };

  window.submitShare = window.submitShare || function() {
    if (!currentSharePostId) return;
    
    const ta = document.getElementById('shareText');
    const text = ta ? ta.value.trim() : '';
    
    const btn = document.querySelector('#shareModal .btn-primary');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Sharing...';
    }
    
    const form = new FormData();
    form.append('action', 'share_post');
    form.append('shared_post_id', currentSharePostId);
    form.append('content', text);
    
    fetch('../api/users/posts/create-post.php', {
      method: 'POST',
      credentials: 'include',
      body: form
    })
    .then(async function(r) {
      const raw = await r.text();
      let data;
      try { data = JSON.parse(raw); } catch(e) { data = null; }
      if (!r.ok || !data || !data.success) {
        throw new Error((data && data.error) ? data.error : raw || r.statusText);
      }
      return data;
    })
    .then(function(data) {
      if (typeof showToast === 'function') showToast('Post shared! 🎉');
      if (typeof closeModal === 'function') closeModal('shareModal');
      if (ta) ta.value = '';
      if (typeof loadFeedPosts === 'function') {
        loadFeedPosts(1, true);
      } else {
        window.location.reload();
      }
    })
    .catch(function(err) {
      console.error('submitShare error:', err);
      if (typeof showToast === 'function') showToast('Error: ' + err.message, 'error');
    })
    .finally(function() {
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Share Post';
      }
      currentSharePostId = null;
      currentSharePostData = null;
    });
  };

  window.openPostModal = window.openPostModal || function(prefill) {
    const ta = document.getElementById('modalPostText');
    if (ta) ta.value = prefill || '';
    const modal = document.getElementById('postModal');
    if (modal) modal.classList.add('show');
    if (ta) ta.focus();
  };

  window.openPostModalWithImage = window.openPostModalWithImage || function() {
    openPostModal();
    setTimeout(function() {
      var fileInput = document.getElementById('modalPostImage');
      if (fileInput) fileInput.click();
    }, 100);
  };

  window.previewModalImage = window.previewModalImage || function(input) {
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        var preview = document.getElementById('modalImagePreview');
        var img = document.getElementById('modalPreviewImg');
        if (preview && img) {
          img.src = e.target.result;
          preview.style.display = 'block';
        }
      };
      reader.readAsDataURL(input.files[0]);
    }
  };

  window.clearModalImage = window.clearModalImage || function() {
    var input = document.getElementById('modalPostImage');
    var preview = document.getElementById('modalImagePreview');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
  };

  window.closeModal = window.closeModal || function(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
  };

  window.submitPost = window.submitPost || function() {
    const ta = document.getElementById('modalPostText');
    const text = ta ? ta.value.trim() : '';
    const fileInput = document.getElementById('modalPostImage');
    const hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (!text && !hasFile) {
      (typeof window.showToast === 'function' ? window.showToast : console.warn)('Write something or attach a photo/file first!');
      return;
    }

    const btn = document.querySelector('#postModal .btn-primary');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Creating...';
    }

    const form = new FormData();
    form.append('content', text);

    if (fileInput && fileInput.files && fileInput.files[0]) {
      form.append('image', fileInput.files[0]);
    }

    fetch('../api/users/posts/create-post.php', {
      method: 'POST',
      credentials: 'include',
      body: form
    })
      .then(async function(r) {
        const raw = await r.text();
        let data;
        try { data = JSON.parse(raw); } catch(e) { data = null; }
        if (!r.ok || !data || !data.success) {
          const msg = (data && data.error) ? data.error : raw || r.statusText;
          throw new Error(msg);
        }
        return data;
      })
      .then(function(data) {
        if (typeof showToast === 'function') showToast('Post shared! 🎉');
        if (typeof closeModal === 'function') closeModal('postModal');
        if (ta) ta.value = '';
        if (fileInput) fileInput.value = '';
        clearModalImage();
        if (typeof loadFeedPosts === 'function') {
          loadFeedPosts(1, true);
        } else {
          window.location.reload();
        }
      })
      .catch(function(err) {
        console.error('submitPost fallback error:', err);
        if (typeof showToast === 'function') showToast('Error: ' + err.message, 'error');
      })
      .finally(function() {
        if (btn) {
          btn.disabled = false;
          btn.textContent = '+ Create Post';
        }
      });
  };

  window.togglePostOptions = window.togglePostOptions || function() {};
  window.toggleLike = window.toggleLike || function() {};
  window.toggleComments = window.toggleComments || function() {};
  
  window.editPost = window.editPost || function() {
    if (typeof showToast === 'function') showToast('Edit unavailable (missing feed.js)', 'warning');
  };

  window.deletePost = window.deletePost || function() {
    if (typeof showToast === 'function') showToast('Delete unavailable (missing feed.js)', 'warning');
  };

  window.openReportModal = window.openReportModal || function() {
    const el = document.getElementById('reportModal');
    if (el) el.classList.add('show');
  };

  window.openAnnounceModal = window.openAnnounceModal || function() {
    const el = document.getElementById('announceModal');
    if (el) el.classList.add('show');
  };

  window.submitReport = window.submitReport || function() {
    if (typeof showToast === 'function') showToast('Report unavailable (missing handler)', 'warning');
    if (typeof closeModal === 'function') closeModal('reportModal');
  };

  window.submitAnnounceRequest = window.submitAnnounceRequest || function() {
    if (typeof showToast === 'function') showToast('Request unavailable (missing handler)', 'warning');
    if (typeof closeModal === 'function') closeModal('announceModal');
  };

  window.toggleDropdown = window.toggleDropdown || function() {};
  window.confirmDelete = window.confirmDelete || function() {};

  
</script>
</body>
</html>