<?php
require_once '../includes/device.php';

$isMobile = detectDevice();
$tipo = $_POST['tipo'] ?? 'pessoal';

if ($tipo === 'business' && $isMobile) {
    http_response_code(403);
    exit('Cadastro Business bloqueado em celular.');
}

// Salvar no banco...
echo "Cadastro realizado com sucesso.";
