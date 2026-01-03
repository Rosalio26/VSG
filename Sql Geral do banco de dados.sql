USE vsg;
/* 1. Ajustar a tabela users para a nova lógica */

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE login_logs;
TRUNCATE TABLE login_attempts;
TRUNCATE TABLE remember_me;
TRUNCATE TABLE businesses;
TRUNCATE TABLE admin_audit_logs;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE `admin_audit_logs` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`admin_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
	`action` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`ip_address` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`created_at` TIMESTAMP NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `admin_id` (`admin_id`) USING BTREE,
	CONSTRAINT `admin_audit_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=13
;


CREATE TABLE `businesses` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` BIGINT(20) UNSIGNED NOT NULL,
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
	`updated_at` TIMESTAMP NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `tax_id_unique` (`tax_id`) USING BTREE,
	UNIQUE INDEX `user_id_unique` (`user_id`) USING BTREE,
	CONSTRAINT `fk_business_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=3
;


CREATE TABLE `login_attempts` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`email` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_general_ci',
	`ip` VARCHAR(45) NOT NULL COLLATE 'utf8mb4_general_ci',
	`attempts` INT(11) NULL DEFAULT '1',
	`last_attempt` DATETIME NOT NULL,
	PRIMARY KEY (`id`) USING BTREE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=2
;



CREATE TABLE `login_logs` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` BIGINT(20) UNSIGNED NOT NULL,
	`ip_address` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`user_agent` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci',
	`login_time` DATETIME NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `fk_logs_user` (`user_id`) USING BTREE,
	CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
;



CREATE TABLE `remember_me` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` BIGINT(20) UNSIGNED NOT NULL,
	`token_hash` CHAR(64) NOT NULL COLLATE 'utf8mb4_general_ci',
	`expires_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `fk_remember_user` (`user_id`) USING BTREE,
	CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
;



CREATE TABLE `users` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	`updated_at` DATETIME NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	`password_changed_at` DATETIME NULL DEFAULT current_timestamp(),
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
	CONSTRAINT `chk_public_id` CHECK (`public_id` regexp '^[0-9]{8}[PC]$')
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=6
;
