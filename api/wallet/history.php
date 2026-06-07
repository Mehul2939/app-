<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$stmt = db()->prepare('SELECT * FROM coin_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 100');
$stmt->execute([$me['id']]);
json_response(['success' => true, 'transactions' => $stmt->fetchAll()]);

