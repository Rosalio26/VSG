<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE PRODUTOS
 * Arquivo: pages/business/modules/produtos/produtos.php
 * ✅ COMPLETO: Galeria de 5 imagens no modal de edição
 * ================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../../registration/includes/db.php';

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

if ($isEmployee) {
    $userId = (int)$_SESSION['employee_auth']['empresa_id'];
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $userName = $_SESSION['employee_auth']['nome'];
    $userType = 'funcionario';
    
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
    
    if (!$permissions || !$permissions['can_view']) {
        echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
            <i class="fa-solid fa-ban" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
            <h3>Acesso Restrito</h3>
            <p>Você não tem permissão para acessar o módulo de Produtos.</p>
        </div>';
        exit;
    }
    
    $canView = true;
    $canCreate = (bool)$permissions['can_create'];
    $canEdit = (bool)$permissions['can_edit'];
    $canDelete = (bool)$permissions['can_delete'];
    
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
    $employeeId = null;
    $userName = $_SESSION['auth']['nome'];
    $userType = 'gestor';
    $canView = true;
    $canCreate = true;
    $canEdit = true;
    $canDelete = true;
}

$stmt = $mysqli->prepare("SELECT nome, email FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats_data = ['total' => 0, 'active' => 0];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
?>

<style>
.gallery-upload-item {
    position: relative;
    text-align: center;
    background: var(--gh-bg-secondary);
    padding: 10px;
    border-radius: 8px;
    border: 1px dashed var(--gh-border);
    transition: all 0.3s ease;
    cursor: pointer;
    overflow: hidden;
    height: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.gallery-upload-item:hover {
    border-color: var(--primary);
    background: rgba(0, 255, 136, 0.05);
}

.image-preview-wrapper {
    position: relative;
    width: 100%;
    height: 80px;
    margin-bottom: 5px;
    border-radius: 4px;
    overflow: hidden;
    background: #21262d;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-preview-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1;
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 10px;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 3;
}

.gallery-upload-item:hover .image-overlay {
    opacity: 1;
}

.file-input-hidden {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 4;
}

.gallery-upload-item i.placeholder-icon {
    font-size: 24px;
    color: var(--gh-text-secondary);
    z-index: 0;
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
        <div class="stat-label">Estoque Total</div>
        <div class="stat-value" id="estoqueTotal">--</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Valor Total</div>
        <div class="stat-value" id="valorTotal">--</div>
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
        <option value="reciclavel" <?= $category_filter === 'reciclavel' ? 'selected' : '' ?>>♻️ Reciclável</option>
        <option value="sustentavel" <?= $category_filter === 'sustentavel' ? 'selected' : '' ?>>🌿 Sustentável</option>
        <option value="servico" <?= $category_filter === 'servico' ? 'selected' : '' ?>>🛠️ Serviço</option>
        <option value="visiongreen" <?= $category_filter === 'visiongreen' ? 'selected' : '' ?>>🌱 VisionGreen</option>
        <option value="ecologico" <?= $category_filter === 'ecologico' ? 'selected' : '' ?>>🌍 Ecológico</option>
        <option value="outro" <?= $category_filter === 'outro' ? 'selected' : '' ?>>📦 Outro</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Status</option>
        <option value="ativo" <?= $status_filter === 'ativo' ? 'selected' : '' ?>>Ativos</option>
        <option value="inativo" <?= $status_filter === 'inativo' ? 'selected' : '' ?>>Inativos</option>
        <option value="esgotado" <?= $status_filter === 'esgotado' ? 'selected' : '' ?>>Esgotados</option>
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

<div class="modal" id="productModal">
    <div class="modal-dialog" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Editar Produto</h2>
            <button class="modal-close" onclick="window.ProductsModule.closeModal()">&times;</button>
        </div>
        <form id="productForm">
            <input type="hidden" id="productId" name="id">
            <input type="hidden" id="formAction" value="edit">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome do Produto *</label>
                    <input type="text" class="form-control" id="productName" name="nome" required>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label">Preço *</label>
                        <input type="number" step="0.01" class="form-control" id="productPrice" name="preco" required>
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

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label">Estoque</label>
                        <input type="number" class="form-control" id="productStock" name="stock" placeholder="Quantidade disponível">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estoque Mínimo</label>
                        <input type="number" class="form-control" id="productStockMin" name="stock_minimo" value="5">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Categoria *</label>
                    <select class="form-control" id="productCategory" name="categoria" required>
                        <option value="reciclavel">♻️ Reciclável</option>
                        <option value="sustentavel">🌿 Sustentável</option>
                        <option value="servico">🛠️ Serviço</option>
                        <option value="visiongreen">🌱 VisionGreen</option>
                        <option value="ecologico">🌍 Ecológico</option>
                        <option value="outro">📦 Outro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select class="form-control" id="productStatus" name="status" required>
                        <option value="ativo">✓ Ativo</option>
                        <option value="inativo">✗ Inativo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" id="productDescription" name="descricao" rows="3" placeholder="Descreva o produto..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--gh-accent-blue); margin-bottom: 10px; display: block;">
                        <i class="fa-solid fa-images"></i> Galeria de Imagens
                    </label>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">
                        
                        <div class="gallery-upload-item">
                            <input type="file" name="imagem" class="file-input-hidden" accept="image/*" onchange="previewGallery(this, 'prev_imagem')">
                            <div class="image-preview-wrapper">
                                <img id="prev_imagem" src="" style="display: none;">
                                <div class="image-overlay">
                                    <i class="fa-solid fa-camera"></i>
                                    <span>Mudar Capa</span>
                                </div>
                                <i class="fa-solid fa-image placeholder-icon" id="icon_imagem"></i>
                            </div>
                            <label style="font-size: 10px; font-weight: 600; color: var(--gh-text-secondary);">Capa Principal</label>
                        </div>

                        <?php for($i = 1; $i <= 4; $i++): ?>
                        <div class="gallery-upload-item">
                            <input type="file" name="imagem<?= $i ?>" class="file-input-hidden" accept="image/*" onchange="previewGallery(this, 'prev_imagem<?= $i ?>')">
                            <div class="image-preview-wrapper">
                                <img id="prev_imagem<?= $i ?>" src="" style="display: none;">
                                <div class="image-overlay">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Mudar Foto</span>
                                </div>
                                <i class="fa-solid fa-images placeholder-icon" id="icon_imagem<?= $i ?>"></i>
                            </div>
                            <label style="font-size: 10px; color: var(--gh-text-secondary);">Foto <?= $i + 1 ?></label>
                        </div>
                        <?php endfor; ?>
                        
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.ProductsModule.closeModal()">
                    <i class="fa-solid fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> 
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    if (window.ProductsModuleInitialized) {
        delete window.ProductsModule;
        delete window.ProductsModuleInitialized;
    }
    
    window.ProductsModuleInitialized = true;
    
    const userId = <?= $userId ?>;
    const isEmployee = <?= $isEmployee ? 'true' : 'false' ?>;
    const employeeId = <?= $employeeId ?? 'null' ?>;
    const userName = '<?= htmlspecialchars($userName) ?>';
    const userType = '<?= $userType ?>';
    const canView = <?= $canView ? 'true' : 'false' ?>;
    const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
    
    const state = {
        search: '',
        category: '',
        status: '',
        originalImages: {}
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
    
    if (!elements.searchInput || !elements.productsTable) {
        console.error('❌ Elementos não encontrados');
        return;
    }

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

    function updateStatsCards(products) {
        const total = products.length;
        const active = products.filter(p => p.status === 'ativo').length;
        const totalStock = products.reduce((sum, p) => sum + (parseInt(p.stock) || 0), 0);
        const valorTotal = products.reduce((sum, p) => sum + (parseFloat(p.preco) * parseInt(p.stock || 0)), 0);
        
        const totalEl = document.getElementById('totalProdutos');
        const activeEl = document.getElementById('produtosAtivos');
        const stockEl = document.getElementById('estoqueTotal');
        const valorEl = document.getElementById('valorTotal');
        
        if (totalEl) totalEl.textContent = total;
        if (activeEl) activeEl.textContent = active;
        if (stockEl) stockEl.textContent = totalStock;
        if (valorEl) valorEl.textContent = formatMoney(valorTotal);
    }

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
            'reciclavel': '♻️ Reciclável',
            'sustentavel': '🌿 Sustentável',
            'servico': '🛠️ Serviço',
            'visiongreen': '🌱 VisionGreen',
            'ecologico': '🌍 Ecológico',
            'outro': '📦 Outro'
        };
        
        let cardsHTML = '<div class="products-grid">';
        
        products.forEach(product => {
            const stock = parseInt(product.stock) || 0;
            const stockMin = parseInt(product.stock_minimo) || 5;
            const stockColor = product.status === 'esgotado' || stock === 0 ? '#f85149' : 
                              (stock <= stockMin ? '#d29922' : '#3fb950');
            
            const stockDisplay = product.status === 'esgotado' ? 'Esgotado' : stock;
            const productJson = JSON.stringify(product).replace(/'/g, '&#39;');
            const productName = escapeHtml(product.nome).replace(/'/g, '&#39;');
            
            const description = product.descricao || 'Sem descrição';
            const truncatedDesc = description.length > 80 ? description.substring(0, 80) + '...' : description;
            
            const imagePath = product.imagem ? `../uploads/products/${product.imagem}` : null;
            
            cardsHTML += `
                <div class="product-card">
                    ${imagePath ? 
                        `<img src="${imagePath}" class="product-image" alt="${escapeHtml(product.nome)}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                         <div class="product-image" style="display: none; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-box" style="font-size: 48px; color: var(--gh-text-muted);"></i>
                         </div>` :
                        `<div class="product-image" style="display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-box" style="font-size: 48px; color: var(--gh-text-muted);"></i>
                         </div>`
                    }
                    <div class="product-body">
                        <div class="product-name">${escapeHtml(product.nome)}</div>
                        <div class="product-description">${escapeHtml(truncatedDesc)}</div>
                        
                        <div style="margin-bottom: 8px;">
                            <span class="label label-${product.categoria}">
                                ${categoryLabels[product.categoria] || product.categoria}
                            </span>
                        </div>
                        
                        <div class="product-price">
                            ${formatPrice(product.preco)} ${product.currency}
                        </div>
                        
                        <div style="display: flex; gap: 8px; margin-bottom: 12px; font-size: 12px;">
                            <span style="color: ${stockColor};">
                                <i class="fa-solid fa-cubes"></i> ${stockDisplay}
                            </span>
                            <span class="badge badge-${product.status === 'ativo' ? 'success' : 'secondary'}" style="padding: 2px 6px;">
                                ${product.status === 'ativo' ? 'Ativo' : product.status === 'esgotado' ? 'Esgotado' : 'Inativo'}
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
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    }

    let searchTimeout;
    
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

    function openAddModal() {
        if (!canCreate) {
            alert('❌ Você não tem permissão para criar produtos');
            return;
        }
        
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const productId = document.getElementById('productId');
        const productStatus = document.getElementById('productStatus');
        
        if (modalTitle) modalTitle.textContent = 'Adicionar Produto';
        if (formAction) formAction.value = 'add';
        if (productId) productId.value = '';
        if (elements.productForm) elements.productForm.reset();
        if (productStatus) productStatus.value = 'ativo';
        state.originalImages = {};
    
        const galleryKeys = ['imagem', 'imagem1', 'imagem2', 'imagem3', 'imagem4'];
        galleryKeys.forEach(key => {
            const previewEl = document.getElementById('prev_' + key);
            const iconEl = document.getElementById('icon_' + key);
            const inputEl = document.querySelector(`input[name="${key}"]`);
            if (previewEl) {
                previewEl.src = '';
                previewEl.style.display = 'none';
            }
            if (iconEl) {
                iconEl.style.display = 'block';
            }
            if (inputEl) {
                inputEl.value = '';
            }
        });
        
        if (elements.productModal) {
            elements.productModal.classList.add('show');
        }
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
        const productStockMin = document.getElementById('productStockMin');
        const productDescription = document.getElementById('productDescription');
        const productCategory = document.getElementById('productCategory');
        const productStatus = document.getElementById('productStatus');

        if (modalTitle) modalTitle.textContent = 'Editar Produto: ' + product.nome;
        if (formAction) formAction.value = 'edit';
        if (productId) productId.value = product.id;
        if (productName) productName.value = product.nome;
        if (productPrice) productPrice.value = product.preco;
        if (productCurrency) productCurrency.value = product.currency || 'MZN';
        if (productStock) productStock.value = product.stock || '';
        if (productStockMin) productStockMin.value = product.stock_minimo || 5;
        if (productDescription) productDescription.value = product.descricao || '';
        if (productCategory) productCategory.value = product.categoria || 'outro';
        if (productStatus) productStatus.value = product.status || 'ativo';

        const basePath = '../uploads/products/';

        const galleryMap = {
            'imagem': product.imagem,
            'imagem1': product.image_path1,
            'imagem2': product.image_path2,
            'imagem3': product.image_path3,
            'imagem4': product.image_path4
        };

        state.originalImages = {};

        Object.keys(galleryMap).forEach(key => {
            const previewEl = document.getElementById('prev_' + key);
            const iconEl = document.getElementById('icon_' + key);
            const inputEl = document.querySelector(`input[name="${key}"]`);
            
            if (inputEl) {
                inputEl.value = '';
            }
            
            if (galleryMap[key] && galleryMap[key].trim() !== "" && galleryMap[key] !== "null") {
                const fullImageUrl = basePath + galleryMap[key];
                state.originalImages[key] = fullImageUrl;
                
                if (previewEl) {
                    previewEl.src = fullImageUrl;
                    previewEl.style.display = 'block';
                    previewEl.onerror = function() { 
                        this.style.display = 'none';
                        if (iconEl) iconEl.style.display = 'block';
                    };
                }
                if (iconEl) {
                    iconEl.style.display = 'none';
                }
            } else {
                state.originalImages[key] = null;
                
                if (previewEl) {
                    previewEl.src = '';
                    previewEl.style.display = 'none';
                }
                if (iconEl) {
                    iconEl.style.display = 'block';
                }
            }
        });

        const modal = document.getElementById('productModal');
        if (modal) {
            modal.classList.add('show');
        }
    }

    window.previewGallery = function(input, imgId) {
        const preview = document.getElementById(imgId);
        const icon = document.getElementById('icon_' + imgId.replace('prev_', ''));
        const inputName = input.name;
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                if(icon) icon.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            if (state.originalImages[inputName]) {
                preview.src = state.originalImages[inputName];
                preview.style.display = 'block';
                if(icon) icon.style.display = 'none';
            } else {
                preview.src = '';
                preview.style.display = 'none';
                if(icon) icon.style.display = 'block';
            }
        }
    };

    function closeModal() {
        if (elements.productModal) {
            elements.productModal.classList.remove('show');
        } else {
            const modal = document.getElementById('productModal');
            if (modal) modal.classList.remove('show');
        }
    }

    function verProduto(produtoId) {
        alert(`Ver detalhes do produto #${produtoId}`);
    }

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
            const productStockMin = document.getElementById('productStockMin');
            const productStatus = document.getElementById('productStatus');
            
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
                showAlert('error', 'Selecione a categoria');
                if (productCategory) productCategory.focus();
                return;
            }
            
            if (!productStatus || !productStatus.value) {
                showAlert('error', 'Selecione o status');
                if (productStatus) productStatus.focus();
                return;
            }
            
            const action = formAction ? formAction.value : 'edit';
            const actionFile = action === 'add' ? 'adicionar_produto.php' : 'editar_produto.php';
            
            const formData = new FormData(elements.productForm);
            
            try {
                const response = await fetch(`modules/produtos/actions/${actionFile}`, {
                    method: 'POST',
                    body: formData
                });
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('❌ Resposta não é JSON:', text);
                    showAlert('error', 'Erro no servidor');
                    return;
                }
                
                const result = await response.json();
                
                showAlert(result.success ? 'success' : 'error', result.message);
                
                if (result.success) {
                    closeModal();
                    loadProducts();
                }
            } catch (error) {
                console.error('❌ Erro:', error);
                showAlert('error', 'Erro ao salvar produto');
            }
        });
    }

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

    function showAlert(type, message, duration = 5000) {
        if (!elements.alertContainer) return;
        
        const alertConfig = {
            success: { icon: 'fa-circle-check', title: 'Sucesso!' },
            error: { icon: 'fa-circle-exclamation', title: 'Erro!' },
            info: { icon: 'fa-circle-info', title: 'Informação' },
            warning: { icon: 'fa-triangle-exclamation', title: 'Atenção!' }
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
        
        const closeBtn = alert.querySelector('.alert-close');
        closeBtn.addEventListener('click', () => dismissAlert(alert));
        
        const autoDismissTimer = setTimeout(() => dismissAlert(alert), duration);
        
        alert.addEventListener('mouseenter', () => {
            clearTimeout(autoDismissTimer);
            const progressBar = alert.querySelector('.alert-progress');
            if (progressBar) progressBar.style.animationPlayState = 'paused';
        });
        
        alert.addEventListener('mouseleave', () => {
            const progressBar = alert.querySelector('.alert-progress');
            if (progressBar) progressBar.style.animationPlayState = 'running';
            setTimeout(() => dismissAlert(alert), 1000);
        });
        
        return alert;
    }

    function dismissAlert(alert) {
        if (!alert || alert.classList.contains('hiding')) return;
        alert.classList.add('hiding');
        setTimeout(() => {
            if (alert.parentElement) alert.remove();
        }, 300);
    }

    if (elements.productModal) {
        elements.productModal.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
    }

    const closeButtons = document.querySelectorAll('.modal-close');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    window.ProductsModule = {
        openAddModal,
        editProduct,
        closeModal,
        deleteProduct,
        verProduto,
        loadProducts
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

    loadProducts();

})();
</script>