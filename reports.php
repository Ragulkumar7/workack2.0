<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS Executive Suite</title>
    
    <!-- External Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-dark: #0f172a;
            --bg-card: #ffffff;
            --bg-body: #f1f5f9;
            --border: #e2e8f0;
            --text-main: #1e293b;
            --text-light: #94a3b8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden; /* Prevent body scroll */
        }

        /* --- Sidebar --- */
        .sidebar {
            width: 260px;
            background-color: var(--bg-dark);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            flex-shrink: 0;
            z-index: 10;
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #818cf8;
        }

        .role-switcher {
            margin-bottom: 2rem;
        }

        .role-switcher label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            display: block;
        }

        .role-btn {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid transparent;
            color: var(--text-light);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
            font-size: 0.9rem;
        }

        .role-btn:hover { background: rgba(255,255,255,0.1); }
        .role-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }
        .role-btn i { margin-right: 10px; width: 20px; text-align: center; }

        /* --- Main Content --- */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0; /* Important for flex overflow prevention */
        }

        header {
            height: 70px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            flex-shrink: 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .dashboard-scroll {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
            width: 100%;
        }

        /* --- CEO Specific Layout (Your Requested Structure) --- */
        .ceo-layout {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            max-width: 100%;
            width: 100%;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .charts-row {
            display: grid;
            grid-template-columns: 3fr 1fr; /* 3:1 Ratio as requested */
            gap: 1.25rem;
        }

        /* --- General Card & Grid Styles (For CFO/Manager too) --- */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: auto auto;
            gap: 1.5rem;
            width: 100%;
        }
        
        .span-1 { grid-column: span 1; }
        .span-2 { grid-column: span 2; }
        .span-4 { grid-column: span 4; }

        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            min-width: 0; /* Prevents overflow */
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .card-title { font-size: 0.95rem; font-weight: 600; color: var(--secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-value { font-size: 1.9rem; font-weight: 700; color: var(--text-main); margin: 0.25rem 0 0.5rem 0; word-wrap: break-word;}
        
        .trend {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .trend.up { color: var(--success); }
        .trend.down { color: var(--danger); }

        /* Chart Containers */
        .chart-box {
            position: relative;
            height: 280px;
            width: 100%;
        }
        .chart-box-sm {
            height: 240px;
            width: 100%;
        }

        /* --- Tables --- */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            table-layout: auto;
        }
        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .data-table th {
            font-weight: 600;
            color: var(--secondary);
            background: #f8fafc;
            font-size: 0.85rem;
        }
        .data-table td { font-size: 0.9rem; }
        .data-table tr:hover { background-color: #f8fafc; }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .bg-green { background: #dcfce7; color: #166534; }
        .bg-red { background: #fee2e2; color: #991b1b; }
        .bg-yellow { background: #fef3c7; color: #92400e; }
        .bg-blue { background: #dbeafe; color: #1e40af; }

        /* Buttons */
        .btn-action {
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-action:hover { background-color: var(--primary-dark); }
        .btn-export { background-color: #10b981; }
        .btn-export:hover { background-color: #059669; }

        /* --- Tabs --- */
        .tabs-container {
            margin-top: 1.5rem;
        }
        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-header::before {
            content: '';
            display: block;
            width: 4px;
            height: 24px;
            background: var(--primary);
            border-radius: 2px;
        }

        .tabs-nav {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid var(--border);
            margin-bottom: 1.5rem;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            color: var(--secondary);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab-btn:hover { color: var(--primary); }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .charts-row { grid-template-columns: 1fr; gap: 1rem; }
            .grid-container { grid-template-columns: repeat(2, 1fr); }
            .span-4, .span-3 { grid-column: span 2; }
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .grid-container { grid-template-columns: 1fr; }
            .span-1, .span-2, .span-3, .span-4 { grid-column: span 1; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-layer-group"></i> HRMS Portal
        </div>

        <div class="role-switcher">
            <label>Switch View</label>
            <button class="role-btn active" onclick="switchRole('CEO')">
                <i class="fa-solid fa-user-tie"></i> CEO / Founder
            </button>
            <button class="role-btn" onclick="switchRole('CFO')">
                <i class="fa-solid fa-file-invoice-dollar"></i> CFO / Accounts
            </button>
            <button class="role-btn" onclick="switchRole('MANAGER')">
                <i class="fa-solid fa-list-check"></i> Manager
            </button>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <header>
            <div class="header-title">
                <h2 id="pageTitle">Executive Overview</h2>
                <span id="pageSubtitle">Company-wide financial and growth metrics</span>
            </div>
            <div class="user-profile">
                <div style="text-align: right; margin-right: 5px;">
                    <div style="font-weight: 600; font-size: 0.9rem;" id="userName">Alex Morgan</div>
                    <div style="font-size: 0.8rem; color: var(--secondary);" id="userRole">Administrator</div>
                </div>
                <div class="avatar" id="userAvatar">AM</div>
            </div>
        </header>

        <div class="dashboard-scroll">
            <!-- Content injected via JS -->
            <div id="dashboardGrid"></div>
        </div>
    </main>

    <script>
        // --- Configuration & Data Models ---
        
        const config = {
            CEO: {
                title: "Executive Overview",
                subtitle: "Profit & Loss, Expenses, and Overall Growth",
                user: "Alex Morgan",
                role: "CEO",
                color: "#4f46e5",
                cards: [
                    { title: "Total Revenue", value: "$25,340", trend: "+19.01%", up: true, icon: "fa-dollar-sign" },
                    { title: "Total Expenses", value: "$45,221", trend: "+5.4%", up: false, icon: "fa-money-bill-wave" },
                    { title: "Net Profit", value: "-$19,881", trend: "-12.3%", up: false, icon: "fa-chart-line" },
                    { title: "Active Users", value: "800", trend: "+20.01%", up: true, icon: "fa-users" }
                ],
                tabs: [
                    { id: 'accounts', label: 'Accounts Team', headers: ['Invoice ID', 'Client', 'Amount', 'Status', 'Date'],
                      rows: [["Inv-001", "Michael Walker", "$1,200.00", "Paid", "2023-10-01"], ["Inv-002", "Sarah Connor", "$3,450.00", "Pending", "2023-10-02"], ["Inv-003", "CyberDyne", "$12,000.00", "Overdue", "2023-09-28"], ["Inv-004", "Stark Ind", "$5,600.00", "Paid", "2023-10-05"], ["Inv-005", "Wayne Ent", "$8,900.00", "Paid", "2023-10-06"]] },
                    { id: 'sales', label: 'Sales Team', headers: ['Lead ID', 'Client Name', 'Deal Value', 'Stage', 'Sales Rep'],
                      rows: [["L-101", "Globex Corp", "$50,000", "Negotiation", "John Doe"], ["L-102", "Soylent Corp", "$15,000", "Proposal", "Jane Smith"], ["L-103", "Initech", "$8,500", "Qualified", "Mike Ross"], ["L-104", "Umbrella Corp", "$120,000", "Closed Won", "Harvey S."]] },
                    { id: 'management', label: 'Management / Projects', headers: ['Project ID', 'Project Name', 'Manager', 'Progress', 'Deadline'],
                      rows: [["PRJ-101", "HRMS Migration", "David Chen", "85%", "2023-11-15"], ["PRJ-102", "Website Overhaul", "Sarah Lee", "40%", "2023-12-01"], ["PRJ-103", "Q4 Marketing", "Emily Blunt", "95%", "2023-10-30"]] },
                    { id: 'leads', label: 'Team Leads', headers: ['Lead Name', 'Team Size', 'Daily Tasks', 'Avg Completion', 'Status'],
                      rows: [["Anthony Lewis", "12", "45/50", "90%", "Active"], ["Brian Villalobos", "8", "30/32", "94%", "Active"], ["Amanda Foster", "15", "20/50", "40%", "At Risk"]] },
                    { id: 'hr', label: 'HR & Employees', headers: ['Emp ID', 'Name', 'Department', 'Join Date', 'Status'],
                      rows: [["E-001", "Alice Wonderland", "Engineering", "2021-05-12", "Active"], ["E-002", "Bob Builder", "Construction", "2022-01-10", "Active"], ["E-003", "Charlie Brown", "Marketing", "2023-03-15", "On Leave"], ["E-004", "Dora Explorer", "Research", "2023-09-01", "Probation"]] },
                    { id: 'marketing', label: 'Digital Marketing', headers: ['Campaign', 'Channel', 'Budget Spent', 'ROI', 'Clicks'],
                      rows: [["Fall Sale", "Google Ads", "$2,500", "320%", "1,200"], ["Social Push", "LinkedIn", "$1,000", "150%", "850"], ["Email Blast", "Mailchimp", "$200", "450%", "2,100"]] }
                ]
            },
            CFO: {
                title: "Finance & Accounts",
                subtitle: "Invoices, Payments, Payroll, and Expenses",
                user: "Sarah Lee",
                role: "CFO",
                color: "#059669",
                cards: [
                    { title: "Total Payments", value: "$45,221.45", trend: "Completed", up: true, icon: "fa-check-circle" },
                    { title: "Payment Success", value: "90%", trend: "+2% vs last mo", up: true, icon: "fa-credit-card" },
                    { title: "Pending Payroll", value: "$250,000", trend: "Due in 5 days", up: false, icon: "fa-clock" },
                    { title: "Total Expenses", value: "$45,221", trend: "Audit Required", up: false, icon: "fa-receipt" }
                ],
                charts: [
                    { id: "cfoPaymentChart", type: "doughnut", title: "Payments by Method", span: "span-2" },
                    { id: "cfoExpenseChart", type: "bar", title: "Monthly Expenses Breakdown", span: "span-2" },
                    { id: "cfoTable", type: "table", title: "Recent Invoices", span: "span-4" }
                ]
            },
            MANAGER: {
                title: "Management Reports",
                subtitle: "Project Progress, Tasks, and Team Productivity",
                user: "David Chen",
                role: "Product Manager",
                color: "#d97706",
                cards: [
                    { title: "Total Projects", value: "300", trend: "+12 New", up: true, icon: "fa-briefcase" },
                    { title: "Completed Tasks", value: "800", trend: "94% Rate", up: true, icon: "fa-clipboard-check" },
                    { title: "Pending Tasks", value: "125", trend: "Requires Action", up: false, icon: "fa-hourglass-half" },
                    { title: "Team Size", value: "600", trend: "Active", up: true, icon: "fa-people-group" }
                ],
                charts: [
                    { id: "managerProjectChart", type: "pie", title: "Project Status Distribution", span: "span-2" },
                    { id: "managerTaskChart", type: "bar", title: "Task Completion by Team", span: "span-2" },
                    { id: "managerTable", type: "table", title: "Active Projects List", span: "span-4" }
                ]
            }
        };

        // --- Mock Data for Tables (CFO & Manager) ---
        const tableData = {
            cfo: [
                { id: "Inv-001", client: "Michael Walker", company: "BrightWave Innovations", amount: "$1,200.00", status: "Paid" },
                { id: "Inv-002", client: "Sarah Connor", company: "CyberDyne Systems", amount: "$3,450.00", status: "Pending" },
                { id: "Inv-003", client: "Tony Stark", company: "Stark Ind", amount: "$12,000.00", status: "Overdue" }
            ],
            manager: [
                { id: "PRJ-101", name: "Hospital Administration", lead: "Anthony Lewis", progress: "85%", status: "In Progress" },
                { id: "PRJ-102", name: "Payment Gateway", lead: "Brian Villalobos", progress: "45%", status: "Pending" },
                { id: "PRJ-103", name: "HRMS Migration", lead: "Amanda Foster", progress: "100%", status: "Completed" }
            ]
        };

        // --- Core Application Logic ---

        let currentCharts = {}; // Store chart instances to destroy them later

        function switchRole(roleKey) {
            // Update Sidebar UI
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // Render Dashboard
            renderDashboard(roleKey);
        }

        function renderDashboard(roleKey) {
            const data = config[roleKey];
            const grid = document.getElementById('dashboardGrid');
            
            // 1. Update Header
            document.getElementById('pageTitle').innerText = data.title;
            document.getElementById('pageSubtitle').innerText = data.subtitle;
            document.getElementById('userName').innerText = data.user;
            document.getElementById('userRole').innerText = data.role;
            document.getElementById('userAvatar').style.backgroundColor = data.color;
            document.getElementById('userAvatar').innerText = data.user.split(' ').map(n=>n[0]).join('');

            // 2. Clear Grid & Old Charts
            grid.innerHTML = '';
            Object.values(currentCharts).forEach(chart => chart.destroy());
            currentCharts = {};

            if (roleKey === 'CEO') {
                // --- CEO SPECIFIC LAYOUT (YOUR REQUESTED STRUCTURE) ---
                grid.className = 'ceo-layout';
                
                grid.innerHTML = `
                    <div class="stats-row">
                        ${data.cards.map(card => `
                            <div class="card">
                                <div class="card-header">
                                    <span class="card-title">${card.title}</span>
                                    <i class="fa-solid ${card.icon}" style="color: var(--secondary); font-size: 1.4rem;"></i>
                                </div>
                                <div class="card-value">${card.value}</div>
                                <div class="trend ${card.up ? 'up' : 'down'}">
                                    <i class="fa-solid ${card.up ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}"></i>
                                    ${card.trend}
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    <div class="charts-row">
                        <div class="card">
                            <div class="card-header">
                                <span class="card-title">Financial Performance (Revenue vs Expense)</span>
                                <button class="btn-action" style="padding:4px 8px; font-size:0.7rem;">Export</button>
                            </div>
                            <div class="chart-box"><canvas id="ceoFinancialChart"></canvas></div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <span class="card-title">User Growth Rate</span>
                                <button class="btn-action" style="padding:4px 8px; font-size:0.7rem;">Export</button>
                            </div>
                            <div class="chart-box-sm"><canvas id="ceoGrowthChart"></canvas></div>
                        </div>
                    </div>

                    <div class="tabs-container">
                        <div class="section-header">Departmental Drill-Down</div>
                        <div class="tabs-nav">
                            ${data.tabs.map((tab, i) => `<button class="tab-btn ${i===0?'active':''}" onclick="openTab(event, '${tab.id}')">${tab.label}</button>`).join('')}
                        </div>
                        ${data.tabs.map((tab, i) => `
                            <div id="${tab.id}" class="tab-content ${i===0?'active':''}" style="display:${i===0?'block':'none'};">
                                <div class="card">
                                    <div class="card-header">
                                        <span class="card-title">${tab.label} Data</span>
                                        <button class="btn-action btn-export" onclick="exportTableToCSV('table-${tab.id}', '${tab.label}-Report.csv')">
                                            <i class="fa-solid fa-file-excel"></i> Export Excel
                                        </button>
                                    </div>
                                    <div style="overflow-x: auto; max-height: 300px;">
                                        <table class="data-table" id="table-${tab.id}">
                                            <thead><tr>${tab.headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                                            <tbody>
                                                ${tab.rows.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                // --- CFO & MANAGER LAYOUT (Standard Grid) ---
                grid.className = 'grid-container';

                // 3. Generate Stat Cards
                data.cards.forEach(card => {
                    const cardHTML = `
                        <div class="card span-1">
                            <div class="card-header">
                                <span class="card-title">${card.title}</span>
                                <i class="fa-solid ${card.icon}" style="color: var(--secondary);"></i>
                            </div>
                            <div class="card-value">${card.value}</div>
                            <div class="trend ${card.up ? 'up' : 'down'}">
                                <i class="fa-solid ${card.up ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}"></i>
                                ${card.trend}
                            </div>
                        </div>
                    `;
                    grid.innerHTML += cardHTML;
                });

                // 4. Generate Charts and Standard Tables
                data.charts.forEach(item => {
                    let contentHTML = '';

                    if (item.type === 'table') {
                        const rows = item.id.includes('cfo') ? tableData.cfo : tableData.manager;
                        contentHTML = `
                            <table class="data-table" id="${item.id}">
                                <thead>
                                    <tr>
                                        ${item.id.includes('cfo') ? '<th>Invoice ID</th><th>Client</th><th>Company</th><th>Amount</th><th>Status</th>' : '<th>Project ID</th><th>Project Name</th><th>Lead</th><th>Progress</th><th>Status</th>'}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rows.map(row => `
                                        <tr>
                                            ${item.id.includes('cfo') 
                                                ? `<td><b>${row.id}</b></td><td>${row.client}</td><td>${row.company}</td><td>${row.amount}</td>
                                                   <td><span class="status-badge ${getStatusColor(row.status)}">${row.status}</span></td>`
                                                : `<td><b>${row.id}</b></td><td>${row.name}</td><td>${row.lead}</td><td>${row.progress}</td>
                                                   <td><span class="status-badge ${getStatusColor(row.status)}">${row.status}</span></td>`
                                            }
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            <div style="margin-top: 1rem; text-align: right;">
                                <button class="btn-action btn-export" onclick="exportTableToCSV('${item.id}', '${item.title}.csv')">
                                    <i class="fa-solid fa-file-excel"></i> Export to Excel
                                </button>
                            </div>
                        `;
                    } else {
                        contentHTML = `<div class="chart-box-${item.type === 'pie' || item.type === 'doughnut' ? 'sm' : ''}"><canvas id="${item.id}"></canvas></div>`;
                    }

                    const chartCardHTML = `
                        <div class="card ${item.span}">
                            <div class="card-header">
                                <span class="card-title">${item.title}</span>
                                ${item.type === 'table' ? '' : '<button class="btn-action" style="margin:0; padding: 4px 8px; font-size: 0.7rem;">Export</button>'}
                            </div>
                            ${contentHTML}
                        </div>
                    `;
                    grid.innerHTML += chartCardHTML;
                });
            }

            // 6. Initialize Charts (Wait for DOM)
            setTimeout(() => initCharts(roleKey), 50);
        }

        // --- Tab Switching Logic (CEO) ---
        function openTab(evt, tabId) {
            // Hide all tab content
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
                tabContents[i].style.display = "none";
            }

            // Remove active class from buttons
            const tabBtns = document.getElementsByClassName("tab-btn");
            for (let i = 0; i < tabBtns.length; i++) {
                tabBtns[i].classList.remove("active");
            }

            // Show current tab and add active class to button
            document.getElementById(tabId).style.display = "block";
            // Small timeout to allow display:block to apply before opacity transition
            setTimeout(() => {
                document.getElementById(tabId).classList.add("active");
            }, 10);
            evt.currentTarget.classList.add("active");
        }

        // --- Excel Export Function ---
        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            if (!table) {
                console.error("Table not found: " + tableId);
                return;
            }

            let csv = [];
            const rows = table.querySelectorAll("tr");

            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (let j = 0; j < cols.length; j++) 
                    row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"'); // Escape quotes
                
                csv.push(row.join(","));        
            }

            // Download CSV
            downloadCSV(csv.join("\n"), filename);
        }

        function downloadCSV(csv, filename) {
            const csvFile = new Blob([csv], {type: "text/csv"});
            const downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        function getStatusColor(status) {
            if (status === 'Paid' || status === 'Completed') return 'bg-green';
            if (status === 'Pending' || status === 'In Progress') return 'bg-yellow';
            return 'bg-red';
        }

        // --- Chart Initialization Logic ---

        function initCharts(role) {
            const ctxDefaults = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            };

            if (role === 'CEO') {
                // Financial Line Chart
                currentCharts['ceoFinancialChart'] = new Chart(document.getElementById('ceoFinancialChart'), {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [
                            { label: 'Revenue', data: [20000, 22000, 21000, 24000, 23000, 25340], borderColor: '#4f46e5', tension: 0.4, fill: false },
                            { label: 'Expenses', data: [15000, 18000, 17000, 20000, 19000, 21000], borderColor: '#ef4444', tension: 0.4, fill: false }
                        ]
                    },
                    options: ctxDefaults
                });

                // User Growth Bar Chart
                currentCharts['ceoGrowthChart'] = new Chart(document.getElementById('ceoGrowthChart'), {
                    type: 'bar',
                    data: {
                        labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                        datasets: [{ label: 'New Users', data: [150, 200, 300, 150], backgroundColor: '#10b981' }]
                    },
                    options: { ...ctxDefaults, scales: { y: { beginAtZero: true } } }
                });
            }

            if (role === 'CFO') {
                // Payment Methods Doughnut
                currentCharts['cfoPaymentChart'] = new Chart(document.getElementById('cfoPaymentChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Paypal', 'Debit Card', 'Bank Transfer', 'Credit Card'],
                        datasets: [{ data: [30, 20, 40, 10], backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#6366f1'] }]
                    },
                    options: ctxDefaults
                });

                // Expense Bar
                currentCharts['cfoExpenseChart'] = new Chart(document.getElementById('cfoExpenseChart'), {
                    type: 'bar',
                    data: {
                        labels: ['Salaries', 'IT Equip', 'Office', 'Marketing'],
                        datasets: [{ label: 'Amount ($)', data: [250000, 45000, 12000, 30000], backgroundColor: '#059669' }]
                    },
                    options: { ...ctxDefaults, plugins: { legend: { display: false } } }
                });
            }

            if (role === 'MANAGER') {
                // Project Status Pie
                currentCharts['managerProjectChart'] = new Chart(document.getElementById('managerProjectChart'), {
                    type: 'pie',
                    data: {
                        labels: ['Completed', 'In Progress', 'Pending', 'New'],
                        datasets: [{ data: [250, 50, 50, 30], backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#64748b'] }]
                    },
                    options: ctxDefaults
                });

                // Task Completion Bar
                currentCharts['managerTaskChart'] = new Chart(document.getElementById('managerTaskChart'), {
                    type: 'bar',
                    data: {
                        labels: ['Design', 'Dev', 'QA', 'Sales'],
                        datasets: [
                            { label: 'Assigned', data: [50, 120, 40, 30], backgroundColor: '#cbd5e1' },
                            { label: 'Completed', data: [45, 90, 35, 20], backgroundColor: '#d97706' }
                        ]
                    },
                    options: ctxDefaults
                });
            }
        }

        // --- Init ---
        // Load CEO by default
        document.addEventListener('DOMContentLoaded', () => {
            renderDashboard('CEO');
        });

    </script>
</body>
</html>