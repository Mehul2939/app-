<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/response.php';

function bearer_token(): string
{
    $direct = trim((string)($_SERVER['HTTP_X_USER_TOKEN'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }
    return (string)($_SESSION['token'] ?? '');
}

function current_user(bool $required = true): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = bearer_token();
    if ($token === '') {
        if ($required) {
            json_response(['success' => false, 'message' => 'Login required'], 401);
        }
        return null;
    }

    $stmt = db()->prepare("SELECT u.*, p.profile_photo, p.cover_photo, p.bio, p.city, p.gender, p.account_type
        FROM users u
        LEFT JOIN user_profiles p ON p.user_id = u.id
        WHERE u.login_token = ? AND u.status = 'active'
        LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user && $required) {
        json_response(['success' => false, 'message' => 'Invalid session'], 401);
    }
    return $user ?: null;
}

function public_user(array $user): array
{
    unset($user['password'], $user['login_token']);
    return $user;
}
