<?php 
// purchase_order.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; } 
else { require_once $projectRoot . '/include/db_connect.php'; }

if (!isset($conn) || $conn === null) { die("Database connection failed."); }

// Neoera Infotech Default Details for Printing
$company_details = [
    'name' => 'Neoera infotech',
    'address' => '9/96 h, post, village nagar, Kurumbapalayam SSKulam, coimbatore, Tamil Nadu 641107',
    'phone' => '+91 866 802 5451',
    'email' => 'Contact@neoerainfotech.com',
    'website' => 'www.neoerainfotech.com',
    'logo' => '../assets/neoera.png' 
];

// =========================================================================
// BACKEND AJAX HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    if(ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');

    try {
        // --- FETCH PO DETAILS FOR PRINT/VIEW ---
        if ($_POST['ajax_action'] === 'get_po_details') {
            $id = intval($_POST['id']);
            $po_res = mysqli_query($conn, "SELECT * FROM purchase_orders WHERE id = $id");
            if(!$po_res) throw new Exception(mysqli_error($conn));
            $po = mysqli_fetch_assoc($po_res);
            
            $items = [];
            if($po) {
                // Fetch perfectly mapped items from po_line_items table
                $items_res = mysqli_query($conn, "SELECT * FROM po_line_items WHERE po_number = '".$po['po_number']."'");
                if($items_res) {
                    while($it = mysqli_fetch_assoc($items_res)) { 
                        $items[] = $it; 
                    }
                }
            }
            echo json_encode(['status' => 'success', 'po' => $po, 'items' => $items]);
            exit;
        }

        // --- SAVE PURCHASE ORDER ---
        if ($_POST['ajax_action'] === 'save_po') {
            $po_no = mysqli_real_escape_string($conn, $_POST['po_no']);
            
            // Safe Date Handling
            $po_date = !empty($_POST['po_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['po_date']) . "'" : "CURDATE()";
            $expected_delivery = !empty($_POST['delivery_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['delivery_date']) . "'" : "NULL";
            
            $vendor_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
            $vendor_gstin = mysqli_real_escape_string($conn, $_POST['gst_number'] ?? '');
            $po_status = mysqli_real_escape_string($conn, $_POST['status']);
            $payment_mode = mysqli_real_escape_string($conn, $_POST['payment_mode']);
            $remark = mysqli_real_escape_string($conn, $_POST['remark'] ?? '');
            
            $net_total = floatval($_POST['net_total'] ?? 0);
            $tax_amount = floatval($_POST['tax_amount'] ?? 0);
            $freight_charges = floatval($_POST['transport_charges'] ?? 0);
            $grand_total = floatval($_POST['grand_total'] ?? 0);
            $paid_amount = floatval($_POST['paid_amount'] ?? 0);
            $balance_amount = floatval($_POST['balance_amount'] ?? 0);

            $vendor_address = mysqli_real_escape_string($conn, $_POST['vendor_address'] ?? '');
            $vendor_email = mysqli_real_escape_string($conn, $_POST['vendor_email'] ?? '');
            $vendor_phone = mysqli_real_escape_string($conn, $_POST['vendor_phone'] ?? '');

            $insert_po = "INSERT INTO purchase_orders 
                (po_number, po_date, vendor_name, vendor_address, vendor_email, vendor_phone, vendor_gstin, expected_delivery, po_status, payment_mode, terms_conditions, net_total, tax_amount, freight_charges, grand_total, paid_amount, balance_amount, approval_status, created_at) 
                VALUES 
                ('$po_no', $po_date, '$vendor_name', '$vendor_address', '$vendor_email', '$vendor_phone', '$vendor_gstin', $expected_delivery, '$po_status', '$payment_mode', '$remark', $net_total, $tax_amount, $freight_charges, $grand_total, $paid_amount, $balance_amount, 'Pending', NOW())";
            
            if (mysqli_query($conn, $insert_po)) {
                $item_errors = [];
                // Save Items (Description, Qty, Rate, Total) perfectly to po_line_items
                if (isset($_POST['materials']) && is_array($_POST['materials'])) {
                    foreach ($_POST['materials'] as $key => $material) {
                        $material_escaped = mysqli_real_escape_string($conn, $material);
                        $hsn_code = mysqli_real_escape_string($conn, $_POST['hsn_code'][$key] ?? '');
                        $qty = floatval($_POST['qtys'][$key] ?? 0);
                        $unit = mysqli_real_escape_string($conn, $_POST['unit'][$key] ?? '');
                        $price = floatval($_POST['prices'][$key] ?? 0);
                        $discount = floatval($_POST['discount'][$key] ?? 0);
                        $gst = floatval($_POST['gst_percent'][$key] ?? 0);
                        $line_total = floatval($_POST['totals'][$key] ?? 0);

                        if (!empty($material_escaped)) {
                            // Exact insert mapping to po_line_items.sql
                            // Empty string '' is sent for item_code
                            $insert_item = "INSERT INTO po_line_items 
                                (po_number, item_description, item_code, hsn_code, quantity, unit, rate, discount_percent, gst_percent, line_total) 
                                VALUES 
                                ('$po_no', '$material_escaped', '', '$hsn_code', $qty, '$unit', $price, $discount, $gst, $line_total)";
                            
                            if(!mysqli_query($conn, $insert_item)){
                                $item_errors[] = mysqli_error($conn);
                            }
                        }
                    }
                }
                
                if (empty($item_errors)) {
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => "Items Insert Error: " . implode(" | ", $item_errors)]);
                }
            } else { 
                echo json_encode(['status' => 'error', 'message' => "PO Header Insert Error: " . mysqli_error($conn)]); 
            }
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// FETCH DATA FOR UI
// =========================================================================
// Fixed PO Number Generation matching exact DB structure (e.g. PO-20260228-005)
$po_query = mysqli_query($conn, "SELECT id FROM purchase_orders ORDER BY id DESC LIMIT 1");
$last_id = $po_query && mysqli_num_rows($po_query) > 0 ? mysqli_fetch_assoc($po_query)['id'] : 0;
$next_id = $last_id + 1;
$po_number = "PO-" . date('Ymd') . "-" . str_pad($next_id, 3, '0', STR_PAD_LEFT);

$history_query = mysqli_query($conn, "SELECT * FROM purchase_orders ORDER BY created_at DESC LIMIT 50");

include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Management | Workack</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --theme-color: #1b5a5a; 
            --theme-light: #f0fdfa;
            --bg-body: #f1f5f9; 
            --text-main: #0f172a; 
            --text-muted: #64748b; 
            --border-color: #e2e8f0; 
            --primary-sidebar-width: 95px; 
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); 
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; color: var(--text-main); font-size: 15px;}
        .main-content { margin-left: var(--primary-sidebar-width); padding: 40px; width: calc(100% - var(--primary-sidebar-width)); transition: margin-left 0.3s ease; min-height: 100vh; box-sizing: border-box; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h2 { color: var(--theme-color); font-weight: 800; font-size: 28px; margin: 0; letter-spacing: -0.5px;}
        .page-header p { color: var(--text-muted); font-size: 16px; margin: 8px 0 0; }

        .card { background: #fff; border-radius: 14px; border: 1px solid var(--border-color); margin-bottom: 35px; box-shadow: var(--shadow-sm); overflow: hidden; }
        .card-header { background: #f8fafc; padding: 20px 30px; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { color: var(--theme-color); margin: 0; font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px;}
        .card-body { padding: 30px; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;}
        .form-control { padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; outline: none; background: #fff; color: var(--text-main); font-family: inherit; transition: 0.2s;}
        .form-control:focus { border-color: var(--theme-color); box-shadow: 0 0 0 4px rgba(27,90,90,0.1); }
        .form-control[readonly] { background: #f8fafc; color: var(--text-muted); cursor: not-allowed; }

        .section-divider { display: flex; align-items: center; text-transform: uppercase; font-size: 14px; font-weight: 800; color: var(--theme-color); margin: 35px 0 20px; }
        .section-divider::after { content: ''; flex: 1; height: 1px; background: var(--border-color); margin-left: 15px; }

        /* Items Table */
        .table-responsive { overflow-x: auto; margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: 10px;}
        .items-table { width: 100%; border-collapse: collapse; background: #fff;}
        .items-table th { background: #f1f5f9; padding: 15px; text-align: left; font-size: 14px; text-transform: uppercase; color: var(--text-muted); font-weight: 800; border-bottom: 1px solid var(--border-color); white-space: nowrap;}
        .items-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 15px; vertical-align: middle; }
        
        .qty-unit-group { display: flex; align-items: center; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff;}
        .qty-unit-group input { border: none !important; width: 60%; text-align: center; border-radius: 0; outline: none; padding: 10px;}
        .qty-unit-group select { border: none !important; width: 40%; background: #f8fafc; border-left: 1px solid #cbd5e1 !important; cursor: pointer; border-radius: 0; outline: none; padding: 10px; font-size: 14px;}

        .btn-add-row { background: var(--theme-light); color: var(--theme-color); border: 2px dashed var(--theme-color); padding: 12px 25px; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: 800; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; margin-bottom: 25px;}
        .btn-add-row:hover { background: #ccfbf1; }

        .terms-summary-wrapper { display: flex; flex-wrap: wrap; gap: 40px; margin-top: 15px; }
        .terms-section { flex: 1; min-width: 350px; }
        
        /* Summary Box with Bigger Fonts */
        .summary-section { width: 420px; }
        .summary-box { background: #f8fafc; padding: 25px; border-radius: 14px; border: 1px solid #cbd5e1; box-shadow: var(--shadow-sm);}
        .summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-size: 16px; color: #475569; font-weight: 600;}
        .currency-input-wrap { display: flex; align-items: center; justify-content: space-between; width: 160px;}
        .summary-row input { text-align: right; background: transparent; border: none; font-weight: 800; width: 100%; outline: none; padding: 0; color: inherit; font-size: 16px;}
        .summary-row input.form-control { background: #fff; border: 1px solid #cbd5e1; padding: 10px 14px; border-radius: 8px; font-size: 16px;}
        .summary-row.total { font-weight: 800; font-size: 18px; color: var(--theme-color); border-top: 2px dashed #cbd5e1; padding-top: 18px; margin-top: 8px; }
        .summary-row.total input { font-size: 20px; color: var(--theme-color); }

        .btn-save { background: var(--theme-color); color: #fff; border: none; padding: 16px 40px; border-radius: 10px; font-weight: 800; cursor: pointer; font-size: 16px; display: inline-flex; justify-content: center; align-items: center; gap: 10px; transition: 0.2s; box-shadow: var(--shadow-md);}
        .btn-save:hover { background: #144444; transform: translateY(-2px); }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; box-shadow: none;}
        .btn-outline:hover { background: #f1f5f9; color: var(--text-main); transform: translateY(0);}

        /* History Table */
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { background: #f8fafc; padding: 18px; text-align: left; font-size: 13px; text-transform: uppercase; color: var(--text-muted); font-weight: 800; border-bottom: 1px solid var(--border-color); }
        .history-table td { padding: 18px; border-bottom: 1px solid #f1f5f9; font-size: 15px; vertical-align: middle; }
        .history-table tr:hover { background: #f8fafc; }

        .action-btns { display: flex; gap: 10px; justify-content: center; align-items: center;}
        .btn-icon { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: none; transition: 0.2s; cursor: pointer; font-size: 18px; font-weight: bold;}
        .btn-print { background: #e0f2fe; color: #0369a1; }
        .btn-print:hover { background: #bae6fd; }
        .btn-view { background: #fef3c7; color: #d97706; }
        .btn-view:hover { background: #fde68a; }

        .status-badge { padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; }
        .bg-pending { background: #fef9c3; color: #d97706; border: 1px solid #fde047;}
        .bg-approved { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;}
        .bg-rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;}

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 2000; display: none; justify-content: center; align-items: center; backdrop-filter: blur(4px);}
        .modal-content { background: white; border-radius: 14px; width: 95%; max-width: 1000px; max-height: 90vh; overflow-y: auto; padding: 35px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .close-modal { position: absolute; top: 25px; right: 25px; font-size: 28px; cursor: pointer; color: #94a3b8; transition: 0.2s;}
        .close-modal:hover { color: #ef4444; }

        /* --- STRICT PRINT TEMPLATE CSS --- */
        #printablePO { display: none; }
        @media print {
            @page { size: A4; margin: 15mm; }
            body { background: #fff !important; margin: 0; padding: 0; height: auto !important; overflow: visible !important;}
            
            body > * { display: none !important; }
            
            body > #printablePO.active-print {
                display: block !important;
                position: relative !important;
                width: 100% !important;
                font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
                color: #000 !important;
                line-height: 1.5 !important;
            }
            #printablePO * { visibility: visible; }

            .p-header { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
            .p-logo { max-height: 65px; }
            .p-title { font-size: 34px; font-weight: 900; letter-spacing: 1px; color: #000; margin-bottom: 12px;}
            
            .p-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .p-table th { background-color: #f0f0f0 !important; color: #000; border-bottom: 2px solid #000; padding: 14px 12px; font-size: 13px; text-transform: uppercase; font-weight: bold;}
            .p-table td { padding: 14px 12px; border-bottom: 1px solid #ddd; font-size: 14px; vertical-align: top;}
            
            .p-totals { width: 400px; float: right; border-collapse: collapse; margin-bottom: 40px;}
            .p-totals td { padding: 10px 12px; font-size: 15px; text-align: right;}
            .p-grand { border-top: 2px solid #000; border-bottom: 2px solid #000; font-size: 20px !important; font-weight: bold; background: #f9f9f9 !important;}
            
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<div id="mainWrapper">
    <main class="main-content">
        <div class="page-header">
            <h2>Purchase Order Generation</h2>
            <p>Draft professional purchase orders and manage vendor records.</p>
        </div>

        <form id="poForm" onsubmit="savePO(event)">
            <input type="hidden" name="ajax_action" value="save_po">
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="ph-bold ph-identification-card"></i> Header & Vendor Details</h3>
                </div>
                <div class="card-body">
                    <div class="form-grid" style="border-bottom: 1px dashed var(--border-color); padding-bottom: 20px; margin-bottom: 30px;">
                        <div class="form-group">
                            <label>Purchase Order #</label>
                            <input type="text" name="po_no" class="form-control" value="<?= $po_number ?>" readonly style="font-weight: 800; color: var(--theme-color); font-size: 16px;">
                        </div>
                        <div class="form-group">
                            <label>PO Date</label>
                            <input type="date" name="po_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Expected Delivery</label>
                            <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Vendor Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="shop_name" id="shopName" class="form-control" required placeholder="Enter Vendor Business Name">
                        </div>
                        <div class="form-group">
                            <label>Vendor GSTIN</label>
                            <input type="text" name="gst_number" class="form-control" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label>Vendor Contact (Email / Phone)</label>
                            <div style="display:flex; gap:12px;">
                                <input type="text" name="vendor_phone" class="form-control" placeholder="Phone" style="width: 40%;">
                                <input type="email" name="vendor_email" class="form-control" placeholder="Email" style="width: 60%;">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Vendor Full Address</label>
                        <textarea name="vendor_address" class="form-control" rows="2" placeholder="Street, City, State, Zip..."></textarea>
                    </div>
                    
                    <div class="form-grid" style="margin-top: 25px;">
                        <div class="form-group">
                            <label>PO Status</label>
                            <select name="status" class="form-control">
                                <option value="Draft">Draft</option>
                                <option value="Approved">Approved</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Mode</label>
                            <select name="payment_mode" class="form-control">
                                <option value="Bank Transfer">Bank Transfer (NEFT/RTGS)</option>
                                <option value="UPI">UPI</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="ph-bold ph-list-numbers"></i> Itemized Details</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th width="5%" style="text-align:center;">#</th>
                                    <th width="35%">Item Description</th>
                                    <th width="10%" style="text-align:center;">HSN</th>
                                    <th width="15%" style="text-align:center;">Qty & Unit</th>
                                    <th width="15%" style="text-align:right;">Rate (₹)</th>
                                    <th width="15%" style="text-align:right;">Amount (₹)</th>
                                    <th width="5%" style="text-align:center;">Act</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                </tbody>
                        </table>
                    </div>
                    
                    <button type="button" class="btn-add-row" onclick="addRow()">
                        <i class="ph-bold ph-plus-circle"></i> Add New Item
                    </button>

                    <div class="terms-summary-wrapper">
                        <div class="terms-section">
                            <div class="form-group">
                                <label>Notes & Terms / Instructions</label>
                                <textarea name="remark" class="form-control" rows="6" placeholder="Special instructions for vendor...">1. Please supply the items listed above by the specified delivery date.
2. Ensure items are properly packaged to avoid damage.
3. Invoice must reference this PO Number.</textarea>
                            </div>
                        </div>

                        <div class="summary-section">
                            <div class="summary-box">
                                <div class="summary-row">
                                    <span>Sub Total</span>
                                    <span class="currency-input-wrap"><span>₹</span> <input type="text" id="netTotal" name="net_total" value="0.00" readonly></span>
                                </div>
                                <div class="summary-row">
                                    <span>Total Tax Amount</span>
                                    <span class="currency-input-wrap"><span>₹</span> <input type="text" id="taxAmount" name="tax_amount" value="0.00" readonly></span>
                                </div>
                                <div class="summary-row">
                                    <span>Freight Charges (+)</span>
                                    <span class="currency-input-wrap"><span>₹</span> <input type="number" name="transport_charges" id="transport" class="form-control" style="width: 120px; text-align:right;" value="0" oninput="calculateTotals()"></span>
                                </div>
                                <div class="summary-row total">
                                    <span>Grand Total</span>
                                    <span class="currency-input-wrap"><span>₹</span> <input type="text" id="grandTotal" name="grand_total" value="0.00" readonly></span>
                                </div>
                                <div class="summary-row" style="margin-top: 20px;">
                                    <span style="font-weight: 800;">Amount Paid Adv.</span>
                                    <span class="currency-input-wrap"><span>₹</span> <input type="number" name="paid_amount" id="paidAmount" class="form-control" style="width: 120px; text-align:right; border-color: var(--theme-color);" value="0" oninput="calculateTotals()"></span>
                                </div>
                                <div class="summary-row">
                                    <span style="color: #ef4444; font-weight: 800;">Balance Due</span>
                                    <span class="currency-input-wrap" style="color: #ef4444;"><span>₹</span> <input type="text" id="balanceAmount" name="balance_amount" value="0.00" readonly style="color: inherit;"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 35px; border-top: 1px solid var(--border-color); padding-top: 25px;">
                        <button type="button" class="btn-save btn-outline" onclick="location.reload()">Reset Form</button>
                        <button type="submit" id="saveBtn" class="btn-save">
                            Generate Purchase Order <i class="ph-bold ph-paper-plane-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border-color); border-left: 5px solid var(--theme-color);">
                <h3 style="color: var(--theme-color);"><i class="ph-bold ph-clock-counter-clockwise"></i> Purchase Order History</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive" style="margin: 0; border: none; border-radius: 0;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>PO No</th>
                                <th>Vendor Details</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($history_query && mysqli_num_rows($history_query) > 0): while($row = mysqli_fetch_assoc($history_query)): 
                                $status = !empty($row['approval_status']) ? $row['approval_status'] : 'Pending';
                                
                                $badgeClass = 'bg-pending';
                                $icon = '<i class="ph-bold ph-clock"></i>';
                                
                                if ($status == 'Approved') { 
                                    $badgeClass = 'bg-approved'; 
                                    $icon = '<i class="ph-bold ph-check"></i>'; 
                                } elseif ($status == 'Rejected') { 
                                    $badgeClass = 'bg-rejected'; 
                                    $icon = '<i class="ph-bold ph-x"></i>'; 
                                }
                            ?>
                            <tr id="row_<?= $row['id'] ?>">
                                <td style="color: var(--theme-color); font-weight: 800; font-size: 16px;"><?= $row['po_number'] ?></td>
                                <td>
                                    <strong style="font-size: 15px;"><?= htmlspecialchars($row['vendor_name']) ?></strong><br>
                                    <span style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars($row['vendor_email'] ?? '') ?></span>
                                </td>
                                <td><?= date('d M Y', strtotime($row['po_date'])) ?></td>
                                <td><strong style="color: var(--text-main); font-size: 16px;">₹<?= number_format($row['grand_total'], 2) ?></strong></td>
                                <td style="color: <?= ($row['balance_amount'] > 0) ? '#ef4444' : '#15803d' ?>; font-weight: 800; font-size: 15px;">₹<?= number_format($row['balance_amount'], 2) ?></td>
                                
                                <td>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <?= $icon ?> <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                
                                <td class="action-btns">
                                    <button type="button" class="btn-icon btn-view" onclick="viewPODetails(<?= $row['id'] ?>, '<?= $row['po_number'] ?>')" title="View Items"><i class="ph-bold ph-eye"></i></button>
                                    
                                    <?php if($status == 'Approved'): ?>
                                        <button type="button" class="btn-icon btn-print" onclick="prepareAndPrintPO(<?= $row['id'] ?>)" title="Print PO Document"><i class="ph-bold ph-printer"></i></button>
                                    <?php else: ?>
                                        <div style="width: 38px; height: 38px; visibility: hidden;"></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px; font-size: 16px;">No Purchase Orders generated yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div> <div class="print-container" id="printablePO">
    <div class="p-header">
        <div style="text-align: left;">
            <img src="<?= $company_details['logo'] ?>" alt="Logo" class="p-logo">
            <div style="font-size: 18px; font-weight: 800; color: #000; text-transform: uppercase; margin-top: 8px;"><?= $company_details['name'] ?></div>
            <div style="font-size: 14px; margin-top: 5px; color: #333; max-width: 300px;">
                <?= $company_details['address'] ?><br>
                Phone: <?= $company_details['phone'] ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div class="p-title">PURCHASE ORDER</div>
            <table style="margin-left: auto; font-size: 15px; text-align: left; color: #000;">
                <tr><td style="padding: 4px 10px; font-weight: bold; text-align: right;">DATE:</td><td style="padding: 4px 10px;" id="p_po_date_lbl"></td></tr>
                <tr><td style="padding: 4px 10px; font-weight: bold; text-align: right;">PO #:</td><td style="padding: 4px 10px; font-weight: bold; color: #1b5a5a;" id="p_po_no_lbl"></td></tr>
            </table>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        <h4 style="font-size: 14px; font-weight: bold; border-bottom: 2px solid #ccc; padding-bottom: 5px; text-transform: uppercase; color: #555; display: inline-block; margin: 0 0 10px 0;">To (Vendor Details):</h4>
        <div style="font-weight: bold; font-size: 16px; text-transform: uppercase; margin-bottom: 6px;" id="p_po_vendor"></div>
        <div style="font-size: 14px; margin-bottom: 6px; white-space: pre-line;" id="p_po_vendor_address"></div>
        <div style="font-size: 14px; margin-bottom: 6px;" id="p_po_vendor_gst"></div>
        <div style="font-size: 14px;" id="p_po_vendor_contact"></div>
    </div>

    <table class="p-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">S.NO</th>
                <th style="width: 50%;">ITEM DESCRIPTION</th>
                <th style="width: 10%; text-align: center;">QTY</th>
                <th style="width: 15%; text-align: right;">UNIT PRICE</th>
                <th style="width: 20%; text-align: right;">AMOUNT</th>
            </tr>
        </thead>
        <tbody id="p_po_items"></tbody>
    </table>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 40px;">
        <table class="p-totals">
            <tr>
                <td>Sub Total</td>
                <td style="width: 150px;">
                    <div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_sub">0.00</span></div>
                </td>
            </tr>
            <tr id="tr_po_tax">
                <td>Tax Amount</td>
                <td>
                    <div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_tax">0.00</span></div>
                </td>
            </tr>
            <tr id="tr_po_freight">
                <td>Freight Charges</td>
                <td>
                    <div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_freight">0.00</span></div>
                </td>
            </tr>
            <tr class="p-grand">
                <td>GRAND TOTAL</td>
                <td>
                    <div style="display:flex; justify-content:space-between;"><span>₹</span> <span id="p_po_grand">0.00</span></div>
                </td>
            </tr>
        </table>
    </div>

    <div style="font-size: 14px; margin-bottom: 30px;">
        <p style="font-weight: bold; margin: 0 0 5px 0; text-transform: uppercase; color: #555;">Terms & Instructions:</p>
        <p style="white-space: pre-line; line-height: 1.6;" id="p_po_notes"></p>
    </div>

    <div style="text-align: center; font-size: 14px; border-top: 1px solid #000; padding-top: 15px; font-weight: bold; position: fixed; bottom: 15mm; width: 100%;">
        <?= $company_details['name'] ?> | <?= $company_details['phone'] ?> | <?= $company_details['website'] ?>
    </div>
</div>

<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()"><i class="ph-bold ph-x"></i></span>
        <h3 id="modalPOTitle" style="color: var(--theme-color); margin: 0 0 25px 0; font-weight: 800; font-size: 22px;"></h3>
        <div class="table-responsive" style="margin: 0; border: none; box-shadow: var(--shadow-sm);">
            <table class="items-table" style="margin:0;">
                <thead>
                    <tr>
                        <th style="padding: 16px;">Item Description</th>
                        <th style="text-align: center; padding: 16px;">HSN</th>
                        <th style="text-align: center; padding: 16px;">Qty</th>
                        <th style="text-align: right; padding: 16px;">Rate</th>
                        <th style="text-align: center; padding: 16px;">GST%</th>
                        <th style="text-align: right; padding: 16px;">Total</th>
                    </tr>
                </thead>
                <tbody id="modalBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let itemIndex = 1;

    $(document).ready(function() {
        addRow(); // Load one empty row on start
    });

    // --- CALCULATIONS & TABLE LOGIC ---
    function addRow() {
        const tbody = document.getElementById('itemsBody');
        const tr = document.createElement('tr');
        tr.className = "item-row";
        tr.innerHTML = `
            <td style="text-align: center; font-weight: 800; color: #777; padding-top: 20px;"></td>
            <td>
                <input type="text" name="materials[${itemIndex}]" class="form-control" placeholder="Item description..." required style="width: 100%;">
            </td>
            <td><input type="text" name="hsn_code[${itemIndex}]" class="form-control" placeholder="HSN" style="width:100%; text-align:center;"></td>
            <td>
                <div class="qty-unit-group">
                    <input type="number" name="qtys[${itemIndex}]" class="qty" value="1" step="0.01" oninput="calculateTotals()">
                    <select name="unit[${itemIndex}]">
                        <option>Nos</option><option>Kg</option><option>Pcs</option><option>Box</option>
                    </select>
                </div>
            </td>
            <td><input type="number" name="prices[${itemIndex}]" class="form-control price" value="0.00" step="0.01" style="width:100%; text-align: right;" oninput="calculateTotals()"></td>
            
            <input type="hidden" name="discount[${itemIndex}]" class="discount" value="0">
            <input type="hidden" name="gst_percent[${itemIndex}]" class="gst" value="0">
            
            <td>
                <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:12px 16px; border:1px solid #cbd5e1; border-radius:8px;">
                    <span style="color:var(--theme-color); font-weight:800; font-size:16px;">₹</span>
                    <input type="text" name="totals[${itemIndex}]" class="row-total" readonly value="0.00" style="width:100%; text-align:right; font-weight:800; font-size:16px; color:var(--theme-color); background:transparent; border:none; outline:none;">
                </div>
            </td>
            <td style="text-align: center; vertical-align: middle;">
                <button type="button" class="btn-icon" style="background: transparent; color:#dc2626; box-shadow:none; padding:0; font-size:22px;" onclick="removeRow(this)"><i class="ph-bold ph-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
        itemIndex++;
        updateRowNumbers();
    }

    function removeRow(btn) { 
        if($('#items-container tr').length > 1 || document.querySelectorAll('.item-row').length > 1) { 
            $(btn).closest('tr').remove(); 
            calculateTotals(); 
            updateRowNumbers();
        } else {
            Swal.fire('Notice', 'At least one item is required.', 'info');
        }
    }

    function calculateTotals() {
        let subTotal = 0;
        const taxP = parseFloat(document.getElementById('po_tax_p')?.value) || 0; 
        
        document.querySelectorAll('.item-row').forEach(r => {
            const qty = parseFloat(r.querySelector('.qty').value) || 0;
            const rate = parseFloat(r.querySelector('.price').value) || 0;
            
            const lineAmount = (qty * rate);
            r.querySelector('.row-total').value = lineAmount.toFixed(2);
            subTotal += lineAmount;
        });
        
        const taxAmount = subTotal * (taxP / 100);
        const freight = parseFloat(document.getElementById('transport').value) || 0;
        const grandTotal = subTotal + taxAmount + freight;
        const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
        const balance = grandTotal - paid;

        document.getElementById('netTotal').value = subTotal.toFixed(2);
        if(document.getElementById('displaySubtotal')) document.getElementById('displaySubtotal').innerText = subTotal.toFixed(2);
        
        document.getElementById('taxAmount').value = taxAmount.toFixed(2);
        if(document.getElementById('displayTax')) document.getElementById('displayTax').innerText = taxAmount.toFixed(2);
        
        document.getElementById('grandTotal').value = grandTotal.toFixed(2);
        if(document.getElementById('displayGrandTotal')) document.getElementById('displayGrandTotal').innerText = grandTotal.toFixed(2);
        
        document.getElementById('balanceAmount').value = balance.toFixed(2);

        if(document.getElementById('hiddenSubTotal')) document.getElementById('hiddenSubTotal').value = subTotal.toFixed(2);
        if(document.getElementById('hiddenTaxTotal')) document.getElementById('hiddenTaxTotal').value = taxAmount.toFixed(2);
        if(document.getElementById('hiddenGrandTotal')) document.getElementById('hiddenGrandTotal').value = grandTotal.toFixed(2);
    }

    function updateRowNumbers() {
        document.querySelectorAll('.item-row').forEach((row, index) => {
            row.cells[0].innerText = index + 1;
        });
    }

    // --- FORM SUBMISSION ---
    function savePO(e) {
        e.preventDefault(); 
        
        if(!$('#shopName').val()) { 
            Swal.fire("Required", "Please enter Vendor Name", "warning"); 
            return; 
        }

        const btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="ph-bold ph-spinner ph-spin"></i> Generating...');
        
        $.ajax({
            url: window.location.href, 
            type: 'POST', 
            data: $('#poForm').serialize(),
            dataType: 'json',
            success: function(res) { 
                if (res.status === 'success') { 
                    Swal.fire({title: "Success!", text: "Purchase Order Saved Successfully and sent to CFO for approval!", icon: "success", timer: 2000, showConfirmButton: false})
                    .then(() => window.location.reload()); 
                } 
                else { 
                    Swal.fire("Database Error", res.message, "error"); 
                    btn.prop('disabled', false).html('Generate Purchase Order <i class="ph-bold ph-paper-plane-right"></i>'); 
                }
            },
            error: function() { 
                Swal.fire("Network Error", "Error connecting to server.", "error"); 
                btn.prop('disabled', false).html('Generate Purchase Order <i class="ph-bold ph-paper-plane-right"></i>'); 
            }
        });
    }

    // --- MODAL VIEW ---
    function viewPODetails(id, poNum) {
        $('#modalPOTitle').text('Purchase Order Details');
        $.post(window.location.href, {ajax_action: 'get_po_details', id: id}, function(res) {
            try {
                let data = typeof res === 'string' ? JSON.parse(res) : res;
                if(data.status === 'success') {
                    $('#modalPOTitle').text('PO Reference: ' + data.po.po_number);
                    let html = '';
                    data.items.forEach(item => {
                        let desc = item.item_description || item.description || '';
                        let qty = item.quantity || item.qty || 0;
                        let rate = item.rate || item.unit_price || 0;
                        let line_total = item.line_total || item.amount || item.total_price || 0;

                        html += `<tr>
                            <td style="padding: 16px; font-size: 15px;">${desc}</td>
                            <td style="text-align:center; padding: 16px; font-size: 15px;">${item.hsn_code || '-'}</td>
                            <td style="text-align:center; padding: 16px; font-size: 15px;">${qty} ${item.unit || ''}</td>
                            <td style="padding: 16px; font-size: 15px;">
                                <div style="display:flex; justify-content:space-between; width:80px; margin-left:auto;"><span>₹</span><span>${parseFloat(rate).toFixed(2)}</span></div>
                            </td>
                            <td style="text-align:center; padding: 16px; font-size: 15px;">${parseFloat(item.gst_percent || 0).toFixed(0)}%</td>
                            <td style="padding: 16px; font-weight:800; font-size: 15px; color:var(--theme-color);">
                                <div style="display:flex; justify-content:space-between; width:90px; margin-left:auto;"><span>₹</span><span>${parseFloat(line_total).toFixed(2)}</span></div>
                            </td>
                        </tr>`;
                    });
                    $('#modalBody').html(html);
                    $('#viewModal').css('display', 'flex');
                }
            } catch(e) {
                Swal.fire('Error', 'Failed to parse details.', 'error');
            }
        });
    }

    function closeModal() { $('#viewModal').hide(); }

    // --- PERFECT ALIGNMENT PRINTING LOGIC ---
    function prepareAndPrintPO(id) {
        $.post(window.location.href, {ajax_action: 'get_po_details', id: id}, function(res) {
            try {
                let data = typeof res === 'string' ? JSON.parse(res) : res;
                if(data.status === 'success') {
                    const po = data.po;
                    document.getElementById('p_po_no_lbl').innerText = po.po_number;
                    
                    const dateObj = new Date(po.po_date);
                    document.getElementById('p_po_date_lbl').innerText = dateObj.toLocaleDateString('en-GB');
                    
                    document.getElementById('p_po_vendor').innerText = po.vendor_name || 'N/A';
                    document.getElementById('p_po_vendor_address').innerText = po.vendor_address || '';
                    document.getElementById('p_po_vendor_gst').innerText = po.vendor_gstin ? 'GSTIN: ' + po.vendor_gstin : '';
                    
                    let contactInfo = [];
                    if(po.vendor_email) contactInfo.push(po.vendor_email);
                    if(po.vendor_phone) contactInfo.push(po.vendor_phone);
                    document.getElementById('p_po_vendor_contact').innerText = contactInfo.join(' | ');
                    
                    document.getElementById('p_po_sub').innerText = parseFloat(po.net_total || po.sub_total || 0).toFixed(2);
                    
                    if(parseFloat(po.tax_amount) > 0) {
                        document.getElementById('p_po_tax').innerText = parseFloat(po.tax_amount).toFixed(2);
                        document.getElementById('tr_po_tax').style.display = 'table-row';
                    } else {
                        document.getElementById('tr_po_tax').style.display = 'none';
                    }

                    if(parseFloat(po.freight_charges) > 0) {
                        document.getElementById('p_po_freight').innerText = parseFloat(po.freight_charges).toFixed(2);
                        document.getElementById('tr_po_freight').style.display = 'table-row';
                    } else {
                        document.getElementById('tr_po_freight').style.display = 'none';
                    }
                    
                    document.getElementById('p_po_grand').innerText = parseFloat(po.grand_total || 0).toFixed(2);
                    document.getElementById('p_po_notes').innerText = po.terms_conditions || po.notes || '';

                    const tbody = document.getElementById('p_po_items');
                    tbody.innerHTML = '';
                    let sno = 1;
                    
                    if(data.items && data.items.length > 0) {
                        data.items.forEach(it => {
                            let desc = it.item_description || it.description || '';
                            let qty = it.quantity || it.qty || 1;
                            let rate = it.rate || it.unit_price || 0;
                            let line_total = it.line_total || it.amount || it.total_price || 0;

                            tbody.innerHTML += `<tr>
                                <td style="border-bottom:1px solid #ddd; padding:14px 10px; text-align:center;">${sno++}</td>
                                <td style="border-bottom:1px solid #ddd; padding:14px 10px;">${desc}</td>
                                <td style="border-bottom:1px solid #ddd; padding:14px 10px; text-align:center;">${qty} ${it.unit || ''}</td>
                                <td style="border-bottom:1px solid #ddd; padding:14px 10px;">
                                    <div style="display:flex; justify-content:space-between;"><span>₹</span><span>${parseFloat(rate).toFixed(2)}</span></div>
                                </td>
                                <td style="border-bottom:1px solid #ddd; padding:14px 10px; font-weight:bold;">
                                    <div style="display:flex; justify-content:space-between;"><span>₹</span><span>${parseFloat(line_total).toFixed(2)}</span></div>
                                </td>
                            </tr>`;
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 30px;">No specific items broken down.</td></tr>`;
                    }

                    // --- TRICK TO PREVENT EXTRA BLANK PAGES ---
                    document.getElementById('mainWrapper').style.display = 'none';
                    document.getElementById('printablePO').classList.add('active-print');
                    
                    setTimeout(() => { 
                        window.print(); 
                        document.getElementById('printablePO').classList.remove('active-print'); 
                        document.getElementById('mainWrapper').style.display = 'block';
                    }, 500);

                } else {
                    Swal.fire('Error', 'Could not fetch PO details', 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Failed to parse print data.', 'error');
            }
        });
    }

</script>
</body>
</html>