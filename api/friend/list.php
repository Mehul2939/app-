<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user();
$type = clean_string($_GET['type'] ?? 'friends', 20);

if ($type === 'requests') {
    $stmt = db()->prepare("SELECT fr.*, u.id user_id, u.public_user_id, u.name, u.username, p.profile_photo
      FROM friend_requests fr JOIN users u ON u.id = fr.sender_id LEFT JOIN user_profiles p ON p.user_id = u.id
      WHERE fr.receiver_id = ? AND fr.status = 'pending' ORDER BY fr.id DESC");
    $stmt->execute([$me['id']]);
    json_response(['success' => true, 'requests' => $stmt->fetchAll()]);
}

$userId = (int)($_GET['user_id'] ?? $me['id']);
$stmt = db()->prepare("SELECT u.id, u.public_user_id, u.name, u.username, p.profile_photo, p.bio, p.city
  FROM friends f JOIN users u ON u.id = f.friend_id LEFT JOIN user_profiles p ON p.user_id = u.id
  WHERE f.user_id = ? ORDER BY u.name");
$stmt->execute([$userId]);
$users = $stmt->fetchAll();
foreach ($users as &$user) {
    $user['friend_status'] = friend_status((int)$me['id'], (int)$user['id']);
}
json_response(['success' => true, 'friends' => $users]);

