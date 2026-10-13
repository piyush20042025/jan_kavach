-- =====================================================================
--  JAN KAVACH - MIGRATION 01 : BENEFICIARY PROFILE QUESTIONNAIRE
--
--  Brings an existing database up to the schema the questionnaire from
--  "Beneficiary Profile Questionnaire.docx" needs. A database created from
--  the current jan_kavach.sql already has all of this - run this file only
--  when upgrading an installation made before 2026-09-29.
--
--  Run this BEFORE database/seed_07_questionnaire.sql.
--
--  Every statement is written so a second run is harmless.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Beneficiary identity fields the questionnaire asks for by name.
--
--    Father's and Mother's name are asked separately, which the single
--    relation_type / relation_name pair could not hold. relation_* is kept
--    for the "care of" line already shown on profiles and printouts.
-- ---------------------------------------------------------------------
ALTER TABLE `beneficiaries`
  ADD COLUMN IF NOT EXISTS `father_name`  VARCHAR(150) DEFAULT NULL AFTER `relation_name`,
  ADD COLUMN IF NOT EXISTS `mother_name`  VARCHAR(150) DEFAULT NULL AFTER `father_name`,
  ADD COLUMN IF NOT EXISTS `spouse_name`  VARCHAR(150) DEFAULT NULL AFTER `mother_name`,
  ADD COLUMN IF NOT EXISTS `post_office`  VARCHAR(120) DEFAULT NULL AFTER `village_name`;

-- ---------------------------------------------------------------------
-- 2. Conditional questions.
--
--    The questionnaire opens Occupation and Monthly Income only when the
--    person earns, and the "if Other, mention" boxes only when Other was
--    chosen. depends_on_value holds one or more triggering codes,
--    comma-separated.
-- ---------------------------------------------------------------------
ALTER TABLE `assessment_questions`
  ADD COLUMN IF NOT EXISTS `depends_on_key`   VARCHAR(60)  DEFAULT NULL AFTER `lookup_key`,
  ADD COLUMN IF NOT EXISTS `depends_on_value` VARCHAR(200) DEFAULT NULL AFTER `depends_on_key`;

-- ---------------------------------------------------------------------
-- 3. Multi-valued answers.
--
--    Vulnerability Group accepts several answers at once. They are stored
--    as a comma-separated list in one attribute row, and the new `set` data
--    type tells the matching engine to compare it as a set rather than as
--    one string.
-- ---------------------------------------------------------------------
ALTER TABLE `eligibility_attributes`
  MODIFY COLUMN `data_type` ENUM('int','decimal','bool','string','enum','set','date')
    NOT NULL DEFAULT 'string';

-- ---------------------------------------------------------------------
-- 4. The beneficiary listing view gains the new name columns.
-- ---------------------------------------------------------------------
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
