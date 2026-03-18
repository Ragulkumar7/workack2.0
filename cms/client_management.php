<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack CMS | Clients & Demo Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        workack: {
                            light: '#d1fae5', 
                            DEFAULT: '#10b981', 
                            dark: '#047857', 
                            darker: '#064e3b', 
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal flex">

    <aside class="w-64 min-h-screen bg-workack-darker text-white shadow-lg flex flex-col sticky top-0">
        <div class="h-16 flex items-center justify-center border-b border-workack-dark">
            <h1 class="text-2xl font-bold tracking-wider text-workack-light"><i class="fas fa-leaf mr-2"></i> Workack</h1>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="cms_dashboard.php" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors">
                <i class="fas fa-chart-line w-6"></i> Dashboard
            </a>
            <a href="clients_management.php" class="block px-4 py-2 bg-workack-dark rounded-md transition-colors">
                <i class="fas fa-users w-6"></i> Customers & Signups
            </a>
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
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 sticky top-0">
            <h2 class="text-xl font-semibold text-gray-800">Client & Signup Management</h2>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 border-l pl-4">
                    <div class="w-8 h-8 bg-workack rounded-full flex items-center justify-center text-white font-bold">A</div>
                    <span class="text-sm font-medium text-gray-700">Admin</span>
                </div>
            </div>
        </header>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Paid Clients</p>
                            <p class="text-2xl font-bold text-gray-800">856</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-crown"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Active Demo Users</p>
                            <p class="text-2xl font-bold text-gray-800">142</p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 text-xl">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Monthly Revenue</p>
                            <p class="text-2xl font-bold text-gray-800">₹1.2L</p>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-xl">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Expiring Soon</p>
                            <p class="text-2xl font-bold text-gray-800">08</p>
                        </div>
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600 text-xl">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="flex border-b">
                    <button onclick="switchTab('clients')" id="tab-clients" class="px-6 py-4 text-sm font-medium border-b-2 border-workack text-workack">
                        <i class="fas fa-briefcase mr-2"></i> Paid Clients
                    </button>
                    <button onclick="switchTab('demos')" id="tab-demos" class="px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-workack">
                        <i class="fas fa-vial mr-2"></i> Demo Users
                    </button>
                </div>

                <div id="content-clients" class="block p-4 overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-4 py-3">Client / Company</th>
                                <th class="px-4 py-3">Signup Date</th>
                                <th class="px-4 py-3">Subscription</th>
                                <th class="px-4 py-3">Payment Details</th>
                                <th class="px-4 py-3">Expiry Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-gray-800">Neoera Infotech</div>
                                    <div class="text-xs text-gray-500">contact@neoera.in</div>
                                </td>
                                <td class="px-4 py-4 text-gray-600">Jan 10, 2026</td>
                                <td class="px-4 py-4">
                                    <span class="px-2 py-1 bg-emerald-50 text-emerald-700 rounded text-xs font-semibold border border-emerald-100">Annual</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-gray-800">₹85,000</div>
                                    <div class="text-[10px] text-gray-400">TXN: #992812</div>
                                </td>
                                <td class="px-4 py-4 font-semibold text-gray-700">Jan 09, 2027</td>
                                <td class="px-4 py-4">
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold uppercase tracking-wider">Active</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button class="text-gray-400 hover:text-workack mr-3"><i class="fas fa-edit"></i></button>
                                    <button class="text-gray-400 hover:text-red-500"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="content-demos" class="hidden p-4 overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-4 py-3">User / Email</th>
                                <th class="px-4 py-3">Username</th>
                                <th class="px-4 py-3">Signup Date</th>
                                <th class="px-4 py-3">Trial Expiry</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-gray-800">Mark Smith</div>
                                    <div class="text-xs text-gray-500">mark.smith@gmail.com</div>
                                </td>
                                <td class="px-4 py-4 text-gray-600 font-mono">mark_demo99</td>
                                <td class="px-4 py-4 text-gray-600">Mar 15, 2026</td>
                                <td class="px-4 py-4 font-medium text-red-600 italic">Expires in 2 hours</td>
                                <td class="px-4 py-4 text-center">
                                    <button class="bg-workack text-white px-3 py-1 rounded text-xs hover:bg-workack-dark shadow-sm">
                                        Upgrade to Paid
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tab) {
            const clientTab = document.getElementById('tab-clients');
            const demoTab = document.getElementById('tab-demos');
            const clientContent = document.getElementById('content-clients');
            const demoContent = document.getElementById('content-demos');

            if (tab === 'clients') {
                clientTab.classList.add('border-workack', 'text-workack');
                clientTab.classList.remove('border-transparent', 'text-gray-500');
                demoTab.classList.remove('border-workack', 'text-workack');
                demoTab.classList.add('border-transparent', 'text-gray-500');
                clientContent.classList.remove('hidden');
                demoContent.classList.add('hidden');
            } else {
                demoTab.classList.add('border-workack', 'text-workack');
                demoTab.classList.remove('border-transparent', 'text-gray-500');
                clientTab.classList.remove('border-workack', 'text-workack');
                clientTab.classList.add('border-transparent', 'text-gray-500');
                demoContent.classList.remove('hidden');
                clientContent.classList.add('hidden');
            }
        }
    </script>
</body>
</html>