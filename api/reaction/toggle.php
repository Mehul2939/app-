<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';

$me = current_user();
$data = input();
$postId = (int)($data['post_id'] ?? 0);
$reaction = clean_string($data['reaction_type'] ?? 'like', 20);
$allowed = ['like','love','laugh','sad','angry'];
if (!in_array($reaction, $allowed, true)) {
    json_response(['success' => false, 'message' => 'Invalid reaction'], 422);
}

$existing = db()->prepare('SELECT id, reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ? LIMIT 1');
$existing->execute([$postId, $me['id']]);
$row = $existing->fetch();
if ($row && $row['reaction_type'] === $reaction) {
    db()->prepare('DELETE FROM post_reactions WHERE id = ?')->execute([$row['id']]);
    json_response(['success' => true, 'reaction' => null]);
}
if ($row) {
    db()->prepare('UPDATE post_reactions SET reaction_type = ? WHERE id = ?')->execute([$reaction, $row['id']]);
} else {
    db()->prepare('INSERT INTO post_reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)')->execute([$postId, $me['id'], $reaction]);
}
$owner = db()->prepare('SELECT user_id FROM posts WHERE id = ?');
$owner->execute([$postId]);
if ($p = $owner->fetch()) notify_user((int)$p['user_id'], (int)$me['id'], 'post_like', $me['name'] . ' reacted to your post', $postId);
json_response(['success' => true, 'reaction' => $reaction]);

