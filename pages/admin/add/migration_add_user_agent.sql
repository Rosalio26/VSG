-- ============================================================
-- MIGRATION: Adicionar user_agent à tabela admin_audit_logs
-- Data: 2026-01-14
-- Descrição: Adiciona coluna para registrar User-Agent do navegador
-- ============================================================

-- 1. Adicionar coluna user_agent
ALTER TABLE `admin_audit_logs` 
ADD COLUMN `user_agent` TEXT NULL DEFAULT NULL AFTER `ip_address`;

-- 2. Adicionar índice para melhor performance
ALTER TABLE `admin_audit_logs`
ADD INDEX `idx_admin_id_created` (`admin_id`, `created_at` DESC);

-- 3. Adicionar índice para busca por action
ALTER TABLE `admin_audit_logs`
ADD INDEX `idx_action` (`action`);

-- ============================================================
-- ROLLBACK (caso necessário)
-- ============================================================

-- Para reverter as mudanças, execute:
-- ALTER TABLE `admin_audit_logs` DROP COLUMN `user_agent`;
-- ALTER TABLE `admin_audit_logs` DROP INDEX `idx_admin_id_created`;
-- ALTER TABLE `admin_audit_logs` DROP INDEX `idx_action`;

-- ============================================================
-- VERIFICAÇÃO
-- ============================================================

-- Verificar estrutura da tabela
-- DESCRIBE admin_audit_logs;

-- Verificar índices
-- SHOW INDEX FROM admin_audit_logs;

-- ============================================================
-- EXEMPLO DE USO
-- ============================================================

/*
-- Inserir log com user_agent
INSERT INTO admin_audit_logs (admin_id, action, ip_address, user_agent, details)
VALUES (
    1, 
    'USER_UPDATE', 
    '192.168.1.100',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    '{"user_id": 123, "field": "status", "old": "inactive", "new": "active"}'
);

-- Buscar logs com filtro por role (Admin não vê SuperAdmins)
SELECT 
    al.id,
    al.action,
    al.ip_address,
    al.user_agent,
    al.created_at,
    u.nome,
    u.role
FROM admin_audit_logs al
LEFT JOIN users u ON al.admin_id = u.id
WHERE (
    u.role = 'admin' 
    OR u.role IS NULL 
    OR al.admin_id = 1  -- ID do admin atual
)
AND (
    u.role != 'superadmin' 
    OR u.role IS NULL
)
ORDER BY al.created_at DESC
LIMIT 50;
*/

-- ============================================================
-- STATUS
-- ============================================================

SELECT 'Migration completed successfully!' as status;
