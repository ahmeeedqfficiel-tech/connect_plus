<?php
/**
 * admin/dashboard.php - لوحة التحكم الرئيسية مع نوافذ جانبية
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// ============================================================
// إحصائيات عامة
// ============================================================

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn();
$totalPharmacists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pharmacist'")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

$verifiedUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn();
$unverifiedUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 0 AND role != 'admin'")->fetchColumn();

$pendingApprovals = $pdo->query("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'")->fetchColumn();
$pendingChanges = $pdo->query("SELECT COUNT(*) FROM profile_changes WHERE status = 'pending'")->fetchColumn();

$todayUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$todayAppointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetchColumn();
$todayOrders = $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE DATE(order_date) = CURDATE()")->fetchColumn();

// ============================================================
// آخر المستخدمين المسجلين
// ============================================================
$recentUsers = $pdo->query("
    SELECT id, user_code, full_name, email, role, is_verified, created_at, profile_image, city, phone
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// ============================================================
// آخر النشاطات
// ============================================================
$recentActivities = $pdo->query("
    SELECT a.*, u.full_name, u.profile_image, u.role, u.user_code
    FROM activity_log a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
")->fetchAll();

// ============================================================
// إحصائيات حسب الشهر
// ============================================================
$monthlyStats = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
")->fetchAll();

// ============================================================
// توزيع المستخدمين حسب الدور
// ============================================================
$roleStats = [
    ['role' => 'مرضى', 'count' => $totalPatients, 'color' => '#2A9D8F'],
    ['role' => 'أطباء', 'count' => $totalDoctors, 'color' => '#E76F51'],
    ['role' => 'صيادلة', 'count' => $totalPharmacists, 'color' => '#F4A261'],
    ['role' => 'أدمن', 'count' => $totalAdmins, 'color' => '#264653']
];

// تحويل بيانات المستخدمين إلى JSON لاستخدامها في JavaScript
$recentUsersJson = json_encode($recentUsers);
$recentActivitiesJson = json_encode($recentActivities);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - CONNECT+</title>
    
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
        
        .stat-change {
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .positive {
            color: var(--success);
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
        
        .recent-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .recent-item:hover {
            background: var(--primary-soft);
            border-radius: var(--radius-md);
        }
        
        .recent-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .recent-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .recent-info {
            flex: 1;
        }
        
        .recent-name {
            font-weight: 600;
        }
        
        .recent-meta {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .recent-time {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .chart-container {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        body.dark-mode .chart-container {
            background: #1E1E1E;
        }
        
        .chart-bars {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            height: 200px;
            margin-top: 2rem;
            gap: 1rem;
        }
        
        .bar-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        
        .bar {
            width: 100%;
            background: linear-gradient(to top, var(--primary), var(--secondary));
            border-radius: 10px 10px 0 0;
            transition: height 0.3s ease;
            min-height: 4px;
        }
        
        .bar-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .role-distribution {
            display: flex;
            justify-content: space-around;
            margin: 2rem 0;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .role-item {
            text-align: center;
        }
        
        .role-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 0.5rem;
        }
        
        .alert-badge {
            background: var(--danger);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 30px;
            font-size: 0.7rem;
            margin-right: 0.5rem;
        }
        
        /* تنسيق النافذة الجانبية */
        .info-row {
            display: flex;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .info-label {
            width: 100px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .badge-sm {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
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
                    <p>مدير النظام - لوحة التحكم</p>
                </div>
                
                <div class="quick-actions">
                    <a href="pending_approvals.php" class="quick-action-btn">
                        <i class="fas fa-user-check"></i> طلبات التأكيد
                        <?php if ($pendingApprovals > 0): ?>
                            <span class="alert-badge"><?= $pendingApprovals ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="profile_changes.php" class="quick-action-btn">
                        <i class="fas fa-edit"></i> طلبات التعديل
                        <?php if ($pendingChanges > 0): ?>
                            <span class="alert-badge"><?= $pendingChanges ?></span>
                        <?php endif; ?>
                    </a>
                    <span class="quick-action-btn">
                        <i class="fas fa-calendar"></i> <?= date('Y-m-d') ?>
                    </span>
                </div>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='users.php'">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= $totalUsers ?></div>
                    <div style="color: var(--gray);">إجمالي المستخدمين</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> +<?= $todayUsers ?> اليوم
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='patients.php'">
                    <div class="stat-icon"><i class="fas fa-user"></i></div>
                    <div class="stat-number"><?= $totalPatients ?></div>
                    <div style="color: var(--gray);">المرضى</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='doctors.php'">
                    <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="stat-number"><?= $totalDoctors ?></div>
                    <div style="color: var(--gray);">الأطباء</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='pharmacists.php'">
                    <div class="stat-icon"><i class="fas fa-store"></i></div>
                    <div class="stat-number"><?= $totalPharmacists ?></div>
                    <div style="color: var(--gray);">الصيادلة</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='users.php?status=unverified'">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?= $unverifiedUsers ?></div>
                    <div style="color: var(--gray);">غير موثقين</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='pending_approvals.php'">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-number"><?= $pendingApprovals ?></div>
                    <div style="color: var(--gray);">بانتظار التأكيد</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='profile_changes.php'">
                    <div class="stat-icon"><i class="fas fa-edit"></i></div>
                    <div class="stat-number"><?= $pendingChanges ?></div>
                    <div style="color: var(--gray);">طلبات تعديل</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='appointments.php'">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?= $todayAppointments ?></div>
                    <div style="color: var(--gray);">مواعيد اليوم</div>
                </div>
            </div>
            
            <!-- الشبكة الرئيسية -->
            <div class="dashboard-grid">
                <!-- آخر المستخدمين -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-plus"></i> آخر المستخدمين</h3>
                        <a href="users.php" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <?php if (empty($recentUsers)): ?>
                        <p class="text-center" style="padding: 2rem;">لا يوجد مستخدمين</p>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $u): ?>
                            <div class="recent-item" onclick="viewUserDetails(<?= $u['id'] ?>)">
                                <div class="recent-avatar">
                                    <?php if (!empty($u['profile_image'])): ?>
                                        <img src="../<?= $u['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <i class="fas fa-<?= $u['role'] == 'doctor' ? 'user-md' : ($u['role'] == 'pharmacist' ? 'store' : ($u['role'] == 'admin' ? 'crown' : 'user')) ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="recent-info">
                                    <div class="recent-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                    <div class="recent-meta">
                                        <?= $u['email'] ?> • 
                                        <span class="badge badge-<?= $u['role'] ?> badge-sm"><?= getRoleText($u['role']) ?></span>
                                        <?php if (!$u['is_verified'] && $u['role'] != 'admin'): ?>
                                            <span class="badge badge-warning badge-sm">قيد الانتظار</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="recent-time"><?= timeAgo($u['created_at']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- آخر النشاطات -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> آخر النشاطات</h3>
                        <a href="logs.php" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <?php if (empty($recentActivities)): ?>
                        <p class="text-center" style="padding: 2rem;">لا توجد نشاطات</p>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $act): ?>
                            <div class="recent-item" onclick="viewActivityDetails(<?= $act['id'] ?>)">
                                <div class="recent-avatar">
                                    <?php if (!empty($act['profile_image'])): ?>
                                        <img src="../<?= $act['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <i class="fas fa-<?= $act['role'] == 'doctor' ? 'user-md' : ($act['role'] == 'pharmacist' ? 'store' : 'user') ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="recent-info">
                                    <div class="recent-name"><?= $act['full_name'] ?? 'نظام' ?></div>
                                    <div class="recent-meta"><?= $act['action'] ?> - <?= $act['description'] ?></div>
                                </div>
                                <div class="recent-time"><?= timeAgo($act['created_at']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- الرسم البياني -->
            <div class="chart-container">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> إحصائيات التسجيل (آخر 6 أشهر)</h3>
                
                <div class="chart-bars">
                    <?php 
                    $maxCount = 0;
                    foreach ($monthlyStats as $stat) {
                        if ($stat['count'] > $maxCount) $maxCount = $stat['count'];
                    }
                    $maxCount = $maxCount ?: 1;
                    
                    foreach (array_reverse($monthlyStats) as $stat):
                        $height = ($stat['count'] / $maxCount) * 180;
                    ?>
                        <div class="bar-container">
                            <div class="bar" style="height: <?= $height ?>px;"></div>
                            <div class="bar-label"><?= $stat['month'] ?></div>
                            <div class="bar-label" style="font-weight: 600;"><?= $stat['count'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- توزيع المستخدمين -->
            <div class="info-card" style="margin-top: 1.5rem;">
                <h3 class="card-title"><i class="fas fa-chart-pie"></i> توزيع المستخدمين</h3>
                
                <div class="role-distribution">
                    <?php foreach ($roleStats as $role): ?>
                        <div class="role-item">
                            <div>
                                <span class="role-color" style="background: <?= $role['color'] ?>;"></span>
                                <strong><?= $role['role'] ?></strong>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: <?= $role['color'] ?>; margin-top: 0.5rem;">
                                <?= $role['count'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لتفاصيل المستخدم
         ============================================================ -->
    <div class="side-modal" id="userDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-user"></i> تفاصيل المستخدم</h3>
            <button class="close-side-modal" onclick="closeSideModal('userDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="userDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لتفاصيل النشاط
         ============================================================ -->
    <div class="side-modal" id="activityDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-history"></i> تفاصيل النشاط</h3>
            <button class="close-side-modal" onclick="closeSideModal('activityDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="activityDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // تحويل بيانات PHP إلى JavaScript
    const recentUsers = <?= json_encode($recentUsers) ?>;
    const recentActivities = <?= json_encode($recentActivities) ?>;
    
    // ============================================================
    // عرض تفاصيل المستخدم
    // ============================================================
    function viewUserDetails(id) {
        event?.stopPropagation();
        
        // البحث عن المستخدم في المصفوفة
        const user = recentUsers.find(u => u.id == id);
        
        if (user) {
            openSideModal('userDetailsModal');
            
            let roleText = getRoleText(user.role);
            let verifiedText = user.is_verified ? 'موثق' : 'غير موثق';
            let verifiedClass = user.is_verified ? 'badge-success' : 'badge-warning';
            
            document.getElementById('userDetailsContent').innerHTML = `
                <div style="text-align: center;">
                    <div class="recent-avatar" style="width: 80px; height: 80px; margin: 0 auto;">
                        ${user.profile_image ? `<img src="../${user.profile_image}">` : `<i class="fas fa-${user.role == 'doctor' ? 'user-md' : (user.role == 'pharmacist' ? 'store' : (user.role == 'admin' ? 'crown' : 'user'))}"></i>`}
                    </div>
                    <h3 style="margin-top: 1rem;">${user.full_name}</h3>
                    <p style="color: var(--gray);">${user.user_code}</p>
                    <div style="margin: 0.5rem 0;">
                        <span class="badge badge-primary">${roleText}</span>
                        <span class="badge ${verifiedClass}">${verifiedText}</span>
                    </div>
                </div>
                
                <hr>
                
                <div style="margin: 1.5rem 0;">
                    <div class="info-row">
                        <span class="info-label">البريد:</span>
                        <span class="info-value">${user.email}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span class="info-value">${user.phone || '-'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">المدينة:</span>
                        <span class="info-value">${user.city || '-'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تاريخ التسجيل:</span>
                        <span class="info-value">${user.created_at}</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button class="btn btn-primary" onclick="window.location.href='mailto:${user.email}'" style="flex: 1;">
                        <i class="fas fa-envelope"></i> مراسلة
                    </button>
                    <button class="btn btn-outline" onclick="window.location.href='users.php?edit=${user.id}'" style="flex: 1;">
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                </div>
            `;
        } else {
            showToast('لم يتم العثور على المستخدم', 'error');
        }
    }
    
    // ============================================================
    // عرض تفاصيل النشاط
    // ============================================================
    function viewActivityDetails(id) {
        event?.stopPropagation();
        
        // البحث عن النشاط في المصفوفة
        const activity = recentActivities.find(a => a.id == id);
        
        if (activity) {
            openSideModal('activityDetailsModal');
            
            let deviceInfo = activity.device_info ? JSON.parse(activity.device_info) : null;
            
            document.getElementById('activityDetailsContent').innerHTML = `
                <div style="text-align: center;">
                    <h3>تفاصيل النشاط</h3>
                    <p style="color: var(--gray);">${activity.created_at}</p>
                </div>
                
                <hr>
                
                <div style="margin: 1.5rem 0;">
                    <div class="info-row">
                        <span class="info-label">المستخدم:</span>
                        <span class="info-value">${activity.full_name || 'نظام'} (${activity.user_code || ''})</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الإجراء:</span>
                        <span class="info-value"><span class="badge badge-primary">${activity.action}</span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الوصف:</span>
                        <span class="info-value">${activity.description || '-'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">عنوان IP:</span>
                        <span class="info-value">${activity.ip_address || '-'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">المتصفح:</span>
                        <span class="info-value">${deviceInfo?.browser || '-'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">نظام التشغيل:</span>
                        <span class="info-value">${deviceInfo?.platform || '-'}</span>
                    </div>
                </div>
            `;
        } else {
            showToast('لم يتم العثور على النشاط', 'error');
        }
    }
    
    // ============================================================
    // دالة مساعدة لترجمة الأدوار
    // ============================================================
    function getRoleText(role) {
        const roles = {
            'patient': 'مريض',
            'doctor': 'طبيب',
            'pharmacist': 'صيدلي',
            'admin': 'أدمن'
        };
        return roles[role] || role;
    }
    </script>
</body>
</html>