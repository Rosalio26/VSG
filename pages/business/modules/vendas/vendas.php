<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE VENDAS
 * Arquivo: pages/business/modules/vendas/vendas.php
 * Descrição: Visualização de vendas e pagamentos da empresa
 * ================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Acesso Negado</div>';
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
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

.vendas-container {
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

/* Stats Cards */
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

/* Filters */
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

/* Table */
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

/* Badges */
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

/* Empty State */
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

/* Loading */
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

/* Responsive */
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
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-chart-line"></i>
            Minhas Vendas
        </h1>
    </div>

    <!-- Stats Cards -->
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

    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <label class="filter-label">Período</label>
            <select class="filter-select" id="filterPeriodo">
                <option value="7">Últimos 7 dias</option>
                <option value="30" selected>Últimos 30 dias</option>
                <option value="90">Últimos 90 dias</option>
                <option value="365">Último ano</option>
                <option value="custom">Personalizado</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select class="filter-select" id="filterStatus">
                <option value="">Todos</option>
                <option value="pending">Pendente</option>
                <option value="completed">Completo</option>
                <option value="cancelled">Cancelado</option>
                <option value="refunded">Reembolsado</option>
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
            <input type="text" class="filter-input" id="searchInput" placeholder="Cliente, invoice...">
        </div>

        <div class="filter-group" style="margin-top: 18px;">
            <button class="btn btn-primary" onclick="aplicarFiltros()">
                <i class="fa-solid fa-filter"></i>
                Filtrar
            </button>
        </div>

        <div class="filter-group" style="margin-top: 18px;">
            <button class="btn btn-secondary" onclick="exportarVendas()">
                <i class="fa-solid fa-download"></i>
                Exportar
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Histórico de Vendas</div>
            <span class="badge badge-info" id="totalRecords">0 registros</span>
        </div>

        <div class="table-wrapper">
            <table id="vendasTable">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Valor Unit.</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="vendasBody">
                    <tr>
                        <td colspan="9">
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
    let vendas = [];
    let produtos = [];

    // Carregar dados ao iniciar
    init();

    async function init() {
        await Promise.all([
            carregarVendas(),
            carregarProdutos(),
            carregarStats()
        ]);
    }

    // Carregar vendas
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

    // Carregar produtos
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

    // Carregar estatísticas
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

    // Setup busca automática
    function setupAutoBusca() {
        let timeout = null;
        
        // Busca automática ao digitar (com debounce)
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                carregarVendas();
            }, 500); // 500ms após parar de digitar
        });
        
        // Busca automática ao mudar filtros
        document.getElementById('filterPeriodo').addEventListener('change', carregarVendas);
        document.getElementById('filterStatus').addEventListener('change', carregarVendas);
        document.getElementById('filterProduto').addEventListener('change', carregarVendas);
    }

    // Inicializar busca automática
    setupAutoBusca();

    // Render vendas
    function renderVendas() {
        const tbody = document.getElementById('vendasBody');

        if (vendas.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
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
                <td><strong>#${venda.invoice_number}</strong></td>
                <td>${formatDate(venda.purchase_date)}</td>
                <td>${venda.cliente_nome || 'N/A'}</td>
                <td>${venda.produto_nome}</td>
                <td>${venda.quantity}</td>
                <td>${formatMoney(venda.unit_price)}</td>
                <td><strong>${formatMoney(venda.total_amount)}</strong></td>
                <td>${getStatusBadge(venda.status)}</td>
                <td>
                    <button class="btn btn-secondary" onclick="verDetalhes(${venda.id})" title="Ver detalhes">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // Render produtos filter
    function renderProdutosFilter() {
        const select = document.getElementById('filterProduto');
        select.innerHTML = '<option value="">Todos os produtos</option>' +
            produtos.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
    }

    // Status badge
    function getStatusBadge(status) {
        const badges = {
            'pending': '<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pendente</span>',
            'completed': '<span class="badge badge-success"><i class="fa-solid fa-check"></i> Completo</span>',
            'cancelled': '<span class="badge badge-danger"><i class="fa-solid fa-times"></i> Cancelado</span>',
            'refunded': '<span class="badge badge-info"><i class="fa-solid fa-undo"></i> Reembolsado</span>'
        };
        return badges[status] || status;
    }

    // Format money
    function formatMoney(value) {
        return new Intl.NumberFormat('pt-MZ', {
            style: 'currency',
            currency: 'MZN'
        }).format(value);
    }

    // Format date
    function formatDate(date) {
        return new Date(date).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Aplicar filtros
    window.aplicarFiltros = function() {
        carregarVendas();
    };

    // Ver detalhes
    window.verDetalhes = async function(vendaId) {
        const venda = vendas.find(v => v.id === vendaId);
        if (!venda) return;

        // Buscar detalhes completos
        try {
            const response = await fetch(`modules/vendas/actions/detalhes_venda.php?venda_id=${vendaId}`);
            const data = await response.json();

            if (data.success) {
                mostrarModalDetalhes(data.venda);
            } else {
                alert('Erro ao carregar detalhes');
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao carregar detalhes');
        }
    };

    // Mostrar modal de detalhes
    function mostrarModalDetalhes(venda) {
        const modal = document.createElement('div');
        modal.id = 'modalDetalhes';
        modal.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(1, 4, 9, 0.8); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;">
                <div style="background: var(--gh-bg-secondary); border: 1px solid var(--gh-border); border-radius: 8px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);">
                    <!-- Header -->
                    <div style="padding: 20px; border-bottom: 1px solid var(--gh-border); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--gh-text);">
                                <i class="fa-solid fa-file-invoice"></i>
                                Detalhes da Venda
                            </h3>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--gh-text-secondary);">
                                Invoice #${venda.invoice_number || 'N/A'}
                            </p>
                        </div>
                        <button onclick="fecharModal()" style="background: none; border: none; color: var(--gh-text-secondary); font-size: 24px; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s;">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div style="padding: 20px;">
                        <!-- Cliente -->
                        <div style="background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-user"></i> Cliente
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Nome</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.cliente_nome || 'N/A'}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Email</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.cliente_email || 'N/A'}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Telefone</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.cliente_telefone || 'N/A'}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Tipo</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.cliente_tipo || 'N/A'}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Produto -->
                        <div style="background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-box"></i> Produto
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div style="grid-column: 1 / -1;">
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Nome</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.produto_nome}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Categoria</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.produto_categoria || 'N/A'}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Categoria Ecológica</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.produto_eco_categoria || 'N/A'}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Valores -->
                        <div style="background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-calculator"></i> Valores
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Quantidade</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.quantity} unidade(s)</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Valor Unitário</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${formatMoney(venda.unit_price)}</div>
                                </div>
                                <div style="grid-column: 1 / -1; padding-top: 12px; border-top: 1px solid var(--gh-border);">
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Total</div>
                                    <div style="font-size: 20px; color: var(--gh-accent-green-bright); font-weight: 700;">${formatMoney(venda.total_amount)}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Informações da Compra -->
                        <div style="background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; padding: 16px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-info-circle"></i> Informações da Compra
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Data da Compra</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${formatDate(venda.purchase_date)}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Status</div>
                                    <div>${getStatusBadge(venda.status)}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Método de Pagamento</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${venda.payment_method || 'N/A'}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">ID da Transação</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">#${venda.transaction_id || 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div style="padding: 16px 20px; border-top: 1px solid var(--gh-border); display: flex; gap: 12px; justify-content: flex-end;">
                        <button onclick="fecharModal()" class="btn btn-secondary">
                            <i class="fa-solid fa-times"></i> Fechar
                        </button>
                        <button onclick="imprimirVenda(${venda.id})" class="btn btn-primary">
                            <i class="fa-solid fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        
        // Adicionar evento de fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModal();
        });
    }

    // Fechar modal
    window.fecharModal = function() {
        const modal = document.getElementById('modalDetalhes');
        if (modal) {
            modal.remove();
        }
    };

    // Imprimir venda
    window.imprimirVenda = function(vendaId) {
        alert('Funcionalidade de impressão será implementada');
    };

    // Exportar vendas
    window.exportarVendas = function() {
        const csv = generateCSV(vendas);
        downloadCSV(csv, 'vendas_' + new Date().toISOString().split('T')[0] + '.csv');
    };

    function generateCSV(data) {
        const headers = ['Invoice', 'Data', 'Cliente', 'Produto', 'Quantidade', 'Valor Unitário', 'Total', 'Status'];
        const rows = data.map(v => [
            v.invoice_number,
            formatDate(v.purchase_date),
            v.cliente_nome || 'N/A',
            v.produto_nome,
            v.quantity,
            v.unit_price,
            v.total_amount,
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

    console.log('✅ Módulo de Vendas carregado');
})();
</script>