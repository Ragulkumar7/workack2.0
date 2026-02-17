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
            --border: #e2e8f0;
            --text-muted: #64748b;
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
            color: white ;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        
        }
        .btn-primary:hover { 
            background-color: var(--primary-light); 
            transform: translateY(-1px); 
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            border: 1px solid var(--border);
            background: white;
            font-weight: 500;
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

        /* --- Modal CSS --- */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none; /* Hidden by default */
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-box {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
        .modal-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            padding: 0 20px;
            gap: 24px;
        }
        .tab-item {
            padding: 15px 0;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            font-size: 14px;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .tab-item.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f8fafc;
            border-radius: 0 0 12px 12px;
        }
        .img-upload-area { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .preview-circle {
            width: 70px; height: 70px; border-radius: 50%;
            background: #f1f5f9; border: 1px dashed #cbd5e1;
            display: flex; align-items: center; justify-content: center; color: #94a3b8;
        }
        .form-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px;
        }
        .form-group label {
            display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;
        }
        .form-group label span { color: #ef4444; }
        .form-group .form-control {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border);
            border-radius: 6px; font-size: 14px; transition: all 0.2s; background: #fff;
        }
        .form-group .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27,90,90,0.1); }
        .password-group { position: relative; }
        .password-toggle { position: absolute; right: 14px; top: 34px; color: #94a3b8; cursor: pointer; }
        .form-section-title { font-size: 16px; font-weight: 700; color: #1e293b; margin: 10px 0 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;}
        .perm-table { width: 100%; text-align: left; border-collapse: collapse; background: #fff;}
        .perm-table th, .perm-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .perm-table th { font-weight: 600; color: #475569; background: #f8fafc; }

        @media (max-width: 1024px) {
            main#content-wrapper { margin-left: 0; padding-top: 70px; }
            .form-grid { grid-template-columns: 1fr; }
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
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-600 bg-white px-5 py-2.5 rounded-lg shadow-sm border">
                    <span id="currentDateDisplay"></span>
                </div>
                <button onclick="openModal()" class="btn-primary px-5 py-2.5 rounded-lg font-bold shadow-sm border flex items-center gap-2">
                    <i class="fas fa-plus"></i> Add Employee
                </button>
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

        <div>
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
                                    <span class="font-medium text-teal-700"><?= $item['role'] ?></span> • <?= $item['dept'] ?>
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

    <div class="modal-overlay" id="employeeModal">
        <div class="modal-box">
            <div class="modal-header">
                <div style="display:flex; align-items:center;">
                    <h3 id="modalTitle">Add New Employee</h3>
                    <span id="modalIdDisplay"></span>
                </div>
                <i class="fas fa-times" style="cursor:pointer; color:#94a3b8; font-size: 18px;" onclick="closeModal()"></i>
            </div>
            
            <div class="modal-tabs">
                <div class="tab-item active" onclick="switchTab(this, 'tab-basic')">Basic Information</div>
                <div class="tab-item" onclick="switchTab(this, 'tab-bank')">Bank Details</div>
                <div class="tab-item" onclick="switchTab(this, 'tab-permissions')">Permissions</div>
            </div>
            
            <div class="modal-body custom-scroll">
                <form id="empFormDetailed">
                    
                    <div id="tab-basic" class="tab-content">
                        <div class="img-upload-area">
                            <div class="preview-circle" id="imgPreview">
                                <i class="fas fa-image" style="font-size: 24px;"></i>
                            </div>
                            <div>
                                <h5 style="margin:0 0 5px; font-size:14px; font-weight:600;">Upload Profile Image</h5>
                                <p style="margin:0 0 10px; font-size:12px; color:#94a3b8;">Image should be below 4 mb</p>
                                <div style="display:flex; gap:10px;">
                                    <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:12px;">Upload</button>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group"><label>First Name <span>*</span></label><input type="text" class="form-control" id="modFName" required></div>
                            <div class="form-group"><label>Last Name</label><input type="text" class="form-control" id="modLName"></div>
                            <div class="form-group"><label>Employee ID <span>*</span></label><input type="text" class="form-control" id="modEmpId" required></div>
                            <div class="form-group"><label>Joining Date <span>*</span></label><input type="date" class="form-control" id="modJoinDate" required></div>
                            <div class="form-group"><label>Username <span>*</span></label><input type="text" class="form-control" id="modUName"></div>
                            <div class="form-group"><label>Email <span>*</span></label><input type="email" class="form-control" id="modEmail"></div>
                            <div class="form-group password-group">
                                <label>Password <span>*</span></label>
                                <input type="password" class="form-control" id="modPwd">
                                <i class="fas fa-eye-slash password-toggle"></i>
                            </div>
                            <div class="form-group password-group">
                                <label>Confirm Password <span>*</span></label>
                                <input type="password" class="form-control">
                                <i class="fas fa-eye-slash password-toggle"></i>
                            </div>
                            <div class="form-group"><label>Phone Number <span>*</span></label><input type="text" class="form-control" id="modPhone"></div>
                            <div class="form-group"><label>Company <span>*</span></label><input type="text" class="form-control" id="modCompany"></div>
                            
                            <div class="form-group">
                                <label>Department</label>
                                <select class="form-control" id="modDept" onchange="updateModalManager()">
                                    <option value="">Select</option>
                                    <option value="Development Team">Development Team</option>
                                    <option value="Sales">Sales</option>
                                    <option value="Design & Creative">Design & Creative</option>
                                    <option value="Marketing & Growth">Marketing & Growth</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Designation (Job Role) <span>*</span></label>
                                <input type="text" class="form-control" id="modDesig" placeholder="e.g. Senior Developer">
                            </div>

                            <div class="form-group">
                                <label>Reporting Manager</label>
                                <select class="form-control" id="modManager" disabled>
                                    <option value="">Select Department First</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Annual Salary (₹) <span>*</span></label>
                                <input type="number" class="form-control" id="modSalary" placeholder="1200000" min="0">
                            </div>

                            <div class="form-group">
                                <label>Employment Type <span>*</span></label>
                                <select class="form-control" id="modEmpType">
                                    <option value="Permanent">Permanent</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Intern">Intern</option>
                                    <option value="Freelance">Freelance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="tab-bank" class="tab-content" style="display:none;">
                        <h4 class="form-section-title">Add Employee Bank Details</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>PAN Card No.</label><input type="text" class="form-control" placeholder="ABCDE1234F"></div>
                            <div class="form-group"><label>PF Account No.</label><input type="text" class="form-control" placeholder="PF12345678"></div>
                            <div class="form-group"><label>ESI Number</label><input type="text" class="form-control" placeholder="ESI987654"></div>
                        </div>

                        <h4 class="form-section-title">Bank Details</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Bank Name</label><input type="text" class="form-control" placeholder="e.g. HDFC Bank"></div>
                            <div class="form-group"><label>Bank Account No.</label><input type="text" class="form-control" placeholder="1234567890"></div>
                            <div class="form-group"><label>IFSC Code</label><input type="text" class="form-control" placeholder="HDFC0001234"></div>
                        </div>
                    </div>

                    <div id="tab-permissions" class="tab-content" style="display:none;">
                        <h4 class="form-section-title">Module Access Control</h4>
                        <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px;">Define which modules this employee can access and their privilege level.</p>
                        
                        <div style="border:1px solid var(--border); border-radius:12px; overflow:hidden;">
                            <table class="perm-table">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Read</th>
                                        <th>Write</th>
                                        <th>Create</th>
                                        <th>Delete</th>
                                        <th>Import</th>
                                        <th>Export</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $modules = ['Employee', 'Holidays', 'Leaves', 'Events', 'Chat', 'Jobs', 'Payroll', 'Reports', 'Settings'];
                                    foreach($modules as $mod): 
                                    ?>
                                    <tr>
                                        <td><?= $mod ?></td>
                                        <td><input type="checkbox" class="role-check" checked></td>
                                        <td><input type="checkbox" class="role-check"></td>
                                        <td><input type="checkbox" class="role-check"></td>
                                        <td><input type="checkbox" class="role-check"></td>
                                        <td><input type="checkbox" class="role-check"></td>
                                        <td><input type="checkbox" class="role-check"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" id="saveModalBtn">Save Employee</button>
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

        const departmentManagers = {
            "Development Team": ["Sarah Chen", "David Kim", "Marcus Vane"],
            "Sales": ["Richard Hendricks", "Gavin Belson", "Jian Yang"],
            "Design & Creative": ["Liam O'Shea", "Emma Wilson", "Noah Garcia"],
            "Marketing & Growth": ["Olivia Pope", "Riley Scott", "Lucas Meyer"],
            "Human Resources": ["Janet Levin", "Michael Scott"]
        };

        // Populate Manager dropdown for the Modal
        function updateModalManager() {
            const dept = document.getElementById('modDept').value;
            const mgrSelect = document.getElementById('modManager');
            mgrSelect.innerHTML = '<option value="" disabled selected>Select Manager</option>';
            mgrSelect.disabled = !dept;
            if (dept && departmentManagers[dept]) {
                departmentManagers[dept].forEach(mgr => {
                    mgrSelect.insertAdjacentHTML('beforeend', `<option value="${mgr}">${mgr}</option>`);
                });
            }
        }

        const list = document.getElementById('onboardingList');
        const empty = document.getElementById('emptyState');

        // Render Card HTML to List
        function appendEmployeeCard(id, name, dept, manager, role, salary, date) {
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
                            <span class="font-medium text-teal-700">${role}</span> • ${dept || 'N/A'}
                        </div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                            <i class="fas fa-user-tie"></i> Mgr: ${manager || 'Pending Assignment'}
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
            
            // Reset filter to show new item
            document.querySelector('.filter-btn.active').click();
            updateStats();
        }

        // ---------------- MODAL LOGIC ----------------
        function openModal() {
            document.getElementById('employeeModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('employeeModal').style.display = 'none';
        }

        function switchTab(btnElement, tabId) {
            // Remove active classes
            document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            
            // Add active classes
            btnElement.classList.add('active');
            document.getElementById(tabId).style.display = 'block';
        }

        // Modal Save Logic
        document.getElementById('saveModalBtn').addEventListener('click', (e) => {
            e.preventDefault();
            
            const fName = document.getElementById('modFName').value.trim();
            const lName = document.getElementById('modLName').value.trim();
            const id = document.getElementById('modEmpId').value.trim();
            const date = document.getElementById('modJoinDate').value;
            const role = document.getElementById('modDesig').value.trim();
            const dept = document.getElementById('modDept').value;
            const manager = document.getElementById('modManager').value;
            const salary = document.getElementById('modSalary').value;

            // Simple basic validation
            if(!fName || !id || !date || !role) {
                showToast("Please fill all required (*) fields in basic info", "error");
                switchTab(document.querySelector('.tab-item'), 'tab-basic'); // Ensure basic tab is showing
                return;
            }

            const fullName = lName ? `${fName} ${lName}` : fName;

            appendEmployeeCard(id, fullName, dept, manager, role, salary, date);
            
            closeModal();
            document.getElementById('empFormDetailed').reset();
            document.getElementById('modManager').disabled = true;
            document.getElementById('modManager').innerHTML = '<option value="">Select Department First</option>';
            
            showToast(`Employee ${fullName} successfully added`);
        });

        // ---------------- UTILITIES ----------------
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

            document.getElementById('totalCount').textContent = total;
            document.getElementById('inProgressCount').textContent = progress;
            document.getElementById('completedCount').textContent = completed;

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

        // Initialize view
        updateStats();
    </script>
</body>
</html>