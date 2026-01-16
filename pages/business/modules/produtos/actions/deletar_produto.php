<?php
/**
 * ================================================================================
 * VISIONGREEN - ACTION: DELETAR PRODUTO
 * Arquivo: company/modules/produtos/actions/deletar_produto.php
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
    if (empty($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        exit;
    }
    
    $id = intval($_POST['id']);
    
    // Deletar apenas produtos da própria empresa
    $stmt = $mysqli->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Produto deletado!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $mysqli->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}