<?php
/**
 * CONCEDER ACESSO AO SISTEMA PARA FUNCIONÁRIO
 * NOVA ESTRATÉGIA: Usar user_employee_id como FK para users
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
    
    file_put_contents($logDir . 'conceder_acesso.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO CONCEDER ACESSO ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';
require_once __DIR__ . '/../../../../../registration/includes/mailer.php';

$input = json_decode(file_get_contents('php://input'), true);

$funcionarioId = (int)$input['employee_id'];
$userId = (int)$_SESSION['auth']['user_id'];
$permissoes = $input['permissions'] ?? ['mensagens'];

logDebug('Dados recebidos', [
    'employee_id' => $funcionarioId,
    'user_id' => $userId,
    'permissions' => $permissoes
]);

try {
    // Buscar dados do funcionário
    logDebug('Buscando dados do funcionário');
    $stmt = $mysqli->prepare("
        SELECT 
            e.id, 
            e.nome, 
            e.email_company, 
            e.pode_acessar_sistema, 
            e.cargo,
            e.user_employee_id,
            emp.nome as empresa_nome
        FROM employees e
        INNER JOIN users emp ON e.user_id = emp.id
        WHERE e.id = ?
        AND e.user_id = ?
        AND e.is_active = 1
    ");
    $stmt->bind_param('ii', $funcionarioId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logDebug('ERRO: Funcionário não encontrado');
        throw new Exception('Funcionário não encontrado');
    }
    
    $funcionario = $result->fetch_assoc();
    
    logDebug('Funcionário encontrado', [
        'nome' => $funcionario['nome'],
        'email_company' => $funcionario['email_company'],
        'user_employee_id' => $funcionario['user_employee_id'],
        'empresa' => $funcionario['empresa_nome']
    ]);
    
    // Verificar se tem user_employee_id
    if (!$funcionario['user_employee_id']) {
        logDebug('ERRO: user_employee_id não encontrado');
        throw new Exception('Funcionário não possui registro em users. Recadastre o funcionário.');
    }
    
    // Buscar email pessoal em users usando user_employee_id
    logDebug('Buscando email pessoal em users');
    $stmt = $mysqli->prepare("
        SELECT email 
        FROM users 
        WHERE id = ?
        AND type = 'employee'
        LIMIT 1
    ");
    $stmt->bind_param('i', $funcionario['user_employee_id']);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows === 0) {
        logDebug('ERRO: Email pessoal não encontrado em users');
        throw new Exception('Email pessoal do funcionário não encontrado. Recadastre o funcionário.');
    }
    
    $userData = $userResult->fetch_assoc();
    $emailPessoal = $userData['email'];
    
    logDebug('Email pessoal encontrado', [
        'email_pessoal' => $emailPessoal
    ]);
    
    // Gerar token único
    $token = bin2hex(random_bytes(32));
    $tokenExpira = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    logDebug('Token gerado', ['expira_em' => $tokenExpira]);
    
    // Atualizar funcionário
    $stmt = $mysqli->prepare("
        UPDATE employees SET
            pode_acessar_sistema = 1,
            token_primeiro_acesso = ?,
            token_expira_em = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('ssi', $token, $tokenExpira, $funcionarioId);
    
    if (!$stmt->execute()) {
        logDebug('ERRO ao atualizar', ['error' => $stmt->error]);
        throw new Exception('Erro ao conceder acesso');
    }
    
    // Atualizar users com email_corporativo
    logDebug('Atualizando email_corporativo em users');
    $stmt = $mysqli->prepare("
        UPDATE users SET
            email_corporativo = ?
        WHERE id = ?
    ");
    $stmt->bind_param('si', $funcionario['email_company'], $funcionario['user_employee_id']);
    $stmt->execute();
    
    // Adicionar permissões
    logDebug('Adicionando permissões');
    $stmt = $mysqli->prepare("
        INSERT INTO employee_permissions (employee_id, module, can_view, can_edit, can_create)
        VALUES (?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
            can_view = 1,
            can_edit = VALUES(can_edit),
            can_create = VALUES(can_create)
    ");
    
    foreach ($permissoes as $modulo) {
        $canEdit = in_array($modulo, ['mensagens']) ? 1 : 0;
        $canCreate = in_array($modulo, ['mensagens']) ? 1 : 0;
        
        $stmt->bind_param('isii', $funcionarioId, $modulo, $canEdit, $canCreate);
        $stmt->execute();
        
        logDebug('Permissão adicionada', ['modulo' => $modulo]);
    }
    
    // Gerar link de primeiro acesso
    $linkAcesso = "http://" . $_SERVER['HTTP_HOST'] . "/vsg/pages/business/employee/primeiro_acesso.php?token=" . $token;
    
    logDebug('Link gerado', ['link' => $linkAcesso]);
    
    // Enviar email
    logDebug('Enviando email', [
        'destino' => $emailPessoal,
        'email_login' => $funcionario['email_company']
    ]);
    
    $emailEnviado = enviarEmailVisionGreen(
        $emailPessoal,
        $funcionario['nome'],
        '',
        'employee_access',
        [
            'empresa' => $funcionario['empresa_nome'],
            'link_acesso' => $linkAcesso,
            'cargo' => $funcionario['cargo'],
            'email_login' => $funcionario['email_company']
        ]
    );
    
    if (!$emailEnviado) {
        logDebug('AVISO: Falha ao enviar email, mas acesso foi concedido');
    } else {
        logDebug('Email enviado com sucesso');
    }
    
    logDebug('Acesso concedido com sucesso');
    logDebug('=== FIM CONCEDER ACESSO (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'message' => 'Acesso concedido com sucesso!',
        'link_acesso' => $linkAcesso,
        'email_pessoal' => $emailPessoal,
        'email_company' => $funcionario['email_company'],
        'email_enviado' => $emailEnviado
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM CONCEDER ACESSO (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}