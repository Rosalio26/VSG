<?php
// ==================== INICIALIZAÇÃO E SEGURANÇA ====================
require_once __DIR__ . '/registration/bootstrap.php';
require_once __DIR__ . '/registration/includes/security.php';
require_once __DIR__ . '/registration/includes/db.php';
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
// Configurar quantos dias um produto é considerado "novo"
define('PRODUCT_NEW_DAYS', 7); // Produtos com menos de 7 dias são "novos"

// Configurar mínimo de vendas para ser "popular"
define('PRODUCT_POPULAR_MIN_SALES', 50);

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

// ==================== LOCALIZAÇÃO E DADOS DO USUÁRIO ====================
$user_db_location = 'Definir localização';
$user_country = '';
$cart_count = 0;

if ($user_logged_in) {
    $user_id = (int)$_SESSION['auth']['user_id'];
    
    // Query combinada para localização e carrinho
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

// Se não tem country do usuário, tentar pegar da geolocalização
if (empty($user_country) && !empty($_SESSION['user_location']['country'])) {
    $user_country = $_SESSION['user_location']['country'];
}

// ==================== PRODUTOS EM DESTAQUE (MAIS VENDIDOS) ====================
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

// Array para rastrear IDs de produtos já exibidos
$displayed_product_ids = array_column($featured_products, 'id');

// ==================== PRODUTOS NOVOS (EXCLUINDO OS JÁ EXIBIDOS) ====================
$new_products_days = PRODUCT_NEW_DAYS;

// Construir a cláusula WHERE para excluir produtos já exibidos
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

// ==================== SE NÃO HOUVER PRODUTOS NOVOS, BUSCAR RECENTES (SEM DUPLICAR) ====================
if (empty($new_products)) {
    // Buscar produtos mais recentes que não estejam nos mais vendidos
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

function formatPrice($price) {
    return number_format($price, 2, ',', '.');
}

function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica se um produto é considerado "novo" (menos de X dias)
 */
function isProductNew($product) {
    $days_old = $product['days_old'] ?? 999;
    return $days_old <= PRODUCT_NEW_DAYS;
}

/**
 * Verifica se um produto é considerado "popular" (muitas vendas)
 */
function isProductPopular($product) {
    $total_sales = $product['total_sales'] ?? 0;
    return $total_sales >= PRODUCT_POPULAR_MIN_SALES;
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
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= escapeHtml($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:title" content="VSG Marketplace - Produtos Sustentáveis">
    <meta property="og:description" content="Conectamos você ao melhor da sustentabilidade com mais de <?= number_format($stats['total_products']) ?> produtos eco-friendly">
    <meta property="og:image" content="<?= escapeHtml($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']) ?>/assets/img/og-image.jpg">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= escapeHtml($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta name="twitter:title" content="VSG Marketplace - Produtos Sustentáveis">
    <meta name="twitter:description" content="Conectamos você ao melhor da sustentabilidade">
    <meta name="twitter:image" content="<?= escapeHtml($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']) ?>/assets/img/twitter-card.jpg">
    
    <title>VSG Marketplace - Produtos Sustentáveis</title>

    <!-- Preconnect para performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- DNS Prefetch -->
    <link rel="dns-prefetch" href="https://ui-avatars.com">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Favicon -->
    <link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
    <link rel="apple-touch-icon" href="sources/img/logo_small_gr.png">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/style/footer.css">
    <link rel="stylesheet" href="assets/style/index_start.css">
    
    <!-- Structured Data / Schema.org -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "VSG Marketplace",
        "url": "<?= escapeHtml($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']) ?>",
        "description": "Marketplace de produtos sustentáveis e eco-friendly",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?= escapeHtml($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']) ?>/marketplace.php?search={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
</head>

<body>
    <!-- Loading Spinner -->
    <div id="pageLoader" class="page-loader">
        <div class="spinner"></div>
    </div>

    <!-- Flash Message -->
    <?php if ($flash_message): ?>
    <div class="flash-message flash-<?= escapeHtml($flash_type) ?>" id="flashMessage">
        <i class="fa-solid fa-<?= $flash_type === 'success' ? 'check-circle' : ($flash_type === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
        <span><?= escapeHtml($flash_message) ?></span>
        <button onclick="this.parentElement.remove()" class="flash-close" aria-label="Fechar mensagem">×</button>
    </div>
    <?php endif; ?>

    <!-- Top Strip -->
    <div class="top-strip">
        <div class="container">
            <div class="top-strip-content">
                <div class="top-left-info">
                    <a href="#" class="top-link" id="locationTrigger">
                        <i class="fa-solid fa-location-dot"></i>
                        País <span id="locationDisplay"><?= escapeHtml(!empty($user_country) ? $user_country : 'Selecionar localização') ?></span>
                    </a>
                    <span class="divider"></span>
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

    <!-- Navigation -->
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

    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-main">
                <a href="index.php" class="logo-container" aria-label="VSG Marketplace - Página Inicial">
                    <div class="logo-text">
                        VSG<span class="logo-accent">•</span> <span class="logo-name-market">MARKETPLACE</span>
                    </div>
                </a>

                <!-- Barra de Busca -->
                <div class="search-container">
                    <form action="marketplace.php" method="GET" class="search-form" role="search">
                        <input type="text" 
                               name="search" 
                               placeholder="Buscar produtos sustentáveis..." 
                               class="search-input"
                               aria-label="Buscar produtos">
                        <button type="submit" class="search-btn" aria-label="Buscar">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </form>
                </div>

                <ul class="nav-menu" id="sec-main-header">
                    <?php include 'includes/header-nav.html'; ?>
                </ul>

                <!-- Carrinho -->
                <a href="cart.php" class="cart-link" aria-label="Carrinho de compras">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>

                <!-- Conta do Usuário -->
                <?php if ($user_logged_in): ?>
                    <a href="pages/person/index.php" class="account-action">
                        <?php if ($user_avatar): ?>
                            <img src="<?= escapeHtml($user_avatar) ?>" alt="Avatar de <?= escapeHtml($user_name) ?>" class="user-avatar">
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

    <!-- Hero Banner -->
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
                            <i class="fa-solid fa-building"></i>
                            Sou Fornecedor
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="content-img">
                    <img src="assets/img/vsg-bg/cole-frp/bg-vsg-index-rbm.png" alt="VSG Marketplace - Produtos Sustentáveis">
                </div>
            </div>
        </div>
    </section>

    <!-- Category Grid -->
    <section class="category-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fa-solid fa-compass"></i> 
                    Explore as categorias <span style="color: #059669; font-weight: 700;">Green</span>
                </h2>
            </div>
            <div class="category-carousel-wrapper">
                <button class="carousel-btn carousel-btn-left" id="scrollLeft" aria-label="Rolar categorias para esquerda">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                
                <div class="category-grid" id="categoryGrid" role="list">
                    <?php foreach ($categories as $category): ?>
                    <a href="marketplace.php?category=<?= $category['id'] ?>" class="category-card" role="listitem">
                        <div class="category-icon">
                            <i class="fa-solid fa-<?= escapeHtml($category['icon'] ?: 'box') ?>"></i>
                        </div>
                        <div class="category-name"><?= escapeHtml($category['name']) ?></div>
                        <div class="category-count"><?= number_format($category['product_count']) ?> produtos</div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <button class="carousel-btn carousel-btn-right" id="scrollRight" aria-label="Rolar categorias para direita">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Trust Bar -->
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
                    <div class="trust-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="trust-text">
                        <h4>Compra Protegida</h4>
                        <p>Seu dinheiro seguro até receber</p>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                    <div class="trust-text">
                        <h4>Entrega Rastreada</h4>
                        <p>Acompanhe em tempo real</p>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="fa-solid fa-certificate"></i>
                    </div>
                    <div class="trust-text">
                        <h4>Produtos Certificados</h4>
                        <p>ISO, FSC, Fair Trade</p>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <div class="trust-text">
                        <h4>Suporte 24/7</h4>
                        <p>Sempre disponível</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products (Mais Vendidos) -->
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
                                <button class="action-btn" onclick="event.preventDefault();" aria-label="Adicionar aos favoritos">
                                    <i class="fa-regular fa-heart"></i>
                                </button>
                                <button class="action-btn" onclick="event.preventDefault();" aria-label="Visualização rápida">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
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
                                <div class="stars" aria-label="Avaliação: <?= number_format($avg_rating, 1) ?> de 5 estrelas">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-<?= $i <= $rating ? 'solid' : 'regular' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">
                                    <?= $avg_rating > 0 ? number_format($avg_rating, 1) : '0' ?>
                                </span>
                                <span class="rating-count">(<?= $review_count ?>)</span>
                            </div>

                            <div class="product-footer">
                                <div class="product-price">
                                    <span class="price-currency"><?= strtoupper($product['currency']) ?></span>
                                    <span class="price-value"><?= formatPrice($product['preco']) ?></span>
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
                <h3>Nenhum produto disponível no momento</h3>
                <p>Volte em breve para conferir novos produtos sustentáveis!</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- New Products Section (Produtos Recentes/Novos - SEM DUPLICAR) -->
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
                <!-- Featured Products Block -->
                <div class="featured-block">
                    <?php for ($i = 0; $i < 2 && $i < count($new_products); $i++): 
                        $product = $new_products[$i];
                        $avg_rating = $product['avg_rating'] ?? 0;
                        $review_count = $product['review_count'] ?? 0;
                        $isNew = isProductNew($product);
                        $productImage = getProductImageUrl($product);
                    ?>
                        <a href="marketplace.php?product=<?= $product['id'] ?>" class="featured-card">
                            <div class="featured-image">
                                <img src="<?= $productImage ?>" 
                                     alt="<?= escapeHtml($product['nome']) ?>"
                                     loading="lazy"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($product['nome']) ?>&size=600&background=00b96b&color=fff&font-size=0.1'">
                                <?php if ($isNew): ?>
                                    <span class="product-badge new">Novo</span>
                                <?php endif; ?>
                                <div class="featured-overlay">
                                    <button class="quick-action" onclick="event.preventDefault();" aria-label="Adicionar aos favoritos">
                                        <i class="fa-regular fa-heart"></i>
                                    </button>
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
                                        <span class="currency"><?= strtoupper($product['currency']) ?></span>
                                        <span class="price"><?= formatPrice($product['preco']) ?></span>
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

                <!-- Flex Products -->
                <div class="flex-products">
                    <?php 
                    $maxFlexProducts = min(6, count($new_products));
                    for ($i = 2; $i < $maxFlexProducts; $i++): 
                        $product = $new_products[$i];
                        $avg_rating = $product['avg_rating'] ?? 0;
                        $review_count = $product['review_count'] ?? 0;
                        $isNew = isProductNew($product);
                        $productImage = getProductImageUrl($product);
                    ?>
                        <a href="marketplace.php?product=<?= $product['id'] ?>" class="flex-card">
                            <div class="flex-image">
                                <img src="<?= $productImage ?>" 
                                     alt="<?= escapeHtml($product['nome']) ?>"
                                     loading="lazy"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($product['nome']) ?>&size=400&background=00b96b&color=fff&font-size=0.1'">
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
                                        <span class="currency"><?= strtoupper($product['currency']) ?></span>
                                        <span class="price"><?= formatPrice($product['preco']) ?></span>
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

    <!-- Hero Stats Section -->
    <section class="hero-stats-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title hero-title">
                    <i class="fa-solid fa-globe"></i>
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

    <!-- Modal Offline -->
    <div id="offlineModal" class="offline-modal" role="dialog" aria-labelledby="offlineTitle" aria-modal="true">
        <div class="offline-content">
            <i class="fa-solid fa-wifi-slash"></i>
            <h3 id="offlineTitle">Sem Conexão com a Internet</h3>
            <p>Parece que você está offline. Verifique sua rede para continuar navegando no Vision Green.</p>
            <button onclick="location.reload()">Tentar Novamente</button>
        </div>
    </div>

    <!-- Botão Voltar ao Topo -->
    <button id="backToTop" class="back-to-top" aria-label="Voltar ao topo">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <?php include 'includes/footer.html'; ?>

    <!-- JavaScript Otimizado -->
    <script>
        // ==================== INICIALIZAÇÃO ====================
        (function() {
            'use strict';
            
            // Cache de elementos DOM
            const elements = {
                navBar: document.querySelector('.nav-bar'),
                secHeader: document.getElementById('sec-main-header'),
                categoryGrid: document.getElementById('categoryGrid'),
                scrollLeft: document.getElementById('scrollLeft'),
                scrollRight: document.getElementById('scrollRight'),
                locationDisplay: document.getElementById('locationDisplay'),
                locationTrigger: document.getElementById('locationTrigger'),
                offlineModal: document.getElementById('offlineModal'),
                pageLoader: document.getElementById('pageLoader'),
                backToTop: document.getElementById('backToTop'),
                flashMessage: document.getElementById('flashMessage')
            };

            // ==================== REMOVER LOADER ====================
            window.addEventListener('load', () => {
                if (elements.pageLoader) {
                    elements.pageLoader.classList.add('hidden');
                    setTimeout(() => {
                        elements.pageLoader.style.display = 'none';
                    }, 300);
                }
            });

            // ==================== AUTO-FECHAR FLASH MESSAGE ====================
            if (elements.flashMessage) {
                setTimeout(() => {
                    elements.flashMessage.style.opacity = '0';
                    setTimeout(() => {
                        elements.flashMessage.remove();
                    }, 300);
                }, 5000);
            }

            // ==================== SCROLL DA NAVEGAÇÃO ====================
            let ticking = false;
            let lastScrollY = 0;
            
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        const scrollY = window.pageYOffset;
                        
                        // Navegação sticky
                        if (scrollY > 50) {
                            elements.navBar.style.opacity = '0';
                            elements.navBar.style.pointerEvents = 'none';
                            elements.secHeader.style.display = 'flex';
                            setTimeout(() => elements.secHeader.style.opacity = '1', 10);
                        } else {
                            elements.navBar.style.opacity = '1';
                            elements.navBar.style.pointerEvents = 'auto';
                            elements.secHeader.style.opacity = '0';
                            setTimeout(() => {
                                if(window.pageYOffset <= 50) elements.secHeader.style.display = 'none';
                            }, 300);
                        }
                        
                        // Botão voltar ao topo
                        if (elements.backToTop) {
                            if (scrollY > 300) {
                                elements.backToTop.classList.add('visible');
                            } else {
                                elements.backToTop.classList.remove('visible');
                            }
                        }
                        
                        lastScrollY = scrollY;
                        ticking = false;
                    });
                    ticking = true;
                }
            }, { passive: true });

            // ==================== BOTÃO VOLTAR AO TOPO ====================
            if (elements.backToTop) {
                elements.backToTop.addEventListener('click', () => {
                    window.scrollTo({ 
                        top: 0, 
                        behavior: 'smooth' 
                    });
                });
            }

            // ==================== CARROSSEL DE CATEGORIAS ====================
            const scrollAmount = 300;
            
            function updateCarouselButtons() {
                if (!elements.categoryGrid) return;
                elements.scrollLeft.disabled = elements.categoryGrid.scrollLeft <= 0;
                elements.scrollRight.disabled = 
                    elements.categoryGrid.scrollLeft >= elements.categoryGrid.scrollWidth - elements.categoryGrid.clientWidth;
            }
            
            if (elements.scrollLeft && elements.scrollRight && elements.categoryGrid) {
                elements.scrollLeft.addEventListener('click', () => {
                    elements.categoryGrid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                });
                
                elements.scrollRight.addEventListener('click', () => {
                    elements.categoryGrid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                });
                
                elements.categoryGrid.addEventListener('scroll', updateCarouselButtons, { passive: true });
                window.addEventListener('resize', updateCarouselButtons, { passive: true });
                updateCarouselButtons();
            }

            // ==================== CONECTIVIDADE ====================
            function checkConnectivity() {
                if (elements.offlineModal) {
                    elements.offlineModal.style.display = navigator.onLine ? 'none' : 'flex';
                }
            }

            window.addEventListener('online', checkConnectivity);
            window.addEventListener('offline', checkConnectivity);

            // ==================== LOCALIZAÇÃO ====================
            async function updateLocation() {
                if (!navigator.onLine) {
                    checkConnectivity();
                    return;
                }

                if (elements.locationDisplay) {
                    elements.locationDisplay.textContent = 'Localizando...';
                }
                
                try {
                    const response = await fetch('refresh_location.php');
                    if (!response.ok) throw new Error('Falha na requisição');
                    
                    const data = await response.json();
                    if (elements.locationDisplay) {
                        elements.locationDisplay.textContent = 
                            (data.country && data.country !== 'Desconhecido' && data.country !== '') 
                            ? data.country 
                            : 'Selecionar localização';
                    }
                } catch (error) {
                    if (elements.locationDisplay) {
                        elements.locationDisplay.textContent = 'Selecionar localização';
                    }
                    console.error('Erro ao buscar localização:', error);
                }
            }

            if (elements.locationTrigger) {
                elements.locationTrigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (elements.locationDisplay && elements.locationDisplay.textContent.trim() !== 'Selecionar localização') {
                        return;
                    }
                    
                    if (!navigator.onLine) {
                        checkConnectivity();
                        return;
                    }

                    alert("Não conseguimos detectar sua região automaticamente.\n\nPara ajudar:\n1. Verifique se o seu GPS está ativo\n2. Desative sua VPN, se estiver usando\n3. Recarregue a página");
                    updateLocation();
                });
            }

            // ==================== INICIALIZAÇÃO ====================
            checkConnectivity();
            
            // Performance: Intersection Observer para lazy load adicional
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                            }
                            observer.unobserve(img);
                        }
                    });
                }, {
                    rootMargin: '50px 0px'
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        })();
    </script>
</body>
</html>
<?php
// Enviar buffer de saída
ob_end_flush();
?>