<?php
// attendance_card.php
ob_start(); // Ensure output buffering is active to prevent HTML bleeding

// 1. DETECT CONTEXT (AJAX API vs Standard Include)
$is_ajax_request = isset($_POST['action']) || (isset($_GET['ajax_card']) && $_GET['ajax_card'] == '1');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($is_ajax_request) {
    // Prevent Session Locking during AJAX reload
    session_write_close();
    date_default_timezone_set('Asia/Kolkata');
    
    // Dynamic DB Pathing for AJAX
    $dbPath = $_SERVER['DOCUMENT_ROOT'] . '/workack2.0/include/db_connect.php';
    if (file_exists($dbPath)) { require_once($dbPath); } 
    else { require_once('../include/db_connect.php'); }
}

$current_user_id = $_SESSION['user_id'] ?? 0;
// Ensure path_to_root exists depending on where this is included
$local_path_to_root = $path_to_root ?? '../'; 

// 2. ALWAYS FETCH SHIFT DETAILS (Crucial for accurate Late logic)
if (isset($conn)) {
    $u_sql = "SELECT shift_type, shift_timings FROM employee_profiles WHERE user_id = ?";
    $u_stmt = mysqli_prepare($conn, $u_sql);
    mysqli_stmt_bind_param($u_stmt, "i", $current_user_id);
    mysqli_stmt_execute($u_stmt);
    $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));

    // DATABASE PATCHER FOR BREAK TYPES
    $check_bt = $conn->query("SHOW COLUMNS FROM `attendance_breaks` LIKE 'break_type'");
    if ($check_bt && $check_bt->num_rows == 0) {
        $conn->query("ALTER TABLE `attendance_breaks` ADD COLUMN `break_type` VARCHAR(50) DEFAULT 'General' AFTER `attendance_id`");
    }
}

$today = date('Y-m-d');
$shift_name = $user_info['shift_type'] ?? 'General Shift';
$shift_timings = $user_info['shift_timings'] ?? '09:00 AM - 06:00 PM';

// 3. SECURE AJAX POST HANDLER (Acts as a clean API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (ob_get_length()) ob_clean(); // Wipe HTML before returning JSON
    header('Content-Type: application/json');
    
    $response = ['status' => 'error', 'message' => 'Unknown action'];
    $now = date('Y-m-d H:i:s');
    
    if ($_POST['action'] === 'punch_in') {
        $time_parts = explode('-', $shift_timings);
        $shift_start_str = count($time_parts) > 0 ? trim($time_parts[0]) : '09:00 AM';
        $expected_start_ts = strtotime($today . ' ' . $shift_start_str);
        
        $status = (strtotime($now) > ($expected_start_ts + 60)) ? 'Late' : 'On Time';
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, date, punch_in, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $current_user_id, $today, $now, $status);
        if ($stmt->execute()) $response = ['status' => 'success'];
        $stmt->close();
        
    } elseif ($_POST['action'] === 'punch_out') {
        $att_rec = $conn->query("SELECT id, punch_in FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        if ($att_rec) {
            $break_sec = 0;
            $br_q = $conn->query("SELECT * FROM attendance_breaks WHERE attendance_id = " . $att_rec['id']);
            while($br = $br_q->fetch_assoc()){ 
                if($br['break_end']) {
                    $break_sec += strtotime($br['break_end']) - strtotime($br['break_start']); 
                } else {
                    $conn->query("UPDATE attendance_breaks SET break_end = '$now' WHERE id = " . $br['id']);
                    $break_sec += strtotime($now) - strtotime($br['break_start']); 
                }
            }
            $prod_hours = max(0, (time() - strtotime($att_rec['punch_in'])) - $break_sec) / 3600;
            $stmt = $conn->prepare("UPDATE attendance SET punch_out = ?, production_hours = ? WHERE id = ?");
            $stmt->bind_param("sdi", $now, $prod_hours, $att_rec['id']);
            if ($stmt->execute()) $response = ['status' => 'success'];
        }
        
    } elseif ($_POST['action'] === 'take_break') {
        $b_type = $_POST['break_type'] ?? 'General';
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        if ($att_rec) {
            $stmt = $conn->prepare("INSERT INTO attendance_breaks (attendance_id, break_start, break_type) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $att_rec['id'], $now, $b_type);
            if($stmt->execute()) {
                $conn->query("UPDATE attendance SET break_time = '1' WHERE id = " . $att_rec['id']);
                $response = ['status' => 'success'];
            }
        }
        
    } elseif ($_POST['action'] === 'break_end') {
        $att_rec = $conn->query("SELECT id FROM attendance WHERE user_id = $current_user_id AND date = '$today'")->fetch_assoc();
        if ($att_rec) {
            $stmt = $conn->prepare("UPDATE attendance_breaks SET break_end = ? WHERE attendance_id = ? AND break_end IS NULL");
            $stmt->bind_param("si", $now, $att_rec['id']);
            if($stmt->execute()) $response = ['status' => 'success'];
        }
    }
    
    echo json_encode($response); 
    exit; 
}

// 4. FETCH LIVE ATTENDANCE DATA FOR UI
$attendance_record = null;
$total_hours_today = "00:00:00";
$display_punch_in = "--:--";
$total_seconds_worked = 0;
$is_on_break = false;
$total_break_seconds = 0;
$break_start_ts = 0;
$break_seconds = 0;
$lunch_break_seconds = 0;
$active_break_type = 'General';
$delay_text = "";
$delay_class = "";

$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
$attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

if ($attendance_record) {
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ?";
    $bk_stmt = mysqli_prepare($conn, $bk_sql);
    mysqli_stmt_bind_param($bk_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($bk_stmt);
    $bk_res = mysqli_stmt_get_result($bk_stmt);
    
    while($b_row = mysqli_fetch_assoc($bk_res)) {
        $type = $b_row['break_type'] ?? 'General';
        
        if ($b_row['break_end']) {
            $dur = strtotime($b_row['break_end']) - strtotime($b_row['break_start']);
            $total_break_seconds += $dur;
            if ($type === 'Break') $break_seconds += $dur;
            elseif ($type === 'Lunch') $lunch_break_seconds += $dur;
        } else {
            $is_on_break = true;
            $active_break_type = $type;
            $break_start_ts = strtotime($b_row['break_start']);
            
            $live_dur = time() - $break_start_ts;
            $total_break_seconds += $live_dur;
            if ($type === 'Break') $break_seconds += $live_dur;
            elseif ($type === 'Lunch') $lunch_break_seconds += $live_dur;
        }
    }
}

// Calculate Display Times & Late Delay
$display_break_seconds = $total_break_seconds;
$break_time_str = "00:00:00";

if ($attendance_record) {
    $display_punch_in = date('h:i A', strtotime($attendance_record['punch_in']));
    $start_ts = strtotime($attendance_record['punch_in']);
    
    $time_parts = explode('-', $shift_timings);
    if (count($time_parts) > 0) {
        $shift_start_str = trim($time_parts[0]); 
        $expected_start_ts = strtotime($today . ' ' . $shift_start_str);
        
        $diff_seconds = $start_ts - $expected_start_ts;
        if ($diff_seconds > 60) { 
            $mins_late = floor($diff_seconds / 60);
            $delay_text = "Late by $mins_late mins";
            $delay_class = "text-rose-600 bg-rose-50 border-rose-200";
        } elseif ($diff_seconds < -60) { 
            $mins_early = floor(abs($diff_seconds) / 60);
            $delay_text = "Early by $mins_early mins";
            $delay_class = "text-emerald-600 bg-emerald-50 border-emerald-200";
        } else {
            $delay_text = "On Time";
            // FIXED: Replaced "Breakl" typo with "teal"
            $delay_class = "text-teal-600 bg-teal-50 border-teal-200";
        }
    }

    if ($is_on_break) {
        $now_ts = $break_start_ts; 
    } elseif ($attendance_record['punch_out']) {
        $now_ts = strtotime($attendance_record['punch_out']);
    } else {
        $now_ts = time(); 
    }
    
    $total_seconds_worked = max(0, ($now_ts - $start_ts) - ($is_on_break ? ($total_break_seconds - (time() - $break_start_ts)) : $total_break_seconds));
    
    $hours = floor($total_seconds_worked / 3600);
    $mins = floor(($total_seconds_worked % 3600) / 60);
    $secs = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

    $b_hours = floor($display_break_seconds / 3600);
    $b_mins = floor(($display_break_seconds % 3600) / 60);
    $b_secs = $display_break_seconds % 60;
    $break_time_str = sprintf('%02d:%02d:%02d', $b_hours, $b_mins, $b_secs);
}

$ring_label = 'TOTAL WORK';
if ($is_on_break) {
    $ring_label = strtoupper($active_break_type) . ' BREAK';
}

function formatMinsSecs($seconds) {
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d', $m, $s);
}

// --- HTML RENDER ---
if (!$is_ajax_request): 
?>
<style>
    .progress-ring-circle {
        transition: stroke-dashoffset 1.0s linear; 
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
    }
    .break-card {
        background: #fffdf5;
        border: 1px solid #fde68a;
        border-radius: 12px;
        padding: 12px;
        text-align: center;
        flex: 1;
    }
    .break-card-lunch {
        background: #fffaf5;
        border: 1px solid #fed7aa;
    }
</style>
<div class="card" id="attendanceCardWrapper" style="border: none; padding: 0;">
<?php endif; ?>

    <div class="card-body flex flex-col items-center w-full p-6">
        
        <div class="w-full flex justify-between items-center mb-5">
            <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Today's Shift</h3>
            <span class="text-[10px] font-bold text-slate-800 bg-slate-100 px-2 py-1 rounded"><?php echo date("d M Y"); ?></span>
        </div>

        <div class="w-full bg-slate-50 border border-slate-100 p-3 rounded-xl mb-6 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded bg-teal-100 flex items-center justify-center text-teal-600">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Shift Type</p>
                    <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($shift_name); ?></p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Timings</p>
                <p class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($shift_timings); ?></p>
            </div>
        </div>

        <div class="relative w-48 h-48 mb-8">
            <svg class="w-full h-full transform -rotate-90">
            <circle cx="96" cy="96" r="86" stroke="#f1f5f9" stroke-width="14" fill="transparent"></circle>
            <?php 
                $pct = min(1, $total_seconds_worked / 32400); 
                $circumference = 540.35; 
                $dashoffset = $circumference - $pct * $circumference;
                
                // Color Logic
                $ringColor = '#0d9488'; // Default Teal
                if ($is_on_break) {
                $ringColor = $active_break_type === 'Lunch' ? '#ea580c' : '#f59e0b';
                }
            ?>
            <circle cx="96" cy="96" r="86" stroke="<?php echo $ringColor; ?>" stroke-width="14" fill="transparent" 
                stroke-dasharray="540.35" 
                stroke-dashoffset="<?php echo $attendance_record && $attendance_record['punch_out'] ? '0' : max(0, $dashoffset); ?>" 
                stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
            </svg>
            
            <div class="absolute inset-0 flex flex-col items-center justify-center">
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1" id="ringLabel"><?php echo $ring_label; ?></p>
            <p class="text-3xl font-black <?php echo $is_on_break ? 'text-gray-400' : 'text-slate-800'; ?> tracking-tight" id="liveTimer" 
                data-running="<?php echo $attendance_record && !$attendance_record['punch_out'] && !$is_on_break ? 'true' : 'false'; ?>"
                data-total="<?php echo $total_seconds_worked; ?>">
                <?php echo $total_hours_today; ?>
            </p>
            <p class="text-[11px] text-gray-500 font-bold mt-1">Break: <?php echo $break_time_str; ?></p>
            <?php if ($is_on_break): ?>
                <?php 
                $pulse_color = $active_break_type === 'Lunch' ? 'text-orange-500 bg-orange-50' : 'text-amber-500 bg-amber-50';
                $pulse_icon = $active_break_type === 'Lunch' ? 'fa-utensils' : 'fa-mug-hot';
                ?>
                <div class="mt-2 flex items-center justify-center gap-1.5 <?php echo $pulse_color; ?> font-bold text-sm px-3 py-1 rounded-full animate-pulse" id="breakTimerContainer">
                <i class="fa-solid <?php echo $pulse_icon; ?> text-xs"></i>
                <span id="breakTimer" data-break-running="true" data-break-total="<?php echo $total_break_seconds; ?>"><?php echo $break_time_str; ?></span>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <div class="w-full flex gap-4 mb-6">
            <div class="break-card">
                <p class="text-[10px] text-amber-600 font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-mug-hot mr-1"></i> Break</p>
                <p class="text-lg font-black text-slate-800">
                    <span id="BreakTimer" data-seconds="<?php echo $break_seconds; ?>"><?php echo formatMinsSecs($break_seconds); ?></span> 
                    <span class="text-[9px] text-gray-400 font-bold ml-0.5">MINS</span>
                </p>
            </div>
            <div class="break-card break-card-lunch">
                <p class="text-[10px] text-orange-600 font-black uppercase tracking-widest mb-1"><i class="fa-solid fa-utensils mr-1"></i> Lunch</p>
                <p class="text-lg font-black text-slate-800">
                    <span id="lunchTimer" data-seconds="<?php echo $lunch_break_seconds; ?>"><?php echo formatMinsSecs($lunch_break_seconds); ?></span> 
                    <span class="text-[9px] text-gray-400 font-bold ml-0.5">MINS</span>
                </p>
            </div>
        </div>

        <div class="w-full relative" id="attendanceActionButtons">
            <?php if (!$attendance_record): ?>
                <button type="button" onclick="submitAttendanceAction('punch_in')" class="w-full bg-[#0d9488] hover:bg-[#0f766e] text-white font-bold py-4 rounded-xl shadow-lg transition flex items-center justify-center gap-2 text-lg">
                    <i class="fa-solid fa-right-to-bracket"></i> Punch In
                </button>
            <?php elseif (!$attendance_record['punch_out']): ?>
                
                <div class="grid grid-cols-2 gap-4 w-full" id="normalActions">
                    <?php if ($is_on_break): ?>
                        <button type="button" onclick="submitAttendanceAction('break_end')" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3.5 rounded-xl shadow-md transition flex justify-center items-center gap-2 text-base">
                            <i class="fa-solid fa-play"></i> Resume Work
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="toggleBreakMenu()" class="bg-[#fbbf24] hover:bg-[#f59e0b] text-white font-black py-3.5 rounded-xl shadow-md transition flex justify-center items-center gap-2 text-base tracking-wide">
                            <i class="fa-solid fa-pause"></i> Take Break
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="submitAttendanceAction('punch_out')" class="bg-[#ef4444] hover:bg-[#dc2626] text-white font-black py-3.5 rounded-xl shadow-md transition flex justify-center items-center gap-2 text-base tracking-wide">
                        <i class="fa-solid fa-right-from-bracket"></i> Punch Out
                    </button>
                </div>

                <div class="absolute inset-0 bg-white z-10 hidden flex gap-2 w-full h-full items-center" id="breakOptions">
                    <button type="button" onclick="submitAttendanceAction('take_break', 'Break')" class="bg-[#fef3c7] hover:bg-[#fde68a] text-[#d97706] border border-[#fcd34d] font-bold py-2 px-3 rounded-lg shadow-sm transition flex-1 flex justify-center items-center gap-1.5 text-sm">
                        <i class="fa-solid fa-mug-hot text-xs"></i> Break
                    </button>
                    <button type="button" onclick="submitAttendanceAction('take_break', 'Lunch')" class="bg-[#ffedd5] hover:bg-[#fed7aa] text-[#ea580c] border border-[#fdba74] font-bold py-2 px-3 rounded-lg shadow-sm transition flex-1 flex justify-center items-center gap-1.5 text-sm">
                        <i class="fa-solid fa-utensils text-xs"></i> Lunch
                    </button>
                    <button type="button" onclick="toggleBreakMenu()" class="bg-gray-100 hover:bg-gray-200 text-gray-500 font-bold px-2.5 py-2 rounded-lg shadow-sm transition flex justify-center items-center">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

            <?php else: ?>
                <button disabled class="w-full bg-slate-100 text-slate-400 font-bold py-4 rounded-xl cursor-not-allowed flex justify-center items-center gap-2 border border-slate-200 text-lg">
                    <i class="fa-solid fa-check-circle text-emerald-500"></i> Shift Completed
                </button>
            <?php endif; ?>
        </div>

        <?php if($attendance_record): ?>
        <div class="w-full mt-6 flex justify-between items-center bg-gray-50 p-3 rounded-xl border border-gray-100">
            <p class="text-xs text-gray-500 flex items-center gap-1.5">
                <i class="fa-solid fa-fingerprint text-teal-600"></i> Punched In: <span class="font-black text-slate-800"><?php echo $display_punch_in; ?></span>
            </p>
            <?php if($delay_text != ""): ?>
                <span class="text-[10px] font-bold px-2 py-1.5 border rounded-lg <?php echo $delay_class; ?> tracking-wide"><?php echo $delay_text; ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>

<?php if (!$is_ajax_request): ?>
</div>

<script>
    let attendanceTimerInterval = null;

    function toggleBreakMenu() {
        const normal = document.getElementById('normalActions');
        const breaks = document.getElementById('breakOptions');
        if (normal && breaks) {
            normal.classList.toggle('hidden');
            breaks.classList.toggle('hidden');
        }
    }

    function initAttendance() {
        if (attendanceTimerInterval) clearInterval(attendanceTimerInterval);

        const timerElement = document.getElementById('liveTimer');
        const progressRing = document.getElementById('progressRing');
        const breakTimerElement = document.getElementById('breakTimer');
        
        const BreakEl = document.getElementById('BreakTimer');
        const lunchEl = document.getElementById('lunchTimer');

        if (!timerElement) return;

        const isWorkRunning = timerElement.getAttribute('data-running') === 'true';
        const isBreakRunning = breakTimerElement ? breakTimerElement.getAttribute('data-break-running') === 'true' : false;
        const activeBreakType = '<?php echo $active_break_type; ?>';
        
        const workTotalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
        const breakTotalSeconds = breakTimerElement ? (parseInt(breakTimerElement.getAttribute('data-break-total')) || 0) : 0;
        
        const BreakTotal = BreakEl ? (parseInt(BreakEl.getAttribute('data-seconds')) || 0) : 0;
        const lunchTotal = lunchEl ? (parseInt(lunchEl.getAttribute('data-seconds')) || 0) : 0;

        const startTime = new Date().getTime(); 

        function formatTime(totalSecs) {
            const h = Math.floor(totalSecs / 3600);
            const m = Math.floor((totalSecs % 3600) / 60);
            const s = totalSecs % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function formatMinsSecs(totalSecs) {
            const m = Math.floor(totalSecs / 60);
            const s = totalSecs % 60;
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function updateTimer() {
            const now = new Date().getTime();
            const diffSeconds = Math.floor((now - startTime) / 1000);
            
            if (isWorkRunning) {
                const currentWork = workTotalSeconds + diffSeconds;
                timerElement.innerText = formatTime(currentWork);
                const progress = Math.min(currentWork / 32400, 1);
                if(progressRing) progressRing.style.strokeDashoffset = 540.35 - (progress * 540.35);
            }

            if (isBreakRunning && breakTimerElement) {
                const currentBreak = breakTotalSeconds + diffSeconds;
                breakTimerElement.innerText = formatTime(currentBreak);
                
                // Live tick specific break counters
                if (activeBreakType === 'Break' && BreakEl) {
                    BreakEl.innerText = formatMinsSecs(BreakTotal + diffSeconds);
                } else if (activeBreakType === 'Lunch' && lunchEl) {
                    lunchEl.innerText = formatMinsSecs(lunchTotal + diffSeconds);
                }
            }
        }

        if (isWorkRunning || isBreakRunning) {
            attendanceTimerInterval = setInterval(updateTimer, 1000);
            updateTimer(); 
        }
    }

    // Secure Crash-Proof AJAX Function
    function submitAttendanceAction(actionStr, breakType = null) {
        const fd = new FormData();
        fd.append('action', actionStr);
        if (breakType) fd.append('break_type', breakType);

        const btnContainer = document.getElementById('attendanceActionButtons');
        if(btnContainer) {
            btnContainer.innerHTML = '<div class="w-full flex justify-center py-4 bg-slate-50 rounded-xl border border-slate-100"><span class="spinner w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full animate-spin"></span></div>';
        }

        // FIXED: Force the POST request specifically to attendance_card.php 
        // This prevents the parent dashboard from intercepting and breaking the JSON response.
        let postUrl = '<?php echo $local_path_to_root; ?>attendance_card.php'; 

        fetch(postUrl, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Fetch perfectly clean card HTML
                let fetchUrl = postUrl + '?ajax_card=1';
                fetch(fetchUrl)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('attendanceCardWrapper').innerHTML = html;
                    initAttendance();
                });
            } else {
                alert('Error: ' + data.message);
                window.location.reload();
            }
        })
        .catch(err => {
            console.error("AJAX Error:", err);
            // Fallback gracefully without breaking the layout
            window.location.reload();
        });
    }

    document.addEventListener('DOMContentLoaded', initAttendance);
</script>
<?php 
endif; 
?>