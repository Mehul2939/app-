<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$stmt = db()->prepare('SELECT u.id, u.name, u.username, p.profile_photo, p.bio FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id <> ? AND u.status = "active" AND NOT EXISTS (SELECT 1 FROM followers f WHERE f.follower_id = ? AND f.following_id = u.id) ORDER BY u.id DESC LIMIT 30');
$stmt->execute([$me['id'], $me['id']]);
json_response(['success' => true, 'users' => $stmt->fetchAll()]);

