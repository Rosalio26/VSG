<?php
/**
 * LISTAR FUNCIONÁRIOS - COM FIX DE COLLATION
 * Usa COLLATE utf8mb4_unicode_ci no JOIN para evitar erro de collation
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

$empresaId = (int)$_GET['user_id'];
$status = $_GET['status'] ?? '';
$departamento = $_GET['departamento'] ?? '';
$search = $_GET['search'] ?? '';

logDebug('Parâmetros', [
    'empresa_id' => $empresaId,
    'status' => $status,
    'departamento' => $departamento,
    'search' => $search
]);

try {
    // FIX COLLATION: Usar COLLATE utf8mb4_unicode_ci no JOIN
    $sql = "
        SELECT 
            e.id,
            e.nome,
            e.email_company,
            e.telefone,
            e.cargo,
            e.departamento,
            e.data_admissao,
            e.salario,
            e.status,
            e.foto_path,
            e.documento,
            e.tipo_documento,
            e.created_at,
            e.pode_acessar_sistema,
            u.email as email_pessoal,
            u.id as user_id
        FROM employees e
        LEFT JOIN users u ON (
            u.email COLLATE utf8mb4_unicode_ci = e.email_company COLLATE utf8mb4_unicode_ci 
            OR 
            u.email_corporativo COLLATE utf8mb4_unicode_ci = e.email_company COLLATE utf8mb4_unicode_ci
        )
        WHERE e.user_id = ?
        AND e.is_active = 1
    ";
    
    $params = [$empresaId];
    $types = 'i';
    
    if ($status) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
        $types .= 's';
        logDebug('Filtro status', ['status' => $status]);
    }
    
    if ($departamento) {
        $sql .= " AND e.departamento = ?";
        $params[] = $departamento;
        $types .= 's';
        logDebug('Filtro departamento', ['departamento' => $departamento]);
    }
    
    if ($search) {
        $sql .= " AND (e.nome LIKE ? OR u.email LIKE ? OR e.email_company LIKE ? OR e.cargo LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
        logDebug('Filtro busca', ['search' => $search]);
    }
    
    $sql .= " ORDER BY e.nome ASC";
    
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
        // Adicionar email pessoal ao resultado
        $row['email'] = $row['email_pessoal'];
        unset($row['email_pessoal']);
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
    $stmt2->bind_param('i', $empresaId);
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