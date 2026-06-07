<?php
require_once __DIR__ . '/../helpers/response.php';
$data = input();
if (isset($data['user_id'], $data['status'])) {
    $status = in_array($data['status'], ['active','blocked'], true) ? $data['status'] : 'active';
    db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, (int)$data['user_id']]);
}
$q = '%' . clean_string($_GET['q'] ?? '', 80) . '%';
$stmt = db()->prepare('SELECT id, public_user_id, name, username, email, status, created_at, last_active FROM users WHERE name LIKE ? OR username LIKE ? OR email LIKE ? OR public_user_id LIKE ? ORDER BY id DESC LIMIT 100');
$stmt->execute([$q, $q, $q, $q]);
json_response(['success' => true, 'users' => $stmt->fetchAll()]);

