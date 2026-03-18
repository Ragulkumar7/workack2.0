<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack CMS | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        workack: {
                            light: '#d1fae5', // emerald-100
                            DEFAULT: '#10b981', // emerald-500
                            dark: '#047857', // emerald-700
                            darker: '#064e3b', // emerald-900
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal flex flex-col md:flex-row min-h-screen">
 <?php include 'sidebar.php'; ?>
    <div class="md:hidden bg-workack-darker text-white p-4 flex justify-between items-center z-50 shadow-md">
        <h1 class="text-xl font-bold tracking-wider text-workack-light"><i class="fas fa-leaf mr-2"></i> Workack</h1>
        <button id="mobile-menu-btn" class="text-white hover:text-workack-light focus:outline-none">
            <i class="fas fa-bars text-2xl"></i>
        </button>
    </div>

    

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto w-full">
        
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 z-10 shrink-0 sticky top-0">
            <div class="hidden sm:flex items-center bg-gray-100 rounded-md px-3 py-2 w-48 md:w-96 transition-all">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" placeholder="Search..." class="bg-transparent border-none outline-none ml-2 w-full text-sm">
            </div>
            
            <button class="sm:hidden text-gray-500 hover:text-workack p-2">
                <i class="fas fa-search"></i>
            </button>

            <div class="flex items-center space-x-2 md:space-x-4">
                <button class="text-gray-500 hover:text-workack p-2 relative">
                    <i class="fas fa-bell"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>
                <div class="flex items-center space-x-2 border-l pl-2 md:pl-4 cursor-pointer">
                    <div class="w-8 h-8 bg-workack rounded-full flex items-center justify-center text-white font-bold text-sm">A</div>
                    <span class="text-sm font-medium text-gray-700 hidden sm:block">Admin</span>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 pb-20 md:pb-6"> 
            <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <h2 class="text-2xl font-semibold text-gray-800">Overview</h2>
                <button class="bg-workack hover:bg-workack-dark text-white px-4 py-2 rounded-md shadow transition-colors text-sm self-start sm:self-auto flex items-center justify-center w-full sm:w-auto">
                    <i class="fas fa-plus mr-2"></i> Generate License Key
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-4 md:p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Customers</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-800">1,248</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-lg md:text-xl">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 md:p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Revenue</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-800">₹45,231</p> 
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-lg md:text-xl">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 md:p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Sales</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-800">856</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-lg md:text-xl">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 md:p-5 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Demo Users</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-800">142</p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 text-lg md:text-xl">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col">
                    <div class="px-4 md:px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-sm md:text-base">Application Tickets</h3>
                        <a href="#" class="text-xs md:text-sm text-workack hover:underline font-medium">View All</a>
                    </div>
                    <div class="p-0 overflow-x-auto flex-1">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-gray-50/50">
                                <tr class="text-gray-500 border-b">
                                    <th class="px-4 py-3 font-medium">Customer</th>
                                    <th class="px-4 py-3 font-medium">Issue</th>
                                    <th class="px-4 py-3 font-medium text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b last:border-0 hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 font-medium text-gray-800">TechCorp Inc.</td>
                                    <td class="px-4 py-3 text-gray-600">Login Session Expired</td>
                                    <td class="px-4 py-3 text-right"><span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-semibold">Open</span></td>
                                </tr>
                                <tr class="border-b last:border-0 hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 font-medium text-gray-800">Jane Doe</td>
                                    <td class="px-4 py-3 text-gray-600">License Key Missing</td>
                                    <td class="px-4 py-3 text-right"><span class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-xs font-semibold">Pending</span></td>
                                </tr>
                                <tr class="border-b last:border-0 hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 font-medium text-gray-800">Global Systems</td>
                                    <td class="px-4 py-3 text-gray-600">Payment Gateway Error</td>
                                    <td class="px-4 py-3 text-right"><span class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs font-semibold">Resolved</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col">
                    <div class="px-4 md:px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-semibold text-gray-800 text-sm md:text-base">Demo Limits & Licenses</h3>
                    </div>
                    <div class="p-4 space-y-3 flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-gray-50 border border-gray-100 rounded-lg hover:shadow-sm transition-shadow gap-2">
                            <div>
                                <p class="text-sm font-bold text-gray-800">Mark Smith <span class="font-normal text-gray-500">(Demo)</span></p>
                                <p class="text-xs text-gray-500 mt-0.5">Time limit expires in 2 hours</p>
                            </div>
                            <span class="text-[10px] sm:text-xs bg-red-100 text-red-600 px-2 py-1 rounded border border-red-200 self-start sm:self-auto font-semibold whitespace-nowrap">Expiring Soon</span>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-gray-50 border border-gray-100 rounded-lg hover:shadow-sm transition-shadow gap-2">
                            <div>
                                <p class="text-sm font-bold text-gray-800 font-mono">WRK-99A2-B4X1</p>
                                <p class="text-xs text-gray-500 mt-0.5">License sent to contact@acme.com</p>
                            </div>
                            <span class="text-[10px] sm:text-xs bg-workack-light text-workack-dark px-2 py-1 rounded border border-workack self-start sm:self-auto font-semibold whitespace-nowrap">Sent</span>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-gray-50 border border-gray-100 rounded-lg hover:shadow-sm transition-shadow gap-2">
                            <div>
                                <p class="text-sm font-bold text-gray-800">Sarah Johnson</p>
                                <p class="text-xs text-gray-500 mt-0.5">Session Expired Details Logged</p>
                            </div>
                            <span class="text-[10px] sm:text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded border border-gray-300 self-start sm:self-auto font-semibold whitespace-nowrap">Logged</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        const btn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function toggleSidebar() {
            // Toggle translate classes
            sidebar.classList.toggle('-translate-x-full');
            // Toggle display block/hidden for mobile mechanics
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('flex');
            // Toggle overlay
            overlay.classList.toggle('hidden');
        }

        btn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
    </script>
</body>
</html>