<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgets - SmartHR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-teal: #1f5a54; 
            --primary-orange: #f66d2f; /* Matching the screenshot orange */
            --bg-light: #f7f7f7;
            --text-dark: #333;
            --text-muted: #666;
            --border-color: #eee;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            padding: 20px;
            color: var(--text-dark);
        }

        /* Header Styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .breadcrumb-section h2 { margin: 0; font-size: 24px; font-weight: 700; }

        .breadcrumb {
            list-style: none; display: flex; padding: 0; margin: 5px 0;
            font-size: 14px; color: var(--text-muted);
        }

        .breadcrumb li:not(:last-child)::after { content: ">"; margin: 0 8px; }

        .btn-add {
            background-color: var(--primary-teal);
            color: white; border: none; padding: 10px 20px;
            border-radius: 5px; cursor: pointer; font-weight: 500;
            display: flex; align-items: center; gap: 8px;
        }

        /* --- MODAL STYLES --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: none; /* Hidden by default */
            justify-content: center; align-items: center; z-index: 1000;
        }

        .modal-content {
            background: white; width: 650px; border-radius: 12px;
            max-height: 90vh; overflow-y: auto; position: relative;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .modal-header {
            padding: 20px; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }

        .modal-header h3 { margin: 0; font-size: 20px; color: #212b36; }

        .btn-close-circle {
            background: #919eab; color: white; border: none;
            width: 24px; height: 24px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
        }

        .modal-body { padding: 25px; }

        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #333; }
        
        .form-control {
            width: 100%; padding: 12px; border: 1px solid #dce0e4;
            border-radius: 6px; box-sizing: border-box; margin-bottom: 15px;
            font-family: inherit;
        }

        .row { display: flex; gap: 20px; margin-bottom: 15px; }
        .col { flex: 1; }

        .radio-group { display: flex; gap: 20px; margin-bottom: 15px; }
        .radio-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }

        .section-header { font-weight: 700; font-size: 15px; color: #1f2937; margin: 20px 0 10px; }

        .input-group-plus { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        
        .btn-plus {
            background-color: var(--primary-orange); color: white;
            border: none; width: 34px; height: 34px; border-radius: 8px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }

        .modal-footer {
            padding: 20px; border-top: 1px solid var(--border-color);
            display: flex; justify-content: flex-end; gap: 12px;
        }

        .btn-cancel {
            background: #f4f6f8; color: #333; border: none;
            padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: 500;
        }

        .btn-submit {
            background: var(--primary-orange); color: white; border: none;
            padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: 600;
        }

        /* Card / Table Container */
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead { background-color: #f9fafb; }
        th { text-align: left; padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-dark); font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid var(--border-color); color: var(--text-muted); }
        .action-btns { display: flex; gap: 10px; }
        .btn-icon { background: none; border: none; cursor: pointer; font-size: 16px; }
        .edit-icon { color: #555; }
        .delete-icon { color: #e54d42; }
        .pagination-area { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 14px; color: var(--text-muted); }
        .page-numbers { display: flex; gap: 5px; }
        .page-link { padding: 5px 12px; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; color: var(--text-dark); }
        .page-link.active { background-color: var(--primary-teal); color: white; border-color: var(--primary-teal); }
    </style>
</head>
<body>

    <div class="modal-overlay" id="addBudgetModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Budget</h3>
                <button class="btn-close-circle" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <label class="form-label">Budget Title</label>
                    <input type="text" class="form-control" placeholder="Enter Budget Title">

                    <label class="form-label">Choose Budget respect type</label>
                    <div class="radio-group">
                        <label class="radio-item"><input type="radio" name="b_type"> Project</label>
                        <label class="radio-item"><input type="radio" name="b_type"> Category</label>
                    </div>

                    <div class="row">
                        <div class="col">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control">
                        </div>
                    </div>

                    <div class="section-header">Expected Revenues</div>
                    <div class="row">
                        <div class="col">
                            <label class="form-label">Revenue Title</label>
                            <input type="text" class="form-control" placeholder="Revenue Title">
                        </div>
                        <div class="col">
                            <label class="form-label">Revenue Amount</label>
                            <div class="input-group-plus">
                                <input type="number" class="form-control" style="margin-bottom:0;" placeholder="Amount">
                                <button type="button" class="btn-plus">+</button>
                            </div>
                        </div>
                    </div>

                    <label class="form-label">Overall Revenue (A)</label>
                    <input type="text" class="form-control" readonly placeholder="0">

                    <div class="section-header">Expected Expenses</div>
                    <div class="row">
                        <div class="col">
                            <label class="form-label">Expenses Title</label>
                            <input type="text" class="form-control" placeholder="Expense Title">
                        </div>
                        <div class="col">
                            <label class="form-label">Expenses Amount</label>
                            <div class="input-group-plus">
                                <input type="number" class="form-control" style="margin-bottom:0;" placeholder="Amount">
                                <button type="button" class="btn-plus">+</button>
                            </div>
                        </div>
                    </div>

                    <label class="form-label">Overall Expense (B)</label>
                    <input type="text" class="form-control" readonly placeholder="0">

                    <div class="row">
                        <div class="col">
                            <label class="form-label">Expected Profit (C=A-B)</label>
                            <input type="text" class="form-control" readonly placeholder="0">
                        </div>
                        <div class="col">
                            <label class="form-label">Tax (D)</label>
                            <input type="number" class="form-control" placeholder="0">
                        </div>
                    </div>

                    <label class="form-label">Budget Amount (E=C-D)</label>
                    <input type="text" class="form-control" readonly placeholder="0">

                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-submit">Add Budget</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="page-header">
        <div class="breadcrumb-section">
            <h2>Budgets</h2>
            <ul class="breadcrumb">
                <li><i class="fa fa-home"></i></li>
                <li>Accounting</li>
                <li style="color: #333;">Budgets</li>
            </ul>
        </div>
        <div>
            <button class="btn-add" onclick="openModal()"><i class="fa fa-plus-circle"></i> Add Budget</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 style="margin:0; font-size:18px;">Budget List</h3>
            <div>
                <span style="font-size: 14px;">Sort By: </span>
                <select>
                    <option>Last 7 Days</option>
                    <option>Last Month</option>
                </select>
            </div>
        </div>

        <div class="table-controls">
            <div>Row Per Page <select><option>10</option><option>25</option></select> Entries</div>
            <div>Search: <input type="text" placeholder="Search..."></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox"></th>
                    <th>Budget Title <i class="fa fa-sort"></i></th>
                    <th>Budget Type <i class="fa fa-sort"></i></th>
                    <th>Start Date <i class="fa fa-sort"></i></th>
                    <th>End Date <i class="fa fa-sort"></i></th>
                    <th>Total Revenue <i class="fa fa-sort"></i></th>
                    <th>Total Expense <i class="fa fa-sort"></i></th>
                    <th>Tax Amount <i class="fa fa-sort"></i></th>
                    <th>Budget Amount <i class="fa fa-sort"></i></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $budgets = [
                    ["title" => "Office Supplies", "type" => "Category", "start" => "14 Jan 2024", "end" => "13 Nov 2024", "rev" => "250000", "exp" => "150000", "tax" => "10000", "amt" => "90000"],
                    ["title" => "Recruitment", "type" => "Category", "start" => "21 Jan 2024", "end" => "20 Nov 2024", "rev" => "300000", "exp" => "200000", "tax" => "15000", "amt" => "85000"],
                    ["title" => "Tender", "type" => "Project", "start" => "10 Feb 2024", "end" => "08 Dec 2024", "rev" => "200000", "exp" => "170000", "tax" => "5000", "amt" => "25000"],
                    ["title" => "Salary 2024", "type" => "Category", "start" => "18 Feb 2024", "end" => "16 Dec 2024", "rev" => "300000", "exp" => "200000", "tax" => "15000", "amt" => "85000"],
                ];

                foreach ($budgets as $row) {
                    echo "<tr>
                            <td><input type='checkbox'></td>
                            <td style='color:#333; font-weight:500;'>{$row['title']}</td>
                            <td>{$row['type']}</td>
                            <td>{$row['start']}</td>
                            <td>{$row['end']}</td>
                            <td>" . number_format($row['rev']) . "</td>
                            <td>" . number_format($row['exp']) . "</td>
                            <td>" . number_format($row['tax']) . "</td>
                            <td style='font-weight:600; color:#333;'>" . number_format($row['amt']) . "</td>
                            <td>
                                <div class='action-btns'>
                                    <button class='btn-icon edit-icon' title='Edit'><i class='fa fa-edit'></i></button>
                                    <button class='btn-icon delete-icon' title='Delete'><i class='fa fa-trash'></i></button>
                                </div>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>

        <div class="pagination-area">
            <div>Showing 1 - 4 of 4 entries</div>
            <div class="page-numbers">
                <a href="#" class="page-link"><i class="fa fa-chevron-left"></i></a>
                <a href="#" class="page-link active">1</a>
                <a href="#" class="page-link"><i class="fa fa-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <footer style="margin-top: 30px; font-size: 12px; color: #999; display: flex; justify-content: space-between;">
        <span>2014 - 2026 © SmartHR.</span>
        <span>Designed & Developed By <span style="color: var(--primary-teal); font-weight: 600;">Dreams</span></span>
    </footer>

    <script>
        const modal = document.getElementById('addBudgetModal');

        function openModal() {
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close modal if user clicks outside the content box
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>