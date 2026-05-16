// feed.js — Home Feed Logic

function togglePostOptions(btn) {
  // harden: if click is on <i> inside the button, use the closest options-btn
  const btnEl = btn?.classList?.contains('options-btn') ? btn : btn?.closest?.('.options-btn');
  if (!btnEl) return;

  const card = btnEl.closest('.post-card');
  const menu = card ? card.querySelector('.post-options-menu') : null;
  if (!menu) return;

  document.querySelectorAll('.post-options-menu').forEach(m => {
    if (m !== menu) m.classList.remove('show');
  });

  menu.classList.toggle('show');
}



function getCurrentUserId() {
  // Prefer global (set after OTP verify)
  const stored = window.user_id ?? window.USER_ID ?? window.userId ?? window.currentUserId;
  if (stored) return stored;

  // Then localStorage (OTP/verify-account flow)
  const ls = localStorage.getItem('currentUserId') || localStorage.getItem('user_id') || localStorage.getItem('userId');
  if (ls) return ls;

  // Finally, check URL params (some pages may pass it)
  const params = new URLSearchParams(window.location.search);
  return params.get('user_id') || params.get('userId') || null;
}


document.addEventListener('click', function(e) {
  if (!e.target.closest('.post-card')) {
    document.querySelectorAll('.post-options-menu').forEach(m => m.classList.remove('show'));
  }

  // Close navbar search results when clicking outside search area.
  const searchWrap = e.target.closest('#navSearchInput')?.closest('.nav-search');
  if (!searchWrap) {
    const results = document.getElementById('navSearchResults');
    if (results) results.classList.remove('show');
  }
});

function safeText(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'<').replace(/>/g,'>');
}

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
  if (!card) return;
  if (confirm('Delete this post?')) {
    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-8px)';

    // Hard-delete will be handled server-side in this codebase (post-actions.php expects action=delete).
    // This UI only removes the card optimistically; backend will process deletion.
    const postId = el.closest('.post-card')?.dataset?.postId;
    if (postId) {
      fetch('../api/users/posts/post-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'delete',
          post_id: postId
        })
      }).catch(() => {
        showToast('Delete failed', 'error');
      });
    }

    setTimeout(() => card.remove(), 290);
    showToast('Post deleted.');
  }
  closeOptions(el);
}





function toggleLike(btn) {
  const postId = btn?.dataset?.postId;
  if (!postId) return;

  // Ensure liked UI is derived from current DOM state
  const isLiked = btn.classList.contains('liked');
  const span = btn.querySelector('span');


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
    .then(async r => {
      const raw = await r.text();
      try {
        return JSON.parse(raw);
      } catch {
        console.error('add-comments non-JSON response:', raw);
        throw new Error('Server returned an invalid response for comments');
      }
    })
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
  // getCurrentUserId() may return strings; ensure we append a trimmed value.
  const uid = getCurrentUserId();
  const trimmedUid = uid != null ? String(uid).trim() : '';
  console.log('submitPost user_id fallback:', trimmedUid || null);
  if (trimmedUid) {
    form.append('user_id', trimmedUid);
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
  const postId = card?.dataset?.postId;
  // store selected post for submitShare
  window.__pendingSharePostId = postId || null;

  const body = card.querySelector('.post-body p');
  _sharePostText = body ? body.innerText : '';

  const preview = document.getElementById('sharePostPreview');
  if (preview) preview.textContent = _sharePostText.length > 80 ? _sharePostText.slice(0, 80) + '...' : _sharePostText;

  // reset textarea
  const ta = document.getElementById('shareText');
  if (ta) ta.value = '';

  document.getElementById('shareModal').classList.add('show');
}


function submitShare() {
  closeModal('shareModal');
  const text = document.getElementById('shareText')?.value?.trim() || '';
  if (!text) {
    showToast('Write something to share first!');
    return;
  }

  // If you have a backend endpoint later for sharing, wire it here.
  // For now, treat share as adding a comment.
  showToast('Shared! 💡');
  if (typeof window.__pendingSharePostId !== 'undefined' && window.__pendingSharePostId) {
    const postId = window.__pendingSharePostId;
    const commentWrap = document.querySelector(`.post-card[data-post-id="${postId}"] .comment-input-wrap input`);
    // Fallback: just send to API (same moderation pipeline as comments)
    fetch(`../api/users/interactions/add-comments.php?id=${postId}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        postId: parseInt(postId, 10),
        text: text
      })
    })
      .then(async r => {
        const raw = await r.text();
        return JSON.parse(raw);
      })
      .then(res => {
        if (res?.warning) showToast(res.warning, 'warning');
        // Update comment count if present
        const card = document.querySelector(`.post-card[data-post-id="${postId}"]`);
        const commentBtn = card?.querySelector('.reaction-btn:has(i.fa-comment)');
        if (commentBtn) {
          const span = commentBtn.querySelector('span');
          if (span) span.textContent = String(res.comment_count || 0);
        }
        // Optionally render comment
        if (res?.moderation_status !== 'removed') {
          const section = card?.querySelector('.comment-section');
          if (section) {
            const item = document.createElement('div');
            item.className = 'comment-item';
            item.innerHTML = `
              <img src="${escapeAttr(res.avatar)}" alt="User"/>
              <div class="comment-bubble">
                <div class="comment-author">${escapeHtml(res.author)}</div>
                <div class="comment-text">${escapeHtml(res.text)}</div>
                ${res.moderation_status === 'flagged' ? '<span class="mod-badge flagged" title="This comment is flagged for review">⚠️ Under Review</span>' : ''}
              </div>`;
            section.appendChild(item);
          }
        }
      })
      .catch(err => {
        console.error('Share(comment) error:', err);
        showToast('Failed to share');
      });
  }
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

// ---- Navbar Search (FeedSpace) ----
// Uses main/api/users/search/search.php

function debounce(fn, ms) {
  let t = null;
  return function(...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), ms);
  };
}

function setNavSearchResults(html, show = true) {
  const box = document.getElementById('navSearchResults');
  if (!box) return;
  box.innerHTML = html || '';
  if (show) box.classList.add('show');
  else box.classList.remove('show');
}

function renderNavSearchResults(data) {
  const q = safeText(data?.query || '');
  const results = data?.results || {};

  const users = Array.isArray(results.users) ? results.users : [];
  const posts = Array.isArray(results.posts) ? results.posts : [];
  const communities = Array.isArray(results.communities) ? results.communities : [];

  const makeItem = (icon, title, subtitle, href) => {
    const safeTitle = safeText(title);
    const safeSubtitle = safeText(subtitle);
    const cls = href ? 'nav-search-item link' : 'nav-search-item';
    const aStart = href ? `<a href="${href}">` : '';
    const aEnd = href ? `</a>` : '';
    return `
      <div class="${cls}" role="option">
        ${aStart}
          <div class="nav-search-item-icon"><i class="${icon}"></i></div>
          <div class="nav-search-item-text">
            <div class="nav-search-item-title">${safeTitle}</div>
            <div class="nav-search-item-subtitle">${safeSubtitle}</div>
          </div>
        ${aEnd}
      </div>
    `;
  };

  let html = '';

  if (!users.length && !posts.length && !communities.length) {
    html = `<div class="nav-search-empty">No results for <b>${q}</b>.</div>`;
    return html;
  }

  if (users.length) {
    html += `<div class="nav-search-section">People</div>`;
    users.slice(0, 5).forEach(u => {
      const id = u?.id;
      const name = u?.name || '';
      const href = id ? `profile.html?user_id=${encodeURIComponent(id)}` : null;
      html += makeItem('fas fa-user', name, u?.bio ? u.bio : `User #${id}`, href);
    });
  }

  if (posts.length) {
    html += `<div class="nav-search-section">Posts</div>`;
    posts.slice(0, 5).forEach(p => {
      const id = p?.id;
      const content = (p?.content || '').toString().trim();
      const title = content.length > 60 ? content.slice(0, 60) + '…' : content;
      // No dedicated post page in repo; route to feed and prefill query.
      const href = id ? `main-feed.html?q=${encodeURIComponent(title)}` : null;
      html += makeItem('fas fa-file-alt', title || `Post #${id}`, `Post ID: ${id}`, href);
    });
  }

  if (communities.length) {
    html += `<div class="nav-search-section">Communities</div>`;
    communities.slice(0, 5).forEach(c => {
      const id = c?.id;
      const name = c?.name || '';
      const href = id ? `community-page.html?community_id=${encodeURIComponent(id)}` : null;
      html += makeItem('fas fa-users', name, `${c?.member_count ?? 0} members`, href);
    });
  }

  return html;
}

async function performNavSearch(q) {
  const query = String(q ?? '').trim();
  if (query.length < 2) {
    setNavSearchResults('', false);
    return;
  }

  try {
    const url = new URL('../api/users/search/search.php', window.location.href);
// console.log('Nav search request:', url.toString());
    // console.log('Nav search query:', query);
    // console.log('Nav search response status:', res.status);
    // Debug: show request URL to help diagnose backend errors
    // console.log('Nav search URL:', url.toString());
    url.searchParams.set('q', query);
    url.searchParams.set('type', 'all');
    url.searchParams.set('limit', '10');

    const res = await fetch(url.toString(), { credentials: 'same-origin' });
    const rawText = await res.text();
    let data;
    try { data = JSON.parse(rawText); }
    catch(e){
      console.error('Nav search non-JSON response:', { status: res.status, url: url.toString(), rawText: rawText.slice(0, 1000) });
      throw new Error('Non-JSON response from search API');
    }

    if (!data?.success) {
      setNavSearchResults(`<div class="nav-search-empty">Search failed.</div>`, true);
      return;
    }

    const html = renderNavSearchResults(data);
    setNavSearchResults(html, true);
  } catch (e) {
    console.error('Nav search error:', e);
    setNavSearchResults(`<div class="nav-search-empty">Search error.</div>`, true);
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('navSearchInput');
  const results = document.getElementById('navSearchResults');

  if (!input || !results) return;

  const doSearch = debounce((value) => performNavSearch(value), 220);

  input.addEventListener('input', function() {
    doSearch(this.value);
  });

  document.addEventListener('keydown', function(ev) {
    if (ev.key === 'Escape') {
      setNavSearchResults('', false);
      input.blur();
    }
  });

  // If search query is present in URL (?q=...), run once.
  const urlParams = new URLSearchParams(window.location.search);
  const q = urlParams.get('q');
  if (q && String(q).trim()) {
    input.value = q;
    performNavSearch(q);
  }
});





