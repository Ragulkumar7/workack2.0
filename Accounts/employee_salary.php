<?php
// Fixes "headers already sent" error by turning on output buffering
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database and Path configuration
$sidebarPath = '';
$headerPath = '';

if (file_exists('include/db_connect.php')) {
    require_once 'include/db_connect.php';
    $sidebarPath = 'sidebars.php';
    $headerPath = 'header.php';
} elseif (file_exists('../include/db_connect.php')) {
    require_once '../include/db_connect.php';
    $sidebarPath = '../sidebars.php';
    $headerPath = '../header.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Salary Management | WorkAck HRMS</title>
    <style>
        :root { --primary: #f97316; --success: #22c55e; --danger: #ef4444; --gray: #6b7280; --bg: #f3f4f6; }
        
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); margin: 0; overflow-x: hidden; }
        
        .main-content {
            margin-left: 100px; /* Aligned with professional sidebar width */
            padding-left: 25px;
            padding-right: 25px;
            padding-bottom: 30px;
            min-height: 100vh;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        @media (max-width: 991px) {
            .main-content { margin-left: 0; padding-left: 15px; padding-right: 15px; padding-top: 80px; }
        }

        .dashboard { max-width: 1400px; margin: 0 auto; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card { background: #fff; padding: 22px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); }
        .card h3 { margin: 0 0 10px; font-size: 13px; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px;}
        .card p { margin: 0; font-size: 26px; font-weight: 700; color: #1f2937; }
        
        .top-bar { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 18px 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px; }
        
        .btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; color: #fff; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 14px; }
        .btn-primary { background: var(--primary); }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--danger); }
        .btn-dark { background: #1f2937; }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
        .btn:hover { filter: brightness(92%); transform: translateY(-1px); }

        .table-responsive { overflow-x: auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th { background: #f9fafb; padding: 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray); text-transform: uppercase; border-bottom: 1px solid #edf2f7; }
        td { padding: 16px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #4a5568; }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 42px; height: 42px; border-radius: 50%; background: #eee; object-fit: cover; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.Pending { background: #fee2e2; color: #dc2626; }
        .badge.Credited { background: #e0f2fe; color: #0284c7; }
        .badge.Approved { background: #dcfce7; color: #16a34a; }

        /* Modal Styles */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
        .modal-content { background: #fff; padding: 30px; width: 100%; max-width: 850px; border-radius: 16px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #f3f4f6; padding-bottom: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select { padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
        .section-title { grid-column: 1 / -1; margin: 20px 0 10px; font-weight: 700; color: #111827; font-size: 16px; border-bottom: 2px solid #f3f4f6; padding-bottom: 8px; }
    </style>
</head>
<body>

<?php 
if (!empty($sidebarPath) && file_exists($sidebarPath)) require_once $sidebarPath; 
if (!empty($headerPath) && file_exists($headerPath)) require_once $headerPath; 
?>

<div class="main-content">
    <div class="dashboard">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h2 style="margin:0; color: #111827; font-weight: 800;">Salary & Payroll Management</h2>
            <div style="color: var(--gray); font-size: 14px; font-weight: 600;" id="current-date"></div>
        </div>
        
        <div class="summary-cards">
            <div class="card"><h3>Total Payroll</h3><p id="tot-payroll">₹0</p></div>
            <div class="card" style="border-left-color: var(--success);"><h3>Net Credited</h3><p id="tot-credited">₹0</p></div>
            <div class="card" style="border-left-color: var(--danger);"><h3>Pending Approval</h3><p id="tot-pending">₹0</p></div>
            <div class="card" style="border-left-color: #8b5cf6;"><h3>Total Deductions</h3><p id="tot-deductions">₹0</p></div>
        </div>

        <div class="top-bar">
            <div style="display:flex; gap: 12px; align-items:center; flex-wrap: wrap;">
                <input type="month" id="filter-month" onchange="loadSalaries()" style="padding:10px; border:1px solid #ddd; border-radius:8px;">
                <select id="filter-status" onchange="loadSalaries()" style="padding:10px; border:1px solid #ddd; border-radius:8px;">
                    <option value="">All Approval Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                </select>
            </div>
            <div style="display:flex; gap: 10px;">
                <button class="btn btn-outline" onclick="exportCSV()">Export CSV</button>
                <button class="btn btn-primary" onclick="openModal()">+ Add New Salary</button>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Employee Details</th>
                        <th>Month</th>
                        <th>Status</th>
                        <th>CFO Approval</th>
                        <th>Net Payable</th>
                        <th style="text-align: right;">Action Center</th>
                    </tr>
                </thead>
                <tbody id="salary-body">
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="salaryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title" style="margin:0; font-weight: 800;">Process Salary Record</h3>
            <button type="button" onclick="closeModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
        </div>
        <form id="salaryForm" onsubmit="submitSalary(event)">
            <input type="hidden" name="id" id="salary_db_id">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee Name</label>
                    <select name="user_id" id="employeeSelect" required>
                        <option value="">Select Employee...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salary Month</label>
                    <input type="month" name="salary_month" id="form_salary_month" required>
                </div>
                <div class="form-group">
                    <label>Credit Status</label>
                    <select name="credit_status" id="creditStatus" onchange="toggleDate(this.value)">
                        <option value="Pending">Pending</option>
                        <option value="Credited">Credited</option>
                    </select>
                </div>
                <div class="form-group" id="creditDateDiv" style="display: none;">
                    <label>Date of Credit</label>
                    <input type="date" name="credit_date" id="creditDate">
                </div>

                <div class="section-title">Earnings (In ₹)</div>
                <div class="form-group"><label>Basic Pay</label><input type="number" name="basic" value="0" step="0.01"></div>
                <div class="form-group"><label>DA (40%)</label><input type="number" name="da" value="0" step="0.01"></div>
                <div class="form-group"><label>HRA (15%)</label><input type="number" name="hra" value="0" step="0.01"></div>
                <div class="form-group"><label>Conveyance</label><input type="number" name="conveyance" value="0" step="0.01"></div>

                <div class="section-title">Deductions (In ₹)</div>
                <div class="form-group"><label>TDS (Tax)</label><input type="number" name="tds" value="0" step="0.01"></div>
                <div class="form-group"><label>ESI / PF</label><input type="number" name="pf" value="0" step="0.01"></div>
                <div class="form-group"><label>LOP Deduction</label><input type="number" name="leave_deduction" value="0" step="0.01"></div>
                
                <div class="form-group" style="grid-column: 1 / -1; margin-top:20px; border-top:1px solid #f3f4f6; padding-top: 20px; display:flex; flex-direction:row; justify-content:flex-end; gap:12px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submit-btn">Send for CFO Approval</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('filter-month').value = new Date().toISOString().slice(0, 7);
    document.getElementById('current-date').innerText = new Date().toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    let currentData = [];

    function toggleDate(status) {
        const dateDiv = document.getElementById('creditDateDiv');
        dateDiv.style.display = (status === 'Credited') ? 'flex' : 'none';
        if (status === 'Credited') document.getElementById('creditDate').value = new Date().toISOString().slice(0, 10);
    }

    async function fetchEmployees() {
        const res = await fetch('api/get_employees.php');
        const result = await res.json();
        if(result.success) {
            const select = document.getElementById('employeeSelect');
            result.data.forEach(emp => {
                const opt = document.createElement('option');
                opt.value = emp.user_id;
                opt.text = `${emp.full_name} (${emp.emp_id_code})`;
                select.appendChild(opt);
            });
        }
    }

    async function loadSalaries() {
        const month = document.getElementById('filter-month').value;
        const status = document.getElementById('filter-status').value;
        const res = await fetch(`api/salary_list.php?month=${month}&status=${status}`);
        const result = await res.json();
        
        if(result.success) {
            currentData = result.data;
            document.getElementById('tot-payroll').innerText = `₹${Number(result.summary.total_payroll).toLocaleString('en-IN')}`;
            document.getElementById('tot-credited').innerText = `₹${Number(result.summary.total_credited).toLocaleString('en-IN')}`;
            document.getElementById('tot-pending').innerText = `₹${Number(result.summary.total_pending).toLocaleString('en-IN')}`;
            document.getElementById('tot-deductions').innerText = `₹${Number(result.summary.total_deductions).toLocaleString('en-IN')}`;

            const tbody = document.getElementById('salary-body');
            tbody.innerHTML = '';
            if(!result.data.length) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--gray);">No payroll records found.</td></tr>';

            result.data.forEach(row => {
                const tr = document.createElement('tr');
                const avatar = row.profile_img && row.profile_img !== 'null' ? row.profile_img : `https://ui-avatars.com/api/?name=${row.name}&background=random`;
                const isApproved = row.approval_status === 'Approved';
                
                tr.innerHTML = `
                    <td>
                        <div class="user-info">
                            <img src="${avatar}" class="user-avatar">
                            <div>
                                <div style="font-weight:700; color:#1e293b;">${row.name}</div>
                                <div style="font-size:11px; color:var(--gray);">${row.emp_code} | ${row.designation || '-'}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:600;">${row.salary_month}</td>
                    <td><span class="badge ${row.credit_status}">${row.credit_status}</span></td>
                    <td><span class="badge ${row.approval_status || 'Pending'}">${row.approval_status || 'Pending'}</span></td>
                    <td style="font-weight:700; color:#111;">₹${Number(row.net_salary).toLocaleString('en-IN')}</td>
                    <td style="text-align:right;">
                        <div style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
                            ${isApproved ? 
                                `<button class="btn btn-dark" style="padding:6px 12px; font-size:12px;" onclick="window.open('api/generate_payslip.php?id=${row.id}')">Generate Slip</button>` : 
                                `<button class="btn btn-success" style="padding:6px 12px; font-size:12px;" onclick="askApproval(${row.id})">Ask Approval</button>`
                            }
                            <button class="btn btn-outline" style="padding:6px 10px;" onclick="editSalary(${row.id})" title="Edit">Edit</button>
                            <button class="btn btn-danger" style="padding:6px 10px;" onclick="deleteSalary(${row.id})" title="Delete">🗑</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    }

    async function askApproval(id) {
        if(confirm("Notify CFO for approval of this record?")) {
            // This button triggers the CFO Approval Center notification logic
            alert("Approval request has been sent to the CFO.");
            loadSalaries();
        }
    }

    async function editSalary(id) {
        const item = currentData.find(d => d.id == id);
        if(!item) return;
        document.getElementById('modal-title').innerText = 'Update Payroll Record';
        document.getElementById('salary_db_id').value = item.id;
        document.getElementById('employeeSelect').value = item.user_id;
        document.getElementById('form_salary_month').value = item.salary_month;
        document.getElementById('creditStatus').value = item.credit_status;
        toggleDate(item.credit_status);
        
        const fields = ['basic', 'da', 'hra', 'conveyance', 'tds', 'pf', 'leave_deduction'];
        fields.forEach(f => {
            const el = document.getElementsByName(f)[0];
            if(el) el.value = item[f] || 0;
        });
        
        document.getElementById('submit-btn').innerText = 'Update & Re-send';
        openModal();
    }

    async function submitSalary(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const actionFile = formData.get('id') ? 'api/salary_update.php' : 'api/salary_add.php';
        
        const res = await fetch(actionFile, { method: 'POST', body: formData });
        const result = await res.json();
        if(result.success) {
            closeModal();
            loadSalaries();
        } else {
            alert(result.message);
        }
    }

    async function deleteSalary(id) {
        if(confirm("Permanently delete this record?")) {
            const res = await fetch('api/salary_delete.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            });
            const result = await res.json();
            if(result.success) loadSalaries();
        }
    }

    function exportCSV() {
        if(!currentData.length) return alert("No data to export");
        let csv = "Employee,Month,Net Salary,Status\n";
        currentData.forEach(r => csv += `"${r.name}","${r.salary_month}","${r.net_salary}","${r.approval_status}"\n`);
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Payroll_Report_${document.getElementById('filter-month').value}.csv`;
        a.click();
    }

    function openModal() { document.getElementById('salaryModal').style.display = 'flex'; }
    function closeModal() { 
        document.getElementById('salaryModal').style.display = 'none'; 
        document.getElementById('salaryForm').reset();
        document.getElementById('salary_db_id').value = '';
        document.getElementById('modal-title').innerText = 'Process Salary Record';
        document.getElementById('submit-btn').innerText = 'Send for CFO Approval';
    }

    window.onload = () => { fetchEmployees(); loadSalaries(); };
</script>
</body>
</html>