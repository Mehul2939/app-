<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';

$me = current_user();
$peerId = (int)($_GET['user_id'] ?? 0);
if ($peerId <= 0 || $peerId === (int)$me['id']) {
    json_response(['success' => false, 'message' => 'Invalid call recipient'], 422);
}

$peer = db()->prepare("SELECT u.id, u.name, u.username, COALESCE(u.profile_photo, p.profile_photo) profile_photo
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = ? AND u.status = 'active'
    LIMIT 1");
$peer->execute([$peerId]);
$peerUser = $peer->fetch();
if (!$peerUser) {
    json_response(['success' => false, 'message' => 'User not found'], 404);
}

$friends = are_friends((int)$me['id'], $peerId) && are_friends($peerId, (int)$me['id']);
$blocked = blocked_between((int)$me['id'], $peerId);
json_response([
    'success' => true,
    'can_call' => $friends && !$blocked,
    'is_friend' => $friends,
    'is_blocked' => $blocked,
    'peer' => $peerUser,
]);
