<?php
/**
 * ================================================================================
 * VISIONGREEN - SALVAR PRODUTO ECOLÓGICO
 * Arquivo: company/modules/produtos/actions/salvar_produto_ecologico.php
 * Descrição: Salva produto após verificação e faz upload da imagem
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

try {
    // Validar campos obrigatórios
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    
    if (empty($name) || empty($description) || empty($category) || $price <= 0) {
        throw new Exception('Campos obrigatórios não preenchidos');
    }
    
    // ==================== UPLOAD DE IMAGEM ====================
    $image_path = null;
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../../uploads/products/';
        
        // Criar diretório se não existir
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file = $_FILES['product_image'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validar extensão
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception('Formato de imagem não permitido');
        }
        
        // Validar tamanho (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('Imagem muito grande (máx. 5MB)');
        }
        
        // Gerar nome único
        $file_name = 'product_' . $userId . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        // Mover arquivo
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $image_path = 'products/' . $file_name;
        } else {
            throw new Exception('Erro ao fazer upload da imagem');
        }
    }
    
    // ==================== PREPARAR DADOS ====================
    $currency = $_POST['currency'] ?? 'MZN';
    $stock_quantity = !empty($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : NULL;
    $product_weight = !empty($_POST['product_weight']) ? floatval($_POST['product_weight']) : NULL;
    
    // Características sustentáveis
    $biodegradable = isset($_POST['biodegradable']) ? 1 : 0;
    $renewable_materials = isset($_POST['renewable_materials']) ? 1 : 0;
    $water_efficient = isset($_POST['water_efficient']) ? 1 : 0;
    $energy_efficient = isset($_POST['energy_efficient']) ? 1 : 0;
    
    // Métricas
    $recyclable_percentage = !empty($_POST['recyclable_percentage']) ? intval($_POST['recyclable_percentage']) : NULL;
    $carbon_footprint = !empty($_POST['carbon_footprint']) ? floatval($_POST['carbon_footprint']) : NULL;
    $eco_certification = !empty($_POST['eco_certification']) ? $_POST['eco_certification'] : NULL;
    
    // Informações adicionais
    $environmental_impact = trim($_POST['environmental_impact'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $origin_country = trim($_POST['origin_country'] ?? '');
    $warranty_months = !empty($_POST['warranty_months']) ? intval($_POST['warranty_months']) : NULL;
    $dimensions = trim($_POST['dimensions'] ?? '');
    
    // ==================== INSERIR NO BANCO ====================
    $stmt = $mysqli->prepare("
        INSERT INTO products (
            user_id, name, description, product_image, category, price, currency,
            stock_quantity, product_weight, dimensions,
            eco_certification, carbon_footprint, recyclable_percentage,
            biodegradable, renewable_materials, water_efficient, energy_efficient,
            environmental_impact, manufacturer, origin_country, warranty_months,
            verification_status, is_active, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            'pending', 1, NOW()
        )
    ");
    
    $stmt->bind_param(
        "issssdsissdiiiiisssi",
        $userId,
        $name,
        $description,
        $image_path,
        $category,
        $price,
        $currency,
        $stock_quantity,
        $product_weight,
        $dimensions,
        $eco_certification,
        $carbon_footprint,
        $recyclable_percentage,
        $biodegradable,
        $renewable_materials,
        $water_efficient,
        $energy_efficient,
        $environmental_impact,
        $manufacturer,
        $origin_country,
        $warranty_months
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar produto: ' . $stmt->error);
    }
    
    $product_id = $mysqli->insert_id;
    $stmt->close();
    
    // ==================== REGISTRAR HISTÓRICO ====================
    $history_stmt = $mysqli->prepare("
        INSERT INTO product_verification_history 
        (product_id, user_id, status, notes, created_at)
        VALUES (?, ?, 'submitted', 'Produto enviado para verificação', NOW())
    ");
    
    $history_stmt->bind_param("ii", $product_id, $userId);
    $history_stmt->execute();
    $history_stmt->close();
    
    // ==================== RESPOSTA DE SUCESSO ====================
    echo json_encode([
        'success' => true,
        'message' => 'Produto cadastrado com sucesso! Aguardando verificação final.',
        'product_id' => $product_id,
        'status' => 'pending'
    ]);
    
} catch (Exception $e) {
    // Deletar imagem se foi feito upload
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}