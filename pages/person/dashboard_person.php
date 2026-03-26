<?php
/**
 * pages/person/dashboard_person.php — VSG Marketplace
 * Painel pessoal do cliente (tipo 'person').
 */

define('REQUIRED_TYPE', 'person');
require_once __DIR__ . '/../../registration/middleware/middleware_auth.php';
require_once __DIR__ . '/../../registration/includes/db.php';
require_once __DIR__ . '/../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php"); exit;
}
if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin','superadmin'])) {
    header("Location: ../../pages/admin/dashboard.php"); exit;
}
if ($_SESSION['auth']['type'] !== 'person') {
    header($_SESSION['auth']['type'] === 'company'
        ? "Location: ../business/dashboard_business.php"
        : "Location: ../../registration/login/login.php?error=acesso_proibido"
    );
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

$stmt = $mysqli->prepare("
    SELECT nome, apelido, email, telefone, public_id, status,
           registration_step, email_verified_at, created_at, type
    FROM users WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $userId); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$user) { session_destroy(); header("Location: ../../registration/login/login.php?error=user_not_found"); exit; }
if (!$user['email_verified_at']) { header("Location: ../../registration/process/verify_email.php"); exit; }
if (!$user['public_id'])         { header("Location: ../../registration/register/gerar_uid.php"); exit; }
if ($user['status'] === 'blocked') { die('Acesso restrito: conta bloqueada.'); }

$displayName   = $user['apelido'] ?: $user['nome'];
$firstName     = explode(' ', trim($displayName))[0];
$nameParts     = explode(' ', trim($displayName));
$initials      = strtoupper(substr($nameParts[0],0,1) . (isset($nameParts[1]) ? substr($nameParts[1],0,1) : ''));
$displayAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=1a7f37&color=ffffff&bold=true&size=64';

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');

$stats = ['mensagens_nao_lidas'=>0,'pedidos_em_andamento'=>0,'total_gasto'=>0,'entregues'=>0];
$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM notifications WHERE receiver_id=? AND status='nao_lida'");
$st->bind_param('i',$userId); $st->execute(); $stats['mensagens_nao_lidas']=(int)$st->get_result()->fetch_assoc()['n']; $st->close();
$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM orders WHERE customer_id=? AND status IN('pendente','confirmado','processando')");
$st->bind_param('i',$userId); $st->execute(); $stats['pedidos_em_andamento']=(int)$st->get_result()->fetch_assoc()['n']; $st->close();
$st = $mysqli->prepare("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE customer_id=? AND payment_status='pago'");
$st->bind_param('i',$userId); $st->execute(); $stats['total_gasto']=(float)$st->get_result()->fetch_assoc()['n']; $st->close();
$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM orders WHERE customer_id=? AND status='entregue'");
$st->bind_param('i',$userId); $st->execute(); $stats['entregues']=(int)$st->get_result()->fetch_assoc()['n']; $st->close();

$stmt = $mysqli->prepare("
    SELECT o.id, o.order_number, o.order_date, o.status, o.payment_status,
           o.payment_method, o.total, o.currency, o.shipping_address,
           o.shipping_city, o.customer_notes, o.created_at,
           COALESCE(u.nome,'Empresa Desconhecida') AS empresa_nome,
           (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS items_count,
           (SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id=o.id) AS total_items
    FROM orders o LEFT JOIN users u ON o.company_id=u.id
    WHERE o.customer_id=? AND o.deleted_at IS NULL
    ORDER BY o.order_date DESC LIMIT 50
");
$stmt->bind_param('i',$userId); $stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$statusMap = [
    'pendente'    => ['icon'=>'fa-clock',        'fa_color'=>'var(--warn)',    'label'=>'Pendente',    'class'=>'status-pendente'],
    'confirmado'  => ['icon'=>'fa-circle-check', 'fa_color'=>'var(--link)',    'label'=>'Confirmado',  'class'=>'status-confirmado'],
    'processando' => ['icon'=>'fa-gear',          'fa_color'=>'var(--link)',    'label'=>'Processando', 'class'=>'status-processando'],
    'enviado'     => ['icon'=>'fa-truck-fast',   'fa_color'=>'var(--link)',    'label'=>'Enviado',     'class'=>'status-enviado'],
    'entregue'    => ['icon'=>'fa-circle-check', 'fa_color'=>'var(--primary)', 'label'=>'Entregue',    'class'=>'status-entregue'],
    'cancelado'   => ['icon'=>'fa-circle-xmark', 'fa_color'=>'var(--danger)',  'label'=>'Cancelado',   'class'=>'status-cancelado'],
];
$paymentStatusMap = ['pendente'=>['label'=>'Aguardando'],'pago'=>['label'=>'Pago'],'parcial'=>['label'=>'Parcial'],'reembolsado'=>['label'=>'Reembolsado']];
$paymentMethodMap = ['mpesa'=>['label'=>'M-Pesa'],'emola'=>['label'=>'E-Mola'],'visa'=>['label'=>'Visa'],'mastercard'=>['label'=>'Mastercard'],'manual'=>['label'=>'Manual']];
$categoryLabels   = [
    'reciclavel'=>['icon'=>'fa-recycle','label'=>'Reciclável'],
    'sustentavel'=>['icon'=>'fa-seedling','label'=>'Sustentável'],
    'servico'=>['icon'=>'fa-screwdriver-wrench','label'=>'Serviços'],
    'visiongreen'=>['icon'=>'fa-leaf','label'=>'VisionGreen'],
    'ecologico'=>['icon'=>'fa-earth-africa','label'=>'Ecológico'],
    'outro'=>['icon'=>'fa-box','label'=>'Outros'],
];
$priceRanges = [
    ['min'=>0,    'max'=>1000,  'label'=>'Até 1.000 MZN'],
    ['min'=>1000, 'max'=>5000,  'label'=>'1.000 – 5.000 MZN'],
    ['min'=>5000, 'max'=>10000, 'label'=>'5.000 – 10.000 MZN'],
    ['min'=>10000,'max'=>999999,'label'=>'Acima de 10.000 MZN'],
];
$pedidosStats = [
    'total'        => count($orders),
    'pendentes'    => count(array_filter($orders, fn($o)=>$o['status']==='pendente')),
    'em_andamento' => count(array_filter($orders, fn($o)=>in_array($o['status'],['confirmado','processando','enviado']))),
    'entregues'    => count(array_filter($orders, fn($o)=>$o['status']==='entregue')),
];

/* BASE_URL — raiz do projecto */
$_sn_parts = explode('/', str_replace('\\','/',$_SERVER['SCRIPT_NAME']));
array_splice($_sn_parts, -3); // remove dashboard_person.php + person + pages
$base = implode('/', $_sn_parts); // ex: '/vsg'
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VisionGreen | <?= htmlspecialchars($displayName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
.no-image-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;width:100%;height:100%;background:var(--raised);gap:3px}
.no-image-placeholder span{font-size:9px;text-transform:uppercase;letter-spacing:.04em;color:var(--txt3)}
.no-image-placeholder strong{font-size:11px;color:var(--link)}
.badge.hiding,.mobile-nav-badge.hiding{opacity:0;transform:scale(.8);transition:opacity .3s,transform .3s}
.theme-switcher{display:flex;align-items:center;gap:6px;padding:6px 10px;margin-bottom:2px}
.theme-switcher-label{font-size:10px;color:var(--txt3);font-weight:600;text-transform:uppercase;letter-spacing:.05em;flex:1}
.theme-dot{width:14px;height:14px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all .15s;flex-shrink:0}
.theme-dot:hover,.theme-dot.active{border-color:var(--txt);transform:scale(1.2)}
.greeting-banner{flex-wrap:wrap}
@media(max-width:480px){.greeting-cta{width:100%;justify-content:center}}
</style>
</head>
<body>
<div class="loading-bar" id="loadingBar"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
            <div class="logo-text">
                <span class="logo-main">VisionGreen</span>
                <span class="logo-sub">Marketplace Eco</span>
            </div>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="sidebar-avatar">
            <img src="<?= $displayAvatar ?>" alt="<?= htmlspecialchars($displayName) ?>"
                 onerror="this.style.display='none';this.parentElement.textContent='<?= $initials ?>'">
        </div>
        <div>
            <div class="sidebar-uname"><?= htmlspecialchars($displayName) ?></div>
            <div class="sidebar-urole"><i class="fa-solid fa-circle-check" style="font-size:8px;margin-right:3px"></i>Cliente</div>
        </div>
    </div>
    <div class="filters-container">
        <div class="filter-section">
            <h3>Principal</h3>
            <div class="filter-option active" onclick="navClick('home',this)">
                <i class="fa-solid fa-house"></i><label>Início</label>
            </div>
            <div class="filter-option" onclick="navClick('carrinho',this)">
                <i class="fa-solid fa-cart-shopping"></i><label>Carrinho</label>
                <span class="nav-pip green cart-count-badge" style="display:none">0</span>
            </div>
        </div>
        <div class="filter-section">
            <h3>Categorias</h3>
            <?php foreach ($categoryLabels as $value => $data): ?>
            <div class="filter-option" onclick="toggleCategoryFilter('<?= $value ?>',this)">
                <i class="fa-solid <?= $data['icon'] ?>"></i><label><?= $data['label'] ?></label>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="filter-section">
            <h3>Preço</h3>
            <?php foreach ($priceRanges as $range): ?>
            <div class="filter-option" onclick="togglePriceFilter('<?= $range['min'] ?>-<?= $range['max'] ?>',this)">
                <i class="fa-solid fa-tag"></i><label><?= $range['label'] ?></label>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="filter-section">
            <h3>Disponibilidade</h3>
            <div class="filter-option" onclick="toggleStockFilter(this)">
                <i class="fa-solid fa-circle-check"></i><label>Em Estoque</label>
            </div>
        </div>
        <div class="filter-option" onclick="resetFilters()">
            <i class="fa-solid fa-rotate-right"></i><label>Limpar Filtros</label>
        </div>
        <div class="filter-section">
            <h3>Conta</h3>
            <div class="filter-option" onclick="navClick('meus_pedidos',this)">
                <i class="fa-solid fa-box-open"></i><label>Meus Pedidos</label>
                <?php if ($stats['pedidos_em_andamento']>0): ?>
                <span class="nav-pip" id="sidebar-pedidos-pip"><?= $stats['pedidos_em_andamento'] ?></span>
                <?php endif; ?>
            </div>
            <div class="filter-option" onclick="navClick('notificacoes',this)">
                <i class="fa-solid fa-bell"></i><label>Notificações</label>
                <?php if ($stats['mensagens_nao_lidas']>0): ?>
                <span class="nav-pip"><?= $stats['mensagens_nao_lidas'] ?></span>
                <?php endif; ?>
            </div>
            <div class="filter-option" onclick="navClick('perfil',this)">
                <i class="fa-solid fa-user"></i><label>Perfil</label>
            </div>
            <div class="filter-option" onclick="navClick('configuracoes',this)">
                <i class="fa-solid fa-gear"></i><label>Configurações</label>
            </div>
        </div>
    </div>
    <div class="sidebar-footer">
        <div class="theme-switcher">
            <span class="theme-switcher-label"><i class="fa-solid fa-palette" style="margin-right:4px"></i>Tema</span>
            <div class="theme-dot active" style="background:#1a7f37" onclick="setTheme('',this)" title="Verde"></div>
            <div class="theme-dot" style="background:#b22a6a" onclick="setTheme('theme-pink',this)" title="Rosa"></div>
            <div class="theme-dot" style="background:#0550ae" onclick="setTheme('theme-ocean',this)" title="Azul"></div>
            <div class="theme-dot" style="background:#7d4e00" onclick="setTheme('theme-amber',this)" title="Âmbar"></div>
        </div>
        <a href="javascript:void(0)" onclick="navClick('configuracoes')" class="action-btn">
            <i class="fa-solid fa-gear"></i><span>Configurações</span>
        </a>
        <form method="post" action="../../registration/login/logout.php" style="width:100%">
            <?= csrf_field() ?>
            <button type="submit" class="action-btn btn-logout" style="width:100%">
                <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sair da conta</span>
            </button>
        </form>
    </div>
</aside>

<main class="main-content">
    <header class="header-main">
        <div class="header-content">
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="col-home-collapse">
                <button class="collapse-btn" onclick="toggleSidebar()" title="Colapsar">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="back-home-btn">
                    <a href="dashboard_person.php" title="Início">
                        <button><i class="fa-solid fa-house"></i></button>
                    </a>
                </div>
            </div>
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Buscar produtos, pedidos…" autocomplete="off">
            </div>
            <div class="header-actions">
                <button class="icon-btn" onclick="navigateTo('carrinho')" title="Carrinho">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span class="badge cart-badge" style="display:none">0</span>
                </button>
                <button class="icon-btn" onclick="navigateTo('meus_pedidos')" title="Pedidos">
                    <i class="fa-solid fa-box-open"></i>
                    <span class="badge" id="header-pedidos-badge" style="<?= $stats['pedidos_em_andamento']>0?'':'display:none' ?>">
                        <?= $stats['pedidos_em_andamento'] ?>
                    </span>
                </button>
                <button class="icon-btn" onclick="navigateTo('notificacoes')" title="Notificações">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($stats['mensagens_nao_lidas']>0): ?>
                    <span class="badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                    <?php endif; ?>
                </button>
                <div class="user-profile" onclick="navigateTo('perfil')">
                    <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($firstName) ?></span>
                        <span class="user-role"><i class="fa-solid fa-circle-check" style="font-size:7px;margin-right:2px"></i>Cliente</span>
                    </div>
                    <i class="fa-solid fa-chevron-down" style="font-size:9px;color:var(--txt3)"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="content-wrapper">

        <!-- HOME -->
        <div id="content-home" class="dynamic-content active">
            <div class="greeting-banner">
                <div>
                    <div class="greeting-hi">
                        <?= $greeting ?>, <?= htmlspecialchars($firstName) ?>
                        <i class="fa-solid fa-hand-wave" style="font-size:15px;color:var(--warn);margin-left:4px"></i>
                    </div>
                    <div class="greeting-sub">
                        <i class="fa-regular fa-calendar"></i>
                        <?= date('l, d \d\e F') ?>
                        <?php if ($stats['pedidos_em_andamento']>0): ?>
                        &nbsp;·&nbsp;
                        <i class="fa-solid fa-circle-notch fa-spin" style="font-size:9px;color:var(--warn)"></i>
                        <?= $stats['pedidos_em_andamento'] ?> pedido<?= $stats['pedidos_em_andamento']>1?'s':'' ?> em andamento
                        <?php endif; ?>
                    </div>
                </div>
                <button class="greeting-cta" onclick="navigateTo('carrinho')">
                    <i class="fa-solid fa-bolt"></i> Comprar agora
                </button>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-label">Pedidos activos</div>
                        <div class="stat-icon warn"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="stat-val" id="stat-pedidos"><?= $stats['pedidos_em_andamento'] ?></div>
                    <div class="stat-meta"><span class="chip y"><i class="fa-solid fa-rotate" style="font-size:7px"></i> Em curso</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-label">Total gasto</div>
                        <div class="stat-icon green"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                    <div class="stat-val"><?= number_format($stats['total_gasto'],0,',','.') ?></div>
                    <div class="stat-meta"><i class="fa-solid fa-circle-info" style="font-size:9px"></i> MZN · histórico</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-label">Entregues</div>
                        <div class="stat-icon blue"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                    <div class="stat-val"><?= $stats['entregues'] ?></div>
                    <div class="stat-meta"><span class="chip g"><i class="fa-solid fa-arrow-up" style="font-size:7px"></i> Total</span></div>
                </div>
            </div>

            <div class="section-header">
                <div class="section-title"><i class="fa-solid fa-fire-flame-curved"></i> Produtos em destaque</div>
                <div class="section-link" onclick="navigateTo('produtos')">
                    Ver todos <i class="fa-solid fa-arrow-right" style="font-size:9px"></i>
                </div>
            </div>
            <div id="productsGrid" class="products-grid">
                <div class="empty-state">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <h3>Carregando produtos…</h3>
                    <p>Aguarde um momento</p>
                </div>
            </div>

            <?php if (!empty($orders)): ?>
            <div class="section-header" style="margin-top:4px">
                <div class="section-title"><i class="fa-solid fa-box-open"></i> Últimos pedidos</div>
                <div class="section-link" onclick="navigateTo('meus_pedidos')">
                    Ver todos <i class="fa-solid fa-arrow-right" style="font-size:9px"></i>
                </div>
            </div>
            <div class="orders-list">
                <?php foreach (array_slice($orders,0,3) as $order):
                    $sm = $statusMap[$order['status']] ?? $statusMap['pendente']; ?>
                <div class="order-item" onclick="navigateTo('meus_pedidos')">
                    <div class="order-icon">
                        <i class="fa-solid <?= $sm['icon'] ?>" style="color:<?= $sm['fa_color'] ?>"></i>
                    </div>
                    <div class="order-info">
                        <div class="order-num"><?= htmlspecialchars($order['order_number']?:'#VG-'.$order['id']) ?></div>
                        <div class="order-meta">
                            <i class="fa-regular fa-building"></i> <?= htmlspecialchars($order['empresa_nome']) ?>
                            &nbsp;·&nbsp;
                            <i class="fa-regular fa-calendar"></i> <?= date('d M',strtotime($order['order_date'])) ?>
                        </div>
                    </div>
                    <span class="order-status <?= $sm['class'] ?>">
                        <i class="fa-solid <?= $sm['icon'] ?>"></i> <?= $sm['label'] ?>
                    </span>
                    <div class="order-value">
                        <?= number_format((float)$order['total'],2,',','.') ?> MZN
                        <small><?= $order['total_items'] ?> iten<?= $order['total_items']!=1?'s':'' ?></small>
                    </div>
                    <i class="fa-solid fa-chevron-right order-chevron"></i>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- CARRINHO — carregado via fetch, scripts removidos, inicializado pelo initCartEmbed() -->
        <div id="content-carrinho" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando carrinho…</h3>
            </div>
        </div>

        <!-- Sub-páginas carregadas via AJAX -->
        <?php foreach (['meus_pedidos','notificacoes','perfil','configuracoes','produtos'] as $pg): ?>
        <div id="content-<?= $pg ?>" class="dynamic-content">
            <div class="content-loading active">
                <div class="spinner"></div>
                <h3>Carregando…</h3>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</main>

<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>

<nav class="mobile-nav">
    <div class="mobile-nav-grid">
        <a href="dashboard_person.php" class="mobile-nav-item active" data-page="home">
            <i class="fa-solid fa-house"></i>
            <span class="mobile-nav-label">Início</span>
        </a>
        <button class="mobile-nav-item" onclick="navigateTo('meus_pedidos')" data-page="meus_pedidos">
            <i class="fa-solid fa-box-open"></i>
            <span class="mobile-nav-label">Pedidos</span>
            <span class="mobile-nav-badge" id="mobile-pedidos-badge" style="<?= $stats['pedidos_em_andamento']>0?'':'display:none' ?>">
                <?= $stats['pedidos_em_andamento'] ?>
            </span>
        </button>
        <button class="mobile-nav-item" onclick="toggleMobileMenu()" data-page="filtros">
            <i class="fa-solid fa-sliders"></i>
            <span class="mobile-nav-label">Filtros</span>
        </button>
        <button class="mobile-nav-item" onclick="navigateTo('notificacoes')" data-page="notificacoes">
            <i class="fa-solid fa-bell"></i>
            <span class="mobile-nav-label">Alertas</span>
            <?php if ($stats['mensagens_nao_lidas']>0): ?>
            <span class="mobile-nav-badge"><?= $stats['mensagens_nao_lidas'] ?></span>
            <?php endif; ?>
        </button>
        <button class="mobile-nav-item" onclick="navigateTo('perfil')" data-page="perfil">
            <i class="fa-solid fa-user"></i>
            <span class="mobile-nav-label">Perfil</span>
        </button>
    </div>
</nav>

<script>
(function(){
    'use strict';
    if(window.GlobalModuleManager) window.GlobalModuleManager.clearAll();
    window.GlobalModuleManager = {
        _iv:new Map(),_mods:new Set(),currentPage:null,
        registerInterval(n,id){if(this._iv.has(n))clearInterval(this._iv.get(n));this._iv.set(n,id);},
        clearInterval(n){if(this._iv.has(n)){clearInterval(this._iv.get(n));this._iv.delete(n);}},
        clearAllExcept(ex){this._iv.forEach((id,n)=>{if(n!==ex)clearInterval(id);});if(ex&&this._iv.has(ex)){const k=this._iv.get(ex);this._iv.clear();this._iv.set(ex,k);}else this._iv.clear();},
        clearAll(){this._iv.forEach(id=>clearInterval(id));this._iv.clear();this._mods.clear();},
        setActivePage(page){if(this.currentPage===page)return;this.currentPage=page;const keep={home:'dashboard_stats',notificacoes:'NotificationsModule',meus_pedidos:'OrdersModule'};const m=keep[page];if(m)this.clearAllExcept(m);else this.clearAll();}
    };
    window.addEventListener('beforeunload',()=>window.GlobalModuleManager.clearAll());
    window.addEventListener('pageshow',e=>{if(e.persisted)window.GlobalModuleManager.clearAll();});
})();

const userData = <?= json_encode(['userId'=>$userId,'nome'=>$displayName,'firstName'=>$firstName,'email'=>$user['email'],'publicId'=>$user['public_id'],'initials'=>$initials],JSON_UNESCAPED_UNICODE) ?>;
const ordersData         = <?= json_encode($orders,          JSON_UNESCAPED_UNICODE) ?>;
const pedidosStats       = <?= json_encode($pedidosStats,    JSON_UNESCAPED_UNICODE) ?>;
const statusMapJS        = <?= json_encode($statusMap,       JSON_UNESCAPED_UNICODE) ?>;
const paymentStatusMapJS = <?= json_encode($paymentStatusMap,JSON_UNESCAPED_UNICODE) ?>;
const paymentMethodMapJS = <?= json_encode($paymentMethodMap,JSON_UNESCAPED_UNICODE) ?>;
const BASE_URL           = <?= json_encode($base) ?>;
const IS_LOGGED          = true;
const CSRF_TOKEN         = <?= json_encode($_SESSION['csrf_token']) ?>;

let filters     = {search:'',categories:[],priceRange:null,inStock:false};
let currentPage = 'home';
const loadedPages = new Set(['home']);

function setTheme(cls,btn){document.querySelectorAll('.theme-dot').forEach(d=>d.classList.remove('active'));if(btn)btn.classList.add('active');document.body.className=cls||'';try{localStorage.setItem('vg_theme',cls||'');}catch(_){}}
function toggleSidebar(){const sb=document.getElementById('sidebar');sb.classList.toggle('collapsed');try{localStorage.setItem('sidebarCollapsed',sb.classList.contains('collapsed'));}catch(_){}}
function toggleMobileMenu(){document.getElementById('sidebar').classList.toggle('mobile-open');document.getElementById('mobileOverlay').classList.toggle('active');}
function closeMobileMenu(){document.getElementById('sidebar').classList.remove('mobile-open');document.getElementById('mobileOverlay').classList.remove('active');}

function navClick(page,el){
    if(page==='home'){window.location.href='dashboard_person.php';return;}
    document.querySelectorAll('.filters-container .filter-option[onclick^="navClick"]').forEach(i=>i.classList.remove('active'));
    if(el)el.classList.add('active');
    navigateTo(page);
    closeMobileMenu();
}

function toggleCategoryFilter(val,el){el.classList.toggle('active');if(el.classList.contains('active'))filters.categories.push(val);else filters.categories=filters.categories.filter(c=>c!==val);currentPage!=='home'?navigateTo('home'):loadProducts();}
function togglePriceFilter(val,el){const was=el.classList.contains('active');document.querySelectorAll('.filters-container .filter-option[onclick^="togglePriceFilter"]').forEach(i=>i.classList.remove('active'));if(!was){el.classList.add('active');filters.priceRange=val;}else{filters.priceRange=null;}currentPage!=='home'?navigateTo('home'):loadProducts();}
function toggleStockFilter(el){el.classList.toggle('active');filters.inStock=el.classList.contains('active');currentPage!=='home'?navigateTo('home'):loadProducts();}
function resetFilters(){filters={search:'',categories:[],priceRange:null,inStock:false};const si=document.getElementById('searchInput');if(si)si.value='';document.querySelectorAll('.filters-container .filter-option').forEach(el=>el.classList.remove('active'));const h=document.querySelector('.filters-container .filter-option[onclick*="home"]');if(h)h.classList.add('active');loadProducts();}

async function updateStatsSmooth(){try{const res=await fetch('actions/get_stats.php');if(!res.ok)return;const data=await res.json();if(!data.success)return;const visto=localStorage.getItem('pedidosVisualizados');let count=data.data.pedidos_em_andamento;if(visto&&(Date.now()-parseInt(visto))<86400000)count=0;['header-pedidos-badge','mobile-pedidos-badge'].forEach(id=>{const el=document.getElementById(id);if(el){el.textContent=count;el.style.display=count>0?'':'none';}});const sp=document.getElementById('sidebar-pedidos-pip');const sv=document.getElementById('stat-pedidos');if(sp){sp.textContent=count;sp.style.display=count>0?'':'none';}if(sv)sv.textContent=count;}catch(_){}}

async function clearOrdersBadge(){['header-pedidos-badge','mobile-pedidos-badge'].forEach(id=>{const el=document.getElementById(id);if(el)el.classList.add('hiding');});setTimeout(()=>{['header-pedidos-badge','mobile-pedidos-badge'].forEach(id=>{const el=document.getElementById(id);if(el){el.style.display='none';el.textContent='0';el.classList.remove('hiding');}});const sp=document.getElementById('sidebar-pedidos-pip');if(sp){sp.style.display='none';sp.textContent='0';}},300);try{await fetch('actions/mark_orders_viewed.php',{method:'POST'});localStorage.setItem('pedidosVisualizados',Date.now().toString());}catch(_){}}

async function navigateTo(page){
    if(window._isNavigating)return;
    if(page==='home'){window.location.href='dashboard_person.php';return;}
    if(currentPage===page)return;
    window._isNavigating=true;
    try{
        closeMobileMenu();
        window.GlobalModuleManager.setActivePage(page);
        document.querySelectorAll('.mobile-nav-item').forEach(btn=>btn.classList.toggle('active',btn.dataset.page===page));
        document.querySelectorAll('.dynamic-content').forEach(c=>c.classList.remove('active'));
        const div=document.getElementById(`content-${page}`);
        if(!div)return;
        div.classList.add('active');
        currentPage=page;
        window.history.pushState({page},'',`?page=${page}`);
        if(page==='meus_pedidos')await clearOrdersBadge();
        if(!loadedPages.has(page)){await loadPageContent(page);loadedPages.add(page);}

        /* Inicializar carrinho ao mostrar */
        if(page==='carrinho') initCartEmbed();

        window.scrollTo({top:0,behavior:'smooth'});
    }finally{setTimeout(()=>{window._isNavigating=false;},300);}
}

async function loadPageContent(page){
    const div=document.getElementById(`content-${page}`);
    const loader=div?.querySelector('.content-loading');
    if(loader)loader.classList.add('active');
    try{
        /* Carrinho: usa cart.php da raiz em modo embed */
        const url = page === 'carrinho'
            ? BASE_URL + '/cart.php?embed=1&t=' + Date.now()
            : `pages/${page}.php?t=${Date.now()}`;
        const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});
        if(!res.ok)throw new Error(`HTTP ${res.status}`);
        const html=await res.text();
        if(loader)loader.remove();
        div.innerHTML=html;
        if(page === 'carrinho'){
            /* Remover scripts do cart.php — inicializar via initCartEmbed() */
            /* Remover scripts do cart.php — causam conflito de const */
            div.querySelectorAll('script').forEach(s=>s.remove());
            /* Manter os <style> do cart.php mas neutralizar o :root
             * para não sobrescrever as variáveis CSS do dashboard */
            div.querySelectorAll('style').forEach(s=>{
                s.textContent = s.textContent
                    .replace(/:root\s*\{[^}]*\}/g, '#content-carrinho {$&}'.replace(':root',''))
                    .replace(/:root/g,'#content-carrinho');
            });
            initCartEmbed();
        } else {
            div.querySelectorAll('script').forEach(s=>{
                const ns=document.createElement('script');
                if(s.src)ns.src=s.src;else ns.textContent=s.textContent;
                ns.async=false;document.body.appendChild(ns);
                setTimeout(()=>{if(document.body.contains(ns))document.body.removeChild(ns);},100);
            });
        }
    }catch(e){
        if(div)div.innerHTML=`<div class="empty-state"><i class="fa-solid fa-triangle-exclamation"></i><h3>Erro ao carregar</h3><p>${e.message}</p><button class="btn-filter-reset" onclick="loadPageContent('${page}')" style="margin-top:12px;justify-content:center"><i class="fa-solid fa-rotate-right"></i> Tentar novamente</button></div>`;
    }
}

window.addEventListener('popstate',e=>{if(!e.state?.page||e.state.page==='home'){window.location.href='dashboard_person.php';return;}navigateTo(e.state.page);});

/* ════════════════════════════════════════════════════════════════
   CART EMBED — inicialização após o HTML do cart.php ser injectado.
   Os <script> do cart.php são removidos antes de chegar aqui.
   Esta função usa os dados já renderizados pelo PHP no HTML embed.
════════════════════════════════════════════════════════════════ */
function initCartEmbed(){
    const CSRF_C = CSRF_TOKEN;
    const LS_KEY = 'vsg_cart_v2';

    function fmtMZN(v){
        return 'MT ' + new Intl.NumberFormat('pt-MZ',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v);
    }
    function updateBadges(n){
        document.querySelectorAll('.cart-badge,.cart-count-badge,[data-cart-count]').forEach(b=>{
            b.textContent=n>99?'99+':n; b.style.display=n>0?'':'none';
        });
    }
    function ajaxC(body){
        return fetch(BASE_URL+'/ajax/ajax_cart.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:body+'&csrf_token='+CSRF_C
        }).then(r=>r.json());
    }

    function recalc(){
        let sub=0;
        document.querySelectorAll('#content-carrinho .cart-item').forEach(el=>{
            sub+=parseFloat(el.dataset.price||0)*parseInt(el.querySelector('.qty-val')?.value||0);
        });
        const ship=sub>=2500?0:(sub>0?150:0);
        const ss=document.getElementById('sumSubtotal');
        const sv=document.getElementById('sumShipping');
        const st=document.getElementById('sumTotal');
        const sn=document.getElementById('sumShippingNote');
        const bt=document.getElementById('btnCheckout');
        if(ss) ss.textContent=fmtMZN(sub);
        if(sv){ sv.textContent=ship===0?'Grátis':fmtMZN(ship); sv.className='sum-val'+(ship===0?' sum-free':''); }
        if(sn) sn.style.display=ship>0?'flex':'none';
        if(st) st.textContent=fmtMZN(sub+ship);
        if(bt) bt.disabled=(sub===0);
        const total=Array.from(document.querySelectorAll('#content-carrinho .cart-item'))
            .reduce((a,el)=>a+(parseInt(el.querySelector('.qty-val')?.value||0)),0);
        updateBadges(total);
        const lbl=document.getElementById('cartCountLabel');
        if(lbl) lbl.textContent=total+(total===1?' item':' itens');
        const acts=document.getElementById('cartActions');
        if(acts) acts.style.display=total>0?'flex':'none';
    }

    /* Ligar botão Finalizar Compra */
    const btnCO=document.getElementById('btnCheckout');
    if(btnCO){ btnCO.removeAttribute('onclick'); btnCO.onclick=()=>{ window.location.href=BASE_URL+'/checkout.php'; }; }

    /* Ligar botão Continuar */
    document.querySelectorAll('#content-carrinho .btn-continue').forEach(b=>{
        b.removeAttribute('onclick'); b.onclick=()=>{ window.location.href=BASE_URL+'/shopping.php'; };
    });

    /* Ligar botão Esvaziar */
    document.querySelectorAll('#content-carrinho [onclick*="Cart.clear"]').forEach(b=>{
        b.removeAttribute('onclick');
        b.onclick=()=>{
            if(!confirm('Esvaziar o carrinho?')) return;
            ajaxC('action=clear').then(()=>{
                const c=document.getElementById('cartContainer');
                if(c) c.innerHTML=`<div class="cart-empty">
                    <div class="cart-empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                    <h2>O seu carrinho está vazio</h2>
                    <p>Explore o marketplace.</p>
                    <a href="${BASE_URL}/shopping.php" class="btn-shop"><i class="fa-solid fa-border-all"></i> Explorar</a>
                </div>`;
                recalc();
            });
        };
    });

    /* Expor CartQty para os botões gerados pelo cart.php */
    window.CartQty = {
        _t: null,
        update(itemId,qty,pid){
            qty=parseInt(qty); if(isNaN(qty)) return;
            if(qty<=0){ this.remove(itemId,pid); return; }
            const el=document.getElementById('item-'+itemId);
            if(!el) return;
            const stock=parseInt(el.dataset.stock||99);
            const price=parseFloat(el.dataset.price||0);
            if(qty>stock) qty=stock;
            const inp=el.querySelector('.qty-val');
            const tot=document.getElementById('total-'+itemId);
            if(inp) inp.value=qty;
            if(tot) tot.textContent=fmtMZN(price*qty);
            recalc();
            clearTimeout(this._t);
            this._t=setTimeout(()=>{
                ajaxC('action=update&item_id='+itemId+'&quantity='+qty).catch(()=>{});
            },500);
        },
        remove(itemId,pid){
            const el=document.getElementById('item-'+itemId);
            if(!el) return;
            el.style.cssText='opacity:0;transform:translateX(20px);transition:all .2s';
            setTimeout(()=>{
                el.remove(); recalc();
                ajaxC('action=remove&item_id='+itemId).catch(()=>{});
                if(!document.querySelectorAll('#content-carrinho .cart-item').length){
                    const c=document.getElementById('cartContainer');
                    if(c) c.innerHTML=`<div class="cart-empty">
                        <div class="cart-empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                        <h2>O seu carrinho está vazio</h2>
                        <p>Explore o marketplace.</p>
                        <a href="${BASE_URL}/shopping.php" class="btn-shop"><i class="fa-solid fa-border-all"></i> Explorar</a>
                    </div>`;
                }
            },220);
        }
    };

    /* Também expor window.Cart para compatibilidade com onclick="Cart.xxx()" do cart.php */
    window.Cart = {
        updateItem:(id,qty,pid)=>window.CartQty.update(id,qty,pid),
        removeItem:(id,pid)=>window.CartQty.remove(id,pid),
        clear:()=>document.querySelector('#content-carrinho [onclick*="Cart.clear"]')?.click(),
        checkout:()=>{ window.location.href=BASE_URL+'/checkout.php'; }
    };

    /* ── Construir HTML de um item ── */
    function buildItem(item){
        const pid    = item.product_id;
        const itemId = item.item_id;
        const qty    = parseInt(item.quantity||1);
        const price  = parseFloat(item.item_price||0);
        const stock  = parseInt(item.stock||99);
        const avail  = item.available!==false;
        const img    = item.img_url||`https://ui-avatars.com/api/?name=P&size=200&background=00b96b&color=fff`;
        const esc    = s=>String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        return `<div class="cart-item${avail?'':' unavailable'}" id="item-${itemId}" data-pid="${pid}" data-price="${price}" data-stock="${stock}">
          <div class="ci-img"><img src="${esc(img)}" alt="${esc(item.product_name)}" onerror="this.src='https://ui-avatars.com/api/?name=P&size=200&background=00b96b&color=fff'"></div>
          <div class="ci-info">
            <div class="ci-cat"><i class="fa-solid fa-${esc(item.category_icon||'box')}"></i> ${esc(item.category_name||'Geral')}</div>
            <div class="ci-name">${esc(item.product_name)}</div>
            <div class="ci-sup"><i class="fa-solid fa-building"></i> ${esc(item.company_name||'Fornecedor')}</div>
            <div class="ci-price-row">
              <div class="qty-ctrl">
                <button class="qty-btn" onclick="window.CartQty.update('${itemId}',${qty-1},${pid})"><i class="fa-solid fa-minus"></i></button>
                <input type="number" class="qty-val" value="${qty}" min="1" max="${stock}" onchange="window.CartQty.update('${itemId}',this.value,${pid})">
                <button class="qty-btn" onclick="window.CartQty.update('${itemId}',${qty+1},${pid})" ${qty>=stock?'disabled':''}><i class="fa-solid fa-plus"></i></button>
              </div>
              <span class="ci-unit-price">${fmtMZN(price)} / un.</span>
            </div>
          </div>
          <div class="ci-right">
            <div class="ci-total" id="total-${itemId}">${fmtMZN(price*qty)}</div>
            <button class="ci-remove" onclick="window.CartQty.remove('${itemId}',${pid})"><i class="fa-solid fa-trash-can"></i></button>
          </div>
        </div>`;
    }

    function renderItems(items){
        const container = document.getElementById('cartContainer');
        const skeleton  = document.getElementById('cartSkeleton');
        const actions   = document.getElementById('cartActions');
        const label     = document.getElementById('cartCountLabel');
        if(skeleton) skeleton.remove();
        if(!container) return;

        if(!items.length){
            container.innerHTML=`<div class="cart-empty">
                <div class="cart-empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <h2>O seu carrinho está vazio</h2>
                <p>Explore o marketplace e encontre produtos incríveis.</p>
                <a href="${BASE_URL}/shopping.php" class="btn-shop"><i class="fa-solid fa-border-all"></i> Explorar Produtos</a>
            </div>`;
            if(actions) actions.style.display='none';
            if(label) label.textContent='0 itens';
            recalc();
            return;
        }

        const list = document.createElement('div');
        list.className='cart-list';
        items.forEach(item=>{ list.innerHTML+=buildItem(item); });
        container.innerHTML='';
        container.appendChild(list);
        if(actions) actions.style.display='flex';
        recalc();
    }

    /* ── Carregar itens via endpoint ── */
    fetch('actions/get_cart.php',{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json())
        .then(d=>renderItems(d.success?d.items:[]))
        .catch(()=>renderItems([]));
}

/* ════════════════════════════════════════════════════════════════
   Produtos
════════════════════════════════════════════════════════════════ */
const _esc=t=>String(t||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const _trunc=(t,max)=>!t?'':t.length<=max?t:t.substring(0,max)+'…';
const _isNew=d=>Math.floor((Date.now()-new Date(d))/86400000)<=7;
const _fmtP=p=>parseFloat(p).toLocaleString('pt-MZ',{minimumFractionDigits:2,maximumFractionDigits:2});
const _catIco={reciclavel:'fa-recycle',sustentavel:'fa-seedling',servico:'fa-screwdriver-wrench',visiongreen:'fa-leaf',ecologico:'fa-earth-africa',outro:'fa-box'};
const _catNm={reciclavel:'Reciclável',sustentavel:'Sustentável',servico:'Serviço',visiongreen:'VisionGreen',ecologico:'Ecológico',outro:'Outros'};

async function loadProducts(){
    const grid=document.getElementById('productsGrid');
    const lbar=document.getElementById('loadingBar');
    if(!grid)return;
    if(lbar)lbar.classList.add('active');
    try{
        const p=new URLSearchParams({search:filters.search,categories:filters.categories.join(','),price_range:filters.priceRange||'',in_stock:filters.inStock?'1':''});
        const res=await fetch(`actions/get_products.php?${p}`);
        if(!res.ok)throw new Error('HTTP '+res.status);
        const data=await res.json();
        if(data.success&&data.products.length>0){
            const favs=JSON.parse(localStorage.getItem('favorites')||'[]');
            grid.innerHTML=data.products.map((p,idx)=>{
                const imgSrc=p.imagem?`../../uploads/products/${_esc(p.imagem)}`:'';
                const fav=favs.includes(p.id);
                const novo=_isNew(p.created_at);
                let badge='';
                if(p.stock===0)badge='<span class="product-badge out-of-stock"><i class="fa-solid fa-circle-xmark" style="font-size:8px;margin-right:2px"></i>Esgotado</span>';
                else if(p.stock<=p.stock_minimo)badge='<span class="product-badge stock-low"><i class="fa-solid fa-triangle-exclamation" style="font-size:8px;margin-right:2px"></i>Últimas unidades</span>';
                else if(novo)badge='<span class="product-badge is-new">NOVO</span>';
                return `<div class="product-card" style="animation-delay:${idx*.04}s">
                    <div class="product-company"><i class="fa-solid fa-store"></i><span>Distribuída por</span><span>${_esc(p.empresa_nome||'VisionGreen')}</span></div>
                    <div class="product-image">
                        ${imgSrc?`<img src="${imgSrc}" alt="${_esc(p.nome)}" loading="lazy" onerror="handleImgErr(this,'${_esc(p.empresa_nome||'VisionGreen')}')">`:
                        `<div class="no-image-placeholder"><i class="fa-solid fa-leaf" style="color:var(--txt3);font-size:24px"></i></div>`}
                        ${badge}
                        <button class="btn-favorite ${fav?'active':''}" onclick="event.stopPropagation();toggleFav(${p.id},this)">
                            <i class="fa-${fav?'solid':'regular'} fa-heart"></i>
                        </button>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><i class="fa-solid ${_catIco[p.categoria]||'fa-box'}"></i>${_catNm[p.categoria]||p.categoria||'Outros'}</div>
                        <div class="product-name" title="${_esc(p.nome)}">${_esc(p.nome)}</div>
                        <div class="product-description">${_esc(_trunc(p.descricao,80))}</div>
                        <div class="product-footer">
                            <div class="product-price">${_fmtP(p.preco)}<small>MZN</small></div>
                            <span class="product-stock ${p.stock===0?'out':p.stock<=p.stock_minimo?'low':''}">${p.stock===0?'Esgotado':`${p.stock} un`}</span>
                        </div>
                        <div class="product-actions">
                            <button class="btn-add-cart" onclick="event.stopPropagation();addToCart(${p.id},'${_esc(p.nome)}',${p.preco},this)" ${p.stock===0?'disabled':''}>
                                <i class="fa-solid fa-cart-plus"></i> Adicionar
                            </button>
                            <button class="btn-buy-now" onclick="event.stopPropagation();buyNow(${p.id})" ${p.stock===0?'disabled':''}>
                                <i class="fa-solid fa-bolt"></i> Comprar
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');
            grid.querySelectorAll('.product-card').forEach(card=>{
                card.addEventListener('click',function(e){if(!e.target.closest('button')){const m=this.querySelector('.btn-buy-now')?.getAttribute('onclick')?.match(/buyNow\((\d+)\)/);if(m)viewProduct(m[1]);}});
            });
        }else{
            grid.innerHTML='<div class="empty-state"><i class="fa-solid fa-box-open"></i><h3>Nenhum produto encontrado</h3><p>Tente ajustar os filtros</p></div>';
        }
    }catch(e){
        grid.innerHTML=`<div class="empty-state"><i class="fa-solid fa-triangle-exclamation"></i><h3>Erro ao carregar produtos</h3><p>${e.message}</p><button class="btn-filter-reset" onclick="loadProducts()" style="margin-top:12px;justify-content:center"><i class="fa-solid fa-rotate-right"></i> Tentar novamente</button></div>`;
    }finally{if(lbar)lbar.classList.remove('active');}
}

function handleImgErr(img,company){const c=img.parentElement;const ph=document.createElement('div');ph.className='no-image-placeholder';ph.innerHTML=`<span>Distribuído por:</span><strong>${_esc(company)}</strong>`;img.remove();c.prepend(ph);}

function addToCart(id,name,price,btn){
    const orig=btn.innerHTML;
    btn.innerHTML='<i class="fa-solid fa-check"></i> Adicionado!';
    btn.style.cssText='background:var(--primary);color:#fff;border-color:var(--primary)';
    let cart=JSON.parse(localStorage.getItem('vsg_cart_v2')||'{}');
    if(cart[id])cart[id].qty++;else cart[id]={qty:1,name,price};
    localStorage.setItem('vsg_cart_v2',JSON.stringify(cart));
    updateCartBadge();
    showToast(`<strong>${_esc(name)}</strong> adicionado ao carrinho!`,'success');
    fetch(BASE_URL+'/ajax/ajax_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:`action=add&product_id=${id}&quantity=1&csrf_token=${CSRF_TOKEN}`}).catch(()=>{});
    setTimeout(()=>{btn.innerHTML=orig;btn.style.cssText='';},2000);
}
function buyNow(id){window.location.href=BASE_URL+`/checkout.php?buy_now=${id}&qty=1`;}
function toggleFav(id,btn){const icon=btn.querySelector('i');let favs=JSON.parse(localStorage.getItem('favorites')||'[]');if(favs.includes(id)){favs=favs.filter(x=>x!==id);icon.className='fa-regular fa-heart';btn.classList.remove('active');showToast('Removido dos favoritos','warning');}else{favs.push(id);icon.className='fa-solid fa-heart';btn.classList.add('active');showToast('Adicionado aos favoritos!','success');}localStorage.setItem('favorites',JSON.stringify(favs));}
function viewProduct(id){window.location.href=BASE_URL+`/product.php?id=${id}`;}
function updateCartBadge(){const cart=JSON.parse(localStorage.getItem('vsg_cart_v2')||'{}');const total=Object.values(cart).reduce((s,i)=>s+(i.qty||0),0);document.querySelectorAll('.cart-badge').forEach(b=>{b.textContent=total;b.style.display=total>0?'flex':'none';});document.querySelectorAll('.cart-count-badge').forEach(b=>{b.textContent=total;b.style.display=total>0?'':'none';});}

function showToast(message,type){const colors={success:'var(--primary)',error:'var(--danger)',warning:'var(--warn)',info:'var(--link)'};const icons={success:'fa-check',error:'fa-xmark',warning:'fa-triangle-exclamation',info:'fa-circle-info'};const t=document.createElement('div');t.className='vg-toast';t.style.borderLeftColor=colors[type]||colors.info;t.style.borderLeftWidth='3px';t.innerHTML=message;const dot=document.createElement('div');dot.className='vg-toast-icon';dot.style.background=colors[type]||colors.info;dot.innerHTML=`<i class="fa-solid ${icons[type]||icons.info}" style="font-size:9px"></i>`;t.appendChild(dot);document.body.appendChild(t);setTimeout(()=>{t.style.animation='toastOut .25s ease forwards';setTimeout(()=>t.remove(),250);},3000);}

let _searchTimer;
document.getElementById('searchInput')?.addEventListener('input',function(){clearTimeout(_searchTimer);_searchTimer=setTimeout(()=>{filters.search=this.value.trim();currentPage!=='home'?navigateTo('home'):loadProducts();},400);});

document.addEventListener('DOMContentLoaded',function(){
    if(window._domLoaded)return;
    window._domLoaded=true;
    try{const saved=localStorage.getItem('vg_theme')||'';if(saved){document.body.className=saved;const dot=document.querySelector(`.theme-dot[onclick*="${CSS.escape(saved)}"]`);if(dot){document.querySelectorAll('.theme-dot').forEach(d=>d.classList.remove('active'));dot.classList.add('active');}}}catch(_){}
    try{if(localStorage.getItem('sidebarCollapsed')==='true')document.getElementById('sidebar').classList.add('collapsed');}catch(_){}
    try{const visto=localStorage.getItem('pedidosVisualizados');if(visto&&(Date.now()-parseInt(visto))<86400000){['header-pedidos-badge','mobile-pedidos-badge'].forEach(id=>{const el=document.getElementById(id);if(el){el.style.display='none';el.textContent='0';}});const sp=document.getElementById('sidebar-pedidos-pip');if(sp)sp.style.display='none';}}catch(_){}
    const page=new URLSearchParams(window.location.search).get('page')||'home';
    if(page!=='home'){
        navigateTo(page);
    }else{
        loadProducts();
        const iv=setInterval(updateStatsSmooth,30000);
        window.GlobalModuleManager.registerInterval('dashboard_stats',iv);
    }
    updateCartBadge();
});

document.addEventListener('click',function(e){const sb=document.getElementById('sidebar');const btn=document.querySelector('.mobile-menu-btn');if(sb?.classList.contains('mobile-open')&&!sb.contains(e.target)&&!btn?.contains(e.target))closeMobileMenu();});
new MutationObserver(mutations=>{mutations.forEach(m=>{document.body.style.overflow=m.target.classList.contains('mobile-open')?'hidden':'';});}).observe(document.getElementById('sidebar'),{attributes:true,attributeFilter:['class']});
</script>
</body>
</html>