<?php
// /pages/person/actions/process_order.php
session_start();
require_once '../../../registration/includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

// Validações
if (empty($input['items']) || !is_array($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Carrinho vazio']);
    exit;
}

if (empty($input['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Método de pagamento não informado']);
    exit;
}

$paymentMethod = $input['payment_method'];
$items = $input['items'];
$shipping = $input['shipping'];

// Validar dados de entrega
$requiredFields = ['nome', 'telefone', 'endereco', 'cidade', 'provincia'];
foreach ($requiredFields as $field) {
    if (empty($shipping[$field])) {
        echo json_encode(['success' => false, 'message' => "Campo obrigatório: {$field}"]);
        exit;
    }
}

mysqli_begin_transaction($mysqli);

try {
    // Buscar produtos e calcular totais
    $productIds = array_map(fn($item) => (int)$item['id'], $items);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $mysqli->prepare("
        SELECT id, user_id, nome, preco, stock, imagem, categoria
        FROM products 
        WHERE id IN ($placeholders) 
        AND status = 'ativo' 
        AND deleted_at IS NULL
    ");
    $stmt->bind_param(str_repeat('i', count($productIds)), ...$productIds);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (count($products) !== count($items)) {
        throw new Exception('Alguns produtos não estão mais disponíveis');
    }
    
    // Criar array indexado por ID
    $productsById = [];
    foreach ($products as $product) {
        $productsById[$product['id']] = $product;
    }
    
    // Verificar estoque e calcular totais
    $subtotal = 0;
    $ordersByCompany = [];
    
    foreach ($items as $item) {
        $productId = (int)$item['id'];
        $quantity = (int)$item['quantity'];
        
        if (!isset($productsById[$productId])) {
            throw new Exception("Produto {$item['name']} não encontrado");
        }
        
        $product = $productsById[$productId];
        
        if ($product['stock'] < $quantity) {
            throw new Exception("Estoque insuficiente para {$product['nome']}");
        }
        
        $companyId = $product['user_id'];
        
        if (!isset($ordersByCompany[$companyId])) {
            $ordersByCompany[$companyId] = [
                'items' => [],
                'subtotal' => 0
            ];
        }
        
        $itemTotal = $product['preco'] * $quantity;
        $subtotal += $itemTotal;
        
        $ordersByCompany[$companyId]['items'][] = [
            'product_id' => $productId,
            'product_name' => $product['nome'],
            'product_image' => $product['imagem'],
            'product_category' => $product['categoria'],
            'quantity' => $quantity,
            'unit_price' => $product['preco'],
            'total' => $itemTotal
        ];
        
        $ordersByCompany[$companyId]['subtotal'] += $itemTotal;
    }
    
    // Criar pedidos (um por empresa)
    $orderNumbers = [];
    
    foreach ($ordersByCompany as $companyId => $orderData) {
        // Gerar número do pedido
        $orderNumber = 'VSG-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Definir status inicial baseado no método de pagamento
        $initialStatus = ($paymentMethod === 'manual') ? 'pendente' : 'confirmado';
        $paymentStatus = 'pendente';
        
        // Criar pedido
        $stmt = $mysqli->prepare("
            INSERT INTO orders (
                company_id, customer_id, order_number, order_date,
                subtotal, shipping_cost, total, currency,
                status, payment_status, payment_method,
                shipping_address, shipping_city, shipping_phone,
                customer_notes
            ) VALUES (?, ?, ?, NOW(), ?, 0, ?, 'MZN', ?, ?, ?,
                      ?, ?, ?, ?)
        ");
        
        $shippingAddress = $shipping['endereco'] . ', ' . $shipping['provincia'];
        $notes = $shipping['observacoes'] ?? '';
        
        $stmt->bind_param('iisddsssssss',
            $companyId,
            $userId,
            $orderNumber,
            $orderData['subtotal'],
            $orderData['subtotal'],
            $initialStatus,
            $paymentStatus,
            $paymentMethod,
            $shippingAddress,
            $shipping['cidade'],
            $shipping['telefone'],
            $notes
        );
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();
        
        // Se pagamento manual, criar registro de pagamento pendente
        if ($paymentMethod === 'manual') {
            $stmt = $mysqli->prepare("
                INSERT INTO payments (
                    order_id, amount, currency, payment_method,
                    payment_status, notes
                ) VALUES (?, ?, 'MZN', 'manual', 'pendente', 
                          'Pagamento manual - Aguardando confirmação da empresa')
            ");
            $stmt->bind_param('id', $orderId, $orderData['subtotal']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Adicionar itens do pedido
        $stmt = $mysqli->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, product_image,
                product_category, quantity, unit_price, total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($orderData['items'] as $item) {
            $stmt->bind_param('iisssidd',
                $orderId,
                $item['product_id'],
                $item['product_name'],
                $item['product_image'],
                $item['product_category'],
                $item['quantity'],
                $item['unit_price'],
                $item['total']
            );
            $stmt->execute();
        }
        $stmt->close();
        
        $orderNumbers[] = $orderNumber;
    }
    
    mysqli_commit($mysqli);
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido realizado com sucesso!',
        'order_number' => implode(', ', $orderNumbers),
        'orders_count' => count($orderNumbers)
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($mysqli);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}