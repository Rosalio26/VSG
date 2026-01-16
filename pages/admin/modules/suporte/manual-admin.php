<?php
/**
 * ================================================================================
 * VISIONGREEN - MANUAL DO SUPERADMINISTRADOR
 * Módulo: modules/suporte/manual-admin.php
 * Descrição: Documentação completa para superadministradores
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

if (!$isSuperAdmin) {
    echo '<div class="alert error">
            <i class="fa-solid fa-lock"></i>
            <div><strong>Erro:</strong> Este manual é restrito apenas para Superadministradores.</div>
          </div>';
    exit;
}
?>

<!-- PAGE HEADER -->
<div style="margin-bottom: 40px;">
    <h1 style="color: #fff; margin: 0 0 8px 0; font-size: 2rem;">
        <i class="fa-solid fa-book-bookmark"></i>
        Manual do Superadministrador
    </h1>
    <p style="color: #888; font-size: 0.938rem; margin: 0;">
        Documentação completa e detalhada para o uso do painel VisionGreen como Superadministrador
    </p>
</div>

<!-- TABLE OF CONTENTS -->
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-list"></i>
            Índice de Conteúdo
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="toc-item" onclick="scrollToSection('introducao')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>1. Introdução e Visão Geral</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('dashboard')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>2. Dashboard Principal</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('auditores')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>3. Gerenciamento de Auditores</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('usuarios')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>4. Gerenciamento de Usuários</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('empresas')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>5. Verificação de Empresas</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('seguranca')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>6. Segurança e Autenticação</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('relatorios')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>7. Relatórios e Análises</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('notificacoes')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>8. Sistema de Notificações</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('logs')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>9. Auditoria e Logs</span>
            </div>
            <div class="toc-item" onclick="scrollToSection('troubleshooting')">
                <i class="fa-solid fa-arrow-right"></i>
                <span>10. Solução de Problemas</span>
            </div>
        </div>
    </div>
</div>

<!-- SEÇÃO 1: INTRODUÇÃO -->
<section id="introducao" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-circle-info"></i>
        1. Introdução e Visão Geral
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">O que é um Superadministrador?</h3>
        </div>
        <div class="card-body">
            <p>Um Superadministrador tem acesso total ao sistema VisionGreen. Com este acesso, você pode:</p>
            <ul style="color: #ccc; line-height: 2;">
                <li>✓ Gerenciar todos os auditores do sistema</li>
                <li>✓ Visualizar e controlar usuários pessoa física e empresas</li>
                <li>✓ Aprovar ou rejeitar documentos de empresas</li>
                <li>✓ Gerar relatórios completos e análises</li>
                <li>✓ Monitorar logs de segurança e auditoria</li>
                <li>✓ Configurar parâmetros de sistema</li>
                <li>✓ Gerenciar planos de subscrição e pagamentos</li>
                <li>✓ Acessar dados sensíveis de todas as contas</li>
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Responsabilidades Importantes</h3>
        </div>
        <div class="card-body">
            <div style="background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; padding: 16px; border-radius: 8px; color: #ccc;">
                <p style="margin: 0; font-weight: 600; color: #f85149; margin-bottom: 8px;">⚠️ Segurança Crítica</p>
                <p style="margin: 0;">
                    Como Superadministrador, você tem a responsabilidade de proteger dados sensíveis. Sempre mantenha sua senha segura, 
                    use autenticação de dois fatores quando disponível, e revise regularmente os logs de auditoria.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- SEÇÃO 2: DASHBOARD -->
<section id="dashboard" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-gauge-high"></i>
        2. Dashboard Principal
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Componentes do Dashboard</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div style="padding: 16px; background: rgba(35,134,54,0.1); border-radius: 8px; border-left: 3px solid #00ff88;">
                    <h4 style="color: #00ff88; margin: 0 0 8px 0;">📊 Estatísticas em Tempo Real</h4>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">
                        Visualize números atualizados de usuários ativos, empresas registradas, receita mensal e taxa de aprovação.
                    </p>
                </div>
                <div style="padding: 16px; background: rgba(56,139,253,0.1); border-radius: 8px; border-left: 3px solid #58a6ff;">
                    <h4 style="color: #58a6ff; margin: 0 0 8px 0;">📈 Gráficos de Tendência</h4>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">
                        Acompanhe crescimento, churn, uso de armazenamento e outras métricas importantes ao longo do tempo.
                    </p>
                </div>
                <div style="padding: 16px; background: rgba(248,81,73,0.1); border-radius: 8px; border-left: 3px solid #f85149;">
                    <h4 style="color: #f85149; margin: 0 0 8px 0;">⚡ Alertas Críticos</h4>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">
                        Receba notificações instantâneas sobre atividades suspeitas, tentativas de acesso, ou problemas no sistema.
                    </p>
                </div>
                <div style="padding: 16px; background: rgba(158,106,3,0.1); border-radius: 8px; border-left: 3px solid #d29922;">
                    <h4 style="color: #d29922; margin: 0 0 8px 0;">📋 Tarefas Pendentes</h4>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">
                        Veja documentos aguardando aprovação, usuários novos pendentes e outras ações necessárias.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Como Usar o Dashboard</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 1.8;">
                <li><strong>Ao entrar no sistema</strong>, você é automaticamente direcionado para o Dashboard</li>
                <li><strong>Verifique primeiro</strong> os alertas críticos (sino vermelho no canto superior direito)</li>
                <li><strong>Analise as métricas</strong> para identificar tendências e anomalias</li>
                <li><strong>Acesse rápido</strong> as abas de Pendências para ver trabalho em espera</li>
                <li><strong>Use os gráficos</strong> para tomar decisões baseadas em dados</li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 3: AUDITORES -->
<section id="auditores" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-user-shield"></i>
        3. Gerenciamento de Auditores
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">O que são Auditores?</h3>
        </div>
        <div class="card-body">
            <p style="color: #ccc;">
                Auditores são administradores da plataforma que ajudam a gerenciar usuários e empresas. Existem dois níveis:
            </p>
            <div style="display: grid; gap: 16px; margin-top: 16px;">
                <div style="padding: 16px; background: rgba(255,204,0,0.1); border-radius: 8px; border-left: 3px solid #ffcc00;">
                    <h4 style="color: #ffcc00; margin: 0 0 8px 0;">👑 Superadministrador</h4>
                    <p style="color: #999; margin: 0;">Acesso total ao sistema. Pode gerenciar auditores, usuários, empresas e configurações.</p>
                </div>
                <div style="padding: 16px; background: rgba(35,134,54,0.1); border-radius: 8px; border-left: 3px solid #00ff88;">
                    <h4 style="color: #00ff88; margin: 0 0 8px 0;">👤 Administrador</h4>
                    <p style="color: #999; margin: 0;">Acesso limitado. Pode visualizar dados, gerenciar pendências, mas não tem acesso total ao sistema.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Criar Novo Auditor</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Acesse <strong>Administração → Auditores → Lista de Auditores</strong></li>
                <li>Clique no botão <strong>"Criar Novo Auditor"</strong> (ícone +)</li>
                <li>Preencha os campos:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li><strong>Nome:</strong> Nome completo do auditor</li>
                        <li><strong>Email:</strong> Email corporativo único</li>
                        <li><strong>Email Pessoal:</strong> Email pessoal para recuperação</li>
                        <li><strong>Telefone:</strong> Contato telefônico</li>
                        <li><strong>Cargo:</strong> Selecione Administrador ou Superadministrador</li>
                    </ul>
                </li>
                <li>Clique em <strong>"Criar"</strong></li>
                <li>Uma <strong>senha temporária será gerada</strong> e enviada por email</li>
                <li>O auditor deve <strong>alterar a senha</strong> no primeiro login</li>
            </ol>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Editar Auditor</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Vá para <strong>Administração → Auditores → Lista de Auditores</strong></li>
                <li>Clique na linha do auditor que deseja editar</li>
                <li>Você será direcionado para a <strong>página de detalhes</strong></li>
                <li>Clique no botão <strong>"Editar"</strong></li>
                <li>Altere os dados necessários</li>
                <li>Clique em <strong>"Salvar"</strong></li>
            </ol>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Visualizar Histórico do Auditor</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Clique na linha do auditor na lista</li>
                <li>Você verá na <strong>página de detalhes</strong>:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>Informações pessoais</li>
                        <li>Status online/offline</li>
                        <li>Datas de criação e última atividade</li>
                        <li><strong>Timeline de ações</strong> (últimas 50 ações)</li>
                        <li>Estatísticas de uso</li>
                    </ul>
                </li>
                <li>Analise o histórico para <strong>monitorar atividades</strong></li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 4: USUÁRIOS -->
<section id="usuarios" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-users-gear"></i>
        4. Gerenciamento de Usuários
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Tipos de Usuários</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; gap: 16px;">
                <div style="padding: 16px; background: rgba(56,139,253,0.1); border-radius: 8px; border-left: 3px solid #58a6ff;">
                    <h4 style="color: #58a6ff; margin: 0 0 8px 0;">👤 Pessoa Física (Person)</h4>
                    <p style="color: #999; margin: 0;">Usuários individuais que utilizam a plataforma para seus próprios negócios.</p>
                </div>
                <div style="padding: 16px; background: rgba(35,134,54,0.1); border-radius: 8px; border-left: 3px solid #00ff88;">
                    <h4 style="color: #00ff88; margin: 0 0 8px 0;">🏢 Empresa (Company)</h4>
                    <p style="color: #999; margin: 0;">Entidades comerciais que requerem verificação de documentos antes de ativar.</p>
                </div>
                <div style="padding: 16px; background: rgba(248,81,73,0.1); border-radius: 8px; border-left: 3px solid #f85149;">
                    <h4 style="color: #f85149; margin: 0 0 8px 0;">👨‍💼 Administrador (Admin)</h4>
                    <p style="color: #999; margin: 0;">Funcionários da plataforma com direitos administrativos.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Listar Usuários</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Acesse <strong>Administração → Usuários</strong></li>
                <li>Você verá uma tabela com todos os usuários cadastrados</li>
                <li>Use <strong>filtros e busca</strong> para encontrar usuários específicos</li>
                <li>Colunas visíveis:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>Nome e UID (ID único)</li>
                        <li>Email</li>
                        <li>Tipo de conta (Person, Company, Admin)</li>
                        <li>Status (Pendente, Ativo, Bloqueado)</li>
                        <li>Data de criação</li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Bloquear/Desbloquear Usuário</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Localize o usuário na lista</li>
                <li>Clique na linha para visualizar detalhes</li>
                <li>Vá para <strong>"Ações"</strong> ou <strong>"Segurança"</strong></li>
                <li>Selecione <strong>"Bloquear Conta"</strong> ou <strong>"Desbloquear"</strong></li>
                <li>Confirme a ação</li>
                <li>Uma notificação será enviada ao usuário</li>
            </ol>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Resetar Senha de Usuário</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Na página de detalhes do usuário, clique em <strong>"Segurança"</strong></li>
                <li>Selecione <strong>"Resetar Senha"</strong></li>
                <li>Uma <strong>nova senha temporária será gerada</strong></li>
                <li>A senha será <strong>enviada por email</strong> ao usuário</li>
                <li>O usuário deverá alterar a senha no próximo login</li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 5: EMPRESAS -->
<section id="empresas" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-building"></i>
        5. Verificação de Empresas
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Processo de Verificação</h3>
        </div>
        <div class="card-body">
            <p style="color: #ccc; margin-bottom: 20px;">
                As empresas passam por um processo de verificação de documentos antes de serem ativadas. Como Superadministrador, você é responsável por aprovar ou rejeitar estes documentos.
            </p>
            <div style="background: #000; border: 1px solid var(--border); border-radius: 8px; padding: 20px;">
                <p style="color: #999; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; margin: 0 0 16px 0;">Fluxo de Status</p>
                <div style="display: flex; align-items: center; gap: 12px; color: #ccc;">
                    <div style="text-align: center;">
                        <div style="background: rgba(158,106,3,0.3); border: 1px solid #d29922; border-radius: 6px; padding: 8px 12px; font-weight: 600; color: #d29922; font-size: 0.75rem;">PENDENTE</div>
                        <div style="font-size: 0.75rem; color: #666; margin-top: 8px;">Aguardando<br/>análise</div>
                    </div>
                    <i class="fa-solid fa-arrow-right" style="color: #666;"></i>
                    <div style="text-align: center;">
                        <div style="background: rgba(35,134,54,0.3); border: 1px solid #00ff88; border-radius: 6px; padding: 8px 12px; font-weight: 600; color: #00ff88; font-size: 0.75rem;">APROVADO</div>
                        <div style="font-size: 0.75rem; color: #666; margin-top: 8px;">Empresa<br/>ativada</div>
                    </div>
                    <i class="fa-solid fa-arrow-right" style="color: #666;"></i>
                    <div style="text-align: center;">
                        <div style="background: rgba(248,81,73,0.3); border: 1px solid #f85149; border-radius: 6px; padding: 8px 12px; font-weight: 600; color: #f85149; font-size: 0.75rem;">REJEITADO</div>
                        <div style="font-size: 0.75rem; color: #666; margin-top: 8px;">Rejeição<br/>informada</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Analisar Documentos de Empresa</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Acesse <strong>Dashboard → Pendências</strong></li>
                <li>Procure por <strong>"Documentos Pendentes"</strong></li>
                <li>Clique em uma empresa para <strong>visualizar seus documentos</strong>:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li><strong>Alvará de Funcionamento:</strong> Documento legal da empresa</li>
                        <li><strong>Identificação Fiscal (NUIT):</strong> Número de imposto</li>
                        <li><strong>Comprovante de Endereço:</strong> Prova do local da empresa</li>
                        <li><strong>Documentos Adicionais:</strong> Se necessário</li>
                    </ul>
                </li>
                <li><strong>Analise cada documento</strong> com cuidado</li>
            </ol>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Aprovar Documentos</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Após verificar todos os documentos, clique em <strong>"Aprovar"</strong></li>
                <li>Confirme que:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>✓ Todos os documentos estão legítimos</li>
                        <li>✓ Datas não estão expiradas</li>
                        <li>✓ Informações correspondem</li>
                        <li>✓ Não há bandeiras vermelhas</li>
                    </ul>
                </li>
                <li>Clique em <strong>"Confirmar Aprovação"</strong></li>
                <li>Um <strong>email será enviado</strong> à empresa confirmando a aprovação</li>
                <li>A <strong>conta será ativada</strong> automaticamente</li>
            </ol>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Rejeitar Documentos</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Se houver problemas, clique em <strong>"Rejeitar"</strong></li>
                <li><strong>Selecione os motivos</strong> da rejeição:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>Documentos ilegíveis</li>
                        <li>Documentos expirados</li>
                        <li>Informações inconsistentes</li>
                        <li>Documentos inválidos</li>
                        <li>Outro (especifique)</li>
                    </ul>
                </li>
                <li><strong>Adicione um comentário</strong> detalhando o problema</li>
                <li>Clique em <strong>"Enviar Rejeição"</strong></li>
                <li>Um <strong>email será enviado</strong> à empresa com instruções para reenviar</li>
                <li>A empresa pode <strong>reencarregar documentos</strong> após corrigir</li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 6: SEGURANÇA -->
<section id="seguranca" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-shield-halved"></i>
        6. Segurança e Autenticação
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Gerenciar Autenticação</h3>
        </div>
        <div class="card-body">
            <p style="color: #ccc; margin-bottom: 16px;">
                Acesse <strong>Páginas → Autenticação e Segurança</strong> para gerenciar logins e tentativas falhadas.
            </p>
            <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 16px; color: #999;">
                <p style="margin: 0 0 8px 0;"><strong style="color: #ccc;">Abas Disponíveis:</strong></p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Autenticação:</strong> Logs de login com IP, navegador e SO</li>
                    <li><strong>Empresas:</strong> Listagem de empresas com status</li>
                    <li><strong>Usuários:</strong> Usuários pessoa física cadastrados</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Monitorar Tentativas Falhadas</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Na aba <strong>"Autenticação"</strong>, procure por <strong>"Tentativas de Login Falhadas"</strong></li>
                <li>Revise a lista regularmente</li>
                <li>Se notar um <strong>padrão suspeito</strong>:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>Mesmo IP com múltiplas tentativas</li>
                        <li>Tentativas de força bruta</li>
                        <li>IPs de países suspeitos</li>
                    </ul>
                </li>
                <li><strong>Investigue</strong> e tome ação (bloqueio de IP, etc.)</li>
            </ol>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Renovar Sua Senha de Superadmin</h3>
        </div>
        <div class="card-body">
            <div style="background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; padding: 16px; border-radius: 8px; color: #ccc; margin-bottom: 16px;">
                <p style="margin: 0; color: #f85149; font-weight: 600;">⏰ Importante</p>
                <p style="margin: 8px 0 0 0; font-size: 0.875rem;">
                    Como Superadministrador, sua sessão expira em <strong>1 hora</strong> de inatividade. Renove sua senha regularmente.
                </p>
            </div>
            <ol style="color: #ccc; line-height: 2;">
                <li>Clique no <strong>ícone de rotação</strong> na seção de segurança (sidebar)</li>
                <li>Uma <strong>nova senha será gerada</strong> automaticamente</li>
                <li>Você receberá um <strong>modal com a nova senha</strong></li>
                <li><strong>Copie a senha</strong> para um local seguro</li>
                <li>A nova senha será <strong>enviada por email</strong></li>
                <li>Seu timer de <strong>sessão será resetado</strong></li>
            </ol>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Visualizar Logs de Auditoria Pessoal</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Acesse <strong>Páginas → Perfil do Admin</strong></li>
                <li>Você verá sua:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>Informações pessoais</li>
                        <li>Status online/offline</li>
                        <li>Estatísticas de ações</li>
                        <li><strong>Timeline completa de suas ações</strong></li>
                    </ul>
                </li>
                <li>Use para <strong>revisar suas próprias atividades</strong></li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 7: RELATÓRIOS -->
<section id="relatorios" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-chart-line"></i>
        7. Relatórios e Análises
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Gerar Relatórios</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Acesse <strong>Dados de Base → Relatórios</strong></li>
                <li>Selecione <strong>o tipo de relatório</strong>:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li><strong>Usuários:</strong> Estatísticas de contas</li>
                        <li><strong>Empresas:</strong> Status de documentos</li>
                        <li><strong>Financeiro:</strong> Receita e transações</li>
                        <li><strong>Segurança:</strong> Logs e tentativas</li>
                        <li><strong>Personalizado:</strong> Crie seu próprio</li>
                    </ul>
                </li>
                <li><strong>Defina o período</strong> (data inicial e final)</li>
                <li>Clique em <strong>"Gerar"</strong></li>
                <li><strong>Exporte em PDF ou Excel</strong> se necessário</li>
            </ol>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Analisar Métricas</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Acesse <strong>Dashboard → Análise de Contas</strong></li>
                <li>Analise os seguintes KPIs:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li><strong>Taxa de Crescimento:</strong> Novos usuários por mês</li>
                        <li><strong>Taxa de Churn:</strong> Usuários que cancelaram</li>
                        <li><strong>Receita Mensal Recorrente (MRR):</strong> Faturamento consistente</li>
                        <li><strong>Saúde da Empresa:</strong> Score de risco de cancelamento</li>
                        <li><strong>Utilização:</strong> Uso médio do armazenamento</li>
                    </ul>
                </li>
                <li><strong>Identifique tendências</strong> e anomalias</li>
                <li><strong>Tome decisões</strong> baseadas nos dados</li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 8: NOTIFICAÇÕES -->
<section id="notificacoes" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-bell"></i>
        8. Sistema de Notificações
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Tipos de Notificações</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; gap: 16px;">
                <div style="padding: 12px; background: rgba(35,134,54,0.1); border-left: 3px solid #00ff88; border-radius: 6px;">
                    <p style="color: #00ff88; margin: 0 0 4px 0; font-weight: 600;">💬 Chat</p>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">Mensagens de outros administradores</p>
                </div>
                <div style="padding: 12px; background: rgba(248,81,73,0.1); border-left: 3px solid #f85149; border-radius: 6px;">
                    <p style="color: #f85149; margin: 0 0 4px 0; font-weight: 600;">⚠️ Alertas de Segurança</p>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">Atividades suspeitas, tentativas de acesso</p>
                </div>
                <div style="padding: 12px; background: rgba(158,106,3,0.1); border-left: 3px solid #d29922; border-radius: 6px;">
                    <p style="color: #d29922; margin: 0 0 4px 0; font-weight: 600;">🚨 Erros de Sistema</p>
                    <p style="color: #999; margin: 0; font-size: 0.875rem;">Problemas técnicos que requerem ação</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Gerenciar Notificações</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Clique no <strong>sino (🔔)</strong> no canto superior direito</li>
                <li>Você verá as <strong>últimas notificações</strong></li>
                <li>Clique em uma notificação para <strong>visualizar completa</strong></li>
                <li>Use <strong>"Marcar como Lida"</strong> para remover do dropdown</li>
                <li>Acesse <strong>Comunicação → Mensagens</strong> para ver histórico completo</li>
                <li>Clique <strong>"Limpar Notificações"</strong> para marcar todas como lidas</li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 9: LOGS -->
<section id="logs" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-file-signature"></i>
        9. Auditoria e Logs
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Visualizar Logs de Auditoria</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li>Acesse <strong>Administração → Logs de Auditoria</strong></li>
                <li>Você verá todas as ações de todos os administradores</li>
                <li>Use <strong>filtros</strong> para:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>Filtrar por administrador específico</li>
                        <li>Filtrar por tipo de ação (CREATE, UPDATE, DELETE, etc.)</li>
                        <li>Filtrar por data</li>
                        <li>Buscar por IP ou detalhes</li>
                    </ul>
                </li>
                <li><strong>Revise regularmente</strong> para garantir conformidade</li>
            </ol>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Entender as Ações Registradas</h3>
        </div>
        <div class="card-body">
            <p style="color: #ccc; margin-bottom: 16px;">Cada ação registrada inclui:</p>
            <ul style="color: #999; line-height: 2;">
                <li><strong>Data/Hora:</strong> Quando a ação foi realizada</li>
                <li><strong>Administrador:</strong> Quem realizou a ação</li>
                <li><strong>Ação:</strong> O que foi feito (CREATE_USER, UPDATE_COMPANY, etc.)</li>
                <li><strong>IP Address:</strong> De onde a ação foi feita</li>
                <li><strong>Detalhes:</strong> Informações adicionais sobre a ação</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Investigar Atividades Suspeitas</h3>
        </div>
        <div class="card-body">
            <ol style="color: #ccc; line-height: 2;">
                <li><strong>Procure por padrões suspeitos</strong>:
                    <ul style="margin-top: 10px; margin-left: 20px; color: #999;">
                        <li>Múltiplas ações em poucos segundos</li>
                        <li>IPs diferentes para o mesmo usuário</li>
                        <li>Ações fora de horários normais</li>
                        <li>Exclusões em massa de registros</li>
                    </ul>
                </li>
                <li><strong>Clique em uma ação</strong> para ver detalhes completos</li>
                <li>Se necessário, <strong>contacte o administrador</strong> envolvido</li>
                <li>Documente qualquer <strong>incidente de segurança</strong></li>
                <li>Considere <strong>revogar acesso</strong> se necessário</li>
            </ol>
        </div>
    </div>
</section>

<!-- SEÇÃO 10: TROUBLESHOOTING -->
<section id="troubleshooting" class="manual-section">
    <h2 class="section-title">
        <i class="fa-solid fa-wrench"></i>
        10. Solução de Problemas
    </h2>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Problemas Comuns e Soluções</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; gap: 16px;">
                <div style="border-left: 3px solid #f85149; padding: 16px; background: rgba(248,81,73,0.05); border-radius: 6px;">
                    <h4 style="color: #f85149; margin: 0 0 8px 0;">❌ Problema: Sessão Expirada</h4>
                    <p style="color: #999; margin: 0 0 8px 0;"><strong>Solução:</strong></p>
                    <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                        <li>Como Superadmin, você tem 1 hora de inatividade</li>
                        <li>Renove sua senha clicando no ícone de rotação</li>
                        <li>Faça login novamente se necessário</li>
                    </ul>
                </div>

                <div style="border-left: 3px solid #f85149; padding: 16px; background: rgba(248,81,73,0.05); border-radius: 6px;">
                    <h4 style="color: #f85149; margin: 0 0 8px 0;">❌ Problema: Documento Não Carrega</h4>
                    <p style="color: #999; margin: 0 0 8px 0;"><strong>Solução:</strong></p>
                    <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                        <li>Atualize a página (F5)</li>
                        <li>Limpe o cache do navegador</li>
                        <li>Tente com outro navegador</li>
                        <li>Verifique permissões de arquivo</li>
                    </ul>
                </div>

                <div style="border-left: 3px solid #f85149; padding: 16px; background: rgba(248,81,73,0.05); border-radius: 6px;">
                    <h4 style="color: #f85149; margin: 0 0 8px 0;">❌ Problema: Email Não Enviado</h4>
                    <p style="color: #999; margin: 0 0 8px 0;"><strong>Solução:</strong></p>
                    <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                        <li>Verifique se o email está correto</li>
                        <li>Verifique a pasta de spam</li>
                        <li>Tente reenviar de um período diferente</li>
                        <li>Contacte suporte técnico se persistir</li>
                    </ul>
                </div>

                <div style="border-left: 3px solid #f85149; padding: 16px; background: rgba(248,81,73,0.05); border-radius: 6px;">
                    <h4 style="color: #f85149; margin: 0 0 8px 0;">❌ Problema: Erro ao Aprovar Documentos</h4>
                    <p style="color: #999; margin: 0 0 8px 0;"><strong>Solução:</strong></p>
                    <ul style="color: #999; margin: 0; padding-left: 20px; font-size: 0.875rem;">
                        <li>Confirme que todos os campos estão preenchidos</li>
                        <li>Verifique se há documentos faltando</li>
                        <li>Tente novamente em alguns minutos</li>
                        <li>Contacte suporte se o erro persistir</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Contactar Suporte</h3>
        </div>
        <div class="card-body">
            <p style="color: #ccc;">Se encontrar um problema que não consegue resolver:</p>
            <ol style="color: #999; line-height: 2; margin-top: 12px;">
                <li>Anote o <strong>código de erro</strong> exato</li>
                <li>Tire uma <strong>captura de tela</strong> do problema</li>
                <li>Revise os <strong>logs de auditoria</strong> relevantes</li>
                <li>Contacte o <strong>suporte técnico</strong> com estas informações</li>
                <li>Inclua seu <strong>navegador e sistema operacional</strong></li>
            </ol>
        </div>
    </div>
</section>

<!-- FOOTER -->
<div style="margin-top: 60px; padding: 40px; text-align: center; background: rgba(0,0,0,0.3); border-radius: 12px; border: 1px solid var(--border);">
    <p style="color: #666; margin: 0 0 16px 0; font-size: 0.875rem;">
        Última atualização: <?= date('d/m/Y H:i') ?>
    </p>
    <p style="color: #999; margin: 0; font-size: 0.813rem;">
        Este manual foi desenvolvido para ajudar Superadministradores a utilizar o VisionGreen de forma eficiente e segura.
    </p>
</div>

<style>
    .manual-section {
        margin-bottom: 50px;
    }

    .section-title {
        color: #fff;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 24px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 16px;
        border-bottom: 2px solid rgba(0,255,136,0.2);
    }

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

    .mb-3 {
        margin-bottom: 20px;
    }

    .mb-4 {
        margin-bottom: 30px;
    }

    .toc-item {
        padding: 12px 16px;
        background: rgba(35,134,54,0.1);
        border: 1px solid rgba(0,255,136,0.2);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #ccc;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .toc-item:hover {
        background: rgba(35,134,54,0.2);
        border-color: var(--accent);
        color: var(--accent);
        transform: translateX(4px);
    }

    .alert {
        padding: 16px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .alert.error {
        background: rgba(248,81,73,0.1);
        border: 1px solid rgba(248,81,73,0.3);
        color: #f85149;
    }

    ul {
        color: #ccc;
    }

    ol {
        color: #ccc;
    }

    p {
        color: #ccc;
    }
</style>

<script>
    function scrollToSection(sectionId) {
        const element = document.getElementById(sectionId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }
</script>