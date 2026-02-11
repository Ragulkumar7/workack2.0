<?php 
include 'sidebars.php'; 
?>
<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance & Accounts - HRMS</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
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
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main Content Container */
        #mainContent {
            margin-left: 95px;          /* Width of primary sidebar */
            transition: margin-left 0.35s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Adjust when secondary sidebar is visible */
        @media (min-width: 1024px) {
            #mainContent.secondary-visible {
                margin-left: 315px;      /* 95px (primary) + 220px (secondary) */
            }
        }

        /* HEADER STYLES */
        header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between; /* Pushes title left, profile right */
            padding: 0 2rem;
            
            /* Sticky Positioning */
            position: sticky;
            top: 0;
            z-index: 90;
            width: 100%; /* Header takes full width of #mainContent */
        }

        /* Header Title Area */
        .header-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.1rem;
        }

        .header-title span {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        /* User Profile Area */
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

        /* Dashboard Content */
        .dashboard-scroll {
            padding: 1.5rem 2rem;
            overflow-y: auto;
            flex: 1;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            width: 100%;
        }

        .span-2 { grid-column: span 2; }
        .span-4 { grid-column: span 4; }

        /* Card Styles */
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
        }

        .card-value {
            font-size: 2.1rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0.25rem 0 0.5rem 0;
        }

        .trend {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .trend.up { color: var(--success); }
        .trend.down { color: var(--danger); }

        /* Chart Containers */
        .chart-box {
            position: relative;
            height: 320px;
            width: 100%;
        }

        .chart-box-sm {
            height: 260px;
            width: 100%;
        }

        /* Tables */
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
            background: #f8fafc;
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .data-table td { font-size: 0.95rem; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .bg-green { background: #dcfce7; color: #166534; }
        .bg-yellow { background: #fef3c7; color: #92400e; }
        .bg-red { background: #fee2e2; color: #991b1b; }

        /* Buttons */
        .btn-action {
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .btn-action:hover { background: var(--primary-dark); }
        .btn-export { background: #10b981; }
        .btn-export:hover { background: #059669; }

        /* Responsive */
        @media (max-width: 1023px) {
            #mainContent, header {
                margin-left: 0 !important;
                width: 100% !important;
            }
            .dashboard-scroll {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
            .span-2, .span-4 {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>

<div id="mainContent">

    <header>
        <div class="header-title">
            <h2 id="pageTitle">Finance & Accounts</h2>
            <span id="pageSubtitle">Invoices, Payments, Payroll, and Expenses</span>
        </div>
        <div class="user-profile">
            <div style="text-align: right; margin-right: 8px;">
                <div style="font-weight: 600; font-size: 0.95rem;" id="userName">Sarah Lee</div>
                <div style="font-size: 0.85rem; color: var(--secondary);" id="userRole">CFO / Accounts</div>
            </div>
            <div class="avatar" id="userAvatar">SL</div>
        </div>
    </header>

    <div class="dashboard-scroll">
        <div id="dashboardGrid" class="grid-container"></div>
    </div>

</div>

<script>
    // Configuration Data
    const cfoConfig = {
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
    };

    let currentCharts = {};

    function renderCFODashboard() {
        const data = cfoConfig;
        const grid = document.getElementById('dashboardGrid');

        // Render Cards and Charts Structure
        grid.innerHTML = '';
        Object.values(currentCharts).forEach(chart => chart?.destroy());
        currentCharts = {};

        // 1. Stat Cards
        data.cards.forEach(card => {
            const cardHTML = `
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
            `;
            grid.innerHTML += cardHTML;
        });

        // 2. Charts & Table
        data.charts.forEach(item => {
            let contentHTML = '';

            if (item.type === 'table') {
                contentHTML = `
                    <div style="overflow-x:auto;">
                        <table class="data-table" id="${item.id}">
                            <thead>
                                <tr>
                                    <th>Invoice ID</th>
                                    <th>Client</th>
                                    <th>Company</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${[
                                    { id: "Inv-001", client: "Michael Walker", company: "BrightWave Innovations", amount: "$1,200.00", status: "Paid" },
                                    { id: "Inv-002", client: "Sarah Connor", company: "CyberDyne Systems", amount: "$3,450.00", status: "Pending" },
                                    { id: "Inv-003", client: "Tony Stark", company: "Stark Ind", amount: "$12,000.00", status: "Overdue" }
                                ].map(row => `
                                    <tr>
                                        <td><b>${row.id}</b></td>
                                        <td>${row.client}</td>
                                        <td>${row.company}</td>
                                        <td>${row.amount}</td>
                                        <td><span class="status-badge bg-${row.status === 'Paid' ? 'green' : row.status === 'Pending' ? 'yellow' : 'red'}">${row.status}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 1rem; text-align: right;">
                        <button class="btn-action btn-export" onclick="exportTableToCSV('${item.id}', '${item.title}.csv')">
                            <i class="fa-solid fa-file-excel"></i> Export to Excel
                        </button>
                    </div>
                `;
            } else {
                contentHTML = `<div class="chart-box${item.type === 'doughnut' ? '-sm' : ''}"><canvas id="${item.id}"></canvas></div>`;
            }

            grid.innerHTML += `
                <div class="card ${item.span}">
                    <div class="card-header">
                        <span class="card-title">${item.title}</span>
                        ${item.type !== 'table' ? '<button class="btn-action" style="padding:4px 8px; font-size:0.7rem;">Export</button>' : ''}
                    </div>
                    ${contentHTML}
                </div>
            `;
        });

        // 3. Initialize Charts
        setTimeout(() => {
            currentCharts['cfoPaymentChart'] = new Chart(document.getElementById('cfoPaymentChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Paypal', 'Debit Card', 'Bank Transfer', 'Credit Card'],
                    datasets: [{ data: [30, 20, 40, 10], backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#6366f1'] }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            currentCharts['cfoExpenseChart'] = new Chart(document.getElementById('cfoExpenseChart'), {
                type: 'bar',
                data: {
                    labels: ['Salaries', 'IT Equip', 'Office', 'Marketing'],
                    datasets: [{ label: 'Amount ($)', data: [250000, 45000, 12000, 30000], backgroundColor: '#059669' }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }, 100);
    }

    // CSV Export function
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

    // Initialize Logic
    document.addEventListener('DOMContentLoaded', () => {
        renderCFODashboard();

        // Secondary Sidebar Observer (keeps header and content aligned)
        const secondary = document.querySelector('.sidebar-secondary');
        if (secondary && window.matchMedia("(min-width: 1024px)").matches) {
            const observer = new MutationObserver(() => {
                const isOpen = secondary.classList.contains('open') ||
                              (secondary.style.transform && !secondary.style.transform.includes('-105%'));
                document.getElementById('mainContent').classList.toggle('secondary-visible', isOpen);
            });

            observer.observe(secondary, { attributes: true, attributeFilter: ['class', 'style'] });

            const initialOpen = secondary.classList.contains('open');
            document.getElementById('mainContent').classList.toggle('secondary-visible', initialOpen);
        }
    });
</script>
</body>
</html>