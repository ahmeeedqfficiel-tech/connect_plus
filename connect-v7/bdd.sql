-- ============================================================
-- قاعدة بيانات CONNECT+ - النسخة النهائية الكاملة
-- الإصدار: 2.0 النهائي
-- ============================================================

-- حذف قاعدة البيانات إذا كانت موجودة (اختياري)
DROP DATABASE IF EXISTS connect_plus;

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS connect_plus
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- استخدام قاعدة البيانات
USE connect_plus;

-- ============================================================
-- 1. جدول المستخدمين (الأساسي)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(30) UNIQUE NOT NULL COMMENT 'كود المستخدم الفريد',
    full_name VARCHAR(100) NOT NULL COMMENT 'الاسم الكامل',
    email VARCHAR(100) UNIQUE NOT NULL COMMENT 'البريد الإلكتروني',
    phone VARCHAR(20) NULL COMMENT 'رقم الهاتف',
    password VARCHAR(50) NOT NULL COMMENT 'كلمة المرور',
    national_id VARCHAR(30) UNIQUE NULL COMMENT 'رقم البطاقة الوطنية',
    role ENUM('patient','doctor','pharmacist','admin') NOT NULL DEFAULT 'patient' COMMENT 'دور المستخدم',
    
    -- معلومات شخصية إضافية
    birth_date DATE NULL COMMENT 'تاريخ الميلاد',
    gender ENUM('male','female') NULL COMMENT 'الجنس',
    address TEXT NULL COMMENT 'العنوان',
    city VARCHAR(50) NULL COMMENT 'الولاية',
    
    -- ملفات المستخدم
    profile_image VARCHAR(255) NULL COMMENT 'صورة الملف الشخصي',
    id_card_image VARCHAR(255) NULL COMMENT 'صورة بطاقة التعريف',
    birth_certificate VARCHAR(255) NULL COMMENT 'صورة شهادة الميلاد',
    postal_receipt VARCHAR(255) NULL COMMENT 'صورة الوصل البريدي',
    
    -- ملفات إضافية للأطباء والصيادلة
    license_image VARCHAR(255) NULL COMMENT 'صورة رخصة المزاولة',
    degree_certificate VARCHAR(255) NULL COMMENT 'شهادة التخرج',
    pharmacy_license VARCHAR(255) NULL COMMENT 'رخصة الصيدلية',
    
    -- حالة الحساب
    is_verified BOOLEAN DEFAULT FALSE COMMENT 'تم التأكيد من قبل الأدمن',
    is_available BOOLEAN DEFAULT TRUE COMMENT 'متاح الآن (للأطباء)',
    
    -- التواريخ
    last_login DATETIME NULL COMMENT 'آخر تسجيل دخول',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    
    INDEX idx_role (role),
    INDEX idx_email (email),
    INDEX idx_city (city),
    INDEX idx_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. جدول المرضى (معلومات إضافية)
-- ============================================================
CREATE TABLE IF NOT EXISTS patients (
    user_id INT PRIMARY KEY,
    emergency_name VARCHAR(100) NULL COMMENT 'اسم جهة اتصال الطوارئ',
    emergency_phone VARCHAR(20) NULL COMMENT 'هاتف جهة اتصال الطوارئ',
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL COMMENT 'فصيلة الدم',
    chronic_diseases TEXT NULL COMMENT 'الأمراض المزمنة',
    allergies TEXT NULL COMMENT 'الحساسيات',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. جدول الأطباء
-- ============================================================
CREATE TABLE IF NOT EXISTS doctors (
    user_id INT PRIMARY KEY,
    degree VARCHAR(100) NULL COMMENT 'الدرجة العلمية',
    specialties VARCHAR(255) NULL COMMENT 'التخصصات',
    license_number VARCHAR(50) NULL COMMENT 'رقم رخصة المزاولة',
    license_start_date DATE NULL COMMENT 'تاريخ بدء الترخيص',
    workplace_name VARCHAR(100) NULL COMMENT 'اسم العيادة/المشفى',
    workplace_address TEXT NULL COMMENT 'عنوان العمل',
    consultation_fees DECIMAL(10,2) NULL COMMENT 'رسوم الكشف',
    available_from TIME NULL COMMENT 'من الساعة',
    available_to TIME NULL COMMENT 'إلى الساعة',
    working_days VARCHAR(100) NULL COMMENT 'أيام العمل',
    rating DECIMAL(2,1) DEFAULT 0.0 COMMENT 'متوسط التقييم',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. جدول الصيادلة
-- ============================================================
CREATE TABLE IF NOT EXISTS pharmacists (
    user_id INT PRIMARY KEY,
    pharmacy_name VARCHAR(100) NOT NULL COMMENT 'اسم الصيدلية',
    license_number VARCHAR(50) NULL COMMENT 'رقم الترخيص',
    city VARCHAR(50) NULL COMMENT 'الولاية',
    address TEXT NULL COMMENT 'العنوان',
    is_24h BOOLEAN DEFAULT FALSE COMMENT 'مفتوح 24 ساعة',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. جدول الأدمن
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    user_id INT PRIMARY KEY,
    department VARCHAR(100) NULL COMMENT 'القسم',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. جدول المواعيد
-- ============================================================
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id) ON DELETE CASCADE,
    
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_date (appointment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. جدول الوصفات الطبية
-- ============================================================
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    prescription_date DATE NOT NULL,
    diagnosis TEXT NULL,
    notes TEXT NULL,
    prescription_image VARCHAR(255) NULL,
    status ENUM('active','completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. جدول أدوية الوصفات
-- ============================================================
CREATE TABLE IF NOT EXISTS prescription_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50) NULL,
    duration VARCHAR(50) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    instructions TEXT NULL,
    status ENUM('active','completed','stopped') DEFAULT 'active',
    
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. جدول السجل الطبي
-- ============================================================
CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_date DATETIME NOT NULL,
    diagnosis TEXT NOT NULL,
    notes TEXT NULL,
    
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. جدول التحاليل
-- ============================================================
CREATE TABLE IF NOT EXISTS analyses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NULL,
    analysis_name VARCHAR(100) NOT NULL,
    analysis_date DATE NOT NULL,
    result TEXT NOT NULL,
    lab_name VARCHAR(100) NULL,
    file_path VARCHAR(255) NULL,
    
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. جدول الأشعة
-- ============================================================
CREATE TABLE IF NOT EXISTS radiology (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NULL,
    exam_type VARCHAR(100) NOT NULL,
    exam_date DATE NOT NULL,
    images TEXT NULL,
    report TEXT NULL,
    facility_name VARCHAR(100) NULL,
    
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. جدول الرسائل
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_conversation (sender_id, receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. جدول الإشعارات
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'appointment, message, order, approval, emergency',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. جدول التقييمات
-- ============================================================
CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_rating (doctor_id, patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. جدول طلبات التأكيد
-- ============================================================
CREATE TABLE IF NOT EXISTS approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. جدول طلبات تعديل الملفات
-- ============================================================
CREATE TABLE IF NOT EXISTS profile_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. جدول الصيدليات
-- ============================================================
CREATE TABLE IF NOT EXISTS pharmacies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pharmacist_id INT NULL,
    name VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) NULL,
    city VARCHAR(50) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    phone VARCHAR(20) NOT NULL,
    emergency_phone VARCHAR(20) NULL,
    is_24h BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pharmacist_id) REFERENCES pharmacists(user_id) ON DELETE SET NULL,
    
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. جدول الأدوية العامة
-- ============================================================
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    scientific_name VARCHAR(100) NULL,
    category VARCHAR(50) NULL,
    default_price DECIMAL(10,2) NULL,
    requires_prescription BOOLEAN DEFAULT TRUE,
    
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. جدول مخزون الصيدليات
-- ============================================================
CREATE TABLE IF NOT EXISTS pharmacy_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT DEFAULT 0,
    price DECIMAL(10,2) NULL,
    
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_pharmacy_medicine (pharmacy_id, medicine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. جدول طلبات الأدوية
-- ============================================================
CREATE TABLE IF NOT EXISTS medicine_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    pharmacy_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','confirmed','ready','delivered','cancelled') DEFAULT 'pending',
    total_price DECIMAL(10,2) NULL,
    prescription_image VARCHAR(255) NULL,
    notes TEXT NULL,
    
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE,
    
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 21. جدول تفاصيل طلب الأدوية
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NULL,
    
    FOREIGN KEY (order_id) REFERENCES medicine_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 22. جدول العلاقة بين المرضى والأطباء
-- ============================================================
CREATE TABLE IF NOT EXISTS patient_doctor (
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (patient_id, doctor_id),
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 23. جدول إعدادات المستخدم
-- ============================================================
CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT PRIMARY KEY,
    dark_mode BOOLEAN DEFAULT FALSE,
    language VARCHAR(5) DEFAULT 'ar',
    appointment_notifications BOOLEAN DEFAULT TRUE,
    medication_notifications BOOLEAN DEFAULT TRUE,
    message_notifications BOOLEAN DEFAULT TRUE,
    order_notifications BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    low_stock_alert BOOLEAN DEFAULT TRUE,
    low_stock_threshold INT DEFAULT 10,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 24. جدول سجل النشاطات
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    device_info TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 25. جدول تنبيهات الطوارئ
-- ============================================================
CREATE TABLE IF NOT EXISTS emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    alert_time DATETIME NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    status ENUM('pending','responding','resolved') DEFAULT 'pending',
    
    FOREIGN KEY (patient_id) REFERENCES patients(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 26. جدول رموز إعادة تعيين كلمة المرور
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- إدخال البيانات الأساسية
-- ============================================================

-- 1. مستخدم أدمن
INSERT INTO users (user_code, full_name, email, phone, password, role, is_verified) VALUES
('#ADM000001', 'مدير النظام', 'admin@connectplus.dz', '0553000001', 'admin123', 'admin', 1);

INSERT INTO admins (user_id, department) VALUES (1, 'الإدارة العامة');

-- 2. مرضى
INSERT INTO users (user_code, full_name, email, phone, password, role, is_verified, city) VALUES
('#PAT000001', 'أحمد بن علي', 'ahmed@example.com', '0555123456', '123456', 'patient', 1, 'الجزائر'),
('#PAT000002', 'فاطمة الزهراء', 'fatima@example.com', '0556123456', '123456', 'patient', 0, 'وهران');

INSERT INTO patients (user_id, emergency_name, emergency_phone, blood_type) VALUES
(2, 'سارة بن علي', '0555123477', 'O+'),
(3, 'عمر الزهراء', '0556123477', 'A-');

-- 3. أطباء
INSERT INTO users (user_code, full_name, email, phone, password, role, is_verified, city) VALUES
('#DOC000001', 'د. سمير حداد', 'samir@example.com', '0558123456', 'doctor123', 'doctor', 1, 'الجزائر'),
('#DOC000002', 'د. نوال بن عيسى', 'nawal@example.com', '0559123456', 'doctor123', 'doctor', 1, 'وهران');

INSERT INTO doctors (user_id, degree, specialties, license_number, workplace_name, consultation_fees, available_from, available_to) VALUES
(4, 'دكتوراه في الطب', 'قلب وأوعية دموية', 'LIC001', 'مستشفى مصطفى باشا', 2500, '09:00:00', '17:00:00'),
(5, 'أستاذ في الطب', 'أمراض جلدية', 'LIC002', 'عيادة الجلدية', 2000, '10:00:00', '18:00:00');

-- 4. صيادلة
INSERT INTO users (user_code, full_name, email, phone, password, role, is_verified) VALUES
('#PHA000001', 'عبد الرحمن خالد', 'abderrahmane@example.com', '0551123456', 'pharmacy123', 'pharmacist', 1),
('#PHA000002', 'حنان مرزوق', 'hanane@example.com', '0552123456', 'pharmacy123', 'pharmacist', 1);

INSERT INTO pharmacists (user_id, pharmacy_name, license_number, city, address) VALUES
(6, 'صيدلية الأمان', 'PH001', 'الجزائر', '15 شارع ديدوش مراد'),
(7, 'صيدلية الشفاء', 'PH002', 'وهران', '5 نهج الإخوة بوعلام');

-- 5. صيدليات
INSERT INTO pharmacies (pharmacist_id, name, license_number, city, address, phone, is_24h) VALUES
(6, 'صيدلية الأمان', 'PH001', 'الجزائر', '15 شارع ديدوش مراد', '0551123456', TRUE),
(7, 'صيدلية الشفاء', 'PH002', 'وهران', '5 نهج الإخوة بوعلام', '0552123456', FALSE);

-- 6. أدوية
INSERT INTO medicines (name, scientific_name, category, default_price, requires_prescription) VALUES
('دوليبيران', 'Paracetamol', 'مسكن', 120, 0),
('أموكسيسيلين', 'Amoxicillin', 'مضاد حيوي', 250, 1),
('أدفيل', 'Ibuprofen', 'مضاد التهاب', 180, 0),
('جلوكوفاج', 'Metformin', 'سكري', 150, 1),
('لازيكس', 'Furosemide', 'مدر بول', 90, 1),
('فنتولين', 'Salbutamol', 'موسع قصبات', 200, 1);

-- 7. مخزون الصيدليات
INSERT INTO pharmacy_medicines (pharmacy_id, medicine_id, quantity, price) VALUES
(1, 1, 100, 120),
(1, 2, 50, 250),
(1, 3, 75, 180),
(2, 1, 80, 125),
(2, 4, 40, 150),
(2, 5, 60, 90);

-- 8. مواعيد
INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, reason) VALUES
(2, 4, DATE_ADD(NOW(), INTERVAL 2 DAY), 'pending', 'آلام في الصدر'),
(3, 5, DATE_ADD(NOW(), INTERVAL 1 DAY), 'confirmed', 'طفح جلدي'),
(2, 5, DATE_SUB(NOW(), INTERVAL 5 DAY), 'completed', 'استشارة');

-- 9. وصفات
INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, diagnosis, notes) VALUES
(2, 4, CURDATE(), 'ارتفاع ضغط الدم', 'علاج لمدة شهر'),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'حساسية جلدية', 'مرهم وكريم');

INSERT INTO prescription_medicines (prescription_id, medicine_name, dosage, frequency, duration, start_date, status) VALUES
(1, 'Lisinopril', '10mg', 'يومياً', '30 يوم', CURDATE(), 'active'),
(1, 'Metformin', '850mg', 'مرتين يومياً', '30 يوم', CURDATE(), 'active'),
(2, 'Cetirizine', '10mg', 'يومياً', '10 يوم', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'completed');

-- 10. رسائل
INSERT INTO messages (sender_id, receiver_id, message) VALUES
(2, 4, 'مرحباً دكتور، متى موعدي؟'),
(4, 2, 'موعدك الثلاثاء 10 صباحاً'),
(3, 5, 'هل يمكن تغيير الموعد؟'),
(5, 3, 'نعم، يمكنك اختيار وقت آخر');

-- 11. تقييمات
INSERT INTO ratings (doctor_id, patient_id, rating, comment) VALUES
(4, 2, 5, 'دكتور ممتاز'),
(5, 3, 5, 'أحسن طبيب جلدية');

-- 12. إعدادات المستخدمين
INSERT INTO user_settings (user_id, dark_mode, language) VALUES
(1, 0, 'ar'),
(2, 1, 'ar'),
(3, 0, 'ar'),
(4, 0, 'ar'),
(5, 1, 'ar'),
(6, 1, 'ar'),
(7, 0, 'ar');

-- 13. طلبات تأكيد
INSERT INTO approval_requests (user_id) VALUES (3);

-- 14. علاقة مرضى-أطباء
INSERT INTO patient_doctor (patient_id, doctor_id) VALUES
(2, 4), (2, 5), (3, 5);

-- 15. سجل النشاطات
INSERT INTO activity_log (user_id, action, description, ip_address) VALUES
(1, 'تسجيل دخول', 'تسجيل دخول ناجح', '127.0.0.1');

-- ============================================================
-- عرض معلومات قاعدة البيانات
-- ============================================================
SELECT '✅ قاعدة بيانات CONNECT+ جاهزة للتشغيل' AS result;
SELECT '-----------------------------------' AS '';
SELECT 'عدد الجداول: 26' AS info;
SELECT '-----------------------------------' AS '';
SELECT 'بيانات تسجيل الدخول:' AS info;
SELECT 'admin@connectplus.dz / admin123 (أدمن)' AS admin;
SELECT 'ahmed@example.com / 123456 (مريض)' AS patient;
SELECT 'samir@example.com / doctor123 (طبيب)' AS doctor;
SELECT 'abderrahmane@example.com / pharmacy123 (صيدلي)' AS pharmacist;