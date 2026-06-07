<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$id = (int)(input()['notification_id'] ?? 0);
if ($id) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $me['id']]);
} else {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$me['id']]);
}
json_response(['success' => true]);

