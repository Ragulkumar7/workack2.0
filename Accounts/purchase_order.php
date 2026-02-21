<?php 
include '../include/db_connect.php'; 

// --- BACKEND LOGIC: Handle PO Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'delete_po') {
    $id = intval($_POST['id']);
    if (mysqli_query($conn, "DELETE FROM purchase_orders WHERE id = $id")) {
        echo "success";
    } else {
        echo "error";
    }
    exit;
}

// --- BACKEND LOGIC: Fetch PO Items for View Modal ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'get_po_details') {
    $id = intval($_POST['id']);
    $query = mysqli_query($conn, "SELECT pi.*, p.po_number FROM po_line_items pi JOIN purchase_orders p ON pi.po_number = p.po_number WHERE p.id = $id");
    $items = [];
    while($row = mysqli_fetch_assoc($query)) { $items[] = $row; }
    echo json_encode($items);
    exit;
}

// --- BACKEND LOGIC: Save the PO Data to the Database ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_no'])) {
    $po_no = mysqli_real_escape_string($conn, $_POST['po_no']);
    $po_date = mysqli_real_escape_string($conn, $_POST['po_date']);
    $vendor_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $vendor_gstin = mysqli_real_escape_string($conn, $_POST['gst_number'] ?? '');
    $expected_delivery = mysqli_real_escape_string($conn, $_POST['delivery_date'] ?? null);
    $po_status = mysqli_real_escape_string($conn, $_POST['status']);
    $payment_mode = mysqli_real_escape_string($conn, $_POST['payment_mode']);
    $remark = mysqli_real_escape_string($conn, $_POST['remark'] ?? '');
    
    $net_total = floatval($_POST['net_total'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $freight_charges = floatval($_POST['transport_charges'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $paid_amount = floatval($_POST['paid_amount'] ?? 0);
    $balance_amount = floatval($_POST['balance_amount'] ?? 0);

    $insert_po = "INSERT INTO purchase_orders 
        (po_number, po_date, vendor_name, vendor_gstin, expected_delivery, po_status, payment_mode, terms_conditions, net_total, tax_amount, freight_charges, grand_total, paid_amount, balance_amount, approval_status) 
        VALUES 
        ('$po_no', '$po_date', '$vendor_name', '$vendor_gstin', '$expected_delivery', '$po_status', '$payment_mode', '$remark', '$net_total', '$tax_amount', '$freight_charges', '$grand_total', '$paid_amount', '$balance_amount', 'Pending')";

    if (mysqli_query($conn, $insert_po)) {
        if (isset($_POST['materials']) && is_array($_POST['materials'])) {
            $count = count($_POST['materials']);
            for ($i = 0; $i < $count; $i++) {
                $material = mysqli_real_escape_string($conn, $_POST['materials'][$i]);
                $item_code = mysqli_real_escape_string($conn, $_POST['item_code'][$i] ?? '');
                $hsn_code = mysqli_real_escape_string($conn, $_POST['hsn_code'][$i] ?? '');
                $qty = floatval($_POST['qtys'][$i] ?? 0);
                $unit = mysqli_real_escape_string($conn, $_POST['unit'][$i] ?? '');
                $price = floatval($_POST['prices'][$i] ?? 0);
                $discount = floatval($_POST['discount'][$i] ?? 0);
                $gst = floatval($_POST['gst_percent'][$i] ?? 0);
                $line_total = floatval($_POST['totals'][$i] ?? 0);

                if (!empty($material)) {
                    $insert_item = "INSERT INTO po_line_items 
                        (po_number, item_description, item_code, hsn_code, quantity, unit, rate, discount_percent, gst_percent, line_total) 
                        VALUES 
                        ('$po_no', '$material', '$item_code', '$hsn_code', '$qty', '$unit', '$price', '$discount', '$gst', '$line_total')";
                    mysqli_query($conn, $insert_item);
                }
            }
        }
        echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
    exit;
}

include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Entry</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root {
            --sidebar-bg: #0f172a;
            --accent: #d4af37;
            --accent-hover: #c19b2e;
            --accent-glow: rgba(212, 175, 55, 0.3);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --hover-bg: rgba(255, 255, 255, 0.08);
            --border-color: rgba(255, 255, 255, 0.1);
            --sidebar-width: 280px;
            --primary-color: #1b5a5a;
            --accent-gold: #D4AF37;
            --bg-light: #f8fafc;
            --border: #e4e4e7;
        }

        body { background-color: var(--bg-light); font-family: "Plus Jakarta Sans", sans-serif; color: #1e293b; margin: 0; padding: 0; }

        .main-content { margin-left: 95px; padding: 30px; transition: all 0.3s ease; min-height: 100vh; }
        .header-section { margin-bottom: 25px; }
        .header-section h2 { color: var(--primary-color); font-weight: 700; margin: 0; }
        .header-section p { color: #71717a; font-size: 13px; margin: 5px 0 0; }

        .card { background: #fff; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); overflow: hidden; }
        .card-header { background: var(--primary-color); padding: 15px 25px; border-bottom: 3px solid var(--accent-gold); }
        .card-header h3 { color: #fff; margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;}
        .card-body { padding: 25px; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #52525b; }
        input, select, textarea { padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; background: #fff; color: #3f3f46; font-family: inherit; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary-color); }
        input[readonly] { background: #f4f4f5; }

        .section-title { font-size: 14px; font-weight: 700; color: var(--primary-color); margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 1px dashed var(--border); }
        .stacked-input-container { display: flex; flex-direction: column; }
        .stacked-input-container input:first-child { border-bottom-left-radius: 0; border-bottom-right-radius: 0; border-bottom: none; }
        .stacked-input-container input:last-child { border-top-left-radius: 0; border-top-right-radius: 0; background-color: #f8fafc; font-size: 11px; height: 32px; }
        
        .qty-unit-group { display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff;}
        .qty-unit-group input { border: none !important; width: 60%; text-align: center; border-radius: 0; }
        .qty-unit-group select { border: none !important; width: 40%; background: #f4f4f5; border-left: 1px solid var(--border) !important; cursor: pointer; border-radius: 0;}

        .table-responsive { overflow-x: auto; margin-bottom: 10px; }
        .items-table, .history-table { width: 100%; border-collapse: collapse; }
        .items-table th, .history-table th { background: #f4f4f5; padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #71717a; font-weight: 700;}
        .items-table td, .history-table td { padding: 10px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: top; }

        .btn-add-row { background: transparent; color: #10b981; border: 1px dashed #10b981; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s;}
        .btn-add-row:hover { background: #f0fdf4; }

        .terms-summary-wrapper { display: flex; flex-wrap: wrap; gap: 30px; margin-top: 10px; }
        .terms-section { flex: 1; min-width: 300px; }
        .summary-section { width: 340px; }
        .summary-box { background: #eefcfd; padding: 20px; border-radius: 10px; border: 1px solid #c7ecee; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 13px; color: #3f3f46; }
        .summary-row input { text-align: right; background: transparent; border: none; font-weight: 600; width: 120px; outline: none; padding: 0; color: inherit;}
        .summary-row input.form-control { background: #fff; border: 1px solid #cbd5e1; padding: 6px 10px; border-radius: 6px;}
        .summary-row.total { font-weight: 700; font-size: 15px; color: var(--primary-color); border-top: 1px solid #cbd5e1; padding-top: 12px; margin-top: 12px; }
        .summary-row.total input { font-size: 16px; font-weight: 800; color: var(--primary-color); }

        .btn-save { background: var(--primary-color); color: #fff; border: none; padding: 10px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; display: inline-flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s;}
        .btn-save:hover { opacity: 0.9; }
        .btn-outline { background: #fff; color: #3f3f46; border: 1px solid var(--border); }
        .btn-outline:hover { background: #f4f4f5; }

        .action-btns { display: flex; gap: 10px; justify-content: center; align-items: center;}
        .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; transition: 0.2s; cursor: pointer; font-size: 16px;}
        .btn-print { background: #e0e7ff; color: #4338ca; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .btn-view { background: #fef3c7; color: #d97706; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; text-align: center; }
        .bg-pending { background: #fef3c7; color: #d97706; }
        .bg-approved { background: #dcfce7; color: #15803d; }
        .bg-rejected { background: #fee2e2; color: #b91c1c; }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto; padding: 25px; position: relative; }
        .close-modal { position: absolute; top: 15px; right: 15px; font-size: 24px; cursor: pointer; color: #64748b; }

        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } .form-grid { grid-template-columns: 1fr; } .summary-section { width: 100%; } }
        @media print { .main-content { margin: 0; padding: 0; } .sidebar, .btn-add-row, .card-footer, .history-section, .btn-save, .btn-outline { display: none; } }
    </style>
</head>
<body>

<main class="main-content">
    <div class="header-section">
        <h2>Purchase Order Entry</h2>
        <p data-key="po-subtitle">Create purchase orders and record vendor bills securely.</p>
    </div>

    <form id="poForm">
        <div class="card p-4">
            <div class="card-header">
                <h3><i class="ph-bold ph-identification-card"></i> <span data-key="po-sec-1">Header and Vendor Details</span></h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><span data-key="label-po-no">Purchase Order Number</span></label>
                        <?php $new_po_no = "PO-" . date('Ymd') . "-" . rand(100, 999); ?>
                        <input type="text" name="po_no" value="<?= $new_po_no ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><span data-key="label-po-date">PO Date</span></label>
                        <input type="date" name="po_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label><span data-key="label-vendor-name">Vendor Name *</span></label>
                        <input type="text" name="shop_name" id="shopName" required data-key="ph-vendor-name" placeholder="Enter Vendor Business Name">
                    </div>
                    <div class="form-group">
                        <label><span data-key="label-vendor-gst">Vendor GSTIN</span></label>
                        <input type="text" name="gst_number" data-key="ph-optional" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label><span data-key="label-exp-delivery">Expected Delivery</span></label>
                        <input type="date" name="delivery_date">
                    </div>
                    <div class="form-group">
                        <label><span data-key="label-po-status">PO Status</span></label>
                        <select name="status">
                            <option data-key="opt-draft">Draft</option>
                            <option data-key="opt-approved">Approved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span data-key="label-pay-mode">Payment Mode</span></label>
                        <select name="payment_mode">
                            <option value="Cash" data-key="opt-cash">Cash</option>
                            <option value="Bank Account" data-key="opt-bank">Bank Transfer</option>
                            <option value="UPI" data-key="opt-upi">UPI</option>
                        </select>
                    </div>
                </div>

                <div class="section-title" data-key="po-sec-2">Line Items</div>
                <div class="table-responsive">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th width="3%" data-key="th-sno">#</th>
                                <th width="28%" data-key="th-desc-code">Description & Code</th>
                                <th width="10%" data-key="th-hsn">HSN</th>
                                <th width="14%" data-key="th-qty-unit">Qty & Unit</th>
                                <th width="10%" data-key="th-rate">Rate</th>
                                <th width="8%" data-key="th-disc">Disc%</th>
                                <th width="10%" data-key="th-gst">GST%</th>
                                <th width="12%" data-key="th-total">Total</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="items-container"></tbody>
                    </table>
                </div>
                <button type="button" class="btn-add-row" onclick="addNewRow()">
                    <i class="ph-bold ph-plus-circle"></i> <span data-key="btn-add-item">Add New Item</span>
                </button>

                <div class="terms-summary-wrapper">
                    <div class="terms-section">
                        <div class="section-title" data-key="po-sec-3">Terms & Shipping</div>
                        <div class="form-grid" style="margin-bottom: 0;">
                            <div class="form-group">
                                <label><span data-key="label-pay-terms">Payment Terms</span></label>
                                <input type="text" data-key="ph-optional" placeholder="Optional">
                            </div>
                            <div class="form-group">
                                <label><span data-key="label-del-loc">Delivery Location</span></label>
                                <input type="text" data-key="ph-optional" placeholder="Optional">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 15px;">
                            <label><span data-key="label-terms-cond">Terms & Conditions</span></label>
                            <textarea name="remark" rows="3" data-key="ph-remarks" placeholder="Notes..."></textarea>
                        </div>
                    </div>

                    <div class="summary-section">
                        <div class="section-title" data-key="po-sec-4">PO Summary</div>
                        <div class="summary-box">
                            <div class="summary-row">
                                <span data-key="label-net-total">Net Total</span>
                                <input type="text" id="netTotal" name="net_total" value="0.00" readonly>
                            </div>
                            <div class="summary-row">
                                <span data-key="label-tax-amt">Tax Amount</span>
                                <input type="text" id="taxAmount" name="tax_amount" value="0.00" readonly>
                            </div>
                            <div class="summary-row">
                                <span data-key="label-freight">Freight Charges (+)</span>
                                <input type="number" name="transport_charges" id="transport" class="form-control" style="width: 100px; text-align:right;" value="0">
                            </div>
                            <div class="summary-row total">
                                <span data-key="label-grand-total">Grand Total</span>
                                <input type="text" id="grandTotal" name="grand_total" value="0.00" readonly>
                            </div>
                            <div class="summary-row" style="margin-top: 12px;">
                                <span data-key="label-paid-amt" style="font-weight: 600;">Paid Amount</span>
                                <input type="number" name="paid_amount" id="paidAmount" class="form-control" style="width: 100px; text-align:right;" value="0">
                            </div>
                            <div class="summary-row">
                                <span data-key="label-bal-payable" style="color: #ef4444; font-weight: 700;">Balance Payable</span>
                                <input type="text" id="balanceAmount" name="balance_amount" value="0.00" readonly style="color: #ef4444;">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px;">
                    <button type="button" class="btn-save btn-outline" onclick="location.reload()" data-key="btn-reset">Reset</button>
                    <button type="button" class="btn-save" id="submitBtn" onclick="savePO()">
                        <span data-key="btn-generate">SUBMIT PO</span> <i class="ph-bold ph-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card history-section">
        <div class="card-header" style="background: #fff; border-bottom: 1px solid var(--border); border-left: 4px solid var(--primary-color);">
            <h3 style="color: var(--primary-color);"><i class="ph-bold ph-clock-counter-clockwise"></i> <span data-key="history-title">Purchase Order History</span></h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive" style="margin: 0;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th data-key="label-po-no">PO No</th>
                            <th data-key="label-po-date">Date</th>
                            <th data-key="th-vendor-shop">Vendor Shop</th>
                            <th data-key="label-grand-total">Grand Total</th>
                            <th data-key="label-paid-amt">Paid</th>
                            <th data-key="label-bal-payable">Balance</th>
                            <th data-key="label-status">Status</th>
                            <th class="text-center" style="text-align: center;" data-key="th-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $history_query = mysqli_query($conn, "SELECT * FROM purchase_orders ORDER BY id DESC LIMIT 50");
                        if ($history_query && mysqli_num_rows($history_query) > 0) {
                            while ($row = mysqli_fetch_assoc($history_query)) {
                                $db_id = $row['id'];
                                $bal_color = ($row['balance_amount'] > 0) ? '#ef4444' : '#10b981';
                                $status_class = ($row['approval_status'] === 'Approved') ? 'bg-approved' : (($row['approval_status'] === 'Rejected') ? 'bg-rejected' : 'bg-pending');
                        ?>
                                <tr id='row_<?= $db_id ?>'>
                                    <td style="color: var(--primary-color); font-weight: 700;"><?= htmlspecialchars($row['po_number']) ?></td>
                                    <td><?= date('d-m-Y', strtotime($row['po_date'])) ?></td>
                                    <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                                    <td style="font-weight: 600;">₹<?= number_format($row['grand_total'], 2) ?></td>
                                    <td>₹<?= number_format($row['paid_amount'], 2) ?></td>
                                    <td style="color: <?= $bal_color ?>; font-weight: 700;">₹<?= number_format($row['balance_amount'], 2) ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($row['approval_status'] ?? 'Pending') ?></span></td>
                                    <td class="action-btns">
                                        <button type="button" class='btn-icon btn-view' onclick='viewPODetails(<?= $db_id ?>, "<?= htmlspecialchars($row['po_number']) ?>")' title="View"><i class='ph-bold ph-eye'></i></button>
                                        <button type="button" class='btn-icon btn-print' onclick='printPO(<?= $db_id ?>)' title="Print"><i class='ph-bold ph-printer'></i></button>
                                        <button type="button" class='btn-icon btn-delete' onclick='deletePO(<?= $db_id ?>)' title="Delete"><i class='ph-bold ph-trash'></i></button>
                                    </td>
                                </tr>
                        <?php 
                            } 
                        } else { ?>
                            <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 20px;">No purchase orders found.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3 id="modalPOTitle" style="color: var(--primary-color); margin-bottom: 20px;"></h3>
        <div class="table-responsive">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>HSN</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Disc%</th>
                        <th>GST%</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="modalBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function viewPODetails(id, poNum) {
        $('#modalPOTitle').text('Purchase Order: ' + poNum);
        $.post(window.location.href, {ajax_action: 'get_po_details', id: id}, function(data) {
            const items = JSON.parse(data);
            let html = '';
            items.forEach(item => {
                html += `<tr>
                    <td>${item.item_description}</td>
                    <td>${item.hsn_code}</td>
                    <td>${item.quantity} ${item.unit}</td>
                    <td>₹${parseFloat(item.rate).toFixed(2)}</td>
                    <td>${item.discount_percent}%</td>
                    <td>${item.gst_percent}%</td>
                    <td>₹${parseFloat(item.line_total).toFixed(2)}</td>
                </tr>`;
            });
            $('#modalBody').html(html);
            $('#viewModal').css('display', 'flex');
        });
    }

    function closeModal() { $('#viewModal').hide(); }

    async function changeLang(lang) {
        try {
            localStorage.setItem('rupnidhi_lang', lang);
            const response = await fetch(`lang/${lang}.json`);
            if (!response.ok) throw new Error("Language file not found");
            const translations = await response.json();
            document.querySelectorAll('[data-key]').forEach(el => {
                const key = el.getAttribute('data-key');
                if (translations[key]) {
                    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') { el.setAttribute('placeholder', translations[key]); } 
                    else {
                        if (el.children.length === 0) { el.innerText = translations[key]; } 
                        else {
                            const textNode = Array.from(el.childNodes).find(node => node.nodeType === 3 && node.textContent.trim() !== "");
                            if (textNode) textNode.textContent = translations[key];
                        }
                    }
                }
            });
            if (lang === 'ta') { document.body.classList.add('lang-ta'); } else { document.body.classList.remove('lang-ta'); }
            const btnEn = document.getElementById('btn-en'), btnTa = document.getElementById('btn-ta');
            if (btnEn && btnTa) {
                if (lang === 'en') { btnEn.classList.add('active'); btnTa.classList.remove('active'); } 
                else { btnTa.classList.add('active'); btnEn.classList.remove('active'); }
            }
        } catch (error) { console.error("Language Error:", error); }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const savedLang = localStorage.getItem('rupnidhi_lang') || 'en';
        setTimeout(() => changeLang(savedLang), 150);
        addNewRow();
    });

    function addNewRow() {
        const count = $('#items-container tr').length + 1;
        const row = `<tr>
            <td style="color: #71717a; font-weight: 700; text-align: center; padding-top: 15px;">${count}</td>
            <td class="stacked-input-container">
                <input type="text" name="materials[]" data-key="placeholder-item-name" placeholder="Item Name" required>
                <input type="text" name="item_code[]" data-key="ph-item-code" placeholder="Item Code">
            </td>
            <td><input type="text" name="hsn_code[]" data-key="th-hsn" placeholder="HSN" style="width:100%;"></td>
            <td><div class="qty-unit-group"><input type="number" name="qtys[]" class="qty" value="0" step="0.01"><select name="unit[]"><option data-key="unit-kg">Kg</option><option data-key="unit-nos">Nos</option><option data-key="unit-pcs">Pcs</option></select></div></td>
            <td><input type="number" name="prices[]" class="price" value="0" step="0.01" style="width:100%;"></td>
            <td><input type="number" name="discount[]" class="discount" value="0" style="width:100%;"></td>
            <td><select name="gst_percent[]" class="gst" style="width:100%;"><option value="0">0%</option><option value="5">5%</option><option value="12">12%</option><option value="18" selected>18%</option></select></td>
            <td><input type="text" name="totals[]" class="line_total" readonly value="0.00" style="width:100%; font-weight:600;"></td>
            <td style="text-align: center; vertical-align: middle;"><button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="ph-bold ph-trash"></i></button></td>
        </tr>`;
        $('#items-container').append(row);
        const currentLang = localStorage.getItem('rupnidhi_lang') || 'en';
        if(typeof changeLang === 'function') { changeLang(currentLang); }
    }

    function removeRow(btn) { if($('#items-container tr').length > 1) { $(btn).closest('tr').remove(); calculateTotals(); } }

    $(document).on('input', '.qty, .price, .discount, .gst, #transport, #paidAmount', calculateTotals);

    function calculateTotals() {
        let subTotal = 0, totalTax = 0;
        $('#items-container tr').each(function() {
            let qty = parseFloat($(this).find('.qty').val()) || 0, price = parseFloat($(this).find('.price').val()) || 0, disc = parseFloat($(this).find('.discount').val()) || 0, gst = parseFloat($(this).find('.gst').val()) || 0;
            let basePrice = qty * price, afterDisc = basePrice - (basePrice * (disc / 100)), taxValue = afterDisc * (gst / 100), lineTotal = afterDisc + taxValue;
            $(this).find('.line_total').val(lineTotal.toFixed(2));
            subTotal += afterDisc; totalTax += taxValue;
        });
        let freight = parseFloat($('#transport').val()) || 0, grandTotal = subTotal + totalTax + freight, paid = parseFloat($('#paidAmount').val()) || 0;
        $('#netTotal').val(subTotal.toFixed(2)); $('#taxAmount').val(totalTax.toFixed(2)); $('#grandTotal').val(grandTotal.toFixed(2)); $('#balanceAmount').val((grandTotal - paid).toFixed(2));
    }

    function savePO() {
        if(!$('#shopName').val()) { alert("Please enter Vendor Name"); return; }
        const btn = $('#submitBtn');
        btn.prop('disabled', true).html('Saving... <i class="ph-bold ph-spinner"></i>');
        $.ajax({
            url: window.location.href, type: 'POST', data: $('#poForm').serialize(),
            success: function(response) { 
                if (response.trim() === 'success') { alert("Purchase Order Saved Successfully and sent to CFO for approval!"); location.reload(); } 
                else { alert("Database Error: " + response); btn.prop('disabled', false).html('<span data-key="btn-generate">SUBMIT PO</span> <i class="ph-bold ph-arrow-right"></i>'); }
            },
            error: function() { alert("Error connecting to server."); btn.prop('disabled', false).html('<span data-key="btn-generate">SUBMIT PO</span> <i class="ph-bold ph-arrow-right"></i>'); }
        });
    }

    function deletePO(id) { if(confirm("Are you sure you want to delete this Purchase Order?")) { $.post(window.location.href, {ajax_action: 'delete_po', id: id}, function(res) { if(res.trim() === 'success') { $('#row_' + id).fadeOut(); } else { alert("Error deleting record."); } }); } }

    function printPO(id) {
        const existingFrame = document.getElementById('printFrame'); if (existingFrame) { document.body.removeChild(existingFrame); }
        const iframe = document.createElement('iframe'); iframe.id = 'printFrame'; iframe.style.position = 'fixed'; iframe.style.right = '0'; iframe.style.bottom = '0'; iframe.style.width = '0'; iframe.style.height = '0'; iframe.style.border = '0';
        iframe.src = 'poprint.php?id=' + id; document.body.appendChild(iframe);
    }
</script>
</body>
</html>