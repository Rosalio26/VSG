<?php
    session_start();

    // Arquivos de configuração e segurança
    require_once 'registration/includes/db.php';
    require_once 'registration/includes/security.php';

    // Configuração de paginação
    $items_per_page = 21;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Filtros
    $selected_categories = isset($_GET['categories']) ? (is_array($_GET['categories']) ? $_GET['categories'] : [$_GET['categories']]) : [];
    $selected_category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
    $max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 100000;
    $min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;
    $search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
    $eco_badges = isset($_GET['eco_badges']) ? (is_array($_GET['eco_badges']) ? $_GET['eco_badges'] : [$_GET['eco_badges']]) : [];

    // ========================================
    // BUSCAR TODAS AS CATEGORIAS DIRETO DO BANCO
    // ========================================
    $all_categories_query = "
        SELECT 
            c.id, 
            c.name, 
            c.slug, 
            c.icon,
            c.parent_id,
            COUNT(DISTINCT p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id 
            AND p.deleted_at IS NULL 
            AND p.status = 'ativo'
        WHERE c.status = 'ativa'
        GROUP BY c.id, c.name, c.slug, c.icon, c.parent_id
        ORDER BY c.parent_id IS NULL DESC, c.name ASC
    ";
    $all_categories_result = $mysqli->query($all_categories_query)->fetch_all(MYSQLI_ASSOC);

    // Organizar categorias (principais com suas subcategorias)
    $all_categories = [];
    $subcategories_map = [];

    // Separar principais e subcategorias
    foreach ($all_categories_result as $cat) {
        if ($cat['parent_id'] === NULL || $cat['parent_id'] == 0) {
            $cat['subcategories'] = [];
            $all_categories[$cat['id']] = $cat;
        } else {
            if (!isset($subcategories_map[$cat['parent_id']])) {
                $subcategories_map[$cat['parent_id']] = [];
            }
            $subcategories_map[$cat['parent_id']][] = $cat;
        }
    }

    // Adicionar subcategorias às principais
    foreach ($all_categories as $id => &$cat) {
        if (isset($subcategories_map[$id])) {
            $cat['subcategories'] = $subcategories_map[$id];
        }
    }
    unset($cat);

    // Converter para array indexado
    $all_categories = array_values($all_categories);

    // Buscar badges ecológicos disponíveis
    $eco_badges_available = [
        'reciclavel' => ['label' => 'Reciclável', 'icon' => 'recycle', 'color' => '#10b981'],
        'biodegradavel' => ['label' => 'Biodegradável', 'icon' => 'leaf', 'color' => '#059669'],
        'organico' => ['label' => 'Orgânico', 'icon' => 'seedling', 'color' => '#84cc16'],
        'zero_waste' => ['label' => 'Zero Waste', 'icon' => 'trash-arrow-up', 'color' => '#0ea5e9'],
        'energia_renovavel' => ['label' => 'Energia Renovável', 'icon' => 'solar-panel', 'color' => '#f59e0b'],
        'comercio_justo' => ['label' => 'Comércio Justo', 'icon' => 'handshake', 'color' => '#8b5cf6'],
        'vegano' => ['label' => 'Vegano', 'icon' => 'leaf', 'color' => '#22c55e'],
        'certificado' => ['label' => 'Certificado', 'icon' => 'certificate', 'color' => '#3b82f6'],
        'reutilizavel' => ['label' => 'Reutilizável', 'icon' => 'arrows-rotate', 'color' => '#06b6d4'],
        'duravel' => ['label' => 'Durável', 'icon' => 'shield-check', 'color' => '#6366f1'],
        'compostavel' => ['label' => 'Compostável', 'icon' => 'seedling', 'color' => '#84cc16'],
        'nao_ogm' => ['label' => 'Não OGM', 'icon' => 'wheat-awn-circle-exclamation', 'color' => '#eab308']
    ];

    // ========================================
    // CONSTRUIR QUERY SQL PRINCIPAL
    // ========================================
    $sql = "SELECT DISTINCT 
        p.id,
        p.nome,
        p.descricao,
        p.imagem,
        p.preco,
        p.currency,
        p.stock,
        p.visualizacoes,
        p.eco_badges,
        p.created_at,
        c.name as categoria,
        c.slug as categoria_slug,
        c.icon as categoria_icon,
        parent_cat.name as categoria_pai,
        parent_cat.slug as categoria_pai_slug,
        u.nome as fornecedor_nome,
        u.public_id as fornecedor_id,
        COALESCE(AVG(cr.rating), 0) as avg_rating,
        COUNT(DISTINCT cr.id) as total_reviews,
        COALESCE(SUM(oi.quantity), 0) as total_vendas
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN categories parent_cat ON c.parent_id = parent_cat.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN customer_reviews cr ON p.id = cr.product_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    WHERE p.deleted_at IS NULL 
    AND p.status = 'ativo'
    AND u.status = 'active'
    AND u.deleted_at IS NULL";

    // Aplicar filtros
    $params = [];
    $types = '';

    // Filtro de busca
    if (!empty($search_query)) {
        $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ? OR c.name LIKE ? OR parent_cat.name LIKE ?)";
        $search_param = "%{$search_query}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }

    // Filtro de categoria específica (do dropdown do header)
    if ($selected_category_id > 0) {
        $sql .= " AND (c.id = ? OR c.parent_id = ?)";
        $params[] = $selected_category_id;
        $params[] = $selected_category_id;
        $types .= 'ii';
    }

    // Filtro de múltiplas categorias (checkboxes do sidebar)
    if (!empty($selected_categories)) {
        $placeholders = str_repeat('?,', count($selected_categories) - 1) . '?';
        $sql .= " AND (c.id IN ({$placeholders}) OR c.parent_id IN ({$placeholders}))";
        foreach ($selected_categories as $cat_id) {
            $params[] = (int)$cat_id;
            $types .= 'i';
        }
        foreach ($selected_categories as $cat_id) {
            $params[] = (int)$cat_id;
            $types .= 'i';
        }
    }

    // Filtro de preço
    if ($min_price > 0 || $max_price < 100000) {
        $sql .= " AND p.preco BETWEEN ? AND ?";
        $params[] = $min_price;
        $params[] = $max_price;
        $types .= 'dd';
    }

    // Filtro de eco badges
    if (!empty($eco_badges)) {
        foreach ($eco_badges as $badge) {
            $sql .= " AND JSON_CONTAINS(p.eco_badges, ?)";
            $params[] = json_encode($badge);
            $types .= 's';
        }
    }

    $sql .= " GROUP BY p.id, p.nome, p.descricao, p.imagem, p.preco, p.currency, p.stock, p.visualizacoes, p.eco_badges, p.created_at, c.name, c.slug, c.icon, parent_cat.name, parent_cat.slug, u.nome, u.public_id";

    // Filtro de avaliação mínima
    if ($min_rating > 0) {
        $sql .= " HAVING avg_rating >= ?";
        $params[] = $min_rating;
        $types .= 'i';
    }

    // Ordenação
    switch ($sort_by) {
        case 'price_asc':
            $sql .= " ORDER BY p.preco ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY p.preco DESC";
            break;
        case 'best_sellers':
            $sql .= " ORDER BY total_vendas DESC, avg_rating DESC";
            break;
        case 'best_rated':
            $sql .= " ORDER BY avg_rating DESC, total_reviews DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY p.created_at DESC";
            break;
        case 'name_asc':
            $sql .= " ORDER BY p.nome ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY p.nome DESC";
            break;
        default:
            $sql .= " ORDER BY total_vendas DESC, avg_rating DESC, p.created_at DESC";
    }

    // ========================================
    // CONTAR TOTAL DE PRODUTOS
    // ========================================
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN categories parent_cat ON c.parent_id = parent_cat.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.deleted_at IS NULL AND p.status = 'ativo' AND u.status = 'active' AND u.deleted_at IS NULL";

    $count_params = [];
    $count_types = '';

    if (!empty($search_query)) {
        $count_sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ? OR c.name LIKE ? OR parent_cat.name LIKE ?)";
        $count_params = array_merge($count_params, [$search_param, $search_param, $search_param, $search_param]);
        $count_types .= 'ssss';
    }

    if ($selected_category_id > 0) {
        $count_sql .= " AND (c.id = ? OR c.parent_id = ?)";
        $count_params[] = $selected_category_id;
        $count_params[] = $selected_category_id;
        $count_types .= 'ii';
    }

    if (!empty($selected_categories)) {
        $placeholders = str_repeat('?,', count($selected_categories) - 1) . '?';
        $count_sql .= " AND (c.id IN ({$placeholders}) OR c.parent_id IN ({$placeholders}))";
        foreach ($selected_categories as $cat_id) {
            $count_params[] = (int)$cat_id;
            $count_types .= 'i';
        }
        foreach ($selected_categories as $cat_id) {
            $count_params[] = (int)$cat_id;
            $count_types .= 'i';
        }
    }

    if ($min_price > 0 || $max_price < 100000) {
        $count_sql .= " AND p.preco BETWEEN ? AND ?";
        $count_params[] = $min_price;
        $count_params[] = $max_price;
        $count_types .= 'dd';
    }

    if (!empty($eco_badges)) {
        foreach ($eco_badges as $badge) {
            $count_sql .= " AND JSON_CONTAINS(p.eco_badges, ?)";
            $count_params[] = json_encode($badge);
            $count_types .= 's';
        }
    }

    $stmt_count = $mysqli->prepare($count_sql);
    if (!empty($count_params)) {
        $stmt_count->bind_param($count_types, ...$count_params);
    }
    $stmt_count->execute();
    $total_products = $stmt_count->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_products / $items_per_page);
    $stmt_count->close();

    // Adicionar limite e offset
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $items_per_page;
    $params[] = $offset;
    $types .= 'ii';

    // Executar query principal
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ========================================
    // VERIFICAR USUÁRIO LOGADO
    // ========================================
    $user_logged_in = isset($_SESSION['auth']['user_id']);
    $user = null;
    $displayName = 'Visitante';
    $displayAvatar = "https://ui-avatars.com/api/?name=Visitante&background=00b96b&color=fff&bold=true";
    $cart_count = 0;

    if ($user_logged_in) {
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
        
        if ($user) {
            if ($user['status'] === 'blocked') {
                session_destroy();
                header("Location: ../../registration/login/login.php?error=conta_bloqueada");
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
            
            if ($user['type'] === 'company') {
                header("Location: ../business/dashboard_business.php");
                exit;
            }
            
            if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
                header("Location: ../../pages/admin/dashboard.php");
                exit;
            }
            
            $displayName = $user['apelido'] ?: $user['nome'];
            $displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=00ff88&color=000&bold=true";
            
            $cart_query = "SELECT COALESCE(SUM(ci.quantity), 0) as cart_count 
                FROM shopping_carts sc
                LEFT JOIN cart_items ci ON sc.id = ci.cart_id
                WHERE sc.user_id = ? AND sc.status = 'active'";
            $stmt_cart = $mysqli->prepare($cart_query);
            $stmt_cart->bind_param('i', $userId);
            $stmt_cart->execute();
            $cart_count = $stmt_cart->get_result()->fetch_assoc()['cart_count'];
            $stmt_cart->close();
        } else {
            session_destroy();
            $user_logged_in = false;
        }
    }

    // ========================================
    // VERIFICAR FAVORITOS DO USUÁRIO LOGADO
    // ========================================
    $user_favorites = [];
    if ($user_logged_in) {
        $stmt = $mysqli->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $user_favorites[] = $row['product_id'];
        }
        $stmt->close();
    }

    // ========================================
    // FUNÇÕES AUXILIARES
    // ========================================
    function getProductBadge($product) {
        if ($product['total_vendas'] > 100) {
            return ['hot', '🔥 Mais Vendido'];
        }
        if (strtotime($product['created_at'] ?? 'now') > strtotime('-30 days')) {
            return ['new', '✨ Novo'];
        }
        if ($product['avg_rating'] >= 4.8 && $product['total_reviews'] > 5) {
            return ['eco', '🌿 Top Rated'];
        }
        return null;
    }

    function formatPrice($price, $currency = 'MZN') {
        return $currency . ' ' . number_format($price, 2, ',', '.');
    }

    function generateStars($rating) {
        $html = '<div class="stars-display">';
        $fullStars = floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
        
        for ($i = 0; $i < $fullStars; $i++) {
            $html .= '<i class="fa-solid fa-star"></i>';
        }
        
        if ($hasHalfStar) {
            $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        }
        
        $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
        for ($i = 0; $i < $emptyStars; $i++) {
            $html .= '<i class="fa-regular fa-star"></i>';
        }
        
        $html .= '</div>';
        return $html;
    }

    function renderEcoBadges($eco_badges_json) {
        global $eco_badges_available;
        
        if (empty($eco_badges_json)) return '';
        
        $badges = json_decode($eco_badges_json, true);
        if (!is_array($badges)) return '';
        
        $html = '<div class="eco-badges-container">';
        foreach (array_slice($badges, 0, 3) as $badge) {
            if (isset($eco_badges_available[$badge])) {
                $badge_info = $eco_badges_available[$badge];
                $html .= sprintf(
                    '<span class="eco-badge-mini" style="background: %s20; color: %s;" title="%s">
                        <i class="fa-solid fa-%s"></i> %s
                    </span>',
                    $badge_info['color'],
                    $badge_info['color'],
                    $badge_info['label'],
                    $badge_info['icon'],
                    substr($badge_info['label'], 0, 10)
                );
            }
        }
        if (count($badges) > 3) {
            $html .= '<span class="eco-badge-mini" style="background: #6366f120; color: #6366f1;">+' . (count($badges) - 3) . '</span>';
        }
        $html .= '</div>';
        
        return $html;
    }

    function getCategoryBreadcrumb($category_slug, $parent_slug) {
        if (!empty($parent_slug)) {
            return $parent_slug . ' › ' . $category_slug;
        }
        return $category_slug;
    }
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos Sustentáveis - VSG B2B Marketplace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="assets/style/marketplace.css">

</head>

<body>
    <?php include 'includes/header.html'; ?>

    <!-- Page Container -->
    <div class="page-container">
        <div class="page-layout">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="marketplace.php">
                    <i class="fa-solid fa-house"></i> Início
                </a>
                <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                <a href="marketplace.php">Produtos</a>
                <?php if ($selected_category_id > 0): 
                    $current_cat = null;
                    foreach ($all_categories as $cat) {
                        if ($cat['id'] == $selected_category_id) {
                            $current_cat = $cat;
                            break;
                        }
                        foreach ($cat['subcategories'] as $subcat) {
                            if ($subcat['id'] == $selected_category_id) {
                                $current_cat = $subcat;
                                $current_cat['parent'] = $cat;
                                break 2;
                            }
                        }
                    }
                    if ($current_cat):
                ?>
                    <?php if (isset($current_cat['parent'])): ?>
                        <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                        <a href="?category=<?= $current_cat['parent']['id'] ?>">
                            <?= htmlspecialchars($current_cat['parent']['name']) ?>
                        </a>
                    <?php endif; ?>
                    <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                    <span><?= htmlspecialchars($current_cat['name']) ?></span>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php include 'includes/aside-sidebar.php'; ?>

            <!-- Main Content -->
            <div class="products-section">
                <!-- Results Header -->
                <div class="results-header">
                    <div class="results-info">
                        Mostrando <strong><?= $offset + 1 ?>-<?= min($offset + $items_per_page, $total_products) ?></strong> de <strong><?= number_format($total_products) ?></strong> produtos
                    </div>
                    <div class="results-controls">
                        <div class="view-toggle">
                            <button class="view-btn active" type="button">
                                <i class="fa-solid fa-grip"></i>
                            </button>
                            <button class="view-btn" type="button">
                                <i class="fa-solid fa-list"></i>
                            </button>
                        </div>
                        <select class="sort-select" onchange="updateSort(this.value)">
                            <option value="relevance" <?= $sort_by == 'relevance' ? 'selected' : '' ?>>Mais Relevantes</option>
                            <option value="price_asc" <?= $sort_by == 'price_asc' ? 'selected' : '' ?>>Menor Preço</option>
                            <option value="price_desc" <?= $sort_by == 'price_desc' ? 'selected' : '' ?>>Maior Preço</option>
                            <option value="best_sellers" <?= $sort_by == 'best_sellers' ? 'selected' : '' ?>>Mais Vendidos</option>
                            <option value="best_rated" <?= $sort_by == 'best_rated' ? 'selected' : '' ?>>Melhor Avaliados</option>
                            <option value="newest" <?= $sort_by == 'newest' ? 'selected' : '' ?>>Mais Recentes</option>
                            <option value="name_asc" <?= $sort_by == 'name_asc' ? 'selected' : '' ?>>A-Z</option>
                            <option value="name_desc" <?= $sort_by == 'name_desc' ? 'selected' : '' ?>>Z-A</option>
                        </select>
                    </div>
                </div>

                <!-- Active Filters -->
                <?php if (!empty($selected_categories) || $min_price > 0 || $max_price < 100000 || !empty($search_query) || !empty($eco_badges) || $min_rating > 0): ?>
                <div class="active-filters">
                    <span style="font-size: 13px; color: var(--gray-600); font-weight: 600;">Filtros ativos:</span>
                    
                    <?php if (!empty($search_query)): ?>
                        <div class="filter-pill">
                            Busca: "<?= htmlspecialchars($search_query) ?>"
                            <i class="fa-solid fa-xmark" onclick="removeSearchFilter()"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($selected_categories)): 
                        foreach ($selected_categories as $cat_id):
                            $cat_name = null;
                            foreach ($all_categories as $cat) {
                                if ($cat['id'] == $cat_id) {
                                    $cat_name = $cat['name'];
                                    break;
                                }
                                foreach ($cat['subcategories'] as $subcat) {
                                    if ($subcat['id'] == $cat_id) {
                                        $cat_name = $subcat['name'];
                                        break 2;
                                    }
                                }
                            }
                            if ($cat_name):
                    ?>
                        <div class="filter-pill">
                            <?= htmlspecialchars($cat_name) ?>
                            <i class="fa-solid fa-xmark" onclick="removeCategoryFilter(<?= $cat_id ?>)"></i>
                        </div>
                    <?php 
                            endif;
                        endforeach;
                    endif; ?>
                    
                    <?php if ($min_price > 0 || $max_price < 100000): ?>
                        <div class="filter-pill">
                            Preço: MZN <?= number_format($min_price) ?> - <?= number_format($max_price) ?>
                            <i class="fa-solid fa-xmark" onclick="clearPriceFilter(); document.getElementById('filterForm').submit();"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($eco_badges)): 
                        foreach ($eco_badges as $badge):
                            if (isset($eco_badges_available[$badge])):
                    ?>
                        <div class="filter-pill">
                            <i class="fa-solid fa-<?= $eco_badges_available[$badge]['icon'] ?>"></i>
                            <?= $eco_badges_available[$badge]['label'] ?>
                            <i class="fa-solid fa-xmark" onclick="removeEcoBadge('<?= $badge ?>')"></i>
                        </div>
                    <?php 
                            endif;
                        endforeach;
                    endif; ?>
                    
                    <?php if ($min_rating > 0): ?>
                        <div class="filter-pill">
                            Avaliação: <?= $min_rating ?>+ estrelas
                            <i class="fa-solid fa-xmark" onclick="clearRating(); document.getElementById('filterForm').submit();"></i>
                        </div>
                    <?php endif; ?>
                    
                    <a href="marketplace.php" class="clear-all-filters">Limpar todos</a>
                </div>
                <?php endif; ?>

                <!-- Products Grid -->
                <div class="products-grid">
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-box-open"></i>
                            <h3>Nenhum produto encontrado</h3>
                            <p>Tente ajustar seus filtros ou fazer uma nova busca.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): 
                            $badge = getProductBadge($product);
                            $product_url = "product.php?id=" . $product['id'];
                            $image_path = !empty($product['imagem']) ? $product['imagem'] : 'placeholder.jpg';
                        ?>
                            <div class="product-card" onclick="window.location.href='<?= $product_url ?>'">
                                <?php if ($badge): ?>
                                    <div class="product-badge <?= $badge[0] ?>"><?= $badge[1] ?></div>
                                <?php endif; ?>
                                
                                <div class="product-actions">
                                    <button class="action-btn <?= in_array($product['id'], $user_favorites) ? 'favorited' : '' ?>" 
                                            title="Favoritar" 
                                            onclick="toggleFavorite(event, <?= $product['id'] ?>)">
                                        <i class="fa-<?= in_array($product['id'], $user_favorites) ? 'solid' : 'regular' ?> fa-heart"></i>
                                    </button>
                                    <button class="action-btn" title="Visualização Rápida" onclick="quickView(event, <?= $product['id'] ?>)">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                                
                                <div class="product-image-container">
                                    <img src="pages/uploads/products/<?= htmlspecialchars($image_path) ?>" 
                                    alt="<?= htmlspecialchars($product['nome']) ?>" 
                                    class="product-image"
                                    onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($product['fornecedor_nome']) ?>&size=100&background=10b981&color=fff&bold=true&font-size=0.1&length=2'">
                                </div>
                                
                                <div class="product-content">
                                    <div class="product-category-tag">
                                        <?php if (!empty($product['categoria_icon'])): ?>
                                            <i class="fa-solid fa-<?= htmlspecialchars($product['categoria_icon']) ?>"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($product['categoria'] ?? 'Geral') ?>
                                    </div>
                                    
                                    <h3 class="product-title"><?= htmlspecialchars($product['nome']) ?></h3>
                                    
                                    <div class="product-rating">
                                        <?= generateStars($product['avg_rating']) ?>
                                        <span class="rating-count">(<?= number_format($product['total_reviews']) ?>)</span>
                                    </div>
                                    
                                    <?= renderEcoBadges($product['eco_badges']) ?>
                                    
                                    <div class="product-meta">
                                        <span class="meta-item">
                                            <i class="fa-solid fa-box"></i>
                                            <?= $product['stock'] ?> un
                                        </span>
                                        <span class="meta-item">
                                            <i class="fa-solid fa-truck-fast"></i>
                                            <?= number_format($product['total_vendas']) ?> vendas
                                        </span>
                                    </div>
                                    
                                    <div class="supplier-info">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($product['fornecedor_nome']) ?>&size=64&background=00b96b&color=fff&bold=true" 
                                             alt="<?= htmlspecialchars($product['fornecedor_nome']) ?>" 
                                             class="supplier-logo-small">
                                        <span class="supplier-name"><?= htmlspecialchars($product['fornecedor_nome']) ?></span>
                                        <i class="fa-solid fa-circle-check verified-icon" title="Fornecedor Verificado"></i>
                                    </div>
                                    
                                    <div class="product-footer">
                                        <div class="product-price">
                                            <span class="price-label">A partir de</span>
                                            <span class="price-value"><?= formatPrice($product['preco'], $product['currency']) ?></span>
                                            <span class="price-unit">/unidade</span>
                                        </div>
                                        <button class="add-to-cart-btn" onclick="addToCart(event, <?= $product['id'] ?>)">
                                            <i class="fa-solid fa-cart-plus"></i>
                                            Adicionar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <!-- Botão Anterior -->
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>" class="page-btn">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <button class="page-btn" disabled>
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                    <?php endif; ?>
                    
                    <?php
                    // Lógica de paginação: mostrar até 5 páginas visíveis
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    // Sempre mostrar primeira página
                    if ($start_page > 1):
                    ?>
                        <a href="?page=1&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>" 
                        class="page-btn">1</a>
                        <?php if ($start_page > 2): ?>
                            <span style="padding: 0 8px; color: var(--gray-600);">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Páginas do meio -->
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>" 
                        class="page-btn <?= $i == $current_page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Sempre mostrar última página -->
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span style="padding: 0 8px; color: var(--gray-600);">...</span>
                        <?php endif; ?>
                        <a href="?page=<?= $total_pages ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>" 
                        class="page-btn"><?= $total_pages ?></a>
                    <?php endif; ?>
                    
                    <!-- Botão Próximo -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k != 'page', ARRAY_FILTER_USE_KEY)) ?>" 
                        class="page-btn">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="page-btn" disabled>
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.html'; ?>
    <script src="assets/scripts/marketplace.js"> </script>

</body>
</html>