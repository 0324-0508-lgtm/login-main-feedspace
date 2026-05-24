// feed.js

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
    // Hook into base.js toast if available
    if (window._showToast) window._showToast(message, type);
}

function togglePostOptions(btn) {
    var card = btn.closest('.post-card');
    var menu = card ? card.querySelector('.post-options-menu') : null;
    if (!menu) return;
    document.querySelectorAll('.post-options-menu.show').forEach(function(m) {
        if (m !== menu) m.classList.remove('show');
    });
    menu.classList.toggle('show');

    function closeOnClickOutside(e) {
        if (!menu.contains(e.target) && !btn.contains(e.target)) {
            menu.classList.remove('show');
            document.removeEventListener('click', closeOnClickOutside);
        }
    }
    if (menu.classList.contains('show')) {
        setTimeout(function() {
            document.addEventListener('click', closeOnClickOutside);
        }, 0);
    }
}

// ===== EDIT POST =====
var currentEditPostId = null;
var currentEditHasImage = false;
var currentEditImageRemoved = false;

function editPost(postId) {
    if (!postId) return;
    currentEditPostId = postId;
    currentEditImageRemoved = false;

    var card = document.querySelector('.post-card[data-post-id="' + postId + '"]');
    if (!card) { showToast('Post not found', 'error'); return; }

    var menu = card.querySelector('.post-options-menu');
    if (menu) menu.classList.remove('show');

    var contentEl = card.querySelector('.post-body > p');
    var imgEl = card.querySelector('.post-image');
    var text = contentEl ? contentEl.textContent.trim() : '';

    var editIdInput = document.getElementById('editPostId');
    var editTextInput = document.getElementById('editPostText');
    if (editIdInput) editIdInput.value = postId;
    if (editTextInput) editTextInput.value = text;

    var previewWrap = document.getElementById('editImagePreview');
    var previewImg  = document.getElementById('editPreviewImg');
    var removeBtn   = document.getElementById('editRemovePhotoBtn');
    var fileInput   = document.getElementById('editPostImage');
    if (fileInput) fileInput.value = '';

    if (imgEl && imgEl.src) {
        currentEditHasImage = true;
        if (previewImg) previewImg.src = imgEl.src;
        if (previewWrap) previewWrap.style.display = 'block';
        if (removeBtn) removeBtn.style.display = 'inline-flex';
    } else {
        currentEditHasImage = false;
        if (previewWrap) previewWrap.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'none';
    }

    var modal = document.getElementById('editModal');
    if (modal) modal.classList.add('show');
}
window.editPost = editPost;

function previewEditImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview  = document.getElementById('editImagePreview');
            var img      = document.getElementById('editPreviewImg');
            var removeBtn = document.getElementById('editRemovePhotoBtn');
            if (preview && img) { img.src = e.target.result; preview.style.display = 'block'; }
            if (removeBtn) removeBtn.style.display = 'inline-flex';
            currentEditImageRemoved = false;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
window.previewEditImage = previewEditImage;

function clearEditImage() {
    var input   = document.getElementById('editPostImage');
    var preview = document.getElementById('editImagePreview');
    var removeBtn = document.getElementById('editRemovePhotoBtn');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
    if (removeBtn) removeBtn.style.display = 'none';
    currentEditImageRemoved = true;
}
window.clearEditImage = clearEditImage;

function removeEditImage() { clearEditImage(); }
window.removeEditImage = removeEditImage;

async function submitEdit() {
    if (!currentEditPostId) { showToast('No post selected to edit', 'error'); return; }

    var textInput  = document.getElementById('editPostText');
    var text       = textInput ? textInput.value.trim() : '';
    var fileInput  = document.getElementById('editPostImage');
    var hasNewFile = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (!text && !hasNewFile && currentEditImageRemoved) {
        showToast('Post cannot be empty. Write something or add a photo.', 'warning');
        return;
    }

    var btn = document.querySelector('#editModal .btn-primary');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }

    var form = new FormData();
    form.append('post_id', currentEditPostId);
    form.append('content', text);
    if (hasNewFile) form.append('image', fileInput.files[0]);
    if (currentEditImageRemoved) form.append('remove_image', '1');

    try {
        var response = await fetch('../api/users/posts/edit-posts.php', {
            method: 'POST', credentials: 'include', body: form
        });
        var raw = await response.text();
        var data;
        try { data = JSON.parse(raw); } catch(e) { data = null; }
        if (!response.ok || !data || !data.success) {
            throw new Error((data && data.error) ? data.error : raw || response.statusText);
        }
        showToast('Post updated! ✏️');
        closeModal('editModal');
        if (typeof loadFeedPostsDynamic === 'function') loadFeedPostsDynamic(1);
        else window.location.reload();
    } catch (err) {
        console.error('submitEdit error:', err);
        showToast('Error: ' + err.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save Changes'; }
        currentEditPostId = null;
        currentEditHasImage = false;
        currentEditImageRemoved = false;
    }
}
window.submitEdit = submitEdit;

// ===== DELETE POST =====
async function deletePost(postId) {
    if (!postId) { console.error('[ERROR] No post ID for deletion'); return; }

    var card = document.querySelector('.post-card[data-post-id="' + postId + '"]');
    if (!card) { showToast('Post not found', 'error'); return; }

    var postUserId = card.dataset.userId;
    if (String(window.__currentUserId) !== String(postUserId)) {
        showToast('You can only delete your own posts', 'error');
        return;
    }

    if (!confirm('Are you sure you want to delete this post?')) return;

    try {
        var response = await fetch('../api/users/posts/delete-post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ post_id: parseInt(postId) })
        });
        var data = await response.json();
        if (data.success) {
            showToast('Post deleted', 'success');
            card.style.transition = 'opacity 0.3s, transform 0.3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(function() { card.remove(); }, 300);
        } else {
            throw new Error(data.error || 'Delete failed');
        }
    } catch (error) {
        console.error('[ERROR] Delete failed:', error.message);
        showToast('Failed to delete post: ' + error.message, 'error');
    }
}
window.deletePost = deletePost;

// ===== LIKE =====
function toggleLike(btn) {
    var postId = btn.dataset.postId;
    if (!postId) return;
    fetch('../api/users/interactions/toggle-post-like.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res || !res.success) throw new Error(res.error || 'Like failed');
        btn.classList.toggle('liked', res.liked);
        var icon = btn.querySelector('i');
        if (icon) icon.className = res.liked ? 'fas fa-heart' : 'far fa-heart';
        var span = btn.querySelector('span');
        if (span) span.textContent = String(res.likesCount || 0);
    })
    .catch(function(err) { console.error(err); showToast('Like failed', 'error'); });
}
window.toggleLike = toggleLike;

// ===== COMMENTS =====
async function loadComments(postId, section) {
    try {
        var res = await fetch('../api/users/interactions/get-comments.php', {
            method: 'POST', credentials: 'include',
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
        data.comments.forEach(function(c) {
            var item = document.createElement('div');
            item.className = 'comment-item';
            item.innerHTML = '<div class="comment-bubble"><div class="comment-author">' + escapeHtml(c.author) + '</div><div class="comment-text">' + escapeHtml(c.content) + '</div></div>';
            section.appendChild(item);
        });
    } catch (err) { console.error(err); showToast('Failed to load comments', 'error'); }
}

function toggleComments(btn) {
    var card = btn.closest('.post-card');
    var section = card ? card.querySelector('.comment-section') : null;
    if (!section) return;
    var isHidden = section.style.display === 'none' || !section.style.display;
    section.style.display = isHidden ? 'block' : 'none';
    if (isHidden) loadComments(card.dataset.postId, section);
}
window.toggleComments = toggleComments;

function addComment(btn) {
    var wrap  = btn.closest('.comment-input-wrap');
    var input = wrap.querySelector('input');
    var text  = input.value.trim();
    if (!text) { showToast('Write something first!', 'warning'); return; }
    var card   = btn.closest('.post-card');
    var postId = card.dataset.postId;
    btn.disabled = true;
    fetch('../api/users/interactions/add-comments.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: parseInt(postId, 10), content: text })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res || !res.success) throw new Error(res.error || 'Failed to add comment');
        showToast('Comment added!');
        input.value = '';
        loadComments(postId, btn.closest('.comment-section'));
    })
    .catch(function(err) { console.error(err); showToast('Failed to add comment', 'error'); })
    .finally(function() { btn.disabled = false; });
}
window.addComment = addComment;

// ===== MODALS =====
function openPostModal(prefill) {
    var ta = document.getElementById('modalPostText');
    if (ta) ta.value = prefill || '';
    var modal = document.getElementById('postModal');
    if (modal) modal.classList.add('show');
    if (ta) setTimeout(function() { ta.focus(); }, 50);
}
window.openPostModal = openPostModal;

function openPostModalWithImage() {
    openPostModal();
    // Wait for modal to be visible before triggering file input
    setTimeout(function() {
        var fileInput = document.getElementById('modalPostImage');
        if (fileInput) fileInput.click();
    }, 300);
}
window.openPostModalWithImage = openPostModalWithImage;

function previewModalImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('modalImagePreview');
            var img     = document.getElementById('modalPreviewImg');
            if (preview && img) { img.src = e.target.result; preview.style.display = 'block'; }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
window.previewModalImage = previewModalImage;

function clearModalImage() {
    var input   = document.getElementById('modalPostImage');
    var preview = document.getElementById('modalImagePreview');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
}
window.clearModalImage = clearModalImage;

function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('show');
}
window.closeModal = closeModal;

function submitPost() {
    var ta       = document.getElementById('modalPostText');
    var text     = ta ? ta.value.trim() : '';
    var fileInput = document.getElementById('modalPostImage');
    var hasFile  = !!(fileInput && fileInput.files && fileInput.files[0]);

    if (!text && !hasFile) {
        showToast('Write something or attach a photo first!', 'warning');
        return;
    }

    var btn = document.querySelector('#postModal .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Creating...'; }

    var form = new FormData();
    form.append('content', text);
    if (hasFile) form.append('image', fileInput.files[0]);

    fetch('../api/users/posts/create-post.php', {
        method: 'POST', credentials: 'include', body: form
    })
    .then(async function(r) {
        var raw = await r.text();
        var data;
        try { data = JSON.parse(raw); } catch(e) { data = null; }
        if (!r.ok || !data || !data.success) {
            throw new Error((data && data.error) ? data.error : raw || r.statusText);
        }
        return data;
    })
    .then(function() {
        showToast('Post created! 🎉');
        closeModal('postModal');
        if (ta) ta.value = '';
        clearModalImage();
        if (fileInput) fileInput.value = '';
        // Reload feed dynamically, not the whole page
        if (typeof loadFeedPostsDynamic === 'function') loadFeedPostsDynamic(1);
        else window.location.reload();
    })
    .catch(function(err) {
        console.error('submitPost error:', err);
        showToast('Error: ' + err.message, 'error');
    })
    .finally(function() {
        if (btn) { btn.disabled = false; btn.textContent = '+ Create Post'; }
    });
}
window.submitPost = submitPost;

// ===== REPORT =====
function openReportModal(postId) {
    if (postId && postId.closest) {
        var card = postId.closest('.post-card');
        postId = card ? card.dataset.postId : null;
    }
    if (!postId) { console.error('[ERROR] No post ID for report'); return; }
    document.getElementById('report-post-id').value = postId;
    document.getElementById('report-reason').value = '';
    document.getElementById('report-description').value = '';
    document.getElementById('report-reason-error').textContent = '';
    document.getElementById('report-desc-error').textContent = '';
    document.getElementById('reportModal').classList.add('show');
}
window.openReportModal = openReportModal;

async function submitReport() {
    var postId      = document.getElementById('report-post-id').value;
    var reason      = document.getElementById('report-reason').value;
    var description = document.getElementById('report-description').value.trim();

    document.getElementById('report-reason-error').textContent = '';
    document.getElementById('report-desc-error').textContent = '';

    if (!reason) {
        document.getElementById('report-reason-error').textContent = 'Please select a reason';
        document.getElementById('report-reason').focus();
        return;
    }
    if (reason === 'other' && !description) {
        document.getElementById('report-desc-error').textContent = 'Please provide details for "Other"';
        document.getElementById('report-description').focus();
        return;
    }

    try {
        var response = await fetch('../api/users/reports/report-post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: parseInt(postId), reason: reason, description: description })
        });
        var contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server error. Please try again later.');
        }
        var data = await response.json();
        if (data.success) {
            showToast('Report submitted successfully', 'success');
            closeModal('reportModal');
        } else {
            throw new Error(data.error || 'Report failed');
        }
    } catch (error) {
        console.error('[ERROR] Report failed:', error.message);
        showToast('Failed to submit report: ' + error.message, 'error');
    }
}
window.submitReport = submitReport;

// ===== SHARE =====
var currentSharePostId = null;

function openShareModal(btn) {
    var card = btn.closest('.post-card');
    if (!card) return;
    currentSharePostId = card.dataset.postId;

    var authorEl  = card.querySelector('.post-author');
    var contentEl = card.querySelector('.post-body > p');
    var imgEl     = card.querySelector('.post-image');

    var previewAuthor    = document.getElementById('sharePreviewAuthor');
    var previewText      = document.getElementById('sharePostPreview');
    var previewContainer = document.getElementById('sharePreviewContainer');

    if (previewAuthor) previewAuthor.textContent = (authorEl ? authorEl.textContent.trim() : 'Unknown') + ' · Community';
    if (previewText)   previewText.textContent   = contentEl ? contentEl.textContent.trim().slice(0, 120) : '';

    // Remove old preview image
    var old = previewContainer ? previewContainer.querySelector('.sp-preview-image') : null;
    if (old) old.remove();

    if (imgEl && imgEl.src && previewContainer) {
        var imgWrap = document.createElement('div');
        imgWrap.className = 'sp-preview-image';
        imgWrap.style.cssText = 'margin-top:8px;border-radius:8px;overflow:hidden;';
        imgWrap.innerHTML = '<img src="' + imgEl.src + '" style="width:100%;max-height:200px;object-fit:cover;display:block;" onerror="this.style.display=\'none\'"/>';
        previewContainer.appendChild(imgWrap);
    }

    var ta = document.getElementById('shareText');
    if (ta) ta.value = '';
    document.getElementById('shareModal').classList.add('show');
}
window.openShareModal = openShareModal;

function submitShare() {
    if (!currentSharePostId) { showToast('No post selected to share', 'error'); return; }

    var ta   = document.getElementById('shareText');
    var text = ta ? ta.value.trim() : '';
    var btn  = document.querySelector('#shareModal .btn-primary');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sharing...'; }

    var form = new FormData();
    form.append('content', text);
    form.append('shared_post_id', currentSharePostId);

    fetch('../api/users/posts/create-post.php', {
        method: 'POST', credentials: 'include', body: form
    })
    .then(async function(r) {
        var raw = await r.text();
        var data;
        try { data = JSON.parse(raw); } catch(e) { data = null; }
        if (!r.ok || !data || !data.success) throw new Error((data && data.error) ? data.error : raw || r.statusText);
        return data;
    })
    .then(function() {
        showToast('Post shared! 🎉');
        closeModal('shareModal');
        if (ta) ta.value = '';
        if (typeof loadFeedPostsDynamic === 'function') loadFeedPostsDynamic(1);
        else window.location.reload();
    })
    .catch(function(err) { console.error('submitShare error:', err); showToast('Error: ' + err.message, 'error'); })
    .finally(function() {
        if (btn) { btn.disabled = false; btn.textContent = 'Share Post'; }
        currentSharePostId = null;
    });
}
window.submitShare = submitShare;

// ===== ANNOUNCE =====
function openAnnounceModal() {
    var el = document.getElementById('announceModal');
    if (el) el.classList.add('show');
}
window.openAnnounceModal = openAnnounceModal;

function submitAnnounceRequest() {
    showToast('Announcement requests are not available yet.', 'warning');
    closeModal('announceModal');
}
window.submitAnnounceRequest = submitAnnounceRequest;

// ===== CREATE POST CARD =====
function createPostCard(post) {
    var card   = document.createElement('div');
    card.className = 'post-card';

    var postId       = post.post_id || post.id;
    var userId       = post.user_id || '';
    var avatar       = post.profile_picture || DEFAULT_AVATAR;
    var author       = post.full_name || 'Unknown';
    var content      = post.content || '';
    var image        = post.image || post.file_url;
    var likeCount    = Number(post.like_count) || 0;
    var commentCount = Number(post.comment_count) || 0;
    var userLiked    = !!post.user_liked;
    var isShared     = !!post.is_shared;
    var profileLink  = 'profile.php?id=' + escapeAttr(String(userId));

    card.dataset.postId = postId;
    card.dataset.userId = userId;

    // Ownership — only show edit/delete for own posts
    var currentUserId = String(window.__currentUserId || '').trim();
    var isOwn = currentUserId && currentUserId === String(userId).trim();

    var ownerOptions = isOwn ? (
        '<div class="post-option" onclick="editPost(\'' + postId + '\')"><i class="fas fa-pen"></i> Edit Post</div>' +
        '<div class="post-option danger" onclick="deletePost(\'' + postId + '\')"><i class="fas fa-trash"></i> Delete Post</div>'
    ) : '';

    // Shared post inner card
    var sharedHtml = '';
    if (isShared && post.original) {
        var orig = post.original;
        var origImg = (orig.image || orig.file_url)
            ? '<div class="sp-image-wrap"><img src="' + escapeAttr(orig.image || orig.file_url) + '" class="sp-image" onerror="this.style.display=\'none\'"/></div>'
            : '';
        sharedHtml = '<div class="shared-post-card">' +
            '<div class="sp-header">' +
            '<img src="' + escapeAttr(orig.profile_picture || DEFAULT_AVATAR) + '" class="sp-avatar" onerror="this.src=\'' + DEFAULT_AVATAR + '\'"/>' +
            '<div class="sp-meta"><span class="sp-author">' + escapeHtml(orig.full_name || 'Unknown') + '</span>' +
            '<span class="sp-time">' + escapeHtml(orig.created_at || '') + '</span></div></div>' +
            '<div class="sp-body"><p class="sp-content">' + escapeHtml(orig.content || '') + '</p>' + origImg + '</div></div>';
    }

    var postImageHtml = (!isShared && image)
        ? '<div class="image-grid grid-1"><img src="' + escapeAttr(image) + '" alt="Post image" class="post-image" onerror="this.style.display=\'none\'"/></div>'
        : '';

    var likeIconClass = userLiked ? 'fas fa-heart' : 'far fa-heart';
    var likeBtnClass  = userLiked ? 'reaction-btn liked' : 'reaction-btn';

    card.innerHTML =
        '<div class="post-header">' +
            '<a href="' + profileLink + '" class="post-avatar-link">' +
                '<img src="' + escapeAttr(avatar) + '" alt="User" class="post-avatar" onerror="this.src=\'' + DEFAULT_AVATAR + '\'"/>' +
            '</a>' +
            '<div class="post-meta">' +
                '<a href="' + profileLink + '" class="post-author-link">' +
                    '<div class="post-author">' + escapeHtml(author) + (isShared ? ' <span class="shared-badge"><i class="fas fa-share"></i> Shared</span>' : '') + '</div>' +
                '</a>' +
                '<div class="post-community">Community · <span class="post-time">' + escapeHtml(post.created_at || '') + '</span></div>' +
            '</div>' +
            /* FIX: fa-ellipsis-h = 3-dot menu icon */
            '<button class="options-btn" type="button" onclick="togglePostOptions(this)" aria-label="Post options">' +
                '<i class="fas fa-ellipsis-h"></i>' +
            '</button>' +
            '<div class="post-options-menu">' +
                ownerOptions +
                '<div class="post-option" onclick="openReportModal(\'' + postId + '\')"><i class="fas fa-flag"></i> Report</div>' +
                '<div class="post-option" onclick="openAnnounceModal()"><i class="fas fa-bullhorn"></i> Request to Announce</div>' +
            '</div>' +
        '</div>' +
        '<div class="post-body"><p>' + escapeHtml(content) + '</p>' + postImageHtml + sharedHtml + '</div>' +
        '<div class="post-footer">' +
            '<button class="' + likeBtnClass + '" data-post-id="' + postId + '" type="button" onclick="toggleLike(this)">' +
                '<i class="' + likeIconClass + '"></i><span>' + likeCount + '</span>' +
            '</button>' +
            '<button class="reaction-btn" type="button" onclick="toggleComments(this)">' +
                '<i class="fas fa-comment"></i> <span>' + commentCount + '</span> Comment' +
            '</button>' +
            '<button class="reaction-btn" type="button" onclick="openShareModal(this)">' +
                '<i class="fas fa-share"></i> Share' +
            '</button>' +
        '</div>' +
        '<div class="comment-section" style="display:none;">' +
            '<div class="comment-input-row">' +
                '<img src="' + DEFAULT_AVATAR + '" alt="User"/>' +
                '<div class="comment-input-wrap">' +
                    '<input type="text" placeholder="Write a comment..."/>' +
                    '<button class="comment-send-btn" type="button" onclick="addComment(this)"><i class="fas fa-paper-plane"></i></button>' +
                '</div>' +
            '</div>' +
        '</div>';

    return card;
}
window.createPostCard = createPostCard;

// ===== LOAD FEED =====
async function loadFeed(page) {
    page = page || 1;
    try {
        var res = await fetch('../api/users/posts/get-posts.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ page: page })
        });
        var data = await res.json();
        if (!data || !data.success) throw new Error(data.error || 'Failed to load feed');
        var container = document.getElementById('feedPosts');
        if (!container) return;
        data.posts.forEach(function(post) {
            var normalized = Object.assign({}, post);
            normalized.id = normalized.post_id;
            normalized.user_liked  = !!normalized.user_liked;
            normalized.like_count  = Number(normalized.like_count || 0);
            normalized.comment_count = Number(normalized.comment_count || 0);
            container.appendChild(createPostCard(normalized));
        });
    } catch (err) {
        console.error(err);
        showToast('Failed to load feed', 'error');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Close modals on backdrop click
    document.addEventListener('click', function(e) {
        ['postModal','editModal','reportModal','shareModal','announceModal'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el && e.target === el) el.classList.remove('show');
        });
    });

    // Nav search — filter rendered post cards
    var navInput = document.getElementById('navSearchInput');
    if (navInput) {
        navInput.addEventListener('input', function() {
            var q = navInput.value.trim().toLowerCase();
            document.querySelectorAll('#feedPosts .post-card').forEach(function(card) {
                card.style.display = (!q || card.innerText.toLowerCase().includes(q)) ? '' : 'none';
            });
        });
    }
});