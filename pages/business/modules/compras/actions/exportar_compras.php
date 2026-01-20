<?php
/**
 * ================================================================================
 * VISIONGREEN - EXPORTAR COMPRAS PARA CSV
 * Arquivo: pages/business/modules/compras/actions/exportar_compras.php
 * ✅ CORRIGIDO: Mapeamento de status português → legível
 * ================================================================================
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

// Mapeamento de status para exibição no CSV
$statusLabels = [
    'pendente' => 'Pendente',
    'confirmado' => 'Confirmado',
    'processando' => 'Processando',
    'enviado' => 'Enviado',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado'
];

$paymentStatusLabels = [
    'pendente' => 'Pendente',
    'pago' => 'Pago',
    'parcial' => 'Parcial',
    'reembolsado' => 'Reembolsado'
];

$paymentMethodLabels = [
    'mpesa' => 'M-Pesa',
    'emola' => 'E-Mola',
    'visa' => 'Visa',
    'mastercard' => 'Mastercard',
    'manual' => 'Pagamento Manual'
];

try {
    $stmt = $mysqli->prepare("
        SELECT 
            o.order_number,
            o.order_date,
            CONCAT(u.nome, ' ', COALESCE(u.apelido, '')) as customer_name,
            u.email as customer_email,
            u.telefone as customer_phone,
            o.total,
            o.currency,
            o.status,
            o.payment_status,
            o.payment_method,
            o.shipping_address,
            o.shipping_city,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
            (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o
        INNER JOIN users u ON o.customer_id = u.id
        WHERE o.company_id = ? AND o.deleted_at IS NULL
        ORDER BY o.order_date DESC
    ");
    
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $filename = 'pedidos_visiongreen_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // BOM UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos
    $headers = [
        'Nº Pedido',
        'Data Pedido',
        'Cliente',
        'Email',
        'Telefone',
        'Endereço',
        'Cidade',
        'Total (MZN)',
        'Moeda',
        'Status Pedido',
        'Status Pagamento',
        'Método Pagamento',
        'Qtd Produtos',
        'Total Itens'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        $data = [
            $row['order_number'],
            date('d/m/Y H:i', strtotime($row['order_date'])),
            $row['customer_name'],
            $row['customer_email'],
            $row['customer_phone'] ?? 'N/A',
            $row['shipping_address'] ?? 'N/A',
            $row['shipping_city'] ?? 'N/A',
            number_format($row['total'], 2, ',', '.'),
            $row['currency'],
            $statusLabels[$row['status']] ?? ucfirst($row['status']),
            $paymentStatusLabels[$row['payment_status']] ?? ucfirst($row['payment_status']),
            $paymentMethodLabels[$row['payment_method']] ?? ucfirst($row['payment_method']),
            $row['items_count'] ?? 0,
            $row['total_items'] ?? 0
        ];
        
        fputcsv($output, $data, ';');
    }
    
    fclose($output);
    $stmt->close();
    exit;
    
} catch (Exception $e) {
    error_log("Erro ao exportar compras: " . $e->getMessage());
    http_response_code(500);
    exit('Erro ao exportar dados. Por favor, tente novamente.');
}