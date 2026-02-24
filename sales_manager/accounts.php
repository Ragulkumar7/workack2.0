<?php 
include '../../sidebars.php'; 
include '../../header.php';
// Commented out includes for testing purposes, uncomment in your real file
?>
<?php
// Mock Data (Replace this with your actual database query later)
$leads = [
    [
        'name' => 'madhan', 'date' => '18/2/2025', 'email' => 'madhan@gmail.com', 
        'phone' => '6565463231', 'address' => 'Hosur, Tamilnadu', 'status' => 'inactive', 'assigned' => 'ram'
    ],
    [
        'name' => 'kumar', 'date' => '18/2/2025', 'email' => 'kumar@gmail.com', 
        'phone' => '6565463231', 'address' => 'Hosur, Tamilnadu', 'status' => 'inactive', 'assigned' => 'ram'
    ],
    [
        'name' => 'KSB Pumps', 'date' => '25/2/2025', 'email' => 'developer.smiv@gmail.com', 
        'phone' => '123456789', 'address' => 'Coimbatore', 'status' => 'converted', 'assigned' => 'smivin'
    ],
    [
        'name' => 'Test', 'date' => '25/2/2025', 'email' => 'developer1.smiv@gmail.com', 
        'phone' => '12345678', 'address' => 'cbe', 'status' => 'inactive', 'assigned' => ''
    ],
    [
        'name' => 'AMEEN', 'date' => '25/2/2025', 'email' => 'ameenshanib@gmail.com', 
        'phone' => '9747073669', 'address' => 'bye pass road Perinthalmanna, Malappuram, kerala. Pin: 679322', 'status' => 'inactive', 'assigned' => ''
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts / Leads</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Very light gray/slate bg */
        }
        
        /* Custom scrollbar for webkit */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800">

    <main class="p-6 md:p-8 max-w-[1400px] mx-auto">
        
        <div class="text-sm font-medium mb-6">
            <span class="text-gray-800">Dashboard</span>
            <span class="text-gray-400 mx-2">/</span>
            <span class="text-[#1b5a5a]">accounts</span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl p-5 shadow-sm border-t-4 border-[#1b5a5a]">
                <p class="text-sm font-medium text-[#1b5a5a]">Total Leads</p>
                <h3 class="text-2xl font-bold text-[#1b5a5a] mt-1">5</h3>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-t-4 border-blue-500">
                <p class="text-sm font-medium text-blue-600">Follow Ups</p>
                <h3 class="text-2xl font-bold text-blue-600 mt-1">0</h3>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-t-4 border-purple-500">
                <p class="text-sm font-medium text-purple-600">In Progress</p>
                <h3 class="text-2xl font-bold text-purple-600 mt-1">0</h3>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-t-4 border-green-500">
                <p class="text-sm font-medium text-green-600">Opportunities</p>
                <h3 class="text-2xl font-bold text-green-600 mt-1">0</h3>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-t-4 border-indigo-500">
                <p class="text-sm font-medium text-indigo-600">Converted</p>
                <h3 class="text-2xl font-bold text-indigo-600 mt-1">1</h3>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border-t-4 border-red-500">
                <p class="text-sm font-medium text-red-600">In active</p>
                <h3 class="text-2xl font-bold text-red-600 mt-1">4</h3>
            </div>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full md:w-auto">
                <h1 class="text-2xl font-bold text-slate-900">Accounts</h1>
                
                <div class="relative w-full sm:w-72">
                    <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                    <input type="text" placeholder="Search across all routes..." class="w-full pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-[#1b5a5a] focus:ring-1 focus:ring-[#1b5a5a] transition-all">
                </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                <button class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-600 transition-colors">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                </button>
                <button class="bg-[#1b5a5a] hover:bg-[#134343] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i> Create Lead
                </button>
                <button class="bg-[#1b5a5a] hover:bg-[#134343] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 shadow-sm">
                    <i data-lucide="upload" class="w-4 h-4"></i> Upload Leads
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 text-xs text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-4 px-6 w-12"><input type="checkbox" class="rounded border-gray-300 text-[#1b5a5a] focus:ring-[#1b5a5a]"></th>
                            <th class="py-4 px-6 cursor-pointer hover:text-gray-700">NAME <span class="text-gray-400 ml-1">↑</span></th>
                            <th class="py-4 px-6">DATE</th>
                            <th class="py-4 px-6">EMAIL</th>
                            <th class="py-4 px-6">PHONE</th>
                            <th class="py-4 px-6">ADDRESS</th>
                            <th class="py-4 px-6">STATUS</th>
                            <th class="py-4 px-6">ASSIGNED TO</th>
                            <th class="py-4 px-6 text-center">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 text-sm">
                        <?php foreach($leads as $lead): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                <td class="py-4 px-6"><input type="checkbox" class="rounded border-gray-300 text-[#1b5a5a] focus:ring-[#1b5a5a]"></td>
                                <td class="py-4 px-6 font-medium text-blue-600 hover:text-blue-800 cursor-pointer"><?php echo htmlspecialchars($lead['name']); ?></td>
                                <td class="py-4 px-6 text-gray-600"><?php echo htmlspecialchars($lead['date']); ?></td>
                                <td class="py-4 px-6 text-gray-600"><?php echo htmlspecialchars($lead['email']); ?></td>
                                <td class="py-4 px-6 text-gray-600"><?php echo htmlspecialchars($lead['phone']); ?></td>
                                <td class="py-4 px-6 text-gray-600 max-w-[200px] truncate" title="<?php echo htmlspecialchars($lead['address']); ?>"><?php echo htmlspecialchars($lead['address']); ?></td>
                                <td class="py-4 px-6">
                                    <?php if(strtolower($lead['status']) === 'inactive'): ?>
                                        <span class="bg-red-50 text-red-600 border border-red-100 px-2.5 py-1 rounded-full text-xs font-semibold tracking-wide">inactive</span>
                                    <?php else: ?>
                                        <span class="bg-green-50 text-green-600 border border-green-100 px-2.5 py-1 rounded-full text-xs font-semibold tracking-wide">converted</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-gray-600"><?php echo htmlspecialchars($lead['assigned']); ?></td>
                                <td class="py-4 px-6 text-center text-gray-400">
                                    <button class="hover:text-gray-700 p-1 rounded-md hover:bg-gray-100 transition-colors">
                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-center p-4 border-t border-gray-100 text-sm text-gray-500 bg-white">
                <div class="mb-4 sm:mb-0">Rows per page: <span class="font-medium text-gray-700 ml-1">10</span></div>
                <div class="flex items-center gap-6">
                    <span>1-5 of 5</span>
                    <div class="flex gap-4 font-medium">
                        <button class="text-gray-400 cursor-not-allowed" disabled>Previous</button>
                        <button class="text-indigo-600 hover:text-indigo-800 transition-colors">Next</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
</body>
</html>