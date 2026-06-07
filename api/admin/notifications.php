<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
$admin = current_admin();
$data = input();
if (!empty($data['read_all'])) db()->prepare('UPDATE admin_notifications SET is_read=1 WHERE admin_id=?')->execute([$admin['id']]);
$stmt = db()->prepare('SELECT n.*,u.name actor_name,u.username,s.title story_title,s.slug story_slug FROM admin_notifications n LEFT JOIN users u ON u.id=n.actor_user_id LEFT JOIN stories s ON s.id=n.story_id WHERE n.admin_id=? ORDER BY n.id DESC LIMIT 100');
$stmt->execute([$admin['id']]);
json_response(['success' => true, 'notifications' => $stmt->fetchAll()]);

