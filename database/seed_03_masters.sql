-- =====================================================================
--  JAN KAVACH - SEED 03 : DOMAIN MASTERS
--  Themes, beneficiary types, documents, lookups, eligibility vocabulary
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `jan_kavach`;

-- Re-runnable: clear then reload.
DELETE FROM `scheme_themes`;
DELETE FROM `beneficiary_types`;
DELETE FROM `target_groups`;
DELETE FROM `service_approaches`;
DELETE FROM `priority_levels`;
DELETE FROM `feasibility_levels`;
DELETE FROM `document_types`;
DELETE FROM `application_statuses`;
DELETE FROM `lookup_options`;
DELETE FROM `eligibility_attributes`;
DELETE FROM `assessment_questions`;
DELETE FROM `system_settings`;
DELETE FROM `implementing_agencies`;
ALTER TABLE `scheme_themes` AUTO_INCREMENT = 1;
ALTER TABLE `beneficiary_types` AUTO_INCREMENT = 1;
ALTER TABLE `target_groups` AUTO_INCREMENT = 1;
ALTER TABLE `service_approaches` AUTO_INCREMENT = 1;
ALTER TABLE `priority_levels` AUTO_INCREMENT = 1;
ALTER TABLE `feasibility_levels` AUTO_INCREMENT = 1;
ALTER TABLE `document_types` AUTO_INCREMENT = 1;
ALTER TABLE `application_statuses` AUTO_INCREMENT = 1;
ALTER TABLE `lookup_options` AUTO_INCREMENT = 1;
ALTER TABLE `eligibility_attributes` AUTO_INCREMENT = 1;
ALTER TABLE `assessment_questions` AUTO_INCREMENT = 1;
ALTER TABLE `system_settings` AUTO_INCREMENT = 1;
ALTER TABLE `implementing_agencies` AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------
-- Scheme themes (34)
-- ---------------------------------------------------------------
INSERT INTO `scheme_themes` (`id`, `code`, `name`, `description`, `color`, `icon`, `sort_order`, `is_active`) VALUES
  (1, 'HEALTH', 'Health', 'Preventive, primary and curative health services', '#e11d48', 'bi-heart-pulse', 1, 1),
  (2, 'HEALTH_INSURANCE', 'Health Insurance', 'Cashless hospitalisation and medical insurance cover', '#be123c', 'bi-shield-plus', 2, 1),
  (3, 'MEDICAL_AID', 'Medical Financial Assistance', 'One-time financial help for treatment costs', '#9f1239', 'bi-bandaid', 3, 1),
  (4, 'NUTRITION', 'Nutrition', 'Supplementary nutrition and anaemia control', '#f59e0b', 'bi-egg-fried', 4, 1),
  (5, 'EDUCATION', 'Education', 'Scholarships, fee support and school education', '#2563eb', 'bi-mortarboard', 5, 1),
  (6, 'SKILL_DEV', 'Skill Development', 'Vocational training and certification', '#1d4ed8', 'bi-tools', 6, 1),
  (7, 'DIGITAL_INCLUSION', 'Digital Inclusion', 'Devices, connectivity and digital literacy', '#3b82f6', 'bi-laptop', 7, 1),
  (8, 'LANGUAGE', 'Language Promotion', 'Mother-tongue and language development', '#60a5fa', 'bi-translate', 8, 1),
  (9, 'LIVELIHOOD', 'Livelihood', 'Income generation and self-employment support', '#059669', 'bi-briefcase', 9, 1),
  (10, 'EMPLOYMENT', 'Employment', 'Wage employment and job guarantee', '#047857', 'bi-person-workspace', 10, 1),
  (11, 'ENTREPRENEURSHIP', 'Entrepreneurship', 'Enterprise set-up, seed capital and incubation', '#0d9488', 'bi-lightbulb', 11, 1),
  (12, 'AGRICULTURE', 'Agriculture', 'Crop production, inputs and farm advisory', '#65a30d', 'bi-flower3', 12, 1),
  (13, 'IRRIGATION', 'Irrigation', 'Micro-irrigation and water management', '#0891b2', 'bi-droplet', 13, 1),
  (14, 'FOOD_SECURITY', 'Food Security', 'Subsidised food grain and public distribution', '#ca8a04', 'bi-basket', 14, 1),
  (15, 'HOUSING', 'Housing', 'Housing construction and upgrade assistance', '#c2410c', 'bi-house-door', 15, 1),
  (16, 'ENERGY', 'Energy', 'Clean cooking fuel and household energy', '#ea580c', 'bi-lightning-charge', 16, 1),
  (17, 'SOCIAL_SECURITY', 'Social Security', 'Pension, provident and contributory security', '#4f46e5', 'bi-shield-check', 17, 1),
  (18, 'OLD_AGE', 'Old Age Protection', 'Old-age pension and elderly welfare', '#4338ca', 'bi-person-walking', 18, 1),
  (19, 'WIDOW_SUPPORT', 'Widow Support', 'Widow pension and destitute-woman support', '#6d28d9', 'bi-person-heart', 19, 1),
  (20, 'DISABILITY', 'Disability Support', 'Disability certification, aids and pension', '#7c3aed', 'bi-universal-access', 20, 1),
  (21, 'INSURANCE', 'Insurance', 'Life and accident insurance cover', '#5b21b6', 'bi-umbrella', 21, 1),
  (22, 'CASH_TRANSFER', 'Cash Transfer', 'Direct benefit transfer to the beneficiary', '#0284c7', 'bi-cash-coin', 22, 1),
  (23, 'FINANCIAL_ASSISTANCE', 'Financial Assistance', 'Grants and one-time financial aid', '#0369a1', 'bi-wallet2', 23, 1),
  (24, 'FINANCIAL_INCLUSION', 'Financial Inclusion', 'Bank accounts, credit and savings access', '#075985', 'bi-bank', 24, 1),
  (25, 'WOMEN_EMPOWERMENT', 'Women Empowerment', 'Economic and social empowerment of women', '#db2777', 'bi-gender-female', 25, 1),
  (26, 'WOMEN_SAFETY', 'Women\'s Safety', 'Protection, helplines and one-stop centres', '#be185d', 'bi-shield-exclamation', 26, 1),
  (27, 'GIRL_CHILD', 'Girl Child Empowerment', 'Girl-child survival, education and incentives', '#ec4899', 'bi-star', 27, 1),
  (28, 'CHILD_WELFARE', 'Child Welfare & Protection', 'Child care, sponsorship and protection services', '#f472b6', 'bi-people', 28, 1),
  (29, 'PROTECTION', 'Protection & Safety', 'Victim protection and rehabilitation services', '#dc2626', 'bi-shield-lock', 29, 1),
  (30, 'REHABILITATION', 'Rehabilitation', 'Rehabilitation of victims and affected persons', '#b91c1c', 'bi-arrow-repeat', 30, 1),
  (31, 'COMPENSATION', 'Compensation', 'Statutory compensation and relief payments', '#991b1b', 'bi-file-earmark-medical', 31, 1),
  (32, 'WORKER_WELFARE', 'Worker Welfare', 'Welfare boards for unorganised and construction workers', '#475569', 'bi-hammer', 32, 1),
  (33, 'TRANSPORT', 'Transport Support', 'Bicycles, scooters and travel assistance', '#64748b', 'bi-bus-front', 33, 1),
  (34, 'POVERTY', 'Poverty Alleviation', 'Multi-dimensional poverty reduction programmes', '#78716c', 'bi-graph-up-arrow', 34, 1);

-- ---------------------------------------------------------------
-- Beneficiary types (22)
-- ---------------------------------------------------------------
INSERT INTO `beneficiary_types` (`id`, `code`, `name`, `description`, `icon`, `sort_order`, `is_active`) VALUES
  (1, 'UNIVERSAL', 'Universal', 'Open to all citizens meeting the scheme conditions', 'bi-globe', 1, 1),
  (2, 'WOMEN', 'Women', 'Women beneficiaries', 'bi-gender-female', 2, 1),
  (3, 'PREGNANT_WOMEN', 'Pregnant Women', 'Women during pregnancy', 'bi-heart', 3, 1),
  (4, 'LACTATING', 'Lactating Mothers', 'Mothers in the breastfeeding period', 'bi-heart-half', 4, 1),
  (5, 'CHILDREN', 'Children', 'Children, typically 0-18 years', 'bi-person-arms-up', 5, 1),
  (6, 'YOUTH', 'Youth', 'Young people, typically 15-35 years', 'bi-person-badge', 6, 1),
  (7, 'STUDENTS', 'Students', 'Enrolled school, college or university students', 'bi-book', 7, 1),
  (8, 'ELDERLY', 'Elderly', 'Senior citizens, typically 60 years and above', 'bi-person-walking', 8, 1),
  (9, 'PWD', 'Persons with Disabilities', 'Persons with benchmark disability or chronic illness', 'bi-universal-access', 9, 1),
  (10, 'FARMERS', 'Farmers', 'Cultivators and agricultural households', 'bi-flower2', 10, 1),
  (11, 'WORKERS', 'Workers', 'Wage workers across sectors', 'bi-hammer', 11, 1),
  (12, 'INFORMAL_WORKERS', 'Informal / Unorganised Workers', 'Workers in the unorganised sector', 'bi-people-fill', 12, 1),
  (13, 'WEAVERS', 'Weavers & Handloom Artisans', 'Handloom weavers and allied artisans', 'bi-basket2', 13, 1),
  (14, 'ENTREPRENEURS', 'Entrepreneurs', 'Micro and small enterprise owners', 'bi-lightbulb', 14, 1),
  (15, 'MINORITIES', 'Minorities', 'Notified minority communities', 'bi-people', 15, 1),
  (16, 'TRANSGENDER', 'Transgender Persons', 'Transgender and gender-diverse persons', 'bi-gender-ambiguous', 16, 1),
  (17, 'ARTISTS', 'Artists', 'Practising artists and performers', 'bi-music-note-beamed', 17, 1),
  (18, 'HOUSEHOLDS', 'Households', 'Benefit accrues to the household as a unit', 'bi-house', 18, 1),
  (19, 'FAMILIES', 'Families', 'Family-level benefit', 'bi-people', 19, 1),
  (20, 'LOW_INCOME', 'Low-income Families', 'Households below the prescribed income ceiling', 'bi-cash-stack', 20, 1),
  (21, 'DESTITUTE_WOMEN', 'Destitute Women & Adolescent Girls', 'Women and girls in difficult circumstances', 'bi-person-heart', 21, 1),
  (22, 'INDIVIDUALS', 'Individuals', 'Individual applicant, no group restriction', 'bi-person', 22, 1);

-- ---------------------------------------------------------------
-- Target groups (23)
-- ---------------------------------------------------------------
INSERT INTO `target_groups` (`id`, `code`, `name`, `description`, `sort_order`, `is_active`) VALUES
  (1, 'PREGNANT_LACTATING', 'Pregnant and lactating women', NULL, 1, 1),
  (2, 'CHILD_0_6', 'Children 0-6 years', NULL, 2, 1),
  (3, 'CHILD_6_14', 'Children 6-14 years', NULL, 3, 1),
  (4, 'ADOLESCENT_GIRLS', 'Adolescent girls 11-18 years', NULL, 4, 1),
  (5, 'SENIOR_CITIZENS', 'Senior citizens 60 years and above', NULL, 5, 1),
  (6, 'WIDOWS', 'Widows and deserted women', NULL, 6, 1),
  (7, 'PWD_40', 'Persons with 40% or more disability', NULL, 7, 1),
  (8, 'SMALL_MARGINAL', 'Small and marginal farmers', NULL, 8, 1),
  (9, 'LANDLESS', 'Landless agricultural labourers', NULL, 9, 1),
  (10, 'BPL_FAMILIES', 'Below Poverty Line families', NULL, 10, 1),
  (11, 'INFORMAL_WORKERS', 'Unorganised / informal sector workers', NULL, 11, 1),
  (12, 'CONSTRUCTION_WORK', 'Registered construction workers', NULL, 12, 1),
  (13, 'WEAVERS', 'Handloom weavers and artisans', NULL, 13, 1),
  (14, 'SC_ST', 'Scheduled Castes and Scheduled Tribes', NULL, 14, 1),
  (15, 'OBC_MINORITY', 'OBC and minority communities', NULL, 15, 1),
  (16, 'STUDENTS_PREMATRIC', 'Pre-matric students', NULL, 16, 1),
  (17, 'STUDENTS_POSTMATRIC', 'Post-matric students', NULL, 17, 1),
  (18, 'YOUTH_18_35', 'Youth 18-35 years', NULL, 18, 1),
  (19, 'SHG_WOMEN', 'Women in Self-Help Groups', NULL, 19, 1),
  (20, 'TEA_GARDEN', 'Tea garden community', NULL, 20, 1),
  (21, 'URBAN_POOR', 'Urban poor households', NULL, 21, 1),
  (22, 'RURAL_HOUSEHOLDS', 'Rural households', NULL, 22, 1),
  (23, 'ALL_CITIZENS', 'All citizens', NULL, 23, 1);

-- ---------------------------------------------------------------
-- Service approaches
-- ---------------------------------------------------------------
INSERT INTO `service_approaches` (`id`, `code`, `name`, `description`, `color`, `sort_order`, `is_active`) VALUES
  (1, 'DIRECT_LINKAGE', 'Direct Linkage', 'Field team completes the application end to end', '#198754', 1, 1),
  (2, 'REFERRAL', 'Referral to Concerned Department', 'Beneficiary is referred to the implementing department', '#0d6efd', 2, 1),
  (3, 'AWARENESS', 'Awareness Generation', 'Information sharing and community mobilisation', '#fd7e14', 3, 1),
  (4, 'AWARENESS_REFERRAL', 'Awareness / Referral', 'Awareness generation combined with a departmental referral', '#6f42c1', 4, 1);

-- ---------------------------------------------------------------
-- Priority ranking (for programme prioritisation)
-- ---------------------------------------------------------------
INSERT INTO `priority_levels` (`id`, `rank_value`, `code`, `name`, `description`, `color`, `is_active`) VALUES
  (1, 1, 'PRIORITY_1', 'Priority 1 - Basic and Fundamental Schemes', 'Pension, insurance, PDS cards, BPL cards and medical schemes: the foundational entitlements every household should hold.', '#dc3545', 1),
  (2, 2, 'PRIORITY_2', 'Priority 2 - Domain Specific Schemes', 'Schemes targeted at a specific domain such as education, agriculture or livelihood.', '#fd7e14', 1),
  (3, 3, 'PRIORITY_3', 'Priority 3 - Other Schemes', 'Schemes that do not fall under Priority 1 or Priority 2.', '#6c757d', 1);

-- ---------------------------------------------------------------
-- Feasibility ranking (1 = difficult ... 5 = easy)
-- ---------------------------------------------------------------
INSERT INTO `feasibility_levels` (`id`, `rank_value`, `name`, `description`, `color`, `is_active`) VALUES
  (1, 1, 'Very Difficult', 'Dormant or discontinued enrolment, heavy documentation, many procedural hurdles.', '#dc3545', 1),
  (2, 2, 'Difficult', 'Enrolment open but with significant documentation or approval hurdles.', '#fd7e14', 1),
  (3, 3, 'Moderate', 'Standard documentation and processing effort.', '#ffc107', 1),
  (4, 4, 'Easy', 'Active enrolment, limited documentation, predictable processing.', '#20c997', 1),
  (5, 5, 'Very Easy', 'Active enrolment with government priority, simple application, minimal documents.', '#198754', 1);

-- ---------------------------------------------------------------
-- Document master (42)
-- ---------------------------------------------------------------
INSERT INTO `document_types` (`id`, `code`, `name`, `description`, `category`, `requires_number`, `validity_required`, `is_sensitive`, `sort_order`, `is_active`) VALUES
  (1, 'AADHAAR', 'Aadhaar Card', 'Unique identification number issued by UIDAI', 'Identity', 1, 0, 1, 1, 1),
  (2, 'VOTER_ID', 'Voter ID (EPIC)', 'Electoral photo identity card', 'Identity', 1, 0, 1, 2, 1),
  (3, 'PAN', 'PAN Card', 'Permanent Account Number card', 'Identity', 1, 0, 1, 3, 1),
  (4, 'PHOTO', 'Passport Size Photograph', 'Recent passport-size colour photograph', 'Photo', 0, 0, 0, 4, 1),
  (5, 'BIRTH_CERT', 'Birth Certificate', 'Municipal or panchayat birth registration certificate', 'Certificate', 1, 0, 0, 5, 1),
  (6, 'AGE_PROOF', 'Age Proof', 'Any accepted document evidencing date of birth', 'Certificate', 0, 0, 0, 6, 1),
  (7, 'DEATH_CERT', 'Death Certificate', 'Required for family benefit and widow pension claims', 'Certificate', 1, 0, 0, 7, 1),
  (8, 'RESIDENCE', 'Residence / Domicile Certificate', 'Proof of residence in the State or District', 'Address', 1, 1, 0, 8, 1),
  (9, 'ADDRESS_PROOF', 'Address Proof', 'Utility bill, rent agreement or equivalent', 'Address', 0, 0, 0, 9, 1),
  (10, 'RATION_CARD', 'Ration Card', 'Public Distribution System entitlement card', 'Income', 1, 0, 1, 10, 1),
  (11, 'BPL_CERT', 'BPL Certificate', 'Below Poverty Line status certificate', 'Income', 1, 1, 0, 11, 1),
  (12, 'INCOME_CERT', 'Income Certificate', 'Competent-authority certificate of annual family income', 'Income', 1, 1, 0, 12, 1),
  (13, 'CASTE_CERT', 'Caste Certificate', 'SC / ST / OBC category certificate', 'Category', 1, 0, 0, 13, 1),
  (14, 'MINORITY_CERT', 'Minority Community Certificate', 'Proof of belonging to a notified minority community', 'Category', 1, 0, 0, 14, 1),
  (15, 'EWS_CERT', 'EWS Certificate', 'Economically Weaker Section certificate', 'Category', 1, 1, 0, 15, 1),
  (16, 'BANK_PASSBOOK', 'Bank Passbook', 'First page showing account number and IFSC', 'Bank', 1, 0, 1, 16, 1),
  (17, 'BANK_DETAILS', 'Bank Account Details', 'Cancelled cheque or account statement for DBT', 'Bank', 1, 0, 1, 17, 1),
  (18, 'DISABILITY_CERT', 'Disability Certificate', 'Medical board certificate of benchmark disability', 'Health', 1, 1, 0, 18, 1),
  (19, 'UDID', 'UDID Card', 'Unique Disability ID card', 'Health', 1, 1, 0, 19, 1),
  (20, 'MEDICAL_RECORD', 'Medical Records', 'Prescriptions, diagnostic reports and discharge summaries', 'Health', 0, 0, 0, 20, 1),
  (21, 'PREGNANCY_PROOF', 'Pregnancy Proof / MCP Card', 'Mother and Child Protection card or pregnancy test report', 'Health', 0, 0, 0, 21, 1),
  (22, 'ABHA', 'ABHA (Health ID)', 'Ayushman Bharat Health Account number', 'Health', 1, 0, 1, 22, 1),
  (23, 'LAND_RECORD', 'Land Records', 'Khatian, patta, RoR or equivalent land record', 'Land', 1, 0, 0, 23, 1),
  (24, 'KISAN_CARD', 'Kisan Credit Card', 'KCC issued against agricultural holding', 'Land', 1, 1, 0, 24, 1),
  (25, 'SOIL_CARD', 'Soil Health Card', 'Soil test card issued to the holding', 'Land', 1, 1, 0, 25, 1),
  (26, 'SCHOOL_ID', 'School / College ID Card', 'Institutional identity card of the student', 'Education', 1, 1, 0, 26, 1),
  (27, 'MARKSHEET', 'Previous Year Marksheet', 'Last examination result of the student', 'Education', 0, 0, 0, 27, 1),
  (28, 'BONAFIDE', 'Bonafide Student Certificate', 'Institution certificate of current enrolment', 'Education', 0, 1, 0, 28, 1),
  (29, 'FEE_RECEIPT', 'Fee Receipt', 'Receipt of fees paid to the institution', 'Education', 0, 0, 0, 29, 1),
  (30, 'MARRIAGE_CERT', 'Marriage Certificate', 'Registered marriage certificate', 'Certificate', 1, 0, 0, 30, 1),
  (31, 'WIDOW_CERT', 'Widow Certificate', 'Certificate of widowhood from the competent authority', 'Certificate', 1, 0, 0, 31, 1),
  (32, 'WEAVER_ID', 'Weaver ID Card', 'Handloom weaver identity card', 'Other', 1, 1, 0, 32, 1),
  (33, 'WORKER_ID', 'Worker ID / BOCW Registration Card', 'Welfare board registration for construction workers', 'Other', 1, 1, 0, 33, 1),
  (34, 'ESHRAM', 'e-Shram Card', 'Unorganised worker registration under e-Shram', 'Other', 1, 0, 0, 34, 1),
  (35, 'JOB_CARD', 'MGNREGA Job Card', 'Household job card under MGNREGA', 'Other', 1, 0, 0, 35, 1),
  (36, 'ELECTRICITY', 'Electricity Bill', 'Latest electricity bill of the household', 'Address', 0, 0, 0, 36, 1),
  (37, 'PPO', 'Pension Payment Order', 'Existing pension sanction order, where applicable', 'Other', 1, 0, 0, 37, 1),
  (38, 'SELF_DECL', 'Self Declaration', 'Signed declaration by the applicant', 'Other', 0, 0, 0, 38, 1),
  (39, 'GUARDIAN_CONSENT', 'Guardian Consent', 'Consent of parent or guardian for a minor', 'Other', 0, 0, 0, 39, 1),
  (40, 'FIR_COPY', 'FIR / Police Report Copy', 'Required for victim compensation and protection schemes', 'Other', 1, 0, 0, 40, 1),
  (41, 'ENTERPRISE_DOC', 'Enterprise / Udyam Registration', 'Udyam or shop-establishment registration of the enterprise', 'Other', 1, 1, 0, 41, 1),
  (42, 'OTHER', 'Other Supporting Document', 'Any other document required by the department', 'Other', 0, 0, 0, 42, 1);

-- ---------------------------------------------------------------
-- Application statuses (pipeline)
-- ---------------------------------------------------------------
INSERT INTO `application_statuses` (`id`, `code`, `name`, `stage_order`, `funnel_stage`, `color`, `is_terminal`, `is_active`) VALUES
  (1, 'IDENTIFIED', 'Identified', 1, 'Identified', '#6c757d', 0, 1),
  (2, 'ELIGIBILITY_CHECKED', 'Eligibility Checked', 2, 'Eligible', '#0dcaf0', 0, 1),
  (3, 'DOCUMENTS_PENDING', 'Documents Pending', 3, 'Eligible', '#ffc107', 0, 1),
  (4, 'READY_TO_APPLY', 'Ready to Apply', 4, 'Eligible', '#20c997', 0, 1),
  (5, 'INITIATED', 'Application Initiated', 5, 'Initiated', '#0d6efd', 0, 1),
  (6, 'SUBMITTED', 'Application Submitted', 6, 'Submitted', '#6610f2', 0, 1),
  (7, 'UNDER_VERIFICATION', 'Under Verification', 7, 'Submitted', '#6f42c1', 0, 1),
  (8, 'APPROVED', 'Approved', 8, 'Approved', '#198754', 0, 1),
  (9, 'REJECTED', 'Rejected', 9, 'Rejected', '#dc3545', 1, 1),
  (10, 'BENEFIT_RECEIVED', 'Benefit Received', 10, 'Benefit', '#146c43', 1, 1),
  (11, 'CLOSED', 'Closed', 11, 'Closed', '#495057', 1, 1);

-- ---------------------------------------------------------------
-- Configurable dropdown options (123 across 17 lookups)
-- New dropdowns need no schema change: add rows with a new lookup_key.
-- ---------------------------------------------------------------
INSERT INTO `lookup_options` (`id`, `lookup_key`, `code`, `label`, `numeric_value`, `sort_order`, `is_active`) VALUES
  (1, 'gender', 'Male', 'Male', NULL, 1, 1),
  (2, 'gender', 'Female', 'Female', NULL, 2, 1),
  (3, 'gender', 'Other', 'Other', NULL, 3, 1),
  (4, 'area_type', 'Rural', 'Rural', NULL, 1, 1),
  (5, 'area_type', 'Urban', 'Urban', NULL, 2, 1),
  (6, 'yes_no_unknown', 'Yes', 'Yes', 1, 1, 1),
  (7, 'yes_no_unknown', 'No', 'No', 0, 2, 1),
  (8, 'yes_no_unknown', 'Unknown', 'Not Known', NULL, 3, 1),
  (9, 'social_category', 'GEN', 'General', 1, 1, 1),
  (10, 'social_category', 'OBC', 'Other Backward Class (OBC)', 2, 2, 1),
  (11, 'social_category', 'SC', 'Scheduled Caste (SC)', 3, 3, 1),
  (12, 'social_category', 'ST', 'Scheduled Tribe (ST)', 4, 4, 1),
  (13, 'social_category', 'EWS', 'Economically Weaker Section', 5, 5, 1),
  (14, 'social_category', 'MIN', 'Minority Community', 6, 6, 1),
  (15, 'religion', 'HINDU', 'Hindu', NULL, 1, 1),
  (16, 'religion', 'MUSLIM', 'Muslim', NULL, 2, 1),
  (17, 'religion', 'CHRISTIAN', 'Christian', NULL, 3, 1),
  (18, 'religion', 'SIKH', 'Sikh', NULL, 4, 1),
  (19, 'religion', 'BUDDHIST', 'Buddhist', NULL, 5, 1),
  (20, 'religion', 'JAIN', 'Jain', NULL, 6, 1),
  (21, 'religion', 'PARSI', 'Parsi', NULL, 7, 1),
  (22, 'religion', 'OTHER', 'Other', NULL, 8, 1),
  (23, 'marital_status', 'UNMARRIED', 'Unmarried', NULL, 1, 1),
  (24, 'marital_status', 'MARRIED', 'Married', NULL, 2, 1),
  (25, 'marital_status', 'WIDOWED', 'Widowed', NULL, 3, 1),
  (26, 'marital_status', 'DIVORCED', 'Divorced', NULL, 4, 1),
  (27, 'marital_status', 'SEPARATED', 'Separated', NULL, 5, 1),
  (28, 'marital_status', 'DESERTED', 'Deserted', NULL, 6, 1),
  (29, 'education_level', 'NONE', 'No Formal Education', 0, 1, 1),
  (30, 'education_level', 'PRIMARY', 'Primary (Class 1-5)', 1, 2, 1),
  (31, 'education_level', 'UPPER_PRI', 'Upper Primary (Class 6-8)', 2, 3, 1),
  (32, 'education_level', 'SECONDARY', 'Secondary (Class 9-10)', 3, 4, 1),
  (33, 'education_level', 'HIGHER_SEC', 'Higher Secondary (11-12)', 4, 5, 1),
  (34, 'education_level', 'ITI', 'ITI / Diploma', 5, 6, 1),
  (35, 'education_level', 'GRADUATE', 'Graduate', 6, 7, 1),
  (36, 'education_level', 'POST_GRAD', 'Post Graduate', 7, 8, 1),
  (37, 'education_level', 'PROF', 'Professional Degree', 8, 9, 1),
  (38, 'occupation', 'AGRI_LABOUR', 'Agricultural Labourer', NULL, 1, 1),
  (39, 'occupation', 'CULTIVATOR', 'Cultivator / Farmer', NULL, 2, 1),
  (40, 'occupation', 'DAILY_WAGE', 'Daily Wage Labourer', NULL, 3, 1),
  (41, 'occupation', 'CONSTRUCTION', 'Construction Worker', NULL, 4, 1),
  (42, 'occupation', 'DOMESTIC', 'Domestic Worker', NULL, 5, 1),
  (43, 'occupation', 'STREET_VENDOR', 'Street Vendor', NULL, 6, 1),
  (44, 'occupation', 'ARTISAN', 'Artisan / Weaver', NULL, 7, 1),
  (45, 'occupation', 'FISHERMAN', 'Fisherman', NULL, 8, 1),
  (46, 'occupation', 'SMALL_BUSINESS', 'Small Business / Shop', NULL, 9, 1),
  (47, 'occupation', 'PRIVATE_JOB', 'Salaried - Private', NULL, 10, 1),
  (48, 'occupation', 'GOVT_JOB', 'Salaried - Government', NULL, 11, 1),
  (49, 'occupation', 'SELF_EMPLOYED', 'Self-employed / Own Account', NULL, 12, 1),
  (50, 'occupation', 'STUDENT', 'Student', NULL, 13, 1),
  (51, 'occupation', 'HOMEMAKER', 'Homemaker', NULL, 14, 1),
  (52, 'occupation', 'UNEMPLOYED', 'Unemployed', NULL, 15, 1),
  (53, 'occupation', 'RETIRED', 'Retired', NULL, 16, 1),
  (54, 'occupation', 'UNABLE', 'Unable to Work', NULL, 17, 1),
  (55, 'occupation', 'OTHER', 'Other', NULL, 18, 1),
  (56, 'employment_type', 'REGULAR', 'Regular Salaried', NULL, 1, 1),
  (57, 'employment_type', 'CASUAL', 'Casual / Daily Wage', NULL, 2, 1),
  (58, 'employment_type', 'SELF', 'Self-employed', NULL, 3, 1),
  (59, 'employment_type', 'CONTRACT', 'Contractual', NULL, 4, 1),
  (60, 'employment_type', 'FAMILY', 'Unpaid Family Worker', NULL, 5, 1),
  (61, 'employment_type', 'UNEMPLOYED', 'Unemployed', NULL, 6, 1),
  (62, 'employment_type', 'NOT_IN_LF', 'Not in Labour Force', NULL, 7, 1),
  (63, 'disability_status', 'NONE', 'No Disability', NULL, 1, 1),
  (64, 'disability_status', 'LOCOMOTOR', 'Locomotor Disability', NULL, 2, 1),
  (65, 'disability_status', 'VISUAL', 'Visual Impairment', NULL, 3, 1),
  (66, 'disability_status', 'HEARING', 'Hearing Impairment', NULL, 4, 1),
  (67, 'disability_status', 'SPEECH', 'Speech and Language', NULL, 5, 1),
  (68, 'disability_status', 'INTELLECTUAL', 'Intellectual Disability', NULL, 6, 1),
  (69, 'disability_status', 'MENTAL', 'Mental Illness', NULL, 7, 1),
  (70, 'disability_status', 'MULTIPLE', 'Multiple Disabilities', NULL, 8, 1),
  (71, 'disability_status', 'CHRONIC', 'Chronic Illness', NULL, 9, 1),
  (72, 'disability_status', 'OTHER', 'Other', NULL, 10, 1),
  (73, 'ration_card_type', 'AAY', 'AAY (Antyodaya)', 1, 1, 1),
  (74, 'ration_card_type', 'PHH', 'PHH (Priority Household)', 2, 2, 1),
  (75, 'ration_card_type', 'SFSS', 'State Food Security Card', 3, 3, 1),
  (76, 'ration_card_type', 'APL', 'APL (Above Poverty Line)', 4, 4, 1),
  (77, 'ration_card_type', 'NONE', 'No Ration Card', 0, 5, 1),
  (78, 'house_type', 'KUTCHA', 'Kutcha', 1, 1, 1),
  (79, 'house_type', 'SEMI_PUCCA', 'Semi-Pucca', 2, 2, 1),
  (80, 'house_type', 'PUCCA', 'Pucca', 3, 3, 1),
  (81, 'house_type', 'HOMELESS', 'Homeless', 0, 4, 1),
  (82, 'farmer_category', 'NOT_FARMER', 'Not a Farmer', 0, 1, 1),
  (83, 'farmer_category', 'LANDLESS', 'Landless Agricultural Worker', 1, 2, 1),
  (84, 'farmer_category', 'MARGINAL', 'Marginal (below 1 hectare)', 2, 3, 1),
  (85, 'farmer_category', 'SMALL', 'Small (1-2 hectares)', 3, 4, 1),
  (86, 'farmer_category', 'SEMI_MEDIUM', 'Semi-Medium (2-4 hectares)', 4, 5, 1),
  (87, 'farmer_category', 'MEDIUM', 'Medium (4-10 hectares)', 5, 6, 1),
  (88, 'farmer_category', 'LARGE', 'Large (above 10 hectares)', 6, 7, 1),
  (89, 'worker_category', 'NOT_WORKER', 'Not a Worker', NULL, 1, 1),
  (90, 'worker_category', 'BOCW', 'Construction Worker (BOCW)', NULL, 2, 1),
  (91, 'worker_category', 'INFORMAL', 'Unorganised / Informal Worker', NULL, 3, 1),
  (92, 'worker_category', 'WEAVER', 'Handloom Weaver', NULL, 4, 1),
  (93, 'worker_category', 'DOMESTIC', 'Domestic Worker', NULL, 5, 1),
  (94, 'worker_category', 'VENDOR', 'Street Vendor', NULL, 6, 1),
  (95, 'worker_category', 'AGRI', 'Agricultural Worker', NULL, 7, 1),
  (96, 'worker_category', 'SANITATION', 'Sanitation Worker', NULL, 8, 1),
  (97, 'worker_category', 'TEA_GARDEN', 'Tea Garden Worker', NULL, 9, 1),
  (98, 'worker_category', 'MIGRANT', 'Migrant Worker', NULL, 10, 1),
  (99, 'worker_category', 'SELF_EMP', 'Self-employed Worker', NULL, 11, 1),
  (100, 'worker_category', 'OTHER', 'Other', NULL, 12, 1),
  (101, 'income_source', 'AGRICULTURE', 'Agriculture', NULL, 1, 1),
  (102, 'income_source', 'WAGE_LABOUR', 'Wage Labour', NULL, 2, 1),
  (103, 'income_source', 'SALARY', 'Salary', NULL, 3, 1),
  (104, 'income_source', 'BUSINESS', 'Business / Trade', NULL, 4, 1),
  (105, 'income_source', 'PENSION', 'Pension', NULL, 5, 1),
  (106, 'income_source', 'REMITTANCE', 'Remittance', NULL, 6, 1),
  (107, 'income_source', 'LIVESTOCK', 'Livestock', NULL, 7, 1),
  (108, 'income_source', 'MIXED', 'Multiple Sources', NULL, 8, 1),
  (109, 'income_source', 'NONE', 'No Regular Income', NULL, 9, 1),
  (110, 'water_source', 'PIPED', 'Piped Water Supply', NULL, 1, 1),
  (111, 'water_source', 'HANDPUMP', 'Hand Pump', NULL, 2, 1),
  (112, 'water_source', 'TUBEWELL', 'Tube Well / Borewell', NULL, 3, 1),
  (113, 'water_source', 'WELL', 'Open Well', NULL, 4, 1),
  (114, 'water_source', 'POND', 'Pond / River', NULL, 5, 1),
  (115, 'water_source', 'TANKER', 'Tanker Supply', NULL, 6, 1),
  (116, 'water_source', 'OTHER', 'Other', NULL, 7, 1),
  (117, 'followup_type', 'CALL', 'Phone Call', NULL, 1, 1),
  (118, 'followup_type', 'HOME', 'Home Visit', NULL, 2, 1),
  (119, 'followup_type', 'DEPT', 'Department Visit', NULL, 3, 1),
  (120, 'followup_type', 'DOC', 'Document Collection', NULL, 4, 1),
  (121, 'followup_type', 'SMS', 'SMS / WhatsApp', NULL, 5, 1),
  (122, 'followup_type', 'CAMP', 'Camp', NULL, 6, 1),
  (123, 'followup_type', 'OTHER', 'Other', NULL, 7, 1);

-- ---------------------------------------------------------------
-- Eligibility attribute vocabulary (57) used by the rule builder
-- ---------------------------------------------------------------
INSERT INTO `eligibility_attributes` (`id`, `attribute_key`, `label`, `data_type`, `lookup_key`, `source`, `source_column`, `unit`, `group_label`, `description`, `allowed_operators`, `sort_order`, `is_active`) VALUES
  (1, 'age', 'Age', 'int', NULL, 'beneficiary', 'age', 'years', 'Demographic', 'Age of the beneficiary in completed years', 'eq,neq,gt,lt,gte,lte,between', 1, 1),
  (2, 'gender', 'Gender', 'enum', 'gender', 'beneficiary', 'gender', NULL, 'Demographic', 'Gender recorded at registration', 'eq,neq,any_of', 2, 1),
  (3, 'marital_status', 'Marital Status', 'enum', 'marital_status', 'assessment', NULL, NULL, 'Demographic', 'Current marital status', 'eq,neq,any_of', 3, 1),
  (4, 'social_category', 'Caste / Social Category', 'enum', 'social_category', 'assessment', NULL, NULL, 'Demographic', 'Scheduled Caste, Scheduled Tribe, OBC, General, EWS or Minority', 'eq,neq,any_of,not_in', 4, 1),
  (5, 'religion', 'Religion', 'enum', 'religion', 'assessment', NULL, NULL, 'Demographic', 'Religion, used by minority-specific schemes', 'eq,neq,any_of', 5, 1),
  (6, 'is_minority', 'Belongs to Minority Community', 'bool', NULL, 'derived', NULL, NULL, 'Demographic', 'Derived from social category or religion', 'eq', 6, 1),
  (7, 'is_transgender', 'Transgender Person', 'bool', NULL, 'derived', NULL, NULL, 'Demographic', 'Derived from gender recorded as Other with a transgender declaration', 'eq', 7, 1),
  (8, 'state_id', 'State', 'int', NULL, 'geo', 'state_id', NULL, 'Geography', 'State of residence', 'eq,neq,any_of', 8, 1),
  (9, 'district_id', 'District', 'int', NULL, 'geo', 'district_id', NULL, 'Geography', 'District of residence', 'eq,neq,any_of', 9, 1),
  (10, 'area_type', 'Rural / Urban', 'enum', 'area_type', 'beneficiary', 'area_type', NULL, 'Geography', 'Whether the household is rural or urban', 'eq,neq', 10, 1),
  (11, 'annual_income', 'Annual Family Income', 'decimal', NULL, 'household', 'annual_income', 'INR', 'Economic', 'Total annual income of the household from all sources', 'eq,gt,lt,gte,lte,between', 11, 1),
  (12, 'family_size', 'Family Size', 'int', NULL, 'household', 'family_size', 'members', 'Economic', 'Number of members in the household', 'eq,gt,lt,gte,lte,between', 12, 1),
  (13, 'bpl_status', 'BPL Status', 'enum', 'yes_no_unknown', 'household', 'bpl_status', NULL, 'Economic', 'Whether the household is below the poverty line', 'eq,neq', 13, 1),
  (14, 'ration_card_type', 'Ration Card Type', 'enum', 'ration_card_type', 'household', 'ration_card_type', NULL, 'Economic', 'Type of PDS card held by the household', 'eq,neq,any_of,is_set,not_set', 14, 1),
  (15, 'has_ration_card', 'Holds a Ration Card', 'bool', NULL, 'derived', NULL, NULL, 'Economic', 'Derived from the ration card type', 'eq', 15, 1),
  (16, 'house_type', 'House Type', 'enum', 'house_type', 'assessment', NULL, NULL, 'Economic', 'Kutcha, semi-pucca, pucca or homeless', 'eq,neq,any_of', 16, 1),
  (17, 'owns_house', 'Owns a House', 'bool', NULL, 'assessment', NULL, NULL, 'Economic', 'Whether the household owns its dwelling', 'eq', 17, 1),
  (18, 'has_electricity', 'Electricity Connection', 'bool', NULL, 'household', 'has_electricity', NULL, 'Economic', 'Household has a working electricity connection', 'eq', 18, 1),
  (19, 'has_toilet', 'Toilet Facility', 'bool', NULL, 'household', 'has_toilet', NULL, 'Economic', 'Household has access to a toilet', 'eq', 19, 1),
  (20, 'occupation', 'Occupation', 'enum', 'occupation', 'assessment', NULL, NULL, 'Livelihood', 'Primary occupation of the beneficiary', 'eq,neq,any_of,not_in', 20, 1),
  (21, 'employment_type', 'Employment Type', 'enum', 'employment_type', 'assessment', NULL, NULL, 'Livelihood', 'Nature of employment', 'eq,neq,any_of', 21, 1),
  (22, 'farmer_category', 'Farmer Category', 'enum', 'farmer_category', 'assessment', NULL, NULL, 'Livelihood', 'Land-holding class of the farming household', 'eq,neq,any_of', 22, 1),
  (23, 'land_holding', 'Land Holding', 'decimal', NULL, 'household', 'land_holding_acres', 'acres', 'Livelihood', 'Total agricultural land held, in acres', 'eq,gt,lt,gte,lte,between', 23, 1),
  (24, 'worker_category', 'Worker Category', 'enum', 'worker_category', 'assessment', NULL, NULL, 'Livelihood', 'Category of worker for welfare-board schemes', 'eq,neq,any_of', 24, 1),
  (25, 'is_farmer', 'Is a Farmer', 'bool', NULL, 'derived', NULL, NULL, 'Livelihood', 'Derived from occupation or farmer category', 'eq', 25, 1),
  (26, 'handloom_weaver', 'Handloom Weaver', 'bool', NULL, 'assessment', NULL, NULL, 'Livelihood', 'Registered or practising handloom weaver', 'eq', 26, 1),
  (27, 'tea_garden_worker', 'Tea Garden Worker', 'bool', NULL, 'assessment', NULL, NULL, 'Livelihood', 'Member of a tea garden or ex-tea garden community', 'eq', 27, 1),
  (28, 'has_job_card', 'MGNREGA Job Card', 'bool', NULL, 'assessment', NULL, NULL, 'Livelihood', 'Household holds an MGNREGA job card', 'eq', 28, 1),
  (29, 'has_eshram', 'e-Shram Registration', 'bool', NULL, 'assessment', NULL, NULL, 'Livelihood', 'Registered on the e-Shram portal', 'eq', 29, 1),
  (30, 'education_level', 'Education Level', 'enum', 'education_level', 'assessment', NULL, NULL, 'Education', 'Highest level of education completed', 'eq,neq,gte,lte,any_of', 30, 1),
  (31, 'is_student', 'Currently Studying', 'bool', NULL, 'assessment', NULL, NULL, 'Education', 'Enrolled in an educational institution', 'eq', 31, 1),
  (32, 'current_class', 'Current Class / Year', 'int', NULL, 'assessment', NULL, NULL, 'Education', 'Class or year of study for students', 'eq,gt,lt,gte,lte,between', 32, 1),
  (33, 'disability_status', 'Disability Status', 'enum', 'disability_status', 'assessment', NULL, NULL, 'Health', 'Type of disability or chronic illness, if any', 'eq,neq,any_of,not_in', 33, 1),
  (34, 'disability_percent', 'Disability Percentage', 'int', NULL, 'assessment', NULL, '%', 'Health', 'Assessed disability percentage as per certificate', 'eq,gt,lt,gte,lte,between', 34, 1),
  (35, 'has_disability', 'Has a Disability', 'bool', NULL, 'derived', NULL, NULL, 'Health', 'Derived from disability status', 'eq', 35, 1),
  (36, 'pregnancy_status', 'Currently Pregnant', 'bool', NULL, 'assessment', NULL, NULL, 'Health', 'Woman is currently pregnant', 'eq', 36, 1),
  (37, 'lactating_status', 'Lactating Mother', 'bool', NULL, 'assessment', NULL, NULL, 'Health', 'Woman is breastfeeding a child under two years', 'eq', 37, 1),
  (38, 'has_abha', 'Holds ABHA Health ID', 'bool', NULL, 'assessment', NULL, NULL, 'Health', 'Ayushman Bharat Health Account created', 'eq', 38, 1),
  (39, 'chronic_illness', 'Chronic Illness in Household', 'bool', NULL, 'assessment', NULL, NULL, 'Health', 'Any household member has a long-term illness needing care', 'eq', 39, 1),
  (40, 'widow_status', 'Widow', 'bool', NULL, 'assessment', NULL, NULL, 'Vulnerability', 'Woman who has lost her spouse', 'eq', 40, 1),
  (41, 'senior_citizen', 'Senior Citizen', 'bool', NULL, 'derived', NULL, NULL, 'Vulnerability', 'Derived: age 60 years or above', 'eq', 41, 1),
  (42, 'is_orphan', 'Orphan / Lost a Parent', 'bool', NULL, 'assessment', NULL, NULL, 'Vulnerability', 'Child who has lost one or both parents', 'eq', 42, 1),
  (43, 'single_woman_household', 'Woman-headed Household', 'bool', NULL, 'assessment', NULL, NULL, 'Vulnerability', 'Household headed by a woman with no adult male earner', 'eq', 43, 1),
  (44, 'children_under_6', 'Children Under 6 Years', 'int', NULL, 'assessment', NULL, 'children', 'Vulnerability', 'Number of children below six years in the household', 'eq,gt,lt,gte,lte,between', 44, 1),
  (45, 'girl_child_count', 'Number of Girl Children', 'int', NULL, 'assessment', NULL, 'children', 'Vulnerability', 'Number of daughters in the household', 'eq,gt,lt,gte,lte,between', 45, 1),
  (46, 'violence_survivor', 'Survivor of Violence or Crime', 'bool', NULL, 'assessment', NULL, NULL, 'Vulnerability', 'Recorded survivor of domestic violence, assault or trafficking', 'eq', 46, 1),
  (47, 'has_aadhaar', 'Holds Aadhaar', 'bool', NULL, 'beneficiary', 'has_aadhaar', NULL, 'Documents', 'Aadhaar number available for the beneficiary', 'eq', 47, 1),
  (48, 'has_bank_account', 'Bank Account Available', 'bool', NULL, 'assessment', NULL, NULL, 'Documents', 'Beneficiary holds an operative bank account', 'eq', 48, 1),
  (49, 'bank_aadhaar_linked', 'Bank Account Aadhaar-linked', 'bool', NULL, 'assessment', NULL, NULL, 'Documents', 'Account is seeded with Aadhaar for DBT', 'eq', 49, 1),
  (50, 'has_income_certificate', 'Income Certificate Available', 'bool', NULL, 'assessment', NULL, NULL, 'Documents', 'Valid income certificate in hand', 'eq', 50, 1),
  (51, 'has_caste_certificate', 'Caste Certificate Available', 'bool', NULL, 'assessment', NULL, NULL, 'Documents', 'Valid caste certificate in hand', 'eq', 51, 1),
  (52, 'has_disability_certificate', 'Disability Certificate / UDID Available', 'bool', NULL, 'assessment', NULL, NULL, 'Documents', 'Valid disability certificate or UDID card in hand', 'eq', 52, 1),
  (53, 'existing_benefits', 'Existing Government Benefits', 'string', NULL, 'assessment', NULL, NULL, 'Existing Coverage', 'Schemes the household already receives', 'contains,is_set,not_set', 53, 1),
  (54, 'receives_pension', 'Already Receives a Pension', 'bool', NULL, 'assessment', NULL, NULL, 'Existing Coverage', 'Any social security pension already drawn', 'eq', 54, 1),
  (55, 'has_health_insurance', 'Covered by Health Insurance', 'bool', NULL, 'assessment', NULL, NULL, 'Existing Coverage', 'Already enrolled in a government health insurance scheme', 'eq', 55, 1),
  (56, 'income_tax_payer', 'Income Tax Payer', 'bool', NULL, 'assessment', NULL, NULL, 'Existing Coverage', 'Files income tax returns - excluded by several schemes', 'eq', 56, 1),
  (57, 'govt_employee', 'Government Employee in Family', 'bool', NULL, 'assessment', NULL, NULL, 'Existing Coverage', 'Any household member in regular government service', 'eq', 57, 1);

-- ---------------------------------------------------------------
-- Dynamic assessment questionnaire (38 questions)
-- Adding a question here makes it appear on the assessment form and,
-- when feeds_eligibility = 1, available to the matching engine.
-- ---------------------------------------------------------------
INSERT INTO `assessment_questions` (`id`, `section`, `question_key`, `label`, `help_text`, `input_type`, `lookup_key`, `unit`, `is_required`, `feeds_eligibility`, `sort_order`, `is_active`) VALUES
  (1, 'Socio-Economic', 'social_category', 'Caste / Social Category', 'As recorded on the caste certificate, where held', 'select', 'social_category', NULL, 1, 1, 1, 1),
  (2, 'Socio-Economic', 'religion', 'Religion', 'Used only for minority-specific schemes', 'select', 'religion', NULL, 0, 1, 2, 1),
  (3, 'Socio-Economic', 'marital_status', 'Marital Status', NULL, 'select', 'marital_status', NULL, 1, 1, 3, 1),
  (4, 'Socio-Economic', 'education_level', 'Highest Education Completed', NULL, 'select', 'education_level', NULL, 1, 1, 4, 1),
  (5, 'Socio-Economic', 'is_student', 'Currently Studying?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 5, 1),
  (6, 'Socio-Economic', 'current_class', 'Current Class / Year of Study', 'Leave blank if not studying', 'number', NULL, 'class', 0, 1, 6, 1),
  (7, 'Livelihood', 'occupation', 'Primary Occupation', NULL, 'select', 'occupation', NULL, 1, 1, 7, 1),
  (8, 'Livelihood', 'employment_type', 'Employment Type', NULL, 'select', 'employment_type', NULL, 0, 1, 8, 1),
  (9, 'Livelihood', 'farmer_category', 'Farmer Category', 'Select "Not a Farmer" where agriculture is not the livelihood', 'select', 'farmer_category', NULL, 0, 1, 9, 1),
  (10, 'Livelihood', 'worker_category', 'Worker Category', 'Determines welfare-board eligibility', 'select', 'worker_category', NULL, 0, 1, 10, 1),
  (11, 'Livelihood', 'handloom_weaver', 'Handloom Weaver?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 11, 1),
  (12, 'Livelihood', 'tea_garden_worker', 'Tea Garden Community?', 'Relevant for Assam tea-garden schemes', 'radio', 'yes_no_unknown', NULL, 0, 1, 12, 1),
  (13, 'Livelihood', 'has_job_card', 'Holds an MGNREGA Job Card?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 13, 1),
  (14, 'Livelihood', 'has_eshram', 'Registered on e-Shram?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 14, 1),
  (15, 'Health & Disability', 'disability_status', 'Disability / Chronic Illness', 'Select "No Disability" where none applies', 'select', 'disability_status', NULL, 1, 1, 15, 1),
  (16, 'Health & Disability', 'disability_percent', 'Disability Percentage', 'As stated on the disability certificate', 'number', NULL, '%', 0, 1, 16, 1),
  (17, 'Health & Disability', 'has_disability_certificate', 'Disability Certificate / UDID in hand?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 17, 1),
  (18, 'Health & Disability', 'pregnancy_status', 'Currently Pregnant?', 'Ask only where applicable', 'radio', 'yes_no_unknown', NULL, 0, 1, 18, 1),
  (19, 'Health & Disability', 'lactating_status', 'Lactating Mother?', 'Child under two years', 'radio', 'yes_no_unknown', NULL, 0, 1, 19, 1),
  (20, 'Health & Disability', 'chronic_illness', 'Chronic Illness in the Household?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 20, 1),
  (21, 'Health & Disability', 'has_abha', 'Holds an ABHA Health ID?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 21, 1),
  (22, 'Vulnerability', 'widow_status', 'Widow?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 22, 1),
  (23, 'Vulnerability', 'is_orphan', 'Orphan or Lost a Parent?', 'For child beneficiaries', 'radio', 'yes_no_unknown', NULL, 0, 1, 23, 1),
  (24, 'Vulnerability', 'single_woman_household', 'Woman-headed Household?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 24, 1),
  (25, 'Vulnerability', 'children_under_6', 'Children Under 6 Years', NULL, 'number', NULL, 'children', 0, 1, 25, 1),
  (26, 'Vulnerability', 'girl_child_count', 'Number of Girl Children', NULL, 'number', NULL, 'children', 0, 1, 26, 1),
  (27, 'Vulnerability', 'violence_survivor', 'Survivor of Violence or Crime?', 'Record with consent only', 'radio', 'yes_no_unknown', NULL, 0, 1, 27, 1),
  (28, 'Housing & Assets', 'house_type', 'House Type', NULL, 'select', 'house_type', NULL, 0, 1, 28, 1),
  (29, 'Housing & Assets', 'owns_house', 'Owns the House?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 29, 1),
  (30, 'Financial Inclusion', 'has_bank_account', 'Bank Account Available?', NULL, 'radio', 'yes_no_unknown', NULL, 1, 1, 30, 1),
  (31, 'Financial Inclusion', 'bank_aadhaar_linked', 'Bank Account Linked to Aadhaar?', 'Required for Direct Benefit Transfer', 'radio', 'yes_no_unknown', NULL, 0, 1, 31, 1),
  (32, 'Financial Inclusion', 'has_income_certificate', 'Income Certificate in hand?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 32, 1),
  (33, 'Financial Inclusion', 'has_caste_certificate', 'Caste Certificate in hand?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 33, 1),
  (34, 'Existing Coverage', 'receives_pension', 'Already Receives a Pension?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 34, 1),
  (35, 'Existing Coverage', 'has_health_insurance', 'Covered by Government Health Insurance?', 'Ayushman Bharat, State health scheme etc.', 'radio', 'yes_no_unknown', NULL, 0, 1, 35, 1),
  (36, 'Existing Coverage', 'income_tax_payer', 'Income Tax Payer?', 'Several schemes exclude tax payers', 'radio', 'yes_no_unknown', NULL, 0, 1, 36, 1),
  (37, 'Existing Coverage', 'govt_employee', 'Government Employee in the Family?', NULL, 'radio', 'yes_no_unknown', NULL, 0, 1, 37, 1),
  (38, 'Existing Coverage', 'existing_benefits', 'Schemes Already Being Received', 'List the schemes the household already benefits from', 'textarea', NULL, NULL, 0, 1, 38, 1);

-- ---------------------------------------------------------------
-- System settings (24)
-- ---------------------------------------------------------------
INSERT INTO `system_settings` (`id`, `setting_group`, `setting_key`, `setting_value`, `value_type`, `label`, `description`, `is_editable`, `sort_order`) VALUES
  (1, 'general', 'app_display_name', 'Jan Kavach', 'string', 'Application Name', 'Shown in the header and on the login page', 1, 1),
  (2, 'general', 'app_tagline', 'Connecting Citizens with Social Security Schemes', 'string', 'Tagline', 'Shown under the application name', 1, 2),
  (3, 'general', 'organisation_name', 'Social Protection Programme', 'string', 'Organisation Name', 'Owner organisation shown in reports', 1, 3),
  (4, 'general', 'support_email', 'support@jankavach.local', 'string', 'Support Email', 'Displayed to users who need help', 1, 4),
  (5, 'general', 'support_phone', '1800-000-0000', 'string', 'Support Helpline', 'Helpline shown in the footer', 1, 5),
  (6, 'matching', 'eligible_threshold', '90', 'int', 'Eligible Threshold (%)', 'Match percentage at or above which a scheme is graded "Eligible"', 1, 6),
  (7, 'matching', 'potential_threshold', '60', 'int', 'Potentially Eligible Threshold (%)', 'Match percentage at or above which a scheme is graded "Potentially Eligible"', 1, 7),
  (8, 'matching', 'store_not_eligible', '1', 'bool', 'Record Non-matching Schemes', 'Keep negative results so the reason a beneficiary does not qualify can be shown', 1, 8),
  (9, 'matching', 'auto_match_on_assessment', '1', 'bool', 'Run Matching After Assessment', 'Automatically identify schemes once an assessment is marked complete', 1, 9),
  (10, 'followup', 'default_followup_days', '15', 'int', 'Default Follow-up Interval (days)', 'Suggested gap when scheduling the next follow-up', 1, 10),
  (11, 'followup', 'upcoming_window_days', '7', 'int', 'Upcoming Follow-up Window (days)', 'How far ahead the dashboard looks for upcoming follow-ups', 1, 11),
  (12, 'security', 'session_timeout_minutes', '60', 'int', 'Session Timeout (minutes)', 'Idle time after which a user is signed out', 1, 12),
  (13, 'security', 'max_login_attempts', '5', 'int', 'Maximum Login Attempts', 'Failed attempts allowed before the account is locked', 1, 13),
  (14, 'security', 'lockout_minutes', '15', 'int', 'Lockout Duration (minutes)', 'How long an account stays locked after too many failures', 1, 14),
  (15, 'security', 'mask_aadhaar_in_lists', '1', 'bool', 'Mask Aadhaar in Listings', 'Show only the last four digits of Aadhaar on list screens', 1, 15),
  (16, 'security', 'mask_mobile_in_lists', '1', 'bool', 'Mask Mobile Numbers in Listings', 'Partially hide mobile numbers on list screens', 1, 16),
  (17, 'upload', 'max_document_size_mb', '5', 'int', 'Maximum Document Size (MB)', 'Largest single beneficiary document that may be uploaded', 1, 17),
  (18, 'upload', 'allowed_extensions', 'pdf,jpg,jpeg,png', 'string', 'Allowed File Types', 'Comma-separated list of permitted document extensions', 1, 18),
  (19, 'notification', 'channel_inapp', '1', 'bool', 'In-app Notifications', 'Show notifications in the application header', 1, 19),
  (20, 'notification', 'channel_email', '0', 'bool', 'Email Notifications', 'Reserved for a future email integration', 1, 20),
  (21, 'notification', 'channel_sms', '0', 'bool', 'SMS Notifications', 'Reserved for a future SMS gateway integration', 1, 21),
  (22, 'notification', 'channel_whatsapp', '0', 'bool', 'WhatsApp Notifications', 'Reserved for a future WhatsApp Business integration', 1, 22),
  (23, 'report', 'export_row_limit', '20000', 'int', 'Export Row Limit', 'Largest number of rows a single export may contain', 1, 23),
  (24, 'report', 'footer_note', 'Generated from Jan Kavach - Social Protection MIS', 'string', 'Report Footer Note', 'Printed at the foot of exported reports', 1, 24);

-- ---------------------------------------------------------------
-- Implementing ministries and departments
-- ---------------------------------------------------------------
INSERT INTO `implementing_agencies` (`id`, `name`, `agency_type`, `level`, `state_id`, `is_active`) VALUES
  (1, 'Ministry of Women and Child Development', 'Ministry', 'National', NULL, 1),
  (2, 'Ministry of Health and Family Welfare', 'Ministry', 'National', NULL, 1),
  (3, 'Ministry of Education', 'Ministry', 'National', NULL, 1),
  (4, 'Ministry of Labour and Employment', 'Ministry', 'National', NULL, 1),
  (5, 'Ministry of Rural Development', 'Ministry', 'National', NULL, 1),
  (6, 'Ministry of Housing and Urban Affairs', 'Ministry', 'National', NULL, 1),
  (7, 'Ministry of Agriculture and Farmers Welfare', 'Ministry', 'National', NULL, 1),
  (8, 'Ministry of Consumer Affairs, Food and Public Distribution', 'Ministry', 'National', NULL, 1),
  (9, 'Ministry of Social Justice and Empowerment', 'Ministry', 'National', NULL, 1),
  (10, 'Ministry of Finance', 'Ministry', 'National', NULL, 1),
  (11, 'Ministry of Textiles', 'Ministry', 'National', NULL, 1),
  (12, 'Ministry of Minority Affairs', 'Ministry', 'National', NULL, 1),
  (13, 'Ministry of Skill Development and Entrepreneurship', 'Ministry', 'National', NULL, 1),
  (14, 'Ministry of Micro, Small and Medium Enterprises', 'Ministry', 'National', NULL, 1),
  (15, 'Ministry of Petroleum and Natural Gas', 'Ministry', 'National', NULL, 1),
  (16, 'Ministry of Law and Justice', 'Ministry', 'National', NULL, 1),
  (17, 'Department of Social Welfare', 'Department', 'State', NULL, 1),
  (18, 'Department of Women and Child Development', 'Department', 'State', NULL, 1),
  (19, 'Department of Health and Family Welfare', 'Department', 'State', NULL, 1),
  (20, 'Department of School Education', 'Department', 'State', NULL, 1),
  (21, 'Department of Labour', 'Department', 'State', NULL, 1),
  (22, 'Department of Agriculture', 'Department', 'State', NULL, 1),
  (23, 'Department of Food and Civil Supplies', 'Department', 'State', NULL, 1),
  (24, 'Department of Rural Development and Panchayati Raj', 'Department', 'State', NULL, 1),
  (25, 'Department of Minority Welfare', 'Department', 'State', NULL, 1),
  (26, 'Building and Other Construction Workers Welfare Board', 'Board', 'State', NULL, 1);

SET FOREIGN_KEY_CHECKS = 1;
