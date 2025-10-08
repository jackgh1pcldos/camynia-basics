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

    $where = ['er.company_id = :company_id'];
    $params = [':company_id' => $companyId];

    if (!empty($_GET['status'])) {
        $where[] = 'er.status = :status';
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['date_from'])) {
        $where[] = 'er.created_at >= :date_from';
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(er.report_number LIKE :search OR er.report_name LIKE :search OR er.business_purpose LIKE :search)';
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            er.*,
            CONCAT(u.first_name, ' ', u.last_name) as employee_name
        FROM expense_reports er
        LEFT JOIN users u ON er.employee_user_id = u.id
        WHERE $whereClause
        ORDER BY er.created_at DESC, er.id DESC
    ");

    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $reports
    ]);
}

function handleCreate() {
    global $pdo;
    $currentUser = currentUser();
    $companyId = $currentUser['company_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['line_items'])) {
        throw new Exception('At least one expense line is required');
    }

    $total = 0;
    foreach ($input['line_items'] as $line) {
        $total += $line['amount'] ?? 0;
    }

    $pdo->beginTransaction();

    try {
        $status = ($input['submit'] ?? false) ? 'submitted' : 'draft';
        $reportNumber = null;

        if ($status === 'submitted') {
            $year = date('Y');
            $stmt = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING(report_number, -5) AS UNSIGNED)) as max_num
                FROM expense_reports
                WHERE company_id = ? AND report_number LIKE ?
            ");
            $stmt->execute([$companyId, "EXP-{$year}-%"]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextNum = ($result['max_num'] ?? 0) + 1;
            $reportNumber = sprintf("EXP-%s-%05d", $year, $nextNum);
        }

        $stmt = $pdo->prepare("
            INSERT INTO expense_reports (
                company_id, employee_user_id, report_number, report_name,
                business_purpose, total_amount, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $companyId,
            $currentUser['id'],
            $reportNumber,
            $input['purpose'] ?? 'Expense Report',
            $input['purpose'] ?? null,
            $total,
            $status,
            $currentUser['id']
        ]);

        $reportId = $pdo->lastInsertId();

        $lineNumber = 1;
        foreach ($input['line_items'] as $line) {
            $stmt = $pdo->prepare("
                INSERT INTO expense_report_lines (
                    expense_report_id, line_number, expense_date, expense_category,
                    description, amount
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $reportId,
                $lineNumber,
                $line['expense_date'],
                $line['expense_category'],
                $line['description'],
                $line['amount']
            ]);

            $lineNumber++;
        }

        $pdo->commit();

        logFinanceAudit(
            'expense_report',
            $reportId,
            'created',
            $reportNumber,
            "Expense report created - {$input['purpose']}",
            null, null, null, null,
            ['total_amount' => $total, 'line_count' => count($input['line_items']), 'status' => $status]
        );

        echo json_encode([
            'success' => true,
            'message' => $status === 'submitted' ? "Expense report {$reportNumber} submitted" : 'Expense report saved as draft',
            'id' => $reportId
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
