<?php include('../sidebars.php'); ?>

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
            --accent-orange: #ff7849; /* Matching the orange button in your screenshot */
            --bg-light: #f7f7f7;
            --text-dark: #333;
            --text-muted: #666;
            --border-color: #eee;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            display: flex; /* Aligns sidebar and content */
            color: var(--text-dark);
        }

        .main-content {
            flex: 1;
            padding: 25px;
            overflow-x: hidden;
        }

        /* Header Styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .breadcrumb-section h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        .breadcrumb {
            list-style: none;
            display: flex;
            padding: 0;
            margin: 5px 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        .breadcrumb li:not(:last-child)::after {
            content: ">";
            margin: 0 8px;
        }

        /* Add Budget Button - Orange as per screenshot */
        .btn-add {
            background-color: var(--accent-orange);
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }

        .btn-add:hover {
            background-color: #e66a3e;
        }

        /* Card / Table Container */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Table Design */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead tr {
            background-color: #f9fafb;
        }

        th {
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-dark);
            font-weight: 600;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-muted);
            vertical-align: middle;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 12px;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #888;
            padding: 5px;
        }

        .edit-icon:hover { color: #555; }
        .delete-icon:hover { color: #e54d42; }

        /* Pagination */
        .pagination-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            font-size: 14px;
        }

        .page-link {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-dark);
            margin-left: 5px;
        }

        .page-link.active {
            background-color: var(--accent-orange);
            color: white;
            border-color: var(--accent-orange);
        }

        input[type="text"], select {
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 6px;
            outline: none;
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <div class="breadcrumb-section">
            <h2>Budgets</h2>
            <ul class="breadcrumb">
                <li><i class="fa fa-home"></i></li>
                <li>Accounting</li>
                <li style="color: #333; font-weight: 500;">Budgets</li>
            </ul>
        </div>
        <button class="btn-add"><i class="fa fa-plus-circle"></i> Add Budget</button>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 style="margin:0; font-size:18px;">Budget List</h3>
            <div>
                <span style="font-size: 14px; color: #888;">Sort By: </span>
                <select>
                    <option>Last 7 Days</option>
                    <option>Last Month</option>
                </select>
            </div>
        </div>

        <div class="table-controls">
            <div>
                Row Per Page 
                <select>
                    <option>10</option>
                    <option>25</option>
                </select>
                Entries
            </div>
            <div>
                <input type="text" placeholder="Search budgets...">
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox"></th>
                        <th>Budget Title <i class="fa fa-sort" style="font-size:10px; opacity:0.5;"></i></th>
                        <th>Budget Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Total Revenue</th>
                        <th>Total Expense</th>
                        <th>Tax Amount</th>
                        <th>Budget Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $budgets = [
                        ["title" => "Office Supplies", "type" => "Category", "start" => "14 Jan 2024", "end" => "13 Nov 2024", "rev" => "250,000", "exp" => "150,000", "tax" => "10,000", "amt" => "90,000"],
                        ["title" => "Recruitment", "type" => "Category", "start" => "21 Jan 2024", "end" => "20 Nov 2024", "rev" => "300,000", "exp" => "200,000", "tax" => "15,000", "amt" => "85,000"],
                        ["title" => "Tender", "type" => "Project", "start" => "10 Feb 2024", "end" => "08 Dec 2024", "rev" => "200,000", "exp" => "170,000", "tax" => "5,000", "amt" => "25,000"],
                        ["title" => "Salary 2024", "type" => "Category", "start" => "18 Feb 2024", "end" => "16 Dec 2024", "rev" => "300,000", "exp" => "200,000", "tax" => "15,000", "amt" => "85,000"],
                    ];

                    foreach ($budgets as $row) {
                        echo "<tr>
                                <td><input type='checkbox'></td>
                                <td style='color:#333; font-weight:600;'>{$row['title']}</td>
                                <td>{$row['type']}</td>
                                <td>{$row['start']}</td>
                                <td>{$row['end']}</td>
                                <td>{$row['rev']}</td>
                                <td>{$row['exp']}</td>
                                <td>{$row['tax']}</td>
                                <td style='font-weight:700; color:#000;'>{$row['amt']}</td>
                                <td>
                                    <div class='action-btns'>
                                        <button class='btn-icon edit-icon'><i class='fa fa-edit'></i></button>
                                        <button class='btn-icon delete-icon'><i class='fa fa-trash-alt'></i></button>
                                    </div>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-area">
            <div style="color: #888;">Showing 1 - 4 of 4 entries</div>
            <div class="page-numbers">
                <a href="#" class="page-link"><i class="fa fa-chevron-left"></i></a>
                <a href="#" class="page-link active">1</a>
                <a href="#" class="page-link"><i class="fa fa-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <footer style="margin-top: 40px; font-size: 12px; color: #aaa; display: flex; justify-content: space-between; border-top: 1px solid #eee; padding-top: 15px;">
        <span>2014 - 2026 © SmartHR.</span>
        <span>Designed & Developed By <span style="color: var(--primary-teal); font-weight: 600;">Dreams</span></span>
    </footer>
</div>

</body>
</html>