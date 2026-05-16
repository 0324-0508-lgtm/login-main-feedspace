// feed.js — Home Feed Logic

function togglePostOptions(btn) {
  const menu = btn.nextElementSibling;
  document.querySelectorAll('.post-options-menu').forEach(m => { if (m !== menu) m.classList.remove('show'); });
  menu.classList.toggle('show');
}

function getCurrentUserId() {
  const stored = window.user_id ?? window.USER_ID ?? window.userId ?? window.currentUserId;
  if (stored) return stored;
  return localStorage.getItem('currentUserId') || localStorage.getItem('user_id') || localStorage.getItem('userId') || null;
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.post-card')) {
    document.querySelectorAll('.post-options-menu').forEach(m => m.classList.remove('show'));
  }
});

function closeOptions(el) { el.closest('.post-options-menu').classList.remove('show'); }

function editPost(el) {
  const card = el.closest('.post-card');
  const body = card.querySelector('.post-body p');
  const newText = prompt('Edit your post:', body ? body.innerText : '');
  if (newText !== null && newText.trim()) { if (body) body.innerText = newText; showToast('Post updated!'); }
  closeOptions(el);
}

function deletePost(el) {
  const card = el.closest('.post-card');
  if (confirm('Delete this post?')) {
    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0'; card.style.transform = 'translateY(-8px)';
    setTimeout(() => card.remove(), 290);
    showToast('Post deleted.');
  }
  closeOptions(el);
}

function archivePost(el) {
  // "Archive" should behave like delete in this UI
  deletePost(el);
}

function toggleLike(btn) {
  const postId = btn?.dataset?.postId;
  if (!postId) {
    // If UI is using mock/static cards without data-post-id, ignore.
    // Real feed cards must include data-post-id for backend toggling.
    return;
  }


  const isLiked = btn.classList.contains('liked');
  const span = btn.querySelector('span');
  if (span) span.textContent = span.textContent; // no-op to keep UI stable

  fetch('../api/users/interactions/toggle-post-like.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ post_id: parseInt(postId, 10) })
  })
    .then(r => r.json())
    .then(res => {
      if (!res || !res.success) throw new Error(res?.error || 'Toggle failed');

      const nextLiked = !!res.is_liked;
      btn.classList.toggle('liked', nextLiked);
      const icon = btn.querySelector('i');
      if (icon) icon.className = nextLiked ? 'fas fa-heart' : 'far fa-heart';
      if (span) span.textContent = String(res.likesCount ?? 0);

      // Update button label if needed (future-proof)
    })
    .catch(() => {
      showToast(isLiked ? 'Failed to unlike' : 'Failed to like');
    });
}


function toggleComments(btn) {
  const card = btn.closest('.post-card');
  const section = card.querySelector('.comment-section');
  if (section) {
    const isHidden = section.style.display === 'none' || !section.style.display;
    section.style.display = isHidden ? 'block' : 'none';
    if (isHidden) section.querySelector('input').focus();
  }
}

function addComment(btn) {
  const wrap = btn.closest('.comment-input-wrap');
  const input = wrap.querySelector('input');
  const text = input.value.trim();
  
  if (!text) {
    showToast('Write something first!');
    return;
  }
  
  // Get post ID from the card
  const card = btn.closest('.post-card');
  const likeBtn = card.querySelector('[data-post-id]');
  const postId = likeBtn ? likeBtn.dataset.postId : null;
  
  if (!postId) {
    showToast('Error: Post ID not found');
    return;
  }
  
  // Disable button while saving
  btn.disabled = true;
  const originalIcon = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  
  // Send comment to backend with AI moderation
  fetch(`../api/users/interactions/add-comments.php?id=${postId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
      postId: parseInt(postId),
      text: text 
    })
  })
    .then(r => r.json())
    .then(res => {
      if (!res || !res.success) {
        throw new Error(res?.message || 'Failed to add comment');
      }
      
      // Show warning if moderated
      if (res.warning) {
        showToast(res.warning, 'warning');
      } else {
        showToast('Comment added! 💬');
      }
      
      // Clear input
      input.value = '';
      
      // Add comment to DOM only if it's not removed
      if (res.moderation_status !== 'removed') {
        const section = btn.closest('.comment-section');
        const item = document.createElement('div');
        item.className = 'comment-item';
        
        // Add moderation badge if flagged
        let modBadge = '';
        if (res.moderation_status === 'flagged') {
          modBadge = '<span class="mod-badge flagged" title="This comment is flagged for review">⚠️ Under Review</span>';
        }
        
        item.innerHTML = `
          <img src="${escapeAttr(res.avatar)}" alt="User"/>
          <div class="comment-bubble">
            <div class="comment-author">${escapeHtml(res.author)}</div>
            <div class="comment-text">${escapeHtml(res.text)}</div>
            ${modBadge}
          </div>`;
        section.appendChild(item);
      }
      
      // Update comment count
      const commentBtn = card.querySelector('.reaction-btn:has(i.fa-comment)');
      if (commentBtn) {
        const span = commentBtn.querySelector('span');
        if (span) span.textContent = String(res.comment_count || 0);
      }
    })
    .catch(err => {
      console.error('Comment error:', err);
      showToast('Failed to add comment');
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = originalIcon;
    });
}

function openPostModal(prefill) {
  const ta = document.getElementById('modalPostText');
  if (ta) ta.value = prefill || '';
  document.getElementById('postModal').classList.add('show');
  if (ta) ta.focus();
  
  // Add image preview listener
  const fileInput = document.getElementById('modalPostImage');
  if (fileInput) {
    fileInput.onchange = function(e) {
      const file = e.target.files[0];
      if (file) {
        console.log('Image selected:', file.name, file.size, file.type);
      }
    };
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
}


function submitPost(e) {
  // Prevent modal overlay click/propagation edge cases
  if (e && typeof e.preventDefault === 'function') e.preventDefault();
  if (e && typeof e.stopPropagation === 'function') e.stopPropagation();

  const ta = document.getElementById('modalPostText');
  const text = ta ? ta.value.trim() : '';
  const fileInput = document.getElementById('modalPostImage');
  const hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);
  
  console.log('submitPost called:', { text: text.length, hasFile });
  
  if (!text && !hasFile) { 
    showToast('Write something or attach a photo/file first!'); 
    return; 
  }

  // Disable button while saving (always use the real modal submit button)
  const btn = document.querySelector('#postModal .btn-primary');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Creating...';
  }

  const form = new FormData();
  form.append('content', text);

  // Backend fallback: include explicit user_id if the JS auth layer exposes it.
  // create-post.php supports: $_SESSION['user_id'] OR $_POST['user_id']
  const uid = getCurrentUserId();
  console.log('submitPost user_id fallback:', uid);
  if (uid) {
    form.append('user_id', String(uid));
  }

  // Add image file if selected
  if (fileInput && fileInput.files && fileInput.files[0]) {
    console.log('Image file:', fileInput.files[0].name, fileInput.files[0].size);
    form.append('image', fileInput.files[0]);
  }

  console.log('Sending POST to create-post.php...');

fetch('../api/users/posts/create-post.php', {
    method: 'POST',
    credentials: 'include',
    body: form
  })
    .then(async r => {
      console.log('Response status:', r.status);
      const text = await r.text();
      console.log('Raw create-post response:', text);

      let data;
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error("INVALID JSON RESPONSE:", text);
        throw new Error("Invalid JSON response from create-post API");
      }

      if (!data || !data.success) {
        throw new Error(data?.error || 'Create failed - unknown error');
      }

      if (data.warning) {
        showToast(data.warning, 'warning');
      } else {
        showToast('Post shared! 🎉');
      }

      closeModal('postModal');
      if (ta) ta.value = '';
      if (fileInput) fileInput.value = '';

      console.log('Reloading feed...');
      if (typeof loadFeedPosts === 'function') loadFeedPosts(1, true);
    })
    .catch(err => {
      console.error('Post creation error:', err);
      showToast('Error: ' + err.message, 'error');
    })
    .finally(() => {
      console.log('submitPost finally - re-enabling button');
      if (btn) {
        btn.disabled = false;
        btn.textContent = '+ Create Post';
      }
    });
}




function openReportModal(el) {
  closeOptions(el);
  document.getElementById('reportModal').classList.add('show');
}

function submitReport() {
  closeModal('reportModal');
  showToast('Post reported. Thank you!');
}

let _sharePostText = '';
function openShareModal(btn) {
  const card = btn.closest('.post-card');
  const body = card.querySelector('.post-body p');
  _sharePostText = body ? body.innerText : '';
  const preview = document.getElementById('sharePostPreview');
  if (preview) preview.textContent = _sharePostText.length > 80 ? _sharePostText.slice(0, 80) + '...' : _sharePostText;
  document.getElementById('shareModal').classList.add('show');
}

function submitShare() {
  closeModal('shareModal');
  showToast('Post shared! 🔁');
}

function createPostCard(post) {

  const card = document.createElement('div');

  card.className = 'post-card';

  const avatar = post.profile_picture || 'http://localhost/assets/default.png';
  const author = post.full_name || 'Unknown';

  card.innerHTML = `
    <div class="post-header">
      <img src="${escapeAttr(avatar)}" alt="User" class="post-avatar"/>
      <div class="post-meta">
        <div class="post-author">${escapeHtml(author)}</div>
        <div class="post-community">Community · <span class="post-time">${escapeHtml(post.created_at || '')}</span></div>
      </div>
      <button class="options-btn" onclick="togglePostOptions(this)"><i class="fas fa-sliders-h"></i></button>
      <div class="post-options-menu">
        <div class="post-option" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit Post</div>
        <div class="post-option danger" onclick="deletePost(this)"><i class="fas fa-trash"></i> Delete Post</div>
        <div class="post-option" onclick="archivePost(this)"><i class="fas fa-archive"></i> Archive</div>
        <div class="post-option" onclick="openReportModal(this)"><i class="fas fa-flag"></i> Report</div>
        <div class="post-option" onclick="openAnnounceModal(this)"><i class="fas fa-bullhorn"></i> Request to Announce</div>
      </div>
    </div>
    <div class="post-body">${post.image ? `<p>${escapeHtml(post.content || '')}</p><div class="image-grid grid-1"><img src="${escapeAttr(post.image)}" alt="Post image" class="post-image"/></div>` : `<p>${escapeHtml(post.content || '')}</p>`}</div>
    <div class="post-footer">
      <button class="reaction-btn ${post.user_liked ? 'liked' : ''}" data-post-id="${post.id}" onclick="toggleLike(this)">
        <i class="${post.user_liked ? 'fas' : 'far'} fa-heart"></i>
        <span>${Number(post.like_count || 0)}</span>
      </button>
      <button class="reaction-btn" onclick="toggleComments(this)"><i class="fas fa-comment"></i> <span>${Number(post.comment_count || 0) ? '' : ''}Comment</span></button>
      <button class="reaction-btn" onclick="openShareModal(this)"><i class="fas fa-share"></i> <span>Share</span></button>
    </div>
    <div class="comment-section" style="display:none;">
      <div class="comment-input-row">
        <img src="http://localhost/assets/default.png" alt="User"/>
        <div class="comment-input-wrap">
          <input type="text" placeholder="Write a comment..."/>
          <button class="comment-send-btn" onclick="addComment(this)"><i class="fas fa-plus"></i></button>
        </div>
      </div>
    </div>`;

  return card;
}

function escapeAttr(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'<').replace(/>/g,'>').replace(/"/g,'"');
}


function escapeHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close modals on overlay click
document.addEventListener('click', function(e) {

  ['postModal','reportModal','shareModal'].forEach(id => {

    const el = document.getElementById(id);
    if (el && e.target === el) el.classList.remove('show');
  });
});

// ---- Dynamic loading (feed) ----
function loadFeedPosts(page = 1, replace = true) {
  const feedPosts = document.getElementById('feedPosts');
  if (!feedPosts) {
    console.error('feedPosts element not found!');
    return;
  }

  console.log('loadFeedPosts called:', { page, replace });

  const uid = getCurrentUserId();
  console.log('loadFeedPosts user_id fallback:', uid);
  const bodyParams = new URLSearchParams();
  bodyParams.append('page', page);
  if (uid) {
    bodyParams.append('user_id', String(uid));
  }

fetch('../api/users/posts/get-posts.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    credentials: 'include',
    body: bodyParams.toString()
  })
    .then(async r => {
      console.log('Feed response status:', r.status);
      const text = await r.text();
      console.log('Feed response length:', text.length);
      
      let res = null;
      try {
        res = JSON.parse(text);
        console.log('Feed JSON parsed:', { success: res.success, postCount: res.posts?.length });
      } catch (err) {
        console.error('Feed JSON parse error:', err.message);
        console.error('First 300 chars:', text.substring(0, 300));
        throw new Error('Invalid JSON response from feed API');
      }
      return res;
    })
    .then(res => {
      if (!res || !res.success) throw new Error(res?.error || 'Failed to load feed');

      if (replace) feedPosts.innerHTML = '';

      const posts = res.posts || [];
      console.log('Rendering ' + posts.length + ' posts');
      
      if (posts.length === 0) {
        feedPosts.innerHTML = '<div style="text-align:center;padding:20px;color:var(--color-subtext);">No posts yet. Be the first to post!</div>';
        return;
      }

      posts.forEach((p, idx) => {
        console.log('Creating post card:', { id: p.id, content: p.content.substring(0, 30) });
        feedPosts.appendChild(createPostCard(p));
      });
    })
    .catch(err => {
      console.error('Feed loading error:', err);
      feedPosts.innerHTML = '<div style="text-align:center;padding:20px;color:red;">Error loading feed: ' + err.message + '</div>';
    });
}

document.addEventListener('DOMContentLoaded', () => {
  // If mock cards are present, replace them with dynamic content.
  loadFeedPosts(1, true);
});

