/* ── CREATE COMMUNITY CARD ── */
function createCommunityCard(c) {
    const commId = c.community_id || c.id;
    const name = c.community_name || c.name || 'Unnamed';
    const desc = c.description || '';
    const memberCount = c.member_count || 0;
    const isMember = c.is_member || false;

    const emojiPool = ['🌟','🔥','💡','🎯','🚀','🎨','📸','📚','🎮','🌿','🍜','💻'];
    let idx = 0;
    const seedStr = String(name);
    for (let i = 0; i < seedStr.length; i++) idx = (idx + seedStr.charCodeAt(i)) % emojiPool.length;
    const emoji = emojiPool[idx];
    const colors = ['#355872,#7AAACE','#7AAACE,#9CD5FF','#9CD5FF,#355872','#355872,#9CD5FF'];
    const col = colors[idx % colors.length];

    const card = document.createElement('div');
    card.className = 'comm-card';
    card.dataset.name = name;
    card.dataset.id = commId;
    card.innerHTML = `
        <div class="comm-card-thumb" style="background:linear-gradient(135deg,${col})">${emoji}</div>
        <div class="comm-card-body">
            <div class="comm-card-name">${escapeHtml(name)}</div>
            <div class="comm-card-desc">${escapeHtml(desc)}</div>
            <div class="comm-card-members"><i class="fas fa-users"></i> ${Number(memberCount).toLocaleString()} members</div>
        </div>
        <div class="comm-card-footer">
            <button class="comm-view-btn" onclick="event.stopPropagation();window.location.href='community.php?id=${commId}'">View</button>
            <button class="comm-like-btn ${isMember ? 'liked' : ''}" onclick="event.stopPropagation();toggleJoinCommunity(event, ${commId}, this)">
                <i class="${isMember ? 'fas' : 'far'} fa-heart"></i>
            </button>
        </div>`;
    return card;
}

/* ── LOAD COMMUNITIES ── */
async function loadCommunities() {
    const grid = document.getElementById('commGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    try {
        const res = await fetch('../../html/community.php', {
            method: 'GET',
            credentials: 'include'
        });
        
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await res.text();
            console.error('Server returned HTML:', text.substring(0, 500));
            throw new Error('Server error - check PHP logs');
        }
        
        const data = await res.json();
        console.log('API response:', data);

        if (!data || !data.success) {
            throw new Error(data?.error || 'Failed to load communities');
        }

        const communities = data.communities || [];
        grid.innerHTML = '';

        if (!communities.length) {
            grid.innerHTML = '<div class="empty-state"><i class="fas fa-users"></i><div>No communities yet. Create one!</div></div>';
            return;
        }

        window.allCommunities = communities;

        communities.forEach((c) => {
            grid.appendChild(createCommunityCard(c));
        });

        loadLikedCommunities();

    } catch (err) {
        console.error('loadCommunities error:', err);
        grid.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><div>${err.message}</div></div>`;
    }
}

/* ── LOAD LIKED COMMUNITIES ── */
function loadLikedCommunities() {
    const grid = document.getElementById('likedGrid');
    if (!grid) return;

    const all = window.allCommunities || [];
    const liked = all.filter(c => c.is_member === true || c.is_member === 1 || c.is_member === '1');

    grid.innerHTML = '';

    if (!liked.length) {
        grid.innerHTML = '<div class="empty-state"><i class="fas fa-heart"></i><div>No liked communities yet. Join some!</div></div>';
        return;
    }

    liked.forEach((c) => {
        grid.appendChild(createCommunityCard(c));
    });
}

/* ── JOIN / LEAVE ── */
/* ── JOIN / LEAVE ── */
async function toggleJoinCommunity(event, communityId, btn) {
    event.stopPropagation();
    const isJoined = btn.classList.contains('liked');
    const action = isJoined ? 'leave' : 'join';

    try {
        const form = new FormData();
        form.append('community_id', communityId);
        form.append('action', action);

        const res = await fetch('../api/users/communities/join-community.php', {
            method: 'POST',
            credentials: 'include',
            body: form
        });
        
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        if (data.deleted) {
            showToast('Community deleted (you were the only admin)');
            loadCommunities();
            return;
        }

        if (isJoined) {
            btn.classList.remove('liked');
            btn.innerHTML = '<i class="far fa-heart"></i>';
            showToast('Left community');
        } else {
            btn.classList.add('liked');
            btn.innerHTML = '<i class="fas fa-heart"></i>';
            showToast('Joined! 🎉');
        }

        // Refresh both tabs
        loadCommunities();

    } catch (err) {
        showToast(err.message, 'error');
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

