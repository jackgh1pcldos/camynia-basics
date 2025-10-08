<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Employees (HRIS)';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

$action = $_GET['action'] ?? 'list';
$employeeId = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (isset($_POST['add_employee'])) {
        // Add new employee
        try {
            $db->beginTransaction();

            // Create user account
            $stmt = $db->prepare("
                INSERT INTO users (company_id, first_name, last_name, email, password_hash, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $passwordHash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt->execute([
                $companyId,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $passwordHash
            ]);
            $userId = $db->lastInsertId();

            // Create employee record
            $stmt = $db->prepare("
                INSERT INTO employees (
                    company_id, user_id, employee_number, department_id, job_title,
                    employment_type, hire_date, termination_date, manager_id, salary, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $companyId,
                $userId,
                $_POST['employee_number'],
                $_POST['department_id'] ?: null,
                $_POST['job_title'],
                $_POST['employment_type'],
                $_POST['hire_date'],
                $_POST['termination_date'] ?: null,
                $_POST['manager_id'] ?: null,
                $_POST['salary'] ?: null
            ]);

            $db->commit();
            setFlash('success', 'Employee added successfully!');
            redirect('/staff/hr-employees.php');
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to add employee: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_employee'])) {
        // Update employee
        try {
            $db->beginTransaction();

            // Update user
            $stmt = $db->prepare("
                UPDATE users SET first_name = ?, last_name = ?, email = ?
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['user_id'],
                $companyId
            ]);

            // Update employee
            $stmt = $db->prepare("
                UPDATE employees SET
                    employee_number = ?, department_id = ?, job_title = ?,
                    employment_type = ?, hire_date = ?, termination_date = ?, manager_id = ?, salary = ?, status = ?
                WHERE id = ? AND company_id = ?
            ");
            // If termination date is set, mark as terminated
            $status = $_POST['termination_date'] ? 'terminated' : 'active';
            $stmt->execute([
                $_POST['employee_number'],
                $_POST['department_id'] ?: null,
                $_POST['job_title'],
                $_POST['employment_type'],
                $_POST['hire_date'],
                $_POST['termination_date'] ?: null,
                $_POST['manager_id'] ?: null,
                $_POST['salary'] ?: null,
                $status,
                $employeeId,
                $companyId
            ]);

            $db->commit();
            setFlash('success', 'Employee updated successfully!');
            redirect('/staff/hr-employees.php');
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to update employee: ' . $e->getMessage();
        }
    } elseif (isset($_POST['add_department'])) {
        // Add new department
        try {
            // Prepare allowances JSON
            $allowances = [
                'vacation' => (int)($_POST['allowance_vacation'] ?? 20),
                'sick' => (int)($_POST['allowance_sick'] ?? 10),
                'personal' => (int)($_POST['allowance_personal'] ?? 5),
                'unpaid' => (int)($_POST['allowance_unpaid'] ?? 0)
            ];

            $stmt = $db->prepare("
                INSERT INTO departments (company_id, name, manager_id, time_off_allowances)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $_POST['name'],
                $_POST['manager_id'] ?: null,
                json_encode($allowances)
            ]);
            setFlash('success', 'Department added successfully!');
            redirect('/staff/hr-employees.php?action=departments');
        } catch (PDOException $e) {
            $error = 'Failed to add department: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_department'])) {
        // Update department
        try {
            // Prepare allowances JSON
            $allowances = [
                'vacation' => (int)($_POST['allowance_vacation'] ?? 20),
                'sick' => (int)($_POST['allowance_sick'] ?? 10),
                'personal' => (int)($_POST['allowance_personal'] ?? 5),
                'unpaid' => (int)($_POST['allowance_unpaid'] ?? 0)
            ];

            $stmt = $db->prepare("
                UPDATE departments SET name = ?, manager_id = ?, time_off_allowances = ?
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['manager_id'] ?: null,
                json_encode($allowances),
                $_POST['department_id'],
                $companyId
            ]);
            setFlash('success', 'Department updated successfully!');
            redirect('/staff/hr-employees.php?action=departments');
        } catch (PDOException $e) {
            $error = 'Failed to update department: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_department'])) {
        // Delete department
        try {
            $stmt = $db->prepare("
                DELETE FROM departments WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $_POST['department_id'],
                $companyId
            ]);
            setFlash('success', 'Department deleted successfully!');
            redirect('/staff/hr-employees.php?action=departments');
        } catch (PDOException $e) {
            $error = 'Failed to delete department: ' . $e->getMessage();
        }
    }
}

// Get employees list
try {
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email, u.avatar,
               d.name as department_name,
               m.first_name as manager_first_name, m.last_name as manager_last_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users m ON e.manager_id = m.id
        WHERE e.company_id = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$companyId]);
    $employees = $stmt->fetchAll();

    // Get departments for dropdowns
    $stmt = $db->prepare("SELECT id, name, manager_id, time_off_allowances FROM departments WHERE company_id = ? ORDER BY name");
    $stmt->execute([$companyId]);
    $departments = $stmt->fetchAll();

    // Get managers (all active users)
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        WHERE u.company_id = ? AND u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$companyId]);
    $managers = $stmt->fetchAll();

    // If editing, get employee details
    if ($action === 'edit' && $employeeId) {
        $stmt = $db->prepare("
            SELECT e.*, u.first_name, u.last_name, u.email, u.id as user_id
            FROM employees e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ? AND e.company_id = ?
        ");
        $stmt->execute([$employeeId, $companyId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            setFlash('danger', 'Employee not found');
            redirect('/staff/hr-employees.php');
        }
    }
} catch (PDOException $e) {
    error_log('Employee list error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Employees (HRIS)</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Employees</li>
                </ol>
            </nav>
        </div>
        <?php if ($action === 'list'): ?>
            <div>
                <a href="?action=departments" class="btn btn-secondary me-2">
                    <i class="bi bi-diagram-3"></i> Manage Departments
                </a>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Add Employee
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Employee List -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">All Employees (<?= count($employees) ?>)</h5>
                </div>
                <div class="col-auto">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search employees..." id="searchInput">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($employees)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-people" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Employees Yet</h4>
                    <p>Get started by adding your first employee</p>
                    <a href="?action=add" class="btn btn-primary mt-2">
                        <i class="bi bi-person-plus"></i> Add Employee
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="employeeTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Employee #</th>
                                <th>Department</th>
                                <th>Job Title</th>
                                <th>Type</th>
                                <th>Manager</th>
                                <th>Hire Date</th>
                                <th>Status</th>
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
                                    <td><?= e($emp['employee_number'] ?? '-') ?></td>
                                    <td><?= e($emp['department_name'] ?? '-') ?></td>
                                    <td><?= e($emp['job_title'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= ucfirst($emp['employment_type'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <?php if ($emp['manager_first_name']): ?>
                                            <?= e($emp['manager_first_name'] . ' ' . $emp['manager_last_name']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
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
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=view&id=<?= $emp['id'] ?>" class="btn btn-outline-secondary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?= $emp['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
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

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Employee Form -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><?= $action === 'add' ? 'Add New Employee' : 'Edit Employee' ?></h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="user_id" value="<?= $employee['user_id'] ?>">
                <?php endif; ?>

                <h6 class="mb-3">Personal Information</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control"
                               value="<?= e($employee['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control"
                               value="<?= e($employee['last_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= e($employee['email'] ?? '') ?>" required>
                    </div>
                    <?php if ($action === 'add'): ?>
                        <div class="col-md-6">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" minlength="8" required>
                        </div>
                    <?php endif; ?>
                </div>

                <hr class="my-4">

                <h6 class="mb-3">Employment Information</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee Number</label>
                        <input type="text" name="employee_number" class="form-control"
                               value="<?= e($employee['employee_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Job Title *</label>
                        <input type="text" name="job_title" class="form-control"
                               value="<?= e($employee['job_title'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"
                                    <?= isset($employee) && $employee['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                    <?= e($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Employment Type *</label>
                        <select name="employment_type" class="form-select" required>
                            <option value="full-time" <?= isset($employee) && $employee['employment_type'] == 'full-time' ? 'selected' : '' ?>>Full-Time</option>
                            <option value="part-time" <?= isset($employee) && $employee['employment_type'] == 'part-time' ? 'selected' : '' ?>>Part-Time</option>
                            <option value="contractor" <?= isset($employee) && $employee['employment_type'] == 'contractor' ? 'selected' : '' ?>>Contractor</option>
                            <option value="intern" <?= isset($employee) && $employee['employment_type'] == 'intern' ? 'selected' : '' ?>>Intern</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Manager</label>
                        <select name="manager_id" class="form-select">
                            <option value="">-- Select Manager --</option>
                            <?php foreach ($managers as $mgr): ?>
                                <option value="<?= $mgr['id'] ?>"
                                    <?= isset($employee) && $employee['manager_id'] == $mgr['id'] ? 'selected' : '' ?>>
                                    <?= e($mgr['first_name'] . ' ' . $mgr['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hire Date *</label>
                        <input type="date" name="hire_date" class="form-control"
                               value="<?= $employee['hire_date'] ?? date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Termination Date</label>
                        <input type="date" name="termination_date" class="form-control"
                               value="<?= $employee['termination_date'] ?? '' ?>">
                        <small class="text-muted">Leave blank if employee is active</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="salary" class="form-control" step="0.01"
                                   value="<?= $employee['salary'] ?? '' ?>">
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" name="<?= $action === 'add' ? 'add_employee' : 'update_employee' ?>" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= $action === 'add' ? 'Add Employee' : 'Update Employee' ?>
                    </button>
                    <a href="/staff/hr-employees.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view' && $employeeId): ?>
    <?php
    // Get detailed employee info
    try {
        $stmt = $db->prepare("
            SELECT e.*, u.first_name, u.last_name, u.email, u.avatar, u.created_at as user_created,
                   d.name as department_name,
                   m.first_name as manager_first_name, m.last_name as manager_last_name
            FROM employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users m ON e.manager_id = m.id
            WHERE e.id = ? AND e.company_id = ?
        ");
        $stmt->execute([$employeeId, $companyId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            setFlash('danger', 'Employee not found');
            redirect('/staff/hr-employees.php');
        }
    } catch (PDOException $e) {
        error_log('Employee view error: ' . $e->getMessage());
        setFlash('danger', 'Error loading employee details');
        redirect('/staff/hr-employees.php');
    }
    ?>

    <!-- Employee Profile View -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="user-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 36px;">
                        <?= getInitials($employee['first_name'], $employee['last_name']) ?>
                    </div>
                    <h4><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></h4>
                    <p class="text-muted mb-1"><?= e($employee['job_title'] ?? 'N/A') ?></p>
                    <p class="text-muted small"><?= e($employee['department_name'] ?? 'No Department') ?></p>

                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?= $employee['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="mailto:<?= e($employee['email']) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-envelope"></i> Email
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Quick Info</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Employee Number</small>
                        <div class="fw-semibold"><?= e($employee['employee_number'] ?? 'N/A') ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Email</small>
                        <div class="fw-semibold"><?= e($employee['email']) ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Status</small>
                        <div>
                            <?php
                            $statusColors = ['active' => 'success', 'on_leave' => 'warning', 'terminated' => 'danger'];
                            $color = $statusColors[$employee['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $employee['status'])) ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Employment Type</small>
                        <div class="fw-semibold"><?= ucfirst($employee['employment_type'] ?? 'N/A') ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Hire Date</small>
                        <div class="fw-semibold"><?= formatDate($employee['hire_date']) ?></div>
                    </div>
                    <?php if ($employee['termination_date']): ?>
                    <div>
                        <small class="text-muted">Termination Date</small>
                        <div class="fw-semibold text-danger"><?= formatDate($employee['termination_date']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Employment Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Department</small>
                            <div class="fw-semibold"><?= e($employee['department_name'] ?? 'Not assigned') ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Manager</small>
                            <div class="fw-semibold">
                                <?= $employee['manager_first_name'] ? e($employee['manager_first_name'] . ' ' . $employee['manager_last_name']) : 'None' ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Job Title</small>
                            <div class="fw-semibold"><?= e($employee['job_title'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Salary</small>
                            <div class="fw-semibold"><?= $employee['salary'] ? formatCurrency($employee['salary']) : 'N/A' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="text-muted text-center p-4">
                        <i class="bi bi-clock-history" style="font-size: 32px;"></i>
                        <p class="mt-2">No recent activity</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'departments'): ?>
    <!-- Department Management -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Departments</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                        <i class="bi bi-plus-lg"></i> Add Department
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if ($error): ?>
                        <div class="alert alert-danger m-3"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if (empty($departments)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-diagram-3" style="font-size: 64px;"></i>
                            <h4 class="mt-3">No Departments Yet</h4>
                            <p>Get started by adding your first department</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Department Name</th>
                                        <th>Manager</th>
                                        <th>Employees</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $dept):
                                        // Get employee count
                                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE department_id = ? AND company_id = ?");
                                        $stmt->execute([$dept['id'], $companyId]);
                                        $empCount = $stmt->fetch()['count'];

                                        // Get manager name
                                        $managerName = 'Not assigned';
                                        if ($dept['manager_id']) {
                                            $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                                            $stmt->execute([$dept['manager_id']]);
                                            $mgr = $stmt->fetch();
                                            if ($mgr) {
                                                $managerName = e($mgr['first_name'] . ' ' . $mgr['last_name']);
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td><strong><?= e($dept['name']) ?></strong></td>
                                            <td><?= $managerName ?></td>
                                            <td><span class="badge bg-primary"><?= $empCount ?></span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary"
                                                            onclick='editDepartment(<?= $dept['id'] ?>, "<?= e($dept['name']) ?>", <?= $dept['manager_id'] ?: 'null' ?>, <?= $dept['time_off_allowances'] ?: 'null' ?>)'>
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger"
                                                            onclick="deleteDepartment(<?= $dept['id'] ?>, '<?= e($dept['name']) ?>')">
                                                        <i class="bi bi-trash"></i>
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

            <div class="mt-3">
                <a href="/staff/hr-employees.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Employees
                </a>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Manager</label>
                            <select name="manager_id" class="form-select">
                                <option value="">-- Select Manager --</option>
                                <?php foreach ($managers as $mgr): ?>
                                    <option value="<?= $mgr['id'] ?>">
                                        <?= e($mgr['first_name'] . ' ' . $mgr['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr class="my-3">
                        <h6 class="mb-3">Time Off Allowances (days per year)</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Vacation Days</label>
                                <input type="number" name="allowance_vacation" class="form-control" value="20" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sick Days</label>
                                <input type="number" name="allowance_sick" class="form-control" value="10" min="0">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Personal Days</label>
                                <input type="number" name="allowance_personal" class="form-control" value="5" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unpaid Days</label>
                                <input type="number" name="allowance_unpaid" class="form-control" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="department_id" id="edit_dept_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" name="name" id="edit_dept_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Manager</label>
                            <select name="manager_id" id="edit_dept_manager" class="form-select">
                                <option value="">-- Select Manager --</option>
                                <?php foreach ($managers as $mgr): ?>
                                    <option value="<?= $mgr['id'] ?>">
                                        <?= e($mgr['first_name'] . ' ' . $mgr['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr class="my-3">
                        <h6 class="mb-3">Time Off Allowances (days per year)</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Vacation Days</label>
                                <input type="number" name="allowance_vacation" id="edit_allowance_vacation" class="form-control" value="20" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sick Days</label>
                                <input type="number" name="allowance_sick" id="edit_allowance_sick" class="form-control" value="10" min="0">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Personal Days</label>
                                <input type="number" name="allowance_personal" id="edit_allowance_personal" class="form-control" value="5" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unpaid Days</label>
                                <input type="number" name="allowance_unpaid" id="edit_allowance_unpaid" class="form-control" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Department Modal -->
    <div class="modal fade" id="deleteDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="department_id" id="delete_dept_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete department <strong id="delete_dept_name"></strong>?</p>
                        <p class="text-muted small">Employees in this department will be unassigned.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_department" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endif; ?>

<script>
// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('employeeTable');
    const rows = table?.querySelectorAll('tbody tr');

    rows?.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Department management functions
function editDepartment(id, name, managerId, allowances) {
    document.getElementById('edit_dept_id').value = id;
    document.getElementById('edit_dept_name').value = name;
    document.getElementById('edit_dept_manager').value = managerId || '';

    // Populate allowances
    if (allowances) {
        document.getElementById('edit_allowance_vacation').value = allowances.vacation || 20;
        document.getElementById('edit_allowance_sick').value = allowances.sick || 10;
        document.getElementById('edit_allowance_personal').value = allowances.personal || 5;
        document.getElementById('edit_allowance_unpaid').value = allowances.unpaid || 0;
    }

    new bootstrap.Modal(document.getElementById('editDeptModal')).show();
}

function deleteDepartment(id, name) {
    document.getElementById('delete_dept_id').value = id;
    document.getElementById('delete_dept_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteDeptModal')).show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
