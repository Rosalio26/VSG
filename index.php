<?php
// ==================== INICIALIZAÇÃO E SEGURANÇA ====================
require_once __DIR__ . '/registration/bootstrap.php';
require_once __DIR__ . '/registration/includes/security.php';
require_once __DIR__ . '/geo_location.php';

// Habilitar cache de saída
ob_start();

// ==================== AUTENTICAÇÃO DO USUÁRIO ====================
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_name = $user_logged_in ? ($_SESSION['auth']['nome'] ?? 'Usuário') : null;
$user_avatar = $user_logged_in ? ($_SESSION['auth']['avatar'] ?? null) : null;
$user_type = $user_logged_in ? ($_SESSION['auth']['type'] ?? 'customer') : null;

// ==================== SISTEMA DE MENSAGENS FLASH ====================
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ==================== PROTEÇÃO CSRF ====================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== CONFIGURAÇÕES DE EXIBIÇÃO ====================
define('PRODUCT_NEW_DAYS', 7);
define('PRODUCT_POPULAR_MIN_SALES', 50);

// ==================== SISTEMA DE CACHE (10 MINUTOS) ====================
$cache_key = 'vsg_homepage_v2';
$cache_file = sys_get_temp_dir() . '/' . $cache_key . '.cache';
$cache_duration = 600; // 10 minutos
$use_cache = file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_duration);

if ($use_cache) {
    // CARREGAR DO CACHE
    $cached_data = unserialize(file_get_contents($cache_file));
    $stats = $cached_data['stats'];
    $categories = $cached_data['categories'];
    $featured_products = $cached_data['featured_products'];
    $new_products = $cached_data['new_products'];
} else {
    // ==================== QUERY OTIMIZADA - ESTATÍSTICAS ====================
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM products WHERE status = 'ativo' AND deleted_at IS NULL) as total_products,
            (SELECT COUNT(*) FROM users WHERE type = 'company' AND status = 'active') as total_suppliers,
            (SELECT COUNT(DISTINCT country) FROM users WHERE country IS NOT NULL) as total_countries,
            (SELECT COALESCE(AVG(rating), 0) FROM customer_reviews) as avg_rating
    ";
    $stats_result = $mysqli->query($stats_query);

    if (!$stats_result) {
        error_log("Erro na query de estatísticas: " . $mysqli->error);
        $stats = [
            'total_products' => 0,
            'total_suppliers' => 0,
            'total_countries' => 0,
            'avg_rating' => 0
        ];
    } else {
        $stats = $stats_result->fetch_assoc();
        $stats_result->free();
    }

    // ==================== CATEGORIAS COM LIMIT E OTIMIZAÇÃO ====================
    $categories_query = "
        SELECT 
            c.id,
            c.name,
            c.icon,
            COALESCE((
                SELECT COUNT(DISTINCT p.id) 
                FROM products p
                LEFT JOIN categories sub ON p.category_id = sub.id
                WHERE (sub.id = c.id OR sub.parent_id = c.id)
                AND p.status = 'ativo' 
                AND p.deleted_at IS NULL
            ), 0) as product_count
        FROM categories c
        WHERE c.parent_id IS NULL
        AND c.status = 'ativa'
        ORDER BY product_count DESC, c.name ASC
        LIMIT 12
    ";
    $categories_result = $mysqli->query($categories_query);

    if (!$categories_result) {
        error_log("Erro na query de categorias: " . $mysqli->error);
        $categories = [];
    } else {
        $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
        $categories_result->free();
    }

    // ==================== PRODUTOS EM DESTAQUE ====================
    $products_query = "
        SELECT 
            p.id, p.nome, p.preco, p.currency, p.imagem, p.image_path1, p.stock, p.created_at,
            c.name as category_name,
            c.icon as category_icon,
            u.nome as company_name,
            COALESCE(AVG(cr.rating), 0) as avg_rating,
            COUNT(DISTINCT cr.id) as review_count,
            COALESCE(SUM(oi.quantity), 0) as total_sales,
            DATEDIFF(NOW(), p.created_at) as days_old
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN customer_reviews cr ON p.id = cr.product_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.status = 'ativo' 
        AND p.deleted_at IS NULL
        AND p.stock > 0
        GROUP BY p.id
        HAVING total_sales > 0
        ORDER BY total_sales DESC, p.created_at DESC
        LIMIT 8
    ";
    $products_result = $mysqli->query($products_query);

    if (!$products_result) {
        error_log("Erro na query de produtos em destaque: " . $mysqli->error);
        $featured_products = [];
    } else {
        $featured_products = $products_result->fetch_all(MYSQLI_ASSOC);
        $products_result->free();
    }

    $displayed_product_ids = array_column($featured_products, 'id');

    // ==================== PRODUTOS NOVOS ====================
    $new_products_days = PRODUCT_NEW_DAYS;

    $exclude_ids_clause = '';
    if (!empty($displayed_product_ids)) {
        $exclude_ids = implode(',', array_map('intval', $displayed_product_ids));
        $exclude_ids_clause = "AND p.id NOT IN ($exclude_ids)";
    }

    $new_products_query = "
        SELECT 
            p.id, p.nome, p.preco, p.currency, p.imagem, p.image_path1, p.stock, p.created_at,
            c.name as category_name,
            c.icon as category_icon,
            u.nome as company_name,
            COALESCE(AVG(cr.rating), 0) as avg_rating,
            COUNT(DISTINCT cr.id) as review_count,
            DATEDIFF(NOW(), p.created_at) as days_old
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN customer_reviews cr ON p.id = cr.product_id
        WHERE p.status = 'ativo' 
        AND p.deleted_at IS NULL
        AND p.stock > 0
        AND p.created_at >= DATE_SUB(NOW(), INTERVAL $new_products_days DAY)
        $exclude_ids_clause
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 8
    ";
    $new_products_result = $mysqli->query($new_products_query);

    if (!$new_products_result) {
        error_log("Erro na query de produtos novos: " . $mysqli->error);
        $new_products = [];
    } else {
        $new_products = $new_products_result->fetch_all(MYSQLI_ASSOC);
        $new_products_result->free();
    }

    if (empty($new_products)) {
        $recent_products_query = "
            SELECT 
                p.id, p.nome, p.preco, p.currency, p.imagem, p.image_path1, p.stock, p.created_at,
                c.name as category_name,
                c.icon as category_icon,
                u.nome as company_name,
                COALESCE(AVG(cr.rating), 0) as avg_rating,
                COUNT(DISTINCT cr.id) as review_count,
                DATEDIFF(NOW(), p.created_at) as days_old
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN customer_reviews cr ON p.id = cr.product_id
            WHERE p.status = 'ativo' 
            AND p.deleted_at IS NULL
            AND p.stock > 0
            $exclude_ids_clause
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT 8
        ";
        $recent_result = $mysqli->query($recent_products_query);
        
        if ($recent_result) {
            $new_products = $recent_result->fetch_all(MYSQLI_ASSOC);
            $recent_result->free();
        }
    }
    
    // SALVAR NO CACHE
    file_put_contents($cache_file, serialize([
        'stats' => $stats,
        'categories' => $categories,
        'featured_products' => $featured_products,
        'new_products' => $new_products
    ]));
}

// ==================== LOCALIZAÇÃO E DADOS DO USUÁRIO (NÃO CACHEAR) ====================
$user_db_location = 'Definir localização';
$user_country = '';
$cart_count = 0;

if ($user_logged_in) {
    $user_id = (int)$_SESSION['auth']['user_id'];
    
    $user_data_query = "
        SELECT 
            u.city, u.state, u.country,
            COALESCE((
                SELECT SUM(ci.quantity)
                FROM shopping_carts sc
                INNER JOIN cart_items ci ON sc.id = ci.cart_id
                WHERE sc.user_id = ? AND sc.status = 'active'
            ), 0) as cart_total
        FROM users u
        WHERE u.id = ?
    ";
    $stmt = $mysqli->prepare($user_data_query);
    
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user_data) {
            if ($user_data['city']) {
                $user_db_location = $user_data['city'];
            }
            $user_country = $user_data['country'] ?? '';
            $cart_count = (int)$user_data['cart_total'];
        }
    } else {
        error_log("Erro ao preparar query de dados do usuário: " . $mysqli->error);
    }
}

if (empty($user_country) && !empty($_SESSION['user_location']['country'])) {
    $user_country = $_SESSION['user_location']['country'];
}

// Detectar moeda do usuário
$user_currency_info = get_user_currency_info($user_country ?: 'MZ');

// ==================== FUNÇÕES AUXILIARES ====================
function getProductImageUrl($product) {
    if (!empty($product['imagem'])) {
        return 'uploads/products/' . htmlspecialchars($product['imagem'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($product['image_path1'])) {
        return 'uploads/products/' . htmlspecialchars($product['image_path1'], ENT_QUOTES, 'UTF-8');
    }
    $company = urlencode($product['company_name'] ?? 'Produto');
    return "https://ui-avatars.com/api/?name={$company}&size=400&background=00b96b&color=fff&bold=true&font-size=0.1";
}

function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function isProductNew($product) {
    $days_old = $product['days_old'] ?? 999;
    return $days_old <= PRODUCT_NEW_DAYS;
}

function isProductPopular($product) {
    $total_sales = $product['total_sales'] ?? 0;
    return $total_sales >= PRODUCT_POPULAR_MIN_SALES;
}

function displayPrice($product, $user_country = null) {
    $converted = format_product_price($product, $user_country);
    return $converted;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="VSG Marketplace - Conectamos você ao melhor da sustentabilidade com produtos eco-friendly verificados">
    <meta name="keywords" content="marketplace, sustentabilidade, produtos eco-friendly, green, sustentável">
    <meta name="author" content="VSG Marketplace">
    <meta name="theme-color" content="#00b96b">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= escapeHtml($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:title" content="VSG Marketplace - Produtos Sustentáveis">
    <meta property="og:description" content="Conectamos você ao melhor da sustentabilidade com mais de <?= number_format($stats['total_products']) ?> produtos eco-friendly">
    
    <title>VSG Marketplace - Produtos Sustentáveis</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://ui-avatars.com">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
    
    <link rel="stylesheet" href="assets/style/footer.css">
    <link rel="stylesheet" href="assets/style/index_start.css">
    <link rel="stylesheet" href="assets/style/index_start_search_product.css">
    <link rel="stylesheet" href="assets/style/currency_styles.css">
</head>

<body>
    <div id="pageLoader" class="page-loader">
        <div class="spinner"></div>
    </div>

    <?php if ($flash_message): ?>
    <div class="flash-message flash-<?= escapeHtml($flash_type) ?>" id="flashMessage">
        <i class="fa-solid fa-<?= $flash_type === 'success' ? 'check-circle' : ($flash_type === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
        <span><?= escapeHtml($flash_message) ?></span>
        <button onclick="this.parentElement.remove()" class="flash-close">×</button>
    </div>
    <?php endif; ?>

    <div class="top-strip">
        <div class="container">
            <div class="top-strip-content">
                <div class="top-left-info">
                    <a href="#" class="top-link" id="locationTrigger">
                        <i class="fa-solid fa-location-dot"></i>
                        País <span id="locationDisplay"><?= escapeHtml(!empty($user_country) ? $user_country : 'Selecionar localização') ?></span>
                    </a>
                    <span class="divider"></span>
                    <span><i class="fa-solid fa-coins"></i> Moeda</span>
                    <span id="currentCurrencyDisplay"><?= escapeHtml($user_currency_info['currency']) ?></span>
                </div>
                <ul class="top-right-nav">
                    <?php if ($user_logged_in && $user_type === 'company'): ?>
                        <li><a href="pages/person/index.php">Meu Painel</a></li>
                        <li><span class="divider"></span></li>
                    <?php endif; ?>
                    <li><a href="#">Central de Ajuda</a></li>
                    <li><span class="divider"></span></li>
                    <li><a href="#">Rastrear Pedido</a></li>
                </ul>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <div class="container">
            <div class="nav-content">
                <ul class="nav-menu">
                    <?php include 'includes/header-nav.html'; ?>
                    <?php if (!$user_logged_in): ?>
                        <li class="nav-item" style="margin-left: auto;">
                            <a href="registration/register/painel_cadastro.php" class="nav-link">
                                <i class="fa-solid fa-building"></i>
                                Vender na VSG
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <header class="main-header">
        <div class="container">
            <div class="header-main">
                <a href="index.php" class="logo-container">
                    <div class="logo-text">
                        VSG<span class="logo-accent">•</span> <span class="logo-name-market">MARKETPLACE</span>
                    </div>
                </a>

                <div class="search-container">
                    <form id="searchForm" class="search-form" role="search" onsubmit="return false;">
                        <input type="text" id="searchInput" name="search" placeholder="Buscar produtos sustentáveis..." class="search-input" autocomplete="off">
                        <button type="button" id="searchBtn" class="search-btn"><i class="fa-solid fa-search"></i></button>
                        <button type="button" id="clearSearchBtn" class="clear-search-btn" style="display: none;"><i class="fa-solid fa-times"></i></button>
                    </form>
                    <div id="searchResults" class="search-results-dropdown" style="display: none;"></div>
                </div>

                <ul class="nav-menu" id="sec-main-header">
                    <?php include 'includes/header-nav.html'; ?>
                </ul>

                <a href="cart.php" class="cart-link">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>

                <?php if ($user_logged_in): ?>
                    <a href="pages/person/index.php" class="account-action">
                        <?php if ($user_avatar): ?>
                            <img src="<?= escapeHtml($user_avatar) ?>" alt="Avatar" class="user-avatar">
                        <?php else: ?>
                            <i class="fa-solid fa-user-circle"></i>
                        <?php endif; ?>
                        <span><?= escapeHtml($user_name) ?></span>
                    </a>
                <?php else: ?>
                    <a href="registration/login/login.php" class="account-action">
                        <i class="fa-solid fa-user-circle"></i>
                        <span>Entrar</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="hero-banner">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <div class="hero-badge">
                        <i class="fa-solid fa-bolt"></i>
                        Marketplace Sustentável
                    </div>
                    <h1 class="hero-title">
                        Conectamos você ao<br>
                        melhor da sustentabilidade
                    </h1>
                    <p class="hero-subtitle">
                        Mais de <?= number_format($stats['total_products']) ?> produtos eco-friendly de fornecedores verificados. 
                        Compras seguras, entrega garantida e impacto positivo.
                    </p>

                    <div class="hero-features">
                        <div class="hero-feature">
                            <i class="fa-solid fa-check-circle"></i>
                            Produtos Verificados
                        </div>
                        <div class="hero-feature">
                            <i class="fa-solid fa-check-circle"></i>
                            Entrega Rastreada
                        </div>
                        <div class="hero-feature">
                            <i class="fa-solid fa-check-circle"></i>
                            Pagamento Seguro
                        </div>
                    </div>

                    <div class="hero-cta-group">
                        <a href="marketplace.php" class="btn-primary">
                            Explorar Produtos
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <?php if (!$user_logged_in): ?>
                        <a href="registration/register/painel_cadastro.php" class="btn-secondary">
                            <i class="fa-solid fa-register"></i>
                            Cadastrar - Se
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="content-img">
                    <img src="assets/img/vsg-bg/cole-frp/bg-vsg-index-rbm.png" alt="VSG Marketplace">
                </div>
            </div>
        </div>
    </section>

    <section class="category-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fa-solid fa-compass"></i> 
                    Explore as categorias <span style="color: #059669; font-weight: 700;">Green</span>
                </h2>
            </div>
            <div class="category-carousel-wrapper">
                <button class="carousel-btn carousel-btn-left" id="scrollLeft"><i class="fa-solid fa-chevron-left"></i></button>
                
                <div class="category-grid" id="categoryGrid" role="list">
                    <?php foreach ($categories as $category): ?>
                    <a href="marketplace.php?category=<?= $category['id'] ?>" class="category-card">
                        <div class="category-icon">
                            <i class="fa-solid fa-<?= escapeHtml($category['icon'] ?: 'box') ?>"></i>
                        </div>
                        <div class="category-name"><?= escapeHtml($category['name']) ?></div>
                        <div class="category-count"><?= number_format($category['product_count']) ?> produtos</div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <button class="carousel-btn carousel-btn-right" id="scrollRight"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
    </section>

    <section class="trust-bar">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fa-solid fa-lock"></i>
                    VSG Safe & Suporte
                </h2>
            </div>
            <div class="trust-items">
                <div class="trust-item">
                    <div class="trust-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="trust-text">
                        <h4>Compra Protegida</h4>
                        <p>Seu dinheiro seguro até receber</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="fa-solid fa-truck"></i></div>
                    <div class="trust-text">
                        <h4>Entrega Rastreada</h4>
                        <p>Acompanhe em tempo real</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="fa-solid fa-certificate"></i></div>
                    <div class="trust-text">
                        <h4>Produtos Certificados</h4>
                        <p>ISO, FSC, Fair Trade</p>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon"><i class="fa-solid fa-headset"></i></div>
                    <div class="trust-text">
                        <h4>Suporte 24/7</h4>
                        <p>Sempre disponível</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fa-solid fa-trophy"></i>
                    Produtos Mais Vendidos
                </h2>
                <a href="marketplace.php?sort=bestsellers" class="section-link">
                    Ver mais produtos
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if (!empty($featured_products)): ?>
            <div class="products-grid">
                <?php foreach ($featured_products as $product): 
                    $avg_rating = $product['avg_rating'] ?? 0;
                    $review_count = $product['review_count'] ?? 0;
                    $rating = round($avg_rating);
                    $isNew = isProductNew($product);
                    $isPopular = isProductPopular($product);
                    $isLowStock = $product['stock'] > 0 && $product['stock'] <= 10;
                    $productImage = getProductImageUrl($product);
                    $priceConverted = displayPrice($product, $user_country);
                ?>
                    <a href="marketplace.php?product=<?= $product['id'] ?>" class="product-card">
                        <div class="product-image-container">
                            <img src="<?= $productImage ?>" 
                                alt="<?= escapeHtml($product['nome']) ?>" 
                                class="product-image"
                                loading="lazy"
                                onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($product['nome']) ?>&size=400&background=00b96b&color=fff&font-size=0.1'">
                            
                            <?php if ($isNew): ?>
                                <span class="product-badge new">Novo</span>
                            <?php elseif ($isPopular): ?>
                                <span class="product-badge">Popular</span>
                            <?php endif; ?>

                            <div class="product-actions">
                                <button class="action-btn" onclick="event.preventDefault();"><i class="fa-regular fa-heart"></i></button>
                                <button class="action-btn" onclick="event.preventDefault();"><i class="fa-regular fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="product-info">
                            <div class="product-category">
                                <i class="fa-solid fa-<?= escapeHtml($product['category_icon'] ?: 'box') ?>"></i>
                                <span><?= escapeHtml($product['category_name'] ?: 'Geral') ?></span>
                            </div>

                            <h3 class="product-name"><?= escapeHtml($product['nome']) ?></h3>

                            <div class="product-supplier">
                                <i class="fa-solid fa-building"></i>
                                <?= escapeHtml($product['company_name'] ?: 'Fornecedor') ?>
                            </div>

                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-<?= $i <= $rating ? 'solid' : 'regular' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text"><?= $avg_rating > 0 ? number_format($avg_rating, 1) : '0' ?></span>
                                <span class="rating-count">(<?= $review_count ?>)</span>
                            </div>

                            <div class="product-footer">
                                <div class="product-price">
                                    <span class="price-currency"><?= escapeHtml($priceConverted['symbol']) ?></span>
                                    <span class="price-value" data-price-mzn="<?= $product['preco'] ?>">
                                        <?= number_format($priceConverted['amount'], 2, ',', '.') ?>
                                    </span>
                                </div>
                                <span class="stock-badge <?= $isLowStock ? 'low' : 'high' ?>">
                                    <?= $isLowStock ? "Últimas {$product['stock']}" : "{$product['stock']} em estoque" ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open"></i>
                <h3>Nenhum produto disponível</h3>
                <p>Volte em breve!</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($new_products)): ?>
    <section class="new-products-modern">
        <div class="container">
            <div class="modern-section-header">
                <div class="header-content">
                    <span class="header-label">Acabaram de Chegar</span>
                    <h2 class="modern-title">
                        Produtos <span class="gradient-text">Recentes</span>
                    </h2>
                    <p class="header-description">
                        Descubra as últimas adições ao nosso marketplace sustentável
                    </p>
                </div>
            </div>

            <div class="modern-layout">
                <div class="featured-block">
                    <?php for ($i = 0; $i < 2 && $i < count($new_products); $i++): 
                        $product = $new_products[$i];
                        $avg_rating = $product['avg_rating'] ?? 0;
                        $review_count = $product['review_count'] ?? 0;
                        $isNew = isProductNew($product);
                        $productImage = getProductImageUrl($product);
                        $priceConverted = displayPrice($product, $user_country);
                    ?>
                        <a href="marketplace.php?product=<?= $product['id'] ?>" class="featured-card">
                            <div class="featured-image">
                                <img src="pages/<?= $productImage ?>" alt="<?= escapeHtml($product['nome']) ?>" loading="lazy">
                                <?php if ($isNew): ?>
                                    <span class="product-badge new">Novo</span>
                                <?php endif; ?>
                                <div class="featured-overlay">
                                    <button class="quick-action" onclick="event.preventDefault();"><i class="fa-regular fa-heart"></i></button>
                                </div>
                            </div>
                            <div class="featured-info">
                                <div class="featured-category">
                                    <i class="fa-solid fa-<?= escapeHtml($product['category_icon'] ?: 'box') ?>"></i>
                                    <?= escapeHtml($product['category_name'] ?: 'Geral') ?>
                                </div>
                                <h3 class="featured-name"><?= escapeHtml($product['nome']) ?></h3>
                                <div class="featured-footer">
                                    <div class="featured-price">
                                        <span class="currency"><?= escapeHtml($priceConverted['symbol']) ?></span>
                                        <span class="price" data-price-mzn="<?= $product['preco'] ?>">
                                            <?= number_format($priceConverted['amount'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <div class="featured-rating">
                                        <i class="fa-solid fa-star"></i>
                                        <?= $avg_rating > 0 ? number_format($avg_rating, 1) : '0' ?>
                                        <span>(<?= $review_count ?>)</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endfor; ?>
                </div>

                <div class="flex-products">
                    <?php 
                    $maxFlexProducts = min(6, count($new_products));
                    for ($i = 2; $i < $maxFlexProducts; $i++): 
                        $product = $new_products[$i];
                        $avg_rating = $product['avg_rating'] ?? 0;
                        $isNew = isProductNew($product);
                        $productImage = getProductImageUrl($product);
                        $priceConverted = displayPrice($product, $user_country);
                    ?>
                        <a href="marketplace.php?product=<?= $product['id'] ?>" class="flex-card">
                            <div class="flex-image">
                                <img src="pages/<?= $productImage ?>" alt="<?= escapeHtml($product['nome']) ?>" loading="lazy">
                                <?php if ($isNew): ?>
                                    <span class="product-badge new">Novo</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-info">
                                <div class="flex-category">
                                    <i class="fa-solid fa-<?= escapeHtml($product['category_icon'] ?: 'box') ?>"></i>
                                    <span><?= escapeHtml($product['category_name'] ?: 'Geral') ?></span>
                                </div>
                                <h4 class="flex-name"><?= escapeHtml($product['nome']) ?></h4>
                                <div class="flex-meta">
                                    <div class="flex-price">
                                        <span class="currency"><?= escapeHtml($priceConverted['symbol']) ?></span>
                                        <span class="price" data-price-mzn="<?= $product['preco'] ?>">
                                            <?= number_format($priceConverted['amount'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <div class="flex-rating">
                                        <i class="fa-solid fa-star"></i>
                                        <?= $avg_rating > 0 ? number_format($avg_rating, 1) : '0' ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="hero-stats-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title hero-title">
                    <i class="fa-solid fa-trophy"></i>
                    Impacto Global 
                </h2>
            </div>
            <div class="hero-stats">
                <div class="stat-card">
                    <i class="fa-stat-card fa-solid fa-boxes-stacked stat-icon"></i>
                    <div class="stat-number"><?= number_format($stats['total_products']) ?></div>
                    <div class="stat-label">Produtos Listados</div>
                </div>
                <div class="stat-card">
                    <i class="fa-stat-card fa-solid fa-users stat-icon"></i>
                    <div class="stat-number"><?= number_format($stats['total_suppliers']) ?></div>
                    <div class="stat-label">Fornecedores Ativos</div>
                </div>
                <div class="stat-card">
                    <i class="fa-stat-card fa-solid fa-globe-africa stat-icon"></i>
                    <div class="stat-number"><?= $stats['total_countries'] ?></div>
                    <div class="stat-label">Países Atendidos</div>
                </div>
                <div class="stat-card">
                    <i class="fa-stat-card fa-solid fa-star stat-icon"></i>
                    <div class="stat-number"><?= $stats['avg_rating'] > 0 ? number_format($stats['avg_rating'], 1) : '0' ?></div>
                    <div class="stat-label">Avaliação Média</div>
                </div>
            </div>
        </div>
    </section>

    <div id="offlineModal" class="offline-modal" role="dialog">
        <div class="offline-content">
            <i class="fa-solid fa-wifi-slash"></i>
            <h3>Sem Conexão</h3>
            <p>Verifique sua rede para continuar navegando.</p>
            <button onclick="location.reload()">Tentar Novamente</button>
        </div>
    </div>

    <button id="backToTop" class="back-to-top"><i class="fa-solid fa-arrow-up"></i></button>
    <div id="searchOverlay" class="search-overlay"></div>

    <?php include 'includes/footer.html'; ?>

    <script src="assets/scripts/currency_exchange.js" defer></script>
    <script src="assets/scripts/main_index.js" defer></script>
    <script src="assets/scripts/redirect_handler.js"></script>
    
</body>
</html>
<?php ob_end_flush(); ?>