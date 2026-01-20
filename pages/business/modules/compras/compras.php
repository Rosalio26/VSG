<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE PEDIDOS/VENDAS
 * Arquivo: pages/business/modules/compras/compras.php
 * ✅ ATUALIZADO: Confirmação de pagamento manual incluída
 * ================================================================================
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
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $userName = $_SESSION['employee_auth']['nome'];
    
    $stmt = $mysqli->prepare("
        SELECT can_view, can_edit, can_delete 
        FROM employee_permissions 
        WHERE employee_id = ? AND module IN ('vendas', 'compras')
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
        </div>';
        exit;
    }
    
    $canView = true;
    $canEdit = (bool)$permissions['can_edit'];
    $canDelete = (bool)$permissions['can_delete'];
    
    // Verificar se pode confirmar pagamentos
    $stmt = $mysqli->prepare("SELECT pode_confirmar_pagamentos FROM employees WHERE id = ?");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $empData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $canConfirmPayment = (bool)$empData['pode_confirmar_pagamentos'];
    
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
    $employeeId = null;
    $userName = $_SESSION['auth']['nome'];
    $canView = true;
    $canEdit = true;
    $canDelete = true;
    $canConfirmPayment = true;
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
    --gh-green-bright: #2ea043;
    --gh-blue: #1f6feb;
    --gh-red: #da3633;
    --gh-orange: #d29922;
    --gh-yellow: #e3b341;
}

.compras-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
}

.compras-title {
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    letter-spacing: 0.5px;
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
}

.search-input, .filter-select {
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

.search-input:focus, .filter-select:focus {
    border-color: var(--gh-blue);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.1);
}

.btn {
    height: 32px;
    padding: 0 16px;
    border: 1px solid;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}

.btn-primary {
    background: var(--gh-green);
    border-color: rgba(240, 246, 252, 0.1);
    color: #fff;
}

.btn-primary:hover {
    background: var(--gh-green-bright);
}

.btn-secondary {
    background: transparent;
    border-color: var(--gh-border);
    color: var(--gh-text);
}

.btn-secondary:hover {
    background: var(--gh-bg-tertiary);
}

.btn-danger {
    background: var(--gh-red);
    border-color: rgba(240, 246, 252, 0.1);
    color: #fff;
}

.btn-danger:hover {
    background: #b62324;
}

.btn-warning {
    background: var(--gh-yellow);
    border-color: rgba(240, 246, 252, 0.1);
    color: #000;
    font-weight: 600;
}

.btn-warning:hover {
    background: #d4a537;
}

.btn-icon {
    height: 28px;
    width: 28px;
    padding: 0;
    justify-content: center;
}

.compras-table {
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

.badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border: 1px solid;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.badge-pending { background: rgba(210, 153, 34, 0.1); border-color: rgba(210, 153, 34, 0.4); color: #d29922; }
.badge-confirmed { background: rgba(31, 111, 235, 0.15); border-color: rgba(31, 111, 235, 0.4); color: #58a6ff; }
.badge-processing { background: rgba(137, 87, 229, 0.15); border-color: rgba(137, 87, 229, 0.4); color: #a371f7; }
.badge-shipped { background: rgba(46, 160, 67, 0.1); border-color: rgba(46, 160, 67, 0.4); color: #3fb950; }
.badge-delivered { background: rgba(46, 160, 67, 0.2); border-color: rgba(46, 160, 67, 0.5); color: #2ea043; }
.badge-cancelled { background: rgba(248, 81, 73, 0.1); border-color: rgba(248, 81, 73, 0.4); color: #f85149; }

.badge-paid { background: rgba(46, 160, 67, 0.1); border-color: rgba(46, 160, 67, 0.4); color: #3fb950; }
.badge-partial { background: rgba(210, 153, 34, 0.1); border-color: rgba(210, 153, 34, 0.4); color: #d29922; }
.badge-refunded { background: rgba(248, 81, 73, 0.1); border-color: rgba(248, 81, 73, 0.4); color: #f85149; }

.payment-alert {
    background: rgba(227, 179, 65, 0.15);
    border: 1px solid rgba(227, 179, 65, 0.5);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #e3b341;
    display: inline-flex;
    align-items: center;
    gap: 4px;
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
    pointer-events: none;
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
    pointer-events: all;
    position: relative;
    min-width: 300px;
}

.alert-success { background: rgba(46, 160, 67, 0.15); border-color: rgba(46, 160, 67, 0.5); color: #3fb950; }
.alert-error { background: rgba(248, 81, 73, 0.15); border-color: rgba(248, 81, 73, 0.5); color: #f85149; }

.alert-close {
    background: none;
    border: none;
    color: currentColor;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    opacity: 0.6;
    font-size: 18px;
}

.alert-close:hover {
    opacity: 1;
}

.alert-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: currentColor;
    opacity: 0.3;
    animation: progressBar 5s linear forwards;
}

@keyframes slideInRight {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes progressBar {
    from { width: 100%; }
    to { width: 0%; }
}

@media (max-width: 768px) {
    #alert-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
}
</style>

<div id="alert-container"></div>

<?php if ($isEmployee): ?>
<div style="padding: 16px; background: rgba(77, 163, 255, 0.1); border: 1px solid rgba(77, 163, 255, 0.3); border-radius: 8px; margin-bottom: 16px;">
    <p style="color: var(--gh-text); font-size: 14px; margin: 0;">
        <i class="fa-solid fa-info-circle"></i>
        <strong>Modo Funcionário:</strong> Visualizando pedidos/vendas da empresa.
        <?php if ($canConfirmPayment): ?>
            <span style="color: var(--gh-green); margin-left: 10px;">
                <i class="fa-solid fa-check-circle"></i> Autorizado a confirmar pagamentos
            </span>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div class="compras-header">
    <h1 class="compras-title">
        <i class="fa-solid fa-shopping-bag"></i>
        Pedidos / Vendas
    </h1>
    <div style="display: flex; gap: 8px;">
        <button class="btn btn-secondary" onclick="window.ComprasModule.exportar()">
            <i class="fa-solid fa-download"></i>
            Exportar
        </button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total de Pedidos</div>
        <div class="stat-value" id="totalPedidos">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Faturamento Total</div>
        <div class="stat-value" id="faturamentoTotal" style="font-size: 20px;">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pendentes</div>
        <div class="stat-value" id="pedidosPendentes">--</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Entregues</div>
        <div class="stat-value" id="pedidosEntregues">--</div>
    </div>
</div>

<div class="toolbar">
    <input type="text" class="search-input" id="searchInput" placeholder="Buscar pedidos..." autocomplete="off">
    <select class="filter-select" id="statusFilter">
        <option value="">Status do Pedido</option>
        <option value="pending">Pendente</option>
        <option value="confirmed">Confirmado</option>
        <option value="processing">Processando</option>
        <option value="shipped">Enviado</option>
        <option value="delivered">Entregue</option>
        <option value="cancelled">Cancelado</option>
    </select>
    <select class="filter-select" id="paymentFilter">
        <option value="">Status Pagamento</option>
        <option value="pending">Pendente</option>
        <option value="paid">Pago</option>
        <option value="partial">Parcial</option>
        <option value="refunded">Reembolsado</option>
    </select>
</div>

<div class="compras-table" id="comprasTable">
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
        <h3 style="margin: 0; font-size: 16px; color: var(--gh-text);">Carregando pedidos...</h3>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const companyId = <?= $companyId ?>;
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
    const canConfirmPayment = <?= $canConfirmPayment ? 'true' : 'false' ?>;
    
    const state = {
        search: '',
        status: '',
        payment_status: ''
    };
    
    const elements = {
        searchInput: document.getElementById('searchInput'),
        statusFilter: document.getElementById('statusFilter'),
        paymentFilter: document.getElementById('paymentFilter'),
        comprasTable: document.getElementById('comprasTable'),
        alertContainer: document.getElementById('alert-container')
    };
    
    async function loadCompras() {
        const params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.status) params.set('status', state.status);
        if (state.payment_status) params.set('payment_status', state.payment_status);
        
        try {
            const response = await fetch(`modules/compras/actions/search_compras.php?${params.toString()}`);
            const data = await response.json();
            
            if (!data.success) {
                showAlert('error', data.message || 'Erro ao carregar pedidos');
                return;
            }
            
            renderCompras(data.compras);
            updateStats(data.stats);
            
        } catch (error) {
            console.error('Erro:', error);
            showAlert('error', 'Erro ao carregar pedidos');
        }
    }
    
    function updateStats(stats) {
        document.getElementById('totalPedidos').textContent = stats.total || 0;
        document.getElementById('faturamentoTotal').textContent = formatMoney(stats.faturamento_total || 0) + ' MZN';
        document.getElementById('pedidosPendentes').textContent = stats.pendentes || 0;
        document.getElementById('pedidosEntregues').textContent = stats.entregues || 0;
    }
    
    function renderCompras(compras) {
        if (!elements.comprasTable) return;
        
        if (compras.length === 0) {
            elements.comprasTable.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-shopping-bag"></i></div>
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; color: var(--gh-text);">Nenhum pedido encontrado</h3>
                    <p style="margin: 0;">Aguardando clientes realizarem compras.</p>
                </div>
            `;
            return;
        }
        
        const statusLabels = {
            pending: 'Pendente', confirmed: 'Confirmado', processing: 'Processando',
            shipped: 'Enviado', delivered: 'Entregue', cancelled: 'Cancelado'
        };
        
        const paymentLabels = {
            pending: 'Pendente', paid: 'Pago', partial: 'Parcial', refunded: 'Reembolsado'
        };
        
        const paymentMethodLabels = {
            mpesa: 'M-Pesa', emola: 'E-Mola', visa: 'Visa', 
            mastercard: 'Mastercard', manual: 'Manual'
        };
        
        let tableHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Nº Pedido</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Itens</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        compras.forEach(compra => {
            const isManualPayment = compra.payment_method === 'manual';
            const isCancelled = compra.status === 'cancelled';
            const isPendingPayment = compra.payment_status === 'pending' && isManualPayment && !isCancelled;
            
            tableHTML += `
                <tr ${isPendingPayment ? 'style="background: rgba(227, 179, 65, 0.05);"' : ''}>
                    <td>
                        <strong>${escapeHtml(compra.order_number)}</strong>
                        ${isPendingPayment ? '<br><span class="payment-alert"><i class="fa-solid fa-exclamation-triangle"></i> Aguardando Confirmação</span>' : ''}
                    </td>
                    <td>
                        <div>${escapeHtml(compra.customer_name)}</div>
                        <div style="font-size: 12px; color: var(--gh-text-secondary);">${escapeHtml(compra.customer_email)}</div>
                        ${isManualPayment ? '<div style="font-size: 11px; color: var(--gh-yellow); margin-top: 2px;"><i class="fa-solid fa-hand-holding-dollar"></i> Pag. Manual</div>' : ''}
                    </td>
                    <td>${formatDate(compra.order_date)}</td>
                    <td>${compra.items_count || 0}</td>
                    <td><strong>${formatMoney(compra.total)} ${compra.currency}</strong></td>
                    <td><span class="badge badge-${compra.status}">${statusLabels[compra.status]}</span></td>
                    <td><span class="badge badge-${compra.payment_status}">${paymentLabels[compra.payment_status]}</span></td>
                    <td>
                        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                            <button class="btn btn-secondary btn-icon" onclick="window.ComprasModule.viewDetails(${compra.id})" title="Ver Detalhes">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            
                            ${canConfirmPayment && isPendingPayment ? `
                            <button class="btn btn-warning btn-icon" onclick="window.ComprasModule.confirmarPagamento(${compra.id})" title="Confirmar Pagamento Manual">
                                <i class="fa-solid fa-check-circle"></i>
                            </button>
                            ` : ''}
                            
                            ${compra.payment_status === 'paid' ? `
                            <button class="btn btn-secondary btn-icon" onclick="window.ComprasModule.gerarRecibo(${compra.id})" title="Gerar Recibo PDF">
                                <i class="fa-solid fa-file-pdf"></i>
                            </button>
                            ` : ''}
                            
                            ${canEdit ? `
                            <button class="btn btn-secondary btn-icon" onclick="window.ComprasModule.updateStatus(${compra.id}, '${compra.status}')" title="Atualizar Status">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            ` : ''}
                            
                            ${canEdit && compra.status !== 'delivered' && compra.status !== 'cancelled' ? `
                            <button class="btn btn-danger btn-icon" onclick="window.ComprasModule.cancelar(${compra.id})" title="Cancelar Pedido">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                            ` : ''}
                            
                            ${canDelete && (compra.status === 'pending' || compra.status === 'cancelled') ? `
                            <button class="btn btn-danger btn-icon" onclick="window.ComprasModule.deletar(${compra.id})" title="Deletar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tableHTML += `</tbody></table>`;
        elements.comprasTable.innerHTML = tableHTML;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('pt-BR');
    }
    
    function formatMoney(value) {
        return parseFloat(value || 0).toFixed(2).replace('.', ',');
    }
    
    // Filtros
    let searchTimeout;
    elements.searchInput.addEventListener('input', (e) => {
        state.search = e.target.value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadCompras, 400);
    });
    
    elements.statusFilter.addEventListener('change', (e) => {
        state.status = e.target.value;
        loadCompras();
    });
    
    elements.paymentFilter.addEventListener('change', (e) => {
        state.payment_status = e.target.value;
        loadCompras();
    });
    
    // Funções
    async function viewDetails(id) {
        try {
            const response = await fetch(`modules/compras/actions/get_pedido.php?id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                let msg = `PEDIDO: ${data.pedido.order_number}\n\n`;
                msg += `Cliente: ${data.pedido.customer_name}\n`;
                msg += `Email: ${data.pedido.customer_email}\n`;
                msg += `Telefone: ${data.pedido.customer_phone || 'N/A'}\n`;
                msg += `Data: ${formatDate(data.pedido.order_date)}\n`;
                msg += `Total: ${formatMoney(data.pedido.total)} ${data.pedido.currency}\n`;
                msg += `Status: ${data.pedido.status}\n`;
                msg += `Pagamento: ${data.pedido.payment_status}\n`;
                msg += `Método: ${data.pedido.payment_method}\n\n`;
                
                if (data.pedido.shipping_address) {
                    msg += `Endereço: ${data.pedido.shipping_address}\n`;
                    msg += `Cidade: ${data.pedido.shipping_city || 'N/A'}\n\n`;
                }
                
                msg += `ITENS (${data.items.length}):\n`;
                data.items.forEach(item => {
                    msg += `- ${item.product_name} (${item.quantity}x) = ${formatMoney(item.total)}\n`;
                });
                
                alert(msg);
            } else {
                showAlert('error', data.message);
            }
        } catch (error) {
            showAlert('error', 'Erro ao buscar detalhes');
        }
    }
    
    async function confirmarPagamento(orderId) {
        const receiptNumber = prompt('Número do comprovante/recibo (opcional):');
        if (receiptNumber === null) return;
        
        const notes = prompt('Observações sobre o pagamento (opcional):');
        if (notes === null) return;
        
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('receipt_number', receiptNumber);
        formData.append('notes', notes);
        
        try {
            const response = await fetch('modules/compras/actions/confirmar_pagamento.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            showAlert(result.success ? 'success' : 'error', result.message);
            
            if (result.success) loadCompras();
        } catch (error) {
            showAlert('error', 'Erro ao confirmar pagamento');
        }
    }
    
    function gerarRecibo(orderId) {
        if (!confirm('Gerar recibo em PDF e enviar ao cliente?')) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'modules/compras/actions/gerar_recibo.php';
        form.target = '_blank';
        
        const orderInput = document.createElement('input');
        orderInput.type = 'hidden';
        orderInput.name = 'order_id';
        orderInput.value = orderId;
        form.appendChild(orderInput);
        
        const autoSendInput = document.createElement('input');
        autoSendInput.type = 'hidden';
        autoSendInput.name = 'auto_send';
        autoSendInput.value = 'true';
        form.appendChild(autoSendInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        
        showAlert('success', 'Gerando recibo... O cliente receberá uma notificação.');
    }
    
    async function updateStatus(id, currentStatus) {
        const statusOptions = {
            'pending': 'confirmed',
            'confirmed': 'processing',
            'processing': 'shipped',
            'shipped': 'delivered'
        };
        
        const nextStatus = statusOptions[currentStatus] || 'confirmed';
        const statusText = prompt(`Atualizar status para?\n\nOpções: pending, confirmed, processing, shipped, delivered, cancelled\n\nSugestão: ${nextStatus}`, nextStatus);
        
        if (!statusText) return;
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', statusText.trim().toLowerCase());
        
        try {
            const response = await fetch('modules/compras/actions/atualizar_status.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            showAlert(result.success ? 'success' : 'error', result.message);
            
            if (result.success) loadCompras();
        } catch (error) {
            showAlert('error', 'Erro ao atualizar');
        }
    }
    
    async function cancelar(id) {
        const reason = prompt('Motivo do cancelamento:');
        if (!reason) return;
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('reason', reason);
        
        try {
            const response = await fetch('modules/compras/actions/cancelar_pedido.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            showAlert(result.success ? 'success' : 'error', result.message);
            
            if (result.success) loadCompras();
        } catch (error) {
            showAlert('error', 'Erro ao cancelar');
        }
    }
    
    async function deletar(id) {
        if (!confirm('Deletar este pedido permanentemente?')) return;
        
        const formData = new FormData();
        formData.append('id', id);
        
        try {
            const response = await fetch('modules/compras/actions/deletar_compra.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            showAlert(result.success ? 'success' : 'error', result.message);
            
            if (result.success) loadCompras();
        } catch (error) {
            showAlert('error', 'Erro ao deletar');
        }
    }
    
    function exportar() {
        window.location.href = 'modules/compras/actions/exportar_compras.php';
    }
    
    function showAlert(type, message, duration = 5000) {
        if (!elements.alertContainer) return;
        
        const config = {
            success: { icon: 'fa-circle-check', title: 'Sucesso!' },
            error: { icon: 'fa-circle-exclamation', title: 'Erro!' }
        };
        
        const c = config[type] || config.error;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fa-solid ${c.icon} alert-icon"></i>
            <div class="alert-content">
                <div class="alert-title">${c.title}</div>
                <div class="alert-message">${message}</div>
            </div>
            <button class="alert-close"><i class="fa-solid fa-xmark"></i></button>
            <div class="alert-progress"></div>
        `;
        
        elements.alertContainer.appendChild(alert);
        alert.querySelector('.alert-close').addEventListener('click', () => alert.remove());
        setTimeout(() => alert.remove(), duration);
    }
    
    window.ComprasModule = {
        viewDetails,
        confirmarPagamento,
        gerarRecibo,
        updateStatus,
        cancelar,
        deletar,
        exportar
    };
    
    loadCompras();
    
})();
</script>