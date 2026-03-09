<?php 
ob_start(); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. HARD LOGIN GUARD
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../include/db_connect.php';

// 2. SECURE RESUME VIEWER ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'view_resume' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = mysqli_prepare($conn, "SELECT resume_path FROM candidates WHERE candidate_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $path = $row['resume_path'];
        if (file_exists($path)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="candidate_resume.pdf"');
            readfile($path);
            exit;
        } else if (file_exists('../' . $path)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="candidate_resume.pdf"');
            readfile('../' . $path);
            exit;
        }
    }
    die("Error: Resume file not found or access denied.");
}

// --- CSRF PROTECTION ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- AJAX API ENDPOINTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate CSRF for ALL POST requests
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(["status" => "error", "message" => "CSRF Token Validation Failed"]); exit;
    }

    $action = $_POST['action'] ?? '';

    // 0. ELITE FEATURE: REAL-TIME CANDIDATE SYNC
    if ($action === 'get_all_candidates') {
        header('Content-Type: application/json');
        $c_arr = [];
        $fetch_query = "SELECT candidate_id as id, name, email, phone, match_score, DATE(created_at) as added, status, resume_path, skills, gaps, experience_years, resume_summary, recommendation, pipeline_stage FROM candidates ORDER BY match_score DESC, experience_years DESC";
        $fetch_result = mysqli_query($conn, $fetch_query);
        $rank = 1;
        if ($fetch_result) {
            while ($row = mysqli_fetch_assoc($fetch_result)) {
                $row['img'] = "https://ui-avatars.com/api/?name=" . urlencode($row['name']) . "&background=random";
                $path = $row['resume_path'];
                if (strpos($path, 'xampp') !== false || strpos($path, 'C:') !== false) {
                    $parts = explode('uploads', $path);
                    if (count($parts) > 1) { $path = '../uploads' . str_replace('\\', '/', $parts[1]); }
                }
                if (strpos($path, '../') === false && strpos($path, 'uploads/') !== false) { $path = '../' . $path; }
                $row['display_path'] = $path;
                $row['ai_rank'] = $rank++;
                if(empty($row['pipeline_stage'])) $row['pipeline_stage'] = 'Applied';
                $c_arr[] = $row;
            }
        }
        while (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode(["status" => "success", "data" => $c_arr], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // 1. DELETE CANDIDATE (CASCADING DELETES)
    if ($action === 'delete') {
        header('Content-Type: application/json');
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = mysqli_prepare($conn, "SELECT resume_path FROM candidates WHERE candidate_id = ?");
            mysqli_stmt_bind_param($stmt, "s", $id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                if (file_exists($row['resume_path'])) @unlink($row['resume_path']); 
                else if (file_exists('../' . $row['resume_path'])) @unlink('../' . $row['resume_path']);
            }
            
            $stmt1 = mysqli_prepare($conn, "DELETE FROM candidate_notes WHERE candidate_id = ?");
            mysqli_stmt_bind_param($stmt1, "s", $id); mysqli_stmt_execute($stmt1);
            
            $stmt2 = mysqli_prepare($conn, "DELETE FROM interviews WHERE candidate_id = ?");
            mysqli_stmt_bind_param($stmt2, "s", $id); mysqli_stmt_execute($stmt2);

            $stmt3 = mysqli_prepare($conn, "DELETE FROM candidates WHERE candidate_id = ?");
            mysqli_stmt_bind_param($stmt3, "s", $id);
            if (mysqli_stmt_execute($stmt3)) {
                while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "success"]);
            } else { 
                while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error"]); 
            }
        }
        exit; 
    }

    // 2. UPDATE KANBAN STAGE
    if ($action === 'update_stage') {
        header('Content-Type: application/json');
        $id = $_POST['id'] ?? '';
        $stage = $_POST['stage'] ?? 'Applied';
        $stmt = mysqli_prepare($conn, "UPDATE candidates SET pipeline_stage = ? WHERE candidate_id = ?");
        mysqli_stmt_bind_param($stmt, "ss", $stage, $id);
        if (mysqli_stmt_execute($stmt)) { 
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "success"]); 
        } else { 
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error"]); 
        }
        exit;
    }

    // 3. SCHEDULE INTERVIEW
    if ($action === 'schedule_interview') {
        header('Content-Type: application/json');
        $cand_id = $_POST['candidate_id'];
        $datetime = $_POST['date'] . ' ' . $_POST['time'];
        $type = $_POST['type'];
        $interviewer = $_POST['interviewer'];
        $notes = $_POST['notes'];
        
        $stmt = mysqli_prepare($conn, "INSERT INTO interviews (candidate_id, interview_datetime, interview_type, interviewer, notes) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssss", $cand_id, $datetime, $type, $interviewer, $notes);
        if (mysqli_stmt_execute($stmt)) {
            $upd = mysqli_prepare($conn, "UPDATE candidates SET pipeline_stage = 'Interview Scheduled' WHERE candidate_id = ?");
            mysqli_stmt_bind_param($upd, "s", $cand_id);
            mysqli_stmt_execute($upd);
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "success"]);
        } else { 
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error", "message" => mysqli_error($conn)]); 
        }
        exit;
    }

    // 4. ADD NOTE
    if ($action === 'add_note') {
        header('Content-Type: application/json');
        $cand_id = $_POST['candidate_id'];
        $note_text = $_POST['note_text'];
        $author = $_SESSION['name'] ?? 'Recruiter'; 
        
        $stmt = mysqli_prepare($conn, "INSERT INTO candidate_notes (candidate_id, author_name, note_text) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $cand_id, $author, $note_text);
        if (mysqli_stmt_execute($stmt)) {
            $note_id = mysqli_insert_id($conn);
            $date = date('Y-m-d h:i A');
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "success", "data" => ["id"=>$note_id, "author"=>$author, "text"=>$note_text, "date"=>$date]]);
        } else { 
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error"]); 
        }
        exit;
    }

    // 5. GET FULL PROFILE
    if ($action === 'get_profile') {
        header('Content-Type: application/json');
        $cand_id = $_POST['id'];
        
        $notes = [];
        $n_stmt = mysqli_prepare($conn, "SELECT author_name, note_text, DATE_FORMAT(created_at, '%Y-%m-%d %h:%i %p') as dt FROM candidate_notes WHERE candidate_id = ? ORDER BY created_at DESC");
        mysqli_stmt_bind_param($n_stmt, "s", $cand_id);
        mysqli_stmt_execute($n_stmt);
        $n_res = mysqli_stmt_get_result($n_stmt);
        while($n = mysqli_fetch_assoc($n_res)) { $notes[] = $n; }

        $interviews = [];
        $i_stmt = mysqli_prepare($conn, "SELECT interview_type, interviewer, DATE_FORMAT(interview_datetime, '%b %d, %Y') as dt, TIME_FORMAT(interview_datetime, '%h:%i %p') as tm FROM interviews WHERE candidate_id = ? ORDER BY interview_datetime DESC");
        mysqli_stmt_bind_param($i_stmt, "s", $cand_id);
        mysqli_stmt_execute($i_stmt);
        $i_res = mysqli_stmt_get_result($i_stmt);
        while($i = mysqli_fetch_assoc($i_res)) { $interviews[] = $i; }

        while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "success", "notes" => $notes, "interviews" => $interviews]);
        exit;
    }

    // 6. UPLOAD & PROCESS AI RESUME (ULTIMATE BULLETPROOF FIX)
    if (isset($_FILES['resume'])) {
        header('Content-Type: application/json');
        
        // Massive timeout allowance for heavy ML models
        set_time_limit(500); 
        session_write_close(); 
        
        $keywords = $_POST['keywords'] ?? '';
        $year_month = date('Y/m');
        $target_dir = "../uploads/resumes/{$year_month}/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

        $file = $_FILES['resume'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($type !== 'application/pdf') {
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error", "message" => "Security Alert: Only true PDF files allowed."]); exit;
        }

        $clean_filename = preg_replace("/[^a-zA-Z0-9.]/", "_", basename($file['name']));
        $filename = time() . '_' . $clean_filename;
        $target_filepath = $target_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_filepath)) {
            
            $base_dir = dirname(__DIR__); 
            $absolute_script_path = $base_dir . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "ats_engine.py";
            $absolute_pdf_path = $base_dir . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "resumes" . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR . $filename;
            
            $pythonExe = "python";
            $command = escapeshellarg($pythonExe) . " " . escapeshellarg($absolute_script_path) . " " . escapeshellarg($absolute_pdf_path) . " " . escapeshellarg($keywords);
            
            // RUN PYTHON
            $output = shell_exec($command . " 2>&1");
            
            // BULLETPROOF RECONNECT: If MySQL timed out, catch it and force a reconnect
            try {
                if (!@mysqli_ping($conn)) {
                    global $host, $user, $pass, $db;
                    $conn = mysqli_connect($host, $user, $pass, $db);
                }
            } catch (Exception $e) {
                global $host, $user, $pass, $db;
                $conn = mysqli_connect($host, $user, $pass, $db);
            }
            
            if ($conn) {
                mysqli_set_charset($conn, "utf8mb4");
            } else {
                while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error", "message" => "Database connection lost during AI processing."]); exit;
            }
            
            $result = null;
            if ($output) {
                // STRICT JSON EXTRACTION (Ignores all python terminal spam)
                if (preg_match('/\{[\s\S]*\}/', $output, $matches)) {
                    $result = json_decode($matches[0], true);
                }
            }

            if (!$result || !isset($result['status']) || $result['status'] !== 'success') {
                while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error", "message" => "AI Engine Error.", "details" => $output]); exit;
            }

            $email = $result['email'];
            $file_hash = $result['file_hash'] ?? '';
            
            $dup_query = "SELECT id FROM candidates WHERE (email = ? AND email != 'N/A') OR file_hash = ?";
            $dup_stmt = mysqli_prepare($conn, $dup_query);
            mysqli_stmt_bind_param($dup_stmt, "ss", $email, $file_hash);
            mysqli_stmt_execute($dup_stmt);
            mysqli_stmt_store_result($dup_stmt);
            
            if (mysqli_stmt_num_rows($dup_stmt) > 0) {
                @unlink($target_filepath); 
                while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error", "message" => "Duplicate Resume Detected!"]); exit;
            }

            $cand_id = "Cand-" . date('Ymd') . "-" . rand(1000, 9999);
            
            $query = "INSERT INTO candidates (candidate_id, name, email, applied_role, phone, resume_path, skills, gaps, match_score, status, experience_years, resume_summary, recommendation, file_hash, pipeline_stage) 
                      VALUES (?, ?, ?, 'AI Processed', ?, ?, ?, ?, ?, 'Parsed', ?, ?, ?, ?, 'Applied')";
            
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssssssssssss", 
                $cand_id, $result['name'], $result['email'], $result['phone'], $target_filepath, 
                $result['skills'], $result['gaps'], $result['match_score'], $result['experience_years'], 
                $result['resume_summary'], $result['recommendation'], $result['file_hash']
            );
            
            if (mysqli_stmt_execute($stmt)) {
                while (ob_get_level() > 0) { ob_end_clean(); } 
                echo json_encode(["status" => "success", "message" => "Processed successfully!"], JSON_INVALID_UTF8_SUBSTITUTE);
            } else { 
                while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error", "message" => "DB Insert Failed"]); 
            }
        } else { 
            while (ob_get_level() > 0) { ob_end_clean(); } echo json_encode(["status" => "error", "message" => "File upload error."]); 
        }
        exit; 
    }
}

// --- FETCH INITIAL DATA FOR UI ---
$candidates_array = [];
$fetch_query = "SELECT candidate_id as id, name, email, phone, match_score, DATE(created_at) as added, status, skills, gaps, experience_years, resume_summary, recommendation, pipeline_stage FROM candidates ORDER BY match_score DESC, experience_years DESC";
$fetch_result = mysqli_query($conn, $fetch_query);
$rank = 1;

if ($fetch_result) {
    while ($row = mysqli_fetch_assoc($fetch_result)) {
        $row['img'] = "https://ui-avatars.com/api/?name=" . urlencode($row['name']) . "&background=random";
        $row['ai_rank'] = $rank++;
        if(empty($row['pipeline_stage'])) $row['pipeline_stage'] = 'Applied';
        $candidates_array[] = $row;
    }
}
$candidates_json = json_encode($candidates_array, JSON_INVALID_UTF8_SUBSTITUTE);

include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise ATS | HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <style>
        :root { --primary: #0f766e; --bg-body: #f3f4f6; }
        body { background: var(--bg-body); font-family: 'Inter', sans-serif; overflow-x: hidden; }
        main#content-wrapper { margin-left: 100px; padding: 20px 20px 20px; transition: margin 0.3s; }
        .sidebar-secondary.open ~ main#content-wrapper { margin-left: 280px; }

        .glass-card { background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .upload-zone { border: 2px dashed #0f766e; background-color: #ffffff; transition: all 0.3s ease; }
        .upload-zone:hover, .upload-zone.dragover { background-color: #f0fdfa; border-color: #0f766e; transform: scale(1.01); }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

        .score-bar-bg { background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden; width: 60px; }
        .score-bar-fill { height: 100%; border-radius: 3px; transition: width 1s ease; }
        
        .skill-tag { display: inline-block; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; background: #f1f5f9; color: #475569; margin-right: 4px; margin-bottom: 2px; border: 1px solid #e2e8f0; }
        .gap-tag { display: inline-block; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; background: #fef2f2; color: #dc2626; margin-right: 4px; margin-bottom: 2px; border: 1px solid #fecaca; }

        .rank-badge { position: absolute; top: -10px; left: -10px; background: linear-gradient(135deg, #f59e0b, #ea580c); color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 0.7rem; font-weight: 900; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 10; }

        /* Kanban Specifics */
        .kanban-col { min-width: 320px; max-width: 320px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; flex-direction: column; max-height: 800px; }
        .kanban-header { padding: 12px 16px; border-bottom: 2px solid #e2e8f0; font-weight: 700; color: #334155; display: flex; justify-content: space-between; align-items: center; }
        .kanban-body { padding: 12px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; min-height: 150px; }
        .kanban-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: grab; position: relative; }
        .kanban-card:active { cursor: grabbing; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transform: scale(1.02); }

        /* Profile Slide-Out Panel */
        .profile-panel { position: fixed; top: 0; right: -600px; width: 500px; max-width: 100vw; height: 100vh; background: #fff; z-index: 10000; box-shadow: -5px 0 25px rgba(0,0,0,0.1); transition: right 0.3s ease-in-out; display: flex; flex-direction: column; }
        .profile-panel.open { right: 0; }
        .panel-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; display: none; backdrop-filter: blur(2px); }
        .panel-overlay.open { display: block; }

        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 30000; display: flex; flex-direction: column; gap: 10px; }
        .toast { min-width: 300px; padding: 16px; background: white; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-left: 4px solid var(--primary); display: flex; align-items: center; animation: slideInRight 0.3s ease-out; }
        @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }
        
        @media (max-width: 768px) {
            .profile-panel { width: 100vw; }
            .kanban-col { min-width: 280px; max-width: 280px; }
        }
    </style>
</head>
<body>

<main id="content-wrapper">
    <div class="max-w-[1600px] mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
            <div>
                <h1 class="text-3xl font-black text-gray-800 tracking-tight">Enterprise ATS <span class="text-teal-600">Pipeline</span></h1>
                <p class="text-gray-500 mt-1 font-medium flex items-center gap-2"><i class="fa-solid fa-microchip text-teal-500"></i> AI Auto-Shortlisting & Ranking Engine</p>
            </div>
            <div class="flex flex-wrap gap-3 mt-4 md:mt-0 w-full md:w-auto">
                 <button onclick="window.location.href='ats_calendar.php'" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg font-bold shadow-sm transition-all flex items-center gap-2 flex-1 md:flex-none justify-center"><i class="fa-regular fa-calendar text-teal-600"></i> View Calendar</button>
                 <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-100 flex-1 md:flex-none text-center md:text-left"><span class="text-xs text-gray-400 uppercase font-bold">Total Resumes</span><div class="text-xl font-bold text-gray-800" id="totalCount">0</div></div>
            </div>
        </div>

        <div class="glass-card p-4 sm:p-6 mb-6">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-2">1. Target Job Keywords <span class="text-red-500">*</span></label>
                <input type="text" id="keywordsInput" placeholder="e.g. Python, React, AWS, Leadership" class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none shadow-sm font-medium">
            </div>

            <label class="block text-sm font-bold text-gray-700 mb-2">2. Upload Resumes (Duplicate Detection Active)</label>
            <div id="dropZone" class="upload-zone rounded-xl p-6 sm:h-32 flex flex-col items-center justify-center cursor-pointer relative overflow-hidden">
                <div class="flex flex-col sm:flex-row items-center text-center sm:text-left gap-4 z-10 w-full justify-center">
                    <i class="fa-solid fa-cloud-arrow-up text-4xl text-teal-600"></i>
                    <div>
                        <p class="text-base font-bold text-gray-800">Drag & Drop PDF Resumes</p>
                        <p class="text-xs text-gray-500 mt-1">AI extracts Experience, Strengths, Gaps & Summary</p>
                    </div>
                    <button onclick="document.getElementById('fileInput').click(); event.stopPropagation();" class="sm:ml-4 bg-teal-700 hover:bg-teal-800 text-white px-6 py-2 rounded-lg font-bold shadow-lg transition-all w-full sm:w-auto mt-2 sm:mt-0">Browse Files</button>
                </div>
                <input type="file" id="fileInput" multiple accept=".pdf" class="hidden">
            </div>
            <div id="processingQueue" class="mt-4 hidden"><div id="queueList" class="space-y-2"></div></div>
        </div>

        <div class="glass-card overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-100 bg-white flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
                <div class="flex bg-gray-100 p-1 rounded-lg w-full xl:w-auto justify-center">
                    <button id="btnListView" onclick="toggleView('list')" class="px-4 py-1.5 text-sm font-bold bg-white text-teal-700 rounded shadow-sm flex-1 xl:flex-none">Table List</button>
                    <button id="btnKanbanView" onclick="toggleView('kanban')" class="px-4 py-1.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition flex-1 xl:flex-none">Kanban Pipeline</button>
                </div>
                <div class="flex gap-2 items-center flex-wrap w-full xl:w-auto">
                    <div class="relative flex items-center flex-1 sm:flex-none min-w-[200px]"><i class="fa-solid fa-search absolute left-3 text-gray-400 text-xs"></i><input type="text" id="searchInput" placeholder="Search Names/Emails..." class="w-full pl-8 pr-3 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-teal-500 outline-none"></div>
                    <select id="filterExp" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-medium outline-none flex-1 sm:flex-none"><option value="0">All Experience</option><option value="2">2+ Years</option><option value="5">5+ Years</option></select>
                    <select id="filterScore" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-medium outline-none flex-1 sm:flex-none"><option value="0">All Scores</option><option value="50">> 50% Match</option><option value="80">> 80% Match</option></select>
                </div>
            </div>

            <div id="listView" class="overflow-x-auto w-full block">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                            <th class="p-4 font-bold">Candidate Info</th>
                            <th class="p-4 font-bold">AI Insights</th>
                            <th class="p-4 font-bold text-center">Score</th>
                            <th class="p-4 font-bold">Stage</th>
                            <th class="p-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="text-sm text-gray-700"></tbody>
                </table>
            </div>

            <div id="kanbanView" class="p-6 overflow-x-auto w-full hidden bg-gray-50/50 custom-scroll">
                <div class="flex gap-5 pb-4 min-h-[600px] w-max">
                    <div class="kanban-col" data-stage="Applied"><div class="kanban-header border-b-blue-200">Applied <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full count-badge">0</span></div><div class="kanban-body sortable-list"></div></div>
                    <div class="kanban-col" data-stage="Screening"><div class="kanban-header border-b-purple-200">Screening <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full count-badge">0</span></div><div class="kanban-body sortable-list"></div></div>
                    <div class="kanban-col" data-stage="Interview Scheduled"><div class="kanban-header border-b-orange-200">Interview <span class="bg-orange-100 text-orange-700 text-xs px-2 py-0.5 rounded-full count-badge">0</span></div><div class="kanban-body sortable-list"></div></div>
                    <div class="kanban-col" data-stage="Offer Sent"><div class="kanban-header border-b-teal-200">Offer Sent <span class="bg-teal-100 text-teal-700 text-xs px-2 py-0.5 rounded-full count-badge">0</span></div><div class="kanban-body sortable-list"></div></div>
                    <div class="kanban-col" data-stage="Hired"><div class="kanban-header border-b-green-200">Hired <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full count-badge">0</span></div><div class="kanban-body sortable-list"></div></div>
                    <div class="kanban-col" data-stage="Rejected"><div class="kanban-header border-b-red-200">Rejected <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full count-badge">0</span></div><div class="kanban-body sortable-list"></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-overlay" id="panelOverlay" onclick="closeProfile()"></div>
    <div class="profile-panel" id="profilePanel">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h2 class="text-lg font-bold text-gray-800">Candidate Profile</h2>
            <button onclick="closeProfile()" class="text-gray-400 hover:text-gray-700"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto" id="profileContent"></div>
    </div>

    <div id="resumeModal" class="fixed inset-0 z-[20000] hidden bg-black bg-opacity-60 flex items-center justify-center backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl h-[90vh] flex flex-col overflow-hidden shadow-2xl relative">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800"><i class="far fa-file-pdf text-red-500 mr-2 text-xl"></i> Resume Viewer</h3>
                <button onclick="closeModal('resumeModal')" class="text-gray-400 hover:text-red-500 h-8 w-8 rounded-full hover:bg-red-50"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="flex-1 bg-gray-200 p-2"><iframe id="resumeIframe" src="" class="w-full h-full border-0 rounded" style="background: white;"></iframe></div>
        </div>
    </div>

    <div id="interviewModal" class="fixed inset-0 z-[20000] hidden bg-black bg-opacity-60 flex items-center justify-center backdrop-blur-sm p-4">
        <div class="bg-white rounded-xl w-full max-w-md flex flex-col overflow-hidden shadow-2xl relative">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800"><i class="fa-regular fa-calendar-check text-teal-600 mr-2"></i> Schedule Interview</h3>
                <button onclick="closeModal('interviewModal')" class="text-gray-400 hover:text-red-500"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-5">
                <input type="hidden" id="int_cand_id">
                <div class="mb-4"><label class="block text-xs font-bold text-gray-600 mb-1">Date</label><input type="date" id="int_date" class="w-full p-2 border rounded outline-none focus:border-teal-500"></div>
                <div class="mb-4"><label class="block text-xs font-bold text-gray-600 mb-1">Time</label><input type="time" id="int_time" class="w-full p-2 border rounded outline-none focus:border-teal-500"></div>
                <div class="mb-4"><label class="block text-xs font-bold text-gray-600 mb-1">Type</label><select id="int_type" class="w-full p-2 border rounded outline-none focus:border-teal-500"><option>Technical Round</option><option>HR Round</option><option>Final Round</option></select></div>
                <div class="mb-4"><label class="block text-xs font-bold text-gray-600 mb-1">Interviewer</label><input type="text" id="int_interviewer" class="w-full p-2 border rounded outline-none focus:border-teal-500"></div>
                <button onclick="submitInterview()" class="w-full bg-teal-600 text-white font-bold py-2.5 rounded hover:bg-teal-700 transition">Schedule & Move to Pipeline</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
</main>

<script>
const csrfToken = "<?= $csrf_token ?>";
let candidates = <?php echo $candidates_json ?: '[]'; ?>;
let currentView = 'list';
let currentProfileId = null;

const tableBody = document.getElementById('tableBody');
const searchInput = document.getElementById('searchInput');

function init() { setupEventListeners(); refreshUI(); initSortable(); }

// 0. ELITE FEATURE: MEMORY SYNC API
async function reloadCandidatesFromAPI() {
    const fd = new FormData();
    fd.append('action', 'get_all_candidates');
    fd.append('csrf_token', csrfToken);
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.status === 'success') {
            candidates = data.data;
            refreshUI();
        }
    } catch(e) { console.error("Sync error:", e); }
}

function updateStats() { document.getElementById('totalCount').innerText = candidates.length; }

function toggleView(view) {
    currentView = view;
    if(view === 'list') {
        document.getElementById('btnListView').className = 'px-4 py-1.5 text-sm font-bold bg-white text-teal-700 rounded shadow-sm flex-1 xl:flex-none';
        document.getElementById('btnKanbanView').className = 'px-4 py-1.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition flex-1 xl:flex-none';
        document.getElementById('listView').classList.remove('hidden'); document.getElementById('kanbanView').classList.add('hidden');
    } else {
        document.getElementById('btnKanbanView').className = 'px-4 py-1.5 text-sm font-bold bg-white text-teal-700 rounded shadow-sm flex-1 xl:flex-none';
        document.getElementById('btnListView').className = 'px-4 py-1.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition flex-1 xl:flex-none';
        document.getElementById('kanbanView').classList.remove('hidden'); document.getElementById('listView').classList.add('hidden');
    }
    refreshUI();
}

function getFilteredData() {
    let filtered = candidates.filter(c => {
        const term = searchInput.value.toLowerCase();
        const minExp = parseInt(document.getElementById('filterExp').value);
        const minScore = parseInt(document.getElementById('filterScore').value);
        return (c.name.toLowerCase().includes(term) || (c.email && c.email.toLowerCase().includes(term))) && 
               ((parseInt(c.experience_years)||0) >= minExp) && (parseInt(c.match_score) >= minScore);
    });
    filtered.sort((a,b) => a.ai_rank - b.ai_rank);
    return filtered;
}

function refreshUI() { updateStats(); const data = getFilteredData(); if (currentView === 'list') renderTable(data); else renderKanban(data); }

// 0. ELITE FEATURE: AI AUTO-SHORTLISTING VISUAL GROUPS
function renderTable(data) {
    tableBody.innerHTML = '';
    if (data.length === 0) { tableBody.innerHTML = `<tr><td colspan="5" class="p-10 text-center text-gray-500 font-medium">No candidates match criteria.</td></tr>`; return; }
    
    const topCandidates = data.filter(c => parseInt(c.match_score) >= 80);
    const midCandidates = data.filter(c => parseInt(c.match_score) >= 60 && parseInt(c.match_score) < 80);
    const weakCandidates = data.filter(c => parseInt(c.match_score) < 60);

    const renderGroup = (groupData, groupTitle, groupClass, icon) => {
        if(groupData.length === 0) return;
        
        tableBody.innerHTML += `
            <tr class="bg-gray-50/80 border-y border-gray-200">
                <td colspan="5" class="px-4 py-2 font-black text-xs uppercase tracking-widest ${groupClass}">
                    ${icon} ${groupTitle} (${groupData.length})
                </td>
            </tr>
        `;

        groupData.forEach(c => {
            const score = parseInt(c.match_score);
            let colorClass = score >= 75 ? 'bg-green-500' : (score >= 50 ? 'bg-yellow-500' : 'bg-red-500');
            let strengthsHtml = ''; let gapsHtml = '';
            if(c.skills && c.skills !== 'No specific matches') { c.skills.split(',').slice(0, 3).forEach(s => strengthsHtml += `<span class="skill-tag"><i class="fa-solid fa-check text-green-500 mr-1"></i>${s.trim()}</span>`); }
            if(c.gaps && c.gaps !== 'No major gaps detected') { c.gaps.split(',').slice(0, 2).forEach(s => gapsHtml += `<span class="gap-tag"><i class="fa-solid fa-xmark mr-1"></i>${s.trim()}</span>`); }

            tableBody.innerHTML += `
                <tr class="hover:bg-gray-50 border-b border-gray-50 transition-colors">
                    <td class="p-4 min-w-[250px]">
                        <div class="flex items-start gap-3 relative">
                            ${c.ai_rank <= 3 ? `<div class="rank-badge">#${c.ai_rank}</div>` : ''}
                            <img src="${c.img}" class="w-10 h-10 rounded-full border border-gray-200 mt-1">
                            <div>
                                <div class="font-bold text-gray-800 cursor-pointer hover:text-teal-600 transition" onclick="openProfile('${c.id}')">${c.name}</div>
                                <div class="text-xs text-gray-500"><i class="fa-solid fa-briefcase text-gray-400 mr-1"></i> ${c.experience_years||0} Yrs • ${c.email}</div>
                                <div class="text-[11px] text-gray-500 mt-1 italic border-l-2 border-teal-200 pl-2">"${c.resume_summary ? c.resume_summary.substring(0,60)+'...' : ''}"</div>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 w-1/4 min-w-[200px]"><div class="mb-1">${strengthsHtml}</div>${gapsHtml}</td>
                    <td class="p-4 text-center min-w-[100px]"><div class="font-black text-lg text-gray-700">${score}%</div><div class="score-bar-bg mx-auto"><div class="score-bar-fill ${colorClass}" style="width: ${score}%"></div></div></td>
                    <td class="p-4 min-w-[120px]"><span class="px-2 py-1 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100">${c.pipeline_stage}</span></td>
                    <td class="p-4 text-right min-w-[100px]">
                        <button onclick="openProfile('${c.id}')" class="action-btn w-8 h-8 rounded bg-white border border-gray-200 text-teal-600 hover:bg-teal-50 shadow-sm transition" title="View Profile"><i class="fa-solid fa-user-circle"></i></button>
                    </td>
                </tr>
            `;
        });
    };

    renderGroup(topCandidates, "Top Candidates (80%+ Match)", "text-emerald-700", "⭐");
    renderGroup(midCandidates, "Medium Fit (60% - 79%)", "text-amber-600", "⚠️");
    renderGroup(weakCandidates, "Weak Fit (< 60%)", "text-rose-600", "❌");
}

function renderKanban(data) {
    document.querySelectorAll('.kanban-body').forEach(col => { col.innerHTML = ''; col.parentElement.querySelector('.count-badge').innerText = data.filter(c => c.pipeline_stage === col.parentElement.dataset.stage).length; });
    data.forEach(c => {
        const col = document.querySelector(`.kanban-col[data-stage="${c.pipeline_stage}"] .kanban-body`);
        if(!col) return;
        const score = parseInt(c.match_score);
        let colorClass = score >= 75 ? 'text-green-600' : (score >= 50 ? 'text-yellow-600' : 'text-red-600');
        const card = document.createElement('div'); card.className = 'kanban-card group'; card.dataset.id = c.id;
        card.innerHTML = `
            ${c.ai_rank <= 3 ? `<div class="rank-badge scale-75 top-[-8px] left-[-8px]">#${c.ai_rank}</div>` : ''}
            <div class="flex justify-between items-start mb-2" onclick="openProfile('${c.id}')">
                <div class="flex items-center gap-2 cursor-pointer">
                    <img src="${c.img}" class="w-7 h-7 rounded-full">
                    <div><div class="font-bold text-sm text-gray-800 leading-tight">${c.name}</div><div class="text-[10px] text-gray-500">${c.experience_years||0} Yrs Exp</div></div>
                </div>
                <span class="font-black text-xs ${colorClass}">${score}%</span>
            </div>
            <div class="flex justify-between pt-2 border-t border-gray-100 opacity-0 group-hover:opacity-100 transition-opacity">
                <button onclick="openScheduleModal('${c.id}'); event.stopPropagation();" class="text-[10px] font-bold text-orange-600 hover:underline"><i class="fa-regular fa-calendar-plus"></i> Schedule</button>
                <div class="flex gap-2">
                    <button onclick="openResumeModal('${c.id}'); event.stopPropagation();" class="text-[10px] font-bold text-teal-600 hover:underline"><i class="far fa-file-pdf"></i> View</button>
                    <button onclick="deleteCandidate('${c.id}'); event.stopPropagation();" class="text-[10px] font-bold text-red-500 hover:underline"><i class="far fa-trash-alt"></i></button>
                </div>
            </div>
        `;
        col.appendChild(card);
    });
}

function initSortable() {
    document.querySelectorAll('.sortable-list').forEach(list => {
        new Sortable(list, {
            group: 'shared', animation: 150, ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                const candId = evt.item.dataset.id;
                const newStage = evt.to.parentElement.dataset.stage;
                const cand = candidates.find(c => c.id === candId);
                if(cand && cand.pipeline_stage !== newStage) {
                    cand.pipeline_stage = newStage;
                    const fd = new FormData(); fd.append('action', 'update_stage'); fd.append('id', candId); fd.append('stage', newStage); fd.append('csrf_token', csrfToken);
                    fetch(window.location.href, { method: 'POST', body: fd }).then(()=> reloadCandidatesFromAPI());
                    refreshUI();
                }
            },
        });
    });
}

// ELITE DEBOUNCE SEARCH (Prevents UI lockups on large datasets)
let searchTimeout;
function setupEventListeners() {
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(refreshUI, 300);
    });
    document.getElementById('filterExp').addEventListener('change', refreshUI);
    document.getElementById('filterScore').addEventListener('change', refreshUI);
    const dropZone = document.getElementById('dropZone'); const fileInput = document.getElementById('fileInput');
    dropZone.addEventListener('click', () => fileInput.click());
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => dropZone.addEventListener(eName, e => { e.preventDefault(); e.stopPropagation(); }, false));
    ['dragenter', 'dragover'].forEach(eName => dropZone.addEventListener(eName, () => dropZone.classList.add('dragover'), false));
    ['dragleave', 'drop'].forEach(eName => dropZone.addEventListener(eName, () => dropZone.classList.remove('dragover'), false));
    dropZone.addEventListener('drop', e => processFiles(e.dataTransfer.files));
    fileInput.addEventListener('change', function() { processFiles(this.files); this.value=''; });
}

let isProcessing = false;
async function processFiles(fileList) {
    const keywords = document.getElementById('keywordsInput').value.trim();
    if (!keywords) return showToast('Enter Job Keywords first.', 'error');
    if (isProcessing) return showToast('Wait for current queue.', 'error');
    const files = Array.from(fileList).filter(f => f.type === 'application/pdf');
    if (files.length === 0) return showToast('Only PDF files supported.', 'error');

    isProcessing = true; 
    document.getElementById('processingQueue').classList.remove('hidden');
    
    for (let i = 0; i < files.length; i++) {
        const id = 'q-' + Date.now() + i;
        const statusId = 'status-' + id;
        
        const queueHtml = `<div id="${id}" class="flex items-center justify-between bg-white p-3 rounded-lg border border-gray-100 shadow-sm"><span class="text-sm font-semibold text-gray-700 truncate w-3/4">${files[i].name}</span><span id="${statusId}" class="text-xs font-bold text-blue-600">Processing AI...</span></div>`;
        document.getElementById('queueList').insertAdjacentHTML('beforeend', queueHtml);
        
        const fd = new FormData(); 
        fd.append('resume', files[i]); 
        fd.append('keywords', keywords); 
        fd.append('csrf_token', csrfToken);
        
        try {
            const response = await fetch(window.location.href, { method: 'POST', body: fd });
            const rawText = await response.text();
            let data;
            
            try { 
                data = JSON.parse(rawText); 
            } catch(e) { 
                console.error("Backend Error Response:", rawText);
                const statusEl = document.getElementById(statusId);
                if (statusEl) {
                    statusEl.className = 'text-xs font-bold text-red-600'; 
                    statusEl.innerText = 'System Error';
                }
                showToast("Error communicating with AI engine.", 'error');
                continue; 
            }

            const statusEl = document.getElementById(statusId);
            if (data.status === 'success') {
                if (statusEl) {
                    statusEl.className = 'text-xs font-bold text-green-600'; 
                    statusEl.innerText = 'Success!';
                }
                await reloadCandidatesFromAPI();
                showToast(`${files[i].name} analyzed!`);
            } else {
                if (statusEl) {
                    statusEl.className = 'text-xs font-bold text-red-600'; 
                    statusEl.innerText = 'Error';
                }
                showToast(data.message, 'error');
            }
        } catch(e) { 
            // Graceful JS Fallback on HTTP Timeout
            const statusEl = document.getElementById(statusId);
            if (statusEl) {
                statusEl.className = 'text-xs font-bold text-orange-600'; 
                statusEl.innerText = 'Syncing...';
            }
            showToast(`Processing ${files[i].name}. It will appear shortly.`, 'error');
            setTimeout(() => reloadCandidatesFromAPI(), 4000);
        }
    }
    isProcessing = false; 
    setTimeout(() => { document.getElementById('processingQueue').classList.add('hidden'); document.getElementById('queueList').innerHTML = ''; }, 4000);
}

// --- PROFILE SLIDE-OUT PANEL LOGIC ---
async function openProfile(id) {
    const c = candidates.find(cand => cand.id === id);
    if(!c) return;
    currentProfileId = id;
    
    document.getElementById('panelOverlay').classList.add('open');
    document.getElementById('profilePanel').classList.add('open');
    
    let content = `
        <div class="p-4 sm:p-6">
            <div class="flex gap-4 items-start mb-6 border-b border-gray-100 pb-6">
                <img src="${c.img}" class="w-16 h-16 rounded-full shadow border">
                <div class="flex-1 overflow-hidden">
                    <h3 class="text-xl font-bold text-gray-800 truncate">${c.name}</h3>
                    <p class="text-sm text-gray-500 truncate">${c.email} | ${c.phone || 'No phone'}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button onclick="openResumeModal('${c.id}')" class="px-3 py-1.5 bg-teal-50 text-teal-700 text-xs font-bold rounded hover:bg-teal-100 transition"><i class="far fa-file-pdf mr-1"></i> Resume</button>
                        <button onclick="openScheduleModal('${c.id}')" class="px-3 py-1.5 bg-orange-50 text-orange-700 text-xs font-bold rounded hover:bg-orange-100 transition"><i class="fa-regular fa-calendar-plus mr-1"></i> Schedule</button>
                        <button onclick="deleteCandidate('${c.id}'); closeProfile();" class="px-3 py-1.5 bg-red-50 text-red-600 text-xs font-bold rounded hover:bg-red-100 transition"><i class="far fa-trash-alt mr-1"></i> Delete</button>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg mb-6 border border-gray-100">
                <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">AI Summary</h4>
                <p class="text-sm text-gray-700 italic">"${c.resume_summary}"</p>
            </div>
            
            <h4 class="text-sm font-bold text-gray-800 mb-3 border-b pb-2">Recruiter Notes</h4>
            <div id="notesContainer" class="space-y-3 mb-4 max-h-48 overflow-y-auto custom-scroll pr-2">
                <div class="text-center text-gray-400 text-xs py-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
            </div>
            <div class="flex gap-2 mb-8">
                <input type="text" id="newNoteInput" placeholder="Add a note..." class="flex-1 border rounded px-3 py-2 text-sm outline-none focus:border-teal-500">
                <button onclick="addNote()" class="bg-teal-600 text-white px-4 rounded font-bold text-sm hover:bg-teal-700">Save</button>
            </div>
            
            <h4 class="text-sm font-bold text-gray-800 mb-3 border-b pb-2">Interview History</h4>
            <div id="interviewsContainer" class="space-y-3"></div>
        </div>
    `;
    document.getElementById('profileContent').innerHTML = content;

    const fd = new FormData(); fd.append('action', 'get_profile'); fd.append('id', id); fd.append('csrf_token', csrfToken);
    const res = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    
    const nCont = document.getElementById('notesContainer');
    nCont.innerHTML = '';
    if(data.notes.length === 0) nCont.innerHTML = '<p class="text-xs text-gray-400">No notes yet.</p>';
    data.notes.forEach(n => { nCont.innerHTML += `<div class="bg-white p-3 rounded border border-gray-100"><div class="flex justify-between items-center mb-1"><span class="font-bold text-xs text-gray-700">${n.author_name}</span><span class="text-[10px] text-gray-400">${n.dt}</span></div><p class="text-sm text-gray-600">${n.note_text}</p></div>`; });

    const iCont = document.getElementById('interviewsContainer');
    iCont.innerHTML = '';
    if(data.interviews.length === 0) iCont.innerHTML = '<p class="text-xs text-gray-400">No interviews scheduled.</p>';
    data.interviews.forEach(i => { iCont.innerHTML += `<div class="flex items-center gap-3 bg-gray-50 p-3 rounded border"><i class="fa-regular fa-calendar-check text-teal-600 text-lg"></i><div><p class="text-sm font-bold text-gray-800">${i.interview_type}</p><p class="text-xs text-gray-500">${i.dt} at ${i.tm} with ${i.interviewer}</p></div></div>`; });
}

function closeProfile() { document.getElementById('panelOverlay').classList.remove('open'); document.getElementById('profilePanel').classList.remove('open'); }

async function addNote() {
    const input = document.getElementById('newNoteInput');
    if(!input.value.trim()) return;
    const fd = new FormData(); fd.append('action', 'add_note'); fd.append('candidate_id', currentProfileId); fd.append('note_text', input.value); fd.append('csrf_token', csrfToken);
    
    const res = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if(data.status === 'success') {
        const nCont = document.getElementById('notesContainer');
        if(nCont.innerHTML.includes('No notes yet.')) nCont.innerHTML = '';
        nCont.innerHTML = `<div class="bg-white p-3 rounded border border-teal-100 bg-teal-50/30"><div class="flex justify-between items-center mb-1"><span class="font-bold text-xs text-teal-700">${data.data.author}</span><span class="text-[10px] text-gray-400">Just now</span></div><p class="text-sm text-gray-600">${data.data.text}</p></div>` + nCont.innerHTML;
        input.value = '';
    }
}

// MODALS & HELPERS
function openScheduleModal(id) { document.getElementById('int_cand_id').value = id; document.getElementById('interviewModal').classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// SECURE RESUME VIEWER
function openResumeModal(candId) { 
    document.getElementById('resumeIframe').src = `?action=view_resume&id=${candId}`; 
    document.getElementById('resumeModal').classList.remove('hidden'); 
}

async function submitInterview() {
    const cand_id = document.getElementById('int_cand_id').value;
    const date = document.getElementById('int_date').value;
    const time = document.getElementById('int_time').value;
    const type = document.getElementById('int_type').value;
    const interviewer = document.getElementById('int_interviewer').value;

    if (!date || !time || !interviewer) {
        return showToast('Please fill in all interview details.', 'error');
    }

    const fd = new FormData(); 
    fd.append('action', 'schedule_interview'); 
    fd.append('candidate_id', cand_id); 
    fd.append('date', date); 
    fd.append('time', time); 
    fd.append('type', type); 
    fd.append('interviewer', interviewer); 
    fd.append('notes', 'Scheduled via Dashboard'); 
    fd.append('csrf_token', csrfToken);
    
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.status === 'success') {
            showToast("Interview Scheduled Successfully!"); 
            closeModal('interviewModal');
            await reloadCandidatesFromAPI();
            if(currentProfileId === cand_id) openProfile(cand_id); 
        } else {
            showToast(data.message || "Scheduling failed.", "error");
        }
    } catch(e) { 
        showToast("Server communication error.", "error"); 
    }
}

async function deleteCandidate(id) {
    if(!confirm("Permanently delete this candidate and all history?")) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id); fd.append('csrf_token', csrfToken);
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.status === 'success') { 
            await reloadCandidatesFromAPI(); 
            showToast("Deleted successfully."); 
        }
    } catch(e) {}
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const borderClass = type === 'error' ? 'border-red-500' : 'border-teal-500';
    const iconClass = type === 'error' ? 'fa-circle-exclamation text-red-500' : 'fa-circle-check text-teal-500';
    const t = document.createElement('div'); t.className = `toast ${borderClass}`;
    t.innerHTML = `<div class="flex items-center gap-3"><i class="fa-solid ${iconClass} text-xl"></i><span class="text-sm font-bold text-gray-700">${message}</span></div>`;
    container.appendChild(t); setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(100%)'; setTimeout(() => t.remove(), 300); }, 3000);
}

init();
</script>
</body>
</html>