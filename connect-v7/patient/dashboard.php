<?php
/**
 * patient/dashboard.php - الصفحة الرئيسية للمريض مع نوافذ جانبية
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
// إحصائيات سريعة
// ============================================================

// عدد الأدوية النشطة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM prescription_medicines pm 
                       JOIN prescriptions p ON pm.prescription_id = p.id 
                       WHERE p.patient_id = ? AND pm.status = 'active'");
$stmt->execute([$user['id']]);
$activeMeds = $stmt->fetchColumn();

// عدد المواعيد القادمة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                       WHERE patient_id = ? AND appointment_date > NOW() AND status != 'cancelled'");
$stmt->execute([$user['id']]);
$upcomingApps = $stmt->fetchColumn();

// عدد الرسائل غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$unreadMessages = $stmt->fetchColumn();

// آخر موعد
$stmt = $pdo->prepare("SELECT appointment_date FROM appointments 
                       WHERE patient_id = ? ORDER BY appointment_date DESC LIMIT 1");
$stmt->execute([$user['id']]);
$lastAppointment = $stmt->fetchColumn();

// ============================================================
// آخر المواعيد
// ============================================================
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as doctor_name, d.specialties, u.profile_image as doctor_image
    FROM appointments a 
    JOIN users u ON a.doctor_id = u.id 
    LEFT JOIN doctors d ON a.doctor_id = d.user_id 
    WHERE a.patient_id = ? 
    ORDER BY a.appointment_date DESC 
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentAppointments = $stmt->fetchAll();

// ============================================================
// آخر الأدوية
// ============================================================
$stmt = $pdo->prepare("
    SELECT pm.*, p.prescription_date, u.full_name as doctor_name,
           p.diagnosis
    FROM prescription_medicines pm 
    JOIN prescriptions p ON pm.prescription_id = p.id 
    LEFT JOIN users u ON p.doctor_id = u.id 
    WHERE p.patient_id = ? AND pm.status = 'active' 
    ORDER BY pm.start_date DESC 
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentMeds = $stmt->fetchAll();

// ============================================================
// آخر الوصفات
// ============================================================
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as doctor_name 
    FROM prescriptions p 
    LEFT JOIN users u ON p.doctor_id = u.id 
    WHERE p.patient_id = ? 
    ORDER BY p.prescription_date DESC 
    LIMIT 3
");
$stmt->execute([$user['id']]);
$recentPrescriptions = $stmt->fetchAll();

// تحويل البيانات إلى JSON
$appointmentsJson = json_encode($recentAppointments);
$medicationsJson = json_encode($recentMeds);
$prescriptionsJson = json_encode($recentPrescriptions);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرئيسية - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        body.dark-mode .stat-card {
            background: #1E1E1E;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-icon {
            position: absolute;
            top: 1rem;
            left: 1rem;
            font-size: 2.5rem;
            color: var(--primary-soft);
            opacity: 0.5;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .info-item:hover {
            background: var(--primary-soft);
            padding: 0.8rem 1rem;
            border-radius: 10px;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .quick-action-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .quick-action-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .reminder-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--danger);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }
        
        .blood-type-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            background: var(--danger);
            color: white;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
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
        
        /* تنسيق النافذة الجانبية */
        .info-row {
            display: flex;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .info-label {
            width: 120px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .detail-section {
            background: var(--light);
            border-radius: var(--radius-md);
            padding: 1rem;
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
            
            <!-- قسم الترحيب -->
            <div class="welcome-section">
                <div>
                    <h1>مرحباً <?= explode(' ', $user['full_name'])[0] ?> 👋</h1>
                    <p>نتمنى لك دوام الصحة والعافية</p>
                </div>
                
                <div class="quick-actions">
                    <a href="find_doctor.php" class="quick-action-btn">
                        <i class="fas fa-search"></i> ابحث عن طبيب
                    </a>
                    <a href="pharmacy.php" class="quick-action-btn">
                        <i class="fas fa-store"></i> الصيدلية
                    </a>
                    <span class="quick-action-btn">
                        <i class="fas fa-calendar"></i> <?= date('Y-m-d') ?>
                    </span>
                </div>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='medications.php?tab=active'">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                    <div class="stat-number"><?= $activeMeds ?></div>
                    <div style="color: var(--gray);">دواء نشط</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='medications.php?tab=appointments'">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?= $upcomingApps ?></div>
                    <div style="color: var(--gray);">موعد قادم</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='consultations.php'">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-number"><?= $unreadMessages ?></div>
                    <div style="color: var(--gray);">رسالة غير مقروءة</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tint"></i></div>
                    <div class="stat-number">
                        <?php if (!empty($patient['blood_type'])): ?>
                            <span class="blood-type-badge"><?= $patient['blood_type'] ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                    <div style="color: var(--gray);">فصيلة الدم</div>
                </div>
            </div>
            
            <!-- الشبكة الرئيسية -->
            <div class="dashboard-grid">
                <!-- آخر المواعيد -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> آخر المواعيد</h3>
                        <a href="medications.php?tab=appointments" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <?php if (empty($recentAppointments)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--gray);"></i>
                            <p style="margin-top: 1rem;">لا توجد مواعيد</p>
                            <a href="find_doctor.php" class="btn btn-primary btn-sm" style="margin-top: 1rem;">
                                <i class="fas fa-search"></i> احجز موعداً الآن
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentAppointments as $a): ?>
                            <div class="info-item" onclick="viewAppointmentDetails(<?= $a['id'] ?>)">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="doctor-avatar-mini">
                                        <?php if (!empty($a['doctor_image'])): ?>
                                            <img src="../<?= $a['doctor_image'] ?>" alt="د">
                                        <?php else: ?>
                                            <i class="fas fa-user-md"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong>د. <?= htmlspecialchars($a['doctor_name']) ?></strong>
                                        <div style="font-size: 0.9rem; color: var(--gray);">
                                            <?= $a['specialties'] ?> • <?= date('Y-m-d H:i', strtotime($a['appointment_date'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge badge-<?= $a['status'] ?>">
                                    <?= getStatusText($a['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- آخر الأدوية -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-pills"></i> آخر الأدوية</h3>
                        <a href="medications.php?tab=active" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <?php if (empty($recentMeds)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-pills" style="font-size: 3rem; color: var(--gray);"></i>
                            <p style="margin-top: 1rem;">لا توجد أدوية نشطة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentMeds as $m): ?>
                            <div class="info-item" onclick="viewMedicationDetails(<?= $m['id'] ?>)">
                                <div>
                                    <strong><?= htmlspecialchars($m['medicine_name']) ?></strong>
                                    <div style="font-size: 0.9rem; color: var(--gray);">
                                        <?= $m['dosage'] ?> - <?= $m['frequency'] ?>
                                        <?php if ($m['doctor_name']): ?>
                                            • د. <?= htmlspecialchars($m['doctor_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge badge-success">نشط</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- آخر الوصفات -->
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-prescription"></i> آخر الوصفات</h3>
                    <a href="medications.php?tab=prescriptions" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                </div>
                
                <?php if (empty($recentPrescriptions)): ?>
                    <p class="text-center" style="padding: 2rem;">لا توجد وصفات طبية</p>
                <?php else: ?>
                    <?php foreach ($recentPrescriptions as $p): ?>
                        <div class="info-item" onclick="viewPrescriptionDetails(<?= $p['id'] ?>)">
                            <div>
                                <strong>د. <?= htmlspecialchars($p['doctor_name'] ?? 'غير محدد') ?></strong>
                                <div style="font-size: 0.9rem; color: var(--gray);">
                                    <?= $p['prescription_date'] ?> • <?= $p['diagnosis'] ?: 'بدون تشخيص' ?>
                                </div>
                            </div>
                            <span class="badge badge-primary">وصفة</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النوافذ الجانبية للتفاصيل
         ============================================================ -->
    
    <!-- نافذة تفاصيل الموعد -->
    <div class="side-modal" id="appointmentDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-calendar-alt"></i> تفاصيل الموعد</h3>
            <button class="close-side-modal" onclick="closeSideModal('appointmentDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="appointmentDetailsContent"></div>
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
    
    <script src="../assets/js/main.js"></script>
    <script>
    // بيانات JSON
    const appointments = <?= $appointmentsJson ?>;
    const medications = <?= $medicationsJson ?>;
    const prescriptions = <?= $prescriptionsJson ?>;
    
    // ============================================================
    // عرض تفاصيل الموعد
    // ============================================================
    function viewAppointmentDetails(id) {
        event?.stopPropagation();
        
        const a = appointments.find(apt => apt.id == id);
        
        if (a) {
            openSideModal('appointmentDetailsModal');
            
            let statusClass = '';
            let statusText = '';
            
            switch(a.status) {
                case 'pending':
                    statusClass = 'badge-warning';
                    statusText = 'قيد الانتظار';
                    break;
                case 'confirmed':
                    statusClass = 'badge-success';
                    statusText = 'مؤكد';
                    break;
                case 'completed':
                    statusClass = 'badge-info';
                    statusText = 'مكتمل';
                    break;
                case 'cancelled':
                    statusClass = 'badge-danger';
                    statusText = 'ملغي';
                    break;
            }
            
            document.getElementById('appointmentDetailsContent').innerHTML = `
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div class="doctor-avatar-mini" style="width: 80px; height: 80px; margin: 0 auto;">
                        ${a.doctor_image ? `<img src="../${a.doctor_image}">` : '<i class="fas fa-user-md fa-2x"></i>'}
                    </div>
                    <h3 style="margin-top: 1rem;">د. ${a.doctor_name}</h3>
                    <p style="color: var(--gray);">${a.specialties || ''}</p>
                </div>
                
                <hr>
                
                <div class="detail-section">
                    <div class="info-row">
                        <span class="info-label">التاريخ:</span>
                        <span class="info-value">${a.appointment_date}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الحالة:</span>
                        <span class="info-value"><span class="badge ${statusClass}">${statusText}</span></span>
                    </div>
                </div>
                
                ${a.reason ? `
                    <div class="detail-section">
                        <strong><i class="fas fa-comment"></i> سبب الزيارة:</strong>
                        <p style="margin-top: 0.5rem;">${a.reason}</p>
                    </div>
                ` : ''}
                
                ${a.notes ? `
                    <div class="detail-section">
                        <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong>
                        <p style="margin-top: 0.5rem;">${a.notes}</p>
                    </div>
                ` : ''}
            `;
        }
    }
    
    // ============================================================
    // عرض تفاصيل الدواء
    // ============================================================
    function viewMedicationDetails(id) {
        event?.stopPropagation();
        
        const m = medications.find(med => med.id == id);
        
        if (m) {
            openSideModal('medicationDetailsModal');
            
            document.getElementById('medicationDetailsContent').innerHTML = `
                <h2 style="color: var(--primary);">${m.medicine_name}</h2>
                
                ${m.doctor_name ? `<p style="color: var(--gray);">وصفة من د. ${m.doctor_name}</p>` : ''}
                
                <hr>
                
                <div class="detail-section">
                    <div class="info-row">
                        <span class="info-label">الجرعة:</span>
                        <span class="info-value">${m.dosage}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">التكرار:</span>
                        <span class="info-value">${m.frequency}</span>
                    </div>
                    ${m.duration ? `
                        <div class="info-row">
                            <span class="info-label">المدة:</span>
                            <span class="info-value">${m.duration}</span>
                        </div>
                    ` : ''}
                    <div class="info-row">
                        <span class="info-label">تاريخ البدء:</span>
                        <span class="info-value">${m.start_date || m.prescription_date}</span>
                    </div>
                    ${m.end_date ? `
                        <div class="info-row">
                            <span class="info-label">تاريخ الانتهاء:</span>
                            <span class="info-value">${m.end_date}</span>
                        </div>
                    ` : ''}
                </div>
                
                ${m.diagnosis ? `
                    <div class="detail-section">
                        <strong><i class="fas fa-stethoscope"></i> التشخيص:</strong>
                        <p style="margin-top: 0.5rem;">${m.diagnosis}</p>
                    </div>
                ` : ''}
                
                ${m.instructions ? `
                    <div class="detail-section">
                        <strong><i class="fas fa-info-circle"></i> تعليمات:</strong>
                        <p style="margin-top: 0.5rem;">${m.instructions}</p>
                    </div>
                ` : ''}
            `;
        }
    }
    
    // ============================================================
    // عرض تفاصيل الوصفة
    // ============================================================
    function viewPrescriptionDetails(id) {
        event?.stopPropagation();
        
        const p = prescriptions.find(presc => presc.id == id);
        
        if (p) {
            openSideModal('prescriptionDetailsModal');
            
            document.getElementById('prescriptionDetailsContent').innerHTML = `
                <h3 style="color: var(--primary);">وصفة طبية</h3>
                <p style="color: var(--gray);">من د. ${p.doctor_name || 'غير محدد'}</p>
                
                <hr>
                
                <div class="detail-section">
                    <div class="info-row">
                        <span class="info-label">التاريخ:</span>
                        <span class="info-value">${p.prescription_date}</span>
                    </div>
                    ${p.diagnosis ? `
                        <div class="info-row">
                            <span class="info-label">التشخيص:</span>
                            <span class="info-value">${p.diagnosis}</span>
                        </div>
                    ` : ''}
                </div>
                
                ${p.notes ? `
                    <div class="detail-section">
                        <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong>
                        <p style="margin-top: 0.5rem;">${p.notes}</p>
                    </div>
                ` : ''}
            `;
        }
    }
    </script>
</body>
</html>