<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$codigo = trim($_POST['codigo'] ?? '');

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado.']);
    exit;
}

$stmt = $mysqli->prepare("SELECT email_token, email_token_expires FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['email_token'] !== $codigo) {
    echo json_encode(['success' => false, 'error' => 'Código incorreto.']);
    exit;
}

if (strtotime($user['email_token_expires']) < time()) {
    echo json_encode(['success' => false, 'error' => 'Código expirado.']);
    exit;
}

// Sucesso: Confirmar no banco
$now = date('Y-m-d H:i:s');
$update = $mysqli->prepare("UPDATE users SET email_verified_at = ?, email_token = NULL, registration_step = 'email_verified' WHERE id = ?");
$update->bind_param('si', $now, $userId);
$update->execute();

echo json_encode(['success' => true]);