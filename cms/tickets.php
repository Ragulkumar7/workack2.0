<?php
// Sample ticket data - Easy to replace with your database fetch logic later
$tickets = [
    [
        'id' => 'T-5678',
        'customer' => 'TechCorp Inc.',
        'issue' => 'Login Session Expired Bug on mobile app',
        'agent' => 'Karthik Raja',
        'date' => '12/03/2026',
        'status' => 'Open',
        'status_color' => 'bg-red-100 text-red-600 border-red-200'
    ],
    [
        'id' => 'T-5677',
        'customer' => 'Jane Doe',
        'issue' => 'License Key Not Received for renewal',
        'agent' => 'Priya Sharma',
        'date' => '10/03/2026',
        'status' => 'Pending',
        'status_color' => 'bg-yellow-100 text-yellow-600 border-yellow-200'
    ],
    [
        'id' => 'T-5676',
        'customer' => 'Global Systems',
        'issue' => 'Payment Gateway Error on checkout',
        'agent' => 'Admin',
        'date' => '08/03/2026',
        'status' => 'Resolved',
        'status_color' => 'bg-green-100 text-green-600 border-green-200'
    ],
    [
        'id' => 'T-5675',
        'customer' => 'Acme Corp.',
        'issue' => 'Dashboard not loading for team members',
        'agent' => 'Karthik Raja',
        'date' => '05/03/2026',
        'status' => 'Resolved',
        'status_color' => 'bg-green-100 text-green-600 border-green-200'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack CMS | Ticket Management</title>
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

    <main class="flex-1 flex flex-col h-screen overflow-y-auto relative">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 sticky top-0">
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

        <div class="p-6 flex-grow">
            <div class="mb-6 flex justify-between items-center">
                <h2 class="text-2xl font-semibold text-gray-800">Ticket Management</h2>
                
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">2,345</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Open Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">89</p>
                        </div>
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600 text-xl">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Pending Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">112</p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 text-xl">
                            <i class="fas fa-hourglass-start"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Resolved Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">2,144</p>
                        </div>
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-green-600 text-xl">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Detailed Ticket Tracking</h3>
                    <div class="flex items-center space-x-2">
                        <div class="flex items-center bg-gray-50 rounded-md px-2 py-1 border border-gray-200">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                            <input type="text" id="searchInput" onkeyup="filterTickets()" placeholder="Filter tickets..." class="bg-transparent border-none outline-none ml-2 text-xs w-48">
                        </div>
                        <button class="text-xs text-gray-500 hover:text-workack"><i class="fas fa-filter mr-1"></i> Filter</button>
                    </div>
                </div>
                
                <div class="flex flex-col w-full" id="ticketTableBody">
                    <div class="flex bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-600">
                        <div class="w-1/2 px-6 py-3 border-r border-gray-200 flex items-center">
                            <i class="fas fa-bullhorn text-gray-400 mr-2"></i> Raised Details
                        </div>
                        <div class="w-1/2 px-6 py-3 flex items-center">
                            <i class="fas fa-user-shield text-gray-400 mr-2"></i> Assignment & Resolution
                        </div>
                    </div>

                    <?php foreach ($tickets as $ticket): ?>
                    <div class="flex w-full border-b last:border-0 hover:bg-gray-50 transition-colors duration-150 ticket-row" 
                         id="row-<?php echo $ticket['id']; ?>"
                         data-id="<?php echo $ticket['id']; ?>"
                         data-customer="<?php echo htmlspecialchars($ticket['customer']); ?>"
                         data-issue="<?php echo htmlspecialchars($ticket['issue']); ?>"
                         data-agent="<?php echo htmlspecialchars($ticket['agent']); ?>"
                         data-date="<?php echo $ticket['date']; ?>"
                         data-status="<?php echo $ticket['status']; ?>">
                        
                        <div class="w-1/2 px-6 py-5 border-r border-gray-200">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-sm font-bold text-gray-800 customer-text"><?php echo $ticket['customer']; ?></span>
                                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded font-mono border border-gray-200 id-text">
                                    <?php echo $ticket['id']; ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2 leading-relaxed">
                                <span class="font-medium text-gray-700">Issue:</span> <span class="issue-text"><?php echo $ticket['issue']; ?></span>
                            </p>
                            <p class="text-xs text-gray-400">
                                <i class="far fa-clock mr-1"></i> Raised on <span class="date-text"><?php echo $ticket['date']; ?></span>
                            </p>
                        </div>

                        <div class="w-1/2 px-6 py-5 flex flex-col justify-center">
                            <div class="flex justify-between items-center w-full">
                                
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-workack-light rounded-full flex items-center justify-center text-workack-dark mr-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-0.5">Assigned To</p>
                                        <p class="text-sm font-semibold text-gray-800 agent-text"><?php echo $ticket['agent']; ?></p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-4">
                                    <span class="status-badge <?php echo $ticket['status_color']; ?> px-3 py-1.5 rounded-full text-xs font-bold border">
                                        <?php echo $ticket['status']; ?>
                                    </span>
                                    
                                    <div class="border-l border-gray-200 pl-4 flex space-x-3 text-gray-400">
                                        <button onclick="openViewModal('<?php echo $ticket['id']; ?>')" class="hover:text-workack transition-colors" title="View Details"><i class="fas fa-eye"></i></button>
                                        <button onclick="openEditModal('<?php echo $ticket['id']; ?>')" class="hover:text-blue-500 transition-colors" title="Update Ticket"><i class="fas fa-edit"></i></button>
                                    </div>
                                </div>
                                
                            </div>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center text-sm text-gray-500 bg-white rounded-b-lg">
                    <div id="paginationInfo">Showing 1 to 4 of 2,345 entries</div>
                    <div class="flex space-x-1" id="paginationControls">
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50 disabled:opacity-50" disabled><i class="fas fa-chevron-left"></i></button>
                        <button class="px-3 py-1 bg-workack text-white border border-workack rounded text-xs font-semibold">1</button>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">2</button>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">3</button>
                        <span class="px-3 py-1 text-xs">...</span>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">587</button>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div id="viewModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-[500px] max-w-[90%] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-800 text-lg">Ticket Details <span id="viewId" class="text-workack ml-2 font-mono text-sm"></span></h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-red-500"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Customer</p>
                    <p id="viewCustomer" class="text-gray-800 font-medium"></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Issue Description</p>
                    <div class="bg-gray-50 p-3 rounded border border-gray-100">
                        <p id="viewIssue" class="text-gray-700 text-sm"></p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Assigned Agent</p>
                        <p id="viewAgent" class="text-gray-800"></p>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Status</p>
                        <span id="viewStatus" class="px-2 py-1 rounded text-xs font-bold border"></span>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Date Raised</p>
                    <p id="viewDate" class="text-gray-800 text-sm"></p>
                </div>
            </div>
            <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex justify-end">
                <button onclick="closeModal('viewModal')" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded shadow-sm hover:bg-gray-50 text-sm font-medium">Close</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-[500px] max-w-[90%] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-800 text-lg">Edit Ticket <span id="editIdDisplay" class="text-blue-500 ml-2 font-mono text-sm"></span></h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-red-500"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6">
                <form id="editTicketForm" onsubmit="saveTicket(event)">
                    <input type="hidden" id="editId">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Agent</label>
                            <input type="text" id="editAgent" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-workack focus:ring-1 focus:ring-workack">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="editStatus" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-workack focus:ring-1 focus:ring-workack">
                                <option value="Open">Open</option>
                                <option value="Pending">Pending</option>
                                <option value="Resolved">Resolved</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Issue Description</label>
                            <textarea id="editIssue" rows="3" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-workack focus:ring-1 focus:ring-workack"></textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editModal')" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded shadow-sm hover:bg-gray-50 text-sm font-medium">Cancel</button>
                        <button type="submit" class="bg-workack text-white px-4 py-2 rounded shadow-sm hover:bg-workack-dark text-sm font-medium">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Data & Pagination Settings
        let currentPage = 1;
        const itemsPerPage = 2; // Fixed to 2 to demonstrate pagination with 4 entries
        let allRows = [];

        document.addEventListener('DOMContentLoaded', () => {
            allRows = Array.from(document.querySelectorAll('.ticket-row'));
            applyPaginationAndFilter();
        });

        // Combined Search and Pagination Logic
        function applyPaginationAndFilter() {
            const input = document.getElementById("searchInput").value.toLowerCase();
            
            // 1. Filter rows based on search input
            const filteredRows = allRows.filter(row => {
                const customer = row.dataset.customer.toLowerCase();
                const issue = row.dataset.issue.toLowerCase();
                const id = row.dataset.id.toLowerCase();
                const agent = row.dataset.agent.toLowerCase();
                
                return customer.includes(input) || issue.includes(input) || id.includes(input) || agent.includes(input);
            });

            // 2. Calculate Pages
            const totalEntries = filteredRows.length;
            const totalPages = Math.ceil(totalEntries / itemsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * itemsPerPage;
            const endIdx = startIdx + itemsPerPage;

            // 3. Hide all rows
            allRows.forEach(row => row.style.display = 'none');

            // 4. Show only rows for current page
            filteredRows.forEach((row, idx) => {
                if (idx >= startIdx && idx < endIdx) {
                    row.style.display = 'flex';
                }
            });

            // 5. Update Pagination Info Text
            const displayingStart = totalEntries === 0 ? 0 : startIdx + 1;
            const displayingEnd = Math.min(endIdx, totalEntries);
            document.getElementById("paginationInfo").innerText = `Showing ${displayingStart} to ${displayingEnd} of ${totalEntries} entries`;

            // 6. Generate Pagination Buttons dynamically
            const controlsDiv = document.getElementById("paginationControls");
            let btnHtml = '';

            // Previous Button
            btnHtml += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50 disabled:opacity-50" ${currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;

            // Page Numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    btnHtml += `<button onclick="goToPage(${i})" class="px-3 py-1 bg-workack text-white border border-workack rounded text-xs font-semibold">${i}</button>`;
                } else {
                    btnHtml += `<button onclick="goToPage(${i})" class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">${i}</button>`;
                }
            }

            // Next Button
            btnHtml += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50 disabled:opacity-50" ${currentPage === totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;

            controlsDiv.innerHTML = btnHtml;
        }

        // Search trigger
        function filterTickets() {
            currentPage = 1; // Reset to page 1 on new search
            applyPaginationAndFilter();
        }

        // Page change trigger
        function goToPage(page) {
            currentPage = page;
            applyPaginationAndFilter();
        }

        // Color mapper for statuses
        const statusColors = {
            'Open': 'bg-red-100 text-red-600 border-red-200',
            'Pending': 'bg-yellow-100 text-yellow-600 border-yellow-200',
            'Resolved': 'bg-green-100 text-green-600 border-green-200'
        };

        // Modal Controls
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function openViewModal(id) {
            const row = document.getElementById('row-' + id);
            
            // Set values in view modal
            document.getElementById('viewId').innerText = row.dataset.id;
            document.getElementById('viewCustomer').innerText = row.dataset.customer;
            document.getElementById('viewIssue').innerText = row.dataset.issue;
            document.getElementById('viewAgent').innerText = row.dataset.agent;
            document.getElementById('viewDate').innerText = row.dataset.date;
            
            const statusBadge = document.getElementById('viewStatus');
            statusBadge.innerText = row.dataset.status;
            statusBadge.className = `px-2 py-1 rounded text-xs font-bold border ${statusColors[row.dataset.status]}`;

            document.getElementById('viewModal').classList.remove('hidden');
        }

        function openEditModal(id) {
            const row = document.getElementById('row-' + id);
            
            // Populate form fields
            document.getElementById('editId').value = row.dataset.id;
            document.getElementById('editIdDisplay').innerText = row.dataset.id;
            document.getElementById('editAgent').value = row.dataset.agent;
            document.getElementById('editStatus').value = row.dataset.status;
            document.getElementById('editIssue').value = row.dataset.issue;

            document.getElementById('editModal').classList.remove('hidden');
        }

        // Save Edit Form (Updates DOM instantly)
        function saveTicket(e) {
            e.preventDefault(); // Prevent page refresh
            
            const id = document.getElementById('editId').value;
            const newAgent = document.getElementById('editAgent').value;
            const newStatus = document.getElementById('editStatus').value;
            const newIssue = document.getElementById('editIssue').value;

            // Find the row and update its data attributes
            const row = document.getElementById('row-' + id);
            row.dataset.agent = newAgent;
            row.dataset.status = newStatus;
            row.dataset.issue = newIssue;

            // Update visible UI text
            row.querySelector('.agent-text').innerText = newAgent;
            row.querySelector('.issue-text').innerText = newIssue;
            
            // Update UI Status Badge
            const statusBadge = row.querySelector('.status-badge');
            statusBadge.innerText = newStatus;
            statusBadge.className = `status-badge ${statusColors[newStatus]} px-3 py-1.5 rounded-full text-xs font-bold border`;

            // Close the modal and show success alert (optional)
            closeModal('editModal');
            
            // Tiny animation effect to show it saved
            row.classList.add('bg-workack-light');
            setTimeout(() => {
                row.classList.remove('bg-workack-light');
            }, 800);

            // Re-apply filter and pagination to respect the updated data (e.g., if you edit and it no longer matches search)
            applyPaginationAndFilter();
        }
    </script>

</body>
</html>