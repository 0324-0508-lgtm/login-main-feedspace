<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId    = $_SESSION['user_id'];
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$bio       = trim($_POST['bio']        ?? '');
$college   = trim($_POST['college']    ?? '');
$avatarSeed = trim($_POST['avatar_seed'] ?? '');

if (empty($firstName) || empty($lastName)) {
    echo json_encode(['success' => false, 'error' => 'First and last name are required']);
    exit;
}

// Validate bio length
if (strlen($bio) > 500) {
    echo json_encode(['success' => false, 'error' => 'Bio must be 500 characters or less']);
    exit;
}

try {
    $conn->beginTransaction();

    $profilePicture = null;

    // Handle uploaded avatar file
    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../../../uploads/profile/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Use JPG, PNG, GIF or WEBP.']);
            exit;
        }

        $filename = $userId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
            $profilePicture = $filename;
        } else {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
            exit;
        }
    }

    // Build UPDATE query dynamically
    $fields = ['first_name = ?', 'last_name = ?', 'bio = ?', 'college = ?'];
    $params = [$firstName, $lastName, $bio, $college];

    if ($profilePicture) {
        $fields[] = 'profile_picture = ?';
        $params[] = $profilePicture;
    } elseif (!empty($avatarSeed)) {
        $fields[] = 'profile_picture = ?';
        $params[] = 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($avatarSeed);
    }

    $params[] = $userId;
    $sql  = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $conn->commit();

    echo json_encode([
        'success'  => true,
        'message'  => 'Profile updated successfully',
        'avatar'   => $profilePicture
            ? '../uploads/profile/' . $profilePicture
            : (!empty($avatarSeed) ? 'https://api.dicebear.com/7.x/adventurer/svg?seed=' . urlencode($avatarSeed) : null)
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}