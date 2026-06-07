<?php
require_once __DIR__ . '/api_headers.php';
require_once __DIR__ . '/helpers/auth.php';

$me = current_user();
$data = input();
$commentId = (int)($data['comment_id'] ?? 0);
$text = clean_string($data['comment_text'] ?? '', 1000);
if ($commentId <= 0 || $text === '') {
    json_response(['success' => false, 'message' => 'Comment and text are required'], 422);
}
$stmt = db()->prepare("UPDATE post_comments SET comment_text = ? WHERE id = ? AND user_id = ? AND status = 'active'");
$stmt->execute([$text, $commentId, $me['id']]);
json_response(['success' => $stmt->rowCount() > 0, 'message' => $stmt->rowCount() ? 'Comment updated' : 'Comment not found']);

