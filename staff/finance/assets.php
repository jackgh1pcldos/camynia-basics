<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Fixed Assets';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-laptop"></i> Fixed Assets</h1>
        <button type="button" class="btn btn-primary" onclick="openAssetModal('create')">
            <i class="bi bi-plus-circle"></i> New Asset
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus" onchange="loadAssets()">
                        <option value="">All Status</option>
                        <option value="active" selected>Active</option>
                        <option value="disposed">Disposed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterCategory" onchange="loadAssets()">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" id="filterSearch" placeholder="Search asset tag, description..." onkeyup="loadAssets()">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Acquisition Cost</th>
                        <th>Book Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assetsTableBody">
                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assetModalTitle">New Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assetForm">
                    <input type="hidden" id="assetId">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Asset Tag <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="assetTag" required>
                        </div>
                        <div class="col-md-8">
                            <label>Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="description" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Category ID</label>
                            <input type="number" class="form-control" id="categoryId">
                        </div>
                        <div class="col-md-4">
                            <label>Acquisition Date</label>
                            <input type="date" class="form-control" id="acquisitionDate" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label>Acquisition Cost <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="acquisitionCost" step="0.01" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Depreciation Method</label>
                            <select class="form-select" id="depreciationMethod">
                                <option value="straight_line">Straight Line</option>
                                <option value="declining_balance">Declining Balance</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Useful Life (years)</label>
                            <input type="number" class="form-control" id="usefulLife" value="5" min="1">
                        </div>
                        <div class="col-md-4">
                            <label>Salvage Value</label>
                            <input type="number" class="form-control" id="salvageValue" step="0.01" value="0">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAsset()">Save Asset</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => loadAssets());

function loadAssets() {
    const params = new URLSearchParams();
    const status = document.getElementById('filterStatus').value;
    const category = document.getElementById('filterCategory').value;
    const search = document.getElementById('filterSearch').value;

    if (status) params.append('status', status);
    if (category) params.append('category_id', category);
    if (search) params.append('search', search);

    fetch(`/staff/api/finance/assets.php?action=list&${params}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) renderAssets(d.data);
            else alert('Error: ' + d.message);
        });
}

function renderAssets(assets) {
    const tbody = document.getElementById('assetsTableBody');
    if (assets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No assets found</td></tr>';
        return;
    }

    tbody.innerHTML = assets.map(asset => `
        <tr>
            <td><strong>${asset.asset_tag}</strong></td>
            <td>${asset.description}</td>
            <td>${asset.category_name || 'N/A'}</td>
            <td>$${parseFloat(asset.acquisition_cost || 0).toFixed(2)}</td>
            <td>$${parseFloat(asset.book_value || 0).toFixed(2)}</td>
            <td><span class="badge bg-${asset.status === 'active' ? 'success' : 'secondary'}">${asset.status}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewAsset(${asset.id})">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function openAssetModal(mode) {
    const modal = new bootstrap.Modal(document.getElementById('assetModal'));
    document.getElementById('assetForm').reset();
    document.getElementById('assetModalTitle').textContent = mode === 'create' ? 'New Asset' : 'Edit Asset';
    modal.show();
}

function saveAsset() {
    const form = document.getElementById('assetForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        asset_tag: document.getElementById('assetTag').value,
        description: document.getElementById('description').value,
        category_id: document.getElementById('categoryId').value || null,
        acquisition_date: document.getElementById('acquisitionDate').value,
        acquisition_cost: parseFloat(document.getElementById('acquisitionCost').value),
        depreciation_method: document.getElementById('depreciationMethod').value,
        useful_life_years: parseInt(document.getElementById('usefulLife').value),
        salvage_value: parseFloat(document.getElementById('salvageValue').value)
    };

    fetch('/staff/api/finance/assets.php?action=create', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Success: ' + d.message);
            bootstrap.Modal.getInstance(document.getElementById('assetModal')).hide();
            loadAssets();
        } else {
            alert('Error: ' + d.message);
        }
    });
}

function viewAsset(id) {
    alert('View asset #' + id);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
