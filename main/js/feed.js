// feed.js — Home Feed Logic

function togglePostOptions(btn) {
  const menu = btn.nextElementSibling;
  document.querySelectorAll('.post-options-menu').forEach(m => { if (m !== menu) m.classList.remove('show'); });
  menu.classList.toggle('show');
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
  el.closest('.post-card').style.opacity = '0.38';
  showToast('Post archived!'); closeOptions(el);
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
  if (!text) return;
  const section = btn.closest('.comment-section');
  const item = document.createElement('div');
  item.className = 'comment-item';
  item.innerHTML = `<img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Kim" alt="User"/>
    <div class="comment-bubble"><div class="comment-author">Kim Ballebar</div><div class="comment-text">${escapeHtml(text)}</div></div>`;
  section.appendChild(item);
  input.value = '';
}

function openPostModal(prefill) {
  const ta = document.getElementById('modalPostText');
  if (ta) ta.value = prefill || '';
  document.getElementById('postModal').classList.add('show');
  if (ta) ta.focus();
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function submitPost() {
  const ta = document.getElementById('modalPostText');
  const text = ta ? ta.value.trim() : '';
  if (!text) { showToast('Write something first!'); return; }

  // TODO: image upload not wired in UI yet; creating post as text-only
  fetch('../api/users/posts/create-post.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'content=' + encodeURIComponent(text)
  })
    .then(r => r.json())
    .then(res => {
      if (!res || !res.success) throw new Error(res?.error || 'Create failed');
      closeModal('postModal');
      showToast('Post shared! 🎉');
      if (typeof loadFeedPosts === 'function') loadFeedPosts(1, true);
    })
    .catch(() => {
      showToast('Failed to create post');
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
  if (!feedPosts) return;

  fetch('../api/users/posts/get-posts.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'page=' + encodeURIComponent(page)
  })
    .then(r => r.json())
    .then(res => {
      if (!res || !res.success) throw new Error(res?.error || 'Failed to load feed');

      if (replace) feedPosts.innerHTML = '';

      (res.posts || []).forEach(p => {
        feedPosts.appendChild(createPostCard(p));
      });
    })
    .catch(() => {
      // silent fail (UI still has placeholder)
    });
}

document.addEventListener('DOMContentLoaded', () => {
  // If mock cards are present, replace them with dynamic content.
  loadFeedPosts(1, true);
});

