<?php
// Não precisa de session ou auth, já está dentro do dashboard
?>
<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-title-group">
                <i class="fa-solid fa-shopping-cart"></i>
                <div>
                    <h1>Carrinho de Compras</h1>
                    <p class="page-subtitle">Revise seus produtos antes de finalizar</p>
                </div>
            </div>
            <button class="btn-secondary" onclick="navigateTo('home')">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Continuar Comprando</span>
            </button>
        </div>
    </div>

    <div class="cart-container">
        <!-- Carrinho Vazio -->
        <div id="emptyCart" class="empty-cart" style="display: none;">
            <i class="fa-solid fa-cart-shopping"></i>
            <h2>Seu carrinho está vazio</h2>
            <p>Adicione produtos para continuar suas compras</p>
            <button class="btn-primary" onclick="navigateTo('home')">
                <i class="fa-solid fa-store"></i>
                <span>Explorar Produtos</span>
            </button>
        </div>

        <!-- Carrinho com Produtos -->
        <div id="cartContent" class="cart-content">
            <div class="cart-items" id="cartItems">
                <!-- Items serão inseridos aqui via JS -->
            </div>

            <div class="cart-summary">
                <div class="summary-card">
                    <h3>Resumo do Pedido</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal (<span id="totalItems">0</span> itens)</span>
                        <span id="subtotalValue">0,00 MZN</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Frete</span>
                        <span class="text-success">Grátis</span>
                    </div>
                    
                    <div class="summary-row discount-row" id="discountRow" style="display: none;">
                        <span>Desconto</span>
                        <span class="text-success" id="discountValue">- 0,00 MZN</span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row total-row">
                        <span>Total</span>
                        <span id="totalValue">0,00 MZN</span>
                    </div>
                    
                    <button class="btn-checkout" onclick="proceedToCheckout()">
                        <i class="fa-solid fa-credit-card"></i>
                        <span>Finalizar Compra</span>
                    </button>
                    
                    <button class="btn-clear-cart" onclick="clearCart()">
                        <i class="fa-solid fa-trash"></i>
                        <span>Limpar Carrinho</span>
                    </button>
                </div>

                <div class="trust-badges">
                    <div class="trust-badge">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Compra Segura</span>
                    </div>
                    <div class="trust-badge">
                        <i class="fa-solid fa-truck-fast"></i>
                        <span>Entrega Rápida</span>
                    </div>
                    <div class="trust-badge">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Troca Fácil</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Método de Pagamento -->
<div id="paymentMethodModal" class="payment-modal" style="display: none;">
    <div class="payment-modal-content">
        <div class="payment-modal-header">
            <h2>Escolha o Método de Pagamento</h2>
            <button class="payment-btn-close" onclick="closePaymentModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        
        <div class="payment-modal-body">
            <div class="payment-methods-grid">
                <!-- Pagamento Manual -->
                <div class="payment-method-card" onclick="selectPaymentMethod('manual')">
                    <div class="payment-icon manual">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <h3>Pagamento Manual</h3>
                    <p>Pague na entrega em dinheiro</p>
                    <div class="payment-badge">
                        <i class="fa-solid fa-clock"></i>
                        Pagamento após receber
                    </div>
                </div>
                
                <!-- M-Pesa -->
                <div class="payment-method-card" onclick="selectPaymentMethod('mpesa')">
                    <div class="payment-icon mpesa">
                        <i class="fa-solid fa-mobile-screen"></i>
                    </div>
                    <h3>M-Pesa</h3>
                    <p>Pagamento via M-Pesa</p>
                    <div class="payment-badge">
                        <i class="fa-solid fa-bolt"></i>
                        Instantâneo
                    </div>
                </div>
                
                <!-- E-Mola -->
                <div class="payment-method-card" onclick="selectPaymentMethod('emola')">
                    <div class="payment-icon emola">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <h3>E-Mola</h3>
                    <p>Pagamento via E-Mola</p>
                    <div class="payment-badge">
                        <i class="fa-solid fa-bolt"></i>
                        Instantâneo
                    </div>
                </div>
                
                <!-- Cartão -->
                <div class="payment-method-card" onclick="selectPaymentMethod('card')">
                    <div class="payment-icon card">
                        <i class="fa-solid fa-credit-card"></i>
                    </div>
                    <h3>Cartão de Crédito</h3>
                    <p>Visa, Mastercard</p>
                    <div class="payment-badge">
                        <i class="fa-solid fa-shield-halved"></i>
                        Seguro
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos do Modal de Pagamento */
.payment-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(1, 4, 9, 0.95);
    backdrop-filter: blur(12px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.payment-modal-content {
    background: #0d1117;
    border: 2px solid #30363d;
    border-radius: 20px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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

.payment-modal-header {
    padding: 28px 32px;
    border-bottom: 2px solid #30363d;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, rgba(35, 134, 54, 0.05), transparent);
}

.payment-modal-header h2 {
    font-size: 24px;
    font-weight: 800;
    color: #e6edf3;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.payment-btn-close {
    width: 40px;
    height: 40px;
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 10px;
    color: #7d8590;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.payment-btn-close:hover {
    background: #f85149;
    border-color: #f85149;
    color: #fff;
    transform: rotate(90deg);
}

.payment-modal-body {
    padding: 32px;
}

.payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.payment-method-card {
    background: #161b22;
    border: 2px solid #30363d;
    border-radius: 16px;
    padding: 28px 24px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.payment-method-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(35, 134, 54, 0.1), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.payment-method-card:hover {
    border-color: #238636;
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(35, 134, 54, 0.2);
}

.payment-method-card:hover::before {
    opacity: 1;
}

.payment-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    position: relative;
    z-index: 1;
}

.payment-icon.manual {
    background: linear-gradient(135deg, rgba(210, 153, 34, 0.2), rgba(210, 153, 34, 0.05));
    border: 2px solid rgba(210, 153, 34, 0.3);
    color: #d29922;
}

.payment-icon.mpesa {
    background: linear-gradient(135deg, rgba(35, 134, 54, 0.2), rgba(35, 134, 54, 0.05));
    border: 2px solid rgba(35, 134, 54, 0.3);
    color: #238636;
}

.payment-icon.emola {
    background: linear-gradient(135deg, rgba(88, 166, 255, 0.2), rgba(88, 166, 255, 0.05));
    border: 2px solid rgba(88, 166, 255, 0.3);
    color: #58a6ff;
}

.payment-icon.card {
    background: linear-gradient(135deg, rgba(163, 113, 247, 0.2), rgba(163, 113, 247, 0.05));
    border: 2px solid rgba(163, 113, 247, 0.3);
    color: #a371f7;
}

.payment-method-card h3 {
    font-size: 18px;
    font-weight: 700;
    color: #e6edf3;
    margin-bottom: 8px;
}

.payment-method-card p {
    font-size: 14px;
    color: #7d8590;
    margin-bottom: 16px;
}

.payment-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: rgba(35, 134, 54, 0.1);
    border: 1px solid rgba(35, 134, 54, 0.2);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #238636;
}

/* Responsive */
@media (max-width: 768px) {
    .payment-methods-grid {
        grid-template-columns: 1fr;
    }
    
    .payment-modal-header {
        padding: 20px 24px;
    }
    
    .payment-modal-body {
        padding: 24px;
    }
}
</style>

<script>
function loadCartItems() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const emptyCart = document.getElementById('emptyCart');
    const cartContent = document.getElementById('cartContent');
    const cartItems = document.getElementById('cartItems');

    if (cart.length === 0) {
        emptyCart.style.display = 'block';
        cartContent.style.display = 'none';
        return;
    }

    emptyCart.style.display = 'none';
    cartContent.style.display = 'grid';

    cartItems.innerHTML = cart.map(item => `
        <div class="cart-item" data-id="${item.id}">
            <div class="item-image">
                <i class="fa-solid fa-leaf"></i>
            </div>
            
            <div class="item-details">
                <div class="item-name">${item.name}</div>
                <span class="item-category">🌱 Produto Ecológico</span>
                <div class="item-price">
                    ${formatPrice(item.price)}
                    <small>MZN</small>
                </div>
                
                <div class="quantity-controls">
                    <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">
                        <i class="fa-solid fa-minus"></i>
                    </button>
                    <span class="qty-display">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
            
            <div class="item-actions">
                <button class="btn-remove" onclick="removeFromCart(${item.id})">
                    <i class="fa-solid fa-trash"></i>
                    <span>Remover</span>
                </button>
                <div class="item-total">${formatPrice(item.price * item.quantity)} MZN</div>
            </div>
        </div>
    `).join('');

    updateCartSummary();
}

function updateQuantity(productId, change) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const item = cart.find(i => i.id === productId);
    
    if (item) {
        item.quantity += change;
        
        if (item.quantity <= 0) {
            removeFromCart(productId);
            return;
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCartItems();
        updateCartBadge();
    }
}

function removeFromCart(productId) {
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    cart = cart.filter(item => item.id !== productId);
    
    localStorage.setItem('cart', JSON.stringify(cart));
    loadCartItems();
    updateCartBadge();
    showToast('🗑️ Produto removido do carrinho', 'warning');
}

function clearCart() {
    if (confirm('Tem certeza que deseja limpar todo o carrinho?')) {
        localStorage.setItem('cart', JSON.stringify([]));
        loadCartItems();
        updateCartBadge();
        showToast('🗑️ Carrinho limpo com sucesso', 'success');
    }
}

function updateCartSummary() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('subtotalValue').textContent = formatPrice(subtotal) + ' MZN';
    document.getElementById('totalValue').textContent = formatPrice(subtotal) + ' MZN';
}

function proceedToCheckout() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    
    if (cart.length === 0) {
        showToast('⚠️ Seu carrinho está vazio', 'warning');
        return;
    }
    
    // Abrir modal de método de pagamento
    document.getElementById('paymentMethodModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentMethodModal').style.display = 'none';
}

function selectPaymentMethod(method) {
    closePaymentModal();
    
    const methodNames = {
        'manual': 'Pagamento Manual',
        'mpesa': 'M-Pesa',
        'emola': 'E-Mola',
        'card': 'Cartão de Crédito'
    };
    
    showToast(`💳 ${methodNames[method]} selecionado. Redirecionando...`, 'info');
    
    setTimeout(() => {
        window.location.href = `checkout.php?method=${method}`;
    }, 800);
}

function formatPrice(price) {
    return parseFloat(price).toLocaleString('pt-MZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Carregar ao abrir a página
loadCartItems();

// Fechar modal ao clicar fora
document.addEventListener('click', function(e) {
    const modal = document.getElementById('paymentMethodModal');
    if (e.target === modal) {
        closePaymentModal();
    }
});
</script>