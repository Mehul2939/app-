<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();

if (empty($_FILES['photo']['tmp_name'])) {
    json_response(['success' => false, 'message' => 'Photo required'], 422);
}
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$type = $_FILES['photo']['type'] ?? '';
$size = (int)($_FILES['photo']['size'] ?? 0);
if (!isset($allowed[$type]) || $size > 3145728) {
    json_response(['success' => false, 'message' => 'Only JPG, PNG, WEBP up to 3MB allowed'], 422);
}
$path = 'uploads/profiles/user_' . $me['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$type];
if (!move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../../' . $path)) {
    json_response(['success' => false, 'message' => 'Upload failed'], 500);
}
db()->prepare('UPDATE user_profiles SET profile_photo = ? WHERE user_id = ?')->execute([$path, $me['id']]);
db()->prepare('UPDATE users SET profile_photo = ? WHERE id = ?')->execute([$path, $me['id']]);
json_response(['success' => true, 'message' => 'Profile photo updated', 'profile_photo' => $path]);
