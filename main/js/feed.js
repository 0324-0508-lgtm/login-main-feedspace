// feed.js — FeedSpace Main Feed

const DEFAULT_AVATAR = '/login-main-feedspace/assets/default.jpg';

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

/*
|--------------------------------------------------------------------------
| POST OPTIONS MENU
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| EDIT POST
|--------------------------------------------------------------------------
*/

function editPost(el) {
    var card = el.closest('.post-card');
    var body = card.querySelector('.post-body > p');
    var newText = prompt('Edit your post:', body ? body.innerText : '');

    if (newText !== null && newText.trim()) {
        var postId = card.dataset.postId;

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
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                if (body) body.innerText = newText;
                showToast('Post updated!');
            } else {
                showToast(res.error || 'Update failed', 'error');
            }
        })
        .catch(function() { showToast('Update failed', 'error'); });
    }
    closeOptions(el);
}

/*
|--------------------------------------------------------------------------
| DELETE POST
|--------------------------------------------------------------------------
*/

function deletePost(el) {
    var card = el.closest('.post-card');
    if (!card) return;

    if (!confirm('Delete this post?')) {
        closeOptions(el);
        return;
    }

    var postId = card.dataset.postId;

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
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            setTimeout(function() { card.remove(); }, 290);
            showToast('Post deleted.');
        } else {
            showToast(res.error || 'Delete failed', 'error');
            card.style.opacity = '1';
            card.style.transform = 'none';
        }
    })
    .catch(function() {
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
    var postId = btn && btn.dataset && btn.dataset.postId;
    if (!postId) return;

    var isLiked = btn.classList.contains('liked');
    var span = btn.querySelector('span');

    fetch('../api/users/interactions/toggle-post-like.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res || !res.success) throw new Error(res && res.error ? res.error : 'Toggle failed');

        var nextLiked = !!res.liked;
        btn.classList.toggle('liked', nextLiked);
        var icon = btn.querySelector('i');
        if (icon) icon.className = nextLiked ? 'fas fa-heart' : 'far fa-heart';
        if (span) span.textContent = String(res.likesCount != null ? res.likesCount : 0);
    })
    .catch(function() { showToast(isLiked ? 'Failed to unlike' : 'Failed to like', 'error'); });
}

/*
|--------------------------------------------------------------------------
| COMMENTS
|--------------------------------------------------------------------------
*/

async function loadComments(postId, section) {
    try {
        var res = await fetch('../api/users/interactions/get-comments.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: parseInt(postId, 10), page: 1 })
        });

        var text = await res.text();
        var data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Server returned non-JSON:', text.substring(0, 500));
            throw new Error('Server error - check PHP logs');
        }

        if (!data || !data.success) throw new Error(data && data.error ? data.error : 'Failed to load comments');

        var inputRow = section.querySelector('.comment-input-row');
        section.innerHTML = '';
        if (inputRow) section.appendChild(inputRow);

        if (!data.comments || data.comments.length === 0) {
            var emptyMsg = document.createElement('div');
            emptyMsg.className = 'comment-empty';
            emptyMsg.textContent = 'No comments yet. Be the first!';
            section.appendChild(emptyMsg);
            return;
        }

        data.comments.forEach(function(comment) {
            var item = document.createElement('div');
            item.className = 'comment-item';

            var modBadge = '';
            if (comment.moderation_status === 'flagged') {
                modBadge = '<span class="mod-badge flagged">⚠️ Under Review</span>';
            }

            var toxicityBadge = '';
            if (comment.toxicity_score && comment.toxicity_score > 0.5) {
                toxicityBadge = '<span class="toxicity-badge" title="Toxicity: ' + Math.round(comment.toxicity_score * 100) + '%">⚠️</span>';
            }

            item.innerHTML = '<img src="' + escapeAttr(comment.avatar || DEFAULT_AVATAR) + '" alt="User"/>' +
                '<div class="comment-bubble">' +
                    '<div class="comment-author">' + escapeHtml(comment.author) + toxicityBadge + '</div>' +
                    '<div class="comment-text">' + escapeHtml(comment.content) + '</div>' +
                    modBadge +
                '</div>';
            section.appendChild(item);
        });

    } catch (err) {
        console.error('Load comments error:', err);
        showToast('Failed to load comments', 'error');
    }
}

function toggleComments(btn) {
    var card = btn.closest('.post-card');
    var section = card.querySelector('.comment-section');
    if (!section) return;

    var isHidden = section.style.display === 'none' || !section.style.display;
    section.style.display = isHidden ? 'block' : 'none';

    if (isHidden) {
        var input = section.querySelector('input');
        if (input) input.focus();
        var postId = card.dataset.postId;
        if (postId) loadComments(postId, section);
    }
}

function addComment(btn) {
    var wrap = btn.closest('.comment-input-wrap');
    var input = wrap.querySelector('input');
    var text = input.value.trim();

    if (!text) {
        showToast('Write something first!', 'warning');
        return;
    }

    var card = btn.closest('.post-card');
    var postId = card && card.dataset ? card.dataset.postId : null;

    if (!postId) {
        showToast('Error: Post ID not found', 'error');
        return;
    }

    btn.disabled = true;
    var originalIcon = btn.innerHTML;
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
    .then(async function(r) {
        var text = await r.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Server returned non-JSON:', text.substring(0, 500));
            throw new Error('Server error - check PHP logs');
        }
    })
    .then(function(res) {
        if (!res || !res.success) throw new Error(res && res.error ? res.error : 'Failed to add comment');

        showToast('Comment added!');
        input.value = '';

        var section = btn.closest('.comment-section');
        loadComments(postId, section);

        var commentBtn = card.querySelector('.reaction-btn:has(i.fa-comment)');
        if (commentBtn) {
            var span = commentBtn.querySelector('span');
            if (span) span.textContent = String(res.comment_count || 0);
        }
    })
    .catch(function(err) {
        console.error('Comment error:', err);
        showToast('Failed to add comment', 'error');
    })
    .finally(function() {
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
    if (e && typeof e.preventDefault === 'function') e.preventDefault();

    var ta = document.getElementById('modalPostText');
    var text = ta ? ta.value.trim() : '';
    var fileInput = document.getElementById('modalPostImage');
    var hasFile = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (!text && !hasFile) {
        showToast('Write something or attach a photo first!', 'warning');
        return;
    }

    var btn = document.querySelector('#postModal .btn-primary');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Creating...';
    }

    var form = new FormData();
    form.append('content', text);

    if (fileInput && fileInput.files && fileInput.files[0]) {
        form.append('image', fileInput.files[0]);
    }

    fetch('../api/users/posts/create-post.php', {
        method: 'POST',
        credentials: 'include',
        body: form
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data || !data.success) {
            throw new Error(data && data.error ? data.error : 'Create failed');
        }

        showToast('Post created!');
        closeModal('postModal');
        if (ta) ta.value = '';
        if (fileInput) fileInput.value = '';
        window.location.reload();
    })
    .catch(function(err) {
        console.error('Post creation error:', err);
        showToast('Error: ' + err.message, 'error');
    })
    .finally(function() {
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

    var card = el.closest('.post-card');
    window.__pendingReportPostId = card && card.dataset ? card.dataset.postId || null : null;
}

function submitReport() {
    var reason = document.getElementById('reportReason');
    reason = reason ? reason.value.trim() : '';
    if (!reason) {
        showToast('Please provide a reason', 'warning');
        return;
    }

    var postId = window.__pendingReportPostId;
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
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            showToast('Post reported. Thank you!');
            closeModal('reportModal');
        } else {
            showToast(res.error || 'Report failed', 'error');
        }
    })
    .catch(function() { showToast('Report failed', 'error'); });
}

/*
|--------------------------------------------------------------------------
| SHARE POST
|--------------------------------------------------------------------------
*/

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
    closeModal('shareModal');
    var text = document.getElementById('shareText');
    text = text ? text.value.trim() : '';

    if (!window.__pendingSharePostId) {
        showToast('Error: No post selected', 'error');
        return;
    }

    var postId = window.__pendingSharePostId;
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
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res || !res.success) throw new Error(res && res.error ? res.error : 'Share failed');
        showToast(res.message || 'Post shared!');
        setTimeout(function() { window.location.reload(); }, 500);
    })
    .catch(function(err) {
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
    var modal = document.getElementById('announceModal');
    if (modal) modal.classList.add('show');

    var card = el.closest('.post-card');
    window.__pendingAnnouncePostId = card && card.dataset ? card.dataset.postId || null : null;
}

function submitAnnounceRequest() {
    var reason = document.getElementById('announceReason');
    reason = reason ? reason.value.trim() : '';
    if (!reason) {
        showToast('Please provide a reason', 'warning');
        return;
    }

    var postId = window.__pendingAnnouncePostId;
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
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            showToast('Announcement request submitted!');
            closeModal('announceModal');
        } else {
            showToast(res.error || 'Request failed', 'error');
        }
    })
    .catch(function() { showToast('Request failed', 'error'); });
}

/*
|--------------------------------------------------------------------------
| ARCHIVE POST
|--------------------------------------------------------------------------
*/

function archivePost(el) {
    var card = el.closest('.post-card');
    if (!card) return;

    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-8px)';

    setTimeout(function() { card.remove(); }, 290);
    showToast('Post archived.');
    closeOptions(el);
}

/*
|--------------------------------------------------------------------------
| CREATE POST CARD (with shared post support)
| Uses string concatenation ONLY - no template literals for HTML
|--------------------------------------------------------------------------
*/

function createPostCard(post) {
    var card = document.createElement('div');
    card.className = 'post-card';

    var postId = post.post_id || post.id;
    var avatar = post.profile_picture || DEFAULT_AVATAR;
    var author = post.full_name || 'Unknown';
    var content = post.content || '';
    var image = post.image || post.file_url;
    var likeCount = post.like_count || 0;
    var commentCount = post.comment_count || 0;
    var userLiked = post.user_liked || false;
    var isShared = post.is_shared || false;

    // Build badges using string concatenation - NO template literals
    var sharedBadge = '';
    if (isShared) {
        sharedBadge = '<span class="shared-badge"><i class="fas fa-share"></i> Shared</span>';
    }

    var aiBadge = '';
    if (post.ai_status === 'review') {
        aiBadge = '<span class="ai-badge review" title="' + escapeAttr(post.ai_reason || 'Under review') + '">🤖 Review</span>';
    } else if (post.ai_status === 'rejected') {
        aiBadge = '<span class="ai-badge rejected">🤖 Rejected</span>';
    }

    // Build shared post preview if applicable
    var sharedHtml = '';
    if (isShared && post.original_post) {
        var orig = post.original_post;
        var origAvatar = orig.profile_picture || DEFAULT_AVATAR;
        var origImageHtml = '';
        if (orig.file_url) {
            origImageHtml = '<div class="shared-image"><img src="' + escapeAttr(orig.file_url) + '" alt="Shared image"/></div>';
        }
        sharedHtml = '<div class="shared-post-wrapper">' +
            '<div class="shared-post-header"><i class="fas fa-retweet"></i><span>Shared from <strong>' + escapeHtml(orig.author) + '</strong></span></div>' +
            '<div class="shared-post-card">' +
                '<div class="shared-post-author">' +
                    '<img src="' + escapeAttr(origAvatar) + '" alt="User" class="shared-avatar"/>' +
                    '<div class="shared-author-info">' +
                        '<div class="shared-name">' + escapeHtml(orig.author) + '</div>' +
                        '<div class="shared-time">' + escapeHtml(orig.created_at) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="shared-post-body"><p>' + escapeHtml(orig.content) + '</p>' + origImageHtml + '</div>' +
            '</div>' +
        '</div>';
    }

    card.dataset.postId = postId;

    // Build post image HTML
    var postImageHtml = '';
    if (!isShared && image) {
        postImageHtml = '<div class="image-grid grid-1"><img src="' + escapeAttr(image) + '" alt="Post image" class="post-image"/></div>';
    }

    // Build announcement badge
    var announcementBadge = '';
    if (post.is_announcement) {
        announcementBadge = '<span class="announcement-badge">📢 Announcement</span>';
    }

    // Build like icon class
    var likeIconClass = userLiked ? 'fas fa-heart' : 'far fa-heart';
    var likeBtnClass = userLiked ? 'reaction-btn liked' : 'reaction-btn';

    // Build the ENTIRE card using string concatenation ONLY
    // NO template literals (${...}) anywhere in the HTML
    var html = '<div class="post-header">' +
        '<img src="' + escapeAttr(avatar) + '" alt="User" class="post-avatar"/>' +
        '<div class="post-meta">' +
            '<div class="post-author">' + escapeHtml(author) + ' ' + sharedBadge + ' ' + aiBadge + '</div>' +
            '<div class="post-community">Community · <span class="post-time">' + escapeHtml(post.created_at || '') + '</span></div>' +
            announcementBadge +
        '</div>' +
        '<button class="options-btn" onclick="togglePostOptions(this)"><i class="fas fa-ellipsis-h"></i></button>' +
        '<div class="post-options-menu">' +
            '<div class="post-option" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit Post</div>' +
            '<div class="post-option danger" onclick="deletePost(this)"><i class="fas fa-trash"></i> Delete Post</div>' +
            '<div class="post-option" onclick="archivePost(this)"><i class="fas fa-archive"></i> Archive</div>' +
            '<div class="post-option" onclick="openReportModal(this)"><i class="fas fa-flag"></i> Report</div>' +
            '<div class="post-option" onclick="openAnnounceModal(this)"><i class="fas fa-bullhorn"></i> Request to Announce</div>' +
        '</div>' +
    '</div>' +
    '<div class="post-body">' +
        '<p>' + escapeHtml(content) + '</p>' +
        postImageHtml +
        sharedHtml +
    '</div>' +
    '<div class="post-footer">' +
        '<button class="' + likeBtnClass + '" data-post-id="' + postId + '" onclick="toggleLike(this)">' +
            '<i class="' + likeIconClass + '"></i>' +
            '<span>' + likeCount + '</span>' +
        '</button>' +
        '<button class="reaction-btn" onclick="toggleComments(this)">' +
            '<i class="fas fa-comment"></i> <span>' + commentCount + '</span> Comment' +
        '</button>' +
        '<button class="reaction-btn" onclick="openShareModal(this)">' +
            '<i class="fas fa-share"></i> <span>Share</span>' +
        '</button>' +
    '</div>' +
    '<div class="comment-section" style="display:none;">' +
        '<div class="comment-input-row">' +
            '<img src="' + DEFAULT_AVATAR + '" alt="User"/>' +
            '<div class="comment-input-wrap">' +
                '<input type="text" placeholder="Write a comment..."/>' +
                '<button class="comment-send-btn" onclick="addComment(this)"><i class="fas fa-paper-plane"></i></button>' +
            '</div>' +
        '</div>' +
    '</div>';

    card.innerHTML = html;
    return card;
}

/*
|--------------------------------------------------------------------------
| LOAD FEED
|--------------------------------------------------------------------------
*/

async function loadFeed(page) {
    page = page || 1;
    try {
        var res = await fetch('../api/users/posts/get-posts.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ page: page })
        });

        var data;
        try { data = await res.json(); }
        catch (e) { throw new Error('Invalid feed response'); }

        if (!data || !data.success) throw new Error(data && data.error ? data.error : 'Failed to load feed');

        var container = document.getElementById('feedPosts');
        if (!container) return;

        data.posts.forEach(function(post) {
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

document.addEventListener('DOMContentLoaded', function() {
    loadFeed();

    document.addEventListener('click', function(e) {
        ['postModal', 'reportModal', 'shareModal', 'announceModal'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el && e.target === el) el.classList.remove('show');
        });
    });
});