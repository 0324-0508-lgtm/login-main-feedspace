// feed.js — FeedSpace Main Feed

/*
|--------------------------------------------------------------------------
| UTILITY FUNCTIONS
|--------------------------------------------------------------------------
*/

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escapeAttr(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(message, type = 'info') {
    // Implement your toast notification here
    console.log(`[${type.toUpperCase()}] ${message}`);
}

/*
|--------------------------------------------------------------------------
| POST OPTIONS MENU
|--------------------------------------------------------------------------
*/

function togglePostOptions(btn) {
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

function closeOptions(el) {
    el.closest('.post-options-menu').classList.remove('show');
}

/*
|--------------------------------------------------------------------------
| EDIT POST
|--------------------------------------------------------------------------
*/

function editPost(el) {
    const card = el.closest('.post-card');
    const body = card.querySelector('.post-body p');
    const newText = prompt('Edit your post:', body ? body.innerText : '');

    if (newText !== null && newText.trim()) {
        const postId = card.dataset.postId;

        fetch('../api/users/posts/post-actions.php', {
            credentials: 'include',
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'edit',
                post_id: postId,
                content: newText
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (body) body.innerText = newText;
                showToast('Post updated!');
            } else {
                showToast(res.error || 'Update failed', 'error');
            }
        })
        .catch(() => showToast('Update failed', 'error'));
    }
    closeOptions(el);
}

/*
|--------------------------------------------------------------------------
| DELETE POST
|--------------------------------------------------------------------------
*/

function deletePost(el) {
    const card = el.closest('.post-card');
    if (!card) return;

    if (!confirm('Delete this post?')) {
        closeOptions(el);
        return;
    }

    const postId = card.dataset.postId;

    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-8px)';

    fetch('../api/users/posts/post-actions.php', {
        credentials: 'include',
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'delete',
            post_id: postId
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            setTimeout(() => card.remove(), 290);
            showToast('Post deleted.');
        } else {
            showToast(res.error || 'Delete failed', 'error');
            card.style.opacity = '1';
            card.style.transform = 'none';
        }
    })
    .catch(() => {
        showToast('Delete failed', 'error');
        card.style.opacity = '1';
        card.style.transform = 'none';
    });

    closeOptions(el);
}

/*
|--------------------------------------------------------------------------
| LIKE / UNLIKE
|--------------------------------------------------------------------------
*/

function toggleLike(btn) {
    const postId = btn?.dataset?.postId;
    if (!postId) return;

    const isLiked = btn.classList.contains('liked');
    const span = btn.querySelector('span');

    fetch('../api/users/interactions/toggle-post-like.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
    })
    .then(r => r.json())
    .then(res => {
        if (!res || !res.success) throw new Error(res?.error || 'Toggle failed');

        const nextLiked = !!res.liked;
        btn.classList.toggle('liked', nextLiked);
        const icon = btn.querySelector('i');
        if (icon) icon.className = nextLiked ? 'fas fa-heart' : 'far fa-heart';
        if (span) span.textContent = String(res.likesCount ?? 0);
    })
    .catch(() => showToast(isLiked ? 'Failed to unlike' : 'Failed to like', 'error'));
}

/*
|--------------------------------------------------------------------------
| COMMENTS
|--------------------------------------------------------------------------
*/

async function loadComments(postId, section) {
    try {
        const res = await fetch('../api/users/interactions/get-comments.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: parseInt(postId, 10), page: 1 })
        });

        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); }
        catch { throw new Error('Invalid server response'); }

        if (!data?.success) throw new Error(data?.error || 'Failed to load comments');

        const inputRow = section.querySelector('.comment-input-row');
        section.innerHTML = '';
        if (inputRow) section.appendChild(inputRow);

        data.comments.forEach(comment => {
            const item = document.createElement('div');
            item.className = 'comment-item';

            let modBadge = '';
            if (comment.moderation_status === 'flagged') {
                modBadge = '<span class="mod-badge flagged">⚠️ Under Review</span>';
            }

            item.innerHTML = `
                <img src="${escapeAttr(comment.avatar)}" alt="User"/>
                <div class="comment-bubble">
                    <div class="comment-author">${escapeHtml(comment.author)}</div>
                    <div class="comment-text">${escapeHtml(comment.content)}</div>
                    ${modBadge}
                </div>`;
            section.appendChild(item);
        });

    } catch (err) {
        console.error('Load comments error:', err);
        showToast('Failed to load comments', 'error');
    }
}

function toggleComments(btn) {
    const card = btn.closest('.post-card');
    const section = card.querySelector('.comment-section');
    if (!section) return;

    const isHidden = section.style.display === 'none' || !section.style.display;
    section.style.display = isHidden ? 'block' : 'none';

    if (isHidden) {
        const input = section.querySelector('input');
        if (input) input.focus();
        const postId = card.dataset.postId;
        if (postId) loadComments(postId, section);
    }
}

function addComment(btn) {
    const wrap = btn.closest('.comment-input-wrap');
    const input = wrap.querySelector('input');
    const text = input.value.trim();

    if (!text) {
        showToast('Write something first!', 'warning');
        return;
    }

    const card = btn.closest('.post-card');
    const postId = card?.dataset?.postId;

    if (!postId) {
        showToast('Error: Post ID not found', 'error');
        return;
    }

    btn.disabled = true;
    const originalIcon = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('../api/users/interactions/add-comments.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            post_id: parseInt(postId, 10),
            content: text
        })
    })
    .then(r => r.json())
    .then(res => {
        if (!res?.success) throw new Error(res?.error || 'Failed to add comment');

        showToast('Comment added!');
        input.value = '';

        const section = btn.closest('.comment-section');
        loadComments(postId, section);

        const commentBtn = card.querySelector('.reaction-btn:has(i.fa-comment)');
        if (commentBtn) {
            const span = commentBtn.querySelector('span');
            if (span) span.textContent = String(res.comment_count || 0);
        }
    })
    .catch(err => {
        console.error('Comment error:', err);
        showToast('Failed to add comment', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalIcon;
    });
}

/*
|--------------------------------------------------------------------------
| CREATE POST
|--------------------------------------------------------------------------
*/

function openPostModal(prefill) {
    const ta = document.getElementById('modalPostText');
    if (ta) ta.value = prefill || '';
    document.getElementById('postModal').classList.add('show');
    if (ta) ta.focus();
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

function submitPost(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();

    const ta = document.getElementById('modalPostText');
    const text = ta ? ta.value.trim() : '';
    const fileInput = document.getElementById('modalPostImage');
    const hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (!text && !hasFile) {
        showToast('Write something or attach a photo first!', 'warning');
        return;
    }

    const btn = document.querySelector('#postModal .btn-primary');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Creating...';
    }

    const form = new FormData();
    form.append('content', text);

    if (fileInput && fileInput.files && fileInput.files[0]) {
        form.append('image', fileInput.files[0]);
    }

    fetch('../api/users/posts/create-post.php', {
        method: 'POST',
        credentials: 'include',
        body: form
    })
    .then(r => r.json())
    .then(data => {
        if (!data || !data.success) {
            throw new Error(data?.error || 'Create failed');
        }

        showToast('Post created!');
        closeModal('postModal');
        if (ta) ta.value = '';
        if (fileInput) fileInput.value = '';
        window.location.reload();
    })
    .catch(err => {
        console.error('Post creation error:', err);
        showToast('Error: ' + err.message, 'error');
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '+ Create Post';
        }
    });
}

/*
|--------------------------------------------------------------------------
| REPORT POST
|--------------------------------------------------------------------------
*/

function openReportModal(el) {
    closeOptions(el);
    document.getElementById('reportModal').classList.add('show');
}

function submitReport() {
    const reason = document.getElementById('reportReason')?.value?.trim();
    if (!reason) {
        showToast('Please provide a reason', 'warning');
        return;
    }

    const postId = window.__pendingReportPostId;
    if (!postId) {
        showToast('Error: No post selected', 'error');
        return;
    }

    fetch('../api/users/posts/post-actions.php', {
        credentials: 'include',
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'report',
            post_id: postId,
            reason: reason
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('Post reported. Thank you!');
            closeModal('reportModal');
        } else {
            showToast(res.error || 'Report failed', 'error');
        }
    })
    .catch(() => showToast('Report failed', 'error'));
}

/*
|--------------------------------------------------------------------------
| SHARE POST
|--------------------------------------------------------------------------
*/

let _sharePostText = '';

function openShareModal(btn) {
    const card = btn.closest('.post-card');
    const postId = card?.dataset?.postId;
    window.__pendingSharePostId = postId || null;

    const body = card.querySelector('.post-body p');
    _sharePostText = body ? body.innerText : '';

    const preview = document.getElementById('sharePostPreview');
    if (preview) preview.textContent = _sharePostText.length > 80 ? _sharePostText.slice(0, 80) + '...' : _sharePostText;

    const ta = document.getElementById('shareText');
    if (ta) ta.value = '';

    document.getElementById('shareModal').classList.add('show');
}

function submitShare() {
    closeModal('shareModal');
    const text = document.getElementById('shareText')?.value?.trim() || '';
    if (!text) {
        showToast('Write something to share first!', 'warning');
        return;
    }

    if (!window.__pendingSharePostId) {
        showToast('Error: No post selected', 'error');
        return;
    }

    const postId = window.__pendingSharePostId;
    showToast('Sharing...');

    fetch('../api/users/posts/share-post.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            post_id: parseInt(postId, 10),
            comment: text
        })
    })
    .then(r => r.json())
    .then(res => {
        if (!res?.success) throw new Error(res?.error || 'Share failed');
        showToast(res.message || 'Post shared!');
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(err => {
        console.error('Share error:', err);
        showToast('Failed to share', 'error');
    });
}

/*
|--------------------------------------------------------------------------
| REQUEST TO ANNOUNCE
|--------------------------------------------------------------------------
*/

function openAnnounceModal(el) {
    closeOptions(el);
    const modal = document.getElementById('announceModal');
    if (modal) modal.classList.add('show');

    const card = el.closest('.post-card');
    window.__pendingAnnouncePostId = card?.dataset?.postId || null;
}

function submitAnnounceRequest() {
    const reason = document.getElementById('announceReason')?.value?.trim();
    if (!reason) {
        showToast('Please provide a reason', 'warning');
        return;
    }

    const postId = window.__pendingAnnouncePostId;
    if (!postId) {
        showToast('Error: No post selected', 'error');
        return;
    }

    fetch('../api/users/posts/post-actions.php', {
        credentials: 'include',
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'announce',
            post_id: postId,
            reason: reason
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('Announcement request submitted!');
            closeModal('announceModal');
        } else {
            showToast(res.error || 'Request failed', 'error');
        }
    })
    .catch(() => showToast('Request failed', 'error'));
}

/*
|--------------------------------------------------------------------------
| ARCHIVE POST
|--------------------------------------------------------------------------
*/

function archivePost(el) {
    const card = el.closest('.post-card');
    if (!card) return;

    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-8px)';

    setTimeout(() => card.remove(), 290);
    showToast('Post archived.');
    closeOptions(el);
}

/*
|--------------------------------------------------------------------------
| CREATE POST CARD
|--------------------------------------------------------------------------
*/

function createPostCard(post) {
    const card = document.createElement('div');
    card.className = 'post-card';

    const postId = post.post_id || post.id;
    const avatar = post.profile_picture || '../../assets/default.jpg';
    const author = post.full_name || 'Unknown';
    const content = post.content || '';
    const image = post.image || post.file_url;
    const likeCount = post.like_count || 0;
    const commentCount = post.comment_count || 0;
    const userLiked = post.user_liked || false;

    // Shared post handling
    let sharedHeader = '';
    let sharedBody = '';

    if (post.is_shared && post.shared_by) {
        sharedHeader = `
            <div class="shared-header">
                <i class="fas fa-share"></i>
                <span>Shared by <strong>${escapeHtml(post.shared_by.name)}</strong></span>
            </div>
        `;

        if (post.original_post) {
            sharedBody = `
                <div class="shared-original">
                    <div class="shared-original-header">
                        <img src="${escapeAttr(post.shared_by.avatar)}" alt="User" class="shared-avatar"/>
                        <span class="shared-name">${escapeHtml(post.shared_by.name)}</span>
                        <span class="shared-time">${escapeHtml(post.original_post.created_at)}</span>
                    </div>
                    <p class="shared-content">${escapeHtml(post.original_post.content)}</p>
                    ${post.original_post.file_url ? `<img src="${escapeAttr(post.original_post.file_url)}" alt="Shared image" class="shared-image"/>` : ''}
                </div>
            `;
        }
    }

    card.dataset.postId = postId;

    card.innerHTML = `
        ${sharedHeader}
        <div class="post-header">
            <img src="${escapeAttr(avatar)}" alt="User" class="post-avatar"/>
            <div class="post-meta">
                <div class="post-author">${escapeHtml(author)}</div>
                <div class="post-community">Community · <span class="post-time">${escapeHtml(post.created_at || '')}</span></div>
                ${post.is_announcement ? '<span class="announcement-badge">📢 Announcement</span>' : ''}
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
        <div class="post-body">
            <p>${escapeHtml(content)}</p>
            ${image ? `<div class="image-grid grid-1"><img src="${escapeAttr(image)}" alt="Post image" class="post-image"/></div>` : ''}
            ${sharedBody}
        </div>
        <div class="post-footer">
            <button class="reaction-btn ${userLiked ? 'liked' : ''}" data-post-id="${postId}" onclick="toggleLike(this)">
                <i class="${userLiked ? 'fas' : 'far'} fa-heart"></i>
                <span>${likeCount}</span>
            </button>
            <button class="reaction-btn" onclick="toggleComments(this)">
                <i class="fas fa-comment"></i> <span>${commentCount}</span> Comment
            </button>
            <button class="reaction-btn" onclick="openShareModal(this)">
                <i class="fas fa-share"></i> <span>Share</span>
            </button>
        </div>
        <div class="comment-section" style="display:none;">
            <div class="comment-input-row">
                <img src="../../assets/default.jpg" alt="User"/>
                <div class="comment-input-wrap">
                    <input type="text" placeholder="Write a comment..."/>
                    <button class="comment-send-btn" onclick="addComment(this)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>
    `;

    return card;
}

/*
|--------------------------------------------------------------------------
| LOAD FEED
|--------------------------------------------------------------------------
*/

async function loadFeed(page = 1) {
    try {
        const res = await fetch('../api/users/posts/get-posts.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ page })
        });

        let data;
        try { data = await res.json(); }
        catch { throw new Error('Invalid feed response'); }

        if (!data?.success) throw new Error(data?.error || 'Failed to load feed');

        const container = document.getElementById('feedPosts');
        if (!container) return;

        data.posts.forEach(post => {
            container.appendChild(createPostCard(post));
        });

    } catch (err) {
        console.error('Load feed error:', err);
        showToast('Failed to load feed', 'error');
    }
}

/*
|--------------------------------------------------------------------------
| INITIALIZE
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', () => {
    loadFeed();

    // Close modals on outside click
    document.addEventListener('click', function(e) {
        ['postModal', 'reportModal', 'shareModal', 'announceModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el && e.target === el) el.classList.remove('show');
        });
    });
});