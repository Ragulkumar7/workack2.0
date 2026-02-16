<?php 
include '../sidebars.php'; 
include '../header.php';
// Uncomment in production
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Onboarding | Workack HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1b5a5a;
            --primary-light: #2d7a7a;
            --primary-bg: #f0fdfa;
        }

        body { 
            background-color: #f8fafc; 
            font-family: 'Inter', sans-serif; 
            margin: 0;
        }

        main#content-wrapper {
            margin-left: 95px;           /* matches primary sidebar width */
            padding-top: 80px;           /* space for header */
            padding-bottom: 40px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* When secondary sidebar opens */
        .sidebar-secondary.open ~ main#content-wrapper {
            margin-left: calc(95px + 220px);
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            transition: all 0.2s;
        }
        .btn-primary:hover { 
            background-color: var(--primary-light); 
            transform: translateY(-1px); 
        }

        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .onboarding-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .onboarding-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary);
        }

        .d-none { display: none !important; }

        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.12);
            outline: none;
        }
        
        .filter-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        @media (max-width: 1024px) {
            main#content-wrapper {
                margin-left: 0;
                padding-top: 70px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">

<main id="content-wrapper">

    <div class="max-w-[96%] mx-auto pt-3 pb-10 px-4 sm:px-6 lg:px-8">
        
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-5">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Employee Onboarding</h2>
                <p class="text-gray-600 mt-1.5">Manage new hires, assign IDs, managers, and salary packages.</p>
            </div>
            <div class="text-sm text-gray-600 bg-white px-5 py-2.5 rounded-lg shadow-sm border">
                <span id="currentDateDisplay"></span>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Onboarding</p>
                    <h3 class="text-3xl font-bold text-gray-900 mt-2" id="totalCount">0</h3>
                </div>
                <div class="h-14 w-14 rounded-full bg-teal-50 flex items-center justify-center text-teal-700">
                    <i class="fas fa-users text-2xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">In Progress</p>
                    <h3 class="text-3xl font-bold text-orange-600 mt-2" id="inProgressCount">0</h3>
                </div>
                <div class="h-14 w-14 rounded-full bg-orange-50 flex items-center justify-center text-orange-600">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Completed</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-2" id="completedCount">0</h3>
                </div>
                <div class="h-14 w-14 rounded-full bg-green-50 flex items-center justify-center text-green-600">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">
                    <div class="bg-[#1b5a5a] p-6 border-b border-teal-800">
                        <h3 class="text-lg font-bold text-white flex items-center gap-3">
                            <i class="fas fa-user-plus"></i> New Hire Onboarding
                        </h3>
                        <p class="text-teal-100 text-sm mt-1.5">Create profile, assign ID, manager & salary.</p>
                    </div>

                    <div class="p-6">
                        <form id="onboardingForm" class="space-y-5">
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Employee ID</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-3.5 text-gray-400"><i class="fas fa-id-card"></i></span>
                                    <input type="text" id="empId" placeholder="EMP-2026-001" 
                                           class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:border-teal-600" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Full Name</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-3.5 text-gray-400"><i class="fas fa-user"></i></span>
                                    <input type="text" id="empName" placeholder="e.g. Julian Thorne" 
                                           class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:border-teal-600" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Department</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-3.5 text-gray-400"><i class="fas fa-building"></i></span>
                                    <select id="empDept" onchange="updateManagerOptions()" 
                                            class="w-full pl-11 pr-10 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm appearance-none focus:border-teal-600" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <option value="Development Team">Development Team</option>
                                        <option value="Design & Creative">Design & Creative</option>
                                        <option value="Marketing & Growth">Marketing & Growth</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Human Resources">Human Resources</option>
                                    </select>
                                    <span class="absolute right-3.5 top-3.5 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Reporting Manager</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-3.5 text-gray-400"><i class="fas fa-user-tie"></i></span>
                                    <select id="empManager" class="w-full pl-11 pr-10 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm appearance-none focus:border-teal-600" required disabled>
                                        <option value="" disabled selected>Select Department First</option>
                                    </select>
                                    <span class="absolute right-3.5 top-3.5 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Job Role</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-3.5 text-gray-400"><i class="fas fa-briefcase"></i></span>
                                    <input type="text" id="empRole" placeholder="e.g. Growth Manager" 
                                           class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:border-teal-600" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Annual Salary (₹)</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-3.5 text-gray-400">₹</span>
                                    <input type="number" id="empSalary" placeholder="e.g. 1200000" 
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:border-teal-600" required min="0">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Start Date</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-3.5 text-gray-400"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" id="empStartDate" 
                                           class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-sm focus:border-teal-600" required>
                                </div>
                            </div>

                            <button type="submit" class="btn-primary w-full py-3.5 rounded-lg font-bold shadow-md mt-6 flex items-center justify-center gap-2">
                                <i class="fas fa-paper-plane"></i> Start Onboarding
                            </button>

                        </form>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 min-h-[700px]">
                    <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gray-50 rounded-t-xl">
                        <h3 class="text-xl font-bold text-gray-900">Onboarding Pipeline</h3>
                        <div class="flex flex-wrap gap-2">
                            <button onclick="filterPipeline('All', this)" class="filter-btn active px-4 py-2 text-sm font-semibold rounded-lg border transition-all">All</button>
                            <button onclick="filterPipeline('Pending', this)" class="filter-btn px-4 py-2 text-sm font-semibold rounded-lg border transition-all">Pending</button>
                            <button onclick="filterPipeline('In Progress', this)" class="filter-btn px-4 py-2 text-sm font-semibold rounded-lg border transition-all">In Progress</button>
                            <button onclick="filterPipeline('Completed', this)" class="filter-btn px-4 py-2 text-sm font-semibold rounded-lg border transition-all">Completed</button>
                        </div>
                    </div>

                    <div class="p-6 custom-scroll overflow-y-auto max-h-[800px]" id="onboardingList">
                        <?php
                        $onboardingList = [
                            [
                                "id" => "EMP-2023-001",
                                "name" => "Alexander Wright",
                                "role" => "Tech Lead",
                                "dept" => "Development Team",
                                "manager" => "Sarah Chen",
                                "salary" => "₹18,50,000",
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
                                "salary" => "₹14,20,000",
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
                                "salary" => "₹16,80,000",
                                "date" => "2023-10-20",
                                "status" => "Completed",
                                "img" => "https://i.pravatar.cc/150?u=julian"
                            ]
                        ];

                        foreach ($onboardingList as $item): 
                            $statusColor = $item['status'] == 'Completed' ? 'bg-green-100 text-green-700 border-green-200' : 
                                          ($item['status'] == 'In Progress' ? 'bg-orange-100 text-orange-700 border-orange-200' : 'bg-gray-100 text-gray-600 border-gray-200');
                        ?>
                        <div class="onboarding-card bg-white border border-gray-100 rounded-lg p-5 mb-5 flex flex-col sm:flex-row gap-5 items-start sm:items-center justify-between" data-status="<?= $item['status'] ?>">
                            
                            <div class="flex items-center gap-4 w-full sm:w-auto">
                                <img src="<?= $item['img'] ?>" class="w-14 h-14 rounded-full object-cover border-2 border-gray-200" alt="">
                                <div>
                                    <h4 class="font-bold text-gray-900 text-base flex items-center gap-2">
                                        <?= $item['name'] ?> 
                                        <span class="text-xs font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded border"><?= $item['id'] ?></span>
                                    </h4>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium text-teal-700"><?= $item['role'] ?></span> 
                                        • <?= $item['dept'] ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                                        <i class="fas fa-user-tie"></i> Mgr: <?= $item['manager'] ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 sm:gap-8 w-full sm:w-auto">
                                <div class="text-left sm:text-right">
                                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Start Date</div>
                                    <div class="text-sm font-medium text-gray-800"><?= date("M d, Y", strtotime($item['date'])) ?></div>
                                </div>
                                
                                <div class="text-left sm:text-right">
                                    <div class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Salary</div>
                                    <div class="text-sm font-bold text-gray-900"><?= $item['salary'] ?></div>
                                </div>

                                <span class="px-4 py-1.5 rounded-full text-xs font-bold <?= $statusColor ?>">
                                    <?= $item['status'] ?>
                                </span>

                                <div class="relative group">
                                    <button class="text-gray-500 hover:text-teal-700 p-2">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="hidden group-hover:block absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-xl z-20">
                                        <button onclick="updateStatus(this, 'In Progress')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-orange-50">
                                            <i class="fas fa-hourglass-half mr-2 text-orange-500"></i> In Progress
                                        </button>
                                        <button onclick="updateStatus(this, 'Completed')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50">
                                            <i class="fas fa-check mr-2 text-green-600"></i> Complete
                                        </button>
                                        <button onclick="deleteCard(this)" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 border-t">
                                            <i class="fas fa-trash mr-2"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div id="emptyState" class="hidden text-center py-16">
                            <div class="text-gray-300 text-6xl mb-4"><i class="fas fa-clipboard-list"></i></div>
                            <p class="text-gray-500 text-lg">No onboarding records found.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-6 right-6 bg-teal-800 text-white px-6 py-3.5 rounded-lg shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
        <i class="fas fa-check-circle text-lg"></i>
        <span id="toastMsg" class="font-medium">Action completed</span>
    </div>

    <script>
        // Current date
        document.getElementById('currentDateDisplay').textContent = new Date().toLocaleDateString('en-IN', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });

        // Department → Manager mapping
        const departmentManagers = {
            "Development Team": ["Sarah Chen", "David Kim", "Marcus Vane"],
            "Sales": ["Richard Hendricks", "Gavin Belson", "Jian Yang"],
            "Design & Creative": ["Liam O'Shea", "Emma Wilson", "Noah Garcia"],
            "Marketing & Growth": ["Olivia Pope", "Riley Scott", "Lucas Meyer"],
            "Human Resources": ["Janet Levin", "Michael Scott"]
        };

        function updateManagerOptions() {
            const dept = document.getElementById('empDept').value;
            const mgrSelect = document.getElementById('empManager');

            mgrSelect.innerHTML = '<option value="" disabled selected>Select Manager</option>';
            mgrSelect.disabled = !dept;

            if (dept && departmentManagers[dept]) {
                departmentManagers[dept].forEach(mgr => {
                    const opt = document.createElement('option');
                    opt.value = mgr;
                    opt.textContent = mgr;
                    mgrSelect.appendChild(opt);
                });
            }
        }

        const form = document.getElementById('onboardingForm');
        const list = document.getElementById('onboardingList');
        const empty = document.getElementById('emptyState');
        const totalEl = document.getElementById('totalCount');
        const progressEl = document.getElementById('inProgressCount');
        const completedEl = document.getElementById('completedCount');

        // Initial stats from loaded cards
        updateStats();

        form.addEventListener('submit', e => {
            e.preventDefault();

            const id      = document.getElementById('empId').value.trim();
            const name    = document.getElementById('empName').value.trim();
            const dept    = document.getElementById('empDept').value;
            const manager = document.getElementById('empManager').value;
            const role    = document.getElementById('empRole').value.trim();
            const salary  = document.getElementById('empSalary').value.trim();
            const date    = document.getElementById('empStartDate').value;

            if (!id || !name || !dept || !manager || !role || !salary || !date) {
                showToast("Please fill all required fields", "error");
                return;
            }

            const seed = Math.random().toString(36).substring(7);
            const avatar = `https://i.pravatar.cc/150?u=${seed}`;

            const salaryDisplay = salary ? `₹${Number(salary).toLocaleString('en-IN')}` : "—";

            const card = `
            <div class="onboarding-card bg-white border border-gray-100 rounded-lg p-5 mb-5 flex flex-col sm:flex-row gap-5 items-start sm:items-center justify-between animate-fade-in" data-status="Pending">
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <img src="${avatar}" class="w-14 h-14 rounded-full object-cover border-2 border-gray-200" alt="">
                    <div>
                        <h4 class="font-bold text-gray-900 text-base flex items-center gap-2">
                            ${name}
                            <span class="text-xs font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded border">${id}</span>
                        </h4>
                        <div class="text-sm text-gray-600 mt-1">
                            <span class="font-medium text-teal-700">${role}</span> • ${dept}
                        </div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                            <i class="fas fa-user-tie"></i> Mgr: ${manager}
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 sm:gap-8 w-full sm:w-auto">
                    <div class="text-left sm:text-right">
                        <div class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Start Date</div>
                        <div class="text-sm font-medium text-gray-800">${new Date(date).toLocaleDateString('en-IN', {month:'short', day:'numeric', year:'numeric'})}</div>
                    </div>
                    
                    <div class="text-left sm:text-right">
                        <div class="text-xs uppercase font-semibold text-gray-500 tracking-wide">Salary</div>
                        <div class="text-sm font-bold text-gray-900">${salaryDisplay}</div>
                    </div>

                    <span class="px-4 py-1.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200 status-badge">
                        Pending
                    </span>

                    <div class="relative group">
                        <button class="text-gray-500 hover:text-teal-700 p-2">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="hidden group-hover:block absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-xl z-20">
                            <button onclick="updateStatus(this, 'In Progress')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-orange-50">
                                <i class="fas fa-hourglass-half mr-2 text-orange-500"></i> In Progress
                            </button>
                            <button onclick="updateStatus(this, 'Completed')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-teal-50">
                                <i class="fas fa-check mr-2 text-green-600"></i> Complete
                            </button>
                            <button onclick="deleteCard(this)" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 border-t border-gray-200">
                                <i class="fas fa-trash mr-2"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

            list.insertAdjacentHTML('afterbegin', card);
            form.reset();
            document.getElementById('empManager').disabled = true;
            document.getElementById('empManager').innerHTML = '<option value="" disabled selected>Select Department First</option>';
            
            // Reset filter to show new item
            document.querySelector('.filter-btn.active').click();
            updateStats();
            showToast(`Onboarding initiated for ${name}`);
        });

        function filterPipeline(status, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => {
                b.classList.remove('active', 'bg-teal-700', 'text-white', 'border-teal-700');
                b.classList.add('text-gray-700', 'bg-white', 'border-gray-300');
            });
            btn.classList.add('active', 'bg-teal-700', 'text-white', 'border-teal-700');

            document.querySelectorAll('.onboarding-card').forEach(card => {
                card.classList.toggle('d-none', status !== 'All' && card.dataset.status !== status);
            });
        }

        function updateStats() {
            const cards = document.querySelectorAll('.onboarding-card');
            const total = cards.length;
            let progress = 0, completed = 0;

            cards.forEach(c => {
                const s = c.dataset.status;
                if (s === 'Completed') completed++;
                else if (s === 'In Progress') progress++;
            });

            totalEl.textContent = total;
            progressEl.textContent = progress;
            completedEl.textContent = completed;

            empty.classList.toggle('hidden', total > 0);
        }

        function deleteCard(btn) {
            if (!confirm('Remove this onboarding record?')) return;
            const card = btn.closest('.onboarding-card');
            card.style.opacity = '0';
            card.style.transform = 'translateX(30px)';
            setTimeout(() => {
                card.remove();
                updateStats();
                showToast('Record removed');
            }, 300);
        }

        function updateStatus(btn, status) {
            const card = btn.closest('.onboarding-card');
            card.dataset.status = status;

            const badge = card.querySelector('.status-badge');
            badge.textContent = status;

            badge.className = 'px-4 py-1.5 rounded-full text-xs font-bold border status-badge ';
            if (status === 'Completed') {
                badge.classList.add('bg-green-100', 'text-green-700', 'border-green-200');
                showToast('Onboarding marked as completed');
            } else if (status === 'In Progress') {
                badge.classList.add('bg-orange-100', 'text-orange-700', 'border-orange-200');
                showToast('Status updated to In Progress');
            }

            updateStats();
        }

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            toast.classList.remove('translate-y-24', 'opacity-0');
            toast.classList.add(type === 'error' ? 'bg-red-600' : 'bg-teal-800');
            setTimeout(() => toast.classList.add('translate-y-24', 'opacity-0'), 3400);
        }

        // Initial stats
        updateStats();
    </script>
</body>
</html>