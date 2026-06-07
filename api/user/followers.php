<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$type = ($_GET['type'] ?? 'followers') === 'following' ? 'following' : 'followers';
$userId = (int)($_GET['user_id'] ?? $me['id']);
if ($type === 'following') {
    $stmt = db()->prepare('SELECT u.id, u.name, u.username, p.profile_photo FROM followers f JOIN users u ON u.id = f.following_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE f.follower_id = ? ORDER BY f.id DESC');
} else {
    $stmt = db()->prepare('SELECT u.id, u.name, u.username, p.profile_photo FROM followers f JOIN users u ON u.id = f.follower_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE f.following_id = ? ORDER BY f.id DESC');
}
$stmt->execute([$userId]);
json_response(['success' => true, 'users' => $stmt->fetchAll()]);

