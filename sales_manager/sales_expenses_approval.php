<?php
ob_start();
// Include your existing layout files
if (file_exists('../sidebars.php')) include '../sidebars.php';
if (file_exists('../header.php')) include '../header.php';

// Mock Data for the Manager's Expenses View
// In real-time, fetch where department = 'Sales' and status is Pending/Approved
$team_expenses = [
    ['id' => 'EXP-101', 'exec_name' => 'Prem Karthick', 'name' => 'Client Meeting Lunch', 'date' => '24 Feb 2026', 'amount' => '₹3000', 'status' => 'Pending', 'receipt' => true],
    ['id' => 'EXP-102', 'exec_name' => 'Kavya Aruldas', 'name' => 'Travel to Chennai', 'date' => '23 Feb 2026', 'amount' => '₹4500', 'status' => 'Pending', 'receipt' => true],
    ['id' => 'EXP-103', 'exec_name' => 'Prem Karthick', 'name' => 'Office Supplies', 'date' => '20 Feb 2026', 'amount' => '₹1200', 'status' => 'Approved', 'receipt' => false],
    ['id' => 'EXP-104', 'exec_name' => 'Varshini M', 'name' => 'Hotel Accommodation', 'date' => '18 Feb 2026', 'amount' => '₹6000', 'status' => 'Forwarded', 'receipt' => true],
    ['id' => 'EXP-105', 'exec_name' => 'Kavya Aruldas', 'name' => 'Team Snacks', 'date' => '15 Feb 2026', 'amount' => '₹800', 'status' => 'Rejected', 'receipt' => false],
    ['id' => 'EXP-106', 'exec_name' => 'Prem Karthick', 'name' => 'Cab Fare', 'date' => '12 Feb 2026', 'amount' => '₹2500', 'status' => 'Approved', 'receipt' => true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Approvals | Sales Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8fafc; 
        }
        
        .main-content { 
            margin-left: 95px; 
            padding: 30px; 
            width: calc(100% - 95px); 
            box-sizing: border-box; 
        }
        @media (max-width: 992px) { 
            .main-content { margin-left: 0; width: 100%; padding-top: 80px; } 
        }

        .custom-checkbox {
            appearance: none;
            background-color: #f1f5f9;
            margin: 0;
            font: inherit;
            color: currentColor;
            width: 1.15em;
            height: 1.15em;
            border: 1px solid #cbd5e1;
            border-radius: 0.25em;
            display: grid;
            place-content: center;
            cursor: pointer;
        }
        .custom-checkbox::before {
            content: "";
            width: 0.65em;
            height: 0.65em;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em white;
            background-color: transform;
            transform-origin: center;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }
        .custom-checkbox:checked {
            background-color: #1b5a5a;
            border-color: #1b5a5a;
        }
        .custom-checkbox:checked::before {
            transform: scale(1);
        }

        .table-row-hover:hover { background-color: #f8fafc; }
        
        select:focus, input:focus, textarea:focus { outline: none; border-color: #1b5a5a; box-shadow: 0 0 0 1px #1b5a5a20; }
    </style>
</head>
<body class="text-slate-800">

    <div class="main-content">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Team Expenses Approval</h1>
                <nav class="flex text-sm text-gray-500 mt-1 gap-2 items-center">
                    <i data-lucide="home" class="w-3 h-3"></i>
                    <span>></span>
                    <span>Sales</span>
                    <span>></span>
                    <span class="text-slate-800 font-medium">Expense Approvals</span>
                </nav>
            </div>
            
            <div class="flex gap-3 relative">
                <button onclick="openForwardModal()" class="px-5 py-2 bg-[#1b5a5a] text-white rounded-lg text-sm font-semibold shadow-sm flex items-center gap-2 hover:bg-[#134040] transition-colors disabled:opacity-50 disabled:cursor-not-allowed" id="forwardBtn" disabled>
                    <i data-lucide="send" class="w-4 h-4"></i> Forward to Accounts
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <div class="p-5 border-b border-gray-100 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <h2 class="font-bold text-lg text-slate-800">Pending & Approved Expenses</h2>
                
                <div class="flex flex-wrap gap-3">
                    <div class="relative">
                        <select class="pl-4 pr-8 py-2 border border-gray-200 rounded-lg text-sm text-gray-800 font-medium bg-white appearance-none cursor-pointer w-[160px] shadow-sm">
                            <option value="all">All Executives</option>
                            <option value="prem">Prem Karthick</option>
                            <option value="kavya">Kavya Aruldas</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#1b5a5a] pointer-events-none font-bold"></i>
                    </div>

                    <div class="relative">
                        <select class="pl-4 pr-8 py-2 border border-gray-200 rounded-lg text-sm text-gray-800 font-medium bg-white appearance-none cursor-pointer w-[140px] shadow-sm">
                            <option value="all">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Forwarded">Forwarded</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#1b5a5a] pointer-events-none font-bold"></i>
                    </div>
                </div>
            </div>

            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <span>Row Per Page</span>
                    <select class="border border-gray-200 rounded px-2 py-1 bg-white">
                        <option>10</option>
                    </select>
                    <span>Entries</span>
                </div>
                <div class="relative w-64">
                    <input type="text" placeholder="Search by ID or Name" class="w-full pl-10 pr-4 py-1.5 border border-gray-200 rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:border-[#1b5a5a]">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-sm text-gray-600 font-semibold">
                            <th class="p-4 w-12 text-center">
                                <input type="checkbox" class="custom-checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th class="p-4">EXP ID</th>
                            <th class="p-4">Executive Name</th>
                            <th class="p-4">Expense Details</th>
                            <th class="p-4">Date</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_expenses as $expense): ?>
                        <tr class="border-b border-gray-50 text-sm text-gray-600 table-row-hover transition-colors">
                            <td class="p-4 text-center">
                                <?php if($expense['status'] === 'Approved'): ?>
                                    <input type="checkbox" class="custom-checkbox row-checkbox" value="<?php echo $expense['id']; ?>" onchange="checkSelections()">
                                <?php else: ?>
                                    <input type="checkbox" class="custom-checkbox" disabled style="opacity: 0.4; cursor: not-allowed;">
                                <?php endif; ?>
                            </td>
                            <td class="p-4 font-semibold text-slate-800"><?php echo htmlspecialchars($expense['id']); ?></td>
                            <td class="p-4 font-medium text-slate-700">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-teal-100 text-[#1b5a5a] flex items-center justify-center font-bold text-xs">
                                        <?php echo strtoupper(substr($expense['exec_name'], 0, 1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($expense['exec_name']); ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="block text-slate-800"><?php echo htmlspecialchars($expense['name']); ?></span>
                                <?php if($expense['receipt']): ?>
                                    <span class="text-xs text-blue-500 flex items-center gap-1 mt-1 cursor-pointer hover:underline"><i data-lucide="paperclip" class="w-3 h-3"></i> View Receipt</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 mt-1 block">No receipt</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4"><?php echo htmlspecialchars($expense['date']); ?></td>
                            <td class="p-4 font-bold text-slate-900"><?php echo htmlspecialchars($expense['amount']); ?></td>
                            <td class="p-4 text-center">
                                <?php
                                    $bg = 'bg-yellow-50 text-yellow-600';
                                    if($expense['status'] == 'Approved') $bg = 'bg-green-50 text-green-600';
                                    if($expense['status'] == 'Forwarded') $bg = 'bg-blue-50 text-blue-600';
                                    if($expense['status'] == 'Rejected') $bg = 'bg-red-50 text-red-600';
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo $bg; ?>">
                                    <?php echo htmlspecialchars($expense['status']); ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if($expense['status'] === 'Pending'): ?>
                                        <button onclick="approveExpense('<?php echo $expense['id']; ?>')" class="p-1.5 bg-green-50 text-green-600 rounded-md hover:bg-green-500 hover:text-white transition-colors shadow-sm" title="Approve">
                                            <i data-lucide="check" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="openRejectModal('<?php echo $expense['id']; ?>')" class="p-1.5 bg-red-50 text-red-600 rounded-md hover:bg-red-500 hover:text-white transition-colors shadow-sm" title="Reject">
                                            <i data-lucide="x" class="w-4 h-4"></i>
                                        </button>
                                    <?php elseif($expense['status'] === 'Approved'): ?>
                                        <span class="text-xs text-green-600 font-medium flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Approved</span>
                                    <?php elseif($expense['status'] === 'Forwarded'): ?>
                                        <span class="text-xs text-blue-600 font-medium flex items-center gap-1"><i data-lucide="send" class="w-3 h-3"></i> Accounts</span>
                                    <?php else: ?>
                                        <span class="text-xs text-red-600 font-medium flex items-center gap-1"><i data-lucide="x-circle" class="w-3 h-3"></i> Rejected</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div id="forwardModal" class="fixed inset-0 bg-slate-900/40 z-50 hidden flex items-center justify-center backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="send" class="w-7 h-7"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Forward to Accounts?</h3>
                <p class="text-sm text-gray-500 mb-6" id="forwardText">Are you sure you want to forward 0 selected approved expenses to the accounts team?</p>
                
                <div class="flex justify-center gap-3">
                    <button type="button" onclick="closeModals()" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors shadow-sm">
                        Cancel
                    </button>
                    <button type="button" onclick="submitForward()" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors shadow-sm">
                        Yes, Forward
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="fixed inset-0 bg-slate-900/40 z-50 hidden flex items-center justify-center backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
            <div class="flex justify-between items-center p-6 pb-4">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2"><i data-lucide="alert-triangle" class="text-red-500 w-5 h-5"></i> Reject Expense</h3>
                <button type="button" onclick="closeModals()" class="text-gray-400 hover:bg-gray-100 p-1.5 rounded-full transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6 pt-2">
                <form id="rejectForm">
                    <input type="hidden" id="rejectExpId">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Reason for Rejection *</label>
                            <textarea rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-shadow resize-none" placeholder="Provide a reason to the executive..."></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors shadow-sm">
                            Cancel
                        </button>
                        <button type="button" onclick="submitReject()" class="px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors shadow-sm">
                            Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Checkbox Logic for Forwarding
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                if(!cb.disabled) {
                    cb.checked = source.checked;
                }
            });
            checkSelections();
        }

        function checkSelections() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            const btn = document.getElementById('forwardBtn');
            if(checkboxes.length > 0) {
                btn.removeAttribute('disabled');
            } else {
                btn.setAttribute('disabled', 'true');
            }
        }

        // Modals Logic
        function openForwardModal() {
            const selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
            document.getElementById('forwardText').innerText = `Are you sure you want to forward ${selectedCount} selected approved expenses to the accounts team?`;
            document.getElementById('forwardModal').classList.remove('hidden');
        }

        function openRejectModal(id) {
            document.getElementById('rejectExpId').value = id;
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function closeModals() {
            document.getElementById('forwardModal').classList.add('hidden');
            document.getElementById('rejectModal').classList.add('hidden');
        }

        // Action Logic (Mock functions - connect to backend later)
        function approveExpense(id) {
            if(confirm(`Are you sure you want to approve expense ${id}?`)) {
                alert(`${id} has been Approved.`);
                // In a real app, make an AJAX call to update DB, then reload page
                location.reload();
            }
        }

        function submitReject() {
            const id = document.getElementById('rejectExpId').value;
            alert(`${id} has been Rejected and executive will be notified.`);
            closeModals();
            // Reload page or update DOM
        }

        function submitForward() {
            const selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
            alert(`${selectedCount} expenses forwarded to Accounts Team successfully!`);
            closeModals();
            // Reload page to reflect Forwarded status
        }
    </script>
</body>
</html>