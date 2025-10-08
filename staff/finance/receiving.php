<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Goods Receipt (GRPO)';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-box-seam"></i> Goods Receipt (GRPO)
        </h1>
        <button type="button" class="btn btn-primary" onclick="openReceiptModal('create')">
            <i class="bi bi-plus-circle"></i> New Receipt
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadReceipts()">
                        <option value="">All Status</option>
                        <option value="draft" selected>Draft</option>
                        <option value="completed">Completed</option>
                        <option value="qc_hold">QC Hold</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">PO Number</label>
                    <input type="text" class="form-control" id="filterPO" placeholder="PO-2025-00001" onchange="loadReceipts()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterDateFrom" onchange="loadReceipts()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="GRN number..." onkeyup="loadReceipts()">
                </div>
            </div>
        </div>
    </div>

    <!-- Receipts Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>GRN Number</th>
                            <th>Date</th>
                            <th>PO Number</th>
                            <th>Vendor</th>
                            <th>Received By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="receiptsTableBody">
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Loading receipts...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalTitle">New Goods Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="receiptForm">
                    <input type="hidden" id="receiptId" name="id">

                    <!-- Header Info -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Purchase Order <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="poId" name="po_id" required placeholder="PO ID">
                                <button type="button" class="btn btn-outline-secondary" onclick="loadPOForReceipt()">
                                    <i class="bi bi-search"></i> Load PO
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Receipt Date</label>
                            <input type="date" class="form-control" id="receiptDate" name="receipt_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Received By</label>
                            <input type="number" class="form-control" id="receivedBy" name="received_by_user_id" placeholder="User ID">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Warehouse</label>
                            <input type="number" class="form-control" id="warehouseId" name="warehouse_id" placeholder="Warehouse ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delivery Note / Packing Slip</label>
                            <input type="text" class="form-control" id="deliveryNote" name="delivery_note_number" placeholder="Optional">
                        </div>
                    </div>

                    <div id="poInfo" class="alert alert-info" style="display: none;">
                        <strong>PO Details:</strong> <span id="poDetails"></span>
                    </div>

                    <!-- Line Items Section -->
                    <h6 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-list-ul"></i> Items to Receive
                    </h6>

                    <div id="lineItems">
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i> Load a Purchase Order to see items available for receipt.
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Receipt Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any notes about this receipt..."></textarea>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" onclick="saveReceipt('draft')">
                    <i class="bi bi-save"></i> Save as Draft
                </button>
                <button type="button" class="btn btn-primary" onclick="saveReceipt('completed')">
                    <i class="bi bi-check-circle"></i> Complete Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Receipt Modal -->
<div class="modal fade" id="viewReceiptModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Receipt Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receiptDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilters = {
    status: 'draft',
    po_number: '',
    date_from: '',
    search: ''
};

let currentPO = null;

document.addEventListener('DOMContentLoaded', function() {
    loadReceipts();
});

function loadReceipts() {
    currentFilters.status = document.getElementById('filterStatus').value;
    currentFilters.po_number = document.getElementById('filterPO').value;
    currentFilters.date_from = document.getElementById('filterDateFrom').value;
    currentFilters.search = document.getElementById('filterSearch').value;

    const params = new URLSearchParams();
    if (currentFilters.status) params.append('status', currentFilters.status);
    if (currentFilters.po_number) params.append('po_number', currentFilters.po_number);
    if (currentFilters.date_from) params.append('date_from', currentFilters.date_from);
    if (currentFilters.search) params.append('search', currentFilters.search);

    fetch(`/staff/api/finance/goods-receipts.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderReceipts(data.data);
            } else {
                showError('Failed to load receipts: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading receipts:', error);
            showError('Failed to load receipts');
        });
}

function renderReceipts(receipts) {
    const tbody = document.getElementById('receiptsTableBody');

    if (receipts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <i class="bi bi-inbox"></i> No receipts found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = receipts.map(receipt => {
        const statusColors = {
            'draft': 'secondary',
            'completed': 'success',
            'qc_hold': 'warning',
            'cancelled': 'danger'
        };

        return `
            <tr>
                <td><strong>${escapeHtml(receipt.grn_number || 'DRAFT')}</strong></td>
                <td>${new Date(receipt.receipt_date).toLocaleDateString()}</td>
                <td>${escapeHtml(receipt.po_number || 'N/A')}</td>
                <td>${escapeHtml(receipt.vendor_name || 'N/A')}</td>
                <td>${escapeHtml(receipt.receiver_name || 'N/A')}</td>
                <td>
                    <span class="badge bg-${statusColors[receipt.status]}">
                        ${formatStatus(receipt.status)}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewReceipt(${receipt.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function formatStatus(status) {
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function openReceiptModal(mode, receiptId = null) {
    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    const form = document.getElementById('receiptForm');
    form.reset();

    document.getElementById('receiptModalTitle').textContent = mode === 'create' ? 'New Goods Receipt' : 'Edit Receipt';
    document.getElementById('lineItems').innerHTML = `
        <div class="alert alert-warning">
            <i class="bi bi-info-circle"></i> Load a Purchase Order to see items available for receipt.
        </div>
    `;
    document.getElementById('poInfo').style.display = 'none';
    currentPO = null;

    modal.show();
}

function loadPOForReceipt() {
    const poId = document.getElementById('poId').value;

    if (!poId) {
        showError('Please enter a PO ID');
        return;
    }

    fetch(`/staff/api/finance/purchase-orders.php?action=view&id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPO = data.data;
                displayPOForReceipt(currentPO);
            } else {
                showError('Failed to load PO: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading PO:', error);
            showError('Failed to load PO');
        });
}

function displayPOForReceipt(po) {
    document.getElementById('poInfo').style.display = 'block';
    document.getElementById('poDetails').textContent = `${po.po_number} - ${po.vendor_name} - Total: $${parseFloat(po.total_amount).toFixed(2)}`;

    const container = document.getElementById('lineItems');
    container.innerHTML = '';

    po.line_items.forEach((item, index) => {
        const qtyRemaining = item.quantity - (item.received_quantity || 0);

        if (qtyRemaining <= 0) return; // Skip fully received items

        const itemDiv = document.createElement('div');
        itemDiv.className = 'card mb-2 receipt-line';
        itemDiv.id = `receipt-line-${index}`;

        itemDiv.innerHTML = `
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5">
                        <strong>${escapeHtml(item.item_description)}</strong><br>
                        <small class="text-muted">Ordered: ${item.quantity} | Received: ${item.received_quantity || 0} | Remaining: ${qtyRemaining}</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Qty to Receive</label>
                        <input type="number" class="form-control form-control-sm receive-qty" value="${qtyRemaining}" min="0" max="${qtyRemaining}" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bin Location</label>
                        <input type="text" class="form-control form-control-sm bin-location" placeholder="Optional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Serial/Batch #</label>
                        <input type="text" class="form-control form-control-sm serial-batch" placeholder="Optional">
                    </div>
                </div>
                <input type="hidden" class="po-line-id" value="${item.id}">
                <input type="hidden" class="item-description" value="${escapeHtml(item.item_description)}">
                <input type="hidden" class="unit-price" value="${item.unit_price}">
            </div>
        `;

        container.appendChild(itemDiv);
    });

    if (container.children.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-check-circle"></i> All items on this PO have been fully received.
            </div>
        `;
    }
}

function saveReceipt(status) {
    const form = document.getElementById('receiptForm');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (!currentPO) {
        showError('Please load a Purchase Order first');
        return;
    }

    const lineItems = [];
    document.querySelectorAll('.receipt-line').forEach(lineDiv => {
        const qty = parseFloat(lineDiv.querySelector('.receive-qty').value) || 0;

        if (qty > 0) {
            lineItems.push({
                po_line_id: lineDiv.querySelector('.po-line-id').value,
                item_description: lineDiv.querySelector('.item-description').value,
                received_quantity: qty,
                unit_price: parseFloat(lineDiv.querySelector('.unit-price').value),
                bin_location: lineDiv.querySelector('.bin-location').value || null,
                serial_batch_number: lineDiv.querySelector('.serial-batch').value || null
            });
        }
    });

    if (lineItems.length === 0) {
        showError('Please enter at least one quantity to receive');
        return;
    }

    const receiptData = {
        po_id: document.getElementById('poId').value,
        receipt_date: document.getElementById('receiptDate').value,
        received_by_user_id: document.getElementById('receivedBy').value || null,
        warehouse_id: document.getElementById('warehouseId').value || null,
        delivery_note_number: document.getElementById('deliveryNote').value || null,
        notes: document.getElementById('notes').value || null,
        line_items: lineItems,
        status: status
    };

    fetch(`/staff/api/finance/goods-receipts.php?action=create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            bootstrap.Modal.getInstance(document.getElementById('receiptModal')).hide();
            loadReceipts();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error saving receipt:', error);
        showError('Failed to save receipt');
    });
}

function viewReceipt(receiptId) {
    fetch(`/staff/api/finance/goods-receipts.php?action=view&id=${receiptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderReceiptDetails(data.data);
                const modal = new bootstrap.Modal(document.getElementById('viewReceiptModal'));
                modal.show();
            } else {
                showError('Failed to load receipt: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error loading receipt:', error);
            showError('Failed to load receipt');
        });
}

function renderReceiptDetails(receipt) {
    const container = document.getElementById('receiptDetails');

    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>${escapeHtml(receipt.grn_number || 'DRAFT')}</h5>
                <p class="text-muted">PO: ${escapeHtml(receipt.po_number || 'N/A')}</p>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge bg-${getStatusColor(receipt.status)} fs-6">${formatStatus(receipt.status)}</span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <strong>Receipt Date:</strong> ${new Date(receipt.receipt_date).toLocaleDateString()}<br>
                <strong>Received By:</strong> ${escapeHtml(receipt.receiver_name || 'N/A')}<br>
                <strong>Warehouse:</strong> ${receipt.warehouse_id || 'N/A'}
            </div>
            <div class="col-md-6">
                <strong>Vendor:</strong> ${escapeHtml(receipt.vendor_name || 'N/A')}<br>
                <strong>Delivery Note:</strong> ${escapeHtml(receipt.delivery_note_number || 'N/A')}<br>
            </div>
        </div>

        ${receipt.notes ? `
            <div class="alert alert-info">
                <strong>Notes:</strong><br>
                ${escapeHtml(receipt.notes)}
            </div>
        ` : ''}

        <h6 class="border-bottom pb-2">Received Items</h6>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty Received</th>
                    <th>Unit Price</th>
                    <th>Bin Location</th>
                    <th>Serial/Batch</th>
                    <th class="text-end">Value</th>
                </tr>
            </thead>
            <tbody>
                ${receipt.line_items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.item_description)}</td>
                        <td>${item.received_quantity}</td>
                        <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>${escapeHtml(item.bin_location || '-')}</td>
                        <td>${escapeHtml(item.serial_batch_number || '-')}</td>
                        <td class="text-end">$${(item.received_quantity * item.unit_price).toFixed(2)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

function getStatusColor(status) {
    const colors = {
        'draft': 'secondary',
        'completed': 'success',
        'qc_hold': 'warning',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showSuccess(message) {
    alert('Success: ' + message);
}

function showError(message) {
    alert('Error: ' + message);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
