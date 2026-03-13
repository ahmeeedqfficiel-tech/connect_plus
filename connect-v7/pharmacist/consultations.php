<?php
/**
 * pharmacist/consultations.php - الاستشارات والرسائل
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم صيدلي
requirePharmacist($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// ============================================================
// جلب المحادثات
// ============================================================
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_id
    FROM messages 
    WHERE sender_id = ? OR receiver_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user['id'], $user['id'], $user['id']]);
$conversationIds = $stmt->fetchAll();

$conversations = [];
foreach ($conversationIds as $c) {
    $otherId = $c['other_id'];
    
    // معلومات المستخدم الآخر
    $stmt2 = $pdo->prepare("SELECT id, full_name, role, profile_image FROM users WHERE id = ?");
    $stmt2->execute([$otherId]);
    $other = $stmt2->fetch();
    
    if (!$other) continue;
    
    // آخر رسالة
    $stmt3 = $pdo->prepare("
        SELECT message, created_at, is_read, sender_id 
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt3->execute([$user['id'], $otherId, $otherId, $user['id']]);
    $lastMsg = $stmt3->fetch();
    
    // عدد الرسائل غير المقروءة
    $stmt4 = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt4->execute([$otherId, $user['id']]);
    $unread = $stmt4->fetchColumn();
    
    $conversations[] = [
        'other_id' => $otherId,
        'full_name' => $other['full_name'],
        'role' => $other['role'],
        'profile_image' => $other['profile_image'],
        'last_message' => $lastMsg['message'] ?? '',
        'last_time' => $lastMsg['created_at'] ?? null,
        'last_sender' => $lastMsg['sender_id'] ?? null,
        'unread' => $unread
    ];
}

// ترتيب حسب آخر رسالة
usort($conversations, function($a, $b) {
    return strtotime($b['last_time']) - strtotime($a['last_time']);
});

// ============================================================
// جلب المرضى للرسائل الجديدة
// ============================================================
$patients = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name 
    FROM users u
    JOIN medicine_orders mo ON u.id = mo.patient_id
    JOIN pharmacies p ON mo.pharmacy_id = p.id
    WHERE p.pharmacist_id = ? AND u.role = 'patient'
    ORDER BY u.full_name
");
$patients->execute([$user['id']]);
$patientsList = $patients->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الاستشارات - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .chat-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            height: calc(100vh - 200px);
            min-height: 500px;
        }
        
        @media (max-width: 768px) {
            .chat-container {
                grid-template-columns: 1fr;
            }
        }
        
        .conversations-list {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: var(--shadow-md);
            overflow-y: auto;
            height: 100%;
        }
        
        body.dark-mode .conversations-list {
            background: #1E1E1E;
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
            position: relative;
        }
        
        .conversation-item:hover {
            background: var(--primary-soft);
        }
        
        .conversation-item.active {
            background: var(--primary-soft);
            border-right: 4px solid var(--primary);
        }
        
        .conversation-item.unread {
            background: rgba(42, 157, 143, 0.05);
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-name {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .role-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            background: var(--light-gray);
            border-radius: 30px;
            color: var(--gray);
        }
        
        .conversation-last {
            font-size: 0.85rem;
            color: var(--gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-time {
            font-size: 0.7rem;
            color: var(--gray);
            margin-top: 0.2rem;
        }
        
        .unread-badge {
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
        
        .chat-area {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        body.dark-mode .chat-area {
            background: #1E1E1E;
        }
        
        .chat-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-gray);
            margin-bottom: 1rem;
        }
        
        .chat-avatar {
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
        }
        
        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .chat-title {
            flex: 1;
            font-weight: 600;
        }
        
        .chat-status {
            font-size: 0.8rem;
        }
        
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        body.dark-mode .messages-area {
            background: #2D2D2D;
        }
        
        .message {
            max-width: 70%;
            display: flex;
            flex-direction: column;
        }
        
        .message.sent {
            align-self: flex-end;
        }
        
        .message.received {
            align-self: flex-start;
        }
        
        .message-content {
            padding: 0.8rem 1rem;
            border-radius: 20px;
            background: white;
            box-shadow: var(--shadow-sm);
            word-wrap: break-word;
        }
        
        body.dark-mode .message-content {
            background: #1E1E1E;
            color: white;
        }
        
        .message.sent .message-content {
            background: var(--primary);
            color: white;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: var(--gray);
            margin-top: 0.2rem;
            align-self: flex-end;
        }
        
        .message.sent .message-time {
            align-self: flex-end;
        }
        
        .message.received .message-time {
            align-self: flex-start;
        }
        
        .chat-input-area {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .chat-input {
            flex: 1;
            padding: 0.8rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: 30px;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        
        .chat-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .send-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .send-btn:hover {
            transform: scale(1.1);
        }
        
        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .no-chat-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--gray);
        }
        
        .no-chat-selected i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .new-message-btn {
            width: 100%;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .new-message-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
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
                <h1 class="page-title"><i class="fas fa-comments"></i> الاستشارات</h1>
                <button class="btn btn-primary" onclick="openSideModal('newMessageSideModal')">
                    <i class="fas fa-plus"></i> رسالة جديدة
                </button>
            </div>
            
            <div class="chat-container">
                <!-- قائمة المحادثات -->
                <div class="conversations-list">
                    <button class="new-message-btn" onclick="openSideModal('newMessageSideModal')">
                        <i class="fas fa-pen"></i> رسالة جديدة
                    </button>
                    
                    <h3 style="margin-bottom: 1rem; font-size: 1rem; color: var(--gray);">المحادثات</h3>
                    
                    <?php if (empty($conversations)): ?>
                        <div class="no-chat-selected" style="height: auto; padding: 2rem;">
                            <i class="fas fa-comments"></i>
                            <p>لا توجد محادثات</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $c): ?>
                            <div class="conversation-item <?= $c['unread'] ? 'unread' : '' ?>" onclick="loadConversation(<?= $c['other_id'] ?>)">
                                <div class="conversation-avatar">
                                    <?php if (!empty($c['profile_image'])): ?>
                                        <img src="../<?= $c['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name">
                                        <?= htmlspecialchars($c['full_name']) ?>
                                        <span class="role-badge"><?= getRoleText($c['role']) ?></span>
                                    </div>
                                    <div class="conversation-last">
                                        <?php if ($c['last_sender'] == $user['id']): ?>
                                            <span style="color: var(--primary);">أنت: </span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars(substr($c['last_message'], 0, 40)) ?><?= strlen($c['last_message']) > 40 ? '...' : '' ?>
                                    </div>
                                    <div class="conversation-time">
                                        <?= $c['last_time'] ? timeAgo($c['last_time']) : '' ?>
                                    </div>
                                </div>
                                <?php if ($c['unread'] > 0): ?>
                                    <span class="unread-badge"><?= $c['unread'] ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- منطقة المحادثة -->
                <div class="chat-area" id="chatArea">
                    <div class="no-chat-selected" id="noChatSelected">
                        <i class="fas fa-comments"></i>
                        <h3>اختر محادثة</h3>
                        <p style="color: var(--gray);">اختر محادثة من القائمة لبدء المراسلة</p>
                    </div>
                    
                    <div id="activeChat" style="display: none; height: 100%; flex-direction: column;">
                        <div class="chat-header" id="chatHeader">
                            <div class="chat-avatar" id="chatAvatar"></div>
                            <div class="chat-title" id="chatTitle"></div>
                            <div class="chat-status" id="chatStatus"></div>
                        </div>
                        
                        <div class="messages-area" id="messagesArea"></div>
                        
                        <div class="chat-input-area">
                            <input type="text" id="messageInput" class="chat-input" placeholder="اكتب رسالتك...">
                            <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- نافذة رسالة جديدة -->
    <div class="side-modal" id="newMessageSideModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-pen"></i> رسالة جديدة</h3>
            <button class="close-side-modal" onclick="closeSideModal('newMessageSideModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <div class="form-group">
                <label>إلى</label>
                <select id="newMessageReceiver" class="form-control">
                    <option value="">-- اختر المستلم --</option>
                    <?php foreach ($patientsList as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>الرسالة</label>
                <textarea id="newMessageText" class="form-control" rows="6" placeholder="اكتب رسالتك هنا..."></textarea>
            </div>
            
            <button class="btn btn-primary" onclick="sendNewMessage()" style="width: 100%;">
                <i class="fas fa-paper-plane"></i> إرسال
            </button>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    let currentChatId = null;
    let currentUserId = <?= $user['id'] ?>;
    let refreshInterval = null;
    let lastMessageId = 0;
    
    // ============================================================
    // تحميل المحادثة
    // ============================================================
    function loadConversation(otherId) {
        currentChatId = otherId;
        
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        event?.currentTarget.classList.add('active');
        
        document.getElementById('noChatSelected').style.display = 'none';
        document.getElementById('activeChat').style.display = 'flex';
        
        fetch(`../api/get_user_info.php?id=${otherId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const user = data.user;
                    
                    document.getElementById('chatTitle').innerHTML = `
                        <strong>${user.full_name}</strong>
                        <span class="role-badge">${getRoleText(user.role)}</span>
                    `;
                    
                    document.getElementById('chatAvatar').innerHTML = user.profile_image 
                        ? `<img src="../${user.profile_image}">` 
                        : `<i class="fas fa-user"></i>`;
                }
            });
        
        document.getElementById('messageInput').disabled = false;
        document.getElementById('sendBtn').disabled = false;
        document.getElementById('messageInput').focus();
        
        fetchMessages();
        
        if (refreshInterval) clearInterval(refreshInterval);
        refreshInterval = setInterval(fetchMessages, 3000);
    }
    
    // ============================================================
    // جلب الرسائل
    // ============================================================
    function fetchMessages() {
        if (!currentChatId) return;
        
        fetch(`../api/get_messages.php?other_id=${currentChatId}&after=${lastMessageId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    lastMessageId = Math.max(...data.messages.map(m => m.id));
                    appendMessages(data.messages);
                    markMessagesAsRead();
                }
            });
    }
    
    // ============================================================
    // إضافة رسائل جديدة
    // ============================================================
    function appendMessages(messages) {
        const area = document.getElementById('messagesArea');
        
        messages.forEach(msg => {
            const isMine = msg.sender_id == currentUserId;
            const messageHtml = `
                <div class="message ${isMine ? 'sent' : 'received'}">
                    <div class="message-content">${escapeHtml(msg.message)}</div>
                    <div class="message-time">${timeAgo(msg.created_at)}</div>
                </div>
            `;
            
            area.insertAdjacentHTML('beforeend', messageHtml);
        });
        
        area.scrollTop = area.scrollHeight;
    }
    
    // ============================================================
    // تعليم الرسائل كمقروءة
    // ============================================================
    function markMessagesAsRead() {
        if (!currentChatId) return;
        
        fetch('../api/mark_messages_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sender_id: currentChatId})
        }).then(() => {
            updateConversationUnread(currentChatId, 0);
        });
    }
    
    // ============================================================
    // تحديث عداد المحادثة
    // ============================================================
    function updateConversationUnread(chatId, count) {
        const convItems = document.querySelectorAll('.conversation-item');
        for (let item of convItems) {
            if (item.getAttribute('onclick')?.includes(chatId)) {
                const badge = item.querySelector('.unread-badge');
                if (count > 0) {
                    if (badge) {
                        badge.textContent = count;
                    } else {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'unread-badge';
                        newBadge.textContent = count;
                        item.appendChild(newBadge);
                    }
                    item.classList.add('unread');
                } else {
                    if (badge) badge.remove();
                    item.classList.remove('unread');
                }
                break;
            }
        }
    }
    
    // ============================================================
    // إرسال رسالة
    // ============================================================
    function sendMessage() {
        const input = document.getElementById('messageInput');
        const msg = input.value.trim();
        
        if (!msg || !currentChatId) return;
        
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;
        
        fetch('../api/send_message.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                receiver_id: currentChatId,
                message: msg
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const area = document.getElementById('messagesArea');
                const messageHtml = `
                    <div class="message sent">
                        <div class="message-content">${escapeHtml(msg)}</div>
                        <div class="message-time">الآن</div>
                    </div>
                `;
                area.insertAdjacentHTML('beforeend', messageHtml);
                area.scrollTop = area.scrollHeight;
                
                input.value = '';
                updateLastMessage(currentChatId, msg);
            } else {
                showToast('فشل إرسال الرسالة', 'error');
            }
        })
        .finally(() => {
            sendBtn.disabled = false;
            input.focus();
        });
    }
    
    // ============================================================
    // تحديث آخر رسالة
    // ============================================================
    function updateLastMessage(chatId, message) {
        const convItems = document.querySelectorAll('.conversation-item');
        for (let item of convItems) {
            if (item.getAttribute('onclick')?.includes(chatId)) {
                const lastMsgEl = item.querySelector('.conversation-last');
                if (lastMsgEl) {
                    lastMsgEl.innerHTML = `<span style="color: var(--primary);">أنت: </span>${escapeHtml(message.substring(0, 30))}${message.length > 30 ? '...' : ''}`;
                }
                
                const parent = item.parentNode;
                parent.insertBefore(item, parent.firstChild);
                break;
            }
        }
    }
    
    // ============================================================
    // إرسال رسالة جديدة
    // ============================================================
    function sendNewMessage() {
        const receiver = document.getElementById('newMessageReceiver').value;
        const message = document.getElementById('newMessageText').value.trim();
        
        if (!receiver) {
            showToast('اختر المستلم أولاً', 'warning');
            return;
        }
        
        if (!message) {
            showToast('اكتب الرسالة', 'warning');
            return;
        }
        
        fetch('../api/send_message.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                receiver_id: receiver,
                message: message
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم إرسال الرسالة', 'success');
                closeSideModal('newMessageSideModal');
                loadConversation(receiver);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('فشل إرسال الرسالة', 'error');
            }
        });
    }
    
    // ============================================================
    // أحداث الإدخال
    // ============================================================
    document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) clearInterval(refreshInterval);
    });
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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