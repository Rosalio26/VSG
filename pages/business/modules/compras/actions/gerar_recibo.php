<?php
/**
 * ================================================================================
 * VISIONGREEN - GERADOR DE RECIBOS
 * Arquivo: pages/business/modules/compras/actions/gerar_recibo.php
 * Descrição: Gera PDF do recibo e salva em storage
 * ================================================================================
 */

session_start();
require_once '../../../../../registration/includes/db.php';

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    die('Acesso negado');
}

$companyId = $isEmployee 
    ? (int)$_SESSION['employee_auth']['empresa_id'] 
    : (int)$_SESSION['auth']['user_id'];

$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$autoSend = isset($_POST['auto_send']) && $_POST['auto_send'] === 'true';

if ($orderId <= 0) {
    die('ID do pedido inválido');
}

try {
    // Buscar dados completos do pedido
    $stmt = $mysqli->prepare("
        SELECT 
            o.*,
            CONCAT(customer.nome, ' ', COALESCE(customer.apelido, '')) as customer_name,
            customer.email as customer_email,
            customer.telefone as customer_phone,
            company.nome as company_name,
            company.email as company_email,
            company.telefone as company_tel,
            b.tax_id as company_nuit
        FROM orders o
        JOIN users customer ON o.customer_id = customer.id
        JOIN users company ON o.company_id = company.id
        LEFT JOIN businesses b ON company.id = b.user_id
        WHERE o.id = ? AND o.company_id = ?
    ");
    $stmt->bind_param('ii', $orderId, $companyId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        die('Pedido não encontrado');
    }
    
    // Buscar itens do pedido
    $stmt = $mysqli->prepare("
        SELECT 
            oi.*,
            p.nome as current_product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Criar diretório de recibos se não existir
    $receiptsDir = __DIR__ . '/../../../../../../pages/uploads/receipts/';
    if (!is_dir($receiptsDir)) {
        mkdir($receiptsDir, 0755, true);
    }
    
    $receiptFile = 'recibo_' . $order['order_number'] . '_' . time() . '.pdf';
    $receiptPath = $receiptsDir . $receiptFile;
    
    // Preparar dados para Python
    $data = [
        'order' => $order,
        'items' => $items,
        'receipt_path' => $receiptPath
    ];
    
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
    $tmpFile = tempnam(sys_get_temp_dir(), 'receipt_');
    file_put_contents($tmpFile, $jsonData);
    
    // Executar script Python
    $pythonScript = __DIR__ . '/generate_receipt.py';
    $command = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($tmpFile) . " 2>&1";
    $output = shell_exec($command);
    
    unlink($tmpFile);
    
    if (!file_exists($receiptPath)) {
        error_log("Erro ao gerar PDF: " . $output);
        die('Erro ao gerar recibo');
    }
    
    // Salvar caminho do recibo no pedido
    $stmt = $mysqli->prepare("
        UPDATE orders 
        SET internal_notes = CONCAT(
            COALESCE(internal_notes, ''),
            '\n[', NOW(), '] Recibo gerado: ', ?
        )
        WHERE id = ?
    ");
    $stmt->bind_param('si', $receiptFile, $orderId);
    $stmt->execute();
    $stmt->close();
    
    // Se auto_send, enviar notificação ao cliente
    if ($autoSend) {
        $receiptUrl = 'uploads/receipts/' . $receiptFile;
        
        $stmt = $mysqli->prepare("
            INSERT INTO notifications (
                sender_id, receiver_id, category, priority,
                subject, message, related_order_id
            ) VALUES (?, ?, 'compra_confirmada', 'alta', ?, ?, ?)
        ");
        
        $subject = 'Recibo do Pedido #' . $order['order_number'];
        $message = sprintf(
            "Seu recibo está pronto!\n\n" .
            "Pedido: %s\n" .
            "Valor: %.2f %s\n" .
            "Data: %s\n\n" .
            "Clique no link abaixo para baixar seu recibo:\n" .
            "%s\n\n" .
            "Obrigado por comprar conosco!",
            $order['order_number'],
            $order['total'],
            $order['currency'],
            date('d/m/Y', strtotime($order['order_date'])),
            $receiptUrl
        );
        
        $stmt->bind_param('iissi', $companyId, $order['customer_id'], $subject, $message, $orderId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Recibo gerado e enviado ao cliente',
            'receipt_file' => $receiptFile,
            'receipt_url' => $receiptUrl
        ]);
    } else {
        // Download direto
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $receiptFile . '"');
        header('Content-Length: ' . filesize($receiptPath));
        readfile($receiptPath);
    }
    
} catch (Exception $e) {
    error_log("Erro ao gerar recibo: " . $e->getMessage());
    if ($autoSend) {
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar recibo']);
    } else {
        die('Erro ao gerar recibo');
    }
}