<?php include('../sidebars.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Revenue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; overflow-x: hidden; }
        
        /* Layout Structure - Eliminates the Gap */
        .wrapper { 
            display: flex; 
            width: 100%; 
            align-items: stretch; 
        }

        #sidebar { 
            min-width: 80px; 
            max-width: 80px;
            background-color: #fff;
            border-right: 1px solid #e0e0e0;
            min-height: 100vh;
            z-index: 1000;
        }

        #content { 
            flex-grow: 1; 
            width: 100%; 
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        /* Inner spacing for content area */
        .page-inner-content {
            padding: 20px 30px;
        }

        /* UI Elements */
        .card { border: none; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .breadcrumb { font-size: 0.9rem; color: #6c757d; }
        .breadcrumb-item i { margin-right: 5px; }
        .btn-orange { background-color: #ff6b35; color: white; border-radius: 6px; border: none; }
        .btn-orange:hover { background-color: #e85a2a; color: white; }
        .table thead th { background-color: #f1f4f8; border-bottom: none; color: #333; font-weight: 600; font-size: 0.9rem; }
        .table tbody td { vertical-align: middle; color: #555; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .action-btns i { cursor: pointer; margin: 0 5px; color: #999; transition: 0.2s; }
        .action-btns i:hover { color: #333; }
        .form-label { color: #33475b; font-weight: 600; }
    </style>
</head>
<body>

<div class="wrapper">
    <nav id="sidebar">
        <?php // Sidebar content loaded from sidebars.php ?>
    </nav>

    <div id="content">
        
        <?php include('../header.php'); ?>

        <div class="page-inner-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1" style="color: #002b5b;">Budget Revenue</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
                            <li class="breadcrumb-item">Accounting</li>
                            <li class="breadcrumb-item active">Budget Revenue</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-orange px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addRevenueModal">
                        <i class="fa fa-plus-circle me-2"></i> Add Revenue
                    </button>
                </div>
            </div>

            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Budget Revenue List</h5>
                    <div class="dropdown">
                        <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Sort By : Last 7 Days
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">Recently Added</a></li>
                            <li><a class="dropdown-item" href="#">Ascending</a></li>
                            <li><a class="dropdown-item" href="#">Descending</a></li>
                        </ul>
                    </div>
                </div>

                <div class="row mb-3 align-items-center">
                    <div class="col-md-6 d-flex align-items-center gap-2">
                        <span class="text-muted small">Row Per Page</span>
                        <select class="form-select w-auto form-select-sm">
                            <option>10</option>
                            <option>25</option>
                            <option>50</option>
                        </select>
                        <span class="text-muted small">Entries</span>
                    </div>
                    <div class="col-md-6 text-end">
                        <input type="text" class="form-control form-control-sm d-inline-block w-50" placeholder="Search">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table" id="revenueTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="form-check-input"></th>
                                <th>Revenue Name <i class="fa fa-sort ms-1 opacity-50"></i></th>
                                <th>Category Name <i class="fa fa-sort ms-1 opacity-50"></i></th>
                                <th>Sub Category Name <i class="fa fa-sort ms-1 opacity-50"></i></th>
                                <th>Amount <i class="fa fa-sort ms-1 opacity-50"></i></th>
                                <th>Expense Date <i class="fa fa-sort ms-1 opacity-50"></i></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $revenues = [
                                ['id' => 1, 'name' => 'Training Programs', 'cat' => 'Training', 'sub' => 'Employee Training', 'amt' => '20000', 'date' => '14 Jan 2024'],
                                ['id' => 2, 'name' => 'Premium Support Packages', 'cat' => 'Support & Maintenance', 'sub' => 'Premium Support', 'amt' => '40000', 'date' => '21 Jan 2024'],
                                ['id' => 3, 'name' => 'Consulting Services', 'cat' => 'Services', 'sub' => 'Consulting', 'amt' => '10000', 'date' => '10 Feb 2024'],
                                ['id' => 4, 'name' => 'Subscription Fees', 'cat' => 'Platform Fees', 'sub' => 'Subscription Plans', 'amt' => '20000', 'date' => '18 Feb 2024'],
                            ];

                            foreach ($revenues as $row) {
                                echo "<tr id='row-{$row['id']}'>
                                    <td><input type='checkbox' class='form-check-input'></td>
                                    <td class='fw-bold' style='color:#002b5b;'>{$row['name']}</td>
                                    <td>{$row['cat']}</td>
                                    <td>{$row['sub']}</td>
                                    <td>{$row['amt']}</td>
                                    <td>{$row['date']}</td>
                                    <td class='action-btns'>
                                        <i class='fa fa-trash' onclick=\"deleteRow('row-{$row['id']}')\"></i>
                                    </td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addRevenueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 px-4 pt-4">
                <h4 class="modal-title fw-bold" style="color: #002b5b;">Add Budget Revenue</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <form action="" method="POST">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Revenue Name</label>
                            <input type="text" name="revenue_name" class="form-control" placeholder="Enter revenue name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="category_name" class="form-control" placeholder="Enter category" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sub Category Name</label>
                            <input type="text" name="sub_category_name" class="form-control" placeholder="Enter sub-category" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Revenue Date</label>
                            <input type="date" name="revenue_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light border px-4 py-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-orange px-4 py-2 fw-bold">Add Budget Revenue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function deleteRow(rowId) {
        if(confirm("Are you sure?")) {
            const row = document.getElementById(rowId);
            row.style.transition = "0.3s";
            row.style.opacity = "0";
            setTimeout(() => { row.remove(); }, 300);
        }
    }
</script>
</body>
</html>