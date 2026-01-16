<?php
/**
 * ================================================================================
 * VISIONGREEN - MOTOR DE BUSCA AUDITORES
 * Arquivo: modules/auditor/aud_search.php
 * Descrição: API de busca para módulo de auditores
 * ================================================================================
 */

require_once '../../../../registration/includes/db.php';
session_start();

header('Content-Type: application/json');

// Verificar acesso
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
if ($adminRole !== 'superadmin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Acesso negado'
    ]);
    exit;
}

$search = trim($_GET['search'] ?? '');
$order = $_GET['order'] ?? 'recent';
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;

$results = [];
$totalRecords = 0;
$totalPages = 0;

if (empty($search)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Busca vazia',
        'results' => [],
        'total' => 0
    ]);
    exit;
}

try {
    // Escapar o termo de busca
    $searchEscaped = $mysqli->real_escape_string($search);
    
    // Contar total de resultados
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM users 
        WHERE type = 'admin' 
        AND role = 'admin' 
        AND deleted_at IS NULL 
        AND (nome LIKE '%$searchEscaped%' 
             OR email LIKE '%$searchEscaped%' 
             OR public_id LIKE '%$searchEscaped%' 
             OR email_corporativo LIKE '%$searchEscaped%')
    ";
    
    $countResult = $mysqli->query($countQuery);
    if ($countResult) {
        $totalRecords = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $perPage);
    }
    
    // Validar página
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    
    // Determinar ordenação
    $orderClause = match($order) {
        'name' => 'ORDER BY nome ASC',
        'oldest' => 'ORDER BY created_at ASC',
        default => 'ORDER BY created_at DESC'
    };
    
    // Buscar resultados
    $query = "
        SELECT 
            id,
            public_id,
            nome,
            email,
            email_corporativo,
            status,
            created_at,
            last_activity
        FROM users
        WHERE type = 'admin' 
        AND role = 'admin' 
        AND deleted_at IS NULL 
        AND (nome LIKE '%$searchEscaped%' 
             OR email LIKE '%$searchEscaped%' 
             OR public_id LIKE '%$searchEscaped%' 
             OR email_corporativo LIKE '%$searchEscaped%')
        $orderClause
        LIMIT $offset, $perPage
    ";
    
    $stmt = $mysqli->query($query);
    
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'public_id' => htmlspecialchars($row['public_id']),
                'nome' => htmlspecialchars($row['nome']),
                'email' => htmlspecialchars($row['email']),
                'email_corporativo' => htmlspecialchars($row['email_corporativo']),
                'status' => $row['status'],
                'created_at' => date('d/m/Y H:i', strtotime($row['created_at'])),
                'last_activity' => $row['last_activity'] ? date('d/m/Y H:i', strtotime($row['last_activity'])) : 'Nunca'
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'search' => htmlspecialchars($search),
        'order' => $order,
        'page' => $page,
        'total' => $totalRecords,
        'pages' => $totalPages,
        'perPage' => $perPage,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'results' => []
    ]);
}
?>