<?php
// ==================== AJAX SEARCH ENDPOINT ====================
// Arquivo: ajax_search.php
// Descrição: Endpoint para busca de produtos via AJAX sem recarregar página

require_once __DIR__ . '/../../../registration/bootstrap.php';
require_once __DIR__ . '/../../../registration/includes/security.php';
require_once __DIR__ . '/../../../registration/includes/db.php';

// Definir header JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar se é requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// ==================== PARÂMETROS DE BUSCA ====================
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 12;
$in_stock_only = isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : false;
$rating_min = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// Validação básica
if (empty($search_term) && $category_id === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Por favor, digite algo para buscar',
        'products' => [],
        'total' => 0
    ]);
    exit;
}

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
    default => 'total_sales DESC, p.created_at DESC'
};

// ==================== QUERY PRINCIPAL ====================
$main_query = "
    SELECT 
        p.id, p.nome, p.descricao, p.preco, p.currency, 
        p.imagem, p.image_path1, p.stock, p.created_at,
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
    LIMIT ?
";

// Adicionar limit
$params[] = $limit;
$types .= 'i';

// ==================== EXECUTAR QUERY ====================
$stmt = $mysqli->prepare($main_query);

if (!$stmt) {
    error_log("Erro AJAX Search: " . $mysqli->error);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar produtos',
        'products' => [],
        'total' => 0
    ]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ==================== CONTAR TOTAL (SEM LIMIT) ====================
$count_query = "
    SELECT COUNT(DISTINCT p.id) as total
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN customer_reviews cr ON p.id = cr.product_id
    WHERE {$where_clause}
    " . ($rating_min > 0 ? "GROUP BY p.id HAVING COALESCE(AVG(cr.rating), 0) >= {$rating_min}" : "");

// Remover último parâmetro (limit)
$count_params = array_slice($params, 0, -1);
$count_types = substr($types, 0, -1);

$count_stmt = $mysqli->prepare($count_query);

if ($count_stmt) {
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
} else {
    $total_results = count($products);
}

// ==================== PROCESSAR PRODUTOS PARA JSON ====================
$processed_products = [];

foreach ($products as $product) {
    // Determinar URL da imagem
    $image_url = '';
    if (!empty($product['imagem'])) {
        $image_url = 'uploads/products/' . htmlspecialchars($product['imagem'], ENT_QUOTES, 'UTF-8');
    } elseif (!empty($product['image_path1'])) {
        $image_url = 'uploads/products/' . htmlspecialchars($product['image_path1'], ENT_QUOTES, 'UTF-8');
    } else {
        $company = urlencode($product['company_name'] ?? 'Produto');
        $image_url = "https://ui-avatars.com/api/?name={$company}&size=400&background=00b96b&color=fff&bold=true&font-size=0.1";
    }
    
    // Badges
    $days_old = $product['days_old'] ?? 999;
    $is_new = $days_old <= 7;
    $is_popular = ($product['total_sales'] ?? 0) >= 50;
    $is_low_stock = $product['stock'] > 0 && $product['stock'] <= 10;
    
    $processed_products[] = [
        'id' => (int)$product['id'],
        'nome' => htmlspecialchars($product['nome'], ENT_QUOTES, 'UTF-8'),
        'descricao' => htmlspecialchars($product['descricao'] ?? '', ENT_QUOTES, 'UTF-8'),
        'preco' => (float)$product['preco'],
        'preco_formatado' => number_format($product['preco'], 2, ',', '.'),
        'currency' => strtoupper($product['currency'] ?? 'MZN'),
        'imagem' => $image_url,
        'stock' => (int)$product['stock'],
        'category_name' => htmlspecialchars($product['category_name'] ?? 'Geral', ENT_QUOTES, 'UTF-8'),
        'category_icon' => htmlspecialchars($product['category_icon'] ?? 'box', ENT_QUOTES, 'UTF-8'),
        'company_name' => htmlspecialchars($product['company_name'] ?? 'Fornecedor', ENT_QUOTES, 'UTF-8'),
        'avg_rating' => round((float)$product['avg_rating'], 1),
        'review_count' => (int)$product['review_count'],
        'total_sales' => (int)$product['total_sales'],
        'days_old' => (int)$days_old,
        'is_new' => $is_new,
        'is_popular' => $is_popular,
        'is_low_stock' => $is_low_stock,
        'url' => 'marketplace.php?product=' . (int)$product['id']
    ];
}

// ==================== RESPOSTA JSON ====================
echo json_encode([
    'success' => true,
    'message' => $total_results > 0 
        ? sprintf('Encontrados %d %s', $total_results, $total_results === 1 ? 'produto' : 'produtos')
        : 'Nenhum produto encontrado',
    'products' => $processed_products,
    'total' => $total_results,
    'showing' => count($processed_products),
    'search_term' => htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8'),
    'filters' => [
        'category_id' => $category_id,
        'min_price' => $min_price,
        'max_price' => $max_price,
        'sort_by' => $sort_by,
        'in_stock_only' => $in_stock_only,
        'rating_min' => $rating_min
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>