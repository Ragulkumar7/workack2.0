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
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f8fafc; 
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        /* FIXED OVERLAP: Added margin-left to prevent sidebar overlap */
        .dashboard-container {
            width: auto;
            margin-left: 80px; /* Adjust this value to match your sidebar width exactly */
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .dashboard-container {
                margin-left: 0; /* Stack on mobile if sidebar becomes a drawer */
            }
        }

        .glass-card { 
            @apply bg-white rounded-[24px] border border-slate-100 shadow-sm p-6 transition-all duration-300;
        }
        .glass-card:hover {
            @apply shadow-xl shadow-slate-200/50 transform -translate-y-1;
        }
        .status-pill {
            @apply px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest;
        }
    </style>
</head>
<body class="min-h-screen">

    <div class="dashboard-container p-6 lg:p-10">
        
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">HR Executive Dashboard</h1>
                <div class="flex items-center gap-2 text-slate-400 text-sm font-medium mt-1">
                    <i data-lucide="home" class="w-4 h-4"></i>
                    <span>Dashboard</span>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    <span class="text-slate-600">HR Executive</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4 bg-white p-2 px-3 rounded-3xl shadow-sm border border-slate-100">
                <div class="flex -space-x-3 pr-4 border-r border-slate-100">
                    <img class="w-9 h-9 rounded-full border-4 border-white shadow-sm" src="https://i.pravatar.cc/150?u=1">
                    <img class="w-9 h-9 rounded-full border-4 border-white shadow-sm" src="https://i.pravatar.cc/150?u=2">
                    <img class="w-9 h-9 rounded-full border-4 border-white shadow-sm" src="https://i.pravatar.cc/150?u=3">
                    <div class="w-9 h-9 rounded-full bg-slate-900 border-4 border-white flex items-center justify-center text-[10px] font-bold text-white shadow-sm">+12</div>
                </div>
                <div class="flex items-center gap-2 px-2 text-sm font-bold text-slate-600">
                    <i data-lucide="calendar" class="w-4 h-4 text-[#1e4d57]"></i> 02/10/2026 - 02/16/2026
                </div>
                <button class="bg-[#1e4d57] text-white px-5 py-2.5 rounded-2xl font-bold text-sm flex items-center gap-2 shadow-lg shadow-teal-900/20 hover:bg-[#153a42] transition-all">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add New
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 max-w-[1600px]">
            
            <div class="lg:col-span-8 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="glass-card md:col-span-2">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold flex items-center gap-2">
                                <span class="w-1 h-6 bg-[#1e4d57] rounded-full"></span> Employee Status & Type
                            </h2>
                            <button class="text-xs font-bold text-slate-400 border px-4 py-1.5 rounded-xl hover:bg-slate-50">View All</button>
                        </div>
                        <div class="flex gap-1 mb-8 overflow-hidden">
                            <?php 
                                for($i=0; $i<45; $i++) echo '<div class="h-12 w-1.5 rounded-full bg-[#1e4d57]"></div>'; 
                                for($i=0; $i<25; $i++) echo '<div class="h-12 w-1.5 rounded-full bg-slate-300"></div>';
                                for($i=0; $i<10; $i++) echo '<div class="h-12 w-1.5 rounded-full bg-slate-100"></div>';
                            ?>
                        </div>
                        <div class="grid grid-cols-3 gap-8">
                            <div>
                                <p class="text-2xl font-black">1,054</p>
                                <p class="text-[11px] font-bold text-slate-400 mt-1 border-l-2 border-[#1e4d57] pl-3 uppercase">Full-Time</p>
                            </div>
                            <div>
                                <p class="text-2xl font-black">568</p>
                                <p class="text-[11px] font-bold text-slate-400 mt-1 border-l-2 border-slate-300 pl-3 uppercase">Contract</p>
                            </div>
                            <div>
                                <p class="text-2xl font-black">80</p>
                                <p class="text-[11px] font-bold text-slate-400 mt-1 border-l-2 border-slate-100 pl-3 uppercase">Probation</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold">Overview Statistics</h2>
                            <span class="status-pill bg-slate-100 text-slate-500">Monthly</span>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-[#1e4d57] flex items-center justify-center"><i data-lucide="users" class="w-5 h-5"></i></div>
                                    <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Employees</p><p class="text-lg font-black">1,848</p></div>
                                </div>
                                <span class="text-emerald-600 font-bold text-[10px] bg-emerald-50 px-2 py-1 rounded-lg">+18% ↑</span>
                            </div>
                            <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center"><i data-lucide="user-plus" class="w-5 h-5"></i></div>
                                    <div><p class="text-[10px] font-bold text-slate-400 uppercase">New Joinees</p><p class="text-lg font-black">1,248</p></div>
                                </div>
                                <span class="text-emerald-600 font-bold text-[10px] bg-emerald-50 px-2 py-1 rounded-lg">+22% ↑</span>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold">Top Distribution</h2>
                            <i data-lucide="more-horizontal" class="text-slate-300 w-5 h-5"></i>
                        </div>
                        <div class="flex items-end justify-between h-36 gap-2">
                            <?php 
                            $skills = [['Sales', 70], ['Front End', 30], ['React', 60], ['UI/UX', 20]];
                            foreach($skills as $s): ?>
                            <div class="flex-1 flex flex-col items-center gap-3 group">
                                <div class="w-full bg-slate-50 rounded-xl relative overflow-hidden flex flex-col justify-end" style="height: 100px;">
                                    <div class="bg-teal-100 group-hover:bg-[#1e4d57] transition-all duration-500 w-full" style="height: <?php echo $s[1]; ?>%"></div>
                                </div>
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter"><?php echo $s[0]; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <div class="glass-card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-bold">Recruitment Statistics</h2>
                        <span class="status-pill bg-slate-100 text-slate-500">Weekly</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-6 text-center">
                        <div><p class="text-xl font-black">487</p><p class="text-[10px] font-bold text-slate-400 uppercase">Applicants</p></div>
                        <div><p class="text-xl font-black">24</p><p class="text-[10px] font-bold text-slate-400 uppercase">Hired</p></div>
                        <div><p class="text-xl font-black">28d</p><p class="text-[10px] font-bold text-slate-400 uppercase">Avg Time</p></div>
                    </div>
                    <div class="h-4 w-full bg-slate-100 rounded-full overflow-hidden flex mb-6">
                        <div class="bg-[#1e4d57]" style="width: 40%"></div>
                        <div class="bg-teal-700" style="width: 25%"></div>
                        <div class="bg-pink-500" style="width: 20%"></div>
                        <div class="bg-emerald-500" style="width: 15%"></div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase">
                            <div class="w-2 h-2 rounded-sm bg-[#1e4d57]"></div> 40% Applications
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase">
                            <div class="w-2 h-2 rounded-sm bg-teal-700"></div> 25% Screening
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase">
                            <div class="w-2 h-2 rounded-sm bg-pink-500"></div> 20% Interview
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase">
                            <div class="w-2 h-2 rounded-sm bg-emerald-500"></div> 15% Hired
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 space-y-6">
                
                <div class="glass-card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-bold">Upcoming Interview</h2>
                        <span class="status-pill bg-teal-50 text-[#1e4d57]">Today</span>
                    </div>
                    <div class="space-y-4">
                        <?php for($i=0; $i<2; $i++): ?>
                        <div class="p-5 rounded-[20px] bg-slate-50 border border-slate-100 hover:border-teal-100 transition-all">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-sm text-slate-900">UI/UX Design Interview</h3>
                                    <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tight">12:00 PM - 01:50 PM</p>
                                </div>
                                <div class="flex -space-x-2">
                                    <img class="w-6 h-6 rounded-full border-2 border-white" src="https://i.pravatar.cc/150?u=x<?php echo $i; ?>">
                                    <div class="w-6 h-6 rounded-full bg-slate-200 border-2 border-white flex items-center justify-center text-[7px] font-black">+9</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button class="py-2.5 bg-white border border-slate-200 text-[9px] font-black rounded-xl uppercase hover:bg-slate-100">Calendar</button>
                                <button class="py-2.5 bg-[#1e4d57] text-white text-[9px] font-black rounded-xl uppercase hover:bg-[#153a42] transition-all">Join Now</button>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button class="w-full mt-6 text-xs font-bold text-slate-400 flex items-center justify-center gap-2 hover:text-[#1e4d57]">
                        View All Interviews <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </button>
                </div>

                <div class="glass-card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-bold">Pending Approvals</h2>
                        <button class="text-xs font-bold text-[#1e4d57]">View All</button>
                    </div>
                    <div class="space-y-4">
                        <?php 
                        $approvals = [
                            ['name' => 'Hendrita Merkel', 'role' => 'Manager', 'reason' => 'Family trip'],
                            ['name' => 'Michael Brown', 'role' => 'Senior Dev', 'reason' => 'Medical appointment']
                        ];
                        foreach($approvals as $user): ?>
                        <div class="flex flex-col gap-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-50">
                            <div class="flex items-center gap-3">
                                <img class="w-10 h-10 rounded-full border-2 border-white shadow-sm" src="https://i.pravatar.cc/150?u=<?php echo urlencode($user['name']); ?>">
                                <div>
                                    <p class="font-bold text-sm text-slate-900 leading-tight"><?php echo $user['name']; ?></p>
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $user['role']; ?></p>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 italic line-clamp-1">"<?php echo $user['reason']; ?>"</p>
                            <div class="flex gap-2">
                                <button class="flex-1 py-2 bg-[#1e4d57] text-white text-[9px] font-extrabold rounded-lg uppercase">Approve</button>
                                <button class="flex-1 py-2 bg-white border border-slate-200 text-slate-400 text-[9px] font-extrabold rounded-lg uppercase">Decline</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
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