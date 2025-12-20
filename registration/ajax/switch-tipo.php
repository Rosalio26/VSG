<?php
session_start();

require_once __DIR__ . '/../middleware/cadastro.middleware.php';

/* ================= MÉTODO ================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido');
}

/* ================= CSRF ================= */

$csrf = $_POST['csrf'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !$csrf ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    http_response_code(403);
    exit('CSRF inválido');
}

/* ================= REGRA DE NEGÓCIO ================= */

$tipo = $_POST['tipo'] ?? '';

if (!in_array($tipo, $_SESSION['tipos_permitidos'], true)) {
    http_response_code(403);
    exit('Troca não permitida');
}

/* ================= PERSISTÊNCIA ================= */

$_SESSION['tipo_atual'] = $tipo;

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'tipo' => $tipo
]);
