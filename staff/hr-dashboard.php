<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'HR Dashboard';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Get comprehensive HR stats
try {
    // Employee stats
    $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$companyId]);
    $activeEmployees = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'on_leave'");
    $stmt->execute([$companyId]);
    $onLeave = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND MONTH(hire_date) = MONTH(CURDATE())");
    $stmt->execute([$companyId]);
    $newHires = $stmt->fetchColumn();

    // Time off stats
    $stmt = $db->prepare("SELECT COUNT(*) FROM time_off_requests WHERE company_id = ? AND status = 'pending'");
    $stmt->execute([$companyId]);
    $pendingTimeOff = $stmt->fetchColumn();

    // Department breakdown
    $stmt = $db->prepare("
        SELECT d.name, COUNT(e.id) as count
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
        WHERE d.company_id = ?
        GROUP BY d.id, d.name
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute([$companyId]);
    $departmentStats = $stmt->fetchAll();

    // Recent activities
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ?
        ORDER BY e.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId]);
    $recentActivity = $stmt->fetchAll();

    // Pending time off requests
    $stmt = $db->prepare("
        SELECT t.*, u.first_name, u.last_name, u.avatar
        FROM time_off_requests t
        JOIN users u ON t.user_id = u.id
        WHERE t.company_id = ? AND t.status = 'pending'
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId]);
    $pendingRequests = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('HR Dashboard error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>HR Dashboard</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item active">HR Dashboard</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="/staff/hr-employees.php?action=add" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Add Employee
            </a>
        </div>
    </div>
</div>

<!-- Stats Overview -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-value"><?= $activeEmployees ?? 0 ?></div>
            <div class="stat-label">Active Employees</div>
            <div class="mt-2">
                <small class="text-success"><i class="bi bi-arrow-up"></i> <?= $newHires ?? 0 ?> new this month</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                <i class="bi bi-person-x"></i>
            </div>
            <div class="stat-value"><?= $onLeave ?? 0 ?></div>
            <div class="stat-label">On Leave</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?= $pendingTimeOff ?? 0 ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <i class="bi bi-building"></i>
            </div>
            <div class="stat-value"><?= count($departmentStats ?? []) ?></div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Department Breakdown -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Department Breakdown</h5>
            </div>
            <div class="card-body">
                <canvas id="departmentChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <!-- Pending Time Off Requests -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pending Time Off Requests</h5>
                <a href="/staff/hr-timeoff.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingRequests)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle" style="font-size: 48px;"></i>
                        <p class="mt-2">No pending requests</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2">
                                                    <?= getInitials($request['first_name'], $request['last_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= e($request['first_name'] . ' ' . $request['last_name']) ?></div>
                                                    <small class="text-muted"><?= timeAgo($request['created_at']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-info"><?= ucfirst($request['type']) ?></span></td>
                                        <td>
                                            <small><?= formatDate($request['start_date'], 'M j') ?> - <?= formatDate($request['end_date'], 'M j, Y') ?></small>
                                        </td>
                                        <td><?= $request['days_count'] ?> days</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" title="Approve">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                                <button class="btn btn-danger" title="Deny">
                                                    <i class="bi bi-x"></i>
                                                </button>
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
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Recent Employee Activity</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentActivity)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 48px;"></i>
                        <p class="mt-2">No recent activity</p>
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
                                    <th>Hire Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $emp): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2">
                                                    <?= getInitials($emp['first_name'], $emp['last_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                                    <small class="text-muted"><?= e($emp['employee_number'] ?? 'N/A') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= e($emp['department_name'] ?? '-') ?></td>
                                        <td><?= e($emp['job_title'] ?? '-') ?></td>
                                        <td><span class="badge bg-secondary"><?= ucfirst($emp['employment_type'] ?? 'N/A') ?></span></td>
                                        <td><?= formatDate($emp['hire_date']) ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'active' => 'success',
                                                'on_leave' => 'warning',
                                                'terminated' => 'danger'
                                            ];
                                            $color = $statusColors[$emp['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $emp['status'])) ?></span>
                                        </td>
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
    // Department chart
    const ctx = document.getElementById('departmentChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($departmentStats ?? [], 'name')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($departmentStats ?? [], 'count')) ?>,
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(241, 196, 15, 0.8)',
                        'rgba(231, 76, 60, 0.8)'
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
