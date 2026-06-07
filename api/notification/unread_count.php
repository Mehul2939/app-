<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$me['id']]);
json_response(['success' => true, 'unread_count' => (int)$stmt->fetchColumn()]);

