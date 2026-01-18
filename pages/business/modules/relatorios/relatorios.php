<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE RELATÓRIOS
 * Arquivo: pages/business/modules/relatorios/relatorios.php
 * Descrição: Geração e visualização de relatórios empresariais
 * ================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detectar tipo de usuário
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isManager = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isManager) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Acesso Negado</div>';
    exit;
}

// Determinar empresa_id
if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $userName = $_SESSION['employee_auth']['nome'];
    $userType = 'funcionario';
    $canExport = false; // Funcionários podem ter permissão limitada
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $userName = $_SESSION['auth']['nome'];
    $userType = 'gestor';
    $canExport = true;
}
?>

<style>
/* ==================== GitHub Dark Theme ==================== */
:root {
    --gh-bg-primary: #0d1117;
    --gh-bg-secondary: #161b22;
    --gh-bg-tertiary: #21262d;
    --gh-border: #30363d;
    --gh-border-hover: #8b949e;
    --gh-text: #c9d1d9;
    --gh-text-secondary: #8b949e;
    --gh-text-muted: #6e7681;
    --gh-accent-green: #238636;
    --gh-accent-green-bright: #2ea043;
    --gh-accent-blue: #1f6feb;
    --gh-accent-yellow: #d29922;
    --gh-accent-red: #da3633;
}

.relatorios-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
}

/* Header */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gh-border);
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--gh-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-title i {
    color: var(--gh-accent-green-bright);
}

/* Report Cards Grid */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.report-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    padding: 24px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.report-card:hover {
    transform: translateY(-4px);
    border-color: var(--gh-accent-blue);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}

.report-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.report-icon.sales {
    background: rgba(46, 160, 67, 0.1);
    color: var(--gh-accent-green-bright);
}

.report-icon.financial {
    background: rgba(31, 111, 235, 0.1);
    color: var(--gh-accent-blue);
}

.report-icon.inventory {
    background: rgba(210, 153, 34, 0.1);
    color: var(--gh-accent-yellow);
}

.report-icon.customers {
    background: rgba(218, 54, 51, 0.1);
    color: var(--gh-accent-red);
}

.report-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--gh-text);
    margin-bottom: 8px;
}

.report-description {
    font-size: 14px;
    color: var(--gh-text-secondary);
    margin-bottom: 16px;
    line-height: 1.5;
}

.report-actions {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--gh-border);
}

.btn-primary {
    background: var(--gh-accent-green);
    border-color: rgba(240, 246, 252, 0.1);
    color: #ffffff;
}

.btn-primary:hover {
    background: var(--gh-accent-green-bright);
}

.btn-secondary {
    background: var(--gh-bg-tertiary);
    border-color: var(--gh-border);
    color: var(--gh-text);
}

.btn-secondary:hover {
    background: var(--gh-bg-primary);
    border-color: var(--gh-border-hover);
}

/* Quick Stats */
.quick-stats {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 32px;
}

.stats-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.stat-item {
    background: var(--gh-bg-tertiary);
    border: 1px solid var(--gh-border);
    border-radius: 8px;
    padding: 16px;
}

.stat-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--gh-text);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(1, 4, 9, 0.85);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--gh-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    background: none;
    border: none;
    color: var(--gh-text-secondary);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--gh-bg-tertiary);
    color: var(--gh-text);
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--gh-border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    margin-bottom: 8px;
}

.form-input, .form-select {
    width: 100%;
    padding: 10px 12px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--gh-accent-blue);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.1);
}

/* Employee Badge */
.employee-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(77, 163, 255, 0.1);
    border: 1px solid rgba(77, 163, 255, 0.3);
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-accent-blue);
}

/* Responsive */
@media (max-width: 768px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="relatorios-container">
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-file-invoice"></i>
            Relatórios
        </h1>
        <?php if ($isEmployee): ?>
            <span class="employee-badge">
                <i class="fa-solid fa-user-tie"></i>
                Modo Visualização
            </span>
        <?php endif; ?>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <h2 class="stats-title">
            <i class="fa-solid fa-chart-line"></i>
            Visão Geral
        </h2>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-label">Vendas Este Mês</div>
                <div class="stat-value" id="vendasMes">--</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Receita Total</div>
                <div class="stat-value" id="receitaTotal">--</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Produtos Ativos</div>
                <div class="stat-value" id="produtosAtivos">--</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Clientes Ativos</div>
                <div class="stat-value" id="clientesAtivos">--</div>
            </div>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="reports-grid">
        <!-- Relatório de Vendas -->
        <div class="report-card" onclick="openReportModal('vendas')">
            <div class="report-icon sales">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="report-title">Relatório de Vendas</div>
            <div class="report-description">
                Analise suas vendas por período, produto ou categoria. Visualize gráficos e tendências.
            </div>
            <div class="report-actions" onclick="event.stopPropagation()">
                <button class="btn btn-primary" onclick="openReportModal('vendas')">
                    <i class="fa-solid fa-eye"></i>
                    Visualizar
                </button>
                <?php if ($canExport): ?>
                <button class="btn btn-secondary" onclick="exportReport('vendas')">
                    <i class="fa-solid fa-download"></i>
                    Exportar
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Relatório Financeiro -->
        <div class="report-card" onclick="openReportModal('financeiro')">
            <div class="report-icon financial">
                <i class="fa-solid fa-dollar-sign"></i>
            </div>
            <div class="report-title">Relatório Financeiro</div>
            <div class="report-description">
                Visão completa de receitas, despesas e fluxo de caixa do seu negócio.
            </div>
            <div class="report-actions" onclick="event.stopPropagation()">
                <button class="btn btn-primary" onclick="openReportModal('financeiro')">
                    <i class="fa-solid fa-eye"></i>
                    Visualizar
                </button>
                <?php if ($canExport): ?>
                <button class="btn btn-secondary" onclick="exportReport('financeiro')">
                    <i class="fa-solid fa-download"></i>
                    Exportar
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Relatório de Estoque -->
        <div class="report-card" onclick="openReportModal('estoque')">
            <div class="report-icon inventory">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div class="report-title">Relatório de Estoque</div>
            <div class="report-description">
                Controle de inventário, produtos em falta e movimentações de estoque.
            </div>
            <div class="report-actions" onclick="event.stopPropagation()">
                <button class="btn btn-primary" onclick="openReportModal('estoque')">
                    <i class="fa-solid fa-eye"></i>
                    Visualizar
                </button>
                <?php if ($canExport): ?>
                <button class="btn btn-secondary" onclick="exportReport('estoque')">
                    <i class="fa-solid fa-download"></i>
                    Exportar
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Relatório de Clientes -->
        <div class="report-card" onclick="openReportModal('clientes')">
            <div class="report-icon customers">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="report-title">Relatório de Clientes</div>
            <div class="report-description">
                Análise do comportamento de clientes, fidelização e segmentação.
            </div>
            <div class="report-actions" onclick="event.stopPropagation()">
                <button class="btn btn-primary" onclick="openReportModal('clientes')">
                    <i class="fa-solid fa-eye"></i>
                    Visualizar
                </button>
                <?php if ($canExport): ?>
                <button class="btn btn-secondary" onclick="exportReport('clientes')">
                    <i class="fa-solid fa-download"></i>
                    Exportar
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Geração de Relatório -->
<div class="modal-overlay" id="reportModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">
                <i class="fa-solid fa-file-invoice"></i>
                Gerar Relatório
            </h3>
            <button class="modal-close" onclick="closeReportModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <form id="reportForm">
                <div class="form-group">
                    <label class="form-label">Período</label>
                    <select class="form-select" id="reportPeriodo">
                        <option value="7">Últimos 7 dias</option>
                        <option value="30" selected>Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                        <option value="365">Último ano</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>

                <div class="form-group" id="customDateRange" style="display: none;">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" class="form-input" id="dateStart">
                    
                    <label class="form-label" style="margin-top: 12px;">Data Final</label>
                    <input type="date" class="form-input" id="dateEnd">
                </div>

                <div class="form-group">
                    <label class="form-label">Formato de Exportação</label>
                    <select class="form-select" id="reportFormat">
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel (XLSX)</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeReportModal()">
                <i class="fa-solid fa-times"></i>
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="generateReport()">
                <i class="fa-solid fa-file-arrow-down"></i>
                Gerar Relatório
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const userId = <?= $userId ?>;
    const userType = '<?= $userType ?>';
    const isEmployee = <?= $isEmployee ? 'true' : 'false' ?>;
    const canExport = <?= $canExport ? 'true' : 'false' ?>;
    let currentReportType = null;

    // Carregar estatísticas ao iniciar
    loadQuickStats();

    async function loadQuickStats() {
        try {
            // Simulação - substituir por chamada real à API
            setTimeout(() => {
                document.getElementById('vendasMes').textContent = '127';
                document.getElementById('receitaTotal').textContent = 'MT 125,430.00';
                document.getElementById('produtosAtivos').textContent = '43';
                document.getElementById('clientesAtivos').textContent = '89';
            }, 500);
        } catch (error) {
            console.error('Erro ao carregar stats:', error);
        }
    }

    // Abrir modal de relatório
    window.openReportModal = function(type) {
        currentReportType = type;
        const titles = {
            vendas: 'Relatório de Vendas',
            financeiro: 'Relatório Financeiro',
            estoque: 'Relatório de Estoque',
            clientes: 'Relatório de Clientes'
        };
        
        document.getElementById('modalTitle').innerHTML = `
            <i class="fa-solid fa-file-invoice"></i>
            ${titles[type] || 'Gerar Relatório'}
        `;
        
        document.getElementById('reportModal').classList.add('active');
    };

    // Fechar modal
    window.closeReportModal = function() {
        document.getElementById('reportModal').classList.remove('active');
        currentReportType = null;
    };

    // Gerar relatório
    window.generateReport = async function() {
        const periodo = document.getElementById('reportPeriodo').value;
        const formato = document.getElementById('reportFormat').value;
        
        let dateStart, dateEnd;
        
        if (periodo === 'custom') {
            dateStart = document.getElementById('dateStart').value;
            dateEnd = document.getElementById('dateEnd').value;
            
            if (!dateStart || !dateEnd) {
                alert('Por favor, selecione as datas inicial e final');
                return;
            }
        }
        
        try {
            const params = new URLSearchParams({
                user_id: userId,
                tipo: currentReportType,
                periodo: periodo,
                formato: formato,
                ...(dateStart && { date_start: dateStart }),
                ...(dateEnd && { date_end: dateEnd })
            });
            
            // Mostrar loading
            const btnGenerate = event.target;
            const originalText = btnGenerate.innerHTML;
            btnGenerate.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando...';
            btnGenerate.disabled = true;
            
            // Simular geração (substituir por chamada real)
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            alert(`✅ Relatório de ${currentReportType} gerado com sucesso!`);
            closeReportModal();
            
            btnGenerate.innerHTML = originalText;
            btnGenerate.disabled = false;
            
        } catch (error) {
            console.error('Erro:', error);
            alert('❌ Erro ao gerar relatório');
        }
    };

    // Exportar relatório direto
    window.exportReport = async function(type) {
        if (!canExport) {
            alert('❌ Você não tem permissão para exportar relatórios');
            return;
        }
        
        if (confirm(`Exportar relatório de ${type} para Excel?`)) {
            try {
                // Simular exportação (substituir por chamada real)
                const downloadBtn = event.target;
                downloadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                alert(`✅ Relatório de ${type} exportado!`);
                downloadBtn.innerHTML = '<i class="fa-solid fa-download"></i> Exportar';
                
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao exportar');
            }
        }
    };

    // Toggle custom date range
    document.getElementById('reportPeriodo').addEventListener('change', function() {
        const customRange = document.getElementById('customDateRange');
        customRange.style.display = this.value === 'custom' ? 'block' : 'none';
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeReportModal();
        }
    });

    // Fechar modal clicando fora
    document.getElementById('reportModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReportModal();
        }
    });

    console.log('✅ Módulo de Relatórios carregado -', userType, '- User ID:', userId);
})();
</script>