<?php
// 1. SESSION & MOCK DATA
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Mock Data for Initial Load
$requests = [
    ['id' => 'REQ-001', 'date' => '2026-02-10', 'period' => 'Jan 01 - Jan 31, 2026', 'priority' => 'High', 'status' => 'Pending', 'reply' => '-'],
    ['id' => 'REQ-002', 'date' => '2026-01-05', 'period' => 'Dec 01 - Dec 31, 2025', 'priority' => 'Medium', 'status' => 'Approved', 'reply' => 'Sent to your email.'],
    ['id' => 'REQ-003', 'date' => '2025-12-02', 'period' => 'Nov 01 - Nov 30, 2025', 'priority' => 'Low', 'status' => 'Rejected', 'reply' => 'Clarification needed on LOP.']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Payslip | SmartHR</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1b5a5a',
                        primaryHover: '#144343',
                        bgLight: '#f8fafc',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        #mainContent { 
            margin-left: 95px; width: calc(100% - 95px); transition: margin-left 0.3s ease, width 0.3s ease;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        
        .modal { transition: opacity 0.25s ease; }
        .modal-content { transition: transform 0.25s ease; }
        
        /* Badges */
        .badge { @apply px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide border; }
        .badge-High { @apply bg-red-50 text-red-600 border-red-100; }
        .badge-Medium { @apply bg-orange-50 text-orange-600 border-orange-100; }
        .badge-Low { @apply bg-green-50 text-green-600 border-green-100; }
        
        .status-Pending { @apply bg-yellow-50 text-yellow-700 border-yellow-200; }
        .status-Approved { @apply bg-teal-50 text-teal-700 border-teal-200; }
        .status-Rejected { @apply bg-gray-100 text-gray-600 border-gray-200; }

        /* Toast Notification */
        #toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 12px; position: fixed; z-index: 1000; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 14px; opacity: 0; transition: opacity 0.5s, bottom 0.5s; }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        #toast.success { background-color: #1b5a5a; }
        #toast.error { background-color: #ef4444; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="mainContent" class="p-8 min-h-screen">
        
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Payslip Requests</h1>
                <nav class="flex text-sm text-gray-500 mt-1 gap-2 items-center">
                </nav>
            </div>
            <button onclick="openModal('requestModal')" class="bg-primary hover:bg-primaryHover text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg shadow-teal-900/10 transition-all flex items-center gap-2 transform active:scale-95">
                <i class="fas fa-plus"></i> New Request
            </button>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-slate-700">Request History</h3>
                <div class="text-xs text-gray-500">Latest requests</div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="requestTable">
                    <thead>
                        <tr class="bg-slate-50 text-xs uppercase text-gray-500 font-semibold border-b border-gray-100">
                            <th class="px-6 py-4">Request ID</th>
                            <th class="px-6 py-4">Requested Date</th>
                            <th class="px-6 py-4">Payslip Period</th>
                            <th class="px-6 py-4">Priority</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Accounts Reply</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100" id="tableBody">
                        <?php foreach($requests as $req): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-4 font-semibold text-primary"><?php echo $req['id']; ?></td>
                            <td class="px-6 py-4 text-slate-600"><?php echo $req['date']; ?></td>
                            <td class="px-6 py-4 text-slate-600 font-medium"><?php echo $req['period']; ?></td>
                            <td class="px-6 py-4">
                                <span class="badge badge-<?php echo $req['priority']; ?>"><?php echo $req['priority']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="badge status-<?php echo $req['status']; ?>"><?php echo $req['status']; ?></span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs italic"><?php echo $req['reply']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="requestModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300 overflow-hidden" id="modalPanel">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-lg text-slate-800">Request Payslip</h3>
                <button onclick="closeModal('requestModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form id="payslipForm" class="p-6 space-y-5" onsubmit="handleRequest(event)">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">From Date <span class="text-red-500">*</span></label>
                        <input type="date" id="fromDate" required class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">To Date <span class="text-red-500">*</span></label>
                        <input type="date" id="toDate" required class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Priority <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select id="priority" required class="w-full pl-4 pr-10 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                            <option value="Low">Low - Routine Request</option>
                            <option value="Medium" selected>Medium - Standard</option>
                            <option value="High">High - Urgent (Loan/Visa)</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Note to Accounts</label>
                    <textarea id="note" rows="3" placeholder="Any specific details needed?" class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('requestModal')" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button type="submit" class="bg-primary hover:bg-primaryHover text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-teal-900/20 transition-all transform active:scale-95">Send Request</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast"></div>

    <script>
        // Modal Logic
        function openModal(id) { 
            const modal = document.getElementById(id);
            const panel = modal.querySelector('#modalPanel');
            modal.classList.remove('hidden');
            setTimeout(() => { panel.classList.remove('scale-95'); panel.classList.add('scale-100'); }, 10);
        }

        function closeModal(id) { 
            const modal = document.getElementById(id);
            const panel = modal.querySelector('#modalPanel');
            panel.classList.remove('scale-100'); panel.classList.add('scale-95');
            setTimeout(() => { modal.classList.add('hidden'); }, 200);
        }

        // --- Handle Form Submission (Add to Table + Toast) ---
        function handleRequest(e) {
            e.preventDefault();
            
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            const priority = document.getElementById('priority').value;
            const note = document.getElementById('note').value;

            // 1. Validation
            if (new Date(fromDate) > new Date(toDate)) {
                showToast("Error: 'To Date' cannot be earlier than 'From Date'", "error");
                return;
            }

            // 2. Add New Row to Table (UI Update)
            const tableBody = document.getElementById('tableBody');
            const newRow = document.createElement('tr');
            newRow.className = "hover:bg-slate-50/80 transition-colors animate-pulse"; // Add animation
            
            // Format Date Period
            const start = new Date(fromDate).toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
            const end = new Date(toDate).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            const requestDate = new Date().toISOString().split('T')[0];

            newRow.innerHTML = `
                <td class="px-6 py-4 font-semibold text-primary">REQ-NEW</td>
                <td class="px-6 py-4 text-slate-600">${requestDate}</td>
                <td class="px-6 py-4 text-slate-600 font-medium">${start} - ${end}</td>
                <td class="px-6 py-4"><span class="badge badge-${priority}">${priority}</span></td>
                <td class="px-6 py-4"><span class="badge status-Pending">Pending</span></td>
                <td class="px-6 py-4 text-slate-500 text-xs italic">-</td>
            `;

            // Insert as first row
            tableBody.insertBefore(newRow, tableBody.firstChild);

            // Remove animation after 2s
            setTimeout(() => newRow.classList.remove('animate-pulse'), 2000);

            // 3. Success Message & Close
            showToast("Success: Payslip request sent!", "success");
            closeModal('requestModal');
            document.getElementById('payslipForm').reset();
        }

        // --- Toast Notification Logic ---
        function showToast(message, type) {
            const toast = document.getElementById("toast");
            toast.innerText = message;
            toast.className = "show " + type;
            setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
        }

        // Close on Outside Click
        window.onclick = function(event) {
            const modal = document.getElementById('requestModal');
            if (event.target === modal) { closeModal('requestModal'); }
        }
    </script>
</body>
</html>