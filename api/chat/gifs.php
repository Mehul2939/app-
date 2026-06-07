<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['gif']['tmp_name'])) {
        json_response(['success' => false, 'message' => 'GIF required'], 422);
    }
    $type = $_FILES['gif']['type'] ?? '';
    $size = (int)($_FILES['gif']['size'] ?? 0);
    if ($type !== 'image/gif') {
        json_response(['success' => false, 'message' => 'Only GIF files are allowed'], 422);
    }
    if ($size > 20971520) {
        json_response(['success' => false, 'message' => 'Maximum file size is 20 MB.'], 422);
    }
    $dir = __DIR__ . '/../../uploads/chats/gifs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $path = 'uploads/chats/gifs/gif_' . $me['id'] . '_' . bin2hex(random_bytes(8)) . '.gif';
    if (!move_uploaded_file($_FILES['gif']['tmp_name'], __DIR__ . '/../../' . $path)) {
        json_response(['success' => false, 'message' => 'GIF upload failed'], 500);
    }
    db()->prepare('INSERT INTO user_gifs (user_id, gif_path, title) VALUES (?, ?, ?)')
        ->execute([$me['id'], $path, clean_string($_POST['title'] ?? '', 120)]);
    json_response(['success' => true, 'message' => 'GIF saved to your gallery', 'gif_path' => $path]);
}

$stmt = db()->prepare('SELECT id, gif_path, title, created_at FROM user_gifs WHERE user_id = ? ORDER BY id DESC LIMIT 60');
$stmt->execute([$me['id']]);
json_response(['success' => true, 'gifs' => $stmt->fetchAll()]);

