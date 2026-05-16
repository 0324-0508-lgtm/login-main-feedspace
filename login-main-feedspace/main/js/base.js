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
