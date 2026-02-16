<?php 
include '../sidebars.php'; 
include '../header.php';
// Uncomment in production
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | AI Recruiting (ATS)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Flatpickr for date range picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root {
            --primary: #1b5a5a;
            --primary-dark: #134444;
            --secondary: #64748b;
            --bg-light: #f8fafc;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-light: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #e2e8f0;
        }

        body { background: var(--bg-light); color: var(--text-main); margin: 0; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }

        main#content-wrapper {
            margin-left: 95px;
            padding-top: 70px;
            padding-bottom: 40px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .sidebar-secondary.open ~ main#content-wrapper {
            margin-left: calc(95px + 220px);
        }

        .container { max-width: 1440px; margin: 0 auto; padding: 0 20px; }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px -2px rgba(0,0,0,0.07);
            border: 1px solid #e5e7eb;
            margin-bottom: 32px;
            overflow: hidden;
        }

        .upload-zone {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 50px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .upload-zone:hover {
            border-color: var(--primary);
            background: rgba(27,90,90,0.04);
        }

        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }

        .btn-outline { border: 1px solid #d1d5db; background: white; color: #374151; }
        .btn-outline:hover { background: #f3f4f6; }

        .table-container { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; }

        th {
            background: #f8fafc;
            color: #4b5563;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        td { padding: 14px 16px; color: #374151; }

        tr:hover { background: #f9fafb; }

        .status-badge {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; }

        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
        }

        .toast {
            background: white;
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.12);
            border-left: 4px solid var(--primary);
            margin-top: 10px;
            min-width: 280px;
        }

        .flatpickr-input {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.875rem;
            width: 220px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<main id="content-wrapper">

    <div class="container py-6">

        <h1 class="text-3xl font-bold mb-8 text-gray-800">AI Recruiting (ATS)</h1>

        <!-- 1. AI Resume Shortlisting + Upload -->
        <div class="card p-6">
            <h3 class="text-xl font-semibold mb-5">AI Resume Shortlisting</h3>

            <div class="flex flex-wrap gap-3 mb-6 items-center">
                <input type="text" id="keywordsInput" placeholder="Keywords: Python, React, Manager, AWS, 5+ years..." 
                       class="flex-1 min-w-[300px] p-3 border border-gray-300 rounded-lg focus:border-[var(--primary)] outline-none">
                <button class="btn btn-primary" onclick="simulateAIAnalysis()">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Run AI Matching
                </button>
                <!-- Button now triggers dynamic file input -->
                <button class="btn btn-outline" onclick="triggerFileUpload()">
                    <i class="fa-solid fa-upload"></i> Bulk Upload Resumes
                </button>
            </div>

            <!-- Drag & Drop Zone -->
            <div class="upload-zone" id="dropZone" onclick="triggerFileUpload()">
                <i class="fa-solid fa-cloud-arrow-up text-5xl text-[var(--secondary)] mb-4"></i>
                <p class="text-lg font-medium mb-2">Drag & Drop Resumes Here</p>
                <p class="text-[var(--text-light)]">or click to browse (PDF, DOCX supported)</p>
            </div>

            <div id="ai-loader" class="text-center py-10 hidden">
                <div class="w-12 h-12 border-4 border-gray-200 border-t-[var(--primary)] rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-gray-600">AI is analyzing resumes...</p>
            </div>
        </div>

        <!-- 2. Resume Parsing / Parsed Candidates Table -->
        <div class="card">

            <div class="p-5 border-b flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-800">Resume Parsing</h2>

                <div class="flex flex-wrap items-center gap-3">
                    <input type="text" id="dateRange" class="flatpickr-input" placeholder="Select date range..." readonly>

                    <select id="designationFilter" class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] min-w-[160px]">
                        <option value="">All Designations</option>
                        <option value="Technician">Technician</option>
                        <option value="Web Developer">Web Developer</option>
                        <option value="Sales Executive Officer">Sales Executive Officer</option>
                        <option value="Designer">Designer</option>
                        <option value="Accountant">Accountant</option>
                        <option value="App Developer">App Developer</option>
                    </select>

                    <select id="sortBy" class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] min-w-[180px]">
                        <option value="last7days">Sort By: Last 7 Days</option>
                        <option value="last30days">Last 30 Days</option>
                        <option value="thisYear">This Year</option>
                        <option value="name-asc">Name (A–Z)</option>
                        <option value="name-desc">Name (Z–A)</option>
                    </select>

                    <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium flex items-center gap-2 border border-gray-300">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>

            <div class="p-5 flex flex-wrap items-center justify-between gap-4 border-b">
                <div class="flex items-center gap-3 text-sm">
                    <span>Rows per page</span>
                    <select id="rowsPerPage" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                <div class="relative w-64">
                    <input type="text" id="searchInput" placeholder="Search candidates..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--primary)]">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <div class="table-container">
                <table id="candidatesTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Cand ID</th>
                            <th>Candidate</th>
                            <th>Applied Role</th>
                            <th>Phone</th>
                            <!-- Location and Experience columns removed as requested -->
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="parsed-candidates-body"></tbody>
                </table>
            </div>

            <div class="p-5 flex items-center justify-between border-t text-sm text-gray-600">
                <div id="showingInfo">Showing 1–10 of 0 entries</div>
                <div class="flex gap-2">
                    <button id="prevPage" class="px-4 py-2 border rounded hover:bg-gray-100 disabled:opacity-50" disabled>Previous</button>
                    <button id="nextPage" class="px-4 py-2 border rounded hover:bg-gray-100 disabled:opacity-50" disabled>Next</button>
                </div>
            </div>

        </div>

    </div>

</main>

<div id="toast-container"></div>

<script>
// ────────────────────────────────────────────────
// Sample data (Location and Experience removed)
// ────────────────────────────────────────────────
const candidates = [
    { id: "Cand-003", name: "John Harris", email: "john@example.com", role: "Technician", phone: "(196) 2348 947", img: "https://i.pravatar.cc/150?u=1", added: "2026-02-10" },
    { id: "Cand-004", name: "Carole Langan", email: "carole@example.com", role: "Web Developer", phone: "(138) 6487 295", img: "https://i.pravatar.cc/150?u=2", added: "2026-02-12" },
    { id: "Cand-005", name: "Charles Marks", email: "charles@example.com", role: "Sales Executive Officer", phone: "(154) 6485 218", img: "https://i.pravatar.cc/150?u=3", added: "2026-02-09" },
    { id: "Cand-006", name: "Kerry Drake", email: "kerry@example.com", role: "Designer", phone: "(185) 5947 097", img: "https://i.pravatar.cc/150?u=4", added: "2026-02-14" },
    { id: "Cand-007", name: "David Carmona", email: "david@example.com", role: "Accountant", phone: "(106) 3485 978", img: "https://i.pravatar.cc/150?u=5", added: "2026-02-11" },
];

// ────────────────────────────────────────────────
// Table state
// ────────────────────────────────────────────────
let currentPage = 1;
let rowsPerPage = 10;
let filtered = [...candidates];

// ────────────────────────────────────────────────
// DOM references
// ────────────────────────────────────────────────
const tableBody     = document.getElementById('parsed-candidates-body');
const showingInfo   = document.getElementById('showingInfo');
const searchInput   = document.getElementById('searchInput');
const designation   = document.getElementById('designationFilter');
const sortSelect    = document.getElementById('sortBy');
const rowsSelect    = document.getElementById('rowsPerPage');
const prevBtn       = document.getElementById('prevPage');
const nextBtn       = document.getElementById('nextPage');

// ────────────────────────────────────────────────
// Render function (Removed Location/Exp columns)
// ────────────────────────────────────────────────
function render() {
    tableBody.innerHTML = '';

    const start = (currentPage - 1) * rowsPerPage;
    const end   = start + rowsPerPage;
    const pageData = filtered.slice(start, end);

    pageData.forEach(c => {
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        tr.innerHTML = `
            <td class="p-4"><input type="checkbox" class="rounded"></td>
            <td class="p-4 font-medium text-gray-700">${c.id}</td>
            <td class="p-4">
                <div class="flex items-center gap-3">
                    <img src="${c.img}" class="w-10 h-10 rounded-full border" alt="">
                    <div>
                        <div class="font-semibold">${c.name}</div>
                        <div class="text-xs text-gray-500">${c.email}</div>
                    </div>
                </div>
            </td>
            <td class="p-4 text-gray-600">${c.role}</td>
            <td class="p-4 text-gray-600">${c.phone}</td>
            <!-- Removed Location and Experience Columns -->
            <td class="p-4">
                <span class="status-badge">
                    <span class="status-dot"></span> Parsed
                </span>
            </td>
            <td class="p-4 text-center">
                <div class="flex justify-center gap-5 text-gray-500">
                    <button class="hover:text-[var(--primary)]"><i class="far fa-file-alt"></i></button>
                    <button class="hover:text-[var(--primary)]"><i class="fas fa-download"></i></button>
                </div>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    showingInfo.textContent = `Showing ${start+1}–${Math.min(end, filtered.length)} of ${filtered.length} entries`;
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = end >= filtered.length;
}

// ────────────────────────────────────────────────
// Filter & sort logic (Removed Exp sort)
// ────────────────────────────────────────────────
function filterAndSort() {
    let data = [...candidates];

    // Search
    const term = searchInput.value.toLowerCase().trim();
    if (term) {
        data = data.filter(c =>
            c.name.toLowerCase().includes(term) ||
            c.email.toLowerCase().includes(term) ||
            c.role.toLowerCase().includes(term)
        );
    }

    // Designation
    const role = designation.value;
    if (role) data = data.filter(c => c.role === role);

    // Sort
    const sortVal = sortSelect.value;
    if (sortVal === 'name-asc')  data.sort((a,b) => a.name.localeCompare(b.name));
    if (sortVal === 'name-desc') data.sort((a,b) => b.name.localeCompare(a.name));

    filtered = data;
    currentPage = 1;
    render();
}

// ────────────────────────────────────────────────
// Event listeners
// ────────────────────────────────────────────────
searchInput.addEventListener('input', filterAndSort);
designation.addEventListener('change', filterAndSort);
sortSelect.addEventListener('change', filterAndSort);
rowsSelect.addEventListener('change', () => {
    rowsPerPage = parseInt(rowsSelect.value);
    currentPage = 1;
    render();
});

prevBtn.addEventListener('click', () => { if (currentPage > 1) { currentPage--; render(); } });
nextBtn.addEventListener('click', () => { if ((currentPage * rowsPerPage) < filtered.length) { currentPage++; render(); } });

// Date picker (for future date filtering)
flatpickr("#dateRange", {
    mode: "range",
    dateFormat: "d/m/Y",
    defaultDate: ["2026/02/10", "2026/02/16"],
    onChange: (selectedDates) => {
        if (selectedDates.length === 2) {
            console.log("Selected range:", selectedDates);
        }
    }
});

// Initial render
render();

// ────────────────────────────────────────────────
// File Upload Logic (Dynamic Input)
// ────────────────────────────────────────────────
const dropZone = document.getElementById('dropZone');

function triggerFileUpload() {
    // Dynamically create file input to avoid showing the "No file chosen" text statically
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.accept = '.pdf,.doc,.docx,.txt';
    input.style.display = 'none';
    
    input.onchange = (e) => {
        const files = e.target.files;
        if (!files.length) return;
        showToast(`Processing ${files.length} resume${files.length > 1 ? 's' : ''}...`);
        // Clean up
        document.body.removeChild(input);
    };

    document.body.appendChild(input);
    input.click();
}

// Drag and Drop visual effects
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = 'var(--primary)';
    dropZone.style.background = 'rgba(27,90,90,0.04)';
});

dropZone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#d1d5db';
    dropZone.style.background = 'transparent';
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#d1d5db';
    dropZone.style.background = 'transparent';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        showToast(`Processing ${files.length} resume${files.length > 1 ? 's' : ''}...`);
    }
});

// ────────────────────────────────────────────────
// AI Simulation Logic
// ────────────────────────────────────────────────
function simulateAIAnalysis() {
    const kw = document.getElementById('keywordsInput').value.trim();
    if (!kw) return showToast('Enter keywords first', 'error');

    document.getElementById('ai-loader').classList.remove('hidden');

    setTimeout(() => {
        document.getElementById('ai-loader').classList.add('hidden');

        const mock = [
            { name: "Priya Sharma", role: "Full Stack Developer" },
            { name: "Ananya Krishnan", role: "React Native Developer" },
            { name: "Michael Chen", role: "Backend Engineer" }
        ];
        
        const item = mock[Math.floor(Math.random() * mock.length)];
        const newId = "Cand-" + Math.floor(100 + Math.random() * 900);
        
        // Add new candidate to the top of the list
        const newCandidate = {
            id: newId,
            name: item.name,
            email: item.name.toLowerCase().replace(' ', '.') + "@example.com",
            role: item.role,
            phone: "(555) " + Math.floor(100 + Math.random() * 900) + "-" + Math.floor(1000 + Math.random() * 9000),
            img: `https://i.pravatar.cc/150?u=${Math.random()}`,
            added: new Date().toISOString().split('T')[0]
        };

        candidates.unshift(newCandidate); // Add to beginning of array
        filterAndSort(); // Re-render table with new data

        showToast(`AI Match Found: ${item.name} added to list.`);
    }, 2200);
}

function showToast(msg, type = 'success') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = 'toast';
    t.style.borderLeftColor = type === 'error' ? 'var(--danger)' : 'var(--primary)';
    t.innerHTML = `<strong>${msg}</strong>`;
    c.appendChild(t);
    setTimeout(() => { 
        t.style.opacity = '0'; 
        t.style.transition = 'opacity 0.4s';
        setTimeout(() => t.remove(), 400); 
    }, 3400);
}
</script>

</body>
</html>
