<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../../registration/includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['auth']['user_id'])) {
    http_response_code(401);
    exit;
}

$userId = (int) $_SESSION['auth']['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'IDs não fornecidos']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids)) . 'i';
$params = array_merge($ids, [$userId]);

$stmt = $mysqli->prepare("UPDATE notifications SET status = 'read' WHERE id IN ($placeholders) AND receiver_id = ?");
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro']);
}
