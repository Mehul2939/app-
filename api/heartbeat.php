<?php
require_once __DIR__ . '/api_headers.php';
require_once __DIR__ . '/helpers/auth.php';

$me = current_user();
db()->prepare('UPDATE users SET is_online = 1, last_seen = NOW(), last_active = NOW(), last_active_at = NOW() WHERE id = ?')->execute([$me['id']]);
json_response(['success' => true, 'is_online' => true, 'last_seen' => date('Y-m-d H:i:s')]);
