<?php
/**
 * ================================================================================
 * VISIONGREEN - CENTRO DE AJUDA E DOCUMENTAÇÃO
 * Módulo: modules/suporte/help-sub-admin.php
 * Descrição: Guia completo de uso do sistema para administradores
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');
?>

<!-- PAGE HEADER -->
<div style="margin-bottom: 40px;">
    <h1 style="color: #fff; margin: 0 0 8px 0; font-size: 2rem;">
        <i class="fa-solid fa-circle-question"></i>
        Centro de Ajuda
    </h1>
    <p style="color: #888; font-size: 0.938rem; margin: 0;">
        Aprenda como usar o VisionGreen e resolva problemas comuns
    </p>
</div>

<!-- QUICK START -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-rocket"></i>
            Início Rápido
        </h3>
    </div>
    <div class="card-body">
        <p style="color: #ccc; margin-bottom: 20px;">
            Se você é novo no sistema, siga estes passos básicos:
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px; border-left: 3px solid #00ff88;">
                <h4 style="color: #00ff88; margin: 0 0 8px 0;">✅ Passo 1: Login</h4>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    Use suas credenciais fornecidas para acessar o painel administrativo.
                </p>
            </div>
            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px; border-left: 3px solid #58a6ff;">
                <h4 style="color: #58a6ff; margin: 0 0 8px 0;">✅ Passo 2: Primeira Senha</h4>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    Altere sua senha temporária na primeira vez que entrar no sistema.
                </p>
            </div>
            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px; border-left: 3px solid #d29922;">
                <h4 style="color: #d29922; margin: 0 0 8px 0;">✅ Passo 3: Dashboard</h4>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    Explore o Dashboard para conhecer as métricas principais do sistema.
                </p>
            </div>
            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px; border-left: 3px solid #f85149;">
                <h4 style="color: #f85149; margin: 0 0 8px 0;">✅ Passo 4: Notificações</h4>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    Verifique regularmente as notificações para novas tarefas.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- FAQ SECTION -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-comments"></i>
            Perguntas Frequentes (FAQ)
        </h3>
    </div>
    <div class="card-body">
        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como faço login no sistema?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    Acesse a página de login e insira seu email e senha. Se esquecer a senha, clique em "Esqueci minha senha" e siga as instruções. 
                    Você receberá um token de recuperação por email.
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como altero minha senha?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    Clique na sua conta (canto superior direito) → Segurança → Alterar Senha. 
                    Você precisará confirmar sua senha atual antes de definir uma nova.
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Quanto tempo minha sessão dura?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    <strong>Superadministrador:</strong> 1 hora de inatividade<br>
                    <strong>Administrador:</strong> 24 horas de inatividade<br>
                    Você será avisado antes que expire. Renove sua senha para estender a sessão.
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como busco um usuário específico?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    Use a barra de pesquisa no topo de qualquer página. Você pode buscar por:
                    <ul style="color: #999; margin-top: 8px;">
                        <li>UID (ID único do usuário)</li>
                        <li>Email</li>
                        <li>Nome</li>
                        <li>Nome da empresa</li>
                    </ul>
                    Ou use os filtros específicos em cada seção.
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como aprovo documentos de uma empresa?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    1. Vá para Dashboard → Pendências<br>
                    2. Procure por "Documentos Pendentes"<br>
                    3. Clique em uma empresa para ver os documentos<br>
                    4. Analise os documentos (Alvará, NUIT, etc.)<br>
                    5. Clique "Aprovar" se tudo está correto<br>
                    6. Ou "Rejeitar" com motivo se houver problemas
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como vejo o histórico de um usuário?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    1. Localize o usuário na lista de usuários<br>
                    2. Clique na linha para abrir detalhes<br>
                    3. Você verá informações pessoais e um timeline de ações<br>
                    4. O histórico mostra todas as ações e quando foram realizadas
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como gero um relatório?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    1. Acesse Dados de Base → Relatórios<br>
                    2. Selecione o tipo de relatório (Usuários, Empresas, Financeiro, etc.)<br>
                    3. Defina o período de datas<br>
                    4. Clique em "Gerar"<br>
                    5. Exporte em PDF ou Excel se necessário
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como recebo notificações?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    As notificações aparecem em dois lugares:<br>
                    <strong>Sino (🔔):</strong> Alertas críticos e mensagens<br>
                    <strong>Mensagens (💬):</strong> Mensagens de outros admins<br>
                    Você também pode acessar Comunicação → Mensagens para ver o histórico completo.
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como bloqueio um usuário?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    1. Acesse a lista de usuários<br>
                    2. Encontre o usuário que deseja bloquear<br>
                    3. Clique para abrir detalhes<br>
                    4. Clique em "Segurança" → "Bloquear Conta"<br>
                    5. Confirme a ação<br>
                    6. O usuário receberá notificação e não poderá mais acessar
                </p>
            </div>
        </div>

        <div class="faq-item">
            <h4 style="color: #fff; cursor: pointer;" onclick="toggleFAQ(this)">
                <i class="fa-solid fa-chevron-right" style="transition: transform 0.3s;"></i>
                Como reseto a senha de um usuário?
            </h4>
            <div class="faq-answer" style="display: none;">
                <p style="color: #999;">
                    1. Na página de detalhes do usuário<br>
                    2. Clique em "Segurança" → "Resetar Senha"<br>
                    3. Uma nova senha temporária será gerada<br>
                    4. A senha será enviada por email ao usuário<br>
                    5. O usuário deve alterar na próxima vez que fizer login
                </p>
            </div>
        </div>
    </div>
</div>

<!-- FEATURES GUIDE -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-lightbulb"></i>
            Guia de Funcionalidades Principais
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; gap: 20px;">
            
            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <h4 style="color: #00ff88; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-gauge-high"></i>
                    Dashboard
                </h4>
                <p style="color: #999; margin: 0;">
                    <strong>O que faz:</strong> Mostra um resumo visual de todas as métricas importantes do sistema.<br>
                    <strong>Como acessar:</strong> Clique em "Dashboard" na barra lateral.<br>
                    <strong>Dica:</strong> Comece aqui para entender a saúde geral do sistema.
                </p>
            </div>

            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <h4 style="color: #58a6ff; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clipboard-check"></i>
                    Pendências
                </h4>
                <p style="color: #999; margin: 0;">
                    <strong>O que faz:</strong> Lista todos os documentos e usuários aguardando aprovação.<br>
                    <strong>Como acessar:</strong> Dashboard → Pendências.<br>
                    <strong>Dica:</strong> Revise regularmente para manter o sistema organizado.
                </p>
            </div>

            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <h4 style="color: #d29922; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    Análise de Contas
                </h4>
                <p style="color: #999; margin: 0;">
                    <strong>O que faz:</strong> Fornece estatísticas e análises detalhadas de usuários e receita.<br>
                    <strong>Como acessar:</strong> Dashboard → Análise de Contas.<br>
                    <strong>Dica:</strong> Use para identificar tendências e anomalias.
                </p>
            </div>

            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <h4 style="color: #f85149; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comment-dots"></i>
                    Mensagens
                </h4>
                <p style="color: #999; margin: 0;">
                    <strong>O que faz:</strong> Centro de notificações e mensagens entre administradores.<br>
                    <strong>Como acessar:</strong> Comunicação → Mensagens ou clique no sino.<br>
                    <strong>Dica:</strong> Verifique regularmente para não perder alertas importantes.
                </p>
            </div>

            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <h4 style="color: #00ff88; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-file-export"></i>
                    Relatórios
                </h4>
                <p style="color: #999; margin: 0;">
                    <strong>O que faz:</strong> Gera relatórios em PDF ou Excel com os dados que você especificar.<br>
                    <strong>Como acessar:</strong> Dados de Base → Relatórios.<br>
                    <strong>Dica:</strong> Exporte dados regularmente para análise ou conformidade.
                </p>
            </div>

            <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <h4 style="color: #58a6ff; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-shield-halved"></i>
                    Autenticação
                </h4>
                <p style="color: #999; margin: 0;">
                    <strong>O que faz:</strong> Mostra logs de login e tentativas de acesso falhadas.<br>
                    <strong>Como acessar:</strong> Páginas → Autenticação e Segurança.<br>
                    <strong>Dica:</strong> Monitore para detectar atividades suspeitas.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- BEST PRACTICES -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-star"></i>
            Melhores Práticas
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; gap: 20px;">
            <div style="padding: 16px; background: rgba(35,134,54,0.1); border-left: 3px solid #00ff88; border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><strong>🔐 Segurança</strong></p>
                <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                    <li>Altere sua senha regularmente (a cada 30 dias)</li>
                    <li>Nunca compartilhe suas credenciais</li>
                    <li>Saia da conta ao terminar cada sessão</li>
                    <li>Revise logs de auditoria periodicamente</li>
                </ul>
            </div>

            <div style="padding: 16px; background: rgba(35,134,54,0.1); border-left: 3px solid #00ff88; border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><strong>📋 Aprovação de Documentos</strong></p>
                <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                    <li>Verifique sempre a autenticidade dos documentos</li>
                    <li>Procure por sinais de falsificação</li>
                    <li>Confirme que as datas não estão expiradas</li>
                    <li>Deixe comentários detalhados ao rejeitar</li>
                </ul>
            </div>

            <div style="padding: 16px; background: rgba(35,134,54,0.1); border-left: 3px solid #00ff88; border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><strong>📊 Análise de Dados</strong></p>
                <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                    <li>Revise métricas semanalmente</li>
                    <li>Compare períodos para identificar tendências</li>
                    <li>Investigue anomalias imediatamente</li>
                    <li>Documente achados importantes</li>
                </ul>
            </div>

            <div style="padding: 16px; background: rgba(35,134,54,0.1); border-left: 3px solid #00ff88; border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><strong>💬 Comunicação</strong></p>
                <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                    <li>Verifique notificações no mínimo 2x por dia</li>
                    <li>Responda alertas críticos imediatamente</li>
                    <li>Mantenha a comunicação clara com colegas</li>
                    <li>Documente decisões importantes</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- KEYBOARD SHORTCUTS -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-keyboard"></i>
            Atalhos do Teclado
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
            <div style="padding: 12px; background: rgba(0,0,0,0.2); border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><kbd>/</kbd></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">Focar na barra de busca principal</p>
            </div>
            <div style="padding: 12px; background: rgba(0,0,0,0.2); border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><kbd>Ctrl</kbd> + <kbd>K</kbd></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">Abrir modal de comando (em breve)</p>
            </div>
            <div style="padding: 12px; background: rgba(0,0,0,0.2); border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><kbd>Escape</kbd></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">Fechar modais e dropdowns</p>
            </div>
            <div style="padding: 12px; background: rgba(0,0,0,0.2); border-radius: 6px;">
                <p style="color: #ccc; margin: 0 0 8px 0;"><kbd>Enter</kbd></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">Enviar formulário ou confirmar ação</p>
            </div>
        </div>
    </div>
</div>

<!-- TROUBLESHOOTING -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-wrench"></i>
            Solução de Problemas Comuns
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; gap: 20px;">
            
            <div style="padding: 16px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px;">
                <p style="color: #f85149; margin: 0 0 8px 0;"><strong>❌ Problema: "Acesso Recusado"</strong></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    <strong>Solução:</strong> Você não tem permissão para acessar esta página. Verifique seu cargo 
                    ou contacte um Superadministrador para obter acesso.
                </p>
            </div>

            <div style="padding: 16px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px;">
                <p style="color: #f85149; margin: 0 0 8px 0;"><strong>❌ Problema: "Sessão Expirada"</strong></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    <strong>Solução:</strong> Sua sessão expirou. Faça login novamente. Se for Superadmin, você tem 1 hora; 
                    se for Admin, tem 24 horas.
                </p>
            </div>

            <div style="padding: 16px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px;">
                <p style="color: #f85149; margin: 0 0 8px 0;"><strong>❌ Problema: "Página não carrega"</strong></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    <strong>Solução:</strong> Tente atualizar a página (F5), limpar cache ou usar incógnito. 
                    Se persistir, tente outro navegador.
                </p>
            </div>

            <div style="padding: 16px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px;">
                <p style="color: #f85149; margin: 0 0 8px 0;"><strong>❌ Problema: "Erro ao enviar formulário"</strong></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    <strong>Solução:</strong> Verifique se todos os campos obrigatórios estão preenchidos. 
                    Tente novamente em alguns minutos.
                </p>
            </div>

            <div style="padding: 16px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px;">
                <p style="color: #f85149; margin: 0 0 8px 0;"><strong>❌ Problema: "Email não recebido"</strong></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    <strong>Solução:</strong> Verifique a pasta de spam. Aguarde alguns minutos. 
                    Se não receber, contacte suporte.
                </p>
            </div>

            <div style="padding: 16px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px;">
                <p style="color: #f85149; margin: 0 0 8px 0;"><strong>❌ Problema: "Documento não aparece"</strong></p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    <strong>Solução:</strong> Verifique se o arquivo foi enviado corretamente. 
                    Tente fazer upload novamente com um navegador diferente.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- CONTACT SUPPORT -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-headset"></i>
            Precisa de Ajuda Adicional?
        </h3>
    </div>
    <div class="card-body">
        <p style="color: #ccc; margin-bottom: 20px;">
            Se não encontrou a resposta que procura, entre em contato com nosso suporte:
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div style="padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <p style="color: #00ff88; margin: 0 0 8px 0; font-weight: 600;">
                    <i class="fa-solid fa-envelope"></i>
                    Email
                </p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    support@visiongreen.com
                </p>
            </div>
            <div style="padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <p style="color: #58a6ff; margin: 0 0 8px 0; font-weight: 600;">
                    <i class="fa-solid fa-phone"></i>
                    Telefone
                </p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    +258 (21) 123-4567
                </p>
            </div>
            <div style="padding: 16px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                <p style="color: #d29922; margin: 0 0 8px 0; font-weight: 600;">
                    <i class="fa-solid fa-clock"></i>
                    Horário
                </p>
                <p style="color: #999; margin: 0; font-size: 0.875rem;">
                    Seg-Sex: 8h às 18h (Hora de Moçambique)
                </p>
            </div>
        </div>
        
        <div style="margin-top: 20px; padding: 16px; background: rgba(35,134,54,0.1); border-left: 3px solid #00ff88; border-radius: 6px;">
            <p style="color: #ccc; margin: 0;">
                <strong>💡 Dica:</strong> Quando contactar suporte, inclua:
            </p>
            <ul style="color: #999; margin: 8px 0 0 20px; font-size: 0.875rem;">
                <li>Código de erro ou mensagem exata</li>
                <li>Captura de tela do problema</li>
                <li>Seu navegador e sistema operacional</li>
                <li>Passos para reproduzir o problema</li>
            </ul>
        </div>
    </div>
</div>

<!-- FOOTER -->
<div style="margin-top: 60px; padding: 40px; text-align: center; background: rgba(0,0,0,0.3); border-radius: 12px; border: 1px solid var(--border);">
    <p style="color: #666; margin: 0 0 16px 0; font-size: 0.875rem;">
        Versão: 2.0 | Última atualização: <?= date('d/m/Y') ?>
    </p>
    <p style="color: #999; margin: 0; font-size: 0.813rem;">
        VisionGreen © 2024. Todos os direitos reservados.
    </p>
</div>

<style>
    .card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: rgba(0,0,0,0.2);
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-body {
        padding: 24px;
    }

    .mb-4 {
        margin-bottom: 30px;
    }

    .faq-item {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
    }

    .faq-item:last-child {
        border-bottom: none;
    }

    .faq-item h4 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: color 0.3s;
    }

    .faq-item h4:hover {
        color: var(--accent);
    }

    .faq-item h4 i {
        transition: transform 0.3s;
    }

    .faq-answer {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid rgba(255,255,255,0.05);
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
        }
        to {
            opacity: 1;
            max-height: 500px;
        }
    }

    kbd {
        padding: 2px 6px;
        background: rgba(0,0,0,0.5);
        border: 1px solid var(--border);
        border-radius: 4px;
        color: var(--accent);
        font-family: monospace;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>

<script>
    function toggleFAQ(element) {
        const answer = element.nextElementSibling;
        const icon = element.querySelector('i');
        
        if (answer.style.display === 'none' || answer.style.display === '') {
            answer.style.display = 'block';
            icon.style.transform = 'rotate(90deg)';
        } else {
            answer.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
</script>