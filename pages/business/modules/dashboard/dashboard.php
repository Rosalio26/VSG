<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DASHBOARD (EMPRESA)
 * Arquivo: company/modules/dashboard/dashboard.php
 * Descrição: Dashboard principal com estatísticas e informações da empresa
 * ================================================================================
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
if (!isset($_SESSION['auth']['user_id'])) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Sessão Expirada</div>';
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

// Conectar ao banco - tentar múltiplos caminhos
$db_paths = [
    __DIR__ . '/../../../../registration/includes/db.php',
    __DIR__ . '/../../../registration/includes/db.php',
    dirname(dirname(dirname(__FILE__))) . '/registration/includes/db.php'
];

$db_connected = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected || !isset($mysqli)) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Erro de Conexão</div>';
    exit;
}

// ==================== BUSCAR DADOS DO USUÁRIO ====================
$stmt = $mysqli->prepare("
    SELECT u.*, 
           b.tax_id,
           b.business_type,
           b.description,
           b.country,
           b.region,
           b.city
    FROM users u 
    LEFT JOIN businesses b ON u.id = b.user_id 
    WHERE u.id = ? LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ==================== CALCULAR ESTATÍSTICAS ====================
$stats = [
    'vendas_mes' => 0,
    'vendas_total' => 0,
    'transacoes_mes' => 0,
    'transacoes_total' => 0,
    'produtos_total' => 0,
    'produtos_ativos' => 0,
    'mensagens_nao_lidas' => 0,
    'mensagens_total' => 0,
    'dias_ativo' => 0,
    'taxa_conversao' => 0,
    'ticket_medio' => 0
];

// Vendas este mês
$result = $mysqli->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND MONTH(transaction_date) = MONTH(NOW()) 
    AND YEAR(transaction_date) = YEAR(NOW()) 
    AND status = 'completed'
");
if ($result) {
    $stats['vendas_mes'] = (float)$result->fetch_assoc()['total'];
    $result->close();
}

// Vendas totais
$result = $mysqli->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND status = 'completed'
");
if ($result) {
    $stats['vendas_total'] = (float)$result->fetch_assoc()['total'];
    $result->close();
}

// Transações este mês
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND MONTH(transaction_date) = MONTH(NOW()) 
    AND YEAR(transaction_date) = YEAR(NOW())
");
if ($result) {
    $stats['transacoes_mes'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Total de transações
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM transactions 
    WHERE user_id = '$userId' 
    AND status = 'completed'
");
if ($result) {
    $stats['transacoes_total'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Total de produtos
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM products 
    WHERE user_id = '$userId'
");
if ($result) {
    $stats['produtos_total'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Produtos ativos
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM products 
    WHERE user_id = '$userId' 
    AND is_active = 1
");
if ($result) {
    $stats['produtos_ativos'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Mensagens não lidas
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = '$userId' 
    AND status = 'unread'
");
if ($result) {
    $stats['mensagens_nao_lidas'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Total de mensagens
$result = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = '$userId'
");
if ($result) {
    $stats['mensagens_total'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

// Dias ativo
$result = $mysqli->query("
    SELECT DATEDIFF(NOW(), created_at) as dias 
    FROM users 
    WHERE id = '$userId'
");
if ($result) {
    $stats['dias_ativo'] = (int)$result->fetch_assoc()['dias'];
    $result->close();
}

// Calcular taxa de conversão e ticket médio
$stats['taxa_conversao'] = $stats['transacoes_total'] > 0 ? round(($stats['transacoes_total'] / max($stats['mensagens_total'], 1)) * 100, 1) : 0;
$stats['ticket_medio'] = $stats['transacoes_total'] > 0 ? round($stats['vendas_total'] / $stats['transacoes_total'], 2) : 0;

// ==================== BUSCAR TRANSAÇÕES RECENTES ====================
$recent_transactions = $mysqli->query("
    SELECT t.*, sp.name as plan_name 
    FROM transactions t
    LEFT JOIN subscription_plans sp ON t.plan_id = sp.id
    WHERE t.user_id = '$userId'
    ORDER BY t.transaction_date DESC
    LIMIT 5
");

// ==================== BUSCAR PRODUTOS MAIS VENDIDOS ====================
$top_products = $mysqli->query("
    SELECT p.name, COUNT(pp.id) as vendas, SUM(pp.total_amount) as receita
    FROM products p
    INNER JOIN product_purchases pp ON p.id = pp.product_id
    WHERE p.user_id = '$userId' AND pp.status = 'completed'
    GROUP BY p.id
    ORDER BY vendas DESC
    LIMIT 5
");
?>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card, #161b22);
    border: 1px solid var(--border-color, #30363d);
    border-radius: 12px;
    padding: 24px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    border-color: var(--accent-green, #00ff88);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 255, 136, 0.1);
}

.stat-icon-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary, #8b949e);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 8px;
}

.stat-description {
    font-size: 13px;
    color: var(--text-secondary, #8b949e);
}

.info-section {
    background: var(--bg-card, #161b22);
    border: 1px solid var(--border-color, #30363d);
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
}

.section-header {
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.info-item {
    background: rgba(255, 255, 255, 0.03);
    padding: 16px;
    border-radius: 10px;
    border-left: 3px solid;
}

.info-label {
    font-size: 11px;
    color: var(--text-secondary, #8b949e);
    margin-bottom: 8px;
    text-transform: uppercase;
    font-weight: 700;
}

.info-value {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    font-weight: 600;
}

.recent-activity {
    background: var(--bg-card, #161b22);
    border: 1px solid var(--border-color, #30363d);
    border-radius: 12px;
    padding: 24px;
}

.activity-item {
    padding: 16px;
    border-bottom: 1px solid rgba(48, 54, 61, 0.5);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.2s ease;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item:hover {
    background: rgba(0, 255, 136, 0.03);
}

.activity-info h4 {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}

.activity-info p {
    font-size: 12px;
    color: var(--text-secondary, #8b949e);
}

.activity-amount {
    font-size: 16px;
    font-weight: 800;
}

.amount-positive {
    color: var(--accent-green, #00ff88);
}

.amount-pending {
    color: #ffc107;
}

.amount-failed {
    color: #ff4d4d;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary, #8b949e);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Cards de Estatísticas -->
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper">
            <div class="stat-icon" style="background: rgba(0, 255, 136, 0.1);">
                <i class="fa-solid fa-dollar-sign" style="color: var(--accent-green, #00ff88);"></i>
            </div>
            <span class="stat-label">Vendas Totais</span>
        </div>
        <div class="stat-value">MT <?= number_format($stats['vendas_total'], 2, ',', '.') ?></div>
        <div class="stat-description"><?= $stats['transacoes_total'] ?> transações concluídas</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper">
            <div class="stat-icon" style="background: rgba(77, 163, 255, 0.1);">
                <i class="fa-solid fa-box" style="color: var(--accent-blue, #4da3ff);"></i>
            </div>
            <span class="stat-label">Produtos</span>
        </div>
        <div class="stat-value"><?= $stats['produtos_total'] ?></div>
        <div class="stat-description"><?= $stats['produtos_ativos'] ?> produtos ativos</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper">
            <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1);">
                <i class="fa-solid fa-chart-line" style="color: #ffc107;"></i>
            </div>
            <span class="stat-label">Taxa Conversão</span>
        </div>
        <div class="stat-value"><?= $stats['taxa_conversao'] ?>%</div>
        <div class="stat-description">Ticket médio: MT <?= number_format($stats['ticket_medio'], 2, ',', '.') ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper">
            <div class="stat-icon" style="background: rgba(255, 77, 77, 0.1);">
                <i class="fa-solid fa-bell" style="color: <?= $stats['mensagens_nao_lidas'] > 0 ? '#ff4d4d' : '#ffc107' ?>;"></i>
            </div>
            <span class="stat-label">Mensagens</span>
        </div>
        <div class="stat-value"><?= $stats['mensagens_nao_lidas'] ?></div>
        <div class="stat-description"><?= $stats['mensagens_total'] ?> mensagens totais</div>
    </div>
</div>

<!-- Informações da Empresa -->
<div class="info-section">
    <h3 class="section-header">
        <i class="fa-solid fa-building" style="color: var(--accent-green, #00ff88);"></i>
        Informações da Empresa
    </h3>
    <div class="info-grid">
        <div class="info-item" style="border-left-color: var(--accent-green, #00ff88);">
            <p class="info-label">ID de Conta</p>
            <code class="info-value" style="color: var(--accent-green, #00ff88);"><?= htmlspecialchars($user['public_id']) ?></code>
        </div>
        <div class="info-item" style="border-left-color: var(--accent-blue, #4da3ff);">
            <p class="info-label">Tax ID</p>
            <code class="info-value" style="color: var(--accent-blue, #4da3ff);"><?= htmlspecialchars($user['tax_id'] ?? 'N/A') ?></code>
        </div>
        <div class="info-item" style="border-left-color: #8b949e;">
            <p class="info-label">Tipo de Empresa</p>
            <code class="info-value" style="color: #8b949e;"><?= strtoupper($user['business_type'] ?? 'N/A') ?></code>
        </div>
        <div class="info-item" style="border-left-color: #ffc107;">
            <p class="info-label">Dias Ativo</p>
            <code class="info-value" style="color: #ffc107;"><?= $stats['dias_ativo'] ?> dias</code>
        </div>
    </div>
</div>

<!-- Grid de Atividades Recentes -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
    <!-- Transações Recentes -->
    <div class="recent-activity">
        <h3 class="section-header">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent-green, #00ff88);"></i>
            Transações Recentes
        </h3>
        <?php if ($recent_transactions && $recent_transactions->num_rows > 0): ?>
            <?php while ($trans = $recent_transactions->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-info">
                        <h4><?= htmlspecialchars($trans['plan_name'] ?? $trans['description'] ?? 'Transação') ?></h4>
                        <p>
                            <i class="fa-solid fa-calendar"></i>
                            <?= date('d/m/Y H:i', strtotime($trans['transaction_date'])) ?>
                            <?php if ($trans['invoice_number']): ?>
                                | Fatura: <?= htmlspecialchars($trans['invoice_number']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="activity-amount <?= $trans['status'] === 'completed' ? 'amount-positive' : ($trans['status'] === 'pending' ? 'amount-pending' : 'amount-failed') ?>">
                        MT <?= number_format($trans['amount'], 2, ',', '.') ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-receipt"></i>
                <p>Nenhuma transação registrada</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Produtos Mais Vendidos -->
    <div class="recent-activity">
        <h3 class="section-header">
            <i class="fa-solid fa-trophy" style="color: #ffcc00;"></i>
            Produtos Mais Vendidos
        </h3>
        <?php if ($top_products && $top_products->num_rows > 0): ?>
            <?php $rank = 1; ?>
            <?php while ($prod = $top_products->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-info">
                        <h4>
                            <?php if ($rank === 1): ?>
                                <i class="fa-solid fa-crown" style="color: #ffcc00;"></i>
                            <?php elseif ($rank === 2): ?>
                                <i class="fa-solid fa-medal" style="color: #c0c0c0;"></i>
                            <?php elseif ($rank === 3): ?>
                                <i class="fa-solid fa-medal" style="color: #cd7f32;"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($prod['name']) ?>
                        </h4>
                        <p>
                            <i class="fa-solid fa-shopping-cart"></i>
                            <?= $prod['vendas'] ?> vendas
                        </p>
                    </div>
                    <div class="activity-amount amount-positive">
                        MT <?= number_format($prod['receita'], 2, ',', '.') ?>
                    </div>
                </div>
                <?php $rank++; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open"></i>
                <p>Nenhuma venda registrada</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
console.log('✅ Dashboard Module carregado');
</script>