<?php
session_start();
require_once '../../../registration/includes/db.php';
require_once '../includes/get_user_stats.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Verificar autenticação
if (!isset($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado'
    ]);
    exit;
}

// Verificar tipo de usuário
if ($_SESSION['auth']['type'] !== 'person') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Acesso negado'
    ]);
    exit;
}

try {
    $userId = (int)$_SESSION['auth']['user_id'];
    
    // Buscar estatísticas sem cache para dados em tempo real
    $stats = getUserStats($mysqli, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => time(),
        'formatted_time' => date('H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar estatísticas',
        'message' => $e->getMessage()
    ]);
}