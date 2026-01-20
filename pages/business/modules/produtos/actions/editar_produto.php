<?php
/**
 * ================================================================================
 * VISIONGREEN - EDITAR PRODUTO
 * Arquivo: pages/business/modules/produtos/actions/editar_produto.php
 * ✅ CORRIGIDO: Campos ajustados para SQL real
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

try {
    if (empty($_POST['id']) || empty($_POST['nome']) || !isset($_POST['preco'])) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        exit;
    }
    
    $id = intval($_POST['id']);
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = $_POST['categoria'];
    $preco = floatval($_POST['preco']);
    $currency = $_POST['currency'] ?? 'MZN';
    $stock = !empty($_POST['stock']) ? intval($_POST['stock']) : 0;
    $stock_minimo = !empty($_POST['stock_minimo']) ? intval($_POST['stock_minimo']) : 5;
    $status = $_POST['status'] ?? 'ativo';
    
    $valid_categories = ['reciclavel', 'sustentavel', 'servico', 'visiongreen', 'ecologico', 'outro'];
    if (!in_array($categoria, $valid_categories)) {
        echo json_encode(['success' => false, 'message' => 'Categoria inválida']);
        exit;
    }
    
    $valid_status = ['ativo', 'inativo', 'esgotado'];
    if (!in_array($status, $valid_status)) {
        $status = 'ativo';
    }
    
    // Processar upload de imagens adicionais
    $image_updates = [];
    $image_params = [];
    $image_types = '';
    
    for ($i = 1; $i <= 4; $i++) {
        $field_name = 'imagem' . $i;
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../../../../pages/uploads/products/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES[$field_name];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $file_name = 'product_' . $userId . '_' . time() . '_' . $i . '.' . $file_ext;
                $full_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file['tmp_name'], $full_path)) {
                    $image_updates[] = "image_path" . $i . " = ?";
                    $image_params[] = $file_name;
                    $image_types .= 's';
                }
            }
        }
    }
    
    // Construir query
    $set_clause = "nome = ?, descricao = ?, categoria = ?, preco = ?, currency = ?, 
            stock = ?, stock_minimo = ?, status = ?";
    
    if (!empty($image_updates)) {
        $set_clause .= ", " . implode(", ", $image_updates);
    }
    
    $stmt = $mysqli->prepare("
        UPDATE products 
        SET {$set_clause}, updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    
    $base_types = "sssdsiis";
    $all_types = $base_types . $image_types . "ii";
    
    $params = [
        $nome, 
        $descricao, 
        $categoria, 
        $preco, 
        $currency, 
        $stock, 
        $stock_minimo, 
        $status
    ];
    
    $params = array_merge($params, $image_params, [$id, $userId]);
    
    $stmt->bind_param($all_types, ...$params);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Produto atualizado com sucesso!'
            ]);
        } else {
            $check = $mysqli->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
            $check->bind_param('ii', $id, $userId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            
            if (!$exists) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Produto não encontrado'
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