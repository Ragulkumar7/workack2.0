<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Request | HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #F7F9FA; 
            font-family: 'Inter', -apple-system, sans-serif;
            color: #334155;
        }

        .breadcrumb-custom {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 25px;
        }

        /* The Main Header Text */
        .page-title {
            color: #004A55; /* Deep Teal from your reference image */
            font-weight: 700;
        }

        /* Action Button Style */
        .btn-request {
            background-color: #FF6B2C; /* Orange from your reference image */
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-request:hover {
            background-color: #e85a1e;
            color: white;
            transform: translateY(-1px);
        }

        /* Content Card */
        .ui-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background-color: #f8fafc;
        }

        .form-control:focus {
            border-color: #FF6B2C;
            box-shadow: 0 0 0 3px rgba(255, 107, 44, 0.1);
        }
    </style>
</head>
<body>

<div class="container-fluid p-5">
    <div class="mb-4">
        <h2 class="page-title mb-1">Payroll Request</h2>
        <div class="breadcrumb-custom">
            <i class="fa fa-home"></i> &nbsp;›&nbsp; Payroll &nbsp;›&nbsp; Submit Request
        </div>
    </div>

    <div class="ui-card shadow-sm mx-auto" style="max-width: 800px;">
        
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Form processing logic
            echo "<div class='alert alert-success border-0 mb-4' style='background-color: #ecfdf5; color: #065f46;'>
                    <i class='fa-solid fa-check-circle'></i> Your payroll request has been sent to the accounts team successfully.
                  </div>";
        }
        ?>

        <form action="" method="POST">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="form-label">Register Number</label>
                    <input type="text" name="reg_no" class="form-control" placeholder="e.g. 2026-REG-01" required>
                </div>

                <div class="col-md-6 mb-4">
                    <label class="form-label">Email (Office)</label>
                    <input type="email" name="work_email" class="form-control" placeholder="office@company.com" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="form-label">Personal Email</label>
                    <input type="email" name="pers_email" class="form-control" placeholder="personal@email.com" required>
                </div>

                <div class="col-12 mb-4">
                    <label class="form-label">Request Date (Day/Month/Year)</label>
                    <input type="date" name="request_date" class="form-control" required>
                </div>
            </div>

            <div class="d-flex justify-content-end pt-3">
                <button type="submit" class="btn btn-request">
                    Request <i class="fa-solid fa-paper-plane ms-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>