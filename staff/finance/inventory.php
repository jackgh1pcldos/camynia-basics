<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Inventory Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-box-seam"></i> Inventory Management</h1>
        <button type="button" class="btn btn-primary" onclick="openTransactionModal()">
            <i class="bi bi-plus-circle"></i> New Transaction
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="filterSearch" placeholder="Search SKU or item name..." onkeyup="loadInventory()">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterWarehouse" onchange="loadInventory()">
                        <option value="">All Warehouses</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterLowStock" onchange="loadInventory()">
                        <option value="">All Items</option>
                        <option value="1">Low Stock Only</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item Name</th>
                        <th>Warehouse</th>
                        <th>On Hand</th>
                        <th>Reorder Point</th>
                        <th>Value</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Inventory Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="transactionForm">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Transaction Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="txnType" required>
                                <option value="adjustment">Adjustment</option>
                                <option value="transfer">Transfer</option>
                                <option value="count">Cycle Count</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Item ID <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="itemId" required>
                        </div>
                        <div class="col-md-4">
                            <label>Warehouse ID <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="warehouseId" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label>Reason</label>
                            <input type="text" class="form-control" id="reason" placeholder="Optional">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTransaction()">Save Transaction</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => loadInventory());

function loadInventory() {
    const params = new URLSearchParams();
    const search = document.getElementById('filterSearch').value;
    const warehouse = document.getElementById('filterWarehouse').value;
    const lowStock = document.getElementById('filterLowStock').value;

    if (search) params.append('search', search);
    if (warehouse) params.append('warehouse_id', warehouse);
    if (lowStock) params.append('low_stock', lowStock);

    fetch(`/staff/api/finance/inventory.php?action=list&${params}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) renderInventory(d.data);
            else alert('Error: ' + d.message);
        });
}

function renderInventory(items) {
    const tbody = document.getElementById('inventoryTableBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No inventory found</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(item => {
        const isLowStock = item.reorder_point && item.quantity_on_hand < item.reorder_point;
        return `
            <tr ${isLowStock ? 'class="table-warning"' : ''}>
                <td><strong>${item.item_sku}</strong></td>
                <td>${item.item_name}</td>
                <td>${item.warehouse_name || 'N/A'}</td>
                <td>${item.quantity_on_hand || 0}</td>
                <td>${item.reorder_point || '-'}</td>
                <td>$${parseFloat(item.total_value || 0).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewHistory(${item.id})">
                        <i class="bi bi-clock-history"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function openTransactionModal() {
    const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
    document.getElementById('transactionForm').reset();
    modal.show();
}

function saveTransaction() {
    const form = document.getElementById('transactionForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        transaction_type: document.getElementById('txnType').value,
        catalog_item_id: document.getElementById('itemId').value,
        warehouse_id: document.getElementById('warehouseId').value,
        quantity: parseFloat(document.getElementById('quantity').value),
        reason: document.getElementById('reason').value || null
    };

    fetch('/staff/api/finance/inventory.php?action=transaction', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Success: ' + d.message);
            bootstrap.Modal.getInstance(document.getElementById('transactionModal')).hide();
            loadInventory();
        } else {
            alert('Error: ' + d.message);
        }
    });
}

function viewHistory(id) {
    alert('View history for stock #' + id);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
