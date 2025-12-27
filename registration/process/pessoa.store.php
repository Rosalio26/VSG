<?php
session_start();

/**
 * Arquivo: pessoa.store.php
 * Sincronizado com a tabela 'users' (password_hash e ENUMs corretos)
 */

ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/rate_limit.php';
require_once '../includes/mailer.php'; 

header('Content-Type: application/json');

try {
    /* ================= 1. SEGURANÇA ================= */
    if (!isset($_SESSION['cadastro']['started'])) {
        echo json_encode(['error' => 'Sessão expirada. Recarregue a página.']);
        exit;
    }

    rateLimit('pessoa_store', 5, 60);

    if (!csrf_validate($_POST['csrf'] ?? '')) {
        echo json_encode(['error' => 'Token inválido.']);
        exit;
    }

    /* ================= 2. COLETA E VALIDAÇÃO ================= */
    $nome     = trim($_POST['nome'] ?? '');
    $apelido  = trim($_POST['apelido'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $telefone = trim($_POST['telefone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $errors = [];
    if (mb_strlen($nome) < 3) $errors['nome'] = 'Nome muito curto.';
    if (mb_strlen($apelido) < 2) $errors['apelido'] = 'Apelido muito curto.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail inválido.';
    if (strlen($password) < 8) $errors['password'] = 'A senha deve ter 8+ caracteres.';
    if ($password !== $confirm) $errors['password_confirm'] = 'As senhas não coincidem.';

    if ($errors) {
        echo json_encode(['errors' => $errors]);
        exit;
    }

    /* ================= 3. VERIFICAÇÃO DE DUPLICIDADE ================= */
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? OR telefone = ? LIMIT 1");
    $stmt->bind_param('ss', $email, $telefone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['errors' => ['email' => 'E-mail ou telefone já cadastrados.']]);
        exit;
    }
    $stmt->close();

    /* ================= 4. DADOS PARA O BANCO (NOMES EXATOS) ================= */
    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $token    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires  = date('Y-m-d H:i:s', time() + 3600);

    // Valores baseados nos seus ENUMs
    $type   = 'person';
    $status = 'pending';
    $step   = 'email_pending'; // Ajustado para o seu ENUM

    /* ================= 5. INSERÇÃO (PASSWORD_HASH) ================= */
    $sql = "INSERT INTO users (
                type, nome, apelido, email, telefone, password_hash, 
                status, registration_step, email_token, email_token_expires
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    
    // "ssssssssss" = 10 strings
    $stmt->bind_param(
        "ssssssssss", 
        $type, $nome, $apelido, $email, $telefone, $passHash, 
        $status, $step, $token, $expires
    );

    $stmt->execute();
    $userId = $stmt->insert_id;
    $stmt->close();

    /* ================= 6. FINALIZAÇÃO ================= */
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['cadastro']); 

    // Envio do e-mail
    enviarEmailVisionGreen($email, $nome, $token);

    echo json_encode([
        'success' => true,
        'redirect' => '../process/verify_email.php?info=codigo_enviado'
    ]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(['error' => 'Erro no Banco: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro Crítico: ' . $e->getMessage()]);
}