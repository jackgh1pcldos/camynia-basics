<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Compensation Planning';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Get employee salary data
try {
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email,
               d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ? AND e.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$companyId]);
    $employees = $stmt->fetchAll();

    // Calculate stats
    $totalSalary = 0;
    $salaries = [];
    foreach ($employees as $emp) {
        if ($emp['salary']) {
            $totalSalary += $emp['salary'];
            $salaries[] = $emp['salary'];
        }
    }
    $avgSalary = count($salaries) > 0 ? $totalSalary / count($salaries) : 0;

} catch (PDOException $e) {
    error_log('Compensation error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Compensation Planning</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Compensation</li>
                </ol>
            </nav>
        </div>
        <div>
            <button class="btn btn-primary">
                <i class="bi bi-calculator"></i> Salary Review Cycle
            </button>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-value"><?= formatCurrency($totalSalary) ?></div>
            <div class="stat-label">Total Annual Payroll</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?= formatCurrency($avgSalary) ?></div>
            <div class="stat-label">Average Salary</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= count($employees) ?></div>
            <div class="stat-label">Active Employees</div>
        </div>
    </div>
</div>

<!-- Compensation Table -->
<div class="card">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Employee Compensation</h5>
            <button class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($employees)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-people" style="font-size: 64px;"></i>
                <h4 class="mt-3">No Employee Data</h4>
                <p>Add employees to view compensation data</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Job Title</th>
                            <th>Employment Type</th>
                            <th>Current Salary</th>
                            <th>Hire Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?= getInitials($emp['first_name'], $emp['last_name']) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                            <small class="text-muted"><?= e($emp['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($emp['department_name'] ?? '-') ?></td>
                                <td><?= e($emp['job_title'] ?? '-') ?></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($emp['employment_type']) ?></span></td>
                                <td>
                                    <?php if ($emp['salary']): ?>
                                        <strong><?= formatCurrency($emp['salary']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($emp['hire_date']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#salaryModal<?= $emp['id'] ?>">
                                        <i class="bi bi-pencil"></i> Adjust
                                    </button>

                                    <!-- Salary Adjustment Modal -->
                                    <div class="modal fade" id="salaryModal<?= $emp['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Salary Adjustment - <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Current Salary</label>
                                                        <input type="text" class="form-control" readonly
                                                               value="<?= $emp['salary'] ? formatCurrency($emp['salary']) : 'Not set' ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">New Salary</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" step="1000"
                                                                   value="<?= $emp['salary'] ?? '' ?>">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Effective Date</label>
                                                        <input type="date" class="form-control" value="<?= date('Y-m-d') ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Reason for Adjustment</label>
                                                        <select class="form-select">
                                                            <option>Merit Increase</option>
                                                            <option>Promotion</option>
                                                            <option>Cost of Living Adjustment</option>
                                                            <option>Market Adjustment</option>
                                                            <option>Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Notes</label>
                                                        <textarea class="form-control" rows="2"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="button" class="btn btn-primary">Save Adjustment</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Salary Distribution Chart -->
<div class="card mt-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Salary Distribution</h5>
    </div>
    <div class="card-body">
        <canvas id="salaryChart" height="80"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Salary distribution chart
    const ctx = document.getElementById('salaryChart');
    if (ctx) {
        const salaries = <?= json_encode($salaries) ?>;

        // Create salary ranges
        const ranges = {
            '0-30k': 0,
            '30k-50k': 0,
            '50k-70k': 0,
            '70k-100k': 0,
            '100k+': 0
        };

        salaries.forEach(salary => {
            if (salary < 30000) ranges['0-30k']++;
            else if (salary < 50000) ranges['30k-50k']++;
            else if (salary < 70000) ranges['50k-70k']++;
            else if (salary < 100000) ranges['70k-100k']++;
            else ranges['100k+']++;
        });

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(ranges),
                datasets: [{
                    label: 'Number of Employees',
                    data: Object.values(ranges),
                    backgroundColor: 'rgba(52, 152, 219, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
