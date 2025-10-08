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
        case 'approve':
            handleApprove();
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

    $where = ['inv.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    if (!empty($_GET['status'])) {
        $where[] = 'inv.status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['match_status'])) {
        $where[] = 'inv.match_status = :match_status';
        $params[':match_status'] = $_GET['match_status'];
    }

    if (!empty($_GET['vendor_id'])) {
        $where[] = 'inv.vendor_id = :vendor_id';
        $params[':vendor_id'] = $_GET['vendor_id'];
    }

    if (!empty($_GET['date_from'])) {
        $where[] = 'inv.invoice_date >= :date_from';
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['due_from'])) {
        $where[] = 'inv.due_date >= :due_from';
        $params[':due_from'] = $_GET['due_from'];
    }

    if (!empty($_GET['search'])) {
        $where[] = 'inv.vendor_invoice_number LIKE :search';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            inv.*,
            v.vendor_name,
            po.po_number
        FROM ap_invoices inv
        LEFT JOIN vendors v ON inv.vendor_id = v.id
        LEFT JOIN purchase_orders po ON inv.po_id = po.id
        WHERE $whereClause
        ORDER BY inv.invoice_date DESC, inv.id DESC
    ");

    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $invoices
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $invId = $_GET['id'] ?? null;

    if (!$invId) {
        throw new Exception('Invoice ID required');
    }

    $stmt = $pdo->prepare("
        SELECT
            inv.*,
            v.vendor_name,
            po.po_number
        FROM ap_invoices inv
        LEFT JOIN vendors v ON inv.vendor_id = v.id
        LEFT JOIN purchase_orders po ON inv.po_id = po.id
        WHERE inv.id = ? AND inv.company_id = ?
    ");

    $stmt->execute([$invId, $companyId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception('Invoice not found');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM ap_invoice_lines
        WHERE invoice_id = ?
        ORDER BY line_number ASC
    ");

    $stmt->execute([$invId]);
    $invoice['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $invoice
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

    if (empty($input['vendor_invoice_number'])) {
        throw new Exception('Vendor invoice number is required');
    }

    if (empty($input['line_items']) || !is_array($input['line_items'])) {
        throw new Exception('At least one line item is required');
    }

    // Check for duplicate invoice number
    $stmt = $pdo->prepare("SELECT id FROM ap_invoices WHERE vendor_id = ? AND vendor_invoice_number = ? AND company_id = ?");
    $stmt->execute([$input['vendor_id'], $input['vendor_invoice_number'], $companyId]);
    if ($stmt->fetch()) {
        throw new Exception('Duplicate invoice: This invoice number already exists for this vendor');
    }

    $subtotal = 0;
    $totalTax = 0;

    foreach ($input['line_items'] as $line) {
        $lineTotal = $line['quantity'] * $line['unit_price'];
        $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);
        $subtotal += $lineTotal;
        $totalTax += $lineTax;
    }

    $totalAmount = $subtotal + $totalTax;

    $pdo->beginTransaction();

    try {
        $matchType = $input['po_id'] ? '3-way' : 'no_po';
        $status = ($input['submit'] ?? false) ? 'pending_match' : 'draft';

        $stmt = $pdo->prepare("
            INSERT INTO ap_invoices (
                company_id, vendor_id, po_id,
                vendor_invoice_number, invoice_date, due_date,
                payment_terms, currency,
                subtotal_amount, tax_amount, total_amount,
                match_type, match_status, status,
                created_by
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, 'pending', ?,
                ?
            )
        ");

        $stmt->execute([
            $companyId,
            $input['vendor_id'],
            $input['po_id'] ?? null,

            $input['vendor_invoice_number'],
            $input['invoice_date'] ?? date('Y-m-d'),
            $input['due_date'] ?? null,

            $input['payment_terms'] ?? 'NET30',
            $input['currency'] ?? 'USD',

            $subtotal,
            $totalTax,
            $totalAmount,

            $matchType,
            $status,

            $currentUser['id']
        ]);

        $invoiceId = $pdo->lastInsertId();

        $lineNumber = 1;
        foreach ($input['line_items'] as $line) {
            $lineTotal = $line['quantity'] * $line['unit_price'];
            $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);

            $stmt = $pdo->prepare("
                INSERT INTO ap_invoice_lines (
                    invoice_id, line_number, po_line_id,
                    item_description, quantity, unit_price,
                    tax_rate, tax_amount, line_total
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?
                )
            ");

            $stmt->execute([
                $invoiceId,
                $lineNumber,
                $line['po_line_id'] ?? null,
                $line['item_description'],
                $line['quantity'],
                $line['unit_price'],
                $line['tax_rate'] ?? 0,
                $lineTax,
                $lineTotal + $lineTax
            ]);

            $lineNumber++;
        }

        $matchResult = null;

        // Perform 3-way matching if PO is linked and invoice is submitted
        if ($input['po_id'] && ($input['submit'] ?? false)) {
            $matchResult = perform3WayMatch($pdo, $invoiceId, $input['po_id']);
        }

        $pdo->commit();

        logFinanceAudit(
            'ap_invoice',
            $invoiceId,
            'created',
            $input['vendor_invoice_number'],
            "AP Invoice {$input['vendor_invoice_number']} created",
            null,
            null,
            null,
            null,
            [
                'vendor_id' => $input['vendor_id'],
                'total_amount' => $totalAmount,
                'match_type' => $matchType,
                'status' => $status
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => $status === 'pending_match' ? 'Invoice submitted for matching' : 'Invoice saved as draft',
            'id' => $invoiceId,
            'match_result' => $matchResult
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function perform3WayMatch($pdo, $invoiceId, $poId) {
    // Get invoice lines
    $stmt = $pdo->prepare("SELECT * FROM ap_invoice_lines WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $invoiceLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get PO lines
    $stmt = $pdo->prepare("SELECT * FROM purchase_order_lines WHERE po_id = ?");
    $stmt->execute([$poId]);
    $poLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get receipt lines for this PO
    $stmt = $pdo->prepare("
        SELECT grl.*, gr.status as gr_status
        FROM goods_receipt_lines grl
        INNER JOIN goods_receipts gr ON grl.gr_id = gr.id
        WHERE grl.po_line_id IN (SELECT id FROM purchase_order_lines WHERE po_id = ?)
        AND gr.status = 'completed'
    ");
    $stmt->execute([$poId]);
    $receiptLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasReceipts = count($receiptLines) > 0;

    $priceVariance = 0;
    $quantityVariance = 0;
    $matchStatus = 'matched';
    $matchMessage = '3-way match successful';

    // Configurable tolerance (5% for price, 2% for quantity)
    $priceTolerance = 0.05;
    $qtyTolerance = 0.02;

    foreach ($invoiceLines as $invLine) {
        if (!$invLine['po_line_id']) continue;

        // Find matching PO line
        $poLine = array_filter($poLines, fn($pl) => $pl['id'] == $invLine['po_line_id']);
        $poLine = reset($poLine);

        if (!$poLine) {
            $matchStatus = 'exception';
            $matchMessage = 'Invoice line does not match any PO line';
            continue;
        }

        // Check price variance
        $priceVarianceAmt = abs($invLine['unit_price'] - $poLine['unit_price']);
        $priceVariancePct = $poLine['unit_price'] > 0 ? ($priceVarianceAmt / $poLine['unit_price']) : 0;

        if ($priceVariancePct > $priceTolerance) {
            $matchStatus = 'variance';
            $priceVariance += $priceVarianceAmt * $invLine['quantity'];
            $matchMessage = 'Price variance exceeds tolerance';
        }

        // Check quantity variance (against received quantity if receipts exist)
        if ($hasReceipts) {
            $receivedQty = 0;
            foreach ($receiptLines as $rcptLine) {
                if ($rcptLine['po_line_id'] == $invLine['po_line_id']) {
                    $receivedQty += $rcptLine['received_quantity'];
                }
            }

            $qtyVarianceAmt = abs($invLine['quantity'] - $receivedQty);
            $qtyVariancePct = $receivedQty > 0 ? ($qtyVarianceAmt / $receivedQty) : 0;

            if ($qtyVariancePct > $qtyTolerance) {
                $matchStatus = 'variance';
                $quantityVariance += $qtyVarianceAmt;
                $matchMessage = 'Quantity variance exceeds tolerance';
            }
        }
    }

    // Update invoice with match result
    $stmt = $pdo->prepare("
        UPDATE ap_invoices SET
            match_status = ?,
            price_variance = ?,
            quantity_variance = ?,
            variance_tolerance_passed = ?,
            status = ?
        WHERE id = ?
    ");

    $tolerancePassed = ($matchStatus === 'matched');
    $newStatus = $tolerancePassed ? 'matched' : 'variance';

    $stmt->execute([
        $matchStatus,
        $priceVariance,
        $quantityVariance,
        $tolerancePassed ? 1 : 0,
        $newStatus,
        $invoiceId
    ]);

    // Log matching result
    logFinanceAudit(
        'ap_invoice',
        $invoiceId,
        'matched',
        null,
        "3-way match performed: {$matchMessage}",
        'match_status',
        'pending',
        $matchStatus,
        null,
        [
            'price_variance' => $priceVariance,
            'quantity_variance' => $quantityVariance,
            'tolerance_passed' => $tolerancePassed
        ]
    );

    return [
        'status' => $matchStatus,
        'message' => $matchMessage,
        'price_variance' => $priceVariance,
        'quantity_variance' => $quantityVariance,
        'tolerance_passed' => $tolerancePassed
    ];
}

function handleApprove() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $invId = $input['id'] ?? null;
    if (!$invId) {
        throw new Exception('Invoice ID required');
    }

    $stmt = $pdo->prepare("SELECT * FROM ap_invoices WHERE id = ? AND company_id = ?");
    $stmt->execute([$invId, $companyId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception('Invoice not found');
    }

    if ($invoice['status'] !== 'matched' && $invoice['status'] !== 'variance') {
        throw new Exception('Only matched or variance invoices can be approved');
    }

    $stmt = $pdo->prepare("
        UPDATE ap_invoices SET
            status = 'approved',
            approved_date = NOW(),
            approved_by_user_id = ?
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([$currentUser['id'], $invId, $companyId]);

    logFinanceAudit(
        'ap_invoice',
        $invId,
        'approved',
        $invoice['vendor_invoice_number'],
        "Invoice {$invoice['vendor_invoice_number']} approved for payment",
        'status',
        $invoice['status'],
        'approved'
    );

    echo json_encode([
        'success' => true,
        'message' => "Invoice {$invoice['vendor_invoice_number']} approved for payment"
    ]);
}
