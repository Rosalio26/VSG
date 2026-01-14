<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - EXPORTAÇÃO DE HISTÓRICO (CSV/EXCEL)
 * Arquivo: modules/dashboard/historico_inc/export.php
 * Descrição: Sistema avançado de exportação de logs
 * ================================================================================
 */

session_start();
require_once '../../../../../registration/includes/db.php';

// Verificar autenticação
if (!isset($_SESSION['auth']['role']) || !in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    die('Acesso negado');
}

$adminRole = $_SESSION['auth']['role'];
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= PARÂMETROS ================= */
$periodo = $_GET['periodo'] ?? '7';
$tipo = $_GET['tipo'] ?? 'all';
$admin = $_GET['admin'] ?? 'all';
$format = $_GET['format'] ?? 'csv'; // csv ou excel

/* ================= CONSTRUIR QUERY ================= */
$where_conditions = ["1=1"];

if ($periodo !== 'all') {
    $where_conditions[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL $periodo DAY)";
}

if ($tipo !== 'all') {
    $tipo_safe = $mysqli->real_escape_string($tipo);
    $where_conditions[] = "al.action LIKE '%$tipo_safe%'";
}

if ($admin !== 'all') {
    $admin_id_safe = (int)$admin;
    $where_conditions[] = "al.admin_id = $admin_id_safe";
}

$where_clause = implode(" AND ", $where_conditions);

/* ================= BUSCAR DADOS ================= */
$sql = "
    SELECT 
        al.id,
        al.admin_id,
        COALESCE(u.nome, 'Sistema') as admin_nome,
        COALESCE(u.email, 'sistema@visiongreen.com') as admin_email,
        al.action,
        al.ip_address,
        al.created_at,
        DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') as data_formatada
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    WHERE $where_clause
    ORDER BY al.created_at DESC
    LIMIT 1000
";

$result = $mysqli->query($sql);

if (!$result || $result->num_rows === 0) {
    die('Nenhum dado para exportar');
}

/* ================= EXPORTAR CSV ================= */
if ($format === 'csv') {
    // Headers para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="historico_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalho
    fputcsv($output, [
        'ID',
        'Ação',
        'Admin',
        'Email do Admin',
        'Endereço IP',
        'Data e Hora',
        'Categoria'
    ], ';');
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        // Determinar categoria
        $categoria = 'Outro';
        if (strpos($row['action'], 'LOGIN') !== false) {
            $categoria = 'Login';
        } elseif (strpos($row['action'], 'AUDIT') !== false) {
            $categoria = 'Auditoria';
        } elseif (strpos($row['action'], 'CREATE') !== false) {
            $categoria = 'Criação';
        } elseif (strpos($row['action'], 'UPDATE') !== false) {
            $categoria = 'Atualização';
        } elseif (strpos($row['action'], 'DELETE') !== false) {
            $categoria = 'Exclusão';
        }
        
        fputcsv($output, [
            $row['id'],
            $row['action'],
            $row['admin_nome'],
            $row['admin_email'],
            $row['ip_address'],
            $row['data_formatada'],
            $categoria
        ], ';');
    }
    
    fclose($output);
    exit;
}

/* ================= EXPORTAR EXCEL (HTML TABLE) ================= */
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="historico_' . date('Y-m-d_His') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr style="background-color: #238636; color: #fff; font-weight: bold;">';
    echo '<th>ID</th>';
    echo '<th>Ação</th>';
    echo '<th>Admin</th>';
    echo '<th>Email</th>';
    echo '<th>IP</th>';
    echo '<th>Data e Hora</th>';
    echo '<th>Categoria</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $categoria = 'Outro';
        if (strpos($row['action'], 'LOGIN') !== false) {
            $categoria = 'Login';
        } elseif (strpos($row['action'], 'AUDIT') !== false) {
            $categoria = 'Auditoria';
        } elseif (strpos($row['action'], 'CREATE') !== false) {
            $categoria = 'Criação';
        } elseif (strpos($row['action'], 'UPDATE') !== false) {
            $categoria = 'Atualização';
        } elseif (strpos($row['action'], 'DELETE') !== false) {
            $categoria = 'Exclusão';
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['action']) . '</td>';
        echo '<td>' . htmlspecialchars($row['admin_nome']) . '</td>';
        echo '<td>' . htmlspecialchars($row['admin_email']) . '</td>';
        echo '<td>' . htmlspecialchars($row['ip_address']) . '</td>';
        echo '<td>' . htmlspecialchars($row['data_formatada']) . '</td>';
        echo '<td>' . htmlspecialchars($categoria) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/* ================= EXPORTAR JSON (API) ================= */
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="historico_' . date('Y-m-d_His') . '.json"');
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'total' => count($data),
        'periodo' => $periodo,
        'exported_at' => date('Y-m-d H:i:s'),
        'data' => $data
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

die('Formato inválido');