<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../../../registration/includes/db.php';

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($isEmployee) {
    $companyId = (int)$_SESSION['employee_auth']['empresa_id'];
} else {
    $companyId = (int)$_SESSION['auth']['user_id'];
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$paymentStatus = trim($_GET['payment_status'] ?? '');

try {
    $sql = "
        SELECT 
            o.id,
            o.order_number,
            o.order_date,
            o.status,
            o.payment_status,
            o.payment_method,
            o.total,
            o.currency,
            CONCAT(u.nome, ' ', COALESCE(u.apelido, '')) AS customer_name,
            u.email AS customer_email,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items_count
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        WHERE o.company_id = ? 
        AND o.deleted_at IS NULL
    ";
    
    $params = [$companyId];
    $types = 'i';
    
    if ($search) {
        $sql .= " AND (o.order_number LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'sss';
    }
    
    if ($status) {
        $statusMap = [
            'pending' => 'pendente',
            'confirmed' => 'confirmado',
            'processing' => 'processando',
            'shipped' => 'enviado',
            'delivered' => 'entregue',
            'cancelled' => 'cancelado'
        ];
        $dbStatus = $statusMap[$status] ?? $status;
        $sql .= " AND o.status = ?";
        $params[] = $dbStatus;
        $types .= 's';
    }
    
    if ($paymentStatus) {
        $paymentMap = [
            'pending' => 'pendente',
            'paid' => 'pago',
            'partial' => 'parcial',
            'refunded' => 'reembolsado'
        ];
        $dbPayment = $paymentMap[$paymentStatus] ?? $paymentStatus;
        $sql .= " AND o.payment_status = ?";
        $params[] = $dbPayment;
        $types .= 's';
    }
    
    $sql .= " ORDER BY o.order_date DESC";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $compras = [];
    $statusToEng = [
        'pendente' => 'pending',
        'confirmado' => 'confirmed',
        'processando' => 'processing',
        'enviado' => 'shipped',
        'entregue' => 'delivered',
        'cancelado' => 'cancelled'
    ];
    
    $paymentToEng = [
        'pendente' => 'pending',
        'pago' => 'paid',
        'parcial' => 'partial',
        'reembolsado' => 'refunded'
    ];
    
    while ($row = $result->fetch_assoc()) {
        $row['status'] = $statusToEng[$row['status']] ?? $row['status'];
        $row['payment_status'] = $paymentToEng[$row['payment_status']] ?? $row['payment_status'];
        $compras[] = $row;
    }
    $stmt->close();
    
    // Estatísticas
    $statsStmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregues,
            COALESCE(SUM(total), 0) as faturamento_total
        FROM orders 
        WHERE company_id = ? AND deleted_at IS NULL
    ");
    $statsStmt->bind_param('i', $companyId);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    
    echo json_encode([
        'success' => true,
        'compras' => $compras,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro em search_compras: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedidos']);
}