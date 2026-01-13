<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

/* ================= LISTAR EMPRESAS COM MÉTRICAS ================= */

$empresas = $mysqli->query("
    SELECT 
        u.id,
        u.nome,
        u.email,
        b.tax_id,
        b.status_documentos,
        us.status as subscription_status,
        sp.name as plan_name,
        sp.price as plan_price,
        us.mrr,
        us.start_date,
        us.next_billing_date,
        chs.score as health_score,
        chs.risk_level,
        chs.churn_probability,
        (SELECT SUM(revenue) FROM company_growth_metrics WHERE user_id = u.id AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as revenue_30d,
        (SELECT AVG(active_users) FROM company_growth_metrics WHERE user_id = u.id AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as avg_users_7d,
        (SELECT AVG(satisfaction_score) FROM company_growth_metrics WHERE user_id = u.id AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as avg_satisfaction
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    LEFT JOIN user_subscriptions us ON u.id = us.user_id
    LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
    LEFT JOIN company_health_score chs ON u.id = chs.user_id
    WHERE u.type = 'company'
    ORDER BY chs.score DESC, u.created_at DESC
");

$totalEmpresas = $empresas->num_rows;
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

.geral-container {
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

/* ========== STATS ROW ========== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.stat-mini {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}

.stat-mini-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.stat-mini-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

/* ========== COMPANIES TABLE ========== */
.companies-table-wrapper {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
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

.search-box {
    display: flex;
    gap: 8px;
}

.search-input {
    padding: 8px 12px;
    background: var(--bg-page);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.875rem;
    width: 250px;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent);
}

.companies-table {
    width: 100%;
    border-collapse: collapse;
}

.companies-table thead {
    background: var(--bg-elevated);
}

.companies-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 0.813rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.companies-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
    cursor: pointer;
}

.companies-table tbody tr:hover {
    background: var(--bg-elevated);
}

.companies-table td {
    padding: 16px;
    font-size: 0.875rem;
    color: var(--text-primary);
}

/* ========== HEALTH SCORE ========== */
.health-score {
    display: flex;
    align-items: center;
    gap: 12px;
}

.health-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 700;
    border: 3px solid;
}

.health-circle.excellent {
    background: rgba(35, 134, 54, 0.1);
    border-color: #238636;
    color: #7ee787;
}

.health-circle.good {
    background: rgba(56, 139, 253, 0.1);
    border-color: #388bfd;
    color: #58a6ff;
}

.health-circle.warning {
    background: rgba(158, 106, 3, 0.1);
    border-color: #9e6a03;
    color: #f0c065;
}

.health-circle.critical {
    background: rgba(218, 54, 51, 0.1);
    border-color: #da3633;
    color: #ff7b72;
}

.health-info {
    flex: 1;
}

.health-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 2px;
}

.health-value {
    font-size: 0.875rem;
    font-weight: 600;
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

/* ========== RISK BADGE ========== */
.risk-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.risk-badge.low {
    background: rgba(35, 134, 54, 0.1);
    color: #7ee787;
}

.risk-badge.medium {
    background: rgba(158, 106, 3, 0.1);
    color: #f0c065;
}

.risk-badge.high {
    background: rgba(218, 54, 51, 0.1);
    color: #ff7b72;
}

.risk-badge.critical {
    background: rgba(218, 54, 51, 0.2);
    color: #ff7b72;
    border: 1px solid #da3633;
}

/* ========== MODAL ========== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    width: 100%;
    max-width: 1200px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--bg-card);
    z-index: 10;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    width: 32px;
    height: 32px;
    border: none;
    background: var(--bg-elevated);
    color: var(--text-secondary);
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.25rem;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.modal-body {
    padding: 24px;
}

/* ========== METRICS GRID ========== */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.metric-card {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
}

.metric-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.metric-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.metric-trend {
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

.metric-trend.up { color: #7ee787; }
.metric-trend.down { color: #ff7b72; }

/* ========== CHARTS ========== */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
}

.chart-container {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
}

.chart-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .companies-table {
        font-size: 0.813rem;
    }
    
    .companies-table th,
    .companies-table td {
        padding: 12px 8px;
    }
    
    .health-score {
        flex-direction: column;
        align-items: start;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        max-width: 100%;
        margin: 0;
        border-radius: 0;
    }
}

/* ========== LOADING ========== */
.loading {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.loading i {
    font-size: 2rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<script>
// Verificar se Chart.js está disponível globalmente
(function() {
    const checkChartJS = () => {
        if (typeof Chart !== 'undefined') {
            console.log('✅ Chart.js disponível globalmente');
            return true;
        }
        console.warn('⚠️ Chart.js não carregado ainda');
        return false;
    };
    
    // Verificar imediatamente
    if (!checkChartJS()) {
        // Aguardar evento de carregamento
        window.addEventListener('chartjs-loaded', function() {
            console.log('✅ Chart.js carregou via evento');
        });
    }
})();
</script>

<div class="geral-container">
    <div class="page-header">
        <div class="header-top">
            <h1 class="header-title">📈 Crescimento por Empresa</h1>
            <a href="javascript:loadContent('modules/tabelas/tabelas')" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
        <p class="header-subtitle">Monitore o crescimento, saúde e métricas detalhadas de cada empresa</p>
    </div>

    <!-- STATS MINI -->
    <div class="stats-row">
        <div class="stat-mini">
            <div class="stat-mini-label">Total de Empresas</div>
            <div class="stat-mini-value"><?= $totalEmpresas ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Saúde Excelente (80+)</div>
            <div class="stat-mini-value">
                <?php
                $excellent = $mysqli->query("SELECT COUNT(*) as count FROM company_health_score WHERE score >= 80")->fetch_assoc()['count'];
                echo $excellent;
                ?>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Risco Alto</div>
            <div class="stat-mini-value">
                <?php
                $highRisk = $mysqli->query("SELECT COUNT(*) as count FROM company_health_score WHERE risk_level IN ('high', 'critical')")->fetch_assoc()['count'];
                echo $highRisk;
                ?>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">MRR Médio</div>
            <div class="stat-mini-value">
                <?php
                $avgMrr = $mysqli->query("SELECT COALESCE(AVG(mrr), 0) as avg FROM user_subscriptions WHERE status = 'active'")->fetch_assoc()['avg'];
                echo number_format($avgMrr, 0);
                ?>
            </div>
        </div>
    </div>

    <!-- COMPANIES TABLE -->
    <div class="companies-table-wrapper">
        <div class="table-header">
            <h3 class="table-title">Lista de Empresas</h3>
            <div class="search-box">
                <input type="text" class="search-input" id="searchCompanies" placeholder="Buscar empresa...">
            </div>
        </div>

        <table class="companies-table" id="companiesTable">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Health Score</th>
                    <th>Plano</th>
                    <th>MRR</th>
                    <th>Receita 30d</th>
                    <th>Satisfação</th>
                    <th>Risco</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($emp = $empresas->fetch_assoc()): ?>
                    <?php
                    $healthScore = $emp['health_score'] ?? 50;
                    $healthClass = $healthScore >= 80 ? 'excellent' : ($healthScore >= 60 ? 'good' : ($healthScore >= 40 ? 'warning' : 'critical'));
                    $riskClass = $emp['risk_level'] ?? 'medium';
                    $satisfaction = $emp['avg_satisfaction'] ? number_format($emp['avg_satisfaction'], 1) : 'N/A';
                    ?>
                    <tr onclick="openCompanyDetails(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['nome'], ENT_QUOTES) ?>')">
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($emp['nome']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($emp['email']) ?></div>
                        </td>
                        <td>
                            <div class="health-score">
                                <div class="health-circle <?= $healthClass ?>">
                                    <?= $healthScore ?>
                                </div>
                                <div class="health-info">
                                    <div class="health-label">Score</div>
                                    <div class="health-value"><?= $healthClass === 'excellent' ? 'Excelente' : ($healthClass === 'good' ? 'Bom' : ($healthClass === 'warning' ? 'Atenção' : 'Crítico')) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($emp['plan_name']): ?>
                                <div style="font-weight: 600;"><?= htmlspecialchars($emp['plan_name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= number_format($emp['plan_price'], 0) ?> MT/mês</div>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">Sem plano</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--accent);">
                                <?= $emp['mrr'] ? number_format($emp['mrr'], 0) . ' MT' : '-' ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 600;">
                                <?= $emp['revenue_30d'] ? number_format($emp['revenue_30d'], 0) . ' MT' : '-' ?>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-star" style="color: #f0c065; font-size: 0.875rem;"></i>
                                <span style="font-weight: 600;"><?= $satisfaction ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="risk-badge <?= $riskClass ?>">
                                <?= ucfirst($riskClass) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $subStatus = $emp['subscription_status'] ?? 'inactive';
                            $statusBadge = $subStatus === 'active' ? 'success' : ($subStatus === 'trial' ? 'info' : 'error');
                            $statusText = $subStatus === 'active' ? 'Ativa' : ($subStatus === 'trial' ? 'Trial' : 'Inativa');
                            ?>
                            <span class="badge <?= $statusBadge ?>">
                                <?= $statusText ?>
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL DE DETALHES -->
<div class="modal" id="companyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Carregando...</h2>
            <button class="modal-close" onclick="closeCompanyDetails()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading">
                <i class="fa-solid fa-spinner"></i>
                <p>Carregando dados...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Busca em tempo real
document.getElementById('searchCompanies').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#companiesTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Abrir modal com detalhes da empresa
async function openCompanyDetails(userId, companyName) {
    console.log('🔍 Abrindo detalhes da empresa:', userId, companyName);
    
    const modal = document.getElementById('companyModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = companyName;
    modal.classList.add('active');
    
    modalBody.innerHTML = `
        <div class="loading">
            <i class="fa-solid fa-spinner"></i>
            <p>Carregando métricas detalhadas...</p>
        </div>
    `;
    
    try {
        const url = `modules/tabelas/ajax/get-company-details.php?user_id=${userId}`;
        console.log('📡 Fazendo requisição para:', url);
        
        const response = await fetch(url);
        console.log('📡 Resposta recebida:', response.status, response.statusText);
        
        const contentType = response.headers.get('content-type');
        console.log('📄 Content-Type:', contentType);
        
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('❌ Resposta não é JSON:', text.substring(0, 500));
            throw new Error('Servidor não retornou JSON');
        }
        
        const data = await response.json();
        console.log('✅ Dados recebidos:', data);
        
        if (data.success) {
            renderCompanyDetails(data.data);
        } else {
            modalBody.innerHTML = `<p style="color: var(--error); text-align: center;">❌ ${data.message || 'Erro ao carregar dados.'}</p>`;
        }
    } catch (error) {
        console.error('❌ Erro ao carregar detalhes:', error);
        modalBody.innerHTML = `<p style="color: var(--error); text-align: center;">❌ Erro: ${error.message}</p>`;
    }
}

function closeCompanyDetails() {
    document.getElementById('companyModal').classList.remove('active');
}

// Fechar modal ao clicar fora
document.getElementById('companyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCompanyDetails();
    }
});

// ESC para fechar
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCompanyDetails();
    }
});

function renderCompanyDetails(data) {
    const modalBody = document.getElementById('modalBody');
    
    modalBody.innerHTML = `
        <!-- METRICS GRID -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-label">
                    <i class="fa-solid fa-dollar-sign"></i> Receita Total (30d)
                </div>
                <div class="metric-value">${data.revenue_30d.toLocaleString('pt-BR')} MT</div>
                <div class="metric-trend ${data.revenue_trend >= 0 ? 'up' : 'down'}">
                    <i class="fa-solid fa-arrow-${data.revenue_trend >= 0 ? 'up' : 'down'}"></i>
                    ${Math.abs(data.revenue_trend).toFixed(1)}% vs mês anterior
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-label">
                    <i class="fa-solid fa-users"></i> Usuários Ativos (média 7d)
                </div>
                <div class="metric-value">${data.avg_users_7d}</div>
            </div>
            
            <div class="metric-card">
                <div class="metric-label">
                    <i class="fa-solid fa-database"></i> Storage Usado
                </div>
                <div class="metric-value">${data.storage_used_gb} GB</div>
                <div class="metric-trend">de ${data.storage_limit_gb} GB</div>
            </div>
            
            <div class="metric-card">
                <div class="metric-label">
                    <i class="fa-solid fa-star"></i> Satisfação
                </div>
                <div class="metric-value">${data.satisfaction_score}</div>
                <div class="metric-trend">Média últimos 30 dias</div>
            </div>
        </div>
        
        <!-- CHARTS -->
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-title">📊 Receita Diária (Últimos 30 dias)</div>
                <canvas id="chartRevenue" height="200"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-title">👥 Usuários Ativos</div>
                <canvas id="chartUsers" height="200"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-title">💾 Uso de Storage</div>
                <canvas id="chartStorage" height="200"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-title">⭐ Score de Satisfação</div>
                <canvas id="chartSatisfaction" height="200"></canvas>
            </div>
        </div>
    `;
    
    // Render charts
    renderCharts(data);
}

function renderCharts(data) {
    console.log('📊 Renderizando gráficos com dados:', data);
    
    // Verificar se Chart.js está disponível
    if (typeof Chart === 'undefined') {
        console.error('❌ Chart.js não está carregado!');
        return;
    }
    
    try {
        // Revenue Chart
        const ctxRevenue = document.getElementById('chartRevenue');
        if (ctxRevenue) {
            new Chart(ctxRevenue.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.charts.revenue.labels,
                    datasets: [{
                        label: 'Receita (MT)',
                        data: data.charts.revenue.data,
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
                    maintainAspectRatio: false,
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
        
        // Users Chart
        const ctxUsers = document.getElementById('chartUsers');
        if (ctxUsers) {
            new Chart(ctxUsers.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.charts.users.labels,
                    datasets: [{
                        label: 'Usuários',
                        data: data.charts.users.data,
                        backgroundColor: '#388bfd',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Usuários renderizado');
        }
        
        // Storage Chart
        const ctxStorage = document.getElementById('chartStorage');
        if (ctxStorage) {
            new Chart(ctxStorage.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.charts.storage.labels,
                    datasets: [{
                        label: 'Storage (GB)',
                        data: data.charts.storage.data,
                        borderColor: '#9e6a03',
                        backgroundColor: 'rgba(158, 106, 3, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Storage: ' + context.parsed.y.toFixed(2) + ' GB';
                                }
                            }
                        }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(1) + ' GB';
                                }
                            }
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Storage renderizado');
        }
        
        // Satisfaction Chart
        const ctxSatisfaction = document.getElementById('chartSatisfaction');
        if (ctxSatisfaction) {
            new Chart(ctxSatisfaction.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.charts.satisfaction.labels,
                    datasets: [{
                        label: 'Satisfação',
                        data: data.charts.satisfaction.data,
                        borderColor: '#f0c065',
                        backgroundColor: 'rgba(240, 192, 101, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Satisfação: ' + context.parsed.y.toFixed(1) + ' ⭐';
                                }
                            }
                        }
                    },
                    scales: { 
                        y: { 
                            min: 0, 
                            max: 5,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value.toFixed(1) + ' ⭐';
                                }
                            }
                        }
                    }
                }
            });
            console.log('✅ Gráfico de Satisfação renderizado');
        }
        
        console.log('✅ Todos os gráficos foram renderizados!');
        
    } catch (error) {
        console.error('❌ Erro ao renderizar gráficos:', error);
    }
}

console.log('✅ Tabela Geral (Company Growth) loaded!');
</script>