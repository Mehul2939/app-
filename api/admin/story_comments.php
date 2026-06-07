<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
current_admin();
$data = input();
if (isset($data['comment_id'], $data['status'])) {
    $status = in_array($data['status'], ['active','hidden','deleted'], true) ? $data['status'] : 'hidden';
    db()->prepare('UPDATE story_comments SET status=? WHERE id=?')->execute([$status, (int)$data['comment_id']]);
}
$rows = db()->query("SELECT c.*,u.name,u.username,s.title story_title FROM story_comments c JOIN users u ON u.id=c.user_id JOIN stories s ON s.id=c.story_id ORDER BY c.id DESC LIMIT 200")->fetchAll();
json_response(['success' => true, 'comments' => $rows]);

