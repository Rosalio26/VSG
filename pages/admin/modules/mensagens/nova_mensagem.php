<?php
require_once '../../../../registration/includes/db.php';
session_start();

/* ================= PROTEÇÃO DE ACESSO ================= */
if (!isset($_SESSION['auth']['user_id'])) {
    die("Acesso negado.");
}

$adminId = $_SESSION['auth']['user_id'];
$prefill_to = isset($_GET['to']) ? (int)$_GET['to'] : '';
$prefill_subject = isset($_GET['subject']) ? "RE: " . htmlspecialchars($_GET['subject']) : '';
?>

<div style="padding: 20px; animation: fadeIn 0.3s ease;">
    <button onclick="loadContent('modules/mensagens/mensagens')" style="background:none; border:none; color:var(--accent-green); cursor:pointer; margin-bottom:20px; font-weight:bold; display:flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-arrow-left"></i> VOLTAR À LISTA
    </button>
    
    <div style="background: #111; border: 1px solid rgba(255,255,255,0.05); border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        <h2 style="color:#fff; margin-bottom:25px;">
            <i class="fa-solid fa-pen-nib" style="color:var(--accent-green); margin-right:10px;"></i> Nova Mensagem
        </h2>
        
        <form id="formNovaMsg" onsubmit="event.preventDefault();">
            <div style="margin-bottom:20px;">
                <label style="color:#666; display:block; margin-bottom:8px; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Destinatário</label>
                <select name="receiver_id" id="receiver_id" required style="width:100%; background:#080808; border:1px solid #222; padding:14px; color:#fff; border-radius:10px; outline:none; transition: 0.3s focus;">
                    <option value="">Selecione um membro...</option>
                    <?php
                    // Busca outros admins e empresas para enviar mensagem
                    $contacts = $mysqli->query("SELECT id, nome, role FROM users WHERE id != $adminId ORDER BY nome ASC");
                    while($c = $contacts->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $prefill_to == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= strtoupper($c['role']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="color:#666; display:block; margin-bottom:8px; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Assunto</label>
                <input type="text" name="subject" id="subject" value="<?= $prefill_subject ?>" required placeholder="Título da comunicação" style="width:100%; background:#080808; border:1px solid #222; padding:14px; color:#fff; border-radius:10px; outline:none;">
            </div>

            <div style="margin-bottom:25px;">
                <label style="color:#666; display:block; margin-bottom:8px; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Conteúdo</label>
                <textarea name="message" id="message" required placeholder="Escreva sua mensagem aqui..." style="width:100%; background:#080808; border:1px solid #222; padding:15px; color:#fff; border-radius:10px; min-height:180px; outline:none; font-family:inherit; line-height:1.6;"></textarea>
            </div>

            <button type="button" id="btnEnviar" onclick="window.dispararEnvio()" style="background:var(--accent-green); color:#000; border:none; padding:18px 30px; border-radius:12px; font-weight:900; cursor:pointer; width:100%; display:flex; align-items:center; justify-content:center; gap:10px; text-transform:uppercase; letter-spacing:1px;">
                <i class="fa-solid fa-paper-plane"></i> ENVIAR MENSAGEM
            </button>
        </form>
    </div>
</div>

<script>
/**
 * Atribuímos a função ao objeto global 'window'.
 * Isso resolve o erro de 'dispararEnvio is not defined' ao carregar via AJAX.
 */
window.dispararEnvio = function() {
    const btn = document.getElementById('btnEnviar');
    const form = document.getElementById('formNovaMsg');
    const receiver = document.getElementById('receiver_id').value;
    const msg = document.getElementById('message').value;

    if(!receiver || !msg.trim()) {
        alert('Por favor, selecione um destinatário e escreva uma mensagem.');
        return;
    }

    // Feedback visual de carregamento
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ENVIANDO...';

    const formData = new FormData(form);
    formData.append('send_msg', '1');

    fetch('modules/mensagens/actions/processar_msg.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Mensagem enviada com sucesso!');
            // Carrega a listagem de volta
            loadContent('modules/mensagens/mensagens');
        } else {
            alert('Erro: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        console.error('Fetch Error:', err);
        alert('Erro ao processar a requisição. Verifique o console.');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
};
</script>