<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DASHBOARD (EMPRESA)
 * Arquivo: company/modules/dashboard/dashboard.php
 * Descrição: Dashboard principal com estatísticas e informações da empresa
 * ATUALIZADO: Compatível com novo schema SQL (orders, order_items, payments)
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
    'pedidos_mes' => 0,
    'pedidos_total' => 0,
    'produtos_total' => 0,
    'produtos_ativos' => 0,
    'produtos_estoque_baixo' => 0,
    'mensagens_nao_lidas' => 0,
    'mensagens_total' => 0,
    'clientes_total' => 0,
    'dias_ativo' => 0,
    'taxa_conversao' => 0,
    'ticket_medio' => 0,
    'pedidos_pendentes' => 0,
    'pedidos_entregues' => 0
];

// Vendas este mês (apenas pedidos pagos)
$stmt = $mysqli->prepare("
    SELECT COALESCE(SUM(total), 0) as total 
    FROM orders 
    WHERE company_id = ? 
    AND MONTH(order_date) = MONTH(NOW()) 
    AND YEAR(order_date) = YEAR(NOW()) 
    AND payment_status = 'pago'
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['vendas_mes'] = (float)$result['total'];
$stmt->close();

// Vendas totais
$stmt = $mysqli->prepare("
    SELECT COALESCE(SUM(total), 0) as total 
    FROM orders 
    WHERE company_id = ? 
    AND payment_status = 'pago'
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['vendas_total'] = (float)$result['total'];
$stmt->close();

// Pedidos este mês
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
$result = $stmt->get_result()->fetch_assoc();
$stats['pedidos_mes'] = (int)$result['total'];
$stmt->close();

// Total de pedidos
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM orders 
    WHERE company_id = ? 
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['pedidos_total'] = (int)$result['total'];
$stmt->close();

// Pedidos pendentes
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM orders 
    WHERE company_id = ? 
    AND status = 'pendente'
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['pedidos_pendentes'] = (int)$result['total'];
$stmt->close();

// Pedidos entregues
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM orders 
    WHERE company_id = ? 
    AND status = 'entregue'
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['pedidos_entregues'] = (int)$result['total'];
$stmt->close();

// Total de produtos
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM products 
    WHERE user_id = ?
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['produtos_total'] = (int)$result['total'];
$stmt->close();

// Produtos ativos
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM products 
    WHERE user_id = ? 
    AND status = 'ativo'
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['produtos_ativos'] = (int)$result['total'];
$stmt->close();

// Produtos com estoque baixo
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM products 
    WHERE user_id = ? 
    AND stock <= stock_minimo
    AND status = 'ativo'
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['produtos_estoque_baixo'] = (int)$result['total'];
$stmt->close();

// Mensagens não lidas
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = ? 
    AND status = 'nao_lida'
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['mensagens_nao_lidas'] = (int)$result['total'];
$stmt->close();

// Total de mensagens
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE receiver_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['mensagens_total'] = (int)$result['total'];
$stmt->close();

// Total de clientes únicos
$stmt = $mysqli->prepare("
    SELECT COUNT(DISTINCT customer_id) as total 
    FROM orders 
    WHERE company_id = ?
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['clientes_total'] = (int)$result['total'];
$stmt->close();

// Dias ativo
$stmt = $mysqli->prepare("
    SELECT DATEDIFF(NOW(), created_at) as dias 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['dias_ativo'] = (int)$result['dias'];
$stmt->close();

// Calcular taxa de conversão e ticket médio
$stats['taxa_conversao'] = $stats['pedidos_total'] > 0 ? round(($stats['pedidos_entregues'] / $stats['pedidos_total']) * 100, 1) : 0;
$stats['ticket_medio'] = $stats['pedidos_total'] > 0 ? round($stats['vendas_total'] / $stats['pedidos_total'], 2) : 0;

// ==================== BUSCAR PEDIDOS RECENTES ====================
$stmt = $mysqli->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.total,
        o.status,
        o.payment_status,
        o.payment_method,
        CONCAT(u.nome, ' ', COALESCE(u.apelido, '')) as customer_name,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
    FROM orders o
    INNER JOIN users u ON o.customer_id = u.id
    WHERE o.company_id = ?
    AND o.deleted_at IS NULL
    ORDER BY o.order_date DESC
    LIMIT 5
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recent_orders = $stmt->get_result();
$stmt->close();

// ==================== BUSCAR PRODUTOS MAIS VENDIDOS ====================
$stmt = $mysqli->prepare("
    SELECT 
        p.nome as name,
        COUNT(oi.id) as vendas,
        SUM(oi.total) as receita,
        p.stock,
        p.preco
    FROM products p
    INNER JOIN order_items oi ON p.id = oi.product_id
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE p.user_id = ? 
    AND o.payment_status = 'pago'
    AND o.deleted_at IS NULL
    AND p.deleted_at IS NULL
    GROUP BY p.id
    ORDER BY vendas DESC
    LIMIT 5
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$top_products = $stmt->get_result();
$stmt->close();

// ==================== BUSCAR PRODUTOS COM ESTOQUE BAIXO ====================
$stmt = $mysqli->prepare("
    SELECT 
        nome,
        stock,
        stock_minimo,
        preco
    FROM products
    WHERE user_id = ?
    AND stock <= stock_minimo
    AND status = 'ativo'
    AND deleted_at IS NULL
    ORDER BY stock ASC
    LIMIT 5
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$low_stock_products = $stmt->get_result();
$stmt->close();
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

.stat-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    margin-top: 8px;
}

.badge-warning {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.badge-danger {
    background: rgba(255, 77, 77, 0.1);
    color: #ff4d4d;
    border: 1px solid rgba(255, 77, 77, 0.3);
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

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-pendente {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.status-confirmado {
    background: rgba(77, 163, 255, 0.1);
    color: #4da3ff;
}

.status-entregue {
    background: rgba(0, 255, 136, 0.1);
    color: #00ff88;
}

.status-cancelado {
    background: rgba(255, 77, 77, 0.1);
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

.stock-alert {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(255, 77, 77, 0.1);
    border: 1px solid rgba(255, 77, 77, 0.3);
    border-radius: 8px;
    font-size: 11px;
    color: #ff4d4d;
    font-weight: 700;
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
        <div class="stat-value"><?= number_format($stats['vendas_total'], 2, ',', '.') ?> MT</div>
        <div class="stat-description"><?= $stats['pedidos_total'] ?> pedidos realizados</div>
        <div class="stat-description" style="margin-top: 4px;">
            <i class="fa-solid fa-calendar-day"></i> Este mês: <?= number_format($stats['vendas_mes'], 2, ',', '.') ?> MT
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper">
            <div class="stat-icon" style="background: rgba(77, 163, 255, 0.1);">
                <i class="fa-solid fa-shopping-bag" style="color: var(--accent-blue, #4da3ff);"></i>
            </div>
            <span class="stat-label">Pedidos</span>
        </div>
        <div class="stat-value"><?= $stats['pedidos_total'] ?></div>
        <div class="stat-description"><?= $stats['pedidos_entregues'] ?> entregues | <?= $stats['pedidos_mes'] ?> este mês</div>
        <?php if($stats['pedidos_pendentes'] > 0): ?>
            <span class="stat-badge badge-warning">
                <i class="fa-solid fa-clock"></i> <?= $stats['pedidos_pendentes'] ?> pendentes
            </span>
        <?php endif; ?>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper">
            <div class="stat-icon" style="background: rgba(138, 43, 226, 0.1);">
                <i class="fa-solid fa-box" style="color: #8a2be2;"></i>
            </div>
            <span class="stat-label">Produtos</span>
        </div>
        <div class="stat-value"><?= $stats['produtos_total'] ?></div>
        <div class="stat-description"><?= $stats['produtos_ativos'] ?> produtos ativos</div>
        <?php if($stats['produtos_estoque_baixo'] > 0): ?>
            <span class="stat-badge badge-danger">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= $stats['produtos_estoque_baixo'] ?> estoque baixo
            </span>
        <?php endif; ?>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper">
            <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1);">
                <i class="fa-solid fa-chart-line" style="color: #ffc107;"></i>
            </div>
            <span class="stat-label">Performance</span>
        </div>
        <div class="stat-value"><?= $stats['taxa_conversao'] ?>%</div>
        <div class="stat-description">Taxa de entrega</div>
        <div class="stat-description" style="margin-top: 4px;">
            <i class="fa-solid fa-ticket"></i> Ticket médio: <?= number_format($stats['ticket_medio'], 2, ',', '.') ?> MT
        </div>
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
        <div class="info-item" style="border-left-color: #8a2be2;">
            <p class="info-label">Total de Clientes</p>
            <code class="info-value" style="color: #8a2be2;"><?= $stats['clientes_total'] ?></code>
        </div>
        <div class="info-item" style="border-left-color: #ff4d4d;">
            <p class="info-label">Mensagens Não Lidas</p>
            <code class="info-value" style="color: #ff4d4d;"><?= $stats['mensagens_nao_lidas'] ?> / <?= $stats['mensagens_total'] ?></code>
        </div>
    </div>
</div>

<!-- Grid de Atividades Recentes -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
    <!-- Pedidos Recentes -->
    <div class="recent-activity">
        <h3 class="section-header">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent-green, #00ff88);"></i>
            Pedidos Recentes
        </h3>
        <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-info">
                        <h4>
                            Pedido #<?= htmlspecialchars($order['order_number']) ?>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </h4>
                        <p>
                            <i class="fa-solid fa-user"></i> <?= htmlspecialchars($order['customer_name']) ?>
                            | <i class="fa-solid fa-box"></i> <?= $order['items_count'] ?> itens
                            | <i class="fa-solid fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($order['order_date'])) ?>
                        </p>
                        <p style="margin-top: 4px;">
                            <i class="fa-solid fa-credit-card"></i> <?= ucfirst($order['payment_method']) ?>
                            <?php if($order['payment_status'] === 'pago'): ?>
                                <span style="color: var(--accent-green, #00ff88);">✓ Pago</span>
                            <?php else: ?>
                                <span style="color: #ffc107;">⏳ <?= ucfirst($order['payment_status']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="activity-amount <?= $order['payment_status'] === 'pago' ? 'amount-positive' : 'amount-pending' ?>">
                        <?= number_format($order['total'], 2, ',', '.') ?> MT
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-shopping-bag"></i>
                <p>Nenhum pedido registrado</p>
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
                            <i class="fa-solid fa-shopping-cart"></i> <?= $prod['vendas'] ?> vendas
                            | <i class="fa-solid fa-box-open"></i> Estoque: <?= $prod['stock'] ?>
                            | <i class="fa-solid fa-tag"></i> <?= number_format($prod['preco'], 2, ',', '.') ?> MT
                        </p>
                    </div>
                    <div class="activity-amount amount-positive">
                        <?= number_format($prod['receita'], 2, ',', '.') ?> MT
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

<!-- Alerta de Estoque Baixo -->
<?php if ($low_stock_products && $low_stock_products->num_rows > 0): ?>
<div class="recent-activity" style="margin-top: 20px; border: 1px solid rgba(255, 77, 77, 0.3);">
    <h3 class="section-header">
        <i class="fa-solid fa-triangle-exclamation" style="color: #ff4d4d;"></i>
        Alerta: Produtos com Estoque Baixo
    </h3>
    <?php while ($prod = $low_stock_products->fetch_assoc()): ?>
        <div class="activity-item">
            <div class="activity-info">
                <h4><?= htmlspecialchars($prod['nome']) ?></h4>
                <p>
                    <span class="stock-alert">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        Estoque: <?= $prod['stock'] ?> / Mínimo: <?= $prod['stock_minimo'] ?>
                    </span>
                </p>
            </div>
            <div class="activity-amount" style="color: #ffc107;">
                <?= number_format($prod['preco'], 2, ',', '.') ?> MT
            </div>
        </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<script>
console.log('✅ Dashboard Module carregado');
</script>