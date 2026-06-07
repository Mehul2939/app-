<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/seo.php';
$me = current_user();
$data = input();
$postId = (int)($data['post_id'] ?? 0);
$text = clean_string($data['post_text'] ?? '', 2000);
$slug = unique_post_slug($text, $postId);
$stmt = db()->prepare('UPDATE posts SET post_text = ?, slug = ?, meta_title = ?, meta_description = ? WHERE id = ? AND user_id = ? AND status = "active"');
$stmt->execute([$text, $slug, excerpt_text($text, 70), excerpt_text($text, 155), $postId, $me['id']]);
json_response(['success' => $stmt->rowCount() > 0, 'message' => $stmt->rowCount() ? 'Post updated' : 'Post not found']);
