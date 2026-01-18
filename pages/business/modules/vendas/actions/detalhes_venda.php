<?php
/**
 * DETALHES DA VENDA
 * Retorna informações completas da venda incluindo cliente e produto
 * ATUALIZADO: Suporta empresa e funcionário
 */

header('Content-Type: application/json');

function logDebug($message, $data = null) {
    $logDir = __DIR__ . '/../debug/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $log .= "\n";
    
    file_put_contents($logDir . 'detalhes_venda.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO DETALHES VENDA ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação (empresa OU funcionário)
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Determinar userId (ID da empresa)
if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $userType = 'funcionario';
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $userType = 'gestor';
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

$vendaId = (int)($_GET['venda_id'] ?? 0);

if (!$vendaId) {
    logDebug('ERRO: venda_id não fornecido');
    echo json_encode(['success' => false, 'message' => 'ID da venda não fornecido']);
    exit;
}

logDebug('Venda ID', ['venda_id' => $vendaId, 'user_type' => $userType]);

try {
    logDebug('Buscando detalhes completos');
    
    // Verificar que a venda pertence à empresa do usuário logado
    $stmt = $mysqli->prepare("
        SELECT 
            pp.id,
            pp.quantity,
            pp.unit_price,
            pp.total_amount,
            pp.status,
            pp.purchase_date,
            pp.transaction_id,
            
            p.name as produto_nome,
            p.category as produto_categoria,
            p.eco_category as produto_eco_categoria,
            p.description as produto_descricao,
            
            t.invoice_number,
            t.payment_method,
            t.transaction_date,
            t.due_date,
            t.paid_date,
            
            u.nome as cliente_nome,
            u.apelido as cliente_apelido,
            u.email as cliente_email,
            u.telefone as cliente_telefone,
            u.type as cliente_tipo
            
        FROM product_purchases pp
        INNER JOIN products p ON pp.product_id = p.id
        LEFT JOIN transactions t ON pp.transaction_id = t.id
        LEFT JOIN users u ON pp.user_id = u.id
        WHERE pp.id = ?
        AND p.user_id = ?
    ");
    
    $stmt->bind_param('ii', $vendaId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logDebug('Venda não encontrada ou sem permissão');
        echo json_encode([
            'success' => false,
            'message' => 'Venda não encontrada ou você não tem permissão para visualizá-la'
        ]);
        exit;
    }
    
    $venda = $result->fetch_assoc();
    
    logDebug('Venda encontrada', [
        'invoice' => $venda['invoice_number'],
        'cliente' => $venda['cliente_nome']
    ]);
    
    logDebug('=== FIM DETALHES VENDA (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'venda' => $venda,
        'user_type' => $userType
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM DETALHES VENDA (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar detalhes: ' . $e->getMessage()
    ]);
}