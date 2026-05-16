// feed-dynamic.js - fully dynamic feed loader

function dynamicRenderPosts(posts) {
  const container = document.getElementById('feedPosts');
  if (!container) return;
  container.innerHTML = '';

  posts.forEach(p => {
    // createPostCard already exists in feed.js; fallback if not present.
    if (typeof createPostCard === 'function') {
      container.appendChild(createPostCard(p));
    } else {
      const el = document.createElement('div');
      el.textContent = p.content || '';
      container.appendChild(el);
    }
  });
}

async function loadFeedPostsDynamic(page = 1) {
    console.log('loadFeedPostsDynamic called, page:', page);

    const container = document.getElementById('feedPosts');
    if (!container) {
        console.error('feedPosts container not found');
        return;
    }

    // Show loading state
    container.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading posts...</div>';

    try {
        // Pass user_id explicitly so get-posts.php can set session user_id fallback.
        // Use a resilient user_id source for the feed.
        // main-feed.html is a PHP-rendered authenticated page, but JS fetch may not have session cookies in some setups.
        // We try multiple client sources and ALSO send user_id even if we later clear cookies.
        const uid = (typeof getCurrentUserId === 'function'
          ? getCurrentUserId()
          : (window.currentUserId || localStorage.getItem('currentUserId') || localStorage.getItem('user_id') || localStorage.getItem('userId')));
        const trimmedUid = uid != null ? String(uid).trim() : '';
        console.log('feed-dynamic user_id:', { uid, trimmedUid });

        // So send form-urlencoded instead of application/json.

        const body = new URLSearchParams();
        body.set('page', String(page));
        if (trimmedUid) body.set('user_id', trimmedUid);

        const response = await fetch('/api/users/posts/get-posts.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
        });
        const debugText = await response.text();
        console.log('get-posts status/body:', { status: response.status, body: debugText.slice(0, 1000) });
        let data;
        try { data = JSON.parse(debugText); } catch { data = null; }
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${data?.error || response.statusText}`);
        }


        console.log('Feed API response:', data);

        if (!data.success) {
            throw new Error(data.error || 'Failed to load posts');
        }

        if (!data.posts || data.posts.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--color-subtext);">No posts yet. Be the first to create a post!</div>';
            return;
        }

        container.innerHTML = '';

        // Ensure the toggleLike handler works by generating the exact same markup shape
        // as feed.js server-rendered cards.
        data.posts.forEach(post => {
            // Normalize IDs/fields to match createPostCard in feed.js
            // (API responses sometimes use post_id instead of id, so force a stable key set.)
            const normalized = { ...post };

            // id (stable)
            const stableId = normalized.id ?? normalized.post_id;
            normalized.id = stableId;

            // liked flag (stable)
            // Some APIs return 0/1, '0'/'1', or boolean; coerce to boolean.
            normalized.user_liked = !!normalized.user_liked;

            // like count / comment count (stable numeric)
            normalized.like_count = Number(normalized.like_count ?? normalized.likesCount ?? 0);
            normalized.comment_count = Number(normalized.comment_count ?? normalized.commentsCount ?? 0);

            // author fields
            normalized.full_name = normalized.full_name || `${normalized.first_name || ''} ${normalized.last_name || ''}`.trim() || 'Unknown User';
            normalized.profile_picture = normalized.profile_picture || 'http://localhost/assets/default.png';


            // If createPostCard exists, use it (this produces onclick="return toggleLike(this)" + data-post-id)
            if (typeof createPostCard === 'function') {
                const card = createPostCard(normalized);
                container.appendChild(card);
                return;
            }

            // Fallback: replicate the essential button markup so toggleLike works.
            const postId = normalized.id;
            const userLiked = normalized.user_liked;

            const fallbackCard = document.createElement('div');
            fallbackCard.className = 'post-card';
            fallbackCard.dataset.postId = postId;

            fallbackCard.innerHTML = `
                <div class="post-header">
                    <img src="${escapeHtml(normalized.profile_picture)}" alt="User" class="post-avatar"/>
                    <div class="post-meta">
                        <div class="post-author">${escapeHtml(normalized.full_name)}</div>
                        <div class="post-community">Community · <span class="post-time">${escapeHtml(normalized.created_at || '')}</span></div>
                    </div>
                    <button class="options-btn" type="button" onclick="togglePostOptions(this)"><i class="fas fa-sliders-h"></i></button>
                    <div class="post-options-menu" role="menu">
                        <div class="post-option" onclick="editPost(this)"><i class="fas fa-pen"></i> Edit Post</div>
                        <div class="post-option danger" onclick="deletePost(this)"><i class="fas fa-trash"></i> Delete Post</div>
                        <div class="post-option" onclick="openReportModal(this)"><i class="fas fa-flag"></i> Report</div>
                        <div class="post-option" onclick="openAnnounceModal(this)"><i class="fas fa-bullhorn"></i> Request to Announce</div>
                    </div>
                </div>
                <div class="post-body">
                    <p>${escapeHtml(normalized.content || '')}</p>
                    ${normalized.image ? `<div class="image-grid grid-1"><img src="${escapeHtml(normalized.image)}" alt="Post image" class="post-image"/></div>` : ''}
                </div>
                <div class="post-footer">
                    <button class="reaction-btn ${userLiked ? 'liked' : ''}" data-post-id="${postId}" type="button" onclick="return toggleLike(this)">
                        <i class="${userLiked ? 'fas' : 'far'} fa-heart"></i>
                        <span>${normalized.like_count}</span>
                    </button>
                    <button class="reaction-btn" type="button" onclick="toggleComments(this)">
                        <i class="fas fa-comment"></i> <span>${normalized.comment_count}</span>Comment
                    </button>
                    <button class="reaction-btn" type="button" onclick="openShareModal(this)">
                        <i class="fas fa-share"></i> <span>Share</span>
                    </button>
                </div>
                <div class="comment-section" style="display:none;">
                    <div class="comment-input-row">
                        <img src="http://localhost/assets/default.png" alt="User"/>
                        <div class="comment-input-wrap">
                            <input type="text" placeholder="Write a comment..."/>
                            <button class="comment-send-btn" type="button" onclick="addComment(this)"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(fallbackCard);
        });

        console.log(`Successfully loaded ${data.posts.length} posts`);

    } catch (error) {
        console.error('Error loading feed posts:', error);
        container.innerHTML = `<div style="text-align:center;padding:20px;color:red;">
            <i class="fas fa-exclamation-circle"></i> Failed to load posts: ${error.message}<br>
            <small>Check console for details</small>
        </div>`;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Auto-load on page ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => loadFeedPostsDynamic(1));
} else {
    loadFeedPostsDynamic(1);
}
