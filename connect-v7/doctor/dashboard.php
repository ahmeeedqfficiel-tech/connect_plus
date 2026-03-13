<?php
/**
 * doctor/dashboard.php - الصفحة الرئيسية للطبيب
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم طبيب
requireDoctor($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);
$doctor = getDoctorData($pdo, $user['id']);

// ============================================================
// إحصائيات سريعة
// ============================================================

// عدد المرضى
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?");
$stmt->execute([$user['id']]);
$patientCount = $stmt->fetchColumn();

// مواعيد اليوم
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status != 'cancelled'");
$stmt->execute([$user['id']]);
$todayAppointments = $stmt->fetchColumn();

// طلبات المواعيد المعلقة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$pendingAppointments = $stmt->fetchColumn();

// متوسط التقييم
$stmt = $pdo->prepare("SELECT AVG(rating) FROM ratings WHERE doctor_id = ?");
$stmt->execute([$user['id']]);
$avgRating = round($stmt->fetchColumn() ?: 0, 1);

// ============================================================
// آخر المواعيد
// ============================================================
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as patient_name, u.profile_image 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    WHERE a.doctor_id = ? 
    ORDER BY a.appointment_date DESC 
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentAppointments = $stmt->fetchAll();

// ============================================================
// آخر المرضى
// ============================================================
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name, u.profile_image,
           (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = u.id AND doctor_id = ?) as last_visit
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = ?
    ORDER BY last_visit DESC
    LIMIT 5
");
$stmt->execute([$user['id'], $user['id']]);
$recentPatients = $stmt->fetchAll();

// ============================================================
// آخر الوصفات
// ============================================================
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as patient_name 
    FROM prescriptions p
    JOIN users u ON p.patient_id = u.id
    WHERE p.doctor_id = ?
    ORDER BY p.prescription_date DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentPrescriptions = $stmt->fetchAll();
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
            transition: var(--transition);
            position: relative;
            overflow: hidden;
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
        
        .availability-badge {
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.2);
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
        
        .appointment-item, .patient-item, .prescription-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .appointment-item:hover, .patient-item:hover, .prescription-item:hover {
            background: var(--primary-soft);
            border-radius: var(--radius-md);
        }
        
        .appointment-item:last-child, .patient-item:last-child, .prescription-item:last-child {
            border-bottom: none;
        }
        
        .patient-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .patient-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
        }
        
        .item-meta {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .badge-pending { background: var(--warning); color: white; }
        .badge-confirmed { background: var(--success); color: white; }
        .badge-completed { background: var(--info); color: white; }
        .badge-cancelled { background: var(--danger); color: white; }
        
        .rating-stars {
            color: #F4A261;
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
                    <h1>مرحباً د. <?= explode(' ', $user['full_name'])[0] ?> 👨‍⚕️</h1>
                    <p><?= htmlspecialchars($doctor['workplace_name'] ?: 'عضو في CONNECT+') ?></p>
                </div>
                
                <div class="quick-actions">
                    <span class="availability-badge <?= $doctor['is_available'] ? '' : 'unavailable' ?>">
                        <i class="fas fa-<?= $doctor['is_available'] ? 'check-circle' : 'clock' ?>"></i>
                        <?= $doctor['is_available'] ? 'متاح الآن' : 'غير متاح' ?>
                    </span>
                    
                    <a href="agenda.php" class="quick-action-btn">
                        <i class="fas fa-calendar-alt"></i> الأجندة
                    </a>
                    
                    <button class="quick-action-btn" onclick="openSideModal('addPatientSideModal')">
                        <i class="fas fa-user-plus"></i> إضافة مريض
                    </button>
                </div>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='patients.php'">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= $patientCount ?></div>
                    <div style="color: var(--gray);">إجمالي المرضى</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='agenda.php?filter=today'">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?= $todayAppointments ?></div>
                    <div style="color: var(--gray);">مواعيد اليوم</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='agenda.php?filter=pending'">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?= $pendingAppointments ?></div>
                    <div style="color: var(--gray);">طلبات معلقة</div>
                    <?php if ($pendingAppointments > 0): ?>
                        <span class="badge badge-danger" style="position: absolute; top: 0.5rem; right: 0.5rem;">!</span>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-number"><?= $avgRating ?></div>
                    <div style="color: var(--gray);">التقييم</div>
                </div>
            </div>
            
            <!-- الشبكة الرئيسية -->
            <div class="dashboard-grid">
                <!-- آخر المواعيد -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> آخر المواعيد</h3>
                        <a href="agenda.php" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <?php if (empty($recentAppointments)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--gray);"></i>
                            <p style="margin-top: 1rem;">لا توجد مواعيد</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentAppointments as $a): ?>
                            <div class="appointment-item" onclick="showAppointmentDetails(<?= $a['id'] ?>)">
                                <div class="patient-avatar-small">
                                    <?php if (!empty($a['profile_image'])): ?>
                                        <img src="../<?= $a['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <?= mb_substr($a['patient_name'], 0, 1) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($a['patient_name']) ?></div>
                                    <div class="item-meta">
                                        <?= date('Y-m-d H:i', strtotime($a['appointment_date'])) ?>
                                    </div>
                                </div>
                                <span class="badge badge-<?= $a['status'] ?>">
                                    <?= getStatusText($a['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- آخر المرضى -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-friends"></i> آخر المرضى</h3>
                        <a href="patients.php" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <?php if (empty($recentPatients)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-users" style="font-size: 3rem; color: var(--gray);"></i>
                            <p style="margin-top: 1rem;">لا يوجد مرضى</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentPatients as $p): ?>
                            <div class="patient-item" onclick="window.location.href='patient_view.php?id=<?= $p['id'] ?>'">
                                <div class="patient-avatar-small">
                                    <?php if (!empty($p['profile_image'])): ?>
                                        <img src="../<?= $p['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <?= mb_substr($p['full_name'], 0, 1) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($p['full_name']) ?></div>
                                    <div class="item-meta">
                                        آخر زيارة: <?= $p['last_visit'] ? date('Y-m-d', strtotime($p['last_visit'])) : 'لا توجد' ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-left" style="color: var(--gray);"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- آخر الوصفات -->
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-prescription"></i> آخر الوصفات</h3>
                    <a href="prescriptions.php" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                </div>
                
                <?php if (empty($recentPrescriptions)): ?>
                    <p class="text-center" style="padding: 2rem;">لا توجد وصفات</p>
                <?php else: ?>
                    <?php foreach ($recentPrescriptions as $p): ?>
                        <div class="prescription-item" onclick="showPrescriptionDetails(<?= $p['id'] ?>)">
                            <div class="patient-avatar-small">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="item-info">
                                <div class="item-name"><?= htmlspecialchars($p['patient_name']) ?></div>
                                <div class="item-meta">
                                    <?= $p['prescription_date'] ?> • <?= $p['diagnosis'] ?: 'بدون تشخيص' ?>
                                </div>
                            </div>
                            <i class="fas fa-file-prescription" style="color: var(--primary);"></i>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- معلومات العمل -->
            <div class="info-card">
                <h3 class="card-title"><i class="fas fa-briefcase"></i> معلومات العمل</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">مكان العمل</div>
                        <div><?= htmlspecialchars($doctor['workplace_name'] ?: 'غير محدد') ?></div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">العنوان</div>
                        <div><?= htmlspecialchars($doctor['workplace_address'] ?: 'غير محدد') ?></div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">ساعات العمل</div>
                        <div>
                            <?php if ($doctor['available_from'] && $doctor['available_to']): ?>
                                <?= $doctor['available_from'] ?> - <?= $doctor['available_to'] ?>
                            <?php else: ?>
                                غير محدد
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">رسوم الكشف</div>
                        <div><?= $doctor['consultation_fees'] ?: 'غير محدد' ?> دج</div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">التقييم</div>
                        <div class="rating-stars">
                            <?php 
                            $rating = round($avgRating);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النوافذ الجانبية للتفاصيل
         ============================================================ -->
    
    <!-- نافذة تفاصيل الموعد -->
    <div class="side-modal" id="appointmentDetailsSideModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-calendar-alt"></i> تفاصيل الموعد</h3>
            <button class="close-side-modal" onclick="closeSideModal('appointmentDetailsSideModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="appointmentDetailsContent">
            <!-- يتم ملؤها بالجافاسكريبت -->
        </div>
    </div>
    
    <!-- نافذة تفاصيل الوصفة -->
    <div class="side-modal" id="prescriptionDetailsSideModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-prescription"></i> تفاصيل الوصفة</h3>
            <button class="close-side-modal" onclick="closeSideModal('prescriptionDetailsSideModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="prescriptionDetailsContent">
            <!-- يتم ملؤها بالجافاسكريبت -->
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // عرض تفاصيل الموعد
    // ============================================================
    function showAppointmentDetails(id) {
        fetch(`../api/doctor/get_appointment.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const a = data.data;
                    
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
                            <div class="patient-avatar-small" style="width: 80px; height: 80px; margin: 0 auto;">
                                ${a.profile_image ? `<img src="../${a.profile_image}">` : a.patient_name.charAt(0)}
                            </div>
                            <h3 style="margin-top: 1rem;">${a.patient_name}</h3>
                        </div>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            <p><i class="fas fa-calendar"></i> <strong>التاريخ:</strong> ${a.appointment_date}</p>
                            <p><i class="fas fa-clock"></i> <strong>الحالة:</strong> <span class="badge ${statusClass}">${statusText}</span></p>
                            ${a.phone ? `<p><i class="fas fa-phone"></i> <strong>الهاتف:</strong> ${a.phone}</p>` : ''}
                        </div>
                        
                        ${a.reason ? `
                            <div style="margin: 1rem 0;">
                                <strong><i class="fas fa-comment"></i> سبب الزيارة:</strong>
                                <p style="margin-top: 0.5rem; padding: 1rem; background: var(--light); border-radius: 10px;">${a.reason}</p>
                            </div>
                        ` : ''}
                        
                        ${a.notes ? `
                            <div style="margin: 1rem 0;">
                                <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong>
                                <p style="margin-top: 0.5rem; padding: 1rem; background: var(--light); border-radius: 10px;">${a.notes}</p>
                            </div>
                        ` : ''}
                        
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            ${a.status === 'pending' ? `
                                <button class="btn btn-success btn-sm" onclick="updateAppointmentStatus(${a.id}, 'confirmed')" style="flex: 1;">
                                    <i class="fas fa-check"></i> قبول
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="updateAppointmentStatus(${a.id}, 'cancelled')" style="flex: 1;">
                                    <i class="fas fa-times"></i> رفض
                                </button>
                            ` : ''}
                            
                            ${a.status === 'confirmed' ? `
                                <button class="btn btn-info btn-sm" onclick="updateAppointmentStatus(${a.id}, 'completed')" style="flex: 1;">
                                    <i class="fas fa-check-circle"></i> إتمام
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="updateAppointmentStatus(${a.id}, 'cancelled')" style="flex: 1;">
                                    <i class="fas fa-ban"></i> إلغاء
                                </button>
                            ` : ''}
                            
                            <button class="btn btn-primary btn-sm" onclick="viewPatientProfile(${a.patient_id})" style="flex: 1;">
                                <i class="fas fa-user"></i> ملف المريض
                            </button>
                        </div>
                    `;
                    
                    openSideModal('appointmentDetailsSideModal');
                }
            });
    }
    
    // ============================================================
    // تحديث حالة الموعد
    // ============================================================
    function updateAppointmentStatus(id, status) {
        if (!confirm('تأكيد تحديث حالة الموعد؟')) return;
        
        fetch('../api/doctor/update_appointment_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id, status: status})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم تحديث الحالة', 'success');
                closeSideModal('appointmentDetailsSideModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('حدث خطأ', 'error');
            }
        });
    }
    
    // ============================================================
    // عرض تفاصيل الوصفة
    // ============================================================
    function showPrescriptionDetails(id) {
        fetch(`../api/doctor/get_prescription.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const p = data.data;
                    
                    let medicinesHtml = '';
                    if (p.medicines && p.medicines.length > 0) {
                        medicinesHtml = '<h4 style="margin: 1rem 0;">الأدوية</h4>';
                        p.medicines.forEach(m => {
                            medicinesHtml += `
                                <div style="margin: 1rem 0; padding: 1rem; background: var(--light); border-radius: 10px;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <strong>${m.medicine_name}</strong>
                                        <span class="badge ${m.status === 'active' ? 'badge-success' : 'badge-secondary'}">
                                            ${m.status === 'active' ? 'نشط' : 'مكتمل'}
                                        </span>
                                    </div>
                                    <p style="margin-top: 0.5rem;">${m.dosage} - ${m.frequency}</p>
                                </div>
                            `;
                        });
                    }
                    
                    document.getElementById('prescriptionDetailsContent').innerHTML = `
                        <h3>وصفة طبية</h3>
                        <p style="color: var(--gray);">للمريض: ${p.patient_name}</p>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            <p><i class="fas fa-calendar"></i> <strong>التاريخ:</strong> ${p.prescription_date}</p>
                            ${p.diagnosis ? `<p><i class="fas fa-stethoscope"></i> <strong>التشخيص:</strong> ${p.diagnosis}</p>` : ''}
                        </div>
                        
                        ${medicinesHtml}
                        
                        ${p.notes ? `
                            <div style="margin: 1rem 0;">
                                <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong>
                                <p style="margin-top: 0.5rem; padding: 1rem; background: var(--light); border-radius: 10px;">${p.notes}</p>
                            </div>
                        ` : ''}
                        
                        <button class="btn btn-primary" onclick="viewPatientProfile(${p.patient_id})" style="width: 100%; margin-top: 1rem;">
                            <i class="fas fa-user"></i> عرض ملف المريض
                        </button>
                    `;
                    
                    openSideModal('prescriptionDetailsSideModal');
                }
            });
    }
    
    // ============================================================
    // عرض ملف المريض
    // ============================================================
    function viewPatientProfile(patientId) {
        window.location.href = `patient_view.php?id=${patientId}`;
    }
    </script>
</body>
</html>