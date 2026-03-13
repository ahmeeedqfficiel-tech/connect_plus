<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$pharmacist_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$is_24h = isset($data['is_24h']) ? (int)$data['is_24h'] : null;

if ($is_24h === null) {
    $stmt = $pdo->prepare("SELECT is_24h FROM pharmacists WHERE user_id = ?");
    $stmt->execute([$pharmacist_id]);
    $current = $stmt->fetchColumn();
    $is_24h = $current ? 0 : 1;
}

try {
    $stmt = $pdo->prepare("UPDATE pharmacists SET is_24h = ? WHERE user_id = ?");
    $stmt->execute([$is_24h, $pharmacist_id]);
    
    echo json_encode(['success' => true, 'is_24h' => $is_24h]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>