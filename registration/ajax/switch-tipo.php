<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../middleware/cadastro.middleware.php';

/* ================= MÉTODO ================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

/* ================= CSRF ================= */

$csrf = $_POST['csrf'] ?? '';

if (!csrf_validate($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf']);
    exit;
}

/* ================= REGRA DE NEGÓCIO ================= */

$tipo = $_POST['tipo'] ?? '';

if (!in_array($tipo, $_SESSION['tipos_permitidos'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'device']);
    exit;
}

/* ================= PERSISTÊNCIA ================= */

$_SESSION['tipo_atual'] = $tipo;

/* ================= RESPOSTA ================= */

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'tipo'   => $tipo
]);
exit;
