<?php
/**
 * ================================================================================
 * VISIONGREEN - ACTION: BUSCAR PRODUTOS
 * Arquivo: company/modules/produtos/actions/search_products.php
 * Descrição: Retorna produtos filtrados via AJAX
 * ================================================================================
 */

header('Content-Type: application/json');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

// Conectar ao banco
$db_paths = [
    __DIR__ . '/../../../../../registration/includes/db.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/registration/includes/db.php'
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
    echo json_encode(['success' => false, 'message' => 'Erro de conexão']);
    exit;
}

// Parâmetros de busca
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Construir query
$sql = "SELECT * FROM products WHERE user_id = ?";
$params = [$userId];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($status === 'active') {
    $sql .= " AND is_active = 1";
} elseif ($status === 'inactive') {
    $sql .= " AND is_active = 0";
}

$sql .= " ORDER BY created_at DESC";

// Executar query
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Estatísticas
$stats_stmt = $mysqli->prepare("
    SELECT 
        COALESCE(COUNT(*), 0) as total,
        COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
        COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as inactive,
        COALESCE(SUM(CASE WHEN is_recurring = 1 THEN 1 ELSE 0 END), 0) as recurring
    FROM products
    WHERE user_id = ?
");
$stats_stmt->bind_param("i", $userId);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'products' => $products,
    'stats' => $stats
]);