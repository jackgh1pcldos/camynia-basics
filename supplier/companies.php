<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Companies';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$supplierId = $currentUser['supplier_id'];

$action = $_GET['action'] ?? 'list';
$companyId = $_GET['id'] ?? null;
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request';
    } elseif (isset($_POST['add_company'])) {
        try {
            $stmt = $db->prepare("
                INSERT INTO companies (supplier_id, company_name, slug, email, phone, address, website, timezone, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $_POST['company_name']));
            $stmt->execute([
                $supplierId,
                $_POST['company_name'],
                $slug,
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['website'],
                $_POST['timezone']
            ]);

            setFlash('success', 'Company added successfully!');
            redirect('/supplier/companies.php');
        } catch (PDOException $e) {
            $error = 'Failed to add company: ' . $e->getMessage();
        }
    }
}

// Get companies
try {
    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM users WHERE company_id = c.id) as user_count
        FROM companies c
        WHERE c.supplier_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$supplierId]);
    $companies = $stmt->fetchAll();

    // If viewing, get company details
    if ($action === 'view' && $companyId) {
        $stmt = $db->prepare("
            SELECT * FROM companies
            WHERE id = ? AND supplier_id = ?
        ");
        $stmt->execute([$companyId, $supplierId]);
        $company = $stmt->fetch();

        if (!$company) {
            setFlash('danger', 'Company not found');
            redirect('/supplier/companies.php');
        }

        // Get users for this company
        $stmt = $db->prepare("
            SELECT * FROM users
            WHERE company_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$companyId]);
        $companyUsers = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log('Companies error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Companies</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/supplier/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Companies</li>
                </ol>
            </nav>
        </div>
        <?php if ($action === 'list'): ?>
            <div>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Company
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Companies List -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">All Companies (<?= count($companies) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($companies)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-building" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Companies</h4>
                    <p>Add your first company to get started</p>
                    <a href="?action=add" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Add Company
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Users</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $comp): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($comp['company_name']) ?></div>
                                        <small class="text-muted"><?= e($comp['slug'] ?? '') ?></small>
                                    </td>
                                    <td><?= e($comp['email']) ?></td>
                                    <td><?= e($comp['phone'] ?? '-') ?></td>
                                    <td><span class="badge bg-primary"><?= $comp['user_count'] ?> users</span></td>
                                    <td>
                                        <?php
                                        $statusColors = ['active' => 'success', 'suspended' => 'warning', 'cancelled' => 'danger'];
                                        $color = $statusColors[$comp['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst($comp['status']) ?></span>
                                    </td>
                                    <td><?= formatDate($comp['created_at']) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=view&id=<?= $comp['id'] ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?= $comp['id'] ?>" class="btn btn-outline-primary">
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

<?php elseif ($action === 'add'): ?>
    <!-- Add Company Form -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Add New Company</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select">
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">Eastern Time</option>
                            <option value="America/Chicago">Central Time</option>
                            <option value="America/Denver">Mountain Time</option>
                            <option value="America/Los_Angeles">Pacific Time</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" name="add_company" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Add Company
                    </button>
                    <a href="/supplier/companies.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view' && $company): ?>
    <!-- View Company -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">Company Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Company Name</small>
                        <div class="fw-semibold"><?= e($company['company_name']) ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Email</small>
                        <div class="fw-semibold"><?= e($company['email']) ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Phone</small>
                        <div class="fw-semibold"><?= e($company['phone'] ?? 'Not provided') ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Website</small>
                        <div class="fw-semibold">
                            <?php if ($company['website']): ?>
                                <a href="<?= e($company['website']) ?>" target="_blank"><?= e($company['website']) ?></a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Status</small>
                        <div>
                            <?php
                            $statusColors = ['active' => 'success', 'suspended' => 'warning', 'cancelled' => 'danger'];
                            $color = $statusColors[$company['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst($company['status']) ?></span>
                        </div>
                    </div>
                    <div>
                        <small class="text-muted">Created</small>
                        <div class="fw-semibold"><?= formatDateTime($company['created_at']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">Company Users (<?= count($companyUsers ?? []) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($companyUsers)): ?>
                        <div class="p-4 text-center text-muted">
                            <p>No users in this company</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companyUsers as $user): ?>
                                        <tr>
                                            <td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                            <td><?= e($user['email']) ?></td>
                                            <td>
                                                <?php
                                                $userStatusColors = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'danger'];
                                                $color = $userStatusColors[$user['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $color ?>"><?= ucfirst($user['status']) ?></span>
                                            </td>
                                            <td><?= $user['last_login_at'] ? timeAgo($user['last_login_at']) : 'Never' ?></td>
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
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
