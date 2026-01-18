<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';

require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

/* ================= 1. BLOQUEIO DE ACESSO (AUTENTICAÇÃO) ================= */
if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

/* ================= 2. REFORÇO DE SEGURANÇA (AUTORIZAÇÃO POR TIPO E CARGO) ================= */
if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    header("Location: ../../pages/admin/dashboard.php");
    exit;
}

if ($_SESSION['auth']['type'] !== 'person') {
    if ($_SESSION['auth']['type'] === 'company') {
        header("Location: ../business/dashboard_business.php");
    } else {
        header("Location: ../../registration/login/login.php?error=acesso_proibido");
    }
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

/* ================= 3. BUSCAR USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT 
        nome, apelido, email, telefone, 
        public_id, status, registration_step, 
        email_verified_at, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../../registration/login/login.php?error=user_not_found");
    exit;
}

/* ================= 4. BLOQUEIOS DE SEGURANÇA ESPECÍFICOS ================= */

// Email não confirmado
if (!$user['email_verified_at']) {
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

// UID não gerado
if (!$user['public_id']) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

// Conta bloqueada
if ($user['status'] === 'blocked') {
    die('Acesso restrito: Sua conta está bloqueada. Por favor, contacte o suporte técnico.');
}

// Helper para exibição amigável
$statusTraduzido = [
    'active' => 'Ativa ✅',
    'pending' => 'Pendente ⏳',
    'blocked' => 'Bloqueada ❌'
];

$customerId = $userId;
$customerName = $user['nome'];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            --gh-purple: #8957e5;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans", Helvetica, Arial, sans-serif;
            background: var(--gh-bg-primary);
            color: var(--gh-text);
            line-height: 1.5;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 16px;
        }

        /* Header */
        .header {
            background: var(--gh-bg-secondary);
            border-bottom: 1px solid var(--gh-border);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
            color: var(--gh-text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo i {
            color: var(--gh-green);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cart-btn {
            position: relative;
            background: transparent;
            border: 1px solid var(--gh-border);
            color: var(--gh-text);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .cart-btn:hover {
            background: var(--gh-bg-tertiary);
            border-color: var(--gh-text-secondary);
        }

        .cart-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--gh-red);
            color: white;
            border-radius: 12px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--gh-bg-tertiary);
            border: 1px solid var(--gh-border);
            border-radius: 6px;
            cursor: pointer;
        }

        /* Main */
        .main {
            padding: 32px 0;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--gh-text-secondary);
            margin-bottom: 24px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--gh-border);
            overflow-x: auto;
        }

        .tab {
            padding: 12px 16px;
            background: transparent;
            border: none;
            color: var(--gh-text-secondary);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--gh-text);
        }

        .tab.active {
            color: var(--gh-text);
            border-bottom-color: var(--gh-orange);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Stats Grid */
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
        }

        .stat-label {
            font-size: 12px;
            color: var(--gh-text-secondary);
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }

        /* Products Grid */
        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px;
            background: var(--gh-bg-secondary);
            border: 1px solid var(--gh-border);
            border-radius: 6px;
            color: var(--gh-text);
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--gh-blue);
        }

        .filter-select {
            padding: 8px 12px;
            background: var(--gh-bg-secondary);
            border: 1px solid var(--gh-border);
            border-radius: 6px;
            color: var(--gh-text);
            font-size: 14px;
            cursor: pointer;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: var(--gh-bg-secondary);
            border: 1px solid var(--gh-border);
            border-radius: 6px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .product-card:hover {
            border-color: var(--gh-text-secondary);
            transform: translateY(-2px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: var(--gh-bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--gh-border);
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            padding: 16px;
        }

        .product-company {
            font-size: 12px;
            color: var(--gh-text-secondary);
            margin-bottom: 4px;
        }

        .product-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--gh-green);
            margin-bottom: 12px;
        }

        .product-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border: 1px solid;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
            justify-content: center;
        }

        .btn-primary {
            background: var(--gh-green);
            border-color: var(--gh-green);
            color: white;
            flex: 1;
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

        /* Orders */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .order-card {
            background: var(--gh-bg-secondary);
            border: 1px solid var(--gh-border);
            border-radius: 6px;
            padding: 16px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .order-number {
            font-weight: 600;
            font-size: 16px;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-pending { background: rgba(210, 153, 34, 0.2); color: var(--gh-orange); }
        .badge-confirmed { background: rgba(31, 111, 235, 0.2); color: var(--gh-blue); }
        .badge-processing { background: rgba(137, 87, 229, 0.2); color: var(--gh-purple); }
        .badge-shipped { background: rgba(35, 134, 54, 0.2); color: var(--gh-green); }
        .badge-delivered { background: rgba(35, 134, 54, 0.3); color: var(--gh-green-bright); }
        .badge-cancelled { background: rgba(218, 54, 51, 0.2); color: var(--gh-red); }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            font-size: 14px;
        }

        .order-detail-item {
            display: flex;
            justify-content: space-between;
        }

        .order-detail-label {
            color: var(--gh-text-secondary);
        }

        /* Cart Sidebar */
        .cart-sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            max-width: 90vw;
            height: 100vh;
            background: var(--gh-bg-secondary);
            border-left: 1px solid var(--gh-border);
            z-index: 1000;
            transition: right 0.3s;
            display: flex;
            flex-direction: column;
        }

        .cart-sidebar.open {
            right: 0;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .cart-overlay.show {
            display: block;
        }

        .cart-header {
            padding: 16px;
            border-bottom: 1px solid var(--gh-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-title {
            font-size: 18px;
            font-weight: 600;
        }

        .cart-close {
            background: transparent;
            border: none;
            color: var(--gh-text);
            font-size: 24px;
            cursor: pointer;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .cart-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            background: var(--gh-bg-tertiary);
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .cart-item-image {
            width: 60px;
            height: 60px;
            background: var(--gh-bg-primary);
            border-radius: 4px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--gh-border);
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .cart-item-price {
            color: var(--gh-green);
            font-weight: 600;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .qty-btn {
            width: 24px;
            height: 24px;
            background: var(--gh-bg-primary);
            border: 1px solid var(--gh-border);
            border-radius: 4px;
            color: var(--gh-text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-remove {
            background: transparent;
            border: none;
            color: var(--gh-red);
            cursor: pointer;
            font-size: 18px;
        }

        .cart-footer {
            padding: 16px;
            border-top: 1px solid var(--gh-border);
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gh-text-secondary);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal.show {
            display: flex;
        }

        .modal-dialog {
            background: var(--gh-bg-secondary);
            border: 1px solid var(--gh-border);
            border-radius: 8px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 16px;
            border-bottom: 1px solid var(--gh-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: var(--gh-text);
            font-size: 24px;
            cursor: pointer;
        }

        .modal-body {
            padding: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: var(--gh-bg-primary);
            border: 1px solid var(--gh-border);
            border-radius: 6px;
            color: var(--gh-text);
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gh-blue);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .modal-footer {
            padding: 16px;
            border-top: 1px solid var(--gh-border);
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        /* Alert */
        #alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
        }

        .alert {
            padding: 16px;
            border: 1px solid;
            border-radius: 8px;
            display: flex;
            gap: 12px;
            animation: slideIn 0.3s;
        }

        .alert-success { background: rgba(35, 134, 54, 0.2); border-color: var(--gh-green); color: var(--gh-green); }
        .alert-error { background: rgba(218, 54, 51, 0.2); border-color: var(--gh-red); color: var(--gh-red); }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .product-image {
                height: 150px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .header-actions {
                gap: 8px;
            }

            .user-menu span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div id="alert-container"></div>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo">
                    <i class="fa-solid fa-leaf"></i>
                    VisionGreen
                </a>
                <div class="header-actions">
                    <button class="cart-btn" onclick="toggleCart()">
                        <i class="fa-solid fa-shopping-cart"></i>
                        Carrinho
                        <span class="cart-badge" id="cartCount">0</span>
                    </button>
                    <div class="user-menu">
                        <i class="fa-solid fa-user-circle"></i>
                        <span><?= htmlspecialchars($customerName) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main -->
    <main class="main">
        <div class="container">
            <h1 class="page-title">Olá, <?= htmlspecialchars($customerName) ?>!</h1>
            <p class="page-subtitle">Bem-vindo ao seu painel de compras</p>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Meus Pedidos</div>
                    <div class="stat-value" id="statTotalPedidos">--</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Gasto</div>
                    <div class="stat-value" id="statTotalGasto" style="font-size: 20px;">--</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pedidos Pendentes</div>
                    <div class="stat-value" id="statPendentes">--</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pedidos Entregues</div>
                    <div class="stat-value" id="statEntregues">--</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('produtos')">
                    <i class="fa-solid fa-store"></i> Produtos
                </button>
                <button class="tab" onclick="switchTab('pedidos')">
                    <i class="fa-solid fa-box"></i> Meus Pedidos
                </button>
            </div>

            <!-- Tab: Produtos -->
            <div class="tab-content active" id="tab-produtos">
                <div class="products-header">
                    <div class="search-box">
                        <input type="text" class="search-input" id="searchProducts" placeholder="Buscar produtos...">
                    </div>
                    <select class="filter-select" id="filterCompany">
                        <option value="">Todas as Empresas</option>
                    </select>
                </div>

                <div class="products-grid" id="productsGrid">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
                        <p>Carregando produtos...</p>
                    </div>
                </div>
            </div>

            <!-- Tab: Pedidos -->
            <div class="tab-content" id="tab-pedidos">
                <div class="orders-list" id="ordersList">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
                        <p>Carregando pedidos...</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Cart Sidebar -->
    <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3 class="cart-title">Carrinho</h3>
            <button class="cart-close" onclick="toggleCart()">&times;</button>
        </div>
        <div class="cart-items" id="cartItems">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <p>Carrinho vazio</p>
            </div>
        </div>
        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">0,00 MZN</span>
            </div>
            <button class="btn btn-primary" style="width: 100%;" onclick="openCheckout()">
                <i class="fa-solid fa-credit-card"></i>
                Finalizar Compra
            </button>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div class="modal" id="checkoutModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Finalizar Compra</h3>
                <button class="modal-close" onclick="closeCheckout()">&times;</button>
            </div>
            <form id="checkoutForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Endereço de Entrega *</label>
                        <textarea class="form-control" id="shippingAddress" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cidade *</label>
                        <input type="text" class="form-control" id="shippingCity" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Telefone de Contato *</label>
                        <input type="tel" class="form-control" id="shippingPhone" value="<?= htmlspecialchars($user['telefone']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Método de Pagamento *</label>
                        <select class="form-control" id="paymentMethod" required>
                            <option value="">Selecione...</option>
                            <option value="dinheiro">Dinheiro na Entrega</option>
                            <option value="transferencia">Transferência Bancária</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="emola">E-Mola</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" id="customerNotes"></textarea>
                    </div>

                    <div style="background: var(--gh-bg-tertiary); padding: 12px; border-radius: 6px; margin-top: 16px;">
                        <strong>Total do Pedido: <span id="checkoutTotal">0,00 MZN</span></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCheckout()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar Pedido</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const customerId = <?= $customerId ?>;
        let cart = [];
        let products = [];
        let orders = [];

        // Load Cart from localStorage
        function loadCart() {
            const saved = localStorage.getItem('vsg_cart');
            if (saved) {
                cart = JSON.parse(saved);
                updateCartUI();
            }
        }

        // Save Cart
        function saveCart() {
            localStorage.setItem('vsg_cart', JSON.stringify(cart));
            updateCartUI();
        }

        // Add to Cart
        function addToCart(product) {
            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                existing.quantity++;
            } else {
                cart.push({...product, quantity: 1});
            }
            saveCart();
            showAlert('success', 'Produto adicionado ao carrinho!');
        }

        // Update Cart UI
        function updateCartUI() {
            document.getElementById('cartCount').textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            const cartItems = document.getElementById('cartItems');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                        <p>Carrinho vazio</p>
                    </div>
                `;
            } else {
                let html = '';
                cart.forEach(item => {
                    html += `
                        <div class="cart-item">
                            <div class="cart-item-image">
                                ${item.imagem ? `<img src="${item.imagem}" alt="">` : '<i class="fa-solid fa-box"></i>'}
                            </div>
                            <div class="cart-item-info">
                                <div class="cart-item-name">${escapeHtml(item.nome)}</div>
                                <div class="cart-item-price">${formatMoney(item.preco)} MZN</div>
                                <div class="cart-item-quantity">
                                    <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">-</button>
                                    <span>${item.quantity}</span>
                                    <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                                </div>
                            </div>
                            <button class="cart-item-remove" onclick="removeFromCart(${item.id})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    `;
                });
                cartItems.innerHTML = html;
            }
            
            const total = cart.reduce((sum, item) => sum + (item.preco * item.quantity), 0);
            document.getElementById('cartTotal').textContent = formatMoney(total) + ' MZN';
            document.getElementById('checkoutTotal').textContent = formatMoney(total) + ' MZN';
        }

        // Update Quantity
        function updateQuantity(productId, delta) {
            const item = cart.find(i => i.id === productId);
            if (item) {
                item.quantity += delta;
                if (item.quantity <= 0) {
                    removeFromCart(productId);
                } else {
                    saveCart();
                }
            }
        }

        // Remove from Cart
        function removeFromCart(productId) {
            cart = cart.filter(item => item.id !== productId);
            saveCart();
        }

        // Toggle Cart
        function toggleCart() {
            const sidebar = document.getElementById('cartSidebar');
            const overlay = document.getElementById('cartOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }

        // Switch Tab
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
            
            if (tab === 'pedidos') {
                loadOrders();
            }
        }

        // Load Products
        async function loadProducts() {
            try {
                const response = await fetch('actions/get_products.php');
                const data = await response.json();
                
                if (data.success) {
                    products = data.products;
                    renderProducts(products);
                    populateCompanyFilter(data.companies);
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }

        // Render Products
        function renderProducts(items) {
            const grid = document.getElementById('productsGrid');
            
            if (items.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-store"></i></div>
                        <p>Nenhum produto encontrado</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            items.forEach(product => {
                html += `
                    <div class="product-card">
                        <div class="product-image">
                            ${product.imagem ? `<img src="${product.imagem}" alt="">` : '<i class="fa-solid fa-box"></i>'}
                        </div>
                        <div class="product-info">
                            <div class="product-company">${escapeHtml(product.company_name)}</div>
                            <div class="product-name">${escapeHtml(product.nome)}</div>
                            <div class="product-price">${formatMoney(product.preco)} MZN</div>
                            <div class="product-actions">
                                <button class="btn btn-primary" onclick='addToCart(${JSON.stringify(product)})'>
                                    <i class="fa-solid fa-cart-plus"></i> Adicionar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            grid.innerHTML = html;
        }

        // Populate Company Filter
        function populateCompanyFilter(companies) {
            const select = document.getElementById('filterCompany');
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.nome;
                select.appendChild(option);
            });
        }

        // Search Products
        document.getElementById('searchProducts').addEventListener('input', (e) => {
            const search = e.target.value.toLowerCase();
            const filtered = products.filter(p => 
                p.nome.toLowerCase().includes(search) || 
                p.company_name.toLowerCase().includes(search)
            );
            renderProducts(filtered);
        });

        // Filter by Company
        document.getElementById('filterCompany').addEventListener('change', (e) => {
            const companyId = e.target.value;
            const filtered = companyId ? products.filter(p => p.company_id == companyId) : products;
            renderProducts(filtered);
        });

        // Load Orders
        async function loadOrders() {
            try {
                const response = await fetch('actions/get_my_orders.php');
                const data = await response.json();
                
                if (data.success) {
                    orders = data.orders;
                    renderOrders(orders);
                    updateStats(data.stats);
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }

        // Render Orders
        function renderOrders(items) {
            const list = document.getElementById('ordersList');
            
            if (items.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-box"></i></div>
                        <p>Você ainda não fez nenhum pedido</p>
                    </div>
                `;
                return;
            }
            
            const statusLabels = {
                pending: 'Pendente', confirmed: 'Confirmado', processing: 'Processando',
                shipped: 'Enviado', delivered: 'Entregue', cancelled: 'Cancelado'
            };
            
            let html = '';
            items.forEach(order => {
                html += `
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-number">#${escapeHtml(order.order_number)}</div>
                            <span class="badge badge-${order.status}">${statusLabels[order.status]}</span>
                        </div>
                        <div class="order-details">
                            <div class="order-detail-item">
                                <span class="order-detail-label">Data:</span>
                                <span>${formatDate(order.order_date)}</span>
                            </div>
                            <div class="order-detail-item">
                                <span class="order-detail-label">Total:</span>
                                <strong>${formatMoney(order.total)} ${order.currency}</strong>
                            </div>
                            <div class="order-detail-item">
                                <span class="order-detail-label">Itens:</span>
                                <span>${order.items_count} produto(s)</span>
                            </div>
                            <div class="order-detail-item">
                                <span class="order-detail-label">Pagamento:</span>
                                <span>${order.payment_status === 'paid' ? '✓ Pago' : 'Pendente'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        // Update Stats
        function updateStats(stats) {
            document.getElementById('statTotalPedidos').textContent = stats.total || 0;
            document.getElementById('statTotalGasto').textContent = formatMoney(stats.total_gasto || 0) + ' MZN';
            document.getElementById('statPendentes').textContent = stats.pendentes || 0;
            document.getElementById('statEntregues').textContent = stats.entregues || 0;
        }

        // Checkout
        function openCheckout() {
            if (cart.length === 0) {
                showAlert('error', 'Carrinho vazio!');
                return;
            }
            document.getElementById('checkoutModal').classList.add('show');
        }

        function closeCheckout() {
            document.getElementById('checkoutModal').classList.remove('show');
        }

        document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('items', JSON.stringify(cart));
            formData.append('shipping_address', document.getElementById('shippingAddress').value);
            formData.append('shipping_city', document.getElementById('shippingCity').value);
            formData.append('shipping_phone', document.getElementById('shippingPhone').value);
            formData.append('payment_method', document.getElementById('paymentMethod').value);
            formData.append('customer_notes', document.getElementById('customerNotes').value);
            
            try {
                const response = await fetch('actions/create_order.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Pedido realizado com sucesso!');
                    cart = [];
                    saveCart();
                    closeCheckout();
                    toggleCart();
                    switchTab('pedidos');
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                showAlert('error', 'Erro ao processar pedido');
            }
        });

        // Utilities
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatMoney(value) {
            return parseFloat(value).toFixed(2).replace('.', ',');
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('pt-BR');
        }

        function showAlert(type, message) {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fa-solid fa-${type === 'success' ? 'circle-check' : 'circle-exclamation'}"></i>
                <div>${message}</div>
            `;
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Init
        loadCart();
        loadProducts();
        loadOrders();
    </script>
</body>
</html>