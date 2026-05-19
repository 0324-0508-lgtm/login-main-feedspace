// ============================================
//  base.js — Shared logic for all pages
//  FeedSpace | Where Ka-Piyu Connects
// ============================================

// ---- Dropdown Toggling ----

function toggleDropdown(id) {
  // Close all other dropdowns first
  document.querySelectorAll('.dropdown').forEach(function(d) {
    if (d.id !== id) d.classList.remove('show');
  });

  const el = document.getElementById(id);
  if (el) el.classList.toggle('show');
}

// Close dropdowns when clicking outside nav-actions area
document.addEventListener('click', function(e) {
  if (!e.target.closest('.nav-actions')) {
    document.querySelectorAll('.dropdown').forEach(function(d) {
      d.classList.remove('show');
    });
  }
});

// ---- Toast Notifications ----

let _toastTimer = null;

function showToast(message, type = 'success') {
  let toast = document.getElementById('globalToast');

  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'globalToast';
    toast.className = 'toast';
    document.body.appendChild(toast);
  }

  toast.textContent = message;
  toast.classList.remove('success', 'warning', 'error');
  toast.classList.add('show', type);

  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(function() {
    toast.classList.remove('show');
  }, 2600);
}

// ---- Confirm Delete Profile ----

function confirmDelete() {
  if (confirm('Are you sure you want to delete your profile? This cannot be undone.')) {
    showToast('Profile deletion requested.');
  }
}

// ---- Like Toggle (DB-backed) ----
// Uses session user_id (backend validates auth).
async function toggleLike(btn) {
  const postCard = btn.closest('.post-card');
  const postId = btn?.dataset?.postId || postCard?.dataset?.postId;
  if (!postId) {
    showToast('Error: Post ID not found', 'error');
    return;
  }

  // prevent double click
  if (btn.disabled) return;
  btn.disabled = true;

  const span = btn.querySelector('span');
  const icon = btn.querySelector('i');
  const oldIconClass = icon ? icon.className : '';
  const oldCount = span ? parseInt(span.textContent) || 0 : 0;

  try {
    const res = await fetch('../api/users/interactions/toggle-post-like.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: parseInt(postId, 10) })
    });

    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      throw new Error('Invalid JSON from server');
    }

    if (!data?.success) throw new Error(data?.error || 'Failed to toggle like');

    // Update UI from server response
    const isLiked = !!data.is_liked;
    const newCount = Number(data.likesCount ?? data.like_count ?? oldCount);

    btn.classList.toggle('liked', isLiked);
    if (icon) {
      icon.className = isLiked ? 'fas fa-heart' : 'far fa-heart';
    }
    if (span) span.textContent = String(newCount);
  } catch (err) {
    console.error('toggleLike error:', err);
    // rollback icon class/count
    if (icon) icon.className = oldIconClass;
    if (span) span.textContent = String(oldCount);
    showToast('Failed to like: ' + err.message, 'error');
  } finally {
    btn.disabled = false;
  }
}

