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

$companyId = $isEmployee 
    ? (int)$_SESSION['employee_auth']['empresa_id'] 
    : (int)$_SESSION['auth']['user_id'];

$userId = $isEmployee 
    ? (int)$_SESSION['employee_auth']['employee_id']
    : $companyId;

$id = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificar pedido
    $stmt = $mysqli->prepare("
        SELECT status FROM orders 
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
    
    $order = $result->fetch_assoc();
    $oldStatus = $order['status'];
    $stmt->close();
    
    // Não pode cancelar se já foi entregue
    if ($oldStatus === 'delivered') {
        echo json_encode(['success' => false, 'message' => 'Não é possível cancelar pedido já entregue']);
        exit;
    }
    
    $mysqli->begin_transaction();
    
    // ============================================================
    // 1. CANCELAR PEDIDO
    // ============================================================
    $stmtCancel = $mysqli->prepare("
        UPDATE orders 
        SET status = 'cancelled', internal_notes = CONCAT(COALESCE(internal_notes, ''), ?)
        WHERE id = ? AND company_id = ?
    ");
    
    $note = "\n[" . date('Y-m-d H:i:s') . "] Pedido cancelado: " . $reason;
    $stmtCancel->bind_param('sii', $note, $id, $companyId);
    $stmtCancel->execute();
    $stmtCancel->close();
    
    // ============================================================
    // 2. RESTAURAR STOCK (devolver produtos ao estoque)
    // ============================================================
    $stmtItems = $mysqli->prepare("
        SELECT product_id, quantity 
        FROM order_items 
        WHERE order_id = ? AND product_id IS NOT NULL
    ");
    $stmtItems->bind_param('i', $id);
    $stmtItems->execute();
    $resultItems = $stmtItems->get_result();
    
    $stmtRestoreStock = $mysqli->prepare("
        UPDATE products 
        SET stock = stock + ? 
        WHERE id = ? AND stock IS NOT NULL
    ");
    
    while ($item = $resultItems->fetch_assoc()) {
        $stmtRestoreStock->bind_param('ii', $item['quantity'], $item['product_id']);
        $stmtRestoreStock->execute();
    }
    
    $stmtItems->close();
    $stmtRestoreStock->close();
    
    // ============================================================
    // 3. CRIAR HISTÓRICO
    // ============================================================
    $stmtHistory = $mysqli->prepare("
        INSERT INTO order_status_history (order_id, status_from, status_to, notes, changed_by)
        VALUES (?, ?, 'cancelled', ?, ?)
    ");
    $stmtHistory->bind_param('issi', $id, $oldStatus, $reason, $userId);
    $stmtHistory->execute();
    $stmtHistory->close();
    
    // ============================================================
    // 4. ATUALIZAR SALES_RECORDS
    // ============================================================
    $mysqli->query("UPDATE sales_records SET order_status = 'cancelled' WHERE order_id = $id");
    
    $mysqli->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pedido cancelado com sucesso']);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao cancelar: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao cancelar pedido']);
}