<?php
/*
 * ajax/ajax_cart_guest.php — VSG Marketplace
 *
 * Dois modos:
 *   GET  ?action=resolve&pids=1,2,3  → devolve dados actuais dos produtos (visitante)
 *   POST action=merge                → faz merge do localStorage na BD (pós-login)
 */

require_once dirname(__DIR__) . '/registration/bootstrap.php';

header('Content-Type: application/json');

if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

/* ══════════════════════════════════════════════════════════════════
   GET — resolve: devolve dados actualizados dos produtos para o
   carrinho do visitante (preços, stock, imagens, etc.)
══════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'resolve') {
    $raw  = $_GET['pids'] ?? '';
    $pids = array_filter(array_map('intval', explode(',', $raw)));

    if (empty($pids)) {
        echo json_encode(['success' => true, 'products' => []]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $types        = str_repeat('i', count($pids));

    $st = $mysqli->prepare("
        SELECT
            p.id         AS product_id,
            p.nome       AS product_name,
            p.preco,
            p.stock,
            p.imagem, p.image_path1,
            p.status     AS product_status,
            p.deleted_at AS product_deleted,
            COALESCE(c.name, 'Geral') AS category_name,
            COALESCE(c.icon, 'box')   AS category_icon,
            COALESCE(u.nome, '')      AS company_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN users      u ON u.id = p.user_id
        WHERE p.id IN ($placeholders)
    ");
    $st->bind_param($types, ...$pids);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    // Montar URL da imagem
    foreach ($rows as &$row) {
        $img = $row['image_path1'] ?: $row['imagem'];
        if (empty($img)) {
            $row['img_url'] = 'https://ui-avatars.com/api/?name=' . urlencode($row['product_name']) . '&size=200&background=00b96b&color=fff&font-size=0.1';
        } elseif (str_starts_with($img, 'http') || str_starts_with($img, 'uploads/')) {
            $row['img_url'] = $img;
        } elseif (str_starts_with($img, 'products/')) {
            $row['img_url'] = 'uploads/' . $img;
        } else {
            $row['img_url'] = 'uploads/products/' . $img;
        }
        $row['available'] = $row['product_status'] === 'ativo' && $row['product_deleted'] === null && (int)$row['stock'] > 0;
    }
    unset($row);

    echo json_encode(['success' => true, 'products' => $rows]);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   POST — merge: após o visitante fazer login, importa os itens do
   localStorage para o carrinho da BD do utilizador autenticado
══════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'merge') {

    // CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }

    // Só para utilizadores autenticados
    if (!isset($_SESSION['auth']['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
        exit;
    }

    $user_id = (int)$_SESSION['auth']['user_id'];

    // items[product_id] = qty  (enviado pelo JS)
    $items = $_POST['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => true, 'merged' => 0]);
        exit;
    }

    // Obter ou criar carrinho activo do utilizador
    $st = $mysqli->prepare("SELECT id FROM shopping_carts WHERE user_id = ? AND status = 'active' LIMIT 1");
    $st->bind_param('i', $user_id);
    $st->execute();
    $cart = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$cart) {
        $st = $mysqli->prepare("INSERT INTO shopping_carts (user_id, status, created_at) VALUES (?, 'active', NOW())");
        $st->bind_param('i', $user_id);
        $st->execute();
        $cart_id = (int)$mysqli->insert_id;
        $st->close();
    } else {
        $cart_id = (int)$cart['id'];
    }

    $merged = 0;

    foreach ($items as $pid_raw => $qty_raw) {
        $pid = (int)$pid_raw;
        $qty = (int)$qty_raw;
        if ($pid <= 0 || $qty <= 0) continue;

        // Verificar produto
        $st = $mysqli->prepare("SELECT preco, stock, status, deleted_at FROM products WHERE id = ?");
        $st->bind_param('i', $pid);
        $st->execute();
        $p = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$p || $p['status'] !== 'ativo' || $p['deleted_at'] !== null || (int)$p['stock'] <= 0) continue;

        $qty = min($qty, (int)$p['stock']);

        // Verificar se já existe no carrinho
        $st = $mysqli->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $st->bind_param('ii', $cart_id, $pid);
        $st->execute();
        $existing = $st->get_result()->fetch_assoc();
        $st->close();

        if ($existing) {
            // Somar à quantidade existente
            $new_qty = min((int)$existing['quantity'] + $qty, (int)$p['stock']);
            $st = $mysqli->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $st->bind_param('ii', $new_qty, (int)$existing['id']);
            $st->execute();
            $st->close();
        } else {
            // Inserir novo
            $st = $mysqli->prepare("
                INSERT INTO cart_items (cart_id, product_id, quantity, price, currency, created_at)
                VALUES (?, ?, ?, ?, 'MZN', NOW())
            ");
            $price = (float)$p['preco'];
            $st->bind_param('iiid', $cart_id, $pid, $qty, $price);
            $st->execute();
            $st->close();
        }
        $merged++;
    }

    echo json_encode(['success' => true, 'merged' => $merged]);
    exit;
}

// Acção desconhecida
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Acção inválida.']);