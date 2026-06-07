<?php

declare(strict_types=1);

define('RUN_MONTHLY_REPORT_SYNC', true);
define('ATTENDANCE_AJAX_REQUEST', true);
chdir(dirname(__DIR__, 2));

ob_start();
require dirname(__DIR__) . '/php/attendance_page.php';
ob_end_clean();

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'success' => true,
    'message' => 'Monthly report synchronized.',
]);
