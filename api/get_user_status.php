<?php
require_once __DIR__ . '/api_headers.php';
require_once __DIR__ . '/helpers/auth.php';

current_user(false);
$userId = (int)($_GET['user_id'] ?? 0);
$username = clean_string($_GET['username'] ?? '', 60);
$sql = 'SELECT id, username, is_online, last_seen, last_active FROM users WHERE ' . ($username !== '' ? 'username = ?' : 'id = ?') . ' LIMIT 1';
$stmt = db()->prepare($sql);
$stmt->execute([$username !== '' ? $username : $userId]);
$user = $stmt->fetch();
if (!$user) json_response(['success' => false, 'message' => 'User not found'], 404);

$lastSeen = $user['last_seen'] ?: $user['last_active'];
$online = false;
if ($lastSeen) {
    $online = (time() - strtotime($lastSeen)) <= 60;
}
if (!$online && (int)$user['is_online'] === 1) {
    db()->prepare('UPDATE users SET is_online = 0 WHERE id = ?')->execute([$user['id']]);
}
json_response(['success' => true, 'user_id' => (int)$user['id'], 'username' => $user['username'], 'is_online' => $online, 'last_seen' => $lastSeen]);

