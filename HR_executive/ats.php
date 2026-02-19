<?php 
// Start output buffering
ob_start(); 

// 1. Database Connection
require_once '../include/db_connect.php';

// --- ACTION: DELETE CANDIDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id = $_POST['id'] ?? '';
    
    if ($id) {
        // Delete File
        $pathQuery = "SELECT resume_path FROM candidates WHERE candidate_id = ?";
        $stmt = mysqli_prepare($conn, $pathQuery);
        mysqli_stmt_bind_param($stmt, "s", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            if (file_exists($row['resume_path'])) @unlink($row['resume_path']); 
        }
        
        // Delete DB Record
        $delQuery = "DELETE FROM candidates WHERE candidate_id = ?";
        $stmt = mysqli_prepare($conn, $delQuery);
        mysqli_stmt_bind_param($stmt, "s", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            ob_clean();
            echo json_encode(["status" => "success", "message" => "Candidate deleted."]);
        } else {
            ob_clean();
            echo json_encode(["status" => "error", "message" => "Database error."]);
        }
    }
    exit; 
}

// --- ACTION: UPLOAD & PROCESS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
    header('Content-Type: application/json');
    set_time_limit(300); // 5 minutes
    
    $keywords = $_POST['keywords'] ?? '';
    $target_dir = "../uploads/resumes/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    $file = $_FILES['resume'];
    if ($file['type'] !== 'application/pdf') {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Only PDF files allowed."]);
        exit;
    }

    $clean_filename = preg_replace("/[^a-zA-Z0-9.]/", "_", basename($file['name']));
    $filename = time() . '_' . $clean_filename;
    $target_filepath = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_filepath)) {
        
        // Close DB before Python
        if ($conn) mysqli_close($conn);
        
        $pythonScript = "../scripts/ats_shortlist.py";
        // --- UPDATE THIS PATH IF NEEDED ---
        $pythonExe = "C:\\Users\\APARNA MA\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";
        
        $command = escapeshellarg($pythonExe) . " " . escapeshellarg($pythonScript) . " " . escapeshellarg($target_filepath) . " " . escapeshellarg($keywords);
        
        $output = shell_exec($command . " 2>&1");
        
        // Reconnect DB
        global $host, $user, $pass, $db;
        $conn = mysqli_connect($host, $user, $pass, $db);
        if ($conn) mysqli_set_charset($conn, "utf8mb4");
        
        // Clean output
        $json_start = strpos($output, '{');
        if ($json_start !== false) $output = substr($output, $json_start);
        
        $result = json_decode($output, true);

        if ($result && isset($result['status']) && $result['status'] === 'success') {
            
            $cand_id = "Cand-" . rand(10000, 99999);
            $applied_role = "Applied Candidate";
            $status = "Parsed";
            $skills = $result['skills'] ?? ""; 
            
            // QUERY WITH SKILLS
            $query = "INSERT INTO candidates (candidate_id, name, email, applied_role, phone, resume_path, skills, match_score, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                ob_clean();
                echo json_encode(["status" => "error", "message" => "SQL Error: " . mysqli_error($conn)]);
                exit;
            }
            
            // Added 's' for skills
            mysqli_stmt_bind_param($stmt, "sssssssis", $cand_id, $result['name'], $result['email'], $applied_role, $result['phone'], $target_filepath, $skills, $result['match_score'], $status);
            
            if (mysqli_stmt_execute($stmt)) {
                $result['id'] = $cand_id;
                $result['added'] = date('Y-m-d H:i:s');
                $result['img'] = "https://ui-avatars.com/api/?name=" . urlencode($result['name']) . "&background=random";
                $result['resume_path'] = $target_filepath;
                
                ob_clean();
                echo json_encode(["status" => "success", "message" => "Processed successfully!", "data" => $result]);
            } else {
                ob_clean();
                echo json_encode(["status" => "error", "message" => "DB Insert Failed"]);
            }
        } else {
            ob_clean();
            echo json_encode(["status" => "error", "message" => "Parsing Failed", "details" => $output]);
        }
    } else {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "File upload error."]);
    }
    exit; 
}

// --- FETCH DATA ---
 $candidates_array = [];
 $fetch_query = "SELECT candidate_id as id, name, email, phone, match_score, DATE(created_at) as added, status, resume_path, skills FROM candidates ORDER BY created_at DESC";
 $fetch_result = mysqli_query($conn, $fetch_query);

if ($fetch_result) {
    while ($row = mysqli_fetch_assoc($fetch_result)) {
        $row['img'] = "https://ui-avatars.com/api/?name=" . urlencode($row['name']) . "&background=random";
        $candidates_array[] = $row;
    }
}
 $candidates_json = json_encode($candidates_array);

include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | Advanced ATS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { 
            --primary: #0f766e; 
            --primary-light: #14b8a6; 
            --bg-body: #f3f4f6;
        }
        body { background: var(--bg-body); font-family: 'Inter', sans-serif; }
        
        main#content-wrapper { margin-left: 80px; padding: 80px 20px 20px; transition: margin 0.3s; }
        .sidebar-secondary.open ~ main#content-wrapper { margin-left: 280px; }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .upload-zone {
            background-image: url("data:image/svg+xml,%3csvg width='100%25' height='100%25' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100%25' height='100%25' fill='none' rx='16' ry='16' stroke='%230F766EFF' stroke-width='2' stroke-dasharray='12%2c 12' stroke-dashoffset='0' stroke-linecap='square'/%3e%3c/svg%3e");
            transition: all 0.3s ease;
        }
        .upload-zone:hover, .upload-zone.dragover {
            background-color: #f0fdfa;
            transform: scale(1.01);
        }

        .table-row-enter { animation: slideIn 0.4s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            min-width: 300px; padding: 16px; background: white; border-radius: 8px; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-left: 4px solid var(--primary);
            display: flex; align-items: center; justify-content: space-between;
            animation: slideInRight 0.3s ease-out;
        }
        @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }
        
        .score-bar-bg { background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden; width: 60px; }
        .score-bar-fill { height: 100%; border-radius: 3px; transition: width 1s ease; }
        
        .skill-tag {
            display: inline-block;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #475569;
            margin-right: 4px;
            margin-bottom: 2px;
            border: 1px solid #e2e8f0;
        }
        
        /* ACTIONS ALWAYS VISIBLE */
        .action-btn {
            opacity: 1 !important; /* Override hover logic */
            transition: background 0.2s;
        }
    </style>
</head>
<body>

<main id="content-wrapper">
    <div class="max-w-[1600px] mx-auto">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-end mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">AI Recruiting <span class="text-teal-600">ATS</span></h1>
                <p class="text-gray-500 mt-1">Intelligent candidate shortlist screening</p>
            </div>
            
            <div class="flex gap-3 mt-4 md:mt-0">
                 <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-100">
                    <span class="text-xs text-gray-400 uppercase font-bold">Total Resumes</span>
                    <div class="text-xl font-bold text-gray-800" id="totalCount">0</div>
                 </div>
                 <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-100">
                    <span class="text-xs text-gray-400 uppercase font-bold">Avg. Score</span>
                    <div class="text-xl font-bold text-teal-600" id="avgScore">0%</div>
                 </div>
            </div>
        </div>

        <!-- Upload Card -->
        <div class="glass-card p-8 mb-8">
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Keywords (Comma Separated)</label>
                    <input type="text" id="keywordsInput" 
                           placeholder="e.g. Python, React, SQL, Communication, Management" 
                           class="w-full p-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none transition-all">
                </div>
                <div class="flex items-end">
                    <button onclick="document.getElementById('fileInput').click()" 
                            class="bg-teal-700 hover:bg-teal-800 text-white px-6 py-3 rounded-lg font-medium shadow-lg shadow-teal-200 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-folder-open"></i> Browse Files
                    </button>
                    <input type="file" id="fileInput" multiple accept=".pdf" class="hidden">
                </div>
            </div>

            <div id="dropZone" class="upload-zone rounded-2xl h-48 flex flex-col items-center justify-center cursor-pointer relative">
                <div class="text-center pointer-events-none z-10">
                    <i class="fa-solid fa-cloud-arrow-up text-4xl text-teal-600 mb-3"></i>
                    <p class="text-lg font-semibold text-gray-700">Drag & Drop PDF Resumes Here</p>
                    <p class="text-sm text-gray-400">AI will analyze Name, Skills, Contact & Match Score</p>
                </div>
            </div>

            <!-- Processing Queue UI -->
            <div id="processingQueue" class="mt-6 hidden">
                <h4 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-3">Processing Queue</h4>
                <div id="queueList" class="space-y-2"></div>
            </div>
        </div>

        <!-- Candidates Table -->
        <div class="glass-card overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row justify-between gap-4">
                <h2 class="text-lg font-bold text-gray-800">Candidates</h2>
                <div class="flex gap-2 items-center">
                    <input type="text" id="searchInput" placeholder="Search..." class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none w-64">
                    <i class="fa-solid fa-search absolute left-3 top-3.5 text-gray-400 text-xs" style="margin-left: 8px;"></i> 
                    
                    <select id="sortBy" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white outline-none focus:ring-2 focus:ring-teal-500">
                        <option value="newest">Newest First</option>
                        <option value="score-desc">Highest Score</option>
                        <option value="score-asc">Lowest Score</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                            <th class="p-4 font-semibold">Candidate</th>
                            <th class="p-4 font-semibold">Contact</th>
                            <th class="p-4 font-semibold">Skills (Matched)</th>
                            <th class="p-4 font-semibold">Applied On</th>
                            <th class="p-4 font-semibold">AI Score</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="text-sm text-gray-700 divide-y divide-gray-100">
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t border-gray-100 flex justify-between items-center text-sm text-gray-500">
                <span id="pageInfo">Showing 0-0 of 0</span>
                <div class="flex gap-2">
                    <button id="prevBtn" class="px-3 py-1 rounded border hover:bg-gray-50 disabled:opacity-50" disabled>Prev</button>
                    <button id="nextBtn" class="px-3 py-1 rounded border hover:bg-gray-50 disabled:opacity-50" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- RESUME MODAL (Extracted from your reference code) -->
    <div id="resumeModal" class="fixed inset-0 z-[9999] hidden bg-black bg-opacity-50 flex items-center justify-center transition-opacity">
        <div class="bg-white rounded-lg w-11/12 md:w-3/4 lg:w-2/3 h-[90vh] flex flex-col overflow-hidden shadow-2xl relative">
            
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800"><i class="far fa-file-pdf text-red-500 mr-2"></i> Resume Viewer</h3>
                <button onclick="closeResumeModal()" class="text-gray-500 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="flex-1 bg-gray-100 p-2">
                <!-- Using iframe as requested -->
                <iframe id="resumeIframe" src="" class="w-full h-full border-0 rounded shadow-inner"></iframe>
            </div>
            
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
</main>

<script>
// --- State Management ---
const rawCandidates = <?php echo $candidates_json ?: '[]'; ?>;
let candidates = [...rawCandidates];
let currentPage = 1;
let rowsPerPage = 10;
let isProcessing = false;

// --- DOM Elements ---
const tableBody = document.getElementById('tableBody');
const searchInput = document.getElementById('searchInput');
const sortSelect = document.getElementById('sortBy');
const fileInput = document.getElementById('fileInput');
const dropZone = document.getElementById('dropZone');
const queueList = document.getElementById('queueList');
const processingQueue = document.getElementById('processingQueue');

// --- Initialization ---
function init() {
    updateStats();
    renderTable();
    setupEventListeners();
}

function updateStats() {
    document.getElementById('totalCount').innerText = candidates.length;
    if(candidates.length > 0) {
        const total = candidates.reduce((acc, curr) => acc + parseInt(curr.match_score || 0), 0);
        document.getElementById('avgScore').innerText = Math.round(total / candidates.length) + '%';
    }
}

// --- Table Rendering ---
function renderTable() {
    tableBody.innerHTML = '';
    
    // Filter
    let filtered = candidates.filter(c => {
        const term = searchInput.value.toLowerCase();
        return c.name.toLowerCase().includes(term) || c.email.toLowerCase().includes(term);
    });

    // Sort
    const sortVal = sortSelect.value;
    if (sortVal === 'newest') filtered.sort((a,b) => new Date(b.added) - new Date(a.added));
    if (sortVal === 'score-desc') filtered.sort((a,b) => b.match_score - a.match_score);
    if (sortVal === 'score-asc') filtered.sort((a,b) => a.match_score - b.match_score);

    // Paginate
    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const pageData = filtered.slice(start, end);

    document.getElementById('pageInfo').innerText = `Showing ${filtered.length > 0 ? start+1 : 0}-${Math.min(end, filtered.length)} of ${filtered.length}`;
    document.getElementById('prevBtn').disabled = currentPage === 1;
    document.getElementById('nextBtn').disabled = end >= filtered.length;

    if (pageData.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400">No candidates found. Upload resumes to get started.</td></tr>`;
        return;
    }

    pageData.forEach(c => {
        const score = parseInt(c.match_score);
        let colorClass = score >= 75 ? 'bg-green-500' : (score >= 50 ? 'bg-yellow-500' : 'bg-red-500');
        let textColor = score >= 75 ? 'text-green-700' : (score >= 50 ? 'text-yellow-700' : 'text-red-700');

        // Format Skills HTML
        let skillsHtml = '';
        if(c.skills && c.skills !== 'No specific matches' && c.skills !== 'N/A') {
            const skillsList = c.skills.split(',');
            skillsList.slice(0, 3).forEach(s => {
                skillsHtml += `<span class="skill-tag">${s.trim()}</span>`;
            });
            if(skillsList.length > 3) skillsHtml += `<span class="text-xs text-gray-400">+${skillsList.length - 3}</span>`;
        } else {
            skillsHtml = `<span class="text-xs text-gray-400">-</span>`;
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50 transition-colors table-row-enter group';
        tr.innerHTML = `
            <td class="p-4">
                <div class="flex items-center gap-3">
                    <img src="${c.img}" class="w-9 h-9 rounded-full border border-gray-200">
                    <div>
                        <div class="font-semibold text-gray-800">${c.name}</div>
                        <div class="text-xs text-gray-500">${c.email}</div>
                    </div>
                </div>
            </td>
            <td class="p-4 text-gray-600">
                ${c.phone || '-'}
            </td>
            <td class="p-4">
                <div class="flex flex-wrap">${skillsHtml}</div>
            </td>
            <td class="p-4 text-gray-600 text-xs">
                ${c.added}
            </td>
            <td class="p-4">
                <div class="flex items-center gap-2">
                    <span class="font-bold ${textColor} w-8">${score}%</span>
                    <div class="score-bar-bg">
                        <div class="score-bar-fill ${colorClass}" style="width: ${score}%"></div>
                    </div>
                </div>
            </td>
            <td class="p-4">
                <span class="px-2 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-100">
                    ${c.status || 'Parsed'}
                </span>
            </td>
            <td class="p-4 text-right">
                <!-- ACTIONS ALWAYS VISIBLE (class action-btn ensures opacity 1) -->
                <div class="flex justify-end gap-2">
                    <button onclick="openResumeModal('${c.resume_path}')" class="action-btn w-8 h-8 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center" title="View Resume">
                        <i class="far fa-eye text-lg"></i>
                    </button>
                    <button onclick="deleteCandidate('${c.id}')" class="action-btn w-8 h-8 rounded bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center" title="Delete">
                        <i class="far fa-trash-alt text-lg"></i>
                    </button>
                </div>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

// --- Event Listeners ---
function setupEventListeners() {
    searchInput.addEventListener('input', () => { currentPage = 1; renderTable(); });
    sortSelect.addEventListener('change', () => { currentPage = 1; renderTable(); });
    document.getElementById('prevBtn').addEventListener('click', () => { if(currentPage > 1) { currentPage--; renderTable(); }});
    document.getElementById('nextBtn').addEventListener('click', () => { currentPage++; renderTable(); });
    
    dropZone.addEventListener('click', () => fileInput.click());
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    dropZone.addEventListener('drop', handleDrop, false);
    fileInput.addEventListener('change', handleFiles, false);
}

// --- Upload Logic (Sequential Queue) ---
function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    processFiles(files);
}

function handleFiles(e) {
    const files = this.files;
    processFiles(files);
    this.value = ''; // Reset input
}

async function processFiles(fileList) {
    const keywords = document.getElementById('keywordsInput').value.trim();
    if (!keywords) return showToast('Please enter job keywords first!', 'error');
    
    if(isProcessing) return showToast('Please wait for current queue to finish.', 'error');

    const files = Array.from(fileList).filter(f => f.type === 'application/pdf');
    if (files.length === 0) return showToast('Only PDF files are supported.', 'error');

    isProcessing = true;
    processingQueue.classList.remove('hidden');
    
    const fileQueueIds = [];
    files.forEach(f => {
        const id = 'q-' + Date.now() + Math.random().toString(16).slice(2);
        fileQueueIds.push({ id, file: f });
        addQueueItemUI(id, f.name, 0);
    });

    for (let i = 0; i < fileQueueIds.length; i++) {
        const { id, file } = fileQueueIds[i];
        updateQueueItemUI(id, 'Processing...', 'text-blue-600', false);
        
        await uploadSingleFile(file, keywords, id);
        await new Promise(r => setTimeout(r, 1000));
    }

    isProcessing = false;
    setTimeout(() => {
        if(!isProcessing) processingQueue.classList.add('hidden'); 
    }, 2000);
}

function addQueueItemUI(id, filename, progress) {
    const div = document.createElement('div');
    div.id = id;
    div.className = "flex items-center justify-between bg-white p-3 rounded border border-gray-100 shadow-sm";
    div.innerHTML = `
        <div class="flex items-center gap-3 overflow-hidden">
            <i class="fa-regular fa-file-pdf text-red-500"></i>
            <span class="text-sm font-medium text-gray-700 truncate w-48">${filename}</span>
        </div>
        <div class="flex items-center gap-3">
            <span class="queue-status text-xs text-gray-400">Queued</span>
            <i class="fa-solid fa-check text-green-500 queue-icon hidden"></i>
            <i class="fa-solid fa-spinner fa-spin text-blue-500 queue-spinner hidden"></i>
        </div>
    `;
    queueList.appendChild(div);
}

function updateQueueItemUI(id, statusText, colorClass, isDone) {
    const el = document.getElementById(id);
    if (!el) return;
    
    const statusEl = el.querySelector('.queue-status');
    const checkEl = el.querySelector('.queue-icon');
    const spinEl = el.querySelector('.queue-spinner');

    statusEl.className = `queue-status text-xs ${colorClass}`;
    statusEl.innerText = statusText;

    if (isDone) {
        spinEl.classList.add('hidden');
        checkEl.classList.remove('hidden');
    } else {
        spinEl.classList.remove('hidden');
        checkEl.classList.add('hidden');
    }
}

async function uploadSingleFile(file, keywords, uiId) {
    const formData = new FormData();
    formData.append('resume', file);
    formData.append('keywords', keywords);

    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const rawText = await response.text();
        let result;
        try { result = JSON.parse(rawText); } 
        catch (e) { throw new Error("Server Response Error"); }

        if (result.status === 'success') {
            updateQueueItemUI(uiId, `Score: ${result.data.match_score}%`, 'text-green-600', true);
            showToast(`${file.name} processed (${result.data.match_score}%)`);
            
            candidates.unshift({
                id: result.data.id,
                name: result.data.name,
                email: result.data.email,
                phone: result.data.phone,
                added: result.data.added,
                skills: result.data.skills,
                match_score: result.data.match_score,
                status: 'Parsed',
                img: result.data.img,
                resume_path: result.data.resume_path
            });
            updateStats();
            renderTable();
        } else {
            updateQueueItemUI(uiId, 'Failed', 'text-red-600', true);
            showToast(`${file.name} failed: ${result.message}`, 'error');
        }

    } catch (err) {
        updateQueueItemUI(uiId, 'Network Error', 'text-red-600', true);
        showToast(`${file.name} connection failed.`, 'error');
    }
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const borderClass = type === 'error' ? 'border-red-500' : 'border-teal-500';
    const iconClass = type === 'error' ? 'fa-circle-exclamation text-red-500' : 'fa-circle-check text-teal-500';
    
    toast.className = `toast ${borderClass}`;
    toast.innerHTML = `
        <div class="flex items-center gap-3">
            <i class="fa-solid ${iconClass}"></i>
            <span class="text-sm font-medium text-gray-700">${message}</span>
        </div>
    `;
    
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// --- RESUME MODAL LOGIC (Extracted from your reference) ---
function openResumeModal(path) {
    document.getElementById('resumeIframe').src = path;
    document.getElementById('resumeModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; 
}

function closeResumeModal() {
    document.getElementById('resumeIframe').src = '';
    document.getElementById('resumeModal').classList.add('hidden');
    document.body.style.overflow = 'auto'; 
}

async function deleteCandidate(id) {
    if(!confirm("Delete this candidate permanently?")) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.status === 'success') {
            candidates = candidates.filter(c => c.id !== id);
            updateStats();
            renderTable();
            showToast("Candidate deleted");
        }
    } catch(e) { showToast("Delete failed", 'error'); }
}

init();
</script>
</body>
</html>