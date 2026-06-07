<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user();
$postId = (int)(input()['post_id'] ?? 0);
$check = db()->prepare('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?');
$check->execute([$postId, $me['id']]);
if ($row = $check->fetch()) {
    db()->prepare('DELETE FROM post_likes WHERE id = ?')->execute([$row['id']]);
    json_response(['success' => true, 'liked' => false]);
}
db()->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)')->execute([$postId, $me['id']]);
$owner = db()->prepare('SELECT user_id FROM posts WHERE id = ?');
$owner->execute([$postId]);
if ($p = $owner->fetch()) notify_user((int)$p['user_id'], (int)$me['id'], 'post_like', $me['name'] . ' liked your post', $postId);
json_response(['success' => true, 'liked' => true]);

