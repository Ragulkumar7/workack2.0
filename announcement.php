<?php
// announcement.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Check Login
if (!isset($_SESSION['user_id'])) { 
    // header("Location: index.php"); 
    // exit(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | Announcements</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        /* SIDEBAR TRANSITION */
        #mainContent { 
            margin-left: 95px; 
            padding: 30px; 
            width: calc(100% - 95px); 
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        #mainContent.main-shifted { 
            margin-left: 315px; 
            width: calc(100% - 315px); 
        }

        /* Custom Colors */
        .text-primary { color: #ff5b37; }
        .bg-primary { background-color: #ff5b37; }
        .hover-bg-primary:hover { background-color: #e64a0f; }
        .border-primary { border-color: #ff5b37; }

        /* Tabs Active State */
        .tab-btn.active {
            color: #ff5b37;
            border-bottom: 2px solid #ff5b37;
            background-color: #fff7ed;
        }

        /* Modal Transitions */
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
    </style>
</head>
<body class="text-slate-600">

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="mainContent">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Announcements</h1>
                <p class="text-sm text-slate-500 mt-1">Manage and view company-wide updates</p>
            </div>
            <button onclick="openModal('addAnnouncementModal')" class="flex items-center gap-2 bg-primary hover-bg-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-all">
                <i class="fa-solid fa-plus"></i> Add Announcement
            </button>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm mb-6">
            <div class="flex border-b border-slate-100">
                <button class="tab-btn active px-6 py-4 text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors" onclick="switchTab('all', this)">
                    <i class="fa-solid fa-bullhorn mr-2"></i> All Announcements
                </button>
                <button class="tab-btn px-6 py-4 text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors" onclick="switchTab('scheduled', this)">
                    <i class="fa-regular fa-clock mr-2"></i> Scheduled
                </button>
                <button class="tab-btn px-6 py-4 text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors" onclick="switchTab('archived', this)">
                    <i class="fa-solid fa-box-archive mr-2"></i> Archived
                </button>
            </div>
        </div>

        <div id="all-card" class="content-card block">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-xs border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4">Subject</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Posted Date</th>
                                <th class="px-6 py-4">Priority</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center shrink-0 mt-1">
                                            <i class="fa-solid fa-bullhorn text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm">Office Holiday: Pongal 2026</div>
                                            <div class="text-xs text-slate-500 mt-0.5 line-clamp-1">Office will remain closed from Jan 14 to Jan 16...</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-600 px-2.5 py-1 rounded text-xs font-semibold border border-blue-100">Holiday</span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 font-medium">06 Feb 2026</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-rose-50 text-rose-600 px-2.5 py-1 rounded text-xs font-semibold border border-rose-100">High</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-full" onclick="openModal('editAnnouncementModal')"><i class="fa-regular fa-pen-to-square"></i></button>
                                        <button class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-full" onclick="openModal('deleteModal')"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0 mt-1">
                                            <i class="fa-solid fa-file-contract text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm">Updated HR Policy</div>
                                            <div class="text-xs text-slate-500 mt-0.5 line-clamp-1">Please review the updated leave policy document.</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded text-xs font-semibold border border-indigo-100">Policy</span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 font-medium">01 Feb 2026</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-600 px-2.5 py-1 rounded text-xs font-semibold border border-amber-100">Medium</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-full"><i class="fa-regular fa-pen-to-square"></i></button>
                                        <button class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-full"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="scheduled-card" class="content-card hidden">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-xs border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4">Subject</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Scheduled Date</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center shrink-0 mt-1">
                                            <i class="fa-solid fa-calendar-days text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm">Team Outing: March 2026</div>
                                            <div class="text-xs text-slate-500 mt-0.5 line-clamp-1">Details regarding the upcoming summer team outing...</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-purple-50 text-purple-600 px-2.5 py-1 rounded text-xs font-semibold border border-purple-100">Event</span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 font-medium">10 Mar 2026</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600 px-2.5 py-1 rounded text-xs font-semibold border border-slate-200">
                                        <i class="fa-regular fa-clock text-[10px] mr-1"></i> Scheduled
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-full"><i class="fa-regular fa-pen-to-square"></i></button>
                                        <button class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-full"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="archived-card" class="content-card hidden">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-xs border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4">Subject</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Expired Date</th>
                                <th class="px-6 py-4">Priority</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr class="hover:bg-slate-50 transition-colors opacity-75">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center shrink-0 mt-1">
                                            <i class="fa-solid fa-box-archive text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-700 text-sm">Year End Party 2025</div>
                                            <div class="text-xs text-slate-400 mt-0.5">Celebration details for Dec 31st...</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-500 px-2.5 py-1 rounded text-xs font-semibold border border-slate-200">Event</span>
                                </td>
                                <td class="px-6 py-4 text-slate-500 font-medium">01 Jan 2026</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-500 px-2.5 py-1 rounded text-xs font-semibold border border-slate-200">Low</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full"><i class="fa-regular fa-eye"></i></button>
                                        <button class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-full"><i class="fa-regular fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <div id="addAnnouncementModal" class="modal fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('addAnnouncementModal')"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl relative z-10 overflow-hidden transform transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                    <h3 class="font-bold text-slate-800 text-lg">New Announcement</h3>
                    <button onclick="closeModal('addAnnouncementModal')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <div class="p-6">
                    <form>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Subject Title</label>
                            <input type="text" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500" placeholder="e.g. Office Picnic">
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Category</label>
                                <select class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-orange-500">
                                    <option>General</option>
                                    <option>Holiday</option>
                                    <option>Policy</option>
                                    <option>Event</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Priority</label>
                                <select class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-orange-500">
                                    <option>Low</option>
                                    <option>Medium</option>
                                    <option>High</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Publish Date</label>
                            <input type="date" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Message</label>
                            <textarea rows="4" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-orange-500" placeholder="Write details..."></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closeModal('addAnnouncementModal')" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-primary hover-bg-primary text-white rounded-lg text-sm font-bold shadow-md">Post Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="editAnnouncementModal" class="modal fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('editAnnouncementModal')"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl relative z-10 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h3 class="font-bold text-slate-800">Edit Announcement</h3>
                </div>
                <div class="p-6">
                    <form>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Subject</label>
                            <input type="text" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm" value="Office Holiday: Pongal 2026">
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" onclick="closeModal('editAnnouncementModal')" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-bold text-slate-600">Cancel</button>
                            <button type="button" class="px-4 py-2 bg-primary hover-bg-primary text-white rounded-lg text-sm font-bold">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="modal fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('deleteModal')"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-sm rounded-xl shadow-2xl relative z-10 p-6 text-center">
                <div class="w-14 h-14 bg-rose-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500 text-xl"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-lg">Confirm Deletion</h3>
                <p class="text-sm text-slate-500 mt-2">Are you sure you want to remove this announcement? This action cannot be undone.</p>
                <div class="grid grid-cols-2 gap-3 mt-6">
                    <button onclick="closeModal('deleteModal')" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button class="px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-sm font-bold">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal Logic
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.classList.add('modal-active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.classList.remove('modal-active');
        }

        // Tab Switching Logic
        function switchTab(tabId, btn) {
            // Hide all content cards
            document.querySelectorAll('.content-card').forEach(card => {
                card.classList.add('hidden');
                card.classList.remove('block');
            });
            // Remove active style from buttons
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active', 'text-primary', 'border-b-2', 'border-primary', 'bg-orange-50');
            });

            // Show selected card
            document.getElementById(tabId + '-card').classList.remove('hidden');
            document.getElementById(tabId + '-card').classList.add('block');
            
            // Add active style to button
            btn.classList.add('active');
        }
    </script>
</body>
</html>