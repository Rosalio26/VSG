<?php
require_once '../../../../registration/includes/db.php';
session_start();

/* ================= PROTEÇÃO DE ACESSO ================= */
if (!isset($_SESSION['auth']['user_id'])) {
    die("Acesso negado.");
}

$adminId = $_SESSION['auth']['user_id'];
$chatAtivo = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? 'list';

/* ================= LÓGICA DE EXCLUSÃO ================= */
if ($action === 'delete' && isset($_GET['msg_id'])) {
    $msgId = (int)$_GET['msg_id'];
    $mysqli->query("DELETE FROM notifications WHERE id = $msgId AND receiver_id = $adminId");
    echo "<script>loadContent('modules/mensagens/mensagens');</script>";
    exit;
}

/**
 * BUSCA LISTA DE CONVERSAS (Lado Esquerdo)
 */
$queryContatos = "
    SELECT 
        u.id as contato_id, 
        u.nome, 
        (SELECT message FROM notifications 
         WHERE (sender_id = $adminId AND receiver_id = u.id) 
            OR (sender_id = u.id AND receiver_id = $adminId) 
         ORDER BY created_at DESC LIMIT 1) as ultima_msg,
        (SELECT created_at FROM notifications 
         WHERE (sender_id = $adminId AND receiver_id = u.id) 
            OR (sender_id = u.id AND receiver_id = $adminId) 
         ORDER BY created_at DESC LIMIT 1) as data_msg,
        (SELECT COUNT(*) FROM notifications 
         WHERE sender_id = u.id AND receiver_id = $adminId AND status = 'unread') as nao_lidas
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT receiver_id FROM notifications WHERE sender_id = $adminId
        UNION
        SELECT DISTINCT sender_id FROM notifications WHERE receiver_id = $adminId
    )
    ORDER BY data_msg DESC";

$contatos = $mysqli->query($queryContatos);

// Se for uma requisição de atualização silenciosa (AJAX interno)
if (isset($_GET['fetch_messages']) && $chatAtivo) {
    $mysqli->query("UPDATE notifications SET status = 'read' WHERE sender_id = $chatAtivo AND receiver_id = $adminId");
    $historico = $mysqli->query("SELECT * FROM notifications WHERE (sender_id = $adminId AND receiver_id = $chatAtivo) OR (sender_id = $chatAtivo AND receiver_id = $adminId) ORDER BY created_at ASC");
    while ($m = $historico->fetch_assoc()) {
        $isMe = ($m['sender_id'] == $adminId);
        echo '<div style="max-width: 75%; display: flex; flex-direction: column; '.($isMe ? 'align-self: flex-end; align-items: flex-end;' : 'align-self: flex-start; align-items: flex-start;').'">
                <div style="padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; '.($isMe ? 'background: var(--accent-green); color: #000; border-bottom-right-radius: 2px;' : 'background: #1a1a1a; color: #ccc; border-bottom-left-radius: 2px;').'">
                    '.nl2br(htmlspecialchars($m['message'])).'
                </div>
                <small style="color: #333; font-size: 0.65rem; margin-top: 5px; font-weight: bold;">'.date('H:i', strtotime($m['created_at'])).'</small>
              </div>';
    }
    exit;
}
?>

<div style="display: flex; height: calc(100vh - 160px); background: #0a0a0a; border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); animation: fadeIn 0.4s ease;">
    
    <div style="width: 350px; border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; background: rgba(255,255,255,0.02);">
        <div style="padding: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="color: #fff; margin: 0; font-size: 1.2rem;">Conversas</h2>
            <button onclick="loadContent('modules/mensagens/nova_mensagem')" style="background: var(--accent-green); border: none; width: 32px; height: 32px; border-radius: 10px; cursor: pointer; color: #000; transition: 0.3s;">
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>

        <div style="flex: 1; overflow-y: auto; padding: 10px;">
            <?php if ($contatos && $contatos->num_rows > 0): ?>
                <?php while ($c = $contatos->fetch_assoc()): ?>
                    <div onclick="loadContent('modules/mensagens/mensagens?id=<?= $c['contato_id'] ?>', this)" 
                         style="padding: 15px; border-radius: 15px; margin-bottom: 8px; cursor: pointer; transition: 0.3s; position: relative; 
                         <?= $chatAtivo == $c['contato_id'] ? 'background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.2);' : 'background: transparent;' ?>">
                        
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($c['nome']) ?>&background=333&color=fff&bold=true" style="width: 45px; height: 45px; border-radius: 12px;">
                            <div style="flex: 1; overflow: hidden;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: #fff; font-size: 0.9rem;"><?= htmlspecialchars($c['nome']) ?></strong>
                                    <small style="color: #444; font-size: 0.7rem;"><?= ($c['data_msg']) ? date('H:i', strtotime($c['data_msg'])) : '' ?></small>
                                </div>
                                <p style="color: #666; font-size: 0.8rem; margin: 4px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($c['ultima_msg'] ?? 'Inicie uma conversa') ?>
                                </p>
                            </div>
                            <?php if ($c['nao_lidas'] > 0): ?>
                                <span style="background: var(--accent-green); color: #000; font-size: 0.65rem; font-weight: 900; padding: 2px 7px; border-radius: 20px; min-width: 18px; text-align: center;">
                                    <?= $c['nao_lidas'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #333;">Nenhuma conversa.</div>
            <?php endif; ?>
        </div>
    </div>

    <div style="flex: 1; display: flex; flex-direction: column; background: #070707;">
        <?php 
        if ($chatAtivo): 
            $resUser = $mysqli->query("SELECT nome FROM users WHERE id = $chatAtivo");
            $contato = $resUser->fetch_assoc();
            if ($contato): ?>
                <div style="padding: 20px 30px; background: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($contato['nome']) ?>&background=00ff88&color=000&bold=true" style="width: 35px; height: 35px; border-radius: 10px;">
                        <h4 style="color: #fff; margin: 0;"><?= htmlspecialchars($contato['nome']) ?></h4>
                    </div>
                </div>

                <div id="chatBox" style="flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth;">
                    <?php 
                    $historico = $mysqli->query("SELECT * FROM notifications WHERE (sender_id = $adminId AND receiver_id = $chatAtivo) OR (sender_id = $chatAtivo AND receiver_id = $adminId) ORDER BY created_at ASC");
                    while ($m = $historico->fetch_assoc()): 
                        $isMe = ($m['sender_id'] == $adminId);
                    ?>
                        <div style="max-width: 75%; display: flex; flex-direction: column; <?= $isMe ? 'align-self: flex-end; align-items: flex-end;' : 'align-self: flex-start; align-items: flex-start;' ?>">
                            <div style="padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; <?= $isMe ? 'background: var(--accent-green); color: #000; border-bottom-right-radius: 2px;' : 'background: #1a1a1a; color: #ccc; border-bottom-left-radius: 2px;' ?>">
                                <?= nl2br(htmlspecialchars($m['message'])) ?>
                            </div>
                            <small style="color: #333; font-size: 0.65rem; margin-top: 5px; font-weight: bold;">
                                <?= date('H:i', strtotime($m['created_at'])) ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div style="padding: 20px 30px; background: rgba(255,255,255,0.02); border-top: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; gap: 12px; background: #000; padding: 8px 15px; border-radius: 30px; border: 1px solid #222;">
                        <input type="text" id="fastMsgInput" placeholder="Escreva uma mensagem..." style="flex: 1; background: transparent; border: none; color: #fff; outline: none; padding: 8px;">
                        <button id="btnSendFast" onclick="window.enviarChatRapido(<?= $chatAtivo ?>)" style="background: var(--accent-green); border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; color: #000;">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.2;">
                <i class="fa-solid fa-comments" style="font-size: 6rem; margin-bottom: 20px;"></i>
                <p style="font-size: 1.2rem; font-weight: bold;">Selecione uma conversa para começar</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    // 1. Função de envio sem recarregar a página inteira
    window.enviarChatRapido = function(toId) {
        const inputElem = document.getElementById('fastMsgInput');
        if(!inputElem) return;
        
        const msg = inputElem.value.trim();
        if(!msg) return;

        inputElem.value = ''; // Limpa o campo imediatamente para fluidez

        const formData = new FormData();
        formData.append('send_msg', '1');
        formData.append('receiver_id', toId);
        formData.append('subject', 'Conversa Direta');
        formData.append('message', msg);

        fetch('modules/mensagens/actions/processar_msg.php', {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if(data.status === 'success') {
                // Em vez de loadContent, atualizamos apenas as bolhas
                window.atualizarBolhasChat(toId);
            } else {
                alert('Erro ao enviar: ' + data.message);
                inputElem.value = msg; // Devolve o texto se der erro
            }
        });
    };

    // 2. Função que busca apenas as mensagens silenciosamente
    window.atualizarBolhasChat = function(toId) {
        const chatBox = document.getElementById('chatBox');
        if(!chatBox || !toId) return;

        fetch('modules/mensagens/mensagens.php?id=' + toId + '&fetch_messages=1')
        .then(res => res.text())
        .then(html => {
            const isAtBottom = (chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 100);
            chatBox.innerHTML = html;
            if(isAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
        });
    };

    // 3. Polling: Atualiza automaticamente a cada 3 segundos
    if(window.msgInterval) clearInterval(window.msgInterval);
    window.msgInterval = setInterval(() => {
        const activeId = <?= (int)$chatAtivo ?>;
        if(activeId > 0) window.atualizarBolhasChat(activeId);
    }, 3000);

    const box = document.getElementById('chatBox');
    if(box) box.scrollTop = box.scrollHeight;

    const fmsg = document.getElementById('fastMsgInput');
    if(fmsg) {
        fmsg.onkeydown = function(e) {
            if (e.key === 'Enter') {
                window.enviarChatRapido(<?= (int)$chatAtivo ?>);
            }
        };
    }
})();
</script>