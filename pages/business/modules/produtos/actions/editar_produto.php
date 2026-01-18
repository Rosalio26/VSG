<?php
/**
 * ================================================================================
 * VISIONGREEN - ACTION: EDITAR PRODUTO
 * Arquivo: company/modules/produtos/actions/editar_produto.php
 * ATUALIZADO: Suporta empresa e funcionário COM PERMISSÕES
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

// Determinar empresa_id e verificar permissões
if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id']; // ID da empresa
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    
    // Conectar ao banco para verificar permissões
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
    
    // Verificar permissão de editar
    $stmt = $mysqli->prepare("
        SELECT can_edit 
        FROM employee_permissions 
        WHERE employee_id = ? AND module = 'produtos'
        LIMIT 1
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $permissions = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$permissions || !$permissions['can_edit']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Você não tem permissão para editar produtos'
        ]);
        exit;
    }
    
} else {
    // GESTOR - Permissões completas
    $userId = (int)$_SESSION['auth']['user_id'];
}

// Conectar ao banco (se ainda não conectado)
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

try {
    // Validar dados obrigatórios
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
    
    // Validar categoria
    $valid_categories = ['addon', 'service', 'consultation', 'training', 'other'];
    if (!in_array($category, $valid_categories)) {
        echo json_encode(['success' => false, 'message' => 'Categoria inválida']);
        exit;
    }
    
    // Validar billing_cycle
    $valid_cycles = ['monthly', 'yearly', 'one_time'];
    if (!in_array($billing_cycle, $valid_cycles)) {
        $billing_cycle = 'one_time';
    }
    
    // Atualizar apenas produtos da própria empresa
    $stmt = $mysqli->prepare("
        UPDATE products 
        SET name = ?, description = ?, category = ?, price = ?, currency = ?, 
            is_recurring = ?, billing_cycle = ?, stock_quantity = ?, is_active = ?,
            updated_at = NOW()
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
            echo json_encode([
                'success' => true, 
                'message' => 'Produto atualizado com sucesso!'
            ]);
        } else {
            // Verificar se o produto existe
            $check = $mysqli->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
            $check->bind_param('ii', $id, $userId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            
            if (!$exists) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Produto não encontrado ou sem permissão'
                ]);
            } else {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Nenhuma alteração foi feita'
                ]);
            }
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao atualizar: ' . $mysqli->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}