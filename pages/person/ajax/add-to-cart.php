<?php
session_start();
require_once '../registration/includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faça login para adicionar ao carrinho']);
    exit;
}

$userId = $_SESSION['auth']['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;

if ($productId <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Buscar informações do produto
$stmt = $mysqli->prepare("
    SELECT p.id, p.preco, p.currency, p.stock, p.user_id, p.status
    FROM products p
    WHERE p.id = ? AND p.deleted_at IS NULL AND p.status = 'ativo'
");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
    exit;
}

if ($product['stock'] < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Estoque insuficiente']);
    exit;
}

// Verificar se usuário já tem carrinho ativo
$stmt = $mysqli->prepare("
    SELECT id FROM shopping_carts 
    WHERE user_id = ? AND status = 'active'
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$cart = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cart) {
    // Criar novo carrinho
    $stmt = $mysqli->prepare("INSERT INTO shopping_carts (user_id, status) VALUES (?, 'active')");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $cartId = $stmt->insert_id;
    $stmt->close();
} else {
    $cartId = $cart['id'];
}

// Verificar se produto já está no carrinho
$stmt = $mysqli->prepare("
    SELECT id, quantity FROM cart_items 
    WHERE cart_id = ? AND product_id = ?
");
$stmt->bind_param("ii", $cartId, $productId);
$stmt->execute();
$cartItem = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($cartItem) {
    // Atualizar quantidade
    $newQuantity = $cartItem['quantity'] + $quantity;
    
    if ($newQuantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Quantidade excede estoque disponível']);
        exit;
    }
    
    $stmt = $mysqli->prepare("
        UPDATE cart_items 
        SET quantity = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $newQuantity, $cartItem['id']);
    $stmt->execute();
    $stmt->close();
} else {
    // Adicionar novo item
    $stmt = $mysqli->prepare("
        INSERT INTO cart_items (cart_id, product_id, company_id, quantity, price, currency)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiiids", $cartId, $productId, $product['user_id'], $quantity, $product['preco'], $product['currency']);
    $stmt->execute();
    $stmt->close();
}

// Atualizar timestamp do carrinho
$stmt = $mysqli->prepare("UPDATE shopping_carts SET updated_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $cartId);
$stmt->execute();
$stmt->close();

// Contar total de itens no carrinho
$stmt = $mysqli->prepare("
    SELECT COALESCE(SUM(quantity), 0) as cart_count 
    FROM cart_items 
    WHERE cart_id = ?
");
$stmt->bind_param("i", $cartId);
$stmt->execute();
$cartCount = $stmt->get_result()->fetch_assoc()['cart_count'];
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Produto adicionado ao carrinho',
    'cart_count' => $cartCount
]);
?>