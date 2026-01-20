<?php
/**
 * /pages/person/actions/confirm_payment.php
 */

session_start();
require_once '../../../registration/includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

function logDebug($message, $data = null) {
    $logFile = __DIR__ . '/order_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
}

try {
    if (empty($_SESSION['auth']['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Não autorizado']);
        exit;
    }

    $userId = (int)$_SESSION['auth']['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $notes = isset($input['notes']) ? trim($input['notes']) : '';

    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    logDebug("Tentativa de confirmação de pagamento", [
        'userId' => $userId, 
        'orderId' => $orderId,
        'notes' => $notes
    ]);

    // Verificar se o pedido pertence ao usuário e está entregue
    $stmt = $mysqli->prepare("
        SELECT id, order_number, payment_method, payment_status, status, company_id, total
        FROM orders 
        WHERE id = ? 
        AND customer_id = ? 
        AND payment_method = 'manual'
        AND status = 'entregue'
        AND payment_status = 'pendente'
        AND deleted_at IS NULL
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $mysqli->error);
    }

    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode([
            'success' => false, 
            'message' => 'Pedido não encontrado ou não elegível para confirmação'
        ]);
        exit;
    }

    mysqli_begin_transaction($mysqli);

    // Atualizar status de pagamento do pedido
    $stmt = $mysqli->prepare("
        UPDATE orders 
        SET payment_status = 'pago',
            payment_date = NOW(),
            confirmed_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();
    
    // Registrar pagamento
    $stmt = $mysqli->prepare("
        INSERT INTO payments (
            order_id, amount, currency, payment_method, 
            payment_status, payment_date, confirmed_at, notes
        ) VALUES (?, ?, 'MZN', 'manual', 'confirmado', NOW(), NOW(), ?)
    ");
    $stmt->bind_param('ids', $orderId, $order['total'], $notes);
    $stmt->execute();
    $stmt->close();
    
    // Notificar empresa
    $message = "O cliente confirmou o pagamento manual do pedido #{$order['order_number']}. Valor: {$order['total']} MZN.";
    if ($notes) {
        $message .= " Observação: {$notes}";
    }
    
    $stmt = $mysqli->prepare("
        INSERT INTO notifications (
            receiver_id, sender_id, category, priority, 
            subject, message, related_order_id
        ) VALUES (?, ?, 'pagamento_manual', 'alta', ?, ?, ?)
    ");
    $subject = "Pagamento Confirmado - Pedido #{$order['order_number']}";
    $stmt->bind_param('iissi', $order['company_id'], $userId, $subject, $message, $orderId);
    $stmt->execute();
    $stmt->close();
    
    mysqli_commit($mysqli);
    
    logDebug("Pagamento confirmado com sucesso", ['orderId' => $orderId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Pagamento confirmado com sucesso!'
    ]);
    
} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->connect_errno === 0) {
        mysqli_rollback($mysqli);
    }
    
    logDebug("Erro ao confirmar pagamento", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao confirmar pagamento',
        'error' => $e->getMessage()
    ]);
}