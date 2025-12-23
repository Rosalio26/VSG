<?php
require_once '../middleware/cadastro.middleware.php';

/* ================= BLOQUEIO EXTRA ================= */
if (!isset($_SESSION['cadastro']['started'])) {
    // Acesso direto não permitido
    header('Location: ../../index.php');
    exit;
}

/* ================= VARIÁVEIS ================= */
$tiposPermitidos = $_SESSION['tipos_permitidos'];
$tipoAtual = $_SESSION['tipo_atual'];
$isMobile = count($tiposPermitidos) === 1;
$csrf = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Painel de Cadastro</title>
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
    
    <!-- ================== SWITCH (DESKTOP APENAS) ================== -->
    <?php if (!$isMobile && count($tiposPermitidos) > 1): ?>
      <h2>Mudar de conta</h2>
    <div id="switchConta">
      <button type="button" data-tipo="business">Negócio</button>
      <button type="button" data-tipo="pessoal">Pessoa</button>
    </div>
    <?php endif; ?>

    <!-- ================== BUSINESS ================== -->
    <?php if (in_array('business', $tiposPermitidos, true)): ?>
    <form
      id="formBusiness"
      method="post"
      action="../process/cadastro.process.php"
      <?= $tipoAtual === 'business' ? '' : 'hidden' ?> >
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="tipo" value="business">
    
      <label>Nome do Negócio</label>
      <input type="text" name="empresa" required>
    
      <label>CNPJ</label>
      <input type="text" name="cnpj" required>
    
      <button type="submit">Cadastrar Negócio</button>
    </form>
    <?php endif; ?>
    
    <!-- ================== PESSOA ================== -->
    <?php if (in_array('pessoal', $tiposPermitidos, true)): ?>
    <form
      id="formPessoa"
      method="post"
      action="../process/pessoa.store.php"
      <?= $tipoAtual === 'pessoal' ? '' : 'hidden' ?>
    >
      <div class="person-field-input">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" autocomplete="off">
        <input type="hidden" name="tipo" value="pessoal">
      </div>
    
      <div class="person-field-input">
        <label>Nome</label>
        <input type="text" name="nome" required minlength="2" autocomplete="off">
      </div>
    
      <div class="person-field-input">
        <label>Apelido</label>
        <input type="text" name="apelido" required minlength="2" autocomplete="off">
      </div>
    
      <div class="person-field-input">
        <label>Email</label>
        <input type="email" name="email" required autocomplete="off">
      </div>
    
      <div class="person-field-input">
        <label>Telefone</label>
        <input type="tel" name="telefone" required autocomplete="off">
      </div>
    
      <div class="person-field-input">
        <label>Palavra-passe</label>
        <input type="password" name="password" required minlength="8" autocomplete="off">
      </div>
    
      <div class="person-field-input">
        <label>Confirmar palavra-passe</label>
        <input type="password" name="password_confirm" required minlength="8" autocomplete="off">
      </div>
    
      <button type="submit">Continuar</button>
    </form>
    <?php endif; ?>
    
  </div>
</div>

<script src="../../assets/scripts/painel_cadastro.js"></script>
</body>
</html>
