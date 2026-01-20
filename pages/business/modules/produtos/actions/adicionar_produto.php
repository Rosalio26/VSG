<?php
/**
 * ================================================================================
 * VISIONGREEN - ADICIONAR PRODUTO
 * Arquivo: pages/business/modules/produtos/actions/adicionar_produto.php
 * ✅ CORRIGIDO: Campos ajustados para SQL real
 * ================================================================================
 */

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../../logs/product_save_errors.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $isEmployee = isset($_SESSION['employee_auth']['employee_id']);
    $isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

    if (!$isEmployee && !$isCompany) {
        sendJsonResponse(['success' => false, 'message' => 'Sessão expirada']);
    }

    if ($isEmployee) {
        $userId = (int)$_SESSION['employee_auth']['empresa_id'];
        $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
        sendJsonResponse(['success' => false, 'message' => 'Funcionários não podem cadastrar produtos']);
    } else {
        $userId = (int)$_SESSION['auth']['user_id'];
    }

    $db_paths = [
        __DIR__ . '/../../../../../registration/includes/db.php',
        __DIR__ . '/../../../../registration/includes/db.php',
        dirname(dirname(dirname(dirname(__FILE__)))) . '/registration/includes/db.php'
    ];

    $db_connected = false;
    foreach ($db_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            if (isset($mysqli)) {
                $db_connected = true;
                break;
            }
        }
    }

    if (!$db_connected || !isset($mysqli)) {
        sendJsonResponse(['success' => false, 'message' => 'Erro de conexão']);
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $preco = floatval($_POST['preco'] ?? 0);
    
    if (empty($nome) || empty($categoria) || $preco <= 0) {
        throw new Exception('Preencha todos os campos obrigatórios');
    }

    $image_path = null;
    $image_path1 = null;
    $image_path2 = null;
    $image_path3 = null;
    $image_path4 = null;
    
    // Upload da imagem principal
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../../../pages/uploads/products/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file = $_FILES['imagem'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception('Formato de imagem não permitido');
        }
        
        $file_name = 'product_' . $userId . '_' . time() . '.' . $file_ext;
        $full_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $image_path = $file_name;
        }
    }
    
    // Upload das imagens adicionais (1-4)
    for ($i = 1; $i <= 4; $i++) {
        $fieldName = 'imagem' . $i;
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../../../../pages/uploads/products/';
            
            $file = $_FILES[$fieldName];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            
            if (!in_array($file_ext, $allowed_ext)) {
                continue;
            }
            
            $file_name = 'product_' . $userId . '_' . time() . '_' . $i . '.' . $file_ext;
            $full_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $full_path)) {
                $varName = 'image_path' . $i;
                $$varName = $file_name;
            }
        }
    }
    
    $currency = $_POST['currency'] ?? 'MZN';
    $stock = !empty($_POST['stock']) ? intval($_POST['stock']) : 0;
    $stock_minimo = !empty($_POST['stock_minimo']) ? intval($_POST['stock_minimo']) : 5;
    $status = $_POST['status'] ?? 'ativo';
    
    $sql = "
        INSERT INTO products (
            user_id, nome, descricao, imagem, image_path1, image_path2, image_path3, image_path4,
            categoria, preco, currency, stock, stock_minimo,
            status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, NOW()
        )
    ";
    
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $mysqli->error);
    }

    $stmt->bind_param(
        "issssssssdsiis",
        $userId,
        $nome,
        $descricao,
        $image_path,
        $image_path1,
        $image_path2,
        $image_path3,
        $image_path4,
        $categoria,
        $preco,
        $currency,
        $stock,
        $stock_minimo,
        $status
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar: ' . $stmt->error);
    }
    
    $product_id = $mysqli->insert_id;
    $stmt->close();
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Produto cadastrado com sucesso!',
        'product_id' => $product_id
    ]);
    
} catch (Exception $e) {
    if (isset($full_path) && file_exists($full_path)) {
        @unlink($full_path);
    }
    
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}