<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE PRODUTOS (Design GitHub Dark)
 * Arquivo: company/modules/produtos/produtos.php
 * ================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Sessão Expirada</div>';
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

$db_paths = [
    __DIR__ . '/../../../../registration/includes/db.php',
    dirname(dirname(dirname(__FILE__))) . '/registration/includes/db.php'
];

$db_connected = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected || !isset($mysqli)) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Erro de Conexão</div>';
    exit;
}

// Verificar coluna user_id
$check_column = $mysqli->query("SHOW COLUMNS FROM products LIKE 'user_id'");
if ($check_column->num_rows === 0) {
    ?>
    <div style="padding: 40px; background: rgba(255, 77, 77, 0.1); border: 1px solid rgba(255, 77, 77, 0.3); border-radius: 12px; margin: 20px;">
        <h3 style="color: #ff4d4d;"><i class="fa-solid fa-database"></i> Migração Necessária</h3>
        <pre style="background: #000; padding: 16px; border-radius: 8px; color: #00ff88; font-size: 13px; overflow-x: auto; margin-top: 16px;">ALTER TABLE `products` 
ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `id`,
ADD INDEX `idx_user_id` (`user_id`),
ADD CONSTRAINT `fk_products_user` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE CASCADE;</pre>
    </div>
    <?php
    exit;
}

// Dados iniciais serão carregados via JavaScript usando search_products.php
$search = '';
$category_filter = '';
$status_filter = '';
$stats_data = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'recurring' => 0
];
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
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead {
    background: var(--gh-bg-primary);
    border-bottom: 1px solid var(--gh-border);
}

.table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    padding: 12px 16px;
    border-top: 1px solid var(--gh-border);
    font-size: 14px;
    color: var(--gh-text);
}

.table tbody tr:hover {
    background: var(--gh-bg-primary);
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

.modal-dialog {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
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

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--gh-border);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.alert {
    padding: 12px 16px;
    border: 1px solid;
    border-radius: 6px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    animation: slideIn 0.2s ease;
}

.alert-success {
    background: rgba(46, 160, 67, 0.1);
    border-color: rgba(46, 160, 67, 0.4);
    color: #3fb950;
}

.alert-error {
    background: rgba(248, 81, 73, 0.1);
    border-color: rgba(248, 81, 73, 0.4);
    color: #f85149;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
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
}
</style>

<div id="alert-container"></div>

<div class="products-header">
    <h1 class="products-title">
        <i class="fa-solid fa-box-open"></i>
        Produtos
    </h1>
    <div style="display: flex; gap: 8px; align-items: center;">
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
        <button class="btn btn-primary" onclick="openAddProductPage()">
            <i class="fa-solid fa-plus"></i>
            Novo Produto
        </button>
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

<div class="modal" id="productModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Adicionar Produto</h2>
            <button class="modal-close" onclick="window.ProductsModule.closeModal()">&times;</button>
        </div>
        <form id="productForm">
            <input type="hidden" id="productId">
            <input type="hidden" id="formAction" value="add">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" class="form-control" id="productName" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" id="productDescription"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Categoria *</label>
                        <select class="form-control" id="productCategory" required>
                            <option value="addon">Addon</option>
                            <option value="service">Serviço</option>
                            <option value="consultation">Consultoria</option>
                            <option value="training">Treinamento</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Preço *</label>
                        <input type="number" step="0.01" class="form-control" id="productPrice" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Moeda</label>
                        <select class="form-control" id="productCurrency">
                            <option value="MZN">MZN</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estoque</label>
                        <input type="number" class="form-control" id="productStock" placeholder="Vazio = ilimitado">
                    </div>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="productRecurring">
                    <label class="form-check-label" for="productRecurring">Produto Recorrente</label>
                </div>

                <div class="form-group" id="billingCycleGroup" style="display: none;">
                    <label class="form-label">Ciclo de Cobrança</label>
                    <select class="form-control" id="productBillingCycle">
                        <option value="monthly">Mensal</option>
                        <option value="yearly">Anual</option>
                        <option value="one_time">Único</option>
                    </select>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="productActive" checked>
                    <label class="form-check-label" for="productActive">Produto Ativo</label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.ProductsModule.closeModal()">
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
    
    // Limpar inicialização anterior se existir
    if (window.ProductsModuleInitialized) {
        console.log('🔄 Reinicializando módulo de produtos...');
        delete window.ProductsModule;
        delete window.ProductsModuleInitialized;
    }
    
    // Marcar como inicializado
    window.ProductsModuleInitialized = true;
    
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
            
        } catch (error) {
            console.error('Erro:', error);
            showAlert('error', 'Erro ao carregar produtos');
        }
    }

    // Atualizar estatísticas
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

    // Renderizar tabela
    function renderProductsTable(products) {
        if (!elements.productsTable) return;
        
        if (products.length === 0) {
            elements.productsTable.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; color: var(--gh-text);">Nenhum produto encontrado</h3>
                    <p style="margin: 0 0 16px 0;">Comece adicionando seu primeiro produto</p>
                    <button class="btn btn-primary" onclick="window.ProductsModule.openAddModal()">
                        <i class="fa-solid fa-plus"></i>
                        Adicionar Produto
                    </button>
                </div>
            `;
            return;
        }
        
        let tableHTML = `
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Nome</th>
                        <th style="width: 120px;">Categoria</th>
                        <th style="width: 130px;">Preço</th>
                        <th style="width: 100px;">Estoque</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 100px; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        products.forEach(product => {
            const categoryLabels = {
                addon: 'Addon',
                service: 'Serviço',
                consultation: 'Consultoria',
                training: 'Treinamento',
                other: 'Outro'
            };
            
            const stockColor = product.stock_quantity !== null 
                ? (product.stock_quantity > 10 ? '#3fb950' : (product.stock_quantity > 0 ? '#d29922' : '#f85149'))
                : 'var(--gh-text-secondary)';
            
            const stockDisplay = product.stock_quantity !== null ? product.stock_quantity : '∞';
            const productJson = JSON.stringify(product).replace(/'/g, '&#39;');
            const productName = escapeHtml(product.name).replace(/'/g, '&#39;');
            
            tableHTML += `
                <tr>
                    <td><code>#${product.id}</code></td>
                    <td>
                        <div style="font-weight: 600;">${escapeHtml(product.name)}</div>
                        ${product.description ? `<div style="font-size: 12px; color: var(--gh-text-secondary); margin-top: 2px;">${escapeHtml(product.description.substring(0, 50))}${product.description.length > 50 ? '...' : ''}</div>` : ''}
                    </td>
                    <td>
                        <span class="label label-${product.category}">
                            ${categoryLabels[product.category] || product.category}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 600;">${formatPrice(product.price)} ${product.currency}</div>
                        ${product.is_recurring == 1 ? `<div style="font-size: 12px; color: var(--gh-text-secondary);">/ ${product.billing_cycle}</div>` : ''}
                    </td>
                    <td>
                        <span style="color: ${stockColor};">${stockDisplay}</span>
                    </td>
                    <td>
                        <span class="badge badge-${product.is_active == 1 ? 'success' : 'secondary'}">
                            ${product.is_active == 1 ? 'Ativo' : 'Inativo'}
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <button class="btn btn-secondary btn-icon" onclick='window.ProductsModule.editProduct(${productJson})' title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-danger btn-icon" onclick="window.ProductsModule.deleteProduct(${product.id}, '${productName}')" title="Deletar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tableHTML += '</tbody></table>';
        elements.productsTable.innerHTML = tableHTML;
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

    // Modal
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Adicionar Produto';
        document.getElementById('formAction').value = 'add';
        elements.productForm.reset();
        document.getElementById('productActive').checked = true;
        elements.productModal.classList.add('show');
    }

    function editProduct(product) {
        document.getElementById('modalTitle').textContent = 'Editar Produto';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('productId').value = product.id;
        document.getElementById('productName').value = product.name;
        document.getElementById('productDescription').value = product.description || '';
        document.getElementById('productCategory').value = product.category;
        document.getElementById('productPrice').value = product.price;
        document.getElementById('productCurrency').value = product.currency;
        document.getElementById('productStock').value = product.stock_quantity || '';
        document.getElementById('productRecurring').checked = product.is_recurring == 1;
        document.getElementById('productBillingCycle').value = product.billing_cycle;
        document.getElementById('productActive').checked = product.is_active == 1;
        
        toggleBillingCycle();
        elements.productModal.classList.add('show');
    }

    function closeModal() {
        if (elements.productModal) {
            elements.productModal.classList.remove('show');
        }
    }

    function toggleBillingCycle() {
        const isRecurring = document.getElementById('productRecurring').checked;
        const billingGroup = document.getElementById('billingCycleGroup');
        if (billingGroup) {
            billingGroup.style.display = isRecurring ? 'block' : 'none';
        }
    }

    // Submit
    if (elements.productForm) {
        elements.productForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const action = document.getElementById('formAction').value;
            const actionFile = action === 'add' ? 'adicionar_produto.php' : 'editar_produto.php';
            
            const formData = new FormData();
            if (action === 'edit') formData.append('id', document.getElementById('productId').value);
            formData.append('name', document.getElementById('productName').value);
            formData.append('description', document.getElementById('productDescription').value);
            formData.append('category', document.getElementById('productCategory').value);
            formData.append('price', document.getElementById('productPrice').value);
            formData.append('currency', document.getElementById('productCurrency').value);
            formData.append('stock_quantity', document.getElementById('productStock').value);
            formData.append('is_recurring', document.getElementById('productRecurring').checked ? '1' : '0');
            formData.append('billing_cycle', document.getElementById('productBillingCycle').value);
            formData.append('is_active', document.getElementById('productActive').checked ? '1' : '0');
            
            try {
                const response = await fetch(`modules/produtos/actions/${actionFile}`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                
                if (result.success) {
                    closeModal();
                    loadProducts();
                }
            } catch (error) {
                showAlert('error', 'Erro: ' + error.message);
            }
        });
    }

    // Deletar
    async function deleteProduct(id, name) {
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

    // Alertas
    function showAlert(type, message) {
        if (!elements.alertContainer) return;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}`;
        
        elements.alertContainer.appendChild(alert);
        
        setTimeout(() => {
            alert.style.animation = 'slideIn 0.2s ease reverse';
            setTimeout(() => alert.remove(), 200);
        }, 3000);
    }

    // Modal click outside
    if (elements.productModal) {
        elements.productModal.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
    }

    // Checkbox recorrente
    const recurringCheckbox = document.getElementById('productRecurring');
    if (recurringCheckbox) {
        recurringCheckbox.addEventListener('change', toggleBillingCycle);
    }
    
    // Fechar modal no botão X
    const closeButtons = document.querySelectorAll('.modal-close');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    // Expor API pública
    window.ProductsModule = {
        openAddModal,
        editProduct,
        closeModal,
        deleteProduct,
        toggleBillingCycle
    };
    
    // Função global para abrir página de adicionar produto
    window.openAddProductPage = function() {
        if (typeof loadContent === 'function') {
            loadContent('modules/produtos/adicionar_produto_page');
        } else {
            window.location.href = '?page=modules/produtos/adicionar_produto_page';
        }
    };

    // Inicializar com valores dos filtros se existirem
    if (elements.searchInput?.value) {
        state.search = elements.searchInput.value.trim();
    }
    if (elements.categoryFilter?.value) {
        state.category = elements.categoryFilter.value;
    }
    if (elements.statusFilter?.value) {
        state.status = elements.statusFilter.value;
    }

    // Carregar produtos
    loadProducts();
    console.log('✅ Módulo de Produtos iniciado', state);
    
})();
</script>