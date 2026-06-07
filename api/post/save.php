<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$postId = (int)(input()['post_id'] ?? 0);
$check = db()->prepare('SELECT id FROM saved_posts WHERE post_id = ? AND user_id = ?');
$check->execute([$postId, $me['id']]);
if ($row = $check->fetch()) {
    db()->prepare('DELETE FROM saved_posts WHERE id = ?')->execute([$row['id']]);
    json_response(['success' => true, 'saved' => false]);
}
db()->prepare('INSERT INTO saved_posts (post_id, user_id) VALUES (?, ?)')->execute([$postId, $me['id']]);
json_response(['success' => true, 'saved' => true]);

