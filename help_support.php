<?php
// help_support.php

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'include/db_connect.php'; // Ensure DB connection is included

// 2. PATHS
$sidebarPath = __DIR__ . '/sidebars.php'; 
$headerPath = __DIR__ . '/header.php';
if (!file_exists($sidebarPath)) { $sidebarPath = __DIR__ . '/../sidebars.php'; }
if (!file_exists($headerPath)) { $headerPath = __DIR__ . '/../header.php'; }

// 3. FETCH DATA FROM DB
$kb_data = [];
$article_map = []; // To store Title -> Content for JS
$total_articles = 0; // To count total articles for the header

// Fetch Categories
$cat_sql = "SELECT * FROM help_categories ORDER BY id ASC";
// Suppress error if table doesn't exist yet, so we can show dummy data
$cat_res = @$conn->query($cat_sql); 

if ($cat_res && $cat_res->num_rows > 0) {
    while($cat = $cat_res->fetch_assoc()) {
        $cat_id = $cat['id'];
        
        $art_sql = "SELECT title, content FROM help_articles WHERE category_id = ?";
        $stmt = $conn->prepare($art_sql);
        $stmt->bind_param("i", $cat_id);
        $stmt->execute();
        $art_res = $stmt->get_result();
        
        $articles = [];
        while($row = $art_res->fetch_assoc()) {
            $clean_desc = strip_tags($row['content']);
            $short_desc = strlen($clean_desc) > 70 ? substr($clean_desc, 0, 70) . "..." : $clean_desc;
            if(empty($short_desc)) $short_desc = "Click to view detailed guide and instructions.";

            $articles[] = [
                'title' => $row['title'],
                'desc' => $short_desc
            ];
            $article_map[$row['title']] = $row['content'];
            $total_articles++;
        }
        
        if (!empty($articles)) {
            $kb_data[] = [
                'title' => $cat['title'],
                'articles' => $articles
            ];
        }
    }
}

// 4. FALLBACK UNIQUE CONTENT (If Database is empty)
if (empty($kb_data)) {
    $kb_data = [
        [
            'title' => 'Attendance, Leaves & Shifts',
            'articles' => [
                [
                    'title' => 'How to punch in and track daily attendance', 
                    'desc' => 'Guide to logging your daily entry, exits, and tracking production hours.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Your daily attendance and production hours are calculated based on your punch-in and punch-out times.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Steps to Punch In:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Go to the <b>Attendance</b> module from the left sidebar.</li>
                            <li>Click the green <b>Punch In</b> button at the top of the dashboard.</li>
                            <li>When you take a break, click <b>Start Break</b>. Remember to click <b>End Break</b> when you return.</li>
                            <li>At the end of your shift, click <b>Punch Out</b> to log your total production hours.</li>
                        </ul></div>"
                ],
                [
                    'title' => 'Applying for Sick or Casual Leaves', 
                    'desc' => 'How to check your leave balance and apply for time off in the portal.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Employees can request Sick, Casual, or Loss of Pay leaves directly through the HRMS.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>How to Apply:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Navigate to <b>Leave Management</b> > <b>Apply Leave</b>.</li>
                            <li>Select your <b>Leave Type</b> (e.g., Casual Leave) from the dropdown.</li>
                            <li>Select the <b>Start Date</b> and <b>End Date</b>.</li>
                            <li>Enter a valid reason for your absence and click <b>Submit</b>. Your TL and Manager will be notified.</li>
                        </ul></div>"
                ],
                [
                    'title' => 'Applying for Work From Home (WFH)', 
                    'desc' => 'How to submit a WFH request to your manager and HR.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>If you need to work remotely, you must submit a WFH request prior to your shift.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Request Process:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Open the <b>Attendance</b> menu and select <b>WFH Requests</b>.</li>
                            <li>Click on <b>New Request</b>.</li>
                            <li>Provide the specific dates and detailed reason for working from home.</li>
                            <li>Once submitted, track the status (Pending/Approved/Rejected) in the same menu.</li>
                        </ul></div>"
                ]
            ]
        ],
        [
            'title' => 'Task Management',
            'articles' => [
                [
                    'title' => 'Managing your Personal Taskboard', 
                    'desc' => 'How to add, track, and update the status of your daily personal tasks.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Keep track of your own daily goals and reminders using the Personal Taskboard.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>How to use the Taskboard:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Navigate to the <b>Task Management</b> menu from the sidebar.</li>
                            <li>Click <b>Add New Task</b> to create a personal goal. Add a title, priority, and deadline.</li>
                            <li>Drag and drop cards or click to update the status from <b>To Do</b> to <b>In Progress</b> or <b>Completed</b>.</li>
                        </ul></div>"
                ],
                [
                    'title' => 'Completing Team Tasks assigned by your Lead', 
                    'desc' => 'Updating priority and marking assigned team tasks as completed.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Tasks assigned to you by your Team Lead or Manager will appear in your Team Tasks module.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Action Steps:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Go to <b>Task Management</b> > <b>Team Tasks</b>.</li>
                            <li>Review the priority (Critical, High, Medium, Low) and deadline set by the assigner.</li>
                            <li>Once you finish the work, click on the task and change its status to <b>Completed</b>. The assigner will be notified instantly.</li>
                        </ul></div>"
                ]
            ]
        ],
        [
            'title' => 'Team Chat & Communication',
            'articles' => [
                [
                    'title' => 'Using the Internal Chat System', 
                    'desc' => 'Starting direct conversations or group chats with colleagues.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Connect with your colleagues instantly without leaving the HRMS portal.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Starting a Chat:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Click on <b>Team Chat</b> in the left sidebar.</li>
                            <li>Use the search bar to find a specific employee or group.</li>
                            <li>Click their name to open the chat window. You can send text, files, and images securely.</li>
                        </ul></div>"
                ],
                [
                    'title' => 'Making Audio & Video Calls', 
                    'desc' => 'How to initiate a call request with team members directly from the portal.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>You can instantly transition a text chat into a voice or video meeting.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>How to Call:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Open the active conversation in the <b>Team Chat</b> window.</li>
                            <li>In the top right corner of the chat box, click the <b>Video Camera</b> icon for a video call or the <b>Phone</b> icon for an audio call.</li>
                            <li>Ensure your browser has permission to access your microphone and camera.</li>
                        </ul></div>"
                ]
            ]
        ],
        [
            'title' => 'Company Announcements',
            'articles' => [
                [
                    'title' => 'Viewing Company Announcements', 
                    'desc' => 'Checking the announcement board for priority updates and pinned messages.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>HR and Management use the Announcement module to broadcast important news, policies, and holiday updates.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Finding Announcements:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Go to <b>Announcements</b> from the sidebar.</li>
                            <li>Priority and <b>Pinned</b> messages will appear at the very top of your feed.</li>
                            <li>Click on any announcement card to read the full message and see who posted it.</li>
                        </ul></div>"
                ]
            ]
        ],
        [
            'title' => 'Performance Management',
            'articles' => [
                [
                    'title' => 'Checking your Performance Score', 
                    'desc' => 'Understanding your productivity score, task completion percentage, and manager ratings.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Your performance dashboard gives you a transparent view of your work metrics and appraisals.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>How to view your metrics:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Click on the <b>Performance</b> tab in the sidebar.</li>
                            <li>Here you can view your Total Score, which is calculated based on: Project completions, On-time tasks, Attendance percentage, and Manager ratings.</li>
                            <li>Check the <b>Manager Comments</b> section to read direct feedback from your supervisors.</li>
                        </ul></div>"
                ]
            ]
        ],
        [
            'title' => 'Payroll & Compensation',
            'articles' => [
                [
                    'title' => 'How to download your monthly Payslip', 
                    'desc' => 'Requesting and downloading your official salary slips for specific months.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Official payslips are generated after salary disbursement and can be downloaded as PDFs.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Download Instructions:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Go to <b>Request Payslip</b> in the sidebar.</li>
                            <li>Select the <b>Month</b> and <b>Year</b> you need the payslip for.</li>
                            <li>Click <b>Generate/Download PDF</b>.</li>
                            <li>If a payslip is missing, use the 'Raise Request to Accounts' button on the same page.</li>
                        </ul></div>"
                ]
            ]
        ],
        [
            'title' => 'IT Support & Hardware Assets',
            'articles' => [
                [
                    'title' => 'Raising an IT Support Ticket', 
                    'desc' => 'How to report an issue, set a priority, and track the resolution status.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>If you are facing hardware issues, software bugs, or network problems, raise a ticket for the IT Admin.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Steps to Raise a Ticket:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Click on <b>Raise Ticket</b> at the bottom of your sidebar.</li>
                            <li>Enter a clear <b>Subject</b> and select the appropriate <b>Department</b> (e.g., IT Support).</li>
                            <li>Set the <b>Priority</b> (Low, Medium, High) based on urgency.</li>
                            <li>Provide a detailed description of the issue. You can also attach screenshots.</li>
                            <li>Click <b>Submit</b>. You can track updates in the 'My Tickets' section.</li>
                        </ul></div>"
                ],
                [
                    'title' => 'Viewing your Assigned Hardware Assets', 
                    'desc' => 'Checking the details, specs, and barcodes of laptops/devices assigned to you.',
                    'content' => "
                        <div style='margin-bottom: 20px;'><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Overview</h3><p>Keep track of company property assigned to you, including laptops, monitors, and accessories.</p></div>
                        <div><h3 style='color: #1b5a5a; margin-bottom: 10px;'>Where to find your assets:</h3>
                        <ul style='list-style-type: decimal; padding-left: 20px; line-height: 1.8;'>
                            <li>Navigate to your <b>User Profile</b> (top right corner).</li>
                            <li>Click on the <b>My Assets</b> tab.</li>
                            <li>Here you will see a list of all devices, their specifications, and unique system barcodes.</li>
                        </ul></div>"
                ]
            ]
        ]
    ];

    // Map unique content to the article map
    foreach($kb_data as $cat) {
        foreach($cat['articles'] as $art) {
            $article_map[$art['title']] = $art['content'];
            $total_articles++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support</title>
    
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* Base HRMS Layout Styles */
        :root {
            --bg-body: #f8fafc; 
            --text-main: #0f172a;
            --sidebar-width: 95px;
            --hostinger-purple: #1b5a5a; 
            --search-bg: #1b5a5a; /* Changed banner background to match your theme based on screenshot */
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            line-height: 1.5;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px 40px; 
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        /* New Main Page Header above banner */
        .page-main-header {
            margin-bottom: 24px;
        }
        
        .page-main-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        /* --- SEARCH HERO SECTION --- */
        .search-hero {
            background-color: var(--search-bg);
            margin: 0 -40px 40px -40px; /* Pulls the banner to touch the side edges, but leaves space at top */
            padding: 60px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 700px; 
            /* Ensure the wrapper handles the flex context for the input correctly */
            display: flex;
            align-items: center;
        }

        .search-wrapper i {
            position: absolute;
            left: 20px;
            /* Remove top/transform to avoid floating issues, rely on flex alignment */
            color: #ffffff;
            width: 20px;
            height: 20px;
            pointer-events: none; /* Prevent icon from blocking clicks */
        }

        .search-wrapper input {
            width: 100%;
            padding: 16px 20px 16px 56px;
            font-size: 16px;
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.2); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            outline: none;
            transition: background-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
            /* Ensure line-height doesn't collapse causing the cursor/icon to misalign */
            line-height: normal; 
        }

        .search-wrapper input::placeholder {
            color: rgba(255, 255, 255, 0.8);
        }

        .search-wrapper input:focus {
            background-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        /* Container */
        .support-container {
            max-width: 860px; 
            margin: 0 auto; 
        }

        .category-header {
            margin-bottom: 40px;
        }

        .category-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
            margin-top: 0;
        }

        .category-header .subtitle {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 12px;
        }

        .category-header .article-count {
            display: inline-block;
            font-size: 14px;
            color: #6b7280;
        }

        /* Cards */
        .category-card {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); 
        }

        .category-card h2 {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-top: 0;
            margin-bottom: 0;
            padding-bottom: 20px;
            border-bottom: 1px solid #f3f4f6; 
        }

        .article-list {
            display: flex;
            flex-direction: column;
        }

        .article-item {
            text-decoration: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 0;
            border-bottom: 1px solid #f3f4f6;
            background: transparent;
            border: none;
            cursor: pointer;
            text-align: left;
            width: 100%;
            transition: opacity 0.2s ease;
        }

        .article-item:last-child {
            border-bottom: none; 
            padding-bottom: 0;
        }

        .article-item:hover {
            opacity: 0.8;
        }

        .article-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-right: 20px;
        }

        .article-content h3 {
            font-size: 16px;
            font-weight: 400; 
            color: #111827; 
            margin: 0;
        }

        .article-content p {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .article-icon {
            color: var(--hostinger-purple);
            flex-shrink: 0; 
        }

        /* --- MODAL STYLES --- */
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
            border-radius: 12px;
            padding: 40px;
            position: relative;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
            background: none;
            border: none;
        }
        .close-btn:hover { color: var(--hostinger-purple); }

        .btn-primary {
            background-color: var(--hostinger-purple);
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #144545;
        }
    </style>
</head>
<body>

    <?php if (file_exists($headerPath)) include($headerPath); ?>
    <?php if (file_exists($sidebarPath)) include($sidebarPath); ?>

    <div class="main-content" id="mainContent">
        
        <div class="page-main-header">
            <h1>Help & Support</h1>
        </div>
        
        <div class="search-hero">
            <div class="search-wrapper">
                <i data-lucide="search"></i>
                <input type="text" id="searchInput" placeholder="Search for help... (e.g. 'how to access')">
            </div>
        </div>

        <div class="support-container">
            
            <header class="category-header">
                <h1 id="pageTitle">Getting Started</h1>
                <p class="subtitle" id="pageSubtitle">Everything you need to know about the HRMS Portal features</p>
                <span class="article-count" id="articleCount"><?php echo $total_articles; ?> articles</span>
            </header>

            <div id="articlesContainer">
                <?php foreach ($kb_data as $category): ?>
                    <div class="category-card">
                        <h2><?php echo htmlspecialchars($category['title']); ?></h2>
                        
                        <div class="article-list">
                            <?php foreach ($category['articles'] as $article): ?>
                                <button class="article-item" onclick="showTopicContent('<?php echo addslashes($article['title']); ?>')">
                                    <div class="article-content">
                                        <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($article['desc']); ?></p>
                                    </div>
                                    <i data-lucide="chevron-right" class="article-icon w-5 h-5"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <div id="contentModal">
        <div class="modal-box">
            <button class="close-btn" onclick="closeModal()"><i data-lucide="x"></i></button>
            <div id="modalInner">
                <h2 id="topicTitle" style="font-size: 24px; font-weight: bold; color: var(--hostinger-purple); margin-bottom: 16px; margin-top:0;"></h2>
                <hr style="margin-bottom: 24px; border: 0; border-top: 1px solid #edf2f7;">
                <div id="topicDescription" style="color: #4b5563; font-size: 15px; margin-bottom: 32px;">
                </div>
                <div style="border-top: 1px solid #edf2f7; padding-top: 24px; text-align: right;">
                    <button onclick="closeModal()" class="btn-primary">Got it, thanks!</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const articleData = <?php echo json_encode($article_map); ?>;

        // Modal Logic
        function showTopicContent(title) {
            const modal = document.getElementById('contentModal');
            const titleEl = document.getElementById('topicTitle');
            const descEl = document.getElementById('topicDescription');

            const decodedTitle = new DOMParser().parseFromString(title, "text/html").body.textContent;

            titleEl.innerText = decodedTitle;
            const content = articleData[decodedTitle] || "Content is being prepared.";
            
            descEl.innerHTML = content; 
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; 
        }

        function closeModal() {
            document.getElementById('contentModal').style.display = 'none';
            document.body.style.overflow = 'auto'; 
        }

        window.onclick = function(event) {
            const modal = document.getElementById('contentModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Live Search Filtering Logic
        const searchInput = document.getElementById('searchInput');
        const pageTitle = document.getElementById('pageTitle');
        const pageSubtitle = document.getElementById('pageSubtitle');
        const articleCount = document.getElementById('articleCount');
        
        const originalTitle = "Getting Started";
        const totalArticles = <?php echo $total_articles; ?>;

        searchInput.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase().trim();
            let cards = document.querySelectorAll('.category-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                let articles = card.querySelectorAll('.article-item');
                let hasVisibleArticle = false;
                
                articles.forEach(article => {
                    let title = article.querySelector('h3').innerText.toLowerCase();
                    let desc = article.querySelector('p').innerText.toLowerCase();
                    
                    // Show article if text matches search query
                    if (title.includes(filter) || desc.includes(filter)) {
                        article.style.display = 'flex';
                        hasVisibleArticle = true;
                        visibleCount++;
                    } else {
                        article.style.display = 'none';
                    }
                });
                
                // Hide the whole category card if no articles inside it match the search
                card.style.display = hasVisibleArticle ? 'block' : 'none';
            });

            // Update Header Text dynamically to match the Hostinger image style
            if (filter.length > 0) {
                pageTitle.innerHTML = `Search Results for: "${this.value}"`;
                pageSubtitle.style.display = 'none'; // Hide subtitle when searching
                articleCount.innerHTML = `Found ${visibleCount} results`;
            } else {
                pageTitle.innerHTML = originalTitle;
                pageSubtitle.style.display = 'block'; // Bring subtitle back
                articleCount.innerHTML = `${totalArticles} articles`;
            }
        });
    </script>
</body>
</html>