<?php
// Prevent "Headers already sent" error
ob_start();
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR - Clients</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-custom-orange { background-color: #f66a23; }
        .bg-theme-teal { background-color: #1b5a5a; }
        .text-custom-orange { color: #f66a23; }
        .dropdown-shadow { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .avatar-ring { border: 2px solid #f66a23; padding: 2px; }
        .status-badge-active { 
            background-color: #00c48c; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }
        .dot { height: 6px; width: 6px; background-color: white; border-radius: 50%; display: inline-block; margin-right: 6px; }

        /* --- SIDEBAR INTEGRATION STYLES --- */
        :root {
            --primary-sidebar-width: 95px;
        }
        
        body {
            background-color: #f7f7f7;
        }

        /* Adjusted Main Content to fit with fixed Sidebar */
        .main-content {
            margin-left: var(--primary-sidebar-width);
            transition: margin-left 0.3s ease;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Class added by sidebar JS when submenu opens */
        .main-content.main-shifted {
            margin-left: 315px; /* 95px + 220px */
        }

        .client-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            color: #333d5e;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .client-table td {
            padding: 12px 16px;
            font-size: 14px;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
        }

        /* Custom Sort Dropdown Styles */
        .sort-dropdown-menu {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #f1f1f1;
        }
        .sort-item {
            padding: 12px 24px;
            color: #333d5e;
            font-size: 15px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .sort-item:hover {
            background-color: #f9fafb;
            color: #1b5a5a;
        }
    </style>
</head>
<body class="text-[#333d5e] font-sans">

    <?php include '../sidebars.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="max-w-[1600px] mx-auto">
            
            <div class="flex justify-between items-start mb-8">
                <div>
                    <h1 class="text-2xl font-bold">Clients</h1>
                    <nav class="text-sm text-gray-400 flex items-center space-x-2 mt-1">
                        <i class="fa fa-home"></i> <span>/ Projects / <span id="breadcrumb" class="text-gray-600">Client Grid</span></span>
                    </nav>
                </div>
                
                <div class="flex items-center space-x-3">
                    <div class="flex bg-white border border-gray-200 rounded-lg p-1 shadow-sm">
                        <button id="listBtn" onclick="switchView('list')" class="px-3 py-2 text-gray-400 hover:text-gray-600 transition">
                            <i class="fa-solid fa-list-ul"></i>
                        </button>
                        <button id="gridBtn" onclick="switchView('grid')" class="px-3 py-2 bg-theme-teal text-white rounded-md shadow-sm transition">
                            <i class="fa-solid fa-table-cells-large"></i>
                        </button>
                    </div>

                    <div class="relative">
                        <button onclick="toggleExport(event)" class="px-4 py-2 bg-white border border-gray-200 rounded-lg flex items-center space-x-2 font-semibold text-gray-700 hover:bg-gray-50 transition">
                            <i class="fa-regular fa-file-lines"></i> <span>Export</span> <i class="fa fa-chevron-down text-[10px] ml-1"></i>
                        </button>
                        <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl dropdown-shadow border py-2 z-30 text-sm">
                            <button class="w-full text-left px-4 py-2 hover:bg-gray-50">Export as PDF</button>
                            <button class="w-full text-left px-4 py-2 hover:bg-gray-50">Export as Excel</button>
                        </div>
                    </div>

                    <button onclick="toggleModal(event)" class="px-5 py-2 bg-theme-teal text-white rounded-lg flex items-center space-x-2 font-bold shadow-md hover:opacity-90 transition">
                        <i class="fa fa-plus-circle"></i> <span>Add Client</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-pink-50 rounded-lg flex items-center justify-center text-pink-500"><i class="fa-solid fa-users-viewfinder text-xl"></i></div>
                        <div><p class="text-[11px] font-bold text-gray-400 uppercase">Total Clients</p><h3 class="text-xl font-bold">300</h3></div>
                    </div>
                    <div class="text-[10px] font-bold text-pink-500 bg-pink-50 px-2 py-1 rounded">+19.01%</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-500"><i class="fa-solid fa-user-check text-xl"></i></div>
                        <div><p class="text-[11px] font-bold text-gray-400 uppercase">Active Clients</p><h3 class="text-xl font-bold">270</h3></div>
                    </div>
                    <div class="text-[10px] font-bold text-orange-400 bg-orange-50 px-2 py-1 rounded">+19.01%</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center text-red-500"><i class="fa-solid fa-user-slash text-xl"></i></div>
                        <div><p class="text-[11px] font-bold text-gray-400 uppercase">Inactive Clients</p><h3 class="text-xl font-bold">30</h3></div>
                    </div>
                    <div class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-1 rounded">+19.01%</div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center text-blue-500"><i class="fa-solid fa-user-plus text-xl"></i></div>
                        <div><p class="text-[11px] font-bold text-gray-400 uppercase">New Clients</p><h3 class="text-xl font-bold">300</h3></div>
                    </div>
                    <div class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-1 rounded">+19.01%</div>
                </div>
            </div>

            <div id="gridView">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-6">
                    <div class="p-6 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                        <h2 class="text-lg font-bold">Client Grid</h2>
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <button onclick="toggleSortDropdown(event, 'gridStatus')" class="appearance-none pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none flex items-center min-w-[150px]">
                                    <span id="gridStatusLabel">Select Status</span>
                                    <i class="fa fa-chevron-down absolute right-4 text-[10px] text-gray-400"></i>
                                </button>
                                <div id="gridStatus" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl sort-dropdown-menu z-50 overflow-hidden">
                                    <div class="sort-item" onclick="selectStatus('Select Status', 'gridStatus')">Select Status</div>
                                    <div class="sort-item" onclick="selectStatus('Active', 'gridStatus')">Active</div>
                                    <div class="sort-item" onclick="selectStatus('Inactive', 'gridStatus')">Inactive</div>
                                </div>
                            </div>
                            
                            <div class="relative">
                                <button onclick="toggleSortDropdown(event, 'gridSort')" class="appearance-none pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none flex items-center min-w-[180px]">
                                    <span id="gridSortLabel">Sort By : Last 7 Days</span>
                                    <i class="fa fa-chevron-down absolute right-4 text-[10px] text-gray-400"></i>
                                </button>
                                <div id="gridSort" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl sort-dropdown-menu z-50 overflow-hidden">
                                    <div class="sort-item" onclick="selectSort('Recently Added', 'gridSort')">Recently Added</div>
                                    <div class="sort-item" onclick="selectSort('Ascending', 'gridSort')">Ascending</div>
                                    <div class="sort-item" onclick="selectSort('Descending', 'gridSort')">Descending</div>
                                    <div class="sort-item" onclick="selectSort('Last Month', 'gridSort')">Last Month</div>
                                    <div class="sort-item" onclick="selectSort('Last 7 Days', 'gridSort')">Last 7 Days</div>
                                </div>
                            </div>
                            </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 text-center relative shadow-sm">
                        <div class="absolute top-4 right-4 text-gray-300 cursor-pointer"><i class="fa fa-ellipsis-v"></i></div>
                        <div class="absolute top-4 left-4"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 accent-custom-orange"></div>
                        <div class="relative w-20 h-20 mx-auto mb-3">
                            <img src="https://i.pravatar.cc/150?u=1" class="w-full h-full rounded-full avatar-ring object-cover">
                            <div class="absolute bottom-0 right-1 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></div>
                        </div>
                        <h3 class="font-bold">Michael Walker</h3>
                        <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-pink-50 text-pink-400">CEO</span>
                        <p class="text-xs text-gray-400 mt-6 mb-2 text-left">Project : Office Management App</p>
                        <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2"><div class="bg-purple-500 h-1.5 rounded-full w-[60%]"></div></div>
                        <div class="flex justify-between items-center mb-6 text-[11px] font-bold text-purple-500">
                            <div class="flex -space-x-2">
                                <img src="https://i.pravatar.cc/30?u=9" class="w-6 h-6 rounded-full border-2 border-white">
                                <div class="w-6 h-6 rounded-full bg-custom-orange text-white flex items-center justify-center border-2 border-white text-[8px]">+1</div>
                            </div>
                            <span>60%</span>
                        </div>
                        <div class="border-t border-gray-50 pt-4 text-left flex justify-between items-end">
                            <div><p class="text-[9px] text-gray-400 uppercase font-bold">Company</p><p class="text-sm font-bold text-gray-700">BrightWave Innovations</p></div>
                            <div class="flex space-x-2 text-gray-300">
                                <i class="fa-regular fa-comment-dots border p-1.5 rounded-lg cursor-pointer hover:text-custom-orange"></i>
                                <i class="fa fa-phone border p-1.5 rounded-lg cursor-pointer hover:text-custom-orange"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="listView" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-6 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <h2 class="text-lg font-bold">Client List</h2>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button onclick="toggleSortDropdown(event, 'listStatus')" class="appearance-none pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none flex items-center min-w-[150px]">
                                <span id="listStatusLabel">Select Status</span>
                                <i class="fa fa-chevron-down absolute right-4 text-[10px] text-gray-400"></i>
                            </button>
                            <div id="listStatus" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl sort-dropdown-menu z-50 overflow-hidden">
                                <div class="sort-item" onclick="selectStatus('Select Status', 'listStatus')">Select Status</div>
                                <div class="sort-item" onclick="selectStatus('Active', 'listStatus')">Active</div>
                                <div class="sort-item" onclick="selectStatus('Inactive', 'listStatus')">Inactive</div>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <button onclick="toggleSortDropdown(event, 'listSort')" class="appearance-none pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none flex items-center min-w-[180px]">
                                <span id="listSortLabel">Sort By : Last 7 Days</span>
                                <i class="fa fa-chevron-down absolute right-4 text-[10px] text-gray-400"></i>
                            </button>
                            <div id="listSort" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl sort-dropdown-menu z-50 overflow-hidden">
                                <div class="sort-item" onclick="selectSort('Recently Added', 'listSort')">Recently Added</div>
                                <div class="sort-item" onclick="selectSort('Ascending', 'listSort')">Ascending</div>
                                <div class="sort-item" onclick="selectSort('Descending', 'listSort')">Descending</div>
                                <div class="sort-item" onclick="selectSort('Last Month', 'listSort')">Last Month</div>
                                <div class="sort-item" onclick="selectSort('Last 7 Days', 'listSort')">Last 7 Days</div>
                            </div>
                        </div>

                    </div>
                </div>
                <hr class="border-gray-50">
                <div class="p-6 flex justify-between items-center">
                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                        <span>Row Per Page</span>
                        <div class="relative">
                            <select class="appearance-none border border-gray-200 rounded px-2 py-1.5 pr-8 bg-white focus:outline-none">
                                <option>10</option>
                            </select>
                            <i class="fa fa-chevron-down absolute right-2 top-2.5 text-[10px] text-gray-400"></i>
                        </div>
                        <span>Entries</span>
                    </div>
                    <div class="relative">
                        <input type="text" placeholder="Search" class="pl-4 pr-4 py-2 border border-gray-100 rounded-lg bg-gray-50 text-sm w-64 focus:outline-none">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full client-table">
                        <thead>
                            <tr>
                                <th class="w-12"><input type="checkbox" class="w-4 h-4 rounded border-gray-300"></th>
                                <th>Client ID <i class="fa-solid fa-arrow-up-long text-[10px] ml-1 text-gray-300"></i><i class="fa-solid fa-arrow-down-long text-[10px] text-gray-300"></i></th>
                                <th>Client Name <i class="fa-solid fa-arrow-up-long text-[10px] ml-1 text-gray-300"></i><i class="fa-solid fa-arrow-down-long text-[10px] text-gray-300"></i></th>
                                <th>Company Name <i class="fa-solid fa-arrow-up-long text-[10px] ml-1 text-gray-300"></i><i class="fa-solid fa-arrow-down-long text-[10px] text-gray-300"></i></th>
                                <th>Email <i class="fa-solid fa-arrow-up-long text-[10px] ml-1 text-gray-300"></i><i class="fa-solid fa-arrow-down-long text-[10px] text-gray-300"></i></th>
                                <th>Phone <i class="fa-solid fa-arrow-up-long text-[10px] ml-1 text-gray-300"></i><i class="fa-solid fa-arrow-down-long text-[10px] text-gray-300"></i></th>
                                <th>Status <i class="fa-solid fa-arrow-up-long text-[10px] ml-1 text-gray-300"></i><i class="fa-solid fa-arrow-down-long text-[10px] text-gray-300"></i></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="hover:bg-gray-50">
                                <td><input type="checkbox" class="w-4 h-4 rounded border-gray-300"></td>
                                <td class="font-medium text-gray-700">Cli-001</td>
                                <td>
                                    <div class="flex items-center space-x-3">
                                        <img src="https://i.pravatar.cc/40?u=1" class="w-10 h-10 rounded-full object-cover">
                                        <div>
                                            <div class="font-bold text-gray-800">Michael Walker</div>
                                            <div class="text-[11px] text-gray-400 uppercase">CEO</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-gray-500">BrightWave Innovations</td>
                                <td class="text-gray-500">michael@example.com</td>
                                <td class="text-gray-500">(163) 2459 315</td>
                                <td>
                                    <span class="status-badge-active">
                                        <span class="dot"></span>Active
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center space-x-4 text-gray-400">
                                        <button class="hover:text-blue-500"><i class="fa-regular fa-pen-to-square"></i></button>
                                        <button class="hover:text-red-500"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-center mt-12">
                <button class="px-8 py-2.5 bg-theme-teal text-white rounded-lg font-bold shadow-lg hover:opacity-90 transition">Load More</button>
            </div>
        </div>
    </main>

    <script>
        function switchView(view) {
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const gridBtn = document.getElementById('gridBtn');
            const listBtn = document.getElementById('listBtn');
            const breadcrumb = document.getElementById('breadcrumb');

            if (view === 'grid') {
                gridView.classList.remove('hidden');
                listView.classList.add('hidden');
                gridBtn.classList.add('bg-theme-teal', 'text-white', 'rounded-md');
                gridBtn.classList.remove('text-gray-400');
                listBtn.classList.remove('bg-theme-teal', 'text-white', 'rounded-md');
                listBtn.classList.add('text-gray-400');
                breadcrumb.innerText = "Client Grid";
            } else {
                gridView.classList.add('hidden');
                listView.classList.remove('hidden');
                listBtn.classList.add('bg-theme-teal', 'text-white', 'rounded-md');
                listBtn.classList.remove('text-gray-400');
                gridBtn.classList.remove('bg-theme-teal', 'text-white', 'rounded-md');
                gridBtn.classList.add('text-gray-400');
                breadcrumb.innerText = "Client List";
            }
        }

        function toggleExport(e) {
            e.stopPropagation();
            document.getElementById('exportMenu').classList.toggle('hidden');
        }

        function toggleSortDropdown(e, menuId) {
            e.stopPropagation();
            document.querySelectorAll('.sort-dropdown-menu').forEach(menu => {
                if(menu.id !== menuId) menu.classList.add('hidden');
            });
            document.getElementById(menuId).classList.toggle('hidden');
        }

        function selectSort(value, menuId) {
            const labelId = menuId === 'gridSort' ? 'gridSortLabel' : 'listSortLabel';
            document.getElementById(labelId).innerText = 'Sort By : ' + value;
            document.getElementById(menuId).classList.add('hidden');
        }

        function selectStatus(value, menuId) {
            const labelId = menuId === 'gridStatus' ? 'gridStatusLabel' : 'listStatusLabel';
            document.getElementById(labelId).innerText = value;
            document.getElementById(menuId).classList.add('hidden');
        }

        window.onclick = function() {
            if(document.getElementById('exportMenu')) document.getElementById('exportMenu').classList.add('hidden');
            document.querySelectorAll('.sort-dropdown-menu').forEach(menu => menu.classList.add('hidden'));
        }
    </script>
</body>
</html>