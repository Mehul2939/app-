<?php
require_once __DIR__ . '/../helpers/auth.php';
$user = current_user(false);
if ($user) {
    db()->prepare('UPDATE users SET login_token = NULL WHERE id = ?')->execute([$user['id']]);
}
if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
json_response(['success' => true, 'message' => 'Logged out']);

