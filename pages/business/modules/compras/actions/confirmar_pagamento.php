<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../../../registration/includes/db.php';

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($isEmployee) {
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    
    // Verificar se funcionário pode confirmar pagamentos
    $stmt = $mysqli->prepare("
        SELECT pode_confirmar_pagamentos FROM employees 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $employeeId, $companyId);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$employee || !$employee['pode_confirmar_pagamentos']) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para confirmar pagamentos']);
        exit;
    }
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
    $employeeId = null;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$receiptNumber = trim($_POST['receipt_number'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido inválido']);
    exit;
}

try {
    $mysqli->begin_transaction();
    
    // Verificar se pedido existe e é pagamento manual
    $stmt = $mysqli->prepare("
        SELECT id, payment_method, payment_status, total, currency
        FROM orders 
        WHERE id = ? AND company_id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param('ii', $orderId, $companyId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        throw new Exception('Pedido não encontrado');
    }
    
    if ($order['payment_method'] !== 'manual') {
        throw new Exception('Este pedido não é de pagamento manual');
    }
    
    if ($order['payment_status'] === 'pago') {
        throw new Exception('Pagamento já foi confirmado');
    }
    
    // Atualizar pedido para pago
    $stmt = $mysqli->prepare("
        UPDATE orders 
        SET payment_status = 'pago',
            payment_date = NOW(),
            confirmed_by_employee = ?,
            confirmed_at = NOW(),
            internal_notes = CONCAT(
                COALESCE(internal_notes, ''),
                '\n[', NOW(), '] PAGAMENTO CONFIRMADO',
                CASE WHEN ? != '' THEN CONCAT(' - Recibo: ', ?) ELSE '' END,
                CASE WHEN ? != '' THEN CONCAT(' - Obs: ', ?) ELSE '' END
            )
        WHERE id = ?
    ");
    $stmt->bind_param('issssi', $employeeId, $receiptNumber, $receiptNumber, $notes, $notes, $orderId);
    $stmt->execute();
    $stmt->close();
    
    // Registrar no histórico de pagamentos
    $stmt = $mysqli->prepare("
        INSERT INTO payments (
            order_id, amount, currency, payment_method, 
            payment_status, payment_date, confirmed_at,
            confirmed_by_employee, receipt_number, notes
        ) VALUES (?, ?, ?, 'manual', 'confirmado', NOW(), NOW(), ?, ?, ?)
    ");
    $stmt->bind_param('idsiss', 
        $orderId, 
        $order['total'], 
        $order['currency'],
        $employeeId,
        $receiptNumber,
        $notes
    );
    $stmt->execute();
    $stmt->close();
    
    // Atualizar sales_records
    $stmt = $mysqli->prepare("
        UPDATE sales_records 
        SET payment_status = 'pago'
        WHERE order_id = ?
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();
    
    // Criar notificação para o cliente
    $stmt = $mysqli->prepare("
        INSERT INTO notifications (
            sender_id, receiver_id, category, priority,
            subject, message, related_order_id
        )
        SELECT 
            ?, 
            customer_id,
            'compra_confirmada',
            'alta',
            CONCAT('Pagamento Confirmado - Pedido #', order_number),
            CONCAT(
                'Seu pagamento manual foi confirmado com sucesso! ',
                'Pedido #', order_number, ' no valor de ', total, ' ', currency, '. ',
                'Seu pedido está sendo processado e será enviado em breve.'
            ),
            id
        FROM orders
        WHERE id = ?
    ");
    $stmt->bind_param('ii', $companyId, $orderId);
    $stmt->execute();
    $stmt->close();
    
    $mysqli->commit();
    
    // ============================================================
    // 4. GERAR E ENVIAR RECIBO AUTOMATICAMENTE
    // ============================================================
    try {
        // Chamar gerador de recibo
        $ch = curl_init();
        $postData = http_build_query([
            'order_id' => $orderId,
            'auto_send' => 'true'
        ]);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/gerar_recibo.php',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $receiptResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $receiptData = json_decode($receiptResponse, true);
            if ($receiptData && $receiptData['success']) {
                error_log("Recibo gerado e enviado: " . $receiptData['receipt_file']);
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao gerar recibo: " . $e->getMessage());
        // Não falha a confirmação se recibo falhar
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pagamento confirmado com sucesso! Recibo enviado ao cliente.'
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao confirmar pagamento: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}