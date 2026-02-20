<?php
// team_chat.php - FULL OPTIMIZED VERSION (WhatsApp-like with read receipts + typing)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$my_id = $_SESSION['user_id'];
$my_role = trim($_SESSION['role'] ?? 'Employee');
$my_username = $_SESSION['username'] ?? 'User';

// --- ENCRYPTION HELPERS ---
define('CHAT_ENC_KEY', 'Workack_Secret_Key_2026');

function encryptChatMessage($plainText) {
    if (empty($plainText)) return $plainText;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($plainText, 'aes-256-cbc', CHAT_ENC_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

function decryptChatMessage($encryptedText) {
    if (empty($encryptedText)) return $encryptedText;
    $decoded = base64_decode($encryptedText, true);
    if ($decoded !== false && strpos($decoded, '::') !== false) {
        $parts = explode('::', $decoded, 2);
        if (count($parts) == 2) {
            $decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', CHAT_ENC_KEY, 0, base64_decode($parts[1]));
            if ($decrypted !== false) return $decrypted;
        }
    }
    return $encryptedText;
}
// --------------------------

// Database schema updates + indexes + new tables
if (!isset($_SESSION['chat_db_checked'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS call_requests (id INT AUTO_INCREMENT PRIMARY KEY, conversation_id INT NOT NULL, caller_id INT NOT NULL, room_id VARCHAR(64) NOT NULL, call_type ENUM('audio','video') DEFAULT 'video', status ENUM('ringing','answered','declined','ended') DEFAULT 'ringing', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_conv_status (conversation_id, status))");

    // Add missing columns
    if ($conn->query("SHOW COLUMNS FROM chat_messages LIKE 'edited_at'")->num_rows === 0) $conn->query("ALTER TABLE chat_messages ADD COLUMN edited_at DATETIME NULL DEFAULT NULL");
    if ($conn->query("SHOW COLUMNS FROM chat_messages LIKE 'deleted_at'")->num_rows === 0) $conn->query("ALTER TABLE chat_messages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    if ($conn->query("SHOW COLUMNS FROM chat_participants LIKE 'muted_until'")->num_rows === 0) $conn->query("ALTER TABLE chat_participants ADD COLUMN muted_until DATETIME NULL DEFAULT NULL");
    if ($conn->query("SHOW COLUMNS FROM chat_participants LIKE 'hidden_at'")->num_rows === 0) $conn->query("ALTER TABLE chat_participants ADD COLUMN hidden_at DATETIME NULL DEFAULT NULL");

    // WhatsApp-style per-message read receipts
    $conn->query("CREATE TABLE IF NOT EXISTS message_reads (message_id INT NOT NULL, user_id INT NOT NULL, read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (message_id, user_id), INDEX idx_user (user_id)) ENGINE=InnoDB");

    // Typing indicators
    $conn->query("CREATE TABLE IF NOT EXISTS typing_status (conversation_id INT NOT NULL, user_id INT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (conversation_id, user_id)) ENGINE=InnoDB");

    // Performance indexes
    $conn->query("ALTER TABLE chat_messages ADD INDEX IF NOT EXISTS idx_conv_sender_created (conversation_id, sender_id, created_at)");
    $conn->query("ALTER TABLE chat_messages ADD INDEX IF NOT EXISTS idx_conv_id_desc (conversation_id, id DESC)");
    $conn->query("ALTER TABLE chat_participants ADD UNIQUE KEY IF NOT EXISTS uk_user_conv (user_id, conversation_id)");
    $conn->query("ALTER TABLE call_requests ADD INDEX IF NOT EXISTS idx_conv_caller_status (conversation_id, caller_id, status)");
    $conn->query("ALTER TABLE chat_messages ADD INDEX IF NOT EXISTS idx_conv_sender_read (conversation_id, sender_id, deleted_at)");

    // Remove old global is_read if exists
    if ($conn->query("SHOW COLUMNS FROM chat_messages LIKE 'is_read'")->num_rows > 0) {
        $conn->query("ALTER TABLE chat_messages DROP COLUMN is_read");
    }

    $_SESSION['chat_db_checked'] = true;
}

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_write_close(); 
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // 1. SEARCH USERS
    if ($action === 'search_users') {
        $term = "%" . ($_POST['term'] ?? '') . "%";
        $sql = "SELECT u.id, u.role, COALESCE(ep.full_name, u.username) as display_name, ep.profile_img 
                FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE (ep.full_name LIKE ? OR u.username LIKE ? OR u.role LIKE ?) AND u.id != ? LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $term, $term, $term, $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        while($row = $res->fetch_assoc()) { 
            if(empty($row['profile_img'])) $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
            $users[] = $row; 
        }
        echo json_encode($users);
        exit;
    }

    // 2. CREATE GROUP
    if ($action === 'create_group') {
        $group_name = trim($_POST['group_name'] ?? '');
        $members = json_decode($_POST['members'] ?? '[]', true);
        
        if (empty($group_name) || !is_array($members) || empty($members)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide a group name and select at least one member.']); 
            exit;
        }
        
        $members = array_filter(array_unique(array_map('intval', $members)));
        if (empty($members)) {
            echo json_encode(['status' => 'error', 'message' => 'Selected members are invalid.']); 
            exit;
        }

        $placeholders = str_repeat('?,', count($members)-1) . '?';
        $sql = "SELECT id FROM users WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($members)), ...$members);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok_ids = [];
        while ($r = $res->fetch_assoc()) { $ok_ids[] = $r['id']; }
        
        if (empty($ok_ids)) { echo json_encode(['status' => 'error', 'message' => 'Selected members are invalid.']); exit; }

        $stmt = $conn->prepare("INSERT INTO chat_conversations (type, group_name, created_by) VALUES ('group', ?, ?)");
        $stmt->bind_param("si", $group_name, $my_id);
        $stmt->execute();
        
        $conv_id = $conn->insert_id;
        
        $stmt_part = $conn->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
        $stmt_part->bind_param("ii", $conv_id, $my_id);
        $stmt_part->execute();
        foreach ($ok_ids as $uid) {
            $stmt_part->bind_param("ii", $conv_id, $uid);
            $stmt_part->execute();
        }

        $sys_msg = encryptChatMessage("Group '" . $group_name . "' created.");
        $stmt_msg = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES (?, ?, ?, 'text')");
        $stmt_msg->bind_param("iis", $conv_id, $my_id, $sys_msg);
        $stmt_msg->execute();

        echo json_encode(['status' => 'success', 'conversation_id' => $conv_id]);
        exit;
    }

    // 3. GET RECENT CHATS (MariaDB compatible + per-message unread)
    if ($action === 'get_recent_chats') {
        $sql = "
            SELECT 
                c.id AS conversation_id,
                c.type,
                c.group_name,
                cp.muted_until,
                cm.message AS last_msg,
                cm.message_type,
                cm.created_at AS time,
                (
                    SELECT COUNT(m.id)
                    FROM chat_messages m
                    LEFT JOIN message_reads r ON m.id = r.message_id AND r.user_id = ?
                    WHERE m.conversation_id = c.id AND m.sender_id != ? AND r.message_id IS NULL AND m.deleted_at IS NULL
                ) AS unread,
                IF(c.type = 'group',
                    c.group_name,
                    COALESCE(ep.full_name, u.username, 'Unknown User')
                ) AS name,
                IF(c.type = 'group',
                    CONCAT('https://ui-avatars.com/api/?name=', REPLACE(c.group_name, ' ', '+'), '&background=6366f1&color=fff'),
                    COALESCE(ep.profile_img, CONCAT('https://ui-avatars.com/api/?name=', REPLACE(COALESCE(ep.full_name, u.username), ' ', '+'), '&background=random'))
                ) AS avatar_db
            FROM chat_conversations c
            INNER JOIN chat_participants cp ON c.id = cp.conversation_id AND cp.user_id = ? AND cp.hidden_at IS NULL
            LEFT JOIN chat_messages cm ON cm.id = (SELECT MAX(id) FROM chat_messages m2 WHERE m2.conversation_id = c.id)
            LEFT JOIN chat_participants cp2 ON c.type = 'direct' AND cp2.conversation_id = c.id AND cp2.user_id != ?
            LEFT JOIN users u ON cp2.user_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id
            ORDER BY COALESCE(cm.created_at, c.created_at) DESC
            LIMIT 60
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $my_id, $my_id, $my_id, $my_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $chats = [];
        while($row = $result->fetch_assoc()) {
            if ($row['type'] == 'group') {
                $row['name'] = $row['group_name'];
                $row['avatar'] = "https://ui-avatars.com/api/?name=".urlencode($row['group_name'])."&background=6366f1&color=fff";
            } else {
                $row['avatar'] = !empty($row['avatar_db']) ? $row['avatar_db'] : "https://ui-avatars.com/api/?name=".urlencode($row['name'])."&background=1b5a5a&color=fff";
            }
            
            if ($row['message_type'] == 'text') $row['last_msg'] = decryptChatMessage($row['last_msg']);
            else if ($row['message_type'] == 'call') $row['last_msg'] = (stripos($row['last_msg'], 'audio:') === 0) ? '📞 Voice call' : '📹 Video meeting';
            else if ($row['message_type'] == 'image') $row['last_msg'] = '🖼️ Photo';
            else if ($row['message_type'] == 'file') $row['last_msg'] = '📎 Attachment';

            $row['time'] = $row['time'] ? date('h:i A', strtotime($row['time'])) : '';
            $row['muted'] = !empty($row['muted_until']) && strtotime($row['muted_until']) > time();
            $chats[] = $row;
        }
        echo json_encode($chats);
        exit;
    }

    // 4. GET MESSAGES
    if ($action === 'get_messages') {
        $conv_id = (int)$_POST['conversation_id'];
        $last_msg_id = isset($_POST['last_msg_id']) ? (int)$_POST['last_msg_id'] : 0;

        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $chk->bind_param("ii", $conv_id, $my_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            echo json_encode(['messages' => [], 'info' => null]);
            exit;
        }
        
        // Mark as read (per-message WhatsApp style)
        $mark = $conn->prepare("INSERT IGNORE INTO message_reads (message_id, user_id)
            SELECT id, ? FROM chat_messages WHERE conversation_id = ? AND sender_id != ? AND deleted_at IS NULL");
        $mark->bind_param("iii", $my_id, $conv_id, $my_id);
        $mark->execute();

        $sql = "
            SELECT m.*, COALESCE(ep.full_name, u.username) as display_name,
                   (SELECT COUNT(*) FROM message_reads r WHERE r.message_id = m.id) AS read_count
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id
            WHERE m.conversation_id = ? AND m.id > ? AND m.deleted_at IS NULL
            ORDER BY m.id ASC LIMIT 50
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $conv_id, $last_msg_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $msgs = [];
        while($row = $res->fetch_assoc()) {
            $row['is_me'] = ($row['sender_id'] == $my_id);
            $row['time'] = date('h:i A', strtotime($row['created_at']));
            if($row['message_type'] == 'text') $row['message'] = decryptChatMessage($row['message']);
            $row['read_status'] = $row['is_me'] ? ($row['read_count'] > 0 ? 2 : 1) : 0;
            $msgs[] = $row;
        }

        // Typing users
        $typing_sql = "
            SELECT COALESCE(ep.full_name, u.username) as typing_name
            FROM typing_status ts
            JOIN users u ON ts.user_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id
            WHERE ts.conversation_id = ? AND ts.user_id != ? AND ts.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ";
        $typing_stmt = $conn->prepare($typing_sql);
        $typing_stmt->bind_param("ii", $conv_id, $my_id);
        $typing_stmt->execute();
        $typing_res = $typing_stmt->get_result();
        $typing_users = [];
        while ($t = $typing_res->fetch_assoc()) $typing_users[] = $t['typing_name'];

        // Partner info
        $conv_info = $conn->query("SELECT * FROM chat_conversations WHERE id = $conv_id")->fetch_assoc();
        if ($conv_info['type'] == 'direct') {
            $p_stmt = $conn->prepare("SELECT COALESCE(ep.full_name, u.username) as display_name, u.role, ep.profile_img 
                                      FROM chat_participants cp 
                                      JOIN users u ON cp.user_id = u.id 
                                      LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                                      WHERE cp.conversation_id = ? AND cp.user_id != ? LIMIT 1");
            $p_stmt->bind_param("ii", $conv_id, $my_id);
            $p_stmt->execute();
            $partner = $p_stmt->get_result()->fetch_assoc() ?: ['display_name' => 'Unknown User', 'role' => '', 'profile_img' => ''];
            if(empty($partner['profile_img'])) $partner['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($partner['display_name'])."&background=random";
        } else {
            $partner = ['display_name' => $conv_info['group_name'], 'role' => 'Group Chat', 'is_group' => true, 'profile_img' => "https://ui-avatars.com/api/?name=".urlencode($conv_info['group_name'])."&background=6366f1&color=fff"];
        }
        $mu = $conn->query("SELECT muted_until FROM chat_participants WHERE conversation_id = $conv_id AND user_id = $my_id")->fetch_assoc();
        $partner['muted'] = !empty($mu['muted_until']) && strtotime($mu['muted_until']) > time();

        echo json_encode(['messages' => $msgs, 'info' => $partner, 'typing' => $typing_users]);
        exit;
    }

    // 5. SEND MESSAGE
    if ($action === 'send_message') {
        $conv_id = (int)$_POST['conversation_id'];
        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $chk->bind_param("ii", $conv_id, $my_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['status' => 'denied']); exit; }
        
        $msg_text = $_POST['message'] ?? '';
        $msg_type = $_POST['type'] ?? 'text'; 
        $attachment = null;

        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $target_dir = "uploads/chat/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $fname = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $fname);
            $attachment = $target_dir . $fname;
            $msg_type = in_array(strtolower($ext), ['jpg','jpeg','png','gif']) ? 'image' : 'file';
            if(!$msg_text) $msg_text = $_FILES['file']['name'];
        }

        if ($msg_type === 'text') { $msg_text = encryptChatMessage($msg_text); }

        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, attachment_path, message_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $conv_id, $my_id, $msg_text, $attachment, $msg_type);
        $stmt->execute();
        echo json_encode(['status' => 'sent']);
        exit;
    }

    // 6. START CHAT
    if ($action === 'start_chat') {
        $target_user_id = (int)$_POST['target_user_id'];
        $sql = "SELECT c.id FROM chat_conversations c
                JOIN chat_participants cp1 ON c.id = cp1.conversation_id
                JOIN chat_participants cp2 ON c.id = cp2.conversation_id
                WHERE c.type = 'direct' AND cp1.user_id = ? AND cp2.user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $my_id, $target_user_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $upd = $conn->prepare("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = ? AND user_id = ?");
            $upd->bind_param("ii", $row['id'], $my_id);
            $upd->execute();
            echo json_encode(['status' => 'success', 'id' => $row['id']]);
        } else {
            $ins = $conn->prepare("INSERT INTO chat_conversations (type) VALUES ('direct')");
            $ins->execute();
            $new_id = $conn->insert_id;
            $part = $conn->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
            $part->bind_param("ii", $new_id, $my_id); $part->execute();
            $part->bind_param("ii", $new_id, $target_user_id); $part->execute();
            echo json_encode(['status' => 'success', 'id' => $new_id]);
        }
        exit;
    }

    // 7. START CALL
    if ($action === 'start_call') {
        $conv_id = (int)$_POST['conversation_id'];
        $call_type = ($_POST['call_type'] ?? 'video') === 'audio' ? 'audio' : 'video';
        $room_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $_POST['room_id'] ?? '');
        
        $store_value = ($call_type === 'audio') ? 'audio:' . $room_id : $room_id;
        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES (?, ?, ?, 'call')");
        $stmt->bind_param("iis", $conv_id, $my_id, $store_value);
        $stmt->execute();
        
        $del = $conn->prepare("DELETE FROM call_requests WHERE conversation_id = ? AND status = 'ringing'");
        $del->bind_param("i", $conv_id);
        $del->execute();
        
        $ins = $conn->prepare("INSERT INTO call_requests (conversation_id, caller_id, room_id, call_type, status) VALUES (?, ?, ?, ?, 'ringing')");
        $ins->bind_param("iiss", $conv_id, $my_id, $room_id, $call_type);
        $ins->execute();
        
        echo json_encode(['status' => 'ok', 'room_id' => $room_id, 'call_type' => $call_type]);
        exit;
    }

    // Other actions (all prepared now)
    if ($action === 'clear_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("UPDATE chat_messages SET deleted_at = NOW() WHERE conversation_id = ? AND sender_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'cleared']); exit;
    }
    if ($action === 'delete_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("UPDATE chat_participants SET hidden_at = NOW() WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }
    if ($action === 'check_incoming_call') {
        $conv_id = (int)($_POST['conversation_id'] ?? 0);
        $sql = "SELECT cr.id, cr.conversation_id, cr.room_id, cr.call_type, 
                       COALESCE(ep.full_name, u.username) as caller_name, ep.profile_img as caller_avatar, 
                       c.type as conv_type, c.group_name 
                FROM call_requests cr 
                JOIN chat_participants cp ON cr.conversation_id = cp.conversation_id AND cp.user_id = ? 
                JOIN users u ON cr.caller_id = u.id 
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                JOIN chat_conversations c ON cr.conversation_id = c.id 
                WHERE cr.caller_id != ? AND cr.status = 'ringing' AND cr.created_at > DATE_SUB(NOW(), INTERVAL 90 SECOND) 
                ORDER BY cr.created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $my_id, $my_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            if (empty($row['caller_avatar'])) $row['caller_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($row['caller_name']) . "&background=random";
            $row['display_label'] = $row['conv_type'] === 'group' ? $row['group_name'] . ' – ' . $row['caller_name'] : $row['caller_name'];
            echo json_encode(['has_call' => true, 'call' => $row]);
        } else { echo json_encode(['has_call' => false]); }
        exit;
    }
    if ($action === 'answer_call') {
        $call_id = (int)$_POST['call_id'];
        $stmt = $conn->prepare("UPDATE call_requests SET status = 'answered' WHERE id = ?");
        $stmt->bind_param("i", $call_id);
        $stmt->execute();
        $row = $conn->query("SELECT room_id, call_type, conversation_id FROM call_requests WHERE id = $call_id")->fetch_assoc();
        echo json_encode(['status' => 'ok', 'room_id' => $row['room_id'], 'call_type' => $row['call_type'], 'conversation_id' => $row['conversation_id']]); exit;
    }
    if ($action === 'decline_call' || $action === 'end_call_request') {
        if(isset($_POST['call_id'])) {
            $stmt = $conn->prepare("UPDATE call_requests SET status = 'declined' WHERE id = ?");
            $stmt->bind_param("i", (int)$_POST['call_id']);
            $stmt->execute();
        }
        if(isset($_POST['conversation_id'])) {
            $stmt = $conn->prepare("UPDATE call_requests SET status = 'ended' WHERE conversation_id = ?");
            $stmt->bind_param("i", (int)$_POST['conversation_id']);
            $stmt->execute();
        }
        echo json_encode(['status' => 'ok']); exit;
    }

    // TYPING INDICATORS
    if ($action === 'start_typing') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("INSERT INTO typing_status (conversation_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action === 'stop_typing') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("DELETE FROM typing_status WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeamChat | Workack</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://meet.jit.si/external_api.js"></script>

    <style>
        :root { 
            --primary-color:  #1b5a5a;
            --primary-light: #2d7a7a;
            --bg-body: #f8fafc; 
            --text-main: #1e293b; 
            --text-secondary: #64748b; 
            --border-color: #e2e8f0; 
            --incoming-bg: #ffffff; 
            --outgoing-bg: #1b5a5a; 
            --outgoing-text: #ffffff; 
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
        body { background-color:var(--bg-body); height:100vh; width:100vw; overflow:hidden; }
        #mainContent { margin-left: 95px; width: calc(100% - 95px); height: 100vh; display: flex; flex-direction: column; }
        .sidebar-secondary.open ~ #mainContent { margin-left: calc(95px + 220px); width: calc(100% - (95px + 220px)); }
        .app-container { flex: 1; display:flex; height: 0; min-height: 0; background: #fff; border-top: 1px solid var(--border-color);}
        
        /* SIDEBAR */
        .sidebar { width: 340px; background: #fff; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content:space-between; align-items:center; }
        .search-box { position: relative; padding: 15px 20px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;}
        .search-box input { width: 100%; padding: 12px 12px 12px 40px; border: 1px solid var(--border-color); border-radius: 12px; background: #fff; outline: none; }
        .search-box i { position: absolute; left: 35px; top: 27px; color: var(--text-secondary); }
        
        .chat-list { flex: 1; overflow-y: auto; }
        .chat-item { display: flex; align-items: center; padding: 15px 20px; cursor: pointer; border-bottom: 1px solid #f8fafc; }
        .chat-item:hover { background: #f1f5f9; }
        .chat-item.active { background: #f0fdfa; border-right: 4px solid var(--primary-color); }
        .avatar { width: 48px; height: 48px; border-radius: 50%; margin-right: 15px; object-fit: cover; }
        
        #searchResults { position: absolute; top: 70px; left: 20px; width: calc(100% - 40px); background: white; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; display: none; }
        .search-item { padding: 12px 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .search-item:hover { background: #f8fafc; }

        /* CHAT AREA */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #f8fafc; overflow:hidden;}
        .chat-header { height: 75px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; padding: 0 25px; justify-content: space-between; }
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .btn-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #f1f5f9; border:none; cursor: pointer; }
        .btn-icon:hover { background: #e2e8f0; color: var(--primary-color); }
        
        .messages-box { flex: 1; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 15px; }
        .msg-wrapper { display: flex; flex-direction: column; max-width: 75%; }
        .msg-wrapper.incoming { align-self: flex-start; }
        .msg-wrapper.outgoing { align-self: flex-end; }
        .msg { padding: 12px 16px; border-radius: 16px; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; }
        .msg.incoming { background: var(--incoming-bg); border: 1px solid var(--border-color); border-bottom-left-radius: 4px; }
        .msg.outgoing { background: var(--outgoing-bg); color: var(--outgoing-text); border-bottom-right-radius: 4px; }
        .msg-time { font-size: 0.7rem; margin-top: 6px; opacity: 0.7; text-align: right; }
        .ticks { font-size: 0.9rem; margin-left: 4px; }
        
        .input-area { padding: 20px 25px; background: white; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; }
        .input-area input { flex: 1; padding: 14px 20px; border: 1px solid var(--border-color); border-radius: 30px; outline: none; background: #f8fafc; }
        .btn-send { background: var(--primary-color); color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; }

        /* VIDEO OVERLAY */
        #videoOverlay { display:none; position:absolute; top:75px; left:0; width:100%; height:calc(100% - 75px); background:#0f172a; z-index:200; flex-direction:column; }
        .video-overlay-header { padding: 15px 25px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; color: white; }

        /* MODALS */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal { background: white; width: 420px; border-radius: 16px; padding: 25px; }
        .incoming-call-box { background: white; border-radius: 20px; padding: 36px; text-align: center; min-width: 320px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .typing { font-size: 0.85rem; color: #64748b; font-style: italic; padding-left: 57px; margin-top: 2px; }
    </style>
</head>
<body>

<?php if(file_exists('sidebars.php')) include 'sidebars.php'; ?>

<main id="mainContent">
    <?php if(file_exists('header.php')) include 'header.php'; ?>
    
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2 style="font-weight:800; color:var(--text-main); font-size: 1.4rem;">Chats</h2>
                <button onclick="openGroupModal()" title="Create Group" class="btn-icon" style="width:35px; height:35px;"><i class="ri-add-line"></i></button>
            </div>
            <div class="search-box">
                <i class="ri-search-line"></i>
                <input type="text" id="userSearch" placeholder="Search team members...">
                <div id="searchResults"></div>
            </div>
            <div class="chat-list" id="chatList">
                <div style="text-align:center; padding: 30px; color:var(--text-secondary);"><i class="ri-loader-4-line ri-spin"></i> Loading chats...</div>
            </div>
        </aside>

        <section class="chat-area" id="chatArea">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-secondary);">
                <div style="width: 80px; height: 80px; background: #e2e8f0; border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 20px;">
                    <i class="ri-message-3-line" style="font-size:2.5rem; color: #94a3b8;"></i>
                </div>
                <h3 style="font-size: 1.2rem; color: var(--text-main); margin-bottom: 5px;">Workack Team Chat</h3>
                <p>Select a conversation from the sidebar to start messaging.</p>
            </div>
            
            <div id="videoOverlay">
                <div class="video-overlay-header">
                    <h3 style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                        <i class="ri-record-circle-fill" style="color:#ef4444; font-size:0.9rem;"></i> <span id="callTypeLabel">Live Meeting</span>
                    </h3>
                    <button onclick="closeCall()" style="background:#ef4444; border:none; color:white; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="ri-phone-x-line"></i> Leave Call
                    </button>
                </div>
                <div id="jitsiContainer"></div>
            </div>
        </section>
    </div>
</main>

<div id="incomingCallModal" class="modal-overlay">
    <div class="incoming-call-box">
        <img id="incomingCallerAvatar" src="" alt="">
        <h3 id="incomingCallerName">Incoming Call</h3>
        <p id="incomingCallLabel">Voice Call</p>
        <div class="incoming-call-actions" style="display:flex; gap:16px; justify-content:center; margin-top:24px;">
            <button class="btn-accept-call" onclick="acceptIncomingCall()" style="background:#22c55e; color:white; padding:14px 28px; border-radius:12px; border:none; cursor:pointer; font-weight:600;">Accept</button>
            <button class="btn-decline-call" onclick="declineIncomingCall()" style="background:#ef4444; color:white; padding:14px 28px; border-radius:12px; border:none; cursor:pointer; font-weight:600;">Decline</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="groupModal">
    <div class="modal">
        <h3>Create New Group</h3>
        <input type="text" id="groupName" placeholder="Enter Group Name">
        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:8px; font-weight: 500;">Select Members:</p>
        <div id="selectedTags" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px;"></div>
        <input type="text" id="memberSearch" placeholder="Search names..." oninput="searchForGroup(this.value)">
        <div class="user-select-list" id="groupUserList" style="max-height:250px; overflow-y:auto; border:1px solid var(--border-color); border-radius:8px;"></div>
        <div class="modal-actions" style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
            <button class="btn-cancel" onclick="closeGroupModal()" style="padding:10px 20px; border:1px solid var(--border-color); background:white; border-radius:8px; cursor:pointer;">Cancel</button>
            <button class="btn-create" onclick="createGroup()" style="padding:10px 20px; background:var(--primary-color); color:white; border:none; border-radius:8px; cursor:pointer;">Create Group</button>
        </div>
    </div>
</div>

<script>
    let activeConvId = null;
    let pollInterval = null;
    let lastFetchedMsgId = 0; 
    let isUserScrolling = false;
    let isFetching = false;
    let isSidebarFetching = false;
    let cachedSidebarHTML = '';
    let selectedMembers = new Set();
    let selectedMembersData = new Map(); 
    let jitsiApi = null;
    const myUserName = "<?php echo htmlspecialchars($my_username, ENT_QUOTES, 'UTF-8'); ?>";
    let currentIncomingCall = null;
    let searchDebounce = null;
    let typingTimer = null;

    // Load sidebar
    function loadSidebar() {
        if(isSidebarFetching) return;
        isSidebarFetching = true;
        let fd = new FormData();
        fd.append('action', 'get_recent_chats');
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            isSidebarFetching = false;
            let html = '';
            data.forEach(c => {
                let active = (c.conversation_id == activeConvId) ? 'active' : '';
                let unread = c.unread > 0 ? `<span style="background:var(--primary-color); color:white; font-size:0.7rem; font-weight:bold; padding:2px 6px; border-radius:10px;">${c.unread}</span>` : '';
                let muteTag = c.muted ? '<span style="font-size:0.65rem; color:#94a3b8; margin-left:4px;">Muted</span>' : '';
                html += `<div class="chat-item ${active}" onclick="loadConversation(${c.conversation_id})">
                            <img src="${c.avatar}" class="avatar" loading="lazy">
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                    <div style="font-weight:600; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.name || 'Unknown'}${muteTag}</div>
                                    <div style="font-size:0.7rem; color:var(--text-secondary);">${c.time}</div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:80%; font-size:0.85rem; color:var(--text-secondary);">${c.last_msg || 'Start a conversation'}</span>
                                    ${unread}
                                </div>
                            </div>
                        </div>`;
            });
            if(data.length === 0) html = '<div style="text-align:center; padding: 30px; color:var(--text-secondary);">No active chats</div>';
            if (cachedSidebarHTML !== html) {
                document.getElementById('chatList').innerHTML = html;
                cachedSidebarHTML = html;
            }
        }).catch(() => isSidebarFetching = false);
    }

    function loadConversation(convId, forceRefresh = false) {
        if(activeConvId === convId && !forceRefresh) return; 
        activeConvId = convId;
        lastFetchedMsgId = 0; 
        isUserScrolling = false;
        isFetching = false;

        document.getElementById('chatArea').innerHTML = `
            <div class="chat-header" id="chatHeader">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="avatar" style="width:42px;height:42px;background:#f1f5f9;"></div>
                    <div>
                        <h3 style="font-size:1.1rem; color:#ccc;background:#f1f5f9;width:120px;height:18px;border-radius:4px;"></h3>
                        <div id="typingIndicator" class="typing"></div>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="startCall('audio', this)" title="Voice Call"><i class="ri-phone-line"></i></button>
                    <button class="btn-icon" onclick="startCall('video', this)" title="Video Call"><i class="ri-vidicon-line"></i></button>
                    <button class="btn-icon" onclick="toggleMenu()"><i class="ri-more-2-fill"></i></button>
                </div>
            </div>
            <div class="messages-box" id="msgBox"></div>
            <div class="input-area">
                <label for="fileUpload"><i class="ri-attachment-2 btn-icon"></i></label>
                <input type="file" id="fileUpload" hidden onchange="handleFileUpload(this)">
                <input type="text" id="msgInput" placeholder="Type your message..." onkeypress="if(event.key === 'Enter') sendMessage()">
                <button class="btn-send" onclick="sendMessage()"><i class="ri-send-plane-fill"></i></button>
            </div>
            <div id="videoOverlay"><div class="video-overlay-header"><h3 style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;"><i class="ri-record-circle-fill" style="color:#ef4444; font-size:0.9rem;"></i> <span id="callTypeLabel">Live Meeting</span></h3><button onclick="closeCall()" style="background:#ef4444; border:none; color:white; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px;"><i class="ri-phone-x-line"></i> Leave Call</button></div><div id="jitsiContainer"></div></div>
        `;

        // Typing listener
        let msgInput = document.getElementById('msgInput');
        msgInput.addEventListener('input', function() {
            if(activeConvId) {
                startTyping();
                clearTimeout(typingTimer);
                typingTimer = setTimeout(stopTyping, 2500);
            }
        });
        msgInput.addEventListener('blur', stopTyping);

        fetchMessages(true); 
        loadSidebar(); 
        startSmartPolling();
    }

    function fetchMessages(isInitialLoad = false) {
        if(!activeConvId || isFetching) return;
        isFetching = true;

        let fd = new FormData();
        fd.append('action', 'get_messages');
        fd.append('conversation_id', activeConvId);
        fd.append('last_msg_id', lastFetchedMsgId); 

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            isFetching = false; 
            let msgs = data.messages;
            let info = data.info;
            let typing = data.typing || [];
            let box = document.getElementById('msgBox');
            if(!box) return;

            // Update typing indicator
            let typingDiv = document.getElementById('typingIndicator');
            if(typing.length > 0) {
                typingDiv.textContent = typing.join(', ') + (typing.length === 1 ? ' is typing...' : ' are typing...');
            } else {
                typingDiv.textContent = '';
            }

            if(isInitialLoad && info) {
                let headerHTML = `
                    <div style="display:flex; align-items:center; gap:15px;">
                        <img src="${info.profile_img}" class="avatar" style="width:42px;height:42px;">
                        <div>
                            <h3 style="font-size:1.1rem; color:var(--text-main); margin:0;">${info.display_name}</h3>
                            <div id="typingIndicator" class="typing"></div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn-icon" onclick="startCall('audio', this)" title="Voice Call"><i class="ri-phone-line"></i></button>
                        <button class="btn-icon" onclick="startCall('video', this)" title="Video Call"><i class="ri-vidicon-line"></i></button>
                        <button class="btn-icon" onclick="toggleMenu()"><i class="ri-more-2-fill"></i></button>
                    </div>
                `;
                document.getElementById('chatHeader').innerHTML = headerHTML;
                box.innerHTML = '';
            }

            if (msgs.length > 0) {
                let html = '';
                msgs.forEach(m => {
                    lastFetchedMsgId = Math.max(lastFetchedMsgId, m.id);
                    let cls = m.is_me ? 'outgoing' : 'incoming';
                    let content = m.message;
                    let ticks = '';
                    if (m.is_me) {
                        if (m.read_status === 2) ticks = '<span class="ticks" style="color:#22c55e;">✓✓</span>';
                        else if (m.read_status === 1) ticks = '<span class="ticks" style="color:#94a3b8;">✓</span>';
                    }
                    if(m.message_type === 'call') {
                        let raw = m.message;
                        let callType = raw.startsWith('audio:') ? 'audio' : 'video';
                        let meetId = raw.replace(/^(audio|video):/i, '');
                        let label = callType === 'audio' ? 'Voice Call' : 'Video Meeting';
                        html += `<div class="msg-wrapper ${cls}"><div class="msg call-msg"><i class="${callType==='audio'?'ri-phone-fill':'ri-vidicon-fill'}" style="font-size:1.5rem;margin-bottom:5px;display:block;"></i><strong>${label}</strong><br><button onclick="openEmbeddedMeeting('${meetId}','${callType}')" class="join-btn">Join</button><div class="msg-time">${m.time}</div></div></div>`;
                    } else {
                        if(m.message_type === 'image') content = `<img src="${m.attachment_path}">`;
                        else if(m.message_type === 'file') content = `<a href="${m.attachment_path}" target="_blank" style="color:inherit;font-weight:600;">📎 File Attachment</a>`;
                        let senderName = (!m.is_me && info && info.is_group) ? `<div style="font-size:0.75rem;color:var(--primary-color);margin-bottom:4px;">${m.display_name}</div>` : '';
                        html += `<div class="msg-wrapper ${cls}"><div class="msg ${cls}">${senderName}${content}${ticks}<div class="msg-time">${m.time}</div></div></div>`;
                    }
                });
                box.insertAdjacentHTML('beforeend', html);
                if (isInitialLoad || !isUserScrolling) box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            } else if (isInitialLoad) {
                box.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-secondary);">👋 Say hello and start the conversation!</div>';
            }
        }).catch(() => isFetching = false);
    }

    function sendMessage() {
        let input = document.getElementById('msgInput');
        let txt = input.value.trim();
        if(!txt) return;

        let box = document.getElementById('msgBox');
        let tempId = Date.now();
        box.insertAdjacentHTML('beforeend', `<div class="msg-wrapper outgoing" id="temp-${tempId}"><div class="msg outgoing"> ${txt} <span class="ticks" style="color:#94a3b8;">✓</span><div class="msg-time">sending...</div></div></div>`);
        box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });

        let fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('conversation_id', activeConvId);
        fd.append('message', txt);
        fd.append('type', 'text');

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(() => {
            document.getElementById(`temp-${tempId}`).remove();
            fetchMessages(false);
        });
        input.value = '';
        stopTyping();
    }

    function handleFileUpload(input) {
        if(input.files.length) sendMessage(); // file handling can be expanded
    }

    function toggleMenu() {
        alert('Menu coming soon (clear/delete chat)');
    }

    function startSmartPolling() {
        if(pollInterval) clearInterval(pollInterval);
        let tick = 0;
        pollInterval = setInterval(() => {
            if (document.hidden) return;
            fetchMessages(false);
            if (++tick % 3 === 0) loadSidebar();
        }, 4000);
    }

    // Group modal functions (same as original)
    function openGroupModal() { 
        document.getElementById('groupModal').style.display = 'flex'; 
        selectedMembers.clear(); selectedMembersData.clear();
        document.getElementById('groupName').value = ''; 
        document.getElementById('memberSearch').value = '';
        searchForGroup(''); 
    }
    function closeGroupModal() { document.getElementById('groupModal').style.display = 'none'; }

    function searchForGroup(val) {
        let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val); 
        fetch('team_chat.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedMembers.has(u.id) ? 'selected' : '';
                let safeName = u.display_name.replace(/'/g, "\\'");
                html += `<div class="user-option ${isSel}" onclick="toggleMember(${u.id}, '${safeName}', this)" style="padding:10px 15px;display:flex;align-items:center;gap:12px;cursor:pointer;border-bottom:1px solid #f1f5f9;">`;
                html += `<img src="${u.profile_img}" style="width:30px;height:30px;border-radius:50%;"><div style="font-weight:600;">${u.display_name}</div></div>`;
            });
            document.getElementById('groupUserList').innerHTML = html || '<div style="padding:15px;text-align:center;color:#888;">No members found</div>';
        });
    }

    function toggleMember(uid, name, el) {
        if(selectedMembers.has(uid)) {
            selectedMembers.delete(uid); selectedMembersData.delete(uid);
            if(el) el.classList.remove('selected');
        } else {
            selectedMembers.add(uid); selectedMembersData.set(uid, name);
            if(el) el.classList.add('selected');
        }
        // render tags if needed
    }

    function createGroup() {
        let name = document.getElementById('groupName').value.trim();
        if(!name || selectedMembers.size === 0) return alert('Group name and members required');
        let fd = new FormData();
        fd.append('action', 'create_group');
        fd.append('group_name', name);
        fd.append('members', JSON.stringify(Array.from(selectedMembers)));
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeGroupModal();
                loadConversation(data.conversation_id, true);
            }
        });
    }

    // Call functions (same)
    function startCall(type) {
        if(!activeConvId) return;
        let meetId = 'Workack-' + Date.now().toString(36) + '-' + Math.random().toString(36).substring(2,8);
        let fd = new FormData();
        fd.append('action', 'start_call');
        fd.append('conversation_id', activeConvId);
        fd.append('call_type', type);
        fd.append('room_id', meetId);
        fetch('team_chat.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'ok') openEmbeddedMeeting(meetId, type);
        });
    }

    function openEmbeddedMeeting(roomId, type = 'video') {
        let overlay = document.getElementById('videoOverlay');
        overlay.style.display = 'flex';
        document.getElementById('callTypeLabel').textContent = type === 'audio' ? 'Voice Call' : 'Video Meeting';
        if(jitsiApi) jitsiApi.dispose();
        jitsiApi = new JitsiMeetExternalAPI('meet.jit.si', {
            roomName: roomId,
            width: '100%',
            height: '100%',
            parentNode: document.getElementById('jitsiContainer'),
            configOverwrite: { startWithVideoMuted: type==='audio', startAudioOnly: type==='audio' },
            userInfo: { displayName: myUserName }
        });
    }

    function closeCall() {
        if(jitsiApi) { jitsiApi.dispose(); jitsiApi = null; }
        document.getElementById('videoOverlay').style.display = 'none';
        let fd = new FormData(); fd.append('action', 'end_call_request'); fd.append('conversation_id', activeConvId);
        fetch('team_chat.php', { method: 'POST', body: fd });
    }

    // Incoming call polling
    setInterval(() => {
        if(document.hidden || currentIncomingCall) return;
        let fd = new FormData();
        fd.append('action', 'check_incoming_call');
        fd.append('conversation_id', activeConvId || 0);
        fetch('team_chat.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.has_call && !currentIncomingCall) {
                currentIncomingCall = data.call;
                document.getElementById('incomingCallerAvatar').src = data.call.caller_avatar;
                document.getElementById('incomingCallerName').textContent = data.call.display_label;
                document.getElementById('incomingCallLabel').textContent = data.call.call_type === 'audio' ? 'Voice Call' : 'Video Meeting';
                document.getElementById('incomingCallModal').style.display = 'flex';
            }
        });
    }, 3000);

    function acceptIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData(); fd.append('action', 'answer_call'); fd.append('call_id', currentIncomingCall.id);
        fetch('team_chat.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            document.getElementById('incomingCallModal').style.display = 'none';
            currentIncomingCall = null;
            if(data.room_id) openEmbeddedMeeting(data.room_id, data.call_type);
        });
    }

    function declineIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData(); fd.append('action', 'decline_call'); fd.append('call_id', currentIncomingCall.id);
        fetch('team_chat.php', { method: 'POST', body: fd });
        document.getElementById('incomingCallModal').style.display = 'none';
        currentIncomingCall = null;
    }

    // Typing
    function startTyping() {
        let fd = new FormData();
        fd.append('action', 'start_typing');
        fd.append('conversation_id', activeConvId);
        fetch('team_chat.php', { method: 'POST', body: fd });
    }
    function stopTyping() {
        clearTimeout(typingTimer);
        let fd = new FormData();
        fd.append('action', 'stop_typing');
        fd.append('conversation_id', activeConvId);
        fetch('team_chat.php', { method: 'POST', body: fd });
    }

    // Start everything
    loadSidebar();
    startSmartPolling();

    // Search
    document.getElementById('userSearch').addEventListener('input', function(e) {
        let val = e.target.value.trim();
        let results = document.getElementById('searchResults');
        results.style.display = val.length < 2 ? 'none' : 'block';
        if (val.length < 2) return;
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            let fd = new FormData();
            fd.append('action', 'search_users');
            fd.append('term', val);
            fetch('team_chat.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                let html = data.map(u => `<div class="search-item" onclick="startChat(${u.id});"><img src="${u.profile_img}" style="width:35px;height:35px;border-radius:50%;"><div><div style="font-weight:600;">${u.display_name}</div><div style="font-size:0.75rem;color:var(--text-secondary);">${u.role}</div></div></div>`).join('');
                results.innerHTML = html || '<div style="padding:15px;text-align:center;color:#888;">No members found</div>';
            });
        }, 350);
    });

    function startChat(userId) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('userSearch').value = '';
        let fd = new FormData();
        fd.append('action', 'start_chat');
        fd.append('target_user_id', userId);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if(data.id) loadConversation(data.id, true); });
    }
</script>
</body>
</html>