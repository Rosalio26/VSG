<?php
/**
 * MIDDLEWARE DE AUTENTICAÇÃO PARA FUNCIONÁRIOS
 * Arquivo: pages/business/employee/auth_middleware.php
 * 
 * Uso:
 * require_once 'employee/auth_middleware.php';
 * 
 * Verifica se:
 * - Funcionário está autenticado
 * - Sessão é válida
 * - Funcionário ainda tem permissão de acesso
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se existe sessão de funcionário
if (!isset($_SESSION['employee_auth']['employee_id'])) {
    header('Location: employee/login_funcionario.php?error=session_expired');
    exit;
}

// Pegar dados da sessão
$employeeId = (int)$_SESSION['employee_auth']['employee_id'];
$loginTime = $_SESSION['employee_auth']['login_time'] ?? 0;

// Timeout de sessão (8 horas)
$sessionTimeout = 8 * 60 * 60;
if ((time() - $loginTime) > $sessionTimeout) {
    unset($_SESSION['employee_auth']);
    header('Location: employee/login_funcionario.php?error=session_expired');
    exit;
}

// Verificar se funcionário ainda tem acesso no banco
require_once __DIR__ . '/../../../registration/includes/db.php';

$stmt = $mysqli->prepare("
    SELECT id, status, pode_acessar_sistema, is_active
    FROM employees
    WHERE id = ?
");
$stmt->bind_param('i', $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Funcionário não existe mais
    unset($_SESSION['employee_auth']);
    header('Location: employee/login_funcionario.php?error=access_revoked');
    exit;
}

$employee = $result->fetch_assoc();
$stmt->close();

// Verificar se ainda está ativo e com permissão
if (!$employee['is_active'] || !$employee['pode_acessar_sistema']) {
    unset($_SESSION['employee_auth']);
    header('Location: employee/login_funcionario.php?error=access_revoked');
    exit;
}

// Verificar se status permite login
if ($employee['status'] !== 'ativo') {
    unset($_SESSION['employee_auth']);
    header('Location: employee/login_funcionario.php?error=account_inactive');
    exit;
}

// Atualizar timestamp da sessão
$_SESSION['employee_auth']['login_time'] = time();

// Tudo OK - continuar execução
return true;