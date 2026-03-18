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
    
     <?php include 'sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 sticky top-0">
            <h2 class="text-xl font-semibold text-gray-800">Client & Signup Management</h2>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 border-l pl-4 cursor-pointer">
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
                            <p class="text-2xl font-bold text-gray-800" id="stat-paid">856</p>
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
                            <tr id="row-client-1">
                                <td class="px-4 py-4">
                                    <div class="font-bold text-gray-800">Neoera Infotech</div>
                                    <div class="text-xs text-gray-500">contact@neoera.in</div>
                                </td>
                                <td class="px-4 py-4 text-gray-600">Jan 10, 2026</td>
                                <td class="px-4 py-4"><span class="px-2 py-1 bg-emerald-50 text-emerald-700 rounded text-xs font-semibold border border-emerald-100">Annual</span></td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-gray-800">₹85,000</div>
                                    <div class="text-[10px] text-gray-400">TXN: #992812</div>
                                </td>
                                <td class="px-4 py-4 font-semibold text-gray-700">Jan 09, 2027</td>
                                <td class="px-4 py-4"><span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold uppercase tracking-wider">Active</span></td>
                                <td class="px-4 py-4 text-center">
                                    <button onclick="viewClient('Neoera Infotech', 'contact@neoera.in', 'Jan 10, 2026', 'Annual', '₹85,000', 'Jan 09, 2027')" class="text-gray-400 hover:text-workack mr-3"><i class="fas fa-eye"></i></button>
                                    <button onclick="openDeleteModal('row-client-1', 'Neoera Infotech')" class="text-gray-400 hover:text-red-500"><i class="fas fa-trash"></i></button>
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
                                    <button class="bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-3 py-1 rounded text-xs font-medium transition-colors">
                                        <i class="fas fa-bell mr-1"></i> Send Alert
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transition-all transform duration-200">
            <div class="bg-workack-darker p-4 text-white flex justify-between items-center">
                <h3 class="text-lg font-bold">Client Detailed View</h3>
                <button onclick="closeModal('viewModal')" class="text-white hover:text-red-300"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Company Name</label>
                        <input type="text" id="view-name" class="w-full mt-1 p-2 bg-gray-100 border rounded text-sm font-semibold outline-none" readonly>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Email</label>
                        <input type="text" id="view-email" class="w-full mt-1 p-2 bg-gray-100 border rounded text-sm font-semibold outline-none" readonly>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Signup Date</label>
                        <input type="text" id="view-signup" class="w-full mt-1 p-2 bg-gray-100 border rounded text-sm font-semibold outline-none" readonly>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Subscription</label>
                        <input type="text" id="view-sub" class="w-full mt-1 p-2 bg-gray-100 border rounded text-sm font-semibold outline-none" readonly>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Payment</label>
                        <input type="text" id="view-payment" class="w-full mt-1 p-2 bg-gray-100 border rounded text-sm font-semibold outline-none" readonly>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Expiry Date</label>
                        <input type="text" id="view-expiry" class="w-full mt-1 p-2 bg-gray-100 border rounded text-sm font-semibold text-red-600 outline-none" readonly>
                    </div>
                </div>
                <div class="pt-6 flex justify-end">
                    <button onclick="closeModal('viewModal')" class="bg-gray-200 text-gray-700 px-6 py-2 rounded font-semibold hover:bg-gray-300">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-sm overflow-hidden transform transition-all">
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Confirm Removal</h3>
                <p class="text-gray-500 text-sm mb-6">Are you sure you want to remove <span id="delete-client-name" class="font-bold text-gray-700"></span>? This action is permanent.</p>
                <div class="flex space-x-3">
                    <button onclick="closeModal('deleteModal')" class="flex-1 bg-gray-100 text-gray-600 py-2 rounded-md font-semibold hover:bg-gray-200">Cancel</button>
                    <button id="confirm-delete-btn" class="flex-1 bg-red-600 text-white py-2 rounded-md font-semibold hover:bg-red-700 transition shadow-lg">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentTargetRow = '';

        function switchTab(tab) {
            const clientTab = document.getElementById('tab-clients');
            const demoTab = document.getElementById('tab-demos');
            const clientContent = document.getElementById('content-clients');
            const demoContent = document.getElementById('content-demos');

            if (tab === 'clients') {
                clientTab.className = "px-6 py-4 text-sm font-medium border-b-2 border-workack text-workack";
                demoTab.className = "px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-workack";
                clientContent.classList.remove('hidden');
                demoContent.classList.add('hidden');
            } else {
                demoTab.className = "px-6 py-4 text-sm font-medium border-b-2 border-workack text-workack";
                clientTab.className = "px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-workack";
                demoContent.classList.remove('hidden');
                clientContent.classList.add('hidden');
            }
        }

        function viewClient(name, email, signup, sub, payment, expiry) {
            document.getElementById('view-name').value = name;
            document.getElementById('view-email').value = email;
            document.getElementById('view-signup').value = signup;
            document.getElementById('view-sub').value = sub;
            document.getElementById('view-payment').value = payment;
            document.getElementById('view-expiry').value = expiry;
            document.getElementById('viewModal').classList.remove('hidden');
        }

        // Open custom delete modal
        function openDeleteModal(rowId, clientName) {
            currentTargetRow = rowId;
            document.getElementById('delete-client-name').innerText = clientName;
            document.getElementById('deleteModal').classList.remove('hidden');
            
            // Set up the actual delete button action
            document.getElementById('confirm-delete-btn').onclick = function() {
                executeDelete();
            };
        }

        function executeDelete() {
            const row = document.getElementById(currentTargetRow);
            if (row) {
                row.classList.add('opacity-0', 'duration-300');
                setTimeout(() => {
                    row.remove();
                    // Update stat counter
                    const stat = document.getElementById('stat-paid');
                    stat.innerText = (parseInt(stat.innerText) - 1);
                }, 300);
            }
            closeModal('deleteModal');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
    </script>
</body>
</html>