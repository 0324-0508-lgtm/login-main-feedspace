const notificationsData = [];

function getUserIdFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return params.get('user_id') || params.get('userId');
}

function getCurrentUserId() {
  if (window.currentUserId) return window.currentUserId;
  const storedUserId = localStorage.getItem('currentUserId');
  if (storedUserId) return storedUserId;
  const urlUserId = getUserIdFromUrl();
  if (urlUserId) return urlUserId;
  console.warn('Notifications: no user id found in URL, localStorage, or global script.');
  return null;
}

function formatNotificationTime(timestamp, fallback) {
  if (!timestamp) return fallback || '';
  const date = new Date(timestamp);
  if (isNaN(date.getTime())) return fallback || timestamp;
  const now = new Date();
  const diffMin = Math.floor((now - date) / 60000);
  if (diffMin < 1) return 'Just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHr = Math.floor(diffMin / 60);
  if (diffHr < 24) return `${diffHr}h ago`;
  const diffDay = Math.floor(diffHr / 24);
  return `${diffDay}d ago`;
}

function getNotificationLink(notification) {
  if (!notification) return '#';
  if (notification.link) return notification.link;
  if (notification.message && notification.message.toLowerCase().includes('announcement')) {
    return 'announcements.html';
  }
  return '#';
}

function renderNotifications() {
  const dropdownList = document.getElementById('notifList');
  const panelList = document.getElementById('notificationsMiniList');

  const htmlItems = notificationsData.map(function(item, index) {
    const readClass = item.is_read ? ' read' : '';
    const timeLabel = item.time_formatted || item.timestamp || item.time || 'Just now';
    return `
      <div class="notif-item${readClass}" onclick="handleNotificationClick(${index})">
        <div class="notif-dot${readClass}"></div>
        <div>
          <div class="notif-title">${item.message}</div>
          <div class="notif-time">${formatNotificationTime(item.timestamp, timeLabel)}</div>
        </div>
      </div>
    `;
  }).join('');

  if (dropdownList) {
    dropdownList.innerHTML = htmlItems || '<div class="notif-empty">No notifications yet.</div>';
  }
  if (panelList) {
    panelList.innerHTML = notificationsData.slice(0, 3).map(function(item, index) {
      const readClass = item.is_read ? ' read' : '';
      const timeLabel = item.time_formatted || item.timestamp || item.time || 'Just now';
      return `
        <div class="notif-item${readClass}" onclick="handleNotificationClick(${index})">
          <div class="notif-dot${readClass}"></div>
          <div>
            <div class="notif-title">${item.message}</div>
            <div class="notif-time">${formatNotificationTime(item.timestamp, timeLabel)}</div>
          </div>
        </div>
      `;
    }).join('') || '<div class="notif-empty">No notifications yet.</div>';
  }

  updateNotificationBadge();
}

async function markNotificationRead(notifId) {
  if (!notifId) return;
  const userId = getCurrentUserId();

  try {
    const body = { notifId };
    if (userId) {
      body.userId = userId;
    }

    const response = await fetch('../api/users/notifications/mark-read.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(body)
    });
    const json = await response.json();
    if (json.success) {
      const badge = document.getElementById('notifCount');
      if (badge) badge.textContent = json.unreadCount;
    }
  } catch (error) {
    console.error('Failed to mark notification read:', error);
  }
}

async function handleNotificationClick(index) {
  const notification = notificationsData[index];
  if (!notification) return;

  if (!notification.is_read) {
    notification.is_read = 1;
    await markNotificationRead(notification.notif_id);
  }

  renderNotifications();

  const link = getNotificationLink(notification);
  if (link && link !== '#') {
    window.location.href = link;
    return;
  }

  if (notification.message) {
    showToast(`Opened notification: ${notification.message}`);
  }
}

function updateNotificationBadge() {
  const badge = document.getElementById('notifCount');
  if (!badge) return;

  const unreadCount = notificationsData.filter(function(item) {
    return !item.is_read;
  }).length;

  badge.textContent = unreadCount;
  badge.style.display = unreadCount > 0 ? 'inline-flex' : 'none';
}

async function loadNotifications() {
  const userId = getCurrentUserId();
  const apiUrl = userId
    ? `../api/users/notifications/get-notif.php?userId=${encodeURIComponent(userId)}&limit=20`
    : '../api/users/notifications/get-notif.php?limit=20';

  try {
    const response = await fetch(apiUrl, { credentials: 'same-origin' });
    const json = await response.json();
    if (json.success && json.data && Array.isArray(json.data.notifications)) {
      notificationsData.splice(0, notificationsData.length, ...json.data.notifications);
    } else {
      console.warn('Notifications API returned no data', json);
      notificationsData.splice(0, notificationsData.length);
    }
  } catch (error) {
    console.error('Failed to load notifications:', error);
    notificationsData.splice(0, notificationsData.length);
  }

  renderNotifications();
}

document.addEventListener('DOMContentLoaded', loadNotifications);
