<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
session_start();

// DB connection
require_once './backend/db.php';

function attendance_is_ajax_request(): bool
{
    return defined('ATTENDANCE_AJAX_REQUEST')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function attendance_json_response(bool $success, string $message, array $data = [], int $status = 200): void
{
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (attendance_is_ajax_request()) {
    ini_set('display_errors', '0');
} else {
    header('Content-Type: text/html; charset=UTF-8');
}

// Login check
if (!isset($_SESSION['emp_id'])) {
    if (attendance_is_ajax_request()) {
        attendance_json_response(false, 'Your session has expired. Please login again.', [], 401);
    }
    header("Location: login.php");
    exit;
}

$employeeId = $_SESSION['emp_id'];

// ===== BLOCKED CHECK =====
$checkBlocked = $conn->prepare("
    SELECT blocked, payment_block, block_3, block_4 
    FROM employees 
    WHERE employee_id=? 
    LIMIT 1
");
$checkBlocked->bind_param("s", $employeeId);
$checkBlocked->execute();
$blockedResult = $checkBlocked->get_result()->fetch_assoc();

if ($blockedResult) {

    // 🚫 Account fully blocked
    if ($blockedResult['blocked'] == 1) {
        if (attendance_is_ajax_request()) {
            attendance_json_response(false, 'Your account is blocked.', [], 403);
        }
        header("Location: blocked.php");
        exit;
    }

    // 💰 Payment blocked
    if ($blockedResult['payment_block'] == 1) {
        if (attendance_is_ajax_request()) {
            attendance_json_response(false, 'Your account is payment blocked.', [], 403);
        }
        header("Location: payment_block.php");
        exit;
    }

    // 🔒 Block 3 (example: attendance / feature lock)
    if ($blockedResult['block_3'] == 1) {
        if (attendance_is_ajax_request()) {
            attendance_json_response(false, 'This attendance feature is locked.', [], 403);
        }
        header("Location: account_status.php");
        exit;
    }

    // ⛔ Block 4 (example: login / system lock)
    if ($blockedResult['block_4'] == 1) {
        if (attendance_is_ajax_request()) {
            attendance_json_response(false, 'Your account access is locked.', [], 403);
        }
        header("Location: account_notice.php");
        exit;
    }
}



$employeeId   = (string) $_SESSION['emp_id'];
$employeeName = (string) ($_SESSION['emp_name'] ?? '');
$employeeIdSafe = htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8');
$month = date("Y-m");
$today = date("Y-m-d");

if (defined('RUN_MONTHLY_REPORT_SYNC')) {
    goto monthly_report_sync;
}




date_default_timezone_set("Asia/Kolkata");

$officeStart = "12:00:00";
$officeEnd   = "21:45:00";

$currentTime = date("H:i:s");

// Start se pehle OR End ke baad office closed
$isOfficeClosed = ($currentTime < $officeStart || $currentTime >= $officeEnd);

// End time ke baad hai ya nahi
$isAfterOfficeEnd = ($currentTime >= $officeEnd);

$displayOfficeStart = date("h:i A", strtotime($officeStart));
$displayOfficeEnd   = date("h:i A", strtotime($officeEnd));

$todayDate = date("Y-m-d");

$todayOpenTime = $todayDate . "T" . $officeStart;
$todayEndTime  = $todayDate . "T" . $officeEnd;

// Bonus settings (as requested)
$earlyBonusAmount = 25;   // ₹25 early check-in
$lateBonusAmount  = 00;   // ₹25 late check-out
$earlyBeforeTime = "06:02:00"; // correct format
$lateAfterTime   = "20:15:00"; // correct format


// ----------------- Fetch employee profile -----------------
$imgSQL = "SELECT profile_image, name FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($imgSQL);
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$empData = $stmt->get_result()->fetch_assoc();
$empImage = $empData['profile_image'] ?? "uploads/employees/default.png";
if(!file_exists($empImage)) $empImage = "uploads/employees/default.png";
$employeeNamee = $empData['name'] ?? $employeeName;

// ----------------- Fetch salary info -----------------
$salSQL = "SELECT salary_amount, salary_type FROM employee_salary WHERE employee_id = ?";
$stmt = $conn->prepare($salSQL);
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$sal = $stmt->get_result()->fetch_assoc();
$salaryPerHour = floatval($sal['salary_amount'] ?? 0);
$salaryType    = $sal['salary_type'] ?? "hourly";

// ----------------- Check today's attendance (single open session check) -----------------
$checkSessionSQL = "SELECT * FROM employee_attendance WHERE employee_id = ? AND created_date = ? ORDER BY id ASC";
$stmt = $conn->prepare($checkSessionSQL);
$stmt->bind_param("ss", $employeeId, $today);
$stmt->execute();
$result = $stmt->get_result();

$sessions = [];
$lastOpenSession = null;
$totalPastEarning = 0.0;

while($row = $result->fetch_assoc()){
    $sessions[] = $row;
    if(empty($row['check_out_time'])) {
        $lastOpenSession = $row;
    } else {
        $totalPastEarning += floatval($row['earning'] ?? 0);
    }
}

// ----------------- Helpers -----------------
function save_photo($employeeId){
    // Accept base64 field photo_data (as in original UI)
    if(isset($_POST['photo_data']) && !empty($_POST['photo_data'])){
        $data = $_POST['photo_data'];
        if(strpos($data, ',') !== false){
            list($meta, $data) = explode(',', $data, 2);
        }
        $data = base64_decode($data);
        $dir = 'uploads/attendance/';
        if(!is_dir($dir)){
            mkdir($dir, 0777, true);
        }
        $fileName = $dir . $employeeId . '_' . time() . '.png';
        file_put_contents($fileName, $data);
        return $fileName;
    }
    return null;
}

function back_with_msg($msg){
    if (attendance_is_ajax_request()) {
        attendance_json_response(false, $msg, [], 422);
    }
    header("Location: ckinandckout.php?msg=" . urlencode($msg));
    exit;
}

// ----------------- CHECK-IN -----------------
if(isset($_POST['checkin'])){

    if($lastOpenSession){
        back_with_msg("You already checked in. Please checkout first.");
    }

    $photoPath = save_photo($employeeId);
    if(!$photoPath){
        back_with_msg("Photo is required for Check In. Please capture your photo.");
    }

    $attendanceDate = date("Y-m-d");
    $monthValue     = date("Y-m");
    $now            = date("Y-m-d H:i:s");

    // EARLY BONUS LOGIC (use $earlyBeforeTime)
    $currentTime = date("H:i:s");
    $earlyBonus = 0;

    // Give early bonus if check-in time is <= earlyBeforeTime (and ensure not duplicate)
    if($currentTime <= $earlyBeforeTime){
        // Check if bonus already exists for today for this employee
        $checkBonusSQL = "SELECT SUM(early_checkin_bonus) as total_early FROM early_late_bonus WHERE employee_id = ? AND created_date = ?";
        $stmtB = $conn->prepare($checkBonusSQL);
        $stmtB->bind_param("ss", $employeeId, $attendanceDate);
        $stmtB->execute();
        $brow = $stmtB->get_result()->fetch_assoc();
        if(floatval($brow['total_early']) == 0){
            $earlyBonus = $earlyBonusAmount;
        }
    }

    // Insert attendance row
    $sql = "INSERT INTO employee_attendance 
            (employee_id, month, check_in_time, created_date, advance_payment, photo_path, early_checkin_bonus) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    // advance_payment default 0
    $adv = 0;
  $stmt->bind_param("ssssdsd", 
    $employeeId,
    $monthValue,
    $now,
    $attendanceDate,
    $adv,        // numeric (double)
    $photoPath,
    $earlyBonus
);

    
    $stmt->execute();
    $attendanceId = $stmt->insert_id;

    // Also insert/update bonus table
    $sqlBonus = "INSERT INTO early_late_bonus (employee_id, month, check_in_time, early_checkin_bonus, created_date) VALUES (?, ?, ?, ?, ?)";
    $stmtBonus = $conn->prepare($sqlBonus);
    $stmtBonus->bind_param("sssds", $employeeId, $monthValue, $now, $earlyBonus, $attendanceDate);
    $stmtBonus->execute();
    // Fetch employee WhatsApp number from DB
$employeePhone = '';
$phoneStmt = $conn->prepare("SELECT contact FROM employees WHERE employee_id = ? LIMIT 1");
if ($phoneStmt) {
    $phoneStmt->bind_param("s", $employeeId);
    $phoneStmt->execute();
    $phoneRow = $phoneStmt->get_result()->fetch_assoc();
    $employeePhone = $phoneRow['contact'] ?? '';
    $phoneStmt->close();
}

if (!empty($employeePhone)) {

    $displayName = $employeeNamee;
    $cardNumber = "";

    // Card Number extract (Only digits)
    if (preg_match('/(\d+)/', $displayName, $match)) {
        $cardNumber = $match[1];
    }

    // TIME FORMAT
    $time = date("h:i A");

    // ============================================
    // WC EMPLOYEE (HAS CARD NUMBER IN NAME)
    // ============================================
    if (!empty($cardNumber)) {

         $checkinMsg =
        "🍁 प्लम्बर एसोसिएशन जिंदाबाद 🍁\n\n"
        . "नाम > *$displayName जी*\n"
        . "कार्ड नंबर > *$cardNumber*\n"
        . "अटेंडेंस लगाने का समय > *$time*\n\n"
        . "🌻 आपकी अटेंडेंस सफलतापूर्वक लग गई है। 🌻\n\n"
        
      . "💃🏻 *रिक्वायरमेंट स्लिप* जिसमे पार्टी का नाम, मोबाइल नंबर और उसकी जरूरत का सामान लिखकर डालते ही "
      . "आपके अटेंडेंस पोर्टल अकाउंट में *25 रुपए* का बोनस ट्रांसफर कर दिया जाएगा! "
      . "और आपकी फोटो भी अलग से डिस्प्ले पर दिखाई जाएगी!\n\n"
      . "💃🏻 *GST BILL* साइट पर रिक्वायरमेंट स्लिप का माल *जयपुर जिले के किसी भी दुकान या शोरूम* से आते ही "
      . "उस बिल की फोटो खींच कर डालने पर भी अलग से *25 रुपए* का बोनस तुरंत ट्रांसफर कर दिया जाएगा! "
      . "और आपकी फोटो भी अलग से डिस्प्ले पर दिखाई जाएगी!\n\n"
      . "🍁 शाम 6 से 9 बजे के बीच Attendance लगाना न भूलें। 🍁\n\n"
      . "🌹 मुकेश कुमार सैनी 🌹\n"
      . "मुख्य संरक्षक\n"
      . "प्लंबर एसोसिएशन\n"
      . "📞 9928221039 📞\n"
       . "https://archaicfacility.in/at/attendance_app/ckinandckout.php";
    }

    // ============================================
    // NORMAL EMPLOYEE (NO CARD NUMBER)
    // ============================================अमावस कितनी तारीख की रखी ओर आपने छुट्टी कब की रखी है कृपया मेसेज करके बताना ok।
    else {
        $checkinMsg =
        "🍁 प्लम्बर एसोसिएशन जिंदाबाद 🍁\n\n"
        . "नाम > $displayName जी\n"
        . "अटेंडेंस लगाने का समय > $time\n\n"
        . "🌼 आपका Check-In सफलतापूर्वक दर्ज हो गया है।\n"
        . "कृपया कार्य के नियमों का पालन करें।\n"
        . "शाम 5 से 9 बजे के बीच Attendance लगाना न भूलें। 🌙\n\n"
        . "🌹 मुकेश कुमार सैनी 🌹\n"
        . "मुख्य संरक्षक\n"
        . "प्लंबर एसोसिएशन\n"
        . "📞 9928221039";
    }

    // SEND MESSAGE
    sendWhatsAppMessage($employeePhone, $checkinMsg);
}




    if (attendance_is_ajax_request()) {
        attendance_json_response(true, "Check-In successful.", [
            'action' => 'checkin',
            'attendance_id' => $attendanceId,
        ]);
    }
    header("Location: ckinandckout.php");
    exit;
}

// ----------------- CHECK-OUT -----------------
// ----------------- CHECK-OUT -----------------
if(isset($_POST['checkout'])){

    if(!$lastOpenSession){
        back_with_msg("You need to check in first.");
    }

    $checkoutPhoto = save_photo($employeeId);
    if(!$checkoutPhoto){
        back_with_msg("Photo is required for Check Out. Please capture your photo.");
    }

    $checkin = strtotime($lastOpenSession['check_in_time']);
    $now     = time();
    $hours   = ($now - $checkin) / 3600;
    $checkoutTime = date("Y-m-d H:i:s");
    $currentTime  = date("H:i:s");

    // Normal earning
    $earning = ($salaryType == "hourly") ? round($hours * $salaryPerHour, 2) : 0;

    // LATE CHECKOUT BONUS
    $lateBonus = 0;
    if($currentTime >= $lateAfterTime){
        // check if late bonus already given today
        $checkLateSQL = "SELECT SUM(late_checkout_bonus) as total_late FROM early_late_bonus WHERE employee_id = ? AND created_date = ?";
        $stmtLate = $conn->prepare($checkLateSQL);
        $stmtLate->bind_param("ss", $employeeId, $today);
        $stmtLate->execute();
        $lateRow = $stmtLate->get_result()->fetch_assoc();
        if(floatval($lateRow['total_late']) == 0){
            $lateBonus = $lateBonusAmount;
        }
    }

    // Earning only (bonus stored separately)
    $totalEarning = $earning;

    // Advance settlement
    $oldAdv = floatval($lastOpenSession['advance_payment'] ?? 0);
    $sett   = floatval($lastOpenSession['settled_amount'] ?? 0);

    $cutAmount = min($totalEarning, $oldAdv);
    $newSett   = round($sett + $cutAmount, 2);
    $newAdv    = round($oldAdv - $cutAmount, 2);

    // Update attendance row
    $sql = "UPDATE employee_attendance 
            SET check_out_time=?, earning=?, settled_amount=?, advance_payment=?, 
                checkout_photo=?, late_checkout_bonus=?
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $lastOpenSessionId = intval($lastOpenSession['id']);
    $stmt->bind_param("sdddsdi",
        $checkoutTime,
        $totalEarning,
        $newSett,
        $newAdv,
        $checkoutPhoto,
        $lateBonus,
        $lastOpenSessionId
    );
    $stmt->execute();


    // Update early/late bonus table
    $sqlCheckBonusRow = "SELECT id FROM early_late_bonus WHERE employee_id = ? AND created_date = ? LIMIT 1";
    $stmtC = $conn->prepare($sqlCheckBonusRow);
    $stmtC->bind_param("ss", $employeeId, $today);
    $stmtC->execute();
    $resC = $stmtC->get_result()->fetch_assoc();

    if($resC && isset($resC['id'])){
        $bonusRowId = intval($resC['id']);
        $sqlBonusUpdate = "UPDATE early_late_bonus SET check_out_time = ?, late_checkout_bonus = ? WHERE id = ?";
        $stmtUpd = $conn->prepare($sqlBonusUpdate);
        $stmtUpd->bind_param("sdi", $checkoutTime, $lateBonus, $bonusRowId);
        $stmtUpd->execute();
    } else {
        $monthValue = date("Y-m");
        $sqlBonusInsert = "INSERT INTO early_late_bonus (employee_id, month, check_in_time, check_out_time, late_checkout_bonus, created_date)
                           VALUES (?, ?, ?, ?, ?, ?)";
        $stmtIns = $conn->prepare($sqlBonusInsert);
        $ci = $lastOpenSession['check_in_time'] ?? date("Y-m-d H:i:s");
        $stmtIns->bind_param("ssssds", $employeeId, $monthValue, $ci, $checkoutTime, $lateBonus, $today);
        $stmtIns->execute();
    }


// ============= SEND WHATSAPP CHECK-OUT MESSAGE =============
$empSQL = "SELECT name, contact FROM employees WHERE employee_id = ?";
$st = $conn->prepare($empSQL);
$st->bind_param("s", $employeeId);
$st->execute();
$emp = $st->get_result()->fetch_assoc();

if($emp && $emp['contact']){
    $name = $emp['name'];
    $mobile = preg_replace('/[^0-9]/', '', $emp['contact']);
    $mobile = "91" . ltrim($mobile, "0");

    // ====== WC & WB MEMBER CHECK (same message for both) ======
    if (preg_match('/\bW[CB]\s*[-:]?\s*\d+/i', $name)) {

        $bonusAmount = 25; // fixed bonus

        // SAME MESSAGE for WC + WB
        $message = "🍁 प्लंबर एसोसिएशन जिंदाबाद 🍁\n\n"
                 . "नाम > $name जी\n"
                 . "कार्ड नंबर > $employeeId\n"
                 . "चेक आउट टाइम > " . date("h:i A", strtotime($checkoutTime)) . "\n"
                 . "आज की कमाई > ₹" . $totalEarning . "\n"
                 . "बोनस > आज दिनांक " . date("j F Y") . " का $bonusAmount रुपये बोनस सुनिश्चित हो गया है जो आपको अगले महीने फोनपे या पेटीएम के माध्यम से दे दिया जाएगा ! 💃🏻🕺🏻🍁👍🏻\n\n"
                 . "🌹 मुकेश कुमार सैनी 🌹\n"
                 . "मुख्य संरक्षक\n"
                 . "प्लंबर एसोसिएशन\n"
                 . "9928221039\n"
                  . "https://archaicfacility.in/at/attendance_app/ckinandckout.php";

    } 
    else {

        // NORMAL MESSAGE
        $message = "👋 Hello $name\n"
                 . "Checkout Successful!\n"
                 . "⏱ Time: " . date("h:i A", strtotime($checkoutTime)) . "\n"
                 . "💰 Earning Today: ₹" . $totalEarning . "\n"
                 . "Thank you for your work 🙌";
    }

    // ====== SEND API MESSAGE (ONLY ONCE) ======
   $api = "http://api.iconicsolution.co.in/wapp/v2/api/send?"
     . "apikey=0a8beeae2b3a43b08d49a44c3ac13e59"
     . "&mobile=" . urlencode($mobile)
     . "&msg=" . urlencode($message);

    @file_get_contents($api);
}




    if (attendance_is_ajax_request()) {
        attendance_json_response(true, "Check-Out successful.", [
            'action' => 'checkout',
            'earning' => $totalEarning,
            'total_today_earning' => round($totalPastEarning + $totalEarning, 2),
        ]);
    }
    header("Location: ckinandckout.php?msg=Checkout%20Success");
    exit;
}

// ----------------- AUTO CHECK-OUT at 20:00 if not done -----------------
$autoTime = "21:00:00";
if($lastOpenSession && empty($lastOpenSession['check_out_time']) && date("H:i:s") >= $autoTime){
    $checkinTime = strtotime($lastOpenSession['check_in_time']);
    $todayDate = date("Y-m-d");
    $nowTime = strtotime($todayDate . ' ' . $autoTime);

    $hoursWorked = ($nowTime - $checkinTime) / 3600;
    $checkoutTime = date("Y-m-d H:i:s", $nowTime);
    $earning = ($salaryType == "hourly") ? round($hoursWorked * $salaryPerHour, 2) : 0;

    // Update attendance
    $sql = "UPDATE employee_attendance SET check_out_time=?, earning=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdi", $checkoutTime, $earning, $lastOpenSession['id']);
    $stmt->execute();

    // late bonus check for auto checkout
    $lateBonus = ($autoTime >= $lateAfterTime) ? $lateBonusAmount : 0;

    // Update/insert bonus row
    $sqlCheckBonusRow = "SELECT id FROM early_late_bonus WHERE employee_id = ? AND created_date = ? LIMIT 1";
    $stmtC = $conn->prepare($sqlCheckBonusRow);
    $stmtC->bind_param("ss", $employeeId, $today);
    $stmtC->execute();
    $resC = $stmtC->get_result()->fetch_assoc();

    if($resC && isset($resC['id'])){
        $bonusRowId = intval($resC['id']);
        $sqlUpd = "UPDATE early_late_bonus SET check_out_time = ?, late_checkout_bonus = ? WHERE id = ?";
        $stmtUpd = $conn->prepare($sqlUpd);
        $stmtUpd->bind_param("sdi", $checkoutTime, $lateBonus, $bonusRowId);
        $stmtUpd->execute();
    } else {
        $monthValue = date("Y-m");
        $ci = $lastOpenSession['check_in_time'] ?? date("Y-m-d H:i:s");
        $sqlIns = "INSERT INTO early_late_bonus (employee_id, month, check_in_time, check_out_time, late_checkout_bonus, created_date)
                   VALUES (?, ?, ?, ?, ?, ?)";
        $stmtIns = $conn->prepare($sqlIns);
        $stmtIns->bind_param("ssssds", $employeeId, $monthValue, $ci, $checkoutTime, $lateBonus, $today);
        $stmtIns->execute();
    }

    // refresh sessions after auto checkout (optional) — we'll reload page
    header("Location: ckinandckout.php");
    exit;
}

// ----------------- Active Users -----------------
// ----------------- Active Users with Time -----------------
$activeSQL = "
    SELECT 
        e.name AS name,
        e.profile_image,
        a.check_in_time
    FROM employee_attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    WHERE a.created_date = ? 
      AND a.check_out_time IS NULL
";
$stmtActive = $conn->prepare($activeSQL);
$stmtActive->bind_param("s", $today);
$stmtActive->execute();
$activeResult = $stmtActive->get_result();

$activeUsers = [];
while($row = $activeResult->fetch_assoc()){
    $img = $row['profile_image'] ?: "uploads/employees/default.png";
    if(!file_exists($img)) $img = "uploads/employees/default.png";

    $ciTime = strtotime($row['check_in_time']);
    $diff   = time() - $ciTime;
    $hours  = floor($diff / 3600);
    $mins   = floor(($diff % 3600) / 60);

    $activeUsers[] = [
        'name'  => $row['name'],
        'img'   => $img,
        'time'  => sprintf("%02d:%02d hrs", $hours, $mins)
    ];
}




// ----------------- Sessions (with LEFT JOIN to include bonus columns) -----------------
// Added COLLATE to avoid collation mix error if any
$sessionsSQL = "
    SELECT a.*,
           IFNULL(b.early_checkin_bonus,0) AS early_checkin_bonus,
           IFNULL(b.late_checkout_bonus,0) AS late_checkout_bonus
    FROM employee_attendance a
    LEFT JOIN early_late_bonus b
      ON a.employee_id COLLATE utf8mb4_general_ci = b.employee_id COLLATE utf8mb4_general_ci
     AND a.created_date COLLATE utf8mb4_general_ci = b.created_date COLLATE utf8mb4_general_ci
    WHERE a.employee_id COLLATE utf8mb4_general_ci = ?
      AND MONTH(a.created_date) = MONTH(?)
    ORDER BY a.created_date DESC, a.id DESC
";
$stmtSessions = $conn->prepare($sessionsSQL);
$stmtSessions->bind_param("ss", $employeeId, $today);
$stmtSessions->execute();
$resSessions = $stmtSessions->get_result();

$sessions = [];
$totalPastEarning = 0.0;
$earlyTodayBonus = 0.0;
$lateTodayBonus = 0.0;
$lastOpenSession = null;

while($r = $resSessions->fetch_assoc()){
    $r['earning'] = floatval($r['earning'] ?? 0);
    $r['early_checkin_bonus'] = floatval($r['early_checkin_bonus'] ?? 0);
    $r['late_checkout_bonus']  = floatval($r['late_checkout_bonus'] ?? 0);

    $sessions[] = $r;
    $totalPastEarning += $r['earning'];

    if($r['created_date'] == $today){
        $earlyTodayBonus += $r['early_checkin_bonus'];
        $lateTodayBonus  += $r['late_checkout_bonus'];
        if(empty($r['check_out_time'])) $lastOpenSession = $r;
    }
}

// ---- override using early_late_bonus table (month wise 06:00–06:02) ----
$stmtEarlyMonth = $conn->prepare("
    SELECT 
        COUNT(*) AS total_days,
        IFNULL(SUM(early_checkin_bonus),0) AS total_amount
    FROM early_late_bonus
    WHERE employee_id = ?
      AND MONTH(created_date) = MONTH(?)
      AND TIME(check_in_time) BETWEEN '06:00:00' AND '06:02:59'
");
$stmtEarlyMonth->bind_param("ss", $employeeId, $today);
$stmtEarlyMonth->execute();
$resM = $stmtEarlyMonth->get_result()->fetch_assoc();

// override values used by frontend
$earlyTodayBonus = floatval($resM['total_amount'] ?? 0); // ₹
$lateTodayBonus  = intval($resM['total_days'] ?? 0);    // days

// compute totalTodayEarning used in UI (no change)
$totalTodayEarning = 0.0;
foreach($sessions as $s){
    if($s['created_date'] == $today){
        $totalTodayEarning += floatval($s['earning']) + floatval($s['early_checkin_bonus']) + floatval($s['late_checkout_bonus']);
    }
}

$bonusAdded = ($earlyTodayBonus + $lateTodayBonus) > 0 ? true : false;


?>


<?php
// Fetch all currently checked-in employees
$activeSQL = "
    SELECT 
        e.name, 
        e.profile_image,
        a.check_in_time,
        a.created_date
    FROM employee_attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    WHERE a.created_date = ? 
      AND a.check_out_time IS NULL
";
$stmtActive = $conn->prepare($activeSQL);
$stmtActive->bind_param("s", $today);
$stmtActive->execute();
$activeResult = $stmtActive->get_result();

$activeUsers = [];
while($row = $activeResult->fetch_assoc()){

    $img = !empty($row['profile_image']) ? $row['profile_image'] : "uploads/employees/default.png";
    if(!file_exists($img)) {
        $img = "uploads/employees/default.png";
    }

    // Format Check-in Display Time ⏱
    $formattedTime = date("h:i A", strtotime($row['check_in_time'])); 

    $activeUsers[] = [
        'name'  => $row['name'],
        'img'   => $img,
        'time'  => $formattedTime // SHOW CHECK-IN TIME
    ];
}


// Assume $conn is your mysqli connection
$employeeId = $_SESSION['emp_id'];

// ================= Fetch GST + PVC data (EMPLOYEE WISE) =================
$sql = "
    SELECT 
        'GST' AS entry_type,
        g.id,
        g.gst_bill_no AS bill_no,
        g.amount,
        g.customer_name,
        g.customer_number,
        g.bonus,
        g.remark,
        g.status,
        g.created_at,
        e.employee_id,
        e.name AS employee_name,
        e.contact,
        e.profile_image
    FROM gstbill g
    JOIN employees e 
        ON g.emp_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci
    WHERE g.emp_id = ?

    UNION ALL

    SELECT 
        'PVC' AS entry_type,
        p.id,
        p.slip_no AS bill_no,
        p.amount,
        p.customer_name,
        p.customer_number,
        p.bonus,
        p.remark,
        p.status,
        p.created_at,
        e.employee_id,
        e.name AS employee_name,
        e.contact,
        e.profile_image
    FROM pvc_cp_fitting p
    JOIN employees e 
        ON p.emp_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci
    WHERE p.emp_id = ?

    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $employeeId, $employeeId);
$stmt->execute();
$result = $stmt->get_result();

$businessData = [];

while ($row = $result->fetch_assoc()) {

    // safety fallback for image
    if (empty($row['profile_image']) || !file_exists($row['profile_image'])) {
        $row['profile_image'] = 'uploads/employees/default.png';
    }

    $businessData[] = $row;
}


// ================== Echo the data ==================

?>


<?php
// Date & employee info
$month = date("Y-m");
$today = date("Y-m-d");

// ----------------- Sessions (with LEFT JOIN to include bonus columns) -----------------
$sessionsSQL = "
    SELECT 
        a.*,
        DATE(a.created_date) AS onlydate,
        IFNULL(b.early_checkin_bonus,0) AS early_checkin_bonus,
        IFNULL(b.late_checkout_bonus,0) AS late_checkout_bonus
    FROM employee_attendance a
    LEFT JOIN early_late_bonus b
      ON a.employee_id = b.employee_id
     AND DATE(a.created_date) = DATE(b.created_date)
    WHERE a.employee_id = ?
      AND DATE_FORMAT(a.created_date, '%Y-%m') = ?
    ORDER BY a.created_date DESC, a.id DESC
";
$stmtSessions = $conn->prepare($sessionsSQL);
$stmtSessions->bind_param("ss", $employeeId, $month);
$stmtSessions->execute();
$resSessions = $stmtSessions->get_result();

$sessions = [];
$lastOpenSession = null;
$totalPastEarning = 0.0;
$earlyTodayBonus = 0.0;
$lateTodayBonus = 0.0;

while($r = $resSessions->fetch_assoc()){
    $r['earning'] = floatval($r['earning'] ?? 0);
    $r['early_checkin_bonus'] = floatval($r['early_checkin_bonus'] ?? 0);
    $r['late_checkout_bonus']  = floatval($r['late_checkout_bonus'] ?? 0);

    $sessions[] = $r;

    $totalPastEarning += $r['earning'];

    // Today Bonus Correct Fetch
    if($r['onlydate'] == $today){
        $earlyTodayBonus += $r['early_checkin_bonus'];
        $lateTodayBonus  += $r['late_checkout_bonus'];
        if(empty($r['check_out_time'])) $lastOpenSession = $r;
    }
}

// ----------------- Define today sessions for UI -----------------
$todaySessions = array_filter($sessions, function($s) use ($today){
    return $s['onlydate'] == $today;
});

// ----------------- Calculate totals correctly -----------------
$totalMonthEarning = 0.0;
foreach($sessions as $s){
    $totalMonthEarning += (
        $s['earning'] +
        $s['early_checkin_bonus'] +
        $s['late_checkout_bonus']
    );
}

$totalTodayEarning = 0.0;
foreach($todaySessions as $s){
    $totalTodayEarning += (
        $s['earning'] +
        $s['early_checkin_bonus'] +
        $s['late_checkout_bonus']
    );
}
function sendWhatsAppMessage($mobile, $message){
    $apiKey = "0a8beeae2b3a43b08d49a44c3ac13e59";
    $mobile = preg_replace('/[^0-9]/', '', $mobile); // clean number
    $msg    = urlencode($message);
    
    $url = "http://api.iconicsolution.co.in/wapp/v2/api/send?apikey=$apiKey&mobile=$mobile&msg=$msg";

    // Use cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    // Optional: log response
    $resArr = json_decode($response, true);
    if(isset($resArr['status']) && $resArr['status'] == 'success'){
        return true;
    } else {
        error_log("WhatsApp API Error: " . ($resArr['errormsg'] ?? 'Unknown error'));
        return false;
    }
}

?>
<?php
// ================= Fetch last 24 hours ACTIVE EMPLOYEES (PVC + GST) =================

$sql = "
    SELECT 
        e.id,
        e.name AS employee_name,
        e.profile_image,
        MAX(t.activity_time) AS last_activity
    FROM employees e
    INNER JOIN (
        -- PVC entries
        SELECT emp_id, created_at AS activity_time
        FROM pvc_cp_fitting
        WHERE created_at >= (NOW() - INTERVAL 24 HOUR)

        UNION ALL

        -- GST entries
        SELECT emp_id, created_at AS activity_time
        FROM gstbill
        WHERE created_at >= (NOW() - INTERVAL 24 HOUR)
    ) t 
        ON t.emp_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci
    GROUP BY e.id, e.name, e.profile_image
    ORDER BY last_activity DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$profiles = [];

while ($row = $result->fetch_assoc()) {

    $img = $row['profile_image'];

    // ✅ Safety fallback
    if (empty($img) || !file_exists($img)) {
        $img = 'uploads/employees/default.png';
    }

    $profiles[] = [
        'name' => $row['employee_name'],
        'image' => $img,
        'time' => $row['last_activity']
    ];
}

$today = date('Y-m-d');

$sql = "
SELECT *
FROM company_sliders
WHERE start_date <= '$today'
AND end_date >= '$today'
ORDER BY priority DESC, RAND()
LIMIT 1
";

$res = $conn->query($sql);
$slider = $res ? $res->fetch_assoc() : null;

$images = [];
if($slider){
    $images = json_decode($slider['slider_images'], true);
}

// Employee ID (Change as needed)
$emp_id = $employeeId;

// Fetch all data for employee and total bonus
$sql = "
SELECT `id`, `emp_id`, `slip_no`, `amount`, `customer_name`, 
       `customer_number`, `bonus`, `remark`, `type`, `status`, `created_at`,
       (SELECT SUM(`bonus`) FROM `gst_chori_roko` WHERE emp_id = '$emp_id') AS totalBonus
FROM `gst_chori_roko`
WHERE emp_id = '$emp_id'
ORDER BY `created_at` DESC
";

$result = mysqli_query($conn, $sql);

$data = [];
$chschori = 0; // total bonus

if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
        $chschori = $row['totalBonus']; // same total for all rows
    }
}

$totalEntries = count($data);      // Number of entries
$totalBonus = !empty($chschori) ? $chschori : 500;
        // Total bonus
       

$employeeId = $_SESSION['emp_id'] ?? 0;

$result = $conn->query("
SELECT message, created_at
FROM impmessage
WHERE emp_id='$employeeId' OR emp_id='0'
ORDER BY id DESC
");

$count = 0;

if($result){
$count = $result->num_rows;
}




// Keep the connection open: more PHP/SQL sections below still use $conn.

?>



<!DOCTYPE html> 
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Attendance — Check In / Check Out</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link href="ckinandckout.css" rel="stylesheet">
<style>.summary-box {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: #fff;
}

/* Total Amount */
.total-text {
    font-size: 20px;
}

.amount {
    font-size: 28px;
    color: #00ffcc;
}

/* Profit */
.profit-box {
    font-size: 16px;
    background: linear-gradient(45deg, #ff9800, #ff5722);
    padding: 8px;
    border-radius: 10px;
    color: #fff;
    font-weight: bold;
}

/* Mahila Bachat */
.mahila-box {
    font-size: 16px;
    background: linear-gradient(45deg, #00c6ff, #0072ff);
    padding: 8px;
    border-radius: 10px;
    color: #fff;
    font-weight: bold;
}

/* Mobile Responsive */
@media (max-width: 576px) {
    .total-text {
        font-size: 26px;
    }

    .amount {
        font-size: 32px;
    }

    .profit-box,
    .mahila-box {
        font-size: 24px;
    }
}</style>

</head>
<body>


        <!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container-fluid">
    <button class="btn btn-dark d-lg-none me-2" id="sidebarToggle">&#9776;</button>
    <a class="navbar-brand">Dashboard</a>
    <ul class="navbar-nav ms-auto">
      <!--<li class="nav-item"><a class="nav-link" href="https://otieu.com/4/10437432">Profile</a></li>-->
    </ul>
  </div>
</nav>

<!-- Sidebar -->
<ul class="sidenav list-unstyled" id="sidebar">
    <li>
        <a href="ckinandckout.php">Home</a>
        <a href="empreport.php?employeeId=<?= $employeeIdSafe ?>&month=<?= $month ?>">History</a>
        <a href="Profile.php?id=<?= $employeeIdSafe ?>">Profile</a>
    </li>  
</ul>
<div class="active-users-section" style="margin-top:20px; width:100%;">
    <div style="font-weight:700; font-size:10px; margin-bottom:8px; color:#1a202c; letter-spacing:0.5px; text-transform:uppercase;">
        🇮🇳 Currently Checked-In Employees 🇮🇳
    </div>
    <div style="display:flex; overflow-x:auto; gap:6px; padding:6px; border-radius:20px; background:linear-gradient(120deg, #fffaf0, #ffeedd); border:2px solid #ff9933; width:100%;">
        <?php foreach($activeUsers as $index => $user): ?>
            <div style="flex:0 0 auto; text-align:center; width:90px; cursor:pointer; transition: transform 0.3s;" 
                 onclick="openUserModal(<?= $index ?>)"
                 onmouseover="this.style.transform='scale(1.1)'" 
                 onmouseout="this.style.transform='scale(1)'">
                
                <!-- Avatar -->
                <div style="width:75px; height:75px; margin:auto; border-radius:50%; display:flex; align-items:center; justify-content:center; 
                            background: linear-gradient(135deg, #FF9933, #138808); 
                            box-shadow: 0 6px 18px rgba(0,0,0,0.2); transition: all 0.3s;">
                    <img src="<?= htmlspecialchars($user['img']) ?>" style="width:65px; height:65px; border-radius:50%; object-fit:cover; border:3px solid #fff;">
                </div>

                <!-- User Name with subtle Indian Flag gradient -->
                <div style="
                    font-size:14px; 
                    margin-top:6px; 
                    font-weight:700; 
                    white-space:nowrap; 
                    overflow:hidden; 
                    text-overflow:ellipsis;
                    background: linear-gradient(135deg, #FF9933, #138808);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    text-shadow: 1px 1px 2px rgba(0,0,0,0.4);
                ">
                    <?= htmlspecialchars($user['name']) ?>
                </div>

                <!-- Checked-in Time -->
                <div style="font-size:12px; margin-top:3px; color:#FF9933; font-weight:bold;">
                    <?= isset($user['time']) ? $user['time'] : '00:00 hrs' ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
</div>





<div id="overlay"></div>
<div class="wrap">
  <div class="topbar" style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 20px;
    margin-top:20px; /* added top margin */
    background: linear-gradient(135deg, #ff8a5b, #ff5e7a);
    color:#fff;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
    font-family: 'Segoe UI', sans-serif;
">

    <div class="brand" style="display:flex; flex-direction:column;">
       <div id="pageTitle" class="page-title" style="
    font-size:18px; 
    font-weight:700;
    letter-spacing:0.5px;
    cursor:pointer;
">
    Attendance System
</div>



        <div class="slogan" style="
            font-size:12px; 
            font-weight:500;
            color:rgba(255,255,255,0.85);
            margin-top:2px;
            letter-spacing:0.3px;
        ">
            Check In / Check Out — Secure & Easy
        </div>
    </div>

    <div class="small-muted" style="
        font-size:12px; 
        font-weight:500;
        color:rgba(255,255,255,0.9);
    ">
        Today: <?= date("d M, Y") ?>
    </div>
</div>




<!-- LEFT COLUMN: Profile -->
<div class="col-left" style="flex:1; min-width:250px; text-align:center;">
    <div class="profile"
         ondblclick="window.location.href='contractor_jodo_view.php?id=<?= urlencode($employeeId) ?>'"
         style="
        text-align:center; 
        padding:20px; 
        background: linear-gradient(145deg, #FF9933, #FFFFFF, #138808);
        border-radius:24px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        transition: transform 0.3s, box-shadow 0.3s;
        cursor:pointer;
    ">
        <!-- Profile Image -->
        <img src="<?= htmlspecialchars($empImage) ?>" 
             alt="Profile" 
             style="
                width:160px; 
                height:160px; 
                border-radius:50%; 
                object-fit:cover; 
                border:4px solid #fff; 
                box-shadow:0 8px 20px rgba(0,0,0,0.25); 
                margin-bottom:16px;
                transition: transform 0.3s, box-shadow 0.3s;
             "
             onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 12px 28px rgba(0,0,0,0.3)'" 
             onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.25)'"
        >

        <!-- Employee Name -->
        <h3 style="
            display:inline-block;
            padding:6px 14px;
            border-radius:12px;
            background: linear-gradient(135deg, #FF9933, #138808);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight:900; 
            font-size:24px;
            margin:0 0 8px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.4);
        ">
            <?= htmlspecialchars($employeeNamee) ?>
        </h3>

        <!-- Employee ID -->
        <p class="small-muted" style="
            color:#374151; 
            font-size:14px; 
            margin:0;
            letter-spacing:0.5px;
            font-weight:500;
        ">
            Employee ID: <?= htmlspecialchars($employeeId) ?>
        </p>
    </div>
</div>

<!--<div class="container mt-5">-->
<!--  <div class="card text-dark border-0 shadow-lg" style="background: linear-gradient(135deg, #FFF3CD, #FFE5B4);">-->
    
<!--    <div class="card-header text-white text-center fs-5 fw-bold" style="background: linear-gradient(90deg, #FF6B6B, #FFD93D);">-->
<!--      📢 महत्वपूर्ण Notice-->
<!--    </div>-->

<!--    <div class="card-body">-->
      
<!--      <p class="card-text">-->
<!--        सभी Contractors को सूचित किया जाता है कि वे अपने जान-पहचान के Contractors को भी -->
<!--        inform कर दें कि वे <strong>31 March 2026</strong> से पहले अपना -->
<!--        <strong>Registration</strong> अवश्य करवा लें।-->
<!--      </p>-->

<!--      <p class="card-text text-danger fw-bold">-->
<!--        31 March 2026 के बाद Free Registration की सुविधा उपलब्ध नहीं होगी।-->
<!--      </p>-->

<!--      <hr class="border-dark">-->

<!--      <p class="card-text">-->
<!--        अतः सभी से request है कि निर्धारित date से पहले अपना -->
<!--        <strong>Registration complete</strong> कर लें। <br>-->
<!--        धन्यवाद।-->
<!--      </p>-->

<!--    </div>-->
<!--  </div>-->
<!--</div>-->

<?php if($count > 0){ ?>

<style>

.notice-wrapper{
background:linear-gradient(135deg,#fff5f5,#ffecec);
border:2px solid #ffb3b3;
border-radius:14px;
padding:18px;
margin-bottom:22px;
box-shadow:0 8px 20px rgba(0,0,0,0.08);
}

.notice-title{
font-weight:900;
font-size:18px;
color:#b30000;
margin-bottom:14px;
letter-spacing:.5px;
display:flex;
align-items:center;
gap:6px;
}

.notice-card{
border-left:6px solid #dc3545;
border-radius:8px;
background:white;
padding:12px 14px;
margin-bottom:10px;
font-size:15px;
font-weight:700;
color:#212529;
box-shadow:0 3px 8px rgba(0,0,0,0.05);
transition:0.25s;
}

.notice-card:hover{
background:#fff1f1;
transform:translateX(6px);
box-shadow:0 6px 14px rgba(0,0,0,0.12);
}

.notice-card::before{
content:"📢 ";
font-size:16px;
}

</style>

<div class="container notice-wrapper">

<div class="notice-title">
🚨 महत्वपूर्ण सूचना
</div>

<div class="<?php if($count>3) echo 'overflow-auto'; ?>" 
style="<?php if($count>3) echo 'max-height:220px;'; ?>">

<?php while($row = $result->fetch_assoc()){ ?>

<div class="notice-card">
<strong><?php echo htmlspecialchars($row['message']); ?></strong>
</div>

<?php } ?>

</div>

</div>

<?php } ?>

<!--udhar?-->
<div class="contact-btn" id="udhar_udharbandBtn" onclick="openContactModal_udharband()" 
     style="max-width:380px;margin:20px auto;border-radius:26px;padding:3px;
      background:linear-gradient(270deg,#ff1744,#ff5252,#ff9100,#ff1744);
      background-size:400% 400%;animation:redGlow 6s ease infinite;
      box-shadow:0 0 15px rgba(255,23,68,0.6),0 0 25px rgba(255,82,82,0.6);
      cursor:pointer;position:relative;">
  <div class="contact-btn-inner" style="padding:20px 16px;border-radius:24px;background:rgba(60,0,0,0.7);
        display:flex;align-items:center;justify-content:center;gap:10px;
        font-size:17px;font-weight:900;color:#fff;text-shadow:0 2px 4px rgba(0,0,0,0.5);">
    🌹 100 % उधार माल लेना और देना बंद  🌹  क्लिक करें
  </div>
</div>

<style>
@keyframes redGlow{0%{background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}
@keyframes pop{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}
.ratio-210x297{--bs-aspect-ratio:calc(297 / 210 * 100%);}
</style>

<div id="udhar_contactModal_udharband" class="modal-overlay"
     style="position:fixed;inset:0;background:rgba(0,0,0,.65);display:none;
     align-items:center;justify-content:center;z-index:9999;"
     onclick="closeContactModal_udharband()">

  <div class="modal-card"
       onclick="event.stopPropagation()"
       style="background:#fff;border-radius:22px;padding:22px 18px;width:92%;max-width:420px;text-align:center;
       box-shadow:0 18px 40px rgba(0,0,0,.3);animation:pop .3s ease forwards;
       max-height:90vh;display:flex;flex-direction:column;">

    <div class="modal-top-image" style="margin:-18px -12px 6px -12px;display:flex;justify-content:center;">
      <img src="./qr/mimg.jpeg" style="width:90px;height:90px;object-fit:contain;border-radius:14px;">
    </div>

    <div class="modal-title" style="font-size:19px;font-weight:900;margin-bottom:8px;color:#e53935;">
      🌹 मुकेश कुमार सैनी 🌹 <br> मुख्य संरक्षक — प्लंबर एसोसिएशन
    </div>

    <div class="modal-number" style="font-size:17px;margin-bottom:10px;font-weight:800;">
      📞 <a href="tel:9928221039" style="color:#2e7d32;font-weight:900;text-decoration:none;">9928221039</a>
    </div>

   <div class="modal-message" style="font-size:14.5px;line-height:1.75;color:#222;white-space:pre-line;text-align:left;
    overflow-y:auto;max-height:45vh;padding-right:6px;margin-bottom:12px;
    border-top:1px dashed #ffcdd2;border-bottom:1px dashed #ffcdd2;padding-top:8px;padding-bottom:8px;">

<strong>जागो व्यापारी भाइयो जागो ! जागो प्लंबर भाइयो जागो !</strong>
<strong>2 टाइम की रोटी खानी है ! और शाम को शांति से घर परिवार बाल बच्चो के पास जाना है !</strong>
<strong>और दोनों टाइम रोटी सबको मीलेगी ! कोई भूका नहीं सोयेगा !</strong>

इसलिए आज <span style="font-weight:900; color:#d50000;">1 जनवरी 2026</span> से टेंसन लेना और देना बंद करो !
और जिसका एडवांस पैसा पहले खाते में आएगा !
उसका माल गाड़ी वाले पहले भरकर ले जाएँगे !

<strong style="color:#b71c1c;">उधार माल लेने वाले कभी भी भूल कर कॉल ना करे !</strong>
धन्यवाद 🙏

------------------------------
<b>SHRI SHYAM BROTHERS</b>
HDFC BANK  
A/c No : 50200077800334  
JAIPUR NEW SANGANER ROAD  
IFSC : HDFC0007653
------------------------------
फोनपे / पेटीएम नंबर : 8824960090  
<span style="font-weight:900; color:#d50000;">🌹 श्री दुष्यंत राजोरिया 🌹</span>

<div class="container-fluid p-0 mt-2">
  <div class="row g-2 justify-content-center">

    <!-- p1 -->
    <div class="col-12 col-sm-6 col-md-4 d-flex justify-content-center">
      <div class="modal-top-image ratio ratio-210x297" style="max-width:300px;width:100%;box-shadow:0 8px 18px rgba(0,0,0,.25);border-radius:14px;">
        <img src="./pem/p1.jpeg" class="img-fluid w-100 h-100 rounded" style="object-fit:contain;">
      </div>
    </div>

    <!-- p2 -->
    <div class="col-12 col-sm-6 col-md-4 d-flex justify-content-center">
      <div class="modal-top-image ratio ratio-210x297" style="max-width:300px;width:100%;box-shadow:0 8px 18px rgba(0,0,0,.25);border-radius:14px;">
        <img src="./pem/p2.jpeg" class="img-fluid w-100 h-100 rounded" style="object-fit:contain;">
      </div>
    </div>

    <!-- p3 -->
    <div class="col-12 col-sm-6 col-md-4 d-flex justify-content-center">
      <div class="modal-top-image ratio ratio-210x297" style="max-width:300px;width:100%;box-shadow:0 8px 18px rgba(0,0,0,.25);border-radius:14px;">
        <img src="./pem/p3.jpeg" class="img-fluid w-100 h-100 rounded" style="object-fit:contain;">
      </div>
    </div>

    <!-- p4 -->
    <div class="col-12 col-sm-6 col-md-4 d-flex justify-content-center">
      <div class="modal-top-image ratio ratio-210x297" style="max-width:300px;width:100%;box-shadow:0 8px 18px rgba(0,0,0,.25);border-radius:14px;">
        <img src="./pem/p4.jpeg" class="img-fluid w-100 h-100 rounded" style="object-fit:contain;">
      </div>
    </div>
    
   <p style="
    font-weight:900;
    font-size:22px;
    color:#b10000;
    background:linear-gradient(90deg,#fff3cd,#ffe69c);
    padding:12px 18px;
    border-radius:10px;
    text-align:center;
    box-shadow:0 4px 10px rgba(0,0,0,0.15);
    letter-spacing:0.5px;
">
    🔥 NICRON कंपनी की 550 लीटर की एक टंकी लगाओ और तुरंत 1 राशन का 600 रुपए का कीट लेकर जाओ !
</p>
>

    <!-- p5 -->
    <div class="col-12 col-sm-6 col-md-4 d-flex justify-content-center">
      <div class="modal-top-image ratio ratio-210x297" style="max-width:300px;width:100%;box-shadow:0 8px 18px rgba(0,0,0,.25);border-radius:14px;">
        <img src="./pem/p5.jpeg" class="img-fluid w-100 h-100 rounded" style="object-fit:contain;">
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 d-flex justify-content-center">
      <div class="modal-top-image ratio ratio-210x297" style="max-width:300px;width:100%;box-shadow:0 8px 18px rgba(0,0,0,.25);border-radius:14px;">
        <img src="./pem/p6.jpeg" class="img-fluid w-100 h-100 rounded" style="object-fit:contain;">
      </div>
    </div>

  </div>
</div>


    </div>

    <div id="udhar_paytmQR" style="display:none;margin-bottom:10px;">
      <img src="./qr/Ms.jpeg" style="width:200px;border-radius:16px;
      box-shadow:0 8px 20px rgba(0,0,0,.4);">
      <p style="font-size:12px;color:#777;margin-top:6px;">Payment QR</p>
    </div>

    <div class="modal-actions" style="display:flex;gap:10px;margin-bottom:10px;">
      <button onclick="makeCall_udharband()" style="flex:1;padding:12px;border:none;border-radius:14px;font-size:15px;font-weight:800;color:#fff;
      box-shadow:0 6px 14px rgba(0,0,0,.3);
      background:linear-gradient(135deg,#1b5e20,#66bb6a);">Call</button>

      <button onclick="openWhatsApp_udharband()" style="flex:1;padding:12px;border:none;border-radius:14px;font-size:15px;font-weight:800;color:#fff;
      box-shadow:0 6px 14px rgba(0,0,0,.3);
      background:linear-gradient(135deg,#25D366,#7CFC9A);">WhatsApp</button>

      <button onclick="togglePaytmQR()" style="flex:1;padding:12px;border:none;border-radius:14px;font-size:15px;font-weight:800;color:#fff;
      box-shadow:0 6px 14px rgba(0,0,0,.3);
      background:linear-gradient(135deg,#1565c0,#64b5f6);">Pay / QR</button>
    </div>

    <button onclick="closeContactModal_udharband()" 
            style="background:none;border:none;color:#999;font-size:13px;font-weight:700;cursor:pointer;">
      Close
    </button>

  </div>
</div>





<!--udhar-end-->
<!--+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++-->



<!--+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++auto end =================-->

<!--+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++auto ravi =================-->
<!-- Button -->
<a href="./autoriksha/autobookings.php" 
   onclick="openContactModalRavi()" 
   id="contactBtnRavi" 
   class="contact-btn"
   style="
    display:block;
    text-decoration:none;
    max-width:380px;
    margin:20px auto;
    border-radius:26px;
    padding:3px;
    background:linear-gradient(270deg,#00ff99,#00cc66,#33ff77,#00ff99);
    background-size:400% 400%;
    animation:greenGlow 6s ease infinite;
    box-shadow:0 0 15px rgba(0,255,153,0.6),0 0 25px rgba(0,204,102,0.6);
    cursor:pointer;
    position:relative;
   ">

  <div class="contact-btn-inner"
       style="
        padding:20px 16px;
        border-radius:24px;
        background:rgba(0,60,30,0.7);
        display:flex;
        align-items:center;
        justify-content:center;
        gap:10px;
        font-size:17px;
        font-weight:900;
        color:#eafff3;
        text-shadow:0 2px 4px rgba(0,0,0,0.5);
       ">
    <i class="fa-solid fa-phone" style="color:#00ff99;text-shadow:0 0 6px #00ff99;"></i>
    <i class="fa-brands fa-whatsapp" style="color:#25D366;text-shadow:0 0 6px #25D366;"></i>
    <span>ओला , उबेर , पोर्टर , बुक करने के लिए क्लिक करें</span>
  </div>

</a>



<!--+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++auto end =================-->


<!--mehul start-->
<!--mehul start-->
<!-- Button -->
<!--mehul start-->
<!-- Button -->
<div class="contact-btn" id="mehulRoyBtn" onclick="openContactModal_mehulRoy()"
     style="
      max-width:360px;
      margin:20px auto;
      border-radius:26px;
      padding:3px;
      background:linear-gradient(270deg,#40c4ff,#00bfa5,#26a69a,#40c4ff);
      background-size:400% 400%;
      animation:discreetGlow 8s ease infinite;
      box-shadow:0 0 12px rgba(64,196,255,0.5),0 0 20px rgba(0,191,165,0.4);
      cursor:pointer;
      position:relative;
     ">
  <div class="contact-btn-inner"
       style="
        padding:20px 16px;
        border-radius:24px;
        background:rgba(0,0,0,0.5);
        display:flex;
        align-items:center;
        justify-content:center;
        gap:12px;
        font-size:18px;
        font-weight:900;
        color:#fff;
        text-shadow:0 2px 4px rgba(0,0,0,0.5);
       ">
    <i class="fa-solid fa-phone" style="color:#00bfa5;text-shadow:0 0 6px #00bfa5;"></i>
    <i class="fa-brands fa-whatsapp" style="color:#25D366;text-shadow:0 0 6px #25D366;"></i>
    <span style="
      color:#e0f7fa;
      animation:discreetTextColor 6s linear infinite;
    ">
      ऑनलाइन कार्ड बनवाने के लिए यहाँ क्लिक करें
    </span>
  </div>
</div>

<div id="contactModal_mehulRoy" class="modal-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);display:none;align-items:center;justify-content:center;z-index:9999;">
  <div class="modal-card"
       style="
        background:linear-gradient(145deg,#ffffff,#f3f4f6);
        border-radius:22px;
        padding:22px 18px;
        width:92%;
        max-width:400px;
        max-height:80vh;
        overflow-y:auto;
        box-shadow:0 15px 35px rgba(0,0,0,0.25);
        animation:pop .3s ease forwards;
        scrollbar-width:thin;
        scrollbar-color:#ff6a00 #eee;
       ">
      
      <div class="modal-top-image" style="margin:-18px -12px 6px -12px; display:flex; justify-content:center;">
  <img src="./qr/mimg.jpeg" 
       alt="Top Banner"
       style="
         width:20%;
         max-width:300px3
         height:90px;
         max-height:90px;
         object-fit:cover;
         border-radius:14px;
         display:block;
       ">
</div>
   <div class="modal-title" style="font-size:19px;font-weight:800;margin-bottom:8px;text-align:center;color:#e53935;">
      🌹 मुकेश कुमार सैनी 🌹 <br> मुख्य संरक्षक — प्लंबर एसोसिएशन
    </div>

    <div class="modal-number" style="font-size:19px;font-weight:800;margin-bottom:8px;text-align:center;">
      📞 <a href="tel:9928221039" style="color:#2e7d32;font-weight:900;text-decoration:none;">9928221039</a>
    </div>

    <!-- TOP CARD IMAGES -->
    <div style="display:flex;gap:10px;justify-content:center;margin-bottom:12px;">
      <div  style="cursor:pointer;text-align:center;">
        <img src="./Card-form/qr/gopal_ji.jpeg" style="width:130px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.3);">
        <div style="font-size:12px;font-weight:700;margin-top:4px;">Contractor Card</div>
      </div>

      <div style="cursor:pointer;text-align:center;">
        <img src="./Card-form/qr/gopalji_wife.jpeg" style="width:130px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.3);">
        <div style="font-size:12px;font-weight:700;margin-top:4px;">Contractor Wife Card</div>
      </div>
    </div>

    <div class="modal-title" style="font-size:19px;font-weight:800;margin-bottom:8px;text-align:center;">
      🌹 मेहुल रॉय जी 🌹 <br> 
    </div>

   

    <div class="modal-actions" style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">
      <button onclick="openContractorForm()" style="padding:12px;border:none;border-radius:10px;background:#28a745;color:#fff;font-size:15px;cursor:pointer;">🏢 Contractor Card बनवाने के लिए यहाँ क्लिक करें</button>
      <button onclick="openWifeForm()" style="padding:12px;border:none;border-radius:10px;background:#007bff;color:#fff;font-size:15px;cursor:pointer;">👩 Wife Card बनवाने के लिए यहाँ क्लिक करें</button>
      <button onclick="makeCall_mehulRoy()" style="padding:12px;border:none;border-radius:10px;background:#ffc107;color:#000;font-size:15px;cursor:pointer;">📞 Call</button>
   <button onclick="openWhatsApp_mehulRoy()" 
        style="padding:12px;border:none;border-radius:10px;background:#25D366;color:#fff;font-size:15px;cursor:pointer;">
  <i class="fa-brands fa-whatsapp"></i> WhatsApp 
</button>

    </div>
    
    <br>
         <div class="modal-top-image" style="margin:-18px -12px 6px -12px; display:flex; justify-content:center;">
  <img src="./qr/qr.jpeg" 
       alt="Top Banner"
       style="
         width:100%;
         max-width:300px3
         height:400px;
         max-height:600px;
         object-fit:cover;
         border-radius:14px;
         display:block;
       ">
</div>

    <div style="text-align:center;margin-top:12px;">
      <button onclick="closeContactModal_mehulRoy()" style="background:none;border:none;color:#666;font-size:14px;cursor:pointer;">Close</button>
    </div>

  </div>
</div>






<!--mehul roy end -->
<?php include 'today_employee_slider.php'; ?>
<!--========================-->
<?php if($slider && is_array($images) && count($images)){ ?>
<div class="container mt-4">

    <div id="mySlider"
         class="carousel slide rounded-4 shadow-lg overflow-hidden border border-3 border-warning"
         data-bs-ride="carousel"
         style="background: linear-gradient(135deg, #FF9933, #FFFFFF, #138808);">

        <!-- INDICATORS -->
        <div class="carousel-indicators">
            <?php foreach($images as $k => $img){ ?>
            <button type="button"
                    data-bs-target="#mySlider"
                    data-bs-slide-to="<?= $k ?>"
                    class="<?= $k==0?'active':'' ?>"
                    style="width:12px; height:12px; border-radius:50%; border:2px solid #fff;"></button>
            <?php } ?>
        </div>

        <!-- SLIDES -->
        <div class="carousel-inner" style="height:450px;">
            <?php foreach($images as $k => $img){ ?>
            <div class="carousel-item <?= $k==0?'active':'' ?> h-100">
                <div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg, #FF9933, #FFFFFF, #138808);">
                    <img src="uploads/<?= $img ?>" class="img-fluid" style="max-width:100%; max-height:100%; object-fit:contain; border:3px solid #fff; border-radius:15px;">
                </div>
                <!-- Decorative Overlay -->
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(90deg, rgba(255,153,51,0.1), rgba(255,255,255,0.15), rgba(19,136,8,0.1));"></div>

                <div class="carousel-caption text-start" style="left:5%; right:auto; bottom:40px; max-width:420px;">
                    <span class="badge px-3 py-2 mb-2" style="background: linear-gradient(90deg, #FF9933, #FFFFFF, #138808); color:#000; font-weight:bold; letter-spacing:1px;">
                        <?= htmlspecialchars($slider['company_name']) ?>
                    </span>
                    <div class="text-white mt-2" style="font-size:1.1rem; font-weight:bold; text-shadow: 1px 1px 2px #000;">
                     
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- CONTROLS -->
        <button class="carousel-control-prev" type="button" data-bs-target="#mySlider" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" style="filter: invert(100%)"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#mySlider" data-bs-slide="next">
            <span class="carousel-control-next-icon" style="filter: invert(100%)"></span>
        </button>

    </div>
</div>

<?php } ?>



<!--==============================-->

<!-- STATS -->
<div class="stats" style="display:flex; flex-wrap:wrap; gap:12px; margin-top:24px; justify-content:center;">
    <?php
   $statCards = [
    [
        'label' => 'प्रति घंटा दर',
        'value' => "₹ " . number_format($salaryPerHour, 2) . "/hr",
        'bg'    => 'linear-gradient(135deg, #3b82f6, #60a5fa)'
    ],
    [
        'label' => 'कुल दिन',
        'value' => count($sessions),
        'bg'    => 'linear-gradient(135deg, #f59e0b, #fbbf24)'
    ],
  
   
];


    foreach($statCards as $s):
        
    ?>
    <div class="stat" style="
        flex:1; 
        min-width:140px;
        background: <?= $s['bg'] ?>; 
        color:#fff; 
        padding:16px 12px; 
        border-radius:14px; 
        text-align:center; 
        box-shadow:0 4px 12px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
        <div class="label" style="font-size:13px; font-weight:600; opacity:0.85; margin-bottom:4px;"><?= $s['label'] ?></div>
        <div class="value" style="font-size:20px; font-weight:800;"><?= $s['value'] ?></div>
    </div>
    <?php endforeach; ?>
<?php
$today = date('Y-m-d');
$lpgImage   = "./temp/cyl.jpeg";
$lpgImage22 = "./temp/ins.png";

/* ==================================================
   1️⃣ UPPER CARD : ONLY CHECK-IN (6:01–6:02 AM)
================================================== */
$sqlEarly = "
    SELECT COUNT(DISTINCT DATE(created_date)) AS total_days
    FROM employee_attendance
    WHERE employee_id = ?
      AND DATE(created_date) BETWEEN '2026-01-01' AND CURDATE()
      AND check_in_time IS NOT NULL
      AND TIME(check_in_time) >= '06:00:00'
      AND TIME(check_in_time) <  '06:01:59'
";


$stmt = $conn->prepare($sqlEarly);
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$resEarly = $stmt->get_result()->fetch_assoc();

$earlyDays   = (int)$resEarly['total_days'];
$earlyAmount = $earlyDays * 25;


/* ==================================================
   2️⃣ LOWER CARD : OLD LOGIC (CHECK-IN + CHECK-OUT)
================================================== */
$sqlNormal = "
    SELECT COUNT(DISTINCT DATE(created_date)) AS total_days
    FROM employee_attendance
    WHERE employee_id = ?
      AND DATE(created_date) BETWEEN '2026-01-01' AND CURDATE()
      AND check_in_time IS NOT NULL
      AND check_out_time IS NOT NULL
";

$stmt = $conn->prepare($sqlNormal);
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$resNormal = $stmt->get_result()->fetch_assoc();

$normalDays   = (int)$resNormal['total_days'];
$normalAmount = $normalDays * 25;

/* ===== CYLINDER CALCULATION ===== */
$cylinder = floor($earlyAmount / 750);


/* ==================================================
   FRONTEND CARDS
================================================== */

echo "
<!-- ================= UPPER CARD ================= -->
<div class='stat-card' style='
    background:linear-gradient(135deg,#0595a5,#10b981);
    padding:20px;
    border-radius:20px;
    color:white;
    max-width:360px;
    margin:16px auto;
    text-align:center;
    border:3px solid #dc2626;
    box-shadow:0 16px 36px rgba(0,0,0,.35);
    font-family:Segoe UI;
    position:relative;
'>
<div style='position:absolute;top:-14px;right:-14px;background:#dc2626;
padding:7px 14px;border-radius:22px;font-size:12px;font-weight:900;'>
🔥 EARLY BONUS
</div>

<img src='$lpgImage' style='width:100%;border-radius:14px;margin-bottom:14px;'>

<div style='font-size:15px;font-weight:700'>
6 बजकर 1 मिनट पर attendance लगाने वाले विजेता
(1 Jan 2026 → Today)
</div>

<hr style='opacity:.3'>

<div style='font-size:28px;font-weight:900;color:#fef08a'>
$earlyDays दिन
</div>

<div style='font-size:32px;font-weight:900;color:#bbf7d0'>
₹ ".number_format($earlyAmount,2)."
</div>

<div style='font-size:20px;font-weight:800;color:#fde68a;margin-top:6px;'>
🛢️ $cylinder गैस सिलेंडर 
</div>

<P><b>30 दिन सुबह 6 बजकर 1 मिनट पर अटेंडेंस लगाओ ! और 750 रूपये होने पर 860 रूपये का किसी भी कंपनी का गेस सिलेंडर मुफ्त पाओ<b></P>
</div>

<!-- ================= LOWER CARD ================= -->
<div class='stat-card' 
ondblclick=\"window.location.href='insurance_cover_showcase.php'\"
style='
    background:
        linear-gradient(135deg, #2563eb, #7c3aed),
        radial-gradient(circle at top, rgba(255,255,255,0.35), transparent 60%);
    padding:20px;
    border-radius:20px;
    color:white;
    width:100%;
    max-width:360px;
    margin:16px auto;
    text-align:center;
    border:3px solid #1e40af;
    box-shadow:
        0 16px 36px rgba(0,0,0,0.35),
        inset 0 0 16px rgba(255,255,255,0.25);
    font-family:\"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;
    position:relative;
    cursor:pointer;
'>

<div style='
    width:100%;
    margin:10px auto 16px;
    padding:8px;
    border-radius:20px;
    background:linear-gradient(135deg,#ffffff,#eef2ff);
    box-shadow:0 12px 28px rgba(0,0,0,0.35);
'>
    <img src='$lpgImage22'
         style='width:100%;border-radius:14px;background:#fff;'>
</div>

<div style='padding:16px;'>
    <div style='font-size:14px;opacity:0.95;margin-bottom:6px;'>
        <b>“1 जनवरी 2026 से अब तक की attendance का 25/= RS बोनस</b>
    </div>

    <div style='font-size:26px;font-weight:bold;'>
        $normalDays दिन = ₹ ".number_format($normalAmount,2)."
    </div>

    <br>
    <h2>डबल क्लिक करके पूरी डिटेल्स देखें</h2>

<br>

<p style='margin-top:12px;font-weight:600;font-size:14px;'>
इंश्योरेंस कराने के लिए Apply Button पर क्लिक करें
</p>

<a href='javascript:void(0);' 
   class='btn btn-success'
   data-bs-toggle='modal'
   data-bs-target='#insuranceModal'
   style='margin-top:10px;font-weight:bold;'>
   Apply Now
</a>

</div>
</div>
";



// ----------------- Stat Card: कुल काम किए दिन और ₹ -----------------



$employee_id = $employeeId;

// Fetch all required fields
$sql = "SELECT 
            employee_name,
            amount,
            percent,
            mahila_bachat_amount,
            pdf,
            number_of_kit,
            kit_amount
        FROM employee_payments_salse_commision
        WHERE employee_id = '$employee_id'
        LIMIT 1";

$result = $conn->query($sql);
$imgds = "temp/dsk"; // या uploads/photo.jpg



if ($result && $result->num_rows > 0) {

    $row = $result->fetch_assoc();
$name = htmlspecialchars($row['employee_name'] ?? '', ENT_QUOTES, 'UTF-8');

$amount = number_format((float)($row['amount'] ?? 0), 2);

$commission = number_format((float)($row['percent'] ?? 0), 2);

$mahila_bachat = number_format(
    ((float)($row['percent'] ?? 0)) * 2,
    2
);

/* ✅ NEW ADDED — SAME STYLE */
$number_of_kit = (int)($row['number_of_kit'] ?? 0);

$kit_amount = number_format(
    ((float)($row['kit_amount'] ?? 0)) * 2,
    2
);
$total_profit = number_format(
    ((float)str_replace(',', '', $mahila_bachat) +
     (float)str_replace(',', '', $kit_amount)),
    2
);


/* PDF */
$pdf = htmlspecialchars($row['pdf'] ?? '', ENT_QUOTES, 'UTF-8');


  // Block 1 — Employee + Amount + Commission + Double click PDF
// Block 1 — Employee + Amount + Commission + Double click PDF
// echo "
// <div class='stat-card'
//      ondblclick=\"".($pdf ? "window.open('$pdf','_blank')" : "")."\"
//      style='
//         cursor:pointer;
//         position:relative;
//         overflow:hidden;

//         background:
//             linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0)),
//             linear-gradient(135deg, #6366f1, #22d3ee, #ec4899);

//         padding:22px;
//         border-radius:22px;
//         color:white;
//         width:100%;
//         max-width:380px;
//         margin:18px auto;
//         text-align:center;

//         border:4px solid transparent;
//         background-image:
//             linear-gradient(135deg, #6366f1, #22d3ee, #ec4899),
//             linear-gradient(to bottom, #FF9933, #ffffff, #138808);
//         background-origin:border-box;
//         background-clip:padding-box, border-box;

//         box-shadow:
//             0 20px 40px rgba(0,0,0,0.35),
//             inset 0 0 25px rgba(255,255,255,0.15);

//         font-family:\"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;
//         transition:all .45s ease;
//      '

//      onmouseover=\"this.style.transform='translateY(-6px) scale(1.02)';
//                   this.style.boxShadow='0 30px 60px rgba(0,0,0,0.45)';\"
//      onmouseout=\"this.style.transform='none';
//                   this.style.boxShadow='0 20px 40px rgba(0,0,0,0.35)';\"
// >

//     <!-- GLOW EFFECT -->
//     <div style='
//         position:absolute;
//         inset:0;
//         background:radial-gradient(circle at top, rgba(255,255,255,0.35), transparent 60%);
//         pointer-events:none;
//     '></div>

//     <!-- BUSINESS NAME -->
//     <div style='
//         font-size:30px;
//         font-weight:900;
//         margin-bottom:12px;
//         letter-spacing:1.5px;
//         text-shadow:0 3px 6px rgba(0,0,0,0.45);
//     '>
//         🌹 श्री श्याम ब्रदर्स 🌹
//     </div>

//     <!-- IMAGE -->
//     <img src='$imgds'
//          alt='Employee'
//          style='
//             width:150px;
//             height:150px;
//             object-fit:cover;
//             display:block;
//             margin:0 auto 12px;
//             border-radius:22px;
//             border:5px solid rgba(255,255,255,0.95);
//             box-shadow:
//                 0 15px 35px rgba(0,0,0,0.45),
//                 inset 0 0 12px rgba(255,255,255,0.4);
//             background:#fff;
//          '>

//     <!-- NAME -->
//   <!-- NAME -->
// <div style='
//     font-size:26px;
//     font-weight:900;
//     margin-top:6px;
//     letter-spacing:1px;

//     background:linear-gradient(90deg,#fde68a,#f59e0b,#ef4444);
//     -webkit-background-clip:text;
//     color:transparent;

//     text-shadow:0 2px 6px rgba(0,0,0,0.35);
// '>
//     दुष्यंत राजोरिया
// </div>

// <!-- PHONE -->
// <div style='
//     font-size:15px;
//     font-weight:800;
//     margin-top:6px;
//     letter-spacing:1.5px;

//     background:linear-gradient(90deg,#22d3ee,#3b82f6,#6366f1);
//     -webkit-background-clip:text;
//     color:transparent;

//     text-shadow:0 2px 5px rgba(0,0,0,0.3);
// '>
//     📞 8824960090
// </div>


//     <!-- CUSTOMER NAME -->
//     <div style='
//         font-size:19px;
//         font-weight:700;
//         margin-top:14px;
//         letter-spacing:0.6px;
//         text-transform:uppercase;
//         opacity:0.9;
//     '>
//         $name
//     </div>

//     <!-- BILL -->
//     <div style='
//         font-size:26px;
//         font-weight:900;
//         margin-top:14px;
//         background:linear-gradient(90deg,#fde68a,#facc15,#f59e0b);
//         -webkit-background-clip:text;
//         color:transparent;
//         text-shadow:0 3px 6px rgba(0,0,0,0.25);
//     '>
//         BILL AMOUNT: ₹$amount
//     </div>

//     <!-- COMMISSION -->
//   <!-- लाभ राशि -->
// <div style='
//     font-size:20px;
//     font-weight:700;
//     margin-top:8px;
//     color:red; /* sky blue */
//     text-shadow:0 2px 4px rgba(0,0,0,0.3);
// '>
//     लाभ राशि: ₹$commission
// </div>

// <!-- किट की संख्या -->
// <div style='
//     font-size:20px;
//     font-weight:700;
//     margin-top:8px;
//     color:#facc15; /* golden yellow */
//     text-shadow:0 2px 4px rgba(0,0,0,0.3);
// '>
//   राशन किट की संख्या: $number_of_kit
// </div>

// <!-- किट राशि -->
// <div style='
//     font-size:20px;
//     font-weight:700;
//     margin-top:8px;
//     color:#34d399; /* green */
//     text-shadow:0 2px 4px rgba(0,0,0,0.3);
// '>
//   राशन  किट राशि: ₹$kit_amount
// </div>

        
//     </div>
   
    

// </div>
// ";

$employee_id = $employeeId;

/* ===== MONTHLY BILL SUM ===== */

$sql = "SELECT 
            SUM(last_bill_amount) as amount,
            MAX(pdf) as pdf
        FROM employee_monthly_report
        WHERE employee_id = '$employee_id'";

$result = $conn->query($sql);

$imgds = "temp/dsk";

if ($result && $result->num_rows > 0) {

    $row = $result->fetch_assoc();

    $name = "Customer";

    $amount_value = (float)($row['amount'] ?? 0);
    $amount = number_format($amount_value, 2);

    /* 2% PROFIT */
    $commission_value = $amount_value * 0.02;
    $commission = number_format($commission_value, 2);

    $pdf = htmlspecialchars($row['pdf'] ?? '', ENT_QUOTES, 'UTF-8');

echo "
<div class='stat-card'

     ondblclick=\"openNoticeModal2()\"

     style='
        position:relative;
        overflow:hidden;

        background:
            linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0)),
            linear-gradient(135deg, #6366f1, #22d3ee, #ec4899);

        padding:22px;
        border-radius:22px;
        color:white;
        width:100%;
        max-width:380px;
        margin:18px auto;
        text-align:center;

        border:4px solid transparent;
        background-image:
            linear-gradient(135deg, #6366f1, #22d3ee, #ec4899),
            linear-gradient(to bottom, #FF9933, #ffffff, #138808);
        background-origin:border-box;
        background-clip:padding-box, border-box;

        box-shadow:
            0 20px 40px rgba(0,0,0,0.35),
            inset 0 0 25px rgba(255,255,255,0.15);

        font-family:\"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;
        transition:all .45s ease;
     '

     onmouseover=\"this.style.transform='translateY(-6px) scale(1.02)';
                   this.style.boxShadow='0 30px 60px rgba(0,0,0,0.45)';\"

     onmouseout=\"this.style.transform='none';
                  this.style.boxShadow='0 20px 40px rgba(0,0,0,0.35)';\"
>


<div style='
position:absolute;
inset:0;
background:radial-gradient(circle at top, rgba(255,255,255,0.35), transparent 60%);
pointer-events:none;
'></div>

<div style='
font-size:30px;
font-weight:900;
margin-bottom:12px;
letter-spacing:1.5px;
text-shadow:0 3px 6px rgba(0,0,0,0.45);
'>
🌹 श्री श्याम ब्रदर्स 🌹
</div>

<img src='$imgds'
     alt='Employee'
     style='
        width:150px;
        height:150px;
        object-fit:cover;
        display:block;
        margin:0 auto 12px;
        border-radius:22px;
        border:5px solid rgba(255,255,255,0.95);
        box-shadow:
            0 15px 35px rgba(0,0,0,0.45),
            inset 0 0 12px rgba(255,255,255,0.4);
        background:#fff;
     '>

<div style='
font-size:26px;
font-weight:900;
margin-top:6px;
letter-spacing:1px;
background:linear-gradient(90deg,#fde68a,#f59e0b,#ef4444);
-webkit-background-clip:text;
color:transparent;
text-shadow:0 2px 6px rgba(0,0,0,0.35);
'>
दुष्यंत राजोरिया
</div>

<div style='
font-size:15px;
font-weight:800;
margin-top:6px;
letter-spacing:1.5px;
background:linear-gradient(90deg,#22d3ee,#3b82f6,#6366f1);
-webkit-background-clip:text;
color:transparent;
text-shadow:0 2px 5px rgba(0,0,0,0.3);
'>
📞 8824960090
</div>

<div style='
font-size:19px;
font-weight:700;
margin-top:14px;
letter-spacing:0.6px;
text-transform:uppercase;
opacity:0.9;
'>
$name
</div>

<div style='
font-size:26px;
font-weight:900;
margin-top:14px;
background:linear-gradient(90deg,#fde68a,#facc15,#f59e0b);
-webkit-background-clip:text;
color:transparent;
text-shadow:0 3px 6px rgba(0,0,0,0.25);
'>
BILL AMOUNT: ₹$amount
</div>

<div style='
font-size:20px;
font-weight:700;
margin-top:8px;
color:red;
text-shadow:0 2px 4px rgba(0,0,0,0.3);
'>
लाभ राशि: ₹$commission
</div>

</div>
";

}

$imgLeft='./temp/mmm';
$imgRight='./temp/ss.jpeg';
$posterImg='./temp/store.png';

date_default_timezone_set('Asia/Kolkata');
$currentDateTime = date("d M Y, h:i A");

/* =====================
   COMMISSION x2 PROFIT
===================== */
$commissionAmount = isset($commission) ? (float)str_replace(',', '', $commission) : 0;

$total_profit = $commissionAmount * 2;

// echo "₹" . number_format($total_profit, 2);


echo "
<div style='
    position:relative;
    background:linear-gradient(135deg,#fdf2f8,#fbcfe8);
    padding:20px;
    border-radius:16px;
    width:100%;
    max-width:420px;
    margin:18px auto;
    border:1px solid #f9a8d4;
    box-shadow:0 10px 24px rgba(0,0,0,0.08);
    font-family:Segoe UI, Roboto, sans-serif;
'>

<div style='text-align:center;'>

    <div style='
        font-size:28px;
        font-weight:800;
        color:#831843;
        margin-bottom:10px;
    '>
        महिला बचत योजना 
    </div>

</div>

<!-- TOP IMAGES -->
<div style='
    display:flex;
    justify-content:space-between;
    gap:14px;
    margin-bottom:16px;
'>

<!-- LEFT IMAGE -->
<div style='flex:1;text-align:center;'>
    <div style='
        width:88px;
        height:88px;
        border-radius:14px;
        overflow:hidden;
        background:#fff;
        border:1px solid #f9a8d4;
        box-shadow:0 6px 14px rgba(0,0,0,0.14);
        margin:0 auto 6px;
    '>
        <img src='$imgLeft' style='width:100%;height:100%;object-fit:cover;'>
    </div>

    <div style='font-size:14px;font-weight:800;color:#831843;'>
        🌹सीमा सैनी  🌹
    </div>

    <div style='font-size:12px;font-weight:600;color:#9d174d;'>
        महिला बचत योजना
    </div>

    <div style='font-size:12px;font-weight:600;color:#9d174d;'>
        प्लंबर एसोसिएशन
    </div>

    <div style='font-size:13px;font-weight:700;color:#be185d;margin-top:2px;'>
        📞 9928221039
    </div>
</div>

<!-- RIGHT IMAGE -->
<div style='flex:1;text-align:center;'>
    <div style='
        width:88px;
        height:88px;
        border-radius:14px;
        overflow:hidden;
        background:#fff;
        border:1px solid #f9a8d4;
        box-shadow:0 6px 14px rgba(0,0,0,0.14);
        margin:0 auto 6px;
    '>
        <img src='$imgRight' style='width:100%;height:100%;object-fit:cover;'>
    </div>

    <div style='font-size:14px;font-weight:800;color:#831843;'>
        🌹 मुकेश कुमार सैनी 🌹
    </div>

    <div style='font-size:12px;font-weight:600;color:#9d174d;'>
        मुख्य संरक्षक
    </div>

    <div style='font-size:12px;font-weight:600;color:#9d174d;'>
        प्लंबर एसोसिएशन
    </div>

    <div style='font-size:13px;font-weight:700;color:#be185d;margin-top:2px;'>
        📞 9928221039
    </div>
</div>

</div>

<div style='text-align:center;'>

<div style='
    font-size:18px;
    font-weight:800;
    color:#831843;
    margin-bottom:10px;
'>
महिला बचत योजना
</div>

<!-- POSTER -->
<div style='
    width:100%;
    margin:14px 0 18px;
    border-radius:14px;
    overflow:hidden;
    border:1px solid #f9a8d4;
    box-shadow:0 6px 18px rgba(0,0,0,0.12);
'>
<img src='$posterImg' style='width:100%;height:auto;display:block;object-fit:cover;'>
</div>

<div style='
    font-size:18px;
    color:#9d174d;
    font-weight:600;
    letter-spacing:0.5px;
    text-transform:uppercase;
    margin-bottom:4px;
'>
इस खाते में जमा राशी से आप राजस्थान के किसी भी रिलाइंस फ्रेस / जिओ मार्ट / बिग बाजार / डी मार्ट / नेशनल सुपर मार्केट से कोई भी सामान मिलेक्ट्री केंटिन की तरह आधी रेट में खरीद सकते है !
<br>
⚠️ नोट: खाते में न्यूनतम ₹2000 होना अनिवार्य है।
</div>

<div style='
    font-size:28px;
    font-weight:900;
    color:#831843;
    margin-bottom:10px;
'>
महिला बचत योजना खाता
</div>

<div style='
    background:#fff;
    border-radius:14px;
    padding:14px;
    border:1px solid #fbcfe8;
    display:inline-block;
    min-width:200px;
'>

<div style='font-size:13px;color:#9d174d;font-weight:600;'>
कुल जमा राशि
</div>

<div style='font-size:28px;font-weight:900;color:#be185d;'>
₹$total_profit
</div>

<div style='
margin-top:6px;
font-size:12px;
font-weight:600;
color:#6b7280;
'>
🕒 $currentDateTime
</div>

</div>

</div>

</div>
";

} else {

    echo "
    <div class='stat-card' style='
        background:linear-gradient(135deg, #ec4899, #f472b6);
        padding:16px;
        border-radius:12px;
        color:white;
        width:100%;
        max-width:350px;
        margin:10px auto;
        text-align:center;
    '>
        <div style='font-size:16px; opacity:0.9;'><b>महिला बचत योजना खाता</b></div>
        <div style='font-size:26px; font-weight:bold; margin-top:4px;'>No Data Found</div>
    </div>
    ";
}


?>
<?php
$employee_id = isset($employee_id) ? $employee_id : '';
?>
<?php

$currentMonth = date("Y-m");
$left_amount = 0;

$stmt = $conn->prepare("
SELECT left_amount 
FROM employee_monthly_report 
WHERE employee_id = ? 
AND month = ?
LIMIT 1
");

if($stmt){
    $stmt->bind_param("ss", $employee_id, $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()){
        $left_amount = $row['left_amount'];
    }

    $stmt->close();
} else {
    error_log("employee monthly report left amount prepare failed: " . $conn->error);
}
?>

<div class="stat-card"
     ondblclick="window.location.href='balancesheet.php?employee_id=<?php echo urlencode($employee_id); ?>'"
     style="
        cursor:pointer;
        position:relative;
        overflow:hidden;
        background:linear-gradient(135deg,#6366f1,#22d3ee,#ec4899);
        padding:30px;
        border-radius:24px;
        color:white;
        width:100%;
        max-width:420px;
        margin:22px auto;
        text-align:center;
        box-shadow:0 25px 50px rgba(0,0,0,0.35);
        font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        transition:all .45s ease;
     "
     onmouseover="this.style.transform='translateY(-6px) scale(1.02)'"
     onmouseout="this.style.transform='none'"
>

<div style="font-size:34px;font-weight:900;margin-bottom:18px;letter-spacing:1px;">
📊 CURRENT MONTH REPORT
</div>

<div style="font-size:22px;font-weight:700;margin-top:6px;">
Employee ID: <?php echo htmlspecialchars($employee_id); ?>
</div>

<div style="font-size:16px;font-weight:700;margin-top:15px;color:#b30000;background:#fff3cd;padding:10px 15px;border-radius:6px;border-left:5px solid #ffcc00;">
⚠️ विशेष सूचना >> अपने अकाउंट में जमा पैसे से हर महीने एक ही बार पूरा राशन खरीदें। यानी एक साल में केवल 12 भुगतान एंट्री होंगी।
</div>

<div style="
    margin-top:28px;
    background:rgba(255,255,255,0.15);
    padding:20px;
    border-radius:16px;
    backdrop-filter:blur(6px);
">

<div style="
    font-size:18px;
    font-weight:700;
    opacity:0.9;
    margin-bottom:6px;
">
💰 USABLE AMOUNT
</div>

<div style="
    font-size:52px;
    font-weight:900;
    letter-spacing:2px;
    text-shadow:0 4px 10px rgba(0,0,0,0.4);
">
₹ <?php echo number_format($left_amount,2); ?>
</div>

</div>

<div style="font-size:16px;margin-top:18px;opacity:0.9;">
(पूरा रिपोर्ट देखने के लिए डबल क्लिक करें)
</div>

</div>
<?php




$employeeId = (string) ($_SESSION['emp_id'] ?? '');

/* DATA */

$result = $conn->query("
SELECT quotation_date,party_name,company,amount
FROM upcoming_quotation
WHERE emp_id='$employeeId'
ORDER BY id DESC
");

/* COUNT */

$count = $result ? $result->num_rows : 0;

/* TOTAL */

$totalResult = $conn->query("
SELECT SUM(amount) as total
FROM upcoming_quotation
WHERE emp_id='$employeeId'
");
$totalRow = $totalResult ? $totalResult->fetch_assoc() : [];
$total = $totalRow['total'] ?? 0;

$total = $total ?? 0;

$profit = ($total * 2) / 100;
$mahila_bachat = ($total * 4) / 100;

?>

<style>

.quote-card{
border-left:5px solid #0d6efd;
border-radius:8px;
transition:0.3s;
}

.quote-card:hover{
background:#f8f9fa;
transform:scale(1.01);
}

.amount-box{
font-size:18px;
color:#198754;
font-weight:bold;
}

.summary-box{
background:linear-gradient(135deg,#0d6efd,#6610f2);
color:white;
border-radius:10px;
}

.summary-box hr{
border-color:rgba(255,255,255,0.4);
}

</style>


<div class="container">

<h4 class="mb-3 text-primary fw-bold">📋 मेरे द्वारा बनवाए गए कोटेशन</h

<!-- Scroll only if more than 3 -->

<div class="list-group <?php if($count>3) echo 'overflow-auto'; ?>" 
style="<?php if($count>3) echo 'max-height:260px;'; ?>">

<?php while($row = $result->fetch_assoc()){ ?>

<div class="list-group-item quote-card mb-2">

<div class="d-flex justify-content-between align-items-center">

<div>

<div><span class="text-secondary">Date :</span> 
<b><?php echo $row['quotation_date']; ?></b></div>

<div><span class="text-secondary">Party :</span> 
<b><?php echo $row['party_name']; ?></b></div>

<div><span class="text-secondary">Company :</span> 
<b><?php echo $row['company']; ?></b></div>

</div>

<div class="amount-box">

₹ <?php echo number_format($row['amount'],2); ?>

</div>

</div>

</div>

<?php } ?>

</div>

<!-- Total Section -->

<div class="summary-box p-4 mt-3 shadow-lg rounded-4 text-center">

    <!-- 🔹 Total -->
    <h4 class="fw-bold mb-3 total-text">
        💰 Total Amount <br>
        <span class="amount">₹ <?php echo number_format($total,2); ?></span>
    </h4>

    <hr>

    <!-- 🔹 Profit -->
    <div class="mb-2 profit-box">
        📈 Profit (2%) :
        <span>₹ <?php echo number_format($profit,2); ?></span>
    </div>

    <!-- 🔹 Mahila Bachat -->
    <div class="mahila-box">
        👩‍💼 Mahila Bachat (4%) :
        <span>₹ <?php echo number_format($mahila_bachat,2); ?></span>
    </div>

</div>

</div>





<!-- Bootstrap CSS & JS (अगर पहले से नहीं है तो add करें) -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="modal fade" id="insuranceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title">इंश्योरेंस आवेदन</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <label>अपना नाम लिखें</label>
        <input type="text" id="userName" class="form-control"
               value="<?php echo htmlspecialchars($employeeNamee); ?>">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="sendWhatsApp()">Confirm & Send</button>
      </div>

    </div>
  </div>
</div>





    
</div>
<?php
include "balancesheet.php";
?>
<!--==============================-->

<?php
// ================= Business summary boxes =================
$totalPVCAmount = 0;   // सामान की पर्ची (PVC / Slip)
$totalGSTAmount = 0;   // GST Bill

foreach ($businessData as $bd) {

    $amount = floatval($bd['bonus'] ?? 0);

    if ($bd['entry_type'] === 'PVC') {
        $totalPVCAmount += $amount;
    }

    if ($bd['entry_type'] === 'GST') {
        $totalGSTAmount += $amount;
    }
}

?>
<?php
$totalAmountt = 0; // 👈 NEW variable

foreach ($businessData as $bd) {
    $totalAmountt += floatval($bd['amount'] ?? 0);
}
?>


<div style="display:flex; gap:16px; flex-wrap:wrap; justify-content:center; margin-top:24px;">
    




  

</div>


<!--===============================-->


<!--echo "-->
<div
style='
    cursor:pointer;
    position:relative;
    overflow:hidden;

    background:
        linear-gradient(135deg, rgba(255,255,255,0.18), rgba(255,255,255,0)),
        linear-gradient(135deg, #7c2d12, #ea580c, #facc15);

    padding:22px;
    border-radius:22px;
    color:white;
    width:100%;
    max-width:420px;
    margin:18px auto;
    text-align:center;

    border:4px solid transparent;
    background-image:
        linear-gradient(135deg, #7c2d12, #ea580c, #facc15),
        linear-gradient(to bottom, #ffedd5, #ffffff, #fde68a);
    background-origin:border-box;
    background-clip:padding-box, border-box;

    box-shadow:
        0 22px 45px rgba(0,0,0,0.38),
        inset 0 0 26px rgba(255,255,255,0.18);

    font-family:Segoe UI, Tahoma, sans-serif;
    transition:all .45s ease;
'
onmouseover="this.style.transform='translateY(-6px) scale(1.02)';
             this.style.boxShadow='0 32px 65px rgba(0,0,0,0.48)';"
onmouseout="this.style.transform='none';
             this.style.boxShadow='0 22px 45px rgba(0,0,0,0.38)';"
>

<!-- GLOW -->
<div style='
    position:absolute;
    inset:0;
    background:radial-gradient(circle at top, rgba(255,255,255,0.35), transparent 65%);
    pointer-events:none;
'></div>

<div style='
    width:100%;
    border-radius:18px;
    overflow:hidden;
    margin-bottom:14px;
    background:#000;
'>
    <img src="./temp/movie final.png"
    style='
        width:100%;
        height:auto;
        display:block;
        object-fit:contain;

        /* sharpness helpers */
        image-rendering:-webkit-optimize-contrast;
        transform:translateZ(0);
    '>
</div>


<!-- TITLE -->
<div style='
    font-size:23px;
    font-weight:900;
    margin-bottom:10px;
    background:linear-gradient(90deg,#fff7ed,#fde68a,#fbbf24);
    -webkit-background-clip:text;
    color:transparent;
    text-shadow:0 3px 6px rgba(0,0,0,0.45);
'>
💃 मनोरंजन संग भोजन योजना💃
</div>

<!-- INTRO -->


<hr style='border-color:rgba(255,255,255,0.35);'>

<!-- OFFER -->
<div style='
    font-size:25px;
    font-weight:800;
    color:#dcfce7;
'>
👉 जब आपकी टोटल 20 रिकवायरमेंट स्लिप हो जाएगी
</div>

<div style='
    margin-top:6px;
    font-size:26px;
    font-weight:900;
'>यानी
₹25 × 20 = <span style='color:#fde68a;'>₹500</span>
</div>

<!-- FEATURES -->
<div style='
    background:rgba(255,255,255,0.18);
    border-radius:14px;
    padding:12px;
    margin:14px 0;
    font-size:24px;
'>
💃 रेस्टोरेंट में स्वादिष्ट भोजन<br>
🎥 राजस्थान के किसी भी पिक्चर हॉल में पत्नी के साथ<br>
<b style='color:#fde68a;'>💖 Movie का आनंद 💖</b>
</div>

<!-- PRICE -->
<div style='font-size:24px;'>
📌 2 Movie Ticket ₹300 = <b>₹600</b><br>
📌 पति-पत्नी भोजन ₹450 = <b>₹900</b>
</div>

<div style='
    margin-top:12px;
    font-size:27px;
    font-weight:900;
    background:linear-gradient(90deg,#fff7ed,#fde68a,#facc15);
    -webkit-background-clip:text;
    color:transparent;
'>
✨ कुल ₹1500  ✨
</div>

<div style='
    font-size:23px;
    opacity:0.9;
    margin-top:8px;
'>
जो पैसों से नहीं, <b>यादों</b> से मापा जाता है।
</div>

<!-- CTA -->
<div style='
    margin-top:16px;
    font-size:20px;
    background:#fff;
    color:#7c2d12;
    font-weight:900;
    padding:10px;
    border-radius:999px;
    box-shadow:0 6px 16px rgba(0,0,0,0.25);
'>
👉आज से ₹5000/- से अधिक की पर्चियाँ डालना शुरू करें
</div>
<br>
  <!-- AMOUNT -->
    <div style="
        text-align:center;
        font-size:24px;
        font-weight:900;
        background:linear-gradient(135deg,#fbbf24,#f59e0b);
        color:#000;
        padding:10px;
        border-radius:14px;
    ">
        ₹<?= number_format($totalPVCAmount, 2) ?>
    </div>
    <div style="text-align:center; margin-top:15px;">

    <button onclick="goToDetails('<?= $employeeId ?>')" 
    style="
        background:linear-gradient(135deg,#22c55e,#16a34a);
        border:none;
        color:white;
        padding:12px 22px;
        font-size:18px;
        font-weight:700;
        border-radius:999px;
        cursor:pointer;
        box-shadow:0 6px 15px rgba(0,0,0,0.3);
        transition:0.3s;
    ">
        ➕ Requirement Slip डालें
    </button>

</div>


</div>
<br>

<!--";-->
<?php
$billAmount = $totalAmountt;

$twoPercent = ($billAmount * 2) / 100;
$mahilaBachat = ($billAmount * 4) / 100;

// GST already hai
$gstBonus = $totalGSTAmount;
?>
<!--===================?-->
    <!-- Gold GST Box -->
   <div style="
    max-width:360px;
    margin:20px auto;
    background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
    border-radius:18px;
    padding:18px;
    color:#fff;
    box-shadow:0 10px 30px rgba(0,0,0,0.35);
    font-family:Arial, sans-serif;
">

    <!-- IMAGE -->
    <div style="text-align:center; margin-bottom:12px;">
        <img src="./temp/petrol.png"
             style="width:100%; max-width:300px; border-radius:12px;"
             alt="Fuel Station">
    </div>

    <!-- TITLE -->
    <div style="
        text-align:center;
        font-size:18px;
        font-weight:800;
        margin-bottom:10px;
        letter-spacing:0.5px;
    ">
        Fuel Wallet Accepted 
    </div>

    <!-- FUEL TYPES -->
    <div style="
        display:flex;
        justify-content:space-between;
        gap:8px;
        margin-bottom:12px;
    ">
   <div style="flex:1; background:#22c55e; padding:10px; border-radius:10px; text-align:center; font-weight:700; color:#fff;">
    ⛽ Petrol<br>
    <span style="font-size:12px; text-decoration:line-through; opacity:0.8;">
        ₹104.72
    </span><br>
    <span style="font-size:16px; font-weight:900;">
        ₹52.36
    </span>
</div>

<div style="flex:1; background:#facc15; color:#000; padding:10px; border-radius:10px; text-align:center; font-weight:700;">
    🚛 Diesel<br>
    <span style="font-size:12px; text-decoration:line-through; opacity:0.7;">
        ₹88.42
    </span><br>
    <span style="font-size:16px; font-weight:900;">
        ₹44.21
    </span>
</div>

<div style="flex:1; background:#38bdf8; padding:10px; border-radius:10px; text-align:center; font-weight:700; color:#fff;">
    🔥 CNG<br>
    <span style="font-size:12px; text-decoration:line-through; opacity:0.8;">
        ₹90.21
    </span><br>
    <span style="font-size:16px; font-weight:900;">
        ₹45.10
    </span>
</div>

<!--<div style="flex:1; background:#16a34a; padding:10px; border-radius:10px; text-align:center; font-weight:700; color:#fff;">-->
<!--    ⚡ EV Charging<br>-->
<!--    <span style="font-size:12px; opacity:0.8;">-->
<!--        Fast Charge-->
<!--    </span><br>-->
<!--    <span style="font-size:16px; font-weight:900;">-->
<!--        Available-->
<!--    </span>-->
<!--</div>-->


    </div>

    <!-- INFO TEXT -->
    <div style="
        background:rgba(255,255,255,0.12);
        padding:10px;
        border-radius:12px;
        text-align:center;
        font-size:14px;
        margin-bottom:10px;
    ">
        इस बैलेंस से आप <b>Petrol, Diesel और CNG</b> खरीद सकते हैं  
        <br>
        <span style="color:#facc15; font-weight:800;">
            Example: ₹500 Balance = ₹1000 Fuel
        </span>
    </div>

    <!-- BONUS -->
    <div style="
        text-align:center;
        font-size:14px;
        opacity:0.9;
        margin-bottom:6px;
    ">
        GST BILL डालने पर बोनस <b>₹25</b>
    </div>

    <!-- AMOUNT -->
 <div style="
    text-align:center;
    font-size:20px;
    font-weight:900;
    border-radius:14px;
    overflow:hidden;
">

    <div style="padding:12px; background:linear-gradient(135deg,#60a5fa,#3b82f6); color:#fff;">
        Bill Amount: ₹ <?= number_format($billAmount, 2) ?>
    </div>

    <div style="padding:12px; background:linear-gradient(135deg,#34d399,#10b981); color:#fff;">
        2% Commission: ₹ <?= number_format($twoPercent, 2) ?>
    </div>

    <div style="padding:12px; background:linear-gradient(135deg,#f472b6,#ec4899); color:#fff;">
        Mahila Bachat (4%): ₹ <?= number_format($mahilaBachat, 2) ?>
    </div>

    <div style="padding:12px; background:linear-gradient(135deg,#fbbf24,#f59e0b); color:#000;">
        GST Bill Bonus: ₹ <?= number_format($gstBonus, 2) ?>
    </div>

</div>
</div>

<?php
function renderGSTBillList($businessData) {
    if (empty($businessData)) {
        echo "<p style='text-align:center; color:red; font-weight:800; font-size:16px;'>No Data Found</p>";
        return;
    }

    echo '<div style="
        margin-top:20px;
        overflow-x:auto;
        max-height:250px;
        overflow-y:auto;
        border-radius:12px;
        box-shadow:0 5px 20px rgba(0,0,0,0.10);
    ">';

    echo '<table style="
        width:100%;
        border-collapse:collapse;
        font-size:15px;
        text-align:center;
        font-family:Arial, sans-serif;
    ">';

    // HEADER (extra bold + bigger font)
    echo '<tr style="
            background:linear-gradient(90deg,#0d6efd,#0b5ed7);
            color:#fff;
            position:sticky;
            top:0;
            z-index:2;
            font-weight:900;
            font-size:16px;
            letter-spacing:0.8px;
        ">
            <th style="padding:15px; font-weight:900;">GST Bill No</th>
            <th style="padding:15px; font-weight:900;">Amount</th>
            <th style="padding:15px; font-weight:900;">Customer Name</th>
            <th style="padding:15px; font-weight:900;">Mobile</th>
            <th style="padding:15px; font-weight:900;">Bonus</th>
            <th style="padding:15px; font-weight:900;">Remark</th>
          </tr>';

    $i = 0;
    foreach ($businessData as $row) {

        $amount  = isset($row['amount']) ? floatval($row['amount']) : 0;
        $bonus   = isset($row['bonus']) ? floatval($row['bonus']) : 0;

        $name    = htmlspecialchars($row['customer_name'] ?? '-') ;
        $mobile  = htmlspecialchars($row['customer_number'] ?? '-');
        $remark  = htmlspecialchars($row['remark'] ?? '-');

        $bg = ($i % 2 == 0) ? '#f7faff' : '#ffffff';

        echo "<tr style='
                border-bottom:1px solid #e6e6e6;
                background:$bg;
                transition:0.2s;
                font-size:15px;
                font-weight:600;
            '
            onmouseover=\"this.style.background='#dbeafe'\"
            onmouseout=\"this.style.background='$bg'\">

                <td style='padding:14px; font-weight:900; font-size:15px; color:#111;'>#".($row['bill_no'] ?? '-')."</td>

                <td style='padding:14px; font-weight:900; font-size:15px; color:#198754;'>
                    ₹ ".number_format($amount,2)."
                </td>

                <td style='padding:14px; font-weight:800; font-size:15px; color:#111;'>
                    $name
                </td>

                <td style='padding:14px; font-weight:700; font-size:15px; color:#333;'>
                    $mobile
                </td>

                <td style='padding:14px; font-weight:900; font-size:15px; color:#0d6efd;'>
                    ₹ ".number_format($bonus,2)."
                </td>

                <td style='padding:14px; font-weight:600; font-size:14px; color:#555;'>
                    $remark
                </td>

              </tr>";

        $i++;
    }

    echo '</table>';
    echo '</div>';
}
?>
<?php
renderGSTBillList($businessData);
?>
<div class="container mt-5">

  

    <div id="profileCarousel"
         class="carousel slide rounded-4 shadow-lg overflow-hidden"
         data-bs-ride="carousel"
         data-bs-interval="3000"
         style="background:#0a0a1a; min-height:400px; position:relative;">
          <!-- Slider Heading -->
    <h2 class="text-center text-white mb-4" style="font-weight:bold; font-size:2rem;">
       GST बिल और रिक्वायामेंट स्लिप डालने वाले विजेता 25/=RS
    </h2>

        <div class="carousel-inner text-center d-flex align-items-center"
             style="min-height:400px;">

            <?php 
            $active = 'active';
            foreach($profiles as $p): 
                
                // Random card color gradient
                $gradients = [
                    'linear-gradient(135deg,#ff9a9e,#fad0c4)',
                    'linear-gradient(135deg,#a18cd1,#fbc2eb)',
                    'linear-gradient(135deg,#fad0c4,#ffd1ff)'
                ];
                $grad = $gradients[array_rand($gradients)];
            ?>
                <div class="carousel-item <?= $active ?>">

                    <div class="d-flex justify-content-center align-items-center"
                         style="min-height:400px; position:relative;">

                        <!-- Card with star/moon/glitter background -->
                        <div style="
                            position:relative;
                            border-radius:22px;
                            width:320px;
                            background: <?= $grad ?>;
                            box-shadow:0 0 40px rgba(255,255,255,0.3), 0 25px 50px rgba(0,0,0,0.3);
                            overflow:hidden;
                        ">

                            <!-- Stars -->
                            <?php for($i=0;$i<12;$i++): ?>
                                <div style="
                                    position:absolute;
                                    width:4px; height:4px;
                                    background:white;
                                    border-radius:50%;
                                    top:<?= rand(5,90) ?>%;
                                    left:<?= rand(5,90) ?>%;
                                    opacity:0.7;
                                    animation: starBlink <?= rand(2,5) ?>s infinite alternate;
                                "></div>
                            <?php endfor; ?>

                            <!-- Moon glow circle -->
                            <div style="
                                position:absolute;
                                width:80px; height:80px;
                                border-radius:50%;
                                background:rgba(255,255,255,0.2);
                                top:20px; right:20px;
                                box-shadow:0 0 40px rgba(255,255,255,0.4);
                                "></div>

                            <!-- Profile content -->
                            <div class="card-body text-center p-4" style="position:relative; z-index:1;">

                                <img 
                                    src="<?= htmlspecialchars($p['image']) ?>" 
                                    class="rounded-circle mb-3"
                                    style="
                                        width:160px; height:160px; object-fit:cover;
                                        border:4px solid #fff;
                                        box-shadow:0 0 20px #fff, 0 0 35px #f9f, 0 0 45px #ff0;
                                        animation: imgGlow 2s infinite alternate;
                                    "
                                >

                                <h5 class="fw-bold mb-1 text-white">
                                    <?= htmlspecialchars($p['name']) ?>
                                </h5>

                                <span class="badge px-3 py-2" style="background:#ff0; color:#000;">
                                    Last 24 Hours
                                </span>

                            </div>
                        </div>

                    </div>

                </div>
            <?php 
                $active = '';
            endforeach; ?>

            <?php if(empty($profiles)): ?>
                <div class="carousel-item active">
                    <div class="d-flex align-items-center justify-content-center"
                         style="min-height:400px;">
                        <p class="text-muted fs-5">
                            Last 24 घंटे में कोई एंट्री नहीं
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Controls -->
        <button class="carousel-control-prev" type="button"
                data-bs-target="#profileCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>

        <button class="carousel-control-next" type="button"
                data-bs-target="#profileCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>

    </div>

    <!-- Inline animation keyframes -->
    <script src="attendance_app/js/animations.js" defer></script>

</div>



             <?php
// Total earnings including bonuses (today)
?>

<div style="display:flex; justify-content:space-between; gap:16px; margin-top:20px;">
    <!-- Left: Total Month Earning -->
    <div class="stat-box" style="
        flex:1; 
        padding:20px; 
        border-radius:16px; 
        background: linear-gradient(135deg, #4f46e5, #6366f1);
        color: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-align:center;
        transition: transform 0.3s;
    " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="font-size:14px; opacity:0.8; font-weight:500;">महीने की कुल कमाई</div>
        <div style="font-weight:900; font-size:24px; margin-top:10px;">
            ₹ <?= number_format($totalMonthEarning, 2) ?>
        </div>
    </div>

    <!-- Right: Today's Earning -->
    <div class="stat-box" style="
        flex:1; 
        padding:20px; 
        border-radius:16px; 
        background: linear-gradient(135deg, #10b981, #34d399);
        color: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-align:center;
        transition: transform 0.3s;
    " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="font-size:14px; opacity:0.8; font-weight:500;">अभी तक की कुल कमाई</div>
        <div style="font-weight:900; font-size:24px; margin-top:10px;">
            ₹ <span id="totalEarn"><?= number_format($totalTodayEarning, 2) ?></span>
        </div>
    </div>
</div>

<?php if($lastOpenSession): ?>
<div class="live-card" style="
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    padding:16px 20px; 
    border-radius:16px; 
    background: linear-gradient(135deg, #4ade80, #16a34a);
    color:#fff;
    font-weight:700;
    font-size:16px;
    box-shadow:0 6px 18px rgba(0,0,0,0.15);
    transition: transform 0.3s, box-shadow 0.3s;
    margin-top:20px;
">
    <div class="live-left" style="font-size:15px; opacity:0.9;">
        आज की कमाई <span style="font-weight:800;"><?= date("h:i A", strtotime($lastOpenSession['check_in_time'])) ?></span>
    </div>
    <div class="live-right" style="font-size:18px; font-weight:900;">
        ₹ <span id="liveEarn">0.00</span>
    </div>
</div>


<?php endif; ?>

<!-- Today's Sessions -->
<div class="sessions" style="margin-top:20px; display:flex; flex-direction:column; gap:12px;">
    <?php foreach($todaySessions as $s): ?>
        <?php
            $sessEarning = floatval($s['earning'] ?? 0);
            $sessEarly   = floatval($s['early_checkin_bonus'] ?? 0);
            $sessLate    = floatval($s['late_checkout_bonus'] ?? 0);
        ?>
        <div class="session-item" style="
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
            padding:16px 18px; 
            border-radius:16px; 
            background: linear-gradient(135deg, #f0f4f8, #ffffff);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08); 
            transition: transform 0.3s, box-shadow 0.3s;
        " onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'" 
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 16px rgba(0,0,0,0.08)'">
           
            <!-- Left: Session Time -->
            <div>
                <div style="font-weight:700; font-size:15px; color:#1f2937;"><?= htmlspecialchars($s['created_date']) ?></div>
                <div style="font-size:13px; color:#ef4444; margin-top:2px;">
                    <?= date("h:i A", strtotime($s['check_in_time'])) ?> → 
                    <?= $s['check_out_time'] ? date("h:i A", strtotime($s['check_out_time'])) : '—' ?>
                </div>
            </div>
            
            <!-- Right: Earnings -->
            <div style="text-align:right">
                <div style="font-weight:800; font-size:16px; color:#10b981;">
                   आज की कमाई: ₹ <?= number_format($sessEarning, 2) ?>
                </div>
                <div style="font-size:13px; color:#6b7280; margin-top:4px;">
                    जल्दी आने पर बोनस: <span style="color:#059669;">₹ <?= number_format($sessEarly, 2) ?></span><br>
                   ओवरटाइम बोनस: <span style="color:#dc2626;">₹ <?= number_format($sessLate, 2) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- RIGHT -->
<div class="col-right" style="flex:1; min-width:300px;">
    <div class="controls" style="
        display:flex; 
        justify-content:space-between; 
        align-items:center; 
        padding:14px 16px; 
        border-radius:14px; 
        background: linear-gradient(135deg, #3b82f6, #60a5fa); 
        color:#fff; 
        box-shadow:0 6px 18px rgba(0,0,0,0.1);
        margin-bottom:20px;
    ">
        <div class="controls-left">
            <div style="font-weight:800; font-size:16px;">Attendance Panel</div>
            <div class="small-muted" style="margin-left:4px; font-size:13px; opacity:0.85;">Secure photo check</div>
        </div>

        <div class="actions">
            <?php if(!$lastOpenSession): ?>
                <button id="openCamBtnTop" class="btn-primary-large" style="
                    background:#fff; color:#3b82f6; font-weight:700; padding:8px 16px; border-radius:10px;
                    border:none; box-shadow:0 4px 10px rgba(0,0,0,0.15); cursor:pointer; transition:0.3s;
                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(0,0,0,0.2)';" 
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.15)';">
                    Check In — Start
                </button>
            <?php else: ?>
                <button id="openCamBtnTop" class="btn-danger-large" style="
                    background:#ef4444; color:#fff; font-weight:700; padding:8px 16px; border-radius:10px;
                    border:none; box-shadow:0 4px 10px rgba(0,0,0,0.15); cursor:pointer; transition:0.3s;
                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(0,0,0,0.2)';" 
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.15)';">
                    Check Out — Start
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="camera-wrap" style="background:#f3f4f6; padding:16px; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.08);">
        <div class="camera-card">
            <div style="font-weight:700; font-size:16px; margin-bottom:12px;">Camera Verification</div>
            <div class="video-frame" id="videoFrame" style="
                width:100%; 
                height:240px; 
                background:#000; 
                border-radius:12px; 
                overflow:hidden; 
                display:flex; 
                justify-content:center; 
                align-items:center;
                position:relative;
            ">
                <video id="video" autoplay playsinline muted style="width:100%; height:100%; object-fit:cover; border-radius:12px;"></video>
                <canvas id="canvas" style="display:none;"></canvas>
            </div>

            <div class="capture-controls" style="margin-top:14px; display:flex; justify-content:center; gap:12px;">
                <button id="switchCam" class="small-action" style="
                    padding:6px 12px; font-size:13px; border-radius:8px; border:1px solid #ccc; background:#fff; cursor:pointer;
                    transition:0.3s;
                " onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#fff'">Switch</button>

                <button id="captureBtn" class="round-btn capture" title="Capture" style="
                    width:48px; height:48px; border-radius:50%; background:#10b981; border:none; cursor:pointer;
                    display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(0,0,0,0.15);
                    transition:0.3s;
                " onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                    <span class="dot" style="
                        width:18px; height:18px; border-radius:50%; background:#fff;
                        display:block;
                    "></span>
                </button>

                <button id="retake" class="small-action" style="display:none; padding:6px 12px; font-size:13px; border-radius:8px; border:1px solid #ccc; background:#fff; cursor:pointer;" 
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#fff'">Retake</button>
            </div>

            <div style="margin-top:12px; display:flex; justify-content:center;">
                <div class="small-muted" style="font-size:13px; color:#6b7280;">कैप्चर के बाद नीचे ‘सबमिट’ बटन दबाएँ</div>
            </div>

            <form method="POST" action="attendance_app/api/attendance_action.php" id="attForm" style="margin-top:14px; display:flex; justify-content:center;">
                <input type="hidden" name="photo_data" id="photo_data" value="">
                <?php if(!$lastOpenSession): ?>
                    <button name="checkin" id="submitBtn" class="btn-primary-large" style="
                        display:none; padding:10px 20px; border-radius:12px; font-weight:700;
                        background:#3b82f6; color:#fff; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.15);
                        transition:0.3s;
                    " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)';">Submit Check-In</button>
                <?php else: ?>
                    <button name="checkout" id="submitBtn" class="btn-danger-large" style="
                        display:none; padding:10px 20px; border-radius:12px; font-weight:700;
                        background:#ef4444; color:#fff; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.15);
                        transition:0.3s;
                    " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)';">Submit Check-Out</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Attendance feature configuration and cached JavaScript modules -->
<script>
window.attendanceEarningConfig = <?php echo json_encode($lastOpenSession ? [
    'salaryPerHour' => (float) $salaryPerHour,
    'checkInTime' => strtotime($lastOpenSession['check_in_time']) * 1000,
    'pastEarning' => (float) $totalPastEarning,
    'bonusToday' => (float) ($earlyTodayBonus + $lateTodayBonus),
] : null, JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="attendance_app/js/camera.js" defer></script>
<script src="attendance_app/js/sidebar.js" defer></script>

<!-- Modal -->
<div id="userModal" style="
    display:none; 
    position:fixed; 
    inset:0; 
    background:rgba(0,0,0,0.5); 
    z-index:999; 
    justify-content:center; 
    align-items:center;">
    
    <div style="
        background:#fff; 
        border-radius:16px; 
        padding:20px; 
        max-width:300px; 
        width:90%; 
        text-align:center; 
        position:relative;">
        
        <span style="
            position:absolute; 
            top:10px; 
            right:14px; 
            cursor:pointer; 
            font-size:20px;" 
            onclick="closeUserModal()">&times;</span>

        <img id="modalImg" src="" style="
            width:120px; 
            height:120px; 
            border-radius:50%; 
            object-fit:cover; 
            box-shadow:0 4px 12px rgba(0,0,0,0.15); 
            margin-bottom:12px;">

        <h3 id="modalName" style="font-weight:800; margin:6px 0;"></h3>

        <p style="color:#6b7280; margin:0; font-size:14px;">
             <span id="modalCheckIn"></span>
        </p>
    </div>
</div>
<?php include './Bottom_navbar.php'; ?>





<!-- Bootstrap JS -->
<?php include './Bottom_navbar.php'; ?>
<div style="height: 80px;"></div>
<script src="attendance_app/js/whatsapp.js" defer></script>
<div class="modal fade" id="noticeModal2">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-danger text-white">
<h5 class="modal-title">📢 महत्वपूर्ण सूचना (NOTICE)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body" style="padding-bottom:40px;">

<p>
किट, गैस सिलेंडर तथा अन्य योजनाओं का लाभ उठाने के लिए सभी Contractor को अपने 
<b>महिला बचत (Mahila Bachat) Account</b> में हर महीने के हिसाब से न्यूनतम राशि रखना अनिवार्य होगा।
</p>

<p class="fw-bold">
कृपया नीचे दी गई सूची के अनुसार अपने Mahila Bachat Account में न्यूनतम राशि सुनिश्चित करें:
</p>

<table id="targetAmountTableUnique_2026" style="width:100%; border-collapse:collapse; text-align:center;">

<thead style="background:#212529; color:white;">
<tr>
<th style="padding:10px; border:1px solid #ccc;">महीना</th>
<th style="padding:10px; border:1px solid #ccc;">Target Amount</th>
<th style="padding:10px; border:1px solid #ccc;">कुल बिल की राशि</th>
</tr>
</thead>

<tbody>

<tr data-month="0">
<td style="padding:8px; border:1px solid #ccc;">जनवरी</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
</tr>

<tr data-month="1">
<td style="padding:8px; border:1px solid #ccc;">फ़रवरी</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹1,00,000</td>
</tr>

<tr data-month="2">
<td style="padding:8px; border:1px solid #ccc;">मार्च</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹1,50,000</td>
</tr>

<tr data-month="3">
<td style="padding:8px; border:1px solid #ccc;">अप्रैल</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹2,00,000</td>
</tr>

<tr data-month="4">
<td style="padding:8px; border:1px solid #ccc;">मई</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹2,50,000</td>
</tr>

<tr data-month="5">
<td style="padding:8px; border:1px solid #ccc;">जून</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹3,00,000</td>
</tr>

<tr data-month="6">
<td style="padding:8px; border:1px solid #ccc;">जुलाई</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹3,50,000</td>
</tr>

<tr data-month="7">
<td style="padding:8px; border:1px solid #ccc;">अगस्त</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹4,00,000</td>
</tr>

<tr data-month="8">
<td style="padding:8px; border:1px solid #ccc;">सितंबर</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹4,50,000</td>
</tr>

<tr data-month="9">
<td style="padding:8px; border:1px solid #ccc;">अक्टूबर</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹5,00,000</td>
</tr>

<tr data-month="10">
<td style="padding:8px; border:1px solid #ccc;">नवंबर</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹5,50,000</td>
</tr>

<tr data-month="11">
<td style="padding:8px; border:1px solid #ccc;">दिसंबर</td>
<td style="padding:8px; border:1px solid #ccc;">₹50,000</td>
<td style="padding:8px; border:1px solid #ccc;">₹6,00,000</td>
</tr>

</tbody>

</table>

<p class="text-danger fw-bold">
⚠️ नोट:<br>
किट, गैस सिलेंडर एवं अन्य योजनाओं का लाभ लेने के लिए उपरोक्त राशि का 
<b>Mahila Bachat Account</b> में होना आवश्यक है।
</p>

</div>

</div>
</div>
</div>
<!-- Bootstrap CSS -->


<!-- Modal -->
<!--<style>-->
<!--#videoModal .modal-content{-->
<!--    background:#111;-->
<!--    border-radius:15px;-->
<!--    border:none;-->
<!--    box-shadow:0 0 30px rgba(0,0,0,0.6);-->
<!--}-->

<!--#videoModal .modal-header{-->
<!--    border-bottom:none;-->
<!--    color:#fff;-->
<!--}-->

<!--#videoModal .modal-title{-->
<!--    font-weight:600;-->
<!--    letter-spacing:1px;-->
<!--}-->

<!--#videoModal .btn-close{-->
<!--    filter:invert(1);-->
<!--}-->

<!--#myVideo{-->
<!--    width:100%;-->
<!--    border-radius:12px;-->
<!--    box-shadow:0 10px 25px rgba(0,0,0,0.7);-->
<!--}-->
<!--</style>-->


<!--<div class="modal fade" id="videoModal" tabindex="-1">-->
<!--  <div class="modal-dialog modal-lg modal-dialog-centered">-->
<!--    <div class="modal-content">-->

<!--      <div class="modal-header">-->
        <!--<h5 class="modal-title">🎬 Special Video</h5>-->
<!--        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>-->
<!--      </div>-->

<!--      <div class="modal-body text-center">-->
<!--        <video id="myVideo" width="100%" controls autoplay style="transform:scaleX(1);">-->
<!--  <source src="final.mp4" type="video/mp4">-->
<!--</video>-->
<!--      </div>-->

<!--    </div>-->
<!--  </div>-->
<!--</div>-->
<!-- Bootstrap JS -->
<!---->

<!--<script>-->
<!--window.onload = function () {-->

<!--    let now = new Date();-->
<!--    let hour = now.getHours();-->

    <!--// 6AM to 7AM ke beech modal nahi khulega-->
<!--    if (!(hour >= 6 && hour < 7)) {-->

<!--        var myModal = new bootstrap.Modal(document.getElementById('videoModal'));-->
<!--        myModal.show();-->

<!--    }-->
<!--};-->

<!--// modal close hone par video pause-->
<!--document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {-->
<!--    document.getElementById('myVideo').pause();-->
<!--});-->
<!--</script>-->

<?php 
$block = 'Unblock';
$message = '';
$screenshot = '';
$start_time = '';
$end_time = '';

if($employeeId){

    // ✅ TIME COLUMN ADD
    $query = "SELECT id, employee_id, block, payment_status, screenshot, qr_code, start_time, end_time, created_at 
    FROM employee_paymentss 
    WHERE employee_id='$employeeId' 
    ORDER BY id DESC LIMIT 1";

    $result = $conn->query($query);

    if($result && $result->num_rows > 0){
        $data = $result->fetch_assoc();

        $block = $data['block'] ?? 'Unblock';
        $message = $data['qr_code'] ?? '';
        $screenshot = $data['screenshot'] ?? '';

        // ✅ TIME FETCH
        $start_time = $data['start_time'] ?? '';
        $end_time   = $data['end_time'] ?? '';
    }
}
?>

<!-- ✅ BLOCK MODAL -->
<div class="modal fade" id="blockModal" data-bs-backdrop="static" data-bs-keyboard="false">
  
  <!-- 👇 yaha size bada kiya -->
  <div class="modal-dialog modal-dialog-centered modal-lg">
    
    <div class="modal-content">

      <div class="modal-header bg-danger text-white">
        <h5 class="text-white">Session Expired</h5>
      </div>

      <!-- 👇 height aur bada ki -->
      <div class="modal-body" style="max-height: 500px; overflow-y: auto;">

        <div class="alert alert-warning">
            <?= $message ? nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) : 'No Message Available' ?>
        </div>

        <div class="mt-2 text-danger">
            <b id="timeRange"></b>
        </div>

      </div>

      <div class="modal-footer" id="modalFooter">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>
<?php
date_default_timezone_set("Asia/Kolkata");

// Reuse the office hours defined at the top of this file.

$currentTime = date("H:i:s");

$isOfficeClosed = ($currentTime < $officeStart || $currentTime >= $officeEnd);

$displayOfficeStart = date("h:i A", strtotime($officeStart));
$displayOfficeEnd   = date("h:i A", strtotime($officeEnd));

$isAfterOfficeEnd = ($currentTime >= $officeEnd);
?>

<?php if($isOfficeClosed){ ?>

<div id="officeModal" style="
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,.65);
z-index:999999;
display:flex;
justify-content:center;
align-items:center;
">

    <div style="
    width:340px;
    max-width:90%;
    background:#fff;
    border-radius:15px;
    padding:25px;
    text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,.3);
    ">

        <h3 style="color:red;">
            ⏰ Office Closed
        </h3>

        <p>
            Office Time
        </p>

        <h4 style="color:#0d6efd;">
            <?= $displayOfficeStart ?> - <?= $displayOfficeEnd ?>
        </h4>

        <hr>

        <?php if($isAfterOfficeEnd){ ?>

            <p style="font-size:18px;color:red;font-weight:bold;">
                Office time is over.
            </p>

            <p style="color:#555;">
                Please come back tomorrow during office hours.
            </p>

        <?php }else{ ?>

            <p>
                Office Open Hone Me
            </p>

            <h2 id="countdown" style="color:green;">
                Loading...
            </h2>

        <?php } ?>

    </div>

</div>

<?php } ?>
<script>
window.attendanceOfficeConfig = <?php echo json_encode([
    'closed' => $isOfficeClosed,
    'afterOfficeEnd' => $isAfterOfficeEnd,
    'openTime' => $todayOpenTime,
    'endTime' => $todayEndTime,
], JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="attendance_app/js/office-time.js" defer></script>
<script>
window.attendanceUiConfig = <?php echo json_encode([
    'employeeId' => $employeeId,
    'activeUsers' => $activeUsers,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="attendance_app/js/ui-actions.js" defer></script>
<script src="attendance_app/js/attendance-ajax.js" defer></script>
<script src="attendance_app/js/background-sync.js" defer></script>
</body>

</html>
<?php

monthly_report_sync:
if (!defined('RUN_MONTHLY_REPORT_SYNC')) {
    return;
}

$attendanceData = [];

/* ===== EMPLOYEE ID ===== */

$employee_id = (string) ($_SESSION['emp_id'] ?? '');
// Debug output after </html> intentionally disabled.

/* ===== ATTENDANCE DATA FETCH ===== */

if(!empty($employee_id)){

$stmt = $conn->prepare("
SELECT 
    DATE_FORMAT(a.check_in_time,'%Y-%m') AS month,

    COUNT(DISTINCT CASE 
        WHEN a.check_in_time IS NOT NULL 
         AND a.check_out_time IS NOT NULL
        THEN DATE(a.check_in_time)
    END) AS total_present_days,

    COUNT(DISTINCT CASE 
        WHEN a.check_in_time IS NOT NULL 
         AND TIME(a.check_in_time) >= '06:00:00'
         AND TIME(a.check_in_time) <  '06:02:00'
        THEN DATE(a.check_in_time)
    END) AS early_6am_days,

    (
        SELECT es.bill_amount
        FROM employee_sales es
        WHERE es.employee_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(es.sale_date,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
        ORDER BY es.sale_date DESC, es.id DESC
        LIMIT 1
    ) AS last_bill_amount,

    (
        SELECT es.commission_amount
        FROM employee_sales es
        WHERE es.employee_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(es.sale_date,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
        ORDER BY es.sale_date DESC, es.id DESC
        LIMIT 1
    ) AS last_sale_commission,

    (
        SELECT es.pdf
        FROM employee_sales es
        WHERE es.employee_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(es.sale_date,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
        ORDER BY es.sale_date DESC, es.id DESC
        LIMIT 1
    ) AS last_pdf,

    IFNULL(( 
        SELECT SUM(k.kit_amount)
        FROM employee_kits k
        WHERE k.employee_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(k.kit_date,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
    ),0) AS total_kit_amount,

    IFNULL(( 
        SELECT SUM(g.amount)
        FROM gas_cylinders g
        WHERE g.emp_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(g.created_at,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
    ),0) AS total_gas_amount,

    IFNULL(( 
        SELECT SUM(g.number_of_cylinders)
        FROM gas_cylinders g
        WHERE g.emp_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(g.created_at,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
    ),0) AS total_gas_cylinders,

    IFNULL(( 
        SELECT e.carry_forward_amount
        FROM employee_commission_carryforward_amount e
        WHERE e.emp_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND e.carryforward_month = DATE_FORMAT(a.check_in_time,'%Y-%m')
        LIMIT 1
    ),0) AS carry_forward_amount,

    IFNULL(( 
        SELECT p.paid_amount
        FROM employee_paid_amount p
        WHERE p.emp_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(p.payment_date,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
        ORDER BY p.payment_date DESC
        LIMIT 1
    ),0) AS paid_amount,

    IFNULL((
        SELECT p.payment_date
        FROM employee_paid_amount p
        WHERE p.emp_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(p.payment_date,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
        ORDER BY p.payment_date DESC
        LIMIT 1
    ),NULL) AS payment_date,

    IFNULL((
        SELECT p.screenshot
        FROM employee_paid_amount p
        WHERE p.emp_id COLLATE utf8mb4_unicode_ci 
        = a.employee_id COLLATE utf8mb4_unicode_ci
        AND DATE_FORMAT(p.payment_date,'%Y-%m') 
        = DATE_FORMAT(a.check_in_time,'%Y-%m')
        ORDER BY p.payment_date DESC
        LIMIT 1
    ),NULL) AS payment_screenshot

FROM employee_attendance a
WHERE a.employee_id COLLATE utf8mb4_unicode_ci = ?
GROUP BY DATE_FORMAT(a.check_in_time,'%Y-%m')
ORDER BY month DESC
");

if($stmt){
    $stmt->bind_param("s",$employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while($row = $result->fetch_assoc()){
        $attendanceData[] = $row;
    }

    $stmt->close();
} else {
    error_log("employee monthly report fetch prepare failed: " . $conn->error);
}
}


/* ===== INSERT OR UPDATE REPORT ===== */

if(!empty($attendanceData)){

foreach($attendanceData as $data){

$totalAmount = ($data['carry_forward_amount'] ?? 0)
             + ($data['last_sale_commission'] ?? 0)
             + ($data['total_kit_amount'] ?? 0)
             + ($data['total_gas_amount'] ?? 0);

$leftAmount = $totalAmount - ($data['paid_amount'] ?? 0);

$six_am_bonus = ($data['early_6am_days'] ?? 0) * 25;

/* ===== CHECK RECORD EXIST ===== */

$check = $conn->prepare("SELECT id FROM employee_monthly_report WHERE employee_id=? AND month=? LIMIT 1");
if(!$check){
    error_log("employee monthly report check prepare failed: " . $conn->error);
    continue;
}
$check->bind_param("ss",$employee_id,$data['month']);
$check->execute();
$check->store_result();


if($check->num_rows > 0){

/* ===== UPDATE ===== */

$update = $conn->prepare("
UPDATE employee_monthly_report SET
present_days=?,
early_6am_days=?,
six_am_bonus=?,
last_bill_amount=?,
commission_amount=?,
kit_amount=?,
gas_amount=?,
gas_cylinders=?,
carry_forward_amount=?,
total_amount=?,
paid_amount=?,
left_amount=?,
payment_date=?,
pdf=?,
payment_screenshot=?
WHERE employee_id=? AND month=?
");

if($update){
$update->bind_param(
"iiidddddddddsssss",

$data['total_present_days'],
$data['early_6am_days'],
$six_am_bonus,
$data['last_bill_amount'],
$data['last_sale_commission'],
$data['total_kit_amount'],
$data['total_gas_amount'],
$data['total_gas_cylinders'],
$data['carry_forward_amount'],
$totalAmount,
$data['paid_amount'],
$leftAmount,
$data['payment_date'],
$data['last_pdf'],
$data['payment_screenshot'],
$employee_id,
$data['month']
);

$update->execute();
$update->close();
} else {
    error_log("employee monthly report update prepare failed: " . $conn->error);
}

}else{

/* ===== INSERT ===== */

$insert = $conn->prepare("
INSERT INTO employee_monthly_report
(employee_id,month,present_days,early_6am_days,six_am_bonus,
last_bill_amount,commission_amount,kit_amount,gas_amount,gas_cylinders,
carry_forward_amount,total_amount,paid_amount,left_amount,
payment_date,pdf,payment_screenshot)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

if($insert){
$insert->bind_param(
"ssiiddddddddddsss",

$employee_id,
$data['month'],
$data['total_present_days'],
$data['early_6am_days'],
$six_am_bonus,
$data['last_bill_amount'],
$data['last_sale_commission'],
$data['total_kit_amount'],
$data['total_gas_amount'],
$data['total_gas_cylinders'],
$data['carry_forward_amount'],
$totalAmount,
$data['paid_amount'],
$leftAmount,
$data['payment_date'],
$data['last_pdf'],
$data['payment_screenshot']
);

$insert->execute();
$insert->close();
} else {
    error_log("employee monthly report insert prepare failed: " . $conn->error);
}

}

$check->close();

}

}

?>




