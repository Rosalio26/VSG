<?php
/**
 * ALTERAR SENHA DO GESTOR
 * Valida senha atual e atualiza para nova senha
 */

session_start();
header('Content-Type: application/json');

require_once '../../../../../registration/includes/db.php';

// Verificar autenticação do gestor
if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$senhaAtual = isset($input['senha_atual']) ? $input['senha_atual'] : '';
$senhaNova = isset($input['senha_nova']) ? $input['senha_nova'] : '';

// Validações
if (empty($senhaAtual) || empty($senhaNova)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
    exit;
}

if (strlen($senhaNova) < 8) {
    echo json_encode(['success' => false, 'message' => 'A nova senha deve ter no mínimo 8 caracteres']);
    exit;
}

// Buscar senha atual do banco
$stmt = $mysqli->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}

// Verificar senha atual
if (!password_verify($senhaAtual, $user['password'])) {
    echo json_encode(['success' => false, 'message' => '❌ Senha atual incorreta']);
    exit;
}

// Hash da nova senha
$senhaHash = password_hash($senhaNova, PASSWORD_DEFAULT);

// Iniciar transação
$mysqli->begin_transaction();

try {
    // Atualizar senha
    $stmt = $mysqli->prepare("
        UPDATE users 
        SET password = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('si', $senhaHash, $userId);
    $stmt->execute();
    $stmt->close();

    // Registrar log de segurança
    $stmt = $mysqli->prepare("
        INSERT INTO audit_logs 
        (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (?, 'change_password', 'user', ?, ?, ?, ?)
    ");
    
    $action = 'change_password';
    $entityType = 'user';
    $details = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'success' => true
    ]);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param('iisss', 
        $userId,
        $userId,
        $details,
        $ipAddress,
        $userAgent
    );
    $stmt->execute();
    $stmt->close();

    // Commit transação
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => '✅ Senha alterada com sucesso!'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao alterar senha: ' . $e->getMessage()
    ]);
}