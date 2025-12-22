<?php
require_once '../includes/db.php';

$nome     = trim($_POST['nome'] ?? '');
$apelido = trim($_POST['apelido'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$telefone = trim($_POST['telefone'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

if ($password !== $confirm) {
    die('Senhas não coincidem');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

/* === VERIFICA SE JÁ EXISTE === */
$stmt = $mysqli->prepare("
    SELECT id, registration_step 
    FROM users 
    WHERE email = ?
");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    $userId = $user['id'];
} else {
    /* === CRIA USUÁRIO (UMA ÚNICA VEZ) === */
    $stmt = $mysqli->prepare("
        INSERT INTO users
        (type, nome, apelido, email, telefone, password_hash)
        VALUES ('person', ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sssss',
        $nome,
        $apelido,
        $email,
        $telefone,
        $passwordHash
    );
    $stmt->execute();

    $userId = $stmt->insert_id;
}

/* === SESSÃO = APENAS UX === */
session_start();
$_SESSION['user_id'] = $userId;

header('Location: ../register/gerar_uid.php');
exit;
