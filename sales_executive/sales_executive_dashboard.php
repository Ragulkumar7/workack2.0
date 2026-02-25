<?php
// Mock Data - In a real app, this would come from your database
$kpis = [
    ['icon' => 'Δ', 'title' => 'Total No of Leads', 'value' => '6000', 'trend' => '-4.01%', 'trend_up' => false, 'color' => 'bg-orange-500'],
    ['icon' => '¤', 'title' => 'No of New Leads', 'value' => '120', 'trend' => '+20.01%', 'trend_up' => true, 'color' => 'bg-teal-700'],
    ['icon' => '📈', 'title' => 'No of Lost Leads', 'value' => '30', 'trend' => '+55%', 'trend_up' => true, 'color' => 'bg-red-500'],
    ['icon' => '👥', 'title' => 'No of Total Customers', 'value' => '9895', 'trend' => '+55%', 'trend_up' => true, 'color' => 'bg-purple-500']
];

$recent_leads = [
    ['company' => 'BrightWave', 'stage' => 'Contacted', 'stage_color' => 'bg-teal-700', 'date' => '14 Jan 2024', 'owner' => 'William Parsons'],
    ['company' => 'Stellar', 'stage' => 'Closed', 'stage_color' => 'bg-green-500', 'date' => '21 Jan 2024', 'owner' => 'Lucille Tomberlin'],
    ['company' => 'Quantum', 'stage' => 'Lost', 'stage_color' => 'bg-red-500', 'date' => '20 Feb 2024', 'owner' => 'Frederick Johnson'],
    ['company' => 'EcoVision', 'stage' => 'Not Contacted', 'stage_color' => 'bg-purple-500', 'date' => '15 Mar 2024', 'owner' => 'Sarah Henry'],
];

$company_leads = [
    ['name' => 'Pitch', 'value' => '$45,985', 'status' => 'Not Contacted', 'color' => 'bg-purple-500', 'icon' => 'bg-black'],
    ['name' => 'Initech', 'value' => '$21,145', 'status' => 'Closed', 'color' => 'bg-green-500', 'icon' => 'bg-purple-600'],
    ['name' => 'Umbrella Corp', 'value' => '$15,685', 'status' => 'Contacted', 'color' => 'bg-teal-800', 'icon' => 'bg-blue-400'],
    ['name' => 'Capital Partners', 'value' => '$12,105', 'status' => 'Contacted', 'color' => 'bg-teal-800', 'icon' => 'bg-orange-500'],
    ['name' => 'Massive Dynamic', 'value' => '$2,546', 'status' => 'Lost', 'color' => 'bg-red-500', 'icon' => 'bg-gray-800'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; }
        .card { background: white; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }
        
        /* Layout wrapper to prevent overlap with fixed sidebar and header */
        .dashboard-wrapper { margin-left: 90px; padding-top: 80px; }
        @media (max-width: 768px) { .dashboard-wrapper { margin-left: 0; } }
    </style>
</head>
<body class="text-gray-800">

    <?php include '../header.php'; ?>
    <?php include '../sidebars.php'; ?>

    <div class="dashboard-wrapper p-6">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Leads Dashboard</h1>
                <p class="text-sm text-gray-500">Dashboard > Leads Dashboard</p>
            </div>
            <div class="flex gap-3">
                <button class="px-4 py-2 bg-white border rounded shadow-sm text-sm">Export ⌄</button>
                <button class="px-4 py-2 bg-white border rounded shadow-sm text-sm">02/19/2026 - 02/25/2026</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <?php foreach ($kpis as $kpi): ?>
            <div class="card p-5">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-10 h-10 rounded-full text-white flex items-center justify-center <?= $kpi['color'] ?>">
                        <?= $kpi['icon'] ?>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500"><?= $kpi['title'] ?></p>
                        <p class="text-xl font-bold"><?= $kpi['value'] ?></p>
                    </div>
                </div>
                <div class="text-sm border-t pt-2 mt-2">
                    <span class="<?= $kpi['trend_up'] ? 'text-green-500' : 'text-red-500' ?>">
                        <?= $kpi['trend'] ?>
                    </span> 
                    <span class="text-gray-400">from last week</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card overflow-hidden">
                <div class="bg-teal-700 text-center p-6 text-white">
                    <div class="w-20 h-20 bg-teal-600 rounded-full mx-auto border-4 border-white flex items-center justify-center text-2xl font-bold relative">
                        SP
                        <div class="absolute bottom-0 right-0 w-4 h-4 bg-green-400 rounded-full border-2 border-white"></div>
                    </div>
                    <h2 class="text-xl font-bold mt-3">Stephen Peralt</h2>
                    <p class="text-sm text-teal-200">Senior Software Engineer</p>
                    <button class="mt-3 px-4 py-1 bg-teal-600/50 rounded-full text-xs font-semibold">Verified Account</button>
                </div>
                <div class="p-5">
                    <div class="flex gap-3 mb-4 items-center">
                        <div class="p-2 bg-gray-100 rounded text-teal-700">📞</div>
                        <div><p class="text-xs text-gray-400">PHONE</p><p class="text-sm font-bold">+1 234 567 890</p></div>
                    </div>
                    <div class="flex gap-3 mb-4 items-center border-b pb-4">
                        <div class="p-2 bg-gray-100 rounded text-teal-700">✉️</div>
                        <div><p class="text-xs text-gray-400">EMAIL</p><p class="text-sm font-bold">employee@gmail.com</p></div>
                    </div>
                    <div class="bg-green-50 p-3 rounded flex justify-between items-center text-sm font-bold text-green-900">
                        <span>📅 Joined</span>
                        <span>15 Jan 2024</span>
                    </div>
                </div>
            </div>

            <div class="card p-7 flex flex-col h-full">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="font-bold text-[18px] text-[#031d38]">Leave Details</h3>
                    <button class="px-3 py-1 bg-white border border-gray-100 rounded-md shadow-[0_1px_2px_rgba(0,0,0,0.03)] text-[13px] font-semibold text-gray-600">2026</button>
                </div>
                <div class="flex items-center justify-between flex-1">
                    <div class="flex flex-col gap-[22px]">
                        <div class="flex items-center">
                            <div class="w-[26px] h-[26px] rounded-full bg-[#edf3f2] flex items-center justify-center mr-4">
                                <div class="w-2 h-2 rounded-full bg-[#185c50]"></div>
                            </div>
                            <span class="font-bold text-[15px] text-[#031d38] w-7">3</span>
                            <span class="text-[14px] text-slate-500">On Time</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-[26px] h-[26px] rounded-full bg-[#e8fbee] flex items-center justify-center mr-4">
                                <div class="w-2 h-2 rounded-full bg-[#22c55e]"></div>
                            </div>
                            <span class="font-bold text-[15px] text-[#031d38] w-7">0</span>
                            <span class="text-[14px] text-slate-500">Late Attendance</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-[26px] h-[26px] rounded-full bg-[#fef3e9] flex items-center justify-center mr-4">
                                <div class="w-2 h-2 rounded-full bg-[#f97316]"></div>
                            </div>
                            <span class="font-bold text-[15px] text-[#031d38] w-7">0</span>
                            <span class="text-[14px] text-slate-500">Work From Home</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-[26px] h-[26px] rounded-full bg-[#fdebed] flex items-center justify-center mr-4">
                                <div class="w-2 h-2 rounded-full bg-[#ef4444]"></div>
                            </div>
                            <span class="font-bold text-[15px] text-[#031d38] w-7">0</span>
                            <span class="text-[14px] text-slate-500">Absent</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-[26px] h-[26px] rounded-full bg-[#fdf9e2] flex items-center justify-center mr-4">
                                <div class="w-2 h-2 rounded-full bg-[#eab308]"></div>
                            </div>
                            <span class="font-bold text-[15px] text-[#031d38] w-7">0</span>
                            <span class="text-[14px] text-slate-500">Sick Leave</span>
                        </div>
                    </div>
                    <div class="pr-5">
                        <div class="w-[136px] h-[136px] rounded-full border-[20px] border-[#185c50]"></div>
                    </div>
                </div>
            </div>

            <div class="card p-8 flex flex-col items-center justify-center h-full">
                <p class="text-sm text-gray-500 mb-1">Good Morning, admin</p>
                <h2 class="text-4xl font-extrabold text-[#1c2c42] mb-1 tracking-tight">04:16 PM</h2>
                <p class="text-sm text-gray-400 mb-6">23 Feb 2026</p>

                <div class="w-24 h-24 rounded-full bg-gradient-to-r from-blue-500 to-green-500 p-[3px] mb-8">
                    <div class="w-full h-full rounded-full border-2 border-white bg-[#225a58] flex items-center justify-center text-3xl font-normal text-white">
                        AD
                    </div>
                </div>

                <div class="w-full bg-[#225a58] text-white py-2.5 rounded-md font-semibold text-sm mb-4 text-center">
                    Production : 0.00 hrs
                </div>

                <div class="text-[#0ea5e9] text-emerald-500 text-sm font-medium mb-4 flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Not Punched In
                </div>

                <button class="w-full bg-[#225a58] hover:bg-[#1a4443] text-white py-3 rounded-md font-bold text-sm transition-colors mt-auto">
                    Punch In
                </button>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card p-5 lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Pipeline Stages</h3>
                    <button class="px-3 py-1 bg-gray-50 border rounded text-sm">2023 - 2024</button>
                </div>
                <div class="flex gap-4 mb-4 text-sm font-semibold">
                    <div><span class="inline-block w-3 h-3 bg-orange-500 rounded-full mr-1"></span> Contacted: 50000</div>
                    <div><span class="inline-block w-3 h-3 bg-teal-800 rounded-full mr-1"></span> Opportunity: 25985</div>
                    <div><span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-1"></span> Not Contacted: 12566</div>
                </div>
                <div id="pipelineChart" class="h-64"></div>
            </div>

            <div class="card p-5 h-full flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Recent Leads</h3>
                    <button class="px-3 py-1 bg-gray-50 border rounded text-sm">View All</button>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-sm">
                                <th class="p-3">Company Name</th>
                                <th class="p-3">Stage</th>
                                <th class="p-3">Created Date</th>
                                <th class="p-3">Lead Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_leads as $lead): ?>
                            <tr class="border-b text-sm">
                                <td class="p-3 font-semibold flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gray-200 rounded-full shrink-0"></div>
                                    <?= $lead['company'] ?>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-1 text-xs font-bold text-white rounded <?= $lead['stage_color'] ?>">
                                        <?= $lead['stage'] ?>
                                    </span>
                                </td>
                                <td class="p-3 text-gray-500"><?= $lead['date'] ?></td>
                                <td class="p-3 text-gray-500"><?= $lead['owner'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card p-5 lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Lost Leads</h3>
                    <button class="px-3 py-1 bg-white border rounded text-sm text-gray-700 flex items-center gap-1 shadow-sm">
                        Sales Pipeline 
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                </div>
                <div id="lostLeadsChart" class="h-64"></div>
            </div>

            <div class="card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Leads By Companies</h3>
                    <button class="px-3 py-1 bg-white border rounded text-sm text-gray-700 flex items-center gap-1 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        This Week
                    </button>
                </div>
                <div class="flex flex-col gap-3">
                    <?php foreach ($company_leads as $c_lead): ?>
                    <?php
                        // Map Status to Background Color
                        $status_bg = 'bg-gray-500';
                        if ($c_lead['status'] == 'Not Contacted') $status_bg = 'bg-[#A855F7]'; // Purple
                        elseif ($c_lead['status'] == 'Closed') $status_bg = 'bg-[#10B981]'; // Green
                        elseif ($c_lead['status'] == 'Contacted') $status_bg = 'bg-[#115E59]'; // Dark Teal
                        elseif ($c_lead['status'] == 'Lost') $status_bg = 'bg-[#EF4444]'; // Red
                    ?>
                    <div class="flex items-center justify-between p-3 border border-gray-100 rounded bg-gray-50/50 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-bold <?= $c_lead['icon'] ?>">
                                <?= substr($c_lead['name'], 0, 1) ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?= $c_lead['name'] ?></p>
                                <p class="text-xs text-gray-500">Value : <?= $c_lead['value'] ?></p>
                            </div>
                        </div>
                        <div>
                            <span class="px-2.5 py-1 text-[11px] font-bold text-white rounded flex items-center gap-1.5 <?= $status_bg ?>">
                                <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                                <?= $c_lead['status'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card p-5">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg text-gray-900">Recent Follow Up</h3>
                    <button class="px-3 py-1 bg-white border rounded text-sm text-gray-700 shadow-sm">View All</button>
                </div>
                <div class="flex flex-col gap-6">
                    <div class="flex justify-between items-center">
                        <div class="flex gap-3 items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-900 flex items-center justify-center text-white overflow-hidden"><img src="https://i.pravatar.cc/100?img=11" alt="avatar" class="w-full h-full object-cover"></div>
                            <div><p class="text-sm font-bold text-gray-900">Alexander Jermai</p><p class="text-xs text-gray-500">UI/UX Designer</p></div>
                        </div>
                        <button class="w-8 h-8 flex items-center justify-center rounded bg-gray-50 border text-gray-500 hover:text-gray-800 transition">✉️</button>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex gap-3 items-center">
                            <div class="w-10 h-10 rounded-full bg-orange-200 flex items-center justify-center text-white overflow-hidden"><img src="https://i.pravatar.cc/100?img=5" alt="avatar" class="w-full h-full object-cover"></div>
                            <div><p class="text-sm font-bold text-gray-900">Doglas Martini</p><p class="text-xs text-gray-500">Product Designer</p></div>
                        </div>
                        <button class="w-8 h-8 flex items-center justify-center rounded bg-gray-50 border text-gray-500 hover:text-gray-800 transition">📞</button>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex gap-3 items-center">
                            <div class="w-10 h-10 rounded-full bg-red-200 flex items-center justify-center text-white overflow-hidden"><img src="https://i.pravatar.cc/100?img=9" alt="avatar" class="w-full h-full object-cover"></div>
                            <div><p class="text-sm font-bold text-gray-900">Daniel Esbella</p><p class="text-xs text-gray-500">Project Manager</p></div>
                        </div>
                        <button class="w-8 h-8 flex items-center justify-center rounded bg-gray-50 border text-gray-500 hover:text-gray-800 transition">✉️</button>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex gap-3 items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-200 flex items-center justify-center text-white overflow-hidden"><img src="https://i.pravatar.cc/100?img=12" alt="avatar" class="w-full h-full object-cover"></div>
                            <div><p class="text-sm font-bold text-gray-900">Daniel Esbella</p><p class="text-xs text-gray-500">Team Lead</p></div>
                        </div>
                        <button class="w-8 h-8 flex items-center justify-center rounded bg-gray-50 border text-gray-500 hover:text-gray-800 transition">💬</button>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex gap-3 items-center">
                            <div class="w-10 h-10 rounded-full bg-yellow-200 flex items-center justify-center text-white overflow-hidden"><img src="https://i.pravatar.cc/100?img=1" alt="avatar" class="w-full h-full object-cover"></div>
                            <div><p class="text-sm font-bold text-gray-900">Doglas Martini</p><p class="text-xs text-gray-500">Team Lead</p></div>
                        </div>
                        <button class="w-8 h-8 flex items-center justify-center rounded bg-gray-50 border text-gray-500 hover:text-gray-800 transition">💬</button>
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg text-gray-900">Recent Activities</h3>
                    <button class="px-3 py-1 bg-white border rounded text-sm text-gray-700 shadow-sm">View All</button>
                </div>
                <div class="relative pl-3 ml-3 border-l border-dashed border-gray-200 flex flex-col gap-8 mt-2 pb-2">
                    <div class="relative">
                        <div class="absolute -left-[27px] top-0 w-7 h-7 rounded-full bg-green-500 flex items-center justify-center text-white border-[3px] border-white">📞</div>
                        <p class="text-sm font-bold text-gray-900">Drain responded to your appointment schedule question.</p>
                        <p class="text-xs text-gray-500 mt-1.5">09:25 PM</p>
                    </div>
                    <div class="relative">
                        <div class="absolute -left-[27px] top-0 w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white border-[3px] border-white">💬</div>
                        <p class="text-sm font-bold text-gray-900">You sent 1 Message to the James.</p>
                        <p class="text-xs text-gray-500 mt-1.5">10:25 PM</p>
                    </div>
                    <div class="relative">
                        <div class="absolute -left-[27px] top-0 w-7 h-7 rounded-full bg-green-500 flex items-center justify-center text-white border-[3px] border-white">📞</div>
                        <p class="text-sm font-bold text-gray-900">Denwar responded to your appointment on 25 Jan 2025, 08:15 PM</p>
                        <p class="text-xs text-gray-500 mt-1.5">09:25 PM</p>
                    </div>
                    <div class="relative flex items-center gap-2">
                        <div class="absolute -left-[27px] top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-purple-500 flex items-center justify-center text-white border-[3px] border-white text-[10px]">👤</div>
                        <p class="text-sm font-bold text-gray-900">Meeting With</p>
                        <div class="w-6 h-6 rounded-full bg-gray-200 overflow-hidden"><img src="https://i.pravatar.cc/100?img=60" alt="avatar" class="w-full h-full object-cover"></div>
                        <p class="text-sm font-bold text-gray-900">Abraham</p>
                    </div>
                    <div class="relative">
                        <p class="text-xs text-gray-500 mt-[-10px]">09:25 PM</p>
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg text-gray-900">Notifications</h3>
                    <button class="px-3 py-1 bg-white border rounded text-sm text-gray-700 shadow-sm">View All</button>
                </div>
                <div class="flex flex-col gap-6 mt-2">
                    <div class="flex gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-blue-900"><img src="https://i.pravatar.cc/100?img=11" alt="avatar" class="w-full h-full object-cover"></div>
                        <div>
                            <p class="text-sm font-bold text-gray-900">Lex Murphy requested access to UNIX</p>
                            <p class="text-xs text-gray-500 mt-0.5">Today at 9:42 AM</p>
                            <div class="flex items-center gap-1.5 mt-2 bg-gray-50 border border-gray-100 px-3 py-1.5 rounded w-max">
                                <span class="w-4 h-4 rounded-full bg-red-500 text-white text-[8px] flex justify-center items-center">📄</span>
                                <span class="text-xs font-semibold text-gray-700">EY_review.pdf</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-pink-200"><img src="https://i.pravatar.cc/100?img=12" alt="avatar" class="w-full h-full object-cover"></div>
                        <div>
                            <p class="text-sm font-bold text-gray-900">Lex Murphy requested access to UNIX</p>
                            <p class="text-xs text-gray-500 mt-0.5">Today at 10:00 AM</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-teal-200"><img src="https://i.pravatar.cc/100?img=33" alt="avatar" class="w-full h-full object-cover"></div>
                        <div>
                            <p class="text-sm font-bold text-gray-900">Lex Murphy requested access to UNIX</p>
                            <p class="text-xs text-gray-500 mt-0.5 mb-2">Today at 10:50 AM</p>
                            <div class="flex gap-2">
                                <button class="bg-[#F97316] hover:bg-orange-600 text-white px-4 py-1.5 rounded text-xs font-bold transition">Approve</button>
                                <button class="bg-white border border-[#F97316] text-[#F97316] hover:bg-orange-50 px-4 py-1.5 rounded text-xs font-bold transition">Decline</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-red-900"><img src="https://i.pravatar.cc/100?img=13" alt="avatar" class="w-full h-full object-cover"></div>
                        <div>
                            <p class="text-sm font-bold text-gray-900">Lex Murphy requested access to UNIX</p>
                            <p class="text-xs text-gray-500 mt-0.5">Today at 05:00 PM</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">New Leads</h3>
                    <button class="px-3 py-1 bg-white border rounded text-sm flex items-center gap-1 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        This Week
                    </button>
                </div>
                
                <div class="flex h-56 w-full text-[11px] font-semibold text-white text-center pb-6 mt-6">
                    <div class="flex flex-col justify-between items-end pr-3 text-gray-500 h-full w-8 font-normal relative -top-3">
                        <span>120</span><span>80</span><span>60</span><span>40</span><span>20</span><span>0</span>
                    </div>
                    <div class="flex-1 grid grid-cols-7 gap-[2px] h-full border-b border-gray-200 relative pb-1">
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">22</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">22</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">22</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Mon</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">20</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">29</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">29</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">29</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">29</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Tue</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div class="w-full h-8 bg-[#F97316] flex items-center justify-center">75</div>
                            <div class="w-full h-8 bg-[#E2E8F0] text-gray-400 flex items-center justify-center">13</div>
                            <div class="w-full h-8 bg-[#FFEDD5] text-gray-400 flex items-center justify-center">13</div>
                            <div class="w-full h-8 bg-[#E2E8F0] text-gray-400 flex items-center justify-center">13</div>
                            <div class="w-full h-8 bg-[#FFEDD5] text-gray-400 flex items-center justify-center">13</div>
                            <div class="w-full h-8 bg-[#E2E8F0] text-gray-400 flex items-center justify-center">13</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Wed</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Thu</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Fri</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Sat</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div class="w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Sun</span>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

    </div> <script>
        // ApexCharts config for Pipeline Stages (Stacked Bar)
        var options = {
            series: [{
                name: 'Contacted', data: [25, 30, 45, 55, 60, 50, 40, 55, 45, 35, 20, 30]
            }, {
                name: 'Opportunity', data: [30, 20, 30, 25, 30, 30, 40, 30, 35, 40, 0, 25]
            }, {
                name: 'Not Contacted', data: [10, 5, 10, 25, 30, 25, 20, 25, 20, 15, 0, 25]
            }],
            chart: { type: 'bar', height: 280, stacked: true, toolbar: { show: false } },
            colors: ['#F97316', '#115E59', '#3B82F6'], // Orange, Teal, Blue
            plotOptions: { bar: { horizontal: false, columnWidth: '50%', borderRadius: 4 } },
            xaxis: { categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] },
            legend: { show: false },
            dataLabels: { enabled: false }
        };

        var chart = new ApexCharts(document.querySelector("#pipelineChart"), options);
        chart.render();

        // ApexCharts config for Lost Leads
        var lostLeadsOptions = {
            series: [{
                name: 'Lost Leads',
                data: [80, 40, 60, 40]
            }],
            chart: { 
                type: 'bar', 
                height: 280, 
                toolbar: { show: false } 
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '45%',
                    borderRadius: 4,
                    colors: {
                        backgroundBarColors: ['#F3F4F6', '#F3F4F6', '#F3F4F6', '#F3F4F6'],
                        backgroundBarRadius: 4,
                    }
                },
            },
            colors: ['#F97316'], // Orange
            dataLabels: { enabled: false },
            xaxis: { 
                categories: ['Competitor', 'Budget', 'Unresponsive', 'Timing'],
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: '#6B7280', fontSize: '12px' } }
            },
            yaxis: {
                min: 0,
                max: 200,
                tickAmount: 4,
                labels: { style: { colors: '#6B7280', fontSize: '12px' } }
            },
            grid: {
                borderColor: '#E5E7EB',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } }
            }
        };

        var lostLeadsChart = new ApexCharts(document.querySelector("#lostLeadsChart"), lostLeadsOptions);
        lostLeadsChart.render();
    </script>
</body>
</html>