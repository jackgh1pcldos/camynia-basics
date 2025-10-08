<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$pageTitle = 'Finance Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-graph-up"></i> Finance Reports</h1>

    <div class="row">
        <!-- Spend Analysis -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Spend Analysis</h5>
                </div>
                <div class="card-body">
                    <p>Analyze spending by vendor, category, department, or cost center</p>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Start Date</label>
                            <input type="date" class="form-control" id="spendStartDate">
                        </div>
                        <div class="col-md-6">
                            <label>End Date</label>
                            <input type="date" class="form-control" id="spendEndDate" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="runSpendAnalysis()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>

        <!-- AP Aging -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-check"></i> AP Aging</h5>
                </div>
                <div class="card-body">
                    <p>Accounts Payable aging report - invoices due by period</p>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Aging Bucket</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="apAgingBody">
                            <tr><td colspan="2" class="text-center">Click to load report</td></tr>
                        </tbody>
                    </table>
                    <button class="btn btn-success" onclick="runAPAging()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Open POs -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-cart-check"></i> Open Purchase Orders</h5>
                </div>
                <div class="card-body">
                    <p>POs that are sent but not yet fully received</p>
                    <div id="openPOsTable"></div>
                    <button class="btn btn-warning" onclick="runOpenPOs()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Budget Variance -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Budget vs. Actual</h5>
                </div>
                <div class="card-body">
                    <p>Budget variance analysis by cost center</p>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Fiscal Year</label>
                            <input type="number" class="form-control" id="budgetYear" value="<?= date('Y') ?>">
                        </div>
                        <div class="col-md-6">
                            <label>Period</label>
                            <select class="form-select" id="budgetPeriod">
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                                <option value="ANNUAL">Annual</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-info" onclick="runBudgetVariance()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>

        <!-- GR/IR Report -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> GR/IR Report</h5>
                </div>
                <div class="card-body">
                    <p>Goods Received Not Invoiced - liability tracking</p>
                    <div id="grirTable"></div>
                    <button class="btn btn-secondary" onclick="runGRIR()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Expense Report Summary -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> Expense Summary</h5>
                </div>
                <div class="card-body">
                    <p>Employee expense reports by category and department</p>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Start Date</label>
                            <input type="date" class="form-control" id="expenseStartDate">
                        </div>
                        <div class="col-md-6">
                            <label>End Date</label>
                            <input type="date" class="form-control" id="expenseEndDate" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <button class="btn btn-danger" onclick="runExpenseSummary()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Vendor Performance -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Vendor Performance</h5>
                </div>
                <div class="card-body">
                    <p>Vendor scorecards - on-time delivery, quality, spend</p>
                    <div id="vendorPerformanceTable"></div>
                    <button class="btn btn-dark" onclick="runVendorPerformance()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Asset Register -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header" style="background-color: #6610f2; color: white;">
                    <h5 class="mb-0"><i class="bi bi-laptop"></i> Asset Register</h5>
                </div>
                <div class="card-body">
                    <p>Complete fixed asset listing with depreciation schedules</p>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Status</label>
                            <select class="form-select" id="assetStatus">
                                <option value="active">Active</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Category</label>
                            <input type="number" class="form-control" id="assetCategory" placeholder="Category ID (optional)">
                        </div>
                    </div>
                    <button class="btn" style="background-color: #6610f2; color: white;" onclick="runAssetRegister()">
                        <i class="bi bi-play-circle"></i> Run Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function runSpendAnalysis() {
    const startDate = document.getElementById('spendStartDate').value;
    const endDate = document.getElementById('spendEndDate').value;

    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }

    alert('Spend Analysis Report\nPeriod: ' + startDate + ' to ' + endDate + '\n\nThis would show spend by vendor, category, department, etc.\n\n(Full implementation would fetch data from API and display charts/tables)');
}

function runAPAging() {
    // Simulated AP Aging data
    const aging = [
        { bucket: 'Current (0-30 days)', amount: 45230.50 },
        { bucket: '31-60 days', amount: 12450.00 },
        { bucket: '61-90 days', amount: 3200.00 },
        { bucket: '90+ days', amount: 850.00 }
    ];

    const tbody = document.getElementById('apAgingBody');
    tbody.innerHTML = aging.map(a => `
        <tr>
            <td>${a.bucket}</td>
            <td class="text-end"><strong>$${a.amount.toFixed(2)}</strong></td>
        </tr>
    `).join('') + `
        <tr class="table-primary">
            <th>Total</th>
            <th class="text-end">$${aging.reduce((sum, a) => sum + a.amount, 0).toFixed(2)}</th>
        </tr>
    `;
}

function runOpenPOs() {
    alert('Open Purchase Orders Report\n\nThis would show:\n- POs sent to vendors\n- Not yet fully received\n- Expected delivery dates\n- Outstanding amounts\n\n(Full implementation would query the database)');
}

function runBudgetVariance() {
    const year = document.getElementById('budgetYear').value;
    const period = document.getElementById('budgetPeriod').value;

    alert('Budget vs. Actual Report\nFiscal Year: ' + year + '\nPeriod: ' + period + '\n\nThis would show:\n- Budget amounts by cost center\n- Actual spending\n- Variance (favorable/unfavorable)\n- Percentage used\n\n(Full implementation would query budgets and actual spend)');
}

function runGRIR() {
    alert('GR/IR (Goods Received Not Invoiced) Report\n\nThis would show:\n- Items received but not yet invoiced\n- Accrued liability amounts\n- Aging of unbilled receipts\n- Expected invoice dates\n\n(Full implementation would query goods receipts vs. invoices)');
}

function runExpenseSummary() {
    const startDate = document.getElementById('expenseStartDate').value;
    const endDate = document.getElementById('expenseEndDate').value;

    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }

    alert('Expense Report Summary\nPeriod: ' + startDate + ' to ' + endDate + '\n\nThis would show:\n- Total expenses by category (meals, lodging, airfare, etc.)\n- Top spenders\n- Policy violations\n- Pending approvals\n\n(Full implementation would query expense_reports table)');
}

function runVendorPerformance() {
    alert('Vendor Performance Scorecard\n\nThis would show:\n- On-time delivery rate\n- Quality metrics\n- Total spend\n- Number of POs\n- Average PO value\n- Price variance trends\n\n(Full implementation would aggregate data from POs, receipts, and invoices)');
}

function runAssetRegister() {
    const status = document.getElementById('assetStatus').value;
    const category = document.getElementById('assetCategory').value;

    alert('Fixed Asset Register\nStatus: ' + status + '\nCategory: ' + (category || 'All') + '\n\nThis would show:\n- Asset tag and description\n- Acquisition date and cost\n- Current book value\n- Accumulated depreciation\n- Depreciation schedule\n- Assigned location/custodian\n\n(Full implementation would query fixed_assets table)');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
