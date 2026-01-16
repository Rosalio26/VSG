<?php
/**
 * LISTAR FUNCIONÁRIOS
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
    
    file_put_contents($logDir . 'listar_funcionarios.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO LISTAR FUNCIONÁRIOS ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

$userId = (int)$_GET['user_id'];
$status = $_GET['status'] ?? '';
$departamento = $_GET['departamento'] ?? '';
$search = $_GET['search'] ?? '';

logDebug('Parâmetros', [
    'user_id' => $userId,
    'status' => $status,
    'departamento' => $departamento,
    'search' => $search
]);

try {
    $sql = "
        SELECT 
            id, nome, email, telefone, cargo, departamento,
            data_admissao, salario, status, foto_path,
            documento, tipo_documento, created_at
        FROM employees
        WHERE user_id = ?
        AND is_active = 1
    ";
    
    $params = [$userId];
    $types = 'i';
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
        logDebug('Filtro status', ['status' => $status]);
    }
    
    if ($departamento) {
        $sql .= " AND departamento = ?";
        $params[] = $departamento;
        $types .= 's';
        logDebug('Filtro departamento', ['departamento' => $departamento]);
    }
    
    if ($search) {
        $sql .= " AND (nome LIKE ? OR email LIKE ? OR cargo LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
        logDebug('Filtro busca', ['search' => $search]);
    }
    
    $sql .= " ORDER BY nome ASC";
    
    logDebug('Preparando query');
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        logDebug('ERRO prepare', ['error' => $mysqli->error]);
        throw new Exception('Erro ao preparar query');
    }
    
    $stmt->bind_param($types, ...$params);
    logDebug('Executando query');
    $stmt->execute();
    $result = $stmt->get_result();
    
    $funcionarios = [];
    while ($row = $result->fetch_assoc()) {
        $funcionarios[] = $row;
    }
    
    logDebug('Funcionários encontrados', ['count' => count($funcionarios)]);
    
    // Buscar departamentos únicos
    $stmt2 = $mysqli->prepare("
        SELECT DISTINCT departamento
        FROM employees
        WHERE user_id = ?
        AND departamento IS NOT NULL
        AND departamento != ''
        ORDER BY departamento ASC
    ");
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    $departamentos = [];
    while ($row = $result2->fetch_assoc()) {
        $departamentos[] = $row['departamento'];
    }
    
    logDebug('Departamentos encontrados', ['count' => count($departamentos)]);
    logDebug('=== FIM LISTAR FUNCIONÁRIOS (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'funcionarios' => $funcionarios,
        'departamentos' => $departamentos
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM LISTAR FUNCIONÁRIOS (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}