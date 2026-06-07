<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$id = (int)(input()['comment_id'] ?? 0);
$stmt = db()->prepare('DELETE FROM post_comments WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $me['id']]);
json_response(['success' => $stmt->rowCount() > 0]);

