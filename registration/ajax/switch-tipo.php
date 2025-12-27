<?php
/**
 * Arquivo: switch-tipo.php
 * Finalidade: Alternar o tipo de cadastro (Pessoa/Negócio) via AJAX
 */

// 1. O Bootstrap deve ser o primeiro para configurar os cookies de sessão corretamente
require_once __DIR__ . '/../bootstrap.php'; 

// 2. Security contém as funções de validação de CSRF
require_once __DIR__ . '/../includes/security.php';

// Define o cabeçalho para JSON
header('Content-Type: application/json');

/* ================= MÉTODO ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'error'   => 'Método de requisição inválido.'
    ]);
    exit;
}

/* ================= CSRF ================= */
// Valida o token vindo do AJAX contra o token na $_SESSION['csrf_token']
if (!csrf_validate($_POST['csrf'] ?? '')) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Sessão expirada ou token inválido. Recarregue a página.'
    ]);
    exit;
}

/* ================= VALIDAÇÃO DE FLUXO ================= */
if (empty($_SESSION['tipos_permitidos']) || !is_array($_SESSION['tipos_permitidos'])) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Fluxo de cadastro não iniciado corretamente.'
    ]);
    exit;
}

/* ================= REGRA DE NEGÓCIO ================= */
$tipo = $_POST['tipo'] ?? '';

// Verifica se o tipo solicitado está entre os permitidos pelo middleware de dispositivo
if (!in_array($tipo, $_SESSION['tipos_permitidos'], true)) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Este tipo de cadastro não é permitido no seu dispositivo atual.'
    ]);
    exit;
}

/* ================= PERSISTÊNCIA ================= */
// Salva a escolha na sessão para manter a aba correta em caso de refresh
$_SESSION['tipo_atual'] = $tipo;

/* ================= RESPOSTA ================= */
echo json_encode([
    'success' => true,
    'tipo'    => $tipo
]);
exit;