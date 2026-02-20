<?php
require_once '../include/db_connect.php'; 

// --- CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="jobs_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Job Title', 'Requested By', 'Date Requested', 'Location', 'Salary', 'Experience'));
    $export_sql = "SELECT id, title, requested_by, created_at, loc, sal, exp FROM jobs";
    $export_result = mysqli_query($conn, $export_sql);
    if ($export_result && mysqli_num_rows($export_result) > 0) {
        while ($row = mysqli_fetch_assoc($export_result)) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit();
}

include '../sidebars.php';
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Recruitment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
        .flatpickr-day.selected { background: #0f766e !important; border-color: #0f766e !important; }
    </style>
    
    <script>
        function updateFilter(param, value) {
            const url = new URL(window.location.href);
            if (value) url.searchParams.set(param, value);
            else url.searchParams.delete(param);
            window.location.href = url.href;
        }
    </script>
</head>
<body>

    <div class="lg:ml-20 p-6 md:p-10 transition-all duration-300 min-h-screen">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Recruitment</h1>
                <nav class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                    <i class="fa-solid fa-house text-xs"></i>
                    <span>&rsaquo;</span> Recruitment <span>&rsaquo;</span> <span class="text-gray-400">Jobs</span>
                </nav>
            </div>
            
            <div class="flex items-center gap-3 mt-4 md:mt-0">
                <button onclick="window.location.href='?export=csv'" class="bg-white border px-4 py-2 rounded-md text-gray-700 flex items-center gap-2">
                    <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down text-xs"></i>
                </button>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow-sm border mb-8 flex flex-col lg:flex-row justify-between items-center gap-4">
            <h2 class="font-bold text-gray-700 px-2">Job Grid</h2>
            
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative cursor-pointer">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 z-10">
                        <i class="fa-regular fa-calendar"></i>
                    </span>
                    <input type="text" id="date-range" value="<?= $_GET['dates'] ?? '' ?>" placeholder="Select Date Range" class="pl-10 pr-4 py-2 border rounded-md text-sm text-gray-600 focus:outline-none w-56 bg-white cursor-pointer" readonly>
                </div>

                <select onchange="updateFilter('role', this.value)" class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none appearance-none bg-white min-w-[100px]">
                    <option value="">Role</option>
                    
                    <option value="React Developer" <?= (isset($_GET['role']) && $_GET['role'] == 'React Developer') ? 'selected' : '' ?>>React Developer</option>
                </select>

                <div class="relative" x-data="{ open: false }">
                    <?php 
                        $sort_text = "Sort By";
                        if(isset($_GET['sort'])) {
                            $sort_map = ['recent'=>'Recently Added', 'asc'=>'Ascending', 'desc'=>'Descending', 'month'=>'Last Month', '7days'=>'Last 7 Days'];
                            $sort_text = $sort_map[$_GET['sort']] ?? "Sort By";
                        }
                    ?>
                    <button @click="open = !open" class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none bg-white min-w-[180px] flex justify-between items-center">
                        <span><?= $sort_text ?></span>
                        <i class="fa-solid fa-chevron-down text-xs ml-2"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white border rounded-md shadow-lg z-50">
                        <div class="py-1">
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'recent')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Recently Added</a>
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'asc')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Ascending</a>
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'desc')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Descending</a>
                            <a href="javascript:void(0)" onclick="updateFilter('sort', 'month')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Last Month</a>
                            <a href="javascript:void(0)" onclick="updateFilter('sort', '7days')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Last 7 Days</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php
            $jobs = [];
            $conditions = [];
            $order_clause = " ORDER BY id DESC";

            if (!empty($_GET['role'])) {
                $role = mysqli_real_escape_string($conn, $_GET['role']);
                $conditions[] = "title = '$role'";
            }

            // --- DATE FILTER LOGIC ---
            if (!empty($_GET['dates'])) {
                $date_parts = explode(" to ", $_GET['dates']);
                if (count($date_parts) == 2) {
                    $start = date('Y-m-d', strtotime(trim($date_parts[0])));
                    $end = date('Y-m-d', strtotime(trim($date_parts[1])));
                    $conditions[] = "DATE(created_at) BETWEEN '$start' AND '$end'";
                } else {
                    $single_date = date('Y-m-d', strtotime(trim($date_parts[0])));
                    $conditions[] = "DATE(created_at) = '$single_date'";
                }
            }

            if (isset($_GET['sort'])) {
                if ($_GET['sort'] == 'asc') $order_clause = " ORDER BY title ASC";
                if ($_GET['sort'] == 'desc') $order_clause = " ORDER BY title DESC";
                if ($_GET['sort'] == 'recent') $order_clause = " ORDER BY created_at DESC";
                if ($_GET['sort'] == '7days') $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                if ($_GET['sort'] == 'month') $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            }

            $where_clause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";
            $sql = "SELECT * FROM jobs" . $where_clause . $order_clause;
            $result = mysqli_query($conn, $sql); 
            
            if ($result && mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
            ?>
                <div class="card bg-white rounded-xl shadow-sm border p-5">
                    <div class="flex items-center gap-4 bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="w-12 h-12 <?= $row['icon_bg'] ?? 'bg-gray-200' ?> rounded-lg flex items-center justify-center text-2xl text-gray-700">
                            <i class="fa-brands <?= $row['icon'] ?? 'fa-php' ?>"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 leading-tight"><?= $row['title'] ?></h3>
                            <p class="text-xs text-gray-500">25 Applicants</p>
                        </div>
                    </div>
                    <div class="mb-4 pb-3 border-b border-dashed border-gray-100">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Requested By</p>
                                <p class="text-sm text-teal-700 font-medium"><?= $row['requested_by'] ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 uppercase font-semibold">On</p>
                                <p class="text-[11px] text-gray-600"><?= !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A' ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3 mb-5">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-regular fa-calendar-check text-gray-400 w-4"></i> 
                            <span class="font-medium text-gray-500">Requested:</span> 
                            <?= !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A' ?>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-location-dot text-gray-400 w-4"></i> <?= $row['loc'] ?>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-dollar-sign text-gray-400 w-4"></i> <?= $row['sal'] ?>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-briefcase text-gray-400 w-4"></i> <?= $row['exp'] ?> of experience
                        </div>
                    </div>
                    <div class="flex gap-2 mb-6">
                        <span class="px-3 py-1 bg-pink-100 text-pink-500 text-[10px] font-bold uppercase rounded">Full Time</span>
                        <span class="px-3 py-1 bg-cyan-50 text-cyan-500 text-[10px] font-bold uppercase rounded border border-cyan-100">Expert</span>
                    </div>
                    <div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2">
                            <div class="bg-teal-700 h-1.5 rounded-full" style="width: 40%"></div>
                        </div>
                        <p class="text-[11px] text-gray-400">10 of 25 filled</p>
                    </div>
                </div>
            <?php 
                }
            } else {
                echo "<div class='col-span-4 text-center py-10 text-gray-500'>No jobs found for the selected criteria.</div>";
            }
            ?>
        </div>
    </div>

    <script>
        flatpickr("#date-range", {
            mode: "range",
            dateFormat: "m/d/Y",
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    updateFilter('dates', dateStr);
                }
            }
        });
    </script>
</body>
</html>