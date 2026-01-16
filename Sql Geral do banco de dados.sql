CREATE TABLE `users` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`public_id` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`type` ENUM('person','company','admin') NOT NULL COLLATE 'utf8mb4_general_ci',
	`role` ENUM('user','admin','superadmin') NULL DEFAULT 'user' COLLATE 'utf8mb4_general_ci',
	`nome` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`apelido` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`email` VARCHAR(150) NOT NULL COLLATE 'utf8mb4_general_ci',
	`email_corporativo` VARCHAR(150) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`telefone` VARCHAR(20) NOT NULL COLLATE 'utf8mb4_general_ci',
	`password_hash` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_general_ci',
	`secure_id_hash` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`status` ENUM('pending','active','blocked') NULL DEFAULT 'pending' COLLATE 'utf8mb4_general_ci',
	`email_token` CHAR(6) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`email_token_expires` DATETIME NULL DEFAULT NULL,
	`email_verified_at` DATETIME NULL DEFAULT NULL,
	`registration_step` VARCHAR(30) NOT NULL DEFAULT 'email_pending' COLLATE 'utf8mb4_general_ci',
	`last_activity` INT NULL DEFAULT NULL,
	`created_at` DATETIME NULL DEFAULT (CURRENT_TIMESTAMP),
	`updated_at` DATETIME NULL DEFAULT (CURRENT_TIMESTAMP) ON UPDATE CURRENT_TIMESTAMP,
	`deleted_at` DATETIME NULL DEFAULT NULL,
	`password_changed_at` DATETIME NULL DEFAULT (CURRENT_TIMESTAMP),
	`uid_generated_at` DATETIME NULL DEFAULT NULL,
	`lock_until` DATETIME NULL DEFAULT NULL,
	`two_fa_code` VARCHAR(6) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`is_in_lockdown` TINYINT(1) NULL DEFAULT '0',
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `email` (`email`) USING BTREE,
	UNIQUE INDEX `telefone` (`telefone`) USING BTREE,
	UNIQUE INDEX `public_id` (`public_id`) USING BTREE,
	UNIQUE INDEX `email_corporativo` (`email_corporativo`) USING BTREE,
	INDEX `idx_email_verified` (`email_verified_at`) USING BTREE,
	INDEX `idx_registration_step` (`registration_step`) USING BTREE,
	INDEX `idx_status` (`status`) USING BTREE,
	INDEX `idx_deleted_at` (`deleted_at`) USING BTREE,
	INDEX `idx_type_deleted` (`type`, `deleted_at`) USING BTREE,
	CONSTRAINT `chk_public_id` CHECK (regexp_like(`public_id`,_utf8mb4\'^[0-9]{8}[SAPC]$\'))
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=4
;


CREATE TABLE `user_subscriptions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL,
	`plan_id` INT NOT NULL,
	`status` ENUM('active','cancelled','expired','suspended','trial') NULL DEFAULT 'active' COLLATE 'utf8mb4_0900_ai_ci',
	`start_date` DATE NOT NULL,
	`end_date` DATE NULL DEFAULT NULL,
	`next_billing_date` DATE NULL DEFAULT NULL,
	`auto_renew` TINYINT(1) NULL DEFAULT '1',
	`mrr` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Monthly Recurring Revenue',
	`trial_ends_at` DATE NULL DEFAULT NULL,
	`cancelled_at` DATETIME NULL DEFAULT NULL,
	`cancellation_reason` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	`updated_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP) ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_user_id` (`user_id`) USING BTREE,
	INDEX `idx_plan_id` (`plan_id`) USING BTREE,
	INDEX `idx_status` (`status`) USING BTREE,
	INDEX `idx_next_billing` (`next_billing_date`) USING BTREE,
	CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON UPDATE NO ACTION ON DELETE RESTRICT
)
COLLATE='utf8mb4_0900_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=13
;


CREATE TABLE `transactions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL,
	`type` ENUM('subscription','upgrade','addon','one_time','refund') NULL DEFAULT 'subscription' COLLATE 'utf8mb4_0900_ai_ci',
	`plan_id` INT NULL DEFAULT NULL,
	`amount` DECIMAL(10,2) NOT NULL,
	`currency` VARCHAR(3) NULL DEFAULT 'MZN' COLLATE 'utf8mb4_0900_ai_ci',
	`status` ENUM('pending','completed','failed','refunded','cancelled') NULL DEFAULT 'pending' COLLATE 'utf8mb4_0900_ai_ci',
	`payment_method` ENUM('mpesa','bank_transfer','credit_card','paypal','cash') NULL DEFAULT 'mpesa' COLLATE 'utf8mb4_0900_ai_ci',
	`transaction_date` DATETIME NULL DEFAULT (CURRENT_TIMESTAMP),
	`due_date` DATE NULL DEFAULT NULL,
	`paid_date` DATETIME NULL DEFAULT NULL,
	`invoice_number` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`description` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`notes` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	`updated_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP) ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `invoice_number` (`invoice_number`) USING BTREE,
	INDEX `idx_user_id` (`user_id`) USING BTREE,
	INDEX `idx_status` (`status`) USING BTREE,
	INDEX `idx_transaction_date` (`transaction_date`) USING BTREE,
	INDEX `idx_type` (`type`) USING BTREE,
	INDEX `idx_plan_id` (`plan_id`) USING BTREE,
	CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON UPDATE NO ACTION ON DELETE SET NULL
)
COLLATE='utf8mb4_0900_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=9
;


CREATE TABLE `subscription_plans` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(100) NOT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`description` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`price` DECIMAL(10,2) NOT NULL,
	`currency` VARCHAR(3) NULL DEFAULT 'MZN' COLLATE 'utf8mb4_0900_ai_ci',
	`billing_cycle` ENUM('monthly','quarterly','yearly','one_time') NULL DEFAULT 'monthly' COLLATE 'utf8mb4_0900_ai_ci',
	`features` JSON NULL DEFAULT NULL,
	`max_users` INT NULL DEFAULT '1',
	`max_storage_gb` INT NULL DEFAULT '10',
	`is_active` TINYINT(1) NULL DEFAULT '1',
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	`updated_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP) ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`) USING BTREE
)
COLLATE='utf8mb4_0900_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=13
;


CREATE TABLE `remember_me` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` BIGINT UNSIGNED NOT NULL,
	`token_hash` CHAR(64) NOT NULL COLLATE 'utf8mb4_general_ci',
	`expires_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `fk_remember_user` (`user_id`) USING BTREE,
	CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
;

CREATE TABLE `products` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Empresa dona do produto',
	`name` VARCHAR(150) NOT NULL COMMENT 'Nome do produto' COLLATE 'utf8mb4_unicode_ci',
	`description` TEXT NULL DEFAULT NULL COMMENT 'Descrição detalhada' COLLATE 'utf8mb4_unicode_ci',
	`image_path` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Caminho da imagem' COLLATE 'utf8mb4_unicode_ci',
	`category` ENUM('addon','service','consultation','training','other') NULL DEFAULT 'addon' COMMENT 'Tipo de produto/negócio' COLLATE 'utf8mb4_unicode_ci',
	`eco_category` ENUM('recyclable','reusable','biodegradable','sustainable','organic','zero_waste','energy_efficient','not_eco','unknown') NULL DEFAULT 'unknown' COMMENT 'Classificação ecológica' COLLATE 'utf8mb4_unicode_ci',
	`price` DECIMAL(10,2) NOT NULL COMMENT 'Preço do produto',
	`currency` VARCHAR(3) NULL DEFAULT 'MZN' COMMENT 'Moeda' COLLATE 'utf8mb4_unicode_ci',
	`is_recurring` TINYINT(1) NULL DEFAULT '0' COMMENT 'Se é recorrente',
	`billing_cycle` ENUM('monthly','yearly','one_time') NULL DEFAULT 'one_time' COMMENT 'Ciclo de cobrança' COLLATE 'utf8mb4_unicode_ci',
	`stock_quantity` INT NULL DEFAULT NULL COMMENT 'Quantidade em estoque (NULL = ilimitado)',
	`eco_verified` TINYINT(1) NULL DEFAULT '0' COMMENT '0=pendente, 1=aprovado, 2=rejeitado',
	`eco_score` DECIMAL(3,2) NULL DEFAULT NULL COMMENT 'Score ecológico 0.00-10.00',
	`eco_certifications` JSON NULL DEFAULT NULL COMMENT 'Certificações ambientais',
	`carbon_footprint` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Pegada de carbono' COLLATE 'utf8mb4_unicode_ci',
	`materials_used` TEXT NULL DEFAULT NULL COMMENT 'Materiais utilizados' COLLATE 'utf8mb4_unicode_ci',
	`recyclability_index` DECIMAL(3,2) NULL DEFAULT NULL COMMENT 'Índice de reciclabilidade 0-10',
	`eco_benefits` TEXT NULL DEFAULT NULL COMMENT 'Benefícios ecológicos' COLLATE 'utf8mb4_unicode_ci',
	`verification_notes` TEXT NULL DEFAULT NULL COMMENT 'Notas da verificação' COLLATE 'utf8mb4_unicode_ci',
	`verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Data da verificação',
	`verified_by` ENUM('ai','admin','both') NULL DEFAULT NULL COMMENT 'Verificado por' COLLATE 'utf8mb4_unicode_ci',
	`rejection_reason` TEXT NULL DEFAULT NULL COMMENT 'Motivo da rejeição' COLLATE 'utf8mb4_unicode_ci',
	`is_active` TINYINT(1) NULL DEFAULT '1' COMMENT 'Se está ativo',
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	`updated_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP) ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_user_id` (`user_id`) USING BTREE,
	INDEX `idx_category` (`category`) USING BTREE,
	INDEX `idx_eco_category` (`eco_category`) USING BTREE,
	INDEX `idx_eco_verified` (`eco_verified`) USING BTREE,
	INDEX `idx_eco_score` (`eco_score`) USING BTREE,
	INDEX `idx_is_active` (`is_active`) USING BTREE,
	INDEX `idx_created_at` (`created_at`) USING BTREE,
	CONSTRAINT `fk_products_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=3
;



CREATE TABLE `product_purchases` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL,
	`product_id` INT NOT NULL,
	`quantity` INT NULL DEFAULT '1',
	`unit_price` DECIMAL(10,2) NOT NULL,
	`total_amount` DECIMAL(10,2) NOT NULL,
	`status` ENUM('pending','completed','cancelled','refunded') NULL DEFAULT 'pending' COLLATE 'utf8mb4_0900_ai_ci',
	`purchase_date` DATETIME NULL DEFAULT (CURRENT_TIMESTAMP),
	`transaction_id` INT NULL DEFAULT NULL,
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_user_id` (`user_id`) USING BTREE,
	INDEX `idx_product_id` (`product_id`) USING BTREE,
	INDEX `idx_purchase_date` (`purchase_date`) USING BTREE,
	INDEX `transaction_id` (`transaction_id`) USING BTREE,
	CONSTRAINT `product_purchases_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE NO ACTION ON DELETE RESTRICT,
	CONSTRAINT `product_purchases_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON UPDATE NO ACTION ON DELETE SET NULL
)
COLLATE='utf8mb4_0900_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=5
;


CREATE TABLE `notifications` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`sender_id` BIGINT UNSIGNED NULL DEFAULT NULL,
	`receiver_id` BIGINT UNSIGNED NOT NULL,
	`reply_to` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'ID da mensagem sendo respondida',
	`category` ENUM('chat','alert','security','system_error','audit') NULL DEFAULT 'chat' COLLATE 'utf8mb4_0900_ai_ci',
	`priority` ENUM('low','medium','high','critical') NULL DEFAULT 'low' COLLATE 'utf8mb4_0900_ai_ci',
	`subject` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`message` TEXT NOT NULL COLLATE 'utf8mb4_0900_ai_ci',
	`status` ENUM('unread','read','archived') NULL DEFAULT 'unread' COLLATE 'utf8mb4_0900_ai_ci',
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `fk_notif_sender` (`sender_id`) USING BTREE,
	INDEX `idx_notifications_check` (`receiver_id`, `status`, `category`, `created_at` DESC) USING BTREE,
	INDEX `fk_notif_reply` (`reply_to`) USING BTREE,
	CONSTRAINT `fk_notif_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT `fk_notif_reply` FOREIGN KEY (`reply_to`) REFERENCES `notifications` (`id`) ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON UPDATE NO ACTION ON DELETE SET NULL
)
COLLATE='utf8mb4_0900_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=59
;


CREATE TABLE `login_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` BIGINT UNSIGNED NOT NULL,
	`ip_address` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`user_agent` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`login_time` DATETIME NULL DEFAULT (CURRENT_TIMESTAMP),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `fk_logs_user` (`user_id`) USING BTREE,
	CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
;


CREATE TABLE `login_attempts` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`email` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_general_ci',
	`ip` VARCHAR(45) NOT NULL COLLATE 'utf8mb4_general_ci',
	`attempts` INT NULL DEFAULT '1',
	`last_attempt` DATETIME NOT NULL,
	PRIMARY KEY (`id`) USING BTREE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=18
;


CREATE TABLE `form_config` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`config_key` VARCHAR(100) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`config_value` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`config_type` ENUM('boolean','integer','string','json') NULL DEFAULT 'string' COLLATE 'utf8mb4_unicode_ci',
	`description` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`updated_by` INT UNSIGNED NULL DEFAULT NULL,
	`updated_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP) ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `config_key` (`config_key`) USING BTREE,
	INDEX `idx_config_key` (`config_key`) USING BTREE,
	INDEX `idx_updated_by` (`updated_by`) USING BTREE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=341
;


CREATE TABLE `company_health_score` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL,
	`score` INT NULL DEFAULT '50' COMMENT 'Score de 0 a 100',
	`payment_health` DECIMAL(3,2) NULL DEFAULT '0.00' COMMENT '0 a 1',
	`usage_health` DECIMAL(3,2) NULL DEFAULT '0.00' COMMENT '0 a 1',
	`engagement_health` DECIMAL(3,2) NULL DEFAULT '0.00' COMMENT '0 a 1',
	`support_health` DECIMAL(3,2) NULL DEFAULT '0.00' COMMENT '0 a 1',
	`risk_level` ENUM('low','medium','high','critical') NULL DEFAULT 'medium' COLLATE 'utf8mb4_0900_ai_ci',
	`churn_probability` DECIMAL(3,2) NULL DEFAULT '0.00' COMMENT 'Probabilidade de cancelamento',
	`recommendations` JSON NULL DEFAULT NULL COMMENT 'Recomendações para melhorar',
	`last_calculated` DATETIME NULL DEFAULT (CURRENT_TIMESTAMP),
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `unique_user` (`user_id`) USING BTREE,
	INDEX `idx_score` (`score`) USING BTREE,
	INDEX `idx_risk_level` (`risk_level`) USING BTREE
)
COLLATE='utf8mb4_0900_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=7
;


CREATE TABLE `company_growth_metrics` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL,
	`metric_date` DATE NOT NULL,
	`revenue` DECIMAL(10,2) NULL DEFAULT '0.00' COMMENT 'Receita do dia',
	`active_users` INT NULL DEFAULT '0' COMMENT 'Usuários ativos',
	`new_signups` INT NULL DEFAULT '0' COMMENT 'Novos cadastros',
	`churn_count` INT NULL DEFAULT '0' COMMENT 'Cancelamentos',
	`storage_used_gb` DECIMAL(8,2) NULL DEFAULT '0.00',
	`api_calls` INT NULL DEFAULT '0',
	`support_tickets` INT NULL DEFAULT '0',
	`satisfaction_score` DECIMAL(3,2) NULL DEFAULT '0.00' COMMENT 'Nota de 0 a 5',
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `unique_user_date` (`user_id`, `metric_date`) USING BTREE,
	INDEX `idx_user_id` (`user_id`) USING BTREE,
	INDEX `idx_metric_date` (`metric_date`) USING BTREE
)
COLLATE='utf8mb4_0900_ai_ci'
ENGINE=InnoDB
AUTO_INCREMENT=41
;


CREATE TABLE `businesses` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` BIGINT UNSIGNED NOT NULL,
	`tax_id` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`tax_id_file` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`business_type` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`description` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`country` CHAR(2) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`region` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`city` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`license_path` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`logo_path` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`status_documentos` ENUM('pendente','aprovado','rejeitado') NULL DEFAULT 'pendente' COLLATE 'utf8mb4_general_ci',
	`motivo_rejeicao` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`updated_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP) ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `user_id_unique` (`user_id`) USING BTREE,
	UNIQUE INDEX `tax_id_unique` (`tax_id`) USING BTREE,
	CONSTRAINT `fk_business_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=2
;


CREATE TABLE `admin_audit_logs` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
	`action` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`ip_address` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`user_agent` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`details` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`created_at` TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `admin_id` (`admin_id`) USING BTREE,
	INDEX `idx_admin_id_created` (`admin_id`, `created_at` DESC) USING BTREE,
	INDEX `idx_action` (`action`) USING BTREE,
	CONSTRAINT `admin_audit_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=129
;
