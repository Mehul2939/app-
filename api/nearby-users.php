<?php
require_once __DIR__ . '/helpers/discovery.php';
$me = current_user();
$limit = min(60, max(1, (int)($_GET['limit'] ?? 24)));
$sql = "SELECT " . discovery_select_sql() . "
  FROM users u JOIN users me ON me.id=?
  LEFT JOIN user_profiles p ON p.user_id=u.id
  WHERE u.id<>me.id AND u.status='active'
    AND NOT EXISTS(SELECT 1 FROM blocked_users b WHERE (b.blocker_id=me.id AND b.blocked_id=u.id) OR (b.blocker_id=u.id AND b.blocked_id=me.id))
  ORDER BY preferred_gender DESC, u.is_demo_user ASC,
    CASE WHEN distance_km IS NULL THEN 1 ELSE 0 END ASC, distance_km ASC,
    same_city DESC, same_state DESC, u.is_online DESC, u.last_active_at DESC, u.id DESC LIMIT {$limit}";
$stmt = db()->prepare($sql);
$stmt->execute([$me['id']]);
json_response(['success' => true, 'users' => attach_friend_statuses($stmt->fetchAll(), (int)$me['id'])]);
