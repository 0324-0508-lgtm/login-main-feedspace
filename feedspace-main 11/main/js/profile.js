// ============================================
//  profile.js — Logic for profile.html
// ============================================

// ---- Edit Profile Modal ----

function openEditModal() {
  document.getElementById('editProfileModal').classList.add('show');
}

function closeEditModal() {
  document.getElementById('editProfileModal').classList.remove('show');
}

// Close modal when clicking the overlay background
document.addEventListener('DOMContentLoaded', function() {
  const overlay = document.getElementById('editProfileModal');
  if (overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) closeEditModal();
    });
  }
});

// ---- Save Profile Changes ----

function saveProfile() {
  const nameInput = document.getElementById('editName');
  const bioInput  = document.getElementById('editBio');

  const name = nameInput ? nameInput.value.trim() : '';
  const bio  = bioInput  ? bioInput.value.trim()  : '';

  if (name) {
    document.getElementById('profileName').textContent = name;
  }

  if (bio) {
    document.getElementById('profileBio').textContent = bio;
  }

  closeEditModal();
  showToast('Profile updated! ✨');
}

// ---- Post Options (same as feed, needed on profile posts) ----

function togglePostOptions(btn) {
  const menu = btn.nextElementSibling;

  document.querySelectorAll('.post-options-menu').forEach(function(m) {
    if (m !== menu) m.classList.remove('show');
  });

  menu.classList.toggle('show');
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.post-card')) {
    document.querySelectorAll('.post-options-menu').forEach(function(m) {
      m.classList.remove('show');
    });
  }
});

function closeOptions(el) {
  el.closest('.post-options-menu').classList.remove('show');
}

function editPost(el) {
  const card = el.closest('.post-card');
  const body = card.querySelector('.post-body p');
  const current = body ? body.innerText : '';

  const newText = prompt('Edit your post:', current);

  if (newText !== null && newText.trim() !== '') {
    if (body) body.innerText = newText;
    showToast('Post updated!');
  }

  closeOptions(el);
}

function deletePost(el) {
  const card = el.closest('.post-card');

  if (confirm('Delete this post?')) {
    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-8px)';
    setTimeout(function() { card.remove(); }, 290);
    showToast('Post deleted.');
  }

  closeOptions(el);
}

function archivePost(el) {
  const card = el.closest('.post-card');
  card.style.transition = 'opacity 0.3s';
  card.style.opacity = '0.38';
  showToast('Post archived!');
  closeOptions(el);
}

function toggleLike(btn) {
  const isLiked = btn.classList.contains('liked');
  const span = btn.querySelector('span');
  let count = parseInt(span.textContent) || 0;

  if (isLiked) {
    btn.classList.remove('liked');
    btn.querySelector('i').className = 'far fa-heart';
    span.textContent = count - 1;
  } else {
    btn.classList.add('liked');
    btn.querySelector('i').className = 'fas fa-heart';
    span.textContent = count + 1;
  }
}

function toggleFollow(btn) {
  const isFollowing = btn.classList.contains('following');
  const icon = btn.querySelector('i');
  const text = btn.querySelector('span');

  if (isFollowing) {
    btn.classList.remove('following');
    icon.className = 'fas fa-user-plus';
    text.textContent = 'Follow';
    showToast('Unfollowed');
  } else {
    btn.classList.add('following');
    icon.className = 'fas fa-user-check';
    text.textContent = 'Following';
    showToast('You are now following Kim Ballebar');
  }
}
