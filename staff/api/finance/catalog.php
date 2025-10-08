<?php
require_once __DIR__ . '/../../../includes/core/bootstrap.php';

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

    $where = ['company_id = :company_id'];
    $params = [':company_id' => $companyId];

    // Filters
    if (!empty($_GET['item_type'])) {
        $where[] = 'item_type = :item_type';
        $params[':item_type'] = $_GET['item_type'];
    }

    if (isset($_GET['is_inventory_item']) && $_GET['is_inventory_item'] !== '') {
        $where[] = 'is_inventory_item = :is_inventory_item';
        $params[':is_inventory_item'] = $_GET['is_inventory_item'];
    }

    if (!empty($_GET['status'])) {
        $where[] = 'status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(item_code LIKE :search OR item_name LIKE :search OR description LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT *
        FROM catalog_items
        WHERE $whereClause
        ORDER BY item_name ASC
    ");

    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $items
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $itemId = $_GET['id'] ?? null;

    if (!$itemId) {
        throw new Exception('Item ID required');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM catalog_items
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([$itemId, $companyId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Catalog item not found');
    }

    echo json_encode([
        'success' => true,
        'data' => $item
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($input['item_code']) || empty($input['item_name'])) {
        throw new Exception('Item code and name are required');
    }

    // Check for duplicate code
    $stmt = $pdo->prepare("SELECT id FROM catalog_items WHERE item_code = ? AND company_id = ?");
    $stmt->execute([$input['item_code'], $companyId]);
    if ($stmt->fetch()) {
        throw new Exception('Item code already exists');
    }

    // Insert item
    $stmt = $pdo->prepare("
        INSERT INTO catalog_items (
            company_id, item_code, item_name, description, uom,
            standard_cost, list_price, currency_code,
            gl_account_code,
            is_taxable, tax_code,
            is_inventory_item, reorder_point, reorder_qty, lead_time_days,
            track_serial_numbers, track_lot_numbers,
            preferred_vendor_id,
            status, created_by
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?,
            ?, ?
        )
    ");

    $stmt->execute([
        $companyId,
        $input['item_code'],
        $input['item_name'],
        $input['description'] ?? null,
        $input['uom'] ?? 'EA',

        $input['standard_cost'] ?? 0,
        $input['list_price'] ?? 0,
        $input['currency_code'] ?? 'USD',

        $input['gl_account_code'] ?? null,

        $input['is_taxable'] ?? 1,
        $input['tax_code'] ?? null,

        $input['is_inventory_item'] ?? 0,
        $input['reorder_point'] ?? 0,
        $input['reorder_qty'] ?? 0,
        $input['lead_time_days'] ?? 0,

        $input['track_serial_numbers'] ?? 0,
        $input['track_lot_numbers'] ?? 0,

        $input['preferred_vendor_id'] ?? null,

        $input['status'] ?? 'active',
        $currentUser['id']
    ]);

    $itemId = $pdo->lastInsertId();

    // **AUDIT LOG** - Item created
    logFinanceAudit(
        'catalog_item',
        $itemId,
        'created',
        $input['item_code'],
        "Catalog item '{$input['item_name']}' ({$input['item_code']}) created",
        null,
        null,
        null,
        null,
        [
            'item_name' => $input['item_name'],
            'is_inventory' => $input['is_inventory_item'] ?? 0,
            'status' => $input['status'] ?? 'active'
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Catalog item created successfully',
        'id' => $itemId
    ]);
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $itemId = $input['id'] ?? null;
    if (!$itemId) {
        throw new Exception('Item ID required');
    }

    // Get current item data for audit trail
    $stmt = $pdo->prepare("SELECT * FROM catalog_items WHERE id = ? AND company_id = ?");
    $stmt->execute([$itemId, $companyId]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        throw new Exception('Catalog item not found');
    }

    // Update item
    $stmt = $pdo->prepare("
        UPDATE catalog_items SET
            item_code = ?,
            item_name = ?,
            description = ?,
            uom = ?,
            standard_cost = ?,
            list_price = ?,
            currency_code = ?,
            gl_account_code = ?,
            is_taxable = ?,
            tax_code = ?,
            is_inventory_item = ?,
            reorder_point = ?,
            reorder_qty = ?,
            lead_time_days = ?,
            track_serial_numbers = ?,
            track_lot_numbers = ?,
            preferred_vendor_id = ?,
            status = ?
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([
        $input['item_code'],
        $input['item_name'],
        $input['description'] ?? null,
        $input['uom'] ?? 'EA',
        $input['standard_cost'] ?? 0,
        $input['list_price'] ?? 0,
        $input['currency_code'] ?? 'USD',
        $input['gl_account_code'] ?? null,
        $input['is_taxable'] ?? 1,
        $input['tax_code'] ?? null,
        $input['is_inventory_item'] ?? 0,
        $input['reorder_point'] ?? 0,
        $input['reorder_qty'] ?? 0,
        $input['lead_time_days'] ?? 0,
        $input['track_serial_numbers'] ?? 0,
        $input['track_lot_numbers'] ?? 0,
        $input['preferred_vendor_id'] ?? null,
        $input['status'] ?? 'active',
        $itemId,
        $companyId
    ]);

    // **AUDIT LOG** - Track all field changes
    $fieldsToTrack = [
        'item_code', 'item_name', 'description', 'uom',
        'standard_cost', 'list_price', 'status', 'is_inventory_item',
        'gl_account_code'
    ];

    logFieldChanges(
        'catalog_item',
        $itemId,
        $input['item_code'],
        $oldData,
        $input,
        $fieldsToTrack
    );

    echo json_encode([
        'success' => true,
        'message' => 'Catalog item updated successfully'
    ]);
}

function handleDelete() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $itemId = $input['id'] ?? null;
    if (!$itemId) {
        throw new Exception('Item ID required');
    }

    // Get item for audit
    $stmt = $pdo->prepare("SELECT item_code, item_name, status FROM catalog_items WHERE id = ? AND company_id = ?");
    $stmt->execute([$itemId, $companyId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Catalog item not found');
    }

    // Soft delete (set to inactive)
    $stmt = $pdo->prepare("UPDATE catalog_items SET status = 'inactive' WHERE id = ? AND company_id = ?");
    $stmt->execute([$itemId, $companyId]);

    // **AUDIT LOG** - Item deactivated
    logFinanceAudit(
        'catalog_item',
        $itemId,
        'deleted',
        $item['item_code'],
        "Catalog item '{$item['item_name']}' ({$item['item_code']}) deactivated",
        'status',
        $item['status'],
        'inactive'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Catalog item deactivated successfully'
    ]);
}
