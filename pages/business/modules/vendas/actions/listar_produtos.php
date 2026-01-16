<?php
/**
 * LISTAR PRODUTOS
 */

header('Content-Type: application/json');

function logDebug($message, $data = null) {
    $logDir = __DIR__ . '/../debug/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $log .= "\n";
    
    file_put_contents($logDir . 'listar_produtos.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO LISTAR PRODUTOS ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

$userId = (int)$_GET['user_id'];
logDebug('User ID', ['user_id' => $userId]);

try {
    logDebug('Buscando produtos da empresa');
    
    $stmt = $mysqli->prepare("
        SELECT id, name
        FROM products
        WHERE user_id = ?
        AND is_active = 1
        ORDER BY name ASC
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $produtos = [];
    while ($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }
    
    logDebug('Produtos encontrados', ['count' => count($produtos)]);
    logDebug('=== FIM LISTAR PRODUTOS (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'produtos' => $produtos
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM LISTAR PRODUTOS (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}