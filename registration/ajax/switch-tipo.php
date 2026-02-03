<?php
/**
 * Arquivo: switch-tipo.php
 * Finalidade: Alternar o tipo de cadastro (Pessoa/Negócio) via AJAX
 */

require_once __DIR__ . '/../bootstrap.php'; 
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'error'   => 'Método de requisição inválido.'
    ]);
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? '')) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Sessão expirada ou token inválido. Recarregue a página.'
    ]);
    exit;
}

if (empty($_SESSION['tipos_permitidos']) || !is_array($_SESSION['tipos_permitidos'])) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Fluxo de cadastro não iniciado corretamente.'
    ]);
    exit;
}

$tipo = $_POST['tipo'] ?? '';

// Normaliza "pessoal" para compatibilidade
if ($tipo === 'pessoal') {
    $tipo = 'pessoal';
} elseif ($tipo === 'business') {
    $tipo = 'business';
}

if (!in_array($tipo, $_SESSION['tipos_permitidos'], true)) {
    echo json_encode([
        'success' => false, 
        'error'   => 'Este tipo de cadastro não é permitido no seu dispositivo atual.'
    ]);
    exit;
}

$_SESSION['tipo_atual'] = $tipo;

echo json_encode([
    'success' => true,
    'tipo'    => $tipo
]);
exit;