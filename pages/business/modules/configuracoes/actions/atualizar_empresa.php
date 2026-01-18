<?php
/**
 * ATUALIZAR DADOS DA EMPRESA
 * Atualiza informações básicas da empresa
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

$nome = isset($input['nome']) ? trim($input['nome']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$telefone = isset($input['telefone']) ? trim($input['telefone']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';

// Validações
if (empty($nome)) {
    echo json_encode(['success' => false, 'message' => 'Nome da empresa é obrigatório']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

// Verificar se email já está em uso por outro usuário
$stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->bind_param('si', $email, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email já está em uso']);
    exit;
}
$stmt->close();

// Iniciar transação
$mysqli->begin_transaction();

try {
    // Atualizar tabela users
    $stmt = $mysqli->prepare("
        UPDATE users 
        SET nome = ?, email = ?, telefone = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('sssi', $nome, $email, $telefone, $userId);
    $stmt->execute();
    $stmt->close();

    // Atualizar tabela businesses
    $stmt = $mysqli->prepare("
        UPDATE businesses 
        SET description = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->bind_param('si', $description, $userId);
    $stmt->execute();
    $stmt->close();

    // Atualizar sessão
    $_SESSION['auth']['nome'] = $nome;
    $_SESSION['auth']['email'] = $email;

    // Registrar log de auditoria
    $stmt = $mysqli->prepare("
        INSERT INTO audit_logs 
        (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (?, 'update', 'company', ?, ?, ?, ?)
    ");
    
    $action = 'update';
    $entityType = 'company';
    $details = json_encode([
        'fields' => ['nome', 'email', 'telefone', 'description']
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
        'message' => '✅ Dados atualizados com sucesso!'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar dados: ' . $e->getMessage()
    ]);
}