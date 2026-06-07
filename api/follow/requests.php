<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user();
$data = input();
if (isset($data['request_id'], $data['action'])) {
    $id = (int)$data['request_id'];
    $action = $data['action'] === 'accept' ? 'accepted' : 'rejected';
    $stmt = db()->prepare('SELECT * FROM follow_requests WHERE id = ? AND receiver_id = ?');
    $stmt->execute([$id, $me['id']]);
    if ($r = $stmt->fetch()) {
        db()->prepare('UPDATE follow_requests SET status = ? WHERE id = ?')->execute([$action, $id]);
        if ($action === 'accepted') {
            db()->prepare('INSERT IGNORE INTO followers (follower_id, following_id) VALUES (?, ?)')->execute([$r['requester_id'], $me['id']]);
            notify_user((int)$r['requester_id'], (int)$me['id'], 'follow_accepted', $me['name'] . ' accepted your request');
        }
    }
}
$stmt = db()->prepare('SELECT fr.*, u.name, u.username FROM follow_requests fr JOIN users u ON u.id = fr.requester_id WHERE fr.receiver_id = ? AND fr.status = "pending" ORDER BY fr.id DESC');
$stmt->execute([$me['id']]);
json_response(['success' => true, 'requests' => $stmt->fetchAll()]);

