<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
require_once __DIR__ . '/../helpers/story.php';
$admin = current_admin();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->query("SELECT s.*, a.name author_name, a.admin_code,
      (SELECT COALESCE(SUM(view_count),0) FROM story_views v WHERE v.story_id=s.id) views_count,
      (SELECT COUNT(*) FROM story_comments c WHERE c.story_id=s.id AND c.status='active') comments_count,
      (SELECT COUNT(*) FROM story_reactions r WHERE r.story_id=s.id) reactions_count
      FROM stories s JOIN admin_users a ON a.id=s.admin_id ORDER BY s.id DESC LIMIT 200");
    json_response(['success' => true, 'stories' => $stmt->fetchAll()]);
}
$data = array_merge(input(), $_POST);
$action = clean_string($data['action'] ?? 'save', 20);
$id = (int)($data['story_id'] ?? 0);
if ($action === 'delete') {
    db()->prepare('DELETE FROM stories WHERE id=?')->execute([$id]);
    json_response(['success' => true]);
}
$title = clean_string($data['title'] ?? '', 220);
$content = sanitize_story_html((string)($data['content'] ?? ''));
if ($title === '' || trim(strip_tags($content)) === '') json_response(['success' => false, 'message' => 'Title and content required'], 422);
$status = in_array($data['status'] ?? '', ['draft','published','scheduled','unpublished'], true) ? $data['status'] : 'draft';
$publishAt = clean_string($data['publish_at'] ?? '', 30) ?: null;
if ($status === 'scheduled' && !$publishAt) json_response(['success' => false, 'message' => 'Schedule date required'], 422);
$excerpt = excerpt_text($content, 320);
$metaTitle = clean_string($data['meta_title'] ?? '', 220) ?: $title;
$metaDescription = clean_string($data['meta_description'] ?? '', 320) ?: $excerpt;
$image = store_story_upload('featured_image', 'image');
$audio = store_story_upload('audio_file', 'audio');
if ($id) {
    $old = db()->prepare('SELECT featured_image,audio_path FROM stories WHERE id=?');
    $old->execute([$id]);
    $row = $old->fetch();
    $stmt = db()->prepare("UPDATE stories SET title=?,slug=?,content=?,excerpt=?,featured_image=?,audio_path=?,category=?,keywords=?,seo_tags=?,meta_title=?,meta_description=?,status=?,publish_at=?,published_at=IF(?='published',COALESCE(published_at,NOW()),published_at) WHERE id=?");
    $stmt->execute([$title, unique_story_slug($title, $id), $content, $excerpt, $image ?: $row['featured_image'], $audio ?: $row['audio_path'], clean_string($data['category'] ?? 'Stories', 120), clean_string($data['keywords'] ?? '', 500), clean_string($data['seo_tags'] ?? '', 500), $metaTitle, $metaDescription, $status, $publishAt, $status, $id]);
} else {
    $stmt = db()->prepare("INSERT INTO stories (admin_id,title,slug,content,excerpt,featured_image,audio_path,category,keywords,seo_tags,meta_title,meta_description,status,publish_at,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?='published',NOW(),NULL))");
    $stmt->execute([$admin['id'], $title, unique_story_slug($title), $content, $excerpt, $image, $audio, clean_string($data['category'] ?? 'Stories', 120), clean_string($data['keywords'] ?? '', 500), clean_string($data['seo_tags'] ?? '', 500), $metaTitle, $metaDescription, $status, $publishAt, $status]);
}
json_response(['success' => true, 'message' => 'Story saved']);

