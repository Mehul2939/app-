<?php
require_once __DIR__ . '/../helpers/auth.php';
$me = current_user();
$data = input();
$name = clean_string($data['name'] ?? $me['name'], 120);
$bio = clean_string($data['bio'] ?? '', 1000);
$city = clean_string($data['city'] ?? '', 120);
$gender = clean_string($data['gender'] ?? '', 30);
$gender = in_array($gender, ['Male','Female','Other'], true) ? $gender : (string)($me['gender'] ?? '');
$state = clean_string($data['state'] ?? '', 120);
$interests = clean_string($data['interests'] ?? '', 500);
$preferredGenderInput = (string)($data['preferred_gender_filter'] ?? ($me['preferred_gender_filter'] ?? 'Any'));
$preferredGender = in_array($preferredGenderInput, ['Male','Female','Other','Any'], true) ? $preferredGenderInput : 'Any';
$accountTypeInput = (string)($data['account_type'] ?? 'public');
$accountType = in_array($accountTypeInput, ['public', 'private'], true) ? $accountTypeInput : 'public';
$sensitiveInput = (string)($data['sensitive_content_preference'] ?? 'blur');
$sensitivePreference = in_array($sensitiveInput, ['blur', 'show'], true) ? $sensitiveInput : 'blur';

db()->prepare('UPDATE users SET name=?,bio=?,city=?,state=?,gender=?,interests=?,preferred_gender_filter=?,profile_completed=IF(?<>"" AND ?<>"",1,profile_completed) WHERE id=?')
    ->execute([$name,$bio,$city,$state,$gender,$interests,$preferredGender,$city,$gender,$me['id']]);
db()->prepare('UPDATE user_profiles SET bio = ?, city = ?, gender = ?, account_type = ?, sensitive_content_preference = ? WHERE user_id = ?')
    ->execute([$bio, $city, $gender, $accountType, $sensitivePreference, $me['id']]);
if (!empty($data['password'])) {
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash((string)$data['password'], PASSWORD_DEFAULT), $me['id']]);
}
json_response(['success' => true, 'message' => 'Profile updated']);
