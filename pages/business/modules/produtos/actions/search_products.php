<?php
/**
 * search_products.php - Busca de produtos
 * CORRIGIDO 100% para estrutura SQL real da tabela products
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tentar múltiplos caminhos possíveis
$db_paths = [
    __DIR__ . '/../../../../../registration/includes/db.php',
    __DIR__ . '/../../../../registration/includes/db.php',
    dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/registration/includes/db.php'
];

$db_connected = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected || !isset($mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco de dados']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = $isEmployee ? (int)$_SESSION['employee_auth']['empresa_id'] : (int)$_SESSION['auth']['user_id'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

try {
    // Verificar se mysqli está disponível
    if (!isset($mysqli) || !$mysqli) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }
    
    $sql = "
        SELECT 
            p.id,
            p.nome,
            p.descricao,
            p.imagem,
            p.image_path1,
            p.image_path2,
            p.image_path3,
            p.image_path4,
            p.categoria,
            p.preco,
            p.currency,
            p.stock,
            p.stock_minimo,
            p.status,
            p.visualizacoes,
            p.created_at,
            u.nome AS company_name
        FROM products p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ? AND p.deleted_at IS NULL
    ";
    
    $params = [$userId];
    $types = 'i';
    
    if (!empty($search)) {
        $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    if (!empty($category)) {
        $sql .= " AND p.categoria = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    if (!empty($status)) {
        $sql .= " AND p.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $mysqli->error);
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception('Erro ao obter resultados: ' . $stmt->error);
    }
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $stmt->close();
    
    $total = count($products);
    $active = count(array_filter($products, fn($p) => $p['status'] === 'ativo'));
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'stats' => [
            'total' => $total,
            'active' => $active
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro em search_products.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar produtos: ' . $e->getMessage()
    ]);
}