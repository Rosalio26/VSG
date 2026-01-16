<?php
/**
 * ================================================================================
 * VISIONGREEN - ACTION: EDITAR PRODUTO
 * Arquivo: company/modules/produtos/actions/editar_produto.php
 * ================================================================================
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

try {
    if (empty($_POST['id']) || empty($_POST['name']) || !isset($_POST['price'])) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        exit;
    }
    
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $currency = $_POST['currency'] ?? 'MZN';
    $is_recurring = isset($_POST['is_recurring']) && $_POST['is_recurring'] === '1' ? 1 : 0;
    $billing_cycle = $_POST['billing_cycle'] ?? 'one_time';
    $stock_quantity = !empty($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : NULL;
    $is_active = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
    
    // Atualizar apenas produtos da própria empresa
    $stmt = $mysqli->prepare("
        UPDATE products 
        SET name = ?, description = ?, category = ?, price = ?, currency = ?, 
            is_recurring = ?, billing_cycle = ?, stock_quantity = ?, is_active = ? 
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->bind_param(
        "sssdsisisii", 
        $name, 
        $description, 
        $category, 
        $price, 
        $currency, 
        $is_recurring, 
        $billing_cycle, 
        $stock_quantity, 
        $is_active, 
        $id, 
        $userId
    );
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Produto atualizado!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nenhuma alteração']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $mysqli->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}