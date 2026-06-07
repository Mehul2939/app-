<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$reason = clean_string(input()['reason'] ?? '', 255);
db()->prepare('INSERT INTO account_deletion_requests (user_id, reason) VALUES (?, ?)')->execute([$me['id'], $reason]);
db()->prepare("UPDATE users SET status = 'deleted', login_token = NULL WHERE id = ?")->execute([$me['id']]);
json_response(['success' => true, 'message' => 'Your account deletion request has been recorded and your account is disabled.']);

