<?php
// O arquivo security.php (chamado dentro do middleware ou aqui) garante a sessão e o token
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css">

    <link rel="stylesheet" href="../../assets/style/painel_cadastro.css">
    <link rel="stylesheet" href="../../assets/style/geral.css">
</head>

<body
  id="painel_cadastro"
  data-is-mobile="<?= $isMobile ? '1' : '0' ?>"
  data-tipo-inicial="<?= htmlspecialchars($tipoAtual) ?>"
  data-csrf="<?= htmlspecialchars($csrf) ?>"
>
<div class="main-container">
    <div class="chi-main">
        <h1 id="titulo">
            <?= $tipoAtual === 'business' ? 'Cadastro de Negócio' : 'Cadastro Pessoal' ?>
        </h1>
    
        <?php if (!$isMobile && count($tiposPermitidos) > 1): ?>
            <h2>Mudar tipo de conta</h2>
            <div id="switchConta">
                <button type="button" class="<?= $tipoAtual === 'business' ? 'active' : '' ?>" data-tipo="business">Negócio</button>
                <button type="button" class="<?= $tipoAtual === 'pessoal' ? 'active' : '' ?>" data-tipo="pessoal">Pessoa</button>
            </div>
        <?php endif; ?>

        <?php if (in_array('business', $tiposPermitidos, true)): ?>
        <form
            id="formBusiness"
            method="post"
            action="../process/cadastro.process.php"
            novalidate
            <?= $tipoAtual === 'business' ? '' : 'hidden' ?> >
            
            <?= csrf_field(); ?>
            <input type="hidden" name="tipo" value="business">
        
            <label>Nome do Negócio</label>
            <input type="text" name="empresa" placeholder="Razão Social ou Nome Fantasia" required>
        
            <label>CNPJ</label>
            <input type="text" name="cnpj" placeholder="00.000.000/0000-00" required>
        
            <button type="submit">Cadastrar Negócio</button>
        </form>
        <?php endif; ?>
        
        <?php if (in_array('pessoal', $tiposPermitidos, true)): ?>
        <form
            id="formPessoa"
            method="post"
            action="../process/pessoa.store.php"
            novalidate
            <?= $tipoAtual === 'pessoal' ? '' : 'hidden' ?>
        >
            <?= csrf_field(); ?>
            <input type="hidden" name="tipo" value="person"> 
            
            <div class="person-field-input">
                <label>Nome Completo</label>
                <input type="text" name="nome" required minlength="2" autocomplete="name">
            </div>
        
            <div class="person-field-input">
                <label>Como quer ser chamado (Apelido)</label>
                <input type="text" name="apelido" required minlength="2">
            </div>
        
            <div class="person-field-input">
                <label>E-mail</label>
                <input type="email" name="email" required autocomplete="email">
            </div>
        
            <div class="person-field-input">
                <label>Telefone</label>
                <input type="tel" id="telefone_input" placeholder="Digite o codigo + número" required>
                <input type="hidden" name="telefone" id="telefone_final">
            </div>
        
            <div class="person-field-input">
                <label>Senha</label>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
        
            <div class="person-field-input">
                <label>Confirmar Senha</label>
                <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
            </div>
        
            <button type="submit">Continuar para Verificação</button>
        </form>
        <?php endif; ?>
        
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
<script src="../../assets/scripts/painel_cadastro.js"></script>
<script src="../../assets/scripts/phone_handler.js"></script>
<script src="../../assets/scripts/painel_cadastro_erros.js"></script>
</body>
</html>