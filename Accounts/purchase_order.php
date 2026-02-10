<?php
// sidebar.php is one level up from the Accounts folder
include '../sidebars.php'; 
include '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Management</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1b5a5a;
            --accent-gold: #D4AF37;
            --bg-light: #f8fafc;
            --border: #e4e4e7;
        }

        /* Shift content when sidebar is open */
        .main-content {
            margin-left: 95px; 
            padding: 30px;
            transition: all 0.3s ease;
            min-height: 100vh;
            background: var(--bg-light);
        }

        .main-shifted {
            margin-left: 315px; 
        }

        .header-section { margin-bottom: 25px; }
        .header-section h2 { color: var(--primary-color); font-weight: 700; margin: 0; }
        .header-section p { color: #71717a; font-size: 13px; margin: 5px 0 0; }

        .card { 
            background: #fff; 
            border-radius: 12px; 
            border: 1px solid var(--border); 
            margin-bottom: 30px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            overflow: hidden;
        }

        .card-header { 
            background: var(--primary-color); 
            padding: 15px 25px; 
            border-bottom: 3px solid var(--accent-gold); 
        }
        .card-header h3 { color: #fff; margin: 0; font-size: 18px; }

        .card-body { padding: 25px; }

        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 20px; 
        }

        .form-group { display: flex; flex-direction: column; gap: 5px; }
        label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #52525b; }
        
        input, select, textarea { 
            padding: 10px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            font-size: 14px; 
            outline: none; 
        }
        input:focus { border-color: var(--primary-color); }

        .section-title { 
            font-size: 14px; 
            font-weight: 700; 
            color: var(--primary-color); 
            margin: 20px 0 15px; 
            padding-bottom: 8px; 
            border-bottom: 1px dashed var(--border); 
        }

        .table-responsive { overflow-x: auto; }
        .items-table, .history-table { width: 100%; border-collapse: collapse; }
        
        .items-table th, .history-table th { 
            background: #f4f4f5; 
            padding: 12px; 
            text-align: left; 
            font-size: 11px; 
            text-transform: uppercase; 
            color: #71717a; 
        }
        .items-table td, .history-table td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 13px; }

        .btn-add-row { background: #10b981; color: #fff; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-top: 10px; }
        .btn-remove { background: #ef4444; color: #fff; border: none; border-radius: 4px; padding: 5px 8px; cursor: pointer; }

        .summary-wrapper { display: flex; justify-content: flex-end; margin-top: 20px; }
        .summary-box { width: 320px; background: #eefcfd; padding: 20px; border-radius: 10px; border: 1px solid #c7ecee; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .summary-row.total { 
            font-weight: 700; 
            font-size: 16px; 
            color: var(--primary-color); 
            border-top: 1px solid #cbd5e1; 
            padding-top: 10px; 
            margin-top: 10px; 
        }

        .btn-save { 
            background: var(--primary-color); 
            color: #fff; 
            border: none; 
            padding: 12px 30px; 
            border-radius: 8px; 
            font-weight: 700; 
            cursor: pointer; 
            width: 100%;
        }

        .action-btns { display: flex; gap: 10px; }
        .btn-view { color: #3b82f6; cursor: pointer; font-size: 18px; }
        .btn-delete { color: #ef4444; cursor: pointer; font-size: 18px; }

        /* MODAL STYLES */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: none; justify-content: center;
            align-items: center; z-index: 2000;
        }
        .modal-content {
            background: #fff; padding: 30px; border-radius: 12px;
            width: 90%; max-width: 600px; position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-header { border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;}
        .close-modal { cursor: pointer; color: #71717a; font-size: 20px; }
        
        .success-popup {
            position: fixed; top: 20px; right: 20px; background: #10b981; color: white;
            padding: 15px 25px; border-radius: 8px; display: none; z-index: 3000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .main-content, .main-shifted { margin-left: 0; padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
            .summary-box { width: 100%; }
        }
    </style>
</head>
<body>

<div id="msgPopup" class="success-popup">Purchase Order Saved Successfully!</div>

<div id="viewModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--primary-color); margin:0;">Purchase Order Details</h3>
            <i class="ph ph-x close-modal" onclick="closeModal()"></i>
        </div>
        <div id="modalBody">
            </div>
        <button class="btn-save" style="margin-top:20px;" onclick="closeModal()">Close</button>
    </div>
</div>

<main class="main-content" id="mainContent">
    <div class="header-section">
        <h2>Purchase Order Management</h2>
        <p>Create and manage vendor purchase orders for the company.</p>
    </div>

    <form onsubmit="event.preventDefault(); addPOtoTable();">
        <div class="card">
            <div class="card-header">
                <h3><i class="ph ph-file-plus"></i> New Purchase Order</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>PO Number</label>
                        <input type="text" id="po_number" value="PO-IT-2026-<?= rand(100,999) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>PO Date</label>
                        <input type="date" id="po_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="po_type">
                            <option>Accounting</option>
                            <option>Non-Accounting</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">Vendor Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Vendor Name</label>
                        <input type="text" id="vendor_name" placeholder="Enter vendor name" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" id="vendor_phone" placeholder="+91 00000 00000">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="vendor_email" placeholder="vendor@example.com">
                    </div>
                    <div class="form-group">
                        <label>GST Number</label>
                        <input type="text" id="vendor_gst" placeholder="29ABCDE1234F1Z5">
                    </div>
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" id="bank_name" placeholder="e.g. HDFC, SBI, ICICI">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea id="vendor_address" rows="1" placeholder="Vendor complete address"></textarea>
                    </div>
                </div>

                <div class="section-title">Items & Particulars</div>
                <div class="table-responsive">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Description</th>
                                <th style="width: 100px;">Qty</th>
                                <th style="width: 150px;">Rate (₹)</th>
                                <th style="width: 150px;">Total (₹)</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="poItemsBody">
                            <tr class="item-row">
                                <td>1</td>
                                <td><input type="text" placeholder="Item description..."></td>
                                <td><input type="number" class="qty" value="1" min="1" oninput="calcTotal()"></td>
                                <td><input type="number" class="rate" placeholder="0.00" oninput="calcTotal()"></td>
                                <td><input type="number" class="row-total" value="0.00" readonly></td>
                                <td><button type="button" class="btn-remove" onclick="removeRow(this)">×</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn-add-row" onclick="addRow()">+ Add New Item</button>

                <div class="summary-wrapper">
                    <div class="summary-box">
                        <div class="summary-row">
                            <span>Sub Total:</span>
                            <span id="subTotalDisplay">₹ 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax / GST (%):</span>
                            <input type="number" id="taxInput" value="18" style="width: 60px; padding: 2px 5px;" oninput="calcTotal()">
                        </div>
                        <div class="summary-row total">
                            <span>Grand Total:</span>
                            <span id="grandTotalDisplay">₹ 0.00</span>
                        </div>
                        <div style="margin-top: 15px;">
                            <button type="submit" class="btn-save">Save Purchase Order</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div style="padding:20px; border-bottom:1px solid var(--border);">
            <h3 style="margin: 0; color: var(--primary-color);">Recent Purchase Orders</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Vendor</th>
                            <th>Contact</th>
                            <th>Date</th>
                            <th>Grand Total</th>
                            <th>Bank</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="poHistoryBody">
                        <tr>
                            <td><strong>PO-IT-2026-205</strong></td>
                            <td>Catherine</td>
                            <td>8956231456</td>
                            <td>30-Jan-2026</td>
                            <td><strong>₹ 600.00</strong></td>
                            <td>HDFC</td>
                            <td class="action-btns">
                                <i class="ph ph-eye btn-view" onclick="viewPO('PO-IT-2026-205', 'Catherine', '8956231456', '30-Jan-2026', '₹ 600.00', 'HDFC')"></i>
                                <i class="ph ph-trash btn-delete" onclick="this.closest('tr').remove()"></i>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    function addRow() {
        const tbody = document.getElementById('poItemsBody');
        const rowCount = tbody.rows.length + 1;
        const row = `
            <tr class="item-row">
                <td>${rowCount}</td>
                <td><input type="text" placeholder="Item description..."></td>
                <td><input type="number" class="qty" value="1" min="1" oninput="calcTotal()"></td>
                <td><input type="number" class="rate" placeholder="0.00" oninput="calcTotal()"></td>
                <td><input type="number" class="row-total" value="0.00" readonly></td>
                <td><button type="button" class="btn-remove" onclick="removeRow(this)">×</button></td>
            </tr>`;
        tbody.insertAdjacentHTML('beforeend', row);
    }

    function removeRow(btn) {
        if (document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
            calcTotal();
            document.querySelectorAll('.item-row').forEach((r, i) => r.cells[0].innerText = i + 1);
        }
    }

    function calcTotal() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const q = row.querySelector('.qty').value || 0;
            const r = row.querySelector('.rate').value || 0;
            const rowTotal = q * r;
            row.querySelector('.row-total').value = rowTotal.toFixed(2);
            subtotal += rowTotal;
        });

        const taxRate = document.getElementById('taxInput').value || 0;
        const taxAmount = subtotal * (taxRate / 100);
        const grandTotal = subtotal + taxAmount;

        document.getElementById('subTotalDisplay').innerText = '₹ ' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('grandTotalDisplay').innerText = '₹ ' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2});
    }

    function addPOtoTable() {
        const poNum = document.getElementById('po_number').value;
        const vendor = document.getElementById('vendor_name').value;
        const phone = document.getElementById('vendor_phone').value;
        const date = document.getElementById('po_date').value;
        const total = document.getElementById('grandTotalDisplay').innerText;
        const bank = document.getElementById('bank_name').value || '-';

        const historyBody = document.getElementById('poHistoryBody');
        const newRow = `
            <tr>
                <td><strong>${poNum}</strong></td>
                <td>${vendor}</td>
                <td>${phone}</td>
                <td>${date}</td>
                <td><strong>${total}</strong></td>
                <td>${bank}</td>
                <td class="action-btns">
                    <i class="ph ph-eye btn-view" onclick="viewPO('${poNum}', '${vendor}', '${phone}', '${date}', '${total}', '${bank}')"></i>
                    <i class="ph ph-trash btn-delete" onclick="this.closest('tr').remove()"></i>
                </td>
            </tr>`;
        historyBody.insertAdjacentHTML('afterbegin', newRow);
        
        // Show Message Popup (Custom notification instead of alert)
        const popup = document.getElementById('msgPopup');
        popup.style.display = 'block';
        setTimeout(() => { popup.style.display = 'none'; }, 3000);
    }

    function viewPO(po, vendor, contact, date, total, bank) {
        const body = document.getElementById('modalBody');
        body.innerHTML = `
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:20px;">
                <div><label>PO Number:</label><p><strong>${po}</strong></p></div>
                <div><label>Date:</label><p>${date}</p></div>
                <div><label>Vendor:</label><p>${vendor}</p></div>
                <div><label>Contact:</label><p>${contact}</p></div>
                <div><label>Bank:</label><p>${bank}</p></div>
                <div><label>Grand Total:</label><p style="color:var(--primary-color); font-weight:700;">${total}</p></div>
            </div>
        `;
        document.getElementById('viewModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('viewModal').style.display = 'none';
    }
</script>

</body>
</html>