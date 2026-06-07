<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user(false);
$viewerId = (int)($me['id'] ?? 0);
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = min(20, max(5, (int)($_GET['limit'] ?? 10)));
$username = clean_string($_GET['username'] ?? '', 60);
$search = '%' . clean_string($_GET['q'] ?? '', 100) . '%';
$userFilterSql = '';
$params = [$viewerId, $viewerId];
if ($username !== '') {
    $userFilterSql = ' AND u.username = ?';
    $params[] = $username;
}
if (trim($search, '%') !== '') {
    $userFilterSql .= ' AND p.post_text LIKE ?';
    $params[] = $search;
}
$stmt = db()->prepare("SELECT p.*, u.name, u.username, up.profile_photo
  FROM posts p
  JOIN users u ON u.id = p.user_id
  LEFT JOIN user_profiles up ON up.user_id = u.id
  WHERE p.status = 'active' AND (
    p.privacy = 'public' OR p.user_id = ? OR EXISTS (SELECT 1 FROM followers f WHERE f.follower_id = ? AND f.following_id = p.user_id)
  ) $userFilterSql
  ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$posts = array_map(fn($p) => post_payload($p, $viewerId), $stmt->fetchAll());
json_response(['success' => true, 'posts' => $posts]);
