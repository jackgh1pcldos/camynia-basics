<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Time Off Management';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];
$userId = $currentUser['id'];

$action = $_GET['action'] ?? 'list';
$requestId = $_GET['id'] ?? null;
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request';
    } elseif (isset($_POST['submit_request'])) {
        // Submit time off request
        try {
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $daysCount = $_POST['days_count'];

            $stmt = $db->prepare("
                INSERT INTO time_off_requests (company_id, user_id, type, start_date, end_date, days_count, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $companyId,
                $userId,
                $_POST['type'],
                $startDate,
                $endDate,
                $daysCount,
                $_POST['reason']
            ]);

            setFlash('success', 'Time off request submitted successfully!');
            redirect('/staff/hr-timeoff.php');
        } catch (PDOException $e) {
            $error = 'Failed to submit request: ' . $e->getMessage();
        }
    } elseif (isset($_POST['approve_request'])) {
        // Approve request
        try {
            $stmt = $db->prepare("
                UPDATE time_off_requests
                SET status = 'approved', approved_by = ?, approved_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$userId, $requestId, $companyId]);

            setFlash('success', 'Request approved successfully!');
            redirect('/staff/hr-timeoff.php');
        } catch (PDOException $e) {
            $error = 'Failed to approve request';
        }
    } elseif (isset($_POST['deny_request'])) {
        // Deny request
        try {
            $stmt = $db->prepare("
                UPDATE time_off_requests
                SET status = 'denied', approved_by = ?, approved_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$userId, $requestId, $companyId]);

            setFlash('warning', 'Request denied');
            redirect('/staff/hr-timeoff.php');
        } catch (PDOException $e) {
            $error = 'Failed to deny request';
        }
    } elseif (isset($_POST['cancel_request'])) {
        // Cancel request (only by owner, only if pending)
        try {
            $stmt = $db->prepare("
                UPDATE time_off_requests
                SET status = 'cancelled'
                WHERE id = ? AND company_id = ? AND user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$requestId, $companyId, $userId]);

            setFlash('success', 'Request cancelled successfully!');
            redirect('/staff/hr-timeoff.php');
        } catch (PDOException $e) {
            $error = 'Failed to cancel request';
        }
    } elseif (isset($_POST['update_request'])) {
        // Update request (only if pending)
        try {
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $daysCount = $_POST['days_count'];

            $stmt = $db->prepare("
                UPDATE time_off_requests
                SET type = ?, start_date = ?, end_date = ?, days_count = ?, reason = ?
                WHERE id = ? AND company_id = ? AND user_id = ? AND status = 'pending'
            ");
            $stmt->execute([
                $_POST['type'],
                $startDate,
                $endDate,
                $daysCount,
                $_POST['reason'],
                $requestId,
                $companyId,
                $userId
            ]);

            setFlash('success', 'Request updated successfully!');
            redirect('/staff/hr-timeoff.php');
        } catch (PDOException $e) {
            $error = 'Failed to update request: ' . $e->getMessage();
        }
    }
}

// Get all time off requests
try {
    $filter = $_GET['filter'] ?? 'all';
    $sql = "
        SELECT t.*, u.first_name, u.last_name, u.email,
               a.first_name as approver_first_name, a.last_name as approver_last_name
        FROM time_off_requests t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN users a ON t.approved_by = a.id
        WHERE t.company_id = ?
    ";

    if ($filter === 'pending') {
        $sql .= " AND t.status = 'pending'";
    } elseif ($filter === 'approved') {
        $sql .= " AND t.status = 'approved'";
    } elseif ($filter === 'my_requests') {
        $sql .= " AND t.user_id = " . $userId;
    }

    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$companyId]);
    $requests = $stmt->fetchAll();

    // Get stats
    $stmt = $db->prepare("SELECT COUNT(*) FROM time_off_requests WHERE company_id = ? AND status = 'pending'");
    $stmt->execute([$companyId]);
    $pendingCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM time_off_requests WHERE company_id = ? AND status = 'approved' AND start_date >= CURDATE()");
    $stmt->execute([$companyId]);
    $upcomingCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM time_off_requests WHERE company_id = ? AND user_id = ?");
    $stmt->execute([$companyId, $userId]);
    $myRequestsCount = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('Time off error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Time Off Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Time Off</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="?action=request" class="btn btn-primary">
                <i class="bi bi-calendar-plus"></i> Request Time Off
            </a>
        </div>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-value"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stat-value"><?= $upcomingCount ?></div>
                <div class="stat-label">Upcoming Time Off</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="stat-value"><?= $myRequestsCount ?></div>
                <div class="stat-label">My Requests</div>
            </div>
        </div>
    </div>

    <!-- Time Off Requests List -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Time Off Requests</h5>
                <div class="btn-group">
                    <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                    <a href="?filter=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">Pending</a>
                    <a href="?filter=approved" class="btn btn-sm <?= $filter === 'approved' ? 'btn-primary' : 'btn-outline-secondary' ?>">Approved</a>
                    <a href="?filter=my_requests" class="btn btn-sm <?= $filter === 'my_requests' ? 'btn-primary' : 'btn-outline-secondary' ?>">My Requests</a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-calendar-x" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Time Off Requests</h4>
                    <p>No requests match the selected filter</p>
                    <a href="?action=request" class="btn btn-primary mt-2">
                        <i class="bi bi-calendar-plus"></i> Request Time Off
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?= getInitials($req['first_name'], $req['last_name']) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= e($req['first_name'] . ' ' . $req['last_name']) ?></div>
                                                <small class="text-muted"><?= e($req['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $typeColors = [
                                            'vacation' => 'info',
                                            'sick' => 'warning',
                                            'personal' => 'secondary',
                                            'unpaid' => 'dark',
                                            'other' => 'light text-dark'
                                        ];
                                        $color = $typeColors[$req['type']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst($req['type']) ?></span>
                                    </td>
                                    <td><?= formatDate($req['start_date']) ?></td>
                                    <td><?= formatDate($req['end_date']) ?></td>
                                    <td><?= $req['days_count'] ?> days</td>
                                    <td>
                                        <small><?= e(truncate($req['reason'] ?? 'No reason provided', 50)) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'denied' => 'danger',
                                            'cancelled' => 'secondary'
                                        ];
                                        $color = $statusColors[$req['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst($req['status']) ?></span>
                                    </td>
                                    <td><small><?= timeAgo($req['created_at']) ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=view&id=<?= $req['id'] ?>" class="btn btn-outline-secondary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($req['status'] === 'pending' && $req['user_id'] == $userId): ?>
                                                <a href="?action=edit&id=<?= $req['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal"
                                                        data-bs-target="#cancelModal<?= $req['id'] ?>" title="Cancel">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <button class="btn btn-success" data-bs-toggle="modal"
                                                        data-bs-target="#approveModal<?= $req['id'] ?>" title="Approve">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button class="btn btn-danger" data-bs-toggle="modal"
                                                        data-bs-target="#denyModal<?= $req['id'] ?>" title="Deny">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                            <!-- Approve Modal -->
                                            <div class="modal fade" id="approveModal<?= $req['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Approve Time Off Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Approve time off request for <strong><?= e($req['first_name'] . ' ' . $req['last_name']) ?></strong>?</p>
                                                            <p class="mb-0">
                                                                <strong>Dates:</strong> <?= formatDate($req['start_date']) ?> - <?= formatDate($req['end_date']) ?><br>
                                                                <strong>Days:</strong> <?= $req['days_count'] ?><br>
                                                                <strong>Type:</strong> <?= ucfirst($req['type']) ?>
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form method="post" action="?id=<?= $req['id'] ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="approve_request" class="btn btn-success">Approve</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Deny Modal -->
                                            <div class="modal fade" id="denyModal<?= $req['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Deny Time Off Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Deny time off request for <strong><?= e($req['first_name'] . ' ' . $req['last_name']) ?></strong>?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form method="post" action="?id=<?= $req['id'] ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="deny_request" class="btn btn-danger">Deny</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Cancel Modal -->
                                            <?php if ($req['user_id'] == $userId): ?>
                                            <div class="modal fade" id="cancelModal<?= $req['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Cancel Time Off Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to cancel your time off request?</p>
                                                            <p class="mb-0">
                                                                <strong>Dates:</strong> <?= formatDate($req['start_date']) ?> - <?= formatDate($req['end_date']) ?><br>
                                                                <strong>Type:</strong> <?= ucfirst($req['type']) ?>
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form method="post" action="?id=<?= $req['id'] ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                                                                <button type="submit" name="cancel_request" class="btn btn-danger">Yes, Cancel Request</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($req['approver_first_name']): ?>
                                                <small class="text-muted">
                                                    by <?= e($req['approver_first_name'] . ' ' . $req['approver_last_name']) ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calendar View -->
    <div class="card mt-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Calendar View</h5>
        </div>
        <div class="card-body">
            <div id="timeoffCalendar" class="p-4 text-center text-muted">
                <i class="bi bi-calendar3" style="font-size: 48px;"></i>
                <p class="mt-2">Calendar integration coming soon</p>
            </div>
        </div>
    </div>

<?php elseif ($action === 'request'): ?>
    <!-- Request Time Off Form -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Request Time Off</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" id="timeoffForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Time Off Type *</label>
                        <select name="type" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            <option value="vacation">Vacation</option>
                            <option value="sick">Sick Leave</option>
                            <option value="personal">Personal</option>
                            <option value="unpaid">Unpaid Leave</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" id="startDate" class="form-control"
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" id="endDate" class="form-control"
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Number of Days *</label>
                        <input type="number" name="days_count" id="daysCount" class="form-control"
                               step="0.5" min="0.5" required readonly>
                        <small class="text-muted">Automatically calculated</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3"
                              placeholder="Optional: Provide details about your time off request"></textarea>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" name="submit_request" class="btn btn-primary">
                        <i class="bi bi-send"></i> Submit Request
                    </button>
                    <a href="/staff/hr-timeoff.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Calculate days between start and end date
    function calculateDays() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end
            document.getElementById('daysCount').value = diffDays;
        }
    }

    document.getElementById('startDate')?.addEventListener('change', calculateDays);
    document.getElementById('endDate')?.addEventListener('change', calculateDays);
    </script>

<?php elseif ($action === 'edit' && $requestId): ?>
    <?php
    // Get request details
    try {
        $stmt = $db->prepare("
            SELECT * FROM time_off_requests
            WHERE id = ? AND company_id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$requestId, $companyId, $userId]);
        $request = $stmt->fetch();

        if (!$request) {
            setFlash('danger', 'Request not found or cannot be edited');
            redirect('/staff/hr-timeoff.php');
        }
    } catch (PDOException $e) {
        setFlash('danger', 'Error loading request');
        redirect('/staff/hr-timeoff.php');
    }
    ?>

    <!-- Edit Time Off Request -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Edit Time Off Request</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="?action=edit&id=<?= $requestId ?>" id="timeoffForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Time Off Type *</label>
                        <select name="type" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            <option value="vacation" <?= $request['type'] == 'vacation' ? 'selected' : '' ?>>Vacation</option>
                            <option value="sick" <?= $request['type'] == 'sick' ? 'selected' : '' ?>>Sick Leave</option>
                            <option value="personal" <?= $request['type'] == 'personal' ? 'selected' : '' ?>>Personal</option>
                            <option value="unpaid" <?= $request['type'] == 'unpaid' ? 'selected' : '' ?>>Unpaid Leave</option>
                            <option value="other" <?= $request['type'] == 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" id="startDate" class="form-control"
                               value="<?= $request['start_date'] ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" id="endDate" class="form-control"
                               value="<?= $request['end_date'] ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Number of Days *</label>
                        <input type="number" name="days_count" id="daysCount" class="form-control"
                               value="<?= $request['days_count'] ?>" step="0.5" min="0.5" required readonly>
                        <small class="text-muted">Automatically calculated</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3"
                              placeholder="Optional: Provide details about your time off request"><?= e($request['reason']) ?></textarea>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" name="update_request" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Update Request
                    </button>
                    <a href="/staff/hr-timeoff.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Calculate days between start and end date
    function calculateDays() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('daysCount').value = diffDays;
        }
    }

    document.getElementById('startDate')?.addEventListener('change', calculateDays);
    document.getElementById('endDate')?.addEventListener('change', calculateDays);
    </script>

<?php elseif ($action === 'view' && $requestId): ?>
    <?php
    // Get request details
    try {
        $stmt = $db->prepare("
            SELECT t.*, u.first_name, u.last_name, u.email,
                   a.first_name as approver_first_name, a.last_name as approver_last_name
            FROM time_off_requests t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.approved_by = a.id
            WHERE t.id = ? AND t.company_id = ?
        ");
        $stmt->execute([$requestId, $companyId]);
        $request = $stmt->fetch();

        if (!$request) {
            setFlash('danger', 'Request not found');
            redirect('/staff/hr-timeoff.php');
        }
    } catch (PDOException $e) {
        setFlash('danger', 'Error loading request');
        redirect('/staff/hr-timeoff.php');
    }
    ?>

    <!-- View Time Off Request -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Time Off Request Details</h5>
                    <div>
                        <?php if ($request['status'] === 'pending' && $request['user_id'] == $userId): ?>
                            <a href="?action=edit&id=<?= $request['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        <?php endif; ?>
                        <a href="/staff/hr-timeoff.php" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Employee</h6>
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-2">
                                    <?= getInitials($request['first_name'], $request['last_name']) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= e($request['first_name'] . ' ' . $request['last_name']) ?></div>
                                    <small class="text-muted"><?= e($request['email']) ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Status</h6>
                            <?php
                            $statusColors = [
                                'pending' => 'warning',
                                'approved' => 'success',
                                'denied' => 'danger',
                                'cancelled' => 'secondary'
                            ];
                            $color = $statusColors[$request['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?> fs-6"><?= ucfirst($request['status']) ?></span>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Time Off Type</h6>
                            <?php
                            $typeColors = [
                                'vacation' => 'info',
                                'sick' => 'warning',
                                'personal' => 'secondary',
                                'unpaid' => 'dark',
                                'other' => 'light text-dark'
                            ];
                            $color = $typeColors[$request['type']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?> fs-6"><?= ucfirst($request['type']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Duration</h6>
                            <p class="mb-0"><?= $request['days_count'] ?> days</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Start Date</h6>
                            <p class="mb-0"><?= formatDate($request['start_date']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">End Date</h6>
                            <p class="mb-0"><?= formatDate($request['end_date']) ?></p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-muted">Reason</h6>
                        <p class="mb-0"><?= e($request['reason']) ?: 'No reason provided' ?></p>
                    </div>

                    <?php if ($request['approved_by']): ?>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-muted"><?= $request['status'] === 'approved' ? 'Approved By' : 'Denied By' ?></h6>
                                <p class="mb-0"><?= e($request['approver_first_name'] . ' ' . $request['approver_last_name']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted"><?= $request['status'] === 'approved' ? 'Approved At' : 'Denied At' ?></h6>
                                <p class="mb-0"><?= formatDate($request['approved_at']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Submitted</h6>
                            <p class="mb-0"><?= formatDate($request['created_at']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
