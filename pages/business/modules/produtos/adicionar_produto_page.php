<?php
/**
 * ================================================================================
 * VISIONGREEN - ADICIONAR PRODUTO
 * Arquivo: pages/business/modules/produtos/adicionar_produto_page.php
 * ✅ CORRIGIDO: Campos ajustados para estrutura SQL real
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
?>

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

    <form id="ecoProductForm" enctype="multipart/form-data">
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
                    <input type="text" name="nome" id="nome" class="form-control" required placeholder="Ex: Garrafa Reutilizável">
                    <div class="field-error" id="nome-error">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span></span>
                    </div>
                    <small class="form-help">Nome claro e descritivo</small>
                </div>

                <div class="form-group">
                    <label class="form-label required">Categoria</label>
                    <select name="categoria" id="categoria" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="reciclavel">♻️ Reciclável</option>
                        <option value="sustentavel">🌿 Sustentável</option>
                        <option value="servico">🛠️ Serviço</option>
                        <option value="visiongreen">🌱 VisionGreen</option>
                        <option value="ecologico">🌍 Ecológico</option>
                        <option value="outro">📦 Outro</option>
                    </select>
                    <div class="field-error" id="categoria-error">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span></span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label required">Descrição</label>
                <textarea name="descricao" id="descricao" class="form-control" required placeholder="Descreva o produto..."></textarea>
                <div class="field-error" id="descricao-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <span></span>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Preço</label>
                    <input type="number" step="0.01" name="preco" id="preco" class="form-control" required placeholder="0.00">
                    <div class="field-error" id="preco-error">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Moeda</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="MZN">MZN</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Estoque</label>
                    <input type="number" name="stock" id="stock" class="form-control" placeholder="Quantidade">
                </div>

                <div class="form-group">
                    <label class="form-label">Estoque Mínimo</label>
                    <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" value="5">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="ativo">✓ Ativo</option>
                    <option value="inativo">✗ Inativo</option>
                </select>
            </div>
        </div>

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
                <input type="file" name="imagem" id="imagem" accept="image/*" style="display: none;">
            </div>

            <div class="image-preview" id="imagePreview">
                <img id="previewImg" src="" alt="Preview">
            </div>
            
            <h3 class="section-title" style="margin-top: 24px;">
                <i class="fa-solid fa-images"></i>
                Imagens Adicionais (Opcional)
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                <div>
                    <label class="form-label">Imagem 1</label>
                    <input type="file" name="imagem1" id="imagem1" accept="image/*" class="form-control" style="font-size: 12px; padding: 6px;">
                    <div class="image-preview" id="imagePreview1" style="margin-top: 8px; display: none;">
                        <img id="previewImg1" src="" alt="Preview 1" style="max-height: 100px;">
                    </div>
                </div>
                
                <div>
                    <label class="form-label">Imagem 2</label>
                    <input type="file" name="imagem2" id="imagem2" accept="image/*" class="form-control" style="font-size: 12px; padding: 6px;">
                    <div class="image-preview" id="imagePreview2" style="margin-top: 8px; display: none;">
                        <img id="previewImg2" src="" alt="Preview 2" style="max-height: 100px;">
                    </div>
                </div>
                
                <div>
                    <label class="form-label">Imagem 3</label>
                    <input type="file" name="imagem3" id="imagem3" accept="image/*" class="form-control" style="font-size: 12px; padding: 6px;">
                    <div class="image-preview" id="imagePreview3" style="margin-top: 8px; display: none;">
                        <img id="previewImg3" src="" alt="Preview 3" style="max-height: 100px;">
                    </div>
                </div>
                
                <div>
                    <label class="form-label">Imagem 4</label>
                    <input type="file" name="imagem4" id="imagem4" accept="image/*" class="form-control" style="font-size: 12px; padding: 6px;">
                    <div class="image-preview" id="imagePreview4" style="margin-top: 8px; display: none;">
                        <img id="previewImg4" src="" alt="Preview 4" style="max-height: 100px;">
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button type="button" class="btn btn-secondary" onclick="clearFormData()">
                <i class="fa-solid fa-eraser"></i>
                Limpar
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
        <p>Processando...</p>
    </div>
</div>

<script>
(function() {
    'use strict';

    const STORAGE_KEY = 'visiongreen_product_draft';
    const AUTOSAVE_DELAY = 1000;

    let autosaveTimeout;
    const saveIndicator = document.getElementById('saveIndicator');

    const validationRules = {
        nome: {
            required: true,
            minLength: 3,
            message: 'Nome deve ter pelo menos 3 caracteres'
        },
        categoria: {
            required: true,
            message: 'Selecione a categoria'
        },
        descricao: {
            required: true,
            minLength: 10,
            message: 'Descrição deve ter pelo menos 10 caracteres'
        },
        preco: {
            required: true,
            min: 0.01,
            message: 'Preço deve ser maior que zero'
        }
    };

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

    function clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById(`${fieldId}-error`);
        
        if (field && errorDiv) {
            field.classList.remove('error');
            field.classList.add('success');
            errorDiv.classList.remove('show');
        }
    }

    function validateField(fieldId) {
        const field = document.getElementById(fieldId);
        const rules = validationRules[fieldId];
        
        if (!field || !rules) return true;

        const value = field.value.trim();

        if (rules.required && !value) {
            showFieldError(fieldId, rules.message || 'Campo obrigatório');
            return false;
        }

        if (rules.minLength && value.length < rules.minLength) {
            showFieldError(fieldId, rules.message || `Mínimo ${rules.minLength} caracteres`);
            return false;
        }

        if (rules.min !== undefined && parseFloat(value) < rules.min) {
            showFieldError(fieldId, rules.message || `Valor mínimo: ${rules.min}`);
            return false;
        }

        clearFieldError(fieldId);
        return true;
    }

    function validateAllFields() {
        let valid = true;
        Object.keys(validationRules).forEach(fieldId => {
            if (!validateField(fieldId)) {
                valid = false;
            }
        });
        return valid;
    }

    function setupRealtimeValidation() {
        Object.keys(validationRules).forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field) return;

            field.addEventListener('blur', () => {
                if (field.value.trim()) {
                    validateField(fieldId);
                }
            });

            field.addEventListener('input', () => {
                if (field.classList.contains('error')) {
                    clearFieldError(fieldId);
                }
            });
        });
    }

    const fields = [
        'nome', 'categoria', 'descricao', 'preco', 'currency',
        'stock', 'stock_minimo', 'status'
    ];

    function loadSavedData() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;

        try {
            const data = JSON.parse(saved);
            console.log('📦 Carregando dados salvos');

            fields.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element) {
                    element.value = data[fieldId] || '';
                }
            });

            showAlert('info', 'Rascunho restaurado');
        } catch (e) {
            console.error('Erro ao carregar dados:', e);
        }
    }

    function saveFormData() {
        const data = {};
        
        fields.forEach(fieldId => {
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

    window.clearFormData = function() {
        if (confirm('Limpar todos os dados?')) {
            localStorage.removeItem(STORAGE_KEY);
            document.getElementById('ecoProductForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
            showAlert('success', 'Formulário limpo!');
        }
    };

    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('imagem');
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
    
    // Listeners para imagens adicionais
    for (let i = 1; i <= 4; i++) {
        const input = document.getElementById('imagem' + i);
        if (input) {
            input.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    const file = e.target.files[0];
                    if (file.type.startsWith('image/')) {
                        const preview = document.getElementById('imagePreview' + i);
                        const img = document.getElementById('previewImg' + i);
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            img.src = e.target.result;
                            preview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    }
                }
            });
        }
    }

    function handleImageUpload(file) {
        if (!file.type.startsWith('image/')) {
            showAlert('error', 'Selecione uma imagem válida');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            showAlert('error', 'Imagem deve ter no máximo 5MB');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            imagePreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    document.getElementById('ecoProductForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!validateAllFields()) {
            showAlert('error', 'Preencha todos os campos obrigatórios');
            return;
        }

        const formData = new FormData(e.target);
        const loadingOverlay = document.getElementById('loadingOverlay');

        loadingOverlay.classList.add('show');

        try {
            const currentPath = window.location.pathname;
            const basePath = currentPath.substring(0, currentPath.indexOf('/pages/business/'));
            const apiUrl = basePath + '/pages/business/modules/produtos/actions/adicionar_produto.php';
            
            console.log('Enviando para:', apiUrl);

            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const text = await response.text();
            console.log('Resposta:', text);

            let result;
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('Erro parse JSON:', parseError);
                console.error('Texto recebido:', text);
                throw new Error('Resposta inválida do servidor');
            }

            loadingOverlay.classList.remove('show');

            if (result.success) {
                localStorage.removeItem(STORAGE_KEY);
                showAlert('success', result.message || 'Produto cadastrado!');
                setTimeout(() => {
                    if (typeof loadContent === 'function') {
                        loadContent('modules/produtos/produtos');
                    } else {
                        window.location.href = 'index.php?page=modules/produtos/produtos';
                    }
                }, 2000);
            } else {
                showAlert('error', result.message || 'Erro ao cadastrar');
            }
        } catch (error) {
            loadingOverlay.classList.remove('show');
            console.error('Erro:', error);
            showAlert('error', 'Erro: ' + error.message);
        }
    });

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

    loadSavedData();
    setupAutosave();
    setupRealtimeValidation();

    console.log('✅ Formulário carregado');
})();
</script>