<?php
/**
 * DETALHES DA VENDA
 * Retorna informações completas da venda incluindo cliente e produto
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

if (!isset($_SESSION['auth']['user_id'])) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

$vendaId = (int)$_GET['venda_id'];
logDebug('Venda ID', ['venda_id' => $vendaId]);

try {
    logDebug('Buscando detalhes completos');
    
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
    ");
    
    $stmt->bind_param('i', $vendaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logDebug('Venda não encontrada');
        echo json_encode([
            'success' => false,
            'message' => 'Venda não encontrada'
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
        'venda' => $venda
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM DETALHES VENDA (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}