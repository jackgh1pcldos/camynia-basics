<?php
require_once __DIR__ . '/../../../includes/core/bootstrap.php';
require_once __DIR__ . '/../../../includes/core/finance-audit.php';

if (!isLoggedIn() || isSupplier()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'view':
            handleView();
            break;
        case 'create':
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            handleDelete();
            break;
        case 'categories':
            handleCategories();
            break;
        case 'audit_history':
            handleAuditHistory();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleList() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];

    $where = ['v.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    // Filters
    if (!empty($_GET['status'])) {
        $where[] = 'v.status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['risk_level'])) {
        $where[] = 'v.risk_level = :risk_level';
        $params[':risk_level'] = $_GET['risk_level'];
    }

    if (!empty($_GET['category_id'])) {
        $where[] = 'v.category_id = :category_id';
        $params[':category_id'] = $_GET['category_id'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(v.vendor_code LIKE :search OR v.vendor_name LIKE :search OR v.primary_contact_email LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            v.*,
            vc.name as category_name
        FROM vendors v
        LEFT JOIN vendor_categories vc ON v.category_id = vc.id
        WHERE $whereClause
        ORDER BY v.vendor_name ASC
    ");

    $stmt->execute($params);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $vendors
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $vendorId = $_GET['id'] ?? null;

    if (!$vendorId) {
        throw new Exception('Vendor ID required');
    }

    $stmt = $pdo->prepare("
        SELECT
            v.*,
            vc.name as category_name
        FROM vendors v
        LEFT JOIN vendor_categories vc ON v.category_id = vc.id
        WHERE v.id = ? AND v.company_id = ?
    ");

    $stmt->execute([$vendorId, $companyId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        throw new Exception('Vendor not found');
    }

    // Log data access for sensitive fields
    $sensitiveFields = ['tax_id', 'bank_account_number', 'bank_routing_number'];
    logDataAccess('vendor_banking', 'vendors', $vendorId, $sensitiveFields, 'Vendor details viewed');

    echo json_encode([
        'success' => true,
        'data' => $vendor
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($input['vendor_code']) || empty($input['vendor_name'])) {
        throw new Exception('Vendor code and name are required');
    }

    // Check for duplicate vendor code
    $stmt = $pdo->prepare("SELECT id FROM vendors WHERE vendor_code = ? AND company_id = ?");
    $stmt->execute([$input['vendor_code'], $companyId]);
    if ($stmt->fetch()) {
        throw new Exception('Vendor code already exists');
    }

    // Insert vendor
    $stmt = $pdo->prepare("
        INSERT INTO vendors (
            company_id, vendor_code, vendor_name, legal_name, category_id,
            primary_contact_name, primary_contact_email, primary_contact_phone,
            website, address_line1, address_line2, city, state_province, postal_code, country,
            tax_id_type, tax_id, tax_exempt, is_1099_vendor,
            payment_terms, payment_method, credit_limit,
            risk_level, risk_score, risk_notes,
            status, onboarding_status, notes,
            created_by
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?
        )
    ");

    $stmt->execute([
        $companyId,
        $input['vendor_code'],
        $input['vendor_name'],
        $input['legal_name'] ?? null,
        $input['category_id'] ?? null,

        $input['primary_contact_name'] ?? null,
        $input['primary_contact_email'] ?? null,
        $input['primary_contact_phone'] ?? null,

        $input['website'] ?? null,
        $input['address_line1'] ?? null,
        $input['address_line2'] ?? null,
        $input['city'] ?? null,
        $input['state_province'] ?? null,
        $input['postal_code'] ?? null,
        $input['country'] ?? 'US',

        $input['tax_id_type'] ?? null,
        $input['tax_id'] ?? null,
        $input['tax_exempt'] ?? 0,
        $input['is_1099_vendor'] ?? 0,

        $input['payment_terms'] ?? 'NET30',
        $input['payment_method'] ?? 'CHECK',
        $input['credit_limit'] ?? 0,

        $input['risk_level'] ?? 'medium',
        $input['risk_score'] ?? 50,
        $input['risk_notes'] ?? null,

        $input['status'] ?? 'pending',
        $input['onboarding_status'] ?? 'new',
        $input['notes'] ?? null,

        $currentUser['id']
    ]);

    $vendorId = $pdo->lastInsertId();

    // **AUDIT LOG** - Vendor created
    logFinanceAudit(
        'vendor',
        $vendorId,
        'created',
        $input['vendor_code'],
        "Vendor '{$input['vendor_name']}' created",
        null,
        null,
        null,
        null,
        [
            'vendor_name' => $input['vendor_name'],
            'status' => $input['status'] ?? 'pending',
            'risk_level' => $input['risk_level'] ?? 'medium'
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Vendor created successfully',
        'id' => $vendorId
    ]);
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $vendorId = $input['id'] ?? null;
    if (!$vendorId) {
        throw new Exception('Vendor ID required');
    }

    // Get current vendor data for audit trail
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ? AND company_id = ?");
    $stmt->execute([$vendorId, $companyId]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        throw new Exception('Vendor not found');
    }

    // Update vendor
    $stmt = $pdo->prepare("
        UPDATE vendors SET
            vendor_code = ?,
            vendor_name = ?,
            legal_name = ?,
            category_id = ?,
            primary_contact_name = ?,
            primary_contact_email = ?,
            primary_contact_phone = ?,
            website = ?,
            address_line1 = ?,
            address_line2 = ?,
            city = ?,
            state_province = ?,
            postal_code = ?,
            country = ?,
            tax_id_type = ?,
            tax_id = ?,
            tax_exempt = ?,
            is_1099_vendor = ?,
            payment_terms = ?,
            payment_method = ?,
            credit_limit = ?,
            risk_level = ?,
            risk_score = ?,
            risk_notes = ?,
            status = ?,
            onboarding_status = ?,
            notes = ?
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([
        $input['vendor_code'],
        $input['vendor_name'],
        $input['legal_name'] ?? null,
        $input['category_id'] ?? null,
        $input['primary_contact_name'] ?? null,
        $input['primary_contact_email'] ?? null,
        $input['primary_contact_phone'] ?? null,
        $input['website'] ?? null,
        $input['address_line1'] ?? null,
        $input['address_line2'] ?? null,
        $input['city'] ?? null,
        $input['state_province'] ?? null,
        $input['postal_code'] ?? null,
        $input['country'] ?? 'US',
        $input['tax_id_type'] ?? null,
        $input['tax_id'] ?? null,
        $input['tax_exempt'] ?? 0,
        $input['is_1099_vendor'] ?? 0,
        $input['payment_terms'] ?? 'NET30',
        $input['payment_method'] ?? 'CHECK',
        $input['credit_limit'] ?? 0,
        $input['risk_level'] ?? 'medium',
        $input['risk_score'] ?? 50,
        $input['risk_notes'] ?? null,
        $input['status'] ?? 'pending',
        $input['onboarding_status'] ?? 'new',
        $input['notes'] ?? null,
        $vendorId,
        $companyId
    ]);

    // **AUDIT LOG** - Track all field changes
    $fieldsToTrack = [
        'vendor_code', 'vendor_name', 'legal_name', 'status', 'risk_level', 'risk_score',
        'payment_terms', 'credit_limit', 'tax_id', 'primary_contact_email'
    ];

    logFieldChanges(
        'vendor',
        $vendorId,
        $input['vendor_code'],
        $oldData,
        $input,
        $fieldsToTrack
    );

    echo json_encode([
        'success' => true,
        'message' => 'Vendor updated successfully'
    ]);
}

function handleDelete() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $vendorId = $input['id'] ?? null;
    if (!$vendorId) {
        throw new Exception('Vendor ID required');
    }

    // Get vendor for audit
    $stmt = $pdo->prepare("SELECT vendor_code, vendor_name, status FROM vendors WHERE id = ? AND company_id = ?");
    $stmt->execute([$vendorId, $companyId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        throw new Exception('Vendor not found');
    }

    // Check if vendor has active POs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ? AND status IN ('approved', 'sent', 'partially_received')");
    $stmt->execute([$vendorId]);
    $activePOs = $stmt->fetchColumn();

    if ($activePOs > 0) {
        throw new Exception("Cannot delete vendor: {$activePOs} active purchase order(s) exist");
    }

    // Soft delete (set to inactive)
    $stmt = $pdo->prepare("UPDATE vendors SET status = 'inactive' WHERE id = ? AND company_id = ?");
    $stmt->execute([$vendorId, $companyId]);

    // **AUDIT LOG** - Vendor deactivated
    logFinanceAudit(
        'vendor',
        $vendorId,
        'deleted',
        $vendor['vendor_code'],
        "Vendor '{$vendor['vendor_name']}' deactivated",
        'status',
        $vendor['status'],
        'inactive'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Vendor deactivated successfully'
    ]);
}

function handleCategories() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];

    $stmt = $pdo->prepare("
        SELECT * FROM vendor_categories
        WHERE company_id = ? AND is_active = 1
        ORDER BY name ASC
    ");

    $stmt->execute([$companyId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);
}

function handleAuditHistory() {
    $vendorId = $_GET['id'] ?? null;

    if (!$vendorId) {
        throw new Exception('Vendor ID required');
    }

    $history = getAuditTrail('vendor', $vendorId, 50);

    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
}
