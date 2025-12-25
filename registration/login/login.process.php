<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

/* ================= CSRF ================= */
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    $_SESSION['login_error'] = 'Sessão expirada. Tente novamente.';
    header("Location: login.php");
    exit;
}

/* ================= INPUT ================= */
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    $_SESSION['login_error'] = 'Preencha todos os campos.';
    header("Location: login.php");
    exit;
}

/* ================= BUSCAR USUÁRIO ================= */
$stmt = $mysqli->prepare("
    SELECT 
        id, nome, email, password_hash,
        email_verified_at, email_token, email_token_expires,
        public_id, status
    FROM users
    WHERE email = ?
");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Email ou senha incorretos.';
    header("Location: login.php");
    exit;
}

/* ================= EMAIL NÃO CONFIRMADO ================= */
if (!$user['email_verified_at']) {

    // Gera novo código
    $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $upd = $mysqli->prepare("
        UPDATE users SET email_token = ?, email_token_expires = ? WHERE id = ?
    ");
    $upd->bind_param('ssi', $token, $expires, $user['id']);
    $upd->execute();

    // Enviar email
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'eanixr@gmail.com';
        $mail->Password = 'zwirfytkoskulbfx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('eanixr@gmail.com', 'VisionGreen');
        $mail->addAddress($user['email'], $user['nome']);
        $mail->isHTML(true);
        $mail->Subject = 'Confirmação de Email';
        $mail->Body = "
            <p>Olá {$user['nome']},</p>
            <p>Seu novo código de confirmação é:</p>
            <b style='font-size:20px;'>$token</b>
            <p>Validade: 1 hora.</p>
        ";

        $mail->send();
    } catch (Exception $e) {
        $_SESSION['login_error'] = 'Erro ao reenviar email.';
        header("Location: login.php");
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    header("Location: ../process/verify_email.php");
    exit;
}

/* ================= UID NÃO GERADO ================= */
if (!$user['public_id']) {
    $_SESSION['user_id'] = $user['id'];
    header("Location: ../process/gerar_uid.php");
    exit;
}

/* ================= STATUS ================= */
if ($user['status'] !== 'active' && $user['status'] !== 'pending') {
    $_SESSION['login_error'] = 'Conta bloqueada.';
    header("Location: login.php");
    exit;
}

/* ================= LOGIN OK ================= */
session_regenerate_id(true);

$_SESSION['auth'] = [
    'user_id'   => $user['id'],
    'email'     => $user['email'],
    'public_id' => $user['public_id'],
];

header("Location: ../../pages/person/dashboard_person.php");
exit;
