<?php
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
    
    file_put_contents($logDir . 'stats_vendas.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO STATS ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $userType = 'funcionario';
    logDebug('Funcionário acessando', ['empresa_id' => $userId]);
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $userType = 'gestor';
    logDebug('Gestor acessando', ['user_id' => $userId]);
}

if (isset($_GET['user_id'])) {
    $requestUserId = (int)$_GET['user_id'];
    if ($requestUserId !== $userId) {
        logDebug('ERRO: User ID não corresponde', [
            'session_user_id' => $userId,
            'request_user_id' => $requestUserId
        ]);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

logDebug('User ID validado', ['user_id' => $userId, 'type' => $userType]);

try {
    logDebug('Calculando vendas mês atual');
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM orders
        WHERE company_id = ?
        AND MONTH(order_date) = MONTH(NOW())
        AND YEAR(order_date) = YEAR(NOW())
        AND deleted_at IS NULL
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $totalVendas = $stmt->get_result()->fetch_assoc()['total'];
    logDebug('Total vendas', ['total' => $totalVendas]);
    
    logDebug('Calculando vendas mês anterior');
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM orders
        WHERE company_id = ?
        AND MONTH(order_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND YEAR(order_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND deleted_at IS NULL
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $vendasMesAnterior = $stmt->get_result()->fetch_assoc()['total'];
    
    $vendasTrend = $vendasMesAnterior > 0 ? 
        round((($totalVendas - $vendasMesAnterior) / $vendasMesAnterior) * 100, 1) : 0;
    logDebug('Trend vendas', ['trend' => $vendasTrend]);
    
    logDebug('Calculando receita total');
    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(total), 0) as total
        FROM orders
        WHERE company_id = ?
        AND payment_status = 'pago'
        AND MONTH(order_date) = MONTH(NOW())
        AND YEAR(order_date) = YEAR(NOW())
        AND deleted_at IS NULL
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $receitaTotal = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    logDebug('Receita total', ['receita' => $receitaTotal]);
    
    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(total), 0) as total
        FROM orders
        WHERE company_id = ?
        AND payment_status = 'pago'
        AND MONTH(order_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND YEAR(order_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND deleted_at IS NULL
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $receitaMesAnterior = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    $receitaTrend = $receitaMesAnterior > 0 ?
        round((($receitaTotal - $receitaMesAnterior) / $receitaMesAnterior) * 100, 1) : 0;
    logDebug('Trend receita', ['trend' => $receitaTrend]);
    
    logDebug('Calculando pendentes');
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(total), 0) as valor
        FROM orders
        WHERE company_id = ?
        AND status = 'pendente'
        AND deleted_at IS NULL
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $pendentes = $stmt->get_result()->fetch_assoc();
    logDebug('Pendentes', $pendentes);
    
    $taxaConversao = $totalVendas > 0 ? 
        round(($totalVendas / ($totalVendas + $pendentes['count'])) * 100, 1) : 0;
    logDebug('Taxa conversão', ['taxa' => $taxaConversao]);
    
    $response = [
        'success' => true,
        'total_vendas' => $totalVendas,
        'vendas_trend' => $vendasTrend,
        'receita_total' => $receitaTotal,
        'receita_trend' => $receitaTrend,
        'pendentes' => $pendentes['count'],
        'valor_pendente' => $pendentes['valor'] ?? 0,
        'taxa_conversao' => $taxaConversao,
        'conversao_trend' => 0,
        'user_type' => $userType
    ];
    
    logDebug('Stats calculadas');
    logDebug('=== FIM STATS (SUCESSO) ===');
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM STATS (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}