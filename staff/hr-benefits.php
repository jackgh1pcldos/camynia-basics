<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Benefits Management';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Sample benefits data
$benefitPlans = [
    [
        'id' => 1,
        'name' => 'Health Insurance - Premium',
        'type' => 'Medical',
        'provider' => 'Blue Cross Blue Shield',
        'cost_monthly' => 450.00,
        'enrolled' => 15,
        'status' => 'active'
    ],
    [
        'id' => 2,
        'name' => 'Health Insurance - Basic',
        'type' => 'Medical',
        'provider' => 'Blue Cross Blue Shield',
        'cost_monthly' => 250.00,
        'enrolled' => 8,
        'status' => 'active'
    ],
    [
        'id' => 3,
        'name' => 'Dental Insurance',
        'type' => 'Dental',
        'provider' => 'Delta Dental',
        'cost_monthly' => 45.00,
        'enrolled' => 20,
        'status' => 'active'
    ],
    [
        'id' => 4,
        'name' => 'Vision Insurance',
        'type' => 'Vision',
        'provider' => 'VSP',
        'cost_monthly' => 15.00,
        'enrolled' => 18,
        'status' => 'active'
    ],
    [
        'id' => 5,
        'name' => '401(k) Retirement Plan',
        'type' => 'Retirement',
        'provider' => 'Fidelity',
        'cost_monthly' => 0.00,
        'enrolled' => 22,
        'status' => 'active'
    ]
];
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Benefits Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Benefits</li>
                </ol>
            </nav>
        </div>
        <div>
            <button class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Benefit Plan
            </button>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <i class="bi bi-heart-pulse"></i>
            </div>
            <div class="stat-value"><?= count($benefitPlans) ?></div>
            <div class="stat-label">Active Plans</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= array_sum(array_column($benefitPlans, 'enrolled')) ?></div>
            <div class="stat-label">Total Enrollments</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-value"><?= formatCurrency(array_sum(array_column($benefitPlans, 'cost_monthly'))) ?></div>
            <div class="stat-label">Monthly Cost (per employee)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-value">85%</div>
            <div class="stat-label">Participation Rate</div>
        </div>
    </div>
</div>

<!-- Benefit Plans -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Benefit Plans</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Type</th>
                        <th>Provider</th>
                        <th>Monthly Cost</th>
                        <th>Enrolled</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($benefitPlans as $plan): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($plan['name']) ?></div>
                            </td>
                            <td>
                                <?php
                                $typeIcons = [
                                    'Medical' => 'heart-pulse',
                                    'Dental' => 'tooth',
                                    'Vision' => 'eye',
                                    'Retirement' => 'piggy-bank'
                                ];
                                $icon = $typeIcons[$plan['type']] ?? 'shield';
                                ?>
                                <i class="bi bi-<?= $icon ?> me-1"></i><?= e($plan['type']) ?>
                            </td>
                            <td><?= e($plan['provider']) ?></td>
                            <td><?= $plan['cost_monthly'] > 0 ? formatCurrency($plan['cost_monthly']) : 'Free' ?></td>
                            <td>
                                <span class="badge bg-primary"><?= $plan['enrolled'] ?> employees</span>
                            </td>
                            <td>
                                <span class="badge bg-success">Active</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enrollment Periods -->
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Enrollment Periods</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle"></i> <strong>Open Enrollment:</strong> November 1 - November 30, 2024
        </div>

        <div class="list-group">
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Annual Open Enrollment 2024</h6>
                        <p class="mb-0 text-muted">November 1 - November 30, 2024</p>
                    </div>
                    <span class="badge bg-primary">Upcoming</span>
                </div>
            </div>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">New Hire Enrollment</h6>
                        <p class="mb-0 text-muted">Available for 30 days after hire date</p>
                    </div>
                    <span class="badge bg-success">Active</span>
                </div>
            </div>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Qualifying Life Event</h6>
                        <p class="mb-0 text-muted">Available within 30 days of event</p>
                    </div>
                    <span class="badge bg-success">Active</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
