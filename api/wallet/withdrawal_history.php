<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$stmt = db()->prepare('SELECT id, coins, amount_inr, upi_id, status, admin_note, rejection_reason, estimated_payout_date, created_at, updated_at FROM wallet_withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 100');
$stmt->execute([$me['id']]);
json_response(['success' => true, 'withdrawals' => $stmt->fetchAll(), 'coin_rate' => '100 coins = INR 49']);

