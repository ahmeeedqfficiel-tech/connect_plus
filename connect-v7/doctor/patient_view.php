<?php
/**
 * doctor/patient_view.php - عرض ملف مريض كامل
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم طبيب
requireDoctor($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);
$patient_id = (int)($_GET['id'] ?? 0);

if (!$patient_id) {
    setToast('معرف المريض غير صحيح', 'error');
    redirect('patients.php');
}

// ============================================================
// جلب بيانات المريض
// ============================================================
$stmt = $pdo->prepare("
    SELECT u.*, p.* 
    FROM users u 
    JOIN patients p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    setToast('المريض غير موجود', 'error');
    redirect('patients.php');
}

// التحقق من أن هذا المريض يتبع هذا الطبيب
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND patient_id = ?");
$stmt->execute([$user['id'], $patient_id]);
if ($stmt->fetchColumn() == 0) {
    setToast('لا يمكنك الوصول إلى هذا المريض', 'error');
    redirect('patients.php');
}

// ============================================================
// معالجة إضافة وصفة جديدة
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prescription'])) {
    $diagnosis = cleanInput($_POST['diagnosis']);
    $notes = cleanInput($_POST['notes']);
    $medicines = json_decode($_POST['medicines_data'], true) ?: [];
    
    try {
        $pdo->beginTransaction();
        
        // إضافة الوصفة
        $stmt = $pdo->prepare("INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, diagnosis, notes) VALUES (?, ?, CURDATE(), ?, ?)");
        $stmt->execute([$patient_id, $user['id'], $diagnosis, $notes]);
        $prescription_id = $pdo->lastInsertId();
        
        // إضافة الأدوية
        if (!empty($medicines)) {
            $stmt = $pdo->prepare("INSERT INTO prescription_medicines (prescription_id, medicine_name, dosage, frequency, duration, start_date, instructions, status) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'active')");
            foreach ($medicines as $med) {
                $stmt->execute([$prescription_id, $med['name'], $med['dosage'], $med['frequency'], $med['duration'], $med['instructions'] ?? null]);
            }
        }
        
        // إضافة إلى السجل الطبي
        $stmt = $pdo->prepare("INSERT INTO medical_records (patient_id, doctor_id, visit_date, diagnosis, notes) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->execute([$patient_id, $user['id'], $diagnosis, $notes]);
        
        // إشعار للمريض
        createNotification($pdo, $patient_id, 'prescription', 'وصفة طبية جديدة', 'تم إضافة وصفة طبية جديدة من قبل الدكتور ' . $user['full_name']);
        
        $pdo->commit();
        
        setToast('تم إضافة الوصفة بنجاح', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('حدث خطأ: ' . $e->getMessage(), 'error');
    }
    
    redirect('patient_view.php?id=' . $patient_id);
}

// ============================================================
// معالجة إضافة موعد جديد
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $appointment_date = $_POST['appointment_date'];
    $reason = cleanInput($_POST['reason']);
    $notes = cleanInput($_POST['notes']);
    
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, notes, status) VALUES (?, ?, ?, ?, ?, 'confirmed')");
    $stmt->execute([$patient_id, $user['id'], $appointment_date, $reason, $notes]);
    
    // إشعار للمريض
    createNotification($pdo, $patient_id, 'appointment', 'موعد جديد', 'تم حجز موعد جديد مع الدكتور ' . $user['full_name'] . ' في ' . $appointment_date);
    
    setToast('تم إضافة الموعد بنجاح', 'success');
    redirect('patient_view.php?id=' . $patient_id);
}

// ============================================================
// جلب البيانات
// ============================================================

// 1. الأدوية النشطة
$stmt = $pdo->prepare("
    SELECT pm.*, p.prescription_date 
    FROM prescription_medicines pm 
    JOIN prescriptions p ON pm.prescription_id = p.id 
    WHERE p.patient_id = ? AND pm.status = 'active' 
    ORDER BY pm.start_date DESC
");
$stmt->execute([$patient_id]);
$activeMeds = $stmt->fetchAll();

// 2. أرشيف الأدوية
$stmt = $pdo->prepare("
    SELECT pm.*, p.prescription_date, p.diagnosis
    FROM prescription_medicines pm 
    JOIN prescriptions p ON pm.prescription_id = p.id 
    WHERE p.patient_id = ? AND pm.status != 'active'
    ORDER BY p.prescription_date DESC
    LIMIT 20
");
$stmt->execute([$patient_id]);
$archiveMeds = $stmt->fetchAll();

// 3. الوصفات الطبية
$stmt = $pdo->prepare("
    SELECT p.* 
    FROM prescriptions p
    WHERE p.patient_id = ? AND p.doctor_id = ?
    ORDER BY p.prescription_date DESC
    LIMIT 20
");
$stmt->execute([$patient_id, $user['id']]);
$prescriptions = $stmt->fetchAll();

// جلب أدوية كل وصفة
$prescriptionMeds = [];
foreach ($prescriptions as $p) {
    $stmt = $pdo->prepare("SELECT * FROM prescription_medicines WHERE prescription_id = ?");
    $stmt->execute([$p['id']]);
    $prescriptionMeds[$p['id']] = $stmt->fetchAll();
}

// 4. المواعيد
$stmt = $pdo->prepare("
    SELECT a.* 
    FROM appointments a
    WHERE a.patient_id = ? AND a.doctor_id = ?
    ORDER BY a.appointment_date DESC
    LIMIT 20
");
$stmt->execute([$patient_id, $user['id']]);
$appointments = $stmt->fetchAll();

// 5. السجل الطبي
$stmt = $pdo->prepare("
    SELECT mr.* 
    FROM medical_records mr
    WHERE mr.patient_id = ? AND mr.doctor_id = ?
    ORDER BY mr.visit_date DESC
    LIMIT 20
");
$stmt->execute([$patient_id, $user['id']]);
$medicalRecords = $stmt->fetchAll();

// 6. إحصائيات سريعة
$totalVisits = count($appointments);
$totalPrescriptions = count($prescriptions);
$lastVisit = $appointments[0]['appointment_date'] ?? null;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملف المريض - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .patient-header {
            display: flex;
            gap: 2rem;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            flex-wrap: wrap;
        }
        
        body.dark-mode .patient-header {
            background: #1E1E1E;
        }
        
        .patient-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .patient-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .patient-info {
            flex: 1;
        }
        
        .patient-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .patient-code {
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .patient-stats-mini {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .stat-mini {
            text-align: center;
        }
        
        .stat-mini-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-mini-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        
        body.dark-mode .quick-stat-card {
            background: #1E1E1E;
        }
        
        .quick-stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .section-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 80px;
            z-index: 90;
        }
        
        body.dark-mode .section-tabs {
            background: #1E1E1E;
        }
        
        .section-tab {
            padding: 0.8rem 1.5rem;
            background: var(--light-gray);
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            color: var(--dark);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        body.dark-mode .section-tab {
            background: #333;
            color: white;
        }
        
        .section-tab:hover {
            background: var(--primary-soft);
        }
        
        .section-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-card-item {
            background: var(--light);
            border-radius: var(--radius-md);
            padding: 1rem;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .medicine-card, .prescription-card, .appointment-card, .record-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border-right: 4px solid transparent;
        }
        
        body.dark-mode .medicine-card,
        body.dark-mode .prescription-card,
        body.dark-mode .appointment-card,
        body.dark-mode .record-card {
            background: #1E1E1E;
        }
        
        .medicine-card:hover,
        .prescription-card:hover,
        .appointment-card:hover,
        .record-card:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .medicine-card.active {
            border-right-color: var(--success);
        }
        
        .medicine-card.completed {
            border-right-color: var(--gray);
            opacity: 0.8;
        }
        
        .appointment-card.pending {
            border-right-color: var(--warning);
        }
        
        .appointment-card.confirmed {
            border-right-color: var(--success);
        }
        
        .appointment-card.completed {
            border-right-color: var(--info);
        }
        
        .appointment-card.cancelled {
            border-right-color: var(--danger);
            opacity: 0.7;
        }
        
        .prescription-card {
            border-right-color: var(--primary);
        }
        
        .record-card {
            border-right-color: var(--secondary);
        }
        
        .floating-add-btn {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-xl);
            cursor: pointer;
            transition: var(--transition);
            z-index: 100;
            border: none;
        }
        
        .floating-add-btn:hover {
            transform: scale(1.1) rotate(90deg);
        }
        
        .add-menu {
            position: fixed;
            bottom: 5rem;
            left: 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 1rem;
            display: none;
            z-index: 101;
            min-width: 200px;
        }
        
        body.dark-mode .add-menu {
            background: #1E1E1E;
        }
        
        .add-menu.show {
            display: block;
        }
        
        .add-menu-item {
            padding: 0.8rem 1rem;
            cursor: pointer;
            transition: var(--transition);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .add-menu-item:hover {
            background: var(--primary-soft);
        }
        
        .blood-badge {
            background: var(--danger);
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            display: inline-block;
            font-weight: 600;
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
            
            <!-- رأس المريض -->
            <div class="patient-header">
                <div class="patient-avatar-large">
                    <?php if (!empty($patient['profile_image'])): ?>
                        <img src="../<?= $patient['profile_image'] ?>" alt="صورة المريض">
                    <?php else: ?>
                        <?= mb_substr($patient['full_name'], 0, 1) ?>
                    <?php endif; ?>
                </div>
                
                <div class="patient-info">
                    <div class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></div>
                    <div class="patient-code">
                        <i class="fas fa-id-card"></i> <?= $patient['user_code'] ?> 
                        | <i class="fas fa-phone"></i> <?= $patient['phone'] ?: 'غير محدد' ?>
                        | <i class="fas fa-envelope"></i> <?= $patient['email'] ?>
                    </div>
                    
                    <div class="patient-stats-mini">
                        <div class="stat-mini">
                            <div class="stat-mini-value"><?= $totalVisits ?></div>
                            <div class="stat-mini-label">زيارة</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-value"><?= $totalPrescriptions ?></div>
                            <div class="stat-mini-label">وصفة</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-value"><?= count($activeMeds) ?></div>
                            <div class="stat-mini-label">دواء نشط</div>
                        </div>
                        <?php if ($lastVisit): ?>
                        <div class="stat-mini">
                            <div class="stat-mini-value"><?= date('d/m', strtotime($lastVisit)) ?></div>
                            <div class="stat-mini-label">آخر زيارة</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openAddPrescriptionModal()">
                        <i class="fas fa-prescription"></i> وصفة
                    </button>
                    <button class="btn btn-outline" onclick="openAddAppointmentModal()">
                        <i class="fas fa-calendar-plus"></i> موعد
                    </button>
                    <button class="btn btn-outline" onclick="window.location.href='consultations.php?patient=<?= $patient_id ?>'">
                        <i class="fas fa-comment"></i> رسالة
                    </button>
                </div>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="quick-stats">
                <div class="quick-stat-card">
                    <div class="quick-stat-number"><?= $patient['blood_type'] ?: '--' ?></div>
                    <div style="color: var(--gray);">فصيلة الدم</div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="quick-stat-number"><?= !empty($patient['emergency_name']) ? 'نعم' : 'لا' ?></div>
                    <div style="color: var(--gray);">جهة اتصال</div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="quick-stat-number"><?= $patient['city'] ?: '--' ?></div>
                    <div style="color: var(--gray);">المدينة</div>
                </div>
            </div>
            
            <!-- تبويبات المعلومات -->
            <div class="section-tabs">
                <button class="section-tab active" onclick="showSection('info')">
                    <i class="fas fa-info-circle"></i> المعلومات
                </button>
                <button class="section-tab" onclick="showSection('active')">
                    <i class="fas fa-pills"></i> الأدوية النشطة
                </button>
                <button class="section-tab" onclick="showSection('archive')">
                    <i class="fas fa-archive"></i> الأرشيف
                </button>
                <button class="section-tab" onclick="showSection('prescriptions')">
                    <i class="fas fa-prescription"></i> الوصفات
                </button>
                <button class="section-tab" onclick="showSection('appointments')">
                    <i class="fas fa-calendar-alt"></i> المواعيد
                </button>
                <button class="section-tab" onclick="showSection('records')">
                    <i class="fas fa-notes-medical"></i> السجل الطبي
                </button>
            </div>
            
            <!-- =================================================
                 المعلومات الشخصية
                 ================================================= -->
            <div id="infoTab" class="tab-content active">
                <div class="info-card">
                    <h3 class="card-title">معلومات شخصية</h3>
                    <div class="info-grid">
                        <div class="info-card-item">
                            <div class="info-label">الاسم الكامل</div>
                            <div class="info-value"><?= htmlspecialchars($patient['full_name']) ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">تاريخ الميلاد</div>
                            <div class="info-value"><?= $patient['birth_date'] ?: 'غير محدد' ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">الجنس</div>
                            <div class="info-value"><?= $patient['gender'] == 'male' ? 'ذكر' : ($patient['gender'] == 'female' ? 'أنثى' : 'غير محدد') ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">رقم البطاقة</div>
                            <div class="info-value"><?= $patient['national_id'] ?: 'غير محدد' ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">العنوان</div>
                            <div class="info-value"><?= $patient['address'] ?: 'غير محدد' ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">المدينة</div>
                            <div class="info-value"><?= $patient['city'] ?: 'غير محدد' ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3 class="card-title">معلومات الاتصال</h3>
                    <div class="info-grid">
                        <div class="info-card-item">
                            <div class="info-label">البريد الإلكتروني</div>
                            <div class="info-value"><?= htmlspecialchars($patient['email']) ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">رقم الهاتف</div>
                            <div class="info-value"><?= $patient['phone'] ?: 'غير محدد' ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3 class="card-title">جهة اتصال الطوارئ</h3>
                    <div class="info-grid">
                        <div class="info-card-item">
                            <div class="info-label">الاسم</div>
                            <div class="info-value"><?= $patient['emergency_name'] ?: 'غير محدد' ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">رقم الهاتف</div>
                            <div class="info-value"><?= $patient['emergency_phone'] ?: 'غير محدد' ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3 class="card-title">معلومات طبية</h3>
                    <div class="info-grid">
                        <div class="info-card-item">
                            <div class="info-label">فصيلة الدم</div>
                            <div class="info-value">
                                <?php if ($patient['blood_type']): ?>
                                    <span class="blood-badge"><?= $patient['blood_type'] ?></span>
                                <?php else: ?>
                                    غير محدد
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">الأمراض المزمنة</div>
                            <div class="info-value"><?= $patient['chronic_diseases'] ?: 'لا يوجد' ?></div>
                        </div>
                        
                        <div class="info-card-item">
                            <div class="info-label">الحساسيات</div>
                            <div class="info-value"><?= $patient['allergies'] ?: 'لا توجد' ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- =================================================
                 الأدوية النشطة
                 ================================================= -->
            <div id="activeTab" class="tab-content">
                <?php if (empty($activeMeds)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-pills" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3 style="margin-top: 1rem;">لا توجد أدوية نشطة</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeMeds as $med): ?>
                        <div class="medicine-card active" onclick="showMedicationDetails(<?= $med['id'] ?>)">
                            <div class="medicine-header">
                                <span class="medicine-name"><?= htmlspecialchars($med['medicine_name']) ?></span>
                                <span class="badge badge-success">نشط</span>
                            </div>
                            
                            <div class="medicine-details">
                                <p><strong>الجرعة:</strong> <?= $med['dosage'] ?></p>
                                <p><strong>التكرار:</strong> <?= $med['frequency'] ?></p>
                                <p><strong>تاريخ البدء:</strong> <?= $med['start_date'] ?: $med['prescription_date'] ?></p>
                                <?php if ($med['end_date']): ?>
                                    <p><strong>تاريخ الانتهاء:</strong> <?= $med['end_date'] ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($med['instructions']): ?>
                                <p style="color: var(--gray);"><?= htmlspecialchars($med['instructions']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 أرشيف الأدوية
                 ================================================= -->
            <div id="archiveTab" class="tab-content">
                <?php if (empty($archiveMeds)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-archive" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3 style="margin-top: 1rem;">لا توجد أدوية سابقة</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($archiveMeds as $med): ?>
                        <div class="medicine-card completed" onclick="showMedicationDetails(<?= $med['id'] ?>)">
                            <div class="medicine-header">
                                <span class="medicine-name"><?= htmlspecialchars($med['medicine_name']) ?></span>
                                <span class="badge badge-secondary">مكتمل</span>
                            </div>
                            
                            <div class="medicine-details">
                                <p><strong>الجرعة:</strong> <?= $med['dosage'] ?></p>
                                <p><strong>الفترة:</strong> <?= $med['start_date'] ?> - <?= $med['end_date'] ?: 'حتى الآن' ?></p>
                            </div>
                            
                            <?php if ($med['diagnosis']): ?>
                                <p><strong>التشخيص:</strong> <?= htmlspecialchars($med['diagnosis']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 الوصفات الطبية
                 ================================================= -->
            <div id="prescriptionsTab" class="tab-content">
                <?php if (empty($prescriptions)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-prescription" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3 style="margin-top: 1rem;">لا توجد وصفات طبية</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($prescriptions as $p): ?>
                        <div class="prescription-card" onclick="showPrescriptionDetails(<?= $p['id'] ?>)">
                            <div style="display: flex; justify-content: space-between;">
                                <h3>وصفة بتاريخ <?= $p['prescription_date'] ?></h3>
                                <span class="badge badge-<?= $p['status'] ?>"><?= getStatusText($p['status']) ?></span>
                            </div>
                            
                            <?php if ($p['diagnosis']): ?>
                                <p><strong>التشخيص:</strong> <?= htmlspecialchars($p['diagnosis']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($prescriptionMeds[$p['id']])): ?>
                                <div style="margin-top: 1rem;">
                                    <strong>الأدوية:</strong>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                                        <?php foreach (array_slice($prescriptionMeds[$p['id']], 0, 3) as $m): ?>
                                            <span class="badge badge-primary"><?= htmlspecialchars($m['medicine_name']) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($prescriptionMeds[$p['id']]) > 3): ?>
                                            <span class="badge">+<?= count($prescriptionMeds[$p['id']]) - 3 ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 المواعيد
                 ================================================= -->
            <div id="appointmentsTab" class="tab-content">
                <?php if (empty($appointments)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-calendar-alt" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3 style="margin-top: 1rem;">لا توجد مواعيد</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <div class="appointment-card <?= $a['status'] ?>" onclick="showAppointmentDetails(<?= $a['id'] ?>)">
                            <div style="display: flex; justify-content: space-between;">
                                <h3><?= date('Y-m-d H:i', strtotime($a['appointment_date'])) ?></h3>
                                <span class="badge badge-<?= $a['status'] ?>"><?= getStatusText($a['status']) ?></span>
                            </div>
                            
                            <?php if ($a['reason']): ?>
                                <p><strong>السبب:</strong> <?= htmlspecialchars($a['reason']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 السجل الطبي
                 ================================================= -->
            <div id="recordsTab" class="tab-content">
                <?php if (empty($medicalRecords)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-notes-medical" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3 style="margin-top: 1rem;">لا توجد سجلات طبية</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($medicalRecords as $r): ?>
                        <div class="record-card" onclick="showRecordDetails(<?= $r['id'] ?>)">
                            <h3>زيارة بتاريخ <?= date('Y-m-d', strtotime($r['visit_date'])) ?></h3>
                            <p><strong>التشخيص:</strong> <?= htmlspecialchars($r['diagnosis']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- زر الإضافة العائم -->
    <button class="floating-add-btn" onclick="toggleAddMenu()">
        <i class="fas fa-plus"></i>
    </button>
    
    <!-- قائمة الإضافة -->
    <div class="add-menu" id="addMenu">
        <div class="add-menu-item" onclick="openAddPrescriptionModal()">
            <i class="fas fa-prescription" style="color: var(--primary);"></i>
            <span>وصفة جديدة</span>
        </div>
        <div class="add-menu-item" onclick="openAddAppointmentModal()">
            <i class="fas fa-calendar-plus" style="color: var(--success);"></i>
            <span>موعد جديد</span>
        </div>
        <div class="add-menu-item" onclick="openMessageModal()">
            <i class="fas fa-comment" style="color: var(--info);"></i>
            <span>إرسال رسالة</span>
        </div>
    </div>
    
    <!-- ============================================================
         النوافذ الجانبية
         ============================================================ -->
    
    <!-- نافذة إضافة وصفة -->
    <div class="side-modal" id="addPrescriptionModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-prescription"></i> وصفة جديدة</h3>
            <button class="close-side-modal" onclick="closeSideModal('addPrescriptionModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post" id="prescriptionForm">
                <div class="form-group">
                    <label>التشخيص</label>
                    <input type="text" name="diagnosis" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                
                <h4 style="margin: 1rem 0;">الأدوية</h4>
                <div id="medicinesContainer">
                    <div class="medicine-row" style="margin-bottom: 1rem; padding: 1rem; background: var(--light); border-radius: 10px;">
                        <div class="form-group">
                            <label>اسم الدواء</label>
                            <input type="text" name="medicine_name[]" class="form-control" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>الجرعة</label>
                                <input type="text" name="dosage[]" class="form-control" placeholder="500mg" required>
                            </div>
                            <div class="form-group">
                                <label>التكرار</label>
                                <input type="text" name="frequency[]" class="form-control" placeholder="كل 8 ساعات" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>المدة</label>
                                <input type="text" name="duration[]" class="form-control" placeholder="7 أيام">
                            </div>
                            <div class="form-group">
                                <label>تعليمات</label>
                                <input type="text" name="instructions[]" class="form-control" placeholderبعد الأكل">
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">حذف</button>
                    </div>
                </div>
                
                <button type="button" class="btn btn-outline btn-sm" onclick="addMedicineRow()" style="margin-bottom: 1rem;">
                    <i class="fas fa-plus"></i> إضافة دواء آخر
                </button>
                
                <input type="hidden" name="medicines_data" id="medicinesData">
                <button type="submit" name="add_prescription" class="btn btn-primary" style="width: 100%;" onclick="prepareMedicinesData()">
                    <i class="fas fa-prescription"></i> إصدار الوصفة
                </button>
            </form>
        </div>
    </div>
    
    <!-- نافذة إضافة موعد -->
    <div class="side-modal" id="addAppointmentModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-calendar-plus"></i> موعد جديد</h3>
            <button class="close-side-modal" onclick="closeSideModal('addAppointmentModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post">
                <div class="form-group">
                    <label>تاريخ الموعد</label>
                    <input type="datetime-local" name="appointment_date" class="form-control" required 
                           min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
                </div>
                
                <div class="form-group">
                    <label>سبب الزيارة</label>
                    <textarea name="reason" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                
                <button type="submit" name="add_appointment" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-calendar-check"></i> حجز الموعد
                </button>
            </form>
        </div>
    </div>
    
    <!-- نافذة تفاصيل الدواء -->
    <div class="side-modal" id="medicationDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-pills"></i> تفاصيل الدواء</h3>
            <button class="close-side-modal" onclick="closeSideModal('medicationDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="medicationDetailsContent"></div>
    </div>
    
    <!-- نافذة تفاصيل الوصفة -->
    <div class="side-modal" id="prescriptionDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-prescription"></i> تفاصيل الوصفة</h3>
            <button class="close-side-modal" onclick="closeSideModal('prescriptionDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="prescriptionDetailsContent"></div>
    </div>
    
    <!-- نافذة تفاصيل الموعد -->
    <div class="side-modal" id="appointmentDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-calendar-alt"></i> تفاصيل الموعد</h3>
            <button class="close-side-modal" onclick="closeSideModal('appointmentDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="appointmentDetailsContent"></div>
    </div>
    
    <!-- نافذة تفاصيل السجل الطبي -->
    <div class="side-modal" id="recordDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-notes-medical"></i> تفاصيل السجل الطبي</h3>
            <button class="close-side-modal" onclick="closeSideModal('recordDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="recordDetailsContent"></div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    let medicineCount = 1;
    
    // ============================================================
    // تبديل التبويبات
    // ============================================================
    function showSection(section) {
        document.querySelectorAll('.section-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        const tabMap = {
            'info': 'infoTab',
            'active': 'activeTab',
            'archive': 'archiveTab',
            'prescriptions': 'prescriptionsTab',
            'appointments': 'appointmentsTab',
            'records': 'recordsTab'
        };
        
        event.target.classList.add('active');
        document.getElementById(tabMap[section]).classList.add('active');
    }
    
    // ============================================================
    // قائمة الإضافة
    // ============================================================
    function toggleAddMenu() {
        document.getElementById('addMenu').classList.toggle('show');
    }
    
    // ============================================================
    // إضافة دواء في الوصفة
    // ============================================================
    function addMedicineRow() {
        const container = document.getElementById('medicinesContainer');
        const newRow = document.createElement('div');
        newRow.className = 'medicine-row';
        newRow.style.marginBottom = '1rem';
        newRow.style.padding = '1rem';
        newRow.style.background = 'var(--light)';
        newRow.style.borderRadius = '10px';
        newRow.innerHTML = `
            <div class="form-group">
                <label>اسم الدواء</label>
                <input type="text" name="medicine_name[]" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>الجرعة</label>
                    <input type="text" name="dosage[]" class="form-control" placeholder="500mg" required>
                </div>
                <div class="form-group">
                    <label>التكرار</label>
                    <input type="text" name="frequency[]" class="form-control" placeholder="كل 8 ساعات" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>المدة</label>
                    <input type="text" name="duration[]" class="form-control" placeholder="7 أيام">
                </div>
                <div class="form-group">
                    <label>تعليمات</label>
                    <input type="text" name="instructions[]" class="form-control" placeholderبعد الأكل">
                </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">حذف</button>
        `;
        container.appendChild(newRow);
    }
    
    // ============================================================
    // تحضير بيانات الأدوية
    // ============================================================
    function prepareMedicinesData() {
        const medicineNames = document.getElementsByName('medicine_name[]');
        const dosages = document.getElementsByName('dosage[]');
        const frequencies = document.getElementsByName('frequency[]');
        const durations = document.getElementsByName('duration[]');
        const instructions = document.getElementsByName('instructions[]');
        
        const medicines = [];
        
        for (let i = 0; i < medicineNames.length; i++) {
            medicines.push({
                name: medicineNames[i].value,
                dosage: dosages[i].value,
                frequency: frequencies[i].value,
                duration: durations[i]?.value || '',
                instructions: instructions[i]?.value || ''
            });
        }
        
        document.getElementById('medicinesData').value = JSON.stringify(medicines);
    }
    
    // ============================================================
    // فتح النوافذ
    // ============================================================
    function openAddPrescriptionModal() {
        closeSideModal('addMenu');
        openSideModal('addPrescriptionModal');
    }
    
    function openAddAppointmentModal() {
        closeSideModal('addMenu');
        openSideModal('addAppointmentModal');
    }
    
    function openMessageModal() {
        closeSideModal('addMenu');
        window.location.href = `consultations.php?patient=<?= $patient_id ?>`;
    }
    
    // ============================================================
    // عرض التفاصيل
    // ============================================================
    function showMedicationDetails(id) {
        fetch(`../api/doctor/get_medication.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const m = data.data;
                    document.getElementById('medicationDetailsContent').innerHTML = `
                        <h3>${m.medicine_name}</h3>
                        <hr>
                        <p><strong>الجرعة:</strong> ${m.dosage}</p>
                        <p><strong>التكرار:</strong> ${m.frequency}</p>
                        <p><strong>تاريخ البدء:</strong> ${m.start_date}</p>
                        <p><strong>تاريخ الانتهاء:</strong> ${m.end_date || 'غير محدد'}</p>
                        <p><strong>تعليمات:</strong> ${m.instructions || 'لا توجد'}</p>
                    `;
                    openSideModal('medicationDetailsModal');
                }
            });
    }
    
    function showPrescriptionDetails(id) {
        fetch(`../api/doctor/get_prescription_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const p = data.data;
                    let html = `
                        <h3>وصفة بتاريخ ${p.prescription_date}</h3>
                        <p><strong>التشخيص:</strong> ${p.diagnosis}</p>
                        <p><strong>ملاحظات:</strong> ${p.notes || 'لا توجد'}</p>
                        <hr>
                        <h4>الأدوية</h4>
                    `;
                    
                    if (p.medicines && p.medicines.length > 0) {
                        p.medicines.forEach(m => {
                            html += `
                                <div style="margin: 1rem 0; padding: 1rem; background: var(--light); border-radius: 10px;">
                                    <strong>${m.medicine_name}</strong>
                                    <p>${m.dosage} - ${m.frequency}</p>
                                </div>
                            `;
                        });
                    }
                    
                    document.getElementById('prescriptionDetailsContent').innerHTML = html;
                    openSideModal('prescriptionDetailsModal');
                }
            });
    }
    
    function showAppointmentDetails(id) {
        fetch(`../api/doctor/get_appointment_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const a = data.data;
                    document.getElementById('appointmentDetailsContent').innerHTML = `
                        <h3>موعد بتاريخ ${a.appointment_date}</h3>
                        <p><strong>الحالة:</strong> <span class="badge badge-${a.status}">${getStatusText(a.status)}</span></p>
                        ${a.reason ? `<p><strong>السبب:</strong> ${a.reason}</p>` : ''}
                        ${a.notes ? `<p><strong>ملاحظات:</strong> ${a.notes}</p>` : ''}
                    `;
                    openSideModal('appointmentDetailsModal');
                }
            });
    }
    
    function showRecordDetails(id) {
        fetch(`../api/doctor/get_medical_record.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const r = data.data;
                    document.getElementById('recordDetailsContent').innerHTML = `
                        <h3>سجل طبي</h3>
                        <p><strong>تاريخ الزيارة:</strong> ${r.visit_date}</p>
                        <p><strong>التشخيص:</strong> ${r.diagnosis}</p>
                        ${r.notes ? `<p><strong>ملاحظات:</strong> ${r.notes}</p>` : ''}
                    `;
                    openSideModal('recordDetailsModal');
                }
            });
    }
    
    function getStatusText(status) {
        const statuses = {
            'pending': 'قيد الانتظار',
            'confirmed': 'مؤكد',
            'completed': 'مكتمل',
            'cancelled': 'ملغي'
        };
        return statuses[status] || status;
    }
    </script>
</body>
</html>