<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../registration/includes/db.php';

$customerId = (int)($_SESSION['auth']['user_id'] ?? 0);

if ($customerId === 0) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$items = json_decode($_POST['items'] ?? '[]', true);
$shipping_address = trim($_POST['shipping_address'] ?? '');
$shipping_city = trim($_POST['shipping_city'] ?? '');
$shipping_phone = trim($_POST['shipping_phone'] ?? '');
$payment_method = $_POST['payment_method'] ?? '';
$customer_notes = trim($_POST['customer_notes'] ?? '');

if (empty($items) || empty($shipping_address) || empty($payment_method)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    $mysqli->begin_transaction();
    
    // Buscar dados do cliente
    $stmtCustomer = $mysqli->prepare("SELECT nome, apelido FROM users WHERE id = ?");
    $stmtCustomer->bind_param('i', $customerId);
    $stmtCustomer->execute();
    $customerData = $stmtCustomer->get_result()->fetch_assoc();
    $stmtCustomer->close();
    
    $customerName = $customerData['nome'] . ' ' . ($customerData['apelido'] ?? '');
    
    // Agrupar itens por empresa
    $itemsByCompany = [];
    foreach ($items as $item) {
        $companyId = $item['company_id'];
        if (!isset($itemsByCompany[$companyId])) {
            $itemsByCompany[$companyId] = [];
        }
        $itemsByCompany[$companyId][] = $item;
    }
    
    // Criar um pedido para cada empresa
    foreach ($itemsByCompany as $companyId => $companyItems) {
        $subtotal = 0;
        foreach ($companyItems as $item) {
            $subtotal += $item['preco'] * $item['quantity'];
        }
        
        $total = $subtotal;
        $orderNumber = 'PED-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $orderDate = date('Y-m-d');
        
        // ============================================================
        // 1. INSERIR PEDIDO
        // ============================================================
        $stmtOrder = $mysqli->prepare("
            INSERT INTO orders (
                company_id, customer_id, order_number, order_date, 
                subtotal, total, currency, status, payment_status, payment_method,
                shipping_address, shipping_city, shipping_phone, customer_notes
            ) VALUES (?, ?, ?, ?, ?, ?, 'MZN', 'pending', 'pending', ?, ?, ?, ?, ?)
        ");
        
        $stmtOrder->bind_param(
            'iissddsssss',
            $companyId, $customerId, $orderNumber, $orderDate,
            $subtotal, $total, $payment_method, 
            $shipping_address, $shipping_city, $shipping_phone, $customer_notes
        );
        
        $stmtOrder->execute();
        $orderId = $stmtOrder->insert_id;
        $stmtOrder->close();
        
        // ============================================================
        // 2. INSERIR ITENS DO PEDIDO
        // ============================================================
        $stmtItem = $mysqli->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, product_image, product_category,
                quantity, unit_price, discount, total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($companyItems as $item) {
            $itemTotal = $item['preco'] * $item['quantity'];
            $discount = 0;
            $productImage = $item['imagem'] ?? null;
            $productCategory = $item['categoria'] ?? null;
            
            $stmtItem->bind_param(
                'iisssiddd',
                $orderId, $item['id'], $item['nome'], $productImage, $productCategory,
                $item['quantity'], $item['preco'], $discount, $itemTotal
            );
            $stmtItem->execute();
            $itemId = $stmtItem->insert_id;
            
            // ============================================================
            // 3. ATUALIZAR STOCK (Manual - substitui trigger)
            // ============================================================
            if ($item['id']) {
                $stmtStock = $mysqli->prepare("
                    UPDATE products 
                    SET stock = stock - ? 
                    WHERE id = ? AND stock IS NOT NULL AND stock >= ?
                ");
                $stmtStock->bind_param('iii', $item['quantity'], $item['id'], $item['quantity']);
                $stmtStock->execute();
                
                if ($stmtStock->affected_rows === 0) {
                    // Stock insuficiente
                    $stmtStock->close();
                    throw new Exception("Stock insuficiente para: " . $item['nome']);
                }
                $stmtStock->close();
            }
            
            // ============================================================
            // 4. CRIAR SALES_RECORD (Manual - substitui trigger)
            // ============================================================
            $stmtSales = $mysqli->prepare("
                INSERT INTO sales_records (
                    order_id, order_item_id, company_id, customer_id, product_id,
                    sale_date, sale_time, 
                    quantity, unit_price, discount, total, currency,
                    order_status, payment_status,
                    product_name, product_category, customer_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'MZN', 'pending', 'pending', ?, ?, ?)
            ");
            
            $saleTime = date('H:i:s');
            
            $stmtSales->bind_param(
                'iiiiiisidddsss',
                $orderId, $itemId, $companyId, $customerId, $item['id'],
                $orderDate, $saleTime,
                $item['quantity'], $item['preco'], $discount, $itemTotal,
                $item['nome'], $productCategory, $customerName
            );
            $stmtSales->execute();
            $stmtSales->close();
        }
        
        $stmtItem->close();
        
        // ============================================================
        // 5. CRIAR HISTÓRICO INICIAL
        // ============================================================
        $stmtHistory = $mysqli->prepare("
            INSERT INTO order_status_history (order_id, status_from, status_to, changed_by)
            VALUES (?, NULL, 'pending', ?)
        ");
        $stmtHistory->bind_param('ii', $orderId, $customerId);
        $stmtHistory->execute();
        $stmtHistory->close();
    }
    
    $mysqli->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pedido realizado com sucesso!']);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Erro ao criar pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}