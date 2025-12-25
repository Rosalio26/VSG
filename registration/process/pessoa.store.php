<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/rate_limit.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

/* ================= BLOQUEIO ================= */
if (!isset($_SESSION['cadastro']['started'])) {
    echo json_encode(['errors' => ['flow' => 'Acesso direto não permitido.']]);
    exit;
}

/* ================= RATE LIMIT ================= */
rateLimit('pessoa_store', 5, 60);

/* ================= MÉTODO ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['errors' => ['method' => 'Método inválido.']]);
    exit;
}

/* ================= CSRF ================= */
if (!csrf_validate($_POST['csrf'] ?? '')) {
    echo json_encode(['errors' => ['csrf' => 'Token CSRF inválido.']]);
    exit;
}

/* ================= INPUT ================= */
$nome     = trim($_POST['nome'] ?? '');
$apelido  = trim($_POST['apelido'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$telefone = trim($_POST['telefone'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

$errors = [];

/* ================= VALIDAÇÕES ================= */
if (mb_strlen($nome) < 2) $errors['nome'] = 'Nome inválido.';
if (mb_strlen($apelido) < 2) $errors['apelido'] = 'Apelido inválido.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email inválido.';
if (!preg_match('/^\+?\d{8,15}$/', $telefone)) $errors['telefone'] = 'Telefone inválido.';
if (strlen($password) < 8) $errors['password'] = 'Senha fraca.';
if ($password !== $confirm) $errors['password_confirm'] = 'Senhas não coincidem.';

/* ================= DUPLICIDADE ================= */
$stmt = $mysqli->prepare("
    SELECT email, telefone
    FROM users
    WHERE email = ? OR telefone = ?
");
$stmt->bind_param('ss', $email, $telefone);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['email'] === $email) {
        $errors['email'] = 'Este email já está cadastrado.';
    }
    if ($row['telefone'] === $telefone) {
        $errors['telefone'] = 'Este telefone já está cadastrado.';
    }
}

$stmt->close();

if ($errors) {
    echo json_encode(['errors' => $errors]);
    exit;
}

/* ================= CRIA USUÁRIO ================= */
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 3600);

$stmt = $mysqli->prepare("
    INSERT INTO users (
        type, nome, apelido, email, telefone, password_hash,
        status, registration_step, email_token, email_token_expires
    ) VALUES (
        'person', ?, ?, ?, ?, ?,
        'pending', 'email_pending', ?, ?
    )
");

$stmt->bind_param(
    'sssssss',
    $nome, $apelido, $email, $telefone,
    $passwordHash, $verification_code, $expires
);

$stmt->execute();
$userId = $stmt->insert_id;
$stmt->close();

$_SESSION['user_id'] = $userId;

/* ================= RESPONDE IMEDIATAMENTE ================= */
echo json_encode([
    'success' => true,
    'redirect' => '../process/verify_email.php'
]);

/* ================= FINALIZA RESPOSTA HTTP ================= */
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

/* ================= ENVIO DE EMAIL (ASSÍNCRONO) ================= */
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'eanixr@gmail.com'; // NÃO ALTERADO
    $mail->Password = 'zwirfytkoskulbfx'; // NÃO ALTERADO
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('eanixr@gmail.com', 'VisionGreen');
    $mail->addAddress($email, $nome);

    $mail->isHTML(true);
    $mail->Subject = 'Código de verificação';
    $mail->Body = "
        <p>Olá <b>$nome</b>,</p>
        <p>Seu código de verificação é:</p>
        <h2 style='letter-spacing:3px;'>$verification_code</h2>
        <p>Este código expira em 1 hora.</p>
    ";

    $mail->send();
} catch (Exception $e) {
    // Erro silencioso (não afeta UX)
}
