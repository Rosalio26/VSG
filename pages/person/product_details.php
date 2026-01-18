<?php
define('REQUIRED_TYPE', 'person');
require_once '../../registration/middleware/middleware_auth.php';
require_once '../../registration/includes/db.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../registration/login/login.php");
    exit;
}

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$productId) {
    header("Location: dashboard_person.php");
    exit;
}

$stmt = $mysqli->prepare("
    SELECT p.*, 
           u.nome as empresa_nome, 
           u.email as empresa_email,
           u.telefone as empresa_telefone,
           u.public_id as empresa_id,
           b.logo_path as empresa_logo,
           b.description as empresa_descricao
    FROM products p
    INNER JOIN users u ON p.user_id = u.id
    LEFT JOIN businesses b ON u.id = b.user_id
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

$catLabels = [
    'addon' => '📦 Produto',
    'service' => '🛠️ Serviço',
    'consultation' => '💼 Consultoria',
    'training' => '📚 Treinamento',
    'other' => '📋 Outro'
];

$ecoLabels = [
    'recyclable' => '♻️ Reciclável',
    'reusable' => '🔄 Reutilizável',
    'biodegradable' => '🌱 Biodegradável',
    'sustainable' => '🌿 Sustentável',
    'organic' => '🌾 Orgânico',
    'zero_waste' => '🗑️ Zero Desperdício',
    'energy_efficient' => '⚡ Eficiente em Energia'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['nome']) ?> | VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0d1117;
            --bg-card: #161b22;
            --border-color: #30363d;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-green: #00ff88;
            --accent-blue: #4da3ff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .top-bar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--accent-green);
            color: #000;
            border-color: var(--accent-green);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .product-image-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
        }

        .product-image-section img {
            max-width: 100%;
            max-height: 500px;
            border-radius: 12px;
        }

        .product-image-section i {
            font-size: 120px;
            color: var(--text-secondary);
            opacity: 0.3;
        }

        .product-details-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-title {
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
        }

        .product-category-badge {
            display: inline-block;
            padding: 6px 12px;
            background: rgba(77, 163, 255, 0.1);
            border: 1px solid rgba(77, 163, 255, 0.3);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--accent-blue);
        }

        .product-price {
            font-size: 42px;
            font-weight: 800;
            color: var(--accent-green);
            margin: 20px 0;
        }

        .eco-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(0, 255, 136, 0.1);
            border: 2px solid rgba(0, 255, 136, 0.3);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 20px;
        }

        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
        }

        .info-card h3 {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-secondary);
            font-weight: 600;
        }

        .info-value {
            color: #fff;
            font-weight: 700;
        }

        .btn-buy {
            width: 100%;
            padding: 16px;
            background: var(--accent-green);
            color: #000;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.3);
        }

        .seller-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            margin-top: 40px;
        }

        .seller-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .seller-logo {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: #000;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .seller-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .seller-logo i {
            font-size: 32px;
            color: var(--text-secondary);
        }

        .seller-info h3 {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
        }

        .seller-id {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: var(--accent-green);
            background: rgba(0, 255, 136, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(77, 163, 255, 0.1);
            border: 1px solid rgba(77, 163, 255, 0.3);
            color: var(--accent-blue);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .contact-btn:hover {
            background: var(--accent-blue);
            color: #000;
        }

        @media (max-width: 768px) {
            .product-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="dashboard_person.php" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i>
        Voltar ao Marketplace
    </a>
</div>

<div class="container">
    <div class="product-layout">
        <div class="product-image-section">
            <?php if($product['imagem']): ?>
                <img src="../../registration/uploads/products/<?= htmlspecialchars($product['imagem']) ?>" alt="<?= htmlspecialchars($product['nome']) ?>">
            <?php else: ?>
                <i class="fa-solid fa-box"></i>
            <?php endif; ?>
        </div>

        <div class="product-details-section">
            <div>
                <h1 class="product-title"><?= htmlspecialchars($product['nome']) ?></h1>
                <span class="product-category-badge"><?= $catLabels[$product['categoria']] ?? $product['categoria'] ?></span>
            </div>

            <?php if($product['eco_verified'] == 1): ?>
                <div class="eco-badge-large">
                    <i class="fa-solid fa-certificate"></i>
                    <span>PRODUTO ECO-CERTIFICADO</span>
                </div>
            <?php endif; ?>

            <div class="product-price">
                <?= number_format($product['preco'], 2, ',', '.') ?> <?= htmlspecialchars($product['currency']) ?>
            </div>

            <?php if($product['descricao']): ?>
                <div class="info-card">
                    <h3>Descrição</h3>
                    <p style="color: var(--text-primary); line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($product['descricao'])) ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="info-card">
                <h3>Informações do Produto</h3>
                <?php if($product['stock']): ?>
                <div class="info-row">
                    <span class="info-label">Estoque Disponível</span>
                    <span class="info-value"><?= (int)$product['stock'] ?> unidades</span>
                </div>
                <?php endif; ?>
                
                <?php if($product['eco_category']): ?>
                <div class="info-row">
                    <span class="info-label">Categoria Ecológica</span>
                    <span class="info-value"><?= $ecoLabels[$product['eco_category']] ?? $product['eco_category'] ?></span>
                </div>
                <?php endif; ?>

                <?php if($product['eco_score']): ?>
                <div class="info-row">
                    <span class="info-label">Pontuação Eco</span>
                    <span class="info-value"><?= number_format($product['eco_score'], 1) ?>/5.0</span>
                </div>
                <?php endif; ?>
            </div>

            <button class="btn-buy" onclick="alert('Funcionalidade de compra em desenvolvimento')">
                <i class="fa-solid fa-shopping-cart"></i>
                Comprar Agora
            </button>
        </div>
    </div>

    <div class="seller-card">
        <h3 style="font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 20px;">
            Vendido por
        </h3>
        <div class="seller-header">
            <div class="seller-logo">
                <?php if($product['empresa_logo']): ?>
                    <img src="../../registration/uploads/business/<?= htmlspecialchars($product['empresa_logo']) ?>" alt="Logo">
                <?php else: ?>
                    <i class="fa-solid fa-building"></i>
                <?php endif; ?>
            </div>
            <div class="seller-info">
                <h3><?= htmlspecialchars($product['empresa_nome']) ?></h3>
                <span class="seller-id">ID: <?= htmlspecialchars($product['empresa_id']) ?></span>
            </div>
        </div>

        <?php if($product['empresa_descricao']): ?>
            <p style="color: var(--text-secondary); margin-bottom: 15px; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($product['empresa_descricao'])) ?>
            </p>
        <?php endif; ?>

        <a href="mailto:<?= htmlspecialchars($product['empresa_email']) ?>" class="contact-btn">
            <i class="fa-solid fa-envelope"></i>
            Entrar em Contato
        </a>
    </div>
</div>

</body>
</html>