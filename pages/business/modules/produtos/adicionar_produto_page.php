<?php
/**
 * ================================================================================
 * VISIONGREEN - ADICIONAR PRODUTO ECOLÓGICO (GitHub Dark + LocalStorage)
 * Arquivo: company/modules/produtos/adicionar_produto_page.php
 * Versão: 2.0 - Com persistência de dados
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
    __DIR__ . '/../../../registration/includes/db.php',
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

// Buscar certificações disponíveis
$certifications = $mysqli->query("SELECT * FROM eco_certifications WHERE is_active = 1 ORDER BY name");
?>

<style>
/* ==================== GitHub Dark Theme Autêntico ==================== */
:root {
    --gh-bg-primary: #0d1117;
    --gh-bg-secondary: #161b22;
    --gh-bg-tertiary: #21262d;
    --gh-border: #30363d;
    --gh-border-hover: #8b949e;
    --gh-text: #c9d1d9;
    --gh-text-secondary: #8b949e;
    --gh-text-muted: #6e7681;
    --gh-accent-green: #238636;
    --gh-accent-green-bright: #2ea043;
    --gh-accent-blue: #1f6feb;
    --gh-accent-red: #da3633;
    --gh-shadow: 0 8px 24px rgba(1, 4, 9, 0.8);
}

.add-product-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 16px;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gh-border);
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--gh-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-title i {
    color: var(--gh-accent-green-bright);
}

.back-btn {
    padding: 5px 16px;
    height: 32px;
    background: var(--gh-bg-tertiary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.back-btn:hover {
    background: var(--gh-bg-primary);
    border-color: var(--gh-border-hover);
}

.form-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
}

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gh-border);
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    font-size: 16px;
    color: var(--gh-text-secondary);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-size: 14px;
    font-weight: 600;
    color: var(--gh-text);
}

.form-label.required::after {
    content: ' *';
    color: var(--gh-accent-red);
}

.form-control {
    width: 100%;
    padding: 5px 12px;
    min-height: 32px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--gh-accent-blue);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.15);
}

.form-control::placeholder {
    color: var(--gh-text-muted);
}

select.form-control {
    padding-right: 32px;
    background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='%238b949e' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M4.427 7.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 7H4.604a.25.25 0 00-.177.427z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    appearance: none;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
    line-height: 1.5;
}

.form-help {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: var(--gh-text-secondary);
}

.image-upload-area {
    border: 1px dashed var(--gh-border);
    border-radius: 6px;
    padding: 32px;
    text-align: center;
    background: var(--gh-bg-primary);
    transition: all 0.2s ease;
    cursor: pointer;
}

.image-upload-area:hover {
    border-color: var(--gh-accent-blue);
    background: rgba(31, 111, 235, 0.05);
}

.image-upload-area.dragover {
    border-color: var(--gh-accent-green-bright);
    background: rgba(46, 160, 67, 0.05);
    border-style: solid;
}

.upload-icon {
    font-size: 48px;
    color: var(--gh-text-muted);
    margin-bottom: 12px;
}

.image-preview {
    display: none;
    margin-top: 16px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--gh-border);
}

.image-preview img {
    width: 100%;
    max-height: 400px;
    object-fit: contain;
    background: var(--gh-bg-primary);
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.checkbox-item:hover {
    background: var(--gh-bg-tertiary);
    border-color: var(--gh-border-hover);
}

.checkbox-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.checkbox-item label {
    cursor: pointer;
    margin: 0;
    font-size: 13px;
    color: var(--gh-text);
    user-select: none;
}

.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding-top: 16px;
    margin-top: 16px;
    border-top: 1px solid var(--gh-border);
}

.btn {
    padding: 5px 16px;
    height: 32px;
    border: 1px solid;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: var(--gh-accent-green);
    border-color: rgba(240, 246, 252, 0.1);
    color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
    background: var(--gh-accent-green-bright);
}

.btn-secondary {
    background: var(--gh-bg-tertiary);
    border-color: var(--gh-border);
    color: var(--gh-text);
}

.btn-secondary:hover {
    background: var(--gh-bg-primary);
    border-color: var(--gh-border-hover);
}

.btn-verify {
    background: rgba(31, 111, 235, 0.15);
    border-color: rgba(31, 111, 235, 0.4);
    color: var(--gh-accent-blue);
}

.btn-verify:hover {
    background: rgba(31, 111, 235, 0.25);
    border-color: var(--gh-accent-blue);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.alert {
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 14px;
    border: 1px solid;
    animation: slideIn 0.2s ease;
}

.alert-success {
    background: rgba(46, 160, 67, 0.15);
    border-color: rgba(46, 160, 67, 0.4);
    color: var(--gh-accent-green-bright);
}

.alert-error {
    background: rgba(218, 54, 51, 0.15);
    border-color: rgba(218, 54, 51, 0.4);
    color: #ff7b72;
}

.verification-panel {
    display: none;
    background: rgba(31, 111, 235, 0.1);
    border: 1px solid rgba(31, 111, 235, 0.3);
    border-radius: 6px;
    padding: 16px;
    margin-top: 16px;
}

.verification-panel.show {
    display: block;
    animation: slideIn 0.2s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(1, 4, 9, 0.85);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.loading-overlay.show {
    display: flex;
}

.loading-content {
    text-align: center;
    background: var(--gh-bg-secondary);
    padding: 32px 48px;
    border-radius: 12px;
    border: 1px solid var(--gh-border);
    box-shadow: var(--gh-shadow);
}

.spinner {
    width: 48px;
    height: 48px;
    border: 3px solid rgba(46, 160, 67, 0.1);
    border-top-color: var(--gh-accent-green-bright);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-content p {
    color: var(--gh-text);
    margin-top: 16px;
    font-size: 14px;
    font-weight: 500;
}

.save-indicator {
    display: inline-block;
    margin-left: 8px;
    font-size: 12px;
    color: var(--gh-accent-green-bright);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.save-indicator.show {
    opacity: 1;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    .checkbox-grid {
        grid-template-columns: 1fr;
    }
    .action-buttons {
        flex-direction: column;
    }
}

/* ==================== Validação Inline ==================== */
.form-control.error {
    border-color: var(--gh-accent-red);
    background: rgba(218, 54, 51, 0.05);
}

.form-control.error:focus {
    border-color: var(--gh-accent-red);
    box-shadow: 0 0 0 3px rgba(218, 54, 51, 0.15);
}

.form-control.success {
    border-color: var(--gh-accent-green-bright);
}

.field-error {
    display: none;
    margin-top: 6px;
    padding: 8px 12px;
    background: rgba(218, 54, 51, 0.1);
    border: 1px solid rgba(218, 54, 51, 0.3);
    border-radius: 6px;
    font-size: 12px;
    color: #ff7b72;
    animation: slideIn 0.2s ease;
}

.field-error.show {
    display: flex;
    align-items: center;
    gap: 8px;
}

.field-error i {
    flex-shrink: 0;
}

/* ==================== Modal de Erro Frontal ==================== */
.error-modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-accent-red);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(218, 54, 51, 0.3), 0 0 0 1000px rgba(1, 4, 9, 0.8);
    z-index: 10000;
    animation: modalSlideIn 0.3s ease;
    overflow: hidden;
}

.error-modal.show {
    display: flex;
    flex-direction: column;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -45%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

.error-modal-header {
    padding: 20px 24px;
    background: rgba(218, 54, 51, 0.1);
    border-bottom: 1px solid rgba(218, 54, 51, 0.3);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.error-modal-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
    font-weight: 600;
    color: #ff7b72;
}

.error-modal-title i {
    font-size: 24px;
}

.error-modal-close {
    width: 32px;
    height: 32px;
    border: none;
    background: rgba(255, 255, 255, 0.1);
    color: var(--gh-text);
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all 0.2s ease;
}

.error-modal-close:hover {
    background: rgba(218, 54, 51, 0.2);
    color: #ff7b72;
}

.error-modal-body {
    padding: 24px;
    overflow-y: auto;
}

.error-section {
    margin-bottom: 24px;
}

.error-section:last-child {
    margin-bottom: 0;
}

.error-section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.error-section-title i {
    color: var(--gh-text-secondary);
}

.error-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.error-item {
    padding: 12px 16px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-left: 3px solid var(--gh-accent-red);
    border-radius: 6px;
    margin-bottom: 8px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.error-item:hover {
    background: var(--gh-bg-tertiary);
    border-color: var(--gh-accent-red);
}

.error-item-icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gh-accent-red);
}

.error-item-content {
    flex: 1;
}

.error-item-field {
    font-size: 13px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 4px;
}

.error-item-message {
    font-size: 12px;
    color: var(--gh-text-secondary);
}

.error-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--gh-border);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.warning-section {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: 6px;
    padding: 16px;
    margin-top: 16px;
}

.warning-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #ffc107;
    margin-bottom: 12px;
}

.warning-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.warning-item {
    padding: 8px 12px;
    background: rgba(255, 193, 7, 0.05);
    border-radius: 4px;
    margin-bottom: 6px;
    font-size: 12px;
    color: var(--gh-text-secondary);
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.warning-item i {
    color: #ffc107;
    margin-top: 2px;
}
</style>

<div class="add-product-container">
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-leaf"></i>
            Adicionar Produto Ecológico
        </h1>
        <a href="javascript:void(0)" onclick="window.history.back()" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            Voltar
        </a>
    </div>

    <!-- Alert Container -->
    <div id="alert-container"></div>

    <!-- Formulário -->
    <form id="ecoProductForm" enctype="multipart/form-data">
        <!-- Informações Básicas -->
        <div class="form-card">
            <h3 class="section-title">
                <i class="fa-solid fa-info-circle"></i>
                Informações Básicas
                <span class="save-indicator" id="saveIndicator">
                    <i class="fa-solid fa-check"></i> Auto-save
                </span>
            </h3>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Nome do Produto</label>
                    <input type="text" name="name" id="name" class="form-control" required placeholder="Ex: Garrafa Reutilizável Bamboo">
                    <div class="field-error" id="name-error">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span></span>
                    </div>
                    <small class="form-help">Nome claro e descritivo</small>
                </div>

                <div class="form-group">
                    <label class="form-label required">Tipo de Produto</label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="addon">📦 Produto/Addon</option>
                        <option value="service">🛠️ Serviço</option>
                        <option value="consultation">💼 Consultoria</option>
                        <option value="training">📚 Treinamento</option>
                        <option value="other">📋 Outro</option>
                    </select>
                    <div class="field-error" id="category-error">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span></span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label required">Categoria Ecológica</label>
                <select name="eco_category" id="eco_category" class="form-control" required>
                    <option value="">Selecione a classificação ecológica...</option>
                    <option value="recyclable">♻️ Reciclável - Pode ser reciclado após uso</option>
                    <option value="reusable">🔄 Reutilizável - Pode ser usado múltiplas vezes</option>
                    <option value="biodegradable">🌱 Biodegradável - Decompõe naturalmente</option>
                    <option value="sustainable">🌿 Sustentável - Produção sustentável</option>
                    <option value="organic">🍃 Orgânico - Sem químicos/pesticidas</option>
                    <option value="zero_waste">🗑️ Zero Desperdício - Não gera resíduos</option>
                    <option value="energy_efficient">⚡ Eficiente Energeticamente - Economiza energia</option>
                </select>
                <div class="field-error" id="eco_category-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <span></span>
                </div>
                <small class="form-help">Esta categoria será verificada pela nossa IA</small>
            </div>

            <div class="form-group">
                <label class="form-label required">Descrição Detalhada</label>
                <textarea name="description" id="description" class="form-control" required placeholder="Descreva o produto, seus benefícios ambientais e como contribui para a sustentabilidade..."></textarea>
                <div class="field-error" id="description-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <span></span>
                </div>
                <small class="form-help">Inclua informações sobre materiais, processo de fabricação e impacto ambiental</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Preço</label>
                    <input type="number" step="0.01" name="price" id="price" class="form-control" required placeholder="0.00">
                    <div class="field-error" id="price-error">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Moeda</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="MZN">MZN - Metical</option>
                        <option value="USD">USD - Dólar</option>
                        <option value="EUR">EUR - Euro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Estoque Disponível</label>
                    <input type="number" name="stock_quantity" id="stock_quantity" class="form-control" placeholder="Vazio = ilimitado">
                </div>

                <div class="form-group">
                    <label class="form-label">Peso (kg)</label>
                    <input type="number" step="0.01" name="product_weight" id="product_weight" class="form-control" placeholder="0.00">
                </div>
            </div>
        </div>

        <!-- Imagem do Produto -->
        <div class="form-card">
            <h3 class="section-title">
                <i class="fa-solid fa-image"></i>
                Imagem do Produto
            </h3>

            <div class="image-upload-area" id="uploadArea">
                <div class="upload-icon">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <p style="font-size: 16px; font-weight: 600; color: var(--gh-text); margin-bottom: 8px;">
                    Arraste a imagem ou clique para selecionar
                </p>
                <p style="font-size: 13px; color: var(--gh-text-secondary);">
                    PNG, JPG ou WEBP (max. 5MB)
                </p>
                <input type="file" name="product_image" id="productImage" accept="image/*" style="display: none;">
            </div>

            <div class="image-preview" id="imagePreview">
                <img id="previewImg" src="" alt="Preview">
            </div>
        </div>

        <!-- Características Sustentáveis -->
        <div class="form-card">
            <h3 class="section-title">
                <i class="fa-solid fa-seedling"></i>
                Características Sustentáveis
            </h3>

            <div class="checkbox-grid">
                <div class="checkbox-item">
                    <input type="checkbox" name="biodegradable" id="biodegradable">
                    <label for="biodegradable">🌱 Biodegradável</label>
                </div>

                <div class="checkbox-item">
                    <input type="checkbox" name="renewable_materials" id="renewable">
                    <label for="renewable">♻️ Materiais Renováveis</label>
                </div>

                <div class="checkbox-item">
                    <input type="checkbox" name="water_efficient" id="water">
                    <label for="water">💧 Economiza Água</label>
                </div>

                <div class="checkbox-item">
                    <input type="checkbox" name="energy_efficient" id="energy">
                    <label for="energy">⚡ Economiza Energia</label>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 20px;">
                <div class="form-group">
                    <label class="form-label">Percentual Reciclável (%)</label>
                    <input type="number" min="0" max="100" name="recyclable_percentage" id="recyclable_percentage" class="form-control" placeholder="0-100">
                    <small class="form-help">Quanto do produto pode ser reciclado</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Pegada de Carbono (kg CO2)</label>
                    <input type="number" step="0.01" name="carbon_footprint" id="carbon_footprint" class="form-control" placeholder="0.00">
                    <small class="form-help">Emissões totais do ciclo de vida</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Certificação Ecológica</label>
                    <select name="eco_certification" id="eco_certification" class="form-control">
                        <option value="">Nenhuma</option>
                        <?php if ($certifications): ?>
                            <?php while ($cert = $certifications->fetch_assoc()): ?>
                                <option value="<?= $cert['code'] ?>"><?= htmlspecialchars($cert['name']) ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Impacto Ambiental Positivo</label>
                <textarea name="environmental_impact" id="environmental_impact" class="form-control" placeholder="Descreva como este produto ajuda o meio ambiente..."></textarea>
                <small class="form-help">Ex: Reduz 50kg de plástico por ano, economia de 100L de água</small>
            </div>
        </div>

        <!-- Informações Adicionais -->
        <div class="form-card">
            <h3 class="section-title">
                <i class="fa-solid fa-circle-info"></i>
                Informações Adicionais
            </h3>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Fabricante</label>
                    <input type="text" name="manufacturer" id="manufacturer" class="form-control" placeholder="Nome do fabricante">
                </div>

                <div class="form-group">
                    <label class="form-label">País de Origem</label>
                    <input type="text" name="origin_country" id="origin_country" class="form-control" placeholder="Ex: Moçambique">
                </div>

                <div class="form-group">
                    <label class="form-label">Garantia (meses)</label>
                    <input type="number" name="warranty_months" id="warranty_months" class="form-control" placeholder="12">
                </div>

                <div class="form-group">
                    <label class="form-label">Dimensões (LxAxP cm)</label>
                    <input type="text" name="dimensions" id="dimensions" class="form-control" placeholder="Ex: 10x20x5">
                </div>
            </div>
        </div>

        <!-- Painel de Verificação -->
        <div class="verification-panel" id="verificationPanel">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gh-accent-blue); margin-bottom: 12px;">
                <i class="fa-solid fa-robot"></i>
                Análise de IA em Andamento...
            </h4>
            <p style="font-size: 13px; color: var(--gh-text-secondary); margin-bottom: 16px;">
                Estamos analisando se o produto atende aos padrões de sustentabilidade da VisionGreen.
            </p>
            <div id="verificationResult"></div>
        </div>

        <!-- Botões de Ação -->
        <div class="action-buttons">
            <button type="button" class="btn btn-secondary" onclick="clearFormData()">
                <i class="fa-solid fa-eraser"></i>
                Limpar Formulário
            </button>
            <button type="button" class="btn btn-verify" id="verifyBtn" onclick="verifyProduct()">
                <i class="fa-solid fa-shield-check"></i>
                Verificar Produto
            </button>
            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                <i class="fa-solid fa-paper-plane"></i>
                Enviar para Aprovação
            </button>
        </div>
    </form>
</div>

<!-- Modal de Erro Frontal -->
<div class="error-modal" id="errorModal">
    <div class="error-modal-header">
        <div class="error-modal-title">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Problemas de Validação</span>
        </div>
        <button class="error-modal-close" onclick="closeErrorModal()">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>
    <div class="error-modal-body" id="errorModalBody">
        <!-- Conteúdo será inserido dinamicamente -->
    </div>
    <div class="error-modal-footer">
        <button class="btn btn-primary" onclick="closeErrorModal()">
            <i class="fa-solid fa-check"></i>
            Entendi, vou corrigir
        </button>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <p>Processando...</p>
    </div>
</div>

<script>
(function() {
    'use strict';

    const STORAGE_KEY = 'visiongreen_product_draft';
    const AUTOSAVE_DELAY = 1000; // 1 segundo

    let autosaveTimeout;
    const saveIndicator = document.getElementById('saveIndicator');

    // ==================== VALIDAÇÃO INLINE ====================
    
    const validationRules = {
        name: {
            required: true,
            minLength: 3,
            message: 'Nome deve ter pelo menos 3 caracteres'
        },
        category: {
            required: true,
            message: 'Selecione o tipo de produto'
        },
        eco_category: {
            required: true,
            message: 'Selecione a categoria ecológica'
        },
        description: {
            required: true,
            minLength: 50,
            message: 'Descrição deve ter pelo menos 50 caracteres'
        },
        price: {
            required: true,
            min: 0.01,
            message: 'Preço deve ser maior que zero'
        }
    };

    // Mostrar erro inline no campo
    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById(`${fieldId}-error`);
        
        if (field && errorDiv) {
            field.classList.add('error');
            field.classList.remove('success');
            errorDiv.querySelector('span').textContent = message;
            errorDiv.classList.add('show');
        }
    }

    // Limpar erro inline do campo
    function clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById(`${fieldId}-error`);
        
        if (field && errorDiv) {
            field.classList.remove('error');
            field.classList.add('success');
            errorDiv.classList.remove('show');
        }
    }

    // Validar campo individual
    function validateField(fieldId) {
        const field = document.getElementById(fieldId);
        const rules = validationRules[fieldId];
        
        if (!field || !rules) return true;

        const value = field.value.trim();

        // Required
        if (rules.required && !value) {
            showFieldError(fieldId, rules.message || 'Campo obrigatório');
            return false;
        }

        // Min length
        if (rules.minLength && value.length < rules.minLength) {
            showFieldError(fieldId, rules.message || `Mínimo ${rules.minLength} caracteres`);
            return false;
        }

        // Min value
        if (rules.min !== undefined && parseFloat(value) < rules.min) {
            showFieldError(fieldId, rules.message || `Valor mínimo: ${rules.min}`);
            return false;
        }

        // Se passou, limpar erro
        clearFieldError(fieldId);
        return true;
    }

    // Validar todos os campos
    function validateAllFields() {
        const errors = [];
        const warnings = [];

        // Validar campos obrigatórios
        Object.keys(validationRules).forEach(fieldId => {
            if (!validateField(fieldId)) {
                const field = document.getElementById(fieldId);
                const label = field?.closest('.form-group')?.querySelector('.form-label')?.textContent.replace(' *', '');
                errors.push({
                    field: fieldId,
                    label: label || fieldId,
                    message: validationRules[fieldId].message
                });
            }
        });

        // Validar imagem
        const imageFile = document.getElementById('productImage').files[0];
        if (!imageFile) {
            errors.push({
                field: 'productImage',
                label: 'Imagem do Produto',
                message: 'Por favor, adicione uma imagem do produto'
            });
        }

        // Warnings (não bloqueiam, mas alertam)
        const description = document.getElementById('description').value.trim();
        const ecoKeywords = ['sustentável', 'ecológico', 'reciclado', 'biodegradável', 'orgânico', 'renovável'];
        const hasEcoKeyword = ecoKeywords.some(keyword => description.toLowerCase().includes(keyword));
        
        if (!hasEcoKeyword) {
            warnings.push({
                message: 'Descrição não menciona termos de sustentabilidade. Adicione palavras como "sustentável", "ecológico", "reciclado" para melhor pontuação.'
            });
        }

        const recyclable = document.getElementById('recyclable_percentage').value;
        if (!recyclable || recyclable == 0) {
            warnings.push({
                message: 'Informe o percentual reciclável para aumentar o score ecológico'
            });
        }

        return { errors, warnings };
    }

    // Mostrar modal de erro
    function showErrorModal(errors, warnings) {
        const modal = document.getElementById('errorModal');
        const body = document.getElementById('errorModalBody');
        
        let html = '';

        // Seção de erros
        if (errors.length > 0) {
            html += `
                <div class="error-section">
                    <div class="error-section-title">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span>Campos Obrigatórios (${errors.length})</span>
                    </div>
                    <ul class="error-list">
            `;

            errors.forEach(error => {
                html += `
                    <li class="error-item" onclick="focusField('${error.field}')">
                        <div class="error-item-icon">
                            <i class="fa-solid fa-exclamation-circle"></i>
                        </div>
                        <div class="error-item-content">
                            <div class="error-item-field">${error.label}</div>
                            <div class="error-item-message">${error.message}</div>
                        </div>
                        <i class="fa-solid fa-arrow-right" style="color: var(--gh-text-secondary);"></i>
                    </li>
                `;
            });

            html += `
                    </ul>
                </div>
            `;
        }

        // Seção de avisos
        if (warnings.length > 0) {
            html += `
                <div class="warning-section">
                    <div class="warning-section-title">
                        <i class="fa-solid fa-lightbulb"></i>
                        <span>Sugestões para Melhor Score</span>
                    </div>
                    <ul class="warning-list">
            `;

            warnings.forEach(warning => {
                html += `
                    <li class="warning-item">
                        <i class="fa-solid fa-info-circle"></i>
                        <span>${warning.message}</span>
                    </li>
                `;
            });

            html += `
                    </ul>
                </div>
            `;
        }

        body.innerHTML = html;
        modal.classList.add('show');
    }

    // Fechar modal de erro
    window.closeErrorModal = function() {
        document.getElementById('errorModal').classList.remove('show');
    };

    // Focar no campo com erro
    window.focusField = function(fieldId) {
        closeErrorModal();
        const field = document.getElementById(fieldId);
        if (field) {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => {
                field.focus();
            }, 300);
        }
    };

    // Validação em tempo real
    function setupRealtimeValidation() {
        Object.keys(validationRules).forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field) return;

            // Validar ao perder foco
            field.addEventListener('blur', () => {
                if (field.value.trim()) {
                    validateField(fieldId);
                }
            });

            // Limpar erro ao começar a digitar
            field.addEventListener('input', () => {
                if (field.classList.contains('error')) {
                    clearFieldError(fieldId);
                }
            });
        });
    }

    // Campos para salvar
    const fields = [
        'name', 'category', 'eco_category', 'description', 'price', 'currency',
        'stock_quantity', 'product_weight', 'biodegradable', 'renewable',
        'water', 'energy', 'recyclable_percentage', 'carbon_footprint',
        'eco_certification', 'environmental_impact', 'manufacturer',
        'origin_country', 'warranty_months', 'dimensions'
    ];

    // Carregar dados salvos ao inicializar
    function loadSavedData() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;

        try {
            const data = JSON.parse(saved);
            console.log('📦 Carregando dados salvos:', data);

            fields.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (!element) return;

                if (element.type === 'checkbox') {
                    element.checked = data[fieldId] === true;
                } else {
                    element.value = data[fieldId] || '';
                }
            });

            showAlert('info', 'Rascunho restaurado automaticamente');
        } catch (e) {
            console.error('Erro ao carregar dados:', e);
        }
    }

    // Salvar dados
    function saveFormData() {
        const data = {};
        
        fields.forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (!element) return;

            if (element.type === 'checkbox') {
                data[fieldId] = element.checked;
            } else {
                data[fieldId] = element.value;
            }
        });

        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        
        // Mostrar indicador
        saveIndicator.classList.add('show');
        setTimeout(() => {
            saveIndicator.classList.remove('show');
        }, 2000);
    }

    // Auto-save ao digitar
    function setupAutosave() {
        fields.forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (!element) return;

            element.addEventListener('input', () => {
                clearTimeout(autosaveTimeout);
                autosaveTimeout = setTimeout(saveFormData, AUTOSAVE_DELAY);
            });

            element.addEventListener('change', saveFormData);
        });
    }

    // Limpar formulário e localStorage
    window.clearFormData = function() {
        if (confirm('Tem certeza que deseja limpar todos os dados do formulário?')) {
            localStorage.removeItem(STORAGE_KEY);
            document.getElementById('ecoProductForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('verificationPanel').classList.remove('show');
            document.getElementById('submitBtn').disabled = true;
            showAlert('success', 'Formulário limpo com sucesso!');
        }
    };

    // Upload de imagem
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('productImage');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');

    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleImageUpload(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleImageUpload(e.target.files[0]);
        }
    });

    function handleImageUpload(file) {
        if (!file.type.startsWith('image/')) {
            showAlert('error', 'Por favor, selecione uma imagem válida');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            showAlert('error', 'A imagem deve ter no máximo 5MB');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            imagePreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    // Verificar produto
    window.verifyProduct = async function() {
        // Validar todos os campos primeiro
        const { errors, warnings } = validateAllFields();

        if (errors.length > 0) {
            showErrorModal(errors, warnings);
            return;
        }

        const form = document.getElementById('ecoProductForm');
        const formData = new FormData(form);

        const loadingOverlay = document.getElementById('loadingOverlay');
        const verificationPanel = document.getElementById('verificationPanel');
        
        loadingOverlay.classList.add('show');

        try {
            // Construir URL correta
            const currentPath = window.location.pathname;
            const basePath = currentPath.substring(0, currentPath.indexOf('/pages/business/'));
            const apiUrl = basePath + '/pages/business/modules/produtos/actions/verificar_produto.php';
            
            console.log('Verificando em:', apiUrl);

            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });

            // Verificar se response é ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Tentar pegar o texto primeiro
            const text = await response.text();
            console.log('Resposta do servidor:', text);

            // Tentar fazer parse do JSON
            let result;
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('Erro ao fazer parse do JSON:', parseError);
                console.error('Texto recebido:', text);
                throw new Error('Resposta inválida do servidor. Verifique o console.');
            }

            loadingOverlay.classList.remove('show');
            verificationPanel.classList.add('show');

            if (result.success) {
                document.getElementById('verificationResult').innerHTML = `
                    <div style="background: rgba(46, 160, 67, 0.15); border: 1px solid rgba(46, 160, 67, 0.4); border-radius: 6px; padding: 16px;">
                        <h5 style="color: var(--gh-accent-green-bright); font-size: 14px; font-weight: 600; margin-bottom: 8px;">
                            <i class="fa-solid fa-check-circle"></i>
                            Produto Aprovado!
                        </h5>
                        <p style="font-size: 13px; color: var(--gh-text-secondary); margin-bottom: 12px;">
                            Score de Sustentabilidade: <strong style="color: var(--gh-accent-green-bright);">${result.score}/10</strong>
                        </p>
                        <p style="font-size: 13px; color: var(--gh-text-secondary);">
                            ${result.analysis || 'Produto verificado com sucesso!'}
                        </p>
                    </div>
                `;
                document.getElementById('submitBtn').disabled = false;
                showAlert('success', 'Produto verificado e aprovado! Você pode enviá-lo agora.');
                
                // Scroll suave até o botão de enviar
                document.getElementById('submitBtn').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                document.getElementById('verificationResult').innerHTML = `
                    <div style="background: rgba(218, 54, 51, 0.15); border: 1px solid rgba(218, 54, 51, 0.4); border-radius: 6px; padding: 16px;">
                        <h5 style="color: #ff7b72; font-size: 14px; font-weight: 600; margin-bottom: 8px;">
                            <i class="fa-solid fa-times-circle"></i>
                            Produto Não Aprovado
                        </h5>
                        <p style="font-size: 13px; color: var(--gh-text-secondary);">
                            ${result.message || 'Produto não atende aos critérios ecológicos'}
                        </p>
                    </div>
                `;
                showAlert('error', result.message || 'Produto não aprovado');
            }
        } catch (error) {
            loadingOverlay.classList.remove('show');
            console.error('Erro completo:', error);
            showAlert('error', 'Erro ao verificar produto: ' + error.message);
        }
    };

    // Submit do formulário
    document.getElementById('ecoProductForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        const loadingOverlay = document.getElementById('loadingOverlay');

        loadingOverlay.classList.add('show');

        try {
            // Construir URL correta baseada na localização atual
            const currentPath = window.location.pathname;
            const basePath = currentPath.substring(0, currentPath.indexOf('/pages/business/'));
            const apiUrl = basePath + '/pages/business/modules/produtos/actions/salvar_produto_ecologico.php';
            
            console.log('Enviando para:', apiUrl);

            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });

            // Verificar se response é ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Pegar texto primeiro para debug
            const text = await response.text();
            console.log('Resposta do servidor:', text);

            // Tentar fazer parse
            let result;
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('Erro ao fazer parse do JSON:', parseError);
                console.error('Texto recebido:', text);
                throw new Error('Resposta inválida do servidor. Verifique o console para detalhes.');
            }

            loadingOverlay.classList.remove('show');

            if (result.success) {
                // Limpar localStorage após sucesso
                localStorage.removeItem(STORAGE_KEY);
                
                showAlert('success', result.message || 'Produto cadastrado com sucesso!');
                setTimeout(() => {
                    if (typeof loadContent === 'function') {
                        loadContent('modules/produtos/produtos');
                    } else {
                        window.location.href = 'index.php?page=modules/produtos/produtos';
                    }
                }, 2000);
            } else {
                showAlert('error', result.message || 'Erro ao cadastrar produto');
            }
        } catch (error) {
            loadingOverlay.classList.remove('show');
            console.error('Erro completo:', error);
            showAlert('error', 'Erro: ' + error.message);
        }
    });

    // Alertas
    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fa-solid fa-${type === 'success' ? 'check' : (type === 'error' ? 'exclamation' : 'info')}-circle"></i>
            ${message}
        `;
        container.appendChild(alert);

        setTimeout(() => {
            alert.style.animation = 'slideIn 0.2s ease reverse';
            setTimeout(() => alert.remove(), 200);
        }, 5000);
    }

    // Inicializar
    loadSavedData();
    setupAutosave();
    setupRealtimeValidation();

    console.log('✅ Formulário de Produto Ecológico carregado com auto-save e validação');
})();
</script>