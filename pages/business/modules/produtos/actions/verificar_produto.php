<?php
/**
 * ================================================================================
 * VISIONGREEN - VERIFICAÇÃO DE PRODUTO COM IA REAL
 * Arquivo: company/modules/produtos/actions/verificar_produto.php
 * Versão: 2.0 - Com Claude Vision AI + Fallback Simulado
 * ================================================================================
 */

// Limpar buffer de saída
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
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
    __DIR__ . '/../../../../../registration/includes/db.php',  // 6 níveis - WINDOWS
    __DIR__ . '/../../../../registration/includes/db.php',     // 5 níveis - LINUX
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

try {
    // ==================== VALIDAR DADOS ====================
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $eco_category = $_POST['eco_category'] ?? '';
    $environmental_impact = trim($_POST['environmental_impact'] ?? '');

    if (empty($name) || empty($description) || empty($category) || empty($eco_category)) {
        throw new Exception('Campos obrigatórios não preenchidos');
    }

    // Verificar imagem
    if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Imagem é obrigatória para verificação');
    }

    // ==================== CONFIGURAÇÃO IA ====================
    // Buscar API key de diferentes fontes
    $ANTHROPIC_API_KEY = null;
    
    // 1. Variável de ambiente
    if (getenv('ANTHROPIC_API_KEY')) {
        $ANTHROPIC_API_KEY = getenv('ANTHROPIC_API_KEY');
    }
    
    // 2. Arquivo de configuração (se existir)
    $config_file = __DIR__ . '/../../../../config/anthropic_key.txt';
    if (file_exists($config_file)) {
        $ANTHROPIC_API_KEY = trim(file_get_contents($config_file));
    }
    
    // 3. Constante no código (para teste rápido)
    // Descomente e adicione sua key aqui:
    // $ANTHROPIC_API_KEY = 'sk-ant-api03-...';

    // ==================== DECIDIR: IA REAL OU SIMULADA ====================
    $use_real_ai = !empty($ANTHROPIC_API_KEY) && $ANTHROPIC_API_KEY !== 'sua-api-key-aqui';

    if ($use_real_ai) {
        // ==================== ANÁLISE COM IA REAL ====================
        $result = analyzeWithRealAI($ANTHROPIC_API_KEY, $_FILES['product_image'], $_POST);
        echo json_encode($result);
    } else {
        // ==================== ANÁLISE SIMULADA ====================
        $result = analyzeWithSimulation($_POST);
        echo json_encode($result);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar: ' . $e->getMessage()
    ]);
    exit;
}

// ==================== FUNÇÃO: IA REAL ====================
function analyzeWithRealAI($api_key, $image_file, $data) {
    try {
        // Preparar imagem
        $image_data = file_get_contents($image_file['tmp_name']);
        $image_base64 = base64_encode($image_data);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $image_file['tmp_name']);
        finfo_close($finfo);

        // Construir prompt
        $prompt = buildAIPrompt($data);

        // Chamar API
        $api_data = [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mime_type,
                                'data' => $image_base64
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01',
            'x-api-key: ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Erro na API Claude: HTTP ' . $http_code . ' - ' . $curl_error);
        }

        $api_response = json_decode($response, true);
        
        if (!isset($api_response['content'][0]['text'])) {
            throw new Exception('Resposta inválida da API');
        }

        // Extrair JSON da resposta
        $ai_text = $api_response['content'][0]['text'];
        
        // Remover markdown se houver
        $ai_text = preg_replace('/```json\s*/', '', $ai_text);
        $ai_text = preg_replace('/```\s*$/', '', $ai_text);
        $ai_text = trim($ai_text);
        
        // Extrair JSON
        if (preg_match('/\{[\s\S]*\}/', $ai_text, $matches)) {
            $ai_result = json_decode($matches[0], true);
        } else {
            $ai_result = json_decode($ai_text, true);
        }

        if (!$ai_result) {
            throw new Exception('Não foi possível parsear resposta da IA');
        }

        // Retornar resultado formatado
        $approved = ($ai_result['approved'] ?? false) && ($ai_result['score'] ?? 0) >= 5.0;
        $score = floatval($ai_result['score'] ?? 0);

        return [
            'success' => $approved,
            'approved' => $approved,
            'score' => $score,
            'rating' => $ai_result['rating'] ?? 'unknown',
            'analysis' => $ai_result['analysis'] ?? 'Análise concluída',
            'image_analysis' => $ai_result['image_analysis'] ?? '',
            'coherence' => $ai_result['coherence'] ?? '',
            'greenwashing_detected' => $ai_result['greenwashing_detected'] ?? false,
            'reasons' => $ai_result['reasons'] ?? [],
            'warnings' => $ai_result['warnings'] ?? [],
            'full_analysis' => $ai_result,
            'ai_powered' => true,
            'ai_type' => 'real',
            'message' => $approved ? 
                "✅ Produto aprovado pela IA! Score: {$score}/10" : 
                "❌ " . ($ai_result['analysis'] ?? 'Produto não atende aos critérios ecológicos')
        ];

    } catch (Exception $e) {
        // Se IA falhar, usar simulação como fallback
        error_log("IA Real falhou: " . $e->getMessage());
        $result = analyzeWithSimulation($data);
        $result['ai_type'] = 'simulated_fallback';
        $result['ai_error'] = $e->getMessage();
        return $result;
    }
}

// ==================== FUNÇÃO: PROMPT PARA IA ====================
function buildAIPrompt($data) {
    $name = $data['name'] ?? '';
    $eco_category = $data['eco_category'] ?? '';
    $description = $data['description'] ?? '';
    $environmental_impact = $data['environmental_impact'] ?? '';
    
    $biodegradable = isset($data['biodegradable']) ? 'Sim' : 'Não';
    $renewable = isset($data['renewable_materials']) ? 'Sim' : 'Não';
    $water = isset($data['water_efficient']) ? 'Sim' : 'Não';
    $energy = isset($data['energy_efficient']) ? 'Sim' : 'Não';
    $recyclable = $data['recyclable_percentage'] ?? 'Não informado';
    $carbon = $data['carbon_footprint'] ?? 'Não informado';

    return "Você é um especialista em sustentabilidade da VisionGreen, uma plataforma que só aceita produtos REALMENTE ecológicos.

🎯 MISSÃO: Analisar se este produto é genuinamente ecológico ou se é greenwashing (marketing enganoso).

📦 PRODUTO:
Nome: {$name}
Categoria Declarada: {$eco_category}

📝 DESCRIÇÃO:
{$description}

🌍 IMPACTO AMBIENTAL DECLARADO:
{$environmental_impact}

✅ CARACTERÍSTICAS DECLARADAS:
• Biodegradável: {$biodegradable}
• Materiais Renováveis: {$renewable}
• Economiza Água: {$water}
• Economiza Energia: {$energy}
• Percentual Reciclável: {$recyclable}%
• Pegada de Carbono: {$carbon} kg CO2

🔍 INSTRUÇÕES DE ANÁLISE:

1. **ANÁLISE DA IMAGEM** (peso 40%):
   - O produto É VISUALMENTE ecológico?
   - Materiais aparentam ser sustentáveis?
   - Há sinais de durabilidade/reutilização?
   - Detecta plástico descartável, embalagem excessiva?

2. **COERÊNCIA** (peso 30%):
   - A imagem CONFIRMA a descrição?
   - Se diz 'bambu', mostra bambu?
   - Se diz 'reutilizável', aparenta durável?

3. **GREENWASHING** (peso 20%):
   - Produto tentando se passar por ecológico?
   - Marketing enganoso detectado?
   - Contradições entre visual e texto?

4. **IMPACTO REAL** (peso 10%):
   - Produto realmente ajuda o meio ambiente?
   - Substitui alternativa poluente?

⚠️ SEJA RIGOROSO:
• Plástico descartável = REJEITAR
• Produto convencional com logo verde = REJEITAR  
• Contradição imagem/texto = REJEITAR
• Score mínimo para aprovar: 5.0/10

📊 RESPONDA EM JSON PURO (sem markdown, sem ```):
{
  \"approved\": true/false,
  \"score\": 0.0,
  \"rating\": \"excellent\",
  \"analysis\": \"Explicação em português de 2-3 frases\",
  \"image_analysis\": \"O que você viu na imagem em 1-2 frases\",
  \"coherence\": \"A descrição é coerente com a imagem? Sim/Não e por quê\",
  \"greenwashing_detected\": false,
  \"reasons\": [\"motivo 1\", \"motivo 2\", \"motivo 3\"],
  \"warnings\": [\"aviso se houver\"]
}

LEMBRE-SE: Só aprove produtos REALMENTE ecológicos. A VisionGreen confia em você!";
}

// ==================== FUNÇÃO: IA SIMULADA ====================
function analyzeWithSimulation($data) {
    $score = 0;
    $reasons = [];
    $warnings = [];
    
    $eco_category = $data['eco_category'] ?? '';
    $description = mb_strtolower($data['description'] ?? '');
    $environmental_impact = $data['environmental_impact'] ?? '';

    // 1. Categoria (25%)
    $high_eco = ['recyclable', 'reusable', 'biodegradable', 'organic', 'zero_waste'];
    $medium_eco = ['sustainable', 'energy_efficient'];
    
    if (in_array($eco_category, $high_eco)) {
        $score += 2.5;
        $reasons[] = "Categoria ecológica de alto impacto (+2.5)";
    } elseif (in_array($eco_category, $medium_eco)) {
        $score += 1.5;
        $reasons[] = "Categoria ecológica validada (+1.5)";
    }

    // 2. Palavras-chave (20%)
    $keywords = ['sustentável', 'ecológico', 'reciclado', 'biodegradável', 'orgânico', 
                 'renovável', 'reutilizável', 'verde', 'eco-friendly', 'carbono neutro',
                 'energia limpa', 'zero desperdício', 'compostável', 'natural', 'bamboo'];
    
    $found = 0;
    foreach ($keywords as $kw) {
        if (mb_strpos($description, $kw) !== false) $found++;
    }
    
    if ($found >= 3) {
        $score += 2.0;
        $reasons[] = "Descrição rica em termos sustentáveis (+2.0)";
    } elseif ($found >= 1) {
        $score += 1.0;
        $reasons[] = "Descrição menciona sustentabilidade (+1.0)";
    } else {
        $warnings[] = "Descrição não enfatiza aspectos ecológicos";
    }

    // 3. Características (30%)
    $features = 0;
    if (isset($data['biodegradable'])) { $features++; $reasons[] = "Biodegradável (+0.75)"; }
    if (isset($data['renewable_materials'])) { $features++; $reasons[] = "Materiais renováveis (+0.75)"; }
    if (isset($data['water_efficient'])) { $features++; $reasons[] = "Economiza água (+0.75)"; }
    if (isset($data['energy_efficient'])) { $features++; $reasons[] = "Economiza energia (+0.75)"; }
    $score += ($features * 0.75);

    // 4. Reciclável (10%)
    $recyclable = floatval($data['recyclable_percentage'] ?? 0);
    if ($recyclable >= 80) {
        $score += 1.0;
        $reasons[] = "Alto percentual reciclável (+1.0)";
    } elseif ($recyclable >= 50) {
        $score += 0.5;
        $reasons[] = "Bom percentual reciclável (+0.5)";
    }

    // 5. Carbono (10%)
    $carbon = floatval($data['carbon_footprint'] ?? 0);
    if ($carbon > 0 && $carbon < 5) {
        $score += 1.0;
        $reasons[] = "Baixa pegada de carbono (+1.0)";
    } elseif ($carbon > 0 && $carbon < 10) {
        $score += 0.5;
        $reasons[] = "Pegada de carbono moderada (+0.5)";
    }

    // 6. Certificação (10%)
    if (!empty($data['eco_certification'])) {
        $score += 1.0;
        $reasons[] = "Possui certificação ecológica (+1.0)";
    }

    // 7. Impacto (10%)
    if (strlen($environmental_impact) > 50) {
        $score += 1.0;
        $reasons[] = "Descrição detalhada de impacto (+1.0)";
    }

    // 8. Greenwashing
    $prohibited = ['plástico descartável', 'uso único', 'não reciclável', 'petróleo'];
    $greenwashing = false;
    foreach ($prohibited as $term) {
        if (mb_strpos($description, $term) !== false) {
            $greenwashing = true;
            $warnings[] = "Termo preocupante: '{$term}'";
            $score -= 2.0;
        }
    }

    $score = max(0, min(10, $score));
    $approved = $score >= 5.0 && !$greenwashing;

    $analysis_text = $approved ? 
        "Produto demonstra bom compromisso com sustentabilidade. " . implode('. ', array_slice($reasons, 0, 3)) . "." :
        "Produto não atende aos padrões mínimos de sustentabilidade.";

    if (count($warnings) > 0) {
        $analysis_text .= " Observações: " . implode('; ', $warnings) . ".";
    }

    return [
        'success' => $approved,
        'approved' => $approved,
        'score' => round($score, 2),
        'rating' => $score >= 7 ? 'excellent' : ($score >= 5 ? 'good' : 'poor'),
        'analysis' => $analysis_text,
        'reasons' => $reasons,
        'warnings' => $warnings,
        'greenwashing_detected' => $greenwashing,
        'ai_powered' => true,
        'ai_type' => 'simulated',
        'message' => $approved ? 
            "✅ Produto aprovado! Score: " . round($score, 2) . "/10" :
            "❌ " . $analysis_text
    ];
}