<?php
// Fixes "headers already sent" error by turning on output buffering
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database and Path configuration
if (file_exists('include/db_connect.php')) {
    require_once 'include/db_connect.php';
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    $sidebarPath = '';
    $headerPath = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales CRM - Client Management</title>
    <style>
        /* ==========================================================================
           CSS VARIABLES & THEME
           ========================================================================== */
        :root {
            /* Light Theme */
            --bg-body: #f8fafc;
            --bg-surface: #ffffff;
            --bg-hover: #f1f5f9;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            
            /* Brand Colors */
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --danger-color: #ef4444;
            --danger-hover: #dc2626;
            
            /* Status Colors */
            --status-new-bg: #dbeafe;
            --status-new-text: #1e40af;
            --status-contacted-bg: #f3e8ff;
            --status-contacted-text: #6b21a8;
            --status-qualified-bg: #ffedd5;
            --status-qualified-text: #9a3412;
            --status-proposal-bg: #fef9c3;
            --status-proposal-text: #854d0e;
            --status-won-bg: #dcfce7;
            --status-won-text: #166534;
            --status-lost-bg: #fee2e2;
            --status-lost-text: #991b1b;

            /* Shadows & Geometry */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-md: 8px;
            --radius-lg: 12px;
            --sidebar-width: 100px;
            --sidebar-collapsed-width: 80px;
            --transition-speed: 0.3s;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-surface: #1e293b;
            --bg-hover: #334155;
            --border-color: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            
            --status-new-bg: rgba(59, 130, 246, 0.2);
            --status-new-text: #93c5fd;
            --status-contacted-bg: rgba(168, 85, 247, 0.2);
            --status-contacted-text: #d8b4fe;
            --status-qualified-bg: rgba(249, 115, 22, 0.2);
            --status-qualified-text: #fdba74;
            --status-proposal-bg: rgba(234, 179, 8, 0.2);
            --status-proposal-text: #fde047;
            --status-won-bg: rgba(34, 197, 94, 0.2);
            --status-won-text: #86efac;
            --status-lost-bg: rgba(239, 68, 68, 0.2);
            --status-lost-text: #fca5a5;
        }

        /* ==========================================================================
           RESET & BASE
           ========================================================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        button {
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--bg-surface);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* ==========================================================================
           LAYOUT: SIDEBAR & MAIN WRAPPER
           ========================================================================== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background-color: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: width var(--transition-speed) ease, transform var(--transition-speed) ease;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left var(--transition-speed) ease;
        }

        .sidebar.collapsed ~ .main-wrapper {
            margin-left: var(--sidebar-collapsed-width);
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-speed) ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .sidebar-header {
            height: 70px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid var(--border-color);
            overflow: hidden;
            white-space: nowrap;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .sidebar-nav {
            padding: 20px 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: background-color 0.2s ease, color 0.2s ease;
            overflow: hidden;
            white-space: nowrap;
        }

        .nav-item:hover, .nav-item.active {
            background-color: var(--bg-hover);
            color: var(--primary-color);
        }

        .sidebar.collapsed .nav-item span,
        .sidebar.collapsed .sidebar-logo span {
            opacity: 0;
            pointer-events: none;
        }

        /* ==========================================================================
           TOP NAVBAR
           ========================================================================== */
        .navbar {
            height: 70px;
            background-color: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .nav-left, .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .toggle-btn {
            color: var(--text-secondary);
            padding: 8px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-btn:hover {
            background-color: var(--bg-hover);
            color: var(--text-primary);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; }
        .user-role { font-size: 12px; color: var(--text-muted); }

        /* ==========================================================================
           MAIN CONTENT & STATS
           ========================================================================== */
        .content {
            flex: 1;
            padding: 30px;
        }

        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--bg-surface);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-hover);
            color: var(--primary-color);
        }

        .stat-details { display: flex; flex-direction: column; }
        .stat-value { font-size: 24px; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
        .stat-label { font-size: 13px; color: var(--text-secondary); font-weight: 500; }

        /* ==========================================================================
           TABLE COMPONENT & CONTROLS
           ========================================================================== */
        .card {
            background-color: var(--bg-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box { position: relative; width: 250px; }
        .search-box svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; }
        .search-box input { padding-left: 36px; }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 16px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover { background-color: var(--primary-hover); }

        .btn-secondary {
            background-color: var(--bg-hover);
            color: var(--text-primary);
            padding: 10px 16px;
            border-radius: var(--radius-md);
        }
        .btn-secondary:hover { background-color: var(--border-color); }

        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 16px 24px; border-bottom: 1px solid var(--border-color); font-size: 14px; white-space: nowrap; }
        th { background-color: var(--bg-hover); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        
        /* Sorting UI */
        th.sortable { cursor: pointer; user-select: none; transition: background-color 0.2s; }
        th.sortable:hover { background-color: var(--border-color); color: var(--text-primary); }
        th.sortable::after { content: ' \2195'; opacity: 0.3; margin-left: 5px; font-size: 14px; }
        th.sortable.asc::after { content: ' \2191'; opacity: 1; color: var(--primary-color); }
        th.sortable.desc::after { content: ' \2193'; opacity: 1; color: var(--primary-color); }

        tbody tr { transition: background-color 0.2s ease; }
        tbody tr:hover { background-color: var(--bg-hover); }

        .client-info { display: flex; flex-direction: column; }
        .client-name { font-weight: 600; color: var(--text-primary); }
        .client-id { font-size: 12px; color: var(--text-muted); }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge.new { background: var(--status-new-bg); color: var(--status-new-text); }
        .badge.contacted { background: var(--status-contacted-bg); color: var(--status-contacted-text); }
        .badge.qualified { background: var(--status-qualified-bg); color: var(--status-qualified-text); }
        .badge.proposal { background: var(--status-proposal-bg); color: var(--status-proposal-text); }
        .badge.won { background: var(--status-won-bg); color: var(--status-won-text); }
        .badge.lost { background: var(--status-lost-bg); color: var(--status-lost-text); }

        .action-btns { display: flex; gap: 10px; }
        .action-btn { width: 32px; height: 32px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: var(--text-secondary); }
        .action-btn:hover { background-color: var(--border-color); color: var(--text-primary); }
        .action-btn.delete:hover { background-color: rgba(239, 68, 68, 0.1); color: var(--danger-color); }

        /* Pagination */
        .pagination-container {
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--border-color);
            background-color: var(--bg-surface);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }
        .pagination-info { font-size: 13px; color: var(--text-secondary); }
        .pagination-controls { display: flex; gap: 8px; }
        .page-btn { padding: 6px 12px; font-size: 13px; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-surface); color: var(--text-primary); }
        .page-btn:hover:not(:disabled) { background: var(--bg-hover); color: var(--primary-color); border-color: var(--primary-color); }
        .page-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Empty State */
        .empty-state {
            display: none; padding: 60px 20px; text-align: center; flex-direction: column; align-items: center; justify-content: center;
        }
        .empty-state svg { width: 64px; height: 64px; color: var(--text-muted); margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; color: var(--text-primary); margin-bottom: 8px; }
        .empty-state p { color: var(--text-secondary); font-size: 14px; max-width: 300px; }

        /* ==========================================================================
           MODALS
           ========================================================================== */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            z-index: 2000; opacity: 0; visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease; padding: 20px;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        
        .modal-content {
            background-color: var(--bg-surface); width: 100%; max-width: 500px;
            border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
            transform: translateY(20px); transition: transform 0.3s ease;
            display: flex; flex-direction: column; max-height: 90vh;
        }
        .modal-overlay.active .modal-content { transform: translateY(0); }

        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        .close-btn { color: var(--text-muted); padding: 4px; border-radius: 4px; }
        .close-btn:hover { background-color: var(--bg-hover); color: var(--text-primary); }

        .modal-body { padding: 24px; overflow-y: auto; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: var(--text-secondary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* Confirmation Modal specific */
        #confirmModal .modal-content { max-width: 400px; text-align: center; }
        #confirmModal .modal-body { padding: 30px 24px; }
        .warning-icon { width: 48px; height: 48px; background-color: rgba(239, 68, 68, 0.1); color: var(--danger-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; }
        
        /* ==========================================================================
           RESPONSIVE DESIGN
           ========================================================================== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width) !important; }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-wrapper, .sidebar.collapsed ~ .main-wrapper { margin-left: 0; }
            .form-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: 1fr; }
            .user-info { display: none; }
            .pagination-container { flex-direction: column; gap: 12px; }
        }
    </style>
</head>
<body>

    <?php if(file_exists($sidebarPath)) require_once $sidebarPath; ?>

    <?php if(!file_exists($sidebarPath)): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                <span>Sales CRM</span>
            </div>
        </div>
        <div class="sidebar-nav">
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item active">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Clients</span>
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                <span>Reports</span>
            </a>
        </div>
    </aside>
    <?php endif; ?>

    <div class="main-wrapper">
        <?php if(file_exists($headerPath)) require_once $headerPath; ?>
        
        <?php if(!file_exists($headerPath)): ?>
        <nav class="navbar">
            <div class="nav-left">
                <button class="toggle-btn" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
            </div>
            <div class="nav-right">
                <button class="toggle-btn" id="themeToggle" aria-label="Toggle Theme">
                    <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                </button>
                <div class="user-profile">
                    <div class="avatar">SM</div>
                    <div class="user-info">
                        <span class="user-name">Sarah Manager</span>
                        <span class="user-role">Sales Director</span>
                    </div>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <main class="content">
            <div class="page-header">
                <h1 class="page-title">Client Management</h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <div class="stat-details">
                        <span class="stat-value" id="stat-total-clients">0</span>
                        <span class="stat-label">Total Clients</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--status-qualified-text); background: var(--status-qualified-bg);">
                        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    </div>
                    <div class="stat-details">
                        <span class="stat-value" id="stat-active-leads">0</span>
                        <span class="stat-label">Active Leads</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--status-won-text); background: var(--status-won-bg);">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                    <div class="stat-details">
                        <span class="stat-value" id="stat-closed-deals">0</span>
                        <span class="stat-label">Closed Deals</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--status-new-text); background: var(--status-new-bg);">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                    <div class="stat-details">
                        <span class="stat-value" id="stat-total-revenue">₹0</span>
                        <span class="stat-label">Total Revenue (Won)</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="controls">
                        <div class="search-box">
                            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" id="searchInput" placeholder="Search clients...">
                        </div>
                        <select id="filterStatus">
                            <option value="All">All Statuses</option>
                            <option value="New">New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Proposal">Proposal</option>
                            <option value="Won">Won</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                    <button class="btn-primary" id="addClientBtn">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add Client
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="clientTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="name">Client Details</th>
                                <th class="sortable" data-sort="email">Contact Info</th>
                                <th class="sortable" data-sort="source">Source</th>
                                <th class="sortable" data-sort="status">Status</th>
                                <th class="sortable" data-sort="executive">Executive</th>
                                <th class="sortable" data-sort="value">Deal Value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            </tbody>
                    </table>
                    
                    <div class="empty-state" id="emptyState">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <h3>No Clients Found</h3>
                        <p>Get started by adding a new client to your pipeline or adjust your search filters.</p>
                    </div>
                </div>

                <div class="pagination-container" id="paginationContainer">
                    <div class="pagination-info" id="paginationInfo">Showing 1 to 5 of 10 entries</div>
                    <div class="pagination-controls">
                        <button class="page-btn" id="prevPageBtn" disabled>Previous</button>
                        <button class="page-btn" id="nextPageBtn">Next</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="clientModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Client</h3>
                <button class="close-btn" id="closeModalBtn" aria-label="Close">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form id="clientForm">
                <div class="modal-body">
                    <input type="hidden" id="clientId">
                    
                    <div class="form-group">
                        <label for="clientName">Full Name / Company *</label>
                        <input type="text" id="clientName" required placeholder="e.g. Acme Corp">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientPhone">Phone Number *</label>
                            <input type="tel" id="clientPhone" required placeholder="+91 98765 43210">
                        </div>
                        <div class="form-group">
                            <label for="clientEmail">Email Address *</label>
                            <input type="email" id="clientEmail" required placeholder="contact@acme.com">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientSource">Lead Source *</label>
                            <select id="clientSource" required>
                                <option value="" disabled selected>Select Source</option>
                                <option value="Website">Website</option>
                                <option value="Referral">Referral</option>
                                <option value="Cold Call">Cold Call</option>
                                <option value="Social Media">Social Media</option>
                                <option value="Trade Show">Trade Show</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="clientStatus">Status *</label>
                            <select id="clientStatus" required>
                                <option value="New">New</option>
                                <option value="Contacted">Contacted</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Proposal">Proposal</option>
                                <option value="Won">Won</option>
                                <option value="Lost">Lost</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientExecutive">Assigned Executive *</label>
                            <input type="text" id="clientExecutive" required placeholder="e.g. John Doe">
                        </div>
                        <div class="form-group">
                            <label for="clientValue">Deal Value (₹) *</label>
                            <input type="number" id="clientValue" required min="0" step="0.01" placeholder="50000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn-primary">Save Client</button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirmModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-body">
                <div class="warning-icon">
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <h3 class="modal-title" style="margin-bottom: 8px;">Delete Client?</h3>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 24px;">This action cannot be undone. All data related to this client will be permanently removed.</p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button class="btn-secondary" id="cancelDeleteBtn">Cancel</button>
                    <button class="btn-danger" id="confirmDeleteBtn">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * Professional Sales CRM - Client Management Architecture
         */
        class ClientManager {
            constructor() {
                // Application State
                this.clients = [];
                this.editingId = null;
                this.clientToDelete = null;
                
                // Pagination & Sorting State
                this.currentPage = 1;
                this.itemsPerPage = 5;
                this.sortCol = 'created_at'; // Default sort key (pseudo)
                this.sortAsc = false;

                // DOM Elements - Layout
                this.sidebar = document.getElementById('sidebar');
                this.sidebarOverlay = document.getElementById('sidebarOverlay');
                this.sidebarToggle = document.getElementById('sidebarToggle');
                this.themeToggle = document.getElementById('themeToggle');
                
                // DOM Elements - Table & Controls
                this.tableBody = document.getElementById('tableBody');
                this.emptyState = document.getElementById('emptyState');
                this.searchInput = document.getElementById('searchInput');
                this.filterStatus = document.getElementById('filterStatus');
                this.clientTable = document.getElementById('clientTable');
                this.tableHeaders = document.querySelectorAll('th.sortable');
                
                // DOM Elements - Pagination
                this.paginationContainer = document.getElementById('paginationContainer');
                this.paginationInfo = document.getElementById('paginationInfo');
                this.prevPageBtn = document.getElementById('prevPageBtn');
                this.nextPageBtn = document.getElementById('nextPageBtn');

                // DOM Elements - Stats
                this.statTotal = document.getElementById('stat-total-clients');
                this.statActive = document.getElementById('stat-active-leads');
                this.statClosed = document.getElementById('stat-closed-deals');
                this.statRevenue = document.getElementById('stat-total-revenue');

                // DOM Elements - Modals
                this.addClientBtn = document.getElementById('addClientBtn');
                this.clientModal = document.getElementById('clientModal');
                this.clientForm = document.getElementById('clientForm');
                this.closeModalBtn = document.getElementById('closeModalBtn');
                this.cancelModalBtn = document.getElementById('cancelModalBtn');
                this.modalTitle = document.getElementById('modalTitle');
                
                // DOM Elements - Confirm Modal
                this.confirmModal = document.getElementById('confirmModal');
                this.cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
                this.confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

                // Boot initialization
                this.init();
            }

            init() {
                this.loadTheme();
                this.loadData();
                this.bindEvents();
                this.render();
            }

            // --- DATA MANAGEMENT ---
            loadData() {
                const storedClients = localStorage.getItem('crm_clients_v2');
                if (storedClients) {
                    this.clients = JSON.parse(storedClients);
                } else {
                    // Seed robust dummy data
                    this.clients = [
                        { id: this.generateId(), name: 'Global Tech Industries', phone: '+91 98765 12345', email: 'purchasing@globaltech.inc', source: 'Website', status: 'Proposal', executive: 'Sarah Manager', value: 1250000.00, created_at: Date.now() - 100000 },
                        { id: this.generateId(), name: 'Stark Logistics', phone: '+91 98765 23456', email: 'hello@stark.com', source: 'Referral', status: 'Won', executive: 'John Doe', value: 4500000.00, created_at: Date.now() - 200000 },
                        { id: this.generateId(), name: 'Wayne Enterprises', phone: '+91 98765 34567', email: 'b.wayne@wayne.ent', source: 'Trade Show', status: 'New', executive: 'Bruce W.', value: 850000.00, created_at: Date.now() - 300000 },
                        { id: this.generateId(), name: 'Acme Corp', phone: '+91 98765 45678', email: 'contact@acmecorp.in', source: 'Cold Call', status: 'Contacted', executive: 'Sarah Manager', value: 500000.00, created_at: Date.now() - 400000 },
                        { id: this.generateId(), name: 'CyberDyne Systems', phone: '+91 98765 56789', email: 'miles@cyberdyne.io', source: 'Website', status: 'Qualified', executive: 'John Doe', value: 2000000.00, created_at: Date.now() - 500000 },
                        { id: this.generateId(), name: 'Initech', phone: '+91 98765 67890', email: 'sales@initech.com', source: 'Social Media', status: 'Lost', executive: 'Peter G.', value: 100000.00, created_at: Date.now() - 600000 }
                    ];
                    this.saveData();
                }
            }

            saveData() {
                localStorage.setItem('crm_clients_v2', JSON.stringify(this.clients));
            }

            // Secure collision-resistant ID generation
            generateId() {
                const timestamp = Date.now().toString().slice(-6);
                const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
                return `CLI-${timestamp}-${random}`;
            }

            // --- EVENT LISTENERS ---
            bindEvents() {
                // Layout & Mobile Toggles
                if(this.sidebarToggle) {
                    this.sidebarToggle.addEventListener('click', () => {
                        if (window.innerWidth <= 992) {
                            this.sidebar.classList.add('mobile-open');
                            this.sidebarOverlay.classList.add('active');
                            document.body.style.overflow = 'hidden'; // Lock scroll
                        } else {
                            this.sidebar.classList.toggle('collapsed');
                        }
                    });
                }

                if(this.sidebarOverlay) {
                    this.sidebarOverlay.addEventListener('click', () => {
                        this.sidebar.classList.remove('mobile-open');
                        this.sidebarOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    });
                }

                if(this.themeToggle) {
                    this.themeToggle.addEventListener('click', () => {
                        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                        document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
                        localStorage.setItem('crm_theme', isDark ? 'light' : 'dark');
                    });
                }

                // Search & Filter (Reset pagination to page 1 on filter)
                this.searchInput.addEventListener('input', () => { this.currentPage = 1; this.render(); });
                this.filterStatus.addEventListener('change', () => { this.currentPage = 1; this.render(); });

                // Sorting
                this.tableHeaders.forEach(th => {
                    th.addEventListener('click', () => {
                        const col = th.getAttribute('data-sort');
                        if(this.sortCol === col) {
                            this.sortAsc = !this.sortAsc;
                        } else {
                            this.sortCol = col;
                            this.sortAsc = true;
                        }
                        this.render();
                    });
                });

                // Pagination Buttons
                this.prevPageBtn.addEventListener('click', () => {
                    if (this.currentPage > 1) { this.currentPage--; this.render(); }
                });
                this.nextPageBtn.addEventListener('click', () => {
                    this.currentPage++; this.render(); 
                });

                // Modal Triggers
                this.addClientBtn.addEventListener('click', () => this.openFormModal());
                this.closeModalBtn.addEventListener('click', () => this.closeFormModal());
                this.cancelModalBtn.addEventListener('click', () => this.closeFormModal());
                this.clientForm.addEventListener('submit', (e) => this.handleFormSubmit(e));

                // Confirm Delete Triggers
                this.cancelDeleteBtn.addEventListener('click', () => this.closeConfirmModal());
                this.confirmDeleteBtn.addEventListener('click', () => this.executeDelete());

                // Table Actions via Event Delegation
                this.tableBody.addEventListener('click', (e) => {
                    const btn = e.target.closest('.action-btn');
                    if (!btn) return;
                    const id = btn.getAttribute('data-id');
                    if (btn.classList.contains('edit')) this.openFormModal(id);
                    else if (btn.classList.contains('delete')) this.requestDelete(id);
                });

                // Overlay clicks to close modals
                window.addEventListener('click', (e) => {
                    if (e.target === this.clientModal) this.closeFormModal();
                    if (e.target === this.confirmModal) this.closeConfirmModal();
                });
            }

            loadTheme() {
                const theme = localStorage.getItem('crm_theme') || 'light';
                document.documentElement.setAttribute('data-theme', theme);
            }

            // --- DATA PROCESSING & RENDER PIPELINE ---
            render() {
                const searchTerm = this.searchInput.value.toLowerCase();
                const statusFilter = this.filterStatus.value;

                // 1. Filter Data
                let filtered = this.clients.filter(client => {
                    const matchesSearch = client.name.toLowerCase().includes(searchTerm) || 
                                          client.id.toLowerCase().includes(searchTerm) ||
                                          client.email.toLowerCase().includes(searchTerm);
                    const matchesStatus = statusFilter === 'All' || client.status === statusFilter;
                    return matchesSearch && matchesStatus;
                });

                // 2. Sort Data
                filtered.sort((a, b) => {
                    let valA = a[this.sortCol] || '';
                    let valB = b[this.sortCol] || '';
                    
                    if (this.sortCol === 'name' || this.sortCol === 'email' || this.sortCol === 'executive' || this.sortCol === 'source') {
                        valA = valA.toString().toLowerCase();
                        valB = valB.toString().toLowerCase();
                    }

                    if (valA < valB) return this.sortAsc ? -1 : 1;
                    if (valA > valB) return this.sortAsc ? 1 : -1;
                    return 0;
                });

                this.updateSortUI();

                // 3. Paginate Data
                const totalItems = filtered.length;
                const totalPages = Math.ceil(totalItems / this.itemsPerPage) || 1;
                if (this.currentPage > totalPages) this.currentPage = totalPages;

                const startIndex = (this.currentPage - 1) * this.itemsPerPage;
                const endIndex = startIndex + this.itemsPerPage;
                const paginatedData = filtered.slice(startIndex, endIndex);

                // 4. Render Views
                this.renderTable(paginatedData);
                this.renderPagination(totalItems, startIndex, endIndex);
                this.renderStats();
            }

            updateSortUI() {
                this.tableHeaders.forEach(th => {
                    th.classList.remove('asc', 'desc');
                    if (th.getAttribute('data-sort') === this.sortCol) {
                        th.classList.add(this.sortAsc ? 'asc' : 'desc');
                    }
                });
            }

            renderTable(data) {
                this.tableBody.innerHTML = '';

                if (data.length === 0) {
                    this.emptyState.style.display = 'flex';
                    this.clientTable.style.display = 'none';
                    this.paginationContainer.style.display = 'none';
                    return;
                }

                this.emptyState.style.display = 'none';
                this.clientTable.style.display = 'table';
                this.paginationContainer.style.display = 'flex';

                data.forEach(client => {
                    const tr = document.createElement('tr');
                    
                    // INR Currency Formatting
                    const valueFormatted = new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        maximumFractionDigits: 0
                    }).format(client.value);

                    const badgeClass = client.status.toLowerCase().replace(' ', '-');

                    tr.innerHTML = `
                        <td>
                            <div class="client-info">
                                <span class="client-name">${this.escapeHTML(client.name)}</span>
                                <span class="client-id">${client.id}</span>
                            </div>
                        </td>
                        <td>
                            <div class="client-info">
                                <span style="font-size: 13px; color: var(--text-primary);">${this.escapeHTML(client.phone)}</span>
                                <span style="font-size: 12px; color: var(--text-muted);">${this.escapeHTML(client.email)}</span>
                            </div>
                        </td>
                        <td>${this.escapeHTML(client.source)}</td>
                        <td><span class="badge ${badgeClass}">${client.status}</span></td>
                        <td>${this.escapeHTML(client.executive)}</td>
                        <td style="font-weight: 600;">${valueFormatted}</td>
                        <td>
                            <div class="action-btns">
                                <button class="action-btn edit" data-id="${client.id}" title="Edit">
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                <button class="action-btn delete" data-id="${client.id}" title="Delete">
                                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </div>
                        </td>
                    `;
                    this.tableBody.appendChild(tr);
                });
            }

            renderPagination(totalItems, startIndex, endIndex) {
                const actualEnd = Math.min(endIndex, totalItems);
                const actualStart = totalItems === 0 ? 0 : startIndex + 1;
                
                this.paginationInfo.textContent = `Showing ${actualStart} to ${actualEnd} of ${totalItems} entries`;
                
                this.prevPageBtn.disabled = this.currentPage === 1;
                this.nextPageBtn.disabled = endIndex >= totalItems;
            }

            renderStats() {
                const total = this.clients.length;
                let active = 0;
                let closed = 0;
                let revenue = 0;

                this.clients.forEach(client => {
                    if (['New', 'Contacted', 'Qualified', 'Proposal'].includes(client.status)) {
                        active++;
                    }
                    if (client.status === 'Won') {
                        closed++;
                        revenue += parseFloat(client.value);
                    }
                });

                this.statTotal.textContent = total;
                this.statActive.textContent = active;
                this.statClosed.textContent = closed;
                this.statRevenue.textContent = new Intl.NumberFormat('en-IN', {
                    style: 'currency',
                    currency: 'INR',
                    maximumFractionDigits: 0
                }).format(revenue);
            }

            // --- FORM & MODAL LOGIC ---
            openFormModal(id = null) {
                this.clientForm.reset();
                this.editingId = id;

                if (id) {
                    this.modalTitle.textContent = 'Edit Client';
                    const client = this.clients.find(c => c.id === id);
                    if (client) {
                        document.getElementById('clientId').value = client.id;
                        document.getElementById('clientName').value = client.name;
                        document.getElementById('clientPhone').value = client.phone;
                        document.getElementById('clientEmail').value = client.email;
                        document.getElementById('clientSource').value = client.source;
                        document.getElementById('clientStatus').value = client.status;
                        document.getElementById('clientExecutive').value = client.executive;
                        document.getElementById('clientValue').value = client.value;
                    }
                } else {
                    this.modalTitle.textContent = 'Add New Client';
                    document.getElementById('clientId').value = '';
                }

                this.clientModal.classList.add('active');
            }

            closeFormModal() {
                this.clientModal.classList.remove('active');
                this.editingId = null;
            }

            handleFormSubmit(e) {
                e.preventDefault();

                const clientData = {
                    id: document.getElementById('clientId').value || this.generateId(),
                    name: document.getElementById('clientName').value,
                    phone: document.getElementById('clientPhone').value,
                    email: document.getElementById('clientEmail').value,
                    source: document.getElementById('clientSource').value,
                    status: document.getElementById('clientStatus').value,
                    executive: document.getElementById('clientExecutive').value,
                    value: parseFloat(document.getElementById('clientValue').value),
                    created_at: Date.now() // Track creation time for sorting
                };

                if (this.editingId) {
                    const index = this.clients.findIndex(c => c.id === this.editingId);
                    if (index > -1) {
                        // preserve original creation time
                        clientData.created_at = this.clients[index].created_at || Date.now();
                        this.clients[index] = clientData;
                    }
                } else {
                    this.clients.unshift(clientData); 
                }

                this.saveData();
                this.render();
                this.closeFormModal();
            }

            // --- DELETE LOGIC ---
            requestDelete(id) {
                this.clientToDelete = id;
                this.confirmModal.classList.add('active');
            }

            closeConfirmModal() {
                this.confirmModal.classList.remove('active');
                this.clientToDelete = null;
            }

            executeDelete() {
                if (this.clientToDelete) {
                    this.clients = this.clients.filter(c => c.id !== this.clientToDelete);
                    this.saveData();
                    // Auto-adjust pagination if last item on page is deleted
                    const maxPage = Math.ceil(this.clients.length / this.itemsPerPage) || 1;
                    if (this.currentPage > maxPage) this.currentPage = maxPage;
                    this.render();
                }
                this.closeConfirmModal();
            }

            // --- UTILITIES ---
            escapeHTML(str) {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }
        }

        // Boot the Application
        document.addEventListener('DOMContentLoaded', () => {
            new ClientManager();
        });
    </script>
</body>
</html>