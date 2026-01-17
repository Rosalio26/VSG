-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.44 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.14.0.7165
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table vsg.admin_audit_logs
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `admin_id` (`admin_id`) USING BTREE,
  KEY `idx_admin_id_created` (`admin_id`,`created_at` DESC),
  KEY `idx_action` (`action`),
  CONSTRAINT `admin_audit_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Dumping structure for table vsg.businesses
CREATE TABLE IF NOT EXISTS `businesses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tax_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tax_id_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `business_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `country` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `license_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_documentos` enum('pendente','aprovado','rejeitado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pendente',
  `motivo_rejeicao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `user_id_unique` (`user_id`) USING BTREE,
  UNIQUE KEY `tax_id_unique` (`tax_id`) USING BTREE,
  CONSTRAINT `fk_business_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Dumping structure for table vsg.company_growth_metrics
CREATE TABLE IF NOT EXISTS `company_growth_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `metric_date` date NOT NULL,
  `revenue` decimal(10,2) DEFAULT '0.00' COMMENT 'Receita do dia',
  `active_users` int DEFAULT '0' COMMENT 'Usuários ativos',
  `new_signups` int DEFAULT '0' COMMENT 'Novos cadastros',
  `churn_count` int DEFAULT '0' COMMENT 'Cancelamentos',
  `storage_used_gb` decimal(8,2) DEFAULT '0.00',
  `api_calls` int DEFAULT '0',
  `support_tickets` int DEFAULT '0',
  `satisfaction_score` decimal(3,2) DEFAULT '0.00' COMMENT 'Nota de 0 a 5',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_date` (`user_id`,`metric_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_metric_date` (`metric_date`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Dumping structure for table vsg.company_health_score
CREATE TABLE IF NOT EXISTS `company_health_score` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `score` int DEFAULT '50' COMMENT 'Score de 0 a 100',
  `payment_health` decimal(3,2) DEFAULT '0.00' COMMENT '0 a 1',
  `usage_health` decimal(3,2) DEFAULT '0.00' COMMENT '0 a 1',
  `engagement_health` decimal(3,2) DEFAULT '0.00' COMMENT '0 a 1',
  `support_health` decimal(3,2) DEFAULT '0.00' COMMENT '0 a 1',
  `risk_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `churn_probability` decimal(3,2) DEFAULT '0.00' COMMENT 'Probabilidade de cancelamento',
  `recommendations` json DEFAULT NULL COMMENT 'Recomendações para melhorar',
  `last_calculated` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  KEY `idx_score` (`score`),
  KEY `idx_risk_level` (`risk_level`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Dumping structure for table vsg.eco_certifications
CREATE TABLE IF NOT EXISTS `eco_certifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código da certificação',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome completo',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Descrição',
  `logo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL do logo',
  `verification_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL para verificação',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.employee_access_logs
CREATE TABLE IF NOT EXISTS `employee_access_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ação realizada',
  `module` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Módulo acessado',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_employee_id` (`employee_id`) USING BTREE,
  KEY `idx_action` (`action`) USING BTREE,
  KEY `idx_created_at` (`created_at`) USING BTREE,
  CONSTRAINT `fk_access_logs_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.employee_permissions
CREATE TABLE IF NOT EXISTS `employee_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL COMMENT 'ID do funcionário',
  `module` enum('mensagens','produtos','vendas','relatorios') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Módulo permitido',
  `can_view` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Pode visualizar',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Pode editar',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Pode deletar',
  `can_create` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Pode criar',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_employee_module` (`employee_id`,`module`) USING BTREE,
  KEY `idx_employee_id` (`employee_id`) USING BTREE,
  KEY `idx_module` (`module`) USING BTREE,
  CONSTRAINT `fk_permissions_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.employees
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT 'ID da empresa',
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome completo',
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Email',
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Telefone',
  `cargo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cargo/Função',
  `departamento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Departamento',
  `data_admissao` date NOT NULL COMMENT 'Data de admissão',
  `salario` decimal(10,2) DEFAULT NULL COMMENT 'Salário',
  `status` enum('ativo','inativo','ferias','afastado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `foto_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho da foto',
  `documento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nº do documento',
  `tipo_documento` enum('bi','passaporte','dire','nuit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'bi',
  `endereco` text COLLATE utf8mb4_unicode_ci COMMENT 'Endereço completo',
  `observacoes` text COLLATE utf8mb4_unicode_ci COMMENT 'Observações',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hash da senha',
  `pode_acessar_sistema` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Se pode fazer login',
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Se é primeiro acesso',
  `ultimo_login` datetime DEFAULT NULL COMMENT 'Data do último login',
  `token_primeiro_acesso` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token para definir senha',
  `token_expira_em` datetime DEFAULT NULL COMMENT 'Expiração do token',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Se está ativo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_email` (`user_id`,`email`) USING BTREE,
  KEY `idx_user_id` (`user_id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_cargo` (`cargo`) USING BTREE,
  KEY `idx_is_active` (`is_active`) USING BTREE,
  KEY `idx_data_admissao` (`data_admissao`),
  KEY `idx_departamento` (`departamento`),
  KEY `idx_email_login` (`email`,`pode_acessar_sistema`),
  KEY `idx_token` (`token_primeiro_acesso`),
  CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.form_config
CREATE TABLE IF NOT EXISTS `form_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `config_type` enum('boolean','integer','string','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_config_key` (`config_key`),
  KEY `idx_updated_by` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=341 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.login_attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` int DEFAULT '1',
  `last_attempt` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Dumping structure for table vsg.login_history
CREATE TABLE IF NOT EXISTS `login_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `browser` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `operating_system` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'success',
  `created_at` datetime DEFAULT (now()),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table vsg.login_history: ~0 rows (approximately)

-- Dumping structure for table vsg.login_logs
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `login_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `fk_logs_user` (`user_id`) USING BTREE,
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table vsg.login_logs: ~0 rows (approximately)

-- Dumping structure for table vsg.notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned DEFAULT NULL,
  `receiver_id` bigint unsigned NOT NULL,
  `reply_to` bigint unsigned DEFAULT NULL COMMENT 'ID da mensagem sendo respondida',
  `category` enum('chat','alert','security','system_error','audit') DEFAULT 'chat',
  `priority` enum('low','medium','high','critical') DEFAULT 'low',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','archived') DEFAULT 'unread',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_sender` (`sender_id`),
  KEY `idx_notifications_check` (`receiver_id`,`status`,`category`,`created_at` DESC),
  KEY `fk_notif_reply` (`reply_to`),
  CONSTRAINT `fk_notif_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_reply` FOREIGN KEY (`reply_to`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Dumping structure for table vsg.product_purchases
CREATE TABLE IF NOT EXISTS `product_purchases` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','cancelled','refunded') DEFAULT 'pending',
  `purchase_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `transaction_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_purchase_date` (`purchase_date`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `product_purchases_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `product_purchases_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Dumping structure for table vsg.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL COMMENT 'Empresa dona do produto',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do produto',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Descrição detalhada',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho da imagem',
  `category` enum('addon','service','consultation','training','other') COLLATE utf8mb4_unicode_ci DEFAULT 'addon' COMMENT 'Tipo de produto/negócio',
  `eco_category` enum('recyclable','reusable','biodegradable','sustainable','organic','zero_waste','energy_efficient','not_eco','unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'unknown' COMMENT 'Classificação ecológica',
  `price` decimal(10,2) NOT NULL COMMENT 'Preço do produto',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'MZN' COMMENT 'Moeda',
  `is_recurring` tinyint(1) DEFAULT '0' COMMENT 'Se é recorrente',
  `billing_cycle` enum('monthly','yearly','one_time') COLLATE utf8mb4_unicode_ci DEFAULT 'one_time' COMMENT 'Ciclo de cobrança',
  `stock_quantity` int DEFAULT NULL COMMENT 'Quantidade em estoque (NULL = ilimitado)',
  `eco_verified` tinyint(1) DEFAULT '0' COMMENT '0=pendente, 1=aprovado, 2=rejeitado',
  `eco_score` decimal(3,2) DEFAULT NULL COMMENT 'Score ecológico 0.00-10.00',
  `eco_certifications` json DEFAULT NULL COMMENT 'Certificações ambientais',
  `carbon_footprint` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pegada de carbono',
  `materials_used` text COLLATE utf8mb4_unicode_ci COMMENT 'Materiais utilizados',
  `recyclability_index` decimal(3,2) DEFAULT NULL COMMENT 'Índice de reciclabilidade 0-10',
  `eco_benefits` text COLLATE utf8mb4_unicode_ci COMMENT 'Benefícios ecológicos',
  `verification_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Notas da verificação',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT 'Data da verificação',
  `verified_by` enum('ai','admin','both') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Verificado por',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Motivo da rejeição',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Se está ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category` (`category`),
  KEY `idx_eco_category` (`eco_category`),
  KEY `idx_eco_verified` (`eco_verified`),
  KEY `idx_eco_score` (`eco_score`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_products_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.remember_me
CREATE TABLE IF NOT EXISTS `remember_me` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `fk_remember_user` (`user_id`) USING BTREE,
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table vsg.remember_me: ~0 rows (approximately)

-- Dumping structure for table vsg.subscription_plans
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'MZN',
  `billing_cycle` enum('monthly','quarterly','yearly','one_time') DEFAULT 'monthly',
  `features` json DEFAULT NULL,
  `max_users` int DEFAULT '1',
  `max_storage_gb` int DEFAULT '10',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Dumping structure for table vsg.transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` enum('subscription','upgrade','addon','one_time','refund') DEFAULT 'subscription',
  `plan_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'MZN',
  `status` enum('pending','completed','failed','refunded','cancelled') DEFAULT 'pending',
  `payment_method` enum('mpesa','bank_transfer','credit_card','paypal','cash') DEFAULT 'mpesa',
  `transaction_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `due_date` date DEFAULT NULL,
  `paid_date` datetime DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `description` text,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_type` (`type`),
  KEY `idx_plan_id` (`plan_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Dumping structure for table vsg.user_subscriptions
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `plan_id` int NOT NULL,
  `status` enum('active','cancelled','expired','suspended','trial') DEFAULT 'active',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  `auto_renew` tinyint(1) DEFAULT '1',
  `mrr` decimal(10,2) DEFAULT NULL COMMENT 'Monthly Recurring Revenue',
  `trial_ends_at` date DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_next_billing` (`next_billing_date`),
  CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Dumping structure for table vsg.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('person','company','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('user','admin','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user',
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `apelido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email_corporativo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `secure_id_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','active','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `email_token` char(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email_token_expires` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `registration_step` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email_pending',
  `last_activity` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `uid_generated_at` datetime DEFAULT NULL,
  `lock_until` datetime DEFAULT NULL,
  `two_fa_code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_in_lockdown` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE,
  UNIQUE KEY `telefone` (`telefone`) USING BTREE,
  UNIQUE KEY `public_id` (`public_id`) USING BTREE,
  UNIQUE KEY `email_corporativo` (`email_corporativo`) USING BTREE,
  KEY `idx_email_verified` (`email_verified_at`) USING BTREE,
  KEY `idx_registration_step` (`registration_step`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_type_deleted` (`type`,`deleted_at`),
  CONSTRAINT `chk_public_id` CHECK (regexp_like(`public_id`,_utf8mb4'^[0-9]{8}[SAPC]$'))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Dumping structure for view vsg.view_eco_products_approved
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_eco_products_approved` (
	`id` INT NOT NULL,
	`user_id` BIGINT UNSIGNED NULL COMMENT 'Empresa dona do produto',
	`name` VARCHAR(1) NOT NULL COMMENT 'Nome do produto' COLLATE 'utf8mb4_unicode_ci',
	`description` TEXT NULL COMMENT 'Descrição detalhada' COLLATE 'utf8mb4_unicode_ci',
	`image_path` VARCHAR(1) NULL COMMENT 'Caminho da imagem' COLLATE 'utf8mb4_unicode_ci',
	`category` ENUM('addon','service','consultation','training','other') NULL COMMENT 'Tipo de produto/negócio' COLLATE 'utf8mb4_unicode_ci',
	`eco_category` ENUM('recyclable','reusable','biodegradable','sustainable','organic','zero_waste','energy_efficient','not_eco','unknown') NULL COMMENT 'Classificação ecológica' COLLATE 'utf8mb4_unicode_ci',
	`price` DECIMAL(10,2) NOT NULL COMMENT 'Preço do produto',
	`currency` VARCHAR(1) NULL COMMENT 'Moeda' COLLATE 'utf8mb4_unicode_ci',
	`is_recurring` TINYINT(1) NULL COMMENT 'Se é recorrente',
	`billing_cycle` ENUM('monthly','yearly','one_time') NULL COMMENT 'Ciclo de cobrança' COLLATE 'utf8mb4_unicode_ci',
	`stock_quantity` INT NULL COMMENT 'Quantidade em estoque (NULL = ilimitado)',
	`eco_verified` TINYINT(1) NULL COMMENT '0=pendente, 1=aprovado, 2=rejeitado',
	`eco_score` DECIMAL(3,2) NULL COMMENT 'Score ecológico 0.00-10.00',
	`eco_certifications` JSON NULL COMMENT 'Certificações ambientais',
	`carbon_footprint` VARCHAR(1) NULL COMMENT 'Pegada de carbono' COLLATE 'utf8mb4_unicode_ci',
	`materials_used` TEXT NULL COMMENT 'Materiais utilizados' COLLATE 'utf8mb4_unicode_ci',
	`recyclability_index` DECIMAL(3,2) NULL COMMENT 'Índice de reciclabilidade 0-10',
	`eco_benefits` TEXT NULL COMMENT 'Benefícios ecológicos' COLLATE 'utf8mb4_unicode_ci',
	`verification_notes` TEXT NULL COMMENT 'Notas da verificação' COLLATE 'utf8mb4_unicode_ci',
	`verified_at` TIMESTAMP NULL COMMENT 'Data da verificação',
	`verified_by` ENUM('ai','admin','both') NULL COMMENT 'Verificado por' COLLATE 'utf8mb4_unicode_ci',
	`rejection_reason` TEXT NULL COMMENT 'Motivo da rejeição' COLLATE 'utf8mb4_unicode_ci',
	`is_active` TINYINT(1) NULL COMMENT 'Se está ativo',
	`created_at` TIMESTAMP NULL,
	`updated_at` TIMESTAMP NULL,
	`company_name` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`company_email` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`business_type` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci'
);

-- Dumping structure for view vsg.view_eco_products_pending
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_eco_products_pending` (
	`id` INT NOT NULL,
	`user_id` BIGINT UNSIGNED NULL COMMENT 'Empresa dona do produto',
	`name` VARCHAR(1) NOT NULL COMMENT 'Nome do produto' COLLATE 'utf8mb4_unicode_ci',
	`description` TEXT NULL COMMENT 'Descrição detalhada' COLLATE 'utf8mb4_unicode_ci',
	`image_path` VARCHAR(1) NULL COMMENT 'Caminho da imagem' COLLATE 'utf8mb4_unicode_ci',
	`category` ENUM('addon','service','consultation','training','other') NULL COMMENT 'Tipo de produto/negócio' COLLATE 'utf8mb4_unicode_ci',
	`eco_category` ENUM('recyclable','reusable','biodegradable','sustainable','organic','zero_waste','energy_efficient','not_eco','unknown') NULL COMMENT 'Classificação ecológica' COLLATE 'utf8mb4_unicode_ci',
	`price` DECIMAL(10,2) NOT NULL COMMENT 'Preço do produto',
	`currency` VARCHAR(1) NULL COMMENT 'Moeda' COLLATE 'utf8mb4_unicode_ci',
	`is_recurring` TINYINT(1) NULL COMMENT 'Se é recorrente',
	`billing_cycle` ENUM('monthly','yearly','one_time') NULL COMMENT 'Ciclo de cobrança' COLLATE 'utf8mb4_unicode_ci',
	`stock_quantity` INT NULL COMMENT 'Quantidade em estoque (NULL = ilimitado)',
	`eco_verified` TINYINT(1) NULL COMMENT '0=pendente, 1=aprovado, 2=rejeitado',
	`eco_score` DECIMAL(3,2) NULL COMMENT 'Score ecológico 0.00-10.00',
	`eco_certifications` JSON NULL COMMENT 'Certificações ambientais',
	`carbon_footprint` VARCHAR(1) NULL COMMENT 'Pegada de carbono' COLLATE 'utf8mb4_unicode_ci',
	`materials_used` TEXT NULL COMMENT 'Materiais utilizados' COLLATE 'utf8mb4_unicode_ci',
	`recyclability_index` DECIMAL(3,2) NULL COMMENT 'Índice de reciclabilidade 0-10',
	`eco_benefits` TEXT NULL COMMENT 'Benefícios ecológicos' COLLATE 'utf8mb4_unicode_ci',
	`verification_notes` TEXT NULL COMMENT 'Notas da verificação' COLLATE 'utf8mb4_unicode_ci',
	`verified_at` TIMESTAMP NULL COMMENT 'Data da verificação',
	`verified_by` ENUM('ai','admin','both') NULL COMMENT 'Verificado por' COLLATE 'utf8mb4_unicode_ci',
	`rejection_reason` TEXT NULL COMMENT 'Motivo da rejeição' COLLATE 'utf8mb4_unicode_ci',
	`is_active` TINYINT(1) NULL COMMENT 'Se está ativo',
	`created_at` TIMESTAMP NULL,
	`updated_at` TIMESTAMP NULL,
	`company_name` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`company_email` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`days_waiting` INT NULL
);