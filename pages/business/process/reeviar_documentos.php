<?php
session_start();
require_once '../../../registration/includes/db.php';
require_once '../../../registration/includes/security.php';

if (empty($_SESSION['auth']['user_id'])) {
    header("Location: ../../../registration/login/login.php");
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$motivo = $_GET['motivo'] ?? 'Documentação inválida ou ilegível.';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Resolução de Documentação - VisionGreen</title>
    <style>
        :root {
            --color-bg-000: #dcfce7;
            --color-bg-001: #ffffff;
            --color-bg-100: #1f2937;
            --color-bg-101: #111827;
            --color-bg-102: #101828;
            --color-bg-103: #364153;
            --color-bg-104: #00a63e;
            --color-bg-105: #4a5565;
            --color-bg-106: #99a1af;
            --color-bg-107: #111827;
            --color-bg-108: #9ca3af;
            --color-bg-109: #4ade80;
            --color-dg-001: #ff3232;
            --color-dg-002: #fb5353;
        }

        body { 
            background-color: var(--color-bg-000); 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
            color: var(--color-bg-101);
        }

        .container-opcoes { 
            max-width: 850px; 
            width: 95%; 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 25px; 
            padding: 20px;
        }

        .header-status { 
            grid-column: 1 / -1; 
            background-color: var(--color-bg-001); 
            color: var(--color-dg-001); 
            padding: 25px; 
            border-radius: 16px; 
            margin-bottom: 10px; 
            border-left: 8px solid var(--color-dg-001);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .header-status h3 { margin: 0 0 10px 0; display: flex; align-items: center; gap: 10px; }

        .card-opcao { 
            background-color: var(--color-bg-102); 
            padding: 40px 30px; 
            border-radius: 20px; 
            text-align: center; 
            border: 2px solid transparent; 
            transition: all 0.3s ease; 
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: var(--color-bg-001);
        }

        .card-opcao:hover { 
            border-color: var(--color-bg-109); 
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
        }
        
        .icon { font-size: 50px; margin-bottom: 20px; }
        
        h2 { font-size: 1.4rem; color: var(--color-bg-109); margin-bottom: 15px; }
        
        p { 
            color: var(--color-bg-108); 
            font-size: 0.95rem; 
            line-height: 1.6; 
            margin-bottom: 25px; 
            min-height: 70px;
        }
        
        .btn { 
            display: inline-block; 
            padding: 14px 24px; 
            border-radius: 10px; 
            font-weight: bold; 
            text-decoration: none; 
            cursor: pointer; 
            width: 100%; 
            box-sizing: border-box; 
            border: none; 
            transition: 0.2s;
            font-size: 1rem;
        }

        .btn-upload { background-color: var(--color-bg-104); color: white; }
        .btn-upload:hover { background-color: var(--color-bg-109); }

        .btn-create { background-color: var(--color-bg-103); color: var(--color-bg-000); }
        .btn-create:hover { background-color: var(--color-bg-105); color: white; }

        input[type="file"] {
            margin-bottom: 15px;
            color: var(--color-bg-108);
            font-size: 0.8rem;
            width: 100%;
        }

        .footer-links {
            grid-column: 1 / -1;
            text-align: center;
            margin-top: 20px;
        }

        .footer-links a {
            color: var(--color-bg-105);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-links a:hover { color: var(--color-dg-001); }

        @media (max-width: 600px) {
            .container-opcoes { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container-opcoes">
    <div class="header-status">
        <h3><span>⚠️</span> Documentação Rejeitada</h3>
        <p style="margin:0; color: var(--color-bg-105);"><strong>Motivo:</strong> <?= htmlspecialchars($motivo) ?></p>
    </div>

    <div class="card-opcao">
        <div>
            <span class="icon">📁</span>
            <h2>Novo Upload</h2>
            <p>Se você já possui o documento físico corrigido, envie uma foto ou PDF nítido aqui.</p>
        </div>
        
        <form action="processar_reenvio.php" method="POST" enctype="multipart/form-data">
            <input type="file" name="licenca" required>
            <button type="submit" class="btn btn-upload">Submeter Arquivo</button>
        </form>
    </div>

    <div class="card-opcao">
        <div>
            <span class="icon">🛠️</span>
            <h2>Gerar Digitalmente</h2>
            <p>Inicie o processo para emitir o seu Alvará Digital VisionGreen diretamente pela plataforma.</p>
        </div>
        
        <div style="margin-top: auto;">
            <a href="gerar_documento_vision.php" class="btn btn-create">Criar no VisionGreen</a>
        </div>
    </div>

    <div class="footer-links">
        <a href="../../../registration/login/logout.php">Encerrar Sessão e Sair</a>
    </div>
</div>

</body>
</html>