<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
$admin = current_admin(false);
json_response(['success' => true, 'admin' => $admin]);

