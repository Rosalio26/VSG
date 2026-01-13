<?php
header('Content-Type: application/json');

require_once '../../../../../registration/includes/db.php';
session_start();

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    /* ================= DADOS GERAIS ================= */
    $company = $mysqli->query("
        SELECT 
            u.id,
            u.nome,
            u.email,
            b.tax_id,
            us.mrr,
            sp.name as plan_name,
            sp.max_storage_gb as storage_limit,
            chs.score,
            chs.risk_level,
            chs.churn_probability
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id
        LEFT JOIN user_subscriptions us ON u.id = us.user_id
        LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
        LEFT JOIN company_health_score chs ON u.id = chs.user_id
        WHERE u.id = $userId
    ")->fetch_assoc();
    
    if (!$company) {
        throw new Exception('Empresa não encontrada');
    }
    
    /* ================= MÉTRICAS AGREGADAS ================= */
    
    // Receita últimos 30 dias
    $revenue30d = $mysqli->query("
        SELECT COALESCE(SUM(revenue), 0) as total
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ")->fetch_assoc()['total'];
    
    // Receita mês anterior (para comparação)
    $revenuePrevMonth = $mysqli->query("
        SELECT COALESCE(SUM(revenue), 0) as total
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        AND metric_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ")->fetch_assoc()['total'];
    
    $revenueTrend = $revenuePrevMonth > 0 
        ? (($revenue30d - $revenuePrevMonth) / $revenuePrevMonth) * 100 
        : 0;
    
    // Média usuários últimos 7 dias
    $avgUsers7d = $mysqli->query("
        SELECT ROUND(AVG(active_users), 0) as avg
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ")->fetch_assoc()['avg'] ?? 0;
    
    // Storage atual
    $storageUsed = $mysqli->query("
        SELECT storage_used_gb
        FROM company_growth_metrics
        WHERE user_id = $userId
        ORDER BY metric_date DESC
        LIMIT 1
    ")->fetch_assoc()['storage_used_gb'] ?? 0;
    
    // Satisfação média
    $satisfaction = $mysqli->query("
        SELECT ROUND(AVG(satisfaction_score), 1) as avg
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ")->fetch_assoc()['avg'] ?? 0;
    
    /* ================= DADOS PARA GRÁFICOS ================= */
    
    // Receita diária (últimos 30 dias)
    $revenueData = $mysqli->query("
        SELECT 
            DATE_FORMAT(metric_date, '%d/%m') as label,
            revenue as value
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY metric_date ASC
    ");
    
    $chartRevenue = ['labels' => [], 'data' => []];
    while ($row = $revenueData->fetch_assoc()) {
        $chartRevenue['labels'][] = $row['label'];
        $chartRevenue['data'][] = (float)$row['value'];
    }
    
    // Usuários ativos
    $usersData = $mysqli->query("
        SELECT 
            DATE_FORMAT(metric_date, '%d/%m') as label,
            active_users as value
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY metric_date ASC
    ");
    
    $chartUsers = ['labels' => [], 'data' => []];
    while ($row = $usersData->fetch_assoc()) {
        $chartUsers['labels'][] = $row['label'];
        $chartUsers['data'][] = (int)$row['value'];
    }
    
    // Storage usado
    $storageData = $mysqli->query("
        SELECT 
            DATE_FORMAT(metric_date, '%d/%m') as label,
            storage_used_gb as value
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY metric_date ASC
    ");
    
    $chartStorage = ['labels' => [], 'data' => []];
    while ($row = $storageData->fetch_assoc()) {
        $chartStorage['labels'][] = $row['label'];
        $chartStorage['data'][] = (float)$row['value'];
    }
    
    // Satisfação
    $satisfactionData = $mysqli->query("
        SELECT 
            DATE_FORMAT(metric_date, '%d/%m') as label,
            satisfaction_score as value
        FROM company_growth_metrics
        WHERE user_id = $userId
        AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY metric_date ASC
    ");
    
    $chartSatisfaction = ['labels' => [], 'data' => []];
    while ($row = $satisfactionData->fetch_assoc()) {
        $chartSatisfaction['labels'][] = $row['label'];
        $chartSatisfaction['data'][] = (float)$row['value'];
    }
    
    /* ================= RESPOSTA ================= */
    echo json_encode([
        'success' => true,
        'data' => [
            'company' => $company,
            'revenue_30d' => (float)$revenue30d,
            'revenue_trend' => (float)$revenueTrend,
            'avg_users_7d' => (int)$avgUsers7d,
            'storage_used_gb' => (float)$storageUsed,
            'storage_limit_gb' => (int)$company['storage_limit'] ?? 10,
            'satisfaction_score' => (float)$satisfaction,
            'charts' => [
                'revenue' => $chartRevenue,
                'users' => $chartUsers,
                'storage' => $chartStorage,
                'satisfaction' => $chartSatisfaction
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}