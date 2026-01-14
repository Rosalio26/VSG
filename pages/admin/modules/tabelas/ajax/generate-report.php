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
$template = $input['selectedTemplate'] ?? '';
$config = $input['config'] ?? [];
$sections = $input['sections'] ?? [];

try {
    // Registrar no audit log
    $stmt = $mysqli->prepare("
        INSERT INTO admin_audit_logs (admin_id, action, details, ip_address, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $action = "Geração de Relatório: " . ucfirst($template);
    $details = "Período: " . $config['dateFrom'] . " a " . $config['dateTo'] . " | Formato: " . $config['format'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param('isss', $adminId, $action, $details, $ip);
    $stmt->execute();
    
    // Gerar relatório baseado no formato
    switch ($config['format']) {
        case 'pdf':
            generatePDFReport($mysqli, $template, $config, $sections);
            break;
        case 'excel':
            generateExcelReport($mysqli, $template, $config, $sections);
            break;
        case 'web':
            generateWebReport($mysqli, $template, $config, $sections);
            break;
        default:
            throw new Exception('Formato inválido');
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/* ================= FUNÇÕES DE GERAÇÃO ================= */

function generatePDFReport($mysqli, $template, $config, $sections) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="relatorio_' . date('Y-m-d') . '.pdf"');
    
    // Buscar dados
    $data = collectReportData($mysqli, $config, $sections);
    
    // Gerar PDF simples
    // Em produção, use TCPDF ou Dompdf
    echo generateSimplePDF($config['title'], $data);
    exit;
}

function generateExcelReport($mysqli, $template, $config, $sections) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="relatorio_' . date('Y-m-d') . '.xlsx"');
    
    // Buscar dados
    $data = collectReportData($mysqli, $config, $sections);
    
    // Gerar Excel (CSV por enquanto)
    // Em produção, use PHPSpreadsheet
    echo generateSimpleExcel($config['title'], $data);
    exit;
}

function generateWebReport($mysqli, $template, $config, $sections) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="relatorio_' . date('Y-m-d') . '.html"');
    
    // Buscar dados
    $data = collectReportData($mysqli, $config, $sections);
    
    // Gerar HTML
    echo generateHTMLReport($config['title'], $config, $data);
    exit;
}

/* ================= COLETAR DADOS ================= */

function collectReportData($mysqli, $config, $sections) {
    $dateFrom = $config['dateFrom'];
    $dateTo = $config['dateTo'];
    $data = [];
    
    foreach ($sections as $section) {
        switch ($section) {
            case 'summary':
                $data['summary'] = [
                    'companies' => $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'company'")->fetch_assoc()['total'],
                    'active' => $mysqli->query("SELECT COUNT(*) as total FROM user_subscriptions WHERE status = 'active'")->fetch_assoc()['total'],
                    'revenue' => $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'completed' AND DATE(transaction_date) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_assoc()['total'],
                    'mrr' => $mysqli->query("SELECT COALESCE(SUM(mrr), 0) as total FROM user_subscriptions WHERE status = 'active'")->fetch_assoc()['total']
                ];
                break;
                
            case 'revenue':
                $result = $mysqli->query("
                    SELECT DATE(transaction_date) as date, SUM(amount) as total
                    FROM transactions
                    WHERE status = 'completed'
                    AND DATE(transaction_date) BETWEEN '$dateFrom' AND '$dateTo'
                    GROUP BY DATE(transaction_date)
                    ORDER BY date ASC
                ");
                $data['revenue'] = [];
                while ($row = $result->fetch_assoc()) {
                    $data['revenue'][] = $row;
                }
                break;
                
            case 'companies':
                $result = $mysqli->query("
                    SELECT 
                        u.nome,
                        u.email,
                        b.status_documentos,
                        us.status as subscription_status,
                        sp.name as plan_name
                    FROM users u
                    LEFT JOIN businesses b ON u.id = b.user_id
                    LEFT JOIN user_subscriptions us ON u.id = us.user_id
                    LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
                    WHERE u.type = 'company'
                    ORDER BY u.nome ASC
                    LIMIT 50
                ");
                $data['companies'] = [];
                while ($row = $result->fetch_assoc()) {
                    $data['companies'][] = $row;
                }
                break;
                
            case 'transactions':
                $result = $mysqli->query("
                    SELECT 
                        t.invoice_number,
                        t.transaction_date,
                        u.nome as company_name,
                        t.amount,
                        t.status,
                        t.payment_method
                    FROM transactions t
                    LEFT JOIN users u ON t.user_id = u.id
                    WHERE DATE(t.transaction_date) BETWEEN '$dateFrom' AND '$dateTo'
                    ORDER BY t.transaction_date DESC
                    LIMIT 100
                ");
                $data['transactions'] = [];
                while ($row = $result->fetch_assoc()) {
                    $data['transactions'][] = $row;
                }
                break;
        }
    }
    
    return $data;
}

/* ================= GERADORES ================= */

function generateSimplePDF($title, $data) {
    // PDF básico
    $content = "%PDF-1.4\n";
    $content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
    $content .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    
    $text = "BT\n/F1 16 Tf\n100 700 Td\n($title) Tj\nET\n";
    $length = strlen($text);
    
    $content .= "5 0 obj\n<< /Length $length >>\nstream\n$text\nendstream\nendobj\n";
    $content .= "xref\n0 6\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\n0000000262 00000 n\n0000000341 00000 n\n";
    $content .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . strlen($content) . "\n%%EOF";
    
    return $content;
}

function generateSimpleExcel($title, $data) {
    $output = '';
    
    // Header
    $output .= "$title\n\n";
    $output .= "Gerado em: " . date('d/m/Y H:i') . "\n\n";
    
    // Resumo
    if (isset($data['summary'])) {
        $output .= "RESUMO EXECUTIVO\n";
        $output .= "Total de Empresas;" . $data['summary']['companies'] . "\n";
        $output .= "Assinaturas Ativas;" . $data['summary']['active'] . "\n";
        $output .= "Receita Total;" . $data['summary']['revenue'] . "\n";
        $output .= "MRR;" . $data['summary']['mrr'] . "\n\n";
    }
    
    // Empresas
    if (isset($data['companies'])) {
        $output .= "EMPRESAS\n";
        $output .= "Nome;Email;Docs;Status;Plano\n";
        foreach ($data['companies'] as $company) {
            $output .= "{$company['nome']};{$company['email']};{$company['status_documentos']};{$company['subscription_status']};{$company['plan_name']}\n";
        }
    }
    
    return $output;
}

function generateHTMLReport($title, $config, $data) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #238636;
            padding-bottom: 20px;
            margin-bottom: 40px;
        }
        h1 {
            color: #238636;
            margin: 0 0 10px 0;
        }
        .info {
            color: #666;
            font-size: 14px;
        }
        .section {
            margin-bottom: 40px;
        }
        .section-title {
            font-size: 24px;
            color: #238636;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .metric {
            background: #f6f8fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .metric-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #238636;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f6f8fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
        }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($title) . '</h1>
        <p class="info">
            Período: ' . date('d/m/Y', strtotime($config['dateFrom'])) . ' - ' . date('d/m/Y', strtotime($config['dateTo'])) . '<br>
            Gerado em: ' . date('d/m/Y H:i') . '
        </p>
    </div>';
    
    // Resumo
    if (isset($data['summary'])) {
        $html .= '
    <div class="section">
        <h2 class="section-title">Resumo Executivo</h2>
        <div class="metrics">
            <div class="metric">
                <div class="metric-label">Total de Empresas</div>
                <div class="metric-value">' . number_format($data['summary']['companies'], 0) . '</div>
            </div>
            <div class="metric">
                <div class="metric-label">Assinaturas Ativas</div>
                <div class="metric-value">' . number_format($data['summary']['active'], 0) . '</div>
            </div>
            <div class="metric">
                <div class="metric-label">Receita Total</div>
                <div class="metric-value">' . number_format($data['summary']['revenue'], 0) . ' MT</div>
            </div>
            <div class="metric">
                <div class="metric-label">MRR</div>
                <div class="metric-value">' . number_format($data['summary']['mrr'], 0) . ' MT</div>
            </div>
        </div>
    </div>';
    }
    
    // Empresas
    if (isset($data['companies']) && count($data['companies']) > 0) {
        $html .= '
    <div class="section">
        <h2 class="section-title">Empresas</h2>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Documentos</th>
                    <th>Status</th>
                    <th>Plano</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data['companies'] as $company) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($company['nome']) . '</td>
                    <td>' . htmlspecialchars($company['email']) . '</td>
                    <td>' . htmlspecialchars($company['status_documentos'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($company['subscription_status'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($company['plan_name'] ?? 'N/A') . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
    </div>';
    }
    
    // Transações
    if (isset($data['transactions']) && count($data['transactions']) > 0) {
        $html .= '
    <div class="section">
        <h2 class="section-title">Transações</h2>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Data</th>
                    <th>Empresa</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Método</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data['transactions'] as $trans) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($trans['invoice_number']) . '</td>
                    <td>' . date('d/m/Y H:i', strtotime($trans['transaction_date'])) . '</td>
                    <td>' . htmlspecialchars($trans['company_name']) . '</td>
                    <td>' . number_format($trans['amount'], 2) . ' MT</td>
                    <td>' . htmlspecialchars($trans['status']) . '</td>
                    <td>' . htmlspecialchars($trans['payment_method']) . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
    </div>';
    }
    
    $html .= '
    <div class="no-print" style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
        <button onclick="window.print()" style="padding: 12px 24px; background: #238636; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
            Imprimir Relatório
        </button>
    </div>
</body>
</html>';
    
    return $html;
}