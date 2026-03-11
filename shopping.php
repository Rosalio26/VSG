<?php
/* ===== BOOTSTRAP ===== */
ob_start();

if (!defined('APP_ENV')) define('APP_ENV', 'dev');

if (APP_ENV === 'dev') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

date_default_timezone_set('UTC');

if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ===== DEPENDÊNCIAS ===== */
require_once __DIR__ . '/registration/includes/device.php';
require_once __DIR__ . '/registration/includes/rate_limit.php';
require_once __DIR__ . '/registration/includes/errors.php';
require_once __DIR__ . '/registration/includes/db.php';
require_once __DIR__ . '/includes/currency/currency_bootstrap.php';

/* ===== HELPERS ===== */
function esc($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function getProductImageUrl(array $p): string {
    foreach (['imagem', 'image_path1'] as $col) {
        if (empty($p[$col])) continue;
        $v = $p[$col];
        if (str_starts_with($v, 'http') || str_starts_with($v, 'pages/uploads/')) return esc($v);
        if (str_starts_with($v, 'products/')) return 'pages/uploads/' . esc($v);
        return 'pages/uploads/products/' . esc($v);
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($p['nome'] ?? 'P')
         . '&size=400&background=00b96b&color=fff&bold=true&font-size=0.1';
}

function removeFilter(string $key): string {
    $p = $_GET;
    if ($key === 'price') { unset($p['min'], $p['max']); } else { unset($p[$key]); }
    unset($p['page']);
    return 'shopping.php' . ($p ? '?' . http_build_query($p) : '');
}

function filterUrl(string $key, $val): string {
    $p = $_GET; $p[$key] = $val; unset($p['page']);
    return 'shopping.php?' . http_build_query($p);
}

function highlightTerm(string $text, string $term): string {
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if ($term === '') return $safe;
    $words = array_filter(explode(' ', preg_replace('/\s+/', ' ', $term)));
    foreach ($words as $w) {
        if (mb_strlen($w) < 2) continue;
        $pat  = '/' . preg_quote(htmlspecialchars($w, ENT_QUOTES, 'UTF-8'), '/') . '/iu';
        $safe = preg_replace($pat, '<mark>$0</mark>', $safe);
    }
    return $safe;
}

/* ===== MOEDA ===== */
$currency_map = [
    'MZ' => ['currency' => 'MZN', 'symbol' => 'MT',  'rate' => 1],
    'BR' => ['currency' => 'BRL', 'symbol' => 'R$',   'rate' => 0.062],
    'PT' => ['currency' => 'EUR', 'symbol' => '€',    'rate' => 0.015],
    'US' => ['currency' => 'USD', 'symbol' => '$',    'rate' => 0.016],
    'GB' => ['currency' => 'GBP', 'symbol' => '£',    'rate' => 0.013],
    'ZA' => ['currency' => 'ZAR', 'symbol' => 'R',    'rate' => 0.29],
    'AO' => ['currency' => 'AOA', 'symbol' => 'Kz',   'rate' => 15.2],
];
$user_country_code  = $_SESSION['auth']['country_code'] ?? $_SESSION['user_location']['country_code'] ?? 'MZ';
$user_currency_info = $currency_map[strtoupper($user_country_code)] ?? $currency_map['MZ'];

/* ===== SESSÃO / AUTH ===== */
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_name      = $user_logged_in ? ($_SESSION['auth']['nome']   ?? 'Usuário') : null;
$user_avatar    = $user_logged_in ? ($_SESSION['auth']['avatar'] ?? null)      : null;
$user_type      = $user_logged_in ? ($_SESSION['auth']['type']   ?? 'person')  : null;
$user_id        = $user_logged_in ? (int)$_SESSION['auth']['user_id']          : 0;

/* ===== FLASH ===== */
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type    = $_SESSION['flash_type']    ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

/* ===== PARÂMETROS GET ===== */
$search      = trim($_GET['search']    ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$sort        = $_GET['sort']           ?? 'recent';
$min_price   = (float)($_GET['min']    ?? 0);
$max_price   = (float)($_GET['max']    ?? 0);
$supplier_id = (int)($_GET['supplier'] ?? 0);
$eco_filter  = trim($_GET['eco']       ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 24;

$qs_no_page = $_GET; unset($qs_no_page['page']);
$pg_base    = 'shopping.php' . ($qs_no_page ? '?' . http_build_query($qs_no_page) . '&' : '?');

/* ===== ECO BADGES ===== */
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

/* ===== SORT ===== */
$sort_map = [
    'recent'     => 'p.created_at DESC',
    'bestseller' => 'p.total_sales DESC, p.id DESC',
    'price_asc'  => 'p.preco ASC',
    'price_desc' => 'p.preco DESC',
    'rating'     => 'p.total_sales DESC, p.created_at DESC',
];
$sort      = in_array($sort, array_keys($sort_map)) ? $sort : 'recent';
$order_sql = $sort_map[$sort];

/* ===== FULLTEXT CHECK ===== */
$use_fulltext = false;
$search_score = '0';
if ($search !== '') {
    $ft_check     = $mysqli->query("SELECT 1 FROM information_schema.STATISTICS WHERE table_schema=DATABASE() AND table_name='products' AND index_type='FULLTEXT' AND index_name='ft_products_search' LIMIT 1");
    $use_fulltext = $ft_check && $ft_check->num_rows > 0;
    if ($ft_check) $ft_check->free();
}

/* ===== WHERE DINÂMICO ===== */
$where  = ["p.status = 'ativo'", "p.deleted_at IS NULL", "p.stock > 0"];
$params = [];
$types  = '';

if ($search !== '') {
    if ($use_fulltext) {
        $ft_term = '+' . implode(' +', array_filter(explode(' ', preg_replace('/[^\w\s]/u', ' ', $search)))) . '*';
        if (trim(str_replace('+', '', $ft_term)) === '*') {
            $use_fulltext = false;
        } else {
            $where[]      = "MATCH(p.nome, p.descricao) AGAINST(? IN BOOLEAN MODE)";
            $search_score = "MATCH(p.nome, p.descricao) AGAINST(? IN BOOLEAN MODE)";
            $params[]     = $ft_term; $types .= 's';
        }
    }
    if (!$use_fulltext) {
        $where[]      = "(p.nome LIKE ? OR p.descricao LIKE ?)";
        $wild         = "%{$search}%";
        $params[]     = $wild; $params[] = $wild; $types .= 'ss';
        $search_score = "(CASE WHEN p.nome LIKE ? THEN 2 ELSE 1 END)";
    }
}
if ($category_id > 0) { $where[] = 'p.category_id = ?'; $params[] = $category_id; $types .= 'i'; }
if ($supplier_id > 0) { $where[] = 'p.user_id = ?';     $params[] = $supplier_id; $types .= 'i'; }
if ($min_price   > 0) { $where[] = 'p.preco >= ?';       $params[] = $min_price;   $types .= 'd'; }
if ($max_price   > 0) { $where[] = 'p.preco <= ?';       $params[] = $max_price;   $types .= 'd'; }
if ($eco_filter !== '') {
    $where[]  = "JSON_CONTAINS(p.eco_badges, JSON_QUOTE(?))";
    $params[] = $eco_filter; $types .= 's';
}
$where_sql = implode(' AND ', $where);

/* ===== SIDEBAR CACHE (5 min) ===== */
if (isset($_SESSION['_sb_cache']) && (time() - ($_SESSION['_sb_cache']['ts'] ?? 0)) < 300) {
    $categories = $_SESSION['_sb_cache']['cats'];
    $suppliers  = $_SESSION['_sb_cache']['sups'];
} else {
    $cr = $mysqli->query("SELECT c.id,c.name,c.icon,COUNT(p.id) AS cnt FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.status='ativo' AND p.deleted_at IS NULL AND p.stock>0 WHERE c.parent_id IS NULL AND c.status='ativa' GROUP BY c.id,c.name,c.icon ORDER BY cnt DESC,c.name ASC LIMIT 20");
    $categories = $cr ? $cr->fetch_all(MYSQLI_ASSOC) : [];
    if ($cr) $cr->free();

    $sr = $mysqli->query("SELECT u.id,u.nome,COUNT(p.id) AS cnt FROM users u INNER JOIN products p ON p.user_id=u.id AND p.status='ativo' AND p.deleted_at IS NULL AND p.stock>0 WHERE u.type='company' AND u.status='active' GROUP BY u.id,u.nome ORDER BY cnt DESC LIMIT 15");
    $suppliers = $sr ? $sr->fetch_all(MYSQLI_ASSOC) : [];
    if ($sr) $sr->free();

    $_SESSION['_sb_cache'] = ['ts' => time(), 'cats' => $categories, 'sups' => $suppliers];
}

/* ===== TOTAL ROWS ===== */
$total_rows = 0;
if ($params) {
    $st = $mysqli->prepare("SELECT COUNT(*) AS n FROM products p WHERE $where_sql");
    $st->bind_param($types, ...$params);
    $st->execute();
    $total_rows = (int)$st->get_result()->fetch_assoc()['n'];
    $st->close();
} else {
    $r = $mysqli->query("SELECT COUNT(*) AS n FROM products p WHERE $where_sql");
    if ($r) { $total_rows = (int)$r->fetch_assoc()['n']; $r->free(); }
}

$total_pages = max(1, ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

/* ===== QUERY PRODUTOS ===== */
$score_col   = $search !== '' ? "({$search_score}) AS search_score" : "0 AS search_score";
$order_sql_f = ($search !== '' && $sort === 'recent') ? "search_score DESC, p.total_sales DESC, p.created_at DESC" : $order_sql;

$products_sql = "
    SELECT p.id,p.nome,p.descricao,p.preco,p.currency,p.imagem,p.image_path1,
           p.stock,p.created_at,p.eco_badges,p.total_sales,
           COALESCE(c.name,'') AS category_name,
           COALESCE(c.icon,'box') AS category_icon,
           COALESCE(u.nome,'') AS company_name,
           COALESCE((SELECT ROUND(AVG(r.rating),1) FROM customer_reviews r WHERE r.product_id=p.id),0) AS avg_rating,
           COALESCE((SELECT COUNT(*) FROM customer_reviews r WHERE r.product_id=p.id),0) AS review_count,
           {$score_col}
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    LEFT JOIN users      u ON u.id=p.user_id
    WHERE $where_sql
    ORDER BY $order_sql_f
    LIMIT ? OFFSET ?
";

$score_params = [];
$score_types  = '';
if ($search !== '' && $use_fulltext)  { $score_params = [$ft_term];       $score_types = 's'; }
elseif ($search !== '')               { $score_params = ["%{$search}%"]; $score_types = 's'; }

$products = [];
$st = $mysqli->prepare($products_sql);
if ($st) {
    $all_params = array_merge($score_params, $params, [$per_page, $offset]);
    $all_types  = $score_types . $types . 'ii';
    $st->bind_param($all_types, ...$all_params);
    $st->execute();
    $products = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

$new_cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));

/* ===== CART COUNT ===== */
$cart_count = 0;
if ($user_logged_in) {
    if (isset($_SESSION['cart_count'])) {
        $cart_count = (int)$_SESSION['cart_count'];
    } else {
        $st = $mysqli->prepare("SELECT COALESCE(SUM(ci.quantity),0) AS n FROM shopping_carts sc INNER JOIN cart_items ci ON ci.cart_id=sc.id WHERE sc.user_id=? AND sc.status='active'");
        if ($st) {
            $st->bind_param('i', $user_id);
            $st->execute();
            $cart_count = (int)$st->get_result()->fetch_assoc()['n'];
            $st->close();
            $_SESSION['cart_count'] = $cart_count;
        }
    }
}

/* ===== FILTROS ACTIVOS ===== */
$active_filters = [];
if ($search !== '') $active_filters[] = ['label' => "\"$search\"", 'remove' => 'search'];
if ($category_id > 0) {
    $cn = '';
    foreach ($categories as $c) { if ($c['id'] == $category_id) { $cn = $c['name']; break; } }
    $active_filters[] = ['label' => $cn, 'remove' => 'category'];
}
if ($supplier_id > 0) {
    $sn = '';
    foreach ($suppliers as $s) { if ($s['id'] == $supplier_id) { $sn = $s['nome']; break; } }
    $active_filters[] = ['label' => $sn, 'remove' => 'supplier'];
}
if ($min_price > 0 || $max_price > 0) {
    $lbl  = 'MT ' . ($min_price > 0 ? number_format($min_price,0,'.',',') : '0');
    $lbl .= ' – ' . ($max_price > 0 ? 'MT ' . number_format($max_price,0,'.',',') : '∞');
    $active_filters[] = ['label' => $lbl, 'remove' => 'price'];
}
if ($eco_filter !== '') $active_filters[] = ['label' => ($eco_opts[$eco_filter] ?? $eco_filter), 'remove' => 'eco'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="description" content="VSG Marketplace — <?= $total_rows ?> produtos sustentáveis disponíveis">
<title>VSG Marketplace – Shopping</title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
<link rel="stylesheet" href="assets/style/footer.css">
<link rel="stylesheet" href="assets/style/shopping-responsive.css">

<style>
/* ════════════════════════════════════════════
   TOKENS
════════════════════════════════════════════ */
:root {
  /* Brand */
  --g0: #f0fdf7;
  --g1: #d1fae5;
  --g2: #6ee7b7;
  --g3: #10b981;
  --g4: #059669;
  --g5: #047857;

  /* Ink */
  --ink:   #0f172a;
  --ink2:  #334155;
  --ink3:  #64748b;
  --ink4:  #94a3b8;
  --ink5:  #cbd5e1;

  /* Surface */
  --bg:    #f8fafc;
  --sur:   #ffffff;
  --bdr:   #e2e8f0;
  --bdr2:  #f1f5f9;

  /* Accent */
  --amber: #f59e0b;
  --red:   #ef4444;
  --blue:  #3b82f6;

  /* Shadows */
  --sh1: 0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
  --sh2: 0 4px 12px rgba(15,23,42,.08), 0 2px 4px rgba(15,23,42,.04);
  --sh3: 0 8px 24px rgba(15,23,42,.10), 0 4px 8px rgba(15,23,42,.04);
  --sh4: 0 20px 48px rgba(15,23,42,.14);

  /* Radius */
  --r4:  4px; --r6: 6px; --r8: 8px; --r10: 10px;
  --r12: 12px; --r16: 16px; --r20: 20px; --r99: 999px;

  /* Type */
  --font: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  --mono: 'DM Mono', monospace;

  /* Layout */
  --hdr-h:    68px;
  --strip-h:  36px;
  --cat-h:    56px;
  --filter-h: 54px;

  --ease: cubic-bezier(.22,1,.36,1);
}

/* ════════════════════════════════════════════
   BASE
════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{font-family:var(--font);font-size:15px;line-height:1.6;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
img{display:block;max-width:100%}
button{font-family:var(--font);cursor:pointer;border:none;background:none}
input,select{font-family:var(--font)}
.wrap{max-width:1400px;margin:0 auto;padding:0 28px}

/* ════════════════════════════════════════════
   TOP STRIP
════════════════════════════════════════════ */
.strip{
  background:var(--ink);
  color:rgba(255,255,255,.65);
  font-size:12.5px;
  height:var(--strip-h);
  border-bottom:1px solid rgba(255,255,255,.06);
}
.strip-in{
  display:flex;align-items:center;justify-content:space-between;
  height:100%;
}
.strip-l{display:flex;align-items:center;gap:20px}
.strip-r{display:flex;align-items:center;gap:4px}
.strip-lk{
  display:inline-flex;align-items:center;gap:6px;
  padding:4px 10px;border-radius:var(--r4);
  color:rgba(255,255,255,.65);
  transition:color .15s,background .15s;
}
.strip-lk:hover{color:var(--g3);background:rgba(255,255,255,.06)}
.strip-div{width:1px;height:12px;background:rgba(255,255,255,.12);margin:0 4px}
.strip-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 10px;background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.25);
  border-radius:var(--r99);color:var(--g2);font-size:11px;font-weight:600
}

/* ════════════════════════════════════════════
   HEADER
════════════════════════════════════════════ */
.hdr{
  background:var(--sur);
  border-bottom:1px solid var(--bdr);
  height:var(--hdr-h);
  position:sticky;
  top:0;z-index:500;
  box-shadow:var(--sh1);
}
.hdr-in{
  display:flex;align-items:center;gap:20px;
  height:100%;
}

/* Logo */
.logo{
  display:flex;align-items:center;gap:10px;flex-shrink:0;
  font-size:19px;font-weight:700;letter-spacing:-.4px;color:var(--ink);
  transition:opacity .15s;
}
.logo:hover{opacity:.8}
.logo-mark{
  width:40px;height:40px;
  background:linear-gradient(135deg,var(--g4),var(--g3));
  border-radius:var(--r10);
  display:grid;place-items:center;
  color:#fff;font-size:17px;
  box-shadow:0 2px 10px rgba(5,150,105,.3);
  flex-shrink:0;
}
.logo-name{font-weight:800;color:var(--ink)}
.logo-name em{color:var(--g4);font-style:normal}
.logo-tag{
  font-size:10px;font-weight:500;
  color:var(--ink4);letter-spacing:.5px;
  display:block;line-height:1;text-transform:uppercase;
}

/* Search */
.search-wrap{flex:1;max-width:620px;position:relative}
.search-form{display:flex;height:44px}
.search-input{
  flex:1;height:100%;
  padding:0 42px 0 18px;
  border:1.5px solid var(--bdr);border-right:none;
  border-radius:var(--r10) 0 0 var(--r10);
  font-size:14.5px;color:var(--ink);
  background:var(--bg);outline:none;
  transition:border-color .15s,box-shadow .15s,background .15s;
}
.search-input::placeholder{color:var(--ink4)}
.search-input:focus{
  border-color:var(--g3);background:var(--sur);
  box-shadow:0 0 0 3px rgba(16,185,129,.12);
}
.search-x{
  position:absolute;right:52px;top:50%;transform:translateY(-50%);
  color:var(--ink4);font-size:14px;padding:5px 7px;
  display:none;transition:color .15s;
}
.search-x:hover{color:var(--ink)}
.search-go{
  width:52px;height:100%;flex-shrink:0;
  background:var(--g4);border:1.5px solid var(--g4);
  border-radius:0 var(--r10) var(--r10) 0;
  color:#fff;font-size:17px;
  transition:background .15s;
}
.search-go:hover{background:var(--g5);border-color:var(--g5)}

/* Search dropdown */
.s-drop{
  position:absolute;top:calc(100% + 6px);left:0;right:0;
  background:var(--sur);border:1px solid var(--bdr);
  border-radius:var(--r12);box-shadow:var(--sh4);
  max-height:68vh;overflow-y:auto;display:none;z-index:800;
  animation:dropIn .15s var(--ease);
}
@keyframes dropIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.s-drop-hd{
  padding:10px 14px 8px;border-bottom:1px solid var(--bdr2);
  font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--ink4);
  position:sticky;top:0;background:var(--sur);z-index:2
}
.s-drop-list{list-style:none}
.s-drop-item a{
  display:flex;align-items:center;gap:10px;
  padding:9px 14px;border-bottom:1px solid var(--bdr2);
  transition:background .1s;
}
.s-drop-item:last-child a{border-bottom:none}
.s-drop-item a:hover{background:var(--bg)}
.s-drop-img{
  width:44px;height:44px;min-width:44px;
  object-fit:cover;border-radius:var(--r6);
  border:1px solid var(--bdr2);background:var(--bg);
}
.s-drop-info{flex:1;min-width:0}
.s-drop-name{font-size:13px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:2px}
.s-drop-cat{font-size:11px;color:var(--ink4);display:flex;align-items:center;gap:4px}
.s-drop-price{font-size:14px;font-weight:700;color:var(--g4);white-space:nowrap}
.s-drop-all{
  display:block;padding:10px 14px;text-align:center;
  font-size:12.5px;font-weight:600;color:var(--blue);
  border-top:1px solid var(--bdr);
  position:sticky;bottom:0;background:var(--sur);
  transition:background .1s;
}
.s-drop-all:hover{background:var(--bg)}
.s-drop-empty,.s-drop-loading{padding:28px 14px;text-align:center;color:var(--ink4)}
.s-drop-empty i{font-size:30px;color:var(--bdr);display:block;margin-bottom:8px}
.s-spin{
  width:22px;height:22px;margin:0 auto 8px;
  border:3px solid var(--bdr);border-top-color:var(--g3);
  border-radius:50%;animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.s-hi{background:rgba(255,220,0,.4);font-weight:700;border-radius:2px;padding:0 2px}

/* Header right */
.hdr-r{margin-left:auto;display:flex;align-items:center;gap:8px;flex-shrink:0}
.cart-btn{
  position:relative;display:flex;align-items:center;justify-content:center;
  width:44px;height:44px;border-radius:var(--r10);
  color:var(--ink);font-size:19px;
  transition:background .15s,color .15s;
}
.cart-btn:hover{background:var(--bg);color:var(--g4)}
.cart-badge{
  position:absolute;top:2px;right:2px;
  background:var(--red);color:#fff;
  font-size:9.5px;font-weight:700;min-width:17px;height:17px;
  border-radius:var(--r99);display:grid;place-items:center;padding:0 4px;
  border:2px solid var(--sur);
}
.hdr-btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 18px;border-radius:var(--r10);
  border:1.5px solid var(--bdr);
  font-size:14px;font-weight:600;color:var(--ink);
  background:var(--sur);white-space:nowrap;
  transition:border-color .15s,background .15s,color .15s;
}
.hdr-btn:hover{border-color:var(--g3);color:var(--g4);background:var(--g0)}
.hdr-btn.pri{background:var(--g4);border-color:var(--g4);color:#fff}
.hdr-btn.pri:hover{background:var(--g5);border-color:var(--g5)}
.hdr-btn img{width:26px;height:26px;border-radius:50%;object-fit:cover}

/* ════════════════════════════════════════════
   CATEGORY BAR  (estilo Alibaba/Amazon)
════════════════════════════════════════════ */
.cat-bar{
  background:var(--sur);
  border-bottom:1px solid var(--bdr);
  position:sticky;
  top:var(--hdr-h);
  z-index:490;
}
.cat-bar-in{
  display:flex;align-items:center;
  height:var(--cat-h);
  gap:0;
  overflow-x:auto;
  scroll-behavior:smooth;
  scrollbar-width:none;
}
.cat-bar-in::-webkit-scrollbar{display:none}

.cat-all{
  display:flex;align-items:center;gap:8px;
  padding:0 20px;height:100%;flex-shrink:0;
  font-size:14px;font-weight:700;color:#fff;
  background:var(--g4);
  white-space:nowrap;
  border-right:1px solid rgba(255,255,255,.15);
  transition:background .15s;
}
.cat-all:hover{background:var(--g5)}
.cat-all i{font-size:15px}

.cat-item{
  display:flex;align-items:center;gap:7px;
  padding:0 18px;height:100%;flex-shrink:0;
  font-size:13.5px;font-weight:500;color:var(--ink2);
  border-right:1px solid var(--bdr2);
  white-space:nowrap;
  position:relative;
  transition:color .15s,background .15s;
}
.cat-item:hover{color:var(--g4);background:var(--g0)}
.cat-item.on{color:var(--g4);font-weight:700;background:var(--g0)}
.cat-item.on::after{
  content:'';position:absolute;bottom:0;left:14px;right:14px;
  height:3px;background:var(--g4);border-radius:var(--r99) var(--r99) 0 0;
}
.cat-item i{font-size:13.5px;opacity:.7}
.cat-item .cnt{
  font-size:10.5px;font-weight:700;
  background:var(--bdr2);color:var(--ink4);
  padding:2px 6px;border-radius:var(--r99);
  margin-left:2px;
}
.cat-item.on .cnt{background:var(--g1);color:var(--g5)}

/* Scroll arrows */
.cat-arrow{
  position:absolute;top:0;bottom:0;
  width:32px;display:flex;align-items:center;justify-content:center;
  font-size:12px;color:var(--ink3);cursor:pointer;
  background:linear-gradient(to right,transparent,var(--sur) 60%);
  border:none;z-index:2;
  transition:color .15s;
}
.cat-arrow:hover{color:var(--g4)}
.cat-arrow.left{left:0;background:linear-gradient(to left,transparent,var(--sur) 60%)}
.cat-arrow.right{right:0}
.cat-bar{position:sticky;top:var(--hdr-h);z-index:490;overflow:hidden}

/* ════════════════════════════════════════════
   FILTER BAR  (inline, entre cats e produtos)
════════════════════════════════════════════ */
.filter-bar{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  padding:14px 0;
}
.filter-bar-l{display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1;min-width:0}
.filter-bar-r{display:flex;align-items:center;gap:8px;flex-shrink:0}

/* Pills activos */
.f-pill{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 12px;
  background:var(--sur);border:1.5px solid var(--g3);
  border-radius:var(--r99);
  font-size:13px;font-weight:600;color:var(--g5);
}
.f-pill a{color:var(--ink4);font-size:13px;margin-left:4px;transition:color .1s}
.f-pill a:hover{color:var(--red)}

/* Filter dropdowns */
.f-drop-wrap{position:relative}
.f-drop-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 14px;
  border:1.5px solid var(--bdr);border-radius:var(--r99);
  font-size:13.5px;font-weight:500;color:var(--ink2);
  background:var(--sur);white-space:nowrap;
  transition:border-color .15s,color .15s,background .15s;
}
.f-drop-btn:hover,.f-drop-btn.open{border-color:var(--g3);color:var(--g4);background:var(--g0)}
.f-drop-btn i.ch{font-size:10px;transition:transform .15s}
.f-drop-btn.open i.ch{transform:rotate(180deg)}
.f-drop{
  position:absolute;top:calc(100% + 8px);left:0;min-width:230px;
  background:var(--sur);border:1px solid var(--bdr);
  border-radius:var(--r12);box-shadow:var(--sh3);
  padding:8px;z-index:600;
  display:none;animation:dropIn .15s var(--ease);
}
.f-drop.open{display:block}
.f-opt{
  display:flex;align-items:center;justify-content:space-between;
  padding:9px 12px;border-radius:var(--r8);
  font-size:13.5px;color:var(--ink);cursor:pointer;
  transition:background .1s;
}
.f-opt:hover{background:var(--bg)}
.f-opt.on{background:var(--g0);color:var(--g5);font-weight:700}
.f-opt .f-cnt{font-size:11.5px;color:var(--ink4);background:var(--bg);padding:2px 7px;border-radius:var(--r99)}
.f-opt.on .f-cnt{background:var(--g1);color:var(--g5)}
.f-sep{height:1px;background:var(--bdr2);margin:5px 0}

/* Price form inline */
.price-form-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:6px 6px 8px}
.price-form-row label{font-size:11px;font-weight:600;color:var(--ink4);margin-bottom:3px;display:block}
.price-form-row input{
  width:100%;padding:8px 12px;
  border:1.5px solid var(--bdr);border-radius:var(--r8);
  font-size:13.5px;color:var(--ink);background:var(--bg);
  outline:none;transition:border-color .15s;
}
.price-form-row input:focus{border-color:var(--g3);background:var(--sur)}
.btn-apply{
  width:100%;margin-top:4px;padding:9px;
  background:var(--g4);border-radius:var(--r8);
  color:#fff;font-size:13.5px;font-weight:700;
  transition:background .15s;
}
.btn-apply:hover{background:var(--g5)}

/* Sort select */
.sort-sel{
  padding:7px 32px 7px 14px;
  border:1.5px solid var(--bdr);border-radius:var(--r99);
  font-size:13.5px;font-weight:600;color:var(--ink);
  background:var(--sur) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 11px center;
  appearance:none;outline:none;cursor:pointer;
  transition:border-color .15s;
}
.sort-sel:focus,.sort-sel:hover{border-color:var(--g3)}

/* View toggle */
.view-tog{display:flex;gap:4px}
.v-btn{
  width:36px;height:36px;border-radius:var(--r8);
  border:1.5px solid var(--bdr);display:grid;place-items:center;
  font-size:14px;color:var(--ink4);
  transition:all .15s;
}
.v-btn.on,.v-btn:hover{background:var(--g4);border-color:var(--g4);color:#fff}

/* Total label */
.total-lbl{font-size:13.5px;color:var(--ink3);flex-shrink:0}
.total-lbl strong{color:var(--ink);font-weight:700}

/* ════════════════════════════════════════════
   PAGE LAYOUT
════════════════════════════════════════════ */
.page-body{
  max-width:1400px;margin:0 auto;
  padding:0 28px 100px;
}

/* ════════════════════════════════════════════
   PRODUCTS GRID
════════════════════════════════════════════ */
.p-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
  gap:18px;
  margin-top:6px;
}
.p-grid.lv{grid-template-columns:1fr}
.p-grid.lv .pcard{flex-direction:row;max-height:172px}
.p-grid.lv .pcard-img{padding-top:0;width:172px;min-width:172px;height:172px;flex-shrink:0}
.p-grid.lv .pcard-img img{position:static;width:100%;height:100%}
.p-grid.lv .pcard-body{padding:16px 20px}
.p-grid.lv .pcard-name{-webkit-line-clamp:1;min-height:auto;font-size:16px}
.p-grid.lv .pcard-desc{display:none}

/* ════════════════════════════════════════════
   PRODUCT CARD
════════════════════════════════════════════ */
.pcard{
  background:var(--sur);border:1px solid var(--bdr);
  border-radius:var(--r12);overflow:hidden;
  display:flex;flex-direction:column;
  text-decoration:none;color:inherit;
  transition:box-shadow .2s var(--ease),transform .2s var(--ease),border-color .2s;
  cursor:pointer;
}
.pcard:hover{
  box-shadow:var(--sh3);
  transform:translateY(-3px);
  border-color:rgba(16,185,129,.3);
}

/* Image */
.pcard-img{
  position:relative;width:100%;padding-top:100%;
  background:var(--bdr2);overflow:hidden;
}
.pcard-img img{
  position:absolute;inset:0;width:100%;height:100%;
  object-fit:cover;
  transition:transform .3s var(--ease);
}
.pcard:hover .pcard-img img{transform:scale(1.05)}

/* Badges */
.pcard-badges{position:absolute;top:10px;left:10px;display:flex;flex-direction:column;gap:4px;z-index:2}
.pb{
  display:inline-block;padding:3px 8px;border-radius:var(--r4);
  font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;
}
.pb-new{background:#3b82f6;color:#fff}
.pb-hot{background:var(--amber);color:#fff}
.pb-eco{background:var(--g1);color:var(--g5);border:1px solid var(--g2)}

/* Quick actions */
.pcard-qa{
  position:absolute;top:10px;right:10px;z-index:2;
  display:flex;flex-direction:column;gap:5px;
  opacity:0;transform:translateX(6px);
  transition:opacity .2s,transform .2s var(--ease);
}
.pcard:hover .pcard-qa{opacity:1;transform:translateX(0)}
.qa-btn{
  width:32px;height:32px;
  background:var(--sur);border:1px solid var(--bdr);
  border-radius:50%;display:grid;place-items:center;
  font-size:13px;color:var(--ink3);
  box-shadow:var(--sh1);
  transition:background .1s,color .1s,border-color .1s;
}
.qa-btn:hover{background:var(--g4);color:#fff;border-color:var(--g4)}

/* Body */
.pcard-body{padding:14px 16px;flex:1;display:flex;flex-direction:column}
.pcard-cat{
  display:inline-flex;align-items:center;gap:5px;
  font-size:11px;font-weight:700;color:var(--g4);
  text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;
}
.pcard-cat i{font-size:10px}
.pcard-name{
  font-size:14px;font-weight:600;color:var(--ink);line-height:1.4;
  margin-bottom:6px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
  overflow:hidden;min-height:40px;
}
.pcard-name mark{background:rgba(255,220,0,.4);border-radius:2px;font-weight:700;padding:0 1px}
.pcard-sup{
  font-size:12px;color:var(--ink4);
  display:flex;align-items:center;gap:4px;margin-bottom:9px;
}
.pcard-stars{display:flex;align-items:center;gap:5px;margin-bottom:11px}
.stars{display:flex;gap:1px}
.stars i{font-size:11.5px;color:var(--amber)}
.stars .fa-regular{color:var(--bdr)}
.rv{font-size:11.5px;color:var(--ink4)}
.pcard-foot{
  display:flex;align-items:flex-end;justify-content:space-between;
  padding-top:11px;border-top:1px solid var(--bdr2);margin-top:auto;gap:8px;
}
.pcard-price{display:flex;flex-direction:column}
.pcard-price .sym{font-size:10.5px;color:var(--ink4);font-weight:600}
.pcard-price .val{
  font-size:1.2rem;font-weight:800;color:var(--g4);
  letter-spacing:-.3px;line-height:1.1;
}
.pcard-stock{
  font-size:10.5px;font-weight:700;
  padding:4px 8px;border-radius:var(--r4);border:1px solid;white-space:nowrap;
}
.pcard-stock.ok{background:#f0fdf4;color:#166534;border-color:#bbf7d0}
.pcard-stock.low{background:#fef2f2;color:#991b1b;border-color:#fecaca}

/* Hover actions */
.pcard-actions{
  display:flex;gap:7px;margin-top:10px;
  opacity:0;transform:translateY(4px);
  transition:opacity .2s,transform .2s;
}
.pcard:hover .pcard-actions{opacity:1;transform:translateY(0)}
.p-cart{
  flex:1;padding:9px 8px;
  background:var(--g4);border-radius:var(--r8);
  color:#fff;font-size:13px;font-weight:700;
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:background .15s;font-family:var(--font);border:none;cursor:pointer;
}
.p-cart:hover{background:var(--g5)}
.p-cart.added{background:#166534}
.p-buy{
  flex-shrink:0;padding:9px 14px;
  background:var(--ink);border-radius:var(--r8);
  color:#fff;font-size:13px;font-weight:700;
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:background .15s;white-space:nowrap;text-decoration:none;
}
.p-buy:hover{background:var(--ink2)}

/* Description */
.pcard-desc{
  font-size:12px;color:var(--ink3);line-height:1.45;
  margin-bottom:8px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
  overflow:hidden;
}

/* No-comment badge */
.rv-nac{
  font-size:11px;font-weight:600;
  color:var(--ink4);
  background:var(--bdr2);
  border:1px solid var(--bdr);
  border-radius:var(--r4);
  padding:2px 7px;
  letter-spacing:.2px;
}

/* ════════════════════════════════════════════
   EMPTY STATES
════════════════════════════════════════════ */
.empty-wrap{
  grid-column:1/-1;text-align:center;
  padding:80px 28px;
  background:var(--sur);border:1px solid var(--bdr);
  border-radius:var(--r16);
}
.empty-ico{
  width:80px;height:80px;
  background:var(--bdr2);border:2px dashed var(--bdr);
  border-radius:50%;display:grid;place-items:center;
  margin:0 auto 22px;font-size:32px;color:var(--ink4);
}
.empty-wrap h3{font-size:1.2rem;font-weight:700;margin-bottom:8px}
.empty-wrap p{font-size:14px;color:var(--ink3);margin-bottom:22px;max-width:400px;margin-left:auto;margin-right:auto}
.empty-tips{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-bottom:22px}
.e-tip{
  padding:5px 14px;background:var(--bg);border:1px solid var(--bdr);
  border-radius:var(--r99);font-size:12.5px;color:var(--ink2);
  display:flex;align-items:center;gap:5px;
}
.e-tip i{color:var(--g4);font-size:11px}
.empty-actions{display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap}
.btn-pr{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 24px;background:var(--g4);border-radius:var(--r10);
  color:#fff;font-weight:700;font-size:14px;transition:background .15s;
}
.btn-pr:hover{background:var(--g5)}
.btn-se{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 24px;border:1.5px solid var(--bdr);border-radius:var(--r10);
  color:var(--ink2);font-weight:600;font-size:14px;
  transition:border-color .15s,color .15s;
}
.btn-se:hover{border-color:var(--g4);color:var(--g4)}

/* ════════════════════════════════════════════
   PAGINATION
════════════════════════════════════════════ */
.pager{
  display:flex;align-items:center;justify-content:center;
  gap:5px;padding:28px 0 10px;flex-wrap:wrap;
}
.pg{
  min-width:40px;height:40px;padding:0 10px;
  background:var(--sur);border:1.5px solid var(--bdr);
  border-radius:var(--r8);font-size:14px;font-weight:600;color:var(--ink);
  display:flex;align-items:center;justify-content:center;
  transition:all .15s;
}
.pg:hover:not(:disabled){border-color:var(--g3);color:var(--g4)}
.pg.on{background:var(--g4);border-color:var(--g4);color:#fff}
.pg:disabled{opacity:.35;pointer-events:none}
.pg-dots{color:var(--ink4);line-height:40px;padding:0 3px;font-weight:700}

/* ════════════════════════════════════════════
   FLASH TOAST
════════════════════════════════════════════ */
.flash{
  position:fixed;top:74px;right:18px;z-index:9999;
  display:flex;align-items:center;gap:12px;
  padding:14px 42px 14px 16px;border-radius:var(--r12);
  box-shadow:var(--sh4);border:1px solid;
  font-size:14px;max-width:360px;
  animation:slideIn .3s var(--ease);transition:opacity .3s;
}
.flash-success{background:#f0fdf4;color:#166534;border-color:#bbf7d0}
.flash-error{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.flash-info{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
.flash-x{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:18px;opacity:.5;color:currentColor;transition:opacity .1s}
.flash-x:hover{opacity:1}
@keyframes slideIn{from{transform:translateX(380px);opacity:0}to{transform:none;opacity:1}}

/* ════════════════════════════════════════════
   BACK TO TOP
════════════════════════════════════════════ */
.btt{
  position:fixed;bottom:24px;right:20px;z-index:300;
  width:38px;height:38px;background:var(--g4);border-radius:50%;
  color:#fff;font-size:15px;box-shadow:var(--sh2);
  display:none;place-items:center;
  transition:transform .2s,background .15s;
}
.btt.vis{display:grid}
.btt:hover{background:var(--g5);transform:translateY(-2px)}

/* ════════════════════════════════════════════
   RESPONSIVIDADE
   Ver: assets/style/shopping-responsive.css
════════════════════════════════════════════ */
</style>
</head>
<body>

<!-- Flash -->
<?php if ($flash_message): ?>
<div class="flash flash-<?= esc($flash_type) ?>" id="flashMsg">
  <i class="fa-solid fa-<?= $flash_type==='success'?'check-circle':($flash_type==='error'?'exclamation-circle':'info-circle') ?>"></i>
  <span><?= esc($flash_message) ?></span>
  <button class="flash-x" onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>

<!-- ══ TOP STRIP ══ -->
<div class="strip">
  <div class="wrap">
    <div class="strip-in">
      <div class="strip-l">
        <span class="strip-badge"><i class="fa-solid fa-leaf"></i> Marketplace Sustentável</span>
        <a href="#" class="strip-lk"><i class="fa-solid fa-coins"></i> <?= esc($user_currency_info['currency']) ?></a>
        <a href="#" class="strip-lk"><i class="fa-solid fa-shield-halved"></i> Compra Segura</a>
      </div>
      <div class="strip-r">
        <?php if ($user_logged_in && $user_type === 'company'): ?>
          <a href="pages/person/index.php" class="strip-lk">Meu Painel</a>
          <span class="strip-div"></span>
        <?php endif; ?>
        <a href="#" class="strip-lk">Ajuda</a>
        <span class="strip-div"></span>
        <a href="#" class="strip-lk">Rastrear Pedido</a>
      </div>
    </div>
  </div>
</div>

<!-- ══ HEADER ══ -->
<header class="hdr">
  <div class="wrap">
    <div class="hdr-in" id="hdrIn">
      <a href="index.php" class="logo" id="hdrLogo">
        <div class="logo-mark"><i class="fa-solid fa-leaf"></i></div>
        <div class="logo-text">
          <div class="logo-name">VSG<em>•</em>MARKET</div>
          <span class="logo-tag">MARKETPLACE</span>
        </div>
      </a>

      <div class="search-wrap" id="searchWrap">
        <form class="search-form" onsubmit="return false;">
          <input type="text" id="searchInput" class="search-input"
                 placeholder="Buscar produtos, marcas, categorias..."
                 value="<?= esc($search) ?>" autocomplete="off">
          <button type="button" class="search-x" id="searchX"><i class="fa-solid fa-xmark"></i></button>
          <button type="button" class="search-go" id="searchGo"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="s-drop" id="sDrop"></div>
      </div>
      <button type="button" class="search-cancel" id="searchCancel">Cancelar</button>

      <div class="hdr-r">
        <a href="cart.php" class="cart-btn" title="Carrinho">
          <i class="fa-solid fa-cart-shopping"></i>
          <?php if ($cart_count > 0): ?>
            <span class="cart-badge"><?= $cart_count > 99 ? '99+' : $cart_count ?></span>
          <?php endif; ?>
        </a>
        <?php if ($user_logged_in): ?>
          <a href="pages/person/index.php" class="hdr-btn">
            <?php if ($user_avatar): ?><img src="<?= esc($user_avatar) ?>" alt="avatar"><?php else: ?><i class="fa-solid fa-circle-user"></i><?php endif; ?>
            <span class="btn-text"><?= esc($user_name) ?></span>
          </a>
        <?php else: ?>
          <a href="registration/login/login.php" class="hdr-btn">
            <i class="fa-solid fa-circle-user"></i><span class="btn-text">Entrar</span>
          </a>
          <a href="registration/register/painel_cadastro.php?tipo=business" class="hdr-btn pri">
            <i class="fa-solid fa-store"></i><span class="btn-text">Vender</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<!-- ══ CATEGORY BAR ══ -->
<nav class="cat-bar">
  <div class="wrap" style="position:relative">
    <div class="cat-bar-in" id="catBar">
      <a href="shopping.php" class="cat-all">
        <i class="fa-solid fa-border-all"></i> Todos
      </a>
      <?php foreach ($categories as $cat): ?>
      <a href="<?= filterUrl('category', $cat['id']) ?>"
         class="cat-item <?= $category_id == $cat['id'] ? 'on' : '' ?>">
        <i class="fa-solid fa-<?= esc($cat['icon'] ?: 'box') ?>"></i>
        <?= esc($cat['name']) ?>
        <span class="cnt"><?= number_format($cat['cnt']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <button class="cat-arrow left" id="catLeft" onclick="scrollCat(-160)"><i class="fa-solid fa-chevron-left"></i></button>
    <button class="cat-arrow right" id="catRight" onclick="scrollCat(160)"><i class="fa-solid fa-chevron-right"></i></button>
  </div>
</nav>

<!-- ══ PAGE BODY ══ -->
<div class="page-body">

  <!-- Filter bar -->
  <div class="filter-bar">
    <div class="filter-bar-l">

      <!-- Active pills -->
      <?php foreach ($active_filters as $af): ?>
      <div class="f-pill">
        <i class="fa-solid fa-tag" style="font-size:9px;opacity:.6"></i>
        <?= esc($af['label']) ?>
        <a href="<?= removeFilter($af['remove']) ?>" title="Remover">×</a>
      </div>
      <?php endforeach; ?>
      <?php if (!empty($active_filters)): ?>
      <a href="shopping.php" style="font-size:12px;color:var(--ink4);text-decoration:none;transition:color .1s"
         onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--ink4)'">
        <i class="fa-solid fa-xmark"></i> Limpar
      </a>
      <?php endif; ?>

      <!-- Price filter dropdown -->
      <div class="f-drop-wrap">
        <button class="f-drop-btn <?= ($min_price > 0 || $max_price > 0) ? 'open' : '' ?>"
                onclick="toggleDrop('priceD',this)">
          <i class="fa-solid fa-tag" style="font-size:10px"></i> Preço
          <i class="fa-solid fa-chevron-down ch"></i>
        </button>
        <div class="f-drop" id="priceD">
          <form action="shopping.php" method="get" onsubmit="">
            <?php foreach ($_GET as $k => $v): if (!in_array($k,['min','max','page'])): ?>
              <input type="hidden" name="<?= esc($k) ?>" value="<?= esc($v) ?>">
            <?php endif; endforeach; ?>
            <div class="price-form-row">
              <div>
                <label>Mínimo (MT)</label>
                <input type="number" name="min" placeholder="0" min="0" value="<?= $min_price > 0 ? $min_price : '' ?>">
              </div>
              <div>
                <label>Máximo (MT)</label>
                <input type="number" name="max" placeholder="∞" min="0" value="<?= $max_price > 0 ? $max_price : '' ?>">
              </div>
            </div>
            <button type="submit" class="btn-apply"><i class="fa-solid fa-filter"></i> Aplicar</button>
          </form>
        </div>
      </div>

      <!-- Eco filter dropdown -->
      <div class="f-drop-wrap">
        <button class="f-drop-btn <?= $eco_filter !== '' ? 'open' : '' ?>"
                onclick="toggleDrop('ecoD',this)">
          <i class="fa-solid fa-leaf" style="font-size:10px;color:var(--g4)"></i>
          <?= $eco_filter !== '' ? esc($eco_opts[$eco_filter] ?? $eco_filter) : 'Eco' ?>
          <i class="fa-solid fa-chevron-down ch"></i>
        </button>
        <div class="f-drop" id="ecoD">
          <?php foreach ($eco_opts as $val => $label): ?>
          <div class="f-opt <?= $eco_filter === $val ? 'on' : '' ?>"
               onclick="location='<?= filterUrl('eco', $val) ?>'">
            <?= esc($label) ?>
          </div>
          <?php endforeach; ?>
          <?php if ($eco_filter !== ''): ?>
          <div class="f-sep"></div>
          <div class="f-opt" onclick="location='<?= removeFilter('eco') ?>'">
            <i class="fa-solid fa-xmark" style="font-size:11px;color:var(--ink4)"></i> Limpar
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Fornecedor dropdown -->
      <?php if (!empty($suppliers)): ?>
      <div class="f-drop-wrap">
        <button class="f-drop-btn <?= $supplier_id > 0 ? 'open' : '' ?>"
                onclick="toggleDrop('supD',this)">
          <i class="fa-solid fa-building" style="font-size:10px"></i> Fornecedor
          <i class="fa-solid fa-chevron-down ch"></i>
        </button>
        <div class="f-drop" id="supD" style="max-height:250px;overflow-y:auto">
          <?php foreach ($suppliers as $sup): ?>
          <div class="f-opt <?= $supplier_id == $sup['id'] ? 'on' : '' ?>"
               onclick="location='<?= filterUrl('supplier', $sup['id']) ?>'">
            <?= esc($sup['nome']) ?>
            <span class="f-cnt"><?= number_format($sup['cnt']) ?></span>
          </div>
          <?php endforeach; ?>
          <?php if ($supplier_id > 0): ?>
          <div class="f-sep"></div>
          <div class="f-opt" onclick="location='<?= removeFilter('supplier') ?>'">
            <i class="fa-solid fa-xmark" style="font-size:11px;color:var(--ink4)"></i> Limpar
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <div class="filter-bar-r">
      <span class="total-lbl"><strong><?= number_format($total_rows) ?></strong> produto<?= $total_rows !== 1 ? 's' : '' ?></span>

      <select class="sort-sel" onchange="sortBy(this.value)">
        <?php if ($search !== ''): ?>
          <option value="recent" <?= $sort==='recent'?'selected':'' ?>>Relevância</option>
        <?php else: ?>
          <option value="recent" <?= $sort==='recent'?'selected':'' ?>>Recentes</option>
        <?php endif; ?>
        <option value="bestseller" <?= $sort==='bestseller'?'selected':'' ?>>Mais Vendidos</option>
        <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Menor Preço</option>
        <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Maior Preço</option>
        <option value="rating"     <?= $sort==='rating'    ?'selected':'' ?>>Avaliações</option>
      </select>

      <div class="view-tog">
        <button class="v-btn on" id="vGrid" onclick="setView('grid')" title="Grade"><i class="fa-solid fa-grip"></i></button>
        <button class="v-btn" id="vList" onclick="setView('list')" title="Lista"><i class="fa-solid fa-list"></i></button>
      </div>
    </div>
  </div>

  <!-- Search banner -->
  <?php if ($search !== ''): ?>
  <div style="
    display:flex;align-items:center;justify-content:space-between;gap:14px;
    padding:13px 20px;margin-bottom:14px;
    background:var(--g0);border:1px solid var(--g2);border-radius:var(--r10);
    font-size:14px;
  ">
    <span style="color:var(--ink2)">
      <i class="fa-solid fa-magnifying-glass" style="color:var(--g4);margin-right:7px"></i>
      <strong style="color:var(--ink)"><?= number_format($total_rows) ?></strong>
      resultado<?= $total_rows !== 1 ? 's' : '' ?> para
      <strong style="color:var(--g5)">"<?= esc($search) ?>"</strong>
      <?php if ($use_fulltext): ?>
        <span style="margin-left:10px;padding:3px 9px;background:var(--g1);color:var(--g5);border:1px solid var(--g2);border-radius:var(--r99);font-size:11px;font-weight:700">
          <i class="fa-solid fa-bolt"></i> Busca inteligente
        </span>
      <?php endif; ?>
    </span>
    <a href="<?= removeFilter('search') ?>"
       style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--ink3);transition:color .1s;white-space:nowrap"
       onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--ink3)'">
      <i class="fa-solid fa-xmark"></i> Limpar busca
    </a>
  </div>
  <?php endif; ?>

  <!-- Products -->
  <div class="p-grid" id="pgrid">
    <?php if (empty($products)): ?>
      <?php if ($search !== ''): ?>
      <div class="empty-wrap">
        <div class="empty-ico"><i class="fa-solid fa-magnifying-glass"></i></div>
        <h3>Sem resultados para "<?= esc($search) ?>"</h3>
        <p>Não encontrámos produtos para esta busca. Tente sugestões abaixo.</p>
        <div class="empty-tips">
          <div class="e-tip"><i class="fa-solid fa-lightbulb"></i> Verifique a ortografia</div>
          <div class="e-tip"><i class="fa-solid fa-minimize"></i> Use termos mais curtos</div>
          <div class="e-tip"><i class="fa-solid fa-filter"></i> Remova filtros activos</div>
        </div>
        <div class="empty-actions">
          <a href="shopping.php" class="btn-pr"><i class="fa-solid fa-border-all"></i> Ver todos os produtos</a>
          <a href="<?= removeFilter('search') ?>" class="btn-se"><i class="fa-solid fa-xmark"></i> Limpar busca</a>
        </div>
      </div>
      <?php else: ?>
      <div class="empty-wrap">
        <div class="empty-ico"><i class="fa-solid fa-box-open"></i></div>
        <h3>Nenhum produto encontrado</h3>
        <p>Tente ajustar os filtros ou buscar com outros termos.</p>
        <div class="empty-actions">
          <a href="shopping.php" class="btn-pr"><i class="fa-solid fa-rotate-left"></i> Ver todos</a>
        </div>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <?php foreach ($products as $p):
        $img      = getProductImageUrl($p);
        $avg      = (float)($p['avg_rating'] ?? 0);
        $rating   = (int)round($avg);
        $rev_cnt  = (int)($p['review_count'] ?? 0);
        $isNew    = ($p['created_at'] ?? '') >= $new_cutoff;
        $isHot    = ((int)($p['total_sales'] ?? 0)) > 0;
        $isLow    = $p['stock'] > 0 && $p['stock'] <= 10;
        $eco_raw  = $p['eco_badges'] ? json_decode($p['eco_badges'], true) : [];
        $eco1     = is_array($eco_raw) && !empty($eco_raw) ? ($eco_opts[$eco_raw[0]] ?? null) : null;
        $nom_hl   = $search !== '' ? highlightTerm($p['nome'], $search) : esc($p['nome']);
        $pid      = (int)$p['id'];

        // Description: strip tags, truncate at 90 chars
        $desc_raw = trim(strip_tags($p['descricao'] ?? ''));
        $desc_short = mb_strlen($desc_raw) > 90
            ? mb_substr($desc_raw, 0, 90) . '…'
            : $desc_raw;

        // Buy button: logged in → checkout directly; guest → login redirect
        $buy_href = $user_logged_in
            ? 'checkout.php?buy_now=' . $pid . '&qty=1'
            : 'registration/login/login.php?redirect=' . urlencode('checkout.php?buy_now=' . $pid . '&qty=1');

        $meta_js  = json_encode([
          'name'=>$p['nome'],'price'=>(float)$p['preco'],
          'img'=>$img,'stock'=>(int)$p['stock'],
          'category'=>$p['category_name']?:'Geral',
          'icon'=>$p['category_icon']?:'box',
          'company'=>$p['company_name']?:'',
        ]);
      ?>
      <div class="pcard" onclick="location.href='product.php?id=<?= $pid ?>'">
        <div class="pcard-img">
          <img src="<?= $img ?>" alt="<?= esc($p['nome']) ?>" loading="lazy"
               onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($p['nome']) ?>&size=400&background=00b96b&color=fff&font-size=0.1'">
          <div class="pcard-badges">
            <?php if ($isNew): ?><span class="pb pb-new">Novo</span>
            <?php elseif ($isHot): ?><span class="pb pb-hot">Popular</span><?php endif; ?>
            <?php if ($eco1): ?><span class="pb pb-eco"><?= esc($eco1) ?></span><?php endif; ?>
          </div>
          <div class="pcard-qa">
            <button class="qa-btn" onclick="event.stopPropagation()" title="Favoritar"><i class="fa-regular fa-heart"></i></button>
            <button class="qa-btn" onclick="event.stopPropagation()" title="Partilhar"><i class="fa-regular fa-share-from-square"></i></button>
          </div>
        </div>
        <div class="pcard-body">
          <div class="pcard-cat">
            <i class="fa-solid fa-<?= esc($p['category_icon'] ?: 'box') ?>"></i>
            <?= esc($p['category_name'] ?: 'Geral') ?>
          </div>
          <div class="pcard-name"><?= $nom_hl ?></div>
          <?php if ($desc_short !== ''): ?>
          <div class="pcard-desc"><?= esc($desc_short) ?></div>
          <?php endif; ?>
          <div class="pcard-sup">
            <i class="fa-solid fa-building"></i>
            <?= esc($p['company_name'] ?: 'Fornecedor') ?>
          </div>
          <div class="pcard-stars">
            <?php if ($rev_cnt > 0): ?>
              <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="fa-<?= $i <= $rating ? 'solid' : 'regular' ?> fa-star"></i>
                <?php endfor; ?>
              </div>
              <span class="rv"><?= number_format($avg, 1) ?> (<?= $rev_cnt ?>)</span>
            <?php else: ?>
              <span class="rv-nac">S/ avaliações</span>
            <?php endif; ?>
          </div>
          <div class="pcard-foot">
            <div class="pcard-price">
              <span class="sym"><?= esc($user_currency_info['symbol']) ?></span>
              <span class="val" data-price-mzn="<?= (float)$p['preco'] ?>">
                <?= number_format($p['preco'] * $user_currency_info['rate'], 2, ',', '.') ?>
              </span>
            </div>
            <span class="pcard-stock <?= $isLow ? 'low' : 'ok' ?>">
              <?= $isLow ? 'Últimas ' . (int)$p['stock'] : 'Em estoque' ?>
            </span>
          </div>
          <div class="pcard-actions">
            <button class="p-cart"
              onclick="event.stopPropagation();addToCart(<?= $pid ?>,this,<?= htmlspecialchars($meta_js,ENT_QUOTES) ?>)">
              <i class="fa-solid fa-cart-plus"></i><span>Carrinho</span>
            </button>
            <a href="<?= esc($buy_href) ?>"
               onclick="event.stopPropagation()" class="p-buy">
              <i class="fa-solid fa-bolt"></i> Comprar
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pager">
    <?php
    $pd = $page <= 1 ? 'disabled' : '';
    $pp = max(1, $page - 1);
    echo "<button class='pg' $pd onclick=\"location='{$pg_base}page={$pp}'\"><i class='fa-solid fa-chevron-left'></i></button>";
    $s = max(1, $page - 2); $e = min($total_pages, $page + 2);
    if ($s > 1) { echo "<button class='pg' onclick=\"location='{$pg_base}page=1'\">1</button>"; if ($s > 2) echo "<span class='pg-dots'>…</span>"; }
    for ($i = $s; $i <= $e; $i++) { $on = $i === $page ? 'on' : ''; echo "<button class='pg $on' onclick=\"location='{$pg_base}page={$i}'\">{$i}</button>"; }
    if ($e < $total_pages) { if ($e < $total_pages - 1) echo "<span class='pg-dots'>…</span>"; echo "<button class='pg' onclick=\"location='{$pg_base}page={$total_pages}'\">{$total_pages}</button>"; }
    $nd = $page >= $total_pages ? 'disabled' : '';
    $np = min($total_pages, $page + 1);
    echo "<button class='pg' $nd onclick=\"location='{$pg_base}page={$np}'\"><i class='fa-solid fa-chevron-right'></i></button>";
    ?>
  </div>
  <?php endif; ?>

</div><!-- /page-body -->

<button class="btt" id="btt" title="Voltar ao topo"><i class="fa-solid fa-arrow-up"></i></button>

<?php include 'includes/footer.html'; ?>

<script>
(function(){
'use strict';

/* ══ Config ══ */
var _LOGGED = <?= $user_logged_in ? 'true' : 'false' ?>;
var _CSRF   = <?= json_encode($_SESSION['csrf_token']) ?>;

/* ══ Category bar scroll ══ */
window.scrollCat = function(dx) {
  const bar = document.getElementById('catBar');
  if (bar) bar.scrollBy({ left: dx, behavior: 'smooth' });
};
// Show/hide arrows based on scroll
(function() {
  const bar = document.getElementById('catBar');
  const btnL = document.getElementById('catLeft');
  const btnR = document.getElementById('catRight');
  if (!bar || !btnL || !btnR) return;
  function upd() {
    btnL.style.display = bar.scrollLeft > 8 ? 'flex' : 'none';
    btnR.style.display = (bar.scrollLeft + bar.clientWidth) < (bar.scrollWidth - 8) ? 'flex' : 'none';
  }
  bar.addEventListener('scroll', upd, { passive: true });
  window.addEventListener('resize', upd);
  upd();
})();

/* ══ Filter dropdowns ══ */
window.toggleDrop = function(id, btn) {
  const d = document.getElementById(id);
  if (!d) return;
  const isOpen = d.classList.contains('open');
  // Close all
  document.querySelectorAll('.f-drop.open').forEach(x => x.classList.remove('open'));
  document.querySelectorAll('.f-drop-btn.open').forEach(x => x.classList.remove('open'));
  if (!isOpen) { d.classList.add('open'); if (btn) btn.classList.add('open'); }
};
document.addEventListener('click', function(e) {
  if (!e.target.closest('.f-drop-wrap')) {
    document.querySelectorAll('.f-drop.open').forEach(x => x.classList.remove('open'));
    document.querySelectorAll('.f-drop-btn.open').forEach(x => x.classList.remove('open'));
  }
});

/* ══ Sort ══ */
window.sortBy = function(val) {
  const p = new URLSearchParams(window.location.search);
  p.set('sort', val); p.delete('page');
  location.href = 'shopping.php?' + p.toString();
};

/* ══ View grid/list ══ */
window.setView = function(v) {
  const grid = document.getElementById('pgrid');
  const bG = document.getElementById('vGrid');
  const bL = document.getElementById('vList');
  grid.classList.remove('lv');
  bG.classList.remove('on'); bL.classList.remove('on');
  if (v === 'list') { grid.classList.add('lv'); bL.classList.add('on'); }
  else              { bG.classList.add('on'); }
  try { localStorage.setItem('vsg_view_v2', v); } catch(_) {}
};
try { const sv = localStorage.getItem('vsg_view_v2'); if (sv && sv !== 'grid') setView(sv); } catch(_) {}

/* ══ Toast helper ══ */
function toast(msg, tp) {
  document.querySelectorAll('.vsg-toast').forEach(t => t.remove());
  const c = { success: ['#f0fdf4','#166534','#bbf7d0'], error: ['#fef2f2','#991b1b','#fecaca'] };
  const [bg, co, bd] = c[tp] || c.success;
  const ic = tp === 'error' ? 'exclamation-circle' : 'check-circle';
  if (!document.getElementById('_vsgks')) {
    const s = document.createElement('style'); s.id = '_vsgks';
    s.textContent = '@keyframes _vsgIn{from{transform:translateY(60px);opacity:0}to{transform:none;opacity:1}}';
    document.head.appendChild(s);
  }
  const el = document.createElement('div');
  el.className = 'vsg-toast flash flash-' + tp;
  el.style.cssText = `position:fixed;bottom:24px;right:20px;z-index:9999;background:${bg};color:${co};border-color:${bd};animation:_vsgIn .3s cubic-bezier(.22,1,.36,1);top:auto`;
  el.innerHTML = `<i class="fa-solid fa-${ic}"></i><span>${msg}</span>`;
  document.body.appendChild(el);
  setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 320); }, 3000);
}

/* ══ Badge sync ══ */
function syncBadge(n) {
  document.querySelectorAll('.cart-badge').forEach(el => {
    el.textContent = n > 99 ? '99+' : n;
    el.style.display = n > 0 ? '' : 'none';
  });
  if (n > 0 && !document.querySelector('.cart-badge')) {
    const cb = document.querySelector('.cart-btn');
    if (cb) { const b = document.createElement('span'); b.className = 'cart-badge'; b.textContent = n > 99 ? '99+' : n; cb.appendChild(b); }
  }
}

/* ══ Add to Cart ══ */
window.addToCart = function(productId, btn, meta) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> <span>...</span>';

  if (_LOGGED) {
    fetch('ajax/ajax_cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: 'action=add&product_id=' + productId + '&quantity=1&csrf_token=' + encodeURIComponent(_CSRF)
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Adicionado</span>';
        btn.classList.add('added');
        syncBadge(data.cart_count || 0);
        try { sessionStorage.setItem('cart_count', data.cart_count); } catch(_) {}
        toast('Produto adicionado ao carrinho!', 'success');
        setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('added'); btn.disabled = false; }, 2200);
      } else {
        btn.innerHTML = orig; btn.disabled = false;
        if (data.redirect) location.href = data.redirect;
        else toast(data.message || 'Erro ao adicionar.', 'error');
      }
    })
    .catch(() => { btn.innerHTML = orig; btn.disabled = false; toast('Erro de conexão.', 'error'); });
  } else {
    try {
      const LS = 'vsg_cart_v2';
      const d  = JSON.parse(localStorage.getItem(LS) || '{}');
      const mx = (meta && meta.stock) ? parseInt(meta.stock) : 9999;
      d[productId] = {
        qty: Math.min(((d[productId] && d[productId].qty) || 0) + 1, mx),
        name: (meta && meta.name) || '', price: parseFloat(meta && meta.price) || 0,
        img: (meta && meta.img) || '', stock: mx,
        category: (meta && meta.category) || 'Geral', icon: (meta && meta.icon) || 'box',
        company: (meta && meta.company) || '',
      };
      localStorage.setItem(LS, JSON.stringify(d));
      const total = Object.values(d).reduce((a, i) => a + (parseInt(i.qty) || 0), 0);
      syncBadge(total);
      btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Adicionado</span>';
      btn.classList.add('added');
      toast('Produto adicionado ao carrinho!', 'success');
      setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('added'); btn.disabled = false; }, 2200);
    } catch(e) {
      btn.innerHTML = orig; btn.disabled = false;
      toast('Não foi possível adicionar.', 'error');
    }
  }
};

/* Badge para visitantes */
if (!_LOGGED) {
  try {
    const d = JSON.parse(localStorage.getItem('vsg_cart_v2') || '{}');
    const n = Object.values(d).reduce((a, i) => a + (parseInt(i.qty) || 0), 0);
    if (n > 0) {
      const badge = document.querySelector('.cart-badge');
      if (badge) { badge.textContent = n > 99 ? '99+' : n; }
      else { const cb = document.querySelector('.cart-btn'); if (cb) { const b = document.createElement('span'); b.className = 'cart-badge'; b.textContent = n > 99 ? '99+' : n; cb.appendChild(b); } }
    }
  } catch(_) {}
}

/* ══ Search dropdown ══ */
const inp = document.getElementById('searchInput');
const sdrop = document.getElementById('sDrop');
const xBtn = document.getElementById('searchX');
const goBtn = document.getElementById('searchGo');
const hdrIn = document.getElementById('hdrIn');
const cancelBtn = document.getElementById('searchCancel');
let sTimer = null;

/* Search expand / collapse (mobile) */
function isMobile() { return window.innerWidth <= 600; }

function expandSearch() {
  if (!isMobile()) return;
  hdrIn.classList.add('search-active');
  inp.focus();
}
function collapseSearch() {
  hdrIn.classList.remove('search-active');
  hideDrop();
}

inp?.addEventListener('focus', () => {
  expandSearch();
  if (inp.value.trim().length >= 2 && sdrop.innerHTML) sdrop.style.display = 'block';
});
cancelBtn?.addEventListener('click', () => {
  collapseSearch();
  inp.value = '';
  xBtn.style.display = 'none';
});
/* Colapsa quando muda o tamanho da janela para desktop */
window.addEventListener('resize', () => {
  if (!isMobile()) hdrIn.classList.remove('search-active');
}, { passive: true });

function escH(t) { return String(t??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function hlWord(text, term) {
  if (!term) return escH(text);
  const re = new RegExp('('+term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi');
  return escH(text).replace(re,'<span class="s-hi">$1</span>');
}
function showDrop(html) { sdrop.innerHTML = html; sdrop.style.display = 'block'; }
function hideDrop() { sdrop.style.display = 'none'; }

function doSearch(term) {
  showDrop('<div class="s-drop-loading"><div class="s-spin"></div><p>Procurando…</p></div>');
  fetch('pages/app/ajax/ajax_search.php?search=' + encodeURIComponent(term) + '&limit=8')
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.products?.length) {
        showDrop('<div class="s-drop-empty"><i class="fa-solid fa-magnifying-glass"></i><p>Sem resultados para "' + escH(term) + '"</p></div>');
        return;
      }
      let html = '<div class="s-drop-hd">Produtos</div><ul class="s-drop-list">';
      data.products.forEach(p => {
        html += `<li class="s-drop-item"><a href="${escH(p.url)}">
          <img class="s-drop-img" src="${escH(p.imagem)}" alt="${escH(p.nome)}"
               onerror="this.src='https://ui-avatars.com/api/?name=P&size=80&background=00b96b&color=fff'">
          <div class="s-drop-info">
            <div class="s-drop-name">${hlWord(p.nome, term)}</div>
            <div class="s-drop-cat"><i class="fa-solid fa-tag"></i>${escH(p.category_name||'Produto')}</div>
          </div>
          <div class="s-drop-price">MT ${escH(p.preco_formatado)}</div>
        </a></li>`;
      });
      html += '</ul><a class="s-drop-all" href="shopping.php?search=' + encodeURIComponent(inp.value) + '">Ver todos <i class="fa-solid fa-arrow-right"></i></a>';
      showDrop(html);
    })
    .catch(() => { showDrop('<div class="s-drop-empty"><i class="fa-solid fa-circle-exclamation"></i><p>Erro ao buscar.</p></div>'); });
}

if (inp) {
  inp.addEventListener('input', () => {
    const v = inp.value.trim();
    xBtn.style.display = v ? 'block' : 'none';
    clearTimeout(sTimer);
    if (v.length >= 2) sTimer = setTimeout(() => doSearch(v), 280);
    else hideDrop();
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Enter') { const v = inp.value.trim(); if (v.length >= 2) location.href = 'shopping.php?search=' + encodeURIComponent(v); }
  });
  if (inp.value.trim()) xBtn.style.display = 'block';
}
xBtn?.addEventListener('click', () => { inp.value = ''; xBtn.style.display = 'none'; hideDrop(); inp.focus(); });
goBtn?.addEventListener('click', () => { const v = inp.value.trim(); if (v.length >= 2) location.href = 'shopping.php?search=' + encodeURIComponent(v); });
document.addEventListener('click', e => { if (!e.target.closest('.search-wrap')) hideDrop(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { hideDrop(); collapseSearch(); } });

/* ══ Back to top ══ */
const btt = document.getElementById('btt');
window.addEventListener('scroll', () => btt.classList.toggle('vis', scrollY > 400), { passive: true });
btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

/* ══ Flash auto-hide ══ */
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 350); }, 5000);

/* ══ Currency ══ */
const CACHE_KEY = 'vsg_rates';
const CACHE_DUR = 6*60*60*1000;
const BASE = 'MZN';
const SYM = {MZN:'MT',EUR:'€',BRL:'R$',USD:'$',GBP:'£',CAD:'CA$',AUD:'A$',JPY:'¥',CNY:'¥',CHF:'Fr',AOA:'Kz',ZAR:'R',MXN:'MX$'};
const PREFIX_S = new Set(['USD','GBP','EUR','CAD','AUD','CHF']);
function getCur() {
  try { const s = localStorage.getItem('vsg_preferred_currency'); if (s) return s.toUpperCase(); } catch(_) {}
  const el = document.querySelector('[data-price-mzn]');
  return '<?= esc($user_currency_info['currency']) ?>';
}
function convert(amt, rates, from, to) {
  if (!rates || from === to) return amt;
  const d = rates[`${from}_${to}`]; if (d) return amt * d;
  const a = rates[`${from}_${BASE}`], b = rates[`${BASE}_${to}`]; if (a && b) return amt * a * b;
  const inv = rates[`${to}_${from}`]; if (inv) return amt / inv;
  return amt;
}
function fmtCur(amt, cur) {
  const sym = SYM[cur] || cur;
  const dec = ['JPY','KRW'].includes(cur) ? 0 : 2;
  const n = new Intl.NumberFormat('pt-MZ', { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(amt);
  return PREFIX_S.has(cur) ? `${sym} ${n}` : `${n} ${sym}`;
}
function applyRates(rates) {
  const cur = getCur(); if (cur === BASE) return;
  document.querySelectorAll('[data-price-mzn]').forEach(el => {
    const mzn = parseFloat(el.dataset.priceMzn); if (isNaN(mzn)) return;
    el.textContent = fmtCur(convert(mzn, rates, BASE, cur), cur);
    const sym = el.previousElementSibling;
    if (sym && sym.classList.contains('sym')) sym.textContent = SYM[cur] || cur;
  });
}
function loadCache() {
  try { const raw = localStorage.getItem(CACHE_KEY); if (raw) { const d = JSON.parse(raw); if (Date.now() - d.ts < CACHE_DUR) return d.rates; } } catch(_) {}
  return null;
}
function saveCache(rates) { try { localStorage.setItem(CACHE_KEY, JSON.stringify({ rates, ts: Date.now() })); } catch(_) {} }
async function fetchRates(silent) {
  try {
    const r = await fetch('/api/get_exchange_rates.php?t=' + Date.now(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: AbortSignal.timeout(5000) });
    const data = await r.json();
    if (data.success && data.rates) { saveCache(data.rates); applyRates(data.rates); }
  } catch(e) { if (!silent) console.warn('[VSG]', e.message); }
}
const cached = loadCache();
if (cached) { applyRates(cached); fetchRates(true); } else fetchRates(false);
window.addEventListener('currency-changed', () => { const r = loadCache(); if (r) applyRates(r); else fetchRates(false); });

})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>