-- =====================================================================
--  JAN KAVACH
--  Social Security Scheme Information, Beneficiary Assessment
--  & Field Data Management Platform
--
--  DATABASE SCHEMA  (MySQL 5.7+ / MariaDB 10.3+)
--  Engine: InnoDB   Charset: utf8mb4   Collation: utf8mb4_unicode_ci
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `jan_kavach`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jan_kavach`;

-- =====================================================================
-- SECTION 1 : ROLE BASED ACCESS CONTROL
-- =====================================================================

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(50)  NOT NULL COMMENT 'machine key e.g. SUPER_ADMIN',
  `name`          VARCHAR(100) NOT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  -- data_scope drives automatic geographic row filtering
  `data_scope`    ENUM('all','state','district','own') NOT NULL DEFAULT 'district',
  `requires_state`    TINYINT(1) NOT NULL DEFAULT 0,
  `requires_district` TINYINT(1) NOT NULL DEFAULT 0,
  `is_system`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = cannot be deleted',
  `sort_order`    SMALLINT NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`),
  KEY `idx_roles_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(80)  NOT NULL COMMENT 'module.action e.g. scheme.create',
  `name`        VARCHAR(120) NOT NULL,
  `module`      VARCHAR(60)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_code` (`code`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 2 : GEOGRAPHIC HIERARCHY
--  Country -> State -> District -> Block -> Gram Panchayat -> Village
-- =====================================================================

DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`      VARCHAR(5)   NOT NULL,
  `name`      VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_countries_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `states`;
CREATE TABLE `states` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id`   INT UNSIGNED NOT NULL DEFAULT 1,
  `state_code`   VARCHAR(10)  NOT NULL COMMENT 'short code e.g. WB, UP',
  `lgd_code`     VARCHAR(10)  DEFAULT NULL COMMENT 'Local Govt Directory code',
  `name`         VARCHAR(120) NOT NULL,
  `type`         ENUM('State','UT') NOT NULL DEFAULT 'State',
  `region`       VARCHAR(40)  DEFAULT NULL COMMENT 'North/South/East/West/Central/North-East',
  `is_programme_state` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = programme operational',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_states_code` (`state_code`),
  UNIQUE KEY `uq_states_name` (`name`),
  KEY `idx_states_active` (`is_active`),
  CONSTRAINT `fk_states_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `districts`;
CREATE TABLE `districts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `state_id`      INT UNSIGNED NOT NULL,
  `district_code` VARCHAR(15)  DEFAULT NULL,
  `lgd_code`      VARCHAR(10)  DEFAULT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_districts_state_name` (`state_id`,`name`),
  KEY `idx_districts_state` (`state_id`),
  KEY `idx_districts_active` (`is_active`),
  CONSTRAINT `fk_districts_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `blocks`;
CREATE TABLE `blocks` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `district_id` INT UNSIGNED NOT NULL,
  `code`        VARCHAR(15)  DEFAULT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `block_type`  ENUM('Rural','Urban','Mixed') NOT NULL DEFAULT 'Rural',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocks_district_name` (`district_id`,`name`),
  KEY `idx_blocks_district` (`district_id`),
  CONSTRAINT `fk_blocks_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gram_panchayats`;
CREATE TABLE `gram_panchayats` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `block_id`   INT UNSIGNED NOT NULL,
  `code`       VARCHAR(15)  DEFAULT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gp_block_name` (`block_id`,`name`),
  KEY `idx_gp_block` (`block_id`),
  CONSTRAINT `fk_gp_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `villages`;
CREATE TABLE `villages` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gram_panchayat_id`  INT UNSIGNED DEFAULT NULL,
  `block_id`           INT UNSIGNED NOT NULL,
  `code`               VARCHAR(15)  DEFAULT NULL,
  `name`               VARCHAR(150) NOT NULL,
  `area_type`          ENUM('Rural','Urban') NOT NULL DEFAULT 'Rural',
  `pincode`            VARCHAR(6)   DEFAULT NULL,
  `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_villages_gp` (`gram_panchayat_id`),
  KEY `idx_villages_block` (`block_id`),
  KEY `idx_villages_name` (`name`),
  CONSTRAINT `fk_villages_gp`    FOREIGN KEY (`gram_panchayat_id`) REFERENCES `gram_panchayats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_villages_block` FOREIGN KEY (`block_id`)          REFERENCES `blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 3 : USERS / AUTHENTICATION
-- =====================================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_code`       VARCHAR(20)  NOT NULL COMMENT 'human readable e.g. JK-USR-00001',
  `full_name`       VARCHAR(150) NOT NULL,
  `username`        VARCHAR(60)  NOT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `mobile`          VARCHAR(15)  DEFAULT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `role_id`         INT UNSIGNED NOT NULL,
  `state_id`        INT UNSIGNED DEFAULT NULL,
  `district_id`     INT UNSIGNED DEFAULT NULL,
  `block_id`        INT UNSIGNED DEFAULT NULL,
  `designation`     VARCHAR(120) DEFAULT NULL,
  `organization`    VARCHAR(150) DEFAULT NULL,
  `photo_path`      VARCHAR(255) DEFAULT NULL,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    DATETIME     DEFAULT NULL,
  `last_login_at`   DATETIME     DEFAULT NULL,
  `last_login_ip`   VARCHAR(45)  DEFAULT NULL,
  `remember_token`  VARCHAR(255) DEFAULT NULL,
  `remember_expires` DATETIME    DEFAULT NULL,
  `created_by`      INT UNSIGNED DEFAULT NULL,
  `updated_by`      INT UNSIGNED DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_code` (`user_code`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_state` (`state_id`),
  KEY `idx_users_district` (`district_id`),
  KEY `idx_users_active` (`is_active`,`deleted_at`),
  CONSTRAINT `fk_users_role`     FOREIGN KEY (`role_id`)     REFERENCES `roles` (`id`),
  CONSTRAINT `fk_users_state`    FOREIGN KEY (`state_id`)    REFERENCES `states` (`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_users_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_block`    FOREIGN KEY (`block_id`)    REFERENCES `blocks` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(150) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `user_agent`   VARCHAR(255) DEFAULT NULL,
  `was_success`  TINYINT(1)   NOT NULL DEFAULT 0,
  `reason`       VARCHAR(100) DEFAULT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_username_time` (`username`,`attempted_at`),
  KEY `idx_la_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64)     NOT NULL COMMENT 'sha256 of emailed token',
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used_at`    DATETIME     DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_token` (`token_hash`),
  KEY `idx_pr_user` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 4 : DOMAIN MASTERS
-- =====================================================================

DROP TABLE IF EXISTS `scheme_themes`;
CREATE TABLE `scheme_themes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `color`       VARCHAR(9)   DEFAULT '#0d6efd',
  `icon`        VARCHAR(50)  DEFAULT 'bi-grid',
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_themes_code` (`code`),
  UNIQUE KEY `uq_themes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `beneficiary_types`;
CREATE TABLE `beneficiary_types` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `icon`        VARCHAR(50)  DEFAULT 'bi-people',
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bt_code` (`code`),
  UNIQUE KEY `uq_bt_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `target_groups`;
CREATE TABLE `target_groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(60)  NOT NULL,
  `name`        VARCHAR(200) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tg_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `service_approaches`;
CREATE TABLE `service_approaches` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `color`       VARCHAR(9)   DEFAULT '#0dcaf0',
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_code` (`code`),
  UNIQUE KEY `uq_sa_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `priority_levels`;
CREATE TABLE `priority_levels` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rank_value`  TINYINT UNSIGNED NOT NULL COMMENT '1 = highest priority',
  `code`        VARCHAR(30)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `color`       VARCHAR(9)   DEFAULT '#dc3545',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pl_rank` (`rank_value`),
  UNIQUE KEY `uq_pl_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `feasibility_levels`;
CREATE TABLE `feasibility_levels` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rank_value`  TINYINT UNSIGNED NOT NULL COMMENT '1 = difficult ... 5 = easy',
  `name`        VARCHAR(80)  NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `color`       VARCHAR(9)   DEFAULT '#198754',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fl_rank` (`rank_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_types`;
CREATE TABLE `document_types` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`              VARCHAR(40)  NOT NULL,
  `name`              VARCHAR(150) NOT NULL,
  `description`       VARCHAR(500) DEFAULT NULL,
  `category`          ENUM('Identity','Address','Income','Bank','Health','Education','Land','Category','Certificate','Photo','Other')
                      NOT NULL DEFAULT 'Other',
  `requires_number`   TINYINT(1) NOT NULL DEFAULT 1,
  `validity_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = issue/expiry dates matter',
  `is_sensitive`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = mask number in listings',
  `sort_order`        SMALLINT NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dt_code` (`code`),
  UNIQUE KEY `uq_dt_name` (`name`),
  KEY `idx_dt_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `application_statuses`;
CREATE TABLE `application_statuses` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(40)  NOT NULL,
  `name`         VARCHAR(100) NOT NULL,
  `stage_order`  SMALLINT NOT NULL DEFAULT 0 COMMENT 'pipeline position',
  `funnel_stage` ENUM('Identified','Eligible','Initiated','Submitted','Approved','Benefit','Closed','Rejected')
                 NOT NULL DEFAULT 'Identified' COMMENT 'roll-up bucket for the funnel chart',
  `color`        VARCHAR(9)  DEFAULT '#6c757d',
  `is_terminal`  TINYINT(1)  NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_as_code` (`code`),
  KEY `idx_as_order` (`stage_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic lookup table: lets new dropdowns be added with zero schema change.
DROP TABLE IF EXISTS `lookup_options`;
CREATE TABLE `lookup_options` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lookup_key`  VARCHAR(60)  NOT NULL COMMENT 'e.g. social_category, occupation',
  `code`        VARCHAR(60)  NOT NULL,
  `label`       VARCHAR(200) NOT NULL,
  `numeric_value` DECIMAL(14,2) DEFAULT NULL COMMENT 'ordinal value for comparisons',
  `extra`       TEXT DEFAULT NULL,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lo_key_code` (`lookup_key`,`code`),
  KEY `idx_lo_key` (`lookup_key`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `implementing_agencies`;
CREATE TABLE `implementing_agencies` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(200) NOT NULL,
  `agency_type` ENUM('Ministry','Department','Board','Corporation','Mission','Other') NOT NULL DEFAULT 'Department',
  `level`      ENUM('National','State') NOT NULL DEFAULT 'National',
  `state_id`   INT UNSIGNED DEFAULT NULL,
  `website`    VARCHAR(255) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ia_name_state` (`name`,`state_id`),
  KEY `idx_ia_state` (`state_id`),
  CONSTRAINT `fk_ia_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 5 : SCHEME MASTER
-- =====================================================================

DROP TABLE IF EXISTS `schemes`;
CREATE TABLE `schemes` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scheme_code`        VARCHAR(30)  NOT NULL COMMENT 'e.g. JK-SCH-0001',
  `name`               VARCHAR(300) NOT NULL,
  `short_name`         VARCHAR(80)  DEFAULT NULL COMMENT 'acronym e.g. PMMVY',
  `slug`               VARCHAR(320) NOT NULL,
  `description`        TEXT         DEFAULT NULL,
  `scheme_level`       ENUM('National','State','National+State') NOT NULL DEFAULT 'National',
  `state_id`           INT UNSIGNED DEFAULT NULL COMMENT 'primary state for State schemes',
  `theme_id`           INT UNSIGNED DEFAULT NULL COMMENT 'primary theme (denormalised for listing/sort)',
  `service_approach_id` INT UNSIGNED DEFAULT NULL,
  `priority_id`        INT UNSIGNED DEFAULT NULL,
  `feasibility_id`     INT UNSIGNED DEFAULT NULL,
  `ministry_id`        INT UNSIGNED DEFAULT NULL,
  `department_id`      INT UNSIGNED DEFAULT NULL,
  `ministry_name`      VARCHAR(200) DEFAULT NULL COMMENT 'free text fallback',
  `department_name`    VARCHAR(200) DEFAULT NULL,
  `scheme_status`      ENUM('Active','Dormant','Discontinued') NOT NULL DEFAULT 'Active',
  `active_enrollment`  TINYINT(1)   NOT NULL DEFAULT 1,
  `launch_year`        SMALLINT     DEFAULT NULL,
  -- classification summaries straight from the source workbook
  `target_group_summary` TEXT       DEFAULT NULL,
  `eligibility_summary`  TEXT       DEFAULT NULL,
  -- benefits
  `benefit_type`        VARCHAR(120) DEFAULT NULL,
  `benefit_description` TEXT         DEFAULT NULL,
  `benefit_amount`      DECIMAL(14,2) DEFAULT NULL,
  `benefit_amount_text` VARCHAR(255) DEFAULT NULL COMMENT 'when amount is a range/slab',
  `benefit_mode`        ENUM('One-time','Recurring','Both','In-kind','Service') DEFAULT NULL,
  `benefit_frequency`   VARCHAR(80)  DEFAULT NULL,
  `benefit_conditions`  TEXT         DEFAULT NULL,
  -- how to apply
  `application_mode`     ENUM('Online','Offline','Both','Not Applicable') DEFAULT 'Offline',
  `application_process`  TEXT DEFAULT NULL,
  `application_instructions` TEXT DEFAULT NULL,
  `portal_url`           VARCHAR(500) DEFAULT NULL,
  `csc_available`        TINYINT(1) NOT NULL DEFAULT 0,
  `physical_office`      VARCHAR(255) DEFAULT NULL,
  `helpline_number`      VARCHAR(80)  DEFAULT NULL,
  -- source / verification
  `source_url`           VARCHAR(500) DEFAULT NULL,
  `source_website`       VARCHAR(200) DEFAULT NULL,
  `last_verified_date`   DATE DEFAULT NULL,
  `verified_by`          VARCHAR(150) DEFAULT NULL,
  -- housekeeping
  `keywords`             VARCHAR(500) DEFAULT NULL COMMENT 'extra search terms',
  `view_count`           INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`            TINYINT(1) NOT NULL DEFAULT 1,
  `source_row_no`        INT DEFAULT NULL COMMENT 'SL.No in source workbook',
  `created_by`           INT UNSIGNED DEFAULT NULL,
  `updated_by`           INT UNSIGNED DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`           DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schemes_code` (`scheme_code`),
  KEY `idx_schemes_slug` (`slug`),
  KEY `idx_schemes_level` (`scheme_level`),
  KEY `idx_schemes_state` (`state_id`),
  KEY `idx_schemes_theme` (`theme_id`),
  KEY `idx_schemes_status` (`scheme_status`,`is_active`),
  KEY `idx_schemes_priority` (`priority_id`),
  KEY `idx_schemes_deleted` (`deleted_at`),
  FULLTEXT KEY `ft_schemes_search` (`name`,`short_name`,`description`,`keywords`,`target_group_summary`),
  CONSTRAINT `fk_schemes_state`    FOREIGN KEY (`state_id`)            REFERENCES `states` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schemes_theme`    FOREIGN KEY (`theme_id`)            REFERENCES `scheme_themes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schemes_sa`       FOREIGN KEY (`service_approach_id`) REFERENCES `service_approaches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schemes_priority` FOREIGN KEY (`priority_id`)         REFERENCES `priority_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schemes_feas`     FOREIGN KEY (`feasibility_id`)      REFERENCES `feasibility_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schemes_ministry` FOREIGN KEY (`ministry_id`)         REFERENCES `implementing_agencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schemes_dept`     FOREIGN KEY (`department_id`)       REFERENCES `implementing_agencies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- a scheme may carry several themes ("Health and Education")
DROP TABLE IF EXISTS `scheme_theme_map`;
CREATE TABLE `scheme_theme_map` (
  `scheme_id` INT UNSIGNED NOT NULL,
  `theme_id`  INT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`scheme_id`,`theme_id`),
  KEY `idx_stm_theme` (`theme_id`),
  CONSTRAINT `fk_stm_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stm_theme`  FOREIGN KEY (`theme_id`)  REFERENCES `scheme_themes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scheme_beneficiary_types`;
CREATE TABLE `scheme_beneficiary_types` (
  `scheme_id`           INT UNSIGNED NOT NULL,
  `beneficiary_type_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`scheme_id`,`beneficiary_type_id`),
  KEY `idx_sbt_type` (`beneficiary_type_id`),
  CONSTRAINT `fk_sbt_scheme` FOREIGN KEY (`scheme_id`)           REFERENCES `schemes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sbt_type`   FOREIGN KEY (`beneficiary_type_id`) REFERENCES `beneficiary_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scheme_target_groups`;
CREATE TABLE `scheme_target_groups` (
  `scheme_id`       INT UNSIGNED NOT NULL,
  `target_group_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`scheme_id`,`target_group_id`),
  KEY `idx_stg_tg` (`target_group_id`),
  CONSTRAINT `fk_stg_scheme` FOREIGN KEY (`scheme_id`)       REFERENCES `schemes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stg_tg`     FOREIGN KEY (`target_group_id`) REFERENCES `target_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- states in which the scheme applies (handles "National + Andhra Pradesh")
DROP TABLE IF EXISTS `scheme_states`;
CREATE TABLE `scheme_states` (
  `scheme_id` INT UNSIGNED NOT NULL,
  `state_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`scheme_id`,`state_id`),
  KEY `idx_ss_state` (`state_id`),
  CONSTRAINT `fk_ss_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ss_state`  FOREIGN KEY (`state_id`)  REFERENCES `states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scheme_benefits`;
CREATE TABLE `scheme_benefits` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scheme_id`   INT UNSIGNED NOT NULL,
  `benefit_type` VARCHAR(120) DEFAULT NULL COMMENT 'Cash / In-kind / Insurance / Service / Subsidy',
  `description`  TEXT DEFAULT NULL,
  `amount`       DECIMAL(14,2) DEFAULT NULL,
  `amount_text`  VARCHAR(255) DEFAULT NULL,
  `mode`         ENUM('One-time','Recurring','Both','In-kind','Service') DEFAULT NULL,
  `frequency`    VARCHAR(80) DEFAULT NULL,
  `conditions`   TEXT DEFAULT NULL,
  `sort_order`   SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sb_scheme` (`scheme_id`),
  CONSTRAINT `fk_sb_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- documents a scheme requires (Section 10 of the specification)
DROP TABLE IF EXISTS `scheme_documents`;
CREATE TABLE `scheme_documents` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scheme_id`        INT UNSIGNED NOT NULL,
  `document_type_id` INT UNSIGNED NOT NULL,
  `is_mandatory`     TINYINT(1) NOT NULL DEFAULT 1,
  `validity_required` TINYINT(1) NOT NULL DEFAULT 0,
  `remarks`          VARCHAR(500) DEFAULT NULL,
  `sort_order`       SMALLINT NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sd_scheme_doc` (`scheme_id`,`document_type_id`),
  KEY `idx_sd_doc` (`document_type_id`),
  CONSTRAINT `fk_sd_scheme` FOREIGN KEY (`scheme_id`)        REFERENCES `schemes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sd_doc`    FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 6 : CONFIGURABLE ELIGIBILITY ENGINE
--  eligibility_attributes   = the vocabulary the rule builder offers
--  scheme_eligibility_rules = the rules; rules AND inside a group, groups OR
-- =====================================================================

DROP TABLE IF EXISTS `eligibility_attributes`;
CREATE TABLE `eligibility_attributes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_key` VARCHAR(60)  NOT NULL COMMENT 'e.g. age, annual_income, bpl_status',
  `label`         VARCHAR(150) NOT NULL,
  `data_type`     ENUM('int','decimal','bool','string','enum','set','date') NOT NULL DEFAULT 'string',
  `lookup_key`    VARCHAR(60)  DEFAULT NULL COMMENT 'lookup_options.lookup_key for enum types',
  `source`        ENUM('beneficiary','household','assessment','derived','geo') NOT NULL DEFAULT 'assessment',
  `source_column` VARCHAR(60)  DEFAULT NULL COMMENT 'column name when source is a real table',
  `unit`          VARCHAR(30)  DEFAULT NULL,
  `group_label`   VARCHAR(60)  DEFAULT 'General',
  `description`   VARCHAR(500) DEFAULT NULL,
  `allowed_operators` VARCHAR(255) DEFAULT NULL COMMENT 'CSV restriction; NULL = all',
  `sort_order`    SMALLINT NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ea_key` (`attribute_key`),
  KEY `idx_ea_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scheme_eligibility_rules`;
CREATE TABLE `scheme_eligibility_rules` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scheme_id`     INT UNSIGNED NOT NULL,
  `rule_group`    TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'rules in the same group are ANDed; groups are ORed',
  `attribute_key` VARCHAR(60)  NOT NULL,
  `operator`      ENUM('eq','neq','gt','lt','gte','lte','between','contains','any_of','not_in','is_set','not_set')
                  NOT NULL DEFAULT 'eq',
  `value1`        VARCHAR(255) DEFAULT NULL,
  `value2`        VARCHAR(255) DEFAULT NULL COMMENT 'upper bound for BETWEEN',
  `value_list`    TEXT         DEFAULT NULL COMMENT 'CSV for any_of / not_in',
  `weight`        TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'contribution to match %',
  `is_mandatory`  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = failure disqualifies outright',
  `description`   VARCHAR(500) DEFAULT NULL COMMENT 'human explanation used in match reasons',
  `sort_order`    SMALLINT NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ser_scheme` (`scheme_id`,`is_active`),
  KEY `idx_ser_attr` (`attribute_key`),
  CONSTRAINT `fk_ser_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 7 : BENEFICIARY / FIELD DATA
-- =====================================================================

DROP TABLE IF EXISTS `beneficiaries`;
CREATE TABLE `beneficiaries` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_code`  VARCHAR(25)  NOT NULL COMMENT 'auto e.g. JK-WB-2026-000123',
  `registration_date` DATE NOT NULL,
  `name`              VARCHAR(150) NOT NULL,
  `relation_type`     ENUM('Father','Husband','Mother','Guardian','Other') DEFAULT 'Father',
  `relation_name`     VARCHAR(150) DEFAULT NULL,
  -- The questionnaire asks for both parents and the spouse by name, which the
  -- single relation_type / relation_name pair could not hold.
  `father_name`       VARCHAR(150) DEFAULT NULL,
  `mother_name`       VARCHAR(150) DEFAULT NULL,
  `spouse_name`       VARCHAR(150) DEFAULT NULL,
  `gender`            ENUM('Male','Female','Other') NOT NULL,
  `dob`               DATE DEFAULT NULL,
  `age`               TINYINT UNSIGNED DEFAULT NULL,
  `mobile`            VARCHAR(15) DEFAULT NULL,
  `alt_mobile`        VARCHAR(15) DEFAULT NULL,
  -- Aadhaar is never stored in the clear: last 4 digits for field reference,
  -- peppered SHA-256 only for duplicate detection.
  `aadhaar_last4`     CHAR(4) DEFAULT NULL,
  `aadhaar_hash`      CHAR(64) DEFAULT NULL,
  `has_aadhaar`       TINYINT(1) NOT NULL DEFAULT 0,
  `state_id`          INT UNSIGNED NOT NULL,
  `district_id`       INT UNSIGNED NOT NULL,
  `block_id`          INT UNSIGNED DEFAULT NULL,
  `gram_panchayat_id` INT UNSIGNED DEFAULT NULL,
  `village_id`        INT UNSIGNED DEFAULT NULL,
  `village_name`      VARCHAR(150) DEFAULT NULL COMMENT 'free text when not in master',
  `post_office`       VARCHAR(120) DEFAULT NULL,
  `address_line`      VARCHAR(255) DEFAULT NULL,
  `pincode`           VARCHAR(6) DEFAULT NULL,
  `area_type`         ENUM('Rural','Urban') NOT NULL DEFAULT 'Rural',
  `photo_path`        VARCHAR(255) DEFAULT NULL,
  `record_status`     ENUM('Registered','Assessed','Matched','In Process','Benefited','Closed','Rejected')
                      NOT NULL DEFAULT 'Registered',
  `assessment_status` ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  `last_assessed_at`  DATETIME DEFAULT NULL,
  `last_matched_at`   DATETIME DEFAULT NULL,
  `consent_given`     TINYINT(1) NOT NULL DEFAULT 0,
  `consent_date`      DATE DEFAULT NULL,
  `field_user_id`     INT UNSIGNED DEFAULT NULL COMMENT 'field worker who collected the record',
  `remarks`           TEXT DEFAULT NULL,
  `created_by`        INT UNSIGNED DEFAULT NULL,
  `updated_by`        INT UNSIGNED DEFAULT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ben_code` (`beneficiary_code`),
  KEY `idx_ben_aadhaar_hash` (`aadhaar_hash`),
  KEY `idx_ben_state_district` (`state_id`,`district_id`),
  KEY `idx_ben_district` (`district_id`),
  KEY `idx_ben_village` (`village_id`),
  KEY `idx_ben_field_user` (`field_user_id`),
  KEY `idx_ben_status` (`record_status`),
  KEY `idx_ben_regdate` (`registration_date`),
  KEY `idx_ben_mobile` (`mobile`),
  KEY `idx_ben_deleted` (`deleted_at`),
  KEY `idx_ben_name` (`name`),
  CONSTRAINT `fk_ben_state`    FOREIGN KEY (`state_id`)          REFERENCES `states` (`id`),
  CONSTRAINT `fk_ben_district` FOREIGN KEY (`district_id`)       REFERENCES `districts` (`id`),
  CONSTRAINT `fk_ben_block`    FOREIGN KEY (`block_id`)          REFERENCES `blocks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ben_gp`       FOREIGN KEY (`gram_panchayat_id`) REFERENCES `gram_panchayats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ben_village`  FOREIGN KEY (`village_id`)        REFERENCES `villages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ben_fielduser` FOREIGN KEY (`field_user_id`)    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `beneficiary_households`;
CREATE TABLE `beneficiary_households` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id`      INT UNSIGNED NOT NULL,
  `household_head_name` VARCHAR(150) DEFAULT NULL,
  `family_size`         TINYINT UNSIGNED DEFAULT NULL,
  `adults_count`        TINYINT UNSIGNED DEFAULT NULL,
  `children_count`      TINYINT UNSIGNED DEFAULT NULL,
  `earning_members`     TINYINT UNSIGNED DEFAULT NULL,
  `annual_income`       DECIMAL(12,2) DEFAULT NULL,
  `income_source`       VARCHAR(120) DEFAULT NULL,
  `bpl_status`          ENUM('Yes','No','Unknown') NOT NULL DEFAULT 'Unknown',
  `ration_card_type`    VARCHAR(40) DEFAULT NULL COMMENT 'AAY / PHH / APL / None',
  `ration_card_last4`   VARCHAR(4) DEFAULT NULL,
  `house_type`          VARCHAR(40) DEFAULT NULL COMMENT 'Kutcha / Semi-Pucca / Pucca / Homeless',
  `has_electricity`     TINYINT(1) DEFAULT NULL,
  `has_toilet`          TINYINT(1) DEFAULT NULL,
  `drinking_water_source` VARCHAR(80) DEFAULT NULL,
  `land_holding_acres`  DECIMAL(8,3) DEFAULT NULL,
  `livestock_count`     SMALLINT UNSIGNED DEFAULT NULL,
  `remarks`             TEXT DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bh_beneficiary` (`beneficiary_id`),
  CONSTRAINT `fk_bh_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `beneficiary_members`;
CREATE TABLE `beneficiary_members` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id` INT UNSIGNED NOT NULL,
  `name`           VARCHAR(150) NOT NULL,
  `relation`       VARCHAR(60) DEFAULT NULL,
  `gender`         ENUM('Male','Female','Other') DEFAULT NULL,
  `dob`            DATE DEFAULT NULL,
  `age`            TINYINT UNSIGNED DEFAULT NULL,
  `education_level` VARCHAR(60) DEFAULT NULL,
  `occupation`     VARCHAR(80) DEFAULT NULL,
  `is_disabled`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_student`     TINYINT(1) NOT NULL DEFAULT 0,
  `remarks`        VARCHAR(255) DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bm_beneficiary` (`beneficiary_id`),
  CONSTRAINT `fk_bm_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dynamic assessment questionnaire: new questions need no schema change.
DROP TABLE IF EXISTS `assessment_questions`;
CREATE TABLE `assessment_questions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section`       VARCHAR(80)  NOT NULL DEFAULT 'Socio-Economic',
  `question_key`  VARCHAR(60)  NOT NULL COMMENT 'matches eligibility_attributes.attribute_key where relevant',
  `label`         VARCHAR(255) NOT NULL,
  `help_text`     VARCHAR(500) DEFAULT NULL,
  `input_type`    ENUM('text','number','decimal','select','multiselect','radio','checkbox','date','textarea')
                  NOT NULL DEFAULT 'text',
  `lookup_key`    VARCHAR(60)  DEFAULT NULL,
  -- A question that only opens when another holds one of these values.
  `depends_on_key`   VARCHAR(60)  DEFAULT NULL,
  `depends_on_value` VARCHAR(200) DEFAULT NULL,
  `unit`          VARCHAR(30)  DEFAULT NULL,
  `min_value`     DECIMAL(14,2) DEFAULT NULL,
  `max_value`     DECIMAL(14,2) DEFAULT NULL,
  `is_required`   TINYINT(1) NOT NULL DEFAULT 0,
  `feeds_eligibility` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = answer is published to the matching engine',
  `sort_order`    SMALLINT NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aq_key` (`question_key`),
  KEY `idx_aq_section` (`section`,`sort_order`),
  KEY `idx_aq_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `beneficiary_assessments`;
CREATE TABLE `beneficiary_assessments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id`  INT UNSIGNED NOT NULL,
  `assessment_date` DATE NOT NULL,
  `status`          ENUM('Draft','Completed') NOT NULL DEFAULT 'Draft',
  `answered_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `completeness_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `remarks`         TEXT DEFAULT NULL,
  `assessed_by`     INT UNSIGNED DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ba_beneficiary` (`beneficiary_id`),
  KEY `idx_ba_date` (`assessment_date`),
  CONSTRAINT `fk_ba_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ba_user`        FOREIGN KEY (`assessed_by`)    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `beneficiary_assessment_answers`;
CREATE TABLE `beneficiary_assessment_answers` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id`  INT UNSIGNED NOT NULL,
  `beneficiary_id` INT UNSIGNED NOT NULL,
  `question_key`   VARCHAR(60) NOT NULL,
  `value_text`     VARCHAR(500) DEFAULT NULL,
  `value_number`   DECIMAL(14,2) DEFAULT NULL,
  `value_date`     DATE DEFAULT NULL,
  `value_bool`     TINYINT(1) DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_baa` (`assessment_id`,`question_key`),
  KEY `idx_baa_beneficiary` (`beneficiary_id`,`question_key`),
  CONSTRAINT `fk_baa_assessment`  FOREIGN KEY (`assessment_id`)  REFERENCES `beneficiary_assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_baa_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Flattened "current truth" profile the matching engine reads (one row per attribute).
DROP TABLE IF EXISTS `beneficiary_profile_attributes`;
CREATE TABLE `beneficiary_profile_attributes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id` INT UNSIGNED NOT NULL,
  `attribute_key`  VARCHAR(60) NOT NULL,
  `value_text`     VARCHAR(500) DEFAULT NULL,
  `value_number`   DECIMAL(14,2) DEFAULT NULL,
  `value_bool`     TINYINT(1) DEFAULT NULL,
  `value_date`     DATE DEFAULT NULL,
  `source`         VARCHAR(40) NOT NULL DEFAULT 'assessment',
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bpa` (`beneficiary_id`,`attribute_key`),
  KEY `idx_bpa_attr` (`attribute_key`),
  CONSTRAINT `fk_bpa_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `beneficiary_documents`;
CREATE TABLE `beneficiary_documents` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id`      INT UNSIGNED NOT NULL,
  `document_type_id`    INT UNSIGNED NOT NULL,
  `document_number`     VARCHAR(80) DEFAULT NULL COMMENT 'masked on display for sensitive types',
  `issue_date`          DATE DEFAULT NULL,
  `expiry_date`         DATE DEFAULT NULL,
  `file_path`           VARCHAR(500) DEFAULT NULL COMMENT 'path under storage/documents (never web-served directly)',
  `original_name`       VARCHAR(255) DEFAULT NULL,
  `stored_name`         VARCHAR(255) DEFAULT NULL,
  `file_size`           INT UNSIGNED DEFAULT NULL,
  `mime_type`           VARCHAR(100) DEFAULT NULL,
  `file_hash`           CHAR(64) DEFAULT NULL,
  `verification_status` ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `verified_by`         INT UNSIGNED DEFAULT NULL,
  `verified_at`         DATETIME DEFAULT NULL,
  `rejection_reason`    VARCHAR(255) DEFAULT NULL,
  `remarks`             VARCHAR(500) DEFAULT NULL,
  `uploaded_by`         INT UNSIGNED DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bd_beneficiary` (`beneficiary_id`),
  KEY `idx_bd_type` (`document_type_id`),
  KEY `idx_bd_status` (`verification_status`),
  CONSTRAINT `fk_bd_beneficiary` FOREIGN KEY (`beneficiary_id`)   REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bd_type`        FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  CONSTRAINT `fk_bd_verifier`    FOREIGN KEY (`verified_by`)      REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bd_uploader`    FOREIGN KEY (`uploaded_by`)      REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 8 : MATCHING, APPLICATIONS, FOLLOW-UPS, BENEFITS
-- =====================================================================

DROP TABLE IF EXISTS `beneficiary_scheme_matches`;
CREATE TABLE `beneficiary_scheme_matches` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id`     INT UNSIGNED NOT NULL,
  `scheme_id`          INT UNSIGNED NOT NULL,
  `match_percent`      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `eligibility_status` ENUM('Eligible','Potentially Eligible','Needs Verification','Not Eligible')
                       NOT NULL DEFAULT 'Needs Verification',
  `rules_total`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `rules_passed`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `rules_failed`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `rules_unknown`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `passed_reasons`     TEXT DEFAULT NULL COMMENT 'JSON array of human-readable reasons',
  `failed_reasons`     TEXT DEFAULT NULL,
  `unknown_reasons`    TEXT DEFAULT NULL,
  `missing_documents`  TEXT DEFAULT NULL COMMENT 'JSON array of document names',
  `missing_doc_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `reason_text`        VARCHAR(1000) DEFAULT NULL COMMENT 'plain-language explanation',
  `is_dismissed`       TINYINT(1) NOT NULL DEFAULT 0,
  `matched_at`         DATETIME NOT NULL,
  `matched_by`         INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bsm` (`beneficiary_id`,`scheme_id`),
  KEY `idx_bsm_scheme` (`scheme_id`),
  KEY `idx_bsm_status` (`eligibility_status`),
  KEY `idx_bsm_pct` (`match_percent`),
  CONSTRAINT `fk_bsm_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bsm_scheme`      FOREIGN KEY (`scheme_id`)      REFERENCES `schemes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `beneficiary_scheme_applications`;
CREATE TABLE `beneficiary_scheme_applications` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_code`    VARCHAR(25) NOT NULL COMMENT 'internal tracking code',
  `beneficiary_id`      INT UNSIGNED NOT NULL,
  `scheme_id`           INT UNSIGNED NOT NULL,
  `eligibility_status`  ENUM('Eligible','Potentially Eligible','Needs Verification','Not Eligible')
                        NOT NULL DEFAULT 'Potentially Eligible',
  `status_id`           INT UNSIGNED NOT NULL,
  `referral_date`       DATE DEFAULT NULL,
  `initiated_date`      DATE DEFAULT NULL,
  `application_number`  VARCHAR(80) DEFAULT NULL COMMENT 'number issued by the department',
  `submission_date`     DATE DEFAULT NULL,
  `department`          VARCHAR(200) DEFAULT NULL,
  `verification_date`   DATE DEFAULT NULL,
  `approval_date`       DATE DEFAULT NULL,
  `rejection_reason`    VARCHAR(500) DEFAULT NULL,
  `benefit_received`    TINYINT(1) NOT NULL DEFAULT 0,
  `benefit_amount`      DECIMAL(12,2) DEFAULT NULL,
  `benefit_date`        DATE DEFAULT NULL,
  `next_followup_date`  DATE DEFAULT NULL,
  `remarks`             TEXT DEFAULT NULL,
  `assigned_to`         INT UNSIGNED DEFAULT NULL,
  -- geography copied down so district dashboards stay index-friendly
  `state_id`            INT UNSIGNED DEFAULT NULL,
  `district_id`         INT UNSIGNED DEFAULT NULL,
  `created_by`          INT UNSIGNED DEFAULT NULL,
  `updated_by`          INT UNSIGNED DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bsa_code` (`application_code`),
  UNIQUE KEY `uq_bsa_ben_scheme` (`beneficiary_id`,`scheme_id`),
  KEY `idx_bsa_scheme` (`scheme_id`),
  KEY `idx_bsa_status` (`status_id`),
  KEY `idx_bsa_state_district` (`state_id`,`district_id`),
  KEY `idx_bsa_followup` (`next_followup_date`),
  KEY `idx_bsa_assigned` (`assigned_to`),
  KEY `idx_bsa_deleted` (`deleted_at`),
  CONSTRAINT `fk_bsa_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bsa_scheme`      FOREIGN KEY (`scheme_id`)      REFERENCES `schemes` (`id`),
  CONSTRAINT `fk_bsa_status`      FOREIGN KEY (`status_id`)      REFERENCES `application_statuses` (`id`),
  CONSTRAINT `fk_bsa_assigned`    FOREIGN KEY (`assigned_to`)    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `application_status_history`;
CREATE TABLE `application_status_history` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `from_status_id` INT UNSIGNED DEFAULT NULL,
  `to_status_id`   INT UNSIGNED NOT NULL,
  `remarks`        VARCHAR(500) DEFAULT NULL,
  `changed_by`     INT UNSIGNED DEFAULT NULL,
  `changed_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ash_app` (`application_id`,`changed_at`),
  CONSTRAINT `fk_ash_app` FOREIGN KEY (`application_id`) REFERENCES `beneficiary_scheme_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `application_followups`;
CREATE TABLE `application_followups` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id`     INT UNSIGNED DEFAULT NULL,
  `beneficiary_id`     INT UNSIGNED NOT NULL,
  `scheme_id`          INT UNSIGNED DEFAULT NULL,
  `followup_date`      DATE NOT NULL,
  `followup_type`      ENUM('Phone Call','Home Visit','Department Visit','Document Collection','SMS/WhatsApp','Camp','Other')
                       NOT NULL DEFAULT 'Phone Call',
  `discussion`         TEXT DEFAULT NULL,
  `status_id`          INT UNSIGNED DEFAULT NULL COMMENT 'application status after this follow-up',
  `outcome`            ENUM('Resolved','Pending','Escalated','Not Reachable','Rescheduled') NOT NULL DEFAULT 'Pending',
  `next_followup_date` DATE DEFAULT NULL,
  `responsible_user_id` INT UNSIGNED DEFAULT NULL,
  `state_id`           INT UNSIGNED DEFAULT NULL,
  `district_id`        INT UNSIGNED DEFAULT NULL,
  `created_by`         INT UNSIGNED DEFAULT NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_af_app` (`application_id`),
  KEY `idx_af_beneficiary` (`beneficiary_id`),
  KEY `idx_af_date` (`followup_date`),
  KEY `idx_af_next` (`next_followup_date`),
  KEY `idx_af_responsible` (`responsible_user_id`),
  KEY `idx_af_geo` (`state_id`,`district_id`),
  CONSTRAINT `fk_af_app`         FOREIGN KEY (`application_id`) REFERENCES `beneficiary_scheme_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_af_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_af_scheme`      FOREIGN KEY (`scheme_id`)      REFERENCES `schemes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_af_user`        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `benefits_received`;
CREATE TABLE `benefits_received` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED DEFAULT NULL,
  `beneficiary_id` INT UNSIGNED NOT NULL,
  `scheme_id`      INT UNSIGNED NOT NULL,
  `benefit_type`   VARCHAR(120) DEFAULT NULL,
  `amount`         DECIMAL(12,2) DEFAULT NULL,
  `received_date`  DATE NOT NULL,
  `payment_mode`   ENUM('DBT','Cheque','Cash','In-kind','Service','Insurance Cover','Other') NOT NULL DEFAULT 'DBT',
  `reference_no`   VARCHAR(80) DEFAULT NULL,
  `instalment_no`  TINYINT UNSIGNED DEFAULT NULL,
  `remarks`        VARCHAR(500) DEFAULT NULL,
  `state_id`       INT UNSIGNED DEFAULT NULL,
  `district_id`    INT UNSIGNED DEFAULT NULL,
  `recorded_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_br_app` (`application_id`),
  KEY `idx_br_beneficiary` (`beneficiary_id`),
  KEY `idx_br_scheme` (`scheme_id`),
  KEY `idx_br_date` (`received_date`),
  KEY `idx_br_geo` (`state_id`,`district_id`),
  CONSTRAINT `fk_br_app`         FOREIGN KEY (`application_id`) REFERENCES `beneficiary_scheme_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_br_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_br_scheme`      FOREIGN KEY (`scheme_id`)      REFERENCES `schemes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `field_visits`;
CREATE TABLE `field_visits` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id` INT UNSIGNED DEFAULT NULL,
  `visit_date`     DATE NOT NULL,
  `visit_type`     ENUM('Registration','Assessment','Document Collection','Follow-up','Verification','Awareness Camp','Other')
                   NOT NULL DEFAULT 'Registration',
  `field_user_id`  INT UNSIGNED NOT NULL,
  `state_id`       INT UNSIGNED DEFAULT NULL,
  `district_id`    INT UNSIGNED DEFAULT NULL,
  `block_id`       INT UNSIGNED DEFAULT NULL,
  `village_id`     INT UNSIGNED DEFAULT NULL,
  `village_name`   VARCHAR(150) DEFAULT NULL,
  `households_covered` SMALLINT UNSIGNED DEFAULT NULL,
  `purpose`        VARCHAR(255) DEFAULT NULL,
  `observations`   TEXT DEFAULT NULL,
  `latitude`       DECIMAL(10,7) DEFAULT NULL,
  `longitude`      DECIMAL(10,7) DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fv_beneficiary` (`beneficiary_id`),
  KEY `idx_fv_user_date` (`field_user_id`,`visit_date`),
  KEY `idx_fv_geo` (`state_id`,`district_id`),
  CONSTRAINT `fk_fv_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fv_user`        FOREIGN KEY (`field_user_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 9 : SCHEME IMPORT (Excel)
-- =====================================================================

DROP TABLE IF EXISTS `scheme_import_batches`;
CREATE TABLE `scheme_import_batches` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_code`    VARCHAR(25) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_path`   VARCHAR(500) DEFAULT NULL,
  `sheet_name`    VARCHAR(120) DEFAULT NULL,
  `column_map`    TEXT DEFAULT NULL COMMENT 'JSON: excel header -> db field',
  `total_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
  `valid_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
  `inserted_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows`   INT UNSIGNED NOT NULL DEFAULT 0,
  `status`        ENUM('Uploaded','Validated','Imported','Cancelled','Failed') NOT NULL DEFAULT 'Uploaded',
  `message`       VARCHAR(500) DEFAULT NULL,
  `imported_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sib_code` (`batch_code`),
  KEY `idx_sib_user` (`imported_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scheme_import_rows`;
CREATE TABLE `scheme_import_rows` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`   INT UNSIGNED NOT NULL,
  `row_no`     INT UNSIGNED NOT NULL,
  `scheme_name` VARCHAR(300) DEFAULT NULL,
  `raw_data`   TEXT DEFAULT NULL COMMENT 'JSON of the parsed row',
  `status`     ENUM('Valid','Duplicate','Error','Inserted','Updated','Skipped') NOT NULL DEFAULT 'Valid',
  `message`    VARCHAR(500) DEFAULT NULL,
  `scheme_id`  INT UNSIGNED DEFAULT NULL,
  `action`     ENUM('insert','update','skip') NOT NULL DEFAULT 'insert',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sir_batch` (`batch_id`,`status`),
  CONSTRAINT `fk_sir_batch` FOREIGN KEY (`batch_id`) REFERENCES `scheme_import_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 10 : AUDIT, NOTIFICATIONS, SETTINGS
-- =====================================================================

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `user_name`   VARCHAR(150) DEFAULT NULL COMMENT 'snapshot so history survives user deletion',
  `role_code`   VARCHAR(50)  DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `module`      VARCHAR(60)  NOT NULL,
  `action`      ENUM('Created','Modified','Deleted','Viewed','Login','Logout','Approved','Rejected','Exported','Imported','Matched','Verified','Failed Login','Password Reset')
                NOT NULL,
  `entity_type` VARCHAR(60)  DEFAULT NULL,
  `entity_id`   INT UNSIGNED DEFAULT NULL,
  `entity_label` VARCHAR(255) DEFAULT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `old_values`  TEXT DEFAULT NULL COMMENT 'JSON snapshot before change',
  `new_values`  TEXT DEFAULT NULL COMMENT 'JSON snapshot after change',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user_time` (`user_id`,`created_at`),
  KEY `idx_al_module` (`module`,`action`),
  KEY `idx_al_entity` (`entity_type`,`entity_id`),
  KEY `idx_al_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL COMMENT 'NULL = broadcast to role/geography',
  `role_id`     INT UNSIGNED DEFAULT NULL,
  `state_id`    INT UNSIGNED DEFAULT NULL,
  `district_id` INT UNSIGNED DEFAULT NULL,
  `type`        VARCHAR(50) NOT NULL COMMENT 'followup_due, document_pending, application_approved, ...',
  `title`       VARCHAR(200) NOT NULL,
  `message`     VARCHAR(500) DEFAULT NULL,
  `link`        VARCHAR(255) DEFAULT NULL,
  `icon`        VARCHAR(40) DEFAULT 'bi-bell',
  `priority`    ENUM('Low','Normal','High','Critical') NOT NULL DEFAULT 'Normal',
  `entity_type` VARCHAR(60) DEFAULT NULL,
  `entity_id`   INT UNSIGNED DEFAULT NULL,
  -- channel flags keep the door open for SMS / WhatsApp / Email later
  `channel_inapp` TINYINT(1) NOT NULL DEFAULT 1,
  `channel_email` TINYINT(1) NOT NULL DEFAULT 0,
  `channel_sms`   TINYINT(1) NOT NULL DEFAULT 0,
  `channel_whatsapp` TINYINT(1) NOT NULL DEFAULT 0,
  `dispatch_status`  ENUM('Pending','Sent','Failed','Not Required') NOT NULL DEFAULT 'Not Required',
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`     DATETIME DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notif_role_geo` (`role_id`,`state_id`,`district_id`),
  KEY `idx_notif_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_reads`;
CREATE TABLE `notification_reads` (
  `notification_id` BIGINT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `read_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`,`user_id`),
  KEY `idx_nr_user` (`user_id`),
  CONSTRAINT `fk_nr_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nr_user`  FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `setting_key`   VARCHAR(80) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `value_type`    ENUM('string','int','bool','json','text') NOT NULL DEFAULT 'string',
  `label`         VARCHAR(150) NOT NULL,
  `description`   VARCHAR(500) DEFAULT NULL,
  `is_editable`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`    SMALLINT NOT NULL DEFAULT 0,
  `updated_by`    INT UNSIGNED DEFAULT NULL,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ss_key` (`setting_key`),
  KEY `idx_ss_group` (`setting_group`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 11 : REPORTING VIEWS
-- =====================================================================

DROP VIEW IF EXISTS `vw_scheme_listing`;
CREATE VIEW `vw_scheme_listing` AS
SELECT s.`id`, s.`scheme_code`, s.`name`, s.`short_name`, s.`slug`,
       s.`scheme_level`, s.`scheme_status`, s.`active_enrollment`, s.`is_active`,
       s.`state_id`, st.`name` AS state_name,
       s.`theme_id`, th.`name` AS theme_name, th.`color` AS theme_color,
       s.`service_approach_id`, sa.`name` AS service_approach,
       s.`priority_id`, pl.`rank_value` AS priority_rank, pl.`name` AS priority_name,
       s.`feasibility_id`, fl.`rank_value` AS feasibility_rank, fl.`name` AS feasibility_name,
       s.`updated_at`, s.`created_at`, s.`last_verified_date`, s.`source_url`,
       (SELECT GROUP_CONCAT(bt.`name` ORDER BY bt.`sort_order` SEPARATOR ', ')
          FROM `scheme_beneficiary_types` sbt
          JOIN `beneficiary_types` bt ON bt.`id` = sbt.`beneficiary_type_id`
         WHERE sbt.`scheme_id` = s.`id`) AS beneficiary_types,
       (SELECT COUNT(*) FROM `scheme_documents` sd WHERE sd.`scheme_id` = s.`id`) AS document_count,
       (SELECT COUNT(*) FROM `scheme_eligibility_rules` r WHERE r.`scheme_id` = s.`id` AND r.`is_active` = 1) AS rule_count
  FROM `schemes` s
  LEFT JOIN `states` st             ON st.`id` = s.`state_id`
  LEFT JOIN `scheme_themes` th      ON th.`id` = s.`theme_id`
  LEFT JOIN `service_approaches` sa ON sa.`id` = s.`service_approach_id`
  LEFT JOIN `priority_levels` pl    ON pl.`id` = s.`priority_id`
  LEFT JOIN `feasibility_levels` fl ON fl.`id` = s.`feasibility_id`
 WHERE s.`deleted_at` IS NULL;

DROP VIEW IF EXISTS `vw_beneficiary_listing`;
CREATE VIEW `vw_beneficiary_listing` AS
SELECT b.`id`, b.`beneficiary_code`, b.`name`, b.`gender`, b.`age`, b.`mobile`,
       b.`registration_date`, b.`record_status`, b.`assessment_status`, b.`area_type`,
       b.`state_id`, st.`name` AS state_name,
       b.`district_id`, d.`name` AS district_name,
       b.`block_id`, bl.`name` AS block_name,
       b.`village_id`, COALESCE(v.`name`, b.`village_name`) AS village_name,
       b.`field_user_id`, u.`full_name` AS field_user_name, b.`created_by`,
       (SELECT COUNT(*) FROM `beneficiary_scheme_matches` m
         WHERE m.`beneficiary_id` = b.`id` AND m.`eligibility_status` <> 'Not Eligible') AS schemes_identified,
       (SELECT COUNT(*) FROM `beneficiary_scheme_applications` a
         WHERE a.`beneficiary_id` = b.`id` AND a.`deleted_at` IS NULL) AS applications_count,
       (SELECT COUNT(*) FROM `beneficiary_scheme_applications` a
         WHERE a.`beneficiary_id` = b.`id` AND a.`submission_date` IS NOT NULL AND a.`deleted_at` IS NULL) AS applications_submitted,
       (SELECT COUNT(*) FROM `beneficiary_scheme_applications` a
         WHERE a.`beneficiary_id` = b.`id` AND a.`benefit_received` = 1 AND a.`deleted_at` IS NULL) AS benefits_received,
       (SELECT COUNT(*) FROM `beneficiary_documents` bd WHERE bd.`beneficiary_id` = b.`id`) AS document_count,
       b.`created_at`, b.`updated_at`
  FROM `beneficiaries` b
  LEFT JOIN `states` st          ON st.`id` = b.`state_id`
  LEFT JOIN `districts` d        ON d.`id`  = b.`district_id`
  LEFT JOIN `blocks` bl          ON bl.`id` = b.`block_id`
  LEFT JOIN `villages` v         ON v.`id`  = b.`village_id`
  LEFT JOIN `users` u            ON u.`id`  = b.`field_user_id`
 WHERE b.`deleted_at` IS NULL;

DROP VIEW IF EXISTS `vw_application_listing`;
CREATE VIEW `vw_application_listing` AS
SELECT a.`id`, a.`application_code`, a.`application_number`,
       a.`beneficiary_id`, b.`beneficiary_code`, b.`name` AS beneficiary_name, b.`gender`, b.`age`, b.`mobile`,
       a.`scheme_id`, s.`name` AS scheme_name, s.`short_name` AS scheme_short_name, s.`scheme_level`,
       s.`theme_id`, th.`name` AS theme_name,
       a.`status_id`, ast.`name` AS status_name, ast.`color` AS status_color,
       ast.`funnel_stage`, ast.`stage_order`,
       a.`eligibility_status`, a.`referral_date`, a.`initiated_date`, a.`submission_date`,
       a.`approval_date`, a.`benefit_received`, a.`benefit_amount`, a.`benefit_date`,
       a.`next_followup_date`, a.`rejection_reason`, a.`department`,
       a.`state_id`, st.`name` AS state_name,
       a.`district_id`, d.`name` AS district_name,
       a.`assigned_to`, u.`full_name` AS assigned_to_name,
       -- owner columns, so a field user's scope can be applied to the view
       b.`field_user_id`, a.`created_by`,
       a.`created_at`, a.`updated_at`
  FROM `beneficiary_scheme_applications` a
  JOIN `beneficiaries` b            ON b.`id`   = a.`beneficiary_id`
  JOIN `schemes` s                  ON s.`id`   = a.`scheme_id`
  JOIN `application_statuses` ast   ON ast.`id` = a.`status_id`
  LEFT JOIN `scheme_themes` th      ON th.`id`  = s.`theme_id`
  LEFT JOIN `states` st             ON st.`id`  = a.`state_id`
  LEFT JOIN `districts` d           ON d.`id`   = a.`district_id`
  LEFT JOIN `users` u               ON u.`id`   = a.`assigned_to`
 WHERE a.`deleted_at` IS NULL;

DROP VIEW IF EXISTS `vw_field_user_performance`;
CREATE VIEW `vw_field_user_performance` AS
SELECT u.`id` AS user_id, u.`user_code`, u.`full_name`, u.`username`, r.`name` AS role_name,
       u.`state_id`, st.`name` AS state_name,
       u.`district_id`, d.`name` AS district_name, u.`is_active`, u.`last_login_at`,
       COALESCE(bn.`registered`,0)          AS beneficiaries_registered,
       COALESCE(bn.`assessed`,0)            AS assessments_completed,
       COALESCE(mt.`schemes_identified`,0)  AS schemes_identified,
       COALESCE(ap.`initiated`,0)           AS applications_initiated,
       COALESCE(ap.`submitted`,0)           AS applications_submitted,
       COALESCE(ap.`approved`,0)            AS applications_approved,
       COALESCE(ap.`benefited`,0)           AS benefits_received,
       COALESCE(fu.`followups`,0)           AS followups_done,
       COALESCE(fv.`visits`,0)              AS field_visits
  FROM `users` u
  JOIN `roles` r        ON r.`id`  = u.`role_id`
  LEFT JOIN `states` st ON st.`id` = u.`state_id`
  LEFT JOIN `districts` d ON d.`id` = u.`district_id`
  LEFT JOIN (
      SELECT `field_user_id` AS uid,
             COUNT(*) AS registered,
             SUM(CASE WHEN `assessment_status` = 'Completed' THEN 1 ELSE 0 END) AS assessed
        FROM `beneficiaries` WHERE `deleted_at` IS NULL GROUP BY `field_user_id`
  ) bn ON bn.uid = u.`id`
  LEFT JOIN (
      SELECT b.`field_user_id` AS uid, COUNT(*) AS schemes_identified
        FROM `beneficiary_scheme_matches` m
        JOIN `beneficiaries` b ON b.`id` = m.`beneficiary_id`
       WHERE m.`eligibility_status` <> 'Not Eligible' AND b.`deleted_at` IS NULL
       GROUP BY b.`field_user_id`
  ) mt ON mt.uid = u.`id`
  LEFT JOIN (
      SELECT b.`field_user_id` AS uid,
             SUM(CASE WHEN a.`initiated_date`  IS NOT NULL THEN 1 ELSE 0 END) AS initiated,
             SUM(CASE WHEN a.`submission_date` IS NOT NULL THEN 1 ELSE 0 END) AS submitted,
             SUM(CASE WHEN a.`approval_date`   IS NOT NULL THEN 1 ELSE 0 END) AS approved,
             SUM(CASE WHEN a.`benefit_received` = 1        THEN 1 ELSE 0 END) AS benefited
        FROM `beneficiary_scheme_applications` a
        JOIN `beneficiaries` b ON b.`id` = a.`beneficiary_id`
       WHERE a.`deleted_at` IS NULL AND b.`deleted_at` IS NULL
       GROUP BY b.`field_user_id`
  ) ap ON ap.uid = u.`id`
  LEFT JOIN (
      SELECT `created_by` AS uid, COUNT(*) AS followups FROM `application_followups` GROUP BY `created_by`
  ) fu ON fu.uid = u.`id`
  LEFT JOIN (
      SELECT `field_user_id` AS uid, COUNT(*) AS visits FROM `field_visits` GROUP BY `field_user_id`
  ) fv ON fv.uid = u.`id`
 WHERE u.`deleted_at` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- END OF SCHEMA
-- =====================================================================
