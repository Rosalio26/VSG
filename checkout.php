<?php
/*
 * checkout.php — VSG Marketplace
 *
 * REGRA DE ACESSO:
 *   Visitantes acedem livremente, vêem resumo e preenchem entrega.
 *   Login APENAS solicitado ao clicar "Confirmar Pedido" (método de pagamento).
 *   Após autenticação o utilizador regressa ao mesmo checkout com os mesmos params.
 *
 * shopping.php: apenas contas tipo 'person' e 'employee' podem aceder (logadas).
 *               Empresas são redirecionadas para index.php.
 */

require_once __DIR__ . '/registration/includes/device.php';
require_once __DIR__ . '/registration/includes/rate_limit.php';
require_once __DIR__ . '/registration/includes/errors.php';
require_once __DIR__ . '/registration/includes/db.php';
require_once __DIR__ . '/includes/currency/currency_bootstrap.php';

ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function imgUrl($v, $name='P') {
    if (empty($v)) return 'https://ui-avatars.com/api/?name='.urlencode($name).'&size=200&background=00b96b&color=fff&font-size=0.1';
    if (str_starts_with($v,'http') || str_starts_with($v,'uploads/')) return $v;
    if (str_starts_with($v,'products/')) return 'uploads/'.$v;
    return 'uploads/products/'.$v;
}

// ── Moeda ─────────────────────────────────────────────────────────
$currency_map = [
    'MZ'=>['symbol'=>'MT','rate'=>1],   'BR'=>['symbol'=>'R$','rate'=>0.062],
    'PT'=>['symbol'=>'€','rate'=>0.015],'US'=>['symbol'=>'$','rate'=>0.016],
    'GB'=>['symbol'=>'£','rate'=>0.013],'ZA'=>['symbol'=>'R','rate'=>0.29],
    'AO'=>['symbol'=>'Kz','rate'=>15.2],
];
$cc  = strtoupper($_SESSION['auth']['country_code'] ?? 'MZ');
$cur = $currency_map[$cc] ?? ['symbol'=>'MT','rate'=>1];
$sym = $cur['symbol']; $rate = $cur['rate'];
$fmt = fn($v) => $sym.' '.number_format($v*$rate,2,',','.');

// ── Auth — SEM redireccionar visitantes obrigatoriamente ──────────
$user_logged_in = isset($_SESSION['auth']['user_id']);
$user_id        = $user_logged_in ? (int)$_SESSION['auth']['user_id'] : 0;
$user_name      = $user_logged_in ? ($_SESSION['auth']['nome']     ?? 'Utilizador') : '';
$user_avatar    = $user_logged_in ? ($_SESSION['auth']['avatar']   ?? null) : null;
$user_phone     = $user_logged_in ? ($_SESSION['auth']['telefone'] ?? '') : '';

// ── Parâmetros ────────────────────────────────────────────────────
$buy_now_id  = (int)($_GET['buy_now'] ?? 0);
$buy_now_qty = max(1,(int)($_GET['qty'] ?? 1));

// URL de retorno após login (preserva query string completa)
$return_url = 'checkout.php?' . http_build_query($_GET);

// ── Carregar itens ────────────────────────────────────────────────
$checkout_items = [];
$subtotal       = 0;

if ($buy_now_id > 0) {
    // Buy Now — disponível para visitantes sem login
    $st = $mysqli->prepare("
        SELECT p.id AS product_id,p.nome AS product_name,p.preco AS item_price,
               p.currency,p.stock,p.imagem,p.image_path1,p.user_id AS company_id,
               COALESCE(c.name,'Geral') AS category_name,
               COALESCE(u.nome,'') AS company_name
        FROM products p
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN users u ON u.id=p.user_id
        WHERE p.id=? AND p.status='ativo' AND p.deleted_at IS NULL LIMIT 1
    ");
    $st->bind_param('i',$buy_now_id); $st->execute();
    $prod = $st->get_result()->fetch_assoc(); $st->close();
    if (!$prod) { header('Location: shopping.php'); exit; }
    if ($buy_now_qty > $prod['stock']) $buy_now_qty = $prod['stock'];
    $img = imgUrl($prod['image_path1']?:$prod['imagem'],$prod['product_name']);
    $checkout_items[] = [
        'item_id'=>0,'product_id'=>$prod['product_id'],'product_name'=>$prod['product_name'],
        'company_id'=>$prod['company_id'],'company_name'=>$prod['company_name'],
        'category_name'=>$prod['category_name'],'item_price'=>$prod['item_price'],
        'quantity'=>$buy_now_qty,'stock'=>$prod['stock'],'img_url'=>$img,
        'line_total'=>(float)$prod['item_price']*$buy_now_qty,'currency'=>$prod['currency'],
    ];
    $subtotal = (float)$prod['item_price'] * $buy_now_qty;
} elseif ($user_logged_in) {
    // Carrinho — requer login (se não tem login, redireciona para shopping)
    $st = $mysqli->prepare("
        SELECT ci.id AS item_id,ci.quantity,ci.price AS item_price,ci.currency AS item_currency,
               ci.company_id,p.id AS product_id,p.nome AS product_name,p.stock,
               p.imagem,p.image_path1,COALESCE(c.name,'Geral') AS category_name,
               COALESCE(u.nome,'') AS company_name
        FROM shopping_carts sc
        INNER JOIN cart_items ci ON ci.cart_id=sc.id
        INNER JOIN products p ON p.id=ci.product_id
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN users u ON u.id=ci.company_id
        WHERE sc.user_id=? AND sc.status='active'
        ORDER BY ci.created_at DESC
    ");
    $st->bind_param('i',$user_id); $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    if (empty($rows)) { header('Location: cart.php'); exit; }
    foreach ($rows as $row) {
        $img  = imgUrl($row['image_path1']?:$row['imagem'],$row['product_name']);
        $line = (float)$row['item_price']*(int)$row['quantity'];
        $checkout_items[] = array_merge($row,['img_url'=>$img,'line_total'=>$line]);
        $subtotal += $line;
    }
} else {
    // Visitante sem buy_now → não há o que mostrar, redirecionar para a loja
    header('Location: shopping.php'); exit;
}

$shipping_cost = $subtotal >= 2500 ? 0 : 150;
$total_mzn     = $subtotal + $shipping_cost;

// ── Dados pré-preenchimento (só se logado) ────────────────────────
$user_data = [];
if ($user_logged_in) {
    $st = $mysqli->prepare("SELECT nome,telefone,email,address,city,state FROM users WHERE id=? LIMIT 1");
    $st->bind_param('i',$user_id); $st->execute();
    $user_data = $st->get_result()->fetch_assoc() ?? []; $st->close();
}
$prefill_phone      = $user_data['telefone'] ?? $user_phone ?? '';
$prefill_phone_norm = preg_replace('/\D/','',$prefill_phone);
if (strlen($prefill_phone_norm)===9) $prefill_phone_norm = '258'.$prefill_phone_norm;
$phone_suffix = strlen($prefill_phone_norm) > 3 ? substr($prefill_phone_norm,3) : '';
?>
<!DOCTYPE html>
<html lang="pt-MZ">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finalizar Compra — VSG Marketplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="shortcut icon" href="sources/img/logo_small_gr.png" type="image/x-icon">
<style>
:root {
  --gr:#00b96b;--gr-d:#009956;--gr-l:#e6faf2;--gr-ring:#6ee7b7;
  --ink:#111827;--ink-2:#4b5563;--ink-3:#9ca3af;
  --bg:#f3f4f6;--sur:#fff;--bdr:#e5e7eb;--bdr-2:#f0f2f4;
  --red:#ef4444;--amber:#f59e0b;--blue:#2563eb;
  --mpesa:#d62b2b;--emola:#e67e22;
  --sh-sm:0 1px 3px rgba(0,0,0,.1);--sh-md:0 4px 16px rgba(0,0,0,.1);
  --r6:6px;--r8:8px;--r10:10px;--r12:12px;--r16:16px;--r99:999px;
  --ease:cubic-bezier(.16,1,.3,1);
  --font:'Plus Jakarta Sans',-apple-system,sans-serif;--hdr-h:56px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);font-size:14px;line-height:1.5;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
img{display:block;max-width:100%}
button{font-family:var(--font);cursor:pointer;border:none;background:none}
input,select,textarea{font-family:var(--font)}

/* Strip */
.top-strip{background:var(--ink);color:rgba(255,255,255,.75);font-size:11.5px;line-height:30px}
.ts-in{display:flex;justify-content:space-between;align-items:center;height:30px;max-width:1360px;margin:0 auto;padding:0 20px}
.ts-link{display:inline-flex;align-items:center;gap:4px;color:rgba(255,255,255,.75);transition:color .15s}
.ts-link:hover{color:var(--gr)}

/* Header */
.main-header{background:var(--sur);border-bottom:1px solid var(--bdr);position:sticky;top:0;z-index:500;height:var(--hdr-h)}
.hdr-in{display:flex;align-items:center;gap:12px;height:100%;max-width:1360px;margin:0 auto;padding:0 20px}
.logo{display:flex;align-items:center;gap:6px;flex-shrink:0;font-size:18px;font-weight:800;letter-spacing:-.5px}
.logo:hover{opacity:.8}
.logo-icon{width:32px;height:32px;background:var(--gr);border-radius:var(--r8);display:grid;place-items:center;font-size:15px;color:#fff}
.logo-text em{color:var(--gr);font-style:normal}
.logo-sub{font-size:10px;font-weight:500;color:var(--ink-3);letter-spacing:.3px;display:block;line-height:1}
.hdr-right{margin-left:auto;display:flex;align-items:center;gap:6px}
.hdr-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:var(--r8);border:1.5px solid var(--bdr);font-size:13px;font-weight:600;color:var(--ink);transition:border-color .15s,background .15s}
.hdr-btn:hover{border-color:var(--gr);background:var(--gr-l)}
.hdr-btn.pri{background:var(--gr);border-color:var(--gr);color:#fff}
.hdr-btn.pri:hover{background:var(--gr-d)}
.hdr-btn img{width:22px;height:22px;border-radius:50%;object-fit:cover}

/* Steps */
.checkout-steps{max-width:1360px;margin:0 auto;padding:16px 20px;display:flex;align-items:center}
.step{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:var(--ink-3)}
.step.active{color:var(--gr)}.step.done{color:var(--ink-2)}
.step-num{width:26px;height:26px;border-radius:50%;border:2px solid currentColor;display:grid;place-items:center;font-size:11px;font-weight:800;flex-shrink:0}
.step.active .step-num{background:var(--gr);border-color:var(--gr);color:#fff}
.step.done .step-num{background:var(--ink-2);border-color:var(--ink-2);color:#fff}
.step-line{flex:1;height:2px;background:var(--bdr);margin:0 8px}
.step-line.done{background:var(--ink-2)}

/* Layout */
.co-wrap{max-width:1360px;margin:0 auto;padding:0 20px 80px;display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
.co-card{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r16);padding:22px;margin-bottom:16px}
.co-card-title{font-size:15px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px;margin-bottom:18px}
.co-card-title i{color:var(--gr)}

/* ── BANNER VISITANTE ── */
.guest-banner{
  display:flex;align-items:flex-start;gap:12px;
  padding:14px 18px;background:#eff6ff;
  border:1.5px solid #bfdbfe;border-radius:var(--r10);
  margin-bottom:16px;font-size:13.5px;color:#1d4ed8;line-height:1.5;
}
.guest-banner i{font-size:18px;flex-shrink:0;margin-top:2px}
.guest-banner strong{font-weight:800}
.guest-banner a{color:#1d4ed8;font-weight:700;text-decoration:underline}
.guest-banner a:hover{color:#1e40af}

/* Form */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.span2{grid-column:span 2}
.form-label{font-size:12px;font-weight:700;color:var(--ink-2);text-transform:uppercase;letter-spacing:.4px}
.form-label span{color:var(--red)}
.form-input,.form-select,.form-textarea{width:100%;padding:10px 12px;border:1.5px solid var(--bdr);border-radius:var(--r8);font-size:14px;color:var(--ink);background:var(--sur);outline:none;transition:border-color .15s,box-shadow .15s}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--gr);box-shadow:0 0 0 3px rgba(0,185,107,.12)}
.form-textarea{resize:vertical;min-height:72px}

/* Pagamento */
.pay-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.pay-option{position:relative;cursor:pointer}
.pay-option input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.pay-card{padding:14px 10px;border:2px solid var(--bdr);border-radius:var(--r10);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;text-align:center;transition:all .15s;min-height:88px}
.pay-option input:checked + .pay-card{border-color:var(--gr);background:var(--gr-l);box-shadow:0 0 0 3px rgba(0,185,107,.15)}
.pay-card:hover{border-color:rgba(0,185,107,.5);background:var(--gr-l)}
.pay-icon{font-size:22px}.pay-name{font-size:11.5px;font-weight:700;color:var(--ink)}.pay-desc{font-size:10px;color:var(--ink-3)}
.pay-option.mpesa .pay-icon{color:var(--mpesa)}.pay-option.emola .pay-icon{color:var(--emola)}
.pay-option.visa .pay-icon{color:#1a1f71}.pay-option.master .pay-icon{color:#eb001b}
.pay-option.manual .pay-icon{color:var(--ink-2)}

/* MM panels */
.mm-panel{display:none;margin-top:4px;padding:18px;background:var(--bg);border-radius:var(--r12);border:1.5px solid var(--bdr);animation:fadeUp .2s var(--ease)}
.mm-panel.show{display:block}
.mm-panel-title{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;margin-bottom:14px}
.mm-panel-title i{font-size:16px}
.mm-panel.mpesa .mm-panel-title i{color:var(--mpesa)}.mm-panel.emola .mm-panel-title i{color:var(--emola)}
.phone-input-wrap{position:relative;display:flex}
.phone-prefix{flex-shrink:0;padding:0 12px;height:44px;background:var(--bdr-2);border:1.5px solid var(--bdr);border-right:none;border-radius:var(--r8) 0 0 var(--r8);display:flex;align-items:center;gap:6px;font-size:13.5px;font-weight:700;color:var(--ink-2)}
.phone-number-inp{flex:1;height:44px;padding:0 14px;border:1.5px solid var(--bdr);border-radius:0 var(--r8) var(--r8) 0;font-size:15px;font-weight:600;color:var(--ink);background:var(--sur);outline:none;transition:border-color .15s,box-shadow .15s;letter-spacing:.5px}
.phone-number-inp:focus{border-color:var(--gr);box-shadow:0 0 0 3px rgba(0,185,107,.12)}
.phone-number-inp.error{border-color:var(--red);box-shadow:0 0 0 3px rgba(239,68,68,.1)}
.phone-hint{font-size:11.5px;color:var(--ink-3);margin-top:6px;display:flex;align-items:center;gap:5px}
.mm-status{display:none;margin-top:16px;padding:14px;border-radius:var(--r10);border:1.5px solid}
.mm-status.waiting{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.mm-status.polling{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.mm-status.success{background:#f0fdf4;border-color:#86efac;color:#166534}
.mm-status.failed{background:#fef2f2;border-color:#fecaca;color:#991b1b}
.mm-status.show{display:flex;gap:10px;align-items:flex-start}
.mm-status-icon{font-size:18px;flex-shrink:0;margin-top:1px}
.mm-status-text{flex:1;font-size:13px;font-weight:500;line-height:1.5}
.mm-status-text strong{display:block;font-weight:800;margin-bottom:2px}
.mm-timer{width:44px;height:44px;flex-shrink:0;border-radius:50%;border:3px solid var(--bdr);display:grid;place-items:center;position:relative;font-size:12px;font-weight:700;color:var(--ink-2)}
.mm-timer-ring{position:absolute;inset:0;border-radius:50%;background:conic-gradient(var(--gr) calc(var(--p,0)*1%), transparent 0);mask: radial-gradient(farthest-side,transparent calc(100% - 3px),#fff calc(100% - 3px));-webkit-mask: radial-gradient(farthest-side,transparent calc(100% - 3px),#fff calc(100% - 3px))}

/* Card 3D */
.card-panel{display:none;margin-top:4px;animation:fadeUp .2s var(--ease)}.card-panel.show{display:block}
.card-scene{width:100%;max-width:360px;height:200px;perspective:1000px;margin:0 auto 20px;cursor:pointer}
.card-3d{width:100%;height:100%;position:relative;transform-style:preserve-3d;transition:transform .6s var(--ease)}
.card-3d.flipped{transform:rotateY(180deg)}
.card-face{position:absolute;inset:0;border-radius:14px;backface-visibility:hidden;-webkit-backface-visibility:hidden;padding:18px 20px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 16px 40px rgba(0,0,0,.22);overflow:hidden}
.card-face::before{content:'';position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.07);top:-80px;right:-70px}
.card-face::after{content:'';position:absolute;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.05);bottom:-50px;left:-30px}
.card-front{background:linear-gradient(135deg,#1a1f71,#2563eb 60%,#1e40af);color:#fff}
.card-front.mc{background:linear-gradient(135deg,#1a1a1a,#2d2d2d 60%,#111);color:#fff}
.card-back{background:linear-gradient(135deg,#1e1e2e,#2d2d4e);color:#fff;transform:rotateY(180deg);justify-content:flex-start}
.card-top{display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1}
.card-chip{width:38px;height:28px;background:linear-gradient(135deg,#d4a843,#f5c842,#c9952e);border-radius:5px;display:grid;place-items:center}
.card-chip-inner{width:22px;height:16px;border:1.5px solid rgba(0,0,0,.25);border-radius:3px;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.15) 3px,rgba(0,0,0,.15) 4px)}
.card-brand-logo{font-size:12px;font-weight:800;letter-spacing:1px;z-index:1}
.visa-logo{font-size:20px;font-style:italic;font-family:serif;letter-spacing:-1px}
.mc-circles{display:flex}.mc-circles span{width:26px;height:26px;border-radius:50%}
.mc-circles span:first-child{background:#eb001b}.mc-circles span:last-child{background:#f79e1b;margin-left:-10px;opacity:.9}
.card-num-disp{font-size:17px;font-weight:700;letter-spacing:2.5px;font-family:'Courier New',monospace;text-align:center;position:relative;z-index:1;text-shadow:0 1px 3px rgba(0,0,0,.3)}
.card-bottom{display:flex;justify-content:space-between;align-items:flex-end;position:relative;z-index:1}
.card-lbl{font-size:8px;text-transform:uppercase;opacity:.6;letter-spacing:.8px;margin-bottom:2px}
.card-holder-name{font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.card-exp-val{font-size:12px;font-weight:700;text-align:right}
.card-stripe{height:40px;background:#000;margin:14px -20px;opacity:.8}
.card-cvv-strip{background:#fff;border-radius:3px;height:32px;display:flex;align-items:center;justify-content:flex-end;padding:0 10px;font-family:'Courier New',monospace;font-size:14px;letter-spacing:3px;color:#111;font-weight:700;margin-top:14px}
.card-form{display:flex;flex-direction:column;gap:12px}
.card-input-wrap{position:relative}
.card-input{width:100%;padding:10px 14px 10px 42px;border:1.5px solid var(--bdr);border-radius:var(--r8);font-size:14px;color:var(--ink);background:var(--sur);outline:none;transition:border-color .15s,box-shadow .15s}
.card-input.num{font-family:'Courier New',monospace;font-size:15px;letter-spacing:2px}
.card-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.card-input.err{border-color:var(--red)}.card-input.ok{border-color:#22c55e}
.ci-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:14px;color:var(--ink-3);pointer-events:none}
.ci-brand{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:19px}
.card-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.card-secure{display:flex;align-items:center;gap:7px;padding:9px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--r8);font-size:12px;color:#166534}

/* Manual */
.manual-panel{display:none;margin-top:4px;padding:16px;background:var(--bg);border-radius:var(--r12);border:1.5px dashed var(--bdr);font-size:13px;color:var(--ink-2);line-height:1.7;animation:fadeUp .2s var(--ease)}
.manual-panel.show{display:block}
.ref-box{background:var(--sur);border:1.5px solid var(--gr);border-radius:var(--r8);padding:10px 14px;margin-top:8px;display:flex;align-items:center;justify-content:space-between;font-size:14px;font-weight:800;color:var(--gr);letter-spacing:.5px}

/* Overlay */
.pay-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);place-items:center}
.pay-overlay.show{display:grid}
.po-box{background:#fff;border-radius:20px;padding:32px 36px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:10px;box-shadow:0 30px 80px rgba(0,0,0,.3);min-width:300px;max-width:360px;width:90%}
.po-spinner{width:48px;height:48px;border:4px solid var(--bdr);border-top-color:var(--gr);border-radius:50%;animation:spin .8s linear infinite}
.po-icon-ok{width:48px;height:48px;border-radius:50%;background:var(--gr);color:#fff;display:grid;place-items:center;font-size:20px;animation:pop .4s var(--ease)}
.po-title{font-size:16px;font-weight:800;color:var(--ink)}.po-sub{font-size:12.5px;color:var(--ink-3)}
.po-steps{display:flex;flex-direction:column;gap:6px;margin-top:4px;text-align:left;width:100%}
.po-step{font-size:12.5px;color:var(--ink-3);display:flex;align-items:center;gap:7px;padding:3px 0;transition:color .2s}
.po-step.active{color:var(--blue);font-weight:700}.po-step.done{color:#16a34a;font-weight:700}
.po-step i{width:14px;text-align:center;flex-shrink:0}
.po-err{display:none;margin-top:12px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--r8);color:#991b1b;font-size:13px;text-align:center;max-width:280px}
.po-err-btn{display:none;margin-top:10px;padding:8px 18px;background:#fff;border:1.5px solid var(--bdr);border-radius:var(--r8);font-size:13px;font-weight:600}

/* ═══ MODAL DE LOGIN ═══════════════════════════════════════════════
   Aparece APENAS ao clicar "Confirmar Pedido" sem estar autenticado.
   Não aparece ao entrar no checkout, nem ao navegar na página.
═══════════════════════════════════════════════════════════════════ */
.login-modal{display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);place-items:center}
.login-modal.show{display:grid}
.lm-box{background:var(--sur);border-radius:var(--r16);padding:30px 34px;max-width:420px;width:90%;box-shadow:0 30px 80px rgba(0,0,0,.3);animation:fadeUp .25s var(--ease)}
.lm-header{text-align:center;margin-bottom:18px}
.lm-icon{width:58px;height:58px;background:var(--gr-l);border-radius:50%;border:2px solid var(--gr-ring);display:grid;place-items:center;margin:0 auto 12px;font-size:22px;color:var(--gr)}
.lm-title{font-size:19px;font-weight:800;color:var(--ink);margin-bottom:6px}
.lm-sub{font-size:13px;color:var(--ink-2);line-height:1.55}
.lm-benefits{display:flex;flex-direction:column;gap:9px;margin:16px 0}
.lm-benefit{display:flex;align-items:center;gap:9px;font-size:13px;color:var(--ink-2)}
.lm-benefit i{color:var(--gr);width:16px;flex-shrink:0}
.lm-divider{height:1px;background:var(--bdr);margin:14px 0}
.lm-btns{display:flex;flex-direction:column;gap:10px}
.lm-btn-login{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;background:var(--gr);border-radius:var(--r10);color:#fff;font-size:15px;font-weight:700;transition:background .15s}
.lm-btn-login:hover{background:var(--gr-d)}
.lm-btn-reg{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;border:1.5px solid var(--bdr);border-radius:var(--r10);color:var(--ink-2);font-size:14px;font-weight:600;transition:border-color .15s,color .15s}
.lm-btn-reg:hover{border-color:var(--gr);color:var(--gr)}
.lm-cancel{display:block;text-align:center;font-size:13px;color:var(--ink-3);margin-top:14px;cursor:pointer;transition:color .15s}
.lm-cancel:hover{color:var(--ink)}

/* Resumo */
.order-items{display:flex;flex-direction:column;gap:10px;margin-bottom:14px}
.oi-row{display:flex;align-items:center;gap:10px}
.oi-img{width:46px;height:46px;flex-shrink:0;border-radius:var(--r8);overflow:hidden;border:1px solid var(--bdr-2);background:var(--bg)}
.oi-img img{width:100%;height:100%;object-fit:cover}
.oi-name{flex:1;font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.oi-qty{font-size:11px;color:var(--ink-3)}.oi-price{font-size:13px;font-weight:700;flex-shrink:0}
.sum-sep{height:1px;background:var(--bdr);margin:10px 0}
.sum-row{display:flex;justify-content:space-between;font-size:13.5px;padding:3px 0}
.sum-row.total{padding-top:10px;margin-top:4px;border-top:2px solid var(--bdr)}
.sum-row.total span:first-child{font-size:15px;font-weight:800}
.sum-row.total span:last-child{font-size:1.25rem;font-weight:800;color:var(--gr)}
.sum-free{color:var(--gr);font-weight:700}
.btn-place{width:100%;padding:14px;margin-top:16px;background:var(--gr);border-radius:var(--r10);color:#fff;font-size:15px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s,transform .15s}
.btn-place:hover:not(:disabled){background:var(--gr-d);transform:translateY(-1px)}
.btn-place:disabled{opacity:.5;cursor:not-allowed;transform:none}
.trust-badges{display:flex;flex-direction:column;gap:6px;margin-top:14px}
.trust-badge{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--ink-3)}
.trust-badge i{color:var(--gr)}
.alert-error{display:flex;align-items:flex-start;gap:10px;padding:14px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--r10);margin-bottom:16px;font-size:13.5px;color:#991b1b}

/* Sucesso */
#successScreen{display:none;position:fixed;inset:0;background:#f3f4f6;z-index:10001;overflow-y:auto}
.success-wrap{max-width:520px;margin:0 auto;padding:40px 20px 80px;text-align:center}
.success-icon{width:76px;height:76px;border-radius:50%;background:var(--gr-l);border:3px solid var(--gr);display:grid;place-items:center;margin:0 auto 18px;font-size:30px;color:var(--gr);opacity:0;transform:scale(0)}
.success-title{font-size:1.5rem;font-weight:800;color:var(--ink);margin-bottom:8px;opacity:0;transform:translateY(10px)}
.success-sub{font-size:13.5px;color:var(--ink-3);margin-bottom:22px;opacity:0;transform:translateY(10px)}
.success-card{background:var(--sur);border:1px solid var(--bdr);border-radius:var(--r16);padding:16px;margin-bottom:12px;text-align:left;opacity:0;transform:translateY(10px)}
.success-card-title{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ink-3);margin-bottom:8px}
.success-num{font-size:1.1rem;font-weight:800;color:var(--gr);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.copy-btn{font-size:14px;color:var(--ink-3);cursor:pointer;transition:color .15s;border:none;background:none}
.copy-btn:hover{color:var(--gr)}
.success-steps{display:flex;flex-direction:column;gap:7px}
.success-step{display:flex;align-items:center;gap:9px;padding:7px 10px;background:var(--bg);border-radius:var(--r8)}
.success-step i{color:var(--gr);flex-shrink:0;width:15px}.success-step-text{font-size:13px;color:var(--ink-2)}
.success-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:8px;opacity:0;transform:translateY(10px)}
.btn-orders{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:var(--gr);border-radius:var(--r10);color:#fff;font-weight:700;font-size:13.5px;transition:background .15s}
.btn-orders:hover{background:var(--gr-d)}
.btn-shop2{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border:1.5px solid var(--bdr);border-radius:var(--r10);color:var(--ink-2);font-weight:600;font-size:13.5px;transition:all .15s}
.btn-shop2:hover{border-color:var(--gr);color:var(--gr)}

@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

@media(max-width:900px){.co-wrap{grid-template-columns:1fr}}
@media(max-width:600px){
  .co-wrap{padding:0 12px 80px}.form-grid{grid-template-columns:1fr}.form-group.span2{grid-column:span 1}
  .pay-grid{grid-template-columns:repeat(3,1fr)}.logo-sub{display:none}
  .checkout-steps span{display:none}.step{gap:4px}.lm-box{padding:22px 18px}
}
</style>
</head>
<body>

<div class="top-strip">
  <div class="ts-in">
    <span class="ts-link"><i class="fa-solid fa-lock"></i> Compra Segura SSL</span>
    <span class="ts-link"><i class="fa-solid fa-leaf"></i> VSG Marketplace</span>
  </div>
</div>

<header class="main-header">
  <div class="hdr-in">
    <a href="index.php" class="logo">
      <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
      <div><div class="logo-text">VSG<em>&bull;</em>MARKET</div><span class="logo-sub">MARKETPLACE</span></div>
    </a>
    <div class="hdr-right">
      <?php if ($user_logged_in): ?>
        <a href="pages/person/index.php" class="hdr-btn">
          <?php if ($user_avatar): ?><img src="<?= esc($user_avatar) ?>" alt=""><?php else: ?><i class="fa-solid fa-circle-user"></i><?php endif; ?>
          <span><?= esc($user_name) ?></span>
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

<div class="checkout-steps">
  <div class="step done"><div class="step-num"><i class="fa-solid fa-check" style="font-size:10px"></i></div><span>Carrinho</span></div>
  <div class="step-line done"></div>
  <div class="step active"><div class="step-num">2</div><span>Entrega &amp; Pagamento</span></div>
  <div class="step-line"></div>
  <div class="step"><div class="step-num">3</div><span>Confirmação</span></div>
</div>

<div class="co-wrap">
  <div>
    <div id="formErrors"></div>

    <?php if (!$user_logged_in): ?>
    <!-- Banner visitante: visible only to non-logged users -->
    <div class="guest-banner">
      <i class="fa-solid fa-circle-info"></i>
      <div>
        Podes ver o resumo e preencher os dados livremente.
        O login é pedido <strong>apenas ao confirmar o pagamento</strong>.
        &mdash; <a href="registration/login/login.php?redirect=<?= urlencode($return_url) ?>">Entrar agora</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Entrega -->
    <div class="co-card">
      <div class="co-card-title"><i class="fa-solid fa-location-dot"></i> Informações de Entrega</div>
      <div class="form-grid" id="deliveryForm">
        <div class="form-group">
          <label class="form-label">Nome completo <span>*</span></label>
          <input type="text" id="shipName" class="form-input" required value="<?= esc($user_data['nome'] ?? '') ?>" placeholder="Nome de quem recebe">
        </div>
        <div class="form-group">
          <label class="form-label">Telefone <span>*</span></label>
          <input type="tel" id="shipPhone" class="form-input" required value="<?= esc($user_data['telefone'] ?? '') ?>" placeholder="+258 84 000 0000">
        </div>
        <div class="form-group span2">
          <label class="form-label">Endereço <span>*</span></label>
          <input type="text" id="shipAddress" class="form-input" required value="<?= esc($user_data['address'] ?? '') ?>" placeholder="Rua, número, bairro">
        </div>
        <div class="form-group">
          <label class="form-label">Cidade <span>*</span></label>
          <input type="text" id="shipCity" class="form-input" required value="<?= esc($user_data['city'] ?? '') ?>" placeholder="Ex: Maputo">
        </div>
        <div class="form-group">
          <label class="form-label">Província</label>
          <input type="text" id="shipState" class="form-input" value="<?= esc($user_data['state'] ?? '') ?>" placeholder="Ex: Maputo">
        </div>
        <div class="form-group span2">
          <label class="form-label">Notas (opcional)</label>
          <textarea id="orderNotes" class="form-textarea" placeholder="Referências, instruções especiais..."></textarea>
        </div>
      </div>
    </div>

    <!-- Pagamento -->
    <div class="co-card">
      <div class="co-card-title"><i class="fa-solid fa-credit-card"></i> Método de Pagamento</div>
      <div class="pay-grid">
        <label class="pay-option mpesa"><input type="radio" name="pay_method" value="mpesa" checked onchange="selectPay('mpesa')"><div class="pay-card"><i class="fa-solid fa-mobile-screen pay-icon"></i><div class="pay-name">M-Pesa</div><div class="pay-desc">Vodacom &middot; USSD Push</div></div></label>
        <label class="pay-option emola"><input type="radio" name="pay_method" value="emola" onchange="selectPay('emola')"><div class="pay-card"><i class="fa-solid fa-mobile-screen pay-icon"></i><div class="pay-name">e-Mola</div><div class="pay-desc">Movitel &middot; USSD Push</div></div></label>
        <label class="pay-option visa"><input type="radio" name="pay_method" value="visa" onchange="selectPay('visa')"><div class="pay-card"><i class="fa-brands fa-cc-visa pay-icon"></i><div class="pay-name">Visa</div><div class="pay-desc">Cartão crédito</div></div></label>
        <label class="pay-option master"><input type="radio" name="pay_method" value="mastercard" onchange="selectPay('mastercard')"><div class="pay-card"><i class="fa-brands fa-cc-mastercard pay-icon"></i><div class="pay-name">Mastercard</div><div class="pay-desc">Cartão crédito</div></div></label>
        <label class="pay-option manual"><input type="radio" name="pay_method" value="manual" onchange="selectPay('manual')"><div class="pay-card"><i class="fa-solid fa-building-columns pay-icon"></i><div class="pay-name">Transferência</div><div class="pay-desc">Bancária / Manual</div></div></label>
      </div>

      <div id="panel-mpesa" class="mm-panel mpesa show">
        <div class="mm-panel-title"><i class="fa-solid fa-mobile-screen"></i> Número M-Pesa (Vodacom)</div>
        <div class="phone-input-wrap"><div class="phone-prefix">&#127474;&#127487; +258</div><input type="tel" id="mpesa-phone" class="phone-number-inp" placeholder="84 000 0000" maxlength="12" inputmode="numeric" value="<?= esc($phone_suffix) ?>" oninput="formatPhone(this)"></div>
        <div class="phone-hint"><i class="fa-solid fa-circle-info"></i> Receberá um USSD Push no seu telemóvel para confirmar com o PIN M-Pesa</div>
        <div class="mm-status" id="mpesa-status"></div>
      </div>

      <div id="panel-emola" class="mm-panel emola">
        <div class="mm-panel-title"><i class="fa-solid fa-mobile-screen"></i> Número e-Mola (Movitel)</div>
        <div class="phone-input-wrap"><div class="phone-prefix">&#127474;&#127487; +258</div><input type="tel" id="emola-phone" class="phone-number-inp" placeholder="86 000 0000" maxlength="12" inputmode="numeric" value="<?= esc($phone_suffix) ?>" oninput="formatPhone(this)"></div>
        <div class="phone-hint"><i class="fa-solid fa-circle-info"></i> Receberá um USSD Push no seu telemóvel para confirmar com o PIN e-Mola</div>
        <div class="mm-status" id="emola-status"></div>
      </div>

      <div id="panel-card" class="card-panel">
        <div class="card-scene" onclick="flipCard()" title="Clique para ver o verso">
          <div class="card-3d" id="card3d">
            <div class="card-face card-front" id="cardFace">
              <div class="card-top"><div class="card-chip"><div class="card-chip-inner"></div></div><div class="card-brand-logo" id="cardBrandLogo"><i class="fa-solid fa-credit-card" style="font-size:20px;opacity:.5"></i></div></div>
              <div class="card-num-disp" id="cardNumDisp">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;</div>
              <div class="card-bottom"><div><div class="card-lbl">Titular</div><div class="card-holder-name" id="cardNameDisp">NOME NO CARTÃO</div></div><div><div class="card-lbl" style="text-align:right">Validade</div><div class="card-exp-val" id="cardExpDisp">MM/AA</div></div></div>
            </div>
            <div class="card-face card-back"><div class="card-stripe"></div><div style="padding:0;position:relative;z-index:1"><div class="card-lbl" style="opacity:.5;font-size:9px;margin-bottom:4px">CVV</div><div class="card-cvv-strip" id="cardCvvDisp">&bull;&bull;&bull;</div></div></div>
          </div>
        </div>
        <div class="card-form">
          <div class="card-input-wrap"><input type="text" id="cardNumber" class="card-input num" placeholder="1234 5678 9012 3456" maxlength="23" autocomplete="cc-number" inputmode="numeric" oninput="onCardNum(this)" onfocus="flipToFront()"><i class="fa-solid fa-credit-card ci-icon"></i><span class="ci-brand" id="cardBrandBadge"></span></div>
          <div class="card-input-wrap"><input type="text" id="cardName" class="card-input" placeholder="Nome como aparece no cartão" maxlength="26" autocomplete="cc-name" oninput="onCardName(this)" onfocus="flipToFront()"><i class="fa-solid fa-user ci-icon"></i></div>
          <div class="card-row">
            <div class="card-input-wrap"><input type="text" id="cardExpiry" class="card-input" placeholder="MM/AA" maxlength="5" autocomplete="cc-exp" inputmode="numeric" oninput="onCardExp(this)" onfocus="flipToFront()"><i class="fa-regular fa-calendar ci-icon"></i></div>
            <div class="card-input-wrap"><input type="text" id="cardCvv" class="card-input" placeholder="CVV" maxlength="4" autocomplete="cc-csc" inputmode="numeric" oninput="onCardCvv(this)" onfocus="flipToBack()" onblur="flipToFront()"><i class="fa-solid fa-lock ci-icon"></i></div>
          </div>
          <div class="card-secure"><i class="fa-solid fa-shield-halved"></i> Dados encriptados com SSL 256-bit. Nunca armazenados nos nossos servidores.</div>
        </div>
      </div>

      <div id="panel-manual" class="manual-panel">
        <strong>Transferência Bancária:</strong><br>
        <strong>Banco:</strong> BCI &nbsp;|&nbsp; <strong>IBAN:</strong> MZ59 0006 0000 0000 0000 000<br>
        <strong>Titular:</strong> VSG Marketplace Lda.<br>
        <div class="ref-box" style="font-size:13px">Use o número do pedido como referência <span style="font-size:11px;color:var(--ink-3)">(gerado após confirmar)</span></div>
        Envie o comprovativo para <strong>pagamentos@vsgmarket.co.mz</strong>
      </div>
    </div>
  </div>

  <!-- Resumo (coluna direita) -->
  <div>
    <div class="co-card" style="position:sticky;top:calc(var(--hdr-h)+16px)">
      <div class="co-card-title"><i class="fa-solid fa-receipt"></i> Resumo</div>
      <div class="order-items">
        <?php foreach ($checkout_items as $ci): ?>
        <div class="oi-row">
          <div class="oi-img"><img src="<?= esc($ci['img_url']) ?>" alt="<?= esc($ci['product_name']) ?>" onerror="this.src='https://ui-avatars.com/api/?name=P&size=100&background=00b96b&color=fff'"></div>
          <div style="flex:1;min-width:0"><div class="oi-name" title="<?= esc($ci['product_name']) ?>"><?= esc($ci['product_name']) ?></div><div class="oi-qty">Qtd: <?= (int)$ci['quantity'] ?></div></div>
          <div class="oi-price"><?= $fmt($ci['line_total']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sum-sep"></div>
      <div class="sum-row"><span style="color:var(--ink-2)">Subtotal</span><span style="font-weight:700"><?= $fmt($subtotal) ?></span></div>
      <div class="sum-row"><span style="color:var(--ink-2)">Entrega</span><span class="<?= $shipping_cost===0?'sum-free':'' ?>" style="font-weight:700"><?= $shipping_cost===0?'Grátis':$fmt($shipping_cost) ?></span></div>
      <div class="sum-row total"><span>Total</span><span><?= $fmt($total_mzn) ?></span></div>
      <button type="button" class="btn-place" id="btnPlace" onclick="submitOrder()">
        <i class="fa-solid fa-lock"></i> Confirmar Pedido
      </button>
      <div class="trust-badges">
        <div class="trust-badge"><i class="fa-solid fa-shield-halved"></i> Dados protegidos SSL</div>
        <div class="trust-badge"><i class="fa-solid fa-rotate-left"></i> Devolução em 30 dias</div>
        <div class="trust-badge"><i class="fa-solid fa-headset"></i> Suporte 24/7</div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.html'; ?>

<!-- Overlay processamento -->
<div class="pay-overlay" id="payOverlay">
  <div class="po-box">
    <div class="po-spinner" id="poSpinner"></div>
    <div class="po-icon-ok" id="poIconOk" style="display:none"><i class="fa-solid fa-check"></i></div>
    <div class="po-title" id="poTitle">A processar&hellip;</div>
    <div class="po-sub" id="poSub">Por favor aguarde</div>
    <div class="po-steps" id="poSteps">
      <div class="po-step" id="poStep1"><i class="fa-regular fa-circle"></i><span>A validar dados</span></div>
      <div class="po-step" id="poStep2"><i class="fa-regular fa-circle"></i><span>A aguardar pagamento</span></div>
      <div class="po-step" id="poStep3"><i class="fa-regular fa-circle"></i><span>A criar pedido</span></div>
    </div>
    <div class="po-err" id="poErr"></div>
    <button class="po-err-btn" id="poErrBtn" onclick="closeOverlay()">&larr; Voltar e corrigir</button>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
  MODAL DE LOGIN
  Activado APENAS quando visitante clica "Confirmar Pedido".
  Nunca bloqueia o acesso ao checkout nem ao resumo.
═══════════════════════════════════════════════════════════════ -->
<div class="login-modal" id="loginModal">
  <div class="lm-box">
    <div class="lm-header">
      <div class="lm-icon"><i class="fa-solid fa-lock"></i></div>
      <div class="lm-title">Inicia sessão para pagar</div>
      <div class="lm-sub">O teu resumo está guardado. Entra na tua conta para completar a compra com segurança.</div>
    </div>
    <div class="lm-benefits">
      <div class="lm-benefit"><i class="fa-solid fa-shield-halved"></i> Pagamento encriptado e protegido</div>
      <div class="lm-benefit"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de pedidos guardado</div>
      <div class="lm-benefit"><i class="fa-solid fa-truck"></i> Rastreio de entrega em tempo real</div>
    </div>
    <div class="lm-divider"></div>
    <div class="lm-btns">
      <a id="lmLoginBtn" href="#" class="lm-btn-login"><i class="fa-solid fa-right-to-bracket"></i> Entrar na minha conta</a>
      <a id="lmRegBtn"   href="#" class="lm-btn-reg"><i class="fa-solid fa-user-plus"></i> Criar conta gratuita</a>
    </div>
    <span class="lm-cancel" onclick="closeLoginModal()">Continuar a ver o resumo</span>
  </div>
</div>

<!-- Ecrã de sucesso -->
<div id="successScreen">
  <div class="success-wrap">
    <div class="success-icon" id="sIcon"><i class="fa-solid fa-check"></i></div>
    <h1 class="success-title" id="sTitle"></h1>
    <p class="success-sub" id="sSub"></p>
    <div class="success-card" id="sCardNum"><div class="success-card-title">Número do Pedido</div><div class="success-num"><span id="sOrderNum"></span><button class="copy-btn" onclick="copyOrder()" title="Copiar"><i class="fa-regular fa-copy"></i></button></div></div>
    <div class="success-card" id="sCardPay" style="display:none"><div class="success-card-title">Pagamento</div><div style="display:flex;flex-direction:column;gap:7px"><div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:var(--ink-3)">Estado</span><span style="color:#16a34a;font-weight:700"><i class="fa-solid fa-circle-check"></i> Aprovado</span></div><div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:var(--ink-3)">Transacção</span><span style="font-family:monospace;font-size:11px;font-weight:700" id="sTxnId"></span></div><div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:var(--ink-3)">Método</span><span style="font-weight:700" id="sPayMethod"></span></div></div></div>
    <div class="success-card" id="sCardSteps"><div class="success-card-title">Próximos Passos</div><div class="success-steps" id="sStepsList"></div></div>
    <div class="success-btns" id="sBtns"><a href="pages/person/index.php" class="btn-orders"><i class="fa-solid fa-list-check"></i> Ver Pedidos</a><a href="shopping.php" class="btn-shop2"><i class="fa-solid fa-border-all"></i> Continuar</a></div>
  </div>
</div>

<script>
(function(){
'use strict';

// ── Dados PHP → JS ────────────────────────────────────────────────
const IS_LOGGED   = <?= $user_logged_in ? 'true' : 'false' ?>;
const BUY_NOW_ID  = <?= $buy_now_id ?>;
const BUY_NOW_QTY = <?= $buy_now_qty ?>;
const TOTAL_MZN   = <?= $total_mzn ?>;
const USER_PHONE  = <?= json_encode($prefill_phone_norm) ?>;
let   CSRF        = <?= json_encode($_SESSION['csrf_token']) ?>;

// URL de retorno após login
const RETURN_URL = <?= json_encode($return_url) ?>;
const LOGIN_BASE = 'registration/login/login.php?redirect=';
const REG_BASE   = 'registration/register/painel_cadastro.php?redirect=';

<?php
$base      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$ajax_url  = $base . '/ajax/ajax_checkout.php';
$poll_url  = $base . '/ajax/ajax_payment_status.php';
?>
const AJAX_URL = <?= json_encode($ajax_url) ?>;
const POLL_URL = <?= json_encode($poll_url) ?>;

// ════════════════════════════════════════════════════════
// MODAL DE LOGIN
// Aberto APENAS quando visitante tenta confirmar o pedido.
// ════════════════════════════════════════════════════════
function openLoginModal() {
  const enc = encodeURIComponent(RETURN_URL);
  document.getElementById('lmLoginBtn').href = LOGIN_BASE + enc;
  document.getElementById('lmRegBtn').href   = REG_BASE   + enc;
  document.getElementById('loginModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}
window.closeLoginModal = function() {
  document.getElementById('loginModal').classList.remove('show');
  document.body.style.overflow = '';
};
document.getElementById('loginModal').addEventListener('click', function(e) {
  if (e.target === this) closeLoginModal();
});

// ── Seleccionar método ────────────────────────────────────────────
let currentPay = 'mpesa';
window.selectPay = function(method) {
  currentPay = method;
  ['mpesa','emola','card','manual'].forEach(p => {
    document.getElementById('panel-'+p)?.classList.remove('show');
  });
  const target = (method==='visa'||method==='mastercard') ? 'card' : method;
  document.getElementById('panel-'+target)?.classList.add('show');
  const isCard = method==='visa'||method==='mastercard';
  const isMM   = method==='mpesa'||method==='emola';
  document.getElementById('btnPlace').innerHTML = isCard
    ? '<i class="fa-solid fa-lock"></i> Pagar com Cartão'
    : isMM
    ? '<i class="fa-solid fa-mobile-screen"></i> Enviar USSD Push'
    : '<i class="fa-solid fa-check"></i> Confirmar Pedido';
};
selectPay('mpesa');

// ── Formatar telefone ─────────────────────────────────────────────
window.formatPhone = function(inp) {
  let v = inp.value.replace(/\D/g,'');
  if (v.startsWith('258')) v = v.slice(3);
  inp.value = v.slice(0,9);
};

// ── Cartão 3D ─────────────────────────────────────────────────────
const card3d = document.getElementById('card3d');
let flipped = false;
window.flipCard    = () => { flipped=!flipped; card3d?.classList.toggle('flipped',flipped); };
window.flipToBack  = () => { flipped=true;  card3d?.classList.add('flipped'); };
window.flipToFront = () => { flipped=false; card3d?.classList.remove('flipped'); };

function detectBrand(n) {
  n=n.replace(/\D/g,'');
  if (/^4/.test(n)) return 'visa';
  if (/^5[1-5]/.test(n)||/^2[2-7]/.test(n)) return 'mastercard';
  return '';
}
window.onCardNum = function(inp) {
  const raw=inp.value.replace(/\D/g,'').slice(0,16);
  inp.value=raw.replace(/(.{4})/g,'$1 ').trim();
  const d=document.getElementById('cardNumDisp');
  if(d){const p=(raw+'................').slice(0,16);d.textContent=p.replace(/(.{4})/g,'$1 ').trim().replace(/\./g,'•');}
  const brand=detectBrand(raw);
  const badge=document.getElementById('cardBrandBadge');
  const face=document.getElementById('cardFace');
  const logo=document.getElementById('cardBrandLogo');
  if(brand==='visa'){badge&&(badge.innerHTML='<i class="fa-brands fa-cc-visa" style="color:#1a1f71"></i>');face&&(face.className='card-face card-front');logo&&(logo.innerHTML='<span class="visa-logo">VISA</span>');}
  else if(brand==='mastercard'){badge&&(badge.innerHTML='<i class="fa-brands fa-cc-mastercard" style="color:#eb001b"></i>');face&&(face.className='card-face card-front mc');logo&&(logo.innerHTML='<div class="mc-circles"><span></span><span></span></div>');}
  else{badge&&(badge.innerHTML='');logo&&(logo.innerHTML='<i class="fa-solid fa-credit-card" style="font-size:20px;opacity:.5"></i>');}
  if(raw.length===16){inp.classList.toggle('ok',luhn(raw));inp.classList.toggle('err',!luhn(raw));}
  else inp.classList.remove('ok','err');
};
window.onCardName = function(inp) {
  inp.value=inp.value.toUpperCase().replace(/[^A-Z\s]/g,'');
  const d=document.getElementById('cardNameDisp');if(d)d.textContent=inp.value||'NOME NO CARTÃO';
};
window.onCardExp = function(inp) {
  let v=inp.value.replace(/\D/g,'');
  if(v.length>2)v=v.slice(0,2)+'/'+v.slice(2,4);
  inp.value=v;
  const d=document.getElementById('cardExpDisp');if(d)d.textContent=v||'MM/AA';
  if(v.length===5){const[m,y]=v.split('/');const e=new Date(2000+parseInt(y),parseInt(m)-1+1,1);inp.classList.toggle('ok',e>new Date());inp.classList.toggle('err',e<=new Date());}
  else inp.classList.remove('ok','err');
};
window.onCardCvv = function(inp) {
  inp.value=inp.value.replace(/\D/g,'').slice(0,4);
  const d=document.getElementById('cardCvvDisp');if(d)d.textContent=inp.value.replace(/./g,'•')||'•••';
  if(inp.value.length>=3)inp.classList.add('ok');else inp.classList.remove('ok');
};
function luhn(n){let s=0,alt=false;for(let i=n.length-1;i>=0;i--){let d=parseInt(n[i]);if(alt){d*=2;if(d>9)d-=9;}s+=d;alt=!alt;}return n.length>=13&&s%10===0;}

// ── Overlay ───────────────────────────────────────────────────────
const overlay = document.getElementById('payOverlay');
const poSteps = [1,2,3].map(n=>document.getElementById('poStep'+n));
function showOverlay(title,sub){
  document.getElementById('poSpinner').style.display='';
  document.getElementById('poIconOk').style.display='none';
  document.getElementById('poTitle').textContent=title||'A processar…';
  document.getElementById('poSub').textContent=sub||'Por favor aguarde';
  document.getElementById('poSteps').style.display='';
  document.getElementById('poErr').style.display='none';
  document.getElementById('poErrBtn').style.display='none';
  poSteps.forEach(el=>{if(el){el.classList.remove('active','done');el.querySelector('i').className='fa-regular fa-circle';el.style.color='';}});
  overlay.classList.add('show');document.body.style.overflow='hidden';
}
function hideOverlay(){overlay.classList.remove('show');document.body.style.overflow='';}
window.closeOverlay=function(){hideOverlay();document.getElementById('btnPlace').disabled=false;};
function setStep(n){
  poSteps.forEach((el,i)=>{
    if(!el)return;const idx=i+1;el.classList.remove('active','done');
    if(idx<n){el.classList.add('done');el.querySelector('i').className='fa-solid fa-check';}
    if(idx===n){el.classList.add('active');el.querySelector('i').className='fa-solid fa-circle-notch fa-spin';}
    if(idx>n){el.querySelector('i').className='fa-regular fa-circle';}
  });
}
function showOverlayError(msg){
  document.getElementById('poSpinner').style.display='none';
  document.getElementById('poSteps').style.display='none';
  document.getElementById('poSub').style.display='none';
  document.getElementById('poTitle').textContent='Pagamento não processado';
  const e=document.getElementById('poErr');e.style.display='block';e.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+msg;
  document.getElementById('poErrBtn').style.display='block';
  poSteps.forEach(el=>{if(el?.classList.contains('active')){el.classList.remove('active');el.querySelector('i').className='fa-solid fa-xmark';el.style.color='var(--red)';}});
}

// ── MM Status ─────────────────────────────────────────────────────
let pollInterval=null,pollSeconds=0,pollMax=120;
let currentConvId=null,currentPayToken=null;
let timerEl=null;

function showMmStatus(method,type,title,msg){
  const el=document.getElementById(method+'-status');if(!el)return;
  el.className='mm-status '+type+' show';
  const icons={waiting:'<i class="fa-solid fa-mobile-screen mm-status-icon"></i>',polling:'<i class="fa-solid fa-spinner fa-spin mm-status-icon"></i>',success:'<i class="fa-solid fa-circle-check mm-status-icon"></i>',failed:'<i class="fa-solid fa-circle-xmark mm-status-icon"></i>'};
  el.innerHTML=(icons[type]||'')+'<div class="mm-status-text"><strong>'+escH(title)+'</strong>'+escH(msg)+'</div>';
}
function startTimer(secs){
  let remaining=secs;
  if(timerEl)timerEl.remove();
  timerEl=document.createElement('div');timerEl.className='mm-timer';
  timerEl.innerHTML='<div class="mm-timer-ring"></div><span class="mm-timer-num">'+remaining+'</span>';
  const statusEl=document.getElementById(currentPay+'-status');
  if(statusEl)statusEl.appendChild(timerEl);
  const tick=setInterval(()=>{
    remaining--;
    const ring=timerEl.querySelector('.mm-timer-ring');const num=timerEl.querySelector('.mm-timer-num');
    if(ring)ring.style.setProperty('--p',((secs-remaining)/secs*100)+'');
    if(num)num.textContent=remaining;if(remaining<=0)clearInterval(tick);
  },1000);
}
function stopTimer(){if(timerEl){timerEl.remove();timerEl=null;}}

// ════════════════════════════════════════════════════════
// SUBMETER PEDIDO
// PASSO 1: Se visitante → abrir modal de login (NÃO avançar)
// PASSO 2: Se autenticado → validar e processar normalmente
// ════════════════════════════════════════════════════════
window.submitOrder = async function() {
  const btn = document.getElementById('btnPlace');

  // ─── VERIFICAÇÃO DE LOGIN ────────────────────────────
  // O login só é solicitado aqui, neste momento.
  // O utilizador pode navegar, ver resumo, preencher tudo sem login.
  if (!IS_LOGGED) {
    openLoginModal();
    return; // não desabilitar botão — utilizador pode fechar modal e continuar
  }

  btn.disabled = true;

  const ship_name    = document.getElementById('shipName')?.value?.trim()||'';
  const ship_phone   = document.getElementById('shipPhone')?.value?.trim()||'';
  const ship_address = document.getElementById('shipAddress')?.value?.trim()||'';
  const ship_city    = document.getElementById('shipCity')?.value?.trim()||'';
  const ship_state   = document.getElementById('shipState')?.value?.trim()||'';
  const notes        = document.getElementById('orderNotes')?.value?.trim()||'';

  if(!ship_name||!ship_phone||!ship_address||!ship_city){
    showFormError('Preencha todos os campos de entrega obrigatórios (*).');
    btn.disabled=false; return;
  }
  clearFormError();

  const isMM   = currentPay==='mpesa'||currentPay==='emola';
  const isCard = currentPay==='visa'||currentPay==='mastercard';

  if (isMM) {
    const phoneInp = document.getElementById(currentPay+'-phone');
    const rawPhone = (phoneInp?.value||'').replace(/\D/g,'');
    if (rawPhone.length < 9 || !['84','85','86','87'].some(p=>rawPhone.startsWith(p))) {
      phoneInp?.classList.add('error');
      showFormError('Número de telemóvel inválido para '+currentPay.toUpperCase()+'.');
      btn.disabled=false; return;
    }
    phoneInp?.classList.remove('error');
    await initiateMobileMoney(currentPay,'258'+rawPhone,{ship_name,ship_phone,ship_address,ship_city,ship_state,notes});
    return;
  }

  if (isCard) {
    const numRaw=document.getElementById('cardNumber')?.value?.replace(/\D/g,'')||'';
    if(!luhn(numRaw)){document.getElementById('cardNumber')?.classList.add('err');btn.disabled=false;return;}
    showOverlay('A processar cartão','Por favor aguarde, não feche esta janela.');
    setStep(1);await new Promise(r=>setTimeout(r,300));setStep(2);
    await submitToBackend({pay_method:currentPay,card_number:document.getElementById('cardNumber')?.value||'',card_name:document.getElementById('cardName')?.value||'',card_expiry:document.getElementById('cardExpiry')?.value||'',card_cvv:document.getElementById('cardCvv')?.value||'',ship_name,ship_phone,ship_address,ship_city,ship_state,notes});
    return;
  }

  showOverlay('A registar pedido','Por favor aguarde…');
  setStep(1);await new Promise(r=>setTimeout(r,200));setStep(2);
  await submitToBackend({pay_method:'manual',ship_name,ship_phone,ship_address,ship_city,ship_state,notes});
};

// ── Mobile Money ──────────────────────────────────────────────────
async function initiateMobileMoney(method,phone,formData){
  showMmStatus(method,'waiting','A enviar pedido USSD…','Receberá uma notificação no número '+phone+' para confirmar.');
  const btn=document.getElementById('btnPlace');
  const fd=new FormData();
  fd.set('action','initiate_mm');fd.set('csrf_token',CSRF);fd.set('pay_method',method);
  fd.set('phone',phone);fd.set('amount',TOTAL_MZN);fd.set('order_ref','VSG-'+Date.now());
  fd.set('ship_name',formData.ship_name);fd.set('ship_phone',formData.ship_phone);
  fd.set('ship_address',formData.ship_address);fd.set('ship_city',formData.ship_city);
  fd.set('ship_state',formData.ship_state||'');fd.set('notes',formData.notes||'');
  fd.set('buy_now_id',BUY_NOW_ID);fd.set('buy_now_qty',BUY_NOW_QTY);
  let resp;
  try{
    const r=await fetch(AJAX_URL,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    if(!r.ok)throw new Error('HTTP '+r.status);
    resp=await r.json();
  }catch(e){showMmStatus(method,'failed','Erro de ligação',e.message);btn.disabled=false;return;}
  if(!resp.ok){showMmStatus(method,'failed','Erro',resp.msg||'Ocorreu um erro.');btn.disabled=false;return;}
  currentConvId=resp.conversation_id;currentPayToken=resp.pay_token;
  showMmStatus(method,'polling','⏳ Aguardando confirmação PIN…','Aprove o pagamento de MT '+TOTAL_MZN.toLocaleString('pt-MZ',{minimumFractionDigits:2})+' no seu telemóvel.');
  startTimer(pollMax);
  startPolling(method,currentConvId,currentPayToken,formData);
}

// ── Polling ───────────────────────────────────────────────────────
function startPolling(method,convId,payToken,formData){
  pollSeconds=0;if(pollInterval)clearInterval(pollInterval);
  pollInterval=setInterval(async()=>{
    pollSeconds+=5;
    if(pollSeconds>=pollMax){clearInterval(pollInterval);stopTimer();showMmStatus(method,'failed','Tempo expirado','O utilizador não confirmou a tempo. Tente novamente.');document.getElementById('btnPlace').disabled=false;return;}
    try{
      const r=await fetch(POLL_URL+'?conv_id='+encodeURIComponent(convId)+'&method='+method+'&token='+encodeURIComponent(payToken),{headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!r.ok)throw new Error('HTTP '+r.status);
      const d=await r.json();
      if(d.status==='confirmed'){
        clearInterval(pollInterval);stopTimer();
        showMmStatus(method,'success','Pagamento confirmado!','O seu pagamento foi aprovado com sucesso.');
        showOverlay('A criar pedido…','O pagamento foi confirmado. A finalizar…');setStep(3);
        await submitToBackend({pay_method:method,phone:convId,pay_token:payToken,mm_confirmed:'1',...formData});
      }else if(d.status==='failed'||d.status==='cancelled'){
        clearInterval(pollInterval);stopTimer();
        showMmStatus(method,'failed','Pagamento recusado',d.msg||'O utilizador cancelou ou PIN incorrecto.');
        document.getElementById('btnPlace').disabled=false;
      }
    }catch(e){showMmStatus(method,'polling','⏳ Aguardando…','Rede instável. A tentar novamente…');}
  },5000);
}

// ── Submit backend ────────────────────────────────────────────────
async function submitToBackend(extra){
  const btn=document.getElementById('btnPlace');
  const fd=new FormData();
  fd.set('action','create_order');fd.set('csrf_token',CSRF);
  fd.set('buy_now_id',BUY_NOW_ID);fd.set('buy_now_qty',BUY_NOW_QTY);
  Object.entries(extra).forEach(([k,v])=>fd.set(k,v));
  let data;
  try{
    const r=await fetch(AJAX_URL,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    if(!r.ok)throw new Error('HTTP '+r.status);
    const text=await r.text();
    try{data=JSON.parse(text);}catch(_){throw new Error('Resposta inválida: '+text.slice(0,120));}
  }catch(e){showOverlayError('Erro de ligação: '+e.message);btn.disabled=false;return;}
  if(data.new_csrf)CSRF=data.new_csrf;
  if(data.ok){
    poSteps.forEach(el=>{if(el){el.classList.remove('active');el.classList.add('done');el.querySelector('i').className='fa-solid fa-check';}});
    document.getElementById('poSpinner').style.display='none';
    document.getElementById('poIconOk').style.display='grid';
    document.getElementById('poTitle').textContent='Concluído!';
    document.getElementById('poSub').textContent='';
    await new Promise(r=>setTimeout(r,700));
    showSuccess(data);
  }else{showOverlayError(data.msg||'Ocorreu um erro. Tente novamente.');btn.disabled=false;}
}

// ── Ecrã de sucesso ───────────────────────────────────────────────
function showSuccess(data){
  if(overlay){overlay.style.transition='opacity .4s';overlay.style.opacity='0';setTimeout(()=>hideOverlay(),400);}
  const screen=document.getElementById('successScreen');if(!screen)return;
  const isMM=data.pay_method==='mpesa'||data.pay_method==='emola';
  const isCard=data.pay_method==='visa'||data.pay_method==='mastercard';
  const isManual=data.pay_method==='manual';
  document.getElementById('sOrderNum').textContent=data.order_number;
  document.getElementById('sTitle').textContent=(isCard||isMM)?'Pagamento Confirmado!':'Pedido Registado!';
  document.getElementById('sSub').textContent=isCard?'O seu pagamento foi processado.':isMM?'Pagamento '+data.pay_method.toUpperCase()+' confirmado.':'Efectue a transferência para confirmar.';
  if((isCard||isMM)&&data.transaction_id){document.getElementById('sCardPay').style.display='block';document.getElementById('sTxnId').textContent=data.transaction_id;document.getElementById('sPayMethod').textContent=data.pay_method.toUpperCase();}
  const stepsList=isManual?[['envelope','Receberá confirmação por email.'],['building-columns','Efectue a transferência usando o número do pedido.'],['check-circle','Após confirmarmos, o produto será enviado.']]:isMM?[['check-circle','Pagamento '+data.pay_method.toUpperCase()+' confirmado.'],['truck','Enviado em 1-3 dias úteis.'],['envelope','Confirmação por email enviada.']]:[[
'check-circle','Pagamento aprovado.'],['truck','Enviado em 1-3 dias úteis.'],['envelope','Confirmação por email enviada.']];
  document.getElementById('sStepsList').innerHTML=stepsList.map(([icon,text])=>`<div class="success-step"><i class="fa-solid fa-${icon}"></i><span class="success-step-text">${text}</span></div>`).join('');
  screen.style.display='block';document.body.style.overflow='';overlay.style.opacity='';
  const ease='cubic-bezier(.16,1,.3,1)';
  [['sIcon',50],['sTitle',240],['sSub',370],['sCardNum',490],['sCardPay',600],['sCardSteps',680],['sBtns',800]].forEach(([id,delay])=>{
    const el=document.getElementById(id);if(!el||el.style.display==='none')return;
    setTimeout(()=>{el.style.transition=`opacity .45s ${ease},transform .45s ${ease}`;el.style.opacity='1';el.style.transform='none';},delay);
  });
}

// ── Helpers ───────────────────────────────────────────────────────
function escH(t){return String(t??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function showFormError(msg){const e=document.getElementById('formErrors');if(e)e.innerHTML=`<div class="alert-error"><i class="fa-solid fa-exclamation-circle"></i><span>${escH(msg)}</span></div>`;window.scrollTo({top:0,behavior:'smooth'});}
function clearFormError(){const e=document.getElementById('formErrors');if(e)e.innerHTML='';}
window.copyOrder=function(){
  const num=document.getElementById('sOrderNum')?.textContent;
  if(num)navigator.clipboard?.writeText(num).then(()=>{document.querySelectorAll('.copy-btn').forEach(b=>{b.innerHTML='<i class="fa-solid fa-check"></i>';setTimeout(()=>b.innerHTML='<i class="fa-regular fa-copy"></i>',2000);});});
};
document.querySelectorAll('.cart-badge').forEach(b=>{b.textContent='0';});

})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>