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
        case 'approve':
            handleApprove();
            break;
        case 'send':
            handleSend();
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

    $where = ['po.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    if (!empty($_GET['status'])) {
        $where[] = 'po.status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['vendor_id'])) {
        $where[] = 'po.vendor_id = :vendor_id';
        $params[':vendor_id'] = $_GET['vendor_id'];
    }

    if (!empty($_GET['date_from'])) {
        $where[] = 'po.po_date >= :date_from';
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(po.po_number LIKE :search OR v.vendor_name LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            po.*,
            v.vendor_name,
            CONCAT(u.first_name, ' ', u.last_name) as buyer_name
        FROM purchase_orders po
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON po.buyer_user_id = u.id
        WHERE $whereClause
        ORDER BY po.po_date DESC, po.id DESC
    ");

    $stmt->execute($params);
    $pos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $pos
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $poId = $_GET['id'] ?? null;

    if (!$poId) {
        throw new Exception('PO ID required');
    }

    $stmt = $pdo->prepare("
        SELECT
            po.*,
            v.vendor_name,
            CONCAT(u.first_name, ' ', u.last_name) as buyer_name
        FROM purchase_orders po
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON po.buyer_user_id = u.id
        WHERE po.id = ? AND po.company_id = ?
    ");

    $stmt->execute([$poId, $companyId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        throw new Exception('Purchase order not found');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM purchase_order_lines
        WHERE po_id = ?
        ORDER BY line_number ASC
    ");

    $stmt->execute([$poId]);
    $po['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $po
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['vendor_id'])) {
        throw new Exception('Vendor is required');
    }

    if (empty($input['line_items']) || !is_array($input['line_items'])) {
        throw new Exception('At least one line item is required');
    }

    $subtotal = 0;
    $totalTax = 0;

    foreach ($input['line_items'] as $line) {
        $lineTotal = $line['quantity'] * $line['unit_price'];
        $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);
        $subtotal += $lineTotal;
        $totalTax += $lineTax;
    }

    $shippingCost = $input['shipping_cost'] ?? 0;
    $totalAmount = $subtotal + $totalTax + $shippingCost;

    $pdo->beginTransaction();

    try {
        $status = ($input['approve'] ?? false) ? 'approved' : 'draft';
        $poNumber = null;

        if ($status === 'approved') {
            $year = date('Y');
            $stmt = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING(po_number, -5) AS UNSIGNED)) as max_num
                FROM purchase_orders
                WHERE company_id = ? AND po_number LIKE ?
            ");
            $stmt->execute([$companyId, "PO-{$year}-%"]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextNum = ($result['max_num'] ?? 0) + 1;
            $poNumber = sprintf("PO-%s-%05d", $year, $nextNum);
        }

        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (
                company_id, vendor_id, po_number, po_date,
                expected_delivery_date, payment_terms, shipping_method, shipping_cost,
                buyer_user_id, notes,
                subtotal_amount, tax_amount, total_amount,
                status, created_by
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?
            )
        ");

        $stmt->execute([
            $companyId,
            $input['vendor_id'],
            $poNumber,
            $input['po_date'] ?? date('Y-m-d'),

            $input['expected_delivery_date'] ?? null,
            $input['payment_terms'] ?? 'NET30',
            $input['shipping_method'] ?? 'STANDARD',
            $shippingCost,

            $input['buyer_user_id'] ?? $currentUser['id'],
            $input['notes'] ?? null,

            $subtotal,
            $totalTax,
            $totalAmount,

            $status,
            $currentUser['id']
        ]);

        $poId = $pdo->lastInsertId();

        $lineNumber = 1;
        foreach ($input['line_items'] as $line) {
            $lineTotal = $line['quantity'] * $line['unit_price'];
            $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);

            $stmt = $pdo->prepare("
                INSERT INTO purchase_order_lines (
                    po_id, line_number, catalog_item_id, requisition_line_id,
                    item_description, quantity, uom, unit_price,
                    tax_rate, tax_amount, line_total,
                    expected_delivery_date
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?
                )
            ");

            $stmt->execute([
                $poId,
                $lineNumber,
                $line['catalog_item_id'] ?? null,
                $line['requisition_line_id'] ?? null,

                $line['item_description'],
                $line['quantity'],
                $line['uom'] ?? 'EA',
                $line['unit_price'],

                $line['tax_rate'] ?? 0,
                $lineTax,
                $lineTotal + $lineTax,

                $line['expected_delivery_date'] ?? null
            ]);

            $lineNumber++;
        }

        $pdo->commit();

        logFinanceAudit(
            'purchase_order',
            $poId,
            'created',
            $poNumber,
            "Purchase order created for vendor ID {$input['vendor_id']}",
            null,
            null,
            null,
            null,
            [
                'vendor_id' => $input['vendor_id'],
                'total_amount' => $totalAmount,
                'line_count' => count($input['line_items']),
                'status' => $status
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => $status === 'approved' ? "Purchase order {$poNumber} created and approved" : 'Purchase order created as draft',
            'id' => $poId,
            'po_number' => $poNumber
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleUpdate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $poId = $input['id'] ?? null;
    if (!$poId) {
        throw new Exception('PO ID required');
    }

    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND company_id = ?");
    $stmt->execute([$poId, $companyId]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        throw new Exception('Purchase order not found');
    }

    if ($oldData['status'] !== 'draft') {
        throw new Exception('Only draft purchase orders can be edited');
    }

    $subtotal = 0;
    $totalTax = 0;

    foreach ($input['line_items'] as $line) {
        $lineTotal = $line['quantity'] * $line['unit_price'];
        $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);
        $subtotal += $lineTotal;
        $totalTax += $lineTax;
    }

    $shippingCost = $input['shipping_cost'] ?? 0;
    $totalAmount = $subtotal + $totalTax + $shippingCost;

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE purchase_orders SET
                vendor_id = ?,
                po_date = ?,
                expected_delivery_date = ?,
                payment_terms = ?,
                shipping_method = ?,
                shipping_cost = ?,
                buyer_user_id = ?,
                notes = ?,
                subtotal_amount = ?,
                tax_amount = ?,
                total_amount = ?
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $input['vendor_id'],
            $input['po_date'] ?? date('Y-m-d'),
            $input['expected_delivery_date'] ?? null,
            $input['payment_terms'] ?? 'NET30',
            $input['shipping_method'] ?? 'STANDARD',
            $shippingCost,
            $input['buyer_user_id'] ?? null,
            $input['notes'] ?? null,
            $subtotal,
            $totalTax,
            $totalAmount,
            $poId,
            $companyId
        ]);

        $stmt = $pdo->prepare("DELETE FROM purchase_order_lines WHERE po_id = ?");
        $stmt->execute([$poId]);

        $lineNumber = 1;
        foreach ($input['line_items'] as $line) {
            $lineTotal = $line['quantity'] * $line['unit_price'];
            $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);

            $stmt = $pdo->prepare("
                INSERT INTO purchase_order_lines (
                    po_id, line_number, catalog_item_id, requisition_line_id,
                    item_description, quantity, uom, unit_price,
                    tax_rate, tax_amount, line_total,
                    expected_delivery_date
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?
                )
            ");

            $stmt->execute([
                $poId,
                $lineNumber,
                $line['catalog_item_id'] ?? null,
                $line['requisition_line_id'] ?? null,
                $line['item_description'],
                $line['quantity'],
                $line['uom'] ?? 'EA',
                $line['unit_price'],
                $line['tax_rate'] ?? 0,
                $lineTax,
                $lineTotal + $lineTax,
                $line['expected_delivery_date'] ?? null
            ]);

            $lineNumber++;
        }

        $pdo->commit();

        logFinanceAudit(
            'purchase_order',
            $poId,
            'updated',
            $oldData['po_number'],
            "Purchase order updated",
            'total_amount',
            $oldData['total_amount'],
            $totalAmount,
            $totalAmount - $oldData['total_amount']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Purchase order updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleApprove() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $poId = $input['id'] ?? null;
    if (!$poId) {
        throw new Exception('PO ID required');
    }

    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND company_id = ?");
    $stmt->execute([$poId, $companyId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        throw new Exception('Purchase order not found');
    }

    if ($po['status'] !== 'draft') {
        throw new Exception('Only draft purchase orders can be approved');
    }

    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(po_number, -5) AS UNSIGNED)) as max_num
        FROM purchase_orders
        WHERE company_id = ? AND po_number LIKE ?
    ");
    $stmt->execute([$companyId, "PO-{$year}-%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNum = ($result['max_num'] ?? 0) + 1;
    $poNumber = sprintf("PO-%s-%05d", $year, $nextNum);

    $stmt = $pdo->prepare("
        UPDATE purchase_orders SET
            po_number = ?,
            status = 'approved',
            approved_date = NOW(),
            approved_by_user_id = ?
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([$poNumber, $currentUser['id'], $poId, $companyId]);

    logFinanceAudit(
        'purchase_order',
        $poId,
        'approved',
        $poNumber,
        "Purchase order {$poNumber} approved",
        'status',
        'draft',
        'approved',
        null,
        ['po_number' => $poNumber]
    );

    echo json_encode([
        'success' => true,
        'message' => "Purchase order {$poNumber} approved successfully",
        'po_number' => $poNumber
    ]);
}

function handleSend() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $poId = $input['id'] ?? null;
    if (!$poId) {
        throw new Exception('PO ID required');
    }

    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND company_id = ?");
    $stmt->execute([$poId, $companyId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        throw new Exception('Purchase order not found');
    }

    if ($po['status'] !== 'approved') {
        throw new Exception('Only approved purchase orders can be sent to vendor');
    }

    $stmt = $pdo->prepare("
        UPDATE purchase_orders SET
            status = 'sent',
            sent_date = NOW()
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([$poId, $companyId]);

    logFinanceAudit(
        'purchase_order',
        $poId,
        'sent',
        $po['po_number'],
        "Purchase order {$po['po_number']} sent to vendor",
        'status',
        'approved',
        'sent'
    );

    echo json_encode([
        'success' => true,
        'message' => "Purchase order {$po['po_number']} sent to vendor"
    ]);
}
