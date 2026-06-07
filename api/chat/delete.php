<?php
require_once __DIR__ . '/../helpers/auth.php';

$me = current_user();
$data = input();
$ids = $data['message_ids'] ?? [];
$mode = clean_string($data['mode'] ?? 'me', 20);
if (!is_array($ids) || count($ids) === 0) {
    json_response(['success' => false, 'message' => 'Select messages first'], 422);
}
$ids = array_values(array_unique(array_map('intval', $ids)));
$ids = array_filter($ids, fn($id) => $id > 0);
if (!$ids) {
    json_response(['success' => false, 'message' => 'Invalid messages'], 422);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
if ($mode === 'everyone') {
    $params = array_merge([$me['id']], $ids);
    db()->prepare("UPDATE messages SET deleted_for_everyone = 1 WHERE sender_id = ? AND id IN ($placeholders)")->execute($params);
} else {
    $senderParams = array_merge([$me['id']], $ids);
    db()->prepare("UPDATE messages SET deleted_by_sender = 1 WHERE sender_id = ? AND id IN ($placeholders)")->execute($senderParams);
    $receiverParams = array_merge([$me['id']], $ids);
    db()->prepare("UPDATE messages SET deleted_by_receiver = 1 WHERE receiver_id = ? AND id IN ($placeholders)")->execute($receiverParams);
}

json_response(['success' => true, 'message' => 'Messages deleted']);

