<?php
// Sample Licenses data
$licenses = [
    [
        'id' => 'L-1001',
        'customer' => 'TechCorp Inc.',
        'email' => 'admin@techcorp.com',
        'key' => 'WRK-99A2-B4X1-77C3',
        'plan' => 'Enterprise',
        'issued_date' => '10/03/2026',
        'status' => 'Active',
        'status_color' => 'bg-green-100 text-green-600 border-green-200'
    ],
    [
        'id' => 'L-1002',
        'customer' => 'Jane Doe',
        'email' => 'jane.doe@gmail.com',
        'key' => 'WRK-45B8-M2Z9-11Q7',
        'plan' => 'Pro',
        'issued_date' => '15/03/2026',
        'status' => 'Expiring Soon',
        'status_color' => 'bg-yellow-100 text-yellow-600 border-yellow-200'
    ],
    [
        'id' => 'L-1003',
        'customer' => 'Global Systems',
        'email' => 'contact@globalsys.io',
        'key' => 'WRK-88Y5-K9N2-33P4',
        'plan' => 'Basic',
        'issued_date' => '01/01/2026',
        'status' => 'Active',
        'status_color' => 'bg-green-100 text-green-600 border-green-200'
    ],
    [
        'id' => 'L-1004',
        'customer' => 'Acme Corp.',
        'email' => 'billing@acmecorp.net',
        'key' => 'WRK-22D4-F6G8-99L1',
        'plan' => 'Demo',
        'issued_date' => '17/03/2026',
        'status' => 'Expired',
        'status_color' => 'bg-red-100 text-red-600 border-red-200'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack CMS | License Management</title>
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
<body class="bg-gray-100 font-sans leading-normal tracking-normal flex h-screen overflow-hidden">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto relative bg-gray-50">
        
        <?php include 'header.php'; ?>

        <div id="successAlert" class="hidden mx-6 mt-6 bg-green-50 border-l-4 border-green-500 p-4 rounded shadow-sm flex items-center justify-between transition-all duration-300">
            <div class="flex items-center">
                <div class="text-green-500 mr-3 text-xl"><i class="fas fa-check-circle"></i></div>
                <div>
                    <p class="text-green-800 font-bold text-sm" id="alertTitle">Action Successful!</p>
                    <p class="text-green-600 text-xs mt-0.5" id="alertMessage">The license key has been sent.</p>
                </div>
            </div>
            <button onclick="closeAlert()" class="text-green-600 hover:text-green-800"><i class="fas fa-times"></i></button>
        </div>

        <div class="p-6 flex-grow">
            <div class="mb-6 flex justify-between items-center">
                <h2 class="text-2xl font-semibold text-gray-800">License Management</h2>
                <button onclick="openGenerateModal()" class="bg-workack hover:bg-workack-dark text-white px-4 py-2 rounded-md shadow transition-colors text-sm">
                    <i class="fas fa-key mr-1"></i> Generate New License
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Licenses</p>
                            <p class="text-2xl font-bold text-gray-800">856</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Active Licenses</p>
                            <p class="text-2xl font-bold text-gray-800">712</p>
                        </div>
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-green-600 text-xl">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Expiring Soon</p>
                            <p class="text-2xl font-bold text-gray-800">45</p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 text-xl">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Expired/Revoked</p>
                            <p class="text-2xl font-bold text-gray-800">99</p>
                        </div>
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600 text-xl">
                            <i class="fas fa-ban"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Issued Licenses</h3>
                    <div class="flex items-center space-x-2">
                        <div class="flex items-center bg-gray-50 rounded-md px-2 py-1 border border-gray-200">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                            <input type="text" id="searchInput" onkeyup="filterLicenses()" placeholder="Filter table..." class="bg-transparent border-none outline-none ml-2 text-xs w-48">
                        </div>
                        <button class="text-xs text-gray-500 hover:text-workack"><i class="fas fa-filter mr-1"></i> Filter</button>
                    </div>
                </div>
                
                <div class="p-0 overflow-x-auto">
                    <table class="w-full text-left text-sm table-auto">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wider">
                                <th class="py-3 px-6 font-semibold">Customer Details</th>
                                <th class="py-3 px-6 font-semibold">License Key</th>
                                <th class="py-3 px-6 font-semibold">Plan</th>
                                <th class="py-3 px-6 font-semibold">Issued On</th>
                                <th class="py-3 px-6 font-semibold">Status</th>
                                <th class="py-3 px-6 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="licenseTableBody">
                            <?php foreach ($licenses as $license): ?>
                            <tr class="border-b last:border-0 hover:bg-gray-50 transition-colors duration-150 license-row"
                                id="row-<?php echo $license['id']; ?>"
                                data-customer="<?php echo htmlspecialchars($license['customer']); ?>"
                                data-email="<?php echo htmlspecialchars($license['email']); ?>"
                                data-key="<?php echo htmlspecialchars($license['key']); ?>">
                                
                                <td class="py-4 px-6">
                                    <p class="font-bold text-gray-800"><?php echo $license['customer']; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $license['email']; ?></p>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center">
                                        <span class="bg-gray-100 text-gray-700 font-mono text-xs px-2 py-1 rounded border border-gray-200 mr-2 tracking-wide"><?php echo $license['key']; ?></span>
                                        <button onclick="copyToClipboard('<?php echo $license['key']; ?>')" class="text-gray-400 hover:text-workack transition-colors" title="Copy Key"><i class="far fa-copy"></i></button>
                                    </div>
                                </td>
                                <td class="py-4 px-6 font-medium text-gray-700"><?php echo $license['plan']; ?></td>
                                <td class="py-4 px-6 text-gray-600 text-xs"><i class="far fa-calendar-alt mr-1"></i> <?php echo $license['issued_date']; ?></td>
                                <td class="py-4 px-6">
                                    <span class="<?php echo $license['status_color']; ?> px-2.5 py-1 rounded text-xs font-bold border">
                                        <?php echo $license['status']; ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center text-gray-400 space-x-3 action-cell">
                                    <button onclick="resendEmail('<?php echo $license['email']; ?>', '<?php echo $license['key']; ?>')" class="hover:text-blue-500 transition-colors" title="Resend Email"><i class="far fa-envelope"></i></button>
                                    <button onclick="revokeLicense('<?php echo $license['id']; ?>')" class="hover:text-red-500 transition-colors" title="Revoke License"><i class="fas fa-ban"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center text-sm text-gray-500 bg-white rounded-b-lg">
                    <div id="paginationInfo">Showing 1 to 4 of 856 entries</div>
                    <div class="flex space-x-1" id="paginationControls">
                        </div>
                </div>
            </div>

        </div>
    </main>

    <div id="generateModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-[500px] max-w-[90%] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-800 text-lg">Generate New License</h3>
                <button onclick="closeModal('generateModal')" class="text-gray-400 hover:text-red-500"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6">
                <form id="generateForm" onsubmit="processGeneration(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                            <input type="text" id="genName" required placeholder="e.g., Tech Solutions LLC" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-workack focus:ring-1 focus:ring-workack">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" id="genEmail" required placeholder="contact@techsolutions.com" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-workack focus:ring-1 focus:ring-workack">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Plan / License Type</label>
                            <select id="genPlan" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-workack focus:ring-1 focus:ring-workack">
                                <option value="Basic">Basic Plan</option>
                                <option value="Pro">Pro Plan</option>
                                <option value="Enterprise">Enterprise Plan</option>
                                <option value="Demo">14-Day Demo</option>
                            </select>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-100 p-3 rounded-md flex items-start mt-2">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                            <p class="text-xs text-blue-700 leading-relaxed">
                                Clicking "Save" will create the license. You can then trigger the email from the table actions.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('generateModal')" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded shadow-sm hover:bg-gray-50 text-sm font-medium">Cancel</button>
                        <button type="submit" class="bg-workack text-white px-4 py-2 rounded shadow-sm hover:bg-workack-dark text-sm font-medium flex items-center">
                            Save License
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openGenerateModal() {
            document.getElementById('generateForm').reset();
            document.getElementById('generateModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function closeAlert() {
            document.getElementById('successAlert').classList.add('hidden');
        }

        function showAlert(title, message) {
            const alertBox = document.getElementById('successAlert');
            document.getElementById('alertTitle').innerText = title;
            document.getElementById('alertMessage').innerText = message;
            alertBox.classList.remove('hidden');
            setTimeout(() => { alertBox.classList.add('hidden'); }, 5000);
        }

        function generateRandomKey() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let key = 'WRK-';
            for (let i = 0; i < 3; i++) {
                let segment = '';
                for (let j = 0; j < 4; j++) { segment += chars.charAt(Math.floor(Math.random() * chars.length)); }
                key += segment + (i < 2 ? '-' : '');
            }
            return key;
        }

        // Form Submit - Generates key, adds to table with "Send" button
        function processGeneration(e) {
            e.preventDefault();
            
            const name = document.getElementById('genName').value;
            const email = document.getElementById('genEmail').value;
            const plan = document.getElementById('genPlan').value;
            
            const newKey = generateRandomKey();
            const today = new Date().toLocaleDateString('en-GB'); 
            const uniqueId = 'L-' + Math.floor(Math.random() * 10000);
            
            const newRow = document.createElement('tr');
            newRow.className = "border-b last:border-0 bg-workack-light transition-colors duration-1000 license-row";
            newRow.id = `row-${uniqueId}`;
            newRow.dataset.customer = name;
            newRow.dataset.email = email;
            newRow.dataset.key = newKey;
            
            newRow.innerHTML = `
                <td class="py-4 px-6">
                    <p class="font-bold text-gray-800">${name}</p>
                    <p class="text-xs text-gray-500">${email}</p>
                </td>
                <td class="py-4 px-6">
                    <div class="flex items-center">
                        <span class="bg-gray-100 text-gray-700 font-mono text-xs px-2 py-1 rounded border border-gray-200 mr-2 tracking-wide">${newKey}</span>
                        <button onclick="copyToClipboard('${newKey}')" class="text-gray-400 hover:text-workack transition-colors" title="Copy Key"><i class="far fa-copy"></i></button>
                    </div>
                </td>
                <td class="py-4 px-6 font-medium text-gray-700">${plan}</td>
                <td class="py-4 px-6 text-gray-600 text-xs"><i class="far fa-calendar-alt mr-1"></i> ${today}</td>
                <td class="py-4 px-6">
                    <span class="bg-yellow-100 text-yellow-600 border-yellow-200 px-2.5 py-1 rounded text-xs font-bold border">
                        Pending Sent
                    </span>
                </td>
                <td class="py-4 px-6 text-center action-cell" id="action-${uniqueId}">
                    <button onclick="initialSend('${uniqueId}', '${email}', '${newKey}')" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-semibold shadow-sm transition-colors">
                        <i class="fas fa-paper-plane mr-1"></i> Send
                    </button>
                </td>
            `;
            
            const tbody = document.getElementById('licenseTableBody');
            tbody.insertBefore(newRow, tbody.firstChild);
            
            setTimeout(() => {
                newRow.classList.remove('bg-workack-light');
                newRow.classList.add('hover:bg-gray-50');
            }, 1500);

            closeModal('generateModal');
            showAlert("License Created", `License key for ${name} has been generated. Click 'Send' in the table to email it.`);
            
            allRows = Array.from(document.querySelectorAll('.license-row'));
            applyPaginationAndFilter();
        }

        // --- Initial Send Logic (Changes state to standard icons) ---
        function initialSend(rowId, email, key) {
            // Update the actions cell
            const actionCell = document.getElementById(`action-${rowId}`);
            actionCell.innerHTML = `
                <button onclick="resendEmail('${email}', '${key}')" class="text-gray-400 hover:text-blue-500 transition-colors mr-3" title="Resend Email"><i class="far fa-envelope"></i></button>
                <button onclick="revokeLicense('${rowId}')" class="text-gray-400 hover:text-red-500 transition-colors" title="Revoke License"><i class="fas fa-ban"></i></button>
            `;

            // Update the status badge
            const row = document.getElementById(`row-${rowId}`);
            const tdStatus = row.cells[4]; 
            tdStatus.innerHTML = `
                <span class="bg-green-100 text-green-600 border-green-200 px-2.5 py-1 rounded text-xs font-bold border">
                    Active
                </span>
            `;

            showAlert("Email Sent!", `License key ${key} has been successfully sent to ${email}.`);
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('License Key copied to clipboard: ' + text);
            });
        }

        function resendEmail(email, key) {
            if(confirm(`Are you sure you want to resend the license key to ${email}?`)) {
                showAlert("Email Resent!", `License key ${key} has been successfully resent to ${email}.`);
            }
        }

        function revokeLicense(rowId) {
            if(confirm("Are you sure you want to completely remove/revoke this license? This action cannot be undone.")) {
                const row = document.getElementById('row-' + rowId);
                
                row.style.transition = "all 0.5s ease";
                row.style.opacity = "0";
                row.style.transform = "translateX(20px)";
                
                setTimeout(() => {
                    row.remove();
                    allRows = Array.from(document.querySelectorAll('.license-row'));
                    applyPaginationAndFilter();
                    showAlert("License Revoked", "The license has been successfully removed from the system.");
                }, 500);
            }
        }

        // --- Search and Pagination Logic ---
        let currentPage = 1;
        const itemsPerPage = 4; 
        let allRows = [];

        document.addEventListener('DOMContentLoaded', () => {
            allRows = Array.from(document.querySelectorAll('.license-row'));
            applyPaginationAndFilter();
        });

        function applyPaginationAndFilter() {
            // Check if global search from header exists, otherwise fallback to local search
            const globalSearch = document.getElementById("globalSearchInput");
            const localSearch = document.getElementById("searchInput");
            
            let input = "";
            if(localSearch && localSearch.value) input = localSearch.value.toLowerCase();
            else if(globalSearch && globalSearch.value) input = globalSearch.value.toLowerCase();
            
            const filteredRows = allRows.filter(row => {
                const customer = row.dataset.customer.toLowerCase();
                const email = row.dataset.email.toLowerCase();
                const key = row.dataset.key.toLowerCase();
                return customer.includes(input) || email.includes(input) || key.includes(input);
            });

            const totalEntries = filteredRows.length;
            const totalPages = Math.ceil(totalEntries / itemsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * itemsPerPage;
            const endIdx = startIdx + itemsPerPage;

            allRows.forEach(row => row.style.display = 'none');

            filteredRows.forEach((row, idx) => {
                if (idx >= startIdx && idx < endIdx) {
                    row.style.display = 'table-row'; 
                }
            });

            const displayingStart = totalEntries === 0 ? 0 : startIdx + 1;
            const displayingEnd = Math.min(endIdx, totalEntries);
            document.getElementById("paginationInfo").innerText = `Showing ${displayingStart} to ${displayingEnd} of ${totalEntries} entries`;

            const controlsDiv = document.getElementById("paginationControls");
            let btnHtml = '';

            btnHtml += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50 disabled:opacity-50" ${currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    btnHtml += `<button onclick="goToPage(${i})" class="px-3 py-1 bg-workack text-white border border-workack rounded text-xs font-semibold">${i}</button>`;
                } else {
                    btnHtml += `<button onclick="goToPage(${i})" class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">${i}</button>`;
                }
            }

            btnHtml += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50 disabled:opacity-50" ${currentPage === totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;

            controlsDiv.innerHTML = btnHtml;
        }

        function filterLicenses() {
            currentPage = 1;
            applyPaginationAndFilter();
        }

        function goToPage(page) {
            currentPage = page;
            applyPaginationAndFilter();
        }

        // Bind global search if it exists
        document.addEventListener('DOMContentLoaded', () => {
            const globalSearch = document.getElementById("globalSearchInput");
            if(globalSearch) {
                globalSearch.addEventListener('keyup', filterLicenses);
            }
        });
    </script>
</body>
</html>