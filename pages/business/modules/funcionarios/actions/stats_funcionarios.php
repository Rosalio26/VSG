<?php
/**
 * ESTATÍSTICAS DE FUNCIONÁRIOS
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
    
    file_put_contents($logDir . 'stats_funcionarios.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO STATS FUNCIONÁRIOS ===');

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
    // Total funcionários
    logDebug('Calculando total');
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM employees
        WHERE user_id = ?
        AND is_active = 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    logDebug('Total', ['total' => $total]);
    
    // Ativos
    logDebug('Calculando ativos');
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM employees
        WHERE user_id = ?
        AND status = 'ativo'
        AND is_active = 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $ativos = $stmt->get_result()->fetch_assoc()['total'];
    logDebug('Ativos', ['ativos' => $ativos]);
    
    // Inativos
    logDebug('Calculando inativos');
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM employees
        WHERE user_id = ?
        AND status != 'ativo'
        AND is_active = 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $inativos = $stmt->get_result()->fetch_assoc()['total'];
    logDebug('Inativos', ['inativos' => $inativos]);
    
    // Departamentos
    logDebug('Calculando departamentos');
    $stmt = $mysqli->prepare("
        SELECT COUNT(DISTINCT departamento) as total
        FROM employees
        WHERE user_id = ?
        AND departamento IS NOT NULL
        AND departamento != ''
        AND is_active = 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $departamentos = $stmt->get_result()->fetch_assoc()['total'];
    logDebug('Departamentos', ['departamentos' => $departamentos]);
    
    $response = [
        'success' => true,
        'total' => $total,
        'ativos' => $ativos,
        'inativos' => $inativos,
        'departamentos' => $departamentos
    ];
    
    logDebug('Stats calculadas', $response);
    logDebug('=== FIM STATS FUNCIONÁRIOS (SUCESSO) ===');
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM STATS FUNCIONÁRIOS (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}