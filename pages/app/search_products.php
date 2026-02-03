<?php
// ==================== INICIALIZAÇÃO E SEGURANÇA ====================
require_once __DIR__ . '/../../registration/bootstrap.php';
require_once __DIR__ . '/../../registration/includes/security.php';
require_once __DIR__ . '/../../registration/includes/db.php';

// Habilitar output buffering
ob_start();

// ==================== AUTENTICAÇÃO DO USUÁRIO ====================
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_name = $user_logged_in ? ($_SESSION['auth']['nome'] ?? 'Usuário') : null;
$user_avatar = $user_logged_in ? ($_SESSION['auth']['avatar'] ?? null) : null;
$user_type = $user_logged_in ? ($_SESSION['auth']['type'] ?? 'customer') : null;

// ==================== PARÂMETROS DE BUSCA ====================
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 24;
$offset = ($page - 1) * $per_page;

// Filtros adicionais
$in_stock_only = isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : false;
$eco_badges = isset($_GET['eco_badges']) ? explode(',', $_GET['eco_badges']) : [];
$rating_min = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// ==================== CONSTRUIR QUERY DE BUSCA ====================
$where_conditions = [];
$params = [];
$types = '';

// Condições básicas
$where_conditions[] = "p.status = 'ativo'";
$where_conditions[] = "p.deleted_at IS NULL";

// Busca por termo
if (!empty($search_term)) {
    $search_like = '%' . $search_term . '%';
    $where_conditions[] = "(p.nome LIKE ? OR p.descricao LIKE ? OR c.name LIKE ? OR u.nome LIKE ?)";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ssss';
}

// Filtro por categoria
if ($category_id > 0) {
    $where_conditions[] = "(p.category_id = ? OR c.parent_id = ?)";
    $params[] = $category_id;
    $params[] = $category_id;
    $types .= 'ii';
}

// Filtro por preço
if ($min_price > 0) {
    $where_conditions[] = "p.preco >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price > 0) {
    $where_conditions[] = "p.preco <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

// Filtro por estoque
if ($in_stock_only) {
    $where_conditions[] = "p.stock > 0";
}

// Filtro por avaliação mínima
if ($rating_min > 0) {
    $where_conditions[] = "COALESCE(AVG(cr.rating), 0) >= ?";
    $params[] = $rating_min;
    $types .= 'i';
}

// Combinar condições
$where_clause = implode(' AND ', $where_conditions);

// ==================== DETERMINAR ORDENAÇÃO ====================
$order_by = match($sort_by) {
    'price_asc' => 'p.preco ASC',
    'price_desc' => 'p.preco DESC',
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'name_asc' => 'p.nome ASC',
    'name_desc' => 'p.nome DESC',
    'rating' => 'avg_rating DESC',
    'popular' => 'total_sales DESC',
    default => 'total_sales DESC, p.created_at DESC' // Padrão: mais vendidos primeiro
};

// ==================== QUERY PRINCIPAL ====================
$main_query = "
    SELECT 
        p.id, p.nome, p.descricao, p.preco, p.currency, 
        p.imagem, p.image_path1, p.stock, p.created_at,
        p.eco_badges,
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
    WHERE {$where_clause}
    GROUP BY p.id
    " . ($rating_min > 0 ? "HAVING avg_rating >= {$rating_min}" : "") . "
    ORDER BY {$order_by}
    LIMIT ? OFFSET ?
";

// Adicionar limit e offset
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// ==================== EXECUTAR QUERY ====================
$stmt = $mysqli->prepare($main_query);

if (!$stmt) {
    error_log("Erro na preparação da query de busca: " . $mysqli->error);
    $products = [];
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ==================== CONTAR TOTAL DE RESULTADOS ====================
$count_query = "
    SELECT COUNT(DISTINCT p.id) as total
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN customer_reviews cr ON p.id = cr.product_id
    WHERE {$where_clause}
    " . ($rating_min > 0 ? "GROUP BY p.id HAVING COALESCE(AVG(cr.rating), 0) >= {$rating_min}" : "");

$count_stmt = $mysqli->prepare($count_query);

if (!$count_stmt) {
    $total_results = 0;
} else {
    // Remover os últimos 2 parâmetros (limit e offset) para a query de contagem
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    
    if ($rating_min > 0) {
        $total_results = $count_result->num_rows;
    } else {
        $count_row = $count_result->fetch_assoc();
        $total_results = $count_row['total'] ?? 0;
    }
    
    $count_stmt->close();
}

$total_pages = ceil($total_results / $per_page);

// ==================== BUSCAR CATEGORIAS PARA FILTRO ====================
$categories_query = "
    SELECT id, name, icon,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'ativo' AND deleted_at IS NULL) as product_count
    FROM categories c
    WHERE c.parent_id IS NULL AND c.status = 'ativa'
    ORDER BY name ASC
";
$categories_result = $mysqli->query($categories_query);
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

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

function isProductNew($days_old) {
    return $days_old <= 7;
}

function buildQueryString($params) {
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Busca de produtos sustentáveis - VSG Marketplace">
    <meta name="theme-color" content="#00b96b">
    <title><?= !empty($search_term) ? 'Busca: ' . escapeHtml($search_term) : 'Buscar Produtos' ?> - VSG Marketplace</title>

    <!-- Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Favicon -->
    <link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../../assets/style/footer.css">
    <link rel="stylesheet" href="../../assets/style/index_start.css">
    <link rel="stylesheet" href="../../assets/style/search.css">
</head>

<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-main">
                <a href="../../index.php" class="logo-container" aria-label="VSG Marketplace - Página Inicial">
                    <div class="logo-text">
                        VSG<span class="logo-accent">•</span> <span class="logo-name-market">MARKETPLACE</span>
                    </div>
                </a>

                <!-- Barra de Busca -->
                <div class="search-container">
                    <form action="search_products.php" method="GET" class="search-form" role="search">
                        <input type="text" 
                               name="search" 
                               value="<?= escapeHtml($search_term) ?>"
                               placeholder="Buscar produtos sustentáveis..." 
                               class="search-input"
                               aria-label="Buscar produtos">
                        <button type="submit" class="search-btn" aria-label="Buscar">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </form>
                </div>

                <!-- User Account -->
                <?php if ($user_logged_in): ?>
                    <a href="../person/index.php" class="account-action">
                        <?php if ($user_avatar): ?>
                            <img src="<?= escapeHtml($user_avatar) ?>" alt="Avatar" class="user-avatar">
                        <?php else: ?>
                            <i class="fa-solid fa-user-circle"></i>
                        <?php endif; ?>
                        <span><?= escapeHtml($user_name) ?></span>
                    </a>
                <?php else: ?>
                    <a href="../../registration/login/login.php" class="account-action">
                        <i class="fa-solid fa-user-circle"></i>
                        <span>Entrar</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Breadcrumbs -->
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <div class="container">
            <a href="../../index.php">Início</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Buscar Produtos</span>
            <?php if (!empty($search_term)): ?>
                <i class="fa-solid fa-chevron-right"></i>
                <span>"<?= escapeHtml($search_term) ?>"</span>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="search-page">
        <div class="container">
            <div class="search-layout">
                
                <!-- Sidebar de Filtros -->
                <aside class="search-sidebar">
                    <div class="filter-header">
                        <h3><i class="fa-solid fa-filter"></i> Filtros</h3>
                        <button class="clear-filters" onclick="clearFilters()">Limpar</button>
                    </div>

                    <form id="filterForm" method="GET" action="search_products.php">
                        <input type="hidden" name="search" value="<?= escapeHtml($search_term) ?>">
                        
                        <!-- Filtro de Categorias -->
                        <div class="filter-section">
                            <h4 class="filter-title">
                                <i class="fa-solid fa-tag"></i> Categorias
                            </h4>
                            <div class="filter-options">
                                <label class="filter-option">
                                    <input type="radio" name="category" value="0" <?= $category_id === 0 ? 'checked' : '' ?>>
                                    <span>Todas as Categorias</span>
                                </label>
                                <?php foreach ($categories as $cat): ?>
                                    <label class="filter-option">
                                        <input type="radio" name="category" value="<?= $cat['id'] ?>" 
                                               <?= $category_id === (int)$cat['id'] ? 'checked' : '' ?>>
                                        <i class="fa-solid fa-<?= escapeHtml($cat['icon'] ?: 'box') ?>"></i>
                                        <span><?= escapeHtml($cat['name']) ?></span>
                                        <small>(<?= $cat['product_count'] ?>)</small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filtro de Preço -->
                        <div class="filter-section">
                            <h4 class="filter-title">
                                <i class="fa-solid fa-money-bill-wave"></i> Faixa de Preço
                            </h4>
                            <div class="price-inputs">
                                <input type="number" name="min_price" 
                                       placeholder="Mín" 
                                       value="<?= $min_price > 0 ? $min_price : '' ?>" 
                                       min="0" step="0.01">
                                <span>até</span>
                                <input type="number" name="max_price" 
                                       placeholder="Máx" 
                                       value="<?= $max_price > 0 ? $max_price : '' ?>" 
                                       min="0" step="0.01">
                            </div>
                        </div>

                        <!-- Filtro de Avaliação -->
                        <div class="filter-section">
                            <h4 class="filter-title">
                                <i class="fa-solid fa-star"></i> Avaliação Mínima
                            </h4>
                            <div class="filter-options">
                                <?php for ($r = 5; $r >= 1; $r--): ?>
                                    <label class="filter-option">
                                        <input type="radio" name="rating" value="<?= $r ?>" 
                                               <?= $rating_min === $r ? 'checked' : '' ?>>
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fa-<?= $i <= $r ? 'solid' : 'regular' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span>ou mais</span>
                                    </label>
                                <?php endfor; ?>
                                <label class="filter-option">
                                    <input type="radio" name="rating" value="0" 
                                           <?= $rating_min === 0 ? 'checked' : '' ?>>
                                    <span>Todas as Avaliações</span>
                                </label>
                            </div>
                        </div>

                        <!-- Filtro de Estoque -->
                        <div class="filter-section">
                            <h4 class="filter-title">
                                <i class="fa-solid fa-box"></i> Disponibilidade
                            </h4>
                            <div class="filter-options">
                                <label class="filter-checkbox">
                                    <input type="checkbox" name="in_stock" value="1" 
                                           <?= $in_stock_only ? 'checked' : '' ?>>
                                    <span>Apenas Produtos em Estoque</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="apply-filters-btn">
                            <i class="fa-solid fa-check"></i> Aplicar Filtros
                        </button>
                    </form>
                </aside>

                <!-- Área de Resultados -->
                <div class="search-results">
                    
                    <!-- Header de Resultados -->
                    <div class="results-header">
                        <div class="results-info">
                            <h2>
                                <?php if (!empty($search_term)): ?>
                                    Resultados para "<strong><?= escapeHtml($search_term) ?></strong>"
                                <?php else: ?>
                                    Todos os Produtos
                                <?php endif; ?>
                            </h2>
                            <p><?= number_format($total_results) ?> <?= $total_results === 1 ? 'produto encontrado' : 'produtos encontrados' ?></p>
                        </div>

                        <!-- Ordenação -->
                        <div class="sort-options">
                            <label for="sortSelect">
                                <i class="fa-solid fa-sort"></i> Ordenar por:
                            </label>
                            <select id="sortSelect" onchange="changeSort(this.value)">
                                <option value="relevance" <?= $sort_by === 'relevance' ? 'selected' : '' ?>>Relevância</option>
                                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Mais Recentes</option>
                                <option value="popular" <?= $sort_by === 'popular' ? 'selected' : '' ?>>Mais Populares</option>
                                <option value="price_asc" <?= $sort_by === 'price_asc' ? 'selected' : '' ?>>Menor Preço</option>
                                <option value="price_desc" <?= $sort_by === 'price_desc' ? 'selected' : '' ?>>Maior Preço</option>
                                <option value="rating" <?= $sort_by === 'rating' ? 'selected' : '' ?>>Melhor Avaliados</option>
                                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>A-Z</option>
                                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Z-A</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grid de Produtos -->
                    <?php if (!empty($products)): ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): 
                                $avg_rating = $product['avg_rating'] ?? 0;
                                $review_count = $product['review_count'] ?? 0;
                                $rating = round($avg_rating);
                                $isNew = isProductNew($product['days_old']);
                                $isPopular = $product['total_sales'] >= 50;
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
                                            <span class="product-badge popular">Popular</span>
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

                        <!-- Paginação -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php
                                $query_params = $_GET;
                                unset($query_params['page']);
                                
                                // Primeira página
                                if ($page > 1):
                                    $query_params['page'] = 1;
                                ?>
                                    <a href="?<?= buildQueryString($query_params) ?>" class="page-link">
                                        <i class="fa-solid fa-angles-left"></i>
                                    </a>
                                    <?php
                                    $query_params['page'] = $page - 1;
                                    ?>
                                    <a href="?<?= buildQueryString($query_params) ?>" class="page-link">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                // Páginas numeradas
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                    $query_params['page'] = $i;
                                ?>
                                    <a href="?<?= buildQueryString($query_params) ?>" 
                                       class="page-link <?= $i === $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php
                                // Última página
                                if ($page < $total_pages):
                                    $query_params['page'] = $page + 1;
                                ?>
                                    <a href="?<?= buildQueryString($query_params) ?>" class="page-link">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                    <?php
                                    $query_params['page'] = $total_pages;
                                    ?>
                                    <a href="?<?= buildQueryString($query_params) ?>" class="page-link">
                                        <i class="fa-solid fa-angles-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Estado Vazio -->
                        <div class="empty-state">
                            <i class="fa-solid fa-search"></i>
                            <h3>Nenhum produto encontrado</h3>
                            <p>Tente ajustar seus filtros ou buscar por outros termos</p>
                            <a href="search_products.php" class="btn-primary">
                                <i class="fa-solid fa-rotate"></i> Ver Todos os Produtos
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.html'; ?>

    <!-- JavaScript -->
    <script>
        function changeSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            url.searchParams.delete('page'); // Reset page on sort change
            window.location.href = url.toString();
        }

        function clearFilters() {
            const search = new URLSearchParams(window.location.search).get('search');
            window.location.href = 'search_products.php' + (search ? '?search=' + encodeURIComponent(search) : '');
        }

        // Auto-submit form on filter change (opcional)
        document.querySelectorAll('#filterForm input[type="radio"], #filterForm input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', function() {
                // Uncomment to enable auto-submit
                // document.getElementById('filterForm').submit();
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>