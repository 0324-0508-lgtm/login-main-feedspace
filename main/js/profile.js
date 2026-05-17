// ============================================================
//  profile.js — Dynamic profile page logic for FeedSpace
//
//  Reads the logged-in user_id from localStorage (set by
//  auth.js after OTP verification), then:
//    1. Fetches profile data from get-profile.php
//    2. Renders avatar, banner, name, stats, bio
//    3. Loads and renders the user's posts
//    4. Handles Edit Profile modal with save-to-backend
//    5. Handles avatar & cover-photo uploads
// ============================================================

// ── Constants ────────────────────────────────────────────────
const API_BASE = 'api/users/user/uploads/profiles/';
const DEFAULT_AVATAR  = 'https://api.dicebear.com/7.x/adventurer/svg?seed=Default';
const DEFAULT_COVER   = null; // CSS gradient fallback used when null

// ── State ────────────────────────────────────────────────────
let currentUserId   = null;   // logged-in user
let profileUserId   = null;   // profile being viewed
let currentPage     = 1;
let totalPages      = 1;
let isLoadingPosts  = false;
let profileData     = null;

// ── Helpers ──────────────────────────────────────────────────
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function getStoredUserId() {
  return localStorage.getItem('currentUserId') || null;
}

function getUserIdFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return params.get('user_id') || null;
}

function resolveAvatarUrl(url) {
  if (!url) return DEFAULT_AVATAR;
  if (url.startsWith('http')) return url;
  return url;
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  currentUserId  = getStoredUserId();
  profileUserId  = getUserIdFromUrl() || currentUserId;

  if (!currentUserId) {
    // Not logged in — redirect to login
    window.location.href = '../index.html';
    return;
  }

  loadProfile();

  // Edit Profile modal — close on overlay click
  const overlay = document.getElementById('editProfileModal');
  if (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeEditModal();
    });
  }

  // Avatar / banner file inputs (profile page header)
  const avatarInput = document.getElementById('avatarFileInput');
  if (avatarInput) {
    avatarInput.addEventListener('change', function () {
      if (this.files && this.files[0]) uploadProfilePic(this.files[0]);
    });
  }

  const bannerInput = document.getElementById('bannerFileInput');
  if (bannerInput) {
    bannerInput.addEventListener('change', function () {
      if (this.files && this.files[0]) uploadCoverPhoto(this.files[0]);
    });
  }

  // Edit-modal inner avatar / banner inputs
  const epAvatarInput = document.getElementById('epAvatarInput');
  if (epAvatarInput) {
    epAvatarInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const preview = document.getElementById('epAvatarPreview');
          if (preview) preview.src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  }

  const epBannerInput = document.getElementById('epBannerInput');
  if (epBannerInput) {
    epBannerInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const preview = document.getElementById('epBannerPreview');
          if (preview) preview.style.backgroundImage = `url('${e.target.result}')`;
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  }
});

// ── Load Profile ─────────────────────────────────────────────
async function loadProfile() {
  applySkeleton(true);

  try {
    const formData = new FormData();
    formData.append('user_id', currentUserId);
    formData.append('target_user_id', profileUserId);
    formData.append('page', currentPage);

    const res  = await fetch(API_BASE + 'get-profile.php', {
      method: 'POST',
      body: formData,
    });

    if (res.status === 401) {
      window.location.href = '../index.html';
      return;
    }

    const data = await res.json();

    if (!data.success) {
      showToast(data.error || 'Failed to load profile');
      return;
    }

    profileData = data.profile;
    totalPages  = data.pagination.pages || 1;

    renderProfile(data.profile);
    renderPosts(data.posts);

  } catch (err) {
    console.error('Profile load error:', err);
    showToast('Could not load profile. Please try again.');
  } finally {
    applySkeleton(false);
  }
}

// ── Load More Posts ───────────────────────────────────────────
async function loadMorePosts() {
  if (isLoadingPosts || currentPage >= totalPages) return;
  isLoadingPosts = true;
  currentPage++;

  try {
    const formData = new FormData();
    formData.append('user_id', currentUserId);
    formData.append('target_user_id', profileUserId);
    formData.append('page', currentPage);

    const res  = await fetch(API_BASE + 'get-profile.php', {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data.success) {
      appendPosts(data.posts);
    }
  } catch (err) {
    currentPage--;
    console.error('Load more posts error:', err);
  } finally {
    isLoadingPosts = false;
  }
}

// ── Render Profile Header ─────────────────────────────────────
function renderProfile(p) {
  const isOwn = p.is_own_profile;

  // Banner
  const banner = document.getElementById('profileBanner');
  if (banner) {
    if (p.cover_photo_url) {
      banner.style.backgroundImage = `url('${p.cover_photo_url}')`;
      banner.style.backgroundSize  = 'cover';
      banner.style.backgroundPosition = 'center';
    }
    // Only allow click-to-change for own profile
    if (!isOwn) {
      banner.style.cursor = 'default';
      banner.onclick = null;
    }
  }

  // Avatar
  const avatarEl = document.getElementById('profileAvatar');
  if (avatarEl) {
    avatarEl.src = resolveAvatarUrl(p.profile_picture_url);
    avatarEl.onerror = function () { this.src = DEFAULT_AVATAR; };
    if (!isOwn) {
      const wrap = document.getElementById('profileAvatar')?.closest('.profile-avatar-wrap');
      if (wrap) { wrap.style.cursor = 'default'; wrap.onclick = null; }
    }
  }

  // Name
  const nameEl = document.getElementById('profileName');
  if (nameEl) {
    nameEl.innerHTML = `${escapeHtml(p.full_name)}<i class="fas fa-check-circle verified-badge"></i>`;
  }

  // Username / email
  const usernameEl = document.getElementById('profileUsername');
  if (usernameEl) {
    usernameEl.textContent = p.email ? `@${p.email.split('@')[0]}` : `@${p.user_id}`;
  }

  // Stats
  const statsEl = document.getElementById('profileStats');
  if (statsEl) {
    statsEl.innerHTML = `
      <span data-stat="posts"><strong>${p.post_count || 0}</strong> Posts</span>
      <span data-stat="communities"><strong>${p.community_count || 0}</strong> Communities</span>
      <span data-stat="role"><strong>${escapeHtml(p.role || 'Student')}</strong></span>
    `;
  }

  // Bio (if element exists)
  const bioEl = document.getElementById('profileBio');
  if (bioEl) {
    bioEl.textContent = p.bio || '';
  }

  // College (if element exists)
  const collegeEl = document.getElementById('profileCollege');
  if (collegeEl) {
    collegeEl.textContent = p.college || '';
  }

  // Show correct action button
  const editBtn   = document.querySelector('.profile-edit-btn');
  const followBtn = document.querySelector('.profile-follow-btn');

  if (isOwn) {
    if (editBtn)   editBtn.style.display   = 'inline-flex';
    if (followBtn) followBtn.style.display = 'none';
  } else {
    if (editBtn)   editBtn.style.display   = 'none';
    if (followBtn) followBtn.style.display = 'inline-flex';
  }

  // Populate navbar / sidebar
  const navAvatar = document.getElementById('navbarAvatar');
  const navName   = document.getElementById('navbarProfileName');
  const sideAvatar = document.getElementById('sidebarAvatar');
  const sideName   = document.getElementById('sidebarProfileName');

  // For navbar/sidebar always show the LOGGED-IN user (currentUserId data).
  // If viewing own profile, profileData IS the logged-in user.
  if (isOwn) {
    const avatarUrl = resolveAvatarUrl(p.profile_picture_url);
    if (navAvatar)  { navAvatar.src  = avatarUrl; navAvatar.onerror  = () => { navAvatar.src  = DEFAULT_AVATAR; }; }
    if (navName)    navName.textContent   = p.full_name || 'Profile';
    if (sideAvatar) { sideAvatar.src = avatarUrl; sideAvatar.onerror = () => { sideAvatar.src = DEFAULT_AVATAR; }; }
    if (sideName)   sideName.textContent  = p.full_name || 'Profile';
  }
}

// ── Render Posts ──────────────────────────────────────────────
function renderPosts(posts) {
  const container = document.querySelector('.posts-container');
  if (!container) return;

  if (posts.length === 0 && currentPage === 1) {
    container.innerHTML = `
      <div class="no-posts">
        <i class="fas fa-feather-alt"></i>
        No posts yet.
      </div>`;
    return;
  }

  container.innerHTML = '';
  appendPosts(posts);
}

function appendPosts(posts) {
  const container = document.querySelector('.posts-container');
  if (!container) return;

  posts.forEach(post => {
    const card = buildPostCard(post);
    container.insertAdjacentHTML('beforeend', card);
  });

  // Lazy-load more posts when near the bottom
  if (currentPage < totalPages) {
    const sentinel = document.createElement('div');
    sentinel.id = 'posts-sentinel';
    sentinel.style.height = '1px';
    container.appendChild(sentinel);

    const observer = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting) {
        observer.disconnect();
        loadMorePosts();
      }
    }, { threshold: 0.1 });
    observer.observe(sentinel);
  }
}

function buildPostCard(post) {
  const isOwn   = (profileData && profileData.is_own_profile);
  const name    = profileData ? escapeHtml(profileData.full_name) : 'User';
  const avatar  = profileData ? resolveAvatarUrl(profileData.profile_picture_url) : DEFAULT_AVATAR;
  const liked   = post.user_liked;

  const imageHtml = post.image
    ? `<img src="${escapeHtml(post.image)}" class="post-image" alt="Post image" loading="lazy" onerror="this.style.display='none'"/>`
    : '';

  const optionsHtml = isOwn ? `
    <div class="post-options">
      <button class="post-options-btn" onclick="togglePostOptions(this)" title="Options">
        <i class="fas fa-ellipsis-h"></i>
      </button>
      <div class="post-options-menu">
        <div class="options-item" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit</div>
        <div class="options-item" onclick="archivePost(this)"><i class="fas fa-archive"></i> Archive</div>
        <div class="options-item danger" onclick="deletePost(this)"><i class="fas fa-trash"></i> Delete</div>
      </div>
    </div>` : `
    <div class="post-options">
      <button class="post-options-btn" onclick="togglePostOptions(this)" title="Options">
        <i class="fas fa-ellipsis-h"></i>
      </button>
      <div class="post-options-menu">
        <div class="options-item" onclick="openReportModal(this)"><i class="fas fa-flag"></i> Report</div>
      </div>
    </div>`;

  return `
    <div class="post-card" data-post-id="${post.id}">
      <div class="post-header">
        <img src="${avatar}" class="post-avatar" alt="${name}"
             onerror="this.src='${DEFAULT_AVATAR}'"/>
        <div class="post-meta">
          <div class="post-author">${name}</div>
          <div class="post-time">${escapeHtml(post.created_at)}</div>
        </div>
        ${optionsHtml}
      </div>
      <div class="post-body">
        <p>${escapeHtml(post.content || '')}</p>
        ${imageHtml}
      </div>
      <div class="post-footer">
        <button class="post-action-btn like-btn ${liked ? 'liked' : ''}"
                data-post-id="${post.id}"
                onclick="toggleLike(this)">
          <i class="${liked ? 'fas' : 'far'} fa-heart"></i>
          <span>${post.like_count || 0}</span>
        </button>
        <button class="post-action-btn" onclick="toggleComments(this)">
          <i class="far fa-comment"></i>
          <span>${post.comment_count || 0}</span>
        </button>
        <button class="post-action-btn" onclick="openShareModal(this)">
          <i class="fas fa-share"></i>
          <span>${post.share_count || 0}</span>
        </button>
      </div>
      <div class="comment-section" style="display:none;">
        <div class="comment-input-wrap">
          <input type="text" placeholder="Write a comment…"/>
          <button onclick="addComment(this, ${post.id})">Post</button>
        </div>
      </div>
    </div>`;
}

// ── Skeleton Loading ──────────────────────────────────────────
function applySkeleton(on) {
  const targets = [
    document.getElementById('profileName'),
    document.getElementById('profileUsername'),
    document.getElementById('profileStats'),
  ];
  targets.forEach(el => {
    if (!el) return;
    if (on) {
      el.classList.add('skeleton-pulse');
      el._savedText = el.innerHTML;
      el.innerHTML  = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    } else {
      el.classList.remove('skeleton-pulse');
    }
  });

  const container = document.querySelector('.posts-container');
  if (container && on && container.children.length === 0) {
    container.innerHTML = '<div class="posts-loading"><i class="fas fa-spinner fa-spin"></i></div>';
  }
}

// ── Edit Profile Modal ────────────────────────────────────────
function openEditModal() {
  if (!profileData) return;

  // Pre-fill form fields
  const epName = document.getElementById('epName');
  const epUsername = document.getElementById('epUsername');
  const epBio  = document.getElementById('epBio');

  if (epName)     epName.value     = profileData.full_name || '';
  if (epUsername) epUsername.value = profileData.email ? profileData.email.split('@')[0] : profileData.user_id;
  if (epBio)      epBio.value      = profileData.bio || '';

  // Pre-fill avatar preview
  const epAvatar = document.getElementById('epAvatarPreview');
  if (epAvatar) {
    epAvatar.src = resolveAvatarUrl(profileData.profile_picture_url);
    epAvatar.onerror = function () { this.src = DEFAULT_AVATAR; };
  }

  // Pre-fill banner preview
  const epBanner = document.getElementById('epBannerPreview');
  if (epBanner && profileData.cover_photo_url) {
    epBanner.style.backgroundImage  = `url('${profileData.cover_photo_url}')`;
    epBanner.style.backgroundSize   = 'cover';
    epBanner.style.backgroundPosition = 'center';
  }

  document.getElementById('editProfileModal').classList.add('show');
}

function closeEditModal() {
  document.getElementById('editProfileModal').classList.remove('show');
}

// ── Save Profile (name + bio) ─────────────────────────────────
async function saveProfile() {
  const epName = document.getElementById('epName');
  const epBio  = document.getElementById('epBio');

  const fullName  = (epName ? epName.value.trim() : '').split(' ');
  const firstName = fullName[0] || '';
  const lastName  = fullName.slice(1).join(' ') || '.';
  const bio       = epBio ? epBio.value.trim() : '';

  if (!firstName) {
    showToast('Please enter a display name.');
    return;
  }

  const formData = new FormData();
  formData.append('user_id',    currentUserId);
  formData.append('first_name', firstName);
  formData.append('last_name',  lastName);
  formData.append('bio',        bio);

  // Include any newly selected avatar/banner files
  const epAvatarFile = document.getElementById('epAvatarInput')?.files?.[0];
  const epBannerFile = document.getElementById('epBannerInput')?.files?.[0];
  if (epAvatarFile) formData.append('profile_picture', epAvatarFile);
  if (epBannerFile) formData.append('cover_photo', epBannerFile);

  try {
    const res  = await fetch(API_BASE + 'update-profile.php', {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data.success) {
      // Reflect changes immediately in the UI
      const nameEl = document.getElementById('profileName');
      if (nameEl) {
        nameEl.innerHTML = `${escapeHtml(firstName + ' ' + lastName).trim()} <i class="fas fa-check-circle verified-badge"></i>`;
      }
      const bioEl = document.getElementById('profileBio');
      if (bioEl) bioEl.textContent = bio;

      // Update cached profile data
      if (profileData) {
        profileData.full_name  = (firstName + ' ' + lastName).trim();
        profileData.bio        = bio;
        if (data.profile_picture) profileData.profile_picture_url = '/main/api/users/user/uploads/profiles/' + data.profile_picture;
        if (data.cover_photo)     profileData.cover_photo_url     = '/main/api/users/user/uploads/covers/'   + data.cover_photo;
      }

      closeEditModal();
      showToast('Profile updated! ✨');
    } else {
      showToast(data.error || 'Update failed.');
    }
  } catch (err) {
    console.error('Save profile error:', err);
    showToast('Could not save profile. Please try again.');
  }
}

// ── Avatar upload (header click) ─────────────────────────────
async function uploadProfilePic(file) {
  const formData = new FormData();
  formData.append('user_id', currentUserId);
  formData.append('profile_picture', file);

  try {
    const res  = await fetch(API_BASE + 'update-profile-pic.php', {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data.success) {
      const url = data.profile_picture || URL.createObjectURL(file);
      const avatarEl = document.getElementById('profileAvatar');
      if (avatarEl) avatarEl.src = url;
      const navAvatar = document.getElementById('navbarAvatar');
      if (navAvatar) navAvatar.src = url;
      const sideAvatar = document.getElementById('sidebarAvatar');
      if (sideAvatar) sideAvatar.src = url;
      showToast('Profile picture updated!');
    } else {
      showToast(data.error || 'Upload failed.');
    }
  } catch (err) {
    console.error('Avatar upload error:', err);
    showToast('Upload failed. Please try again.');
  }
}

// ── Cover photo upload (header click) ────────────────────────
async function uploadCoverPhoto(file) {
  const formData = new FormData();
  formData.append('user_id', currentUserId);
  formData.append('cover_photo', file);

  try {
    const res  = await fetch('../api/users/user/uploads/covers/update-cover-pic.php', {
      method: 'POST',
      body: formData,
    });
    const data = await res.json();

    if (data.success) {
      const url = data.cover_photo || URL.createObjectURL(file);
      const banner = document.getElementById('profileBanner');
      if (banner) {
        banner.style.backgroundImage    = `url('${url}')`;
        banner.style.backgroundSize     = 'cover';
        banner.style.backgroundPosition = 'center';
      }
      showToast('Cover photo updated!');
    } else {
      showToast(data.error || 'Upload failed.');
    }
  } catch (err) {
    console.error('Cover upload error:', err);
    showToast('Upload failed. Please try again.');
  }
}

// ── Avatar seed picker (modal) ────────────────────────────────
function selectSeed(el) {
  document.querySelectorAll('.ep-avatar-seed-opt').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  const preview = document.getElementById('epAvatarPreview');
  if (preview) preview.src = el.src;
}

// ── Follow toggle (placeholder — no follow table in DB) ──────
function toggleFollow(btn) {
  const isFollowing = btn.classList.contains('following');
  const icon = btn.querySelector('i');
  const text = btn.querySelector('span');

  if (isFollowing) {
    btn.classList.remove('following');
    if (icon) icon.className = 'fas fa-user-plus';
    if (text) text.textContent = 'Follow';
    showToast('Unfollowed');
  } else {
    btn.classList.add('following');
    if (icon) icon.className = 'fas fa-user-check';
    if (text) text.textContent = 'Following';
    showToast('Now following!');
  }
}
