<?php
/**
 * EXEMPLO DE USO - index.php ou header.php
 */

// 1. INCLUA NO TOPO DO SEU ARQUIVO (antes de qualquer HTML)
require_once 'geo_location.php';

// Agora você tem acesso às variáveis:
// $user_location - array completo com todos os dados
// $user_city - apenas a cidade
// $user_country - apenas o país
// $user_region - região/estado
// $user_full_location - "Cidade, País"

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>VSG Marketplace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dados de localização disponíveis globalmente -->
    <script>
        var userLocation = <?= json_encode($user_location) ?>;
        console.log('Localização detectada:', userLocation);
    </script>
</head>
<body>

<!-- Top Strip -->
<div class="top-strip">
    <div class="top-strip-content">
        <div class="top-left-info">
            <a href="javascript:void(0);" class="top-link" onclick="openLocationModal()">
                <i class="fa-solid fa-location-dot"></i>
                Entregar em <span class="location-now"><?= htmlspecialchars($user_full_location) ?></span>
            </a>
        </div>
        <ul class="top-right-nav">
            <li><a href="#">Central de Ajuda</a></li>
            <li><a href="#">Rastrear Pedido</a></li>
        </ul>
    </div>
</div>

<!-- Main Header -->
<header class="main-header">
    <div class="header-main">
        <a href="index.php" class="logo-container">
            <div class="logo-text">
                VSG<span class="logo-accent">•</span>
            </div>
        </a>

        <form action="marketplace.php" method="GET" class="search-section">
            <select class="search-category" name="category" id="searchCategorySelect">
                <option value="" data-icon="">Todas Categorias</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" 
                            data-icon="<?= htmlspecialchars($cat['icon']) ?>"
                            <?= $selected_category_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                    </option>
                    <?php if (!empty($cat['subcategories'])): ?>
                        <?php foreach ($cat['subcategories'] as $subcat): ?>
                            <option value="<?= $subcat['id'] ?>" 
                                    data-icon="<?= htmlspecialchars($subcat['icon']) ?>"
                                    <?= $selected_category_id == $subcat['id'] ? 'selected' : '' ?>>
                                &nbsp;&nbsp;↳ <?= htmlspecialchars($subcat['name']) ?> (<?= $subcat['product_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <div class="search-input-wrapper">
                <input 
                    type="text" 
                    class="search-input" 
                    name="q"
                    value="<?= htmlspecialchars($search_query) ?>"
                    placeholder="Buscar produtos sustentáveis..."
                >
            </div>
            <button type="submit" class="search-button">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>

        <div class="header-actions">
            <a href="<?= $user_logged_in ? 'pages/person/index.php?page=favoritos' : 'registration/login/login.php' ?>" class="header-action">
                <i class="fa-solid fa-heart action-icon"></i>
                <span>Favoritos</span>
            </a>

            <a href="<?= $user_logged_in ? 'pages/person/index.php?page=carrinho' : 'registration/login/login.php' ?>" class="header-action">
                <i class="fa-solid fa-cart-shopping action-icon"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
                <span>Carrinho</span>
            </a>

            <?php if ($user_logged_in): ?>
                <a href="pages/person/index.php" class="header-action" target="_blank">
                    <img src="<?= htmlspecialchars($displayAvatar) ?>" 
                         alt="<?= htmlspecialchars($displayName) ?>" 
                         style="width: 32px; height: 32px; border-radius: 50%; margin-bottom: 4px;">
                    <span><?= htmlspecialchars($displayName) ?></span>
                </a>
            <?php else: ?>
                <a href="registration/login/login.php" class="header-action">
                    <i class="fa-solid fa-user action-icon"></i>
                    <span>Entrar</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Modal de Localização -->
<div id="locationModal" class="location-modal">
    <div class="modal-overlay" onclick="closeLocationModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-location-dot"></i> Sua Localização</h3>
            <button class="modal-close" onclick="closeLocationModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="location-info">
                <div class="info-item">
                    <i class="fa-solid fa-globe"></i>
                    <div>
                        <label>País</label>
                        <span><?= htmlspecialchars($user_country) ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-map"></i>
                    <div>
                        <label>Região</label>
                        <span><?= htmlspecialchars($user_region) ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-city"></i>
                    <div>
                        <label>Cidade</label>
                        <span><?= htmlspecialchars($user_city) ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fa-solid fa-network-wired"></i>
                    <div>
                        <label>IP</label>
                        <span><?= htmlspecialchars($user_location['ip']) ?></span>
                    </div>
                </div>
                
                <?php if (!empty($user_location['latitude']) && !empty($user_location['longitude'])): ?>
                <div class="info-item">
                    <i class="fa-solid fa-map-pin"></i>
                    <div>
                        <label>Coordenadas</label>
                        <span><?= htmlspecialchars($user_location['latitude']) ?>, <?= htmlspecialchars($user_location['longitude']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="location-actions">
                <button class="btn-primary" onclick="changeLocation()">
                    <i class="fa-solid fa-pen"></i> Alterar Localização
                </button>
                <button class="btn-secondary" onclick="closeLocationModal()">
                    <i class="fa-solid fa-check"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS -->
<style>
.top-strip {
    background: #232f3e;
    color: white;
    padding: 8px 0;
}

.top-strip-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
}

.top-link {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: color 0.3s;
}

.top-link:hover {
    color: #ffa500;
}

.location-now {
    font-weight: 600;
    color: #2ecc71;
    cursor: pointer;
}

.top-right-nav {
    display: flex;
    gap: 20px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.top-right-nav a {
    color: white;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s;
}

.top-right-nav a:hover {
    color: #ffa500;
}

/* Modal de Localização */
.location-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e0e0e0;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    color: #232f3e;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: #2ecc71;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #666;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s;
}

.modal-close:hover {
    background: #f5f5f5;
    color: #232f3e;
}

.modal-body {
    padding: 24px;
}

.location-info {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 24px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.3s;
}

.info-item:hover {
    background: #e9ecef;
    transform: translateX(4px);
}

.info-item i {
    font-size: 20px;
    color: #2ecc71;
    width: 24px;
    text-align: center;
}

.info-item div {
    flex: 1;
}

.info-item label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item span {
    display: block;
    font-size: 16px;
    color: #232f3e;
    font-weight: 600;
}

.location-actions {
    display: flex;
    gap: 12px;
}

.location-actions button {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background: #2ecc71;
    color: white;
}

.btn-primary:hover {
    background: #27ae60;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
}

.btn-secondary {
    background: #232f3e;
    color: white;
}

.btn-secondary:hover {
    background: #1a242f;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(35, 47, 62, 0.3);
}

/* Responsivo */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        max-width: none;
    }
    
    .location-actions {
        flex-direction: column;
    }
    
    .info-item {
        padding: 10px;
    }
    
    .info-item span {
        font-size: 14px;
    }
}
</style>

<script>
// Funções do modal
function openLocationModal() {
    document.getElementById('locationModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLocationModal() {
    document.getElementById('locationModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function changeLocation() {
    alert('Funcionalidade de alterar localização em desenvolvimento');
    // Aqui você pode adicionar um formulário para o usuário inserir uma nova localização
    // ou usar a API de Geolocalização do navegador
}

// Fechar modal ao pressionar ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLocationModal();
    }
});

// Filtrar produtos por região
function filterProductsByLocation() {
    const city = userLocation.city;
    const country = userLocation.country;
    
    fetch(`api/products.php?city=${city}&country=${country}`)
        .then(response => response.json())
        .then(data => {
            console.log('Produtos na sua região:', data);
        });
}

// Calcular frete baseado na localização
function calculateShipping() {
    const lat = userLocation.latitude;
    const lon = userLocation.longitude;
    
    console.log('Calculando frete para:', lat, lon);
}
</script>

</body>
</html>

<?php
/*
 * OUTRAS FORMAS DE USAR OS DADOS DE LOCALIZAÇÃO:
 */

// 1. Filtrar produtos por região
$products_query = "SELECT * FROM products WHERE region = ? OR city = ?";
$stmt = $pdo->prepare($products_query);
$stmt->execute([$user_region, $user_city]);

// 2. Mostrar frete grátis para região específica
if ($user_region === 'Maputo City') {
    echo '<div class="free-shipping-banner">🎉 Frete Grátis para sua região!</div>';
}

// 3. Mostrar vendedores próximos
$nearby_sellers = "SELECT * FROM sellers 
                   WHERE city = ? 
                   ORDER BY rating DESC 
                   LIMIT 5";

// 4. Personalizar mensagens
echo "Produtos populares em {$user_city}";

// 5. Rastrear origem das visitas (analytics)
$analytics = "INSERT INTO visitor_analytics (ip, city, country, timestamp) 
              VALUES (?, ?, ?, NOW())";
$stmt = $pdo->prepare($analytics);
$stmt->execute([
    $user_location['ip'],
    $user_city,
    $user_country
]);
?>