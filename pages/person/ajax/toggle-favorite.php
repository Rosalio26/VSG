<?php
session_start();
require_once '../../../registration/includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faça login para adicionar aos favoritos']);
    exit;
}

$userId = $_SESSION['auth']['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produto inválido']);
    exit;
}

// Verificar se já está favoritado
$stmt = $mysqli->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $userId, $productId);
$stmt->execute();
$result = $stmt->get_result();
$exists = $result->fetch_assoc();
$stmt->close();

if ($exists) {
    // Remover dos favoritos
    $stmt = $mysqli->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $userId, $productId);
    
    if ($stmt->execute()) {
        // Limpar cache de estatísticas
        if (function_exists('clearUserStatsCache')) {
            clearUserStatsCache($userId);
        }
        
        echo json_encode([
            'success' => true,
            'favorited' => false,
            'message' => 'Removido dos favoritos'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover favorito']);
    }
    $stmt->close();
} else {
    // Adicionar aos favoritos
    $stmt = $mysqli->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $productId);
    
    if ($stmt->execute()) {
        // Limpar cache de estatísticas
        if (function_exists('clearUserStatsCache')) {
            clearUserStatsCache($userId);
        }
        
        echo json_encode([
            'success' => true,
            'favorited' => true,
            'message' => 'Adicionado aos favoritos'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar favorito']);
    }
    $stmt->close();
}
?>