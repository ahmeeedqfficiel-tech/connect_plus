<?php
/**
 * CONNECT+ - دوال التحقق من الصلاحيات
 * الإصدار: 2.0
 */

// ============================================================
// التحقق من صلاحية المريض
// ============================================================
function requirePatient($pdo) {
    if (!isset($_SESSION['user_id'])) {
        setToast('الرجاء تسجيل الدخول أولاً', 'error');
        redirect('../login.php');
    }
    
    $stmt = $pdo->prepare("SELECT role, is_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'patient') {
        setToast('غير مصرح لك بالوصول إلى هذه الصفحة', 'error');
        redirect('../index.php');
    }
    
    if (!$user['is_verified']) {
        redirect('../pending_approval.php');
    }
}

// ============================================================
// التحقق من صلاحية الطبيب
// ============================================================
function requireDoctor($pdo) {
    if (!isset($_SESSION['user_id'])) {
        redirect('../login.php');
    }
    
    $stmt = $pdo->prepare("SELECT role, is_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'doctor') {
        redirect('../index.php');
    }
    
    if (!$user['is_verified']) {
        redirect('../pending_approval.php');
    }
}

// ============================================================
// التحقق من صلاحية الصيدلي
// ============================================================
function requirePharmacist($pdo) {
    if (!isset($_SESSION['user_id'])) {
        redirect('../login.php');
    }
    
    $stmt = $pdo->prepare("SELECT role, is_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'pharmacist') {
        redirect('../index.php');
    }
    
    if (!$user['is_verified']) {
        redirect('../pending_approval.php');
    }
}

// ============================================================
// التحقق من صلاحية الأدمن
// ============================================================
function requireAdmin($pdo) {
    if (!isset($_SESSION['user_id'])) {
        redirect('../login.php');
    }
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
    
    if ($role !== 'admin') {
        redirect('../index.php');
    }
}
?>