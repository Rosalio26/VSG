<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

if (isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    header("Location: ../../pages/admin/dashboard.php");
    exit;
}

if ($_SESSION['auth']['type'] !== 'person') {
    if ($_SESSION['auth']['type'] === 'company') {
        header("Location: ../business/dashboard_business.php");
    } else {
        header("Location: ../../registration/login/login.php?error=acesso_proibido");
    }
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];

$stmt = $mysqli->prepare("
    SELECT nome, apelido, email, telefone, public_id, status, registration_step, 
           email_verified_at, created_at, type
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
    header("Location: ../../registration/process/verify_email.php");
    exit;
}

if (!$user['public_id']) {
    header("Location: ../../registration/register/gerar_uid.php");
    exit;
}

if ($user['status'] === 'blocked') {
    die('Acesso restrito: Sua conta está bloqueada. Por favor, contacte o suporte técnico.');
}

$displayName = $user['apelido'] ?: $user['nome'];
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=00ff88&color=000&bold=true";

// Buscar categorias disponíveis
$categories = [];
$catResult = $mysqli->query("SELECT DISTINCT categoria FROM products WHERE status = 'ativo' AND deleted_at IS NULL");
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row['categoria'];
    }
    $catResult->close();
}

// Buscar faixas de preço
$priceRanges = [
    ['min' => 0, 'max' => 1000, 'label' => 'Até 1.000 MZN'],
    ['min' => 1000, 'max' => 5000, 'label' => '1.000 - 5.000 MZN'],
    ['min' => 5000, 'max' => 10000, 'label' => '5.000 - 10.000 MZN'],
    ['min' => 10000, 'max' => 999999, 'label' => 'Acima de 10.000 MZN']
];

// Estatísticas
$stats = ['mensagens_nao_lidas' => 0];
$result = $mysqli->query("SELECT COUNT(*) as total FROM notifications WHERE receiver_id = '$userId' AND status = 'unread'");
if ($result) {
    $stats['mensagens_nao_lidas'] = (int)$result->fetch_assoc()['total'];
    $result->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionGreen Marketplace | <?= htmlspecialchars($displayName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0d1117;
            --bg-sidebar: #161b22;
            --bg-card: #161b22;
            --border-color: #30363d;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-green: #00ff88;
            --accent-blue: #4da3ff;
            --sidebar-width: 280px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
        }

        #masterBody.sidebar-is-collapsed .sidebar { width: 80px; }
        #masterBody.sidebar-is-collapsed .main-wrapper { margin-left: 80px; }
        #masterBody.sidebar-is-collapsed .logo-text,
        #masterBody.sidebar-is-collapsed .user-details,
        #masterBody.sidebar-is-collapsed .filter-section h3,
        #masterBody.sidebar-is-collapsed .filter-section label,
        #masterBody.sidebar-is-collapsed .price-range-label { display: none; }
        #masterBody.sidebar-is-collapsed .collapse-btn i { transform: rotate(180deg); }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
            z-index: 100;
            overflow-y: auto;
        }

        .collapse-btn {
            position: absolute;
            right: -15px;
            top: 20px;
            width: 30px;
            height: 30px;
            background: var(--bg-sidebar);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 101;
            transition: all 0.3s ease;
        }

        .collapse-btn:hover {
            background: var(--accent-green);
            border-color: var(--accent-green);
            color: #000;
        }

        .collapse-btn i { transition: transform 0.3s ease; }

        .header-section {
            padding: 30px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: var(--accent-green);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 20px;
            flex-shrink: 0;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            white-space: nowrap;
        }

        .logo-main {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .user-badge {
            font-size: 9px;
            font-weight: 800;
            padding: 4px 8px;
            border-radius: 6px;
            margin-top: 6px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(0, 255, 136, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(0, 255, 136, 0.3);
        }

        .filters-wrapper {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .filter-section {
            margin-bottom: 25px;
        }

        .filter-section h3 {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-option:hover {
            color: var(--accent-green);
        }

        .filter-option input[type="checkbox"],
        .filter-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent-green);
        }

        .filter-option label {
            font-size: 14px;
            cursor: pointer;
            flex: 1;
        }

        .price-range-label {
            font-size: 14px;
            color: var(--text-primary);
        }

        .btn-filter-reset {
            width: 100%;
            padding: 10px;
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.3);
            color: #ff4d4d;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .btn-filter-reset:hover {
            background: #ff4d4d;
            color: #fff;
        }

        .sidebar-footer-fixed {
            border-top: 1px solid var(--border-color);
            padding: 20px;
        }

        .settings-btn, .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 10px;
            border: none;
            width: 100%;
        }

        .settings-btn {
            background: rgba(77, 163, 255, 0.1);
            border: 1px solid rgba(77, 163, 255, 0.3);
            color: var(--accent-blue);
        }

        .settings-btn:hover {
            background: var(--accent-blue);
            color: #000;
        }

        .logout-btn {
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.3);
            color: #ff4d4d;
        }

        .logout-btn:hover {
            background: #ff4d4d;
            color: #fff;
        }

        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .header-section-main {
            background: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 30px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
        }

        .search-container {
            flex: 1;
            max-width: 600px;
            position: relative;
        }

        .search-container input {
            width: 100%;
            padding: 12px 16px 12px 45px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
        }

        .search-container input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 3px rgba(0, 255, 136, 0.1);
        }

        .search-container i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .icon-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .icon-action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
        }

        .badge-dot {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 10px;
            height: 10px;
            background: #ff4d4d;
            border: 2px solid var(--bg-sidebar);
            border-radius: 50%;
        }

        .master-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        .master-info { text-align: right; }

        .master-name {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .master-role {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
        }

        .avatar-box img {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .main-wrapper-content {
            flex: 1;
            padding: 30px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .product-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent-green);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.2);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image i {
            font-size: 48px;
            color: var(--text-secondary);
        }

        .product-info {
            padding: 16px;
        }

        .product-name {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .product-category {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .product-price {
            font-size: 20px;
            font-weight: 800;
            color: var(--accent-green);
        }

        .product-eco-badge {
            display: inline-block;
            padding: 4px 8px;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--accent-green);
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-secondary);
        }

        #page-loader {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            width: calc(100% - var(--sidebar-width));
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--accent-green), transparent);
            z-index: 1000;
            animation: loadingBar 1.5s infinite linear;
        }

        @keyframes loadingBar {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>
</head>
<body id="masterBody">

<div id="page-loader"></div>

<aside class="sidebar">
    <div class="collapse-btn" onclick="toggleSidebar()">
        <i class="fa-solid fa-chevron-left"></i>
    </div>

    <div class="header-section">
        <div class="brand-area">
            <div class="logo-icon">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <div class="logo-text">
                <span class="logo-main">VISIONGREEN</span>
                <span class="user-badge">
                    <i class="fa-solid fa-user"></i> MARKETPLACE
                </span>
            </div>
        </div>
    </div>

    <div class="filters-wrapper">
        <div class="filter-section">
            <h3>Categorias</h3>
            <?php
            $catLabels = [
                'addon' => '📦 Produtos',
                'service' => '🛠️ Serviços',
                'consultation' => '💼 Consultoria',
                'training' => '📚 Treinamento',
                'other' => '📋 Outros'
            ];
            foreach ($catLabels as $value => $label):
            ?>
            <div class="filter-option">
                <input type="checkbox" id="cat_<?= $value ?>" class="category-filter" value="<?= $value ?>">
                <label for="cat_<?= $value ?>"><?= $label ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-section">
            <h3>Categoria Ecológica</h3>
            <div class="filter-option">
                <input type="checkbox" id="eco_recyclable" class="eco-filter" value="recyclable">
                <label for="eco_recyclable">♻️ Reciclável</label>
            </div>
            <div class="filter-option">
                <input type="checkbox" id="eco_reusable" class="eco-filter" value="reusable">
                <label for="eco_reusable">🔄 Reutilizável</label>
            </div>
            <div class="filter-option">
                <input type="checkbox" id="eco_biodegradable" class="eco-filter" value="biodegradable">
                <label for="eco_biodegradable">🌱 Biodegradável</label>
            </div>
            <div class="filter-option">
                <input type="checkbox" id="eco_sustainable" class="eco-filter" value="sustainable">
                <label for="eco_sustainable">🌿 Sustentável</label>
            </div>
        </div>

        <div class="filter-section">
            <h3>Faixa de Preço</h3>
            <?php foreach ($priceRanges as $index => $range): ?>
            <div class="filter-option">
                <input type="radio" name="price_range" id="price_<?= $index ?>" class="price-filter" 
                       value="<?= $range['min'] ?>-<?= $range['max'] ?>">
                <label for="price_<?= $index ?>" class="price-range-label"><?= $range['label'] ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-section">
            <h3>Certificação</h3>
            <div class="filter-option">
                <input type="checkbox" id="eco_certified" class="cert-filter" value="1">
                <label for="eco_certified">✓ Eco-Certificado</label>
            </div>
        </div>

        <button class="btn-filter-reset" onclick="resetFilters()">
            <i class="fa-solid fa-rotate-right"></i> Limpar Filtros
        </button>
    </div>

    <div class="sidebar-footer-fixed">
        <a href="javascript:void(0)" onclick="loadSettings()" class="settings-btn">
            <i class="fa-solid fa-sliders"></i>
            <span>Configurações</span>
        </a>
        <form method="post" action="../../registration/login/logout.php">
            <?= csrf_field(); ?>
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-power-off"></i>
                <span>Sair</span>
            </button>
        </form>
    </div>
</aside>

<main class="main-wrapper">
    <header class="header-section-main">
        <div class="header-content">
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="mainSearchInput" placeholder="Pesquisar produtos ecológicos..." autocomplete="off">
            </div>

            <div class="header-right">
                <div class="icon-action-btn" title="Notificações">
                    <i class="fa-solid fa-bell"></i>
                    <?php if($stats['mensagens_nao_lidas'] > 0): ?>
                        <span class="badge-dot"></span>
                    <?php endif; ?>
                </div>

                <div class="master-profile">
                    <div class="master-info">
                        <span class="master-name"><?= htmlspecialchars($displayName) ?></span>
                        <span class="master-role">CLIENTE</span>
                    </div>
                    <div class="avatar-box">
                        <img src="<?= $displayAvatar ?>" alt="Avatar">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-wrapper-content">
        <div id="productsContainer" class="products-grid">
            <div class="empty-state">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <h3>Carregando produtos...</h3>
            </div>
        </div>
    </div>
</main>

<script>
const userData = <?= json_encode([
    'userId' => $userId,
    'nome' => $displayName,
    'email' => $user['email'],
    'publicId' => $user['public_id'],
    'type' => $user['type']
], JSON_UNESCAPED_UNICODE) ?>;

function toggleSidebar() {
    document.getElementById('masterBody').classList.toggle('sidebar-is-collapsed');
}

let currentFilters = {
    search: '',
    categories: [],
    ecoCategories: [],
    priceRange: null,
    ecoCertified: false
};

async function loadProducts() {
    const loader = document.getElementById('page-loader');
    const container = document.getElementById('productsContainer');
    
    if (loader) loader.style.display = 'block';
    
    try {
        const params = new URLSearchParams({
            search: currentFilters.search,
            categories: currentFilters.categories.join(','),
            eco_categories: currentFilters.ecoCategories.join(','),
            price_range: currentFilters.priceRange || '',
            eco_certified: currentFilters.ecoCertified ? '1' : ''
        });

        const response = await fetch(`actions/get_products.php?${params.toString()}`);
        const data = await response.json();

        if (data.success && data.products.length > 0) {
            container.innerHTML = data.products.map(product => `
                <a href="product_details.php?id=${product.id}" class="product-card">
                    <div class="product-image">
                        ${product.imagem ? 
                            `<img src="../../registration/uploads/products/${product.imagem}" alt="${product.nome}">` :
                            '<i class="fa-solid fa-box"></i>'
                        }
                    </div>
                    <div class="product-info">
                        <div class="product-name">${product.nome}</div>
                        <div class="product-category">${getCategoryLabel(product.categoria)}</div>
                        <div class="product-price">${product.preco} ${product.currency}</div>
                        ${product.eco_verified == 1 ? '<span class="product-eco-badge">✓ ECO-CERTIFICADO</span>' : ''}
                    </div>
                </a>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="empty-state" style="grid-column: 1/-1;">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>Nenhum produto encontrado</h3>
                    <p>Tente ajustar os filtros de pesquisa</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        container.innerHTML = `
            <div class="empty-state" style="grid-column: 1/-1;">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <h3>Erro ao carregar produtos</h3>
                <p>Tente novamente mais tarde</p>
            </div>
        `;
    } finally {
        if (loader) loader.style.display = 'none';
    }
}

function getCategoryLabel(cat) {
    const labels = {
        'addon': '📦 Produto',
        'service': '🛠️ Serviço',
        'consultation': '💼 Consultoria',
        'training': '📚 Treinamento',
        'other': '📋 Outro'
    };
    return labels[cat] || cat;
}

// Event Listeners para Filtros
document.querySelectorAll('.category-filter').forEach(el => {
    el.addEventListener('change', function() {
        if (this.checked) {
            currentFilters.categories.push(this.value);
        } else {
            currentFilters.categories = currentFilters.categories.filter(c => c !== this.value);
        }
        loadProducts();
    });
});

document.querySelectorAll('.eco-filter').forEach(el => {
    el.addEventListener('change', function() {
        if (this.checked) {
            currentFilters.ecoCategories.push(this.value);
        } else {
            currentFilters.ecoCategories = currentFilters.ecoCategories.filter(c => c !== this.value);
        }
        loadProducts();
    });
});

document.querySelectorAll('.price-filter').forEach(el => {
    el.addEventListener('change', function() {
        currentFilters.priceRange = this.value;
        loadProducts();
    });
});

document.querySelectorAll('.cert-filter').forEach(el => {
    el.addEventListener('change', function() {
        currentFilters.ecoCertified = this.checked;
        loadProducts();
    });
});

// Busca em tempo real
let searchTimeout;
document.getElementById('mainSearchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentFilters.search = this.value;
        loadProducts();
    }, 500);
});

function resetFilters() {
    currentFilters = {
        search: '',
        categories: [],
        ecoCategories: [],
        priceRange: null,
        ecoCertified: false
    };
    
    document.getElementById('mainSearchInput').value = '';
    document.querySelectorAll('.category-filter').forEach(el => el.checked = false);
    document.querySelectorAll('.eco-filter').forEach(el => el.checked = false);
    document.querySelectorAll('.price-filter').forEach(el => el.checked = false);
    document.querySelectorAll('.cert-filter').forEach(el => el.checked = false);
    
    loadProducts();
}

function loadSettings() {
    window.location.href = 'configuracoes.php';
}

// Carregar produtos ao iniciar
window.addEventListener('DOMContentLoaded', function() {
    loadProducts();
});

console.log('✅ VisionGreen Marketplace carregado -', userData.nome);
</script>
</body>
</html>