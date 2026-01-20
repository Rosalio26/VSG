<?php
/**
 * /pages/person/actions/get_order_total.php
 * Retorna apenas o total de um pedido
 */

session_start();
require_once '../../../registration/includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // Verificar autenticação
    if (empty($_SESSION['auth']['user_id'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Não autorizado',
            'total' => 0
        ]);
        exit;
    }

    $userId = (int)$_SESSION['auth']['user_id'];
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$orderId) {
        echo json_encode([
            'success' => false, 
            'message' => 'ID inválido',
            'total' => 0
        ]);
        exit;
    }

    // Verificar conexão
    if (!$mysqli || $mysqli->connect_error) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro de conexão',
            'total' => 0
        ]);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT 
            total,
            currency,
            payment_status,
            status
        FROM orders 
        WHERE id = ? AND customer_id = ? AND deleted_at IS NULL
        LIMIT 1
    ");

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao preparar consulta',
            'total' => 0
        ]);
        exit;
    }

    $stmt->bind_param('ii', $orderId, $userId);
    
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao executar consulta',
            'total' => 0
        ]);
        exit;
    }

    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Pedido não encontrado',
            'total' => 0
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'total' => (float)$result['total'],
        'currency' => $result['currency'] ?? 'MZN',
        'payment_status' => $result['payment_status'],
        'order_status' => $result['status']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno',
        'total' => 0,
        'error' => $e->getMessage()
    ]);
}