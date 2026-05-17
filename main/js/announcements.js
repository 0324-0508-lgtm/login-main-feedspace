const announcementsData = [];

function getBadge(item) {
  if (item.is_pinned) return { text: '📌 Pinned', className: 'red' };
  if (item.priority === 'urgent') return { text: '⚠️ Urgent', className: 'red' };
  if (item.priority === 'high') return { text: '🔥 High', className: 'yellow' };
  if (item.priority === 'low') return { text: 'ℹ️ Update', className: 'blue' };
  return { text: '📢 Announcement', className: 'green' };
}

function formatDate(dateString) {
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) {
    return dateString;
  }
  return date.toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });
}

function formatRelativeTime(dateString) {
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return dateString;

  const diffMs = Date.now() - date.getTime();
  const diffMinutes = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMinutes / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  return `${diffDays}d ago`;
}

function renderAnnouncementsList() {
  const list = document.getElementById('announcementsList');
  if (!list) return;

  if (announcementsData.length === 0) {
    list.innerHTML = '<div class="announcement-empty">No announcements available right now.</div>';
    return;
  }

  list.innerHTML = announcementsData.map(function(item) {
    const badge = getBadge(item);
    return `
      <div class="announcement-card">
        <div class="ann-card-top">
          <div class="ann-card-icon"><i class="fas fa-bullhorn"></i></div>
          <div class="ann-card-title-group">
            <div class="ann-badge ${badge.className}">${badge.text}</div>
            <h3>${item.title}</h3>
            <div class="ann-card-subtitle">
              <span>${item.audience || 'All Members'}</span>
              <span class="ann-dot-separator">•</span>
              <span>${item.status === 'approved' ? 'Posted' : 'Pending Review'}</span>
            </div>
          </div>
        </div>
        <p class="ann-card-text">${item.description || 'No details available.'}</p>
        <div class="ann-footer">
          <span><i class="fas fa-calendar-day"></i> ${formatDate(item.created_at)}</span>
          <span><i class="fas fa-clock"></i> ${formatRelativeTime(item.created_at)}</span>
        </div>
      </div>
    `;
  }).join('');
}

function renderAnnouncementsMiniList() {
  const list = document.getElementById('announcementsMiniList');
  if (!list) return;

  if (announcementsData.length === 0) {
    list.innerHTML = '<div class="ann-mini-empty">No announcements at the moment.</div>';
    return;
  }

  list.innerHTML = announcementsData.slice(0, 3).map(function(item) {
    const badge = getBadge(item);
    return `
      <div class="ann-mini-item" onclick="window.location.href='announcements.html'">
        <div class="ann-dot ${badge.className}"></div>
        <div>
          <div class="ann-mini-title">${item.title}</div>
          <div class="ann-mini-desc">${item.description || 'View announcement details'}</div>
          <div class="ann-mini-time">${formatRelativeTime(item.created_at)}</div>
        </div>
      </div>
    `;
  }).join('');
}

async function loadAnnouncements() {
  try {
    const response = await fetch('../api/users/announcements/get-announcements.php');
    const json = await response.json();

    if (json.success && Array.isArray(json.announcements)) {
      announcementsData.splice(0, announcementsData.length, ...json.announcements);
    } else {
      console.error('Announcements fetch failed', json);
    }
  } catch (error) {
    console.error('Announcements fetch error', error);
  } finally {
    renderAnnouncementsList();
    renderAnnouncementsMiniList();
  }
}

function initAnnouncements() {
  loadAnnouncements();
}

document.addEventListener('DOMContentLoaded', initAnnouncements);
