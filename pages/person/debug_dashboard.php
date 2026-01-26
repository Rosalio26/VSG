<?php
// debug_dashboard.php - Arquivo de diagnóstico CORRIGIDO
ob_start(); // Inicia buffer de saída para evitar problemas com headers

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicia sessão ANTES de qualquer output
session_start();
?>
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Dashboard Debug - VisionGreen</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0d1117;
            color: #e6edf3;
            padding: 20px;
            line-height: 1.6;
        }
        .debug-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .debug-section {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .debug-section h2 {
            color: #00ff88;
            margin-top: 0;
            border-bottom: 2px solid #00ff88;
            padding-bottom: 10px;
        }
        .success { color: #00ff88; }
        .error { color: #ff6b6b; }
        .warning { color: #ffc107; }
        .info { color: #58a6ff; }
        pre {
            background: #0d1117;
            border: 1px solid #30363d;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 400px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table td, table th {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #30363d;
        }
        table th {
            background: #0d1117;
            color: #00ff88;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-ok { background: rgba(0, 255, 136, 0.2); color: #00ff88; }
        .status-fail { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .status-warn { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .test-btn {
            background: #00ff88;
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
        }
        .test-btn:hover {
            background: #00cc6a;
        }
        #testResults {
            margin-top: 20px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: bold;
        }
        .alert-danger {
            background: rgba(255, 107, 107, 0.2);
            border: 2px solid #ff6b6b;
            color: #ff6b6b;
        }
        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            border: 2px solid #ffc107;
            color: #ffc107;
        }
        .alert-success {
            background: rgba(0, 255, 136, 0.2);
            border: 2px solid #00ff88;
            color: #00ff88;
        }
    </style>
</head>
<body>
<div class='debug-container'>
    <h1 style='color: #00ff88;'>🔍 VisionGreen Dashboard - Diagnóstico Completo</h1>

<?php
// ===================================
// PROBLEMA PRINCIPAL IDENTIFICADO
// ===================================
echo "<div class='alert alert-danger'>
    <h2>⚠️ PROBLEMA IDENTIFICADO!</h2>
    <p><strong>A sessão não está sendo iniciada corretamente porque os headers já foram enviados.</strong></p>
    <p>Isso acontece quando há espaços em branco ou output antes do session_start().</p>
</div>";

// ===================================
// 1. TESTE DE SESSÃO
// ===================================
echo "<div class='debug-section'>
    <h2>1. Estado da Sessão</h2>";

$sessionStatus = session_status();
$sessionNames = [
    PHP_SESSION_DISABLED => '❌ Sessões Desabilitadas',
    PHP_SESSION_NONE => '⚠️ Sessão Não Iniciada',
    PHP_SESSION_ACTIVE => '✅ Sessão Ativa'
];

echo "<table>
    <tr><th>Parâmetro</th><th>Valor</th></tr>
    <tr><td>Status da Sessão</td><td><span class='status-badge status-" . ($sessionStatus === PHP_SESSION_ACTIVE ? 'ok' : 'fail') . "'>" . $sessionNames[$sessionStatus] . "</span></td></tr>
    <tr><td>Session ID</td><td>" . session_id() . "</td></tr>
    <tr><td>Session Name</td><td>" . session_name() . "</td></tr>
</table>";

if (isset($_SESSION['auth'])) {
    echo "<h3 class='success'>✅ Dados de Autenticação Encontrados</h3>";
    echo "<pre>" . htmlspecialchars(print_r($_SESSION['auth'], true)) . "</pre>";
} else {
    echo "<div class='alert alert-warning'>
        <h3>⚠️ Nenhum Dado de Autenticação na Sessão</h3>
        <p>Para corrigir:</p>
        <ol>
            <li>Faça login no sistema primeiro</li>
            <li>Depois acesse este debug novamente</li>
            <li>Ou teste os endpoints manualmente depois de autenticado</li>
        </ol>
    </div>";
}

echo "</div>";

// ===================================
// 2. TESTE DE BANCO DE DADOS
// ===================================
echo "<div class='debug-section'>
    <h2>2. Conexão com Banco de Dados</h2>";

try {
    require_once '../../registration/includes/db.php';
    
    if ($mysqli->connect_error) {
        throw new Exception("Erro de conexão: " . $mysqli->connect_error);
    }
    
    echo "<p class='success'>✅ Conexão estabelecida com sucesso!</p>";
    echo "<table>
        <tr><td>Host</td><td>" . $mysqli->host_info . "</td></tr>
        <tr><td>Charset</td><td>" . $mysqli->character_set_name() . "</td></tr>
        <tr><td>Server Version</td><td>" . $mysqli->server_info . "</td></tr>
    </table>";
    
    // Testar query simples
    $result = $mysqli->query("SELECT COUNT(*) as total FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p class='info'>📊 Total de usuários cadastrados: <strong>" . $row['total'] . "</strong></p>";
    }
    
    // Testar tabelas necessárias
    $tables = ['users', 'products', 'orders', 'notifications', 'order_items'];
    echo "<h3>Verificação de Tabelas</h3><table><tr><th>Tabela</th><th>Status</th><th>Registros</th></tr>";
    
    foreach ($tables as $table) {
        $check = $mysqli->query("SHOW TABLES LIKE '$table'");
        $exists = $check && $check->num_rows > 0;
        
        $count = 0;
        if ($exists) {
            $countResult = $mysqli->query("SELECT COUNT(*) as total FROM $table");
            if ($countResult) {
                $count = $countResult->fetch_assoc()['total'];
            }
        }
        
        echo "<tr>
            <td>$table</td>
            <td><span class='status-badge status-" . ($exists ? 'ok' : 'fail') . "'>" . ($exists ? '✅ Existe' : '❌ Não Encontrada') . "</span></td>
            <td>" . ($exists ? $count : '-') . "</td>
        </tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "</div>";

// ===================================
// 3. ANÁLISE DE ARQUIVOS PHP
// ===================================
echo "<div class='debug-section'>
    <h2>3. Análise de Arquivos Críticos</h2>";

$filesToCheck = [
    'dashboard_person.php' => 'dashboard_person.php',
    'get_products.php' => 'actions/get_products.php',
    'get_stats.php' => 'actions/get_stats.php'
];

echo "<h3>Procurando por espaços em branco antes de &lt;?php</h3>";

foreach ($filesToCheck as $name => $path) {
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $firstChars = substr($content, 0, 5);
        $hasWhitespace = $firstChars !== '<?php';
        
        echo "<div style='margin: 10px 0; padding: 10px; background: " . ($hasWhitespace ? 'rgba(255, 107, 107, 0.1)' : 'rgba(0, 255, 136, 0.1)') . "; border-radius: 6px;'>";
        echo "<strong>$name:</strong> ";
        
        if ($hasWhitespace) {
            echo "<span class='error'>❌ TEM ESPAÇOS/CARACTERES ANTES DE &lt;?php</span><br>";
            echo "<small>Primeiros 10 bytes: " . htmlspecialchars(bin2hex(substr($content, 0, 10))) . "</small>";
        } else {
            echo "<span class='success'>✅ OK - Inicia corretamente com &lt;?php</span>";
        }
        echo "</div>";
    }
}

echo "</div>";

// ===================================
// 4. TESTE DE PRODUTOS
// ===================================
if (isset($mysqli)) {
    echo "<div class='debug-section'>
        <h2>4. Diagnóstico de Produtos</h2>";
    
    $result = $mysqli->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
            SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as nao_deletados,
            SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as com_estoque
        FROM products
    ");
    
    if ($result) {
        $stats = $result->fetch_assoc();
        
        if ($stats['total'] == 0) {
            echo "<div class='alert alert-warning'>
                ⚠️ Não há produtos cadastrados no sistema!
            </div>";
        } else {
            echo "<table>
                <tr><td>Total de Produtos</td><td><strong>" . $stats['total'] . "</strong></td></tr>
                <tr><td>Produtos Ativos</td><td><strong>" . $stats['ativos'] . "</strong></td></tr>
                <tr><td>Não Deletados</td><td><strong>" . $stats['nao_deletados'] . "</strong></td></tr>
                <tr><td>Com Estoque</td><td><strong>" . $stats['com_estoque'] . "</strong></td></tr>
            </table>";
            
            // Produtos que deveriam aparecer
            $produtosVisiveis = $mysqli->query("
                SELECT p.id, p.nome, p.preco, p.stock, p.status, u.nome as empresa, u.status as empresa_status
                FROM products p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.deleted_at IS NULL 
                  AND p.status = 'ativo'
                  AND u.status = 'active'
                LIMIT 5
            ");
            
            echo "<h3>Produtos Visíveis (5 primeiros que aparecem no dashboard):</h3>";
            
            if ($produtosVisiveis && $produtosVisiveis->num_rows > 0) {
                echo "<table>
                    <tr><th>ID</th><th>Nome</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Empresa</th><th>Status Empresa</th></tr>";
                
                while ($p = $produtosVisiveis->fetch_assoc()) {
                    echo "<tr>
                        <td>{$p['id']}</td>
                        <td>{$p['nome']}</td>
                        <td>" . number_format($p['preco'], 2) . " MZN</td>
                        <td>{$p['stock']}</td>
                        <td><span class='status-badge status-ok'>{$p['status']}</span></td>
                        <td>{$p['empresa']}</td>
                        <td><span class='status-badge status-ok'>{$p['empresa_status']}</span></td>
                    </tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='alert alert-danger'>
                    ❌ Nenhum produto corresponde aos critérios de exibição!<br>
                    Verifique se os produtos têm empresa ativa associada.
                </div>";
            }
        }
    }
    
    echo "</div>";
}

// ===================================
// SOLUÇÕES E PRÓXIMOS PASSOS
// ===================================
echo "<div class='debug-section'>
    <h2>🔧 Soluções para o Problema</h2>
    
    <div class='alert alert-success'>
        <h3>✅ Como Corrigir o Processamento Infinito:</h3>
        <ol>
            <li><strong>Verificar espaços em branco:</strong> Certifique-se que TODOS os arquivos PHP começam imediatamente com <code>&lt;?php</code> sem nenhum espaço antes</li>
            <li><strong>Remover UTF-8 BOM:</strong> Use um editor como VS Code e salve os arquivos como 'UTF-8 without BOM'</li>
            <li><strong>Reduzir intervalo de atualização:</strong> Já corrigimos para 10 segundos (10000ms) em vez de 1 segundo</li>
            <li><strong>Limpar cache do navegador:</strong> Pressione Ctrl+Shift+Delete e limpe tudo</li>
            <li><strong>Testar sem módulos:</strong> Comente temporariamente as funções de auto-update para ver se o problema persiste</li>
        </ol>
    </div>
    
    <div class='alert alert-warning'>
        <h3>⚠️ Pontos de Atenção:</h3>
        <ul>
            <li>O arquivo <code>dashboard_person.php</code> tem <strong>46,961 bytes</strong> - muito código inline</li>
            <li>Cada módulo carregado (notificações, pedidos, carrinho) adiciona seus próprios scripts</li>
            <li>Múltiplos setInterval podem estar rodando simultaneamente</li>
        </ul>
    </div>
</div>";

// ===================================
// 6. TESTES AJAX
// ===================================
echo "<div class='debug-section'>
    <h2>6. Testes de Endpoints AJAX</h2>
    <p>⚠️ <strong>IMPORTANTE:</strong> Faça login primeiro, depois volte aqui e teste!</p>
    
    <button class='test-btn' onclick='testEndpoint(\"actions/get_products.php\")'>Testar get_products.php</button>
    <button class='test-btn' onclick='testEndpoint(\"actions/get_stats.php\")'>Testar get_stats.php</button>
    <button class='test-btn' onclick='testEndpoint(\"actions/get_notifications.php\")'>Testar get_notifications.php</button>
    
    <div id='testResults'></div>
</div>";

?>

<script>
async function testEndpoint(endpoint) {
    const resultsDiv = document.getElementById('testResults');
    resultsDiv.innerHTML = '<p style="color: #ffc107;">⏳ Testando ' + endpoint + '...</p>';
    
    try {
        const startTime = performance.now();
        const response = await fetch(endpoint);
        const endTime = performance.now();
        const duration = (endTime - startTime).toFixed(2);
        
        const contentType = response.headers.get('content-type');
        let data;
        
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            data = { error: 'Resposta não é JSON', response: text.substring(0, 500) };
        }
        
        let statusClass = response.ok ? 'success' : 'error';
        
        resultsDiv.innerHTML = `
            <h3 class="${statusClass}">
                ${response.ok ? '✅' : '❌'} Resposta de ${endpoint}
            </h3>
            <table>
                <tr><td>Status HTTP</td><td><span class="status-badge status-${response.ok ? 'ok' : 'fail'}">${response.status} ${response.statusText}</span></td></tr>
                <tr><td>Content-Type</td><td>${contentType || 'N/A'}</td></tr>
                <tr><td>Tempo de Resposta</td><td>${duration}ms</td></tr>
                <tr><td>Success</td><td>${data.success ? '✅ Sim' : '❌ Não'}</td></tr>
            </table>
            <h4>Dados Retornados:</h4>
            <pre>${JSON.stringify(data, null, 2)}</pre>
        `;
    } catch (error) {
        resultsDiv.innerHTML = `
            <h3 class="error">❌ Erro ao testar ${endpoint}</h3>
            <pre style="color: #ff6b6b;">${error.message}\n\n${error.stack}</pre>
        `;
    }
}

console.log('%c🔍 Debug Dashboard Carregado', 'color: #00ff88; font-size: 20px; font-weight: bold;');
console.log('Use os botões acima para testar os endpoints após fazer login.');
</script>

</div>
</body>
</html>