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
        case 'transaction':
            handleTransaction();
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

    $where = ['s.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    if (!empty($_GET['search'])) {
        $where[] = '(ci.item_code LIKE :search OR ci.item_name LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    if (!empty($_GET['warehouse_id'])) {
        $where[] = 's.warehouse_id = :warehouse_id';
        $params[':warehouse_id'] = $_GET['warehouse_id'];
    }

    if (!empty($_GET['low_stock'])) {
        $where[] = 's.quantity_on_hand < ci.reorder_point';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            s.*,
            ci.item_code,
            ci.item_name,
            ci.reorder_point,
            w.warehouse_name
        FROM inventory_stock s
        LEFT JOIN catalog_items ci ON s.catalog_item_id = ci.id
        LEFT JOIN warehouses w ON s.warehouse_id = w.id
        WHERE $whereClause
        ORDER BY ci.item_name ASC
    ");

    $stmt->execute($params);
    $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $stock
    ]);
}

function handleTransaction() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['catalog_item_id']) || empty($input['warehouse_id']) || !isset($input['quantity'])) {
        throw new Exception('Item ID, warehouse ID, and quantity are required');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO inventory_transactions (
                company_id, catalog_item_id, warehouse_id, transaction_type,
                quantity, unit_cost, reason, created_by
            ) VALUES (?, ?, ?, ?, ?, 0, ?, ?)
        ");

        $stmt->execute([
            $companyId,
            $input['catalog_item_id'],
            $input['warehouse_id'],
            $input['transaction_type'],
            $input['quantity'],
            $input['reason'] ?? null,
            $currentUser['id']
        ]);

        $txnId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO inventory_stock (company_id, catalog_item_id, warehouse_id, quantity_on_hand, total_value)
            VALUES (?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)
        ");

        $stmt->execute([
            $companyId,
            $input['catalog_item_id'],
            $input['warehouse_id'],
            $input['quantity']
        ]);

        $pdo->commit();

        logFinanceAudit(
            'inventory_transaction',
            $txnId,
            'created',
            null,
            "Inventory {$input['transaction_type']} - Qty: {$input['quantity']}",
            null, null, null, null,
            ['item_id' => $input['catalog_item_id'], 'warehouse_id' => $input['warehouse_id'], 'quantity' => $input['quantity']]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Inventory transaction recorded successfully',
            'id' => $txnId
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
