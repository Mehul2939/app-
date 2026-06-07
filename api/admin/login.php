<?php
require_once __DIR__ . '/../helpers/admin_auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$data = input();
$email = clean_string($data['email'] ?? '', 160);
$password = (string)($data['password'] ?? '');

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$attempt = db()->prepare("SELECT COUNT(*) FROM login_logs WHERE login_input = ? AND ip_address = ? AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$attempt->execute(['admin:' . $email, $ip]);
if ((int)$attempt->fetchColumn() >= 8) json_response(['success' => false, 'message' => 'Too many attempts. Try again later.'], 429);
$stmt = db()->prepare("SELECT * FROM admin_users WHERE email = ? AND status = 'active' LIMIT 1");
$stmt->execute([$email]);
$admin = $stmt->fetch();
$valid = $admin && password_verify($password, $admin['password']);
db()->prepare('INSERT INTO login_logs (user_id, login_input, ip_address, user_agent, success) VALUES (NULL, ?, ?, ?, ?)')
    ->execute(['admin:' . $email, $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $valid ? 1 : 0]);
if (!$valid) {
    json_response(['success' => false, 'message' => 'Invalid admin login'], 401);
}

$token = bin2hex(random_bytes(32));
db()->prepare('UPDATE admin_users SET login_token = ? WHERE id = ?')->execute([$token, $admin['id']]);
unset($admin['password'], $admin['login_token']);
json_response(['success' => true, 'token' => $token, 'admin' => $admin]);
