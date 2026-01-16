<?php
/**
 * ================================================================================
 * DIAGNÓSTICO DE UPLOADS - VisionGreen
 * Arquivo: test_uploads.php
 * 
 * INSTRUÇÕES:
 * 1. Coloque este arquivo no mesmo diretório que analise.php
 * 2. Acesse via navegador: http://seusite.com/admin/modules/dashboard/test_uploads.php
 * 3. Veja os resultados e identifique o caminho correto
 * ================================================================================
 */

// Evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Uploads</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #2c3e50; margin-top: 0; }
        h2 { color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 8px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { background: #ecf0f1; padding: 12px; border-radius: 4px; margin: 12px 0; }
        code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Courier New", monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        th {
            background: #34495e;
            color: white;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .path-test {
            margin: 8px 0;
            padding: 8px;
            border-left: 3px solid #95a5a6;
            background: #ecf0f1;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>🔍 Diagnóstico de Uploads - VisionGreen</h1>
    <p>Este arquivo verifica os caminhos e permissões dos arquivos de upload.</p>
</div>

<?php
// ==================================================
// 1. INFORMAÇÕES DO SERVIDOR
// ==================================================
?>
<div class="card">
    <h2>📊 Informações do Servidor</h2>
    <table>
        <tr>
            <th>Item</th>
            <th>Valor</th>
        </tr>
        <tr>
            <td><strong>Diretório Atual</strong></td>
            <td><code><?= __DIR__ ?></code></td>
        </tr>
        <tr>
            <td><strong>Arquivo Atual</strong></td>
            <td><code><?= __FILE__ ?></code></td>
        </tr>
        <tr>
            <td><strong>Document Root</strong></td>
            <td><code><?= $_SERVER['DOCUMENT_ROOT'] ?? 'N/A' ?></code></td>
        </tr>
        <tr>
            <td><strong>PHP Version</strong></td>
            <td><?= PHP_VERSION ?></td>
        </tr>
    </table>
</div>

<?php
// ==================================================
// 2. TESTAR CAMINHOS POSSÍVEIS
// ==================================================

$possiblePaths = [
    "../../../registration/uploads/business/",
    "../../../../registration/uploads/business/",
    "../../registration/uploads/business/",
    "../registration/uploads/business/",
    "registration/uploads/business/",
    $_SERVER['DOCUMENT_ROOT'] . "/registration/uploads/business/",
];

?>
<div class="card">
    <h2>🗂️ Teste de Caminhos Possíveis</h2>
    <p>Testando caminhos relativos a partir de: <code><?= __DIR__ ?></code></p>
    
    <?php foreach ($possiblePaths as $index => $path): ?>
        <?php
        $fullPath = __DIR__ . '/' . $path;
        $exists = is_dir($fullPath);
        $readable = $exists && is_readable($fullPath);
        ?>
        <div class="path-test">
            <strong>Teste <?= $index + 1 ?>:</strong> <code><?= htmlspecialchars($path) ?></code><br>
            <strong>Caminho Completo:</strong> <code><?= htmlspecialchars($fullPath) ?></code><br>
            <strong>Existe:</strong> <?= $exists ? '<span class="success">✅ SIM</span>' : '<span class="error">❌ NÃO</span>' ?><br>
            <strong>Legível:</strong> <?= $readable ? '<span class="success">✅ SIM</span>' : '<span class="error">❌ NÃO</span>' ?>
            
            <?php if ($exists && $readable): ?>
                <br><strong>Arquivos dentro:</strong>
                <?php
                $files = array_diff(scandir($fullPath), ['.', '..']);
                if (count($files) > 0) {
                    echo '<ul style="margin: 8px 0;">';
                    $count = 0;
                    foreach ($files as $file) {
                        if ($count >= 5) {
                            echo '<li>... e mais ' . (count($files) - 5) . ' arquivos</li>';
                            break;
                        }
                        $fileSize = filesize($fullPath . $file);
                        echo '<li>' . htmlspecialchars($file) . ' <small>(' . number_format($fileSize / 1024, 2) . ' KB)</small></li>';
                        $count++;
                    }
                    echo '</ul>';
                    echo '<div class="success">✅ <strong>ESTE CAMINHO FUNCIONA!</strong> Use: <code>' . htmlspecialchars($path) . '</code></div>';
                } else {
                    echo '<br><span class="warning">⚠️ Diretório vazio</span>';
                }
                ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
// ==================================================
// 3. BUSCAR EMPRESAS NO BANCO (se conexão existir)
// ==================================================

$dbConfigPaths = [
    '../../../../registration/includes/db.php',
    '../../../registration/includes/db.php',
    '../../registration/includes/db.php',
];

$dbLoaded = false;
foreach ($dbConfigPaths as $dbPath) {
    if (file_exists(__DIR__ . '/' . $dbPath)) {
        require_once __DIR__ . '/' . $dbPath;
        $dbLoaded = true;
        break;
    }
}

if ($dbLoaded && isset($mysqli) && $mysqli->connect_errno === 0):
?>
<div class="card">
    <h2>💾 Empresas no Banco de Dados</h2>
    <?php
    $sql = "SELECT user_id, license_path, status_documentos FROM businesses WHERE license_path IS NOT NULL LIMIT 10";
    $result = $mysqli->query($sql);
    
    if ($result && $result->num_rows > 0):
    ?>
    <table>
        <thead>
            <tr>
                <th>User ID</th>
                <th>Caminho no Banco</th>
                <th>Status</th>
                <th>Teste de Arquivo</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['user_id'] ?></td>
                <td><code><?= htmlspecialchars($row['license_path']) ?></code></td>
                <td><?= htmlspecialchars($row['status_documentos']) ?></td>
                <td>
                    <?php
                    $found = false;
                    $correctPath = '';
                    foreach ($possiblePaths as $testPath) {
                        $fullTestPath = __DIR__ . '/' . $testPath . $row['license_path'];
                        if (file_exists($fullTestPath)) {
                            $found = true;
                            $correctPath = $testPath;
                            break;
                        }
                    }
                    if ($found) {
                        echo '<span class="success">✅ Encontrado</span><br>';
                        echo '<small>Caminho: <code>' . htmlspecialchars($correctPath) . '</code></small>';
                    } else {
                        echo '<span class="error">❌ Não encontrado</span>';
                    }
                    ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="warning">⚠️ Nenhuma empresa com documentos encontrada no banco.</p>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <h2>💾 Banco de Dados</h2>
    <p class="error">❌ Não foi possível conectar ao banco de dados.</p>
    <p>Certifique-se de que o arquivo de configuração existe em um destes caminhos:</p>
    <ul>
        <?php foreach ($dbConfigPaths as $path): ?>
        <li><code><?= htmlspecialchars($path) ?></code></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php
// ==================================================
// 4. RECOMENDAÇÕES
// ==================================================
?>
<div class="card">
    <h2>💡 Recomendações</h2>
    
    <div class="info">
        <strong>Como usar este diagnóstico:</strong>
        <ol>
            <li>Procure por <span class="success">✅ ESTE CAMINHO FUNCIONA!</span> na seção "Teste de Caminhos"</li>
            <li>Copie o caminho que funcionou</li>
            <li>No arquivo <code>analise.php</code>, substitua a linha <code>$uploadPath = "..."</code> pelo caminho correto</li>
            <li>Se vários caminhos funcionarem, use o mais curto (relativo)</li>
        </ol>
    </div>
    
    <div class="info">
        <strong>Estrutura esperada do projeto:</strong>
        <pre style="background: #2c3e50; color: #ecf0f1; padding: 12px; border-radius: 4px; overflow-x: auto;">
projeto/
├── admin/
│   └── modules/
│       └── dashboard/
│           ├── analise.php          ← Arquivo que precisa dos uploads
│           └── test_uploads.php     ← Este arquivo
└── registration/
    └── uploads/
        └── business/
            ├── documento1.pdf
            ├── documento2.jpg
            └── ...
        </pre>
    </div>
    
    <div class="info">
        <strong>Problemas comuns:</strong>
        <ul>
            <li><strong>Todos os caminhos dão erro:</strong> Verifique se a pasta <code>registration/uploads/business/</code> existe</li>
            <li><strong>Diretório vazio:</strong> Certifique-se de que há arquivos na pasta</li>
            <li><strong>Arquivo encontrado mas não exibe:</strong> Verifique permissões (deve ser 755 para diretórios, 644 para arquivos)</li>
            <li><strong>PDF não carrega no navegador:</strong> Alguns navegadores bloqueiam PDFs de origens locais. Use HTTPS ou servidor web adequado</li>
        </ul>
    </div>
</div>

<div class="card">
    <h2>🎯 Próximos Passos</h2>
    <ol>
        <li>Identifique o caminho correto acima (marcado com ✅)</li>
        <li>Atualize a variável <code>$uploadPath</code> em <code>analise.php</code></li>
        <li>Teste a visualização de documentos</li>
        <li><strong>Delete este arquivo após o diagnóstico</strong> (por segurança)</li>
    </ol>
</div>

<div style="text-align: center; padding: 20px; color: #7f8c8d;">
    <small>Diagnóstico gerado em <?= date('d/m/Y H:i:s') ?></small>
</div>

</body>
</html>