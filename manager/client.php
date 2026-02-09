<?php 
// This MUST be the first thing in the file
include('../sidebars.php'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR - Clients Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Primary Theme Color: Dark Teal #1a534f */
        .bg-custom-teal { background-color: #1a534f; }
        .text-custom-teal { color: #1a534f; }
        .border-custom-teal { border-color: #1a534f; }
        .hover-teal:hover { background-color: #14403d; }
        
        .dropdown-shadow { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .avatar-ring { border: 2px solid #1a534f; padding: 2px; }
        
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

        .page-container { display: flex; min-height: 100vh; }
        
        /* Main Content Adjustments */
        .main-content {
    flex-grow: 1;
    margin-left: 95px; /* Matches the sidebar width */
    width: calc(100% - 95px);
    padding: 2rem;
    background-color: #f7f7f7;
    overflow-x: hidden;
    transition: all 0.3s ease;
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

        /* Modal & Tabs */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }
        .tab-btn {
            padding-bottom: 12px;
            font-size: 14px;
            font-weight: 700;
            color: #9ca3af;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        .tab-btn.active {
            color: #1a534f;
            border-bottom: 2px solid #1a534f;
        }

        /* Toggle Switch UI */
        .switch {
            position: relative;
            display: inline-block;
            width: 38px;
            height: 20px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 20px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 14px; width: 14px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #1a534f; }
        input:checked + .slider:before { transform: translateX(18px); }

        .permission-row { border-bottom: 1px solid #f3f4f6; }
        .permission-row:last-child { border-bottom: none; }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-input:focus { border-color: #1a534f; }
        .required-star { color: #ef4444; margin-left: 2px; }
        
        .custom-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #1a534f;
            cursor: pointer;
        }

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
        .sort-item:hover { background-color: #f9fafb; color: #1a534f; }
    </style>
</head>
<body class="text-[#333d5e] font-sans">

    <div class="page-container">
        <main class="main-content">
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
                            <button id="gridBtn" onclick="switchView('grid')" class="px-3 py-2 bg-custom-teal text-white rounded-md shadow-sm transition">
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
                                <button class="w-full text-left px-4 py-2 hover:bg-gray-50">Export as CSV</button>
                            </div>
                        </div>

                        <button onclick="toggleModal(true)" class="px-5 py-2 bg-custom-teal text-white rounded-lg flex items-center space-x-2 font-bold shadow-md hover-teal transition">
                            <i class="fa fa-plus-circle"></i> <span>Add Client</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center text-custom-teal">
                                <i class="fa-solid fa-user-group text-xl"></i>
                            </div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">Total Clients</p><h3 class="text-xl font-bold">300</h3></div>
                        </div>
                        <div class="text-[10px] font-bold text-teal-600 bg-teal-50 px-2 py-1 rounded">+19.01%</div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-500"><i class="fa-solid fa-user-check text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">Active Clients</p><h3 class="text-xl font-bold">270</h3></div>
                        </div>
                        <div class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">+12.5%</div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center text-red-500"><i class="fa-solid fa-user-slash text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">Inactive Clients</p><h3 class="text-xl font-bold">30</h3></div>
                        </div>
                        <div class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-1 rounded">-2.4%</div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center justify-between shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center text-blue-500"><i class="fa-solid fa-user-plus text-xl"></i></div>
                            <div><p class="text-[11px] font-bold text-gray-400 uppercase">New Inquiries</p><h3 class="text-xl font-bold">12</h3></div>
                        </div>
                        <div class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">Hot</div>
                    </div>
                </div>

                <div id="gridView">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-6">
                        <div class="p-6 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                            <h2 class="text-lg font-bold">Client Catalog</h2>
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
                                        <div class="sort-item" onclick="selectStatus('Pending', 'gridStatus')">Pending</div>
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
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 text-center relative shadow-sm hover:shadow-md transition">
                            <div class="absolute top-4 right-4 text-gray-300 cursor-pointer"><i class="fa fa-ellipsis-v"></i></div>
                            <div class="absolute top-4 left-4"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 custom-checkbox"></div>
                            <div class="relative w-20 h-20 mx-auto mb-3">
                                <img src="https://i.pravatar.cc/150?u=1" class="w-full h-full rounded-full avatar-ring object-cover">
                                <div class="absolute bottom-0 right-1 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></div>
                            </div>
                            <h3 class="font-bold">Michael Walker</h3>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-teal-50 text-custom-teal">CEO</span>
                            <p class="text-xs text-gray-400 mt-6 mb-2 text-left">Project : Office Management App</p>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2"><div class="bg-custom-teal h-1.5 rounded-full w-[60%]"></div></div>
                            <div class="flex justify-between items-center mb-6 text-[11px] font-bold text-custom-teal">
                                <div class="flex -space-x-2">
                                    <img src="https://i.pravatar.cc/30?u=9" class="w-6 h-6 rounded-full border-2 border-white">
                                    <div class="w-6 h-6 rounded-full bg-custom-teal text-white flex items-center justify-center border-2 border-white text-[8px]">+1</div>
                                </div>
                                <span>60%</span>
                            </div>
                            <div class="border-t border-gray-50 pt-4 text-left flex justify-between items-end">
                                <div><p class="text-[9px] text-gray-400 uppercase font-bold">Company</p><p class="text-sm font-bold text-gray-700">BrightWave Innovations</p></div>
                                <div class="flex space-x-2 text-gray-300">
                                    <i class="fa-regular fa-comment-dots border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                    <i class="fa fa-phone border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 p-6 text-center relative shadow-sm hover:shadow-md transition">
                            <div class="absolute top-4 right-4 text-gray-300 cursor-pointer"><i class="fa fa-ellipsis-v"></i></div>
                            <div class="absolute top-4 left-4"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 custom-checkbox"></div>
                            <div class="relative w-20 h-20 mx-auto mb-3">
                                <img src="https://i.pravatar.cc/150?u=2" class="w-full h-full rounded-full avatar-ring object-cover">
                                <div class="absolute bottom-0 right-1 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></div>
                            </div>
                            <h3 class="font-bold">Sarah Jenkins</h3>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-teal-50 text-custom-teal">Manager</span>
                            <p class="text-xs text-gray-400 mt-6 mb-2 text-left">Project : E-commerce Platform</p>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2"><div class="bg-blue-500 h-1.5 rounded-full w-[85%]"></div></div>
                            <div class="flex justify-between items-center mb-6 text-[11px] font-bold text-blue-500">
                                <div class="flex -space-x-2">
                                    <img src="https://i.pravatar.cc/30?u=12" class="w-6 h-6 rounded-full border-2 border-white">
                                    <div class="w-6 h-6 rounded-full bg-custom-teal text-white flex items-center justify-center border-2 border-white text-[8px]">+3</div>
                                </div>
                                <span>85%</span>
                            </div>
                            <div class="border-t border-gray-50 pt-4 text-left flex justify-between items-end">
                                <div><p class="text-[9px] text-gray-400 uppercase font-bold">Company</p><p class="text-sm font-bold text-gray-700">TechFlow Systems</p></div>
                                <div class="flex space-x-2 text-gray-300">
                                    <i class="fa-regular fa-comment-dots border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                    <i class="fa fa-phone border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 p-6 text-center relative shadow-sm">
                            <div class="absolute top-4 right-4 text-gray-300 cursor-pointer"><i class="fa fa-ellipsis-v"></i></div>
                            <div class="absolute top-4 left-4"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 custom-checkbox"></div>
                            <div class="relative w-20 h-20 mx-auto mb-3">
                                <img src="https://i.pravatar.cc/150?u=4" class="w-full h-full rounded-full avatar-ring object-cover">
                                <div class="absolute bottom-0 right-1 w-3 h-3 bg-gray-300 border-2 border-white rounded-full"></div>
                            </div>
                            <h3 class="font-bold">James Wilson</h3>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-gray-50 text-gray-400">Director</span>
                            <p class="text-xs text-gray-400 mt-6 mb-2 text-left">Project : HR Management</p>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2"><div class="bg-red-500 h-1.5 rounded-full w-[30%]"></div></div>
                            <div class="flex justify-between items-center mb-6 text-[11px] font-bold text-red-500">
                                <div class="flex -space-x-2">
                                    <img src="https://i.pravatar.cc/30?u=22" class="w-6 h-6 rounded-full border-2 border-white">
                                </div>
                                <span>30%</span>
                            </div>
                            <div class="border-t border-gray-50 pt-4 text-left flex justify-between items-end">
                                <div><p class="text-[9px] text-gray-400 uppercase font-bold">Company</p><p class="text-sm font-bold text-gray-700">Global Logistics</p></div>
                                <div class="flex space-x-2 text-gray-300">
                                    <i class="fa-regular fa-comment-dots border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                    <i class="fa fa-phone border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 p-6 text-center relative shadow-sm">
                            <div class="absolute top-4 right-4 text-gray-300 cursor-pointer"><i class="fa fa-ellipsis-v"></i></div>
                            <div class="absolute top-4 left-4"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 custom-checkbox"></div>
                            <div class="relative w-20 h-20 mx-auto mb-3">
                                <img src="https://i.pravatar.cc/150?u=5" class="w-full h-full rounded-full avatar-ring object-cover">
                                <div class="absolute bottom-0 right-1 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></div>
                            </div>
                            <h3 class="font-bold">Emma Davis</h3>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-teal-50 text-custom-teal">Lead Designer</span>
                            <p class="text-xs text-gray-400 mt-6 mb-2 text-left">Project : Mobile App Design</p>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2"><div class="bg-emerald-500 h-1.5 rounded-full w-[95%]"></div></div>
                            <div class="flex justify-between items-center mb-6 text-[11px] font-bold text-emerald-500">
                                <div class="flex -space-x-2">
                                    <img src="https://i.pravatar.cc/30?u=33" class="w-6 h-6 rounded-full border-2 border-white">
                                    <div class="w-6 h-6 rounded-full bg-custom-teal text-white flex items-center justify-center border-2 border-white text-[8px]">+2</div>
                                </div>
                                <span>95%</span>
                            </div>
                            <div class="border-t border-gray-50 pt-4 text-left flex justify-between items-end">
                                <div><p class="text-[9px] text-gray-400 uppercase font-bold">Company</p><p class="text-sm font-bold text-gray-700">Creative Hub</p></div>
                                <div class="flex space-x-2 text-gray-300">
                                    <i class="fa-regular fa-comment-dots border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                    <i class="fa fa-phone border p-1.5 rounded-lg cursor-pointer hover:text-custom-teal"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="listView" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
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
                                        <div class="sort-item" onclick="selectSort('Last 7 Days', 'listSort')">Last 7 Days</div>
                                        <div class="sort-item" onclick="selectSort('Name Ascending', 'listSort')">Name Ascending</div>
                                        <div class="sort-item" onclick="selectSort('Name Descending', 'listSort')">Name Descending</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                            <div class="flex items-center space-x-2 text-sm text-gray-500">
                                <span>Row Per Page</span>
                                <select class="border border-gray-200 rounded px-2 py-1 outline-none focus:border-custom-teal">
                                    <option>10</option>
                                    <option>25</option>
                                    <option>50</option>
                                    <option>100</option>
                                </select>
                                <span>Entries</span>
                            </div>
                            <div class="relative w-full md:w-64">
                                <input type="text" placeholder="Search Clients..." class="w-full pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-custom-teal">
                                <i class="fa fa-search absolute right-3 top-2.5 text-gray-300"></i>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full client-table">
                            <thead>
                                <tr>
                                    <th class="w-12"><input type="checkbox" class="w-4 h-4 rounded border-gray-300"></th>
                                    <th>Client ID</th>
                                    <th>Client Name</th>
                                    <th>Company Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
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
                                    <td><span class="status-badge-active"><span class="dot"></span>Active</span></td>
                                    <td>
                                        <div class="flex items-center space-x-4 text-gray-400">
                                            <button class="hover:text-custom-teal"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="hover:text-red-500"><i class="fa-regular fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td><input type="checkbox" class="w-4 h-4 rounded border-gray-300"></td>
                                    <td class="font-medium text-gray-700">Cli-002</td>
                                    <td>
                                        <div class="flex items-center space-x-3">
                                            <img src="https://i.pravatar.cc/40?u=2" class="w-10 h-10 rounded-full object-cover">
                                            <div>
                                                <div class="font-bold text-gray-800">Sarah Jenkins</div>
                                                <div class="text-[11px] text-gray-400 uppercase">Manager</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-gray-500">TechFlow Systems</td>
                                    <td class="text-gray-500">sarah@techflow.com</td>
                                    <td class="text-gray-500">(163) 8821 445</td>
                                    <td><span class="status-badge-active"><span class="dot"></span>Active</span></td>
                                    <td>
                                        <div class="flex items-center space-x-4 text-gray-400">
                                            <button class="hover:text-custom-teal"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="hover:text-red-500"><i class="fa-regular fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td><input type="checkbox" class="w-4 h-4 rounded border-gray-300"></td>
                                    <td class="font-medium text-gray-700">Cli-003</td>
                                    <td>
                                        <div class="flex items-center space-x-3">
                                            <img src="https://i.pravatar.cc/40?u=3" class="w-10 h-10 rounded-full object-cover">
                                            <div>
                                                <div class="font-bold text-gray-800">Robert Fox</div>
                                                <div class="text-[11px] text-gray-400 uppercase">Director</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-gray-500">Fox Solution</td>
                                    <td class="text-gray-500">robert@fox.com</td>
                                    <td class="text-gray-500">(163) 9988 112</td>
                                    <td><span class="status-badge-active"><span class="dot"></span>Active</span></td>
                                    <td>
                                        <div class="flex items-center space-x-4 text-gray-400">
                                            <button class="hover:text-custom-teal"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="hover:text-red-500"><i class="fa-regular fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td><input type="checkbox" class="w-4 h-4 rounded border-gray-300"></td>
                                    <td class="font-medium text-gray-700">Cli-004</td>
                                    <td>
                                        <div class="flex items-center space-x-3">
                                            <img src="https://i.pravatar.cc/40?u=4" class="w-10 h-10 rounded-full object-cover">
                                            <div>
                                                <div class="font-bold text-gray-800">James Wilson</div>
                                                <div class="text-[11px] text-gray-400 uppercase">Director</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-gray-500">Global Logistics</td>
                                    <td class="text-gray-500">james@logistic.com</td>
                                    <td class="text-gray-500">(163) 5544 332</td>
                                    <td><span class="status-badge-active bg-red-500"><span class="dot"></span>Inactive</span></td>
                                    <td>
                                        <div class="flex items-center space-x-4 text-gray-400">
                                            <button class="hover:text-custom-teal"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="hover:text-red-500"><i class="fa-regular fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td><input type="checkbox" class="w-4 h-4 rounded border-gray-300"></td>
                                    <td class="font-medium text-gray-700">Cli-005</td>
                                    <td>
                                        <div class="flex items-center space-x-3">
                                            <img src="https://i.pravatar.cc/40?u=5" class="w-10 h-10 rounded-full object-cover">
                                            <div>
                                                <div class="font-bold text-gray-800">Emma Davis</div>
                                                <div class="text-[11px] text-gray-400 uppercase">Lead</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-gray-500">Creative Hub</td>
                                    <td class="text-gray-500">emma@creative.com</td>
                                    <td class="text-gray-500">(163) 1122 334</td>
                                    <td><span class="status-badge-active"><span class="dot"></span>Active</span></td>
                                    <td>
                                        <div class="flex items-center space-x-4 text-gray-400">
                                            <button class="hover:text-custom-teal"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="hover:text-red-500"><i class="fa-regular fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex justify-center mt-12">
                    <button class="px-8 py-2.5 bg-custom-teal text-white rounded-lg font-bold shadow-lg hover-teal transition">Load More Clients</button>
                </div>
            </div>
        </main>
    </div>

    <div id="addClientModal" class="hidden fixed inset-0 z-[100] modal-overlay flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden">
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100">
                <h2 class="text-xl font-bold text-[#333d5e]">Add New Client</h2>
                <button onclick="toggleModal(false)" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>

            <div class="px-6 pt-4 flex space-x-8 border-b border-gray-100">
                <button onclick="switchTab('basic')" id="tab-basic" class="tab-btn active">Basic Information</button>
                <button onclick="switchTab('permissions')" id="tab-permissions" class="tab-btn">Permissions</button>
            </div>

            <div id="content-basic" class="p-8 max-h-[70vh] overflow-y-auto">
                <div class="bg-gray-50 rounded-xl p-6 mb-8 border border-dashed border-gray-200 flex items-center space-x-6">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center border border-gray-100 shadow-sm">
                        <i class="fa-regular fa-image text-3xl text-gray-300"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800">Upload Profile Image</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Image should be below 4 mb</p>
                        <div class="mt-3 flex space-x-2">
                            <button class="px-4 py-1.5 bg-custom-teal text-white text-xs font-bold rounded-lg shadow-sm hover-teal">Upload</button>
                            <button class="px-4 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-bold rounded-lg shadow-sm">Cancel</button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    <div>
                        <label class="block text-sm font-bold mb-2">First Name <span class="required-star">*</span></label>
                        <input type="text" class="form-input" placeholder="Enter first name">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700">Last Name</label>
                        <input type="text" class="form-input" placeholder="Enter last name">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2">Username <span class="required-star">*</span></label>
                        <input type="text" class="form-input" placeholder="Choose username">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2">Email <span class="required-star">*</span></label>
                        <input type="email" class="form-input" placeholder="example@domain.com">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2">Password <span class="required-star">*</span></label>
                        <div class="relative">
                            <input type="password" class="form-input pr-10">
                            <i class="fa-regular fa-eye-slash absolute right-3 top-3.5 text-gray-400 cursor-pointer text-sm"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2">Confirm Password <span class="required-star">*</span></label>
                        <div class="relative">
                            <input type="password" class="form-input pr-10">
                            <i class="fa-regular fa-eye-slash absolute right-3 top-3.5 text-gray-400 cursor-pointer text-sm"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2">Phone Number <span class="required-star">*</span></label>
                        <input type="text" class="form-input" placeholder="+1 (000) 000-0000">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700">Company</label>
                        <input type="text" class="form-input" placeholder="Company name">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2">Client Status</label>
                        <select class="form-input">
                            <option>Active</option>
                            <option>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2">Address</label>
                        <input type="text" class="form-input" placeholder="Full Address">
                    </div>
                </div>
            </div>

            <div id="content-permissions" class="hidden p-8 max-h-[70vh] overflow-y-auto">
                <div class="bg-gray-50 rounded-lg p-4 mb-6 flex justify-between items-center border border-gray-100">
                    <span class="font-bold text-custom-teal">Global Module Control</span>
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-3">
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                            <span class="text-sm font-bold text-gray-700">Enable all Module</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="checkbox" checked class="custom-checkbox">
                            <span class="text-sm font-bold text-gray-700">Select All</span>
                        </div>
                    </div>
                </div>

                <div class="w-full">
                    <div class="grid grid-cols-7 gap-4 pb-4 border-b border-gray-100 mb-2">
                        <div class="col-span-2 text-sm font-bold text-gray-400">MODULE</div>
                        <div class="text-sm font-bold text-gray-400 text-center">Read</div>
                        <div class="text-sm font-bold text-gray-400 text-center">Write</div>
                        <div class="text-sm font-bold text-gray-400 text-center">Create</div>
                        <div class="text-sm font-bold text-gray-400 text-center">Delete</div>
                        <div class="text-sm font-bold text-gray-400 text-center">Import</div>
                    </div>

                    <div class="space-y-1">
                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Holidays</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Leaves</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Clients</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Projects</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Tasks</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox"><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Assets</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox"><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Invoices</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Timing</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-4 py-3 items-center permission-row">
                            <div class="col-span-2 flex items-center space-x-3">
                                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                                <span class="text-sm font-bold text-gray-500">Payroll</span>
                            </div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" checked class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                            <div class="flex justify-center"><input type="checkbox" class="custom-checkbox"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-8 py-6 bg-gray-50 flex justify-end space-x-3 border-t border-gray-100">
                <button onclick="toggleModal(false)" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 font-bold rounded-lg shadow-sm hover:bg-gray-100 transition">Cancel</button>
                <button class="px-6 py-2.5 bg-custom-teal text-white font-bold rounded-lg shadow-md hover-teal transition">Save Client</button>
            </div>
        </div>
    </div>
    <script>
        // Logic for Switching between Basic Info and Permissions Tabs
        function switchTab(tab) {
            const basicBtn = document.getElementById('tab-basic');
            const permBtn = document.getElementById('tab-permissions');
            const basicContent = document.getElementById('content-basic');
            const permContent = document.getElementById('content-permissions');

            if (tab === 'basic') {
                basicBtn.classList.add('active');
                permBtn.classList.remove('active');
                basicContent.classList.remove('hidden');
                permContent.classList.add('hidden');
            } else {
                permBtn.classList.add('active');
                basicBtn.classList.remove('active');
                permContent.classList.remove('hidden');
                basicContent.classList.add('hidden');
            }
        }

        // Logic for Displaying/Hiding the Add Client Modal
        function toggleModal(show) {
            const modal = document.getElementById('addClientModal');
            if (show) {
                modal.classList.remove('hidden');
                switchTab('basic'); 
            } else {
                modal.classList.add('hidden');
            }
        }

        // Logic for Switching Between Grid and List Views in the Dashboard
        function switchView(view) {
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const gridBtn = document.getElementById('gridBtn');
            const listBtn = document.getElementById('listBtn');
            const breadcrumb = document.getElementById('breadcrumb');

            if (view === 'grid') {
                gridView.classList.remove('hidden');
                listView.classList.add('hidden');
                gridBtn.classList.add('bg-custom-teal', 'text-white');
                gridBtn.classList.remove('text-gray-400');
                listBtn.classList.remove('bg-custom-teal', 'text-white');
                listBtn.classList.add('text-gray-400');
                breadcrumb.innerText = "Client Grid";
            } else {
                gridView.classList.add('hidden');
                listView.classList.remove('hidden');
                listBtn.classList.add('bg-custom-teal', 'text-white');
                listBtn.classList.remove('text-gray-400');
                gridBtn.classList.remove('bg-custom-teal', 'text-white');
                gridBtn.classList.add('text-gray-400');
                breadcrumb.innerText = "Client List";
            }
        }

        // Dropdown Logic for Export and Sort Menus
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

        // Updates the button labels after selection
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

        // Global Window Event Listener to close open menus when clicking elsewhere
        window.onclick = function(event) {
            if(document.getElementById('exportMenu')) {
                document.getElementById('exportMenu').classList.add('hidden');
            }
            document.querySelectorAll('.sort-dropdown-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
            
            const modal = document.getElementById('addClientModal');
            if (event.target == modal) { 
                toggleModal(false); 
            }
        }

        // Console logger to ensure script loads correctly
        console.log("Dashboard Loaded Successfully - Sidebar Removed.");
    </script>
</body>
</html>