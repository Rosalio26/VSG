<?php
require_once '../includes/security.php'; 
require_once '../middleware/cadastro.middleware.php';

if (!isset($_SESSION['cadastro']['started'])) {
    header('Location: ../../index.php');
    exit;
}

$tiposPermitidos = $_SESSION['tipos_permitidos'] ?? ['pessoal'];

// Detecção inteligente de tipo
if (isset($_GET['tipo']) && in_array($_GET['tipo'], $tiposPermitidos, true)) {
    $_SESSION['tipo_atual'] = $_GET['tipo'];
}
elseif (!isset($_SESSION['tipo_atual'])) {
    $_SESSION['tipo_atual'] = in_array('business', $tiposPermitidos) ? 'business' : 'pessoal';
}

$tipoAtual = $_SESSION['tipo_atual'] ?? 'pessoal';
$isMobile = count($tiposPermitidos) === 1;
$csrf = csrf_generate(); 
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro VisionGreen - Sistema Inteligente</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/style/painel_cadastro_ultimate.css">

    <style>
        /* ===== ESTILOS PERSONALIZADOS ===== */
        .fiscal-mode-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .fiscal-mode-selector label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 13px;
            color: #475569;
        }
        
        form[hidden] {
            display: none !important;
        }

        .btn-location {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-location:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #070129;
            margin: 20px 0 16px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #00b96b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: #00b96b;
            border-radius: 2px;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .chi-main {
            animation: fadeInScale 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ===== ALERTA DE TELA PEQUENA ===== */
        .screen-warning {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 20px;
            text-align: center;
            color: white;
        }

        .screen-warning.active {
            display: flex;
        }

        .screen-warning i {
            font-size: 64px;
            color: #f59e0b;
            margin-bottom: 20px;
        }

        .screen-warning h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .screen-warning p {
            font-size: 16px;
            color: #d1d5db;
            max-width: 500px;
        }
    </style>
</head>

<body id="painel_cadastro" 
      data-is-mobile="<?= $isMobile ? '1' : '0' ?>" 
      data-tipo-inicial="<?= htmlspecialchars($tipoAtual) ?>" 
      data-csrf="<?= htmlspecialchars($csrf) ?>"
      data-tipos-permitidos="<?= htmlspecialchars(json_encode($tiposPermitidos)) ?>">

<!-- ===== ALERTA DE TELA PEQUENA (Para Business) ===== -->
<div class="screen-warning" id="screenWarning">
    <i class="fas fa-desktop"></i>
    <h2>Tela Muito Pequena</h2>
    <p>
        Cadastros empresariais requerem uma tela com <strong>largura mínima de 1080px</strong>.<br>
        Por favor, utilize um computador desktop ou aumente o zoom para continuar.
    </p>
</div>

<!-- Container de Imagens (Fixas) -->
<div class="cnt-image">
    <div class="cnt-img-item <?= $tipoAtual === 'pessoal' ? 'active' : '' ?>" id="img-person-fixa">
        <img src="../../assets/img/local/2301.png" alt="Cadastro Pessoal"> 
    </div>
    <div class="cnt-img-item <?= $tipoAtual === 'business' ? 'active' : '' ?>" id="img-business-fixa">
        <img src="../../assets/img/vsg-bg/cole-frp/business_panel_rmb.png" alt="Cadastro Business"> 
    </div>
</div>

<!-- Container Principal -->
<div class="main-container">
    <div class="chi-main">
        <button class="btn-back">
            <a href="../../index.php">
                <i class="fa-solid fa-house"></i>
                Inicio
            </a>
        </button>

        <h1 id="titulo"><?= $tipoAtual === 'business' ? 'Cadastro de Negócio' : 'Cadastro Pessoal' ?></h1>
    
        <?php if (!$isMobile && count($tiposPermitidos) > 1): ?>
            <div id="switchConta">
                <button type="button" class="btn-toggle <?= $tipoAtual === 'business' ? 'active' : '' ?>" data-tipo="business">
                    <i class="fa-solid fa-building"></i> Negócio / Fornecedor
                </button>
                <button type="button" class="btn-toggle <?= $tipoAtual === 'pessoal' ? 'active' : '' ?>" data-tipo="pessoal">
                    <i class="fa-solid fa-user"></i> Pessoa
                </button>
            </div>
        <?php endif; ?>

        <!-- ==================== FORMULÁRIO BUSINESS ==================== -->
        <?php if (in_array('business', $tiposPermitidos, true)): ?>
        <form id="formBusiness" method="post" action="../process/business.store.php" enctype="multipart/form-data" novalidate <?= $tipoAtual === 'business' ? '' : 'hidden' ?>>
            <?= csrf_field(); ?>
            <input type="hidden" name="tipo" value="company">

            <!-- STEP 1: Identidade -->
            <div class="step-content active" data-step="1">
                <div class="section-title">
                    <i class="fa-solid fa-building"></i>
                    Etapa 1/4: Identidade do Negócio
                </div>
                
                <div class="person-field-input">
                    <label>Nome da Empresa / Negócio  <span class="required">*</span></label>
                    <input type="text" name="nome_empresa" id="nome_empresa" required placeholder="Digite o nome da sua empresa">
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <div class="person-field-input">
                    <label>Tipo de Empresa <span class="required">*</span></label>
                    <select name="tipo_empresa" id="tipo_empresa" required>
                        <option value="">Selecione o tipo...</option>
                        <option value="ltda">LTDA / Limitada</option>
                        <option value="mei">MEI / Individual</option>
                        <option value="sa">S.A / Corporação</option>
                        <option value="eireli">EIRELI</option>
                    </select>
                </div>

                <div class="person-field-input">
                    <label>Descrição Curta</label>
                    <textarea name="descricao" id="descricao" rows="3" placeholder="Descreva brevemente sua empresa e atividades..."></textarea>
                </div>

                <div class="btn-navigation">
                    <button type="button" class="btn-next" onclick="changeStep(1, 2)">
                        Próxima Etapa <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Localização & Fiscal -->
            <div class="step-content" data-step="2">
                <div class="section-title">
                    <i class="fa-solid fa-map-location-dot"></i>
                    Etapa 2/4: Localização & Fiscal
                </div>
                
                <div class="person-field-input">
                    <label>País <span class="required">*</span></label>
                    <select name="pais" id="select_pais" required>
                        <option value="">Carregando países...</option>
                    </select>
                </div>

                <div class="person-field-input">
                    <label id="label_state">Província/Estado <span class="required">*</span></label>
                    <input type="text" name="state" id="state_input" required placeholder="Digite a província ou estado">
                </div>

                <div class="person-field-input">
                    <label>Cidade/Distrito <span class="required">*</span></label>
                    <input type="text" name="city" id="city_input" required placeholder="Digite a cidade ou distrito">
                </div>

                <div class="person-field-input">
                    <label>Endereço Completo</label>
                    <input type="text" name="address" id="address_input" placeholder="Rua, número, bairro...">
                    <button type="button" class="btn-location" onclick="detectarLocalizacaoManual()">
                        <i class="fa-solid fa-location-crosshairs"></i>
                        Usar Localização Atual
                    </button>
                </div>

                <!-- Os checkboxes exclusivos serão inseridos aqui via JavaScript -->

                <div class="person-field-input">
                    <label>Código Postal / CEP</label>
                    <input type="text" name="postal_code" id="postal_code_input" placeholder="Digite o código postal">
                </div>

                <input type="hidden" name="latitude" id="latitude_input">
                <input type="hidden" name="longitude" id="longitude_input">
                <input type="hidden" name="country_code" id="country_code_input">

                <div class="person-field-input">
                    <label id="label_fiscal">Documento Fiscal <span class="required">*</span></label>
                    
                    <div class="fiscal-mode-selector">
                        <label>
                            <input type="radio" name="fiscal_mode" value="text" checked onclick="toggleFiscalMode('text')">
                            <span><i class="fa-solid fa-keyboard"></i> Digitar Código</span>
                        </label>
                        <label>
                            <input type="radio" name="fiscal_mode" value="file" onclick="toggleFiscalMode('file')">
                            <span><i class="fa-solid fa-file-arrow-up"></i> Fazer Upload</span>
                        </label>
                    </div>

                    <input type="text" name="tax_id" id="tax_id" placeholder="Digite o CNPJ / NUIT / NIF" required>

                    <label class="custom-file-upload" id="area_tax_file" style="display:none;">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>Clique para anexar o Comprovante</span>
                        <input type="file" name="tax_id_file" id="tax_id_file" accept=".pdf,image/*" onchange="updateFileName(this)">
                        <div class="file-selected-name"></div>
                    </label>

                    <small style="color: #64748b; display: block; margin-top: 8px;">
                        <i class="fa-solid fa-info-circle"></i> Se fizer upload, nosso Admin validará manualmente.
                    </small>
                </div>

                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(2, 1)">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </button>
                    <button type="button" class="btn-next" onclick="changeStep(2, 3)">
                        Próxima Etapa <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Contatos & Documentação -->
            <div class="step-content" data-step="3">
                <div class="section-title">
                    <i class="fa-solid fa-file-contract"></i>
                    Etapa 3/4: Contatos & Documentação
                </div>
                
                <div class="person-field-input">
                    <label>E-mail Corporativo <span class="required">*</span></label>
                    <input type="email" name="email_business" id="email_business" placeholder="exemplo@empresa.com" required>
                </div>

                <div class="person-field-input">
                    <label>Telefone <span class="required">*</span></label>
                    <input type="tel" name="telefone" id="tel_business" placeholder="Ex: +258 84 123 4567" required>
                </div>
                
                <div class="person-field-input">
                    <label>Alvará / Licença (PDF/JPG) <span class="required">*</span></label>
                    <label class="custom-file-upload">
                        <i class="fa-solid fa-file-pdf"></i>
                        <span>Selecionar Alvará ou Licença</span>
                        <input type="file" name="licenca" accept=".pdf,image/*" required onchange="updateFileName(this)">
                        <div class="file-selected-name"></div>
                    </label>
                    <small style="color: #64748b; display: block; margin-top: 8px;">
                        <i class="fa-solid fa-info-circle"></i> Documento obrigatório para verificação.
                    </small>
                </div>

                <div class="person-field-input">
                    <label>Logo da Empresa</label>
                    <label class="custom-file-upload" id="logo_container">
                        <i class="fa-solid fa-image"></i>
                        <span>Selecionar Logo da Empresa</span>
                        <input type="file" name="logo" id="input_logo" accept="image/*" required onchange="updateFileName(this)">
                        <div class="file-selected-name"></div>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="no_logo" onchange="toggleLogoRequired(this)"> 
                        <span>Não tenho logo no momento</span>
                    </label>
                </div>

                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(3, 2)">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </button>
                    <button type="button" class="btn-next" onclick="changeStep(3, 4)">
                        Próxima Etapa <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 4: Segurança -->
            <div class="step-content" data-step="4">
                <div class="section-title">
                    <i class="fa-solid fa-shield-halved"></i>
                    Etapa 4/4: Acesso e Segurança
                </div>
                
                <div class="person-field-input">
                    <label>Confirme seu E-mail</label>
                    <input type="email" id="email_confirm_display" disabled>
                </div>

                <div class="person-field-input">
                    <label>Senha de Acesso <span class="required">*</span></label>
                    <input type="password" name="password" id="pass_bus" required minlength="8" placeholder="Mínimo 8 caracteres" oninput="checkStrengthBus(this.value)">
                    <div class="strength-meter">
                        <div id="strengthBarBus" class="strength-bar"></div>
                    </div>
                    <small id="strengthTextBus" class="strength-text">Força da senha</small>
                </div>

                <div class="person-field-input">
                    <label>Confirmar Senha <span class="required">*</span></label>
                    <input type="password" name="password_confirm" id="pass_bus_conf" required minlength="8" placeholder="Repita a senha">
                </div>

                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(4, 3)">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </button>
                    <button type="submit" id="btnFinalizarBus" class="btn-submit">
                        <i class="fa-solid fa-check-circle"></i> Finalizar Cadastro
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- ==================== FORMULÁRIO PESSOA ==================== -->
        <?php if (in_array('pessoal', $tiposPermitidos, true)): ?>
        <form id="formPessoa" method="post" action="../process/pessoa.store.php" novalidate <?= $tipoAtual === 'pessoal' ? '' : 'hidden' ?>>
            <?= csrf_field(); ?>
            <input type="hidden" name="tipo" value="person"> 

            <div class="section-title">
                <i class="fa-solid fa-user"></i>
                Dados Pessoais
            </div>
            
            <div class="person-field-input">
                <label>Nome Completo <span class="required">*</span></label>
                <input type="text" name="nome" id="nome_pessoa" required minlength="2" placeholder="Digite seu nome completo">
            </div>

            <div class="person-field-input">
                <label>Como quer ser chamado (Apelido) <span class="required">*</span></label>
                <input type="text" name="apelido" id="apelido_pessoa" required minlength="2" placeholder="Ex: João, Maria">
            </div>

            <div class="section-title">
                <i class="fa-solid fa-map-location-dot"></i>
                Localização
            </div>

            <div class="person-field-input">
                <label>País <span class="required">*</span></label>
                <select name="country" id="select_pais_pessoa" required>
                    <option value="">Carregando países...</option>
                </select>
            </div>

            <div class="person-field-input">
                <label id="label_state_pessoa">Província/Estado <span class="required">*</span></label>
                <input type="text" name="state" id="state_input_pessoa" required placeholder="Digite a província ou estado">
            </div>

            <div class="person-field-input">
                <label>Cidade/Distrito <span class="required">*</span></label>
                <input type="text" name="city" id="city_input_pessoa" required placeholder="Digite a cidade ou distrito">
            </div>

            <div class="person-field-input">
                <label>Endereço Completo</label>
                <input type="text" name="address" id="address_input_pessoa" placeholder="Rua, número, bairro...">
                <button type="button" class="btn-location" onclick="detectarLocalizacaoManualPessoa()">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    Usar Minha Localização
                </button>
            </div>

            <!-- Checkbox será inserido aqui via JavaScript -->

            <div class="person-field-input">
                <label>Código Postal / CEP</label>
                <input type="text" name="postal_code" id="postal_code_input_pessoa" placeholder="Digite o código postal">
            </div>

            <input type="hidden" name="latitude" id="latitude_input_pessoa">
            <input type="hidden" name="longitude" id="longitude_input_pessoa">
            <input type="hidden" name="country_code" id="country_code_input_pessoa">

            <div class="section-title">
                <i class="fa-solid fa-envelope"></i>
                Contato
            </div>

            <div class="person-field-input">
                <label>E-mail <span class="required">*</span></label>
                <input type="email" name="email" id="email_pessoa" required placeholder="exemplo@email.com">
            </div>

            <div class="person-field-input">
                <label>Telefone <span class="required">*</span></label>
                <input type="tel" name="telefone" id="telefone_input" placeholder="Ex: +258 84 123 4567" required>
            </div>

            <div class="section-title">
                <i class="fa-solid fa-shield-halved"></i>
                Segurança
            </div>

            <div class="person-field-input">
                <label>Senha <span class="required">*</span></label>
                <input type="password" name="password" id="password_pessoa" required minlength="8" placeholder="Mínimo 8 caracteres" oninput="checkStrengthPessoa(this.value)">
                <div class="strength-meter">
                    <div id="strengthBarPessoa" class="strength-bar"></div>
                </div>
                <small id="strengthTextPessoa" class="strength-text">Força da senha</small>
            </div>

            <div class="person-field-input">
                <label>Confirmar Senha <span class="required">*</span></label>
                <input type="password" name="password_confirm" id="password_confirm_pessoa" required minlength="8" placeholder="Repita a senha">
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-user-check"></i> Continuar para Verificação
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ===== SCRIPT UNIFICADO COMPLETO ===== -->
<script src="../../assets/scripts/painel_cadastro_ultimate.js"></script>
<script src="../../assets/scripts/redirect_handler.js"></script>

<script>
// Integração com sistema existente
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tipoURL = urlParams.get('tipo');
        
        if (tipoURL && (tipoURL === 'business' || tipoURL === 'pessoal')) {
            console.log(`🎯 Tipo detectado: ${tipoURL}`);
            
            // Força renderização correta
            if (typeof renderizar === 'function') {
                renderizar();
            }
            
            // Atualiza botões
            document.querySelectorAll('.btn-toggle').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tipo === tipoURL);
            });
            
            // Mostra formulário correto
            const formBusiness = document.getElementById('formBusiness');
            const formPessoa = document.getElementById('formPessoa');
            
            if (tipoURL === 'business') {
                if (formBusiness) formBusiness.hidden = false;
                if (formPessoa) formPessoa.hidden = true;
            } else {
                if (formBusiness) formBusiness.hidden = true;
                if (formPessoa) formPessoa.hidden = false;
            }
        }
    }, 100);
});
</script>

<script>
// ===== VALIDAÇÃO DE LARGURA DE TELA PARA BUSINESS =====
(function() {
    const MIN_WIDTH_BUSINESS = 1080;
    const warningScreen = document.getElementById('screenWarning');
    const formBusiness = document.getElementById('formBusiness');
    const toggleButtons = document.querySelectorAll('.btn-toggle');
    
    function checkScreenSize() {
        const currentWidth = window.innerWidth;
        const tiposPermitidos = <?= json_encode($tiposPermitidos) ?>;
        const tipoAtual = document.body.getAttribute('data-tipo-inicial');
        
        // Se o tipo atual é business e a tela é pequena
        if (tipoAtual === 'business' && currentWidth < MIN_WIDTH_BUSINESS) {
            if (tiposPermitidos.includes('business')) {
                warningScreen.classList.add('active');
                if (formBusiness) formBusiness.style.display = 'none';
            }
        } else {
            warningScreen.classList.remove('active');
            if (formBusiness && tipoAtual === 'business') {
                formBusiness.style.display = 'block';
            }
        }
    }
    
    // Verifica ao carregar
    checkScreenSize();
    
    // Verifica ao redimensionar
    window.addEventListener('resize', checkScreenSize);
    
    // Verifica ao trocar de tipo
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            setTimeout(checkScreenSize, 100);
        });
    });
    
    // Bloqueia submit se tela pequena
    if (formBusiness) {
        formBusiness.addEventListener('submit', function(e) {
            if (window.innerWidth < MIN_WIDTH_BUSINESS) {
                e.preventDefault();
                alert('Sua tela é muito pequena para cadastro empresarial. Largura mínima: 1080px');
                return false;
            }
        });
    }
})();
</script>

</body>
</html>