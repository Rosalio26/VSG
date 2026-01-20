<?php
/**
 * ================================================================================
 * VISIONGREEN - DELETAR PRODUTO
 * Arquivo: pages/business/modules/produtos/actions/deletar_produto.php
 * ✅ CORRIGIDO: Soft delete usando deleted_at
 * ================================================================================
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    
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
    
    $stmt = $mysqli->prepare("
        SELECT can_delete 
        FROM employee_permissions 
        WHERE employee_id = ? AND module = 'produtos'
        LIMIT 1
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $permissions = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$permissions || !$permissions['can_delete']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Você não tem permissão para deletar produtos'
        ]);
        exit;
    }
    
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
}

if (!isset($mysqli)) {
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
}

try {
    if (empty($_POST['id'])) {
        throw new Exception('ID não fornecido');
    }
    
    $id = intval($_POST['id']);
    
    $stmt = $mysqli->prepare("
        UPDATE products 
        SET deleted_at = NOW() 
        WHERE id = ? AND user_id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Produto removido!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        }
    } else {
        throw new Exception($mysqli->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}