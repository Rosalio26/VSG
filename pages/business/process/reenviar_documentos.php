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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resolução de Documentação - VisionGreen</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981;
            --primary-soft: rgba(16, 185, 129, 0.1);
            --dark: #0f172a;
            --danger: #ef4444;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --radius: 16px;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        body { 
            background-color: var(--bg); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
            color: var(--text-main);
        }

        .container-opcoes { 
            max-width: 850px; 
            width: 92%; 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 24px; 
            padding: 20px 0;
        }

        .header-status { 
            grid-column: 1 / -1; 
            background: var(--white);
            padding: 20px 32px; 
            border-radius: var(--radius); 
            text-align: center;
            border: 1px solid #fee2e2;
            box-shadow: var(--shadow);
            margin-bottom: 8px;
        }

        .header-status h3 { 
            margin: 0 0 5px 0; 
            color: var(--danger);
            font-size: 1.15rem;
            font-weight: 700;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .motivo-tag {
            display: inline-block;
            margin-top: 5px;
            padding: 2px 12px;
            background: #fef2f2;
            color: var(--danger);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .card-opcao { 
            background: var(--white);
            padding: 32px 24px; /* Altura reduzida aqui */
            border-radius: var(--radius); 
            text-align: center; 
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease; 
            display: flex;
            flex-direction: column;
            align-items: center; 
            box-shadow: var(--shadow);
            height: 100%; 
            box-sizing: border-box;
        }

        .card-opcao:hover { 
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 20px -5px rgba(16, 185, 129, 0.1);
        }
        
        .icon-wrapper { 
            width: 60px; /* Reduzido */
            height: 60px; /* Reduzido */
            background: var(--bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        h2 { font-size: 1.3rem; margin: 0 0 8px 0; font-weight: 800; color: var(--dark); }
        
        p.description { 
            color: var(--text-muted); 
            font-size: 0.9rem; 
            line-height: 1.5; 
            margin-bottom: 24px; 
            max-width: 250px; 
        }

        .action-area {
            margin-top: auto; 
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* --- ESTILIZAÇÃO CHOOSE FILE COMPACTO --- */
        .custom-file-upload {
            position: relative;
            width: 100%;
            max-width: 240px;
            margin-bottom: 12px;
        }

        input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }

        .file-drop-area {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: 0.3s;
            gap: 8px;
        }

        .custom-file-upload:hover .file-drop-area {
            border-color: var(--primary);
            background: var(--primary-soft);
        }

        .file-msg {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            max-width: 160px;
        }
        
        .btn { 
            display: inline-flex; 
            align-items: center;
            justify-content: center;
            padding: 14px 28px; 
            border-radius: 10px; 
            font-weight: 700; 
            text-decoration: none; 
            cursor: pointer; 
            width: 100%; 
            max-width: 240px; 
            border: none; 
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-upload { background-color: var(--dark); color: var(--white); }
        .btn-upload:hover { background-color: #334155; }

        .btn-create { background-color: var(--primary); color: white; }
        .btn-create:hover { background-color: #059669; }

        .footer-links { grid-column: 1 / -1; text-align: center; margin-top: 20px; }
        .footer-links a { color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 0.85rem; }

        @media (max-width: 768px) {
            .container-opcoes { grid-template-columns: 1fr; gap: 16px; }
        }
    </style>
</head>
<body>

<div class="container-opcoes">
    <div class="header-status">
        <h3><span>⚠️</span> Documentação Rejeitada</h3>
        <div class="motivo-tag">Motivo: <?= htmlspecialchars($motivo) ?></div>
    </div>

    <div class="card-opcao">
        <div class="icon-wrapper">📁</div>
        <h2>Upload Manual</h2>
        <p class="description">Envie seu documento retificado em imagem ou PDF.</p>
        
        <div class="action-area">
            <form action="processar_reenvio.php" method="POST" enctype="multipart/form-data" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                <div class="custom-file-upload">
                    <input type="file" name="licenca" id="fileInput" required>
                    <div class="file-drop-area">
                        <span style="font-size: 1rem;">📄</span>
                        <span class="file-msg" id="fileName">Selecionar arquivo</span>
                    </div>
                </div>
                <button type="submit" class="btn btn-upload">Submeter Arquivo</button>
            </form>
        </div>
    </div>

    <div class="card-opcao">
        <div class="icon-wrapper">⚡</div>
        <h2>Emissão Digital</h2>
        <p class="description">Gere seu Alvará Digital oficial automaticamente.</p>
        
        <div class="action-area">
            <a href="gerar_documento_vision.php" class="btn btn-create">Gerar Agora</a>
        </div>
    </div>

    <div class="footer-links">
        <a href="../../../registration/login/logout.php">Sair do Sistema</a>
    </div>
</div>

<script>
    document.getElementById('fileInput').addEventListener('change', function(e) {
        var fileName = e.target.files[0] ? e.target.files[0].name : "Selecionar arquivo";
        document.getElementById('fileName').textContent = fileName;
        if(e.target.files[0]) {
            this.nextElementSibling.style.borderColor = "var(--primary)";
            this.nextElementSibling.style.background = "var(--primary-soft)";
        }
    });
</script>

</body>
</html>