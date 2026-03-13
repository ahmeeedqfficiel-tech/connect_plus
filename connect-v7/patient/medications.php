<?php
/**
 * patient/medications.php - صفحة المتابعات مع إضافة دواء
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم مريض
requirePatient($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// ============================================================
// معالجة إضافة دواء جديد
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medication'])) {
    $medicine_name = cleanInput($_POST['medicine_name']);
    $dosage = cleanInput($_POST['dosage']);
    $frequency = cleanInput($_POST['frequency']);
    $duration = cleanInput($_POST['duration']);
    $start_date = $_POST['start_date'];
    $instructions = cleanInput($_POST['instructions']);
    
    try {
        $pdo->beginTransaction();
        
        // حساب تاريخ الانتهاء
        $duration_days = (int) filter_var($duration, FILTER_SANITIZE_NUMBER_INT);
        $end_date = null;
        if ($duration_days > 0) {
            $end_date = date('Y-m-d', strtotime("+$duration_days days", strtotime($start_date)));
        }
        
        // إنشاء وصفة مؤقتة (بدون طبيب)
        $stmt = $pdo->prepare("
            INSERT INTO prescriptions (
                patient_id, doctor_id, prescription_date, diagnosis, notes, status
            ) VALUES (?, NULL, CURDATE(), ?, ?, 'active')
        ");
        $stmt->execute([$user['id'], 'إضافة يدوية', 'تم إضافة الدواء يدوياً بواسطة المريض']);
        $prescription_id = $pdo->lastInsertId();
        
        // إضافة الدواء
        $stmt = $pdo->prepare("
            INSERT INTO prescription_medicines (
                prescription_id, medicine_name, dosage, frequency, duration, 
                start_date, end_date, instructions, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $prescription_id, $medicine_name, $dosage, $frequency, $duration,
            $start_date, $end_date, $instructions
        ]);
        
        $pdo->commit();
        
        // رسالة نجاح وإعادة توجيه
        setToast('تم إضافة الدواء بنجاح', 'success');
        header('Location: medications.php?tab=active');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('حدث خطأ: ' . $e->getMessage(), 'error');
        header('Location: medications.php?tab=active');
        exit;
    }
}

// ============================================================
// تحديث حالة الأدوية المنتهية (ينتقل للأرشيف تلقائياً)
// ============================================================
$stmt = $pdo->prepare("
    SELECT pm.*, p.prescription_date, p.prescription_image, 
           u.full_name as doctor_name, p.diagnosis, p.notes as prescription_notes
    FROM prescription_medicines pm 
    JOIN prescriptions p ON pm.prescription_id = p.id 
    LEFT JOIN users u ON p.doctor_id = u.id 
    WHERE p.patient_id = ?
    AND (pm.end_date IS NULL OR pm.end_date >= CURDATE())
    ORDER BY pm.end_date ASC, pm.start_date DESC
");
$stmt->execute([$user['id']]);
$activeMeds = $stmt->fetchAll();

// ============================================================
// جلب البيانات
// ============================================================

// 1. الأدوية النشطة
$stmt = $pdo->prepare("
    SELECT pm.*, p.prescription_date, p.prescription_image, 
           u.full_name as doctor_name, p.diagnosis, p.notes as prescription_notes
    FROM prescription_medicines pm 
    JOIN prescriptions p ON pm.prescription_id = p.id 
    LEFT JOIN users u ON p.doctor_id = u.id 
    WHERE p.patient_id = ?
    AND pm.end_date IS NOT NULL
    AND pm.end_date < CURDATE()
    ORDER BY pm.end_date DESC
    LIMIT 50
");
$stmt->execute([$user['id']]);
$archiveMeds = $stmt->fetchAll();

// 2. أرشيف الأدوية (منتهية)
$stmt = $pdo->prepare("
    SELECT pm.*, p.prescription_date, p.prescription_image, 
           u.full_name as doctor_name, p.diagnosis, p.notes as prescription_notes
    FROM prescription_medicines pm 
    JOIN prescriptions p ON pm.prescription_id = p.id 
    LEFT JOIN users u ON p.doctor_id = u.id 
    WHERE p.patient_id = ? AND pm.status != 'active' 
    ORDER BY pm.end_date DESC, p.prescription_date DESC 
    LIMIT 50
");
$stmt->execute([$user['id']]);
$archiveMeds = $stmt->fetchAll();

// 3. الوصفات الطبية
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as doctor_name, u.profile_image as doctor_image,
           d.specialties, d.degree
    FROM prescriptions p 
    LEFT JOIN users u ON p.doctor_id = u.id 
    LEFT JOIN doctors d ON p.doctor_id = d.user_id
    WHERE p.patient_id = ? 
    ORDER BY p.prescription_date DESC 
    LIMIT 50
");
$stmt->execute([$user['id']]);
$prescriptions = $stmt->fetchAll();

// جلب أدوية كل وصفة
$prescriptionMedicines = [];
foreach ($prescriptions as $p) {
    $stmt = $pdo->prepare("
        SELECT * FROM prescription_medicines 
        WHERE prescription_id = ? 
        ORDER BY id
    ");
    $stmt->execute([$p['id']]);
    $prescriptionMedicines[$p['id']] = $stmt->fetchAll();
}

// 4. المواعيد
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as doctor_name, d.specialties, u.profile_image as doctor_image
    FROM appointments a 
    JOIN users u ON a.doctor_id = u.id 
    LEFT JOIN doctors d ON a.doctor_id = d.user_id 
    WHERE a.patient_id = ? 
    ORDER BY a.appointment_date DESC 
    LIMIT 50
");
$stmt->execute([$user['id']]);
$appointments = $stmt->fetchAll();

// 5. السجل الطبي
$stmt = $pdo->prepare("
    SELECT mr.*, u.full_name as doctor_name, u.profile_image as doctor_image,
           d.specialties
    FROM medical_records mr 
    JOIN users u ON mr.doctor_id = u.id 
    LEFT JOIN doctors d ON mr.doctor_id = d.user_id
    WHERE mr.patient_id = ? 
    ORDER BY mr.visit_date DESC 
    LIMIT 50
");
$stmt->execute([$user['id']]);
$medicalRecords = $stmt->fetchAll();

// تحديد التبويب النشط
$activeTab = $_GET['tab'] ?? 'active';

// تحويل البيانات إلى JSON
$activeMedsJson = json_encode($activeMeds);
$archiveMedsJson = json_encode($archiveMeds);
$prescriptionsJson = json_encode($prescriptions);
$prescriptionMedicinesJson = json_encode($prescriptionMedicines);
$appointmentsJson = json_encode($appointments);
$recordsJson = json_encode($medicalRecords);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المتابعات - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .medications-tabs {
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
        
        body.dark-mode .medications-tabs {
            background: #1E1E1E;
        }
        
        .med-tab {
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
        
        body.dark-mode .med-tab {
            background: #333;
            color: white;
        }
        
        .med-tab:hover {
            background: var(--primary-soft);
        }
        
        .med-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .medicine-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border-right: 4px solid var(--primary);
            cursor: pointer;
            transition: var(--transition);
        }
        
        body.dark-mode .medicine-card {
            background: #1E1E1E;
        }
        
        .medicine-card:hover {
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
        
        .medicine-card.expiring-soon {
            border-right-color: var(--warning);
        }
        
        .medicine-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .medicine-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .expiring-badge {
            background: var(--warning);
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.7rem;
        }
        
        .prescription-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border-right: 4px solid var(--primary);
        }
        
        body.dark-mode .prescription-card {
            background: #1E1E1E;
        }
        
        .prescription-card:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .doctor-info-mini {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .doctor-avatar-mini {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            overflow: hidden;
        }
        
        .doctor-avatar-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .appointment-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border-right: 4px solid transparent;
        }
        
        body.dark-mode .appointment-card {
            background: #1E1E1E;
        }
        
        .appointment-card:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
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
        
        .record-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border-right: 4px solid var(--secondary);
        }
        
        body.dark-mode .record-card {
            background: #1E1E1E;
        }
        
        .record-card:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
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
        
        /* تنسيق النوافذ الجانبية */
        .side-modal {
            position: fixed;
            top: 0;
            left: -450px;
            width: 450px;
            height: 100vh;
            background: white;
            box-shadow: var(--shadow-xl);
            z-index: 2000;
            transition: left 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
        body.dark-mode .side-modal {
            background: #1E1E1E;
        }
        
        .side-modal.active {
            left: 0;
        }
        
        .side-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 2px solid var(--light-gray);
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        
        body.dark-mode .side-modal-header {
            background: #1E1E1E;
        }
        
        .side-modal-header h3 {
            color: var(--primary);
        }
        
        .close-side-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .side-modal-body {
            padding: 1.5rem;
            flex: 1;
            overflow-y: auto;
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
            
            <div class="card-header">
                <h1 class="page-title"><i class="fas fa-chart-line"></i> المتابعات</h1>
                <button class="btn btn-primary" onclick="openSideModal('addMedicationModal')">
                    <i class="fas fa-plus"></i> إضافة دواء
                </button>
            </div>
            
            <!-- تبويبات المتابعات -->
            <div class="medications-tabs">
                <a href="?tab=active" class="med-tab <?= $activeTab == 'active' ? 'active' : '' ?>">
                    <i class="fas fa-pills"></i> الأدوية النشطة (<?= count($activeMeds) ?>)
                </a>
                <a href="?tab=archive" class="med-tab <?= $activeTab == 'archive' ? 'active' : '' ?>">
                    <i class="fas fa-archive"></i> الأرشيف (<?= count($archiveMeds) ?>)
                </a>
                <a href="?tab=prescriptions" class="med-tab <?= $activeTab == 'prescriptions' ? 'active' : '' ?>">
                    <i class="fas fa-prescription"></i> الوصفات (<?= count($prescriptions) ?>)
                </a>
                <a href="?tab=appointments" class="med-tab <?= $activeTab == 'appointments' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> المواعيد (<?= count($appointments) ?>)
                </a>
                <a href="?tab=records" class="med-tab <?= $activeTab == 'records' ? 'active' : '' ?>">
                    <i class="fas fa-notes-medical"></i> السجل الطبي (<?= count($medicalRecords) ?>)
                </a>
            </div>
            
            <!-- =================================================
                 الأدوية النشطة
                 ================================================= -->
            <div id="activeTab" class="tab-content <?= $activeTab == 'active' ? 'active' : '' ?>">
                <?php if (empty($activeMeds)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-pills" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3>لا توجد أدوية نشطة</h3>
                        <button class="btn btn-primary" onclick="openSideModal('addMedicationModal')">
                            <i class="fas fa-plus"></i> إضافة دواء
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeMeds as $med): 
                        $isExpiringSoon = false;
                        if ($med['end_date']) {
                            $daysLeft = (strtotime($med['end_date']) - strtotime($today)) / 86400;
                            $isExpiringSoon = $daysLeft <= 3 && $daysLeft >= 0;
                        }
                    ?>
                        <div class="medicine-card <?= $isExpiringSoon ? 'expiring-soon' : 'active' ?>" 
                             onclick="viewMedicationDetails(<?= $med['id'] ?>)">
                            <div class="medicine-header">
                                <span class="medicine-name"><?= htmlspecialchars($med['medicine_name']) ?></span>
                                <span class="badge badge-success">نشط</span>
                            </div>
                            
                            <p><strong>الجرعة:</strong> <?= $med['dosage'] ?> | <strong>التكرار:</strong> <?= $med['frequency'] ?></p>
                            
                            <?php if ($med['end_date']): ?>
                                <p>
                                    <strong>ينتهي في:</strong> <?= $med['end_date'] ?>
                                    <?php if ($isExpiringSoon): ?>
                                        <span class="expiring-badge">ينتهي قريباً</span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($med['doctor_name']): ?>
                                <p style="color: var(--gray); font-size: 0.9rem;">
                                    <i class="fas fa-user-md"></i> د. <?= htmlspecialchars($med['doctor_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 أرشيف الأدوية
                 ================================================= -->
            <div id="archiveTab" class="tab-content <?= $activeTab == 'archive' ? 'active' : '' ?>">
                <?php if (empty($archiveMeds)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-archive" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3>لا توجد أدوية سابقة</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($archiveMeds as $med): ?>
                        <div class="medicine-card completed" onclick="viewMedicationDetails(<?= $med['id'] ?>)">
                            <div class="medicine-header">
                                <span class="medicine-name"><?= htmlspecialchars($med['medicine_name']) ?></span>
                                <span class="badge badge-secondary">مكتمل</span>
                            </div>
                            
                            <p><strong>الجرعة:</strong> <?= $med['dosage'] ?></p>
                            <p><strong>الفترة:</strong> <?= $med['start_date'] ?> - <?= $med['end_date'] ?: 'حتى الآن' ?></p>
                            
                            <?php if ($med['doctor_name']): ?>
                                <p style="color: var(--gray);">د. <?= htmlspecialchars($med['doctor_name']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 الوصفات الطبية
                 ================================================= -->
            <div id="prescriptionsTab" class="tab-content <?= $activeTab == 'prescriptions' ? 'active' : '' ?>">
                <?php if (empty($prescriptions)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-prescription" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3>لا توجد وصفات طبية</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($prescriptions as $p): ?>
                        <div class="prescription-card" onclick="viewPrescriptionDetails(<?= $p['id'] ?>)">
                            <div class="prescription-header">
                                <div class="doctor-info-mini">
                                    <div class="doctor-avatar-mini">
                                        <?php if (!empty($p['doctor_image'])): ?>
                                            <img src="../<?= $p['doctor_image'] ?>" alt="د">
                                        <?php else: ?>
                                            <i class="fas fa-user-md"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong>د. <?= htmlspecialchars($p['doctor_name'] ?? 'غير محدد') ?></strong>
                                        <?php if (!empty($p['specialties'])): ?>
                                            <br><small style="color: var(--gray);"><?= htmlspecialchars($p['specialties']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge badge-<?= $p['status'] ?>"><?= getStatusText($p['status']) ?></span>
                            </div>
                            
                            <p><strong>التاريخ:</strong> <?= $p['prescription_date'] ?></p>
                            
                            <?php if ($p['diagnosis'] && $p['diagnosis'] != 'إضافة يدوية'): ?>
                                <p><strong>التشخيص:</strong> <?= htmlspecialchars($p['diagnosis']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($prescriptionMedicines[$p['id']])): ?>
                                <p><strong>الأدوية:</strong> 
                                    <?php 
                                    $medNames = array_column($prescriptionMedicines[$p['id']], 'medicine_name');
                                    echo implode(' - ', array_slice($medNames, 0, 2));
                                    if (count($medNames) > 2) echo ' +' . (count($medNames) - 2);
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 المواعيد
                 ================================================= -->
            <div id="appointmentsTab" class="tab-content <?= $activeTab == 'appointments' ? 'active' : '' ?>">
                <?php if (empty($appointments)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-calendar-alt" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3>لا توجد مواعيد</h3>
                        <a href="find_doctor.php" class="btn btn-primary">البحث عن طبيب</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <div class="appointment-card <?= $a['status'] ?>" onclick="viewAppointmentDetails(<?= $a['id'] ?>)">
                            <div class="doctor-info-mini" style="margin-bottom: 0.5rem;">
                                <div class="doctor-avatar-mini">
                                    <?php if (!empty($a['doctor_image'])): ?>
                                        <img src="../<?= $a['doctor_image'] ?>" alt="د">
                                    <?php else: ?>
                                        <i class="fas fa-user-md"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong>د. <?= htmlspecialchars($a['doctor_name']) ?></strong>
                                    <?php if (!empty($a['specialties'])): ?>
                                        <br><small style="color: var(--gray);"><?= htmlspecialchars($a['specialties']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p><i class="fas fa-calendar"></i> <?= date('Y-m-d H:i', strtotime($a['appointment_date'])) ?></p>
                            
                            <?php if ($a['reason']): ?>
                                <p style="color: var(--gray);"><?= htmlspecialchars($a['reason']) ?></p>
                            <?php endif; ?>
                            
                            <span class="badge badge-<?= $a['status'] ?>"><?= getStatusText($a['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- =================================================
                 السجل الطبي
                 ================================================= -->
            <div id="recordsTab" class="tab-content <?= $activeTab == 'records' ? 'active' : '' ?>">
                <?php if (empty($medicalRecords)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-notes-medical" style="font-size: 3rem; color: var(--gray);"></i>
                        <h3>لا توجد سجلات طبية</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($medicalRecords as $r): ?>
                        <div class="record-card" onclick="viewRecordDetails(<?= $r['id'] ?>)">
                            <div class="doctor-info-mini" style="margin-bottom: 0.5rem;">
                                <div class="doctor-avatar-mini">
                                    <?php if (!empty($r['doctor_image'])): ?>
                                        <img src="../<?= $r['doctor_image'] ?>" alt="د">
                                    <?php else: ?>
                                        <i class="fas fa-user-md"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong>د. <?= htmlspecialchars($r['doctor_name']) ?></strong>
                                    <?php if (!empty($r['specialties'])): ?>
                                        <br><small style="color: var(--gray);"><?= htmlspecialchars($r['specialties']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p><i class="fas fa-calendar"></i> <?= date('Y-m-d', strtotime($r['visit_date'])) ?></p>
                            <p><strong>التشخيص:</strong> <?= htmlspecialchars($r['diagnosis']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- زر الإضافة العائم -->
    <button class="floating-add-btn" onclick="openSideModal('addMedicationModal')">
        <i class="fas fa-plus"></i>
    </button>
    
    <!-- ============================================================
         النوافذ الجانبية
         ============================================================ -->
    
    <!-- نافذة إضافة دواء -->
    <div class="side-modal" id="addMedicationModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-plus-circle"></i> إضافة دواء جديد</h3>
            <button class="close-side-modal" onclick="closeSideModal('addMedicationModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post">
                <div class="form-group">
                    <label>اسم الدواء</label>
                    <input type="text" name="medicine_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>الجرعة</label>
                        <input type="text" name="dosage" class="form-control" placeholder="500mg" required>
                    </div>
                    <div class="form-group">
                        <label>التكرار</label>
                        <input type="text" name="frequency" class="form-control" placeholder="كل 8 ساعات" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>المدة (أيام)</label>
                        <input type="text" name="duration" class="form-control" placeholder="7 أيام">
                    </div>
                    <div class="form-group">
                        <label>تاريخ البدء</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>تعليمات</label>
                    <textarea name="instructions" class="form-control" rows="2" placeholder="مثال: بعد الأكل..."></textarea>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    عند انتهاء مدة الدواء، سينتقل تلقائياً إلى الأرشيف.
                </div>
                
                <button type="submit" name="add_medication" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> حفظ الدواء
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
    
    <script>
    // بيانات JSON
    const activeMeds = <?= $activeMedsJson ?>;
    const archiveMeds = <?= $archiveMedsJson ?>;
    const prescriptions = <?= $prescriptionsJson ?>;
    const prescriptionMedicines = <?= $prescriptionMedicinesJson ?>;
    const appointments = <?= $appointmentsJson ?>;
    const records = <?= $recordsJson ?>;
    
    // ============================================================
    // دوال فتح وإغلاق النوافذ
    // ============================================================
    function openSideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeSideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }
    
    // ============================================================
    // عرض تفاصيل الدواء
    // ============================================================
    function viewMedicationDetails(id) {
        let med = activeMeds.find(m => m.id == id) || archiveMeds.find(m => m.id == id);
        
        if (med) {
            let statusText = med.status === 'active' ? 'نشط' : 'مكتمل';
            let statusClass = med.status === 'active' ? 'badge-success' : 'badge-secondary';
            
            document.getElementById('medicationDetailsContent').innerHTML = `
                <h2 style="color: var(--primary); margin-bottom: 1rem;">${med.medicine_name}</h2>
                
                ${med.doctor_name ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <p style="margin: 0;"><i class="fas fa-user-md" style="color: var(--primary);"></i> <strong>الطبيب:</strong> د. ${med.doctor_name}</p>
                    </div>
                ` : ''}
                
                <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <h4 style="color: var(--primary); margin-bottom: 0.8rem;">معلومات الجرعة</h4>
                    <p><strong>الجرعة:</strong> ${med.dosage}</p>
                    <p><strong>التكرار:</strong> ${med.frequency}</p>
                    ${med.duration ? `<p><strong>المدة:</strong> ${med.duration}</p>` : ''}
                </div>
                
                <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <h4 style="color: var(--primary); margin-bottom: 0.8rem;">تواريخ العلاج</h4>
                    <p><strong>تاريخ البدء:</strong> ${med.start_date || med.prescription_date}</p>
                    ${med.end_date ? `<p><strong>تاريخ الانتهاء:</strong> ${med.end_date}</p>` : ''}
                    <p><strong>الحالة:</strong> <span class="badge ${statusClass}">${statusText}</span></p>
                </div>
                
                ${med.diagnosis && med.diagnosis != 'إضافة يدوية' ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary); margin-bottom: 0.8rem;">التشخيص</h4>
                        <p>${med.diagnosis}</p>
                    </div>
                ` : ''}
                
                ${med.instructions ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary); margin-bottom: 0.8rem;">تعليمات</h4>
                        <p>${med.instructions}</p>
                    </div>
                ` : ''}
                
                ${med.prescription_notes ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px;">
                        <h4 style="color: var(--primary); margin-bottom: 0.8rem;">ملاحظات إضافية</h4>
                        <p>${med.prescription_notes}</p>
                    </div>
                ` : ''}
            `;
            
            openSideModal('medicationDetailsModal');
        }
    }
    
    // ============================================================
    // عرض تفاصيل الوصفة
    // ============================================================
    function viewPrescriptionDetails(id) {
        const p = prescriptions.find(pr => pr.id == id);
        
        if (p) {
            const meds = prescriptionMedicines[id] || [];
            
            let medicinesHtml = '';
            if (meds.length > 0) {
                medicinesHtml = '<h4 style="color: var(--primary); margin: 1rem 0 0.5rem;">الأدوية</h4>';
                meds.forEach(m => {
                    medicinesHtml += `
                        <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 0.8rem;">
                            <p style="font-weight: 700; color: var(--primary); margin-bottom: 0.3rem;">${m.medicine_name}</p>
                            <p style="margin: 0.2rem 0;">الجرعة: ${m.dosage} | التكرار: ${m.frequency}</p>
                            ${m.instructions ? `<p style="margin: 0.2rem 0; color: var(--gray);">${m.instructions}</p>` : ''}
                        </div>
                    `;
                });
            }
            
            document.getElementById('prescriptionDetailsContent').innerHTML = `
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; overflow: hidden;">
                        ${p.doctor_image ? `<img src="../${p.doctor_image}" style="width:100%; height:100%; object-fit:cover;">` : '<i class="fas fa-user-md"></i>'}
                    </div>
                    <div>
                        <h3 style="color: var(--primary); margin-bottom: 0.2rem;">د. ${p.doctor_name || 'غير محدد'}</h3>
                        <p style="color: var(--gray); margin: 0;">${p.specialties || 'طبيب'}</p>
                        ${p.degree ? `<small style="color: var(--gray);">${p.degree}</small>` : ''}
                    </div>
                </div>
                
                <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <h4 style="color: var(--primary); margin-bottom: 0.8rem;">معلومات الوصفة</h4>
                    <p><strong>التاريخ:</strong> ${p.prescription_date}</p>
                    ${p.diagnosis && p.diagnosis != 'إضافة يدوية' ? `<p><strong>التشخيص:</strong> ${p.diagnosis}</p>` : ''}
                    <p><strong>الحالة:</strong> <span class="badge badge-${p.status}">${getStatusText(p.status)}</span></p>
                </div>
                
                ${medicinesHtml}
                
                ${p.notes ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px;">
                        <h4 style="color: var(--primary); margin-bottom: 0.8rem;">ملاحظات</h4>
                        <p>${p.notes}</p>
                    </div>
                ` : ''}
            `;
            
            openSideModal('prescriptionDetailsModal');
        }
    }
    
    // ============================================================
    // عرض تفاصيل الموعد
    // ============================================================
    function viewAppointmentDetails(id) {
        const a = appointments.find(apt => apt.id == id);
        
        if (a) {
            let statusText = {
                'pending': 'قيد الانتظار',
                'confirmed': 'مؤكد',
                'completed': 'مكتمل',
                'cancelled': 'ملغي'
            }[a.status] || a.status;
            
            let statusClass = {
                'pending': 'badge-warning',
                'confirmed': 'badge-success',
                'completed': 'badge-info',
                'cancelled': 'badge-danger'
            }[a.status] || 'badge-secondary';
            
            document.getElementById('appointmentDetailsContent').innerHTML = `
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; overflow: hidden;">
                        ${a.doctor_image ? `<img src="../${a.doctor_image}" style="width:100%; height:100%; object-fit:cover;">` : '<i class="fas fa-user-md"></i>'}
                    </div>
                    <div>
                        <h3 style="color: var(--primary); margin-bottom: 0.2rem;">د. ${a.doctor_name}</h3>
                        <p style="color: var(--gray); margin: 0;">${a.specialties || ''}</p>
                    </div>
                </div>
                
                <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <h4 style="color: var(--primary); margin-bottom: 0.8rem;">تفاصيل الموعد</h4>
                    <p><strong>التاريخ:</strong> ${a.appointment_date}</p>
                    <p><strong>الحالة:</strong> <span class="badge ${statusClass}">${statusText}</span></p>
                </div>
                
                ${a.reason ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary); margin-bottom: 0.8rem;">سبب الزيارة</h4>
                        <p>${a.reason}</p>
                    </div>
                ` : ''}
                
                ${a.notes ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px;">
                        <h4 style="color: var(--primary); margin-bottom: 0.8rem;">ملاحظات</h4>
                        <p>${a.notes}</p>
                    </div>
                ` : ''}
            `;
            
            openSideModal('appointmentDetailsModal');
        }
    }
    
    // ============================================================
    // عرض تفاصيل السجل الطبي
    // ============================================================
    function viewRecordDetails(id) {
        const r = records.find(rec => rec.id == id);
        
        if (r) {
            document.getElementById('recordDetailsContent').innerHTML = `
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; overflow: hidden;">
                        ${r.doctor_image ? `<img src="../${r.doctor_image}" style="width:100%; height:100%; object-fit:cover;">` : '<i class="fas fa-user-md"></i>'}
                    </div>
                    <div>
                        <h3 style="color: var(--primary); margin-bottom: 0.2rem;">د. ${r.doctor_name}</h3>
                        <p style="color: var(--gray); margin: 0;">${r.specialties || 'طبيب'}</p>
                    </div>
                </div>
                
                <div style="background: var(--light); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <h4 style="color: var(--primary); margin-bottom: 0.8rem;">معلومات الزيارة</h4>
                    <p><strong>تاريخ الزيارة:</strong> ${r.visit_date}</p>
                    <p><strong>التشخيص:</strong> ${r.diagnosis}</p>
                </div>
                
                ${r.notes ? `
                    <div style="background: var(--light); padding: 1rem; border-radius: 10px;">
                        <h4 style="color: var(--primary); margin-bottom: 0.8rem;">ملاحظات</h4>
                        <p>${r.notes}</p>
                    </div>
                ` : ''}
            `;
            
            openSideModal('recordDetailsModal');
        }
    }
    
    // ============================================================
    // دالة مساعدة لترجمة الحالة
    // ============================================================
    function getStatusText(status) {
        const statuses = {
            'pending': 'قيد الانتظار',
            'confirmed': 'مؤكد',
            'completed': 'مكتمل',
            'cancelled': 'ملغي',
            'active': 'نشط',
            'inactive': 'غير نشط'
        };
        return statuses[status] || status;
    }
    </script>
</body>
</html>