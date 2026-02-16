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
    <title>Employee Onboarding Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1b5a5a;
            --primary-light: #2d7a7a;
            --primary-bg: #f0fdfa;
        }

        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            transition: all 0.2s;
        }
        .btn-primary:hover { background-color: var(--primary-light); transform: translateY(-1px); }

        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .onboarding-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            display: flex; /* Ensure layout consistency when filtering */
        }
        
        /* Hidden class for filtering */
        .d-none { display: none !important; }

        .onboarding-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }

        /* Custom Scrollbar for the list */
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Form Input Focus */
        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1);
            outline: none;
        }
        
        /* Active Filter Button Style */
        .filter-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">

    <div class="container mx-auto py-10 px-4 max-w-7xl">
        
        <!-- Header Section -->
        <header class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Employee Onboarding</h2>
                <p class="text-gray-500 mt-1">Manage new hire workflows, IDs, and manager allocation.</p>
            </div>
            <div class="text-sm text-gray-500 bg-white px-4 py-2 rounded-lg shadow-sm border">
                <span id="currentDateDisplay"></span>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 uppercase">Total Onboarding</p>
                    <h3 class="text-3xl font-bold text-gray-800 mt-1" id="totalCount">0</h3>
                </div>
                <div class="h-12 w-12 rounded-full bg-[#e6fffa] flex items-center justify-center text-[#1b5a5a]">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 uppercase">In Progress</p>
                    <h3 class="text-3xl font-bold text-orange-500 mt-1" id="inProgressCount">0</h3>
                </div>
                <div class="h-12 w-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-500">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 uppercase">Completed</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-1" id="completedCount">0</h3>
                </div>
                <div class="h-12 w-12 rounded-full bg-green-50 flex items-center justify-center text-green-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- LEFT COLUMN: Add New Onboarding Form -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">
                    <div class="bg-[#1b5a5a] p-5 border-b border-teal-800">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas fa-user-plus"></i> Start Onboarding
                        </h3>
                        <p class="text-teal-100 text-xs mt-1">Allocate ID and Manager to new hire.</p>
                    </div>

                    <div class="p-6">
                        <form id="onboardingForm" class="space-y-5">
                            
                            <!-- Employee ID Input -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Employee ID</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-id-card"></i></span>
                                    <input type="text" id="candId" placeholder="e.g. EMP-2024-001" 
                                           class="input-field w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm" required>
                                </div>
                            </div>

                            <!-- Name Input -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Full Name</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-user"></i></span>
                                    <input type="text" id="candName" placeholder="e.g. Sarah Connor" 
                                           class="input-field w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm" required>
                                </div>
                            </div>

                            <!-- Department Select -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Department</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-building"></i></span>
                                    <select id="candDept" onchange="updateManagerOptions()" class="input-field w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm appearance-none" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <option value="Development Team">Development Team</option>
                                        <option value="Design & Creative">Design & Creative</option>
                                        <option value="Marketing & Growth">Marketing & Growth</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Human Resources">Human Resources</option>
                                    </select>
                                    <span class="absolute right-3 top-3 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                                </div>
                            </div>

                            <!-- Manager Select (Cascading) -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Reporting Manager</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-user-tie"></i></span>
                                    <select id="candManager" class="input-field w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm appearance-none" required disabled>
                                        <option value="" disabled selected>Select Dept First</option>
                                    </select>
                                    <span class="absolute right-3 top-3 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                                </div>
                            </div>

                            <!-- Role Input -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Job Role</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-briefcase"></i></span>
                                    <input type="text" id="candRole" placeholder="e.g. Senior Engineer" 
                                           class="input-field w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm" required>
                                </div>
                            </div>

                            <!-- Start Date -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Start Date</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" id="candDate" 
                                           class="input-field w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm" required>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn-primary w-full py-3 rounded-lg font-bold shadow-lg mt-4 flex items-center justify-center gap-2">
                                <i class="fas fa-paper-plane"></i> Initiate Onboarding
                            </button>

                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Onboarding Pipeline -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 min-h-[600px]">
                    <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-xl">
                        <h3 class="text-lg font-bold text-gray-800">Onboarding Pipeline</h3>
                        <div class="flex gap-2">
                            <button onclick="filterPipeline('All', this)" class="filter-btn active text-xs font-semibold text-white bg-teal-700 px-3 py-1.5 rounded-md border border-teal-700 transition-colors">All</button>
                            <button onclick="filterPipeline('Pending', this)" class="filter-btn text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-md border border-gray-200 transition-colors">Pending</button>
                            <button onclick="filterPipeline('In Progress', this)" class="filter-btn text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-md border border-gray-200 transition-colors">In Progress</button>
                            <button onclick="filterPipeline('Completed', this)" class="filter-btn text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-md border border-gray-200 transition-colors">Completed</button>
                        </div>
                    </div>

                    <div class="p-5 custom-scroll overflow-y-auto max-h-[700px]" id="onboardingList">
                        <!-- PHP Generated Initial Items -->
                        <?php
                        $onboardingList = [
                            [
                                "id" => "EMP-2023-001",
                                "name" => "Alexander Wright",
                                "role" => "Tech Lead",
                                "dept" => "Development Team",
                                "manager" => "Sarah Chen",
                                "date" => "2023-10-25",
                                "status" => "In Progress",
                                "img" => "https://i.pravatar.cc/150?u=alex"
                            ],
                            [
                                "id" => "EMP-2023-002",
                                "name" => "Sophia Bennett",
                                "role" => "Art Director",
                                "dept" => "Design & Creative",
                                "manager" => "Liam O'Shea",
                                "date" => "2023-10-28",
                                "status" => "Pending",
                                "img" => "https://i.pravatar.cc/150?u=sophia"
                            ],
                            [
                                "id" => "EMP-2023-003",
                                "name" => "Julian Thorne",
                                "role" => "Growth Manager",
                                "dept" => "Marketing & Growth",
                                "manager" => "Olivia Pope",
                                "date" => "2023-10-20",
                                "status" => "Completed",
                                "img" => "https://i.pravatar.cc/150?u=julian"
                            ]
                        ];

                        foreach ($onboardingList as $index => $item): 
                            $statusColor = $item['status'] == 'Completed' ? 'bg-green-100 text-green-700' : 
                                          ($item['status'] == 'In Progress' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600');
                        ?>
                        <div class="onboarding-card bg-white border border-gray-100 rounded-lg p-4 mb-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between" data-status="<?php echo $item['status']; ?>">
                            
                            <div class="flex items-center gap-4 w-full sm:w-auto">
                                <img src="<?php echo $item['img']; ?>" class="w-12 h-12 rounded-full object-cover border border-gray-200" alt="Avatar">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                        <?php echo $item['name']; ?> 
                                        <span class="text-[10px] font-normal text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded border"><?php echo $item['id']; ?></span>
                                    </h4>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        <span class="font-medium text-[#1b5a5a]"><?php echo $item['role']; ?></span> 
                                        &bull; <?php echo $item['dept']; ?>
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
                                        <i class="fas fa-user-tie"></i> Mgr: <?php echo $item['manager']; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                                <div class="text-right">
                                    <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Start Date</div>
                                    <div class="text-xs font-medium text-gray-700"><?php echo date("M d, Y", strtotime($item['date'])); ?></div>
                                </div>
                                
                                <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $statusColor; ?>">
                                    <?php echo $item['status']; ?>
                                </span>

                                <div class="relative group">
                                    <button class="text-gray-400 hover:text-[#1b5a5a] p-2"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="hidden group-hover:block absolute right-0 mt-0 w-32 bg-white border border-gray-100 rounded-lg shadow-xl z-10 overflow-hidden">
                                        <button onclick="updateStatus(this, 'In Progress')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-orange-50 hover:text-orange-700 border-b border-gray-100">
                                            <i class="fas fa-hourglass-half mr-2"></i> In Progress
                                        </button>
                                        <button onclick="updateStatus(this, 'Completed')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-teal-50 hover:text-teal-800">
                                            <i class="fas fa-check mr-2"></i> Complete
                                        </button>
                                        <button onclick="deleteCard(this)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 border-t border-gray-100">
                                            <i class="fas fa-trash mr-2"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Empty state message (hidden by default via JS if items exist) -->
                        <div id="emptyState" class="hidden text-center py-10">
                            <div class="text-gray-300 text-5xl mb-3"><i class="fas fa-clipboard-list"></i></div>
                            <p class="text-gray-500">No onboarding tasks currently.</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-5 right-5 bg-[#1b5a5a] text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
        <i class="fas fa-info-circle"></i>
        <span id="toastMsg" class="font-medium text-sm">Action Successful</span>
    </div>

    <script>
        // Set Current Date
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDateDisplay').textContent = new Date().toLocaleDateString('en-US', dateOptions);

        // ────────────────────────────────────────────────
        // Manager Data Lookup
        // ────────────────────────────────────────────────
        const departmentManagers = {
            "Development Team": ["Sarah Chen", "David Kim", "Marcus Vane"],
            "Sales": ["Richard Hendricks", "Gavin Belson", "Jian Yang"],
            "Design & Creative": ["Liam O'Shea", "Emma Wilson", "Noah Garcia"],
            "Marketing & Growth": ["Olivia Pope", "Riley Scott", "Lucas Meyer"],
            "Human Resources": ["Janet Levin", "Michael Scott"]
        };

        // Function to update Manager dropdown based on Department
        function updateManagerOptions() {
            const deptSelect = document.getElementById('candDept');
            const managerSelect = document.getElementById('candManager');
            const selectedDept = deptSelect.value;

            // Clear current options
            managerSelect.innerHTML = '<option value="" disabled selected>Select Manager</option>';

            if (selectedDept && departmentManagers[selectedDept]) {
                managerSelect.disabled = false;
                departmentManagers[selectedDept].forEach(manager => {
                    const option = document.createElement('option');
                    option.value = manager;
                    option.textContent = manager;
                    managerSelect.appendChild(option);
                });
            } else {
                managerSelect.innerHTML = '<option value="" disabled selected>Select Dept First</option>';
                managerSelect.disabled = true;
            }
        }

        // ────────────────────────────────────────────────
        // DOM Elements
        // ────────────────────────────────────────────────
        const form = document.getElementById('onboardingForm');
        const listContainer = document.getElementById('onboardingList');
        const emptyState = document.getElementById('emptyState');
        
        // Stats Elements
        const elTotal = document.getElementById('totalCount');
        const elProgress = document.getElementById('inProgressCount');
        const elCompleted = document.getElementById('completedCount');

        // Initial Count Update based on PHP rendered items
        updateStats();

        // ────────────────────────────────────────────────
        // Form Submission
        // ────────────────────────────────────────────────
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get Values
            const id = document.getElementById('candId').value;
            const name = document.getElementById('candName').value;
            const dept = document.getElementById('candDept').value;
            const manager = document.getElementById('candManager').value;
            const role = document.getElementById('candRole').value;
            const date = document.getElementById('candDate').value;
            
            // Generate Random Avatar Seed
            const seed = Math.random().toString(36).substring(7);
            const avatarUrl = `https://i.pravatar.cc/150?u=${seed}`;

            // Create HTML String
            const cardHTML = `
            <div class="onboarding-card bg-white border border-gray-100 rounded-lg p-4 mb-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between animate-fade-in" data-status="Pending">
                
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <img src="${avatarUrl}" class="w-12 h-12 rounded-full object-cover border border-gray-200" alt="Avatar">
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                            ${name} 
                            <span class="text-[10px] font-normal text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded border">${id}</span>
                        </h4>
                        <div class="text-xs text-gray-500 mt-0.5">
                            <span class="font-medium text-[#1b5a5a]">${role}</span> 
                            &bull; ${dept}
                        </div>
                        <div class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
                            <i class="fas fa-user-tie"></i> Mgr: ${manager}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                    <div class="text-right">
                        <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Start Date</div>
                        <div class="text-xs font-medium text-gray-700">${date}</div>
                    </div>
                    
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 status-badge">
                        Pending
                    </span>

                    <div class="relative group">
                        <button class="text-gray-400 hover:text-[#1b5a5a] p-2"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="hidden group-hover:block absolute right-0 mt-0 w-32 bg-white border border-gray-100 rounded-lg shadow-xl z-10 overflow-hidden">
                            <button onclick="updateStatus(this, 'In Progress')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-orange-50 hover:text-orange-700 border-b border-gray-100">
                                <i class="fas fa-hourglass-half mr-2"></i> In Progress
                            </button>
                            <button onclick="updateStatus(this, 'Completed')" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-teal-50 hover:text-teal-800">
                                <i class="fas fa-check mr-2"></i> Complete
                            </button>
                            <button onclick="deleteCard(this)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 border-t border-gray-100">
                                <i class="fas fa-trash mr-2"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            `;

            // Insert at the top
            listContainer.insertAdjacentHTML('afterbegin', cardHTML);

            // Reset Form
            form.reset();
            document.getElementById('candManager').disabled = true;
            document.getElementById('candManager').innerHTML = '<option value="" disabled selected>Select Dept First</option>';
            
            // Update UI & Reset Filter to 'All' so user sees the new item
            document.querySelector('.filter-btn.active').click(); 
            updateStats();
            showToast(`Onboarding started for ${name}`);
        });

        // ────────────────────────────────────────────────
        // Filter Logic
        // ────────────────────────────────────────────────
        function filterPipeline(status, btn) {
            const cards = document.querySelectorAll('.onboarding-card');
            
            // Update Button Styles
            document.querySelectorAll('.filter-btn').forEach(b => {
                b.classList.remove('active', 'bg-[#1b5a5a]', 'text-white', 'border-[#1b5a5a]');
                b.classList.add('text-gray-600', 'bg-gray-100', 'border-gray-200');
            });
            
            btn.classList.remove('text-gray-600', 'bg-gray-100', 'border-gray-200');
            btn.classList.add('active', 'bg-[#1b5a5a]', 'text-white', 'border-[#1b5a5a]');

            // Filter Items
            cards.forEach(card => {
                if (status === 'All') {
                    card.classList.remove('d-none');
                } else {
                    if (card.getAttribute('data-status') === status) {
                        card.classList.remove('d-none');
                    } else {
                        card.classList.add('d-none');
                    }
                }
            });
        }

        // ────────────────────────────────────────────────
        // Update Statistics Function
        // ────────────────────────────────────────────────
        function updateStats() {
            const cards = document.querySelectorAll('.onboarding-card');
            let total = cards.length;
            let completed = 0;
            let inProgress = 0;

            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                if(status === 'Completed') completed++;
                else if(status === 'In Progress') inProgress++;
            });

            // Animate Numbers
            elTotal.textContent = total;
            elProgress.textContent = inProgress;
            elCompleted.textContent = completed;

            // Toggle Empty State
            if(total === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }

        // Delete Card Function
        function deleteCard(btn) {
            if(confirm('Are you sure you want to remove this candidate?')) {
                const card = btn.closest('.onboarding-card');
                card.style.opacity = '0';
                card.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    card.remove();
                    updateStats();
                    showToast('Candidate removed');
                }, 300);
            }
        }

        // Update Status Function
        function updateStatus(btn, newStatus) {
            const card = btn.closest('.onboarding-card');
            const badge = card.querySelector('.status-badge');
            
            // Visual Update
            card.setAttribute('data-status', newStatus);
            
            // Badge Styling
            badge.className = 'px-3 py-1 rounded-full text-xs font-bold status-badge';
            if(newStatus === 'Completed') {
                badge.classList.add('bg-green-100', 'text-green-700');
                showToast('Onboarding completed successfully');
            } else if (newStatus === 'In Progress') {
                badge.classList.add('bg-orange-100', 'text-orange-700');
                showToast('Status updated to In Progress');
            }
            badge.textContent = newStatus;

            updateStats();
        }

        // Toast Notification System
        function showToast(message) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = message;
            
            toast.classList.remove('translate-y-20', 'opacity-0');
            
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }
    </script>
</body>
</html>