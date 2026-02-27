<?php
// attendance_card.php

// Detect if this is an AJAX request asking for fresh HTML
$is_ajax_request = isset($_GET['ajax_card']) && $_GET['ajax_card'] == '1';

if ($is_ajax_request) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    date_default_timezone_set('Asia/Kolkata');
    // Connect to DB securely for the AJAX reload
    $paths = ['include/db_connect.php', '../include/db_connect.php'];
    foreach($paths as $path) { if(file_exists($path)) { require_once $path; break; } }
    $current_user_id = $_SESSION['user_id'];
}

$today = date('Y-m-d');
$attendance_record = null;
$total_hours_today = "00:00:00";
$display_punch_in = "--:--";
$total_seconds_worked = 0;
$is_on_break = false;
$total_break_seconds = 0;
$break_start_ts = 0;

// 1. Fetch Attendance Data
$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "is", $current_user_id, $today);
mysqli_stmt_execute($check_stmt);
$attendance_record = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

if ($attendance_record) {
    $bk_sql = "SELECT * FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NULL";
    $bk_stmt = mysqli_prepare($conn, $bk_sql);
    mysqli_stmt_bind_param($bk_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($bk_stmt);
    if ($bk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($bk_stmt))) {
        $is_on_break = true;
        $break_start_ts = strtotime($bk_row['break_start']);
    }

    $sum_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total FROM attendance_breaks WHERE attendance_id = ? AND break_end IS NOT NULL";
    $sum_stmt = mysqli_prepare($conn, $sum_sql);
    mysqli_stmt_bind_param($sum_stmt, "i", $attendance_record['id']);
    mysqli_stmt_execute($sum_stmt);
    $sum_res = mysqli_fetch_assoc(mysqli_stmt_get_result($sum_stmt));
    $total_break_seconds = $sum_res['total'] ?? 0;
}

// 2. Calculate Display Times
$display_break_seconds = $total_break_seconds;
$break_time_str = "00:00:00";

if ($attendance_record) {
    $display_punch_in = date('h:i A', strtotime($attendance_record['punch_in']));
    $start_ts = strtotime($attendance_record['punch_in']);
    
    if ($is_on_break) {
        $now_ts = $break_start_ts; 
        $display_break_seconds = $total_break_seconds + (time() - $break_start_ts); 
    } elseif ($attendance_record['punch_out']) {
        $now_ts = strtotime($attendance_record['punch_out']);
    } else {
        $now_ts = time(); 
    }
    
    $total_seconds_worked = max(0, ($now_ts - $start_ts) - $total_break_seconds);
    $hours = floor($total_seconds_worked / 3600);
    $mins = floor(($total_seconds_worked % 3600) / 60);
    $secs = $total_seconds_worked % 60;
    $total_hours_today = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

    $b_hours = floor($display_break_seconds / 3600);
    $b_mins = floor(($display_break_seconds % 3600) / 60);
    $b_secs = $display_break_seconds % 60;
    $break_time_str = sprintf('%02d:%02d:%02d', $b_hours, $b_mins, $b_secs);
}

// Ensure the wrapper is only rendered on the initial page load, not during AJAX reloads
if (!$is_ajax_request): 
?>
<style>
    .progress-ring-circle {
    /* Changing from 0.35s to 1.5s makes the ring move much slower and smoother */
    transition: stroke-dashoffset 1.5s ease-in-out; 
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}
</style>
<div class="card" id="attendanceCardWrapper">
<?php endif; ?>

    <div class="card-body flex flex-col items-center w-full">
        <div class="text-center mb-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Today's Attendance</h3>
            <p class="text-lg font-bold text-slate-800 mt-1"><?php echo date("h:i A, d M Y"); ?></p>
        </div>

        <div class="relative w-44 h-44 mb-6">
            <svg class="w-full h-full transform -rotate-90">
                <circle cx="88" cy="88" r="78" stroke="#f1f5f9" stroke-width="12" fill="transparent"></circle>
                <?php 
                    $pct = min(1, $total_seconds_worked / 32400); 
                    $circumference = 490; 
                    $dashoffset = $circumference - ($pct * $circumference);
                    $ringColor = $is_on_break ? '#f59e0b' : '#0d9488';
                ?>
                <circle cx="88" cy="88" r="78" stroke="<?php echo $ringColor; ?>" stroke-width="12" fill="transparent" 
                    stroke-dasharray="490" 
                    stroke-dashoffset="<?php echo ($attendance_record && $attendance_record['punch_out']) ? '0' : max(0, $dashoffset); ?>" 
                    stroke-linecap="round" class="progress-ring-circle" id="progressRing"></circle>
            </svg>
            
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider"><?php echo $is_on_break ? 'WORK PAUSED' : 'TOTAL WORK'; ?></p>
                <p class="text-2xl font-black <?php echo $is_on_break ? 'text-gray-400' : 'text-slate-800'; ?>" id="liveTimer" 
                    data-running="<?php echo ($attendance_record && !$attendance_record['punch_out'] && !$is_on_break) ? 'true' : 'false'; ?>"
                    data-total="<?php echo $total_seconds_worked; ?>">
                    <?php echo $total_hours_today; ?>
                </p>
                <?php if ($is_on_break): ?>
                    <div class="mt-1 flex items-center justify-center gap-1.5 text-amber-500 font-bold text-sm bg-amber-50 px-2 py-0.5 rounded-full animate-pulse">
                        <i class="fa-solid fa-mug-hot text-[10px]"></i>
                        <span id="breakTimer" data-break-running="true" data-break-total="<?php echo $display_break_seconds; ?>"><?php echo $break_time_str; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="w-full">
            <?php if (!$attendance_record): ?>
                <button type="button" onclick="submitAttendanceAction('punch_in')" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl shadow-lg transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i> Punch In
                </button>
            <?php elseif (!$attendance_record['punch_out']): ?>
                <div class="grid grid-cols-2 gap-3 w-full">
                    <?php if ($is_on_break): ?>
                        <button type="button" onclick="submitAttendanceAction('break_end')" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-xl shadow-md transition flex justify-center items-center gap-2">
                            <i class="fa-solid fa-play"></i> Resume
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="submitAttendanceAction('break_start')" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-xl shadow-md transition flex justify-center items-center gap-2">
                            <i class="fa-solid fa-mug-hot"></i> Break
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="submitAttendanceAction('punch_out')" class="bg-red-500 hover:bg-rose-600 text-white font-bold py-3 rounded-xl shadow-md transition flex justify-center items-center gap-2">
                        <i class="fa-solid fa-right-from-bracket"></i> Out
                    </button>
                </div>
            <?php else: ?>
                <button disabled class="w-full bg-slate-100 text-slate-400 font-bold py-3 rounded-xl cursor-not-allowed flex justify-center items-center gap-2 border border-slate-200">
                    <i class="fa-solid fa-check-circle text-emerald-500"></i> Shift Completed
                </button>
            <?php endif; ?>
        </div>

        <p class="text-xs text-gray-400 mt-5 flex items-center gap-1.5 font-medium">
            <i class="fa-solid fa-fingerprint text-teal-600"></i> 
            Punched In at: <span class="font-bold text-slate-700"><?php echo $display_punch_in; ?></span>
        </p>
    </div>

<?php if (!$is_ajax_request): ?>
</div>

<script>
    let attendanceTimerInterval = null;

    function initAttendance() {
        if (attendanceTimerInterval) clearInterval(attendanceTimerInterval);

        const timerElement = document.getElementById('liveTimer');
        const progressRing = document.getElementById('progressRing');
        const breakTimerElement = document.getElementById('breakTimer');

        if (!timerElement) return;

        const isWorkRunning = timerElement.getAttribute('data-running') === 'true';
        const isBreakRunning = breakTimerElement ? breakTimerElement.getAttribute('data-break-running') === 'true' : false;
        
        const workTotalSeconds = parseInt(timerElement.getAttribute('data-total')) || 0;
        const breakTotalSeconds = breakTimerElement ? (parseInt(breakTimerElement.getAttribute('data-break-total')) || 0) : 0;
        const startTime = new Date().getTime(); 

        function formatTime(totalSecs) {
            const h = Math.floor(totalSecs / 3600);
            const m = Math.floor((totalSecs % 3600) / 60);
            const s = totalSecs % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function updateTimer() {
            const now = new Date().getTime();
            const diffSeconds = Math.floor((now - startTime) / 1000);
            
            if (isWorkRunning) {
                const currentWork = workTotalSeconds + diffSeconds;
                timerElement.innerText = formatTime(currentWork);
                const progress = Math.min(currentWork / 32400, 1);
                if(progressRing) progressRing.style.strokeDashoffset = 490 - (progress * 490);
            }

            if (isBreakRunning && breakTimerElement) {
                const currentBreak = breakTotalSeconds + diffSeconds;
                breakTimerElement.innerText = formatTime(currentBreak);
            }
        }

        if (isWorkRunning || isBreakRunning) {
            attendanceTimerInterval = setInterval(updateTimer, 1000);
        }
    }

    function submitAttendanceAction(actionStr) {
        const fd = new FormData();
        fd.append('action', actionStr);

        // Visually disable buttons to prevent double-clicks
        const buttons = document.querySelectorAll('#attendanceCardWrapper button');
        buttons.forEach(b => { b.disabled = true; b.style.opacity = '0.6'; });

        // 1. Send the action to the DB safely
        fetch('../api/attendance_action.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // 2. Fetch purely the updated inner UI of the card WITHOUT refreshing the page
                fetch('../attendance_card.php?ajax_card=1')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('attendanceCardWrapper').innerHTML = html;
                    initAttendance(); // Restart timers on the newly loaded HTML
                });
            } else {
                alert("Error: " + data.message);
                buttons.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
            }
        })
        .catch(err => {
            alert("Network Error updating attendance.");
            buttons.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
        });
    }

    document.addEventListener('DOMContentLoaded', initAttendance);
</script>
<?php endif; ?>