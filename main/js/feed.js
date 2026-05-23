// feed.js — FIXED VERSION

const DEFAULT_AVATAR = 'https://api.dicebear.com/7.x/adventurer/svg?seed=Default';

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escapeAttr(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(message, type) {
    type = type || 'info';
    console.log('[' + type.toUpperCase() + '] ' + message);
}

function togglePostOptions(btn) {
    var btnEl = btn && btn.classList && btn.classList.contains('options-btn') ? btn : (btn && btn.closest ? btn.closest('.options-btn') : null);
    if (!btnEl) return;
    var card = btnEl.closest('.post-card');
    var menu = card ? card.querySelector('.post-options-menu') : null;
    if (!menu) return;
    document.querySelectorAll('.post-options-menu').forEach(function(m) {
        if (m !== menu) m.classList.remove('show');
    });
    menu.classList.toggle('show');
}

function closeOptions(el) {
    el.closest('.post-options-menu').classList.remove('show');
}

function editPost(el) {
    var card = el.closest('.post-card');
    var body = card.querySelector('.post-body > p');
    var newText = prompt('Edit your post:', body ? body.innerText : '');
    if (newText !== null && newText.trim()) {
        fetch('../api/users/posts/edit-posts.php', {
            credentials: 'include',
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ post_id: card.dataset.postId, content: newText })
        })
        .then(r => r.text())
        .then(text => {
            var res = JSON.parse(text);
            if (res.success) { body.innerText = newText; showToast('Post updated!'); }
            else showToast(res.error || 'Update failed', 'error');
        })
        .catch(err => { console.error(err); showToast('Update failed', 'error'); });
    }
    closeOptions(el);
}

async function deletePost(postId) {
    if (!postId) {
        console.error('[ERROR] No post ID provided for deletion');
        return;
    }
    if (!confirm('Are you sure you want to delete this post?')) return;
    
    try {
        const response = await fetch('../api/users/posts/delete-post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ post_id: parseInt(postId) })
        });
        const data = await response.json();
        if (data.success) {
            console.log('[INFO] Post deleted successfully');
            showToast('Post deleted', 'success');
            const postCard = document.querySelector(`.post-card[data-post-id="${postId}"]`);
            if (postCard) {
                postCard.style.transition = 'opacity 0.3s, transform 0.3s';
                postCard.style.opacity = '0';
                postCard.style.transform = 'scale(0.95)';
                setTimeout(() => postCard.remove(), 300);
            }
        } else {
            throw new Error(data.error || 'Delete failed');
        }
    } catch (error) {
        console.error('[ERROR] Delete failed:', error.message);
        showToast('Failed to delete post: ' + error.message, 'error');
    }
}

function toggleLike(btn) {
    var postId = btn.dataset.postId;
    if (!postId) return;
    fetch('../api/users/interactions/toggle-post-like.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
    })
    .then(r => r.json())
    .then(res => {
        if (!res || !res.success) throw new Error(res.error || 'Like failed');
        btn.classList.toggle('liked', res.liked);
        var icon = btn.querySelector('i');
        if (icon) icon.className = res.liked ? 'fas fa-heart' : 'far fa-heart';
        var span = btn.querySelector('span');
        if (span) span.textContent = String(res.likesCount || 0);
    })
    .catch(err => { console.error(err); showToast('Like failed', 'error'); });
}

async function loadComments(postId, section) {
    try {
        var res = await fetch('../api/users/interactions/get-comments.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: parseInt(postId, 10), page: 1 })
        });
        var data = await res.json();
        if (!data.success) throw new Error(data.error || 'Failed to load comments');
        section.innerHTML = '<div class="comment-input-row"><img src="' + DEFAULT_AVATAR + '" alt="User"/><div class="comment-input-wrap"><input type="text" placeholder="Write a comment..."/><button class="comment-send-btn" onclick="addComment(this)"><i class="fas fa-paper-plane"></i></button></div></div>';
        if (!data.comments || !data.comments.length) {
            section.innerHTML += '<div class="comment-empty">No comments yet. Be the first!</div>';
            return;
        }
        data.comments.forEach(c => {
            var item = document.createElement('div');
            item.className = 'comment-item';
            item.innerHTML = '<div class="comment-bubble"><div class="comment-author">' + escapeHtml(c.author) + '</div><div class="comment-text">' + escapeHtml(c.content) + '</div></div>';
            section.appendChild(item);
        });
    } catch (err) { console.error(err); showToast('Failed to load comments', 'error'); }
}

function toggleComments(btn) {
    var card = btn.closest('.post-card');
    var section = card.querySelector('.comment-section');
    if (!section) return;
    var isHidden = !section.style.display || section.style.display === 'none';
    section.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        var input = section.querySelector('input');
        if (input) input.focus();
        loadComments(card.dataset.postId, section);
    }
}

function addComment(btn) {
    var wrap = btn.closest('.comment-input-wrap');
    var input = wrap.querySelector('input');
    var text = input.value.trim();
    if (!text) { showToast('Write something first!', 'warning'); return; }
    var card = btn.closest('.post-card');
    var postId = card.dataset.postId;
    btn.disabled = true;
    fetch('../api/users/interactions/add-comments.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10), content: text })
    })
    .then(r => r.json())
    .then(res => {
        if (!res || !res.success) throw new Error(res.error || 'Failed to add comment');
        showToast('Comment added!');
        input.value = '';
        loadComments(postId, btn.closest('.comment-section'));
    })
    .catch(err => { console.error(err); showToast('Failed to add comment', 'error'); })
    .finally(() => btn.disabled = false);
}

function openPostModal(prefill) {
    var ta = document.getElementById('modalPostText');
    if (ta) ta.value = prefill || '';
    document.getElementById('postModal').classList.add('show');
    if (ta) ta.focus();
}

function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

function submitPost(e) {
    if (e && e.preventDefault) e.preventDefault();
    var ta = document.getElementById('modalPostText');
    var text = ta ? ta.value.trim() : '';
    var fileInput = document.getElementById('modalPostImage');
    var hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);
    if (!text && !hasFile) { showToast('Write something or attach a photo first!', 'warning'); return; }
    var btn = document.querySelector('#postModal .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Creating...'; }
    var form = new FormData();
    form.append('content', text);
    if (hasFile) form.append('image', fileInput.files[0]);
    fetch('../api/users/posts/create-post.php', { method: 'POST', credentials: 'include', body: form })
    .then(r => r.json())
    .then(data => {
        if (!data || !data.success) throw new Error(data.error || 'Create failed');
        showToast('Post created!');
        closeModal('postModal');
        if (ta) ta.value = '';
        if (fileInput) fileInput.value = '';
        window.location.reload();
    })
    .catch(err => { console.error(err); showToast('Error: ' + err.message, 'error'); })
    .finally(() => { if (btn) { btn.disabled = false; btn.textContent = '+ Create Post'; } });
}

function openReportModal(postId) {
    if (postId && postId.closest) {
        var card = postId.closest('.post-card');
        postId = card ? card.dataset.postId : null;
    }
    if (!postId) {
        console.error('[ERROR] No post ID for report');
        return;
    }
    document.getElementById('report-post-id').value = postId;
    document.getElementById('report-reason').value = '';
    document.getElementById('report-description').value = '';
    document.getElementById('report-reason-error').textContent = '';
    document.getElementById('report-desc-error').textContent = '';
    document.getElementById('reportModal').classList.add('show');
}

async function submitReport() {
    const postId = document.getElementById('report-post-id').value;
    const reason = document.getElementById('report-reason').value;
    const description = document.getElementById('report-description').value.trim();
    
    document.getElementById('report-reason-error').textContent = '';
    document.getElementById('report-desc-error').textContent = '';
    
    if (!reason) {
        document.getElementById('report-reason-error').textContent = 'Please select a reason';
        document.getElementById('report-reason').focus();
        return;
    }
    
    try {
        const response = await fetch('/login-main-feedspace/main/api/users/reports/report-post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                post_id: parseInt(postId),
                reason: reason,
                description: description
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Report submitted successfully!', 'success');
            closeModal('reportModal');
            document.getElementById('report-reason').value = '';
            document.getElementById('report-description').value = '';
        } else {
            throw new Error(data.error || 'Report failed');
        }
    } catch (error) {
        console.error('[ERROR] Report failed:', error.message);
        showToast('Failed to submit report: ' + error.message, 'error');
    }
}

var _sharePostText = '';
function openShareModal(btn) {
    var card = btn.closest('.post-card');
    var postId = card && card.dataset ? card.dataset.postId : null;
    window.__pendingSharePostId = postId || null;
    var body = card.querySelector('.post-body > p');
    _sharePostText = body ? body.innerText : '';
    var preview = document.getElementById('sharePostPreview');
    if (preview) preview.textContent = _sharePostText.length > 80 ? _sharePostText.slice(0, 80) + '...' : _sharePostText;
    var ta = document.getElementById('shareText');
    if (ta) ta.value = '';
    document.getElementById('shareModal').classList.add('show');
}

function submitShare() {
    var text = document.getElementById('shareText');
    text = text ? text.value.trim() : '';
    
    if (!window.__pendingSharePostId) { 
        showToast('Error: No post selected', 'error'); 
        return; 
    }
    
    showToast('Sharing...');
    
    var form = new FormData();
    form.append('content', text); // Can be empty now
    form.append('shared_post_id', window.__pendingSharePostId);
    
    fetch('../api/users/posts/create-post.php', {
        method: 'POST',
        credentials: 'include',
        body: form
    })
    .then(r => r.json())
    .then(res => {
        if (!res || !res.success) throw new Error(res.error || 'Share failed');
        showToast(res.message || 'Post shared!');
        closeModal('shareModal');
        // Clear the share text
        var ta = document.getElementById('shareText');
        if (ta) ta.value = '';
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(err => { 
        console.error(err); 
        showToast('Failed to share: ' + err.message, 'error'); 
    });
}

function openAnnounceModal(el) {
    closeOptions(el);
    showToast('Announcement requests are not available yet.', 'warning');
}

function submitAnnounceRequest() {
    showToast('Announcement requests are not available yet.', 'warning');
}

function archivePost(el) {
    var card = el.closest('.post-card');
    if (!card) return;
    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-8px)';
    setTimeout(() => card.remove(), 300);
    showToast('Post archived.');
    closeOptions(el);
}

function createPostCard(post, currentUserId) {
    var card = document.createElement('div');
    card.className = 'post-card';
    var postId = post.post_id || post.id;
    var userId = post.user_id || '';
    var avatar = post.profile_picture || DEFAULT_AVATAR;
    var author = post.full_name || 'Unknown';
    var content = post.content || '';
    var image = post.image || post.file_url;
    var likeCount = post.like_count || 0;
    var commentCount = post.comment_count || 0;
    var userLiked = post.user_liked || false;
    var isShared = post.is_shared || false;
    var profileLink = 'profile.php?id=' + escapeAttr(userId);
    var sharedBadge = isShared ? '<span class="shared-badge"><i class="fas fa-share"></i> Shared</span>' : '';
    var aiBadge = '';
    if (post.ai_status === 'review') aiBadge = '<span class="ai-badge review" title="' + escapeAttr(post.ai_reason || 'Under review') + '">🤖 Review</span>';
    else if (post.ai_status === 'rejected') aiBadge = '<span class="ai-badge rejected">🤖 Rejected</span>';

    var sharedHtml = '';
    if (isShared && post.original) {
        var orig = post.original;
        var origImageHtml = '';
        if (orig.image || orig.file_url) origImageHtml = '<div class="sp-image-wrap"><img src="' + escapeAttr(orig.image || orig.file_url) + '" class="sp-image" onerror="this.style.display=\'none\'"/></div>';
        sharedHtml = '<div class="shared-post-card"><div class="sp-header"><a href="profile.php?id=' + escapeAttr(orig.user_id || '') + '" class="sp-avatar-link"><img src="' + escapeAttr(orig.profile_picture || DEFAULT_AVATAR) + '" class="sp-avatar" onerror="this.src=\'' + DEFAULT_AVATAR + '\'"/></a><div class="sp-meta"><span class="sp-author">' + escapeHtml(orig.full_name || orig.author || 'Unknown') + '</span><span class="sp-time">' + escapeHtml(orig.created_at || '') + '</span></div></div><div class="sp-body"><p class="sp-content">' + escapeHtml(orig.content || '') + '</p>' + origImageHtml + '</div></div>';
    }

    card.dataset.postId = postId;
    var postImageHtml = !isShared && image ? '<div class="image-grid grid-1"><img src="' + escapeAttr(image) + '" alt="Post image" class="post-image" onerror="this.style.display=\'none\'"/></div>' : '';
    var announcementBadge = post.is_announcement ? '<span class="announcement-badge">📢 Announcement</span>' : '';
    var likeIconClass = userLiked ? 'fas fa-heart' : 'far fa-heart';
    var likeBtnClass = userLiked ? 'reaction-btn liked' : 'reaction-btn';

    var isOwnPost = (String(userId).trim() === String(currentUserId).trim());
    var optionsMenuHtml = '<div class="post-options-menu">';
    if (isOwnPost) {
        optionsMenuHtml += '<div class="post-option" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit Post</div>';
        optionsMenuHtml += '<div class="post-option danger" onclick="deletePost(' + postId + ')"><i class="fas fa-trash"></i> Delete Post</div>';
    }
    optionsMenuHtml += '<div class="post-option" onclick="openAnnounceModal(this)"><i class="fas fa-bullhorn"></i> Request to Announce</div>';
    optionsMenuHtml += '</div>';

    card.innerHTML = '<div class="post-header"><a href="' + profileLink + '" class="post-avatar-link"><img src="' + escapeAttr(avatar) + '" alt="User" class="post-avatar" onerror="this.src=\'' + DEFAULT_AVATAR + '\'"/></a><div class="post-meta"><a href="' + profileLink + '" class="post-author-link"><div class="post-author">' + escapeHtml(author) + ' ' + sharedBadge + ' ' + aiBadge + '</div></a><div class="post-community">Community · <span class="post-time">' + escapeHtml(post.created_at || '') + '</span></div>' + announcementBadge + '</div><button class="options-btn" onclick="togglePostOptions(this)"><i class="fas fa-ellipsis-h"></i></button>' + optionsMenuHtml + '</div><div class="post-body"><p>' + escapeHtml(content) + '</p>' + postImageHtml + sharedHtml + '</div><div class="post-footer"><button class="' + likeBtnClass + '" data-post-id="' + postId + '" onclick="toggleLike(this)"><i class="' + likeIconClass + '"></i><span>' + likeCount + '</span></button><button class="reaction-btn" onclick="toggleComments(this)"><i class="fas fa-comment"></i> <span>' + commentCount + '</span> Comment</button><button class="reaction-btn" onclick="openShareModal(this)"><i class="fas fa-share"></i> <span>Share</span></button><button class="post-action-btn report-btn" onclick="openReportModal(' + postId + ')" title="Report post"><i class="fas fa-exclamation-circle"></i> Report</button></div><div class="comment-section" style="display:none;"><div class="comment-input-row"><img src="' + DEFAULT_AVATAR + '" alt="User"/><div class="comment-input-wrap"><input type="text" placeholder="Write a comment..."/><button class="comment-send-btn" onclick="addComment(this)"><i class="fas fa-paper-plane"></i></button></div></div></div>';
    return card;
}

async function loadFeed(page) {
    page = page || 1;
    try {
        var currentUserId = window.__currentUserId || '';
        
        var res = await fetch('../api/users/posts/get-posts.php', { 
            method: 'POST', 
            credentials: 'include', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ page: page }) 
        });
        var data = await res.json();
        if (!data || !data.success) throw new Error(data.error || 'Failed to load feed');
        var container = document.getElementById('feedPosts');
        if (!container) return;
        
        data.posts.forEach(post => container.appendChild(createPostCard(post, currentUserId)));
    } catch (err) { 
        console.error(err); 
        showToast('Failed to load feed', 'error'); 
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadFeed();
    document.addEventListener('click', e => {
        ['postModal', 'reportModal', 'shareModal', 'announceModal'].forEach(id => {
            var el = document.getElementById(id);
            if (el && e.target === el) el.classList.remove('show');
        });
    });
});