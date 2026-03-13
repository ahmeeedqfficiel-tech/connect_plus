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
$rating = (int)($_POST['rating'] ?? 0);
$comment = cleanInput($_POST['comment'] ?? '');

if (!$doctor_id || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM ratings WHERE doctor_id = ? AND patient_id = ?");
    $stmt->execute([$doctor_id, $patient_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE ratings SET rating = ?, comment = ? WHERE doctor_id = ? AND patient_id = ?");
        $result = $stmt->execute([$rating, $comment, $doctor_id, $patient_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO ratings (doctor_id, patient_id, rating, comment) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$doctor_id, $patient_id, $rating, $comment]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'تم إرسال التقييم']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل إرسال التقييم']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>