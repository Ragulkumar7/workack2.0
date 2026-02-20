<?php
// team_chat.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$my_id = $_SESSION['user_id'];
$my_role = trim($_SESSION['role'] ?? 'Employee');
$my_username = $_SESSION['username'] ?? 'User';

// Ensure necessary tables/columns exist
$conn->query("CREATE TABLE IF NOT EXISTS call_requests (id INT AUTO_INCREMENT PRIMARY KEY, conversation_id INT NOT NULL, caller_id INT NOT NULL, room_id VARCHAR(64) NOT NULL, call_type ENUM('audio','video') DEFAULT 'video', status ENUM('ringing','answered','declined','ended') DEFAULT 'ringing', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_conv_status (conversation_id, status))");
if ($conn->query("SHOW COLUMNS FROM chat_messages LIKE 'edited_at'")->num_rows === 0) $conn->query("ALTER TABLE chat_messages ADD COLUMN edited_at DATETIME NULL DEFAULT NULL");
if ($conn->query("SHOW COLUMNS FROM chat_messages LIKE 'deleted_at'")->num_rows === 0) $conn->query("ALTER TABLE chat_messages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
if ($conn->query("SHOW COLUMNS FROM chat_participants LIKE 'muted_until'")->num_rows === 0) $conn->query("ALTER TABLE chat_participants ADD COLUMN muted_until DATETIME NULL DEFAULT NULL");
if ($conn->query("SHOW COLUMNS FROM chat_participants LIKE 'hidden_at'")->num_rows === 0) $conn->query("ALTER TABLE chat_participants ADD COLUMN hidden_at DATETIME NULL DEFAULT NULL");

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Prevent Session Locking so chat stays fast
    session_write_close(); 

    header('Content-Type: application/json');
    $action = $_POST['action'];

    // 1. SEARCH USERS (Unrestricted - Anyone can search anyone)
    if ($action === 'search_users') {
        $term = "%" . ($_POST['term'] ?? '') . "%";
        $sql = "SELECT u.id, u.role, COALESCE(ep.full_name, u.username) as display_name, ep.profile_img 
                FROM users u 
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE (ep.full_name LIKE ? OR u.username LIKE ? OR u.role LIKE ?) AND u.id != ? LIMIT 50";
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
        
        // Fetch valid members to ensure they exist
        $valid = $conn->query("SELECT id FROM users WHERE id IN (" . implode(',', $members) . ")");
        $ok_ids = [];
        while ($r = $valid->fetch_assoc()) {
            $ok_ids[] = $r['id'];
        }
        
        if (empty($ok_ids)) {
            echo json_encode(['status' => 'error', 'message' => 'Selected members are invalid.']); 
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO chat_conversations (type, group_name, created_by) VALUES ('group', ?, ?)");
        $stmt->bind_param("si", $group_name, $my_id);
        if(!$stmt->execute()) {
             echo json_encode(['status' => 'error', 'message' => 'Database error.']); exit;
        }
        
        $conv_id = $conn->insert_id;

        $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($conv_id, $my_id)");
        foreach ($ok_ids as $uid) {
            $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($conv_id, $uid)");
        }

        $sys_msg = "Group '" . $conn->real_escape_string($group_name) . "' created.";
        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$sys_msg', 'text')");

        echo json_encode(['status' => 'success', 'conversation_id' => $conv_id]);
        exit;
    }

    // 3. GET RECENT CHATS
    if ($action === 'get_recent_chats') {
        $sql = "SELECT c.id as conversation_id, c.type, c.group_name,
                    COALESCE(ep.full_name, u.username, 'Unknown User') as name, ep.profile_img as avatar_db, COALESCE(u.role, '') as role,
                    m.message as last_msg, m.message_type, m.created_at as time, cp.muted_until,
                    (SELECT COUNT(*) FROM chat_messages cm WHERE cm.conversation_id = c.id AND cm.is_read = 0 AND cm.sender_id != ?) as unread
                FROM chat_conversations c
                JOIN chat_participants cp ON c.id = cp.conversation_id AND cp.user_id = ? AND cp.hidden_at IS NULL
                LEFT JOIN chat_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id != ?
                LEFT JOIN users u ON cp2.user_id = u.id
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                LEFT JOIN chat_messages m ON m.id = (SELECT MAX(id) FROM chat_messages mm WHERE mm.conversation_id = c.id)
                GROUP BY c.id
                ORDER BY MAX(m.created_at) DESC
                LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $my_id, $my_id, $my_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $chats = [];
        while($row = $result->fetch_assoc()) {
            if ($row['type'] == 'group') {
                $row['name'] = $row['group_name'];
                $row['avatar'] = "https://ui-avatars.com/api/?name=".urlencode($row['group_name'])."&background=6366f1&color=fff";
                $row['role'] = 'Group';
            } else {
                $row['avatar'] = !empty($row['avatar_db']) ? $row['avatar_db'] : "https://ui-avatars.com/api/?name=".urlencode($row['name'])."&background=1b5a5a&color=fff";
            }
            
            if ($row['message_type'] == 'call') {
                $msg = $row['last_msg'] ?? '';
                $row['last_msg'] = (stripos($msg, 'audio:') === 0) ? '📞 Voice call active' : '📹 Video meeting active';
            } else if ($row['message_type'] == 'image') $row['last_msg'] = '🖼️ Photo';
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
        if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['messages' => [], 'info' => null, 'conv_type' => '']); exit; }
        
        $upd = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0");
        $upd->bind_param("ii", $conv_id, $my_id);
        $upd->execute();

        $sql = "SELECT * FROM (
                    SELECT m.*, COALESCE(ep.full_name, u.username) as display_name 
                    FROM chat_messages m 
                    JOIN users u ON m.sender_id = u.id 
                    LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                    WHERE m.conversation_id = ? AND m.id > ?
                    ORDER BY m.id DESC LIMIT 50
                ) AS sub ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $conv_id, $last_msg_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $msgs = [];
        while($row = $res->fetch_assoc()) {
            $row['is_me'] = ($row['sender_id'] == $my_id);
            $row['time'] = date('h:i A', strtotime($row['created_at']));
            $msgs[] = $row;
        }

        $info_sql = "SELECT * FROM chat_conversations WHERE id = ?";
        $i_stmt = $conn->prepare($info_sql);
        $i_stmt->bind_param("i", $conv_id);
        $i_stmt->execute();
        $conv_info = $i_stmt->get_result()->fetch_assoc();

        $partner = null;
        if ($conv_info['type'] == 'direct') {
            $p_sql = "SELECT COALESCE(ep.full_name, u.username) as display_name, u.role, ep.profile_img 
                      FROM chat_participants cp 
                      JOIN users u ON cp.user_id = u.id 
                      LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                      WHERE cp.conversation_id = ? AND cp.user_id != ? LIMIT 1";
            $p_stmt = $conn->prepare($p_sql);
            $p_stmt->bind_param("ii", $conv_id, $my_id);
            $p_stmt->execute();
            $res = $p_stmt->get_result();
            if($res->num_rows > 0) {
                $partner = $res->fetch_assoc();
                if(empty($partner['profile_img'])) $partner['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($partner['display_name'])."&background=random";
            } else {
                $partner = ['display_name' => 'Unknown User', 'role' => '', 'profile_img' => ''];
            }
        } else {
            $partner = ['display_name' => $conv_info['group_name'], 'role' => 'Group Chat', 'is_group' => true, 'profile_img' => "https://ui-avatars.com/api/?name=".urlencode($conv_info['group_name'])."&background=6366f1&color=fff"];
        }
        $mu = $conn->query("SELECT muted_until FROM chat_participants WHERE conversation_id = $conv_id AND user_id = $my_id")->fetch_assoc();
        $partner['muted'] = !empty($mu['muted_until']) && strtotime($mu['muted_until']) > time();

        echo json_encode(['messages' => $msgs, 'info' => $partner, 'conv_type' => $conv_info['type']]);
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
            $conn->query("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = {$row['id']} AND user_id = $my_id");
            echo json_encode(['status' => 'success', 'id' => $row['id']]);
        } else {
            $conn->query("INSERT INTO chat_conversations (type) VALUES ('direct')");
            $new_id = $conn->insert_id;
            $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($new_id, $my_id)");
            $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($new_id, $target_user_id)");
            echo json_encode(['status' => 'success', 'id' => $new_id]);
        }
        exit;
    }

    // 7. CLEAR CHAT
    if ($action === 'clear_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $chk->bind_param("ii", $conv_id, $my_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['status' => 'denied']); exit; }
        $stmt = $conn->prepare("DELETE FROM chat_messages WHERE conversation_id = ? AND sender_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'cleared']);
        exit;
    }

    // 8. START CALL
    if ($action === 'start_call') {
        $conv_id = (int)$_POST['conversation_id'];
        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $chk->bind_param("ii", $conv_id, $my_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['status' => 'error']); exit; }
        $call_type = ($_POST['call_type'] ?? 'video') === 'audio' ? 'audio' : 'video';
        $room_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $_POST['room_id'] ?? '');
        if (empty($room_id) || $conv_id < 1) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']); exit;
        }
        $store_value = ($call_type === 'audio') ? 'audio:' . $room_id : $room_id;
        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES (?, ?, ?, 'call')");
        $stmt->bind_param("iis", $conv_id, $my_id, $store_value);
        $stmt->execute();
        $conn->query("DELETE FROM call_requests WHERE conversation_id = $conv_id AND status = 'ringing'");
        $conn->query("INSERT INTO call_requests (conversation_id, caller_id, room_id, call_type, status) VALUES ($conv_id, $my_id, '" . $conn->real_escape_string($room_id) . "', '$call_type', 'ringing')");
        $call_id = $conn->insert_id;
        echo json_encode(['status' => 'ok', 'room_id' => $room_id, 'call_type' => $call_type, 'call_id' => $call_id]);
        exit;
    }

    // 9. CHECK INCOMING CALL 
    if ($action === 'check_incoming_call') {
        $conv_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
        $stmt = $conn->prepare("SELECT cr.id, cr.conversation_id, cr.caller_id, cr.room_id, cr.call_type, cr.created_at,
                COALESCE(ep.full_name, u.username) as caller_name, ep.profile_img as caller_avatar, c.type as conv_type, c.group_name
                FROM call_requests cr
                JOIN chat_participants cp ON cr.conversation_id = cp.conversation_id AND cp.user_id = ?
                JOIN users u ON cr.caller_id = u.id
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                JOIN chat_conversations c ON cr.conversation_id = c.id
                WHERE cr.caller_id != ? AND cr.status = 'ringing' AND cr.created_at > DATE_SUB(NOW(), INTERVAL 90 SECOND)
                AND ($conv_id = 0 OR cr.conversation_id = ?)
                ORDER BY cr.created_at DESC LIMIT 1");
        $stmt->bind_param("iii", $my_id, $my_id, $conv_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            if (empty($row['caller_avatar'])) $row['caller_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($row['caller_name']) . "&background=random";
            $row['display_label'] = $row['conv_type'] === 'group' ? $row['group_name'] . ' – ' . $row['caller_name'] : $row['caller_name'];
            echo json_encode(['has_call' => true, 'call' => $row]);
        } else {
            echo json_encode(['has_call' => false]);
        }
        exit;
    }

    // 10. ANSWER CALL
    if ($action === 'answer_call') {
        $call_id = (int)$_POST['call_id'];
        $stmt = $conn->prepare("UPDATE call_requests SET status = 'answered' WHERE id = ? AND status = 'ringing' AND caller_id != ?");
        $stmt->bind_param("ii", $call_id, $my_id);
        $stmt->execute();
        $row = $conn->query("SELECT room_id, call_type, conversation_id FROM call_requests WHERE id = $call_id")->fetch_assoc();
        echo json_encode(['status' => 'ok', 'room_id' => $row['room_id'] ?? '', 'call_type' => $row['call_type'] ?? 'video', 'conversation_id' => $row['conversation_id'] ?? 0]);
        exit;
    }

    // 11. DECLINE CALL
    if ($action === 'decline_call') {
        $call_id = (int)$_POST['call_id'];
        $conn->query("UPDATE call_requests SET status = 'declined' WHERE id = $call_id AND status = 'ringing'");
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 12. END CALL
    if ($action === 'end_call_request') {
        $conv_id = (int)$_POST['conversation_id'];
        $conn->query("UPDATE call_requests SET status = 'ended' WHERE conversation_id = $conv_id AND caller_id = $my_id AND status = 'ringing'");
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 13. EDIT MESSAGE
    if ($action === 'edit_message') {
        $msg_id = (int)$_POST['message_id'];
        $new_text = trim($_POST['message'] ?? '');
        if (empty($new_text)) { echo json_encode(['status' => 'error']); exit; }
        $stmt = $conn->prepare("UPDATE chat_messages SET message = ?, edited_at = NOW() WHERE id = ? AND sender_id = ? AND message_type IN ('text','file') AND deleted_at IS NULL");
        $stmt->bind_param("sii", $new_text, $msg_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => $stmt->affected_rows ? 'ok' : 'denied']);
        exit;
    }

    // 14. DELETE MESSAGE
    if ($action === 'delete_message') {
        $msg_id = (int)$_POST['message_id'];
        $stmt = $conn->prepare("UPDATE chat_messages SET deleted_at = NOW(), message = 'This message was deleted' WHERE id = ? AND sender_id = ?");
        $stmt->bind_param("ii", $msg_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => $stmt->affected_rows ? 'ok' : 'denied']);
        exit;
    }

    // 15. MUTE CHAT
    if ($action === 'mute_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $dur = (int)($_POST['duration'] ?? 0); 
        $until = date('Y-m-d H:i:s', strtotime($dur === 0 ? '+8 hours' : ($dur === 1 ? '+1 week' : '+100 years')));
        $stmt = $conn->prepare("UPDATE chat_participants SET muted_until = ? WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("sii", $until, $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 16. UNMUTE CHAT
    if ($action === 'unmute_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $conn->query("UPDATE chat_participants SET muted_until = NULL WHERE conversation_id = $conv_id AND user_id = $my_id");
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 17. DELETE CHAT
    if ($action === 'delete_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $chk->bind_param("ii", $conv_id, $my_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['status' => 'denied']); exit; }
        $stmt = $conn->prepare("UPDATE chat_participants SET hidden_at = NOW() WHERE conversation_id = ? AND user_id = ?");
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

        #mainContent { 
            margin-left: 95px; width: calc(100% - 95px); height: 100vh;
            display: flex; flex-direction: column; transition: 0.3s ease;
        }
        .sidebar-secondary.open ~ #mainContent { margin-left: calc(95px + 220px); width: calc(100% - (95px + 220px)); }

        .app-container { flex: 1; display:flex; height: 0; min-height: 0; margin-top: 0; background: #fff; border-top: 1px solid var(--border-color);}
        #mainContent > div[style*="height: 84px"] { height: 64px !important; min-height: 64px !important; }

        /* SIDEBAR */
        .sidebar { width: 340px; background: #fff; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; z-index: 10;}
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content:space-between; align-items:center; }
        .search-box { position: relative; padding: 15px 20px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;}
        .search-box input { width: 100%; padding: 12px 12px 12px 40px; border: 1px solid var(--border-color); border-radius: 12px; background: #fff; outline: none; font-size: 14px; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);}
        .search-box input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(27,90,90,0.1); }
        .search-box i { position: absolute; left: 35px; top: 27px; color: var(--text-secondary); font-size: 1.1rem;}
        
        .chat-list { flex: 1; overflow-y: auto; }
        .chat-list::-webkit-scrollbar { width: 5px; }
        .chat-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .chat-item { display: flex; align-items: center; padding: 15px 20px; cursor: pointer; transition: 0.2s; border-bottom: 1px solid #f8fafc; }
        .chat-item:hover { background: #f1f5f9; }
        .chat-item.active { background: #f0fdfa; border-right: 4px solid var(--primary-color); }
        .avatar { width: 48px; height: 48px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 1px solid #e2e8f0; }
        
        #searchResults { position: absolute; top: 70px; left: 20px; width: calc(100% - 40px); background: white; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 50; display: none; overflow: hidden; }
        .search-item { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .search-item:hover { background: #f8fafc; }
        .search-item img { width: 35px; height: 35px; border-radius: 50%; }

        /* CHAT AREA */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #f8fafc; position: relative; overflow:hidden;}
        .chat-header { height: 75px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; padding: 0 25px; justify-content: space-between; box-shadow: 0 1px 2px rgba(0,0,0,0.02); z-index: 10;}
        .header-actions { position: relative; display: flex; gap: 10px; align-items: center;}
        .btn-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: var(--text-secondary); cursor: pointer; transition: 0.2s; font-size: 1.3rem; background: #f1f5f9; border:none;}
        .btn-icon:hover { background: #e2e8f0; color: var(--primary-color); }
        
        .menu-dropdown { position: absolute; top: 50px; right: 0; background: white; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 200px; display: none; z-index: 100; overflow: hidden; }
        .menu-dropdown.show { display: block; }
        .menu-item { padding: 12px 18px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 500; transition: 0.2s; }
        .msg-context-menu { position: absolute; background: white; border: 1px solid var(--border-color); border-radius: 10px; box-shadow: 0 8px 20px rgba(0,0,0,0.12); padding: 6px 0; min-width: 140px; display: none; z-index: 150; }
        .msg-context-menu.show { display: block; }
        .msg-context-menu .ctx-item { padding: 10px 16px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; color: var(--text-main); }
        .msg-context-menu .ctx-item:hover { background: #f8fafc; }
        .msg-context-menu .ctx-item.danger { color: #ef4444; }
        .menu-item:hover { background: #f8fafc; }
        .menu-item.danger { color: #ef4444; border-top: 1px solid #f1f5f9; }

        .messages-box { flex: 1; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth; z-index: 1;}
        .messages-box::-webkit-scrollbar { width: 6px; }
        .messages-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .msg-wrapper { display: flex; flex-direction: column; max-width: 75%; position: relative; }
        .msg-wrapper .msg-actions { opacity: 0; position: absolute; right: 8px; top: 4px; display: flex; gap: 4px; transition: opacity 0.2s; }
        .msg-wrapper:hover .msg-actions { opacity: 1; }
        .msg-wrapper.incoming { align-self: flex-start; }
        .msg-wrapper.outgoing { align-self: flex-end; }
        
        .msg { padding: 12px 16px; border-radius: 16px; position: relative; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .msg.incoming { background: var(--incoming-bg); border: 1px solid var(--border-color); border-bottom-left-radius: 4px; color: var(--text-main); }
        .msg.outgoing { background: var(--outgoing-bg); color: var(--outgoing-text); border-bottom-right-radius: 4px; }
        .msg.temp { opacity: 0.6; }
        
        .msg img { max-width: 100%; border-radius: 8px; margin-top: 5px; }
        .msg-time { font-size: 0.7rem; margin-top: 6px; opacity: 0.7; text-align: right; font-weight: 500;}
        
        .msg.call-msg { background: #e0e7ff; border: 1px solid #c7d2fe; color: #3730a3; align-self: center; width: 100%; max-width: 320px; text-align: center; border-radius: 12px; }
        .join-btn { display: inline-block; background: #4f46e5; color: white; padding: 10px 20px; border-radius: 8px; margin-top: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: 0.2s; border:none; cursor:pointer;}
        .join-btn:hover { background: #4338ca; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);}

        .input-area { padding: 20px 25px; background: white; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; z-index: 10;}
        .input-area input { flex: 1; padding: 14px 20px; border: 1px solid var(--border-color); border-radius: 30px; outline: none; background: #f8fafc; font-size: 15px; transition: 0.2s; }
        .input-area input:focus { border-color: var(--primary-color); background: #fff; }
        
        .btn-send { background: var(--primary-color); color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: 0.2s; font-size: 1.3rem; box-shadow: 0 4px 6px rgba(27, 90, 90, 0.2);}
        .btn-send:hover { background: var(--primary-light); transform: scale(1.05); }

        /* EMBEDDED VIDEO/AUDIO CALL OVERLAY */
        #videoOverlay { display:none; position:absolute; top:75px; left:0; width:100%; height:calc(100% - 75px); background:#0f172a; z-index:200; flex-direction:column; }
        .video-overlay-header { padding: 15px 25px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; color: white; border-bottom: 1px solid #334155;}
        #jitsiContainer { flex: 1; width: 100%; min-height: 0; position: relative; }
        .jitsi-loading { position: absolute; inset: 0; background: #0f172a; display: flex; align-items: center; justify-content: center; z-index: 5; transition: opacity 0.3s; pointer-events: none; opacity: 0; }
        .jitsi-loading.show { opacity: 1; }
        .jitsi-loading-content { text-align: center; color: #94a3b8; }
        .jitsi-loading-content i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: var(--primary-color); }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        /* MODALS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px);}
        .modal { background: white; width: 420px; border-radius: 16px; padding: 25px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal h3 { margin-bottom: 20px; font-weight: 700; color: var(--text-main); font-size: 1.2rem; }
        .modal input { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 15px; outline:none;}
        .modal input:focus { border-color: var(--primary-color); }
        .user-select-list { max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 20px; }
        .user-option { padding: 10px 15px; display: flex; align-items: center; gap: 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: 0.2s;}
        .user-option:hover { background: #f8fafc; }
        .user-option.selected { background: #f0fdfa; border-left: 3px solid var(--primary-color); }
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; }
        .btn-cancel { padding: 10px 20px; border: 1px solid var(--border-color); background: white; border-radius: 8px; cursor: pointer; font-weight: 500;}
        .btn-create { padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;}

        /* INCOMING CALL MODAL */
        #incomingCallModal { position: fixed; inset: 0; background: rgba(15,23,42,0.85); z-index: 2000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
        #incomingCallModal.show { display: flex; }
        .incoming-call-box { background: white; border-radius: 20px; padding: 36px; text-align: center; min-width: 320px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: ringPulse 1.5s ease-in-out infinite; }
        @keyframes ringPulse { 0%, 100% { transform: scale(1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); } 50% { transform: scale(1.02); box-shadow: 0 25px 50px -12px rgba(27,90,90,0.3); } }
        .incoming-call-box img { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 16px; border: 3px solid var(--primary-color); }
        .incoming-call-box h3 { font-size: 1.25rem; color: var(--text-main); margin-bottom: 4px; }
        .incoming-call-box p { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 24px; }
        .incoming-call-actions { display: flex; gap: 16px; justify-content: center; }
        .btn-accept-call { background: #22c55e; color: white; padding: 14px 28px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .btn-decline-call { background: #ef4444; color: white; padding: 14px 28px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .btn-accept-call:hover, .btn-decline-call:hover { transform: scale(1.05); transition: 0.2s; }
    </style>
</head>
<body>

<?php 
if(file_exists('sidebars.php')) include 'sidebars.php'; 
?>

<main id="mainContent">
    <?php 
    if(file_exists('header.php')) include 'header.php'; 
    ?>
    
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
                        <i class="ri-record-circle-fill" style="color:#ef4444; font-size:0.9rem; animation: blink 1s infinite;"></i> <span id="callTypeLabel">Live Meeting</span>
                    </h3>
                    <button onclick="closeCall()" style="background:#ef4444; border:none; color:white; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="ri-phone-x-line"></i> Leave Call
                    </button>
                </div>
                <div class="jitsi-loading" id="jitsiLoading"><div class="jitsi-loading-content"><i class="ri-loader-4-line ri-spin"></i> Joining call...</div></div>
                <div id="jitsiContainer"></div>
            </div>
        </section>
    </div>
</main>

<div id="incomingCallModal">
    <div class="incoming-call-box">
        <img id="incomingCallerAvatar" src="" alt="">
        <h3 id="incomingCallerName">Incoming Call</h3>
        <p id="incomingCallLabel">Voice Call</p>
        <div class="incoming-call-actions">
            <button class="btn-accept-call" onclick="acceptIncomingCall()"><i class="ri-phone-fill"></i> Accept</button>
            <button class="btn-decline-call" onclick="declineIncomingCall()"><i class="ri-phone-x-line"></i> Decline</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editMsgModal" onclick="if(event.target===this)closeEditModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <h3>Edit Message</h3>
        <textarea id="editMsgInput" rows="3" style="width:100%; padding:12px; border:1px solid var(--border-color); border-radius:8px; resize:none; font-size:15px;"></textarea>
        <div class="modal-actions" style="margin-top:16px;">
            <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button class="btn-create" onclick="saveEditMessage()">Save</button>
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
        <div class="user-select-list" id="groupUserList"></div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeGroupModal()">Cancel</button>
            <button class="btn-create" onclick="createGroup()">Create Group</button>
        </div>
    </div>
</div>

<script>
    let activeConvId = null;
    let pollInterval = null;
    let lastFetchedMsgId = 0; 
    let isUserScrolling = false;
    let isFetching = false; 

    // Group Member Storage Data
    let selectedMembers = new Set();
    let selectedMembersData = new Map(); // Stores the mapped Names for the tags

    // JITSI API INSTANCE
    let jitsiApi = null;
    const myUserName = "<?php echo htmlspecialchars($my_username, ENT_QUOTES, 'UTF-8'); ?>";
    
    let currentIncomingCall = null;
    let searchDebounce = null;

    // 1. SEARCH USERS FOR DIRECT CHAT
    document.getElementById('userSearch').addEventListener('input', function(e) {
        let val = e.target.value.trim();
        document.getElementById('searchResults').style.display = val.length < 2 ? 'none' : 'block';
        if (val.length < 2) return;
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            let fd = new FormData();
            fd.append('action', 'search_users');
            fd.append('term', val);
            fetch('team_chat.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                let html = data.length ? data.map(u => `<div class="search-item" onclick="startChat(${u.id})"><img src="${u.profile_img}"><div><div style="font-weight:600; color:var(--text-main);">${u.display_name}</div><div style="font-size:0.75rem; color:var(--text-secondary);">${u.role}</div></div></div>`).join('') : '<div style="padding:15px; text-align:center; color:#888;">No members found</div>';
                document.getElementById('searchResults').innerHTML = html;
            });
        }, 350);
    });

    // 2. START DIRECT CHAT
    function startChat(userId) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('userSearch').value = '';
        
        let fd = new FormData();
        fd.append('action', 'start_chat');
        fd.append('target_user_id', userId);

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'error') { alert(data.message); return; }
            if (data.id) loadConversation(data.id, true);
        });
    }

    // 3. LOAD SIDEBAR
    function loadSidebar() {
        let fd = new FormData();
        fd.append('action', 'get_recent_chats');

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(c => {
                let active = (c.conversation_id == activeConvId) ? 'active' : '';
                let unread = c.unread > 0 ? `<span style="background:var(--primary-color); color:white; font-size:0.7rem; font-weight:bold; padding:2px 6px; border-radius:10px;">${c.unread}</span>` : '';
                let muteTag = c.muted ? '<span style="font-size:0.65rem; color:#94a3b8; margin-left:4px;">Muted</span>' : '';
                
                html += `
                <div class="chat-item ${active}" onclick="loadConversation(${c.conversation_id})">
                    <img src="${c.avatar}" class="avatar">
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <div style="font-weight:600; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.name || 'Unknown'}${muteTag}</div>
                            <div style="font-size:0.7rem; color:var(--text-secondary);">${c.time}</div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:80%; font-size:0.85rem; color:var(--text-secondary);">
                                ${c.last_msg ? c.last_msg : 'Start a conversation'}
                            </span>
                            ${unread}
                        </div>
                    </div>
                </div>`;
            });
            if(data.length === 0) html = '<div style="text-align:center; padding: 30px; color:var(--text-secondary);">No active chats</div>';
            document.getElementById('chatList').innerHTML = html;
        });
    }

    // 4. LOAD CONVERSATION
    function loadConversation(convId, forceRefresh = false) {
        if(activeConvId === convId && !forceRefresh) return; 
        
        activeConvId = convId;
        lastFetchedMsgId = 0; 
        isUserScrolling = false;

        document.getElementById('chatArea').innerHTML = `
            <div class="chat-header" id="chatHeader">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="avatar" style="width:40px; height:40px; background:#f1f5f9; border:none;"></div>
                    <div><h3 style="font-size:1.1rem; color:#ccc; background:#f1f5f9; width:100px; height:15px; border-radius:4px;"></h3></div>
                </div>
            </div>
            <div class="messages-box" id="msgBox">
                 <div style="display:flex; justify-content:center; padding-top:40px; color:var(--text-secondary);"><i class="ri-loader-4-line ri-spin" style="margin-right:8px;"></i> Loading securely...</div>
            </div>
            <div class="input-area">
                <label for="fileUpload"><i class="ri-attachment-2 btn-icon"></i></label>
                <input type="file" id="fileUpload" hidden onchange="handleFileUpload(this)">
                <input type="text" id="msgInput" placeholder="Type your message..." onkeypress="if(event.key === 'Enter') sendMessage()">
                <button class="btn-send" onclick="sendMessage()"><i class="ri-send-plane-fill"></i></button>
            </div>
            
            <div id="videoOverlay">
                <div class="video-overlay-header">
                    <h3 style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                        <i class="ri-record-circle-fill" style="color:#ef4444; font-size:0.9rem; animation: blink 1s infinite;"></i> <span id="callTypeLabel">Live Meeting</span>
                    </h3>
                    <button onclick="closeCall()" style="background:#ef4444; border:none; color:white; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px;">
                        <i class="ri-phone-x-line"></i> Leave Call
                    </button>
                </div>
                <div class="jitsi-loading" id="jitsiLoading"><div class="jitsi-loading-content"><i class="ri-loader-4-line ri-spin"></i> Joining call...</div></div>
                <div id="jitsiContainer"></div>
            </div>
        `;

        fetchMessages(true); 
        loadSidebar(); 
        
        if(pollInterval) clearInterval(pollInterval);
        let pollTick = 0;
        pollInterval = setInterval(() => {
            if (document.hidden) return;
            fetchMessages(false);
            if (++pollTick % 3 === 0) loadSidebar();
        }, 5000);
    }

    // 5. FETCH MESSAGES
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
            let box = document.getElementById('msgBox');
            if(!box) return; 

            if(isInitialLoad && info) {
                chatMuted = !!info.muted;
                let headerHTML = `
                    <div style="display:flex; align-items:center; gap:15px;">
                        <img src="${info.profile_img}" class="avatar" style="width:42px; height:42px; margin:0;">
                        <div>
                            <h3 style="font-size:1.1rem; color:var(--text-main); margin:0; line-height:1.2; font-weight:700;">${info.display_name}</h3>
                            <div style="font-size:0.8rem; color:var(--text-secondary); font-weight:500;">${info.role}</div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn-icon" onclick="startCall('audio', this)" title="Voice Call"><i class="ri-phone-line"></i></button>
                        <button class="btn-icon" onclick="startCall('video', this)" title="Video Call"><i class="ri-vidicon-line"></i></button>
                        <button class="btn-icon" onclick="toggleMenu()"><i class="ri-more-2-fill"></i></button>
                        <div class="menu-dropdown" id="chatMenu">
                            <div class="menu-item" id="muteMenuItem" onclick="toggleMuteChat()"><i class="ri-notification-3-line"></i> <span id="muteMenuText">${chatMuted ? 'Unmute' : 'Mute notifications'}</span></div>
                            <div class="menu-item" onclick="clearChat()"><i class="ri-delete-bin-line"></i> Clear messages</div>
                            <div class="menu-item danger" onclick="deleteChat()"><i class="ri-chat-delete-line"></i> Delete chat</div>
                        </div>
                    </div>
                `;
                document.getElementById('chatHeader').innerHTML = headerHTML;
                box.innerHTML = ''; 
                
                box.addEventListener('scroll', () => {
                    let isAtBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 50;
                    isUserScrolling = !isAtBottom;
                });
            }

            if (msgs.length > 0) {
                let html = '';
                msgs.forEach(m => {
                    lastFetchedMsgId = Math.max(lastFetchedMsgId, m.id); 
                    let cls = m.is_me ? 'outgoing' : 'incoming';
                    let content = m.message;
                    
                    if(m.message_type === 'call') {
                        let raw = m.message;
                        let callType = (raw.startsWith('audio:')) ? 'audio' : 'video';
                        let meetId = raw.includes('meet.jit.si/') ? raw.split('/').pop() : raw.replace(/^(audio|video):/i, '');
                        let iconClass = callType === 'audio' ? 'ri-phone-fill' : 'ri-vidicon-fill';
                        let label = callType === 'audio' ? 'Voice Call' : 'Video Meeting';

                        html += `<div class="msg-wrapper ${cls}"><div class="msg call-msg">
                                    <i class="${iconClass}" style="font-size:1.5rem; margin-bottom:5px; display:block;"></i> 
                                    <strong>${label}</strong><br>
                                    <button onclick="openEmbeddedMeeting('${String(meetId).replace(/'/g, "\\'")}', '${callType}')" class="join-btn">Join ${callType === 'audio' ? 'Call' : 'Meeting'}</button>
                                    <div class="msg-time">${m.time}</div>
                                 </div></div>`;
                    } else {
                        let isDeleted = !!m.deleted_at;
                        if(isDeleted) content = '<em style="color:#94a3b8; font-size:0.9rem;">This message was deleted</em>';
                        else if(m.message_type === 'image') content = `<img src="${m.attachment_path}">`;
                        else if(m.message_type === 'file') content = `<a href="${m.attachment_path}" target="_blank" style="text-decoration:none; color:inherit; font-weight:600;"><i class="ri-file-text-fill"></i> ${m.message}</a>`;
                        
                        let senderName = (!m.is_me && info && info.is_group) ? `<div style="font-size:0.75rem; color:var(--primary-color); font-weight:700; margin-bottom:4px;">${m.display_name}</div>` : '';
                        let editedBadge = m.edited_at ? ' <span style="font-size:0.65rem; opacity:0.8;">(edited)</span>' : '';
                        let safeMsg = (m.message||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/\r?\n/g,' ');
                        let canEditDel = m.is_me && !isDeleted && (m.message_type === 'text' || m.message_type === 'file') ? `<div class="msg-actions"><button class="btn-icon" style="width:28px;height:28px;font-size:0.85rem;" onclick="event.stopPropagation();openEditModal(${m.id}, this.closest('.msg-wrapper'))" title="Edit"><i class="ri-edit-line"></i></button><button class="btn-icon" style="width:28px;height:28px;font-size:0.85rem;color:#ef4444;" onclick="event.stopPropagation();deleteMessage(${m.id})" title="Delete"><i class="ri-delete-bin-line"></i></button></div>` : '';

                        html += `<div class="msg-wrapper ${cls}" id="msg-${m.id}" data-msg-text="${safeMsg}">${canEditDel}
                                    <div class="msg ${cls}">
                                        ${senderName}
                                        ${content}
                                        <div class="msg-time">${m.time}${editedBadge}</div>
                                    </div>
                                 </div>`;
                    }
                });

                box.insertAdjacentHTML('beforeend', html);
                if (isInitialLoad || !isUserScrolling) { scrollToBottom(box); }
            } else if (isInitialLoad && msgs.length === 0) {
                box.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-secondary);"><div style="font-size:3rem; margin-bottom:10px;">👋</div>Say hello and start the conversation!</div>';
            }
        })
        .catch(() => isFetching = false);
    }

    function scrollToBottom(el) {
        setTimeout(() => { el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' }); }, 50);
    }

    // 6. SEND MESSAGE
    function sendMessage(type = 'text', content = null) {
        let input = document.getElementById('msgInput');
        let txt = content ? content : (input ? input.value.trim() : '');
        let fileInput = document.getElementById('fileUpload');
        let file = fileInput ? fileInput.files[0] : null;

        if(!txt && !file) return;

        let tempId = Date.now();
        let box = document.getElementById('msgBox');
        let timeNow = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        if (type === 'text' && txt && box) {
            if(box.innerHTML.includes('Say hello')) box.innerHTML = '';
            let html = `
                <div class="msg-wrapper outgoing" id="temp-${tempId}">
                    <div class="msg outgoing temp">
                        ${txt}
                        <div class="msg-time">${timeNow} <i class="ri-time-line"></i></div>
                    </div>
                </div>`;
            box.insertAdjacentHTML('beforeend', html);
            scrollToBottom(box);
        }

        let fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('conversation_id', activeConvId);
        fd.append('type', type);
        
        if(type === 'text') fd.append('message', txt);
        else fd.append('message', content); 

        if(file) { fd.append('file', file); fileInput.value = ''; }
        if(input) input.value = ''; 

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(() => {
            let tempMsg = document.getElementById(`temp-${tempId}`);
            if(tempMsg) tempMsg.remove();
            fetchMessages(false); 
            isUserScrolling = false; 
        });
    }

    function handleFileUpload(input) {
        if(input.files.length > 0) { sendMessage('file'); }
    }

    // JITSI CALLS
    function startCall(type, btnElement) {
        if(!activeConvId) return;
        if(btnElement) {
            btnElement.disabled = true;
            btnElement.style.opacity = '0.5';
            setTimeout(() => { btnElement.disabled = false; btnElement.style.opacity = '1'; }, 3000);
        }
        let meetId = 'Workack-' + Date.now().toString(36) + '-' + Math.random().toString(36).substring(2, 8);
        let fd = new FormData();
        fd.append('action', 'start_call');
        fd.append('conversation_id', activeConvId);
        fd.append('call_type', type);
        fd.append('room_id', meetId);

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'ok') {
                sendMessage('call', type === 'audio' ? 'audio:' + meetId : meetId);
                openEmbeddedMeeting(meetId, type);
            } else alert('Could not start call. Please try again.');
        });
    }

    function openEmbeddedMeeting(roomId, type = 'video') {
        let overlay = document.getElementById('videoOverlay');
        let container = document.querySelector('#jitsiContainer');
        if(!overlay || !container) return;
        
        overlay.style.display = 'flex';
        let lbl = document.getElementById('callTypeLabel');
        if(lbl) lbl.textContent = type === 'audio' ? 'Voice Call' : 'Video Meeting';
        let loading = document.getElementById('jitsiLoading') || overlay.querySelector('.jitsi-loading');
        if(loading) loading.classList.add('show');
        
        if(jitsiApi) { jitsiApi.dispose(); jitsiApi = null; }

        const domain = 'meet.jit.si';
        const isAudioOnly = type === 'audio';
        const options = {
            roomName: roomId,
            width: '100%',
            height: '100%',
            parentNode: container,
            subject: isAudioOnly ? 'Workack Voice Call' : 'Workack Video Meeting',
            configOverwrite: {
                startWithAudioMuted: false,
                startWithVideoMuted: isAudioOnly,
                startAudioOnly: isAudioOnly,
                prejoinPageEnabled: false,
                disableDeepLinking: true,
                enableLobby: false,
                enableWelcomePage: false,
                enableClosePage: false,
                requireDisplayName: false
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                DEFAULT_BACKGROUND: '#0f172a',
                startAudioOnly: isAudioOnly,
                TOOLBAR_BUTTONS: isAudioOnly
                    ? ['microphone', 'fodeviceselection', 'hangup', 'profile', 'settings', 'raisehand']
                    : ['microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen', 'fodeviceselection', 'hangup', 'profile', 'chat', 'settings', 'raisehand', 'videoquality', 'filmstrip', 'shortcuts', 'tileview']
            },
            userInfo: { displayName: myUserName }
        };
        
        jitsiApi = new JitsiMeetExternalAPI(domain, options);
        jitsiApi.addEventListener('videoConferenceJoined', hideJitsiLoading);
        jitsiApi.addEventListener('videoConferenceLeft', () => { closeCall(); });
        jitsiApi.addEventListener('readyToClose', () => { closeCall(); });
        setTimeout(hideJitsiLoading, 12000); 
        function hideJitsiLoading() {
            (document.getElementById('jitsiLoading') || document.querySelector('.jitsi-loading'))?.classList?.remove('show');
        }
    }

    function closeCall() {
        if(jitsiApi) { jitsiApi.dispose(); jitsiApi = null; }
        let ov = document.getElementById('videoOverlay');
        if(ov) { ov.style.display = 'none'; document.getElementById('jitsiLoading')?.classList?.remove('show'); }
        if(activeConvId) {
            let fd = new FormData();
            fd.append('action', 'end_call_request');
            fd.append('conversation_id', activeConvId);
            fetch('team_chat.php', { method: 'POST', body: fd }).catch(()=>{});
        }
    }

    let editingMsgId = null;
    let chatMuted = false;

    function toggleMenu() { 
        let menu = document.getElementById('chatMenu');
        if(menu) menu.classList.toggle('show'); 
    }

    function openEditModal(msgId, wrapperEl) {
        editingMsgId = msgId;
        let el = typeof wrapperEl === 'object' ? wrapperEl : document.getElementById('msg-'+msgId);
        document.getElementById('editMsgInput').value = el ? (el.getAttribute('data-msg-text') || '').replace(/&quot;/g,'"').replace(/&amp;/g,'&').replace(/&lt;/g,'<') : '';
        document.getElementById('editMsgModal').style.display = 'flex';
    }
    function closeEditModal() { document.getElementById('editMsgModal').style.display = 'none'; editingMsgId = null; }
    function saveEditMessage() {
        if(!editingMsgId) return;
        let txt = document.getElementById('editMsgInput').value.trim();
        if(!txt) return;
        let fd = new FormData();
        fd.append('action', 'edit_message');
        fd.append('message_id', editingMsgId);
        fd.append('message', txt);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if(data.status === 'ok') { closeEditModal(); lastFetchedMsgId = 0; fetchMessages(true); } });
    }
    function deleteMessage(msgId) {
        if(!confirm('Delete this message?')) return;
        let fd = new FormData();
        fd.append('action', 'delete_message');
        fd.append('message_id', msgId);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if(data.status === 'ok') { lastFetchedMsgId = 0; fetchMessages(true); } });
    }
    function toggleMuteChat() {
        let fd = new FormData();
        fd.append('action', chatMuted ? 'unmute_chat' : 'mute_chat');
        fd.append('conversation_id', activeConvId);
        if(!chatMuted) fd.append('duration', 0);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(() => { chatMuted = !chatMuted; document.getElementById('muteMenuText').textContent = chatMuted ? 'Unmute' : 'Mute notifications'; document.getElementById('chatMenu').classList.remove('show'); loadSidebar(); });
    }
    function deleteChat() {
        if(!confirm('Remove this chat from your list?')) return;
        let fd = new FormData();
        fd.append('action', 'delete_chat');
        fd.append('conversation_id', activeConvId);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(() => { activeConvId = null; document.getElementById('chatArea').innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-secondary);"><div style="width:80px;height:80px;background:#e2e8f0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:20px;"><i class="ri-message-3-line" style="font-size:2.5rem;color:#94a3b8;"></i></div><h3 style="font-size:1.2rem;color:var(--text-main);margin-bottom:5px;">Workack Team Chat</h3><p>Select a conversation from the sidebar.</p></div>`; loadSidebar(); });
    }
    
    function clearChat() {
        if(!confirm('Are you sure you want to clear this chat history for yourself?')) return;
        let fd = new FormData();
        fd.append('action', 'clear_chat');
        fd.append('conversation_id', activeConvId);
        fetch('team_chat.php', { method: 'POST', body: fd }).then(() => {
            document.getElementById('msgBox').innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-secondary);">Chat cleared.</div>';
            document.getElementById('chatMenu').classList.remove('show');
            lastFetchedMsgId = 0;
            loadSidebar();
        });
    }

    // ==============================================
    // IMPROVED: GROUP CREATION WITH VISUAL TAGS
    // ==============================================
    
    function openGroupModal() { 
        document.getElementById('groupModal').style.display = 'flex'; 
        selectedMembers.clear(); 
        selectedMembersData.clear(); 
        renderSelectedTags();
        document.getElementById('groupName').value = ''; 
        document.getElementById('memberSearch').value = '';
        
        // Auto-fetch all valid members immediately
        searchForGroup(''); 
    }
    
    function closeGroupModal() { document.getElementById('groupModal').style.display = 'none'; }

    function searchForGroup(val) {
        let fd = new FormData();
        fd.append('action', 'search_users');
        fd.append('term', val); 

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedMembers.has(u.id) ? 'selected' : '';
                let checkIcon = isSel ? '<i class="ri-checkbox-circle-fill" style="color:var(--primary-color); font-size:1.2rem; margin-left:auto;"></i>' : '';
                
                let safeName = u.display_name.replace(/'/g, "\\'");
                html += `<div class="user-option ${isSel}" onclick="toggleMember(${u.id}, '${safeName}', this)">
                            <img src="${u.profile_img}" style="width:30px; height:30px; border-radius:50%;">
                            <div style="font-weight:600; color:var(--text-main);">${u.display_name}</div>
                            ${checkIcon}
                         </div>`;
            });
            document.getElementById('groupUserList').innerHTML = html || '<div style="padding:15px; text-align:center; color:#888;">No members found</div>';
        });
    }

    function toggleMember(uid, name, el = null) {
        if(selectedMembers.has(uid)) { 
            selectedMembers.delete(uid); 
            selectedMembersData.delete(uid);
            if(el) {
                el.classList.remove('selected'); 
                let icon = el.querySelector('.ri-checkbox-circle-fill');
                if(icon) icon.remove();
            }
        }
        else { 
            selectedMembers.add(uid); 
            selectedMembersData.set(uid, name);
            if(el) {
                el.classList.add('selected'); 
                el.insertAdjacentHTML('beforeend', '<i class="ri-checkbox-circle-fill" style="color:var(--primary-color); font-size:1.2rem; margin-left:auto;"></i>');
            }
        }
        renderSelectedTags();
        if(!el) searchForGroup(document.getElementById('memberSearch').value);
    }

    function renderSelectedTags() {
        let html = '';
        selectedMembersData.forEach((name, id) => {
            html += `<span style="background:var(--primary-light); color:white; padding:5px 12px; border-radius:20px; font-size:0.8rem; display:flex; align-items:center; gap:6px;">
                        ${name} <i class="ri-close-circle-line" style="cursor:pointer; font-size:1rem;" onclick="toggleMember(${id}, '${name.replace(/'/g, "\\'")}')"></i>
                     </span>`;
        });
        document.getElementById('selectedTags').innerHTML = html;
    }

    function createGroup() {
        let name = document.getElementById('groupName').value.trim();
        if(!name) { alert('Please enter a group name.'); return; }
        if(selectedMembers.size === 0) { alert('Please select at least one member from the list below.'); return; }

        let fd = new FormData();
        fd.append('action', 'create_group');
        fd.append('group_name', name);
        fd.append('members', JSON.stringify(Array.from(selectedMembers)));

        let btn = document.querySelector('.btn-create');
        let oldText = btn.innerHTML;
        btn.innerHTML = 'Creating...';
        btn.disabled = true;

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.innerHTML = oldText;
            btn.disabled = false;
            
            // Check for actual success response
            if (data.status === 'success') {
                closeGroupModal();
                loadConversation(data.conversation_id, true);
            } else {
                alert('Error: ' + (data.message || 'Something went wrong.'));
            }
        })
        .catch(err => {
            btn.innerHTML = oldText;
            btn.disabled = false;
            alert('Failed to connect to the server.');
        });
    }

    // CLOSE MENUS ON CLICK OUTSIDE
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.header-actions')) {
            let menu = document.getElementById('chatMenu');
            if(menu) menu.classList.remove('show');
        }
        if (!e.target.closest('.search-box')) {
            let sRes = document.getElementById('searchResults');
            if(sRes) sRes.style.display = 'none';
        }
    });

    // STARTUP
    setInterval(function pollIncomingCall() {
        if(document.hidden || currentIncomingCall || document.getElementById('videoOverlay')?.style?.display === 'flex') return;
        let fd = new FormData();
        fd.append('action', 'check_incoming_call');
        fd.append('conversation_id', activeConvId || 0);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.has_call && data.call && !currentIncomingCall) {
                currentIncomingCall = data.call;
                document.getElementById('incomingCallerAvatar').src = data.call.caller_avatar;
                document.getElementById('incomingCallerName').textContent = data.call.display_label;
                document.getElementById('incomingCallLabel').textContent = data.call.call_type === 'audio' ? 'Voice Call' : 'Video Meeting';
                document.getElementById('incomingCallModal').classList.add('show');
            }
        }).catch(()=>{});
    }, 2000);

    function acceptIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData();
        fd.append('action', 'answer_call');
        fd.append('call_id', currentIncomingCall.id);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            document.getElementById('incomingCallModal').classList.remove('show');
            currentIncomingCall = null;
            if(data.room_id) {
                let convId = data.conversation_id || activeConvId;
                if(convId) { activeConvId = convId; loadConversation(convId, true); }
                setTimeout(() => openEmbeddedMeeting(data.room_id, data.call_type || 'video'), 400);
            }
        });
    }

    function declineIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData();
        fd.append('action', 'decline_call');
        fd.append('call_id', currentIncomingCall.id);
        fetch('team_chat.php', { method: 'POST', body: fd }).then(()=>{});
        document.getElementById('incomingCallModal').classList.remove('show');
        currentIncomingCall = null;
    }

    loadSidebar();
</script>

</body>
</html>