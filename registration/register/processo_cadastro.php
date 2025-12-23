<?php
require_once '../includes/device.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/bootstrap.php';

/* ================= BLOQUEIO DE ACESSO DIRETO ================= */
if (!isset($_SESSION['cadastro']['started'])) {
    header('Location: ../../index.php');
    exit;
}

/* ================= MÉTODO POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorRedirect('method');
}

/* ================= CSRF ================= */
$csrf = $_POST['csrf'] ?? null;
if (!csrf_validate($csrf)) {
    errorRedirect('csrf');
}

/* ================= DETECÇÃO DE DISPOSITIVO ================= */
$device = detectDevice();
$isMobile = in_array($device['os'], ['android', 'ios'], true);

/* ================= RECEBER TIPO ================= */
$tipo = $_POST['tipo'] ?? 'pessoal';

/* ================= BLOQUEIO BUSINESS EM MOBILE ================= */
if ($tipo === 'business' && $isMobile) {
    http_response_code(403);
    exit('Cadastro Business bloqueado em celular.');
}

/* ================= SALVAR NO BANCO ================= */
// Aqui você adicionaria a lógica de cadastro do negócio
// Ex: inserir empresa e cnpj no banco

echo "Cadastro realizado com sucesso para tipo: " . htmlspecialchars($tipo);
