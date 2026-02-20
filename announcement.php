<?php
// announcement.php

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'include/db_connect.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// Get User Role
$logged_in_role = $_SESSION['role'] ?? 'Employee';
$logged_in_id = $_SESSION['user_id'];

// Helper for file upload
function uploadFile($fileInput, $targetDir, $allowedTypes) {
    if (isset($_FILES[$fileInput]) && $_FILES[$fileInput]['error'] == 0) {
        if (!is_dir($targetDir)) { mkdir($targetDir, 0777, true); }
        $ext = pathinfo($_FILES[$fileInput]["name"], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowedTypes)) {
            $newFilename = uniqid() . '.' . $ext;
            $targetFile = $targetDir . $newFilename;
            if (move_uploaded_file($_FILES[$fileInput]["tmp_name"], $targetFile)) {
                return $targetFile;
            }
        }
    }
    return null;
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $title = $_POST['title'];
    $category = $_POST['category'];
    $target_audience = $_POST['target_audience'];
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $p_date = $_POST['publish_date'];
    $msg = $_POST['message'];
    $created_by = $_SESSION['user_id'];

    $image_path = uploadFile('announcement_image', 'uploads/images/', ['jpg', 'jpeg', 'png', 'gif']);
    $attachment_path = uploadFile('announcement_file', 'uploads/docs/', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

    if ($action == 'add') {
        $stmt = $conn->prepare("INSERT INTO announcements (title, category, target_audience, is_pinned, publish_date, message, created_by, image_path, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisssss", $title, $category, $target_audience, $is_pinned, $p_date, $msg, $created_by, $image_path, $attachment_path);
        $stmt->execute();
        header("Location: announcement.php?success=added");
        exit();

    } elseif ($action == 'update') {
        $id = $_POST['id'];
        $query = "UPDATE announcements SET title=?, category=?, target_audience=?, is_pinned=?, publish_date=?, message=?";
        $types = "sssiis";
        $params = [$title, $category, $target_audience, $is_pinned, $p_date, $msg];

        if ($image_path) { $query .= ", image_path=?"; $types .= "s"; $params[] = $image_path; }
        if ($attachment_path) { $query .= ", attachment_path=?"; $types .= "s"; $params[] = $attachment_path; }
        
        $query .= " WHERE id=?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        header("Location: announcement.php?success=updated");
        exit();
    }
}

// --- HANDLE DELETION ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: announcement.php?deleted=1");
    exit();
}

// --- FETCH ANNOUNCEMENTS (With Creator Info) ---
// We JOIN with the 'users' table to get the name of who posted it.
$sql = "SELECT a.*, u.username as creator_name, u.role as creator_role 
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id ";

// Filter Logic:
// 1. Admin, HR, CFO, HR Executive -> See ALL.
// 2. Manager, Team Lead -> See ONLY what THEY created (to manage/edit).
if (in_array($logged_in_role, ['System Admin', 'HR', 'CFO', 'HR Executive'])) {
    $sql .= " ORDER BY a.is_pinned DESC, a.publish_date DESC";
    $result = $conn->query($sql);
} else {
    $sql .= " WHERE a.created_by = ? ORDER BY a.is_pinned DESC, a.publish_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $logged_in_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | Manage Announcements</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        #mainContent { 
            margin-left: 95px; 
            padding: 20px 30px; 
            width: calc(100% - 95px); 
            transition: all 0.3s ease; 
            min-height: 100vh; 
            padding-top: 80px !important; 
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        
        .text-primary { color: #1b5a5a; }
        .bg-primary { background-color: #1b5a5a; }
        .hover-bg-primary:hover { background-color: #144242; }
        .modal { transition: opacity 0.25s ease; }
    </style>
</head>
<body class="text-slate-600">

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="mainContent">
        <div class="flex justify-between items-end mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Announcements</h1>
                <p class="text-xs text-slate-500 mt-1">Manage and broadcast company updates</p>
            </div>
            
            <div class="flex gap-3">
                <a href="view_announcements.php" class="flex items-center gap-2 bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:bg-slate-50 transition-all">
                    <i class="fa-regular fa-eye"></i> View Page
                </a>
                <button onclick="openModal('announcementModal', 'add')" class="flex items-center gap-2 bg-primary hover-bg-primary text-white px-5 py-2 rounded-lg text-sm font-medium shadow-sm transition-all">
                    <i class="fa-solid fa-plus"></i> Add Announcement
                </button>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-xs border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Title</th>
                            <th class="px-6 py-4">Posted By</th>
                            <th class="px-6 py-4">Audience</th>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while($row = $result->fetch_assoc()): 
                            $bg = 'bg-teal-50 text-teal-700';
                            if($row['category'] == 'Holiday') $bg = 'bg-blue-50 text-blue-600';
                            if($row['category'] == 'Policy') $bg = 'bg-indigo-50 text-indigo-600';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full <?php echo $bg; ?> flex items-center justify-center shrink-0 mt-1">
                                        <i class="fa-solid fa-bullhorn text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 text-sm flex items-center gap-2">
                                            <?php echo htmlspecialchars($row['title']); ?>
                                            <?php if($row['is_pinned']): ?>
                                                <i class="fa-solid fa-thumbtack text-rose-500 text-xs" title="Pinned"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5"><?php echo substr($row['message'], 0, 40); ?>...</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-xs font-semibold text-slate-700"><?php echo htmlspecialchars($row['creator_name']); ?></div>
                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($row['creator_role']); ?></div>
                            </td>
                            <td class="px-6 py-4"><span class="px-2.5 py-1 rounded text-xs font-semibold bg-slate-100 border"><?php echo $row['target_audience']; ?></span></td>
                            <td class="px-6 py-4 text-slate-600 font-medium"><?php echo date('d M Y', strtotime($row['publish_date'])); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="p-2 text-slate-400 hover:text-teal-600 hover:bg-teal-50 rounded-full">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                    <button onclick="openModal('announcementModal', 'edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-full">
                                        <i class="fa-regular fa-pen-to-square"></i>
                                    </button>
                                    <a href="announcement.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-full">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="announcementModal" class="modal fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('announcementModal')"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl relative z-10 p-6">
                <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
                    <h3 id="modalTitle" class="font-bold text-lg">New Announcement</h3>
                    <button onclick="closeModal('announcementModal')"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <form method="POST" action="announcement.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="mb-3">
                        <label class="block text-xs font-bold uppercase mb-1">Title</label>
                        <input type="text" name="title" id="inpTitle" required class="w-full border rounded-lg p-2.5 text-sm focus:border-teal-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Target Audience</label>
                            <select name="target_audience" id="inpAudience" class="w-full border rounded-lg p-2.5 text-sm focus:border-teal-500">
                                <?php if(in_array($logged_in_role, ['System Admin', 'HR', 'CFO', 'HR Executive'])): ?>
                                    <option value="All">All Employees</option>
                                    <option value="HR">HR Team</option>
                                    <option value="Manager">Managers</option>
                                    <option value="Team Lead">Team Leads</option>
                                    <option value="Employee">Employees</option>
                                    <option value="Accounts">Accounts Team</option>
                                    <option value="IT Admin">IT Admins</option>
                                    <option value="IT Executive">IT Executives</option>
                                    <option value="CFO">CFO Office</option>
                                <?php elseif($logged_in_role == 'Manager'): ?>
                                    <option value="Team Lead">Team Leads</option>
                                    <option value="Employee">My Team (Employees)</option>
                                <?php elseif($logged_in_role == 'Team Lead'): ?>
                                    <option value="Employee">My Team (Employees)</option>
                                <?php else: ?>
                                    <option value="All">General</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Category</label>
                            <select name="category" id="inpCategory" class="w-full border rounded-lg p-2.5 text-sm focus:border-teal-500">
                                <option>General</option><option>Holiday</option><option>Policy</option><option>Event</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Publish Date</label>
                            <input type="date" name="publish_date" id="inpDate" required class="w-full border rounded-lg p-2.5 text-sm focus:border-teal-500">
                        </div>
                        <div class="flex items-end pb-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_pinned" id="inpPinned" class="w-4 h-4 text-teal-600 rounded">
                                <span class="text-sm font-medium text-slate-700">Pin to Top?</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Cover Image</label>
                            <input type="file" name="announcement_image" accept="image/*" class="w-full text-xs border rounded p-1">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Attach PDF</label>
                            <input type="file" name="announcement_file" accept=".pdf,.doc,.docx" class="w-full text-xs border rounded p-1">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold uppercase mb-1">Message</label>
                        <textarea name="message" id="inpMessage" rows="4" required class="w-full border rounded-lg p-2.5 text-sm focus:border-teal-500"></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('announcementModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold shadow-md hover-bg-primary">Save Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="viewModal" class="modal fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('viewModal')"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl relative z-10 overflow-hidden">
                <div class="bg-teal-50 px-6 py-4 border-b border-teal-100 flex justify-between items-center">
                    <h3 class="font-bold text-teal-800 text-lg" id="viewTitle"></h3>
                    <button onclick="closeModal('viewModal')"><i class="fa-solid fa-xmark text-teal-600"></i></button>
                </div>
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex gap-2">
                            <span id="viewCategory" class="text-xs font-bold px-2 py-1 bg-slate-100 rounded"></span>
                            <span id="viewAudience" class="text-xs font-bold px-2 py-1 bg-blue-50 text-blue-600 rounded"></span>
                            <span id="viewPinned" class="hidden text-xs font-bold px-2 py-1 bg-rose-50 text-rose-600 rounded">Pinned</span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Posted By:</p>
                            <p id="viewCreator" class="text-xs font-bold text-slate-700"></p>
                        </div>
                    </div>
                    <img id="viewImage" src="" class="w-full h-40 object-cover rounded-lg mb-4 hidden">
                    <p id="viewMessage" class="text-sm text-slate-600 whitespace-pre-wrap leading-relaxed mb-4"></p>
                    <a id="downloadLink" href="#" target="_blank" class="hidden text-xs bg-slate-100 border border-slate-300 px-3 py-2 rounded font-medium hover:bg-slate-200 block text-center">Download Attachment</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(id, mode = 'add', data = null) {
            document.getElementById(id).classList.remove('hidden');
            if (id === 'announcementModal') {
                if (mode === 'edit' && data) {
                    document.getElementById('modalTitle').innerText = 'Edit Announcement';
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('editId').value = data.id;
                    document.getElementById('inpTitle').value = data.title;
                    document.getElementById('inpAudience').value = data.target_audience;
                    document.getElementById('inpCategory').value = data.category;
                    document.getElementById('inpDate').value = data.publish_date;
                    document.getElementById('inpMessage').value = data.message;
                    document.getElementById('inpPinned').checked = data.is_pinned == 1;
                } else {
                    document.getElementById('modalTitle').innerText = 'New Announcement';
                    document.getElementById('formAction').value = 'add';
                    document.querySelector('form').reset();
                }
            }
        }

        function openViewModal(data) {
            document.getElementById('viewModal').classList.remove('hidden');
            document.getElementById('viewTitle').innerText = data.title;
            document.getElementById('viewCategory').innerText = data.category;
            document.getElementById('viewAudience').innerText = data.target_audience;
            document.getElementById('viewMessage').innerText = data.message;
            document.getElementById('viewCreator').innerText = data.creator_name + ' (' + data.creator_role + ')';
            
            if(data.is_pinned == 1) document.getElementById('viewPinned').classList.remove('hidden');
            else document.getElementById('viewPinned').classList.add('hidden');
            
            const imgEl = document.getElementById('viewImage');
            if(data.image_path) { imgEl.src = data.image_path; imgEl.classList.remove('hidden'); }
            else { imgEl.classList.add('hidden'); }

            const dlLink = document.getElementById('downloadLink');
            if(data.attachment_path) { dlLink.href = data.attachment_path; dlLink.classList.remove('hidden'); }
            else { dlLink.classList.add('hidden'); }
        }

        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    </script>
</body>
</html>