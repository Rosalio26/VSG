<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE PRODUTOS
 * Arquivo: pages/business/modules/produtos/produtos.php
 * Descrição: Gerenciamento de produtos da empresa
 * ATUALIZADO: Suporta empresa e funcionário COM VERIFICAÇÃO DE PERMISSÕES
 * ✅ CORRIGIDO: Botões do modal agora são exibidos corretamente
 * ================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir DB
require_once '../../../../registration/includes/db.php';

// Verificar autenticação (empresa OU funcionário)
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['type']) && $_SESSION['auth']['type'] === 'company';

if (!$isEmployee && !$isCompany) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
        <i class="fa-solid fa-lock" style="font-size: 48px; margin-bottom: 16px;"></i>
        <h3>Acesso Negado</h3>
        <p>Faça login para acessar esta página.</p>
    </div>';
    exit;
}

// Determinar empresa_id e permissões
if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id']; // ID da empresa
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $userName = $_SESSION['employee_auth']['nome'];
    $userType = 'funcionario';
    
    // ========================================
    // VERIFICAR PERMISSÕES DO FUNCIONÁRIO
    // ========================================
    $stmt = $mysqli->prepare("
        SELECT can_view, can_create, can_edit, can_delete 
        FROM employee_permissions 
        WHERE employee_id = ? AND module = 'produtos'
        LIMIT 1
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $permissions = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Se não tem permissão de visualizar
    if (!$permissions || !$permissions['can_view']) {
        echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
            <i class="fa-solid fa-ban" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
            <h3>Acesso Restrito</h3>
            <p>Você não tem permissão para acessar o módulo de Produtos.</p>
            <p style="font-size: 12px; color: #8b949e; margin-top: 16px;">
                Contate o gestor da empresa para solicitar acesso.
            </p>
        </div>';
        exit;
    }
    
    $canView = true; // Já verificado acima
    $canCreate = (bool)$permissions['can_create'];
    $canEdit = (bool)$permissions['can_edit'];
    $canDelete = (bool)$permissions['can_delete'];
    
} else {
    // GESTOR - Permissões completas
    $userId = (int)$_SESSION['auth']['user_id'];
    $employeeId = null;
    $userName = $_SESSION['auth']['nome'];
    $userType = 'gestor';
    $canView = true;
    $canCreate = true;
    $canEdit = true;
    $canDelete = true;
}

// Buscar dados da empresa
$stmt = $mysqli->prepare("SELECT nome, email FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Inicializar stats_data
$stats_data = ['total' => 0, 'active' => 0];

// Buscar filtros da URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
?>

<style>
/* GitHub Dark Theme Styles */
:root {
    --gh-bg-primary: #0d1117;
    --gh-bg-secondary: #161b22;
    --gh-bg-tertiary: #21262d;
    --gh-border: #30363d;
    --gh-text: #c9d1d9;
    --gh-text-secondary: #8b949e;
    --gh-green: #238636;
    --gh-green-bright: #2ea043;
    --gh-blue: #1f6feb;
    --gh-red: #da3633;
    --gh-orange: #d29922;
    --gh-border-hover: #8b949e;
    --gh-text-muted: #6e7681;
    --gh-accent-green: #238636;
    --gh-accent-green-bright: #2ea043;
    --gh-accent-blue: #1f6feb;
    --gh-accent-yellow: #d29922;
    --gh-accent-red: #da3633;
}

.products-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
}

.products-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--gh-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.products-stats {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.stat-item {
    background: var(--gh-bg-tertiary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 500;
    color: var(--gh-text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.stat-value {
    color: var(--gh-text);
    font-weight: 600;
}

/* Stats Cards do código pequeno */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    transition: all 0.2s ease;
}

.stat-card:hover {
    border-color: var(--gh-border-hover);
    transform: translateY(-2px);
}

.stat-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-card .stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--gh-text);
}

/* Permission Badge do código pequeno */
.permission-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(77, 163, 255, 0.1);
    border: 1px solid rgba(77, 163, 255, 0.3);
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-accent-blue);
}

.permission-badge.readonly {
    background: rgba(210, 153, 34, 0.1);
    border-color: rgba(210, 153, 34, 0.3);
    color: var(--gh-accent-yellow);
}

.permission-badge.full-access {
    background: rgba(46, 160, 67, 0.1);
    border-color: rgba(46, 160, 67, 0.3);
    color: var(--gh-accent-green-bright);
}

.toolbar {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.search-input {
    flex: 1;
    min-width: 200px;
    max-width: 400px;
    height: 32px;
    padding: 0 12px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
    outline: none;
    transition: all 0.2s;
}

.search-input:focus {
    border-color: var(--gh-blue);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.1);
}

.filter-select {
    height: 32px;
    padding: 0 32px 0 12px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
    cursor: pointer;
    outline: none;
    appearance: none;
    background-image: url('data:image/svg+xml;utf8,<svg fill="%238b949e" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z"/></svg>');
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 16px;
}

.btn {
    height: 32px;
    padding: 0 16px;
    border: 1px solid;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
    white-space: nowrap;
}

.btn-primary {
    background: var(--gh-green);
    border-color: rgba(240, 246, 252, 0.1);
    color: #fff;
}

.btn-primary:hover {
    background: var(--gh-green-bright);
}

.btn-secondary {
    background: transparent;
    border-color: var(--gh-border);
    color: var(--gh-text);
}

.btn-secondary:hover {
    background: var(--gh-bg-tertiary);
    border-color: var(--gh-text-secondary);
}

.btn-danger {
    background: var(--gh-red);
    border-color: rgba(240, 246, 252, 0.1);
    color: #fff;
}

.btn-danger:hover {
    background: #b62324;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-sm {
    height: 28px;
    padding: 0 12px;
    font-size: 12px;
}

.btn-icon {
    height: 28px;
    width: 28px;
    padding: 0;
    justify-content: center;
}

.table-container {
    background: transparent;
    border: none;
    border-radius: 0;
    overflow: visible;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border: 1px solid;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.badge-success {
    background: rgba(46, 160, 67, 0.1);
    border-color: rgba(46, 160, 67, 0.4);
    color: #3fb950;
}

.badge-secondary {
    background: rgba(139, 148, 158, 0.1);
    border-color: rgba(139, 148, 158, 0.4);
    color: var(--gh-text-secondary);
}

.badge-warning {
    background: rgba(187, 128, 9, 0.1);
    border-color: rgba(187, 128, 9, 0.4);
    color: #d29922;
}

.badge-info {
    background: rgba(31, 111, 235, 0.15);
    color: var(--gh-accent-blue);
}

.label {
    display: inline-flex;
    padding: 0 7px;
    font-size: 12px;
    font-weight: 500;
    line-height: 18px;
    border: 1px solid transparent;
    border-radius: 2em;
    white-space: nowrap;
}

.label-addon { background: #ffc107; color: #000; }
.label-service { background: #1f6feb; color: #fff; }
.label-consultation { background: #8957e5; color: #fff; }
.label-training { background: #2ea043; color: #fff; }
.label-other { background: #6e7681; color: #fff; }

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(1, 4, 9, 0.8);
    z-index: 999;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

/* ==================== ESTILO DO SCROLLBAR (GITHUB DARK) ==================== */

/* 1. Definir o container do modal */
.modal-dialog {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    width: 90%;
    max-width: 800px; /* Ajustado para caber as duas colunas que criamos */
    max-height: 90vh;
    overflow-x: hidden;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.5);
    
    /* Para Firefox */
    scrollbar-width: thin;
    scrollbar-color: var(--gh-bg-tertiary) transparent;
}

/* 2. Largura da barra de rolagem (Chrome, Edge, Safari) */
.modal-dialog::-webkit-scrollbar {
    width: 8px;
}

/* 3. Fundo da barra (Track) */
.modal-dialog::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
}

/* 4. A barra em si (Thumb) */
.modal-dialog::-webkit-scrollbar-thumb {
    background-color: var(--gh-bg-tertiary);
    border-radius: 10px;
    border: 2px solid var(--gh-bg-secondary); /* Cria um espaçamento visual */
}

/* 5. Efeito ao passar o mouse */
.modal-dialog::-webkit-scrollbar-thumb:hover {
    background-color: var(--gh-border-hover);
}

.modal-header {
    padding: 16px 24px;
    border-bottom: 1px solid var(--gh-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--gh-text);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--gh-text-secondary);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--gh-text);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--gh-text);
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
    outline: none;
}

.form-control:focus {
    border-color: var(--gh-blue);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.form-check-input {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.form-check-label {
    font-size: 14px;
    color: var(--gh-text);
    cursor: pointer;
    margin: 0;
}

/* ✅ CORREÇÃO CRÍTICA: Modal Footer */
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--gh-border);
    display: flex !important;
    flex-direction: row !important;
    justify-content: flex-end !important;
    align-items: center !important;
    gap: 8px !important;
    background: var(--gh-bg-secondary);
}

/* ✅ GARANTIR QUE BOTÕES SEJAM VISÍVEIS */
.modal-footer .btn {
    display: inline-flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    z-index: 1 !important;
}

/* Alertas Fixos com Animação */
#alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 400px;
    pointer-events: none;
}

.alert {
    padding: 16px 20px;
    border: 1px solid;
    border-radius: 8px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    animation: slideInRight 0.3s ease-out;
    pointer-events: all;
    position: relative;
    min-width: 300px;
}

.alert-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.alert-title {
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-message {
    font-size: 13px;
    line-height: 1.5;
    opacity: 0.9;
}

.alert-close {
    background: none;
    border: none;
    color: currentColor;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.6;
    transition: opacity 0.2s;
    font-size: 18px;
    line-height: 1;
    flex-shrink: 0;
}

.alert-close:hover {
    opacity: 1;
}

.alert-success {
    background: rgba(46, 160, 67, 0.15);
    border-color: rgba(46, 160, 67, 0.5);
    color: #3fb950;
}

.alert-error {
    background: rgba(248, 81, 73, 0.15);
    border-color: rgba(248, 81, 73, 0.5);
    color: #f85149;
}

.alert-info {
    background: rgba(31, 111, 235, 0.15);
    border-color: rgba(31, 111, 235, 0.5);
    color: #58a6ff;
}

.alert-warning {
    background: rgba(187, 128, 9, 0.15);
    border-color: rgba(187, 128, 9, 0.5);
    color: #d29922;
}

.alert-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.alert.hiding {
    animation: slideOutRight 0.3s ease-in forwards;
}

@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

/* Barra de progresso para auto-dismiss */
.alert-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: currentColor;
    opacity: 0.3;
    border-radius: 0 0 8px 8px;
    animation: progressBar 5s linear forwards;
}

@keyframes progressBar {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: var(--gh-text-secondary);
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-icon {
    font-size: 64px;
    color: var(--gh-text-muted);
    margin-bottom: 16px;
}

.empty-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 8px;
}

.empty-text {
    font-size: 14px;
    color: var(--gh-text-secondary);
    margin-bottom: 16px;
}

/* Products Grid do código pequeno */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.product-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.product-card:hover {
    border-color: var(--gh-border-hover);
    transform: translateY(-2px);
}

.product-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: var(--gh-bg-tertiary);
}

.product-body {
    padding: 16px;
}

.product-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 8px;
}

.product-description {
    font-size: 14px;
    color: var(--gh-text-secondary);
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-price {
    font-size: 20px;
    font-weight: 700;
    color: var(--gh-accent-green-bright);
    margin-bottom: 12px;
}

.product-actions {
    display: flex;
    gap: 8px;
}

/* Loading do código pequeno */
.loading {
    text-align: center;
    padding: 40px;
    color: var(--gh-text-secondary);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(46, 160, 67, 0.1);
    border-top-color: var(--gh-accent-green-bright);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .products-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .search-input {
        max-width: 100%;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    /* Alertas mobile */
    #alert-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .alert {
        min-width: auto;
    }
}
</style>

<div id="alert-container"></div>

<?php if ($isEmployee): ?>
<div style="padding: 16px; background: rgba(77, 163, 255, 0.1); border: 1px solid rgba(77, 163, 255, 0.3); border-radius: 8px; margin-bottom: 16px;">
    <p style="color: var(--gh-text); font-size: 14px; margin: 0;">
        <i class="fa-solid fa-info-circle"></i>
        <strong>Modo Funcionário:</strong> Você está visualizando os produtos da empresa.
        <?php if (!$canEdit): ?>
            <span style="color: var(--gh-text-secondary);">(Sem permissão para editar)</span>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div class="products-header">
    <h1 class="products-title">
        <i class="fa-solid fa-box-open"></i>
        Produtos
        <?php if ($isEmployee): ?>
            <span style="font-size: 14px; color: var(--gh-accent-blue); margin-left: 10px; background: rgba(77, 163, 255, 0.1); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(77, 163, 255, 0.3);">
                <i class="fa-solid fa-user-tie"></i> Funcionário
            </span>
        <?php endif; ?>
    </h1>
    <div style="display: flex; gap: 8px; align-items: center;">
        <?php if ($isEmployee): ?>
            <?php if ($canEdit && $canCreate && $canDelete): ?>
                <span class="permission-badge full-access">
                    <i class="fa-solid fa-shield-check"></i>
                    Controle Total
                </span>
            <?php elseif ($canEdit || $canCreate): ?>
                <span class="permission-badge">
                    <i class="fa-solid fa-user-tie"></i>
                    <?= $canCreate ? 'Pode Criar' : 'Pode Editar' ?>
                </span>
            <?php else: ?>
                <span class="permission-badge readonly">
                    <i class="fa-solid fa-eye"></i>
                    Apenas Visualização
                </span>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="products-stats">
            <div class="stat-item">
                <span>Total:</span>
                <span class="stat-value"><?= $stats_data['total'] ?></span>
            </div>
            <div class="stat-item">
                <span>Ativos:</span>
                <span class="stat-value" style="color: #3fb950;"><?= $stats_data['active'] ?></span>
            </div>
        </div>
        <?php if ($canCreate): ?>
        <button class="btn btn-primary" onclick="openAddProductPage()">
            <i class="fa-solid fa-plus"></i>
            Novo Produto
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Grid do código pequeno -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total de Produtos</div>
        <div class="stat-value" id="totalProdutos">--</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Produtos Ativos</div>
        <div class="stat-value" id="produtosAtivos">--</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Eco-Verificados</div>
        <div class="stat-value" id="ecoVerificados">--</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Estoque Total</div>
        <div class="stat-value" id="estoqueTotal">--</div>
    </div>
</div>

<div class="toolbar">
    <form onsubmit="return false;" style="display: contents;">
        <input 
            type="text" 
            class="search-input" 
            id="searchInput" 
            placeholder="Buscar produtos..." 
            value="<?= htmlspecialchars($search) ?>"
            autocomplete="off"
        >
    </form>
    <select class="filter-select" id="categoryFilter">
        <option value="">Categoria</option>
        <option value="addon" <?= $category_filter === 'addon' ? 'selected' : '' ?>>Addon</option>
        <option value="service" <?= $category_filter === 'service' ? 'selected' : '' ?>>Serviço</option>
        <option value="consultation" <?= $category_filter === 'consultation' ? 'selected' : '' ?>>Consultoria</option>
        <option value="training" <?= $category_filter === 'training' ? 'selected' : '' ?>>Treinamento</option>
        <option value="other" <?= $category_filter === 'other' ? 'selected' : '' ?>>Outro</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Status</option>
        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Ativos</option>
        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inativos</option>
    </select>
</div>

<div class="table-container" id="productsTable">
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fa-solid fa-spinner fa-spin"></i>
        </div>
        <h3 style="margin: 0; font-size: 16px; color: var(--gh-text);">Carregando produtos...</h3>
    </div>
</div>

<!-- ✅ MODAL CORRIGIDO COM BOTÕES VISÍVEIS -->
<div class="modal" id="productModal">
    <div class="modal-dialog" style="max-width: 800px;">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Editar Produto Ecológico</h2>
            <button class="modal-close" onclick="window.ProductsModule.closeModal()">&times;</button>
        </div>
        <form id="productForm">
            <input type="hidden" id="productId" name="id">
            <input type="hidden" id="formAction" value="edit">
            
            <div class="modal-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                
                <!-- COLUNA ESQUERDA -->
                <div class="modal-col-left">
                    <div class="form-group">
                        <label class="form-label">Nome do Produto *</label>
                        <input type="text" class="form-control" id="productName" name="name" required>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label class="form-label">Preço *</label>
                            <input type="number" step="0.01" class="form-control" id="productPrice" name="price" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Moeda</label>
                            <select class="form-control" id="productCurrency" name="currency">
                                <option value="MZN" selected>MZN</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estoque</label>
                        <input type="number" class="form-control" id="productStock" name="stock_quantity" placeholder="Deixe vazio para ilimitado">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="productDescription" name="description" rows="4" placeholder="Descreva o produto..."></textarea>
                    </div>
                </div>

                <!-- COLUNA DIREITA -->
                <div class="modal-col-right">
                    <div class="form-group">
                        <label class="form-label">Tipo de Produto *</label>
                        <select class="form-control" id="productCategory" name="category" required>
                            <option value="addon">📦 Produto/Addon</option>
                            <option value="service">🛠️ Serviço</option>
                            <option value="consultation">💼 Consultoria</option>
                            <option value="training">📚 Treinamento</option>
                            <option value="other">📋 Outro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Categoria Ecológica</label>
                        <select class="form-control" id="productEcoCategory" name="eco_category">
                            <option value="recyclable">♻️ Reciclável</option>
                            <option value="reusable">🔄 Reutilizável</option>
                            <option value="biodegradable">🌱 Biodegradável</option>
                            <option value="sustainable">🌿 Sustentável</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pegada de Carbono (kg CO₂)</label>
                        <input type="number" step="0.01" class="form-control" id="productCarbon" name="carbon_footprint" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Certificação Ecológica</label>
                        <div class="form-check" style="padding: 10px; border: 1px solid var(--gh-border); border-radius: 6px;">
                            <input type="checkbox" class="form-check-input" id="productEcoVerified" name="eco_verified">
                            <label class="form-check-label" for="productEcoVerified">
                                ✓ Produto Eco-Certificado
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status de Visibilidade *</label>
                        <select class="form-control" id="productActive" name="is_active" required>
                            <option value="1">✓ Ativo no Marketplace</option>
                            <option value="0">✗ Inativo / Rascunho</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Imagem do Produto</label>
                        <div style="text-align: center; margin-bottom: 8px;">
                            <img id="modalPreviewImg" src="" alt="Preview" style="max-width: 100%; max-height: 120px; border-radius: 6px; border: 1px solid var(--gh-border); display: none;">
                        </div>
                        <input type="file" class="form-control" id="productImage" name="product_image" accept="image/*" style="font-size: 12px; padding: 6px;">
                    </div>
                </div>
            </div>

            <!-- ✅ FOOTER CORRIGIDO -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.ProductsModule.closeModal()">
                    <i class="fa-solid fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> 
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // Limpar inicialização anterior se existir
    if (window.ProductsModuleInitialized) {
        console.log('🔄 Reinicializando módulo de produtos...');
        delete window.ProductsModule;
        delete window.ProductsModuleInitialized;
    }
    
    // Marcar como inicializado
    window.ProductsModuleInitialized = true;
    
    // Variáveis do usuário
    const userId = <?= $userId ?>;
    const isEmployee = <?= $isEmployee ? 'true' : 'false' ?>;
    const employeeId = <?= $employeeId ?? 'null' ?>;
    const userName = '<?= htmlspecialchars($userName) ?>';
    const userType = '<?= $userType ?>';
    const canView = <?= $canView ? 'true' : 'false' ?>;
    const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
    
    console.log('🔐 Permissões:', { canView, canCreate, canEdit, canDelete });
    
    // Estado do módulo
    const state = {
        search: '',
        category: '',
        status: ''
    };
    
    const elements = {
        searchInput: document.getElementById('searchInput'),
        categoryFilter: document.getElementById('categoryFilter'),
        statusFilter: document.getElementById('statusFilter'),
        productsTable: document.getElementById('productsTable'),
        productForm: document.getElementById('productForm'),
        productModal: document.getElementById('productModal'),
        alertContainer: document.getElementById('alert-container')
    };
    
    // Verificar elementos
    if (!elements.searchInput || !elements.productsTable) {
        console.error('❌ Elementos do módulo não encontrados');
        return;
    }

    // Carregar produtos
    async function loadProducts() {
        const params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.category) params.set('category', state.category);
        if (state.status) params.set('status', state.status);
        
        try {
            const response = await fetch(`modules/produtos/actions/search_products.php?${params.toString()}`);
            const data = await response.json();
            
            if (!data.success) {
                showAlert('error', data.message || 'Erro ao carregar produtos');
                return;
            }
            
            updateStats(data.stats);
            renderProductsTable(data.products);
            updateStatsCards(data.products);
            
        } catch (error) {
            console.error('Erro:', error);
            showAlert('error', 'Erro ao carregar produtos');
        }
    }

    // Atualizar estatísticas (código original)
    function updateStats(stats) {
        const total = parseInt(stats.total) || 0;
        const active = parseInt(stats.active) || 0;
        
        const statsContainer = document.querySelector('.products-stats');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="stat-item">
                    <span>Total:</span>
                    <span class="stat-value">${total}</span>
                </div>
                <div class="stat-item">
                    <span>Ativos:</span>
                    <span class="stat-value" style="color: #3fb950;">${active}</span>
                </div>
            `;
        }
    }

    // Atualizar cards de stats (do código pequeno)
    function updateStatsCards(products) {
        const total = products.length;
        const active = products.filter(p => p.is_active).length;
        const ecoVerified = products.filter(p => p.eco_verified === 1).length;
        const totalStock = products.reduce((sum, p) => sum + (p.stock_quantity || 0), 0);
        
        const totalEl = document.getElementById('totalProdutos');
        const activeEl = document.getElementById('produtosAtivos');
        const ecoEl = document.getElementById('ecoVerificados');
        const stockEl = document.getElementById('estoqueTotal');
        
        if (totalEl) totalEl.textContent = total;
        if (activeEl) activeEl.textContent = active;
        if (ecoEl) ecoEl.textContent = ecoVerified;
        if (stockEl) stockEl.textContent = totalStock;
    }

    // Renderizar produtos em cards
    function renderProductsTable(products) {
        if (!elements.productsTable) return;
        
        if (products.length === 0) {
            elements.productsTable.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; color: var(--gh-text);">Nenhum produto encontrado</h3>
                    <p style="margin: 0 0 16px 0;">
                        ${canCreate ? 'Comece adicionando seu primeiro produto' : 'A empresa ainda não possui produtos cadastrados'}
                    </p>
                    ${canCreate ? `
                    <button class="btn btn-primary" onclick="openAddProductPage()">
                        <i class="fa-solid fa-plus"></i>
                        Adicionar Produto
                    </button>
                    ` : ''}
                </div>
            `;
            return;
        }
        
        const categoryLabels = {
            addon: 'Addon',
            service: 'Serviço',
            consultation: 'Consultoria',
            training: 'Treinamento',
            other: 'Outro'
        };
        
        let cardsHTML = '<div class="products-grid">';
        
        products.forEach(product => {
            const stockColor = product.stock_quantity !== null 
                ? (product.stock_quantity > 10 ? '#3fb950' : (product.stock_quantity > 0 ? '#d29922' : '#f85149'))
                : 'var(--gh-text-secondary)';
            
            const stockDisplay = product.stock_quantity !== null ? product.stock_quantity : '∞';
            const productJson = JSON.stringify(product).replace(/'/g, '&#39;');
            const productName = escapeHtml(product.name).replace(/'/g, '&#39;');
            
            // Truncar descrição em 80 caracteres
            const description = product.description || 'Sem descrição';
            const truncatedDesc = description.length > 80 
                ? description.substring(0, 80) + '...' 
                : description;
            
            // Imagem do produto ou placeholder
            const imagePath = product.image_path 
                ? `../uploads/${product.image_path}` 
                : null;
            
            cardsHTML += `
                <div class="product-card">
                    ${imagePath ? 
                        `<img src="${imagePath}" class="product-image" alt="${escapeHtml(product.name)}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                         <div class="product-image" style="display: none; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-box" style="font-size: 48px; color: var(--gh-text-muted);"></i>
                         </div>` :
                        `<div class="product-image" style="display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-box" style="font-size: 48px; color: var(--gh-text-muted);"></i>
                         </div>`
                    }
                    <div class="product-body">
                        <div class="product-name">${escapeHtml(product.name)}</div>
                        <div class="product-description">${escapeHtml(truncatedDesc)}</div>
                        
                        <div style="margin-bottom: 8px;">
                            <span class="label label-${product.category}">
                                ${categoryLabels[product.category] || product.category}
                            </span>
                            ${product.eco_verified == 1 ? '<span class="badge badge-success" style="margin-left: 4px;"><i class="fa-solid fa-leaf"></i> Eco</span>' : ''}
                        </div>
                        
                        <div class="product-price">
                            ${formatPrice(product.price)} ${product.currency}
                            ${product.is_recurring == 1 ? `<span style="font-size: 12px; font-weight: 400; color: var(--gh-text-secondary);">/${product.billing_cycle}</span>` : ''}
                        </div>
                        
                        <div style="display: flex; gap: 8px; margin-bottom: 12px; font-size: 12px;">
                            <span style="color: ${stockColor};">
                                <i class="fa-solid fa-cubes"></i> ${stockDisplay}
                            </span>
                            <span class="badge badge-${product.is_active == 1 ? 'success' : 'secondary'}" style="padding: 2px 6px;">
                                ${product.is_active == 1 ? 'Ativo' : 'Inativo'}
                            </span>
                        </div>
                        
                        <div class="product-actions">
                            <button class="btn btn-secondary" onclick="window.ProductsModule.verProduto(${product.id})" style="flex: 1;">
                                <i class="fa-solid fa-eye"></i>
                                Ver
                            </button>
                            ${canEdit ? `
                            <button class="btn btn-secondary btn-icon" onclick='window.ProductsModule.editProduct(${productJson})' title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            ` : ''}
                            ${canDelete ? `
                            <button class="btn btn-danger btn-icon" onclick="window.ProductsModule.deleteProduct(${product.id}, '${productName}')" title="Deletar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        cardsHTML += '</div>';
        elements.productsTable.innerHTML = cardsHTML;
    }

    // Helpers
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatPrice(price) {
        return parseFloat(price).toFixed(2).replace('.', ',');
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('pt-MZ', {
            style: 'currency',
            currency: 'MZN'
        }).format(value);
    }

    // Filtros
    let searchTimeout;
    
    // Prevenir submit ao dar Enter
    elements.searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimeout);
            state.search = elements.searchInput.value;
            loadProducts();
        }
    });
    
    elements.searchInput.addEventListener('input', (e) => {
        state.search = e.target.value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadProducts, 400);
    });

    if (elements.categoryFilter) {
        elements.categoryFilter.addEventListener('change', (e) => {
            state.category = e.target.value;
            loadProducts();
        });
    }

    if (elements.statusFilter) {
        elements.statusFilter.addEventListener('change', (e) => {
            state.status = e.target.value;
            loadProducts();
        });
    }

    // ==================== GESTÃO DO MODAL COM PERSISTÊNCIA ====================
    
    // Funções de persistência do modal
    function saveModalState() {
        const formData = {
            isOpen: true,
            action: document.getElementById('formAction')?.value || 'edit',
            id: document.getElementById('productId')?.value || '',
            name: document.getElementById('productName')?.value || '',
            price: document.getElementById('productPrice')?.value || '',
            currency: document.getElementById('productCurrency')?.value || 'MZN',
            stock: document.getElementById('productStock')?.value || '',
            description: document.getElementById('productDescription')?.value || '',
            category: document.getElementById('productCategory')?.value || 'addon',
            active: document.getElementById('productActive')?.value || '1',
            ecoCategory: document.getElementById('productEcoCategory')?.value || 'recyclable',
            ecoVerified: document.getElementById('productEcoVerified')?.checked || false,
            carbon: document.getElementById('productCarbon')?.value || '',
            modalTitle: document.getElementById('modalTitle')?.textContent || '',
            imageSrc: document.getElementById('modalPreviewImg')?.src || '',
            imageDisplay: document.getElementById('modalPreviewImg')?.style.display || 'none'
        };
        
        localStorage.setItem('productsModalState', JSON.stringify(formData));
        console.log('💾 Estado do modal salvo');
    }
    
    function loadModalState() {
        const saved = localStorage.getItem('productsModalState');
        if (!saved) return null;
        
        try {
            return JSON.parse(saved);
        } catch (e) {
            console.error('Erro ao carregar estado do modal:', e);
            return null;
        }
    }
    
    function clearModalState() {
        localStorage.removeItem('productsModalState');
        console.log('🗑️ Estado do modal limpo');
    }
    
    function restoreModalState() {
        const state = loadModalState();
        if (!state || !state.isOpen) return;
        
        console.log('🔄 Restaurando estado do modal...');
        
        // Restaurar campos
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const productId = document.getElementById('productId');
        const productName = document.getElementById('productName');
        const productPrice = document.getElementById('productPrice');
        const productCurrency = document.getElementById('productCurrency');
        const productStock = document.getElementById('productStock');
        const productDescription = document.getElementById('productDescription');
        const productCategory = document.getElementById('productCategory');
        const productActive = document.getElementById('productActive');
        const productEcoCategory = document.getElementById('productEcoCategory');
        const productEcoVerified = document.getElementById('productEcoVerified');
        const productCarbon = document.getElementById('productCarbon');
        const previewImg = document.getElementById('modalPreviewImg');
        
        if (modalTitle) modalTitle.textContent = state.modalTitle;
        if (formAction) formAction.value = state.action;
        if (productId) productId.value = state.id;
        if (productName) productName.value = state.name;
        if (productPrice) productPrice.value = state.price;
        if (productCurrency) productCurrency.value = state.currency;
        if (productStock) productStock.value = state.stock;
        if (productDescription) productDescription.value = state.description;
        if (productCategory) productCategory.value = state.category;
        if (productActive) productActive.value = state.active;
        if (productEcoCategory) productEcoCategory.value = state.ecoCategory;
        if (productEcoVerified) productEcoVerified.checked = state.ecoVerified;
        if (productCarbon) productCarbon.value = state.carbon;
        
        if (previewImg && state.imageSrc) {
            previewImg.src = state.imageSrc;
            previewImg.style.display = state.imageDisplay;
        }
        
        // Abrir modal
        if (elements.productModal) {
            elements.productModal.classList.add('show');
        }
        
        console.log('✅ Estado do modal restaurado');
    }
    
    function openAddModal() {
        if (!canCreate) {
            alert('❌ Você não tem permissão para criar produtos');
            return;
        }
        
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const productId = document.getElementById('productId');
        const productActive = document.getElementById('productActive');
        const previewImg = document.getElementById('modalPreviewImg');
        
        if (modalTitle) modalTitle.textContent = 'Adicionar Produto Ecológico';
        if (formAction) formAction.value = 'add';
        if (productId) productId.value = '';
        if (elements.productForm) elements.productForm.reset();
        if (previewImg) previewImg.style.display = 'none';
        if (productActive) productActive.value = '1';
        
        toggleBillingCycle();
        
        if (elements.productModal) {
            elements.productModal.classList.add('show');
        }
        
        // Salvar estado inicial
        saveModalState();
    }

    function editProduct(product) {
        if (!canEdit) {
            alert('❌ Você não tem permissão para editar produtos');
            return;
        }

        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const productId = document.getElementById('productId');
        const productName = document.getElementById('productName');
        const productPrice = document.getElementById('productPrice');
        const productCurrency = document.getElementById('productCurrency');
        const productStock = document.getElementById('productStock');
        const productDescription = document.getElementById('productDescription');
        const productCategory = document.getElementById('productCategory');
        const productActive = document.getElementById('productActive');
        const productEcoCategory = document.getElementById('productEcoCategory');
        const productEcoVerified = document.getElementById('productEcoVerified');
        const productCarbon = document.getElementById('productCarbon');
        const previewImg = document.getElementById('modalPreviewImg');

        if (modalTitle) modalTitle.textContent = 'Editar Produto: ' + product.name;
        if (formAction) formAction.value = 'edit';
        if (productId) productId.value = product.id;
        if (productName) productName.value = product.name;
        if (productPrice) productPrice.value = product.price;
        if (productCurrency) productCurrency.value = product.currency || 'MZN';
        if (productStock) productStock.value = product.stock_quantity || '';
        if (productDescription) productDescription.value = product.description || '';
        if (productCategory) productCategory.value = product.category || 'addon';
        if (productActive) productActive.value = product.is_active || '1';

        if (productEcoCategory) productEcoCategory.value = product.eco_category || 'recyclable';
        if (productEcoVerified) productEcoVerified.checked = (product.eco_verified == 1);
        if (productCarbon) productCarbon.value = product.carbon_footprint || '';

        if (previewImg) {
            if (product.image_path) {
                const basePath = window.location.pathname.includes('/pages/business/') 
                    ? '../uploads/' 
                    : '/vsg/pages/uploads/';
                
                previewImg.src = basePath + product.image_path;
                previewImg.style.display = 'block';
                
                previewImg.onerror = function() {
                    this.src = 'https://placehold.co/200x120?text=Imagem+não+encontrada';
                    this.style.opacity = '0.5';
                };
            } else {
                previewImg.src = 'https://placehold.co/200x120?text=Sem+Imagem';
                previewImg.style.display = 'block';
                previewImg.style.opacity = '0.3';
            }
        }

        const modal = document.getElementById('productModal');
        if (modal) {
            modal.classList.add('show');
        }

        console.log('📝 Editando produto:', {
            id: product.id,
            name: product.name,
            eco_verified: product.eco_verified,
            is_active: product.is_active
        });
        
        // Salvar estado inicial
        saveModalState();
    }

    function closeModal() {
        if (elements.productModal) {
            elements.productModal.classList.remove('show');
        } else {
            const modal = document.getElementById('productModal');
            if (modal) modal.classList.remove('show');
        }
        
        // Limpar estado salvo ao fechar
        clearModalState();
    }

    function toggleBillingCycle() {
        const recurringCheckbox = document.getElementById('productRecurring');
        const billingGroup = document.getElementById('billingCycleGroup');
        
        if (recurringCheckbox && billingGroup) {
            const isRecurring = recurringCheckbox.checked;
            billingGroup.style.display = isRecurring ? 'block' : 'none';
        }
    }

    function verProduto(produtoId) {
        alert(`Ver detalhes do produto #${produtoId}`);
    }

    // Submit do formulário
    if (elements.productForm) {
        elements.productForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formAction = document.getElementById('formAction');
            const productId = document.getElementById('productId');
            const productName = document.getElementById('productName');
            const productDescription = document.getElementById('productDescription');
            const productCategory = document.getElementById('productCategory');
            const productPrice = document.getElementById('productPrice');
            const productCurrency = document.getElementById('productCurrency');
            const productStock = document.getElementById('productStock');
            const productActive = document.getElementById('productActive');
            const productEcoCategory = document.getElementById('productEcoCategory');
            const productEcoVerified = document.getElementById('productEcoVerified');
            const productCarbon = document.getElementById('productCarbon');
            const productImage = document.getElementById('productImage');
            
            if (!productName || !productName.value.trim()) {
                showAlert('error', 'Preencha o nome do produto');
                if (productName) productName.focus();
                return;
            }
            
            if (!productPrice || !productPrice.value || parseFloat(productPrice.value) <= 0) {
                showAlert('error', 'Preencha um preço válido');
                if (productPrice) productPrice.focus();
                return;
            }
            
            if (!productCategory || !productCategory.value) {
                showAlert('error', 'Selecione o tipo de produto');
                if (productCategory) productCategory.focus();
                return;
            }
            
            if (!productActive || !productActive.value) {
                showAlert('error', 'Selecione o status de visibilidade');
                if (productActive) productActive.focus();
                return;
            }
            
            const action = formAction ? formAction.value : 'edit';
            const actionFile = action === 'add' ? 'adicionar_produto.php' : 'editar_produto.php';
            
            const formData = new FormData();
            
            if (action === 'edit' && productId && productId.value) {
                formData.append('id', productId.value);
            }
            
            formData.append('name', productName.value.trim());
            formData.append('price', productPrice.value);
            formData.append('category', productCategory.value);
            formData.append('is_active', productActive.value);
            formData.append('description', productDescription ? productDescription.value.trim() : '');
            formData.append('currency', productCurrency ? productCurrency.value : 'MZN');
            formData.append('stock_quantity', productStock && productStock.value ? productStock.value : '');
            formData.append('eco_category', productEcoCategory ? productEcoCategory.value : 'recyclable');
            formData.append('eco_verified', productEcoVerified && productEcoVerified.checked ? '1' : '0');
            formData.append('carbon_footprint', productCarbon && productCarbon.value ? productCarbon.value : '0');
            
            if (productImage && productImage.files && productImage.files.length > 0) {
                formData.append('product_image', productImage.files[0]);
            }
            
            console.log('📤 Enviando dados:', {
                action: action,
                name: productName.value,
                eco_verified: productEcoVerified ? productEcoVerified.checked : false,
                is_active: productActive.value
            });
            
            try {
                const response = await fetch(`modules/produtos/actions/${actionFile}`, {
                    method: 'POST',
                    body: formData
                });
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('❌ Resposta não é JSON:', text);
                    showAlert('error', 'Erro no servidor. Verifique o console para detalhes.');
                    return;
                }
                
                const result = await response.json();
                console.log('📥 Resposta do servidor:', result);
                
                showAlert(result.success ? 'success' : 'error', result.message);
                
                if (result.success) {
                    closeModal();
                    loadProducts();
                }
            } catch (error) {
                console.error('❌ Erro ao salvar:', error);
                showAlert('error', 'Erro ao salvar produto: ' + error.message);
            }
        });
    }

    // Deletar
    async function deleteProduct(id, name) {
        if (!canDelete) {
            alert('❌ Você não tem permissão para deletar produtos');
            return;
        }
        
        if (!confirm(`Deletar "${name}"?`)) return;
        
        const formData = new FormData();
        formData.append('id', id);
        
        try {
            const response = await fetch('modules/produtos/actions/deletar_produto.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            showAlert(result.success ? 'success' : 'error', result.message);
            
            if (result.success) {
                loadProducts();
            }
        } catch (error) {
            showAlert('error', 'Erro: ' + error.message);
        }
    }

    // Alertas Melhorados
    function showAlert(type, message, duration = 5000) {
        if (!elements.alertContainer) return;
        
        // Definir ícones e títulos por tipo
        const alertConfig = {
            success: {
                icon: 'fa-circle-check',
                title: 'Sucesso!'
            },
            error: {
                icon: 'fa-circle-exclamation',
                title: 'Erro!'
            },
            info: {
                icon: 'fa-circle-info',
                title: 'Informação'
            },
            warning: {
                icon: 'fa-triangle-exclamation',
                title: 'Atenção!'
            }
        };
        
        const config = alertConfig[type] || alertConfig.info;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fa-solid ${config.icon} alert-icon"></i>
            <div class="alert-content">
                <div class="alert-title">${config.title}</div>
                <div class="alert-message">${message}</div>
            </div>
            <button class="alert-close" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="alert-progress"></div>
        `;
        
        elements.alertContainer.appendChild(alert);
        
        // Botão fechar
        const closeBtn = alert.querySelector('.alert-close');
        closeBtn.addEventListener('click', () => {
            dismissAlert(alert);
        });
        
        // Auto-dismiss após duração
        const autoDismissTimer = setTimeout(() => {
            dismissAlert(alert);
        }, duration);
        
        // Pausar timer ao passar o mouse
        alert.addEventListener('mouseenter', () => {
            clearTimeout(autoDismissTimer);
            const progressBar = alert.querySelector('.alert-progress');
            if (progressBar) {
                progressBar.style.animationPlayState = 'paused';
            }
        });
        
        // Retomar timer ao sair o mouse
        alert.addEventListener('mouseleave', () => {
            const progressBar = alert.querySelector('.alert-progress');
            if (progressBar) {
                progressBar.style.animationPlayState = 'running';
            }
            setTimeout(() => {
                dismissAlert(alert);
            }, 1000);
        });
        
        return alert;
    }
    
    function dismissAlert(alert) {
        if (!alert || alert.classList.contains('hiding')) return;
        
        alert.classList.add('hiding');
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, 300);
    }

    // Modal click outside
    if (elements.productModal) {
        elements.productModal.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
    }

    const recurringCheckbox = document.getElementById('productRecurring');
    if (recurringCheckbox) {
        recurringCheckbox.addEventListener('change', toggleBillingCycle);
    }
    
    const closeButtons = document.querySelectorAll('.modal-close');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    // ==================== PERSISTÊNCIA EM TEMPO REAL ====================
    
    // Adicionar listeners para salvar estado em tempo real nos campos do formulário
    const formFields = [
        'productName',
        'productPrice',
        'productCurrency',
        'productStock',
        'productDescription',
        'productCategory',
        'productActive',
        'productEcoCategory',
        'productEcoVerified',
        'productCarbon'
    ];
    
    formFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            // Salvar ao digitar/mudar
            field.addEventListener('input', () => {
                const state = loadModalState();
                if (state && state.isOpen) {
                    saveModalState();
                }
            });
            
            field.addEventListener('change', () => {
                const state = loadModalState();
                if (state && state.isOpen) {
                    saveModalState();
                }
            });
        }
    });
    
    console.log('💾 Listeners de persistência configurados');

    // Expor API pública
    window.ProductsModule = {
        openAddModal,
        editProduct,
        closeModal,
        deleteProduct,
        verProduto,
        toggleBillingCycle,
        saveModalState,
        clearModalState
    };
    
    window.openAddProductPage = function() {
        if (!canCreate) {
            alert('❌ Você não tem permissão para criar produtos');
            return;
        }
        if (typeof loadContent === 'function') {
            loadContent('modules/produtos/adicionar_produto_page');
        } else {
            window.location.href = '?page=modules/produtos/adicionar_produto_page';
        }
    };

    if (elements.searchInput?.value) {
        state.search = elements.searchInput.value.trim();
    }
    if (elements.categoryFilter?.value) {
        state.category = elements.categoryFilter.value;
    }
    if (elements.statusFilter?.value) {
        state.status = elements.statusFilter.value;
    }

    // Carregar produtos e depois restaurar modal se necessário
    loadProducts().then(() => {
        // Restaurar estado do modal após carregar produtos
        setTimeout(() => {
            restoreModalState();
        }, 100);
    });
    
    console.log('✅ Módulo de Produtos iniciado -', 
        isEmployee ? 'Funcionário:' : 'Empresa:', 
        userName,
        '- Pode editar:', canEdit,
        '- Pode criar:', canCreate,
        '- Pode deletar:', canDelete
    );
    
})();
</script>