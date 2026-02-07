<?php include '../sidebars.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Reports - HRMS</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            --primary:    #d97706;
            --primary-dk: #b45309;
            --success:    #10b981;
            --warning:    #f59e0b;
            --danger:     #ef4444;
            --gray:       #64748b;
            --light:      #f1f5f9;
            --card:       #ffffff;
            --border:     #e2e8f0;
            --text:       #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* --- Layout --- */
        #mainContent {
            margin-left: 95px;
            transition: margin-left 0.4s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 1024px) {
            #mainContent.secondary-visible { margin-left: 315px; }
        }

        /* --- Header --- */
        header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky; 
            top: 0;
            z-index: 90;
            width: 100%; 
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }
        .page-title p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* --- Export Dropdown Styles --- */
        .export-wrapper {
            position: relative;
            display: inline-block;
        }

        .btn-export {
            padding: 0.6rem 1.2rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-export:hover { background: var(--primary-dk); }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 110%;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 100;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .dropdown-menu.show { display: block; animation: fadeIn 0.2s; }

        .dropdown-menu a {
            color: var(--text);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.1s;
        }

        .dropdown-menu a:hover { background-color: var(--light); color: var(--primary); }
        .dropdown-menu a i { margin-right: 8px; width: 16px; text-align: center; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Content --- */
        .content-wrapper { padding: 2rem; flex: 1; }
        .content-container { max-width: 1600px; margin: 0 auto; }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card);
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .stat-card .title { color: var(--gray); font-size: 0.9rem; font-weight: 600; margin-bottom: 0.6rem; }
        .stat-card .value { font-size: 2.1rem; font-weight: 700; margin-bottom: 0.4rem; }
        .trend { font-size: 0.9rem; display: flex; align-items: center; gap: 0.4rem; }
        .trend.up { color: var(--success); }
        .trend.down { color: var(--danger); }

        /* Charts */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .chart-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .chart-card h3 { margin-bottom: 1.2rem; font-size: 1.1rem; font-weight: 600; color: #334155; }
        .chart-container { position: relative; height: 320px; width: 100%; }
        .chart-container.sm { height: 280px; }

        /* Table */
        .full-width-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f8fafc; font-weight: 600; color: #475569; white-space: nowrap; }
        .status-badge { padding: 5px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .bg-green { background: #ecfdf5; color: #065f46; }
        .bg-yellow { background: #fefce8; color: #854d0e; }
        .bg-blue { background: #eff6ff; color: #1e40af; }
        .bg-red { background: #fee2e2; color: #991b1b; }

        @media (max-width: 1023px) {
            #mainContent { margin-left: 0 !important; }
            .content-wrapper { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<div id="mainContent">

    <header>
        <div class="page-title">
            <h1>Manager Reports</h1>
            <p>Project progress • Team productivity • Task completion overview</p>
        </div>
        
        <div class="export-wrapper">
            <button class="btn-export" onclick="toggleExportMenu()">
                <i class="fa-solid fa-download"></i> Export Report <i class="fa-solid fa-caret-down"></i>
            </button>
            <div id="exportMenu" class="dropdown-menu">
                <a onclick="exportToExcel()"><i class="fa-solid fa-file-excel" style="color:#107c41;"></i> Export as Excel</a>
                <a onclick="exportToCSV()"><i class="fa-solid fa-file-csv" style="color:#0f9d58;"></i> Export as CSV</a>
                <a onclick="exportToPDF()"><i class="fa-solid fa-file-pdf" style="color:#b30b00;"></i> Export as PDF</a>
            </div>
        </div>
    </header>

    <div class="content-wrapper">
        <div class="content-container" id="reportContent">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="title">Total Projects</div>
                    <div class="value">312</div>
                    <div class="trend up"><i class="fa-solid fa-arrow-up"></i> +14 this month</div>
                </div>
                <div class="stat-card">
                    <div class="title">Completed Tasks</div>
                    <div class="value">1,284</div>
                    <div class="trend up"><i class="fa-solid fa-arrow-up"></i> 92.4% completion</div>
                </div>
                <div class="stat-card">
                    <div class="title">Pending Tasks</div>
                    <div class="value">138</div>
                    <div class="trend down"><i class="fa-solid fa-arrow-down"></i> 18 high priority</div>
                </div>
                <div class="stat-card">
                    <div class="title">Active Team Members</div>
                    <div class="value">87</div>
                    <div class="trend up"><i class="fa-solid fa-arrow-up"></i> +3 new joins</div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Project Status Distribution</h3>
                    <div class="chart-container sm">
                        <canvas id="projectStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Tasks Completed vs Assigned (by Department)</h3>
                    <div class="chart-container">
                        <canvas id="tasksByDeptChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="full-width-card">
                <h3 style="margin-bottom:1.2rem;">Active / In-Progress Projects</h3>
                <div class="table-responsive">
                    <table id="reportTable">
                        <thead>
                            <tr>
                                <th>Project ID</th>
                                <th>Project Name</th>
                                <th>Project Lead</th>
                                <th>Progress</th>
                                <th>Deadline</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>PRJ-187</strong></td>
                                <td>Hospital Management Phase 2</td>
                                <td>Anthony Lewis</td>
                                <td>88%</td>
                                <td>15 Aug 2025</td>
                                <td><span class="status-badge bg-green">On Track</span></td>
                            </tr>
                            <tr>
                                <td><strong>PRJ-204</strong></td>
                                <td>Payment Gateway Integration</td>
                                <td>Brian Villalobos</td>
                                <td>62%</td>
                                <td>30 Sep 2025</td>
                                <td><span class="status-badge bg-yellow">At Risk</span></td>
                            </tr>
                            <tr>
                                <td><strong>PRJ-205</strong></td>
                                <td>HRMS</td>
                                <td>Damon salvator</td>
                                <td>80%</td>
                                <td>28 Sep 2025</td>
                                <td><span class="status-badge bg-yellow">On track</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
// Toggle Dropdown
function toggleExportMenu() {
    document.getElementById("exportMenu").classList.toggle("show");
}

// Close dropdown if clicked outside
window.onclick = function(event) {
    if (!event.target.closest('.btn-export')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
    }
}

// --- Export Functions ---

// 1. Export to Excel
function exportToExcel() {
    const table = document.getElementById('reportTable');
    // Create a new workbook and a new sheet from the table
    const wb = XLSX.utils.table_to_book(table, {sheet: "Active Projects"});
    // Write the workbook and trigger download
    XLSX.writeFile(wb, 'Manager_Report_Projects.xlsx');
}

// 2. Export to CSV
function exportToCSV() {
    const table = document.getElementById('reportTable');
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    XLSX.utils.book_append_sheet(wb, ws, "Projects");
    XLSX.writeFile(wb, 'Manager_Report_Projects.csv');
}

// 3. Export to PDF (Visual Snapshot)
function exportToPDF() {
    // We capture the content-container to include Stats + Charts + Table
    const element = document.getElementById('reportContent');
    const { jsPDF } = window.jspdf;

    // Use html2canvas to take a screenshot
    html2canvas(element, { scale: 2 }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        
        // A4 Dimensions in mm
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = pdf.internal.pageSize.getHeight();
        
        const imgProps = pdf.getImageProperties(imgData);
        const imgHeight = (imgProps.height * pdfWidth) / imgProps.width;
        
        // Add Title manually or just the image
        pdf.text("Manager Report Export", 10, 10);
        
        // Add image (with top margin)
        pdf.addImage(imgData, 'PNG', 0, 15, pdfWidth, imgHeight);
        
        pdf.save('Manager_Report_Dashboard.pdf');
    });
}

// --- Charts & Sidebar Logic ---
document.addEventListener('DOMContentLoaded', () => {

    // Chart.js Implementations
    new Chart(document.getElementById('projectStatusChart'), {
        type: 'pie',
        data: {
            labels: ['Completed', 'In Progress', 'On Hold', 'Delayed'],
            datasets: [{
                data: [148, 92, 19, 11],
                backgroundColor: ['#10b981', '#d97706', '#64748b', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    new Chart(document.getElementById('tasksByDeptChart'), {
        type: 'bar',
        data: {
            labels: ['Development', 'QA', 'Design', 'Marketing', 'Support'],
            datasets: [
                { label: 'Assigned',   data: [320,180,95,140,210], backgroundColor: '#cbd5e1' },
                { label: 'Completed',  data: [295,162,88,128,195], backgroundColor: '#d97706' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Sidebar Observer
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

        const initialOpen = secondary.classList.contains('open');
        document.getElementById('mainContent').classList.toggle('secondary-visible', initialOpen);
    }
});
</script>
</body>
</html>