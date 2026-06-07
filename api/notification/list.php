<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$stmt = db()->prepare('SELECT n.*, u.name actor_name, u.username actor_username, p.profile_photo actor_photo, post.slug post_slug
  FROM notifications n
  LEFT JOIN users u ON u.id = n.actor_id
  LEFT JOIN user_profiles p ON p.user_id = u.id
  LEFT JOIN posts post ON post.id = n.reference_id AND n.type IN ("post_like","post_comment")
  WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 80');
$stmt->execute([$me['id']]);
$count = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$count->execute([$me['id']]);
json_response(['success' => true, 'unread_count' => (int)$count->fetchColumn(), 'notifications' => $stmt->fetchAll()]);
