<?php 
// 1. SESSION & INCLUDES
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// include '../sidebars.php'; // Uncomment in your actual environment
// include '../header.php';   // Uncomment in your actual environment

// 2. MOCK DATA (Initial Teams)
$departments = [
    [
        "name" => "Development Team",
        "lead" => "Alexander Wright",
        "role" => "Senior Manager",
        "members" => ["Sarah Chen", "Marcus Vane", "Elena Rodriguez", "David Kim"]
    ],
    [
        "name" => "Design & Creative",
        "lead" => "Sophia Bennett",
        "role" => "Creative Director",
        "members" => ["Liam O'Shea", "Emma Wilson"]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Overview | Workack</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1b5a5a',
                        primaryHover: '#144343',
                        bgLight: '#f8fafc',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        body { background-color: #f1f5f9; color: #1e293b; }

        /* Sidebar Layout Fix */
        #mainContent {
            margin-left: 95px; width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 32px; min-height: 100vh;
        }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* Card Hover */
        .team-card { transition: all 0.3s ease; }
        .team-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }

        /* Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        /* Modal Animation */
        .modal { transition: opacity 0.25s ease; }
        .modal-content { transition: transform 0.25s ease; }
        
        /* Checkbox Style */
        .checkbox-wrapper input:checked + div { background-color: #f0fdfa; border-color: #1b5a5a; color: #1b5a5a; }
        
        /* Toast */
        #toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 12px; position: fixed; z-index: 1000; left: 50%; bottom: 30px; transform: translateX(-50%); opacity: 0; transition: opacity 0.5s, bottom 0.5s; }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        #toast.success { background-color: #1b5a5a; }
    </style>
</head>
<body class="bg-slate-100">

    <?php include('../sidebars.php'); ?>
    <?php include('../header.php'); ?>

    <main id="mainContent">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Manager Overview</h1>
                <p class="text-sm text-gray-500 mt-1">Manage team structures and resource allocation.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="toggleFilter()" class="bg-white border border-gray-200 px-5 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm hover:shadow-md transition text-slate-600">
                    <i class="fa-solid fa-filter text-primary"></i> Filter
                </button>
                <button onclick="openModal('addTeamModal')" class="bg-primary hover:bg-primaryHover text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg shadow-teal-900/10 transition transform active:scale-95">
                    <i class="fa-solid fa-plus"></i> Add Team
                </button>
            </div>
        </div>

        <div id="filterBar" class="hidden mb-6 bg-white p-4 rounded-xl border border-gray-200 shadow-sm animate-fade-in-down">
            <input type="text" id="searchInput" onkeyup="filterTeams()" placeholder="Search by Team Name or Lead..." class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
        </div>

        <div id="teamGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <?php foreach ($departments as $data): ?>
                <div class="team-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover-card flex flex-col">
                    <div class="bg-primary p-6 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-white text-xl backdrop-blur-sm">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg leading-tight team-name"><?php echo $data['name']; ?></h3>
                            <p class="text-teal-100/80 text-xs mt-1"><?php echo count($data['members']); ?> Members</p>
                        </div>
                    </div>

                    <div class="p-6 flex-grow flex flex-col">
                        <div class="mb-5">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Team Lead</label>
                            <div class="flex items-center mt-2 p-3 bg-slate-50 rounded-xl border border-gray-100 group cursor-pointer hover:border-primary/30 transition-colors">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($data['lead']); ?>&background=1b5a5a&color=fff" class="w-10 h-10 rounded-full border-2 border-white shadow-sm">
                                <div class="ml-3">
                                    <p class="font-bold text-slate-800 text-sm lead-name"><?php echo $data['lead']; ?></p>
                                    <p class="text-xs text-primary font-medium"><?php echo $data['role']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex-grow">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Members</label>
                            <div class="mt-2 space-y-2 max-h-40 overflow-y-auto custom-scrollbar pr-1">
                                <?php foreach ($data['members'] as $member): ?>
                                    <div class="flex items-center p-2 rounded-lg hover:bg-slate-50 transition-colors">
                                        <div class="w-2 h-2 rounded-full bg-emerald-400 mr-3"></div>
                                        <span class="text-slate-600 text-sm font-medium"><?php echo $member; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50/50 border-t border-gray-100 flex justify-between items-center">
                        <span class="text-xs font-medium text-gray-500">Total: <span class="text-primary font-bold"><?php echo count($data['members']) + 1; ?></span></span>
                        <button class="text-xs font-bold text-primary hover:underline">Manage Team</button>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </main>

    <div id="addTeamModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform scale-95 transition-transform duration-300 overflow-hidden" id="modalPanel">
            
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-lg text-slate-800">Create New Team</h3>
                <button onclick="closeModal('addTeamModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form id="addTeamForm" class="p-6 space-y-5" onsubmit="handleTeamSubmit(event)">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Team Name <span class="text-red-500">*</span></label>
                    <input type="text" id="teamName" required placeholder="e.g. QA & Testing" class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Select Team Lead <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select id="teamLead" required class="w-full pl-4 pr-10 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                            <option value="">Choose a Manager</option>
                            <option value="James Anderson">James Anderson</option>
                            <option value="Priya Sharma">Priya Sharma</option>
                            <option value="Robert Fox">Robert Fox</option>
                        </select>
                        <i class="fa-solid fa-user-tie absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Select Members</label>
                    <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-2 custom-scrollbar space-y-1">
                        <label class="checkbox-wrapper flex cursor-pointer">
                            <input type="checkbox" name="members" value="Alice Johnson" class="peer sr-only">
                            <div class="w-full p-2 rounded-md border border-transparent hover:bg-slate-50 peer-checked:bg-teal-50 peer-checked:text-primary transition flex items-center">
                                <div class="w-4 h-4 border border-gray-300 rounded mr-2 flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary">
                                    <i class="fa-solid fa-check text-white text-[10px] hidden peer-checked:block"></i>
                                </div>
                                <span class="text-sm">Alice Johnson</span>
                            </div>
                        </label>
                        <label class="checkbox-wrapper flex cursor-pointer">
                            <input type="checkbox" name="members" value="Bob Smith" class="peer sr-only">
                            <div class="w-full p-2 rounded-md border border-transparent hover:bg-slate-50 peer-checked:bg-teal-50 peer-checked:text-primary transition flex items-center">
                                <div class="w-4 h-4 border border-gray-300 rounded mr-2 flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary">
                                    <i class="fa-solid fa-check text-white text-[10px] hidden peer-checked:block"></i>
                                </div>
                                <span class="text-sm">Bob Smith</span>
                            </div>
                        </label>
                        <label class="checkbox-wrapper flex cursor-pointer">
                            <input type="checkbox" name="members" value="Charlie Davis" class="peer sr-only">
                            <div class="w-full p-2 rounded-md border border-transparent hover:bg-slate-50 peer-checked:bg-teal-50 peer-checked:text-primary transition flex items-center">
                                <div class="w-4 h-4 border border-gray-300 rounded mr-2 flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary">
                                    <i class="fa-solid fa-check text-white text-[10px] hidden peer-checked:block"></i>
                                </div>
                                <span class="text-sm">Charlie Davis</span>
                            </div>
                        </label>
                        <label class="checkbox-wrapper flex cursor-pointer">
                            <input type="checkbox" name="members" value="Diana Prince" class="peer sr-only">
                            <div class="w-full p-2 rounded-md border border-transparent hover:bg-slate-50 peer-checked:bg-teal-50 peer-checked:text-primary transition flex items-center">
                                <div class="w-4 h-4 border border-gray-300 rounded mr-2 flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary">
                                    <i class="fa-solid fa-check text-white text-[10px] hidden peer-checked:block"></i>
                                </div>
                                <span class="text-sm">Diana Prince</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-50">
                    <button type="button" onclick="closeModal('addTeamModal')" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button type="submit" class="bg-primary hover:bg-primaryHover text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-teal-900/20 transition-all transform active:scale-95">Create Team</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast"></div>

    <script>
        // --- Modal Logic ---
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

        // --- Filter Logic ---
        function toggleFilter() {
            const filterBar = document.getElementById('filterBar');
            filterBar.classList.toggle('hidden');
            if(!filterBar.classList.contains('hidden')) {
                document.getElementById('searchInput').focus();
            }
        }

        function filterTeams() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.team-card');

            cards.forEach(card => {
                const name = card.querySelector('.team-name').innerText.toLowerCase();
                const lead = card.querySelector('.lead-name').innerText.toLowerCase();
                
                if (name.includes(input) || lead.includes(input)) {
                    card.style.display = "flex";
                } else {
                    card.style.display = "none";
                }
            });
        }

        // --- Add Team Logic (DOM Update) ---
        function handleTeamSubmit(e) {
            e.preventDefault();

            // Get Values
            const teamName = document.getElementById('teamName').value;
            const teamLead = document.getElementById('teamLead').value;
            const checkboxes = document.querySelectorAll('input[name="members"]:checked');
            
            let members = [];
            checkboxes.forEach((cb) => members.push(cb.value));

            // Validate
            if (!teamName || !teamLead) {
                alert("Please fill in all required fields.");
                return;
            }

            // Create Member HTML
            let membersHtml = '';
            members.forEach(m => {
                membersHtml += `
                    <div class="flex items-center p-2 rounded-lg hover:bg-slate-50 transition-colors">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 mr-3"></div>
                        <span class="text-slate-600 text-sm font-medium">${m}</span>
                    </div>`;
            });

            // Create New Card HTML
            const newCardHtml = `
                <div class="team-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover-card flex flex-col animate-pulse">
                    <div class="bg-primary p-6 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-white text-xl backdrop-blur-sm">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg leading-tight team-name">${teamName}</h3>
                            <p class="text-teal-100/80 text-xs mt-1">${members.length} Members</p>
                        </div>
                    </div>
                    <div class="p-6 flex-grow flex flex-col">
                        <div class="mb-5">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Team Lead</label>
                            <div class="flex items-center mt-2 p-3 bg-slate-50 rounded-xl border border-gray-100 group cursor-pointer hover:border-primary/30 transition-colors">
                                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(teamLead)}&background=1b5a5a&color=fff" class="w-10 h-10 rounded-full border-2 border-white shadow-sm">
                                <div class="ml-3">
                                    <p class="font-bold text-slate-800 text-sm lead-name">${teamLead}</p>
                                    <p class="text-xs text-primary font-medium">New Lead</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex-grow">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Members</label>
                            <div class="mt-2 space-y-2 max-h-40 overflow-y-auto custom-scrollbar pr-1">
                                ${membersHtml}
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-slate-50/50 border-t border-gray-100 flex justify-between items-center">
                        <span class="text-xs font-medium text-gray-500">Total: <span class="text-primary font-bold">${members.length + 1}</span></span>
                        <button class="text-xs font-bold text-primary hover:underline">Manage Team</button>
                    </div>
                </div>
            `;

            // Append to Grid
            const grid = document.getElementById('teamGrid');
            grid.insertAdjacentHTML('afterbegin', newCardHtml); // Add to top

            // Remove Pulse Animation after 2s
            setTimeout(() => {
                grid.firstElementChild.classList.remove('animate-pulse');
            }, 2000);

            // Success & Reset
            showToast(`Team "${teamName}" created successfully!`, "success");
            closeModal('addTeamModal');
            document.getElementById('addTeamForm').reset();
        }

        // --- Toast ---
        function showToast(message, type) {
            const toast = document.getElementById("toast");
            toast.innerText = message;
            toast.className = "show " + type;
            setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('addTeamModal');
            if (event.target === modal) { closeModal('addTeamModal'); }
        }
    </script>
</body>
</html>