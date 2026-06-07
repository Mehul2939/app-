<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';

$me = current_user();
$peerId = (int)($_GET['user_id'] ?? 0);
if ($peerId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid user'], 422);
}

$stmt = db()->prepare("SELECT c.id, c.caller_id, c.receiver_id, c.call_type, c.status, c.duration_seconds, c.created_at,
    caller.name caller_name, receiver.name receiver_name
    FROM call_logs c
    JOIN users caller ON caller.id = c.caller_id
    JOIN users receiver ON receiver.id = c.receiver_id
    WHERE (c.caller_id = ? AND c.receiver_id = ?) OR (c.caller_id = ? AND c.receiver_id = ?)
    ORDER BY c.id DESC LIMIT 50");
$stmt->execute([$me['id'], $peerId, $peerId, $me['id']]);
json_response(['success' => true, 'call_logs' => $stmt->fetchAll()]);
