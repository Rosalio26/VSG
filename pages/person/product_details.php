<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';
require_once '../../registration/includes/db.php';
require_once '../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$productId) {
    header("Location: dashboard_person.php");
    exit;
}

// Buscar produto principal - Atualizado para incluir as novas colunas de imagem
$stmt = $mysqli->prepare("
    SELECT p.*, 
           u.nome as empresa_nome, 
           u.public_id as empresa_id
    FROM products p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.id = ? 
    AND p.status = 'ativo' 
    AND p.deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: dashboard_person.php");
    exit;
}

// Buscar produtos relacionados da MESMA EMPRESA - Ajustado para image_path
// Buscar produtos relacionados da MESMA EMPRESA
$stmt = $mysqli->prepare("
    SELECT id, nome, preco, imagem, image_path1, stock, categoria, descricao, stock_minimo 
    FROM products 
    WHERE user_id = ? 
    AND id != ?
    AND status = 'ativo'
    AND deleted_at IS NULL
    ORDER BY RAND()
    LIMIT 4
");
$stmt->bind_param('ii', $product['user_id'], $productId);
$stmt->execute();
$relatedProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Buscar informações do usuário logado
$stmt = $mysqli->prepare("SELECT nome, apelido FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$displayName = $user['apelido'] ?: $user['nome'];
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=00ff88&color=000&bold=true";

$categoryLabels = [
    'reciclavel' => ['icon' => '♻️', 'label' => 'Reciclável'],
    'sustentavel' => ['icon' => '🌿', 'label' => 'Sustentável'],
    'servico' => ['icon' => '🛠️', 'label' => 'Serviços'],
    'visiongreen' => ['icon' => '🌱', 'label' => 'VisionGreen'],
    'ecologico' => ['icon' => '🌍', 'label' => 'Ecológico'],
    'outro' => ['icon' => '📦', 'label' => 'Outros']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['nome']) ?> | VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/products_details.css">
    <style>
        /* Estilos para o fallback de imagem */
        .no-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
            height: 100%;
            min-height: 300px;
            background: var(--gh-bg-secondary, #1a1a1a);
            border-radius: 8px;
        }
        .no-image-placeholder span {
            font-size: 10px;
            text-transform: uppercase;
            color: var(--text-secondary, #8b949e);
        }
        .no-image-placeholder strong {
            font-size: 13px;
            color: var(--primary, #00ff88);
        }
        .main-image-container img {
            width: 100%;
            height: auto;
            display: block;
        }
    </style>
</head>
<body>
    <div class="loading-bar" id="loadingBar"></div>

    <header class="header-main">
        <div class="header-content">
            <button class="mobile-menu-btn" onclick="window.history.back()" title="Voltar">
                <i class="fa-solid fa-arrow-left"></i>
            </button>

            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Buscar produtos..." readonly onclick="window.location.href='dashboard_person.php'">
            </div>

            <div class="header-actions">
                <button class="icon-btn" onclick="window.location.href='dashboard_person.php?page=meus_pedidos'" title="Meus Pedidos">
                    <i class="fa-solid fa-shopping-bag"></i>
                </button>

                <button class="icon-btn" onclick="window.location.href='dashboard_person.php?page=notificacoes'" title="Notificações">
                    <i class="fa-solid fa-bell"></i>
                </button>

                <div class="user-profile" onclick="window.location.href='dashboard_person.php?page=perfil'">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($displayName) ?></span>
                        <span class="user-role">Cliente</span>
                    </div>
                    <img src="<?= $displayAvatar ?>" alt="Avatar" class="user-avatar">
                </div>
            </div>
        </div>
    </header>

    <main class="product-detail-wrapper">
        <div class="breadcrumb">
            <a href="dashboard_person.php"><i class="fa-solid fa-house"></i> Início</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="dashboard_person.php">Produtos</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?= htmlspecialchars($product['nome']) ?></span>
        </div>

        <div class="product-main-grid">
            <div class="product-gallery">
                <div class="main-image-container" id="mainImage">
                    <?php if($product['imagem']): ?>
                        <img src="../uploads/products/<?= htmlspecialchars($product['imagem']) ?>"
                             alt="<?= htmlspecialchars($product['nome']) ?>"
                             onerror="handleImageError(this, '<?= addslashes($product['empresa_nome']) ?>')">
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <span>Distribuído por:</span>
                            <strong><?= htmlspecialchars($product['empresa_nome']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="thumbnail-grid">
                    <?php 
                    // Galeria dinâmica usando as novas colunas
                    $images = array_filter([
                        $product['imagem'],
                        $product['image_path1'],
                        $product['image_path2'],
                        $product['image_path3'],
                        $product['image_path4']
                    ]);
                    
                    if(!empty($images)):
                        foreach($images as $path):
                    ?>
                        <div class="thumbnail" onclick="changeImage('../uploads/products/<?= htmlspecialchars($path) ?>', '<?= addslashes($product['empresa_nome']) ?>')">
                            <img src="../uploads/products/<?= htmlspecialchars($path) ?>" 
                                 onerror="this.parentElement.style.display='none'">
                        </div>
                    <?php 
                        endforeach;
                    else:
                        // Placeholders se não houver imagens
                        for($i=0; $i<4; $i++): ?>
                            <div class="thumbnail">
                                <i class="fa-solid fa-image"></i>
                            </div>
                    <?php endfor; 
                    endif; ?>
                </div>
            </div>

            <div class="product-info-section">
                <div class="product-header">
                    <h1><?= htmlspecialchars($product['nome']) ?></h1>
                    <div class="product-meta">
                        <span class="category-badge">
                            <?= $categoryLabels[$product['categoria']]['icon'] ?>
                            <?= $categoryLabels[$product['categoria']]['label'] ?>
                        </span>
                        <span class="company-badge">
                            <i class="fa-solid fa-store"></i>
                            <?= htmlspecialchars($product['empresa_nome']) ?>
                        </span>
                    </div>
                </div>

                <div class="price-section">
                    <table class="price-table">
                        <tr class="price-row">
                            <td class="price-label">Preço</td>
                            <td class="price-value"><?= number_format($product['preco'], 2, ',', '.') ?> MZN</td>
                        </tr>
                        <tr class="price-row">
                            <td class="price-label">Disponibilidade</td>
                            <td class="stock-info <?= $product['stock'] <= 0 ? 'out' : ($product['stock'] <= $product['stock_minimo'] ? 'low' : 'available') ?>">
                                <?php if($product['stock'] <= 0): ?>
                                    <i class="fa-solid fa-circle-xmark"></i> Esgotado
                                <?php elseif($product['stock'] <= $product['stock_minimo']): ?>
                                    <i class="fa-solid fa-circle-exclamation"></i> <?= $product['stock'] ?> unidades
                                <?php else: ?>
                                    <i class="fa-solid fa-circle-check"></i> Em estoque
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="price-row">
                            <td class="price-label">Código</td>
                            <td style="font-family: 'Courier New', monospace; color: var(--text-primary); font-weight: 600;">
                                #<?= str_pad($product['id'], 6, '0', STR_PAD_LEFT) ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if($product['descricao']): ?>
                <div class="description-box">
                    <h3><i class="fa-solid fa-file-lines"></i> Descrição</h3>
                    <p><?= nl2br(htmlspecialchars($product['descricao'])) ?></p>
                </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <button class="btn-primary-large" onclick="buyProduct(<?= $product['id'] ?>)" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-bolt"></i>
                        Comprar Agora
                    </button>
                    <button class="btn-secondary-large" onclick="addToCartDetail(<?= $product['id'] ?>, '<?= addslashes($product['nome']) ?>', <?= $product['preco'] ?>)" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-cart-plus"></i>
                        Adicionar
                    </button>
                </div>
            </div>
        </div>

        <?php if(!empty($relatedProducts)): ?>
        <div class="related-section">
            <h2 class="section-title">
                <i class="fa-solid fa-layer-group"></i>
                Mais Produtos desta Empresa
            </h2>
            <div class="related-grid">
                <?php foreach($relatedProducts as $related): 
                    $categoryInfo = $categoryLabels[$related['categoria']];
                    $stockStatus = $related['stock'] <= 0 ? 'out' : ($related['stock'] <= 5 ? 'low' : 'available');
                ?>
                <a href="product_details.php?id=<?= $related['id'] ?>" class="product-card">
                    <?php if($related['stock'] > 0 && $related['stock'] <= 5): ?>
                        <span class="product-badge">🔥 Últimas unidades</span>
                    <?php elseif($related['stock'] <= 0): ?>
                        <span class="product-badge" style="background: rgba(248, 81, 73, 0.9);">❌ Esgotado</span>
                    <?php endif; ?>
                    
                    <div class="product-image">
                        <?php if($related['imagem']): ?>
                            <img src="../uploads/products/<?= htmlspecialchars($related['imagem']) ?>" 
                                 alt="<?= htmlspecialchars($related['nome']) ?>" 
                                 loading="lazy"
                                 onerror="handleImageError(this, '<?= addslashes($product['empresa_nome']) ?>')">
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <span>Distribuído por:</span>
                                <strong><?= htmlspecialchars($product['empresa_nome']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <div class="product-category">
                            <span><?= $categoryInfo['icon'] ?></span>
                            <span><?= $categoryInfo['label'] ?></span>
                        </div>
                        
                        <div class="product-name" title="<?= htmlspecialchars($related['nome']) ?>">
                            <?= htmlspecialchars($related['nome']) ?>
                        </div>
                        
                        <div class="product-footer">
                            <div class="product-price">
                                <?= number_format($related['preco'], 2, ',', '.') ?>
                                <small>MZN</small>
                            </div>
                            <div class="product-stock <?= $stockStatus ?>">
                                <?php if($related['stock'] <= 0): ?>
                                    ❌ Esgotado
                                <?php elseif($related['stock'] <= 5): ?>
                                    ⚠️ <?= $related['stock'] ?> rest.
                                <?php else: ?>
                                    ✓ Disponível
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <footer class="product-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>VisionGreen</h4>
                <ul>
                    <li><a href="#">Sobre Nós</a></li>
                    <li><a href="#">Como Funciona</a></li>
                    <li><a href="#">Sustentabilidade</a></li>
                    <li><a href="#">Certificações</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Suporte</h4>
                <ul>
                    <li><a href="#">Central de Ajuda</a></li>
                    <li><a href="#">Política de Devolução</a></li>
                    <li><a href="#">Entregas</a></li>
                    <li><a href="#">Contato</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Legal</h4>
                <ul>
                    <li><a href="#">Termos de Uso</a></li>
                    <li><a href="#">Privacidade</a></li>
                    <li><a href="#">Cookies</a></li>
                    <li><a href="#">Licenças</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            © <?= date('Y') ?> VisionGreen Marketplace. Todos os direitos reservados.
        </div>
    </footer>
    
    <script>
        function handleImageError(img, companyName) {
            const container = img.parentElement;
            const placeholder = document.createElement('div');
            placeholder.className = 'no-image-placeholder';
            placeholder.innerHTML = `<span>Distribuído por:</span><strong>${companyName}</strong>`;
            img.remove();
            container.prepend(placeholder);
        }

        function changeImage(src, companyName) {
            const container = document.getElementById('mainImage');
            container.innerHTML = `<img src="${src}" alt="Produto" onerror="handleImageError(this, '${companyName}')">`;
        }

        function buyProduct(productId) {
            showToast('⚡ Redirecionando para checkout...', 'info');
            setTimeout(() => {
                window.location.href = `checkout.php?product=${productId}&qty=1`;
            }, 500);
        }

        function addToCartDetail(productId, productName, price) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const existingItem = cart.find(item => item.id === productId);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: productId,
                    name: productName,
                    price: price,
                    quantity: 1,
                    addedAt: new Date().toISOString()
                });
            }
            
            localStorage.setItem('cart', JSON.stringify(cart));
            if(typeof updateCartBadge === 'function') updateCartBadge();
            showToast(`✅ <strong>${productName}</strong> adicionado ao carrinho!`, 'success');
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = message;
            
            const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
            const colors = {
                success: 'var(--primary, #00ff88)', 
                error: 'var(--danger, #ff4444)',
                warning: 'var(--warning, #ffbb33)', 
                info: 'var(--accent, #33b5e5)'
            };
            
            toast.style.cssText = `
                position: fixed; bottom: 100px; right: 20px;
                background: var(--card-bg, #0d1117); color: var(--text-primary, #c9d1d9);
                padding: 16px 24px; padding-left: 50px;
                border-radius: 16px; border: 2px solid ${colors[type]};
                box-shadow: 0 12px 40px rgba(0,0,0,0.4); z-index: 9999;
                animation: slideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                font-weight: 600; backdrop-filter: blur(10px);
                max-width: 400px; font-size: 14px;
            `;
            
            const iconEl = document.createElement('span');
            iconEl.textContent = icons[type];
            iconEl.style.cssText = `
                position: absolute; left: 16px; top: 50%;
                transform: translateY(-50%); font-size: 20px;
                width: 28px; height: 28px; background: ${colors[type]};
                color: #000; border-radius: 50%; display: flex;
                align-items: center; justify-content: center; font-weight: bold;
            `;
            toast.appendChild(iconEl);
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function updateCartBadge() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            document.querySelectorAll('.cart-badge').forEach(badge => {
                if (totalItems > 0) {
                    badge.textContent = totalItems;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', updateCartBadge);
    </script>
</body>
</html>