<?php
// Prevent PHP errors from breaking JSON/JS
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/db.php';
require_once '../../config/session.php';
$pdo = $conn;

if (!isLoggedIn()) {
    header('Location: ../index.html');
    exit;
}

$userId = currentUserId();

$stmt = $pdo->prepare("SELECT first_name, last_name, email, bio, college, profile_picture FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { header('Location: profile.php'); exit; }

// Better avatar handling - always use a valid URL
$profilePic = $user['profile_picture'] ?? '';
if (empty($profilePic)) {
    $profilePic = 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($user['first_name']);
} elseif (strpos($profilePic, 'http') !== 0 && strpos($profilePic, 'data:') !== 0) {
    $profilePic = '../uploads/profile/' . $profilePic;
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Edit Profile — FeedSpace</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="../css/base.css"/>
<link rel="stylesheet" href="../css/feed.css"/>
<link rel="stylesheet" href="../css/profile.css"/>
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
    <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Search FeedSpace..."/></div>
  </div>
  <div class="nav-actions">
    <button class="nav-icon-btn" onclick="toggleDropdown('notifDropdown')">
      <i class="fas fa-bell"></i><span class="badge" id="notifBadge"></span>
    </button>
    <div class="profile-chip" onclick="toggleDropdown('settingsDropdown')">
      <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" id="navbarAvatar"
           onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
      <span id="navbarProfileName"><?php echo htmlspecialchars($user['first_name']); ?></span>
    </div>
    <div class="dropdown" id="notifDropdown">
      <div class="dropdown-header">Notifications</div>
      <div id="notifList"><div class="notif-item read"><div class="notif-dot read"></div><div><div class="notif-title">Loading…</div></div></div></div>
    </div>
    <div class="dropdown" id="settingsDropdown">
      <div class="dropdown-header">Settings</div>
      <div class="dropdown-item" onclick="window.location.href='profile.php'"><i class="fas fa-user"></i> My Profile</div>
      <div class="dropdown-item danger" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete Account</div>
      <div class="dropdown-divider"></div>
      <div class="dropdown-item" onclick="window.location.href='../logout.php'"><i class="fas fa-sign-out-alt"></i> Log Out</div>
    </div>
  </div>
</header>

<div class="app-body">
<aside class="sidebar">
  <a href="profile.php" class="sidebar-profile-entry" title="Go to profile">
    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" id="sidebarAvatar"
         onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
    <span class="sidebar-profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
  </a>
  <div class="sidebar-divider"></div>
  <nav class="sidebar-nav">
    <a href="feed-view.php"><i class="fas fa-home"></i><span>Feed</span></a>
    <a href="announcements.php"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
    <a href="community.php"><i class="fas fa-users"></i><span>Communities</span></a>
    <a href="help.php"><i class="fas fa-question-circle"></i><span>Help</span></a>
    <a href="about.php"><i class="fas fa-info-circle"></i><span>About</span></a>
  </nav>
  <div class="sidebar-bottom">
    <a href="../logout.php" class="sidebar-signout"><i class="fas fa-sign-out-alt"></i><span>Sign out</span></a>
  </div>
</aside>

<main class="main-content">
<div class="content-center">
  <div class="edit-profile-card">

    <div class="edit-profile-header">
      <h2><i class="fas fa-user-edit"></i><span>Edit Profile</span></h2>
      <a href="profile.php" class="btn-back"><i class="fas fa-arrow-left"></i><span>Back to Profile</span></a>
    </div>

    <form id="editProfileForm" enctype="multipart/form-data">

      <div class="ep-avatar-section">
        <div class="ep-avatar-wrap-large" onclick="document.getElementById('epAvatarInput').click()" title="Click to change photo">
          <img src="<?php echo htmlspecialchars($profilePic); ?>" id="epAvatarPreview" alt="Avatar"
               onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'"/>
          <div class="ep-avatar-overlay"><i class="fas fa-camera"></i></div>
        </div>
        <input type="file" id="epAvatarInput" name="avatar" accept="image/*" onchange="previewAvatar(this)"/>
        <div class="ep-avatar-info">
          <h4 id="epDisplayName"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
          <p><?php echo htmlspecialchars($user['email']); ?></p>
          <span class="ep-avatar-hint"><i class="fas fa-camera"></i> Click avatar to change photo</span>
        </div>
      </div>

      <div class="ep-form-grid">

        <div class="ep-form-group">
          <label class="ep-label">First Name</label>
          <input type="text" class="ep-input" name="first_name" id="epFirstName"
                 value="<?php echo htmlspecialchars($user['first_name']); ?>" required
                 oninput="updateDisplayName()"/>
        </div>

        <div class="ep-form-group">
          <label class="ep-label">Last Name</label>
          <input type="text" class="ep-input" name="last_name" id="epLastName"
                 value="<?php echo htmlspecialchars($user['last_name']); ?>" required
                 oninput="updateDisplayName()"/>
        </div>

        <div class="ep-form-group ep-form-full">
          <label class="ep-label">Email</label>
          <input type="email" class="ep-input" value="<?php echo htmlspecialchars($user['email']); ?>" readonly/>
          <small class="ep-hint"><i class="fas fa-lock"></i> Email cannot be changed</small>
        </div>

        <div class="ep-form-group ep-form-full">
          <label class="ep-label">College</label>
          <select class="ep-input ep-select" name="college">
            <option value="">— Select your college —</option>
            <?php foreach ($colleges as $c): ?>
              <option value="<?php echo htmlspecialchars($c); ?>"
                <?php echo ($user['college'] === $c) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="ep-form-group ep-form-full">
          <label class="ep-label">Bio</label>
          <textarea class="ep-input" name="bio" id="epBio" rows="4"
                    maxlength="500"
                    placeholder="Tell the community about yourself..."
                    oninput="updateCharCount()"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
          <div class="ep-bio-footer">
            <small class="ep-hint">Max 500 characters</small>
            <small class="ep-char-count"><span id="bioCharCount"><?php echo strlen($user['bio'] ?? ''); ?></span> / 500</small>
          </div>
        </div>

      </div>

      <div class="ep-preset-section">
        <label class="ep-label">Or pick a preset avatar</label>
        <div class="ep-avatar-seeds">
          <?php
          $seeds = ['Kim','Trixie','Maya','Alex','Luna','Zara','Sam','Chris','Jamie','River'];
          foreach ($seeds as $seed):
          ?>
          <img class="ep-avatar-seed-opt"
               src="https://api.dicebear.com/7.x/adventurer/svg?seed=<?php echo $seed; ?>"
               data-seed="<?php echo $seed; ?>"
               onclick="selectSeed(this)"
               title="<?php echo $seed; ?>"
               alt="<?php echo $seed; ?>"/>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="avatar_seed" id="avatarSeedInput"/>
      </div>

      <div class="ep-form-actions">
        <a href="profile.php" class="ep-cancel-btn"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" class="ep-save-btn" id="saveBtn">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>

    </form>
  </div>
</div>
</main>
</div>

<div id="toastContainer"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../js/base.js"></script>
<script src="../js/notifications.js"></script>
<script>
// Dropdown
function toggleDropdown(id) {
  var d = document.getElementById(id);
  if (d) {
    document.querySelectorAll('.dropdown').forEach(function(x){ if(x.id!==id) x.classList.remove('show'); });
    d.classList.toggle('show');
  }
}
document.addEventListener('click', function(e){
  if (!e.target.closest('.nav-actions'))
    document.querySelectorAll('.dropdown').forEach(function(d){ d.classList.remove('show'); });
});

// Toast
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

// Live name preview
function updateDisplayName() {
  var first = document.getElementById('epFirstName').value.trim();
  var last  = document.getElementById('epLastName').value.trim();
  var name  = (first + ' ' + last).trim() || 'Your Name';
  document.getElementById('epDisplayName').textContent = name;
}

// Bio char count
function updateCharCount() {
  var len = document.getElementById('epBio').value.length;
  var el  = document.getElementById('bioCharCount');
  if (el) {
    el.textContent = len;
    el.style.color = len > 450 ? '#e53935' : '#9aa8b5';
  }
}

// Avatar file preview
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    var r = new FileReader();
    r.onload = function(e) {
      document.getElementById('epAvatarPreview').src = e.target.result;
      document.getElementById('avatarSeedInput').value = '';
      document.querySelectorAll('.ep-avatar-seed-opt').forEach(function(o){ o.classList.remove('selected'); });
    };
    r.readAsDataURL(input.files[0]);
  }
}

// Preset avatar seed
function selectSeed(img) {
  document.querySelectorAll('.ep-avatar-seed-opt').forEach(function(o){ o.classList.remove('selected'); });
  img.classList.add('selected');
  document.getElementById('avatarSeedInput').value = img.dataset.seed;
  document.getElementById('epAvatarPreview').src = img.src;
  document.getElementById('epAvatarInput').value = '';
}

// Form submit
document.getElementById('editProfileForm').addEventListener('submit', function(e) {
  e.preventDefault();

  var firstName = document.getElementById('epFirstName').value.trim();
  var lastName  = document.getElementById('epLastName').value.trim();
  if (!firstName || !lastName) {
    showToast('First and last name are required.', 'error');
    return;
  }

  var btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  var fd = new FormData(this);

  fetch('../api/users/profile/update-profile.php', {
    method: 'POST',
    credentials: 'include',
    body: fd
  })
  .then(function(r) { return r.text(); })
  .then(function(text) {
    console.log('Raw response:', text);
    try {
      var res = JSON.parse(text);
      if (res.success) {
        showToast('Profile updated successfully!');
        setTimeout(function(){ window.location.href = 'profile.php'; }, 1200);
      } else {
        showToast(res.error || 'Update failed.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
      }
    } catch(err) {
      console.error('JSON parse error:', err);
      showToast('Server returned invalid response. Check console.', 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    }
  })
  .catch(function(err) {
    console.error('Fetch error:', err);
    showToast('Something went wrong. Please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
  });
});

// Delete account
function confirmDelete() {
  if (confirm('Are you sure you want to delete your account? This cannot be undone!')) {
    fetch('../api/users/delete-account.php', { method: 'POST', credentials: 'include' })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if (res.success) {
        showToast('Account deleted.');
        setTimeout(function(){ window.location.href = '../index.html'; }, 1500);
      } else {
        showToast(res.error || 'Delete failed.', 'error');
      }
    });
  }
}
</script>
</body>
</html>