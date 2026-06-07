<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user();
$q = '%' . clean_string($_GET['q'] ?? '', 80) . '%';
$stmt = db()->prepare("SELECT u.id, u.public_user_id, u.name, u.username, p.profile_photo, p.bio, p.city,
  EXISTS(SELECT 1 FROM followers f WHERE f.follower_id = ? AND f.following_id = u.id) is_following
  FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
  WHERE u.status = 'active' AND (u.name LIKE ? OR u.username LIKE ? OR u.public_user_id LIKE ?)
  ORDER BY u.name LIMIT 30");
$stmt->execute([$me['id'], $q, $q, $q]);
$users = $stmt->fetchAll();
foreach ($users as &$user) {
    $user['friend_status'] = friend_status((int)$me['id'], (int)$user['id']);
}
json_response(['success' => true, 'users' => $users]);
