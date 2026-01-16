<?php
/**
 * ================================================================================
 * VISIONGREEN - VERIFICAÇÃO DE PRODUTO ECOLÓGICO (IA)
 * Arquivo: company/modules/produtos/actions/verificar_produto.php
 * Descrição: Analisa se o produto atende aos critérios de sustentabilidade
 * ================================================================================
 */

// Limpar buffer de saída para garantir JSON puro
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

// Desabilitar exibição de erros no output
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

// Conectar ao banco
$db_paths = [
    __DIR__ . '/../../../../../registration/includes/db.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/registration/includes/db.php'
];

$db_connected = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected || !isset($mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão']);
    exit;
}

// Envolver toda lógica em try-catch
try {
    // Validar dados
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $eco_category = $_POST['eco_category'] ?? '';
    $environmental_impact = trim($_POST['environmental_impact'] ?? '');

    if (empty($name) || empty($description) || empty($category) || empty($eco_category)) {
        throw new Exception('Campos obrigatórios não preenchidos');
    }

// ==================== ANÁLISE DE SUSTENTABILIDADE (IA SIMULADA) ====================

$score = 0;
$reasons = [];
$warnings = [];

// 1. Análise de Categoria Ecológica (peso: 25%)
$high_eco_categories = ['recyclable', 'reusable', 'biodegradable', 'organic', 'zero_waste'];
$medium_eco_categories = ['sustainable', 'energy_efficient'];

if (in_array($eco_category, $high_eco_categories)) {
    $score += 2.5;
    $reasons[] = "Categoria ecológica de alto impacto: " . ucfirst($eco_category) . " (+2.5)";
} elseif (in_array($eco_category, $medium_eco_categories)) {
    $score += 1.5;
    $reasons[] = "Categoria ecológica validada: " . ucfirst($eco_category) . " (+1.5)";
} else {
    $warnings[] = "Categoria ecológica não reconhecida";
}

// 2. Análise de Descrição (peso: 20%)
$eco_keywords = [
    'sustentável', 'ecológico', 'reciclado', 'biodegradável', 'orgânico', 
    'renovável', 'reutilizável', 'verde', 'eco-friendly', 'carbono neutro',
    'energia limpa', 'zero desperdício', 'compostável', 'natural', 'bamboo'
];

$keywords_found = 0;
$description_lower = mb_strtolower($description);

foreach ($eco_keywords as $keyword) {
    if (mb_strpos($description_lower, $keyword) !== false) {
        $keywords_found++;
    }
}

if ($keywords_found >= 3) {
    $score += 2.0;
    $reasons[] = "Descrição rica em termos sustentáveis (+2.0)";
} elseif ($keywords_found >= 1) {
    $score += 1.0;
    $reasons[] = "Descrição menciona sustentabilidade (+1.0)";
} else {
    $warnings[] = "Descrição não enfatiza aspectos ecológicos";
}

// 3. Características Sustentáveis (peso: 30%)
$sustainable_features = 0;

if (isset($_POST['biodegradable']) && $_POST['biodegradable'] === 'on') {
    $sustainable_features++;
    $reasons[] = "Produto biodegradável (+0.75)";
}

if (isset($_POST['renewable_materials']) && $_POST['renewable_materials'] === 'on') {
    $sustainable_features++;
    $reasons[] = "Materiais renováveis (+0.75)";
}

if (isset($_POST['water_efficient']) && $_POST['water_efficient'] === 'on') {
    $sustainable_features++;
    $reasons[] = "Economiza água (+0.75)";
}

if (isset($_POST['energy_efficient']) && $_POST['energy_efficient'] === 'on') {
    $sustainable_features++;
    $reasons[] = "Economiza energia (+0.75)";
}

$score += ($sustainable_features * 0.75);

// 4. Percentual Reciclável (peso: 10%)
$recyclable = floatval($_POST['recyclable_percentage'] ?? 0);
if ($recyclable >= 80) {
    $score += 1.0;
    $reasons[] = "Alto percentual reciclável ({$recyclable}%) (+1.0)";
} elseif ($recyclable >= 50) {
    $score += 0.5;
    $reasons[] = "Bom percentual reciclável ({$recyclable}%) (+0.5)";
}

// 5. Pegada de Carbono (peso: 10%)
$carbon = floatval($_POST['carbon_footprint'] ?? 0);
if ($carbon > 0 && $carbon < 5) {
    $score += 1.0;
    $reasons[] = "Baixa pegada de carbono ({$carbon}kg CO2) (+1.0)";
} elseif ($carbon < 10) {
    $score += 0.5;
    $reasons[] = "Pegada de carbono moderada ({$carbon}kg CO2) (+0.5)";
} elseif ($carbon > 20) {
    $warnings[] = "Pegada de carbono alta ({$carbon}kg CO2)";
}

// 6. Certificação Ecológica (peso: 10%)
$certification = $_POST['eco_certification'] ?? '';
if (!empty($certification) && $certification !== 'none') {
    $score += 1.0;
    $reasons[] = "Possui certificação ecológica reconhecida (+1.0)";
}

// 7. Impacto Ambiental Positivo (peso: 10%)
if (!empty($environmental_impact) && strlen($environmental_impact) > 50) {
    $score += 1.0;
    $reasons[] = "Descrição detalhada de impacto positivo (+1.0)";
}

// 8. Análise de Palavras Proibidas (Greenwashing)
$prohibited_terms = [
    'plástico descartável', 'uso único', 'não reciclável', 'petróleo', 
    'poluente', 'tóxico', 'químico nocivo', 'combustível fóssil'
];

$greenwashing_detected = false;
foreach ($prohibited_terms as $term) {
    if (mb_strpos($description_lower, $term) !== false) {
        $greenwashing_detected = true;
        $warnings[] = "Termo preocupante detectado: '{$term}'";
        $score -= 2.0;
    }
}

// ==================== DECISÃO FINAL ====================

$score = max(0, min(10, $score)); // Limitar entre 0 e 10

$analysis = [
    'score' => round($score, 2),
    'reasons' => $reasons,
    'warnings' => $warnings,
    'eco_rating' => $score >= 7 ? 'excellent' : ($score >= 5 ? 'good' : ($score >= 3 ? 'fair' : 'poor')),
    'timestamp' => date('Y-m-d H:i:s')
];

// Critérios de aprovação
$approved = $score >= 5.0 && !$greenwashing_detected;

if ($approved) {
    $message = "Produto aprovado! Score de sustentabilidade: " . $analysis['score'] . "/10";
    
    $analysis_text = "Análise: O produto demonstra bom compromisso com sustentabilidade. ";
    $analysis_text .= implode('. ', array_slice($reasons, 0, 3)) . ".";
    
    if (count($warnings) > 0) {
        $analysis_text .= " Observações: " . implode('; ', $warnings) . ".";
    }
    
    echo json_encode([
        'success' => true,
        'approved' => true,
        'score' => $analysis['score'],
        'rating' => $analysis['eco_rating'],
        'analysis' => $analysis_text,
        'full_analysis' => $analysis,
        'message' => $message
    ]);
} else {
    $rejection_reasons = [];
    
    if ($score < 5.0) {
        $rejection_reasons[] = "Score de sustentabilidade abaixo do mínimo (5.0)";
    }
    
    if ($greenwashing_detected) {
        $rejection_reasons[] = "Possível greenwashing detectado";
    }
    
    if (count($reasons) < 2) {
        $rejection_reasons[] = "Insuficientes características ecológicas";
    }
    
    $message = "Produto não atende aos padrões VisionGreen. ";
    $message .= implode('. ', $rejection_reasons);
    
    if (count($warnings) > 0) {
        $message .= ". Problemas detectados: " . implode('; ', $warnings);
    }
    
    echo json_encode([
        'success' => false,
        'approved' => false,
        'score' => $analysis['score'],
        'rating' => $analysis['eco_rating'],
        'full_analysis' => $analysis,
        'message' => $message
    ]);
}

// Registrar tentativa de verificação (DESABILITADO - tabela opcional)
// Descomente se tiver criado a tabela product_verification_history
/*
try {
    $log_stmt = $mysqli->prepare("
        INSERT INTO product_verification_history 
        (product_id, user_id, status, verification_score, ai_analysis, notes) 
        VALUES (NULL, ?, 'submitted', ?, ?, ?)
    ");

    if ($log_stmt) {
        $analysis_json = json_encode($analysis);
        $notes = $approved ? 'Pre-verificação aprovada' : 'Pre-verificação rejeitada';
        
        $log_stmt->bind_param("ids", $userId, $score, $analysis_json, $notes);
        $log_stmt->execute();
        $log_stmt->close();
    }
} catch (Exception $log_error) {
    // Ignorar erro de log, não é crítico
}
*/

} catch (Exception $e) {
    // Capturar qualquer erro e retornar JSON
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar: ' . $e->getMessage()
    ]);
    exit;
}