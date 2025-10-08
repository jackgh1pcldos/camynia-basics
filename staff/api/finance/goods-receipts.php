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

    $where = ['gr.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    if (!empty($_GET['status'])) {
        $where[] = 'gr.status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['po_number'])) {
        $where[] = 'po.po_number LIKE :po_number';
        $params[':po_number'] = '%' . $_GET['po_number'] . '%';
    }

    if (!empty($_GET['date_from'])) {
        $where[] = 'gr.receipt_date >= :date_from';
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['search'])) {
        $where[] = 'gr.grn_number LIKE :search';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            gr.*,
            po.po_number,
            v.vendor_name,
            CONCAT(u.first_name, ' ', u.last_name) as receiver_name
        FROM goods_receipts gr
        LEFT JOIN purchase_orders po ON gr.po_id = po.id
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON gr.received_by_user_id = u.id
        WHERE $whereClause
        ORDER BY gr.receipt_date DESC, gr.id DESC
    ");

    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $receipts
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $grId = $_GET['id'] ?? null;

    if (!$grId) {
        throw new Exception('Receipt ID required');
    }

    $stmt = $pdo->prepare("
        SELECT
            gr.*,
            po.po_number,
            v.vendor_name,
            CONCAT(u.first_name, ' ', u.last_name) as receiver_name
        FROM goods_receipts gr
        LEFT JOIN purchase_orders po ON gr.po_id = po.id
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON gr.received_by_user_id = u.id
        WHERE gr.id = ? AND gr.company_id = ?
    ");

    $stmt->execute([$grId, $companyId]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        throw new Exception('Goods receipt not found');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM goods_receipt_lines
        WHERE gr_id = ?
        ORDER BY line_number ASC
    ");

    $stmt->execute([$grId]);
    $receipt['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $receipt
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['po_id'])) {
        throw new Exception('Purchase Order is required');
    }

    if (empty($input['line_items']) || !is_array($input['line_items'])) {
        throw new Exception('At least one line item is required');
    }

    // Check SoD violation - receiver cannot be the PO creator
    $violation = checkSoDViolation('po_receiver', 'purchase_order', $input['po_id']);
    if ($violation) {
        throw new Exception('Segregation of Duties violation: You cannot receive items for a PO you created');
    }

    // Get PO details
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND company_id = ?");
    $stmt->execute([$input['po_id'], $companyId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        throw new Exception('Purchase Order not found');
    }

    if ($po['status'] !== 'sent' && $po['status'] !== 'partially_received' && $po['status'] !== 'approved') {
        throw new Exception('Purchase Order must be sent or approved to receive items');
    }

    $pdo->beginTransaction();

    try {
        $status = $input['status'] ?? 'draft';
        $grnNumber = null;

        if ($status === 'completed') {
            $year = date('Y');
            $stmt = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING(grn_number, -5) AS UNSIGNED)) as max_num
                FROM goods_receipts
                WHERE company_id = ? AND grn_number LIKE ?
            ");
            $stmt->execute([$companyId, "GRN-{$year}-%"]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextNum = ($result['max_num'] ?? 0) + 1;
            $grnNumber = sprintf("GRN-%s-%05d", $year, $nextNum);
        }

        $stmt = $pdo->prepare("
            INSERT INTO goods_receipts (
                company_id, po_id, grn_number, receipt_date,
                received_by_user_id, warehouse_id, delivery_note_number,
                notes, status, created_by
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
        ");

        $stmt->execute([
            $companyId,
            $input['po_id'],
            $grnNumber,
            $input['receipt_date'] ?? date('Y-m-d'),

            $input['received_by_user_id'] ?? $currentUser['id'],
            $input['warehouse_id'] ?? null,
            $input['delivery_note_number'] ?? null,

            $input['notes'] ?? null,
            $status,
            $currentUser['id']
        ]);

        $grId = $pdo->lastInsertId();

        // Insert line items and update PO line quantities
        $lineNumber = 1;
        foreach ($input['line_items'] as $line) {
            $stmt = $pdo->prepare("
                INSERT INTO goods_receipt_lines (
                    gr_id, line_number, po_line_id, item_description,
                    received_quantity, unit_price, bin_location,
                    serial_batch_number
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?
                )
            ");

            $stmt->execute([
                $grId,
                $lineNumber,
                $line['po_line_id'],
                $line['item_description'],
                $line['received_quantity'],
                $line['unit_price'],
                $line['bin_location'] ?? null,
                $line['serial_batch_number'] ?? null
            ]);

            // Update PO line received quantity
            if ($status === 'completed') {
                $stmt = $pdo->prepare("
                    UPDATE purchase_order_lines
                    SET received_quantity = COALESCE(received_quantity, 0) + ?
                    WHERE id = ?
                ");
                $stmt->execute([$line['received_quantity'], $line['po_line_id']]);
            }

            $lineNumber++;
        }

        // Update PO status if fully received
        if ($status === 'completed') {
            $stmt = $pdo->prepare("
                SELECT
                    SUM(quantity) as total_qty,
                    SUM(COALESCE(received_quantity, 0)) as total_received
                FROM purchase_order_lines
                WHERE po_id = ?
            ");
            $stmt->execute([$input['po_id']]);
            $poStatus = $stmt->fetch(PDO::FETCH_ASSOC);

            $newPOStatus = 'partially_received';
            if ($poStatus['total_received'] >= $poStatus['total_qty']) {
                $newPOStatus = 'fully_received';
            }

            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
            $stmt->execute([$newPOStatus, $input['po_id']]);
        }

        $pdo->commit();

        logFinanceAudit(
            'goods_receipt',
            $grId,
            'created',
            $grnNumber,
            "Goods receipt created for PO {$po['po_number']}",
            null,
            null,
            null,
            null,
            [
                'po_number' => $po['po_number'],
                'line_count' => count($input['line_items']),
                'status' => $status
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => $status === 'completed' ? "Goods receipt {$grnNumber} completed successfully" : 'Goods receipt saved as draft',
            'id' => $grId,
            'grn_number' => $grnNumber
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
