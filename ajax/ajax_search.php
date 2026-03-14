<?php
/**
 * ajax/ajax_search.php — VSG Marketplace
 * Busca instantânea no dropdown do header.
 *
 * Localização: <raiz>/ajax/ajax_search.php
 * Chamado por: shopping.php via fetch('ajax/ajax_search.php?search=...')
 */

// ── Bootstrap ────────────────────────────────────────────────────
// Este ficheiro está em <raiz>/ajax/ — um nível abaixo da raiz
require_once __DIR__ . '/../registration/includes/db.php';

// ── Headers ──────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ── Parâmetros ────────────────────────────────────────────────────
$search_term = trim($_GET['search'] ?? '');
$limit       = min(20, max(1, (int)($_GET['limit'] ?? 8)));

if ($search_term === '') {
    echo json_encode(['success' => false, 'products' => [], 'total' => 0]);
    exit;
}

// ── Cache simples em ficheiro (5 min) ─────────────────────────────
$cache_key  = sys_get_temp_dir() . '/vsg_srch_' . md5($search_term . $limit) . '.json';
$cache_ttl  = 300;
if (file_exists($cache_key) && (time() - filemtime($cache_key)) < $cache_ttl) {
    header('X-Cache: HIT');
    readfile($cache_key);
    exit;
}
header('X-Cache: MISS');

// ── Query ─────────────────────────────────────────────────────────
$wild = '%' . $search_term . '%';

$st = $mysqli->prepare("
    SELECT
        p.id,
        p.nome,
        p.preco,
        p.currency,
        p.imagem,
        p.image_path1,
        p.stock,
        COALESCE(c.name, 'Geral') AS category_name,
        COALESCE(c.icon, 'box')   AS category_icon,
        COALESCE(u.nome, '')      AS company_name,
        CASE
            WHEN p.nome     LIKE ? THEN 1
            WHEN c.name     LIKE ? THEN 2
            WHEN u.nome     LIKE ? THEN 3
            WHEN p.descricao LIKE ? THEN 4
            ELSE 5
        END AS relevance_score
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN users      u ON u.id = p.user_id
    WHERE p.status     = 'ativo'
      AND p.deleted_at IS NULL
      AND p.stock      > 0
      AND (
          p.nome      LIKE ?
          OR p.descricao LIKE ?
          OR c.name   LIKE ?
          OR u.nome   LIKE ?
      )
    ORDER BY relevance_score ASC, p.total_sales DESC, p.id DESC
    LIMIT ?
");

if (!$st) {
    http_response_code(500);
    echo json_encode(['success' => false, 'products' => [], 'total' => 0,
                      'debug'   => $mysqli->error]);
    exit;
}

// 4 params no CASE + 4 no WHERE + 1 LIMIT = 9 params
$st->bind_param('ssssssssi',
    $wild, $wild, $wild, $wild,   // CASE
    $wild, $wild, $wild, $wild,   // WHERE
    $limit
);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// ── Processar resultados ──────────────────────────────────────────
$products = [];
foreach ($rows as $p) {
    // Imagem
    if (!empty($p['image_path1'])) {
        $img = 'uploads/products/' . $p['image_path1'];
    } elseif (!empty($p['imagem'])) {
        $img = str_starts_with($p['imagem'], 'http')
             ? $p['imagem']
             : 'uploads/products/' . $p['imagem'];
    } else {
        $img = 'https://ui-avatars.com/api/?name=' . urlencode($p['nome'])
             . '&size=200&background=00b96b&color=fff&font-size=0.1';
    }

    $products[] = [
        'id'             => (int)$p['id'],
        'nome'           => $p['nome'],
        'preco'          => (float)$p['preco'],
        'preco_formatado'=> number_format((float)$p['preco'], 2, ',', '.'),
        'currency'       => strtoupper($p['currency'] ?? 'MZN'),
        'imagem'         => $img,
        'stock'          => (int)$p['stock'],
        'category_name'  => $p['category_name'],
        'category_icon'  => $p['category_icon'],
        'company_name'   => $p['company_name'],
        'url'            => 'product.php?id=' . (int)$p['id'],
    ];
}

// ── Resposta ──────────────────────────────────────────────────────
$response = json_encode([
    'success'  => true,
    'products' => $products,
    'total'    => count($products),
    'message'  => count($products) > 0
        ? count($products) . ' produto(s) encontrado(s)'
        : 'Nenhum produto encontrado',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Guardar cache
@file_put_contents($cache_key, $response);

echo $response;