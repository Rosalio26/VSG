<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

/* ================= MÉTRICAS GERAIS ================= */

// Total de empresas
$totalEmpresas = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'company'")->fetch_assoc()['total'];

// Empresas ativas (com assinatura)
$empresasAtivas = $mysqli->query("SELECT COUNT(DISTINCT user_id) as total FROM user_subscriptions WHERE status = 'active'")->fetch_assoc()['total'];

// MRR Total (Monthly Recurring Revenue)
$mrrTotal = $mysqli->query("SELECT COALESCE(SUM(mrr), 0) as total FROM user_subscriptions WHERE status = 'active'")->fetch_assoc()['total'];

// Receita do mês atual
$receitaMes = $mysqli->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM transactions 
    WHERE status = 'completed' 
    AND MONTH(transaction_date) = MONTH(CURDATE()) 
    AND YEAR(transaction_date) = YEAR(CURDATE())
")->fetch_assoc()['total'];

// Crescimento MRR (comparação com mês anterior)
$mrrMesAnterior = $mysqli->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM transactions 
    WHERE status = 'completed' 
    AND type = 'subscription'
    AND MONTH(transaction_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(transaction_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetch_assoc()['total'];

$crescimentoMRR = $mrrMesAnterior > 0 ? (($mrrTotal - $mrrMesAnterior) / $mrrMesAnterior) * 100 : 0;

// Novos clientes este mês
$novosClientesMes = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM user_subscriptions 
    WHERE MONTH(start_date) = MONTH(CURDATE()) 
    AND YEAR(start_date) = YEAR(CURDATE())
")->fetch_assoc()['total'];

// Taxa de churn (cancelamentos)
$churnCount = $mysqli->query("
    SELECT COUNT(*) as total 
    FROM user_subscriptions 
    WHERE status = 'cancelled' 
    AND MONTH(cancelled_at) = MONTH(CURDATE())
")->fetch_assoc()['total'];

$churnRate = $empresasAtivas > 0 ? ($churnCount / $empresasAtivas) * 100 : 0;

// Pagamentos pendentes
$pagamentosPendentes = $mysqli->query("
    SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as valor 
    FROM transactions 
    WHERE status = 'pending'
")->fetch_assoc();

// Plano mais popular
$planoPopular = $mysqli->query("
    SELECT sp.name, COUNT(*) as subscriptions 
    FROM user_subscriptions us 
    JOIN subscription_plans sp ON us.plan_id = sp.id 
    WHERE us.status = 'active' 
    GROUP BY us.plan_id 
    ORDER BY subscriptions DESC 
    LIMIT 1
")->fetch_assoc();

// Produto mais vendido
$produtoPopular = $mysqli->query("
    SELECT p.name, COUNT(*) as purchases 
    FROM product_purchases pp 
    JOIN products p ON pp.product_id = p.id 
    WHERE pp.status = 'completed' 
    GROUP BY pp.product_id 
    ORDER BY purchases DESC 
    LIMIT 1
")->fetch_assoc();

/* ================= DADOS PARA GRÁFICOS ================= */

// Receita últimos 30 dias
$receitaDiaria = $mysqli->query("
    SELECT 
        DATE(transaction_date) as data,
        SUM(amount) as total
    FROM transactions
    WHERE status = 'completed'
    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(transaction_date)
    ORDER BY data ASC
");

$chartReceitaLabels = [];
$chartReceitaData = [];
while ($row = $receitaDiaria->fetch_assoc()) {
    $chartReceitaLabels[] = date('d/m', strtotime($row['data']));
    $chartReceitaData[] = (float)$row['total'];
}

// Distribuição por planos
$distribuicaoPlanos = $mysqli->query("
    SELECT sp.name, COUNT(*) as count
    FROM user_subscriptions us
    JOIN subscription_plans sp ON us.plan_id = sp.id
    WHERE us.status = 'active'
    GROUP BY us.plan_id
");

$chartPlanosLabels = [];
$chartPlanosData = [];
while ($row = $distribuicaoPlanos->fetch_assoc()) {
    $chartPlanosLabels[] = $row['name'];
    $chartPlanosData[] = (int)$row['count'];
}
?>

<style>
:root {
    --bg-page: #0d1117;
    --bg-card: #161b22;
    --bg-elevated: #21262d;
    --text-primary: #c9d1d9;
    --text-secondary: #8b949e;
    --text-muted: #6e7681;
    --accent: #238636;
    --accent-hover: #2ea043;
    --border: #30363d;
    --success: #238636;
    --warning: #9e6a03;
    --error: #da3633;
    --info: #388bfd;
}

* {
    box-sizing: border-box;
}

.tabelas-container {
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

.header-title {
    font-size: 2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.header-subtitle {
    color: var(--text-secondary);
    font-size: 0.938rem;
}

/* ========== STATS GRID ========== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--accent);
    opacity: 0;
    transition: opacity 0.2s;
}

.stat-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.stat-card:hover::before {
    opacity: 1;
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

.stat-trend {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.813rem;
    padding: 4px 8px;
    border-radius: 4px;
}

.stat-trend.up {
    background: rgba(35, 134, 54, 0.1);
    color: #7ee787;
}

.stat-trend.down {
    background: rgba(218, 54, 51, 0.1);
    color: #ff7b72;
}

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

/* ========== QUICK ACTIONS ========== */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.action-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: var(--text-primary);
}

.action-card:hover {
    background: var(--bg-elevated);
    border-color: var(--accent);
    transform: translateY(-2px);
}

.action-icon {
    font-size: 2rem;
    margin-bottom: 12px;
    color: var(--accent);
}

.action-title {
    font-size: 0.938rem;
    font-weight: 600;
    margin-bottom: 4px;
}

.action-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* ========== CHARTS GRID ========== */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.chart-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
}

.chart-subtitle {
    font-size: 0.813rem;
    color: var(--text-muted);
}

canvas {
    max-height: 300px;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="tabelas-container">
    <div class="page-header">
        <h1 class="header-title">📊 Visão Geral - Analytics</h1>
        <p class="header-subtitle">Dashboard de monitoramento financeiro e crescimento das empresas</p>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div class="stat-trend up">
                    <i class="fa-solid fa-arrow-up"></i>
                    +<?= $novosClientesMes ?>
                </div>
            </div>
            <div class="stat-label">Total de Empresas</div>
            <div class="stat-value"><?= number_format($totalEmpresas, 0, ',', '.') ?></div>
            <div class="stat-description"><?= $empresasAtivas ?> com assinaturas ativas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
                <div class="stat-trend <?= $crescimentoMRR >= 0 ? 'up' : 'down' ?>">
                    <i class="fa-solid fa-arrow-<?= $crescimentoMRR >= 0 ? 'up' : 'down' ?>"></i>
                    <?= number_format(abs($crescimentoMRR), 1) ?>%
                </div>
            </div>
            <div class="stat-label">MRR (Receita Recorrente Mensal)</div>
            <div class="stat-value"><?= number_format($mrrTotal, 0, ',', '.') ?> MT</div>
            <div class="stat-description">Receita previsível mensal</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon info">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-label">Receita Este Mês</div>
            <div class="stat-value"><?= number_format($receitaMes, 0, ',', '.') ?> MT</div>
            <div class="stat-description">Todos os tipos de transações</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon <?= $churnRate > 5 ? 'error' : 'warning' ?>">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div class="stat-trend <?= $churnRate > 5 ? 'down' : 'up' ?>">
                    <?= number_format($churnRate, 1) ?>%
                </div>
            </div>
            <div class="stat-label">Taxa de Churn</div>
            <div class="stat-value"><?= $churnCount ?></div>
            <div class="stat-description">Cancelamentos este mês</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <div class="stat-label">Pagamentos Pendentes</div>
            <div class="stat-value"><?= $pagamentosPendentes['total'] ?></div>
            <div class="stat-description"><?= number_format($pagamentosPendentes['valor'], 0, ',', '.') ?> MT aguardando</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fa-solid fa-trophy"></i>
                </div>
            </div>
            <div class="stat-label">Plano Mais Popular</div>
            <div class="stat-value"><?= $planoPopular['name'] ?? 'N/A' ?></div>
            <div class="stat-description"><?= $planoPopular['subscriptions'] ?? 0 ?> assinaturas ativas</div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="javascript:loadContent('modules/tabelas/tabela-geral')" class="action-card">
            <div class="action-icon">📈</div>
            <div class="action-title">Crescimento por Empresa</div>
            <div class="action-desc">Ver métricas detalhadas</div>
        </a>

        <a href="javascript:loadContent('modules/tabelas/tabela-financeiro')" class="action-card">
            <div class="action-icon">💰</div>
            <div class="action-title">Análise Financeira</div>
            <div class="action-desc">Receitas e transações</div>
        </a>

        <a href="javascript:loadContent('modules/tabelas/tabela-export')" class="action-card">
            <div class="action-icon">📤</div>
            <div class="action-title">Exportar Dados</div>
            <div class="action-desc">CSV, Excel, PDF</div>
        </a>

        <a href="javascript:loadContent('modules/tabelas/lista-empresas')" class="action-card">
            <div class="action-icon">🏢</div>
            <div class="action-title">Lista Completa</div>
            <div class="action-desc">Todas as empresas</div>
        </a>
    </div>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Receita Diária (Últimos 30 dias)</div>
                    <div class="chart-subtitle">Todas as transações completadas</div>
                </div>
            </div>
            <canvas id="chartReceita"></canvas>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Distribuição por Planos</div>
                    <div class="chart-subtitle">Assinaturas ativas</div>
                </div>
            </div>
            <canvas id="chartPlanos"></canvas>
        </div>
    </div>
</div>

<script>
// Aguardar Chart.js estar disponível (carregado globalmente)
function initDashboardCharts() {
    if (typeof Chart === 'undefined') {
        console.warn('⚠️ Aguardando Chart.js carregar...');
        setTimeout(initDashboardCharts, 100);
        return;
    }
    
    console.log('✅ Chart.js disponível, renderizando dashboard...');
    
    try {
        // Gráfico de Receita
        const ctxReceita = document.getElementById('chartReceita');
        if (ctxReceita) {
            new Chart(ctxReceita.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($chartReceitaLabels) ?>,
                    datasets: [{
                        label: 'Receita (MT)',
                        data: <?= json_encode($chartReceitaData) ?>,
                        borderColor: '#238636',
                        backgroundColor: 'rgba(35, 134, 54, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Receita: ' + context.parsed.y.toLocaleString('pt-BR') + ' MT';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR') + ' MT';
                                }
                            }
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Receita renderizado');
        }
        
        // Gráfico de Planos
        const ctxPlanos = document.getElementById('chartPlanos');
        if (ctxPlanos) {
            new Chart(ctxPlanos.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chartPlanosLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($chartPlanosData) ?>,
                        backgroundColor: [
                            '#238636',
                            '#388bfd',
                            '#f0c065',
                            '#ff7b72',
                            '#bc8cff'
                        ],
                        borderWidth: 3,
                        borderColor: '#161b22',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Planos renderizado');
        }
        
        console.log('✅ Dashboard charts renderizados com sucesso!');
        
    } catch (error) {
        console.error('❌ Erro ao renderizar gráficos:', error);
    }
}

// Iniciar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardCharts);
} else {
    initDashboardCharts();
}

console.log('✅ Tabelas.php (Dashboard) carregado!');
</script>