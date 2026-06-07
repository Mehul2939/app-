<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$target = (int)(input()['user_id'] ?? 0);
db()->prepare('DELETE FROM followers WHERE follower_id = ? AND following_id = ?')->execute([$me['id'], $target]);
json_response(['success' => true, 'following' => false]);

