<?php
/**
 * pages/person/actions/get_cart.php
 * Devolve os itens do carrinho do utilizador autenticado.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../registration/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth']['user_id'])) {
    echo json_encode(['success'=>false,'items'=>[]]); exit;
}
$uid = (int)$_SESSION['auth']['user_id'];

$st = $mysqli->prepare("
    SELECT
        ci.id          AS item_id,
        ci.quantity,
        ci.price       AS item_price,
        p.id           AS product_id,
        p.nome         AS product_name,
        p.preco        AS current_price,
        p.stock,
        p.imagem,
        p.image_path1,
        p.status       AS product_status,
        p.deleted_at   AS product_deleted,
        COALESCE(c.name,'Geral') AS category_name,
        COALESCE(c.icon,'box')   AS category_icon,
        COALESCE(u.nome,'')      AS company_name
    FROM shopping_carts sc
    INNER JOIN cart_items ci ON ci.cart_id = sc.id
    INNER JOIN products   p  ON p.id = ci.product_id
    LEFT  JOIN categories c  ON c.id = p.category_id
    LEFT  JOIN users      u  ON u.id = p.user_id
    WHERE sc.user_id = ? AND sc.status = 'active'
    ORDER BY ci.created_at DESC
");
$st->bind_param('i', $uid); $st->execute();
$items = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

foreach ($items as &$item) {
    $img = $item['image_path1'] ?: $item['imagem'];
    if (empty($img)) {
        $item['img_url'] = 'https://ui-avatars.com/api/?name='.urlencode($item['product_name']).'&size=200&background=00b96b&color=fff&font-size=0.1';
    } elseif (str_starts_with($img,'http')) {
        $item['img_url'] = $img;
    } else {
        $item['img_url'] = 'uploads/products/' . $img;
    }
    $item['available']     = $item['product_status']==='ativo' && $item['product_deleted']===null && (int)$item['stock']>0;
    $item['price_changed'] = abs((float)$item['item_price']-(float)$item['current_price'])>0.01;
    unset($item['imagem'],$item['image_path1']);
}
unset($item);

echo json_encode(['success'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);