<?php
session_start();
require_once '../../../registration/includes/db.php';
require_once '../../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../../registration/login/login.php");
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

// Busca corrigida com INNER JOIN
$stmt = $mysqli->prepare("
    SELECT u.nome, b.tax_id, b.country, b.city 
    FROM users u
    INNER JOIN businesses b ON u.id = b.user_id 
    WHERE u.id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$bus = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bus) {
    header("Location: ../dashboard_business.php");
    exit;
}

/* ================= LÓGICA DO NÚMERO DO DOCUMENTO ================= */
// 1. Prefixo (3 dígitos): Identificação do tipo (ex: 100 para Alvará Digital)
$prefixo = "100";

// 2. Código (5 dígitos): Aleatórios ou baseados no ID do utilizador (com preenchimento de zeros)
$codigo = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);

// 3. Segurança (4 dígitos): Aleatórios
$seguranca = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Formato Final: 000-00000-0000-vsg
$numeroDocumento = "{$prefixo}-{$codigo}-{$seguranca}-vsg";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Emissão de Alvará Digital - VisionGreen</title>
    <style>
        :root {
            --color-bg-000: #dcfce7;
            --color-bg-001: #ffffff;
            --color-bg-101: #111827;
            --color-bg-102: #101828;
            --color-bg-103: #364153;
            --color-bg-104: #00a63e;
            --color-bg-105: #4a5565;
            --color-bg-108: #9ca3af;
            --color-bg-109: #4ade80;
        }

        body { 
            background-color: var(--color-bg-000); 
            font-family: 'Segoe UI', sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
        }

        .form-container {
            background-color: var(--color-bg-102);
            width: 100%;
            max-width: 650px;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            color: var(--color-bg-001);
        }

        .header-form { text-align: center; margin-bottom: 30px; }
        .header-form h1 { color: var(--color-bg-109); margin: 0; font-size: 1.8rem; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; color: var(--color-bg-108); font-size: 0.85rem; margin-bottom: 8px; font-weight: 500; }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--color-bg-101);
            border: 1px solid var(--color-bg-103);
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            box-sizing: border-box;
            outline: none;
        }

        /* Campo de número de série estilizado como desativado mas visível */
        .serial-input {
            background-color: #0d1117;
            border-color: var(--color-bg-104);
            color: var(--color-bg-109);
            font-family: monospace;
            font-weight: bold;
            letter-spacing: 2px;
            text-align: center;
        }

        .grid-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .btn-generate {
            background-color: var(--color-bg-104);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            width: 100%;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-generate:hover { background-color: var(--color-bg-109); }

        .info-box {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid var(--color-bg-104);
            padding: 15px;
            border-radius: 10px;
            font-size: 0.8rem;
            color: var(--color-bg-109);
            margin-bottom: 25px;
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="header-form">
        <h1>Alvará Digital VisionGreen</h1>
        <p style="color: var(--color-bg-108);">Geração de licença eletrônica rastreável</p>
    </div>

    <form action="processar_geracao_digital.php" method="POST">
        
        <div class="form-group">
            <label>NÚMERO DE SÉRIE DO DOCUMENTO (GERADO AUTOMATICAMENTE)</label>
            <input type="text" name="doc_serial" value="<?= $numeroDocumento ?>" class="serial-input" readonly>
        </div>

        <div class="form-group">
            <label>Nome da Organização</label>
            <input type="text" name="empresa_nome" value="<?= htmlspecialchars($bus['nome'] ?? '') ?>" required>
        </div>

        <div class="grid-inputs">
            <div class="form-group">
                <label>Identificação Fiscal (Tax ID)</label>
                <input type="text" name="tax_id" value="<?= htmlspecialchars(str_replace('FILE:', '', $bus['tax_id'] ?? '')) ?>" required>
            </div>
            <div class="form-group">
                <label>Tipo de Atividade</label>
                <select name="atividade" required>
                    <option value="comercio">Comércio Geral</option>
                    <option value="servicos">Prestação de Serviços</option>
                    <option value="industria">Indústria</option>
                    <option value="tecnologia">Tecnologia / Digital</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Endereço de Operação</label>
            <textarea name="endereco" rows="2" required><?= htmlspecialchars(($bus['city'] ?? '') . ', ' . ($bus['country'] ?? '')) ?></textarea>
        </div>

        <div class="form-group">
            <label>Data de Início das Atividades</label>
            <input type="date" name="data_inicio" required>
        </div>

        <div class="info-box">
            O número <strong><?= $numeroDocumento ?></strong> é único. Ao clicar abaixo, o sistema irá registrar este código como sua identidade oficial de operação.
        </div>

        <button type="submit" class="btn-generate">Gerar Licença Oficial</button>
    </form>

    <a href="reenviar_documentos.php" style="display:block; text-align:center; margin-top:15px; color:var(--color-bg-108); text-decoration:none; font-size:0.8rem;">Voltar</a>
</div>

</body>
</html>