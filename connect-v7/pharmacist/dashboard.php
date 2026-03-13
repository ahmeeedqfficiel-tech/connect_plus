<?php
/**
 * pharmacist/dashboard.php - الصفحة الرئيسية للصيدلي
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم صيدلي
requirePharmacist($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);
$pharmacist = getPharmacistData($pdo, $user['id']);

// جلب معرف الصيدلية
$pharmacy = $pdo->prepare("SELECT id FROM pharmacies WHERE pharmacist_id = ?")->execute([$user['id']]) ? 
            $pdo->query("SELECT id FROM pharmacies WHERE pharmacist_id = {$user['id']}")->fetch() : null;
$pharmacy_id = $pharmacy ? $pharmacy['id'] : 0;

// ============================================================
// إحصائيات سريعة
// ============================================================

// عدد الأدوية في المخزون
$medicinesCount = $pharmacy_id ? $pdo->query("SELECT COUNT(*) FROM pharmacy_medicines WHERE pharmacy_id = $pharmacy_id")->fetchColumn() : 0;

// عدد الطلبات المعلقة
$pendingOrders = $pharmacy_id ? $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id AND status = 'pending'")->fetchColumn() : 0;

// عدد الطلبات الجاهزة
$readyOrders = $pharmacy_id ? $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id AND status = 'ready'")->fetchColumn() : 0;

// عدد الأدوية منخفضة المخزون
$lowStock = $pharmacy_id ? $pdo->query("SELECT COUNT(*) FROM pharmacy_medicines WHERE pharmacy_id = $pharmacy_id AND quantity < 10")->fetchColumn() : 0;

// ============================================================
// آخر الطلبات
// ============================================================
$recentOrders = $pharmacy_id ? $pdo->query("
    SELECT mo.*, u.full_name as patient_name, u.phone,
           (SELECT COUNT(*) FROM order_items WHERE order_id = mo.id) as items_count
    FROM medicine_orders mo 
    JOIN users u ON mo.patient_id = u.id 
    WHERE mo.pharmacy_id = $pharmacy_id 
    ORDER BY mo.order_date DESC 
    LIMIT 5
")->fetchAll() : [];

// ============================================================
// الأدوية الأكثر طلباً
// ============================================================
$topMedicines = $pharmacy_id ? $pdo->query("
    SELECT m.name, COUNT(*) as order_count, m.default_price
    FROM order_items oi
    JOIN medicines m ON oi.medicine_id = m.id
    JOIN medicine_orders mo ON oi.order_id = mo.id
    WHERE mo.pharmacy_id = $pharmacy_id
    GROUP BY m.id
    ORDER BY order_count DESC
    LIMIT 5
")->fetchAll() : [];

// ============================================================
// آخر المرضى
// ============================================================
$recentPatients = $pharmacy_id ? $pdo->query("
    SELECT DISTINCT u.id, u.full_name, u.profile_image,
           MAX(mo.order_date) as last_order,
           COUNT(*) as orders_count
    FROM medicine_orders mo
    JOIN users u ON mo.patient_id = u.id
    WHERE mo.pharmacy_id = $pharmacy_id
    GROUP BY u.id
    ORDER BY last_order DESC
    LIMIT 5
")->fetchAll() : [];
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
        
        .low-stock-alert {
            background: rgba(244, 162, 97, 0.1);
            border-right: 4px solid var(--warning);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .order-item, .medicine-item, .patient-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .order-item:hover, .medicine-item:hover, .patient-item:hover {
            background: var(--primary-soft);
            border-radius: var(--radius-md);
        }
        
        .order-item:last-child, .medicine-item:last-child, .patient-item:last-child {
            border-bottom: none;
        }
        
        .patient-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            overflow: hidden;
            flex-shrink: 0;
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
        
        .status-badge {
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(244, 162, 97, 0.1);
            color: var(--warning);
        }
        
        .status-confirmed {
            background: rgba(42, 157, 143, 0.1);
            color: var(--primary);
        }
        
        .status-ready {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .status-delivered {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .status-cancelled {
            background: rgba(231, 111, 81, 0.1);
            color: var(--danger);
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
                    <p><?= htmlspecialchars($pharmacist['pharmacy_name']) ?></p>
                </div>
                
                <div class="quick-actions">
                    <a href="inventory.php" class="quick-action-btn">
                        <i class="fas fa-pills"></i> المخزون
                    </a>
                    <a href="orders.php?status=pending" class="quick-action-btn">
                        <i class="fas fa-shopping-cart"></i> الطلبات
                    </a>
                    <span class="quick-action-btn">
                        <i class="fas fa-calendar"></i> <?= date('Y-m-d') ?>
                    </span>
                </div>
            </div>
            
            <!-- تنبيه المخزون المنخفض -->
            <?php if ($lowStock > 0): ?>
                <div class="low-stock-alert">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; color: var(--warning);"></i>
                    <div style="flex: 1;">
                        <strong>تنبيه: <?= $lowStock ?> أدوية منخفضة المخزون</strong>
                        <p style="font-size: 0.9rem; color: var(--gray);">الكمية أقل من 10 وحدات</p>
                    </div>
                    <a href="inventory.php?filter=low" class="btn btn-warning btn-sm">
                        <i class="fas fa-eye"></i> عرض
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='inventory.php'">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                    <div class="stat-number"><?= $medicinesCount ?></div>
                    <div style="color: var(--gray);">إجمالي الأدوية</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='orders.php?status=pending'">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?= $pendingOrders ?></div>
                    <div style="color: var(--gray);">طلبات معلقة</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='orders.php?status=ready'">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?= $readyOrders ?></div>
                    <div style="color: var(--gray);">طلبات جاهزة</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='inventory.php?filter=low'">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-number"><?= $lowStock ?></div>
                    <div style="color: var(--gray);">مخزون منخفض</div>
                </div>
            </div>
            
            <!-- الشبكة الرئيسية -->
            <div class="dashboard-grid">
                <!-- آخر الطلبات -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-shopping-cart"></i> آخر الطلبات</h3>
                        <a href="orders.php" class="card-action">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <?php if (empty($recentOrders)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-shopping-cart" style="font-size: 3rem; color: var(--gray);"></i>
                            <p style="margin-top: 1rem;">لا توجد طلبات</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): ?>
                            <div class="order-item" onclick="showOrderDetails(<?= $o['id'] ?>)">
                                <div class="patient-avatar-small">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($o['patient_name']) ?></div>
                                    <div class="item-meta">
                                        <?= date('Y-m-d H:i', strtotime($o['order_date'])) ?> • <?= $o['items_count'] ?> منتج
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge status-<?= $o['status'] ?>">
                                        <?= getStatusText($o['status']) ?>
                                    </span>
                                    <div style="font-weight: 600; margin-top: 0.3rem;">
                                        <?= number_format($o['total_price'] ?: 0, 2) ?> دج
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- الأدوية الأكثر طلباً -->
                <div class="info-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-bar"></i> الأكثر طلباً</h3>
                    </div>
                    
                    <?php if (empty($topMedicines)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-chart-bar" style="font-size: 3rem; color: var(--gray);"></i>
                            <p style="margin-top: 1rem;">لا توجد بيانات كافية</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topMedicines as $m): ?>
                            <div class="medicine-item">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($m['name']) ?></div>
                                    <div class="item-meta"><?= number_format($m['default_price'] ?: 0, 2) ?> دج</div>
                                </div>
                                <span class="badge badge-primary"><?= $m['order_count'] ?> طلب</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- آخر المرضى -->
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users"></i> آخر المرضى</h3>
                </div>
                
                <?php if (empty($recentPatients)): ?>
                    <p class="text-center" style="padding: 2rem;">لا يوجد مرضى</p>
                <?php else: ?>
                    <?php foreach ($recentPatients as $p): ?>
                        <div class="patient-item" onclick="window.location.href='patient_view.php?id=<?= $p['id'] ?>'">
                            <div class="patient-avatar-small">
                                <?php if (!empty($p['profile_image'])): ?>
                                    <img src="../<?= $p['profile_image'] ?>" alt="صورة">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-info">
                                <div class="item-name"><?= htmlspecialchars($p['full_name']) ?></div>
                                <div class="item-meta">
                                    آخر طلب: <?= date('Y-m-d', strtotime($p['last_order'])) ?> • <?= $p['orders_count'] ?> طلب
                                </div>
                            </div>
                            <i class="fas fa-chevron-left" style="color: var(--gray);"></i>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- معلومات الصيدلية -->
            <div class="info-card">
                <h3 class="card-title"><i class="fas fa-info-circle"></i> معلومات الصيدلية</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">اسم الصيدلية</div>
                        <div><?= htmlspecialchars($pharmacist['pharmacy_name']) ?></div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">رقم الترخيص</div>
                        <div><?= $pharmacist['license_number'] ?: 'غير محدد' ?></div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">العنوان</div>
                        <div><?= htmlspecialchars($pharmacist['address'] ?: 'غير محدد') ?></div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">المدينة</div>
                        <div><?= htmlspecialchars($pharmacist['city'] ?: 'غير محدد') ?></div>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--gray);">24 ساعة</div>
                        <div><?= $pharmacist['is_24h'] ? 'نعم' : 'لا' ?></div>
                    </div>
                </div>
            </div>
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
    
    <script src="../assets/js/main.js"></script>
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
                        
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            ${o.status === 'pending' ? `
                                <button class="btn btn-success btn-sm" onclick="updateOrderStatus(${o.id}, 'confirmed')" style="flex: 1;">
                                    <i class="fas fa-check"></i> تأكيد
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="updateOrderStatus(${o.id}, 'cancelled')" style="flex: 1;">
                                    <i class="fas fa-times"></i> إلغاء
                                </button>
                            ` : ''}
                            
                            ${o.status === 'confirmed' ? `
                                <button class="btn btn-primary btn-sm" onclick="updateOrderStatus(${o.id}, 'ready')" style="flex: 1;">
                                    <i class="fas fa-box"></i> تجهيز
                                </button>
                            ` : ''}
                            
                            ${o.status === 'ready' ? `
                                <button class="btn btn-success btn-sm" onclick="updateOrderStatus(${o.id}, 'delivered')" style="flex: 1;">
                                    <i class="fas fa-check-circle"></i> تم التسليم
                                </button>
                            ` : ''}
                        </div>
                    `;
                    
                    openSideModal('orderDetailsModal');
                }
            });
    }
    
    function updateOrderStatus(id, status) {
        if (!confirm('تأكيد تحديث حالة الطلب؟')) return;
        
        fetch(`../api/pharmacist/update_order_status.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id, status: status})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم تحديث الحالة', 'success');
                closeSideModal('orderDetailsModal');
                setTimeout(() => location.reload(), 1500);
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
    </script>
</body>
</html>