<?php
require_once __DIR__ . '/registration/bootstrap.php';
require_once __DIR__ . '/registration/includes/security.php';
require_once __DIR__ . '/registration/includes/db.php';

// Verificar se usuário está logado
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_name = $user_logged_in ? ($_SESSION['auth']['nome'] ?? 'Usuário') : null;
$user_avatar = $user_logged_in ? ($_SESSION['auth']['avatar'] ?? null) : null;

// Buscar estatísticas reais
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM products WHERE status = 'ativo' AND deleted_at IS NULL) as total_products,
        (SELECT COUNT(*) FROM users WHERE type = 'company' AND status = 'active') as total_suppliers,
        (SELECT COUNT(DISTINCT country) FROM users WHERE country IS NOT NULL) as total_countries,
        (SELECT AVG(rating) FROM customer_reviews) as avg_rating
";
$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Buscar categorias principais com contagem de produtos
// Buscar categorias principais com contagem de produtos (incluindo subcategorias)
$categories_query = "
    SELECT 
        c.id,
        c.name,
        CONCAT('fa-solid fa-', c.icon) as icon,
        (
            SELECT COUNT(DISTINCT p.id) 
            FROM products p
            LEFT JOIN categories sub ON p.category_id = sub.id
            WHERE (sub.id = c.id OR sub.parent_id = c.id)
            AND p.status = 'ativo' 
            AND p.deleted_at IS NULL
        ) as product_count
    FROM categories c
    WHERE c.parent_id IS NULL
    AND c.status = 'ativa'
    GROUP BY c.id
    ORDER BY product_count DESC, c.name ASC
    LIMIT 12
";
$categories_result = $mysqli->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Buscar localização do usuário
$user_location = 'Definir localização';
if ($user_logged_in) {
    $location_query = "SELECT city, state, country FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($location_query);
    $stmt->bind_param("i", $_SESSION['auth']['user_id']);
    $stmt->execute();
    $location_result = $stmt->get_result()->fetch_assoc();
    if ($location_result && $location_result['city']) {
        $user_location = $location_result['city'];
    }
    $stmt->close();
}

// Contar itens no carrinho
$cart_count = 0;
if ($user_logged_in) {
    $cart_query = "
        SELECT COALESCE(SUM(ci.quantity), 0) as total
        FROM shopping_carts sc
        INNER JOIN cart_items ci ON sc.id = ci.cart_id
        WHERE sc.user_id = ? AND sc.status = 'active'
    ";
    $stmt = $mysqli->prepare($cart_query);
    $stmt->bind_param("i", $_SESSION['auth']['user_id']);
    $stmt->execute();
    $cart_result = $stmt->get_result()->fetch_assoc();
    $cart_count = $cart_result['total'];
    $stmt->close();
}

// Buscar produtos em destaque
// Buscar produtos em destaque
// Buscar os 4 produtos mais comprados
$products_query = "
    SELECT 
        p.id,
        p.nome,
        p.preco,
        p.currency,
        p.imagem,
        p.image_path1,
        p.stock,
        p.created_at,
        c.name as category_name,
        CONCAT('fa-solid fa-', c.icon) as category_icon,
        u.nome as company_name,
        COALESCE(AVG(cr.rating), 4.5) as avg_rating,
        COALESCE(COUNT(DISTINCT cr.id), 0) as review_count,
        COALESCE(SUM(oi.quantity), 0) as total_sales
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN customer_reviews cr ON p.id = cr.product_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    WHERE p.status = 'ativo' 
    AND p.deleted_at IS NULL
    AND p.stock > 0
    GROUP BY p.id
    ORDER BY total_sales DESC, p.created_at DESC
    LIMIT 4
";
$products_result = $mysqli->query($products_query);
$featured_products = $products_result->fetch_all(MYSQLI_ASSOC);

// Buscar produtos novos
$new_products_query = "
    SELECT 
        p.id, p.nome, p.preco, p.currency, p.imagem, p.image_path1, p.stock, p.created_at,
        c.name as category_name, 
        CONCAT('fa-solid fa-', c.icon) as category_icon,
        u.nome as company_name,
        COALESCE(AVG(cr.rating), 4.5) as avg_rating,
        COALESCE(COUNT(DISTINCT cr.id), 0) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN customer_reviews cr ON p.id = cr.product_id
    WHERE p.status = 'ativo' 
    AND p.deleted_at IS NULL
    AND p.stock > 0
    AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 8
";
$new_products_result = $mysqli->query($new_products_query);
$new_products = $new_products_result->fetch_all(MYSQLI_ASSOC);

// Função para gerar imagem do produto
function getProductImageUrl($product) {
    if (!empty($product['imagem'])) {
        return 'uploads/products/' . $product['imagem'];
    }
    if (!empty($product['image_path1'])) {
        return 'uploads/products/' . $product['image_path1'];
    }
    $company = urlencode($product['company_name'] ?? 'Produto');
    return "https://ui-avatars.com/api/?name={$company}&size=400&background=00b96b&color=fff&bold=true&font-size=0.1";
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VSG Marketplace - Produtos Sustentáveis Certificados</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style/footer.css">
    <link rel="stylesheet" href="assets/style/index_start.css">
</head>

<body>

    <!-- Top Strip -->
    <div class="top-strip">
        <div class="container">
            <div class="top-strip-content">
                <div class="top-left-info">
                    <a href="#" class="top-link" id="locationTrigger">
                        <i class="fa-solid fa-location-dot"></i>
                        Entregar em <span id="locationDisplay"><?= htmlspecialchars($user_location) ?></span>
                    </a>
                    <span class="divider"></span>
                    <a href="#" class="top-link">
                        <i class="fa-solid fa-shield-check"></i>
                        Proteção ao Comprador
                    </a>
                </div>
                <ul class="top-right-nav">
                    <?php if ($user_logged_in && $_SESSION['auth']['type'] === 'company'): ?>
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

    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-main">
                <a href="index.php" class="logo-container">
                    <div class="logo-text">
                        VSG<span class="logo-accent">•</span>
                    </div>
                </a>

                <form action="marketplace.php" method="GET" class="search-section">
                    <select class="search-category" name="category">
                        <option value="">Todas Categorias</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="search-input-wrapper">
                        <input type="text" class="search-input" name="q" placeholder="Buscar produtos sustentáveis...">
                    </div>
                    <button type="submit" class="search-button">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>

                <div class="header-actions">
                    <?php if ($user_logged_in): ?>
                        <a href="pages/person/index.php?page=favoritos" class="header-action">
                            <i class="fa-solid fa-heart action-icon"></i>
                            <span>Favoritos</span>
                        </a>

                        <a href="pages/person/index.php?page=carrinho" class="header-action">
                            <i class="fa-solid fa-cart-shopping action-icon"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-badge"><?= $cart_count ?></span>
                            <?php endif; ?>
                            <span>Carrinho</span>
                        </a>

                        <a href="pages/person/index.php" class="account-action">
                            <?php if ($user_avatar): ?>
                                <img src="<?= htmlspecialchars($user_avatar) ?>" alt="Avatar" class="user-avatar">
                            <?php else: ?>
                                <i class="fa-solid fa-user-circle"></i>
                            <?php endif; ?>
                            <span><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
                        </a>
                    <?php else: ?>
                        <a href="registration/login/login.php" class="account-action">
                            <i class="fa-solid fa-user-circle"></i>
                            <span>Entrar</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav-bar">
        <div class="container">
            <div class="nav-content">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="marketplace.php" class="nav-link">
                            <i class="fa-solid fa-store"></i>
                            Marketplace
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="marketplace.php?sort=new" class="nav-link">
                            <i class="fa-solid fa-fire"></i>
                            Novidades
                            <span class="promo-tag">Hot</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="marketplace.php?certified=1" class="nav-link">
                            <i class="fa-solid fa-certificate"></i>
                            Certificados
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="marketplace.php?featured=1" class="nav-link">
                            <i class="fa-solid fa-star"></i>
                            Destaques
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="marketplace.php?eco=carbon-zero" class="nav-link">
                            <i class="fa-solid fa-leaf"></i>
                            Carbono Zero
                        </a>
                    </li>
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

                <div class="hero-stats">
                    <div class="stat-card">
                        <i class="fa-solid fa-boxes-stacked stat-icon"></i>
                        <div class="stat-number"><?= number_format($stats['total_products']) ?></div>
                        <div class="stat-label">Produtos Listados</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-users stat-icon"></i>
                        <div class="stat-number"><?= number_format($stats['total_suppliers']) ?></div>
                        <div class="stat-label">Fornecedores Ativos</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-globe-africa stat-icon"></i>
                        <div class="stat-number"><?= $stats['total_countries'] ?></div>
                        <div class="stat-label">Países Atendidos</div>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-star stat-icon"></i>
                        <div class="stat-number"><?= number_format($stats['avg_rating'] ?? 4.8, 1) ?></div>
                        <div class="stat-label">Avaliação Média</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Grid -->
    <section class="category-section">
        <div class="container">
            <div class="category-carousel-wrapper">
                <button class="carousel-btn carousel-btn-left" id="scrollLeft">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                
                <div class="category-grid" id="categoryGrid">
                    <?php foreach ($categories as $category): ?>
                    <a href="marketplace.php?category=<?= $category['id'] ?>" class="category-card">
                        <div class="category-icon">
                            <i class="<?= htmlspecialchars($category['icon'] ?: 'fa-solid fa-box') ?>"></i>
                        </div>
                        <div class="category-name"><?= htmlspecialchars($category['name']) ?></div>
                        <div class="category-count"><?= number_format($category['product_count']) ?> produtos</div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <button class="carousel-btn carousel-btn-right" id="scrollRight">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Trust Bar -->
    <section class="trust-bar">
        <div class="container">
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

    <!-- Featured Products -->
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
                    $rating = round($product['avg_rating']);
                    $isNew = (strtotime($product['created_at']) > strtotime('-7 days'));
                    $isLowStock = $product['stock'] > 0 && $product['stock'] <= 10;
                    $productImage = getProductImageUrl($product);
                ?>
                    <a href="marketplace.php?product=<?= $product['id'] ?>" class="product-card">
                        <div class="product-image-container">
                            <img src="<?= htmlspecialchars($productImage) ?>" 
                                 alt="<?= htmlspecialchars($product['nome']) ?>" 
                                 class="product-image"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($product['nome']) ?>&size=400&background=00b96b&color=fff&font-size=0.1'">
                            
                            <?php if ($isNew): ?>
                                <span class="product-badge new">Novo</span>
                            <?php elseif ($product['total_sales'] > 50): ?>
                                <span class="product-badge">Popular</span>
                            <?php endif; ?>

                            <div class="product-actions">
                                <button class="action-btn" onclick="event.preventDefault();">
                                    <i class="fa-regular fa-heart"></i>
                                </button>
                                <button class="action-btn" onclick="event.preventDefault();">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="product-info">
                            <div class="product-category">
                                <i class="<?= htmlspecialchars($product['category_icon'] ?: 'fa-solid fa-box') ?>"></i>
                                <span><?= htmlspecialchars($product['category_name'] ?: 'Geral') ?></span>
                            </div>

                            <h3 class="product-name"><?= htmlspecialchars($product['nome']) ?></h3>

                            <div class="product-supplier">
                                <i class="fa-solid fa-building"></i>
                                <?= htmlspecialchars($product['company_name'] ?: 'Fornecedor') ?>
                            </div>

                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-<?= $i <= $rating ? 'solid' : 'regular' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count">(<?= $product['review_count'] ?>)</span>
                            </div>

                            <div class="product-footer">
                                <div class="product-price">
                                    <span class="price-currency"><?= strtoupper($product['currency']) ?></span>
                                    <span class="price-value"><?= number_format($product['preco'], 2, ',', '.') ?></span>
                                </div>
                                <span class="stock-badge <?= $isLowStock ? 'low' : 'high' ?>">
                                    <?php if ($isLowStock): ?>
                                        Últimas <?= $product['stock'] ?>
                                    <?php else: ?>
                                        <?= $product['stock'] ?> em estoque
                                    <?php endif; ?>
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

    <?php include 'includes/footer.html'; ?>

    <script>
        let lastScroll = 0;
        const header = document.querySelector('.main-header');

        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            if (currentScroll > 100) {
                header.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            } else {
                header.style.boxShadow = '0 1px 3px rgba(0,0,0,0.12)';
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
        const grid = document.getElementById('categoryGrid');
        const btnLeft = document.getElementById('scrollLeft');
        const btnRight = document.getElementById('scrollRight');
        
        // Distância de scroll (ajuste conforme necessário)
        const scrollAmount = 300;
        
        // Função para atualizar estado dos botões
        function updateButtons() {
            btnLeft.disabled = grid.scrollLeft <= 0;
            btnRight.disabled = grid.scrollLeft >= grid.scrollWidth - grid.clientWidth;
        }
        
        // Event listeners
        btnLeft.addEventListener('click', function() {
            grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
        
        btnRight.addEventListener('click', function() {
            grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });
        
        // Atualizar botões ao fazer scroll
        grid.addEventListener('scroll', updateButtons);
        
        // Atualizar botões inicialmente
        updateButtons();
        
        // Atualizar ao redimensionar a janela
        window.addEventListener('resize', updateButtons);
    });
    </script>

</body>
</html>