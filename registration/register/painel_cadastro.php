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
$tiposPermitidos = $_SESSION['tipos_permitidos'] ?? ['pessoal'];
$tipoAtual       = $_SESSION['tipo_atual'] ?? 'pessoal';
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
        .input-error { border-color: red !important; background-color: #fff0f0; }
        .error-message { color: red; font-size: 12px; display: none; margin-top: 5px; }
        input[type="tel"] { width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #ddd; }
        /* Estilo para campo desabilitado/automático */
        input:disabled { background-color: #f9f9f9; cursor: not-allowed; color: #666; }
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
                <div class="section-title">Etapa 1: Identidade do Negócio</div>
                <div class="person-field-input">
                    <label>Nome da Empresa (Razão Social)</label>
                    <input type="text" name="nome_empresa" id="nome_empresa" required>
                    <span class="error-message">Este campo é obrigatório.</span>
                </div>
                <div class="person-field-input">
                    <label>Tipo de Empresa</label>
                    <select name="tipo_empresa" id="tipo_empresa" required style="width:100%; padding:12px; border-radius:6px; border:1px solid #ddd;">
                        <option value="selet">Selecione o tipo da sua empresa...</option>
                        <option value="ltda">LTDA / Limitada</option>
                        <option value="mei">MEI / Individual</option>
                        <option value="sa">S.A / Corporação</option>
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
                <div class="section-title">Etapa 2: Localização & Fiscal</div>
                <div class="person-field-input">
                    <label>País</label>
                    <select name="pais" id="select_pais" required style="width:100%; padding:12px; border-radius:6px; border:1px solid #ddd;">
                        <option value="">Carregando lista de países...</option>
                    </select>
                </div>
                <div class="person-field-input">
                    <label id="label_fiscal">Documento Fiscal</label>
                    <input type="text" name="tax_id" id="tax_id" placeholder="Digite o seu CNPJ / NUIT / NIF / etc" required>
                </div>
                <div class="person-field-input">
                    <label>Região (Estado/Província)</label>
                    <input type="text" name="regiao" id="regiao" required placeholder="Digite a sua provincia / Região">
                </div>
                <div class="person-field-input">
                    <label>Cidade (Localidade/Município)</label>
                    <input type="text" name="localidade" id="localidade" required placeholder="Digite a sua cidade ou localidade">
                </div>
                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(2, 1)">Voltar</button>
                    <button type="button" class="btn-next" onclick="changeStep(2, 3)">Próxima Etapa</button>
                </div>
            </div>

            <div class="step-content" data-step="3">
                <div class="section-title">Etapa 3: Contatos & Documentação</div>
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
                    <input type="file" name="licenca" accept=".pdf,image/*">
                </div>
                <div class="person-field-input">
                    <label>Logo da Empresa</label>
                    <input type="file" name="logo" accept="image/*">
                </div>
                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(3, 2)">Voltar</button>
                    <button type="button" class="btn-next" onclick="changeStep(3, 4)">Próxima Etapa</button>
                </div>
            </div>

            <div class="step-content" data-step="4">
                <div class="section-title">Etapa 4: Acesso e Segurança</div>
                <div class="person-field-input">
                    <label>Confirme seu E-mail (Automático)</label>
                    <input type="email" id="email_confirm_display" disabled>
                </div>
                <div class="person-field-input">
                    <label>Senha de Acesso</label>
                    <input type="password" name="password" id="pass_bus" required minlength="8" placeholder="Mínimo 8 caracteres">
                </div>
                <div class="person-field-input">
                    <label>Confirmar Senha</label>
                    <input type="password" name="password_confirm" id="pass_bus_conf" required minlength="8" placeholder="Repita a senha">
                </div>
                <div class="btn-navigation">
                    <button type="button" class="btn-prev" onclick="changeStep(4, 3)">Voltar</button>
                    <button type="submit" class="btn-next">Finalizar Cadastro</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if (in_array('pessoal', $tiposPermitidos, true)): ?>
        <form id="formPessoa" method="post" action="../process/pessoa.store.php" novalidate <?= $tipoAtual === 'pessoal' ? '' : 'hidden' ?>>
            <?= csrf_field(); ?>
            <input type="hidden" name="tipo" value="person"> 
            <div class="person-field-input">
                <label>Nome Completo</label>
                <input type="text" name="nome" required minlength="2">
            </div>
            <div class="person-field-input">
                <label>Como quer ser chamado (Apelido)</label>
                <input type="text" name="apelido" required minlength="2">
            </div>
            <div class="person-field-input">
                <label>E-mail</label>
                <input type="email" name="email" required>
            </div>
            <div class="person-field-input">
                <label>Telefone</label>
                <input type="tel" name="telefone" id="telefone_input" placeholder="Ex: +258 84 123 4567" required>
            </div>
            <div class="person-field-input">
                <label>Senha</label>
                <input type="password" name="password" required minlength="8">
            </div>
            <div class="person-field-input">
                <label>Confirmar Senha</label>
                <input type="password" name="password_confirm" required minlength="8">
            </div>
            <button type="submit" style="width:100%; padding:12px; background:#28a745; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Continuar para Verificação</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LÓGICA DO EMAIL AUTOMÁTICO (Sincroniza Etapa 3 com Etapa 4) ---
        const emailInput = document.getElementById('email_business');
        const displayInput = document.getElementById('email_confirm_display');

        if(emailInput && displayInput) {
            emailInput.addEventListener('input', function() {
                displayInput.value = this.value;
            });
        }

        // --- LÓGICA DE SLIDERS ---
        let slideInterval = null;
        let currentIndex = 0;
        const slidesPessoal = document.querySelectorAll('.slide-pessoal');
        const imgBusiness = document.getElementById('img-business-fixa');
        const buttons = document.querySelectorAll('.btn-toggle');

        function startSlide() {
            if(!slidesPessoal.length) return;
            stopSlide();
            if(imgBusiness) imgBusiness.classList.remove('active');
            slidesPessoal[currentIndex].classList.add('active');
            slideInterval = setInterval(() => {
                slidesPessoal[currentIndex].classList.remove('active');
                currentIndex = (currentIndex + 1) % slidesPessoal.length;
                slidesPessoal[currentIndex].classList.add('active');
            }, 5000); 
        }

        function stopSlide() {
            clearInterval(slideInterval);
            slideInterval = null;
            slidesPessoal.forEach(s => s.classList.remove('active'));
            if(imgBusiness) imgBusiness.classList.add('active');
        }

        const tipoInicial = document.body.getAttribute('data-tipo-inicial');
        if (tipoInicial === 'pessoal') startSlide(); else stopSlide();

        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const tipo = this.getAttribute('data-tipo');
                document.getElementById('titulo').innerText = tipo === 'business' ? 'Cadastro de Negócio' : 'Cadastro Pessoal';
                if (tipo === 'pessoal') startSlide(); else stopSlide();
            });
        });
    });
</script>

<script src="../../assets/scripts/painel_cadastro.js"></script>
<script src="../../assets/scripts/painel_cadastro_erros.js"></script>

</body>
</html>