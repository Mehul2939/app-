<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$data = input();
$target = (int)($data['user_id'] ?? 0);
$action = $data['action'] ?? 'block';
if ($target <= 0 || $target === (int)$me['id']) json_response(['success' => false, 'message' => 'Invalid user'], 422);
if ($action === 'unblock') {
    db()->prepare('DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?')->execute([$me['id'], $target]);
    json_response(['success' => true, 'blocked' => false]);
}
db()->prepare('INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)')->execute([$me['id'], $target]);
json_response(['success' => true, 'blocked' => true]);

