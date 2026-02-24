<?php
// ==================== INICIALIZAÇÃO ====================
require_once __DIR__ . '/registration/bootstrap.php';
require_once __DIR__ . '/registration/includes/security.php';
require_once __DIR__ . '/geo_location.php';

ob_start();

// ==================== AUTENTICAÇÃO ====================
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_name      = $user_logged_in ? ($_SESSION['auth']['nome']   ?? 'Usuário') : null;
$user_avatar    = $user_logged_in ? ($_SESSION['auth']['avatar'] ?? null)      : null;
$user_type      = $user_logged_in ? ($_SESSION['auth']['type']   ?? 'person')  : null;

// ==================== FLASH ====================
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type    = $_SESSION['flash_type']    ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ==================== CSRF ====================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== LOCALIZAÇÃO E MOEDA ====================
$user_country      = '';
$cart_count        = 0;

if ($user_logged_in) {
    $user_id = (int)$_SESSION['auth']['user_id'];
    $stmt = $mysqli->prepare("
        SELECT u.country,
               COALESCE((
                   SELECT SUM(ci.quantity)
                   FROM shopping_carts sc
                   INNER JOIN cart_items ci ON sc.id = ci.cart_id
                   WHERE sc.user_id = ? AND sc.status = 'active'
               ), 0) AS cart_total
        FROM users u WHERE u.id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('ii', $user_id, $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $user_country = $row['country'] ?? '';
        $cart_count   = (int)($row['cart_total'] ?? 0);
    }
}

if (empty($user_country) && !empty($_SESSION['user_location']['country'])) {
    $user_country = $_SESSION['user_location']['country'];
}

$user_currency_info = get_user_currency_info($user_country ?: 'MZ');

// ==================== PARÂMETROS DE FILTRO ====================
$search      = trim($_GET['search']      ?? '');
$category_id = (int)($_GET['category']   ?? 0);
$sort        = $_GET['sort']             ?? 'recent';
$min_price   = (float)($_GET['min']      ?? 0);
$max_price   = (float)($_GET['max']      ?? 0);
$supplier_id = (int)($_GET['supplier']   ?? 0);
$eco_filter  = trim($_GET['eco']         ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 24;
$offset      = ($page - 1) * $per_page;

$sort_map = [
    'recent'     => 'p.created_at DESC',
    'bestseller' => 'p.total_sales DESC, p.id DESC',
    'price_asc'  => 'p.preco ASC',
    'price_desc' => 'p.preco DESC',
    'rating'     => 'avg_rating DESC',
];
$order_sql = $sort_map[$sort] ?? $sort_map['recent'];

// ==================== CATEGORIAS ====================
$categories = [];
$cr = $mysqli->query("
    SELECT c.id, c.name, c.icon,
        COALESCE((
            SELECT COUNT(DISTINCT p.id) FROM products p
            WHERE p.category_id = c.id AND p.status = 'ativo' AND p.deleted_at IS NULL
        ), 0) AS cnt
    FROM categories c
    WHERE c.parent_id IS NULL AND c.status = 'ativa'
    ORDER BY cnt DESC, c.name ASC LIMIT 20
");
if ($cr) { $categories = $cr->fetch_all(MYSQLI_ASSOC); $cr->free(); }

// ==================== FORNECEDORES ====================
$suppliers = [];
$sr = $mysqli->query("
    SELECT u.id, u.nome, COUNT(DISTINCT p.id) AS cnt
    FROM users u
    INNER JOIN products p ON p.user_id = u.id AND p.status = 'ativo' AND p.deleted_at IS NULL
    WHERE u.type = 'company' AND u.status = 'active'
    GROUP BY u.id, u.nome ORDER BY cnt DESC LIMIT 15
");
if ($sr) { $suppliers = $sr->fetch_all(MYSQLI_ASSOC); $sr->free(); }

// ==================== ECO BADGES ====================
$eco_opts = [
    'organico'       => 'Orgânico',
    'reciclavel'     => 'Reciclável',
    'biodegradavel'  => 'Biodegradável',
    'compostavel'    => 'Compostável',
    'zero_waste'     => 'Zero Waste',
    'comercio_justo' => 'Comércio Justo',
    'certificado'    => 'Certificado',
    'vegano'         => 'Vegano',
];

// ==================== QUERY DE PRODUTOS ====================
$where  = ["p.status = 'ativo'", "p.deleted_at IS NULL", "p.stock > 0"];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = "(p.nome LIKE ? OR p.descricao LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s; $types .= 'ss';
}
if ($category_id > 0) { $where[] = "p.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
if ($supplier_id > 0) { $where[] = "p.user_id = ?";     $params[] = $supplier_id; $types .= 'i'; }
if ($min_price   > 0) { $where[] = "p.preco >= ?";      $params[] = $min_price;   $types .= 'd'; }
if ($max_price   > 0) { $where[] = "p.preco <= ?";      $params[] = $max_price;   $types .= 'd'; }
if ($eco_filter !== '') { $where[] = "JSON_CONTAINS(p.eco_badges, JSON_QUOTE(?))"; $params[] = $eco_filter; $types .= 's'; }

$where_sql = implode(' AND ', $where);

// Contagem
$total_rows = 0;
$count_sql  = "SELECT COUNT(DISTINCT p.id) AS n FROM products p WHERE $where_sql";
if ($types && $params) {
    $stmt = $mysqli->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_rows = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
} else {
    $r = $mysqli->query($count_sql);
    if ($r) $total_rows = (int)$r->fetch_assoc()['n'];
}

$total_pages = max(1, ceil($total_rows / $per_page));
$page        = min($page, $total_pages);

// Produtos
$products_sql = "
    SELECT p.id, p.nome, p.preco, p.currency, p.imagem, p.image_path1,
           p.stock, p.created_at, p.eco_badges, p.total_sales,
           COALESCE(c.name, '')                AS category_name,
           COALESCE(c.icon, 'box')             AS category_icon,
           COALESCE(u.nome, '')                AS company_name,
           COALESCE(ROUND(AVG(cr.rating),1),0) AS avg_rating,
           COUNT(DISTINCT cr.id)               AS review_count,
           DATEDIFF(NOW(), p.created_at)       AS days_old
    FROM products p
    LEFT JOIN categories       c  ON c.id         = p.category_id
    LEFT JOIN users            u  ON u.id          = p.user_id
    LEFT JOIN customer_reviews cr ON cr.product_id = p.id
    WHERE $where_sql
    GROUP BY p.id, p.nome, p.preco, p.currency, p.imagem, p.image_path1,
             p.stock, p.created_at, p.eco_badges, p.total_sales, c.name, c.icon, u.nome
    ORDER BY $order_sql
    LIMIT ? OFFSET ?
";

$products    = [];
$all_params  = array_merge($params, [$per_page, $offset]);
$all_types   = $types . 'ii';

$stmt = $mysqli->prepare($products_sql);
if ($stmt) {
    $stmt->bind_param($all_types, ...$all_params);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ==================== HELPERS ====================
define('PRODUCT_NEW_DAYS', 7);

function getProductImageUrl($p) {
    if (!empty($p['imagem']))      return 'pages/uploads/products/' . htmlspecialchars($p['imagem'],      ENT_QUOTES, 'UTF-8');
    if (!empty($p['image_path1'])) return 'pages/uploads/products/' . htmlspecialchars($p['image_path1'], ENT_QUOTES, 'UTF-8');
    return "https://ui-avatars.com/api/?name=" . urlencode($p['nome']) . "&size=400&background=00b96b&color=fff&bold=true&font-size=0.1";
}
function escapeHtml($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function isProductNew($p) { return ($p['days_old'] ?? 999) <= PRODUCT_NEW_DAYS; }

// Filtros activos
$active_filters = [];
if ($search !== '')    $active_filters[] = ['label' => "Busca: $search",  'remove' => 'search'];
if ($category_id > 0) {
    $cn = '';
    foreach ($categories as $c) if ($c['id'] == $category_id) { $cn = $c['name']; break; }
    $active_filters[] = ['label' => "Categoria: $cn", 'remove' => 'category'];
}
if ($supplier_id > 0) {
    $sn = '';
    foreach ($suppliers as $s) if ($s['id'] == $supplier_id) { $sn = $s['nome']; break; }
    $active_filters[] = ['label' => "Fornecedor: $sn", 'remove' => 'supplier'];
}
if ($min_price > 0 || $max_price > 0) {
    $lbl = 'Preço: ';
    if ($min_price > 0) $lbl .= 'MT ' . number_format($min_price, 0, '.', ',');
    $lbl .= ' — ';
    if ($max_price > 0) $lbl .= 'MT ' . number_format($max_price, 0, '.', ',');
    $active_filters[] = ['label' => $lbl, 'remove' => 'price'];
}
if ($eco_filter !== '') $active_filters[] = ['label' => "Eco: " . ($eco_opts[$eco_filter] ?? $eco_filter), 'remove' => 'eco'];

function removeFilter($key) {
    $p = $_GET;
    if ($key === 'price') { unset($p['min'], $p['max']); } else { unset($p[$key]); }
    unset($p['page']);
    return 'shopping.php' . ($p ? '?' . http_build_query($p) : '');
}
function filterUrl($key, $val) {
    $p = $_GET; $p[$key] = $val; unset($p['page']);
    return 'shopping.php?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="VSG Marketplace — <?= $total_rows ?> produtos sustentáveis disponíveis">
<title>Shopping — VSG Marketplace</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">

<!-- Reutilizar CSS existente -->
<link rel="stylesheet" href="assets/style/footer.css">
<link rel="stylesheet" href="assets/style/index_start.css">
<link rel="stylesheet" href="assets/style/index_start_search_product.css">
<link rel="stylesheet" href="assets/style/currency_styles.css">

<style>
/* ── TOKENS (mesmo sistema existente) ───────────────── */
:root {
    --primary-green:    #00b96b;
    --secondary-green:  #059669;
    --primary-blue:     #0066c0;
    --gh-canvas-default:#ffffff;
    --gh-canvas-subtle: #f6f8fa;
    --gh-border-default:#d0d7de;
    --gh-fg-default:    #1f2328;
    --gh-fg-muted:      #656d76;
    --gh-shadow-small:  0 1px 0 rgba(31,35,40,.04);
    --gh-shadow-medium: 0 3px 6px rgba(140,149,159,.15);
    --gh-shadow-large:  0 8px 24px rgba(140,149,159,.2);
    --gh-borderRadius-small:  6px;
    --gh-borderRadius-medium: 8px;
    --gh-borderRadius-large:  12px;
    --transition-fast: .12s cubic-bezier(.02,.01,.47,1);
    --transition-base: .2s  cubic-bezier(.02,.01,.47,1);
}

/* ── RESET ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
    background: var(--gh-canvas-subtle);
    color: var(--gh-fg-default);
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}
a { text-decoration: none; color: inherit; }
img { display: block; }
.container { max-width: 1280px; margin: 0 auto; padding: 0 16px; }

/* ── TOP STRIP ─────────────────────────────────────── */
.top-strip {
    background: var(--gh-fg-default);
    color: rgba(255,255,255,.85);
    font-size: 12px; padding: 6px 0;
    border-bottom: 1px solid rgba(255,255,255,.1);
}
.top-strip-content { display: flex; justify-content: space-between; align-items: center; }
.top-left-info { display: flex; gap: 16px; align-items: center; }
.top-link {
    color: rgba(255,255,255,.85); display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 8px; border-radius: var(--gh-borderRadius-small); transition: color var(--transition-fast);
}
.top-link:hover { color: var(--primary-green); background: rgba(255,255,255,.05); }
.top-right-nav { display: flex; gap: 4px; list-style: none; }
.top-right-nav a { color: rgba(255,255,255,.85); padding: 4px 8px; border-radius: var(--gh-borderRadius-small); transition: color var(--transition-fast); }
.top-right-nav a:hover { color: var(--primary-green); background: rgba(255,255,255,.05); }
.divider { width: 1px; height: 16px; background: rgba(255,255,255,.15); margin: 0 4px; }

/* ── HEADER ────────────────────────────────────────── */
.main-header {
    background: var(--gh-canvas-default);
    border-bottom: 1px solid var(--gh-border-default);
    position: sticky; top: 0; z-index: 400;
    box-shadow: var(--gh-shadow-small);
}
.header-main { padding: 12px 0; display: flex; gap: 16px; align-items: center; }
.logo-container { display: flex; align-items: center; transition: opacity var(--transition-fast); }
.logo-container:hover { opacity: .8; }
.logo-text { font-size: 20px; font-weight: 600; color: var(--gh-fg-default); letter-spacing: -.3px; display: flex; align-items: baseline; }
.logo-accent { color: var(--primary-green); font-size: 24px; margin: 0 3px; }
.logo-name-market { font-size: 14px; font-weight: 400; color: var(--gh-fg-muted); }

.account-action {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-small);
    color: var(--gh-fg-default); font-size: 13px; font-weight: 500;
    background: var(--gh-canvas-default); transition: all var(--transition-fast);
}
.account-action:hover { border-color: #aaa; background: var(--gh-canvas-subtle); }
.user-avatar { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; }

.cart-link {
    position: relative; display: flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: var(--gh-borderRadius-small);
    color: var(--gh-fg-default); transition: background var(--transition-fast);
    margin-right: 8px;
}
.cart-link:hover { background: var(--gh-canvas-subtle); }
.cart-link i { font-size: 18px; }
.cart-badge {
    position: absolute; top: 0; right: 4px;
    background: var(--primary-green); color: #fff;
    font-size: 10px; font-weight: 700; min-width: 16px; height: 16px;
    border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 0 4px;
}

/* ── BREADCRUMB ────────────────────────────────────── */
.breadcrumb {
    background: var(--gh-canvas-default);
    border-bottom: 1px solid var(--gh-border-default);
    padding: 8px 0; font-size: 12px;
}
.breadcrumb-in { display: flex; align-items: center; gap: 6px; color: var(--gh-fg-muted); }
.breadcrumb-in a { color: var(--gh-fg-muted); transition: color var(--transition-fast); }
.breadcrumb-in a:hover { color: var(--primary-green); }
.breadcrumb-in i { font-size: 9px; }
.breadcrumb-in .cur { color: var(--gh-fg-default); font-weight: 600; }

/* ── LAYOUT ────────────────────────────────────────── */
.shop-wrap {
    max-width: 1280px; margin: 0 auto; padding: 20px 16px 64px;
    display: grid; grid-template-columns: 248px 1fr; gap: 20px;
}

/* ── SIDEBAR ─────────────────────────────────────────── */
.sidebar { display: flex; flex-direction: column; gap: 12px; }

.sb-card {
    background: var(--gh-canvas-default);
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-medium);
    overflow: hidden;
}
.sb-head {
    padding: 12px 16px 8px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--gh-border-default);
}
.sb-title {
    font-size: 11px; font-weight: 700; letter-spacing: .7px;
    text-transform: uppercase; color: var(--gh-fg-default);
    display: flex; align-items: center; gap: 6px;
}
.sb-title i { color: var(--primary-green); font-size: 11px; }
.sb-clear {
    font-size: 11px; color: var(--primary-green);
    background: none; border: none; cursor: pointer; font-family: inherit;
    transition: color var(--transition-fast);
}
.sb-clear:hover { color: var(--secondary-green); }

.sb-list { list-style: none; padding: 6px 8px; }
.sb-item a {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 8px; border-radius: var(--gh-borderRadius-small);
    font-size: 13px; color: var(--gh-fg-default);
    transition: background var(--transition-fast), color var(--transition-fast);
}
.sb-item a:hover { background: var(--gh-canvas-subtle); color: var(--primary-green); }
.sb-item.active a { background: #f0fdf4; color: var(--primary-green); font-weight: 600; }
.sb-item-l { display: flex; align-items: center; gap: 8px; }
.sb-item-l i { width: 14px; text-align: center; color: var(--gh-fg-muted); font-size: 11px; }
.sb-item.active .sb-item-l i { color: var(--primary-green); }
.sb-cnt {
    font-size: 11px; color: var(--gh-fg-muted);
    background: var(--gh-canvas-subtle); padding: 1px 7px; border-radius: 10px;
    border: 1px solid var(--gh-border-default);
}
.sb-item.active .sb-cnt { background: #dcfce7; color: var(--secondary-green); border-color: #86efac; }

.sb-price { padding: 12px 16px; }
.price-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
.price-inputs label { font-size: 11px; color: var(--gh-fg-muted); margin-bottom: 3px; display: block; }
.price-inputs input {
    width: 100%; padding: 6px 10px;
    border: 1px solid var(--gh-border-default); border-radius: var(--gh-borderRadius-small);
    font-family: inherit; font-size: 12.5px; color: var(--gh-fg-default);
    background: var(--gh-canvas-subtle); outline: none;
    transition: border-color var(--transition-fast);
}
.price-inputs input:focus { border-color: var(--primary-green); background: #fff; }
.btn-apply-price {
    width: 100%; padding: 7px;
    background: var(--primary-green); border: none; border-radius: var(--gh-borderRadius-small);
    color: #fff; font-family: inherit; font-size: 12.5px; font-weight: 600;
    cursor: pointer; transition: background var(--transition-fast);
}
.btn-apply-price:hover { background: var(--secondary-green); }

.sb-eco { padding: 6px 10px 10px; display: flex; flex-direction: column; gap: 2px; }
.eco-opt {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 8px; border-radius: var(--gh-borderRadius-small);
    cursor: pointer; transition: background var(--transition-fast);
}
.eco-opt:hover { background: var(--gh-canvas-subtle); }
.eco-opt input[type=radio] { accent-color: var(--primary-green); }
.eco-opt span { font-size: 12.5px; color: var(--gh-fg-default); }
.eco-opt.active span { color: var(--primary-green); font-weight: 600; }

/* ── ÁREA PRINCIPAL ─────────────────────────────────── */
.main-area { min-width: 0; }

.mob-filter-btn {
    display: none; align-items: center; gap: 8px;
    padding: 8px 14px; margin-bottom: 12px;
    background: var(--gh-canvas-default); border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-medium); font-family: inherit; font-size: 13px;
    color: var(--gh-fg-default); cursor: pointer; transition: border-color var(--transition-fast);
}
.mob-filter-btn:hover { border-color: var(--primary-green); color: var(--primary-green); }
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 450; backdrop-filter: blur(2px);
}

/* Pills filtros activos */
.active-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.af-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; background: #f0fdf4; border: 1px solid #86efac;
    border-radius: 20px; font-size: 12px; color: var(--secondary-green); font-weight: 600;
}
.af-pill a { color: var(--secondary-green); font-size: 14px; line-height: 1; transition: color var(--transition-fast); }
.af-pill a:hover { color: #dc2626; }
.af-clear-all {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; background: none; border: 1px solid var(--gh-border-default);
    border-radius: 20px; font-size: 12px; color: var(--gh-fg-muted);
    cursor: pointer; font-family: inherit; transition: all var(--transition-fast);
}
.af-clear-all:hover { border-color: #dc2626; color: #dc2626; }

/* Barra de controlo */
.ctrl-bar {
    background: var(--gh-canvas-default); border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-medium); padding: 10px 16px;
    display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
}
.ctrl-count { font-size: 13px; color: var(--gh-fg-muted); flex: 1; }
.ctrl-count strong { color: var(--gh-fg-default); }

.ctrl-sort { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gh-fg-muted); }
.ctrl-sort select {
    padding: 5px 26px 5px 10px;
    border: 1px solid var(--gh-border-default); border-radius: var(--gh-borderRadius-small);
    font-family: inherit; font-size: 13px; color: var(--gh-fg-default);
    background: var(--gh-canvas-subtle); outline: none; cursor: pointer; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23656d76' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 8px center;
}
.ctrl-sort select:focus { border-color: var(--primary-green); }

.ctrl-view { display: flex; gap: 4px; }
.view-btn {
    width: 30px; height: 30px;
    background: none; border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-small);
    display: grid; place-items: center; cursor: pointer;
    color: var(--gh-fg-muted); font-size: 13px; transition: all var(--transition-fast);
}
.view-btn.active, .view-btn:hover { background: var(--primary-green); border-color: var(--primary-green); color: #fff; }

/* Grid de produtos */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(215px, 1fr));
    gap: 14px;
}
.products-grid.list-view { grid-template-columns: 1fr; }
.products-grid.list-view .product-card { flex-direction: row; }
.products-grid.list-view .product-image-container {
    padding-top: 0; width: 160px; min-width: 160px; height: 160px; flex-shrink: 0;
}
.products-grid.list-view .product-image-container img { position: static; width: 100%; height: 100%; }

/* Reusa .product-card do index_start.css — overrides */
.product-card {
    background: var(--gh-canvas-default);
    border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-medium);
    overflow: hidden; display: flex; flex-direction: column;
    transition: all var(--transition-base);
    text-decoration: none; color: inherit; cursor: pointer;
}
.product-card:hover {
    box-shadow: var(--gh-shadow-medium);
    transform: translateY(-2px);
    border-color: rgba(0,185,107,.3);
}
.product-image-container {
    position: relative; width: 100%; padding-top: 100%;
    background: var(--gh-canvas-subtle); overflow: hidden;
}
.product-image {
    position: absolute; inset: 0;
    width: 100%; height: 100%; object-fit: cover;
    transition: transform var(--transition-base);
}
.product-card:hover .product-image { transform: scale(1.04); }

.product-badge {
    position: absolute; top: 8px; left: 8px;
    padding: 3px 8px; border-radius: var(--gh-borderRadius-small);
    font-size: 10px; font-weight: 700; text-transform: uppercase; z-index: 2;
    background: var(--primary-green); color: #fff;
}
.product-badge.new { background: #3b82f6; }
.product-badge.eco { background: #d1fae5; color: #065f46; }

.product-actions {
    position: absolute; top: 8px; right: 8px;
    display: flex; flex-direction: column; gap: 5px; z-index: 2;
    opacity: 0; transform: translateX(6px);
    transition: opacity var(--transition-base), transform var(--transition-base);
}
.product-card:hover .product-actions { opacity: 1; transform: translateX(0); }
.action-btn {
    width: 30px; height: 30px;
    background: #fff; border: 1px solid var(--gh-border-default); border-radius: 50%;
    display: grid; place-items: center; cursor: pointer;
    color: var(--gh-fg-muted); font-size: 13px; box-shadow: var(--gh-shadow-small);
    transition: all var(--transition-fast);
}
.action-btn:hover { background: var(--primary-green); color: #fff; border-color: var(--primary-green); }

.product-info { padding: 14px; flex: 1; display: flex; flex-direction: column; }
.product-category {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; color: var(--primary-green); font-weight: 600;
    text-transform: uppercase; margin-bottom: 6px;
}
.product-name {
    font-size: 13.5px; font-weight: 600; color: var(--gh-fg-default);
    margin-bottom: 6px; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    min-height: 38px;
}
.product-supplier { font-size: 12px; color: var(--gh-fg-muted); margin-bottom: 10px; display: flex; align-items: center; gap: 4px; }

.product-rating { display: flex; align-items: center; gap: 6px; margin-bottom: 12px; }
.stars { display: flex; gap: 2px; }
.stars i { font-size: 11px; color: #fbbf24; }
.stars i.fa-regular { color: var(--gh-border-default); }
.rating-text { font-size: 13px; font-weight: 600; }
.rating-count { font-size: 12px; color: var(--gh-fg-muted); }

.product-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 10px; border-top: 1px solid var(--gh-border-default); margin-top: auto;
}
.product-price { display: flex; flex-direction: column; }
.price-currency { font-size: 11px; color: var(--gh-fg-muted); font-weight: 500; }
.price-value { font-size: 1.2rem; font-weight: 700; color: var(--primary-green); }

.stock-badge {
    font-size: 11px; padding: 3px 8px; border-radius: var(--gh-borderRadius-small);
    font-weight: 600; border: 1px solid;
}
.stock-badge.high { background: #dafbe1; color: #116329; border-color: #abf2bc; }
.stock-badge.low  { background: #fff1f0; color: #cf222e; border-color: #ffcecb; }

/* ── ESTADO VAZIO ───────────────────────────────────── */
.empty-state {
    grid-column: 1/-1; text-align: center; padding: 64px 24px;
    background: var(--gh-canvas-default); border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-large);
}
.empty-state i { font-size: 48px; color: var(--gh-border-default); margin-bottom: 16px; display: block; }
.empty-state h3 { font-size: 1.15rem; font-weight: 600; margin-bottom: 8px; }
.empty-state p { font-size: 13.5px; color: var(--gh-fg-muted); margin-bottom: 20px; }
.empty-state a {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 20px; background: var(--primary-green); border-radius: var(--gh-borderRadius-small);
    color: #fff; font-weight: 600; font-size: 13.5px; transition: background var(--transition-fast);
}
.empty-state a:hover { background: var(--secondary-green); }

/* ── PAGINAÇÃO ──────────────────────────────────────── */
.pagination { display: flex; align-items: center; justify-content: center; gap: 5px; margin-top: 28px; }
.pg-btn {
    min-width: 34px; height: 34px; padding: 0 8px;
    background: var(--gh-canvas-default); border: 1px solid var(--gh-border-default);
    border-radius: var(--gh-borderRadius-small); font-family: inherit; font-size: 13px;
    color: var(--gh-fg-default); cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all var(--transition-fast);
}
.pg-btn:hover { border-color: var(--primary-green); color: var(--primary-green); }
.pg-btn.active { background: var(--primary-green); border-color: var(--primary-green); color: #fff; font-weight: 700; }
.pg-btn:disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }
.pg-dots { color: var(--gh-fg-muted); padding: 0 2px; line-height: 34px; }

/* ── BACK TO TOP ────────────────────────────────────── */
.back-to-top {
    position: fixed; bottom: 24px; right: 24px;
    width: 38px; height: 38px;
    background: var(--gh-canvas-default); color: var(--gh-fg-default);
    border: 1px solid var(--gh-border-default); border-radius: 50%;
    cursor: pointer; display: none; place-items: center; font-size: 15px;
    box-shadow: var(--gh-shadow-medium); z-index: 300; transition: all var(--transition-base);
}
.back-to-top.visible { display: grid; }
.back-to-top:hover { background: var(--gh-canvas-subtle); transform: translateY(-2px); }

/* ── RESPONSIVE ─────────────────────────────────────── */
@media (max-width: 1024px) { .shop-wrap { grid-template-columns: 220px 1fr; gap: 14px; } }
@media (max-width: 820px) {
    .shop-wrap { grid-template-columns: 1fr; }
    .sidebar {
        position: fixed; top: 0; left: -280px; bottom: 0; width: 280px;
        background: var(--gh-canvas-subtle); z-index: 500; overflow-y: auto;
        padding: 20px 12px; transition: left .3s ease;
        box-shadow: 4px 0 24px rgba(0,0,0,.2);
    }
    .sidebar.open { left: 0; }
    .sidebar-overlay.open { display: block; }
    .mob-filter-btn { display: flex; }
    .search-container { display: none; }
}
@media (max-width: 560px) {
    .shop-wrap { padding: 12px 12px 48px; }
    .products-grid { grid-template-columns: repeat(2,1fr); gap: 10px; }
    .top-left-info { display: none; }
}
@media (max-width: 380px) { .products-grid { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
</style>
</head>
<body>

<?php if ($flash_message): ?>
<div class="flash-message flash-<?= escapeHtml($flash_type) ?>" id="flashMessage">
    <i class="fa-solid fa-<?= $flash_type === 'success' ? 'check-circle' : ($flash_type === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
    <span><?= escapeHtml($flash_message) ?></span>
    <button onclick="this.parentElement.remove()" class="flash-close">×</button>
</div>
<?php endif; ?>

<!-- ══ TOP STRIP ════════════════════════════════════════ -->
<div class="top-strip">
    <div class="container">
        <div class="top-strip-content">
            <div class="top-left-info">
                <span><i class="fa-solid fa-coins"></i> Moeda</span>
                <span id="currentCurrencyDisplay"><?= escapeHtml($user_currency_info['currency']) ?></span>
            </div>
            <ul class="top-right-nav">
                <?php if ($user_logged_in && $user_type === 'company'): ?>
                    <li><a href="pages/person/index.php">Meu Painel</a></li>
                    <li><span class="divider"></span></li>
                <?php endif; ?>
                <li><a href="#">Central de Ajuda</a></li>
                <li><span class="divider"></span></li>
                <li><a href="#">Rastrear Pedido</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- ══ HEADER ════════════════════════════════════════════ -->
<header class="main-header">
    <div class="container">
        <div class="header-main">
            <a href="index.php" class="logo-container">
                <div class="logo-text">VSG<span class="logo-accent">•</span><span class="logo-name-market">MARKETPLACE</span></div>
            </a>

            <!-- Search -->
            <div class="search-container">
                <form class="search-form" role="search" onsubmit="return false;">
                    <input type="text" id="searchInput" name="search"
                           placeholder="Buscar produtos sustentáveis..."
                           class="search-input"
                           value="<?= escapeHtml($search) ?>"
                           autocomplete="off">
                    <button type="button" id="searchBtn" class="search-btn"><i class="fa-solid fa-search"></i></button>
                    <button type="button" id="clearSearchBtn" class="clear-search-btn" style="display:<?= $search ? 'block' : 'none' ?>"><i class="fa-solid fa-times"></i></button>
                </form>
                <div id="searchResults" class="search-results-dropdown" style="display:none;"></div>
            </div>

            <a href="cart.php" class="cart-link">
                <i class="fa-solid fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>

            <?php if ($user_logged_in): ?>
                <a href="pages/person/index.php" class="account-action">
                    <?php if ($user_avatar): ?>
                        <img src="<?= escapeHtml($user_avatar) ?>" alt="avatar" class="user-avatar">
                    <?php else: ?>
                        <i class="fa-solid fa-user-circle"></i>
                    <?php endif; ?>
                    <span><?= escapeHtml($user_name) ?></span>
                </a>
            <?php else: ?>
                <a href="registration/login/login.php" class="account-action">
                    <i class="fa-solid fa-user-circle"></i><span>Entrar</span>
                </a>
                <a href="registration/register/painel_cadastro.php?tipo=business" class="account-action" style="background:var(--primary-green);color:#fff;border-color:var(--primary-green);">
                    <i class="fa-solid fa-building"></i><span>Vender</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ══ BREADCRUMB ════════════════════════════════════════ -->
<div class="breadcrumb">
    <div class="container">
        <div class="breadcrumb-in">
            <a href="index.php">Início</a>
            <i class="fa-solid fa-chevron-right"></i>
            <?php if ($category_id > 0):
                foreach ($categories as $c) { if ($c['id'] == $category_id) { echo '<span class="cur">' . escapeHtml($c['name']) . '</span>'; break; } }
            elseif ($search !== ''): ?>
                <span>Busca</span>
                <i class="fa-solid fa-chevron-right"></i>
                <span class="cur"><?= escapeHtml($search) ?></span>
            <?php else: ?>
                <span class="cur">Shopping</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ OVERLAY SIDEBAR ═══════════════════════════════════ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══ LAYOUT ════════════════════════════════════════════ -->
<div class="shop-wrap">

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar" id="sidebar">

        <!-- Categorias -->
        <div class="sb-card">
            <div class="sb-head">
                <div class="sb-title"><i class="fa-solid fa-compass"></i>Categorias</div>
                <?php if ($category_id > 0): ?><a href="<?= removeFilter('category') ?>" class="sb-clear">Limpar</a><?php endif; ?>
            </div>
            <ul class="sb-list">
                <li class="sb-item <?= $category_id === 0 ? 'active' : '' ?>">
                    <a href="shopping.php<?= $search ? '?search='.urlencode($search) : '' ?>">
                        <div class="sb-item-l"><i class="fa-solid fa-border-all"></i>Todos</div>
                        <span class="sb-cnt"><?= number_format(array_sum(array_column($categories, 'cnt'))) ?></span>
                    </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                <li class="sb-item <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                    <a href="<?= filterUrl('category', $cat['id']) ?>">
                        <div class="sb-item-l">
                            <i class="fa-solid fa-<?= escapeHtml($cat['icon'] ?: 'box') ?>"></i>
                            <?= escapeHtml($cat['name']) ?>
                        </div>
                        <span class="sb-cnt"><?= number_format($cat['cnt']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Preço -->
        <div class="sb-card">
            <div class="sb-head">
                <div class="sb-title"><i class="fa-solid fa-tag"></i>Preço (MT)</div>
                <?php if ($min_price > 0 || $max_price > 0): ?><a href="<?= removeFilter('price') ?>" class="sb-clear">Limpar</a><?php endif; ?>
            </div>
            <div class="sb-price">
                <form action="shopping.php" method="get">
                    <?php foreach ($_GET as $k => $v): if (!in_array($k, ['min','max','page'])): ?>
                        <input type="hidden" name="<?= escapeHtml($k) ?>" value="<?= escapeHtml($v) ?>">
                    <?php endif; endforeach; ?>
                    <div class="price-inputs">
                        <div><label>Mínimo</label><input type="number" name="min" placeholder="0"    min="0" value="<?= $min_price > 0 ? $min_price : '' ?>"></div>
                        <div><label>Máximo</label><input type="number" name="max" placeholder="9999" min="0" value="<?= $max_price > 0 ? $max_price : '' ?>"></div>
                    </div>
                    <button type="submit" class="btn-apply-price">Aplicar</button>
                </form>
            </div>
        </div>

        <!-- Eco Badges -->
        <div class="sb-card">
            <div class="sb-head">
                <div class="sb-title"><i class="fa-solid fa-leaf"></i>Certificação</div>
                <?php if ($eco_filter !== ''): ?><a href="<?= removeFilter('eco') ?>" class="sb-clear">Limpar</a><?php endif; ?>
            </div>
            <div class="sb-eco">
                <?php foreach ($eco_opts as $val => $label): ?>
                <label class="eco-opt <?= $eco_filter === $val ? 'active' : '' ?>">
                    <input type="radio" name="eco" value="<?= escapeHtml($val) ?>"
                           <?= $eco_filter === $val ? 'checked' : '' ?>
                           onchange="window.location='<?= filterUrl('eco', $val) ?>'">
                    <span><?= escapeHtml($label) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Fornecedores -->
        <?php if (!empty($suppliers)): ?>
        <div class="sb-card">
            <div class="sb-head">
                <div class="sb-title"><i class="fa-solid fa-building"></i>Fornecedor</div>
                <?php if ($supplier_id > 0): ?><a href="<?= removeFilter('supplier') ?>" class="sb-clear">Limpar</a><?php endif; ?>
            </div>
            <ul class="sb-list">
                <?php foreach ($suppliers as $sup): ?>
                <li class="sb-item <?= $supplier_id == $sup['id'] ? 'active' : '' ?>">
                    <a href="<?= filterUrl('supplier', $sup['id']) ?>">
                        <div class="sb-item-l"><i class="fa-solid fa-store"></i><?= escapeHtml($sup['nome']) ?></div>
                        <span class="sb-cnt"><?= number_format($sup['cnt']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </aside>

    <!-- ─── ÁREA PRINCIPAL ─── -->
    <main class="main-area">

        <!-- Botão mobile filtros -->
        <button class="mob-filter-btn" onclick="openSidebar()">
            <i class="fa-solid fa-sliders"></i>Filtros
            <?php if (!empty($active_filters)): ?>
                <span style="background:var(--primary-green);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;"><?= count($active_filters) ?></span>
            <?php endif; ?>
        </button>

        <!-- Pills filtros activos -->
        <?php if (!empty($active_filters)): ?>
        <div class="active-filters">
            <?php foreach ($active_filters as $af): ?>
            <div class="af-pill">
                <?= escapeHtml($af['label']) ?>
                <a href="<?= removeFilter($af['remove']) ?>" title="Remover filtro">×</a>
            </div>
            <?php endforeach; ?>
            <a href="shopping.php">
                <button class="af-clear-all"><i class="fa-solid fa-xmark"></i>Limpar tudo</button>
            </a>
        </div>
        <?php endif; ?>

        <!-- Barra de controlo -->
        <div class="ctrl-bar">
            <div class="ctrl-count">
                <strong><?= number_format($total_rows) ?></strong> produto<?= $total_rows !== 1 ? 's' : '' ?> encontrado<?= $total_rows !== 1 ? 's' : '' ?>
                <?php if ($search !== ''): ?> para <strong>"<?= escapeHtml($search) ?>"</strong><?php endif; ?>
            </div>
            <div class="ctrl-sort">
                <span>Ordenar:</span>
                <select onchange="sortChange(this.value)">
                    <option value="recent"     <?= $sort==='recent'     ?'selected':'' ?>>Mais Recentes</option>
                    <option value="bestseller" <?= $sort==='bestseller' ?'selected':'' ?>>Mais Vendidos</option>
                    <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':'' ?>>Menor Preço</option>
                    <option value="price_desc" <?= $sort==='price_desc' ?'selected':'' ?>>Maior Preço</option>
                    <option value="rating"     <?= $sort==='rating'     ?'selected':'' ?>>Melhor Avaliados</option>
                </select>
            </div>
            <div class="ctrl-view">
                <button class="view-btn active" id="btnGrid" onclick="setView('grid')" title="Grade"><i class="fa-solid fa-grid-2"></i></button>
                <button class="view-btn"        id="btnList" onclick="setView('list')" title="Lista"><i class="fa-solid fa-list"></i></button>
            </div>
        </div>

        <!-- Grid de Produtos -->
        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open"></i>
                <h3>Nenhum produto encontrado</h3>
                <p>Tente ajustar os filtros ou buscar por outros termos.</p>
                <a href="shopping.php"><i class="fa-solid fa-rotate-left"></i>Ver todos os produtos</a>
            </div>
            <?php else: ?>
                <?php foreach ($products as $product):
                    $img        = getProductImageUrl($product);
                    $avg_rating = $product['avg_rating'] ?? 0;
                    $rating     = round($avg_rating);
                    $isNew      = isProductNew($product);
                    $isHot      = $product['total_sales'] > 0;
                    $isLow      = $product['stock'] > 0 && $product['stock'] <= 10;
                    $eco_raw    = $product['eco_badges'] ? json_decode($product['eco_badges'], true) : [];
                    $eco_first  = is_array($eco_raw) && !empty($eco_raw) ? ($eco_opts[$eco_raw[0]] ?? null) : null;
                    $price_conv = format_product_price($product, $user_country);
                ?>
                <a href="shopping.php?product=<?= $product['id'] ?>" class="product-card">
                    <div class="product-image-container">
                        <img class="product-image" src="<?= $img ?>"
                             alt="<?= escapeHtml($product['nome']) ?>" loading="lazy"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($product['nome']) ?>&size=400&background=00b96b&color=fff&font-size=0.1'">

                        <?php if ($isNew): ?><span class="product-badge new">Novo</span>
                        <?php elseif ($isHot): ?><span class="product-badge">Hot</span>
                        <?php elseif ($eco_first): ?><span class="product-badge eco"><?= escapeHtml($eco_first) ?></span>
                        <?php endif; ?>

                        <div class="product-actions">
                            <button class="action-btn" onclick="event.preventDefault()" title="Favoritar"><i class="fa-regular fa-heart"></i></button>
                            <button class="action-btn" onclick="event.preventDefault()" title="Ver rápido"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="product-info">
                        <div class="product-category">
                            <i class="fa-solid fa-<?= escapeHtml($product['category_icon']) ?>"></i>
                            <?= escapeHtml($product['category_name'] ?: 'Geral') ?>
                        </div>
                        <h3 class="product-name"><?= escapeHtml($product['nome']) ?></h3>
                        <div class="product-supplier"><i class="fa-solid fa-building"></i><?= escapeHtml($product['company_name'] ?: 'Fornecedor') ?></div>

                        <div class="product-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-<?= $i <= $rating ? 'solid' : 'regular' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-text"><?= $avg_rating > 0 ? number_format($avg_rating,1) : '0' ?></span>
                            <span class="rating-count">(<?= $product['review_count'] ?>)</span>
                        </div>

                        <div class="product-footer">
                            <div class="product-price">
                                <span class="price-currency"><?= escapeHtml($price_conv['symbol']) ?></span>
                                <span class="price-value" data-price-mzn="<?= $product['preco'] ?>">
                                    <?= number_format($price_conv['amount'], 2, ',', '.') ?>
                                </span>
                            </div>
                            <span class="stock-badge <?= $isLow ? 'low' : 'high' ?>">
                                <?= $isLow ? "Últimas {$product['stock']}" : "Em estoque" ?>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1):
            $qs = $_GET; unset($qs['page']);
            $base = 'shopping.php' . ($qs ? '?' . http_build_query($qs) : '');
        ?>
        <div class="pagination">
            <?php
            $prev = $page <= 1 ? 'disabled' : '';
            echo "<button class='pg-btn' $prev onclick=\"location='{$base}" . ($qs ? '&' : '?') . "page=" . max(1,$page-1) . "'\"><i class='fa-solid fa-chevron-left'></i></button>";

            $start = max(1, $page-2); $end = min($total_pages, $page+2);
            if ($start > 1) { echo "<button class='pg-btn' onclick=\"location='{$base}" . ($qs?'&':'?') . "page=1'\">1</button>"; if ($start > 2) echo "<span class='pg-dots'>…</span>"; }
            for ($i = $start; $i <= $end; $i++) {
                $a = $i === $page ? 'active' : '';
                echo "<button class='pg-btn $a' onclick=\"location='{$base}" . ($qs?'&':'?') . "page=$i'\">$i</button>";
            }
            if ($end < $total_pages) { if ($end < $total_pages-1) echo "<span class='pg-dots'>…</span>"; echo "<button class='pg-btn' onclick=\"location='{$base}" . ($qs?'&':'?') . "page=$total_pages'\">$total_pages</button>"; }

            $next = $page >= $total_pages ? 'disabled' : '';
            echo "<button class='pg-btn' $next onclick=\"location='{$base}" . ($qs?'&':'?') . "page=" . min($total_pages,$page+1) . "'\"><i class='fa-solid fa-chevron-right'></i></button>";
            ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<button id="backToTop" class="back-to-top" title="Voltar ao topo"><i class="fa-solid fa-arrow-up"></i></button>

<?php include 'includes/footer.html'; ?>

<script src="assets/scripts/currency_exchange.js" defer></script>
<script src="assets/scripts/main_index.js" defer></script>

<script>
/* ── Sidebar mobile ─────────────────────── */
function openSidebar()  {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

/* ── Ordenação ──────────────────────────── */
function sortChange(val) {
    const p = new URLSearchParams(window.location.search);
    p.set('sort', val); p.delete('page');
    window.location = 'shopping.php?' + p.toString();
}

/* ── Vista grid / lista ─────────────────── */
function setView(v) {
    const grid = document.getElementById('productsGrid');
    const btnG = document.getElementById('btnGrid');
    const btnL = document.getElementById('btnList');
    if (v === 'list') {
        grid.classList.add('list-view');
        btnL.classList.add('active'); btnG.classList.remove('active');
    } else {
        grid.classList.remove('list-view');
        btnG.classList.add('active'); btnL.classList.remove('active');
    }
    try { localStorage.setItem('vsg_shop_view', v); } catch(e) {}
}
(function() { try { if (localStorage.getItem('vsg_shop_view') === 'list') setView('list'); } catch(e) {} })();

/* ── Back to top ────────────────────────── */
const btt = document.getElementById('backToTop');
window.addEventListener('scroll', () => btt.classList.toggle('visible', scrollY > 400), {passive:true});
btt.addEventListener('click', () => window.scrollTo({top:0,behavior:'smooth'}));

/* ── Flash auto-hide ────────────────────── */
const flash = document.getElementById('flashMessage');
if (flash) setTimeout(() => { flash.style.opacity='0'; setTimeout(()=>flash.remove(),300); }, 5000);
</script>
</body>
</html>
<?php ob_end_flush(); ?>