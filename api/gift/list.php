<?php
require_once __DIR__ . '/../helpers/response.php';
$stmt = db()->query("SELECT * FROM gifts WHERE status = 'active' ORDER BY price_coins ASC");
json_response(['success' => true, 'gifts' => $stmt->fetchAll()]);

