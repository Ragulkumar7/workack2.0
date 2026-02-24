<?php
// Database and Path configuration
if (file_exists('include/db_connect.php')) {
    require_once 'include/db_connect.php';
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
} else {
    die("Critical Error: Cannot find database connection file.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Deals Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #fcfcfd; }
        .funnel-step { transition: all 0.3s ease; cursor: pointer; }
        .funnel-step:hover { filter: brightness(1.1); transform: translateX(5px); }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="text-slate-800 flex h-screen overflow-hidden">

    <?php 
    if(file_exists($sidebarPath)) {
        require_once $sidebarPath; 
    }
    ?>

    <div class="flex-1 flex flex-col h-screen overflow-y-auto pb-10 ml-[100px]">
        
        <?php 
        if(file_exists($headerPath)) {
            require_once $headerPath; 
        }
        ?>

        <header class="p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white border-b border-slate-100 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Deals Dashboard</h1>
                <div class="flex items-center text-xs text-slate-400 font-medium mt-1">
                    <i class="fa-solid fa-house-chimney mr-2"></i> Dashboard 
                    <i class="fa-solid fa-chevron-right mx-2 text-[8px]"></i> Deals Dashboard
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-bold flex items-center shadow-sm hover:bg-slate-50 transition">
                    <i class="fa-solid fa-download mr-2 text-slate-400"></i> Export <i class="fa-solid fa-chevron-down ml-2 text-[8px]"></i>
                </button>
                <button class="bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-bold flex items-center shadow-sm hover:bg-slate-50 transition">
                    <i class="fa-solid fa-calendar-range mr-2 text-slate-400"></i> 02/18/2026 - 02/24/2026
                </button>
            </div>
        </header>

        <main class="max-w-[1600px] w-full mx-auto px-4 md:px-6 space-y-6">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] overflow-hidden flex flex-col">
                    <div class="p-6 pb-0 border-b border-slate-100 flex justify-between items-center mb-6">
                        <h3 class="text-[17px] font-bold text-[#1e293b] pb-6">Pipeline Stages</h3>
                        <button class="text-xs font-medium border border-slate-200 px-3 py-1.5 rounded-md flex items-center gap-1.5 text-slate-600 mb-6 hover:bg-slate-50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            This Week
                        </button>
                    </div>

                    <div class="px-6 flex flex-col items-center gap-[2px] mb-10 w-full relative">
                        <div class="absolute w-[90%] border-l border-r border-slate-100 h-full top-0 -z-10 border-dashed"></div>

                        <div class="funnel-step w-[90%] bg-[#eb7943] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm">Marketing : 7,898</div>
                        <div class="funnel-step w-[75%] bg-[#f19262] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm">Sales : 4,658</div>
                        <div class="funnel-step w-[62%] bg-[#f5aa81] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm">Email : 2,898</div>
                        <div class="funnel-step w-[50%] bg-[#f9c2a0] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm">Chat : 789</div>
                        <div class="funnel-step w-[38%] bg-[#fcdabe] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm">Operational : 655</div>
                        <div class="funnel-step w-[28%] bg-[#fee5cf] h-11 rounded-xl flex items-center justify-center text-white text-[13px] font-medium shadow-sm">Calls : 454</div>
                    </div>

                    <div class="px-6 pb-6 mt-auto">
                        <h4 class="text-[15px] font-bold text-[#1e293b] mb-4">Leads Values By Stages</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#eb7943]"></span> Marketing
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">$5,221.45</p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f19262]"></span> Sales
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">$30,424</p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f5aa81]"></span> Email
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">$21,135</p>
                            </div>
                            <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                                <p class="text-[13px] text-slate-500 mb-1 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f9c2a0]"></span> Chat
                                </p>
                                <p class="font-bold text-[#1e293b] text-sm">$15,235</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-7 grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Total Deals</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">$45,221,45</h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#e85b2b] flex items-center justify-center text-white shadow-sm mt-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4l-8 12h16l-8-12z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 18h8"></path></svg>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#e85b2b] h-full rounded-full" style="width: 55%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-red-500">~ -4.01%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Total Customers</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">9895</h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#9b51e0] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-solid fa-users text-[14px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#9b51e0] h-full rounded-full" style="width: 50%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-emerald-500">~ +55%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Deal Value</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">$12,545,68</h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#0d4752] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-brands fa-connectdevelop text-[16px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#0d4752] h-full rounded-full" style="width: 45%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-emerald-500">~ +20.01%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Conversion Rate</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">51.96%</h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#2563eb] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-solid fa-layer-group text-[14px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#2563eb] h-full rounded-full" style="width: 55%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-red-500">~ -6.01%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Revenue this month</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">$46,548,48</h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#ec4899] flex items-center justify-center text-white shadow-sm mt-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#ec4899] h-full rounded-full" style="width: 65%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-emerald-500">~ +55%</span> from last week
                            </p>
                        </div>
                    </div>

                    <div class="bg-white bg-pattern p-6 rounded-xl border border-slate-200 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-500 text-[14px] font-medium mb-1">Active Customers</p>
                                <h2 class="text-[22px] font-bold text-[#1e293b]">8987</h2>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-[#f59e0b] flex items-center justify-center text-white shadow-sm mt-1">
                                <i class="fa-regular fa-star text-[16px]"></i>
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-3">
                                <div class="bg-[#f59e0b] h-full rounded-full" style="width: 80%;"></div>
                            </div>
                            <p class="text-[13px] font-medium text-slate-500">
                                <span class="text-red-500">~ -3.22%</span> from last week
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-extrabold text-slate-800">Deals By Country</h3>
                        <button class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition">View All</button>
                    </div>
                    <div class="space-y-5">
                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇺🇸</span>
                                <div><p class="text-sm font-extrabold text-slate-800">USA</p><p class="text-[10px] font-bold text-slate-400">Deals : 350</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                <svg class="w-8 h-6 text-emerald-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">$1065.00</p></div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇦🇪</span>
                                <div><p class="text-sm font-extrabold text-slate-800">UAE</p><p class="text-[10px] font-bold text-slate-400">Deals : 221</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                 <svg class="w-8 h-6 text-emerald-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">$966.00</p></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇸🇬</span>
                                <div><p class="text-sm font-extrabold text-slate-800">Singapore</p><p class="text-[10px] font-bold text-slate-400">Deals : 236</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                 <svg class="w-8 h-6 text-red-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline><polyline points="16 17 22 17 22 11"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">$959.00</p></div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between group cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl drop-shadow-sm">🇫🇷</span>
                                <div><p class="text-sm font-extrabold text-slate-800">France</p><p class="text-[10px] font-bold text-slate-400">Deals : 589</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                 <svg class="w-8 h-6 text-emerald-400 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Total Value</p><p class="text-sm font-extrabold text-slate-800">$879.00</p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="font-extrabold text-slate-800">Won Deals Stage</h3>
                        <span class="text-[10px] font-bold bg-slate-50 px-2 py-1 rounded-lg border border-slate-100"><i class="fa-solid fa-calendar text-slate-400 mr-1"></i> This Week</span>
                    </div>
                    <div class="text-center mb-6 mt-4">
                        <p class="text-[10px] text-slate-400 uppercase font-bold tracking-widest">Stages Won This Year</p>
                        <h2 class="text-3xl font-black text-slate-900 mt-1">$45,899,79 <span class="text-xs bg-red-50 text-red-500 px-2 py-0.5 rounded-md font-bold align-middle">↓ 12%</span></h2>
                    </div>
                    <div class="relative flex-grow flex items-center justify-center min-h-[220px]">
                        <div class="absolute top-0 left-0 md:left-4 w-36 h-36 rounded-full bg-[#0d3b44] shadow-2xl flex flex-col items-center justify-center text-white ring-4 ring-white hover:scale-105 transition cursor-pointer z-10">
                            <span class="text-[10px] font-medium opacity-80">Conversion</span><span class="text-3xl font-black">48%</span>
                        </div>
                        <div class="absolute top-0 right-4 md:right-10 w-24 h-24 rounded-full bg-red-600 shadow-xl flex flex-col items-center justify-center text-white ring-4 ring-white z-20 hover:scale-105 transition cursor-pointer">
                            <span class="text-[9px] font-medium opacity-80">Calls</span><span class="text-xl font-black">24%</span>
                        </div>
                        <div class="absolute bottom-4 right-0 md:right-6 w-32 h-32 rounded-full bg-amber-400 shadow-xl flex flex-col items-center justify-center text-white ring-4 ring-white z-10 hover:scale-105 transition cursor-pointer">
                            <span class="text-[11px] font-medium opacity-80">Email</span><span class="text-2xl font-black">39%</span>
                        </div>
                        <div class="absolute bottom-0 left-16 md:left-24 w-20 h-20 rounded-full bg-emerald-500 shadow-xl flex flex-col items-center justify-center text-white ring-4 ring-white z-20 hover:scale-105 transition cursor-pointer">
                            <span class="text-[9px] font-medium opacity-80">Chats</span><span class="text-lg font-black">20%</span>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-extrabold text-slate-800">Recent Follow Up</h3>
                        <button class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition">View All</button>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-2 -mx-2 hover:bg-slate-50 rounded-2xl transition group cursor-pointer border border-transparent hover:border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-blue-100 border-2 border-white shadow-sm overflow-hidden"><img src="https://i.pravatar.cc/100?u=1" alt="avatar" class="w-full h-full object-cover"></div>
                                <div><p class="text-sm font-extrabold text-slate-800">Alexander Jermai</p><p class="text-[11px] font-bold text-slate-400">UI/UX Designer</p></div>
                            </div>
                            <div class="w-8 h-8 flex items-center justify-center bg-slate-50 rounded-xl text-slate-400 group-hover:bg-orange-500 group-hover:text-white transition shadow-sm"><i class="fa-solid fa-envelope text-[11px]"></i></div>
                        </div>
                        
                        <div class="flex items-center justify-between p-2 -mx-2 hover:bg-slate-50 rounded-2xl transition group cursor-pointer border border-transparent hover:border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-pink-100 border-2 border-white shadow-sm overflow-hidden"><img src="https://i.pravatar.cc/100?u=2" alt="avatar" class="w-full h-full object-cover"></div>
                                <div><p class="text-sm font-extrabold text-slate-800">Doglas Martini</p><p class="text-[11px] font-bold text-slate-400">Product Designer</p></div>
                            </div>
                            <div class="w-8 h-8 flex items-center justify-center bg-slate-50 rounded-xl text-slate-400 group-hover:bg-orange-500 group-hover:text-white transition shadow-sm"><i class="fa-solid fa-phone text-[11px]"></i></div>
                        </div>
                        
                        <div class="flex items-center justify-between p-2 -mx-2 hover:bg-slate-50 rounded-2xl transition group cursor-pointer border border-transparent hover:border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-orange-100 border-2 border-white shadow-sm overflow-hidden"><img src="https://i.pravatar.cc/100?u=3" alt="avatar" class="w-full h-full object-cover"></div>
                                <div><p class="text-sm font-extrabold text-slate-800">Daniel Esbella</p><p class="text-[11px] font-bold text-slate-400">Project Manager</p></div>
                            </div>
                            <div class="w-8 h-8 flex items-center justify-center bg-slate-50 rounded-xl text-slate-400 group-hover:bg-orange-500 group-hover:text-white transition shadow-sm"><i class="fa-solid fa-message text-[11px]"></i></div>
                        </div>

                        <div class="flex items-center justify-between p-2 -mx-2 hover:bg-slate-50 rounded-2xl transition group cursor-pointer border border-transparent hover:border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-slate-100 border-2 border-white shadow-sm overflow-hidden"><img src="https://i.pravatar.cc/100?u=4" alt="avatar" class="w-full h-full object-cover opacity-80"></div>
                                <div><p class="text-sm font-extrabold text-slate-800">Stephan Peralt</p><p class="text-[11px] font-bold text-slate-400">Team Lead</p></div>
                            </div>
                            <div class="w-8 h-8 flex items-center justify-center bg-slate-50 rounded-xl text-slate-400 group-hover:bg-orange-500 group-hover:text-white transition shadow-sm"><i class="fa-solid fa-comment-dots text-[11px]"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-8 bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-white">
                        <h3 class="font-extrabold text-slate-800">Recent Deals</h3>
                        <button class="bg-slate-50 text-[11px] font-extrabold px-4 py-1.5 rounded-xl hover:bg-slate-100 transition text-slate-500">View All</button>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar flex-grow">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50/50 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-4">Deal Name</th>
                                    <th class="px-6 py-4 text-center">Stage</th>
                                    <th class="px-6 py-4">Deal Value</th>
                                    <th class="px-6 py-4">Owner</th>
                                    <th class="px-6 py-4">Closed Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr class="hover:bg-slate-50/80 transition cursor-pointer">
                                    <td class="px-6 py-5 font-extrabold text-slate-800">Collins</td>
                                    <td class="px-6 py-5 text-center"><span class="bg-slate-100 text-slate-500 px-3 py-1.5 rounded-lg text-[10px] font-bold">Quality To Buy</span></td>
                                    <td class="px-6 py-5 font-black text-slate-900">$4,50,000</td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shadow-sm border border-white"><img src="https://i.pravatar.cc/100?u=a" alt="u"></div>
                                            <span class="text-xs font-bold text-slate-700">Anthony Lewis</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-[11px] font-bold text-slate-400">14 Jan 2024</td>
                                </tr>
                                <tr class="hover:bg-slate-50/80 transition cursor-pointer">
                                    <td class="px-6 py-5 font-extrabold text-slate-800">Konopelski</td>
                                    <td class="px-6 py-5 text-center"><span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg text-[10px] font-bold">Proposal Made</span></td>
                                    <td class="px-6 py-5 font-black text-slate-900">$3,15,000</td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shadow-sm border border-white"><img src="https://i.pravatar.cc/100?u=b" alt="u"></div>
                                            <span class="text-xs font-bold text-slate-700">Brian Villalobos</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-[11px] font-bold text-slate-400">21 Jan 2024</td>
                                </tr>
                                <tr class="hover:bg-slate-50/80 transition cursor-pointer">
                                    <td class="px-6 py-5 font-extrabold text-slate-800">Adams</td>
                                    <td class="px-6 py-5 text-center"><span class="bg-orange-50 text-orange-600 px-3 py-1.5 rounded-lg text-[10px] font-bold">Contact Made</span></td>
                                    <td class="px-6 py-5 font-black text-slate-900">$8,40,000</td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shadow-sm border border-white"><img src="https://i.pravatar.cc/100?u=c" alt="u"></div>
                                            <span class="text-xs font-bold text-slate-700">Harvey Smith</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-[11px] font-bold text-slate-400">20 Feb 2024</td>
                                </tr>
                                <tr class="hover:bg-slate-50/80 transition cursor-pointer">
                                    <td class="px-6 py-5 font-extrabold text-slate-800">Schumm</td>
                                    <td class="px-6 py-5 text-center"><span class="bg-slate-100 text-slate-500 px-3 py-1.5 rounded-lg text-[10px] font-bold">Quality To Buy</span></td>
                                    <td class="px-6 py-5 font-black text-slate-900">$6,10,000</td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shadow-sm border border-white"><img src="https://i.pravatar.cc/100?u=d" alt="u"></div>
                                            <span class="text-xs font-bold text-slate-700">Stephan Peralt</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-[11px] font-bold text-slate-400">15 Mar 2024</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="font-extrabold text-slate-800">Recent Activities</h3>
                        <button class="text-[10px] font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg hover:bg-slate-100 transition">View All</button>
                    </div>
                    <div class="space-y-8 relative before:absolute before:left-[19px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                        
                        <div class="relative flex gap-5 group cursor-pointer">
                            <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center z-10 shadow-lg shadow-emerald-100 ring-4 ring-white group-hover:scale-110 transition"><i class="fa-solid fa-phone text-xs"></i></div>
                            <div class="pt-1">
                                <p class="text-xs font-extrabold text-slate-800 leading-relaxed pr-4">Drain responded to your appointment schedule question.</p>
                                <p class="text-[11px] font-bold text-slate-400 mt-1">09:25 PM</p>
                            </div>
                        </div>
                        
                        <div class="relative flex gap-5 group cursor-pointer">
                            <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center z-10 shadow-lg shadow-blue-100 ring-4 ring-white group-hover:scale-110 transition"><i class="fa-solid fa-message text-xs"></i></div>
                            <div class="pt-1">
                                <p class="text-xs font-extrabold text-slate-800 leading-relaxed pr-4">You sent 1 Message to the James.</p>
                                <p class="text-[11px] font-bold text-slate-400 mt-1">10:25 PM</p>
                            </div>
                        </div>

                        <div class="relative flex gap-5 group cursor-pointer">
                            <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center z-10 shadow-lg shadow-emerald-100 ring-4 ring-white group-hover:scale-110 transition"><i class="fa-solid fa-phone text-xs"></i></div>
                            <div class="pt-1">
                                <p class="text-xs font-extrabold text-slate-800 leading-relaxed pr-4">Denwar responded to your appointment on 25 Jan 2025.</p>
                                <p class="text-[11px] font-bold text-slate-400 mt-1">08:15 PM</p>
                            </div>
                        </div>
                        
                        <div class="relative flex gap-5 group cursor-pointer">
                            <div class="w-10 h-10 rounded-full bg-purple-500 text-white flex items-center justify-center z-10 shadow-lg shadow-purple-100 ring-4 ring-white group-hover:scale-110 transition"><i class="fa-solid fa-user-circle text-sm"></i></div>
                            <div class="pt-1 flex flex-col justify-center">
                                <p class="text-xs font-extrabold text-slate-800 leading-relaxed flex items-center gap-2">Meeting With <img src="https://i.pravatar.cc/100?u=ab" alt="a" class="w-5 h-5 rounded-full inline"> <span class="text-slate-900">Abraham</span></p>
                                <p class="text-[11px] font-bold text-slate-400 mt-1">09:25 PM</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>

        <div class="fixed right-0 top-1/2 -translate-y-1/2 bg-orange-500 p-3 rounded-l-xl shadow-xl shadow-orange-200 text-white cursor-pointer z-50 hover:bg-orange-600 transition">
            <i class="fa-solid fa-gear text-lg animate-spin-slow" style="animation: spin 6s linear infinite;"></i>
        </div>

    </div>
</body>
</html>