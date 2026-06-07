<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/story.php';
$me = current_user(false);
$slug = preg_replace('/[^a-zA-Z0-9-]/', '', $_GET['slug'] ?? '');
$stmt = db()->prepare("SELECT s.*, a.name author_name, a.admin_code,
 (SELECT COALESCE(SUM(view_count),0) FROM story_views v WHERE v.story_id=s.id) views_count,
 (SELECT COUNT(*) FROM story_views v WHERE v.story_id=s.id) unique_views,
 (SELECT COUNT(*) FROM story_likes l WHERE l.story_id=s.id) likes_count,
 (SELECT COUNT(*) FROM story_likes l WHERE l.story_id=s.id AND l.user_id=?) liked_by_me,
 (SELECT reaction_type FROM story_reactions r WHERE r.story_id=s.id AND r.user_id=? LIMIT 1) my_reaction
 FROM stories s JOIN admin_users a ON a.id=s.admin_id WHERE s.slug=? AND " . story_is_public_sql('s') . ' LIMIT 1');
$stmt->execute([(int)($me['id'] ?? 0), (int)($me['id'] ?? 0), $slug]);
$story = $stmt->fetch();
if (!$story) json_response(['success' => false, 'message' => 'Story not found'], 404);
$story['reading_time'] = story_reading_minutes($story['content']);
$comments = [];
if ($me) {
    $c = db()->prepare("SELECT c.*, u.name, u.username, p.profile_photo FROM story_comments c JOIN users u ON u.id=c.user_id LEFT JOIN user_profiles p ON p.user_id=u.id WHERE c.story_id=? AND c.status='active' ORDER BY c.created_at ASC LIMIT 200");
    $c->execute([$story['id']]);
    $comments = $c->fetchAll();
}
$reactionStmt = db()->prepare('SELECT reaction_type, COUNT(*) total FROM story_reactions WHERE story_id=? GROUP BY reaction_type');
$reactionStmt->execute([$story['id']]);
$reactions = [];
foreach ($reactionStmt->fetchAll() as $r) $reactions[$r['reaction_type']] = (int)$r['total'];
$related = db()->prepare("SELECT slug,title,featured_image,category FROM stories WHERE id<>? AND " . story_is_public_sql('stories') . " AND (category=? OR keywords LIKE ?) ORDER BY published_at DESC LIMIT 6");
$related->execute([$story['id'], $story['category'], '%' . strtok((string)$story['keywords'], ',') . '%']);
json_response(['success' => true, 'story' => $story, 'comments' => $comments, 'comments_locked' => !$me, 'reactions' => $reactions, 'related' => $related->fetchAll()]);

