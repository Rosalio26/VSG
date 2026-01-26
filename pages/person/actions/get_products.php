<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

require_once '../../../registration/includes/db.php';
require_once '../../../registration/includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado'
    ]);
    exit;
}

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $categories = isset($_GET['categories']) ? array_filter(explode(',', $_GET['categories'])) : [];
    $priceRange = isset($_GET['price_range']) ? trim($_GET['price_range']) : '';
    $inStock = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';
    
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
                p.created_at,
                u.nome AS empresa_nome,
                u.public_id AS empresa_id
            FROM products p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.deleted_at IS NULL 
              AND p.status = 'ativo'
              AND u.status = 'active'";
    
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    if (!empty($categories)) {
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';
        $sql .= " AND p.categoria IN ($placeholders)";
        foreach ($categories as $cat) {
            $params[] = $cat;
            $types .= 's';
        }
    }
    
    if (!empty($priceRange) && strpos($priceRange, '-') !== false) {
        list($minPrice, $maxPrice) = explode('-', $priceRange);
        $sql .= " AND p.preco BETWEEN ? AND ?";
        $params[] = (float)$minPrice;
        $params[] = (float)$maxPrice;
        $types .= 'dd';
    }
    
    if ($inStock) {
        $sql .= " AND p.stock > 0";
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT 50";
    
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $mysqli->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
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
            'empresa_id' => $row['empresa_id'],
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => count($products)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro em get_products.php: " . $e->getMessage());
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar produtos'
    ]);
}
?>