<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$postId = (int)(input()['post_id'] ?? 0);
$stmt = db()->prepare('UPDATE posts SET status = "deleted" WHERE id = ? AND user_id = ?');
$stmt->execute([$postId, $me['id']]);
json_response(['success' => $stmt->rowCount() > 0, 'message' => 'Post deleted']);

