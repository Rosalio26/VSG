<?php
/**
 * Keep Alive - Mantém sessão ativa
 * Arquivo: registration/ajax/keep_alive.php
 */
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

// Atualiza timestamp de última atividade
$_SESSION['ultima_atividade'] = time();

echo json_encode([
    'success' => true,
    'timestamp' => time(),
    'message' => 'Sessão renovada'
]);
?>