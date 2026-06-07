<?php
require_once __DIR__ . '/../helpers/response.php';
$data = input();
if (isset($data['report_id'], $data['status'])) {
    db()->prepare('UPDATE reports SET status = ? WHERE id = ?')->execute([clean_string($data['status'], 20), (int)$data['report_id']]);
}
$stmt = db()->query('SELECT r.*, u.username reporter FROM reports r JOIN users u ON u.id = r.reporter_id ORDER BY r.id DESC LIMIT 100');
json_response(['success' => true, 'reports' => $stmt->fetchAll()]);

