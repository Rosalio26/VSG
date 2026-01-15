<?php
/**
 * ================================================================================
 * VISIONGREEN - EXPORT FORMS
 * Módulo: modules/tabelas/actions/export_forms.php
 * Descrição: AJAX endpoint para exportar formulários em CSV
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../../registration/includes/db.php';
    session_start();
}

// Validar autenticação
if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
    die('Acesso não autorizado');
}

// Filtro de status
$statusFilter = $_GET['status'] ?? 'todos';
$whereClause = "WHERE u.type = 'company' AND u.deleted_at IS NULL";

if ($statusFilter !== 'todos') {
    $statusSafe = $mysqli->real_escape_string($statusFilter);
    $whereClause .= " AND b.status_documentos = '$statusSafe'";
}

// Query para exportação
$query = "
    SELECT 
        u.id,
        u.nome,
        u.email,
        u.telefone,
        u.created_at,
        b.tax_id,
        b.business_type,
        b.country,
        b.region,
        b.city,
        b.status_documentos,
        b.updated_at,
        DATEDIFF(NOW(), u.created_at) as dias_pendente
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    $whereClause
    ORDER BY u.created_at DESC
";

$result = $mysqli->query($query);

// Header para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="formularios_' . date('Y-m-d_H-i-s') . '.csv"');

// BOM para UTF-8
echo "\xEF\xBB\xBF";

// Cabeçalhos CSV
$headers = [
    'ID',
    'Empresa',
    'Email',
    'Telefone',
    'NIF',
    'Tipo Negócio',
    'País',
    'Região',
    'Cidade',
    'Status Documentos',
    'Data Criação',
    'Última Atualização',
    'Dias Pendente'
];

echo implode(';', $headers) . "\n";

// Dados
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data = [
            $row['id'],
            $row['nome'],
            $row['email'],
            $row['telefone'],
            $row['tax_id'] ?? 'N/A',
            $row['business_type'] ?? 'N/A',
            $row['country'] ?? 'N/A',
            $row['region'] ?? 'N/A',
            $row['city'] ?? 'N/A',
            strtoupper($row['status_documentos'] ?? 'PENDENTE'),
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-',
            $row['dias_pendente']
        ];
        
        // Escapar aspas duplas
        $data = array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $data);
        
        echo implode(';', $data) . "\n";
    }
}

exit;

?>