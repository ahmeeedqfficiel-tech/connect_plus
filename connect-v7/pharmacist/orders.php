<?php
/**
 * pharmacist/orders.php - إدارة الطلبات
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم صيدلي
requirePharmacist($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// جلب معرف الصيدلية
$pharmacy = $pdo->prepare("SELECT id FROM pharmacies WHERE pharmacist_id = ?")->execute([$user['id']]) ? 
            $pdo->query("SELECT id FROM pharmacies WHERE pharmacist_id = {$user['id']}")->fetch() : null;
$pharmacy_id = $pharmacy ? $pharmacy['id'] : 0;

if (!$pharmacy_id) {
    setToast('لم يتم العثور على الصيدلية', 'error');
    redirect('dashboard.php');
}

// ============================================================
// تحديث حالة الطلب
// ============================================================
if (isset($_GET['update'])) {
    $order_id = (int)$_GET['update'];
    $status = $_GET['status'];
    
    $stmt = $pdo->prepare("UPDATE medicine_orders SET status = ? WHERE id = ? AND pharmacy_id = ?");
    $stmt->execute([$status, $order_id, $pharmacy_id]);
    
    // إشعار للمريض
    $stmt2 = $pdo->prepare("SELECT patient_id FROM medicine_orders WHERE id = ?");
    $stmt2->execute([$order_id]);
    $patient_id = $stmt2->fetchColumn();
    
    $statusText = getStatusText($status);
    createNotification($pdo, $patient_id, 'order', 'تحديث حالة الطلب', "تم تحديث حالة طلبك إلى: $statusText");
    
    setToast('تم تحديث حالة الطلب', 'success');
    redirect('orders.php?status=' . ($_GET['status_filter'] ?? 'all'));
}

// ============================================================
// جلب الطلبات
// ============================================================
$status_filter = $_GET['status'] ?? 'all';

$sql = "SELECT mo.*, u.full_name as patient_name, u.phone, u.profile_image,
               (SELECT COUNT(*) FROM order_items WHERE order_id = mo.id) as items_count,
               (SELECT GROUP_CONCAT(m.name) FROM order_items oi JOIN medicines m ON oi.medicine_id = m.id WHERE oi.order_id = mo.id) as items_list
        FROM medicine_orders mo 
        JOIN users u ON mo.patient_id = u.id 
        WHERE mo.pharmacy_id = ?";

$params = [$pharmacy_id];

if ($status_filter !== 'all') {
    $sql .= " AND mo.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY mo.order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ============================================================
// إحصائيات
// ============================================================
$stats = [
    'all' => $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id AND status = 'pending'")->fetchColumn(),
    'confirmed' => $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id AND status = 'confirmed'")->fetchColumn(),
    'ready' => $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id AND status = 'ready'")->fetchColumn(),
    'delivered' => $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id AND status = 'delivered'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id AND status = 'cancelled'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطلبات - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
            text-decoration: none;
            color: var(--dark);
        }
        
        body.dark-mode .stat-card {
            background: #1E1E1E;
            color: white;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .stat-card.active {
            border-color: var(--primary);
            background: var(--primary-soft);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .status-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        body.dark-mode .status-tabs {
            background: #1E1E1E;
        }
        
        .status-tab {
            padding: 0.6rem 1.2rem;
            background: var(--light-gray);
            border-radius: 30px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            transition: var(--transition);
            flex: 1;
            text-align: center;
            min-width: 100px;
        }
        
        body.dark-mode .status-tab {
            background: #333;
            color: white;
        }
        
        .status-tab:hover {
            background: var(--primary-soft);
        }
        
        .status-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .order-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border-right: 4px solid transparent;
            cursor: pointer;
        }
        
        body.dark-mode .order-card {
            background: #1E1E1E;
        }
        
        .order-card:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .order-card.pending {
            border-right-color: var(--warning);
        }
        
        .order-card.confirmed {
            border-right-color: var(--info);
        }
        
        .order-card.ready {
            border-right-color: var(--success);
        }
        
        .order-card.delivered {
            border-right-color: var(--success);
        }
        
        .order-card.cancelled {
            border-right-color: var(--danger);
            opacity: 0.7;
        }
        
        .order-header {
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
        
        .order-meta {
            display: flex;
            gap: 2rem;
            margin: 1rem 0;
            color: var(--gray);
            flex-wrap: wrap;
        }
        
        .items-preview {
            margin: 1rem 0;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
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
        
        .search-section {
            margin-bottom: 1.5rem;
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
                <i class="fas fa-shopping-cart"></i> إدارة الطلبات
            </h1>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid">
                <a href="orders.php" class="stat-card <?= $status_filter == 'all' ? 'active' : '' ?>">
                    <div class="stat-number"><?= $stats['all'] ?></div>
                    <div>الكل</div>
                </a>
                <a href="orders.php?status=pending" class="stat-card <?= $status_filter == 'pending' ? 'active' : '' ?>">
                    <div class="stat-number"><?= $stats['pending'] ?></div>
                    <div>قيد الانتظار</div>
                </a>
                <a href="orders.php?status=confirmed" class="stat-card <?= $status_filter == 'confirmed' ? 'active' : '' ?>">
                    <div class="stat-number"><?= $stats['confirmed'] ?></div>
                    <div>مؤكد</div>
                </a>
                <a href="orders.php?status=ready" class="stat-card <?= $status_filter == 'ready' ? 'active' : '' ?>">
                    <div class="stat-number"><?= $stats['ready'] ?></div>
                    <div>جاهز</div>
                </a>
                <a href="orders.php?status=delivered" class="stat-card <?= $status_filter == 'delivered' ? 'active' : '' ?>">
                    <div class="stat-number"><?= $stats['delivered'] ?></div>
                    <div>تم التسليم</div>
                </a>
                <a href="orders.php?status=cancelled" class="stat-card <?= $status_filter == 'cancelled' ? 'active' : '' ?>">
                    <div class="stat-number"><?= $stats['cancelled'] ?></div>
                    <div>ملغي</div>
                </a>
            </div>
            
            <!-- شريط البحث -->
            <div class="search-section">
                <input type="text" id="searchInput" class="form-control" placeholder="ابحث باسم المريض...">
            </div>
            
            <!-- قائمة الطلبات -->
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>لا توجد طلبات</h3>
                    <p style="color: var(--gray); margin-top: 0.5rem;">
                        <?php if ($status_filter != 'all'): ?>
                            لا توجد طلبات بهذه الحالة
                        <?php else: ?>
                            لم يتم استلام أي طلبات بعد
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <div class="order-card <?= $o['status'] ?>" onclick="showOrderDetails(<?= $o['id'] ?>)">
                        <div class="order-header">
                            <div class="patient-info">
                                <div class="patient-avatar-small">
                                    <?php if (!empty($o['profile_image'])): ?>
                                        <img src="../<?= $o['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="patient-name"><?= htmlspecialchars($o['patient_name']) ?></div>
                                    <div style="font-size: 0.9rem; color: var(--gray);"><?= $o['phone'] ?></div>
                                </div>
                            </div>
                            
                            <span class="badge badge-<?= $o['status'] ?>">
                                <?= getStatusText($o['status']) ?>
                            </span>
                        </div>
                        
                        <div class="order-meta">
                            <span><i class="fas fa-calendar"></i> <?= date('Y-m-d H:i', strtotime($o['order_date'])) ?></span>
                            <span><i class="fas fa-box"></i> <?= $o['items_count'] ?> منتج</span>
                        </div>
                        
                        <div class="items-preview">
                            <strong>الأدوية:</strong> <?= htmlspecialchars(substr($o['items_list'] ?? '', 0, 100)) ?><?= strlen($o['items_list'] ?? '') > 100 ? '...' : '' ?>
                        </div>
                        
                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary);">
                            <?= number_format($o['total_price'] ?: 0, 2) ?> دج
                        </div>
                        
                        <!-- أزرار الإجراءات -->
                        <div class="action-buttons" onclick="event.stopPropagation()">
                            <?php if ($o['status'] == 'pending'): ?>
                                <a href="?update=<?= $o['id'] ?>&status=confirmed&status_filter=<?= $status_filter ?>" class="btn btn-success btn-sm" onclick="return confirm('تأكيد هذا الطلب؟')">
                                    <i class="fas fa-check"></i> تأكيد
                                </a>
                                <a href="?update=<?= $o['id'] ?>&status=cancelled&status_filter=<?= $status_filter ?>" class="btn btn-danger btn-sm" onclick="return confirm('إلغاء هذا الطلب؟')">
                                    <i class="fas fa-times"></i> إلغاء
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($o['status'] == 'confirmed'): ?>
                                <a href="?update=<?= $o['id'] ?>&status=ready&status_filter=<?= $status_filter ?>" class="btn btn-primary btn-sm" onclick="return confirm('تجهيز الطلب للاستلام؟')">
                                    <i class="fas fa-box"></i> تجهيز
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($o['status'] == 'ready'): ?>
                                <a href="?update=<?= $o['id'] ?>&status=delivered&status_filter=<?= $status_filter ?>" class="btn btn-success btn-sm" onclick="return confirm('تأكيد استلام المريض للطلب؟')">
                                    <i class="fas fa-check-circle"></i> تم التسليم
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة تفاصيل الطلب -->
    <div class="side-modal" id="orderDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-shopping-cart"></i> تفاصيل الطلب</h3>
            <button class="close-side-modal" onclick="closeSideModal('orderDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="orderDetailsContent"></div>
    </div>
    
    <script>
    function showOrderDetails(id) {
        fetch(`../api/pharmacist/get_order_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const o = data.data;
                    
                    let itemsHtml = '';
                    if (o.items && o.items.length > 0) {
                        itemsHtml = '<h4 style="margin: 1rem 0;">الأدوية</h4>';
                        o.items.forEach(item => {
                            itemsHtml += `
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--light-gray);">
                                    <span>${item.medicine_name}</span>
                                    <span>${item.quantity} × ${item.price} دج</span>
                                </div>
                            `;
                        });
                    }
                    
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <h3>طلب من ${o.patient_name}</h3>
                        <p style="color: var(--gray);">${o.phone}</p>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            <p><i class="fas fa-calendar"></i> <strong>التاريخ:</strong> ${o.order_date}</p>
                            <p><i class="fas fa-tag"></i> <strong>الحالة:</strong> <span class="badge badge-${o.status}">${getStatusText(o.status)}</span></p>
                        </div>
                        
                        ${itemsHtml}
                        
                        <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; margin-top: 1rem;">
                            <span>المجموع الكلي</span>
                            <span style="color: var(--primary);">${o.total_price} دج</span>
                        </div>
                        
                        ${o.notes ? `<p style="margin-top: 1rem;"><strong>ملاحظات:</strong> ${o.notes}</p>` : ''}
                        ${o.prescription_image ? `<p style="margin-top: 1rem;"><a href="../${o.prescription_image}" target="_blank" class="btn btn-outline btn-sm">عرض الوصفة</a></p>` : ''}
                    `;
                    
                    openSideModal('orderDetailsModal');
                }
            });
    }
    
    function getStatusText(status) {
        const statuses = {
            'pending': 'قيد الانتظار',
            'confirmed': 'مؤكد',
            'ready': 'جاهز',
            'delivered': 'تم التسليم',
            'cancelled': 'ملغي'
        };
        return statuses[status] || status;
    }
    
    // بحث فوري
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const search = this.value.toLowerCase();
        const cards = document.querySelectorAll('.order-card');
        
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (text.includes(search)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>