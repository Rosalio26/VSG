<?php
// O arquivo security.php garante a sessão e o token
require_once '../includes/security.php'; 
require_once '../middleware/cadastro.middleware.php';

/* ================= BLOQUEIO EXTRA ================= */
if (!isset($_SESSION['cadastro']['started'])) {
    header('Location: ../../index.php');
    exit;
}

/* ================= VARIÁVEIS ================= */
// Priorizamos 'business' no array de tipos permitidos
$tiposPermitidos = $_SESSION['tipos_permitidos'] ?? ['business', 'pessoal'];

// NOVA LÓGICA: Se não houver tipo definido na sessão (primeiro acesso), o padrão agora é 'business'
if (!isset($_SESSION['tipo_atual'])) {
    $_SESSION['tipo_atual'] = 'business'; 
}

$tipoAtual       = $_SESSION['tipo_atual'] ?? 'business';
$isMobile        = count($tiposPermitidos) === 1;

// Usamos a função do security.php para garantir que o token existe
$csrf = csrf_generate(); 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Cadastro - VisionGreen</title>

    <link rel="stylesheet" href="../../assets/style/painel_cadastro.css">
    <link rel="stylesheet" href="../../assets/style/geral.css">
    <link rel="stylesheet" href="../../assets/style/business_style.css">

    <style>
        /* Estilos para o seletor de modo fiscal */
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
        
        /* Garante que formulários marcados com hidden não ocupem espaço nem 'pisquem' no reload */
        form[hidden] {
            display: none !important;
        }
    </style>
</head>

<body
  id="painel_cadastro"
  data-is-mobile="<?= $isMobile ? '1' : '0' ?>"
  data-tipo-inicial="<?= htmlspecialchars($tipoAtual) ?>"
  data-csrf="<?= htmlspecialchars($csrf) ?>"
>
<div class="cnt-image">
    <div class="cnt-img-item slide-pessoal active">
        <img src="../../assets/img/local/2301.png" alt="Slide 1"> 
    </div>
    <div class="cnt-img-item slide-pessoal">
        <img src="../../assets/img/people/2001.png" alt="Slide 2"> 
    </div>
    <div class="cnt-img-item slide-pessoal">
        <img src="../../assets/img/supermarket/18715.png" alt="Slide 3"> 
    </div>
    <div class="cnt-img-item" id="img-business-fixa">
        <img src="../../assets/img/food/1250.png" alt="Negócio Fixo"> 
    </div>
</div>

<div class="main-container">
    <div class="chi-main">
        <h1 id="titulo"><?= $tipoAtual === 'business' ? 'Cadastro de Negócio' : 'Cadastro Pessoal' ?></h1>
    
        <?php if (!$isMobile && count($tiposPermitidos) > 1): ?>
            <div id="switchConta">
                <button type="button" class="btn-toggle <?= $tipoAtual === 'business' ? 'active' : '' ?>" data-tipo="business">Negócio</button>
                <button type="button" class="btn-toggle <?= $tipoAtual === 'pessoal' ? 'active' : '' ?>" data-tipo="pessoal">Pessoa</button>
            </div>
        <?php endif; ?>

        <?php if (in_array('business', $tiposPermitidos, true)): ?>
        <form id="formBusiness" method="post" action="../process/business.store.php" enctype="multipart/form-data" novalidate <?= $tipoAtual === 'business' ? '' : 'hidden' ?> >
            <?= csrf_field(); ?>
            <input type="hidden" name="tipo" value="company">

            <div class="step-content active" data-step="1">
                <div class="section-title">Etapa 1 - 4: Identidade do Negócio</div>
                <div class="person-field-input">
                    <label>Nome da Empresa / Negócio</label>
                    <input type="text" name="nome_empresa" id="nome_empresa" required>
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>
                <div class="person-field-input">
                    <label>Tipo de Empresa</label>
                    <select name="tipo_empresa" id="tipo_empresa" required style="width:100%; padding:12px; border-radius:6px; border:1px solid #ddd;">
                        <option class="option-inp" value="selet">Selecione o tipo da sua empresa...</option>
                        <option class="option-inp" value="ltda">LTDA / Limitada</option>
                        <option class="option-inp" value="mei">MEI / Individual</option>
                        <option class="option-inp" value="sa">S.A / Corporação</option>
                    </select>
                </div>
                <div class="person-field-input">
                    <label>Descrição Curta</label>
                    <textarea name="descricao" id="descricao" placeholder="Descreva brevemente sua empresa..."></textarea>
                </div>
                <div class="btn-navigation">
                    <button type="button" class="btn-next" onclick="changeStep(1, 2)">Próxima Etapa</button>
                </div>
            </div>

            <div class="step-content" data-step="2">
                <div class="section-title">Etapa 2 - 4: Localização & Fiscal</div>
                
                <!-- País -->
                <div class="person-field-input">
                    <label>País <span style="color: red;">*</span></label>
                    <select name="pais" id="select_pais" required style="width:100%; padding:12px; border-radius:6px; border:1px solid #ddd;">
                        <option value="">Selecione o país...</option>
                    </select>
                    <span class="error-message">Selecione um país.</span>
                </div>

                <!-- Província/Estado -->
                <div class="person-field-input">
                    <label id="label_state">Província/Estado <span style="color: red;">*</span></label>
                    <input type="text" name="state" id="state_input" required placeholder="Digite a província ou estado">
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <!-- Cidade -->
                <div class="person-field-input">
                    <label>Cidade <span style="color: red;">*</span></label>
                    <input type="text" name="city" id="city_input" required placeholder="Digite a cidade">
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <!-- Endereço Completo -->
                <div class="person-field-input">
                    <label>Endereço Completo</label>
                    <input type="text" name="address" id="address_input" placeholder="Rua, número, bairro...">
                    <button type="button" class="btn-location" onclick="openLocationModalForRegistration()">
                        <i class="fa-solid fa-location-dot"></i>
                        Usar Localização Atual
                    </button>
                </div>

                <!-- Código Postal (Opcional) -->
                <div class="person-field-input">
                    <label>Código Postal / CEP</label>
                    <input type="text" name="postal_code" id="postal_code_input" placeholder="Digite o código postal">
                </div>

                <!-- Campos Hidden para Coordenadas -->
                <input type="hidden" name="latitude" id="latitude_input">
                <input type="hidden" name="longitude" id="longitude_input">
                <input type="hidden" name="country_code" id="country_code_input">

                <!-- Documento Fiscal -->
                <div class="person-field-input">
                    <label id="label_fiscal">Documento Fiscal <span style="color: red;">*</span></label>
                    
                    <div class="fiscal-mode-selector">
                        <label>
                            <input type="radio" name="fiscal_mode" value="text" checked onclick="toggleFiscalMode('text')">
                            <span>Digitar Código</span>
                        </label>
                        <label>
                            <input type="radio" name="fiscal_mode" value="file" onclick="toggleFiscalMode('file')">
                            <span>Fazer Upload</span>
                        </label>
                    </div>

                    <input type="text" name="tax_id" id="tax_id" placeholder="Digite o CNPJ / NUIT / NIF" required>

                    <label class="custom-file-upload" id="area_tax_file" style="display:none;">
                        <i>📁</i>
                        <span>Clique para anexar o Comprovante</span>
                        <input type="file" name="tax_id_file" id="tax_id_file" accept=".pdf,image/*" onchange="updateFileName(this)">
                        <div class="file-selected-name"></div>
                    </label>

                    <small style="color: #070129ff; display: block; margin-top: 5px;">
                        Se fizer upload, nosso Admin validará manualmente.
                    </small>
                </div>

                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(2, 1)">Voltar</button>
                    <button type="button" class="btn-next" onclick="changeStep(2, 3)">Próxima Etapa</button>
                </div>
            </div>

            <div class="step-content" data-step="3">
                <div class="section-title">Etapa 3 - 4: Contatos & Documentação</div>
                <div class="person-field-input">
                    <label>E-mail Corporativo</label>
                    <input type="email" name="email_business" id="email_business" placeholder="exemplo@email.com" required>
                </div>
                <div class="person-field-input">
                    <label>Telefone</label>
                    <input type="tel" name="telefone" id="tel_business" placeholder="Ex: +00 0000 00000" required>
                </div>
                
                <div class="person-field-input">
                    <label>Alvará / Licença (PDF/JPG)</label>
                    <label class="custom-file-upload">
                        <i>📄</i>
                        <span>Selecionar Alvará ou Licença</span>
                        <input type="file" name="licenca" accept=".pdf,image/*" required onchange="updateFileName(this)">
                        <div class="file-selected-name"></div>
                    </label>
                    <small style="color: #070129ff; display: block; margin-top: 5px;">Documento obrigatório para verificação da empresa.</small>
                </div>

                <div class="person-field-input">
                    <label>Logo da Empresa</label>
                    <label class="custom-file-upload" id="logo_container">
                        <i>🖼️</i>
                        <span>Selecionar Logo da Empresa</span>
                        <input type="file" name="logo" id="input_logo" accept="image/*" required onchange="updateFileName(this)">
                        <div class="file-selected-name"></div>
                    </label>
                    <label style="font-size: 12px; margin-top:10px; display:flex; align-items:center; gap:5px; cursor: pointer;">
                        <input type="checkbox" name="no_logo" onchange="toggleLogoRequired(this)"> 
                        <span>Não tenho logo no momento</span>
                    </label>
                </div>

                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(3, 2)">Voltar</button>
                    <button type="button" class="btn-next" onclick="changeStep(3, 4)">Próxima Etapa</button>
                </div>
            </div>

            <div class="step-content" data-step="4">
                <div class="section-title">Etapa 4 - 4: Acesso e Segurança</div>
                <div class="person-field-input">
                    <label>Confirme seu E-mail (Automático)</label>
                    <input type="email" id="email_confirm_display" disabled>
                </div>
                <div class="person-field-input">
                    <label>Senha de Acesso</label>
                    <input type="password" name="password" id="pass_bus" required minlength="8" placeholder="Mínimo 8 caracteres" oninput="checkStrengthBus(this.value)">
                    <div class="strength-meter">
                        <div id="strengthBarBus" class="strength-bar"></div>
                    </div>
                    <small id="strengthTextBus" style="font-size: 11px; color: #666;">Força da senha</small>
                </div>
                <div class="person-field-input">
                    <label>Confirmar Senha</label>
                    <input type="password" name="password_confirm" id="pass_bus_conf" required minlength="8" placeholder="Repita a senha">
                </div>
                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(4, 3)">Voltar</button>
                    <button type="submit" id="btnFinalizarBus" class="btn-next">Finalizar Cadastro</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if (in_array('pessoal', $tiposPermitidos, true)): ?>
            <form id="formPessoa" method="post" action="../process/pessoa.store.php" novalidate <?= $tipoAtual === 'pessoal' ? '' : 'hidden' ?>>
                <?= csrf_field(); ?>
                <input type="hidden" name="tipo" value="person"> 

                <!-- Dados Pessoais -->
                <div class="section-title">Dados Pessoais</div>
                
                <div class="person-field-input">
                    <label>Nome Completo <span style="color: red;">*</span></label>
                    <input type="text" name="nome" id="nome_pessoa" required minlength="2" placeholder="Digite seu nome completo">
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <div class="person-field-input">
                    <label>Como quer ser chamado (Apelido) <span style="color: red;">*</span></label>
                    <input type="text" name="apelido" id="apelido_pessoa" required minlength="2" placeholder="Ex: João, Maria">
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <!-- Localização -->
                <div class="section-title" style="margin-top: 30px;">Localização</div>

                <div class="person-field-input">
                    <label>País <span style="color: red;">*</span></label>
                    <select name="country" id="select_pais_pessoa" required style="width:100%; padding:12px; border-radius:6px; border:1px solid #ddd;">
                        <option value="">Selecione o país...</option>
                    </select>
                    <span class="error-message">Selecione um país.</span>
                </div>

                <div class="person-field-input">
                    <label id="label_state_pessoa">Província/Estado <span style="color: red;">*</span></label>
                    <input type="text" name="state" id="state_input_pessoa" required placeholder="Digite a província ou estado">
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <div class="person-field-input">
                    <label>Cidade <span style="color: red;">*</span></label>
                    <input type="text" name="city" id="city_input_pessoa" required placeholder="Digite a cidade">
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <div class="person-field-input">
                    <label>Endereço Completo</label>
                    <input type="text" name="address" id="address_input_pessoa" placeholder="Rua, número, bairro...">
                    <button type="button" class="btn-location" onclick="openLocationModalForPerson()">
                        <i class="fa-solid fa-location-dot"></i>
                        Usar Minha Localização
                    </button>
                </div>

                <div class="person-field-input">
                    <label>Código Postal / CEP</label>
                    <input type="text" name="postal_code" id="postal_code_input_pessoa" placeholder="Digite o código postal">
                </div>

                <!-- Campos Hidden para Coordenadas -->
                <input type="hidden" name="latitude" id="latitude_input_pessoa">
                <input type="hidden" name="longitude" id="longitude_input_pessoa">
                <input type="hidden" name="country_code" id="country_code_input_pessoa">

                <!-- Contato -->
                <div class="section-title" style="margin-top: 30px;">Contato</div>

                <div class="person-field-input">
                    <label>E-mail <span style="color: red;">*</span></label>
                    <input type="email" name="email" id="email_pessoa" required placeholder="exemplo@email.com">
                    <span class="error-message">Digite um e-mail válido.</span>
                </div>

                <div class="person-field-input">
                    <label>Telefone <span style="color: red;">*</span></label>
                    <input type="tel" name="telefone" id="telefone_input" placeholder="Ex: +258 84 123 4567" required>
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>

                <!-- Segurança -->
                <div class="section-title" style="margin-top: 30px;">Segurança</div>

                <div class="person-field-input">
                    <label>Senha <span style="color: red;">*</span></label>
                    <input type="password" name="password" id="password_pessoa" required minlength="8" placeholder="Mínimo 8 caracteres" oninput="checkStrengthPessoa(this.value)">
                    <div class="strength-meter">
                        <div id="strengthBarPessoa" class="strength-bar"></div>
                    </div>
                    <small id="strengthTextPessoa" style="font-size: 11px; color: #666;">Força da senha</small>
                    <span class="error-message">A senha deve ter no mínimo 8 caracteres.</span>
                </div>

                <div class="person-field-input">
                    <label>Confirmar Senha <span style="color: red;">*</span></label>
                    <input type="password" name="password_confirm" id="password_confirm_pessoa" required minlength="8" placeholder="Repita a senha">
                    <span class="error-message">As senhas não coincidem.</span>
                </div>

                <button type="submit" style="width:100%; padding:14px; background:#28a745; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer; font-size: 15px; margin-top: 20px; transition: all 0.3s;">
                    <i class="fa-solid fa-user-check"></i> Continuar para Verificação
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="../../assets/scripts/painel_cadastro.js"></script>
<script src="../../assets/scripts/painel_cadastro_erros.js"></script>

</body>
</html>