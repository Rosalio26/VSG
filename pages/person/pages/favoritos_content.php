<?php
/* =========================================
   FAVORITOS DO CLIENTE
   Arquivo: pages/person/pages/favoritos_content.php
   ========================================= */

$userId = $_SESSION['auth']['user_id'];

// PAGINAÇÃO
$items_per_page = 12;
$page_number = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page_number - 1) * $items_per_page;

// FILTROS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent'; // recent, price_asc, price_desc, name

// 1. CONTAR TOTAL DE FAVORITOS
$count_query = "
    SELECT COUNT(*) as total
    FROM favorites f
    INNER JOIN products p ON f.product_id = p.id
    WHERE f.user_id = ?
    AND p.deleted_at IS NULL
    AND p.status = 'ativo'
";

$where_conditions = [];
$params = [$userId];
$param_types = 'i';

if (!empty($search)) {
    $where_conditions[] = "p.nome LIKE ?";
    $params[] = "%{$search}%";
    $param_types .= 's';
}

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
    $param_types .= 'i';
}

if (!empty($where_conditions)) {
    $count_query .= " AND " . implode(" AND ", $where_conditions);
}

$stmt = $mysqli->prepare($count_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_favorites = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_favorites / $items_per_page);

// 2. BUSCAR FAVORITOS COM DETALHES
$order_by = match($sort) {
    'price_asc' => 'p.preco ASC',
    'price_desc' => 'p.preco DESC',
    'name' => 'p.nome ASC',
    default => 'f.created_at DESC'
};

$favorites_query = "
    SELECT 
        f.id as favorite_id,
        f.created_at as favorited_at,
        p.id,
        p.nome,
        p.imagem,
        p.image_path1,
        p.image_path2,
        p.image_path3,
        p.image_path4,
        p.preco,
        p.currency,
        p.stock,
        p.descricao,
        c.name as category_name,
        u.nome as company_name,
        (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) as total_vendas,
        COALESCE((SELECT AVG(rating) FROM customer_reviews WHERE product_id = p.id), 4.5) as avg_rating,
        COALESCE((SELECT COUNT(*) FROM customer_reviews WHERE product_id = p.id), 0) as review_count,
        CASE 
            WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 
            ELSE 0 
        END as is_new
    FROM favorites f
    INNER JOIN products p ON f.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE f.user_id = ?
    AND p.deleted_at IS NULL
    AND p.status = 'ativo'
";

if (!empty($where_conditions)) {
    $favorites_query .= " AND " . implode(" AND ", $where_conditions);
}

$favorites_query .= " ORDER BY {$order_by} LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($favorites_query);
$params[] = $items_per_page;
$params[] = $offset;
$param_types .= 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. BUSCAR CATEGORIAS PARA FILTRO
$stmt = $mysqli->prepare("
    SELECT DISTINCT c.id, c.name
    FROM favorites f
    INNER JOIN products p ON f.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE f.user_id = ?
    AND p.deleted_at IS NULL
    AND c.id IS NOT NULL
    ORDER BY c.name ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$available_categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4. ESTATÍSTICAS RÁPIDAS
$stmt = $mysqli->prepare("
    SELECT 
        COUNT(DISTINCT f.id) as total_favorites,
        COUNT(DISTINCT p.category_id) as total_categories,
        SUM(p.preco) as total_value,
        COUNT(CASE WHEN p.stock > 0 THEN 1 END) as in_stock_count,
        COUNT(CASE WHEN p.stock = 0 THEN 1 END) as out_of_stock_count
    FROM favorites f
    INNER JOIN products p ON f.product_id = p.id
    WHERE f.user_id = ?
    AND p.deleted_at IS NULL
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="index.php">Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Favoritos</span>
</div>

<!-- Page Header -->
<div class="page-header">
    <div class="page-title-section">
        <h1><i class="fa-solid fa-heart" style="color: #ef4444;"></i> Meus Favoritos</h1>
        <p class="page-subtitle">
            <?= $total_favorites ?> produto<?= $total_favorites != 1 ? 's' : '' ?> salvo<?= $total_favorites != 1 ? 's' : '' ?>
            <?php if ($stats['out_of_stock_count'] > 0): ?>
                · <span style="color: #ef4444;"><?= $stats['out_of_stock_count'] ?> esgotado<?= $stats['out_of_stock_count'] != 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <?php if ($total_favorites > 0): ?>
            <button class="btn btn-secondary" onclick="exportFavorites()">
                <i class="fa-solid fa-download"></i>
                Exportar Lista
            </button>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="window.location.href='../../marketplace.php'">
            <i class="fa-solid fa-plus"></i>
            Adicionar Mais
        </button>
    </div>
</div>

<!-- Stats Cards -->
<?php if ($total_favorites > 0): ?>
<div class="favorites-stats">
    <div class="stat-card">
        <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
            <i class="fa-solid fa-heart"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['total_favorites'] ?></div>
            <div class="stat-label">Favoritos</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #dbeafe; color: #3b82f6;">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['total_categories'] ?></div>
            <div class="stat-label">Categorias</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                <?= number_format($stats['total_value'] / 1000, 1) ?>K
            </div>
            <div class="stat-label">Valor Total (MZN)</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['in_stock_count'] ?></div>
            <div class="stat-label">Disponíveis</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters & Search -->
<?php if ($total_favorites > 0): ?>
<div class="filters-bar">
    <form method="GET" action="index.php" class="filters-form">
        <input type="hidden" name="page" value="favoritos">
        
        <!-- Search -->
        <div class="search-box">
            <i class="fa-solid fa-search"></i>
            <input type="text" 
                   name="search" 
                   placeholder="Buscar nos favoritos..." 
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        
        <!-- Category Filter -->
        <select name="category" class="filter-select">
            <option value="0">Todas as categorias</option>
            <?php foreach ($available_categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <!-- Sort -->
        <select name="sort" class="filter-select" onchange="this.form.submit()">
            <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Mais recentes</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nome (A-Z)</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Menor preço</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Maior preço</option>
        </select>
        
        <button type="submit" class="btn btn-secondary">
            <i class="fa-solid fa-filter"></i>
            Filtrar
        </button>
        
        <?php if (!empty($search) || $category_filter > 0 || $sort !== 'recent'): ?>
            <a href="index.php?page=favoritos" class="btn btn-secondary">
                <i class="fa-solid fa-times"></i>
                Limpar
            </a>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<!-- Favorites Grid -->
<?php if (empty($favorites)): ?>
    <div class="empty-state" style="padding: 80px 20px;">
        <i class="fa-regular fa-heart" style="font-size: 80px; color: var(--gray-300);"></i>
        <h3 style="font-size: 20px; color: var(--gray-700); margin: 20px 0 8px 0;">
            <?= !empty($search) ? 'Nenhum favorito encontrado' : 'Você ainda não tem favoritos' ?>
        </h3>
        <p style="color: var(--gray-600); margin-bottom: 24px;">
            <?= !empty($search) ? 'Tente usar outros termos de busca' : 'Salve produtos para acessá-los rapidamente depois' ?>
        </p>
        <button class="btn btn-primary" onclick="window.location.href='../../marketplace.php'">
            <i class="fa-solid fa-shopping-bag"></i>
            Explorar Marketplace
        </button>
    </div>
<?php else: ?>
    <div class="products-grid">
        <?php foreach ($favorites as $product): 
            // Badges
            $badge = null;
            $badgeClass = '';
            
            if ($product['stock'] == 0) {
                $badge = '❌ Esgotado';
                $badgeClass = 'sold-out';
            } elseif ($product['is_new'] == 1) {
                $badge = '✨ Novo';
                $badgeClass = 'new';
            } elseif ($product['total_vendas'] > 50) {
                $badge = '🔥 Popular';
                $badgeClass = 'hot';
            }
            
            // Rating
            $rating = round($product['avg_rating'] ?? 4.5);
            $reviewCount = $product['review_count'] ?? 0;
            
            // Imagem
            $productImage = getProductImage($product);
            
            // Data de adição
            $favoritedDate = date('d/m/Y', strtotime($product['favorited_at']));
        ?>
            <div class="product-card-favorite <?= $product['stock'] == 0 ? 'out-of-stock' : '' ?>">
                <!-- Remove Button -->
                <button class="btn-remove-favorite" 
                        onclick="event.stopPropagation(); removeFavorite(<?= $product['favorite_id'] ?>, <?= $product['id'] ?>)"
                        title="Remover dos favoritos">
                    <i class="fa-solid fa-heart"></i>
                </button>
                
                <?php if ($badge): ?>
                    <div class="product-badge <?= $badgeClass ?>"><?= $badge ?></div>
                <?php endif; ?>
                
                <div onclick="window.location.href='../../marketplace/produto.php?id=<?= $product['id'] ?>'" style="cursor: pointer;">
                    <img src="<?= htmlspecialchars($productImage) ?>" 
                         alt="<?= htmlspecialchars($product['nome']) ?>" 
                         class="product-card-img"
                         onerror="this.src='<?= generateCompanyAvatar($product['company_name'] ?? 'Fornecedor', 400) ?>'">
                    
                    <div class="product-card-content">
                        <div class="product-category">
                            <?= htmlspecialchars($product['category_name'] ?: 'Produtos') ?>
                        </div>
                        
                        <h3 class="product-card-title">
                            <?= htmlspecialchars($product['nome']) ?>
                        </h3>
                        
                        <div class="product-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $rating): ?>
                                    <i class="fa-solid fa-star"></i>
                                <?php else: ?>
                                    <i class="fa-regular fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span class="rating-count">(<?= $reviewCount ?>)</span>
                        </div>
                        
                        <div class="product-meta-info">
                            <span>
                                <i class="fa-solid fa-<?= $product['stock'] > 0 ? 'check-circle' : 'times-circle' ?>"></i>
                                <?php if ($product['stock'] > 0): ?>
                                    <?= $product['stock'] > 100 ? '100+' : $product['stock'] ?> disponíveis
                                <?php else: ?>
                                    Esgotado
                                <?php endif; ?>
                            </span>
                            <span>
                                <i class="fa-solid fa-building"></i> 
                                <?= htmlspecialchars(substr($product['company_name'] ?: 'Fornecedor', 0, 15)) ?>
                            </span>
                        </div>
                        
                        <div class="favorite-date">
                            <i class="fa-solid fa-heart"></i>
                            Salvo em <?= $favoritedDate ?>
                        </div>
                        
                        <div class="product-price-section">
                            <div class="product-price">
                                <span class="price-value">
                                    <?= strtoupper($product['currency']) ?> 
                                    <?= number_format($product['preco'], 2, ',', '.') ?>
                                </span>
                            </div>
                            <?php if ($product['stock'] > 0): ?>
                                <button class="btn-add-cart" onclick="event.stopPropagation(); adicionarAoCarrinho(<?= $product['id'] ?>)">
                                    <i class="fa-solid fa-cart-plus"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn-add-cart" disabled style="background: var(--gray-400); cursor: not-allowed;">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page_number > 1): ?>
                <a href="?page=favoritos&p=<?= $page_number - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $category_filter > 0 ? '&category=' . $category_filter : '' ?>&sort=<?= $sort ?>" class="pagination-btn">
                    <i class="fa-solid fa-chevron-left"></i>
                    Anterior
                </a>
            <?php endif; ?>
            
            <div class="pagination-info">
                Página <?= $page_number ?> de <?= $total_pages ?>
            </div>
            
            <?php if ($page_number < $total_pages): ?>
                <a href="?page=favoritos&p=<?= $page_number + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $category_filter > 0 ? '&category=' . $category_filter : '' ?>&sort=<?= $sort ?>" class="pagination-btn">
                    Próxima
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// Remover favorito
function removeFavorite(favoriteId, productId) {
    if (!confirm('Tem certeza que deseja remover este produto dos favoritos?')) {
        return;
    }
    
    fetch('actions/remove_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            favorite_id: favoriteId,
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Erro ao remover favorito');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao remover favorito');
    });
}

// Adicionar ao carrinho
function adicionarAoCarrinho(productId) {
    alert('Produto adicionado ao carrinho! (ID: ' + productId + ')');
    window.location.href = '../../marketplace/produto.php?id=' + productId;
}

// Exportar favoritos
function exportFavorites() {
    window.location.href = 'actions/export_favorites.php';
}
</script>