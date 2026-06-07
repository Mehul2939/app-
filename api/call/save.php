<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';

$me = current_user();
$data = input();
$callId = (int)($data['call_id'] ?? 0);
$receiverId = (int)($data['receiver_id'] ?? 0);
$status = clean_string($data['status'] ?? '', 20);
$duration = max(0, min(86400, (int)($data['duration_seconds'] ?? 0)));
$validStatuses = ['started', 'answered', 'missed', 'rejected', 'ended'];
if (!in_array($status, $validStatuses, true)) {
    json_response(['success' => false, 'message' => 'Invalid call status'], 422);
}

if ($callId <= 0) {
    if ($status !== 'started' || $receiverId <= 0 || $receiverId === (int)$me['id']) {
        json_response(['success' => false, 'message' => 'Invalid call request'], 422);
    }
    if (!are_friends((int)$me['id'], $receiverId) || !are_friends($receiverId, (int)$me['id']) || blocked_between((int)$me['id'], $receiverId)) {
        json_response(['success' => false, 'message' => 'Only mutual friends can call each other'], 403);
    }
    $validUser = db()->prepare("SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1");
    $validUser->execute([$receiverId]);
    if (!$validUser->fetch()) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }
    db()->prepare("INSERT INTO call_logs (caller_id, receiver_id, call_type, status) VALUES (?, ?, 'audio', 'started')")
        ->execute([$me['id'], $receiverId]);
    json_response(['success' => true, 'call_id' => (int)db()->lastInsertId()]);
}

$call = db()->prepare('SELECT * FROM call_logs WHERE id = ? AND (caller_id = ? OR receiver_id = ?) LIMIT 1');
$call->execute([$callId, $me['id'], $me['id']]);
$existing = $call->fetch();
if (!$existing) {
    json_response(['success' => false, 'message' => 'Call not found'], 404);
}

$allowedTransitions = [
    'started' => ['answered', 'missed', 'rejected', 'ended'],
    'answered' => ['ended'],
    'missed' => [],
    'rejected' => [],
    'ended' => [],
];
if (!in_array($status, $allowedTransitions[$existing['status']] ?? [], true)) {
    json_response(['success' => false, 'message' => 'Invalid call status transition'], 409);
}
db()->prepare('UPDATE call_logs SET status = ?, duration_seconds = ? WHERE id = ?')->execute([$status, $duration, $callId]);
json_response(['success' => true, 'call_id' => $callId, 'status' => $status, 'duration_seconds' => $duration]);
