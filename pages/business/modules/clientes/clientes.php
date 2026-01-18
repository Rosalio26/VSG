<?php
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
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    
    $stmt = $mysqli->prepare("
        SELECT can_view FROM employee_permissions 
        WHERE employee_id = ? AND module = 'clientes'
        LIMIT 1
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $permissions = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$permissions || !$permissions['can_view']) {
        echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
            <i class="fa-solid fa-ban" style="font-size: 48px; margin-bottom: 16px;"></i>
            <h3>Acesso Restrito</h3>
            <p>Você não tem permissão para acessar este módulo.</p>
        </div>';
        exit;
    }
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
}
?>

<style>
:root {
    --gh-bg-primary: #0d1117;
    --gh-bg-secondary: #161b22;
    --gh-bg-tertiary: #21262d;
    --gh-border: #30363d;
    --gh-text: #c9d1d9;
    --gh-text-secondary: #8b949e;
    --gh-green: #238636;
    --gh-blue: #1f6feb;
    --gh-orange: #d29922;
}

.clientes-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
}

.clientes-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--gh-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    border-color: var(--gh-text-secondary);
    transform: translateY(-2px);
}

.stat-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--gh-text);
}

.toolbar {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.search-input, .filter-select, .filter-date {
    height: 32px;
    padding: 0 12px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
    outline: none;
}

.search-input {
    flex: 1;
    min-width: 200px;
}

.search-input:focus, .filter-select:focus, .filter-date:focus {
    border-color: var(--gh-blue);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.1);
}

.filter-select {
    min-width: 150px;
}

.filter-date {
    min-width: 140px;
}

.clientes-table {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead {
    background: var(--gh-bg-tertiary);
}

.table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    border-bottom: 1px solid var(--gh-border);
}

.table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gh-border);
    color: var(--gh-text);
    font-size: 14px;
}

.table tbody tr:hover {
    background: var(--gh-bg-tertiary);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

.btn {
    height: 32px;
    padding: 0 16px;
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    color: var(--gh-text);
    transition: all 0.15s;
}

.btn:hover {
    background: var(--gh-bg-tertiary);
}

.btn-icon {
    height: 28px;
    width: 28px;
    padding: 0;
    justify-content: center;
}

.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    background: var(--gh-bg-tertiary);
    color: var(--gh-text-secondary);
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: var(--gh-text-secondary);
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

#alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 400px;
}

.alert {
    padding: 16px 20px;
    border: 1px solid;
    border-radius: 8px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    animation: slideInRight 0.3s ease-out;
}

.alert-success { background: rgba(46, 160, 67, 0.15); border-color: rgba(46, 160, 67, 0.5); color: #3fb950; }
.alert-error { background: rgba(248, 81, 73, 0.15); border-color: rgba(248, 81, 73, 0.5); color: #f85149; }

.alert-close {
    background: none;
    border: none;
    color: currentColor;
    cursor: pointer;
    padding: 0;
    opacity: 0.6;
    font-size: 18px;
}

@keyframes slideInRight {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<div id="alert-container"></div>

<div style="padding: 16px; background: rgba(77, 163, 255, 0.1); border: 1px solid rgba(77, 163, 255, 0.3); border-radius: 8px; margin-bottom: 16px;">
    <p style="color: var(--gh-text); font-size: 14px; margin: 0;">
        <i class="fa-solid fa-info-circle"></i>
        <strong>Informação:</strong> Lista de clientes que compraram seus produtos. Busque por nome, email, telefone ou produto comprado.
    </p>
</div>

<div class="clientes-header">
    <h1 class="clientes-title">
        <i class="fa-solid fa-users"></i>
        Clientes que Compraram
    </h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Clientes</div>
        <div class="stat-value" id="totalClientes">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Pedidos</div>
        <div class="stat-value" id="totalPedidos">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Faturamento</div>
        <div class="stat-value" id="faturamentoTotal" style="font-size: 20px;">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Valor Pago</div>
        <div class="stat-value" id="valorPago" style="font-size: 20px; color: var(--gh-green);">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pendente</div>
        <div class="stat-value" id="valorPendente" style="font-size: 20px; color: var(--gh-orange);">--</div>
    </div>
</div>

<div class="toolbar">
    <input type="text" class="search-input" id="searchInput" placeholder="Buscar por cliente ou produto..." autocomplete="off">
    
    <select class="filter-select" id="orderStatusFilter">
        <option value="">Status Pedido</option>
        <option value="pending">Pendente</option>
        <option value="confirmed">Confirmado</option>
        <option value="processing">Processando</option>
        <option value="shipped">Enviado</option>
        <option value="delivered">Entregue</option>
        <option value="cancelled">Cancelado</option>
    </select>
    
    <select class="filter-select" id="paymentStatusFilter">
        <option value="">Status Pagamento</option>
        <option value="pending">Pendente</option>
        <option value="paid">Pago</option>
        <option value="partial">Parcial</option>
        <option value="refunded">Reembolsado</option>
    </select>
    
    <input type="date" class="filter-date" id="dateFrom" placeholder="De">
    <input type="date" class="filter-date" id="dateTo" placeholder="Até">
    
    <button class="btn" id="clearFilters">
        <i class="fa-solid fa-filter-circle-xmark"></i>
        Limpar
    </button>
</div>

<div class="clientes-table" id="clientesTable">
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
        <h3 style="margin: 0; font-size: 16px; color: var(--gh-text);">Carregando clientes...</h3>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const state = {
        search: '',
        order_status: '',
        payment_status: '',
        date_from: '',
        date_to: ''
    };
    
    const elements = {
        searchInput: document.getElementById('searchInput'),
        orderStatusFilter: document.getElementById('orderStatusFilter'),
        paymentStatusFilter: document.getElementById('paymentStatusFilter'),
        dateFrom: document.getElementById('dateFrom'),
        dateTo: document.getElementById('dateTo'),
        clearFilters: document.getElementById('clearFilters'),
        clientesTable: document.getElementById('clientesTable'),
        alertContainer: document.getElementById('alert-container')
    };
    
    async function loadClientes() {
        const params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.order_status) params.set('order_status', state.order_status);
        if (state.payment_status) params.set('payment_status', state.payment_status);
        if (state.date_from) params.set('date_from', state.date_from);
        if (state.date_to) params.set('date_to', state.date_to);
        
        try {
            const response = await fetch(`modules/clientes/actions/search_clientes.php?${params.toString()}`);
            const data = await response.json();
            
            if (!data.success) {
                showAlert('error', data.message || 'Erro ao carregar clientes');
                return;
            }
            
            renderClientes(data.clientes);
            updateStats(data.stats);
            
        } catch (error) {
            console.error('Erro:', error);
            showAlert('error', 'Erro ao carregar clientes');
        }
    }
    
    function updateStats(stats) {
        document.getElementById('totalClientes').textContent = stats.total || 0;
        document.getElementById('totalPedidos').textContent = stats.total_pedidos || 0;
        document.getElementById('faturamentoTotal').textContent = formatMoney(stats.faturamento_total || 0);
        document.getElementById('valorPago').textContent = formatMoney(stats.valor_pago || 0);
        document.getElementById('valorPendente').textContent = formatMoney(stats.valor_pendente || 0);
    }
    
    function renderClientes(clientes) {
        if (!elements.clientesTable) return;
        
        if (clientes.length === 0) {
            elements.clientesTable.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; color: var(--gh-text);">Nenhum cliente encontrado</h3>
                    <p style="margin: 0;">Tente ajustar os filtros de busca.</p>
                </div>
            `;
            return;
        }
        
        let tableHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contato</th>
                        <th>Produtos Comprados</th>
                        <th>Pedidos</th>
                        <th>Total Gasto</th>
                        <th>Último Pedido</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        clientes.forEach(cliente => {
            const produtos = cliente.produtos_comprados ? cliente.produtos_comprados.split(', ').slice(0, 3) : [];
            const produtosText = produtos.join(', ') + (produtos.length < cliente.produtos_comprados.split(', ').length ? '...' : '');
            
            tableHTML += `
                <tr>
                    <td>
                        <div><strong>${escapeHtml(cliente.nome)} ${escapeHtml(cliente.apelido || '')}</strong></div>
                        <div style="font-size: 12px; color: var(--gh-text-secondary);">Cliente desde ${formatDate(cliente.created_at)}</div>
                    </td>
                    <td>
                        <div>${escapeHtml(cliente.email)}</div>
                        <div style="font-size: 12px; color: var(--gh-text-secondary);">${escapeHtml(cliente.telefone)}</div>
                    </td>
                    <td>
                        <div style="font-size: 12px; color: var(--gh-text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(cliente.produtos_comprados || '')}">
                            ${escapeHtml(produtosText || '-')}
                        </div>
                    </td>
                    <td><span class="badge">${cliente.total_pedidos}</span></td>
                    <td><strong>${formatMoney(cliente.total_gasto)} MZN</strong></td>
                    <td>${formatDate(cliente.ultimo_pedido)}</td>
                    <td>
                        <button class="btn btn-icon" onclick="window.ClientesModule.viewDetails(${cliente.id})" title="Ver Detalhes">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tableHTML += `</tbody></table>`;
        elements.clientesTable.innerHTML = tableHTML;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('pt-BR');
    }
    
    function formatMoney(value) {
        return parseFloat(value).toFixed(2).replace('.', ',');
    }
    
    // Event listeners
    let searchTimeout;
    elements.searchInput.addEventListener('input', (e) => {
        state.search = e.target.value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadClientes, 400);
    });
    
    elements.orderStatusFilter.addEventListener('change', (e) => {
        state.order_status = e.target.value;
        loadClientes();
    });
    
    elements.paymentStatusFilter.addEventListener('change', (e) => {
        state.payment_status = e.target.value;
        loadClientes();
    });
    
    elements.dateFrom.addEventListener('change', (e) => {
        state.date_from = e.target.value;
        loadClientes();
    });
    
    elements.dateTo.addEventListener('change', (e) => {
        state.date_to = e.target.value;
        loadClientes();
    });
    
    elements.clearFilters.addEventListener('click', () => {
        state.search = '';
        state.order_status = '';
        state.payment_status = '';
        state.date_from = '';
        state.date_to = '';
        
        elements.searchInput.value = '';
        elements.orderStatusFilter.value = '';
        elements.paymentStatusFilter.value = '';
        elements.dateFrom.value = '';
        elements.dateTo.value = '';
        
        loadClientes();
    });
    
    async function viewDetails(id) {
        try {
            const response = await fetch(`modules/clientes/actions/get_cliente_detalhes.php?id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                let msg = `CLIENTE: ${data.cliente.nome} ${data.cliente.apelido || ''}\n`;
                msg += `Email: ${data.cliente.email}\n`;
                msg += `Telefone: ${data.cliente.telefone}\n\n`;
                msg += `ESTATÍSTICAS:\n`;
                msg += `Total de Pedidos: ${data.stats.total_pedidos}\n`;
                msg += `Total Gasto: ${formatMoney(data.stats.total_gasto)} MZN\n`;
                msg += `Ticket Médio: ${formatMoney(data.stats.ticket_medio)} MZN\n\n`;
                msg += `ÚLTIMOS PEDIDOS:\n`;
                data.pedidos.slice(0, 5).forEach(p => {
                    msg += `${p.order_number} - ${formatDate(p.order_date)} - ${formatMoney(p.total)} ${p.currency}\n`;
                });
                alert(msg);
            } else {
                showAlert('error', data.message);
            }
        } catch (error) {
            showAlert('error', 'Erro ao buscar detalhes');
        }
    }
    
    function showAlert(type, message) {
        if (!elements.alertContainer) return;
        
        const config = {
            success: { icon: 'fa-circle-check', title: 'Sucesso!' },
            error: { icon: 'fa-circle-exclamation', title: 'Erro!' }
        };
        
        const c = config[type] || config.error;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fa-solid ${c.icon}"></i>
            <div>
                <div style="font-weight: 600;">${c.title}</div>
                <div style="font-size: 13px; opacity: 0.9;">${message}</div>
            </div>
            <button class="alert-close"><i class="fa-solid fa-xmark"></i></button>
        `;
        
        elements.alertContainer.appendChild(alert);
        alert.querySelector('.alert-close').addEventListener('click', () => alert.remove());
        setTimeout(() => alert.remove(), 5000);
    }
    
    window.ClientesModule = { viewDetails };
    
    loadClientes();
    
})();
</script>