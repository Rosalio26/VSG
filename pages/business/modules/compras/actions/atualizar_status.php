<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../../../registration/includes/db.php';

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Verificar permissão
if ($isEmployee) {
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $userId = $employeeId;
    
    $stmt = $mysqli->prepare("
        SELECT can_edit FROM employee_permissions 
        WHERE employee_id = ? AND module IN ('vendas', 'compras')
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $perm = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$perm || !$perm['can_edit']) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para editar']);
        exit;
    }
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
    $userId = $companyId;
}

$id = (int)($_POST['id'] ?? 0);
$newStatusEng = trim($_POST['status'] ?? '');
$newPaymentStatusEng = trim($_POST['payment_status'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Mapear status de inglês para português (DB usa português)
$statusMap = [
    'pending' => 'pendente',
    'confirmed' => 'confirmado',
    'processing' => 'processando',
    'shipped' => 'enviado',
    'delivered' => 'entregue',
    'cancelled' => 'cancelado'
];

$paymentStatusMap = [
    'pending' => 'pendente',
    'paid' => 'pago',
    'partial' => 'parcial',
    'refunded' => 'reembolsado'
];

// Converter para português
$newStatus = '';
$newPaymentStatus = '';

if ($newStatusEng) {
    if (!isset($statusMap[$newStatusEng])) {
        echo json_encode(['success' => false, 'message' => 'Status inválido']);
        exit;
    }
    $newStatus = $statusMap[$newStatusEng];
}

if ($newPaymentStatusEng) {
    if (!isset($paymentStatusMap[$newPaymentStatusEng])) {
        echo json_encode(['success' => false, 'message' => 'Status de pagamento inválido']);
        exit;
    }
    $newPaymentStatus = $paymentStatusMap[$newPaymentStatusEng];
}

try {
    // Verificar se pedido pertence à empresa
    $stmt = $mysqli->prepare("
        SELECT status, payment_status 
        FROM orders 
        WHERE id = ? AND company_id = ? AND deleted_at IS NULL
    ");
    
    $stmt->bind_param('ii', $id, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }
    
    $currentData = $result->fetch_assoc();
    $oldStatus = $currentData['status'];
    $oldPaymentStatus = $currentData['payment_status'];
    $stmt->close();
    
    $mysqli->begin_transaction();
    
    // ============================================================
    // 1. ATUALIZAR STATUS DO PEDIDO
    // ============================================================
    $updates = [];
    $params = [];
    $types = '';
    
    if ($newStatus && $newStatus !== $oldStatus) {
        $updates[] = "status = ?";
        $params[] = $newStatus;
        $types .= 's';
        
        // Se status = entregue, definir delivered_at
        if ($newStatus === 'entregue') {
            $updates[] = "delivered_at = NOW()";
        }
    }
    
    if ($newPaymentStatus && $newPaymentStatus !== $oldPaymentStatus) {
        $updates[] = "payment_status = ?";
        $params[] = $newPaymentStatus;
        $types .= 's';
        
        // Se pagamento = pago, definir payment_date
        if ($newPaymentStatus === 'pago') {
            $updates[] = "payment_date = NOW()";
        }
    }
    
    if ($notes) {
        $timestamp = date('Y-m-d H:i:s');
        $noteEntry = "\n[{$timestamp}] {$notes}";
        $updates[] = "internal_notes = CONCAT(COALESCE(internal_notes, ''), ?)";
        $params[] = $noteEntry;
        $types .= 's';
    }
    
    if (!empty($updates)) {
        $params[] = $id;
        $params[] = $companyId;
        $types .= 'ii';
        
        $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND company_id = ?";
        $stmtUpdate = $mysqli->prepare($sql);
        $stmtUpdate->bind_param($types, ...$params);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
    
    // ============================================================
    // 2. CRIAR HISTÓRICO DE STATUS
    // ============================================================
    if ($newStatus && $newStatus !== $oldStatus) {
        $stmtHistory = $mysqli->prepare("
            INSERT INTO order_status_history (order_id, status_from, status_to, notes, changed_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtHistory->bind_param('isssi', $id, $oldStatus, $newStatus, $notes, $userId);
        $stmtHistory->execute();
        $stmtHistory->close();
    }
    
    // ============================================================
    // 3. ATUALIZAR SALES_RECORDS (manter português também)
    // ============================================================
    if ($newStatus || $newPaymentStatus) {
        $salesUpdates = [];
        $salesParams = [];
        $salesTypes = '';
        
        if ($newStatus) {
            $salesUpdates[] = "order_status = ?";
            $salesParams[] = $newStatus;
            $salesTypes .= 's';
        }
        
        if ($newPaymentStatus) {
            $salesUpdates[] = "payment_status = ?";
            $salesParams[] = $newPaymentStatus;
            $salesTypes .= 's';
        }
        
        if (!empty($salesUpdates)) {
            $salesParams[] = $id;
            $salesTypes .= 'i';
            
            $salesSql = "UPDATE sales_records SET " . implode(', ', $salesUpdates) . " WHERE order_id = ?";
            $stmtSales = $mysqli->prepare($salesSql);
            $stmtSales->bind_param($salesTypes, ...$salesParams);
            $stmtSales->execute();
            $stmtSales->close();
        }
    }
    
    $mysqli->commit();
    
    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao atualizar status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status: ' . $e->getMessage()]);
}