<?php
/**
 * LISTAR PRODUTOS
 * ATUALIZADO: Suporta empresa e funcionário
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

// Verificar autenticação (empresa OU funcionário)
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Determinar userId (ID da empresa)
if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $userType = 'funcionario';
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $userType = 'gestor';
}

// Se vier user_id por GET, validar que é o mesmo
if (isset($_GET['user_id'])) {
    $requestUserId = (int)$_GET['user_id'];
    if ($requestUserId !== $userId) {
        logDebug('ERRO: User ID não corresponde');
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

logDebug('User ID', ['user_id' => $userId, 'user_type' => $userType]);

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
        'produtos' => $produtos,
        'user_type' => $userType
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM LISTAR PRODUTOS (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}