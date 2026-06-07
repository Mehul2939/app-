<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';

$me = current_user();
$data = input();
$target = (int)($data['user_id'] ?? 0);
$action = clean_string($data['action'] ?? 'send', 20);
if ($target <= 0 || $target === (int)$me['id']) {
    json_response(['success' => false, 'message' => 'Invalid user'], 422);
}
if (blocked_between((int)$me['id'], $target)) {
    json_response(['success' => false, 'message' => 'You have been blocked.'], 403);
}

if ($action === 'send') {
    if (are_friends((int)$me['id'], $target)) json_response(['success' => true, 'status' => 'friends']);
    db()->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = IF(status IN ('rejected','cancelled'), 'pending', status)")
        ->execute([$me['id'], $target]);
    notify_user($target, (int)$me['id'], 'friend_request', $me['name'] . ' sent you a friend request.');
    json_response(['success' => true, 'status' => 'request_sent', 'message' => 'Friend request sent']);
}

if ($action === 'cancel') {
    db()->prepare("UPDATE friend_requests SET status = 'cancelled' WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'")->execute([$me['id'], $target]);
    json_response(['success' => true, 'status' => 'not_friends']);
}

if ($action === 'accept') {
    $stmt = db()->prepare("SELECT id FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$target, $me['id']]);
    if (!$stmt->fetch()) json_response(['success' => false, 'message' => 'Request not found'], 404);
    db()->beginTransaction();
    db()->prepare("UPDATE friend_requests SET status = 'accepted' WHERE sender_id = ? AND receiver_id = ?")->execute([$target, $me['id']]);
    db()->prepare('INSERT IGNORE INTO friends (user_id, friend_id) VALUES (?, ?), (?, ?)')->execute([$me['id'], $target, $target, $me['id']]);
    notify_user($target, (int)$me['id'], 'friend_accept', $me['name'] . ' accepted your friend request.');
    db()->commit();
    json_response(['success' => true, 'status' => 'friends', 'message' => 'Friend request accepted']);
}

if ($action === 'reject') {
    db()->prepare("UPDATE friend_requests SET status = 'rejected' WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'")->execute([$target, $me['id']]);
    json_response(['success' => true, 'status' => 'not_friends']);
}

if ($action === 'remove') {
    db()->prepare('DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)')->execute([$me['id'], $target, $target, $me['id']]);
    json_response(['success' => true, 'status' => 'not_friends']);
}

json_response(['success' => false, 'message' => 'Unknown action'], 422);

