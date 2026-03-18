<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack CMS | Payments & Revenue</title>
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

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 sticky top-0">
            <div class="flex items-center bg-gray-100 rounded-md px-3 py-2 w-96">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" placeholder="Search transactions, customers..." class="bg-transparent border-none outline-none ml-2 w-full text-sm">
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
                <h2 class="text-2xl font-semibold text-gray-800">Payments & Financial Overview</h2>
                <button class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-md shadow-sm transition-colors text-sm font-medium">
                    <i class="fas fa-download mr-1"></i> Export Report
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-800">₹45,231.00</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Net Profit</p>
                            <p class="text-2xl font-bold text-gray-800">₹38,150.00</p>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-xl">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">New Signups Revenue (This Month)</p>
                            <p class="text-2xl font-bold text-gray-800">₹4,200.00</p>
                        </div>
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 text-xl">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Sales (Transactions)</p>
                            <p class="text-2xl font-bold text-gray-800">856</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Active Paid Users</p>
                            <p class="text-2xl font-bold text-gray-800">714</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-user-check"></i>
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
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

            </div>

            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Recent Transactions</h3>
                    <div class="flex space-x-2">
                        <select class="bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-md px-3 py-1 outline-none">
                            <option>Last 7 Days</option>
                            <option>This Month</option>
                            <option>All Time</option>
                        </select>
                    </div>
                </div>
                <div class="p-0 overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-600 font-medium border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3">Transaction ID</th>
                                <th class="px-6 py-3">Customer (Who Paid)</th>
                                <th class="px-6 py-3">Plan / Type</th>
                                <th class="px-6 py-3">Amount (How Much)</th>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">#TRX-9982</td>
                                <td class="px-6 py-4 font-medium text-gray-800">Acme Corp <span class="ml-2 text-[10px] bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full">New Join</span></td>
                                <td class="px-6 py-4 text-gray-600">Enterprise Annual</td>
                                <td class="px-6 py-4 font-semibold text-gray-800">₹1,200.00</td>
                                <td class="px-6 py-4 text-gray-500">Mar 18, 2026</td>
                                <td class="px-6 py-4"><span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-medium">Completed</span></td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">#TRX-9981</td>
                                <td class="px-6 py-4 font-medium text-gray-800">Tech Solutions Ltd</td>
                                <td class="px-6 py-4 text-gray-600">Pro Monthly</td>
                                <td class="px-6 py-4 font-semibold text-gray-800">₹49.00</td>
                                <td class="px-6 py-4 text-gray-500">Mar 18, 2026</td>
                                <td class="px-6 py-4"><span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-medium">Completed</span></td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">#TRX-9980</td>
                                <td class="px-6 py-4 font-medium text-gray-800">Sarah Johnson</td>
                                <td class="px-6 py-4 text-gray-600">Basic Annual</td>
                                <td class="px-6 py-4 font-semibold text-gray-800">₹290.00</td>
                                <td class="px-6 py-4 text-gray-500">Mar 17, 2026</td>
                                <td class="px-6 py-4"><span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-medium">Processing</span></td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">#TRX-9979</td>
                                <td class="px-6 py-4 font-medium text-gray-800">Global Designs <span class="ml-2 text-[10px] bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full">New Join</span></td>
                                <td class="px-6 py-4 text-gray-600">Pro Annual</td>
                                <td class="px-6 py-4 font-semibold text-gray-800">₹490.00</td>
                                <td class="px-6 py-4 text-gray-500">Mar 17, 2026</td>
                                <td class="px-6 py-4"><span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-medium">Completed</span></td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">#TRX-9978</td>
                                <td class="px-6 py-4 font-medium text-gray-800">Nexus Industries</td>
                                <td class="px-6 py-4 text-gray-600">Enterprise Custom</td>
                                <td class="px-6 py-4 font-semibold text-gray-800">₹2,500.00</td>
                                <td class="px-6 py-4 text-gray-500">Mar 16, 2026</td>
                                <td class="px-6 py-4"><span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-medium">Failed</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 text-center rounded-b-lg">
                    <a href="#" class="text-sm font-medium text-workack hover:text-workack-dark transition-colors">View All Transactions <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>

        </div>
    </main>
</body>
</html>