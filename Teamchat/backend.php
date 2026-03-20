<?php
// backend.php - Handles all Data & AJAX Requests
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Database connection fallback
$dbPath = '../include/db_connect.php'; // Adjusted for teamchat folder
if (file_exists($dbPath)) { require_once $dbPath; } 
elseif (file_exists('../../include/db_connect.php')) { require_once '../../include/db_connect.php'; } 
else { die("Database connection missing."); }

// --- CRITICAL FIX: FORCE CONNECTION CLOSURE ---
register_shutdown_function(function() use ($conn) {
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_close($conn);
    }
});

if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

$my_id = $_SESSION['user_id'];
$my_role = trim($_SESSION['role'] ?? 'Employee');
$my_username = $_SESSION['username'] ?? 'User';

// Role Check
$can_create_group = in_array($my_role, ['Manager', 'Team Lead', 'System Admin', 'HR', 'HR Executive']);

$is_root = file_exists('../include/db_connect.php');
$profile_dir = $is_root ? '../assets/profiles/' : '../../assets/profiles/';

// FETCH ALL COMPANY CONTACTS
$all_users = [];
$stmt_users = $conn->prepare("SELECT u.id, u.role, COALESCE(ep.full_name, u.username) as name, ep.profile_img, ep.department FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id != ? ORDER BY name ASC");
$stmt_users->bind_param("i", $my_id);
$stmt_users->execute();
$res_users = $stmt_users->get_result();

if ($res_users) {
    while($row = $res_users->fetch_assoc()) {
        $img = $row['profile_img'];
        if(empty($img) || $img == 'default_user.png') {
            $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['name'])."&background=random";
        } elseif(!str_starts_with($img, 'http')) {
            $img_clean = str_replace(['../assets/profiles/', 'assets/profiles/'], '', $img);
            $row['profile_img'] = $profile_dir . $img_clean;
        }
        $all_users[] = $row;
    }
}
$stmt_users->close();

// --- ENCRYPTION HELPERS ---
if (!defined('CHAT_ENC_KEY')) { define('CHAT_ENC_KEY', 'Workack_Secret_Key_2026'); }

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

// --- DB CREATION ---
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if ($check && $check->num_rows == 0) { $conn->query("ALTER TABLE $table ADD COLUMN $column $definition"); }
}

$check_cal = $conn->query("SHOW TABLES LIKE 'calendar_meetings'");
if ($check_cal && $check_cal->num_rows == 0) {
    $conn->query("CREATE TABLE calendar_meetings (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), meet_date DATE, meet_time VARCHAR(50), meet_link VARCHAR(100), created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE calendar_meeting_participants (meeting_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY (meeting_id, user_id)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE instant_meetings (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), meet_link VARCHAR(100), created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
}

if (!isset($_SESSION['chat_db_checked_v10'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS call_requests (id INT AUTO_INCREMENT PRIMARY KEY, conversation_id INT NOT NULL, caller_id INT NOT NULL, room_id VARCHAR(64) NOT NULL, call_type ENUM('audio','video') DEFAULT 'video', status ENUM('ringing','answered','declined','ended') DEFAULT 'ringing', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE IF NOT EXISTS message_reads (message_id INT NOT NULL, user_id INT NOT NULL, read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (message_id, user_id)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE IF NOT EXISTS typing_status (conversation_id INT NOT NULL, user_id INT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (conversation_id, user_id)) ENGINE=InnoDB");

    addColumnIfNotExists($conn, 'chat_messages', 'edited_at', 'DATETIME NULL DEFAULT NULL');
    addColumnIfNotExists($conn, 'chat_messages', 'deleted_at', 'DATETIME NULL DEFAULT NULL');
    addColumnIfNotExists($conn, 'chat_participants', 'muted_until', 'DATETIME NULL DEFAULT NULL');
    addColumnIfNotExists($conn, 'chat_participants', 'hidden_at', 'DATETIME NULL DEFAULT NULL');
    $_SESSION['chat_db_checked_v10'] = true;
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
                WHERE (ep.full_name LIKE ? OR u.username LIKE ? OR u.role LIKE ?) AND u.id != ? LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $term, $term, $term, $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        while($row = $res->fetch_assoc()) { 
            $img = $row['profile_img'];
            if(empty($img) || $img == 'default_user.png') {
                $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
            } elseif(!str_starts_with($img, 'http')) {
                $img_clean = str_replace(['../assets/profiles/', 'assets/profiles/'], '', $img);
                $row['profile_img'] = $profile_dir . $img_clean;
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
                $row['avatar'] = "https://ui-avatars.com/api/?name=".urlencode($row['group_name'])."&background=FF6B2B&color=fff";
            } else {
                $img = $row['avatar_db'];
                if(empty($img) || $img == 'default_user.png') {
                    $row['avatar'] = "https://ui-avatars.com/api/?name=".urlencode($row['name'])."&background=FF6B2B&color=fff";
                } elseif(!str_starts_with($img, 'http')) {
                    $img_clean = str_replace(['../assets/profiles/', 'assets/profiles/'], '', $img);
                    $row['avatar'] = $profile_dir . $img_clean;
                } else {
                    $row['avatar'] = $img;
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
                $img = $partner['profile_img'];
                if(empty($img) || $img == 'default_user.png') {
                    $partner['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($partner['display_name'])."&background=random";
                } elseif(!str_starts_with($img, 'http')) {
                    $img_clean = str_replace(['../assets/profiles/', 'assets/profiles/'], '', $img);
                    $partner['profile_img'] = $profile_dir . $img_clean;
                }
                $partner['is_group'] = false;
            } else {
                $partner = ['display_name' => $conv_info['group_name'], 'role' => 'Group Chat', 'is_group' => true, 'profile_img' => "https://ui-avatars.com/api/?name=".urlencode($conv_info['group_name'])."&background=FF6B2B&color=fff"];
            }
        }

        // --- NEW CODE: FETCH READ RECEIPT STATUS FOR MY MESSAGES ---
        $read_ids = [];
        $r_stmt = $conn->prepare("SELECT mr.message_id FROM message_reads mr JOIN chat_messages cm ON mr.message_id = cm.id WHERE cm.conversation_id = ? AND cm.sender_id = ?");
        $r_stmt->bind_param("ii", $conv_id, $my_id);
        $r_stmt->execute();
        $r_res = $r_stmt->get_result();
        while($r_row = $r_res->fetch_assoc()) {
            $read_ids[] = $r_row['message_id'];
        }

        echo json_encode([
            'messages' => $msgs, 
            'info' => $partner, 
            'typing' => $typing_users,
            'read_ids' => $read_ids
        ]); 
        exit;
    }

    // 5. GET GROUP INFO
    if ($action === 'get_group_info') {
        $conv_id = (int)$_POST['conversation_id'];
        $sql = "SELECT u.id, COALESCE(ep.full_name, u.username) as display_name, ep.profile_img, u.role
                FROM chat_participants cp JOIN users u ON cp.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE cp.conversation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $conv_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $members = [];
        while($row = $res->fetch_assoc()) {
            $img = $row['profile_img'];
            if(empty($img) || $img == 'default_user.png') {
                $row['profile_img'] = "https://ui-avatars.com/api/?name=".urlencode($row['display_name'])."&background=random";
            } elseif(!str_starts_with($img, 'http')) {
                $img_clean = str_replace(['../assets/profiles/', 'assets/profiles/'], '', $img);
                $row['profile_img'] = $profile_dir . $img_clean;
            }
            $members[] = $row;
        }
        echo json_encode($members); exit;
    }

    // 6. ADD MEMBERS TO GROUP
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

    // 7. SEND MESSAGE (SECURE FILE UPLOAD)
    if ($action === 'send_message') {
        $conv_id = (int)$_POST['conversation_id'];
        $msg_text = $_POST['message'] ?? '';
        $msg_type = $_POST['type'] ?? 'text'; 
        $attachment = null;

        $conn->query("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = $conv_id");

        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $target_dir = "../uploads/chat/"; // Adjusted for teamchat folder
            if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
            if (!is_writable($target_dir)) { echo json_encode(['status' => 'error', 'message' => 'Upload directory is missing or not writable.']); exit; }
            
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','txt','zip','rar'];
            
            if(!in_array($ext, $allowed)){
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Upload blocked for security.']); exit;
            }

            $fname = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $fname)) {
                $attachment = "../uploads/chat/" . $fname; // Save relative path for UI
                $msg_type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
                if(!$msg_text) $msg_text = $_FILES['file']['name'];
            }
        }

        if ($msg_type === 'text') { $msg_text = encryptChatMessage($msg_text); }
        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, attachment_path, message_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $conv_id, $my_id, $msg_text, $attachment, $msg_type);
        $stmt->execute();
        echo json_encode(['status' => 'sent']); exit;
    }

    // 8. MEETINGS HISTORY
    if ($action === 'save_instant_meeting') {
        $title = trim($_POST['title'] ?? 'Instant Meeting');
        $link = $_POST['link'];
        $stmt = $conn->prepare("INSERT INTO instant_meetings (title, meet_link, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $title, $link, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'success']); exit;
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

        $sql = "SELECT DISTINCT cm.* FROM calendar_meetings cm LEFT JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id WHERE cm.created_by = ? OR cmp.user_id = ? ORDER BY cm.meet_date DESC, cm.meet_time DESC LIMIT 10";
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

    if ($action === 'create_scheduled_meeting') {
        $title = trim($_POST['title'] ?? 'Scheduled Meeting');
        $date = $_POST['date'];
        $time = $_POST['time'];
        $members = json_decode($_POST['members'] ?? '[]', true);
        $members[] = $my_id; 
        $members = array_filter(array_unique(array_map('intval', $members)));
        $meet_id = 'Workack-Meet-' . substr(md5(uniqid()), 0, 10);

        $stmt = $conn->prepare("INSERT INTO calendar_meetings (title, meet_date, meet_time, meet_link, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $date, $time, $meet_id, $my_id);
        if (!$stmt->execute()) { echo json_encode(['status' => 'error', 'message' => 'DB Insert Failed: ' . $stmt->error]); exit; }
        $new_meet_id = $conn->insert_id;

        $stmt_part = $conn->prepare("INSERT INTO calendar_meeting_participants (meeting_id, user_id) VALUES (?, ?)");
        foreach ($members as $uid) {
            $stmt_part->bind_param("ii", $new_meet_id, $uid);
            $stmt_part->execute();
        }

        $msg = "📅 **Scheduled Meeting: $title**<br>Date: $date at $time<br>Click to Join: video:$meet_id";
        $enc_msg = encryptChatMessage($msg);

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
            foreach ($members as $uid) { $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($conv_id, $uid)"); }
        }

        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$enc_msg', 'text')");
        echo json_encode(['status' => 'success', 'conversation_id' => $conv_id]); exit;
    }

    if ($action === 'get_calendar_events') {
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $sql = "SELECT cm.* FROM calendar_meetings cm JOIN calendar_meeting_participants cmp ON cm.id = cmp.meeting_id WHERE cmp.user_id = ? AND cm.meet_date >= ? AND cm.meet_date <= ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $my_id, $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        $events = [];
        while($row = $res->fetch_assoc()) { $events[] = $row; }
        echo json_encode($events); exit;
    }

    // 9. EDIT / DELETE / CLEAR CHAT
    if ($action === 'edit_message') {
        $msg_id = (int)$_POST['message_id'];
        $new_text = encryptChatMessage($_POST['new_text']);
        $stmt = $conn->prepare("UPDATE chat_messages SET message = ?, edited_at = NOW() WHERE id = ? AND sender_id = ? AND deleted_at IS NULL AND message_type = 'text'");
        $stmt->bind_param("sii", $new_text, $msg_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }

    if ($action === 'delete_message') {
        $msg_id = (int)$_POST['message_id'];
        $stmt = $conn->prepare("UPDATE chat_messages SET deleted_at = NOW() WHERE id = ? AND sender_id = ?");
        $stmt->bind_param("ii", $msg_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }

    if ($action === 'clear_chat' || $action === 'delete_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("UPDATE chat_participants SET hidden_at = NOW() WHERE conversation_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }

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
            $img = $row['caller_avatar'];
            if (empty($img) || $img == 'default_user.png') {
                $row['caller_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($row['caller_name']) . "&background=random";
            } elseif(!str_starts_with($img, 'http')) {
                $img_clean = str_replace(['../assets/profiles/', 'assets/profiles/'], '', $img);
                $row['caller_avatar'] = $profile_dir . $img_clean;
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