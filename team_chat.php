<?php
// team_chat.php

// 1. SESSION & DB
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$my_id = $_SESSION['user_id'];

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // 1. SEARCH USERS
    if ($action === 'search_users') {
        $term = "%" . $_POST['term'] . "%";
        $sql = "SELECT id, username, role FROM users WHERE (username LIKE ? OR role LIKE ?) AND id != ? LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $term, $term, $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        while($row = $res->fetch_assoc()) { $users[] = $row; }
        echo json_encode($users);
        exit;
    }

    // 2. CREATE GROUP
    if ($action === 'create_group') {
        $group_name = $_POST['group_name'];
        $members = json_decode($_POST['members'], true);
        
        if (empty($group_name) || empty($members)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']); exit;
        }

        $stmt = $conn->prepare("INSERT INTO chat_conversations (type, group_name, created_by) VALUES ('group', ?, ?)");
        $stmt->bind_param("si", $group_name, $my_id);
        $stmt->execute();
        $conv_id = $conn->insert_id;

        $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($conv_id, $my_id)");
        foreach ($members as $uid) {
            $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($conv_id, $uid)");
        }

        $sys_msg = "Group '$group_name' created.";
        $conn->query("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type) VALUES ($conv_id, $my_id, '$sys_msg', 'text')");

        echo json_encode(['status' => 'success', 'conversation_id' => $conv_id]);
        exit;
    }

    // 3. GET RECENT CHATS (Sidebar)
    if ($action === 'get_recent_chats') {
        $sql = "SELECT 
                    c.id as conversation_id,
                    c.type,
                    c.group_name,
                    COALESCE(u.username, 'Unknown User') as name, 
                    COALESCE(u.role, '') as role,
                    m.message as last_msg,
                    m.message_type,
                    m.created_at as time,
                    (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = c.id AND is_read = 0 AND sender_id != ?) as unread
                FROM chat_conversations c
                JOIN chat_participants cp ON c.id = cp.conversation_id
                LEFT JOIN chat_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id != ?
                LEFT JOIN users u ON cp2.user_id = u.id
                LEFT JOIN chat_messages m ON m.id = (
                    SELECT id FROM chat_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1
                )
                WHERE cp.user_id = ?
                GROUP BY c.id
                ORDER BY m.created_at DESC";
        
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
                $row['avatar'] = "https://ui-avatars.com/api/?name=".urlencode($row['name'])."&background=1b5a5a&color=fff";
            }
            
            if ($row['message_type'] == 'call') $row['last_msg'] = '📞 Call started';
            $row['time'] = $row['time'] ? date('h:i A', strtotime($row['time'])) : '';
            $chats[] = $row;
        }
        echo json_encode($chats);
        exit;
    }

    // 4. GET MESSAGES (Chat Area)
    if ($action === 'get_messages') {
        $conv_id = $_POST['conversation_id'];
        
        // Mark as read
        $upd = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?");
        $upd->bind_param("ii", $conv_id, $my_id);
        $upd->execute();

        // Get Messages (Limit 50)
        $sql = "SELECT * FROM (
                    SELECT m.*, u.username, u.role 
                    FROM chat_messages m 
                    JOIN users u ON m.sender_id = u.id 
                    WHERE m.conversation_id = ? 
                    ORDER BY m.created_at DESC LIMIT 50
                ) AS sub ORDER BY created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $conv_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $msgs = [];
        while($row = $res->fetch_assoc()) {
            $row['is_me'] = ($row['sender_id'] == $my_id);
            $row['time'] = date('h:i A', strtotime($row['created_at']));
            $msgs[] = $row;
        }

        // Get Conversation Info
        $info_sql = "SELECT * FROM chat_conversations WHERE id = ?";
        $i_stmt = $conn->prepare($info_sql);
        $i_stmt->bind_param("i", $conv_id);
        $i_stmt->execute();
        $conv_info = $i_stmt->get_result()->fetch_assoc();

        $partner = null;
        if ($conv_info['type'] == 'direct') {
            $p_sql = "SELECT u.username, u.role, u.email FROM chat_participants cp 
                      JOIN users u ON cp.user_id = u.id 
                      WHERE cp.conversation_id = ? AND cp.user_id != ? LIMIT 1";
            $p_stmt = $conn->prepare($p_sql);
            $p_stmt->bind_param("ii", $conv_id, $my_id);
            $p_stmt->execute();
            $res = $p_stmt->get_result();
            if($res->num_rows > 0) {
                $partner = $res->fetch_assoc();
            } else {
                // Fallback if user deleted or self-chat
                $partner = ['username' => 'Unknown User', 'role' => '', 'email' => ''];
            }
        } else {
            $partner = ['username' => $conv_info['group_name'], 'role' => 'Group Chat', 'is_group' => true];
        }

        echo json_encode(['messages' => $msgs, 'info' => $partner, 'conv_type' => $conv_info['type']]);
        exit;
    }

    // 5. SEND MESSAGE
    if ($action === 'send_message') {
        $conv_id = $_POST['conversation_id'];
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
        $target_user_id = $_POST['target_user_id'];
        
        $sql = "SELECT c.id FROM chat_conversations c
                JOIN chat_participants cp1 ON c.id = cp1.conversation_id
                JOIN chat_participants cp2 ON c.id = cp2.conversation_id
                WHERE c.type = 'direct' AND cp1.user_id = ? AND cp2.user_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $my_id, $target_user_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            echo json_encode(['id' => $row['id']]);
        } else {
            $conn->query("INSERT INTO chat_conversations (type) VALUES ('direct')");
            $new_id = $conn->insert_id;
            $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($new_id, $my_id)");
            $conn->query("INSERT INTO chat_participants (conversation_id, user_id) VALUES ($new_id, $target_user_id)");
            echo json_encode(['id' => $new_id]);
        }
        exit;
    }

    // 7. CLEAR CHAT
    if ($action === 'clear_chat') {
        $conv_id = $_POST['conversation_id'];
        $stmt = $conn->prepare("DELETE FROM chat_messages WHERE conversation_id = ? AND sender_id = ?");
        $stmt->bind_param("ii", $conv_id, $my_id);
        $stmt->execute();
        echo json_encode(['status' => 'cleared']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeamChat</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        :root { 
            --primary-color:  #1b5a5a;
            --bg-body: #f8fafc; 
            --text-main: #111827; 
            --text-secondary: #6b7280; 
            --border-color: #E5E7EB; 
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
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        .app-container { flex: 1; display:flex; height: 0; min-height: 0; }

        /* SIDEBAR */
        .sidebar { width: 320px; background: #fff; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content:space-between; align-items:center; }
        .search-box { position: relative; padding: 10px 20px; border-bottom: 1px solid #f0f0f0; }
        .search-box input { width: 100%; padding: 10px 10px 10px 35px; border: 1px solid var(--border-color); border-radius: 8px; background: #f9fafb; outline: none; }
        .search-box i { position: absolute; left: 30px; top: 22px; color: var(--text-secondary); }
        
        .chat-list { flex: 1; overflow-y: auto; }
        .chat-item { display: flex; align-items: center; padding: 12px 20px; cursor: pointer; transition: 0.2s; border-bottom: 1px solid #f3f4f6; }
        .chat-item:hover { background: #f9fafb; }
        .chat-item.active { background: #e0f2f1; border-right: 3px solid var(--primary-color); }
        .avatar { width: 45px; height: 45px; border-radius: 50%; margin-right: 12px; object-fit: cover; }
        
        #searchResults { position: absolute; top: 60px; left: 20px; width: 88%; background: white; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); z-index: 50; display: none; }
        .search-item { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; font-size: 0.9rem; }
        .search-item:hover { background: #f3f4f6; }

        /* CHAT AREA */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #f0f2f5; position: relative; }
        .chat-header { height: 65px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; padding: 0 20px; justify-content: space-between; }
        
        .header-actions { position: relative; display: flex; gap: 15px; }
        .menu-dropdown { position: absolute; top: 40px; right: 0; background: white; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 150px; display: none; z-index: 100; }
        .menu-dropdown.show { display: block; }
        .menu-item { padding: 10px 15px; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color: var(--text-main); }
        .menu-item:hover { background: #f9fafb; }
        .menu-item.danger { color: #dc2626; }

        .messages-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; scroll-behavior: smooth; }
        .msg { max-width: 70%; padding: 10px 15px; border-radius: 15px; position: relative; font-size: 0.95rem; line-height: 1.4; word-wrap: break-word; }
        .msg.incoming { align-self: flex-start; background: var(--incoming-bg); border: 1px solid var(--border-color); border-bottom-left-radius: 2px; }
        .msg.outgoing { align-self: flex-end; background: var(--outgoing-bg); color: var(--outgoing-text); border-bottom-right-radius: 2px; }
        .msg-time { font-size: 0.7rem; margin-top: 4px; opacity: 0.7; text-align: right; }
        .msg.call-msg { background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; align-self: center; width: 100%; max-width: 300px; text-align: center; }
        .join-btn { display: inline-block; background: #4f46e5; color: white; padding: 6px 12px; border-radius: 6px; margin-top: 5px; text-decoration: none; font-size: 0.85rem; }

        .input-area { padding: 15px; background: white; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .input-area input { flex: 1; padding: 12px; border: 1px solid var(--border-color); border-radius: 25px; outline: none; }
        .btn-icon { font-size: 1.4rem; color: var(--text-secondary); cursor: pointer; transition: 0.2s; }
        .btn-icon:hover { color: var(--primary-color); }
        .btn-send { background: var(--primary-color); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; }

        /* MODALS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal { background: white; width: 400px; border-radius: 12px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal h3 { margin-bottom: 15px; font-weight: 700; color: var(--text-main); }
        .modal input { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 15px; }
        .user-select-list { max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 6px; margin-bottom: 15px; }
        .user-option { padding: 8px 10px; display: flex; align-items: center; gap: 10px; cursor: pointer; border-bottom: 1px solid #f9f9f9; }
        .user-option.selected { background: #ccfbf1; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { padding: 8px 16px; border: 1px solid var(--border-color); background: white; border-radius: 6px; cursor: pointer; }
        .btn-create { padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

<?php include('sidebars.php'); ?>

<main id="mainContent">
    <?php include('header.php'); ?>
    
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2 style="font-weight:700; color:var(--primary-color);">TeamChat</h2>
                <button onclick="openGroupModal()" title="Create Group" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-main);"><i class="ri-add-circle-line"></i></button>
            </div>
            <div class="search-box">
                <i class="ri-search-line"></i>
                <input type="text" id="userSearch" placeholder="Search people...">
                <div id="searchResults"></div>
            </div>
            <div class="chat-list" id="chatList"></div>
        </aside>

        <section class="chat-area" id="chatArea">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#aaa;">
                <i class="ri-chat-smile-3-line" style="font-size:4rem; opacity:0.3;"></i>
                <p>Select a chat to start messaging</p>
            </div>
        </section>
    </div>
</main>

<div class="modal-overlay" id="groupModal">
    <div class="modal">
        <h3>Create New Group</h3>
        <input type="text" id="groupName" placeholder="Group Name">
        <p style="font-size:0.85rem; color:#666; margin-bottom:5px;">Add Members:</p>
        <input type="text" id="memberSearch" placeholder="Search to add..." oninput="searchForGroup(this.value)">
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
    let lastMsgCount = 0; 
    let selectedMembers = new Set();

    // 1. SEARCH USERS
    document.getElementById('userSearch').addEventListener('input', function(e) {
        let val = e.target.value;
        if(val.length < 2) { document.getElementById('searchResults').style.display = 'none'; return; }

        let fd = new FormData();
        fd.append('action', 'search_users');
        fd.append('term', val);

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(u => {
                html += `<div class="search-item" onclick="startChat(${u.id})">
                            <div style="font-weight:600">${u.username}</div>
                            <div style="font-size:0.8rem; color:#666">${u.role}</div>
                         </div>`;
            });
            document.getElementById('searchResults').innerHTML = html;
            document.getElementById('searchResults').style.display = html ? 'block' : 'none';
        });
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
            loadConversation(data.id, true);
            loadSidebar(); 
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
                let unread = c.unread > 0 ? `<span class="unread-count">${c.unread}</span>` : '';
                // Fallback for name logic
                let displayName = c.name ? c.name : 'Unknown';
                
                // IMPORTANT: Added TRUE to loadConversation to force refresh
                html += `
                <div class="chat-item ${active}" onclick="loadConversation(${c.conversation_id}, true)">
                    <img src="${c.avatar}" class="avatar">
                    <div class="chat-info">
                        <div style="display:flex; justify-content:space-between">
                            <div class="chat-name">${displayName}</div>
                            <div style="font-size:0.7rem; color:#888">${c.time}</div>
                        </div>
                        <div class="chat-meta">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; color:#666;">
                                ${c.last_msg ? c.last_msg : 'Start a conversation'}
                            </span>
                            ${unread}
                        </div>
                    </div>
                </div>`;
            });
            document.getElementById('chatList').innerHTML = html;
        });
    }

    // 4. LOAD CONVERSATION
    function loadConversation(convId, force = false) {
        if(activeConvId === convId && !force) return;
        activeConvId = convId;
        lastMsgCount = 0; 
        
        // --- FORCE UI REFRESH (Fixes "Not Opening" Issue) ---
        if(force) {
            document.getElementById('chatArea').innerHTML = `
                <div class="chat-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div class="avatar" style="width:35px; height:35px; background:#f0f2f5;"></div>
                        <div><h3 style="font-size:1.1rem; color:#333;">Loading...</h3></div>
                    </div>
                </div>
                <div class="messages-box" id="msgBox">
                    <div style="display:flex; justify-content:center; padding-top:20px; color:#999;">Loading messages...</div>
                </div>
            `;
        }

        fetchMessages(true);
        loadSidebar(); 
        
        if(pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(() => fetchMessages(false), 3000);
    }

    // 5. FETCH MESSAGES
    function fetchMessages(updateHeader = false) {
        if(!activeConvId) return;
        let fd = new FormData();
        fd.append('action', 'get_messages');
        fd.append('conversation_id', activeConvId);

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            let msgs = data.messages;
            let info = data.info;

            // Only update DOM if header needs update OR we have new messages
            if(updateHeader && info) {
                let headerHTML = `
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(info.username)}&background=${info.is_group ? '6366f1' : 'random'}" class="avatar" style="width:35px; height:35px;">
                        <div>
                            <h3 style="font-size:1.1rem; color:#333; margin:0; line-height:1.2;">${info.username}</h3>
                            <div style="font-size:0.75rem; color:#888;">${info.role}</div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <i class="ri-phone-line btn-icon" onclick="startCall('audio')" title="Voice Call"></i>
                        <i class="ri-vidicon-line btn-icon" onclick="startCall('video')" title="Video Call"></i>
                        <i class="ri-more-2-fill btn-icon" onclick="toggleMenu()"></i>
                        <div class="menu-dropdown" id="chatMenu">
                            <div class="menu-item"><i class="ri-user-line"></i> View Profile</div>
                            <div class="menu-item danger" onclick="clearChat()"><i class="ri-delete-bin-line"></i> Clear Chat</div>
                        </div>
                    </div>
                `;
                let inputHTML = `
                <div class="messages-box" id="msgBox"></div>
                <div class="input-area">
                    <label for="fileUpload"><i class="ri-attachment-line btn-icon"></i></label>
                    <input type="file" id="fileUpload" hidden onchange="sendMessage()">
                    <input type="text" id="msgInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') sendMessage()">
                    <button class="btn-send" onclick="sendMessage()"><i class="ri-send-plane-fill"></i></button>
                </div>`;
                
                document.getElementById('chatArea').innerHTML = `<div class="chat-header">${headerHTML}</div>` + inputHTML;
            }

            // Msg Refresh
            if(msgs.length === lastMsgCount && !updateHeader) return;
            lastMsgCount = msgs.length;

            let html = '';
            if(msgs.length == 0) html = '<div style="text-align:center; padding:20px; color:#ccc;">No messages yet. Start chatting!</div>';
            
            msgs.forEach(m => {
                let cls = m.is_me ? 'outgoing' : 'incoming';
                let content = m.message;
                
                if(m.message_type === 'call') {
                    html += `<div class="msg call-msg">
                                <i class="ri-phone-fill"></i> <strong>Incoming Call</strong><br>
                                <a href="${m.message}" target="_blank" class="join-btn">Join Meeting</a>
                                <div class="msg-time">${m.time}</div>
                             </div>`;
                } else {
                    if(m.message_type === 'image') content = `<img src="${m.attachment_path}">`;
                    else if(m.message_type === 'file') content = `<a href="${m.attachment_path}" target="_blank" style="text-decoration:underline; color:inherit"><i class="ri-file-text-fill"></i> ${m.message}</a>`;
                    
                    let senderName = (!m.is_me && info.is_group) ? `<div style="font-size:0.7rem; color:orange; font-weight:bold; margin-bottom:2px;">${m.username}</div>` : '';

                    html += `<div class="msg ${cls}">
                                ${senderName}
                                ${content}
                                <div class="msg-time">${m.time}</div>
                             </div>`;
                }
            });
            
            let box = document.getElementById('msgBox');
            if(box) {
                box.innerHTML = html;
                box.scrollTop = box.scrollHeight;
            }
        });
    }

    // 6. SEND MESSAGE
    function sendMessage(type = 'text', content = null) {
        let input = document.getElementById('msgInput');
        let fileInput = document.getElementById('fileUpload');
        let txt = content ? content : (input ? input.value.trim() : '');
        let file = fileInput ? fileInput.files[0] : null;

        if(!txt && !file) return;

        let fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('conversation_id', activeConvId);
        fd.append('type', type);
        
        if(type === 'text') fd.append('message', txt);
        else fd.append('message', content); 

        if(file) fd.append('file', file);

        if(input) input.value = '';
        if(fileInput) fileInput.value = '';

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(() => {
            fetchMessages(false);
            loadSidebar();
        });
    }

    // CALL & MENU
    function startCall(type) {
        let meetId = 'SmartHR-' + Math.random().toString(36).substring(7);
        let link = `https://meet.jit.si/${meetId}`;
        sendMessage('call', link);
        window.open(link, '_blank');
    }

    function toggleMenu() { document.getElementById('chatMenu').classList.toggle('show'); }
    
    function clearChat() {
        if(!confirm('Clear chat?')) return;
        let fd = new FormData();
        fd.append('action', 'clear_chat');
        fd.append('conversation_id', activeConvId);
        fetch('team_chat.php', { method: 'POST', body: fd }).then(() => {
            document.getElementById('msgBox').innerHTML = '';
            document.getElementById('chatMenu').classList.remove('show');
        });
    }

    // GROUP
    function openGroupModal() { document.getElementById('groupModal').style.display = 'flex'; selectedMembers.clear(); updateGroupList(); }
    function closeGroupModal() { document.getElementById('groupModal').style.display = 'none'; }

    function searchForGroup(val) {
        if(val.length < 1) return;
        let fd = new FormData();
        fd.append('action', 'search_users');
        fd.append('term', val);
        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedMembers.has(u.id) ? 'selected' : '';
                html += `<div class="user-option ${isSel}" onclick="toggleMember(${u.id}, this)">
                            <div style="font-weight:600">${u.username}</div>
                         </div>`;
            });
            document.getElementById('groupUserList').innerHTML = html;
        });
    }

    function toggleMember(uid, el) {
        if(selectedMembers.has(uid)) { selectedMembers.delete(uid); el.classList.remove('selected'); }
        else { selectedMembers.add(uid); el.classList.add('selected'); }
    }

    function updateGroupList() { document.getElementById('groupUserList').innerHTML = ''; }

    function createGroup() {
        let name = document.getElementById('groupName').value;
        if(!name || selectedMembers.size === 0) { alert('Enter name and select members'); return; }

        let fd = new FormData();
        fd.append('action', 'create_group');
        fd.append('group_name', name);
        fd.append('members', JSON.stringify(Array.from(selectedMembers)));

        fetch('team_chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            closeGroupModal();
            loadConversation(data.conversation_id, true);
            loadSidebar();
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.header-actions')) {
            let menu = document.getElementById('chatMenu');
            if(menu) menu.classList.remove('show');
        }
    });

    loadSidebar();
</script>

</body>
</html>