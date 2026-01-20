<?php
/**
 * /pages/person/actions/get_order_details.php
 * Retorna detalhes completos de um pedido específico
 */

session_start();
require_once '../../../registration/includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

// Função de log para debug
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
    // Verificar autenticação
    if (empty($_SESSION['auth']['user_id'])) {
        logDebug('Tentativa de acesso não autorizada');
        echo json_encode([
            'success' => false, 
            'message' => 'Não autorizado. Faça login novamente.'
        ]);
        exit;
    }

    $userId = (int)$_SESSION['auth']['user_id'];
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    logDebug("Buscando detalhes do pedido", [
        'userId' => $userId,
        'orderId' => $orderId
    ]);

    // Validar ID do pedido
    if (!$orderId) {
        logDebug('ID do pedido inválido');
        echo json_encode([
            'success' => false, 
            'message' => 'ID do pedido inválido'
        ]);
        exit;
    }

    // Verificar conexão com banco
    if (!$mysqli || $mysqli->connect_error) {
        logDebug('Erro de conexão com banco de dados', $mysqli->connect_error);
        echo json_encode([
            'success' => false,
            'message' => 'Erro de conexão com banco de dados'
        ]);
        exit;
    }

    // Buscar pedido completo
    $stmt = $mysqli->prepare("
        SELECT 
            o.*,
            u.nome as empresa_nome,
            u.email as empresa_email,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
            (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o
        LEFT JOIN users u ON o.company_id = u.id
        WHERE o.id = ? AND o.customer_id = ? AND o.deleted_at IS NULL
        LIMIT 1
    ");

    if (!$stmt) {
        logDebug('Erro ao preparar statement do pedido', $mysqli->error);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao preparar consulta: ' . $mysqli->error
        ]);
        exit;
    }

    $stmt->bind_param('ii', $orderId, $userId);
    
    if (!$stmt->execute()) {
        logDebug('Erro ao executar query do pedido', $stmt->error);
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao executar consulta: ' . $stmt->error
        ]);
        exit;
    }

    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verificar se pedido existe e pertence ao usuário
    if (!$order) {
        logDebug('Pedido não encontrado ou não pertence ao usuário', [
            'orderId' => $orderId,
            'userId' => $userId
        ]);
        echo json_encode([
            'success' => false, 
            'message' => 'Pedido não encontrado ou você não tem permissão para visualizá-lo'
        ]);
        exit;
    }

    // Buscar itens do pedido
    $stmt = $mysqli->prepare("
        SELECT 
            oi.*,
            p.imagem as product_image_path,
            p.status as product_current_status
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");

    if (!$stmt) {
        logDebug('Erro ao preparar statement dos itens', $mysqli->error);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar itens do pedido'
        ]);
        exit;
    }

    $stmt->bind_param('i', $orderId);
    
    if (!$stmt->execute()) {
        logDebug('Erro ao executar query dos itens', $stmt->error);
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao carregar itens do pedido'
        ]);
        exit;
    }

    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buscar histórico de status (se existir)
    $stmt = $mysqli->prepare("
        SELECT 
            osh.*,
            u.nome as changed_by_name
        FROM order_status_history osh
        LEFT JOIN users u ON osh.changed_by = u.id
        WHERE osh.order_id = ?
        ORDER BY osh.changed_at ASC
    ");

    $statusHistory = [];
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        if ($stmt->execute()) {
            $statusHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }

    // Buscar informações de pagamento
    $stmt = $mysqli->prepare("
        SELECT * FROM payments 
        WHERE order_id = ?
        ORDER BY created_at DESC
    ");

    $payments = [];
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        if ($stmt->execute()) {
            $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }

    // Montar resposta completa
    $order['items'] = $items;
    $order['status_history'] = $statusHistory;
    $order['payments'] = $payments;

    logDebug('Pedido carregado com sucesso', [
        'orderId' => $orderId,
        'itemsCount' => count($items),
        'total' => $order['total']
    ]);

    echo json_encode([
        'success' => true,
        'order' => $order,
        'debug_info' => [
            'items_count' => count($items),
            'has_payments' => count($payments) > 0,
            'has_history' => count($statusHistory) > 0
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    logDebug('Exceção capturada', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}