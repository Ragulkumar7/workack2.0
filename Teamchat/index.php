<?php
// index.php
require_once 'backend.php';
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
        
        #mainContent { margin-left: 95px; width: calc(100% - 95px); height: 100vh; display: flex; flex-direction: column; transition: all 0.3s; }
        .app-container { flex: 1; display:flex; height: 0; min-height: 0; background: var(--bg-light); position: relative;}
        
        /* SECONDARY SIDEBAR */
        .sidebar-secondary-teams { width: 80px; background: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; padding-top: 15px; z-index: 15; box-shadow: var(--shadow-sm); }
        .nav-icon { width: 56px; height: 56px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); font-size: 0.75rem; border-radius: var(--radius-md); margin-bottom: 8px; transition: all 0.2s ease; font-weight: 500;}
        .nav-icon i { font-size: 1.5rem; margin-bottom: 4px; transition: transform 0.2s; }
        .nav-icon:hover { color: var(--primary); background: var(--primary-light); }
        .nav-icon:hover i { transform: translateY(-2px); }
        .nav-icon.active { background: var(--primary-light); color: var(--primary); font-weight: 600; box-shadow: inset 3px 0 0 var(--primary); }

        /* PRIMARY SIDEBAR */
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
        
        .section-toggle { padding: 10px 20px 5px; display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.85rem; cursor: pointer; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .chat-item { display: flex; align-items: center; padding: 12px 16px; cursor: pointer; border-radius: var(--radius-md); margin-bottom: 4px; transition: all 0.2s; border: 1px solid transparent; }
        .chat-item:hover { background: var(--hover-bg); }
        .chat-item.active { background: var(--primary-light); border-color: transparent; }
        .chat-item.active .chat-item-name { color: var(--primary); font-weight: 600; }
        
        .avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid var(--surface); box-shadow: var(--shadow-sm); }
        
        #searchResults { position: absolute; top: 60px; left: 20px; width: calc(100% - 40px); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); z-index: 50; display: none; max-height: 300px; overflow-y: auto;}
        .search-item { padding: 12px 16px; cursor: pointer; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-light);}
        .search-item:hover { background: var(--hover-bg); }

        /* MAIN CONTENT AREA */
        .content-area { flex: 1; display: flex; flex-direction: row; background: var(--bg-light); position: relative; overflow:hidden; }

        .chat-main-column { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; background: var(--surface); margin: 10px 10px 10px 0; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
        
        /* GROUP INFO PANEL */
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
        
        /* Message Dropdowns */
        .msg-menu-btn { position: absolute; top: 6px; right: 6px; background: rgba(255,255,255,0.8); border: none; color: var(--text-muted); cursor: pointer; opacity: 0; transition: opacity 0.2s, background 0.2s; padding: 4px; border-radius: 50%; box-shadow: var(--shadow-sm);}
        .msg-wrapper:hover .msg-menu-btn { opacity: 1; }
        .msg-menu-btn:hover { background: var(--surface); color: var(--text-dark); }
        .msg-dropdown { position: absolute; top: 35px; right: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); z-index: 50; display: none; overflow: hidden; min-width: 140px; padding: 4px;}
        .msg-dropdown button { width: 100%; text-align: left; padding: 10px 16px; border: none; background: transparent; cursor: pointer; font-size: 0.9rem; border-radius: var(--radius-sm); transition: all 0.2s; color: var(--text-dark);}
        .msg-dropdown button:hover { background: var(--hover-bg); color: var(--primary); }
        .msg-dropdown button.delete-btn:hover { color: #EF4444; background: #FEF2F2; }

        /* Header Dropdown */
        #chatOptionsDropdown { position: absolute; top: 55px; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); z-index: 50; display: none; min-width: 180px; padding: 8px;}
        #chatOptionsDropdown button { width: 100%; text-align: left; padding: 10px 16px; border: none; background: transparent; cursor: pointer; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; border-radius: var(--radius-sm); transition: 0.2s; color: var(--text-dark);}
        #chatOptionsDropdown button:hover { background: #FEF2F2; color: #EF4444;}

        /* Teams Input Area & Emoji Picker */
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
        
        #emojiPicker { display: none; position: absolute; bottom: 90px; right: 24px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); width: 300px; height: 250px; overflow-y: auto; padding: 16px; z-index: 100; grid-template-columns: repeat(6, 1fr); gap: 10px; text-align: center; font-size: 1.4rem; }
        #emojiPicker span { cursor: pointer; transition: transform 0.1s; padding: 4px; border-radius: 4px; }
        #emojiPicker span:hover { transform: scale(1.2); background: var(--hover-bg); }

        /* MEET SECTION UI */
        #meet_view { display: none; flex-direction: column; padding: 40px 60px; max-width: 1000px; margin: 0 auto; width: 100%; overflow-y: auto; flex: 1; }
        
        .meet-hero-btn { flex: 1; background: var(--surface); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; gap: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: var(--shadow-sm); font-size: 1.05rem; color: var(--text-dark);}
        .meet-hero-btn:hover { background: var(--hover-bg); color: var(--primary); border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .meet-hero-btn.primary { background: var(--primary); color: white; border-color: var(--primary); }
        .meet-hero-btn.primary:hover { background: var(--primary-hover); color: white; }
        .meet-hero-btn i { font-size: 1.4rem; }

        /* PEOPLE STYLES */
        .people-card { display: flex; align-items: center; justify-content: space-between; padding: 16px 32px; border-bottom: 1px solid var(--border); transition: background 0.2s; background: var(--surface); }
        .people-card:hover { background: var(--hover-bg); }
        .people-info { display: flex; align-items: center; gap: 16px; }
        .people-btn { background: var(--surface); color: var(--text-dark); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--border); transition: all 0.2s; font-size: 1.3rem; box-shadow: var(--shadow-sm);}
        .people-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: scale(1.05); box-shadow: var(--shadow-md); }

        /* CALENDAR STYLES */
        .calendar-header { display: flex; justify-content: space-between; align-items: center; padding: 24px 32px; border-bottom: 1px solid var(--border); background: var(--surface); }
        .calendar-title { font-size: 1.6rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 12px;}
        .calendar-title i { color: var(--primary); }
        .cal-nav-btn { background: var(--surface); border: 1px solid var(--border); padding: 10px 16px; border-radius: var(--radius-sm); font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: 0.2s; color: var(--text-dark); }
        .cal-nav-btn:hover { background: var(--hover-bg); color: var(--primary); border-color: var(--primary); }
        .cal-primary-btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: var(--radius-sm); font-size: 0.95rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: var(--shadow-sm); }
        .cal-primary-btn:hover { background: var(--primary-hover); }
        
        .calendar-grid-container { display: flex; flex-direction: column; flex: 1; overflow: hidden; background: var(--surface); }
        .calendar-grid { display: flex; flex: 1; overflow-y: auto; overflow-x: auto; }
        .time-col { width: 70px; border-right: 1px solid var(--border); display: flex; flex-direction: column; background: #fafafa; }
        .time-slot { height: 70px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: flex-end; padding: 8px 12px 0 0; font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }
        
        .day-cols { display: flex; flex: 1; }
        .day-col { flex: 1; min-width: 160px; border-right: 1px solid var(--border); display: flex; flex-direction: column; position: relative; }
        .day-header { padding: 20px 16px; border-bottom: 1px solid var(--border); text-align: left; height: 85px; position: sticky; top:0; background: var(--surface); z-index:10; }
        .day-num { font-size: 1.8rem; font-weight: 600; color: var(--text-dark); line-height: 1; }
        .day-name { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin-top:6px; letter-spacing: 0.05em; font-weight: 600;}
        .day-header.active .day-num, .day-header.active .day-name { color: var(--primary); }
        .grid-cell { height: 70px; border-bottom: 1px solid var(--border-light); transition: background 0.2s; cursor: pointer; position: relative; }
        .grid-cell:hover { background: var(--primary-light); }
        
        .cal-event { background: var(--primary); color: white; padding: 6px 10px; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; margin: 4px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; position: absolute; width: calc(100% - 8px); z-index: 5; box-shadow: var(--shadow-sm); transition: transform 0.2s;}
        .cal-event:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); filter: brightness(1.05); }

        /* OVERLAYS & MODALS */
        #videoOverlay { display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:#0f172a; z-index:2000; flex-direction:column; }
        .video-overlay-header { padding: 16px 24px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; color: white; box-shadow: var(--shadow-md); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(17, 24, 39, 0.7); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px;}
        .modal { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); border: 1px solid var(--border); animation: modalFadeIn 0.3s ease-out;}
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        
        .incoming-call-box { background: var(--surface); border-radius: var(--radius-lg); padding: 40px; text-align: center; min-width: 360px; box-shadow: var(--shadow-lg); border: 1px solid var(--border); animation: pulse 2s infinite;}
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255, 107, 43, 0.4); } 70% { box-shadow: 0 0 0 20px rgba(255, 107, 43, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 107, 43, 0); } }
        
        /* Edit Mode Bar */
        #editModeBar { display: none; background: var(--primary-light); padding: 12px 24px; align-items: center; justify-content: space-between; font-size: 0.9rem; color: var(--primary); z-index:10; font-weight: 600; border-top: 1px solid #FFD9C6;}

        /* Reusable Card Styles */
        .data-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); transition: box-shadow 0.2s; }
        .data-card:hover { box-shadow: var(--shadow-md); }

        /* General Inputs */
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
        
        /* Primary Buttons */
        .btn-primary { width:100%; padding:12px; background:var(--primary); color:white; border:none; border-radius:var(--radius-sm); cursor:pointer; font-weight:600; font-size: 1rem; transition: background 0.2s, transform 0.1s; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-primary:active { transform: translateY(0); }

        /* Mobile Adjustments */
        #mobileBackBtn { display: none; }
        @media (max-width: 992px) {
            #mainContent { margin-left: 0; width: 100%; }
            .sidebar-secondary-teams { display: none; }
            .sidebar { width: 100%; position: absolute; height: 100%; z-index: 20; }
            .content-area { width: 100%; }
            .sidebar.hide-mobile { transform: translateX(-100%); }
            #mobileBackBtn { display: flex; }
            #meet_view { padding: 20px; }
            .chat-main-column, .group-info-panel { margin: 0; border-radius: 0; border: none; border-top: 1px solid var(--border);}
        }
    </style>
</head>
<body>

<?php if(file_exists('../sidebars.php')) include '../sidebars.php'; ?>

<main id="mainContent">
    <?php if(file_exists('../header.php')) include '../header.php'; ?>
    
    <div class="app-container">
        <aside class="sidebar-secondary-teams">
            <div class="nav-icon active" onclick="switchMainTab('chat_view', this)">
                <i class="ri-chat-3-fill"></i>
                <span>Chat</span>
            </div>
            <div class="nav-icon" onclick="switchMainTab('meet_view', this)">
                <i class="ri-video-add-fill"></i>
                <span>Meet</span>
            </div>
            <div class="nav-icon" onclick="switchMainTab('people_view', this)">
                <i class="ri-contacts-book-2-fill"></i>
                <span>People</span>
            </div>
            <div class="nav-icon" onclick="switchMainTab('calendar_view', this)">
                <i class="ri-calendar-event-fill"></i>
                <span>Calendar</span>
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
                    <button class="btn-icon-small" title="Meet" onclick="switchMainTab('meet_view', document.querySelectorAll('.nav-icon')[1])"><i class="ri-video-add-line"></i></button>
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
                <div class="section-toggle">
                    <i class="ri-arrow-down-s-fill" style="font-size: 1.2rem;"></i> Recent
                </div>
                <div class="chat-list" id="chatList">
                    <div style="text-align:center; padding: 40px; color:var(--text-muted);"><i class="ri-loader-4-line ri-spin" style="font-size: 2rem; color: var(--primary);"></i><br><br>Loading chats...</div>
                </div>
            </div>
        </aside>

        <section class="content-area" id="mainContentView">
            <?php include 'views/chat.php'; ?>
            <?php include 'views/meet.php'; ?>
            <?php include 'views/people.php'; ?>
            <?php include 'views/calendar.php'; ?>
        </section>
    </div>
</main>

<?php include 'views/modals.php'; ?>
<?php include 'views/scripts.php'; ?>

</body>
</html>