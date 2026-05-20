<?php
session_start();

require __DIR__ . '/../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$community_id = intval($_GET['id'] ?? 0);
if (!$community_id) {
    header('Location: communities.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch community
$stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name, u.profile_picture as creator_pic 
    FROM communities c 
    LEFT JOIN users u ON c.created_by = u.user_id 
    WHERE c.community_id = ? AND c.is_active = 1");
$stmt->execute([$community_id]);
$community = $stmt->fetch(PDO::FETCH_ASSOC);

// ========== NO REDIRECT ON ERROR ==========
if (!$community) {
    // Show error inline — NO header('Location: ...')
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Community Not Found</title>
        <style>
            body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f5f7fa; }
            .box { text-align: center; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            h1 { color: #355872; margin-bottom: 10px; }
            p { color: #65676b; margin-bottom: 20px; }
            a { display: inline-block; background: #355872; color: white; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>🔍 Community Not Found</h1>
            <p>This community doesn't exist or has been removed.</p>
            <a href="community-detail.php">← Back to Communities</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
// ========== END ERROR HANDLING ==========

$creator_name = trim(($community['first_name'] ?? '') . ' ' . ($community['last_name'] ?? ''));
if (!$creator_name) $creator_name = 'Unknown';

// Check membership
$memberStmt = $db->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
$memberStmt->execute([$community_id, $user_id]);
$myRole = $memberStmt->fetchColumn();
$isMember = (bool)$myRole;
$isAdmin = ($myRole === 'admin');

// Get stats
$countStmt = $db->prepare("SELECT COUNT(*) FROM community_members WHERE community_id = ?");
$countStmt->execute([$community_id]);
$memberCount = $countStmt->fetchColumn();

$postCountStmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE community_id = ? AND is_deleted = 0");
$postCountStmt->execute([$community_id]);
$postCount = $postCountStmt->fetchColumn();

// Get members
$membersStmt = $db->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.profile_picture, cm.role, cm.joined_at
    FROM community_members cm
    JOIN users u ON cm.user_id = u.user_id
    WHERE cm.community_id = ?
    ORDER BY CASE WHEN cm.role = 'admin' THEN 0 ELSE 1 END, cm.joined_at DESC
    LIMIT 30
");
$membersStmt->execute([$community_id]);
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get posts with likes/comments
$postsStmt = $db->prepare("
    SELECT p.*, u.first_name, u.last_name, u.profile_picture,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND moderation_status IN ('approved','flagged')) as comment_count,
        EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.community_id = ? AND p.is_deleted = 0 AND p.status = 'approved'
    ORDER BY p.is_announcement DESC, p.created_at DESC
    LIMIT 20
");
$postsStmt->execute([$user_id, $community_id]);
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user data
$userStmt = $db->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = ?");
$userStmt->execute([$user_id]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
$currentUserPic = !empty($currentUser['profile_picture']) ? '../../uploads/profiles/' . $currentUser['profile_picture'] : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($currentUser['first_name'] ?? 'User');

// Generate community color
$colors = ['#355872,#7AAACE','#7AAACE,#9CD5FF','#9CD5FF,#355872','#355872,#9CD5FF','#4a7c59,#8fbc8f','#8b4513,#d2691e'];
$colorIdx = array_sum(array_map('ord', str_split($community['name'] ?? ''))) % count($colors);
$communityGradient = $colors[$colorIdx];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($community['name']); ?> - FeedSpace</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --color-dark: #355872; --color-mid: #7AAACE; --color-light: #9CD5FF;
            --color-bg: #f5f7fa; --color-white: #ffffff; --color-border: #e4e6eb;
            --color-text: #1a1a2e; --color-subtext: #65676b;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08); --shadow-md: 0 4px 12px rgba(0,0,0,0.12);
            --radius-md: 16px; --radius-full: 999px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', sans-serif; background: var(--color-bg); color: var(--color-text); }
        .navbar { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: var(--color-white); border-bottom: 2px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 100; }
        .nav-logo a { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--color-dark); text-decoration: none; }
        .nav-back { display: flex; align-items: center; gap: 8px; color: var(--color-dark); text-decoration: none; font-weight: 700; font-size: 0.9rem; }
        .page-container { max-width: 900px; margin: 80px auto 40px; padding: 0 20px; }
        .community-hero { background: linear-gradient(135deg, <?php echo $communityGradient; ?>); border-radius: var(--radius-md); padding: 40px 32px; color: white; margin-bottom: 24px; position: relative; overflow: hidden; }
        .hero-icon { width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 16px; }
        .hero-title { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 8px; }
        .hero-desc { font-size: 1rem; opacity: 0.9; margin-bottom: 20px; max-width: 600px; }
        .hero-stats { display: flex; gap: 24px; margin-bottom: 20px; }
        .hero-stat { display: flex; align-items: center; gap: 6px; font-size: 0.9rem; font-weight: 700; }
        .btn-join { background: white; color: var(--color-dark); border: none; padding: 10px 28px; border-radius: var(--radius-full); font-family: inherit; font-weight: 800; font-size: 0.9rem; cursor: pointer; }
        .btn-join.joined { background: rgba(255,255,255,0.25); color: white; }
        .btn-admin { background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.4); padding: 8px 20px; border-radius: var(--radius-full); font-weight: 700; font-size: 0.85rem; }
        .tab-nav { display: flex; gap: 4px; margin-bottom: 24px; background: var(--color-white); border: 2px solid var(--color-border); border-radius: var(--radius-full); padding: 4px; width: fit-content; }
        .tab-btn { background: transparent; border: none; border-radius: var(--radius-full); padding: 8px 24px; font-family: inherit; font-weight: 700; font-size: 0.85rem; color: var(--color-subtext); cursor: pointer; }
        .tab-btn.active { background: var(--color-dark); color: white; }
        .create-post { background: var(--color-white); border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 16px 20px; margin-bottom: 20px; display: flex; gap: 12px; align-items: center; }
        .create-post img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .create-post-input { flex: 1; border: none; background: var(--color-bg); border-radius: var(--radius-full); padding: 12px 20px; font-family: inherit; font-size: 0.95rem; outline: none; cursor: pointer; }
        .post-card { background: var(--color-white); border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; }
        .post-header { display: flex; gap: 12px; margin-bottom: 12px; }
        .post-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
        .post-author { font-weight: 800; font-size: 0.95rem; }
        .post-time { font-size: 0.8rem; color: var(--color-subtext); }
        .post-body { font-size: 0.95rem; line-height: 1.6; margin-bottom: 12px; word-break: break-word; }
        .post-image { width: 100%; border-radius: 12px; margin: 12px 0; max-height: 400px; object-fit: cover; }
        .post-actions { display: flex; gap: 16px; padding-top: 12px; border-top: 1px solid var(--color-border); }
        .post-action { background: none; border: none; display: flex; align-items: center; gap: 6px; font-family: inherit; font-size: 0.85rem; font-weight: 700; color: var(--color-subtext); cursor: pointer; padding: 6px 12px; border-radius: 8px; }
        .post-action.liked { color: #e05263; }
        .members-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
        .member-card { background: var(--color-white); border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; display: flex; align-items: center; gap: 12px; }
        .member-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
        .member-name { font-weight: 800; font-size: 0.9rem; }
        .member-role { display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 2px 10px; border-radius: var(--radius-full); margin-top: 4px; }
        .member-role.admin { background: #fff3cd; color: #856404; }
        .member-role.member { background: var(--color-bg); color: var(--color-subtext); }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--color-subtext); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-logo"><a href="feed-view.php">Community - FeedSpace</a></div>
    <a href="community-detail.php" class="nav-back"><i class="fas fa-arrow-left"></i> Back</a>
</nav>

<div class="page-container">
    <div class="community-hero">
        <div class="hero-icon">🌐</div>
        <h1 class="hero-title"><?php echo htmlspecialchars($community['name']); ?></h1>
        <p class="hero-desc"><?php echo htmlspecialchars($community['description'] ?? 'No description'); ?></p>
        <div class="hero-stats">
            <div class="hero-stat"><i class="fas fa-users"></i> <?php echo number_format($memberCount); ?> members</div>
            <div class="hero-stat"><i class="fas fa-file-alt"></i> <?php echo number_format($postCount); ?> posts</div>
            <div class="hero-stat"><i class="fas fa-user"></i> by <?php echo htmlspecialchars($creator_name); ?></div>
        </div>
        <div class="hero-actions">
            <?php if ($isAdmin): ?>
                <span class="btn-admin"><i class="fas fa-crown"></i> You are Admin</span>
            <?php else: ?>
                <button class="btn-join <?php echo $isMember ? 'joined' : ''; ?>" onclick="toggleJoin(<?php echo $community_id; ?>, this)">
                    <?php echo $isMember ? '✓ Joined' : '+ Join Community'; ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('posts')"><i class="fas fa-file-alt"></i> Posts</button>
        <button class="tab-btn" onclick="switchTab('members')"><i class="fas fa-users"></i> Members</button>
    </div>

    <div id="posts-panel" class="tab-panel active">
        <?php if ($isMember): ?>
        <div class="create-post" onclick="openPostModal()">
            <img src="<?php echo htmlspecialchars($currentUserPic); ?>" alt="You">
            <input type="text" class="create-post-input" placeholder="Write something...">
        </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:16px;"></i>
            <h3>No posts yet</h3>
            <p>Be the first to share something!</p>
        </div>
        <?php else: ?>
            <?php foreach ($posts as $post): 
                $postPic = !empty($post['profile_picture']) ? '../../uploads/profiles/' . $post['profile_picture'] : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($post['first_name'] ?? 'User');
                $postImage = null;
                if (!empty($post['file_url'])) {
                    $postImage = preg_match('#^https?://#i', $post['file_url']) ? $post['file_url'] : '../../uploads/posts/' . $post['file_url'];
                }
            ?>
            <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
                <div class="post-header">
                    <img src="<?php echo htmlspecialchars($postPic); ?>" class="post-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
                    <div>
                        <div class="post-author"><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></div>
                        <div class="post-time"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></div>
                    </div>
                </div>
                <div class="post-body"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                <?php if ($postImage): ?>
                    <img src="<?php echo htmlspecialchars($postImage); ?>" class="post-image" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="post-actions">
                    <button class="post-action <?php echo $post['user_liked'] ? 'liked' : ''; ?>">
                        <i class="<?php echo $post['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                        <span><?php echo number_format($post['like_count']); ?></span>
                    </button>
                    <button class="post-action">
                        <i class="far fa-comment"></i>
                        <span><?php echo number_format($post['comment_count']); ?></span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="members-panel" class="tab-panel">
        <?php if (empty($members)): ?>
        <div class="empty-state">
            <i class="fas fa-users" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:16px;"></i>
            <h3>No members yet</h3>
        </div>
        <?php else: ?>
        <div class="members-grid">
            <?php foreach ($members as $m): 
                $mPic = !empty($m['profile_picture']) ? '../../uploads/profiles/' . $m['profile_picture'] : 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($m['first_name'] ?? 'User');
            ?>
            <div class="member-card">
                <img src="<?php echo htmlspecialchars($mPic); ?>" class="member-avatar" onerror="this.src='https://api.dicebear.com/7.x/adventurer/svg?seed=Default'">
                <div>
                    <div class="member-name"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></div>
                    <span class="member-role <?php echo $m['role']; ?>"><?php echo ucfirst($m['role']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(tab + '-panel').classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    event.target.closest('.tab-btn').classList.add('active');
}

async function toggleJoin(communityId, btn) {
    const isJoined = btn.classList.contains('joined');
    const action = isJoined ? 'leave' : 'join';
    const form = new FormData();
    form.append('community_id', communityId);
    form.append('action', action);
    
    try {
        const res = await fetch('../api/users/communities/join-community.php', {
            method: 'POST',
            credentials: 'include',
            body: form
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        location.reload();
    } catch (err) {
        alert(err.message);
    }
}
</script>

</body>
</html>