<?php
/**
 * ================================================================================
 * VISIONGREEN - ACTION: DELETAR PRODUTO (SOFT DELETE)
 * ATUALIZADO: Suporta empresa e funcionário
 * ================================================================================
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação (empresa OU funcionário)
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

// Determinar userId (ID da empresa)
if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $canEdit = false; // Funcionários não podem deletar produtos
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $employeeId = null;
    $canEdit = true;
}

// Verificar permissão
if ($isEmployee && !$canEdit) {
    echo json_encode([
        'success' => false, 
        'message' => 'Funcionários não têm permissão para deletar produtos'
    ]);
    exit;
}

// Incluir banco de dados (usando seu padrão de paths)
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

try {
    if (empty($_POST['id'])) {
        throw new Exception('ID não fornecido');
    }
    
    $id = intval($_POST['id']);
    
    // Em vez de DELETE, usamos UPDATE para desativar o produto
    // Preserva integridade referencial com product_purchases
    $stmt = $mysqli->prepare("UPDATE products SET is_active = 0, deleted_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Produto removido do catálogo!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado ou já removido']);
        }
    } else {
        throw new Exception($mysqli->error);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}