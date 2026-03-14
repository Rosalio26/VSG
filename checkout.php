<?php
/*
 * checkout.php — VSG Marketplace  v6.0
 * Design: Light/clean, payment-tiles-first, brand colours per method.
 *
 * REGRAS DE ACESSO:
 *   Visitantes acedem livremente (buy_now). Login só ao confirmar pedido.
 *   Carrinho requer login. Empresas redirecionadas para dashboard_business.
 */

require_once __DIR__ . '/registration/includes/device.php';
require_once __DIR__ . '/registration/includes/rate_limit.php';
require_once __DIR__ . '/registration/includes/errors.php';
require_once __DIR__ . '/registration/includes/db.php';
require_once __DIR__ . '/includes/currency/currency_bootstrap.php';

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

/* ── Restrição: empresas não acedem ao checkout ── */
require_once __DIR__ . '/includes/company_guard.php';

function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function imgUrl($v, $n = 'P') {
    if (empty($v)) return 'https://ui-avatars.com/api/?name='.urlencode($n).'&size=200&background=00b96b&color=fff&font-size=0.1';
    if (str_starts_with($v,'http') || str_starts_with($v,'uploads/')) return $v;
    if (str_starts_with($v,'products/')) return 'uploads/'.$v;
    return 'uploads/products/'.$v;
}

/* ── Moeda ────────────────────────────────────────────────────────── */
$currency_map = [
    'MZ'=>['symbol'=>'MT','rate'=>1],    'BR'=>['symbol'=>'R$','rate'=>0.062],
    'PT'=>['symbol'=>'€','rate'=>0.015], 'US'=>['symbol'=>'$','rate'=>0.016],
    'GB'=>['symbol'=>'£','rate'=>0.013], 'ZA'=>['symbol'=>'R','rate'=>0.29],
    'AO'=>['symbol'=>'Kz','rate'=>15.2],
];
$cc  = strtoupper($_SESSION['auth']['country_code'] ?? 'MZ');
$cur = $currency_map[$cc] ?? ['symbol'=>'MT','rate'=>1];
$sym = $cur['symbol']; $rate = $cur['rate'];
$fmt = fn($v) => $sym.' '.number_format($v * $rate, 2, ',', '.');

/* ── Auth ─────────────────────────────────────────────────────────── */
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_id        = $user_logged_in ? (int)$_SESSION['auth']['user_id'] : 0;
$user_name      = $user_logged_in ? ($_SESSION['auth']['nome']     ?? 'Utilizador') : '';
$user_avatar    = $user_logged_in ? ($_SESSION['auth']['avatar']   ?? null) : null;
$user_phone     = $user_logged_in ? ($_SESSION['auth']['telefone'] ?? '') : '';

/* ── Parâmetros ───────────────────────────────────────────────────── */
$buy_now_id  = (int)($_GET['buy_now'] ?? 0);
$buy_now_qty = max(1, (int)($_GET['qty'] ?? 1));

/*
 * RETURN_URL — caminho absoluto de domínio (começa com /).
 *
 * Usamos caminho absoluto em vez de relativo porque o login.process.php
 * está em registration/login/ e o header("Location: caminho_relativo")
 * resolveria a partir desse directório, não da raiz.
 *
 * Com caminho absoluto (/checkout.php?...) o browser resolve sempre
 * a partir da raiz do domínio, independentemente de onde está o script
 * que emitiu o redirect.
 *
 * Cálculo: SCRIPT_NAME do checkout.php é /checkout.php (ou /pasta/checkout.php)
 * Usamos dirname para obter a pasta raiz do projecto.
 */
$_base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$return_url = $_base_path . '/checkout.php' . ($_GET ? '?' . http_build_query($_GET) : '');

/* ── Carregar itens ───────────────────────────────────────────────── */
$checkout_items = []; $subtotal = 0;

if ($buy_now_id > 0) {
    $st = $mysqli->prepare("
        SELECT p.id AS product_id, p.nome AS product_name, p.preco AS item_price,
               p.currency, p.stock, p.imagem, p.image_path1, p.user_id AS company_id,
               COALESCE(c.name,'Geral') AS category_name,
               COALESCE(u.nome,'') AS company_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.id = ? AND p.status = 'ativo' AND p.deleted_at IS NULL LIMIT 1
    ");
    $st->bind_param('i', $buy_now_id); $st->execute();
    $prod = $st->get_result()->fetch_assoc(); $st->close();
    if (!$prod) { header('Location: shopping.php'); exit; }
    if ($buy_now_qty > $prod['stock']) $buy_now_qty = $prod['stock'];
    $img = imgUrl($prod['image_path1'] ?: $prod['imagem'], $prod['product_name']);
    $checkout_items[] = [
        'item_id'=>0, 'product_id'=>$prod['product_id'], 'product_name'=>$prod['product_name'],
        'company_id'=>$prod['company_id'], 'company_name'=>$prod['company_name'],
        'category_name'=>$prod['category_name'], 'item_price'=>$prod['item_price'],
        'quantity'=>$buy_now_qty, 'stock'=>$prod['stock'], 'img_url'=>$img,
        'line_total'=>(float)$prod['item_price'] * $buy_now_qty, 'currency'=>$prod['currency'],
    ];
    $subtotal = (float)$prod['item_price'] * $buy_now_qty;
} elseif ($user_logged_in) {
    $st = $mysqli->prepare("
        SELECT ci.id AS item_id, ci.quantity, ci.price AS item_price,
               ci.currency AS item_currency, ci.company_id,
               p.id AS product_id, p.nome AS product_name, p.stock,
               p.imagem, p.image_path1,
               COALESCE(c.name,'Geral') AS category_name,
               COALESCE(u.nome,'') AS company_name
        FROM shopping_carts sc
        INNER JOIN cart_items ci ON ci.cart_id = sc.id
        INNER JOIN products p ON p.id = ci.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN users u ON u.id = ci.company_id
        WHERE sc.user_id = ? AND sc.status = 'active'
        ORDER BY ci.created_at DESC
    ");
    $st->bind_param('i', $user_id); $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    if (empty($rows)) { header('Location: cart.php'); exit; }
    foreach ($rows as $row) {
        $img  = imgUrl($row['image_path1'] ?: $row['imagem'], $row['product_name']);
        $line = (float)$row['item_price'] * (int)$row['quantity'];
        $checkout_items[] = array_merge($row, ['img_url'=>$img, 'line_total'=>$line]);
        $subtotal += $line;
    }
} else {
    header('Location: shopping.php'); exit;
}

$shipping_cost = $subtotal >= 2500 ? 0 : 150;
$total_mzn     = $subtotal + $shipping_cost;

/* ── Dados pré-preenchimento ──────────────────────────────────────── */
$user_data = [];
if ($user_logged_in) {
    $st = $mysqli->prepare("SELECT nome,telefone,email,address,city,state FROM users WHERE id=? LIMIT 1");
    $st->bind_param('i', $user_id); $st->execute();
    $user_data = $st->get_result()->fetch_assoc() ?? []; $st->close();
}
$pfn = preg_replace('/\D/', '', $user_data['telefone'] ?? $user_phone ?? '');
if (strlen($pfn) === 9) $pfn = '258'.$pfn;
$phone_suffix = strlen($pfn) > 3 ? substr($pfn, 3) : '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Finalizar Compra — VSG Marketplace</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
<style>
/* ═══════════════════════════════════════════════════════════════
   VSG CHECKOUT v6.0 — Light/Clean, Payment-Tiles-First
   Fonts: Plus Jakarta Sans (display) + JetBrains Mono (numbers)
   Conceito: tiles grandes por método, cor de marca em destaque,
   tabs de filtro, painel inline, cartão 3D, sticky summary.
═══════════════════════════════════════════════════════════════ */
:root{
  --green:#00c468; --green-h:#00d470; --green-d:#009e53; --green-dim:rgba(0,196,104,.1);
  --bg:#f4f5f7; --sur:#fff; --sur2:#f8f9fa; --sur3:#f0f1f3;
  --bdr:rgba(0,0,0,.08); --bdr2:rgba(0,0,0,.13); --bdr3:rgba(0,0,0,.2);
  --ink:#111; --ink2:#444; --ink3:#777; --ink4:#aaa;
  /* método — M-Pesa */
  --mp:#e53935; --mp-bg:rgba(229,57,53,.07); --mp-bdr:rgba(229,57,53,.3); --mp-glow:rgba(229,57,53,.15);
  /* método — e-Mola */
  --em:#fb8c00; --em-bg:rgba(251,140,0,.07); --em-bdr:rgba(251,140,0,.3); --em-glow:rgba(251,140,0,.15);
  /* método — Visa */
  --vi:#1e88e5; --vi-bg:rgba(30,136,229,.07); --vi-bdr:rgba(30,136,229,.3); --vi-glow:rgba(30,136,229,.15);
  /* método — Mastercard */
  --mc:#ff8f00; --mc-bg:rgba(255,143,0,.07); --mc-bdr:rgba(255,143,0,.3); --mc-glow:rgba(255,143,0,.15);
  /* semânticas */
  --red:#e53935; --ok:#00a657; --info:#1565c0;
  --r6:6px; --r8:8px; --r10:10px; --r12:12px; --r14:14px; --r16:16px; --r20:20px; --rpill:999px;
  --ease:cubic-bezier(.16,1,.3,1);
  --font:'Plus Jakarta Sans',-apple-system,sans-serif;
  --mono:'JetBrains Mono',monospace;
  --hdr:52px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);font-size:14px;line-height:1.5;background:var(--bg);color:var(--ink2);-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}
button{font-family:var(--font);cursor:pointer;border:none;background:none}
input,select,textarea{font-family:var(--font)}

/* ── HEADER ── */
.hdr{background:var(--sur);border-bottom:1px solid var(--bdr);position:sticky;top:0;z-index:500;height:var(--hdr)}
.hdr-in{display:flex;align-items:center;gap:12px;height:100%;max-width:860px;margin:0 auto;padding:0 20px}
.logo{display:flex;align-items:center;gap:8px;font-size:16px;font-weight:800;color:var(--ink);letter-spacing:-.4px}
.logo-mk{width:28px;height:28px;background:var(--green);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;color:#fff;flex-shrink:0}
.logo em{color:var(--green);font-style:normal}
.hdr-r{margin-left:auto;display:flex;align-items:center;gap:8px}
.hdr-btn{display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:var(--r8);border:1px solid var(--bdr2);font-size:12.5px;font-weight:600;color:var(--ink2);transition:all .14s}
.hdr-btn:hover{border-color:var(--green);color:var(--green)}
.hdr-btn.pri{background:var(--green);border-color:var(--green);color:#fff}
.hdr-btn.pri:hover{background:var(--green-h)}
.hdr-btn img{width:22px;height:22px;border-radius:50%;object-fit:cover}
.hdr-ssl{display:flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;color:var(--ink3);padding:5px 10px;border-radius:var(--r8);border:1px solid var(--bdr)}
.hdr-ssl i{color:var(--green);font-size:11px}

/* ── STEPS ── */
.steps{display:flex;align-items:center;max-width:860px;margin:0 auto;padding:16px 20px}
.stp{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;white-space:nowrap}
.stp-n{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;border:1.5px solid currentColor}
.stp.done{color:var(--ok)}.stp.done .stp-n{background:var(--ok);border-color:var(--ok);color:#fff}
.stp.act{color:var(--green)}.stp.act .stp-n{background:var(--green);border-color:var(--green);color:#fff}
.stp.idle{color:var(--ink4)}
.stp-ln{flex:1;height:1.5px;background:var(--bdr);margin:0 10px}
.stp-ln.done{background:var(--ok)}

/* ── LAYOUT GRID ── */
.co{max-width:860px;margin:0 auto;padding:0 20px 80px;display:grid;grid-template-columns:1fr 300px;gap:18px;align-items:start}

/* ── BLOCOS ── */
.blk{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r16);overflow:hidden;margin-bottom:14px}
.blk-hd{padding:15px 20px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:10px}
.blk-n{width:26px;height:26px;border-radius:50%;background:var(--green);color:#fff;font-size:11.5px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.blk-t{font-size:14px;font-weight:700;color:var(--ink)}
.blk-body{padding:18px 20px}

/* ── BANNER VISITANTE ── */
.g-bar{padding:11px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--r10);margin-bottom:14px;font-size:12.5px;color:#1d4ed8;display:flex;align-items:flex-start;gap:8px;line-height:1.5}
.g-bar i{flex-shrink:0;margin-top:1px;font-size:14px}
.g-bar a{font-weight:700;text-decoration:underline;color:#1d4ed8}

/* ══════════════════════════════════════════════════
   TABS DE FILTRO — no cabeçalho do bloco pagamento
══════════════════════════════════════════════════ */
.pm-tabs{display:flex;gap:0;margin-left:auto}
.pm-tab{padding:6px 11px;font-size:11.5px;font-weight:600;color:var(--ink3);cursor:pointer;border-radius:var(--r8);transition:all .14s;white-space:nowrap}
.pm-tab:hover{color:var(--ink2);background:var(--sur2)}
.pm-tab.on{color:var(--green);background:var(--green-dim)}

/* ══════════════════════════════════════════════════
   MÉTODOS DE PAGAMENTO — GRID PRINCIPAL
   4 métodos em 2×2, banco em largura total.
   Cada tile tem cor de marca única.
══════════════════════════════════════════════════ */
.pm-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:18px 20px 0}
.pm-full{grid-column:span 2}

/* Label-tile base */
.pm-tile{position:relative;cursor:pointer;display:block}
.pm-tile input{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
.pm-face{
  display:flex;align-items:center;gap:13px;
  padding:14px 15px;border-radius:var(--r14);
  border:1.5px solid var(--bdr);
  background:var(--sur);
  transition:border-color .18s,background .18s,box-shadow .18s;
  user-select:none;
}
.pm-face:hover{border-color:var(--bdr2);background:var(--sur2)}

/* Ícone container */
.pm-ico{width:44px;height:44px;border-radius:var(--r10);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s;font-size:20px;font-weight:900}

/* Texto */
.pm-txt{flex:1;min-width:0}
.pm-name{font-size:13px;font-weight:700;color:var(--ink);margin-bottom:1px;transition:color .15s}
.pm-sub{font-size:11px;color:var(--ink3)}
.pm-badge{display:inline-block;margin-top:4px;padding:2px 7px;border-radius:var(--rpill);font-size:9.5px;font-weight:700;letter-spacing:.3px;text-transform:uppercase}

/* Check circle */
.pm-chk{width:20px;height:20px;border-radius:50%;border:1.5px solid var(--bdr2);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .18s;font-size:9px;color:transparent}

/* ── M-PESA (vermelho) ── */
.pm-mpesa .pm-ico{background:rgba(229,57,53,.1);color:var(--mp)}
.pm-mpesa .pm-badge{background:rgba(229,57,53,.1);color:#b71c1c}
.pm-mpesa input:checked ~ .pm-face{border-color:var(--mp);background:var(--mp-bg);box-shadow:0 0 0 3px var(--mp-glow)}
.pm-mpesa input:checked ~ .pm-face .pm-ico{background:rgba(229,57,53,.18);box-shadow:0 3px 12px rgba(229,57,53,.25)}
.pm-mpesa input:checked ~ .pm-face .pm-name{color:var(--mp)}
.pm-mpesa input:checked ~ .pm-face .pm-chk{background:var(--mp);border-color:var(--mp);color:#fff}

/* ── e-MOLA (laranja) ── */
.pm-emola .pm-ico{background:rgba(251,140,0,.1);color:var(--em)}
.pm-emola .pm-badge{background:rgba(251,140,0,.1);color:#bf360c}
.pm-emola input:checked ~ .pm-face{border-color:var(--em);background:var(--em-bg);box-shadow:0 0 0 3px var(--em-glow)}
.pm-emola input:checked ~ .pm-face .pm-ico{background:rgba(251,140,0,.18);box-shadow:0 3px 12px rgba(251,140,0,.25)}
.pm-emola input:checked ~ .pm-face .pm-name{color:var(--em)}
.pm-emola input:checked ~ .pm-face .pm-chk{background:var(--em);border-color:var(--em);color:#fff}

/* ── VISA (azul) ── */
.pm-visa .pm-ico{background:rgba(30,136,229,.1);color:var(--vi)}
.pm-visa .pm-badge{background:rgba(30,136,229,.1);color:#0d47a1}
.pm-visa input:checked ~ .pm-face{border-color:var(--vi);background:var(--vi-bg);box-shadow:0 0 0 3px var(--vi-glow)}
.pm-visa input:checked ~ .pm-face .pm-ico{background:rgba(30,136,229,.18);box-shadow:0 3px 12px rgba(30,136,229,.25)}
.pm-visa input:checked ~ .pm-face .pm-name{color:var(--vi)}
.pm-visa input:checked ~ .pm-face .pm-chk{background:var(--vi);border-color:var(--vi);color:#fff}

/* ── MASTERCARD (âmbar) ── */
.pm-master .pm-ico{background:rgba(255,143,0,.1);color:var(--mc)}
.pm-master .pm-badge{background:rgba(255,143,0,.1);color:#bf360c}
.pm-master input:checked ~ .pm-face{border-color:var(--mc);background:var(--mc-bg);box-shadow:0 0 0 3px var(--mc-glow)}
.pm-master input:checked ~ .pm-face .pm-ico{background:rgba(255,143,0,.18);box-shadow:0 3px 12px rgba(255,143,0,.2)}
.pm-master input:checked ~ .pm-face .pm-name{color:var(--mc)}
.pm-master input:checked ~ .pm-face .pm-chk{background:var(--mc);border-color:var(--mc);color:#fff}

/* ── BANCO (cinza) ── */
.pm-bank .pm-ico{background:var(--sur2);color:var(--ink3)}
.pm-bank .pm-badge{background:var(--sur2);color:var(--ink3)}
.pm-bank input:checked ~ .pm-face{border-color:var(--bdr3);background:var(--sur2);box-shadow:0 0 0 3px rgba(0,0,0,.05)}
.pm-bank input:checked ~ .pm-face .pm-chk{background:var(--ink2);border-color:var(--ink2);color:#fff}

/* ── CHECK MARK (SVG no span) ── */
.pm-tile input:checked ~ .pm-face .pm-chk::after{content:'✓';font-size:10px;font-weight:900;color:#fff}
.pm-tile input:checked ~ .pm-face .pm-chk{font-size:0}

/* ══════════════════════════════════════════════════
   PAINÉIS DE DETALHE — expandem inline abaixo dos tiles
══════════════════════════════════════════════════ */
.pm-panel{display:none;margin:12px 20px 18px;animation:pnlIn .2s var(--ease)}
.pm-panel.on{display:block}
@keyframes pnlIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:none}}

/* Cabeçalho painel */
.pp-hd{display:flex;align-items:center;gap:10px;margin-bottom:13px}
.pp-ico{width:38px;height:38px;border-radius:var(--r10);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.pp-ico.mp{background:rgba(229,57,53,.1);color:var(--mp)}
.pp-ico.em{background:rgba(251,140,0,.1);color:var(--em)}
.pp-ico.vi{background:rgba(30,136,229,.1);color:var(--vi)}
.pp-ico.bk{background:var(--sur2);color:var(--ink3)}
.pp-name{font-size:13.5px;font-weight:700;color:var(--ink)}
.pp-hint{font-size:11.5px;color:var(--ink3)}

/* Input telemóvel */
.phn-wrap{display:flex;border-radius:var(--r10);overflow:hidden;border:1px solid var(--bdr2);transition:outline .12s}
.phn-wrap:focus-within{outline:2.5px solid var(--green);outline-offset:1px}
.phn-pre{padding:0 13px;font-size:12.5px;font-weight:700;color:var(--ink3);border-right:1px solid var(--bdr);background:var(--sur2);display:flex;align-items:center;gap:6px;white-space:nowrap;font-family:var(--mono)}
.phn-inp{flex:1;padding:11px 13px;border:none;outline:none;background:var(--sur);font-size:16px;font-weight:700;color:var(--ink);font-family:var(--mono);letter-spacing:1px}
.phn-inp::placeholder{color:var(--ink4);font-weight:400;font-size:14px}

/* Status MM */
.mm-st{display:none;margin-top:11px;padding:11px 13px;border-radius:var(--r10);border:1px solid;font-size:12.5px;font-weight:500;line-height:1.5;gap:9px;align-items:flex-start}
.mm-st.on{display:flex}
.mm-st.wait{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.mm-st.poll{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.mm-st.ok  {background:#f0fdf4;border-color:#86efac;color:#15803d}
.mm-st.fail{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
.mm-st i{flex-shrink:0;margin-top:1px}
.mm-st-txt b{display:block;font-weight:700;margin-bottom:1px}

/* ── CARTÃO 3D ── */
.crd-scene{width:100%;max-width:300px;height:168px;perspective:900px;margin:0 auto 16px;cursor:pointer}
.crd-inner{width:100%;height:100%;position:relative;transform-style:preserve-3d;transition:transform .55s var(--ease)}
.crd-inner.flip{transform:rotateY(180deg)}
.crd-face{position:absolute;inset:0;border-radius:14px;backface-visibility:hidden;-webkit-backface-visibility:hidden;padding:16px 18px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 12px 40px rgba(0,0,0,.2)}
.crd-fr{background:linear-gradient(135deg,#1a237e 0%,#283593 100%);color:#fff}
.crd-fr.mc-theme{background:linear-gradient(135deg,#1b1b1b 0%,#2d2d2d 100%)}
.crd-bk{background:linear-gradient(135deg,#1a237e,#283593);color:#fff;transform:rotateY(180deg)}
.crd-r1{display:flex;justify-content:space-between;align-items:flex-start}
.crd-chip{width:32px;height:24px;background:linear-gradient(135deg,#c8a850,#f0d070);border-radius:4px}
.crd-logo{font-size:18px;font-weight:900}
.visa-logo{font-style:italic;font-family:serif;font-size:20px;letter-spacing:-1px}
.mc-logo{display:flex}.mc-logo span{width:20px;height:20px;border-radius:50%}
.mc-logo span:first-child{background:#eb001b}.mc-logo span:last-child{background:#f79e1b;margin-left:-8px}
.crd-num{font-size:15px;font-weight:700;letter-spacing:2.5px;font-family:var(--mono);text-align:center;text-shadow:0 1px 4px rgba(0,0,0,.3)}
.crd-r3{display:flex;justify-content:space-between;align-items:flex-end}
.crd-lbl{font-size:7.5px;text-transform:uppercase;opacity:.5;letter-spacing:.8px;margin-bottom:2px}
.crd-val{font-size:12px;font-weight:700;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.crd-stripe{height:36px;background:#000;margin:10px -18px;opacity:.8}
.crd-cvvbox{background:#fff;border-radius:3px;height:28px;display:flex;align-items:center;justify-content:flex-end;padding:0 10px;font-family:var(--mono);font-size:13px;letter-spacing:3px;color:#111;font-weight:700}

/* Inputs cartão */
.ci-wrap{display:flex;flex-direction:column;gap:4px;margin-top:10px}
.ci-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.ci-lbl{font-size:10.5px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.5px}
.ci{padding:10px 12px;border-radius:var(--r10);border:1px solid var(--bdr2);background:var(--sur);color:var(--ink);font-size:13.5px;outline:none;width:100%;transition:outline .12s}
.ci:focus{outline:2.5px solid var(--green);outline-offset:1px}
.ci.mono{font-family:var(--mono);letter-spacing:1.5px}
.ci.err{outline:2px solid var(--red)}.ci.ok{outline:2px solid var(--ok)}
.ci-ssl{display:flex;align-items:center;gap:7px;padding:8px 11px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--r8);font-size:11.5px;color:#166534;margin-top:10px}

/* Banco */
.bk-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.bk-tbl td{padding:7px 0;border-bottom:1px solid var(--bdr)}
.bk-tbl tr:last-child td{border:none}
.bk-tbl td:first-child{color:var(--ink3);width:90px;font-weight:600}
.bk-tbl td:last-child{font-family:var(--mono);font-size:12px;font-weight:600;color:var(--ink)}
.bk-note{margin-top:10px;padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--r8);font-size:11.5px;color:#1d4ed8;line-height:1.5}

/* ── FORM ENTREGA ── */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ff{display:flex;flex-direction:column;gap:4px}
.ff.full{grid-column:span 2}
.flbl{font-size:10.5px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.5px}
.flbl span{color:var(--red)}
.finp,.ftxt{padding:10px 12px;border-radius:var(--r10);border:1px solid var(--bdr2);background:var(--sur);color:var(--ink);font-size:13.5px;outline:none;width:100%;transition:outline .12s}
.finp:focus,.ftxt:focus{outline:2.5px solid var(--green);outline-offset:1px}
.ftxt{resize:vertical;min-height:64px}

/* ── RESUMO ── */
.sum{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r16);overflow:hidden;position:sticky;top:calc(var(--hdr)+16px)}
.sum-hd{padding:14px 18px;border-bottom:1px solid var(--bdr);font-size:13.5px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:7px}
.sum-hd i{color:var(--green)}
.sum-body{padding:14px 18px}
.sum-itm{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.sum-img{width:40px;height:40px;border-radius:var(--r8);border:1px solid var(--bdr);background:var(--sur2);overflow:hidden;flex-shrink:0}
.sum-img img{width:100%;height:100%;object-fit:cover}
.sum-iname{font-size:12.5px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.sum-iqty{font-size:11px;color:var(--ink4)}
.sum-iprice{font-size:13px;font-weight:700;font-family:var(--mono);flex-shrink:0;color:var(--ink)}
.sum-sep{height:1px;background:var(--bdr);margin:10px 0}
.sum-row{display:flex;justify-content:space-between;font-size:12.5px;padding:3px 0;color:var(--ink3)}
.sum-row span:last-child{font-weight:600;color:var(--ink2);font-family:var(--mono)}
.sum-row.free span:last-child{color:var(--ok)}
.sum-total{display:flex;justify-content:space-between;align-items:baseline;padding:12px 0 0;margin-top:6px;border-top:1px solid var(--bdr)}
.sum-total-l{font-size:15px;font-weight:700;color:var(--ink)}
.sum-total-r{font-size:22px;font-weight:800;color:var(--ok);font-family:var(--mono)}

/* Botão confirmar */
.btn-pay{
  width:100%;margin-top:14px;padding:14px;
  border-radius:var(--r12);background:var(--green);
  color:#fff;font-size:14.5px;font-weight:800;
  display:flex;align-items:center;justify-content:center;gap:9px;
  transition:background .15s,transform .1s;box-shadow:0 4px 16px rgba(0,196,104,.3);
  letter-spacing:-.2px;
}
.btn-pay:hover:not(:disabled){background:var(--green-h);transform:translateY(-1px);box-shadow:0 6px 22px rgba(0,196,104,.35)}
.btn-pay:active:not(:disabled){transform:translateY(0)}
.btn-pay:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}

/* Selos */
.trust{display:flex;flex-direction:column;gap:6px;margin-top:12px;padding-top:12px;border-top:1px solid var(--bdr)}
.trust-row{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--ink4)}
.trust-row i{color:var(--green);width:12px;font-size:11px}

/* ── OVERLAY ── */
.ov{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);place-items:center}
.ov.on{display:grid}
.ov-box{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r20);padding:32px 36px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:11px;width:90%;max-width:360px}
.ov-spin{width:44px;height:44px;border:3px solid var(--bdr);border-top-color:var(--green);border-radius:50%;animation:spin .8s linear infinite}
.ov-ok{width:44px;height:44px;border-radius:50%;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;animation:pop .4s var(--ease)}
.ov-title{font-size:16px;font-weight:700;color:var(--ink)}
.ov-sub{font-size:12.5px;color:var(--ink3)}
.ov-steps{display:flex;flex-direction:column;gap:7px;text-align:left;width:100%;margin-top:4px}
.ov-stp{font-size:12.5px;color:var(--ink4);display:flex;align-items:center;gap:8px;padding:2px 0}
.ov-stp.act{color:#1e88e5;font-weight:600}.ov-stp.dn{color:var(--ok);font-weight:600}
.ov-stp i{width:14px;text-align:center}
.ov-err{display:none;padding:10px 13px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--r8);color:#b91c1c;font-size:13px;margin-top:6px;max-width:280px}
.ov-back{display:none;margin-top:6px;padding:8px 18px;background:var(--sur2);border:1px solid var(--bdr2);border-radius:var(--r8);font-size:13px;font-weight:600;color:var(--ink2)}

/* ── MODAL LOGIN ── */
.lm{display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);place-items:center}
.lm.on{display:grid}
.lm-box{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r20);padding:28px 30px;max-width:390px;width:90%;animation:pnlIn .25s var(--ease)}
.lm-ico{width:52px;height:52px;background:var(--green-dim);border:1.5px solid rgba(0,196,104,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:20px;color:var(--green)}
.lm-t{font-size:18px;font-weight:800;color:var(--ink);text-align:center;margin-bottom:5px}
.lm-s{font-size:13px;color:var(--ink3);text-align:center;line-height:1.5;margin-bottom:16px}
.lm-perks{display:flex;flex-direction:column;gap:7px;margin-bottom:16px}
.lm-perk{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink2)}
.lm-perk i{color:var(--green);width:14px}
.lm-div{height:1px;background:var(--bdr);margin:14px 0}
.lm-btns{display:flex;flex-direction:column;gap:9px}
.lm-lg{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;background:var(--green);border-radius:var(--r10);color:#fff;font-size:14.5px;font-weight:700;transition:background .15s}
.lm-lg:hover{background:var(--green-h)}
.lm-reg{display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border:1px solid var(--bdr2);border-radius:var(--r10);color:var(--ink2);font-size:14px;font-weight:600;transition:all .14s}
.lm-reg:hover{border-color:var(--green);color:var(--green)}
.lm-skip{display:block;text-align:center;font-size:12.5px;color:var(--ink4);margin-top:12px;cursor:pointer}
.lm-skip:hover{color:var(--ink3)}

/* ── ECRÃ SUCESSO ── */
#ss{display:none;position:fixed;inset:0;background:var(--bg);z-index:10001;overflow-y:auto}
.ss-wrap{max-width:480px;margin:0 auto;padding:48px 20px 80px;text-align:center}
.ss-ico{width:72px;height:72px;border-radius:50%;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:28px;opacity:0;transform:scale(.5)}
.ss-h{font-size:1.55rem;font-weight:800;color:var(--ink);margin-bottom:6px;opacity:0;transform:translateY(10px)}
.ss-sub{font-size:13.5px;color:var(--ink3);margin-bottom:22px;opacity:0;transform:translateY(10px)}
.ss-card{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r14);padding:15px;margin-bottom:12px;text-align:left;opacity:0;transform:translateY(10px)}
.ss-ct{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--ink4);margin-bottom:8px}
.ss-num{font-size:1.1rem;font-weight:800;color:var(--ok);font-family:var(--mono);display:flex;align-items:center;gap:8px}
.cp-btn{font-size:14px;color:var(--ink4);cursor:pointer;background:none;border:none;transition:color .14s}
.cp-btn:hover{color:var(--green)}
.ss-steps{display:flex;flex-direction:column;gap:6px}
.ss-stp{display:flex;align-items:center;gap:8px;padding:7px 11px;background:var(--sur2);border-radius:var(--r8)}
.ss-stp i{color:var(--green);width:14px}
.ss-stp-t{font-size:12.5px;color:var(--ink2)}
.ss-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:12px;opacity:0;transform:translateY(10px)}
.ss-b1{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;background:var(--green);border-radius:var(--r10);color:#fff;font-weight:700;font-size:14px;transition:background .14s}
.ss-b1:hover{background:var(--green-h)}
.ss-b2{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:1px solid var(--bdr2);border-radius:var(--r10);color:var(--ink2);font-size:14px;font-weight:500;transition:all .14s}
.ss-b2:hover{border-color:var(--green);color:var(--green)}

/* ── ALERT FORM ── */
.ferr{display:flex;align-items:flex-start;gap:9px;padding:11px 13px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--r10);margin-bottom:14px;font-size:13px;color:#b91c1c}

/* ── ANIMS ── */
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
@keyframes pnlIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}

/* ── RESPONSIVE ── */
@media(max-width:900px){.co{grid-template-columns:1fr}.sum{position:static;margin-bottom:20px}}
@media(max-width:580px){
  .co{padding:0 14px 60px}
  .pm-grid{grid-template-columns:1fr}.pm-full{grid-column:span 1}
  .fg{grid-template-columns:1fr}.ff.full{grid-column:span 1}
  .pm-tabs{display:none}
  .steps span{display:none}.stp{gap:4px}
  .ci-row{grid-template-columns:1fr}
  .lm-box{padding:22px 18px}
}
</style>
</head>
<body>

<!-- ── HEADER ──────────────────────────────────────────────────────── -->
<header class="hdr">
  <div class="hdr-in">
    <a href="index.php" class="logo">
      <div class="logo-mk"><i class="fa-solid fa-leaf" style="font-size:11px"></i></div>
      VSG<em>&middot;</em>MARKET
    </a>
    <div class="hdr-r">
      <div class="hdr-ssl"><i class="fa-solid fa-lock"></i> SSL seguro</div>
      <?php if ($user_logged_in): ?>
        <a href="pages/person/index.php" class="hdr-btn">
          <?php if ($user_avatar): ?>
            <img src="<?= esc($user_avatar) ?>" alt="">
          <?php else: ?>
            <i class="fa-solid fa-circle-user"></i>
          <?php endif; ?>
          <?= esc($user_name) ?>
        </a>
      <?php else: ?>
        <a href="registration/login/login.php?redirect=<?= urlencode($return_url) ?>" class="hdr-btn">
          <i class="fa-solid fa-circle-user"></i> Entrar
        </a>
        <a href="registration/register/painel_cadastro.php?redirect=<?= urlencode($return_url) ?>" class="hdr-btn pri">
          <i class="fa-solid fa-user-plus"></i> Criar conta
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ── STEPS ────────────────────────────────────────────────────────── -->
<nav class="steps">
  <div class="stp done">
    <div class="stp-n"><i class="fa-solid fa-check" style="font-size:9px"></i></div>
    <span>Carrinho</span>
  </div>
  <div class="stp-ln done"></div>
  <div class="stp act">
    <div class="stp-n">2</div>
    <span>Pagamento</span>
  </div>
  <div class="stp-ln"></div>
  <div class="stp idle">
    <div class="stp-n">3</div>
    <span>Confirmação</span>
  </div>
</nav>

<!-- ── LAYOUT PRINCIPAL ─────────────────────────────────────────────── -->
<div class="co">

  <!-- ══ COLUNA ESQUERDA ══════════════════════════════════════════ -->
  <div>
    <div id="formErrors"></div>

    <?php if (!$user_logged_in): ?>
    <div class="g-bar">
      <i class="fa-solid fa-circle-info"></i>
      <span>Podes preencher os dados livremente. O login é pedido <strong>apenas ao confirmar o pagamento</strong>. —
        <a href="registration/login/login.php?redirect=<?= urlencode($return_url) ?>">Entrar agora</a>
      </span>
    </div>
    <?php endif; ?>

    <!-- ── BLOCO 1: MÉTODO DE PAGAMENTO ─────────────────────── -->
    <div class="blk">
      <div class="blk-hd">
        <div class="blk-n">1</div>
        <div class="blk-t">Método de pagamento</div>
        <!-- TABS FILTRO -->
        <div class="pm-tabs">
          <div class="pm-tab on"  onclick="filterPM('all',this)">Todos</div>
          <div class="pm-tab" onclick="filterPM('mm',this)">Mobile Money</div>
          <div class="pm-tab" onclick="filterPM('card',this)">Cartão</div>
          <div class="pm-tab" onclick="filterPM('bank',this)">Banco</div>
        </div>
      </div>

      <!-- GRID DE TILES -->
      <div class="pm-grid" id="pmGrid">

        <!-- M-PESA -->
        <label class="pm-tile pm-mpesa" data-cat="mm" onclick="onPay('mpesa')">
          <input type="radio" name="pm" value="mpesa" checked>
          <div class="pm-face">
            <div class="pm-ico" style="font-size:17px;font-weight:900;font-family:var(--mono)">M</div>
            <div class="pm-txt">
              <div class="pm-name">M-Pesa</div>
              <div class="pm-sub">Vodacom · USSD Push</div>
              <div class="pm-badge"><i class="fa-solid fa-bolt" style="font-size:8px"></i> Recomendado</div>
            </div>
            <div class="pm-chk"></div>
          </div>
        </label>

        <!-- e-MOLA -->
        <label class="pm-tile pm-emola" data-cat="mm" onclick="onPay('emola')">
          <input type="radio" name="pm" value="emola">
          <div class="pm-face">
            <div class="pm-ico" style="font-size:13px;font-weight:900;font-family:var(--mono)">eM</div>
            <div class="pm-txt">
              <div class="pm-name">e-Mola</div>
              <div class="pm-sub">Movitel · USSD Push</div>
              <div class="pm-badge"><i class="fa-solid fa-bolt" style="font-size:8px"></i> Instantâneo</div>
            </div>
            <div class="pm-chk"></div>
          </div>
        </label>

        <!-- VISA -->
        <label class="pm-tile pm-visa" data-cat="card" onclick="onPay('visa')">
          <input type="radio" name="pm" value="visa">
          <div class="pm-face">
            <div class="pm-ico"><i class="fa-brands fa-cc-visa" style="font-size:22px"></i></div>
            <div class="pm-txt">
              <div class="pm-name">Visa</div>
              <div class="pm-sub">Crédito / Débito</div>
              <div class="pm-badge"><i class="fa-solid fa-shield-halved" style="font-size:8px"></i> SSL 256-bit</div>
            </div>
            <div class="pm-chk"></div>
          </div>
        </label>

        <!-- MASTERCARD -->
        <label class="pm-tile pm-master" data-cat="card" onclick="onPay('mastercard')">
          <input type="radio" name="pm" value="mastercard">
          <div class="pm-face">
            <div class="pm-ico">
              <span style="display:flex"><span style="width:20px;height:20px;border-radius:50%;background:#eb001b;display:block"></span><span style="width:20px;height:20px;border-radius:50%;background:#f79e1b;display:block;margin-left:-8px"></span></span>
            </div>
            <div class="pm-txt">
              <div class="pm-name">Mastercard</div>
              <div class="pm-sub">Crédito / Débito</div>
              <div class="pm-badge"><i class="fa-solid fa-shield-halved" style="font-size:8px"></i> SSL 256-bit</div>
            </div>
            <div class="pm-chk"></div>
          </div>
        </label>

        <!-- BANCO (largura total) -->
        <label class="pm-tile pm-bank pm-full" data-cat="bank" onclick="onPay('manual')">
          <input type="radio" name="pm" value="manual">
          <div class="pm-face">
            <div class="pm-ico"><i class="fa-solid fa-building-columns" style="font-size:18px"></i></div>
            <div class="pm-txt">
              <div class="pm-name">Transferência bancária</div>
              <div class="pm-sub">BCI · Pagamento manual · 1-2 dias úteis</div>
              <div class="pm-badge"><i class="fa-solid fa-clock" style="font-size:8px"></i> Manual</div>
            </div>
            <div class="pm-chk"></div>
          </div>
        </label>

      </div><!-- /pm-grid -->

      <!-- ── PAINEL M-PESA ── -->
      <div id="pan-mpesa" class="pm-panel on">
        <div class="pp-hd">
          <div class="pp-ico mp"><i class="fa-solid fa-mobile-screen"></i></div>
          <div><div class="pp-name">M-Pesa · Vodacom</div><div class="pp-hint">Número associado à conta M-Pesa</div></div>
        </div>
        <div class="phn-wrap">
          <div class="phn-pre">🇲🇿 +258</div>
          <input type="tel" id="mpesa-phone" class="phn-inp" placeholder="84 000 0000" maxlength="12"
                 inputmode="numeric" value="<?= esc($phone_suffix) ?>" oninput="fmtPhone(this)">
        </div>
        <div class="mm-st" id="st-mpesa">
          <i class="fa-solid fa-circle-notch fa-spin"></i>
          <div class="mm-st-txt"></div>
        </div>
      </div>

      <!-- ── PAINEL e-MOLA ── -->
      <div id="pan-emola" class="pm-panel">
        <div class="pp-hd">
          <div class="pp-ico em"><i class="fa-solid fa-mobile-screen"></i></div>
          <div><div class="pp-name">e-Mola · Movitel</div><div class="pp-hint">Número associado à conta e-Mola</div></div>
        </div>
        <div class="phn-wrap">
          <div class="phn-pre">🇲🇿 +258</div>
          <input type="tel" id="emola-phone" class="phn-inp" placeholder="86 000 0000" maxlength="12"
                 inputmode="numeric" value="<?= esc($phone_suffix) ?>" oninput="fmtPhone(this)">
        </div>
        <div class="mm-st" id="st-emola">
          <i class="fa-solid fa-circle-notch fa-spin"></i>
          <div class="mm-st-txt"></div>
        </div>
      </div>

      <!-- ── PAINEL CARTÃO ── -->
      <div id="pan-card" class="pm-panel">
        <!-- Cartão 3D -->
        <div class="crd-scene" onclick="flipCard()">
          <div class="crd-inner" id="crd3d">
            <div class="crd-face crd-fr" id="crdFace">
              <div class="crd-r1">
                <div class="crd-chip"></div>
                <div class="crd-logo" id="crdLogo">
                  <i class="fa-solid fa-credit-card" style="font-size:18px;opacity:.3"></i>
                </div>
              </div>
              <div class="crd-num" id="crdNum">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;</div>
              <div class="crd-r3">
                <div><div class="crd-lbl">Titular</div><div class="crd-val" id="crdName">NOME NO CARTÃO</div></div>
                <div style="text-align:right"><div class="crd-lbl">Validade</div><div class="crd-val" id="crdExp">MM/AA</div></div>
              </div>
            </div>
            <div class="crd-face crd-bk">
              <div class="crd-stripe"></div>
              <div><div class="crd-lbl" style="opacity:.4;font-size:7px;margin-bottom:3px">CVV</div>
              <div class="crd-cvvbox" id="crdCvv">&bull;&bull;&bull;</div></div>
            </div>
          </div>
        </div>
        <!-- Inputs -->
        <div class="ci-wrap">
          <div class="ff" style="gap:4px">
            <div class="ci-lbl">Número do cartão</div>
            <input type="text" id="cNumber" class="ci mono" placeholder="1234 5678 9012 3456"
                   maxlength="23" autocomplete="cc-number" inputmode="numeric"
                   oninput="onCNum(this)" onfocus="flipFront()">
          </div>
          <div class="ff" style="gap:4px">
            <div class="ci-lbl">Nome no cartão</div>
            <input type="text" id="cName2" class="ci" placeholder="NOME COMPLETO"
                   maxlength="26" autocomplete="cc-name"
                   oninput="onCName(this)" onfocus="flipFront()">
          </div>
          <div class="ci-row">
            <div class="ff" style="gap:4px">
              <div class="ci-lbl">Validade</div>
              <input type="text" id="cExp2" class="ci" placeholder="MM/AA"
                     maxlength="5" autocomplete="cc-exp" inputmode="numeric"
                     oninput="onCExp(this)" onfocus="flipFront()">
            </div>
            <div class="ff" style="gap:4px">
              <div class="ci-lbl">CVV</div>
              <input type="text" id="cCvv2" class="ci" placeholder="&bull;&bull;&bull;"
                     maxlength="4" autocomplete="cc-csc" inputmode="numeric"
                     oninput="onCCvv(this)" onfocus="flipBack()" onblur="flipFront()">
            </div>
          </div>
          <div class="ci-ssl"><i class="fa-solid fa-shield-halved"></i> Encriptado com SSL 256-bit &mdash; dados nunca armazenados</div>
        </div>
      </div>

      <!-- ── PAINEL BANCO ── -->
      <div id="pan-bank" class="pm-panel">
        <div class="pp-hd">
          <div class="pp-ico bk"><i class="fa-solid fa-building-columns"></i></div>
          <div><div class="pp-name">Transferência Bancária</div><div class="pp-hint">Efectue após confirmar o pedido</div></div>
        </div>
        <table class="bk-tbl">
          <tr><td>Banco</td><td>BCI</td></tr>
          <tr><td>IBAN</td><td>MZ59 0006 0000 0000 0000 000</td></tr>
          <tr><td>Titular</td><td>VSG Marketplace Lda.</td></tr>
          <tr><td>Email</td><td>pagamentos@vsgmarket.co.mz</td></tr>
        </table>
        <div class="bk-note"><i class="fa-solid fa-circle-info"></i> Use o número do pedido como referência. Envie o comprovativo para o email acima.</div>
      </div>

    </div><!-- /blk pagamento -->

    <!-- ── BLOCO 2: ENTREGA ─────────────────────────────────────── -->
    <div class="blk">
      <div class="blk-hd">
        <div class="blk-n">2</div>
        <div class="blk-t">Informações de entrega</div>
      </div>
      <div class="blk-body">
        <div class="fg">
          <div class="ff">
            <label class="flbl">Nome completo <span>*</span></label>
            <input type="text" id="sName" class="finp" required
                   value="<?= esc($user_data['nome'] ?? '') ?>" placeholder="Nome de quem recebe">
          </div>
          <div class="ff">
            <label class="flbl">Telefone <span>*</span></label>
            <input type="tel" id="sPhone" class="finp" required
                   value="<?= esc($user_data['telefone'] ?? '') ?>" placeholder="+258 84 000 0000">
          </div>
          <div class="ff full">
            <label class="flbl">Endereço <span>*</span></label>
            <input type="text" id="sAddress" class="finp" required
                   value="<?= esc($user_data['address'] ?? '') ?>" placeholder="Rua, número, bairro">
          </div>
          <div class="ff">
            <label class="flbl">Cidade <span>*</span></label>
            <input type="text" id="sCity" class="finp" required
                   value="<?= esc($user_data['city'] ?? '') ?>" placeholder="Ex: Maputo">
          </div>
          <div class="ff">
            <label class="flbl">Província</label>
            <input type="text" id="sState" class="finp"
                   value="<?= esc($user_data['state'] ?? '') ?>" placeholder="Ex: Maputo">
          </div>
          <div class="ff full">
            <label class="flbl">Notas (opcional)</label>
            <textarea id="sNotes" class="ftxt" placeholder="Referências, instruções especiais..."></textarea>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /coluna esquerda -->

  <!-- ══ RESUMO (coluna direita) ══════════════════════════════════ -->
  <div>
    <div class="sum">
      <div class="sum-hd"><i class="fa-solid fa-receipt"></i> Resumo do pedido</div>
      <div class="sum-body">
        <?php foreach ($checkout_items as $ci): ?>
        <div class="sum-itm">
          <div class="sum-img">
            <img src="<?= esc($ci['img_url']) ?>" alt="<?= esc($ci['product_name']) ?>"
                 onerror="this.src='https://ui-avatars.com/api/?name=P&size=100&background=00b96b&color=fff'">
          </div>
          <div style="flex:1;min-width:0">
            <div class="sum-iname"><?= esc($ci['product_name']) ?></div>
            <div class="sum-iqty">Qtd: <?= (int)$ci['quantity'] ?></div>
          </div>
          <div class="sum-iprice"><?= $fmt($ci['line_total']) ?></div>
        </div>
        <?php endforeach; ?>

        <div class="sum-sep"></div>
        <div class="sum-row"><span>Subtotal</span><span><?= $fmt($subtotal) ?></span></div>
        <div class="sum-row <?= $shipping_cost === 0 ? 'free' : '' ?>">
          <span>Entrega</span>
          <span><?= $shipping_cost === 0 ? 'Grátis' : $fmt($shipping_cost) ?></span>
        </div>
        <div class="sum-total">
          <span class="sum-total-l">Total</span>
          <span class="sum-total-r"><?= $fmt($total_mzn) ?></span>
        </div>

        <button type="button" class="btn-pay" id="btnPlace" onclick="doOrder()">
          <i class="fa-solid fa-lock"></i>
          <span id="btnTxt">Confirmar Pedido</span>
        </button>

        <div class="trust">
          <div class="trust-row"><i class="fa-solid fa-shield-halved"></i> Pagamento 100% seguro</div>
          <div class="trust-row"><i class="fa-solid fa-rotate-left"></i> Devolução em 30 dias</div>
          <div class="trust-row"><i class="fa-solid fa-headset"></i> Suporte 24/7</div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /co -->

<?php include 'includes/footer.html'; ?>

<!-- ── OVERLAY PROCESSAMENTO ─────────────────────────────────────── -->
<div class="ov" id="ov">
  <div class="ov-box">
    <div class="ov-spin" id="ovSpin"></div>
    <div class="ov-ok"   id="ovOk" style="display:none"><i class="fa-solid fa-check"></i></div>
    <div class="ov-title" id="ovTitle">A processar&hellip;</div>
    <div class="ov-sub"   id="ovSub">Por favor aguarde</div>
    <div class="ov-steps" id="ovSteps">
      <div class="ov-stp" id="os1"><i class="fa-regular fa-circle"></i><span>A validar dados</span></div>
      <div class="ov-stp" id="os2"><i class="fa-regular fa-circle"></i><span>A aguardar pagamento</span></div>
      <div class="ov-stp" id="os3"><i class="fa-regular fa-circle"></i><span>A criar pedido</span></div>
    </div>
    <div class="ov-err" id="ovErr"></div>
    <button class="ov-back" id="ovBack" onclick="closeOv()">&larr; Voltar e corrigir</button>
  </div>
</div>

<!-- ── MODAL LOGIN ───────────────────────────────────────────────── -->
<div class="lm" id="loginModal">
  <div class="lm-box">
    <div class="lm-ico"><i class="fa-solid fa-lock"></i></div>
    <div class="lm-t">Inicia sessão para pagar</div>
    <div class="lm-s">O resumo está guardado. Entra na tua conta para completar a compra com segurança.</div>
    <div class="lm-perks">
      <div class="lm-perk"><i class="fa-solid fa-shield-halved"></i> Pagamento protegido e encriptado</div>
      <div class="lm-perk"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de pedidos guardado</div>
      <div class="lm-perk"><i class="fa-solid fa-truck"></i> Rastreio de entrega em tempo real</div>
    </div>
    <div class="lm-div"></div>
    <div class="lm-btns">
      <a id="lmLg" href="#" class="lm-lg"><i class="fa-solid fa-right-to-bracket"></i> Entrar na minha conta</a>
      <a id="lmRg" href="#" class="lm-reg"><i class="fa-solid fa-user-plus"></i> Criar conta gratuita</a>
    </div>
    <span class="lm-skip" onclick="closeLM()">Continuar a ver o resumo</span>
  </div>
</div>

<!-- ── ECRÃ SUCESSO ──────────────────────────────────────────────── -->
<div id="ss">
  <div class="ss-wrap">
    <div class="ss-ico" id="ssIco"><i class="fa-solid fa-check"></i></div>
    <h1 class="ss-h"  id="ssTitle"></h1>
    <p  class="ss-sub" id="ssSub"></p>
    <div class="ss-card" id="ssNum">
      <div class="ss-ct">Número do Pedido</div>
      <div class="ss-num"><span id="ssOrderNum"></span><button class="cp-btn" onclick="cpOrder()"><i class="fa-regular fa-copy"></i></button></div>
    </div>
    <div class="ss-card" id="ssPay" style="display:none">
      <div class="ss-ct">Pagamento</div>
      <div style="display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px"><span style="color:var(--ink4)">Estado</span><span style="color:var(--ok);font-weight:700"><i class="fa-solid fa-circle-check"></i> Aprovado</span></div>
        <div style="display:flex;justify-content:space-between;font-size:12.5px"><span style="color:var(--ink4)">Transacção</span><span style="font-family:var(--mono);font-size:11px" id="ssTxn"></span></div>
        <div style="display:flex;justify-content:space-between;font-size:12.5px"><span style="color:var(--ink4)">Método</span><span style="font-weight:700" id="ssMth"></span></div>
      </div>
    </div>
    <div class="ss-card" id="ssSteps">
      <div class="ss-ct">Próximos Passos</div>
      <div class="ss-steps" id="ssStepsList"></div>
    </div>
    <div class="ss-btns" id="ssBtns">
      <a href="pages/person/index.php" class="ss-b1"><i class="fa-solid fa-list-check"></i> Ver Pedidos</a>
      <a href="shopping.php"           class="ss-b2"><i class="fa-solid fa-border-all"></i> Continuar</a>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════ -->
<script>
(function () {
'use strict';

/* ── Dados PHP → JS ─────────────────────────────────────────── */
const IS_LOGGED   = <?= $user_logged_in ? 'true' : 'false' ?>;
const BUY_NOW_ID  = <?= $buy_now_id ?>;
const BUY_NOW_QTY = <?= $buy_now_qty ?>;
const TOTAL_MZN   = <?= $total_mzn ?>;
const USER_PHONE  = <?= json_encode($phone_suffix) ?>;
let   CSRF        = <?= json_encode($_SESSION['csrf_token']) ?>;
const RETURN_URL  = <?= json_encode($return_url) ?>;
const LOGIN_BASE  = 'registration/login/login.php?redirect=';
const REG_BASE    = 'registration/register/painel_cadastro.php?redirect=';
<?php
$base     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$ajaxUrl  = $base . '/ajax/ajax_checkout.php';
$pollUrl  = $base . '/ajax/ajax_payment_status.php';
?>
const AJAX_URL = <?= json_encode($ajaxUrl) ?>;
const POLL_URL = <?= json_encode($pollUrl) ?>;

/* ── Estado ─────────────────────────────────────────────────── */
let curPay       = 'mpesa';
let flipped      = false;
let pollInterval = null;
let pollSeconds  = 0;
const pollMax    = 120;
let currentConvId   = '';
let currentPayToken = '';
let timerInterval   = null;

/* ══════════════════════════════════════════════════════════════
   TABS DE FILTRO
══════════════════════════════════════════════════════════════ */
window.filterPM = function (cat, btn) {
  document.querySelectorAll('.pm-tab').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  document.querySelectorAll('.pm-tile').forEach(el => {
    const show = cat === 'all' || el.dataset.cat === cat;
    el.style.display = show ? '' : 'none';
    if (show && el.classList.contains('pm-full'))
      el.style.gridColumn = (cat === 'bank' || cat === 'all') ? '' : 'span 1';
  });
};

/* ══════════════════════════════════════════════════════════════
   SELECIONAR MÉTODO
══════════════════════════════════════════════════════════════ */
window.onPay = function (method) {
  curPay = method;
  ['mpesa','emola','card','bank'].forEach(p => {
    document.getElementById('pan-' + p)?.classList.remove('on');
  });
  const target = (method === 'visa' || method === 'mastercard') ? 'card'
               : (method === 'manual') ? 'bank'
               : method;
  document.getElementById('pan-' + target)?.classList.add('on');

  const isMM   = method === 'mpesa' || method === 'emola';
  const isCard = method === 'visa'  || method === 'mastercard';
  const btnTxt = document.getElementById('btnTxt');
  if (btnTxt) btnTxt.textContent = isCard ? 'Pagar com Cartão'
                                 : isMM   ? 'Enviar USSD Push'
                                 :          'Confirmar Pedido';
};
onPay('mpesa');

/* ── Formatar telefone ──────────────────────────────────────── */
window.fmtPhone = function (inp) {
  let v = inp.value.replace(/\D/g, '');
  if (v.startsWith('258')) v = v.slice(3);
  inp.value = v.slice(0, 9);
};

/* ══════════════════════════════════════════════════════════════
   CARTÃO 3D
══════════════════════════════════════════════════════════════ */
window.flipCard  = () => { flipped = !flipped; document.getElementById('crd3d')?.classList.toggle('flip', flipped); };
window.flipFront = () => { flipped = false; document.getElementById('crd3d')?.classList.remove('flip'); };
window.flipBack  = () => { flipped = true;  document.getElementById('crd3d')?.classList.add('flip'); };

function luhn(n) {
  let s = 0, alt = false;
  for (let i = n.length - 1; i >= 0; i--) {
    let d = parseInt(n[i]);
    if (alt) { d *= 2; if (d > 9) d -= 9; }
    s += d; alt = !alt;
  }
  return n.length >= 13 && s % 10 === 0;
}
function detectBrand(n) {
  if (/^4/.test(n)) return 'visa';
  if (/^5[1-5]/.test(n) || /^2[2-7]/.test(n)) return 'mastercard';
  return '';
}

window.onCNum = function (inp) {
  const raw = inp.value.replace(/\D/g, '').slice(0, 16);
  inp.value = raw.replace(/(.{4})/g, '$1 ').trim();
  const pad  = (raw + '................').slice(0, 16);
  const disp = pad.replace(/(.{4})/g, '$1 ').trim().replace(/\./g, '•');
  const el = document.getElementById('crdNum'); if (el) el.textContent = disp;
  const brand  = detectBrand(raw);
  const logo   = document.getElementById('crdLogo');
  const face   = document.getElementById('crdFace');
  if (logo) {
    if (brand === 'visa')       { logo.innerHTML = '<span class="visa-logo">VISA</span>'; face && (face.className = 'crd-face crd-fr'); }
    else if (brand === 'mastercard') { logo.innerHTML = '<div class="mc-logo"><span></span><span></span></div>'; face && (face.className = 'crd-face crd-fr mc-theme'); }
    else                        { logo.innerHTML = '<i class="fa-solid fa-credit-card" style="font-size:16px;opacity:.3"></i>'; }
  }
  if (raw.length === 16) { inp.classList.toggle('ok', luhn(raw)); inp.classList.toggle('err', !luhn(raw)); }
  else inp.classList.remove('ok','err');
};
window.onCName = function (inp) {
  inp.value = inp.value.toUpperCase().replace(/[^A-Z\s]/g, '');
  const el = document.getElementById('crdName'); if (el) el.textContent = inp.value || 'NOME NO CARTÃO';
};
window.onCExp = function (inp) {
  let v = inp.value.replace(/\D/g, '');
  if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2, 4);
  inp.value = v;
  const el = document.getElementById('crdExp'); if (el) el.textContent = v || 'MM/AA';
  if (v.length === 5) {
    const [m, y] = v.split('/');
    const exp = new Date(2000 + parseInt(y), parseInt(m) - 1 + 1, 1);
    inp.classList.toggle('ok', exp > new Date()); inp.classList.toggle('err', exp <= new Date());
  } else inp.classList.remove('ok','err');
};
window.onCCvv = function (inp) {
  inp.value = inp.value.replace(/\D/g, '').slice(0, 4);
  const el = document.getElementById('crdCvv'); if (el) el.textContent = inp.value.replace(/./g, '•') || '•••';
  if (inp.value.length >= 3) inp.classList.add('ok'); else inp.classList.remove('ok');
};

/* ══════════════════════════════════════════════════════════════
   MODAL LOGIN
══════════════════════════════════════════════════════════════ */
function openLoginModal() {
  const enc = encodeURIComponent(RETURN_URL);
  document.getElementById('lmLg').href = LOGIN_BASE + enc;
  document.getElementById('lmRg').href = REG_BASE   + enc;
  document.getElementById('loginModal').classList.add('on');
  document.body.style.overflow = 'hidden';
}
window.closeLM = function () {
  document.getElementById('loginModal').classList.remove('on');
  document.body.style.overflow = '';
};
document.getElementById('loginModal').addEventListener('click', function (e) {
  if (e.target === this) closeLM();
});

/* ══════════════════════════════════════════════════════════════
   OVERLAY
══════════════════════════════════════════════════════════════ */
const ovEl = document.getElementById('ov');
const ovStps = [1,2,3].map(n => document.getElementById('os' + n));

function showOv(title, sub) {
  document.getElementById('ovSpin').style.display = '';
  document.getElementById('ovOk').style.display   = 'none';
  document.getElementById('ovTitle').textContent  = title || 'A processar…';
  document.getElementById('ovSub').textContent    = sub   || 'Por favor aguarde';
  document.getElementById('ovSteps').style.display = '';
  document.getElementById('ovErr').style.display  = 'none';
  document.getElementById('ovBack').style.display = 'none';
  ovStps.forEach(el => {
    if (!el) return;
    el.classList.remove('act','dn');
    el.querySelector('i').className = 'fa-regular fa-circle';
  });
  ovEl.classList.add('on'); document.body.style.overflow = 'hidden';
}
function hideOv() { ovEl.classList.remove('on'); document.body.style.overflow = ''; }
window.closeOv = function () { hideOv(); document.getElementById('btnPlace').disabled = false; };

function setStep(n) {
  ovStps.forEach((el, i) => {
    if (!el) return;
    if (i + 1 < n)  { el.classList.remove('act'); el.classList.add('dn'); el.querySelector('i').className = 'fa-solid fa-check'; el.style.color = 'var(--ok)'; }
    else if (i + 1 === n) { el.classList.add('act'); el.querySelector('i').className = 'fa-solid fa-circle-dot'; el.style.color = ''; }
    else            { el.classList.remove('act','dn'); el.querySelector('i').className = 'fa-regular fa-circle'; el.style.color = ''; }
  });
}
function showOvErr(msg) {
  document.getElementById('ovSpin').style.display = 'none';
  document.getElementById('ovSteps').style.display = 'none';
  const e = document.getElementById('ovErr'); e.style.display = ''; e.textContent = msg;
  document.getElementById('ovBack').style.display = '';
  document.getElementById('ovTitle').textContent = 'Ocorreu um erro';
  document.getElementById('ovSub').textContent   = '';
}

/* ── Status Mobile Money ─────────────────────────────────────── */
function showMmSt(method, type, title, msg) {
  const el = document.getElementById('st-' + method);
  if (!el) return;
  el.className = 'mm-st on ' + (type === 'waiting' ? 'wait' : type === 'polling' ? 'poll' : type === 'success' ? 'ok' : 'fail');
  const ico  = el.querySelector('i');
  const txt  = el.querySelector('.mm-st-txt');
  if (ico) ico.className = type === 'waiting' ? 'fa-solid fa-circle-notch fa-spin' : type === 'polling' ? 'fa-solid fa-circle-notch fa-spin' : type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark';
  if (txt) txt.innerHTML = `<b>${escH(title)}</b>${escH(msg)}`;
}

/* ── Timer ─────────────────────────────────────────────────────── */
function startTimer(secs) {
  stopTimer();
  let rem = secs;
  timerInterval = setInterval(() => { rem--; if (rem <= 0) stopTimer(); }, 1000);
}
function stopTimer() { if (timerInterval) { clearInterval(timerInterval); timerInterval = null; } }

/* ══════════════════════════════════════════════════════════════
   CONFIRMAR PEDIDO
══════════════════════════════════════════════════════════════ */
window.doOrder = async function () {
  if (!IS_LOGGED) { openLoginModal(); return; }

  const btn = document.getElementById('btnPlace');
  btn.disabled = true;
  clearFErr();

  const ship_name    = (document.getElementById('sName')?.value    || '').trim();
  const ship_phone   = (document.getElementById('sPhone')?.value   || '').trim();
  const ship_address = (document.getElementById('sAddress')?.value || '').trim();
  const ship_city    = (document.getElementById('sCity')?.value    || '').trim();
  const ship_state   = (document.getElementById('sState')?.value   || '').trim();
  const notes        = (document.getElementById('sNotes')?.value   || '').trim();

  if (!ship_name)    { showFErr('Nome de entrega é obrigatório.'); btn.disabled = false; return; }
  if (!ship_phone)   { showFErr('Telefone de entrega é obrigatório.'); btn.disabled = false; return; }
  if (!ship_address) { showFErr('Endereço é obrigatório.'); btn.disabled = false; return; }
  if (!ship_city)    { showFErr('Cidade é obrigatória.'); btn.disabled = false; return; }

  const isMM   = curPay === 'mpesa' || curPay === 'emola';
  const isCard = curPay === 'visa'  || curPay === 'mastercard';

  if (isMM) {
    const phoneInp = document.getElementById(curPay + '-phone');
    const rawPhone = (phoneInp?.value || '').replace(/\D/g, '');
    if (rawPhone.length < 9 || !['84','85','86','87'].some(p => rawPhone.startsWith(p))) {
      phoneInp?.classList.add('err');
      showFErr('Número de telemóvel inválido para ' + curPay.toUpperCase() + '.');
      btn.disabled = false; return;
    }
    phoneInp?.classList.remove('err');
    await initiateMM(curPay, '258' + rawPhone, { ship_name, ship_phone, ship_address, ship_city, ship_state, notes });
    return;
  }

  if (isCard) {
    const numRaw = document.getElementById('cNumber')?.value?.replace(/\D/g,'') || '';
    if (!luhn(numRaw)) { document.getElementById('cNumber')?.classList.add('err'); btn.disabled = false; return; }
    showOv('A processar cartão', 'Por favor aguarde, não feche esta janela.');
    setStep(1); await sleep(300); setStep(2);
    await submitBackend({ pay_method: curPay, card_number: document.getElementById('cNumber')?.value || '', card_name: document.getElementById('cName2')?.value || '', card_expiry: document.getElementById('cExp2')?.value || '', card_cvv: document.getElementById('cCvv2')?.value || '', ship_name, ship_phone, ship_address, ship_city, ship_state, notes });
    return;
  }

  showOv('A registar pedido', 'Por favor aguarde…');
  setStep(1); await sleep(200); setStep(2);
  await submitBackend({ pay_method: 'manual', ship_name, ship_phone, ship_address, ship_city, ship_state, notes });
};

/* ── Mobile Money ───────────────────────────────────────────── */
async function initiateMM(method, phone, formData) {
  showMmSt(method, 'waiting', 'A enviar pedido USSD…', 'Receberá uma notificação no ' + phone + ' para confirmar.');
  const btn = document.getElementById('btnPlace');
  const fd  = new FormData();
  fd.set('action','initiate_mm'); fd.set('csrf_token',CSRF); fd.set('pay_method',method);
  fd.set('phone',phone); fd.set('amount',TOTAL_MZN); fd.set('order_ref','VSG-'+Date.now());
  fd.set('ship_name',formData.ship_name); fd.set('ship_phone',formData.ship_phone);
  fd.set('ship_address',formData.ship_address); fd.set('ship_city',formData.ship_city);
  fd.set('ship_state',formData.ship_state||''); fd.set('notes',formData.notes||'');
  fd.set('buy_now_id',BUY_NOW_ID); fd.set('buy_now_qty',BUY_NOW_QTY);
  let resp;
  try {
    const r = await fetch(AJAX_URL, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    resp = await r.json();
  } catch (e) { showMmSt(method,'fail','Erro de ligação',e.message); btn.disabled=false; return; }
  if (!resp.ok) { showMmSt(method,'fail','Erro',resp.msg||'Ocorreu um erro.'); btn.disabled=false; return; }
  currentConvId   = resp.conversation_id;
  currentPayToken = resp.pay_token;
  showMmSt(method,'polling','⏳ Aguardando confirmação PIN…','Aprove o pagamento de MT '+TOTAL_MZN.toLocaleString('pt-MZ',{minimumFractionDigits:2})+' no seu telemóvel.');
  startTimer(pollMax);
  startPolling(method, currentConvId, currentPayToken, formData);
}

/* ── Polling ─────────────────────────────────────────────────── */
function startPolling(method, convId, payToken, formData) {
  pollSeconds = 0;
  if (pollInterval) clearInterval(pollInterval);
  pollInterval = setInterval(async () => {
    pollSeconds += 5;
    if (pollSeconds >= pollMax) {
      clearInterval(pollInterval); stopTimer();
      showMmSt(method,'fail','Tempo expirado','O utilizador não confirmou a tempo. Tente novamente.');
      document.getElementById('btnPlace').disabled = false; return;
    }
    try {
      const r = await fetch(POLL_URL+'?conv_id='+encodeURIComponent(convId)+'&method='+method+'&token='+encodeURIComponent(payToken), { headers:{'X-Requested-With':'XMLHttpRequest'} });
      if (!r.ok) throw new Error('HTTP '+r.status);
      const d = await r.json();
      if (d.status === 'confirmed') {
        clearInterval(pollInterval); stopTimer();
        showMmSt(method,'ok','Pagamento confirmado!','O seu pagamento foi aprovado com sucesso.');
        showOv('A criar pedido…','O pagamento foi confirmado. A finalizar…'); setStep(3);
        await submitBackend({ pay_method:method, phone:convId, pay_token:payToken, mm_confirmed:'1', ...formData });
      } else if (d.status === 'failed' || d.status === 'cancelled') {
        clearInterval(pollInterval); stopTimer();
        showMmSt(method,'fail','Pagamento recusado',d.msg||'O utilizador cancelou ou PIN incorrecto.');
        document.getElementById('btnPlace').disabled = false;
      }
    } catch (e) { showMmSt(method,'polling','⏳ Aguardando…','Rede instável. A tentar novamente…'); }
  }, 5000);
}

/* ── Submeter ao backend ─────────────────────────────────────── */
async function submitBackend(extra) {
  const btn = document.getElementById('btnPlace');
  const fd  = new FormData();
  fd.set('action','create_order'); fd.set('csrf_token',CSRF);
  fd.set('buy_now_id',BUY_NOW_ID); fd.set('buy_now_qty',BUY_NOW_QTY);
  Object.entries(extra).forEach(([k,v]) => fd.set(k,v));
  let data;
  try {
    const r    = await fetch(AJAX_URL, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const text = await r.text();
    try { data = JSON.parse(text); } catch (_) { throw new Error('Resposta inválida: ' + text.slice(0,120)); }
  } catch (e) { showOvErr('Erro de ligação: ' + e.message); btn.disabled = false; return; }
  if (data.new_csrf) CSRF = data.new_csrf;
  if (data.ok) {
    ovStps.forEach(el => { if (!el) return; el.classList.remove('act'); el.classList.add('dn'); el.querySelector('i').className = 'fa-solid fa-check'; });
    document.getElementById('ovSpin').style.display = 'none';
    document.getElementById('ovOk').style.display   = 'flex';
    document.getElementById('ovTitle').textContent  = 'Concluído!';
    document.getElementById('ovSub').textContent    = '';
    await sleep(700);
    showSuccess(data);
  } else {
    showOvErr(data.msg || 'Ocorreu um erro. Tente novamente.');
    btn.disabled = false;
  }
}

/* ── Ecrã de sucesso ─────────────────────────────────────────── */
function showSuccess(data) {
  if (ovEl) { ovEl.style.transition='opacity .4s'; ovEl.style.opacity='0'; setTimeout(()=>hideOv(),400); }
  const screen = document.getElementById('ss'); if (!screen) return;
  const isMM     = data.pay_method === 'mpesa' || data.pay_method === 'emola';
  const isCard   = data.pay_method === 'visa'  || data.pay_method === 'mastercard';
  const isManual = data.pay_method === 'manual';
  document.getElementById('ssOrderNum').textContent = data.order_number;
  document.getElementById('ssTitle').textContent    = (isCard||isMM) ? 'Pagamento Confirmado!' : 'Pedido Registado!';
  document.getElementById('ssSub').textContent      = isCard  ? 'O seu pagamento foi processado com sucesso.'
                                                     : isMM   ? 'Pagamento '+data.pay_method.toUpperCase()+' confirmado.'
                                                     : 'Efectue a transferência para confirmar o pedido.';
  if ((isCard || isMM) && data.transaction_id) {
    document.getElementById('ssPay').style.display = 'block';
    document.getElementById('ssTxn').textContent   = data.transaction_id;
    document.getElementById('ssMth').textContent   = data.pay_method.toUpperCase();
  }
  const steps = isManual
    ? [['envelope','Receberá confirmação por email.'],['building-columns','Efectue a transferência com o número do pedido.'],['circle-check','Após confirmarmos, o produto será enviado.']]
    : isMM
    ? [['circle-check','Pagamento '+data.pay_method.toUpperCase()+' confirmado.'],['truck','Enviado em 1-3 dias úteis.'],['envelope','Confirmação por email enviada.']]
    : [['circle-check','Pagamento aprovado.'],['truck','Enviado em 1-3 dias úteis.'],['envelope','Confirmação por email enviada.']];
  document.getElementById('ssStepsList').innerHTML = steps.map(([icon, text]) =>
    `<div class="ss-stp"><i class="fa-solid fa-${icon}"></i><span class="ss-stp-t">${escH(text)}</span></div>`
  ).join('');
  screen.style.display = 'block';
  document.body.style.overflow = '';
  if (ovEl) { ovEl.style.opacity = ''; ovEl.style.transition = ''; }
  const ease = 'cubic-bezier(.16,1,.3,1)';
  [['ssIco',50],['ssTitle',230],['ssSub',360],['ssNum',480],['ssPay',580],['ssSteps',660],['ssBtns',770]].forEach(([id,delay]) => {
    const el = document.getElementById(id);
    if (!el || el.style.display === 'none') return;
    setTimeout(() => { el.style.transition = `opacity .45s ${ease},transform .45s ${ease}`; el.style.opacity='1'; el.style.transform='none'; }, delay);
  });
}

/* ── Helpers ─────────────────────────────────────────────────── */
function escH(t) { return String(t??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function showFErr(msg) {
  const e = document.getElementById('formErrors');
  if (e) { e.innerHTML = `<div class="ferr"><i class="fa-solid fa-exclamation-circle"></i><span>${escH(msg)}</span></div>`; }
  window.scrollTo({ top:0, behavior:'smooth' });
}
function clearFErr() { const e = document.getElementById('formErrors'); if (e) e.innerHTML = ''; }

window.cpOrder = function () {
  const num = document.getElementById('ssOrderNum')?.textContent;
  if (num) navigator.clipboard?.writeText(num).then(() => {
    document.querySelectorAll('.cp-btn').forEach(b => {
      b.innerHTML = '<i class="fa-solid fa-check"></i>';
      setTimeout(() => b.innerHTML = '<i class="fa-regular fa-copy"></i>', 2000);
    });
  });
};

})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>