<?php 
// Move all logic-heavy includes to the top to prevent "headers already sent" errors
include '../sidebars.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding | Workack HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #64748b;
            --bg-light: #f1f5f9;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-light: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #e2e8f0;
            --transition: all 0.3s ease;
            /* Adjusted to match the slim sidebar in your sample image */
            --sidebar-width: 100px; 
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        /* Fixed: Adjusted margin-left and width to eliminate the large leftover gap.
           Added padding-right for symmetry.
        */
        main {
            margin-left: var(--sidebar-width);
            padding: 30px 20px 30px 30px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            transition: var(--transition);
            flex-grow: 1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .card {
            background: var(--surface);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .onboarding-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }

        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            margin-top: 5px;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }

        label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-light);
            display: block;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .table-container {
            overflow-x: auto;
            background: var(--surface);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f8fafc;
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .toast {
            background: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
            margin-top: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }

        #toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        @media (max-width: 1024px) {
            .onboarding-grid {
                grid-template-columns: 1fr;
            }
            main {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <main>
        <?php include '../header.php'; ?>

        <section id="onboarding" class="active">
            <h1>Employee Onboarding</h1>

            <div class="onboarding-grid">
                <div class="card">
                    <h3 style="margin-bottom:20px;">Add New Hire</h3>
                    <form id="onboardingForm" onsubmit="addEmployee(event)">
                        <div style="margin-bottom:15px;">
                            <label>Full Name</label>
                            <input type="text" id="empName" required placeholder="Jane Doe">
                        </div>
                        <div style="margin-bottom:15px;">
                            <label>Email</label>
                            <input type="email" id="empEmail" required placeholder="jane@workack.com">
                        </div>
                        <div style="margin-bottom:15px;">
                            <label>Role Designation</label>
                            <select id="empRole" required>
                                <option value="Employee">Employee</option>
                                <option value="Team Lead">Team Lead</option>
                                <option value="Manager">Manager</option>
                            </select>
                        </div>
                        <div style="margin-bottom:15px;">
                            <label>Department</label>
                            <select id="empDept" required>
                                <option value="IT">IT</option>
                                <option value="IT Team">IT Team</option>
                                <option value="Sales">Sales</option>
                                <option value="Accounting">Accounting</option>
                            </select>
                        </div>
                        <div style="margin-bottom:15px;">
                            <label>Allocated Manager</label>
                            <input type="text" id="empManager" required placeholder="Name of Manager">
                        </div>
                        <div style="margin-bottom:20px;">
                            <label>Salary Package (Annual $)</label>
                            <input type="number" id="empSalary" required placeholder="e.g. 60000">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class="fa-solid fa-plus"></i> Create Profile
                        </button>
                    </form>
                </div>

                <div class="card">
                    <h3 style="margin-bottom:20px;">Recent Onboarding</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Manager</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="onboarding-table-body">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div id="toast-container"></div>

    <script>
        let employees = [
            { id: 1, name: "Robert Fox", email: "robert@workack.com", role: "Manager", dept: "IT", manager: "Sarah (HR)", salary: 120000 },
            { id: 2, name: "Jane Cooper", email: "jane@workack.com", role: "Team Lead", dept: "IT Team", manager: "Robert Fox", salary: 95000 },
            { id: 3, name: "Cody Fisher", email: "cody@workack.com", role: "Employee", dept: "Sales", manager: "Albert Flores", salary: 65000 },
        ];

        document.addEventListener('DOMContentLoaded', () => {
            renderOnboardingRecent();
        });

        function addEmployee(e) {
            e.preventDefault();
            const newEmp = {
                id: Date.now(),
                name: document.getElementById('empName').value.trim(),
                email: document.getElementById('empEmail').value.trim(),
                role: document.getElementById('empRole').value,
                dept: document.getElementById('empDept').value,
                manager: document.getElementById('empManager').value.trim(),
                salary: parseInt(document.getElementById('empSalary').value)
            };

            if (!newEmp.name || !newEmp.email || !newEmp.manager || isNaN(newEmp.salary)) {
                showToast('Please fill all fields correctly', 'error');
                return;
            }

            employees.push(newEmp);
            renderOnboardingRecent();
            document.getElementById('onboardingForm').reset();
            showToast(`${newEmp.name} added successfully!`);
        }

        function renderOnboardingRecent() {
            const tbody = document.getElementById('onboarding-table-body');
            if(!tbody) return;
            tbody.innerHTML = '';
            const recent = [...employees].reverse().slice(0, 5);

            recent.forEach(emp => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${emp.name}</strong></td>
                    <td>${emp.role}</td>
                    <td>${emp.manager}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="pushToManager(this)">
                            <i class="fa-solid fa-paper-plane"></i> Push
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function pushToManager(btn) {
            btn.innerHTML = 'Sent';
            btn.disabled = true;
            btn.style.opacity = '0.5';
            showToast('Profile pushed to manager email.');
        }

        function showToast(msg, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.borderLeftColor = type === 'error' ? 'var(--danger)' : 'var(--primary)';
            toast.innerHTML = `<span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>