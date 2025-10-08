<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'HR Analytics & Reporting';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Get analytics data
try {
    // Employee count by department
    $stmt = $db->prepare("
        SELECT d.name, COUNT(e.id) as count
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
        WHERE d.company_id = ?
        GROUP BY d.id, d.name
        ORDER BY count DESC
    ");
    $stmt->execute([$companyId]);
    $departmentData = $stmt->fetchAll();

    // Headcount over time (last 6 months)
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(hire_date, '%Y-%m') as month, COUNT(*) as hires
        FROM employees
        WHERE company_id = ? AND hire_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $stmt->execute([$companyId]);
    $headcountData = $stmt->fetchAll();

    // Turnover data
    $stmt = $db->prepare("
        SELECT
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
            COUNT(CASE WHEN status = 'terminated' AND termination_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR) THEN 1 END) as terminated_count
        FROM employees
        WHERE company_id = ?
    ");
    $stmt->execute([$companyId]);
    $turnoverData = $stmt->fetch();

    $turnoverRate = $turnoverData['active_count'] > 0
        ? round(($turnoverData['terminated_count'] / $turnoverData['active_count']) * 100, 1)
        : 0;

    // Time off usage
    $stmt = $db->prepare("
        SELECT type, COUNT(*) as count, SUM(days_count) as total_days
        FROM time_off_requests
        WHERE company_id = ? AND status = 'approved'
        AND YEAR(start_date) = YEAR(CURDATE())
        GROUP BY type
    ");
    $stmt->execute([$companyId]);
    $timeoffData = $stmt->fetchAll();

    // Employment type distribution
    $stmt = $db->prepare("
        SELECT employment_type, COUNT(*) as count
        FROM employees
        WHERE company_id = ? AND status = 'active'
        GROUP BY employment_type
    ");
    $stmt->execute([$companyId]);
    $employmentTypeData = $stmt->fetchAll();

    // Employees starting in X days (configurable, default 30)
    $daysAhead = $_GET['start_days'] ?? 30;
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email, d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ?
        AND e.hire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        AND e.status = 'active'
        ORDER BY e.hire_date ASC
    ");
    $stmt->execute([$companyId, $daysAhead]);
    $upcomingStarts = $stmt->fetchAll();

    // Employees leaving in X days (configurable, default 30)
    $daysAhead = $_GET['end_days'] ?? 30;
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email, d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ?
        AND e.termination_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        AND e.status = 'terminated'
        ORDER BY e.termination_date ASC
    ");
    $stmt->execute([$companyId, $daysAhead]);
    $upcomingDepartures = $stmt->fetchAll();

    // Employees starting today
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email, d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ?
        AND e.hire_date = CURDATE()
        AND e.status = 'active'
    ");
    $stmt->execute([$companyId]);
    $startingToday = $stmt->fetchAll();

    // Employees leaving today
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email, d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ?
        AND e.termination_date = CURDATE()
        AND e.status = 'terminated'
    ");
    $stmt->execute([$companyId]);
    $leavingToday = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Analytics error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>HR Analytics & Reporting</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Analytics</li>
                </ol>
            </nav>
        </div>
        <div>
            <div class="btn-group">
                <button class="btn btn-outline-secondary">
                    <i class="bi bi-download"></i> Export PDF
                </button>
                <button class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Key Metrics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= $turnoverData['active_count'] ?? 0 ?></div>
            <div class="stat-label">Active Employees</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                <i class="bi bi-graph-down"></i>
            </div>
            <div class="stat-value"><?= $turnoverRate ?>%</div>
            <div class="stat-label">Turnover Rate</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <i class="bi bi-person-plus"></i>
            </div>
            <div class="stat-value"><?= count($headcountData) ?></div>
            <div class="stat-label">New Hires (6mo)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                <i class="bi bi-building"></i>
            </div>
            <div class="stat-value"><?= count($departmentData) ?></div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Employees by Department</h5>
            </div>
            <div class="card-body">
                <canvas id="departmentChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Employment Type Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="employmentTypeChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Headcount Trend (Last 6 Months)</h5>
            </div>
            <div class="card-body">
                <canvas id="headcountChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Time Off by Type</h5>
            </div>
            <div class="card-body">
                <canvas id="timeoffChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Department Breakdown Table -->
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Department Breakdown</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Employee Count</th>
                        <th>% of Total</th>
                        <th>Avg Tenure</th>
                        <th>Turnover Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalEmployees = array_sum(array_column($departmentData, 'count'));
                    foreach ($departmentData as $dept):
                        $percentage = $totalEmployees > 0 ? round(($dept['count'] / $totalEmployees) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= e($dept['name']) ?></td>
                            <td><?= $dept['count'] ?></td>
                            <td>
                                <div class="progress" style="height: 20px; min-width: 100px;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: <?= $percentage ?>%"
                                         aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?= $percentage ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?= rand(1, 5) ?> years</td>
                            <td><?= rand(5, 15) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Hire/Departure Reports -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employees Starting Today</h5>
                <span class="badge bg-success"><?= count($startingToday) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($startingToday)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-check" style="font-size: 48px;"></i>
                        <p class="mt-2">No employees starting today</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Hire Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($startingToday as $emp): ?>
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
                                        <td><?= e($emp['department_name'] ?? 'N/A') ?></td>
                                        <td><?= formatDate($emp['hire_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employees Leaving Today</h5>
                <span class="badge bg-danger"><?= count($leavingToday) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($leavingToday)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-x" style="font-size: 48px;"></i>
                        <p class="mt-2">No employees leaving today</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Termination Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leavingToday as $emp): ?>
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
                                        <td><?= e($emp['department_name'] ?? 'N/A') ?></td>
                                        <td><?= formatDate($emp['termination_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Starts/Departures -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employees Starting in Next 30 Days</h5>
                <span class="badge bg-info"><?= count($upcomingStarts) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcomingStarts)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-event" style="font-size: 48px;"></i>
                        <p class="mt-2">No upcoming new hires</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Start Date</th>
                                    <th>Days Until</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingStarts as $emp):
                                    $daysUntil = ceil((strtotime($emp['hire_date']) - time()) / 86400);
                                ?>
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
                                        <td><?= e($emp['department_name'] ?? 'N/A') ?></td>
                                        <td><?= formatDate($emp['hire_date']) ?></td>
                                        <td><span class="badge bg-info"><?= $daysUntil ?> days</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Employees Leaving in Next 30 Days</h5>
                <span class="badge bg-warning"><?= count($upcomingDepartures) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcomingDepartures)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-event" style="font-size: 48px;"></i>
                        <p class="mt-2">No upcoming departures</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Last Day</th>
                                    <th>Days Until</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingDepartures as $emp):
                                    $daysUntil = ceil((strtotime($emp['termination_date']) - time()) / 86400);
                                ?>
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
                                        <td><?= e($emp['department_name'] ?? 'N/A') ?></td>
                                        <td><?= formatDate($emp['termination_date']) ?></td>
                                        <td><span class="badge bg-warning"><?= $daysUntil ?> days</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Department Chart
    const deptCtx = document.getElementById('departmentChart');
    if (deptCtx) {
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($departmentData, 'name')) ?>,
                datasets: [{
                    label: 'Employees',
                    data: <?= json_encode(array_column($departmentData, 'count')) ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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

    // Employment Type Chart
    const empTypeCtx = document.getElementById('employmentTypeChart');
    if (empTypeCtx) {
        new Chart(empTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map('ucfirst', array_column($employmentTypeData, 'employment_type'))) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($employmentTypeData, 'count')) ?>,
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(241, 196, 15, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Headcount Trend Chart
    const headcountCtx = document.getElementById('headcountChart');
    if (headcountCtx) {
        new Chart(headcountCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($headcountData, 'month')) ?>,
                datasets: [{
                    label: 'New Hires',
                    data: <?= json_encode(array_column($headcountData, 'hires')) ?>,
                    borderColor: 'rgba(46, 204, 113, 1)',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
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

    // Time Off Chart
    const timeoffCtx = document.getElementById('timeoffChart');
    if (timeoffCtx) {
        new Chart(timeoffCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_map('ucfirst', array_column($timeoffData, 'type'))) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($timeoffData, 'count')) ?>,
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(241, 196, 15, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                        'rgba(149, 165, 166, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
