<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/rate_limit.php';

/* ================= RATE LIMIT ================= */
rateLimit('start_form', 5, 60); // max 5 tentativas por minuto

/* ================= MÉTODO ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorRedirect('method');
}

/* ================= ORIGEM ================= */
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$expectedHost = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($referer, $expectedHost) === false) {
    errorRedirect('flow');
}

/* ================= CSRF ================= */
$csrf = $_POST['csrf'] ?? null;
if (!csrf_validate($csrf)) {
    errorRedirect('csrf');
}

/* ================= BLOQUEIO DE ACESSO DIRETO ================= */
if (isset($_SESSION['cadastro']['started'])) {
    // Já iniciou cadastro, redireciona para painel
    header('Location: ../register/painel_cadastro.php');
    exit;
}

/* ================= INÍCIO DO FLUXO ================= */
$_SESSION['cadastro'] = [
    'started' => true,
    'at' => time(),
];

/* ================= LIMPA OUTPUT E REDIRECIONA ================= */
if (ob_get_length()) ob_clean();
header('Location: ../register/painel_cadastro.php');
exit;
