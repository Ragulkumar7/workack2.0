<?php
// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Check Login
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeamChat - Messaging Interface</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
:root { 
    --primary-color: #d13d3dff; 
    --primary-hover: #cf5f5fff; 
    --bg-body: #f0f2f5fe; 
    --bg-white: #fffffff6; 
    --text-main: #111827; 
    --text-secondary: #6b7280; 
    --border-color: #E5E7EB; 
    --incoming-msg-bg: #ffffff; 
    --outgoing-msg-bg: #b19cd9; 
    --outgoing-msg-text: #ffffff; 
    --danger-color: #EF4444; 
    --success-color: #10B981; 
    --warning-color: #F59E0B; 
    --sidebar-width: 380px; 
    --header-height: 80px; 
    --input-area-height: 90px; 
}

* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }

body { 
    background-color:var(--bg-body); 
    height:100vh; 
    width:100vw; 
    overflow:hidden; 
}

/* --- GLOBAL SIDEBAR INTEGRATION --- */
#mainContent { 
    margin-left: 95px; /* Primary Sidebar Width */
    height: 100vh;
    width: calc(100% - 95px);
    transition: margin-left 0.3s ease, width 0.3s ease;
    position: relative;
}
#mainContent.main-shifted {
    margin-left: 315px; /* 95px + 220px */
    width: calc(100% - 315px);
}
/* ---------------------------------- */

.app-container { 
    width:100%; 
    height:100%; 
    max-width:1920px; 
    display:flex; 
    gap: 20px; 
    padding: 20px;
    background:transparent;
    box-shadow:none;
    position:relative; 
}

.sidebar { 
    width:var(--sidebar-width); 
    border:1px solid var(--border-color);
    display:flex; 
    flex-direction:column; 
    background-color:var(--bg-white); 
    border-radius: 7px; 
    flex-shrink:0; 
    height:100%; 
    z-index:20; 
    transition:transform 0.3s ease; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.sidebar-header { 
    height:auto; 
    min-height:130px; 
    padding:24px; 
    display:flex; 
    flex-direction:column; 
    justify-content:flex-start; 
    border-bottom:1px solid var(--border-color); 
    background-color:var(--bg-white); 
    flex-shrink:0; 
    border-radius: 7px 7px 7px 7px;
}

.sidebar-title { font-size:1.5rem; font-weight:800; color:var(--text-main); margin-bottom:20px; letter-spacing:-0.02em; }

.search-box { position:relative; width:100%; }
.search-box input { width:100%; padding:12px 12px 12px 40px; border-radius:12px; border:1px solid var(--border-color); background-color:#F9FAFB; font-size:0.95rem; outline:none; transition:all 0.2s; height:44px; }
.search-box input:focus { border-color:var(--primary-color); background-color:var(--bg-white); box-shadow:0 0 0 3px rgba(88,101,242,0.1); }
.search-box i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:1.1rem; }

.chat-list { flex:1; overflow-y:auto; padding:8px 0; background-color:var(--bg-white); }

.chat-item { display:flex; align-items:center; padding:16px 24px; cursor:pointer; transition:background-color 0.2s; position:relative; margin:4px 12px; border-radius:12px; }
.chat-item:hover { background-color:#F3F4F6; }
.chat-item.active { background-color:#EEF2FF; }

.avatar-container { position:relative; margin-right:16px; flex-shrink:0; }
.avatar { width:52px; height:52px; border-radius:50%; object-fit:cover; border:2px solid white; box-shadow:0 2px 5px rgba(0,0,0,0.05); }
.status-dot { position:absolute; bottom:2px; right:2px; width:14px; height:14px; border-radius:50%; border:3px solid var(--bg-white); }
.status-online { background-color:var(--success-color); }
.status-offline { background-color:var(--text-secondary); }
.status-busy { background-color:var(--danger-color); }

.chat-info { flex:1; min-width:0; }
.chat-header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
.chat-name { font-weight:700; color:var(--text-main); font-size:1rem; }
.chat-meta { font-size:0.75rem; color:var(--text-secondary); display:flex; align-items:center; gap:4px; white-space:nowrap; }
.chat-preview-row { display:flex; justify-content:space-between; align-items:center; }
.chat-last-msg { color:var(--text-secondary); font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px; font-weight:400; }
.typing-indicator { color:var(--primary-color); font-style:italic; font-size:0.8rem; font-weight:600; }
.unread-badge { background-color:var(--primary-color); color:white; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:12px; min-width:24px; text-align:center; box-shadow:0 2px 4px rgba(88,101,242,0.3); }
.missed-call-icon { color:var(--danger-color); }
.doc-icon { color:var(--text-secondary); }

.chat-area { 
    flex:1; 
    display:flex; 
    flex-direction:column; 
    background-color:#F8FAFC; 
    position:relative; 
    z-index:10; 
    height:100%; 
    min-width:0; 
    border:1px solid var(--border-color);
    border-radius: 7px; 
    overflow: hidden; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.chat-top-bar { height:var(--header-height); background-color:var(--bg-white); border-bottom:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; padding:0 32px; flex-shrink:0; box-shadow:0 1px 2px rgba(0,0,0,0.02); }

.user-profile-info { display:flex; align-items:center; }
.user-avatar-large { width:44px; height:44px; border-radius:50%; margin-right:16px; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 5px rgba(0,0,0,0.05); }
.user-details h3 { font-size:1.15rem; font-weight:700; color:var(--text-main); line-height:1.2; }
.user-details span { font-size:0.85rem; color:var(--success-color); font-weight:500; display:flex; align-items:center; gap:4px; }
.user-details span::before { content:''; width:8px; height:8px; background-color:currentColor; border-radius:50%; display:inline-block; }

.action-icons { display:flex; gap:28px; }
.action-icon { font-size:1.35rem; color:var(--text-secondary); cursor:pointer; transition:color 0.2s, transform 0.1s; }
.action-icon:hover { color:var(--primary-color); transform:translateY(-2px); }

.messages-wrapper { flex:1; padding:32px; overflow-y:auto; display:flex; flex-direction:column; gap:24px; scroll-behavior:smooth; }

.message-group { display:flex; align-items:flex-end; gap:12px; max-width:70%; }
.message-group.incoming { align-self:flex-start; }
.message-group.outgoing { align-self:flex-end; flex-direction:row-reverse; }
.msg-avatar { width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.message-content { display:flex; flex-direction:column; gap:6px; }
.message-bubble { padding:14px 18px; border-radius:20px; font-size:0.95rem; line-height:1.5; position:relative; box-shadow:0 1px 2px rgba(0,0,0,0.05); word-wrap:break-word; }
.incoming .message-bubble { background-color:var(--bg-white); color:var(--text-main); border-bottom-left-radius:4px; border:1px solid var(--border-color); }
.outgoing .message-bubble { background-color:var(--primary-color); color:var(--outgoing-msg-text); border-bottom-right-radius:4px; }
.message-time { font-size:0.7rem; margin-top:4px; opacity:0.8; display:flex; align-items:center; gap:4px; }
.incoming .message-time { color:var(--text-secondary); margin-left:4px; }
.outgoing .message-time { color:rgba(255,255,255,0.8); justify-content:flex-end; margin-right:4px; }

.attachment-img { max-width:100%; max-height:300px; border-radius:12px; margin-top:4px; display:block; cursor:pointer; border:1px solid rgba(0,0,0,0.1); }
.file-attachment { display:flex; align-items:center; gap:12px; padding:4px 0; min-width:200px; }
.file-icon-box { width:44px; height:44px; background:rgba(0,0,0,0.05); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; }
.outgoing .file-icon-box { background:rgba(255,255,255,0.2); color:white; }
.file-details { display:flex; flex-direction:column; }
.file-name { font-weight:600; font-size:0.95rem; line-height:1.2; }
.file-size { font-size:0.75rem; opacity:0.8; margin-top:2px; }

.input-wrapper { height:var(--input-area-height); background-color:var(--bg-white); border-top:1px solid var(--border-color); padding:0 32px; display:flex; align-items:center; gap:16px; flex-shrink:0; z-index:15; }
.attach-btn { color:var(--text-secondary); font-size:1.6rem; cursor:pointer; transition:color 0.2s, transform 0.1s; display:flex; align-items:center; }
.attach-btn:hover { color:var(--primary-color); transform:scale(1.1); }
.message-input-container { flex:1; position:relative; }
.message-input { width:100%; padding:14px 20px; background-color:#F3F4F6; border:none; border-radius:30px; font-size:0.95rem; outline:none; resize:none; height:52px; line-height:24px; font-family:inherit; transition:background 0.2s, box-shadow 0.2s; }
.message-input:focus { background-color:#ffffff; box-shadow:0 0 0 2px var(--primary-color); }
.send-btn { width:52px; height:52px; border-radius:50%; background-color:var(--primary-color); color:white; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background-color 0.2s, transform 0.1s; font-size:1.3rem; box-shadow:0 4px 6px rgba(88,101,242,0.25); }
.send-btn:hover { background-color:var(--primary-hover); transform:translateY(-2px); }
.send-btn:active { transform:scale(0.95); }

::-webkit-scrollbar { width:8px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:#D1D5DB; border-radius:4px; }
::-webkit-scrollbar-thumb:hover { background:#9CA3AF; }

/* Video Call Overlay */
.video-call-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background-color:#000; z-index:2000; display:none; flex-direction:column; justify-content:space-between; }
.video-call-overlay.active { display:flex; }

.video-container { position:relative; flex:1; width:100%; overflow:hidden; background-color:#202124; display:flex; align-items:center; justify-content:center; }
.remote-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:0; text-align:center; }
#remoteInitials { font-size:8rem; color:#ffffff; font-weight:700; letter-spacing:4px; text-transform:uppercase; line-height: 1; }
.call-avatar-text { color: rgba(255,255,255,0.7); font-size: 1.5rem; margin-top: 16px; }

#remoteVideo { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:1; background:transparent; display:none; }
.video-call-overlay.is-video #remoteVideo { display:block; }

.local-video-wrapper { position:absolute; bottom:20px; right:20px; width:200px; height:150px; background:#333; border-radius:16px; overflow:hidden; box-shadow:0 8px 20px rgba(0,0,0,0.5); border:2px solid rgba(255,255,255,0.2); z-index:2001; transition:all 0.3s ease; display:none; }
.video-call-overlay.is-video .local-video-wrapper { display:block; }

.local-video-wrapper:hover { transform:scale(1.05); }
#localVideo { width:100%; height:100%; object-fit:cover; transform:scaleX(-1); }

.call-header { padding:24px 32px; color:white; display:flex; justify-content:space-between; align-items:center; background:linear-gradient(to bottom, rgba(0,0,0,0.7), transparent); position:absolute; top:0; left:0; width:100%; z-index:2002; }
.call-info h2 { font-size:1.4rem; font-weight:600; letter-spacing:0.5px; }
.call-info p { font-size:0.95rem; opacity:0.9; font-weight:500; }

.call-controls { height:100px; background:rgba(0,0,0,0.8); backdrop-filter:blur(10px); display:flex; justify-content:center; align-items:center; gap:28px; padding-bottom:20px; z-index:2002; }
.control-btn { width:60px; height:60px; border-radius:50%; border:none; background:rgba(255,255,255,0.15); color:white; font-size:1.5rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
.control-btn:hover { background:rgba(255,255,255,0.25); transform:scale(1.1); }
.control-btn.end-call { background-color:var(--danger-color); box-shadow:0 4px 12px rgba(239,68,68,0.4); }
.control-btn.end-call:hover { background-color:#DC2626; }
.control-btn.active { background-color:white; color:black; }

.toast { position:absolute; top:100px; left:50%; transform:translateX(-50%); background-color:rgba(0,0,0,0.8); color:white; padding:12px 24px; border-radius:50px; font-size:0.95rem; opacity:0; transition:opacity 0.3s; pointer-events:none; z-index:2003; backdrop-filter:blur(4px); box-shadow:0 4px 12px rgba(0,0,0,0.2); }
.toast.show { opacity:1; }

/* Modal */
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6); z-index:3000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.modal-overlay.active { display:flex; }
.modal-content { background-color:var(--bg-white); width:90%; max-width:480px; border-radius:20px; box-shadow:0 20px 50px rgba(0,0,0,0.2); overflow:hidden; display:flex; flex-direction:column; max-height:85vh; animation:modalPop 0.3s cubic-bezier(0.175,0.885,0.32,1.275); }
@keyframes modalPop { from { opacity:0; transform:scale(0.9); } to { opacity:1; transform:scale(1); } }
.modal-header { padding:24px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; background-color:#F9FAFB; }
.modal-header h3 { font-size:1.25rem; color:var(--text-main); font-weight:700; }
.close-modal-btn { background:none; border:none; color:var(--text-secondary); font-size:1.5rem; cursor:pointer; padding:4px; border-radius:50%; transition:background 0.2s; }
.close-modal-btn:hover { background-color:#E5E7EB; color:var(--text-main); }
.modal-body { padding:20px 24px; overflow-y:auto; }
.conf-search-wrapper { margin-bottom:20px; }
.conf-contact-list { display:flex; flex-direction:column; gap:10px; }
.conf-contact-item { display:flex; align-items:center; padding:12px; border-radius:12px; cursor:pointer; transition:all 0.2s; border:2px solid transparent; }
.conf-contact-item:hover { background-color:#F3F4F6; }
.conf-contact-item.selected { background-color:#EEF2FF; border-color:var(--primary-color); }
.conf-avatar { width:44px; height:44px; border-radius:50%; object-fit:cover; margin-right:16px; }
.conf-info { flex:1; }
.conf-name { font-weight:600; font-size:1rem; color:var(--text-main); }
.conf-status { font-size:0.85rem; color:var(--text-secondary); margin-top:2px; }
.conf-checkbox { width:22px; height:22px; accent-color:var(--primary-color); cursor:pointer; }
.modal-footer { padding:20px 24px; border-top:1px solid var(--border-color); display:flex; justify-content:flex-end; background-color:#F9FAFB; gap:12px; }
.btn-primary { background-color:var(--primary-color); color:white; border:none; padding:12px 24px; border-radius:10px; font-weight:600; cursor:pointer; transition:background-color 0.2s; font-size:0.95rem; }
.btn-primary:hover { background-color:var(--primary-hover); }
.btn-secondary { background-color:white; color:var(--text-secondary); border:1px solid var(--border-color); padding:12px 24px; border-radius:10px; font-weight:600; cursor:pointer; transition:background 0.2s, color 0.2s; font-size:0.95rem; }
.btn-secondary:hover { background-color:#F3F4F6; color:var(--text-main); }

@media (max-width: 768px) {
    .app-container { position:relative; padding: 0; gap: 0; }
    .sidebar { position:absolute; z-index:50; height:100%; width:100%; transform:translateX(0); border-radius: 0; }
    .sidebar.hidden { transform:translateX(-100%); }
    .chat-area { width:100%; border-radius: 0; }
    .back-btn { display:flex !important; margin-right:12px; cursor:pointer; font-size:1.5rem; color:var(--text-main); background:#F3F4F6; width:36px; height:36px; border-radius:50%; align-items:center; justify-content:center; }
    .local-video-wrapper { width:120px; height:90px; bottom:120px; right:20px; }
    
    /* Mobile Adjustments for Main Content */
    #mainContent { margin-left: 0; width: 100%; padding: 0; }
    #mainContent.main-shifted { margin-left: 0; width: 100%; }
}

@media (min-width: 769px) {
    .back-btn { display:none !important; }
}
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>
    <?php include 'header.php'; ?>

    <main id="mainContent">
        <div class="app-container">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-title">Chats</div>
                    <div class="search-box">
                        <i class="ri-search-line"></i>
                        <input type="text" placeholder="Search chats..." id="searchInput">
                    </div>
                </div>
                <div class="chat-list" id="chatList">
                    </div>
            </aside>

            <main class="chat-area">
                <header class="chat-top-bar">
                    <div class="user-profile-info">
                        <i class="ri-arrow-left-line back-btn" id="backBtn"></i>
                        <img src="" alt="User Avatar" class="user-avatar-large" id="currentChatAvatar">
                        <div class="user-details">
                            <h3 id="currentChatName">Select a chat</h3>
                            <span id="currentChatStatus">Offline</span>
                        </div>
                    </div>
                    <div class="action-icons">
                        <i class="ri-phone-line action-icon" id="openVoiceCallBtn" title="Start Voice Call"></i>
                        <i class="ri-vidicon-line action-icon" id="startVideoCallBtn" title="Start Video Call"></i>
                        <i class="ri-more-2-fill action-icon"></i>
                    </div>
                </header>

                <div class="messages-wrapper" id="messagesWrapper">
                    </div>

                <footer class="input-wrapper">
                    <input type="file" id="fileInput" hidden>
                    
                    <i class="ri-attachment-line attach-btn" id="attachBtn" title="Share File"></i>
                    <div class="message-input-container">
                        <input type="text" class="message-input" id="messageInput" placeholder="Type a message..." autocomplete="off">
                    </div>
                    <button class="send-btn" id="sendBtn">
                        <i class="ri-send-plane-fill"></i>
                    </button>
                </footer>
            </main>
            
            <div class="video-call-overlay" id="videoCallOverlay">
                <div class="call-header">
                    <div class="call-info">
                        <h2 id="callUserName">User Name</h2>
                        <p id="callTimer">00:00</p>
                    </div>
                    <div style="font-size: 1.5rem; cursor: pointer; background: rgba(255,255,255,0.1); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content:center;" id="minimizeCallBtn">
                        <i class="ri-pushpin-line"></i>
                    </div>
                </div>

                <div class="video-container">
                    <div class="remote-placeholder" id="remotePlaceholder">
                        <div id="remoteInitials">AL</div>
                        <div class="call-avatar-text" id="callAvatarText">Voice Call</div>
                    </div>
                    
                    <video id="remoteVideo" autoplay playsinline></video>
                    
                    <div class="local-video-wrapper">
                        <video id="localVideo" autoplay muted playsinline></video>
                    </div>
                    
                    <div class="toast" id="toastMsg">Link copied to clipboard</div>
                </div>

                <div class="call-controls">
                    <button class="control-btn" id="toggleMicBtn" title="Toggle Microphone">
                        <i class="ri-mic-line"></i>
                    </button>
                    <button class="control-btn" id="toggleCamBtn" title="Toggle Camera">
                        <i class="ri-camera-line"></i>
                    </button>
                    <button class="control-btn" id="shareLinkBtn" title="Share Meeting Link">
                        <i class="ri-share-forward-line"></i>
                    </button>
                    <button class="control-btn end-call" id="endCallBtn" title="End Call">
                        <i class="ri-phone-end-line"></i>
                    </button>
                </div>
            </div>

            <div class="modal-overlay" id="confModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modalTitle">Start Call</h3>
                        <button class="close-modal-btn" id="closeConfModal"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="search-box conf-search-wrapper">
                            <i class="ri-search-line"></i>
                            <input type="text" placeholder="Search users to add..." id="confSearchInput">
                        </div>
                        <div class="conf-contact-list" id="confContactList">
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-secondary" id="cancelConfBtn">Cancel</button>
                        <button class="btn-primary" id="startConfBtn">Start Call</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // --- Data Model ---
        const contacts = [
            {
                id: 1,
                name: "Anthony Lewis",
                avatar: "https://picsum.photos/seed/anthony/200/200.jpg",
                status: "online",
                lastMsgTime: "10:42 AM",
                typing: true,
                messages: [
                    {
                        id: 1,
                        text: "That sounds great! Are there any specific requirements for tracking our hours when working remotely?",
                        type: "incoming",
                        time: "10:42 AM"
                    },
                    {
                        id: 2,
                        text: "Yes, we'll be using a time-tracking tool to log hours automatically. I'll send you the link shortly.",
                        type: "outgoing",
                        time: "10:43 AM",
                        status: "read"
                    }
                ]
            },
            {
                id: 2,
                name: "Elliot Murray",
                avatar: "https://picsum.photos/seed/elliot/200/200.jpg",
                status: "offline",
                lastMsgTime: "Yesterday",
                previewType: "document", 
                lastMsg: "Project_Specs.pdf",
                messages: [
                    { id: 1, text: "Hey, did you get the file?", type: "incoming", time: "Yesterday" },
                    { id: 2, text: "Checking it now.", type: "outgoing", time: "Yesterday" }
                ]
            },
            {
                id: 3,
                name: "Stephan Peralt",
                avatar: "https://picsum.photos/seed/stephan/200/200.jpg",
                status: "busy",
                lastMsgTime: "Tue",
                previewType: "missed_video",
                lastMsg: "Missed video call",
                messages: [
                    { id: 1, text: "Can we hop on a quick call?", type: "incoming", time: "Tue" }
                ]
            },
            {
                id: 4,
                name: "Rebecca Smtih",
                avatar: "https://picsum.photos/seed/rebecca/200/200.jpg",
                status: "online",
                lastMsgTime: "Mon",
                unread: 25,
                lastMsg: "Okay, see you then!",
                messages: [
                    { id: 1, text: "Are we still meeting Monday?", type: "incoming", time: "Mon" }
                ]
            },
            {
                id: 5,
                name: "Design Team",
                avatar: "https://picsum.photos/seed/design/200/200.jpg",
                status: "online",
                lastMsgTime: "Sun",
                lastMsg: "New updates available.",
                messages: []
            }
        ];

        let currentChatId = 1; 
        let localStream = null;
        let callTimerInterval = null;
        let callSeconds = 0;
        let selectedConfContacts = new Set();
        let callType = 'video'; // 'audio' or 'video'

        // --- DOM Elements ---
        const chatListEl = document.getElementById('chatList');
        const messagesWrapperEl = document.getElementById('messagesWrapper');
        const currentChatNameEl = document.getElementById('currentChatName');
        const currentChatStatusEl = document.getElementById('currentChatStatus');
        const currentChatAvatarEl = document.getElementById('currentChatAvatar');
        const messageInputEl = document.getElementById('messageInput');
        const sendBtnEl = document.getElementById('sendBtn');
        const searchInputEl = document.getElementById('searchInput');
        const backBtnEl = document.getElementById('backBtn');
        const sidebarEl = document.getElementById('sidebar');

        // File Attachment Elements
        const attachBtn = document.getElementById('attachBtn');
        const fileInput = document.getElementById('fileInput');

        // Call Buttons (Separated for logic)
        const openVoiceCallBtn = document.getElementById('openVoiceCallBtn');
        const startVideoCallBtn = document.getElementById('startVideoCallBtn');

        // Video Call Overlay Elements
        const videoCallOverlay = document.getElementById('videoCallOverlay');
        const localVideo = document.getElementById('localVideo');
        const remoteVideo = document.getElementById('remoteVideo');
        const endCallBtn = document.getElementById('endCallBtn');
        const shareLinkBtn = document.getElementById('shareLinkBtn');
        const toggleMicBtn = document.getElementById('toggleMicBtn');
        const toggleCamBtn = document.getElementById('toggleCamBtn');
        const callUserName = document.getElementById('callUserName');
        const callTimer = document.getElementById('callTimer');
        const toastMsg = document.getElementById('toastMsg');
        const callAvatarText = document.getElementById('callAvatarText');

        // Modal Elements
        const confModal = document.getElementById('confModal');
        const modalTitle = document.getElementById('modalTitle');
        const closeConfModal = document.getElementById('closeConfModal');
        const cancelConfBtn = document.getElementById('cancelConfBtn');
        const confSearchInput = document.getElementById('confSearchInput');
        const confContactList = document.getElementById('confContactList');
        const startConfBtn = document.getElementById('startConfBtn');

        // --- Functions ---

        // Render Sidebar List
        function renderChatList() {
            chatListEl.innerHTML = '';
            
            const searchTerm = searchInputEl.value.toLowerCase();

            contacts.forEach(contact => {
                if(contact.name.toLowerCase().includes(searchTerm)) {
                    const isActive = contact.id === currentChatId;
                    
                    const div = document.createElement('div');
                    div.className = `chat-item ${isActive ? 'active' : ''}`;
                    div.onclick = () => selectChat(contact.id);

                    // Status Logic
                    let statusHtml = '';
                    if(contact.typing) {
                        statusHtml = `<span class="chat-meta typing-indicator">Typing...</span>`;
                    } else {
                        statusHtml = `<span class="chat-meta">${contact.lastMsgTime}</span>`;
                    }

                    // Preview Logic
                    let previewHtml = contact.lastMsg || 'No messages yet';
                    
                    if(contact.previewType === 'document') {
                        previewHtml = `<i class="ri-file-text-line doc-icon"></i> ${contact.lastMsg}`;
                    } else if(contact.previewType === 'missed_video') {
                        previewHtml = `<i class="ri-missed-video-line missed-call-icon"></i> Missed Video Call`;
                    } else if (contact.messages.length > 0) {
                        const last = contact.messages[contact.messages.length - 1];
                        previewHtml = last.type === 'outgoing' ? `You: ${last.text.replace(/<[^>]*>?/gm, '').substring(0, 30)}...` : last.text.replace(/<[^>]*>?/gm, '').substring(0, 30) + '...';
                    }

                    // Unread Badge
                    const unreadBadge = contact.unread ? `<div class="unread-badge">${contact.unread}</div>` : '';

                    div.innerHTML = `
                        <div class="avatar-container">
                            <img src="${contact.avatar}" alt="${contact.name}" class="avatar">
                            <div class="status-dot status-${contact.status}"></div>
                        </div>
                        <div class="chat-info">
                            <div class="chat-header-row">
                                <span class="chat-name">${contact.name}</span>
                                ${statusHtml}
                            </div>
                            <div class="chat-preview-row">
                                <span class="chat-last-msg">${previewHtml}</span>
                                ${unreadBadge}
                            </div>
                        </div>
                    `;
                    chatListEl.appendChild(div);
                }
            });
        }

        // Select a Chat
        function selectChat(id) {
            currentChatId = id;
            
            const contact = contacts.find(c => c.id === id);
            if(contact) contact.unread = 0;

            renderChatList();
            renderMessages();
            updateHeader();
            
            if(window.innerWidth <= 768) {
                sidebarEl.classList.add('hidden');
            }
        }

        // Update Header Info
        function updateHeader() {
            const contact = contacts.find(c => c.id === currentChatId);
            if(!contact) return;

            currentChatNameEl.textContent = contact.name;
            currentChatAvatarEl.src = contact.avatar;

            if(contact.typing) {
                currentChatStatusEl.textContent = "Typing...";
                currentChatStatusEl.style.color = "var(--primary-color)";
            } else {
                const statusMap = {
                    'online': 'Online',
                    'offline': 'Offline',
                    'busy': 'Busy'
                };
                currentChatStatusEl.textContent = statusMap[contact.status] || 'Offline';
                currentChatStatusEl.style.color = contact.status === 'online' ? 'var(--success-color)' : 'var(--text-secondary)';
            }
        }

        // Render Messages
        function renderMessages() {
            messagesWrapperEl.innerHTML = '';
            const contact = contacts.find(c => c.id === currentChatId);
            
            if(!contact || contact.messages.length === 0) {
                messagesWrapperEl.innerHTML = `<div style="text-align:center; color:var(--text-secondary); margin-top:60px; display:flex; flex-direction:column; align-items:center; gap:12px;">
                    <i class="ri-chat-smile-2-line" style="font-size: 3rem; opacity: 0.5;"></i>
                    <span>No messages yet. Say hello!</span>
                </div>`;
                return;
            }

            contact.messages.forEach(msg => {
                const msgGroup = document.createElement('div');
                msgGroup.className = `message-group ${msg.type}`;
                
                const avatarUrl = msg.type === 'outgoing' 
                    ? 'https://picsum.photos/seed/me/200/200.jpg' 
                    : contact.avatar;

                let ticks = '';
                if(msg.type === 'outgoing') {
                    ticks = `<i class="ri-check-double-line" style="font-size:0.8em; color: ${msg.status === 'read' ? '#4ADE80' : '#fff'}"></i>`;
                }

                msgGroup.innerHTML = `
                    <img src="${avatarUrl}" class="msg-avatar" alt="Avatar">
                    <div class="message-content">
                        <div class="message-bubble">
                            ${msg.text}
                        </div>
                        <div class="message-time">
                            ${msg.time} ${ticks}
                        </div>
                    </div>
                `;
                messagesWrapperEl.appendChild(msgGroup);
            });

            scrollToBottom();
        }

        function scrollToBottom() {
            messagesWrapperEl.scrollTop = messagesWrapperEl.scrollHeight;
        }

        // --- Send Message Logic ---
        function sendMessage() {
            const text = messageInputEl.value.trim();
            if(!text) return;

            const contact = contacts.find(c => c.id === currentChatId);
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            const newMsg = {
                id: Date.now(),
                text: text,
                type: 'outgoing',
                time: timeString,
                status: 'sent'
            };

            contact.messages.push(newMsg);
            messageInputEl.value = '';
            finalizeSending(contact);
        }

        // --- File Sharing Logic ---
        attachBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const contact = contacts.find(c => c.id === currentChatId);
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            const newMsg = {
                id: Date.now(),
                type: 'outgoing',
                time: timeString,
                status: 'sent'
            };

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const imgUrl = event.target.result;
                    newMsg.text = `<img src="${imgUrl}" class="attachment-img" alt="${file.name}">`;
                    contact.messages.push(newMsg);
                    finalizeSending(contact);
                    fileInput.value = ''; 
                };
                reader.readAsDataURL(file);
            } else {
                const fileSizeKB = (file.size / 1024).toFixed(1) + ' KB';
                newMsg.text = `
                    <div class="file-attachment">
                        <div class="file-icon-box">
                            <i class="ri-file-text-line"></i>
                        </div>
                        <div class="file-details">
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${fileSizeKB}</div>
                        </div>
                    </div>
                `;
                contact.messages.push(newMsg);
                finalizeSending(contact);
                fileInput.value = ''; 
            }
        });

        function finalizeSending(contact) {
            if(contact.typing) {
                contact.typing = false;
            }
            renderMessages();
            renderChatList(); 
            simulateReply(contact);
        }

        function simulateReply(contact) {
            setTimeout(() => {
                contact.typing = true;
                renderChatList();
                updateHeader();
            }, 1000);

            setTimeout(() => {
                contact.typing = false;
                const now = new Date();
                const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                const replyMsg = {
                    id: Date.now() + 1,
                    text: "That sounds good to me. Thanks for the update!",
                    type: "incoming",
                    time: timeString
                };
                
                contact.messages.push(replyMsg);

                if(currentChatId === contact.id) {
                    renderMessages();
                    updateHeader();
                } else {
                    contact.unread = (contact.unread || 0) + 1;
                }
                renderChatList();

                const lastOutgoing = contact.messages.filter(m => m.type === 'outgoing').pop();
                if(lastOutgoing) lastOutgoing.status = 'read';
                renderMessages();

            }, 3500);
        }

        // --- Call Logic (Audio vs Video) ---
        
        function getInitials(name) {
            const parts = name.trim().split(' ');
            if (parts.length === 0) return 'U';
            if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }

        async function startCall(isConference, participants, type) {
            callType = type; // 'audio' or 'video'
            const contact = contacts.find(c => c.id === currentChatId);

            // UI Setup based on Call Type
            if(callType === 'audio') {
                videoCallOverlay.classList.remove('is-video'); // Hides video elements, shows placeholder
                callAvatarText.textContent = isConference ? "Voice Conference" : "Voice Call";
                modalTitle.textContent = "Start Voice Call";
                startConfBtn.textContent = "Start Voice Call";
            } else {
                videoCallOverlay.classList.add('is-video'); // Shows video elements
                callAvatarText.textContent = isConference ? "Video Conference" : "Video Call";
                modalTitle.textContent = "Start Video Call";
                startConfBtn.textContent = "Start Video Call";
            }

            if(isConference) {
                const names = participants.map(id => contacts.find(c => c.id == id)?.name).join(', ');
                callUserName.textContent = callType === 'audio' ? "Audio: " + (names || contact?.name) : "Video: " + (names || contact?.name);
                
                let conferenceInitials = "GR";
                if (participants.length === 1) {
                     const single = contacts.find(c => c.id == participants[0]);
                     if(single) conferenceInitials = getInitials(single.name);
                }
                document.getElementById('remoteInitials').textContent = conferenceInitials;
            } else {
                if(contact) {
                    callUserName.textContent = contact.name;
                    document.getElementById('remoteInitials').textContent = getInitials(contact.name);
                }
            }

            // Camera Button Visibility
            if(callType === 'audio') {
                toggleCamBtn.style.display = 'none';
            } else {
                toggleCamBtn.style.display = 'flex';
            }

            try {
                // Dynamic Constraints
                const constraints = { audio: true, video: callType === 'video' };
                
                localStream = await navigator.mediaDevices.getUserMedia(constraints);
                localVideo.srcObject = localStream;
                
                videoCallOverlay.classList.add('active');
                
                callSeconds = 0;
                callTimer.textContent = "00:00";
                callTimerInterval = setInterval(() => {
                    callSeconds++;
                    const mins = Math.floor(callSeconds / 60).toString().padStart(2, '0');
                    const secs = (callSeconds % 60).toString().padStart(2, '0');
                    callTimer.textContent = `${mins}:${secs}`;
                }, 1000);

            } catch (err) {
                console.error("Error accessing media devices:", err);
                alert("Unable to access microphone" + (callType === 'video' ? " or camera" : "") + ". Please allow permissions.");
            }
        }

        function endCall() {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            localVideo.srcObject = null;
            remoteVideo.srcObject = null; 
            videoCallOverlay.classList.remove('active');
            videoCallOverlay.classList.remove('is-video'); // Reset class
            if (callTimerInterval) clearInterval(callTimerInterval);
            
            // Reset UI
            toggleCamBtn.style.display = 'flex';
        }

        function toggleMic() {
            if (!localStream) return;
            const audioTrack = localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                toggleMicBtn.innerHTML = audioTrack.enabled ? '<i class="ri-mic-line"></i>' : '<i class="ri-mic-off-line"></i>';
                toggleMicBtn.classList.toggle('active', !audioTrack.enabled);
            }
        }

        function toggleCam() {
            if (!localStream) return;
            const videoTrack = localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                toggleCamBtn.innerHTML = videoTrack.enabled ? '<i class="ri-camera-line"></i>' : '<i class="ri-camera-off-line"></i>';
                toggleCamBtn.classList.toggle('active', !videoTrack.enabled);
            }
        }

        function shareMeetingLink() {
            const dummyLink = `https://teamchat.app/meet/${Math.random().toString(36).substring(7)}`;
            navigator.clipboard.writeText(dummyLink).then(() => {
                showToast("Meeting link copied to clipboard!");
            }).catch(err => {
                showToast("Failed to copy link");
            });
        }

        function showToast(msg) {
            toastMsg.textContent = msg;
            toastMsg.classList.add('show');
            setTimeout(() => {
                toastMsg.classList.remove('show');
            }, 3000);
        }

        // --- Modal Functions ---

        function openConfModal(type) {
            callType = type; // 'audio' or 'video'
            selectedConfContacts.clear();
            
            if(currentChatId) selectedConfContacts.add(currentChatId);
            
            if(callType === 'video') {
                modalTitle.textContent = "Start Video Call";
                startConfBtn.textContent = "Start Video Call";
            } else {
                modalTitle.textContent = "Start Voice Call";
                startConfBtn.textContent = "Start Voice Call";
            }
            
            renderConfContactList();
            confModal.classList.add('active');
        }

        function closeConfModalFunc() {
            confModal.classList.remove('active');
        }

        function renderConfContactList() {
            confContactList.innerHTML = '';
            const searchTerm = confSearchInput.value.toLowerCase();

            contacts.forEach(contact => {
                if(contact.name.toLowerCase().includes(searchTerm)) {
                    const isSelected = selectedConfContacts.has(contact.id);
                    const div = document.createElement('div');
                    div.className = `conf-contact-item ${isSelected ? 'selected' : ''}`;
                    div.onclick = (e) => toggleConfSelection(contact.id, e);

                    div.innerHTML = `
                        <img src="${contact.avatar}" class="conf-avatar" alt="${contact.name}">
                        <div class="conf-info">
                            <div class="conf-name">${contact.name}</div>
                            <div class="conf-status" style="color: var(--text-secondary)">${contact.status}</div>
                        </div>
                        <input type="checkbox" class="conf-checkbox" ${isSelected ? 'checked' : ''} readonly>
                    `;
                    confContactList.appendChild(div);
                }
            });
        }

        function toggleConfSelection(id, event) {
            if (event.target.type === 'checkbox') return;

            if (selectedConfContacts.has(id)) {
                selectedConfContacts.delete(id);
            } else {
                selectedConfContacts.add(id);
            }
            renderConfContactList();
        }

        function handleStartConference() {
            if(selectedConfContacts.size === 0) {
                alert("Please select at least one participant.");
                return;
            }
            closeConfModalFunc();
            startCall(true, Array.from(selectedConfContacts), callType);
        }

        // --- Event Listeners ---
        sendBtnEl.addEventListener('click', sendMessage);
        messageInputEl.addEventListener('keypress', (e) => {
            if(e.key === 'Enter') sendMessage();
        });
        searchInputEl.addEventListener('input', renderChatList);
        backBtnEl.addEventListener('click', () => {
            sidebarEl.classList.remove('hidden');
        });

        // Separate Listeners for Voice vs Video
        openVoiceCallBtn.addEventListener('click', () => openConfModal('audio'));
        startVideoCallBtn.addEventListener('click', () => openConfModal('video'));

        // Call Controls
        endCallBtn.addEventListener('click', endCall);
        shareLinkBtn.addEventListener('click', shareMeetingLink);
        toggleMicBtn.addEventListener('click', toggleMic);
        toggleCamBtn.addEventListener('click', toggleCam);

        // Modal Listeners
        closeConfModal.addEventListener('click', closeConfModalFunc);
        cancelConfBtn.addEventListener('click', closeConfModalFunc);
        confSearchInput.addEventListener('input', renderConfContactList);
        startConfBtn.addEventListener('click', handleStartConference);
        confModal.addEventListener('click', (e) => {
            if(e.target === confModal) closeConfModalFunc();
        });

        // --- Initialization ---
        contacts[0].typing = false; 
        renderChatList();
        renderMessages();
        updateHeader();
        
        setTimeout(() => {
            contacts[0].typing = true;
            renderChatList();
            updateHeader();
        }, 1000);

    </script>
</body>
</html>