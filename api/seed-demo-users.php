<?php
require_once __DIR__ . '/helpers/admin_auth.php';
require_once __DIR__ . '/helpers/demo_seed.php';
current_admin();
json_response(['success' => true] + seed_demo_users());

