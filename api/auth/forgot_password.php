<?php
require_once __DIR__ . '/../helpers/response.php';
$data = input();
$login = clean_string($data['login'] ?? '', 160);
$stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR mobile = ? OR username = ? LIMIT 1');
$stmt->execute([$login, $login, $login]);
json_response(['success' => true, 'message' => 'If the account exists, OTP reset instructions can be sent from your SMS/email gateway.']);

