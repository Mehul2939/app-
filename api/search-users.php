<?php
require_once __DIR__ . '/helpers/discovery.php';
$me = current_user();
$q = clean_string($_GET['q'] ?? '', 80);
$gender = clean_string($_GET['gender'] ?? 'Any', 20);
$state = clean_string($_GET['state'] ?? '', 120);
$city = clean_string($_GET['city'] ?? '', 120);
$radius = (int)($_GET['radius'] ?? 100);
$activity = clean_string($_GET['activity'] ?? 'any', 20);
$showDemo = ($_GET['show_demo'] ?? '1') !== '0';
$where = ["u.id<>me.id", "u.status='active'", "NOT EXISTS(SELECT 1 FROM blocked_users b WHERE (b.blocker_id=me.id AND b.blocked_id=u.id) OR (b.blocker_id=u.id AND b.blocked_id=me.id))"];
$args = [$me['id']];
if ($q !== '') { $like = "%{$q}%"; $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.public_user_id LIKE ? OR u.bio LIKE ?)'; array_push($args, $like, $like, $like, $like); }
if (in_array($gender, ['Male','Female','Other'], true)) { $where[] = 'u.gender=?'; $args[] = $gender; }
if ($state !== '') { $where[] = 'LOWER(u.state)=LOWER(?)'; $args[] = $state; }
if ($city !== '') { $where[] = 'LOWER(u.city)=LOWER(?)'; $args[] = $city; }
if (!$showDemo) $where[] = 'u.is_demo_user=0';
if ($activity === 'online') $where[] = 'u.is_online=1';
if ($activity === 'recent') $where[] = 'u.last_active_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)';
$having = in_array($radius, [5,10,25,50,100], true) ? ' HAVING distance_km IS NULL OR distance_km<=?' : '';
if ($having) $args[] = $radius;
$sql = "SELECT " . discovery_select_sql() . " FROM users u JOIN users me ON me.id=? LEFT JOIN user_profiles p ON p.user_id=u.id WHERE " . implode(' AND ', $where) . $having . " ORDER BY " . discovery_order_sql() . " LIMIT 100";
$stmt = db()->prepare($sql);
$stmt->execute($args);
json_response(['success' => true, 'users' => attach_friend_statuses($stmt->fetchAll(), (int)$me['id'])]);

