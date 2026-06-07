<?php

declare(strict_types=1);

define('ATTENDANCE_AJAX_REQUEST', true);
chdir(dirname(__DIR__, 2));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed.',
    ]);
    exit;
}

if (!isset($_POST['checkin']) && !isset($_POST['checkout'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid attendance action.',
    ]);
    exit;
}

require dirname(__DIR__) . '/php/attendance_page.php';
