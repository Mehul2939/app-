<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
$creator = current_admin();
if ($creator['role'] !== 'super_admin') json_response(['success' => false, 'message' => 'Only Super Admin can create admins'], 403);
$data = input();
$name = clean_string($data['name'] ?? '', 120);
$email = filter_var(clean_string($data['email'] ?? '', 160), FILTER_VALIDATE_EMAIL);
$password = (string)($data['password'] ?? '');
$role = ($data['role'] ?? '') === 'super_admin' ? 'super_admin' : 'admin';
if (!$email || $name === '' || strlen($password) < 8) json_response(['success' => false, 'message' => 'Name, valid email and 8+ character password required'], 422);
try {
    $stmt = db()->prepare('INSERT INTO admin_users (admin_code, name, email, password, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([next_admin_code(), $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    json_response(['success' => true, 'message' => 'Admin created']);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Email already exists'], 409);
}

