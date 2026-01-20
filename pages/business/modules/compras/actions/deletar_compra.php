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

if ($isEmployee) {
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    
    // Verificar permissão de deletar
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
    // Verificar se pode deletar
    $stmt = $mysqli->prepare("
        SELECT status FROM orders 
        WHERE id = ? AND company_id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param('ii', $id, $companyId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }
    
    // Só pode deletar se pendente ou cancelado
    if (!in_array($order['status'], ['pendente', 'cancelado'])) {
        echo json_encode(['success' => false, 'message' => 'Só é possível deletar pedidos pendentes ou cancelados']);
        exit;
    }
    
    // Soft delete
    $stmt = $mysqli->prepare("
        UPDATE orders SET deleted_at = NOW() 
        WHERE id = ? AND company_id = ?
    ");
    $stmt->bind_param('ii', $id, $companyId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Pedido deletado']);
    
} catch (Exception $e) {
    error_log("Erro ao deletar: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar']);
}