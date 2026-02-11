<?php 
include 'sidebars.php'; 
?>
<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Overview - HRMS</title>

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

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main content shifts according to sidebar */
        #mainContent {
            margin-left: 95px; /* primary sidebar width */
            transition: margin-left 0.35s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 1024px) {
            #mainContent.secondary-visible {
                margin-left: 315px; /* primary (95px) + secondary (220px) */
            }
        }

        /* Header is sticky and stays inside mainContent */
        header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            
            /* Sticky positioning ensures it respects the sidebar margin automatically */
            position: sticky;
            top: 0;
            z-index: 90;
            width: 100%; 
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
            padding: 1.5rem 2rem;
            overflow-y: auto;
            flex: 1;
        }

        /* CEO Layout */
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

        /* UPDATED: Equal columns for graphs */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Changed from 3fr 1fr to equal */
            gap: 1.25rem;
        }

        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-value {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0.25rem 0 0.5rem 0;
        }

        .trend {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend.up { color: var(--success); }
        .trend.down { color: var(--danger); }

        /* Unified chart height */
        .chart-box {
            position: relative;
            height: 280px;
            width: 100%;
            flex: 1; /* Ensures canvas fills space */
        }

        /* Tabs & Tables */
        .tabs-container { margin-top: 1.5rem; }
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

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
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
        }

        .btn-action:hover { background-color: var(--primary-dark); }
        .btn-export { background-color: #10b981; }
        .btn-export:hover { background-color: #059669; }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            /* Stack charts on smaller screens */
            .charts-row { grid-template-columns: 1fr; gap: 1rem; }
        }

        @media (max-width: 1023px) {
            #mainContent, header {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div id="mainContent">

    <header>
        <div class="header-title">
            <h2 id="pageTitle">Executive Overview</h2>
            <span id="pageSubtitle">Company-wide financial and growth metrics</span>
        </div>
        <div class="user-profile">
            <div style="text-align: right; margin-right: 5px;">
                <div style="font-weight: 600; font-size: 0.9rem;" id="userName">Alex Morgan</div>
                <div style="font-size: 0.8rem; color: var(--secondary);" id="userRole">CEO / Founder</div>
            </div>
            <div class="avatar" id="userAvatar">AM</div>
        </div>
    </header>

    <div class="dashboard-scroll">
        <div id="dashboardGrid" class="ceo-layout"></div>
    </div>

</div>

<script>
    // CEO-only configuration
    const ceoConfig = {
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
    };

    let currentCharts = {};

    function renderCEODashboard() {
        const data = ceoConfig;
        const grid = document.getElementById('dashboardGrid');

        document.getElementById('pageTitle').innerText = data.title;
        document.getElementById('pageSubtitle').innerText = data.subtitle;
        document.getElementById('userName').innerText = data.user;
        document.getElementById('userRole').innerText = data.role;
        document.getElementById('userAvatar').style.backgroundColor = data.color;
        document.getElementById('userAvatar').innerText = data.user.split(' ').map(n => n[0]).join('');

        grid.innerHTML = '';
        Object.values(currentCharts).forEach(chart => chart?.destroy());
        currentCharts = {};

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
                    <div class="chart-box"><canvas id="ceoGrowthChart"></canvas></div>
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

        // Initialize charts
        setTimeout(() => {
            currentCharts['ceoFinancialChart'] = new Chart(document.getElementById('ceoFinancialChart'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        { label: 'Revenue', data: [20000, 22000, 21000, 24000, 23000, 25340], borderColor: '#4f46e5', tension: 0.4, fill: false },
                        { label: 'Expenses', data: [15000, 18000, 17000, 20000, 19000, 21000], borderColor: '#ef4444', tension: 0.4, fill: false }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            currentCharts['ceoGrowthChart'] = new Chart(document.getElementById('ceoGrowthChart'), {
                type: 'bar',
                data: {
                    labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                    datasets: [{ label: 'New Users', data: [150, 200, 300, 150], backgroundColor: '#10b981' }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }, 50);
    }

    // Tab switching
    function openTab(evt, tabId) {
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.remove('active');
            el.style.display = 'none';
        });
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        document.getElementById(tabId).style.display = 'block';
        setTimeout(() => document.getElementById(tabId).classList.add('active'), 10);
        evt.currentTarget.classList.add('active');
    }

    // CSV Export
    function exportTableToCSV(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;

        let csv = [];
        table.querySelectorAll("tr").forEach(row => {
            const cols = Array.from(row.querySelectorAll("td, th")).map(col => 
                '"' + col.innerText.replace(/"/g, '""') + '"'
            );
            csv.push(cols.join(","));
        });

        const blob = new Blob([csv.join("\n")], { type: "text/csv" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = filename;
        link.style.display = "none";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    // Sidebar Observer Logic
    document.addEventListener('DOMContentLoaded', () => {
        renderCEODashboard();

        // Observer to detect sidebar state (from sidebars.php)
        const secondary = document.querySelector('.sidebar-secondary');
        if (secondary && window.matchMedia("(min-width: 1024px)").matches) {
            const observer = new MutationObserver(() => {
                const isOpen = secondary.classList.contains('open') || 
                              (secondary.style.transform && !secondary.style.transform.includes('-105%'));
                document.getElementById('mainContent').classList.toggle('secondary-visible', isOpen);
            });

            observer.observe(secondary, {
                attributes: true,
                attributeFilter: ['class', 'style']
            });

            // Check initial state
            const initialOpen = secondary.classList.contains('open');
            document.getElementById('mainContent').classList.toggle('secondary-visible', initialOpen);
        }
    });
</script>
</body>
</html>