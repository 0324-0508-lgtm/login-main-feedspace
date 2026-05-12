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
  const isLiked = btn.classList.contains('liked');
  const span = btn.querySelector('span');
  let count = parseInt(span.textContent) || 0;
  if (isLiked) { btn.classList.remove('liked'); btn.querySelector('i').className = 'far fa-heart'; span.textContent = count - 1; }
  else { btn.classList.add('liked'); btn.querySelector('i').className = 'fas fa-heart'; span.textContent = count + 1; }
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
  const feedPosts = document.getElementById('feedPosts');
  const card = createPostCard(text);
  card.style.opacity = '0'; card.style.transform = 'translateY(14px)';
  feedPosts.insertBefore(card, feedPosts.firstChild);
  requestAnimationFrame(() => { card.style.transition = 'opacity 0.32s, transform 0.32s'; card.style.opacity = '1'; card.style.transform = 'translateY(0)'; });
  closeModal('postModal');
  showToast('Post shared! 🎉');
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

function createPostCard(text) {
  const card = document.createElement('div');
  card.className = 'post-card';
  card.innerHTML = `
    <div class="post-header">
      <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Kim" alt="User" class="post-avatar"/>
      <div class="post-meta"><div class="post-author">Kim Ballebar</div><div class="post-community">Community Name · <span class="post-time">Just now</span></div></div>
      <button class="options-btn" onclick="togglePostOptions(this)"><i class="fas fa-sliders-h"></i></button>
      <div class="post-options-menu">
        <div class="post-option" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit Post</div>
        <div class="post-option danger" onclick="deletePost(this)"><i class="fas fa-trash"></i> Delete Post</div>
        <div class="post-option" onclick="archivePost(this)"><i class="fas fa-archive"></i> Archive</div>
        <div class="post-option" onclick="openReportModal(this)"><i class="fas fa-flag"></i> Report</div>
      </div>
    </div>
    <div class="post-body"><p>${escapeHtml(text)}</p></div>
    <div class="post-footer">
      <button class="reaction-btn" onclick="toggleLike(this)"><i class="far fa-heart"></i> <span>0</span></button>
      <button class="reaction-btn" onclick="toggleComments(this)"><i class="fas fa-comment"></i> <span>Comment</span></button>
      <button class="reaction-btn" onclick="openShareModal(this)"><i class="fas fa-share"></i> <span>Share</span></button>
    </div>
    <div class="comment-section" style="display:none;">
      <div class="comment-input-row">
        <img src="https://api.dicebear.com/7.x/adventurer/svg?seed=Kim" alt="User"/>
        <div class="comment-input-wrap">
          <input type="text" placeholder="Write a comment..."/>
          <button class="comment-send-btn" onclick="addComment(this)"><i class="fas fa-plus"></i></button>
        </div>
      </div>
    </div>`;
  return card;
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
