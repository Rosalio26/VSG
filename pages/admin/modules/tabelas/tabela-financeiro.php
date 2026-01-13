<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

/* ================= FILTROS ================= */
$statusFilter = $_GET['status'] ?? 'todos';
$typeFilter = $_GET['type'] ?? 'todos';
$methodFilter = $_GET['method'] ?? 'todos';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Primeiro dia do mês
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Hoje
$searchTerm = $_GET['search'] ?? '';

/* ================= CONSTRUIR QUERY ================= */
$whereConditions = ["1=1"];
$params = [];

if ($statusFilter !== 'todos') {
    $whereConditions[] = "t.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== 'todos') {
    $whereConditions[] = "t.type = ?";
    $params[] = $typeFilter;
}

if ($methodFilter !== 'todos') {
    $whereConditions[] = "t.payment_method = ?";
    $params[] = $methodFilter;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(t.transaction_date) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(t.transaction_date) <= ?";
    $params[] = $dateTo;
}

if ($searchTerm) {
    $whereConditions[] = "(u.nome LIKE ? OR t.invoice_number LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $whereConditions);

/* ================= BUSCAR TRANSAÇÕES ================= */
$stmt = $mysqli->prepare("
    SELECT 
        t.*,
        u.nome as company_name,
        u.email as company_email,
        sp.name as plan_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN subscription_plans sp ON t.plan_id = sp.id
    WHERE $whereClause
    ORDER BY t.transaction_date DESC
    LIMIT 100
");

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$transactions = $stmt->get_result();

/* ================= MÉTRICAS FINANCEIRAS ================= */

// Total no período
$totalPeriod = $mysqli->query("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as completed,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END), 0) as failed,
        COUNT(*) as total_transactions
    FROM transactions
    WHERE DATE(transaction_date) BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// Por tipo de transação
$byType = $mysqli->query("
    SELECT 
        type,
        COUNT(*) as count,
        SUM(amount) as total
    FROM transactions
    WHERE status = 'completed'
    AND DATE(transaction_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY type
");

$chartTypeLabels = [];
$chartTypeData = [];
while ($row = $byType->fetch_assoc()) {
    $chartTypeLabels[] = ucfirst($row['type']);
    $chartTypeData[] = (float)$row['total'];
}

// Por método de pagamento
$byMethod = $mysqli->query("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(amount) as total
    FROM transactions
    WHERE status = 'completed'
    AND DATE(transaction_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY payment_method
");

$chartMethodLabels = [];
$chartMethodData = [];
while ($row = $byMethod->fetch_assoc()) {
    $chartMethodLabels[] = ucfirst($row['payment_method']);
    $chartMethodData[] = (int)$row['count'];
}

// Receita diária no período
$dailyRevenue = $mysqli->query("
    SELECT 
        DATE(transaction_date) as date,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as revenue
    FROM transactions
    WHERE DATE(transaction_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(transaction_date)
    ORDER BY date ASC
");

$chartDailyLabels = [];
$chartDailyData = [];
while ($row = $dailyRevenue->fetch_assoc()) {
    $chartDailyLabels[] = date('d/m', strtotime($row['date']));
    $chartDailyData[] = (float)$row['revenue'];
}

// Top 5 produtos mais vendidos
$topProducts = $mysqli->query("
    SELECT 
        p.name,
        COUNT(*) as sales,
        SUM(pp.total_amount) as revenue
    FROM product_purchases pp
    JOIN products p ON pp.product_id = p.id
    WHERE pp.status = 'completed'
    AND DATE(pp.purchase_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY pp.product_id
    ORDER BY sales DESC
    LIMIT 5
");

// Top 5 clientes por receita
$topClients = $mysqli->query("
    SELECT 
        u.nome,
        COUNT(*) as transactions,
        SUM(t.amount) as total_revenue
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'completed'
    AND DATE(t.transaction_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY t.user_id
    ORDER BY total_revenue DESC
    LIMIT 5
");

// Taxa de conversão (completed vs total)
$conversionRate = $totalPeriod['total_transactions'] > 0 
    ? ($totalPeriod['completed'] / ($totalPeriod['completed'] + $totalPeriod['pending'] + $totalPeriod['failed'])) * 100 
    : 0;
?>

<style>
:root {
    --bg-page: #0d1117;
    --bg-card: #161b22;
    --bg-elevated: #21262d;
    --bg-hover: #30363d;
    --text-primary: #c9d1d9;
    --text-secondary: #8b949e;
    --text-muted: #6e7681;
    --accent: #238636;
    --border: #30363d;
    --success: #238636;
    --warning: #9e6a03;
    --error: #da3633;
    --info: #388bfd;
}

* {
    box-sizing: border-box;
}

.financeiro-container {
    padding: 24px;
    background: var(--bg-page);
    min-height: 100vh;
}

/* ========== HEADER ========== */
.page-header {
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.header-title {
    font-size: 2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.header-subtitle {
    color: var(--text-secondary);
    font-size: 0.938rem;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--bg-card);
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.btn-back:hover {
    background: var(--bg-elevated);
    border-color: var(--accent);
}

/* ========== STATS GRID ========== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    transition: all 0.2s;
}

.stat-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 16px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-icon.success { background: rgba(35, 134, 54, 0.15); color: #7ee787; }
.stat-icon.warning { background: rgba(158, 106, 3, 0.15); color: #f0c065; }
.stat-icon.error { background: rgba(218, 54, 51, 0.15); color: #ff7b72; }
.stat-icon.info { background: rgba(56, 139, 253, 0.15); color: #58a6ff; }

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 4px;
}

.stat-description {
    font-size: 0.813rem;
    color: var(--text-muted);
}

/* ========== FILTERS ========== */
.filters-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

.filters-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.filter-input,
.filter-select {
    padding: 10px 12px;
    background: var(--bg-page);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.filter-input:focus,
.filter-select:focus {
    outline: none;
    border-color: var(--accent);
    background: var(--bg-elevated);
}

.filters-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover {
    background: #2ea043;
}

.btn-secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-hover);
}

/* ========== CHARTS GRID ========== */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.chart-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
}

.chart-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
}

canvas {
    max-height: 300px;
}

/* ========== TABLES ========== */
.table-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}

.table-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: var(--bg-elevated);
}

th {
    padding: 12px 16px;
    text-align: left;
    font-size: 0.813rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

tbody tr:hover {
    background: var(--bg-elevated);
}

td {
    padding: 16px;
    font-size: 0.875rem;
    color: var(--text-primary);
}

/* ========== BADGES ========== */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge.success {
    background: rgba(35, 134, 54, 0.15);
    color: #7ee787;
}

.badge.warning {
    background: rgba(158, 106, 3, 0.15);
    color: #f0c065;
}

.badge.error {
    background: rgba(218, 54, 51, 0.15);
    color: #ff7b72;
}

.badge.info {
    background: rgba(56, 139, 253, 0.15);
    color: #58a6ff;
}

.badge.neutral {
    background: rgba(139, 148, 158, 0.15);
    color: #8b949e;
}

/* ========== TOP LISTS ========== */
.top-lists {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.top-list-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
}

.top-list-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.top-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-elevated);
    border-radius: 6px;
    margin-bottom: 8px;
}

.top-item:last-child {
    margin-bottom: 0;
}

.top-item-info {
    flex: 1;
}

.top-item-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.top-item-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.top-item-value {
    font-weight: 700;
    color: var(--accent);
    font-size: 1.125rem;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .top-lists {
        grid-template-columns: 1fr;
    }
    
    table {
        font-size: 0.813rem;
    }
    
    th, td {
        padding: 12px 8px;
    }
}
</style>

<div class="financeiro-container">
    <div class="page-header">
        <div class="header-top">
            <h1 class="header-title">💰 Análise Financeira</h1>
            <a href="javascript:loadContent('modules/tabelas/tabelas')" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
        <p class="header-subtitle">Análise completa de receitas, transações e performance financeira</p>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-label">Receita Completada</div>
            <div class="stat-value"><?= number_format($totalPeriod['completed'], 0, ',', '.') ?> MT</div>
            <div class="stat-description">Transações concluídas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <div class="stat-label">Pendente</div>
            <div class="stat-value"><?= number_format($totalPeriod['pending'], 0, ',', '.') ?> MT</div>
            <div class="stat-description">Aguardando confirmação</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon error">
                    <i class="fa-solid fa-times-circle"></i>
                </div>
            </div>
            <div class="stat-label">Falhas</div>
            <div class="stat-value"><?= number_format($totalPeriod['failed'], 0, ',', '.') ?> MT</div>
            <div class="stat-description">Transações falhadas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon info">
                    <i class="fa-solid fa-percentage"></i>
                </div>
            </div>
            <div class="stat-label">Taxa de Conversão</div>
            <div class="stat-value"><?= number_format($conversionRate, 1) ?>%</div>
            <div class="stat-description"><?= $totalPeriod['total_transactions'] ?> transações totais</div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filters-card">
        <h3 class="filters-title">
            <i class="fa-solid fa-filter"></i>
            Filtros
        </h3>
        <form id="filterForm" method="GET" onsubmit="return false;">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Buscar</label>
                    <input type="text" name="search" id="searchInput" class="filter-input" 
                           placeholder="Empresa ou Invoice..." value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" id="statusFilter" class="filter-select">
                        <option value="todos">Todos</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completadas</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Falhadas</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Canceladas</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Tipo</label>
                    <select name="type" id="typeFilter" class="filter-select">
                        <option value="todos">Todos</option>
                        <option value="subscription" <?= $typeFilter === 'subscription' ? 'selected' : '' ?>>Assinatura</option>
                        <option value="upgrade" <?= $typeFilter === 'upgrade' ? 'selected' : '' ?>>Upgrade</option>
                        <option value="addon" <?= $typeFilter === 'addon' ? 'selected' : '' ?>>Addon</option>
                        <option value="one_time" <?= $typeFilter === 'one_time' ? 'selected' : '' ?>>Único</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Método</label>
                    <select name="method" id="methodFilter" class="filter-select">
                        <option value="todos">Todos</option>
                        <option value="mpesa" <?= $methodFilter === 'mpesa' ? 'selected' : '' ?>>M-Pesa</option>
                        <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Transferência</option>
                        <option value="credit_card" <?= $methodFilter === 'credit_card' ? 'selected' : '' ?>>Cartão</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Data Inicial</label>
                    <input type="date" name="date_from" id="dateFrom" class="filter-input" value="<?= $dateFrom ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Data Final</label>
                    <input type="date" name="date_to" id="dateTo" class="filter-input" value="<?= $dateTo ?>">
                </div>
            </div>
            
            <div class="filters-actions">
                <button type="button" onclick="clearFilters()" class="btn btn-secondary">
                    <i class="fa-solid fa-rotate-left"></i> Limpar
                </button>
                <button type="button" onclick="applyFilters()" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Aplicar Filtros
                </button>
            </div>
        </form>
    </div>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3 class="chart-title">📊 Receita Diária</h3>
            <canvas id="chartDaily"></canvas>
        </div>
        
        <div class="chart-card">
            <h3 class="chart-title">📈 Receita por Tipo</h3>
            <canvas id="chartType"></canvas>
        </div>
        
        <div class="chart-card">
            <h3 class="chart-title">💳 Transações por Método</h3>
            <canvas id="chartMethod"></canvas>
        </div>
    </div>

    <!-- TOP LISTS -->
    <div class="top-lists">
        <div class="top-list-card">
            <h3 class="top-list-title">
                <i class="fa-solid fa-trophy"></i>
                Top 5 Produtos
            </h3>
            <?php if ($topProducts->num_rows > 0): ?>
                <?php while ($product = $topProducts->fetch_assoc()): ?>
                    <div class="top-item">
                        <div class="top-item-info">
                            <div class="top-item-name"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="top-item-meta"><?= $product['sales'] ?> vendas</div>
                        </div>
                        <div class="top-item-value"><?= number_format($product['revenue'], 0) ?> MT</div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px;">Nenhum produto vendido no período</p>
            <?php endif; ?>
        </div>
        
        <div class="top-list-card">
            <h3 class="top-list-title">
                <i class="fa-solid fa-star"></i>
                Top 5 Clientes
            </h3>
            <?php if ($topClients->num_rows > 0): ?>
                <?php while ($client = $topClients->fetch_assoc()): ?>
                    <div class="top-item">
                        <div class="top-item-info">
                            <div class="top-item-name"><?= htmlspecialchars($client['nome']) ?></div>
                            <div class="top-item-meta"><?= $client['transactions'] ?> transações</div>
                        </div>
                        <div class="top-item-value"><?= number_format($client['total_revenue'], 0) ?> MT</div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px;">Nenhuma transação no período</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TRANSACTIONS TABLE -->
    <div class="table-card">
        <div class="table-header">
            <h3 class="table-title">Transações Recentes</h3>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Data</th>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Método</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows > 0): ?>
                        <?php while ($trans = $transactions->fetch_assoc()): ?>
                            <?php
                            $statusClass = [
                                'completed' => 'success',
                                'pending' => 'warning',
                                'failed' => 'error',
                                'cancelled' => 'neutral'
                            ][$trans['status']] ?? 'neutral';
                            
                            $statusText = [
                                'completed' => 'Completada',
                                'pending' => 'Pendente',
                                'failed' => 'Falhada',
                                'cancelled' => 'Cancelada'
                            ][$trans['status']] ?? $trans['status'];
                            
                            $typeText = [
                                'subscription' => 'Assinatura',
                                'upgrade' => 'Upgrade',
                                'addon' => 'Addon',
                                'one_time' => 'Único',
                                'refund' => 'Reembolso'
                            ][$trans['type']] ?? $trans['type'];
                            
                            $methodText = [
                                'mpesa' => 'M-Pesa',
                                'bank_transfer' => 'Transferência',
                                'credit_card' => 'Cartão',
                                'paypal' => 'PayPal',
                                'cash' => 'Dinheiro'
                            ][$trans['payment_method']] ?? $trans['payment_method'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($trans['invoice_number']) ?></strong>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($trans['transaction_date'])) ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($trans['company_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($trans['company_email']) ?></div>
                                </td>
                                <td><?= $typeText ?></td>
                                <td><?= $methodText ?></td>
                                <td style="font-weight: 700; color: var(--accent);">
                                    <?= number_format($trans['amount'], 2, ',', '.') ?> MT
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                Nenhuma transação encontrada com os filtros aplicados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Aplicar filtros
function applyFilters() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        if (value && value !== 'todos') {
            params.append(key, value);
        }
    }
    
    const url = 'modules/tabelas/tabela-financeiro' + (params.toString() ? '?' + params.toString() : '');
    console.log('🔍 Aplicando filtros:', url);
    loadContent(url);
}

// Limpar filtros
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'todos';
    document.getElementById('typeFilter').value = 'todos';
    document.getElementById('methodFilter').value = 'todos';
    document.getElementById('dateFrom').value = '<?= date('Y-m-01') ?>';
    document.getElementById('dateTo').value = '<?= date('Y-m-d') ?>';
    
    loadContent('modules/tabelas/tabela-financeiro');
}

// Aplicar filtros ao pressionar Enter
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        applyFilters();
    }
});

// Renderizar gráficos
function initFinanceiroCharts() {
    if (typeof Chart === 'undefined') {
        console.warn('⚠️ Aguardando Chart.js...');
        setTimeout(initFinanceiroCharts, 100);
        return;
    }
    
    console.log('✅ Renderizando gráficos financeiros...');
    
    try {
        // Gráfico de Receita Diária
        const ctxDaily = document.getElementById('chartDaily');
        if (ctxDaily) {
            new Chart(ctxDaily.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($chartDailyLabels) ?>,
                    datasets: [{
                        label: 'Receita (MT)',
                        data: <?= json_encode($chartDailyData) ?>,
                        borderColor: '#238636',
                        backgroundColor: 'rgba(35, 134, 54, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
            console.log('  ✅ Receita Diária');
        }
        
        // Gráfico de Receita por Tipo
        const ctxType = document.getElementById('chartType');
        if (ctxType) {
            new Chart(ctxType.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chartTypeLabels) ?>,
                    datasets: [{
                        label: 'Receita (MT)',
                        data: <?= json_encode($chartTypeData) ?>,
                        backgroundColor: ['#238636', '#388bfd', '#f0c065', '#ff7b72', '#bc8cff'],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
            console.log('  ✅ Receita por Tipo');
        }
        
        // Gráfico de Transações por Método
        const ctxMethod = document.getElementById('chartMethod');
        if (ctxMethod) {
            new Chart(ctxMethod.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chartMethodLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($chartMethodData) ?>,
                        backgroundColor: ['#238636', '#388bfd', '#f0c065', '#ff7b72', '#bc8cff'],
                        borderWidth: 3,
                        borderColor: '#161b22'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, usePointStyle: true }
                        }
                    }
                }
            });
            console.log('  ✅ Transações por Método');
        }
        
        console.log('✅ Gráficos financeiros renderizados!');
        
    } catch (error) {
        console.error('❌ Erro ao renderizar gráficos:', error);
    }
}

// Iniciar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFinanceiroCharts);
} else {
    initFinanceiroCharts();
}

console.log('✅ Financeiro.php carregado!');
</script>