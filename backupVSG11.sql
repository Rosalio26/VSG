
-- Dumping structure for table vsg.audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `user_type` enum('person','company','admin','employee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'company',
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.audit_logs: ~0 rows (approximately)

-- Dumping structure for view vsg.audit_logs_stats
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `audit_logs_stats` (
	`user_id` BIGINT UNSIGNED NOT NULL,
	`user_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`log_date` DATE NULL,
	`action` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`action_count` BIGINT NOT NULL
);

-- Dumping structure for table vsg.businesses
CREATE TABLE IF NOT EXISTS `businesses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tax_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tax_id_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `business_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `country` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nome do país',
  `region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `license_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_documentos` enum('pendente','aprovado','rejeitado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pendente',
  `motivo_rejeicao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_unique` (`user_id`),
  UNIQUE KEY `tax_id_unique` (`tax_id`),
  KEY `idx_businesses_country` (`country`),
  KEY `idx_businesses_type` (`business_type`),
  CONSTRAINT `fk_business_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping structure for table vsg.cart_items
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int unsigned NOT NULL,
  `product_id` int NOT NULL,
  `company_id` bigint unsigned NOT NULL COMMENT 'Empresa dona do produto',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `price` decimal(12,2) NOT NULL COMMENT 'Preço no momento da adição',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'MZN',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_product` (`cart_id`,`product_id`),
  KEY `idx_cart_id` (`cart_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_cart_product` (`cart_id`,`product_id`),
  CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `shopping_carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_items_company` FOREIGN KEY (`company_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.cart_items: ~0 rows (approximately)

-- Dumping structure for table vsg.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'box',
  `parent_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('ativa','pendente','rejeitada') COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  KEY `fk_category_creator` (`created_by_user_id`),
  KEY `idx_categories_status_parent` (`status`,`parent_id`),
  KEY `idx_categories_slug` (`slug`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_category_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.company_growth_metrics
CREATE TABLE IF NOT EXISTS `company_growth_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `metric_date` date NOT NULL,
  `revenue` decimal(10,2) DEFAULT '0.00',
  `active_users` int DEFAULT '0',
  `new_signups` int DEFAULT '0',
  `churn_count` int DEFAULT '0',
  `storage_used_gb` decimal(8,2) DEFAULT '0.00',
  `api_calls` int DEFAULT '0',
  `support_tickets` int DEFAULT '0',
  `satisfaction_score` decimal(3,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_date` (`user_id`,`metric_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_metric_date` (`metric_date`),
  CONSTRAINT `fk_growth_metrics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table vsg.company_growth_metrics: ~0 rows (approximately)

-- Dumping structure for table vsg.company_health_score
CREATE TABLE IF NOT EXISTS `company_health_score` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `score` int DEFAULT '50',
  `payment_health` decimal(3,2) DEFAULT '0.00',
  `usage_health` decimal(3,2) DEFAULT '0.00',
  `engagement_health` decimal(3,2) DEFAULT '0.00',
  `support_health` decimal(3,2) DEFAULT '0.00',
  `risk_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `churn_probability` decimal(3,2) DEFAULT '0.00',
  `recommendations` json DEFAULT NULL,
  `last_calculated` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  KEY `idx_score` (`score`),
  KEY `idx_risk_level` (`risk_level`),
  CONSTRAINT `fk_health_score_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table vsg.company_health_score: ~0 rows (approximately)

-- Dumping structure for table vsg.customer_reviews
CREATE TABLE IF NOT EXISTS `customer_reviews` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `product_id` int NOT NULL,
  `rating` tinyint NOT NULL COMMENT '1-5 estrelas',
  `review_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_verified_purchase` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_reviews_product_rating` (`product_id`,`rating`),
  KEY `idx_reviews_verified` (`is_verified_purchase`,`rating`),
  CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.customer_reviews: ~0 rows (approximately)

-- Dumping structure for table vsg.employee_access_logs
CREATE TABLE IF NOT EXISTS `employee_access_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_access_logs_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.employee_access_logs: ~0 rows (approximately)

-- Dumping structure for table vsg.employee_permissions
CREATE TABLE IF NOT EXISTS `employee_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `module` enum('dashboard','mensagens','produtos','vendas','compras','clientes','relatorios','funcionarios','assinatura','configuracoes') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT '1',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0',
  `can_create` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_module` (`employee_id`,`module`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_module` (`module`),
  CONSTRAINT `fk_permissions_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.employee_permissions: ~0 rows (approximately)

-- Dumping structure for view vsg.employee_permissions_summary
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `employee_permissions_summary` (
	`employee_id` INT NOT NULL,
	`employee_name` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`employee_position` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`company_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID da empresa dona',
	`company_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`accessible_modules` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`total_accessible_modules` BIGINT NOT NULL,
	`status` ENUM('ativo','inativo','ferias','afastado') NOT NULL COLLATE 'utf8mb4_unicode_ci'
);

-- Dumping structure for table vsg.employees
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT 'ID da empresa dona',
  `user_employee_id` bigint unsigned DEFAULT NULL COMMENT 'FK para users.id (funcionário)',
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_company` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Email corporativo único',
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cargo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `departamento` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_admissao` date NOT NULL,
  `salario` decimal(10,2) DEFAULT NULL,
  `status` enum('ativo','inativo','ferias','afastado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
  `foto_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_documento` enum('bi','passaporte','dire','nuit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'bi',
  `endereco` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'DEPRECATED',
  `pode_acessar_sistema` tinyint(1) NOT NULL DEFAULT '0',
  `pode_confirmar_pagamentos` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Autorizado a confirmar pagamentos manuais',
  `primeiro_acesso` tinyint(1) NOT NULL DEFAULT '1',
  `ultimo_login` datetime DEFAULT NULL,
  `token_primeiro_acesso` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_expira_em` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email_company` (`email_company`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_cargo` (`cargo`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_pode_confirmar_pagamentos` (`pode_confirmar_pagamentos`),
  KEY `idx_user_employee_id` (`user_employee_id`),
  CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employees_user_employee` FOREIGN KEY (`user_employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.employees: ~0 rows (approximately)

-- Dumping structure for table vsg.exchange_rates
CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `last_updated` datetime NOT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'api',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pair` (`from_currency`,`to_currency`),
  KEY `idx_currencies` (`from_currency`,`to_currency`),
  KEY `idx_updated` (`last_updated`)
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping structure for table vsg.favorites
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_favorite` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.favorites: ~0 rows (approximately)

-- Dumping structure for table vsg.form_config
CREATE TABLE IF NOT EXISTS `form_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `config_type` enum('boolean','integer','string','json') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_config_key` (`config_key`),
  KEY `idx_updated_by` (`updated_by`),
  CONSTRAINT `fk_form_config_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.form_config: ~0 rows (approximately)

-- Dumping structure for table vsg.login_attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int DEFAULT '1',
  `last_attempt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_last_attempt` (`last_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.login_attempts: ~0 rows (approximately)

-- Dumping structure for table vsg.login_logs
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `browser` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operating_system` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_login_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.login_logs: ~0 rows (approximately)

-- Dumping structure for table vsg.notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned DEFAULT NULL,
  `receiver_id` bigint unsigned NOT NULL,
  `reply_to` bigint unsigned DEFAULT NULL,
  `category` enum('compra_pendente','compra_confirmada','pagamento_manual','pagamento','estoque_baixo','novo_pedido','sistema','alerta','importante') COLLATE utf8mb4_unicode_ci DEFAULT 'sistema',
  `priority` enum('baixa','media','alta','critica') COLLATE utf8mb4_unicode_ci DEFAULT 'media',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_order_id` int unsigned DEFAULT NULL,
  `related_product_id` int DEFAULT NULL,
  `attachment_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('nao_lida','lida','arquivada') COLLATE utf8mb4_unicode_ci DEFAULT 'nao_lida',
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_receiver` (`receiver_id`,`status`,`category`,`created_at` DESC),
  KEY `idx_reply_to` (`reply_to`),
  KEY `idx_related_order` (`related_order_id`),
  KEY `idx_related_product` (`related_product_id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_notifications_receiver_status_priority` (`receiver_id`,`status`,`priority`,`created_at` DESC),
  CONSTRAINT `fk_notif_order` FOREIGN KEY (`related_order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_product` FOREIGN KEY (`related_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_reply` FOREIGN KEY (`reply_to`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping structure for table vsg.order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int NOT NULL,
  `product_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) NOT NULL,
  `discount` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_order_items_product_quantity` (`product_id`,`quantity`),
  KEY `idx_order_items_created` (`created_at` DESC),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping structure for table vsg.order_status_history
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `status_from` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_changed_by` (`changed_by`),
  CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping structure for table vsg.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `order_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivery_date` date DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(12,2) DEFAULT '0.00',
  `tax` decimal(12,2) DEFAULT '0.00',
  `shipping_cost` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'MZN',
  `status` enum('pendente','confirmado','processando','enviado','entregue','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `payment_status` enum('pendente','pago','parcial','reembolsado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `payment_method` enum('mpesa','emola','visa','mastercard','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  `confirmed_by_employee` int DEFAULT NULL COMMENT 'Funcionário que confirmou pagamento manual',
  `confirmed_at` datetime DEFAULT NULL,
  `shipping_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `shipping_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `internal_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_company` (`company_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_confirmed_by` (`confirmed_by_employee`),
  KEY `idx_company_date` (`company_id`,`order_date`),
  KEY `idx_orders_company_date_status` (`company_id`,`order_date`,`status`),
  KEY `idx_orders_payment_method_status` (`payment_method`,`payment_status`),
  CONSTRAINT `fk_orders_company` FOREIGN KEY (`company_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_employee` FOREIGN KEY (`confirmed_by_employee`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping structure for table vsg.payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `transaction_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'MZN',
  `payment_method` enum('mpesa','emola','visa','mastercard','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` enum('pendente','processando','confirmado','falhado','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `payment_date` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `confirmed_by_employee` int DEFAULT NULL,
  `receipt_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_proof` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_confirmed_by` (`confirmed_by_employee`),
  CONSTRAINT `fk_payments_employee` FOREIGN KEY (`confirmed_by_employee`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping structure for table vsg.product_versions
CREATE TABLE IF NOT EXISTS `product_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `preco` decimal(10,2) NOT NULL,
  `preco_original` decimal(10,2) DEFAULT NULL,
  `stock` int NOT NULL,
  `stock_minimo` int DEFAULT '5',
  `status` enum('ativo','inativo','esgotado') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `changed_by` bigint unsigned DEFAULT NULL COMMENT 'Usuário que fez a alteração',
  `change_type` enum('criacao','preco','estoque','status','edicao') COLLATE utf8mb4_unicode_ci DEFAULT 'edicao',
  `change_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_changed_by` (`changed_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_change_type` (`change_type`),
  CONSTRAINT `fk_product_versions_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_versions_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de alterações em produtos';

-- Dumping structure for table vsg.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT 'Empresa dona do produto',
  `category_id` int DEFAULT NULL,
  `eco_badges` json DEFAULT NULL COMMENT 'Lista de atributos: ["reciclavel", "zero_waste"]',
  `nome` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imagem` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'MZN',
  `stock` int NOT NULL DEFAULT '0' COMMENT 'Quantidade em estoque',
  `stock_minimo` int DEFAULT '5' COMMENT 'Alerta de estoque baixo',
  `status` enum('ativo','inativo','esgotado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `visualizacoes` int DEFAULT '0' COMMENT 'Contador de visualizações',
  `total_sales` int DEFAULT '0' COMMENT 'Total de vendas (cache para performance)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Último usuário que editou',
  `preco_original` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_stock` (`stock`),
  KEY `fk_products_category` (`category_id`),
  KEY `fk_products_updated_by` (`updated_by`),
  KEY `idx_products_status_stock_deleted` (`status`,`deleted_at`,`stock`),
  KEY `idx_products_created_recent` (`created_at` DESC,`status`,`deleted_at`),
  KEY `idx_products_sales_created` (`total_sales` DESC,`created_at` DESC),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_company` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_products_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping structure for table vsg.quotations
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `company_id` bigint unsigned NOT NULL,
  `quotation_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pendente','respondida','aceita','recusada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `message` text COLLATE utf8mb4_unicode_ci,
  `response` text COLLATE utf8mb4_unicode_ci,
  `total_estimated` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `customer_id` (`customer_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotations_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.quotations: ~0 rows (approximately)

-- Dumping structure for table vsg.remember_me
CREATE TABLE IF NOT EXISTS `remember_me` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_remember_user` (`user_id`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table vsg.remember_me: ~0 rows (approximately)

-- Dumping structure for table vsg.sales_records
CREATE TABLE IF NOT EXISTS `sales_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `order_item_id` int unsigned NOT NULL,
  `company_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `product_id` int NOT NULL,
  `sale_date` datetime NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `discount` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'MZN',
  `order_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_sale_date` (`sale_date`),
  KEY `idx_company_date` (`company_id`,`sale_date`),
  KEY `idx_order_item_id` (`order_item_id`),
  CONSTRAINT `fk_sales_company` FOREIGN KEY (`company_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dumping structure for table vsg.shipping_tracking
CREATE TABLE IF NOT EXISTS `shipping_tracking` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `carrier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `status` enum('pendente','coletado','em_transito','saiu_entrega','entregue','falhou') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_tracking_code` (`tracking_code`),
  CONSTRAINT `fk_shipping_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.shipping_tracking: ~0 rows (approximately)

-- Dumping structure for table vsg.shopping_carts
CREATE TABLE IF NOT EXISTS `shopping_carts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` enum('active','completed','abandoned') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Para carrinhos de visitantes não logados',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_updated_at` (`updated_at`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.shopping_carts: ~0 rows (approximately)

-- Dumping structure for procedure vsg.sp_add_stock
DELIMITER //
CREATE PROCEDURE `sp_add_stock`(
    IN p_product_id INT,
    IN p_quantity INT,
    IN p_employee_id INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_stock_before INT; DECLARE v_stock_after INT; SELECT stock INTO v_stock_before FROM products WHERE id = p_product_id; UPDATE products 
    SET stock = stock + p_quantity,
        status = CASE 
            WHEN status = 'esgotado' AND (stock + p_quantity) > 0 THEN 'ativo'
            ELSE status
        END
    WHERE id = p_product_id; SELECT stock INTO v_stock_after FROM products WHERE id = p_product_id; INSERT INTO stock_movements (product_id, type, quantity, stock_before, stock_after, employee_id, notes)
    VALUES (p_product_id, 'entrada', p_quantity, v_stock_before, v_stock_after, p_employee_id, p_notes); SELECT CONCAT('Estoque adicionado com sucesso! Stock anterior: ', v_stock_before, ', Stock atual: ', v_stock_after) AS message; END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_cancel_order
DELIMITER //
CREATE PROCEDURE `sp_cancel_order`(
    IN p_order_id INT,
    IN p_user_id BIGINT,
    IN p_reason TEXT
)
BEGIN
    DECLARE done INT DEFAULT FALSE; DECLARE v_product_id INT; DECLARE v_quantity INT; DECLARE v_stock_before INT; DECLARE v_stock_after INT; DECLARE cur CURSOR FOR 
        SELECT product_id, quantity FROM order_items WHERE order_id = p_order_id; DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE; -- Atualizar status do pedido
    UPDATE orders 
    SET status = 'cancelado', 
        internal_notes = CONCAT(COALESCE(internal_notes, ''), '\n[CANCELADO] ', p_reason)
    WHERE id = p_order_id; -- Devolver estoque
    OPEN cur; read_loop: LOOP
        FETCH cur INTO v_product_id, v_quantity; IF done THEN
            LEAVE read_loop; END IF; SELECT stock INTO v_stock_before FROM products WHERE id = v_product_id; UPDATE products 
        SET stock = stock + v_quantity,
            status = CASE 
                WHEN status = 'esgotado' THEN 'ativo'
                ELSE status
            END
        WHERE id = v_product_id; SELECT stock INTO v_stock_after FROM products WHERE id = v_product_id; INSERT INTO stock_movements (product_id, order_id, type, quantity, stock_before, stock_after, user_id, notes)
        VALUES (v_product_id, p_order_id, 'devolucao', v_quantity, v_stock_before, v_stock_after, p_user_id, 
                CONCAT('Devolução por cancelamento de pedido. Motivo: ', p_reason)); END LOOP; CLOSE cur; -- Registrar histórico
    INSERT INTO order_status_history (order_id, changed_by, status_from, status_to, notes)
    SELECT p_order_id, p_user_id, status, 'cancelado', p_reason
    FROM orders WHERE id = p_order_id; SELECT 'Pedido cancelado e estoque devolvido com sucesso!' AS message; END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_cart_to_order
DELIMITER //
CREATE PROCEDURE `sp_cart_to_order`(
    IN p_cart_id INT,
    IN p_payment_method VARCHAR(20),
    IN p_shipping_address TEXT,
    IN p_shipping_city VARCHAR(100),
    IN p_shipping_phone VARCHAR(20),
    IN p_customer_notes TEXT,
    OUT p_order_id INT,
    OUT p_order_number VARCHAR(50)
)
BEGIN
    DECLARE v_user_id BIGINT UNSIGNED;
    DECLARE v_subtotal DECIMAL(12,2) DEFAULT 0;
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;
    DECLARE v_company_id BIGINT UNSIGNED;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity INT;
    DECLARE v_price DECIMAL(12,2);
    DECLARE v_product_name VARCHAR(150);
    DECLARE v_product_image VARCHAR(255);
    DECLARE v_category VARCHAR(50);
    
    DECLARE cur CURSOR FOR 
        SELECT ci.product_id, ci.quantity, ci.price, ci.company_id,
               p.nome, p.imagem, c.name
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE ci.cart_id = p_cart_id;
        
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Obter user_id do carrinho
    SELECT user_id, 
           (SELECT company_id FROM cart_items WHERE cart_id = p_cart_id LIMIT 1)
    INTO v_user_id, v_company_id
    FROM shopping_carts
    WHERE id = p_cart_id;
    
    -- Calcular subtotal
    SELECT SUM(price * quantity) INTO v_subtotal
    FROM cart_items
    WHERE cart_id = p_cart_id;
    
    SET v_total = v_subtotal;
    
    -- Gerar número do pedido
    SET p_order_number = CONCAT('ORD-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 10000), 4, '0'));
    
    -- Criar pedido
    INSERT INTO orders (
        company_id, customer_id, order_number, order_date,
        subtotal, total, currency, status, payment_status, payment_method,
        shipping_address, shipping_city, shipping_phone, customer_notes
    ) VALUES (
        v_company_id, v_user_id, p_order_number, NOW(),
        v_subtotal, v_total, 'MZN', 'pendente', 'pendente', p_payment_method,
        p_shipping_address, p_shipping_city, p_shipping_phone, p_customer_notes
    );
    
    SET p_order_id = LAST_INSERT_ID();
    
    -- Processar itens do carrinho
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_product_id, v_quantity, v_price, v_company_id,
                       v_product_name, v_product_image, v_category;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Inserir item no pedido
        INSERT INTO order_items (
            order_id, product_id, product_name, product_image, 
            product_category, quantity, unit_price, total
        ) VALUES (
            p_order_id, v_product_id, v_product_name, v_product_image,
            v_category, v_quantity, v_price, (v_price * v_quantity)
        );
    END LOOP;
    
    CLOSE cur;
    
    -- Marcar carrinho como completo
    UPDATE shopping_carts
    SET status = 'completed', completed_at = NOW()
    WHERE id = p_cart_id;
    
    SELECT p_order_id AS order_id, p_order_number AS order_number, v_total AS total;
END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_cleanup_abandoned_carts
DELIMITER //
CREATE PROCEDURE `sp_cleanup_abandoned_carts`()
BEGIN
    -- Marcar carrinhos não atualizados há mais de 30 dias como abandonados
    UPDATE shopping_carts
    SET status = 'abandoned'
    WHERE status = 'active'
    AND DATEDIFF(NOW(), updated_at) > 30;
    
    -- Opcional: Deletar carrinhos abandonados há mais de 90 dias
    DELETE FROM shopping_carts
    WHERE status = 'abandoned'
    AND DATEDIFF(NOW(), updated_at) > 90;
    
    SELECT ROW_COUNT() AS carts_cleaned;
END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_confirm_manual_payment
DELIMITER //
CREATE PROCEDURE `sp_confirm_manual_payment`(
    IN p_order_id INT,
    IN p_employee_id INT,
    IN p_amount DECIMAL(12,2),
    IN p_receipt_number VARCHAR(100),
    IN p_notes TEXT
)
BEGIN
    DECLARE v_payment_id INT; DECLARE v_order_total DECIMAL(12,2); DECLARE v_has_permission INT; DECLARE v_employee_user_id BIGINT UNSIGNED; DECLARE v_order_number VARCHAR(50); DECLARE v_company_id BIGINT UNSIGNED; DECLARE v_old_status VARCHAR(20); -- Verificar se funcionário tem permissão
    SELECT COUNT(*), user_employee_id INTO v_has_permission, v_employee_user_id
    FROM employees 
    WHERE id = p_employee_id 
      AND pode_confirmar_pagamentos = 1 
      AND is_active = 1; IF v_has_permission = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Funcionário não autorizado a confirmar pagamentos'; END IF; -- Obter informações do pedido
    SELECT total, order_number, company_id, status 
    INTO v_order_total, v_order_number, v_company_id, v_old_status
    FROM orders WHERE id = p_order_id; -- Verificar se valor está correto
    IF p_amount != v_order_total THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Valor do pagamento não corresponde ao total do pedido'; END IF; -- Criar ou atualizar pagamento
    INSERT INTO payments (
        order_id, amount, currency, payment_method, payment_status,
        payment_date, confirmed_at, confirmed_by_employee,
        receipt_number, notes
    ) VALUES (
        p_order_id, p_amount, 'MZN', 'manual', 'confirmado',
        NOW(), NOW(), p_employee_id, p_receipt_number, p_notes
    )
    ON DUPLICATE KEY UPDATE
        payment_status = 'confirmado',
        confirmed_at = NOW(),
        confirmed_by_employee = p_employee_id,
        receipt_number = p_receipt_number,
        notes = p_notes; -- Atualizar status do pedido
    UPDATE orders 
    SET payment_status = 'pago',
        payment_date = NOW(),
        status = CASE WHEN status = 'pendente' THEN 'confirmado' ELSE status END,
        confirmed_at = NOW(),
        confirmed_by_employee = p_employee_id
    WHERE id = p_order_id; -- Criar notificação de confirmação
    INSERT INTO notifications (receiver_id, category, priority, subject, message, related_order_id)
    VALUES (
        v_company_id,
        'compra_confirmada',
        'alta',
        CONCAT('Pagamento Confirmado - Pedido #', v_order_number),
        CONCAT(
            'O pagamento do pedido #', v_order_number, ' foi confirmado! ',
            'Valor: ', p_amount, ' MZN. ',
            'Confirmado por funcionário ID: ', p_employee_id, '. ',
            'Recibo: ', p_receipt_number
        ),
        p_order_id
    ); -- Registrar histórico de status
    INSERT INTO order_status_history (order_id, changed_by, status_from, status_to, notes)
    VALUES (
        p_order_id,
        v_employee_user_id,
        v_old_status,
        'confirmado',
        CONCAT('Pagamento manual confirmado. Recibo: ', p_receipt_number)
    ); -- Registrar log de auditoria
    IF v_employee_user_id IS NOT NULL THEN
        INSERT INTO audit_logs (user_id, user_type, action, entity_type, entity_id, details)
        VALUES (
            v_employee_user_id,
            'employee',
            'confirm_payment',
            'order',
            p_order_id,
            CONCAT('Pagamento manual confirmado. Valor: ', p_amount, ' MZN. Recibo: ', p_receipt_number)
        ); END IF; SELECT CONCAT('Pagamento confirmado com sucesso! Pedido #', v_order_number, ' atualizado.') AS message; END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_get_dashboard_stats
DELIMITER //
CREATE PROCEDURE `sp_get_dashboard_stats`(
    IN p_company_id BIGINT,
    IN p_period VARCHAR(20)
)
BEGIN
    DECLARE v_start_date DATE;
    
    CASE p_period
        WHEN 'today' THEN SET v_start_date = CURDATE();
        WHEN 'week' THEN SET v_start_date = DATE_SUB(CURDATE(), INTERVAL 7 DAY);
        WHEN 'month' THEN SET v_start_date = DATE_SUB(CURDATE(), INTERVAL 30 DAY);
        WHEN 'year' THEN SET v_start_date = DATE_SUB(CURDATE(), INTERVAL 1 YEAR);
        ELSE SET v_start_date = DATE_SUB(CURDATE(), INTERVAL 30 DAY);
    END CASE;
    
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COUNT(DISTINCT o.customer_id) as total_customers,
        SUM(o.total) as total_revenue,
        AVG(o.total) as avg_order_value,
        SUM(oi.quantity) as total_items_sold,
        COUNT(CASE WHEN o.status = 'entregue' THEN 1 END) as delivered_orders,
        COUNT(CASE WHEN o.status = 'pendente' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN o.payment_method = 'manual' AND o.payment_status = 'pendente' THEN 1 END) as pending_manual_payments
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.company_id = p_company_id
    AND DATE(o.order_date) >= v_start_date
    AND o.deleted_at IS NULL;
END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_get_low_stock_products
DELIMITER //
CREATE PROCEDURE `sp_get_low_stock_products`(
    IN p_company_id BIGINT
)
BEGIN
    SELECT 
        p.id,
        p.nome,
        p.categoria,
        p.stock AS stock_atual,
        p.stock_minimo,
        (p.stock_minimo - p.stock) AS quantidade_necessaria,
        p.preco,
        CASE 
            WHEN p.stock = 0 THEN 'ESGOTADO'
            WHEN p.stock <= (p.stock_minimo / 2) THEN 'CRÍTICO'
            ELSE 'BAIXO'
        END AS nivel_alerta
    FROM products p
    WHERE p.user_id = p_company_id
      AND p.stock <= p.stock_minimo
      AND p.deleted_at IS NULL
      AND p.status != 'inativo'
    ORDER BY p.stock ASC, p.nome; END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_get_products_filtered
DELIMITER //
CREATE PROCEDURE `sp_get_products_filtered`(
    IN p_filter VARCHAR(20),
    IN p_exclude_ids TEXT,
    IN p_limit INT
)
BEGIN
    SET @sql = 'SELECT * FROM view_products_enhanced WHERE status = "ativo" AND stock > 0';
    
    IF p_exclude_ids IS NOT NULL AND p_exclude_ids != '' THEN
        SET @sql = CONCAT(@sql, ' AND id NOT IN (', p_exclude_ids, ')');
    END IF;
    
    IF p_filter = 'bestsellers' THEN
        SET @sql = CONCAT(@sql, ' AND total_sales > 0 ORDER BY total_sales DESC, created_at DESC');
    ELSEIF p_filter = 'new' THEN
        SET @sql = CONCAT(@sql, ' AND days_old <= 7 ORDER BY created_at DESC');
    ELSEIF p_filter = 'recent' THEN
        SET @sql = CONCAT(@sql, ' ORDER BY created_at DESC');
    END IF;
    
    SET @sql = CONCAT(@sql, ' LIMIT ', p_limit);
    
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_get_sales_stats
DELIMITER //
CREATE PROCEDURE `sp_get_sales_stats`(
    IN p_company_id BIGINT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT 
        COUNT(DISTINCT o.id) AS total_orders,
        COUNT(DISTINCT o.customer_id) AS total_customers,
        SUM(o.total) AS total_revenue,
        AVG(o.total) AS avg_order_value,
        SUM(oi.quantity) AS total_items_sold,
        COUNT(CASE WHEN o.status = 'entregue' THEN 1 END) AS delivered_orders,
        COUNT(CASE WHEN o.status = 'pendente' THEN 1 END) AS pending_orders,
        COUNT(CASE WHEN o.status = 'cancelado' THEN 1 END) AS cancelled_orders,
        COUNT(CASE WHEN o.payment_method = 'manual' THEN 1 END) AS manual_payments,
        COUNT(CASE WHEN o.payment_status = 'pago' THEN 1 END) AS paid_orders
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.company_id = p_company_id
      AND DATE(o.order_date) BETWEEN p_start_date AND p_end_date
      AND o.deleted_at IS NULL; END//
DELIMITER ;

-- Dumping structure for procedure vsg.sp_process_new_order
DELIMITER //
CREATE PROCEDURE `sp_process_new_order`(
    IN p_company_id BIGINT,
    IN p_customer_id BIGINT,
    IN p_payment_method VARCHAR(20),
    IN p_shipping_address TEXT,
    IN p_shipping_city VARCHAR(100),
    IN p_shipping_phone VARCHAR(20),
    IN p_customer_notes TEXT,
    IN p_product_ids TEXT,
    IN p_quantities TEXT,
    OUT p_order_id INT,
    OUT p_order_number VARCHAR(50)
)
BEGIN
    DECLARE v_subtotal DECIMAL(12,2) DEFAULT 0; DECLARE v_total DECIMAL(12,2) DEFAULT 0; DECLARE v_item_index INT DEFAULT 1; DECLARE v_items_count INT; DECLARE v_product_id INT; DECLARE v_quantity INT; DECLARE v_unit_price DECIMAL(12,2); DECLARE v_product_name VARCHAR(150); DECLARE v_product_category VARCHAR(50); DECLARE v_item_total DECIMAL(12,2); DECLARE v_product_image VARCHAR(255); -- Gerar número do pedido
    SET p_order_number = CONCAT('ORD-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 10000), 4, '0')); -- Contar itens (assumindo separados por vírgula)
    SET v_items_count = (LENGTH(p_product_ids) - LENGTH(REPLACE(p_product_ids, ',', '')) + 1); -- Criar pedido
    INSERT INTO orders (
        company_id, customer_id, order_number, order_date,
        subtotal, total, currency, status, payment_status, payment_method,
        shipping_address, shipping_city, shipping_phone, customer_notes
    ) VALUES (
        p_company_id, p_customer_id, p_order_number, NOW(),
        0, 0, 'MZN', 'pendente', 'pendente', p_payment_method,
        p_shipping_address, p_shipping_city, p_shipping_phone, p_customer_notes
    ); SET p_order_id = LAST_INSERT_ID(); -- Processar cada item
    WHILE v_item_index <= v_items_count DO
        SET v_product_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p_product_ids, ',', v_item_index), ',', -1) AS UNSIGNED); SET v_quantity = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(p_quantities, ',', v_item_index), ',', -1) AS UNSIGNED); SELECT preco, nome, categoria, imagem
        INTO v_unit_price, v_product_name, v_product_category, v_product_image
        FROM products WHERE id = v_product_id; SET v_item_total = v_unit_price * v_quantity; SET v_subtotal = v_subtotal + v_item_total; INSERT INTO order_items (order_id, product_id, product_name, product_image, product_category, quantity, unit_price, total)
        VALUES (p_order_id, v_product_id, v_product_name, v_product_image, v_product_category, v_quantity, v_unit_price, v_item_total); SET v_item_index = v_item_index + 1; END WHILE; SET v_total = v_subtotal; -- Atualizar totais do pedido
    UPDATE orders 
    SET subtotal = v_subtotal, total = v_total
    WHERE id = p_order_id; -- Criar pagamento inicial (pendente para métodos eletrônicos)
    IF p_payment_method != 'manual' THEN
        INSERT INTO payments (order_id, amount, currency, payment_method, payment_status)
        VALUES (p_order_id, v_total, 'MZN', p_payment_method, 'pendente'); END IF; SELECT p_order_id AS order_id, p_order_number AS order_number, v_total AS total; END//
DELIMITER ;

-- Dumping structure for table vsg.stock_movements
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `order_id` int unsigned DEFAULT NULL,
  `type` enum('venda','entrada','ajuste','devolucao') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `stock_before` int NOT NULL,
  `stock_after` int NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`),
  KEY `fk_stock_user` (`user_id`),
  KEY `fk_stock_employee` (`employee_id`),
  CONSTRAINT `fk_stock_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table vsg.subscription_plans: ~0 rows (approximately)

-- Dumping structure for table vsg.transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table vsg.transactions: ~0 rows (approximately)

-- Dumping structure for table vsg.user_locations
CREATE TABLE IF NOT EXISTS `user_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'País do usuário',
  `country_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código ISO (MZ, BR, PT)',
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado/Província',
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cidade',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Endereço completo',
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CEP/Código Postal',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Latitude GPS',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Longitude GPS',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP usado para geolocalização',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'User agent do navegador',
  `is_primary` tinyint(1) DEFAULT '1' COMMENT 'Localização principal',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_primary_location` (`user_id`,`is_primary`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_country` (`country`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_state` (`state`),
  KEY `idx_city` (`city`),
  KEY `idx_location` (`latitude`,`longitude`),
  CONSTRAINT `fk_user_locations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Armazena múltiplas localizações de usuários';

-- Dumping structure for table vsg.user_notification_settings
CREATE TABLE IF NOT EXISTS `user_notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `email_notifications` tinyint(1) DEFAULT '1',
  `vendas_notifications` tinyint(1) DEFAULT '1',
  `funcionarios_notifications` tinyint(1) DEFAULT '1',
  `relatorios_notifications` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_settings` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `user_notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table vsg.user_notification_settings: ~0 rows (approximately)

-- Dumping structure for table vsg.user_subscriptions
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `plan_id` int NOT NULL,
  `status` enum('active','cancelled','expired','suspended','trial') DEFAULT 'active',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  `auto_renew` tinyint(1) DEFAULT '1',
  `mrr` decimal(10,2) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table vsg.user_subscriptions: ~0 rows (approximately)

-- Dumping structure for table vsg.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('person','company','admin','employee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','admin','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apelido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL do avatar do usuário',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_corporativo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'País do usuário',
  `country_code` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código ISO do país (ex: MZ, BR, PT)',
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado/Província',
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cidade',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Endereço completo',
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código postal/CEP',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Latitude GPS',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Longitude GPS',
  `location_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Última atualização da localização',
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `secure_id_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','active','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `email_token` char(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_token_expires` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `registration_step` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email_pending',
  `last_activity` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `uid_generated_at` datetime DEFAULT NULL,
  `lock_until` datetime DEFAULT NULL,
  `two_fa_code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_in_lockdown` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `telefone` (`telefone`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `email_corporativo` (`email_corporativo`),
  KEY `idx_email_verified` (`email_verified_at`),
  KEY `idx_registration_step` (`registration_step`),
  KEY `idx_status` (`status`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_type_deleted` (`type`,`deleted_at`),
  KEY `idx_country` (`country`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_state` (`state`),
  KEY `idx_city` (`city`),
  KEY `idx_location` (`latitude`,`longitude`),
  KEY `idx_users_type_status` (`type`,`status`,`deleted_at`),
  KEY `idx_users_email_verified` (`email`,`email_verified_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de usuários com suporte a localização global';

-- Dumping structure for view vsg.view_active_carts_summary
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_active_carts_summary` (
	`cart_id` INT UNSIGNED NOT NULL,
	`user_id` BIGINT UNSIGNED NOT NULL,
	`customer_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`customer_email` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`total_items` BIGINT NOT NULL,
	`total_quantity` DECIMAL(32,0) NULL,
	`cart_total` DECIMAL(44,2) NULL,
	`created_at` TIMESTAMP NULL,
	`updated_at` TIMESTAMP NULL,
	`days_since_update` INT NULL
);

-- Dumping structure for view vsg.view_orders_complete
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_orders_complete` (
	`id` INT UNSIGNED NULL,
	`company_id` BIGINT UNSIGNED NULL,
	`customer_id` BIGINT UNSIGNED NULL,
	`order_number` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`order_date` DATETIME NULL,
	`delivery_date` DATE NULL,
	`delivered_at` DATETIME NULL,
	`subtotal` DECIMAL(12,2) NULL,
	`discount` DECIMAL(12,2) NULL,
	`tax` DECIMAL(12,2) NULL,
	`shipping_cost` DECIMAL(12,2) NULL,
	`total` DECIMAL(12,2) NULL,
	`currency` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`status` ENUM('pendente','confirmado','processando','enviado','entregue','cancelado') NULL COLLATE 'utf8mb4_unicode_ci',
	`payment_status` ENUM('pendente','pago','parcial','reembolsado') NULL COLLATE 'utf8mb4_unicode_ci',
	`payment_method` ENUM('mpesa','emola','visa','mastercard','manual') NULL COLLATE 'utf8mb4_unicode_ci',
	`payment_date` DATETIME NULL,
	`confirmed_by_employee` INT NULL COMMENT 'Funcionário que confirmou pagamento manual',
	`confirmed_at` DATETIME NULL,
	`shipping_address` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`shipping_city` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`shipping_phone` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`customer_notes` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`internal_notes` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` TIMESTAMP NULL,
	`updated_at` TIMESTAMP NULL,
	`deleted_at` DATETIME NULL,
	`customer_full_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`customer_email` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`customer_phone` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`items_count` BIGINT NULL,
	`total_items` DECIMAL(32,0) NULL,
	`requires_manual_confirmation` INT NOT NULL
);

-- Dumping structure for view vsg.view_products_enhanced
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_products_enhanced` (
	`id` INT NOT NULL,
	`user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Empresa dona do produto',
	`category_id` INT NULL,
	`nome` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`descricao` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`imagem` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`image_path1` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`preco` DECIMAL(10,2) NOT NULL,
	`currency` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`stock` INT NOT NULL COMMENT 'Quantidade em estoque',
	`stock_minimo` INT NULL COMMENT 'Alerta de estoque baixo',
	`status` ENUM('ativo','inativo','esgotado') NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` TIMESTAMP NULL,
	`updated_at` TIMESTAMP NULL,
	`total_sales` INT NULL COMMENT 'Total de vendas (cache para performance)',
	`days_old` INT NULL,
	`category_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`category_icon` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`company_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`avg_rating` DECIMAL(7,4) NOT NULL,
	`review_count` BIGINT NOT NULL,
	`is_new` INT NOT NULL,
	`is_popular` INT NOT NULL,
	`is_low_stock` INT NOT NULL
);

-- Dumping structure for view vsg.view_products_stats
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_products_stats` 
);

-- Dumping structure for view vsg.view_sales_complete
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_sales_complete` (
	`id` BIGINT UNSIGNED NULL,
	`order_id` INT UNSIGNED NULL,
	`order_item_id` INT UNSIGNED NULL,
	`company_id` BIGINT UNSIGNED NULL,
	`customer_id` BIGINT UNSIGNED NULL,
	`product_id` INT NULL,
	`sale_date` DATETIME NULL,
	`quantity` INT NULL,
	`unit_price` DECIMAL(12,2) NULL,
	`discount` DECIMAL(12,2) NULL,
	`total` DECIMAL(12,2) NULL,
	`currency` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`order_status` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`payment_status` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`payment_method` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`product_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`product_category` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`customer_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` TIMESTAMP NULL,
	`order_number` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`shipping_city` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`current_product_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`product_status` ENUM('ativo','inativo','esgotado') NULL COLLATE 'utf8mb4_unicode_ci',
	`customer_email` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`customer_phone` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`company_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`total_paid` DECIMAL(34,2) NULL
);

-- Dumping structure for view vsg.view_urgent_notifications
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_urgent_notifications` (
	`id` BIGINT UNSIGNED NOT NULL,
	`sender_id` BIGINT UNSIGNED NULL,
	`receiver_id` BIGINT UNSIGNED NOT NULL,
	`reply_to` BIGINT UNSIGNED NULL,
	`category` ENUM('compra_pendente','compra_confirmada','pagamento_manual','pagamento','estoque_baixo','novo_pedido','sistema','alerta','importante') NULL COLLATE 'utf8mb4_unicode_ci',
	`priority` ENUM('baixa','media','alta','critica') NULL COLLATE 'utf8mb4_unicode_ci',
	`subject` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`message` TEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`related_order_id` INT UNSIGNED NULL,
	`related_product_id` INT NULL,
	`status` ENUM('nao_lida','lida','arquivada') NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` TIMESTAMP NULL,
	`receiver_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`receiver_email` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`order_number` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`product_name` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci'
);

-- Dumping structure for view vsg.view_users_with_location
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `view_users_with_location` (
	`id` BIGINT UNSIGNED NOT NULL,
	`public_id` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`type` ENUM('person','company','admin','employee') NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`nome` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`apelido` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`email` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`telefone` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`avatar` VARCHAR(1) NULL COMMENT 'URL do avatar do usuário' COLLATE 'utf8mb4_unicode_ci',
	`status` ENUM('pending','active','blocked') NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` DATETIME NULL,
	`country` VARCHAR(1) NULL COMMENT 'País do usuário' COLLATE 'utf8mb4_unicode_ci',
	`country_code` VARCHAR(1) NULL COMMENT 'Código ISO (MZ, BR, PT)' COLLATE 'utf8mb4_unicode_ci',
	`state` VARCHAR(1) NULL COMMENT 'Estado/Província' COLLATE 'utf8mb4_unicode_ci',
	`city` VARCHAR(1) NULL COMMENT 'Cidade' COLLATE 'utf8mb4_unicode_ci',
	`latitude` DECIMAL(10,8) NULL COMMENT 'Latitude GPS',
	`longitude` DECIMAL(11,8) NULL COMMENT 'Longitude GPS',
	`location_updated_at` TIMESTAMP NULL
);

-- Dumping structure for trigger vsg.trg_cleanup_abandoned_carts
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `trg_cleanup_abandoned_carts` BEFORE UPDATE ON `shopping_carts` FOR EACH ROW BEGIN
    -- Se o carrinho não foi atualizado há mais de 30 dias, marcar como abandonado
    IF NEW.status = 'active' 
       AND DATEDIFF(NOW(), OLD.updated_at) > 30 THEN
        SET NEW.status = 'abandoned';
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.trg_create_sale_record
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `trg_create_sale_record` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    INSERT INTO sales_records (
        order_id, order_item_id, company_id, customer_id, product_id,
        sale_date, quantity, unit_price, discount, total, currency,
        order_status, payment_status, payment_method,
        product_name, product_category, customer_name
    )
    SELECT 
        NEW.order_id, NEW.id, o.company_id, o.customer_id, NEW.product_id,
        o.order_date, NEW.quantity, NEW.unit_price, NEW.discount, NEW.total, 'MZN',
        o.status, o.payment_status, o.payment_method,
        NEW.product_name, NEW.product_category, 
        CONCAT(u.nome, ' ', COALESCE(u.apelido, ''))
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    WHERE o.id = NEW.order_id; END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.trg_notify_new_order
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `trg_notify_new_order` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    DECLARE v_notification_message TEXT; DECLARE v_notification_priority VARCHAR(10); DECLARE v_customer_name VARCHAR(200); SELECT CONCAT(nome, ' ', COALESCE(apelido, '')) INTO v_customer_name
    FROM users WHERE id = NEW.customer_id; IF NEW.payment_method = 'manual' THEN
        SET v_notification_message = CONCAT(
            'Novo pedido #', NEW.order_number, ' com PAGAMENTO MANUAL. ',
            'Valor: ', NEW.total, ' ', NEW.currency, '. ',
            'Cliente: ', v_customer_name, '. ',
            'Endereço: ', COALESCE(NEW.shipping_address, 'Não informado'), '. ',
            'ATENÇÃO: Aguardando confirmação de pagamento após entrega!'
        ); SET v_notification_priority = 'critica'; ELSE
        SET v_notification_message = CONCAT(
            'Novo pedido #', NEW.order_number, ' recebido. ',
            'Valor: ', NEW.total, ' ', NEW.currency, '. ',
            'Forma de pagamento: ', UPPER(NEW.payment_method), '. ',
            'Cliente: ', v_customer_name
        ); SET v_notification_priority = 'alta'; END IF; INSERT INTO notifications (receiver_id, category, priority, subject, message, related_order_id)
    VALUES (
        NEW.company_id,
        CASE WHEN NEW.payment_method = 'manual' THEN 'pagamento_manual' ELSE 'novo_pedido' END,
        v_notification_priority,
        CONCAT('Novo Pedido #', NEW.order_number),
        v_notification_message,
        NEW.id
    ); END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.trg_product_version_on_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `trg_product_version_on_update` AFTER UPDATE ON `products` FOR EACH ROW BEGIN
    DECLARE v_change_type VARCHAR(20);
    DECLARE v_description VARCHAR(255);
    
    IF OLD.preco != NEW.preco THEN
        SET v_change_type = 'preco';
        SET v_description = CONCAT('Preço alterado de ', OLD.preco, ' para ', NEW.preco);
    ELSEIF OLD.stock != NEW.stock THEN
        SET v_change_type = 'estoque';
        SET v_description = CONCAT('Estoque alterado de ', OLD.stock, ' para ', NEW.stock);
    ELSEIF OLD.status != NEW.status THEN
        SET v_change_type = 'status';
        SET v_description = CONCAT('Status alterado de ', OLD.status, ' para ', NEW.status);
    ELSE
        SET v_change_type = 'edicao';
        SET v_description = 'Edição geral do produto';
    END IF;
    
    INSERT INTO product_versions (
        product_id, nome, descricao, preco, preco_original, stock, stock_minimo, 
        status, changed_by, change_type, change_description
    ) VALUES (
        NEW.id, NEW.nome, NEW.descricao, NEW.preco, NEW.preco_original, 
        NEW.stock, NEW.stock_minimo, NEW.status, NEW.updated_by, v_change_type, v_description
    );
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.trg_reduce_stock_on_order
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `trg_reduce_stock_on_order` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE v_stock_before INT; DECLARE v_stock_after INT; DECLARE v_stock_min INT; DECLARE v_product_name VARCHAR(150); DECLARE v_company_id BIGINT UNSIGNED; SELECT stock, stock_minimo, nome INTO v_stock_before, v_stock_min, v_product_name 
    FROM products WHERE id = NEW.product_id; UPDATE products 
    SET stock = stock - NEW.quantity,
        status = CASE 
            WHEN (stock - NEW.quantity) <= 0 THEN 'esgotado'
            ELSE status
        END
    WHERE id = NEW.product_id; SELECT stock INTO v_stock_after FROM products WHERE id = NEW.product_id; INSERT INTO stock_movements (product_id, order_id, type, quantity, stock_before, stock_after, notes)
    SELECT NEW.product_id, NEW.order_id, 'venda', -NEW.quantity, v_stock_before, v_stock_after, 
           CONCAT('Venda automática - Pedido #', order_number)
    FROM orders WHERE id = NEW.order_id; IF v_stock_after <= v_stock_min THEN
        SELECT user_id INTO v_company_id FROM products WHERE id = NEW.product_id; INSERT INTO notifications (receiver_id, category, priority, subject, message, related_product_id)
        VALUES (v_company_id, 'estoque_baixo', 'alta',
                CONCAT('Estoque Baixo: ', v_product_name),
                CONCAT('O produto "', v_product_name, '" está com estoque baixo (', v_stock_after, ' unidades). Considere reabastecer.'),
                NEW.product_id); END IF; END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.TRG_SubtrairEstoque_Venda
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `TRG_SubtrairEstoque_Venda` BEFORE INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE estoque_atual INT;

    -- Busca o estoque atual do produto
    SELECT stock INTO estoque_atual FROM products WHERE id = NEW.product_id;

    -- Verifica se o estoque resultante será negativo
    IF (estoque_atual - NEW.quantity) < 0 THEN
        -- Cancela a inserção e exibe mensagem de erro amigável
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Operação Cancelada: Estoque insuficiente para concluir a venda.';
    ELSE
        -- Se houver estoque, realiza a subtração
        UPDATE products 
        SET stock = stock - NEW.quantity 
        WHERE id = NEW.product_id;
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.trg_sync_user_location
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `trg_sync_user_location` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF (OLD.country != NEW.country OR OLD.city != NEW.city OR 
        OLD.latitude != NEW.latitude OR OLD.longitude != NEW.longitude) THEN
        
        INSERT INTO user_locations (
            user_id, country, country_code, state, city, 
            latitude, longitude, is_primary
        ) VALUES (
            NEW.id, NEW.country, NEW.country_code, NEW.state, NEW.city,
            NEW.latitude, NEW.longitude, 1
        )
        ON DUPLICATE KEY UPDATE
            country = NEW.country,
            country_code = NEW.country_code,
            state = NEW.state,
            city = NEW.city,
            latitude = NEW.latitude,
            longitude = NEW.longitude,
            updated_at = CURRENT_TIMESTAMP;
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.trg_update_product_sales_cache
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `trg_update_product_sales_cache` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    UPDATE products 
    SET total_sales = COALESCE(total_sales, 0) + NEW.quantity
    WHERE id = NEW.product_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger vsg.trg_user_initial_location
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `trg_user_initial_location` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.country IS NOT NULL OR NEW.city IS NOT NULL THEN
        INSERT INTO user_locations (
            user_id, country, country_code, state, city, 
            latitude, longitude, is_primary
        ) VALUES (
            NEW.id, NEW.country, NEW.country_code, NEW.state, NEW.city,
            NEW.latitude, NEW.longitude, 1
        );
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;
