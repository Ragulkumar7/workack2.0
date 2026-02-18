<?php 
// salary_revisions.php (Accountant Role)
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Revisions & Increments</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- GLOBAL STYLES (MATCHING ACCOUNTS THEME) --- */
        :root {
            --theme-color: #1b5a5a;
            --theme-light: #e0f2f1;
            --bg-body: #f3f4f6;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.03);
            --primary-sidebar-width: 95px;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0; padding: 0; color: var(--text-main);
        }

        .main-content {
            margin-left: var(--primary-sidebar-width);
            padding: 30px;
            width: calc(100% - var(--primary-sidebar-width));
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header-area h2 { margin: 0; color: var(--theme-color); font-weight: 700; font-size: 24px; }
        .header-area p { color: var(--text-muted); font-size: 13px; margin: 5px 0 0; }

        /* --- CARDS --- */
        .card {
            background: white; padding: 25px; border-radius: 12px;
            box-shadow: var(--card-shadow); border: 1px solid var(--border-color); margin-bottom: 30px;
        }
        .card-header { border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; }
        .card-header h3 { margin: 0; font-size: 16px; color: var(--theme-color); display: flex; align-items: center; gap: 8px; }

        /* --- FORM GRID --- */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px;
            outline: none; font-size: 14px; background: #fff; transition: 0.2s;
        }
        .form-group input:focus { border-color: var(--theme-color); box-shadow: 0 0 0 3px var(--theme-light); }
        .form-group input[readonly] { background-color: #f8fafc; color: #94a3b8; cursor: not-allowed; }

        /* --- TABLES --- */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { background: #f8fafc; padding: 12px 15px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        td { padding: 14px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: var(--text-main); vertical-align: middle; }
        
        /* --- STATUS BADGES --- */
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .badge.pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .badge.approved { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .badge.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

        /* --- ACTION BUTTONS --- */
        .btn-primary {
            background: var(--theme-color); color: white; padding: 12px 25px;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-primary:hover { background: #134e4e; transform: translateY(-1px); }
        
        .alert-box {
            background: #e0f2fe; border-left: 4px solid #0284c7; padding: 15px;
            margin-bottom: 25px; color: #075985; font-size: 13px; display: flex; align-items: center; gap: 10px; border-radius: 6px;
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <div class="header-area">
        <div>
            <h2>Salary Revision & Increments</h2>
            <p>Request salary structure changes for CFO approval.</p>
        </div>
        <button class="btn-primary" style="background: white; color: var(--text-main); border: 1px solid var(--border-color);" onclick="window.location.href='payslip.php'">
            <i class="ph-bold ph-receipt"></i> Go to Payroll Generation
        </button>
    </div>

    <div class="alert-box">
        <i class="ph-fill ph-info" style="font-size: 20px;"></i>
        <span><strong>Workflow:</strong> Request Salary Change ➝ <strong>CFO Approves</strong> ➝ System Updates Master Data ➝ Accountant Generates Payroll.</span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 25px;">
        
        <div class="card">
            <div class="card-header">
                <h3><i class="ph-fill ph-user-gear"></i> Request Revision</h3>
            </div>
            <form id="revisionForm" onsubmit="event.preventDefault(); sendToCFO();">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Search Employee</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="empId" placeholder="Enter Emp ID (e.g., IGS001)" onblur="fetchEmployee()">
                        <button type="button" class="btn-primary" style="padding: 10px;" onclick="fetchEmployee()"><i class="ph-bold ph-magnifying-glass"></i></button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Employee Name</label>
                    <input type="text" id="empName" placeholder="Name will appear here..." readonly>
                    <input type="hidden" id="empDept">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label>Current Salary (₹)</label>
                        <input type="text" id="currentSalary" value="0.00" readonly>
                    </div>
                    <div class="form-group">
                        <label>New Salary (₹)</label>
                        <input type="number" id="newSalary" placeholder="Enter new amount" required style="border-color: var(--theme-color); font-weight: 700;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Effective Month</label>
                    <input type="month" id="effectiveMonth" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Reason for Revision / Remarks</label>
                    <textarea id="remarks" rows="3" placeholder="e.g., Annual Appraisal, Promotion to Team Lead..." required></textarea>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                    <i class="ph-bold ph-paper-plane-right"></i> Send to CFO for Approval
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="ph-fill ph-clock-counter-clockwise"></i> Revision Status Tracker</h3>
            </div>
            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Emp ID</th>
                            <th>Name</th>
                            <th>Old Salary</th>
                            <th>New Salary</th>
                            <th>Requested On</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="requestTableBody">
                        <tr>
                            <td><strong>IGS005</strong></td>
                            <td>Karthik R<br><span style="font-size:11px; color:#64748b;">IT Dept</span></td>
                            <td>₹35,000</td>
                            <td style="font-weight:700; color:var(--theme-color);">₹42,000</td>
                            <td>17-Feb-2026</td>
                            <td><span class="badge pending"><i class="ph-fill ph-hourglass"></i> Pending CFO</span></td>
                        </tr>
                        <tr>
                            <td><strong>IGS002</strong></td>
                            <td>Priya S<br><span style="font-size:11px; color:#64748b;">HR Dept</span></td>
                            <td>₹28,000</td>
                            <td style="font-weight:700; color:var(--theme-color);">₹32,000</td>
                            <td>10-Feb-2026</td>
                            <td><span class="badge approved"><i class="ph-fill ph-check-circle"></i> Approved</span></td>
                        </tr>
                        <tr>
                            <td><strong>IGS012</strong></td>
                            <td>Suresh K<br><span style="font-size:11px; color:#64748b;">Support</span></td>
                            <td>₹20,000</td>
                            <td style="font-weight:700; color:var(--theme-color);">₹28,000</td>
                            <td>05-Feb-2026</td>
                            <td><span class="badge rejected"><i class="ph-fill ph-x-circle"></i> Rejected</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
    // --- 1. MOCK FETCH EMPLOYEE ---
    function fetchEmployee() {
        const id = document.getElementById('empId').value.toUpperCase();
        const nameField = document.getElementById('empName');
        const salaryField = document.getElementById('currentSalary');
        
        if(id === 'IGS001') {
            nameField.value = "Ram Kumar (Sr. Developer)";
            salaryField.value = "50,000";
        } else if(id === 'IGS002') {
            nameField.value = "Priya S (HR Manager)";
            salaryField.value = "28,000";
        } else {
            // Default mock for demo
            nameField.value = "Demo Employee";
            salaryField.value = "25,000";
        }
    }

    // --- 2. SEND TO CFO LOGIC ---
    function sendToCFO() {
        const empId = document.getElementById('empId').value;
        const empName = document.getElementById('empName').value;
        const oldSal = document.getElementById('currentSalary').value;
        const newSal = document.getElementById('newSalary').value;
        const date = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

        if(!empId || !newSal) {
            alert("Please fill all required fields.");
            return;
        }

        // Simulate API delay
        const btn = document.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Sending...';
        btn.disabled = true;

        setTimeout(() => {
            // Add new row to table
            const table = document.getElementById('requestTableBody');
            const newRow = `
                <tr style="background: #f0fdf4; animation: highlight 1s;">
                    <td><strong>${empId.toUpperCase()}</strong></td>
                    <td>${empName.split('(')[0]}<br><span style="font-size:11px; color:#64748b;">Pending</span></td>
                    <td>₹${oldSal}</td>
                    <td style="font-weight:700; color:var(--theme-color);">₹${Number(newSal).toLocaleString('en-IN')}</td>
                    <td>${date}</td>
                    <td><span class="badge pending"><i class="ph-fill ph-hourglass"></i> Pending CFO</span></td>
                </tr>
            `;
            table.insertAdjacentHTML('afterbegin', newRow);

            // Reset Form
            document.getElementById('revisionForm').reset();
            btn.innerHTML = originalText;
            btn.disabled = false;

            alert("Request Sent Successfully!\n\nThe CFO will be notified to review this salary revision.");
        }, 1000);
    }
</script>

</body>
</html>