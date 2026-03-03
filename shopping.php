<?php
/*
 * shopping.php — VSG Marketplace  |  MySQL 8.0.44
 * ─────────────────────────────────────────────────────────────────────
 * CAUSA DA LENTIDÃO: geo_location.php faz chamadas HTTP externas
 * (ex: ip-api.com, ipinfo.io) a cada request — pode demorar 2-60s.
 * SOLUÇÃO: removido. Usamos só a sessão para país/moeda.
 * ─────────────────────────────────────────────────────────────────────
 * MIGRATION RECOMENDADA — executar 1× no MySQL para activar busca
 * inteligente por relevância (FULLTEXT). Sem isso o sistema usa LIKE.
 *
 *   ALTER TABLE products
 *     ADD FULLTEXT INDEX ft_products_search (nome, descricao);
 *
 * Após criar o index, a página detecta-o automaticamente e passa a
 * usar MATCH...AGAINST IN BOOLEAN MODE, que é ~10× mais rápido em
 * tabelas grandes e ordena por relevância real.
 * ─────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/registration/bootstrap.php';
require_once __DIR__ . '/registration/includes/security.php';

ob_start();

// ── Helpers ────────────────────────────────────────────────────────────
function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function getProductImageUrl($p) {
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
/**
 * Destaca o termo de busca no texto, com segurança XSS.
 * Retorna HTML com <span class="hl"> nos trechos encontrados.
 */
function highlightTerm(string $text, string $term): string {
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if ($term === '') return $safe;
    // Divide o termo em palavras para highlight individual
    $words = array_filter(explode(' ', preg_replace('/\s+/', ' ', $term)));
    foreach ($words as $w) {
        if (mb_strlen($w) < 2) continue;
        $pat  = '/' . preg_quote(htmlspecialchars($w, ENT_QUOTES, 'UTF-8'), '/') . '/iu';
        $safe = preg_replace($pat, '<span class="hl">$0</span>', $safe);
    }
    return $safe;
}

// ── Moeda: mapeamento inline sem chamada externa ────────────────────────
$currency_map = [
    'MZ' => ['currency' => 'MZN', 'symbol' => 'MT',   'rate' => 1],
    'BR' => ['currency' => 'BRL', 'symbol' => 'R$',    'rate' => 0.062],
    'PT' => ['currency' => 'EUR', 'symbol' => '€',     'rate' => 0.015],
    'US' => ['currency' => 'USD', 'symbol' => '$',     'rate' => 0.016],
    'GB' => ['currency' => 'GBP', 'symbol' => '£',     'rate' => 0.013],
    'ZA' => ['currency' => 'ZAR', 'symbol' => 'R',     'rate' => 0.29],
    'AO' => ['currency' => 'AOA', 'symbol' => 'Kz',    'rate' => 15.2],
];
function get_currency_info_inline($country_code) {
    global $currency_map;
    return $currency_map[strtoupper($country_code)]
        ?? ['currency' => 'MZN', 'symbol' => 'MT', 'rate' => 1];
}

// ── Sessão / Auth (sem query ao DB) ────────────────────────────────────
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_name      = $user_logged_in ? ($_SESSION['auth']['nome']   ?? 'Usuário') : null;
$user_avatar    = $user_logged_in ? ($_SESSION['auth']['avatar'] ?? null)      : null;
$user_type      = $user_logged_in ? ($_SESSION['auth']['type']   ?? 'person')  : null;
$user_id        = $user_logged_in ? (int)$_SESSION['auth']['user_id']          : 0;

$user_country_code = $_SESSION['auth']['country_code']
    ?? $_SESSION['user_location']['country_code']
    ?? $_SESSION['user_location']['country']
    ?? 'MZ';
$user_currency_info = get_currency_info_inline($user_country_code);

// ── Flash / CSRF ───────────────────────────────────────────────────────
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type    = $_SESSION['flash_type']    ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Parâmetros GET ─────────────────────────────────────────────────────
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

// ── Eco badges ────────────────────────────────────────────────────────
$eco_opts = [
    'organico'       => 'Orgânico',       'reciclavel'     => 'Reciclável',
    'biodegradavel'  => 'Biodegradável',  'compostavel'    => 'Compostável',
    'zero_waste'     => 'Zero Waste',     'comercio_justo' => 'Comércio Justo',
    'certificado'    => 'Certificado',    'vegano'         => 'Vegano',
];

$sort_map = [
    'recent'     => 'p.created_at DESC',
    'bestseller' => 'p.total_sales DESC, p.id DESC',
    'price_asc'  => 'p.preco ASC',
    'price_desc' => 'p.preco DESC',
    'rating'     => 'p.total_sales DESC, p.created_at DESC',
];
// Quando há busca activa e o utilizador não escolheu outra ordenação,
// usa relevância como critério primário
$default_sort = ($search !== '') ? 'relevance' : 'recent';
$sort         = in_array($sort, array_keys($sort_map)) ? $sort : $default_sort;
$order_sql    = ($sort === 'relevance' || ($search !== '' && $sort === 'recent'))
    ? "search_score DESC, p.total_sales DESC, p.created_at DESC"
    : ($sort_map[$sort] ?? $sort_map['recent']);

// ── Verificar se FULLTEXT index existe ────────────────────────────────
// Tenta usar MATCH...AGAINST (relevância real). Se o index não existir,
// cai para LIKE como fallback — sem quebrar em nenhum ambiente.
$use_fulltext  = false;
$search_score  = '0';   // coluna de relevância injectada no SELECT
if ($search !== '') {
    $ft_check = $mysqli->query("
        SELECT 1 FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE()
          AND table_name   = 'products'
          AND index_type   = 'FULLTEXT'
          AND index_name   = 'ft_products_search'
        LIMIT 1
    ");
    $use_fulltext = $ft_check && $ft_check->num_rows > 0;
    if ($ft_check) $ft_check->free();
}

// ── WHERE dinâmico ─────────────────────────────────────────────────────
$where  = ["p.status = 'ativo'", "p.deleted_at IS NULL", "p.stock > 0"];
$params = [];
$types  = '';

if ($search !== '') {
    if ($use_fulltext) {
        // FULLTEXT boolean mode: tolera parciais, ordena por relevância
        $ft_term     = '+' . implode(' +', array_filter(explode(' ', preg_replace('/[^\w\s]/u', ' ', $search)))) . '*';
        // Fallback para IN BOOLEAN MODE se o termo ficar vazio
        if (trim(str_replace('+', '', $ft_term)) === '*') {
            $use_fulltext = false;
        } else {
            $where[]      = "MATCH(p.nome, p.descricao) AGAINST(? IN BOOLEAN MODE)";
            $search_score = "MATCH(p.nome, p.descricao) AGAINST(? IN BOOLEAN MODE)";
            $params[]     = $ft_term; $types .= 's';
        }
    }
    if (!$use_fulltext) {
        // LIKE como fallback — funciona sempre
        $where[]  = "(p.nome LIKE ? OR p.descricao LIKE ?)";
        $wild     = "%{$search}%";
        $params[] = $wild; $params[] = $wild; $types .= 'ss';
        // Relevância simulada: nome bate vale mais do que descrição
        $search_score = "(CASE WHEN p.nome LIKE ? THEN 2 ELSE 1 END)";
    }
}
if ($category_id > 0) { $where[] = 'p.category_id = ?'; $params[] = $category_id; $types .= 'i'; }
if ($supplier_id > 0) { $where[] = 'p.user_id = ?';     $params[] = $supplier_id; $types .= 'i'; }
if ($min_price   > 0) { $where[] = 'p.preco >= ?';       $params[] = $min_price;   $types .= 'd'; }
if ($max_price   > 0) { $where[] = 'p.preco <= ?';       $params[] = $max_price;   $types .= 'd'; }
if ($eco_filter !== '') {
    $where[] = "JSON_CONTAINS(p.eco_badges, JSON_QUOTE(?))";
    $params[] = $eco_filter; $types .= 's';
}
$where_sql = implode(' AND ', $where);

// ── SIDEBAR: cache de sessão 5 min ─────────────────────────────────────
if (isset($_SESSION['_sb_cache']) && (time() - ($_SESSION['_sb_cache']['ts'] ?? 0)) < 300) {
    $categories = $_SESSION['_sb_cache']['cats'];
    $suppliers  = $_SESSION['_sb_cache']['sups'];
} else {
    $cr = $mysqli->query("
        SELECT c.id, c.name, c.icon, COUNT(p.id) AS cnt
        FROM categories c
        LEFT JOIN products p
               ON p.category_id = c.id
              AND p.status = 'ativo' AND p.deleted_at IS NULL AND p.stock > 0
        WHERE c.parent_id IS NULL AND c.status = 'ativa'
        GROUP BY c.id, c.name, c.icon
        ORDER BY cnt DESC, c.name ASC LIMIT 20
    ");
    $categories = $cr ? $cr->fetch_all(MYSQLI_ASSOC) : [];
    if ($cr) $cr->free();

    $sr = $mysqli->query("
        SELECT u.id, u.nome, COUNT(p.id) AS cnt
        FROM users u
        INNER JOIN products p
               ON p.user_id = u.id
              AND p.status = 'ativo' AND p.deleted_at IS NULL AND p.stock > 0
        WHERE u.type = 'company' AND u.status = 'active'
        GROUP BY u.id, u.nome
        ORDER BY cnt DESC LIMIT 15
    ");
    $suppliers = $sr ? $sr->fetch_all(MYSQLI_ASSOC) : [];
    if ($sr) $sr->free();

    $_SESSION['_sb_cache'] = ['ts' => time(), 'cats' => $categories, 'sups' => $suppliers];
}

// ── Contagem total ─────────────────────────────────────────────────────
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

// ── Query de produtos ──────────────────────────────────────────────────
// search_score: coluna de relevância usada no ORDER BY
// Para FULLTEXT precisa do termo 2× (WHERE + ORDER BY)
// Para LIKE simulado precisa do termo 3× (WHERE nome, WHERE descricao, ORDER score)
$score_col   = $search !== '' ? "({$search_score}) AS search_score" : "0 AS search_score";
$products_sql = "
    SELECT
        p.id, p.nome, p.preco, p.currency,
        p.imagem, p.image_path1,
        p.stock, p.created_at, p.eco_badges, p.total_sales,
        COALESCE(c.name,  '')   AS category_name,
        COALESCE(c.icon, 'box') AS category_icon,
        COALESCE(u.nome,  '')   AS company_name,
        COALESCE((SELECT ROUND(AVG(r.rating),1) FROM customer_reviews r WHERE r.product_id = p.id), 0) AS avg_rating,
        COALESCE((SELECT COUNT(*) FROM customer_reviews r WHERE r.product_id = p.id), 0) AS review_count,
        {$score_col}
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN users      u ON u.id = p.user_id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT ? OFFSET ?
";

// Params extras para o score no SELECT
$score_params = [];
$score_types  = '';
if ($search !== '' && $use_fulltext) {
    // FULLTEXT: repete o $ft_term para o score no SELECT
    $score_params = [$ft_term];
    $score_types  = 's';
} elseif ($search !== '' && !$use_fulltext) {
    // LIKE simulado: repete wild para o CASE no SELECT
    $score_params = ["%{$search}%"];
    $score_types  = 's';
}

$products   = [];
$st = $mysqli->prepare($products_sql);
if ($st) {
    // Ordem dos params: [score_params] + [where_params] + [limit, offset]
    $all_params = array_merge($score_params, $params, [$per_page, $offset]);
    $all_types  = $score_types . $types . 'ii';
    $st->bind_param($all_types, ...$all_params);
    $st->execute();
    $products = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

$new_cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));

// ── Cart count ─────────────────────────────────────────────────────────
$cart_count = 0;
if ($user_logged_in) {
    if (isset($_SESSION['cart_count'])) {
        $cart_count = (int)$_SESSION['cart_count'];
    } else {
        $st = $mysqli->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0) AS n
            FROM shopping_carts sc
            INNER JOIN cart_items ci ON ci.cart_id = sc.id
            WHERE sc.user_id = ? AND sc.status = 'active'
        ");
        if ($st) {
            $st->bind_param('i', $user_id);
            $st->execute();
            $cart_count = (int)$st->get_result()->fetch_assoc()['n'];
            $st->close();
            $_SESSION['cart_count'] = $cart_count;
        }
    }
}

// ── Filtros activos ────────────────────────────────────────────────────
$active_filters = [];
if ($search !== '') $active_filters[] = ['label' => "Busca: $search", 'remove' => 'search'];
if ($category_id > 0) {
    $cn = '';
    foreach ($categories as $c) { if ($c['id'] == $category_id) { $cn = $c['name']; break; } }
    $active_filters[] = ['label' => "Categoria: $cn", 'remove' => 'category'];
}
if ($supplier_id > 0) {
    $sn = '';
    foreach ($suppliers as $s) { if ($s['id'] == $supplier_id) { $sn = $s['nome']; break; } }
    $active_filters[] = ['label' => "Fornecedor: $sn", 'remove' => 'supplier'];
}
if ($min_price > 0 || $max_price > 0) {
    $lbl  = 'Preço: ';
    $lbl .= $min_price > 0 ? 'MT ' . number_format($min_price, 0, '.', ',') : '';
    $lbl .= ' — ';
    $lbl .= $max_price > 0 ? 'MT ' . number_format($max_price, 0, '.', ',') : '';
    $active_filters[] = ['label' => $lbl, 'remove' => 'price'];
}
if ($eco_filter !== '') {
    $active_filters[] = ['label' => 'Eco: ' . ($eco_opts[$eco_filter] ?? $eco_filter), 'remove' => 'eco'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="VSG Marketplace — <?= $total_rows ?> produtos sustentáveis">
<title>Shopping — VSG Marketplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
<link rel="stylesheet" href="assets/style/footer.css">
<style>
/* ═══════════════════════════════════════════
   TOKENS
═══════════════════════════════════════════ */
:root {
  --gr:       #00b96b;
  --gr-d:     #009956;
  --gr-l:     #e6faf2;
  --gr-ring:  #6ee7b7;
  --ink:      #111827;
  --ink-2:    #4b5563;
  --ink-3:    #9ca3af;
  --bg:       #f3f4f6;
  --sur:      #ffffff;
  --bdr:      #e5e7eb;
  --bdr-2:    #f0f2f4;
  --blue:     #2563eb;
  --amber:    #f59e0b;
  --red:      #ef4444;

  --sh-xs: 0 1px 2px rgba(0,0,0,.06);
  --sh-sm: 0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06);
  --sh-md: 0 4px 16px rgba(0,0,0,.1);
  --sh-lg: 0 10px 30px rgba(0,0,0,.12);

  --r4: 4px; --r6: 6px; --r8: 8px; --r10: 10px;
  --r12: 12px; --r16: 16px; --r99: 999px;

  --ease: cubic-bezier(.16,1,.3,1);
  --fast: .15s;
  --mid:  .25s;

  --font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;

  --sb-w: 256px;
  --hdr-h: 56px;
  --strip-h: 30px;
  /* cat-bar REMOVIDA — top offset simplificado */
  --top-total: calc(var(--strip-h) + var(--hdr-h));
}

/* ═══════════════════════════════════════════
   RESET
═══════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body { font-family: var(--font); font-size: 14px; line-height: 1.5;
       background: var(--bg); color: var(--ink); -webkit-font-smoothing: antialiased; }
a { text-decoration: none; color: inherit; }
img { display: block; max-width: 100%; }
button { font-family: var(--font); cursor: pointer; border: none; background: none; }
input, select { font-family: var(--font); }
.container { max-width: 1360px; margin: 0 auto; padding: 0 20px; }

/* ═══════════════════════════════════════════
   TOP STRIP
═══════════════════════════════════════════ */
.top-strip {
  background: var(--ink); color: rgba(255,255,255,.75);
  font-size: 11.5px; line-height: var(--strip-h);
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.ts-in { display: flex; justify-content: space-between; align-items: center; height: var(--strip-h); }
.ts-left { display: flex; align-items: center; gap: 10px; }
.ts-right { display: flex; align-items: center; gap: 2px; list-style: none; }
.ts-link {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: var(--r4); color: rgba(255,255,255,.75);
  transition: color var(--fast), background var(--fast);
}
.ts-link:hover { color: var(--gr); background: rgba(255,255,255,.06); }
.ts-div { width: 1px; height: 12px; background: rgba(255,255,255,.15); margin: 0 2px; }

/* ═══════════════════════════════════════════
   HEADER
═══════════════════════════════════════════ */
.main-header {
  background: var(--sur); border-bottom: 1px solid var(--bdr);
  position: sticky; top: 0; z-index: 500;
  box-shadow: var(--sh-xs);
  height: var(--hdr-h);
  padding: 10px 0px;
}
.hdr-in {
  display: flex; align-items: center; gap: 12px;
  height: 100%; padding: 0;
}

.logo {
  display: flex; align-items: center; gap: 6px; flex-shrink: 0;
  font-size: 18px; font-weight: 800; letter-spacing: -.5px; color: var(--ink);
  transition: opacity var(--fast);
}
.logo:hover { opacity: .8; }
.logo-icon {
  width: 32px; height: 32px; background: var(--gr);
  border-radius: var(--r8); display: grid; place-items: center;
  font-size: 15px; color: #fff; flex-shrink: 0;
}
.logo-text em { color: var(--gr); font-style: normal; }
.logo-sub { font-size: 10px; font-weight: 500; color: var(--ink-3); letter-spacing: .3px; display: block; line-height: 1; }

/* Search bar */
.search-wrap { flex: 1; max-width: 600px; position: relative; }
.search-form { display: flex; height: 38px; }
.search-input {
  flex: 1; height: 100%;
  padding: 0 40px 0 14px;
  border: 1.5px solid var(--bdr); border-right: none;
  border-radius: var(--r8) 0 0 var(--r8);
  font-size: 13.5px; color: var(--ink); background: var(--bg);
  outline: none; transition: border-color var(--fast), box-shadow var(--fast), background var(--fast);
}
.search-input::placeholder { color: var(--ink-3); }
.search-input:focus { border-color: var(--gr); background: var(--sur); box-shadow: 0 0 0 3px rgba(0,185,107,.12); }
.search-clear {
  position: absolute; right: 46px; top: 50%; transform: translateY(-50%);
  color: var(--ink-3); font-size: 14px; padding: 4px 6px; display: none;
  transition: color var(--fast);
}
.search-clear:hover { color: var(--ink); }
.search-btn {
  width: 46px; height: 100%;
  background: var(--gr); border: 1.5px solid var(--gr);
  border-radius: 0 var(--r8) var(--r8) 0;
  color: #fff; font-size: 15px;
  transition: background var(--fast);
}
.search-btn:hover { background: var(--gr-d); }

/* Search dropdown */
.search-dropdown {
  position: absolute; top: calc(100% + 6px); left: 0; right: 0;
  background: var(--sur); border: 1px solid var(--bdr);
  border-radius: var(--r12); box-shadow: var(--sh-lg);
  max-height: 70vh; overflow-y: auto; display: none; z-index: 700;
  animation: dropIn .16s var(--ease);
}
@keyframes dropIn { from { opacity:0; transform:translateY(-6px) scale(.98); } to { opacity:1; transform:none; } }
.sd-head {
  padding: 10px 14px 8px; border-bottom: 1px solid var(--bdr-2);
  font-size: 10.5px; font-weight: 700; letter-spacing: .7px;
  text-transform: uppercase; color: var(--ink-3);
  position: sticky; top: 0; background: var(--sur); z-index: 2;
}
.sd-list { list-style: none; }
.sd-item a { display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-bottom: 1px solid var(--bdr-2); transition: background var(--fast); }
.sd-item:last-child a { border-bottom: none; }
.sd-item a:hover { background: var(--bg); }
.sd-img { width: 44px; height: 44px; min-width: 44px; object-fit: cover; border-radius: var(--r6); border: 1px solid var(--bdr-2); background: var(--bg); }
.sd-info { flex: 1; min-width: 0; }
.sd-name { font-size: 13px; font-weight: 600; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 2px; }
.sd-cat { font-size: 11px; color: var(--ink-3); display: flex; align-items: center; gap: 4px; }
.sd-price { font-size: 14px; font-weight: 700; color: var(--gr); white-space: nowrap; }
.sd-see-all { display: block; padding: 10px 14px; text-align: center; font-size: 12.5px; font-weight: 600; color: var(--blue); border-top: 1px solid var(--bdr); position: sticky; bottom: 0; background: var(--sur); transition: background var(--fast); }
.sd-see-all:hover { background: var(--bg); }
.sd-empty, .sd-loading { padding: 28px 14px; text-align: center; color: var(--ink-3); }
.sd-empty i { font-size: 34px; color: var(--bdr); display: block; margin-bottom: 10px; }
.sd-spin { width: 24px; height: 24px; margin: 0 auto 8px; border: 3px solid var(--bdr); border-top-color: var(--gr); border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.sd-hi { background: rgba(255,220,0,.35); font-weight: 700; border-radius: 2px; padding: 0 2px; }

/* Header right */
.hdr-right { margin-left: auto; display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.cart-btn {
  position: relative; display: flex; align-items: center; justify-content: center;
  width: 38px; height: 38px; border-radius: var(--r8);
  color: var(--ink); font-size: 17px;
  transition: background var(--fast), color var(--fast);
}
.cart-btn:hover { background: var(--bg); color: var(--gr); }
.cart-badge {
  position: absolute; top: 2px; right: 2px;
  background: var(--red); color: #fff;
  font-size: 9px; font-weight: 700; min-width: 15px; height: 15px;
  border-radius: var(--r99); display: grid; place-items: center; padding: 0 3px;
  border: 1.5px solid var(--sur);
}
.hdr-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 14px; border-radius: var(--r8);
  border: 1.5px solid var(--bdr); font-size: 13px; font-weight: 600; color: var(--ink);
  background: var(--sur); white-space: nowrap;
  transition: border-color var(--fast), background var(--fast);
}
.hdr-btn:hover { border-color: var(--gr); background: var(--gr-l); }
.hdr-btn.pri { background: var(--gr); border-color: var(--gr); color: #fff; }
.hdr-btn.pri:hover { background: var(--gr-d); border-color: var(--gr-d); }
.hdr-btn img { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; }

/* ═══════════════════════════════════════════
   BREADCRUMB
═══════════════════════════════════════════ */
.breadcrumb {
  background: transparent; padding: 10px 0 4px;
  font-size: 12px; color: var(--ink-3);
}
.bc-in { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.bc-in a { color: var(--ink-3); transition: color var(--fast); }
.bc-in a:hover { color: var(--gr); }
.bc-in i { font-size: 8px; }
.bc-in .cur { color: var(--ink); font-weight: 600; }

/* ═══════════════════════════════════════════
   SHOP LAYOUT
═══════════════════════════════════════════ */
.shop-wrap {
  max-width: 1360px; margin: 0 auto;
  padding: 16px 20px 80px;
  display: grid;
  grid-template-columns: var(--sb-w) 1fr;
  gap: 20px;
  align-items: start;
}

/* ═══════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════ */
.sidebar {
  display: flex; flex-direction: column; gap: 10px;
  position: sticky;
  top: calc(var(--hdr-h) + 16px);
  max-height: calc(100vh - var(--hdr-h) - 32px);
  overflow-y: auto;
  overflow-x: hidden;
  scroll-behavior: smooth;
  scrollbar-width: thin;
  scrollbar-color: var(--bdr) transparent;
  padding-right: 4px;
}
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: var(--bdr); border-radius: 4px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: var(--ink-3); }

.sb-card {
  background: var(--sur); border: 1px solid var(--bdr);
  border-radius: var(--r10); overflow: hidden; flex-shrink: 0;
}
.sb-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px 9px; border-bottom: 1px solid var(--bdr-2);
  position: sticky; top: 0; background: var(--sur); z-index: 2;
}
.sb-title {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 700; letter-spacing: .6px;
  text-transform: uppercase; color: var(--ink);
}
.sb-title i { color: var(--gr); font-size: 11px; }
.sb-clear-btn { font-size: 11px; color: var(--gr); padding: 0; transition: color var(--fast); }
.sb-clear-btn:hover { color: var(--gr-d); }

.sb-list { list-style: none; padding: 4px 8px 8px; }
.sb-item a {
  display: flex; align-items: center; justify-content: space-between;
  padding: 6px 8px; border-radius: var(--r6); font-size: 12.5px; color: var(--ink);
  transition: background var(--fast), color var(--fast);
}
.sb-item a:hover { background: var(--bg); color: var(--gr); }
.sb-item.on a { background: var(--gr-l); color: var(--gr); font-weight: 700; }
.sb-il { display: flex; align-items: center; gap: 7px; min-width: 0; }
.sb-il i { width: 14px; text-align: center; font-size: 11px; color: var(--ink-3); flex-shrink: 0; }
.sb-item.on .sb-il i { color: var(--gr); }
.sb-il span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sb-cnt {
  font-size: 10.5px; color: var(--ink-3); flex-shrink: 0;
  background: var(--bg); padding: 1px 6px; border-radius: var(--r99);
  border: 1px solid var(--bdr);
}
.sb-item.on .sb-cnt { background: #dcfce7; color: #065f46; border-color: var(--gr-ring); }

/* Preço */
.sb-price-body { padding: 12px 14px; }
.price-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
.price-row label { font-size: 11px; color: var(--ink-3); margin-bottom: 3px; display: block; font-weight: 600; }
.price-row input {
  width: 100%; padding: 7px 10px;
  border: 1.5px solid var(--bdr); border-radius: var(--r6);
  font-size: 12.5px; color: var(--ink); background: var(--bg);
  outline: none; transition: border-color var(--fast);
}
.price-row input:focus { border-color: var(--gr); background: var(--sur); }
.btn-price {
  width: 100%; padding: 8px;
  background: var(--gr); border-radius: var(--r6);
  color: #fff; font-size: 12.5px; font-weight: 700;
  transition: background var(--fast);
}
.btn-price:hover { background: var(--gr-d); }

/* Eco */
.sb-eco { padding: 4px 10px 10px; display: flex; flex-direction: column; gap: 1px; }
.eco-row {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 8px; border-radius: var(--r6); cursor: pointer;
  transition: background var(--fast);
}
.eco-row:hover { background: var(--bg); }
.eco-row input[type=radio] { accent-color: var(--gr); flex-shrink: 0; }
.eco-row span { font-size: 12.5px; color: var(--ink); }
.eco-row.on span { color: var(--gr); font-weight: 700; }

/* ═══════════════════════════════════════════
   ÁREA PRINCIPAL
═══════════════════════════════════════════ */
.main-area { min-width: 0; display: flex; flex-direction: column; gap: 12px; }

/* Botão filtros mobile */
.mob-filter {
  display: none; align-items: center; justify-content: space-between;
  padding: 10px 14px; width: 100%;
  background: var(--sur); border: 1.5px solid var(--bdr);
  border-radius: var(--r10); font-size: 13px; font-weight: 600; color: var(--ink);
  transition: border-color var(--fast), color var(--fast);
}
.mob-filter:hover { border-color: var(--gr); color: var(--gr); }
.mob-filter-left { display: flex; align-items: center; gap: 8px; }
.badge-cnt {
  background: var(--red); color: #fff;
  font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: var(--r99);
}

/* Overlay sidebar mobile */
.sb-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.5); z-index: 490;
  backdrop-filter: blur(3px);
}
.sb-overlay.open { display: block; }

/* Pills filtros activos */
.active-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.a-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; background: var(--gr-l); border: 1px solid var(--gr-ring);
  border-radius: var(--r99); font-size: 11.5px; font-weight: 700; color: #065f46;
}
.a-pill a { color: #065f46; font-size: 13px; line-height: 1; transition: color var(--fast); }
.a-pill a:hover { color: var(--red); }
.a-pill-clear {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border: 1px solid var(--bdr); border-radius: var(--r99);
  font-size: 11.5px; color: var(--ink-3);
  transition: border-color var(--fast), color var(--fast);
}
.a-pill-clear:hover { border-color: var(--red); color: var(--red); }

/* Barra controlo */
.ctrl-bar {
  display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
  padding: 10px 14px;
  background: var(--sur); border: 1px solid var(--bdr); border-radius: var(--r10);
}
.ctrl-count { flex: 1; font-size: 12.5px; color: var(--ink-3); min-width: 120px; }
.ctrl-count strong { color: var(--ink); font-weight: 700; }
.ctrl-right { display: flex; align-items: center; gap: 8px; }
.ctrl-sort { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--ink-3); }
.ctrl-select {
  padding: 5px 26px 5px 10px;
  border: 1.5px solid var(--bdr); border-radius: var(--r6);
  font-size: 12.5px; font-weight: 600; color: var(--ink);
  background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 8px center;
  appearance: none; outline: none; cursor: pointer;
  transition: border-color var(--fast);
}
.ctrl-select:focus { border-color: var(--gr); }
.ctrl-view { display: flex; gap: 3px; }
.view-btn {
  width: 30px; height: 30px; border-radius: var(--r6);
  border: 1.5px solid var(--bdr); display: grid; place-items: center;
  font-size: 13px; color: var(--ink-3);
  transition: all var(--fast);
}
.view-btn.on, .view-btn:hover { background: var(--gr); border-color: var(--gr); color: #fff; }

/* ═══════════════════════════════════════════
   GRID PRODUTOS — vista padrão
═══════════════════════════════════════════ */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
}

/* Vista lista */
.products-grid.lv { grid-template-columns: 1fr; }
.products-grid.lv .pcard { flex-direction: row; max-height: 160px; }
.products-grid.lv .pcard-img { padding-top: 0; width: 160px; min-width: 160px; height: 160px; flex-shrink: 0; }
.products-grid.lv .pcard-img img { position: static; width: 100%; height: 100%; }
.products-grid.lv .pcard-body { padding: 14px 18px; }
.products-grid.lv .pcard-name { -webkit-line-clamp: 1; min-height: auto; font-size: 15px; }

/* ═══════════════════════════════════════════
   SHOWCASE VIEW — novo modo impactante
   Layout editorial: hero grande + cards menores
═══════════════════════════════════════════ */
.products-grid.sv {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  grid-auto-rows: 220px;
  gap: 14px;
}

/* Card padrão no showcase */
.products-grid.sv .pcard {
  grid-column: span 3;
  grid-row: span 1;
}

/* Cada 7º card (posições 1, 8, 15…) é hero landscape */
.products-grid.sv .pcard:nth-child(7n+1) {
  grid-column: span 6;
  grid-row: span 2;
}
/* Cada 7º+4 card é hero portrait */
.products-grid.sv .pcard:nth-child(7n+5) {
  grid-column: span 3;
  grid-row: span 2;
}

/* Cards na vista showcase ficam em flex column com imagem ocupando espaço */
.products-grid.sv .pcard {
  flex-direction: column;
  max-height: none;
  overflow: hidden;
}
.products-grid.sv .pcard-img {
  flex: 1;
  padding-top: 0;
  min-height: 0;
}
.products-grid.sv .pcard-img img {
  position: absolute;
  inset: 0; width: 100%; height: 100%;
}

/* Cards hero (span 2 linhas) têm overlay escuro com info na base */
.products-grid.sv .pcard:nth-child(7n+1),
.products-grid.sv .pcard:nth-child(7n+5) {
  position: relative;
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-img,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-img {
  position: absolute;
  inset: 0;
  padding-top: 0;
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-body,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-body {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  background: linear-gradient(to top, rgba(0,0,0,.88) 0%, rgba(0,0,0,.55) 55%, transparent 100%);
  padding: 20px 16px 14px;
  z-index: 3;
  border-top: none;
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-name,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-name {
  color: #fff;
  font-size: 15px;
  -webkit-line-clamp: 2;
  min-height: auto;
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-cat,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-cat {
  color: var(--gr-ring);
  margin-bottom: 4px;
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-sup,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-sup {
  color: rgba(255,255,255,.65);
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-stars .rv,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-stars .rv {
  color: rgba(255,255,255,.5);
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-foot,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-foot {
  border-top: 1px solid rgba(255,255,255,.15);
  margin-top: 8px;
  padding-top: 8px;
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-price .sym,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-price .sym {
  color: rgba(255,255,255,.55);
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-price .val,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-price .val {
  font-size: 1.4rem;
  color: #fff;
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-stock.ok,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-stock.ok {
  background: rgba(0,185,107,.25);
  color: #6ee7b7;
  border-color: rgba(0,185,107,.4);
}
.products-grid.sv .pcard:nth-child(7n+1) .pcard-stock.low,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-stock.low {
  background: rgba(239,68,68,.2);
  color: #fca5a5;
  border-color: rgba(239,68,68,.35);
}
/* Hero card: imagem com scale mais suave */
.products-grid.sv .pcard:nth-child(7n+1):hover .pcard-img img,
.products-grid.sv .pcard:nth-child(7n+5):hover .pcard-img img {
  transform: scale(1.04);
}

/* Etiqueta "Destaque" nos heroes */
.products-grid.sv .pcard:nth-child(7n+1) .pcard-badges,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-badges {
  z-index: 4;
}

/* Linha separadora entre grupos de 7 — efeito editorial */
.sv-divider {
  grid-column: 1 / -1;
  height: 1px;
  background: linear-gradient(to right, transparent, var(--bdr) 20%, var(--bdr) 80%, transparent);
  align-self: center;
}

/* Showcase: esconde acções nos heroes (overlay não tem espaço) */
.products-grid.sv .pcard:nth-child(7n+1) .pcard-actions,
.products-grid.sv .pcard:nth-child(7n+5) .pcard-actions {
  display: none;
}

/* Efeito de entrada para showcase */
.products-grid.sv .pcard {
  animation: fadeUp .35s var(--ease) both;
}
.products-grid.sv .pcard:nth-child(2)  { animation-delay: .04s; }
.products-grid.sv .pcard:nth-child(3)  { animation-delay: .08s; }
.products-grid.sv .pcard:nth-child(4)  { animation-delay: .12s; }
.products-grid.sv .pcard:nth-child(5)  { animation-delay: .16s; }
.products-grid.sv .pcard:nth-child(6)  { animation-delay: .20s; }
.products-grid.sv .pcard:nth-child(7)  { animation-delay: .24s; }
.products-grid.sv .pcard:nth-child(n+8) { animation-delay: .28s; }

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ═══════════════════════════════════════════
   CARD — estilos comuns
═══════════════════════════════════════════ */
.pcard {
  background: var(--sur); border: 1px solid var(--bdr); border-radius: var(--r10);
  overflow: hidden; display: flex; flex-direction: column;
  text-decoration: none; color: inherit;
  transition: box-shadow var(--mid) var(--ease), transform var(--mid) var(--ease), border-color var(--mid);
}
.pcard:hover { box-shadow: var(--sh-md); transform: translateY(-3px); border-color: rgba(0,185,107,.35); }

.pcard-img {
  position: relative; width: 100%; padding-top: 100%;
  background: var(--bg); overflow: hidden;
}
.pcard-img img {
  position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
  transition: transform var(--mid) var(--ease);
}
.pcard:hover .pcard-img img { transform: scale(1.06); }

.pcard-badges { position: absolute; top: 8px; left: 8px; display: flex; flex-direction: column; gap: 4px; z-index: 2; }
.pbadge {
  display: inline-block; padding: 3px 7px; border-radius: var(--r4);
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
}
.pbadge-new  { background: #3b82f6; color: #fff; }
.pbadge-hot  { background: var(--amber); color: #fff; }
.pbadge-eco  { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }

.pcard-acts {
  position: absolute; top: 8px; right: 8px; z-index: 2;
  display: flex; flex-direction: column; gap: 5px;
  opacity: 0; transform: translateX(8px);
  transition: opacity var(--mid) var(--ease), transform var(--mid) var(--ease);
}
.pcard:hover .pcard-acts { opacity: 1; transform: translateX(0); }
.pact-btn {
  width: 30px; height: 30px;
  background: var(--sur); border: 1px solid var(--bdr); border-radius: 50%;
  display: grid; place-items: center; font-size: 12.5px; color: var(--ink-3);
  box-shadow: var(--sh-xs);
  transition: background var(--fast), color var(--fast), border-color var(--fast);
}
.pact-btn:hover { background: var(--gr); color: #fff; border-color: var(--gr); }

.pcard-body { padding: 12px; flex: 1; display: flex; flex-direction: column; }
.pcard-cat {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10.5px; font-weight: 700; color: var(--gr);
  text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px;
}
.pcard-cat i { font-size: 9px; }
.pcard-name {
  font-size: 13px; font-weight: 700; color: var(--ink); line-height: 1.4;
  margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden; min-height: 36px;
}
.pcard-sup {
  font-size: 11.5px; color: var(--ink-3);
  display: flex; align-items: center; gap: 4px; margin-bottom: 8px;
}
.pcard-sup i { font-size: 9px; }
.pcard-stars { display: flex; align-items: center; gap: 5px; margin-bottom: 10px; }
.stars { display: flex; gap: 1px; }
.stars i { font-size: 11px; color: var(--amber); }
.stars .fa-regular { color: var(--bdr); }
.rv { font-size: 11px; color: var(--ink-3); }
.pcard-foot {
  display: flex; align-items: flex-end; justify-content: space-between;
  padding-top: 10px; border-top: 1px solid var(--bdr-2); margin-top: auto;
  gap: 6px;
}
.pcard-price { display: flex; flex-direction: column; }
.pcard-price .sym { font-size: 10px; color: var(--ink-3); font-weight: 600; }
.pcard-price .val {
  font-size: 1.15rem; font-weight: 800; color: var(--gr);
  letter-spacing: -.3px; line-height: 1.1;
}
.pcard-stock {
  font-size: 10px; font-weight: 700; padding: 3px 7px;
  border-radius: var(--r4); border: 1px solid; white-space: nowrap;
}
.pcard-stock.ok  { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.pcard-stock.low { background: #fef2f2; color: #991b1b; border-color: #fecaca; }

.pcard-actions {
  display: flex; gap: 6px; margin-top: 8px;
  opacity: 0; transform: translateY(4px);
  transition: opacity var(--mid), transform var(--mid);
}
.pcard:hover .pcard-actions { opacity: 1; transform: translateY(0); }

.pcard-cart {
  flex: 1; padding: 8px 6px;
  background: var(--gr); border-radius: var(--r6);
  color: #fff; font-size: 12px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; gap: 5px;
  transition: background var(--fast); cursor: pointer; border: none;
  font-family: var(--font);
}
.pcard-cart:hover { background: var(--gr-d); }
.pcard-cart.added { background: #166534; }

.pcard-buynow {
  flex-shrink: 0; width: 34px; height: 34px;
  background: var(--ink); border-radius: var(--r6);
  color: #fff; font-size: 13px;
  display: flex; align-items: center; justify-content: center;
  transition: background var(--fast); cursor: pointer;
}
.pcard-buynow:hover { background: #374151; }

/* ═══════════════════════════════════════════
   SEARCH RESULTS BANNER
═══════════════════════════════════════════ */
.search-banner {
  background: var(--sur);
  border: 1px solid var(--bdr);
  border-radius: var(--r10);
  padding: 18px 20px 16px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  animation: fadeUp .3s var(--ease);
}
.sb-left { flex: 1; min-width: 0; }
.sb-label {
  font-size: 11px; font-weight: 700; letter-spacing: .7px;
  text-transform: uppercase; color: var(--ink-3);
  display: flex; align-items: center; gap: 6px;
  margin-bottom: 6px;
}
.sb-label i { color: var(--gr); }
.sb-query {
  font-size: 1.35rem; font-weight: 800; color: var(--ink);
  line-height: 1.2; margin-bottom: 8px;
  display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;
}
.sb-query .q-word {
  color: var(--gr);
  position: relative;
  display: inline-block;
}
.sb-query .q-word::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 3px;
  background: var(--gr);
  border-radius: var(--r99);
  opacity: .35;
}
.sb-meta {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.sb-count {
  font-size: 13px; color: var(--ink-2);
  display: flex; align-items: center; gap: 5px;
}
.sb-count strong { color: var(--ink); font-weight: 700; }
.sb-mode-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px;
  border-radius: var(--r99); font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .4px;
}
.sb-mode-badge.ft  { background: #dcfce7; color: #065f46; border: 1px solid #bbf7d0; }
.sb-mode-badge.lk  { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.sb-right { flex-shrink: 0; }
.sb-clear-search {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px;
  border: 1.5px solid var(--bdr); border-radius: var(--r8);
  font-size: 12.5px; font-weight: 600; color: var(--ink-2);
  transition: border-color var(--fast), color var(--fast), background var(--fast);
}
.sb-clear-search:hover {
  border-color: var(--red); color: var(--red); background: #fef2f2;
}
/* Highlight do termo nos nomes dos cards */
.hl {
  background: rgba(255,220,0,.4);
  color: var(--ink);
  border-radius: 2px;
  padding: 0 2px;
  font-weight: 700;
}
/* Empty state específico para busca */
.empty-search {
  grid-column: 1 / -1; text-align: center; padding: 60px 24px;
  background: var(--sur); border: 1px solid var(--bdr); border-radius: var(--r16);
}
.empty-search-icon {
  width: 72px; height: 72px; border-radius: 50%;
  background: var(--bg); border: 2px dashed var(--bdr);
  display: grid; place-items: center; margin: 0 auto 20px;
  font-size: 28px; color: var(--ink-3);
}
.empty-search h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; }
.empty-search p { font-size: 13px; color: var(--ink-3); margin-bottom: 20px; max-width: 380px; margin-left: auto; margin-right: auto; }
.empty-search-tips {
  display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-bottom: 22px;
}
.tip-pill {
  padding: 5px 12px; background: var(--bg); border: 1px solid var(--bdr);
  border-radius: var(--r99); font-size: 12px; color: var(--ink-2);
  display: flex; align-items: center; gap: 5px;
}
.tip-pill i { color: var(--gr); font-size: 11px; }
.empty-search-actions { display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
.btn-browse {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 20px; background: var(--gr); border-radius: var(--r8);
  color: #fff; font-weight: 700; font-size: 13px; transition: background var(--fast);
}
.btn-browse:hover { background: var(--gr-d); }
.btn-browse-out {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 20px; border: 1.5px solid var(--bdr); border-radius: var(--r8);
  color: var(--ink-2); font-weight: 600; font-size: 13px;
  transition: border-color var(--fast), color var(--fast);
}
.btn-browse-out:hover { border-color: var(--gr); color: var(--gr); }
.empty-state {
  grid-column: 1 / -1; text-align: center; padding: 72px 24px;
  background: var(--sur); border: 1px solid var(--bdr); border-radius: var(--r16);
}
.empty-state i { font-size: 52px; color: var(--bdr); display: block; margin-bottom: 16px; }
.empty-state h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: 8px; }
.empty-state p  { font-size: 13px; color: var(--ink-3); margin-bottom: 22px; }
.empty-state a  {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 22px; background: var(--gr); border-radius: var(--r8);
  color: #fff; font-weight: 700; font-size: 13.5px; transition: background var(--fast);
}
.empty-state a:hover { background: var(--gr-d); }

/* ═══════════════════════════════════════════
   PAGINAÇÃO
═══════════════════════════════════════════ */
.pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 5px; margin-top: 24px; flex-wrap: wrap;
}
.pg-btn {
  min-width: 36px; height: 36px; padding: 0 8px;
  background: var(--sur); border: 1.5px solid var(--bdr); border-radius: var(--r8);
  font-size: 13px; font-weight: 600; color: var(--ink);
  display: flex; align-items: center; justify-content: center;
  transition: all var(--fast);
}
.pg-btn:hover:not(:disabled) { border-color: var(--gr); color: var(--gr); }
.pg-btn.on { background: var(--gr); border-color: var(--gr); color: #fff; }
.pg-btn:disabled { opacity: .35; pointer-events: none; }
.pg-dots { color: var(--ink-3); line-height: 36px; padding: 0 2px; font-weight: 700; }

/* ═══════════════════════════════════════════
   FLASH
═══════════════════════════════════════════ */
.flash {
  position: fixed; top: 72px; right: 16px; z-index: 9999;
  display: flex; align-items: center; gap: 10px;
  padding: 12px 38px 12px 14px; border-radius: var(--r10);
  box-shadow: var(--sh-lg); border: 1px solid;
  font-size: 13.5px; max-width: 360px;
  animation: slideIn .3s var(--ease); transition: opacity .3s;
}
.flash-success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
.flash-error   { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
.flash-info    { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.flash-close {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  font-size: 18px; opacity: .5; color: currentColor; transition: opacity var(--fast);
}
.flash-close:hover { opacity: 1; }
@keyframes slideIn { from { transform: translateX(420px); opacity: 0; } to { transform: none; opacity: 1; } }

/* ═══════════════════════════════════════════
   BACK TO TOP
═══════════════════════════════════════════ */
.btt {
  position: fixed; bottom: 24px; right: 20px; z-index: 300;
  width: 40px; height: 40px;
  background: var(--gr); border-radius: 50%;
  color: #fff; font-size: 16px; box-shadow: var(--sh-md);
  display: none; place-items: center;
  transition: transform var(--mid), background var(--fast);
}
.btt.vis { display: grid; }
.btt:hover { background: var(--gr-d); transform: translateY(-3px); }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 1200px) {
  :root { --sb-w: 230px; }
  .shop-wrap { gap: 16px; }
}
@media (max-width: 1024px) {
  :root { --sb-w: 210px; }
  .products-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
  .hdr-btn .btn-text { display: none; }
  .ts-left { display: none; }
  /* Showcase em tablet: menos colunas */
  .products-grid.sv { grid-template-columns: repeat(8, 1fr); grid-auto-rows: 200px; }
  .products-grid.sv .pcard { grid-column: span 4; }
  .products-grid.sv .pcard:nth-child(7n+1) { grid-column: span 8; }
  .products-grid.sv .pcard:nth-child(7n+5) { grid-column: span 4; }
}
@media (max-width: 820px) {
  :root { --sb-w: 280px; }
  .shop-wrap { grid-template-columns: 1fr; padding: 12px 16px 80px; }
  .sidebar {
    position: fixed;
    top: 0; left: -300px; bottom: 0;
    width: var(--sb-w);
    background: var(--bg);
    z-index: 510;
    max-height: 100vh;
    padding: 0 12px 20px;
    border-radius: 0 var(--r16) var(--r16) 0;
    box-shadow: 6px 0 32px rgba(0,0,0,.15);
    transition: left .3s var(--ease);
    overflow-y: auto;
    top: 0;
  }
  .sidebar.open { left: 0; }
  .sb-sidebar-close {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 6px 12px; margin-bottom: 4px;
    border-bottom: 1px solid var(--bdr);
    position: sticky; top: 0; background: var(--bg); z-index: 3;
  }
  .sb-sidebar-close-title {
    font-size: 15px; font-weight: 700; color: var(--ink);
    display: flex; align-items: center; gap: 8px;
  }
  .sb-sidebar-close-title i { color: var(--gr); }
  .sb-close-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--bg); border: 1px solid var(--bdr);
    display: grid; place-items: center; font-size: 14px; color: var(--ink-3);
    transition: all var(--fast);
  }
  .sb-close-btn:hover { background: var(--red); color: #fff; border-color: var(--red); }
  .mob-filter { display: flex; }
  .search-wrap { max-width: none; flex: 1; }
  /* Showcase no tablet small */
  .products-grid.sv { grid-template-columns: repeat(6, 1fr); grid-auto-rows: 180px; }
  .products-grid.sv .pcard { grid-column: span 3; }
  .products-grid.sv .pcard:nth-child(7n+1) { grid-column: span 6; }
  .products-grid.sv .pcard:nth-child(7n+5) { grid-column: span 3; }
}
@media (max-width: 600px) {
  :root { --hdr-h: 52px; }
  .container { padding: 0 12px; }
  .shop-wrap { padding: 10px 12px 80px; gap: 10px; }
  .products-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .products-grid.lv { grid-template-columns: 1fr; }
  .products-grid.lv .pcard { max-height: 130px; }
  .products-grid.lv .pcard-img { width: 130px; min-width: 130px; height: 130px; }
  .pcard-cart { display: none; }
  .pcard-actions { display: none; }
  .products-grid.sv { grid-template-columns: repeat(2, 1fr); grid-auto-rows: 260px; }
  .products-grid.sv .pcard { grid-column: span 1; grid-row: span 1; }
  .products-grid.sv .pcard:nth-child(7n+1),
  .products-grid.sv .pcard:nth-child(7n+5) { grid-column: span 2; grid-row: span 1; }
  .logo-sub { display: none; }
  .logo { font-size: 16px; }
  .logo-icon { width: 28px; height: 28px; font-size: 13px; }
  .hdr-btn { padding: 6px 10px; font-size: 12px; }
  .hdr-btn span.btn-text { display: none; }
  .ctrl-bar { padding: 8px 10px; }
  .ctrl-sort span { display: none; }
  .breadcrumb { padding: 8px 0 2px; }
}
@media (max-width: 390px) {
  .products-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
  .pcard-name { font-size: 12px; }
  .pcard-body { padding: 8px; }
  .pcard-price .val { font-size: 1rem; }
}
@media (max-width: 320px) {
  .products-grid { grid-template-columns: 1fr; }
  .hdr-right .hdr-btn:not(.pri) { display: none; }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
</style>
</head>
<body>

<?php if ($flash_message): ?>
<div class="flash flash-<?= esc($flash_type) ?>" id="flashMsg">
    <i class="fa-solid fa-<?= $flash_type==='success'?'check-circle':($flash_type==='error'?'exclamation-circle':'info-circle') ?>"></i>
    <span><?= esc($flash_message) ?></span>
    <button class="flash-close" onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>

<!-- ══ TOP STRIP ══════════════════════════════════ -->
<div class="top-strip">
  <div class="container">
    <div class="ts-in">
      <div class="ts-left">
        <span class="ts-link"><i class="fa-solid fa-coins"></i> Moeda: <strong id="currDisplay"><?= esc($user_currency_info['currency']) ?></strong></span>
        <span class="ts-link"><i class="fa-solid fa-leaf"></i> Marketplace Sustentável</span>
      </div>
      <ul class="ts-right">
        <?php if ($user_logged_in && $user_type === 'company'): ?>
          <li><a href="pages/person/index.php" class="ts-link">Meu Painel</a></li>
          <li><span class="ts-div"></span></li>
        <?php endif; ?>
        <li><a href="#" class="ts-link">Ajuda</a></li>
        <li><span class="ts-div"></span></li>
        <li><a href="#" class="ts-link">Rastrear Pedido</a></li>
      </ul>
    </div>
  </div>
</div>

<!-- ══ HEADER ══════════════════════════════════════ -->
<header class="main-header">
  <div class="container">
    <div class="hdr-in">
      <a href="index.php" class="logo">
        <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
        <div>
          <div class="logo-text">VSG<em>•</em>MARKET</div>
          <span class="logo-sub">MARKETPLACE</span>
        </div>
      </a>

      <div class="search-wrap">
        <form class="search-form" onsubmit="return false;">
          <input type="text" id="searchInput" class="search-input"
                 placeholder="Buscar produtos, marcas, categorias..."
                 value="<?= esc($search) ?>" autocomplete="off">
          <button type="button" class="search-clear" id="searchClear"><i class="fa-solid fa-xmark"></i></button>
          <button type="button" class="search-btn" id="searchBtn"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="search-dropdown" id="searchDropdown"></div>
      </div>

      <div class="hdr-right">
        <a href="cart.php" class="cart-btn" title="Carrinho">
          <i class="fa-solid fa-cart-shopping"></i>
          <?php if ($cart_count > 0): ?>
            <span class="cart-badge"><?= $cart_count > 99 ? '99+' : $cart_count ?></span>
          <?php endif; ?>
        </a>
        <?php if ($user_logged_in): ?>
          <a href="pages/person/index.php" class="hdr-btn">
            <?php if ($user_avatar): ?>
              <img src="<?= esc($user_avatar) ?>" alt="avatar">
            <?php else: ?>
              <i class="fa-solid fa-circle-user"></i>
            <?php endif; ?>
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

<!-- ══ OVERLAY SIDEBAR MOBILE ════════════════════ -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSb()"></div>

<!-- ══ SHOP WRAP ══════════════════════════════════ -->
<div class="shop-wrap">

  <!-- ── SIDEBAR ──────────────────────────────── -->
  <aside class="sidebar" id="sidebar">

    <div class="sb-sidebar-close">
      <div class="sb-sidebar-close-title">
        <i class="fa-solid fa-sliders"></i> Filtros
      </div>
      <button class="sb-close-btn" onclick="closeSb()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Categorias -->
    <div class="sb-card">
      <div class="sb-head">
        <div class="sb-title"><i class="fa-solid fa-compass"></i>Categorias</div>
        <?php if ($category_id > 0): ?>
          <button class="sb-clear-btn" onclick="location='<?= removeFilter('category') ?>'">Limpar</button>
        <?php endif; ?>
      </div>
      <ul class="sb-list">
        <li class="sb-item <?= $category_id===0?'on':'' ?>">
          <a href="shopping.php<?= $search ? '?search='.urlencode($search) : '' ?>">
            <div class="sb-il"><i class="fa-solid fa-border-all"></i><span>Todos os Produtos</span></div>
            <span class="sb-cnt"><?= number_format(array_sum(array_column($categories,'cnt'))) ?></span>
          </a>
        </li>
        <?php foreach ($categories as $cat): ?>
        <li class="sb-item <?= $category_id==$cat['id']?'on':'' ?>">
          <a href="<?= filterUrl('category', $cat['id']) ?>">
            <div class="sb-il">
              <i class="fa-solid fa-<?= esc($cat['icon']?:'box') ?>"></i>
              <span><?= esc($cat['name']) ?></span>
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
        <?php if ($min_price>0 || $max_price>0): ?>
          <button class="sb-clear-btn" onclick="location='<?= removeFilter('price') ?>'">Limpar</button>
        <?php endif; ?>
      </div>
      <div class="sb-price-body">
        <form action="shopping.php" method="get">
          <?php foreach ($_GET as $k => $v): if (!in_array($k,['min','max','page'])): ?>
            <input type="hidden" name="<?= esc($k) ?>" value="<?= esc($v) ?>">
          <?php endif; endforeach; ?>
          <div class="price-row">
            <div>
              <label>Mínimo</label>
              <input type="number" name="min" placeholder="0" min="0" value="<?= $min_price>0?$min_price:'' ?>">
            </div>
            <div>
              <label>Máximo</label>
              <input type="number" name="max" placeholder="99999" min="0" value="<?= $max_price>0?$max_price:'' ?>">
            </div>
          </div>
          <button type="submit" class="btn-price"><i class="fa-solid fa-filter"></i> Aplicar</button>
        </form>
      </div>
    </div>

    <!-- Certificações eco -->
    <div class="sb-card">
      <div class="sb-head">
        <div class="sb-title"><i class="fa-solid fa-leaf"></i>Certificação</div>
        <?php if ($eco_filter!==''): ?>
          <button class="sb-clear-btn" onclick="location='<?= removeFilter('eco') ?>'">Limpar</button>
        <?php endif; ?>
      </div>
      <div class="sb-eco">
        <?php foreach ($eco_opts as $val => $label): ?>
        <label class="eco-row <?= $eco_filter===$val?'on':'' ?>">
          <input type="radio" name="eco" value="<?= esc($val) ?>"
                 <?= $eco_filter===$val?'checked':'' ?>
                 onchange="location='<?= filterUrl('eco',$val) ?>'">
          <span><?= esc($label) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Fornecedores -->
    <?php if (!empty($suppliers)): ?>
    <div class="sb-card">
      <div class="sb-head">
        <div class="sb-title"><i class="fa-solid fa-building"></i>Fornecedor</div>
        <?php if ($supplier_id>0): ?>
          <button class="sb-clear-btn" onclick="location='<?= removeFilter('supplier') ?>'">Limpar</button>
        <?php endif; ?>
      </div>
      <ul class="sb-list">
        <?php foreach ($suppliers as $sup): ?>
        <li class="sb-item <?= $supplier_id==$sup['id']?'on':'' ?>">
          <a href="<?= filterUrl('supplier',$sup['id']) ?>">
            <div class="sb-il">
              <i class="fa-solid fa-store"></i>
              <span><?= esc($sup['nome']) ?></span>
            </div>
            <span class="sb-cnt"><?= number_format($sup['cnt']) ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

  </aside>

  <!-- ── ÁREA PRINCIPAL ──────────────────────── -->
  <main class="main-area">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <div class="bc-in">
        <a href="index.php"><i class="fa-solid fa-house"></i></a>
        <i class="fa-solid fa-chevron-right"></i>
        <?php if ($category_id>0):
          foreach ($categories as $c) { if ($c['id']==$category_id) { echo '<span class="cur">'.esc($c['name']).'</span>'; break; } }
        elseif ($search!==''): ?>
          <span>Busca</span>
          <i class="fa-solid fa-chevron-right"></i>
          <span class="cur"><?= esc($search) ?></span>
        <?php else: ?>
          <span class="cur">Shopping</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Botão filtros mobile -->
    <button class="mob-filter" onclick="openSb()">
      <div class="mob-filter-left">
        <i class="fa-solid fa-sliders"></i>
        Filtros e Categorias
        <?php if (!empty($active_filters)): ?>
          <span class="badge-cnt"><?= count($active_filters) ?></span>
        <?php endif; ?>
      </div>
      <i class="fa-solid fa-chevron-right"></i>
    </button>

    <!-- Filtros activos -->
    <?php if (!empty($active_filters)): ?>
    <div class="active-pills">
      <?php foreach ($active_filters as $af): ?>
      <div class="a-pill">
        <?= esc($af['label']) ?>
        <a href="<?= removeFilter($af['remove']) ?>" title="Remover">×</a>
      </div>
      <?php endforeach; ?>
      <a href="shopping.php">
        <button class="a-pill-clear"><i class="fa-solid fa-xmark"></i> Limpar tudo</button>
      </a>
    </div>
    <?php endif; ?>

    <!-- ══ BANNER DE RESULTADOS DE BUSCA ══════════════ -->
    <?php if ($search !== ''): ?>
    <div class="search-banner">
      <div class="sb-left">
        <div class="sb-label">
          <i class="fa-solid fa-magnifying-glass"></i>
          Resultados da busca
        </div>
        <div class="sb-query">
          Resultados para
          <span class="q-word">"<?= esc($search) ?>"</span>
        </div>
        <div class="sb-meta">
          <span class="sb-count">
            <strong><?= number_format($total_rows) ?></strong>
            produto<?= $total_rows !== 1 ? 's' : '' ?> encontrado<?= $total_rows !== 1 ? 's' : '' ?>
          </span>
          <?php if ($use_fulltext): ?>
            <span class="sb-mode-badge ft">
              <i class="fa-solid fa-bolt"></i> Busca inteligente
            </span>
          <?php else: ?>
            <span class="sb-mode-badge lk">
              <i class="fa-solid fa-text-slash"></i> Busca por texto
            </span>
          <?php endif; ?>
          <?php if ($total_pages > 1): ?>
            <span class="sb-count">— Página <?= $page ?> de <?= $total_pages ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="sb-right">
        <a href="<?= removeFilter('search') ?>" class="sb-clear-search">
          <i class="fa-solid fa-xmark"></i> Limpar busca
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Barra controlo -->
    <div class="ctrl-bar">
      <div class="ctrl-count">
        <strong><?= number_format($total_rows) ?></strong>
        produto<?= $total_rows!==1?'s':'' ?>
        <?php if ($search===''): ?>— todos os produtos<?php endif; ?>
      </div>
      <div class="ctrl-right">
        <div class="ctrl-sort">
          <span>Ordenar:</span>
          <select class="ctrl-select" onchange="sortBy(this.value)">
            <?php if ($search !== ''): ?>
            <option value="recent"     <?= ($sort==='recent'||$sort==='relevance')?'selected':'' ?>>Relevância</option>
            <?php else: ?>
            <option value="recent"     <?= $sort==='recent'    ?'selected':'' ?>>Recentes</option>
            <?php endif; ?>
            <option value="bestseller" <?= $sort==='bestseller'?'selected':'' ?>>Mais Vendidos</option>
            <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Menor Preço</option>
            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Maior Preço</option>
            <option value="rating"     <?= $sort==='rating'    ?'selected':'' ?>>Avaliações</option>
          </select>
        </div>
        <div class="ctrl-view">
          <!-- Vista grade (padrão) -->
          <button class="view-btn on" id="btnGrid" onclick="setView('grid')" title="Grade">
            <i class="fa-solid fa-grip"></i>
          </button>
          <!-- Vista showcase editorial -->
          <button class="view-btn" id="btnShowcase" onclick="setView('showcase')" title="Showcase">
            <i class="fa-solid fa-table-cells-large"></i>
          </button>
          <!-- Vista lista -->
          <button class="view-btn" id="btnList" onclick="setView('list')" title="Lista">
            <i class="fa-solid fa-list"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Produtos -->
    <div class="products-grid" id="pgrid">
      <?php if (empty($products)): ?>
      <?php if ($search !== ''): ?>
        <!-- Empty state específico para busca sem resultados -->
        <div class="empty-search">
          <div class="empty-search-icon">
            <i class="fa-solid fa-magnifying-glass"></i>
          </div>
          <h3>Sem resultados para "<?= esc($search) ?>"</h3>
          <p>Não encontrámos produtos que correspondam à sua busca. Experimente as sugestões abaixo.</p>
          <div class="empty-search-tips">
            <div class="tip-pill"><i class="fa-solid fa-lightbulb"></i> Verifique o ortografia</div>
            <div class="tip-pill"><i class="fa-solid fa-minimize"></i> Use termos mais curtos</div>
            <div class="tip-pill"><i class="fa-solid fa-language"></i> Tente em português ou inglês</div>
            <div class="tip-pill"><i class="fa-solid fa-filter"></i> Remova filtros activos</div>
          </div>
          <div class="empty-search-actions">
            <a href="shopping.php" class="btn-browse">
              <i class="fa-solid fa-border-all"></i> Ver todos os produtos
            </a>
            <a href="<?= removeFilter('search') ?>" class="btn-browse-out">
              <i class="fa-solid fa-xmark"></i> Limpar busca
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fa-solid fa-box-open"></i>
          <h3>Nenhum produto encontrado</h3>
          <p>Tente ajustar os filtros ou buscar com outros termos.</p>
          <a href="shopping.php"><i class="fa-solid fa-rotate-left"></i> Ver todos</a>
        </div>
      <?php endif; ?>
      <?php else: ?>
        <?php foreach ($products as $p):
          $img       = getProductImageUrl($p);
          $avg       = (float)($p['avg_rating'] ?? 0);
          $rating    = (int)round($avg);
          $isNew     = ($p['created_at'] ?? '') >= $new_cutoff;
          $isHot     = ((int)($p['total_sales'] ?? 0)) > 0;
          $isLow     = $p['stock'] > 0 && $p['stock'] <= 10;
          $eco_raw   = $p['eco_badges'] ? json_decode($p['eco_badges'], true) : [];
          $eco_first = is_array($eco_raw) && !empty($eco_raw) ? ($eco_opts[$eco_raw[0]] ?? null) : null;
          $nome_hl   = $search !== '' ? highlightTerm($p['nome'], $search) : esc($p['nome']);
          $pid       = (int)$p['id'];
        ?>
        <div class="pcard" onclick="location.href='product.php?id=<?= $pid ?>'">
          <div class="pcard-img">
            <img src="<?= $img ?>" alt="<?= esc($p['nome']) ?>" loading="lazy"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($p['nome']) ?>&size=400&background=00b96b&color=fff&font-size=0.1'">
            <div class="pcard-badges">
              <?php if ($isNew): ?>
                <span class="pbadge pbadge-new">Novo</span>
              <?php elseif ($isHot): ?>
                <span class="pbadge pbadge-hot">Popular</span>
              <?php endif; ?>
              <?php if ($eco_first): ?>
                <span class="pbadge pbadge-eco"><?= esc($eco_first) ?></span>
              <?php endif; ?>
            </div>
            <div class="pcard-acts">
              <button class="pact-btn" onclick="event.stopPropagation()" title="Favoritar">
                <i class="fa-regular fa-heart"></i>
              </button>
              <button class="pact-btn" onclick="event.stopPropagation()" title="Partilhar">
                <i class="fa-regular fa-share-from-square"></i>
              </button>
            </div>
          </div>
          <div class="pcard-body">
            <div class="pcard-cat">
              <i class="fa-solid fa-<?= esc($p['category_icon']?:'box') ?>"></i>
              <?= esc($p['category_name']?:'Geral') ?>
            </div>
            <div class="pcard-name"><?= $nome_hl ?></div>
            <div class="pcard-sup">
              <i class="fa-solid fa-building"></i>
              <?= esc($p['company_name']?:'Fornecedor') ?>
            </div>
            <div class="pcard-stars">
              <div class="stars">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <i class="fa-<?= $i<=$rating?'solid':'regular' ?> fa-star"></i>
                <?php endfor; ?>
              </div>
              <span class="rv"><?= $avg>0?number_format($avg,1):'0.0' ?> (<?= (int)$p['review_count'] ?>)</span>
            </div>
            <div class="pcard-foot">
              <div class="pcard-price">
                <span class="sym"><?= esc($user_currency_info['symbol']) ?></span>
                <span class="val" data-price-mzn="<?= (float)$p['preco'] ?>">
                  <?= number_format($p['preco'] * $user_currency_info['rate'], 2, ',', '.') ?>
                </span>
              </div>
              <span class="pcard-stock <?= $isLow?'low':'ok' ?>">
                <?= $isLow ? 'Últimas '.(int)$p['stock'] : 'Em estoque' ?>
              </span>
            </div>
            <!-- Botões de acção — aparecem no hover -->
            <div class="pcard-actions">
              <button class="pcard-cart"
                onclick="event.stopPropagation(); addToCart(<?= $pid ?>, this)"
                title="Adicionar ao carrinho">
                <i class="fa-solid fa-cart-plus"></i>
                <span>Carrinho</span>
              </button>
              <a href="checkout.php?buy_now=<?= $pid ?>&qty=1"
                onclick="event.stopPropagation()"
                class="pcard-buynow"
                title="Comprar agora">
                <i class="fa-solid fa-bolt"></i>
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php
      $pd = $page<=1?'disabled':'';
      $pp = max(1,$page-1);
      echo "<button class='pg-btn' $pd onclick=\"location='{$pg_base}page={$pp}'\"><i class='fa-solid fa-chevron-left'></i></button>";
      $s=max(1,$page-2); $e=min($total_pages,$page+2);
      if ($s>1) { echo "<button class='pg-btn' onclick=\"location='{$pg_base}page=1'\">1</button>"; if($s>2) echo "<span class='pg-dots'>…</span>"; }
      for ($i=$s;$i<=$e;$i++) { $on=$i===$page?'on':''; echo "<button class='pg-btn $on' onclick=\"location='{$pg_base}page={$i}'\">{$i}</button>"; }
      if ($e<$total_pages) { if($e<$total_pages-1) echo "<span class='pg-dots'>…</span>"; echo "<button class='pg-btn' onclick=\"location='{$pg_base}page={$total_pages}'\">{$total_pages}</button>"; }
      $nd=$page>=$total_pages?'disabled':'';
      $np=min($total_pages,$page+1);
      echo "<button class='pg-btn' $nd onclick=\"location='{$pg_base}page={$np}'\"><i class='fa-solid fa-chevron-right'></i></button>";
      ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<button class="btt" id="btt" title="Voltar ao topo"><i class="fa-solid fa-arrow-up"></i></button>

<?php include 'includes/footer.html'; ?>

<script>
(function(){
'use strict';

/* ── Add to Cart (AJAX) ─────────────────────────── */
window.addToCart = function(productId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>...</span>';
  fetch('ajax/ajax_cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: 'action=add&product_id=' + productId + '&quantity=1&csrf_token=<?= $_SESSION['csrf_token'] ?>'
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Adicionado</span>';
      btn.classList.add('added');
      // Actualiza badge do carrinho
      const badge = document.querySelector('.cart-badge');
      if (data.cart_count !== undefined) {
        if (badge) { badge.textContent = data.cart_count > 99 ? '99+' : data.cart_count; badge.style.display = 'grid'; }
        else {
          const cb = document.querySelector('.cart-btn');
          if (cb) { const b = document.createElement('span'); b.className = 'cart-badge'; b.textContent = data.cart_count; cb.appendChild(b); }
        }
        try { sessionStorage.setItem('cart_count', data.cart_count); } catch(_) {}
      }
      setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('added'); btn.disabled = false; }, 2200);
    } else {
      btn.innerHTML = orig; btn.disabled = false;
      if (data.redirect) { location.href = data.redirect; }
      else { alert(data.message || 'Erro ao adicionar ao carrinho.'); }
    }
  })
  .catch(() => { btn.innerHTML = orig; btn.disabled = false; });
};

/* ── Sidebar mobile ─────────────────────────────── */
function openSb() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sbOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSb() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sbOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
window.openSb  = openSb;
window.closeSb = closeSb;
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeSb(); hideDropdown(); }});

/* ── Ordenação ──────────────────────────────────── */
window.sortBy = function(val) {
  const p = new URLSearchParams(window.location.search);
  p.set('sort', val); p.delete('page');
  location.href = 'shopping.php?' + p.toString();
};

/* ── Vista grid / showcase / lista ─────────────────────────── */
window.setView = function(v) {
  const grid = document.getElementById('pgrid');
  const bG   = document.getElementById('btnGrid');
  const bS   = document.getElementById('btnShowcase');
  const bL   = document.getElementById('btnList');

  // Reset all
  grid.classList.remove('lv', 'sv');
  bG.classList.remove('on');
  bS.classList.remove('on');
  bL.classList.remove('on');

  if (v === 'list') {
    grid.classList.add('lv');
    bL.classList.add('on');
  } else if (v === 'showcase') {
    grid.classList.add('sv');
    bS.classList.add('on');
  } else {
    // grid (padrão)
    bG.classList.add('on');
  }

  try { localStorage.setItem('vsg_view', v); } catch(_){}
};

// Restaurar vista salva
try {
  const saved = localStorage.getItem('vsg_view');
  if (saved && saved !== 'grid') setView(saved);
} catch(_){}

/* ── Back to top ────────────────────────────────── */
const btt = document.getElementById('btt');
window.addEventListener('scroll', ()=> btt.classList.toggle('vis', scrollY>400), {passive:true});
btt.addEventListener('click', ()=> window.scrollTo({top:0,behavior:'smooth'}));

/* ── Flash auto-hide ────────────────────────────── */
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(()=>{ flash.style.opacity='0'; setTimeout(()=>flash.remove(),350); }, 5000);

/* ── Search dropdown ────────────────────────────── */
const input    = document.getElementById('searchInput');
const dropdown = document.getElementById('searchDropdown');
const clearBtn = document.getElementById('searchClear');
const goBtn    = document.getElementById('searchBtn');
let   tm       = null;

function escH(t) {
  return String(t??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function highlight(text, term) {
  if (!term) return escH(text);
  const re = new RegExp('('+term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi');
  return escH(text).replace(re,'<span class="sd-hi">$1</span>');
}
function showDropdown(html) { dropdown.innerHTML=html; dropdown.style.display='block'; }
function hideDropdown() { dropdown.style.display='none'; }

function doSearch(term) {
  showDropdown('<div class="sd-loading"><div class="sd-spin"></div><p>A procurar…</p></div>');
  fetch('pages/app/ajax/ajax_search.php?search='+encodeURIComponent(term)+'&limit=8')
    .then(r=>r.json())
    .then(data=>{
      if (!data.success || !data.products?.length) {
        showDropdown('<div class="sd-empty"><i class="fa-solid fa-magnifying-glass"></i><p>Sem resultados para "'+escH(term)+'"</p></div>');
        return;
      }
      let html='<div class="sd-head">Produtos</div><ul class="sd-list">';
      data.products.forEach(p=>{
        html+=`<li class="sd-item"><a href="${escH(p.url)}">
          <img class="sd-img" src="${escH(p.imagem)}" alt="${escH(p.nome)}"
               onerror="this.src='https://ui-avatars.com/api/?name=P&size=80&background=00b96b&color=fff'">
          <div class="sd-info">
            <div class="sd-name">${highlight(p.nome,term)}</div>
            <div class="sd-cat"><i class="fa-solid fa-tag"></i>${escH(p.category_name||'Produto')}</div>
          </div>
          <div class="sd-price">MT ${escH(p.preco_formatado)}</div>
        </a></li>`;
      });
      html+='</ul><a class="sd-see-all" href="shopping.php?search='+encodeURIComponent(input.value)+'">Ver todos <i class="fa-solid fa-arrow-right"></i></a>';
      showDropdown(html);
    })
    .catch(()=>{ showDropdown('<div class="sd-empty"><i class="fa-solid fa-circle-exclamation"></i><p>Erro ao buscar.</p></div>'); });
}

if (input) {
  input.addEventListener('input', ()=>{
    const v=input.value.trim();
    clearBtn.style.display=v?'block':'none';
    clearTimeout(tm);
    if (v.length>=2) { tm=setTimeout(()=>doSearch(v),300); }
    else hideDropdown();
  });
  input.addEventListener('keydown', e=>{
    if (e.key==='Enter') { const v=input.value.trim(); if(v.length>=2) location.href='shopping.php?search='+encodeURIComponent(v); }
  });
  input.addEventListener('focus', ()=>{ if(input.value.trim().length>=2 && dropdown.innerHTML) dropdown.style.display='block'; });
  if (input.value.trim()) clearBtn.style.display='block';
}
clearBtn?.addEventListener('click',()=>{ input.value=''; clearBtn.style.display='none'; hideDropdown(); input.focus(); });
goBtn?.addEventListener('click',()=>{ const v=input.value.trim(); if(v.length>=2) location.href='shopping.php?search='+encodeURIComponent(v); });
document.addEventListener('click', e=>{ if(dropdown && !dropdown.contains(e.target) && e.target!==input && e.target!==goBtn) hideDropdown(); });

/* ── Currency inline ────────────────────────────── */
const CACHE_KEY = 'vsg_rates';
const CACHE_DUR = 6*60*60*1000;
const BASE_CUR  = 'MZN';
const SYM_MAP   = {MZN:'MT',EUR:'€',BRL:'R$',USD:'$',GBP:'£',CAD:'CA$',AUD:'A$',JPY:'¥',CNY:'¥',CHF:'Fr',AOA:'Kz',ZAR:'R',MXN:'MX$'};
const PREFIX_S  = new Set(['USD','GBP','EUR','CAD','AUD','CHF']);

function getCur() {
  try {
    const s=localStorage.getItem('vsg_preferred_currency'); if(s) return s.toUpperCase();
    const c=document.cookie.match(/vsg_currency=([^;]+)/); if(c) return c[1].toUpperCase();
  } catch(_){}
  const el=document.getElementById('currDisplay');
  return el?el.textContent.trim().toUpperCase():BASE_CUR;
}
function convert(amt,rates,from,to) {
  if(!rates||from===to) return amt;
  const d=rates[`${from}_${to}`]; if(d) return amt*d;
  const a=rates[`${from}_${BASE_CUR}`],b=rates[`${BASE_CUR}_${to}`]; if(a&&b) return amt*a*b;
  const inv=rates[`${to}_${from}`]; if(inv) return amt/inv;
  return amt;
}
function fmtCur(amt,cur) {
  const sym=SYM_MAP[cur]||cur;
  const dec=['JPY','KRW'].includes(cur)?0:2;
  const n=new Intl.NumberFormat('pt-MZ',{minimumFractionDigits:dec,maximumFractionDigits:dec}).format(amt);
  return PREFIX_S.has(cur)?`${sym} ${n}`:`${n} ${sym}`;
}
function applyRates(rates) {
  const cur=getCur(); if(cur===BASE_CUR) return;
  document.querySelectorAll('[data-price-mzn]').forEach(el=>{
    const mzn=parseFloat(el.dataset.priceMzn); if(isNaN(mzn)) return;
    el.textContent=fmtCur(convert(mzn,rates,BASE_CUR,cur),cur);
    const sym=el.previousElementSibling;
    if(sym&&sym.classList.contains('sym')) sym.textContent=SYM_MAP[cur]||cur;
  });
}
function loadCache() {
  try { const raw=localStorage.getItem(CACHE_KEY); if(raw){const d=JSON.parse(raw); if(Date.now()-d.ts<CACHE_DUR) return d.rates;} } catch(_){}
  return null;
}
function saveCache(rates) { try{localStorage.setItem(CACHE_KEY,JSON.stringify({rates,ts:Date.now()}));}catch(_){} }
async function fetchRates(silent) {
  try {
    const r=await fetch('/api/get_exchange_rates.php?t='+Date.now(),{headers:{'X-Requested-With':'XMLHttpRequest'},signal:AbortSignal.timeout(5000)});
    const data=await r.json();
    if(data.success&&data.rates){saveCache(data.rates);applyRates(data.rates);}
  } catch(e){ if(!silent) console.warn('[VSG]',e.message); }
}
const cached=loadCache();
if(cached){applyRates(cached);fetchRates(true);}
else fetchRates(false);
window.addEventListener('currency-changed',()=>{ const r=loadCache();if(r)applyRates(r);else fetchRates(false); });

})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>