<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this seeder from the command line.');
}
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/demo_seed.php';
echo json_encode(['success' => true] + seed_demo_users(), JSON_PRETTY_PRINT) . PHP_EOL;

