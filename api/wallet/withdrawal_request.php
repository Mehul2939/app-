<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/wallet.php';

$me = current_user();
$data = input();
$coins = (int)($data['coins'] ?? 0);
$upi = clean_string($data['upi_id'] ?? '', 120);
$contact = clean_string($data['contact_number'] ?? '', 30);
$holder = clean_string($data['account_holder_name'] ?? '', 120);

$minStmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'minimum_withdrawal_coins'");
$minStmt->execute();
$minimum = (int)($minStmt->fetchColumn() ?: 1000);
if ($coins < $minimum || $upi === '' || $contact === '' || $holder === '') {
    json_response(['success' => false, 'message' => 'Minimum withdrawal and all payout details are required'], 422);
}

$amount = round(($coins / 100) * 49, 2);
$pdo = db();
try {
    $pdo->beginTransaction();
    spend_coins((int)$me['id'], $coins, 'admin_deduct', 'Withdrawal request hold');
    $eta = date('Y-m-d', strtotime('+3 weekdays'));
    $pdo->prepare('INSERT INTO wallet_withdrawals (user_id, coins, amount_inr, upi_id, contact_number, account_holder_name, estimated_payout_date) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$me['id'], $coins, $amount, $upi, $contact, $holder, $eta]);
    $id = (int)$pdo->lastInsertId();
    notify_user((int)$me['id'], null, 'withdrawal_pending', 'Withdrawal request submitted and pending approval.', $id);
    $pdo->commit();
    json_response(['success' => true, 'message' => 'Withdrawal request submitted', 'estimated_payout_date' => $eta]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => $e->getMessage()], 409);
}

