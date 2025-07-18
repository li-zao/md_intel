SET NAMES utf8;
CREATE DATABASE IF NOT EXISTS `md_intel` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `md_intel`;
CREATE TABLE IF NOT EXISTS `desc` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `r_id` int(11) NOT NULL COMMENT '记录ID',
  `type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '类型',
  `content` text NOT NULL COMMENT '日志详情',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `mId` (`r_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `dictionary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` char(255) NOT NULL DEFAULT '' COMMENT '类型',
  `key` varchar(255) NOT NULL DEFAULT '' COMMENT '键',
  `value` varchar(255) NOT NULL DEFAULT '' COMMENT '值',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态：1正常，0删除',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  PRIMARY KEY (`id`),
  KEY `idx_dict_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


CREATE TABLE IF NOT EXISTS `dictionary_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` char(255) NOT NULL DEFAULT '' COMMENT '类型',
  `name` char(255) NOT NULL DEFAULT '' COMMENT '名称',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


CREATE TABLE IF NOT EXISTS `file` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `path` varchar(1024) NOT NULL DEFAULT '' COMMENT '路径',
  `name` varchar(256) NOT NULL DEFAULT '' COMMENT '名称',
  `hash` varchar(32) NOT NULL DEFAULT '' COMMENT 'MD5',
  `source` tinyint(4) NOT NULL DEFAULT 0 COMMENT '来源',
  `category` tinyint(4) NOT NULL DEFAULT 0 COMMENT '分类',
  `type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '类型',
  `is_del` tinyint(4) NOT NULL DEFAULT 0 COMMENT '删除',
  `is_rename` tinyint(4) NOT NULL DEFAULT 0 COMMENT '是否重命名',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_file_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `attempts` tinyint(4) unsigned DEFAULT NULL,
  `reserve_time` int(11) unsigned DEFAULT NULL,
  `available_time` int(11) unsigned DEFAULT NULL,
  `create_time` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `migrations` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `url` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `url` varchar(1024) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '' COMMENT 'URL',
  `domain` varchar(256) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '' COMMENT 'Domain',
  `hash` varchar(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '' COMMENT 'MD5',
  `source` tinyint(4) NOT NULL DEFAULT 0 COMMENT '来源',
  `category` tinyint(4) NOT NULL DEFAULT 0 COMMENT '分类',
  `type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '类型',
  `is_del` tinyint(4) NOT NULL DEFAULT 0 COMMENT '删除',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_url_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `url_http_cache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL DEFAULT '',
  `url_hash` varchar(255) NOT NULL DEFAULT '',
  `url_code` int(11) NOT NULL DEFAULT 0,
  `url_http` longtext DEFAULT NULL,
  `url_whois` text DEFAULT NULL,
  `url_tls` text DEFAULT NULL,
  `url_date` varchar(10) DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_http_cache_url` (`url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `url_vector` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL DEFAULT '',
  `url_hash` varchar(255) NOT NULL DEFAULT '',
  `url_vector` text DEFAULT NULL,
  `url_date` varchar(10) DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_url_vector_url` (`url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(255) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `role` varchar(255) NOT NULL DEFAULT 'guest' COMMENT '账户权限，默认guest，admin为特殊用户',
  `is_del` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否删除',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
