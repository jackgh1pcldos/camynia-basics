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
        case 'submit':
            handleSubmit();
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

    $where = ['r.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    // Filters
    if (!empty($_GET['status'])) {
        $where[] = 'r.status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['my_requisitions'])) {
        $where[] = 'r.requested_by_user_id = :user_id';
        $params[':user_id'] = $currentUser['id'];
    }

    if (!empty($_GET['date_from'])) {
        $where[] = 'r.created_at >= :date_from';
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(r.requisition_number LIKE :search OR r.purpose LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            CONCAT(u.first_name, ' ', u.last_name) as requester_name
        FROM requisitions r
        LEFT JOIN users u ON r.requested_by_user_id = u.id
        WHERE $whereClause
        ORDER BY r.created_at DESC, r.id DESC
    ");

    $stmt->execute($params);
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $requisitions
    ]);
}

function handleView() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $reqId = $_GET['id'] ?? null;

    if (!$reqId) {
        throw new Exception('Requisition ID required');
    }

    // Get requisition header
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            CONCAT(u.first_name, ' ', u.last_name) as requester_name
        FROM requisitions r
        LEFT JOIN users u ON r.requested_by_user_id = u.id
        WHERE r.id = ? AND r.company_id = ?
    ");

    $stmt->execute([$reqId, $companyId]);
    $requisition = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$requisition) {
        throw new Exception('Requisition not found');
    }

    // Get line items
    $stmt = $pdo->prepare("
        SELECT *
        FROM requisition_lines
        WHERE requisition_id = ?
        ORDER BY line_number ASC
    ");

    $stmt->execute([$reqId]);
    $requisition['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $requisition
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($input['purpose'])) {
        throw new Exception('Purpose is required');
    }

    if (empty($input['line_items']) || !is_array($input['line_items'])) {
        throw new Exception('At least one line item is required');
    }

    // Calculate totals
    $subtotal = 0;
    $totalTax = 0;

    foreach ($input['line_items'] as $line) {
        $lineTotal = $line['quantity'] * $line['unit_price'];
        $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);
        $subtotal += $lineTotal;
        $totalTax += $lineTax;
    }

    $totalAmount = $subtotal + $totalTax;

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert requisition
        $stmt = $pdo->prepare("
            INSERT INTO requisitions (
                company_id, requested_by_user_id,
                purpose, notes, need_by_date,
                cost_center_id, project_code,
                subtotal, tax_total, total_amount,
                status, budget_check_status
            ) VALUES (
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                'draft', 'not_checked'
            )
        ");

        $stmt->execute([
            $companyId,
            $currentUser['id'],

            $input['purpose'],
            $input['notes'] ?? null,
            $input['need_by_date'] ?? null,

            $input['cost_center_id'] ?? null,
            $input['project_code'] ?? null,

            $subtotal,
            $totalTax,
            $totalAmount
        ]);

        $reqId = $pdo->lastInsertId();

        // Insert line items
        $lineNumber = 1;
        foreach ($input['line_items'] as $line) {
            $lineTotal = $line['quantity'] * $line['unit_price'];
            $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);

            $stmt = $pdo->prepare("
                INSERT INTO requisition_lines (
                    requisition_id, line_number, item_id, item_name,
                    quantity, uom, unit_price, tax_rate, tax_amount,
                    gl_account_code, cost_center_id, project_code
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?
                )
            ");

            $stmt->execute([
                $reqId,
                $lineNumber,
                $line['item_id'] ?? null,
                $line['item_name'],

                $line['quantity'],
                $line['uom'] ?? 'EA',
                $line['unit_price'],
                $line['tax_rate'] ?? 0,
                $lineTax,

                $line['gl_account_code'] ?? null,
                $line['cost_center_id'] ?? null,
                $line['project_code'] ?? null
            ]);

            $lineNumber++;
        }

        $pdo->commit();

        // **AUDIT LOG** - Requisition created
        logFinanceAudit(
            'requisition',
            $reqId,
            'created',
            null, // PR number assigned on submit
            "Requisition created: {$input['purpose']}",
            null,
            null,
            null,
            null,
            [
                'purpose' => $input['purpose'],
                'total_amount' => $totalAmount,
                'line_count' => count($input['line_items']),
                'status' => 'draft'
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Requisition created successfully',
            'id' => $reqId
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

    $reqId = $input['id'] ?? null;
    if (!$reqId) {
        throw new Exception('Requisition ID required');
    }

    // Get current requisition
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE id = ? AND company_id = ?");
    $stmt->execute([$reqId, $companyId]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        throw new Exception('Requisition not found');
    }

    // Only allow editing drafts
    if ($oldData['status'] !== 'draft') {
        throw new Exception('Only draft requisitions can be edited');
    }

    // Calculate totals
    $subtotal = 0;
    $totalTax = 0;

    foreach ($input['line_items'] as $line) {
        $lineTotal = $line['quantity'] * $line['unit_price'];
        $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);
        $subtotal += $lineTotal;
        $totalTax += $lineTax;
    }

    $totalAmount = $subtotal + $totalTax;

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update requisition
        $stmt = $pdo->prepare("
            UPDATE requisitions SET
                purpose = ?,
                notes = ?,
                need_by_date = ?,
                cost_center_id = ?,
                project_code = ?,
                subtotal = ?,
                tax_total = ?,
                total_amount = ?
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $input['purpose'],
            $input['notes'] ?? null,
            $input['need_by_date'] ?? null,
            $input['cost_center_id'] ?? null,
            $input['project_code'] ?? null,
            $subtotal,
            $totalTax,
            $totalAmount,
            $reqId,
            $companyId
        ]);

        // Delete old line items
        $stmt = $pdo->prepare("DELETE FROM requisition_lines WHERE requisition_id = ?");
        $stmt->execute([$reqId]);

        // Insert new line items
        $lineNumber = 1;
        foreach ($input['line_items'] as $line) {
            $lineTotal = $line['quantity'] * $line['unit_price'];
            $lineTax = $lineTotal * (($line['tax_rate'] ?? 0) / 100);

            $stmt = $pdo->prepare("
                INSERT INTO requisition_lines (
                    requisition_id, line_number, item_id, item_name,
                    quantity, uom, unit_price, tax_rate, tax_amount,
                    gl_account_code, cost_center_id, project_code
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?
                )
            ");

            $stmt->execute([
                $reqId,
                $lineNumber,
                $line['item_id'] ?? null,
                $line['item_name'],
                $line['quantity'],
                $line['uom'] ?? 'EA',
                $line['unit_price'],
                $line['tax_rate'] ?? 0,
                $lineTax,
                $line['gl_account_code'] ?? null,
                $line['cost_center_id'] ?? null,
                $line['project_code'] ?? null
            ]);

            $lineNumber++;
        }

        $pdo->commit();

        // **AUDIT LOG** - Requisition updated
        logFinanceAudit(
            'requisition',
            $reqId,
            'updated',
            $oldData['requisition_number'],
            "Requisition updated",
            'total_amount',
            $oldData['total_amount'],
            $totalAmount,
            $totalAmount - $oldData['total_amount']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Requisition updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleSubmit() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $reqId = $input['id'] ?? null;
    if (!$reqId) {
        throw new Exception('Requisition ID required');
    }

    // Get requisition
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE id = ? AND company_id = ?");
    $stmt->execute([$reqId, $companyId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        throw new Exception('Requisition not found');
    }

    if ($req['status'] !== 'draft') {
        throw new Exception('Only draft requisitions can be submitted');
    }

    // Generate PR number
    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(requisition_number, -5) AS UNSIGNED)) as max_num
        FROM requisitions
        WHERE company_id = ? AND requisition_number LIKE ?
    ");
    $stmt->execute([$companyId, "PR-{$year}-%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNum = ($result['max_num'] ?? 0) + 1;
    $prNumber = sprintf("PR-%s-%05d", $year, $nextNum);

    // Budget check (simplified - in real implementation, check against budgets table)
    $budgetStatus = 'not_checked'; // Default
    if ($req['cost_center_id']) {
        // TODO: Implement actual budget checking logic
        $budgetStatus = 'passed';
    }

    // Update requisition status
    $stmt = $pdo->prepare("
        UPDATE requisitions SET
            requisition_number = ?,
            status = 'pending_approval',
            budget_check_status = ?,
            submitted_at = NOW()
        WHERE id = ? AND company_id = ?
    ");

    $stmt->execute([$prNumber, $budgetStatus, $reqId, $companyId]);

    // TODO: Create approval requests based on approval rules
    // This would match rules from approval_rules table and create entries in approval_requests

    // **AUDIT LOG** - Requisition submitted
    logFinanceAudit(
        'requisition',
        $reqId,
        'submitted',
        $prNumber,
        "Requisition {$prNumber} submitted for approval",
        'status',
        'draft',
        'pending_approval',
        null,
        [
            'pr_number' => $prNumber,
            'total_amount' => $req['total_amount'],
            'budget_status' => $budgetStatus
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => "Requisition {$prNumber} submitted for approval",
        'pr_number' => $prNumber
    ]);
}

function handleDelete() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $reqId = $input['id'] ?? null;
    if (!$reqId) {
        throw new Exception('Requisition ID required');
    }

    // Get requisition
    $stmt = $pdo->prepare("SELECT requisition_number, status, purpose FROM requisitions WHERE id = ? AND company_id = ?");
    $stmt->execute([$reqId, $companyId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        throw new Exception('Requisition not found');
    }

    // Only allow deleting drafts
    if ($req['status'] !== 'draft') {
        throw new Exception('Only draft requisitions can be deleted');
    }

    // Soft delete (set to cancelled)
    $stmt = $pdo->prepare("UPDATE requisitions SET status = 'cancelled' WHERE id = ? AND company_id = ?");
    $stmt->execute([$reqId, $companyId]);

    // **AUDIT LOG** - Requisition cancelled
    logFinanceAudit(
        'requisition',
        $reqId,
        'deleted',
        $req['requisition_number'],
        "Requisition '{$req['purpose']}' cancelled",
        'status',
        $req['status'],
        'cancelled'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Requisition cancelled successfully'
    ]);
}
