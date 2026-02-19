<?php
// help_support.php

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'include/db_connect.php'; // Ensure DB connection is included

// 2. PATHS
$sidebarPath = __DIR__ . '/sidebars.php'; 
$headerPath = __DIR__ . '/header.php';
if (!file_exists($sidebarPath)) { $sidebarPath = __DIR__ . '/../sidebars.php'; }
if (!file_exists($headerPath)) { $headerPath = __DIR__ . '/../header.php'; }

// 3. FETCH DATA FROM DB
$kb_data = [];
$article_map = []; // To store Title -> Content for JS

// Fetch Categories
$cat_sql = "SELECT * FROM help_categories ORDER BY id ASC";
$cat_res = $conn->query($cat_sql);

if ($cat_res->num_rows > 0) {
    while($cat = $cat_res->fetch_assoc()) {
        $cat_id = $cat['id'];
        
        // Fetch Articles for this Category
        $art_sql = "SELECT title, content FROM help_articles WHERE category_id = ?";
        $stmt = $conn->prepare($art_sql);
        $stmt->bind_param("i", $cat_id);
        $stmt->execute();
        $art_res = $stmt->get_result();
        
        $articles = [];
        while($row = $art_res->fetch_assoc()) {
            $articles[] = $row['title'];
            // Map title to content for the Modal
            $article_map[$row['title']] = $row['content'];
        }
        
        // Add to main array if category has articles
        if (!empty($articles)) {
            $kb_data[] = [
                'title' => $cat['title'],
                'count' => str_pad(count($articles), 2, '0', STR_PAD_LEFT), // Format 05, 12 etc
                'articles' => $articles
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
            transition: margin-left 0.3s ease;
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

    <div class="main-content" id="mainContent">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-800">Help & Support</h1>
            <p class="text-slate-500 mt-1">Find guides, tutorials, and answers to your HRMS questions.</p>
        </div>

        <div class="kb-grid">
            <?php if(empty($kb_data)): ?>
                <p class="text-gray-500">No support articles found in the database.</p>
            <?php else: ?>
                <?php foreach ($kb_data as $category): ?>
                <div class="kb-card">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="p-2 rounded-lg bg-teal-50">
                            <i data-lucide="folder" class="text-[#1b5a5a] w-5 h-5"></i>
                        </div>
                        <span class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($category['title']); ?></span>
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded-full ml-auto"><?php echo $category['count']; ?></span>
                    </div>
                    <ul class="list-none p-0">
                        <?php foreach ($category['articles'] as $article): ?>
                        <li class="kb-item" onclick="showTopicContent('<?php echo addslashes($article); ?>')">
                            <i data-lucide="file-text" class="w-4 h-4 mt-1 text-slate-400"></i>
                            <span><?php echo htmlspecialchars($article); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

        // Topic Database (Fed from PHP)
        const articleData = <?php echo json_encode($article_map); ?>;

        function showTopicContent(title) {
            const modal = document.getElementById('contentModal');
            const titleEl = document.getElementById('topicTitle');
            const descEl = document.getElementById('topicDescription');

            // Decode HTML entities for title matching
            const decodedTitle = new DOMParser().parseFromString(title, "text/html").body.textContent;

            titleEl.innerText = decodedTitle;
            const content = articleData[decodedTitle] || "The detailed guide for this topic is being prepared. For immediate assistance, please reach out to the IT Support desk.";
            
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