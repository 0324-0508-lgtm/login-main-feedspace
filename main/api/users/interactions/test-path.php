<?php
echo '<h2>Checking paths...</h2>';
$paths = [
    '../api/users/posts/get-profile-posts.php',
    '../api/posts/get-profile-posts.php',
    '../../api/users/posts/get-profile-posts.php',
    '../../api/posts/get-profile-posts.php',
    '../../../api/users/posts/get-profile-posts.php',
    '/login-main-feedspace/main/api/users/posts/get-profile-posts.php',
];

foreach ($paths as $path) {
    $fullPath = realpath($path);
    echo $path . ': ' . ($fullPath ? 'EXISTS at ' . $fullPath : 'NOT FOUND') . '<br><br>';
}
?>