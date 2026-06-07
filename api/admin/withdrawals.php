<?php
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/social.php';

$data = input();
if (isset($data['withdrawal_id'], $data['status'])) {
    $id = (int)$data['withdrawal_id'];
    $status = clean_string($data['status'], 20);
    $note = clean_string($data['admin_note'] ?? '', 255);
    $reason = clean_string($data['rejection_reason'] ?? '', 255);
    if (!in_array($status, ['approved','paid','rejected'], true)) {
        json_response(['success' => false, 'message' => 'Invalid status'], 422);
    }
    db()->prepare('UPDATE wallet_withdrawals SET status = ?, admin_note = ?, rejection_reason = ? WHERE id = ?')->execute([$status, $note, $reason, $id]);
    $stmt = db()->prepare('SELECT user_id FROM wallet_withdrawals WHERE id = ?');
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $msg = $status === 'rejected' ? 'Payment rejected: ' . ($reason ?: 'Admin review') : 'Payment ' . $status . '.';
        notify_user((int)$row['user_id'], null, 'admin_payment_' . $status, $msg, $id);
    }
}

$stmt = db()->query('SELECT w.*, u.public_user_id, u.username, u.email FROM wallet_withdrawals w JOIN users u ON u.id = w.user_id ORDER BY w.id DESC LIMIT 200');
json_response(['success' => true, 'withdrawals' => $stmt->fetchAll()]);
