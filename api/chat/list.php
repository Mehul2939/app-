<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$stmt = db()->prepare("SELECT u.id, u.public_user_id, u.name, u.username, p.profile_photo, u.last_active, u.last_seen,
  IF(COALESCE(u.last_seen, u.last_active) >= (NOW() - INTERVAL 60 SECOND), 1, 0) is_online,
  lm.message_text, lm.media_type, lm.created_at last_message_at,
  (SELECT COUNT(*) FROM messages unread WHERE unread.sender_id = u.id AND unread.receiver_id = ? AND unread.seen_at IS NULL) unread_count
  FROM friends f
  JOIN users u ON u.id = f.friend_id
  LEFT JOIN user_profiles p ON p.user_id = u.id
  LEFT JOIN messages lm ON lm.id = (
    SELECT id FROM messages m
    WHERE (m.sender_id = ? AND m.receiver_id = u.id) OR (m.sender_id = u.id AND m.receiver_id = ?)
    ORDER BY m.id DESC LIMIT 1
  )
  WHERE f.user_id = ?
  ORDER BY COALESCE(lm.created_at, f.created_at) DESC");
$stmt->execute([$me['id'], $me['id'], $me['id'], $me['id']]);
json_response(['success' => true, 'chats' => $stmt->fetchAll()]);
