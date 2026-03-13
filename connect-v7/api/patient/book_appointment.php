<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$patient_id = $_SESSION['user_id'];
$doctor_id = (int)($_POST['doctor_id'] ?? 0);
$appointment_date = $_POST['appointment_date'] ?? '';
$reason = cleanInput($_POST['reason'] ?? '');

if (!$doctor_id || !$appointment_date) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $result = $stmt->execute([$patient_id, $doctor_id, $appointment_date, $reason]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'تم حجز الموعد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل الحجز']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>