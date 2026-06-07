<?php
require_once __DIR__ . '/../helpers/response.php';
$data = input();
if (!empty($data['gift_name'])) {
    db()->prepare('INSERT INTO gifts (gift_name, gift_icon, gift_image, price_coins, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE gift_icon = VALUES(gift_icon), gift_image = VALUES(gift_image), price_coins = VALUES(price_coins), status = VALUES(status)')
        ->execute([clean_string($data['gift_name'], 80), clean_string($data['gift_icon'] ?? '🎁', 20), clean_string($data['gift_image'] ?? '', 255), (int)($data['price_coins'] ?? 1), clean_string($data['status'] ?? 'active', 20)]);
}
$stmt = db()->query('SELECT * FROM gifts ORDER BY id DESC');
json_response(['success' => true, 'gifts' => $stmt->fetchAll()]);

