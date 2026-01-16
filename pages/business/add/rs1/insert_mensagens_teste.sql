-- =====================================================
-- SCRIPT DE TESTE: Mensagens de Exemplo
-- Execute este script se não tiver mensagens de teste
-- =====================================================

-- IMPORTANTE: Substitua {SEU_USER_ID} pelo ID do usuário que você está logado
-- Por exemplo, se seu user_id é 3, substitua todas as ocorrências de {SEU_USER_ID} por 3

-- =====================================================
-- 1. MENSAGENS DO SISTEMA (sender_id = NULL)
-- =====================================================

-- Mensagem crítica do sistema
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'security', 'critical', 
'⚠️ Ação Urgente Necessária', 
'Detectamos uma tentativa de acesso não autorizado à sua conta. Por favor, altere sua senha imediatamente e ative a autenticação de dois fatores.',
'unread', NOW() - INTERVAL 2 HOUR);

-- Mensagem de alerta do sistema
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'alert', 'high', 
'Atualização Importante do Sistema', 
'Uma nova versão do VisionGreen Pro estará disponível em 24 horas. A atualização incluirá melhorias de segurança e novas funcionalidades.',
'unread', NOW() - INTERVAL 5 HOUR);

-- Mensagem informativa do sistema
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'chat', 'medium', 
'Bem-vindo ao VisionGreen Pro!', 
'Obrigado por escolher o VisionGreen Pro. Estamos aqui para ajudar sua empresa a crescer. Se precisar de suporte, não hesite em nos contatar.',
'read', NOW() - INTERVAL 1 DAY);

-- Mensagem de auditoria
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'audit', 'low', 
'Relatório Mensal de Atividades', 
'Seu relatório mensal está disponível. Neste mês você realizou 45 transações totalizando MT 125,000.00.',
'read', NOW() - INTERVAL 2 DAY);

-- =====================================================
-- 2. MENSAGENS DE OUTROS USUÁRIOS
-- =====================================================
-- NOTA: Você precisa ter outros usuários no sistema para isso funcionar
-- Se não tiver, pule esta seção ou crie usuários primeiro

-- Exemplo: Mensagem de outro usuário (substitua {SENDER_USER_ID} por um ID real)
-- INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
-- ({SENDER_USER_ID}, {SEU_USER_ID}, 'chat', 'medium', 
-- 'Proposta de Parceria', 
-- 'Olá! Vi seu perfil e gostaria de discutir uma possível parceria. Podemos agendar uma reunião?',
-- 'unread', NOW() - INTERVAL 3 HOUR);

-- =====================================================
-- 3. MENSAGENS DE TESTE DIVERSAS (do sistema)
-- =====================================================

-- Erro do sistema
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'system_error', 'high', 
'Erro ao Processar Transação', 
'Ocorreu um erro temporário ao processar sua última transação. Nossa equipe já foi notificada e está trabalhando na solução.',
'unread', NOW() - INTERVAL 1 HOUR);

-- Mensagem de sucesso
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'chat', 'low', 
'✅ Pagamento Confirmado', 
'Seu pagamento de MT 5,000.00 foi confirmado com sucesso. Obrigado!',
'read', NOW() - INTERVAL 6 HOUR);

-- Mensagem de lembrete
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'alert', 'medium', 
'Lembrete: Renovação da Assinatura', 
'Sua assinatura será renovada automaticamente em 7 dias. Certifique-se de que seus dados de pagamento estão atualizados.',
'unread', NOW() - INTERVAL 30 MINUTE);

-- Mensagem com múltiplas linhas
INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'chat', 'low', 
'Dicas de Uso do Dashboard', 
'Aqui estão algumas dicas para aproveitar melhor o VisionGreen Pro:

1. Configure alertas personalizados
2. Exporte relatórios mensais
3. Integre com seus sistemas existentes
4. Acompanhe métricas em tempo real

Entre em contato se tiver dúvidas!',
'read', NOW() - INTERVAL 3 DAY);

-- =====================================================
-- 4. MENSAGENS ANTIGAS (para testar scroll e datas)
-- =====================================================

INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'chat', 'low', 
'Confirmação de Cadastro', 
'Seu cadastro foi concluído com sucesso. Bem-vindo à plataforma!',
'read', NOW() - INTERVAL 30 DAY);

INSERT INTO notifications (sender_id, receiver_id, category, priority, subject, message, status, created_at) VALUES
(NULL, {SEU_USER_ID}, 'audit', 'low', 
'Primeiro Login', 
'Este é seu primeiro acesso ao sistema. Aproveite todos os recursos disponíveis!',
'read', NOW() - INTERVAL 35 DAY);

-- =====================================================
-- VERIFICAR OS DADOS
-- =====================================================

-- Execute esta query para verificar se as mensagens foram inseridas:
-- SELECT * FROM notifications WHERE receiver_id = {SEU_USER_ID} ORDER BY created_at DESC;

-- =====================================================
-- NOTA IMPORTANTE
-- =====================================================
-- Lembre-se de substituir {SEU_USER_ID} pelo seu ID real antes de executar!
-- Para descobrir seu ID, execute:
-- SELECT id, nome, email FROM users WHERE type = 'company' ORDER BY id DESC LIMIT 5;
