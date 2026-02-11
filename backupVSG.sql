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

-- Dumping data for table vsg.businesses: ~27 rows (approximately)
INSERT INTO `businesses` (`id`, `user_id`, `tax_id`, `tax_id_file`, `business_type`, `description`, `country`, `region`, `city`, `license_path`, `logo_path`, `status_documentos`, `motivo_rejeicao`, `updated_at`) VALUES
	(1, 2, NULL, 'tax_47bbc172b7b0cb44df3b.png', 'sa', 'Venda de materiais de construcao', NULL, 'Maputo', 'Maputo', 'lic_0ca72b6512d15bffca96.png', 'log_101ce42a4937d8edb6d2.png', 'pendente', NULL, '2026-02-04 15:40:09'),
	(2, 3, NULL, 'tax_37db5c1ed26469429a74.pdf', 'mei', 'VEnda e reciclagem de diversos tipos de papel', NULL, 'Sunaia', 'Gilibida', 'lic_35b6797efec251aec373.jpg', 'log_71cc3aedd4431a66efbb.jpg', 'pendente', NULL, '2026-02-04 15:40:09'),
	(3, 4, 'NUIT100001', NULL, 'sa', 'Especializada em produtos orgânicos e sustentáveis para o dia a dia.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(4, 5, 'NUIT100002', NULL, 'sa', 'Soluções tecnológicas verdes para empresas e residências.', NULL, 'Sofala', 'Beira', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(5, 6, 'NUIT100003', NULL, 'ltda', 'Centro de reciclagem e tratamento de resíduos orgânicos.', NULL, 'Maputo', 'Matola', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(6, 7, 'NUIT100004', NULL, 'mei', 'Produtos naturais e orgânicos certificados.', NULL, 'Nampula', 'Nampula', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(7, 8, 'NUIT100005', NULL, 'sa', 'Painéis solares e sistemas de energia renovável.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(8, 9, 'NUIT100006', NULL, 'ltda', 'Agricultura sustentável e produtos orgânicos.', NULL, 'Zambézia', 'Quelimane', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(9, 10, 'NUIT100007', NULL, 'ltda', 'Móveis e estruturas em bambu certificado.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(10, 11, 'NUIT100008', NULL, 'mei', 'Produtos de limpeza oceânica e praias.', NULL, 'Inhambane', 'Inhambane', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(11, 12, 'NUIT100009', NULL, 'ltda', 'Fertilizantes orgânicos e compostagem.', NULL, 'Tete', 'Tete', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(12, 13, 'NUIT100010', NULL, 'sa', 'Sistemas de energia solar e eólica.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(13, 14, 'NUIT100011', NULL, 'ltda', 'Tecidos sustentáveis e roupas ecológicas.', NULL, 'Maputo', 'Matola', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(14, 15, 'NUIT100012', NULL, 'mei', 'Cosméticos naturais e orgânicos.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(15, 16, 'NUIT100013', NULL, 'sa', 'Madeira certificada FSC para construção.', NULL, 'Cabo Delgado', 'Pemba', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(16, 17, 'NUIT100014', NULL, 'ltda', 'Alimentos orgânicos frescos e naturais.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(17, 18, 'NUIT100015', NULL, 'ltda', 'Centro de reciclagem completo.', NULL, 'Sofala', 'Beira', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(18, 19, 'NUIT100016', NULL, 'mei', 'Filtros e sistemas de purificação de água.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(19, 20, 'NUIT100017', NULL, 'ltda', 'Móveis sustentáveis para casa e escritório.', NULL, 'Maputo', 'Matola', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(20, 21, 'NUIT100018', NULL, 'mei', 'Jardinagem urbana e vertical.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(21, 22, 'NUIT100019', NULL, 'ltda', 'Sistemas de compostagem residencial e comercial.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(22, 23, 'NUIT100020', NULL, 'sa', 'Embalagens biodegradáveis para delivery.', NULL, 'Maputo', 'Maputo', NULL, NULL, 'aprovado', NULL, '2026-02-04 15:40:09'),
	(23, 24, NULL, 'tax_2e90f13faf987bcba863.png', 'ltda', '', 'MZ', '', '', 'lic_ee574f5966ba39af015a.png', NULL, 'pendente', NULL, '2026-01-27 11:41:44'),
	(24, 25, '4643743634764364764', NULL, 'ltda', '', 'MZ', 'Cidade de Maputo', 'Maputo', 'lic_4309cf39746af362f0e3.png', NULL, 'pendente', NULL, '2026-01-27 17:20:24'),
	(25, 26, NULL, 'tax_a71f781827da680a5a74.png', 'ltda', 'Artigos de jornal', 'MZ', 'Cidade de Maputo', 'Maputo', 'lic_0478123b41030430f5dc.png', NULL, 'pendente', NULL, '2026-02-03 22:11:43'),
	(26, 27, NULL, 'tax_1adbf012d7a3265b9e70.png', 'ltda', '', 'MZ', 'Cidade de Maputo', 'Maputo', 'lic_337dc14da75f493dc8d8.jpg', NULL, 'pendente', NULL, '2026-02-03 22:25:09'),
	(27, 28, NULL, 'tax_aa061d782de9e0ab7ce0.jpg', 'ltda', '', 'MZ', 'Cidade de Maputo', 'Maputo', 'lic_4d09999eb2f747473adc.png', NULL, 'pendente', NULL, '2026-02-04 15:38:12'),
	(28, 29, NULL, 'tax_29dcdeb83ab7be70c3ac.jpg', 'sa', '', 'MZ', 'Cidade de Maputo', 'Maputo', 'lic_20137e7e0bd2de223442.jpg', NULL, 'pendente', NULL, '2026-02-04 15:47:42');

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

-- Dumping data for table vsg.categories: ~75 rows (approximately)
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `parent_id`, `created_at`, `updated_at`, `status`, `created_by_user_id`) VALUES
	(1, 'Embalagens & Logística', 'embalagens-logistica', NULL, 'box-open', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(2, 'Agricultura & Jardinagem', 'agricultura-jardinagem', NULL, 'seedling', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(3, 'Energia & Eficiência', 'energia-eficiencia', NULL, 'solar-panel', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(4, 'Construção & Arquitetura', 'construcao-arquitetura', NULL, 'city', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(5, 'Têxtil & Moda', 'textil-moda', NULL, 'tshirt', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(6, 'Higiene & Limpeza', 'higiene-limpeza', NULL, 'pump-soap', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(7, 'Gestão de Resíduos', 'gestao-residuos', NULL, 'recycle', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(8, 'Escritório & Papelaria', 'escritorio-papelaria', NULL, 'stapler', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(9, 'Alimentos & Bebidas', 'alimentos-bebidas', NULL, 'carrot', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(10, 'Tecnologia & Eletrônicos', 'tecnologia-eletronicos', NULL, 'laptop', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(11, 'Transporte & Mobilidade', 'transporte-mobilidade', NULL, 'bicycle', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(12, 'Saúde & Bem-estar', 'saude-bem-estar', NULL, 'heartbeat', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(13, 'Casa & Decoração', 'casa-decoracao', NULL, 'home', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(14, 'Educação & Treinamento', 'educacao-treinamento', NULL, 'graduation-cap', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(15, 'Serviços Ambientais', 'servicos-ambientais', NULL, 'leaf', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(16, 'Pet Care Sustentável', 'pet-care', NULL, 'paw', NULL, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(17, 'Caixas & Envelopes Eco', 'caixas-envelopes', NULL, 'box', 1, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(18, 'Embalagens Alimentícias', 'embalagens-alimentos', NULL, 'utensils', 1, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(19, 'Sacolas & Bolsas', 'sacolas-bolsas', NULL, 'shopping-bag', 1, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(20, 'Vidros & Potes', 'vidros-potes', NULL, 'prescription-bottle', 1, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(21, 'Fertilizantes Orgânicos', 'fertilizantes', NULL, 'leaf', 2, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(22, 'Sementes & Mudas', 'sementes-mudas', NULL, 'hand-holding-water', 2, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(23, 'Ferramentas Agrícolas', 'ferramentas-agro', NULL, 'hammer', 2, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(24, 'Irrigação Eficiente', 'irrigacao', NULL, 'faucet', 2, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(25, 'Energia Solar', 'solar', NULL, 'sun', 3, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(26, 'Iluminação LED', 'iluminacao', NULL, 'lightbulb', 3, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(27, 'Baterias & Armazenamento', 'baterias', NULL, 'battery-full', 3, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(28, 'Bambu & Madeiras FSC', 'bambu-madeiras', NULL, 'tree', 4, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(29, 'Tintas Ecológicas', 'tintas', NULL, 'paint-roller', 4, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(30, 'Tijolos & Blocos Eco', 'tijolos', NULL, 'cube', 4, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(31, 'Gestão de Água', 'gestao-agua', NULL, 'water', 4, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(32, 'Tecidos Naturais', 'tecidos', NULL, 'scroll', 5, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(33, 'Uniformes Corporativos', 'uniformes', NULL, 'user-tie', 5, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(34, 'Couro Vegetal', 'couro-vegetal', NULL, 'leaf', 5, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(35, 'Calçados Sustentáveis', 'calcados', NULL, 'shoe-prints', 5, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(36, 'Acessórios de Moda', 'acessorios-moda', NULL, 'gem', 5, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(37, 'Limpeza Profissional', 'limpeza-profissional', NULL, 'broom', 6, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(38, 'Amenities Hotelaria', 'amenities', NULL, 'hotel', 6, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(39, 'Ingredientes Naturais', 'ingredientes-cosmeticos', NULL, 'flask', 6, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(40, 'Lixeiras & Coleta', 'lixeiras', NULL, 'trash-alt', 7, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(41, 'Compostagem', 'compostagem', NULL, 'recycle', 7, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(42, 'Equipamentos de Reciclagem', 'equipamentos-reciclagem', NULL, 'cogs', 7, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(43, 'Papelaria Reciclada', 'papelaria', NULL, 'book', 8, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(44, 'Brindes Ecológicos', 'brindes', NULL, 'gift', 8, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(45, 'Mobiliário Sustentável', 'mobiliario', NULL, 'chair', 8, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(46, 'Grãos & Cereais (Bulk)', 'graos-cereais', NULL, 'wheat', 9, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(47, 'Cafés & Chás Orgânicos', 'cafe-cha', NULL, 'mug-hot', 9, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(48, 'Superfoods', 'superfoods', NULL, 'apple-alt', 9, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(49, 'Eletrônicos Recondicionados', 'eletronicos-recondicionados', NULL, 'mobile-alt', 10, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(50, 'Acessórios Eco-friendly', 'acessorios-tech', NULL, 'plug', 10, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(51, 'Equipamentos Certificados', 'equipamentos-certificados', NULL, 'certificate', 10, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(52, 'Carregadores Solares', 'carregadores-solares', NULL, 'charging-station', 10, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(53, 'Bicicletas & Acessórios', 'bicicletas', NULL, 'bicycle', 11, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(54, 'Veículos Elétricos', 'veiculos-eletricos', NULL, 'car', 11, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(55, 'Scooters & Patinetes', 'scooters-patinetes', NULL, 'skating', 11, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(56, 'Logística Reversa', 'logistica-reversa', NULL, 'truck', 11, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(57, 'Produtos Naturais', 'produtos-naturais', NULL, 'spa', 12, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(58, 'Suplementos Sustentáveis', 'suplementos', NULL, 'pills', 12, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(59, 'Equipamentos Fitness Eco', 'fitness-eco', NULL, 'dumbbell', 12, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(60, 'Aromaterapia', 'aromaterapia', NULL, 'wind', 12, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(61, 'Móveis Sustentáveis', 'moveis', NULL, 'couch', 13, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(62, 'Decoração Eco-friendly', 'decoracao', NULL, 'palette', 13, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(63, 'Utensílios Reutilizáveis', 'utensilios', NULL, 'blender', 13, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(64, 'Têxteis Lar', 'texteis-lar', NULL, 'bed', 13, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(65, 'Cursos Sustentabilidade', 'cursos', NULL, 'chalkboard-teacher', 14, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(66, 'Materiais Educativos', 'materiais-educativos', NULL, 'book-open', 14, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(67, 'Workshops & Eventos', 'workshops', NULL, 'users', 14, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(68, 'Certificações Verdes', 'certificacoes', NULL, 'award', 15, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(69, 'Auditoria Ambiental', 'auditoria', NULL, 'clipboard-check', 15, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(70, 'Consultoria Sustentável', 'consultoria', NULL, 'handshake', 15, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(71, 'Gestão de Carbono', 'gestao-carbono', NULL, 'cloud', 15, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(72, 'Rações Orgânicas', 'racoes', NULL, 'bone', 16, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(73, 'Acessórios Biodegradáveis', 'acessorios-pet', NULL, 'paw', 16, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(74, 'Produtos Naturais Pet', 'produtos-naturais-pet', NULL, 'heart', 16, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL),
	(75, 'Brinquedos Ecológicos', 'brinquedos-pet', NULL, 'baseball-ball', 16, '2026-01-22 07:51:07', '2026-01-31 19:09:58', 'ativa', NULL);

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

-- Dumping data for table vsg.exchange_rates: ~42 rows (approximately)
INSERT INTO `exchange_rates` (`id`, `from_currency`, `to_currency`, `rate`, `last_updated`, `source`) VALUES
	(1, 'MZN', 'EUR', 0.01330000, '2026-02-06 10:49:42', 'api'),
	(2, 'MZN', 'BRL', 0.08220000, '2026-02-06 10:49:45', 'api'),
	(3, 'MZN', 'AOA', 14.54000000, '2026-02-06 10:49:47', 'api'),
	(4, 'MZN', 'ZAR', 0.25300000, '2026-02-06 10:49:49', 'api'),
	(5, 'MZN', 'USD', 0.01570000, '2026-02-06 10:49:51', 'api'),
	(6, 'MZN', 'GBP', 0.01150000, '2026-02-06 10:49:53', 'api'),
	(7, 'MZN', 'CNY', 0.10900000, '2026-02-06 10:49:55', 'api'),
	(8, 'MZN', 'INR', 1.42000000, '2026-02-06 10:49:57', 'api'),
	(9, 'MZN', 'TZS', 40.44000000, '2026-02-06 10:49:59', 'api'),
	(10, 'MZN', 'KES', 2.03000000, '2026-02-06 10:50:01', 'api'),
	(11, 'MZN', 'MWK', 27.29000000, '2026-02-06 10:50:03', 'api'),
	(12, 'MZN', 'CAD', 0.02140000, '2026-02-06 10:50:05', 'api'),
	(13, 'MZN', 'AUD', 0.02250000, '2026-02-06 10:50:07', 'api'),
	(14, 'MZN', 'JPY', 2.46000000, '2026-02-06 10:50:09', 'api'),
	(30, 'MZN', 'MXN', 0.27200000, '2026-02-06 10:50:11', 'api'),
	(31, 'MZN', 'ARS', 22.82000000, '2026-02-06 10:50:13', 'api'),
	(32, 'MZN', 'CLP', 13.54000000, '2026-02-06 10:50:15', 'api'),
	(33, 'MZN', 'COP', 57.09000000, '2026-02-06 10:50:17', 'api'),
	(34, 'MZN', 'PEN', 0.05270000, '2026-02-06 10:50:19', 'api'),
	(35, 'MZN', 'VES', 5.99000000, '2026-02-06 10:50:22', 'api'),
	(36, 'MZN', 'RUB', 1.20000000, '2026-02-06 10:50:24', 'api'),
	(37, 'MZN', 'TRY', 0.68300000, '2026-02-06 10:50:26', 'api'),
	(38, 'MZN', 'SAR', 0.05890000, '2026-02-06 10:50:28', 'api'),
	(40, 'MZN', 'AED', 0.05770000, '2026-02-06 10:50:31', 'api'),
	(42, 'MZN', 'EGP', 0.73600000, '2026-02-06 10:50:34', 'api'),
	(44, 'MZN', 'NGN', 21.47000000, '2026-02-06 10:50:38', 'api'),
	(46, 'MZN', 'GHS', 0.17200000, '2026-02-06 10:50:47', 'api'),
	(48, 'MZN', 'KRW', 22.72000000, '2026-02-05 10:12:19', 'api'),
	(50, 'MZN', 'THB', 0.49400000, '2026-02-05 10:12:21', 'api'),
	(52, 'MZN', 'VND', 403.25000000, '2026-02-05 10:12:23', 'api'),
	(54, 'MZN', 'IDR', 266.94000000, '2026-02-06 10:52:26', 'api'),
	(56, 'MZN', 'MYR', 0.06150000, '2026-02-05 10:12:26', 'api'),
	(58, 'MZN', 'SGD', 0.02000000, '2026-02-06 10:52:43', 'api'),
	(60, 'MZN', 'PHP', 0.92500000, '2026-02-06 10:52:45', 'api'),
	(62, 'MZN', 'NZD', 0.02620000, '2026-02-06 10:52:47', 'api'),
	(64, 'MZN', 'CHF', 0.01220000, '2026-02-06 10:52:49', 'api'),
	(66, 'MZN', 'SEK', 0.14100000, '2026-02-06 10:52:51', 'api'),
	(68, 'MZN', 'NOK', 0.15200000, '2026-02-06 10:52:53', 'api'),
	(70, 'MZN', 'DKK', 0.09910000, '2026-02-06 10:52:56', 'api'),
	(72, 'MZN', 'PLN', 0.05600000, '2026-02-06 10:52:58', 'api'),
	(74, 'MZN', 'CZK', 0.32300000, '2026-02-06 10:53:00', 'api'),
	(76, 'MZN', 'HUF', 5.04000000, '2026-02-06 10:53:03', 'api');

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

-- Dumping data for table vsg.notifications: ~8 rows (approximately)
INSERT INTO `notifications` (`id`, `sender_id`, `receiver_id`, `reply_to`, `category`, `priority`, `subject`, `message`, `related_order_id`, `related_product_id`, `attachment_url`, `status`, `read_at`, `deleted_at`, `created_at`) VALUES
	(1, NULL, 2, NULL, 'pagamento_manual', 'critica', 'Novo Pedido #VSG-20260119-8B0F98', 'Novo pedido #VSG-20260119-8B0F98 com PAGAMENTO MANUAL. Valor: 123.00 MZN. Cliente: Rest poll Milione. Endereço: Maputo, Maputo. ATENÇÃO: Aguardando confirmação de pagamento após entrega!', 1, NULL, NULL, 'nao_lida', NULL, NULL, '2026-01-19 20:59:45'),
	(2, NULL, 2, NULL, 'pagamento_manual', 'critica', 'Novo Pedido #VSG-20260119-3465C1', 'Novo pedido #VSG-20260119-3465C1 com PAGAMENTO MANUAL. Valor: 123.00 MZN. Cliente: Rest poll Milione. Endereço: Maputo, Maputo. ATENÇÃO: Aguardando confirmação de pagamento após entrega!', 2, NULL, NULL, 'nao_lida', NULL, NULL, '2026-01-19 21:44:39'),
	(3, 1, 2, NULL, 'sistema', 'media', 'Pedido Cancelado - #VSG-20260119-3465C1', 'O cliente cancelou o pedido. O estoque foi devolvido automaticamente.', 2, NULL, NULL, 'nao_lida', NULL, NULL, '2026-01-19 22:04:12'),
	(8, NULL, 2, NULL, 'pagamento_manual', 'critica', 'Novo Pedido #VSG-20260120-318DC3', 'Novo pedido #VSG-20260120-318DC3 com PAGAMENTO MANUAL. Valor: 560.00 MZN. Cliente: Rest poll Milione. Endereço: Maputo, Maputo. ATENÇÃO: Aguardando confirmação de pagamento após entrega!', 7, NULL, NULL, 'nao_lida', NULL, NULL, '2026-01-20 09:04:16'),
	(10, 2, 1, NULL, 'compra_confirmada', 'alta', 'Pagamento Confirmado - Pedido #VSG-20260120-318DC3', 'Seu pagamento manual foi confirmado com sucesso! Pedido #VSG-20260120-318DC3 no valor de 560.00 MZN. Seu pedido está sendo processado e será enviado em breve.', 7, NULL, NULL, 'lida', '2026-01-20 21:48:04', NULL, '2026-01-20 13:48:43'),
	(11, 2, 1, NULL, 'compra_confirmada', 'alta', 'Pagamento Confirmado - Pedido #VSG-20260119-8B0F98', 'Seu pagamento manual foi confirmado com sucesso! Pedido #VSG-20260119-8B0F98 no valor de 123.00 MZN. Seu pedido está sendo processado e será enviado em breve.', 1, NULL, NULL, 'lida', '2026-01-20 21:48:01', NULL, '2026-01-20 13:51:23'),
	(12, NULL, 2, NULL, 'pagamento_manual', 'critica', 'Novo Pedido #VSG-20260120-0D5AEC', 'Novo pedido #VSG-20260120-0D5AEC com PAGAMENTO MANUAL. Valor: 66.00 MZN. Cliente: Rest poll Milione. Endereço: Maputo, Maputo. ATENÇÃO: Aguardando confirmação de pagamento após entrega!', 9, NULL, NULL, 'nao_lida', NULL, NULL, '2026-01-20 13:58:16'),
	(13, 2, 1, NULL, 'compra_confirmada', 'alta', 'Pagamento Confirmado - Pedido #VSG-20260120-0D5AEC', 'Seu pagamento manual foi confirmado com sucesso! Pedido #VSG-20260120-0D5AEC no valor de 66.00 MZN. Seu pedido está sendo processado e será enviado em breve.', 9, NULL, NULL, 'lida', '2026-01-20 21:47:53', NULL, '2026-01-20 13:58:48');

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

-- Dumping data for table vsg.order_items: ~4 rows (approximately)
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `product_image`, `product_category`, `quantity`, `unit_price`, `discount`, `total`, `created_at`) VALUES
	(1, 1, 2, 'Garrafa323asf', 'uploads/products/prod_1768760378_696d243aeaf3.png', 'reciclavel', 1, 123.00, 0.00, 123.00, '2026-01-19 20:59:46'),
	(2, 2, 2, 'Garrafa323asf', 'uploads/products/prod_1768760378_696d243aeaf3.png', 'reciclavel', 1, 123.00, 0.00, 123.00, '2026-01-19 21:44:39'),
	(7, 7, 9, 'Garrafa12344', 'products/product_2_1768887334.png', 'servico', 5, 112.00, 0.00, 560.00, '2026-01-20 09:04:16'),
	(9, 9, 11, 'Garrafa12344', 'product_2_1768913889.png', 'visiongreen', 2, 33.00, 0.00, 66.00, '2026-01-20 13:58:17');

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

-- Dumping data for table vsg.order_status_history: ~5 rows (approximately)
INSERT INTO `order_status_history` (`id`, `order_id`, `changed_by`, `status_from`, `status_to`, `notes`, `changed_at`) VALUES
	(1, 2, 1, 'pendente', 'cancelado', 'Cancelado pelo cliente', '2026-01-19 22:04:12'),
	(2, 7, 2, 'pendente', 'confirmado', '', '2026-01-20 13:37:58'),
	(3, 7, 2, 'confirmado', 'cancelado', 'Nao disponivel', '2026-01-20 13:42:07'),
	(4, 9, 2, 'pendente', 'confirmado', '', '2026-01-20 14:04:56'),
	(5, 1, 2, 'pendente', 'confirmado', '', '2026-01-20 14:05:00');

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

-- Dumping data for table vsg.orders: ~4 rows (approximately)
INSERT INTO `orders` (`id`, `company_id`, `customer_id`, `order_number`, `order_date`, `delivery_date`, `delivered_at`, `subtotal`, `discount`, `tax`, `shipping_cost`, `total`, `currency`, `status`, `payment_status`, `payment_method`, `payment_date`, `confirmed_by_employee`, `confirmed_at`, `shipping_address`, `shipping_city`, `shipping_phone`, `customer_notes`, `internal_notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 2, 1, 'VSG-20260119-8B0F98', '2026-01-19 20:59:45', NULL, NULL, 123.00, 0.00, 0.00, 0.00, 123.00, 'MZN', 'confirmado', 'pago', 'manual', '2026-01-20 13:51:23', NULL, '2026-01-20 13:51:23', 'Maputo, Maputo', 'Maputo', '+25885217000', 'NA', '\n[2026-01-20 13:51:23] PAGAMENTO CONFIRMADO - Recibo: NA - Obs: NA', '2026-01-19 20:59:45', '2026-01-20 14:05:00', NULL),
	(2, 2, 1, 'VSG-20260119-3465C1', '2026-01-19 21:44:39', NULL, NULL, 123.00, 0.00, 0.00, 0.00, 123.00, 'MZN', 'cancelado', 'pendente', 'manual', NULL, NULL, NULL, 'Maputo, Maputo', 'Maputo', '+25885217000', 'NA', NULL, '2026-01-19 21:44:39', '2026-01-20 13:31:48', '2026-01-20 13:31:48'),
	(7, 2, 1, 'VSG-20260120-318DC3', '2026-01-20 09:04:16', NULL, NULL, 560.00, 0.00, 0.00, 0.00, 560.00, 'MZN', 'cancelado', 'pago', 'manual', '2026-01-20 13:48:43', NULL, '2026-01-20 13:48:43', 'Maputo, Maputo', 'Maputo', '+25885217000', 'Maputo', '\r\n[2026-01-20 13:42:07] CANCELADO: Nao disponivel\n[2026-01-20 13:48:43] PAGAMENTO CONFIRMADO - Recibo: NA sem recibo - Obs: NA', '2026-01-20 09:04:16', '2026-01-20 13:51:11', '2026-01-20 13:51:11'),
	(9, 2, 1, 'VSG-20260120-0D5AEC', '2026-01-20 13:58:16', NULL, NULL, 66.00, 0.00, 0.00, 0.00, 66.00, 'MZN', 'confirmado', 'pago', 'manual', '2026-01-20 13:58:48', NULL, '2026-01-20 13:58:48', 'Maputo, Maputo', 'Maputo', '+25885217000', 'Maputo', '\n[2026-01-20 13:58:48] PAGAMENTO CONFIRMADO - Recibo: 1202 - Obs: NA', '2026-01-20 13:58:16', '2026-01-20 14:04:56', NULL);

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

-- Dumping data for table vsg.payments: ~7 rows (approximately)
INSERT INTO `payments` (`id`, `order_id`, `transaction_id`, `amount`, `currency`, `payment_method`, `payment_status`, `payment_date`, `confirmed_at`, `confirmed_by_employee`, `receipt_number`, `payment_proof`, `notes`, `created_at`, `updated_at`) VALUES
	(1, 1, NULL, 123.00, 'MZN', 'manual', 'pendente', NULL, NULL, NULL, NULL, NULL, 'Pagamento manual - Aguardando confirmação da empresa', '2026-01-19 20:59:46', '2026-01-19 20:59:46'),
	(2, 2, NULL, 123.00, 'MZN', 'manual', 'pendente', NULL, NULL, NULL, NULL, NULL, 'Pagamento manual - Aguardando confirmação da empresa', '2026-01-19 21:44:39', '2026-01-19 21:44:39'),
	(7, 7, NULL, 560.00, 'MZN', 'manual', 'pendente', NULL, NULL, NULL, NULL, NULL, 'Pagamento manual - Aguardando confirmação da empresa', '2026-01-20 09:04:16', '2026-01-20 09:04:16'),
	(9, 7, NULL, 560.00, 'MZN', 'manual', 'confirmado', '2026-01-20 13:48:43', '2026-01-20 13:48:43', NULL, 'NA sem recibo', NULL, 'NA', '2026-01-20 13:48:43', '2026-01-20 13:48:43'),
	(10, 1, NULL, 123.00, 'MZN', 'manual', 'confirmado', '2026-01-20 13:51:23', '2026-01-20 13:51:23', NULL, 'NA', NULL, 'NA', '2026-01-20 13:51:23', '2026-01-20 13:51:23'),
	(11, 9, NULL, 66.00, 'MZN', 'manual', 'pendente', NULL, NULL, NULL, NULL, NULL, 'Pagamento manual - Aguardando confirmação da empresa', '2026-01-20 13:58:17', '2026-01-20 13:58:17'),
	(12, 9, NULL, 66.00, 'MZN', 'manual', 'confirmado', '2026-01-20 13:58:48', '2026-01-20 13:58:48', NULL, '1202', NULL, 'NA', '2026-01-20 13:58:48', '2026-01-20 13:58:48');

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

-- Dumping data for table vsg.product_versions: ~120 rows (approximately)
INSERT INTO `product_versions` (`id`, `product_id`, `nome`, `descricao`, `preco`, `preco_original`, `stock`, `stock_minimo`, `status`, `changed_by`, `change_type`, `change_description`, `created_at`) VALUES
	(1, 12, 'Garrafas  (Diversos)', 'Garrafas para reciclar. Varios tipos e tamanhos separados e ja prontos para serem reciclados.\r\nDisponivel garrafas do tipo: Plastico (300ml a 10L)\r\nDisponivel garrafas do tipo: Vidro (300ml a 500ml)\r\nTodos disponiveis separadamente entre plastico e vidro.', 20.00, NULL, 100, 1, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(2, 13, 'Ferros e sucata', 'Ferro do tipo sucata a ser reciclado em diversas quantidades. \r\nMinima quantidade de 10Kg para provedores menores e + de 100Kg para grandes provedores.\r\nOs produtos(ferro do tipo sucata) Estao a ser disponibilizados localmente sem garantias de entrega no local a solicitar.\r\nPagamentos aceitaveis somente presencilamente sem uso de redes eletronicas.', 100.00, NULL, 400, 1, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(3, 14, 'Papeis', 'Disponivel papeis de varios tipos de tamanho e modelos, desde canchotes ate papeis simples.\r\nTodos tem as suas medicoes e especificacoes destiguiveis para a compra ou aquisicao.\r\nAlem de produtos ja reciclados, podera ser encotrado papeis de primeira qualidade.', 120.00, NULL, 30, 1, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(4, 15, 'Caixas de Papelão Reciclado - Pack 50un', 'Caixas resistentes feitas 100% de papel reciclado. Ideais para envios e armazenamento. Disponíveis em 3 tamanhos: pequeno (20x15x10cm), médio (30x25x15cm) e grande (40x35x20cm). Suportam até 15kg.', 450.00, NULL, 200, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(5, 16, 'Embalagens Biodegradáveis para Alimentos - 100un', 'Embalagens 100% compostáveis feitas de amido de milho. Perfeitas para restaurantes e cafeterias eco-friendly. Resistentes a gordura e líquidos. Decomposição em 90 dias.', 680.00, NULL, 150, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(6, 17, 'Sacolas Ecológicas de Algodão Orgânico - Pack 25un', 'Sacolas resistentes de algodão 100% orgânico. Capacidade de 10kg. Alças reforçadas. Laváveis e reutilizáveis por anos. Personalizáveis com logo da empresa.', 890.00, NULL, 75, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(7, 18, 'Fertilizante Orgânico Premium - 25kg', 'Composto orgânico certificado rico em nutrientes. Ideal para hortas e jardins. Produzido a partir de resíduos vegetais compostados. Aumenta produtividade em até 40%.', 1250.00, NULL, 100, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(8, 19, 'Sementes Orgânicas Variadas - Kit Horta Urbana', 'Kit com 15 variedades de sementes orgânicas: tomate, alface, couve, cenoura, beterraba, manjericão e mais. Instruções detalhadas incluídas. Taxa de germinação superior a 85%.', 350.00, NULL, 80, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(9, 20, 'Painel Solar Portátil 100W', 'Painel solar dobrável de alta eficiência. Perfeito para camping, emergências ou uso residencial. Saída USB e DC. Resistente à água. Garantia de 5 anos.', 4500.00, NULL, 30, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(10, 21, 'Kit Lâmpadas LED Econômicas - 10 unidades', 'Lâmpadas LED de alta eficiência. Economia de até 90% em energia. Vida útil de 25.000 horas. Luz branca natural 6500K. Potência equivalente: 60W (consumo real: 9W).', 850.00, NULL, 120, 12, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(11, 22, 'Tecido de Algodão Orgânico - Rolo 10m', 'Tecido 100% algodão orgânico certificado. Largura: 1,50m. Ideal para roupas, lençóis e artesanato. Macio, respirável e hipoalergênico. Cores naturais disponíveis.', 1800.00, NULL, 45, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(12, 23, 'Calçados Sustentáveis Unissex', 'Tênis ecológico feito com materiais reciclados e borracha natural. Palmilha de cortiça reciclada. Confortável e durável. Disponível em 5 cores e todos os tamanhos.', 2200.00, NULL, 60, 6, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(13, 24, 'Kit Produtos de Limpeza Ecológicos - 5 itens', 'Kit completo: detergente multiuso, limpa-vidros, desengordurante, desinfetante e sabão líquido. Todos biodegradáveis, sem químicos tóxicos. Fragrâncias naturais.', 950.00, NULL, 90, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(14, 25, 'Amenities Hotelaria Sustentável - Pack 100un', 'Set completo para hotéis eco-friendly: shampoo, condicionador, sabonete e loção. Embalagens biodegradáveis. Ingredientes naturais. Certificação cruelty-free.', 3200.00, NULL, 50, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(15, 26, 'Lixeiras Seletivas 3 Compartimentos - 60L', 'Sistema de coleta seletiva com 3 compartimentos coloridos: orgânico, reciclável e rejeito. Material reciclado durável. Pedal antiderrapante. Ideal para escritórios.', 1650.00, NULL, 40, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(16, 27, 'Composteira Doméstica 50L', 'Composteira moderna e compacta. Transforma resíduos orgânicos em adubo em 60-90 dias. Sistema de ventilação. Inclui manual e minhocas californianas. Sem odor.', 1950.00, NULL, 35, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(17, 28, 'Cadernos Reciclados A4 - Pack 10un', 'Cadernos espiral com capa dura de material reciclado. 100 folhas de papel reciclado 75g/m². Pautas. Ideal para escritório e escola.', 550.00, NULL, 100, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(18, 29, 'Brindes Corporativos Ecológicos - Kit 50un', 'Kit de brindes sustentáveis: canetas de bambu, blocos de notas reciclados, sacolas de algodão e garrafinhas reutilizáveis. Personalizáveis com logo da empresa.', 2800.00, NULL, 25, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(19, 30, 'Café Orgânico Moçambicano - 1kg', 'Café 100% arábica orgânico cultivado nas montanhas de Gurué. Torrado artesanalmente. Certificação orgânica e comércio justo. Notas de chocolate e caramelo.', 850.00, NULL, 80, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(20, 31, 'Mix Superfoods Tropicais - 500g', 'Blend de superalimentos: baobá em pó, moringa, spirulina e chia. Rico em proteínas, vitaminas e antioxidantes. Ideal para smoothies e receitas saudáveis.', 1200.00, NULL, 60, 6, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(21, 32, 'Mesa de Centro Bambu Sustentável', 'Mesa elegante feita de bambu certificado FSC. Design moderno e minimalista. Resistente e durável. Dimensões: 100x60x45cm. Acabamento natural com verniz ecológico.', 5500.00, NULL, 15, 2, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(22, 33, 'Kit Utensílios de Cozinha Reutilizáveis', 'Set completo para cozinha sustentável: canudos inox, talheres de bambu, potes de vidro, wraps de cera de abelha e esponjas biodegradáveis. Zero desperdício.', 980.00, NULL, 70, 7, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(23, 34, 'Ração Orgânica para Cães - 15kg', 'Ração premium com ingredientes 100% orgânicos. Sem transgênicos, corantes ou conservantes artificiais. Rica em proteínas vegetais. Indicada para cães adultos de todos os portes.', 2400.00, NULL, 40, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(24, 35, 'Sacolas Biodegradáveis Grande - 100un', 'Sacolas 100% biodegradáveis para supermercados. Capacidade 5kg. Decomposição em 180 dias. Certificadas para contato alimentar.', 580.00, NULL, 500, 50, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(25, 36, 'Pratos Descartáveis Folha de Palmeira - 50un', 'Pratos naturais feitos de folhas de palmeira. Biodegradáveis, compostáveis. Tamanhos: 20cm e 25cm. Resistentes a líquidos.', 450.00, NULL, 300, 30, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(26, 37, 'Kit Solar Residencial 300W Completo', 'Kit completo: painel 300W, controlador MPPT, inversor 1000W, bateria 100Ah. Instalação fácil. Garantia 10 anos no painel.', 35000.00, NULL, 25, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(27, 38, 'Sensor de Movimento LED Inteligente', 'Sensor com detecção 180°, alcance 8m. Economia até 85%. Instalação wireless. App para controle remoto.', 1200.00, NULL, 150, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(28, 39, 'Kit Lixeiras Seletivas Premium 5 Compartimentos - 100L', 'Sistema completo: orgânico, papel, plástico, vidro, metal. Pedal inox. Material reciclado. Ideal para condomínios.', 2400.00, NULL, 60, 6, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(29, 40, 'Minhocário Doméstico 3 Andares', 'Sistema de vermicompostagem. Capacidade 15L. Inclui 500 minhocas californianas. Manual completo. Sem odor.', 1850.00, NULL, 45, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(30, 41, 'Óleo Essencial Lavanda Orgânico 30ml', '100% puro, extração a vapor. Certificação orgânica USDA. Relaxante, auxilia no sono. Vidro âmbar.', 650.00, NULL, 200, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(31, 42, 'Sabonete Artesanal Argila Verde - Pack 3un', 'Feito à mão, cold process. Ingredientes naturais. Sem conservantes. Pele oleosa e acneica. Vegano.', 380.00, NULL, 180, 18, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(32, 43, 'Painel Solar Policristalino 450W', 'Eficiência 18.5%. Moldura alumínio anodizado. Resistente granizo até 25mm. Garantia 25 anos performance.', 8500.00, NULL, 80, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(33, 44, 'Bateria Solar Lítio 200Ah 12V', 'Tecnologia LiFePO4. 4000+ ciclos. BMS integrado. Leve, 60% mais leve que chumbo-ácido. 10 anos vida útil.', 15000.00, NULL, 40, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(34, 45, 'Composto Orgânico Enriquecido 50kg', 'NPK natural. Esterco curtido, húmus, farinha de osso. pH balanceado. Certificado orgânico. Para hortas.', 850.00, NULL, 200, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(35, 46, 'Kit Sementes Hortaliças Orgânicas - 20 Variedades', '20 tipos: alface, tomate, couve, cenoura e mais. Taxa germinação >90%. Instruções plantio. Embalagem reciclável.', 420.00, NULL, 120, 12, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(36, 47, 'Tábua de Corte Bambu Profissional', 'Antibacteriana natural. 40x30x2cm. Resistente água. Acabamento óleo mineral. Alças laterais.', 950.00, NULL, 90, 9, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(37, 48, 'Canudos Bambu Reutilizáveis - Pack 10un + Escova', '10 canudos 20cm. Escova limpeza inclusa. Sacola algodão. Biodegradáveis. Livres BPA.', 280.00, NULL, 250, 25, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(38, 49, 'Detergente Concentrado Oceano - 5L', 'Biodegradável, pH neutro. Concentrado 1:10. Fragrância marinha natural. Não testado em animais.', 580.00, NULL, 150, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(39, 50, 'Esponjas Biodegradáveis Fibra Natural - 6un', 'Fibra vegetal + celulose. Compostáveis. Antibacterianas. Absorvem 10x seu peso. Secam rápido.', 320.00, NULL, 200, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(40, 51, 'Húmus de Minhoca Premium 30kg', '100% natural, rico nutrientes. NPK 2-1-1. Melhora estrutura solo. Ideal vasos e canteiros.', 680.00, NULL, 180, 18, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(41, 52, 'Sistema Irrigação Gotejamento 50m', 'Kit completo: mangueiras, gotejadores, timer. Economia 70% água. Fácil instalação.', 1400.00, NULL, 75, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(42, 53, 'Luminária Solar LED Jardim - 4 Unidades', 'Carga solar 8h = 12h luz. Sensor crepuscular. IP65. Estacas inox. Branco quente 3000K.', 980.00, NULL, 110, 11, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(43, 54, 'Carregador Solar Portátil 20000mAh', '3 portas USB, 1 USB-C. Painel dobrável. Lanterna LED. À prova d\'água. Para camping.', 1650.00, NULL, 85, 9, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(44, 55, 'Toalhas Banho Algodão Orgânico - Par', '70x140cm. GOTS certificado. 500g/m². Macio, absorvente. Cores naturais. Comércio justo.', 1800.00, NULL, 95, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(45, 56, 'Camisetas Corporativas Algodão Orgânico - 10un', '100% algodão orgânico. Personalização inclusa. Tamanhos P-GG. Respirável, confortável.', 2200.00, NULL, 60, 6, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(46, 57, 'Shampoo Sólido Carvão Ativado 80g', 'Rende 3 shampoos líquidos. Carvão detox. Cabelos oleosos. Zero plástico. Vegano certificado.', 450.00, NULL, 140, 14, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(47, 58, 'Desodorante Natural Creme 60g', 'Livre alumínio, parabenos. Bicarbonato + óleos essenciais. 24h proteção. Fragrâncias naturais.', 380.00, NULL, 170, 17, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(48, 59, 'Deck Madeira Cumaru FSC - m²', 'Madeira nobre certificada. Alta densidade. Resistente intempéries. 25+ anos vida útil. Instalação inclusa.', 2800.00, NULL, 500, 50, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(49, 60, 'Mesa Escritório Pinus Reflorestado', '140x70cm. Pinus certificado FSC. Gavetas com trilhos. Acabamento verniz ecológico. Montagem fácil.', 4500.00, NULL, 35, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(50, 61, 'Quinoa Orgânica Branca 1kg', 'Certificada USDA. Proteína completa. Sem glúten. Lavada, pronta consumo. Embalagem reciclável.', 750.00, NULL, 100, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(51, 62, 'Café Especial Orgânico Torrado 500g', 'Arábica altitude. Torra média. Notas chocolate, caramelo. Score SCA 85+. Moído ou grão.', 680.00, NULL, 130, 13, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(52, 63, 'Prensa Manual Garrafas PET', 'Reduz volume 80%. Capacidade 2L. Estrutura metálica. Pedal antiderrapante. Uso doméstico/comercial.', 1200.00, NULL, 45, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(53, 64, 'Biodigestor Doméstico 300L', 'Transforma resíduos em biogás + adubo. Manual instalação. Tampa hermética. Válvula segurança.', 3500.00, NULL, 20, 2, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(54, 65, 'Filtro Cerâmica Triplo Estágio', 'Remove 99.9% impurezas. Vela cerâmica + carvão ativado. 10L capacidade. Dispenser inox.', 1400.00, NULL, 70, 7, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(55, 66, 'Estante Modular Madeira Reciclada', '6 nichos. Madeira demolição tratada. 180x80x30cm. Fixação parede. Design industrial. Personalização cores.', 3200.00, NULL, 28, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(56, 67, 'Caixa de Papelão Reciclado 30x30x30cm', 'Caixa 100% reciclada, resistente e biodegradável. Ideal para e-commerce e logística sustentável.', 45.00, NULL, 150, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(57, 68, 'Envelope Kraft Reciclado A4 (100 unidades)', 'Envelopes kraft feitos de papel 100% reciclado. Resistentes e ecológicos.', 85.00, NULL, 200, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(58, 69, 'Sacola de Algodão Orgânico Personalizada', 'Sacola reutilizável de algodão 100% orgânico. Pode ser personalizada com sua marca.', 120.00, NULL, 180, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(59, 70, 'Pote de Vidro com Tampa Bambu 500ml', 'Pote hermético de vidro com tampa de bambu natural. Perfeito para armazenamento.', 95.00, NULL, 120, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(60, 71, 'Embalagem Alimentícia Biodegradável (50 un)', 'Embalagens compostáveis para alimentos. Seguras e 100% biodegradáveis.', 160.00, NULL, 90, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(61, 72, 'Fertilizante Orgânico NPK 5kg', 'Fertilizante 100% orgânico rico em NPK. Ideal para hortas e jardins sustentáveis.', 180.00, NULL, 75, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(62, 73, 'Kit Sementes Hortaliças Orgânicas (10 tipos)', 'Kit com 10 variedades de sementes orgânicas certificadas para horta caseira.', 95.00, NULL, 120, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(63, 74, 'Enxada Ecológica Cabo Madeira Certificada', 'Ferramenta durável com cabo de madeira FSC. Fabricada de forma sustentável.', 220.00, NULL, 60, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(64, 75, 'Sistema Irrigação Gotejamento 20m', 'Sistema completo de irrigação eficiente que economiza até 70% de água.', 650.00, NULL, 30, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(65, 76, 'Adubo Compostagem Orgânica 10kg', 'Composto orgânico rico em nutrientes. Perfeito para enriquecer o solo naturalmente.', 145.00, NULL, 85, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(66, 77, 'Painel Solar Fotovoltaico 100W', 'Painel solar monocristalino de alta eficiência. Energia limpa e renovável.', 3500.00, NULL, 25, 2, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(67, 78, 'Kit Lâmpada LED 12W Branco Frio (6 un)', 'Lâmpadas LED de alta durabilidade. Economia de até 80% de energia.', 280.00, NULL, 200, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(68, 79, 'Bateria Recarregável Li-ion 18650', 'Bateria recarregável de longa duração. Reduz descarte de pilhas descartáveis.', 320.00, NULL, 150, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(69, 80, 'Carregador Solar Portátil 20000mAh', 'Powerbank solar com 3 portas USB. Carregamento via energia solar ou USB.', 680.00, NULL, 55, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(70, 81, 'Refletor LED Solar 100W com Sensor', 'Refletor autônomo com painel solar integrado e sensor de presença.', 890.00, NULL, 40, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(71, 82, 'Tábua Deck Bambu Natural 15x2,5cm m²', 'Deck de bambu tratado, resistente e sustentável. Instalação fácil.', 450.00, NULL, 100, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(72, 83, 'Tinta Ecológica Látex 18L Branca', 'Tinta à base de água, sem VOC, atóxica. Perfeita para ambientes internos.', 890.00, NULL, 40, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(73, 84, 'Tijolo Ecológico Solo-Cimento (100 un)', 'Tijolos modulares sem queima. Economia de 70% em energia de produção.', 350.00, NULL, 500, 50, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(74, 85, 'Sistema Captação Água Chuva 500L', 'Sistema completo para captação e armazenamento de água pluvial.', 1200.00, NULL, 20, 2, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(75, 86, 'Verniz Ecológico Base Água 3,6L', 'Verniz atóxico à base de água. Acabamento profissional e ecológico.', 420.00, NULL, 35, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(76, 87, 'Camiseta Algodão Orgânico Unissex', 'Camiseta 100% algodão orgânico certificado. Conforto e sustentabilidade.', 350.00, NULL, 80, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(77, 88, 'Uniforme Corporativo Sustentável (Kit)', 'Kit completo de uniforme em tecido reciclado. Durável e elegante.', 1200.00, NULL, 45, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(78, 89, 'Bolsa Couro Vegetal Cacto', 'Bolsa moderna feita de couro vegetal extraído de cacto. 100% vegana.', 580.00, NULL, 30, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(79, 90, 'Tênis Reciclado PET + Borracha Natural', 'Tênis confortável feito com garrafas PET recicladas e sola de borracha natural.', 780.00, NULL, 45, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(80, 91, 'Mochila Lona Reciclada 25L', 'Mochila resistente feita de lona de caminhão reciclada. Design moderno.', 420.00, NULL, 65, 6, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(81, 92, 'Kit Limpeza Profissional Eco (5 produtos)', 'Kit completo de produtos de limpeza biodegradáveis para uso profissional.', 380.00, NULL, 60, 6, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(82, 93, 'Amenities Hotel Biodegradáveis (100 kits)', 'Kit de amenities ecológicos para hotelaria. Embalagens compostáveis.', 850.00, NULL, 40, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(83, 94, 'Óleo Essencial Lavanda Orgânico 30ml', 'Óleo essencial 100% puro e orgânico. Ideal para aromaterapia e cosméticos.', 95.00, NULL, 150, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(84, 95, 'Sabonete Artesanal Natural (Pack 5)', 'Sabonetes artesanais com ingredientes naturais. Livres de químicos agressivos.', 120.00, NULL, 180, 18, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(85, 96, 'Detergente Biodegradável Neutro 5L', 'Detergente concentrado biodegradável. Alto poder de limpeza, baixo impacto.', 180.00, NULL, 95, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(86, 97, 'Lixeira Coleta Seletiva 60L (3 comp)', 'Conjunto com 3 compartimentos para coleta seletiva. Cores padronizadas.', 420.00, NULL, 50, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(87, 98, 'Composteira Doméstica 100L', 'Composteira prática para transformar resíduos orgânicos em adubo natural.', 550.00, NULL, 35, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(88, 99, 'Triturador Resíduos Orgânicos Manual', 'Triturador manual que acelera o processo de compostagem doméstica.', 280.00, NULL, 40, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(89, 100, 'Saco Compostável 50L (Rolo 20 un)', 'Sacos 100% compostáveis certificados. Degradação em até 180 dias.', 65.00, NULL, 200, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(90, 101, 'Resma Papel Reciclado A4 500 folhas', 'Papel sulfite reciclado 75g/m². Certificado FSC. Alta alvura.', 180.00, NULL, 200, 20, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(91, 102, 'Caneta Esferográfica Reciclada (Caixa 50)', 'Canetas feitas de plástico 100% reciclado. Tinta azul de longa duração.', 85.00, NULL, 150, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(92, 103, 'Caderno Universitário Reciclado 200 folhas', 'Caderno com papel reciclado e capa de papelão reutilizado. Espiral duplo.', 45.00, NULL, 300, 30, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(93, 104, 'Kit Brindes Ecológicos Corporativos', 'Kit com caneta, caderneta e ecobag personalizáveis. Ideal para eventos.', 250.00, NULL, 80, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(94, 105, 'Mesa Escritório Bambu 120x60cm', 'Mesa moderna e resistente feita de bambu maciço. Design minimalista.', 1800.00, NULL, 15, 2, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(95, 106, 'Café Orgânico Torrado Grão 500g', 'Café 100% arábica orgânico. Torra média, notas de chocolate e caramelo.', 180.00, NULL, 90, 9, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(96, 107, 'Chá Verde Orgânico 100g', 'Chá verde em folhas soltas, cultivado organicamente. Rico em antioxidantes.', 85.00, NULL, 120, 12, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(97, 108, 'Quinoa Orgânica 1kg', 'Quinoa real orgânica certificada. Alto valor nutricional e proteico.', 220.00, NULL, 75, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(98, 109, 'Spirulina Pó Orgânica 200g', 'Superfood rico em proteínas, vitaminas e minerais. Cultivo sustentável.', 320.00, NULL, 60, 6, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(99, 110, 'Granola Orgânica Mix Nuts 500g', 'Granola artesanal com mix de nuts orgânicos. Sem açúcar refinado.', 145.00, NULL, 100, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(100, 111, 'Notebook Recondicionado i5 8GB 256GB SSD', 'Notebook Dell recondicionado certificado. Garantia de 12 meses.', 15500.00, NULL, 12, 1, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(101, 112, 'Mouse Wireless Bambu + Plástico Reciclado', 'Mouse sem fio ergonômico com acabamento em bambu natural.', 280.00, NULL, 70, 7, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(102, 113, 'Teclado USB Reciclado Certificado', 'Teclado feito de plástico 80% reciclado. Teclas silenciosas.', 420.00, NULL, 45, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(103, 114, 'Powerbank Solar 10000mAh', 'Carregador portátil com painel solar integrado. 2 portas USB.', 580.00, NULL, 55, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(104, 115, 'Fone Bluetooth Bambu + Bioplástico', 'Fone wireless premium com case de bambu. Autonomia de 30h.', 650.00, NULL, 40, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(105, 116, 'Bicicleta Urbana Aro 26 Alumínio', 'Bike urbana leve e resistente. 21 marchas, ideal para cidade.', 3200.00, NULL, 18, 2, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(106, 117, 'Kit Acessórios Bike Sustentável (5 itens)', 'Kit com farol LED, campainha, suporte, bomba e kit remendo.', 450.00, NULL, 35, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(107, 118, 'Patinete Elétrico 250W Bateria Li-ion', 'Patinete dobrável com autonomia de 25km. Velocidade até 25km/h.', 8500.00, NULL, 8, 1, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(108, 119, 'Óleo Massagem Arnica Natural 120ml', 'Óleo vegetal com extrato de arnica. Ideal para massagens relaxantes.', 85.00, NULL, 100, 10, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(109, 120, 'Whey Protein Vegano Orgânico 900g', 'Proteína vegetal de ervilha orgânica. Sabor chocolate. Sem lactose.', 580.00, NULL, 45, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(110, 121, 'Colchonete Yoga Cortiça + Borracha Natural', 'Tapete de yoga ecológico com superfície de cortiça natural. Antiderrapante.', 420.00, NULL, 30, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(111, 122, 'Kit Aromaterapia Óleos Essenciais (6 un)', 'Kit com 6 óleos essenciais puros: lavanda, eucalipto, tea tree, hortelã, laranja, alecrim.', 280.00, NULL, 70, 7, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(112, 123, 'Sofá 3 Lugares Madeira Demolição + Linho', 'Sofá artesanal com estrutura de madeira de demolição e estofado em linho.', 5800.00, NULL, 6, 1, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(113, 124, 'Luminária Pendente Bambu Trançado', 'Luminária decorativa feita de bambu natural trançado à mão.', 320.00, NULL, 40, 4, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(114, 125, 'Kit Utensílios Bambu Cozinha (5 peças)', 'Conjunto com colher, espátula, concha, pegador e escumadeira de bambu.', 180.00, NULL, 85, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(115, 126, 'Jogo Lençol Algodão Orgânico Queen', 'Jogo completo 4 peças em algodão 100% orgânico certificado. Macio e respirável.', 680.00, NULL, 35, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(116, 127, 'Tapete Fibra Natural Sisal 2x1,5m', 'Tapete resistente e elegante feito de fibra de sisal natural.', 450.00, NULL, 25, 3, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(117, 128, 'Ração Orgânica Cães Adultos 15kg', 'Ração premium com ingredientes orgânicos. Sem conservantes artificiais.', 420.00, NULL, 50, 5, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(118, 129, 'Coleira Biodegradável + Guia', 'Conjunto de coleira e guia feitos de materiais 100% biodegradáveis.', 95.00, NULL, 80, 8, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(119, 130, 'Shampoo Natural Pet 500ml', 'Shampoo com ingredientes naturais, sem sulfatos ou parabenos.', 65.00, NULL, 120, 12, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00'),
	(120, 131, 'Brinquedo Corda Algodão Orgânico', 'Brinquedo durável para cães, feito de corda de algodão 100% orgânico.', 45.00, NULL, 150, 15, 'ativo', NULL, 'edicao', 'Edição geral do produto', '2026-01-31 19:10:00');

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

-- Dumping data for table vsg.products: ~129 rows (approximately)
INSERT INTO `products` (`id`, `user_id`, `category_id`, `eco_badges`, `nome`, `descricao`, `imagem`, `image_path1`, `image_path2`, `image_path3`, `image_path4`, `preco`, `currency`, `stock`, `stock_minimo`, `status`, `visualizacoes`, `total_sales`, `created_at`, `updated_at`, `deleted_at`, `updated_by`, `preco_original`) VALUES
	(1, 2, NULL, NULL, 'ojnkfh', 'rat rays erhyzf garweyhaawyhz zxfh earty eytgdzst awsdh awt', 'products/product_2_833d7f7a_1768888822.jpg', NULL, NULL, NULL, NULL, 213.00, 'MZN', 24234, 5, 'ativo', 0, 0, '2026-01-18 18:19:38', '2026-01-20 08:55:54', '2026-01-20 08:55:54', NULL, NULL),
	(2, 2, NULL, NULL, 'Garrafa323asf', 'SDJdasj vopjsdaodg aso[gndaf sa[oang  fa g ghs gsgsdgsd fs dgsd gd aegdgsdag', 'uploads/products/prod_1768760378_696d243aeaf3.png', NULL, NULL, NULL, NULL, 123.00, 'MZN', 212, 5, 'ativo', 0, 0, '2026-01-18 18:45:37', '2026-01-20 05:21:54', '2026-01-20 05:21:54', NULL, NULL),
	(5, 2, NULL, NULL, 'Rest poll', 'NA oidif dsoiufgdsg dbgidngk ipadsg idgdaj gdignd eigdgdingdsjkg sdgdgibdjabgdg', 'products/product_2_4406dbbe_1768888814.png', NULL, NULL, NULL, NULL, 13.00, 'MZN', 133, 5, 'ativo', 0, 0, '2026-01-20 05:26:33', '2026-01-20 13:02:52', '2026-01-20 13:02:52', NULL, NULL),
	(6, 2, NULL, NULL, 'Garrafa323asf', 'AFFha fiaf aogasd hpiasdfasdhgpdaj iadophgdja;afsd\'fgasdbfg;aigag', 'products/product_2_7a73eb3a_1768888359.png', NULL, NULL, NULL, NULL, 13.00, 'MZN', 331, 5, 'ativo', 0, 0, '2026-01-20 05:28:26', '2026-01-20 08:55:41', '2026-01-20 08:55:41', NULL, NULL),
	(7, 2, NULL, NULL, 'Garrafa323asf', 'Desse campo no codigo do dashboard_person na falar muito me amostrar onde substituir no codigo de dashboard_person.php e criar o arquivo de buscar os produtos nao falar muito', 'products/product_2_7a741a02_1768888350.png', NULL, NULL, NULL, NULL, 12.00, 'MZN', 13, 5, 'ativo', 0, 0, '2026-01-20 05:33:07', '2026-01-20 13:02:47', '2026-01-20 13:02:47', NULL, NULL),
	(8, 2, NULL, NULL, 'Garrafa323asf', 'Desse campo no codigo do dashboard_person na falar muito me amostrar onde substituir no codigo de dashboard_person.php e criar o arquivo de buscar os produtos nao falar muito', 'products/product_2_1768887278.png', NULL, NULL, NULL, NULL, 43.00, 'MZN', 12, 5, 'ativo', 0, 0, '2026-01-20 05:34:38', '2026-01-20 05:38:35', '2026-01-20 05:38:35', NULL, NULL),
	(9, 2, NULL, NULL, 'Garrafa12344', 'Desse campo no codigo do dashboard_person na falar muito me amostrar onde substituir no codigo de dashboard_person.php e criar o arquivo de buscar os produtos nao falar muito', 'products/product_2_1768887334.png', 'products/prod_9_imagem1_1768890282_e09d.png', 'products/prod_9_imagem2_1768890282_2f7d.jpg', NULL, NULL, 112.00, 'MZN', 26, 5, 'ativo', 0, 0, '2026-01-20 05:35:34', '2026-01-20 13:42:07', '2026-01-20 13:02:43', NULL, NULL),
	(10, 2, NULL, NULL, 'Teste 000', 'DSo fisobf fdifhiaphgaposdfjdigbnasdf pifjafjoghaspfjpagdn aihpsgoiadurbsakgjasgdbdka gaopjgfasd', 'products/prod_10_imagem_1768893575_c687.jpg', 'products/prod_10_imagem1_1768893575_39a0.png', 'products/prod_10_imagem2_1768893575_7f40.png', 'products/prod_10_imagem3_1768893575_2348.png', 'products/prod_10_imagem4_1768893575_feac.png', 34.00, 'MZN', 5324, 5, 'ativo', 0, 0, '2026-01-20 05:48:13', '2026-01-20 08:55:34', '2026-01-20 08:55:34', NULL, NULL),
	(11, 2, NULL, NULL, 'Garrafa12344', 'Psoihfd fisdghsd gdsignsdpiogd fgpsdnhgdopisg adgpkdsn gpodng dgs', 'product_2_1768913889.png', 'product_2_1768913889_1.png', 'product_2_1768915397_2.png', 'product_2_1768913889_3.png', 'product_2_1768913889_4.png', 33.00, 'MZN', 319, 1, 'ativo', 0, 0, '2026-01-20 12:58:09', '2026-01-20 14:11:26', '2026-01-20 14:11:26', NULL, NULL),
	(12, 2, NULL, NULL, 'Garrafas  (Diversos)', 'Garrafas para reciclar. Varios tipos e tamanhos separados e ja prontos para serem reciclados.\r\nDisponivel garrafas do tipo: Plastico (300ml a 10L)\r\nDisponivel garrafas do tipo: Vidro (300ml a 500ml)\r\nTodos disponiveis separadamente entre plastico e vidro.', 'product_2_1768918877.jpg', 'product_2_1768918877_1.jpg', 'product_2_1768918877_2.jpg', 'product_2_1768918877_3.jpg', 'product_2_1768918877_4.jpg', 20.00, 'MZN', 100, 1, 'ativo', 0, 0, '2026-01-20 14:21:17', '2026-01-20 14:21:17', NULL, NULL, NULL),
	(13, 2, NULL, NULL, 'Ferros e sucata', 'Ferro do tipo sucata a ser reciclado em diversas quantidades. \r\nMinima quantidade de 10Kg para provedores menores e + de 100Kg para grandes provedores.\r\nOs produtos(ferro do tipo sucata) Estao a ser disponibilizados localmente sem garantias de entrega no local a solicitar.\r\nPagamentos aceitaveis somente presencilamente sem uso de redes eletronicas.', 'product_2_1768919098.jpg', 'product_2_1768919098_1.jpg', 'product_2_1768919098_2.jpg', 'product_2_1768919098_3.jpg', NULL, 100.00, 'MZN', 400, 1, 'ativo', 0, 0, '2026-01-20 14:24:58', '2026-01-20 14:24:58', NULL, NULL, NULL),
	(14, 3, NULL, NULL, 'Papeis', 'Disponivel papeis de varios tipos de tamanho e modelos, desde canchotes ate papeis simples.\r\nTodos tem as suas medicoes e especificacoes destiguiveis para a compra ou aquisicao.\r\nAlem de produtos ja reciclados, podera ser encotrado papeis de primeira qualidade.', 'product_3_1768919532.jpg', 'product_3_1768919532_1.jpg', 'product_3_1768919532_2.jpg', 'product_3_1768919532_3.jpg', 'product_3_1768919532_4.jpg', 120.00, 'MZN', 30, 1, 'ativo', 0, 0, '2026-01-20 14:32:12', '2026-01-20 14:32:12', NULL, NULL, NULL),
	(15, 2, 17, '["reciclavel", "biodegradavel"]', 'Caixas de Papelão Reciclado - Pack 50un', 'Caixas resistentes feitas 100% de papel reciclado. Ideais para envios e armazenamento. Disponíveis em 3 tamanhos: pequeno (20x15x10cm), médio (30x25x15cm) e grande (40x35x20cm). Suportam até 15kg.', 'products/product_2_caixas_papelao.jpg', NULL, NULL, NULL, NULL, 450.00, 'MZN', 200, 20, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(16, 2, 18, '["compostavel", "zero_waste"]', 'Embalagens Biodegradáveis para Alimentos - 100un', 'Embalagens 100% compostáveis feitas de amido de milho. Perfeitas para restaurantes e cafeterias eco-friendly. Resistentes a gordura e líquidos. Decomposição em 90 dias.', 'products/product_2_embalagens_bio.jpg', NULL, NULL, NULL, NULL, 680.00, 'MZN', 150, 15, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(17, 2, 19, '["reutilizavel", "reciclavel"]', 'Sacolas Ecológicas de Algodão Orgânico - Pack 25un', 'Sacolas resistentes de algodão 100% orgânico. Capacidade de 10kg. Alças reforçadas. Laváveis e reutilizáveis por anos. Personalizáveis com logo da empresa.', 'products/product_2_sacolas_algodao.jpg', NULL, NULL, NULL, NULL, 890.00, 'MZN', 75, 10, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(18, 3, 21, '["organico", "certificado"]', 'Fertilizante Orgânico Premium - 25kg', 'Composto orgânico certificado rico em nutrientes. Ideal para hortas e jardins. Produzido a partir de resíduos vegetais compostados. Aumenta produtividade em até 40%.', 'products/product_3_fertilizante.jpg', NULL, NULL, NULL, NULL, 1250.00, 'MZN', 100, 10, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(19, 3, 22, '["organico", "nao_ogm"]', 'Sementes Orgânicas Variadas - Kit Horta Urbana', 'Kit com 15 variedades de sementes orgânicas: tomate, alface, couve, cenoura, beterraba, manjericão e mais. Instruções detalhadas incluídas. Taxa de germinação superior a 85%.', 'products/product_3_sementes_kit.jpg', NULL, NULL, NULL, NULL, 350.00, 'MZN', 80, 8, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(20, 2, 25, '["energia_renovavel", "duravel"]', 'Painel Solar Portátil 100W', 'Painel solar dobrável de alta eficiência. Perfeito para camping, emergências ou uso residencial. Saída USB e DC. Resistente à água. Garantia de 5 anos.', 'products/product_2_painel_solar.jpg', NULL, NULL, NULL, NULL, 4500.00, 'MZN', 30, 5, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(21, 2, 26, '["economia_energia", "longa_duracao"]', 'Kit Lâmpadas LED Econômicas - 10 unidades', 'Lâmpadas LED de alta eficiência. Economia de até 90% em energia. Vida útil de 25.000 horas. Luz branca natural 6500K. Potência equivalente: 60W (consumo real: 9W).', 'products/product_2_lampadas_led.jpg', NULL, NULL, NULL, NULL, 850.00, 'MZN', 120, 12, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(22, 3, 32, '["organico", "comercio_justo"]', 'Tecido de Algodão Orgânico - Rolo 10m', 'Tecido 100% algodão orgânico certificado. Largura: 1,50m. Ideal para roupas, lençóis e artesanato. Macio, respirável e hipoalergênico. Cores naturais disponíveis.', 'products/product_3_tecido_algodao.jpg', NULL, NULL, NULL, NULL, 1800.00, 'MZN', 45, 5, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(23, 2, 35, '["reciclado", "vegano"]', 'Calçados Sustentáveis Unissex', 'Tênis ecológico feito com materiais reciclados e borracha natural. Palmilha de cortiça reciclada. Confortável e durável. Disponível em 5 cores e todos os tamanhos.', 'products/product_2_tenis_eco.jpg', NULL, NULL, NULL, NULL, 2200.00, 'MZN', 60, 6, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(24, 2, 37, '["biodegradavel", "vegano"]', 'Kit Produtos de Limpeza Ecológicos - 5 itens', 'Kit completo: detergente multiuso, limpa-vidros, desengordurante, desinfetante e sabão líquido. Todos biodegradáveis, sem químicos tóxicos. Fragrâncias naturais.', 'products/product_2_kit_limpeza.jpg', NULL, NULL, NULL, NULL, 950.00, 'MZN', 90, 10, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(25, 3, 38, '["vegano", "cruelty_free"]', 'Amenities Hotelaria Sustentável - Pack 100un', 'Set completo para hotéis eco-friendly: shampoo, condicionador, sabonete e loção. Embalagens biodegradáveis. Ingredientes naturais. Certificação cruelty-free.', 'products/product_3_amenities.jpg', NULL, NULL, NULL, NULL, 3200.00, 'MZN', 50, 5, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(26, 2, 40, '["reciclavel", "duravel"]', 'Lixeiras Seletivas 3 Compartimentos - 60L', 'Sistema de coleta seletiva com 3 compartimentos coloridos: orgânico, reciclável e rejeito. Material reciclado durável. Pedal antiderrapante. Ideal para escritórios.', 'products/product_2_lixeiras_seletivas.jpg', NULL, NULL, NULL, NULL, 1650.00, 'MZN', 40, 5, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(27, 3, 41, '["organico", "compostagem"]', 'Composteira Doméstica 50L', 'Composteira moderna e compacta. Transforma resíduos orgânicos em adubo em 60-90 dias. Sistema de ventilação. Inclui manual e minhocas californianas. Sem odor.', 'products/product_3_composteira.jpg', NULL, NULL, NULL, NULL, 1950.00, 'MZN', 35, 5, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(28, 2, 43, '["reciclado", "sustentavel"]', 'Cadernos Reciclados A4 - Pack 10un', 'Cadernos espiral com capa dura de material reciclado. 100 folhas de papel reciclado 75g/m². Pautas. Ideal para escritório e escola.', 'products/product_2_cadernos_reciclados.jpg', NULL, NULL, NULL, NULL, 550.00, 'MZN', 100, 10, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(29, 3, 44, '["ecologico", "personalizavel"]', 'Brindes Corporativos Ecológicos - Kit 50un', 'Kit de brindes sustentáveis: canetas de bambu, blocos de notas reciclados, sacolas de algodão e garrafinhas reutilizáveis. Personalizáveis com logo da empresa.', 'products/product_3_brindes_eco.jpg', NULL, NULL, NULL, NULL, 2800.00, 'MZN', 25, 3, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(30, 2, 47, '["organico", "certificado", "comercio_justo"]', 'Café Orgânico Moçambicano - 1kg', 'Café 100% arábica orgânico cultivado nas montanhas de Gurué. Torrado artesanalmente. Certificação orgânica e comércio justo. Notas de chocolate e caramelo.', 'products/product_2_cafe_organico.jpg', NULL, NULL, NULL, NULL, 850.00, 'MZN', 80, 8, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(31, 3, 48, '["organico", "superalimento"]', 'Mix Superfoods Tropicais - 500g', 'Blend de superalimentos: baobá em pó, moringa, spirulina e chia. Rico em proteínas, vitaminas e antioxidantes. Ideal para smoothies e receitas saudáveis.', 'products/product_3_superfoods.jpg', NULL, NULL, NULL, NULL, 1200.00, 'MZN', 60, 6, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(32, 2, 61, '["madeira_certificada", "artesanal"]', 'Mesa de Centro Bambu Sustentável', 'Mesa elegante feita de bambu certificado FSC. Design moderno e minimalista. Resistente e durável. Dimensões: 100x60x45cm. Acabamento natural com verniz ecológico.', 'products/product_2_mesa_bambu.jpg', NULL, NULL, NULL, NULL, 5500.00, 'MZN', 15, 2, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(33, 3, 63, '["reutilizavel", "zero_waste"]', 'Kit Utensílios de Cozinha Reutilizáveis', 'Set completo para cozinha sustentável: canudos inox, talheres de bambu, potes de vidro, wraps de cera de abelha e esponjas biodegradáveis. Zero desperdício.', 'products/product_3_kit_cozinha.jpg', NULL, NULL, NULL, NULL, 980.00, 'MZN', 70, 7, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(34, 2, 72, '["organico", "vegano"]', 'Ração Orgânica para Cães - 15kg', 'Ração premium com ingredientes 100% orgânicos. Sem transgênicos, corantes ou conservantes artificiais. Rica em proteínas vegetais. Indicada para cães adultos de todos os portes.', 'products/product_2_racao_organica.jpg', NULL, NULL, NULL, NULL, 2400.00, 'MZN', 40, 5, 'ativo', 0, 0, '2026-01-25 11:32:14', '2026-01-25 11:32:14', NULL, NULL, NULL),
	(35, 4, 17, '["reciclavel", "biodegradavel"]', 'Sacolas Biodegradáveis Grande - 100un', 'Sacolas 100% biodegradáveis para supermercados. Capacidade 5kg. Decomposição em 180 dias. Certificadas para contato alimentar.', 'produto_ecovida_sacolas.jpg', NULL, NULL, NULL, NULL, 580.00, 'MZN', 500, 50, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(36, 4, 18, '["compostavel", "organico"]', 'Pratos Descartáveis Folha de Palmeira - 50un', 'Pratos naturais feitos de folhas de palmeira. Biodegradáveis, compostáveis. Tamanhos: 20cm e 25cm. Resistentes a líquidos.', 'produto_ecovida_pratos.jpg', NULL, NULL, NULL, NULL, 450.00, 'MZN', 300, 30, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(37, 5, 25, '["energia_renovavel", "duravel"]', 'Kit Solar Residencial 300W Completo', 'Kit completo: painel 300W, controlador MPPT, inversor 1000W, bateria 100Ah. Instalação fácil. Garantia 10 anos no painel.', 'produto_greentech_solar.jpg', NULL, NULL, NULL, NULL, 35000.00, 'MZN', 25, 3, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(38, 5, 26, '["economia_energia", "duravel"]', 'Sensor de Movimento LED Inteligente', 'Sensor com detecção 180°, alcance 8m. Economia até 85%. Instalação wireless. App para controle remoto.', 'produto_greentech_sensor.jpg', NULL, NULL, NULL, NULL, 1200.00, 'MZN', 150, 15, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(39, 6, 40, '["reciclavel", "duravel"]', 'Kit Lixeiras Seletivas Premium 5 Compartimentos - 100L', 'Sistema completo: orgânico, papel, plástico, vidro, metal. Pedal inox. Material reciclado. Ideal para condomínios.', 'produto_biorecicla_lixeiras.jpg', NULL, NULL, NULL, NULL, 2400.00, 'MZN', 60, 6, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(40, 6, 41, '["organico", "compostagem"]', 'Minhocário Doméstico 3 Andares', 'Sistema de vermicompostagem. Capacidade 15L. Inclui 500 minhocas californianas. Manual completo. Sem odor.', 'produto_biorecicla_minhocario.jpg', NULL, NULL, NULL, NULL, 1850.00, 'MZN', 45, 5, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(41, 7, 39, '["organico", "vegano", "certificado"]', 'Óleo Essencial Lavanda Orgânico 30ml', '100% puro, extração a vapor. Certificação orgânica USDA. Relaxante, auxilia no sono. Vidro âmbar.', 'produto_natureza_oleo.jpg', NULL, NULL, NULL, NULL, 650.00, 'MZN', 200, 20, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(42, 7, 57, '["organico", "vegano"]', 'Sabonete Artesanal Argila Verde - Pack 3un', 'Feito à mão, cold process. Ingredientes naturais. Sem conservantes. Pele oleosa e acneica. Vegano.', 'produto_natureza_sabonete.jpg', NULL, NULL, NULL, NULL, 380.00, 'MZN', 180, 18, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(43, 8, 25, '["energia_renovavel"]', 'Painel Solar Policristalino 450W', 'Eficiência 18.5%. Moldura alumínio anodizado. Resistente granizo até 25mm. Garantia 25 anos performance.', 'produto_solarmoz_painel.jpg', NULL, NULL, NULL, NULL, 8500.00, 'MZN', 80, 8, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(44, 8, 27, '["duravel", "energia_renovavel"]', 'Bateria Solar Lítio 200Ah 12V', 'Tecnologia LiFePO4. 4000+ ciclos. BMS integrado. Leve, 60% mais leve que chumbo-ácido. 10 anos vida útil.', 'produto_solarmoz_bateria.jpg', NULL, NULL, NULL, NULL, 15000.00, 'MZN', 40, 4, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(45, 9, 21, '["organico", "certificado"]', 'Composto Orgânico Enriquecido 50kg', 'NPK natural. Esterco curtido, húmus, farinha de osso. pH balanceado. Certificado orgânico. Para hortas.', 'produto_agroverde_composto.jpg', NULL, NULL, NULL, NULL, 850.00, 'MZN', 200, 20, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(46, 9, 22, '["organico", "nao_ogm"]', 'Kit Sementes Hortaliças Orgânicas - 20 Variedades', '20 tipos: alface, tomate, couve, cenoura e mais. Taxa germinação >90%. Instruções plantio. Embalagem reciclável.', 'produto_agroverde_sementes.jpg', NULL, NULL, NULL, NULL, 420.00, 'MZN', 120, 12, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(47, 10, 28, '["duravel", "certificado", "reutilizavel"]', 'Tábua de Corte Bambu Profissional', 'Antibacteriana natural. 40x30x2cm. Resistente água. Acabamento óleo mineral. Alças laterais.', 'produto_bambu_tabua.jpg', NULL, NULL, NULL, NULL, 950.00, 'MZN', 90, 9, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(48, 10, 63, '["reutilizavel", "biodegradavel"]', 'Canudos Bambu Reutilizáveis - Pack 10un + Escova', '10 canudos 20cm. Escova limpeza inclusa. Sacola algodão. Biodegradáveis. Livres BPA.', 'produto_bambu_canudos.jpg', NULL, NULL, NULL, NULL, 280.00, 'MZN', 250, 25, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(49, 11, 37, '["biodegradavel", "vegano"]', 'Detergente Concentrado Oceano - 5L', 'Biodegradável, pH neutro. Concentrado 1:10. Fragrância marinha natural. Não testado em animais.', 'produto_oceano_detergente.jpg', NULL, NULL, NULL, NULL, 580.00, 'MZN', 150, 15, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(50, 11, 37, '["biodegradavel", "zero_waste"]', 'Esponjas Biodegradáveis Fibra Natural - 6un', 'Fibra vegetal + celulose. Compostáveis. Antibacterianas. Absorvem 10x seu peso. Secam rápido.', 'produto_oceano_esponjas.jpg', NULL, NULL, NULL, NULL, 320.00, 'MZN', 200, 20, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(51, 12, 21, '["organico", "compostavel"]', 'Húmus de Minhoca Premium 30kg', '100% natural, rico nutrientes. NPK 2-1-1. Melhora estrutura solo. Ideal vasos e canteiros.', 'produto_terra_humus.jpg', NULL, NULL, NULL, NULL, 680.00, 'MZN', 180, 18, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(52, 12, 24, '["economia_energia", "duravel"]', 'Sistema Irrigação Gotejamento 50m', 'Kit completo: mangueiras, gotejadores, timer. Economia 70% água. Fácil instalação.', 'produto_terra_irrigacao.jpg', NULL, NULL, NULL, NULL, 1400.00, 'MZN', 75, 8, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(53, 13, 26, '["economia_energia", "longa_duracao"]', 'Luminária Solar LED Jardim - 4 Unidades', 'Carga solar 8h = 12h luz. Sensor crepuscular. IP65. Estacas inox. Branco quente 3000K.', 'produto_energia_luminaria.jpg', NULL, NULL, NULL, NULL, 980.00, 'MZN', 110, 11, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(54, 13, 52, '["energia_renovavel", "duravel"]', 'Carregador Solar Portátil 20000mAh', '3 portas USB, 1 USB-C. Painel dobrável. Lanterna LED. À prova d\'água. Para camping.', 'produto_energia_carregador.jpg', NULL, NULL, NULL, NULL, 1650.00, 'MZN', 85, 9, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(55, 14, 32, '["organico", "certificado", "comercio_justo"]', 'Toalhas Banho Algodão Orgânico - Par', '70x140cm. GOTS certificado. 500g/m². Macio, absorvente. Cores naturais. Comércio justo.', 'produto_textil_toalhas.jpg', NULL, NULL, NULL, NULL, 1800.00, 'MZN', 95, 10, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(56, 14, 33, '["organico", "duravel"]', 'Camisetas Corporativas Algodão Orgânico - 10un', '100% algodão orgânico. Personalização inclusa. Tamanhos P-GG. Respirável, confortável.', 'produto_textil_camisetas.jpg', NULL, NULL, NULL, NULL, 2200.00, 'MZN', 60, 6, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(57, 15, 39, '["vegano", "organico", "cruelty_free"]', 'Shampoo Sólido Carvão Ativado 80g', 'Rende 3 shampoos líquidos. Carvão detox. Cabelos oleosos. Zero plástico. Vegano certificado.', 'produto_bio_shampoo.jpg', NULL, NULL, NULL, NULL, 450.00, 'MZN', 140, 14, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(58, 15, 57, '["organico", "vegano"]', 'Desodorante Natural Creme 60g', 'Livre alumínio, parabenos. Bicarbonato + óleos essenciais. 24h proteção. Fragrâncias naturais.', 'produto_bio_desodorante.jpg', NULL, NULL, NULL, NULL, 380.00, 'MZN', 170, 17, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(59, 16, 28, '["certificado", "duravel"]', 'Deck Madeira Cumaru FSC - m²', 'Madeira nobre certificada. Alta densidade. Resistente intempéries. 25+ anos vida útil. Instalação inclusa.', 'produto_madeira_deck.jpg', NULL, NULL, NULL, NULL, 2800.00, 'MZN', 500, 50, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(60, 16, 45, '["certificado", "duravel", "reciclavel"]', 'Mesa Escritório Pinus Reflorestado', '140x70cm. Pinus certificado FSC. Gavetas com trilhos. Acabamento verniz ecológico. Montagem fácil.', 'produto_madeira_mesa.jpg', NULL, NULL, NULL, NULL, 4500.00, 'MZN', 35, 4, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(61, 17, 46, '["organico", "nao_ogm"]', 'Quinoa Orgânica Branca 1kg', 'Certificada USDA. Proteína completa. Sem glúten. Lavada, pronta consumo. Embalagem reciclável.', 'produto_organicos_quinoa.jpg', NULL, NULL, NULL, NULL, 750.00, 'MZN', 100, 10, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(62, 17, 47, '["organico", "comercio_justo"]', 'Café Especial Orgânico Torrado 500g', 'Arábica altitude. Torra média. Notas chocolate, caramelo. Score SCA 85+. Moído ou grão.', 'produto_organicos_cafe.jpg', NULL, NULL, NULL, NULL, 680.00, 'MZN', 130, 13, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(63, 18, 42, '["reciclavel", "duravel"]', 'Prensa Manual Garrafas PET', 'Reduz volume 80%. Capacidade 2L. Estrutura metálica. Pedal antiderrapante. Uso doméstico/comercial.', 'produto_reciclagem_prensa.jpg', NULL, NULL, NULL, NULL, 1200.00, 'MZN', 45, 5, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(64, 18, 41, '["organico", "compostagem"]', 'Biodigestor Doméstico 300L', 'Transforma resíduos em biogás + adubo. Manual instalação. Tampa hermética. Válvula segurança.', 'produto_reciclagem_biodigestor.jpg', NULL, NULL, NULL, NULL, 3500.00, 'MZN', 20, 2, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(65, 19, 31, '["duravel", "economia_energia"]', 'Filtro Cerâmica Triplo Estágio', 'Remove 99.9% impurezas. Vela cerâmica + carvão ativado. 10L capacidade. Dispenser inox.', 'produto_agua_filtro.jpg', NULL, NULL, NULL, NULL, 1400.00, 'MZN', 70, 7, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(66, 20, 61, '["reciclavel", "duravel"]', 'Estante Modular Madeira Reciclada', '6 nichos. Madeira demolição tratada. 180x80x30cm. Fixação parede. Design industrial. Personalização cores.', 'produto_ecomoveis_estante.jpg', NULL, NULL, NULL, NULL, 3200.00, 'MZN', 28, 3, 'ativo', 0, 0, '2026-01-26 07:39:20', '2026-01-26 07:39:20', NULL, NULL, NULL),
	(67, 1, 17, '["reciclavel", "biodegradavel"]', 'Caixa de Papelão Reciclado 30x30x30cm', 'Caixa 100% reciclada, resistente e biodegradável. Ideal para e-commerce e logística sustentável.', NULL, NULL, NULL, NULL, NULL, 45.00, 'MZN', 150, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(68, 1, 17, '["reciclavel", "zero_waste"]', 'Envelope Kraft Reciclado A4 (100 unidades)', 'Envelopes kraft feitos de papel 100% reciclado. Resistentes e ecológicos.', NULL, NULL, NULL, NULL, NULL, 85.00, 'MZN', 200, 15, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(69, 1, 19, '["reutilizavel", "organico"]', 'Sacola de Algodão Orgânico Personalizada', 'Sacola reutilizável de algodão 100% orgânico. Pode ser personalizada com sua marca.', NULL, NULL, NULL, NULL, NULL, 120.00, 'MZN', 180, 20, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(70, 1, 20, '["reutilizavel", "duravel"]', 'Pote de Vidro com Tampa Bambu 500ml', 'Pote hermético de vidro com tampa de bambu natural. Perfeito para armazenamento.', NULL, NULL, NULL, NULL, NULL, 95.00, 'MZN', 120, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(71, 1, 18, '["biodegradavel", "compostavel"]', 'Embalagem Alimentícia Biodegradável (50 un)', 'Embalagens compostáveis para alimentos. Seguras e 100% biodegradáveis.', NULL, NULL, NULL, NULL, NULL, 160.00, 'MZN', 90, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(72, 1, 21, '["organico", "natural"]', 'Fertilizante Orgânico NPK 5kg', 'Fertilizante 100% orgânico rico em NPK. Ideal para hortas e jardins sustentáveis.', NULL, NULL, NULL, NULL, NULL, 180.00, 'MZN', 75, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(73, 1, 22, '["organico", "certificado"]', 'Kit Sementes Hortaliças Orgânicas (10 tipos)', 'Kit com 10 variedades de sementes orgânicas certificadas para horta caseira.', NULL, NULL, NULL, NULL, NULL, 95.00, 'MZN', 120, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(74, 1, 23, '["duravel", "certificado"]', 'Enxada Ecológica Cabo Madeira Certificada', 'Ferramenta durável com cabo de madeira FSC. Fabricada de forma sustentável.', NULL, NULL, NULL, NULL, NULL, 220.00, 'MZN', 60, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(75, 1, 24, '["economia_agua", "eficiente"]', 'Sistema Irrigação Gotejamento 20m', 'Sistema completo de irrigação eficiente que economiza até 70% de água.', NULL, NULL, NULL, NULL, NULL, 650.00, 'MZN', 30, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(76, 1, 21, '["organico", "zero_waste"]', 'Adubo Compostagem Orgânica 10kg', 'Composto orgânico rico em nutrientes. Perfeito para enriquecer o solo naturalmente.', NULL, NULL, NULL, NULL, NULL, 145.00, 'MZN', 85, 8, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(77, 1, 25, '["energia_limpa", "renovavel"]', 'Painel Solar Fotovoltaico 100W', 'Painel solar monocristalino de alta eficiência. Energia limpa e renovável.', NULL, NULL, NULL, NULL, NULL, 3500.00, 'MZN', 25, 2, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(78, 1, 26, '["economia_energia", "duravel"]', 'Kit Lâmpada LED 12W Branco Frio (6 un)', 'Lâmpadas LED de alta durabilidade. Economia de até 80% de energia.', NULL, NULL, NULL, NULL, NULL, 280.00, 'MZN', 200, 20, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(79, 1, 27, '["reutilizavel", "duravel"]', 'Bateria Recarregável Li-ion 18650', 'Bateria recarregável de longa duração. Reduz descarte de pilhas descartáveis.', NULL, NULL, NULL, NULL, NULL, 320.00, 'MZN', 150, 15, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(80, 1, 52, '["energia_limpa", "portatil"]', 'Carregador Solar Portátil 20000mAh', 'Powerbank solar com 3 portas USB. Carregamento via energia solar ou USB.', NULL, NULL, NULL, NULL, NULL, 680.00, 'MZN', 55, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(81, 1, 26, '["energia_limpa", "automatico"]', 'Refletor LED Solar 100W com Sensor', 'Refletor autônomo com painel solar integrado e sensor de presença.', NULL, NULL, NULL, NULL, NULL, 890.00, 'MZN', 40, 4, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(82, 1, 28, '["renovavel", "duravel"]', 'Tábua Deck Bambu Natural 15x2,5cm m²', 'Deck de bambu tratado, resistente e sustentável. Instalação fácil.', NULL, NULL, NULL, NULL, NULL, 450.00, 'MZN', 100, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(83, 1, 29, '["atoxica", "baixo_voc"]', 'Tinta Ecológica Látex 18L Branca', 'Tinta à base de água, sem VOC, atóxica. Perfeita para ambientes internos.', NULL, NULL, NULL, NULL, NULL, 890.00, 'MZN', 40, 4, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(84, 1, 30, '["economia_energia", "duravel"]', 'Tijolo Ecológico Solo-Cimento (100 un)', 'Tijolos modulares sem queima. Economia de 70% em energia de produção.', NULL, NULL, NULL, NULL, NULL, 350.00, 'MZN', 500, 50, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(85, 1, 31, '["economia_agua", "sustentavel"]', 'Sistema Captação Água Chuva 500L', 'Sistema completo para captação e armazenamento de água pluvial.', NULL, NULL, NULL, NULL, NULL, 1200.00, 'MZN', 20, 2, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(86, 1, 29, '["atoxica", "baixo_voc"]', 'Verniz Ecológico Base Água 3,6L', 'Verniz atóxico à base de água. Acabamento profissional e ecológico.', NULL, NULL, NULL, NULL, NULL, 420.00, 'MZN', 35, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(87, 1, 32, '["organico", "certificado"]', 'Camiseta Algodão Orgânico Unissex', 'Camiseta 100% algodão orgânico certificado. Conforto e sustentabilidade.', NULL, NULL, NULL, NULL, NULL, 350.00, 'MZN', 80, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(88, 1, 33, '["reciclavel", "duravel"]', 'Uniforme Corporativo Sustentável (Kit)', 'Kit completo de uniforme em tecido reciclado. Durável e elegante.', NULL, NULL, NULL, NULL, NULL, 1200.00, 'MZN', 45, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(89, 1, 34, '["vegano", "cruelty_free"]', 'Bolsa Couro Vegetal Cacto', 'Bolsa moderna feita de couro vegetal extraído de cacto. 100% vegana.', NULL, NULL, NULL, NULL, NULL, 580.00, 'MZN', 30, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(90, 1, 35, '["reciclavel", "confortavel"]', 'Tênis Reciclado PET + Borracha Natural', 'Tênis confortável feito com garrafas PET recicladas e sola de borracha natural.', NULL, NULL, NULL, NULL, NULL, 780.00, 'MZN', 45, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(91, 1, 36, '["reciclavel", "duravel"]', 'Mochila Lona Reciclada 25L', 'Mochila resistente feita de lona de caminhão reciclada. Design moderno.', NULL, NULL, NULL, NULL, NULL, 420.00, 'MZN', 65, 6, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(92, 1, 37, '["biodegradavel", "atoxica"]', 'Kit Limpeza Profissional Eco (5 produtos)', 'Kit completo de produtos de limpeza biodegradáveis para uso profissional.', NULL, NULL, NULL, NULL, NULL, 380.00, 'MZN', 60, 6, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(93, 1, 38, '["biodegradavel", "compostavel"]', 'Amenities Hotel Biodegradáveis (100 kits)', 'Kit de amenities ecológicos para hotelaria. Embalagens compostáveis.', NULL, NULL, NULL, NULL, NULL, 850.00, 'MZN', 40, 4, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(94, 1, 39, '["organico", "natural"]', 'Óleo Essencial Lavanda Orgânico 30ml', 'Óleo essencial 100% puro e orgânico. Ideal para aromaterapia e cosméticos.', NULL, NULL, NULL, NULL, NULL, 95.00, 'MZN', 150, 15, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(95, 1, 39, '["natural", "artesanal"]', 'Sabonete Artesanal Natural (Pack 5)', 'Sabonetes artesanais com ingredientes naturais. Livres de químicos agressivos.', NULL, NULL, NULL, NULL, NULL, 120.00, 'MZN', 180, 18, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(96, 1, 37, '["biodegradavel", "concentrado"]', 'Detergente Biodegradável Neutro 5L', 'Detergente concentrado biodegradável. Alto poder de limpeza, baixo impacto.', NULL, NULL, NULL, NULL, NULL, 180.00, 'MZN', 95, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(97, 1, 40, '["reciclavel", "organizacao"]', 'Lixeira Coleta Seletiva 60L (3 comp)', 'Conjunto com 3 compartimentos para coleta seletiva. Cores padronizadas.', NULL, NULL, NULL, NULL, NULL, 420.00, 'MZN', 50, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(98, 1, 41, '["zero_waste", "organico"]', 'Composteira Doméstica 100L', 'Composteira prática para transformar resíduos orgânicos em adubo natural.', NULL, NULL, NULL, NULL, NULL, 550.00, 'MZN', 35, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(99, 1, 42, '["zero_waste", "eficiente"]', 'Triturador Resíduos Orgânicos Manual', 'Triturador manual que acelera o processo de compostagem doméstica.', NULL, NULL, NULL, NULL, NULL, 280.00, 'MZN', 40, 4, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(100, 1, 41, '["compostavel", "biodegradavel"]', 'Saco Compostável 50L (Rolo 20 un)', 'Sacos 100% compostáveis certificados. Degradação em até 180 dias.', NULL, NULL, NULL, NULL, NULL, 65.00, 'MZN', 200, 20, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(101, 1, 43, '["reciclavel", "certificado"]', 'Resma Papel Reciclado A4 500 folhas', 'Papel sulfite reciclado 75g/m². Certificado FSC. Alta alvura.', NULL, NULL, NULL, NULL, NULL, 180.00, 'MZN', 200, 20, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(102, 1, 43, '["reciclavel", "economico"]', 'Caneta Esferográfica Reciclada (Caixa 50)', 'Canetas feitas de plástico 100% reciclado. Tinta azul de longa duração.', NULL, NULL, NULL, NULL, NULL, 85.00, 'MZN', 150, 15, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(103, 1, 43, '["reciclavel", "duravel"]', 'Caderno Universitário Reciclado 200 folhas', 'Caderno com papel reciclado e capa de papelão reutilizado. Espiral duplo.', NULL, NULL, NULL, NULL, NULL, 45.00, 'MZN', 300, 30, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(104, 1, 44, '["reciclavel", "personalizavel"]', 'Kit Brindes Ecológicos Corporativos', 'Kit com caneta, caderneta e ecobag personalizáveis. Ideal para eventos.', NULL, NULL, NULL, NULL, NULL, 250.00, 'MZN', 80, 8, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(105, 1, 45, '["renovavel", "duravel"]', 'Mesa Escritório Bambu 120x60cm', 'Mesa moderna e resistente feita de bambu maciço. Design minimalista.', NULL, NULL, NULL, NULL, NULL, 1800.00, 'MZN', 15, 2, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(106, 1, 47, '["organico", "certificado"]', 'Café Orgânico Torrado Grão 500g', 'Café 100% arábica orgânico. Torra média, notas de chocolate e caramelo.', NULL, NULL, NULL, NULL, NULL, 180.00, 'MZN', 90, 9, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(107, 1, 47, '["organico", "natural"]', 'Chá Verde Orgânico 100g', 'Chá verde em folhas soltas, cultivado organicamente. Rico em antioxidantes.', NULL, NULL, NULL, NULL, NULL, 85.00, 'MZN', 120, 12, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(108, 1, 46, '["organico", "nutritivo"]', 'Quinoa Orgânica 1kg', 'Quinoa real orgânica certificada. Alto valor nutricional e proteico.', NULL, NULL, NULL, NULL, NULL, 220.00, 'MZN', 75, 8, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(109, 1, 48, '["organico", "superfood"]', 'Spirulina Pó Orgânica 200g', 'Superfood rico em proteínas, vitaminas e minerais. Cultivo sustentável.', NULL, NULL, NULL, NULL, NULL, 320.00, 'MZN', 60, 6, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(110, 1, 46, '["organico", "artesanal"]', 'Granola Orgânica Mix Nuts 500g', 'Granola artesanal com mix de nuts orgânicos. Sem açúcar refinado.', NULL, NULL, NULL, NULL, NULL, 145.00, 'MZN', 100, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(111, 1, 49, '["recondicionado", "garantia"]', 'Notebook Recondicionado i5 8GB 256GB SSD', 'Notebook Dell recondicionado certificado. Garantia de 12 meses.', NULL, NULL, NULL, NULL, NULL, 15500.00, 'MZN', 12, 1, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(112, 1, 50, '["reciclavel", "ergonomico"]', 'Mouse Wireless Bambu + Plástico Reciclado', 'Mouse sem fio ergonômico com acabamento em bambu natural.', NULL, NULL, NULL, NULL, NULL, 280.00, 'MZN', 70, 7, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(113, 1, 51, '["reciclavel", "certificado"]', 'Teclado USB Reciclado Certificado', 'Teclado feito de plástico 80% reciclado. Teclas silenciosas.', NULL, NULL, NULL, NULL, NULL, 420.00, 'MZN', 45, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(114, 1, 52, '["energia_limpa", "portatil"]', 'Powerbank Solar 10000mAh', 'Carregador portátil com painel solar integrado. 2 portas USB.', NULL, NULL, NULL, NULL, NULL, 580.00, 'MZN', 55, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(115, 1, 50, '["renovavel", "premium"]', 'Fone Bluetooth Bambu + Bioplástico', 'Fone wireless premium com case de bambu. Autonomia de 30h.', NULL, NULL, NULL, NULL, NULL, 650.00, 'MZN', 40, 4, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(116, 1, 53, '["zero_emissao", "saudavel"]', 'Bicicleta Urbana Aro 26 Alumínio', 'Bike urbana leve e resistente. 21 marchas, ideal para cidade.', NULL, NULL, NULL, NULL, NULL, 3200.00, 'MZN', 18, 2, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(117, 1, 53, '["duravel", "completo"]', 'Kit Acessórios Bike Sustentável (5 itens)', 'Kit com farol LED, campainha, suporte, bomba e kit remendo.', NULL, NULL, NULL, NULL, NULL, 450.00, 'MZN', 35, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(118, 1, 55, '["eletrico", "portatil"]', 'Patinete Elétrico 250W Bateria Li-ion', 'Patinete dobrável com autonomia de 25km. Velocidade até 25km/h.', NULL, NULL, NULL, NULL, NULL, 8500.00, 'MZN', 8, 1, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(119, 1, 57, '["natural", "terapeutico"]', 'Óleo Massagem Arnica Natural 120ml', 'Óleo vegetal com extrato de arnica. Ideal para massagens relaxantes.', NULL, NULL, NULL, NULL, NULL, 85.00, 'MZN', 100, 10, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(120, 1, 58, '["vegano", "organico"]', 'Whey Protein Vegano Orgânico 900g', 'Proteína vegetal de ervilha orgânica. Sabor chocolate. Sem lactose.', NULL, NULL, NULL, NULL, NULL, 580.00, 'MZN', 45, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(121, 1, 59, '["natural", "antiderrapante"]', 'Colchonete Yoga Cortiça + Borracha Natural', 'Tapete de yoga ecológico com superfície de cortiça natural. Antiderrapante.', NULL, NULL, NULL, NULL, NULL, 420.00, 'MZN', 30, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(122, 1, 60, '["natural", "terapeutico"]', 'Kit Aromaterapia Óleos Essenciais (6 un)', 'Kit com 6 óleos essenciais puros: lavanda, eucalipto, tea tree, hortelã, laranja, alecrim.', NULL, NULL, NULL, NULL, NULL, 280.00, 'MZN', 70, 7, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(123, 1, 61, '["artesanal", "duravel"]', 'Sofá 3 Lugares Madeira Demolição + Linho', 'Sofá artesanal com estrutura de madeira de demolição e estofado em linho.', NULL, NULL, NULL, NULL, NULL, 5800.00, 'MZN', 6, 1, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(124, 1, 62, '["artesanal", "decorativo"]', 'Luminária Pendente Bambu Trançado', 'Luminária decorativa feita de bambu natural trançado à mão.', NULL, NULL, NULL, NULL, NULL, 320.00, 'MZN', 40, 4, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(125, 1, 63, '["natural", "duravel"]', 'Kit Utensílios Bambu Cozinha (5 peças)', 'Conjunto com colher, espátula, concha, pegador e escumadeira de bambu.', NULL, NULL, NULL, NULL, NULL, 180.00, 'MZN', 85, 8, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(126, 1, 64, '["organico", "confortavel"]', 'Jogo Lençol Algodão Orgânico Queen', 'Jogo completo 4 peças em algodão 100% orgânico certificado. Macio e respirável.', NULL, NULL, NULL, NULL, NULL, 680.00, 'MZN', 35, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(127, 1, 62, '["natural", "duravel"]', 'Tapete Fibra Natural Sisal 2x1,5m', 'Tapete resistente e elegante feito de fibra de sisal natural.', NULL, NULL, NULL, NULL, NULL, 450.00, 'MZN', 25, 3, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(128, 1, 72, '["organico", "natural"]', 'Ração Orgânica Cães Adultos 15kg', 'Ração premium com ingredientes orgânicos. Sem conservantes artificiais.', NULL, NULL, NULL, NULL, NULL, 420.00, 'MZN', 50, 5, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(129, 1, 73, '["biodegradavel", "resistente"]', 'Coleira Biodegradável + Guia', 'Conjunto de coleira e guia feitos de materiais 100% biodegradáveis.', NULL, NULL, NULL, NULL, NULL, 95.00, 'MZN', 80, 8, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(130, 1, 74, '["natural", "hipoalergenico"]', 'Shampoo Natural Pet 500ml', 'Shampoo com ingredientes naturais, sem sulfatos ou parabenos.', NULL, NULL, NULL, NULL, NULL, 65.00, 'MZN', 120, 12, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL),
	(131, 1, 75, '["organico", "duravel"]', 'Brinquedo Corda Algodão Orgânico', 'Brinquedo durável para cães, feito de corda de algodão 100% orgânico.', NULL, NULL, NULL, NULL, NULL, 45.00, 'MZN', 150, 15, 'ativo', 0, 0, '2026-01-26 20:42:54', '2026-01-26 20:42:54', NULL, NULL, NULL);

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

-- Dumping data for table vsg.sales_records: ~4 rows (approximately)
INSERT INTO `sales_records` (`id`, `order_id`, `order_item_id`, `company_id`, `customer_id`, `product_id`, `sale_date`, `quantity`, `unit_price`, `discount`, `total`, `currency`, `order_status`, `payment_status`, `payment_method`, `product_name`, `product_category`, `customer_name`, `created_at`) VALUES
	(1, 1, 1, 2, 1, 2, '2026-01-19 20:59:45', 1, 123.00, 0.00, 123.00, 'MZN', 'confirmado', 'pago', 'manual', 'Garrafa323asf', 'reciclavel', 'Rest poll Milione', '2026-01-19 20:59:46'),
	(2, 2, 2, 2, 1, 2, '2026-01-19 21:44:39', 1, 123.00, 0.00, 123.00, 'MZN', 'pendente', 'pendente', 'manual', 'Garrafa323asf', 'reciclavel', 'Rest poll Milione', '2026-01-19 21:44:39'),
	(3, 7, 7, 2, 1, 9, '2026-01-20 09:04:16', 5, 112.00, 0.00, 560.00, 'MZN', 'cancelado', 'pago', 'manual', 'Garrafa12344', 'servico', 'Rest poll Milione', '2026-01-20 09:04:16'),
	(4, 9, 9, 2, 1, 11, '2026-01-20 13:58:16', 2, 33.00, 0.00, 66.00, 'MZN', 'confirmado', 'pago', 'manual', 'Garrafa12344', 'visiongreen', 'Rest poll Milione', '2026-01-20 13:58:17');

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

-- Dumping data for table vsg.stock_movements: ~5 rows (approximately)
INSERT INTO `stock_movements` (`id`, `product_id`, `order_id`, `type`, `quantity`, `stock_before`, `stock_after`, `user_id`, `employee_id`, `notes`, `created_at`) VALUES
	(1, 2, 1, 'venda', -1, 213, 212, NULL, NULL, 'Venda automática - Pedido #VSG-20260119-8B0F98', '2026-01-19 20:59:46'),
	(2, 2, 2, 'venda', -1, 212, 211, NULL, NULL, 'Venda automática - Pedido #VSG-20260119-3465C1', '2026-01-19 21:44:39'),
	(3, 2, 2, 'devolucao', 1, 211, 212, 1, NULL, 'Devolução - Pedido cancelado #VSG-20260119-3465C1', '2026-01-19 22:04:12'),
	(4, 9, 7, 'venda', -5, 26, 21, NULL, NULL, 'Venda automática - Pedido #VSG-20260120-318DC3', '2026-01-20 09:04:16'),
	(5, 11, 9, 'venda', -2, 321, 319, NULL, NULL, 'Venda automática - Pedido #VSG-20260120-0D5AEC', '2026-01-20 13:58:17');

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

-- Dumping data for table vsg.user_locations: ~6 rows (approximately)
INSERT INTO `user_locations` (`id`, `user_id`, `country`, `country_code`, `state`, `city`, `address`, `postal_code`, `latitude`, `longitude`, `ip_address`, `user_agent`, `is_primary`, `created_at`, `updated_at`) VALUES
	(1, 24, 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', NULL, NULL, -25.96550000, 32.58320000, NULL, NULL, 1, '2026-01-27 09:41:44', '2026-01-27 11:41:44'),
	(2, 25, 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', NULL, NULL, -25.96550000, 32.58320000, NULL, NULL, 1, '2026-01-27 15:20:24', '2026-01-27 17:20:24'),
	(3, 26, 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', NULL, NULL, -25.96550000, 32.58320000, NULL, NULL, 1, '2026-02-03 22:11:43', '2026-02-03 22:11:43'),
	(4, 27, 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', NULL, NULL, -25.96550000, 32.58320000, NULL, NULL, 1, '2026-02-03 22:25:09', '2026-02-03 22:25:09'),
	(5, 28, 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', NULL, NULL, -25.96550000, 32.58320000, NULL, NULL, 1, '2026-02-04 15:38:12', '2026-02-04 15:38:12'),
	(6, 29, 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', NULL, NULL, -25.96550000, 32.58320000, NULL, NULL, 1, '2026-02-04 15:47:42', '2026-02-04 15:47:42');

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

-- Dumping data for table vsg.users: ~25 rows (approximately)
INSERT INTO `users` (`id`, `public_id`, `type`, `role`, `nome`, `apelido`, `avatar`, `email`, `email_corporativo`, `telefone`, `country`, `country_code`, `state`, `city`, `address`, `postal_code`, `latitude`, `longitude`, `location_updated_at`, `password_hash`, `secure_id_hash`, `status`, `email_token`, `email_token_expires`, `email_verified_at`, `registration_step`, `last_activity`, `created_at`, `updated_at`, `deleted_at`, `password_changed_at`, `uid_generated_at`, `lock_until`, `two_fa_code`, `is_in_lockdown`) VALUES
	(1, '54209876P', 'person', 'user', 'Rest poll', 'Milione', NULL, 'getchange421@gmail.com', NULL, '+25885217000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$zngcEx..0bFA3CMf3SkvEesix54Y8pE1CqsYgd.ti4OdS/j8.JI.G', NULL, 'active', NULL, '2026-01-18 17:25:45', '2026-01-18 16:26:20', 'completed', 1769533195, '2026-01-18 16:25:45', '2026-01-27 16:59:55', NULL, '2026-01-18 16:25:45', '2026-01-18 16:26:21', NULL, NULL, 0),
	(2, '49034556C', 'company', 'user', 'Apollo', NULL, NULL, 'rosalioalexandre26@gmail.com', 'apollo@visiongreen.com', '+25885217020', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$7/xs82u9smySEkoG2zLtk.S4WUHbFQlN.ZHdaeT35KP06z3Gz.W.a', NULL, 'active', NULL, '2026-01-18 18:03:02', '2026-01-18 17:03:41', 'completed', 1769366761, '2026-01-18 17:03:02', '2026-01-25 18:46:01', NULL, '2026-01-18 17:03:02', '2026-01-18 17:03:43', NULL, NULL, 0),
	(3, '20699880C', 'company', 'user', 'Papel Solidario', NULL, NULL, 'vemros7@gmail.com', 'papel.solidario@visiongreen.com', '+7823923242423', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$Si6VKYdQK1tn4ek/l7mytOP13q3.eTXC5SescK616wY5UaJwzDVDq', NULL, 'active', NULL, '2026-01-20 15:28:18', '2026-01-20 14:28:40', 'completed', 1768921239, '2026-01-20 14:28:18', '2026-01-20 15:00:39', NULL, '2026-01-20 14:28:18', '2026-01-20 14:28:41', NULL, NULL, 0),
	(4, '78945612C', 'company', 'user', 'EcoVida Moçambique', NULL, NULL, 'contato@ecovida.co.mz', 'admin@ecovida.visiongreen.com', '+258843210001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash001', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(5, '65432178C', 'company', 'user', 'GreenTech Solutions', NULL, NULL, 'info@greentech.co.mz', 'admin@greentech.visiongreen.com', '+258843210002', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash002', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(6, '89012345C', 'company', 'user', 'BioRecicla', NULL, NULL, 'contato@biorecicla.co.mz', 'admin@biorecicla.visiongreen.com', '+258843210003', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash003', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(7, '23456789C', 'company', 'user', 'Natureza Pura', NULL, NULL, 'vendas@naturezapura.co.mz', 'admin@naturezapura.visiongreen.com', '+258843210004', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash004', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(8, '34567890C', 'company', 'user', 'SolarMoz', NULL, NULL, 'info@solarmoz.co.mz', 'admin@solarmoz.visiongreen.com', '+258843210005', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash005', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(9, '45678901C', 'company', 'user', 'AgroVerde', NULL, NULL, 'contato@agroverde.co.mz', 'admin@agroverde.visiongreen.com', '+258843210006', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash006', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(10, '56789012C', 'company', 'user', 'Bambu Forte', NULL, NULL, 'vendas@bambuforte.co.mz', 'admin@bambuforte.visiongreen.com', '+258843210007', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash007', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(11, '67890123C', 'company', 'user', 'Oceano Limpo', NULL, NULL, 'info@oceanolimpo.co.mz', 'admin@oceanolimpo.visiongreen.com', '+258843210008', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash008', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(12, '78901234C', 'company', 'user', 'Terra Fértil', NULL, NULL, 'contato@terrafertil.co.mz', 'admin@terrafertil.visiongreen.com', '+258843210009', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash009', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(13, '89012346C', 'company', 'user', 'Energia Verde', NULL, NULL, 'vendas@energiaverde.co.mz', 'admin@energiaverde.visiongreen.com', '+258843210010', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash010', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(14, '90123456C', 'company', 'user', 'Têxtil Sustentável', NULL, NULL, 'info@textilsustentavel.co.mz', 'admin@textilsustentavel.visiongreen.com', '+258843210011', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash011', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(15, '01234567C', 'company', 'user', 'BioCosméticos', NULL, NULL, 'contato@biocosmeticos.co.mz', 'admin@biocosmeticos.visiongreen.com', '+258843210012', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash012', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(16, '12345679C', 'company', 'user', 'Madeira Certificada', NULL, NULL, 'vendas@madeiracertificada.co.mz', 'admin@madeiracertificada.visiongreen.com', '+258843210013', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash013', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(17, '23456780C', 'company', 'user', 'Orgânicos Maputo', NULL, NULL, 'info@organicos.co.mz', 'admin@organicos.visiongreen.com', '+258843210014', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash014', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(18, '34567891C', 'company', 'user', 'Reciclagem Total', NULL, NULL, 'contato@reciclagemtotal.co.mz', 'admin@reciclagemtotal.visiongreen.com', '+258843210015', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash015', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(19, '45678902C', 'company', 'user', 'Água Pura', NULL, NULL, 'vendas@aguapura.co.mz', 'admin@aguapura.visiongreen.com', '+258843210016', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash016', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(20, '56789013C', 'company', 'user', 'EcoMóveis', NULL, NULL, 'info@ecomoveis.co.mz', 'admin@ecomoveis.visiongreen.com', '+258843210017', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash017', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(21, '67890124C', 'company', 'user', 'Verde Urbano', NULL, NULL, 'contato@verdeurbano.co.mz', 'admin@verdeurbano.visiongreen.com', '+258843210018', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash018', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(22, '78901235C', 'company', 'user', 'Compostagem Fácil', NULL, NULL, 'vendas@compostagemfacil.co.mz', 'admin@compostagemfacil.visiongreen.com', '+258843210019', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash019', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(23, '89012347C', 'company', 'user', 'Bio Embalagens', NULL, NULL, 'info@bioembalagens.co.mz', 'admin@bioembalagens.visiongreen.com', '+258843210020', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$randomhash020', NULL, 'active', NULL, NULL, '2026-01-26 09:39:20', 'completed', NULL, '2026-01-26 09:39:20', '2026-01-26 09:39:20', NULL, '2026-01-26 09:39:20', NULL, NULL, NULL, 0),
	(24, NULL, 'company', 'user', 'VEM ROS', NULL, NULL, 'vemroos7@gmail.com', NULL, '+258852170000', 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', '', '12499', -25.96550000, 32.58320000, '2026-01-27 11:41:44', '$2y$10$aDtC.Wd7PhenNDX9hiD8EuGT1y8pkiF9i3bBVS3qTnvP4dAZ9c35m', NULL, 'pending', '289808', '2026-01-27 12:41:44', NULL, 'email_pending', NULL, '2026-01-27 11:41:44', '2026-01-27 11:41:44', NULL, '2026-01-27 11:41:44', NULL, NULL, NULL, 0),
	(25, NULL, 'company', 'user', 'ASFojasf', NULL, NULL, 'getchange@gmail.com', NULL, '+25885217090', 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', '', '12499', -25.96550000, 32.58320000, '2026-01-27 17:20:24', '$2y$10$djeApr32vZYP6J2pgmMXzOJVojVJum5iIV.NXgpGIZlgQ4XdHkhGq', NULL, 'pending', '943496', '2026-01-27 18:20:24', NULL, 'email_pending', NULL, '2026-01-27 17:20:24', '2026-01-27 17:20:24', NULL, '2026-01-27 17:20:24', NULL, NULL, NULL, 0),
	(26, NULL, 'company', 'user', 'Apolla', NULL, NULL, 'grouplayout.gl@gmail.com', NULL, '+258852799218', 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', 'Rell', '', -25.96550000, 32.58320000, '2026-02-03 22:11:43', '$2y$10$I729ZwT.o5OGQ5.ypEpeX.coC8s5T.pZ8Ks4jexUPZIThgZ3bhhEy', NULL, 'pending', '260399', '2026-02-03 23:11:43', NULL, 'email_pending', NULL, '2026-02-03 22:11:43', '2026-02-03 22:11:43', NULL, '2026-02-03 22:11:43', NULL, NULL, NULL, 0),
	(27, NULL, 'company', 'user', 'sgshsh', NULL, NULL, 'gsdshssfsfs@gmail.com', NULL, '+258851610364', 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', 'dfsggdgs', '', -25.96550000, 32.58320000, '2026-02-03 22:25:09', '$2y$10$Gd7R9FhmMutRes2FeePm8eIi/ByA4/nlCQ4rYR6/PZ5UPVDARHfCm', NULL, 'pending', '223055', '2026-02-03 23:25:09', NULL, 'email_pending', NULL, '2026-02-03 22:25:09', '2026-02-03 22:25:09', NULL, '2026-02-03 22:25:09', NULL, NULL, NULL, 0),
	(28, NULL, 'company', 'user', 'dsghbsfhsrh', NULL, NULL, 'sgsedbgsz@gmail.com', NULL, '+258851610000', 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', 'fgrhrg', '', -25.96550000, 32.58320000, '2026-02-04 15:38:12', '$2y$10$cnt1LuJOw5Ilz6.gHdtYQ.V6t3nKDf9ktj1VmZ6FJY1k/JxqFoF3K', NULL, 'pending', '529134', '2026-02-04 16:38:12', NULL, 'email_pending', NULL, '2026-02-04 15:38:12', '2026-02-04 15:38:12', NULL, '2026-02-04 15:38:12', NULL, NULL, NULL, 0),
	(29, NULL, 'company', 'user', 'gfgzfffxgghfg@gmail.com', NULL, NULL, 'sasfsdg@gmail.com', NULL, '+2588512345', 'MZ', 'MZ', 'Cidade de Maputo', 'Maputo', 'fsgfhdg', '', -25.96550000, 32.58320000, '2026-02-04 15:47:42', '$2y$10$U6jABfgCbSJhrwba0CWW3uNYfFc59eljk5k5u5dts5EMaa0MbnRu.', NULL, 'pending', '071674', '2026-02-04 16:47:42', NULL, 'email_pending', NULL, '2026-02-04 15:47:42', '2026-02-04 15:47:42', NULL, '2026-02-04 15:47:42', NULL, NULL, NULL, 0);

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

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `audit_logs_stats`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `audit_logs_stats` AS select `al`.`user_id` AS `user_id`,`u`.`nome` AS `user_name`,cast(`al`.`created_at` as date) AS `log_date`,`al`.`action` AS `action`,count(0) AS `action_count` from (`audit_logs` `al` join `users` `u` on((`al`.`user_id` = `u`.`id`))) group by `al`.`user_id`,`u`.`nome`,cast(`al`.`created_at` as date),`al`.`action` order by `log_date` desc
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `employee_permissions_summary`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `employee_permissions_summary` AS select `e`.`id` AS `employee_id`,`e`.`nome` AS `employee_name`,`e`.`cargo` AS `employee_position`,`e`.`user_id` AS `company_id`,`u`.`nome` AS `company_name`,group_concat((case when (`ep`.`can_view` = 1) then `ep`.`module` end) separator ', ') AS `accessible_modules`,count((case when (`ep`.`can_view` = 1) then 1 end)) AS `total_accessible_modules`,`e`.`status` AS `status` from ((`employees` `e` join `users` `u` on((`e`.`user_id` = `u`.`id`))) left join `employee_permissions` `ep` on((`e`.`id` = `ep`.`employee_id`))) where (`e`.`is_active` = 1) group by `e`.`id`,`e`.`nome`,`e`.`cargo`,`e`.`user_id`,`u`.`nome`,`e`.`status`
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `view_active_carts_summary`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_active_carts_summary` AS select `sc`.`id` AS `cart_id`,`sc`.`user_id` AS `user_id`,`u`.`nome` AS `customer_name`,`u`.`email` AS `customer_email`,count(`ci`.`id`) AS `total_items`,sum(`ci`.`quantity`) AS `total_quantity`,sum((`ci`.`price` * `ci`.`quantity`)) AS `cart_total`,`sc`.`created_at` AS `created_at`,`sc`.`updated_at` AS `updated_at`,(to_days(now()) - to_days(`sc`.`updated_at`)) AS `days_since_update` from ((`shopping_carts` `sc` join `users` `u` on((`sc`.`user_id` = `u`.`id`))) left join `cart_items` `ci` on((`sc`.`id` = `ci`.`cart_id`))) where (`sc`.`status` = 'active') group by `sc`.`id`,`sc`.`user_id`,`u`.`nome`,`u`.`email`,`sc`.`created_at`,`sc`.`updated_at`
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `view_orders_complete`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_orders_complete` AS select `o`.`id` AS `id`,`o`.`company_id` AS `company_id`,`o`.`customer_id` AS `customer_id`,`o`.`order_number` AS `order_number`,`o`.`order_date` AS `order_date`,`o`.`delivery_date` AS `delivery_date`,`o`.`delivered_at` AS `delivered_at`,`o`.`subtotal` AS `subtotal`,`o`.`discount` AS `discount`,`o`.`tax` AS `tax`,`o`.`shipping_cost` AS `shipping_cost`,`o`.`total` AS `total`,`o`.`currency` AS `currency`,`o`.`status` AS `status`,`o`.`payment_status` AS `payment_status`,`o`.`payment_method` AS `payment_method`,`o`.`payment_date` AS `payment_date`,`o`.`confirmed_by_employee` AS `confirmed_by_employee`,`o`.`confirmed_at` AS `confirmed_at`,`o`.`shipping_address` AS `shipping_address`,`o`.`shipping_city` AS `shipping_city`,`o`.`shipping_phone` AS `shipping_phone`,`o`.`customer_notes` AS `customer_notes`,`o`.`internal_notes` AS `internal_notes`,`o`.`created_at` AS `created_at`,`o`.`updated_at` AS `updated_at`,`o`.`deleted_at` AS `deleted_at`,concat(`u`.`nome`,' ',coalesce(`u`.`apelido`,'')) AS `customer_full_name`,`u`.`email` AS `customer_email`,`u`.`telefone` AS `customer_phone`,(select count(0) from `order_items` where (`order_items`.`order_id` = `o`.`id`)) AS `items_count`,(select sum(`order_items`.`quantity`) from `order_items` where (`order_items`.`order_id` = `o`.`id`)) AS `total_items`,(case when ((`o`.`payment_method` = 'manual') and (`o`.`payment_status` = 'pendente')) then 1 else 0 end) AS `requires_manual_confirmation` from (`orders` `o` join `users` `u` on((`o`.`customer_id` = `u`.`id`))) where (`o`.`deleted_at` is null)
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `view_products_enhanced`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_products_enhanced` AS select `p`.`id` AS `id`,`p`.`user_id` AS `user_id`,`p`.`category_id` AS `category_id`,`p`.`nome` AS `nome`,`p`.`descricao` AS `descricao`,`p`.`imagem` AS `imagem`,`p`.`image_path1` AS `image_path1`,`p`.`preco` AS `preco`,`p`.`currency` AS `currency`,`p`.`stock` AS `stock`,`p`.`stock_minimo` AS `stock_minimo`,`p`.`status` AS `status`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`p`.`total_sales` AS `total_sales`,(to_days(now()) - to_days(`p`.`created_at`)) AS `days_old`,`c`.`name` AS `category_name`,`c`.`icon` AS `category_icon`,`u`.`nome` AS `company_name`,coalesce(avg(`cr`.`rating`),0) AS `avg_rating`,count(distinct `cr`.`id`) AS `review_count`,(case when ((to_days(now()) - to_days(`p`.`created_at`)) <= 7) then 1 else 0 end) AS `is_new`,(case when (`p`.`total_sales` >= 50) then 1 else 0 end) AS `is_popular`,(case when ((`p`.`stock` > 0) and (`p`.`stock` <= 10)) then 1 else 0 end) AS `is_low_stock` from (((`products` `p` left join `categories` `c` on((`p`.`category_id` = `c`.`id`))) left join `users` `u` on((`p`.`user_id` = `u`.`id`))) left join `customer_reviews` `cr` on((`p`.`id` = `cr`.`product_id`))) where (`p`.`deleted_at` is null) group by `p`.`id`
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `view_products_stats`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_products_stats` AS select `p`.`id` AS `id`,`p`.`user_id` AS `user_id`,`p`.`nome` AS `nome`,`p`.`descricao` AS `descricao`,`p`.`imagem` AS `imagem`,`p`.`categoria` AS `categoria`,`p`.`preco` AS `preco`,`p`.`currency` AS `currency`,`p`.`stock` AS `stock`,`p`.`stock_minimo` AS `stock_minimo`,`p`.`status` AS `status`,`p`.`visualizacoes` AS `visualizacoes`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`p`.`deleted_at` AS `deleted_at`,`u`.`nome` AS `company_name`,(select count(0) from `order_items` `oi` where (`oi`.`product_id` = `p`.`id`)) AS `total_sales`,(select sum(`oi`.`quantity`) from `order_items` `oi` where (`oi`.`product_id` = `p`.`id`)) AS `units_sold`,(select avg(`cr`.`rating`) from `customer_reviews` `cr` where (`cr`.`product_id` = `p`.`id`)) AS `avg_rating`,(select count(0) from `customer_reviews` `cr` where (`cr`.`product_id` = `p`.`id`)) AS `reviews_count`,(case when (`p`.`stock` <= `p`.`stock_minimo`) then 'baixo' when (`p`.`stock` = 0) then 'esgotado' else 'normal' end) AS `stock_status` from (`products` `p` join `users` `u` on((`p`.`user_id` = `u`.`id`))) where (`p`.`deleted_at` is null)
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `view_sales_complete`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_sales_complete` AS select `sr`.`id` AS `id`,`sr`.`order_id` AS `order_id`,`sr`.`order_item_id` AS `order_item_id`,`sr`.`company_id` AS `company_id`,`sr`.`customer_id` AS `customer_id`,`sr`.`product_id` AS `product_id`,`sr`.`sale_date` AS `sale_date`,`sr`.`quantity` AS `quantity`,`sr`.`unit_price` AS `unit_price`,`sr`.`discount` AS `discount`,`sr`.`total` AS `total`,`sr`.`currency` AS `currency`,`sr`.`order_status` AS `order_status`,`sr`.`payment_status` AS `payment_status`,`sr`.`payment_method` AS `payment_method`,`sr`.`product_name` AS `product_name`,`sr`.`product_category` AS `product_category`,`sr`.`customer_name` AS `customer_name`,`sr`.`created_at` AS `created_at`,`o`.`order_number` AS `order_number`,`o`.`shipping_city` AS `shipping_city`,`p`.`nome` AS `current_product_name`,`p`.`status` AS `product_status`,`u`.`email` AS `customer_email`,`u`.`telefone` AS `customer_phone`,`comp`.`nome` AS `company_name`,(select sum(`payments`.`amount`) from `payments` where ((`payments`.`order_id` = `sr`.`order_id`) and (`payments`.`payment_status` = 'confirmado'))) AS `total_paid` from ((((`sales_records` `sr` join `orders` `o` on((`sr`.`order_id` = `o`.`id`))) join `users` `u` on((`sr`.`customer_id` = `u`.`id`))) join `users` `comp` on((`sr`.`company_id` = `comp`.`id`))) left join `products` `p` on((`sr`.`product_id` = `p`.`id`))) where (`o`.`deleted_at` is null)
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `view_urgent_notifications`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_urgent_notifications` AS select `n`.`id` AS `id`,`n`.`sender_id` AS `sender_id`,`n`.`receiver_id` AS `receiver_id`,`n`.`reply_to` AS `reply_to`,`n`.`category` AS `category`,`n`.`priority` AS `priority`,`n`.`subject` AS `subject`,`n`.`message` AS `message`,`n`.`related_order_id` AS `related_order_id`,`n`.`related_product_id` AS `related_product_id`,`n`.`status` AS `status`,`n`.`created_at` AS `created_at`,`u`.`nome` AS `receiver_name`,`u`.`email` AS `receiver_email`,`o`.`order_number` AS `order_number`,`p`.`nome` AS `product_name` from (((`notifications` `n` join `users` `u` on((`n`.`receiver_id` = `u`.`id`))) left join `orders` `o` on((`n`.`related_order_id` = `o`.`id`))) left join `products` `p` on((`n`.`related_product_id` = `p`.`id`))) where ((`n`.`status` = 'nao_lida') and (`n`.`priority` in ('alta','critica'))) order by `n`.`created_at` desc
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `view_users_with_location`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `view_users_with_location` AS select `u`.`id` AS `id`,`u`.`public_id` AS `public_id`,`u`.`type` AS `type`,`u`.`nome` AS `nome`,`u`.`apelido` AS `apelido`,`u`.`email` AS `email`,`u`.`telefone` AS `telefone`,`u`.`avatar` AS `avatar`,`u`.`status` AS `status`,`u`.`created_at` AS `created_at`,`ul`.`country` AS `country`,`ul`.`country_code` AS `country_code`,`ul`.`state` AS `state`,`ul`.`city` AS `city`,`ul`.`latitude` AS `latitude`,`ul`.`longitude` AS `longitude`,`ul`.`updated_at` AS `location_updated_at` from (`users` `u` left join `user_locations` `ul` on(((`u`.`id` = `ul`.`user_id`) and (`ul`.`is_primary` = 1)))) where (`u`.`deleted_at` is null)
;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
