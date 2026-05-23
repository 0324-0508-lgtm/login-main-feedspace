<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../../index.html');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

$currentFirst = $currentUser['first_name'] ?? 'User';
$currentLast  = $currentUser['last_name'] ?? '';
$currentName  = trim($currentFirst . ' ' . $currentLast) ?: 'User';

$currentPic = $currentUser['profile_picture'] ?? '';
if (empty($currentPic)) {
    $currentPic = 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($currentFirst);
} elseif (strpos($currentPic, 'http') !== 0 && strpos($currentPic, 'data:') !== 0) {
    // main/html -> main/uploads/profiles
    $currentPic = '../../uploads/profiles/' . $currentPic;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Liked Pages – FeedSpace</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="../css/base.css"/>
  <link rel="stylesheet" href="../css/liked.css"/>
</head>
<body>
<header class="navbar">
  <div class="nav-logo">
    <a href="feed-view.php">
      <img src="logo.png" alt="FeedSpace" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"/>
      <span class="nav-logo-fallback"><span class="icon">🎠</span><span class="text">FeedSpace</span></span>
    </a>
  </div>
  <div class="nav-search">
    <div class="search-bar">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search liked communities..."/>
    </div>
  </div>
  <div class="nav-actions">
    <button class="nav-icon-btn" onclick="toggleDropdown('notifDropdown')">
      <i class="fas fa-bell"></i><span class="badge" id="notifCount">3</span>
    </button>
    <div class="profile-chip" onclick="toggleDropdown('settingsDropdown')">
      <img src="<?php echo htmlspecialchars($currentPic); ?>" alt="Profile" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
      <span>You</span>
    </div>

    <div class="dropdown" id="notifDropdown">
      <div class="dropdown-header">Notifications</div>
      <div id="notifList"></div>
    </div>

    <div class="dropdown" id="settingsDropdown">
      <div class="dropdown-header">Settings</div>
      <div class="dropdown-item" onclick="window.location.href='profile.php'">
        <i class="fas fa-user-edit"></i> Edit Profile
      </div>
      <div class="dropdown-item danger" onclick="confirmDelete()">
        <i class="fas fa-trash"></i> Delete Profile
      </div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item" onclick="window.location.href='../../index.html'">
        <i class="fas fa-sign-out-alt"></i> Log Out
      </div>
    </div>
  </div>
</header>

<div class="app-body">
  <aside class="sidebar">
    <a href="profile.php" class="sidebar-profile-entry" title="Go to profile">
      <img src="<?php echo htmlspecialchars($currentPic); ?>" alt="Profile" id="sidebarAvatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
      <span class="sidebar-profile-name"><?php echo htmlspecialchars($currentName); ?></span>
    </a>

    <div class="sidebar-divider"></div>

    <nav class="sidebar-nav">
      <a href='feed-view.php'><i class="fas fa-home"></i><span>Feed</span></a>
      <a href="announcements.html"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
      <a href="community.php"><i class="fas fa-users"></i><span>Communities</span></a>
      <a href="liked.php" class="active"><i class="fas fa-heart"></i><span>Liked</span></a>
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
    <div class="liked-wrapper">
      <div class="liked-header-row">
        <h2 class="liked-title">Liked Communities</h2>
      </div>

      <div class="liked-search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search liked communities..." oninput="filterLiked(this.value)"/>
      </div>

      <div class="liked-list" id="likedList">
        <div class="liked-row" data-name="Photography Club">
          <div class="liked-thumb" style="background:linear-gradient(135deg,#355872,#7AAACE)">📸</div>
          <div class="liked-row-info">
            <div class="liked-row-name">Photography Club</div>
            <div class="liked-row-sub">4,200 followers</div>
            <div class="liked-row-tags"><span class="liked-tag">Arts</span></div>
          </div>
          <button class="unlike-row-btn" onclick="unlikeRow(this, 'Photography Club')">Unlike</button>
        </div>

        <div class="liked-row" data-name="Food & Travel PH">
          <div class="liked-thumb" style="background:linear-gradient(135deg,#7AAACE,#9CD5FF)">🍜</div>
          <div class="liked-row-info">
            <div class="liked-row-name">Food &amp; Travel PH</div>
            <div class="liked-row-sub">12,800 followers</div>
            <div class="liked-row-tags"><span class="liked-tag">Food</span></div>
          </div>
          <button class="unlike-row-btn" onclick="unlikeRow(this, 'Food & Travel PH')">Unlike</button>
        </div>

        <div class="liked-row" data-name="Tech Enthusiasts">
          <div class="liked-thumb" style="background:linear-gradient(135deg,#9CD5FF,#355872)">💻</div>
          <div class="liked-row-info">
            <div class="liked-row-name">Tech Enthusiasts</div>
            <div class="liked-row-sub">8,500 followers</div>
            <div class="liked-row-tags"><span class="liked-tag">Tech</span></div>
          </div>
          <button class="unlike-row-btn" onclick="unlikeRow(this, 'Tech Enthusiasts')">Unlike</button>
        </div>

        <div class="liked-row" data-name="Local Art Scene">
          <div class="liked-thumb" style="background:linear-gradient(135deg,#355872,#9CD5FF)">🎨</div>
          <div class="liked-row-info">
            <div class="liked-row-name">Local Art Scene</div>
            <div class="liked-row-sub">3,100 followers</div>
            <div class="liked-row-tags"><span class="liked-tag">Arts</span></div>
          </div>
          <button class="unlike-row-btn" onclick="unlikeRow(this, 'Local Art Scene')">Unlike</button>
        </div>

        <div class="liked-row" data-name="Student Life PH">
          <div class="liked-thumb" style="background:linear-gradient(135deg,#7AAACE,#355872)">🎓</div>
          <div class="liked-row-info">
            <div class="liked-row-name">Student Life PH</div>
            <div class="liked-row-sub">21,000 followers</div>
            <div class="liked-row-tags"><span class="liked-tag">Campus</span></div>
          </div>
          <button class="unlike-row-btn" onclick="unlikeRow(this, 'Student Life PH')">Unlike</button>
        </div>

        <div class="liked-row" data-name="Nature Lovers">
          <div class="liked-thumb" style="background:linear-gradient(135deg,#9CD5FF,#7AAACE)">🌿</div>
          <div class="liked-row-info">
            <div class="liked-row-name">Nature Lovers</div>
            <div class="liked-row-sub">5,600 followers</div>
            <div class="liked-row-tags"><span class="liked-tag">Outdoors</span></div>
          </div>
          <button class="unlike-row-btn" onclick="unlikeRow(this, 'Nature Lovers')">Unlike</button>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="../js/base.js"></script>
<script src="../js/notifications.js"></script>
<script src="../js/liked.js"></script>
<script>
function filterLiked(query) {
  document.querySelectorAll('.liked-row').forEach(row => {
    const name = row.getAttribute('data-name').toLowerCase();
    row.style.display = name.includes(query.toLowerCase()) ? '' : 'none';
  });
}

function unlikeRow(btn, name) {
  const row = btn.closest('.liked-row');
  if (confirm('Unlike "' + name + '"?')) {
    row.style.transition = 'opacity 0.28s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 290);
    showToast('Unliked ' + name + '.');
  }
}
</script>
</body>
</html>

