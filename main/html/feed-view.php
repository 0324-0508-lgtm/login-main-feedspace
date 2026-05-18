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

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ban-check.php';

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo 'Account banned';
    exit();
}

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
            u.first_name,
            u.last_name,
            u.profile_picture
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN user_bans b ON u.user_id = b.user_id
            AND (b.expires_at > NOW() OR b.expires_at IS NULL)
        WHERE p.is_deleted = 0
            AND p.deleted_at IS NULL
            AND p.is_archived = 0
            AND p.status = 'approved'
            AND p.ai_status != 'rejected'
            AND b.id IS NULL
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
            : '..\main\assets\default.jpg';
        
        if (!empty($row['file_url'])) {
            $row['image'] = preg_match('#^https?://#i', $row['file_url'])
                ? $row['file_url']
                : '../../uploads/posts/' . $row['file_url'];
        } else {
            $row['image'] = null;
        }
        
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
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
        u.first_name,
        u.last_name,
        u.profile_picture,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
        EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = ?) AS user_liked
     FROM posts p
     JOIN users u ON p.user_id = u.user_id
     LEFT JOIN user_bans b ON u.user_id = b.user_id
        AND (b.expires_at > NOW() OR b.expires_at IS NULL)
     WHERE p.is_deleted = 0
        AND p.deleted_at IS NULL
        AND p.is_archived = 0
        AND p.status = 'approved'
        AND p.ai_status != 'rejected'
        AND b.id IS NULL
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
  </style>
</head>

<body>
<<header class="navbar">
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

    <div class="profile-chip" onclick="toggleDropdown('settingsDropdown')">
      <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Kim" alt="Profile"/>
      <span>You</span>
    </div>

    <div class="dropdown" id="notifDropdown">
      <div class="dropdown-header">Notifications</div>
      <div id="notifList"></div>
    </div>

    <div class="dropdown" id="settingsDropdown">
      <div class="dropdown-header">Settings</div>
      <div class="dropdown-item" onclick="window.location.href='profile.html'"><i class="fas fa-user-edit"></i> Edit Profile</div>
      <div class="dropdown-item danger" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete Profile</div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item" onclick="window.location.href='../../api/logout.php'"><i class="fas fa-sign-out-alt"></i> Log Out</div>
    </div>
  </div>
</header>

<div class="app-body">
  <aside class="sidebar">
    <a href="profile.html" class="sidebar-profile-entry" title="Go to profile">
      <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Kim" alt="Profile"/>
      <span class="sidebar-profile-name">Kim Ballebar</span>
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
            <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Kim" alt="User"/>
            <input type="text" id="newPostInput" class="create-post-input" placeholder="What's happening on campus?" onclick="openPostModal('')"/>
            <button onclick="openPostModal()" class="create-post-btn"> + Create Post</button>
          </div>
        </div>

        <div id="feedPosts">
          <?php if (!empty($FEED_POSTS) && is_array($FEED_POSTS)): ?>
            <?php foreach ($FEED_POSTS as $post): ?>
              <?php
                $pid = (int)($post['post_id'] ?? 0);
                $content = htmlspecialchars((string)($post['content'] ?? ''), ENT_QUOTES, 'UTF-8');
                $author = htmlspecialchars((string)($post['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $avatar = htmlspecialchars((string)($post['profile_picture'] ?? ''), ENT_QUOTES, 'UTF-8');
                $likeCount = (int)($post['like_count'] ?? 0);
                $commentCount = (int)($post['comment_count'] ?? 0);
                $userLiked = !empty($post['user_liked']);
                
                $img = $post['image'] ?? null;
                $imgTag = '';
                if (!empty($img)) {
                    $safeImg = htmlspecialchars((string)$img, ENT_QUOTES, 'UTF-8');
                    $imgTag = '<div class="image-grid grid-1"><img src="' . $safeImg . '" alt="Post image" class="post-image" onerror="this.style.display=\'none\'"/></div>';
                }
              ?>

              <div class="post-card" data-post-id="<?= $pid ?>">
                <div class="post-header">
                  <img src="..\assets\default.jpg" alt="User" class="post-avatar"/>

                  <div class="post-meta">
                    <div class="post-author"><?= $author ?></div>
                    <div class="post-community">Community · <span class="post-time"><?= htmlspecialchars((string)($post['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div>
                  </div>

                  <button class="options-btn" type="button" onclick="togglePostOptions(this)" style="pointer-events:auto;"><i class="fas fa-sliders-h"></i></button>

                  <div class="post-options-menu" role="menu">
                    <div class="post-option" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit Post</div>
                    <div class="post-option danger" onclick="deletePost(this)"><i class="fas fa-trash"></i> Delete Post</div>
                    <div class="post-option" onclick="openReportModal(this)"><i class="fas fa-flag"></i> Report</div>
                    <div class="post-option" onclick="openAnnounceModal(this)"><i class="fas fa-bullhorn"></i> Request to Announce</div>
                  </div>
                </div>

                <div class="post-body">
                  <p><?= $content ?></p>
                  <?= $imgTag ?>
                </div>

                <div class="post-footer">
                  <button class="reaction-btn <?= $userLiked ? 'liked' : '' ?>" data-post-id="<?= $pid ?>" type="button" onclick="return toggleLike(this)">
                    <i class="<?= $userLiked ? 'fas' : 'far' ?> fa-heart"></i>
                    <span><?= $likeCount ?></span>
                  </button>

                  <button class="reaction-btn" type="button" onclick="toggleComments(this)">
                    <i class="fas fa-comment"></i> <span><?= $commentCount ?></span>Comment
                  </button>

                  <button class="reaction-btn" type="button" onclick="openShareModal(this)">
                    <i class="fas fa-share"></i> <span>Share</span>
                  </button>
                </div>

                <div class="comment-section" style="display:none;">
                  <div class="comment-input-row">
                    <img src="../assets/default.jpg" alt="User"/>
                    <div class="comment-input-wrap">
                      <input type="text" placeholder="Write a comment..."/>
                      <button class="comment-send-btn" type="button" onclick="addComment(this)"><i class="fas fa-plus"></i></button>
                    </div>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align:center;padding:20px;color:var(--color-subtext);">No posts yet.</div>
          <?php endif; ?>
        </div>
      </div>

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
        <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Trixie" alt="User" style="width:32px;height:32px;border-radius:50%;"/>
        <div>
          <div style="font-weight:800;font-size:0.9rem;color:var(--color-dark);">Trixie May Pontiga</div>
          <div style="font-size:0.74rem;color:var(--color-subtext);">Community Name</div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal('postModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <textarea id="modalPostText" placeholder="What's on your mind?" rows="5"></textarea>
      <input type="file" id="modalPostImage" accept="image/*" style="display:none;"/>
      <div class="modal-attach-btns">
        <button class="modal-attach-btn" type="button" onclick="document.getElementById('modalPostImage').click()"><i class="fas fa-image"></i> Photo</button>
        <button class="modal-attach-btn" type="button" onclick="document.getElementById('modalPostImage').click()"><i class="fas fa-file"></i> File</button>
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
      <div class="shared-post-preview">
        <div class="sp-author">Trixie May Pontiga · Community Name</div>
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
  window.openPostModal = window.openPostModal || function(prefill) {
    const ta = document.getElementById('modalPostText');
    if (ta) ta.value = prefill || '';
    const modal = document.getElementById('postModal');
    if (modal) modal.classList.add('show');
    if (ta) ta.focus();
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
  window.openShareModal = window.openShareModal || function() {};
  
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

  window.submitShare = window.submitShare || function() {};

  window.submitAnnounceRequest = window.submitAnnounceRequest || function() {
    if (typeof showToast === 'function') showToast('Request unavailable (missing handler)', 'warning');
    if (typeof closeModal === 'function') closeModal('announceModal');
  };

  window.toggleDropdown = window.toggleDropdown || function() {};
  window.confirmDelete = window.confirmDelete || function() {};
</script>
</body>
</html>