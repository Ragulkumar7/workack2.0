<?php 
include '../sidebars.php'; 
include '../header.php';
// Commented out includes for testing purposes, uncomment in your real file
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Parsing Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .status-badge {
            background-color: #eef2ff;
            color: #4f46e5;
            border: 1px solid #e0e7ff;
            padding: 2px 12px;
            border-radius: 6px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
        }
        .status-dot {
            height: 6px;
            width: 6px;
            background-color: #4f46e5;
            border-radius: 50%;
            margin-right: 6px;
        }
    </style>
</head>
<body class="text-slate-600">

    <div class="px-8 pt-6 pb-2">
        <h1 class="text-2xl font-bold text-slate-800">Resume Parsing</h1>
        <div class="flex items-center text-sm text-slate-400 mt-1">
            <i class="fas fa-home mr-2"></i> 
            <i class="fas fa-chevron-right text-[10px] mx-2"></i> Recruitment
            <i class="fas fa-chevron-right text-[10px] mx-2"></i> <span class="text-slate-500">Resume Parsing</span>
        </div>
    </div>

    <div class="m-6 bg-white rounded-lg shadow-sm border border-slate-200">
        
        <div class="p-4 flex flex-wrap items-center justify-between border-b border-slate-100">
            <h2 class="text-lg font-semibold text-slate-800">Resume List</h2>
            
            <div class="flex items-center space-x-3">
                <div class="border rounded-md px-3 py-1.5 flex items-center text-sm text-slate-600 bg-white">
                    <i class="far fa-calendar-alt mr-2 text-slate-400"></i>
                    02/10/2026 - 02/16/2026
                </div>
                <select class="border rounded-md px-3 py-1.5 text-sm text-slate-600 bg-white focus:outline-none">
                    <option>Designation</option>
                </select>
                <select class="border rounded-md px-3 py-1.5 text-sm text-slate-600 bg-white focus:outline-none">
                    <option>Sort By : Last 7 Days</option>
                </select>
                <button class="border rounded-md px-4 py-1.5 text-sm font-medium flex items-center hover:bg-gray-50">
                    <i class="fas fa-file-export mr-2 text-slate-400"></i> Export <i class="fas fa-chevron-down ml-2 text-[10px]"></i>
                </button>
                <button class="bg-white border rounded-md p-2 hover:bg-gray-50">
                    <i class="fas fa-chevron-up text-xs"></i>
                </button>
            </div>
        </div>

        <div class="p-4 flex items-center justify-between bg-white">
            <div class="flex items-center text-sm">
                <span class="mr-2">Row Per Page</span>
                <select class="border rounded px-2 py-1 focus:outline-none">
                    <option>10</option>
                </select>
                <span class="ml-2 text-slate-500">Entries</span>
            </div>
            <div class="relative">
                <input type="text" placeholder="Search" class="border rounded-md pl-3 pr-10 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 w-64">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-y border-slate-100 text-slate-700 text-sm font-semibold">
                    <tr>
                        <th class="p-4 w-10"><input type="checkbox" class="rounded border-slate-300"></th>
                        <th class="p-4">Cand ID <i class="fas fa-sort text-slate-300 ml-1"></i></th>
                        <th class="p-4">Candidate <i class="fas fa-sort text-slate-300 ml-1"></i></th>
                        <th class="p-4">Applied Role <i class="fas fa-sort text-slate-300 ml-1"></i></th>
                        <th class="p-4">Phone <i class="fas fa-sort text-slate-300 ml-1"></i></th>
                        <th class="p-4">Expereience <i class="fas fa-sort text-slate-300 ml-1"></i></th>
                        <th class="p-4">Location <i class="fas fa-sort text-slate-300 ml-1"></i></th>
                        <th class="p-4">Status <i class="fas fa-sort text-slate-300 ml-1"></i></th>
                        <th class="p-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php
                    $candidates = [
                        ['id' => 'Cand-003', 'name' => 'John Harris', 'email' => 'john@example.com', 'role' => 'Technician', 'phone' => '(196) 2348 947', 'exp' => '5 yrs', 'loc' => 'Chicago', 'img' => 'https://i.pravatar.cc/150?u=1'],
                        ['id' => 'Cand-004', 'name' => 'Carole Langan', 'email' => 'carole@example.com', 'role' => 'Web Developer', 'phone' => '(138) 6487 295', 'exp' => '1 yr', 'loc' => 'Houston', 'img' => 'https://i.pravatar.cc/150?u=2'],
                        ['id' => 'Cand-005', 'name' => 'Charles Marks', 'email' => 'charles@example.com', 'role' => 'Sales Executive Officer', 'phone' => '(154) 6485 218', 'exp' => '4 yrs', 'loc' => 'Phoenix', 'img' => 'https://i.pravatar.cc/150?u=3'],
                        ['id' => 'Cand-006', 'name' => 'Kerry Drake', 'email' => 'kerry@example.com', 'role' => 'Designer', 'phone' => '(185) 5947 097', 'exp' => '2 yrs', 'loc' => 'Dallas', 'img' => 'https://i.pravatar.cc/150?u=4'],
                        ['id' => 'Cand-007', 'name' => 'David Carmona', 'email' => 'david@example.com', 'role' => 'Account Manager', 'phone' => '(106) 3485 978', 'exp' => '3 yrs', 'loc' => 'Austin', 'img' => 'https://i.pravatar.cc/150?u=5'],
                        ['id' => 'Cand-008', 'name' => 'Margaret Soto', 'email' => 'margaret@example.com', 'role' => 'SEO Analyst', 'phone' => '(174) 3795 107', 'exp' => '5 yrs', 'loc' => 'Boston', 'img' => 'https://i.pravatar.cc/150?u=6'],
                        ['id' => 'Cand-009', 'name' => 'Jeffrey Thaler', 'email' => 'jeffrey@example.com', 'role' => 'Admin', 'phone' => '(128) 0975 348', 'exp' => '4 yrs', 'loc' => 'Miami', 'img' => 'https://i.pravatar.cc/150?u=7'],
                    ];

                    foreach ($candidates as $cand):
                    ?>
                    <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                        <td class="p-4"><input type="checkbox" class="rounded border-slate-300"></td>
                        <td class="p-4 text-slate-500 font-medium"><?php echo $cand['id']; ?></td>
                        <td class="p-4">
                            <div class="flex items-center">
                                <img src="<?php echo $cand['img']; ?>" class="w-10 h-10 rounded-full border border-slate-200 mr-3">
                                <div>
                                    <div class="font-bold text-slate-800"><?php echo $cand['name']; ?></div>
                                    <div class="text-xs text-slate-400"><?php echo $cand['email']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-slate-500"><?php echo $cand['role']; ?></td>
                        <td class="p-4 text-slate-500"><?php echo $cand['phone']; ?></td>
                        <td class="p-4 text-slate-500"><?php echo $cand['exp']; ?></td>
                        <td class="p-4 text-slate-500"><?php echo $cand['loc']; ?></td>
                        <td class="p-4">
                            <span class="status-badge">
                                <span class="status-dot"></span> Parsed
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex items-center justify-center space-x-3 text-slate-400">
                                <button class="hover:text-blue-500"><i class="far fa-file-alt"></i></button>
                                <button class="hover:text-blue-500"><i class="fas fa-download text-sm"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 flex items-center justify-between border-t border-slate-100 bg-white">
            <div class="text-sm text-slate-500">
                Showing 1 - 10 of 10 entries
            </div>
            <div class="flex items-center space-x-2">
                <button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600">
                    <i class="fas fa-chevron-left text-xs"></i>
                </button>
                <button class="w-8 h-8 flex items-center justify-center bg-[#134e4a] text-white rounded-full text-sm font-medium">
                    1
                </button>
                <button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600">
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="fixed right-0 top-1/2 transform -translate-y-1/2 bg-[#134e4a] p-2 rounded-l-md shadow-lg cursor-pointer">
        <i class="fas fa-cog text-white animate-spin-slow"></i>
    </div>

    <style>
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin-slow {
            animation: spin-slow 8s linear infinite;
        }
    </style>

</body>
</html>