<?php
// team_chat.php - PROFESSIONAL ENTERPRISE EDITION (Text Chat & People Only)

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
$can_create_group = in_array($my_role, ['Manager', 'Team Lead', 'System Admin', 'HR', 'HR Executive','CFO', 'Sales Manager','Sales Executive','IT Admin' ,'IT Executive','CEO']);

// === PERFORMANCE & BROKEN IMAGE FIX: Resolve Directory path ONCE ===
$is_root = file_exists('include/db_connect.php');
$profile_dir = $is_root ? 'assets/profiles/' : 'assets/profiles/';

// =========================================================================================
// FETCH ALL COMPANY CONTACTS (Prepared Statement for Security)
// =========================================================================================
$all_users = [];
$stmt_users = $conn->prepare("SELECT u.id, u.role, COALESCE(ep.full_name, u.username) as name, ep.profile_img, ep.department FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id != ? ORDER BY name ASC");
if ($stmt_users) {
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
}

// --- ENCRYPTION HELPERS ---
if (!defined('CHAT_ENC_KEY')) {
    define('CHAT_ENC_KEY', 'Workack_Secret_Key_2026'); 
}

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

// --- GUARANTEED DB CREATION & MYSQL 5.7 COMPATIBILITY ---
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

if (!isset($_SESSION['chat_db_checked_v12'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS message_reads (message_id INT NOT NULL, user_id INT NOT NULL, read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (message_id, user_id)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE IF NOT EXISTS typing_status (conversation_id INT NOT NULL, user_id INT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (conversation_id, user_id)) ENGINE=InnoDB");

    // Safe column additions for MySQL 5.7+
    addColumnIfNotExists($conn, 'chat_messages', 'edited_at', 'DATETIME NULL DEFAULT NULL');
    addColumnIfNotExists($conn, 'chat_messages', 'deleted_at', 'DATETIME NULL DEFAULT NULL');
    addColumnIfNotExists($conn, 'chat_participants', 'muted_until', 'DATETIME NULL DEFAULT NULL');
    addColumnIfNotExists($conn, 'chat_participants', 'hidden_at', 'DATETIME NULL DEFAULT NULL');
    addColumnIfNotExists($conn, 'chat_conversations', 'blocked_by_id', 'INT NULL DEFAULT NULL'); // NEW: For blocking logic
    
    $_SESSION['chat_db_checked_v12'] = true;
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
        $users = [];
        if ($stmt) {
            $stmt->bind_param("sssi", $term, $term, $term, $my_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
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
            }
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

    // 3. GET RECENT CHATS (FIXED EMPTY CHATS FROM STICKING IN LIST)
    if ($action === 'get_recent_chats') {
        $sql = "
            SELECT 
                c.id AS conversation_id, c.type, c.group_name, cp.muted_until, c.blocked_by_id,
                cm.message AS last_msg, cm.message_type, cm.created_at AS time, cm.deleted_at,
                (SELECT COUNT(m.id) FROM chat_messages m LEFT JOIN message_reads r ON m.id = r.message_id AND r.user_id = ? WHERE m.conversation_id = c.id AND m.sender_id != ? AND r.message_id IS NULL AND m.deleted_at IS NULL) AS unread,
                IF(c.type = 'group', c.group_name, COALESCE(ep.full_name, u.username, 'Unknown User')) AS name,
                ep.profile_img AS avatar_db
            FROM chat_conversations c
            INNER JOIN chat_participants cp ON c.id = cp.conversation_id AND cp.user_id = ? AND cp.hidden_at IS NULL
            INNER JOIN chat_messages cm ON cm.id = (SELECT MAX(id) FROM chat_messages m2 WHERE m2.conversation_id = c.id)
            LEFT JOIN chat_participants cp2 ON c.type = 'direct' AND cp2.conversation_id = c.id AND cp2.user_id != ?
            LEFT JOIN users u ON cp2.user_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id
            ORDER BY COALESCE(cm.created_at, c.created_at) DESC LIMIT 50
        ";
        $stmt = $conn->prepare($sql);
        $chats = [];
        if ($stmt) {
            $stmt->bind_param("iiii", $my_id, $my_id, $my_id, $my_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
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
                    else if ($row['message_type'] == 'image') $row['last_msg'] = '🖼️ Photo';
                    else if ($row['message_type'] == 'file') $row['last_msg'] = '📎 Attachment';

                    $row['time'] = $row['time'] ? date('h:i A', strtotime($row['time'])) : '';
                    $chats[] = $row;
                }
            }
        }
        echo json_encode($chats); exit;
    }

    // 4. GET MESSAGES
    if ($action === 'get_messages') {
        $conv_id = (int)$_POST['conversation_id'];
        $last_msg_id = isset($_POST['last_msg_id']) ? (int)$_POST['last_msg_id'] : 0;

        $chk = $conn->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
        if ($chk) {
            $chk->bind_param("ii", $conv_id, $my_id);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) { echo json_encode(['messages' => [], 'info' => null]); exit; }
        }
        
        $conn->query("INSERT IGNORE INTO message_reads (message_id, user_id) SELECT id, $my_id FROM chat_messages WHERE conversation_id = $conv_id AND sender_id != $my_id AND deleted_at IS NULL");

        $sql = "SELECT m.*, COALESCE(ep.full_name, u.username) as display_name,
                       (SELECT COUNT(*) FROM message_reads r WHERE r.message_id = m.id) AS read_count
                FROM chat_messages m JOIN users u ON m.sender_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $msgs = [];
        if ($stmt) {
            $stmt->bind_param("ii", $conv_id, $last_msg_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
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
            }
        }

        $typing_users = [];
        $typing_res = $conn->query("SELECT COALESCE(ep.full_name, u.username) as typing_name FROM typing_status ts JOIN users u ON ts.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE ts.conversation_id = $conv_id AND ts.user_id != $my_id AND ts.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        if ($typing_res) {
            while ($t = $typing_res->fetch_assoc()) $typing_users[] = $t['typing_name'];
        }

        $partner = null;
        $blocked_by = null;
        
        if ($last_msg_id == 0) {
            $conv_info = $conn->query("SELECT * FROM chat_conversations WHERE id = $conv_id")->fetch_assoc();
            $blocked_by = $conv_info['blocked_by_id'] ?? null; 
            
            if ($conv_info && $conv_info['type'] == 'direct') {
                $p_stmt = $conn->prepare("SELECT COALESCE(ep.full_name, u.username) as display_name, u.role, ep.profile_img FROM chat_participants cp JOIN users u ON cp.user_id = u.id LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE cp.conversation_id = ? AND cp.user_id != ? LIMIT 1");
                if ($p_stmt) {
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
                }
            } else if ($conv_info) {
                $partner = ['display_name' => $conv_info['group_name'], 'role' => 'Group Chat', 'is_group' => true, 'profile_img' => "https://ui-avatars.com/api/?name=".urlencode($conv_info['group_name'])."&background=FF6B2B&color=fff"];
            }
        }

        $read_ids = [];
        $r_stmt = $conn->prepare("SELECT mr.message_id FROM message_reads mr JOIN chat_messages cm ON mr.message_id = cm.id WHERE cm.conversation_id = ? AND cm.sender_id = ?");
        if ($r_stmt) {
            $r_stmt->bind_param("ii", $conv_id, $my_id);
            $r_stmt->execute();
            $r_res = $r_stmt->get_result();
            if ($r_res) {
                while($r_row = $r_res->fetch_assoc()) {
                    $read_ids[] = $r_row['message_id'];
                }
            }
        }

        echo json_encode(['messages' => $msgs, 'info' => $partner, 'typing' => $typing_users, 'read_ids' => $read_ids, 'blocked_by' => $blocked_by]); exit;
    }

    // 5. GET GROUP INFO
    if ($action === 'get_group_info') {
        $conv_id = (int)$_POST['conversation_id'];
        $sql = "SELECT u.id, COALESCE(ep.full_name, u.username) as display_name, ep.profile_img, u.role
                FROM chat_participants cp
                JOIN users u ON cp.user_id = u.id
                LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                WHERE cp.conversation_id = ?";
        $stmt = $conn->prepare($sql);
        $members = [];
        if ($stmt) {
            $stmt->bind_param("i", $conv_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
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
            }
        }
        echo json_encode($members); exit;
    }

    if ($action === 'add_members_to_group') {
        $conv_id = (int)$_POST['conversation_id'];
        $members = json_decode($_POST['members'] ?? '[]', true);
        if(!is_array($members) || empty($members)) { echo json_encode(['status'=>'error']); exit; }
        
        $stmt_part = $conn->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
        if ($stmt_part) {
            foreach ($members as $uid) {
                $stmt_part->bind_param("ii", $conv_id, $uid); 
                $stmt_part->execute();
            }
        }
        $sys_msg = encryptChatMessage("New members were added to the group.");
        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$sys_msg', 'text')");
        echo json_encode(['status' => 'success']); exit;
    }

    // 6. SEND MESSAGE
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
            
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','txt','zip','rar'];
            
            if(!in_array($ext, $allowed)){
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Upload blocked for security.']); exit;
            }

            $fname = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $fname)) {
                $attachment = $target_dir . $fname;
                $msg_type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
                if(!$msg_text) $msg_text = $_FILES['file']['name'];
            }
        }

        if ($msg_type === 'text') { $msg_text = encryptChatMessage($msg_text); }
        $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, attachment_path, message_type) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisss", $conv_id, $my_id, $msg_text, $attachment, $msg_type);
            $stmt->execute();
        }
        echo json_encode(['status' => 'sent']); exit;
    }

    // 7. EDIT MESSAGE
    if ($action === 'edit_message') {
        $msg_id = (int)$_POST['message_id'];
        $new_text = encryptChatMessage($_POST['new_text']);
        $stmt = $conn->prepare("UPDATE chat_messages SET message = ?, edited_at = NOW() WHERE id = ? AND sender_id = ? AND deleted_at IS NULL AND message_type = 'text'");
        if ($stmt) {
            $stmt->bind_param("sii", $new_text, $msg_id, $my_id);
            $stmt->execute();
        }
        echo json_encode(['status' => 'ok']); exit;
    }

    // 8. DELETE MESSAGE
    if ($action === 'delete_message') {
        $msg_id = (int)$_POST['message_id'];
        $stmt = $conn->prepare("UPDATE chat_messages SET deleted_at = NOW() WHERE id = ? AND sender_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $msg_id, $my_id);
            $stmt->execute();
        }
        echo json_encode(['status' => 'ok']); exit;
    }

    // 9. CLEAR/DELETE CHAT
    if ($action === 'clear_chat' || $action === 'delete_chat') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("UPDATE chat_participants SET hidden_at = NOW() WHERE conversation_id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $conv_id, $my_id);
            $stmt->execute();
        }
        echo json_encode(['status' => 'ok']); exit;
    }

    // 10. BLOCK / UNBLOCK 
    if ($action === 'block_user') {
        $conv_id = (int)$_POST['conversation_id'];
        $conn->query("UPDATE chat_conversations SET blocked_by_id = $my_id WHERE id = $conv_id AND type = 'direct'");
        echo json_encode(['status' => 'ok']); exit;
    }
    if ($action === 'unblock_user') {
        $conv_id = (int)$_POST['conversation_id'];
        $conn->query("UPDATE chat_conversations SET blocked_by_id = NULL WHERE id = $conv_id AND type = 'direct' AND blocked_by_id = $my_id");
        echo json_encode(['status' => 'ok']); exit;
    }

    // 11. START CHAT (Direct)
    if ($action === 'start_chat') {
        $target = (int)$_POST['target_user_id'];
        $sql = "SELECT c.id FROM chat_conversations c JOIN chat_participants cp1 ON c.id = cp1.conversation_id JOIN chat_participants cp2 ON c.id = cp2.conversation_id WHERE c.type = 'direct' AND cp1.user_id = ? AND cp2.user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $my_id, $target); $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $row = $res->fetch_assoc()) {
                $conn->query("UPDATE chat_participants SET hidden_at = NULL WHERE conversation_id = {$row['id']} AND user_id = $my_id");
                echo json_encode(['status' => 'success', 'id' => $row['id']]);
            } else {
                $conn->query("INSERT INTO chat_conversations (type) VALUES ('direct')");
                $new_id = $conn->insert_id;
                $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($new_id, $my_id), ($new_id, $target)");
                echo json_encode(['status' => 'success', 'id' => $new_id]);
            }
        } else {
             echo json_encode(['status' => 'error']);
        }
        exit;
    }

    if ($action === 'start_typing') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("INSERT INTO typing_status (conversation_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");
        if ($stmt) { $stmt->bind_param("ii", $conv_id, $my_id); $stmt->execute(); }
        echo json_encode(['status' => 'ok']); exit;
    }
    if ($action === 'stop_typing') {
        $conv_id = (int)$_POST['conversation_id'];
        $stmt = $conn->prepare("DELETE FROM typing_status WHERE conversation_id = ? AND user_id = ?");
        if ($stmt) { $stmt->bind_param("ii", $conv_id, $my_id); $stmt->execute(); }
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --primary: #FF6B2B; 
            --primary-hover: #E55A1F; 
            --primary-light: #FFF0E6; 
            --bg-light: #F8F9FA; 
            --surface: #FFFFFF;
            --border: #E5E7EB; 
            --border-light: #F3F4F6;
            --text-dark: #111827; 
            --text-muted: #6B7280; 
            --outgoing-bg: #FFF0E6; 
            --incoming-bg: #FFFFFF;
            --sidebar-bg: #FFFFFF;
            --hover-bg: #F9FAFB; 
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
        body { background-color: var(--bg-light); height: 100vh; display: flex; flex-direction: column; overflow: hidden; color: var(--text-dark); }
        
        #mainContent {
            margin-left: 95px; 
            width: calc(100% - 95px);
            height: 100vh; 
            display: flex; 
            flex-direction: column;
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
            background: var(--bg-light);
        }

        @media (max-width: 991px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; }
        }

        /* --- SKELETON LOADING CSS --- */
        @keyframes skeleton-shimmer {
            0% { background-position: -200px 0; }
            100% { background-position: calc(200px + 100%) 0; }
        }
        .skeleton {
            background: #f6f7f8;
            background-image: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
            background-repeat: no-repeat;
            background-size: 800px 100%;
            animation-duration: 1.5s;
            animation-fill-mode: forwards;
            animation-iteration-count: infinite;
            animation-name: skeleton-shimmer;
            animation-timing-function: linear;
        }
        .skeleton-text { height: 14px; border-radius: 4px; }
        .skeleton-msg-in { max-width: 60%; width: 250px; height: 60px; border-radius: 16px 16px 16px 4px; align-self: flex-start; margin-bottom: 16px; border: 1px solid var(--border); }
        .skeleton-msg-out { max-width: 60%; width: 200px; height: 50px; border-radius: 16px 16px 4px 16px; align-self: flex-end; margin-bottom: 16px; border: 1px solid var(--border-light); }


        .app-container { flex: 1; display:flex; height: 0; min-height: 0; background: var(--bg-light); position: relative;}
        
        .sidebar-secondary-teams { width: 100px; background: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; padding-top: 15px; z-index: 15; box-shadow: var(--shadow-sm); }
        .nav-icon { width: 56px; height: 56px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); font-size: 0.75rem; border-radius: var(--radius-md); margin-bottom: 8px; transition: all 0.2s ease; font-weight: 500;}
        .nav-icon i { font-size: 1.6rem; margin-bottom: 4px; transition: transform 0.2s; }
        .nav-icon:hover { color: var(--primary); background: var(--primary-light); }
        .nav-icon:hover i { transform: translateY(-2px); }
        .nav-icon.active { background: var(--primary-light); color: var(--primary); font-weight: 600; box-shadow: inset 3px 0 0 var(--primary); }

        .sidebar { width: 340px; background: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 10; transition: transform 0.3s ease;}
        
        .sidebar-header { padding: 20px 20px 10px; display: flex; justify-content:space-between; align-items:center; }
        .sidebar-header h2 { font-weight: 700; color: var(--text-dark); font-size: 1.5rem; letter-spacing: -0.02em; }
        
        .btn-icon-small { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); background: var(--surface); border: 1px solid var(--border); cursor: pointer; color: var(--text-muted); transition: all 0.2s; font-size: 1.2rem; }
        .btn-icon-small:hover { background: var(--hover-bg); border-color: var(--primary); color: var(--primary); box-shadow: var(--shadow-sm); }
        
        .search-box { padding: 10px 20px; position: relative; }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--hover-bg); outline: none; transition: all 0.2s; font-size: 0.95rem; color: var(--text-dark); }
        .search-box input:focus { background: var(--surface); border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .search-box i { position: absolute; left: 32px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; }
        
        .chat-list { flex: 1; overflow-y: auto; padding: 10px; }
        .chat-list::-webkit-scrollbar { width: 6px; }
        .chat-list::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }
        .chat-list::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }
        
        .chat-item { display: flex; align-items: center; padding: 12px 16px; cursor: pointer; border-radius: var(--radius-md); margin-bottom: 4px; transition: all 0.2s; border: 1px solid transparent; }
        .chat-item:hover { background: var(--hover-bg); }
        .chat-item.active { background: var(--primary-light); border-color: transparent; }
        .chat-item.active .chat-item-name { color: var(--primary); font-weight: 600; }
        
        .avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid var(--surface); box-shadow: var(--shadow-sm); }
        
        #searchResults { position: absolute; top: 60px; left: 20px; width: calc(100% - 40px); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); z-index: 50; display: none; max-height: 300px; overflow-y: auto;}
        .search-item { padding: 12px 16px; cursor: pointer; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-light);}
        .search-item:hover { background: var(--hover-bg); }

        .content-area { flex: 1; display: flex; flex-direction: row; background: var(--bg-light); position: relative; overflow:hidden; }

        .chat-main-column { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; background: var(--surface); margin: 10px 10px 10px 0; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
        
        .group-info-panel { width: 320px; background: var(--surface); border-left: 1px solid var(--border); display: none; flex-direction: column; z-index: 5; transition: transform 0.3s; overflow-y: auto; margin: 10px 10px 10px 0; border-radius: 0 var(--radius-lg) var(--radius-lg) 0; border: 1px solid var(--border); border-left: none;}

        .chat-header { background: var(--surface); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 16px 24px; justify-content: space-between; z-index: 10; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
        
        .header-nav { display: flex; gap: 24px; align-items: center; margin-left: 30px; height: 100%; }
        .header-nav-item { padding: 8px 0; color: var(--text-muted); font-size: 0.95rem; font-weight: 500; cursor: pointer; position: relative; transition: color 0.2s; }
        .header-nav-item.active { color: var(--primary); font-weight: 600; }
        .header-nav-item.active::after { content: ''; position: absolute; bottom: -17px; left: 0; right: 0; height: 3px; background-color: var(--primary); border-radius: 3px 3px 0 0; }
        .header-nav-item:hover:not(.active) { color: var(--text-dark); }

        .header-actions { display: flex; gap: 8px; align-items: center; position: relative;}
        .btn-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--surface); border: 1px solid var(--border); cursor: pointer; color: var(--text-muted); transition: all 0.2s; font-size: 1.2rem; box-shadow: var(--shadow-sm);}
        .btn-icon:hover { background: var(--hover-bg); color: var(--primary); border-color: var(--primary); transform: translateY(-1px);}
        
        .messages-box { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; z-index: 5; background: #fbfbfb; }
        .messages-box::-webkit-scrollbar { width: 8px; }
        .messages-box::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }
        .messages-box::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }

        .msg-wrapper { display: flex; flex-direction: column; max-width: 75%; position: relative;}
        .msg-wrapper.incoming { align-self: flex-start; }
        .msg-wrapper.outgoing { align-self: flex-end; }
        
        .msg { padding: 12px 40px 12px 16px; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; position: relative; box-shadow: var(--shadow-sm);}
        .msg.incoming { background: var(--incoming-bg); border: 1px solid var(--border); border-radius: 16px 16px 16px 4px; color: var(--text-dark); }
        .msg.outgoing { background: var(--outgoing-bg); color: var(--text-dark); border: 1px solid #FFD9C6; border-radius: 16px 16px 4px 16px; }
        .msg.deleted { font-style: italic; color: var(--text-muted); background: transparent; border: 1px solid var(--border); box-shadow: none; border-radius: 12px;}
        
        .msg-meta { display: flex; justify-content: flex-end; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;}
        .msg.outgoing .msg-meta { color: #A46950; }
        .ticks { font-size: 0.9rem; margin-left: 2px;}
        .tick-read { color: var(--primary); }
        .tick-sent { color: #9CA3AF; }
        
        .msg-menu-btn { position: absolute; top: 6px; right: 6px; background: rgba(255,255,255,0.8); border: none; color: var(--text-muted); cursor: pointer; opacity: 0; transition: opacity 0.2s, background 0.2s; padding: 4px; border-radius: 50%; box-shadow: var(--shadow-sm);}
        .msg-wrapper:hover .msg-menu-btn { opacity: 1; }
        .msg-menu-btn:hover { background: var(--surface); color: var(--text-dark); }
        .msg-dropdown { position: absolute; top: 35px; right: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); z-index: 50; display: none; overflow: hidden; min-width: 140px; padding: 4px;}
        .msg-dropdown button { width: 100%; text-align: left; padding: 10px 16px; border: none; background: transparent; cursor: pointer; font-size: 0.9rem; border-radius: var(--radius-sm); transition: all 0.2s; color: var(--text-dark);}
        .msg-dropdown button:hover { background: var(--hover-bg); color: var(--primary); }
        .msg-dropdown button.delete-btn:hover { color: #EF4444; background: #FEF2F2; }

        #chatOptionsDropdown { position: absolute; top: 55px; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); z-index: 50; display: none; min-width: 180px; padding: 8px;}
        #chatOptionsDropdown button { width: 100%; text-align: left; padding: 10px 16px; border: none; background: transparent; cursor: pointer; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; border-radius: var(--radius-sm); transition: 0.2s; color: var(--text-dark);}
        #chatOptionsDropdown button:hover { background: #FEF2F2; color: #EF4444;}

        .input-area { padding: 16px 24px 24px; background: #fbfbfb; display: flex; flex-direction: column; z-index: 10; position: sticky; bottom: 0; border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
        
        #filePreview { display: none; align-items: center; justify-content: space-between; background: var(--surface); border: 1px solid var(--border); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 12px; font-size: 0.9rem; box-shadow: var(--shadow-sm); }
        
        .input-wrapper { background: var(--surface); border-radius: var(--radius-md); display: flex; align-items: flex-end; width: 100%; box-shadow: var(--shadow-sm); border: 1px solid var(--border); padding: 8px 16px; min-height: 56px; transition: border 0.2s, box-shadow 0.2s;}
        .input-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .input-wrapper input { flex: 1; padding: 10px; border: none; outline: none; background: transparent; font-size: 1rem; color: var(--text-dark); }
        .input-tools { display: flex; align-items: center; gap: 8px; margin-left: 12px; padding-bottom: 4px;}
        .btn-tool { background: transparent; color: var(--text-muted); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all 0.2s; font-size: 1.3rem;}
        .btn-tool:hover { background: var(--hover-bg); color: var(--primary); transform: scale(1.05); }
        .btn-send { background: var(--primary); color: white; border-radius: var(--radius-md); width: auto; padding: 0 16px; font-weight: 600;}
        .btn-send:hover { background: var(--primary-hover); color: white; transform: none; }
        
       #emojiPicker { 
    display: none; /* Default hide */
    position: absolute; 
    bottom: 90px; 
    right: 24px; 
    background: var(--surface); 
    border: 1px solid var(--border); 
    border-radius: var(--radius-lg); 
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); /* Slightly softer shadow */
    width: 320px; /* Konjam width increase pannalam */
    height: 250px; 
    overflow-y: auto; 
    overflow-x: hidden; /* Horizontal scrollbar vara koodathu */
    padding: 12px; 
    z-index: 100; 

    /* Puthiya Grid Setup */
    grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); /* Space-ku etha mathiri auto-arrange aagum */
    gap: 6px; /* Gap-ah konjam kuraikalam */
    justify-items: center; /* Center alignment */
}

/* Custom Scrollbar for neat look */
#emojiPicker::-webkit-scrollbar { width: 6px; }
#emojiPicker::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

#emojiPicker span { 
    cursor: pointer; 
    transition: transform 0.1s, background 0.2s; 
    padding: 6px; 
    border-radius: 8px; 
    font-size: 1.3rem; 
    display: flex; /* Emojis perfect aa center-la ukkara */
    align-items: center;
    justify-content: center;
    width: 100%;
    aspect-ratio: 1/1; /* Square shape maintain panna */
}

#emojiPicker span:hover { 
    transform: scale(1.15); 
    background: var(--hover-bg, #f1f5f9); 
}

        .people-card { display: flex; align-items: center; justify-content: space-between; padding: 16px 32px; border-bottom: 1px solid var(--border); transition: background 0.2s; background: var(--surface); }
        .people-card:hover { background: var(--hover-bg); }
        .people-info { display: flex; align-items: center; gap: 16px; }
        .people-btn { background: var(--surface); color: var(--text-dark); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--border); transition: all 0.2s; font-size: 1.3rem; box-shadow: var(--shadow-sm);}
        .people-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: scale(1.05); box-shadow: var(--shadow-md); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(17, 24, 39, 0.7); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px;}
        .modal { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); border: 1px solid var(--border); animation: modalFadeIn 0.3s ease-out;}
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        
        #editModeBar { display: none; background: var(--primary-light); padding: 12px 24px; align-items: center; justify-content: space-between; font-size: 0.9rem; color: var(--primary); z-index:10; font-weight: 600; border-top: 1px solid #FFD9C6;}

        .data-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); transition: box-shadow 0.2s; }
        .data-card:hover { box-shadow: var(--shadow-md); }

        input[type="text"], input[type="date"], select, textarea { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid var(--border); 
            border-radius: var(--radius-sm); 
            outline: none; 
            font-size: 0.95rem; 
            font-family: inherit; 
            transition: all 0.2s; 
            background: var(--surface); 
            color: var(--text-dark); 
            box-sizing: border-box;
        }

        input[type="text"], input[type="date"], select { height: 48px; }
        select { padding-top: 0; padding-bottom: 0; line-height: 46px; }

        input[type="text"]:focus, input[type="date"]:focus, select:focus, textarea:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px var(--primary-light); 
        }
        
        .btn-primary { width:100%; padding:12px; background:var(--primary); color:white; border:none; border-radius:var(--radius-sm); cursor:pointer; font-weight:600; font-size: 1rem; transition: background 0.2s, transform 0.1s; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-primary:active { transform: translateY(0); }

        #mobileBackBtn { display: none; }
        @media (max-width: 992px) {
            .sidebar-secondary-teams { display: none; }
            .sidebar { width: 100%; position: absolute; height: 100%; z-index: 20; }
            .sidebar.hide-mobile { transform: translateX(-100%); }
            #mobileBackBtn { display: flex; }
            .chat-main-column, .group-info-panel { margin: 0; border-radius: 0; border: none; border-top: 1px solid var(--border);}
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
            <div class="nav-icon" onclick="switchMainTab('people_view', this)">
                <i class="ri-contacts-book-2-fill"></i>
                <span>People</span>
            </div>
            <div style="flex: 1;"></div>
            <div class="nav-icon" style="color: var(--primary);">
                <i class="ri-gem-fill"></i>
            </div>
        </aside>

        <aside class="sidebar" id="chatSidebar">
            <div class="sidebar-header">
                <h2>Chat</h2>
                <div style="display: flex; gap: 8px;">
                    <?php if($can_create_group): ?>
                        <button class="btn-icon-small" title="New Group Chat" onclick="openGroupModal()"><i class="ri-edit-box-line"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="search-box">
                <input type="text" id="userSearch" placeholder="Search people...">
                <div id="searchResults"></div>
            </div>
            
            <div style="flex: 1; overflow-y: auto; padding-bottom: 20px;">
                <div class="chat-list" id="chatList">
                    <div style="text-align:center; padding: 40px; color:var(--text-muted);"><i class="ri-loader-4-line ri-spin" style="font-size: 2rem; color: var(--primary);"></i><br><br>Loading chats...</div>
                </div>
            </div>
        </aside>

        <section class="content-area" id="mainContentView">
            
            <div id="chat_view" style="display: flex; width: 100%; height: 100%;">
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); text-align:center; padding:20px; z-index:5;" id="chatAreaEmpty">
                    <div style="width: 120px; height: 120px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 24px;">
                        <i class="ri-chat-smile-3-line" style="font-size:4rem; color: var(--primary);"></i>
                    </div>
                    <h3 style="font-size: 1.5rem; color: var(--text-dark); margin-bottom: 10px; font-weight: 700;">Workack Team Chat</h3>
                    <p style="font-size: 1rem; max-width: 300px;">Select a conversation from the sidebar or start a new chat to connect with your team.</p>
                </div>
                
                <div id="chatAreaActive" class="chat-main-column" style="display: none;">
                    <div class="chat-header" id="chatHeader">
                        <div style="display:flex; align-items:center;">
                            <button id="mobileBackBtn" class="btn-icon" style="margin-right:12px; box-shadow:none; border:none; background:var(--hover-bg);" onclick="backToList()"><i class="ri-arrow-left-line"></i></button>
                            <div style="position:relative;">
                                 <img src="" id="headerAvatar" class="avatar" loading="lazy" style="width:42px;height:42px;margin:0;border:none;">
                                 <span style="position:absolute; bottom:0; right:-2px; width:14px; height:14px; border:2px solid var(--surface); border-radius:50%; background-color:#22c55e;"></span>
                            </div>
                            
                            <div style="margin-left: 16px;">
                                <h3 id="headerName" style="font-size:1.15rem; color:var(--text-dark); margin:0; line-height:1.2; font-weight:700;">Loading...</h3>
                                <span id="typingIndicator" style="font-size:0.8rem; color:var(--primary); height:16px; display:block; font-style:italic; font-weight: 500;"></span>
                            </div>
                            
                            <div class="header-nav" style="margin-left: 40px;">
                                <div class="header-nav-item active" onclick="switchInnerTab('chat')">Chat</div>
                                <div class="header-nav-item" onclick="switchInnerTab('files')">Files</div>
                                <div class="header-nav-item" onclick="switchInnerTab('photos')">Photos</div>
                            </div>
                        </div>
                        
                        <div class="header-actions">
                            <button class="btn-icon" id="headerInfoBtn" style="display:none;" onclick="toggleGroupInfo()" title="Group Info"><i class="ri-group-line"></i></button>
                            <button class="btn-icon" onclick="toggleHeaderMenu(event)"><i class="ri-more-2-fill"></i></button>
                            <div id="chatOptionsDropdown">
                                </div>
                        </div>
                    </div>
                    
                    <div id="chatMessagesContainer" style="display:flex; flex-direction:column; flex:1; height:100%; overflow:hidden;">
                        <div class="messages-box" id="msgBox" onscroll="handleScroll()"></div>
                        
                        <div id="editModeBar">
                            <div style="display:flex; align-items:center; gap:10px;"><i class="ri-edit-2-fill"></i> <span>Editing message...</span></div>
                            <i class="ri-close-line" style="cursor:pointer; font-size:1.4rem; color: var(--text-dark);" onclick="cancelEdit()" title="Cancel Edit"></i>
                        </div>
                        
                        <div class="input-area">
                            <div id="filePreview">
                                <div style="display: flex; align-items: center; gap: 12px; overflow: hidden;">
                                    <div style="width: 36px; height: 36px; background: var(--primary-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <i class="ri-file-text-fill" style="color: var(--primary); font-size: 1.2rem;"></i>
                                    </div>
                                    <span id="filePreviewName" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; color: var(--text-dark);">filename.pdf</span>
                                </div>
                                <button class="btn-icon-small" onclick="clearFile()" style="border:none; box-shadow:none;"><i class="ri-close-line" style="color: #ef4444; font-size: 1.4rem;"></i></button>
                            </div>
                            <div class="input-wrapper">
                                <input type="file" id="fileUpload" hidden onchange="queueFile(this)">
                                <input type="text" id="msgInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') submitMessage()">
                                <div class="input-tools">
                                    <button class="btn-tool" title="Emoji" onclick="toggleEmojiPicker(event)"><i class="ri-emotion-line"></i></button>
                                    <label for="fileUpload" class="btn-tool" title="Attach file"><i class="ri-attachment-2"></i></label>
                                    <button class="btn-tool btn-send" onclick="submitMessage()" title="Send"><i class="ri-send-plane-2-fill"></i></button>
                                </div>
                            </div>
                            <div id="emojiPicker"></div>
                        </div>
                    </div>

                    <div id="chatFilesContainer" style="display:none; flex-direction:column; flex:1; height:100%; background:var(--surface); overflow-y:auto; padding:40px;">
                        <div id="filesEmptyState" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center;">
                            <div style="width: 160px; height: 160px; background: linear-gradient(135deg, var(--hover-bg), var(--primary-light)); border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 30px; box-shadow: var(--shadow-md);">
                                <i class="ri-folder-upload-fill" style="font-size: 6rem; color: var(--primary);"></i>
                            </div>
                            <h3 style="font-size: 1.4rem; color: var(--text-dark); margin-bottom: 12px; font-weight: 800;">Share files in this chat</h3>
                            <p style="font-size: 1rem; color: var(--text-muted); margin-bottom: 30px; max-width: 300px;">When you upload files to this tab, they will be securely shared with the conversation.</p>
                            <button class="btn-primary" style="width: auto; padding: 12px 32px;" onclick="document.getElementById('fileUpload').click()">
                                <i class="ri-upload-2-line" style="vertical-align: middle; margin-right: 8px;"></i> Upload File
                            </button>
                        </div>
                        <div id="filesContent" style="display:none; width:100%; flex-direction:column; max-width: 800px; margin: 0 auto;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                                <h3 style="font-weight: 700; font-size: 1.2rem;">Shared Files</h3>
                                <button class="btn-primary" style="width: auto; padding: 8px 20px; font-size: 0.9rem;" onclick="document.getElementById('fileUpload').click()"><i class="ri-upload-2-line"></i> Upload</button>
                            </div>
                            <div id="filesList" style="display:flex; flex-direction:column; gap:12px;"></div>
                        </div>
                    </div>
                    
                    <div id="chatPhotosContainer" style="display:none; flex-direction:column; flex:1; height:100%; background:var(--surface); overflow-y:auto; padding:40px;">
                        <div id="photosEmptyState" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center;">
                            <div style="width: 160px; height: 160px; background: linear-gradient(135deg, var(--hover-bg), var(--primary-light)); border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 30px; box-shadow: var(--shadow-md);">
                                <i class="ri-image-2-fill" style="font-size: 6rem; color: var(--primary);"></i>
                            </div>
                            <h3 style="font-size: 1.4rem; color: var(--text-dark); margin-bottom: 12px; font-weight: 800;">No photos shared yet</h3>
                            <p style="font-size: 1rem; color: var(--text-muted); margin-bottom: 30px; max-width: 300px;">Photos and images sent in the chat will automatically appear here as a gallery.</p>
                        </div>
                        <div id="photosGrid" style="display:none; width:100%; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; align-content: start; max-width: 1000px; margin: 0 auto;"></div>
                    </div>
                </div>
                
                <div id="groupInfoPanel" class="group-info-panel">
                    <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--surface); position: sticky; top: 0; z-index: 10;">
                        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark);">Group Members</h3>
                        <button class="btn-icon-small" onclick="closeGroupInfo()"><i class="ri-close-line"></i></button>
                    </div>
                    <div style="padding: 20px; border-bottom: 1px solid var(--border); background: var(--surface);">
                        <button onclick="openAddMemberModal()" style="width: 100%; padding: 10px; background: var(--primary-light); border: 1px dashed var(--primary); color: var(--primary); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s;">
                            <i class="ri-user-add-fill"></i> Add people
                        </button>
                    </div>
                    <div id="groupMembersList" style="flex: 1; overflow-y: auto; padding: 10px 20px;"></div>
                </div>
            </div>

            <div id="people_view" style="display: none; flex-direction: column; height: 100%; width: 100%; background: var(--surface);">
                <div style="padding: 32px 40px; border-bottom: 1px solid var(--border); background: var(--surface); position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-size: 2rem; font-weight:800; color: var(--text-dark);">People Directory</h2>
                        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 8px;">Find and connect with everyone in your organization.</p>
                    </div>
                    <div style="position: relative; width: 300px;">
                        <i class="ri-search-line" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; pointer-events: none;"></i>
                        <input type="text" id="directorySearch" placeholder="Search by name or role..." oninput="filterPeopleDirectory()" style="width: 100%; height: 48px; padding: 10px 15px 10px 42px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--bg-light); outline: none; margin: 0; box-sizing: border-box; font-size: 0.95rem; color: var(--text-dark);">
                    </div>
                </div>
                <div id="peopleListContainer" style="overflow-y: auto; flex: 1; padding: 20px 0;">
                    <?php foreach($all_users as $u): if($u['id'] != $my_id): ?>
                        <div class="people-card directory-item">
                            <div class="people-info">
                                <img src="<?= $u['profile_img'] ?>" class="avatar" loading="lazy" style="width: 56px; height: 56px;">
                                <div>
                                    <div class="dir-name" style="font-weight:700; font-size: 1.1rem; color: var(--text-dark); margin-bottom: 4px;"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="dir-role" style="font-size:0.9rem; color:var(--text-muted); font-weight: 500;"><i class="ri-briefcase-4-line" style="vertical-align: middle;"></i> <?= $u['role'] ?> <?= !empty($u['department']) ? '&bull; ' . $u['department'] : '' ?></div>
                                </div>
                            </div>
                            <button class="people-btn" onclick="startChat(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8')) ?>', '<?= addslashes($u['profile_img']) ?>')" title="Message <?= htmlspecialchars($u['name']) ?>">
                                <i class="ri-chat-3-fill"></i>
                            </button>
                        </div>
                    <?php endif; endforeach; ?>
                    <div id="dirEmptyState" style="display: none; text-align: center; padding: 40px; color: var(--text-muted); font-weight: 500;">
                        <i class="ri-user-unfollow-line" style="font-size: 3rem; color: var(--border); margin-bottom: 15px; display: block;"></i>
                        No people found matching your search.
                    </div>
                </div>
            </div>

        </section>
    </div>
</main>

<div class="modal-overlay" id="groupModal">
    <div class="modal" style="width: 450px; padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <h3 style="font-size:1.4rem; font-weight: 700; color: var(--text-dark);">Create Group</h3>
            <button class="btn-icon-small" style="border:none;" onclick="closeGroupModal()"><i class="ri-close-line" style="font-size:1.5rem;"></i></button>
        </div>
        <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Group Subject</label>
        <input type="text" id="groupName" placeholder="e.g. Marketing Team" style="margin-bottom:20px;">
        
        <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Add Members</label>
        <div style="position: relative;">
            <i class="ri-search-line" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" id="memberSearch" placeholder="Search people..." oninput="searchForGroup(this.value)" style="padding-left: 40px; margin-bottom: 12px;">
        </div>
        
        <div id="groupUserList" style="max-height:250px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-md); margin-bottom:24px; padding: 5px;"></div>
        
        <button class="btn-primary" onclick="createGroup()">Create Group</button>
    </div>
</div>

<div class="modal-overlay" id="addMemberModal">
    <div class="modal" style="width: 450px; padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <h3 style="font-size:1.4rem; font-weight: 700; color: var(--text-dark);">Add to Group</h3>
            <button class="btn-icon-small" style="border:none;" onclick="closeAddMemberModal()"><i class="ri-close-line" style="font-size:1.5rem;"></i></button>
        </div>
        <div style="position: relative;">
            <i class="ri-search-line" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" id="addMemberSearch" placeholder="Search people..." oninput="searchForAddMember(this.value)" style="padding-left: 40px; margin-bottom: 12px;">
        </div>
        <div id="addMemberUserList" style="max-height:250px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-md); margin-bottom:24px; padding: 5px;"></div>
        <button class="btn-primary" onclick="submitAddMembers()">Add Selected Members</button>
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
    const myUserName = "<?php echo htmlspecialchars($my_username, ENT_QUOTES, 'UTF-8'); ?>";
    let searchDebounce = null;
    let typingTimer = null;
    let isGroupChat = false;

    function switchMainTab(tabId, el) {
        document.querySelectorAll('.sidebar-secondary-teams .nav-icon').forEach(n => n.classList.remove('active'));
        el.classList.add('active');

        ['chat_view', 'people_view'].forEach(id => {
            const domEl = document.getElementById(id);
            if(domEl) domEl.style.display = 'none';
        });
        
        const targetEl = document.getElementById(tabId);
        if(targetEl) targetEl.style.display = 'flex';
        
        const chatSidebar = document.getElementById('chatSidebar');
        if(tabId !== 'chat_view') {
            chatSidebar.style.display = 'none';
        } else {
            chatSidebar.style.display = 'flex';
            if(activeConvId) fetchMessages(false);
        }
    }

    function filterPeopleDirectory() {
        let input = document.getElementById('directorySearch').value.toLowerCase();
        let items = document.querySelectorAll('.directory-item');
        let visibleCount = 0;

        items.forEach(item => {
            let name = item.querySelector('.dir-name').textContent.toLowerCase();
            let role = item.querySelector('.dir-role').textContent.toLowerCase();
            
            if (name.includes(input) || role.includes(input)) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        let emptyState = document.getElementById('dirEmptyState');
        if (visibleCount === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    }

    const emojis = [
  // Original Emojis
  '😀','😃','😄','😁','😆','😅','😂','🤣','🥲','☺️','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾',

  // Hands & Body Parts
  '👋','🤚','🖐️','✋','🖖','🫱','🫲','🫳','🫴','🫷','🫸','👌','🤌','🤏','✌️','🤞','🫰','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','🫵','👍','👎','✊','👊','🤛','🤜','👏','🙌','🫶','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🦿','🦵','🦶','👂','🦻','👃','🧠','🫀','🫁','🦷','🦴','👀','👁️','👅','👄','🫦','👣','🫆','🧬','🩸',

  // People & Appearance
  '👶','🧒','👦','👧','🧑','👱','👨','🧔','🧔‍♂️','🧔‍♀️','👨‍🦰','👨‍🦱','👨‍🦳','👨‍🦲','👩','👩‍🦰','🧑‍🦰','👩‍🦱','🧑‍🦱','👩‍🦳','🧑‍🦳','👩‍🦲','🧑‍🦲','👱‍♀️','👱‍♂️','🧓','👴','👵','🧏','🧏‍♂️','🧏‍♀️','👳','👳‍♂️','👳‍♀️','👲','🧕','🤰','🫃','🫄','👼','🗣️','👤','👥','🫂','🦰','🦱','🦳','🦲',

  // Gestures & Expressions
  '🙍','🙍‍♂️','🙍‍♀️','🙎','🙎‍♂️','🙎‍♀️','🙅','🙅‍♂️','🙅‍♀️','🙆','🙆‍♂️','🙆‍♀️','💁','💁‍♂️','💁‍♀️','🙋','🙋‍♂️','🙋‍♀️','🧏','🧏‍♂️','🧏‍♀️','🙇','🙇‍♂️','🙇‍♀️','🤦','🤦‍♂️','🤦‍♀️','🤷','🤷‍♂️','🤷‍♀️',

  // Activities & Sports
  '🤱','👩‍🍼','👨‍🍼','🧑‍🍼','💆','💆‍♂️','💆‍♀️','💇','💇‍♂️','💇‍♀️','🚶','🚶‍♂️','🚶‍♀️','🚶‍➡️','🚶‍♀️‍➡️','🚶‍♂️‍➡️','🧍','🧍‍♂️','🧍‍♀️','🧎','🧎‍♂️','🧎‍♀️','🧎‍➡️','🧎‍♀️‍➡️','🧎‍♂️‍➡️','🧑‍🦯','🧑‍🦯‍➡️','👨‍🦯','👨‍🦯‍➡️','👩‍🦯','👩‍🦯‍➡️','🧑‍🦼','🧑‍🦼‍➡️','👨‍🦼','👨‍🦼‍➡️','👩‍🦼','👩‍🦼‍➡️','🧑‍🦽','🧑‍🦽‍➡️','👨‍🦽','👨‍🦽‍➡️','👩‍🦽','👩‍🦽‍➡️','🏃','🏃‍♂️','🏃‍♀️','🏃‍➡️','🏃‍♀️‍➡️','🏃‍♂️‍➡️','🧑‍🩰','💃','🕺','🕴️','👯','👯‍♂️','👯‍♀️','🧖','🧖‍♂️','🧖‍♀️','🧗','🧗‍♂️','🧗‍♀️','🤺','🏇','⛷️','🏂','🏌️','🏌️‍♂️','🏌️‍♀️','🏄','🏄‍♂️','🏄‍♀️','🚣','🚣‍♂️','🚣‍♀️','🏊','🏊‍♂️','🏊‍♀️','⛹️','⛹️‍♂️','⛹️‍♀️','🏋️','🏋️‍♂️','🏋️‍♀️','🚴','🚴‍♂️','🚴‍♀️','🚵','🚵‍♂️','🚵‍♀️','🤸','🤸‍♂️','🤸‍♀️','🤼','🤼‍♂️','🤼‍♀️','🤽','🤽‍♂️','🤽‍♀️','🤾','🤾‍♂️','🤾‍♀️','🤹','🤹‍♂️','🤹‍♀️','🧘','🧘‍♂️','🧘‍♀️','🛀','🛌',

  // Professions, Roles & Fantasy
  '🧑‍⚕️','👨‍⚕️','👩‍⚕️','🧑‍🎓','👨‍🎓','👩‍🎓','🧑‍🏫','👨‍🏫','👩‍🏫','🧑‍⚖️','👨‍⚖️','👩‍⚖️','🧑‍🌾','👨‍🌾','👩‍🌾','🧑‍🍳','👨‍🍳','👩‍🍳','🧑‍🔧','👨‍🔧','👩‍🔧','🧑‍🏭','👨‍🏭','👩‍🏭','🧑‍💼','👨‍💼','👩‍💼','🧑‍🔬','👨‍🔬','👩‍🔬','🧑‍💻','👨‍💻','👩‍💻','🧑‍🎤','👨‍🎤','👩‍🎤','🧑‍🎨','👨‍🎨','👩‍🎨','🧑‍✈️','👨‍✈️','👩‍✈️','🧑‍🚀','👨‍🚀','👩‍🚀','🧑‍🚒','👨‍🚒','👩‍🚒','👮','👮‍♂️','👮‍♀️','🕵️','🕵️‍♂️','🕵️‍♀️','💂','💂‍♂️','💂‍♀️','🥷','👷','👷‍♂️','👷‍♀️','🫅','🤴','👸','🤵','🤵‍♂️','🤵‍♀️','👰','👰‍♂️','👰‍♀️','🎅','🤶','🧑‍🎄','🦸','🦸‍♂️','🦸‍♀️','🦹','🦹‍♂️','🦹‍♀️','🧙','🧙‍♂️','🧙‍♀️','🧚','🧚‍♂️','🧚‍♀️','🧛','🧛‍♂️','🧛‍♀️','🧜','🧜‍♂️','🧜‍♀️','🧝','🧝‍♂️','🧝‍♀️','🧞','🧞‍♂️','🧞‍♀️','🧟','🧟‍♂️','🧟‍♀️','🧌','🫈','👯','👯‍♂️','👯‍♀️',

  // Families & Couples
  '🧑‍🤝‍🧑','👭','👫','👬','💏','👩‍❤️‍💋‍👨','👨‍❤️‍💋‍👨','👩‍❤️‍💋‍👩','💑','👩‍❤️‍👨','👨‍❤️‍👨','👩‍❤️‍👩','👨‍👩‍👦','👨‍👩‍👧','👨‍👩‍👧‍👦','👨‍👩‍👦‍👦','👨‍👩‍👧‍👧','👨‍👨‍👦','👨‍👨‍👧','👨‍👨‍👧‍👦','👨‍👨‍👦‍👦','👨‍👨‍👧‍👧','👩‍👩‍👦','👩‍👩‍👧','👩‍👩‍👧‍👦','👩‍👩‍👦‍👦','👩‍👩‍👧‍👧','👨‍👦','👨‍👦‍👦','👨‍👧','👨‍👧‍👦','👨‍👧‍👧','👩‍👦','👩‍👦‍👦','👩‍👧','👩‍👧‍👦','👩‍👧‍👧','👪','🧑‍🧑‍🧒','🧑‍🧑‍🧒‍🧒','🧑‍🧒','🧑‍🧒‍🧒',

  // Hearts, Shapes & Emotions
  '💌','💘','💝','💖','💗','💓','💞','💕','💟','❣️','💔','❤️‍🔥','❤️‍🩹','❤️','🩷','🧡','💛','💚','💙','🩵','💜','🤎','🖤','🩶','🤍','💋','💯','💢','💥','💦','💨','🕳️','💬','👁️‍🗨️','🗨️','🗯️','💭','💤','🫟','🔴','🟠','🟡','🟢','🔵','🟣','🟤','⚫','⚪','🟥','🟧','🟨','🟩','🟦','🟪','🟫','⬛','⬜','◼️','◻️','◾','◽','▪️','▫️','🔶','🔷','🔸','🔹','🔺','🔻','💠','🔘','🔳','🔲',

  // Location & Warning
  '🛗','🏧','🚮','🚰','♿','🚹','🚺','🚻','🚼','🚾','🛂','🛃','🛄','🛅','⚠️','🚸','⛔','🚫','🚳','🚭','🚯','🚱','🚷','📵','🔞','☢️','☣️',

  // Arrows & AV
  '⬆️','↗️','➡️','↘️','⬇️','↙️','⬅️','↖️','↕️','↔️','↩️','↪️','⤴️','⤵️','🔃','🔄','🔙','🔚','🔛','🔜','🔝','🔀','🔁','🔂','▶️','⏩','⏭️','⏯️','◀️','⏪','⏮️','🔼','⏫','🔽','⏬','⏸️','⏹️','⏺️','⏏️','🎦','🔅','🔆','📶','🛜','📳','📴',

  // Identity & Beliefs
  '🛐','🕉️','✡️','☸️','☯️','✝️','☦️','☪️','☮️','🕎','🔯','🪯','♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓','⛎','♀️','♂️','⚧️'
];
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
        if(input) { input.value += emo; input.focus(); }
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

    function setupLayoutObserver() {
        const primarySidebar = document.querySelector('.sidebar-primary');
        const secondarySidebar = document.querySelector('.sidebar-secondary');
        const mainContent = document.getElementById('mainContent');
        if (!primarySidebar || !mainContent) return;

        const updateMargin = () => {
            if (window.innerWidth <= 992) {
                mainContent.style.marginLeft = '0'; mainContent.style.width = '100%'; return;
            }
            let totalWidth = primarySidebar.offsetWidth;
            if (secondarySidebar && secondarySidebar.classList.contains('open')) { totalWidth += secondarySidebar.offsetWidth; }
            mainContent.style.marginLeft = totalWidth + 'px';
            mainContent.style.width = `calc(100% - ${totalWidth}px)`;
        };

        new ResizeObserver(() => updateMargin()).observe(primarySidebar);
        if (secondarySidebar) { new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] }); }
        window.addEventListener('resize', updateMargin);
        updateMargin();
    }
    document.addEventListener('DOMContentLoaded', setupLayoutObserver);

    function toggleMobileSidebar() {
        if(window.innerWidth <= 992) {
            const sb = document.getElementById('chatSidebar');
            if(activeConvId) sb.classList.add('hide-mobile'); else sb.classList.remove('hide-mobile');
        }
    }
    window.addEventListener('resize', toggleMobileSidebar);

    function loadSidebar() {
        if(isSidebarFetching) return;
        isSidebarFetching = true;

        let fd = new FormData();
        fd.append('action', 'get_recent_chats');
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            isSidebarFetching = false;
            let html = '';
            data.forEach(c => {
                let active = (c.conversation_id == activeConvId) ? 'active' : '';
                let unread = c.unread > 0 ? `<span style="background:var(--primary); color:white; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:12px;">${c.unread}</span>` : '';
                
                let msgText = c.last_msg || '';
                if (msgText.includes('Voice call') || msgText.includes('Video meeting') || msgText.includes('audio:') || msgText.includes('video:')) {
                     msgText = 'Tap to see conversation';
                }

                html += `<div class="chat-item ${active}" onclick="loadConversation(${c.conversation_id})">
                            <div style="position:relative;">
                                <img src="${c.avatar}" class="avatar" loading="lazy" style="margin:0 12px 0 0;">
                                <span style="position:absolute; bottom:2px; right:10px; width:14px; height:14px; border:3px solid ${active ? 'var(--primary-light)' : 'var(--surface)'}; border-radius:50%; background-color:#22c55e;"></span>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                    <div class="chat-item-name" style="font-weight:600; color:var(--text-dark); font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.name}</div>
                                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight: 500;">${c.time}</div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:80%; font-size:0.85rem; color:var(--text-muted); ${msgText.includes('🚫') ? 'font-style:italic;' : ''}">${msgText}</span>
                                    ${unread}
                                </div>
                            </div>
                        </div>`;
            });
            if(data.length === 0) html = '<div style="text-align:center; padding: 40px; color:var(--text-muted);"><i class="ri-chat-1-line" style="font-size: 2rem; color: var(--border); display: block; margin-bottom: 10px;"></i>No active chats</div>';
            document.getElementById('chatList').innerHTML = html;
        }).catch((e) => { 
            isSidebarFetching = false; 
            console.error("Sidebar Fetch Error:", e);
        });
    }

    function loadConversation(convId) {
        if(activeConvId === convId) return; 
        
        activeConvId = convId; editingMsgId = null; lastFetchedMsgId = 0; 
        isUserScrolling = false; isGroupChat = false; 
        document.getElementById('groupInfoPanel').style.display = 'none';

        toggleMobileSidebar();
        document.getElementById('chatAreaEmpty').style.display = 'none';
        
        const activeArea = document.getElementById('chatAreaActive');
        activeArea.style.display = 'flex';

        document.getElementById('headerName').innerHTML = '<div class="skeleton skeleton-text" style="width: 120px; margin-top: 5px;"></div>';
        let avatarEl = document.getElementById('headerAvatar');
        avatarEl.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
        avatarEl.classList.add('skeleton');
        document.getElementById('typingIndicator').textContent = '';
        
        document.getElementById('msgBox').innerHTML = `
            <div class="msg-wrapper incoming"><div class="skeleton skeleton-msg-in"></div></div>
            <div class="msg-wrapper outgoing"><div class="skeleton skeleton-msg-out"></div></div>
            <div class="msg-wrapper incoming"><div class="skeleton skeleton-msg-in" style="width: 180px;"></div></div>
        `;
        
        switchInnerTab('chat');
        fetchMessages(true); 
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        let msgInput = document.getElementById('msgInput');
        if(msgInput) {
            msgInput.addEventListener('input', function() {
                if(activeConvId) {
                    startTyping(); clearTimeout(typingTimer); typingTimer = setTimeout(stopTyping, 2000);
                }
            });
            msgInput.addEventListener('blur', stopTyping);
        }
    });

    function toggleGroupInfo() {
        let panel = document.getElementById('groupInfoPanel');
        if (panel.style.display === 'flex') { panel.style.display = 'none'; } 
        else { panel.style.display = 'flex'; loadGroupMembers(); }
    }

    function closeGroupInfo() { document.getElementById('groupInfoPanel').style.display = 'none'; }

    function loadGroupMembers() {
        if(!activeConvId) return;
        let fd = new FormData(); fd.append('action', 'get_group_info'); fd.append('conversation_id', activeConvId);
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(members => {
            let html = '';
            members.forEach(m => {
                html += `<div style="display:flex; align-items:center; gap:12px; padding: 12px; border-bottom: 1px solid var(--border-light); border-radius: var(--radius-sm); transition: background 0.2s;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='transparent'">
                            <img src="${m.profile_img}" loading="lazy" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                            <div>
                                <div style="font-weight:700; font-size:0.95rem; color: var(--text-dark);">${escapeHTML(m.display_name)} ${m.id == <?php echo $my_id; ?> ? '<span style="color:var(--primary); font-weight: 500;">(You)</span>' : ''}</div>
                                <div style="font-size:0.8rem; color:var(--text-muted); font-weight: 500;">${escapeHTML(m.role)}</div>
                            </div>
                        </div>`;
            });
            document.getElementById('groupMembersList').innerHTML = html;
        });
    }

    function openAddMemberModal() {
        document.getElementById('addMemberModal').style.display = 'flex';
        selectedAddMembers.clear(); document.getElementById('addMemberSearch').value = ''; searchForAddMember('');
    }
    function closeAddMemberModal() { document.getElementById('addMemberModal').style.display = 'none'; }

    function searchForAddMember(val) {
        let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val); 
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedAddMembers.has(u.id) ? 'background:var(--primary-light); border-color:var(--primary);' : 'background:var(--surface); border-color:var(--border-light);';
                let icon = selectedAddMembers.has(u.id) ? '<i class="ri-checkbox-circle-fill" style="color:var(--primary); font-size:1.2rem;"></i>' : '<i class="ri-checkbox-blank-circle-line" style="color:var(--text-muted); font-size:1.2rem;"></i>';
                html += `<div onclick="toggleAddMember(${u.id}, this)" style="padding:12px 16px; display:flex; align-items:center; gap:16px; cursor:pointer; border:1px solid; border-radius:var(--radius-sm); margin-bottom:8px; transition:0.2s; ${isSel}">
                            ${icon}
                            <img src="${u.profile_img}" loading="lazy" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <div style="font-weight:600; font-size:0.95rem;">${escapeHTML(u.display_name)}</div>
                        </div>`;
            });
            document.getElementById('addMemberUserList').innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--text-muted); font-weight: 500;">No users found</div>';
        });
    }

    function toggleAddMember(uid, el) {
        if(selectedAddMembers.has(uid)) {
            selectedAddMembers.delete(uid);
            el.style.background = 'var(--surface)'; el.style.borderColor = 'var(--border-light)';
            el.querySelector('i').className = 'ri-checkbox-blank-circle-line'; el.querySelector('i').style.color = 'var(--text-muted)';
        } else {
            selectedAddMembers.add(uid);
            el.style.background = 'var(--primary-light)'; el.style.borderColor = 'var(--primary)';
            el.querySelector('i').className = 'ri-checkbox-circle-fill'; el.querySelector('i').style.color = 'var(--primary)';
        }
    }

    function submitAddMembers() {
        if(selectedAddMembers.size === 0) return Swal.fire('Wait', 'Select at least 1 member to add.', 'warning');
        if(!activeConvId) return;

        let fd = new FormData();
        fd.append('action', 'add_members_to_group'); fd.append('conversation_id', activeConvId); fd.append('members', JSON.stringify(Array.from(selectedAddMembers)));
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if (data.status === 'success') { closeAddMemberModal(); loadGroupMembers(); fetchMessages(false); }
        });
    }

    function queueFile(input) {
        if(input.files.length > 0) {
            document.getElementById('filePreview').style.display = 'flex';
            document.getElementById('filePreviewName').innerText = input.files[0].name;
            document.getElementById('msgInput').focus();
        }
    }
    function clearFile() {
        let input = document.getElementById('fileUpload'); input.value = ''; document.getElementById('filePreview').style.display = 'none';
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
            let box = document.getElementById('msgBox'); box.scrollTo({ top: box.scrollHeight });
        } else if (tabName === 'files') {
            navItems[1].classList.add('active'); document.getElementById('chatFilesContainer').style.display = 'flex';
        } else if (tabName === 'photos') {
            navItems[2].classList.add('active'); document.getElementById('chatPhotosContainer').style.display = 'flex';
        }
    }

    function addPhotoToGallery(path, id) {
        let emptyState = document.getElementById('photosEmptyState');
        let grid = document.getElementById('photosGrid');
        
        if(emptyState) emptyState.style.display = 'none';
        if(grid) {
            grid.style.display = 'grid';
            if(!document.getElementById('gallery-img-'+id)) {
                grid.insertAdjacentHTML('beforeend', `<div id="gallery-img-${id}" style="aspect-ratio: 1; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border); box-shadow: var(--shadow-sm);"><img src="${path}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'" onclick="window.open('${path}', '_blank')"></div>`);
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
            list.insertAdjacentHTML('beforeend', `<a href="${path}" target="_blank" id="file-item-${id}" style="display:flex; align-items:center; gap:20px; padding:20px; border:1px solid var(--border); border-radius:var(--radius-md); background:var(--surface); text-decoration:none; color:var(--text-dark); transition:all 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-md)'; this.style.borderColor='var(--primary)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-sm)'; this.style.borderColor='var(--border)'">
                <div style="width:48px; height:48px; border-radius:12px; background:var(--primary-light); display:flex; align-items:center; justify-content:center;">
                    <i class="ri-file-text-fill" style="font-size:1.8rem; color:var(--primary);"></i>
                </div>
                <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; font-size:1.05rem;">${safeName}</div>
                <div style="width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; background: var(--hover-bg);">
                    <i class="ri-download-2-fill" style="color:var(--text-muted); font-size:1.2rem;"></i>
                </div>
            </a>`);
        }
    }

    function backToList() {
        activeConvId = null; toggleMobileSidebar();
        document.getElementById('chatAreaEmpty').style.display = 'flex';
        document.getElementById('chatAreaActive').style.display = 'none';
        document.getElementById('groupInfoPanel').style.display = 'none';
    }

    function handleScroll() { let box = document.getElementById('msgBox'); isUserScrolling = (box.scrollHeight - box.scrollTop - box.clientHeight > 50); }

    function buildMessageHTML(m) {
        if (m.message_type === 'call') { return ''; }

        let cls = m.is_me ? 'outgoing' : 'incoming';
        let content = m.message;
        let isDeleted = m.is_deleted;
        let isEdited = m.is_edited;
        
        let metaHtml = `<div class="msg-meta">`;
        if (isEdited && !isDeleted) metaHtml += `<span style="font-size:0.7rem; font-style:italic; margin-right:6px;">Edited</span>`;
        metaHtml += `<span>${m.time}</span>`;
        if (m.is_me && !isDeleted) {
            let tickClass = (m.read_status === 2) ? 'tick-read' : 'tick-sent';
            let tickMark = (m.read_status === 2) ? '<i class="ri-check-double-line"></i>' : '<i class="ri-check-line"></i>';
            metaHtml += `<span class="ticks ${tickClass}">${tickMark}</span>`;
        }
        metaHtml += `</div>`;

        let menuHtml = '';
        if (m.is_me && !isDeleted && m.message_type === 'text') {
            menuHtml = `
                <button class="msg-menu-btn" onclick="toggleMsgMenu(event, ${m.id})"><i class="ri-arrow-down-s-line"></i></button>
                <div class="msg-dropdown" id="msg-drop-${m.id}">
                    <button onclick="initEdit(${m.id}, '${escapeHTML(m.message)}')"><i class="ri-pencil-fill mr-2" style="color: var(--text-muted); margin-right: 8px;"></i> Edit Message</button>
                    <button onclick="deleteMessage(${m.id})" class="delete-btn"><i class="ri-delete-bin-fill mr-2" style="margin-right: 8px;"></i> Delete</button>
                </div>
            `;
        } else if (m.is_me && !isDeleted) {
            menuHtml = `
                <button class="msg-menu-btn" onclick="toggleMsgMenu(event, ${m.id})"><i class="ri-arrow-down-s-line"></i></button>
                <div class="msg-dropdown" id="msg-drop-${m.id}">
                    <button onclick="deleteMessage(${m.id})" class="delete-btn"><i class="ri-delete-bin-fill mr-2" style="margin-right: 8px;"></i> Delete</button>
                </div>
            `;
        }

        let innerMsg = '';
        if (isDeleted) {
            innerMsg = `<div class="msg deleted"><i class="ri-forbid-line" style="vertical-align: middle; margin-right: 4px;"></i> 🚫 This message was deleted.</div>`;
        } else {
            if(m.message_type === 'image') content = `<img src="${m.attachment_path}" loading="lazy" style="max-width:100%; border-radius:8px; margin-bottom:8px; border: 1px solid var(--border-light);">`;
            else if(m.message_type === 'file') content = `<a href="${m.attachment_path}" target="_blank" style="display:flex; align-items:center; gap:12px; color:inherit; text-decoration:none; background:rgba(0,0,0,0.04); padding:12px; border-radius:8px; border: 1px solid rgba(0,0,0,0.05); transition: 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.08)'" onmouseout="this.style.background='rgba(0,0,0,0.04)'"><div style="width: 32px; height: 32px; background: white; border-radius: 6px; display: flex; align-items: center; justify-content: center;"><i class="ri-file-text-fill" style="font-size: 1.2rem; color: var(--primary);"></i></div> <span style="word-break: break-all; font-weight: 600;">${escapeHTML(m.message)}</span></a>`;
            
            if (content.includes('video:Workack-Meet')) { return ''; }

            let senderName = (!m.is_me && m.display_name) ? `<div style="font-size:0.8rem;color:var(--primary);margin-bottom:6px;font-weight:700;">${m.display_name}</div>` : '';
            innerMsg = `<div class="msg ${cls}">${senderName}<span style="display: block;">${content}</span>${metaHtml}${menuHtml}</div>`;
        }
        return `<div class="msg-wrapper ${cls}" id="msg-${m.id}" data-id="${m.id}">${innerMsg}</div>`;
    }

    function toggleMsgMenu(e, msgId) {
        e.stopPropagation(); let drop = document.getElementById('msg-drop-' + msgId);
        if (drop) {
            let isVisible = drop.style.display === 'block';
            document.querySelectorAll('.msg-dropdown').forEach(d => d.style.display = 'none');
            drop.style.display = isVisible ? 'none' : 'block';
        }
    }

    function fetchMessages(isInitialLoad = false) {
        if(!activeConvId || isFetchingMessages) return;
        isFetchingMessages = true;
        let fd = new FormData(); fd.append('action', 'get_messages'); fd.append('conversation_id', activeConvId); fd.append('last_msg_id', lastFetchedMsgId);

        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            isFetchingMessages = false;
            let msgs = data.messages; let info = data.info; let box = document.getElementById('msgBox');
            if(!box) return;

            if(info && isInitialLoad) {
                if(box.querySelector('.skeleton') || box.innerHTML.includes('ri-loader-4-line')) box.innerHTML = '';
                
                let avatarEl = document.getElementById('headerAvatar');
                avatarEl.classList.remove('skeleton');
                avatarEl.src = info.profile_img;
                
                document.getElementById('headerName').innerText = info.display_name;
                
                let infoBtn = document.getElementById('headerInfoBtn');
                if (info.is_group) { infoBtn.style.display = 'flex'; isGroupChat = true; } 
                else { infoBtn.style.display = 'none'; isGroupChat = false; document.getElementById('groupInfoPanel').style.display = 'none'; }
            }

            let inputArea = document.querySelector('.input-area');
            let isBlockedByMe = (data.blocked_by == <?php echo $my_id; ?>);
            let isBlockedByOther = (data.blocked_by != null && data.blocked_by != <?php echo $my_id; ?>);
            let blockBtnHtml = '';

            if (info && !info.is_group) {
                if (isBlockedByMe) {
                    blockBtnHtml = `<button onclick="toggleBlockStatus('unblock')"><i class="ri-user-unfollow-line"></i> Unblock Contact</button>`;
                } else {
                    blockBtnHtml = `<button onclick="toggleBlockStatus('block')" style="color: #ef4444;"><i class="ri-forbid-line"></i> Block Contact</button>`;
                }
            }

            let dropdown = document.getElementById('chatOptionsDropdown');
            dropdown.innerHTML = `
                <button onclick="clearDeleteChat('clear')"><i class="ri-eraser-line"></i> Clear Chat</button>
                <button onclick="clearDeleteChat('delete')" style="color: #ef4444;"><i class="ri-delete-bin-line"></i> Delete Chat</button>
                ${blockBtnHtml}
            `;

            if(document.getElementById('blockNotice')) document.getElementById('blockNotice').remove();
            
            if (isBlockedByMe) {
                inputArea.style.display = 'none';
                box.insertAdjacentHTML('beforeend', '<div id="blockNotice" style="text-align:center; padding:10px 20px; color:var(--text-muted); background:var(--bg-light); border-radius:var(--radius-md); margin-top:10px; font-weight:500;">You blocked this contact. Unblock to send a message.</div>');
                if(!isUserScrolling) box.scrollTo({ top: box.scrollHeight });
            } else if (isBlockedByOther) {
                inputArea.style.display = 'none';
                box.insertAdjacentHTML('beforeend', '<div id="blockNotice" style="text-align:center; padding:10px 20px; color:var(--text-muted); background:var(--bg-light); border-radius:var(--radius-md); margin-top:10px; font-weight:500;">You cannot reply to this conversation.</div>');
                if(!isUserScrolling) box.scrollTo({ top: box.scrollHeight });
            } else {
                inputArea.style.display = 'flex';
            }


            let typingDiv = document.getElementById('typingIndicator');
            if(data.typing && data.typing.length > 0) { typingDiv.textContent = data.typing.join(', ') + ' is typing...'; } else { typingDiv.textContent = ''; }

            if (msgs.length > 0) {
                msgs.forEach(m => {
                    lastFetchedMsgId = Math.max(lastFetchedMsgId, m.id);
                    let existingMsg = document.getElementById(`msg-${m.id}`);
                    
                    let htmlString = buildMessageHTML(m);
                    if (htmlString !== '') {
                        if (existingMsg) { existingMsg.outerHTML = htmlString; } else { box.insertAdjacentHTML('beforeend', htmlString); }
                    }
                    
                    if (m.message_type === 'image' && !m.is_deleted) { addPhotoToGallery(m.attachment_path, m.id); } 
                    else if (m.message_type === 'file' && !m.is_deleted) { addFileToGallery(m.attachment_path, m.id, m.message); }
                });
                if(!isUserScrolling) box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            } else if (isInitialLoad) {
                box.innerHTML = '<div style="text-align:center; padding:60px 20px; color:var(--text-muted);"><div style="width: 80px; height: 80px; background: var(--border-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;"><i class="ri-chat-smile-2-fill" style="font-size: 2.5rem; color: var(--text-muted);"></i></div><h4 style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">Start of conversation</h4><p style="font-size: 0.95rem;">Send a message to break the ice!</p></div>';
            }

            if (data.read_ids && data.read_ids.length > 0) {
                data.read_ids.forEach(id => {
                    let msgEl = document.getElementById(`msg-${id}`);
                    if (msgEl) {
                        let tickSpan = msgEl.querySelector('.ticks.tick-sent'); 
                        if (tickSpan) {
                            tickSpan.className = 'ticks tick-read'; 
                            tickSpan.innerHTML = '<i class="ri-check-double-line"></i>'; 
                        }
                    }
                });
            }
            
        }).catch((e) => { 
            isFetchingMessages = false; 
            console.error("Fetch Messages Error:", e);
            let box = document.getElementById('msgBox');
            if (isInitialLoad && box) {
                box.innerHTML = '<div style="text-align:center; padding: 40px; color: #ef4444;">Error loading messages. Please try again.</div>';
            }
        });
    }

    function submitMessage() {
        let input = document.getElementById('msgInput'); let txt = input.value.trim(); let fileInput = document.getElementById('fileUpload');
        if(!txt && fileInput.files.length === 0) return;

        let fd = new FormData();
        if (editingMsgId) {
            fd.append('action', 'edit_message'); fd.append('message_id', editingMsgId); fd.append('new_text', txt);
            cancelEdit(); lastFetchedMsgId = 0; document.getElementById('msgBox').innerHTML = ''; 
        } else {
            fd.append('action', 'send_message'); fd.append('conversation_id', activeConvId); fd.append('message', txt);
            if (fileInput.files.length > 0) { fd.append('file', fileInput.files[0]); } else { fd.append('type', 'text'); }
            
            let box = document.getElementById('msgBox');
            let displayTxt = fileInput.files.length > 0 ? "<i class='ri-loader-4-line ri-spin'></i> Uploading file..." : escapeHTML(txt);
            
            let tempHtml = `<div class="msg-wrapper outgoing temp-pending-msg"><div class="msg outgoing"><span style="display: block;">${displayTxt}</span><div class="msg-meta"><span>Just now</span><span class="ticks"><i class="ri-time-line"></i></span></div></div></div>`;
            box.insertAdjacentHTML('beforeend', tempHtml);
            box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
        }

        fetch(window.location.href, { method: 'POST', body: fd }).then(async (r) => {
            let res = await r.json();
            if(res.status === 'error') { Swal.fire('Error', res.message, 'error'); }
            document.querySelectorAll('.temp-pending-msg').forEach(el => el.remove());
            input.value = ''; clearFile(); fetchMessages(false); loadSidebar();
        });
        input.value = ''; stopTyping();
    }

    function initEdit(id, text) {
        editingMsgId = id; document.getElementById('msgInput').value = text;
        document.getElementById('msgInput').focus(); document.getElementById('editModeBar').style.display = 'flex';
    }
    function cancelEdit() {
        editingMsgId = null; document.getElementById('msgInput').value = ''; document.getElementById('editModeBar').style.display = 'none';
    }

    function deleteMessage(id) {
        Swal.fire({
            title: 'Delete Message?', text: "This will delete the message for everyone.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete'
        }).then((res) => {
            if(res.isConfirmed) {
                let msgWrapper = document.getElementById('msg-' + id);
                if (msgWrapper) {
                    let innerMsg = msgWrapper.querySelector('.msg');
                    if(innerMsg) {
                        innerMsg.className = 'msg deleted';
                        innerMsg.innerHTML = '<i class="ri-forbid-line" style="vertical-align: middle; margin-right: 4px;"></i> 🚫 This message was deleted.';
                    }
                    let menuBtn = msgWrapper.querySelector('.msg-menu-btn');
                    if(menuBtn) menuBtn.remove();
                }

                let fd = new FormData(); fd.append('action', 'delete_message'); fd.append('message_id', id);
                fetch(window.location.href, { method: 'POST', body: fd }); 
            }
        });
    }

    function toggleHeaderMenu(e) {
        e.stopPropagation(); let menu = document.getElementById('chatOptionsDropdown');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    function clearDeleteChat(type) {
        let msg = type === 'clear' ? "Clear all messages in this chat?" : "Delete this conversation?";
        Swal.fire({
            title: 'Are you sure?', text: msg, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes'
        }).then((res) => {
            if(res.isConfirmed) {
                let fd = new FormData(); fd.append('action', type + '_chat'); fd.append('conversation_id', activeConvId);
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

    function toggleBlockStatus(type) {
        if(!activeConvId) return;
        let action = type === 'block' ? 'block_user' : 'unblock_user';
        let fd = new FormData();
        fd.append('action', action);
        fd.append('conversation_id', activeConvId);
        fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
            lastFetchedMsgId = 0;
            document.getElementById('msgBox').innerHTML = ''; // force full reload
            fetchMessages(false); 
        });
        document.getElementById('chatOptionsDropdown').style.display = 'none';
    }

    function startSmartPolling() {
        if(masterPollInterval) clearInterval(masterPollInterval);
        masterPollInterval = setInterval(() => {
            if (!document.hidden) { loadSidebar(); if (activeConvId) { fetchMessages(false); } }
        }, 15000); 
    }

    function openGroupModal() { 
        document.getElementById('groupModal').style.display = 'flex'; selectedMembers.clear(); 
        document.getElementById('groupName').value = ''; document.getElementById('memberSearch').value = ''; searchForGroup(''); 
    }
    function closeGroupModal() { document.getElementById('groupModal').style.display = 'none'; }

    function searchForGroup(val) {
        let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val); 
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedMembers.has(u.id) ? 'background:var(--primary-light); border-color:var(--primary);' : 'background:var(--surface); border-color:var(--border-light);';
                let icon = selectedMembers.has(u.id) ? '<i class="ri-checkbox-circle-fill" style="color:var(--primary); font-size:1.2rem;"></i>' : '<i class="ri-checkbox-blank-circle-line" style="color:var(--text-muted); font-size:1.2rem;"></i>';
                html += `<div onclick="toggleMember(${u.id}, this)" style="padding:12px 16px; display:flex; align-items:center; gap:16px; cursor:pointer; border:1px solid; border-radius:var(--radius-sm); margin-bottom:8px; transition:0.2s; ${isSel}">
                            ${icon}
                            <img src="${u.profile_img}" loading="lazy" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <div style="font-weight:600; font-size:0.95rem;">${escapeHTML(u.display_name)}</div>
                        </div>`;
            });
            document.getElementById('groupUserList').innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--text-muted); font-weight: 500;">No users found</div>';
        });
    }

    function toggleMember(uid, el) {
        if(selectedMembers.has(uid)) {
            selectedMembers.delete(uid);
            el.style.background = 'var(--surface)'; el.style.borderColor = 'var(--border-light)';
            el.querySelector('i').className = 'ri-checkbox-blank-circle-line'; el.querySelector('i').style.color = 'var(--text-muted)';
        } else {
            selectedMembers.add(uid);
            el.style.background = 'var(--primary-light)'; el.style.borderColor = 'var(--primary)';
            el.querySelector('i').className = 'ri-checkbox-circle-fill'; el.querySelector('i').style.color = 'var(--primary)';
        }
    }

    function createGroup() {
        let name = document.getElementById('groupName').value.trim();
        if(!name || selectedMembers.size === 0) return Swal.fire('Wait', 'Group name and at least 1 member required.', 'warning');
        
        let fd = new FormData(); fd.append('action', 'create_group'); fd.append('group_name', name); fd.append('members', JSON.stringify(Array.from(selectedMembers)));
        fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if (data.status === 'success') { closeGroupModal(); loadConversation(data.conversation_id); }
        });
    }

    document.getElementById('userSearch').addEventListener('input', function(e) {
        let val = e.target.value.trim(); let results = document.getElementById('searchResults');
        results.style.display = val.length < 1 ? 'none' : 'block'; if (val.length < 1) return;
        
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val);
            fetch(window.location.href, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                let html = data.map(u => {
                    // Safe injection of values to bypass delay
                    let safeName = u.display_name.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    let safeImg = u.profile_img.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                    return `<div class="search-item" onclick="startChat(${u.id}, '${safeName}', '${safeImg}');"><img src="${u.profile_img}" loading="lazy" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:700; font-size:0.95rem;">${escapeHTML(u.display_name)}</div><div style="font-size:0.8rem;color:var(--text-muted);">${escapeHTML(u.role)}</div></div></div>`;
                }).join('');
                results.innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--text-muted); font-weight: 500;">No users found</div>';
            });
        }, 300);
    });

    // --- FIX: ABSOLUTELY INSTANT LOAD BY PASSING NAME & AVATAR ---
    function startChat(userId, name = null, avatar = null) {
        document.getElementById('searchResults').style.display = 'none'; 
        document.getElementById('userSearch').value = '';
        
        // Instantly switch UI tab
        switchMainTab('chat_view', document.querySelectorAll('.sidebar-secondary-teams .nav-icon')[0]); 
        
        document.getElementById('chatAreaEmpty').style.display = 'none';
        document.getElementById('chatAreaActive').style.display = 'flex';
        
        // --- 1 MILLISECOND INSTANT HEADER UPDATE ---
        if (name && avatar) {
            document.getElementById('headerName').innerText = name;
            let avatarEl = document.getElementById('headerAvatar');
            avatarEl.src = avatar;
            avatarEl.classList.remove('skeleton');
        } else {
            // Only fallback to skeleton if no name provided
            document.getElementById('headerName').innerHTML = '<div class="skeleton skeleton-text" style="width: 120px; margin-top: 5px;"></div>';
            let avatarEl = document.getElementById('headerAvatar');
            avatarEl.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
            avatarEl.classList.add('skeleton');
        }

        document.getElementById('typingIndicator').textContent = '';
        
        // Show skeleton layout only for the messages body so it looks completely natural
        document.getElementById('msgBox').innerHTML = `
            <div class="msg-wrapper incoming"><div class="skeleton skeleton-msg-in"></div></div>
            <div class="msg-wrapper outgoing"><div class="skeleton skeleton-msg-out"></div></div>
            <div class="msg-wrapper incoming"><div class="skeleton skeleton-msg-in" style="width: 180px;"></div></div>
        `;
        
        let fd = new FormData(); 
        fd.append('action', 'start_chat'); 
        fd.append('target_user_id', userId);
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { 
            if(data.id) { 
                activeConvId = data.id; 
                editingMsgId = null; 
                lastFetchedMsgId = 0; 
                isUserScrolling = false; 
                isGroupChat = false; 
                switchInnerTab('chat');
                fetchMessages(true); 
            } 
        })
        .catch(e => { 
            console.error("Start Chat Error:", e);
            document.getElementById('headerName').innerText = 'Error';
            document.getElementById('msgBox').innerHTML = '<div style="text-align:center; padding: 40px; color: #ef4444;">Could not start conversation. Please try again.</div>';
        });
    }

    function startTyping() {
        let fd = new FormData(); fd.append('action', 'start_typing'); fd.append('conversation_id', activeConvId); fetch(window.location.href, { method: 'POST', body: fd });
    }
    function stopTyping() {
        clearTimeout(typingTimer); if(!activeConvId) return;
        let fd = new FormData(); fd.append('action', 'stop_typing'); fd.append('conversation_id', activeConvId); fetch(window.location.href, { method: 'POST', body: fd });
    }

    function escapeHTML(str) { return str.replace(/[&<>'"]/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[tag])); }
    
    // Init Page
    loadSidebar(); startSmartPolling();
</script>
</body>
</html>