<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
$admin = current_admin();
db()->prepare('UPDATE admin_users SET login_token = NULL WHERE id = ?')->execute([$admin['id']]);
json_response(['success' => true]);

