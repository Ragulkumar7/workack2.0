<?php
// ats_calendar.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. HARD LOGIN GUARD
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../include/db_connect.php';

// Fetch all scheduled interviews for the calendar
$events = [];

// FIXED: Reverted to use your exact database columns: interview_date and interview_time
$query = "SELECT i.id, i.interview_date, i.interview_time, i.interview_type, i.interviewer, c.name 
          FROM interviews i 
          JOIN candidates c ON i.candidate_id COLLATE utf8mb4_unicode_ci = c.candidate_id COLLATE utf8mb4_unicode_ci";
          
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        // Combine date and time safely for FullCalendar (ISO 8601 format)
        $date_str = $row['interview_date'] . ' ' . $row['interview_time'];
        $dt = new DateTime($date_str);
        $iso_datetime = $dt->format('Y-m-d\TH:i:s');
        $display_time = $dt->format('h:i A'); // For the popup
        
        $color = '#0f766e'; // default teal (HR Round / Default)
        if ($row['interview_type'] === 'Technical Round') $color = '#ea580c'; // orange
        if ($row['interview_type'] === 'Final Round') $color = '#be123c'; // red
        
        $events[] = [
            'id' => $row['id'],
            'title' => $row['name'] . ' - ' . $row['interview_type'],
            'start' => $iso_datetime,
            'color' => $color,
            'extendedProps' => [
                'interviewer' => $row['interviewer'],
                'type' => $row['interview_type'],
                'display_time' => $display_time,
                'name' => $row['name']
            ]
        ];
    }
}

// SECURE JSON ENCODE: Prevents quotes in names from breaking JS
$events_json = json_encode($events, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATS Interview Calendar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --bg-body: #f3f4f6; }
        body { background: var(--bg-body); font-family: 'Inter', sans-serif; }
        main#content-wrapper { margin-left: 100px; padding: 20px 20px 20px; transition: margin 0.3s; }
        .sidebar-secondary.open ~ main#content-wrapper { margin-left: 280px; }

        .glass-card { background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        
        /* Customizing FullCalendar */
        .fc-theme-standard td, .fc-theme-standard th { border-color: #e2e8f0; }
        .fc-col-header-cell-cushion { padding: 12px 0 !important; color: #475569; font-weight: 700; }
        .fc-daygrid-day-number { color: #334155; font-weight: 600; padding: 8px; }
        .fc-button-primary { background-color: #0f766e !important; border-color: #0f766e !important; text-transform: capitalize; font-weight: 600;}
        .fc-button-primary:hover { background-color: #115e59 !important; }
        .fc-event { cursor: pointer; border-radius: 4px; padding: 3px 6px; border: none; font-size: 0.75rem; font-weight: 600; transition: transform 0.2s;}
        .fc-event:hover { transform: scale(1.02); filter: brightness(1.1); }
        
        /* SweetAlert Override */
        .swal2-container { z-index: 20000 !important; }
    </style>
</head>
<body>

<main id="content-wrapper">
    <div class="max-w-[1400px] mx-auto">
        <div class="flex justify-between items-end mb-6">
            <div>
                <h1 class="text-3xl font-black text-gray-800 tracking-tight">Interview <span class="text-teal-600">Calendar</span></h1>
                <p class="text-gray-500 mt-1 font-medium">Track and manage upcoming candidate interviews</p>
            </div>
            <button onclick="window.location.href='ats.php'" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg font-bold shadow-sm transition-all"><i class="fa-solid fa-arrow-left mr-2"></i> Back to ATS Pipeline</button>
        </div>

        <div class="glass-card p-6">
            <div class="flex gap-4 mb-4 justify-end text-xs font-bold text-gray-600">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-[#0f766e]"></span> HR Round</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-[#ea580c]"></span> Technical Round</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-[#be123c]"></span> Final Round</span>
            </div>
            
            <div id="calendar" class="min-h-[700px]"></div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var eventsData = <?php echo $events_json ?: '[]'; ?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            events: eventsData,
            
            // PROFESSIONAL SWEETALERT UPGRADE
            eventClick: function(info) {
                const props = info.event.extendedProps;
                
                Swal.fire({
                    title: props.name,
                    html: `
                        <div class="text-left mt-4 space-y-3 bg-gray-50 p-4 rounded-lg border border-gray-100">
                            <p class="flex justify-between items-center border-b border-gray-200 pb-2"><strong class="text-gray-500 text-xs uppercase tracking-widest">Interview Type</strong> <span class="font-bold text-gray-800">${props.type}</span></p>
                            <p class="flex justify-between items-center border-b border-gray-200 pb-2"><strong class="text-gray-500 text-xs uppercase tracking-widest">Interviewer</strong> <span class="font-bold text-gray-800">${props.interviewer}</span></p>
                            <p class="flex justify-between items-center"><strong class="text-gray-500 text-xs uppercase tracking-widest">Time</strong> <span class="font-bold text-teal-600">${props.display_time}</span></p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonColor: info.event.backgroundColor,
                    confirmButtonText: 'Close',
                    showClass: { popup: 'animate__animated animate__fadeInDown animate__faster' },
                    hideClass: { popup: 'animate__animated animate__fadeOutUp animate__faster' }
                });
            }
        });
        
        calendar.render();
    });
</script>
</body>
</html>