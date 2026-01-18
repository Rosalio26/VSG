<?php
// Este arquivo deve estar em: pages/meus_pedidos.php
?>
<div style="max-width: 1200px; margin: 0 auto;">
    <!-- Header -->
    <div style="margin-bottom: 32px;">
        <h1 style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">
            <i class="fa-solid fa-shopping-bag"></i> Meus Pedidos
        </h1>
        <p style="color: var(--text-secondary); font-size: 16px;">
            Acompanhe o status de todos os seus pedidos
        </p>
    </div>

    <!-- Filtros de Status -->
    <div style="display: flex; gap: 12px; margin-bottom: 28px; flex-wrap: wrap;">
        <button class="status-filter active" onclick="filterOrdersByStatus('all')" data-status="all">
            <i class="fa-solid fa-list"></i> Todos
            <span class="filter-count" id="count-all">0</span>
        </button>
        <button class="status-filter" onclick="filterOrdersByStatus('pendente')" data-status="pendente">
            <i class="fa-solid fa-clock"></i> Pendentes
            <span class="filter-count" id="count-pendente">0</span>
        </button>
        <button class="status-filter" onclick="filterOrdersByStatus('confirmado')" data-status="confirmado">
            <i class="fa-solid fa-check-circle"></i> Confirmados
            <span class="filter-count" id="count-confirmado">0</span>
        </button>
        <button class="status-filter" onclick="filterOrdersByStatus('processando')" data-status="processando">
            <i class="fa-solid fa-box"></i> Em Preparo
            <span class="filter-count" id="count-processando">0</span>
        </button>
        <button class="status-filter" onclick="filterOrdersByStatus('enviado')" data-status="enviado">
            <i class="fa-solid fa-truck"></i> Enviados
            <span class="filter-count" id="count-enviado">0</span>
        </button>
        <button class="status-filter" onclick="filterOrdersByStatus('entregue')" data-status="entregue">
            <i class="fa-solid fa-check-double"></i> Entregues
            <span class="filter-count" id="count-entregue">0</span>
        </button>
    </div>

    <!-- Lista de Pedidos -->
    <div id="ordersContainer">
        <div style="text-align: center; padding: 60px 20px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 20px;"></i>
            <h3 style="color: var(--text-primary);">Carregando pedidos...</h3>
        </div>
    </div>
</div>

<style>
/* Filtros de Status */
.status-filter {
    padding: 10px 20px;
    background: var(--card-bg);
    border: 2px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.status-filter:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.status-filter.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #000;
}

.filter-count {
    padding: 2px 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    font-size: 12px;
    font-weight: 800;
}

.status-filter.active .filter-count {
    background: rgba(0, 0, 0, 0.2);
}

/* Order Card */
.order-card {
    background: var(--card-bg);
    border: 2px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.order-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.15);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.order-number {
    font-size: 18px;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.order-date {
    font-size: 13px;
    color: var(--text-secondary);
}

.order-status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pendente {
    background: rgba(251, 191, 36, 0.1);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

.status-confirmado {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-processando {
    background: rgba(168, 85, 247, 0.1);
    color: #a855f7;
    border: 1px solid rgba(168, 85, 247, 0.3);
}

.status-enviado {
    background: rgba(6, 182, 212, 0.1);
    color: #06b6d4;
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.status-entregue {
    background: rgba(16, 185, 129, 0.1);
    color: var(--primary);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-cancelado {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.order-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.order-item {
    display: flex;
    gap: 16px;
    align-items: center;
    padding: 12px;
    background: var(--darker-bg);
    border-radius: 10px;
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background: rgba(16, 185, 129, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-image i {
    font-size: 24px;
    color: var(--primary);
}

.item-info {
    flex: 1;
}

.item-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.item-details {
    font-size: 13px;
    color: var(--text-secondary);
}

.item-price {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    text-align: right;
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.order-total {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.total-label {
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 600;
}

.total-value {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary);
}

.order-actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
}

.btn-view {
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid rgba(16, 185, 129, 0.3);
    color: var(--primary);
}

.btn-view:hover {
    background: var(--primary);
    color: #000;
}

.btn-cancel {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid rgba(239, 68, 68, 0.3);
    color: var(--danger);
}

.btn-cancel:hover {
    background: var(--danger);
    color: #fff;
}

/* Empty State */
.empty-orders {
    text-align: center;
    padding: 80px 20px;
}

.empty-orders i {
    font-size: 64px;
    color: var(--text-secondary);
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-orders h3 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 12px;
}

.empty-orders p {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

.btn-shop {
    padding: 12px 24px;
    background: var(--primary);
    color: #000;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-shop:hover {
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .order-header {
        flex-direction: column;
        gap: 12px;
    }
    
    .order-footer {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .btn-action {
        justify-content: center;
    }
}
</style>

<script>
let allOrders = [];
let currentFilter = 'all';

// Carregar pedidos
async function loadOrders() {
    const container = document.getElementById('ordersContainer');
    
    try {
        const response = await fetch('actions/get_orders.php');
        const data = await response.json();
        
        if (data.success) {
            allOrders = data.orders;
            updateStatusCounts();
            renderOrders();
        } else {
            showEmptyState('Erro ao carregar pedidos');
        }
    } catch (error) {
        console.error('Erro:', error);
        showEmptyState('Erro ao carregar pedidos');
    }
}

// Atualizar contadores de status
function updateStatusCounts() {
    const counts = {
        all: allOrders.length,
        pendente: 0,
        confirmado: 0,
        processando: 0,
        enviado: 0,
        entregue: 0
    };
    
    allOrders.forEach(order => {
        if (counts.hasOwnProperty(order.status)) {
            counts[order.status]++;
        }
    });
    
    Object.keys(counts).forEach(status => {
        const el = document.getElementById(`count-${status}`);
        if (el) el.textContent = counts[status];
    });
}

// Renderizar pedidos
function renderOrders() {
    const container = document.getElementById('ordersContainer');
    
    const filteredOrders = currentFilter === 'all' 
        ? allOrders 
        : allOrders.filter(o => o.status === currentFilter);
    
    if (filteredOrders.length === 0) {
        showEmptyState();
        return;
    }
    
    container.innerHTML = filteredOrders.map(order => `
        <div class="order-card" data-status="${order.status}">
            <div class="order-header">
                <div>
                    <div class="order-number">#${order.order_number}</div>
                    <div class="order-date">
                        <i class="fa-solid fa-calendar"></i> ${formatDate(order.order_date)}
                    </div>
                </div>
                <span class="order-status-badge status-${order.status}">
                    ${getStatusLabel(order.status)}
                </span>
            </div>
            
            <div class="order-items">
                ${order.items.map(item => `
                    <div class="order-item">
                        <div class="item-image">
                            ${item.product_image ? 
                                `<img src="../../registration/uploads/products/${item.product_image}" alt="${item.product_name}">` :
                                '<i class="fa-solid fa-box"></i>'
                            }
                        </div>
                        <div class="item-info">
                            <div class="item-name">${item.product_name}</div>
                            <div class="item-details">Quantidade: ${item.quantity}</div>
                        </div>
                        <div class="item-price">${parseFloat(item.total).toFixed(2)} MZN</div>
                    </div>
                `).join('')}
            </div>
            
            <div class="order-footer">
                <div class="order-total">
                    <span class="total-label">Total do Pedido</span>
                    <span class="total-value">${parseFloat(order.total).toFixed(2)} MZN</span>
                </div>
                <div class="order-actions">
                    <button class="btn-action btn-view" onclick="viewOrderDetails(${order.id})">
                        <i class="fa-solid fa-eye"></i> Ver Detalhes
                    </button>
                    ${order.status === 'pendente' ? `
                        <button class="btn-action btn-cancel" onclick="cancelOrder(${order.id})">
                            <i class="fa-solid fa-times"></i> Cancelar
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `).join('');
}

// Filtrar por status
function filterOrdersByStatus(status) {
    currentFilter = status;
    
    // Atualizar botões
    document.querySelectorAll('.status-filter').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-status="${status}"]`).classList.add('active');
    
    renderOrders();
}

// Estado vazio
function showEmptyState(message) {
    const container = document.getElementById('ordersContainer');
    container.innerHTML = `
        <div class="empty-orders">
            <i class="fa-solid fa-shopping-bag"></i>
            <h3>${message || 'Nenhum pedido encontrado'}</h3>
            <p>Você ainda não fez nenhum pedido</p>
            <button class="btn-shop" onclick="navigateTo('home')">
                <i class="fa-solid fa-shopping-cart"></i>
                Começar a Comprar
            </button>
        </div>
    `;
}

// Ver detalhes
function viewOrderDetails(orderId) {
    window.location.href = `order_details.php?id=${orderId}`;
}

// Cancelar pedido
async function cancelOrder(orderId) {
    if (!confirm('Tem certeza que deseja cancelar este pedido?')) {
        return;
    }
    
    try {
        const response = await fetch('actions/cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Pedido cancelado com sucesso!');
            loadOrders();
        } else {
            alert('❌ Erro: ' + data.error);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao cancelar pedido');
    }
}

// Formatar data
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}

// Label de status
function getStatusLabel(status) {
    const labels = {
        'pendente': 'Pendente',
        'confirmado': 'Confirmado',
        'processando': 'Em Preparo',
        'enviado': 'Enviado',
        'entregue': 'Entregue',
        'cancelado': 'Cancelado'
    };
    return labels[status] || status;
}

// Carregar ao iniciar
loadOrders();
</script>