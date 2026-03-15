<?php
/**
 * pages/person/dashboard_person.php — VSG Marketplace
 * Painel pessoal do cliente (tipo 'person').
 */

/* ── Auth ──────────────────────────────────────────────────────── */
define('REQUIRED_TYPE', 'person');
require_once __DIR__ . '/../../registration/middleware/middleware_auth.php';
require_once __DIR__ . '/../../registration/includes/db.php';
require_once __DIR__ . '/../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}
if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin','superadmin'])) {
    header("Location: ../../pages/admin/dashboard.php");
    exit;
}
if ($_SESSION['auth']['type'] !== 'person') {
    header($_SESSION['auth']['type'] === 'company'
        ? "Location: ../business/dashboard_business.php"
        : "Location: ../../registration/login/login.php?error=acesso_proibido"
    );
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

/* ── Dados do utilizador ───────────────────────────────────────── */
$stmt = $mysqli->prepare("
    SELECT nome, apelido, email, telefone, public_id, status,
           registration_step, email_verified_at, created_at, type
    FROM users WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../../registration/login/login.php?error=user_not_found");
    exit;
}
if (!$user['email_verified_at']) {
    header("Location: ../../registration/process/verify_email.php"); exit;
}
if (!$user['public_id']) {
    header("Location: ../../registration/register/gerar_uid.php"); exit;
}
if ($user['status'] === 'blocked') {
    die('Acesso restrito: Sua conta está bloqueada. Contacte o suporte técnico.');
}

/* ── Dados de exibição ─────────────────────────────────────────── */
$displayName   = $user['apelido'] ?: $user['nome'];
$firstName     = explode(' ', trim($displayName))[0];
$nameParts     = explode(' ', trim($displayName));
$initials      = strtoupper(substr($nameParts[0],0,1) . (isset($nameParts[1]) ? substr($nameParts[1],0,1) : ''));
$displayAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($displayName)
               . '&background=1a7f37&color=ffffff&bold=true&size=64';

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');

/* ── Stats — usando prepared statements ────────────────────────── */
$stats = ['mensagens_nao_lidas' => 0, 'pedidos_em_andamento' => 0,
          'total_gasto' => 0, 'entregues' => 0];

$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM notifications WHERE receiver_id = ? AND status = 'nao_lida'");
$st->bind_param('i', $userId); $st->execute();
$stats['mensagens_nao_lidas'] = (int)$st->get_result()->fetch_assoc()['n']; $st->close();

$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM orders WHERE customer_id = ? AND status IN ('pendente','confirmado','processando')");
$st->bind_param('i', $userId); $st->execute();
$stats['pedidos_em_andamento'] = (int)$st->get_result()->fetch_assoc()['n']; $st->close();

$st = $mysqli->prepare("SELECT COALESCE(SUM(total),0) AS n FROM orders WHERE customer_id = ? AND payment_status = 'pago'");
$st->bind_param('i', $userId); $st->execute();
$stats['total_gasto'] = (float)$st->get_result()->fetch_assoc()['n']; $st->close();

$st = $mysqli->prepare("SELECT COUNT(*) AS n FROM orders WHERE customer_id = ? AND status = 'entregue'");
$st->bind_param('i', $userId); $st->execute();
$stats['entregues'] = (int)$st->get_result()->fetch_assoc()['n']; $st->close();

/* ── Pedidos recentes ──────────────────────────────────────────── */
$stmt = $mysqli->prepare("
    SELECT o.id, o.order_number, o.order_date, o.status, o.payment_status,
           o.payment_method, o.total, o.currency, o.shipping_address,
           o.shipping_city, o.customer_notes, o.created_at,
           COALESCE(u.nome,'Empresa Desconhecida') AS empresa_nome,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items_count,
           (SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = o.id) AS total_items
    FROM orders o
    LEFT JOIN users u ON o.company_id = u.id
    WHERE o.customer_id = ? AND o.deleted_at IS NULL
    ORDER BY o.order_date DESC
    LIMIT 50
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Maps ──────────────────────────────────────────────────────── */
$statusMap = [
    'pendente'    => ['icon'=>'fa-clock',        'fa_color'=>'var(--warn)',    'label'=>'Pendente',    'class'=>'status-pendente'],
    'confirmado'  => ['icon'=>'fa-circle-check', 'fa_color'=>'var(--link)',    'label'=>'Confirmado',  'class'=>'status-confirmado'],
    'processando' => ['icon'=>'fa-gear',          'fa_color'=>'var(--link)',    'label'=>'Processando', 'class'=>'status-processando'],
    'enviado'     => ['icon'=>'fa-truck-fast',   'fa_color'=>'var(--link)',    'label'=>'Enviado',     'class'=>'status-enviado'],
    'entregue'    => ['icon'=>'fa-circle-check', 'fa_color'=>'var(--primary)', 'label'=>'Entregue',    'class'=>'status-entregue'],
    'cancelado'   => ['icon'=>'fa-circle-xmark', 'fa_color'=>'var(--danger)',  'label'=>'Cancelado',   'class'=>'status-cancelado'],
];
$paymentStatusMap = [
    'pendente'    => ['label'=>'Aguardando'],
    'pago'        => ['label'=>'Pago'],
    'parcial'     => ['label'=>'Parcial'],
    'reembolsado' => ['label'=>'Reembolsado'],
];
$paymentMethodMap = [
    'mpesa'      => ['label'=>'M-Pesa'],
    'emola'      => ['label'=>'E-Mola'],
    'visa'       => ['label'=>'Visa'],
    'mastercard' => ['label'=>'Mastercard'],
    'manual'     => ['label'=>'Manual'],
];
$categoryLabels = [
    'reciclavel'  => ['icon'=>'fa-recycle',           'label'=>'Reciclável'],
    'sustentavel' => ['icon'=>'fa-seedling',          'label'=>'Sustentável'],
    'servico'     => ['icon'=>'fa-screwdriver-wrench','label'=>'Serviços'],
    'visiongreen' => ['icon'=>'fa-leaf',              'label'=>'VisionGreen'],
    'ecologico'   => ['icon'=>'fa-earth-africa',      'label'=>'Ecológico'],
    'outro'       => ['icon'=>'fa-box',               'label'=>'Outros'],
];
$priceRanges = [
    ['min'=>0,     'max'=>1000,   'label'=>'Até 1.000 MZN'],
    ['min'=>1000,  'max'=>5000,   'label'=>'1.000 – 5.000 MZN'],
    ['min'=>5000,  'max'=>10000,  'label'=>'5.000 – 10.000 MZN'],
    ['min'=>10000, 'max'=>999999, 'label'=>'Acima de 10.000 MZN'],
];
$pedidosStats = [
    'total'        => count($orders),
    'pendentes'    => count(array_filter($orders, fn($o) => $o['status'] === 'pendente')),
    'em_andamento' => count(array_filter($orders, fn($o) => in_array($o['status'], ['confirmado','processando','enviado']))),
    'entregues'    => count(array_filter($orders, fn($o) => $o['status'] === 'entregue')),
];

/*
 * CAMINHOS BASE
 * dashboard_person.php está em pages/person/
 * A raiz do projecto está em ../../
 * Calculamos o base path para uso no JS (AJAX, redirects).
 */
$_sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$_parts = explode('/', $_sn);
array_splice($_parts, -3);     // remove ficheiro + person + pages
$base  = implode('/', $_parts); // ex: '/vsg'
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

<!-- ── SIDEBAR ─────────────────────────────────────────────────── -->
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
            <div class="sidebar-urole">
                <i class="fa-solid fa-circle-check" style="font-size:8px;margin-right:3px"></i>Cliente
            </div>
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
                <?php if ($stats['pedidos_em_andamento'] > 0): ?>
                <span class="nav-pip" id="sidebar-pedidos-pip"><?= $stats['pedidos_em_andamento'] ?></span>
                <?php endif; ?>
            </div>
            <div class="filter-option" onclick="navClick('notificacoes',this)">
                <i class="fa-solid fa-bell"></i><label>Notificações</label>
                <?php if ($stats['mensagens_nao_lidas'] > 0): ?>
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

<!-- ── MAIN ──────────────────────────────────────────────────────── -->
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
                    <span class="badge" id="header-pedidos-badge"
                          style="<?= $stats['pedidos_em_andamento'] > 0 ? '' : 'display:none' ?>">
                        <?= $stats['pedidos_em_andamento'] ?>
                    </span>
                </button>
                <button class="icon-btn" onclick="navigateTo('notificacoes')" title="Notificações">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($stats['mensagens_nao_lidas'] > 0): ?>
                    <span class="badge"><?= $stats['mensagens_nao_lidas'] ?></span>
                    <?php endif; ?>
                </button>
                <div class="user-profile" onclick="navigateTo('perfil')">
                    <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($firstName) ?></span>
                        <span class="user-role">
                            <i class="fa-solid fa-circle-check" style="font-size:7px;margin-right:2px"></i>Cliente
                        </span>
                    </div>
                    <i class="fa-solid fa-chevron-down" style="font-size:9px;color:var(--txt3)"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- ── CONTENT ──────────────────────────────────────────────── -->
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
                        <?php if ($stats['pedidos_em_andamento'] > 0): ?>
                        &nbsp;·&nbsp;
                        <i class="fa-solid fa-circle-notch fa-spin" style="font-size:9px;color:var(--warn)"></i>
                        <?= $stats['pedidos_em_andamento'] ?> pedido<?= $stats['pedidos_em_andamento'] > 1 ? 's' : '' ?> em andamento
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
                        <div class="order-num"><?= htmlspecialchars($order['order_number'] ?: '#VG-'.$order['id']) ?></div>
                        <div class="order-meta">
                            <i class="fa-regular fa-building"></i>
                            <?= htmlspecialchars($order['empresa_nome']) ?>
                            &nbsp;·&nbsp;
                            <i class="fa-regular fa-calendar"></i>
                            <?= date('d M', strtotime($order['order_date'])) ?>
                        </div>
                    </div>
                    <span class="order-status <?= $sm['class'] ?>">
                        <i class="fa-solid <?= $sm['icon'] ?>"></i> <?= $sm['label'] ?>
                    </span>
                    <div class="order-value">
                        <?= number_format((float)$order['total'],2,',','.') ?> MZN
                        <small><?= $order['total_items'] ?> iten<?= $order['total_items'] != 1 ? 's' : '' ?></small>
                    </div>
                    <i class="fa-solid fa-chevron-right order-chevron"></i>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sub-páginas carregadas via AJAX -->
        <?php foreach (['meus_pedidos','notificacoes','perfil','configuracoes','carrinho','produtos'] as $pg): ?>
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
            <span class="mobile-nav-badge" id="mobile-pedidos-badge"
                  style="<?= $stats['pedidos_em_andamento'] > 0 ? '' : 'display:none' ?>">
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
            <?php if ($stats['mensagens_nao_lidas'] > 0): ?>
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
/* ════════════════════════════════════════════════════════════════
   GlobalModuleManager — evita intervalos duplicados entre navigações
════════════════════════════════════════════════════════════════ */
(function(){
    'use strict';
    if(window.GlobalModuleManager) window.GlobalModuleManager.clearAll();
    window.GlobalModuleManager = {
        _iv: new Map(), _mods: new Set(), currentPage: null,
        registerInterval(n,id){ if(this._iv.has(n)) clearInterval(this._iv.get(n)); this._iv.set(n,id); },
        clearInterval(n){ if(this._iv.has(n)){ clearInterval(this._iv.get(n)); this._iv.delete(n); } },
        clearAllExcept(ex){
            this._iv.forEach((id,n)=>{ if(n!==ex) clearInterval(id); });
            if(ex&&this._iv.has(ex)){ const k=this._iv.get(ex); this._iv.clear(); this._iv.set(ex,k); } else this._iv.clear();
        },
        clearAll(){ this._iv.forEach(id=>clearInterval(id)); this._iv.clear(); this._mods.clear(); },
        setActivePage(page){
            if(this.currentPage===page) return;
            this.currentPage=page;
            const keep={home:'dashboard_stats',notificacoes:'NotificationsModule',meus_pedidos:'OrdersModule',carrinho:'CartModule'};
            const m=keep[page];
            if(m) this.clearAllExcept(m); else this.clearAll();
        }
    };
    window.addEventListener('beforeunload',()=>window.GlobalModuleManager.clearAll());
    window.addEventListener('pageshow',e=>{ if(e.persisted) window.GlobalModuleManager.clearAll(); });
})();

/* ════════════════════════════════════════════════════════════════
   Dados PHP → JS
════════════════════════════════════════════════════════════════ */
const userData = <?= json_encode([
    'userId'   => $userId,
    'nome'     => $displayName,
    'firstName'=> $firstName,
    'email'    => $user['email'],
    'publicId' => $user['public_id'],
    'initials' => $initials,
], JSON_UNESCAPED_UNICODE) ?>;

const ordersData       = <?= json_encode($orders,           JSON_UNESCAPED_UNICODE) ?>;
const pedidosStats     = <?= json_encode($pedidosStats,     JSON_UNESCAPED_UNICODE) ?>;
const statusMapJS      = <?= json_encode($statusMap,        JSON_UNESCAPED_UNICODE) ?>;
const paymentStatusMapJS = <?= json_encode($paymentStatusMap, JSON_UNESCAPED_UNICODE) ?>;
const paymentMethodMapJS = <?= json_encode($paymentMethodMap, JSON_UNESCAPED_UNICODE) ?>;

/*
 * BASE_URL — raiz do projecto, calculada pelo PHP.
 * Usado para construir caminhos correctos em fetch() e redirects,
 * independentemente do ambiente (XAMPP local ou servidor).
 * Ex: /VSG  →  fetch(BASE_URL + '/ajax/ajax_cart.php')
 */
const BASE_URL = <?= json_encode($base) ?>;

/* ════════════════════════════════════════════════════════════════
   Estado
════════════════════════════════════════════════════════════════ */
let filters     = { search:'', categories:[], priceRange:null, inStock:false };
let currentPage = 'home';
const loadedPages = new Set(['home']);

/* ════════════════════════════════════════════════════════════════
   Tema
════════════════════════════════════════════════════════════════ */
function setTheme(cls, btn){
    document.querySelectorAll('.theme-dot').forEach(d=>d.classList.remove('active'));
    if(btn) btn.classList.add('active');
    document.body.className = cls||'';
    try{ localStorage.setItem('vg_theme', cls||''); }catch(_){}
}

/* ════════════════════════════════════════════════════════════════
   Sidebar / Menu
════════════════════════════════════════════════════════════════ */
function toggleSidebar(){
    const sb=document.getElementById('sidebar');
    sb.classList.toggle('collapsed');
    try{ localStorage.setItem('sidebarCollapsed', sb.classList.contains('collapsed')); }catch(_){}
}
function toggleMobileMenu(){
    document.getElementById('sidebar').classList.toggle('mobile-open');
    document.getElementById('mobileOverlay').classList.toggle('active');
}
function closeMobileMenu(){
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('mobileOverlay').classList.remove('active');
}
function navClick(page, el){
    if(page==='home'){ window.location.href='dashboard_person.php'; return; }
    document.querySelectorAll('.filters-container .filter-option[onclick^="navClick"]')
            .forEach(i=>i.classList.remove('active'));
    if(el) el.classList.add('active');
    navigateTo(page);
    closeMobileMenu();
}

/* ════════════════════════════════════════════════════════════════
   Filtros
════════════════════════════════════════════════════════════════ */
function toggleCategoryFilter(val, el){
    el.classList.toggle('active');
    if(el.classList.contains('active')) filters.categories.push(val);
    else filters.categories = filters.categories.filter(c=>c!==val);
    currentPage!=='home' ? navigateTo('home') : loadProducts();
}
function togglePriceFilter(val, el){
    const was = el.classList.contains('active');
    document.querySelectorAll('.filters-container .filter-option[onclick^="togglePriceFilter"]')
            .forEach(i=>i.classList.remove('active'));
    if(!was){ el.classList.add('active'); filters.priceRange=val; }
    else { filters.priceRange=null; }
    currentPage!=='home' ? navigateTo('home') : loadProducts();
}
function toggleStockFilter(el){
    el.classList.toggle('active');
    filters.inStock = el.classList.contains('active');
    currentPage!=='home' ? navigateTo('home') : loadProducts();
}
function resetFilters(){
    filters={search:'',categories:[],priceRange:null,inStock:false};
    const si=document.getElementById('searchInput');
    if(si) si.value='';
    document.querySelectorAll('.filters-container .filter-option').forEach(el=>el.classList.remove('active'));
    const homeItem=document.querySelector('.filters-container .filter-option[onclick*="home"]');
    if(homeItem) homeItem.classList.add('active');
    loadProducts();
}

/* ════════════════════════════════════════════════════════════════
   Stats polling (a cada 30s — não 5s para não sobrecarregar)
════════════════════════════════════════════════════════════════ */
async function updateStatsSmooth(){
    try{
        const res  = await fetch('actions/get_stats.php');
        if(!res.ok) return;
        const data = await res.json();
        if(!data.success) return;

        const visto = localStorage.getItem('pedidosVisualizados');
        let count   = data.data.pedidos_em_andamento;
        if(visto && (Date.now()-parseInt(visto)) < 86400000) count=0;

        const ids = ['header-pedidos-badge','mobile-pedidos-badge'];
        ids.forEach(id=>{
            const el=document.getElementById(id);
            if(el){ el.textContent=count; el.style.display=count>0?'':'none'; }
        });
        const sp=document.getElementById('sidebar-pedidos-pip');
        const sv=document.getElementById('stat-pedidos');
        if(sp){ sp.textContent=count; sp.style.display=count>0?'':'none'; }
        if(sv) sv.textContent=count;
    }catch(_){}
}
async function clearOrdersBadge(){
    ['header-pedidos-badge','mobile-pedidos-badge'].forEach(id=>{
        const el=document.getElementById(id);
        if(el) el.classList.add('hiding');
    });
    setTimeout(()=>{
        ['header-pedidos-badge','mobile-pedidos-badge'].forEach(id=>{
            const el=document.getElementById(id);
            if(el){ el.style.display='none'; el.textContent='0'; el.classList.remove('hiding'); }
        });
        const sp=document.getElementById('sidebar-pedidos-pip');
        if(sp){ sp.style.display='none'; sp.textContent='0'; }
    },300);
    try{
        await fetch('actions/mark_orders_viewed.php',{method:'POST'});
        localStorage.setItem('pedidosVisualizados', Date.now().toString());
    }catch(_){}
}

/* ════════════════════════════════════════════════════════════════
   Navegação entre secções (SPA)
════════════════════════════════════════════════════════════════ */
async function navigateTo(page){
    if(window._isNavigating) return;
    if(page==='home'){ window.location.href='dashboard_person.php'; return; }
    if(currentPage===page) return;

    window._isNavigating=true;
    try{
        closeMobileMenu();
        window.GlobalModuleManager.setActivePage(page);

        document.querySelectorAll('.mobile-nav-item')
                .forEach(btn=>btn.classList.toggle('active', btn.dataset.page===page));
        document.querySelectorAll('.dynamic-content').forEach(c=>c.classList.remove('active'));

        const div=document.getElementById(`content-${page}`);
        if(!div) return;
        div.classList.add('active');
        currentPage=page;
        window.history.pushState({page},'',`?page=${page}`);

        if(page==='meus_pedidos') await clearOrdersBadge();
        if(!loadedPages.has(page)){ await loadPageContent(page); loadedPages.add(page); }
        window.scrollTo({top:0,behavior:'smooth'});
    }finally{
        setTimeout(()=>{ window._isNavigating=false; },300);
    }
}

async function loadPageContent(page){
    const div    = document.getElementById(`content-${page}`);
    const loader = div.querySelector('.content-loading');
    if(loader) loader.classList.add('active');
    try{
        // Sub-páginas estão em pages/person/pages/<nome>.php
        // Carrinho usa o cart.php da raiz com modo embed (sem header/footer)
        const url = page === 'carrinho'
            ? BASE_URL + '/cart.php?embed=1&t=' + Date.now()
            : `pages/${page}.php?t=${Date.now()}`;
        const res = await fetch(url, {
            headers: page === 'carrinho' ? {'X-Requested-With':'XMLHttpRequest'} : {}
        });
        if(!res.ok) throw new Error(`HTTP ${res.status}`);
        const html = await res.text();
        if(loader) loader.remove();
        div.innerHTML = html;
        // Executar scripts inline da sub-página
        div.querySelectorAll('script').forEach(s=>{
            const ns=document.createElement('script');
            if(s.src) ns.src=s.src; else ns.textContent=s.textContent;
            ns.async=false;
            document.body.appendChild(ns);
            setTimeout(()=>{ if(document.body.contains(ns)) document.body.removeChild(ns); },100);
        });
    }catch(e){
        div.innerHTML=`
            <div class="empty-state">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <h3>Erro ao carregar</h3>
                <p>Não foi possível carregar a página. Tente novamente.</p>
                <button class="btn-filter-reset" onclick="loadPageContent('${page}')" style="margin-top:12px;justify-content:center">
                    <i class="fa-solid fa-rotate-right"></i> Tentar novamente
                </button>
            </div>`;
    }
}

window.addEventListener('popstate', e=>{
    if(!e.state?.page||e.state.page==='home'){ window.location.href='dashboard_person.php'; return; }
    navigateTo(e.state.page);
});

/* ════════════════════════════════════════════════════════════════
   Produtos
════════════════════════════════════════════════════════════════ */
const _esc = t => String(t||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const _trunc = (t,max) => !t?'': t.length<=max ? t : t.substring(0,max)+'…';
const _isNew  = d => Math.floor((Date.now()-new Date(d))/86400000)<=7;
const _fmtP   = p => parseFloat(p).toLocaleString('pt-MZ',{minimumFractionDigits:2,maximumFractionDigits:2});
const _catIco = {reciclavel:'fa-recycle',sustentavel:'fa-seedling',servico:'fa-screwdriver-wrench',visiongreen:'fa-leaf',ecologico:'fa-earth-africa',outro:'fa-box'};
const _catNm  = {reciclavel:'Reciclável',sustentavel:'Sustentável',servico:'Serviço',visiongreen:'VisionGreen',ecologico:'Ecológico',outro:'Outros'};

async function loadProducts(){
    const grid  = document.getElementById('productsGrid');
    const lbar  = document.getElementById('loadingBar');
    if(!grid) return;
    if(lbar) lbar.classList.add('active');

    try{
        const p = new URLSearchParams({
            search:      filters.search,
            categories:  filters.categories.join(','),
            price_range: filters.priceRange||'',
            in_stock:    filters.inStock?'1':''
        });
        const res  = await fetch(`actions/get_products.php?${p}`);
        if(!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();

        if(data.success && data.products.length > 0){
            const favs = JSON.parse(localStorage.getItem('vsg_favorites')||'[]');
            grid.innerHTML = data.products.map((p,idx)=>{
                /*
                 * Caminho da imagem: o dashboard está em pages/person/
                 * As imagens estão em uploads/products/ (na raiz)
                 * Então o caminho relativo é ../../uploads/products/
                 */
                const imgSrc = p.imagem
                    ? `../../uploads/products/${_esc(p.imagem)}`
                    : '';
                const fav  = favs.includes(p.id);
                const novo = _isNew(p.created_at);

                let badge='';
                if(p.stock===0) badge='<span class="product-badge out-of-stock"><i class="fa-solid fa-circle-xmark" style="font-size:8px;margin-right:2px"></i>Esgotado</span>';
                else if(p.stock<=p.stock_minimo) badge='<span class="product-badge stock-low"><i class="fa-solid fa-triangle-exclamation" style="font-size:8px;margin-right:2px"></i>Últimas unidades</span>';
                else if(novo) badge='<span class="product-badge is-new">NOVO</span>';

                return `
                <div class="product-card" style="animation-delay:${idx*.04}s">
                    <div class="product-company">
                        <i class="fa-solid fa-store"></i>
                        <span>Distribuída por</span>
                        <span>${_esc(p.empresa_nome||'VisionGreen')}</span>
                    </div>
                    <div class="product-image">
                        ${imgSrc
                            ? `<img src="${imgSrc}" alt="${_esc(p.nome)}" loading="lazy"
                                   onerror="handleImgErr(this,'${_esc(p.empresa_nome||'VisionGreen')}')">`
                            : `<div class="no-image-placeholder">
                                   <i class="fa-solid fa-leaf" style="color:var(--txt3);font-size:24px"></i>
                               </div>`
                        }
                        ${badge}
                        <button class="btn-favorite ${fav?'active':''}"
                                onclick="event.stopPropagation();toggleFav(${p.id},this)"
                                title="${fav?'Remover':'Adicionar'} favorito">
                            <i class="fa-${fav?'solid':'regular'} fa-heart"></i>
                        </button>
                    </div>
                    <div class="product-info">
                        <div class="product-category">
                            <i class="fa-solid ${_catIco[p.categoria]||'fa-box'}"></i>
                            ${_catNm[p.categoria]||p.categoria||'Outros'}
                        </div>
                        <div class="product-name" title="${_esc(p.nome)}">${_esc(p.nome)}</div>
                        <div class="product-description">${_esc(_trunc(p.descricao,80))}</div>
                        <div class="product-footer">
                            <div class="product-price">${_fmtP(p.preco)}<small>MZN</small></div>
                            <span class="product-stock ${p.stock===0?'out':p.stock<=p.stock_minimo?'low':''}">
                                ${p.stock===0?'Esgotado':`${p.stock} un`}
                            </span>
                        </div>
                        <div class="product-actions">
                            <button class="btn-add-cart"
                                    onclick="event.stopPropagation();addToCart(${p.id},this,{name:${JSON.stringify(p.nome)},price:${p.preco},stock:${p.stock},img:'${_esc(p.imagem||'')}'})"
                                    ${p.stock===0?'disabled':''}>
                                <i class="fa-solid fa-cart-plus"></i> Adicionar
                            </button>
                            <button class="btn-buy-now"
                                    onclick="event.stopPropagation();buyNow(${p.id})"
                                    ${p.stock===0?'disabled':''}>
                                <i class="fa-solid fa-bolt"></i> Comprar
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');

            grid.querySelectorAll('.product-card').forEach(card=>{
                card.addEventListener('click', function(e){
                    if(!e.target.closest('button')){
                        const m=this.querySelector('.btn-buy-now')?.getAttribute('onclick')?.match(/buyNow\((\d+)\)/);
                        if(m) viewProduct(m[1]);
                    }
                });
            });
        } else {
            grid.innerHTML=`
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>Nenhum produto encontrado</h3>
                    <p>Tente ajustar os filtros ou explore outras categorias</p>
                </div>`;
        }
    }catch(e){
        grid.innerHTML=`
            <div class="empty-state">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <h3>Erro ao carregar produtos</h3>
                <p>Por favor, tente novamente mais tarde</p>
                <button class="btn-filter-reset" onclick="loadProducts()" style="margin-top:12px;justify-content:center">
                    <i class="fa-solid fa-rotate-right"></i> Tentar novamente
                </button>
            </div>`;
    }finally{
        if(lbar) lbar.classList.remove('active');
    }
}

function handleImgErr(img, company){
    const c=img.parentElement;
    const ph=document.createElement('div');
    ph.className='no-image-placeholder';
    ph.innerHTML=`<span>Distribuído por:</span><strong>${_esc(company)}</strong>`;
    img.remove(); c.prepend(ph);
}

/* ════════════════════════════════════════════════════════════════
   Carrinho — sincronizado com cart.php principal
   • Autenticado : POST ajax_cart.php → badge actualizado pelo servidor
   • Visitante   : localStorage vsg_cart_v2 (mesma estrutura do cart.php)
   • Após adicionar: se o painel do carrinho estiver aberto, recarrega
════════════════════════════════════════════════════════════════ */
const _CART_KEY  = 'vsg_cart_v2';
const _IS_LOGGED = <?= $user_logged_in ? 'true' : 'false' ?>;
const _CART_CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

function _cartTotal(){
    try{
        const d=JSON.parse(localStorage.getItem(_CART_KEY)||'{}');
        return Object.values(d).reduce((a,i)=>a+(parseInt(i.qty)||0),0);
    }catch(_){return 0;}
}

function updateCartBadge(n){
    if(n===undefined) n=_IS_LOGGED ? null : _cartTotal();
    if(n===null) return; // autenticado: aguarda resposta do servidor
    document.querySelectorAll('.cart-badge,.cart-count-badge').forEach(b=>{
        b.textContent=n>99?'99+':n;
        b.style.display=n>0?'':'none';
    });
}

/*
 * addToCart(id, btn, meta)
 * meta = { name, price, stock, img }
 *
 * Autenticado : POST ajax_cart.php → usa cart_count da resposta para o badge.
 * Visitante   : grava em localStorage com estrutura compatível com cart.php.
 *
 * Em ambos os casos:
 *   • Se o painel "carrinho" estiver activo no dashboard → recarrega o embed
 *   • O badge no header e na sidebar é actualizado imediatamente
 */
function addToCart(id, btn, meta){
    const orig=btn.innerHTML;
    btn.disabled=true;
    btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i>';

    const done=(ok)=>{
        btn.innerHTML=ok
            ? '<i class="fa-solid fa-check"></i> Adicionado'
            : '<i class="fa-solid fa-xmark"></i> Erro';
        btn.style.cssText=ok
            ? 'background:var(--primary);color:#fff;border-color:var(--primary)'
            : 'background:var(--danger-bg);color:var(--danger)';
        setTimeout(()=>{ btn.innerHTML=orig; btn.style.cssText=''; btn.disabled=false; },2000);
    };

    const reloadCartIfOpen=()=>{
        const panel=document.getElementById('content-carrinho');
        if(panel?.classList.contains('active')){
            loadedPages.delete('carrinho'); // forçar reload fresco
            loadPageContent('carrinho');
        }
    };

    if(_IS_LOGGED){
        fetch(BASE_URL+'/ajax/ajax_cart.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:`action=add&product_id=${id}&quantity=1&csrf_token=${_CART_CSRF}`
        })
        .then(r=>r.json())
        .then(d=>{
            if(d.success){
                updateCartBadge(d.cart_count||0);
                showToast('Produto adicionado ao carrinho!','success');
                done(true);
                reloadCartIfOpen();
            }else{
                showToast(d.message||'Erro ao adicionar.','error');
                done(false);
            }
        })
        .catch(()=>{ showToast('Erro de conexão.','error'); done(false); });
    }else{
        try{
            const d=JSON.parse(localStorage.getItem(_CART_KEY)||'{}');
            const cur=d[id]||{qty:0};
            // Estrutura compatível com cart.php (LS.add)
            d[id]={
                qty : (cur.qty||0)+1,
                name: meta?.name||'',
                price: meta?.price||0,
                stock: meta?.stock||99,
                img  : meta?.img||'',
                addedAt: new Date().toISOString()
            };
            localStorage.setItem(_CART_KEY,JSON.stringify(d));
            updateCartBadge(_cartTotal());
            showToast('Produto adicionado ao carrinho!','success');
            done(true);
            reloadCartIfOpen();
        }catch(e){ showToast('Erro ao adicionar.','error'); done(false); }
    }
}
function buyNow(id){
    window.location.href = BASE_URL+`/checkout.php?buy_now=${id}&qty=1`;
}

function toggleFav(id, btn){
    const icon=btn.querySelector('i');
    let favs=JSON.parse(localStorage.getItem('vsg_favorites')||'[]');
    if(favs.includes(id)){
        favs=favs.filter(x=>x!==id);
        icon.className='fa-regular fa-heart';
        btn.classList.remove('active');
        showToast('Removido dos favoritos','warning');
    }else{
        favs.push(id);
        icon.className='fa-solid fa-heart';
        btn.classList.add('active');
        showToast('Adicionado aos favoritos!','success');
    }
    localStorage.setItem('vsg_favorites',JSON.stringify(favs));
}

function viewProduct(id){
    window.location.href = BASE_URL+`/product.php?id=${id}`;
}

/* ════════════════════════════════════════════════════════════════
   Toast
════════════════════════════════════════════════════════════════ */
function showToast(message, type){
    const colors={success:'var(--primary)',error:'var(--danger)',warning:'var(--warn)',info:'var(--link)'};
    const icons ={success:'fa-check',error:'fa-xmark',warning:'fa-triangle-exclamation',info:'fa-circle-info'};
    const t=document.createElement('div');
    t.className='vg-toast';
    t.style.borderLeftColor=colors[type]||colors.info;
    t.style.borderLeftWidth='3px';
    t.innerHTML=message;
    const dot=document.createElement('div');
    dot.className='vg-toast-icon';
    dot.style.background=colors[type]||colors.info;
    dot.innerHTML=`<i class="fa-solid ${icons[type]||icons.info}" style="font-size:9px"></i>`;
    t.appendChild(dot);
    document.body.appendChild(t);
    setTimeout(()=>{ t.style.animation='toastOut .25s ease forwards'; setTimeout(()=>t.remove(),250); },3000);
}

/* ════════════════════════════════════════════════════════════════
   Pesquisa (debounce 400ms)
════════════════════════════════════════════════════════════════ */
let _searchTimer;
document.getElementById('searchInput')?.addEventListener('input', function(){
    clearTimeout(_searchTimer);
    _searchTimer=setTimeout(()=>{
        filters.search=this.value.trim();
        currentPage!=='home' ? navigateTo('home') : loadProducts();
    },400);
});

/* ════════════════════════════════════════════════════════════════
   Init
════════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function(){
    if(window._domLoaded) return;
    window._domLoaded=true;

    // Restaurar tema
    try{
        const saved=localStorage.getItem('vg_theme')||'';
        if(saved){ document.body.className=saved;
            const dot=document.querySelector(`.theme-dot[onclick*="${CSS.escape(saved)}"]`);
            if(dot){ document.querySelectorAll('.theme-dot').forEach(d=>d.classList.remove('active')); dot.classList.add('active'); }
        }
    }catch(_){}

    // Restaurar sidebar
    try{ if(localStorage.getItem('sidebarCollapsed')==='true') document.getElementById('sidebar').classList.add('collapsed'); }catch(_){}

    // Limpar badge de pedidos se já visto hoje
    try{
        const visto=localStorage.getItem('pedidosVisualizados');
        if(visto&&(Date.now()-parseInt(visto))<86400000){
            ['header-pedidos-badge','mobile-pedidos-badge'].forEach(id=>{
                const el=document.getElementById(id);
                if(el){ el.style.display='none'; el.textContent='0'; }
            });
            const sp=document.getElementById('sidebar-pedidos-pip');
            if(sp) sp.style.display='none';
        }
    }catch(_){}

    // Página inicial via query string ou home
    const page=new URLSearchParams(window.location.search).get('page')||'home';
    if(page!=='home'){
        navigateTo(page);
    }else{
        loadProducts();
        const iv=setInterval(updateStatsSmooth, 30000); // 30s — não 5s
        window.GlobalModuleManager.registerInterval('dashboard_stats',iv);
    }

    // Badge inicial: logado→servidor já pôs o valor no PHP; visitante→localStorage
    if(!_IS_LOGGED) updateCartBadge();
});

// Fechar menu mobile ao clicar fora
document.addEventListener('click', function(e){
    const sb=document.getElementById('sidebar');
    const btn=document.querySelector('.mobile-menu-btn');
    if(sb?.classList.contains('mobile-open')&&!sb.contains(e.target)&&!btn?.contains(e.target))
        closeMobileMenu();
});

// Bloquear scroll do body quando menu mobile aberto
new MutationObserver(mutations=>{
    mutations.forEach(m=>{
        document.body.style.overflow=m.target.classList.contains('mobile-open')?'hidden':'';
    });
}).observe(document.getElementById('sidebar'),{attributes:true,attributeFilter:['class']});
</script>
</body>
</html>