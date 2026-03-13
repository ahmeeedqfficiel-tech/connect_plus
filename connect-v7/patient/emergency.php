<?php
/**
 * patient/emergency.php - صفحة الطوارئ
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم مريض
requirePatient($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);
$patient = getPatientData($pdo, $user['id']);

// ============================================================
// معالجة طلب الطوارئ
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // تسجيل تنبيه الطوارئ
        $stmt = $pdo->prepare("INSERT INTO emergency_alerts (patient_id, alert_time, latitude, longitude, status) VALUES (?, NOW(), ?, ?, 'pending')");
        $stmt->execute([$user['id'], $latitude, $longitude]);
        
        // إشعار للمريض
        createNotification($pdo, $user['id'], 'emergency', 'تم إرسال تنبيه الطوارئ', 'سيتم الاتصال بك قريباً');
        
        // إشعار للأدمن (يمكن إضافته لاحقاً)
        
        $pdo->commit();
        
        setToast('تم إرسال تنبيه الطوارئ بنجاح', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('حدث خطأ: ' . $e->getMessage(), 'error');
    }
    
    redirect('emergency.php');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطوارئ - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .emergency-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        
        .emergency-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .emergency-icon {
            font-size: 6rem;
            color: var(--danger);
            animation: pulse 2s infinite;
            margin-bottom: 1rem;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .emergency-title {
            font-size: 2.5rem;
            color: var(--danger);
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .emergency-subtitle {
            color: var(--gray);
            font-size: 1.2rem;
        }
        
        .emergency-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: 2px solid var(--danger);
        }
        
        body.dark-mode .emergency-card {
            background: #1E1E1E;
        }
        
        .emergency-btn {
            width: 100%;
            padding: 2rem;
            background: linear-gradient(135deg, var(--danger), #ff6b6b);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 2rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            margin: 2rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            box-shadow: 0 10px 30px rgba(231, 111, 81, 0.4);
        }
        
        .emergency-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 20px 40px rgba(231, 111, 81, 0.6);
        }
        
        .emergency-btn i {
            font-size: 2.5rem;
        }
        
        .info-section {
            background: var(--light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        body.dark-mode .info-section {
            background: #2D2D2D;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .contact-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border-right: 4px solid var(--primary);
        }
        
        body.dark-mode .contact-card {
            background: #1E1E1E;
        }
        
        .contact-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .contact-phone {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            direction: ltr;
            text-align: right;
        }
        
        .emergency-numbers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .number-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .number-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .number-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .number-card .number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .number-card .service {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .location-status {
            background: rgba(42, 157, 143, 0.1);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .location-status i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .location-status.success {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .location-status.error {
            background: rgba(231, 111, 81, 0.1);
            color: var(--danger);
        }
        
        .dev-note {
            background: rgba(244, 162, 97, 0.1);
            border-right: 4px solid var(--warning);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <!-- تضمين القائمة الجانبية -->
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <!-- تضمين الشريط العلوي -->
            <?php include 'header.php'; ?>
            
            <!-- رسائل Toast -->
            <?php displayToast(); ?>
            
            <div class="emergency-container">
                <div class="emergency-header">
                    <div class="emergency-icon">
                        <i class="fas fa-ambulance"></i>
                    </div>
                    <h1 class="emergency-title">خدمة الطوارئ</h1>
                    <p class="emergency-subtitle">في حالة الطوارئ الصحية، اضغط على الزر الأحمر</p>
                </div>
                
                <div class="emergency-card">
                    <form method="post" id="emergencyForm">
                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">
                        
                        <!-- حالة الموقع -->
                        <div id="locationStatus" class="location-status">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>جاري تحديد موقعك...</span>
                        </div>
                        
                        <button type="submit" class="emergency-btn" onclick="return confirmEmergency()">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>إرسال تنبيه الطوارئ</span>
                        </button>
                    </form>
                    
                    <div class="dev-note">
                        <i class="fas fa-microphone"></i>
                        <strong>ميزة قيد التطوير:</strong> إرسال تنبيه صوتي تلقائي
                    </div>
                </div>
                
                <!-- أرقام الطوارئ -->
                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-phone-alt"></i>
                        أرقام الطوارئ الوطنية
                    </h3>
                    
                    <div class="emergency-numbers">
                        <div class="number-card" onclick="callNumber('14')">
                            <i class="fas fa-ambulance"></i>
                            <div class="number">14</div>
                            <div class="service">الإسعاف</div>
                        </div>
                        
                        <div class="number-card" onclick="callNumber('17')">
                            <i class="fas fa-shield-alt"></i>
                            <div class="number">17</div>
                            <div class="service">الشرطة</div>
                        </div>
                        
                        <div class="number-card" onclick="callNumber('1548')">
                            <i class="fas fa-fire-extinguisher"></i>
                            <div class="number">14</div>
                            <div class="service">الحماية المدنية</div>
                        </div>
                        
                        <div class="number-card" onclick="callNumber('115')">
                            <i class="fas fa-heartbeat"></i>
                            <div class="number">115</div>
                            <div class="service">سموم</div>
                        </div>
                    </div>
                </div>
                
                <!-- جهة اتصال الطوارئ -->
                <?php if (!empty($patient['emergency_name'])): ?>
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-shield"></i>
                            جهة اتصال الطوارئ
                        </h3>
                        
                        <div class="contact-card">
                            <div class="contact-name">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($patient['emergency_name']) ?>
                            </div>
                            <div class="contact-phone" onclick="callNumber('<?= $patient['emergency_phone'] ?>')" style="cursor: pointer;">
                                <i class="fas fa-phone"></i> <?= $patient['emergency_phone'] ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- معلومات إضافية -->
                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        معلومات مهمة
                    </h3>
                    
                    <ul style="margin-right: 1.5rem;">
                        <li style="margin-bottom: 0.5rem;">سيتم مشاركة موقعك الحالي مع فريق الطوارئ</li>
                        <li style="margin-bottom: 0.5rem;">سيتم إشعار جهة اتصال الطوارئ المسجلة</li>
                        <li style="margin-bottom: 0.5rem;">تأكد من تفعيل خدمة الموقع في جهازك</li>
                        <li style="margin-bottom: 0.5rem;">في الحالات الحرجة، اتصل مباشرة على الرقم 14</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // الحصول على موقع المستخدم
    // ============================================================
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                
                const status = document.getElementById('locationStatus');
                status.className = 'location-status success';
                status.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <span>تم تحديد موقعك بنجاح</span>
                `;
            },
            function(error) {
                const status = document.getElementById('locationStatus');
                status.className = 'location-status error';
                
                let message = '';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'تم رفض الوصول إلى الموقع. يرجى تفعيل خدمة الموقع';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'معلومات الموقع غير متوفرة';
                        break;
                    case error.TIMEOUT:
                        message = 'انتهت مهلة تحديد الموقع';
                        break;
                    default:
                        message = 'خطأ في تحديد الموقع';
                }
                
                status.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>${message}</span>
                `;
                
                showToast('تعذر تحديد موقعك، يرجى تفعيل خدمة الموقع', 'warning');
            }
        );
    } else {
        const status = document.getElementById('locationStatus');
        status.className = 'location-status error';
        status.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <span>المتصفح لا يدعم تحديد الموقع</span>
        `;
        showToast('المتصفح لا يدعم تحديد الموقع', 'error');
    }
    
    // ============================================================
    // تأكيد إرسال تنبيه الطوارئ
    // ============================================================
    function confirmEmergency() {
        return confirm('⚠️ هل أنت متأكد من إرسال تنبيه الطوارئ؟\n\nسيتم إشعار فريق الطوارئ وجهات الاتصال المسجلة.');
    }
    
    // ============================================================
    // الاتصال برقم
    // ============================================================
    function callNumber(number) {
        if (confirm(`هل تريد الاتصال بالرقم ${number}؟`)) {
            window.location.href = `tel:${number}`;
        }
    }
    </script>
</body>
</html>