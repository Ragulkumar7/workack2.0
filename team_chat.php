<?php
// team_chat.php - PROFESSIONAL ENTERPRISE EDITION (Optimized for Shared Hosting)

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Database connection fallback
$dbPath = 'include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
elseif (file_exists('../include/db_connect.php')) { require_once '../include/db_connect.php'; } 
else { die("Database connection missing."); }

// --- CRITICAL FIX: FORCE CONNECTION CLOSURE ---
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

// === PERFORMANCE FIX: Resolve Directory path ONCE instead of inside loops ===
$profile_dir = file_exists('../assets/profiles/') ? '../assets/profiles/' : 'assets/profiles/';

// =========================================================================================
// ROLE-BASED ACCESS CONTROL (RBAC) FOR USER DIRECTORY & SEARCH
// =========================================================================================
$my_info_query = $conn->query("SELECT ep.reporting_to, ep.department, u.role FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = $my_id");
$my_info = $my_info_query->fetch_assoc();
$my_tl_id = (int)($my_info['reporting_to'] ?? 0);
$my_dept = $conn->real_escape_string($my_info['department'] ?? '');
$my_exact_role = $my_info['role'] ?? 'Employee';

$see_all_roles = ['System Admin', 'HR', 'HR Executive'];
$manager_roles = ['Manager', 'Sales Manager', 'IT Admin', 'CFO']; 

if (in_array($my_exact_role, $see_all_roles)) {
    // HR and Admins see everyone
    $user_filter = "1=1"; 
} elseif (in_array($my_exact_role, $manager_roles)) {
    // Managers see everyone in their own department + HR
    $user_filter = "(ep.department = '$my_dept' OR u.role IN ('HR', 'HR Executive', 'System Admin'))";
} elseif ($my_exact_role === 'Team Lead') {
    // TL sees: Their reportees, Managers in their dept, and HR
    $user_filter = "(ep.reporting_to = $my_id OR (u.role IN ('Manager', 'Sales Manager', 'IT Admin', 'CFO') AND ep.department = '$my_dept') OR u.role IN ('HR', 'HR Executive', 'System Admin'))";
} else {
    // Employee sees: Their TL, their teammates (same TL), Managers in their dept, and HR
    if ($my_tl_id > 0) {
        $user_filter = "(ep.reporting_to = $my_tl_id OR u.id = $my_tl_id OR (u.role IN ('Manager', 'Sales Manager', 'IT Admin', 'CFO') AND ep.department = '$my_dept') OR u.role IN ('HR', 'HR Executive', 'System Admin'))";
    } else {
        $user_filter = "(ep.department = '$my_dept' OR u.role IN ('Manager', 'HR', 'System Admin', 'HR Executive'))";
    }
}

// Fetch users for the meeting dropdown & People Directory based on RBAC
$all_users = [];
$res_users = $conn->query("SELECT u.id, u.role, COALESCE(ep.full_name, u.username) as name, ep.profile_img FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id != $my_id AND ($user_filter)");
if ($res_users) {
    while($row = $res_users->fetch_assoc()) {
        if(empty($row['profile_img']) || $row['profile_img'] == 'default_user.png') {
            $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['name'])."&background=random";
        } elseif(!str_starts_with($row['profile_img'], 'http')) {
            $row['profile_img'] = $profile_dir . $row['profile_img']; // FAST LOAD
        }
        $all_users[] = $row;
    }
}

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

// --- GUARANTEED DB CREATION ---
$check_cal = $conn->query("SHOW TABLES LIKE 'calendar_meetings'");
if ($check_cal && $check_cal->num_rows == 0) {
    $conn->query("CREATE TABLE calendar_meetings (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), meet_date DATE, meet_time VARCHAR(50), meet_link VARCHAR(100), created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE calendar_meeting_participants (meeting_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY (meeting_id, user_id)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE instant_meetings (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), meet_link VARCHAR(100), created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
}

if (!isset($_SESSION['chat_db_checked_v8'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS call_requests (id INT AUTO_INCREMENT PRIMARY KEY, conversation_id INT NOT NULL, caller_id INT NOT NULL, room_id VARCHAR(64) NOT NULL, call_type ENUM('audio','video') DEFAULT 'video', status ENUM('ringing','answered','declined','ended') DEFAULT 'ringing', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE IF NOT EXISTS message_reads (message_id INT NOT NULL, user_id INT NOT NULL, read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (message_id, user_id)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE IF NOT EXISTS typing_status (conversation_id INT NOT NULL, user_id INT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (conversation_id, user_id)) ENGINE=InnoDB");

    $conn->query("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS edited_at DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE chat_participants ADD COLUMN IF NOT EXISTS muted_until DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE chat_participants ADD COLUMN IF NOT EXISTS hidden_at DATETIME NULL DEFAULT NULL");
    
    $_SESSION['chat_db_checked_v8'] = true;
}

// =========================================================================================
// AJAX HANDLERS
// =========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_write_close(); 
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // 1. SEARCH USERS
    if ($action === 'search_users') {
        $term = "%" . ($_POST['term'] ?? '') . "%";
        $sql = "SELECT u.id, u.role, COALESCE(ep.full_name, u.username) as display_name, ep.profile_img 
                FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE (ep.full_name LIKE ? OR u.username LIKE ? OR u.role LIKE ?) AND u.id != ? AND ($user_filter) LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $term, $term, $term, $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        while($row = $res->fetch_assoc()) { 
            if(empty($row['profile_img']) || $row['profile_img'] == 'default_user.png') {
                $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
            } elseif(!str_starts_with($row['profile_img'], 'http')) {
                $row['profile_img'] = $profile_dir . $row['profile_img'];
            }
            $users[] = $row; 
        }
        echo json_encode($users); exit;
    }

    // 2. CREATE GROUP
    if ($action === 'create_group') {
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

    // 3. GET RECENT CHATS 
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
                    $row['avatar'] = $profile_dir . $row['avatar_db'];
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

    // 4. GET MESSAGES
    if ($action === 'get_messages') {
        $conv_id = (int)$_POST['conversation_id'];
        $last_msg_id = isset($_POST['last_msg_id']) ? (int)$_POST['last_msg_id'] : 0;

        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        $chk->bind_param("ii", $conv_id, $my_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['messages' => [], 'info' => null]); exit; }
        
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

        $typing_users = [];
        $typing_res = $conn->query("SELECT COALESCE(ep.full_name, u.username) as typing_name FROM typing_status ts JOIN users u ON ts.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE ts.conversation_id = $conv_id AND ts.user_id != $my_id AND ts.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        while ($t = $typing_res->fetch_assoc()) $typing_users[] = $t['typing_name'];

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
                    $partner['profile_img'] = $profile_dir . $partner['profile_img'];
                }
                $partner['is_group'] = false;
            } else {
                $partner = ['display_name' => $conv_info['group_name'], 'role' => 'Group Chat', 'is_group' => true, 'profile_img' => "https://ui-avatars.com/api/?name=".urlencode($conv_info['group_name'])."&background=1b5a5a&color=fff"];
            }
        }

        echo json_encode(['messages' => $msgs, 'info' => $partner, 'typing' => $typing_users]); exit;
    }

    // GROUP INFO & MEMBERS
    if ($action === 'get_group_info') {
        $conv_id = (int)$_POST['conversation_id'];
        $sql = "SELECT u.id, COALESCE(ep.full_name, u.username) as display_name, ep.profile_img, u.role
                FROM chat_participants cp
                JOIN users u ON cp.user_id = u.id
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE cp.conversation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $conv_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $members = [];
        while($row = $res->fetch_assoc()) {
            if(empty($row['profile_img']) || $row['profile_img'] == 'default_user.png') {
                $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
            } elseif(!str_starts_with($row['profile_img'], 'http')) {
                $row['profile_img'] = $profile_dir . $row['profile_img'];
            }
            $members[] = $row;
        }
        echo json_encode($members); exit;
    }

    if ($action === 'add_members_to_group') {
        $conv_id = (int)$_POST['conversation_id'];
        $members = json_decode($_POST['members'] ?? '[]', true);
        if(!is_array($members) || empty($members)) { echo json_encode(['status'=>'error']); exit; }
        
        $stmt_part = $conn->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
        foreach ($members as $uid) {
            $stmt_part->bind_param("ii", $conv_id, $uid); 
            $stmt_part->execute();
        }
        $sys_msg = encryptChatMessage("New members were added to the group.");
        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$sys_msg', 'text')");
        echo json_encode(['status' => 'success']); exit;
    }

    // 5. SEND MESSAGE (with staged file logic)
    if ($action === 'send_message') {
        $conv_id = (int)$_POST['conversation_id'];
        $msg_text = $_POST['message'] ?? '';
        $msg_type = $_POST['type'] ?? 'text'; 
        $attachment = null;

        $conn->query("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = $conv_id");

        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $target_dir = "uploads/chat/";
            if (!is_dir($target_dir)) {
                @mkdir($target_dir, 0777, true);
            }
            if (!is_writable($target_dir)) {
                echo json_encode(['status' => 'error', 'message' => 'Upload directory is missing or not writable.']); exit;
            }
            
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $fname = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $fname)) {
                $attachment = $target_dir . $fname;
                $msg_type = in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
                if(!$msg_text) $msg_text = $_FILES['file']['name'];
            }
        }

        if ($msg_type === 'text') { $msg_text = encryptChatMessage($msg_text); }
        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, attachment_path, message_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $conv_id, $my_id, $msg_text, $attachment, $msg_type);
        $stmt->execute();
        echo json_encode(['status' => 'sent']); exit;
    }

    // ==========================================
    // MEETING HISTORY HANDLERS 
    // ==========================================
    if ($action === 'save_instant_meeting') {
        $title = trim($_POST['title'] ?? 'Instant Meeting');
        $link = $_POST['link'];
        $stmt = $conn->prepare("INSERT INTO instant_meetings (title, meet_link, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $title, $link, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'delete_meeting') {
        $meet_id = (int)$_POST['meet_id'];
        $type = $_POST['meet_type'];
        
        if ($type === 'instant') {
            $conn->query("DELETE FROM instant_meetings WHERE id = $meet_id AND created_by = $my_id");
        } else {
            $conn->query("DELETE FROM calendar_meetings WHERE id = $meet_id AND created_by = $my_id");
        }
        echo json_encode(['status' => 'success']); exit;
    }

    if ($action === 'fetch_meeting_history') {
        $res1 = $conn->query("SELECT id, title, meet_link, created_at FROM instant_meetings WHERE created_by = $my_id ORDER BY created_at DESC LIMIT 5");
        $instant = [];
        while($r = $res1->fetch_assoc()) {
            $r['created_at'] = date('M d, Y h:i A', strtotime($r['created_at']));
            $instant[] = $r;
        }

        $sql = "SELECT DISTINCT cm.* FROM calendar_meetings cm
                LEFT JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id
                WHERE cm.created_by = ? OR cmp.user_id = ? ORDER BY cm.meet_date DESC, cm.meet_time DESC LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $my_id, $my_id);
        $stmt->execute();
        $res2 = $stmt->get_result();
        $scheduled = [];
        while($r = $res2->fetch_assoc()) {
            $r['formatted_date'] = date('l, F d, Y', strtotime($r['meet_date']));
            $r['month'] = date('M', strtotime($r['meet_date']));
            $r['day'] = date('d', strtotime($r['meet_date']));
            $r['is_owner'] = ($r['created_by'] == $my_id);
            $scheduled[] = $r;
        }

        echo json_encode(['instant' => $instant, 'scheduled' => $scheduled]); exit;
    }

    // CALENDAR - CREATE SCHEDULED MEETING AND SEND INVITATION
    if ($action === 'create_scheduled_meeting') {
        $title = trim($_POST['title'] ?? 'Scheduled Meeting');
        $date = $_POST['date'];
        $time = $_POST['time'];
        $members = json_decode($_POST['members'] ?? '[]', true);
        $members[] = $my_id; // Always add the creator to the meeting
        $members = array_filter(array_unique(array_map('intval', $members)));

        $meet_id = 'Workack-Meet-' . substr(md5(uniqid()), 0, 10);

        // 1. Save Meeting to DB
        $stmt = $conn->prepare("INSERT INTO calendar_meetings (title, meet_date, meet_time, meet_link, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $date, $time, $meet_id, $my_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'DB Insert Failed: ' . $stmt->error]); exit;
        }
        $new_meet_id = $conn->insert_id;

        // 2. Add Participants to DB
        $stmt_part = $conn->prepare("INSERT INTO calendar_meeting_participants (meeting_id, user_id) VALUES (?, ?)");
        foreach ($members as $uid) {
            $stmt_part->bind_param("ii", $new_meet_id, $uid);
            $stmt_part->execute();
        }

        // 3. Send Chat Message
        $msg = "📅 **Scheduled Meeting: $title**<br>Date: $date at $time<br>Click to Join: video:$meet_id";
        $enc_msg = encryptChatMessage($msg);

        // Decide conversation routing (Direct or Group)
        if (count($members) == 2) {
            $target = ($members[0] == $my_id) ? $members[1] : $members[0];
            $chk = $conn->query("SELECT c.id FROM chat_conversations c JOIN chat_participants cp1 ON c.id = cp1.conversation_id JOIN chat_participants cp2 ON c.id = cp2.conversation_id WHERE c.type = 'direct' AND cp1.user_id = $my_id AND cp2.user_id = $target LIMIT 1");
            if ($chk->num_rows > 0) {
                $conv_id = $chk->fetch_assoc()['id'];
                $conn->query("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = $conv_id");
            } else {
                $conn->query("INSERT INTO chat_conversations (type) VALUES ('direct')");
                $conv_id = $conn->insert_id;
                $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($conv_id, $my_id), ($conv_id, $target)");
            }
        } else {
            $gname = 'Meeting: ' . $title;
            $conn->query("INSERT INTO chat_conversations (type, group_name, created_by) VALUES ('group', '$gname', $my_id)");
            $conv_id = $conn->insert_id;
            foreach ($members as $uid) {
                $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($conv_id, $uid)");
            }
        }

        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$enc_msg', 'text')");
        echo json_encode(['status' => 'success', 'conversation_id' => $conv_id]);
        exit;
    }

    // CALENDAR - FETCH EVENTS
    if ($action === 'get_calendar_events') {
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        
        $sql = "SELECT cm.* FROM calendar_meetings cm
                JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id
                WHERE cmp.user_id = ? AND cm.meet_date >= ? AND cm.meet_date <= ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $my_id, $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        $events = [];
        while($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
        echo json_encode($events); exit;
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
                $row['caller_avatar'] = $profile_dir . $row['caller_avatar'];
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
            --primary: #1b5a5a; /* Dark Teal */
            --primary-hover: #134040;
            --bg-light: #f5f5f5; 
            --border: #e0e0e0; 
            --text-dark: #242424; 
            --text-muted: #616161; 
            --outgoing-bg: #eefcfd;
            --incoming-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --hover-bg: #fff5f0; 
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
        body { background-color: var(--bg-light); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        #mainContent { margin-left: 95px; width: calc(100% - 95px); height: 100vh; display: flex; flex-direction: column; transition: all 0.3s; }
        .app-container { flex: 1; display:flex; height: 0; min-height: 0; background: var(--bg-light); position: relative;}
        
        /* SECONDARY SIDEBAR */
        .sidebar-secondary-teams { width: 68px; background: #ebebeb; border-right: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; padding-top: 15px; z-index: 15; }
        .nav-icon { width: 50px; height: 50px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); font-size: 0.75rem; border-radius: 6px; margin-bottom: 5px; transition: 0.2s; }
        .nav-icon i { font-size: 1.4rem; margin-bottom: 2px; }
        .nav-icon:hover { color: var(--primary); }
        .nav-icon.active { background: white; color: var(--primary); font-weight: 600; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

        /* SIDEBAR (Teams Style) */
        .sidebar { width: 320px; background: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 10; transition: transform 0.3s ease;}
        
        /* EXACT NOTIFICATION BANNER FROM SCREENSHOT */
        .desktop-notif-banner { background: var(--bg-light); padding: 8px 15px; display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; color: var(--text-dark); border-bottom: 1px solid var(--border); }
        
        .sidebar-header { padding: 15px 20px 5px; display: flex; justify-content:space-between; align-items:center; }
        .btn-icon-small { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; background: transparent; border: 1px solid transparent; cursor: pointer; color: var(--text-dark); transition: 0.2s; font-size: 1.1rem; }
        .btn-icon-small:hover { background: var(--hover-bg); border-color: var(--border); color: var(--primary); }
        
        .search-box { padding: 5px 20px 10px; position: relative; }
        .search-box input { width: 100%; padding: 6px 10px 6px 30px; border: none; border-bottom: 1px solid var(--border); background: transparent; outline: none; transition: border 0.2s; font-size: 0.9rem;}
        .search-box input:focus { border-bottom-color: var(--primary); }
        .search-box i { position: absolute; left: 20px; top: 10px; color: var(--text-muted); font-size: 1rem; }
        
        .chat-list { flex: 1; overflow-y: auto; padding: 0 10px; }
        .chat-list::-webkit-scrollbar { width: 4px; }
        .chat-list::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        
        .section-toggle { padding: 10px 10px 5px; display: flex; align-items: center; gap: 5px; color: var(--text-muted); font-size: 0.8rem; cursor: pointer; font-weight: 600; }
        
        .chat-item { display: flex; align-items: center; padding: 12px; cursor: pointer; border-radius: 8px; margin: 2px 10px; transition: 0.2s; border: 1px solid transparent;}
        .chat-item:hover { background: var(--hover-bg); }
        .chat-item.active { background: white; border: 1px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
        
        .invite-btn-container { padding: 15px; background: transparent; }
        .invite-btn { width: 100%; padding: 8px; background: white; border: 1px solid var(--border); border-radius: 6px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; font-size: 0.9rem; transition: 0.2s; }
        .invite-btn:hover { background: var(--hover-bg); color: var(--primary); border-color: var(--primary); }
        
        #searchResults { position: absolute; top: 40px; left: 20px; width: calc(100% - 40px); background: white; border: 1px solid var(--border); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 50; display: none; max-height: 300px; overflow-y: auto;}
        .search-item { padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--bg-light);}
        .search-item:hover { background: var(--hover-bg); }

        /* MAIN CONTENT AREA */
        .content-area { flex: 1; display: flex; flex-direction: row; background: white; position: relative; overflow:hidden; }

        .chat-main-column { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        
        /* GROUP INFO PANEL (Slide in from right) */
        .group-info-panel { width: 300px; background: #fafafa; border-left: 1px solid var(--border); display: none; flex-direction: column; z-index: 5; transition: transform 0.3s; overflow-y: auto;}

        .chat-header { background: white; border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 10px 20px; justify-content: space-between; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.02);}
        
        .header-nav { display: flex; gap: 20px; align-items: center; margin-left: 20px; border-bottom: 2px solid transparent; }
        .header-nav-item { padding: 5px 0; color: var(--text-muted); font-size: 0.95rem; font-weight: 500; cursor: pointer; position: relative;}
        .header-nav-item.active { color: var(--primary); font-weight: 600; }
        .header-nav-item.active::after { content: ''; position: absolute; bottom: -13px; left: 0; right: 0; height: 3px; background-color: var(--primary); border-radius: 3px 3px 0 0; }
        .header-nav-item:hover:not(.active) { color: var(--text-dark); }

        .header-actions { display: flex; gap: 4px; align-items: center; position: relative;}
        .btn-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; background: transparent; border:none; cursor: pointer; color: var(--text-dark); transition: 0.2s; font-size: 1.2rem;}
        .btn-icon:hover { background: var(--hover-bg); color: var(--primary); }
        
        .messages-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; z-index: 5;}
        .messages-box::-webkit-scrollbar { width: 6px; }
        .messages-box::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }

        .msg-wrapper { display: flex; flex-direction: column; max-width: 70%; position: relative;}
        .msg-wrapper.incoming { align-self: flex-start; }
        .msg-wrapper.outgoing { align-self: flex-end; }
        
        .msg { padding: 10px 14px; border-radius: 4px; font-size: 0.95rem; line-height: 1.4; word-wrap: break-word; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.05);}
        .msg.incoming { background: var(--incoming-bg); border: 1px solid var(--border); }
        .msg.outgoing { background: var(--outgoing-bg); color: var(--text-dark); border: 1px solid #ffdecb; }
        .msg.deleted { font-style: italic; color: var(--text-muted); background: transparent; border: 1px solid var(--border); box-shadow: none;}
        
        .msg-meta { display: flex; justify-content: flex-end; align-items: center; gap: 4px; font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;}
        .ticks { font-size: 0.9rem; margin-left: 2px;}
        .tick-read { color: var(--primary); }
        .tick-sent { color: #94a3b8; }
        
        /* Message Dropdowns */
        .msg-menu-btn { position: absolute; top: 4px; right: 4px; background: transparent; border: none; color: var(--text-muted); cursor: pointer; opacity: 0.3; transition: opacity 0.2s; padding: 2px 4px; border-radius: 4px;}
        .msg-menu-btn:hover { background: rgba(0,0,0,0.05); }
        .msg-wrapper:hover .msg-menu-btn { opacity: 1; }
        .msg-dropdown { position: absolute; top: 25px; right: 10px; background: white; border: 1px solid var(--border); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 50; display: none; overflow: hidden; min-width: 120px;}
        .msg-dropdown button { width: 100%; text-align: left; padding: 8px 12px; border: none; background: white; cursor: pointer; font-size: 0.85rem; transition: 0.2s;}
        .msg-dropdown button:hover { background: var(--hover-bg); }
        .msg-dropdown button.delete-btn:hover { color: #ef4444; }

        /* Header Dropdown */
        #chatOptionsDropdown { position: absolute; top: 45px; right: 0; background: white; border: 1px solid var(--border); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 50; display: none; min-width: 160px; overflow: hidden;}
        #chatOptionsDropdown button { width: 100%; text-align: left; padding: 10px 15px; border: none; background: white; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;}
        #chatOptionsDropdown button:hover { background: var(--hover-bg); color: #ef4444;}

        /* Teams Input Area & Emoji Picker */
        .input-area { padding: 0 20px 20px; background: transparent; display: flex; flex-direction: column; z-index: 10; position: relative;}
        
        #filePreview { display: none; align-items: center; justify-content: space-between; background: white; border: 1px solid var(--border); padding: 8px 15px; border-radius: 6px; margin-bottom: 8px; font-size: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        
        .input-wrapper { background: #fff; border-radius: 6px; display: flex; align-items: flex-end; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid var(--border); padding: 8px 12px; min-height: 50px;}
        .input-wrapper input { flex: 1; padding: 8px 10px; border: none; outline: none; background: transparent; font-size: 0.95rem; }
        .input-tools { display: flex; align-items: center; gap: 8px; margin-left: 10px; padding-bottom: 2px;}
        .btn-tool { background: transparent; color: var(--text-muted); width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: 0.2s; font-size: 1.2rem;}
        .btn-tool:hover { background: var(--hover-bg); color: var(--primary); }
        .btn-send { color: var(--primary); }
        
        #emojiPicker { display: none; position: absolute; bottom: 75px; right: 20px; background: white; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 260px; height: 200px; overflow-y: auto; padding: 10px; z-index: 100; grid-template-columns: repeat(6, 1fr); gap: 8px; text-align: center; font-size: 1.3rem; }
        #emojiPicker span { cursor: pointer; transition: transform 0.1s; }
        #emojiPicker span:hover { transform: scale(1.3); }

        /* MEET SECTION UI */
        #meet_view { display: none; flex-direction: column; padding: 40px; max-width: 900px; margin: 0 auto; width: 100%; overflow-y: auto; flex: 1; }
        
        .meet-hero-btn { flex: 1; background: white; border: 1px solid var(--border); padding: 15px 20px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .meet-hero-btn:hover { background: var(--hover-bg); color: var(--primary); border-color: var(--primary); }
        .meet-hero-btn.primary { background: var(--primary); color: white; border-color: var(--primary); }
        .meet-hero-btn.primary:hover { background: var(--primary-hover); }
        .meet-hero-btn i { font-size: 1.2rem; }

        /* PEOPLE STYLES */
        .people-card { display: flex; align-items: center; justify-content: space-between; padding: 15px 30px; border-bottom: 1px solid var(--border); transition: background 0.2s; }
        .people-card:hover { background: var(--hover-bg); }
        .people-info { display: flex; align-items: center; gap: 15px; }
        .people-btn { background: var(--bg-light); color: var(--text-dark); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--border); transition: 0.2s; font-size: 1.2rem;}
        .people-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: scale(1.05); }

        /* CALENDAR STYLES */
        .calendar-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border); }
        .calendar-title { font-size: 1.4rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 10px;}
        .calendar-title i { color: var(--primary); }
        .cal-nav-btn { background: white; border: 1px solid var(--border); padding: 8px 15px; border-radius: 4px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
        .cal-nav-btn:hover { background: var(--hover-bg); color: var(--primary); }
        .cal-primary-btn { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 4px; font-size: 0.9rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .cal-primary-btn:hover { background: var(--primary-hover); }
        
        .calendar-grid-container { display: flex; flex-direction: column; flex: 1; overflow: hidden; background: white; }
        .calendar-grid { display: flex; flex: 1; overflow-y: auto; overflow-x: auto; }
        .time-col { width: 60px; border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .time-slot { height: 60px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: flex-end; padding: 5px 8px 0 0; font-size: 0.75rem; color: var(--text-muted); }
        
        .day-cols { display: flex; flex: 1; }
        .day-col { flex: 1; min-width: 150px; border-right: 1px solid var(--border); display: flex; flex-direction: column; position: relative; }
        .day-header { padding: 15px 10px; border-bottom: 1px solid var(--border); text-align: left; height: 73px; position: sticky; top:0; background:white; z-index:10; }
        .day-num { font-size: 1.5rem; font-weight: 400; color: var(--text-dark); line-height: 1; }
        .day-name { font-size: 0.8rem; color: var(--text-muted); text-transform: capitalize; margin-top:4px;}
        .day-header.active .day-num, .day-header.active .day-name { color: var(--primary); font-weight: 700; }
        .grid-cell { height: 60px; border-bottom: 1px solid var(--border); transition: background 0.2s; cursor: pointer; position: relative; }
        .grid-cell:hover { background: var(--hover-bg); }
        
        .cal-event { background: var(--primary); color: white; padding: 4px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin: 2px 5px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; position: absolute; width: calc(100% - 10px); z-index: 5; box-shadow: 0 1px 3px rgba(0,0,0,0.2);}
        .cal-event:hover { filter: brightness(1.1); }

        /* OVERLAYS */
        #videoOverlay { display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:#0f172a; z-index:2000; flex-direction:column; }
        .video-overlay-header { padding: 12px 20px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; color: white; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px);}
        .modal { background: white; border-radius: 8px; box-shadow: 0 20px 40px -12px rgba(0,0,0,0.2);}
        
        .incoming-call-box { background: white; border-radius: 8px; padding: 30px; text-align: center; min-width: 320px; box-shadow: 0 20px 40px -12px rgba(0,0,0,0.2); border: 1px solid var(--border); animation: pulse 2s infinite;}
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(27, 90, 90, 0.3); } 70% { box-shadow: 0 0 0 15px rgba(27, 90, 90, 0); } 100% { box-shadow: 0 0 0 0 rgba(27, 90, 90, 0); } }
        
        /* Edit Mode Bar */
        #editModeBar { display: none; background: var(--bg-light); padding: 8px 20px; align-items: center; justify-content: space-between; font-size: 0.85rem; color: var(--primary); z-index:10;}

        /* Mobile Adjustments */
        #mobileBackBtn { display: none; }
        @media (max-width: 992px) {
            #mainContent { margin-left: 0; width: 100%; }
            .sidebar-secondary-teams { display: none; }
            .sidebar { width: 100%; position: absolute; height: 100%; z-index: 20; }
            .content-area { width: 100%; }
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
        <aside class="sidebar-secondary-teams">
            <div class="nav-icon active" onclick="switchMainTab('chat_view', this)">
                <i class="ri-chat-3-fill"></i>
                <span>Chat</span>
            </div>
            <div class="nav-icon" onclick="switchMainTab('meet_view', this)">
                <i class="ri-video-add-line"></i>
                <span>Meet</span>
            </div>
            <div class="nav-icon" onclick="switchMainTab('people_view', this)">
                <i class="ri-contacts-line"></i>
                <span>People</span>
            </div>
            <div class="nav-icon" onclick="switchMainTab('calendar_view', this)">
                <i class="ri-calendar-line"></i>
                <span>Calendar</span>
            </div>
            <div style="flex: 1;"></div>
            <div class="nav-icon">
                <i class="ri-gem-line"></i>
            </div>
        </aside>

        <aside class="sidebar" id="chatSidebar">
            <div class="desktop-notif-banner" id="notifBanner">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="ri-information-fill" style="color: var(--text-muted);"></i>
                    <span>Stay in the know. Turn on desktop notifications.</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button style="background: white; border: 1px solid var(--border); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; color: var(--text-dark); font-weight: 500;">Turn on</button>
                    <i class="ri-close-line" style="cursor: pointer; color: var(--text-muted);" onclick="document.getElementById('notifBanner').style.display='none'"></i>
                </div>
            </div>

            <div class="sidebar-header">
                <h2 style="font-weight:700; color:var(--text-dark); font-size: 1.4rem; letter-spacing: -0.5px;">Chat</h2>
                <div style="display: flex; gap: 6px;">
                    <button class="btn-icon-small" title="Filter"><i class="ri-menu-unfold-line"></i></button>
                    <button class="btn-icon-small" title="Meet" onclick="switchMainTab('meet_view', document.querySelectorAll('.nav-icon')[1])"><i class="ri-video-add-line"></i></button>
                    <?php if($can_create_group): ?>
                        <button class="btn-icon-small" title="New Chat" onclick="openGroupModal()"><i class="ri-edit-box-line"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="search-box">
                <i class="ri-search-line"></i>
                <input type="text" id="userSearch" placeholder="Search">
                <div id="searchResults"></div>
            </div>
            
            <div style="flex: 1; overflow-y: auto;">
                <div class="section-toggle">
                    <i class="ri-arrow-down-s-fill" style="font-size: 1rem;"></i> Recent
                </div>
                <div class="chat-list" id="chatList">
                    <div style="text-align:center; padding: 30px; color:var(--text-muted);"><i class="ri-loader-4-line ri-spin"></i> Loading...</div>
                </div>

                <div class="section-toggle" style="margin-top: 5px;">
                    <i class="ri-arrow-right-s-fill" style="font-size: 1rem;"></i> Contacts
                </div>
            </div>

            <div class="invite-btn-container">
                <button class="invite-btn">
                    <i class="ri-user-add-line"></i> Invite to Workack
                </button>
            </div>
        </aside>

        <section class="content-area" id="mainContentView">
            
            <div id="chat_view" style="display: flex; width: 100%; height: 100%;">
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); text-align:center; padding:20px; z-index:5;" id="chatAreaEmpty">
                    <i class="ri-chat-3-line" style="font-size:4rem; color: #e0e0e0; margin-bottom: 15px;"></i>
                    <h3 style="font-size: 1.2rem; color: var(--text-dark); margin-bottom: 8px; font-weight: 600;">Workack Chat</h3>
                    <p style="font-size: 0.9rem;">Select a chat to start messaging</p>
                </div>
                
                <div id="chatAreaActive" class="chat-main-column" style="display: none;"></div>
                
                <div id="groupInfoPanel" class="group-info-panel">
                    <div style="padding: 15px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: white;">
                        <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-dark);">Group Members</h3>
                        <button class="btn-icon-small" onclick="closeGroupInfo()"><i class="ri-close-line"></i></button>
                    </div>
                    <div style="padding: 15px; border-bottom: 1px solid var(--border); background: white;">
                        <button onclick="openAddMemberModal()" style="width: 100%; padding: 8px; background: white; border: 1px solid var(--primary); color: var(--primary); border-radius: 4px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                            <i class="ri-user-add-line"></i> Add people
                        </button>
                    </div>
                    <div id="groupMembersList" style="flex: 1; overflow-y: auto; padding: 10px;">
                        </div>
                </div>
            </div>

            <div id="meet_view">
                <h2 style="font-size: 1.8rem; font-weight: 600; color: var(--text-dark); margin-bottom: 25px;">Meet</h2>
                
                <div style="display: flex; gap: 15px; margin-bottom: 40px;">
                    <button class="meet-hero-btn primary" onclick="document.getElementById('createMeetingModal').style.display='flex'">
                        <i class="ri-link"></i> Create a meeting link
                    </button>
                    <button class="meet-hero-btn" onclick="document.getElementById('scheduleMeetingModal').style.display='flex'">
                        <i class="ri-calendar-event-line"></i> Schedule a meeting
                    </button>
                    <button class="meet-hero-btn" onclick="document.getElementById('joinMeetingModal').style.display='flex'">
                        <i class="ri-hashtag"></i> Join with a meeting ID
                    </button>
                </div>

                <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-dark); margin-bottom: 15px;">Meeting links</h3>
                <div id="instantMeetingsContainer">
                    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <p style="color: var(--text-muted);">No instant meeting links yet.</p>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; margin-top: 20px;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-dark);">Scheduled meetings</h3>
                    <a href="#" onclick="switchMainTab('calendar_view', document.querySelectorAll('.nav-icon')[3])" style="color: var(--text-dark); text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 5px;"><i class="ri-calendar-line"></i> View in calendar</a>
                </div>
                
                <div id="scheduledMeetingsContainer">
                    <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;">
                        <p style="color: var(--text-muted);">No scheduled meetings yet.</p>
                    </div>
                </div>
            </div>

            <div id="people_view" style="display: none; flex-direction: column; height: 100%; width: 100%;">
                <div style="padding: 25px 30px; border-bottom: 1px solid var(--border);">
                    <h2 style="font-size: 1.8rem; font-weight:700; color: var(--text-dark);">People Directory</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Connect with everyone in your designated network.</p>
                </div>
                <div style="overflow-y: auto; flex: 1; background: #fafafa;">
                    <?php foreach($all_users as $u): if($u['id'] != $my_id): ?>
                        <div class="people-card">
                            <div class="people-info">
                                <img src="<?= $u['profile_img'] ?>" class="avatar" loading="lazy" style="width: 50px; height: 50px;">
                                <div>
                                    <div style="font-weight:600; font-size: 1.05rem; color: var(--text-dark);"><?= htmlspecialchars($u['name']) ?></div>
                                    <div style="font-size:0.85rem; color:var(--text-muted);"><?= $u['role'] ?></div>
                                </div>
                            </div>
                            <button class="people-btn" onclick="startChat(<?= $u['id'] ?>)" title="Message">
                                <i class="ri-chat-3-line"></i>
                            </button>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <div id="calendar_view" style="display: none; flex-direction: column; height: 100%; width: 100%; background: white; overflow: hidden;">
                <div class="calendar-header">
                    <div class="calendar-title">
                        <i class="ri-calendar-line"></i> Calendar
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <button class="cal-nav-btn" onclick="resetCalendarToToday()">Today</button>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <button class="btn-icon-small" onclick="shiftCalendarWeek(-1)"><i class="ri-arrow-left-s-line"></i></button>
                            <button class="btn-icon-small" onclick="shiftCalendarWeek(1)"><i class="ri-arrow-right-s-line"></i></button>
                        </div>
                        
                        <div style="position:relative; display:inline-flex; align-items:center; cursor: pointer;" onclick="document.getElementById('calendarMonthPicker').showPicker()">
                            <span id="calendarMonthYear" style="font-weight: 600; font-size: 1.1rem; margin-right: 20px; pointer-events: none;">... <i class="ri-arrow-down-s-line" style="font-size: 0.9rem; color: var(--text-muted);"></i></span>
                            <input type="month" id="calendarMonthPicker" onchange="changeCalendarMonth(this.value)" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; pointer-events:none; border:none; padding:0; margin:0;">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button class="cal-nav-btn" onclick="document.getElementById('joinMeetingModal').style.display='flex'"><i class="ri-hashtag"></i> Join with an ID</button>
                        <button class="cal-primary-btn" onclick="document.getElementById('scheduleMeetingModal').style.display='flex'"><i class="ri-add-line"></i> New meeting</button>
                        <select id="calendarViewSelect" onchange="toggleWeekendView(this.value)" class="cal-nav-btn" style="border:none; background:transparent; appearance: none; outline:none; cursor:pointer; font-weight:600;">
                            <option value="work_week">Work week</option>
                            <option value="week">Week</option>
                        </select>
                    </div>
                </div>

                <div class="calendar-grid-container">
                    <div class="calendar-grid">
                        <div class="time-col">
                            <div class="day-header" style="height: 73px; border-bottom: none; position: sticky; top:0; background:white; z-index:10;"></div>
                            <?php 
                            $times = ['12 AM','1 AM','2 AM','3 AM','4 AM','5 AM','6 AM','7 AM','8 AM','9 AM','10 AM','11 AM','12 PM','1 PM','2 PM','3 PM','4 PM','5 PM','6 PM','7 PM','8 PM','9 PM','10 PM','11 PM'];
                            foreach($times as $t): ?>
                                <div class="time-slot"><?php echo $t; ?></div>
                            <?php endforeach; ?>
                        </div>

                        <div class="day-cols" id="calendarDayCols">
                            </div>
                    </div>
                </div>
            </div>

        </section>
    </div>
</main>

<div id="createMeetingModal" class="modal-overlay">
    <div class="modal" style="width: 350px; padding: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
            <h3 style="font-size:1.1rem; font-weight: 600;">Give your meeting a title</h3>
            <button class="btn-icon-small" onclick="document.getElementById('createMeetingModal').style.display='none'"><i class="ri-close-line"></i></button>
        </div>
        <input type="text" id="newMeetTitle" value="Meeting with <?php echo htmlspecialchars($my_username); ?>" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; margin-bottom:15px; outline:none; font-size: 0.95rem;">
        <button onclick="createAndCopyMeetLink()" style="width:100%; padding:10px; background:var(--primary); color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size: 0.95rem;">Create and copy link</button>
    </div>
</div>

<div id="joinMeetingModal" class="modal-overlay">
    <div class="modal" style="width: 350px; padding: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
            <h3 style="font-size:1.1rem; font-weight: 600;">Join a meeting with an ID</h3>
            <button class="btn-icon-small" onclick="document.getElementById('joinMeetingModal').style.display='none'"><i class="ri-close-line"></i></button>
        </div>
        <label style="font-size: 0.85rem; color: var(--text-dark); margin-bottom: 5px; display: block;">Meeting ID <i class="ri-information-line"></i></label>
        <input type="text" id="joinMeetId" placeholder="Type a meeting ID" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; margin-bottom:5px; outline:none; font-size: 0.95rem;">
        <p id="joinMeetError" style="color: #ef4444; font-size: 0.8rem; margin-bottom: 15px; display: none;"><i class="ri-error-warning-fill"></i> meetingID is a required field</p>
        
        <label style="font-size: 0.85rem; color: var(--text-dark); margin-bottom: 5px; display: block;">Type a meeting passcode</label>
        <input type="text" placeholder="Type a meeting passcode" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; margin-bottom:20px; outline:none; font-size: 0.95rem; background: var(--bg-light);">
        
        <button onclick="joinManualMeet()" style="width:100%; padding:10px; background:#e2e8f0; color:var(--text-muted); border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size: 0.95rem;" id="joinMeetBtn">Join meeting</button>
    </div>
</div>

<div id="scheduleMeetingModal" class="modal-overlay">
    <div class="modal" style="width: 800px; max-width: 95vw; padding: 0; overflow: hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding: 15px 20px; border-bottom: 1px solid var(--border);">
            <div style="display:flex; align-items:center; gap:10px;">
                <i class="ri-calendar-event-fill" style="color: var(--primary); font-size: 1.2rem;"></i>
                <h3 style="font-size:1.1rem; font-weight: 600;">New meeting <span style="font-weight: 400; color: var(--text-muted); font-size: 0.9rem;">Details</span></h3>
            </div>
            <div style="display:flex; gap: 10px;">
                <button onclick="saveScheduledMeeting()" style="background:var(--primary); color:white; border:none; padding:6px 16px; border-radius:4px; font-weight:600; cursor:pointer;">Save</button>
                <button onclick="document.getElementById('scheduleMeetingModal').style.display='none'" style="background:white; border:1px solid var(--border); padding:6px 16px; border-radius:4px; font-weight:600; cursor:pointer;">Close</button>
            </div>
        </div>
        <div style="padding: 20px; background: #faf9f8;">
            <input type="text" id="schTitle" placeholder="Add title" style="width:100%; padding:10px 0; border:none; border-bottom:1px solid var(--primary); background:transparent; font-size: 1.2rem; outline:none; margin-bottom: 15px;">
            
            <div style="max-height: 180px; overflow-y: auto; border: 1px solid var(--border); border-radius: 4px; padding: 10px; background: white; margin-bottom: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 600;">Add required attendees (Select multiple):</p>
                <div id="schUserList" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <?php foreach($all_users as $u): if($u['id'] != $my_id): ?>
                        <label style="display: flex; align-items: center; cursor: pointer; font-size: 0.95rem; padding: 5px; border-radius: 4px; transition: 0.2s;" onmouseover="this.style.background='var(--bg-light)'" onmouseout="this.style.background='transparent'">
                            <input type="checkbox" class="sch-user-checkbox" value="<?php echo $u['id']; ?>" style="margin-right: 10px; width: 16px; height: 16px; accent-color: var(--primary);">
                            <img src="<?php echo $u['profile_img']; ?>" loading="lazy" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; object-fit:cover;">
                            <span><?php echo htmlspecialchars($u['name']); ?></span> 
                            <span style="color:var(--text-muted); font-size:0.75rem; margin-left: 5px;">(<?php echo htmlspecialchars($u['role']); ?>)</span>
                        </label>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
                <input type="date" id="schDate" value="<?php echo date('Y-m-d'); ?>" style="padding:8px; border:none; border-radius:4px; background:white; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <select id="schTime" style="padding:8px; border:none; border-radius:4px; background:white; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <?php 
                    for ($h = 0; $h < 24; $h++) {
                        $hf = $h == 0 ? 12 : ($h > 12 ? $h - 12 : $h);
                        $ap = $h < 12 ? 'AM' : 'PM';
                        $tsStr = sprintf("%02d:00 %s", $hf, $ap);
                        echo "<option value=\"$tsStr\">$tsStr</option>";
                    }
                    ?>
                </select>
            </div>
            <textarea id="schDetails" placeholder="Type details for this new meeting" style="width:100%; height:120px; padding:15px; border:1px solid var(--border); border-radius:4px; background:white; resize:none; outline:none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"></textarea>
        </div>
    </div>
</div>

<div id="videoOverlay">
    <div class="video-overlay-header">
        <h3 style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
            <i class="ri-record-circle-fill" style="color:#ef4444; font-size:0.9rem; animation: pulse 1s infinite;"></i> <span id="callTypeLabel">Live Meeting</span>
        </h3>
        <button onclick="closeCall()" style="background:#ef4444; border:none; color:white; padding:6px 14px; border-radius:4px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:6px;">
            <i class="ri-phone-x-line"></i> End
        </button>
    </div>
    <div id="jitsiContainer" style="flex:1;"></div>
</div>

<div id="incomingCallModal" class="modal-overlay">
    <div class="incoming-call-box">
        <img id="incomingCallerAvatar" src="" style="width:80px; height:80px; border-radius:50%; margin:0 auto 15px auto; object-fit:cover; border:3px solid var(--primary);">
        <h3 id="incomingCallerName" style="font-size:1.2rem; margin-bottom:5px;">Incoming Call</h3>
        <p id="incomingCallLabel" style="color:var(--text-muted); font-size:0.9rem;">Video Call</p>
        <div style="display:flex; gap:16px; justify-content:center; margin-top:25px;">
            <button onclick="declineIncomingCall()" style="background:#ef4444; color:white; width:45px; height:45px; border-radius:50%; border:none; cursor:pointer; font-size:1.3rem;"><i class="ri-phone-fill"></i></button>
            <button onclick="acceptIncomingCall()" style="background:#22c55e; color:white; width:45px; height:45px; border-radius:50%; border:none; cursor:pointer; font-size:1.3rem; animation: jump 1s infinite;"><i class="ri-phone-fill"></i></button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="groupModal">
    <div class="modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h3 style="font-size:1.2rem; font-weight: 600;">Create Group</h3>
            <button class="btn-icon" style="width:30px; height:30px;" onclick="closeGroupModal()"><i class="ri-close-line" style="font-size:1.2rem;"></i></button>
        </div>
        <input type="text" id="groupName" placeholder="Group Subject" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; margin-bottom:15px; outline:none; background:var(--bg-light);">
        <input type="text" id="memberSearch" placeholder="Search members to add..." oninput="searchForGroup(this.value)" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; margin-bottom:10px; outline:none; font-size:0.9rem; background:var(--bg-light);">
        <div id="groupUserList" style="max-height:200px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; margin-bottom:15px;"></div>
        <button onclick="createGroup()" style="width:100%; padding:10px; background:var(--primary); color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600; font-size: 0.95rem;">Create</button>
    </div>
</div>

<div class="modal-overlay" id="addMemberModal">
    <div class="modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h3 style="font-size:1.2rem; font-weight: 600;">Add to Group</h3>
            <button class="btn-icon" style="width:30px; height:30px;" onclick="closeAddMemberModal()"><i class="ri-close-line" style="font-size:1.2rem;"></i></button>
        </div>
        <input type="text" id="addMemberSearch" placeholder="Search members to add..." oninput="searchForAddMember(this.value)" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; margin-bottom:10px; outline:none; font-size:0.9rem; background:var(--bg-light);">
        <div id="addMemberUserList" style="max-height:200px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; margin-bottom:15px;"></div>
        <button onclick="submitAddMembers()" style="width:100%; padding:10px; background:var(--primary); color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600; font-size: 0.95rem;">Add Members</button>
    </div>
</div>

<script>
    let activeConvId = null;
    let editingMsgId = null;
    let masterPollInterval = null; 
    let isFetchingMessages = false;
    let isSidebarFetching = false;
    let lastFetchedMsgId = 0;
    let isUserScrolling = false;
    let selectedMembers = new Set();
    let selectedAddMembers = new Set();
    let jitsiApi = null;
    const myUserName = "<?php echo htmlspecialchars($my_username, ENT_QUOTES, 'UTF-8'); ?>";
    let currentIncomingCall = null;
    let searchDebounce = null;
    let typingTimer = null;
    let isGroupChat = false;

    // --- Tab Switching Logic (Secondary Sidebar) ---
    function switchMainTab(tabId, el) {
        document.querySelectorAll('.sidebar-secondary-teams .nav-icon').forEach(n => n.classList.remove('active'));
        el.classList.add('active');

        ['chat_view', 'people_view', 'calendar_view', 'meet_view'].forEach(id => {
            const domEl = document.getElementById(id);
            if(domEl) domEl.style.display = 'none';
        });
        
        document.getElementById(tabId).style.display = 'flex';
        
        const chatSidebar = document.getElementById('chatSidebar');
        if(tabId !== 'chat_view') {
            chatSidebar.style.display = 'none';
        } else {
            chatSidebar.style.display = 'flex';
            if(activeConvId) fetchMessages(false);
        }

        if (tabId === 'calendar_view') {
            renderCalendar();
        }
        
        if (tabId === 'meet_view') {
            loadMeetingHistory();
        }
    }

    // --- DELETE MEETING HISTORY ---
    function deleteMeetingHistory(id, type) {
        Swal.fire({
            title: 'Delete this meeting?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Delete'
        }).then((res) => {
            if(res.isConfirmed) {
                let fd = new FormData();
                fd.append('action', 'delete_meeting');
                fd.append('meet_id', id);
                fd.append('meet_type', type);
                fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
                    loadMeetingHistory();
                    if (document.getElementById('calendar_view').style.display !== 'none') {
                        renderCalendar();
                    }
                });
            }
        });
    }

    // --- LOAD MEET HISTORY (Links & Scheduled) ---
    function loadMeetingHistory() {
        let fd = new FormData();
        fd.append('action', 'fetch_meeting_history');
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            // 1. Instant Links
            let instHtml = '';
            if (data.instant && data.instant.length > 0) {
                data.instant.forEach(m => {
                    instHtml += `<div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <i class="ri-link" style="font-size: 2rem; color: var(--primary);"></i>
                                        <div>
                                            <p style="font-weight: 600; margin-bottom: 5px;">${escapeHTML(m.title)}</p>
                                            <span style="font-size: 0.85rem; color: var(--text-muted);">${m.created_at}</span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button onclick="openEmbeddedMeeting('${m.meet_link}', 'video')" style="background: white; border: 1px solid var(--border); padding: 6px 20px; border-radius: 4px; font-weight: 600; cursor: pointer;">Join</button>
                                        <button onclick="navigator.clipboard.writeText('${m.meet_link}'); Swal.fire('Copied!', 'Link copied to clipboard', 'success');" style="background: white; border: 1px solid var(--border); padding: 6px 20px; border-radius: 4px; font-weight: 600; cursor: pointer;">Copy ID</button>
                                        <button onclick="deleteMeetingHistory(${m.id}, 'instant')" class="btn-icon-small" style="color:#ef4444;"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                </div>`;
                });
                document.getElementById('instantMeetingsContainer').innerHTML = instHtml;
            } else {
                document.getElementById('instantMeetingsContainer').innerHTML = `<div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);"><p style="color: var(--text-muted);">No instant meeting links yet.</p></div>`;
            }

            // 2. Scheduled Meetings
            let schHtml = '';
            if (data.scheduled && data.scheduled.length > 0) {
                data.scheduled.forEach(m => {
                    let delBtn = m.is_owner ? `<button onclick="deleteMeetingHistory(${m.id}, 'scheduled')" class="btn-icon-small" style="color:#ef4444; border: 1px solid var(--border); background: white;"><i class="ri-delete-bin-line"></i></button>` : '';
                    schHtml += `<div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 25px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; gap: 20px;">
                                        <div style="text-align: center; border-right: 1px solid var(--border); padding-right: 20px;">
                                            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">${m.month}</div>
                                            <div style="font-size: 1.5rem; font-weight: 400; color: var(--text-dark);">${m.day}</div>
                                        </div>
                                        <div>
                                            <h4 style="font-weight: 600; margin-bottom: 5px;">${escapeHTML(m.title)}</h4>
                                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">${m.formatted_date} &bull; ${m.meet_time}</p>
                                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                                <button onclick="openEmbeddedMeeting('${m.meet_link}', 'video')" style="background: white; border: 1px solid var(--border); padding: 6px 20px; border-radius: 4px; font-weight: 600; cursor: pointer;">Join</button>
                                                <button onclick="navigator.clipboard.writeText('${m.meet_link}'); Swal.fire('Copied!', 'Link copied to clipboard', 'success');" style="background: white; border: 1px solid var(--border); padding: 6px 20px; border-radius: 4px; font-weight: 600; cursor: pointer;">Copy ID</button>
                                                ${delBtn}
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 120px; height: 80px; background: var(--outgoing-bg); border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                        <i class="ri-calendar-event-fill" style="font-size: 2rem; color: var(--primary); opacity: 0.5;"></i>
                                    </div>
                                </div>`;
                });
                document.getElementById('scheduledMeetingsContainer').innerHTML = schHtml;
            } else {
                document.getElementById('scheduledMeetingsContainer').innerHTML = `<div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;"><p style="color: var(--text-muted);">No scheduled meetings yet.</p></div>`;
            }
        }).catch(err => console.error(err));
    }


    // --- CALENDAR LOGIC ---
    let currentCalDate = new Date();
    let calViewMode = 'work_week'; 

    function toggleWeekendView(mode) {
        calViewMode = mode;
        renderCalendar();
    }

    function shiftCalendarWeek(direction) {
        currentCalDate.setDate(currentCalDate.getDate() + (direction * 7));
        renderCalendar();
    }

    function resetCalendarToToday() {
        currentCalDate = new Date();
        renderCalendar();
    }

    function changeCalendarMonth(val) {
        if(!val) return;
        let parts = val.split('-'); // YYYY-MM
        currentCalDate = new Date(parts[0], parts[1] - 1, 1);
        renderCalendar();
    }

    function renderCalendar() {
        let colsContainer = document.getElementById('calendarDayCols');
        colsContainer.innerHTML = ''; 

        let dayOfWeek = currentCalDate.getDay();
        let diff = currentCalDate.getDate() - dayOfWeek + (dayOfWeek == 0 ? -6 : 1);
        let startOfWeek = new Date(currentCalDate);
        startOfWeek.setDate(diff);

        let daysToRender = calViewMode === 'work_week' ? 5 : 7;
        let dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        let today = new Date();
        
        for (let i = 0; i < daysToRender; i++) {
            let renderDate = new Date(startOfWeek);
            renderDate.setDate(startOfWeek.getDate() + i);
            
            let isToday = (renderDate.getDate() === today.getDate() && renderDate.getMonth() === today.getMonth() && renderDate.getFullYear() === today.getFullYear());
            let activeClass = isToday ? 'active' : '';

            let colHtml = `
                <div class="day-col" id="cal-col-${i}">
                    <div class="day-header ${activeClass}">
                        <div class="day-num">${renderDate.getDate()}</div>
                        <div class="day-name">${dayNames[i]}</div>
                    </div>
            `;
            
            for(let j=0; j<24; j++) {
                let hourFormat = j === 0 ? 12 : (j > 12 ? j - 12 : j);
                let amPm = j < 12 ? 'AM' : 'PM';
                let timeString = `${String(hourFormat).padStart(2,'0')}:00 ${amPm}`;
                let dateString = `${renderDate.getFullYear()}-${String(renderDate.getMonth()+1).padStart(2,'0')}-${String(renderDate.getDate()).padStart(2,'0')}`;
                
                colHtml += `<div class="grid-cell" id="cell-${dateString}-${j}" onclick="openScheduleModal('${dateString}', '${timeString}')"></div>`;
            }
            colHtml += `</div>`;
            colsContainer.insertAdjacentHTML('beforeend', colHtml);
        }

        let monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        let midWeek = new Date(startOfWeek);
        midWeek.setDate(startOfWeek.getDate() + 3); 
        
        let monthInput = document.getElementById('calendarMonthPicker');
        monthInput.value = `${midWeek.getFullYear()}-${String(midWeek.getMonth()+1).padStart(2,'0')}`;
        document.getElementById('calendarMonthYear').innerHTML = `${monthNames[midWeek.getMonth()]} ${midWeek.getFullYear()} <i class="ri-arrow-down-s-line" style="font-size: 0.9rem; color: var(--text-muted);"></i>`;

        let endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + daysToRender - 1);

        let startStr = `${startOfWeek.getFullYear()}-${String(startOfWeek.getMonth()+1).padStart(2,'0')}-${String(startOfWeek.getDate()).padStart(2,'0')}`;
        let endStr = `${endOfWeek.getFullYear()}-${String(endOfWeek.getMonth()+1).padStart(2,'0')}-${String(endOfWeek.getDate()).padStart(2,'0')}`;

        let fd = new FormData();
        fd.append('action', 'get_calendar_events');
        fd.append('start_date', startStr);
        fd.append('end_date', endStr);

        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r=>r.json())
        .then(events => {
            if (!events || events.length === 0) return;
            
            events.forEach(ev => {
                let timeMatch = ev.meet_time.match(/(\d+):00 (AM|PM)/);
                if (timeMatch) {
                    let h = parseInt(timeMatch[1]);
                    let ampm = timeMatch[2];
                    let j = (h === 12 ? (ampm === 'AM' ? 0 : 12) : (ampm === 'PM' ? h + 12 : h));
                    
                    let targetCell = document.getElementById(`cell-${ev.meet_date}-${j}`);
                    if (targetCell) {
                        targetCell.innerHTML += `<div class="cal-event" onclick="event.stopPropagation(); openEmbeddedMeeting('${ev.meet_link}','video')" title="${ev.title}">${ev.title}</div>`;
                    }
                }
            });
        }).catch(err => console.log('Calendar fetch issue:', err));
    }

    function openScheduleModal(date, time) {
        document.getElementById('schDate').value = date;
        let selectOptions = document.getElementById('schTime').options;
        for(let i=0; i < selectOptions.length; i++) {
            if(selectOptions[i].value === time) {
                document.getElementById('schTime').selectedIndex = i;
                break;
            }
        }
        document.getElementById('scheduleMeetingModal').style.display='flex';
    }


    // --- EMOJI PICKER LOGIC ---
    const emojis = ['😀','😃','😄','😁','😆','😅','😂','🤣','🥲','☺️','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾'];

    function toggleEmojiPicker(e) {
        e.stopPropagation();
        let picker = document.getElementById('emojiPicker');
        if (!picker) return;
        if (picker.style.display === 'grid') {
            picker.style.display = 'none';
        } else {
            picker.style.display = 'grid';
            if (picker.innerHTML.trim() === '') {
                picker.innerHTML = emojis.map(emo => `<span onclick="insertEmoji('${emo}')">${emo}</span>`).join('');
            }
        }
    }

    function insertEmoji(emo) {
        let input = document.getElementById('msgInput');
        if(input) {
            input.value += emo;
            input.focus();
        }
    }

    document.addEventListener('click', (e) => {
        let picker = document.getElementById('emojiPicker');
        if (picker && picker.style.display === 'grid' && !e.target.closest('#emojiPicker')) {
            picker.style.display = 'none';
        }
        document.querySelectorAll('.msg-dropdown, #chatOptionsDropdown').forEach(d => {
            if(!e.target.closest('.msg-menu-btn') && !e.target.closest('.header-actions')) {
                d.style.display = 'none';
            }
        });
    });

    // --- Meet Handlers ---
    document.getElementById('joinMeetId').addEventListener('input', function(e) {
        const btn = document.getElementById('joinMeetBtn');
        const err = document.getElementById('joinMeetError');
        if(e.target.value.trim().length > 0) {
            btn.style.background = 'var(--primary)';
            btn.style.color = 'white';
            err.style.display = 'none';
            e.target.style.borderColor = 'var(--border)';
        } else {
            btn.style.background = '#e2e8f0';
            btn.style.color = 'var(--text-muted)';
        }
    });

    function createAndCopyMeetLink() {
        let title = document.getElementById('newMeetTitle').value || 'Meeting';
        let id = 'Workack-Meet-' + Math.random().toString(36).substring(2, 10);
        navigator.clipboard.writeText(id).then(() => {
            Swal.fire({
                title: 'Copied!',
                text: 'Meeting ID copied to clipboard: ' + id,
                icon: 'success',
                confirmButtonColor: 'var(--primary)'
            }).then(() => {
                document.getElementById('createMeetingModal').style.display = 'none';
                
                let fd = new FormData();
                fd.append('action', 'save_instant_meeting');
                fd.append('title', title);
                fd.append('link', id);
                fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
                    if (document.getElementById('meet_view').style.display !== 'none') {
                        loadMeetingHistory();
                    }
                    openEmbeddedMeeting(id, 'video');
                });
            });
        });
    }

    function joinManualMeet() {
        let input = document.getElementById('joinMeetId');
        let id = input.value.trim();
        if(!id) {
            input.style.borderColor = '#ef4444';
            document.getElementById('joinMeetError').style.display = 'block';
            return;
        }
        document.getElementById('joinMeetingModal').style.display = 'none';
        openEmbeddedMeeting(id, 'video');
    }

    function saveScheduledMeeting() {
        let title = document.getElementById('schTitle').value || 'Scheduled Meeting';
        
        let checkboxes = document.querySelectorAll('.sch-user-checkbox:checked');
        let selectedUsers = Array.from(checkboxes).map(cb => cb.value);
        
        let date = document.getElementById('schDate').value;
        let time = document.getElementById('schTime').value;
        
        if(selectedUsers.length === 0) {
            Swal.fire('Error', 'Please select at least one person to invite.', 'error');
            return;
        }

        let fd = new FormData();
        fd.append('action', 'create_scheduled_meeting');
        fd.append('title', title);
        fd.append('date', date);
        fd.append('time', time);
        fd.append('members', JSON.stringify(selectedUsers));
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(async r => {
            let text = await r.text();
            try {
                let data = JSON.parse(text);
                if(data.status === 'success') {
                    document.getElementById('scheduleMeetingModal').style.display = 'none';
                    Swal.fire('Success', 'Meeting scheduled and invite sent via chat!', 'success');
                    
                    document.getElementById('schTitle').value = '';
                    checkboxes.forEach(cb => cb.checked = false); 
                    
                    // Refresh Calendar immediately if open
                    if (document.getElementById('calendar_view').style.display !== 'none') {
                        renderCalendar();
                    }

                    // Refresh history if Meet View is open
                    if (document.getElementById('meet_view').style.display !== 'none') {
                        loadMeetingHistory();
                    }
                    
                    // Switch back to chat to see the message
                    switchMainTab('chat_view', document.querySelectorAll('.nav-icon')[0]);
                    loadConversation(data.conversation_id);
                } else {
                    Swal.fire('Error', 'Could not schedule meeting. ' + (data.message || ''), 'error');
                }
            } catch(e) {
                console.error("JSON parse error on save: ", text);
                Swal.fire('Error', 'Failed to save to database. Check console.', 'error');
            }
        })
        .catch(err => {
            console.error('Save Meeting Error:', err);
            Swal.fire('Error', 'Failed to connect to the server.', 'error');
        });
    }

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
                let unread = c.unread > 0 ? `<span style="background:var(--primary); color:white; font-size:0.7rem; font-weight:bold; padding:2px 6px; border-radius:10px;">${c.unread}</span>` : '';
                let msgText = c.last_msg || '';
                
                html += `<div class="chat-item ${active}" onclick="loadConversation(${c.conversation_id})">
                            <div style="position:relative;">
                                <img src="${c.avatar}" class="avatar" loading="lazy" style="margin:0 12px 0 0;">
                                <span style="position:absolute; bottom:2px; right:10px; width:12px; height:12px; border:2px solid ${active ? 'white' : 'var(--bg-light)'}; border-radius:50%; background-color:#22c55e;"></span>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                                    <div style="font-weight:400; color:var(--text-dark); font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.name}</div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);">${c.time}</div>
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
        if(activeConvId === convId) return; 
        
        activeConvId = convId;
        editingMsgId = null;
        lastFetchedMsgId = 0; 
        isUserScrolling = false;
        isGroupChat = false; // Reset
        document.getElementById('groupInfoPanel').style.display = 'none';

        toggleMobileSidebar();

        document.getElementById('chatAreaEmpty').style.display = 'none';
        const activeArea = document.getElementById('chatAreaActive');
        activeArea.style.display = 'flex';

        activeArea.innerHTML = `
            <div class="chat-header" id="chatHeader">
                <div style="display:flex; align-items:center;">
                    <button id="mobileBackBtn" class="btn-icon" style="margin-right:8px;" onclick="backToList()"><i class="ri-arrow-left-line"></i></button>
                    <div style="position:relative;">
                         <img src="" id="headerAvatar" class="avatar" loading="lazy" style="width:36px;height:36px;margin:0;border:none;">
                         <span style="position:absolute; bottom:0; right:-2px; width:12px; height:12px; border:2px solid white; border-radius:50%; background-color:#22c55e;"></span>
                    </div>
                    
                    <h3 id="headerName" style="font-size:1.05rem; color:var(--text-dark); margin:0 0 0 12px; line-height:1.2; font-weight:600;">Loading...</h3>
                    
                    <div class="header-nav">
                        <div class="header-nav-item active" onclick="switchInnerTab('chat')">Chat</div>
                        <div class="header-nav-item" onclick="switchInnerTab('files')">Files</div>
                        <div class="header-nav-item" onclick="switchInnerTab('photos')">Photos</div>
                    </div>
                    
                    <span id="typingIndicator" style="font-size:0.8rem; color:var(--primary); height:14px; font-style:italic; margin-left: 15px;"></span>
                </div>
                
                <div class="header-actions">
                    <button class="btn-icon" onclick="startCall('video')" title="Video Call"><i class="ri-vidicon-line"></i></button>
                    <button class="btn-icon" onclick="startCall('audio')" title="Voice Call"><i class="ri-phone-line"></i></button>
                    <button class="btn-icon" id="headerInfoBtn" style="display:none;" onclick="toggleGroupInfo()" title="Group Info"><i class="ri-information-line"></i></button>
                    <button class="btn-icon" onclick="toggleHeaderMenu(event)"><i class="ri-more-fill"></i></button>
                    <div id="chatOptionsDropdown">
                        <button onclick="clearDeleteChat('clear')"><i class="ri-eraser-line"></i> Clear Chat</button>
                        <button onclick="clearDeleteChat('delete')"><i class="ri-delete-bin-line"></i> Delete Chat</button>
                    </div>
                </div>
            </div>
            
            <div id="chatMessagesContainer" style="display:flex; flex-direction:column; flex:1; height:100%; overflow:hidden;">
                <div class="messages-box" id="msgBox" onscroll="handleScroll()"></div>
                
                <div id="editModeBar">
                    <div style="display:flex; align-items:center; gap:8px;"><i class="ri-edit-2-fill"></i> <span>Editing message</span></div>
                    <i class="ri-close-line" style="cursor:pointer; font-size:1.2rem;" onclick="cancelEdit()"></i>
                </div>
                
                <div class="input-area">
                    <div id="filePreview">
                        <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                            <i class="ri-file-text-fill" style="color: var(--primary); font-size: 1.2rem;"></i>
                            <span id="filePreviewName" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;">filename.pdf</span>
                        </div>
                        <button class="btn-icon-small" onclick="clearFile()" style="width: 24px; height: 24px;"><i class="ri-close-line" style="color: #ef4444;"></i></button>
                    </div>
                    <div class="input-wrapper">
                        <input type="file" id="fileUpload" hidden onchange="queueFile(this)">
                        <input type="text" id="msgInput" placeholder="Type a new message" onkeypress="if(event.key === 'Enter') submitMessage()">
                        <div class="input-tools">
                            <button class="btn-tool" title="Format"><i class="ri-format-clear"></i></button>
                            <label for="fileUpload" class="btn-tool" title="Attach file"><i class="ri-attachment-2"></i></label>
                            
                            <button class="btn-tool" title="Emoji" onclick="toggleEmojiPicker(event)"><i class="ri-emotion-line"></i></button>
                            
                            <button class="btn-tool btn-send" onclick="submitMessage()" title="Send"><i class="ri-send-plane-2-fill"></i></button>
                        </div>
                    </div>
                    <div id="emojiPicker"></div>
                </div>
            </div>

            <div id="chatFilesContainer" style="display:none; flex-direction:column; flex:1; height:100%; align-items:center; justify-content:center; text-align:center; background:white;">
                <div style="width: 140px; height: 140px; background: linear-gradient(135deg, #eefcfd, #f5f5f5); border-radius: 24px; display:flex; align-items:center; justify-content:center; margin-bottom: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                    <i class="ri-folder-upload-fill" style="font-size: 5rem; color: var(--primary);"></i>
            <div id="chatFilesContainer" style="display:none; flex-direction:column; flex:1; height:100%; background:white; overflow-y:auto; padding:30px;">
                <div id="filesEmptyState" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center;">
                    <div style="width: 140px; height: 140px; background: linear-gradient(135deg, #e3e5fa, #f5f5f5); border-radius: 24px; display:flex; align-items:center; justify-content:center; margin-bottom: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                        <i class="ri-folder-upload-fill" style="font-size: 5rem; color: var(--primary);"></i>
                    </div>
                    <h3 style="font-size: 1.25rem; color: var(--text-dark); margin-bottom: 8px; font-weight: 700;">Share files in this chat</h3>
                    <p style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 25px;">When you upload files to this files tab, they will show up in chat.</p>
                    <button onclick="document.getElementById('fileUpload').click()" style="background:var(--primary); color:white; border:none; padding:10px 24px; border-radius:6px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px; font-size:0.95rem;">
                        <i class="ri-upload-2-line"></i> Upload
                    </button>
                </div>
                
                <div id="filesContent" style="display:none; width:100%; flex-direction:column;">
                    <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
                        <button onclick="document.getElementById('fileUpload').click()" style="background:var(--bg-light); color:var(--text-dark); border:1px solid var(--border); padding:6px 15px; border-radius:4px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:5px; font-size:0.85rem;"><i class="ri-upload-2-line"></i> Upload</button>
                    </div>
                    <div id="filesList" style="display:flex; flex-direction:column; gap:10px;"></div>
                </div>
            </div>
            
            <div id="chatPhotosContainer" style="display:none; flex-direction:column; flex:1; height:100%; background:white; overflow-y:auto; padding:30px;">
                <div id="photosEmptyState" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center;">
                    <div style="width: 140px; height: 140px; background: linear-gradient(135deg, #eefcfd, #f5f5f5); border-radius: 24px; display:flex; align-items:center; justify-content:center; margin-bottom: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                    <div style="width: 140px; height: 140px; background: linear-gradient(135deg, #e3e5fa, #f5f5f5); border-radius: 24px; display:flex; align-items:center; justify-content:center; margin-bottom: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                        <i class="ri-image-2-fill" style="font-size: 5rem; color: var(--primary);"></i>
                    </div>
                    <h3 style="font-size: 1.25rem; color: var(--text-dark); margin-bottom: 8px; font-weight: 700;">No photos shared in the chat</h3>
                    <p style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 25px;">Photos added to chat automatically show up here.</p>
                </div>
                <div id="photosGrid" style="display:none; width:100%; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; align-content: start;"></div>
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

    // GROUP INFO PANEL LOGIC
    function toggleGroupInfo() {
        let panel = document.getElementById('groupInfoPanel');
        if (panel.style.display === 'flex') {
            panel.style.display = 'none';
        } else {
            panel.style.display = 'flex';
            loadGroupMembers();
        }
    }

    function closeGroupInfo() {
        document.getElementById('groupInfoPanel').style.display = 'none';
    }

    function loadGroupMembers() {
        if(!activeConvId) return;
        let fd = new FormData();
        fd.append('action', 'get_group_info');
        fd.append('conversation_id', activeConvId);
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(members => {
            let html = '';
            members.forEach(m => {
                html += `<div style="display:flex; align-items:center; gap:10px; padding: 10px 0; border-bottom: 1px solid var(--border);">
                            <img src="${m.profile_img}" loading="lazy" style="width:35px;height:35px;border-radius:50%;object-fit:cover;">
                            <div>
                                <div style="font-weight:600; font-size:0.9rem; color: var(--text-dark);">${escapeHTML(m.display_name)} ${m.id == <?php echo $my_id; ?> ? '(You)' : ''}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${escapeHTML(m.role)}</div>
                            </div>
                        </div>`;
            });
            document.getElementById('groupMembersList').innerHTML = html;
        });
    }

    function openAddMemberModal() {
        document.getElementById('addMemberModal').style.display = 'flex';
        selectedAddMembers.clear();
        document.getElementById('addMemberSearch').value = '';
        searchForAddMember('');
    }

    function closeAddMemberModal() {
        document.getElementById('addMemberModal').style.display = 'none';
    }

    function searchForAddMember(val) {
        let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val); 
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedAddMembers.has(u.id) ? 'background:var(--hover-bg);' : '';
                let icon = selectedAddMembers.has(u.id) ? '<i class="ri-checkbox-circle-fill text-primary"></i>' : '<i class="ri-checkbox-blank-circle-line text-gray-300"></i>';
                html += `<div onclick="toggleAddMember(${u.id}, this)" style="padding:10px 15px; display:flex; align-items:center; gap:12px; cursor:pointer; border-bottom:1px solid var(--border); transition:0.2s; ${isSel}">
                            ${icon}
                            <img src="${u.profile_img}" loading="lazy" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                            <div style="font-weight:600; font-size:0.9rem;">${escapeHTML(u.display_name)}</div>
                        </div>`;
            });
            document.getElementById('addMemberUserList').innerHTML = html || '<div style="padding:15px;text-align:center;color:#888;">No users found</div>';
        });
    }

    function toggleAddMember(uid, el) {
        if(selectedAddMembers.has(uid)) {
            selectedAddMembers.delete(uid);
            el.style.background = '';
            el.querySelector('i').className = 'ri-checkbox-blank-circle-line text-gray-300';
        } else {
            selectedAddMembers.add(uid);
            el.style.background = 'var(--hover-bg)';
            el.querySelector('i').className = 'ri-checkbox-circle-fill text-primary';
            el.querySelector('i').style.color = 'var(--primary)';
        }
    }

    function submitAddMembers() {
        if(selectedAddMembers.size === 0) return Swal.fire('Wait', 'Select at least 1 member to add.', 'warning');
        if(!activeConvId) return;

        let fd = new FormData();
        fd.append('action', 'add_members_to_group');
        fd.append('conversation_id', activeConvId);
        fd.append('members', JSON.stringify(Array.from(selectedAddMembers)));
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeAddMemberModal();
                loadGroupMembers(); 
                fetchMessages(false); 
            }
        });
    }

    // --- File Upload Staging Logic ---
    function queueFile(input) {
        if(input.files.length > 0) {
            document.getElementById('filePreview').style.display = 'flex';
            document.getElementById('filePreviewName').innerText = input.files[0].name;
            document.getElementById('msgInput').focus();
        }
    }

    function clearFile() {
        let input = document.getElementById('fileUpload');
        input.value = '';
        document.getElementById('filePreview').style.display = 'none';
    }

    function switchInnerTab(tabName) {
        const navItems = document.querySelectorAll('.header-nav-item');
        navItems.forEach(item => item.classList.remove('active'));
        
        document.getElementById('chatMessagesContainer').style.display = 'none';
        document.getElementById('chatFilesContainer').style.display = 'none';
        document.getElementById('chatPhotosContainer').style.display = 'none';
        
        if (tabName === 'chat') {
            navItems[0].classList.add('active');
            document.getElementById('chatMessagesContainer').style.display = 'flex';
            let box = document.getElementById('msgBox');
            box.scrollTo({ top: box.scrollHeight });
        } else if (tabName === 'files') {
            navItems[1].classList.add('active');
            document.getElementById('chatFilesContainer').style.display = 'flex';
        } else if (tabName === 'photos') {
            navItems[2].classList.add('active');
            document.getElementById('chatPhotosContainer').style.display = 'flex';
        }
    }

    function addPhotoToGallery(path, id) {
        let emptyState = document.getElementById('photosEmptyState');
        let grid = document.getElementById('photosGrid');
        
        if(emptyState) emptyState.style.display = 'none';
        if(grid) {
            grid.style.display = 'grid';
            if(!document.getElementById('gallery-img-'+id)) {
                grid.insertAdjacentHTML('beforeend', `<div id="gallery-img-${id}" style="aspect-ratio: 1; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); box-shadow: 0 2px 5px rgba(0,0,0,0.05);"><img src="${path}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" onclick="window.open('${path}', '_blank')"></div>`);
            }
        }
    }

    function addFileToGallery(path, id, name) {
        let emptyState = document.getElementById('filesEmptyState');
        let content = document.getElementById('filesContent');
        let list = document.getElementById('filesList');
        
        if(emptyState) emptyState.style.display = 'none';
        if(content) content.style.display = 'flex';
        
        if(list && !document.getElementById('file-item-'+id)) {
            let safeName = name ? escapeHTML(name) : 'Document';
            if(safeName.includes('🚫')) return; 
            list.insertAdjacentHTML('beforeend', `<a href="${path}" target="_blank" id="file-item-${id}" style="display:flex; align-items:center; gap:15px; padding:15px; border:1px solid var(--border); border-radius:8px; background:var(--bg-light); text-decoration:none; color:var(--text-dark); transition:0.2s;">
                <div style="width:40px; height:40px; border-radius:8px; background:var(--outgoing-bg); display:flex; align-items:center; justify-content:center;">
                    <i class="ri-file-text-fill" style="font-size:1.5rem; color:var(--primary);"></i>
                </div>
                <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:500; font-size:0.95rem;">${safeName}</div>
                <i class="ri-download-2-line" style="color:var(--text-muted); font-size:1.2rem;"></i>
            </a>`);
        }
    }

    function backToList() {
        activeConvId = null;
        toggleMobileSidebar();
        document.getElementById('chatAreaEmpty').style.display = 'flex';
        document.getElementById('chatAreaActive').style.display = 'none';
        document.getElementById('groupInfoPanel').style.display = 'none';
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
                <button class="msg-menu-btn" onclick="toggleMsgMenu(event, ${m.id})"><i class="ri-more-fill"></i></button>
                <div class="msg-dropdown" id="msg-drop-${m.id}">
                    <button onclick="initEdit(${m.id}, '${escapeHTML(m.message)}')"><i class="ri-pencil-line mr-2"></i> Edit</button>
                    <button onclick="deleteMessage(${m.id})" class="delete-btn"><i class="ri-delete-bin-line mr-2"></i> Delete</button>
                </div>
            `;
        } else if (m.is_me && !isDeleted) {
            menuHtml = `
                <button class="msg-menu-btn" onclick="toggleMsgMenu(event, ${m.id})"><i class="ri-more-fill"></i></button>
                <div class="msg-dropdown" id="msg-drop-${m.id}">
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
                            <div style="background:rgba(0,0,0,0.05); padding:10px; border-radius:4px; margin-bottom:8px;">
                                <i class="${callType==='audio'?'ri-phone-fill':'ri-vidicon-fill'}" style="font-size:1.5rem; color:var(--primary);"></i>
                                <br><strong style="font-size:0.9rem;">${label}</strong>
                            </div>
                            <button onclick="openEmbeddedMeeting('${meetId}','${callType}')" style="background:var(--primary); color:white; border:none; padding:6px 16px; border-radius:4px; cursor:pointer; font-size:0.8rem; width:100%;">Join</button>
                            ${metaHtml}
                        </div>`;
        } else {
            if(m.message_type === 'image') content = `<img src="${m.attachment_path}" loading="lazy" style="max-width:100%; border-radius:4px; margin-bottom:5px;">`;
            else if(m.message_type === 'file') content = `<a href="${m.attachment_path}" target="_blank" style="display:flex; align-items:center; gap:8px; color:inherit; text-decoration:none; background:rgba(0,0,0,0.05); padding:8px; border-radius:4px;"><i class="ri-file-text-fill text-xl"></i> <span style="word-break: break-all;">${escapeHTML(m.message)}</span></a>`;
            else if(m.message.includes('video:')) {
                let meetParts = m.message.split('video:');
                let plainText = meetParts[0];
                let meetId = meetParts[1];
                content = `${plainText}<button onclick="openEmbeddedMeeting('${meetId}','video')" style="margin-top:10px; background:var(--primary); color:white; border:none; padding:6px 16px; border-radius:4px; cursor:pointer; font-size:0.8rem; width:100%;">Join Meeting</button>`;
            }
            
            let senderName = (!m.is_me && m.display_name) ? `<div style="font-size:0.75rem;color:var(--text-dark);margin-bottom:4px;font-weight:600;">${m.display_name}</div>` : '';
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
                
                let infoBtn = document.getElementById('headerInfoBtn');
                if (info.is_group) {
                    infoBtn.style.display = 'flex';
                    isGroupChat = true;
                } else {
                    infoBtn.style.display = 'none';
                    isGroupChat = false;
                    document.getElementById('groupInfoPanel').style.display = 'none';
                }
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
                    
                    if (m.message_type === 'image' && !m.is_deleted) {
                        addPhotoToGallery(m.attachment_path, m.id);
                    } else if (m.message_type === 'file' && !m.is_deleted) {
                        addFileToGallery(m.attachment_path, m.id, m.message);
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
        let fileInput = document.getElementById('fileUpload');
        
        if(!txt && fileInput.files.length === 0) return;

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
            
            if (fileInput.files.length > 0) {
                fd.append('file', fileInput.files[0]);
            } else {
                fd.append('type', 'text');
            }
            
            let box = document.getElementById('msgBox');
            let displayTxt = fileInput.files.length > 0 ? "Uploading file..." : escapeHTML(txt);
            box.insertAdjacentHTML('beforeend', `<div class="msg-wrapper outgoing"><div class="msg outgoing" style="opacity:0.7;">${displayTxt} <div class="msg-meta"><i class="ri-time-line"></i></div></div></div>`);
            box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
        }

        fetch(window.location.href, { method: 'POST', body: fd }).then(async (r) => {
            let res = await r.json();
            if(res.status === 'error') {
                Swal.fire('Error', res.message, 'error');
            }
            input.value = '';
            clearFile(); // clear the staged file
            fetchMessages(false);
            loadSidebar();
        });
        
        input.value = '';
        stopTyping();
    }

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
                    
                    let grid = document.getElementById('photosGrid');
                    if(grid) grid.innerHTML = '';
                    let emptyState = document.getElementById('photosEmptyState');
                    if(emptyState) emptyState.style.display = 'flex';

                    let fGrid = document.getElementById('filesList');
                    if(fGrid) fGrid.innerHTML = '';
                    let fEmpty = document.getElementById('filesEmptyState');
                    if(fEmpty) fEmpty.style.display = 'flex';
                    let fContent = document.getElementById('filesContent');
                    if(fContent) fContent.style.display = 'none';

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
                    document.getElementById('chatAreaEmpty').style.display = 'flex';
                    document.getElementById('chatAreaActive').style.display = 'none';
                    document.getElementById('groupInfoPanel').style.display = 'none';
                    loadSidebar();
                });
            }
        });
    }

    function startSmartPolling() {
        if(masterPollInterval) clearInterval(masterPollInterval);

        masterPollInterval = setInterval(() => {
            if (!document.hidden) {
                checkIncomingCalls();
                loadSidebar();
                if (activeConvId) {
                    fetchMessages(false);
                }
            }
        }, 8000); 
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
                let isSel = selectedMembers.has(u.id) ? 'background:var(--hover-bg);' : '';
                let icon = selectedMembers.has(u.id) ? '<i class="ri-checkbox-circle-fill text-primary"></i>' : '<i class="ri-checkbox-blank-circle-line text-gray-300"></i>';
                html += `<div onclick="toggleMember(${u.id}, this)" style="padding:10px 15px; display:flex; align-items:center; gap:12px; cursor:pointer; border-bottom:1px solid var(--border); transition:0.2s; ${isSel}">
                            ${icon}
                            <img src="${u.profile_img}" loading="lazy" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                            <div style="font-weight:600; font-size:0.9rem;">${escapeHTML(u.display_name)}</div>
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
            el.style.background = 'var(--hover-bg)';
            el.querySelector('i').className = 'ri-checkbox-circle-fill text-primary';
            el.querySelector('i').style.color = 'var(--primary)';
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
                let html = data.map(u => `<div class="search-item" onclick="startChat(${u.id});"><img src="${u.profile_img}" loading="lazy" style="width:35px;height:35px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:600; font-size:0.9rem;">${escapeHTML(u.display_name)}</div><div style="font-size:0.75rem;color:var(--text-muted);">${escapeHTML(u.role)}</div></div></div>`).join('');
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
        .then(data => { if(data.id) { switchMainTab('chat_view', document.querySelectorAll('.sidebar-secondary-teams .nav-icon')[0]); loadConversation(data.id); } });
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
        let fd = new FormData();
        fd.append('action', 'answer_call');
        fd.append('call_id', currentIncomingCall.id);
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
        let fd = new FormData();
        fd.append('action', 'decline_call');
        fd.append('call_id', currentIncomingCall.id);
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