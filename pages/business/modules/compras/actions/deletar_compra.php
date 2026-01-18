<?php
/**
 * Deletar compra/pedido (soft delete)
 */

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
    
    $stmt = $mysqli->prepare("
        SELECT can_delete FROM employee_permissions 
        WHERE employee_id = ? AND module IN ('vendas', 'compras')
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $perm = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$perm || !$perm['can_delete']) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para deletar']);
        exit;
    }
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificar se pedido pertence à empresa
    $stmt = $mysqli->prepare("
        SELECT order_number, status 
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
    
    $pedido = $result->fetch_assoc();
    $stmt->close();
    
    // Apenas pedidos pending ou cancelled podem ser deletados
    if (!in_array($pedido['status'], ['pending', 'cancelled'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Apenas pedidos pendentes ou cancelados podem ser deletados'
        ]);
        exit;
    }
    
    $mysqli->begin_transaction();
    
    // Soft delete
    $stmtDelete = $mysqli->prepare("
        UPDATE orders 
        SET deleted_at = NOW() 
        WHERE id = ? AND company_id = ?
    ");
    
    $stmtDelete->bind_param('ii', $id, $companyId);
    $stmtDelete->execute();
    $stmtDelete->close();
    
    $mysqli->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pedido deletado com sucesso']);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao deletar pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar pedido']);
}