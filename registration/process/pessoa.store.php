<?php
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/rate_limit.php';

header('Content-Type: application/json');

/* ================= BLOQUEIO DE ACESSO DIRETO ================= */
if (!isset($_SESSION['cadastro']['started'])) {
    echo json_encode(['errors' => ['flow' => 'Acesso direto não permitido.']]);
    exit;
}

/* ================= RATE LIMIT ================= */
rateLimit('pessoa_store', 5, 60); // máximo 5 tentativas por minuto

/* ================= MÉTODO POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['errors' => ['method' => 'Método inválido.']]);
    exit;
}

/* ================= CSRF ================= */
$csrf = $_POST['csrf'] ?? '';
if (!csrf_validate($csrf)) {
    echo json_encode(['errors' => ['csrf' => 'Token CSRF inválido.']]);
    exit;
}

/* ================= VARIÁVEIS ================= */
$nome     = trim($_POST['nome'] ?? '');
$apelido  = trim($_POST['apelido'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$telefone = trim($_POST['telefone'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

$errors = [];

/* ================= VALIDAÇÕES ================= */
if (!$nome || strlen($nome) < 2) $errors['nome'] = 'Nome deve ter ao menos 2 caracteres.';
if (!$apelido || strlen($apelido) < 2) $errors['apelido'] = 'Apelido deve ter ao menos 2 caracteres.';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email inválido.';
if (!$telefone || !preg_match('/^\+?\d{8,15}$/', $telefone)) $errors['telefone'] = 'Telefone inválido.';
if (!$password || strlen($password) < 8) $errors['password'] = 'Senha deve ter ao menos 8 caracteres.';
if ($password !== $confirm) $errors['password_confirm'] = 'Senhas não coincidem.';

/* ================= VERIFICAR EMAIL E TELEFONE DUPLICADOS ================= */
if (!isset($errors['email'])) {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['errors' => ['db' => 'Erro no banco de dados']]);
        exit;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $errors['email'] = 'Email já cadastrado.';
}

if (!isset($errors['telefone'])) {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE telefone = ?");
    if (!$stmt) {
        echo json_encode(['errors' => ['db' => 'Erro no banco de dados']]);
        exit;
    }
    $stmt->bind_param('s', $telefone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $errors['telefone'] = 'Telefone já cadastrado.';
}

/* ================= RETORNO DE ERROS ================= */
if (!empty($errors)) {
    echo json_encode(['errors' => $errors]);
    exit;
}

/* ================= HASH DA SENHA ================= */
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

/* ================= CRIAR NOVO USUÁRIO ================= */
$stmt = $mysqli->prepare("
    INSERT INTO users
    (type, nome, apelido, email, telefone, password_hash, registration_step)
    VALUES ('person', ?, ?, ?, ?, ?, 'form_completed')
");
if (!$stmt) {
    echo json_encode(['errors' => ['db' => 'Erro ao preparar inserção no banco']]);
    exit;
}
$stmt->bind_param('sssss', $nome, $apelido, $email, $telefone, $passwordHash);
if (!$stmt->execute()) {
    echo json_encode(['errors' => ['db' => 'Erro ao inserir usuário no banco']]);
    exit;
}

/* ================= SESSÃO PARA FLUXO ================= */
$_SESSION['user_id'] = $stmt->insert_id;

/* ================= SUCESSO ================= */
echo json_encode([
    'success' => true,
    'redirect' => '../register/gerar_uid.php'
]);
exit;
