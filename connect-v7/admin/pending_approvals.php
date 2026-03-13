<?php
/**
 * admin/pending_approvals.php - طلبات التأكيد مع نوافذ جانبية
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

$admin = getCurrentUser($pdo);

// ============================================================
// قبول مستخدم
// ============================================================
if (isset($_GET['approve'])) {
    $user_id = (int)$_GET['approve'];
    
    try {
        $pdo->beginTransaction();
        
        // تحديث حالة المستخدم
        $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user_id]);
        
        // تحديث طلب التأكيد
        $pdo->prepare("UPDATE approval_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE user_id = ? AND status = 'pending'")
            ->execute([$admin['id'], $user_id]);
        
        // إشعار للمستخدم
        createNotification($pdo, $user_id, 'approval', 'تم تأكيد حسابك', 'يمكنك الآن تسجيل الدخول إلى المنصة');
        
        $pdo->commit();
        
        setToast('تم قبول المستخدم', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('حدث خطأ: ' . $e->getMessage(), 'error');
    }
    
    redirect('pending_approvals.php');
}

// ============================================================
// رفض مستخدم
// ============================================================
if (isset($_GET['reject'])) {
    $user_id = (int)$_GET['reject'];
    $reason = $_GET['reason'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // تحديث طلب التأكيد
        $pdo->prepare("UPDATE approval_requests SET status = 'rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE user_id = ? AND status = 'pending'")
            ->execute([$reason, $admin['id'], $user_id]);
        
        // إشعار للمستخدم
        createNotification($pdo, $user_id, 'approval', 'لم يتم تأكيد حسابك', $reason ?: 'تم رفض طلبك');
        
        $pdo->commit();
        
        setToast('تم رفض المستخدم', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('حدث خطأ: ' . $e->getMessage(), 'error');
    }
    
    redirect('pending_approvals.php');
}

// ============================================================
// جلب طلبات التأكيد
// ============================================================
$requests = $pdo->query("
    SELECT ar.*, u.user_code, u.full_name, u.email, u.role, u.phone, u.city,
           u.id_card_image, u.birth_certificate, u.postal_receipt, u.profile_image,
           u.license_image, u.degree_certificate, u.pharmacy_license,
           d.specialties, d.degree, d.license_number,
           ph.pharmacy_name, ph.city as pharmacy_city
    FROM approval_requests ar
    JOIN users u ON ar.user_id = u.id
    LEFT JOIN doctors d ON u.id = d.user_id
    LEFT JOIN pharmacists ph ON u.id = ph.user_id
    WHERE ar.status = 'pending'
    ORDER BY ar.created_at
")->fetchAll();

// ============================================================
// أرشيف الطلبات
// ============================================================
$archive = $pdo->query("
    SELECT ar.*, u.user_code, u.full_name, u.role, a.full_name as reviewer_name
    FROM approval_requests ar
    JOIN users u ON ar.user_id = u.id
    LEFT JOIN users a ON ar.reviewed_by = a.id
    WHERE ar.status != 'pending'
    ORDER BY ar.reviewed_at DESC
    LIMIT 30
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلبات التأكيد - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        body.dark-mode .tabs {
            background: #1E1E1E;
        }
        
        .tab {
            flex: 1;
            padding: 0.8rem;
            background: var(--light-gray);
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
        }
        
        body.dark-mode .tab {
            background: #333;
            color: white;
        }
        
        .tab:hover {
            background: var(--primary-soft);
        }
        
        .tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .request-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-right: 5px solid var(--warning);
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: var(--transition);
        }
        
        body.dark-mode .request-card {
            background: #1E1E1E;
        }
        
        .request-card:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar-small {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            overflow: hidden;
        }
        
        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details h3 {
            margin-bottom: 0.3rem;
        }
        
        .user-details p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .docs-grid {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 1rem 0;
        }
        
        .doc-link {
            padding: 0.5rem 1rem;
            background: var(--light-gray);
            border-radius: 30px;
            text-decoration: none;
            color: var(--dark);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .doc-link:hover {
            background: var(--primary);
            color: white;
        }
        
        .reject-form {
            display: none;
            margin-top: 1rem;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .archive-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .archive-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .archive-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .extra-info {
            background: var(--light);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin: 1rem 0;
        }
        
        /* تنسيق النافذة الجانبية */
        .info-row {
            display: flex;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .info-label {
            width: 140px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .document-section {
            background: var(--light);
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
            
            <h1 class="page-title" style="margin-bottom: 2rem;">
                <i class="fas fa-clock"></i> طلبات التأكيد
            </h1>
            
            <!-- تبويبات -->
            <div class="tabs">
                <a href="#pending" class="tab active" onclick="showTab('pending')">طلبات جديدة (<?= count($requests) ?>)</a>
                <a href="#archive" class="tab" onclick="showTab('archive')">الأرشيف</a>
            </div>
            
            <!-- الطلبات المعلقة -->
            <div id="pendingTab" class="tab-content active">
                <?php if (empty($requests)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success);"></i>
                        <h3 style="margin: 1rem 0;">لا توجد طلبات تأكيد جديدة</h3>
                        <p style="color: var(--gray);">كل المستخدمين الجدد تمت معالجتهم</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <div class="request-card" onclick="viewRequestDetails(<?= $r['user_id'] ?>)">
                            <div class="request-header">
                                <div class="user-info">
                                    <div class="user-avatar-small">
                                        <?php if (!empty($r['profile_image'])): ?>
                                            <img src="../<?= $r['profile_image'] ?>" alt="صورة">
                                        <?php else: ?>
                                            <i class="fas fa-<?= $r['role'] == 'doctor' ? 'user-md' : ($r['role'] == 'pharmacist' ? 'store' : 'user') ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-details">
                                        <h3><?= htmlspecialchars($r['full_name']) ?></h3>
                                        <p><?= $r['user_code'] ?> • <?= getRoleText($r['role']) ?> • <?= $r['email'] ?></p>
                                    </div>
                                </div>
                                <span class="badge badge-warning">جديد</span>
                            </div>
                            
                            <!-- معلومات إضافية حسب الدور -->
                            <?php if ($r['role'] == 'doctor' && $r['specialties']): ?>
                                <div class="extra-info">
                                    <i class="fas fa-stethoscope"></i> <strong>التخصص:</strong> <?= htmlspecialchars($r['specialties']) ?>
                                    <?php if ($r['degree']): ?> • <?= htmlspecialchars($r['degree']) ?><?php endif; ?>
                                </div>
                            <?php elseif ($r['role'] == 'pharmacist' && $r['pharmacy_name']): ?>
                                <div class="extra-info">
                                    <i class="fas fa-store"></i> <strong>الصيدلية:</strong> <?= htmlspecialchars($r['pharmacy_name']) ?>
                                    <?php if ($r['pharmacy_city']): ?> • <?= htmlspecialchars($r['pharmacy_city']) ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 1rem; margin-top: 1rem;" onclick="event.stopPropagation()">
                                <a href="?approve=<?= $r['user_id'] ?>" class="btn btn-success btn-sm" style="flex: 1;" onclick="return confirm('قبول هذا المستخدم؟')">
                                    <i class="fas fa-check"></i> قبول
                                </a>
                                <button class="btn btn-danger btn-sm" style="flex: 1;" onclick="showRejectForm(<?= $r['user_id'] ?>)">
                                    <i class="fas fa-times"></i> رفض
                                </button>
                                <button class="btn btn-outline btn-sm" style="flex: 1;" onclick="viewRequestDetails(<?= $r['user_id'] ?>)">
                                    <i class="fas fa-eye"></i> عرض
                                </button>
                            </div>
                            
                            <!-- نموذج الرفض -->
                            <div id="rejectForm_<?= $r['user_id'] ?>" class="reject-form">
                                <form method="get">
                                    <input type="hidden" name="reject" value="<?= $r['user_id'] ?>">
                                    <div class="form-group">
                                        <label>سبب الرفض (اختياري)</label>
                                        <input type="text" name="reason" class="form-control" placeholder="أدخل سبب الرفض">
                                    </div>
                                    <div style="display: flex; gap: 1rem;">
                                        <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                                        <button type="button" class="btn btn-outline" onclick="hideRejectForm(<?= $r['user_id'] ?>)">إلغاء</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- الأرشيف -->
            <div id="archiveTab" class="tab-content">
                <?php if (empty($archive)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <p>لا توجد طلبات سابقة</p>
                    </div>
                <?php else: ?>
                    <div class="info-card">
                        <div style="overflow-x: auto;">
                            <table class="archive-table">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>الدور</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الطلب</th>
                                        <th>تاريخ المراجعة</th>
                                        <th>المراجع</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archive as $a): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($a['full_name']) ?><br><small><?= $a['user_code'] ?></small></td>
                                            <td><?= getRoleText($a['role']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $a['status'] ?>">
                                                    <?= $a['status'] == 'approved' ? 'مقبول' : 'مرفوض' ?>
                                                </span>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($a['created_at'])) ?></td>
                                            <td><?= $a['reviewed_at'] ? date('Y-m-d', strtotime($a['reviewed_at'])) : '-' ?></td>
                                            <td><?= htmlspecialchars($a['reviewer_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($a['admin_notes'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لتفاصيل الطلب
         ============================================================ -->
    <div class="side-modal" id="requestDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-file-signature"></i> تفاصيل طلب التأكيد</h3>
            <button class="close-side-modal" onclick="closeSideModal('requestDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="requestDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function showTab(tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        if (tab === 'pending') {
            document.querySelector('.tab').classList.add('active');
            document.getElementById('pendingTab').classList.add('active');
        } else {
            document.querySelectorAll('.tab')[1].classList.add('active');
            document.getElementById('archiveTab').classList.add('active');
        }
    }
    
    function showRejectForm(userId) {
        document.getElementById('rejectForm_' + userId).style.display = 'block';
    }
    
    function hideRejectForm(userId) {
        document.getElementById('rejectForm_' + userId).style.display = 'none';
    }
    
    function viewRequestDetails(id) {
        openSideModal('requestDetailsModal');
        
        fetch(`../api/admin/get_user_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const u = data.user;
                    
                    let docsHtml = '';
                    let docsList = [];
                    
                    if (u.id_card_image) docsList.push(`<a href="../${u.id_card_image}" target="_blank" class="doc-link"><i class="fas fa-id-card"></i> بطاقة التعريف</a>`);
                    if (u.birth_certificate) docsList.push(`<a href="../${u.birth_certificate}" target="_blank" class="doc-link"><i class="fas fa-birthday-cake"></i> شهادة الميلاد</a>`);
                    if (u.postal_receipt) docsList.push(`<a href="../${u.postal_receipt}" target="_blank" class="doc-link"><i class="fas fa-receipt"></i> وصل بريدي</a>`);
                    if (u.license_image) docsList.push(`<a href="../${u.license_image}" target="_blank" class="doc-link"><i class="fas fa-file-contract"></i> رخصة مزاولة</a>`);
                    if (u.degree_certificate) docsList.push(`<a href="../${u.degree_certificate}" target="_blank" class="doc-link"><i class="fas fa-graduation-cap"></i> شهادة التخرج</a>`);
                    if (u.pharmacy_license) docsList.push(`<a href="../${u.pharmacy_license}" target="_blank" class="doc-link"><i class="fas fa-store"></i> رخصة الصيدلية</a>`);
                    
                    if (docsList.length > 0) {
                        docsHtml = '<div class="document-section"><h4 style="margin-bottom: 1rem;">المستندات المرفوعة</h4><div class="docs-grid">' + docsList.join('') + '</div></div>';
                    }
                    
                    let extraInfo = '';
                    if (u.role == 'doctor' && u.doctor) {
                        extraInfo = `
                            <div class="extra-info">
                                <h4 style="margin-bottom: 1rem;">معلومات مهنية</h4>
                                <div class="info-row"><span class="info-label">التخصص:</span><span class="info-value">${u.doctor.specialties || '-'}</span></div>
                                <div class="info-row"><span class="info-label">الدرجة:</span><span class="info-value">${u.doctor.degree || '-'}</span></div>
                                <div class="info-row"><span class="info-label">رقم الترخيص:</span><span class="info-value">${u.doctor.license_number || '-'}</span></div>
                                <div class="info-row"><span class="info-label">مكان العمل:</span><span class="info-value">${u.doctor.workplace_name || '-'}</span></div>
                            </div>
                        `;
                    } else if (u.role == 'pharmacist' && u.pharmacist) {
                        extraInfo = `
                            <div class="extra-info">
                                <h4 style="margin-bottom: 1rem;">معلومات الصيدلية</h4>
                                <div class="info-row"><span class="info-label">اسم الصيدلية:</span><span class="info-value">${u.pharmacist.pharmacy_name || '-'}</span></div>
                                <div class="info-row"><span class="info-label">رقم الترخيص:</span><span class="info-value">${u.pharmacist.license_number || '-'}</span></div>
                                <div class="info-row"><span class="info-label">العنوان:</span><span class="info-value">${u.pharmacist.address || '-'}</span></div>
                                <div class="info-row"><span class="info-label">المدينة:</span><span class="info-value">${u.pharmacist.city || '-'}</span></div>
                            </div>
                        `;
                    } else if (u.role == 'patient' && u.patient) {
                        extraInfo = `
                            <div class="extra-info">
                                <h4 style="margin-bottom: 1rem;">معلومات إضافية</h4>
                                <div class="info-row"><span class="info-label">فصيلة الدم:</span><span class="info-value">${u.patient.blood_type || '-'}</span></div>
                                <div class="info-row"><span class="info-label">جهة اتصال:</span><span class="info-value">${u.patient.emergency_name || '-'} (${u.patient.emergency_phone || '-'})</span></div>
                            </div>
                        `;
                    }
                    
                    document.getElementById('requestDetailsContent').innerHTML = `
                        <div style="text-align: center;">
                            <div class="user-avatar-small" style="width: 80px; height: 80px; margin: 0 auto;">
                                ${u.profile_image ? `<img src="../${u.profile_image}">` : `<i class="fas fa-${u.role == 'doctor' ? 'user-md' : (u.role == 'pharmacist' ? 'store' : 'user')}"></i>`}
                            </div>
                            <h3 style="margin-top: 1rem;">${u.full_name}</h3>
                            <p style="color: var(--gray);">${u.user_code}</p>
                        </div>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            <div class="info-row"><span class="info-label">البريد:</span><span class="info-value">${u.email}</span></div>
                            <div class="info-row"><span class="info-label">الهاتف:</span><span class="info-value">${u.phone || '-'}</span></div>
                            <div class="info-row"><span class="info-label">الدور:</span><span class="info-value">${getRoleText(u.role)}</span></div>
                            <div class="info-row"><span class="info-label">تاريخ التسجيل:</span><span class="info-value">${u.created_at}</span></div>
                        </div>
                        
                        ${extraInfo}
                        ${docsHtml}
                        
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <a href="?approve=${u.id}" class="btn btn-success" style="flex: 1;" onclick="return confirm('قبول هذا المستخدم؟')">
                                <i class="fas fa-check"></i> قبول
                            </a>
                            <button class="btn btn-danger" style="flex: 1;" onclick="openRejectForm(${u.id})">
                                <i class="fas fa-times"></i> رفض
                            </button>
                        </div>
                    `;
                }
            });
    }
    
    function openRejectForm(id) {
        closeSideModal('requestDetailsModal');
        showRejectForm(id);
    }
    
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