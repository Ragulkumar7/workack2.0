<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | My Performance</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        
        /* Layout & Transitions */
        #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); transition: 0.3s; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* Progress Ring Animation */
        .progress-ring__circle {
            transition: stroke-dashoffset 0.5s ease-in-out;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        /* Hover Effects */
        .metric-card:hover { transform: translateY(-2px); transition: 0.2s; }

        /* Sidebar Variables */
        :root {
            --primary-sidebar-width: 95px;
            --secondary-sidebar-width: 220px; 
            --active-bg: #f4f4f5;
            --border-color: #e4e4e7;
            --text-muted: #71717a;
        }

        /* Primary Sidebar */
        .sidebar-primary {
            width: var(--primary-sidebar-width); 
            height: 100vh;
            border-right: 1px solid var(--border-color);
            background: #fff; 
            position: fixed; left: 0; top: 0; z-index: 1001;
            overflow-y: auto; overflow-x: hidden; scrollbar-width: none;
            display: flex; flex-direction: column;
        }
        .sidebar-primary::-webkit-scrollbar { display: none; }
        
        .nav-inner { display: flex; flex-direction: column; align-items: center; padding: 20px 0; flex-grow: 1; width: 100%; }
        
        .nav-item {
            width: 100%; padding: 12px 0; display: flex; flex-direction: column; align-items: center;
            cursor: pointer; text-decoration: none; color: var(--text-muted); transition: 0.2s; flex-shrink: 0; white-space: nowrap;
        }
        .nav-item:hover, .nav-item.active { color: #0d9488; background: #f0fdfa; border-right: 3px solid #0d9488; }
        .nav-item span { font-size: 10px; margin-top: 5px; font-weight: 500; text-align: center; padding: 0 4px; }

        /* Secondary Sidebar */
        .sidebar-secondary {
            width: var(--secondary-sidebar-width); height: 100vh; background: #fff;
            border-right: 1px solid var(--border-color); position: fixed;
            left: var(--primary-sidebar-width); top: 0;
            transform: translateX(-105%); transition: transform 0.3s ease; z-index: 1000; overflow-y: auto; scrollbar-width: none;
        }
        .sidebar-secondary.open { transform: translateX(0); }
        
        #subItemContainer { padding: 30px 15px; display: flex; flex-direction: column; }
        
        .sub-item {
            display: flex; align-items: center; padding: 10px; text-decoration: none; color: #3f3f46;
            border-radius: 8px; font-size: 13px; margin-bottom: 4px; transition: 0.2s; font-weight: 500;
        }
        .sub-item:hover { background: var(--active-bg); color: #000; }
        .sub-item .sub-icon { margin-right: 10px; width: 16px; height: 16px; color: #71717a; }
        
        .back-btn {
            display: flex; align-items: center; padding: 10px; margin-bottom: 20px;
            cursor: pointer; color: var(--text-muted); font-size: 13px; font-weight: 600;
            border-radius: 8px; transition: 0.2s; background: #f4f4f5;
        }
        .back-btn:hover { color: #000; background: #e4e4e7; }

        /* Footer */
        .user-footer { margin-top: auto; padding-bottom: 20px; width: 100%; display: flex; flex-direction: column; align-items: center; border-top: 1px solid var(--border-color); background: #fff; padding-top: 15px; }
        .logout-link { font-size: 11px; color: #ef4444; text-decoration: none; font-weight: 700; margin-top: 8px; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .logout-link:hover { opacity: 0.8; }
    </style>
</head>
<body>

    <aside class="sidebar-primary">
        <div class="nav-inner">
            <div style="padding-bottom: 20px; flex-shrink: 0;">
                <div style="width: 40px; height: 40px; background: #0d9488; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">H</div>
            </div>
            
            <a href="javascript:void(0)" class="nav-item " onclick="setActive(this)">
                <i data-lucide="layout-dashboard"></i>
                <span>Dashboard</span>
            </a>

            <a href="javascript:void(0)" class="nav-item " onclick="setActive(this)">
                <i data-lucide="message-circle"></i>
                <span>Team Chat</span>
            </a>

            <a href="javascript:void(0)" class="nav-item " onclick="openSubMenu('Attendance', [{'name':'My Attendance','icon':'user'},{'name':'Leave Request','icon':'calendar-plus'},{'name':'WFH Request','icon':'home'}], this)">
                <i data-lucide="calendar-check"></i>
                <span>Attendance</span>
            </a>

            <a href="javascript:void(0)" class="nav-item " onclick="setActive(this)">
                <i data-lucide="check-square"></i>
                <span>My Tasks</span>
            </a>

            <a href="javascript:void(0)" class="nav-item active" onclick="setActive(this)">
                <i data-lucide="trending-up"></i>
                <span>Performance</span>
            </a>

            <a href="javascript:void(0)" class="nav-item " onclick="setActive(this)">
                <i data-lucide="megaphone"></i>
                <span>News</span>
            </a>

            <div style="width: 40px; height: 1px; background: var(--border-color); margin: 10px 0; flex-shrink: 0;"></div>

            <a href="javascript:void(0)" class="nav-item " onclick="setActive(this)">
                <i data-lucide="file-text"></i>
                <span>Payslips</span>
            </a>

            <div style="width: 40px; height: 1px; background: var(--border-color); margin: 10px 0; flex-shrink: 0;"></div>

            <a href="javascript:void(0)" class="nav-item " onclick="setActive(this)">
                <i data-lucide="help-circle"></i>
                <span>Support</span>
            </a>

            <a href="javascript:void(0)" class="nav-item " onclick="setActive(this)">
                <i data-lucide="settings"></i>
                <span>Settings</span>
            </a>
        </div>

        <div class="user-footer">
            <div style="width: 42px; height: 42px; overflow: hidden; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                <img src="https://ui-avatars.com/api/?name=Sarah+Jenkins&background=0d9488&color=fff" alt="Profile" class="w-full h-full object-cover">
            </div>
            <div style="font-size: 11px; font-weight: 600; color: #18181b; text-align: center; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">sarah@company.com</div>
            <div style="font-size: 9px; color: var(--text-muted); text-align: center;">Employee</div>
            <a href="#" class="logout-link"><i data-lucide="log-out" style="width: 12px; height: 12px;"></i> Logout</a>
        </div>
    </aside>

    <aside class="sidebar-secondary" id="secondaryPanel"><div id="subItemContainer"></div></aside>

    <div id="mainContent">
        
        <div class="flex justify-between items-end mb-8 mt-4">
            <div class="flex items-center gap-4">
                <img src="https://ui-avatars.com/api/?name=Sarah+Jenkins&background=0d9488&color=fff&size=128" class="w-16 h-16 rounded-full border-4 border-white shadow-sm">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">My Performance</h1>
                    <div class="flex gap-2 text-sm text-slate-500">
                        <span>Sarah Jenkins</span> &bull; <span>Frontend Developer</span>
                    </div>
                </div>
            </div>
           
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8 flex flex-col md:flex-row items-center gap-8">
            <div class="relative w-40 h-40 flex items-center justify-center flex-shrink-0">
                <svg class="w-full h-full" viewBox="0 0 100 100">
                    <circle class="text-slate-100 stroke-current" stroke-width="8" cx="50" cy="50" r="40" fill="transparent"></circle>
                    <circle class="text-emerald-500 progress-ring__circle stroke-current" 
                            stroke-width="8" stroke-linecap="round" cx="50" cy="50" r="40" fill="transparent" 
                            stroke-dasharray="251.2" stroke-dashoffset="4.27"></circle>
                </svg>
                <div class="absolute text-center">
                    <span class="text-4xl font-bold text-slate-800">98.3</span>
                    <span class="block text-[10px] text-slate-400 font-bold tracking-wider mt-1">SCORE</span>
                </div>
            </div>
            
            <div class="flex-1 w-full">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="text-lg font-bold text-slate-800">Performance Grade</h3>
                    <span class="text-lg font-bold text-emerald-600">Excellent</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Projects</span> <span>40%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700">100%</div>
                        <div class="text-xs text-slate-500 mt-1">5/5 On Time</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Tasks</span> <span>30%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700">96%</div>
                        <div class="text-xs text-slate-500 mt-1">48 Completed</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Attendance</span> <span>20%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700">100%</div>
                        <div class="text-xs text-slate-500 mt-1">0 Days Leave</div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 metric-card cursor-default">
                        <div class="flex justify-between text-xs text-slate-400 font-bold uppercase mb-1">
                            <span>Manager</span> <span>10%</span>
                        </div>
                        <div class="text-xl font-bold text-slate-700">95%</div>
                        <div class="text-xs text-slate-500 mt-1">Soft Skills</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-layer-group text-blue-500 mr-2"></i> Project Timelines</h4>
                </div>
                <table class="w-full text-sm text-left">
                    <tbody class="divide-y divide-slate-50">
                        <tr>
                            <td class="p-4 font-medium text-slate-700">HRMS Portal v2</td>
                            <td class="p-4 text-slate-500 text-right">10 Feb 2026</td>
                            <td class="p-4 text-right"><span class="bg-emerald-50 text-emerald-700 px-2 py-1 rounded text-xs font-bold">On Time</span></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-medium text-slate-700">E-Commerce App</td>
                            <td class="p-4 text-slate-500 text-right">01 Feb 2026</td>
                            <td class="p-4 text-right"><span class="bg-rose-50 text-rose-700 px-2 py-1 rounded text-xs font-bold">Delayed</span></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-medium text-slate-700">Client API Integ.</td>
                            <td class="p-4 text-slate-500 text-right">15 Jan 2026</td>
                            <td class="p-4 text-right"><span class="bg-emerald-50 text-emerald-700 px-2 py-1 rounded text-xs font-bold">On Time</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h4 class="font-bold text-slate-700"><i class="fa-solid fa-list-check text-orange-500 mr-2"></i> Task Efficiency</h4>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-slate-600">Total Tasks Assigned</span>
                        <span class="font-bold text-slate-800">50</span>
                    </div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-slate-600">Completed On Time</span>
                        <span class="font-bold text-emerald-600">48</span>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-slate-600">Overdue / Pending</span>
                        <span class="font-bold text-rose-600">2</span>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100 mt-2">
                        <p class="text-xs text-slate-400 mb-2 font-bold uppercase">Weekly Trend</p>
                        <div class="flex gap-1 h-10 items-end">
                            <div class="w-1/5 bg-blue-200 rounded-t h-4" title="Mon"></div>
                            <div class="w-1/5 bg-blue-300 rounded-t h-6" title="Tue"></div>
                            <div class="w-1/5 bg-blue-400 rounded-t h-5" title="Wed"></div>
                            <div class="w-1/5 bg-blue-500 rounded-t h-8" title="Thu"></div>
                            <div class="w-1/5 bg-slate-200 rounded-t h-full flex items-center justify-center text-[10px] text-slate-500 font-bold" title="Average">AVG</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm mt-6 p-6">
            <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-comment-dots text-slate-400"></i> Manager's Feedback (10% Score)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Soft Skills Rating</label>
                    <div class="relative w-full h-2 bg-slate-200 rounded-lg overflow-hidden mt-3">
                         <div class="absolute top-0 left-0 h-full bg-slate-800" style="width: 95%;"></div>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs text-slate-400">Rated by Team Lead</span>
                        <span class="font-bold text-slate-800 text-lg">95/100</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Comments</label>
                    <div class="w-full bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-700 italic">
                        "Sarah consistently exceeds expectations. Her code quality is top-notch. Recommended for Senior Developer promotion next cycle."
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Initialize Icons
        lucide.createIcons();

        // Handle Primary Nav Click
        function setActive(element) {
            // Remove active class from all items
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            // Add to clicked
            element.classList.add('active');
            // Close submenu
            closeSubMenu();
        }

        // Handle Submenu Opening
        function openSubMenu(title, items, element) {
            // Set active state on parent
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            const panel = document.getElementById('secondaryPanel');
            const container = document.getElementById('subItemContainer');
            const main = document.getElementById('mainContent');

            // Open Panel
            panel.classList.add('open');
            if(main) main.classList.add('main-shifted');

            // Populate Content
            container.innerHTML = `
                <div class="back-btn" onclick="closeSubMenu()">
                    <i data-lucide="chevron-left" style="width: 16px; height: 16px; margin-right: 8px;"></i>
                    Back
                </div>
                <h3 style="font-size:14px; font-weight:700; margin-bottom:15px; padding-left:10px;">${title}</h3>
            `;

            items.forEach(sub => {
                container.innerHTML += `
                    <a href="javascript:void(0)" class="sub-item">
                        <i data-lucide="${sub.icon || 'circle'}" class="sub-icon"></i>
                        <span style="flex:1">${sub.name}</span>
                        <i data-lucide="chevron-right" style="width:12px; height:12px; color:#a1a1aa"></i>
                    </a>
                `;
            });

            // Re-init icons for new content
            lucide.createIcons();
        }

        // Close Submenu
        function closeSubMenu() {
            const panel = document.getElementById('secondaryPanel');
            const main = document.getElementById('mainContent');
            if(panel) panel.classList.remove('open');
            if(main) main.classList.remove('main-shifted');
        }
    </script>
</body>
</html>