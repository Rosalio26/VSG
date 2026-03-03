<?php
/*
 * ajax_cart.php — VSG Marketplace
 * Operações AJAX do carrinho: add, update, remove, clear, get_count
 * Colocar em: pages/app/ajax/ajax_cart.php
 */

require_once __DIR__ . '/../registration/bootstrap.php';
require_once __DIR__ . '/../registration/includes/security.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

function json_out(array $data): never {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Auth obrigatória ─────────────────────────────────────────────────
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_id        = $user_logged_in ? (int)$_SESSION['auth']['user_id'] : 0;

if (!$user_logged_in) {
    json_out(['success' => false, 'message' => 'Precisa de fazer login.', 'redirect' => '/registration/login/login.php']);
}

// ── CSRF (POST) ──────────────────────────────────────────────────────
if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        json_out(['success' => false, 'message' => 'Token inválido. Recarregue a página.']);
    }
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// ── Helpers ──────────────────────────────────────────────────────────
function getOrCreateCart(int $uid): int {
    global $mysqli;
    $st = $mysqli->prepare("SELECT id FROM shopping_carts WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1");
    $st->bind_param('i', $uid); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if ($row) return (int)$row['id'];
    $st = $mysqli->prepare("INSERT INTO shopping_carts (user_id, status) VALUES (?, 'active')");
    $st->bind_param('i', $uid); $st->execute();
    $id = (int)$mysqli->insert_id; $st->close();
    return $id;
}

function cartCount(int $uid): int {
    global $mysqli;
    $st = $mysqli->prepare("
        SELECT COALESCE(SUM(ci.quantity),0) AS n
        FROM shopping_carts sc
        INNER JOIN cart_items ci ON ci.cart_id = sc.id
        WHERE sc.user_id = ? AND sc.status = 'active'
    ");
    $st->bind_param('i', $uid); $st->execute();
    $n = (int)$st->get_result()->fetch_assoc()['n']; $st->close();
    $_SESSION['cart_count'] = $n;
    return $n;
}

// ════════════════════════════════════════════════════════════════════
switch ($action) {

case 'add':
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
    if ($product_id <= 0) json_out(['success' => false, 'message' => 'Produto inválido.']);

    $st = $mysqli->prepare("SELECT id, preco, stock, user_id AS company_id, currency, status, deleted_at FROM products WHERE id=? LIMIT 1");
    $st->bind_param('i', $product_id); $st->execute();
    $prod = $st->get_result()->fetch_assoc(); $st->close();

    if (!$prod || $prod['status'] !== 'ativo' || $prod['deleted_at'] !== null)
        json_out(['success' => false, 'message' => 'Produto não disponível.']);
    if ($prod['stock'] < $quantity)
        json_out(['success' => false, 'message' => 'Stock insuficiente. Disponível: ' . $prod['stock']]);

    $cart_id    = getOrCreateCart($user_id);
    $company_id = (int)$prod['company_id'];
    $price      = (float)$prod['preco'];
    $currency   = $prod['currency'] ?? 'MZN';
    $max_stock  = (int)$prod['stock'];

    $st = $mysqli->prepare("
        INSERT INTO cart_items (cart_id, product_id, company_id, quantity, price, currency)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            quantity = LEAST(quantity + VALUES(quantity), ?),
            updated_at = NOW()
    ");
    $st->bind_param('iiiidsi', $cart_id, $product_id, $company_id, $quantity, $price, $currency, $max_stock);
    $st->execute(); $st->close();

    json_out(['success' => true, 'message' => 'Produto adicionado ao carrinho!', 'cart_count' => cartCount($user_id)]);

case 'update':
    $item_id  = (int)($_POST['item_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    if ($item_id <= 0) json_out(['success' => false, 'message' => 'Item inválido.']);

    if ($quantity <= 0) {
        $st = $mysqli->prepare("DELETE ci FROM cart_items ci INNER JOIN shopping_carts sc ON sc.id=ci.cart_id WHERE ci.id=? AND sc.user_id=?");
        $st->bind_param('ii', $item_id, $user_id); $st->execute(); $st->close();
        json_out(['success' => true, 'removed' => true, 'cart_count' => cartCount($user_id)]);
    }

    $st = $mysqli->prepare("SELECT ci.id, p.stock FROM cart_items ci INNER JOIN shopping_carts sc ON sc.id=ci.cart_id AND sc.user_id=? INNER JOIN products p ON p.id=ci.product_id WHERE ci.id=?");
    $st->bind_param('ii', $user_id, $item_id); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();

    if (!$row) json_out(['success' => false, 'message' => 'Item não encontrado.']);
    if ($quantity > $row['stock']) json_out(['success' => false, 'message' => 'Stock máximo: ' . $row['stock'], 'max' => $row['stock']]);

    $st = $mysqli->prepare("UPDATE cart_items SET quantity=?, updated_at=NOW() WHERE id=?");
    $st->bind_param('ii', $quantity, $item_id); $st->execute(); $st->close();
    json_out(['success' => true, 'cart_count' => cartCount($user_id)]);

case 'remove':
    $item_id = (int)($_POST['item_id'] ?? 0);
    if ($item_id <= 0) json_out(['success' => false, 'message' => 'Item inválido.']);
    $st = $mysqli->prepare("DELETE ci FROM cart_items ci INNER JOIN shopping_carts sc ON sc.id=ci.cart_id WHERE ci.id=? AND sc.user_id=?");
    $st->bind_param('ii', $item_id, $user_id); $st->execute(); $st->close();
    json_out(['success' => true, 'cart_count' => cartCount($user_id)]);

case 'clear':
    $mysqli->query("DELETE ci FROM cart_items ci INNER JOIN shopping_carts sc ON sc.id=ci.cart_id WHERE sc.user_id={$user_id} AND sc.status='active'");
    $_SESSION['cart_count'] = 0;
    json_out(['success' => true, 'cart_count' => 0]);

case 'get_count':
    json_out(['success' => true, 'cart_count' => cartCount($user_id)]);

default:
    json_out(['success' => false, 'message' => 'Acção desconhecida.']);
}