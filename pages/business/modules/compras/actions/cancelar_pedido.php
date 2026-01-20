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
    $userId = (int)$_SESSION['employee_auth']['employee_id'];
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
    $userId = $companyId;
}

$id = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Cancelado pela empresa');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $mysqli->begin_transaction();
    
    // Verificar se pedido pode ser cancelado
    $stmt = $mysqli->prepare("
        SELECT status, payment_status 
        FROM orders 
        WHERE id = ? AND company_id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param('ii', $id, $companyId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        throw new Exception('Pedido não encontrado');
    }
    
    if ($order['status'] === 'entregue') {
        throw new Exception('Não é possível cancelar pedido já entregue');
    }
    
    if ($order['status'] === 'cancelado') {
        throw new Exception('Pedido já está cancelado');
    }
    
    // Cancelar pedido
    $stmt = $mysqli->prepare("
        UPDATE orders 
        SET status = 'cancelado',
            payment_status = CASE WHEN payment_status = 'pago' THEN 'reembolsado' ELSE payment_status END,
            internal_notes = CONCAT(COALESCE(internal_notes, ''), '
[', NOW(), '] CANCELADO: ', ?)
        WHERE id = ? AND company_id = ?
    ");
    $stmt->bind_param('sii', $reason, $id, $companyId);
    $stmt->execute();
    $stmt->close();
    
    // Registrar histórico
    $stmt = $mysqli->prepare("
        INSERT INTO order_status_history (order_id, status_from, status_to, notes, changed_by)
        VALUES (?, ?, 'cancelado', ?, ?)
    ");
    $stmt->bind_param('issi', $id, $order['status'], $reason, $userId);
    $stmt->execute();
    $stmt->close();
    
    // Atualizar sales_records
    $stmt = $mysqli->prepare("
        UPDATE sales_records 
        SET order_status = 'cancelado',
            payment_status = CASE WHEN payment_status = 'pago' THEN 'reembolsado' ELSE payment_status END
        WHERE order_id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    
    // Restaurar estoque
    $stmt = $mysqli->prepare("
        SELECT oi.product_id, oi.quantity 
        FROM order_items oi
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($items as $item) {
        $stmt = $mysqli->prepare("
            UPDATE products 
            SET stock = stock + ?,
                status = CASE WHEN status = 'esgotado' THEN 'ativo' ELSE status END
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $item['quantity'], $item['product_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    $mysqli->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pedido cancelado com sucesso']);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao cancelar pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}