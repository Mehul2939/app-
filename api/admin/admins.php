<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
$admin = current_admin();
if ($admin['role'] !== 'super_admin') json_response(['success' => false, 'message' => 'Super Admin only'], 403);
$data = input();
if (!empty($data['remove_id']) && (int)$data['remove_id'] !== (int)$admin['id']) {
    $stories = db()->prepare('SELECT COUNT(*) FROM stories WHERE admin_id=?');
    $stories->execute([(int)$data['remove_id']]);
    if ((int)$stories->fetchColumn() > 0) json_response(['success' => false, 'message' => 'Reassign or delete this admin author stories first'], 409);
    db()->prepare('DELETE FROM admin_users WHERE id=?')->execute([(int)$data['remove_id']]);
}
$rows = db()->query('SELECT id,admin_code,name,email,role,status,created_at FROM admin_users ORDER BY id')->fetchAll();
json_response(['success' => true, 'admins' => $rows]);
