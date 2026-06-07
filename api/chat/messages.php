<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$with = (int)($_GET['user_id'] ?? 0);
db()->prepare('UPDATE messages SET seen_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND seen_at IS NULL')->execute([$with, $me['id']]);
$peerStmt = db()->prepare('SELECT u.id, u.name, u.username, p.profile_photo, u.last_active, u.last_seen, IF(COALESCE(u.last_seen, u.last_active) >= (NOW() - INTERVAL 60 SECOND), 1, 0) is_online FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ? LIMIT 1');
$peerStmt->execute([$with]);
$stmt = db()->prepare("SELECT m.*, s.name sender_name, s.username sender_username, p.profile_photo sender_photo,
  reply.message_text reply_text, reply.media_type reply_media_type, reply_user.username reply_username
  FROM messages m
  JOIN users s ON s.id = m.sender_id
  LEFT JOIN user_profiles p ON p.user_id = s.id
  LEFT JOIN messages reply ON reply.id = m.reply_to_message_id
  LEFT JOIN users reply_user ON reply_user.id = reply.sender_id
  WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0) OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0))
    AND m.deleted_for_everyone = 0
  ORDER BY m.id DESC LIMIT 60");
$stmt->execute([$me['id'], $with, $with, $me['id']]);
json_response(['success' => true, 'current_user_id' => (int)$me['id'], 'peer' => $peerStmt->fetch(), 'messages' => array_reverse($stmt->fetchAll())]);
