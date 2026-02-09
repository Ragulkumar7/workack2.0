<?php
// support.php - Help & Support / Knowledgebase Page

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST SIDEBAR INCLUDE
$sidebarPath = __DIR__ . '/sidebars.php'; 
if (!file_exists($sidebarPath)) {
    $sidebarPath = __DIR__ . '/../sidebars.php'; 
}

// 3. LOGIN CHECK (Optional - Uncomment to enforce)
if (!isset($_SESSION['user_id'])) { 
    // header("Location: index.php"); 
    // exit(); 
}

// 4. MOCK DATA FOR KNOWLEDGEBASE
// This structure allows you to easily add more categories/articles from a database later.
$kb_categories = [
    [
        "title" => "Introduction to HRMS",
        "count" => "06",
        "articles" => [
            "What is an HRMS and Why is it Essential?",
            "The Key Features of an HRMS Explained",
            "How HRMS Helps Automate HR Tasks",
            "HRMS Terminology : A Beginner's Guide",
            "Cloud vs On-Premise HRMS vs Hybrid",
            "Getting Started: First Login Guide"
        ]
    ],
    [
        "title" => "Employee Self-Service (ESS)",
        "count" => "12",
        "articles" => [
            "How to view & update your personal profile",
            "Steps to Apply for Leave via the Portal",
            "How to access and download your payslips",
            "Submitting & Tracking Expense Claims",
            "How to track your attendance and work hours",
            "Resetting your password securely"
        ]
    ],
    [
        "title" => "Manager Self-Service (MSS)",
        "count" => "15",
        "articles" => [
            "How to Approve or Reject Employee Requests",
            "Viewing and managing team attendance",
            "How to conduct performance reviews",
            "Approving expense claims for your team",
            "How to update & view team's work shifts",
            "Generating team productivity reports"
        ]
    ],
    [
        "title" => "Payroll Management",
        "count" => "08",
        "articles" => [
            "Understanding your Salary Structure",
            "How Tax Deductions are calculated",
            "Viewing Year-to-Date (YTD) Earnings",
            "Direct Deposit configuration steps"
        ]
    ],
    [
        "title" => "Attendance & Time Tracking",
        "count" => "05",
        "articles" => [
            "How to use the Biometric Punch System",
            "Correcting a missed punch entry",
            "Understanding Overtime Policies",
            "Shift Swapping Guidelines"
        ]
    ],
    [
        "title" => "Leave Management",
        "count" => "06",
        "articles" => [
            "Leave Types and Entitlements",
            "How to cancel an approved leave",
            "Maternity & Paternity Leave Policies",
            "Encashment of unused leaves"
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - HRMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary: #ea580c; 
            --primary-hover: #c2410c;
            --bg-body: #f8f9fa;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --white: #ffffff;
            --sidebar-width: 95px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            margin: 0; padding: 0;
            color: var(--text-main);
        }

        /* --- LAYOUT --- */
        .main-content {
            margin-left: var(--primary-sidebar-width, 95px);
            padding: 24px 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* --- HEADER --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; flex-wrap: wrap; gap: 15px;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: #1e293b; }
        .breadcrumb { display: flex; align-items: center; font-size: 13px; color: var(--text-muted); gap: 8px; margin-top: 5px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; font-size: 14px; font-weight: 500;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--white); color: var(--text-main);
            cursor: pointer; transition: 0.2s; text-decoration: none; gap: 8px;
        }
        .btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .btn-primary { background-color: var(--primary); color: white; border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-hover); }

        /* --- TOOLBAR CARD --- */
        .toolbar-card {
            background: white; border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 24px; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .toolbar-title { font-size: 16px; font-weight: 700; color: #334155; }

        /* Inputs in Toolbar */
        .input-group {
            display: flex; align-items: center; border: 1px solid var(--border);
            border-radius: 6px; padding: 8px 12px; background: white; 
            min-width: 200px;
        }
        .input-group i { color: #9ca3af; width: 16px; height: 16px; margin-right: 8px; }
        .input-group input, .input-group select {
            border: none; outline: none; width: 100%; font-size: 13px; color: var(--text-main); background: transparent;
        }

        /* --- KB CARDS GRID --- */
        .kb-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* 3 Columns on Desktop */
            gap: 24px;
        }

        .kb-card {
            background: white; border: 1px solid var(--border);
            border-radius: 12px; padding: 24px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex; flex-direction: column;
        }
        .kb-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }

        .kb-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .kb-icon { color: var(--primary); width: 20px; height: 20px; }
        .kb-title { font-size: 15px; font-weight: 600; color: #1e293b; }
        .kb-count { font-size: 14px; font-weight: 600; color: var(--primary); }

        .kb-list { list-style: none; padding: 0; margin: 0; }
        .kb-item { 
            display: flex; align-items: flex-start; gap: 10px; 
            margin-bottom: 12px; font-size: 13px; color: #475569; 
            cursor: pointer; transition: color 0.2s;
            line-height: 1.5;
        }
        .kb-item:hover { color: var(--primary); }
        .kb-item-icon { width: 14px; height: 14px; margin-top: 3px; color: #94a3b8; flex-shrink: 0; }
        .kb-item:hover .kb-item-icon { color: var(--primary); }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .kb-grid { grid-template-columns: repeat(2, 1fr); } /* 2 Cols Tablet */
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; justify-content: space-between; }
            .toolbar-card { flex-direction: column; align-items: flex-start; }
            .input-group { width: 100%; }
            .kb-grid { grid-template-columns: 1fr; } /* 1 Col Mobile */
        }
    </style>
</head>
<body>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <div class="main-content" id="mainContent">
        
        <div class="page-header">
            <div>
                <h1>Help & Support</h1>
                <div class="breadcrumb">
                    <i data-lucide="home" style="width:14px; height:14px;"></i>
                    <span>/</span>
                    <span>Administration</span>
                    <span>/</span>
                    <span style="font-weight:600; color:#111827;">Knowledgebase</span>
                </div>
            </div>
            <div class="header-actions">
                <a href="ticketraise.php?view=dashboard" class="btn btn-primary">
                    <i data-lucide="life-buoy" style="width:16px;"></i> Contact Support
                </a>
            </div>
        </div>

        <div class="toolbar-card">
            <span class="toolbar-title">Knowledgebase</span>
            
            <div style="display:flex; gap:10px; flex-wrap:wrap; flex:1; justify-content:flex-end;">
                <div class="input-group" style="flex:1; max-width:300px;">
                    <i data-lucide="search"></i>
                    <input type="text" id="searchInput" placeholder="Search help articles..." onkeyup="filterArticles()">
                </div>

                <div class="input-group" style="width:auto;">
                    <i data-lucide="calendar"></i>
                    <input type="text" value="02/03/2026 - 02/09/2026" readonly style="cursor:default; width: 160px;">
                </div>

                <div class="input-group" style="width:auto;">
                    <span style="font-size:12px; color:#6b7280; white-space:nowrap; margin-right:5px;">Sort By:</span>
                    <select>
                        <option>Last 7 Days</option>
                        <option>Most Viewed</option>
                        <option>Recently Added</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="kb-grid" id="kbGrid">
            <?php foreach ($kb_categories as $category): ?>
            <div class="kb-card">
                <div class="kb-header">
                    <i data-lucide="folder" class="kb-icon"></i>
                    <span class="kb-title"><?php echo $category['title']; ?></span>
                    <span class="kb-count">( <?php echo $category['count']; ?> )</span>
                </div>
                <ul class="kb-list">
                    <?php foreach ($category['articles'] as $article): ?>
                    <li class="kb-item">
                        <i data-lucide="file-text" class="kb-item-icon"></i>
                        <span class="article-text"><?php echo $article; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script>
        // Initialize Icons
        lucide.createIcons();

        // --- Live Search Functionality ---
        function filterArticles() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let cards = document.getElementsByClassName('kb-card');

            for (let i = 0; i < cards.length; i++) {
                let listItems = cards[i].getElementsByClassName('kb-item');
                let hasMatch = false;

                // Loop through articles in each card
                for (let j = 0; j < listItems.length; j++) {
                    let text = listItems[j].querySelector('.article-text').innerText.toLowerCase();
                    if (text.includes(input)) {
                        listItems[j].style.display = ""; // Show matching item
                        hasMatch = true;
                    } else {
                        listItems[j].style.display = "none"; // Hide non-matching item
                    }
                }

                // If input is empty, show everything. 
                // If input exists, show card ONLY if it has matching articles.
                if (input === "") {
                    cards[i].style.display = "";
                    for(let j=0; j<listItems.length; j++) listItems[j].style.display = "";
                } else {
                    cards[i].style.display = hasMatch ? "" : "none";
                }
            }
        }
    </script>
</body>
</html>