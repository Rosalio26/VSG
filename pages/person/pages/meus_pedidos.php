<!-- 
  Arquivo: /pages/person/pages/meus_pedidos.php
  VERSÃO COMPLETA - Com modais e funções de cancelamento/confirmação
-->

<style>
/* Modal Styles - GitHub Dark Theme */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
    z-index: 9999;
    animation: fadeIn 0.2s ease;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-container {
    background: var(--card-bg);
    border: 2px solid var(--border);
    border-radius: 16px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease;
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 20px;
    font-weight: 800;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-close {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-size: 24px;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.order-details-grid {
    display: grid;
    gap: 20px;
}

.detail-section {
    background: rgba(255, 255, 255, 0.02);
    padding: 16px;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.detail-section h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 12px;
    text-transform: uppercase;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: var(--text-secondary);
    font-size: 14px;
}

.detail-value {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 14px;
}

.items-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.item-card {
    background: rgba(0, 0, 0, 0.2);
    padding: 12px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.item-meta {
    font-size: 12px;
    color: var(--text-secondary);
}

.item-total {
    font-weight: 800;
    color: var(--primary);
    font-size: 16px;
}

.modal-input {
    width: 100%;
    padding: 12px 16px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    margin-top: 8px;
}

.modal-input:focus {
    outline: none;
    border-color: var(--primary);
}

.modal-label {
    display: block;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .modal-container {
        max-height: 95vh;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer button {
        width: 100%;
    }
}
</style>

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
                    <span class="stat-value" id="stat-andamento">0</span>
                    <span class="stat-label">Andamento</span>
                </div>
                <div class="stat-pill success">
                    <span class="stat-value" id="stat-entregues">0</span>
                    <span class="stat-label">Entregues</span>
                </div>
            </div>
        </div>
    </div>

    <div class="filters-bar">
        <div class="filter-group">
            <button class="filter-chip active" data-status="all" onclick="filterOrdersByStatus('all', this)">
                <i class="fa-solid fa-list"></i> Todos
            </button>
            <button class="filter-chip" data-status="pendente" onclick="filterOrdersByStatus('pendente', this)">
                ⏳ Pendentes
            </button>
            <button class="filter-chip" data-status="processando,confirmado,enviado" onclick="filterOrdersByStatus('processando,confirmado,enviado', this)">
                ⚙️ Em Andamento
            </button>
            <button class="filter-chip" data-status="entregue" onclick="filterOrdersByStatus('entregue', this)">
                ✅ Entregues
            </button>
            <button class="filter-chip" data-status="cancelado" onclick="filterOrdersByStatus('cancelado', this)">
                ❌ Cancelados
            </button>
        </div>
        
        <div class="search-filter">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchOrders" placeholder="Buscar por número do pedido..." onkeyup="searchOrdersByNumber()">
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

<!-- Modal Detalhes -->
<div class="modal-overlay" id="modalDetails">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fa-solid fa-file-invoice"></i>
                <span id="modalDetailsTitle">Detalhes do Pedido</span>
            </h2>
            <button class="modal-close" onclick="closeModal('modalDetails')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalDetailsContent">
            <!-- Preenchido via JS -->
        </div>
        <div class="modal-footer">
            <button class="btn-action btn-view" onclick="closeModal('modalDetails')">
                <i class="fa-solid fa-check"></i>
                Fechar
            </button>
        </div>
    </div>
</div>

<!-- Modal Cancelamento -->
<div class="modal-overlay" id="modalCancel">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fa-solid fa-ban"></i>
                Cancelar Pedido
            </h2>
            <button class="modal-close" onclick="closeModal('modalCancel')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Tem certeza que deseja cancelar este pedido? Esta ação não pode ser desfeita.
            </p>
            <div class="detail-section">
                <h3>Pedido #<span id="cancelOrderNumber"></span></h3>
                <div class="detail-row">
                    <span class="detail-label">Total:</span>
                    <span class="detail-value" id="cancelOrderTotal"></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-action btn-view" onclick="closeModal('modalCancel')">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar
            </button>
            <button class="btn-action btn-cancel" onclick="confirmCancelOrder()">
                <i class="fa-solid fa-ban"></i>
                Confirmar Cancelamento
            </button>
        </div>
    </div>
</div>

<!-- Modal Confirmação Pagamento -->
<div class="modal-overlay" id="modalConfirmPayment">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fa-solid fa-check-circle"></i>
                Confirmar Pagamento
            </h2>
            <button class="modal-close" onclick="closeModal('modalConfirmPayment')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Confirme que você já realizou o pagamento manual deste pedido.
            </p>
            <div class="detail-section" style="margin-bottom: 20px;">
                <h3>Pedido #<span id="paymentOrderNumber"></span></h3>
                <div class="detail-row">
                    <span class="detail-label">Total:</span>
                    <span class="detail-value" id="paymentOrderTotal"></span>
                </div>
            </div>
            <label class="modal-label">Observações (opcional):</label>
            <textarea id="paymentNotes" class="modal-input" rows="3" placeholder="Adicione qualquer informação adicional..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn-action btn-view" onclick="closeModal('modalConfirmPayment')">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar
            </button>
            <button class="btn-action btn-track" onclick="confirmPayment()">
                <i class="fa-solid fa-check"></i>
                Confirmar Pagamento
            </button>
        </div>
    </div>
</div>

<script>
let currentFilter = 'all';
let currentOrders = [];
let currentOrderIdForAction = null;

function renderOrders() {
    console.log('🔄 Iniciando renderização de pedidos...');
    const container = document.getElementById('ordersList');
    
    if (typeof ordersData === 'undefined') {
        console.error('❌ ordersData não está definido');
        container.innerHTML = `
            <div class="error-state">
                <h3><i class="fa-solid fa-triangle-exclamation"></i> Erro de Dados</h3>
                <p>Os dados dos pedidos não foram carregados. Recarregue a página.</p>
            </div>
        `;
        return;
    }
    
    currentOrders = ordersData;
    
    if (!ordersData || ordersData.length === 0) {
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
    
    updateStats(
        pedidosStats.total || ordersData.length,
        pedidosStats.pendentes || 0,
        pedidosStats.em_andamento || 0,
        pedidosStats.entregues || 0
    );
    
    try {
        container.innerHTML = ordersData.map((order, index) => renderOrderCard(order, index)).join('');
        console.log(`✅ ${ordersData.length} pedidos renderizados`);
    } catch (error) {
        console.error('❌ Erro:', error);
        container.innerHTML = `<div class="error-state"><h3>Erro de Renderização</h3><p>${error.message}</p></div>`;
    }
}

function renderOrderCard(order, index) {
    const statusInfo = statusMap[order.status] || {icon: '❓', label: order.status, color: 'warning'};
    const paymentInfo = paymentStatusMap[order.payment_status] || {icon: '❓', label: order.payment_status, color: 'warning'};
    const methodInfo = paymentMethodMap[order.payment_method] || {icon: '💳', label: order.payment_method};
    
    const shippingAddress = order.shipping_address && order.shipping_address !== 'null' ? order.shipping_address : null;
    const shippingCity = order.shipping_city && order.shipping_city !== 'null' ? order.shipping_city : null;
    
    const canCancel = order.status === 'pendente' || order.status === 'confirmado';
    const canConfirmPayment = order.payment_method === 'manual' && order.status === 'entregue' && order.payment_status === 'pendente';
    
    return `
        <div class="order-card" data-status="${order.status}" data-order="${order.order_number}" style="animation: fadeInUp 0.5s ease ${index * 0.1}s backwards;">
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
                    <button class="btn-action btn-view" onclick="viewOrderDetails(${order.id})">
                        <i class="fa-solid fa-eye"></i>
                        Ver Detalhes
                    </button>
                    ${order.status === 'enviado' || order.status === 'processando' ? `
                    <button class="btn-action btn-track" onclick="trackOrder(${order.id})">
                        <i class="fa-solid fa-truck"></i>
                        Rastrear
                    </button>
                    ` : ''}
                    ${canConfirmPayment ? `
                    <button class="btn-action btn-track" onclick="openConfirmPaymentModal(${order.id}, '${order.order_number}', ${order.total})">
                        <i class="fa-solid fa-check-circle"></i>
                        Confirmar Pagamento
                    </button>
                    ` : ''}
                    ${canCancel ? `
                    <button class="btn-action btn-cancel" onclick="openCancelModal(${order.id}, '${order.order_number}', ${order.total})">
                        <i class="fa-solid fa-ban"></i>
                        Cancelar
                    </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

function updateStats(total, pendentes, andamento, entregues) {
    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-pendentes').textContent = pendentes;
    document.getElementById('stat-andamento').textContent = andamento;
    document.getElementById('stat-entregues').textContent = entregues;
}

function filterOrdersByStatus(status, button) {
    currentFilter = status;
    document.querySelectorAll('.filter-chip').forEach(chip => chip.classList.remove('active'));
    if (button) button.classList.add('active');
    
    const cards = document.querySelectorAll('.order-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const cardStatus = card.dataset.status;
        if (status === 'all') {
            card.style.display = 'block';
            visibleCount++;
        } else if (status.includes(',')) {
            const statusList = status.split(',');
            if (statusList.includes(cardStatus)) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        } else {
            if (cardStatus === status) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
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
    
    cards.forEach(card => {
        const orderNumber = card.dataset.order.toLowerCase();
        const cardStatus = card.dataset.status;
        const matchesFilter = currentFilter === 'all' || 
                             (currentFilter.includes(',') ? currentFilter.split(',').includes(cardStatus) : cardStatus === currentFilter);
        
        if (orderNumber.includes(searchTerm) && matchesFilter) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

async function viewOrderDetails(orderId) {
    try {
        const response = await fetch(`actions/get_order_details.php?id=${orderId}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const data = await response.json();
        
        if (data.success) {
            showOrderDetailsModal(data.order);
        } else {
            showToast(`❌ ${data.message || 'Erro ao carregar detalhes'}`, 'error');
        }
    } catch (error) {
        console.error('❌ Erro:', error);
        showToast(`❌ Erro: ${error.message}`, 'error');
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
        </div>
    `;
    
    openModal('modalDetails');
}

function openCancelModal(orderId, orderNumber, total) {
    currentOrderIdForAction = orderId;
    document.getElementById('cancelOrderNumber').textContent = orderNumber;
    document.getElementById('cancelOrderTotal').textContent = formatPrice(total) + ' MZN';
    openModal('modalCancel');
}

async function confirmCancelOrder() {
    if (!currentOrderIdForAction) return;
    
    try {
        const response = await fetch('actions/cancel_order.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_id: currentOrderIdForAction})
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('✅ Pedido cancelado com sucesso!', 'success');
            closeModal('modalCancel');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(`❌ ${data.message}`, 'error');
        }
    } catch (error) {
        console.error('❌ Erro:', error);
        showToast('❌ Erro ao cancelar pedido', 'error');
    }
}

function openConfirmPaymentModal(orderId, orderNumber, total) {
    currentOrderIdForAction = orderId;
    document.getElementById('paymentOrderNumber').textContent = orderNumber;
    document.getElementById('paymentOrderTotal').textContent = formatPrice(total) + ' MZN';
    document.getElementById('paymentNotes').value = '';
    openModal('modalConfirmPayment');
}

async function confirmPayment() {
    if (!currentOrderIdForAction) return;
    
    const notes = document.getElementById('paymentNotes').value.trim();
    
    try {
        const response = await fetch('actions/confirm_payment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                order_id: currentOrderIdForAction,
                notes: notes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('✅ Pagamento confirmado com sucesso!', 'success');
            closeModal('modalConfirmPayment');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(`❌ ${data.message}`, 'error');
        }
    } catch (error) {
        console.error('❌ Erro:', error);
        showToast('❌ Erro ao confirmar pagamento', 'error');
    }
}

function trackOrder(orderId) {
    showToast('🚚 Função de rastreamento em desenvolvimento', 'info');
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.style.overflow = '';
    currentOrderIdForAction = null;
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});

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

const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);

renderOrders();
console.log('✅ Módulo Meus Pedidos inicializado');
</script>