<?php
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/story.php';
$q = clean_string($_GET['q'] ?? '', 100);
$category = clean_string($_GET['category'] ?? '', 120);
$sql = "SELECT s.id, s.title, s.slug, s.excerpt, s.featured_image, s.category, s.published_at, s.updated_at, a.name author_name, a.admin_code,
 (SELECT COALESCE(SUM(view_count),0) FROM story_views v WHERE v.story_id=s.id) views_count,
 (SELECT COUNT(*) FROM story_views v WHERE v.story_id=s.id) unique_views,
 (SELECT COUNT(*) FROM story_likes l WHERE l.story_id=s.id) likes_count,
 (SELECT COUNT(*) FROM story_comments c WHERE c.story_id=s.id AND c.status='active') comments_count
 FROM stories s JOIN admin_users a ON a.id=s.admin_id
 WHERE " . story_is_public_sql('s');
$args = [];
if ($q !== '') { $sql .= ' AND (s.title LIKE ? OR s.excerpt LIKE ? OR s.keywords LIKE ?)'; $like = "%{$q}%"; array_push($args, $like, $like, $like); }
if ($category !== '') { $sql .= ' AND s.category = ?'; $args[] = $category; }
$sql .= ' ORDER BY s.published_at DESC LIMIT 60';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$stories = $stmt->fetchAll();
foreach ($stories as &$story) $story['reading_time'] = story_reading_minutes($story['excerpt']);
$categories = db()->query("SELECT DISTINCT category FROM stories WHERE " . story_is_public_sql('stories') . " ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
json_response(['success' => true, 'stories' => $stories, 'categories' => $categories]);

