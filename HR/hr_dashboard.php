<?php 
include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Executive Dashboard | Premium UI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #0d9488;
            --primary-dark: #134e4a;
            --bg-main: #f8fafc;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f1f5f9; 
            color: #1e293b;
        }

        .dashboard-container {
            margin-left: 80px;
            min-height: 100vh;
            padding: 2rem;
        }
        
        @media (max-width: 1024px) {
            .dashboard-container { margin-left: 0; }
        }

        .glass-card { 
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            border: 1px solid #f1f5f9;
            padding: 1.5rem;
        }

        /* Specific styles for the Workforce Graph */
        .bar-container {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            height: 60px;
        }
        .bar {
            width: 8px;
            background: #134e4a;
            border-radius: 10px;
        }
        .bar-light {
            background: #e2e8f0;
        }

        .btn-approve { background: #134e4a; color: white; }
        .btn-decline { border: 1px solid #e2e8f0; color: #64748b; }

        /* Punch UI Specifics */
        .attendance-circle {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: conic-gradient(var(--primary) 70%, #f1f5f9 0);
            margin: 0 auto;
        }
        .attendance-circle::before {
            content: "";
            position: absolute;
            width: 130px;
            height: 130px;
            background: white;
            border-radius: 50%;
        }
    </style>
</head>
<body class="antialiased">

    <div class="dashboard-container">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">HR Executive</h1>
                <nav class="flex text-sm text-slate-400 mt-1 font-medium">
                    <i data-lucide="layout-grid" class="w-4 h-4 mr-2"></i>
                    <span>Intelligence</span>
                    <span class="mx-2">/</span>
                    <span class="text-teal-600">Overview</span>
                </nav>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <div class="lg:col-span-8 space-y-6">
                
                <div class="glass-card">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Employment Workforce</h3>
                            <p class="text-xs text-slate-400">Distribution across all sectors</p>
                        </div>
                        <button class="text-xs font-bold text-slate-400 border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-50 transition-colors">Download Report</button>
                    </div>
                    
                    <div class="bar-container mb-8">
                        <div class="bar" style="height: 40%"></div><div class="bar" style="height: 60%"></div><div class="bar" style="height: 30%"></div><div class="bar" style="height: 70%"></div><div class="bar" style="height: 50%"></div><div class="bar" style="height: 80%"></div><div class="bar" style="height: 40%"></div><div class="bar" style="height: 65%"></div><div class="bar" style="height: 45%"></div><div class="bar" style="height: 90%"></div><div class="bar" style="height: 35%"></div><div class="bar" style="height: 75%"></div><div class="bar" style="height: 55%"></div><div class="bar" style="height: 85%"></div><div class="bar" style="height: 40%"></div><div class="bar" style="height: 60%"></div><div class="bar" style="height: 30%"></div><div class="bar" style="height: 70%"></div><div class="bar" style="height: 50%"></div><div class="bar" style="height: 80%"></div><div class="bar" style="height: 40%"></div><div class="bar" style="height: 65%"></div><div class="bar" style="height: 45%"></div><div class="bar" style="height: 90%"></div><div class="bar" style="height: 35%"></div><div class="bar" style="height: 75%"></div><div class="bar" style="height: 55%"></div><div class="bar" style="height: 85%"></div><div class="bar" style="height: 40%"></div><div class="bar" style="height: 60%"></div>
                        <div class="bar bar-light" style="height: 40%"></div><div class="bar bar-light" style="height: 60%"></div><div class="bar bar-light" style="height: 30%"></div><div class="bar bar-light" style="height: 70%"></div><div class="bar bar-light" style="height: 50%"></div><div class="bar bar-light" style="height: 80%"></div><div class="bar bar-light" style="height: 40%"></div><div class="bar bar-light" style="height: 65%"></div><div class="bar bar-light" style="height: 45%"></div><div class="bar bar-light" style="height: 90%"></div><div class="bar bar-light" style="height: 35%"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-2xl font-bold text-slate-800">1,054</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-800"></span> Full-time
                            </p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-800">568</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span> Contract
                            </p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-800">80</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-100"></span> Probation
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="glass-card relative">
                        <div class="flex justify-between items-start">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center">
                                <i data-lucide="users" class="w-5 h-5 text-slate-600"></i>
                            </div>
                            <span class="text-xs font-bold text-emerald-500 bg-emerald-50 px-2 py-1 rounded-full">+18.5% ↑</span>
                        </div>
                        <div class="mt-4">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Total Employees</p>
                            <p class="text-3xl font-extrabold text-slate-800">1,848</p>
                        </div>
                    </div>
                    <div class="glass-card relative">
                        <div class="flex justify-between items-start">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center">
                                <i data-lucide="user-plus" class="w-5 h-5 text-indigo-600"></i>
                            </div>
                            <span class="text-xs font-bold text-emerald-500 bg-emerald-50 px-2 py-1 rounded-full">+22.4% ↑</span>
                        </div>
                        <div class="mt-4">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">New Joinees</p>
                            <p class="text-3xl font-extrabold text-slate-800">1,248</p>
                        </div>
                    </div>
                </div>

                <div class="glass-card">
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Recruitment Funnel</h3>
                            <p class="text-xs text-slate-400">Conversion rate this month</p>
                        </div>
                        <select class="text-xs font-bold text-slate-500 bg-slate-50 border-none outline-none p-2 rounded-lg">
                            <option>Last 7 Days</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-6 mb-8">
                        <div class="bg-slate-50 p-6 rounded-2xl text-center">
                            <p class="text-2xl font-extrabold text-slate-800">487</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Applicants</p>
                        </div>
                        <div class="bg-slate-50 p-6 rounded-2xl text-center">
                            <p class="text-2xl font-extrabold text-slate-800">24</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Hired</p>
                        </div>
                        <div class="bg-slate-50 p-6 rounded-2xl text-center">
                            <p class="text-2xl font-extrabold text-slate-800">28d</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Avg. Time</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex h-3 w-full rounded-full overflow-hidden">
                            <div class="bg-slate-800" style="width: 30%"></div>
                            <div class="bg-teal-600" style="width: 25%"></div>
                            <div class="bg-pink-400" style="width: 15%"></div>
                            <div class="bg-emerald-400" style="width: 30%"></div>
                        </div>
                        <div class="flex justify-between">
                            <span class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase"><span class="w-2 h-2 rounded-full bg-slate-800"></span> Applications</span>
                            <span class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase"><span class="w-2 h-2 rounded-full bg-teal-600"></span> Screening</span>
                            <span class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase"><span class="w-2 h-2 rounded-full bg-pink-400"></span> Interview</span>
                            <span class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Final Hired</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 space-y-6">
                <div class="glass-card text-center">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Today's Attendance</p>
                    <h3 class="text-xl font-bold text-slate-800 mb-6"><?php echo date('h:i A, d M Y'); ?></h3>
                    
                    <div class="attendance-circle mb-6">
                        <div class="relative z-10">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Total Hours</p>
                            <p class="text-2xl font-extrabold text-slate-800">0:00:00</p>
                        </div>
                    </div>

                    <div class="flex justify-center mb-6">
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold border border-emerald-100">
                            <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                            Status: Not Punched In
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 mb-4 flex items-center justify-center gap-1">
                        <i data-lucide="fingerprint" class="w-3 h-3 text-orange-400"></i>
                        Punch In at --:--
                    </p>

                    <div class="grid grid-cols-2 gap-3">
                        <button class="w-full py-4 bg-[#f59e0b] hover:bg-[#d97706] text-white rounded-2xl font-bold flex items-center justify-center gap-2 transition-all shadow-lg shadow-orange-100">
                            <i data-lucide="coffee" class="w-5 h-5"></i>
                            Take Break
                        </button>
                        <button class="w-full py-4 bg-[#0d9488] hover:bg-[#0b7a6f] text-white rounded-2xl font-bold flex items-center justify-center gap-2 transition-all shadow-lg shadow-teal-100">
                            <i data-lucide="log-in" class="w-5 h-5"></i>
                            Punch In
                        </button>
                    </div>
                </div>
                <div class="glass-card">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800">Interviews</h3>
                        <span class="text-xs font-bold text-teal-600 underline cursor-pointer">Today</span>
                    </div>
                    
                    <div class="space-y-4">
                        <?php for($i=0; $i<2; $i++): ?>
                        <div class="border border-slate-50 rounded-2xl p-4 bg-slate-50/30">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">UI/UX Design Interview</h4>
                                    <p class="text-[10px] text-slate-400 mt-1 flex items-center">
                                        <i data-lucide="clock" class="w-3 h-3 mr-1"></i> 12:00 PM — 01:30 PM
                                    </p>
                                </div>
                                <div class="flex -space-x-2">
                                    <div class="w-6 h-6 rounded-full bg-slate-200 border-2 border-white overflow-hidden">
                                        <img src="https://i.pravatar.cc/100?u=1" alt="">
                                    </div>
                                    <div class="w-6 h-6 rounded-full bg-slate-800 border-2 border-white flex items-center justify-center text-[8px] text-white font-bold">
                                        +4
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <button class="py-2 text-xs font-bold border border-slate-200 rounded-xl bg-white">Details</button>
                                <button class="py-2 text-xs font-bold bg-slate-800 text-white rounded-xl">Join Meet</button>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <button class="w-full mt-6 text-xs font-bold text-slate-400 flex items-center justify-center gap-2">
                        View Full Schedule <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </button>
                </div>

                <div class="glass-card">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800">Approvals</h3>
                        <span class="text-[10px] font-bold text-slate-400 underline cursor-pointer uppercase">See all requests</span>
                    </div>

                    <div class="space-y-8">
                        <div>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="relative">
                                    <img src="https://i.pravatar.cc/100?u=9" class="w-10 h-10 rounded-full" alt="">
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800 leading-tight">Hendrita Merkel</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Project Manager</p>
                                </div>
                            </div>
                            <p class="text-xs italic text-slate-500 mb-4 bg-slate-50 p-3 rounded-xl">"Family annual trip"</p>
                            <div class="grid grid-cols-2 gap-3">
                                <button class="py-2.5 text-xs font-bold bg-slate-800 text-white rounded-xl">Approve</button>
                                <button class="py-2.5 text-xs font-bold border border-slate-100 text-slate-400 rounded-xl">Decline</button>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="relative">
                                    <img src="https://i.pravatar.cc/100?u=5" class="w-10 h-10 rounded-full" alt="">
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800 leading-tight">Michael Brown</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Senior Developer</p>
                                </div>
                            </div>
                            <p class="text-xs italic text-slate-500 mb-4 bg-slate-50 p-3 rounded-xl">"Medical checkup"</p>
                            <div class="grid grid-cols-2 gap-3">
                                <button class="py-2.5 text-xs font-bold bg-slate-800 text-white rounded-xl">Approve</button>
                                <button class="py-2.5 text-xs font-bold border border-slate-100 text-slate-400 rounded-xl">Decline</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>