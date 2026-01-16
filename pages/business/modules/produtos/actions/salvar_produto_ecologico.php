<?php
/**
 * ================================================================================
 * VISIONGREEN - SALVAR PRODUTO ECOLÓGICO (VERSÃO CORRIGIDA)
 * Arquivo: company/modules/produtos/actions/salvar_produto_ecologico.php  
 * ================================================================================
 */

// Habilitar log de erros em arquivo
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../../logs/product_save_errors.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Limpar buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

function logDebug($message, $data = null) {
    $log = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . json_encode($data);
    }
    error_log($log);
}

function logToFile($message) {
    $log = date('H:i:s') . ' - ' . $message . "\n";
    file_put_contents(__DIR__ . '/debug.log', $log, FILE_APPEND);
}

function sendJsonResponse($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

logToFile('=== INÍCIO ===');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['auth']['user_id'])) {
        logToFile('ERRO: Sem autenticação');
        sendJsonResponse(['success' => false, 'message' => 'Sessão expirada']);
    }

    $userId = (int)$_SESSION['auth']['user_id'];

    // Conectar ao banco
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
        logToFile('ERRO: DB não conectado');
        sendJsonResponse(['success' => false, 'message' => 'Erro de conexão com o banco de dados']);
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    // ==================== VALIDAR DADOS ====================
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $eco_category = $_POST['eco_category'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    
    if (empty($name) || empty($description) || empty($category) || empty($eco_category) || $price <= 0) {
        throw new Exception('Preencha todos os campos obrigatórios corretamente.');
    }

    // ==================== UPLOAD DE IMAGEM ====================
    $image_path = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../../uploads/products/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file = $_FILES['product_image'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception('Formato de imagem não permitido.');
        }
        
        $file_name = 'product_' . $userId . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $full_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $image_path = 'products/' . $file_name;
        }
    }
    
    // ==================== PREPARAR DADOS ADICIONAIS ====================
    $currency = $_POST['currency'] ?? 'MZN';
    $stock_quantity = !empty($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : null;
    
    $materials = [];
    if (isset($_POST['biodegradable'])) $materials[] = 'biodegradável';
    if (isset($_POST['renewable_materials'])) $materials[] = 'materiais renováveis';
    if (isset($_POST['water_efficient'])) $materials[] = 'eficiente em água';
    if (isset($_POST['energy_efficient'])) $materials[] = 'eficiente em energia';
    $materials_text = !empty($materials) ? implode(', ', $materials) : null;
    
    $recyclable_percentage = !empty($_POST['recyclable_percentage']) ? intval($_POST['recyclable_percentage']) : null;
    $recyclability_index = $recyclable_percentage !== null ? ($recyclable_percentage / 10) : null;
    
    $carbon_footprint_value = !empty($_POST['carbon_footprint']) ? floatval($_POST['carbon_footprint']) : null;
    $carbon_footprint = $carbon_footprint_value !== null ? $carbon_footprint_value . ' kg CO2' : null;
    
    $environmental_impact = trim($_POST['environmental_impact'] ?? ''); // Mapeado para eco_benefits
    $eco_certification = !empty($_POST['eco_certification']) ? $_POST['eco_certification'] : null;
    
    $eco_certifications_json = null;
    if (!empty($eco_certification)) {
        $eco_certifications_json = json_encode([
            'certifications' => [
                ['code' => $eco_certification, 'verified' => false, 'added_at' => date('Y-m-d H:i:s')]
            ]
        ]);
    }
    
    // ==================== INSERIR NO BANCO (CORRIGIDO) ====================
    // 14 placeholders (?) para as colunas dinâmicas. eco_verified(0), is_active(1) e created_at(NOW()) são fixos.
    $sql = "
        INSERT INTO products (
            user_id, name, description, image_path,
            category, eco_category, price, currency, stock_quantity,
            eco_verified, eco_certifications, carbon_footprint, 
            materials_used, recyclability_index, eco_benefits, 
            is_active, created_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            0, ?, ?, 
            ?, ?, ?, 
            1, NOW()
        )
    ";
    
    logToFile('Preparando statement...');
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $mysqli->error);
    }

    // String de tipos: 14 caracteres para 14 placeholders
    $types = "issssssdississ"; 
    
    $stmt->bind_param(
        $types,
        $userId,                 // 1 (i)
        $name,                   // 2 (s)
        $description,            // 3 (s)
        $image_path,             // 4 (s)
        $category,               // 5 (s)
        $eco_category,           // 6 (s)
        $price,                  // 7 (d)
        $currency,               // 8 (s)
        $stock_quantity,         // 9 (i)
        $eco_certifications_json,// 10 (s)
        $carbon_footprint,       // 11 (s)
        $materials_text,         // 12 (s)
        $recyclability_index,    // 13 (d ou i, s resolve)
        $environmental_impact    // 14 (s) -> eco_benefits
    );
    
    logToFile('Executando query...');
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar salvamento: ' . $stmt->error);
    }
    
    $product_id = $mysqli->insert_id;
    $stmt->close();
    
    logToFile('Produto salvo! ID: ' . $product_id);
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Produto cadastrado com sucesso!',
        'product_id' => $product_id
    ]);
    
} catch (Exception $e) {
    logToFile('ERRO: ' . $e->getMessage());
    
    if (isset($full_path) && file_exists($full_path)) {
        @unlink($full_path);
    }
    
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

logToFile('=== FIM ===');