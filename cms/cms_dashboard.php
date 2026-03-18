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
<body class="bg-gray-100 font-sans leading-normal tracking-normal flex">

    <aside class="w-64 min-h-screen bg-workack-darker text-white shadow-lg flex flex-col">
        <div class="h-16 flex items-center justify-center border-b border-workack-dark">
            <h1 class="text-2xl font-bold tracking-wider text-workack-light"><i class="fas fa-leaf mr-2"></i> Workack</h1>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="#" class="block px-4 py-2 bg-workack-dark rounded-md transition-colors"><i class="fas fa-chart-line w-6"></i> Dashboard</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-users w-6"></i> Customers & Signups</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-ticket-alt w-6"></i> Tickets</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-key w-6"></i> Licenses</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-clock w-6"></i> Demo Users</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-wallet w-6"></i> Payments</a>
            <div class="pt-4 mt-4 border-t border-workack-dark">
                <p class="text-xs uppercase text-gray-400 font-semibold px-4 mb-2">HRMS</p>
                <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-user-tie w-6"></i> Employees</a>
                <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-calendar-alt w-6"></i> Attendance</a>
            </div>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10">
            <div class="flex items-center bg-gray-100 rounded-md px-3 py-2 w-96">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" placeholder="Search tickets, users, or licenses..." class="bg-transparent border-none outline-none ml-2 w-full text-sm">
            </div>
            <div class="flex items-center space-x-4">
                <button class="text-gray-500 hover:text-workack"><i class="fas fa-bell"></i></button>
                <div class="flex items-center space-x-2 border-l pl-4 cursor-pointer">
                    <div class="w-8 h-8 bg-workack rounded-full flex items-center justify-center text-white font-bold">A</div>
                    <span class="text-sm font-medium text-gray-700">Admin</span>
                </div>
            </div>
        </header>

        <div class="p-6">
            <div class="mb-6 flex justify-between items-center">
                <h2 class="text-2xl font-semibold text-gray-800">Overview</h2>
                <button class="bg-workack hover:bg-workack-dark text-white px-4 py-2 rounded-md shadow transition-colors text-sm">
                    <i class="fas fa-plus mr-1"></i> Generate License Key
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Customers</p>
                            <p class="text-2xl font-bold text-gray-800">1,248</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-800">$45,231</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Sales</p>
                            <p class="text-2xl font-bold text-gray-800">856</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Demo Users</p>
                            <p class="text-2xl font-bold text-gray-800">142</p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 text-xl">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-semibold text-gray-800">Application Tickets</h3>
                        <a href="#" class="text-sm text-workack hover:underline">View All</a>
                    </div>
                    <div class="p-4">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 border-b">
                                    <th class="pb-2 font-medium">Customer</th>
                                    <th class="pb-2 font-medium">Issue</th>
                                    <th class="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b last:border-0">
                                    <td class="py-3">TechCorp Inc.</td>
                                    <td class="py-3">Login Session Expired Bug</td>
                                    <td class="py-3"><span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-medium">Open</span></td>
                                </tr>
                                <tr class="border-b last:border-0">
                                    <td class="py-3">Jane Doe</td>
                                    <td class="py-3">License Key Not Received</td>
                                    <td class="py-3"><span class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-xs font-medium">Pending</span></td>
                                </tr>
                                <tr class="border-b last:border-0">
                                    <td class="py-3">Global Systems</td>
                                    <td class="py-3">Payment Gateway Error</td>
                                    <td class="py-3"><span class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs font-medium">Resolved</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-semibold text-gray-800">Demo Limits & Licenses</h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Mark Smith (Demo)</p>
                                <p class="text-xs text-gray-500">Time limit expires in 2 hours</p>
                            </div>
                            <span class="text-xs bg-red-100 text-red-600 px-2 py-1 rounded border border-red-200">Expiring Soon</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">WRK-99A2-B4X1</p>
                                <p class="text-xs text-gray-500">License sent to contact@acme.com</p>
                            </div>
                            <span class="text-xs bg-workack-light text-workack-dark px-2 py-1 rounded border border-workack">Sent</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Sarah Johnson</p>
                                <p class="text-xs text-gray-500">Session Expired Details Logged</p>
                            </div>
                            <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded border border-gray-300">Logged</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>