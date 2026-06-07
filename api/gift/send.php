<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/wallet.php';
$me = current_user();
$data = input();
$receiver = (int)($data['receiver_id'] ?? 0);
$giftId = (int)($data['gift_id'] ?? 0);
$message = clean_string($data['message'] ?? '', 255);
if ($receiver === (int)$me['id']) json_response(['success' => false, 'message' => 'You cannot gift yourself'], 422);
$pdo = db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM gifts WHERE id = ? AND status = 'active' FOR UPDATE");
    $stmt->execute([$giftId]);
    $gift = $stmt->fetch();
    if (!$gift) throw new RuntimeException('Gift not found');
    spend_coins((int)$me['id'], (int)$gift['price_coins'], 'gift_send', 'Gift: ' . $gift['gift_name'], $giftId);
    $pdo->prepare('INSERT INTO gift_transactions (sender_id, receiver_id, gift_id, coins_spent, message) VALUES (?, ?, ?, ?, ?)')
        ->execute([$me['id'], $receiver, $giftId, $gift['price_coins'], $message]);
    $trxId = (int)$pdo->lastInsertId();
    notify_user($receiver, (int)$me['id'], 'gift_received', $me['name'] . ' sent you ' . $gift['gift_name'], $trxId);
    $pdo->commit();
    json_response(['success' => true, 'message' => 'Gift sent']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => $e->getMessage()], 409);
}

