<?php
include '../include/db_connect.php'; // Added Database Connection

// --- ADDED: CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="jobs_export.csv"');
    
    $output = fopen('php://output', 'w');
    // Set CSV column headers
    fputcsv($output, array('ID', 'Job Title', 'Requested By', 'Location', 'Salary', 'Experience'));
    
    // Fetch data for export
    $export_sql = "SELECT id, title, requested_by, loc, sal, exp FROM jobs";
    $export_result = mysqli_query($conn, $export_sql);
    
    if ($export_result && mysqli_num_rows($export_result) > 0) {
        while ($row = mysqli_fetch_assoc($export_result)) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit(); // Stop script execution to prevent HTML rendering in the CSV
}
// -------------------------------

include '../sidebars.php';
include '../header.php';
// Commented out includes for testing purposes, uncomment in your real file
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
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
    </style>
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
                <div class="flex bg-white border rounded-md overflow-hidden">
                    <button class="px-3 py-2 text-gray-400 border-r"><i class="fa-solid fa-list-ul"></i></button>
                    <button class="px-3 py-2 bg-teal-700 text-white"><i class="fa-solid fa-table-cells-large"></i></button>
                </div>
                <button onclick="window.location.href='?export=csv'" class="bg-white border px-4 py-2 rounded-md text-gray-700 flex items-center gap-2">
                    <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down text-xs"></i>
                </button>
                <button class="bg-white border p-2 rounded-md text-gray-500"><i class="fa-solid fa-angles-up"></i></button>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow-sm border mb-8 flex flex-col lg:flex-row justify-between items-center gap-4">
            <h2 class="font-bold text-gray-700 px-2">Job Grid</h2>
            
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <i class="fa-regular fa-calendar"></i>
                    </span>
                    <input type="text" value="02/10/2026 - 02/16/2026" class="pl-10 pr-4 py-2 border rounded-md text-sm text-gray-600 focus:outline-none w-56">
                </div>

                <select class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none appearance-none bg-white min-w-[100px]">
                    <option>Role</option>
                </select>
                <select class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none appearance-none bg-white min-w-[100px]">
                    <option>Status</option>
                </select>

                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none bg-white min-w-[180px] flex justify-between items-center">
                        <span>Sort By : Last 7 Days</span>
                        <i class="fa-solid fa-chevron-down text-xs ml-2"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white border rounded-md shadow-lg z-50">
                        <div class="py-1">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Recently Added</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Ascending</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Descending</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Last Month</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 bg-gray-50 font-medium">Last 7 Days</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php
            // Fetch jobs from database instead of hardcoded array
            $jobs = [];
            $sql = "SELECT * FROM jobs";
            $result = mysqli_query($conn, $sql); // Assuming $conn is declared in db_connect.php
            
            if ($result && mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    $jobs[] = $row;
                }
            }

            foreach ($jobs as $job): ?>
                <div class="card bg-white rounded-xl shadow-sm border p-5">
                    <div class="flex items-center gap-4 bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="w-12 h-12 <?= $job['icon_bg'] ?> rounded-lg flex items-center justify-center text-2xl text-gray-700">
                            <i class="fa-brands <?= $job['icon'] ?>"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 leading-tight"><?= $job['title'] ?></h3>
                            <p class="text-xs text-gray-500">25 Applicants</p>
                        </div>
                    </div>

                    <div class="mb-4 pb-3 border-b border-dashed border-gray-100">
                        <p class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Requested By</p>
                        <p class="text-sm text-teal-700 font-medium"><?= $job['requested_by'] ?></p>
                    </div>

                    <div class="space-y-3 mb-5">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-location-dot text-gray-400 w-4"></i>
                            <?= $job['loc'] ?>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-dollar-sign text-gray-400 w-4"></i>
                            <?= $job['sal'] ?>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-briefcase text-gray-400 w-4"></i>
                            <?= $job['exp'] ?> of experience
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
            <?php endforeach; ?>
        </div>
    </div>

    <div class="fixed right-0 top-1/2 bg-teal-700 text-white p-2 rounded-l-md cursor-pointer shadow-lg z-50">
        <i class="fa-solid fa-gear animate-spin-slow"></i>
    </div>

</body>
</html>