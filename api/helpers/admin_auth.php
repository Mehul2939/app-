<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

function admin_bearer_token(): string
{
    $direct = trim((string)($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''));
    if ($direct !== '') return $direct;
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/Bearer\s+(.+)/i', $header, $m) ? trim($m[1]) : '';
}

function current_admin(bool $required = true, ?string $role = null): ?array
{
    $token = admin_bearer_token();
    if ($token === '') {
        if ($required) json_response(['success' => false, 'message' => 'Admin login required'], 401);
        return null;
    }
    $stmt = db()->prepare("SELECT id, admin_code, name, email, role, status, created_at FROM admin_users WHERE login_token = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$token]);
    $admin = $stmt->fetch() ?: null;
    if (!$admin || ($role && $admin['role'] !== $role)) {
        if ($required) json_response(['success' => false, 'message' => $admin ? 'Permission denied' : 'Invalid admin session'], $admin ? 403 : 401);
        return null;
    }
    return $admin;
}

function next_admin_code(): string
{
    $next = (int)db()->query('SELECT COALESCE(MAX(id), 0) + 1 FROM admin_users')->fetchColumn();
    return 'ADM' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}
