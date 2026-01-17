<?php
/**
 * EDITAR FUNCIONÁRIO - NOVA ESTRUTURA
 * Atualiza users (email pessoal, telefone, nome) + employees (dados profissionais)
 * Email corporativo NÃO pode ser alterado
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

$employeeId = (int)$input['id']; // ID na tabela employees
$empresaId = (int)$_SESSION['auth']['user_id'];
$nome = trim($input['nome'] ?? '');
$email = trim($input['email'] ?? ''); // Email PESSOAL
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
    'employee_id' => $employeeId,
    'empresa_id' => $empresaId,
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
    
    // Buscar dados atuais do funcionário (incluindo user_id da tabela users)
    logDebug('Buscando dados atuais');
    $stmt = $mysqli->prepare("
        SELECT e.id, e.email_company, e.user_id as employee_user_id,
               u.id as real_user_id, u.email as current_email
        FROM employees e
        LEFT JOIN users u ON u.email = e.email_company OR u.email_corporativo = e.email_company
        WHERE e.id = ?
        AND e.user_id = ?
        AND e.is_active = 1
    ");
    $stmt->bind_param('ii', $employeeId, $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logDebug('ERRO: Funcionário não encontrado');
        throw new Exception('Funcionário não encontrado');
    }
    
    $funcionario = $result->fetch_assoc();
    $userIdToUpdate = $funcionario['real_user_id'];
    $emailCorporativo = $funcionario['email_company'];
    
    logDebug('Funcionário encontrado', [
        'user_id' => $userIdToUpdate,
        'email_company' => $emailCorporativo
    ]);
    
    // Verificar se email pessoal mudou e se já existe em outro usuário
    if ($email !== $funcionario['current_email']) {
        logDebug('Email mudou, verificando duplicidade');
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $stmt->bind_param('si', $email, $userIdToUpdate);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            logDebug('ERRO: Email já cadastrado');
            throw new Exception('Este email já está cadastrado no sistema');
        }
    }
    
    // Iniciar transação
    $mysqli->begin_transaction();
    
    try {
        // 1. ATUALIZAR USUÁRIO (users)
        logDebug('Atualizando usuário');
        $stmt = $mysqli->prepare("
            UPDATE users SET
                nome = ?,
                email = ?,
                telefone = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param('sssi', $nome, $email, $telefone, $userIdToUpdate);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar usuário: ' . $stmt->error);
        }
        
        logDebug('Usuário atualizado');
        
        // 2. ATUALIZAR DADOS PROFISSIONAIS (employees)
        logDebug('Atualizando dados profissionais');
        $stmt = $mysqli->prepare("
            UPDATE employees SET
                nome = ?,
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
            'sssssdsssssii',
            $nome,
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
            $employeeId,
            $empresaId
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar dados profissionais: ' . $stmt->error);
        }
        
        logDebug('Dados profissionais atualizados');
        
        // Commit da transação
        $mysqli->commit();
        
        logDebug('Funcionário atualizado com sucesso');
        logDebug('=== FIM EDITAR FUNCIONÁRIO (SUCESSO) ===');
        
        echo json_encode([
            'success' => true,
            'message' => 'Funcionário atualizado com sucesso',
            'email_company' => $emailCorporativo
        ]);
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        $mysqli->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM EDITAR FUNCIONÁRIO (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}