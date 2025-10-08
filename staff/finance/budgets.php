<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Budget Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-calculator"></i> Budget Management</h1>
        <button type="button" class="btn btn-primary" onclick="openBudgetModal('create')">
            <i class="bi bi-plus-circle"></i> New Budget
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" id="filterYear" onchange="loadBudgets()">
                        <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                        <option value="<?= date('Y') - 1 ?>"><?= date('Y') - 1 ?></option>
                        <option value="<?= date('Y') + 1 ?>"><?= date('Y') + 1 ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="filterPeriod" onchange="loadBudgets()">
                        <option value="">All Periods</option>
                        <option value="Q1">Q1</option>
                        <option value="Q2">Q2</option>
                        <option value="Q3">Q3</option>
                        <option value="Q4">Q4</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="filterSearch" placeholder="Search cost center..." onkeyup="loadBudgets()">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Cost Center</th>
                        <th>Year</th>
                        <th>Period</th>
                        <th>Budget Amount</th>
                        <th>Spent Amount</th>
                        <th>Committed</th>
                        <th>Available</th>
                        <th>% Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="budgetsTableBody">
                    <tr><td colspan="9" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="budgetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="budgetForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Cost Center ID <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="costCenterId" required>
                        </div>
                        <div class="col-md-3">
                            <label>Fiscal Year <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="fiscalYear" value="<?= date('Y') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label>Period <span class="text-danger">*</span></label>
                            <select class="form-select" id="period" required>
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                                <option value="ANNUAL">Annual</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Budget Amount <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="budgetAmount" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label>Budget Type</label>
                            <select class="form-select" id="budgetType">
                                <option value="hard">Hard (Cannot exceed)</option>
                                <option value="soft">Soft (Warning only)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label>Notes</label>
                            <textarea class="form-control" id="notes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveBudget()">Save Budget</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('filterYear').value = new Date().getFullYear();
    loadBudgets();
});

function loadBudgets() {
    const params = new URLSearchParams();
    const year = document.getElementById('filterYear').value;
    const period = document.getElementById('filterPeriod').value;
    const search = document.getElementById('filterSearch').value;

    if (year) params.append('fiscal_year', year);
    if (period) params.append('period', period);
    if (search) params.append('search', search);

    fetch(`/staff/api/finance/budgets.php?action=list&${params}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) renderBudgets(d.data);
            else alert('Error: ' + d.message);
        });
}

function renderBudgets(budgets) {
    const tbody = document.getElementById('budgetsTableBody');
    if (budgets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No budgets found</td></tr>';
        return;
    }

    tbody.innerHTML = budgets.map(b => {
        const available = b.budget_amount - b.spent_amount - b.committed_amount;
        const pctUsed = b.budget_amount > 0 ? ((b.spent_amount + b.committed_amount) / b.budget_amount * 100).toFixed(1) : 0;
        const barClass = pctUsed > 90 ? 'bg-danger' : (pctUsed > 75 ? 'bg-warning' : 'bg-success');

        return `
            <tr>
                <td>${b.cost_center_name || 'CC #' + b.cost_center_id}</td>
                <td>${b.fiscal_year}</td>
                <td><span class="badge bg-secondary">${b.period}</span></td>
                <td>$${parseFloat(b.budget_amount || 0).toFixed(2)}</td>
                <td>$${parseFloat(b.spent_amount || 0).toFixed(2)}</td>
                <td>$${parseFloat(b.committed_amount || 0).toFixed(2)}</td>
                <td>$${available.toFixed(2)}</td>
                <td>
                    <div class="progress" style="width: 100px;">
                        <div class="progress-bar ${barClass}" role="progressbar" style="width: ${Math.min(pctUsed, 100)}%">
                            ${pctUsed}%
                        </div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewBudget(${b.id})">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function openBudgetModal(mode) {
    const modal = new bootstrap.Modal(document.getElementById('budgetModal'));
    document.getElementById('budgetForm').reset();
    document.getElementById('fiscalYear').value = new Date().getFullYear();
    modal.show();
}

function saveBudget() {
    const form = document.getElementById('budgetForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        cost_center_id: document.getElementById('costCenterId').value,
        fiscal_year: parseInt(document.getElementById('fiscalYear').value),
        period: document.getElementById('period').value,
        budget_amount: parseFloat(document.getElementById('budgetAmount').value),
        budget_type: document.getElementById('budgetType').value,
        notes: document.getElementById('notes').value || null
    };

    fetch('/staff/api/finance/budgets.php?action=create', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Success: ' + d.message);
            bootstrap.Modal.getInstance(document.getElementById('budgetModal')).hide();
            loadBudgets();
        } else {
            alert('Error: ' + d.message);
        }
    });
}

function viewBudget(id) {
    alert('View budget #' + id);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
