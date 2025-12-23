<?php
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/bootstrap.php';

/* ================= BLOQUEIO DE ACESSO DIRETO ================= */
if (empty($_SESSION['user_id']) || empty($_SESSION['cadastro']['started'])) {
    errorRedirect('flow'); // evita acesso direto via URL
}

$userId = $_SESSION['user_id'];

/* ================= ATUALIZA STATUS E FLUXO DE CADASTRO ================= */
$stmt = $mysqli->prepare("
    UPDATE users
    SET status = 'pending',
        registration_step = 'completed'
    WHERE id = ?
");
$stmt->bind_param('i', $userId);
if (!$stmt->execute()) {
    errorRedirect('db'); // Erro ao atualizar no banco
}

/* ================= LIMPA SESSÃO ================= */
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

/* ================= REDIRECIONA PARA DASHBOARD ================= */
header('Location: ../../pages/person/dashboard_person.php');
exit;
