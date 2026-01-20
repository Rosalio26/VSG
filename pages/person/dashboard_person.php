<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

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

$stmt = $mysqli->prepare("
    SELECT nome, apelido, email, telefone, public_id, status, registration_step, 
           email_verified_at, created_at, type
    FROM users WHERE id = ? LIMIT 1
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

if (!$user['email_verified_at']) {
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

if (!$user['public_id']) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

if ($user['status'] === 'blocked') {
    die('Acesso restrito: Sua conta está bloqueada. Por favor, contacte o suporte técnico.');
}

$displayName = $user['apelido'] ?: $user['nome'];
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=00ff88&color=000&bold=true";

$categoryLabels = [
    'reciclavel' => ['icon' => '♻️', 'label' => 'Reciclável'],
    'sustentavel' => ['icon' => '🌿', 'label' => 'Sustentável'],
    'servico' => ['icon' => '🛠️', 'label' => 'Serviços'],
    'visiongreen' => ['icon' => '🌱', 'label' => 'VisionGreen'],
    'ecologico' => ['icon' => '🌍', 'label' => 'Ecológico'],
    'outro' => ['icon' => '📦', 'label' => 'Outros']
];

$priceRanges = [
    ['min' => 0, 'max' => 1000, 'label' => 'Até 1.000 MZN'],
    ['min' => 1000, 'max' => 5000, 'label' => '1.000 - 5.000 MZN'],
    ['min' => 5000, 'max' => 10000, 'label' => '5.000 - 10.000 MZN'],
    ['min' => 10000, 'max' => 999999, 'label' => 'Acima de 10.000 MZN']
];

$stats = [
    'mensagens_nao_lidas' => 0,
    'pedidos_em_andamento' => 0,
    'total_gasto' => 0
];

$result = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = '$userId' AND status = 'nao_lida'");
if ($result) {
    $stats['mensagens_nao_lidas'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

$result = $mysqli->query("SELECT COUNT(*) as total FROM orders WHERE customer_id = '$userId' AND status IN ('pendente', 'confirmado', 'processando')");
if ($result) {
    $stats['pedidos_em_andamento'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}

$result = $mysqli->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE customer_id = '$userId' AND payment_status = 'pago'");
if ($result) {
    $stats['total_gasto'] = (float)$result->fetch_assoc()['total'];
    $result->close();
}

$stmt = $mysqli->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.status,
        o.payment_status,
        o.payment_method,
        o.total,
        o.currency,
        o.shipping_address,
        o.shipping_city,
        o.customer_notes,
        o.created_at,
        COALESCE(u.nome, 'Empresa Desconhecida') as empresa_nome,
        COALESCE((SELECT COUNT(*) FROM order_items WHERE order_id = o.id), 0) as items_count,
        COALESCE((SELECT SUM(quantity) FROM order_items WHERE order_id = o.id), 0) as total_items
    FROM orders o
    LEFT JOIN users u ON o.company_id = u.id
    WHERE o.customer_id = ? 
    AND o.deleted_at IS NULL
    ORDER BY o.order_date DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statusMap = [
    'pendente' => ['icon' => '⏳', 'label' => 'Pendente', 'color' => 'warning'],
    'confirmado' => ['icon' => '✓', 'label' => 'Confirmado', 'color' => 'info'],
    'processando' => ['icon' => '⚙️', 'label' => 'Processando', 'color' => 'primary'],
    'enviado' => ['icon' => '🚚', 'label' => 'Enviado', 'color' => 'accent'],
    'entregue' => ['icon' => '✅', 'label' => 'Entregue', 'color' => 'success'],
    'cancelado' => ['icon' => '❌', 'label' => 'Cancelado', 'color' => 'danger']
];

$paymentStatusMap = [
    'pendente' => ['icon' => '⏳', 'label' => 'Aguardando', 'color' => 'warning'],
    'pago' => ['icon' => '✓', 'label' => 'Pago', 'color' => 'success'],
    'parcial' => ['icon' => '⚠️', 'label' => 'Parcial', 'color' => 'warning'],
    'reembolsado' => ['icon' => '↩️', 'label' => 'Reembolsado', 'color' => 'info']
];

$paymentMethodMap = [
    'mpesa' => ['icon' => '📱', 'label' => 'M-Pesa'],
    'emola' => ['icon' => '💳', 'label' => 'E-Mola'],
    'visa' => ['icon' => '💳', 'label' => 'Visa'],
    'mastercard' => ['icon' => '💳', 'label' => 'Mastercard'],
    'manual' => ['icon' => '💵', 'label' => 'Pagamento Manual']
];

$pedidosStats = [
    'total' => count($orders),
    'pendentes' => count(array_filter($orders, fn($o) => $o['status'] === 'pendente')),
    'em_andamento' => count(array_filter($orders, fn($o) => in_array($o['status'], ['confirmado', 'processando', 'enviado']))),
    'entregues' => count(array_filter($orders, fn($o) => $o['status'] === 'entregue'))
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen Marketplace | <?= htmlspecialchars($displayName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .back-home-btn > button{
            background-color: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 10px;
        }

        .back-home-btn > button > i {
            color: var(--text-secondary);
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .back-home-btn, .collapse-btn {display: none;}
            
        }
        .no-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
            height: 100%;
            background: var(--gh-bg-secondary);
        }

        .no-image-placeholder span {
            font-size: 10px;
            text-transform: uppercase;
            color: var(--gh-text-secondary);
        }

        .no-image-placeholder strong {
            font-size: 13px;
            color: var(--primary);
        }
        
        /* Transição suave para badges */
        .badge, .mobile-nav-badge, .stat-mini-value {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        .badge.hiding, .mobile-nav-badge.hiding {
            opacity: 0;
            transform: scale(0.8);
        }
    </style>
</head>
<body>
    <div class="loading-bar" id="loadingBar"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand">
                <div class="logo-icon">
                    <i class="fa-solid fa-leaf"></i>
                </div>
                <div class="logo-text">
                    <span class="logo-main">VISIONGREEN</span>
                    <span class="logo-sub">Marketplace Eco</span>
                </div>
            </div>
        </div>

        <div class="filters-container">
            <div class="filter-section">
                <h3>Categorias</h3>
                <?php foreach ($categoryLabels as $value => $data): ?>
                <div class="filter-option">
                    <input type="checkbox" id="cat_<?= $value ?>" class="category-filter" value="<?= $value ?>">
                    <span class="checkbox-custom"></span>
                    <label for="cat_<?= $value ?>"><?= $data['icon'] ?> <?= $data['label'] ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="filter-section">
                <h3>Faixa de Preço</h3>
                <?php foreach ($priceRanges as $index => $range): ?>
                <div class="filter-option">
                    <input type="radio" name="price_range" id="price_<?= $index ?>" class="price-filter" 
                           value="<?= $range['min'] ?>-<?= $range['max'] ?>">
                    <span class="radio-custom"></span>
                    <label for="price_<?= $index ?>"><?= $range['label'] ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="filter-section">
                <h3>Disponibilidade</h3>
                <div class="filter-option">
                    <input type="checkbox" id="in_stock" class="stock-filter" value="1">
                    <span class="checkbox-custom"></span>
                    <label for="in_stock">✓ Em Estoque</label>
                </div>
            </div>

            <button class="btn-filter-reset" onclick="resetFilters()">
                <i class="fa-solid fa-rotate-right"></i>
                <span>Limpar Filtros</span>
            </button>
        </div>

        <div class="sidebar-footer">
            <div class="stats-mini">
                <div class="stat-mini">
                    <span class="stat-mini-value" id="sidebar-pedidos-badge"><?= $stats['pedidos_em_andamento'] ?></span>
                    <span class="stat-mini-label">Pedidos</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-mini-value"><?= number_format($stats['total_gasto'], 0) ?></span>
                    <span class="stat-mini-label">Total Gasto</span>
                </div>
            </div>

            <a href="javascript:void(0)" onclick="navigateTo('configuracoes')" class="action-btn btn-settings">
                <i class="fa-solid fa-gear"></i>
                <span>Configurações</span>
            </a>

            <form method="post" action="../../registration/login/logout.php">
                <?= csrf_field(); ?>
                <button type="submit" class="action-btn btn-logout">
                    <i class="fa-solid fa-power-off"></i>
                    <span>Sair</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header-main">
            <div class="header-content">

                <button class="mobile-menu-btn" onclick="toggleMobileMenu()" title="Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="col-home-collapse">
                    <button class="collapse-btn" onclick="toggleSidebar()" title="Colapsar Sidebar">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    
                    <div class="back-home-btn">
                        <button class="btn-home" onclick="navigateTo('home')">
                            <i class="fa-solid fa-house"></i>
                        </button>
                    </div>
                </div>

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Buscar produtos ecológicos..." autocomplete="off">
                </div>

                <div class="header-actions">
                    <button class="icon-btn" onclick="navigateTo('carrinho')" title="Carrinho de Compras">
                        <i class="fa-solid fa-shopping-cart"></i>
                        <span class="badge cart-badge" style="display: none;">0</span>
                    </button>

                    <button class="icon-btn" onclick="navigateTo('meus_pedidos')" title="Meus Pedidos">
                        <i class="fa-solid fa-shopping-bag"></i>
                        <span class="badge" id="header-pedidos-badge" style="<?= $stats['pedidos_em_andamento'] > 0 ? '' : 'display: none;' ?>"><?= $stats['pedidos_em_andamento'] ?></span>
                    </button>

                    <button class="icon-btn" onclick="navigateTo('notificacoes')" title="Notificações">
                        <i class="fa-solid fa-bell"></i>
                        <?php if($stats['mensagens_nao_lidas'] > 0): ?>
                            <span class="badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="user-profile" onclick="navigateTo('perfil')">
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($displayName) ?></span>
                            <span class="user-role">Cliente</span>
                        </div>
                        <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
                    </div>
                </div>
            </div>
        </header>

        <div class="content-wrapper">
            <div id="content-home" class="dynamic-content active">
                <div id="productsGrid" class="products-grid">
                    <div class="empty-state">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <h3>Carregando produtos...</h3>
                        <p>Aguarde um momento</p>
                    </div>
                </div>
            </div>

            <div id="content-meus_pedidos" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando pedidos...</h3>
                </div>
            </div>

            <div id="content-notificacoes" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando notificações...</h3>
                </div>
            </div>

            <div id="content-perfil" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando perfil...</h3>
                </div>
            </div>

            <div id="content-configuracoes" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando configurações...</h3>
                </div>
            </div>

            <div id="content-carrinho" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando carrinho...</h3>
                </div>
            </div>
        </div>
    </main>

    <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>

    <nav class="mobile-nav">
        <div class="mobile-nav-grid">
            <button class="mobile-nav-item active" onclick="navigateTo('home')" data-page="home">
                <i class="fa-solid fa-house"></i>
                <span class="mobile-nav-label">Início</span>
            </button>

            <button class="mobile-nav-item" onclick="navigateTo('meus_pedidos')" data-page="meus_pedidos">
                <i class="fa-solid fa-shopping-bag"></i>
                <span class="mobile-nav-label">Pedidos</span>
                <span class="mobile-nav-badge" id="mobile-pedidos-badge" style="<?= $stats['pedidos_em_andamento'] > 0 ? '' : 'display: none;' ?>"><?= $stats['pedidos_em_andamento'] ?></span>
            </button>

            <button class="mobile-nav-item" onclick="toggleMobileMenu()" data-page="filters">
                <i class="fa-solid fa-filter"></i>
                <span class="mobile-nav-label">Filtros</span>
            </button>

            <button class="mobile-nav-item" onclick="navigateTo('notificacoes')" data-page="notificacoes">
                <i class="fa-solid fa-bell"></i>
                <span class="mobile-nav-label">Alertas</span>
                <?php if($stats['mensagens_nao_lidas'] > 0): ?>
                    <span class="mobile-nav-badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                <?php endif; ?>
            </button>

            <button class="mobile-nav-item" onclick="navigateTo('perfil')" data-page="perfil">
                <i class="fa-solid fa-user"></i>
                <span class="mobile-nav-label">Perfil</span>
            </button>
        </div>
    </nav>

    <script>
        const userData = <?= json_encode([
            'userId' => $userId,
            'nome' => $displayName,
            'email' => $user['email'],
            'publicId' => $user['public_id']
        ], JSON_UNESCAPED_UNICODE) ?>;

        const ordersData = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;
        const pedidosStats = <?= json_encode($pedidosStats, JSON_UNESCAPED_UNICODE) ?>;
        const statusMap = <?= json_encode($statusMap, JSON_UNESCAPED_UNICODE) ?>;
        const paymentStatusMap = <?= json_encode($paymentStatusMap, JSON_UNESCAPED_UNICODE) ?>;
        const paymentMethodMap = <?= json_encode($paymentMethodMap, JSON_UNESCAPED_UNICODE) ?>;

        let filters = {
            search: '',
            categories: [],
            priceRange: null,
            inStock: false
        };

        let currentPage = 'home';
        const loadedPages = new Set(['home']);

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', document.getElementById('sidebar').classList.contains('collapsed'));
        }

        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        }

        function closeMobileMenu() {
            document.getElementById('sidebar').classList.remove('mobile-open');
            document.getElementById('mobileOverlay').classList.remove('active');
        }

        // Função para limpar badges de pedidos com animação suave
        async function clearOrdersBadge() {
            const headerBadge = document.getElementById('header-pedidos-badge');
            const mobileBadge = document.getElementById('mobile-pedidos-badge');
            const sidebarBadge = document.getElementById('sidebar-pedidos-badge');
            
            // Animar saída dos badges
            [headerBadge, mobileBadge].forEach(badge => {
                if (badge) {
                    badge.classList.add('hiding');
                }
            });
            
            // Aguardar animação e então esconder
            setTimeout(() => {
                if (headerBadge) {
                    headerBadge.style.display = 'none';
                    headerBadge.textContent = '0';
                    headerBadge.classList.remove('hiding');
                }
                if (mobileBadge) {
                    mobileBadge.style.display = 'none';
                    mobileBadge.textContent = '0';
                    mobileBadge.classList.remove('hiding');
                }
                if (sidebarBadge) {
                    sidebarBadge.textContent = '0';
                }
            }, 300);
            
            // Notificar o servidor que os pedidos foram visualizados
            try {
                await fetch('actions/mark_orders_viewed.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'}
                });
                localStorage.setItem('pedidosVisualizados', Date.now().toString());
                console.log('✅ Badges de pedidos limpos');
            } catch (error) {
                console.log('⚠️ Aviso: não foi possível marcar pedidos como visualizados');
            }
        }

        async function navigateTo(page) {
            if (currentPage === page && page === 'home') return;
            closeMobileMenu();
            
            document.querySelectorAll('.mobile-nav-item').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.page === page) btn.classList.add('active');
            });

            document.querySelectorAll('.dynamic-content').forEach(content => {
                content.classList.remove('active');
            });

            const contentDiv = document.getElementById(`content-${page}`);
            if (!contentDiv) return;

            contentDiv.classList.add('active');
            currentPage = page;
            window.history.pushState({ page }, '', `?page=${page}`);

            // Limpar badge ao abrir meus pedidos
            if (page === 'meus_pedidos') {
                await clearOrdersBadge();
            }

            if (!loadedPages.has(page)) {
                await loadPageContent(page);
                loadedPages.add(page);
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function loadPageContent(page) {
            const contentDiv = document.getElementById(`content-${page}`);
            const loader = contentDiv.querySelector('.content-loading');
            
            if (loader) loader.classList.add('active');

            try {
                const response = await fetch(`pages/${page}.php`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const html = await response.text();
                if (loader) loader.remove();
                contentDiv.innerHTML = html;

                const scripts = contentDiv.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    newScript.textContent = script.textContent;
                    document.body.appendChild(newScript);
                    document.body.removeChild(newScript);
                });
            } catch (error) {
                console.error(`Erro ao carregar ${page}:`, error);
                contentDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <h3>Erro ao carregar conteúdo</h3>
                        <p>Não foi possível carregar a página. Tente novamente.</p>
                        <button class="btn-filter-reset" onclick="navigateTo('home')" style="max-width: 200px; margin: 20px auto 0;">
                            <i class="fa-solid fa-house"></i>
                            <span>Voltar ao Início</span>
                        </button>
                    </div>
                `;
            }
        }

        window.addEventListener('popstate', (event) => {
            if (event.state && event.state.page) {
                navigateTo(event.state.page);
            } else {
                navigateTo('home');
            }
        });

        function truncateText(text, maxLength) {
            if (!text) return '';
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        }

        async function loadProducts() {
            const grid = document.getElementById('productsGrid');
            const loader = document.getElementById('loadingBar');
            
            if (!grid) return;
            loader.classList.add('active');
            
            try {
                const params = new URLSearchParams({
                    search: filters.search,
                    categories: filters.categories.join(','),
                    price_range: filters.priceRange || '',
                    in_stock: filters.inStock ? '1' : ''
                });

                const response = await fetch(`actions/get_products.php?${params}`);
                const data = await response.json();

                if (data.success && data.products.length > 0) {
                    const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
                    
                    grid.innerHTML = data.products.map((p, index) => {
                        const imageSrc = p.imagem ? `../uploads/products/${p.imagem}` : '';
                        const isFavorite = favorites.includes(p.id);
                        const isNew = isProductNew(p.created_at);
                        
                        let badge = '';
                        if (p.stock === 0) {
                            badge = '<span class="product-badge out-of-stock">Esgotado</span>';
                        } else if (p.stock <= p.stock_minimo) {
                            badge = '<span class="product-badge stock-low">Últimas Unidades</span>';
                        }

                        return `
                            <div class="product-card ${isNew ? 'new' : ''}" style="animation-delay: ${index * 0.05}s">
                                <div class="product-company">
                                    <i class="fa-solid fa-store"></i>
                                    <span>DISTRIBUIDA POR</span>
                                    <span>${p.empresa_nome || 'VisionGreen'}</span>
                                </div>
                                
                                <div class="product-image">
                                    ${imageSrc ? 
                                        `<img src="${imageSrc}"  
                                            alt="${p.nome}"
                                            loading="lazy" 
                                            onerror="handleImageError(this, '${p.empresa_nome || 'VisionGreen'}')">` :
                                        '<i class="fa-solid fa-leaf"></i>'
                                    }
                                    
                                    ${badge}
                                    
                                    <button class="btn-favorite ${isFavorite ? 'active' : ''}" 
                                            onclick="event.stopPropagation(); toggleFavorite(${p.id}, this)" 
                                            title="Adicionar aos favoritos">
                                        <i class="fa-${isFavorite ? 'solid' : 'regular'} fa-heart"></i>
                                        <span>Favoritos</span>
                                    </button>
                                </div>
                                <div class="product-info">
                                        <div class="product-category">${getCategoryIcon(p.categoria)} ${getCategoryName(p.categoria)}</div>
                                        <div class="product-name" title="${p.nome}">${p.nome}</div>
                                        
                                        <div class="product-description">${truncateText(p.descricao, 80)}</div>
                                        
                                        <div class="product-footer">
                                        <div class="product-price">
                                            ${formatPrice(p.preco)}
                                            <small>MZN</small>
                                        </div>
                                        <div class="product-stock ${p.stock === 0 ? 'out' : (p.stock <= p.stock_minimo ? 'low' : '')}">
                                            ${p.stock === 0 ? '❌ Esgotado' : 
                                            p.stock <= p.stock_minimo ? `⚠️ ${p.stock} un` : 
                                            `✓ ${p.stock} un`}
                                        </div>
                                    </div>
                                    <div class="product-actions">
                                        <button class="btn-add-cart" 
                                                onclick="event.stopPropagation(); addToCart(${p.id}, '${escapeHtml(p.nome)}', ${p.preco}, this)" 
                                                ${p.stock === 0 ? 'disabled' : ''}
                                                title="Adicionar ao carrinho">
                                            <i class="fa-solid fa-cart-plus"></i> Adicionar
                                        </button>
                                        <button class="btn-buy-now" 
                                                onclick="event.stopPropagation(); buyNow(${p.id})" 
                                                ${p.stock === 0 ? 'disabled' : ''}
                                                title="Comprar agora">
                                            <i class="fa-solid fa-bolt"></i> Comprar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    grid.querySelectorAll('.product-card').forEach(card => {
                        card.addEventListener('click', function(e) {
                            if (!e.target.closest('button')) {
                                const productId = this.querySelector('.btn-buy-now').onclick
                                    .toString().match(/buyNow\((\d+)\)/)[1];
                                viewProduct(productId);
                            }
                        });
                    });
                } else {
                    grid.innerHTML = `
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="fa-solid fa-box-open"></i>
                            <h3>Nenhum produto encontrado</h3>
                            <p>Tente ajustar os filtros de pesquisa ou explore outras categorias</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro:', error);
                grid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <h3>Erro ao carregar produtos</h3>
                        <p>Por favor, tente novamente mais tarde</p>
                        <button class="btn-filter-reset" onclick="loadProducts()" style="margin-top: 20px;">
                            <i class="fa-solid fa-rotate-right"></i>
                            <span>Tentar Novamente</span>
                        </button>
                    </div>
                `;
            } finally {
                loader.classList.remove('active');
            }
        }
        
        function handleImageError(img, companyName) {
            const container = img.parentElement;
            const placeholder = document.createElement('div');
            placeholder.className = 'no-image-placeholder';
            placeholder.innerHTML = `
                <span>Distribuído por:</span>
                <strong>${companyName}</strong>
            `;
            img.remove();
            container.prepend(placeholder);
        }
        
        function getCategoryIcon(cat) {
            const icons = {
                'reciclavel': '♻️', 'sustentavel': '🌿', 'servico': '🛠️',
                'visiongreen': '🌱', 'ecologico': '🌍', 'outro': '📦'
            };
            return icons[cat] || '📦';
        }

        function getCategoryName(cat) {
            const names = {
                'reciclavel': 'Reciclável', 'sustentavel': 'Sustentável', 'servico': 'Serviço',
                'visiongreen': 'VisionGreen', 'ecologico': 'Ecológico', 'outro': 'Outros'
            };
            return names[cat] || cat;
        }

        function formatPrice(price) {
            return parseFloat(price).toLocaleString('pt-MZ', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function isProductNew(createdAt) {
            const created = new Date(createdAt);
            const now = new Date();
            const diffDays = Math.floor((now - created) / (1000 * 60 * 60 * 24));
            return diffDays <= 7;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;', '<': '&lt;', '>': '&gt;',
                '"': '&quot;', "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function addToCart(productId, productName, price, button) {
            button.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado!';
            button.style.background = 'var(--primary)';
            button.style.color = '#000';
            
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const existingItem = cart.find(item => item.id === productId);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: productId,
                    name: productName,
                    price: price,
                    quantity: 1,
                    addedAt: new Date().toISOString()
                });
            }
            
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartBadge();
            showToast(`✅ <strong>${productName}</strong> adicionado ao carrinho!`, 'success');
            
            setTimeout(() => {
                button.innerHTML = '<i class="fa-solid fa-cart-plus"></i> Adicionar';
                button.style.background = '';
                button.style.color = '';
            }, 2000);
        }

        function buyNow(productId) {
            showToast('⚡ Redirecionando para checkout...', 'info');
            setTimeout(() => {
                window.location.href = `checkout.php?product=${productId}&qty=1`;
            }, 500);
        }

        function toggleFavorite(productId, button) {
            const icon = button.querySelector('i');
            let favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            
            if (favorites.includes(productId)) {
                favorites = favorites.filter(id => id !== productId);
                icon.className = 'fa-regular fa-heart';
                button.classList.remove('active');
                showToast('💔 Removido dos favoritos', 'warning');
            } else {
                favorites.push(productId);
                icon.className = 'fa-solid fa-heart';
                button.classList.add('active');
                showToast('❤️ Adicionado aos favoritos', 'success');
            }
            
            localStorage.setItem('favorites', JSON.stringify(favorites));
        }

        function viewProduct(productId) {
            showToast('🔍 Carregando detalhes do produto...', 'info');
            setTimeout(() => {
                window.location.href = `product_details.php?id=${productId}`;
            }, 300);
        }

        function updateCartBadge() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            document.querySelectorAll('.cart-badge').forEach(badge => {
                if (totalItems > 0) {
                    badge.textContent = totalItems;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = message;
            
            const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
            const colors = {
                success: 'var(--primary)', error: 'var(--danger)',
                warning: 'var(--warning)', info: 'var(--accent)'
            };
            
            toast.style.cssText = `
                position: fixed; bottom: 100px; right: 20px;
                background: var(--card-bg); color: var(--text-primary);
                padding: 16px 24px; padding-left: 50px;
                border-radius: 16px; border: 2px solid ${colors[type]};
                box-shadow: 0 12px 40px rgba(0,0,0,0.4); z-index: 9999;
                animation: slideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                font-weight: 600; backdrop-filter: blur(10px);
                max-width: 400px; font-size: 14px;
            `;
            
            const iconEl = document.createElement('span');
            iconEl.textContent = icons[type];
            iconEl.style.cssText = `
                position: absolute; left: 16px; top: 50%;
                transform: translateY(-50%); font-size: 20px;
                width: 28px; height: 28px; background: ${colors[type]};
                color: #000; border-radius: 50%; display: flex;
                align-items: center; justify-content: center; font-weight: bold;
            `;
            toast.appendChild(iconEl);
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        document.querySelectorAll('.category-filter').forEach(el => {
            el.addEventListener('change', function() {
                if (this.checked) {
                    filters.categories.push(this.value);
                } else {
                    filters.categories = filters.categories.filter(c => c !== this.value);
                }
                loadProducts();
            });
        });

        document.querySelectorAll('.price-filter').forEach(el => {
            el.addEventListener('change', function() {
                filters.priceRange = this.value;
                loadProducts();
            });
        });

        document.querySelectorAll('.stock-filter').forEach(el => {
            el.addEventListener('change', function() {
                filters.inStock = this.checked;
                loadProducts();
            });
        });

        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filters.search = this.value;
                loadProducts();
            }, 500);
        });

        function resetFilters() {
            filters = { search: '', categories: [], priceRange: null, inStock: false };
            document.getElementById('searchInput').value = '';
            document.querySelectorAll('.category-filter, .price-filter, .stock-filter').forEach(el => el.checked = false);
            loadProducts();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                document.getElementById('sidebar').classList.add('collapsed');
            }

            // Verificar se pedidos já foram visualizados nesta sessão
            const pedidosVisualizados = localStorage.getItem('pedidosVisualizados');
            const agora = Date.now();
            const umDiaEmMs = 24 * 60 * 60 * 1000;
            
            // Se foi visualizado há menos de 1 dia, manter badges zerados
            if (pedidosVisualizados && (agora - parseInt(pedidosVisualizados)) < umDiaEmMs) {
                const headerBadge = document.getElementById('header-pedidos-badge');
                const mobileBadge = document.getElementById('mobile-pedidos-badge');
                const sidebarBadge = document.getElementById('sidebar-pedidos-badge');
                
                if (headerBadge) {
                    headerBadge.style.display = 'none';
                    headerBadge.textContent = '0';
                }
                if (mobileBadge) {
                    mobileBadge.style.display = 'none';
                    mobileBadge.textContent = '0';
                }
                if (sidebarBadge) {
                    sidebarBadge.textContent = '0';
                }
                console.log('✅ Badges de pedidos mantidos zerados (visualizados há menos de 1 dia)');
            }

            const urlParams = new URLSearchParams(window.location.search);
            const initialPage = urlParams.get('page') || 'home';
            
            if (initialPage !== 'home') {
                navigateTo(initialPage);
            } else {
                loadProducts();
            }

            updateCartBadge();

            if (!document.getElementById('toast-animations')) {
                const style = document.createElement('style');
                style.id = 'toast-animations';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(400px) scale(0.8); opacity: 0; }
                        to { transform: translateX(0) scale(1); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0) scale(1); opacity: 1; }
                        to { transform: translateX(400px) scale(0.8); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }

            console.log('✅ VisionGreen Marketplace -', userData.nome);
        });

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (sidebar.classList.contains('mobile-open') && 
                !sidebar.contains(event.target) && 
                !menuBtn.contains(event.target)) {
                closeMobileMenu();
            }
        });

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.classList.contains('mobile-open')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            });
        });

        observer.observe(document.getElementById('sidebar'), {
            attributes: true,
            attributeFilter: ['class']
        });
    </script>
</body>
</html>