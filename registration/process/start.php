<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../middleware/cadastro.middleware.php';

/* ================= MÉTODO ================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorRedirect('method', 405);
}

/* ================= CSRF ================= */

$csrf = $_POST['csrf'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    empty($csrf) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    errorRedirect('csrf');
}

/* ================= FLUXO VÁLIDO ================= */

/**
 * Marca que o usuário passou corretamente:
 * index → start.php → painel_cadastro.php
 */
$_SESSION['cadastro_iniciado'] = true;

/* ================= REDIRECIONA ================= */

header('Location: ../register/painel_cadastro.php');
exit;
