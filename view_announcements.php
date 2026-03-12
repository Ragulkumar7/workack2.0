<?php
// view_announcements.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$user_role = $_SESSION['role'] ?? 'Employee'; 
$user_id = $_SESSION['user_id'];

// Login aagiruka employee oda department-a edukkurom
$user_dept = $_SESSION['department'] ?? ''; 

// Fetch exact username and full_name to match with meeting attendees list
$username = $_SESSION['username'] ?? '';
$full_name = '';
$stmt_u = $conn->prepare("SELECT u.username, ep.full_name FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?");
if ($stmt_u) {
    $stmt_u->bind_param("i", $user_id);
    $stmt_u->execute();
    $res_u = $stmt_u->get_result()->fetch_assoc();
    if ($res_u) {
        $username = $res_u['username'];
        $full_name = $res_u['full_name'] ?? $username;
    }
    $stmt_u->close();
}

// ---------------------------------------------------------
// 1. Fetch ONLY Announcements (Exclude Meetings) for Top Section
// ---------------------------------------------------------
if (in_array($user_role, ['System Admin', 'HR', 'CFO', 'HR Executive'])) {
    $sql = "SELECT a.*, u.username as creator_name, u.role as creator_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.category != 'Meeting'
            ORDER BY a.is_pinned DESC, a.publish_date DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT a.*, u.username as creator_name, u.role as creator_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.category != 'Meeting' AND (
                a.target_audience = 'All' 
                OR a.target_audience = 'All Employees' 
                OR a.target_audience = ? 
                OR a.target_audience = ? 
                OR a.created_by = ?
            ) 
            ORDER BY a.is_pinned DESC, a.publish_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $user_role, $user_dept, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$announcements = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $img = $row['image_path'];
        if (empty($img) || !file_exists($img)) {
            $img = '';
        }
        $badgeClass = 'bg-event';
        if ($row['is_pinned'] == 1) $badgeClass = 'bg-urgent'; 
        elseif ($row['category'] == 'Holiday') $badgeClass = 'bg-holiday';

        $row['img'] = $img;
        $row['badgeClass'] = $badgeClass;
        $announcements[] = $row;
    }
}

// Default Item for Top Section
$latest = !empty($announcements) ? $announcements[0] : [
    'title' => 'No Announcements', 
    'message' => 'Check back later for updates.', 
    'publish_date' => date('Y-m-d'), 
    'category' => 'General', 
    'img' => '',
    'badgeClass' => 'bg-event',
    'is_pinned' => 0,
    'attachment_path' => null,
    'target_audience' => 'All',
    'creator_name' => 'System',
    'creator_role' => 'Admin'
];


// ---------------------------------------------------------
// 2. Fetch ONLY Meetings for the Bottom Table
// ---------------------------------------------------------
if (in_array($user_role, ['System Admin', 'HR', 'CFO', 'HR Executive'])) {
    $meet_sql = "SELECT a.*, u.username as creator_name FROM announcements a LEFT JOIN users u ON a.created_by = u.id WHERE a.category = 'Meeting' ORDER BY a.publish_date DESC";
    $stmt_meet = $conn->prepare($meet_sql);
} else {
    $meet_sql = "SELECT a.*, u.username as creator_name FROM announcements a LEFT JOIN users u ON a.created_by = u.id 
                 WHERE a.category = 'Meeting' AND (
                     a.target_audience = 'All' 
                     OR a.target_audience = 'All Employees' 
                     OR a.target_audience = ? 
                     OR a.target_audience = ? 
                     OR a.target_audience LIKE CONCAT('%', ?, '%')
                     OR a.message LIKE CONCAT('%', ?, '%')
                     OR a.message LIKE CONCAT('%', ?, '%')
                     OR a.created_by = ?
                 ) ORDER BY a.publish_date DESC";
    $stmt_meet = $conn->prepare($meet_sql);
    $stmt_meet->bind_param("sssssi", $user_role, $user_dept, $username, $username, $full_name, $user_id);
}
$stmt_meet->execute();
$meet_res = $stmt_meet->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | Announcement</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root { 
            --bg-body: #f8fafc; 
            --primary-color: #1b5a5a; 
            --text-main: #1e293b; 
            --border-color: #e2e8f0; 
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; }
        
        #mainContent { 
            margin-left: 95px; padding: 20px 30px; 
            width: calc(100% - 95px); min-height: 100vh; 
            padding-top: 10px !important; 
            transition: margin-left 0.3s ease; 
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        
        .announcement-layout { display: grid; grid-template-columns: 1fr 380px; gap: 25px; align-items: start; }
        
        .featured-card { background: white; border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; height: 100%; box-shadow: var(--shadow-md); }
        .featured-img-wrapper { width: 100%; height: 380px; background: #f1f5f9; overflow: hidden; position: relative; }
        .featured-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: opacity 0.4s ease-in-out; }
        .featured-content { padding: 30px; }
        
        .badge { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 0.5px; }
        .bg-urgent { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; } 
        .bg-event  { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
        .bg-holiday{ background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .side-panel { background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 100px; }
        .news-item { display: flex; gap: 15px; padding: 12px; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; margin-bottom: 8px; border: 1px solid transparent; }
        .news-item:hover { background: #f0fdfa; border-color: #ccfbf1; } 
        .news-item.active { background: #e0f2f1; border-color: var(--primary-color); }
        
        .date-box { width: 50px; height: 50px; background: #fff; border: 1px solid var(--border-color); border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
        .date-box small { font-size: 10px; color: var(--primary-color); text-transform: uppercase; }
        
        @media (max-width: 1024px) { .announcement-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="mainContent">
        <div class="mb-6 flex items-center gap-4">
            <a href="javascript:history.back()" class="w-10 h-10 bg-white border border-slate-300 rounded-xl flex items-center justify-center text-slate-600 hover:bg-teal-50 hover:text-teal-600 hover:border-teal-200 transition-all shadow-sm" title="Go Back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Company Updates</h1>
                <div class="text-sm text-slate-500">Latest news and announcements</div>
            </div>
        </div>

        <div class="announcement-layout">
            
            <article class="featured-card">
                <div class="featured-img-wrapper" id="mainImgWrapper" <?php echo empty($latest['img']) ? 'style="display: none;"' : ''; ?>>
                    <img id="mainImg" src="<?php echo $latest['img']; ?>" alt="Banner">
                </div>
                <div class="featured-content">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex gap-2">
                            <span id="mainBadge" class="badge <?php echo $latest['badgeClass']; ?>"><?php echo $latest['category']; ?></span>
                            <span id="mainPin" class="<?php echo ($latest['is_pinned'] ? '' : 'hidden'); ?> text-xs font-bold text-red-500 flex items-center gap-1 border border-red-200 px-2 py-1 rounded bg-red-50 mb-3"><i class="fa-solid fa-thumbtack"></i> Pinned</span>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-slate-400 uppercase tracking-wide">Posted By</p>
                            <p id="mainCreator" class="text-xs font-bold text-teal-700"><?php echo htmlspecialchars($latest['creator_name']); ?> <span class="text-slate-400 font-normal">(<?php echo htmlspecialchars($latest['creator_role']); ?>)</span></p>
                        </div>
                    </div>
                    
                    <h2 id="mainTitle" class="text-2xl font-bold text-slate-800 mb-4"><?php echo $latest['title']; ?></h2>
                    
                    <div class="flex gap-5 text-sm text-slate-500 mb-6 pb-4 border-b border-slate-100">
                        <span><i class="far fa-calendar-alt text-teal-600 mr-2"></i> <span id="mainDate"><?php echo date('d M Y', strtotime($latest['publish_date'])); ?></span></span>
                        <span><i class="far fa-eye text-teal-600 mr-2"></i> For: <span id="mainTarget"><?php echo $latest['target_audience'] ?? 'All'; ?></span></span>
                    </div>
                    
                    <div id="mainDesc" class="text-slate-600 leading-relaxed whitespace-pre-wrap"><?php echo $latest['message']; ?></div>

                    <div id="mainAttachment" class="<?php echo ($latest['attachment_path'] ? '' : 'hidden'); ?> mt-6 p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-center gap-4">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center text-red-500 text-xl"><i class="fa-solid fa-file-pdf"></i></div>
                        <div class="flex-1">
                            <div class="text-sm font-bold text-slate-800">Attached Document</div>
                            <div class="text-xs text-slate-500">Download for details</div>
                        </div>
                        <a id="mainDownload" href="<?php echo $latest['attachment_path']; ?>" target="_blank" class="px-4 py-2 bg-white border border-slate-300 rounded text-sm font-bold text-slate-700 hover:bg-gray-50 shadow-sm">Download</a>
                    </div>
                </div>
            </article>

            <aside class="side-panel">
                <div class="flex justify-between items-center mb-4 pb-2 border-b border-slate-100">
                    <span class="font-bold text-slate-800">Latest Updates</span>
                    <i class="fas fa-rss text-teal-600"></i>
                </div>
                
                <?php foreach($announcements as $index => $item): ?>
                <div class="news-item <?php echo ($index === 0) ? 'active' : ''; ?>" 
                     onclick="updateView(this, <?php echo htmlspecialchars(json_encode([
                        'title' => $item['title'],
                        'badge' => $item['category'],
                        'badgeClass' => $item['badgeClass'],
                        'date' => date('d M Y', strtotime($item['publish_date'])),
                        'img' => $item['img'],
                        'desc' => $item['message'],
                        'target' => $item['target_audience'],
                        'is_pinned' => $item['is_pinned'],
                        'attachment' => $item['attachment_path'],
                        'creator' => $item['creator_name'],
                        'role' => $item['creator_role']
                     ])); ?>)">
                    
                    <div class="date-box">
                        <span><?php echo date('d', strtotime($item['publish_date'])); ?></span>
                        <small><?php echo date('M', strtotime($item['publish_date'])); ?></small>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-slate-800 text-sm mb-1 line-clamp-1"><?php echo htmlspecialchars($item['title']); ?></h4>
                            <?php if($item['is_pinned']): ?>
                                <i class="fa-solid fa-thumbtack text-red-500 text-xs mt-1"></i>
                            <?php endif; ?>
                        </div>
                        <p class="text-[10px] text-teal-600 mb-1"><i class="fa-solid fa-user-pen"></i> <?php echo htmlspecialchars($item['creator_name']); ?></p>
                        <p class="text-xs text-slate-500 line-clamp-1"><?php echo substr($item['message'], 0, 40); ?>...</p>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(empty($announcements)): ?>
                    <p class="text-center text-sm text-slate-400 py-4">No updates available.</p>
                <?php endif; ?>

            </aside>
        </div>
        
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mt-8 mb-8">
            <div class="p-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50">
                <i class="fa-solid fa-handshake text-amber-600"></i>
                <h2 class="font-bold text-slate-700">Scheduled Meetings</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-100 text-slate-600 font-bold uppercase text-xs border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Meeting Title</th>
                            <th class="px-6 py-4">Date & Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(!$meet_res || $meet_res->num_rows == 0): ?>
                            <tr><td colspan="2" class="p-10 text-center text-slate-400">No meetings scheduled for you.</td></tr>
                        <?php else: ?>
                        <?php while($row = $meet_res->fetch_assoc()): 
                            // Hide the "Attendees: ..." line from the message
                            $clean_message = preg_replace('/Attendees:.*$/is', '', $row['message']);
                        ?>
                        <tr class="hover:bg-amber-50/30 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-800"><?php echo htmlspecialchars($row['title']); ?></td>
                            <td class="px-6 py-4">
                                <div class="text-teal-700 font-bold"><?php echo date('d M Y', strtotime($row['publish_date'])); ?></div>
                                <div class="text-xs text-slate-500 whitespace-pre-line mt-1"><?php echo htmlspecialchars(trim($clean_message)); ?></div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function updateView(element, data) {
            const imgWrapper = document.getElementById('mainImgWrapper');
            const imgEl = document.getElementById('mainImg');
            const titleEl = document.getElementById('mainTitle');
            const badgeEl = document.getElementById('mainBadge');
            const dateEl = document.getElementById('mainDate');
            const descEl = document.getElementById('mainDesc');
            const pinEl = document.getElementById('mainPin');
            const targetEl = document.getElementById('mainTarget');
            const attDiv = document.getElementById('mainAttachment');
            const dlLink = document.getElementById('mainDownload');
            const creatorEl = document.getElementById('mainCreator');

            imgEl.style.opacity = '0.5';
            titleEl.style.opacity = '0.5';
            
            setTimeout(() => {
                titleEl.innerText = data.title;
                badgeEl.innerText = data.badge;
                badgeEl.className = `badge ${data.badgeClass}`;
                dateEl.innerText = data.date;
                descEl.innerText = data.desc;
                targetEl.innerText = data.target;
                creatorEl.innerHTML = `${data.creator} <span class="text-slate-400 font-normal">(${data.role})</span>`;

                if (data.img && data.img !== '') {
                    imgEl.src = data.img;
                    imgWrapper.style.display = 'block';
                } else {
                    imgEl.src = '';
                    imgWrapper.style.display = 'none';
                }

                if(data.is_pinned == 1) pinEl.classList.remove('hidden');
                else pinEl.classList.add('hidden');

                if(data.attachment) {
                    attDiv.classList.remove('hidden');
                    dlLink.href = data.attachment;
                } else {
                    attDiv.classList.add('hidden');
                }

                imgEl.style.opacity = '1';
                titleEl.style.opacity = '1';
            }, 200);

            document.querySelectorAll('.news-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');
        }
    </script>
</body>
</html>