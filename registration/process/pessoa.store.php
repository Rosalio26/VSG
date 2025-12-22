<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../middleware/cadastro.middleware.php';

/* ========= MÉTODO ========= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorRedirect('method', 405);
}

/* ========= CSRF ========= */
if (!csrf_validate($_POST['csrf'] ?? null)) {
    errorRedirect('csrf');
}

/* ========= TIPO ========= */
if (($_POST['tipo'] ?? '') !== 'pessoal') {
    errorRedirect('device');
}

/* ========= DADOS ========= */
$nome      = trim($_POST['nome'] ?? '');
$apelido   = trim($_POST['apelido'] ?? '');
$email     = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$telefone  = trim($_POST['telefone'] ?? '');
$pass      = $_POST['password'] ?? '';
$confirm   = $_POST['password_confirm'] ?? '';

/* ========= VALIDAÇÕES ========= */
if (
    strlen($nome) < 2 ||
    strlen($apelido) < 2 ||
    !$email ||
    strlen($telefone) < 6 ||
    strlen($pass) < 8 ||
    $pass !== $confirm
) {
    errorRedirect('form');
}

/* ========= GUARDA EM SESSÃO ========= */
$_SESSION['cadastro_pessoa'] = [
    'nome'     => $nome,
    'apelido'  => $apelido,
    'email'    => $email,
    'telefone' => $telefone,
    'password' => password_hash($pass, PASSWORD_DEFAULT),
];

$_SESSION['registration_step'] = 'form_completed';

/* ========= AVANÇA ========= */
header('Location: ../register/gerar_uid.php');
exit;
