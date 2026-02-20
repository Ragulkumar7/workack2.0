<?php
// team_chat.php - PROFESSIONAL ENTERPRISE EDITION (Optimized for Shared Hosting)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Database connection fallback
$dbPath = 'include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
elseif (file_exists('../include/db_connect.php')) { require_once '../include/db_connect.php'; } 
else { die("Database connection missing."); }

// --- CRITICAL FIX: FORCE CONNECTION CLOSURE ---
// This ensures that even when the script uses 'exit;', the connection is killed, 
// preventing "Sleep" processes from eating your 500/hour quota.
register_shutdown_function(function() use ($conn) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_close($conn);
    }
});

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$my_id = $_SESSION['user_id'];
$my_role = trim($_SESSION['role'] ?? 'Employee');
$my_username = $_SESSION['username'] ?? 'User';

// Role Check: Only certain roles can create groups
$can_create_group = in_array($my_role, ['Manager', 'Team Lead', 'System Admin', 'HR', 'HR Executive']);

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
    return $encryptedText; // Fallback if unencrypted
}

// Ensure database schema is up-to-date silently
if (!isset($_SESSION['chat_db_checked'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS call_requests (id INT AUTO_INCREMENT PRIMARY KEY, conversation_id INT NOT NULL, caller_id INT NOT NULL, room_id VARCHAR(64) NOT NULL, call_type ENUM('audio','video') DEFAULT 'video', status ENUM('ringing','answered','declined','ended') DEFAULT 'ringing', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE IF NOT EXISTS message_reads (message_id INT NOT NULL, user_id INT NOT NULL, read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (message_id, user_id)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE IF NOT EXISTS typing_status (conversation_id INT NOT NULL, user_id INT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (conversation_id, user_id)) ENGINE=InnoDB");
    
    // Ensure necessary columns exist
    $conn->query("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS edited_at DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE chat_participants ADD COLUMN IF NOT EXISTS muted_until DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE chat_participants ADD COLUMN IF NOT EXISTS hidden_at DATETIME NULL DEFAULT NULL");
    
    $_SESSION['chat_db_checked'] = true;
}

// =========================================================================================
// AJAX HANDLERS
// =========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_write_close(); // Prevent session locking during polling
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
            if(empty($row['profile_img']) || $row['profile_img'] == 'default_user.png') {
                $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
            } elseif(!str_starts_with($row['profile_img'], 'http')) {
                $row['profile_img'] = (file_exists('../assets/profiles/'.$row['profile_img']) ? '../assets/profiles/' : 'assets/profiles/') . $row['profile_img'];
            }
            $users[] = $row; 
        }
        echo json_encode($users); exit;
    }

    // 2. CREATE GROUP
    if ($action === 'create_group' && $can_create_group) {
        $group_name = trim($_POST['group_name'] ?? '');
        $members = json_decode($_POST['members'] ?? '[]', true);
        
        if (empty($group_name) || !is_array($members) || empty($members)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data.']); exit;
        }
        
        $members = array_filter(array_unique(array_map('intval', $members)));
        $stmt = $conn->prepare("INSERT INTO chat_conversations (type, group_name, created_by) VALUES ('group', ?, ?)");
        $stmt->bind_param("si", $group_name, $my_id);
        $stmt->execute();
        $conv_id = $conn->insert_id;
        
        $stmt_part = $conn->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
        $stmt_part->bind_param("ii", $conv_id, $my_id); $stmt_part->execute();
        foreach ($members as $uid) {
            $stmt_part->bind_param("ii", $conv_id, $uid); $stmt_part->execute();
        }

        $sys_msg = encryptChatMessage("Group '" . $group_name . "' created.");
        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$sys_msg', 'text')");

        echo json_encode(['status' => 'success', 'conversation_id' => $conv_id]); exit;
    }

    // 3. GET RECENT CHATS (Optimized query for sidebar)
    if ($action === 'get_recent_chats') {
        $sql = "
            SELECT 
                c.id AS conversation_id, c.type, c.group_name, cp.muted_until,
                cm.message AS last_msg, cm.message_type, cm.created_at AS time, cm.deleted_at,
                (SELECT COUNT(m.id) FROM chat_messages m LEFT JOIN message_reads r ON m.id = r.message_id AND r.user_id = ? WHERE m.conversation_id = c.id AND m.sender_id != ? AND r.message_id IS NULL AND m.deleted_at IS NULL) AS unread,
                IF(c.type = 'group', c.group_name, COALESCE(ep.full_name, u.username, 'Unknown User')) AS name,
                ep.profile_img AS avatar_db
            FROM chat_conversations c
            INNER JOIN chat_participants cp ON c.id = cp.conversation_id AND cp.user_id = ? AND cp.hidden_at IS NULL
            LEFT JOIN chat_messages cm ON cm.id = (SELECT MAX(id) FROM chat_messages m2 WHERE m2.conversation_id = c.id)
            LEFT JOIN chat_participants cp2 ON c.type = 'direct' AND cp2.conversation_id = c.id AND cp2.user_id != ?
            LEFT JOIN users u ON cp2.user_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id
            ORDER BY COALESCE(cm.created_at, c.created_at) DESC LIMIT 50
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $my_id, $my_id, $my_id, $my_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $chats = [];
        while($row = $result->fetch_assoc()) {
            if ($row['type'] == 'group') {
                $row['avatar'] = "https://ui-avatars.com/api/?name=".urlencode($row['group_name'])."&background=1b5a5a&color=fff";
            } else {
                if(empty($row['avatar_db']) || $row['avatar_db'] == 'default_user.png') {
                    $row['avatar'] = "https://ui-avatars.com/api/?name=".urlencode($row['name'])."&background=1b5a5a&color=fff";
                } elseif(!str_starts_with($row['avatar_db'], 'http')) {
                    $row['avatar'] = (file_exists('../assets/profiles/'.$row['avatar_db']) ? '../assets/profiles/' : 'assets/profiles/') . $row['avatar_db'];
                } else {
                    $row['avatar'] = $row['avatar_db'];
                }
            }
            
            if ($row['deleted_at'] != null) $row['last_msg'] = '🚫 This message was deleted';
            else if ($row['message_type'] == 'text') $row['last_msg'] = decryptChatMessage($row['last_msg']);
            else if ($row['message_type'] == 'call') $row['last_msg'] = (stripos($row['last_msg'], 'audio:') === 0) ? '📞 Voice call' : '📹 Video meeting';
            else if ($row['message_type'] == 'image') $row['last_msg'] = '🖼️ Photo';
            else if ($row['message_type'] == 'file') $row['last_msg'] = '📎 Attachment';

            $row['time'] = $row['time'] ? date('h:i A', strtotime($row['time'])) : '';
            $chats[] = $row;
        }
        echo json_encode($chats); exit;
    }

    // 4. GET MESSAGES (Delta fetching using last_msg_id)
    if ($action === 'get_messages') {
        $conv_id = (int)$_POST['conversation_id'];
        $last_msg_id = isset($_POST['last_msg_id']) ? (int)$_POST['last_msg_id'] : 0;

        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $chk->bind_param("ii", $conv_id, $my_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['messages' => [], 'info' => null]); exit; }
        
        // Mark as read only if checking for new messages
        $conn->query("INSERT IGNORE INTO message_reads (message_id, user_id) SELECT id, $my_id FROM chat_messages WHERE conversation_id = $conv_id AND sender_id != $my_id AND deleted_at IS NULL");

        $sql = "SELECT m.*, COALESCE(ep.full_name, u.username) as display_name,
                       (SELECT COUNT(*) FROM message_reads r WHERE r.message_id = m.id) AS read_count
                FROM chat_messages m JOIN users u ON m.sender_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $conv_id, $last_msg_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $msgs = [];
        while($row = $res->fetch_assoc()) {
            $row['is_me'] = ($row['sender_id'] == $my_id);
            $row['time'] = date('h:i A', strtotime($row['created_at']));
            $row['is_deleted'] = ($row['deleted_at'] != null);
            $row['is_edited'] = ($row['edited_at'] != null && !$row['is_deleted']);
            
            if ($row['is_deleted']) {
                $row['message'] = "🚫 This message was deleted.";
                $row['message_type'] = 'deleted';
            } elseif ($row['message_type'] == 'text') {
                $row['message'] = decryptChatMessage($row['message']);
            }
            $row['read_status'] = $row['is_me'] ? ($row['read_count'] > 0 ? 2 : 1) : 0;
            $msgs[] = $row; 
        }

        // Fetch typing status
        $typing_users = [];
        $typing_res = $conn->query("SELECT COALESCE(ep.full_name, u.username) as typing_name FROM typing_status ts JOIN users u ON ts.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE ts.conversation_id = $conv_id AND ts.user_id != $my_id AND ts.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        while ($t = $typing_res->fetch_assoc()) $typing_users[] = $t['typing_name'];

        // Partner info (only send if initial load to save bandwidth)
        $partner = null;
        if ($last_msg_id == 0) {
            $conv_info = $conn->query("SELECT * FROM chat_conversations WHERE id = $conv_id")->fetch_assoc();
            if ($conv_info['type'] == 'direct') {
                $p_stmt = $conn->prepare("SELECT COALESCE(ep.full_name, u.username) as display_name, u.role, ep.profile_img FROM chat_participants cp JOIN users u ON cp.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE cp.conversation_id = ? AND cp.user_id != ? LIMIT 1");
                $p_stmt->bind_param("ii", $conv_id, $my_id); $p_stmt->execute();
                $partner = $p_stmt->get_result()->fetch_assoc() ?: ['display_name' => 'Unknown User', 'role' => '', 'profile_img' => ''];
                if(empty($partner['profile_img']) || $partner['profile_img'] == 'default_user.png') {
                    $partner['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($partner['display_name'])."&background=random";
                } elseif(!str_starts_with($partner['profile_img'], 'http')) {
                    $partner['profile_img'] = (file_exists('../assets/profiles/'.$partner['profile_img']) ? '../assets/profiles/' : 'assets/profiles/') . $partner['profile_img'];
                }
            } else {
                $partner = ['display_name' => $conv_info['group_name'], 'role' => 'Group Chat', 'is_group' => true, 'profile_img' => "https://ui-avatars.com/api/?name=".urlencode($conv_info['group_name'])."&background=1b5a5a&color=fff"];
            }
        }

        echo json_encode(['messages' => $msgs, 'info' => $partner, 'typing' => $typing_users]); exit;
    }

    // 5. SEND MESSAGE
    if ($action === 'send_message') {
        $conv_id = (int)$_POST['conversation_id'];
        $msg_text = $_POST['message'] ?? '';
        $msg_type = $_POST['type'] ?? 'text'; 
        $attachment = null;

        // Unhide conversation for all participants when a new message is sent
        $conn->query("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = $conv_id");

        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $target_dir = "uploads/chat/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $fname = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $fname);
            $attachment = $target_dir . $fname;
            $msg_type = in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
            if(!$msg_text) $msg_text = $_FILES['file']['name'];
        }

        if ($msg_type === 'text') { $msg_text = encryptChatMessage($msg_text); }
        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, attachment_path, message_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $conv_id, $my_id, $msg_text, $attachment, $msg_type);
        $stmt->execute();
        echo json_encode(['status' => 'sent']); exit;
    }

    // 6. EDIT MESSAGE
    if ($action === 'edit_message') {
        $msg_id = (int)$_POST['message_id'];
        $new_text = encryptChatMessage($_POST['new_text']);
        $stmt = $conn->prepare("UPDATE chat_messages SET message = ?, edited_at = NOW() WHERE id = ? AND sender_id = ? AND deleted_at IS NULL AND message_type = 'text'");
        $stmt->bind_param("sii", $new_text, $msg_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }

    // 7. DELETE MESSAGE
    if ($action === 'delete_message') {
        $msg_id = (int)$_POST['message_id'];
        $stmt = $conn->prepare("UPDATE chat_messages SET deleted_at = NOW() WHERE id = ? AND sender_id = ?");
        $stmt->bind_param("ii", $msg_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }

    // 8. CLEAR/DELETE CHAT
    if ($action === 'clear_chat' || $action === 'delete_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("UPDATE chat_participants SET hidden_at = NOW() WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }

    // 9. START CHAT (Direct)
    if ($action === 'start_chat') {
        $target = (int)$_POST['target_user_id'];
        $sql = "SELECT c.id FROM chat_conversations c JOIN chat_participants cp1 ON c.id = cp1.conversation_id JOIN chat_participants cp2 ON c.id = cp2.conversation_id WHERE c.type = 'direct' AND cp1.user_id = ? AND cp2.user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $my_id, $target); $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $conn->query("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = {$row['id']} AND user_id = $my_id");
            echo json_encode(['status' => 'success', 'id' => $row['id']]);
        } else {
            $conn->query("INSERT INTO chat_conversations (type) VALUES ('direct')");
            $new_id = $conn->insert_id;
            $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($new_id, $my_id), ($new_id, $target)");
            echo json_encode(['status' => 'success', 'id' => $new_id]);
        }
        exit;
    }

    // 10. CALL HANDLING
    if ($action === 'start_call') {
        $conv_id = (int)$_POST['conversation_id'];
        $call_type = ($_POST['call_type'] ?? 'video') === 'audio' ? 'audio' : 'video';
        $room_id = 'Workack-Call-' . substr(md5(uniqid()), 0, 10);
        
        $store_value = ($call_type === 'audio') ? 'audio:' . $room_id : $room_id;
        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$store_value', 'call')");
        $conn->query("INSERT INTO call_requests (conversation_id, caller_id, room_id, call_type, status) VALUES ($conv_id, $my_id, '$room_id', '$call_type', 'ringing')");
        
        echo json_encode(['status' => 'ok', 'room_id' => $room_id, 'call_type' => $call_type]); exit;
    }
    
    if ($action === 'check_incoming_call') {
        $conv_id = (int)($_POST['conversation_id'] ?? 0);
        $sql = "SELECT cr.id, cr.conversation_id, cr.room_id, cr.call_type, COALESCE(ep.full_name, u.username) as caller_name, ep.profile_img as caller_avatar, c.type as conv_type, c.group_name 
                FROM call_requests cr JOIN chat_participants cp ON cr.conversation_id = cp.conversation_id AND cp.user_id = ? 
                JOIN users u ON cr.caller_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                JOIN chat_conversations c ON cr.conversation_id = c.id 
                WHERE cr.caller_id != ? AND cr.status = 'ringing' AND cr.created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) ORDER BY cr.created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $my_id, $my_id); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            if (empty($row['caller_avatar']) || $row['caller_avatar'] == 'default_user.png') {
                $row['caller_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($row['caller_name']) . "&background=random";
            } elseif(!str_starts_with($row['caller_avatar'], 'http')) {
                $row['caller_avatar'] = (file_exists('../assets/profiles/'.$row['caller_avatar']) ? '../assets/profiles/' : 'assets/profiles/') . $row['caller_avatar'];
            }
            $row['display_label'] = $row['conv_type'] === 'group' ? $row['group_name'] . ' – ' . $row['caller_name'] : $row['caller_name'];
            echo json_encode(['has_call' => true, 'call' => $row]);
        } else { echo json_encode(['has_call' => false]); }
        exit;
    }
    
    if ($action === 'answer_call' || $action === 'decline_call' || $action === 'end_call_request') {
        $stat = ($action === 'answer_call') ? 'answered' : (($action === 'decline_call') ? 'declined' : 'ended');
        if(isset($_POST['call_id'])) $conn->query("UPDATE call_requests SET status = '$stat' WHERE id = " . (int)$_POST['call_id']);
        if(isset($_POST['conversation_id'])) $conn->query("UPDATE call_requests SET status = '$stat' WHERE conversation_id = " . (int)$_POST['conversation_id']);
        if ($action === 'answer_call') {
            $row = $conn->query("SELECT room_id, call_type FROM call_requests WHERE id = " . (int)$_POST['call_id'])->fetch_assoc();
            echo json_encode(['status' => 'ok', 'room_id' => $row['room_id'], 'call_type' => $row['call_type']]); exit;
        }
        echo json_encode(['status' => 'ok']); exit;
    }

    // TYPING INDICATORS
    if ($action === 'start_typing') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("INSERT INTO typing_status (conversation_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");
        $stmt->bind_param("ii", $conv_id, $my_id); $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }
    if ($action === 'stop_typing') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("DELETE FROM typing_status WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id); $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --primary: #1b5a5a;
            --primary-hover: #144343;
            --bg-light: #f8fafc; 
            --border: #e2e8f0; 
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
        body { background-color: var(--bg-light); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        #mainContent { margin-left: 95px; width: calc(100% - 95px); height: 100vh; display: flex; flex-direction: column; transition: all 0.3s; }
        .app-container { flex: 1; display:flex; height: 0; min-height: 0; background: #fff; border-top: 1px solid var(--border); position: relative;}
        
        /* SIDEBAR */
        .sidebar { width: 340px; background: #fff; border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 10; transition: transform 0.3s ease;}
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content:space-between; align-items:center; }
        .search-box { padding: 15px; border-bottom: 1px solid #f1f5f9; background: var(--bg-light); position: relative;}
        .search-box input { width: 100%; padding: 10px 15px 10px 35px; border: 1px solid var(--border); border-radius: 8px; outline: none; }
        .search-box i { position: absolute; left: 25px; top: 25px; color: var(--text-muted); }
        
        .chat-list { flex: 1; overflow-y: auto; }
        .chat-list::-webkit-scrollbar { width: 4px; }
        .chat-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        .chat-item { display: flex; align-items: center; padding: 15px; cursor: pointer; border-bottom: 1px solid #f8fafc; transition: 0.2s;}
        .chat-item:hover { background: #f1f5f9; }
        .chat-item.active { background: #f0fdfa; border-right: 4px solid var(--primary); }
        .avatar { width: 45px; height: 45px; border-radius: 50%; margin-right: 12px; object-fit: cover; border: 1px solid var(--border);}
        
        #searchResults { position: absolute; top: 60px; left: 15px; width: calc(100% - 30px); background: white; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; display: none; max-height: 300px; overflow-y: auto;}
        .search-item { padding: 12px; cursor: pointer; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9;}
        .search-item:hover { background: #f8fafc; }

        /* CHAT AREA */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #efeae2; position: relative;}
        .chat-area::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.05; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%231b5a5a' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .chat-header { height: 70px; background: white; border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 20px; justify-content: space-between; z-index: 10;}
        .header-actions { display: flex; gap: 8px; align-items: center; position: relative;}
        .btn-icon { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #f1f5f9; border:none; cursor: pointer; color: var(--text-dark); transition: 0.2s;}
        .btn-icon:hover { background: #e2e8f0; color: var(--primary); }
        
        .messages-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; z-index: 5;}
        .messages-box::-webkit-scrollbar { width: 6px; }
        .messages-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        .msg-wrapper { display: flex; flex-direction: column; max-width: 65%; position: relative;}
        .msg-wrapper.incoming { align-self: flex-start; }
        .msg-wrapper.outgoing { align-self: flex-end; }
        
        .msg { padding: 10px 14px; border-radius: 12px; font-size: 0.95rem; line-height: 1.4; word-wrap: break-word; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.05);}
        .msg.incoming { background: #ffffff; border-top-left-radius: 0; }
        .msg.outgoing { background: #dcfce7; border-top-right-radius: 0; }
        .msg.deleted { font-style: italic; color: var(--text-muted); background: #f1f5f9; border: 1px solid var(--border); box-shadow: none;}
        
        .msg-meta { display: flex; justify-content: flex-end; align-items: center; gap: 5px; font-size: 0.7rem; color: #888; margin-top: 4px;}
        .ticks { font-size: 0.9rem; margin-left: 2px;}
        .tick-read { color: #3b82f6; }
        .tick-sent { color: #94a3b8; }
        
        /* Message Dropdown */
        .msg-menu-btn { position: absolute; top: 5px; right: 5px; background: transparent; border: none; color: #94a3b8; cursor: pointer; opacity: 0; transition: opacity 0.2s; padding: 2px 5px;}
        .msg-wrapper:hover .msg-menu-btn { opacity: 1; }
        .msg-dropdown { position: absolute; top: 25px; right: 10px; background: white; border: 1px solid var(--border); border-radius: 6px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); z-index: 50; display: none; overflow: hidden; min-width: 120px;}
        .msg-dropdown button { width: 100%; text-align: left; padding: 10px 15px; border: none; background: white; cursor: pointer; font-size: 0.85rem; transition: 0.2s;}
        .msg-dropdown button:hover { background: #f1f5f9; }
        .msg-dropdown button.delete-btn:hover { color: #ef4444; }

        /* Header Dropdown */
        #chatOptionsDropdown { position: absolute; top: 45px; right: 0; background: white; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); z-index: 50; display: none; min-width: 180px; overflow: hidden;}
        #chatOptionsDropdown button { width: 100%; text-align: left; padding: 12px 15px; border: none; background: white; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;}
        #chatOptionsDropdown button:hover { background: #f1f5f9; color: #ef4444;}

        .input-area { padding: 15px 20px; background: #f0f2f5; display: flex; align-items: center; gap: 15px; z-index: 10;}
        .input-area input { flex: 1; padding: 14px 20px; border: none; border-radius: 24px; outline: none; background: #fff; font-size: 0.95rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);}
        .btn-send { background: var(--primary); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: 0.2s;}
        .btn-send:hover { background: var(--primary-hover); transform: scale(1.05);}

        /* OVERLAYS */
        #videoOverlay { display:none; position:absolute; top:70px; left:0; width:100%; height:calc(100% - 70px); background:#0f172a; z-index:200; flex-direction:column; }
        .video-overlay-header { padding: 12px 20px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; color: white; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.7); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(3px);}
        .modal { background: white; width: 400px; border-radius: 12px; padding: 25px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);}
        
        .incoming-call-box { background: white; border-radius: 16px; padding: 30px; text-align: center; min-width: 320px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: pulse 2s infinite;}
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); } 70% { box-shadow: 0 0 0 20px rgba(34, 197, 94, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
        
        /* Edit Mode Bar */
        #editModeBar { display: none; background: #f1f5f9; padding: 10px 20px; border-top: 1px solid var(--border); align-items: center; justify-content: space-between; font-size: 0.85rem; color: var(--primary); z-index:10;}

        /* Mobile Adjustments */
        #mobileBackBtn { display: none; }
        @media (max-width: 992px) {
            #mainContent { margin-left: 0; width: 100%; }
            .sidebar { width: 100%; position: absolute; height: 100%; z-index: 20; }
            .chat-area { width: 100%; }
            .sidebar.hide-mobile { transform: translateX(-100%); }
            #mobileBackBtn { display: flex; }
        }
    </style>
</head>
<body>

<?php if(file_exists('sidebars.php')) include 'sidebars.php'; elseif(file_exists('../sidebars.php')) include '../sidebars.php'; ?>

<main id="mainContent">
    <?php if(file_exists('header.php')) include 'header.php'; elseif(file_exists('../header.php')) include '../header.php'; ?>
    
    <div class="app-container">
        <aside class="sidebar" id="chatSidebar">
            <div class="sidebar-header">
                <h2 style="font-weight:800; color:var(--text-dark); font-size: 1.3rem;">Messages</h2>
                <?php if($can_create_group): ?>
                    <button onclick="openGroupModal()" title="Create Group" class="btn-icon"><i class="ri-group-line"></i></button>
                <?php endif; ?>
            </div>
            <div class="search-box">
                <i class="ri-search-line"></i>
                <input type="text" id="userSearch" placeholder="Search or start new chat...">
                <div id="searchResults"></div>
            </div>
            <div class="chat-list" id="chatList">
                <div style="text-align:center; padding: 30px; color:var(--text-muted);"><i class="ri-loader-4-line ri-spin"></i> Loading...</div>
            </div>
        </aside>

        <section class="chat-area" id="chatArea">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); text-align:center; padding:20px; z-index:5;">
                <div style="width: 100px; height: 100px; background: #e2e8f0; border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 20px;">
                    <i class="ri-message-3-fill" style="font-size:3rem; color: #94a3b8;"></i>
                </div>
                <h3 style="font-size: 1.5rem; color: var(--text-dark); margin-bottom: 8px;">Workack Team Chat</h3>
                <p>Send and receive messages securely.<br>Select a conversation from the left to start.</p>
            </div>
        </section>
    </div>
</main>

<div id="incomingCallModal" class="modal-overlay">
    <div class="incoming-call-box">
        <img id="incomingCallerAvatar" src="" style="width:80px; height:80px; border-radius:50%; margin:0 auto 15px auto; object-fit:cover; border:3px solid #22c55e;">
        <h3 id="incomingCallerName" style="font-size:1.2rem; margin-bottom:5px;">Incoming Call</h3>
        <p id="incomingCallLabel" style="color:var(--text-muted); font-size:0.9rem;">Video Call</p>
        <div style="display:flex; gap:16px; justify-content:center; margin-top:25px;">
            <button onclick="declineIncomingCall()" style="background:#ef4444; color:white; width:50px; height:50px; border-radius:50%; border:none; cursor:pointer; font-size:1.5rem;"><i class="ri-phone-fill"></i></button>
            <button onclick="acceptIncomingCall()" style="background:#22c55e; color:white; width:50px; height:50px; border-radius:50%; border:none; cursor:pointer; font-size:1.5rem; animation: jump 1s infinite;"><i class="ri-phone-fill"></i></button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="groupModal">
    <div class="modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h3 style="font-size:1.2rem;">Create Group</h3>
            <i class="ri-close-line" style="font-size:1.5rem; cursor:pointer; color:var(--text-muted);" onclick="closeGroupModal()"></i>
        </div>
        <input type="text" id="groupName" placeholder="Group Subject" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:15px; outline:none;">
        <input type="text" id="memberSearch" placeholder="Search members to add..." oninput="searchForGroup(this.value)" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; margin-bottom:10px; outline:none; font-size:0.9rem;">
        <div id="groupUserList" style="max-height:200px; overflow-y:auto; border:1px solid var(--border); border-radius:8px; margin-bottom:15px;"></div>
        <button onclick="createGroup()" style="width:100%; padding:12px; background:var(--primary); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Create Group</button>
    </div>
</div>

<script>
    let activeConvId = null;
    let editingMsgId = null;
    let masterPollInterval = null; // --- FIX: Merged Interval ---
    let isFetchingMessages = false;
    let isSidebarFetching = false;
    let lastFetchedMsgId = 0;
    let isUserScrolling = false;
    let selectedMembers = new Set();
    let jitsiApi = null;
    const myUserName = "<?php echo htmlspecialchars($my_username, ENT_QUOTES, 'UTF-8'); ?>";
    let currentIncomingCall = null;
    let searchDebounce = null;
    let typingTimer = null;

    // --- Responsive Layout Logic ---
    function setupLayoutObserver() {
        const primarySidebar = document.querySelector('.sidebar-primary');
        const secondarySidebar = document.querySelector('.sidebar-secondary');
        const mainContent = document.getElementById('mainContent');
        if (!primarySidebar || !mainContent) return;

        const updateMargin = () => {
            if (window.innerWidth <= 992) {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
                return;
            }
            let totalWidth = primarySidebar.offsetWidth;
            if (secondarySidebar && secondarySidebar.classList.contains('open')) {
                totalWidth += secondarySidebar.offsetWidth;
            }
            mainContent.style.marginLeft = totalWidth + 'px';
            mainContent.style.width = `calc(100% - ${totalWidth}px)`;
        };

        new ResizeObserver(() => updateMargin()).observe(primarySidebar);
        if (secondarySidebar) {
            new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] });
        }
        window.addEventListener('resize', updateMargin);
        updateMargin();
    }
    document.addEventListener('DOMContentLoaded', setupLayoutObserver);

    function toggleMobileSidebar() {
        if(window.innerWidth <= 992) {
            const sb = document.getElementById('chatSidebar');
            if(activeConvId) sb.classList.add('hide-mobile');
            else sb.classList.remove('hide-mobile');
        }
    }
    window.addEventListener('resize', toggleMobileSidebar);

    // --- Core Chat Logic ---
    function loadSidebar() {
        if(isSidebarFetching) return;
        isSidebarFetching = true;

        let fd = new FormData();
        fd.append('action', 'get_recent_chats');
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            isSidebarFetching = false;
            let html = '';
            data.forEach(c => {
                let active = (c.conversation_id == activeConvId) ? 'active' : '';
                let unread = c.unread > 0 ? `<span style="background:#22c55e; color:white; font-size:0.7rem; font-weight:bold; padding:2px 6px; border-radius:10px;">${c.unread}</span>` : '';
                let msgText = c.last_msg || '';
                
                html += `<div class="chat-item ${active}" onclick="loadConversation(${c.conversation_id})">
                            <img src="${c.avatar}" class="avatar" loading="lazy">
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                    <div style="font-weight:600; color:var(--text-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.name}</div>
                                    <div style="font-size:0.7rem; color:var(--text-muted);">${c.time}</div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:80%; font-size:0.85rem; color:var(--text-muted); ${msgText.includes('🚫') ? 'font-style:italic;' : ''}">${msgText}</span>
                                    ${unread}
                                </div>
                            </div>
                        </div>`;
            });
            if(data.length === 0) html = '<div style="text-align:center; padding: 30px; color:var(--text-muted);">No active chats</div>';
            document.getElementById('chatList').innerHTML = html;
        })
        .catch(() => { isSidebarFetching = false; });
    }

    function loadConversation(convId) {
        if(activeConvId === convId) return; // Don't reload if already active
        
        activeConvId = convId;
        editingMsgId = null;
        lastFetchedMsgId = 0; // Reset delta fetch
        isUserScrolling = false;
        toggleMobileSidebar();

        document.getElementById('chatArea').innerHTML = `
            <div class="chat-header" id="chatHeader">
                <div style="display:flex; align-items:center; gap:15px;">
                    <button id="mobileBackBtn" class="btn-icon" onclick="backToList()"><i class="ri-arrow-left-line"></i></button>
                    <img src="" id="headerAvatar" class="avatar" style="width:42px;height:42px;margin:0;border:none;">
                    <div style="display:flex; flex-direction:column;">
                        <h3 id="headerName" style="font-size:1.05rem; color:var(--text-dark); margin:0; line-height:1.2;">Loading...</h3>
                        <span id="typingIndicator" style="font-size:0.75rem; color:var(--primary); height:14px; font-style:italic;"></span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="startCall('audio')" title="Voice Call"><i class="ri-phone-line"></i></button>
                    <button class="btn-icon" onclick="startCall('video')" title="Video Call"><i class="ri-vidicon-line"></i></button>
                    <button class="btn-icon" onclick="toggleHeaderMenu(event)"><i class="ri-more-2-fill"></i></button>
                    <div id="chatOptionsDropdown">
                        <button onclick="clearDeleteChat('clear')"><i class="ri-eraser-line"></i> Clear Chat</button>
                        <button onclick="clearDeleteChat('delete')"><i class="ri-delete-bin-line"></i> Delete Chat</button>
                    </div>
                </div>
            </div>
            
            <div class="messages-box" id="msgBox" onscroll="handleScroll()"></div>
            
            <div id="editModeBar">
                <div style="display:flex; align-items:center; gap:8px;"><i class="ri-edit-2-fill"></i> <span>Editing message</span></div>
                <i class="ri-close-line" style="cursor:pointer; font-size:1.2rem;" onclick="cancelEdit()"></i>
            </div>
            
            <div class="input-area">
                <label for="fileUpload" style="cursor:pointer;"><i class="ri-attachment-2 text-xl text-gray-500 hover:text-gray-700 transition"></i></label>
                <input type="file" id="fileUpload" hidden onchange="handleFileUpload(this)">
                <input type="text" id="msgInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') submitMessage()">
                <button class="btn-send" onclick="submitMessage()"><i class="ri-send-plane-fill"></i></button>
            </div>
            
            <div id="videoOverlay">
                <div class="video-overlay-header">
                    <h3 style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                        <i class="ri-record-circle-fill" style="color:#ef4444; font-size:0.9rem; animation: pulse 1s infinite;"></i> <span id="callTypeLabel">Live Meeting</span>
                    </h3>
                    <button onclick="closeCall()" style="background:#ef4444; border:none; color:white; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="ri-phone-x-line"></i> End
                    </button>
                </div>
                <div id="jitsiContainer" style="flex:1;"></div>
            </div>
        `;

        let msgInput = document.getElementById('msgInput');
        msgInput.addEventListener('input', function() {
            if(activeConvId) {
                startTyping();
                clearTimeout(typingTimer);
                typingTimer = setTimeout(stopTyping, 2000);
            }
        });
        msgInput.addEventListener('blur', stopTyping);

        fetchMessages(true); 
    }

    function backToList() {
        activeConvId = null;
        toggleMobileSidebar();
    }

    function handleScroll() {
        let box = document.getElementById('msgBox');
        isUserScrolling = (box.scrollHeight - box.scrollTop - box.clientHeight > 50);
    }

    function buildMessageHTML(m) {
        let cls = m.is_me ? 'outgoing' : 'incoming';
        let content = m.message;
        let isDeleted = m.is_deleted;
        let isEdited = m.is_edited;
        
        let metaHtml = `<div class="msg-meta">`;
        if (isEdited && !isDeleted) metaHtml += `<span style="font-size:0.65rem; font-style:italic; margin-right:4px;">Edited</span>`;
        metaHtml += `<span>${m.time}</span>`;
        
        if (m.is_me && !isDeleted) {
            let tickClass = (m.read_status === 2) ? 'tick-read' : 'tick-sent';
            let tickMark = (m.read_status === 2) ? '✓✓' : '✓';
            metaHtml += `<span class="ticks ${tickClass}">${tickMark}</span>`;
        }
        metaHtml += `</div>`;

        let menuHtml = '';
        if (m.is_me && !isDeleted && m.message_type === 'text') {
            menuHtml = `
                <button class="msg-menu-btn" onclick="toggleMsgMenu(event, ${m.id})"><i class="ri-arrow-down-s-line"></i></button>
                <div class="msg-dropdown" id="msg-drop-${m.id}">
                    <button onclick="initEdit(${m.id}, '${escapeHTML(m.message)}')"><i class="ri-pencil-line mr-2"></i> Edit</button>
                    <button onclick="deleteMessage(${m.id})" class="delete-btn"><i class="ri-delete-bin-line mr-2"></i> Delete</button>
                </div>
            `;
        }

        let innerMsg = '';
        if (isDeleted) {
            innerMsg = `<div class="msg deleted" id="msg-content-${m.id}">${content} ${metaHtml}</div>`;
        } else if (m.message_type === 'call') {
            let raw = m.message;
            let callType = raw.startsWith('audio:') ? 'audio' : 'video';
            let meetId = raw.replace(/^(audio|video):/i, '');
            let label = callType === 'audio' ? 'Voice Call' : 'Video Meeting';
            innerMsg = `<div class="msg ${cls}" id="msg-content-${m.id}" style="text-align:center;">
                            <div style="background:rgba(0,0,0,0.05); padding:10px; border-radius:8px; margin-bottom:8px;">
                                <i class="${callType==='audio'?'ri-phone-fill':'ri-vidicon-fill'}" style="font-size:1.5rem; color:var(--primary);"></i>
                                <br><strong style="font-size:0.9rem;">${label}</strong>
                            </div>
                            <button onclick="openEmbeddedMeeting('${meetId}','${callType}')" style="background:var(--primary); color:white; border:none; padding:6px 16px; border-radius:15px; cursor:pointer; font-size:0.8rem; width:100%;">Join</button>
                            ${metaHtml}
                        </div>`;
        } else {
            if(m.message_type === 'image') content = `<img src="${m.attachment_path}" style="max-width:100%; border-radius:8px; margin-bottom:5px;">`;
            else if(m.message_type === 'file') content = `<a href="${m.attachment_path}" target="_blank" style="display:flex; align-items:center; gap:8px; color:inherit; text-decoration:none; background:rgba(0,0,0,0.05); padding:8px; border-radius:6px;"><i class="ri-file-text-fill text-xl"></i> <span>Download File</span></a>`;
            
            let senderName = (!m.is_me && m.display_name) ? `<div style="font-size:0.7rem;color:#0d9488;margin-bottom:4px;font-weight:600;">${m.display_name}</div>` : '';
            innerMsg = `<div class="msg ${cls}" id="msg-content-${m.id}">${senderName}<span id="msg-text-${m.id}">${content}</span>${metaHtml}${menuHtml}</div>`;
        }

        return `<div class="msg-wrapper ${cls}" id="msg-${m.id}" data-id="${m.id}">${innerMsg}</div>`;
    }

    function fetchMessages(isInitialLoad = false) {
        if(!activeConvId || isFetchingMessages) return;
        isFetchingMessages = true;

        let fd = new FormData();
        fd.append('action', 'get_messages');
        fd.append('conversation_id', activeConvId);
        fd.append('last_msg_id', lastFetchedMsgId);

        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            isFetchingMessages = false;
            let msgs = data.messages;
            let info = data.info;
            let box = document.getElementById('msgBox');
            if(!box) return;

            if(info && isInitialLoad) {
                document.getElementById('headerAvatar').src = info.profile_img;
                document.getElementById('headerName').innerText = info.display_name;
            }

            let typingDiv = document.getElementById('typingIndicator');
            if(data.typing && data.typing.length > 0) {
                typingDiv.textContent = data.typing.join(', ') + ' typing...';
            } else {
                typingDiv.textContent = '';
            }

            if (msgs.length > 0) {
                msgs.forEach(m => {
                    lastFetchedMsgId = Math.max(lastFetchedMsgId, m.id);
                    let existingMsg = document.getElementById(`msg-${m.id}`);
                    
                    if (existingMsg) {
                        existingMsg.outerHTML = buildMessageHTML(m);
                    } else {
                        box.insertAdjacentHTML('beforeend', buildMessageHTML(m));
                    }
                });
                
                if(!isUserScrolling) box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            } else if (isInitialLoad) {
                box.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-muted); font-size:0.9rem;">Start of conversation.</div>';
            }
        })
        .catch(() => { isFetchingMessages = false; });
    }

    function submitMessage() {
        let input = document.getElementById('msgInput');
        let txt = input.value.trim();
        if(!txt) return;

        let fd = new FormData();
        if (editingMsgId) {
            fd.append('action', 'edit_message');
            fd.append('message_id', editingMsgId);
            fd.append('new_text', txt);
            cancelEdit();
            lastFetchedMsgId = 0; 
            document.getElementById('msgBox').innerHTML = ''; 
        } else {
            fd.append('action', 'send_message');
            fd.append('conversation_id', activeConvId);
            fd.append('message', txt);
            fd.append('type', 'text');
            
            let box = document.getElementById('msgBox');
            box.insertAdjacentHTML('beforeend', `<div class="msg-wrapper outgoing"><div class="msg outgoing" style="opacity:0.7;">${escapeHTML(txt)} <div class="msg-meta"><i class="ri-time-line"></i></div></div></div>`);
            box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
        }

        fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
            fetchMessages(false);
            loadSidebar();
        });
        
        input.value = '';
        stopTyping();
    }

    function toggleMsgMenu(e, id) {
        e.stopPropagation();
        document.querySelectorAll('.msg-dropdown').forEach(d => d.style.display = 'none');
        document.getElementById('msg-drop-' + id).style.display = 'block';
    }
    
    document.addEventListener('click', () => {
        document.querySelectorAll('.msg-dropdown, #chatOptionsDropdown').forEach(d => d.style.display = 'none');
    });

    function initEdit(id, text) {
        editingMsgId = id;
        document.getElementById('msgInput').value = text;
        document.getElementById('msgInput').focus();
        document.getElementById('editModeBar').style.display = 'flex';
    }

    function cancelEdit() {
        editingMsgId = null;
        document.getElementById('msgInput').value = '';
        document.getElementById('editModeBar').style.display = 'none';
    }

    function deleteMessage(id) {
        Swal.fire({
            title: 'Delete Message?',
            text: "This will delete the message for everyone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Delete'
        }).then((res) => {
            if(res.isConfirmed) {
                let fd = new FormData();
                fd.append('action', 'delete_message');
                fd.append('message_id', id);
                fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
                    lastFetchedMsgId = 0; 
                    document.getElementById('msgBox').innerHTML = '';
                    fetchMessages(false);
                });
            }
        });
    }

    function toggleHeaderMenu(e) {
        e.stopPropagation();
        let menu = document.getElementById('chatOptionsDropdown');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    function clearDeleteChat(type) {
        let msg = type === 'clear' ? "Clear all messages in this chat?" : "Delete this conversation?";
        Swal.fire({
            title: 'Are you sure?', text: msg, icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes'
        }).then((res) => {
            if(res.isConfirmed) {
                let fd = new FormData();
                fd.append('action', type + '_chat');
                fd.append('conversation_id', activeConvId);
                fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
                    activeConvId = null;
                    document.getElementById('chatArea').innerHTML = '<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted);"><div style="width: 100px; height: 100px; background: #e2e8f0; border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 20px;"><i class="ri-delete-bin-fill" style="font-size:3rem; color: #94a3b8;"></i></div><h3 style="font-size: 1.5rem; color: var(--text-dark);">Chat Removed</h3></div>';
                    loadSidebar();
                });
            }
        });
    }

    function handleFileUpload(input) {
        if(!input.files.length || !activeConvId) return;
        let fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('conversation_id', activeConvId);
        fd.append('file', input.files[0]);
        
        let box = document.getElementById('msgBox');
        box.insertAdjacentHTML('beforeend', `<div class="msg-wrapper outgoing"><div class="msg outgoing" style="opacity:0.7;">Uploading file...</div></div>`);
        box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });

        fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
            input.value = '';
            fetchMessages(false);
            loadSidebar();
        });
    }

    // --- FIX: OPTIMIZED POLLING ---
    // Combined 3 separate intervals into 1 single, slower loop to stop Max Connections limit.
    function startSmartPolling() {
        if(masterPollInterval) clearInterval(masterPollInterval);

        masterPollInterval = setInterval(() => {
            if (!document.hidden) {
                // Check Incoming Calls
                checkIncomingCalls();
                
                // Load Sidebar
                loadSidebar();
                
                // Load Messages if chat is open
                if (activeConvId) {
                    fetchMessages(false);
                }
            }
        }, 8000); // 8 seconds is safe for Shared Hosting (450 connections/hour max)
    }

    function checkIncomingCalls() {
        if(currentIncomingCall) return;
        let fd = new FormData();
        fd.append('action', 'check_incoming_call');
        fd.append('conversation_id', activeConvId || 0);
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.has_call && !currentIncomingCall) {
                currentIncomingCall = data.call;
                document.getElementById('incomingCallerAvatar').src = data.call.caller_avatar;
                document.getElementById('incomingCallerName').textContent = data.call.display_label;
                document.getElementById('incomingCallLabel').textContent = data.call.call_type === 'audio' ? 'Incoming Voice Call' : 'Incoming Video Call';
                document.getElementById('incomingCallModal').style.display = 'flex';
            }
        }).catch(() => {});
    }

    function openGroupModal() { 
        document.getElementById('groupModal').style.display = 'flex'; 
        selectedMembers.clear(); 
        document.getElementById('groupName').value = ''; 
        document.getElementById('memberSearch').value = '';
        searchForGroup(''); 
    }
    function closeGroupModal() { document.getElementById('groupModal').style.display = 'none'; }

    function searchForGroup(val) {
        let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val); 
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedMembers.has(u.id) ? 'background:#f0fdfa;' : '';
                let icon = selectedMembers.has(u.id) ? '<i class="ri-checkbox-circle-fill text-green-500"></i>' : '<i class="ri-checkbox-blank-circle-line text-gray-300"></i>';
                let safeName = u.display_name.replace(/'/g, "\\'");
                html += `<div onclick="toggleMember(${u.id}, this)" style="padding:10px 15px; display:flex; align-items:center; gap:12px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:0.2s; ${isSel}">
                            ${icon}
                            <img src="${u.profile_img}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                            <div style="font-weight:600; font-size:0.9rem;">${u.display_name}</div>
                        </div>`;
            });
            document.getElementById('groupUserList').innerHTML = html || '<div style="padding:15px;text-align:center;color:#888;">No users found</div>';
        });
    }

    function toggleMember(uid, el) {
        if(selectedMembers.has(uid)) {
            selectedMembers.delete(uid);
            el.style.background = '';
            el.querySelector('i').className = 'ri-checkbox-blank-circle-line text-gray-300';
        } else {
            selectedMembers.add(uid);
            el.style.background = '#f0fdfa';
            el.querySelector('i').className = 'ri-checkbox-circle-fill text-green-500';
        }
    }

    function createGroup() {
        let name = document.getElementById('groupName').value.trim();
        if(!name || selectedMembers.size === 0) return Swal.fire('Wait', 'Group name and at least 1 member required.', 'warning');
        
        let fd = new FormData();
        fd.append('action', 'create_group');
        fd.append('group_name', name);
        fd.append('members', JSON.stringify(Array.from(selectedMembers)));
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeGroupModal();
                loadConversation(data.conversation_id);
            }
        });
    }

    document.getElementById('userSearch').addEventListener('input', function(e) {
        let val = e.target.value.trim();
        let results = document.getElementById('searchResults');
        results.style.display = val.length < 1 ? 'none' : 'block';
        if (val.length < 1) return;
        
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            let fd = new FormData();
            fd.append('action', 'search_users');
            fd.append('term', val);
            fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                let html = data.map(u => `<div class="search-item" onclick="startChat(${u.id});"><img src="${u.profile_img}" style="width:35px;height:35px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:600; font-size:0.9rem;">${u.display_name}</div><div style="font-size:0.75rem;color:var(--text-muted);">${u.role}</div></div></div>`).join('');
                results.innerHTML = html || '<div style="padding:15px;text-align:center;color:#888;">No users found</div>';
            });
        }, 300);
    });

    function startChat(userId) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('userSearch').value = '';
        let fd = new FormData();
        fd.append('action', 'start_chat');
        fd.append('target_user_id', userId);
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if(data.id) loadConversation(data.id); });
    }

    function startCall(type) {
        if(!activeConvId) return;
        let fd = new FormData();
        fd.append('action', 'start_call');
        fd.append('conversation_id', activeConvId);
        fd.append('call_type', type);
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'ok') {
                openEmbeddedMeeting(data.room_id, type);
                lastFetchedMsgId = 0; 
                fetchMessages(false);
            }
        });
    }

    function openEmbeddedMeeting(roomId, type = 'video') {
        let overlay = document.getElementById('videoOverlay');
        overlay.style.display = 'flex';
        document.getElementById('callTypeLabel').textContent = type === 'audio' ? 'Voice Call' : 'Video Meeting';
        
        if(jitsiApi) jitsiApi.dispose();
        
        const options = {
            roomName: roomId,
            width: '100%',
            height: '100%',
            parentNode: document.getElementById('jitsiContainer'),
            configOverwrite: { 
                startWithVideoMuted: type === 'audio', 
                startAudioOnly: type === 'audio',
                prejoinPageEnabled: false,          
                disableDeepLinking: true            
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                SHOW_BRAND_WATERMARK: false,
                SHOW_PROMOTIONAL_CLOSE_PAGE: false,
                DEFAULT_LOGO_URL: '',
                DEFAULT_WELCOME_PAGE_LOGO_URL: '',
                APP_NAME: 'Workack Call',
                NATIVE_APP_NAME: 'Workack Call',
                PROVIDER_NAME: 'Workack',
                HIDE_INVITE_MORE_HEADER: true
            },
            userInfo: { displayName: myUserName }
        };

        jitsiApi = new JitsiMeetExternalAPI('meet.jit.si', options);
    }

    function closeCall() {
        if(jitsiApi) { jitsiApi.dispose(); jitsiApi = null; }
        document.getElementById('videoOverlay').style.display = 'none';
        let fd = new FormData(); fd.append('action', 'end_call_request'); fd.append('conversation_id', activeConvId);
        fetch(window.location.href, { method: 'POST', body: fd });
    }

    function acceptIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData(); fd.append('action', 'answer_call'); fd.append('call_id', currentIncomingCall.id);
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            document.getElementById('incomingCallModal').style.display = 'none';
            if(data.room_id) {
                if(activeConvId != data.conversation_id) loadConversation(data.conversation_id);
                openEmbeddedMeeting(data.room_id, data.call_type);
            }
            currentIncomingCall = null;
        });
    }

    function declineIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData(); fd.append('action', 'decline_call'); fd.append('call_id', currentIncomingCall.id);
        fetch(window.location.href, { method: 'POST', body: fd });
        document.getElementById('incomingCallModal').style.display = 'none';
        currentIncomingCall = null;
    }

    function startTyping() {
        let fd = new FormData(); fd.append('action', 'start_typing'); fd.append('conversation_id', activeConvId);
        fetch(window.location.href, { method: 'POST', body: fd });
    }
    function stopTyping() {
        clearTimeout(typingTimer);
        if(!activeConvId) return;
        let fd = new FormData(); fd.append('action', 'stop_typing'); fd.append('conversation_id', activeConvId);
        fetch(window.location.href, { method: 'POST', body: fd });
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[tag]));
    }

    // Init Page
    loadSidebar();
    startSmartPolling();

</script>
</body>
</html>