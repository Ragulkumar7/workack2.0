<?php 
include '../sidebars.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resignation - Employee | HRMS</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --primary: #ed3f65ff;
            --primary-dark: #e2244dff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray: #64748b;
            --light: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--text);
            min-height: 100vh;
        }

        #mainContent {
            margin-left: 95px;
            transition: all 0.35s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 1024px) {
            #mainContent.secondary-visible { margin-left: 315px; }
        }

        header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .content-wrapper { padding: 2rem 2.5rem; flex: 1; width: 100%; }

        .policy-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--primary);
        }

        .btn-primary {
            padding: 0.85rem 1.75rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-container {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { background: #f8fafc; padding: 1.2rem 1.5rem; text-align: left; color: #475569; font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        .history-table td { padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f5f9; }

        .status-badge { padding: 6px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; }
        .status-pending  { background: #fffbeb; color: #92400e; }
        .status-approved { background: #ecfdf5; color: #065f46; }
        .status-rejected { background: #fef2f2; color: #991b1b; }

        .action-btn { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; margin-left: 5px; }
        .btn-view { background: #f1f5f9; color: #475569; }
        .btn-cancel { background: #fff1f2; color: #e11d48; border-color: #ffe4e6; }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content { background: white; border-radius: 16px; width: 95%; max-width: 550px; padding: 2.25rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.8rem; border: 1px solid #cbd5e1; border-radius: 8px; }

        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; }
        .detail-label { color: #64748b; font-weight: 500; }
        .detail-value { color: #1e293b; font-weight: 600; }
    </style>
</head>
<body>

<div id="mainContent">
    <header>
        <div class="page-title">
            <h1>Resignation</h1>
            <p>Submit and track your resignation status</p>
        </div>
        <button class="btn-primary" onclick="toggleModal('resignationModal', true)">
            <i class="fas fa-plus"></i> Submit Resignation
        </button>
    </header>

    <div class="content-wrapper">
        <div class="policy-card">
            <h3><i class="fas fa-info-circle"></i> Important Notice Period Policy</h3>
            <ul style="line-height: 1.8; padding-left: 1.2rem; color: #475569;">
                <li>Standard notice: <strong>30 days</strong> (< 2 yrs service) | Senior notice: <strong>60 days</strong> (2+ yrs)</li>
            </ul>
        </div>

        <h2 style="margin-bottom: 1.25rem; font-size: 1.4rem;">Resignation History</h2>
        <div class="table-container">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date Applied</th>
                        <th>Last Working Day</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="resignationTableBody">
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="resignationModal">
    <div class="modal-content">
        <h2 style="margin-bottom: 1.5rem;">Submit Resignation</h2>
        <form id="resignationForm">
            <div class="form-group">
                <label>Proposed Last Working Day *</label>
                <input type="date" id="lastWorkingDay" required>
            </div>
            <div class="form-group">
                <label>Reason *</label>
                <select id="reason" required>
                    <option value="Better career opportunity">Better career opportunity</option>
                    <option value="Personal reasons">Personal reasons</option>
                    <option value="Relocation">Relocation</option>
                </select>
            </div>
            <div class="form-group">
                <label>Additional Comments</label>
                <textarea id="comments" rows="3"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="action-btn" onclick="toggleModal('resignationModal', false)">Cancel</button>
                <button type="submit" class="btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="detailsModal">
    <div class="modal-content">
        <h2 style="margin-bottom: 1.5rem;">Resignation Details</h2>
        <div id="detailsContent"></div>
        <div style="margin-top: 2rem; text-align: right;">
            <button class="btn-primary" onclick="toggleModal('detailsModal', false)">Close</button>
        </div>
    </div>
</div>

<script>
// 1. Initial Data
let resignationData = [
    { id: 1, applied: '15 Jan 2026', lastDay: '2026-02-15', reason: 'Relocation', status: 'Approved', comments: 'Moving to New York.' },
    { id: 2, applied: '03 Dec 2025', lastDay: '2026-02-03', reason: 'Better opportunity', status: 'Pending', comments: 'Offered a senior role.' }
];

// 2. Render Table
function renderTable() {
    const tbody = document.getElementById('resignationTableBody');
    tbody.innerHTML = '';

    resignationData.forEach((item) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.applied}</td>
            <td>${formatDate(item.lastDay)}</td>
            <td>${item.reason}</td>
            <td><span class="status-badge status-${item.status.toLowerCase()}">${item.status}</span></td>
            <td style="text-align: right;">
                <button class="action-btn btn-view" onclick="viewDetails(${item.id})">View Details</button>
                ${item.status === 'Pending' ? `<button class="action-btn btn-cancel" onclick="cancelRequest(${item.id})">Cancel</button>` : ''}
            </td>
        `;
        tbody.appendChild(row);
    });
}

// 3. Form Submission
document.getElementById('resignationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const newEntry = {
        id: Date.now(),
        applied: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
        lastDay: document.getElementById('lastWorkingDay').value,
        reason: document.getElementById('reason').value,
        status: 'Pending',
        comments: document.getElementById('comments').value || 'N/A'
    };

    resignationData.unshift(newEntry);
    renderTable();
    toggleModal('resignationModal', false);
    this.reset();
});

// 4. View Details Functionality (Notice Period Logic)
function viewDetails(id) {
    const item = resignationData.find(x => x.id === id);
    const appliedDate = new Date(item.applied);
    const lastDayDate = new Date(item.lastDay);
    
    // Calculate difference in days for notice period
    const diffTime = Math.abs(lastDayDate - appliedDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    const content = `
        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">${item.status}</span></div>
        <div class="detail-row"><span class="detail-label">Application Date</span><span class="detail-value">${item.applied}</span></div>
        <div class="detail-row"><span class="detail-label">Last Working Day</span><span class="detail-value">${formatDate(item.lastDay)}</span></div>
        <div class="detail-row"><span class="detail-label">Notice Period Served</span><span class="detail-value">${diffDays} Days</span></div>
        <div class="detail-row" style="border:none;"><span class="detail-label">Comments</span><span class="detail-value">${item.comments}</span></div>
    `;
    
    document.getElementById('detailsContent').innerHTML = content;
    toggleModal('detailsModal', true);
}

// 5. Cancel Functionality
function cancelRequest(id) {
    if(confirm('Are you sure you want to cancel this resignation request?')) {
        resignationData = resignationData.filter(item => item.id !== id);
        renderTable();
    }
}

// Helpers
function toggleModal(id, show) {
    document.getElementById(id).style.display = show ? 'flex' : 'none';
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Initial Run
renderTable();

// Sidebar logic (Keep your existing)
const secondary = document.querySelector('.sidebar-secondary');
const mainContent = document.getElementById('mainContent');
if (secondary) {
    const observer = new MutationObserver(() => {
        const isOpen = secondary.classList.contains('open');
        mainContent.classList.toggle('secondary-visible', isOpen);
    });
    observer.observe(secondary, { attributes: true, attributeFilter: ['class'] });
}
</script>
</body>
</html>