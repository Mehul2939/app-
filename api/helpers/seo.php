<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function site_url(): string
{
    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
    $stmt->execute();
    $value = (string)($stmt->fetchColumn() ?: '');
    if ($value !== '') {
        return rtrim($value, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/app';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?: '';
    $text = trim($text, '-');
    return $text !== '' ? substr($text, 0, 150) : 'post';
}

function unique_post_slug(string $text, int $ignoreId = 0): string
{
    $base = slugify($text);
    $slug = $base;
    $i = 2;
    do {
        $stmt = db()->prepare('SELECT id FROM posts WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    } while ($i < 500);
    return $base . '-' . time();
}

function excerpt_text(string $text, int $limit = 155): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?: '');
    return mb_substr($text, 0, $limit);
}

