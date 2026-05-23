<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$currentUserId = $_SESSION['user_id'];

// Fetch current user data — using your actual column names
$stmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = :id");
$stmt->bindValue(':id', $currentUserId, PDO::PARAM_STR); // user_id is varchar(9)
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Combine first + last name, fallback to "User"
$currentUserName = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
$currentUserName = trim($currentUserName) ?: 'User';

// Profile picture — your column is named profile_picture, not profile_pic
$currentUserPic = $user['profile_picture'] ?? 'default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Announcements – FeedSpace</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="../css/base.css"/>
  <link rel="stylesheet" href="../css/announcements.css"/>
</head>
<body>

<!-- ========== NAVBAR ========== -->
<header class="navbar">
  <div class="nav-logo">
    <a href="feed-view.php">
      <img src="../assets/logo.png" alt="FeedSpace" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"/>
      <span class="nav-logo-fallback"><span class="icon">🏠</span><span class="text">FeedSpace</span></span>
    </a>
  </div>

  <div class="nav-search">
    <div class="search-bar">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search posts, people, topics..."/>
    </div>
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
      <div class="dropdown-item" onclick="window.location.href='profile.php'"><i class="fas fa-user-edit"></i> Edit Profile</div>
      <div class="dropdown-item danger" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete Profile</div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item" onclick="window.location.href='../../index.html'"><i class="fas fa-sign-out-alt"></i> Log Out</div>
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
    </a>
    <div class="sidebar-divider"></div>
    <nav class="sidebar-nav">
      <a href="feed-view.php"><i class="fas fa-home"></i><span>Feed</span></a>
      <a href="announcements.php" class="active"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
      <a href="community.php"><i class="fas fa-users"></i><span>Communities</span></a>
      <a href="help.php"><i class="fas fa-question-circle"></i><span>Help</span></a>
      <a href="about.html"><i class="fas fa-info-circle"></i><span>About</span></a>
    </nav>
    <div class="sidebar-bottom">
      <a href="../logout.php" class="sidebar-signout"><i class="fas fa-sign-out-alt"></i><span>Sign out</span></a>
    </div>
  </aside>

  <main class="main-content">
    <div class="announcements-wrapper">

      <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
        <p>Stay updated with the latest news from your communities.</p>
      </div>

      <div id="announcementsList"></div>

    </div>
  </main>

</div>

<script src="../js/base.js"></script>
<script src="../js/notifications.js"></script>
<script src="../js/announcements.js"></script>

<script>
// Fetch current user and update sidebar
fetch('../../auth/session-user.php', { credentials: 'include' })
  .then(function(r) { 
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json(); 
  })
  .then(function(data) {
    if (data.success && data.user) {
      var u = data.user;
      var pic = u.profile_picture || 'https://api.dicebear.com/7.x/adventurer/svg?seed=' + encodeURIComponent(u.first_name || 'User');
      if (pic.indexOf('http') !== 0 && pic.indexOf('data:') !== 0) {
        pic = '../../uploads/profiles/' + pic;
      }
      var name = (u.first_name + ' ' + (u.last_name || '')).trim() || 'User';
      
      var link = document.getElementById('sidebarProfileLink');
      var img = document.getElementById('sidebarProfileImg');
      var span = document.getElementById('sidebarProfileName');
      
      if (link) link.href = 'profile.php?id=' + encodeURIComponent(u.user_id);
      if (img) img.src = pic;
      if (span) span.textContent = name;
    }
  })
  .catch(function(err) {
    console.error('Failed to load user:', err);
  });
</script>

</body>
</html>