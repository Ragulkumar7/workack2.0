<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | AI Recruiting (ATS)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #1b5a5a;           /* ← your premium colour */
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
            --transition: all 0.3s ease;
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1, h2, h3 {
            color: var(--text-main);
        }

        .card {
            background: var(--surface);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 50px 20px;
            text-align: center;
            background: var(--surface);
            cursor: pointer;
            transition: var(--transition);
            margin: 24px 0;
        }

        .upload-zone:hover {
            border-color: var(--primary);
            background: rgba(27,90,90,0.05);
        }

        input[type="text"] {
            width: 100%;
            max-width: 500px;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
        }

        input[type="text"]:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
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

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
        }

        .table-container {
            overflow-x: auto;
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
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
            background: #f1f5f9;
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .loader {
            text-align: center;
            padding: 40px 0;
            display: none;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        /* Toast */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
        }

        .toast {
            background: white;
            padding: 16px 24px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            border-left: 5px solid var(--primary);
            margin-top: 12px;
            animation: slideIn 0.4s ease;
            min-width: 280px;
        }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- 
        You can include your header.php here 
        <?php include 'header.php'; ?>
    -->

    <div class="container">

        <h1 style="margin-bottom: 32px;">AI Recruiting (ATS)</h1>

        <div class="card">
            <h3 style="margin-bottom: 16px;">AI Resume Shortlisting</h3>
            
            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px;">
                <input type="text" id="keywordsInput" placeholder="Keywords: Python, React, Manager, AWS, 5+ years..." style="flex: 1; min-width: 320px;">
                <button class="btn btn-primary" onclick="simulateAIAnalysis()">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Run AI Matching
                </button>
            </div>

            <div class="upload-zone" id="dropZone">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 3rem; color: var(--secondary); margin-bottom: 16px;"></i>
                <p style="font-size: 1.1rem; margin-bottom: 8px;">Drag & Drop Resumes Here</p>
                <p style="color: var(--text-light);">or click to browse (PDF, DOCX supported)</p>
            </div>

            <div id="ai-loader" class="loader">
                <div class="spinner"></div>
                <p style="color: var(--text-light);">AI is analyzing resumes...</p>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 16px;">Shortlisted Candidates</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Role / Position</th>
                            <th>Match Score</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ats-table-body">
                        <!-- Rows added dynamically -->
                        <tr>
                            <td>Alex Johnson</td>
                            <td>Senior Software Engineer</td>
                            <td><span style="color: var(--success); font-weight: bold;">92%</span></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="shortlistCandidate(this)">Shortlist</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="toast-container"></div>

    <script>
        function simulateAIAnalysis() {
            const keywords = document.getElementById('keywordsInput').value.trim();
            if (!keywords) {
                showToast('Please enter some keywords first', 'error');
                return;
            }

            const loader = document.getElementById('ai-loader');
            loader.style.display = 'block';

            setTimeout(() => {
                loader.style.display = 'none';

                const mockCandidates = [
                    { name: "Priya Sharma", role: "Full Stack Developer", base: 88 },
                    { name: "Rahul Menon", role: "DevOps Engineer", base: 79 },
                    { name: "Ananya Krishnan", role: "React Native Developer", base: 94 },
                    { name: "Vikram Singh", role: "Backend Engineer - Node.js", base: 85 },
                    { name: "Sneha Raj", role: "Technical Lead", base: 91 }
                ];

                const tbody = document.getElementById('ats-table-body');

                // Add one random good match
                const rand = Math.floor(Math.random() * mockCandidates.length);
                const candidate = mockCandidates[rand];
                const score = Math.min(98, candidate.base + Math.floor(Math.random() * 12));

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${candidate.name}</td>
                    <td>${candidate.role}</td>
                    <td><span style="color: ${score >= 90 ? 'var(--success)' : 'var(--warning)'}; font-weight: bold;">${score}%</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="shortlistCandidate(this)">Shortlist</button>
                    </td>
                `;

                tbody.insertBefore(row, tbody.firstChild);
                showToast(`AI matching complete — found high potential match (${score}%)`);

            }, 2200);
        }

        function shortlistCandidate(btn) {
            btn.textContent = "Shortlisted";
            btn.disabled = true;
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline');
            showToast('Candidate has been shortlisted and moved to next stage.');
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.borderLeftColor = type === 'error' ? 'var(--danger)' : 'var(--primary)';
            toast.innerHTML = `<strong>${message}</strong>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 400);
            }, 3400);
        }

        // Optional: click to trigger file input simulation
        document.getElementById('dropZone').addEventListener('click', () => {
            showToast('File upload simulation — in real system this opens file picker');
        });
    </script>

    <!-- 
        You can include your sidebars.php or footer here if needed
        <?php include 'sidebars.php'; ?>
    -->

</body>
</html>