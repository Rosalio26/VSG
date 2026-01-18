<?php
/**
 * Atualizar status do pedido
 * Empresa pode alterar status e pagamento
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../../../../../registration/includes/db.php';

// Autenticação
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Verificar permissão de edição
if ($isEmployee) {
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $changedBy = $employeeId;
    
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
    $changedBy = $companyId;
}

// Dados
$id = (int)($_POST['id'] ?? 0);
$newStatus = $_POST['status'] ?? '';
$newPaymentStatus = $_POST['payment_status'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Validar
$validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
$validPaymentStatuses = ['pending', 'paid', 'partial', 'refunded'];

if (!empty($newStatus) && !in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Status inválido']);
    exit;
}

if (!empty($newPaymentStatus) && !in_array($newPaymentStatus, $validPaymentStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Status de pagamento inválido']);
    exit;
}

try {
    // Verificar se pedido pertence à empresa
    $stmt = $mysqli->prepare("
        SELECT id FROM orders 
        WHERE id = ? AND company_id = ? AND deleted_at IS NULL
    ");
    
    $stmt->bind_param('ii', $id, $companyId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }
    $stmt->close();
    
    $mysqli->begin_transaction();
    
    $updates = [];
    $params = [];
    $types = "";
    
    if (!empty($newStatus)) {
        $updates[] = "status = ?";
        $params[] = $newStatus;
        $types .= "s";
        
        if ($newStatus === 'delivered') {
            $updates[] = "delivered_at = NOW()";
        }
    }
    
    if (!empty($newPaymentStatus)) {
        $updates[] = "payment_status = ?";
        $params[] = $newPaymentStatus;
        $types .= "s";
        
        if ($newPaymentStatus === 'paid') {
            $updates[] = "payment_date = NOW()";
        }
    }
    
    if (!empty($notes)) {
        $updates[] = "internal_notes = CONCAT(COALESCE(internal_notes, ''), ?)";
        $params[] = "[" . date('Y-m-d H:i') . "] " . $notes . "\n";
        $types .= "s";
    }
    
    if (empty($updates)) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => 'Nenhuma alteração']);
        exit;
    }
    
    $sql = "UPDATE orders SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $id;
    $types .= "i";
    
    $stmtUpdate = $mysqli->prepare($sql);
    $stmtUpdate->bind_param($types, ...$params);
    $stmtUpdate->execute();
    $stmtUpdate->close();
    
    $mysqli->commit();
    
    echo json_encode(['success' => true, 'message' => 'Status atualizado!']);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao atualizar: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
}