<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$stmt = db()->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND seen_at IS NULL');
$stmt->execute([$me['id']]);
json_response(['success' => true, 'unread_count' => (int)$stmt->fetchColumn()]);

