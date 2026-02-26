<?php
ob_start(); // Prevents "Cannot modify header information" errors
include '../sidebars.php'; 
include '../header.php';

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
    <title>Sales Executive Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; }
        .card { background: white; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }
        
        /* Layout wrapper to prevent overlap with fixed sidebar and header, and span full width */
        .dashboard-wrapper { 
            margin-left: 90px; 
            padding-top: 80px; 
            width: calc(100% - 90px); /* Fill the remaining width */
            box-sizing: border-box; /* Ensure padding doesn't cause overflow */
        }
        @media (max-width: 768px) { 
            .dashboard-wrapper { 
                margin-left: 0; 
                width: 100%;
            } 
        }
        .lead-box { cursor: pointer; transition: transform 0.1s; }
        .lead-box:hover { transform: scale(1.02); filter: brightness(0.95); }
    </style>
</head>
<body class="text-gray-800">

    <div id="leadModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[9999] p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-lg" id="modalTitle">Leads</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="p-4 max-h-[400px] overflow-y-auto" id="modalContent">
                </div>
        </div>
    </div>

    <div class="dashboard-wrapper p-6">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Sales Executive Dashboard</h1>
                <p class="text-sm text-gray-500">Dashboard > Sales Executive Dashboard</p>
            </div>
            <div class="flex gap-3">
                <button class="px-4 py-2 bg-white border rounded shadow-sm text-sm">Export ⌄</button>
                <button class="px-4 py-2 bg-white border rounded shadow-sm text-sm" id="dashboard-date"><?= date('m/d/Y') ?></button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <?php foreach ($kpis as $kpi): ?>
            <div class="card p-5">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-10 h-10 rounded-lg text-white flex items-center justify-center <?= $kpi['color'] ?>">
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
            
            <div class="card p-8 flex flex-col items-center justify-center h-full relative" id="attendanceCardWrapper">
                
                <div id="stateInitial" class="flex flex-col items-center w-full h-full justify-center transition-all duration-300">
                    <p class="text-sm text-gray-500 mb-1">Good Morning, admin</p>
                    <h2 id="live-clock" class="text-4xl font-extrabold text-[#1c2c42] mb-1 tracking-tight">--:-- --</h2>
                    <p id="live-date" class="text-sm text-gray-400 mb-6">-- --- ----</p>

                    <div class="w-24 h-24 rounded-full bg-gradient-to-r from-blue-500 to-green-500 p-[3px] mb-8">
                        <div class="w-full h-full rounded-full border-2 border-white bg-[#225a58] flex items-center justify-center text-3xl font-normal text-white">
                            AD
                        </div>
                    </div>

                    <div class="w-full bg-[#225a58] text-white py-2.5 rounded-md font-semibold text-sm mb-4 text-center">
                        Production : <span id="prod-display">0.00</span> hrs
                    </div>

                    <div class="text-[#0ea5e9] text-emerald-500 text-sm font-medium mb-4 flex items-center justify-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Not Punched In</span>
                    </div>

                    <button onclick="punchIn()" class="w-full bg-[#225a58] hover:bg-[#1a4443] text-white py-3 rounded-md font-bold text-sm transition-colors mt-auto">
                        Punch In
                    </button>
                </div>

                <div id="statePunchedIn" class="hidden flex-col items-center w-full h-full justify-center transition-all duration-300">
                    <p class="text-xs font-bold text-gray-400 tracking-wider mb-1 uppercase">Today's Attendance</p>
                    <h2 id="punch-in-display-time" class="text-lg font-bold text-[#1c2c42] mb-6">--:-- --, -- --- ----</h2>

                    <div class="relative w-40 h-40 mb-8 flex items-center justify-center">
                        <svg class="absolute inset-0 w-full h-full transform -rotate-90">
                            <circle cx="80" cy="80" r="68" stroke="#f1f5f9" stroke-width="12" fill="none"></circle>
                            <circle id="progressCircle" cx="80" cy="80" r="68" stroke="#0d9488" stroke-width="12" fill="none" stroke-dasharray="427" stroke-dashoffset="427" stroke-linecap="round" class="transition-all duration-500"></circle>
                        </svg>
                        <div class="text-center z-10 flex flex-col items-center mt-1">
                            <span id="timerLabel" class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Total Hours</span>
                            <span id="timerValue" class="text-[28px] font-bold text-[#1c2c42] leading-tight">00:00:00</span>
                        </div>
                    </div>

                    <div class="flex w-full gap-3 mb-5 mt-auto">
                        <button id="breakBtn" onclick="toggleBreak()" class="flex-1 bg-white hover:bg-gray-50 text-[#f59e0b] border border-[#f59e0b] py-3 rounded-xl font-bold text-[15px] flex items-center justify-center gap-1.5 transition-colors shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z"/></svg>
                            Break
                        </button>
                        <button onclick="punchOut()" class="flex-1 bg-[#225a58] hover:bg-[#1a4443] text-white py-3 rounded-xl font-bold text-[15px] flex items-center justify-center gap-1.5 transition-colors shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="square" stroke-linejoin="miter" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            Out
                        </button>
                    </div>

                    <p class="text-[13px] text-gray-500 font-medium flex items-center gap-1.5">
                         <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#f97316]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" /></svg>
                         Punched In at: <span id="actual-punch-time" class="font-bold text-gray-800">--:-- --</span>
                    </p>
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

            <div class="card overflow-hidden flex flex-col h-full">
                <div class="bg-teal-700 text-center p-6 text-white">
                    <div class="w-20 h-20 bg-teal-600 rounded-full mx-auto border-4 border-white flex items-center justify-center text-2xl font-bold relative">
                        SP
                        <div class="absolute bottom-0 right-0 w-4 h-4 bg-green-400 rounded-full border-2 border-white"></div>
                    </div>
                    <h2 class="text-xl font-bold mt-3">Stephen Peralt</h2>
                    <p class="text-sm text-teal-200">Senior Software Engineer</p>
                    <button class="mt-3 px-4 py-1 bg-teal-600/50 rounded-full text-xs font-semibold">Verified Account</button>
                </div>
                <div class="p-5 flex-1">
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

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="card p-5 lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Monthly Target</h3>
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
            
            <div class="card p-5 lg:col-span-1">
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
                    <h3 class="font-bold text-lg">New Leads</h3>
                    <button onclick="toggleWeekSelection()" class="px-3 py-1 bg-white border rounded text-sm flex items-center gap-1 shadow-sm hover:bg-gray-50 transition cursor-pointer" id="weekSelectorBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span id="weekSelectorText">This Week</span>
                    </button>
                </div>
                
                <div class="flex h-56 w-full text-[11px] font-semibold text-white text-center pb-6 mt-6">
                    <div class="flex flex-col justify-between items-end pr-3 text-gray-500 h-full w-8 font-normal relative -top-3">
                        <span>120</span><span>80</span><span>60</span><span>40</span><span>20</span><span>0</span>
                    </div>
                    <div class="flex-1 grid grid-cols-7 gap-[2px] h-full border-b border-gray-200 relative pb-1">
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div onclick="showLeads('Monday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">22</div>
                            <div onclick="showLeads('Monday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">22</div>
                            <div onclick="showLeads('Monday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">22</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Mon</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div onclick="showLeads('Tuesday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">20</div>
                            <div onclick="showLeads('Tuesday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">29</div>
                            <div onclick="showLeads('Tuesday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">29</div>
                            <div onclick="showLeads('Tuesday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">29</div>
                            <div onclick="showLeads('Tuesday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">29</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Tue</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div onclick="showLeads('Wednesday')" class="lead-box w-full h-8 bg-[#F97316] flex items-center justify-center">75</div>
                            <div onclick="showLeads('Wednesday')" class="lead-box w-full h-8 bg-[#E2E8F0] text-gray-400 flex items-center justify-center">13</div>
                            <div onclick="showLeads('Wednesday')" class="lead-box w-full h-8 bg-[#FFEDD5] text-gray-400 flex items-center justify-center">13</div>
                            <div onclick="showLeads('Wednesday')" class="lead-box w-full h-8 bg-[#E2E8F0] text-gray-400 flex items-center justify-center">13</div>
                            <div onclick="showLeads('Wednesday')" class="lead-box w-full h-8 bg-[#FFEDD5] text-gray-400 flex items-center justify-center">13</div>
                            <div onclick="showLeads('Wednesday')" class="lead-box w-full h-8 bg-[#E2E8F0] text-gray-400 flex items-center justify-center">13</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Wed</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div onclick="showLeads('Thursday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Thursday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Thursday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Thursday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Thursday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Thu</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div onclick="showLeads('Friday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Friday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Friday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Fri</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div onclick="showLeads('Saturday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Saturday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Saturday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Saturday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Saturday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Sat</span>
                        </div>
                        <div class="flex flex-col justify-end gap-[2px] h-full relative">
                            <div onclick="showLeads('Sunday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Sunday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Sunday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Sunday')" class="lead-box w-full h-8 bg-[#FDBA74] flex items-center justify-center">32</div>
                            <div onclick="showLeads('Sunday')" class="lead-box w-full h-8 bg-[#CBD5E1] flex items-center justify-center">32</div>
                            <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-gray-500 font-normal">Sun</span>
                        </div>
                    </div>
                </div>
                
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

        </div>

    </div>
    <script>
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

        // ---- DYNAMIC LOGIC: REAL TIME CLOCK ----
        function updateLiveTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
            const dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            
            if(document.getElementById('live-clock')) document.getElementById('live-clock').innerText = timeStr;
            if(document.getElementById('live-date')) document.getElementById('live-date').innerText = dateStr;
            if(document.getElementById('punch-in-display-time')) {
                document.getElementById('punch-in-display-time').innerText = timeStr + ", " + dateStr;
            }
        }
        setInterval(updateLiveTime, 1000);
        updateLiveTime();

        // ---- DYNAMIC LOGIC: STOPWATCH & PUNCHING ----
        let isOnBreak = false;
        let totalSeconds = 0;
        let timerInterval = null;

        function punchIn() {
            // UI Switch
            document.getElementById('stateInitial').classList.add('hidden');
            document.getElementById('statePunchedIn').classList.remove('hidden');
            document.getElementById('statePunchedIn').classList.add('flex');
            
            // Set static punch time
            const now = new Date();
            document.getElementById('actual-punch-time').innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
            
            // Start Timer
            startStopwatch();
        }

        function punchOut() {
            // UI Switch back
            document.getElementById('statePunchedIn').classList.add('hidden');
            document.getElementById('statePunchedIn').classList.remove('flex');
            document.getElementById('stateInitial').classList.remove('hidden');
            
            // Stop and reset
            clearInterval(timerInterval);
            document.getElementById('prod-display').innerText = (totalSeconds / 3600).toFixed(2);
            totalSeconds = 0;
            updateTimerUI();
        }

        function startStopwatch() {
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                if (!isOnBreak) {
                    totalSeconds++;
                    updateTimerUI();
                }
            }, 1000);
        }

        function updateTimerUI() {
            const h = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
            const m = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
            const s = (totalSeconds % 60).toString().padStart(2, '0');
            document.getElementById('timerValue').innerText = `${h}:${m}:${s}`;
            
            // Circular Progress Animation (based on 8h shift)
            const maxSecs = 28800; 
            const progress = Math.min(totalSeconds, maxSecs) / maxSecs;
            const dashoffset = 427 - (progress * 427);
            document.getElementById('progressCircle').setAttribute('stroke-dashoffset', dashoffset);
        }

        function toggleBreak() {
            isOnBreak = !isOnBreak;
            const breakBtn = document.getElementById('breakBtn');
            const timerLabel = document.getElementById('timerLabel');
            const progressCircle = document.getElementById('progressCircle');

            if (isOnBreak) {
                breakBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg> Resume`;
                breakBtn.className = 'flex-1 bg-white hover:bg-gray-50 text-[#3b82f6] border border-[#3b82f6] py-3 rounded-xl font-bold text-[15px] flex items-center justify-center gap-1.5 transition-colors shadow-sm';
                timerLabel.innerText = 'ON BREAK';
                progressCircle.setAttribute('stroke', '#f97316'); // Orange
            } else {
                breakBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 fill-current" viewBox="0 0 24 24"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z"/></svg> Break`;
                breakBtn.className = 'flex-1 bg-white hover:bg-gray-50 text-[#f59e0b] border border-[#f59e0b] py-3 rounded-xl font-bold text-[15px] flex items-center justify-center gap-1.5 transition-colors shadow-sm';
                timerLabel.innerText = 'TOTAL HOURS';
                progressCircle.setAttribute('stroke', '#0d9488'); // Teal
            }
        }

        // ---- LEAD LIST LOGIC ----
        const mockLeadsByDay = {
            'Monday': ['Acme Corp', 'Globex', 'Soylent Corp'],
            'Tuesday': ['Initech', 'Umbrella Corp', 'Hooli'],
            'Wednesday': ['Stark Ind', 'Wayne Ent', 'Oscorp'],
            'Thursday': ['Cyberdyne', 'Tyrell Corp', 'Weyland-Yutani'],
            'Friday': ['Wonka Ind', 'Duff Beer', 'Bubba Gump'],
            'Saturday': ['Pied Piper', 'Bluth Company'],
            'Sunday': ['Vandelay Ind', 'Kramerica']
        };

        function showLeads(day) {
            const modal = document.getElementById('leadModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            // Calculate date for the clicked day
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const today = new Date();
            const todayIndex = today.getDay() === 0 ? 6 : today.getDay() - 1; // Adjust index so Mon=0, Sun=6
            const targetIndex = dayNames.indexOf(day) === 0 ? 6 : dayNames.indexOf(day) - 1;
            
            const specificDate = new Date(today);
            specificDate.setDate(today.getDate() + (targetIndex - todayIndex));
            
            // Adjust if 'Last Week' is active in the toggle
            const weekSelector = document.getElementById('weekSelectorText');
            if (weekSelector && weekSelector.innerText === 'Last Week') {
                specificDate.setDate(specificDate.getDate() - 7);
            }

            // Format date to string (e.g., "25 Feb 2026")
            const dateString = specificDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            
            title.innerText = `New Leads for ${day} (${dateString})`;
            content.innerHTML = '';
            
            const leads = mockLeadsByDay[day] || [];
            if(leads.length === 0) {
                content.innerHTML = '<p class="text-gray-500 text-center italic">No leads recorded for this day.</p>';
            } else {
                leads.forEach(lead => {
                    const div = document.createElement('div');
                    div.className = 'flex items-center gap-3 p-3 border-b last:border-0 hover:bg-gray-50 transition cursor-pointer';
                    div.innerHTML = `
                        <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center font-bold text-xs">
                            ${lead.charAt(0)}
                        </div>
                        <span class="font-medium text-gray-700">${lead}</span>
                    `;
                    content.appendChild(div);
                });
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            const modal = document.getElementById('leadModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close modal on click outside
        window.onclick = function(event) {
            const modal = document.getElementById('leadModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // --- NEW LEADS WEEK SELECTOR LOGIC ---
        function toggleWeekSelection() {
            const btnText = document.getElementById('weekSelectorText');
            if (btnText.innerText === 'This Week') {
                btnText.innerText = 'Last Week';
                // You could also trigger an update to the New Leads chart data here
            } else {
                btnText.innerText = 'This Week';
                // Revert chart data here
            }
        }
    </script>
</body>
</html>