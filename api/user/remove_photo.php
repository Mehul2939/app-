<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
db()->prepare('UPDATE user_profiles SET profile_photo = NULL WHERE user_id = ?')->execute([$me['id']]);
db()->prepare('UPDATE users SET profile_photo = NULL WHERE id = ?')->execute([$me['id']]);
json_response(['success' => true, 'message' => 'Profile photo removed']);
