<?php
require_once __DIR__ . '/../helpers/auth.php';

$me = current_user();
$commentId = (int)(input()['comment_id'] ?? 0);
if ($commentId <= 0) {
    json_response(['success' => false, 'message' => 'Comment required'], 422);
}

$check = db()->prepare('SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ? LIMIT 1');
$check->execute([$commentId, $me['id']]);
if ($row = $check->fetch()) {
    db()->prepare('DELETE FROM comment_likes WHERE id = ?')->execute([$row['id']]);
    json_response(['success' => true, 'liked' => false]);
}

db()->prepare('INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)')->execute([$commentId, $me['id']]);
json_response(['success' => true, 'liked' => true]);

