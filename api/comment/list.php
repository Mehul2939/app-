<?php
require_once __DIR__ . '/../helpers/auth.php';

$me = current_user(false);
$postId = (int)($_GET['post_id'] ?? 0);
$countStmt = db()->prepare('SELECT COUNT(*) FROM post_comments WHERE post_id = ?');
$countStmt->execute([$postId]);
$total = (int)$countStmt->fetchColumn();

if (!$me) {
    json_response(['success' => true, 'locked' => true, 'total' => $total, 'comments' => []]);
}

$stmt = db()->prepare("SELECT c.*, u.name, u.username, p.profile_photo,
  parent.user_id parent_user_id, parent_user.username parent_username, parent_user.name parent_name,
  (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) likes_count,
  (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = ?) liked_by_me
  FROM post_comments c
  JOIN users u ON u.id = c.user_id
  LEFT JOIN user_profiles p ON p.user_id = u.id
  LEFT JOIN post_comments parent ON parent.id = c.parent_comment_id
  LEFT JOIN users parent_user ON parent_user.id = parent.user_id
  WHERE c.post_id = ? AND c.status = 'active'
  ORDER BY c.id ASC LIMIT 80");
$stmt->execute([$me['id'], $postId]);
json_response(['success' => true, 'locked' => false, 'total' => $total, 'comments' => $stmt->fetchAll()]);
