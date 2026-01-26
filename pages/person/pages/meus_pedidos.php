<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-title-group">
                <i class="fa-solid fa-shopping-bag"></i>
                <div>
                    <h1>Meus Pedidos</h1>
                    <p class="page-subtitle">Acompanhe o status de suas compras</p>
                </div>
            </div>
            
            <div class="header-stats">
                <div class="stat-pill">
                    <span class="stat-value" id="stat-total">0</span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-pill warning">
                    <span class="stat-value" id="stat-pendentes">0</span>
                    <span class="stat-label">Pendentes</span>
                </div>
                <div class="stat-pill info">
                    <span class="stat-value" id="stat-confirmados">0</span>
                    <span class="stat-label">Confirmados</span>
                </div>
                <div class="stat-pill success">
                    <span class="stat-value" id="stat-entregues">0</span>
                    <span class="stat-label">Entregues e Pagos</span>
                </div>
            </div>
        </div>
    </div>

    <div class="filters-bar">
        <div class="filter-group">
            <button class="filter-chip active" data-status="all" onclick="OrdersModule.filterOrdersByStatus('all', this)">
                <i class="fa-solid fa-list"></i> Todos
            </button>
            <button class="filter-chip" data-status="pendente" onclick="OrdersModule.filterOrdersByStatus('pendente', this)">
                ⏳ Pendentes
            </button>
            <button class="filter-chip" data-status="confirmado" onclick="OrdersModule.filterOrdersByStatus('confirmado', this)">
                ✓ Confirmados
            </button>
            <button class="filter-chip" data-status="entregue_pago" onclick="OrdersModule.filterOrdersByStatus('entregue_pago', this)">
                ✅ Entregues e Pagos
            </button>
            <button class="filter-chip" data-status="cancelado" onclick="OrdersModule.filterOrdersByStatus('cancelado', this)">
                ❌ Cancelados
            </button>
        </div>
        
        <div class="search-filter">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchOrders" placeholder="Buscar por número do pedido..." onkeyup="OrdersModule.searchOrdersByNumber()">
        </div>
    </div>

    <div class="orders-list" id="ordersList">
        <div class="empty-state">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <h2>Carregando pedidos...</h2>
            <p>Aguarde um momento</p>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalDetails">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fa-solid fa-file-invoice"></i>
                <span id="modalDetailsTitle">Detalhes do Pedido</span>
            </h2>
            <button class="modal-close" onclick="OrdersModule.closeModal('modalDetails')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalDetailsContent"></div>
        <div class="modal-footer">
            <button class="btn-action btn-view" onclick="OrdersModule.closeModal('modalDetails')">
                <i class="fa-solid fa-check"></i>
                Fechar
            </button>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

.modal-overlay.active {
    display: flex;
}

.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(0, 255, 136, 0.05);
}

.modal-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.modal-title i {
    color: var(--primary);
    font-size: 24px;
}

.modal-close {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--border);
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text-secondary);
}

.modal-close:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: #f44336;
    color: #f44336;
    transform: rotate(90deg);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: rgba(0, 0, 0, 0.2);
}

.order-details-grid {
    display: grid;
    gap: 24px;
}

.detail-section {
    background: rgba(0, 255, 136, 0.03);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}

.detail-section h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-section h3::before {
    content: '';
    width: 4px;
    height: 20px;
    background: var(--primary);
    border-radius: 2px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-size: 14px;
    color: var(--text-secondary);
    font-weight: 500;
}

.detail-value {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 600;
    text-align: right;
}

.items-list {
    display: grid;
    gap: 12px;
}

.item-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

.item-card:hover {
    border-color: var(--primary);
    transform: translateX(4px);
}

.item-info {
    flex: 1;
}

.item-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.item-meta {
    font-size: 12px;
    color: var(--text-secondary);
}

.item-total {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin-left: 16px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .modal-container {
        max-width: 100%;
        max-height: 95vh;
        border-radius: 12px 12px 0 0;
        margin-top: auto;
    }
    
    .modal-body {
        padding: 16px;
    }
    
    .detail-section {
        padding: 16px;
    }
    
    .item-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .item-total {
        margin-left: 0;
        align-self: flex-end;
    }
}
</style>

<script>
(function() {
    'use strict';
    
    if (window.OrdersModule) {
        return;
    }
    
    const state = {
        currentFilter: 'all',
        currentOrders: [],
        updateInterval: null,
        lastUpdate: 0
    };

    async function loadOrders(silent = false) {
        try {
            const response = await fetch('actions/get_order_total.php');
            const data = await response.json();
            
            if (data.success) {
                const hasChanges = JSON.stringify(state.currentOrders) !== JSON.stringify(data.orders);
                state.currentOrders = data.orders;
                
                if (!silent || hasChanges) {
                    renderOrders();
                }
                
                state.lastUpdate = Date.now();
            } else if (!silent) {
                showError('Erro ao carregar pedidos');
            }
        } catch (error) {
            if (!silent) {
                console.error('Erro ao carregar pedidos:', error);
                showError('Erro de conexão');
            }
        }
    }

    function renderOrders() {
        const container = document.getElementById('ordersList');
        
        if (typeof ordersData === 'undefined' && state.currentOrders.length === 0) {
            container.innerHTML = `
                <div class="error-state">
                    <h3><i class="fa-solid fa-triangle-exclamation"></i> Erro de Dados</h3>
                    <p>Os dados dos pedidos não foram carregados. Recarregue a página.</p>
                </div>
            `;
            return;
        }
        
        const orders = state.currentOrders.length > 0 ? state.currentOrders : (typeof ordersData !== 'undefined' ? ordersData : []);
        
        if (!orders || orders.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-shopping-bag"></i>
                    <h2>Nenhum pedido encontrado</h2>
                    <p>Você ainda não realizou nenhuma compra</p>
                    <button class="btn-action btn-view" onclick="navigateTo('home')" style="margin-top: 20px;">
                        <i class="fa-solid fa-store"></i>
                        Explorar Produtos
                    </button>
                </div>
            `;
            updateStats(0, 0, 0, 0);
            return;
        }
        
        const pendentes = orders.filter(o => o.status === 'pendente').length;
        const confirmados = orders.filter(o => o.status === 'confirmado' || o.status === 'processando' || o.status === 'enviado').length;
        const entreguesPagos = orders.filter(o => o.payment_method === 'manual' && o.payment_status === 'pago').length;
        
        updateStats(orders.length, pendentes, confirmados, entreguesPagos);
        
        try {
            container.innerHTML = orders.map((order, index) => renderOrderCard(order, index)).join('');
        } catch (error) {
            container.innerHTML = `<div class="error-state"><h3>Erro de Renderização</h3><p>${error.message}</p></div>`;
        }
    }

    function renderOrderCard(order, index) {
        const statusInfo = statusMap[order.status] || {icon: '❓', label: order.status, color: 'warning'};
        const paymentInfo = paymentStatusMap[order.payment_status] || {icon: '❓', label: order.payment_status, color: 'warning'};
        const methodInfo = paymentMethodMap[order.payment_method] || {icon: '💳', label: order.payment_method};
        
        const shippingAddress = order.shipping_address && order.shipping_address !== 'null' ? order.shipping_address : null;
        const shippingCity = order.shipping_city && order.shipping_city !== 'null' ? order.shipping_city : null;
        
        return `
            <div class="order-card" data-status="${order.status}" data-payment="${order.payment_status}" data-method="${order.payment_method}" data-order="${order.order_number}" style="animation: fadeInUp 0.5s ease ${index * 0.1}s backwards;">
                <div class="order-header">
                    <div class="order-number">
                        <i class="fa-solid fa-hashtag"></i>
                        <strong>${order.order_number || 'N/A'}</strong>
                    </div>
                    <div class="order-date">
                        <i class="fa-regular fa-calendar"></i>
                        ${formatDate(order.order_date)}
                    </div>
                    <div class="order-company">
                        <i class="fa-solid fa-store"></i>
                        ${order.empresa_nome || 'VisionGreen'}
                    </div>
                </div>
                <div class="order-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Status do Pedido</span>
                            <span class="status-badge status-${statusInfo.color}">
                                ${statusInfo.icon} ${statusInfo.label}
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Pagamento</span>
                            <span class="status-badge status-${paymentInfo.color}">
                                ${paymentInfo.icon} ${paymentInfo.label}
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Método</span>
                            <span class="info-value">${methodInfo.icon} ${methodInfo.label}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Itens</span>
                            <span class="info-value">${order.total_items || order.items_count || 0} produto(s)</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total</span>
                            <span class="order-total">${formatPrice(order.total)} ${order.currency || 'MZN'}</span>
                        </div>
                    </div>
                    ${shippingAddress || shippingCity ? `
                    <div class="shipping-info">
                        <i class="fa-solid fa-location-dot"></i>
                        ${shippingAddress ? `<span>${shippingAddress}</span>` : ''}
                        ${shippingCity ? `<span>${shippingAddress ? '•' : ''} ${shippingCity}</span>` : ''}
                    </div>
                    ` : ''}
                    <div class="order-actions">
                        <button class="btn-action btn-view" onclick="OrdersModule.viewOrderDetails(${order.id})">
                            <i class="fa-solid fa-eye"></i>
                            Ver Detalhes
                        </button>
                        ${order.status === 'enviado' || order.status === 'processando' ? `
                        <button class="btn-action btn-track" onclick="OrdersModule.trackOrder(${order.id})">
                            <i class="fa-solid fa-truck"></i>
                            Rastrear
                        </button>
                        ` : ''}
                        ${order.status === 'entregue' ? `
                        <button class="btn-action btn-track" onclick="OrdersModule.contactSupport('${order.order_number}')">
                            <i class="fa-solid fa-headset"></i>
                            Suporte
                        </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    function updateStats(total, pendentes, confirmados, entreguesPagos) {
        const statTotal = document.getElementById('stat-total');
        const statPendentes = document.getElementById('stat-pendentes');
        const statConfirmados = document.getElementById('stat-confirmados');
        const statEntregues = document.getElementById('stat-entregues');
        
        if (statTotal && statTotal.textContent !== total.toString()) {
            statTotal.textContent = total;
        }
        if (statPendentes && statPendentes.textContent !== pendentes.toString()) {
            statPendentes.textContent = pendentes;
        }
        if (statConfirmados && statConfirmados.textContent !== confirmados.toString()) {
            statConfirmados.textContent = confirmados;
        }
        if (statEntregues && statEntregues.textContent !== entreguesPagos.toString()) {
            statEntregues.textContent = entreguesPagos;
        }
    }

    function filterOrdersByStatus(status, button) {
        state.currentFilter = status;
        document.querySelectorAll('.filter-chip').forEach(chip => chip.classList.remove('active'));
        if (button) button.classList.add('active');
        
        const cards = document.querySelectorAll('.order-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const cardStatus = card.dataset.status;
            const cardPayment = card.dataset.payment;
            let shouldShow = false;
            
            if (status === 'all') {
                shouldShow = true;
            } else if (status === 'entregue_pago') {
                shouldShow = cardPayment === 'pago' && card.dataset.method === 'manual';
            } else if (status === 'confirmado') {
                shouldShow = cardStatus === 'confirmado' || cardStatus === 'processando' || cardStatus === 'enviado';
            } else {
                shouldShow = cardStatus === status;
            }
            
            if (shouldShow) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        const existingEmpty = document.querySelector('.filter-empty-state');
        if (existingEmpty) existingEmpty.remove();
        
        if (visibleCount === 0) {
            const container = document.getElementById('ordersList');
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state filter-empty-state';
            emptyState.innerHTML = `
                <i class="fa-solid fa-filter"></i>
                <h2>Nenhum pedido encontrado</h2>
                <p>Não há pedidos com o filtro selecionado</p>
            `;
            container.appendChild(emptyState);
        }
    }

    function searchOrdersByNumber() {
        const searchTerm = document.getElementById('searchOrders').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.order-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const orderNumber = card.dataset.order.toLowerCase();
            const cardStatus = card.dataset.status;
            const cardPayment = card.dataset.payment;
            const cardMethod = card.dataset.method;
            
            let matchesFilter = false;
            if (state.currentFilter === 'all') {
                matchesFilter = true;
            } else if (state.currentFilter === 'entregue_pago') {
                matchesFilter = cardPayment === 'pago' && cardMethod === 'manual';
            } else if (state.currentFilter === 'confirmado') {
                matchesFilter = cardStatus === 'confirmado' || cardStatus === 'processando' || cardStatus === 'enviado';
            } else {
                matchesFilter = cardStatus === state.currentFilter;
            }
            
            if (orderNumber.includes(searchTerm) && matchesFilter) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        const existingEmpty = document.querySelector('.search-empty-state');
        if (existingEmpty) existingEmpty.remove();
        
        if (visibleCount === 0 && searchTerm.length > 0) {
            const container = document.getElementById('ordersList');
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state search-empty-state';
            emptyState.innerHTML = `
                <i class="fa-solid fa-search"></i>
                <h2>Nenhum pedido encontrado</h2>
                <p>Não há pedidos com o número "<strong>${searchTerm}</strong>"</p>
                <button class="btn-action btn-view" onclick="OrdersModule.clearSearch()" style="margin-top: 20px;">
                    <i class="fa-solid fa-times"></i>
                    Limpar Busca
                </button>
            `;
            container.appendChild(emptyState);
        }
    }

    function clearSearch() {
        document.getElementById('searchOrders').value = '';
        searchOrdersByNumber();
    }

    async function viewOrderDetails(orderId) {
        try {
            const response = await fetch(`actions/get_order_details.php?id=${orderId}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            
            if (data.success) {
                showOrderDetailsModal(data.order);
            } else {
                if (typeof showToast === 'function') {
                    showToast(`❌ ${data.message || 'Erro ao carregar detalhes'}`, 'error');
                }
            }
        } catch (error) {
            if (typeof showToast === 'function') {
                showToast(`❌ Erro: ${error.message}`, 'error');
            }
        }
    }

    function showOrderDetailsModal(order) {
        const items = order.items || [];
        const statusInfo = statusMap[order.status] || {icon: '❓', label: order.status};
        const paymentInfo = paymentStatusMap[order.payment_status] || {icon: '❓', label: order.payment_status};
        
        document.getElementById('modalDetailsTitle').textContent = `Pedido #${order.order_number}`;
        
        document.getElementById('modalDetailsContent').innerHTML = `
            <div class="order-details-grid">
                <div class="detail-section">
                    <h3>Informações do Pedido</h3>
                    <div class="detail-row">
                        <span class="detail-label">Número:</span>
                        <span class="detail-value">#${order.order_number}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Data:</span>
                        <span class="detail-value">${formatDate(order.order_date)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Empresa:</span>
                        <span class="detail-value">${order.company_name || 'VisionGreen'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">${statusInfo.icon} ${statusInfo.label}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Pagamento:</span>
                        <span class="detail-value">${paymentInfo.icon} ${paymentInfo.label}</span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Itens do Pedido</h3>
                    <div class="items-list">
                        ${items.map(item => `
                            <div class="item-card">
                                <div class="item-info">
                                    <div class="item-name">${item.product_name}</div>
                                    <div class="item-meta">Quantidade: ${item.quantity} × ${formatPrice(item.unit_price)} MZN</div>
                                </div>
                                <div class="item-total">${formatPrice(item.total)} MZN</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>Totais</h3>
                    <div class="detail-row">
                        <span class="detail-label">Subtotal:</span>
                        <span class="detail-value">${formatPrice(order.subtotal || order.total)} MZN</span>
                    </div>
                    ${order.discount > 0 ? `
                    <div class="detail-row">
                        <span class="detail-label">Desconto:</span>
                        <span class="detail-value">-${formatPrice(order.discount)} MZN</span>
                    </div>
                    ` : ''}
                    ${order.shipping_cost > 0 ? `
                    <div class="detail-row">
                        <span class="detail-label">Frete:</span>
                        <span class="detail-value">${formatPrice(order.shipping_cost)} MZN</span>
                    </div>
                    ` : ''}
                    <div class="detail-row" style="border-top: 2px solid var(--border); padding-top: 12px; margin-top: 8px;">
                        <span class="detail-label" style="font-weight: 700; color: var(--text-primary);">Total:</span>
                        <span class="detail-value" style="font-size: 18px; color: var(--primary);">${formatPrice(order.total)} MZN</span>
                    </div>
                </div>
                
                ${order.shipping_address ? `
                <div class="detail-section">
                    <h3>Endereço de Entrega</h3>
                    <p style="color: var(--text-primary); line-height: 1.6;">
                        ${order.shipping_address}${order.shipping_city ? `<br>${order.shipping_city}` : ''}
                        ${order.shipping_phone ? `<br>Telefone: ${order.shipping_phone}` : ''}
                    </p>
                </div>
                ` : ''}
                
                ${order.customer_notes ? `
                <div class="detail-section">
                    <h3>Observações do Cliente</h3>
                    <p style="color: var(--text-secondary); line-height: 1.6;">${order.customer_notes}</p>
                </div>
                ` : ''}
            </div>
        `;
        
        openModal('modalDetails');
    }

    function trackOrder(orderId) {
        if (typeof showToast === 'function') {
            showToast('🚚 Função de rastreamento em desenvolvimento', 'info');
        }
    }

    function contactSupport(orderNumber) {
        if (typeof showToast === 'function') {
            showToast(`📞 Entre em contato com o suporte mencionando o pedido #${orderNumber}`, 'info');
        }
    }

    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.body.style.overflow = '';
    }

    function showError(message) {
        const container = document.getElementById('ordersList');
        container.innerHTML = `
            <div class="error-state">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <h3>Erro ao Carregar</h3>
                <p>${message}</p>
                <button class="btn-action btn-view" onclick="OrdersModule.loadOrders()" style="margin-top: 24px;">
                    <i class="fa-solid fa-rotate-right"></i>
                    Tentar Novamente
                </button>
            </div>
        `;
    }

    function formatDate(dateString) {
        if (!dateString) return 'Data inválida';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' às ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        } catch (error) {
            return dateString;
        }
    }

    function formatPrice(price) {
        if (price === null || price === undefined) return '0,00';
        try {
            return parseFloat(price).toLocaleString('pt-MZ', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        } catch (error) {
            return String(price);
        }
    }

    function setupEventListeners() {
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    }

    function startAutoUpdate() {
        if (state.updateInterval) {
            clearInterval(state.updateInterval);
        }
        
        state.updateInterval = setInterval(() => {
            loadOrders(true);
        }, 1000);
    }

    function stopAutoUpdate() {
        if (state.updateInterval) {
            clearInterval(state.updateInterval);
            state.updateInterval = null;
        }
    }

    window.OrdersModule = {
        loadOrders: (silent) => loadOrders(silent || false),
        filterOrdersByStatus,
        searchOrdersByNumber,
        clearSearch,
        viewOrderDetails,
        trackOrder,
        contactSupport,
        closeModal,
        state
    };

    setTimeout(() => {
        setupEventListeners();
        renderOrders();
        startAutoUpdate();
    }, 100);

    window.addEventListener('beforeunload', stopAutoUpdate);
})();
</script>