<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';

$me = current_user(false);
$viewerId = (int)($me['id'] ?? 0);
$slug = clean_string($_GET['slug'] ?? '', 180);
$id = (int)($_GET['id'] ?? 0);

$sql = "SELECT p.*, u.name, u.username, up.profile_photo
  FROM posts p
  JOIN users u ON u.id = p.user_id
  LEFT JOIN user_profiles up ON up.user_id = u.id
  WHERE p.status = 'active' AND " . ($slug !== '' ? 'p.slug = ?' : 'p.id = ?') . " LIMIT 1";
$stmt = db()->prepare($sql);
$stmt->execute([$slug !== '' ? $slug : $id]);
$post = $stmt->fetch();
if (!$post) json_response(['success' => false, 'message' => 'Post not found'], 404);
if ($post['privacy'] !== 'public' && (int)$post['user_id'] !== $viewerId) {
    json_response(['success' => false, 'message' => 'Private post'], 403);
}

$related = db()->prepare("SELECT id, slug, post_text, created_at FROM posts WHERE status = 'active' AND privacy = 'public' AND id <> ? ORDER BY created_at DESC LIMIT 6");
$related->execute([$post['id']]);
json_response(['success' => true, 'post' => post_payload($post, $viewerId), 'related_posts' => $related->fetchAll(), 'comments_locked' => !$viewerId]);

