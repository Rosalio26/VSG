<?php
session_start();
require_once '../../../registration/includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['auth']['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$favoriteId = isset($data['favorite_id']) ? (int)$data['favorite_id'] : 0;
$productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;

if ($favoriteId > 0) {
    // Remover por ID do favorito
    $stmt = $mysqli->prepare("DELETE FROM favorites WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $favoriteId, $userId);
} elseif ($productId > 0) {
    // Remover por ID do produto
    $stmt = $mysqli->prepare("DELETE FROM favorites WHERE product_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $productId, $userId);
} else {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

if ($stmt->execute()) {
    clearUserStatsCache($userId); // Limpar cache
    echo json_encode(['success' => true, 'message' => 'Favorito removido']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao remover favorito']);
}

$stmt->close();
?>