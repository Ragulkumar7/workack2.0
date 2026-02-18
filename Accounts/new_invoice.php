<?php 
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --theme-color: #1b5a5a;
            --theme-light: #e0f2f1;
            --bg-body: #f3f4f6;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.03);
            --primary-sidebar-width: 95px;
            --secondary-sidebar-width: 220px; 
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            color: var(--text-main);
        }

        /* --- LAYOUT --- */
        .main-content {
            margin-left: var(--primary-sidebar-width);
            padding: 30px;
            width: calc(100% - var(--primary-sidebar-width));
            transition: margin-left 0.3s ease, width 0.3s ease;
            min-height: 100vh;
        }

        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header-area h2 { margin: 0; color: var(--theme-color); font-weight: 700; }
        .header-area p { color: var(--text-muted); font-size: 13px; margin: 5px 0 0; }

        /* --- CARDS & FORMS --- */
        .invoice-card, .history-section {
            background: white; padding: 30px; border-radius: 12px;
            box-shadow: var(--card-shadow); border: 1px solid var(--border-color); margin-bottom: 30px;
        }

        .invoice-header { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f1f5f9; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; outline: none; font-size: 14px; background: #fff; }
        .form-group input:focus, .form-group select:focus { border-color: var(--theme-color); }

        /* Client Details */
        .client-details { background: var(--theme-light); padding: 15px; border-radius: 8px; margin-bottom: 20px; display: none; border-left: 4px solid var(--theme-color); }
        .client-details h4 { margin: 0 0 5px; font-size: 14px; color: var(--theme-color); }
        .client-details p { margin: 3px 0; font-size: 13px; color: #555; }

        /* --- TABLES --- */
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .items-table th, .history-table th { background: #f8fafc; padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); }
        td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .items-table input { width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; box-sizing: border-box; }

        /* --- BUTTONS & BADGES --- */
        .btn-add-row { background: var(--theme-color); color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; margin-top: 10px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-remove { background: #ef4444; color: white; width: 24px; height: 24px; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px; }
        .btn-save { background: var(--theme-color); color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .btn-cancel { background: #f1f5f9; color: var(--text-muted); padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-unpaid { background: #fee2e2; color: #dc2626; }
        .badge-pending { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .action-btns i { font-size: 18px; margin-right: 8px; transition: transform 0.2s; cursor:pointer; }
        .action-btns i:hover { transform: scale(1.1); }

        /* --- SUMMARY --- */
        .calculation-summary { display: flex; justify-content: flex-end; margin-top: 20px; }
        .summary-box { width: 100%; max-width: 350px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; align-items: center; }
        .summary-row.total { font-weight: 700; font-size: 16px; color: var(--theme-color); padding-top: 10px; border-top: 2px solid #cbd5e1; }

        /* --- MODAL STYLES (NEW) --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 2000; justify-content: center; align-items: center;
        }
        .modal-content {
            background: white; width: 800px; max-width: 90%; max-height: 90vh;
            border-radius: 12px; padding: 30px; overflow-y: auto; position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; }
        .close-modal { font-size: 24px; cursor: pointer; color: #64748b; }
        
        /* Status Pill */
        .status-pill {
            background: #f1f5f9; color: #64748b; 
            padding: 8px 16px; border-radius: 30px; 
            font-size: 12px; font-weight: 700; border: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 6px;
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .invoice-header { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main id="mainContent" class="main-content">
    
    <div class="header-area">
        <div>
            <h2>Invoice Management</h2>
            <p>Create and manage client invoices with bank selection</p>
        </div>
        <div class="status-pill" id="currentStatus">
            <i class="ph-fill ph-circle"></i> Status: Draft Mode
        </div>
    </div>

    <div class="invoice-card" id="formCard">
        <form method="POST" id="invoiceForm" onsubmit="event.preventDefault(); sendToCFO();">
            <input type="hidden" name="invoice_id" id="invoice_id">
            
            <div class="invoice-header">
                <div class="form-group">
                    <label>Invoice Number</label>
                    <input type="text" name="invoice_no" id="invoice_no" value="INV-2026-015" readonly style="background:#f1f5f9; cursor:not-allowed;">
                </div>
                
                <div class="form-group">
                    <label>Client Name</label>
                    <select name="client_id" id="client_id" required onchange="loadClientDetails()">
                        <option value="">Select Client</option>
                        <option value='7'>Arvind Builders</option>
                        <option value='4'>Facebook India</option>
                        <option value='3'>Google India Pvt Ltd</option>
                        <option value='2'>Neoera</option>
                        <option value='5'>Test Client</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Receiving Bank</label>
                    <input type="text" name="bank_name" id="bank_name" list="bank_list" placeholder="Search Bank..." required>
                    <datalist id="bank_list">
                        <option value='Canara'><option value='HDFC'><option value='HSBC'><option value='ICICI'><option value='SBI'>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label>Invoice Date</label>
                    <input type="date" name="invoice_date" id="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="client-details" id="clientDetails"></div>

            <div class="items-section">
                <h3 style="margin-bottom: 15px; font-size: 16px; color: var(--theme-color);">Invoice Items</h3>
                <div class="table-responsive">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 30%;">Description</th>
                                <th style="width: 10%;">Qty</th>
                                <th style="width: 15%;">Rate</th>
                                <th style="width: 10%;">GST %</th>
                                <th style="width: 15%;">GST Amt</th>
                                <th style="width: 15%;">Total</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <tr class="item-row">
                                <td>1</td>
                                <td><input type="text" name="item_desc[]" class="item-desc" placeholder="Item description"></td>
                                <td><input type="number" name="item_qty[]" class="item-qty" value="1" min="1" onchange="calculateRow(this)"></td>
                                <td><input type="number" name="item_rate[]" class="item-rate" step="0.01" placeholder="0.00" onchange="calculateRow(this)"></td>
                                <td><input type="number" name="item_gst[]" class="item-gst" value="18" step="0.01" onchange="calculateRow(this)"></td>
                                <td><input type="number" name="item_gst_amt[]" class="item-gst-amt" readonly style="background:#f8fafc;"></td>
                                <td><input type="number" name="item_total[]" class="item-total" readonly style="background:#f8fafc; font-weight:600;"></td>
                                <td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="ph ph-x"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn-add-row" onclick="addRow()"><i class="ph ph-plus-circle"></i> Add Item</button>
            </div>

            <div class="calculation-summary">
                <div class="summary-box">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="displaySubtotal">₹0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Discount:</span>
                        <input type="number" name="discount" id="discount" value="0" step="0.01" onchange="calculateGrandTotal()">
                    </div>
                    <div class="summary-row">
                        <span>Total GST:</span>
                        <span id="displayTax">₹0.00</span>
                    </div>
                    <div class="summary-row total">
                        <span>Grand Total:</span>
                        <span id="displayGrandTotal">₹0.00</span>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 25px;">
                <div class="form-group">
                    <label>Payment Terms</label>
                    <textarea name="payment_terms" rows="3" placeholder="Payment due within 15 days"></textarea>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="Additional notes or instructions"></textarea>
                </div>
            </div>

            <input type="hidden" name="sub_total" id="sub_total">
            <input type="hidden" name="tax_amount" id="tax_amount">
            <input type="hidden" name="grand_total" id="grand_total">

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="resetForm()">Reset</button>
                <button type="submit" id="saveBtn" class="btn-save">
                    <i class="ph-bold ph-paper-plane-right"></i> Send to CFO for Approval
                </button>
            </div>
        </form>
    </div>

    <div class="history-section">
        <h3 style="margin-bottom: 20px; font-size: 16px; color: var(--theme-color);">Recent Invoice History</h3>
        <div class="table-responsive">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Client Name</th>
                        <th>Date</th>
                        <th>Bank</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="row-INV-2026-013">
                        <td><strong>INV-2026-013</strong></td>
                        <td>Facebook India</td>
                        <td>2026-01-30</td>
                        <td><small style='color:#64748b;'>Canara</small></td>
                        <td>₹944.00</td>
                        <td><span class='badge badge-unpaid'>Unpaid</span></td>
                        <td class='action-btns'>
                            <i class='ph ph-pencil-simple' onclick="editInvoice('INV-2026-013', 'Facebook India', '2026-01-30', 'Canara')" title="Edit" style='color: #f59e0b;'></i>
                            <i class='ph ph-eye' onclick="viewInvoice('INV-2026-013', 'Facebook India', '2026-01-30', '944.00')" title="View" style='color: #3b82f6;'></i>
                            <i class='ph ph-printer' onclick="alert('Approval Required: You cannot print this invoice until the CFO approves it.')" title="Print Disabled" style='color: #94a3b8; cursor:not-allowed;'></i>
                            <i class='ph ph-trash' onclick="deleteInvoice('INV-2026-013')" title="Delete" style='color:#ef4444;'></i>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="invoiceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0; color:var(--text-main);">Invoice Preview</h3>
            <i class="ph ph-x close-modal" onclick="closeModal()"></i>
        </div>
        
        <div class="invoice-preview-box">
            <div class="preview-header">
                <div>
                    <div class="preview-title">INVOICE</div>
                    <div style="color:#64748b; font-size:14px;" id="modal_inv_no">#INV-001</div>
                </div>
                <div style="text-align:right;">
                    <h4 style="margin:0;">Your Company Name</h4>
                    <p style="margin:2px 0; font-size:12px; color:#64748b;">123 Street Name, City</p>
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px; margin-bottom:30px;">
                <div>
                    <p style="font-weight:700; font-size:12px; color:#64748b; margin-bottom:5px;">BILL TO:</p>
                    <h4 style="margin:0;" id="modal_client">Client Name</h4>
                    <p style="font-size:13px; color:#555;">Client Address goes here...</p>
                </div>
                <div style="text-align:right;">
                    <p style="font-size:13px;"><strong>Date:</strong> <span id="modal_date">2026-01-01</span></p>
                    <p style="font-size:13px;"><strong>Bank:</strong> Canara Bank</p>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr><th>Description</th><th style="text-align:right">Total</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Consulting Services (Example Item)</td>
                        <td style="text-align:right" id="modal_amount">₹0.00</td>
                    </tr>
                </tbody>
            </table>
            
            <div style="margin-top:20px; text-align:right;">
                <h3>Total: <span id="modal_total_large">₹0.00</span></h3>
            </div>
        </div>
    </div>
</div>

<script>
    // --- WORKFLOW LOGIC ---
    function sendToCFO() {
        const btn = document.getElementById('saveBtn');
        const status = document.getElementById('currentStatus');
        
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Sending...';
        btn.style.opacity = '0.8';

        setTimeout(() => {
            status.style.background = '#fff7ed';
            status.style.color = '#c2410c';
            status.style.borderColor = '#ffedd5';
            status.innerHTML = '<i class="ph-fill ph-clock-counter-clockwise"></i> Pending CFO Approval';
            
            btn.innerHTML = '<i class="ph-bold ph-check"></i> Request Sent';
            btn.style.backgroundColor = '#10b981';
            btn.disabled = true;

            alert("Request Sent! \n\nThe invoice has been submitted to the CFO. You can print it once approved.");
        }, 1500);
    }

    // --- FORM LOGIC ---
    let rowCount = 1;

    function loadClientDetails() {
        const clientId = document.getElementById('client_id').value;
        const detailsBox = document.getElementById('clientDetails');
        if (!clientId) { detailsBox.style.display = 'none'; return; }
        const sel = document.getElementById('client_id');
        const text = sel.options[sel.selectedIndex].text;
        detailsBox.innerHTML = `<h4>Bill To: ${text}</h4><p>GSTIN associated with this client will load from DB.</p>`;
        detailsBox.style.display = 'block';
    }

    function addRow() {
        rowCount++;
        const tbody = document.getElementById('itemsTableBody');
        const newRow = `
            <tr class="item-row">
                <td>${rowCount}</td>
                <td><input type="text" name="item_desc[]" class="item-desc" placeholder="Item description"></td>
                <td><input type="number" name="item_qty[]" class="item-qty" value="1" min="1" onchange="calculateRow(this)"></td>
                <td><input type="number" name="item_rate[]" class="item-rate" step="0.01" placeholder="0.00" onchange="calculateRow(this)"></td>
                <td><input type="number" name="item_gst[]" class="item-gst" value="18" step="0.01" onchange="calculateRow(this)"></td>
                <td><input type="number" name="item_gst_amt[]" class="item-gst-amt" readonly style="background:#f8fafc;"></td>
                <td><input type="number" name="item_total[]" class="item-total" readonly style="background:#f8fafc; font-weight:600;"></td>
                <td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="ph ph-x"></i></button></td>
            </tr>`;
        tbody.insertAdjacentHTML('beforeend', newRow);
    }

    function removeRow(btn) {
        if (document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
            calculateGrandTotal();
        }
    }

    function calculateRow(input) {
        const row = input.closest('tr');
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        const gstRate = parseFloat(row.querySelector('.item-gst').value) || 0;
        const subtotal = qty * rate;
        const gstAmount = (subtotal * gstRate) / 100;
        const total = subtotal + gstAmount;
        row.querySelector('.item-gst-amt').value = gstAmount.toFixed(2);
        row.querySelector('.item-total').value = total.toFixed(2);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let subtotal = 0; let totalGst = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            subtotal += (parseFloat(row.querySelector('.item-qty').value)||0) * (parseFloat(row.querySelector('.item-rate').value)||0);
            totalGst += parseFloat(row.querySelector('.item-gst-amt').value)||0;
        });
        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const grandTotal = subtotal + totalGst - discount;
        
        document.getElementById('displaySubtotal').textContent = '₹' + subtotal.toFixed(2);
        document.getElementById('displayTax').textContent = '₹' + totalGst.toFixed(2);
        document.getElementById('displayGrandTotal').textContent = '₹' + grandTotal.toFixed(2);
    }

    function editInvoice(invNo, clientName, date, bank) {
        document.getElementById('mainContent').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('invoice_no').value = invNo;
        document.getElementById('invoice_date').value = date;
        document.getElementById('bank_name').value = bank;
        
        const select = document.getElementById('client_id');
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].text === clientName) {
                select.selectedIndex = i;
                break;
            }
        }
        loadClientDetails();
        const btn = document.getElementById('saveBtn');
        btn.innerHTML = "<i class='ph ph-pencil-simple'></i> Update Request";
        btn.style.background = "#d97706"; 
    }

    function viewInvoice(invNo, client, date, amount) {
        document.getElementById('modal_inv_no').innerText = invNo;
        document.getElementById('modal_client').innerText = client;
        document.getElementById('modal_date').innerText = date;
        document.getElementById('modal_amount').innerText = '₹' + amount;
        document.getElementById('modal_total_large').innerText = '₹' + amount;
        document.getElementById('invoiceModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('invoiceModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('invoiceModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    function deleteInvoice(id) { 
        if(confirm('Are you sure you want to delete invoice ' + id + '?')) {
            document.getElementById('row-' + id).remove();
        } 
    }
    
    function resetForm() {
        if(confirm("Reset form?")) {
             document.getElementById('invoiceForm').reset();
             document.getElementById('clientDetails').style.display = 'none';
             calculateGrandTotal();
        }
    }
</script>

</body>
</html>