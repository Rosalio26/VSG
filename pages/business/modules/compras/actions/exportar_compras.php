<?php
/**
 * Exportar compras para CSV
 */

session_start();
require_once '../../../../../registration/includes/db.php';

$isEmployee = isset($_SESSION['employee_auth']['employee_id']);
$isCompany = isset($_SESSION['auth']['user_id']);

if (!$isEmployee && !$isCompany) {
    exit('Acesso negado');
}

$companyId = $isEmployee 
    ? (int)$_SESSION['employee_auth']['empresa_id'] 
    : (int)$_SESSION['auth']['user_id'];

try {
    $stmt = $mysqli->prepare("
        SELECT 
            o.order_number,
            o.order_date,
            u.nome as customer_name,
            u.email as customer_email,
            u.telefone as customer_phone,
            o.total,
            o.currency,
            o.status,
            o.payment_status,
            o.payment_method,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
        FROM orders o
        INNER JOIN users u ON o.customer_id = u.id
        WHERE o.company_id = ? AND o.deleted_at IS NULL
        ORDER BY o.order_date DESC
    ");
    
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $filename = 'compras_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    $headers = [
        'Nº Pedido', 'Data', 'Cliente', 'Email', 'Telefone',
        'Total', 'Moeda', 'Status', 'Pagamento', 'Método Pag.', 'Itens'
    ];
    
    fputcsv($output, $headers, ';');
    
    while ($row = $result->fetch_assoc()) {
        $data = [
            $row['order_number'],
            date('d/m/Y', strtotime($row['order_date'])),
            $row['customer_name'],
            $row['customer_email'],
            $row['customer_phone'],
            number_format($row['total'], 2, ',', '.'),
            $row['currency'],
            ucfirst($row['status']),
            ucfirst($row['payment_status']),
            ucfirst($row['payment_method']),
            $row['items_count']
        ];
        
        fputcsv($output, $data, ';');
    }
    
    fclose($output);
    $stmt->close();
    exit;
    
} catch (Exception $e) {
    error_log("Erro ao exportar: " . $e->getMessage());
    exit('Erro ao exportar');
}