<?php
/**
 * CADASTRAR FUNCIONÁRIO - COM user_employee_id
 * Cria em users (type=employee) + employees (dados profissionais)
 * SALVA user_employee_id em employees para fazer a ligação
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

function gerarEmailCorporativo($nome, $nomeEmpresa, $mysqli) {
    $nome = strtolower($nome);
    $nome = preg_replace('/[áàãâä]/u', 'a', $nome);
    $nome = preg_replace('/[éèêë]/u', 'e', $nome);
    $nome = preg_replace('/[íìîï]/u', 'i', $nome);
    $nome = preg_replace('/[óòõôö]/u', 'o', $nome);
    $nome = preg_replace('/[úùûü]/u', 'u', $nome);
    $nome = preg_replace('/[ç]/u', 'c', $nome);
    $nome = preg_replace('/[^a-z0-9\s]/i', '', $nome);
    $nome = preg_replace('/\s+/', '', $nome);
    
    $nomeEmpresa = strtolower($nomeEmpresa);
    $nomeEmpresa = preg_replace('/[áàãâä]/u', 'a', $nomeEmpresa);
    $nomeEmpresa = preg_replace('/[éèêë]/u', 'e', $nomeEmpresa);
    $nomeEmpresa = preg_replace('/[íìîï]/u', 'i', $nomeEmpresa);
    $nomeEmpresa = preg_replace('/[óòõôö]/u', 'o', $nomeEmpresa);
    $nomeEmpresa = preg_replace('/[úùûü]/u', 'u', $nomeEmpresa);
    $nomeEmpresa = preg_replace('/[ç]/u', 'c', $nomeEmpresa);
    $nomeEmpresa = preg_replace('/[^a-z0-9]/i', '', $nomeEmpresa);
    
    $baseEmail = $nome . '@' . $nomeEmpresa . '.vsg.com';
    $emailCorporativo = $baseEmail;
    $contador = 1;
    
    while (true) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? OR email_corporativo = ? LIMIT 1");
        $stmt->bind_param('ss', $emailCorporativo, $emailCorporativo);
        $stmt->execute();
        $existsInUsers = $stmt->get_result()->num_rows > 0;
        
        $stmt = $mysqli->prepare("SELECT id FROM employees WHERE email_company = ? LIMIT 1");
        $stmt->bind_param('s', $emailCorporativo);
        $stmt->execute();
        $existsInEmployees = $stmt->get_result()->num_rows > 0;
        
        if (!$existsInUsers && !$existsInEmployees) {
            break;
        }
        
        $emailCorporativo = $nome . $contador . '@' . $nomeEmpresa . '.vsg.com';
        $contador++;
        
        if ($contador > 999) {
            throw new Exception('Não foi possível gerar email corporativo único');
        }
    }
    
    return $emailCorporativo;
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

$empresaId = (int)$_SESSION['auth']['user_id'];
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
    'empresa_id' => $empresaId,
    'nome' => $nome,
    'email' => $email,
    'cargo' => $cargo
]);

try {
    // Validações
    if (empty($nome)) throw new Exception('Nome é obrigatório');
    if (empty($email)) throw new Exception('Email pessoal é obrigatório');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');
    if (empty($telefone)) throw new Exception('Telefone é obrigatório');
    if (empty($cargo)) throw new Exception('Cargo é obrigatório');
    if (empty($data_admissao)) throw new Exception('Data de admissão é obrigatória');
    
    logDebug('Validações OK');
    
    // Verificar email pessoal duplicado
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Este email já está cadastrado no sistema');
    }
    
    // Buscar nome da empresa
    $stmt = $mysqli->prepare("SELECT nome FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $empresa = $stmt->get_result()->fetch_assoc();
    
    if (!$empresa) {
        throw new Exception('Empresa não encontrada');
    }
    
    $nomeEmpresa = $empresa['nome'];
    logDebug('Empresa encontrada', ['nome' => $nomeEmpresa]);
    
    // Gerar email corporativo
    $emailCorporativo = gerarEmailCorporativo($nome, $nomeEmpresa, $mysqli);
    logDebug('Email corporativo gerado', ['email_company' => $emailCorporativo]);
    
    // Iniciar transação
    $mysqli->begin_transaction();
    
    try {
        // 1. CRIAR USUÁRIO (type=employee)
        logDebug('Criando usuário employee');
        $stmt = $mysqli->prepare("
            INSERT INTO users (
                type, nome, email, telefone, password_hash, 
                status, registration_step, created_at
            ) VALUES (
                'employee', ?, ?, ?, '', 
                'pending', 'employee_pending', NOW()
            )
        ");
        
        $stmt->bind_param('sss', $nome, $email, $telefone);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao criar usuário: ' . $stmt->error);
        }
        
        $userEmployeeId = $mysqli->insert_id;
        logDebug('Usuário criado', ['user_employee_id' => $userEmployeeId]);
        
        // 2. CRIAR DADOS PROFISSIONAIS (employees) COM user_employee_id
        logDebug('Criando dados profissionais');
        $stmt = $mysqli->prepare("
            INSERT INTO employees (
                user_id, user_employee_id, nome, email_company, telefone, cargo, departamento,
                data_admissao, salario, status, tipo_documento, documento,
                endereco, observacoes, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->bind_param(
            'iissssssdsssss',
            $empresaId,          // user_id (empresa)
            $userEmployeeId,     // user_employee_id (funcionário em users)
            $nome,
            $emailCorporativo,
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
            throw new Exception('Erro ao criar dados profissionais: ' . $stmt->error);
        }
        
        $employeeId = $mysqli->insert_id;
        logDebug('Dados profissionais criados', ['employee_id' => $employeeId]);
        
        // Commit
        $mysqli->commit();
        
        logDebug('Funcionário cadastrado com sucesso', [
            'user_employee_id' => $userEmployeeId,
            'employee_id' => $employeeId,
            'email_pessoal' => $email,
            'email_corporativo' => $emailCorporativo
        ]);
        logDebug('=== FIM CADASTRAR FUNCIONÁRIO (SUCESSO) ===');
        
        echo json_encode([
            'success' => true,
            'message' => 'Funcionário cadastrado com sucesso',
            'user_employee_id' => $userEmployeeId,
            'employee_id' => $employeeId,
            'email_pessoal' => $email,
            'email_company' => $emailCorporativo
        ]);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM CADASTRAR FUNCIONÁRIO (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}