<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

header('Content-Type: application/json');

// This endpoint is public - no authentication required
// But we need company context from subdomain or parameter

$companyId = null;

// Try to get company from subdomain or parameter
if (isset($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
    $parts = explode('.', $host);

    // If subdomain exists, try to find company
    if (count($parts) > 2) {
        $subdomain = $parts[0];

        $stmt = $pdo->prepare("SELECT id FROM companies WHERE subdomain = ? AND status = 'active'");
        $stmt->execute([$subdomain]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($company) {
            $companyId = $company['id'];
        }
    }
}

// Fallback: allow company_id parameter for testing
if (!$companyId && isset($_POST['company_id'])) {
    $companyId = $_POST['company_id'];
}

if (!$companyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Company identification required']);
    exit;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'submit';

    switch ($action) {
        case 'submit':
            handleSubmit($pdo, $companyId);
            break;
        case 'queues':
            handleQueues($pdo, $companyId);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleSubmit($pdo, $companyId) {
    // Get input data
    $input = $_POST;

    // Validate required fields
    $required = ['contact_name', 'contact_email', 'subject', 'description'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    // Validate email
    if (!filter_var($input['contact_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Generate ticket number
    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT ticket_number FROM tickets
        WHERE company_id = ? AND ticket_number LIKE ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$companyId, "TKT-$year-%"]);
    $lastTicket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lastTicket) {
        $lastNumber = intval(substr($lastTicket['ticket_number'], -5));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    $ticketNumber = sprintf("TKT-%s-%05d", $year, $newNumber);

    // Get default priority (lowest)
    $stmt = $pdo->query("SELECT id FROM ticket_priorities ORDER BY level ASC LIMIT 1");
    $defaultPriority = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create ticket
    $stmt = $pdo->prepare("
        INSERT INTO tickets (
            company_id, ticket_number, subject, description,
            contact_name, contact_email, contact_phone,
            queue_id, priority_id, status, source
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'web', 'web')
    ");

    $stmt->execute([
        $companyId,
        $ticketNumber,
        $input['subject'],
        $input['description'],
        $input['contact_name'],
        $input['contact_email'],
        $input['contact_phone'] ?? null,
        $input['queue_id'] ?? null,
        $defaultPriority['id'] ?? null,
        'new'
    ]);

    $ticketId = $pdo->lastInsertId();

    // Handle file uploads if any
    if (!empty($_FILES['attachments'])) {
        handleAttachments($pdo, $ticketId, $_FILES['attachments']);
    }

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO ticket_activity_log (ticket_id, activity_type, description)
        VALUES (?, 'created', 'Ticket created via web form')
    ");
    $stmt->execute([$ticketId]);

    echo json_encode([
        'success' => true,
        'message' => 'Ticket submitted successfully',
        'ticket_number' => $ticketNumber,
        'ticket_id' => $ticketId
    ]);
}

function handleQueues($pdo, $companyId) {
    $stmt = $pdo->prepare("
        SELECT id, name, description, icon, color
        FROM ticket_queues
        WHERE company_id = ? AND is_active = 1
        ORDER BY sort_order, name
    ");
    $stmt->execute([$companyId]);
    $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $queues
    ]);
}

function handleAttachments($pdo, $ticketId, $files) {
    $uploadDir = __DIR__ . '/../uploads/tickets/' . $ticketId . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];

        if ($fileSize > 0) {
            $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            $uniqueFileName = time() . '_' . $safeFileName;
            $uploadPath = $uploadDir . $uniqueFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_attachments (ticket_id, filename, file_path, file_size, uploaded_by_type)
                    VALUES (?, ?, ?, ?, 'customer')
                ");
                $stmt->execute([
                    $ticketId,
                    $safeFileName,
                    '/uploads/tickets/' . $ticketId . '/' . $uniqueFileName,
                    $fileSize
                ]);
            }
        }
    }
}
