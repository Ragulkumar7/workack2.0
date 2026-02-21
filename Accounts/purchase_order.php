<?php 
include '../sidebars.php'; 
include '../header.php';
// Commented out includes for testing purposes, uncomment in your real file
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
            /* Sidebar Variables (from Code 1) */
            --sidebar-bg: #0f172a;
            --accent: #d4af37;
            --accent-hover: #c19b2e;
            --accent-glow: rgba(212, 175, 55, 0.3);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --hover-bg: rgba(255, 255, 255, 0.08);
            --border-color: rgba(255, 255, 255, 0.1);
            --sidebar-width: 280px;

            /* UI Variables (from Code 2) */
            --primary-color: #1b5a5a;
            --accent-gold: #D4AF37;
            --bg-light: #f8fafc;
            --border: #e4e4e7;
        }

        body { 
            background-color: var(--bg-light); 
            font-family: "Plus Jakarta Sans", sans-serif; 
            color: #1e293b; 
            margin: 0;
            padding: 0;
        }

        /* --- SIDEBAR STYLES (From Code 1) --- */
        .sidebar {
            width: var(--sidebar-width);
            height: 95vh;
            background: var(--sidebar-bg);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            margin: 2.5vh 0 0 1.5vw;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            position: fixed;
            left: 0;
            top: 0;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 1000;
        }

        .logo-area { padding: 25px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-color); }
        .logo-image { width: 50px; height: 50px; border-radius: 10px; object-fit: cover; box-shadow: 0 0 15px var(--accent-glow); }
        .logo-text { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; color: white; }
        .logo-text span { color: var(--accent); }

        .lang-switcher-container { padding: 15px 25px; border-bottom: 1px solid var(--border-color); }
        .lang-switch { display: flex; background: rgba(255, 255, 255, 0.05); padding: 4px; border-radius: 12px; position: relative; border: 1px solid var(--border-color); }
        .lang-btn { flex: 1; padding: 8px 0; border: none; background: transparent; color: var(--text-muted); font-size: 12px; font-weight: 700; cursor: pointer; z-index: 2; transition: color 0.3s ease; text-align: center; }
        .lang-btn.active { color: var(--sidebar-bg); }
        .lang-slider { position: absolute; width: calc(50% - 4px); height: calc(100% - 8px); background: var(--accent); border-radius: 8px; top: 4px; left: 4px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1; }
        body.lang-ta .lang-slider { transform: translateX(100%); }

        .nav-scroll { flex: 1; overflow-y: auto; padding: 20px 15px; scrollbar-width: none; -ms-overflow-style: none; }
        .nav-scroll::-webkit-scrollbar { display: none; }
        .nav-list { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 5px; }
        .nav-link { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-radius: 12px; color: var(--text-muted); text-decoration: none; transition: all 0.3s ease; cursor: pointer; font-weight: 500; font-size: 14px; }
        .nav-link-content { display: flex; align-items: center; gap: 12px; }
        .nav-link i { font-size: 20px; }
        .chevron { font-size: 14px !important; transition: transform 0.3s; }
        .nav-link:hover { background: var(--hover-bg); color: white; padding-left: 20px; }

        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out; padding-left: 15px; position: relative; list-style: none; margin: 0; }
        .submenu::before { content: ""; position: absolute; left: 33px; top: 0; bottom: 0; width: 2px; background: rgba(255, 255, 255, 0.05); }
        .submenu-link { display: flex; align-items: center; padding: 10px 15px 10px 25px; color: var(--text-muted); font-size: 13px; text-decoration: none; margin: 2px 0; border-radius: 8px; transition: 0.2s; position: relative; }
        .submenu-link:hover { color: white; background: rgba(255, 255, 255, 0.03); }

        .nav-item.open > .submenu { max-height: 800px; }
        .nav-item.open > .nav-link .chevron { transform: rotate(180deg); color: white; }
        .nav-item.open > .nav-link { color: white; background: var(--hover-bg); }
        .submenu-link.active-sub { color: var(--accent); font-weight: 600; background: rgba(255, 255, 255, 0.03); }

        .user-profile { padding: 20px; background: rgba(0, 0, 0, 0.2); border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; }
        .user-img { width: 36px; height: 36px; border-radius: 50%; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
        .user-info h4 { font-size: 14px; color: white; margin: 0; }
        .user-info p { font-size: 11px; color: var(--text-muted); margin: 0; }

        /* --- MAIN CONTENT & UI STYLES (From Code 2 + Merged Logic) --- */
        .main-content {
            margin-left: 95px; /* Added to prevent overlap with your external sidebar */
            padding: 30px;
            transition: all 0.3s ease;
            min-height: 100vh;
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
        .card-header h3 { color: #fff; margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;}

        .card-body { padding: 25px; }

        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 20px; 
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #52525b; }
        
        input, select, textarea { 
            padding: 10px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            font-size: 13px; 
            outline: none; 
            background: #fff;
            color: #3f3f46;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--primary-color); }
        input[readonly] { background: #f4f4f5; }

        .section-title { 
            font-size: 14px; 
            font-weight: 700; 
            color: var(--primary-color); 
            margin: 25px 0 15px; 
            padding-bottom: 8px; 
            border-bottom: 1px dashed var(--border); 
        }

        /* Merged Custom Form Components */
        .stacked-input-container { display: flex; flex-direction: column; }
        .stacked-input-container input:first-child { border-bottom-left-radius: 0; border-bottom-right-radius: 0; border-bottom: none; }
        .stacked-input-container input:last-child { border-top-left-radius: 0; border-top-right-radius: 0; background-color: #f8fafc; font-size: 11px; height: 32px; }
        
        .qty-unit-group { display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff;}
        .qty-unit-group input { border: none !important; width: 60%; text-align: center; border-radius: 0; }
        .qty-unit-group select { border: none !important; width: 40%; background: #f4f4f5; border-left: 1px solid var(--border) !important; cursor: pointer; border-radius: 0;}

        /* Tables */
        .table-responsive { overflow-x: auto; margin-bottom: 10px; }
        .items-table, .history-table { width: 100%; border-collapse: collapse; }
        .items-table th, .history-table th { 
            background: #f4f4f5; 
            padding: 12px; 
            text-align: left; 
            font-size: 11px; 
            text-transform: uppercase; 
            color: #71717a; 
            font-weight: 700;
        }
        .items-table td, .history-table td { padding: 10px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: top; }

        /* Buttons & Summaries */
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
        .btn-print:hover { background: #c7d2fe; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .btn-delete:hover { background: #fecaca; }
        .btn-remove-row { color: #ef4444; cursor: pointer; font-size: 18px; border: none; background: transparent; padding: 5px; }

        @media (max-width: 768px) { 
            .sidebar { transform: translateX(-120%); margin: 0; height: 100vh; border-radius: 0; } 
            .main-content { margin-left: 0; padding: 15px; } 
            .form-grid { grid-template-columns: 1fr; }
            .summary-section { width: 100%; }
        }
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
                        <input type="text" name="po_no" value="PO-20260221-691" readonly>
                    </div>
                    <div class="form-group">
                        <label><span data-key="label-po-date">PO Date</span></label>
                        <input type="date" name="po_date" value="2026-02-21">
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
                        <tbody id="items-container">
                            </tbody>
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
                                <input type="text" id="taxAmount" value="0.00" readonly>
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
                        <span data-key="btn-generate">Generate PO</span> <i class="ph-bold ph-arrow-right"></i>
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
                            <th class="text-center" style="text-align: center;" data-key="th-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id='row_90'>
                            <td style="color: var(--primary-color); font-weight: 700;">PO-20260204-429</td>
                            <td>30-11--0001</td>
                            <td>test</td>
                            <td>₹4,672.80</td>
                            <td>₹0.00</td>
                            <td style="color: #ef4444; font-weight: 700;">₹4,672.80</td>
                            <td class="action-btns">
                                <button class='btn-icon btn-print' onclick='printPO(90)'><i class='ph-bold ph-printer'></i></button>
                                <button class='btn-icon btn-delete' onclick='deletePO(90)'><i class='ph-bold ph-trash'></i></button>
                            </td>
                        </tr>
                        <tr id='row_88'>
                            <td style="color: var(--primary-color); font-weight: 700;">PP00088</td>
                            <td>30-11--0001</td>
                            <td>test</td>
                            <td>₹-1,888.00</td>
                            <td>₹0.00</td>
                            <td style="color: #10b981; font-weight: 700;">₹-1,888.00</td>
                            <td class="action-btns">
                                <button class='btn-icon btn-print' onclick='printPO(88)'><i class='ph-bold ph-printer'></i></button>
                                <button class='btn-icon btn-delete' onclick='deletePO(88)'><i class='ph-bold ph-trash'></i></button>
                            </td>
                        </tr>
                        <tr id='row_87'>
                            <td style="color: var(--primary-color); font-weight: 700;">PP00087</td>
                            <td>04-02-2026</td>
                            <td>testing</td>
                            <td>₹40,200.00</td>
                            <td>₹-2.00</td>
                            <td style="color: #ef4444; font-weight: 700;">₹40,202.00</td>
                            <td class="action-btns">
                                <button class='btn-icon btn-print' onclick='printPO(87)'><i class='ph-bold ph-printer'></i></button>
                                <button class='btn-icon btn-delete' onclick='deletePO(87)'><i class='ph-bold ph-trash'></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    /**
     * Language Setup (Modified slightly from Code 1 to not depend on removed sidebar HTML)
     */
    async function changeLang(lang) {
        try {
            localStorage.setItem('rupnidhi_lang', lang);
            const response = await fetch(`lang/${lang}.json`);
            if (!response.ok) throw new Error("Language file not found");
            const translations = await response.json();

            document.querySelectorAll('[data-key]').forEach(el => {
                const key = el.getAttribute('data-key');
                if (translations[key]) {
                    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                        el.setAttribute('placeholder', translations[key]);
                    } else {
                        if (el.children.length === 0) {
                            el.innerText = translations[key];
                        } else {
                            const textNode = Array.from(el.childNodes).find(node => node.nodeType === 3 && node.textContent.trim() !== "");
                            if (textNode) textNode.textContent = translations[key];
                        }
                    }
                }
            });

            if (lang === 'ta') { document.body.classList.add('lang-ta'); } 
            else { document.body.classList.remove('lang-ta'); }

            const btnEn = document.getElementById('btn-en');
            const btnTa = document.getElementById('btn-ta');
            if (btnEn && btnTa) {
                if (lang === 'en') { btnEn.classList.add('active'); btnTa.classList.remove('active'); } 
                else { btnTa.classList.add('active'); btnEn.classList.remove('active'); }
            }
        } catch (error) {
            console.error("Language Error:", error);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const savedLang = localStorage.getItem('rupnidhi_lang') || 'en';
        setTimeout(() => changeLang(savedLang), 150);
        addNewRow();
    });

    /**
     * Form Logic (From Code 1, utilizing Code 2 UI Structure)
     */
    function addNewRow() {
        const count = $('#items-container tr').length + 1;
        const row = `<tr>
            <td style="color: #71717a; font-weight: 700; text-align: center; padding-top: 15px;">${count}</td>
            <td class="stacked-input-container">
                <input type="text" name="materials[]" data-key="placeholder-item-name" placeholder="Item Name" required>
                <input type="text" name="item_code[]" data-key="ph-item-code" placeholder="Item Code">
            </td>
            <td><input type="text" name="hsn_code[]" data-key="th-hsn" placeholder="HSN" style="width:100%;"></td>
            <td>
                <div class="qty-unit-group">
                    <input type="number" name="qtys[]" class="qty" value="0" step="0.01">
                    <select name="unit[]">
                        <option data-key="unit-kg">Kg</option>
                        <option data-key="unit-nos">Nos</option>
                        <option data-key="unit-pcs">Pcs</option>
                    </select>
                </div>
            </td>
            <td><input type="number" name="prices[]" class="price" value="0" step="0.01" style="width:100%;"></td>
            <td><input type="number" name="discount[]" class="discount" value="0" style="width:100%;"></td>
            <td>
                <select name="gst_percent[]" class="gst" style="width:100%;">
                    <option value="0">0%</option>
                    <option value="5">5%</option>
                    <option value="12">12%</option>
                    <option value="18" selected>18%</option>
                </select>
            </td>
            <td><input type="text" name="totals[]" class="line_total" readonly value="0.00" style="width:100%; font-weight:600;"></td>
            <td style="text-align: center; vertical-align: middle;">
                <button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="ph-bold ph-trash"></i></button>
            </td>
        </tr>`;
        $('#items-container').append(row);

        const currentLang = localStorage.getItem('rupnidhi_lang') || 'en';
        if(typeof changeLang === 'function') { changeLang(currentLang); }
    }

    function removeRow(btn) { 
        if($('#items-container tr').length > 1) { 
            $(btn).closest('tr').remove(); 
            calculateTotals(); 
        } 
    }

    $(document).on('input', '.qty, .price, .discount, .gst, #transport, #paidAmount', calculateTotals);

    function calculateTotals() {
        let subTotal = 0, totalTax = 0;
        $('#items-container tr').each(function() {
            let qty = parseFloat($(this).find('.qty').val()) || 0;
            let price = parseFloat($(this).find('.price').val()) || 0;
            let disc = parseFloat($(this).find('.discount').val()) || 0;
            let gst = parseFloat($(this).find('.gst').val()) || 0;
            
            let basePrice = qty * price;
            let afterDisc = basePrice - (basePrice * (disc / 100));
            let taxValue = afterDisc * (gst / 100);
            let lineTotal = afterDisc + taxValue;
            
            $(this).find('.line_total').val(lineTotal.toFixed(2));
            subTotal += afterDisc; 
            totalTax += taxValue;
        });
        
        let freight = parseFloat($('#transport').val()) || 0;
        let grandTotal = subTotal + totalTax + freight;
        let paid = parseFloat($('#paidAmount').val()) || 0;
        
        $('#netTotal').val(subTotal.toFixed(2));
        $('#taxAmount').val(totalTax.toFixed(2));
        $('#grandTotal').val(grandTotal.toFixed(2));
        $('#balanceAmount').val((grandTotal - paid).toFixed(2));
    }

    function savePO() {
        if(!$('#shopName').val()) { alert("Please enter Vendor Name"); return; }
        const btn = $('#submitBtn');
        btn.prop('disabled', true).html('Saving... <i class="ph-bold ph-spinner"></i>');
        
        $.ajax({
            url: window.location.href, type: 'POST', data: $('#poForm').serialize(),
            success: function(response) { alert("Purchase Order Saved Successfully!"); location.reload(); },
            error: function() { alert("Error saving data."); btn.prop('disabled', false).html('<span data-key="btn-generate">Generate PO</span> <i class="ph-bold ph-arrow-right"></i>'); }
        });
    }

    function deletePO(id) {
        if(confirm("Are you sure you want to delete this Purchase Order?")) {
            $.post(window.location.href, {ajax_action: 'delete_po', id: id}, function(res) {
                if(res.trim() === 'success') { $('#row_' + id).fadeOut(); } else { alert("Error deleting record."); }
            });
        }
    }

    function printPO(id) {
        const existingFrame = document.getElementById('printFrame');
        if (existingFrame) { document.body.removeChild(existingFrame); }
        const iframe = document.createElement('iframe');
        iframe.id = 'printFrame';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.src = 'poprint.php?id=' + id;
        document.body.appendChild(iframe);
    }
</script>
</body>
</html>