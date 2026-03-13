<?php
/**
 * doctor/agenda.php - الأجندة والمواعيد
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم طبيب
requireDoctor($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// ============================================================
// تحديث حالة الموعد
// ============================================================
if (isset($_GET['update'])) {
    $appointment_id = (int)$_GET['update'];
    $status = $_GET['status'];
    
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$status, $appointment_id, $user['id']]);
    
    // إشعار للمريض
    $stmt2 = $pdo->prepare("SELECT patient_id, appointment_date FROM appointments WHERE id = ?");
    $stmt2->execute([$appointment_id]);
    $apt = $stmt2->fetch();
    
    $statusText = getStatusText($status);
    createNotification($pdo, $apt['patient_id'], 'appointment', 'تحديث حالة الموعد', "تم تحديث حالة موعدك إلى: $statusText");
    
    setToast('تم تحديث حالة الموعد', 'success');
    redirect('agenda.php?filter=' . ($_GET['filter'] ?? 'upcoming'));
}

// ============================================================
// جلب المواعيد حسب الفلتر
// ============================================================
$filter = $_GET['filter'] ?? 'upcoming';

if ($filter == 'pending') {
    // طلبات المواعيد المعلقة
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as patient_name, u.phone, u.profile_image, p.emergency_phone
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        LEFT JOIN patients p ON a.patient_id = p.user_id
        WHERE a.doctor_id = ? AND a.status = 'pending'
        ORDER BY a.appointment_date
    ");
} elseif ($filter == 'today') {
    // مواعيد اليوم
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as patient_name, u.phone, u.profile_image, p.emergency_phone
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        LEFT JOIN patients p ON a.patient_id = p.user_id
        WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status != 'cancelled'
        ORDER BY a.appointment_date
    ");
} elseif ($filter == 'past') {
    // المواعيد السابقة
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as patient_name, u.phone, u.profile_image
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? AND a.appointment_date < NOW()
        ORDER BY a.appointment_date DESC
    ");
} else {
    // المواعيد القادمة (الافتراضي)
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as patient_name, u.phone, u.profile_image, p.emergency_phone
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        LEFT JOIN patients p ON a.patient_id = p.user_id
        WHERE a.doctor_id = ? AND a.appointment_date >= NOW() AND a.status != 'cancelled'
        ORDER BY a.appointment_date
    ");
}

$stmt->execute([$user['id']]);
$appointments = $stmt->fetchAll();

// ============================================================
// إحصائيات سريعة
// ============================================================
$stats = [
    'pending' => $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'pending'")->execute([$user['id']]) ? 
                $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = {$user['id']} AND status = 'pending'")->fetchColumn() : 0,
    'today' => $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status != 'cancelled'")->execute([$user['id']]) ? 
               $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = {$user['id']} AND DATE(appointment_date) = CURDATE() AND status != 'cancelled'")->fetchColumn() : 0,
    'upcoming' => $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date >= NOW() AND status NOT IN ('cancelled', 'completed')")->execute([$user['id']]) ? 
                  $pdo->query("SELECT COUNT(*) FROM appointments WHERE doctor_id = {$user['id']} AND appointment_date >= NOW() AND status NOT IN ('cancelled', 'completed')")->fetchColumn() : 0
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأجندة - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-mini-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        body.dark-mode .stat-mini-card {
            background: #1E1E1E;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .stat-mini-card.active {
            border-color: var(--primary);
            background: var(--primary-soft);
        }
        
        .stat-mini-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        body.dark-mode .filter-tabs {
            background: #1E1E1E;
        }
        
        .filter-tab {
            padding: 0.8rem 1.5rem;
            background: var(--light-gray);
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        body.dark-mode .filter-tab {
            background: #333;
            color: white;
        }
        
        .filter-tab:hover {
            background: var(--primary-soft);
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
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
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .patient-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .patient-avatar-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            overflow: hidden;
        }
        
        .patient-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .patient-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .appointment-datetime {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .appointment-meta {
            display: flex;
            gap: 2rem;
            margin: 1rem 0;
            color: var(--gray);
            flex-wrap: wrap;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--radius-lg);
        }
        
        body.dark-mode .empty-state {
            background: #1E1E1E;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .quick-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-soft);
            color: var(--primary);
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .quick-action-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .emergency-contact {
            background: rgba(231, 111, 81, 0.1);
            color: var(--danger);
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
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
            
            <h1 class="page-title" style="margin-bottom: 2rem;">
                <i class="fas fa-calendar-alt"></i> الأجندة
            </h1>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-mini-grid">
                <a href="?filter=pending" class="stat-mini-card <?= $filter == 'pending' ? 'active' : '' ?>">
                    <div class="stat-mini-number"><?= $stats['pending'] ?></div>
                    <div style="color: var(--gray);">طلبات معلقة</div>
                </a>
                
                <a href="?filter=today" class="stat-mini-card <?= $filter == 'today' ? 'active' : '' ?>">
                    <div class="stat-mini-number"><?= $stats['today'] ?></div>
                    <div style="color: var(--gray);">مواعيد اليوم</div>
                </a>
                
                <a href="?filter=upcoming" class="stat-mini-card <?= $filter == 'upcoming' ? 'active' : '' ?>">
                    <div class="stat-mini-number"><?= $stats['upcoming'] ?></div>
                    <div style="color: var(--gray);">مواعيد قادمة</div>
                </a>
            </div>
            
            <!-- فلاتر المواعيد -->
            <div class="filter-tabs">
                <a href="?filter=pending" class="filter-tab <?= $filter == 'pending' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i>
                    <span>قيد الانتظار (<?= $stats['pending'] ?>)</span>
                </a>
                <a href="?filter=today" class="filter-tab <?= $filter == 'today' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i>
                    <span>اليوم (<?= $stats['today'] ?>)</span>
                </a>
                <a href="?filter=upcoming" class="filter-tab <?= $filter == 'upcoming' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-week"></i>
                    <span>القادمة (<?= $stats['upcoming'] ?>)</span>
                </a>
                <a href="?filter=past" class="filter-tab <?= $filter == 'past' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>السابقة</span>
                </a>
            </div>
            
            <!-- قائمة المواعيد -->
            <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>لا توجد مواعيد</h3>
                    <p style="color: var(--gray); margin-top: 0.5rem;">
                        <?php if ($filter == 'pending'): ?>
                            لا توجد طلبات مواعيد معلقة
                        <?php elseif ($filter == 'today'): ?>
                            لا توجد مواعيد لليوم
                        <?php elseif ($filter == 'past'): ?>
                            لا توجد مواعيد سابقة
                        <?php else: ?>
                            لا توجد مواعيد قادمة
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($appointments as $a): ?>
                    <div class="appointment-card <?= $a['status'] ?>" onclick="showAppointmentDetails(<?= $a['id'] ?>)">
                        <div class="appointment-header">
                            <div class="patient-info">
                                <div class="patient-avatar-small">
                                    <?php if (!empty($a['profile_image'])): ?>
                                        <img src="../<?= $a['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <?= mb_substr($a['patient_name'], 0, 1) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="patient-name"><?= htmlspecialchars($a['patient_name']) ?></div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.3rem;">
                                        <i class="fas fa-phone" style="font-size: 0.8rem; color: var(--gray);"></i>
                                        <span style="font-size: 0.9rem;"><?= $a['phone'] ?></span>
                                        <?php if (!empty($a['emergency_phone'])): ?>
                                            <span class="emergency-contact">
                                                <i class="fas fa-phone-alt"></i> طوارئ
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="appointment-datetime">
                                <i class="fas fa-clock"></i>
                                <span><?= date('Y-m-d H:i', strtotime($a['appointment_date'])) ?></span>
                            </div>
                        </div>
                        
                        <?php if ($a['reason']): ?>
                            <div class="appointment-meta">
                                <span><i class="fas fa-comment"></i> <?= htmlspecialchars($a['reason']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- أزرار الإجراءات حسب الحالة -->
                        <div class="action-buttons" onclick="event.stopPropagation()">
                            <?php if ($a['status'] == 'pending'): ?>
                                <a href="?update=<?= $a['id'] ?>&status=confirmed&filter=<?= $filter ?>" class="btn btn-success btn-sm" onclick="return confirm('تأكيد هذا الموعد؟')">
                                    <i class="fas fa-check"></i> قبول
                                </a>
                                <a href="?update=<?= $a['id'] ?>&status=cancelled&filter=<?= $filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('رفض هذا الموعد؟')">
                                    <i class="fas fa-times"></i> رفض
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($a['status'] == 'confirmed'): ?>
                                <a href="?update=<?= $a['id'] ?>&status=completed&filter=<?= $filter ?>" class="btn btn-info btn-sm" onclick="return confirm('تأكيد إتمام الموعد؟')">
                                    <i class="fas fa-check-circle"></i> تمت الزيارة
                                </a>
                                <a href="?update=<?= $a['id'] ?>&status=cancelled&filter=<?= $filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('إلغاء هذا الموعد؟')">
                                    <i class="fas fa-ban"></i> إلغاء
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline btn-sm" onclick="window.location.href='patient_view.php?id=<?= $a['patient_id'] ?>'">
                                <i class="fas fa-user"></i> ملف المريض
                            </button>
                            
                            <?php if (strtotime($a['appointment_date']) > time()): ?>
                                <button class="btn btn-outline btn-sm" onclick="openRescheduleModal(<?= $a['id'] ?>)">
                                    <i class="fas fa-calendar-alt"></i> إعادة جدولة
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================================
         النوافذ الجانبية
         ============================================================ -->
    
    <!-- نافذة تفاصيل الموعد -->
    <div class="side-modal" id="appointmentDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-calendar-alt"></i> تفاصيل الموعد</h3>
            <button class="close-side-modal" onclick="closeSideModal('appointmentDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="appointmentDetailsContent"></div>
    </div>
    
    <!-- نافذة إعادة جدولة الموعد -->
    <div class="side-modal" id="rescheduleModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-calendar-alt"></i> إعادة جدولة الموعد</h3>
            <button class="close-side-modal" onclick="closeSideModal('rescheduleModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="get" id="rescheduleForm">
                <input type="hidden" name="update" id="rescheduleAppointmentId">
                <input type="hidden" name="status" value="confirmed">
                <input type="hidden" name="filter" value="<?= $filter ?>">
                
                <div class="form-group">
                    <label>التاريخ الجديد</label>
                    <input type="datetime-local" name="new_date" class="form-control" required 
                           min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
                </div>
                
                <div class="form-group">
                    <label>سبب إعادة الجدولة (اختياري)</label>
                    <textarea name="reason" class="form-control" rows="2"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">تأكيد إعادة الجدولة</button>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // عرض تفاصيل الموعد
    // ============================================================
    function showAppointmentDetails(id) {
        fetch(`../api/doctor/get_appointment_details.php?id=${id}`)
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
                        <div class="text-center">
                            <h3>${a.patient_name}</h3>
                            <p style="color: var(--gray);">${a.phone}</p>
                        </div>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            <p><i class="fas fa-calendar"></i> <strong>التاريخ:</strong> ${a.appointment_date}</p>
                            <p><i class="fas fa-clock"></i> <strong>الحالة:</strong> <span class="badge ${statusClass}">${statusText}</span></p>
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
                            <button class="btn btn-primary" onclick="window.location.href='patient_view.php?id=${a.patient_id}'" style="flex: 1;">
                                <i class="fas fa-user"></i> ملف المريض
                            </button>
                            <button class="btn btn-outline" onclick="window.location.href='consultations.php?patient=${a.patient_id}'" style="flex: 1;">
                                <i class="fas fa-comment"></i> رسالة
                            </button>
                        </div>
                    `;
                    
                    openSideModal('appointmentDetailsModal');
                }
            });
    }
    
    // ============================================================
    // فتح نافذة إعادة الجدولة
    // ============================================================
    function openRescheduleModal(id) {
        event.stopPropagation();
        document.getElementById('rescheduleAppointmentId').value = id;
        openSideModal('rescheduleModal');
    }
    </script>
</body>
</html>