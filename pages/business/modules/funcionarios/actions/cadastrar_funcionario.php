<?php
/**
 * CADASTRAR FUNCIONÁRIO
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
    
    file_put_contents($logDir . 'cadastrar_funcionario.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO CADASTRAR FUNCIONÁRIO ===');

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
    'user_id' => $userId,
    'nome' => $nome,
    'email' => $email,
    'cargo' => $cargo
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
    
    // Verificar email duplicado
    logDebug('Verificando email duplicado');
    $stmt = $mysqli->prepare("
        SELECT id
        FROM employees
        WHERE user_id = ?
        AND email = ?
        AND is_active = 1
    ");
    $stmt->bind_param('is', $userId, $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        logDebug('ERRO: Email já cadastrado');
        throw new Exception('Email já cadastrado para esta empresa');
    }
    
    // Inserir funcionário
    logDebug('Inserindo funcionário');
    $stmt = $mysqli->prepare("
        INSERT INTO employees (
            user_id, nome, email, telefone, cargo, departamento,
            data_admissao, salario, status, tipo_documento, documento,
            endereco, observacoes, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->bind_param(
        'issssssdsssss',
        $userId,
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
        $observacoes
    );
    
    if (!$stmt->execute()) {
        logDebug('ERRO ao executar', ['error' => $stmt->error]);
        throw new Exception('Erro ao cadastrar funcionário');
    }
    
    $funcionarioId = $mysqli->insert_id;
    
    logDebug('Funcionário cadastrado', ['id' => $funcionarioId]);
    logDebug('=== FIM CADASTRAR FUNCIONÁRIO (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'message' => 'Funcionário cadastrado com sucesso',
        'id' => $funcionarioId
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM CADASTRAR FUNCIONÁRIO (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}