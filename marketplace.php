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
    <style>
        :root {
            --primary-green: #00b96b;
            --primary-blue: #0066c0;
            --secondary-green: #059669;
            --secondary-blue: #232f3e;
            --light-green: #f0fdf4;
            --white: #ffffff;
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #eeeeee;
            --gray-300: #e0e0e0;
            --gray-400: #bdbdbd;
            --gray-600: #757575;
            --gray-800: #424242;
            --gray-900: #212121;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 2px 8px rgba(0,0,0,0.15);
            --shadow-lg: 0 4px 12px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
        }

        /* TOP HEADER */
        .top-strip {
            background: var(--secondary-blue);
            color: white;
            font-size: 12px;
            padding: 8px 0;
        }

        .top-strip-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-left-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .top-link {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
        }

        .top-link:hover {
            color: var(--primary-green);
        }

        .top-right-nav {
            display: flex;
            gap: 20px;
            list-style: none;
        }

        .top-right-nav a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: color 0.2s;
        }

        .top-right-nav a:hover {
            color: var(--primary-green);
        }

        /* MAIN HEADER */
        .main-header {
            background: var(--white);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-main {
            max-width: 1600px;
            margin: 0 auto;
            padding: 16px 24px;
            display: grid;
            grid-template-columns: 200px 1fr auto;
            gap: 24px;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-text {
            font-size: 26px;
            font-weight: 900;
            color: var(--secondary-blue);
            letter-spacing: -0.5px;
        }

        .logo-accent {
            color: var(--primary-green);
            font-size: 30px;
            margin-left: 2px;
        }

        /* Search Bar */
        .search-section {
            display: flex;
            gap: 0;
            max-width: 900px;
        }

        /* .search-category {
            background: var(--gray-100);
            border: 1px solid var(--gray-300);
            border-right: none;
            border-radius: 4px 0 0 4px;
            padding: 0 12px;
            font-size: 13px;
            color: var(--gray-800);
            cursor: pointer;
            min-width: 160px;
            font-weight: 500;
        } */

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--primary-green);
            border-left: none;
            border-right: none;
            outline: none;
            font-size: 14px;
            font-family: 'Roboto', sans-serif;
        }

        .search-button {
            background: var(--primary-green);
            border: none;
            padding: 0 24px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            color: white;
            transition: background 0.2s;
        }

        .search-button:hover {
            background: var(--secondary-green);
        }

        .search-button i {
            font-size: 18px;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .header-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray-800);
            font-size: 12px;
            font-weight: 500;
            transition: color 0.2s;
            position: relative;
            padding: 4px 8px;
        }

        .header-action:hover {
            color: var(--primary-green);
        }

        .action-icon {
            font-size: 26px;
            margin-bottom: 2px;
        }

        .cart-badge {
            position: absolute;
            top: 0;
            right: 4px;
            background: var(--primary-green);
            color: white;
            font-size: 11px;
            font-weight: 900;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        /* PAGE LAYOUT */
        .page-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
        }

        /* BREADCRUMB */
        .breadcrumb {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--gray-600);
            flex-wrap: wrap;
        }

        .breadcrumb a {
            color: var(--primary-green);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* SIDEBAR FILTERS */
        .filters-sidebar {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
            height: fit-content;
            position: sticky;
            top: 90px;
            max-height: calc(100vh - 110px);
            overflow-y: auto;
        }

        .filters-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .filters-sidebar::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 3px;
        }

        .filters-sidebar::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 3px;
        }

        .filter-section {
            margin-bottom: 28px;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--gray-200);
        }

        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .filter-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .clear-filter {
            font-size: 12px;
            color: var(--primary-green);
            cursor: pointer;
            font-weight: 600;
        }

        .clear-filter:hover {
            text-decoration: underline;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            cursor: pointer;
        }

        .filter-option:last-child {
            margin-bottom: 0;
        }

        .filter-option input[type="checkbox"],
        .filter-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-green);
        }

        .filter-option label {
            font-size: 14px;
            color: var(--gray-800);
            cursor: pointer;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .category-icon {
            font-size: 16px;
            color: var(--primary-green);
            width: 20px;
            text-align: center;
        }

        .subcategory-list {
            margin-left: 28px;
            margin-top: 8px;
        }

        .subcategory-option {
            margin-bottom: 10px;
        }

        .filter-count {
            font-size: 12px;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Price Range */
        .price-inputs {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .price-input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            width: 100%;
        }

        .price-input:focus {
            border-color: var(--primary-green);
        }

        .price-slider {
            width: 100%;
            margin-top: 8px;
            accent-color: var(--primary-green);
        }

        /* Rating Filter */
        .rating-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            margin-bottom: 8px;
        }

        .rating-option:hover {
            background: var(--gray-50);
        }

        .stars {
            display: flex;
            gap: 2px;
            color: #fbbf24;
        }

        /* Eco Badges Filter */
        .eco-badge-filter {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 2px solid var(--gray-200);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 8px;
        }

        .eco-badge-filter:hover {
            border-color: var(--primary-green);
            background: var(--light-green);
        }

        .eco-badge-filter input[type="checkbox"] {
            display: none;
        }

        .eco-badge-filter input[type="checkbox"]:checked + label {
            font-weight: 700;
        }

        .eco-badge-filter.active {
            border-color: var(--primary-green);
            background: var(--light-green);
        }

        .eco-badge-icon {
            font-size: 18px;
        }

        .eco-badge-label {
            font-size: 13px;
            color: var(--gray-800);
            flex: 1;
        }

        /* Apply Filters Button */
        .apply-filters-btn {
            width: 100%;
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .apply-filters-btn:hover {
            background: var(--secondary-green);
        }

        /* MAIN CONTENT AREA */
        .products-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Results Header */
        .results-header {
            background: white;
            padding: 20px 24px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .results-info {
            font-size: 14px;
            color: var(--gray-600);
        }

        .results-info strong {
            color: var(--gray-900);
            font-weight: 700;
        }

        .results-controls {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .view-toggle {
            display: flex;
            gap: 8px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: 4px;
        }

        .view-btn {
            padding: 8px 12px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 4px;
            color: var(--gray-600);
            transition: all 0.2s;
        }

        .view-btn.active {
            background: white;
            color: var(--primary-green);
            box-shadow: var(--shadow-sm);
        }

        .sort-select {
            padding: 10px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            outline: none;
            background: white;
        }

        .sort-select:focus {
            border-color: var(--primary-green);
        }

        /* Active Filters Pills */
        .active-filters {
            background: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--light-green);
            color: var(--primary-green);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .filter-pill i {
            cursor: pointer;
            font-size: 12px;
        }

        .filter-pill i:hover {
            color: #dc2626;
        }

        .clear-all-filters {
            color: var(--primary-green);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
        }

        /* PRODUCTS GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-green);
        }

        .product-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            z-index: 2;
            backdrop-filter: blur(10px);
        }

        .product-badge.hot {
            background: rgba(239, 68, 68, 0.95);
            color: white;
        }

        .product-badge.new {
            background: rgba(59, 130, 246, 0.95);
            color: white;
        }

        .product-badge.eco {
            background: rgba(16, 185, 107, 0.95);
            color: white;
        }

        .product-actions {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 2;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .product-card:hover .product-actions {
            opacity: 1;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            background: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-md);
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: var(--primary-green);
            color: white;
            transform: scale(1.1);
        }

        .product-image-container {
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: var(--gray-100);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-content {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-category-tag {
            font-size: 11px;
            font-weight: 700;
            color: var(--primary-green);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .product-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .stars-display {
            display: flex;
            gap: 2px;
            color: #fbbf24;
        }

        .rating-count {
            color: var(--gray-600);
            font-size: 12px;
        }

        .eco-badges-container {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .eco-badge-mini {
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .eco-badge-mini i {
            font-size: 11px;
        }

        .product-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: var(--gray-600);
        }

        .meta-item i {
            color: var(--primary-green);
        }

        .supplier-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .supplier-logo-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid var(--gray-200);
        }

        .supplier-name {
            font-size: 12px;
            color: var(--gray-700);
            font-weight: 500;
        }

        .verified-icon {
            color: var(--primary-green);
            font-size: 14px;
        }

        .product-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .product-price {
            display: flex;
            flex-direction: column;
        }

        .price-label {
            font-size: 10px;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }

        .price-value {
            font-size: 22px;
            font-weight: 900;
            color: var(--primary-green);
            line-height: 1;
            margin: 4px 0;
        }

        .price-unit {
            font-size: 11px;
            color: var(--gray-600);
        }

        .add-to-cart-btn {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .add-to-cart-btn:hover {
            background: var(--secondary-green);
            transform: translateY(-2px);
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }

        .page-btn {
            min-width: 40px;
            height: 40px;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-btn:hover {
            border-color: var(--primary-green);
            color: var(--primary-green);
        }

        .page-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--gray-800);
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .page-layout {
                grid-template-columns: 280px 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-layout {
                grid-template-columns: 1fr;
            }

            .filters-sidebar {
                display: none;
            }

            .search-category {
                display: none;
            }

            .header-main {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .results-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .top-left-info {
                display: none;
            }

            .breadcrumb {
                font-size: 11px;
            }
        }

        
        /* ========================================
        DASHBOARD FOOTER - ESTILO MODERNO
        ======================================== */

        .dashboard-footer {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a7b 50%, #1a5c4e 100%);
            color: white;
            border-top: 4px solid var(--primary-green);
        }

        .footer-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ========================================
        FOOTER TOP - COLUNAS
        ======================================== */

        .footer-top {
            display: grid;
            grid-template-columns: 2fr repeat(4, 1fr);
            gap: 40px;
            padding: 60px 0 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Coluna de Brand */
        .footer-brand {
            padding-right: 20px;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 28px;
        }

        .footer-logo i {
            color: var(--primary-green);
            font-size: 32px;
        }

        .footer-logo-text {
            font-weight: 900;
            font-size: 24px;
            letter-spacing: 0.5px;
        }

        .logo-highlight {
            color: var(--primary-green);
        }

        .footer-description {
            font-size: 14px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
        }

        /* Social Links */
        .footer-social {
            display: flex;
            gap: 12px;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .social-link:hover {
            background: var(--primary-green);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 185, 107, 0.4);
        }

        /* Colunas de Links */
        .footer-column {
            display: flex;
            flex-direction: column;
        }

        .footer-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--primary-green);
            border-radius: 2px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-block;
        }

        .footer-links a:hover {
            color: var(--primary-green);
            transform: translateX(5px);
        }

        /* Newsletter */
        .footer-newsletter {
            background: rgba(255, 255, 255, 0.05);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .newsletter-description {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .newsletter-form {
            margin-bottom: 20px;
        }

        .input-group {
            display: flex;
            gap: 8px;
        }

        .newsletter-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }

        .newsletter-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .newsletter-input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-green);
        }

        .newsletter-btn {
            padding: 12px 20px;
            background: var(--primary-green);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .newsletter-btn:hover {
            background: var(--secondary-green);
            transform: scale(1.05);
        }

        /* Footer Badges */
        .footer-badges {
            display: flex;
            gap: 16px;
        }

        .badge-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
        }

        .badge-item i {
            color: var(--primary-green);
            font-size: 16px;
        }

        /* ========================================
        FOOTER BOTTOM - COPYRIGHT
        ======================================== */

        .footer-bottom {
            padding: 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-bottom-left,
        .footer-bottom-center,
        .footer-bottom-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .copyright,
        .powered-by {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
        }

        .footer-legal-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s ease;
        }

        .footer-legal-link:hover {
            color: var(--primary-green);
        }

        .separator {
            color: rgba(255, 255, 255, 0.3);
            font-size: 12px;
        }

        .powered-by strong {
            color: var(--primary-green);
        }

        /* ========================================
        RESPONSIVE
        ======================================== */

        @media (max-width: 1200px) {
            .footer-top {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .footer-brand {
                grid-column: 1 / -1;
                margin-bottom: 20px;
            }
        }

        @media (max-width: 768px) {
            .footer-top {
                grid-template-columns: 1fr;
                gap: 32px;
                padding: 40px 0 30px;
            }
            
            .footer-bottom {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
            
            .footer-bottom-center {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .footer-newsletter {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .input-group {
                flex-direction: column;
            }
            
            .newsletter-btn {
                width: 100%;
            }
            
            .footer-social {
                justify-content: center;
            }
        }

        /* ========================================
        SCROLL TO TOP BUTTON (OPCIONAL)
        ======================================== */

        .scroll-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 999;
        }

        .scroll-to-top:hover {
            background: var(--secondary-green);
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .scroll-to-top.visible {
            display: flex;
        }


 /* Search Category Select - 200px */
.search-category {
    background: var(--gray-100);
    border: 1px solid var(--gray-300);
    border-right: none;
    border-radius: 4px 0 0 4px;
    padding: 0 16px;
    font-size: 14px;
    color: var(--gray-800);
    cursor: pointer;
    width: 200px; /* FIXO em 200px */
    min-width: 200px;
    max-width: 200px;
    height: 48px;
    font-weight: 500;
}

/* ========================================
   SELECT2 - SELECT 200px / DROPDOWN 400px
======================================== */

.select2-container {
    font-size: 14px !important;
    width: 200px !important; /* SELECT fixo em 200px */
    min-width: 200px !important;
    max-width: 200px !important;
}

.select2-container--default .select2-selection--single {
    height: 48px !important;
    border: 1px solid var(--gray-300) !important;
    border-right: none !important;
    border-radius: 4px 0 0 4px !important;
    width: 200px !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    padding: 14px 16px !important;
    line-height: 20px !important;
    color: var(--gray-800) !important;
    font-size: 14px !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 48px !important;
    right: 10px !important;
    top: 0 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow b {
    margin-top: -2px !important;
}

/* Ícones nas opções */
.select2-results__option i,
.select2-selection__rendered i {
    margin-right: 10px;
    color: var(--primary-green);
    min-width: 20px;
    font-size: 16px;
    text-align: center;
}

/* Opções do dropdown */
.select2-results__option {
    padding: 12px 16px !important;
    font-size: 14px !important;
    line-height: 1.5 !important;
}

/* Subcategorias indentadas */
.select2-results__option.subcategory {
    padding-left: 45px !important;
    background: #f9fafb;
    font-size: 13px !important;
}

.select2-results__option.subcategory i {
    color: var(--secondary-green);
    font-size: 14px;
}

/* Hover states */
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: var(--light-green) !important;
    color: var(--gray-900) !important;
}

.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: var(--primary-green) !important;
    color: white !important;
}

.select2-container--default .select2-results__option[aria-selected=true] i {
    color: white !important;
}

/* DROPDOWN - 400px de largura */
.select2-dropdown {
    border: 1px solid var(--primary-green) !important;
    border-radius: 4px !important;
    box-shadow: 0 4px 12px rgba(0, 185, 107, 0.15) !important;
    font-size: 14px !important;
    width: 400px !important; /* DROPDOWN fixo em 400px */
    min-width: 400px !important;
    max-width: 400px !important;
}

.select2-search--dropdown {
    padding: 10px !important;
}

.select2-search__field {
    border: 1px solid var(--gray-300) !important;
    border-radius: 4px !important;
    padding: 10px 14px !important;
    outline: none !important;
    font-size: 14px !important;
    height: 40px !important;
    width: 100% !important;
}

.select2-search__field:focus {
    border-color: var(--primary-green) !important;
}

/* Container de resultados */
.select2-results__options {
    max-height: 400px !important;
}

/* Loading state */
.select2-results__option--loading {
    padding: 12px 16px !important;
}

/* Message when no results */
.select2-results__message {
    padding: 12px 16px !important;
    font-size: 14px !important;
}

.action-btn.favorited {
    background: #fef2f2;
    color: #ef4444;
}

.action-btn.favorited:hover {
    background: #ef4444;
    color: white;
}
    </style>
</head>
<body>

    <!-- Top Strip -->
    <div class="top-strip">
        <div class="top-strip-content">
            <div class="top-left-info">
                <a href="#" class="top-link">
                    <i class="fa-solid fa-location-dot"></i>
                    Entregar em <span class="location-now"></span>
                </a>
            </div>
            <ul class="top-right-nav">
                <li><a href="#">Central de Ajuda</a></li>
                <li><a href="#">Rastrear Pedido</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="header-main">
            <a href="index.php" class="logo-container">
                <div class="logo-text">
                    VSG<span class="logo-accent">•</span>
                </div>
            </a>

            <form action="marketplace.php" method="GET" class="search-section">
                <select class="search-category" name="category" id="searchCategorySelect">
                    <option value="" data-icon="">Todas Categorias</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" 
                                data-icon="<?= htmlspecialchars($cat['icon']) ?>"
                                <?= $selected_category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                        </option>
                        <?php if (!empty($cat['subcategories'])): ?>
                            <?php foreach ($cat['subcategories'] as $subcat): ?>
                                <option value="<?= $subcat['id'] ?>" 
                                        data-icon="<?= htmlspecialchars($subcat['icon']) ?>"
                                        <?= $selected_category_id == $subcat['id'] ? 'selected' : '' ?>>
                                    &nbsp;&nbsp;↳ <?= htmlspecialchars($subcat['name']) ?> (<?= $subcat['product_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        class="search-input" 
                        name="q"
                        value="<?= htmlspecialchars($search_query) ?>"
                        placeholder="Buscar produtos sustentáveis..."
                    >
                </div>
                <button type="submit" class="search-button">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>

            <div class="header-actions">
                <a href="<?= $user_logged_in ? 'pages/person/index.php?page=favoritos' : 'registration/login/login.php' ?>" class="header-action">
                    <i class="fa-solid fa-heart action-icon"></i>
                    <span>Favoritos</span>
                </a>

                <a href="<?= $user_logged_in ? 'pages/person/index.php?page=carrinho' : 'registration/login/login.php' ?>" class="header-action">
                    <i class="fa-solid fa-cart-shopping action-icon"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                    <span>Carrinho</span>
                </a>

                <?php if ($user_logged_in): ?>
                    <a href="pages/person/index.php" class="header-action" target="_blank">
                        <img src="<?= htmlspecialchars($displayAvatar) ?>" 
                             alt="<?= htmlspecialchars($displayName) ?>" 
                             style="width: 32px; height: 32px; border-radius: 50%; margin-bottom: 4px;">
                        <span><?= htmlspecialchars($displayName) ?></span>
                    </a>
                <?php else: ?>
                    <a href="registration/login/login.php" class="header-action">
                        <i class="fa-solid fa-user action-icon"></i>
                        <span>Entrar</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

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

            <!-- Filters Sidebar -->
            <aside class="filters-sidebar">
                <form id="filterForm" method="GET" action="marketplace.php">
                    <!-- Categorias Principais -->
                    <div class="filter-section">
                        <div class="filter-title">
                            <span><i class="fa-solid fa-layer-group"></i> Categorias</span>
                            <?php if (!empty($selected_categories)): ?>
                                <span class="clear-filter" onclick="clearCategoryFilters()">Limpar</span>
                            <?php endif; ?>
                        </div>
                        <?php foreach (array_slice($all_categories, 0, 8) as $category): ?>
                            <div class="filter-option">
                                <input type="checkbox" 
                                       name="categories[]" 
                                       id="cat<?= $category['id'] ?>"
                                       value="<?= $category['id'] ?>"
                                       <?= in_array($category['id'], $selected_categories) ? 'checked' : '' ?>
                                       onchange="toggleSubcategories(<?= $category['id'] ?>)">
                                <label for="cat<?= $category['id'] ?>">
                                    <i class="fa-solid fa-<?= htmlspecialchars($category['icon']) ?> category-icon"></i>
                                    <?= htmlspecialchars($category['name']) ?>
                                </label>
                                <span class="filter-count"><?= number_format($category['product_count']) ?></span>
                            </div>
                            
                            <?php if (!empty($category['subcategories']) && in_array($category['id'], $selected_categories)): ?>
                                <div class="subcategory-list" id="subcat-<?= $category['id'] ?>">
                                    <?php foreach ($category['subcategories'] as $subcat): ?>
                                        <div class="filter-option subcategory-option">
                                            <input type="checkbox" 
                                                   name="categories[]" 
                                                   id="cat<?= $subcat['id'] ?>"
                                                   value="<?= $subcat['id'] ?>"
                                                   <?= in_array($subcat['id'], $selected_categories) ? 'checked' : '' ?>>
                                            <label for="cat<?= $subcat['id'] ?>">
                                                <?= htmlspecialchars($subcat['name']) ?>
                                            </label>
                                            <span class="filter-count"><?= number_format($subcat['product_count']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Faixa de Preço -->
                    <div class="filter-section">
                        <div class="filter-title">
                            <span><i class="fa-solid fa-money-bill-wave"></i> Faixa de Preço</span>
                            <span class="clear-filter" onclick="clearPriceFilter()">Limpar</span>
                        </div>
                        <div class="price-inputs">
                            <input type="number" 
                                   class="price-input" 
                                   name="min_price" 
                                   placeholder="Mín" 
                                   value="<?= $min_price > 0 ? $min_price : '' ?>">
                            <input type="number" 
                                   class="price-input" 
                                   name="max_price" 
                                   placeholder="Máx" 
                                   value="<?= $max_price < 100000 ? $max_price : '' ?>">
                        </div>
                        <input type="range" 
                               class="price-slider" 
                               min="0" 
                               max="10000" 
                               step="100"
                               value="<?= $max_price < 100000 ? $max_price : 10000 ?>"
                               oninput="updateMaxPrice(this.value)">
                        <div style="font-size: 12px; color: var(--gray-600); margin-top: 8px; text-align: center;">
                            até MZN <span id="maxPriceDisplay"><?= number_format($max_price < 100000 ? $max_price : 10000) ?></span>
                        </div>
                    </div>

                    <!-- Eco Badges -->
                    <div class="filter-section">
                        <div class="filter-title">
                            <span><i class="fa-solid fa-leaf"></i> Atributos Ecológicos</span>
                            <?php if (!empty($eco_badges)): ?>
                                <span class="clear-filter" onclick="clearEcoBadges()">Limpar</span>
                            <?php endif; ?>
                        </div>
                        <?php foreach (array_slice($eco_badges_available, 0, 6, true) as $badge_key => $badge_info): ?>
                            <div class="eco-badge-filter <?= in_array($badge_key, $eco_badges) ? 'active' : '' ?>" 
                                 onclick="toggleEcoBadge('<?= $badge_key ?>')">
                                <input type="checkbox" 
                                       name="eco_badges[]" 
                                       id="eco<?= $badge_key ?>"
                                       value="<?= $badge_key ?>"
                                       <?= in_array($badge_key, $eco_badges) ? 'checked' : '' ?>>
                                <label for="eco<?= $badge_key ?>" style="display: contents;">
                                    <i class="fa-solid fa-<?= $badge_info['icon'] ?> eco-badge-icon" 
                                       style="color: <?= $badge_info['color'] ?>;"></i>
                                    <span class="eco-badge-label"><?= $badge_info['label'] ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Avaliação -->
                    <div class="filter-section">
                        <div class="filter-title">
                            <span><i class="fa-solid fa-star"></i> Avaliação</span>
                            <?php if ($min_rating > 0): ?>
                                <span class="clear-filter" onclick="clearRating()">Limpar</span>
                            <?php endif; ?>
                        </div>
                        <div class="rating-option">
                            <input type="radio" name="min_rating" id="rating5" value="5" 
                                   <?= $min_rating == 5 ? 'checked' : '' ?>>
                            <label for="rating5" style="display: contents;">
                                <div class="stars">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                </div>
                                <span style="font-size: 13px; color: var(--gray-600);">e acima</span>
                            </label>
                        </div>
                        <div class="rating-option">
                            <input type="radio" name="min_rating" id="rating4" value="4" 
                                   <?= $min_rating == 4 ? 'checked' : '' ?>>
                            <label for="rating4" style="display: contents;">
                                <div class="stars">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-regular fa-star"></i>
                                </div>
                                <span style="font-size: 13px; color: var(--gray-600);">e acima</span>
                            </label>
                        </div>
                        <div class="rating-option">
                            <input type="radio" name="min_rating" id="rating3" value="3" 
                                   <?= $min_rating == 3 ? 'checked' : '' ?>>
                            <label for="rating3" style="display: contents;">
                                <div class="stars">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-regular fa-star"></i>
                                    <i class="fa-regular fa-star"></i>
                                </div>
                                <span style="font-size: 13px; color: var(--gray-600);">e acima</span>
                            </label>
                        </div>
                    </div>

                    <input type="hidden" name="q" value="<?= htmlspecialchars($search_query) ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">
                    
                    <button type="submit" class="apply-filters-btn">
                        <i class="fa-solid fa-filter"></i>
                        Aplicar Filtros
                    </button>
                </form>
            </aside>

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

    <script>
        // Add to cart functionality
        function addToCart(event, productId) {
            event.stopPropagation();
            const btn = event.currentTarget;
            const originalHTML = btn.innerHTML;
            
            fetch('ajax/add-to-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, quantity: 1 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado!';
                    btn.style.background = 'var(--secondary-green)';
                    
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.cart_count;
                    } else if (data.cart_count > 0) {
                        const cartAction = document.querySelector('.header-action .fa-cart-shopping').parentElement;
                        const badge = document.createElement('span');
                        badge.className = 'cart-badge';
                        badge.textContent = data.cart_count;
                        cartAction.appendChild(badge);
                    }
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.style.background = 'var(--primary-green)';
                    }, 2000);
                } else {
                    alert(data.message || 'Erro ao adicionar ao carrinho');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Por favor, faça login para adicionar produtos ao carrinho.');
            });
        }

        // Favorite toggle
        // Favorite toggle - ATUALIZADO
        function toggleFavorite(event, productId) {
            event.stopPropagation();
            const btn = event.currentTarget;
            const icon = btn.querySelector('i');
            
            fetch('pages/person/ajax/toggle-favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.favorited) {
                        icon.classList.remove('fa-regular');
                        icon.classList.add('fa-solid');
                        btn.classList.add('favorited');
                    } else {
                        icon.classList.remove('fa-solid');
                        icon.classList.add('fa-regular');
                        btn.classList.remove('favorited');
                    }
                    
                    // Mostrar toast de sucesso
                    showToast(data.message, 'success');
                } else {
                    if (data.message.includes('login')) {
                        if (confirm('Você precisa fazer login. Deseja ir para a página de login?')) {
                            window.location.href = 'registration/login/login.php';
                        }
                    } else {
                        showToast(data.message, 'error');
                    }
                }
            })
            .catch(() => {
                if (confirm('Você precisa fazer login. Deseja ir para a página de login?')) {
                    window.location.href = 'registration/login/login.php';
                }
            });
        }

        // Toast notification simples
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            toast.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                animation: slideIn 0.3s ease-out;
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Adicionar animações CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(400px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Quick view
        function quickView(event, productId) {
            event.stopPropagation();
            window.open('product.php?id=' + productId, '_blank');
        }

        // Update sort
        function updateSort(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        // Price filter functions
        function clearPriceFilter() {
            document.querySelector('input[name="min_price"]').value = '';
            document.querySelector('input[name="max_price"]').value = '';
            document.querySelector('.price-slider').value = '10000';
            document.getElementById('maxPriceDisplay').textContent = '10,000';
        }

        function updateMaxPrice(value) {
            document.querySelector('input[name="max_price"]').value = value;
            document.getElementById('maxPriceDisplay').textContent = parseInt(value).toLocaleString();
        }

        // Category filter functions
        function clearCategoryFilters() {
            document.querySelectorAll('input[name="categories[]"]').forEach(cb => cb.checked = false);
            document.getElementById('filterForm').submit();
        }

        function removeCategoryFilter(catId) {
            const checkbox = document.querySelector(`input[name="categories[]"][value="${catId}"]`);
            if (checkbox) {
                checkbox.checked = false;
                document.getElementById('filterForm').submit();
            }
        }

        function toggleSubcategories(catId) {
            const subcatDiv = document.getElementById('subcat-' + catId);
            if (subcatDiv) {
                subcatDiv.style.display = subcatDiv.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Eco badges functions
        function clearEcoBadges() {
            document.querySelectorAll('input[name="eco_badges[]"]').forEach(cb => cb.checked = false);
            document.querySelectorAll('.eco-badge-filter').forEach(div => div.classList.remove('active'));
            document.getElementById('filterForm').submit();
        }

        function toggleEcoBadge(badgeKey) {
            const checkbox = document.getElementById('eco' + badgeKey);
            checkbox.checked = !checkbox.checked;
            event.currentTarget.classList.toggle('active');
        }

        function removeEcoBadge(badge) {
            const checkbox = document.querySelector(`input[name="eco_badges[]"][value="${badge}"]`);
            if (checkbox) {
                checkbox.checked = false;
                document.getElementById('filterForm').submit();
            }
        }

        // Rating functions
        function clearRating() {
            document.querySelectorAll('input[name="min_rating"]').forEach(rb => rb.checked = false);
        }

        // Search filter
        function removeSearchFilter() {
            const url = new URL(window.location.href);
            url.searchParams.delete('q');
            window.location.href = url.toString();
        }

        // View toggle
        const viewBtns = document.querySelectorAll('.view-btn');
        viewBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                viewBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    </script>
<script>
$(document).ready(function() {
    // Inicializar Select2 - SELECT 200px / DROPDOWN 400px
    $('#searchCategorySelect').select2({
        templateResult: formatCategoryOption,
        templateSelection: formatCategorySelection,
        minimumResultsForSearch: 5,
        width: '200px', // SELECT de 200px
        dropdownCssClass: 'select2-dropdown-400', // Classe customizada para dropdown
        placeholder: 'Todas Categorias',
        language: {
            noResults: function() {
                return "Nenhuma categoria encontrada";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });

    // Formatar opções no dropdown (com ícone)
    function formatCategoryOption(option) {
        if (!option.id) {
            return option.text;
        }

        var icon = $(option.element).data('icon');
        var text = option.text;
        var isSubcategory = text.trim().startsWith('↳');

        if (!icon) {
            return $('<span style="font-size: 14px;">' + text + '</span>');
        }

        var $option = $(
            '<span style="display: flex; align-items: center; gap: 10px;">' +
                '<i class="fa-solid fa-' + icon + '" style="font-size: 16px;"></i> ' +
                '<span style="font-size: 14px;">' + text + '</span>' +
            '</span>'
        );

        if (isSubcategory) {
            $option.addClass('subcategory');
        }

        return $option;
    }

    // Formatar seleção (o que aparece no select quando fechado)
    function formatCategorySelection(option) {
        if (!option.id) {
            return $('<span style="font-size: 14px;">' + option.text + '</span>');
        }

        var icon = $(option.element).data('icon');
        var text = option.text.replace('↳', '').trim();

        if (!icon) {
            return $('<span style="font-size: 14px;">' + text + '</span>');
        }

        return $(
            '<span style="display: flex; align-items: center; gap: 10px;">' +
                '<i class="fa-solid fa-' + icon + '" style="font-size: 16px; color: var(--primary-green);"></i> ' +
                '<span style="font-size: 14px; font-weight: 500;">' + text + '</span>' +
            '</span>'
        );
    }

    // Submit do formulário ao mudar categoria
    $('#searchCategorySelect').on('change', function() {
        $(this).closest('form').submit();
    });
});
</script>

</body>
</html>