<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$data = input();
$postId = (int)($data['post_id'] ?? 0);
$reason = clean_string($data['reason'] ?? 'Reported by user', 255);
db()->prepare('INSERT INTO reports (reporter_id, post_id, reason) VALUES (?, ?, ?)')->execute([$me['id'], $postId, $reason]);
db()->prepare('UPDATE posts SET status = "reported" WHERE id = ?')->execute([$postId]);
json_response(['success' => true, 'message' => 'Report submitted']);

