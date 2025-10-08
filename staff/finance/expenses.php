<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Expense Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-receipt"></i> Expense Reports</h1>
        <button type="button" class="btn btn-primary" onclick="openExpenseModal('create')">
            <i class="bi bi-plus-circle"></i> New Expense Report
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus" onchange="loadExpenses()">
                        <option value="">All Status</option>
                        <option value="draft" selected>Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="reimbursed">Reimbursed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" id="filterDateFrom" onchange="loadExpenses()">
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" id="filterSearch" placeholder="Search..." onkeyup="loadExpenses()">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Report #</th>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="expensesTableBody">
                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Expense Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="expenseForm">
                    <input type="hidden" id="expenseId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Report Date</label>
                            <input type="date" class="form-control" id="reportDate" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label>Purpose</label>
                            <input type="text" class="form-control" id="purpose" placeholder="e.g., Client meeting, Conference">
                        </div>
                    </div>
                    <div id="expenseLines"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addExpenseLine()">
                        <i class="bi bi-plus-circle"></i> Add Expense
                    </button>
                    <div class="row">
                        <div class="col-md-8"></div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <strong>Total:</strong>
                                        <strong id="total">$0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" onclick="saveExpense('draft')">Save Draft</button>
                <button type="button" class="btn btn-primary" onclick="saveExpense('submit')">Submit for Approval</button>
            </div>
        </div>
    </div>
</div>

<script>
let lineCount = 0;

document.addEventListener('DOMContentLoaded', () => loadExpenses());

function loadExpenses() {
    const params = new URLSearchParams();
    const status = document.getElementById('filterStatus').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const search = document.getElementById('filterSearch').value;

    if (status) params.append('status', status);
    if (dateFrom) params.append('date_from', dateFrom);
    if (search) params.append('search', search);

    fetch(`/staff/api/finance/expenses.php?action=list&${params}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) renderExpenses(d.data);
            else alert('Error: ' + d.message);
        });
}

function renderExpenses(expenses) {
    const tbody = document.getElementById('expensesTableBody');
    if (expenses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No expense reports found</td></tr>';
        return;
    }

    tbody.innerHTML = expenses.map(e => `
        <tr>
            <td><strong>${e.report_number || 'DRAFT'}</strong></td>
            <td>${new Date(e.report_date).toLocaleDateString()}</td>
            <td>${e.employee_name || 'N/A'}</td>
            <td><strong>$${parseFloat(e.total_amount || 0).toFixed(2)}</strong></td>
            <td><span class="badge bg-secondary">${e.status}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewExpense(${e.id})">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function openExpenseModal(mode) {
    const modal = new bootstrap.Modal(document.getElementById('expenseModal'));
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseLines').innerHTML = '';
    lineCount = 0;
    addExpenseLine();
    modal.show();
}

function addExpenseLine() {
    lineCount++;
    const container = document.getElementById('expenseLines');
    const div = document.createElement('div');
    div.className = 'card mb-2 expense-line';
    div.id = `line-${lineCount}`;
    div.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label>Date</label>
                    <input type="date" class="form-control form-control-sm expense-date" value="${new Date().toISOString().split('T')[0]}">
                </div>
                <div class="col-md-3">
                    <label>Category</label>
                    <select class="form-control form-control-sm expense-category">
                        <option value="meals">Meals</option>
                        <option value="lodging">Lodging</option>
                        <option value="airfare">Airfare</option>
                        <option value="mileage">Mileage</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Description</label>
                    <input type="text" class="form-control form-control-sm expense-description" placeholder="What was this for?">
                </div>
                <div class="col-md-2">
                    <label>Amount</label>
                    <input type="number" class="form-control form-control-sm expense-amount" step="0.01" min="0" onchange="calculateTotal()">
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeExpenseLine('line-${lineCount}')">Remove</button>
        </div>
    `;
    container.appendChild(div);
    calculateTotal();
}

function removeExpenseLine(id) {
    document.getElementById(id).remove();
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.expense-amount').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

function saveExpense(mode) {
    const lines = [];
    document.querySelectorAll('.expense-line').forEach(div => {
        const description = div.querySelector('.expense-description').value;
        const amount = parseFloat(div.querySelector('.expense-amount').value) || 0;
        if (description && amount > 0) {
            lines.push({
                expense_date: div.querySelector('.expense-date').value,
                expense_category: div.querySelector('.expense-category').value,
                description: description,
                amount: amount
            });
        }
    });

    if (lines.length === 0) {
        alert('Please add at least one expense');
        return;
    }

    const data = {
        report_date: document.getElementById('reportDate').value,
        purpose: document.getElementById('purpose').value,
        line_items: lines,
        submit: (mode === 'submit')
    };

    fetch('/staff/api/finance/expenses.php?action=create', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Success: ' + d.message);
            bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
            loadExpenses();
        } else {
            alert('Error: ' + d.message);
        }
    });
}

function viewExpense(id) {
    alert('View expense #' + id);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
