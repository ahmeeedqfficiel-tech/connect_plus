<?php
/**
 * pharmacist/inventory.php - إدارة المخزون
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
// تحديث الكمية
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $medicine_id = (int)$_POST['medicine_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    
    $stmt = $pdo->prepare("UPDATE pharmacy_medicines SET quantity = ?, price = ? WHERE pharmacy_id = ? AND medicine_id = ?");
    $stmt->execute([$quantity, $price, $pharmacy_id, $medicine_id]);
    
    setToast('تم تحديث المخزون', 'success');
    redirect('inventory.php');
}

// ============================================================
// إضافة دواء جديد للمخزون
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $medicine_id = (int)$_POST['medicine_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    
    // التحقق من وجود الدواء في المخزون
    $check = $pdo->prepare("SELECT id FROM pharmacy_medicines WHERE pharmacy_id = ? AND medicine_id = ?");
    $check->execute([$pharmacy_id, $medicine_id]);
    $existing = $check->fetch();
    
    if ($existing) {
        // تحديث الكمية
        $pdo->prepare("UPDATE pharmacy_medicines SET quantity = quantity + ?, price = ? WHERE pharmacy_id = ? AND medicine_id = ?")
            ->execute([$quantity, $price, $pharmacy_id, $medicine_id]);
    } else {
        // إضافة جديدة
        $pdo->prepare("INSERT INTO pharmacy_medicines (pharmacy_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)")
            ->execute([$pharmacy_id, $medicine_id, $quantity, $price]);
    }
    
    setToast('تم إضافة الدواء إلى المخزون', 'success');
    redirect('inventory.php');
}

// ============================================================
// حذف دواء من المخزون
// ============================================================
if (isset($_GET['delete'])) {
    $medicine_id = (int)$_GET['delete'];
    
    $pdo->prepare("DELETE FROM pharmacy_medicines WHERE pharmacy_id = ? AND medicine_id = ?")
        ->execute([$pharmacy_id, $medicine_id]);
    
    setToast('تم حذف الدواء من المخزون', 'success');
    redirect('inventory.php');
}

// ============================================================
// جلب المخزون
// ============================================================
$inventory = $pdo->query("
    SELECT pm.*, m.name, m.scientific_name, m.category, m.requires_prescription
    FROM pharmacy_medicines pm
    JOIN medicines m ON pm.medicine_id = m.id
    WHERE pm.pharmacy_id = $pharmacy_id
    ORDER BY 
        CASE 
            WHEN pm.quantity = 0 THEN 1
            WHEN pm.quantity < 10 THEN 2
            ELSE 3
        END,
        m.name
")->fetchAll();

// ============================================================
// جلب جميع الأدوية للإضافة
// ============================================================
$allMedicines = $pdo->query("SELECT id, name FROM medicines ORDER BY name")->fetchAll();

// ============================================================
// الفلتر
// ============================================================
$filter = $_GET['filter'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المخزون - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .filter-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            border: 1px solid var(--light-gray);
            background: white;
            cursor: pointer;
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
        }
        
        body.dark-mode .filter-btn {
            background: #1E1E1E;
            color: white;
        }
        
        .filter-btn:hover {
            background: var(--primary-soft);
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .inventory-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .inventory-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .inventory-table tr.low-stock {
            background: rgba(244, 162, 97, 0.1);
        }
        
        .inventory-table tr.out-stock {
            background: rgba(231, 111, 81, 0.1);
        }
        
        .quantity-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-weight: 600;
        }
        
        .quantity-normal {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .quantity-low {
            background: rgba(244, 162, 97, 0.1);
            color: var(--warning);
        }
        
        .quantity-out {
            background: rgba(231, 111, 81, 0.1);
            color: var(--danger);
        }
        
        .action-btn {
            padding: 0.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            background: var(--primary);
            color: white;
            margin: 0 0.2rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .action-btn.delete {
            background: var(--danger);
        }
        
        .search-box {
            margin-bottom: 1rem;
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
                <h1 class="page-title"><i class="fas fa-pills"></i> إدارة المخزون</h1>
                <button class="btn btn-primary" onclick="openSideModal('addModal')">
                    <i class="fas fa-plus"></i> إضافة دواء
                </button>
            </div>
            
            <!-- شريط البحث -->
            <div class="search-box">
                <input type="text" id="searchInput" class="form-control" placeholder="ابحث عن دواء...">
            </div>
            
            <!-- أزرار الفلترة -->
            <div class="filter-buttons">
                <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">الكل (<?= count($inventory) ?>)</a>
                <a href="?filter=low" class="filter-btn <?= $filter == 'low' ? 'active' : '' ?>">منخفض (<10)</a>
                <a href="?filter=out" class="filter-btn <?= $filter == 'out' ? 'active' : '' ?>">نافد (0)</a>
            </div>
            
            <!-- جدول المخزون -->
            <div class="info-card">
                <div style="overflow-x: auto;">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>الدواء</th>
                                <th>الاسم العلمي</th>
                                <th>التصنيف</th>
                                <th>الكمية</th>
                                <th>السعر</th>
                                <th>وصفة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryBody">
                            <?php 
                            $filteredInventory = [];
                            foreach ($inventory as $item) {
                                if ($filter == 'low' && ($item['quantity'] >= 10 || $item['quantity'] == 0)) continue;
                                if ($filter == 'out' && $item['quantity'] > 0) continue;
                                $filteredInventory[] = $item;
                            }
                            
                            if (empty($filteredInventory)): 
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="padding: 3rem;">
                                        لا توجد أدوية في المخزون
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($filteredInventory as $item): 
                                    $rowClass = '';
                                    $quantityClass = 'quantity-normal';
                                    
                                    if ($item['quantity'] == 0) {
                                        $rowClass = 'out-stock';
                                        $quantityClass = 'quantity-out';
                                    } elseif ($item['quantity'] < 10) {
                                        $rowClass = 'low-stock';
                                        $quantityClass = 'quantity-low';
                                    }
                                ?>
                                    <tr class="<?= $rowClass ?>" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                                        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($item['scientific_name']) ?></td>
                                        <td><?= $item['category'] ?: '-' ?></td>
                                        <td>
                                            <span class="quantity-badge <?= $quantityClass ?>">
                                                <?= $item['quantity'] ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($item['price'], 2) ?> دج</td>
                                        <td>
                                            <?php if ($item['requires_prescription']): ?>
                                                <span class="badge badge-warning">يحتاج وصفة</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">بدون وصفة</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="action-btn" onclick="editMedicine(<?= $item['medicine_id'] ?>, <?= $item['quantity'] ?>, <?= $item['price'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn delete" onclick="deleteMedicine(<?= $item['medicine_id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- زر الإضافة العائم -->
    <button class="floating-add-btn" onclick="openSideModal('addModal')">
        <i class="fas fa-plus"></i>
    </button>
    
    <!-- نافذة تعديل الكمية -->
    <div class="side-modal" id="editModal">
        <div class="side-modal-header">
            <h3>تحديث المخزون</h3>
            <button class="close-side-modal" onclick="closeSideModal('editModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post">
                <input type="hidden" name="medicine_id" id="editMedicineId">
                
                <div class="form-group">
                    <label>الكمية</label>
                    <input type="number" name="quantity" id="editQuantity" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>السعر (دج)</label>
                    <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0" required>
                </div>
                
                <button type="submit" name="update" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> تحديث
                </button>
            </form>
        </div>
    </div>
    
    <!-- نافذة إضافة دواء -->
    <div class="side-modal" id="addModal">
        <div class="side-modal-header">
            <h3>إضافة دواء للمخزون</h3>
            <button class="close-side-modal" onclick="closeSideModal('addModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post">
                <div class="form-group">
                    <label>اختر الدواء</label>
                    <select name="medicine_id" class="form-control" required>
                        <option value="">-- اختر دواء --</option>
                        <?php foreach ($allMedicines as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>الكمية</label>
                    <input type="number" name="quantity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>السعر (دج)</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <button type="submit" name="add" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-cart-plus"></i> إضافة للمخزون
                </button>
            </form>
        </div>
    </div>
    
    <script>
    function editMedicine(id, quantity, price) {
        document.getElementById('editMedicineId').value = id;
        document.getElementById('editQuantity').value = quantity;
        document.getElementById('editPrice').value = price;
        openSideModal('editModal');
    }
    
    function deleteMedicine(id) {
        if (confirm('هل أنت متأكد من حذف هذا الدواء من المخزون؟')) {
            window.location.href = `?delete=${id}`;
        }
    }
    
    // بحث فوري
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const search = this.value.toLowerCase();
        const rows = document.querySelectorAll('#inventoryBody tr');
        
        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            if (name.includes(search)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>