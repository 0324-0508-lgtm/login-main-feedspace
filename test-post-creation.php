<?php
// test-post-creation.php - Diagnostic tool to test post creation

session_start();

echo "=== FeedSpace Post Creation Diagnostic ===\n\n";

// 1. Check session
echo "1. Session Status:\n";
if (isset($_SESSION['user_id'])) {
    echo "   ✅ User ID: " . $_SESSION['user_id'] . "\n";
} else {
    echo "   ❌ Not logged in! Please log in first.\n";
    exit;
}

// 2. Test database connection
echo "\n2. Database Connection:\n";
include 'config/db.php';

if ($conn && mysqli_connect_error() === null) {
    echo "   ✅ Connected to db_feedspace\n";
    
    // Check if posts table exists
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'posts'");
    if (mysqli_num_rows($result) > 0) {
        echo "   ✅ Posts table exists\n";
    } else {
        echo "   ❌ Posts table not found!\n";
    }
} else {
    echo "   ❌ Database connection failed: " . mysqli_connect_error() . "\n";
    exit;
}

// 3. Test file permissions
echo "\n3. File Permissions:\n";
$upload_dir = 'uploads/posts/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
if (is_writable($upload_dir)) {
    echo "   ✅ uploads/posts/ is writable\n";
} else {
    echo "   ❌ uploads/posts/ is not writable - posts with images will fail\n";
}

// 4. Test ban check
echo "\n4. Ban Status:\n";
include 'config/ban-check.php';
if (!isUserBanned($_SESSION['user_id'], $conn)) {
    echo "   ✅ User is not banned\n";
} else {
    echo "   ❌ User is banned!\n";
}

// 5. Test creating a post
echo "\n5. Test Post Creation:\n";
$test_content = "Test post at " . date('Y-m-d H:i:s');
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "INSERT INTO posts (user_id, content, file_url, file_type, visibility, status, ai_status, ai_score, ai_reason, created_at, updated_at) 
     VALUES (?, ?, ?, ?, 'public', 'pending', 'safe', 0, NULL, NOW(), NOW())"
);

if (!$stmt) {
    echo "   ❌ Prepare failed: " . $conn->error . "\n";
} else {
    $stmt->bind_param("ssss", $user_id, $test_content, $null_file, $null_type);
    $null_file = null;
    $null_type = 'none';
    
    if ($stmt->execute()) {
        $post_id = $conn->insert_id;
        echo "   ✅ Test post created! Post ID: " . $post_id . "\n";
        echo "   Content: " . htmlspecialchars($test_content) . "\n";
        
        // Try to fetch it back
        $fetch_stmt = $conn->prepare("SELECT post_id, content FROM posts WHERE post_id = ?");
        $fetch_stmt->bind_param("i", $post_id);
        $fetch_stmt->execute();
        $result = $fetch_stmt->get_result()->fetch_assoc();
        
        if ($result) {
            echo "   ✅ Post retrieved from database\n";
        } else {
            echo "   ❌ Post not found in database!\n";
        }
    } else {
        echo "   ❌ Execute failed: " . $stmt->error . "\n";
    }
}

// 6. Test Python availability (optional)
echo "\n6. AI Moderation (Optional):\n";
$python_script = 'main/api/hate-speech-detection/toxic_detector.py';
if (file_exists($python_script)) {
    echo "   ✅ toxic_detector.py found\n";
    echo "   Note: Full AI testing requires Python 3 installed\n";
} else {
    echo "   ℹ️  toxic_detector.py not found - posts will work but without AI moderation\n";
}

echo "\n=== Diagnostic Complete ===\n";
echo "\nNext steps:\n";
echo "1. If all tests pass: Try creating a post from the web interface\n";
echo "2. If tests fail: Check the ❌ items above\n";
echo "3. If you're still having issues, check browser console (F12) for API errors\n";
?>
