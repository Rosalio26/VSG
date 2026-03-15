<?php
/**
 * pages/person/actions/get_products.php
 * Retorna produtos para o grid do dashboard.
 * Suporta filtros: search, categories (eco_badges), price_range, in_stock.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../registration/includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']); exit;
}

/* ── Parâmetros ─────────────────────────────────────────────── */
$search      = trim($_GET['search']      ?? '');
$categories  = array_filter(explode(',', trim($_GET['categories'] ?? '')));
$price_range = trim($_GET['price_range'] ?? '');
$in_stock    = !empty($_GET['in_stock']);
$limit       = min(48, max(8, (int)($_GET['limit'] ?? 24)));

/* ── WHERE dinâmico ─────────────────────────────────────────── */
$where  = ["p.status = 'ativo'", "p.deleted_at IS NULL", "p.stock > 0"];
$params = [];
$types  = '';

if ($search !== '') {
    $wild     = '%' . $search . '%';
    $where[]  = "(p.nome LIKE ? OR p.descricao LIKE ? OR u.nome LIKE ?)";
    $params[] = $wild; $params[] = $wild; $params[] = $wild;
    $types   .= 'sss';
}

// Filtro por categorias eco (coluna eco_badges é JSON)
if (!empty($categories)) {
    $eco_parts = [];
    foreach ($categories as $cat) {
        $eco_parts[] = "JSON_CONTAINS(p.eco_badges, JSON_QUOTE(?))";
        $params[]    = $cat;
        $types      .= 's';
    }
    $where[] = '(' . implode(' OR ', $eco_parts) . ')';
}

if ($price_range !== '') {
    $parts = explode('-', $price_range);
    if (count($parts) === 2) {
        $min = (float)$parts[0];
        $max = (float)$parts[1];
        if ($min > 0)      { $where[] = 'p.preco >= ?'; $params[] = $min; $types .= 'd'; }
        if ($max < 999999) { $where[] = 'p.preco <= ?'; $params[] = $max; $types .= 'd'; }
    }
}

$where_sql = implode(' AND ', $where);

/* ── Query ──────────────────────────────────────────────────── */
$sql = "
    SELECT
        p.id, p.nome, p.descricao, p.preco, p.currency,
        p.imagem, p.image_path1, p.stock, p.eco_badges,
        p.created_at, p.total_sales,
        COALESCE(p.stock_minimo, 3)    AS stock_minimo,
        COALESCE(c.name,  'Geral')     AS category_name,
        COALESCE(c.icon,  'box')       AS category_icon,
        COALESCE(u.nome, 'VisionGreen') AS empresa_nome
    FROM products p
    LEFT JOIN users      u ON u.id = p.user_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE {$where_sql}
    ORDER BY p.total_sales DESC, p.created_at DESC
    LIMIT ?
";

$params[] = $limit;
$types   .= 'i';

$st = $mysqli->prepare($sql);
if (!$st) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $mysqli->error]); exit;
}

$st->bind_param($types, ...$params);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// Processar eco_badges para o JS
foreach ($rows as &$row) {
    $row['eco_badges'] = json_decode($row['eco_badges'] ?? '[]', true) ?: [];
    // 'categoria' para compatibilidade com o JS do dashboard
    // usa o primeiro eco_badge como categoria, ou o nome da categoria do JOIN
    $row['categoria'] = !empty($row['eco_badges'])
        ? $row['eco_badges'][0]
        : strtolower(str_replace(' ', '_', $row['category_name'] ?? 'outro'));
}
unset($row);

echo json_encode([
    'success'  => true,
    'products' => $rows,
    'total'    => count($rows),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);