<?php
/**
 * CONNECT+ - الدوال العامة
 * الإصدار: 2.0 النهائي
 */

// ============================================================
// دوال تنظيف المدخلات
// ============================================================

/**
 * تنظيف بيانات الإدخال من الأحرف الضارة
 * @param mixed $data البيانات المراد تنظيفها
 * @return mixed البيانات بعد التنظيف
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * إعادة توجيه المستخدم إلى صفحة أخرى
 * @param string $url الرابط المراد التوجيه إليه
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

// ============================================================
// دوال الجلسة والمستخدم
// ============================================================

/**
 * التحقق من وجود جلسة نشطة
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * جلب بيانات المستخدم الحالي
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @return array|null بيانات المستخدم أو null
 */
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * إنشاء كود فريد للمستخدم
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param string $role دور المستخدم
 * @return string الكود الفريد
 */
function generateUserCode($pdo, $role) {
    $prefix = '#';
    switch($role) {
        case 'patient':
            $prefix .= 'PAT';
            break;
        case 'doctor':
            $prefix .= 'DOC';
            break;
        case 'pharmacist':
            $prefix .= 'PHA';
            break;
        case 'admin':
            $prefix .= 'ADM';
            break;
        default:
            $prefix .= 'USR';
    }
    
    // جلب آخر رقم مستخدم
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
    $stmt->execute([$role]);
    $count = $stmt->fetchColumn() + 1;
    
    return $prefix . str_pad($count, 6, '0', STR_PAD_LEFT);
}

// ============================================================
// دوال جلب البيانات الخاصة بالأدوار
// ============================================================

/**
 * جلب بيانات المريض
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @return array|null
 */
function getPatientData($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * جلب بيانات الطبيب
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @return array|null
 */
function getDoctorData($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * جلب بيانات الصيدلي
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @return array|null
 */
function getPharmacistData($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM pharmacists WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * جلب بيانات الأدمن
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @return array|null
 */
function getAdminData($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// ============================================================
// دوال رفع الملفات
// ============================================================

/**
 * رفع ملف إلى الخادم
 * @param array $file بيانات الملف من $_FILES
 * @param string $targetDir المجلد المستهدف (بدون '../')
 * @param array $allowedTypes أنواع الملفات المسموحة
 * @return array نتيجة العملية (success, path/message)
 */
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf']) {
    // إنشاء المجلد إذا لم يكن موجوداً
    $fullPath = __DIR__ . '/../' . $targetDir;
    if (!file_exists($fullPath)) {
        mkdir($fullPath, 0777, true);
    }
    
    // التحقق من وجود خطأ
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف'];
    }
    
    // التحقق من الحجم (حد أقصى 5 ميجابايت)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 5 ميجابايت)'];
    }
    
    // التحقق من الامتداد
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'message' => 'نوع الملف غير مسموح به'];
    }
    
    // إنشاء اسم فريد للملف
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $targetPath = $fullPath . '/' . $filename;
    
    // رفع الملف
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true,
            'path' => $targetDir . '/' . $filename,
            'filename' => $filename
        ];
    }
    
    return ['success' => false, 'message' => 'فشل في حفظ الملف'];
}

/**
 * رفع صورة شخصية
 * @param array $file بيانات الملف
 * @param int $user_id معرف المستخدم
 * @return array
 */
function uploadProfileImage($file, $user_id) {
    return uploadFile($file, 'uploads/profiles');
}

/**
 * رفع مستند
 * @param array $file بيانات الملف
 * @param string $type نوع المستند
 * @param int $user_id معرف المستخدم
 * @return array
 */
function uploadDocument($file, $type, $user_id) {
    $folder = 'uploads/documents/' . $type;
    return uploadFile($file, $folder, ['jpg', 'jpeg', 'png', 'pdf']);
}

// ============================================================
// دوال الإشعارات
// ============================================================

/**
 * إنشاء إشعار جديد
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @param string $type نوع الإشعار (appointment, message, order, approval, emergency)
 * @param string $title عنوان الإشعار
 * @param string $content محتوى الإشعار
 * @param string|null $link رابط إضافي
 * @return bool
 */
function createNotification($pdo, $user_id, $type, $title, $content, $link = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, content, link) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $type, $title, $content, $link]);
}

/**
 * جلب عدد الإشعارات غير المقروءة
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @return int
 */
function getUnreadNotificationsCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * جلب آخر الإشعارات
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @param int $limit عدد الإشعارات
 * @return array
 */
function getRecentNotifications($pdo, $user_id, $limit = 10) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * تعليم إشعار كمقروء
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $notification_id معرف الإشعار
 * @return bool
 */
function markNotificationAsRead($pdo, $notification_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
    return $stmt->execute([$notification_id]);
}

/**
 * تعليم كل الإشعارات كمقروءة
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @return bool
 */
function markAllNotificationsAsRead($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    return $stmt->execute([$user_id]);
}

// ============================================================
// دوال تنسيق الوقت والتاريخ
// ============================================================

/**
 * حساب الوقت المنقضي
 * @param string $datetime التاريخ والوقت
 * @return string
 */
function timeAgo($datetime) {
    if (!$datetime) return '';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'الآن';
    if ($diff < 3600) return 'منذ ' . floor($diff/60) . ' دقيقة';
    if ($diff < 86400) return 'منذ ' . floor($diff/3600) . ' ساعة';
    if ($diff < 2592000) return 'منذ ' . floor($diff/86400) . ' يوم';
    if ($diff < 31536000) return 'منذ ' . floor($diff/2592000) . ' شهر';
    
    return 'منذ ' . floor($diff/31536000) . ' سنة';
}

/**
 * تنسيق التاريخ
 * @param string $date التاريخ
 * @param string $format صيغة التاريخ
 * @return string
 */
function formatDate($date, $format = 'Y-m-d') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * تنسيق التاريخ والوقت
 * @param string $datetime التاريخ والوقت
 * @return string
 */
function formatDateTime($datetime) {
    if (!$datetime) return '';
    return date('Y-m-d H:i', strtotime($datetime));
}

/**
 * جلب اسم اليوم بالعربية
 * @param string $date التاريخ
 * @return string
 */
function getDayName($date) {
    $days = [
        'Sunday' => 'الأحد',
        'Monday' => 'الاثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت'
    ];
    
    $day = date('l', strtotime($date));
    return $days[$day] ?? $day;
}

// ============================================================
// دوال النصوص المترجمة
// ============================================================

/**
 * الحصول على اسم الدور بالعربية
 * @param string $role الدور
 * @return string
 */
function getRoleText($role) {
    $roles = [
        'patient' => 'مريض',
        'doctor' => 'طبيب',
        'pharmacist' => 'صيدلي',
        'admin' => 'أدمن'
    ];
    return $roles[$role] ?? $role;
}

/**
 * الحصول على نص الحالة
 * @param string $status الحالة
 * @return string
 */
function getStatusText($status) {
    $statuses = [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'delivered' => 'تم التسليم',
        'ready' => 'جاهز',
        'paid' => 'مدفوع',
        'unpaid' => 'غير مدفوع'
    ];
    return $statuses[$status] ?? $status;
}

/**
 * الحصول على نص نوع الإشعار
 * @param string $type النوع
 * @return string
 */
function getNotificationTypeText($type) {
    $types = [
        'appointment' => 'موعد',
        'message' => 'رسالة',
        'order' => 'طلب',
        'approval' => 'توثيق',
        'emergency' => 'طوارئ',
        'prescription' => 'وصفة',
        'reminder' => 'تذكير'
    ];
    return $types[$type] ?? $type;
}

// ============================================================
// دوال رسائل Toast
// ============================================================

/**
 * حفظ رسالة toast في الجلسة
 * @param string $message نص الرسالة
 * @param string $type نوع الرسالة (success, error, warning, info)
 */
function setToast($message, $type = 'success') {
    $_SESSION['toast'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * عرض رسالة toast المحفوظة
 */
function displayToast() {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        echo "<script>showToast('{$toast['message']}', '{$toast['type']}');</script>";
    }
}

// ============================================================
// دوال عرض النجوم للتقييم
// ============================================================

/**
 * عرض النجوم للتقييم
 * @param float $rating قيمة التقييم
 * @param bool $showNumber عرض الرقم
 * @return string HTML النجوم
 */
function renderStars($rating, $showNumber = true) {
    $rating = round($rating * 2) / 2; // تقريب لأقرب نصف
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $stars = '';
    
    // نجوم كاملة
    for ($i = 1; $i <= $fullStars; $i++) {
        $stars .= '<i class="fas fa-star" style="color: #F4A261;"></i>';
    }
    
    // نصف نجمة
    if ($halfStar) {
        $stars .= '<i class="fas fa-star-half-alt" style="color: #F4A261;"></i>';
    }
    
    // نجوم فارغة
    for ($i = 1; $i <= $emptyStars; $i++) {
        $stars .= '<i class="far fa-star" style="color: #F4A261;"></i>';
    }
    
    if ($showNumber) {
        $stars .= ' <span style="color: var(--gray);">(' . number_format($rating, 1) . ')</span>';
    }
    
    return $stars;
}

// ============================================================
// دوال مساعدة
// ============================================================

/**
 * إنشاء رمز عشوائي
 * @param int $length طول الرمز
 * @return string
 */
function generateRandomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

/**
 * التحقق من صحة البريد الإلكتروني
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * التحقق من صحة رقم الهاتف الجزائري
 * @param string $phone
 * @return bool
 */
function isValidAlgerianPhone($phone) {
    return preg_match('/^(05|06|07)[0-9]{8}$/', $phone);
}

/**
 * تنسيق رقم الهاتف
 * @param string $phone
 * @return string
 */
function formatPhoneNumber($phone) {
    if (strlen($phone) == 10) {
        return substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5, 3);
    }
    return $phone;
}

/**
 * اقتطاع النص
 * @param string $text النص
 * @param int $length الطول
 * @param string $suffix النهاية
 * @return string
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * تسجيل نشاط في السجل
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int|null $user_id معرف المستخدم
 * @param string $action الإجراء
 * @param string $description الوصف
 * @return bool
 */
function logActivity($pdo, $user_id, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // معلومات الجهاز (مبسطة)
    $device_info = json_encode([
        'browser' => getBrowserName($user_agent),
        'platform' => getPlatformName($user_agent)
    ]);
    
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, device_info) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $action, $description, $ip, $user_agent, $device_info]);
}

/**
 * جلب اسم المتصفح
 * @param string $user_agent
 * @return string
 */
function getBrowserName($user_agent) {
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    if (strpos($user_agent, 'MSIE') !== false) return 'Internet Explorer';
    return 'Unknown';
}

/**
 * جلب اسم نظام التشغيل
 * @param string $user_agent
 * @return string
 */
function getPlatformName($user_agent) {
    if (strpos($user_agent, 'Windows') !== false) return 'Windows';
    if (strpos($user_agent, 'Mac') !== false) return 'macOS';
    if (strpos($user_agent, 'Linux') !== false) return 'Linux';
    if (strpos($user_agent, 'Android') !== false) return 'Android';
    if (strpos($user_agent, 'iPhone') !== false) return 'iOS';
    return 'Unknown';
}

// ============================================================
// دوال الإحصائيات
// ============================================================

/**
 * جلب إحصائيات المستخدم
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @param int $user_id معرف المستخدم
 * @return array
 */
function getUserStats($pdo, $user_id) {
    $stats = [];
    
    // عدد المواعيد
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $stmt->execute([$user_id]);
    $stats['appointments'] = $stmt->fetchColumn();
    
    // عدد الوصفات
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?");
    $stmt->execute([$user_id]);
    $stats['prescriptions'] = $stmt->fetchColumn();
    
    // عدد الرسائل
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?");
    $stmt->execute([$user_id, $user_id]);
    $stats['messages'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * جلب إحصائيات النظام
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات
 * @return array
 */
function getSystemStats($pdo) {
    return [
        'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'patients' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn(),
        'doctors' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn(),
        'pharmacists' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pharmacist'")->fetchColumn(),
        'appointments' => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
        'prescriptions' => $pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn(),
        'orders' => $pdo->query("SELECT COUNT(*) FROM medicine_orders")->fetchColumn(),
        'messages' => $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
    ];
}

// ============================================================
// دوال الأمان
// ============================================================

/**
 * توليد token CSRF
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من token CSRF
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * تشفير كلمة المرور (للاستخدام المستقبلي)
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * التحقق من كلمة المرور (للاستخدام المستقبلي)
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================================
// دوال التصدير
// ============================================================

/**
 * تصدير البيانات إلى CSV
 * @param array $data البيانات
 * @param array $headers رؤوس الأعمدة
 * @param string $filename اسم الملف
 */
function exportToCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM للعربية
    
    fputcsv($output, $headers);
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * تصدير البيانات إلى JSON
 * @param array $data البيانات
 * @param string $filename اسم الملف
 */
function exportToJSON($data, $filename) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// تصدير الدوال للاستخدام العام
// ============================================================
// جميع الدوال معرفة عامة بالفعل
?>