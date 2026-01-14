<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

/* ================= ESTATÍSTICAS DE DADOS DISPONÍVEIS ================= */

// Contar empresas
$totalCompanies = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'company'")->fetch_assoc()['total'];

// Contar transações
$totalTransactions = $mysqli->query("SELECT COUNT(*) as total FROM transactions")->fetch_assoc()['total'];

// Contar assinaturas
$totalSubscriptions = $mysqli->query("SELECT COUNT(*) as total FROM user_subscriptions")->fetch_assoc()['total'];

// Contar métricas
$totalMetrics = $mysqli->query("SELECT COUNT(*) as total FROM company_growth_metrics")->fetch_assoc()['total'];

// Contar produtos
$totalProducts = $mysqli->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];

// Histórico de exports (últimos 10)
$exportHistory = $mysqli->query("
    SELECT 
        aal.action,
        aal.details,
        aal.created_at,
        u.nome as admin_name
    FROM admin_audit_logs aal
    JOIN users u ON aal.admin_id = u.id
    WHERE aal.action LIKE '%export%'
    ORDER BY aal.created_at DESC
    LIMIT 10
");
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

.export-container {
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

/* ========== STATS MINI ========== */
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
    color: var(--accent);
}

/* ========== EXPORT CARDS ========== */
.export-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.export-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.export-card::before {
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

.export-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.export-card:hover::before {
    opacity: 1;
}

.export-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.export-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    background: rgba(35, 134, 54, 0.15);
    color: #7ee787;
}

.export-card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.export-card-description {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
    line-height: 1.5;
}

.export-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.export-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 6px;
    transition: all 0.2s;
}

.export-option:hover {
    background: var(--bg-hover);
}

.export-option input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent);
}

.export-option label {
    flex: 1;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--text-primary);
    user-select: none;
}

.export-format {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
}

.format-btn {
    flex: 1;
    padding: 10px;
    background: var(--bg-elevated);
    border: 2px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.format-btn:hover {
    background: var(--bg-hover);
}

.format-btn.active {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

.export-actions {
    display: flex;
    gap: 12px;
}

.btn {
    flex: 1;
    padding: 12px 20px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover {
    background: #2ea043;
}

.btn-primary:disabled {
    background: var(--bg-hover);
    color: var(--text-muted);
    cursor: not-allowed;
}

.btn-secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-hover);
}

/* ========== DATE RANGE ========== */
.date-range {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 20px;
}

.date-input-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.date-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.date-input {
    padding: 10px 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.date-input:focus {
    outline: none;
    border-color: var(--accent);
}

/* ========== HISTORY ========== */
.history-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.history-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.history-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.history-list {
    max-height: 400px;
    overflow-y: auto;
}

.history-item {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.history-item:last-child {
    border-bottom: none;
}

.history-item:hover {
    background: var(--bg-elevated);
}

.history-item-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 8px;
}

.history-item-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.history-item-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.history-item-details {
    font-size: 0.813rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.history-item-admin {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 4px;
}

/* ========== LOADING ========== */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.loading-overlay.active {
    display: flex;
}

.loading-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px;
    text-align: center;
    min-width: 300px;
}

.loading-spinner {
    font-size: 3rem;
    color: var(--accent);
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.loading-text {
    margin-top: 16px;
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 600;
}

.loading-subtext {
    margin-top: 8px;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* ========== NOTIFICATION ========== */
.notification-toast {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 10001;
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.notification-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
}

.notification-content.success {
    border-left: 4px solid var(--success);
}

.notification-content.error {
    border-left: 4px solid var(--error);
}

.notification-icon {
    font-size: 1.5rem;
}

.notification-icon.success { color: #7ee787; }
.notification-icon.error { color: #ff7b72; }

.notification-message {
    flex: 1;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.notification-close {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
}

.notification-close:hover {
    background: var(--bg-elevated);
    color: var(--text-primary);
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .export-grid {
        grid-template-columns: 1fr;
    }
    
    .date-range {
        grid-template-columns: 1fr;
    }
    
    .export-format {
        flex-direction: column;
    }
}
</style>

<div class="export-container">
    <div class="page-header">
        <div class="header-top">
            <h1 class="header-title">📤 Exportação de Dados</h1>
            <a href="javascript:loadContent('modules/tabelas/tabelas')" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
        <p class="header-subtitle">Exporte dados em CSV, Excel ou PDF com filtros personalizados</p>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-mini">
            <div class="stat-mini-label">Empresas</div>
            <div class="stat-mini-value"><?= number_format($totalCompanies, 0) ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Transações</div>
            <div class="stat-mini-value"><?= number_format($totalTransactions, 0) ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Assinaturas</div>
            <div class="stat-mini-value"><?= number_format($totalSubscriptions, 0) ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Métricas</div>
            <div class="stat-mini-value"><?= number_format($totalMetrics, 0) ?></div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Produtos</div>
            <div class="stat-mini-value"><?= number_format($totalProducts, 0) ?></div>
        </div>
    </div>

    <!-- EXPORT CARDS -->
    <div class="export-grid">
        <!-- EMPRESAS -->
        <div class="export-card">
            <div class="export-card-header">
                <div class="export-icon">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div>
                    <h3 class="export-card-title">Empresas Cadastradas</h3>
                </div>
            </div>
            <p class="export-card-description">
                Exportar lista completa de empresas com informações de contato, status e documentação.
            </p>
            
            <div class="export-options">
                <div class="export-option">
                    <input type="checkbox" id="companies_basic" checked>
                    <label for="companies_basic">Informações básicas (nome, email, NIF)</label>
                </div>
                <div class="export-option">
                    <input type="checkbox" id="companies_status" checked>
                    <label for="companies_status">Status e documentos</label>
                </div>
                <div class="export-option">
                    <input type="checkbox" id="companies_subscription">
                    <label for="companies_subscription">Dados de assinatura</label>
                </div>
            </div>
            
            <div class="export-format">
                <button class="format-btn active" data-format="csv">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
                <button class="format-btn" data-format="excel">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button class="format-btn" data-format="pdf">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
            </div>
            
            <div class="export-actions">
                <button class="btn btn-primary" onclick="exportData('companies', this)">
                    <i class="fa-solid fa-download"></i> Exportar
                </button>
            </div>
        </div>

        <!-- TRANSAÇÕES -->
        <div class="export-card">
            <div class="export-card-header">
                <div class="export-icon">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 class="export-card-title">Transações Financeiras</h3>
                </div>
            </div>
            <p class="export-card-description">
                Exportar histórico de transações com valores, status e métodos de pagamento.
            </p>
            
            <div class="date-range">
                <div class="date-input-group">
                    <label class="date-label">Data Inicial</label>
                    <input type="date" class="date-input" id="transactions_date_from" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="date-input-group">
                    <label class="date-label">Data Final</label>
                    <input type="date" class="date-input" id="transactions_date_to" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="export-options">
                <div class="export-option">
                    <input type="checkbox" id="transactions_completed" checked>
                    <label for="transactions_completed">Apenas completadas</label>
                </div>
                <div class="export-option">
                    <input type="checkbox" id="transactions_pending">
                    <label for="transactions_pending">Incluir pendentes</label>
                </div>
            </div>
            
            <div class="export-format">
                <button class="format-btn active" data-format="csv">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
                <button class="format-btn" data-format="excel">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button class="format-btn" data-format="pdf">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
            </div>
            
            <div class="export-actions">
                <button class="btn btn-primary" onclick="exportData('transactions', this)">
                    <i class="fa-solid fa-download"></i> Exportar
                </button>
            </div>
        </div>

        <!-- MÉTRICAS -->
        <div class="export-card">
            <div class="export-card-header">
                <div class="export-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <h3 class="export-card-title">Métricas de Crescimento</h3>
                </div>
            </div>
            <p class="export-card-description">
                Exportar métricas diárias de crescimento, receita e uso de recursos.
            </p>
            
            <div class="date-range">
                <div class="date-input-group">
                    <label class="date-label">Data Inicial</label>
                    <input type="date" class="date-input" id="metrics_date_from" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="date-input-group">
                    <label class="date-label">Data Final</label>
                    <input type="date" class="date-input" id="metrics_date_to" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="export-options">
                <div class="export-option">
                    <input type="checkbox" id="metrics_revenue" checked>
                    <label for="metrics_revenue">Receita</label>
                </div>
                <div class="export-option">
                    <input type="checkbox" id="metrics_users" checked>
                    <label for="metrics_users">Usuários ativos</label>
                </div>
                <div class="export-option">
                    <input type="checkbox" id="metrics_satisfaction" checked>
                    <label for="metrics_satisfaction">Satisfação</label>
                </div>
            </div>
            
            <div class="export-format">
                <button class="format-btn active" data-format="csv">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
                <button class="format-btn" data-format="excel">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
            </div>
            
            <div class="export-actions">
                <button class="btn btn-primary" onclick="exportData('metrics', this)">
                    <i class="fa-solid fa-download"></i> Exportar
                </button>
            </div>
        </div>

        <!-- RELATÓRIO COMPLETO -->
        <div class="export-card">
            <div class="export-card-header">
                <div class="export-icon">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div>
                    <h3 class="export-card-title">Relatório Completo</h3>
                </div>
            </div>
            <p class="export-card-description">
                Relatório executivo com todas as informações, gráficos e análises do período.
            </p>
            
            <div class="date-range">
                <div class="date-input-group">
                    <label class="date-label">Data Inicial</label>
                    <input type="date" class="date-input" id="report_date_from" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="date-input-group">
                    <label class="date-label">Data Final</label>
                    <input type="date" class="date-input" id="report_date_to" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="export-options">
                <div class="export-option">
                    <input type="checkbox" id="report_summary" checked>
                    <label for="report_summary">Resumo executivo</label>
                </div>
                <div class="export-option">
                    <input type="checkbox" id="report_charts" checked>
                    <label for="report_charts">Incluir gráficos</label>
                </div>
                <div class="export-option">
                    <input type="checkbox" id="report_details" checked>
                    <label for="report_details">Detalhamento completo</label>
                </div>
            </div>
            
            <div class="export-format">
                <button class="format-btn active" data-format="pdf">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
            </div>
            
            <div class="export-actions">
                <button class="btn btn-primary" onclick="exportData('report', this)">
                    <i class="fa-solid fa-download"></i> Gerar Relatório
                </button>
            </div>
        </div>
    </div>

    <!-- HISTÓRICO -->
    <div class="history-card">
        <div class="history-header">
            <h3 class="history-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Histórico de Exportações
            </h3>
        </div>
        <div class="history-list">
            <?php if ($exportHistory && $exportHistory->num_rows > 0): ?>
                <?php while ($export = $exportHistory->fetch_assoc()): ?>
                    <div class="history-item">
                        <div class="history-item-header">
                            <div class="history-item-title">
                                <?= htmlspecialchars($export['action']) ?>
                            </div>
                            <div class="history-item-date">
                                <?= date('d/m/Y H:i', strtotime($export['created_at'])) ?>
                            </div>
                        </div>
                        <div class="history-item-details">
                            <?= htmlspecialchars($export['details']) ?>
                        </div>
                        <div class="history-item-admin">
                            Por: <?= htmlspecialchars($export['admin_name']) ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                    <i class="fa-solid fa-inbox" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                    <p>Nenhuma exportação realizada ainda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- LOADING OVERLAY -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="loading-spinner">
            <i class="fa-solid fa-spinner"></i>
        </div>
        <div class="loading-text">Gerando exportação...</div>
        <div class="loading-subtext">Aguarde enquanto preparamos seus dados</div>
    </div>
</div>

<script>
// Alternar formato de exportação
document.querySelectorAll('.format-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const card = this.closest('.export-card');
        card.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

// Função de exportação
async function exportData(type, button) {
    console.log('📤 Iniciando exportação:', type);
    
    // Desabilitar botão
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando...';
    
    // Mostrar loading
    document.getElementById('loadingOverlay').classList.add('active');
    
    // Coletar dados do card
    const card = button.closest('.export-card');
    const format = card.querySelector('.format-btn.active').dataset.format;
    
    // Coletar opções
    const options = {};
    card.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        options[checkbox.id] = checkbox.checked;
    });
    
    // Coletar datas (se existirem)
    const dateFrom = card.querySelector('input[type="date"][id$="_date_from"]');
    const dateTo = card.querySelector('input[type="date"][id$="_date_to"]');
    
    if (dateFrom) options.date_from = dateFrom.value;
    if (dateTo) options.date_to = dateTo.value;
    
    console.log('📋 Opções:', options);
    console.log('📄 Formato:', format);
    
    try {
        // Fazer requisição AJAX
        const response = await fetch('modules/tabelas/ajax/export-data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type: type,
                format: format,
                options: options
            })
        });
        
        const contentType = response.headers.get('content-type');
        
        // Se for JSON, houve erro
        if (contentType && contentType.includes('application/json')) {
            const result = await response.json();
            throw new Error(result.message || 'Erro ao gerar exportação');
        }
        
        // Se for arquivo, fazer download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        
        // Nome do arquivo
        const date = new Date().toISOString().split('T')[0];
        const extension = format === 'csv' ? 'csv' : (format === 'excel' ? 'xlsx' : 'pdf');
        a.download = `${type}_${date}.${extension}`;
        
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        console.log('✅ Exportação concluída!');
        
        showNotification('success', '✅ Exportação realizada com sucesso!');
        
        // Recarregar página para atualizar histórico
        setTimeout(() => {
            loadContent('modules/tabelas/tabela-export');
        }, 1500);
        
    } catch (error) {
        console.error('❌ Erro na exportação:', error);
        showNotification('error', '❌ Erro ao gerar exportação: ' + error.message);
    } finally {
        // Ocultar loading
        document.getElementById('loadingOverlay').classList.remove('active');
        
        // Restaurar botão
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// Mostrar notificação
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = 'notification-toast';
    
    const icon = type === 'success' 
        ? '<i class="fa-solid fa-circle-check notification-icon success"></i>' 
        : '<i class="fa-solid fa-triangle-exclamation notification-icon error"></i>';
    
    notification.innerHTML = `
        <div class="notification-content ${type}">
            ${icon}
            <div class="notification-message">${message}</div>
            <button class="notification-close" onclick="this.closest('.notification-toast').remove()">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

console.log('✅ Export.php carregado!');
</script>