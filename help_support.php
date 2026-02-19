<?php
// support.php - Help & Support / Knowledgebase Page

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. ROBUST SIDEBAR & HEADER INCLUDE
$sidebarPath = __DIR__ . '/sidebars.php'; 
$headerPath = __DIR__ . '/header.php';

if (!file_exists($sidebarPath)) { $sidebarPath = __DIR__ . '/../sidebars.php'; }
if (!file_exists($headerPath)) { $headerPath = __DIR__ . '/../header.php'; }

// 4. MOCK DATA FOR KNOWLEDGEBASE
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
            --primary: #1b5a5a; 
            --primary-hover: #144545;
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
            color: var(--text-main);
            margin: 0;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 32px;
            min-height: 100vh;
        }

        .kb-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
            gap: 24px; 
            margin-top: 20px;
        }

        .kb-card {
            background: white; 
            border: 1px solid var(--border);
            border-radius: 12px; 
            padding: 24px;
            transition: all 0.2s ease;
        }
        .kb-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 10px 20px -5px rgba(27, 90, 90, 0.1); 
            border-color: var(--primary);
        }

        .kb-item { 
            display: flex; 
            align-items: flex-start; 
            gap: 10px; 
            margin-bottom: 12px; 
            font-size: 13.5px; 
            color: #475569; 
            cursor: pointer; 
            transition: color 0.2s;
        }
        .kb-item:hover { color: var(--primary); }

        /* MODAL CSS */
        #contentModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
            padding: 20px;
        }
        .modal-box {
            background: white;
            width: 100%;
            max-width: 650px;
            border-radius: 16px;
            padding: 40px;
            position: relative;
            max-height: 85vh;
            overflow-y: auto;
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
        }
        .close-btn:hover { color: var(--primary); }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
    </style>
</head>
<body>

    <?php if (file_exists($headerPath)) include($headerPath); ?>

    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <div class="main-content">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-800">Help & Support</h1>
            <p class="text-slate-500 mt-1">Find guides, tutorials, and answers to your HRMS questions.</p>
        </div>

        <div class="kb-grid">
            <?php foreach ($kb_categories as $category): ?>
            <div class="kb-card">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-2 rounded-lg bg-teal-50">
                        <i data-lucide="folder" class="text-[#1b5a5a] w-5 h-5"></i>
                    </div>
                    <span class="font-bold text-slate-800 text-lg"><?php echo $category['title']; ?></span>
                </div>
                <ul class="list-none p-0">
                    <?php foreach ($category['articles'] as $article): ?>
                    <li class="kb-item" onclick="showTopicContent('<?php echo addslashes($article); ?>')">
                        <i data-lucide="file-text" class="w-4 h-4 mt-1 text-slate-400"></i>
                        <span><?php echo $article; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="contentModal">
        <div class="modal-box shadow-2xl">
            <span class="close-btn" onclick="closeModal()"><i data-lucide="x"></i></span>
            <div id="modalInner">
                <h2 id="topicTitle" class="text-2xl font-bold text-[#1b5a5a] mb-4"></h2>
                <hr class="mb-6 border-slate-100">
                <div id="topicDescription" class="text-slate-600 leading-relaxed space-y-4 text-lg">
                    </div>
                <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
                    <button onclick="closeModal()" class="btn-primary">Got it, thanks!</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        // Topic Database
        const articleData = {
            "What is an HRMS and Why is it Essential?": "An HRMS (Human Resource Management System) is a suite of software used to manage internal HR functions. From employee data to payroll, recruitment, and benefits, it centralizes all employee information in one secure location.",
            "The Key Features of an HRMS Explained": "Key features include Employee Information Management, Payroll processing, Time and Attendance tracking, Recruitment/ATS, and Performance Management systems.",
            "How HRMS Helps Automate HR Tasks": "Automation reduces manual entry in payroll, tracks leave balances automatically, and sends notifications for document expirations or performance reviews.",
            "How to view & update your personal profile": "Navigate to the 'Profile' section from the sidebar. Click 'Edit', update your contact details or address, and click 'Save'. Some changes may require HR approval.",
            "Steps to Apply for Leave via the Portal": "1. Go to Leave Management. 2. Select 'Apply Leave'. 3. Choose Leave Type (Sick/Annual). 4. Pick dates and submit for Manager approval.",
            "How to access and download your payslips": "Visit the 'Payroll' module. Select 'Payslips'. Choose the specific month and year, then click 'Download PDF' to save it to your device.",
            "How to Approve or Reject Employee Requests": "As a manager, go to your 'Inbox' or 'Approval Center'. Review the request details and click 'Approve' or 'Reject' with an optional comment.",
            "Understanding your Salary Structure": "Your salary is composed of Basic Pay, HRA, Special Allowance, and Deductions like PF and Professional Tax. Details are found in your digital contract.",
            "Leave Types and Entitlements": "Employees are entitled to 12 Sick Leaves, 15 Annual Leaves, and Public Holidays as per the company calendar. Check your balance in the Leave module.",
            "How to use the Biometric Punch System": "Ensure your fingerprint or face is registered. Simply scan at the entrance/exit. The data syncs to the HRMS every 30 minutes.",
            "Resetting your password securely": "Click 'Forgot Password' on the login screen. An OTP will be sent to your registered email. Enter the OTP and create a new password following the complexity rules."
        };

        function showTopicContent(title) {
            const modal = document.getElementById('contentModal');
            const titleEl = document.getElementById('topicTitle');
            const descEl = document.getElementById('topicDescription');

            titleEl.innerText = title;
            const content = articleData[title] || "The detailed guide for this topic is being prepared. For immediate assistance, please reach out to the IT Support desk.";
            
            descEl.innerHTML = `<p>${content}</p>`;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent scroll
        }

        function closeModal() {
            document.getElementById('contentModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scroll
        }

        // Close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('contentModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>