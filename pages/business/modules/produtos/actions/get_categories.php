<?php
header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

try {
    $stmt = $mysqli->prepare("
        SELECT id, name, slug, icon, parent_id 
        FROM categories 
        WHERE status = 'ativa' 
        ORDER BY parent_id IS NULL DESC, name ASC
    ");
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $mysqli->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro em get_categories.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar categorias: ' . $e->getMessage()
    ]);
}