<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سياسة الخصوصية - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #2A9D8F, #264653);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .privacy-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 30px;
            padding: 3rem;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo i {
            font-size: 2.2rem;
        }
        
        .back-btn {
            padding: 0.5rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
        }
        
        h1 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        
        .date {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        
        h2 {
            color: var(--secondary);
            margin: 2rem 0 1rem;
            font-size: 1.5rem;
        }
        
        h3 {
            color: var(--primary);
            margin: 1.5rem 0 0.5rem;
            font-size: 1.2rem;
        }
        
        p {
            margin-bottom: 1rem;
            line-height: 1.8;
            color: #333;
        }
        
        ul {
            margin-right: 2rem;
            margin-bottom: 1rem;
        }
        
        li {
            margin-bottom: 0.5rem;
            line-height: 1.8;
        }
        
        .highlight {
            background: rgba(42, 157, 143, 0.1);
            border-right: 4px solid var(--primary);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
            text-align: center;
        }
        
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .data-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }
        
        .data-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .footer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            text-align: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="privacy-container">
        <div class="header">
            <a href="index.php" class="logo">
                <i class="fas fa-plus-circle"></i>
                <span>CONNECT+</span>
            </a>
            <a href="index.php" class="back-btn">
                <i class="fas fa-home"></i> العودة للرئيسية
            </a>
        </div>
        
        <h1>سياسة الخصوصية</h1>
        <p class="date">آخر تحديث: 15 مارس 2026</p>
        
        <div class="highlight">
            <i class="fas fa-lock" style="font-size: 2rem; color: var(--primary); margin-bottom: 1rem; display: block;"></i>
            <p style="font-weight: 600;">خصوصيتك تهمنا. نحن في CONNECT+ نلتزم بحماية معلوماتك الشخصية والطبية.</p>
        </div>
        
        <h2>1. المعلومات التي نجمعها</h2>
        
        <h3>1.1 المعلومات الشخصية</h3>
        <div class="data-grid">
            <div class="data-card">
                <i class="fas fa-user"></i>
                <h4>معلومات أساسية</h4>
                <p>الاسم، تاريخ الميلاد، الجنس</p>
            </div>
            <div class="data-card">
                <i class="fas fa-address-card"></i>
                <h4>معلومات الاتصال</h4>
                <p>البريد، الهاتف، العنوان</p>
            </div>
            <div class="data-card">
                <i class="fas fa-id-card"></i>
                <h4>وثائق تعريف</h4>
                <p>بطاقة التعريف، شهادة الميلاد</p>
            </div>
        </div>
        
        <h3>1.2 المعلومات الطبية</h3>
        <div class="data-grid">
            <div class="data-card">
                <i class="fas fa-heartbeat"></i>
                <h4>ملف طبي</h4>
                <p>الأمراض، الحساسيات، فصيلة الدم</p>
            </div>
            <div class="data-card">
                <i class="fas fa-prescription"></i>
                <h4>وصفات طبية</h4>
                <p>الأدوية، الجرعات، التواريخ</p>
            </div>
            <div class="data-card">
                <i class="fas fa-flask"></i>
                <h4>تحاليل</h4>
                <p>نتائج التحاليل، الأشعة</p>
            </div>
        </div>
        
        <h3>1.3 المعلومات المهنية (للأطباء والصيادلة)</h3>
        <div class="data-grid">
            <div class="data-card">
                <i class="fas fa-graduation-cap"></i>
                <h4>مؤهلات</h4>
                <p>شهادات، تراخيص، تخصصات</p>
            </div>
            <div class="data-card">
                <i class="fas fa-clinic-medical"></i>
                <h4>معلومات العمل</h4>
                <p>عنوان العيادة، ساعات العمل</p>
            </div>
        </div>
        
        <h2>2. كيف نستخدم معلوماتك</h2>
        <ul>
            <li><strong>تقديم الخدمات:</strong> تمكينك من استخدام المنصة والتواصل مع مقدمي الرعاية الصحية.</li>
            <li><strong>تحسين المنصة:</strong> تطوير وتحسين خدماتنا بناءً على استخدامك.</li>
            <li><strong>التواصل:</strong> إرسال إشعارات مهمة حول حسابك ومواعيدك.</li>
            <li><strong>الامتثال القانوني:</strong> الامتثال للقوانين واللوائح المحلية.</li>
        </ul>
        
        <h2>3. مشاركة المعلومات</h2>
        <p>نحن لا نبيع أو نؤجر معلوماتك الشخصية للغير. قد نشارك معلوماتك في الحالات التالية:</p>
        <ul>
            <li><strong>مع مقدمي الرعاية الصحية:</strong> عندما تحجز موعداً مع طبيب أو تطلب دواء من صيدلية.</li>
            <li><strong>بموافقتك:</strong> عندما توافق صراحة على مشاركة معلوماتك.</li>
            <li><strong>للامتثال القانوني:</strong> عندما يطلب منا القانون ذلك.</li>
        </ul>
        
        <h2>4. حماية المعلومات</h2>
        <p>نستخدم إجراءات أمنية متقدمة لحماية معلوماتك:</p>
        <ul>
            <li><i class="fas fa-lock"></i> تشفير البيانات</li>
            <li><i class="fas fa-user-secret"></i> التحكم الصارم في الوصول إلى البيانات</li>
            <li><i class="fas fa-shield-virus"></i> حماية من الهجمات الإلكترونية</li>
            <li><i class="fas fa-database"></i> نسخ احتياطي منتظم للبيانات</li>
        </ul>
        
        <h2>5. حقوقك</h2>
        <ul>
            <li><strong>حق الوصول:</strong> طلب نسخة من معلوماتك الشخصية.</li>
            <li><strong>حق التصحيح:</strong> طلب تصحيح المعلومات غير الدقيقة.</li>
            <li><strong>حق الحذف:</strong> طلب حذف معلوماتك (في حدود القانون).</li>
            <li><strong>حق الاعتراض:</strong> الاعتراض على معالجة معلوماتك.</li>
        </ul>
        <p>لممارسة هذه الحقوق، يرجى التواصل معنا على privacy@connectplus.dz</p>
        
        <h2>6. ملفات تعريف الارتباط (Cookies)</h2>
        <p>نستخدم ملفات تعريف الارتباط لتحسين تجربتك على المنصة. يمكنك التحكم في ملفات تعريف الارتباط من خلال إعدادات المتصفح الخاص بك.</p>
        
        <h2>7. خصوصية القاصرين</h2>
        <p>منصتنا غير موجهة للأشخاص تحت سن 18 عاماً. إذا كنا نعلم أننا جمعنا معلومات من شخص تحت سن 18 عاماً، سنقوم بحذفها فوراً.</p>
        
        <h2>8. التعديلات على سياسة الخصوصية</h2>
        <p>قد نقوم بتحديث هذه السياسة من وقت لآخر. سنقوم بإشعارك بأي تغييرات جوهرية عبر البريد الإلكتروني أو من خلال المنصة.</p>
        
        <h2>9. التواصل معنا</h2>
        <p>إذا كان لديك أي استفسار حول سياسة الخصوصية، يرجى التواصل معنا عبر:</p>
        <ul>
            <li><i class="fas fa-envelope"></i> privacy@connectplus.dz</li>
            <li><i class="fas fa-phone"></i> 0550 00 00 00</li>
            <li><i class="fas fa-map-marker-alt"></i> الجزائر العاصمة، الجزائر</li>
        </ul>
        
        <div class="footer">
            <p>© 2026 CONNECT+ - جميع الحقوق محفوظة</p>
        </div>
    </div>
</body>
</html>