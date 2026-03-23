<?php
// help_support.php - Enterprise Knowledge Base (V4 - Hardened)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'include/db_connect.php'; 

$sidebarPath = __DIR__ . '/sidebars.php'; 
$headerPath = __DIR__ . '/header.php';
if (!file_exists($sidebarPath)) { $sidebarPath = __DIR__ . '/../sidebars.php'; }
if (!file_exists($headerPath)) { $headerPath = __DIR__ . '/../header.php'; }

$user_role = isset($_SESSION['role']) ? trim($_SESSION['role']) : 'Employee'; 
$user_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0);

// =========================================================================
// 🚀 ENTERPRISE AUTO-PATCHER (Analytics, Roles & Performance Indexes)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $check_cols = $conn->query("SHOW COLUMNS FROM `help_articles` LIKE 'allowed_roles'");
    if ($check_cols && $check_cols->num_rows == 0) {
        @$conn->query("ALTER TABLE `help_articles` 
            ADD COLUMN `allowed_roles` VARCHAR(255) DEFAULT 'All' AFTER `category_id`,
            ADD COLUMN `views` INT DEFAULT 0 AFTER `content`,
            ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `views`");
    }

    @$conn->query("CREATE TABLE IF NOT EXISTS `help_feedback` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `article_id` INT NOT NULL,
        `user_id` INT NOT NULL, `is_helpful` TINYINT(1) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    @$conn->query("CREATE TABLE IF NOT EXISTS `help_analytics` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `search_query` VARCHAR(255) NULL,
        `article_id` INT NULL, `user_id` INT NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Performance Indexing (Fail-safe creation)
    try {
        @$conn->query("CREATE INDEX idx_role ON help_articles(allowed_roles)");
        @$conn->query("CREATE INDEX idx_search ON help_analytics(search_query)");
        @$conn->query("CREATE INDEX idx_article ON help_feedback(article_id)");
    } catch (Exception $e) { /* Indexes likely already exist */ }
}

// =========================================================================
// 🚀 BACKGROUND AJAX HANDLERS (100% Prepared Statements - SQLi Safe)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean(); header('Content-Type: application/json');
    
    if ($_POST['action'] === 'submit_feedback') {
        $art_id = intval($_POST['article_id']);
        $helpful = intval($_POST['is_helpful']);
        $stmt = $conn->prepare("INSERT INTO help_feedback (article_id, user_id, is_helpful) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $art_id, $user_id, $helpful);
        $stmt->execute(); $stmt->close();
        echo json_encode(['status' => 'success']); exit;
    }
    
    if ($_POST['action'] === 'log_view') {
        $art_id = intval($_POST['article_id']);
        $stmt = $conn->prepare("INSERT INTO help_analytics (article_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $art_id, $user_id);
        $stmt->execute(); $stmt->close();
        
        $upd = $conn->prepare("UPDATE help_articles SET views = views + 1 WHERE id = ?");
        $upd->bind_param("i", $art_id);
        $upd->execute(); $upd->close();
        
        echo json_encode(['status' => 'success']); exit;
    }

    if ($_POST['action'] === 'log_search') {
        $query = trim($_POST['query']);
        if(!empty($query)) {
            $stmt = $conn->prepare("INSERT INTO help_analytics (search_query, user_id) VALUES (?, ?)");
            $stmt->bind_param("si", $query, $user_id);
            $stmt->execute(); $stmt->close();
        }
        echo json_encode(['status' => 'success']); exit;
    }
}

// =========================================================================
// 🚀 FETCH DATA (Strict Role Filtering & XSS Sanitization)
// =========================================================================
$kb_data = [];
$article_map = []; 
$total_articles = 0; 
// Whitelist of allowed HTML tags for the modal content (XSS Protection)
$allowed_html = '<p><br><b><strong><i><em><ul><ol><li><h3><h4><h5><a><span><div><img><hr><br>';

$cat_sql = "SELECT * FROM help_categories ORDER BY id ASC";
$cat_res = @$conn->query($cat_sql); 

if ($cat_res && $cat_res->num_rows > 0) {
    while($cat = $cat_res->fetch_assoc()) {
        $cat_id = $cat['id'];
        
        $art_sql = "SELECT id, title, content, updated_at FROM help_articles 
                    WHERE category_id = ? AND (allowed_roles = 'All' OR FIND_IN_SET(?, allowed_roles))";
        $stmt = $conn->prepare($art_sql);
        $stmt->bind_param("is", $cat_id, $user_role);
        $stmt->execute();
        $art_res = $stmt->get_result();
        
        $articles = [];
        while($row = $art_res->fetch_assoc()) {
            $clean_desc = strip_tags($row['content']);
            $short_desc = strlen($clean_desc) > 70 ? substr($clean_desc, 0, 70) . "..." : $clean_desc;
            if(empty($short_desc)) $short_desc = "Click to view detailed guide and instructions.";

            $word_count = str_word_count($clean_desc);
            $read_time = max(1, ceil($word_count / 200)); 

            $safe_title = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
            $date_updated = date('M d, Y', strtotime($row['updated_at']));
            
            $articles[] = [
                'id' => $row['id'],
                'title' => $safe_title,
                'desc' => $short_desc,
                'meta' => "$read_time min read • Updated $date_updated"
            ];
            
            // XSS Sanitization: Strip dangerous tags before serving to JS map
            $article_map[$row['id']] = strip_tags($row['content'], $allowed_html); 
            $total_articles++;
        }
        
        if (!empty($articles)) {
            $kb_data[] = [
                'title' => htmlspecialchars($cat['title'], ENT_QUOTES, 'UTF-8'),
                'icon' => $cat['icon'] ?? 'folder', 
                'articles' => $articles
            ];
        }
    }
}

// 4. FALLBACK UNIQUE CONTENT (Role-Based Help Center)
if (empty($kb_data)) {
    $raw_kb_data = [
        [
            'title' => 'Attendance, Leaves & Shifts',
            'icon' => 'calendar-clock',
            'allowed_roles' => ['All'],
            'articles' => [
                [
                    'id' => 1001,
                    'title' => 'How to punch in and track daily attendance', 
                    'desc' => 'Guide to logging your daily entry, exits, and tracking production hours.',
                    'content' => "<div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Your daily attendance and production hours are calculated based on your punch-in and punch-out times.</p></div><div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Steps to Punch In:</h3><ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'><li>Go to the <b>Attendance</b> module from the left sidebar.</li><li>Click the green <b>Punch In</b> button at the top of the dashboard.</li><li>When you take a break, click <b>Start Break</b>. Remember to click <b>End Break</b> when you return.</li><li>At the end of your shift, click <b>Punch Out</b> to log your total production hours.</li></ul></div>"
                ],
                [
                    'id' => 1002,
                    'title' => 'Applying for Sick or Casual Leaves', 
                    'desc' => 'How to check your leave balance and apply for time off in the portal.',
                    'content' => "<div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Employees can request Sick, Casual, or Loss of Pay leaves directly through the HRMS.</p></div><div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>How to Apply:</h3><ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'><li>Navigate to <b>Leave Management</b> > <b>Apply Leave</b>.</li><li>Select your <b>Leave Type</b> (e.g., Casual Leave) from the dropdown.</li><li>Select the <b>Start Date</b> and <b>End Date</b>.</li><li>Enter a valid reason for your absence and click <b>Submit</b>. Your TL and Manager will be notified.</li></ul></div>"
                ]
            ]
        ],
        [
            'title' => 'Manager Approvals & Overrides',
            'icon' => 'shield-check',
            'allowed_roles' => ['Manager', 'System Admin', 'CEO', 'HR'], 
            'articles' => [
                [
                    'id' => 2001,
                    'title' => 'Approving Team Leaves and Expense Claims', 
                    'desc' => 'Workflow for verifying and approving requests from your direct reports.',
                    'content' => "<div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>As a manager, you are required to review pending requests within 24 hours.</p></div><div><ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'><li>Navigate to the <b>Approvals</b> dashboard.</li><li>Review the attached proofs (for expenses) or shift coverage (for leaves).</li><li>Click <b>Approve</b> to forward to Accounts/HR, or <b>Reject</b> with a mandatory reason.</li></ul></div>"
                ]
            ]
        ],
        [
            'title' => 'Payroll & Compensation',
            'icon' => 'banknote',
            'allowed_roles' => ['All'],
            'articles' => [
                [
                    'id' => 3001,
                    'title' => 'How to download your monthly Payslip', 
                    'desc' => 'Requesting and downloading your official salary slips for specific months.',
                    'content' => "<div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Official payslips are generated after salary disbursement and can be downloaded as PDFs.</p></div><div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Download Instructions:</h3><ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'><li>Go to <b>Request Payslip</b> in the sidebar.</li><li>Select the <b>Month</b> and <b>Year</b> you need the payslip for.</li><li>Click <b>Generate/Download PDF</b>.</li><li>If a payslip is missing, use the 'Raise Request to Accounts' button.</li></ul></div>"
                ]
            ]
        ],
        [
            'title' => 'IT Support & Hardware Assets',
            'icon' => 'laptop',
            'allowed_roles' => ['All'],
            'articles' => [
                [
                    'id' => 4001,
                    'title' => 'Raising an IT Support Ticket', 
                    'desc' => 'How to report an issue, set a priority, and track the resolution status.',
                    'content' => "<div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>If you are facing hardware issues, software bugs, or network problems, raise a ticket for the IT Admin.</p></div><div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Steps to Raise a Ticket:</h3><ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'><li>Click on <b>Raise Ticket</b> at the bottom of your sidebar.</li><li>Enter a clear <b>Subject</b> and select the appropriate <b>Department</b> (e.g., IT Support).</li><li>Set the <b>Priority</b> (Low, Medium, High) based on urgency.</li><li>Provide a detailed description of the issue. You can also attach screenshots.</li><li>Click <b>Submit</b>. You can track updates in the 'My Tickets' section.</li></ul></div>"
                ]
            ]
        ]
    ];

    foreach($raw_kb_data as $cat) {
        if(in_array('All', $cat['allowed_roles']) || in_array($user_role, $cat['allowed_roles'])) {
            $arts = [];
            foreach($cat['articles'] as $art) {
                $word_count = str_word_count(strip_tags($art['content']));
                $read_time = max(1, ceil($word_count / 200)); 
                $arts[] = [
                    'id' => $art['id'],
                    'title' => $art['title'],
                    'desc' => $art['desc'],
                    'meta' => "$read_time min read • Updated " . date('M d, Y')
                ];
                $article_map[$art['id']] = strip_tags($art['content'], $allowed_html);
                $total_articles++;
            }
            $kb_data[] = [
                'title' => $cat['title'],
                'icon' => $cat['icon'],
                'articles' => $arts
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Help Center | WorkAck</title>
    
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bg-body: #f8fafc; 
            --text-main: #0f172a;
            --text-muted: #64748b;
            --sidebar-width: 95px;
            --primary: #1b5a5a; 
        }

        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: var(--bg-body); color: var(--text-main); margin: 0; line-height: 1.5; }
        /* ==========================================================
           UNIVERSAL RESPONSIVE LAYOUT 
           ========================================================== */
        .main-content, #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            box-sizing: border-box;
            padding: 30px; /* Adjust inner padding as needed */
            min-height: 100vh;
        }

        /* Desktop: Shifts content right when secondary sub-menu opens */
        .main-content.main-shifted, #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* Mobile & Tablet Adjustments */
        @media (max-width: 991px) {
            .main-content, #mainContent {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 80px 15px 30px !important; /* Top padding clears the hamburger menu */
            }
            
            /* Prevent shifting on mobile (menu floats over content instead) */
            .main-content.main-shifted, #mainContent.main-shifted {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        /* --- PREMIUM HERO SECTION --- */
        .search-hero {
            background: linear-gradient(135deg, var(--primary) 0%, #0d2e2e 100%);
            margin: 0 -40px 40px -40px; padding: 80px 20px 100px;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center; position: relative; overflow: hidden;
        }
        .search-hero::after { content: ''; position: absolute; inset: 0; opacity: 0.1; background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 32px 32px; }

        .hero-title { color: white; font-size: 36px; font-weight: 800; margin: 0 0 10px 0; letter-spacing: -0.5px; z-index: 1;}
        .hero-subtitle { color: #cbd5e1; font-size: 16px; margin: 0 0 30px 0; z-index: 1;}

        .search-wrapper { position: relative; width: 100%; max-width: 650px; z-index: 1;}
        .search-wrapper svg { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 22px; height: 22px; pointer-events: none; z-index: 2; }
        
        .search-wrapper input {
            width: 100%; padding: 20px 20px 20px 56px; font-size: 16px; font-weight: 500; color: #0f172a;
            background-color: #ffffff; border: 2px solid transparent; border-radius: 16px; 
            outline: none; transition: all 0.3s ease; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .search-wrapper input::placeholder { color: #94a3b8; }
        .search-wrapper input:focus { border-color: #38bdf8; box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.2), 0 10px 25px rgba(0,0,0,0.1); }
        
        .kbd-shortcut {
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            background: #f1f5f9; color: #64748b; font-size: 12px; font-weight: 700; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; pointer-events: none;
        }

        /* --- QUICK ACTIONS --- */
        .quick-actions-container {
            max-width: 900px; margin: -70px auto 40px; position: relative; z-index: 10;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 0 20px;
        }
        .quick-action-card {
            background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: var(--text-main);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px;
            transition: all 0.2s ease; border: 1px solid #f1f5f9;
        }
        .quick-action-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); border-color: #e2e8f0;}
        .qa-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* Container */
        .support-container { max-width: 860px; margin: 0 auto; }
        .category-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;}
        .category-header h2 { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px;}
        .category-header .article-count { font-size: 13px; font-weight: 600; color: var(--text-muted); background-color: #f1f5f9; padding: 4px 12px; border-radius: 20px; }

        /* Cards */
        .category-card { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden;}
        .article-list { display: flex; flex-direction: column; }
        .article-item {
            text-decoration: none; display: flex; justify-content: space-between; align-items: center; 
            padding: 20px 24px; border-bottom: 1px solid #f1f5f9; background: transparent; border-left: none; border-right: none; border-top: none;
            cursor: pointer; text-align: left; width: 100%; transition: background-color 0.2s ease; box-sizing: border-box;
        }
        .article-item:last-child { border-bottom: none; }
        .article-item:hover { background-color: #f8fafc; }

        .article-content { display: flex; flex-direction: column; gap: 4px; padding-right: 20px; }
        .article-content h3 { font-size: 15px; font-weight: 600; color: #1e293b; margin: 0; transition: color 0.2s ease; }
        .article-item:hover .article-content h3 { color: var(--primary); }
        .article-content p { font-size: 13px; color: var(--text-muted); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 600px;}
        .article-meta { font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; display: flex; align-items: center; gap: 6px;}

        .article-icon { color: #94a3b8; flex-shrink: 0; transition: transform 0.2s ease, color 0.2s ease; }
        .article-item:hover .article-icon { color: var(--primary); transform: translateX(4px); }

        mark.highlight { background: #fef08a; color: #b45309; padding: 0 2px; border-radius: 3px; font-weight: inherit;}

        .empty-state { text-align: center; padding: 60px 20px; display: none; }
        .empty-state svg { width: 64px; height: 64px; color: #cbd5e1; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; font-weight: 700; color: #334155; margin: 0 0 8px; }
        .empty-state p { font-size: 14px; color: #64748b; margin: 0; }

        /* --- MODAL STYLES --- */
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(20px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

        #contentModal {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 2000; justify-content: center; align-items: center;
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); padding: 20px;
        }
        .modal-box {
            background: white; width: 100%; max-width: 700px; border-radius: 16px; padding: 0; position: relative; max-height: 90vh; 
            display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .modal-header { padding: 30px 40px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start; }
        .breadcrumb { font-size: 12px; font-weight: 600; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;}
        #topicTitle { font-size: 24px; font-weight: 800; color: #0f172a; margin:0; letter-spacing: -0.5px; line-height: 1.3; padding-right: 40px;}
        
        .header-actions { position: absolute; top: 24px; right: 24px; display: flex; gap: 8px;}
        .icon-btn { background: #f1f5f9; border: none; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: 0.2s; flex-shrink: 0;}
        .icon-btn:hover { background: #e2e8f0; color: #0f172a; }

        .modal-body { padding: 30px 40px; overflow-y: auto; color: #334155; font-size: 15px; line-height: 1.7; flex-grow: 1; }
        .modal-body h3 { color: #0f172a; margin-top: 0;}
        
        .loading-skeleton { width: 100%; height: 20px; background: #f1f5f9; border-radius: 4px; margin-bottom: 12px; animation: pulse 1.5s infinite ease-in-out; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }

        .modal-footer { padding: 25px 40px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 16px 16px; display: flex; justify-content: space-between; align-items: center; }
        
        .feedback-section { display: flex; align-items: center; gap: 15px; }
        .feedback-section span { font-size: 13px; font-weight: 600; color: var(--text-muted); }
        .feedback-btn { background: white; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; color: #475569; transition: 0.2s;}
        .feedback-btn:hover { border-color: var(--primary); color: var(--primary); background: #f0fdfa;}

        .support-cta { font-size: 13px; font-weight: 600; color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;}
        .support-cta:hover { color: var(--primary); }

        /* 🚀 --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 991px) {
            .main-content { margin-left: 0; padding: 0 0 40px 0; }
            .search-hero { margin: 0 0 40px 0; border-radius: 0 0 20px 20px; padding: 80px 20px 80px;} 
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 28px; }
            .hero-subtitle { font-size: 14px; }
            .search-wrapper input { padding: 16px 16px 16px 48px; font-size: 15px; }
            .search-wrapper svg { left: 16px; width: 18px; height: 18px; }
            .kbd-shortcut { display: none; }
            
            .quick-actions-container { margin-top: -40px; }
            .support-container { padding: 0 15px; }
            .category-header h2 { font-size: 18px; }
            .article-item { padding: 16px; }
            .article-content p { white-space: normal; -webkit-line-clamp: 2; display: -webkit-box; -webkit-box-orient: vertical;}
            
            .modal-box { height: 100vh; max-height: 100vh; border-radius: 0; } 
            .modal-header { padding: 20px; }
            .modal-body { padding: 20px; }
            .modal-footer { padding: 20px; flex-direction: column; gap: 20px; align-items: stretch; text-align: center; }
            .feedback-section { justify-content: center; }
            .support-cta { justify-content: center; }
        }
    </style>
</head>
<body>

    <?php if (file_exists($headerPath)) include($headerPath); ?>
    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <main id="mainContent" class="main-content">
        
        <div class="search-hero">
            <h1 class="hero-title">How can we help you today?</h1>
            <p class="hero-subtitle">Search our knowledge base or browse categories below.</p>
            
            <div class="search-wrapper">
                <i data-lucide="search"></i>
                <input type="text" id="searchInput" placeholder="Search for articles, guides, or keywords...">
                <span class="kbd-shortcut">/</span>
            </div>
        </div>

        <div class="quick-actions-container">
            <a href="employee/leave_request.php" class="quick-action-card">
                <div class="qa-icon" style="background: #fef3c7; color: #d97706;"><i data-lucide="calendar-plus"></i></div>
                <div style="font-size: 14px; font-weight: 700;">Apply Leave</div>
            </a>
            <a href="payslip_request.php" class="quick-action-card">
                <div class="qa-icon" style="background: #dcfce7; color: #16a34a;"><i data-lucide="file-text"></i></div>
                <div style="font-size: 14px; font-weight: 700;">View Payslip</div>
            </a>
            <a href="ticketraise_form.php" class="quick-action-card">
                <div class="qa-icon" style="background: #e0f2fe; color: #0284c7;"><i data-lucide="life-buoy"></i></div>
                <div style="font-size: 14px; font-weight: 700;">Raise IT Ticket</div>
            </a>
            <a href="team_chat.php" class="quick-action-card">
                <div class="qa-icon" style="background: #f3e8ff; color: #7e22ce;"><i data-lucide="message-circle"></i></div>
                <div style="font-size: 14px; font-weight: 700;">Message HR</div>
            </a>
        </div>

        <div class="support-container">
            
            <div class="category-header">
                <h2><i data-lucide="book-open" style="color: var(--primary);"></i> <span id="pageTitle">Knowledge Base</span></h2>
                <span class="article-count" id="articleCount"><?php echo $total_articles; ?> Articles</span>
            </div>

            <div id="articlesContainer">
                <?php foreach ($kb_data as $category): ?>
                    <div class="category-card">
                        <div style="padding: 20px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="<?= $category['icon'] ?>" style="color: #64748b; width: 20px; height: 20px;"></i>
                            <h2 style="font-size: 16px; font-weight: 700; margin: 0; color: #334155;"><?php echo $category['title']; ?></h2>
                        </div>
                        
                        <div class="article-list">
                            <?php foreach ($category['articles'] as $article): ?>
                                <button class="article-item" onclick="openArticle(<?php echo $article['id']; ?>, '<?php echo rawurlencode($article['title']); ?>', '<?php echo htmlspecialchars($category['title'], ENT_QUOTES); ?>')">
                                    <div class="article-content">
                                        <h3 class="searchable-title"><?php echo $article['title']; ?></h3>
                                        <p class="searchable-desc"><?php echo $article['desc']; ?></p>
                                        <div class="article-meta"><i data-lucide="clock" style="width:12px;height:12px;"></i> <?php echo $article['meta']; ?></div>
                                    </div>
                                    <i data-lucide="chevron-right" class="article-icon"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="empty-state" id="emptyState">
                <i data-lucide="search-x" style="width: 64px; height: 64px; color: #cbd5e1; margin-bottom: 16px; display: inline-block;"></i>
                <h3>No matching articles found</h3>
                <p>We couldn't find anything matching your search. Try different keywords or <a href="ticketraise_form.php" style="color: var(--primary); text-decoration: underline;">raise a support ticket</a>.</p>
            </div>

        </div>
    </div>

    <div id="contentModal">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <div class="breadcrumb" id="modalBreadcrumb">Help Center > Category</div>
                    <h2 id="topicTitle">Article Title</h2>
                </div>
                <div class="header-actions">
                    <button class="icon-btn" onclick="copyArticleLink()" title="Copy Link"><i data-lucide="link"></i></button>
                    <button class="icon-btn" onclick="closeModal()" title="Close"><i data-lucide="x"></i></button>
                </div>
            </div>
            
            <div class="modal-body" id="topicDescription">
                </div>
            
            <div class="modal-footer">
                <div class="feedback-section">
                    <span>Was this article helpful?</span>
                    <button class="feedback-btn" onclick="submitFeedback(1)"><i data-lucide="thumbs-up"></i> Yes</button>
                    <button class="feedback-btn" onclick="submitFeedback(0)"><i data-lucide="thumbs-down"></i> No</button>
                </div>
                <a href="ticketraise_form.php" class="support-cta"><i data-lucide="headphones"></i> Still need help? Contact Support</a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const articleData = <?php echo json_encode($article_map); ?>;
        let currentOpenArticleId = null; 
        
        // --- DEEP LINKING (URL Parameters) ---
        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            const targetArticle = urlParams.get('article');
            const targetId = urlParams.get('id');
            if(targetArticle && targetId) {
                openArticle(targetId, encodeURIComponent(targetArticle), 'Knowledge Base');
            }
        });

        // --- KEYBOARD SHORTCUTS ---
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('contentModal').style.display === 'flex') { closeModal(); }
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault(); document.getElementById('searchInput').focus();
            }
        });

        // --- MODAL LOGIC WITH BACKGROUND ANALYTICS (ID-BASED) ---
        function openArticle(id, encodedTitle, categoryName) {
            const title = decodeURIComponent(encodedTitle);
            currentOpenArticleId = id;
            
            const modal = document.getElementById('contentModal');
            const titleEl = document.getElementById('topicTitle');
            const descEl = document.getElementById('topicDescription');
            const breadcrumbEl = document.getElementById('modalBreadcrumb');

            // Deep Linking with ID
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?id=' + id + '&article=' + encodeURIComponent(title);
            window.history.pushState({path:newUrl}, '', newUrl);

            breadcrumbEl.innerHTML = `<i data-lucide="folder" style="width:14px;height:14px;"></i> Help Center > ${categoryName}`;
            titleEl.innerText = title;
            lucide.createIcons();
            
            descEl.innerHTML = `
                <div class="loading-skeleton" style="width: 80%;"></div><div class="loading-skeleton" style="width: 100%;"></div>
                <div class="loading-skeleton" style="width: 90%;"></div><div class="loading-skeleton" style="width: 60%; margin-bottom: 30px;"></div>
                <div class="loading-skeleton" style="width: 100%;"></div><div class="loading-skeleton" style="width: 85%;"></div>
            `;
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; 

            // Log View to Database Silently (O(1) using ID)
            const fd = new FormData();
            fd.append('action', 'log_view');
            fd.append('article_id', id);
            fetch('', { method: 'POST', body: fd }).catch(() => {}); 

            // Inject Sanitized HTML
            setTimeout(() => {
                const content = articleData[id] || "<p>Content could not be loaded.</p>";
                descEl.innerHTML = content; 
            }, 300);
        }

        function closeModal() {
            document.getElementById('contentModal').style.display = 'none'; 
            document.body.style.overflow = 'auto'; 
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path:cleanUrl}, '', cleanUrl);
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('contentModal')) closeModal();
        }

        function copyArticleLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                Swal.fire({ icon: 'success', title: 'Link Copied!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            });
        }

        // --- TRUE DATABASE FEEDBACK LOOP (ID-BASED) ---
        function submitFeedback(isHelpful) {
            if(!currentOpenArticleId) return;

            const fd = new FormData();
            fd.append('action', 'submit_feedback');
            fd.append('article_id', currentOpenArticleId);
            fd.append('is_helpful', isHelpful);
            
            fetch('', { method: 'POST', body: fd })
            .then(() => {
                Swal.fire({ icon: 'success', title: 'Thank you!', text: isHelpful ? 'Glad we could help!' : 'We will use your feedback to improve this article.', toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000 });
                closeModal();
            });
        }

        // --- HIGHLIGHT SEARCH & ZERO-RESULT ANALYTICS ---
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;

        function escapeRegExp(string) { return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimeout); 
            
            let filterText = this.value.toLowerCase().trim();
            let cards = document.querySelectorAll('.category-card');
            let visibleCount = 0;
            let searchRegex = filterText.length > 0 ? new RegExp('(' + escapeRegExp(filterText) + ')', 'gi') : null;

            cards.forEach(card => {
                let articles = card.querySelectorAll('.article-item');
                let hasVisibleArticle = false;
                
                articles.forEach(article => {
                    let titleEl = article.querySelector('.searchable-title');
                    let descEl = article.querySelector('.searchable-desc');
                    
                    let rawTitle = titleEl.textContent;
                    let rawDesc = descEl.textContent;
                    
                    let titleMatch = rawTitle.toLowerCase().includes(filterText);
                    let descMatch = rawDesc.toLowerCase().includes(filterText);
                    
                    if (filterText === '' || titleMatch || descMatch) {
                        article.style.display = 'flex'; hasVisibleArticle = true; visibleCount++;
                        if (filterText !== '') {
                            if (titleMatch) titleEl.innerHTML = rawTitle.replace(searchRegex, '<mark class="highlight">$1</mark>');
                            if (descMatch) descEl.innerHTML = rawDesc.replace(searchRegex, '<mark class="highlight">$1</mark>');
                        } else {
                            titleEl.innerHTML = rawTitle; descEl.innerHTML = rawDesc;
                        }
                    } else {
                        article.style.display = 'none'; titleEl.innerHTML = rawTitle; descEl.innerHTML = rawDesc;
                    }
                });
                card.style.display = hasVisibleArticle ? 'block' : 'none';
            });

            if (filterText.length > 0) {
                document.getElementById('pageTitle').innerHTML = `Search Results`;
                document.getElementById('articleCount').innerHTML = `${visibleCount} found`;
                if(visibleCount === 0) {
                    document.getElementById('emptyState').style.display = 'block';
                    document.getElementById('articlesContainer').style.display = 'none';
                } else {
                    document.getElementById('emptyState').style.display = 'none';
                    document.getElementById('articlesContainer').style.display = 'block';
                }
                
                searchTimeout = setTimeout(() => {
                    const fd = new FormData(); fd.append('action', 'log_search'); fd.append('query', filterText);
                    fetch('', { method: 'POST', body: fd }).catch(() => {}); 
                }, 1500);

            } else {
                document.getElementById('pageTitle').innerHTML = "Knowledge Base";
                document.getElementById('articleCount').innerHTML = `<?php echo $total_articles; ?> Articles`;
                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('articlesContainer').style.display = 'block';
            }
        });
    </script>
</body>
</html>