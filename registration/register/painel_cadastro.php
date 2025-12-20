<?php
require_once '../middleware/cadastro.middleware.php';

$tiposPermitidos = $_SESSION['tipos_permitidos'];
$tipoAtual = $_SESSION['tipo_atual'];
$isMobile = count($tiposPermitidos) === 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Painel de Cadastro</title>
    <link rel="stylesheet" href="assets/style/painel.css">
</head>

<body
  data-is-mobile="<?= $isMobile ? '1' : '0' ?>"
  data-tipo-inicial="<?= htmlspecialchars($tipoAtual) ?>"
  data-csrf="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
>

<h1 id="titulo">
  <?= $tipoAtual === 'business'
      ? 'Cadastro de Negócio'
      : 'Cadastro de Pessoa'
  ?>
</h1>

<!-- ================== BUSINESS ================== -->
<?php if (in_array('business', $tiposPermitidos)): ?>
<form
  id="formBusiness"
  method="post"
  <?= $tipoAtual === 'business' ? '' : 'hidden' ?>
>
  <input type="hidden" name="tipo" value="business">

  <label>Nome do Negócio</label>
  <input type="text" name="empresa" required>

  <label>CNPJ</label>
  <input type="text" name="cnpj" required>

  <button type="submit">Cadastrar Negócio</button>
</form>
<?php endif; ?>

<!-- ================== PESSOA ================== -->
<?php if (in_array('pessoal', $tiposPermitidos)): ?>
<form
  id="formPessoa"
  method="post"
  <?= $tipoAtual === 'pessoal' ? '' : 'hidden' ?>
>
  <input type="hidden" name="tipo" value="pessoal">

  <label>Nome</label>
  <input type="text" name="nome" required>

  <label>Email</label>
  <input type="email" name="email" required>

  <button type="submit">Cadastrar Pessoa</button>
</form>
<?php endif; ?>

<!-- ================== SWITCH (SÓ DESKTOP) ================== -->
<?php if (count($tiposPermitidos) > 1): ?>
<div id="switchConta">
  <button type="button" data-tipo="business">Negócio</button>
  <button type="button" data-tipo="pessoal">Pessoa</button>
</div>
<?php endif; ?>

<script src="../../assets/scripts/painel_cadastro.js"></script>
</body>
</html>
