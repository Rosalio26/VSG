<?php
require_once '../../../../../registration/includes/db.php';
session_start();

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

// Verificar permissão
if (!$adminId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$format = $input['format'] ?? 'csv';
$options = $input['options'] ?? [];

try {
    // Registrar exportação no audit log
    $stmt = $mysqli->prepare("
        INSERT INTO admin_audit_logs (admin_id, action, details, ip_address, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $action = "Export: " . ucfirst($type);
    $details = "Formato: " . strtoupper($format) . " | Opções: " . json_encode($options);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param('isss', $adminId, $action, $details, $ip);
    $stmt->execute();
    
    // Processar exportação baseado no tipo
    switch ($type) {
        case 'companies':
            exportCompanies($mysqli, $format, $options);
            break;
        case 'transactions':
            exportTransactions($mysqli, $format, $options);
            break;
        case 'metrics':
            exportMetrics($mysqli, $format, $options);
            break;
        case 'report':
            exportReport($mysqli, $format, $options);
            break;
        default:
            throw new Exception('Tipo de exportação inválido');
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/* ================= FUNÇÕES DE EXPORTAÇÃO ================= */

function exportCompanies($mysqli, $format, $options) {
    // Buscar dados
    $sql = "
        SELECT 
            u.id,
            u.nome,
            u.email,
            b.tax_id,
            b.status_documentos,
            b.motivo_rejeicao,
            u.created_at";
    
    if (isset($options['companies_subscription']) && $options['companies_subscription']) {
        $sql .= ",
            us.status as subscription_status,
            sp.name as plan_name,
            us.mrr";
    }
    
    $sql .= "
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id";
    
    if (isset($options['companies_subscription']) && $options['companies_subscription']) {
        $sql .= "
        LEFT JOIN user_subscriptions us ON u.id = us.user_id
        LEFT JOIN subscription_plans sp ON us.plan_id = sp.id";
    }
    
    $sql .= " WHERE u.type = 'company' ORDER BY u.created_at DESC";
    
    $result = $mysqli->query($sql);
    
    if ($format === 'csv') {
        exportAsCSV($result, 'Empresas', [
            'ID', 'Nome', 'Email', 'NIF', 'Status Docs', 'Motivo Rejeição', 'Data Cadastro',
            'Status Assinatura', 'Plano', 'MRR'
        ]);
    } elseif ($format === 'excel') {
        exportAsExcel($result, 'Empresas');
    } elseif ($format === 'pdf') {
        exportAsPDF($result, 'Empresas Cadastradas');
    }
}

function exportTransactions($mysqli, $format, $options) {
    $dateFrom = $options['date_from'] ?? date('Y-m-01');
    $dateTo = $options['date_to'] ?? date('Y-m-d');
    
    $whereConditions = ["DATE(t.transaction_date) BETWEEN '$dateFrom' AND '$dateTo'"];
    
    if (isset($options['transactions_completed']) && $options['transactions_completed']) {
        $whereConditions[] = "t.status = 'completed'";
    }
    
    if (isset($options['transactions_pending']) && $options['transactions_pending']) {
        if (count($whereConditions) > 1) {
            array_pop($whereConditions);
            $whereConditions[] = "t.status IN ('completed', 'pending')";
        } else {
            $whereConditions[] = "t.status = 'pending'";
        }
    }
    
    $where = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            t.invoice_number,
            t.transaction_date,
            u.nome as company_name,
            u.email as company_email,
            t.type,
            t.payment_method,
            t.amount,
            t.status,
            sp.name as plan_name
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN subscription_plans sp ON t.plan_id = sp.id
        WHERE $where
        ORDER BY t.transaction_date DESC
    ";
    
    $result = $mysqli->query($sql);
    
    if ($format === 'csv') {
        exportAsCSV($result, 'Transacoes', [
            'Invoice', 'Data', 'Empresa', 'Email', 'Tipo', 'Método', 'Valor', 'Status', 'Plano'
        ]);
    } elseif ($format === 'excel') {
        exportAsExcel($result, 'Transações');
    } elseif ($format === 'pdf') {
        exportAsPDF($result, 'Transações Financeiras');
    }
}

function exportMetrics($mysqli, $format, $options) {
    $dateFrom = $options['date_from'] ?? date('Y-m-01');
    $dateTo = $options['date_to'] ?? date('Y-m-d');
    
    $sql = "
        SELECT 
            cgm.metric_date,
            u.nome as company_name,
            cgm.revenue,
            cgm.active_users,
            cgm.new_signups,
            cgm.storage_used_gb,
            cgm.api_calls,
            cgm.satisfaction_score
        FROM company_growth_metrics cgm
        LEFT JOIN users u ON cgm.user_id = u.id
        WHERE cgm.metric_date BETWEEN '$dateFrom' AND '$dateTo'
        ORDER BY cgm.metric_date DESC, u.nome ASC
    ";
    
    $result = $mysqli->query($sql);
    
    if ($format === 'csv') {
        exportAsCSV($result, 'Metricas', [
            'Data', 'Empresa', 'Receita', 'Usuários Ativos', 'Novos Cadastros', 
            'Storage (GB)', 'API Calls', 'Satisfação'
        ]);
    } elseif ($format === 'excel') {
        exportAsExcel($result, 'Métricas');
    }
}

function exportReport($mysqli, $format, $options) {
    $dateFrom = $options['date_from'] ?? date('Y-m-01');
    $dateTo = $options['date_to'] ?? date('Y-m-d');
    
    // Gerar relatório em PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="relatorio_' . date('Y-m-d') . '.pdf"');
    
    // Aqui você implementaria a geração de PDF
    // Por enquanto, vamos criar um placeholder
    echo "Relatório Executivo - VisionGreen\n";
    echo "Período: $dateFrom a $dateTo\n";
    echo "\n";
    echo "Este recurso será implementado em breve.";
}

/* ================= FUNÇÕES AUXILIARES ================= */

function exportAsCSV($result, $filename, $headers = null) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    if ($headers) {
        fputcsv($output, $headers, ';');
    } else {
        $firstRow = $result->fetch_assoc();
        if ($firstRow) {
            fputcsv($output, array_keys($firstRow), ';');
            fputcsv($output, $firstRow, ';');
            $result->data_seek(1);
        }
    }
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

function exportAsExcel($result, $sheetName) {
    // Nota: Para produção, use PHPSpreadsheet
    // Por agora, vamos exportar como CSV com extensão .xlsx
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $sheetName . '_' . date('Y-m-d') . '.xlsx"');
    
    // Criar arquivo CSV temporário
    $output = fopen('php://output', 'w');
    
    // Headers
    $firstRow = $result->fetch_assoc();
    if ($firstRow) {
        fputcsv($output, array_keys($firstRow));
        fputcsv($output, $firstRow);
        $result->data_seek(1);
    }
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportAsPDF($result, $title) {
    // Nota: Para produção, use TCPDF ou Dompdf
    // Por agora, vamos criar um PDF simples
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $title) . '_' . date('Y-m-d') . '.pdf"');
    
    echo "%PDF-1.4\n";
    echo "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    echo "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    echo "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
    echo "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    echo "5 0 obj\n<< /Length 100 >>\nstream\nBT\n/F1 12 Tf\n100 700 Td\n($title) Tj\nET\nendstream\nendobj\n";
    echo "xref\n0 6\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\n0000000262 00000 n\n0000000341 00000 n\n";
    echo "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n484\n%%EOF";
    
    exit;
}