<?php
header('Content-Type: application/json');
require_once '../../../registration/includes/db.php';
require_once '../../../registration/includes/security.php';

// Iniciar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado'
    ]);
    exit;
}

try {
    // Pegar parâmetros de filtro
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $categories = isset($_GET['categories']) ? explode(',', $_GET['categories']) : [];
    $priceRange = isset($_GET['price_range']) ? $_GET['price_range'] : '';
    $inStock = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';
    
    // Filtrar categorias vazias
    $categories = array_filter($categories);
    
    // Construir query base
    $sql = "SELECT 
                p.id,
                p.nome,
                p.descricao,
                p.imagem,
                p.categoria,
                p.preco,
                p.currency,
                p.stock,
                p.stock_minimo,
                p.status,
                p.visualizacoes,
                u.nome AS empresa_nome,
                u.public_id AS empresa_id
            FROM products p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.deleted_at IS NULL 
              AND p.status = 'ativo'
              AND u.status = 'active'";
    
    $params = [];
    $types = '';
    
    // Filtro de busca
    if (!empty($search)) {
        $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    // Filtro de categorias
    if (!empty($categories)) {
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';
        $sql .= " AND p.categoria IN ($placeholders)";
        foreach ($categories as $cat) {
            $params[] = $cat;
            $types .= 's';
        }
    }
    
    // Filtro de faixa de preço
    if (!empty($priceRange) && strpos($priceRange, '-') !== false) {
        list($minPrice, $maxPrice) = explode('-', $priceRange);
        $sql .= " AND p.preco BETWEEN ? AND ?";
        $params[] = (float)$minPrice;
        $params[] = (float)$maxPrice;
        $types .= 'dd';
    }
    
    // Filtro de estoque
    if ($inStock) {
        $sql .= " AND p.stock > 0";
    }
    
    // Ordenar por produtos mais recentes
    $sql .= " ORDER BY p.created_at DESC LIMIT 50";
    
    // Preparar e executar query
    $stmt = $mysqli->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Formatar dados do produto
        $products[] = [
            'id' => (int)$row['id'],
            'nome' => $row['nome'],
            'descricao' => $row['descricao'],
            'imagem' => $row['imagem'],
            'categoria' => $row['categoria'],
            'preco' => number_format((float)$row['preco'], 2, '.', ''),
            'currency' => $row['currency'] ?? 'MZN',
            'stock' => (int)$row['stock'],
            'stock_minimo' => (int)$row['stock_minimo'],
            'status' => $row['status'],
            'visualizacoes' => (int)$row['visualizacoes'],
            'empresa_nome' => $row['empresa_nome'],
            'empresa_id' => $row['empresa_id']
        ];
    }
    
    $stmt->close();
    
    // Retornar resposta
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => count($products),
        'filters' => [
            'search' => $search,
            'categories' => $categories,
            'price_range' => $priceRange,
            'in_stock' => $inStock
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro em get_products.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar produtos',
        'message' => $e->getMessage()
    ]);
}
?>