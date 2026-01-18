<?php
/**
 * MIDDLEWARE DE AUTENTICAÇÃO - DASHBOARD BUSINESS
 * Aceita login de empresa OU funcionário
 * Arquivo: pages/business/auth_check.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se é empresa ou funcionário
$isCompany = isset($_SESSION['auth']['user_id']) && $_SESSION['auth']['type'] === 'company';
$isEmployee = isset($_SESSION['employee_auth']['employee_id']);

// Se não for nem empresa nem funcionário, redirecionar
if (!$isCompany && !$isEmployee) {
    header('Location: ../../registration/login/login.php');
    exit;
}

// Se for funcionário, verificar validade da sessão
if ($isEmployee) {
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $loginTime = $_SESSION['employee_auth']['login_time'] ?? 0;
    
    // Timeout de 8 horas
    $sessionTimeout = 8 * 60 * 60;
    if ((time() - $loginTime) > $sessionTimeout) {
        unset($_SESSION['employee_auth']);
        header('Location: employee/login_funcionario.php?error=session_expired');
        exit;
    }
    
    // Verificar se funcionário ainda tem acesso
    require_once __DIR__ . '/../../registration/includes/db.php';
    
    $stmt = $mysqli->prepare("
        SELECT e.id, e.status, e.pode_acessar_sistema, e.is_active,
               u.status as user_status
        FROM employees e
        INNER JOIN users u ON e.user_employee_id = u.id
        WHERE e.id = ?
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        unset($_SESSION['employee_auth']);
        header('Location: employee/login_funcionario.php?error=access_revoked');
        exit;
    }
    
    $employee = $result->fetch_assoc();
    $stmt->close();
    
    // Verificar status
    if (!$employee['is_active'] || !$employee['pode_acessar_sistema']) {
        unset($_SESSION['employee_auth']);
        header('Location: employee/login_funcionario.php?error=access_revoked');
        exit;
    }
    
    if ($employee['status'] !== 'ativo') {
        unset($_SESSION['employee_auth']);
        header('Location: employee/login_funcionario.php?error=account_inactive');
        exit;
    }
    
    if ($employee['user_status'] === 'blocked') {
        unset($_SESSION['employee_auth']);
        header('Location: employee/login_funcionario.php?error=account_inactive');
        exit;
    }
    
    // Atualizar timestamp
    $_SESSION['employee_auth']['login_time'] = time();
    
    // Definir variáveis globais para uso no dashboard
    $CURRENT_USER = [
        'type' => 'employee',
        'id' => $employee['id'],
        'user_id' => $_SESSION['employee_auth']['user_id'],
        'empresa_id' => $_SESSION['employee_auth']['empresa_id'],
        'nome' => $_SESSION['employee_auth']['nome'],
        'email' => $_SESSION['employee_auth']['email_company'],
        'email_pessoal' => $_SESSION['employee_auth']['email_pessoal'],
        'cargo' => $_SESSION['employee_auth']['cargo'],
        'empresa_nome' => $_SESSION['employee_auth']['empresa_nome']
    ];
    
} else {
    // É empresa
    $CURRENT_USER = [
        'type' => 'company',
        'id' => $_SESSION['auth']['user_id'],
        'user_id' => $_SESSION['auth']['user_id'],
        'nome' => $_SESSION['auth']['nome'],
        'email' => $_SESSION['auth']['email'],
        'public_id' => $_SESSION['auth']['public_id']
    ];
}

// Retornar TRUE para continuar execução
return true;