<?php
/**
 * VISIONGREEN - MÓDULO DE VENDAS
 * SQL ATUALIZADO: orders, order_items, products
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../../registration/includes/db.php';

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
        <i class="fa-solid fa-lock" style="font-size: 48px; margin-bottom: 16px;"></i>
        <h3>Acesso Negado</h3>
        <p>Faça login para acessar esta página.</p>
    </div>';
    exit;
}

if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $userName = $_SESSION['employee_auth']['nome'];
    $userType = 'funcionario';
    
    $stmt = $mysqli->prepare("
        SELECT can_view, can_create, can_edit, can_delete 
        FROM employee_permissions 
        WHERE employee_id = ? AND module = 'vendas'
        LIMIT 1
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $permissions = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$permissions || !$permissions['can_view']) {
        echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
            <i class="fa-solid fa-ban" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
            <h3>Acesso Restrito</h3>
            <p>Você não tem permissão para acessar o módulo de Vendas.</p>
            <p style="font-size: 12px; color: #8b949e; margin-top: 16px;">
                Contate o gestor da empresa para solicitar acesso.
            </p>
        </div>';
        exit;
    }
    
    $canView = true;
    $canCreate = (bool)$permissions['can_create'];
    $canEdit = (bool)$permissions['can_edit'];
    $canDelete = (bool)$permissions['can_delete'];
    
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $employeeId = null;
    $userName = $_SESSION['auth']['nome'];
    $userType = 'gestor';
    $canView = true;
    $canCreate = true;
    $canEdit = true;
    $canDelete = true;
}
?>

<style>
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

.vendas-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
}

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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    transition: all 0.2s ease;
}

.stat-card:hover {
    border-color: var(--gh-border-hover);
    transform: translateY(-2px);
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
    font-size: 28px;
    font-weight: 700;
    color: var(--gh-text);
}

.stat-trend {
    font-size: 12px;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stat-trend.positive { color: var(--gh-accent-green-bright); }
.stat-trend.negative { color: var(--gh-accent-red); }

.filters-bar {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
}

.filter-input, .filter-select {
    padding: 5px 12px;
    min-height: 32px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: var(--gh-accent-blue);
}

.btn {
    padding: 5px 16px;
    height: 32px;
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
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

.table-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    overflow: hidden;
}

.table-header {
    padding: 16px;
    border-bottom: 1px solid var(--gh-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--gh-text);
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: var(--gh-bg-primary);
}

th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    border-bottom: 1px solid var(--gh-border);
}

td {
    padding: 12px 16px;
    font-size: 14px;
    color: var(--gh-text);
    border-bottom: 1px solid var(--gh-border);
}

tr:last-child td {
    border-bottom: none;
}

tbody tr {
    transition: background 0.2s ease;
}

tbody tr:hover {
    background: var(--gh-bg-primary);
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-success {
    background: rgba(46, 160, 67, 0.15);
    color: var(--gh-accent-green-bright);
}

.badge-warning {
    background: rgba(210, 153, 34, 0.15);
    color: var(--gh-accent-yellow);
}

.badge-danger {
    background: rgba(218, 54, 51, 0.15);
    color: #ff7b72;
}

.badge-info {
    background: rgba(31, 111, 235, 0.15);
    color: var(--gh-accent-blue);
}

.permission-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(77, 163, 255, 0.1);
    border: 1px solid rgba(77, 163, 255, 0.3);
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #1f6feb;
}

.permission-badge.readonly {
    background: rgba(210, 153, 34, 0.1);
    border-color: rgba(210, 153, 34, 0.3);
    color: #d29922;
}

.permission-badge.full-access {
    background: rgba(46, 160, 67, 0.1);
    border-color: rgba(46, 160, 67, 0.3);
    color: #2ea043;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 64px;
    color: var(--gh-text-muted);
    margin-bottom: 16px;
}

.empty-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 8px;
}

.empty-text {
    font-size: 14px;
    color: var(--gh-text-secondary);
}

.loading {
    text-align: center;
    padding: 40px;
    color: var(--gh-text-secondary);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(46, 160, 67, 0.1);
    border-top-color: var(--gh-accent-green-bright);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .table-wrapper {
        overflow-x: scroll;
    }
}
</style>

<div class="vendas-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-chart-line"></i>
            <?= $isEmployee ? 'Vendas da Empresa' : 'Minhas Vendas' ?>
        </h1>
        <div style="display: flex; align-items: center; gap: 12px;">
            <?php if ($isEmployee): ?>
                <?php if ($canEdit && $canCreate && $canDelete): ?>
                    <span class="permission-badge full-access">
                        <i class="fa-solid fa-shield-check"></i>
                        Controle Total
                    </span>
                <?php elseif ($canEdit): ?>
                    <span class="permission-badge">
                        <i class="fa-solid fa-user-tie"></i>
                        Modo Edição
                    </span>
                <?php else: ?>
                    <span class="permission-badge readonly">
                        <i class="fa-solid fa-eye"></i>
                        Apenas Visualização
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-grid" id="statsGrid">
        <div class="stat-card">
            <div class="stat-label">Total de Vendas</div>
            <div class="stat-value" id="totalVendas">--</div>
            <div class="stat-trend positive">
                <i class="fa-solid fa-arrow-up"></i>
                <span id="vendasTrend">0%</span> vs mês anterior
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Receita Total</div>
            <div class="stat-value" id="receitaTotal">--</div>
            <div class="stat-trend positive">
                <i class="fa-solid fa-arrow-up"></i>
                <span id="receitaTrend">0%</span> este mês
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pagamentos Pendentes</div>
            <div class="stat-value" id="pendentes">--</div>
            <div class="stat-trend">
                <i class="fa-solid fa-clock"></i>
                <span id="pendentesTrend">--</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Taxa de Conversão</div>
            <div class="stat-value" id="conversao">--</div>
            <div class="stat-trend positive">
                <i class="fa-solid fa-arrow-up"></i>
                <span id="conversaoTrend">0%</span>
            </div>
        </div>
    </div>

    <div class="filters-bar">
        <div class="filter-group">
            <label class="filter-label">Período</label>
            <select class="filter-select" id="filterPeriodo">
                <option value="7">Últimos 7 dias</option>
                <option value="30" selected>Últimos 30 dias</option>
                <option value="90">Últimos 90 dias</option>
                <option value="365">Último ano</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select class="filter-select" id="filterStatus">
                <option value="">Todos</option>
                <option value="pendente">Pendente</option>
                <option value="processando">Processando</option>
                <option value="enviado">Enviado</option>
                <option value="entregue">Entregue</option>
                <option value="cancelado">Cancelado</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Produto</label>
            <select class="filter-select" id="filterProduto">
                <option value="">Todos os produtos</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Buscar</label>
            <input type="text" class="filter-input" id="searchInput" placeholder="Cliente, pedido...">
        </div>

        <div class="filter-group" style="margin-top: 18px;">
            <button class="btn btn-primary" onclick="aplicarFiltros()">
                <i class="fa-solid fa-filter"></i>
                Filtrar
            </button>
        </div>

        <?php if ($canEdit || $canCreate): ?>
            <div class="filter-group" style="margin-top: 18px;">
                <button class="btn btn-secondary" onclick="exportarVendas()">
                    <i class="fa-solid fa-download"></i>
                    Exportar
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Histórico de Vendas</div>
            <span class="badge badge-info" id="totalRecords">0 registros</span>
        </div>

        <div class="table-wrapper">
            <table id="vendasTable">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Itens</th>
                        <th>Total</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="vendasBody">
                    <tr>
                        <td colspan="8">
                            <div class="loading">
                                <div class="spinner"></div>
                                <div>Carregando vendas...</div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const userId = <?= $userId ?>;
    const userType = '<?= $userType ?>';
    const isEmployee = <?= $isEmployee ? 'true' : 'false' ?>;
    const canView = <?= $canView ? 'true' : 'false' ?>;
    const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    const canDelete = <?= $canDelete ? 'true' : 'false' ?>;

    console.log('🔐 Permissões:', { canView, canCreate, canEdit, canDelete });

    let vendas = [];
    let produtos = [];

    init();

    async function init() {
        await Promise.all([
            carregarVendas(),
            carregarProdutos(),
            carregarStats()
        ]);
    }

    async function carregarVendas() {
        try {
            const periodo = document.getElementById('filterPeriodo').value;
            const status = document.getElementById('filterStatus').value;
            const produto = document.getElementById('filterProduto').value;
            const search = document.getElementById('searchInput').value;

            const params = new URLSearchParams({
                user_id: userId,
                periodo: periodo,
                ...(status && { status }),
                ...(produto && { produto_id: produto }),
                ...(search && { search })
            });

            const response = await fetch(`modules/vendas/actions/buscar_vendas.php?${params}`);
            const data = await response.json();

            if (data.success) {
                vendas = data.vendas;
                renderVendas();
                document.getElementById('totalRecords').textContent = `${vendas.length} registros`;
            } else {
                mostrarErro('Erro ao carregar vendas');
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarErro('Erro ao carregar vendas');
        }
    }

    async function carregarProdutos() {
        try {
            const response = await fetch(`modules/vendas/actions/listar_produtos.php?user_id=${userId}`);
            const data = await response.json();

            if (data.success) {
                produtos = data.produtos;
                renderProdutosFilter();
            }
        } catch (error) {
            console.error('Erro:', error);
        }
    }

    async function carregarStats() {
        try {
            const response = await fetch(`modules/vendas/actions/stats_vendas.php?user_id=${userId}`);
            const data = await response.json();

            if (data.success) {
                document.getElementById('totalVendas').textContent = data.total_vendas;
                document.getElementById('receitaTotal').textContent = formatMoney(data.receita_total);
                document.getElementById('pendentes').textContent = data.pendentes;
                document.getElementById('conversao').textContent = data.taxa_conversao + '%';
                
                document.getElementById('vendasTrend').textContent = data.vendas_trend + '%';
                document.getElementById('receitaTrend').textContent = data.receita_trend + '%';
                document.getElementById('pendentesTrend').textContent = formatMoney(data.valor_pendente);
                document.getElementById('conversaoTrend').textContent = data.conversao_trend + '%';
            }
        } catch (error) {
            console.error('Erro:', error);
        }
    }

    function setupAutoBusca() {
        let timeout = null;
        
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                carregarVendas();
            }, 500);
        });
        
        document.getElementById('filterPeriodo').addEventListener('change', carregarVendas);
        document.getElementById('filterStatus').addEventListener('change', carregarVendas);
        document.getElementById('filterProduto').addEventListener('change', carregarVendas);
    }

    setupAutoBusca();

    function renderVendas() {
        const tbody = document.getElementById('vendasBody');

        if (vendas.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div class="empty-title">Nenhuma venda encontrada</div>
                            <div class="empty-text">Você ainda não realizou nenhuma venda</div>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = vendas.map(venda => `
            <tr>
                <td><strong>#${venda.order_number}</strong></td>
                <td>${formatDate(venda.order_date)}</td>
                <td>${venda.cliente_nome || 'N/A'}</td>
                <td>${venda.total_items} item(s)</td>
                <td><strong>${formatMoney(venda.total)} ${venda.currency || 'MZN'}</strong></td>
                <td>${getPaymentBadge(venda.payment_status)}</td>
                <td>${getStatusBadge(venda.status)}</td>
                <td>
                    <button class="btn btn-secondary" onclick="verDetalhes(${venda.id})" title="Ver detalhes">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderProdutosFilter() {
        const select = document.getElementById('filterProduto');
        select.innerHTML = '<option value="">Todos os produtos</option>' +
            produtos.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
    }

    function getStatusBadge(status) {
        const badges = {
            'pendente': '<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pendente</span>',
            'processando': '<span class="badge badge-info"><i class="fa-solid fa-hourglass-half"></i> Processando</span>',
            'enviado': '<span class="badge badge-info"><i class="fa-solid fa-truck"></i> Enviado</span>',
            'entregue': '<span class="badge badge-success"><i class="fa-solid fa-check"></i> Entregue</span>',
            'cancelado': '<span class="badge badge-danger"><i class="fa-solid fa-times"></i> Cancelado</span>'
        };
        return badges[status] || status;
    }

    function getPaymentBadge(status) {
        const badges = {
            'pago': '<span class="badge badge-success"><i class="fa-solid fa-check-circle"></i> Pago</span>',
            'pendente': '<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pendente</span>',
            'cancelado': '<span class="badge badge-danger"><i class="fa-solid fa-ban"></i> Cancelado</span>'
        };
        return badges[status] || status;
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('pt-MZ', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    }

    function formatDate(date) {
        return new Date(date).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    window.aplicarFiltros = function() {
        carregarVendas();
    };

    window.verDetalhes = async function(vendaId) {
        alert('Detalhes do pedido #' + vendaId);
    };

    window.exportarVendas = function() {
        if (!canEdit && !canCreate) {
            alert('❌ Você não tem permissão para exportar relatórios');
            return;
        }
        const csv = generateCSV(vendas);
        downloadCSV(csv, 'vendas_' + new Date().toISOString().split('T')[0] + '.csv');
    };

    function generateCSV(data) {
        const headers = ['Pedido', 'Data', 'Cliente', 'Itens', 'Total', 'Moeda', 'Pagamento', 'Status'];
        const rows = data.map(v => [
            v.order_number,
            formatDate(v.order_date),
            v.cliente_nome || 'N/A',
            v.total_items,
            v.total,
            v.currency || 'MZN',
            v.payment_status,
            v.status
        ]);
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    }

    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
    }

    function mostrarErro(msg) {
        console.error(msg);
    }

    console.log('✅ Módulo de Vendas carregado -', userType, '- User ID:', userId);
})();
</script>