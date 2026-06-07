<?php
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/wallet.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$data = input();
$login = clean_string($data['login'] ?? '', 160);
$password = (string)($data['password'] ?? '');

$stmt = db()->prepare("SELECT u.*, p.profile_photo, p.cover_photo, p.bio, p.city, p.gender, p.account_type
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE (u.username = ? OR u.email = ? OR u.mobile = ?) AND u.status = 'active'
    LIMIT 1");
$stmt->execute([$login, $login, $login]);
$user = $stmt->fetch();
$success = $user && password_verify($password, $user['password']);
db()->prepare('INSERT INTO login_logs (user_id, login_input, ip_address, user_agent, success) VALUES (?, ?, ?, ?, ?)')
    ->execute([$user['id'] ?? null, $login, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $success ? 1 : 0]);

if (!$success) {
    json_response(['success' => false, 'message' => 'Invalid login details'], 401);
}

$token = bin2hex(random_bytes(32));
db()->prepare('UPDATE users SET login_token = ?, last_active = NOW() WHERE id = ?')->execute([$token, $user['id']]);
ensure_wallet((int)$user['id']);
$_SESSION['token'] = $token;
unset($user['password'], $user['login_token']);
json_response(['success' => true, 'message' => 'Login successful', 'token' => $token, 'user' => $user]);
