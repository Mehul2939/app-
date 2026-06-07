<?php
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/wallet.php';
require_once __DIR__ . '/../helpers/social.php';

$data = array_merge(input(), $_POST);
$name = clean_string($data['name'] ?? '', 120);
$username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', clean_string($data['username'] ?? '', 60)));
$email = strtolower(clean_string($data['email'] ?? '', 160));
$mobile = clean_string($data['mobile'] ?? '', 30);
$password = (string)($data['password'] ?? '');
$dob = clean_string($data['date_of_birth'] ?? '', 20);
$confirmAdult = !empty($data['confirm_adult']);
$gender = clean_string($data['gender'] ?? '', 20);
$state = clean_string($data['state'] ?? '', 120);
$city = clean_string($data['city'] ?? '', 120);
$latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$interests = clean_string($data['interests'] ?? '', 500);
$preferredGenderInput = (string)($data['preferred_gender_filter'] ?? 'Any');
$preferredGender = in_array($preferredGenderInput, ['Male','Female','Other','Any'], true) ? $preferredGenderInput : 'Any';

if ($name === '' || $username === '' || ($email === '' && $mobile === '') || strlen($password) < 6 || $dob === '' || !in_array($gender, ['Male','Female','Other'], true) || $state === '' || $city === '') {
    json_response(['success' => false, 'message' => 'Name, username, email or mobile, password, gender, state, city and date of birth are required.'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Invalid email address'], 422);
}
$dobDate = DateTime::createFromFormat('Y-m-d', $dob);
$age = $dobDate ? $dobDate->diff(new DateTime('today'))->y : 0;
if (!$dobDate || $age < 18 || !$confirmAdult) {
    json_response(['success' => false, 'message' => 'This platform is available only to users aged 18 years or older.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $publicUserId = generate_public_user_id();
    if ($email === '') $email = strtolower($username . '.' . $publicUserId . '@mobile.myself.local');
    if ($mobile === '') $mobile = 'EMAIL-' . $publicUserId;
    $photoPath = null;
    if (!empty($_FILES['profile_photo']['tmp_name']) && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['profile_photo']['tmp_name']);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$mime]) || (int)$_FILES['profile_photo']['size'] > 5 * 1024 * 1024) throw new RuntimeException('Invalid profile photo');
        $photoPath = 'uploads/profiles/register_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], __DIR__ . '/../../' . $photoPath)) throw new RuntimeException('Photo upload failed');
    }
    $lat = $latitude === false ? null : $latitude;
    $lng = $longitude === false ? null : $longitude;
    $stmt = $pdo->prepare('INSERT INTO users (public_user_id,name,username,email,mobile,password,gender,state,city,latitude,longitude,account_created_at,last_active_at,profile_completed,bio,interests,profile_photo,preferred_gender_filter) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),1,?,?,?,?)');
    $stmt->execute([$publicUserId,$name,$username,$email,$mobile,password_hash($password,PASSWORD_DEFAULT),$gender,$state,$city,$lat,$lng,date('Y-m-d H:i:s'),'',$interests,$photoPath,$preferredGender]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_profiles (user_id,profile_photo,city,gender,date_of_birth,age_verified) VALUES (?,?,?,?,?,1)')->execute([$userId,$photoPath,$city,$gender,$dob]);
    ensure_wallet($userId);
    add_coins($userId, 100, 'admin_add', 'Welcome bonus');
    $pdo->commit();
    json_response(['success' => true, 'message' => 'Account created successfully']);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $message = $e instanceof PDOException ? 'Username, email or mobile already exists' : $e->getMessage();
    json_response(['success' => false, 'message' => $message], $e instanceof PDOException ? 409 : 422);
}
