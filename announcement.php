<?php
// announcement.php

// 1. SESSION & DB CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'include/db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get User Role
$logged_in_role = $_SESSION['role'] ?? 'Employee';
$logged_in_id = $_SESSION['user_id'];
$logged_in_name = $_SESSION['name'] ?? 'Admin'; // Assuming you have the user's name in session

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

// Target audience setting logic
if ($_SESSION['role'] == 'HR') {
    $target_audience_default = 'All Employees';
} else if ($_SESSION['role'] == 'Manager') {
    $target_audience_default = $_SESSION['department'] ?? 'All';
} else {
    $target_audience_default = 'All';
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // MEETING HANDLER
    if ($action == 'arrange_meeting') {
        $m_title = $_POST['meeting_title'];
        $m_date = $_POST['meeting_date'];
        $m_time = $_POST['meeting_time'];
        $m_desc = $_POST['meeting_description'];
        
        // Handle attendees array
        $attendees_array = isset($_POST['attendees']) ? $_POST['attendees'] : [];
        $attendees = !empty($attendees_array) ? implode(', ', $attendees_array) : 'All';
        
        $category = 'Meeting';
        $full_msg = "Time: $m_time \nAgenda: $m_desc \nAttendees: $attendees";
        
        // 1. Storing meeting details in announcements table (Manager's history)
        $stmt = $conn->prepare("INSERT INTO announcements (title, category, target_audience, publish_date, message, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssi", $m_title, $category, $attendees, $m_date, $full_msg, $logged_in_id);
            $stmt->execute();
            $meeting_id = $conn->insert_id; 
            
            // 2. DASHBOARD NOTIFICATION CONNECTION
            $notif_title = "📢 Meeting Scheduled";
            $notif_msg = $m_title . " by " . $logged_in_name;
            $notif_type = "alert";
            $source_type = "meeting";
            $link = "view_announcements.php"; 
            $target_role = 'Meeting Group'; 
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (target_role, title, message, type, link, source_type, source_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($notif_stmt) {
                $notif_stmt->bind_param("ssssssi", $target_role, $notif_title, $notif_msg, $notif_type, $link, $source_type, $meeting_id);
                $notif_stmt->execute();
            }
        }
        
        // 3. DASHBOARD WIDGETS CONNECTION (Fix for TL/Employee Dashboards)
        // Insert into calendar_meetings so teammate's dashboard fetches it automatically
        $formatted_time = date("h:i A", strtotime($m_time)); // Format time to match DB style
        $meet_link = 'Workack-Meet-' . substr(md5(uniqid()), 0, 10); 
        
        $cal_stmt = $conn->prepare("INSERT INTO calendar_meetings (title, meet_date, meet_time, meet_link, created_by) VALUES (?, ?, ?, ?, ?)");
        if ($cal_stmt) {
            $cal_stmt->bind_param("ssssi", $m_title, $m_date, $formatted_time, $meet_link, $logged_in_id);
            $cal_stmt->execute();
            $new_cal_meet_id = $conn->insert_id;
            
            // Link selected attendees to the meeting
            if (!empty($attendees_array)) {
                foreach ($attendees_array as $username) {
                    $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                    if ($u_stmt) {
                        $u_stmt->bind_param("s", $username);
                        $u_stmt->execute();
                        $u_res = $u_stmt->get_result();
                        if ($u_row = $u_res->fetch_assoc()) {
                            $part_user_id = $u_row['id'];
                            $conn->query("INSERT INTO calendar_meeting_participants (meeting_id, user_id) VALUES ($new_cal_meet_id, $part_user_id)");
                        }
                    }
                }
            } else {
                // If "All" is selected, link everyone
                $all_emp = $conn->query("SELECT id FROM users WHERE role != 'System Admin'");
                while ($emp = $all_emp->fetch_assoc()) {
                    $part_user_id = $emp['id'];
                    $conn->query("INSERT INTO calendar_meeting_participants (meeting_id, user_id) VALUES ($new_cal_meet_id, $part_user_id)");
                }
            }
        }

        header("Location: announcement.php?success=meeting");
        exit();
    }

    // ANNOUNCEMENT HANDLER
    if ($action == 'add' || $action == 'update') {
        $title = $_POST['title'];
        $category = $_POST['category'];
        $audience = $_POST['target_audience'] ?? 'All Employees';
        $p_date = $_POST['publish_date'];
        $msg = $_POST['message'];
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

        // Handle File Uploads
        $img_path = uploadFile('announcement_image', 'uploads/announcements/images/', ['jpg', 'jpeg', 'png', 'gif']);
        $doc_path = uploadFile('announcement_file', 'uploads/announcements/files/', ['pdf', 'doc', 'docx']);

        if ($action == 'add') {
            $stmt = $conn->prepare("INSERT INTO announcements (title, category, target_audience, publish_date, message, created_by, is_pinned, image_path, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssssiiss", $title, $category, $audience, $p_date, $msg, $logged_in_id, $is_pinned, $img_path, $doc_path);
                $stmt->execute();
            }
            header("Location: announcement.php?success=added");
            exit();
        } elseif ($action == 'update') {
            $id = $_POST['id'];
            $query = "UPDATE announcements SET title=?, category=?, target_audience=?, publish_date=?, message=?, is_pinned=? WHERE id=?";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("sssssii", $title, $category, $audience, $p_date, $msg, $is_pinned, $id);
                $stmt->execute();
            }
            
            // Update files only if new ones are uploaded
            if ($img_path) {
                $conn->query("UPDATE announcements SET image_path = '$img_path' WHERE id = $id");
            }
            if ($doc_path) {
                $conn->query("UPDATE announcements SET attachment_path = '$doc_path' WHERE id = $id");
            }
            
            header("Location: announcement.php?success=updated");
            exit();
        }
    }
}

// --- HANDLE DELETION ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: announcement.php?deleted=1");
    exit();
}

// Fetch employees for Meeting Attendees
$employees = $conn->query("SELECT id, username, department, role FROM users WHERE role != 'System Admin' ORDER BY department");
$emp_data = [];
$teams_array = []; // Array to hold unique teams/departments
if ($employees) {
    while($e = $employees->fetch_assoc()) { 
        $emp_data[] = $e; 
        if (!empty($e['department']) && !in_array($e['department'], $teams_array)) {
            $teams_array[] = $e['department'];
        }
    }
}

// Fetch Announcements
$sql = "SELECT a.*, u.username as creator_name, u.role as creator_role FROM announcements a LEFT JOIN users u ON a.created_by = u.id WHERE a.category != 'Meeting' ";
if (!in_array($logged_in_role, ['System Admin', 'HR', 'CFO', 'HR Executive'])) {
    $sql .= " AND (a.created_by = $logged_in_id OR a.target_audience = 'All Employees' OR a.target_audience = '$logged_in_role') ";
}
$sql .= " ORDER BY a.is_pinned DESC, a.publish_date DESC";
$result = $conn->query($sql);

// Fetch Meetings Only
$meet_sql = "SELECT a.*, u.username as creator_name FROM announcements a JOIN users u ON a.created_by = u.id WHERE a.category = 'Meeting' ORDER BY a.publish_date DESC";
$meet_res = $conn->query($meet_sql);

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
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        #mainContent { margin-left: 95px; padding: 20px 30px; width: calc(100% - 95px); transition: all 0.3s ease; min-height: 100vh; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        .text-primary { color: #1b5a5a; }
        .bg-primary { background-color: #1b5a5a; }
        .hover-bg-primary:hover { background-color: #144242; }
        .modal { transition: opacity 0.25s ease; }
        .modal-active { display: flex !important; }
    </style>
</head>
<body class="text-slate-600">

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="mainContent">
        <div class="flex justify-between items-end mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Communication Hub</h1>
                <p class="text-xs text-slate-500 mt-1">Manage announcements and schedule meetings</p>
            </div>
            
            <div class="flex gap-3">
                <a href="view_announcements.php" class="flex items-center gap-2 bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:bg-slate-50 transition-all">
                    <i class="fa-solid fa-eye text-teal-600"></i> View Announcements
                </a>
                <button onclick="toggleMeetingModal(true)" class="flex items-center gap-2 bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:bg-slate-50 transition-all">
                    <i class="fa-solid fa-calendar-plus text-teal-600"></i> Arrange Meeting
                </button>
                <button onclick="openModal('announcementModal', 'add')" class="flex items-center gap-2 bg-primary hover-bg-primary text-white px-5 py-2 rounded-lg text-sm font-medium shadow-sm transition-all">
                    <i class="fa-solid fa-plus"></i> Add Announcement
                </button>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-bullhorn text-teal-600"></i>
                <h2 class="font-bold text-slate-700">Recent Announcements</h2>
            </div>
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
                        <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): 
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
                            <td class="px-6 py-4"><span class="px-2.5 py-1 rounded text-xs font-semibold bg-slate-100 border"><?php echo htmlspecialchars($row['target_audience']); ?></span></td>
                            <td class="px-6 py-4 text-slate-600 font-medium"><?php echo date('d M Y', strtotime($row['publish_date'])); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="p-2 text-slate-400 hover:text-teal-600 hover:bg-teal-50 rounded-full">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                    <?php if(in_array($logged_in_role, ['System Admin', 'HR', 'CFO', 'HR Executive']) || $row['created_by'] == $logged_in_id): ?>
                                    <button onclick="openModal('announcementModal', 'edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-full">
                                        <i class="fa-regular fa-pen-to-square"></i>
                                    </button>
                                    <a href="announcement.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-full">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-handshake text-amber-600"></i>
                <h2 class="font-bold text-slate-700">Scheduled Meetings</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-xs border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Meeting Title</th>
                            <th class="px-6 py-4">Date & Details</th>
                            <th class="px-6 py-4">Attendees</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(!$meet_res || $meet_res->num_rows == 0): ?>
                            <tr><td colspan="4" class="p-10 text-center text-slate-400">No meetings scheduled yet.</td></tr>
                        <?php else: ?>
                        <?php while($row = $meet_res->fetch_assoc()): ?>
                        <tr class="hover:bg-amber-50/30 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-800"><?php echo htmlspecialchars($row['title']); ?></td>
                            <td class="px-6 py-4">
                                <div class="text-teal-700 font-bold"><?php echo date('d M Y', strtotime($row['publish_date'])); ?></div>
                                <div class="text-xs text-slate-500 whitespace-pre-line"><?php echo htmlspecialchars($row['message']); ?></div>
                            </td>
                            <td class="px-6 py-4"><span class="px-2 py-1 bg-slate-100 rounded text-xs"><?php echo htmlspecialchars($row['target_audience']); ?></span></td>
                            <td class="px-6 py-4 text-right">
                                <a href="announcement.php?delete_id=<?php echo $row['id']; ?>" class="p-2 text-slate-400 hover:text-rose-600" onclick="return confirm('Delete this meeting?')"><i class="fa-regular fa-trash-can"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="announcementModal" class="modal fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('announcementModal')"></div>
        <div class="flex items-center justify-center min-h-screen p-4 w-full">
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
                        <input type="text" name="title" id="inpTitle" required class="w-full border rounded-lg p-2.5 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Target Audience</label>
                            <select name="target_audience" id="inpAudience" class="w-full border rounded-lg p-2.5 text-sm">
                                <option value="All Employees">All Employees</option>
                                <option value="System Admin">System Admin</option>
                                <option value="HR">HR</option>
                                <option value="Manager">Manager</option>
                                <option value="Team Lead">Team Lead</option>
                                <option value="Employee">Employee</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Category</label>
                            <select name="category" id="inpCategory" class="w-full border rounded-lg p-2.5 text-sm">
                                <option>General</option><option>Holiday</option><option>Policy</option><option>Event</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Publish Date</label>
                            <input type="date" name="publish_date" id="inpDate" required class="w-full border rounded-lg p-2.5 text-sm">
                        </div>
                        <div class="flex items-end pb-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_pinned" id="inpPinned" class="w-4 h-4">
                                <span class="text-sm font-medium">Pin to Top?</span>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div><label class="block text-xs font-bold uppercase mb-1">Cover Image</label><input type="file" name="announcement_image" accept="image/*" class="w-full text-xs border p-1"></div>
                        <div><label class="block text-xs font-bold uppercase mb-1">Attach File</label><input type="file" name="announcement_file" accept=".pdf,.doc,.docx" class="w-full text-xs border p-1"></div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold uppercase mb-1">Message</label>
                        <textarea name="message" id="inpMessage" rows="4" required class="w-full border rounded-lg p-2.5 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('announcementModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="meetingModal" class="modal fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="toggleMeetingModal(false)"></div>
        <form method="POST" class="bg-white w-full max-w-lg rounded-xl shadow-2xl relative p-6 z-10">
            <input type="hidden" name="action" value="arrange_meeting">
            <h3 class="text-lg font-bold mb-4 border-b pb-2">Schedule New Meeting</h3>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="col-span-2"><label class="text-xs font-bold uppercase">Meeting Title</label><input type="text" name="meeting_title" required class="w-full border p-2 rounded-lg text-sm"></div>
                <div><label class="text-xs font-bold uppercase">Date</label><input type="date" name="meeting_date" required class="w-full border p-2 rounded-lg text-sm"></div>
                <div><label class="text-xs font-bold uppercase">Time</label><input type="time" name="meeting_time" required class="w-full border p-2 rounded-lg text-sm"></div>
                <div class="col-span-2">
                    <label class="text-xs font-bold uppercase">Filter Department</label>
                    <select id="deptFilter" onchange="filterAttendees(this.value)" class="w-full border p-2 rounded-lg mt-1 text-sm">
                        <option value="All">All Departments</option>
                        <?php foreach($teams_array as $team): ?>
                            <option value="<?php echo htmlspecialchars($team); ?>"><?php echo htmlspecialchars($team); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-span-2" id="attendeesBox" style="display: none;">
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-xs font-bold uppercase">Select Attendees</label>
                        <label class="text-[10px] font-bold text-teal-700 bg-teal-50 px-2 py-0.5 rounded cursor-pointer hover:bg-teal-100 flex items-center gap-1 border border-teal-200 transition">
                            <input type="checkbox" id="selectAllBtn" onchange="toggleSelectAll(this)" class="w-3 h-3 cursor-pointer accent-teal-600"> Select All
                        </label>
                    </div>
                    <div id="empList" class="border rounded-lg p-2 h-32 overflow-y-auto bg-slate-50 space-y-0.5"></div>
                </div>
                
                <div class="col-span-2"><label class="text-xs font-bold uppercase">Agenda</label><textarea name="meeting_description" rows="2" class="w-full border p-2 rounded-lg text-sm"></textarea></div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleMeetingModal(false)" class="px-4 py-2 text-sm border rounded-lg">Cancel</button>
                <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg font-bold text-sm">Schedule</button>
            </div>
        </form>
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
                    <p id="viewMessage" class="text-sm text-slate-600 whitespace-pre-wrap leading-relaxed mb-4"></p>
                    <img id="viewImage" src="" class="w-full h-40 object-cover rounded-lg mb-4 hidden">
                    <a id="downloadLink" href="#" target="_blank" class="hidden text-xs bg-slate-100 border p-2 rounded text-center">Download Attachment</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const employees = <?php echo json_encode($emp_data); ?>;
        
        function toggleMeetingModal(show) {
            const m = document.getElementById('meetingModal');
            show ? m.classList.add('modal-active') : m.classList.remove('modal-active');
            if(show) {
                // Reset select filter to All when opening
                document.getElementById('deptFilter').value = 'All';
                filterAttendees('All');
            }
        }
        
        function filterAttendees(dept) {
            const container = document.getElementById('empList');
            const attendeesBox = document.getElementById('attendeesBox');
            const selectAllBtn = document.getElementById('selectAllBtn');
            
            container.innerHTML = '';
            // Reset Select All button when switching departments
            if(selectAllBtn) selectAllBtn.checked = false;
            
            if(dept === 'All') {
                // Hide attendees section completely if "All" is selected
                attendeesBox.style.display = 'none';
            } else {
                // Show attendees section and populate filtered list
                attendeesBox.style.display = 'block';
                employees.filter(e => e.department === dept).forEach(e => {
                    container.innerHTML += `<label class="flex items-center gap-2 p-1.5 cursor-pointer hover:bg-slate-100 rounded transition"><input type="checkbox" name="attendees[]" value="${e.username}" class="attendee-checkbox w-4 h-4 text-teal-600 rounded cursor-pointer accent-teal-600"> <span class="text-xs font-medium text-slate-700">${e.username} <span class="text-[9px] text-gray-400">(${e.role})</span></span></label>`;
                });
            }
        }

        // Logic for the Select All button
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.attendee-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = source.checked;
            });
        }
        
        function openModal(id, mode = 'add', data = null) {
            document.getElementById(id).classList.remove('hidden');
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
            }
        }
        
        function openViewModal(data) {
            document.getElementById('viewModal').classList.remove('hidden');
            document.getElementById('viewTitle').innerText = data.title;
            document.getElementById('viewMessage').innerText = data.message;
            const imgEl = document.getElementById('viewImage');
            if(data.image_path) { imgEl.src = data.image_path; imgEl.classList.remove('hidden'); } else { imgEl.classList.add('hidden'); }
        }
        
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    </script>
</body>
</html>