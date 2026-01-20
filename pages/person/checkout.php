<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$paymentMethod = isset($_GET['method']) ? $_GET['method'] : 'manual';

// Buscar dados do usuário
$stmt = $mysqli->prepare("SELECT nome, apelido, email, telefone FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$displayName = $user['apelido'] ?: $user['nome'];
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=238636&color=fff&bold=true";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #238636;
            --primary-dark: #1a7f37;
            --dark-bg: #0d1117;
            --darker-bg: #010409;
            --card-bg: #161b22;
            --border: #30363d;
            --text-primary: #e6edf3;
            --text-secondary: #7d8590;
            --accent: #58a6ff;
            --danger: #f85149;
            --warning: #d29922;
            --success: #3fb950;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--darker-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Header */
        .checkout-header {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #000;
        }

        .header-title h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .header-title p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--primary);
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Grid Layout */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
        }

        /* Form Section */
        .form-section {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 32px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            background: var(--darker-bg);
            border: 2px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(35, 134, 54, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* Payment Method Display */
        .payment-selected {
            background: linear-gradient(135deg, rgba(35, 134, 54, 0.1), transparent);
            border: 2px solid rgba(35, 134, 54, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .payment-icon-display {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #000;
        }

        .payment-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .payment-info p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .change-method-btn {
            margin-left: auto;
            padding: 10px 20px;
            background: var(--darker-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .change-method-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Order Summary */
        .order-summary {
            position: sticky;
            top: 20px;
        }

        .summary-card {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 28px;
        }

        .summary-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .summary-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: var(--darker-bg);
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .item-image {
            width: 60px;
            height: 60px;
            background: var(--card-bg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .item-quantity {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .item-price {
            font-weight: 700;
            color: var(--primary);
            align-self: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 15px;
            color: var(--text-secondary);
        }

        .summary-row.total {
            border-top: 2px solid var(--border);
            padding-top: 16px;
            margin-top: 12px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .summary-row.total span:last-child {
            color: var(--primary);
        }

        .btn-place-order {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 12px;
            color: #000;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-place-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(35, 134, 54, 0.4);
        }

        .btn-place-order:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .security-badges {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .security-badge i {
            color: var(--primary);
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(1, 4, 9, 0.95);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-content {
            text-align: center;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: relative;
                top: 0;
            }
        }

        @media (max-width: 768px) {
            .checkout-header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .payment-selected {
                flex-direction: column;
                text-align: center;
            }

            .change-method-btn {
                margin: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <!-- Header -->
        <div class="checkout-header">
            <div class="header-left">
                <div class="logo">
                    <i class="fa-solid fa-leaf"></i>
                </div>
                <div class="header-title">
                    <h1>Finalizar Compra</h1>
                    <p>Complete seus dados para concluir o pedido</p>
                </div>
            </div>
            <div class="header-user">
                <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
                <span class="user-name"><?= htmlspecialchars($displayName) ?></span>
            </div>
        </div>

        <!-- Grid -->
        <div class="checkout-grid">
            <!-- Form -->
            <div>
                <!-- Método de Pagamento -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-credit-card"></i>
                        Método de Pagamento
                    </h2>

                    <div class="payment-selected" id="paymentDisplay">
                        <div class="payment-icon-display">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                        </div>
                        <div class="payment-info">
                            <h3>Carregando...</h3>
                            <p>Aguarde...</p>
                        </div>
                        <button class="change-method-btn" onclick="window.history.back()">
                            <i class="fa-solid fa-exchange"></i> Alterar
                        </button>
                    </div>
                </div>

                <!-- Dados de Entrega -->
                <div class="form-section" style="margin-top: 24px;">
                    <h2 class="section-title">
                        <i class="fa-solid fa-truck"></i>
                        Dados de Entrega
                    </h2>

                    <form id="checkoutForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nome Completo *</label>
                                <input type="text" name="nome" value="<?= htmlspecialchars($user['nome']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Telefone *</label>
                                <input type="tel" name="telefone" value="<?= htmlspecialchars($user['telefone']) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Endereço de Entrega *</label>
                            <input type="text" name="endereco" placeholder="Rua, número, bairro" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Cidade *</label>
                                <input type="text" name="cidade" placeholder="Ex: Maputo" required>
                            </div>
                            <div class="form-group">
                                <label>Província *</label>
                                <select name="provincia" required>
                                    <option value="">Selecione...</option>
                                    <option value="Maputo">Maputo</option>
                                    <option value="Gaza">Gaza</option>
                                    <option value="Inhambane">Inhambane</option>
                                    <option value="Sofala">Sofala</option>
                                    <option value="Manica">Manica</option>
                                    <option value="Tete">Tete</option>
                                    <option value="Zambézia">Zambézia</option>
                                    <option value="Nampula">Nampula</option>
                                    <option value="Cabo Delgado">Cabo Delgado</option>
                                    <option value="Niassa">Niassa</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Observações (opcional)</label>
                            <textarea name="observacoes" placeholder="Ponto de referência, instruções de entrega..."></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary -->
            <div class="order-summary">
                <div class="summary-card">
                    <h2 class="section-title">
                        <i class="fa-solid fa-receipt"></i>
                        Resumo do Pedido
                    </h2>

                    <div class="summary-items" id="summaryItems">
                        <!-- Itens carregados via JS -->
                    </div>

                    <div class="summary-row">
                        <span>Subtotal (<span id="totalItems">0</span> itens)</span>
                        <span id="subtotalValue">0,00 MZN</span>
                    </div>

                    <div class="summary-row">
                        <span>Frete</span>
                        <span class="text-success">Grátis</span>
                    </div>

                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="totalValue">0,00 MZN</span>
                    </div>

                    <button class="btn-place-order" onclick="placeOrder()">
                        <i class="fa-solid fa-check-circle"></i>
                        Confirmar Pedido
                    </button>

                    <div class="security-badges">
                        <div class="security-badge">
                            <i class="fa-solid fa-shield-halved"></i>
                            Compra Segura
                        </div>
                        <div class="security-badge">
                            <i class="fa-solid fa-lock"></i>
                            Dados Protegidos
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <div class="loading-text">Processando seu pedido...</div>
        </div>
    </div>

    <script>
        const paymentMethod = '<?= $paymentMethod ?>';
        
        const paymentMethods = {
            manual: {
                icon: 'fa-hand-holding-dollar',
                name: 'Pagamento Manual',
                description: 'Pague na entrega em dinheiro'
            },
            mpesa: {
                icon: 'fa-mobile-screen',
                name: 'M-Pesa',
                description: 'Pagamento instantâneo via M-Pesa'
            },
            emola: {
                icon: 'fa-wallet',
                name: 'E-Mola',
                description: 'Pagamento instantâneo via E-Mola'
            },
            card: {
                icon: 'fa-credit-card',
                name: 'Cartão de Crédito',
                description: 'Visa, Mastercard'
            }
        };

        // Carregar método de pagamento
        function loadPaymentMethod() {
            const method = paymentMethods[paymentMethod] || paymentMethods.manual;
            const display = document.getElementById('paymentDisplay');
            
            display.querySelector('.payment-icon-display i').className = `fa-solid ${method.icon}`;
            display.querySelector('h3').textContent = method.name;
            display.querySelector('p').textContent = method.description;
        }

        // Carregar resumo do carrinho
        function loadOrderSummary() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const summaryItems = document.getElementById('summaryItems');

            if (cart.length === 0) {
                window.location.href = 'dashboard_person.php';
                return;
            }

            summaryItems.innerHTML = cart.map(item => `
                <div class="summary-item">
                    <div class="item-image">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <div class="item-details">
                        <div class="item-name">${item.name}</div>
                        <div class="item-quantity">Quantidade: ${item.quantity}</div>
                    </div>
                    <div class="item-price">${formatPrice(item.price * item.quantity)} MZN</div>
                </div>
            `).join('');

            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            document.getElementById('totalItems').textContent = totalItems;
            document.getElementById('subtotalValue').textContent = formatPrice(subtotal) + ' MZN';
            document.getElementById('totalValue').textContent = formatPrice(subtotal) + ' MZN';
        }

        // Finalizar pedido
        async function placeOrder() {
            const form = document.getElementById('checkoutForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');

            if (cart.length === 0) {
                alert('Carrinho vazio!');
                return;
            }

            const orderData = {
                payment_method: paymentMethod,
                items: cart,
                shipping: {
                    nome: formData.get('nome'),
                    telefone: formData.get('telefone'),
                    endereco: formData.get('endereco'),
                    cidade: formData.get('cidade'),
                    provincia: formData.get('provincia'),
                    observacoes: formData.get('observacoes')
                }
            };

            document.getElementById('loadingOverlay').style.display = 'flex';

            try {
                const response = await fetch('actions/process_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(orderData)
                });

                const result = await response.json();

                if (result.success) {
                    localStorage.removeItem('cart');
                    
                    alert('✅ Pedido realizado com sucesso!\n\nNúmero do Pedido: ' + result.order_number);
                    window.location.href = 'dashboard_person.php?page=meus_pedidos';
                } else {
                    alert('❌ Erro: ' + result.message);
                }
            } catch (error) {
                alert('❌ Erro ao processar pedido. Tente novamente.');
                console.error(error);
            } finally {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
        }

        function formatPrice(price) {
            return parseFloat(price).toLocaleString('pt-MZ', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            loadPaymentMethod();
            loadOrderSummary();
        });
    </script>
</body>
</html>