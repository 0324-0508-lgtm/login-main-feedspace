(function() {
  var userIdEl = document.getElementById('postsContainer');
  var userId = userIdEl ? userIdEl.dataset.userId : (window.__profileUserId || '');
  
  console.log('=== PROFILE DEBUG ===');
  console.log('postsContainer found:', !!userIdEl);
  console.log('data-user-id:', userIdEl ? userIdEl.dataset.userId : 'null');
  console.log('window.__profileUserId:', window.__profileUserId || 'not set');
  console.log('Final userId:', userId);
  console.log('=====================');
  
  if (userId) {
    loadProfilePosts(userId, 1);
  } else {
    console.error('No user_id found');
    var container = document.getElementById('postsContainer');
    if (container) {
      container.innerHTML = '<p class="error">User ID not found. Please log in again.</p>';
    }
  }
})();

async function loadProfilePosts(userId, page) {
    page = page || 1;
    try {
        var container = document.getElementById('postsContainer');
        if (!container) {
            console.error('No posts container found');
            return;
        }

        const response = await fetch('../api/users/posts/get-profile-posts.php?user_id=' + encodeURIComponent(userId) + '&page=' + page);
        const text = await response.text();

        if (text.trim().startsWith('<')) {
            console.error('Server returned HTML error:', text.substring(0, 500));
            container.innerHTML = '<p class="error">Server error</p>';
            throw new Error('Server error');
        }

        const data = JSON.parse(text);

        if (data.success && data.posts) {
            container.innerHTML = '';
            if (data.posts.length === 0) {
                container.innerHTML = '<p class="no-posts">No posts yet.</p>';
            } else {
                data.posts.forEach(function(post) {
                    container.appendChild(buildPostCard(post));
                });
            }
        } else {
            container.innerHTML = '<p class="error">' + (data.error || 'Failed to load posts') + '</p>';
        }

    } catch (error) {
        console.error('Failed to load posts:', error);
        var container = document.getElementById('postsContainer');
        if (container) {
            container.innerHTML = '<p class="error">Failed to load posts. Please refresh.</p>';
        }
    }
}

function buildPostCard(post) {
  var card = document.createElement('div');
  card.className = 'post-card';
  card.dataset.postId = post.post_id;

  var name = escapeHtml(post.full_name || 'Unknown');
  var avatar = post.profile_picture || '../assets/default.jpg';
  var time = escapeHtml(post.created_at || '');
  var content = escapeHtml(post.content || '');
  var likes = post.like_count || 0;
  var comments = post.comment_count || 0;
  var liked = post.user_liked ? ' liked' : '';
  var heart = post.user_liked ? 'fas' : 'far';

  var img = '';
  if (post.image) {
    img = '<div class="image-grid"><img src="' + escapeHtml(post.image) + '" class="post-image" onerror="this.style.display=\'none\'"></div>';
  }

  var shared = '';
  if (post.is_shared && post.original) {
    var o = post.original;
    var oName = escapeHtml(o.full_name || 'Unknown');
    var oAvatar = o.profile_picture || '../assets/default.jpg';
    var oTime = escapeHtml(o.created_at || '');
    var oContent = escapeHtml(o.content || '');
    var oImg = '';
    if (o.image) {
      oImg = '<div class="sp-image-wrap"><img src="' + escapeHtml(o.image) + '" class="sp-image" onerror="this.style.display=\'none\'"></div>';
    }
    shared = '<div class="shared-post-card"><div class="sp-header"><img src="' + oAvatar + '" class="sp-avatar" onerror="this.src=\'../assets/default.jpg\'"><div class="sp-meta"><span class="sp-author">' + oName + '</span><span class="sp-time">' + oTime + '</span></div></div><div class="sp-body"><p class="sp-content">' + nl2br(oContent) + '</p>' + oImg + '</div></div>';
  }

  card.innerHTML = '<div class="post-header"><img src="' + avatar + '" class="post-avatar" onerror="this.src=\'../assets/default.jpg\'"><div class="post-meta"><div class="post-author">' + name + '</div><div class="post-community">Community &middot; ' + time + '</div></div><button class="options-btn" onclick="toggleOptions(this)"><i class="fas fa-ellipsis-h"></i></button><div class="post-options-menu"><div class="post-option" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit</div><div class="post-option danger" onclick="deletePost(this)"><i class="fas fa-trash"></i> Delete</div><div class="post-option" onclick="reportPost(this)"><i class="fas fa-flag"></i> Report</div></div></div><div class="post-body"><p>' + nl2br(content) + '</p>' + shared + img + '</div><div class="post-footer"><button class="reaction-btn' + liked + '" data-post-id="' + post.post_id + '" onclick="doLike(this)"><i class="' + heart + ' fa-heart"></i> ' + likes + '</button><button class="reaction-btn" onclick="toggleComments(this)"><i class="fas fa-comment"></i> ' + comments + '</button><button class="reaction-btn" onclick="sharePost(this)"><i class="fas fa-share"></i> Share</button></div><div class="comment-section" style="display:none;"><div class="comment-input-row"><img src="../assets/default.jpg"><div class="comment-input-wrap"><input type="text" placeholder="Write a comment..."><button class="comment-send-btn" onclick="sendComment(this)"><i class="fas fa-paper-plane"></i></button></div></div></div>';

  return card;
}

function escapeHtml(t) {
  var d = document.createElement('div');
  d.textContent = t;
  return d.innerHTML;
}

function nl2br(s) {
  return s.replace(/\n/g, '<br>');
}

function toggleOptions(btn) {
  var m = btn.nextElementSibling;
  if (m) m.classList.toggle('show');
}

function doLike(btn) {
  var id = btn.dataset.postId;
  var liked = btn.classList.contains('liked');
  fetch('../api/users/posts/like-post.php', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'post_id=' + id + '&action=' + (liked ? 'unlike' : 'like')
  })
  .then(function(r){ return r.text(); })
  .then(function(text) {
    if (text.trim().startsWith('<')) {
      console.error('Server error:', text.substring(0, 500));
      throw new Error('Server returned HTML');
    }
    return JSON.parse(text);
  })
  .then(function(res) {
    if (res.success) {
      btn.classList.toggle('liked');
      var i = btn.querySelector('i');
      var s = btn.querySelector('span') || btn.lastChild;
      if (i) i.className = liked ? 'far fa-heart' : 'fas fa-heart';
      var n = parseInt(s.textContent || s.nodeValue || 0);
      s.textContent = ' ' + (liked ? n - 1 : n + 1);
    }
  })
  .catch(function(err) {
    console.error('Like failed:', err);
  });
}

function toggleComments(btn) {
  var card = btn.closest('.post-card');
  var sec = card.querySelector('.comment-section');
  if (sec) sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
}

function sendComment(btn) {
  var wrap = btn.closest('.comment-input-wrap');
  var inp = wrap.querySelector('input');
  var text = inp.value.trim();
  if (!text) return;
  var card = btn.closest('.post-card');
  var id = card.dataset.postId;
  fetch('../api/users/posts/add-comment.php', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'post_id=' + id + '&content=' + encodeURIComponent(text)
  })
  .then(function(r){ return r.text(); })
  .then(function(text) {
    if (text.trim().startsWith('<')) {
      console.error('Server error:', text.substring(0, 500));
      throw new Error('Server returned HTML');
    }
    return JSON.parse(text);
  })
  .then(function(res) {
    if (res.success) inp.value = '';
  })
  .catch(function(err) {
    console.error('Comment failed:', err);
  });
}

function deletePost(btn) {
  if (!confirm('Delete?')) return;
  var card = btn.closest('.post-card');
  var id = card.dataset.postId;
  fetch('../api/users/posts/delete-post.php', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'post_id=' + id
  })
  .then(function(r){ return r.text(); })
  .then(function(text) {
    if (text.trim().startsWith('<')) {
      console.error('Server error:', text.substring(0, 500));
      throw new Error('Server returned HTML');
    }
    return JSON.parse(text);
  })
  .then(function(res) {
    if (res.success) {
      card.style.opacity = '0';
      setTimeout(function(){ card.remove(); }, 300);
      showToast('Deleted');
    }
  })
  .catch(function(err) {
    console.error('Delete failed:', err);
  });
}

function editPost(btn) { showToast('Coming soon', 'warning'); }
function reportPost(btn) { showToast('Coming soon', 'warning'); }
function sharePost(btn) { showToast('Coming soon', 'warning'); }

function showToast(msg, type) {
  type = type || 'success';
  var t = document.createElement('div');
  t.className = 'toast-message toast-' + type;
  t.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
  var c = document.getElementById('toastContainer');
  if (c) c.appendChild(t);
  setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 300); }, 3000);
}