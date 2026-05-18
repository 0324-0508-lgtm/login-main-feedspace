<?php
// simple-post-test.php - Manual test for creating posts
// Access this at: http://localhost/login-main-feedspace/simple-post-test.php

session_start();

echo "
<html>
<head>
    <title>FeedSpace - Post Creation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; padding: 10px; background: #eee; }
        .error { color: red; padding: 10px; background: #fdd; }
        .info { color: blue; padding: 10px; background: #ddf; }
        input, textarea { width: 100%; padding: 10px; margin: 10px 0; }
        button { padding: 10px 20px; background: #333; color: white; cursor: pointer; }
    </style>
</head>
<body>
    <h1>FeedSpace Post Creation Test</h1>
";

// Check if logged in
if (empty($_SESSION['user_id'])) {
    echo "<div class='error'>❌ Not logged in! You need to be logged in first.</div>";
    echo "<p><a href='index.html'>Go to login page</a></p>";
    exit;
}

echo "<div class='info'>✅ Logged in as: " . htmlspecialchars($_SESSION['user_id']) . "</div>";

// Check database connection
include 'config/db.php';
if (!$conn) {
    echo "<div class='error'>❌ Database connection failed: " . mysqli_connect_error() . "</div>";
    exit;
}

echo "<div class='success'>✅ Database connected</div>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['test_content'])) {
    $content = trim($_POST['test_content']);
    $user_id = $_SESSION['user_id'];
    
    // Insert test post directly
    $stmt = $conn->prepare(
        "INSERT INTO posts (user_id, content, file_url, file_type, visibility, status, ai_status, ai_score, ai_reason, created_at, updated_at) 
         VALUES (?, ?, NULL, 'none', 'public', 'pending', 'safe', 0, NULL, NOW(), NOW())"
    );
    
    if (!$stmt) {
        echo "<div class='error'>❌ Prepare Error: " . $conn->error . "</div>";
    } else {
        $stmt->bind_param("ss", $user_id, $content);
        
        if ($stmt->execute()) {
            $post_id = $conn->insert_id;
            echo "<div class='success'>
                ✅ Post Created Successfully!<br>
                Post ID: $post_id<br>
                Content: " . htmlspecialchars($content) . "<br>
                User: " . htmlspecialchars($user_id) . "
            </div>";
            
            // Verify it was saved
            $verify = $conn->prepare("SELECT post_id, content FROM posts WHERE post_id = ?");
            $verify->bind_param("i", $post_id);
            $verify->execute();
            $result = $verify->get_result()->fetch_assoc();
            
            if ($result) {
                echo "<div class='success'>✅ Verified: Post found in database</div>";
            } else {
                echo "<div class='error'>❌ Error: Post not found in database after creation!</div>";
            }
        } else {
            echo "<div class='error'>❌ Execute Error: " . $stmt->error . "</div>";
        }
    }
}

echo "
    <h2>Create a Test Post</h2>
    <form method='POST'>
        <textarea name='test_content' placeholder='Write your test post...' rows='5' required></textarea>
        <button type='submit'>Create Post</button>
    </form>
    
    <h2>Recent Posts</h2>
    <p>Your recent posts:</p>
";

// Show recent posts
$recent = $conn->prepare("SELECT post_id, content, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$user_id = $_SESSION['user_id'];
$recent->bind_param("s", $user_id);
$recent->execute();
$posts = $recent->get_result();

if ($posts->num_rows > 0) {
    echo "<table border='1' cellpadding='10' width='100%'>";
    echo "<tr><th>ID</th><th>Content</th><th>Created</th></tr>";
    
    while ($post = $posts->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $post['post_id'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($post['content'], 0, 50)) . "...</td>";
        echo "<td>" . $post['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No posts found. Create one using the form above.</p>";
}

echo "
    <h2>Debugging Info</h2>
    <p>
        <strong>User ID:</strong> " . htmlspecialchars($_SESSION['user_id']) . "<br>
        <strong>Server:</strong> " . $_SERVER['SERVER_NAME'] . "<br>
        <strong>PHP Version:</strong> " . phpversion() . "<br>
    </p>
    
    <h2>Next Steps</h2>
    <ol>
        <li>If you can create posts here, the database is working ✅</li>
        <li>If it fails, check the error message above</li>
        <li>If it works, try using the main feed to create posts</li>
        <li>If main feed still doesn't work, open browser console (F12) and check for errors</li>
    </ol>
    
    <a href='main/html/main-feed.html'>Go to Main Feed</a>
</body>
</html>
";
?>
