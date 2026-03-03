<?php
/*
 * product.php — VSG Marketplace
 * Página de detalhe de produto: galeria, info, tabs, produtos similares
 */

require_once __DIR__ . '/registration/bootstrap.php';
require_once __DIR__ . '/registration/includes/security.php';

ob_start();

// ── Helpers ────────────────────────────────────────────────────────────
function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function imgUrl($p) {
    foreach (['imagem', 'image_path1'] as $col) {
        if (empty($p[$col])) continue;
        $v = $p[$col];
        if (str_starts_with($v, 'http') || str_starts_with($v, 'pages/uploads/')) return $v;
        if (str_starts_with($v, 'products/')) return 'pages/uploads/' . $v;
        return 'pages/uploads/products/' . $v;
    }
    return null;
}
function fallbackImg($name) {
    return 'https://ui-avatars.com/api/?name=' . urlencode($name)
         . '&size=800&background=00b96b&color=fff&bold=true&font-size=0.08';
}

// ── Moeda ──────────────────────────────────────────────────────────────
$currency_map = [
    'MZ' => ['currency' => 'MZN', 'symbol' => 'MT',   'rate' => 1],
    'BR' => ['currency' => 'BRL', 'symbol' => 'R$',    'rate' => 0.062],
    'PT' => ['currency' => 'EUR', 'symbol' => '€',     'rate' => 0.015],
    'US' => ['currency' => 'USD', 'symbol' => '$',     'rate' => 0.016],
    'GB' => ['currency' => 'GBP', 'symbol' => '£',     'rate' => 0.013],
    'ZA' => ['currency' => 'ZAR', 'symbol' => 'R',     'rate' => 0.29],
    'AO' => ['currency' => 'AOA', 'symbol' => 'Kz',    'rate' => 15.2],
];
$user_country_code  = $_SESSION['auth']['country_code']
    ?? $_SESSION['user_location']['country_code']
    ?? 'MZ';
$cur = $currency_map[strtoupper($user_country_code)]
    ?? ['currency' => 'MZN', 'symbol' => 'MT', 'rate' => 1];

// ── Auth ───────────────────────────────────────────────────────────────
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_name      = $user_logged_in ? ($_SESSION['auth']['nome']   ?? 'Usuário') : null;
$user_avatar    = $user_logged_in ? ($_SESSION['auth']['avatar'] ?? null)      : null;
$user_type      = $user_logged_in ? ($_SESSION['auth']['type']   ?? 'person')  : null;
$user_id        = $user_logged_in ? (int)$_SESSION['auth']['user_id']          : 0;

// ── CSRF ───────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Cart count ─────────────────────────────────────────────────────────
$cart_count = 0;
if ($user_logged_in) {
    $cart_count = (int)($_SESSION['cart_count'] ?? 0);
    if (!isset($_SESSION['cart_count'])) {
        $st = $mysqli->prepare("SELECT COALESCE(SUM(ci.quantity),0) AS n FROM shopping_carts sc INNER JOIN cart_items ci ON ci.cart_id=sc.id WHERE sc.user_id=? AND sc.status='active'");
        if ($st) { $st->bind_param('i', $user_id); $st->execute(); $cart_count = (int)$st->get_result()->fetch_assoc()['n']; $st->close(); $_SESSION['cart_count'] = $cart_count; }
    }
}

// ── Produto ────────────────────────────────────────────────────────────
$product_id = (int)($_GET['id'] ?? 0);
if ($product_id <= 0) { header('Location: shopping.php'); exit; }

$p = null;
$st = $mysqli->prepare("
    SELECT p.*,
        COALESCE(c.name, '')        AS category_name,
        COALESCE(c.icon, 'box')     AS category_icon,
        c.id                        AS cat_id,
        COALESCE(u.nome, '')        AS company_name,
        COALESCE(u.email,'')        AS company_email,
        COALESCE(u.telefone,'')     AS company_phone,
        u.id                        AS company_id,
        COALESCE((SELECT ROUND(AVG(r.rating),1) FROM customer_reviews r WHERE r.product_id=p.id),0) AS avg_rating,
        COALESCE((SELECT COUNT(*) FROM customer_reviews r WHERE r.product_id=p.id),0) AS review_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN users      u ON u.id = p.user_id
    WHERE p.id = ? AND p.status = 'ativo' AND p.deleted_at IS NULL
    LIMIT 1
");
if ($st) {
    $st->bind_param('i', $product_id);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    $st->close();
}
if (!$p) { header('Location: shopping.php'); exit; }

// ── Galeria de imagens ─────────────────────────────────────────────────
$gallery = [];
foreach (['imagem','image_path1','image_path2','image_path3','image_path4'] as $col) {
    if (!empty($p[$col])) {
        $v = $p[$col];
        if (!str_starts_with($v,'http') && !str_starts_with($v,'pages/uploads/')) {
            $v = str_starts_with($v,'products/') ? 'pages/uploads/'.$v : 'pages/uploads/products/'.$v;
        }
        if (!in_array($v, $gallery)) $gallery[] = $v;
    }
}
if (empty($gallery)) $gallery[] = fallbackImg($p['nome']);

// ── Reviews ────────────────────────────────────────────────────────────
$reviews = [];
$rr = $mysqli->prepare("
    SELECT r.rating, r.review_text AS comment, r.created_at,
           r.is_verified_purchase,
           COALESCE(u.nome, 'Anónimo') AS user_name,
           COALESCE(u.avatar, '')      AS user_avatar
    FROM customer_reviews r
    LEFT JOIN users u ON u.id = r.customer_id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
if ($rr) {
    $rr->bind_param('i', $product_id);
    $rr->execute();
    $reviews = $rr->get_result()->fetch_all(MYSQLI_ASSOC);
    $rr->close();
}

// ── Produtos similares ─────────────────────────────────────────────────
$related = [];
if ((int)$p['cat_id'] > 0) {
    $sr = $mysqli->prepare("
        SELECT p.id, p.nome, p.preco, p.imagem, p.image_path1, p.total_sales,
               COALESCE((SELECT ROUND(AVG(r2.rating),1) FROM customer_reviews r2 WHERE r2.product_id=p.id),0) AS avg_rating
        FROM products p
        WHERE p.category_id = ?
          AND p.id != ?
          AND p.status = 'ativo' AND p.deleted_at IS NULL AND p.stock > 0
        ORDER BY p.total_sales DESC, p.created_at DESC
        LIMIT 8
    ");
    if ($sr) {
        $cat_id = (int)$p['cat_id'];
        $sr->bind_param('ii', $cat_id, $product_id);
        $sr->execute();
        $related = $sr->get_result()->fetch_all(MYSQLI_ASSOC);
        $sr->close();
    }
}

// ── Cálculos ───────────────────────────────────────────────────────────
$avg        = (float)($p['avg_rating'] ?? 0);
$rating_int = (int)round($avg);
$price_conv = (float)$p['preco'] * $cur['rate'];
$eco_raw    = $p['eco_badges'] ? json_decode($p['eco_badges'], true) : [];
$eco_opts   = ['organico'=>'Orgânico','reciclavel'=>'Reciclável','biodegradavel'=>'Biodegradável',
               'compostavel'=>'Compostável','zero_waste'=>'Zero Waste','comercio_justo'=>'Comércio Justo',
               'certificado'=>'Certificado','vegano'=>'Vegano'];
$is_low     = $p['stock'] > 0 && $p['stock'] <= 10;
$in_stock   = (int)$p['stock'] > 0;

// Distribuição de ratings (para a barra de avaliações)
$rating_dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
foreach ($reviews as $rv) $rating_dist[(int)$rv['rating']] = ($rating_dist[(int)$rv['rating']] ?? 0) + 1;

$page_title = esc($p['nome']) . ' — VSG Marketplace';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= esc(mb_substr(strip_tags($p['descricao'] ?? $p['nome']), 0, 155)) ?>">
<title><?= $page_title ?></title>
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
  --gr:    #00b96b; --gr-d: #009956; --gr-l: #e6faf2; --gr-ring: #6ee7b7;
  --ink:   #111827; --ink-2: #4b5563; --ink-3: #9ca3af;
  --bg:    #f3f4f6; --sur: #ffffff; --bdr: #e5e7eb; --bdr-2: #f0f2f4;
  --blue:  #2563eb; --amber: #f59e0b; --red: #ef4444;
  --sh-xs: 0 1px 2px rgba(0,0,0,.06);
  --sh-sm: 0 1px 3px rgba(0,0,0,.1);
  --sh-md: 0 4px 16px rgba(0,0,0,.1);
  --sh-lg: 0 10px 30px rgba(0,0,0,.12);
  --r4:4px;--r6:6px;--r8:8px;--r10:10px;--r12:12px;--r16:16px;--r99:999px;
  --ease: cubic-bezier(.16,1,.3,1); --fast:.15s; --mid:.25s;
  --font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  --hdr-h: 56px; --strip-h: 30px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{font-family:var(--font);font-size:14px;line-height:1.5;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
img{display:block;max-width:100%}
button{font-family:var(--font);cursor:pointer;border:none;background:none}
input,select,textarea{font-family:var(--font)}
.container{max-width:1360px;margin:0 auto;padding:0 20px}

/* ── Top strip ── */
.top-strip{background:var(--ink);color:rgba(255,255,255,.75);font-size:11.5px;line-height:var(--strip-h)}
.ts-in{display:flex;justify-content:space-between;align-items:center;height:var(--strip-h)}
.ts-right{display:flex;align-items:center;gap:2px;list-style:none}
.ts-link{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;color:rgba(255,255,255,.75);transition:color .15s,background .15s}
.ts-link:hover{color:var(--gr);background:rgba(255,255,255,.06)}
.ts-div{width:1px;height:12px;background:rgba(255,255,255,.15);margin:0 2px}

/* ── Header ── */
.main-header{background:var(--sur);border-bottom:1px solid var(--bdr);position:sticky;top:0;z-index:500;box-shadow:var(--sh-xs);height:var(--hdr-h);padding:10px 0}
.hdr-in{display:flex;align-items:center;gap:12px;height:100%}
.logo{display:flex;align-items:center;gap:6px;flex-shrink:0;font-size:18px;font-weight:800;letter-spacing:-.5px;color:var(--ink);transition:opacity .15s}
.logo:hover{opacity:.8}
.logo-icon{width:32px;height:32px;background:var(--gr);border-radius:var(--r8);display:grid;place-items:center;font-size:15px;color:#fff;flex-shrink:0}
.logo-text em{color:var(--gr);font-style:normal}
.logo-sub{font-size:10px;font-weight:500;color:var(--ink-3);letter-spacing:.3px;display:block;line-height:1}
.hdr-right{margin-left:auto;display:flex;align-items:center;gap:6px;flex-shrink:0}
.cart-btn{position:relative;display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:var(--r8);color:var(--ink);font-size:17px;transition:background .15s,color .15s}
.cart-btn:hover{background:var(--bg);color:var(--gr)}
.cart-badge{position:absolute;top:2px;right:2px;background:var(--red);color:#fff;font-size:9px;font-weight:700;min-width:15px;height:15px;border-radius:var(--r99);display:grid;place-items:center;padding:0 3px;border:1.5px solid var(--sur)}
.hdr-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:var(--r8);border:1.5px solid var(--bdr);font-size:13px;font-weight:600;color:var(--ink);background:var(--sur);white-space:nowrap;transition:border-color .15s,background .15s}
.hdr-btn:hover{border-color:var(--gr);background:var(--gr-l)}
.hdr-btn.pri{background:var(--gr);border-color:var(--gr);color:#fff}
.hdr-btn.pri:hover{background:var(--gr-d)}
.hdr-btn img{width:22px;height:22px;border-radius:50%;object-fit:cover}

/* ── Breadcrumb ── */
.breadcrumb{padding:14px 0 6px;font-size:12px;color:var(--ink-3)}
.bc-in{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.bc-in a{color:var(--ink-3);transition:color .15s}
.bc-in a:hover{color:var(--gr)}
.bc-in i.sep{font-size:8px}
.bc-in .cur{color:var(--ink);font-weight:600}

/* ══════════════════════════════════════════
   PRODUCT DETAIL LAYOUT
══════════════════════════════════════════ */
.pd-wrap{max-width:1360px;margin:0 auto;padding:0 20px 80px}

/* ── Main section: galeria + info ── */
.pd-main{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:32px;
  background:var(--sur);
  border:1px solid var(--bdr);
  border-radius:var(--r16);
  padding:28px;
  margin-bottom:24px;
}

/* ── GALERIA ── */
.pd-gallery{display:flex;flex-direction:column;gap:12px;position:sticky;top:calc(var(--hdr-h)+16px);align-self:start}
.gallery-main{
  position:relative;width:100%;aspect-ratio:1;
  border-radius:var(--r12);overflow:hidden;
  background:var(--bg);border:1px solid var(--bdr);
  cursor:zoom-in;
}
.gallery-main img{
  width:100%;height:100%;object-fit:cover;
  transition:transform .4s var(--ease);
}
.gallery-main:hover img{transform:scale(1.06)}
.gallery-zoom-hint{
  position:absolute;bottom:10px;right:10px;
  background:rgba(0,0,0,.5);color:#fff;
  font-size:10px;font-weight:600;padding:4px 8px;
  border-radius:var(--r99);display:flex;align-items:center;gap:4px;
  opacity:0;transition:opacity .2s;pointer-events:none;
}
.gallery-main:hover .gallery-zoom-hint{opacity:1}
.gallery-badge{
  position:absolute;top:12px;left:12px;
  display:flex;flex-direction:column;gap:5px;z-index:2;
}
.gbadge{display:inline-block;padding:4px 9px;border-radius:4px;font-size:10.5px;font-weight:700;text-transform:uppercase}
.gbadge-new{background:#3b82f6;color:#fff}
.gbadge-hot{background:#f59e0b;color:#fff}
.gbadge-eco{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}

.gallery-thumbs{
  display:flex;gap:8px;overflow-x:auto;
  scrollbar-width:thin;scrollbar-color:var(--bdr) transparent;
  padding-bottom:4px;
}
.gallery-thumbs::-webkit-scrollbar{height:3px}
.gallery-thumbs::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:4px}
.gthumb{
  flex-shrink:0;width:64px;height:64px;
  border-radius:var(--r8);overflow:hidden;
  border:2px solid var(--bdr);cursor:pointer;
  transition:border-color .15s,transform .15s;
  background:var(--bg);
}
.gthumb img{width:100%;height:100%;object-fit:cover}
.gthumb:hover{border-color:var(--gr);transform:scale(1.05)}
.gthumb.active{border-color:var(--gr);box-shadow:0 0 0 3px rgba(0,185,107,.2)}

/* ── LIGHTBOX ── */
.lightbox{
  position:fixed;inset:0;z-index:9000;
  background:rgba(0,0,0,.92);display:none;
  place-items:center;cursor:zoom-out;
}
.lightbox.open{display:grid;animation:fadeIn .2s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.lb-img{max-width:90vw;max-height:88vh;object-fit:contain;border-radius:var(--r10);box-shadow:0 20px 60px rgba(0,0,0,.5)}
.lb-close{position:absolute;top:16px;right:16px;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.15);color:#fff;font-size:18px;display:grid;place-items:center;transition:background .15s;cursor:pointer}
.lb-close:hover{background:rgba(255,255,255,.3)}
.lb-arrow{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.15);color:#fff;font-size:16px;display:grid;place-items:center;transition:background .15s;cursor:pointer}
.lb-arrow:hover{background:rgba(255,255,255,.3)}
.lb-prev{left:20px}.lb-next{right:20px}
.lb-counter{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.6);font-size:12px;font-weight:600}

/* ── INFO PANEL ── */
.pd-info{display:flex;flex-direction:column;gap:18px}

.pd-meta-top{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.pd-category{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:var(--gr);text-transform:uppercase;letter-spacing:.5px}
.pd-category i{font-size:10px}
.pd-actions-top{display:flex;gap:6px}
.pd-act-btn{width:34px;height:34px;border-radius:50%;border:1.5px solid var(--bdr);display:grid;place-items:center;font-size:13px;color:var(--ink-3);transition:all .15s;cursor:pointer}
.pd-act-btn:hover{border-color:var(--gr);color:var(--gr);background:var(--gr-l)}
.pd-act-btn.liked{border-color:#ef4444;color:#ef4444;background:#fef2f2}

.pd-title{font-size:1.55rem;font-weight:800;color:var(--ink);line-height:1.25;letter-spacing:-.3px}

/* Rating inline */
.pd-rating{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.stars-lg{display:flex;gap:2px}
.stars-lg i{font-size:15px;color:var(--amber)}
.stars-lg .fa-regular{color:var(--bdr)}
.pd-rating-num{font-size:15px;font-weight:700;color:var(--ink)}
.pd-rating-cnt{font-size:13px;color:var(--ink-3)}
.pd-rating-sep{width:1px;height:14px;background:var(--bdr)}
.pd-sales{font-size:13px;color:var(--ink-3);display:flex;align-items:center;gap:4px}
.pd-sales i{font-size:11px;color:var(--gr)}

/* Preço */
.pd-price-block{padding:16px;background:var(--bg);border-radius:var(--r10);border:1px solid var(--bdr)}
.pd-price-lbl{font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--ink-3);margin-bottom:4px}
.pd-price{font-size:2.2rem;font-weight:800;color:var(--gr);letter-spacing:-.5px;line-height:1;display:flex;align-items:baseline;gap:6px}
.pd-price .sym{font-size:1rem;font-weight:700;color:var(--ink-3)}
.pd-stock-pill{display:inline-flex;align-items:center;gap:5px;margin-top:8px;padding:4px 10px;border-radius:var(--r99);font-size:11.5px;font-weight:700;border:1px solid}
.pd-stock-pill.ok{background:#dcfce7;color:#166534;border-color:#bbf7d0}
.pd-stock-pill.low{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.pd-stock-pill.out{background:#f3f4f6;color:#6b7280;border-color:var(--bdr)}

/* Quantidade */
.pd-qty-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.pd-qty-lbl{font-size:12px;font-weight:700;color:var(--ink-2);text-transform:uppercase;letter-spacing:.4px;flex-shrink:0}
.qty-ctrl{display:flex;align-items:center;gap:0;border:1.5px solid var(--bdr);border-radius:var(--r8);overflow:hidden}
.qty-btn{width:36px;height:36px;display:grid;place-items:center;font-size:15px;color:var(--ink-2);transition:background .15s,color .15s;cursor:pointer}
.qty-btn:hover{background:var(--gr-l);color:var(--gr)}
.qty-input{width:48px;text-align:center;font-size:14px;font-weight:700;color:var(--ink);border:none;border-left:1.5px solid var(--bdr);border-right:1.5px solid var(--bdr);outline:none;background:var(--sur);height:36px;-moz-appearance:textfield}
.qty-input::-webkit-outer-spin-button,.qty-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}

/* CTAs */
.pd-cta{display:flex;gap:10px;flex-wrap:wrap}
.btn-addcart{
  flex:1;min-width:160px;
  display:flex;align-items:center;justify-content:center;gap:8px;
  padding:13px 20px;
  background:var(--sur);border:2px solid var(--gr);
  border-radius:var(--r10);
  font-size:14px;font-weight:700;color:var(--gr);
  transition:all .2s;cursor:pointer;font-family:var(--font);
}
.btn-addcart:hover{background:var(--gr-l)}
.btn-addcart.added{background:#dcfce7;border-color:#166534;color:#166534}
.btn-addcart:disabled{opacity:.6;cursor:not-allowed}
.btn-buynow{
  flex:1;min-width:160px;
  display:flex;align-items:center;justify-content:center;gap:8px;
  padding:13px 20px;
  background:var(--gr);border:2px solid var(--gr);
  border-radius:var(--r10);
  font-size:14px;font-weight:700;color:#fff;
  transition:background .15s,border-color .15s;cursor:pointer;
}
.btn-buynow:hover{background:var(--gr-d);border-color:var(--gr-d)}
.btn-buynow:disabled{opacity:.6;cursor:not-allowed}

/* Entrega/garantia pills */
.pd-features{display:flex;flex-wrap:wrap;gap:8px}
.pd-feat{
  display:flex;align-items:center;gap:6px;
  padding:7px 12px;
  background:var(--bg);border:1px solid var(--bdr);border-radius:var(--r8);
  font-size:12px;color:var(--ink-2);
}
.pd-feat i{color:var(--gr);font-size:12px;flex-shrink:0}

/* Fornecedor */
.pd-supplier{
  display:flex;align-items:center;gap:12px;
  padding:14px;background:var(--bg);border:1px solid var(--bdr);border-radius:var(--r10);
}
.pd-sup-avatar{
  width:44px;height:44px;flex-shrink:0;
  border-radius:var(--r8);background:var(--gr);
  display:grid;place-items:center;font-size:18px;color:#fff;font-weight:800;
}
.pd-sup-info{flex:1;min-width:0}
.pd-sup-name{font-size:13.5px;font-weight:700;color:var(--ink);margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pd-sup-meta{font-size:11.5px;color:var(--ink-3);display:flex;align-items:center;gap:6px}
.pd-sup-meta i{font-size:10px;color:var(--gr)}
.pd-sup-link{font-size:12px;font-weight:600;color:var(--gr);white-space:nowrap;transition:color .15s}
.pd-sup-link:hover{color:var(--gr-d)}

/* Eco badges */
.pd-eco-row{display:flex;flex-wrap:wrap;gap:6px}
.eco-chip{
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 10px;
  background:#d1fae5;border:1px solid #a7f3d0;border-radius:var(--r99);
  font-size:11.5px;font-weight:700;color:#065f46;
}
.eco-chip i{font-size:10px}

/* ══════════════════════════════════════════
   TABS
══════════════════════════════════════════ */
.pd-tabs-wrap{
  background:var(--sur);border:1px solid var(--bdr);
  border-radius:var(--r16);overflow:hidden;
  margin-bottom:24px;
}
.pd-tabs{
  display:flex;overflow-x:auto;
  border-bottom:1px solid var(--bdr);
  scrollbar-width:none;
}
.pd-tabs::-webkit-scrollbar{display:none}
.pd-tab{
  flex-shrink:0;padding:0 22px;height:48px;
  display:flex;align-items:center;gap:7px;
  font-size:13px;font-weight:600;color:var(--ink-3);
  border-bottom:3px solid transparent;
  cursor:pointer;transition:color .15s,border-color .15s,background .15s;
  white-space:nowrap;
}
.pd-tab:hover{color:var(--ink);background:var(--bg)}
.pd-tab.active{color:var(--gr);border-bottom-color:var(--gr);font-weight:700}
.pd-tab i{font-size:13px}
.tab-badge{background:var(--gr);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:var(--r99)}

.pd-tab-content{display:none;padding:24px}
.pd-tab-content.active{display:block;animation:fadeUp .2s var(--ease)}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* Tab: Descrição */
.pd-desc{font-size:14px;color:var(--ink-2);line-height:1.75;max-width:720px}
.pd-desc h4{font-size:1rem;font-weight:700;color:var(--ink);margin:18px 0 8px}
.pd-desc p{margin-bottom:10px}
.pd-desc ul{padding-left:20px;margin-bottom:10px}
.pd-desc ul li{margin-bottom:4px}

/* Tab: Especificações */
.spec-table{width:100%;border-collapse:collapse}
.spec-table tr:nth-child(even) td{background:var(--bg)}
.spec-table td{padding:10px 14px;font-size:13.5px;border-bottom:1px solid var(--bdr-2);vertical-align:top}
.spec-table td:first-child{font-weight:700;color:var(--ink);width:38%;white-space:nowrap}
.spec-table td:last-child{color:var(--ink-2)}

/* Tab: Avaliações */
.reviews-summary{display:grid;grid-template-columns:auto 1fr;gap:28px;align-items:center;margin-bottom:28px;padding:20px;background:var(--bg);border-radius:var(--r12)}
.rs-big{text-align:center}
.rs-num{font-size:3.5rem;font-weight:800;color:var(--ink);line-height:1}
.rs-stars{display:flex;justify-content:center;gap:2px;margin:4px 0}
.rs-stars i{font-size:16px;color:var(--amber)}
.rs-stars .fa-regular{color:var(--bdr)}
.rs-total{font-size:12px;color:var(--ink-3);font-weight:600}
.rs-bars{display:flex;flex-direction:column;gap:6px}
.rs-bar-row{display:flex;align-items:center;gap:10px;font-size:12px}
.rs-bar-lbl{width:28px;flex-shrink:0;font-weight:700;color:var(--ink-2)}
.rs-bar-track{flex:1;height:7px;background:var(--bdr);border-radius:var(--r99);overflow:hidden}
.rs-bar-fill{height:100%;background:var(--amber);border-radius:var(--r99);transition:width .6s var(--ease)}
.rs-bar-cnt{width:22px;text-align:right;color:var(--ink-3);flex-shrink:0}

.review-list{display:flex;flex-direction:column;gap:16px}
.review-card{padding:16px;background:var(--bg);border-radius:var(--r10);border:1px solid var(--bdr-2)}
.rv-head{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px}
.rv-avatar{width:38px;height:38px;border-radius:50%;background:var(--gr);display:grid;place-items:center;font-size:15px;color:#fff;font-weight:700;flex-shrink:0;overflow:hidden}
.rv-avatar img{width:100%;height:100%;object-fit:cover}
.rv-meta{flex:1;min-width:0}
.rv-name{font-size:13px;font-weight:700;color:var(--ink)}
.rv-date{font-size:11.5px;color:var(--ink-3)}
.rv-stars{display:flex;gap:1px}
.rv-stars i{font-size:12px;color:var(--amber)}
.rv-stars .fa-regular{color:var(--bdr)}
.rv-text{font-size:13.5px;color:var(--ink-2);line-height:1.6}
.no-reviews{text-align:center;padding:40px 20px;color:var(--ink-3)}
.no-reviews i{font-size:36px;display:block;margin-bottom:10px;color:var(--bdr)}

/* ══════════════════════════════════════════
   PRODUTOS SIMILARES
══════════════════════════════════════════ */
.related-wrap{margin-bottom:24px}
.section-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.section-title{font-size:1.1rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px}
.section-title i{color:var(--gr)}
.section-see-all{font-size:12.5px;font-weight:600;color:var(--gr);transition:color .15s}
.section-see-all:hover{color:var(--gr-d)}

.related-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
  gap:14px;
}
/* Mini card */
.mcard{
  background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r10);
  overflow:hidden;cursor:pointer;
  transition:box-shadow var(--mid) var(--ease),transform var(--mid) var(--ease),border-color var(--mid);
}
.mcard:hover{box-shadow:var(--sh-md);transform:translateY(-3px);border-color:rgba(0,185,107,.35)}
.mc-img{position:relative;width:100%;padding-top:100%;background:var(--bg);overflow:hidden}
.mc-img img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform var(--mid) var(--ease)}
.mcard:hover .mc-img img{transform:scale(1.06)}
.mc-body{padding:10px}
.mc-name{font-size:12.5px;font-weight:700;color:var(--ink);margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mc-price{font-size:14px;font-weight:800;color:var(--gr)}
.mc-rat{display:flex;align-items:center;gap:4px;margin-top:3px}
.mc-rat i{font-size:10px;color:var(--amber)}

/* ── Flash ── */
.flash{position:fixed;top:72px;right:16px;z-index:9999;display:flex;align-items:center;gap:10px;padding:12px 38px 12px 14px;border-radius:var(--r10);box-shadow:var(--sh-lg);border:1px solid;font-size:13.5px;max-width:360px;animation:slideIn .3s var(--ease);transition:opacity .3s}
.flash-success{background:#f0fdf4;color:#166534;border-color:#bbf7d0}
.flash-error{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.flash-close{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:18px;opacity:.5;cursor:pointer;transition:opacity .15s}
.flash-close:hover{opacity:1}
@keyframes slideIn{from{transform:translateX(420px);opacity:0}to{transform:none;opacity:1}}

/* ── Back to top ── */
.btt{position:fixed;bottom:24px;right:20px;z-index:300;width:40px;height:40px;background:var(--gr);border-radius:50%;color:#fff;font-size:16px;box-shadow:var(--sh-md);display:none;place-items:center;transition:transform var(--mid),background .15s}
.btt.vis{display:grid}
.btt:hover{background:var(--gr-d);transform:translateY(-3px)}

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */
@media(max-width:900px){
  .pd-main{grid-template-columns:1fr;gap:20px;padding:20px}
  .pd-gallery{position:static}
}
@media(max-width:600px){
  :root{--hdr-h:52px}
  .container{padding:0 12px}
  .pd-wrap{padding:0 12px 80px}
  .pd-main{padding:14px;border-radius:var(--r10)}
  .pd-title{font-size:1.2rem}
  .pd-price{font-size:1.7rem}
  .btn-addcart,.btn-buynow{min-width:0;padding:11px 14px;font-size:13px}
  .related-grid{grid-template-columns:repeat(2,1fr);gap:10px}
  .pd-tabs{gap:0}
  .pd-tab{padding:0 14px;font-size:12px}
  .logo-sub{display:none}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
</head>
<body>

<!-- ══ TOP STRIP ══ -->
<div class="top-strip">
  <div class="container">
    <div class="ts-in">
      <span class="ts-link" style="color:rgba(255,255,255,.75)"><i class="fa-solid fa-leaf"></i> Marketplace Sustentável</span>
      <ul class="ts-right">
        <li><a href="#" class="ts-link">Ajuda</a></li>
        <li><span class="ts-div"></span></li>
        <li><a href="#" class="ts-link">Rastrear Pedido</a></li>
      </ul>
    </div>
  </div>
</div>

<!-- ══ HEADER ══ -->
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
      <!-- Search simples no header da página de detalhe -->
      <form action="shopping.php" method="get" style="flex:1;max-width:560px">
        <div style="display:flex;height:38px">
          <input type="text" name="search" placeholder="Buscar produtos..." value=""
            style="flex:1;padding:0 12px;border:1.5px solid var(--bdr);border-right:none;border-radius:8px 0 0 8px;font-size:13.5px;font-family:var(--font);outline:none;background:var(--bg);color:var(--ink)">
          <button type="submit" style="width:44px;background:var(--gr);border:1.5px solid var(--gr);border-radius:0 8px 8px 0;color:#fff;font-size:15px;cursor:pointer">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </div>
      </form>
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
            <span><?= esc($user_name) ?></span>
          </a>
        <?php else: ?>
          <a href="registration/login/login.php" class="hdr-btn"><i class="fa-solid fa-circle-user"></i> Entrar</a>
          <a href="registration/register/painel_cadastro.php?tipo=business" class="hdr-btn pri"><i class="fa-solid fa-store"></i> Vender</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<!-- ══ CONTEÚDO ══ -->
<div class="pd-wrap">

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <div class="bc-in">
      <a href="index.php"><i class="fa-solid fa-house"></i></a>
      <i class="fa-solid fa-chevron-right sep"></i>
      <a href="shopping.php">Shopping</a>
      <?php if (!empty($p['category_name'])): ?>
        <i class="fa-solid fa-chevron-right sep"></i>
        <a href="shopping.php?category=<?= (int)$p['cat_id'] ?>"><?= esc($p['category_name']) ?></a>
      <?php endif; ?>
      <i class="fa-solid fa-chevron-right sep"></i>
      <span class="cur"><?= esc(mb_strimwidth($p['nome'], 0, 48, '…')) ?></span>
    </div>
  </div>

  <!-- ══ MAIN: Galeria + Info ══ -->
  <div class="pd-main">

    <!-- ── GALERIA ─────────────────────── -->
    <div class="pd-gallery">
      <div class="gallery-main" id="galleryMain" onclick="openLightbox(0)">
        <img id="mainImg" src="<?= esc($gallery[0]) ?>" alt="<?= esc($p['nome']) ?>"
             onerror="this.src='<?= fallbackImg($p['nome']) ?>'">
        <div class="gallery-badge">
          <?php
          $isNew = ($p['created_at'] ?? '') >= date('Y-m-d H:i:s', strtotime('-7 days'));
          $isHot = ((int)($p['total_sales'] ?? 0)) > 0;
          if ($isNew): ?><span class="gbadge gbadge-new">Novo</span><?php
          elseif ($isHot): ?><span class="gbadge gbadge-hot">Popular</span><?php
          endif;
          if (!empty($eco_raw) && is_array($eco_raw)):
            $el = $eco_opts[$eco_raw[0]] ?? null;
            if ($el): ?><span class="gbadge gbadge-eco"><?= esc($el) ?></span><?php endif;
          endif; ?>
        </div>
        <div class="gallery-zoom-hint"><i class="fa-solid fa-magnifying-glass-plus"></i> Ampliar</div>
      </div>

      <?php if (count($gallery) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($gallery as $i => $gi): ?>
        <div class="gthumb <?= $i===0?'active':'' ?>" data-idx="<?= $i ?>"
             onclick="switchImg(<?= $i ?>, '<?= esc($gi) ?>')">
          <img src="<?= esc($gi) ?>" alt="Imagem <?= $i+1 ?>"
               onerror="this.src='<?= fallbackImg($p['nome']) ?>'">
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── INFO ────────────────────────── -->
    <div class="pd-info">

      <!-- Categoria + acções rápidas -->
      <div class="pd-meta-top">
        <a href="shopping.php?category=<?= (int)$p['cat_id'] ?>" class="pd-category">
          <i class="fa-solid fa-<?= esc($p['category_icon']) ?>"></i>
          <?= esc($p['category_name'] ?: 'Geral') ?>
        </a>
        <div class="pd-actions-top">
          <button class="pd-act-btn" id="btnLike" onclick="toggleLike()" title="Favoritar">
            <i class="fa-regular fa-heart"></i>
          </button>
          <button class="pd-act-btn" onclick="shareProduct()" title="Partilhar">
            <i class="fa-solid fa-share-nodes"></i>
          </button>
        </div>
      </div>

      <!-- Título -->
      <h1 class="pd-title"><?= esc($p['nome']) ?></h1>

      <!-- Rating -->
      <div class="pd-rating">
        <div class="stars-lg">
          <?php for ($i=1;$i<=5;$i++): ?>
            <i class="fa-<?= $i<=$rating_int?'solid':'regular' ?> fa-star"></i>
          <?php endfor; ?>
        </div>
        <span class="pd-rating-num"><?= $avg>0?number_format($avg,1):'0.0' ?></span>
        <a href="#tab-reviews" class="pd-rating-cnt" onclick="activateTab('reviews')"><?= number_format((int)$p['review_count']) ?> avaliações</a>
        <div class="pd-rating-sep"></div>
        <div class="pd-sales"><i class="fa-solid fa-fire"></i><?= number_format((int)$p['total_sales']) ?> vendidos</div>
      </div>

      <!-- Preço -->
      <div class="pd-price-block">
        <div class="pd-price-lbl">Preço unitário</div>
        <?php
          $has_discount = !empty($p['preco_original']) && (float)$p['preco_original'] > (float)$p['preco'];
          $discount_pct = $has_discount ? round((1 - (float)$p['preco']/(float)$p['preco_original'])*100) : 0;
        ?>
        <?php if ($has_discount): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <span style="font-size:13px;color:var(--ink-3);text-decoration:line-through">
            <?= esc($cur['symbol']) ?> <?= number_format((float)$p['preco_original'] * $cur['rate'],2,',','.') ?>
          </span>
          <span style="background:var(--red);color:#fff;font-size:11px;font-weight:700;padding:2px 7px;border-radius:var(--r99)">
            -<?= $discount_pct ?>%
          </span>
        </div>
        <?php endif; ?>
        <div class="pd-price">
          <span class="sym" id="pdPriceSym"><?= esc($cur['symbol']) ?></span>
          <span id="pdPriceVal"><?= number_format($price_conv, 2, ',', '.') ?></span>
        </div>
        <!-- Subtotal — aparece quando qty > 1 -->
        <div id="pdSubtotalRow" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed var(--bdr)">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <span style="font-size:11.5px;color:var(--ink-3);font-weight:600">
              <span id="pdSubQty">1</span> unidades × <span id="pdSubUnit"><?= esc($cur['symbol']) ?> <?= number_format($price_conv,2,',','.') ?></span>
            </span>
            <div style="display:flex;flex-direction:column;align-items:flex-end">
              <span style="font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--ink-3)">Total</span>
              <span id="pdSubtotalVal" style="font-size:1.3rem;font-weight:800;color:var(--ink);letter-spacing:-.3px">
                <?= esc($cur['symbol']) ?> <?= number_format($price_conv,2,',','.') ?>
              </span>
            </div>
          </div>
        </div>
        <?php if ($in_stock): ?>
          <div class="pd-stock-pill <?= $is_low?'low':'ok' ?>" style="margin-top:10px">
            <i class="fa-solid fa-<?= $is_low?'triangle-exclamation':'circle-check' ?>"></i>
            <?= $is_low ? 'Apenas '.(int)$p['stock'].' em estoque' : 'Em estoque ('.(int)$p['stock'].' disponíveis)' ?>
          </div>
        <?php else: ?>
          <div class="pd-stock-pill out" style="margin-top:10px"><i class="fa-solid fa-xmark"></i> Fora de estoque</div>
        <?php endif; ?>
      </div>

      <!-- Quantidade -->
      <?php if ($in_stock): ?>
      <div class="pd-qty-row">
        <span class="pd-qty-lbl">Quantidade</span>
        <div class="qty-ctrl">
          <button class="qty-btn" onclick="changeQty(-1)"><i class="fa-solid fa-minus"></i></button>
          <input type="number" class="qty-input" id="qtyInput" value="1" min="1" max="<?= (int)$p['stock'] ?>">
          <button class="qty-btn" onclick="changeQty(1)"><i class="fa-solid fa-plus"></i></button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Botões CTA -->
      <div class="pd-cta">
        <?php if ($in_stock): ?>
          <button class="btn-addcart" id="btnAddCart" onclick="addToCartDetail()">
            <i class="fa-solid fa-cart-plus"></i> Adicionar ao Carrinho
          </button>
          <a href="checkout.php?buy_now=<?= $product_id ?>&qty=1" class="btn-buynow" id="btnBuyNow">
            <i class="fa-solid fa-bolt"></i> Comprar Agora
          </a>
        <?php else: ?>
          <button class="btn-addcart" disabled style="opacity:.5;cursor:not-allowed">
            <i class="fa-solid fa-cart-plus"></i> Sem estoque
          </button>
        <?php endif; ?>
      </div>

      <!-- Features -->
      <div class="pd-features">
        <div class="pd-feat"><i class="fa-solid fa-shield-halved"></i> Compra Segura</div>
        <div class="pd-feat"><i class="fa-solid fa-truck-fast"></i> Entrega Rápida</div>
        <div class="pd-feat"><i class="fa-solid fa-rotate-left"></i> Devoluções 30 dias</div>
        <div class="pd-feat"><i class="fa-solid fa-headset"></i> Suporte 24h</div>
      </div>

      <!-- Eco badges -->
      <?php if (!empty($eco_raw) && is_array($eco_raw)): ?>
      <div>
        <div style="font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--ink-3);margin-bottom:7px">Certificações</div>
        <div class="pd-eco-row">
          <?php foreach ($eco_raw as $eb): ?>
            <?php if (isset($eco_opts[$eb])): ?>
            <span class="eco-chip"><i class="fa-solid fa-leaf"></i><?= esc($eco_opts[$eb]) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Fornecedor -->
      <div class="pd-supplier">
        <div class="pd-sup-avatar">
          <?= mb_strtoupper(mb_substr($p['company_name'] ?: 'F', 0, 1)) ?>
        </div>
        <div class="pd-sup-info">
          <div class="pd-sup-name"><?= esc($p['company_name'] ?: 'Fornecedor VSG') ?></div>
          <div class="pd-sup-meta">
            <i class="fa-solid fa-store"></i> Vendedor Verificado
            <?php if ($p['company_phone']): ?>
              · <i class="fa-solid fa-phone"></i> <?= esc($p['company_phone']) ?>
            <?php endif; ?>
          </div>
        </div>
        <a href="shopping.php?supplier=<?= (int)$p['company_id'] ?>" class="pd-sup-link">
          Ver loja <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>

    </div><!-- /pd-info -->
  </div><!-- /pd-main -->

  <!-- ══ TABS ══════════════════════════ -->
  <div class="pd-tabs-wrap">
    <div class="pd-tabs" id="pdTabs">
      <div class="pd-tab active" data-tab="desc" onclick="activateTab('desc')">
        <i class="fa-solid fa-align-left"></i> Descrição
      </div>
      <div class="pd-tab" data-tab="specs" onclick="activateTab('specs')">
        <i class="fa-solid fa-list-check"></i> Especificações
      </div>
      <div class="pd-tab" id="tab-reviews" data-tab="reviews" onclick="activateTab('reviews')">
        <i class="fa-solid fa-star"></i> Avaliações
        <?php if ((int)$p['review_count'] > 0): ?>
          <span class="tab-badge"><?= (int)$p['review_count'] ?></span>
        <?php endif; ?>
      </div>
      <div class="pd-tab" data-tab="shipping" onclick="activateTab('shipping')">
        <i class="fa-solid fa-truck"></i> Entrega
      </div>
    </div>

    <!-- Tab: Descrição -->
    <div class="pd-tab-content active" id="tc-desc">
      <div class="pd-desc">
        <?php if (!empty($p['descricao'])): ?>
          <?= nl2br(esc($p['descricao'])) ?>
        <?php else: ?>
          <p style="color:var(--ink-3)">Sem descrição disponível para este produto.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tab: Especificações -->
    <div class="pd-tab-content" id="tc-specs">
      <table class="spec-table">
        <tbody>
          <tr><td>Referência</td><td>#<?= $product_id ?></td></tr>
          <tr><td>Categoria</td><td><?= esc($p['category_name'] ?: '—') ?></td></tr>
          <tr><td>Fornecedor</td><td><?= esc($p['company_name'] ?: '—') ?></td></tr>
          <tr><td>Estoque</td><td><?= (int)$p['stock'] ?> unidades</td></tr>
          <tr><td>Total vendido</td><td><?= number_format((int)$p['total_sales']) ?></td></tr>
          <tr><td>Moeda base</td><td><?= esc($p['currency'] ?: 'MZN') ?></td></tr>
          <tr><td>Adicionado em</td><td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td></tr>
          <?php if (!empty($eco_raw) && is_array($eco_raw)): ?>
          <tr><td>Certificações eco</td><td><?= esc(implode(', ', array_map(fn($e) => $eco_opts[$e] ?? $e, $eco_raw))) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($p['visualizacoes']) && (int)$p['visualizacoes'] > 0): ?>
          <tr><td>Visualizações</td><td><?= number_format((int)$p['visualizacoes']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($p['preco_original']) && (float)$p['preco_original'] > (float)$p['preco']): ?>
          <tr><td>Preço original</td><td><s><?= esc($cur['symbol']) ?> <?= number_format((float)$p['preco_original'] * $cur['rate'], 2, ',', '.') ?></s> <span style="color:var(--red);font-weight:700"><?= round((1 - (float)$p['preco']/(float)$p['preco_original'])*100) ?>% desconto</span></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Tab: Avaliações -->
    <div class="pd-tab-content" id="tc-reviews">
      <?php $total_rv = (int)$p['review_count']; ?>
      <?php if ($total_rv > 0): ?>
        <div class="reviews-summary">
          <div class="rs-big">
            <div class="rs-num"><?= number_format($avg, 1) ?></div>
            <div class="rs-stars">
              <?php for ($i=1;$i<=5;$i++): ?>
                <i class="fa-<?= $i<=$rating_int?'solid':'regular' ?> fa-star"></i>
              <?php endfor; ?>
            </div>
            <div class="rs-total"><?= $total_rv ?> avaliações</div>
          </div>
          <div class="rs-bars">
            <?php foreach ([5,4,3,2,1] as $star): ?>
            <?php $cnt = $rating_dist[$star] ?? 0; $pct = $total_rv > 0 ? round($cnt/$total_rv*100) : 0; ?>
            <div class="rs-bar-row">
              <span class="rs-bar-lbl"><?= $star ?>★</span>
              <div class="rs-bar-track">
                <div class="rs-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="rs-bar-cnt"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="review-list">
          <?php foreach ($reviews as $rv): ?>
          <div class="review-card">
            <div class="rv-head">
              <div class="rv-avatar">
                <?php if (!empty($rv['user_avatar'])): ?>
                  <img src="<?= esc($rv['user_avatar']) ?>" alt="<?= esc($rv['user_name']) ?>">
                <?php else: ?>
                  <?= mb_strtoupper(mb_substr($rv['user_name'], 0, 1)) ?>
                <?php endif; ?>
              </div>
              <div class="rv-meta">
                <div class="rv-name">
                  <?= esc($rv['user_name']) ?>
                  <?php if (!empty($rv['is_verified_purchase'])): ?>
                    <span style="font-size:10px;font-weight:700;color:#065f46;background:#dcfce7;border:1px solid #bbf7d0;padding:1px 6px;border-radius:999px;margin-left:5px">✓ Compra verificada</span>
                  <?php endif; ?>
                </div>
                <div class="rv-date"><?= date('d/m/Y', strtotime($rv['created_at'])) ?></div>
              </div>
              <div class="rv-stars">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <i class="fa-<?= $i<=(int)$rv['rating']?'solid':'regular' ?> fa-star"></i>
                <?php endfor; ?>
              </div>
            </div>
            <?php if (!empty($rv['comment'])): ?>
              <div class="rv-text"><?= esc($rv['comment']) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <div class="no-reviews">
          <i class="fa-regular fa-star"></i>
          <p style="font-weight:600;color:var(--ink);margin-bottom:5px">Sem avaliações ainda</p>
          <p>Seja o primeiro a avaliar este produto.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Tab: Entrega -->
    <div class="pd-tab-content" id="tc-shipping">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;max-width:800px">
        <?php
        $shipping_info = [
          ['icon'=>'truck-fast',      'title'=>'Entrega Padrão',  'desc'=>'3–5 dias úteis · Grátis acima de MT 2.500'],
          ['icon'=>'bolt',            'title'=>'Entrega Expressa','desc'=>'1–2 dias úteis · MT 150'],
          ['icon'=>'store',           'title'=>'Levantar na Loja','desc'=>'Disponível em Maputo, Beira e Nampula'],
          ['icon'=>'rotate-left',     'title'=>'Devolução',       'desc'=>'30 dias após recepção · sem custo'],
          ['icon'=>'shield-halved',   'title'=>'Garantia',        'desc'=>'12 meses de garantia do fabricante'],
          ['icon'=>'headset',         'title'=>'Suporte',         'desc'=>'Segunda a Sábado · 08h–20h'],
        ];
        foreach ($shipping_info as $si): ?>
        <div style="padding:16px;background:var(--bg);border:1px solid var(--bdr);border-radius:var(--r10)">
          <div style="width:38px;height:38px;background:var(--gr-l);border-radius:var(--r8);display:grid;place-items:center;margin-bottom:10px">
            <i class="fa-solid fa-<?= $si['icon'] ?>" style="color:var(--gr);font-size:16px"></i>
          </div>
          <div style="font-size:13.5px;font-weight:700;color:var(--ink);margin-bottom:4px"><?= $si['title'] ?></div>
          <div style="font-size:12.5px;color:var(--ink-3)"><?= $si['desc'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div><!-- /pd-tabs-wrap -->

  <!-- ══ PRODUTOS SIMILARES ═══════════ -->
  <?php if (!empty($related)): ?>
  <div class="related-wrap">
    <div class="section-hdr">
      <div class="section-title">
        <i class="fa-solid fa-layer-group"></i> Produtos Similares
      </div>
      <a href="shopping.php?category=<?= (int)$p['cat_id'] ?>" class="section-see-all">
        Ver todos <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>
    <div class="related-grid">
      <?php foreach ($related as $rp):
        $rimg = imgUrl($rp) ?? fallbackImg($rp['nome']);
        $rprice = (float)$rp['preco'] * $cur['rate'];
        $rrat = (int)round((float)$rp['avg_rating']);
      ?>
      <div class="mcard" onclick="location.href='product.php?id=<?= (int)$rp['id'] ?>'">
        <div class="mc-img">
          <img src="<?= esc($rimg) ?>" alt="<?= esc($rp['nome']) ?>" loading="lazy"
               onerror="this.src='<?= fallbackImg($rp['nome']) ?>'">
        </div>
        <div class="mc-body">
          <div class="mc-name" title="<?= esc($rp['nome']) ?>"><?= esc($rp['nome']) ?></div>
          <div class="mc-price"><?= esc($cur['symbol']) ?> <?= number_format($rprice,2,',','.') ?></div>
          <div class="mc-rat">
            <?php for ($i=1;$i<=5;$i++): ?>
              <i class="fa-<?= $i<=$rrat?'solid':'regular' ?> fa-star"></i>
            <?php endfor; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /pd-wrap -->

<button class="btt" id="btt" title="Voltar ao topo"><i class="fa-solid fa-arrow-up"></i></button>

<!-- ══ LIGHTBOX ══ -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lb-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
  <button class="lb-arrow lb-prev" onclick="event.stopPropagation();lbNav(-1)"><i class="fa-solid fa-chevron-left"></i></button>
  <img class="lb-img" id="lbImg" src="" alt="">
  <button class="lb-arrow lb-next" onclick="event.stopPropagation();lbNav(1)"><i class="fa-solid fa-chevron-right"></i></button>
  <div class="lb-counter" id="lbCounter"></div>
</div>

<?php include 'includes/footer.html'; ?>

<script>
(function(){
'use strict';

const GALLERY    = <?= json_encode($gallery) ?>;
const PRODUCT_ID = <?= $product_id ?>;
const MAX_QTY    = <?= (int)$p['stock'] ?>;
const CSRF       = '<?= $_SESSION['csrf_token'] ?>';

/* ── Galeria ─────────────────────────────────── */
let activeIdx = 0;
window.switchImg = function(idx, src) {
  activeIdx = idx;
  document.getElementById('mainImg').src = src;
  document.querySelectorAll('.gthumb').forEach((t,i) => t.classList.toggle('active', i===idx));
};

/* ── Lightbox ────────────────────────────────── */
let lbIdx = 0;
window.openLightbox = function(idx) {
  lbIdx = activeIdx;
  updateLb();
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
};
window.closeLightbox = function() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
};
window.lbNav = function(dir) {
  lbIdx = (lbIdx + dir + GALLERY.length) % GALLERY.length;
  updateLb();
};
function updateLb() {
  document.getElementById('lbImg').src = GALLERY[lbIdx];
  document.getElementById('lbCounter').textContent = (lbIdx+1) + ' / ' + GALLERY.length;
  if (GALLERY.length<=1) {
    document.querySelector('.lb-prev').style.display='none';
    document.querySelector('.lb-next').style.display='none';
  }
}
document.addEventListener('keydown', e => {
  const lb = document.getElementById('lightbox');
  if (!lb.classList.contains('open')) return;
  if (e.key==='Escape') closeLightbox();
  if (e.key==='ArrowLeft') lbNav(-1);
  if (e.key==='ArrowRight') lbNav(1);
});

/* ── Quantidade + Preço dinâmico + Persistência ──── */
const STORAGE_KEY = 'vsg_qty_' + PRODUCT_ID;

// Preço base em MZN vindo do PHP — fonte de verdade imutável
const UNIT_MZN  = <?= (float)$p['preco'] ?>;
const CUR_RATE  = <?= (float)$cur['rate'] ?>;
const CUR_SYM   = '<?= addslashes($cur['symbol']) ?>';
const UNIT_CONV = UNIT_MZN * CUR_RATE; // preço na moeda do utilizador

function fmtMoney(n) {
  return new Intl.NumberFormat('pt-MZ', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(n);
}

function updatePrice(qty) {
  qty = Math.max(1, Math.min(MAX_QTY || 9999, parseInt(qty) || 1));

  const priceEl   = document.getElementById('pdPriceVal');
  const subRow    = document.getElementById('pdSubtotalRow');
  const subQtyEl  = document.getElementById('pdSubQty');
  const subUntEl  = document.getElementById('pdSubUnit');
  const subTotEl  = document.getElementById('pdSubtotalVal');
  const qtyInput  = document.getElementById('qtyInput');
  const buyNowBtn = document.getElementById('btnBuyNow');

  const total = UNIT_CONV * qty;

  // Actualiza preço unitário mostrado
  if (priceEl) priceEl.textContent = fmtMoney(UNIT_CONV);

  // Actualiza input
  if (qtyInput && parseInt(qtyInput.value) !== qty) qtyInput.value = qty;

  // Subtotal — anima entrada/saída
  if (subRow) {
    if (qty > 1) {
      if (subQtyEl) subQtyEl.textContent = qty;
      if (subUntEl) subUntEl.textContent = CUR_SYM + ' ' + fmtMoney(UNIT_CONV);
      if (subTotEl) subTotEl.textContent = CUR_SYM + ' ' + fmtMoney(total);
      if (subRow.style.display === 'none' || subRow.style.display === '') {
        subRow.style.display = 'block';
        subRow.style.opacity = '0';
        subRow.style.transform = 'translateY(-4px)';
        subRow.style.transition = 'opacity .2s ease, transform .2s ease';
        requestAnimationFrame(() => {
          subRow.style.opacity = '1';
          subRow.style.transform = 'translateY(0)';
        });
      } else {
        // Já visível — pisca o total para feedback visual
        if (subTotEl) {
          subTotEl.style.transition = 'color .1s';
          subTotEl.style.color = 'var(--gr)';
          setTimeout(() => { subTotEl.style.color = 'var(--ink)'; }, 300);
        }
      }
    } else {
      subRow.style.transition = 'opacity .15s ease';
      subRow.style.opacity = '0';
      setTimeout(() => { subRow.style.display = 'none'; subRow.style.opacity = '1'; }, 150);
    }
  }

  // Botão Comprar Agora — actualiza qty no link
  if (buyNowBtn) buyNowBtn.href = 'checkout.php?buy_now=' + PRODUCT_ID + '&qty=' + qty;

  // Persiste — sessionStorage mantém ao refresh, limpa ao fechar aba
  try { sessionStorage.setItem(STORAGE_KEY, qty); } catch(_) {}
}

function applyQty(v) {
  v = Math.max(1, Math.min(MAX_QTY || 9999, parseInt(v) || 1));
  updatePrice(v);
  return v;
}

window.changeQty = function(delta) {
  const inp = document.getElementById('qtyInput');
  if (!inp) return;
  applyQty(parseInt(inp.value || 1) + delta);
};

// Actualiza enquanto digita directamente no input
document.getElementById('qtyInput')?.addEventListener('input', function() {
  const v = parseInt(this.value) || 0;
  if (v >= 1 && v <= (MAX_QTY || 9999)) updatePrice(v);
});
// Confirma e limpa ao sair do campo
document.getElementById('qtyInput')?.addEventListener('blur', function() {
  applyQty(this.value);
});

// ── Restaurar ao refresh ─────────────────────────
// Usa DOMContentLoaded para garantir que os elementos existem
document.addEventListener('DOMContentLoaded', function() {
  try {
    const saved = parseInt(sessionStorage.getItem(STORAGE_KEY)) || 1;
    applyQty(saved);
  } catch(_) {
    updatePrice(1);
  }
});

/* ── Add to Cart ─────────────────────────────── */
window.addToCartDetail = function() {
  const btn = document.getElementById('btnAddCart');
  const qty = parseInt(document.getElementById('qtyInput')?.value || 1);
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A adicionar…';

  fetch('ajax/ajax_cart.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body: 'action=add&product_id='+PRODUCT_ID+'&quantity='+qty+'&csrf_token='+CSRF
  })
  .then(r=>r.json())
  .then(data=>{
    if (data.success) {
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado ao Carrinho!';
      btn.classList.add('added');
      const badge = document.querySelector('.cart-badge');
      if (data.cart_count !== undefined) {
        if (badge) { badge.textContent = data.cart_count>99?'99+':data.cart_count; }
        else {
          const cb = document.querySelector('.cart-btn');
          if (cb) { const b = document.createElement('span'); b.className='cart-badge'; b.textContent=data.cart_count; cb.appendChild(b); }
        }
      }
      showFlash('Produto adicionado ao carrinho!', 'success');
      setTimeout(()=>{ btn.innerHTML=orig; btn.classList.remove('added'); btn.disabled=false; }, 2500);
    } else {
      btn.innerHTML = orig; btn.disabled = false;
      if (data.redirect) { location.href = data.redirect; }
      else showFlash(data.message||'Erro ao adicionar.', 'error');
    }
  })
  .catch(()=>{ btn.innerHTML=orig; btn.disabled=false; showFlash('Erro de conexão.','error'); });
};

/* ── Like / Favoritar ────────────────────────── */
let liked = false;
window.toggleLike = function() {
  liked = !liked;
  const btn = document.getElementById('btnLike');
  btn.querySelector('i').className = liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
  btn.classList.toggle('liked', liked);
};

/* ── Partilhar ───────────────────────────────── */
window.shareProduct = function() {
  if (navigator.share) {
    navigator.share({ title: document.title, url: location.href });
  } else {
    navigator.clipboard?.writeText(location.href).then(()=>showFlash('Link copiado!','success'));
  }
};

/* ── Tabs ────────────────────────────────────── */
window.activateTab = function(tab) {
  document.querySelectorAll('.pd-tab').forEach(t => t.classList.toggle('active', t.dataset.tab===tab));
  document.querySelectorAll('.pd-tab-content').forEach(tc => tc.classList.toggle('active', tc.id==='tc-'+tab));
  // Animar barras de rating na primeira abertura
  if (tab==='reviews') {
    setTimeout(()=>{
      document.querySelectorAll('.rs-bar-fill').forEach(b=>{
        b.style.width = b.style.width || '0%';
      });
    }, 50);
  }
};
// Link âncora #tab-reviews
if (location.hash === '#tab-reviews') activateTab('reviews');

/* ── Flash ───────────────────────────────────── */
function showFlash(msg, type='success') {
  document.querySelectorAll('.flash').forEach(f=>f.remove());
  const icon = type==='success'?'check-circle':type==='error'?'exclamation-circle':'info-circle';
  const el = document.createElement('div');
  el.className = 'flash flash-'+type;
  el.innerHTML = `<i class="fa-solid fa-${icon}"></i><span>${msg}</span><span class="flash-close" onclick="this.parentElement.remove()">×</span>`;
  document.body.appendChild(el);
  setTimeout(()=>{ el.style.opacity='0'; setTimeout(()=>el.remove(),350); }, 4000);
}

/* ── Back to top ─────────────────────────────── */
const btt = document.getElementById('btt');
window.addEventListener('scroll', ()=>btt.classList.toggle('vis', scrollY>400), {passive:true});
btt.addEventListener('click', ()=>window.scrollTo({top:0,behavior:'smooth'}));

})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>