<?php
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/wallet.php';
$data = input();
if (isset($data['user_id'], $data['coins'], $data['action'])) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        if ($data['action'] === 'add') add_coins((int)$data['user_id'], (int)$data['coins'], 'admin_add', 'Admin added coins');
        if ($data['action'] === 'deduct') spend_coins((int)$data['user_id'], (int)$data['coins'], 'admin_deduct', 'Admin deducted coins');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['success' => false, 'message' => $e->getMessage()], 409);
    }
}
$stmt = db()->query('SELECT u.id user_id, u.name, u.username, w.coins_balance, w.total_earned, w.total_spent FROM user_wallets w JOIN users u ON u.id = w.user_id ORDER BY w.coins_balance DESC LIMIT 100');
json_response(['success' => true, 'wallets' => $stmt->fetchAll()]);

