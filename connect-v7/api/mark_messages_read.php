<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$sender_id = $data['sender_id'] ?? 0;

if (!$sender_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE messages SET is_read = 1, read_at = NOW() 
    WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
");
$stmt->execute([$sender_id, $user_id]);

echo json_encode(['success' => true]);
?>