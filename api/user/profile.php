<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user(false);
$username = clean_string($_GET['username'] ?? ($me['username'] ?? ''), 60);
if ($username === '') json_response(['success' => false, 'message' => 'Username required'], 422);

$stmt = db()->prepare("SELECT u.id,u.public_user_id,u.name,u.username,u.created_at,u.last_active,u.last_active_at,u.is_demo_user,u.state,u.interests,u.preferred_gender_filter,
  COALESCE(u.profile_photo,p.profile_photo) profile_photo,p.cover_photo,COALESCE(u.bio,p.bio) bio,COALESCE(u.city,p.city) city,COALESCE(u.gender,p.gender) gender,p.account_type,
  (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND status = 'active') posts_count,
  (SELECT COUNT(*) FROM followers WHERE following_id = u.id) followers_count,
  (SELECT COUNT(*) FROM followers WHERE follower_id = u.id) following_count,
  (SELECT COUNT(*) FROM friends WHERE user_id = u.id) friends_count
  FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
  WHERE u.username = ? AND u.status = 'active' LIMIT 1");
$stmt->execute([$username]);
$profile = $stmt->fetch();
if (!$profile) json_response(['success' => false, 'message' => 'User not found'], 404);

$viewerId = (int)($me['id'] ?? 0);
$profile['is_me'] = $viewerId === (int)$profile['id'];
$profile['friend_status'] = friend_status($viewerId, (int)$profile['id']);
$profile['is_following'] = false;
if ($viewerId) {
    $f = db()->prepare('SELECT id FROM followers WHERE follower_id = ? AND following_id = ? LIMIT 1');
    $f->execute([$viewerId, $profile['id']]);
    $profile['is_following'] = (bool)$f->fetch();
    $b = db()->prepare('SELECT blocker_id, blocked_id FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)');
    $b->execute([$viewerId, $profile['id'], $profile['id'], $viewerId]);
    $profile['blocked_by_me'] = false;
    $profile['blocked_me'] = false;
    foreach ($b->fetchAll() as $row) {
        if ((int)$row['blocker_id'] === $viewerId) $profile['blocked_by_me'] = true;
        if ((int)$row['blocked_id'] === $viewerId) $profile['blocked_me'] = true;
    }
}
$wallet = null;
if ($profile['is_me']) {
    require_once __DIR__ . '/../helpers/wallet.php';
    $wallet = wallet_row($viewerId);
}
$gifts = db()->prepare('SELECT gt.*, g.gift_name, g.gift_icon, u.name sender_name FROM gift_transactions gt JOIN gifts g ON g.id = gt.gift_id JOIN users u ON u.id = gt.sender_id WHERE gt.receiver_id = ? ORDER BY gt.id DESC LIMIT 12');
$gifts->execute([$profile['id']]);
json_response(['success' => true, 'profile' => $profile, 'wallet' => $wallet, 'gifts' => $gifts->fetchAll()]);
