<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    
    require_once __DIR__ . '/../../../../registration/includes/db.php';
    
    $stmt = $mysqli->prepare("SELECT can_create FROM employee_permissions WHERE employee_id = ? AND module = 'produtos' LIMIT 1");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $permissions = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$permissions || !$permissions['can_create']) {
        echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">
            <i class="fa-solid fa-ban" style="font-size: 48px; margin-bottom: 16px;"></i>
            <h3>Sem Permissão</h3>
            <p>Você não pode criar produtos.</p>
        </div>';
        exit;
    }
} else {
    $userId = (int)$_SESSION['auth']['user_id'];
}
?>

<style>
.image-upload-wrapper {
    position: relative;
    width: 100%;
    min-height: 200px;
    border: 2px dashed var(--gh-border);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    overflow: hidden;
    background: var(--gh-bg-secondary);
}

.image-upload-wrapper:hover {
    border-color: var(--primary);
    background: rgba(0, 255, 136, 0.05);
}

.image-upload-wrapper.has-image {
    border-style: solid;
    border-color: var(--primary);
    padding: 0;
}

.upload-placeholder {
    text-align: center;
    padding: 40px 20px;
}

.upload-placeholder i {
    font-size: 48px;
    color: var(--gh-text-secondary);
    margin-bottom: 16px;
    display: block;
}

.upload-placeholder p {
    margin: 0;
    color: var(--gh-text);
    font-weight: 600;
}

.upload-placeholder small {
    display: block;
    margin-top: 8px;
    color: var(--gh-text-secondary);
}

.image-preview-container {
    display: none;
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 200px;
}

.image-preview-container.show {
    display: block;
}

.image-preview-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
}

.image-overlay-actions {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 10px;
}

.image-preview-container:hover .image-overlay-actions {
    opacity: 1;
}

.image-overlay-actions button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.image-overlay-actions button:hover {
    background: #00a85a;
    transform: scale(1.05);
}

.image-upload-small {
    position: relative;
    width: 100%;
    height: 120px;
    border: 2px dashed var(--gh-border);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--gh-bg-secondary);
}

.image-upload-small:hover {
    border-color: var(--primary);
    background: rgba(0, 255, 136, 0.05);
}

.image-upload-small.has-image {
    border-style: solid;
    border-color: var(--primary);
    padding: 0;
}

.image-upload-small .upload-placeholder {
    padding: 20px 10px;
}

.image-upload-small .upload-placeholder i {
    font-size: 24px;
    margin-bottom: 8px;
}

.image-upload-small .upload-placeholder p {
    font-size: 11px;
}

.image-upload-small .image-preview-container {
    min-height: 120px;
}

.save-indicator {
    position: fixed;
    top: 80px;
    right: 20px;
    background: var(--primary);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.save-indicator.show {
    opacity: 1;
    transform: translateY(0);
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateY(0); opacity: 1; }
    to { transform: translateY(-20px); opacity: 0; }
}
</style>

<div class="add-product-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-leaf"></i>
            Adicionar Produto
        </h1>
        <a href="javascript:void(0)" onclick="window.history.back()" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            Voltar
        </a>
    </div>

    <div id="alert-container"></div>

    <div class="save-indicator" id="saveIndicator">
        <i class="fa-solid fa-check-circle"></i>
        Rascunho salvo
    </div>

    <form id="productForm" enctype="multipart/form-data">
        <div class="form-card">
            <h3 class="section-title">
                <i class="fa-solid fa-info-circle"></i>
                Informações Básicas
            </h3>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Nome do Produto</label>
                    <input type="text" name="nome" id="nome" class="form-control" required placeholder="Ex: Garrafa Reutilizável">
                </div>

                <div class="form-group">
                    <label class="form-label required">Categoria</label>
                    <select name="category_id" id="category_id" class="form-control" required>
                        <option value="">Selecione...</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label required">Descrição</label>
                <textarea name="descricao" id="descricao" class="form-control" required placeholder="Descreva o produto..." rows="4"></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Preço</label>
                    <input type="number" step="0.01" name="preco" id="preco" class="form-control" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label class="form-label">Moeda</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="MZN" selected>MZN</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Estoque</label>
                    <input type="number" name="stock" id="stock" class="form-control" placeholder="Quantidade" value="0">
                </div>

                <div class="form-group">
                    <label class="form-label">Estoque Mínimo</label>
                    <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" value="5">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="ativo" selected>✓ Ativo</option>
                    <option value="inativo">✗ Inativo</option>
                </select>
            </div>
        </div>

        <div class="form-card">
            <h3 class="section-title">
                <i class="fa-solid fa-images"></i>
                Imagens do Produto
            </h3>

            <div class="form-group">
                <label class="form-label">Imagem Principal</label>
                <div class="image-upload-wrapper" id="uploadWrapper">
                    <input type="file" name="imagem" id="imagem" accept="image/*" style="display: none;">
                    
                    <div class="upload-placeholder" id="uploadPlaceholder">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p>Clique para selecionar imagem</p>
                        <small>PNG, JPG ou WEBP (max. 5MB)</small>
                    </div>
                    
                    <div class="image-preview-container" id="imagePreview">
                        <img id="previewImg" src="" alt="Preview">
                        <div class="image-overlay-actions">
                            <button type="button" id="changeImageBtn">
                                <i class="fa-solid fa-camera"></i>
                                Trocar Imagem
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 24px;">
                <?php for($i = 1; $i <= 4; $i++): ?>
                <div class="form-group">
                    <label class="form-label">Imagem <?= $i ?></label>
                    <div class="image-upload-small image-upload-wrapper" id="uploadWrapper<?= $i ?>">
                        <input type="file" name="image_path<?= $i ?>" id="image_path<?= $i ?>" accept="image/*" style="display: none;">
                        
                        <div class="upload-placeholder" id="uploadPlaceholder<?= $i ?>">
                            <i class="fa-solid fa-image"></i>
                            <p>Adicionar</p>
                        </div>
                        
                        <div class="image-preview-container" id="imagePreview<?= $i ?>">
                            <img id="previewImg<?= $i ?>" src="" alt="Preview <?= $i ?>">
                            <div class="image-overlay-actions">
                                <button type="button" class="change-image-btn" data-index="<?= $i ?>">
                                    <i class="fa-solid fa-upload"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="action-buttons">
            <button type="button" class="btn btn-secondary" onclick="clearAllData()">
                <i class="fa-solid fa-eraser"></i>
                Limpar Tudo
            </button>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fa-solid fa-save"></i>
                Salvar Produto
            </button>
        </div>
    </form>
</div>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <p>Salvando produto...</p>
    </div>
</div>

<script>
(function() {
    'use strict';

    const STORAGE_KEY = 'vsg_product_draft';
    const STORAGE_IMAGES_KEY = 'vsg_product_images';
    const AUTOSAVE_DELAY = 1000;

    const form = document.getElementById('productForm');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const saveIndicator = document.getElementById('saveIndicator');
    
    let autosaveTimeout;

    const formFields = ['nome', 'category_id', 'descricao', 'preco', 'currency', 'stock', 'stock_minimo', 'status'];

    function saveFormData() {
        const data = {};
        formFields.forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (element) {
                data[fieldId] = element.value;
            }
        });

        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        
        saveIndicator.classList.add('show');
        setTimeout(() => {
            saveIndicator.classList.remove('show');
        }, 2000);
    }

    function saveImageData(imageKey, dataUrl) {
        const images = JSON.parse(localStorage.getItem(STORAGE_IMAGES_KEY) || '{}');
        images[imageKey] = dataUrl;
        localStorage.setItem(STORAGE_IMAGES_KEY, JSON.stringify(images));
    }

    function loadSavedImages() {
        const images = JSON.parse(localStorage.getItem(STORAGE_IMAGES_KEY) || '{}');
        
        Object.keys(images).forEach(imageKey => {
            const dataUrl = images[imageKey];
            if (!dataUrl) return;
            
            const img = document.getElementById('previewImg' + (imageKey === 'imagem' ? '' : imageKey.replace('image_path', '')));
            const preview = document.getElementById('imagePreview' + (imageKey === 'imagem' ? '' : imageKey.replace('image_path', '')));
            const placeholder = document.getElementById('uploadPlaceholder' + (imageKey === 'imagem' ? '' : imageKey.replace('image_path', '')));
            const wrapper = document.getElementById('uploadWrapper' + (imageKey === 'imagem' ? '' : imageKey.replace('image_path', '')));
            
            if (img && preview && placeholder && wrapper) {
                img.src = dataUrl;
                placeholder.style.display = 'none';
                preview.classList.add('show');
                wrapper.classList.add('has-image');
            }
        });
    }

    function loadSavedData() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;

        try {
            const data = JSON.parse(saved);
            console.log('📦 Carregando rascunho salvo...');

            formFields.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element && data[fieldId]) {
                    element.value = data[fieldId];
                }
            });

            loadSavedImages();

            showAlert('info', '📝 Rascunho restaurado automaticamente');
        } catch (e) {
            console.error('Erro ao carregar rascunho:', e);
        }
    }

    function setupAutosave() {
        formFields.forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (!element) return;

            element.addEventListener('input', () => {
                clearTimeout(autosaveTimeout);
                autosaveTimeout = setTimeout(saveFormData, AUTOSAVE_DELAY);
            });

            element.addEventListener('change', saveFormData);
        });
    }

    async function loadCategories() {
        try {
            const response = await fetch('modules/produtos/actions/get_categories.php');
            const data = await response.json();
            
            if (data.success && data.categories) {
                const select = document.getElementById('category_id');
                select.innerHTML = '<option value="">Selecione...</option>';
                data.categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.id;
                    option.textContent = cat.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar categorias:', error);
            showAlert('error', 'Erro ao carregar categorias');
        }
    }

    function setupImageUpload(inputId, wrapperId, previewId, placeholderId) {
        const input = document.getElementById(inputId);
        const wrapper = document.getElementById(wrapperId);
        const preview = document.getElementById(previewId);
        const placeholder = document.getElementById(placeholderId);
        const img = preview.querySelector('img');

        if (!input || !wrapper || !preview || !placeholder) return;

        wrapper.addEventListener('click', (e) => {
            if (!e.target.closest('.image-overlay-actions')) {
                input.click();
            }
        });

        input.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                showAlert('error', 'Selecione uma imagem válida');
                input.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showAlert('error', 'Imagem deve ter no máximo 5MB');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target.result;
                img.src = dataUrl;
                placeholder.style.display = 'none';
                preview.classList.add('show');
                wrapper.classList.add('has-image');
                
                saveImageData(input.name, dataUrl);
            };
            reader.readAsDataURL(file);
        });

        const changeBtn = preview.querySelector('button');
        if (changeBtn) {
            changeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                input.click();
            });
        }
    }

    setupImageUpload('imagem', 'uploadWrapper', 'imagePreview', 'uploadPlaceholder');

    for (let i = 1; i <= 4; i++) {
        setupImageUpload(
            `image_path${i}`, 
            `uploadWrapper${i}`, 
            `imagePreview${i}`, 
            `uploadPlaceholder${i}`
        );
    }

    function validateForm() {
        const nome = document.getElementById('nome').value.trim();
        const category_id = document.getElementById('category_id').value;
        const descricao = document.getElementById('descricao').value.trim();
        const preco = parseFloat(document.getElementById('preco').value);

        if (!nome || nome.length < 3) {
            showAlert('error', 'Nome deve ter pelo menos 3 caracteres');
            document.getElementById('nome').focus();
            return false;
        }

        if (!category_id) {
            showAlert('error', 'Selecione uma categoria');
            document.getElementById('category_id').focus();
            return false;
        }

        if (!descricao || descricao.length < 10) {
            showAlert('error', 'Descrição deve ter pelo menos 10 caracteres');
            document.getElementById('descricao').focus();
            return false;
        }

        if (!preco || preco <= 0) {
            showAlert('error', 'Preço deve ser maior que zero');
            document.getElementById('preco').focus();
            return false;
        }

        return true;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!validateForm()) return;

        const formData = new FormData(form);
        const submitBtn = document.getElementById('submitBtn');
        
        loadingOverlay.classList.add('show');
        submitBtn.disabled = true;

        try {
            const response = await fetch('modules/produtos/actions/adicionar_produto.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const text = await response.text();
            let result;
            
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('Resposta inválida:', text.substring(0, 200));
                throw new Error('Resposta inválida do servidor');
            }

            loadingOverlay.classList.remove('show');
            submitBtn.disabled = false;

            if (result.success) {
                localStorage.removeItem(STORAGE_KEY);
                localStorage.removeItem(STORAGE_IMAGES_KEY);
                showAlert('success', result.message || 'Produto cadastrado com sucesso!');
                setTimeout(() => {
                    if (typeof loadContent === 'function') {
                        loadContent('modules/produtos/produtos');
                    } else {
                        window.location.href = 'index.php?page=modules/produtos/produtos';
                    }
                }, 1500);
            } else {
                showAlert('error', result.message || 'Erro ao cadastrar produto');
            }
        } catch (error) {
            loadingOverlay.classList.remove('show');
            submitBtn.disabled = false;
            console.error('Erro:', error);
            showAlert('error', 'Erro: ' + error.message);
        }
    });

    window.clearAllData = function() {
        if (!confirm('⚠️ Deseja limpar todos os dados do formulário?\n\nEsta ação não pode ser desfeita.')) {
            return;
        }

        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(STORAGE_IMAGES_KEY);

        form.reset();

        const imagePreviews = ['imagePreview', 'imagePreview1', 'imagePreview2', 'imagePreview3', 'imagePreview4'];
        const imagePlaceholders = ['uploadPlaceholder', 'uploadPlaceholder1', 'uploadPlaceholder2', 'uploadPlaceholder3', 'uploadPlaceholder4'];
        const imageWrappers = ['uploadWrapper', 'uploadWrapper1', 'uploadWrapper2', 'uploadWrapper3', 'uploadWrapper4'];

        imagePreviews.forEach((id, index) => {
            const preview = document.getElementById(id);
            const placeholder = document.getElementById(imagePlaceholders[index]);
            const wrapper = document.getElementById(imageWrappers[index]);

            if (preview) {
                preview.classList.remove('show');
                const img = preview.querySelector('img');
                if (img) img.src = '';
            }

            if (placeholder) {
                placeholder.style.display = 'block';
            }

            if (wrapper) {
                wrapper.classList.remove('has-image');
            }
        });

        showAlert('success', '🗑️ Todos os dados foram limpos!');
    };

    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        if (!container) return;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };
        
        const colors = {
            success: { bg: '#d4edda', text: '#155724', border: '#c3e6cb' },
            error: { bg: '#f8d7da', text: '#721c24', border: '#f5c6cb' },
            info: { bg: '#d1ecf1', text: '#0c5460', border: '#bee5eb' }
        };
        
        const color = colors[type] || colors.info;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.style.cssText = `
            padding: 16px 20px;
            margin-bottom: 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: ${color.bg};
            color: ${color.text};
            border: 1px solid ${color.border};
            animation: slideIn 0.3s ease;
        `;
        
        alert.innerHTML = `
            <i class="fa-solid ${icons[type]}" style="font-size: 20px;"></i>
            <span style="flex: 1;">${message}</span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: inherit; font-size: 20px; padding: 0; width: 24px; height: 24px;">×</button>
        `;
        
        container.appendChild(alert);
        
        setTimeout(() => {
            alert.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }

    loadCategories();
    loadSavedData();
    setupAutosave();

    console.log('✅ Formulário de adicionar produto carregado');
    console.log('💾 Autosave ativado (salva a cada 1 segundo)');
    console.log('🖼️ Persistência de imagens ativada');
})();
</script>