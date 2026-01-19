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

// Categorias conforme nova lógica
$categoryLabels = [
    'reciclavel' => ['icon' => '♻️', 'label' => 'Reciclável'],
    'sustentavel' => ['icon' => '🌿', 'label' => 'Sustentável'],
    'servico' => ['icon' => '🛠️', 'label' => 'Serviços'],
    'visiongreen' => ['icon' => '🌱', 'label' => 'VisionGreen'],
    'ecologico' => ['icon' => '🌍', 'label' => 'Ecológico'],
    'outro' => ['icon' => '📦', 'label' => 'Outros']
];

// Faixas de preço
$priceRanges = [
    ['min' => 0, 'max' => 1000, 'label' => 'Até 1.000 MZN'],
    ['min' => 1000, 'max' => 5000, 'label' => '1.000 - 5.000 MZN'],
    ['min' => 5000, 'max' => 10000, 'label' => '5.000 - 10.000 MZN'],
    ['min' => 10000, 'max' => 999999, 'label' => 'Acima de 10.000 MZN']
];

// Estatísticas
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen Marketplace | <?= htmlspecialchars($displayName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="loading-bar" id="loadingBar"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="collapse-btn" onclick="toggleSidebar()" title="Colapsar Sidebar">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
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
                    <span class="stat-mini-value"><?= $stats['pedidos_em_andamento'] ?></span>
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

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Buscar produtos ecológicos..." autocomplete="off">
                </div>

                <div class="header-actions">
                    <button class="icon-btn" onclick="navigateTo('meus_pedidos')" title="Meus Pedidos">
                        <i class="fa-solid fa-shopping-bag"></i>
                        <?php if($stats['pedidos_em_andamento'] > 0): ?>
                            <span class="badge"><?= $stats['pedidos_em_andamento'] ?></span>
                        <?php endif; ?>
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
            <!-- Conteúdo Home (Produtos) -->
            <div id="content-home" class="dynamic-content active">
                <div id="productsGrid" class="products-grid">
                    <div class="empty-state">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <h3>Carregando produtos...</h3>
                        <p>Aguarde um momento</p>
                    </div>
                </div>
            </div>

            <!-- Conteúdo Meus Pedidos -->
            <div id="content-meus_pedidos" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando pedidos...</h3>
                </div>
            </div>

            <!-- Conteúdo Notificações -->
            <div id="content-notificacoes" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando notificações...</h3>
                </div>
            </div>

            <!-- Conteúdo Perfil -->
            <div id="content-perfil" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando perfil...</h3>
                </div>
            </div>

            <!-- Conteúdo Configurações -->
            <div id="content-configuracoes" class="dynamic-content">
                <div class="content-loading active">
                    <div class="spinner"></div>
                    <h3 style="color: var(--text-primary);">Carregando configurações...</h3>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav">
        <div class="mobile-nav-grid">
            <button class="mobile-nav-item active" onclick="navigateTo('home')" data-page="home">
                <i class="fa-solid fa-house"></i>
                <span class="mobile-nav-label">Início</span>
            </button>

            <button class="mobile-nav-item" onclick="navigateTo('meus_pedidos')" data-page="meus_pedidos">
                <i class="fa-solid fa-shopping-bag"></i>
                <span class="mobile-nav-label">Pedidos</span>
                <?php if($stats['pedidos_em_andamento'] > 0): ?>
                    <span class="mobile-nav-badge"><?= $stats['pedidos_em_andamento'] ?></span>
                <?php endif; ?>
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

    let filters = {
        search: '',
        categories: [],
        priceRange: null,
        inStock: false
    };

    let currentPage = 'home';
    const loadedPages = new Set(['home']);

    // Toggle Sidebar
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', document.getElementById('sidebar').classList.contains('collapsed'));
    }

    // Mobile Menu
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

    // Sistema de Navegação Dinâmica
    async function navigateTo(page) {
        if (currentPage === page && page === 'home') return;

        closeMobileMenu();
        
        // Atualizar estado ativo nos botões mobile
        document.querySelectorAll('.mobile-nav-item').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.page === page) {
                btn.classList.add('active');
            }
        });

        // Esconder conteúdo atual
        document.querySelectorAll('.dynamic-content').forEach(content => {
            content.classList.remove('active');
        });

        // Mostrar novo conteúdo
        const contentDiv = document.getElementById(`content-${page}`);
        if (!contentDiv) return;

        contentDiv.classList.add('active');
        currentPage = page;

        // Atualizar URL sem recarregar
        window.history.pushState({ page }, '', `?page=${page}`);

        // Carregar conteúdo se necessário
        if (!loadedPages.has(page)) {
            await loadPageContent(page);
            loadedPages.add(page);
        }

        // Scroll para o topo
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Carregar conteúdo de páginas
    async function loadPageContent(page) {
        const contentDiv = document.getElementById(`content-${page}`);
        const loader = contentDiv.querySelector('.content-loading');
        
        if (loader) loader.classList.add('active');

        try {
            const response = await fetch(`pages/${page}.php`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            
            // Remover loader e inserir conteúdo
            if (loader) loader.remove();
            contentDiv.innerHTML = html;

            // Executar scripts da página carregada
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

    // Gerenciar histórico do navegador
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.page) {
            navigateTo(event.state.page);
        } else {
            navigateTo('home');
        }
    });

    // Carregar produtos
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
                grid.innerHTML = data.products.map(p => {
                    const imageSrc = p.imagem ? 
                        `../${p.imagem}` : '';
                    
                    const stockBadge = p.stock <= 0 ? 
                        '<span class="product-badge" style="background: var(--danger);">Esgotado</span>' :
                        (p.stock <= p.stock_minimo ? 
                            '<span class="product-badge">Últimas Unidades</span>' : '');

                    return `
                        <a href="javascript:void(0)" onclick="viewProduct(${p.id})" class="product-card">
                            <div class="product-image">
                                ${imageSrc ? 
                                    `<img src="${imageSrc}" alt="${p.nome}" loading="lazy">` :
                                    '<i class="fa-solid fa-leaf"></i>'
                                }
                                ${stockBadge}
                            </div>
                            <div class="product-info">
                                <div class="product-category">${getCategoryName(p.categoria)}</div>
                                <div class="product-name">${p.nome}</div>
                                <div class="product-footer">
                                    <div class="product-price">${parseFloat(p.preco).toFixed(2)} MZN</div>
                                    <div class="product-stock ${p.stock === 0 ? 'out' : (p.stock <= p.stock_minimo ? 'low' : '')}">
                                        ${p.stock === 0 ? 'Esgotado' : 
                                          p.stock <= p.stock_minimo ? 'Estoque Baixo' : 
                                          `${p.stock} un`}
                                    </div>
                                </div>
                            </div>
                        </a>
                    `;
                }).join('');
            } else {
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>Nenhum produto encontrado</h3>
                        <p>Tente ajustar os filtros de pesquisa</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Erro:', error);
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <h3>Erro ao carregar produtos</h3>
                    <p>Tente novamente mais tarde</p>
                </div>
            `;
        } finally {
            loader.classList.remove('active');
        }
    }

    function getCategoryName(cat) {
        const names = {
            'reciclavel': '♻️ Reciclável',
            'sustentavel': '🌿 Sustentável',
            'servico': '🛠️ Serviço',
            'visiongreen': '🌱 VisionGreen',
            'ecologico': '🌍 Ecológico',
            'outro': '📦 Outros'
        };
        return names[cat] || cat;
    }

    // Ver detalhes do produto
    function viewProduct(productId) {
        // Carregar modal ou página de detalhes
        window.location.href = `product_details.php?id=${productId}`;
    }

    // Event Listeners para Filtros
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

    // Busca com debounce
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filters.search = this.value;
            loadProducts();
        }, 500);
    });

    function resetFilters() {
        filters = {
            search: '',
            categories: [],
            priceRange: null,
            inStock: false
        };
        
        document.getElementById('searchInput').value = '';
        document.querySelectorAll('.category-filter, .price-filter, .stock-filter').forEach(el => el.checked = false);
        loadProducts();
    }

    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        // Restaurar estado do sidebar
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            document.getElementById('sidebar').classList.add('collapsed');
        }

        // Verificar página inicial via URL
        const urlParams = new URLSearchParams(window.location.search);
        const initialPage = urlParams.get('page') || 'home';
        
        if (initialPage !== 'home') {
            navigateTo(initialPage);
        } else {
            loadProducts();
        }

        console.log('✅ VisionGreen Marketplace -', userData.nome);
    });

    // Fechar menu mobile ao clicar fora
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (sidebar.classList.contains('mobile-open') && 
            !sidebar.contains(event.target) && 
            !menuBtn.contains(event.target)) {
            closeMobileMenu();
        }
    });

    // Prevenir scroll do body quando sidebar mobile está aberto
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