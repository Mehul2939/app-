<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$data = input();
$target = (int)($data['user_id'] ?? 0);
$reason = clean_string($data['reason'] ?? 'Reported by user', 255);
if ($target <= 0 || $target === (int)$me['id']) json_response(['success' => false, 'message' => 'Invalid user'], 422);
db()->prepare('INSERT INTO reports (reporter_id,reported_user_id,reason) VALUES (?,?,?)')->execute([$me['id'], $target, $reason]);
json_response(['success' => true, 'message' => 'User report submitted']);

