<?php
/**
 * /pages/person/actions/cancel_order.php
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

    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    logDebug("Tentativa de cancelamento", ['userId' => $userId, 'orderId' => $orderId]);

    // Verificar se o pedido pode ser cancelado
    $stmt = $mysqli->prepare("
        SELECT id, order_number, status, company_id
        FROM orders 
        WHERE id = ? 
        AND customer_id = ? 
        AND status IN ('pendente', 'confirmado')
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
            'message' => 'Pedido não encontrado ou não pode ser cancelado'
        ]);
        exit;
    }

    mysqli_begin_transaction($mysqli);

    // Atualizar status do pedido
    $stmt = $mysqli->prepare("UPDATE orders SET status = 'cancelado' WHERE id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();
    
    // Registrar histórico
    $stmt = $mysqli->prepare("
        INSERT INTO order_status_history (
            order_id, changed_by, status_from, status_to, notes
        ) VALUES (?, ?, ?, 'cancelado', 'Cancelado pelo cliente')
    ");
    $stmt->bind_param('iis', $orderId, $userId, $order['status']);
    $stmt->execute();
    $stmt->close();
    
    // Devolver estoque dos produtos
    $stmt = $mysqli->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($items as $item) {
        // Atualizar estoque
        $stmt = $mysqli->prepare("
            UPDATE products 
            SET stock = stock + ?,
                status = CASE 
                    WHEN status = 'esgotado' AND (stock + ?) > 0 THEN 'ativo'
                    ELSE status
                END
            WHERE id = ?
        ");
        $stmt->bind_param('iii', $item['quantity'], $item['quantity'], $item['product_id']);
        $stmt->execute();
        $stmt->close();
        
        // Registrar movimentação
        $stmt = $mysqli->prepare("
            SELECT stock FROM products WHERE id = ?
        ");
        $stmt->bind_param('i', $item['product_id']);
        $stmt->execute();
        $stockAfter = $stmt->get_result()->fetch_assoc()['stock'];
        $stockBefore = $stockAfter - $item['quantity'];
        $stmt->close();
        
        $stmt = $mysqli->prepare("
            INSERT INTO stock_movements (
                product_id, order_id, type, quantity, 
                stock_before, stock_after, user_id, notes
            ) VALUES (?, ?, 'devolucao', ?, ?, ?, ?, ?)
        ");
        $notes = "Devolução - Pedido cancelado #{$order['order_number']}";
        $stmt->bind_param('iiiiiis', 
            $item['product_id'], $orderId, $item['quantity'],
            $stockBefore, $stockAfter, $userId, $notes
        );
        $stmt->execute();
        $stmt->close();
    }
    
    // Notificar empresa
    $stmt = $mysqli->prepare("
        INSERT INTO notifications (
            receiver_id, sender_id, category, priority,
            subject, message, related_order_id
        ) VALUES (?, ?, 'sistema', 'media', ?, ?, ?)
    ");
    $subject = "Pedido Cancelado - #{$order['order_number']}";
    $message = "O cliente cancelou o pedido. O estoque foi devolvido automaticamente.";
    $stmt->bind_param('iissi', $order['company_id'], $userId, $subject, $message, $orderId);
    $stmt->execute();
    $stmt->close();
    
    mysqli_commit($mysqli);
    
    logDebug("Pedido cancelado com sucesso", ['orderId' => $orderId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido cancelado com sucesso!'
    ]);
    
} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->connect_errno === 0) {
        mysqli_rollback($mysqli);
    }
    
    logDebug("Erro ao cancelar pedido", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao cancelar pedido',
        'error' => $e->getMessage()
    ]);
}