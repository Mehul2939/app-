<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    return $_POST;
}

function clean_string(mixed $value, int $max = 255): string
{
    $value = trim((string)$value);
    return mb_substr($value, 0, $max);
}
