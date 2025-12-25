<?php
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
session_start();

/* ================= BLOQUEIO DE ACESSO DIRETO ================= */
if (empty($_SESSION['user_id'])) {
    die('Sessão inválida');
}

$userId = $_SESSION['user_id'];

/* ================= VERIFICA EMAIL E UID ================= */
$stmt = $mysqli->prepare("
    SELECT public_id, email_verified_at
    FROM users
    WHERE id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user['public_id']) {
    die('UID ainda não gerado.');
}

if (!$user['email_verified_at']) {
    die('Email ainda não confirmado.');
}

/* ================= ATUALIZA STATUS ================= */
$stmt = $mysqli->prepare("
    UPDATE users
    SET status = 'active', registration_step = 'completed'
    WHERE id = ?
");
$stmt->bind_param('i', $userId);
if (!$stmt->execute()) {
    die('Erro ao finalizar cadastro. Tente novamente.');
}

/* ================= LIMPA SESSÃO ================= */
$_SESSION = [];
session_destroy();

/* ================= REDIRECIONA ================= */
header('Location: ../../pages/person/dashboard_person.php');
exit;
