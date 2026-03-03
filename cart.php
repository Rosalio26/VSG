<?php
/*
 * cart.php — VSG Marketplace
 * Carrinho estilo Amazon/Alibaba:
 *   • Visitante  → localStorage (persiste entre refreshes e sessões)
 *   • Autenticado → base de dados
 *   • Login      → merge automático localStorage → BD
 */

require_once __DIR__ . '/registration/bootstrap.php';
require_once __DIR__ . '/registration/includes/security.php';

ob_start();

function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function imgUrl($v, $name = 'P') {
    if (empty($v)) return 'https://ui-avatars.com/api/?name='.urlencode($name).'&size=200&background=00b96b&color=fff&font-size=0.1';
    if (str_starts_with($v, 'http') || str_starts_with($v, 'uploads/')) return $v;
    if (str_starts_with($v, 'products/')) return 'uploads/' . $v;
    return 'uploads/products/' . $v;
}

// ── Moeda ────────────────────────────────────────────────────────────
$currency_map = [
    'MZ' => ['symbol' => 'MT',  'rate' => 1],
    'BR' => ['symbol' => 'R$',  'rate' => 0.062],
    'PT' => ['symbol' => '€',   'rate' => 0.015],
    'US' => ['symbol' => '$',   'rate' => 0.016],
    'GB' => ['symbol' => '£',   'rate' => 0.013],
    'ZA' => ['symbol' => 'R',   'rate' => 0.29],
    'AO' => ['symbol' => 'Kz',  'rate' => 15.2],
];
$cc  = strtoupper($_SESSION['auth']['country_code'] ?? $_SESSION['user_location']['country_code'] ?? 'MZ');
$cur = $currency_map[$cc] ?? ['symbol' => 'MT', 'rate' => 1];

// ── Auth ─────────────────────────────────────────────────────────────
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_id        = $user_logged_in ? (int)$_SESSION['auth']['user_id'] : 0;
$user_name      = $_SESSION['auth']['nome']   ?? null;
$user_avatar    = $_SESSION['auth']['avatar'] ?? null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Flash ────────────────────────────────────────────────────────────
$flash      = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ── Dados do carrinho (apenas para utilizadores logados — visitantes usam JS) ──
$db_cart_items = [];
$db_subtotal   = 0;

if ($user_logged_in) {
    $st = $mysqli->prepare("
        SELECT
            ci.id        AS item_id,
            ci.quantity,
            ci.price     AS item_price,
            p.id         AS product_id,
            p.nome       AS product_name,
            p.preco      AS current_price,
            p.stock,
            p.imagem, p.image_path1,
            p.status     AS product_status,
            p.deleted_at AS product_deleted,
            COALESCE(c.name, 'Geral') AS category_name,
            COALESCE(c.icon, 'box')   AS category_icon,
            COALESCE(u.nome, '')      AS company_name
        FROM shopping_carts sc
        INNER JOIN cart_items ci ON ci.cart_id = sc.id
        INNER JOIN products   p  ON p.id = ci.product_id
        LEFT  JOIN categories c  ON c.id = p.category_id
        LEFT  JOIN users      u  ON u.id = p.user_id
        WHERE sc.user_id = ? AND sc.status = 'active'
        ORDER BY ci.created_at DESC
    ");
    $st->bind_param('i', $user_id);
    $st->execute();
    $db_cart_items = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    foreach ($db_cart_items as &$item) {
        $img = $item['image_path1'] ?: $item['imagem'];
        $item['img_url']      = imgUrl($img, $item['product_name']);
        $item['line_total']   = (float)$item['item_price'] * (int)$item['quantity'];
        $item['available']    = $item['product_status'] === 'ativo' && $item['product_deleted'] === null && (int)$item['stock'] > 0;
        $item['price_changed']= abs((float)$item['item_price'] - (float)$item['current_price']) > 0.01;
        $db_subtotal += $item['line_total'];
    }
    unset($item);
}

$sym  = $cur['symbol'];
$rate = $cur['rate'];
$fmt  = fn($v) => $sym . ' ' . number_format($v * $rate, 2, ',', '.');

$db_cart_count  = array_sum(array_column($db_cart_items, 'quantity'));
$shipping_cost  = $db_subtotal >= 2500 ? 0 : 150;
$total_mzn      = $db_subtotal + $shipping_cost;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carrinho de Compras — VSG Marketplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
<link rel="stylesheet" href="assets/style/footer.css">
<style>
:root {
  --gr:#00b96b;--gr-d:#009956;--gr-l:#e6faf2;
  --ink:#111827;--ink-2:#4b5563;--ink-3:#9ca3af;
  --bg:#f3f4f6;--sur:#ffffff;--bdr:#e5e7eb;--bdr-2:#f0f2f4;
  --red:#ef4444;--amber:#f59e0b;
  --sh-sm:0 1px 3px rgba(0,0,0,.1);--sh-md:0 4px 16px rgba(0,0,0,.1);
  --r6:6px;--r8:8px;--r10:10px;--r12:12px;--r16:16px;--r99:999px;
  --ease:cubic-bezier(.16,1,.3,1);
  --font:'Plus Jakarta Sans',-apple-system,sans-serif;
  --hdr-h:56px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);font-size:14px;line-height:1.5;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
img{display:block;max-width:100%}
button{font-family:var(--font);cursor:pointer;border:none;background:none}

.top-strip{background:var(--ink);color:rgba(255,255,255,.75);font-size:11.5px}
.ts-in{display:flex;justify-content:space-between;align-items:center;height:30px;max-width:1360px;margin:0 auto;padding:0 20px}
.ts-link{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;color:rgba(255,255,255,.75);transition:color .15s}
.ts-link:hover{color:var(--gr)}
.ts-right{display:flex;align-items:center;gap:2px;list-style:none}
.ts-div{width:1px;height:12px;background:rgba(255,255,255,.15);margin:0 2px}

.main-header{background:var(--sur);border-bottom:1px solid var(--bdr);position:sticky;top:0;z-index:500;box-shadow:0 1px 2px rgba(0,0,0,.06);height:var(--hdr-h)}
.hdr-in{display:flex;align-items:center;gap:12px;height:100%;max-width:1360px;margin:0 auto;padding:0 20px}
.logo{display:flex;align-items:center;gap:6px;flex-shrink:0;font-size:18px;font-weight:800;letter-spacing:-.5px}
.logo:hover{opacity:.8}
.logo-icon{width:32px;height:32px;background:var(--gr);border-radius:var(--r8);display:grid;place-items:center;font-size:15px;color:#fff;flex-shrink:0}
.logo-text em{color:var(--gr);font-style:normal}
.logo-sub{font-size:10px;font-weight:500;color:var(--ink-3);letter-spacing:.3px;display:block;line-height:1}
.hdr-right{margin-left:auto;display:flex;align-items:center;gap:6px}
.hdr-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:var(--r8);border:1.5px solid var(--bdr);font-size:13px;font-weight:600;color:var(--ink);transition:border-color .15s,background .15s}
.hdr-btn:hover{border-color:var(--gr);background:var(--gr-l)}
.hdr-btn.pri{background:var(--gr);border-color:var(--gr);color:#fff}
.hdr-btn.pri:hover{background:var(--gr-d)}
.hdr-btn img{width:22px;height:22px;border-radius:50%;object-fit:cover}

.breadcrumb{font-size:12px;color:var(--ink-3);max-width:1360px;margin:0 auto;padding:14px 20px 6px}
.bc-in{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.bc-in a{color:var(--ink-3);transition:color .15s}
.bc-in a:hover{color:var(--gr)}
.bc-in i.sep{font-size:8px}
.bc-in .cur{color:var(--ink);font-weight:600}

/* ── Banner visitante ── */
.guest-bar{max-width:1360px;margin:0 auto;padding:0 20px 14px}
.guest-bar-inner{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  background:linear-gradient(135deg,#eff6ff 0%,#f0fdf4 100%);
  border:1px solid #bfdbfe;border-radius:var(--r12);
  padding:12px 18px;font-size:13px;color:var(--ink-2);
}
.guest-bar-inner i.icon{font-size:20px;color:var(--gr);flex-shrink:0}
.guest-bar-text{flex:1}
.guest-bar-text strong{color:var(--ink);display:block;margin-bottom:2px}
.guest-bar-actions{display:flex;gap:8px;flex-shrink:0}
.gbtn{padding:7px 16px;border-radius:var(--r8);font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:5px;transition:all .15s}
.gbtn-login{background:var(--gr);color:#fff}
.gbtn-login:hover{background:var(--gr-d)}
.gbtn-reg{border:1.5px solid var(--bdr);color:var(--ink-2)}
.gbtn-reg:hover{border-color:var(--gr);color:var(--gr);background:var(--gr-l)}

/* ══ LAYOUT ══ */
.cart-wrap{max-width:1360px;margin:0 auto;padding:0 20px 80px;display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}

.section-title{font-size:1.2rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px;margin-bottom:16px}
.section-title i{color:var(--gr)}
.section-title .cnt{font-size:13px;font-weight:600;color:var(--ink-3);background:var(--bg);padding:2px 8px;border-radius:var(--r99);margin-left:4px}

/* ── Skeleton loader ── */
.skeleton{background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:var(--r8)}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.sk-item{height:120px;border-radius:var(--r12);margin-bottom:12px}

.cart-list{display:flex;flex-direction:column;gap:12px}

.cart-item{
  background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r12);
  padding:16px;display:flex;gap:16px;align-items:flex-start;
  transition:border-color .15s,box-shadow .15s,opacity .2s,transform .2s;
  position:relative;overflow:hidden;
}
.cart-item:hover{border-color:rgba(0,185,107,.3);box-shadow:var(--sh-sm)}
.cart-item.unavailable{opacity:.6}
.cart-item.unavailable::after{
  content:'Produto indisponível';position:absolute;top:10px;right:10px;
  background:var(--red);color:#fff;font-size:10px;font-weight:700;
  padding:3px 8px;border-radius:var(--r99);
}
.cart-item.removing{opacity:0;transform:translateX(20px)}

.ci-img{width:88px;height:88px;flex-shrink:0;border-radius:var(--r10);overflow:hidden;border:1px solid var(--bdr-2);background:var(--bg);cursor:pointer}
.ci-img img{width:100%;height:100%;object-fit:cover;transition:transform .2s}
.ci-img:hover img{transform:scale(1.06)}

.ci-info{flex:1;min-width:0}
.ci-cat{font-size:11px;font-weight:700;color:var(--gr);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;display:flex;align-items:center;gap:4px}
.ci-name{font-size:14px;font-weight:700;color:var(--ink);line-height:1.3;margin-bottom:4px;cursor:pointer;transition:color .15s}
.ci-name:hover{color:var(--gr)}
.ci-sup{font-size:12px;color:var(--ink-3);display:flex;align-items:center;gap:4px;margin-bottom:10px}
.ci-price-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}
.ci-unit-price{font-size:13px;color:var(--ink-3)}

.qty-ctrl{display:flex;align-items:center;border:1.5px solid var(--bdr);border-radius:var(--r8);overflow:hidden;height:34px}
.qty-btn{width:32px;height:100%;display:grid;place-items:center;font-size:13px;color:var(--ink-2);transition:background .15s,color .15s}
.qty-btn:hover{background:var(--gr-l);color:var(--gr)}
.qty-btn:disabled{opacity:.4;cursor:not-allowed}
.qty-val{width:40px;text-align:center;font-size:13px;font-weight:700;border:none;border-left:1.5px solid var(--bdr);border-right:1.5px solid var(--bdr);outline:none;background:var(--sur);height:100%;-moz-appearance:textfield}
.qty-val::-webkit-outer-spin-button,.qty-val::-webkit-inner-spin-button{-webkit-appearance:none}

.ci-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0}
.ci-total{font-size:1.05rem;font-weight:800;color:var(--gr);white-space:nowrap}
.ci-remove{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:var(--ink-3);font-size:13px;transition:background .15s,color .15s}
.ci-remove:hover{background:#fef2f2;color:var(--red)}

.price-alert{display:flex;align-items:center;gap:6px;padding:6px 10px;margin-top:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:var(--r6);font-size:11.5px;color:#92400e}
.price-alert i{font-size:11px;flex-shrink:0}

/* Empty */
.cart-empty{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r16);padding:64px 24px;text-align:center}
.cart-empty-icon{width:80px;height:80px;border-radius:50%;background:var(--bg);border:2px dashed var(--bdr);display:grid;place-items:center;margin:0 auto 20px;font-size:32px;color:var(--ink-3)}
.cart-empty h2{font-size:1.2rem;font-weight:800;margin-bottom:8px}
.cart-empty p{font-size:13px;color:var(--ink-3);margin-bottom:24px}
.btn-shop{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;background:var(--gr);border-radius:var(--r10);color:#fff;font-weight:700;font-size:14px;transition:background .15s}
.btn-shop:hover{background:var(--gr-d)}

/* ── Resumo ── */
.cart-summary{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r16);padding:20px;position:sticky;top:calc(var(--hdr-h)+16px)}
.summary-title{font-size:15px;font-weight:800;color:var(--ink);margin-bottom:18px;display:flex;align-items:center;gap:8px}
.summary-title i{color:var(--gr)}
.sum-row{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;padding:5px 0}
.sum-row.total{border-top:2px solid var(--bdr);margin-top:10px;padding-top:14px}
.sum-row.total .sum-lbl{font-size:15px;font-weight:800}
.sum-row.total .sum-val{font-size:1.3rem;font-weight:800;color:var(--gr)}
.sum-lbl{color:var(--ink-2)}
.sum-val{font-weight:700;color:var(--ink)}
.sum-free{color:var(--gr);font-weight:700}
.sum-shipping-note{font-size:11.5px;color:var(--ink-3);margin-top:5px;display:flex;align-items:center;gap:4px}
.sum-shipping-note i{color:var(--gr);font-size:11px}

.btn-checkout{
  width:100%;padding:14px;margin-top:18px;
  background:var(--gr);border-radius:var(--r10);
  color:#fff;font-size:15px;font-weight:800;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:background .15s,transform .1s;border:none;cursor:pointer;
}
.btn-checkout:hover{background:var(--gr-d);transform:translateY(-1px)}
.btn-checkout:disabled{opacity:.5;cursor:not-allowed;transform:none}

.btn-continue{
  width:100%;padding:10px;margin-top:8px;
  border:1.5px solid var(--bdr);border-radius:var(--r10);
  color:var(--ink-2);font-size:13px;font-weight:600;
  display:flex;align-items:center;justify-content:center;gap:7px;
  transition:border-color .15s,color .15s,background .15s;
}
.btn-continue:hover{border-color:var(--gr);color:var(--gr);background:var(--gr-l)}

.trust-pills{display:flex;flex-direction:column;gap:7px;margin-top:18px;padding-top:18px;border-top:1px solid var(--bdr-2)}
.trust-pill{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--ink-3)}
.trust-pill i{color:var(--gr);font-size:12px;width:14px}

/* Flash */
.flash{position:fixed;top:72px;right:16px;z-index:9999;display:flex;align-items:center;gap:10px;padding:12px 38px 12px 14px;border-radius:var(--r10);box-shadow:var(--sh-md);border:1px solid;font-size:13.5px;max-width:360px;animation:slideIn .3s var(--ease);transition:opacity .3s}
.flash-success{background:#f0fdf4;color:#166534;border-color:#bbf7d0}
.flash-error{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.flash-info{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
.flash-close{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:18px;opacity:.5;cursor:pointer}
.flash-close:hover{opacity:1}
@keyframes slideIn{from{transform:translateX(420px);opacity:0}to{transform:none;opacity:1}}
@keyframes rotate{to{transform:rotate(360deg)}}
.spin{animation:rotate .7s linear infinite;display:inline-block}

@media(max-width:900px){.cart-wrap{grid-template-columns:1fr}.cart-summary{position:static}}
@media(max-width:600px){.cart-wrap{padding:0 12px 80px}.ci-img{width:68px;height:68px}.logo-sub{display:none}}
</style>
</head>
<body>

<div class="top-strip">
  <div class="ts-in">
    <span class="ts-link"><i class="fa-solid fa-leaf"></i> Marketplace Sustentável</span>
    <ul class="ts-right">
      <li><a href="#" class="ts-link">Ajuda</a></li>
      <li><span class="ts-div"></span></li>
      <li><a href="#" class="ts-link">Rastrear Pedido</a></li>
    </ul>
  </div>
</div>

<header class="main-header">
  <div class="hdr-in">
    <a href="index.php" class="logo">
      <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
      <div>
        <div class="logo-text">VSG<em>•</em>MARKET</div>
        <span class="logo-sub">MARKETPLACE</span>
      </div>
    </a>
    <div class="hdr-right">
      <?php if ($user_logged_in): ?>
        <a href="pages/person/index.php" class="hdr-btn">
          <?php if ($user_avatar): ?>
            <img src="<?= esc($user_avatar) ?>" alt="">
          <?php else: ?>
            <i class="fa-solid fa-circle-user"></i>
          <?php endif; ?>
          <span><?= esc($user_name) ?></span>
        </a>
      <?php else: ?>
        <a href="registration/login/login.php?redirect=cart.php" class="hdr-btn">
          <i class="fa-solid fa-circle-user"></i> Entrar
        </a>
        <a href="registration/register/register.php" class="hdr-btn pri">
          <i class="fa-solid fa-user-plus"></i> Criar conta
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="breadcrumb">
  <div class="bc-in">
    <a href="index.php"><i class="fa-solid fa-house"></i></a>
    <i class="fa-solid fa-chevron-right sep"></i>
    <a href="shopping.php">Shopping</a>
    <i class="fa-solid fa-chevron-right sep"></i>
    <span class="cur">Carrinho</span>
  </div>
</div>

<?php if (!$user_logged_in): ?>
<!-- Banner visitante — só aparece se JS detetar itens no carrinho -->
<div class="guest-bar" id="guestBar" style="display:none">
  <div class="guest-bar-inner">
    <i class="fa-solid fa-bag-shopping icon"></i>
    <div class="guest-bar-text">
      <strong>O seu carrinho está guardado neste dispositivo</strong>
      Inicie sessão para guardar permanentemente e finalizar a compra em qualquer dispositivo.
    </div>
    <div class="guest-bar-actions">
      <a href="registration/login/login.php?redirect=cart.php" class="gbtn gbtn-login">
        <i class="fa-solid fa-right-to-bracket"></i> Entrar
      </a>
      <a href="registration/register/register.php" class="gbtn gbtn-reg">
        Criar conta
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($flash): ?>
<div class="flash flash-<?= esc($flash_type) ?>" id="flashMsg">
  <i class="fa-solid fa-<?= $flash_type==='success'?'check-circle':'exclamation-circle' ?>"></i>
  <span><?= esc($flash) ?></span>
  <span class="flash-close" onclick="this.parentElement.remove()">×</span>
</div>
<?php endif; ?>

<!-- ══ CONTEÚDO PRINCIPAL ══ -->
<div class="cart-wrap">

  <div>
    <div class="section-title">
      <i class="fa-solid fa-cart-shopping"></i>
      Carrinho
      <span class="cnt" id="cartCountLabel">—</span>
    </div>

    <!-- Container renderizado pelo JS -->
    <div id="cartContainer">
      <!-- Skeletons enquanto carrega -->
      <div id="cartSkeleton">
        <div class="skeleton sk-item"></div>
        <div class="skeleton sk-item"></div>
        <div class="skeleton sk-item"></div>
      </div>
    </div>

    <!-- Acções (mostradas pelo JS quando há itens) -->
    <div id="cartActions" style="display:none;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:10px">
      <a href="shopping.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--ink-3);transition:color .15s">
        <i class="fa-solid fa-arrow-left"></i> Continuar a comprar
      </a>
      <button onclick="Cart.clear()"
        style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--red);padding:7px 14px;border:1.5px solid #fecaca;border-radius:var(--r8)">
        <i class="fa-solid fa-trash"></i> Esvaziar carrinho
      </button>
    </div>
  </div>

  <!-- Resumo -->
  <div>
    <div class="cart-summary">
      <div class="summary-title"><i class="fa-solid fa-receipt"></i> Resumo do Pedido</div>

      <div class="sum-row">
        <span class="sum-lbl">Subtotal</span>
        <span class="sum-val" id="sumSubtotal">—</span>
      </div>
      <div class="sum-row">
        <span class="sum-lbl">Entrega</span>
        <span class="sum-val" id="sumShipping">—</span>
      </div>
      <div class="sum-shipping-note" id="sumShippingNote" style="display:none">
        <i class="fa-solid fa-truck-fast"></i>
        <span id="sumShippingNoteText"></span>
      </div>

      <div class="sum-row total">
        <span class="sum-lbl">Total</span>
        <span class="sum-val" id="sumTotal">—</span>
      </div>

      <button class="btn-checkout" id="btnCheckout" onclick="Cart.checkout()" disabled>
        <i class="fa-solid fa-lock"></i> Finalizar Compra
      </button>

      <a href="shopping.php" class="btn-continue">
        <i class="fa-solid fa-arrow-left"></i> Continuar a comprar
      </a>

      <div class="trust-pills">
        <div class="trust-pill"><i class="fa-solid fa-shield-halved"></i> Pagamento 100% seguro</div>
        <div class="trust-pill"><i class="fa-solid fa-rotate-left"></i> Devolução em 30 dias</div>
        <div class="trust-pill"><i class="fa-solid fa-headset"></i> Suporte 24h</div>
      </div>
    </div>
  </div>

</div>

<?php include 'includes/footer.html'; ?>

<script>
/* ════════════════════════════════════════════════════════
   VSG CART ENGINE
   • Visitante  → localStorage  (persiste entre sessões)
   • Autenticado → base de dados via AJAX
   ════════════════════════════════════════════════════════ */
(function(){
'use strict';

/* ── Configuração ── */
const IS_LOGGED = <?= $user_logged_in ? 'true' : 'false' ?>;
const CSRF      = <?= json_encode($_SESSION['csrf_token']) ?>;
const SYM       = <?= json_encode($cur['symbol']) ?>;
const RATE      = <?= (float)$cur['rate'] ?>;
const LS_KEY    = 'vsg_cart_v2';   // chave do localStorage
const CHECKOUT  = 'checkout.php';
const LOGIN_URL = 'registration/login/login.php?redirect=cart.php';

/* ── Dados BD (apenas logados) ── */
const DB_ITEMS = <?= json_encode($user_logged_in ? $db_cart_items : []) ?>;

/* ══════════════════════════════════════
   UTILITÁRIOS
══════════════════════════════════════ */
function fmt(mzn) {
  return SYM + ' ' + new Intl.NumberFormat('pt-MZ',{minimumFractionDigits:2,maximumFractionDigits:2}).format(mzn * RATE);
}

function flash(msg, type='success') {
  document.querySelectorAll('.flash').forEach(f=>f.remove());
  const icons={success:'check-circle',error:'exclamation-circle',info:'info-circle'};
  const el=document.createElement('div');
  el.className='flash flash-'+type;
  el.innerHTML=`<i class="fa-solid fa-${icons[type]||'info-circle'}"></i><span>${msg}</span><span class="flash-close" onclick="this.parentElement.remove()">×</span>`;
  document.body.appendChild(el);
  setTimeout(()=>{el.style.opacity='0';setTimeout(()=>el.remove(),350);},4500);
}

function esc(s){ return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function updateGlobalBadge(n){
  document.querySelectorAll('.cart-badge,[data-cart-count]').forEach(b=>{b.textContent=n>99?'99+':n;});
}

/* ══════════════════════════════════════
   LOCALSTORAGE CART (visitantes)
   Estrutura: { [product_id]: { qty, name, price, img, category, company, stock, available } }
══════════════════════════════════════ */
const LS = {
  get() {
    try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}'); }
    catch(e){ return {}; }
  },
  set(data) {
    try { localStorage.setItem(LS_KEY, JSON.stringify(data)); } catch(e){}
  },
  count() {
    const d=this.get(); return Object.values(d).reduce((a,i)=>a+(i.qty||0),0);
  },
  add(pid, qty, meta) {
    const d=this.get();
    const cur=d[pid]||{qty:0};
    const newQty=Math.min((cur.qty||0)+qty, meta.stock||99);
    d[pid]={ ...meta, qty:newQty };
    this.set(d);
    return d;
  },
  update(pid, qty) {
    const d=this.get();
    if(!d[pid]) return d;
    if(qty<=0){ delete d[pid]; } else { d[pid].qty=Math.min(qty,d[pid].stock||99); }
    this.set(d);
    return d;
  },
  remove(pid) {
    const d=this.get(); delete d[pid]; this.set(d); return d;
  },
  clear() { this.set({}); }
};

/* ══════════════════════════════════════
   AJAX (utilizadores logados)
══════════════════════════════════════ */
function ajax(body) {
  return fetch('ajax/ajax_cart.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body: body + '&csrf_token=' + CSRF
  }).then(r=>r.json());
}

/* ══════════════════════════════════════
   RENDER — constrói os cards do carrinho
══════════════════════════════════════ */
function buildItemHTML(item) {
  const pid       = item.product_id;
  const itemId    = item.item_id || ('g_'+pid);
  const qty       = parseInt(item.quantity||item.qty||1);
  const price     = parseFloat(item.item_price||item.price||0);
  const stock     = parseInt(item.stock||99);
  const available = item.available !== false;
  const lineTotal = price * qty;
  const img       = item.img_url||item.img||`https://ui-avatars.com/api/?name=${encodeURIComponent(item.product_name||'P')}&size=200&background=00b96b&color=fff`;
  const name      = esc(item.product_name||item.name||'');
  const category  = esc(item.category_name||item.category||'Geral');
  const catIcon   = esc(item.category_icon||item.icon||'box');
  const company   = esc(item.company_name||item.company||'Fornecedor');
  const priceChanged = item.price_changed && item.current_price && Math.abs(price - parseFloat(item.current_price)) > 0.01;

  return `
  <div class="cart-item${available?'':' unavailable'}" id="item-${esc(itemId)}" data-pid="${pid}" data-price="${price}" data-stock="${stock}">
    <div class="ci-img" onclick="location.href='product.php?id=${pid}'">
      <img src="${esc(img)}" alt="${name}" onerror="this.src='https://ui-avatars.com/api/?name=P&size=200&background=00b96b&color=fff'">
    </div>
    <div class="ci-info">
      <div class="ci-cat"><i class="fa-solid fa-${catIcon}"></i> ${category}</div>
      <div class="ci-name" onclick="location.href='product.php?id=${pid}'">${name}</div>
      <div class="ci-sup"><i class="fa-solid fa-building"></i> ${company}</div>
      ${priceChanged ? `<div class="price-alert"><i class="fa-solid fa-triangle-exclamation"></i> Preço actualizado para <strong>${fmt(parseFloat(item.current_price))}</strong></div>` : ''}
      <div class="ci-price-row">
        <div class="qty-ctrl">
          <button class="qty-btn" onclick="Cart.updateItem('${esc(itemId)}',${qty-1},${pid})"><i class="fa-solid fa-minus"></i></button>
          <input type="number" class="qty-val" value="${qty}" min="1" max="${stock}"
            onchange="Cart.updateItem('${esc(itemId)}',this.value,${pid})">
          <button class="qty-btn" onclick="Cart.updateItem('${esc(itemId)}',${qty+1},${pid})" ${qty>=stock?'disabled':''}>
            <i class="fa-solid fa-plus"></i>
          </button>
        </div>
        <span class="ci-unit-price">${fmt(price)} / un.</span>
      </div>
    </div>
    <div class="ci-right">
      <div class="ci-total" id="total-${esc(itemId)}">${fmt(lineTotal)}</div>
      <button class="ci-remove" onclick="Cart.removeItem('${esc(itemId)}',${pid})" title="Remover">
        <i class="fa-solid fa-trash-can"></i>
      </button>
    </div>
  </div>`;
}

function renderSummary(items) {
  let subtotal = 0;
  items.forEach(item => {
    const price = parseFloat(item.item_price||item.price||0);
    const qty   = parseInt(item.quantity||item.qty||1);
    subtotal += price * qty;
  });
  const shipping = subtotal >= 2500 ? 0 : (subtotal > 0 ? 150 : 0);
  const total    = subtotal + shipping;

  document.getElementById('sumSubtotal').textContent = items.length ? fmt(subtotal) : '—';
  const shipEl = document.getElementById('sumShipping');
  const noteEl = document.getElementById('sumShippingNote');
  const noteTxt= document.getElementById('sumShippingNoteText');
  if(items.length) {
    shipEl.textContent = shipping === 0 ? 'Grátis' : fmt(shipping);
    shipEl.className   = 'sum-val' + (shipping===0?' sum-free':'');
    if(shipping>0 && noteTxt){ noteTxt.textContent='Falta '+fmt(2500-subtotal)+' para entrega grátis'; noteEl.style.display='flex'; }
    else if(noteEl){ noteEl.style.display='none'; }
  } else {
    shipEl.textContent='—'; if(noteEl) noteEl.style.display='none';
  }
  document.getElementById('sumTotal').textContent = items.length ? fmt(total) : '—';

  const btn = document.getElementById('btnCheckout');
  if(btn) btn.disabled = items.length === 0;
}

function renderCart(items) {
  const container = document.getElementById('cartContainer');
  const skeleton  = document.getElementById('cartSkeleton');
  const actions   = document.getElementById('cartActions');
  const label     = document.getElementById('cartCountLabel');
  const guestBar  = document.getElementById('guestBar');

  if(skeleton) skeleton.remove();

  const total = items.reduce((a,i)=>a+(parseInt(i.quantity||i.qty||1)),0);
  if(label) label.textContent = total + (total===1?' item':' itens');
  updateGlobalBadge(total);

  if(items.length === 0) {
    container.innerHTML = `
      <div class="cart-empty">
        <div class="cart-empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
        <h2>O seu carrinho está vazio</h2>
        <p>Explore o marketplace e encontre produtos incríveis para adicionar.</p>
        <a href="shopping.php" class="btn-shop"><i class="fa-solid fa-border-all"></i> Explorar Produtos</a>
      </div>`;
    if(actions) actions.style.display='none';
    if(guestBar) guestBar.style.display='none';
  } else {
    const list = document.createElement('div');
    list.className='cart-list';
    list.id='cartList';
    items.forEach(item => { list.innerHTML += buildItemHTML(item); });
    container.innerHTML='';
    container.appendChild(list);
    if(actions) actions.style.display='flex';
    if(guestBar && !IS_LOGGED) guestBar.style.display='block';
  }

  renderSummary(items);
}

/* ══════════════════════════════════════
   CART API PÚBLICA
══════════════════════════════════════ */
window.Cart = {

  /* Carregar carrinho na página */
  load() {
    if(IS_LOGGED) {
      // Dados já vêm do PHP
      renderCart(DB_ITEMS);
      // Verifica se há itens no localStorage para fazer merge
      this._mergeGuestOnLogin();
    } else {
      // Visitante: lê localStorage e busca dados actualizados da BD
      const stored = LS.get();
      const pids   = Object.keys(stored).map(Number).filter(Boolean);
      if(pids.length === 0) { renderCart([]); return; }

      // Buscar dados actuais dos produtos (preços, stock)
      fetch('ajax/ajax_cart_guest.php?action=resolve&pids='+pids.join(','), {
        headers:{'X-Requested-With':'XMLHttpRequest'}
      })
      .then(r=>r.json())
      .then(d => {
        if(!d.success) { renderCart([]); return; }
        // Fundir com quantidades guardadas
        const items = d.products.map(p => ({
          ...p,
          quantity: stored[p.product_id]?.qty || 1,
          item_price: p.preco,
          img_url: p.img_url,
        }));
        renderCart(items);
      })
      .catch(()=>{ renderCart([]); });
    }
  },

  /* Actualizar quantidade */
  updateItem(itemId, qty, pid) {
    qty = parseInt(qty);
    if(isNaN(qty)) return;
    if(qty <= 0) { this.removeItem(itemId, pid); return; }

    const itemEl  = document.getElementById('item-'+itemId);
    const totalEl = document.getElementById('total-'+itemId);
    const qtyInp  = itemEl?.querySelector('.qty-val');
    const stock   = parseInt(itemEl?.dataset.stock)||99;
    const price   = parseFloat(itemEl?.dataset.price)||0;

    if(qty > stock) { qty = stock; flash('Stock máximo: '+stock,'info'); }
    if(qtyInp) qtyInp.value = qty;
    if(totalEl) totalEl.textContent = fmt(price*qty);

    // Recalc resumo live
    this._recalcSummary();

    if(IS_LOGGED) {
      clearTimeout(this._t);
      this._t = setTimeout(()=>{
        ajax('action=update&item_id='+itemId+'&quantity='+qty)
          .then(d=>{ if(!d.success) flash(d.message||'Erro.','error'); })
          .catch(()=>flash('Erro de conexão.','error'));
      }, 500);
    } else {
      LS.update(pid, qty);
    }
  },

  /* Remover item */
  removeItem(itemId, pid) {
    const el = document.getElementById('item-'+itemId);
    if(!el) return;
    el.classList.add('removing');

    const done = () => {
      el.remove();
      this._recalcSummary();
      const remaining = document.querySelectorAll('.cart-item');
      if(remaining.length === 0) { renderCart([]); }
      const total = Array.from(document.querySelectorAll('.cart-item'))
        .reduce((a,i)=>a+(parseInt(i.querySelector('.qty-val')?.value||0)),0);
      updateGlobalBadge(total);
      document.getElementById('cartCountLabel').textContent = total+(total===1?' item':' itens');
    };

    if(IS_LOGGED) {
      ajax('action=remove&item_id='+itemId).then(done).catch(()=>{
        el.classList.remove('removing');
        flash('Erro ao remover.','error');
      });
    } else {
      LS.remove(pid);
      setTimeout(done, 220);
    }
  },

  /* Esvaziar */
  clear() {
    if(!confirm('Esvaziar o carrinho completo?')) return;
    if(IS_LOGGED) {
      ajax('action=clear').then(()=>renderCart([]));
    } else {
      LS.clear();
      renderCart([]);
    }
  },

  /* Checkout */
  checkout() {
    if(!IS_LOGGED) {
      // Guardar intenção e redirecionar para login
      sessionStorage.setItem('checkout_intent','1');
      location.href = 'registration/login/login.php?redirect=checkout.php';
    } else {
      location.href = CHECKOUT;
    }
  },

  /* Recalcular resumo a partir dos itens actuais no DOM */
  _recalcSummary() {
    const items = [];
    document.querySelectorAll('.cart-item').forEach(el=>{
      items.push({
        item_price: parseFloat(el.dataset.price)||0,
        quantity:   parseInt(el.querySelector('.qty-val')?.value||0)
      });
    });
    renderSummary(items);
  },

  /* Merge localStorage → BD após login */
  _mergeGuestOnLogin() {
    if(!IS_LOGGED) return;
    const stored = LS.get();
    const pids   = Object.keys(stored).map(Number).filter(Boolean);
    if(pids.length === 0) return;

    // Enviar para BD em background
    const body = pids.map(pid=>`items[${pid}]=${stored[pid].qty||1}`).join('&');
    fetch('ajax/ajax_cart_guest.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
      body: 'action=merge&csrf_token='+CSRF+'&'+body
    })
    .then(r=>r.json())
    .then(d=>{
      if(d.success) {
        LS.clear();   // limpa localStorage após merge
        if(d.merged > 0) {
          flash(d.merged+' produto(s) do seu carrinho anterior foram adicionados.','info');
          location.reload();  // recarrega para mostrar itens da BD
        }
      }
    })
    .catch(()=>{});  // silencioso
  }
};

/* ── Iniciar ── */
Cart.load();

/* ── Flash auto-hide ── */
const fe = document.getElementById('flashMsg');
if(fe) setTimeout(()=>{fe.style.opacity='0';setTimeout(()=>fe.remove(),350);},5000);

})();

/* ══════════════════════════════════════
   API GLOBAL para outras páginas (shopping.php, product.php, etc.)
   Uso: CartGlobal.add(productId, qty, metaObj)
══════════════════════════════════════ */
window.CartGlobal = {
  _LS_KEY: 'vsg_cart_v2',
  _IS_LOGGED: <?= $user_logged_in ? 'true' : 'false' ?>,
  _CSRF: <?= json_encode($_SESSION['csrf_token']) ?>,

  add(pid, qty, meta) {
    if(this._IS_LOGGED) {
      return fetch('ajax/ajax_cart.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body:`action=add&product_id=${pid}&quantity=${qty||1}&csrf_token=${this._CSRF}`
      }).then(r=>r.json());
    } else {
      // Visitante → localStorage
      try {
        const d = JSON.parse(localStorage.getItem(this._LS_KEY)||'{}');
        const cur = d[pid]||{qty:0};
        d[pid] = { ...meta, qty: Math.min((cur.qty||0)+(qty||1), meta?.stock||99) };
        localStorage.setItem(this._LS_KEY, JSON.stringify(d));
        const total = Object.values(d).reduce((a,i)=>a+(i.qty||0),0);
        document.querySelectorAll('.cart-badge,[data-cart-count]').forEach(b=>{b.textContent=total>99?'99+':total;});
      } catch(e){}
      return Promise.resolve({success:true});
    }
  }
};
</script>
</body>
</html>
<?php ob_end_flush(); ?>