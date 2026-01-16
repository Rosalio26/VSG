<?php
/**
 * EDITAR FUNCIONÁRIO
 */

header('Content-Type: application/json');

function logDebug($message, $data = null) {
    $logDir = __DIR__ . '/../debug/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $log .= "\n";
    
    file_put_contents($logDir . 'editar_funcionario.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO EDITAR FUNCIONÁRIO ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

$funcionarioId = (int)$input['id'];
// IMPORTANTE: Usar user_id da SESSÃO, não do input
$userId = (int)$_SESSION['auth']['user_id'];
$nome = trim($input['nome'] ?? '');
$email = trim($input['email'] ?? '');
$telefone = trim($input['telefone'] ?? '');
$cargo = trim($input['cargo'] ?? '');
$departamento = trim($input['departamento'] ?? '');
$data_admissao = $input['data_admissao'] ?? '';
$salario = !empty($input['salario']) ? floatval($input['salario']) : null;
$status = $input['status'] ?? 'ativo';
$tipo_documento = $input['tipo_documento'] ?? 'bi';
$documento = trim($input['documento'] ?? '');
$endereco = trim($input['endereco'] ?? '');
$observacoes = trim($input['observacoes'] ?? '');

logDebug('Dados recebidos', [
    'id' => $funcionarioId,
    'user_id' => $userId,
    'nome' => $nome,
    'email' => $email
]);

try {
    // Validações
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    if (empty($email)) {
        throw new Exception('Email é obrigatório');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    if (empty($telefone)) {
        throw new Exception('Telefone é obrigatório');
    }
    
    if (empty($cargo)) {
        throw new Exception('Cargo é obrigatório');
    }
    
    if (empty($data_admissao)) {
        throw new Exception('Data de admissão é obrigatória');
    }
    
    logDebug('Validações OK');
    
    // Verificar se funcionário pertence à empresa
    logDebug('Verificando proprietário');
    $stmt = $mysqli->prepare("
        SELECT id
        FROM employees
        WHERE id = ?
        AND user_id = ?
        AND is_active = 1
    ");
    $stmt->bind_param('ii', $funcionarioId, $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        logDebug('ERRO: Funcionário não encontrado');
        throw new Exception('Funcionário não encontrado');
    }
    
    // Verificar email duplicado (exceto o próprio)
    logDebug('Verificando email duplicado');
    $stmt = $mysqli->prepare("
        SELECT id
        FROM employees
        WHERE user_id = ?
        AND email = ?
        AND id != ?
        AND is_active = 1
    ");
    $stmt->bind_param('isi', $userId, $email, $funcionarioId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        logDebug('ERRO: Email já cadastrado');
        throw new Exception('Email já cadastrado para outro funcionário');
    }
    
    // Atualizar funcionário
    logDebug('Atualizando funcionário');
    $stmt = $mysqli->prepare("
        UPDATE employees SET
            nome = ?,
            email = ?,
            telefone = ?,
            cargo = ?,
            departamento = ?,
            data_admissao = ?,
            salario = ?,
            status = ?,
            tipo_documento = ?,
            documento = ?,
            endereco = ?,
            observacoes = ?,
            updated_at = NOW()
        WHERE id = ?
        AND user_id = ?
    ");
    
    $stmt->bind_param(
        'ssssssdsssssii',
        $nome,
        $email,
        $telefone,
        $cargo,
        $departamento,
        $data_admissao,
        $salario,
        $status,
        $tipo_documento,
        $documento,
        $endereco,
        $observacoes,
        $funcionarioId,
        $userId
    );
    
    if (!$stmt->execute()) {
        logDebug('ERRO ao executar', ['error' => $stmt->error]);
        throw new Exception('Erro ao atualizar funcionário');
    }
    
    logDebug('Funcionário atualizado com sucesso');
    logDebug('=== FIM EDITAR FUNCIONÁRIO (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'message' => 'Funcionário atualizado com sucesso'
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM EDITAR FUNCIONÁRIO (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}