<?php
/**
 * Finance Module Audit Trail Helper
 *
 * Provides comprehensive audit logging for ALL finance transactions
 * NEVER update or delete audit records - append only!
 */

/**
 * Log a finance audit event
 *
 * @param string $entityType Type of entity (vendor, requisition, purchase_order, etc)
 * @param int $entityId Database ID of the entity
 * @param string $action Action performed (created, updated, approved, etc)
 * @param string|null $entityNumber Human-readable number (e.g., PO-2025-00123)
 * @param string $description Human-readable description of what happened
 * @param string|null $fieldName Specific field that changed (optional)
 * @param mixed $oldValue Previous value (optional)
 * @param mixed $newValue New value (optional)
 * @param float|null $amountDelta Change in amount (optional)
 * @param array $metadata Additional metadata as associative array (optional)
 * @param string|null $signature E-signature data (optional)
 * @param int|null $approvalLevel Approval level if this is an approval (optional)
 * @return bool Success
 */
function logFinanceAudit(
    string $entityType,
    int $entityId,
    string $action,
    ?string $entityNumber = null,
    string $description = '',
    ?string $fieldName = null,
    $oldValue = null,
    $newValue = null,
    ?float $amountDelta = null,
    array $metadata = [],
    ?string $signature = null,
    ?int $approvalLevel = null
): bool {
    global $pdo;

    try {
        $currentUser = currentUser();
        $companyId = $currentUser['company_id'] ?? null;
        $userId = $currentUser['id'] ?? null;

        if (!$companyId) {
            error_log("Finance Audit: Cannot log without company context");
            return false;
        }

        // Convert values to strings for storage
        $oldValueStr = is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : (string)$oldValue;
        $newValueStr = is_array($newValue) || is_object($newValue) ? json_encode($newValue) : (string)$newValue;

        // Capture request details
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $requestUrl = $_SERVER['REQUEST_URI'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $sessionId = session_id();

        // Capture request payload for POST/PUT/DELETE
        $requestPayload = null;
        if (in_array($requestMethod, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $rawInput = file_get_contents('php://input');
            if ($rawInput) {
                $requestPayload = json_decode($rawInput, true);
                // Mask sensitive fields
                if (is_array($requestPayload)) {
                    $sensitiveFields = ['password', 'bank_account_number', 'bank_routing_number', 'tax_id', 'ssn'];
                    foreach ($sensitiveFields as $field) {
                        if (isset($requestPayload[$field])) {
                            $requestPayload[$field] = '***MASKED***';
                        }
                    }
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO finance_audit_log (
                company_id, user_id, session_id,
                entity_type, entity_id, entity_number,
                action, field_name, old_value, new_value,
                amount_delta, description, signature_data, approval_level,
                ip_address, user_agent,
                request_method, request_url, request_payload,
                metadata
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?
            )
        ");

        $metadataJson = !empty($metadata) ? json_encode($metadata) : null;
        $requestPayloadJson = !empty($requestPayload) ? json_encode($requestPayload) : null;

        $stmt->execute([
            $companyId, $userId, $sessionId,
            $entityType, $entityId, $entityNumber,
            $action, $fieldName, $oldValueStr, $newValueStr,
            $amountDelta, $description, $signature, $approvalLevel,
            $ipAddress, $userAgent,
            $requestMethod, $requestUrl, $requestPayloadJson,
            $metadataJson
        ]);

        return true;

    } catch (Exception $e) {
        error_log("Finance Audit Logging Failed: " . $e->getMessage());
        // Don't throw - we don't want audit failures to break business logic
        return false;
    }
}

/**
 * Check for Segregation of Duties (SoD) violation
 *
 * @param string $violationType Type of violation to check
 * @param string $entityType Entity type being checked
 * @param int $entityId Entity ID
 * @param string|null $entityNumber Entity number for reference
 * @param int|null $conflictingUserId The other user involved in the conflict
 * @return bool True if violation detected
 */
function checkSoDViolation(
    string $violationType,
    string $entityType,
    int $entityId,
    ?string $entityNumber = null,
    ?int $conflictingUserId = null
): bool {
    global $pdo;

    try {
        $currentUser = currentUser();
        $companyId = $currentUser['company_id'];
        $userId = $currentUser['id'];

        $hasViolation = false;

        // Check based on violation type
        switch ($violationType) {
            case 'requester_approver':
                // Check if current user is trying to approve their own requisition
                $stmt = $pdo->prepare("
                    SELECT requested_by_user_id
                    FROM requisitions
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$entityId, $companyId]);
                $requester = $stmt->fetchColumn();
                $hasViolation = ($requester == $userId);
                break;

            case 'po_receiver':
                // Check if PO creator is trying to receive their own PO
                $stmt = $pdo->prepare("
                    SELECT buyer_user_id
                    FROM purchase_orders
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$entityId, $companyId]);
                $buyer = $stmt->fetchColumn();
                $hasViolation = ($buyer == $userId);
                break;

            case 'invoice_poster_payer':
                // Check if same person posted invoice and is trying to pay it
                $stmt = $pdo->prepare("
                    SELECT created_by
                    FROM ap_invoices
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$entityId, $companyId]);
                $poster = $stmt->fetchColumn();
                $hasViolation = ($poster == $userId);
                break;
        }

        // Log violation if detected
        if ($hasViolation) {
            $stmt = $pdo->prepare("
                INSERT INTO sod_violations_log (
                    company_id, violation_type, entity_type, entity_id, entity_number,
                    user_id, conflicting_user_id, detection_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'automatic')
            ");
            $stmt->execute([
                $companyId, $violationType, $entityType, $entityId, $entityNumber,
                $userId, $conflictingUserId
            ]);
        }

        return $hasViolation;

    } catch (Exception $e) {
        error_log("SoD Check Failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log data access for GDPR compliance
 *
 * @param string $dataType Type of data accessed
 * @param string $tableName Table name
 * @param int $recordId Record ID
 * @param array $fieldsAccessed Array of field names accessed
 * @param string|null $reason Reason for access
 * @return bool Success
 */
function logDataAccess(
    string $dataType,
    string $tableName,
    int $recordId,
    array $fieldsAccessed,
    ?string $reason = null
): bool {
    global $pdo;

    try {
        $currentUser = currentUser();
        $companyId = $currentUser['company_id'];
        $userId = $currentUser['id'];

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO data_access_log (
                company_id, accessed_by_user_id, data_type, table_name, record_id,
                fields_accessed, access_reason, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $companyId, $userId, $dataType, $tableName, $recordId,
            json_encode($fieldsAccessed), $reason, $ipAddress, $userAgent
        ]);

        return true;

    } catch (Exception $e) {
        error_log("Data Access Logging Failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Track field-level changes for audit
 * Compares old and new arrays and logs each changed field
 *
 * @param string $entityType Entity type
 * @param int $entityId Entity ID
 * @param string $entityNumber Entity number
 * @param array $oldData Old data array
 * @param array $newData New data array
 * @param array $fieldsToTrack Fields to track (empty = track all)
 * @return int Number of fields logged
 */
function logFieldChanges(
    string $entityType,
    int $entityId,
    string $entityNumber,
    array $oldData,
    array $newData,
    array $fieldsToTrack = []
): int {
    $changesLogged = 0;

    // If no specific fields specified, track all changed fields
    if (empty($fieldsToTrack)) {
        $fieldsToTrack = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    }

    foreach ($fieldsToTrack as $field) {
        $oldValue = $oldData[$field] ?? null;
        $newValue = $newData[$field] ?? null;

        // Only log if value actually changed
        if ($oldValue !== $newValue) {
            $amountDelta = null;

            // Calculate delta for numeric fields
            if (is_numeric($oldValue) && is_numeric($newValue)) {
                $amountDelta = $newValue - $oldValue;
            }

            logFinanceAudit(
                $entityType,
                $entityId,
                'updated',
                $entityNumber,
                ucfirst(str_replace('_', ' ', $field)) . " changed",
                $field,
                $oldValue,
                $newValue,
                $amountDelta
            );

            $changesLogged++;
        }
    }

    return $changesLogged;
}

/**
 * Get audit trail for an entity
 *
 * @param string $entityType Entity type
 * @param int $entityId Entity ID
 * @param int $limit Max records to return
 * @return array Audit trail records
 */
function getAuditTrail(string $entityType, int $entityId, int $limit = 100): array {
    global $pdo;

    try {
        $currentUser = currentUser();
        $companyId = $currentUser['company_id'];

        $stmt = $pdo->prepare("
            SELECT
                fal.*,
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                u.email as user_email
            FROM finance_audit_log fal
            LEFT JOIN users u ON fal.user_id = u.id
            WHERE fal.company_id = ?
                AND fal.entity_type = ?
                AND fal.entity_id = ?
            ORDER BY fal.created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$companyId, $entityType, $entityId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Get Audit Trail Failed: " . $e->getMessage());
        return [];
    }
}
