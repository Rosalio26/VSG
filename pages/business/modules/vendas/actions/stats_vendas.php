<?php
/**
 * ESTATÍSTICAS DE VENDAS
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
    
    file_put_contents($logDir . 'stats_vendas.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO STATS ===');

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
    // Total vendas mês atual
    logDebug('Calculando vendas mês atual');
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM product_purchases pp
        INNER JOIN products p ON pp.product_id = p.id
        WHERE p.user_id = ?
        AND MONTH(pp.purchase_date) = MONTH(NOW())
        AND YEAR(pp.purchase_date) = YEAR(NOW())
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $totalVendas = $stmt->get_result()->fetch_assoc()['total'];
    logDebug('Total vendas', ['total' => $totalVendas]);
    
    // Vendas mês anterior
    logDebug('Calculando vendas mês anterior');
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total
        FROM product_purchases pp
        INNER JOIN products p ON pp.product_id = p.id
        WHERE p.user_id = ?
        AND MONTH(pp.purchase_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND YEAR(pp.purchase_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $vendasMesAnterior = $stmt->get_result()->fetch_assoc()['total'];
    
    $vendasTrend = $vendasMesAnterior > 0 ? 
        round((($totalVendas - $vendasMesAnterior) / $vendasMesAnterior) * 100, 1) : 0;
    logDebug('Trend vendas', ['trend' => $vendasTrend]);
    
    // Receita total
    logDebug('Calculando receita total');
    $stmt = $mysqli->prepare("
        SELECT SUM(pp.total_amount) as total
        FROM product_purchases pp
        INNER JOIN products p ON pp.product_id = p.id
        WHERE p.user_id = ?
        AND pp.status = 'completed'
        AND MONTH(pp.purchase_date) = MONTH(NOW())
        AND YEAR(pp.purchase_date) = YEAR(NOW())
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $receitaTotal = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    logDebug('Receita total', ['receita' => $receitaTotal]);
    
    // Receita mês anterior
    $stmt = $mysqli->prepare("
        SELECT SUM(pp.total_amount) as total
        FROM product_purchases pp
        INNER JOIN products p ON pp.product_id = p.id
        WHERE p.user_id = ?
        AND pp.status = 'completed'
        AND MONTH(pp.purchase_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND YEAR(pp.purchase_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $receitaMesAnterior = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    $receitaTrend = $receitaMesAnterior > 0 ?
        round((($receitaTotal - $receitaMesAnterior) / $receitaMesAnterior) * 100, 1) : 0;
    logDebug('Trend receita', ['trend' => $receitaTrend]);
    
    // Pendentes
    logDebug('Calculando pendentes');
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(pp.total_amount) as valor
        FROM product_purchases pp
        INNER JOIN products p ON pp.product_id = p.id
        WHERE p.user_id = ?
        AND pp.status = 'pending'
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $pendentes = $stmt->get_result()->fetch_assoc();
    logDebug('Pendentes', $pendentes);
    
    // Taxa conversão
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
        'conversao_trend' => 0
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