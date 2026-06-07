<?php
require_once __DIR__ . '/helpers/auth.php';
$me = current_user();
$data = input();
$latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$state = clean_string($data['state'] ?? '', 120);
$city = clean_string($data['city'] ?? '', 120);
if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    json_response(['success' => false, 'message' => 'Valid location required'], 422);
}
db()->prepare('UPDATE users SET latitude=?,longitude=?,state=COALESCE(NULLIF(?, ""),state),city=COALESCE(NULLIF(?, ""),city),profile_completed=IF(gender IS NOT NULL AND COALESCE(NULLIF(?,""),city) IS NOT NULL,1,profile_completed) WHERE id=?')
    ->execute([$latitude, $longitude, $state, $city, $city, $me['id']]);
if ($city !== '') db()->prepare('UPDATE user_profiles SET city=? WHERE user_id=?')->execute([$city, $me['id']]);
json_response(['success' => true, 'message' => 'Approximate location updated']);

