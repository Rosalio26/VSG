<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../registration/includes/db.php';

$customerId = (int)($_SESSION['auth']['user_id'] ?? 0);

if ($customerId === 0) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = json_decode($_POST['items'] ?? '[]', true);
$shipping_address = trim($_POST['shipping_address'] ?? '');
$shipping_city = trim($_POST['shipping_city'] ?? '');
$shipping_phone = trim($_POST['shipping_phone'] ?? '');
$payment_method = $_POST['payment_method'] ?? '';
$customer_notes = trim($_POST['customer_notes'] ?? '');

if (empty($items) || empty($shipping_address) || empty($payment_method)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $mysqli->begin_transaction();
    
    // Agrupar itens por empresa
    $itemsByCompany = [];
    foreach ($items as $item) {
        $companyId = (int)$item['company_id'];
        if (!isset($itemsByCompany[$companyId])) {
            $itemsByCompany[$companyId] = [];
        }
        $itemsByCompany[$companyId][] = $item;
    }
    
    $createdOrders = [];
    
    // Criar um pedido para cada empresa
    foreach ($itemsByCompany as $companyId => $companyItems) {
        $subtotal = 0;
        $currency = 'MZN'; // Default
        
        foreach ($companyItems as $item) {
            $subtotal += floatval($item['preco']) * intval($item['quantity']);
            if (isset($item['currency'])) {
                $currency = $item['currency'];
            }
        }
        
        $total = $subtotal;
        $orderNumber = 'PED-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Inserir pedido
        $stmtOrder = $mysqli->prepare("
            INSERT INTO orders (
                company_id, customer_id, order_number, order_date, 
                subtotal, total, currency, status, payment_status, payment_method,
                shipping_address, shipping_city, shipping_phone, customer_notes,
                created_at
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmtOrder) {
            throw new Exception('Erro ao preparar inserção do pedido: ' . $mysqli->error);
        }
        
        $stmtOrder->bind_param(
            'iisddssssss',
            $companyId, 
            $customerId, 
            $orderNumber, 
            $subtotal, 
            $total,
            $currency,
            $payment_method, 
            $shipping_address, 
            $shipping_city, 
            $shipping_phone, 
            $customer_notes
        );
        
        if (!$stmtOrder->execute()) {
            throw new Exception('Erro ao executar inserção do pedido: ' . $stmtOrder->error);
        }
        
        $orderId = $stmtOrder->insert_id;
        $stmtOrder->close();
        
        // Inserir itens do pedido
        $stmtItem = $mysqli->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, quantity, unit_price, total
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmtItem) {
            throw new Exception('Erro ao preparar inserção dos itens: ' . $mysqli->error);
        }
        
        foreach ($companyItems as $item) {
            $productId = (int)$item['id'];
            $productName = trim($item['nome']);
            $quantity = (int)$item['quantity'];
            $unitPrice = floatval($item['preco']);
            $itemTotal = $unitPrice * $quantity;
            
            $stmtItem->bind_param(
                'iisidd',
                $orderId, 
                $productId, 
                $productName, 
                $quantity, 
                $unitPrice, 
                $itemTotal
            );
            
            if (!$stmtItem->execute()) {
                throw new Exception('Erro ao inserir item: ' . $stmtItem->error);
            }
            
            // Atualizar estoque (se não for ilimitado)
            $stmtUpdateStock = $mysqli->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ? AND stock_quantity IS NOT NULL AND stock_quantity >= ?
            ");
            
            $stmtUpdateStock->bind_param('iii', $quantity, $productId, $quantity);
            $stmtUpdateStock->execute();
            $stmtUpdateStock->close();
        }
        
        $stmtItem->close();
        
        $createdOrders[] = [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'company_id' => $companyId,
            'total' => $total
        ];
    }
    
    $mysqli->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pedido realizado com sucesso!',
        'orders' => $createdOrders
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao criar pedido: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar pedido: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}