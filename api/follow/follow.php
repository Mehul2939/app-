<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user();
$target = (int)(input()['user_id'] ?? 0);
if ($target <= 0 || $target === (int)$me['id']) json_response(['success' => false, 'message' => 'Invalid user'], 422);
if (blocked_between((int)$me['id'], $target)) json_response(['success' => false, 'message' => 'Blocked user'], 403);
$p = db()->prepare('SELECT account_type FROM user_profiles WHERE user_id = ?');
$p->execute([$target]);
$profile = $p->fetch();
if (($profile['account_type'] ?? 'public') === 'private') {
    db()->prepare('INSERT INTO follow_requests (requester_id, receiver_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = "pending"')->execute([$me['id'], $target]);
    notify_user($target, (int)$me['id'], 'follow_request', $me['name'] . ' requested to follow you');
    json_response(['success' => true, 'requested' => true, 'message' => 'Follow request sent']);
}
db()->prepare('INSERT IGNORE INTO followers (follower_id, following_id) VALUES (?, ?)')->execute([$me['id'], $target]);
notify_user($target, (int)$me['id'], 'new_follower', $me['name'] . ' started following you.');
json_response(['success' => true, 'following' => true]);
