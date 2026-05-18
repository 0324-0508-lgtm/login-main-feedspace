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
// feed-dynamic.js - fully dynamic feed loader

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
        // Use the dedicated AJAX feed endpoint (prevents 401 from main-feed.php auth guard)
        const response = await fetch('../api/users/posts/get-posts.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                page: String(page)
            }).toString()
        });

        const data = await response.json();
        console.log('Feed API response:', data);

        if (!data.success) {
            throw new Error(data.error || 'Failed to load posts');
        }

        if (!data.posts || data.posts.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--color-subtext);">No posts yet. Be the first to create a post!</div>';
            return;
        }

        container.innerHTML = '';

        data.posts.forEach(post => {
            // Normalize post data
            const normalized = { ...post };
            normalized.id = normalized.post_id;
            normalized.user_liked = !!normalized.user_liked;
            normalized.like_count = Number(normalized.like_count ?? 0);
            normalized.comment_count = Number(normalized.comment_count ?? 0);
            normalized.full_name = normalized.full_name || 'Unknown User';
            normalized.profile_picture = normalized.profile_picture || 'http://localhost/assets/default.png';

            if (typeof createPostCard === 'function') {
                const card = createPostCard(normalized);
                container.appendChild(card);
            } else {
                // Fallback rendering
                const card = createFallbackPostCard(normalized);
                container.appendChild(card);
            }
        });

        console.log(`Successfully loaded ${data.posts.length} posts`);

    } catch (error) {
        console.error('Error loading feed posts:', error);
        container.innerHTML = `<div style="text-align:center;padding:20px;color:red;">
            <i class="fas fa-exclamation-circle"></i> Failed to load posts: ${error.message}<br>
            <small>Please refresh the page or check your connection</small>
        </div>`;
    }
}

function createFallbackPostCard(post) {
    const card = document.createElement('div');
    card.className = 'post-card';
    card.dataset.postId = post.id;

    card.innerHTML = `
        <div class="post-header">
            <img src="${escapeHtml(post.profile_picture)}" alt="User" class="post-avatar"/>
            <div class="post-meta">
                <div class="post-author">${escapeHtml(post.full_name)}</div>
                <div class="post-community">Community · <span class="post-time">${escapeHtml(post.created_at || '')}</span></div>
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
            <p>${escapeHtml(post.content || '')}</p>
            ${post.image ? `<div class="image-grid grid-1"><img src="${escapeHtml(post.image)}" alt="Post image" class="post-image"/></div>` : ''}
        </div>
        <div class="post-footer">
            <button class="reaction-btn ${post.user_liked ? 'liked' : ''}" data-post-id="${post.id}" type="button" onclick="toggleLike(this)">
                <i class="${post.user_liked ? 'fas' : 'far'} fa-heart"></i>
                <span>${post.like_count}</span>
            </button>
            <button class="reaction-btn" type="button" onclick="toggleComments(this)">
                <i class="fas fa-comment"></i> <span>${post.comment_count}</span>Comment
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

    return card;
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

// Only load dynamic posts if container is empty or we need fresh data
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('feedPosts');
        // Only load dynamically if there are no server-rendered posts
        if (container && container.children.length === 0) {
            loadFeedPostsDynamic(1);
        }
    });
} else {
    const container = document.getElementById('feedPosts');
    if (container && container.children.length === 0) {
        loadFeedPostsDynamic(1);
    }
}
