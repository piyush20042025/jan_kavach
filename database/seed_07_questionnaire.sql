-- =====================================================================
--  JAN KAVACH - SEED 07 : BENEFICIARY PROFILE QUESTIONNAIRE
--
--  Every lookup value, question and eligibility attribute here comes from
--  "Beneficiary Profile Questionnaire.docx". Questions the platform already
--  had (social_category, marital_status, occupation, has_bank_account,
--  girl_child_count, has_income_certificate, has_caste_certificate) are
--  reused and extended rather than duplicated, so answers already recorded
--  and scheme rules already written keep working.
--
--  Safe to re-run: every statement is an upsert.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Lookup option sets
-- ---------------------------------------------------------------------

INSERT INTO `lookup_options` (`lookup_key`,`code`,`label`,`numeric_value`,`sort_order`,`is_active`) VALUES
  ('yes_no','Yes','Yes',1,1,1),
  ('yes_no','No','No',0,2,1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `numeric_value`=VALUES(`numeric_value`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1;

INSERT INTO `lookup_options` (`lookup_key`,`code`,`label`,`numeric_value`,`sort_order`,`is_active`) VALUES
  ('yes_no_na','Yes','Yes',1,1,1),
  ('yes_no_na','No','No',0,2,1),
  ('yes_no_na','NA','Not Applicable',NULL,3,1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `numeric_value`=VALUES(`numeric_value`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1;

INSERT INTO `lookup_options` (`lookup_key`,`code`,`label`,`numeric_value`,`sort_order`,`is_active`) VALUES
  ('earning_status','YES','Yes',1,1,1),
  ('earning_status','NO','No',0,2,1),
  ('earning_status','UNEMPLOYED','Unemployed',0,3,1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `numeric_value`=VALUES(`numeric_value`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1;

INSERT INTO `lookup_options` (`lookup_key`,`code`,`label`,`numeric_value`,`sort_order`,`is_active`) VALUES
  ('income_band','NO_INCOME','No income',0,1,1),
  ('income_band','LT_25K','Less than ₹25,000',25000,2,1),
  ('income_band','25K_50K','₹25,000–₹50,000',50000,3,1),
  ('income_band','50K_75K','₹50,000–₹75,000',75000,4,1),
  ('income_band','LT_1L','Less than ₹1,00,000',100000,5,1),
  ('income_band','GT_1L','More than ₹1,00,000',999999999,6,1),
  ('income_band','LT_8L','Less than ₹8,00,000',800000,7,1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `numeric_value`=VALUES(`numeric_value`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1;

INSERT INTO `lookup_options` (`lookup_key`,`code`,`label`,`numeric_value`,`sort_order`,`is_active`) VALUES
  ('vulnerability_group','MIGRANT','Migrant Labor',NULL,1,1),
  ('vulnerability_group','FSW','Female Sex Workers (FSW)',NULL,2,1),
  ('vulnerability_group','CALAMITY','Affected by Natural Calamities',NULL,3,1),
  ('vulnerability_group','CONFLICT','Affected by Conflict',NULL,4,1),
  ('vulnerability_group','VIOLENCE','Victims/Survivors of Violence and Abuse',NULL,5,1),
  ('vulnerability_group','TRAFFICKING','Victims and Survivors of Trafficking',NULL,6,1),
  ('vulnerability_group','TRIBAL','Tribal Population',NULL,7,1),
  ('vulnerability_group','MINORITY','Minority Group',NULL,8,1),
  ('vulnerability_group','ORPHAN','Orphan Child',NULL,9,1),
  ('vulnerability_group','SINGLE_PARENT','Single Parent',NULL,10,1),
  ('vulnerability_group','CNCP','Child in Need of Care and Protection',NULL,11,1),
  ('vulnerability_group','DISPLACED','Displaced',NULL,12,1),
  ('vulnerability_group','PWD_CHRONIC','PwD/Chronic Diseases',NULL,13,1),
  ('vulnerability_group','ELDERLY','Elderly',NULL,14,1),
  ('vulnerability_group','LGBTQ','LGBTQ+',NULL,15,1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `numeric_value`=VALUES(`numeric_value`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1;

-- Options added to sets that already existed.

INSERT INTO `lookup_options` (`lookup_key`,`code`,`label`,`numeric_value`,`sort_order`,`is_active`) VALUES
  ('social_category','PWD','Persons with Disabilities (PwD)',7,7,1),
  ('social_category','OTHER','Other',8,8,1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `numeric_value`=VALUES(`numeric_value`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1;

INSERT INTO `lookup_options` (`lookup_key`,`code`,`label`,`numeric_value`,`sort_order`,`is_active`) VALUES
  ('occupation','TRADER','Trader',NULL,19,1),
  ('occupation','WEAVER','Weaver',NULL,20,1),
  ('occupation','UNORGANISED','Unorganized Sector',NULL,21,1),
  ('occupation','SERVICE','Service',NULL,22,1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `numeric_value`=VALUES(`numeric_value`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1;

-- ---------------------------------------------------------------------
-- 2. Questions
-- ---------------------------------------------------------------------

INSERT INTO `assessment_questions`
  (`section`,`question_key`,`label`,`help_text`,`input_type`,`lookup_key`,`unit`,
   `min_value`,`max_value`,`is_required`,`feeds_eligibility`,`sort_order`,`is_active`,
   `depends_on_key`,`depends_on_value`) VALUES
  ('Identity & Citizenship','is_indian_citizen','Are you an Indian Citizen?',NULL,'radio','yes_no',NULL,NULL,NULL,1,1,10,1,NULL,NULL),
  ('Identity & Citizenship','social_category','Category',NULL,'select','social_category',NULL,NULL,NULL,1,1,20,1,NULL,NULL),
  ('Identity & Citizenship','social_category_other','If Other, please mention','Only needed when Category is answered as Other.','text',NULL,NULL,NULL,NULL,0,0,25,1,'social_category','OTHER'),
  ('Identity & Citizenship','marital_status','Marital Status',NULL,'select','marital_status',NULL,NULL,NULL,1,1,30,1,NULL,NULL),
  ('Household','has_children','Do you have any children?',NULL,'radio','yes_no',NULL,NULL,NULL,0,1,50,1,NULL,NULL),
  ('Household','girl_child_count','No. of girl child',NULL,'number',NULL,NULL,0,30,0,1,60,1,'has_children','Yes'),
  ('Household','boy_child_count','No. of boy child',NULL,'number',NULL,NULL,0,30,0,1,70,1,'has_children','Yes'),
  ('Household','children_under_18','No. of children below the age of 18 years',NULL,'number',NULL,NULL,0,30,0,1,80,1,'has_children','Yes'),
  ('Household','children_in_school','How many children are enrolled in school?',NULL,'number',NULL,NULL,0,30,0,1,90,1,'has_children','Yes'),
  ('Livelihood','earning_status','Do you earn?',NULL,'select','earning_status',NULL,NULL,NULL,1,1,100,1,NULL,NULL),
  ('Livelihood','occupation','Occupation',NULL,'select','occupation',NULL,NULL,NULL,0,1,110,1,'earning_status','YES'),
  ('Livelihood','occupation_other','If Other, please mention','Only needed when Occupation is answered as Other.','text',NULL,NULL,NULL,NULL,0,0,115,1,'occupation','OTHER'),
  ('Livelihood','occupation_registered','Is the trade or business registered?','Applies to farmers, traders, artisans, weavers and businesses.','radio','yes_no',NULL,NULL,NULL,0,1,120,1,'occupation','CULTIVATOR,TRADER,ARTISAN,WEAVER,SMALL_BUSINESS,SELF_EMPLOYED'),
  ('Livelihood','monthly_income_band','What is your monthly income?',NULL,'select','income_band',NULL,NULL,NULL,0,1,130,1,'earning_status','YES'),
  ('Document Checklist','doc_aadhaar','Adhaar Card',NULL,'select','yes_no',NULL,NULL,NULL,0,1,200,1,NULL,NULL),
  ('Document Checklist','doc_pan','PAN Card',NULL,'select','yes_no',NULL,NULL,NULL,0,1,210,1,NULL,NULL),
  ('Document Checklist','doc_birth_certificate','Birth Certificate',NULL,'select','yes_no',NULL,NULL,NULL,0,1,220,1,NULL,NULL),
  ('Document Checklist','doc_voter_id','Voter ID Card',NULL,'select','yes_no',NULL,NULL,NULL,0,1,230,1,NULL,NULL),
  ('Document Checklist','doc_death_certificate','Death Certificate of Husband/Father/Primary Earner of Family',NULL,'select','yes_no_na',NULL,NULL,NULL,0,1,240,1,NULL,NULL),
  ('Document Checklist','has_income_certificate','Income Certificate',NULL,'select','yes_no_na',NULL,NULL,NULL,0,1,250,1,NULL,NULL),
  ('Document Checklist','has_caste_certificate','Caste Certificate',NULL,'select','yes_no_na',NULL,NULL,NULL,0,1,260,1,NULL,NULL),
  ('Document Checklist','doc_ration_aay','Antyodaya Anna Yojana (AAY) ration card',NULL,'select','yes_no_na',NULL,NULL,NULL,0,1,270,1,NULL,NULL),
  ('Document Checklist','doc_ration_phh','Priority Household (PHH) ration card',NULL,'select','yes_no_na',NULL,NULL,NULL,0,1,280,1,NULL,NULL),
  ('Document Checklist','doc_disability_card','Disability Card',NULL,'select','yes_no_na',NULL,NULL,NULL,0,1,290,1,NULL,NULL),
  ('Financial Inclusion','has_bank_account','Do you have a bank account?',NULL,'radio','yes_no',NULL,NULL,NULL,1,1,300,1,NULL,NULL),
  ('Financial Inclusion','phone_aadhaar_linked','Is Phone Number Linked with Adhaar Card?',NULL,'radio','yes_no',NULL,NULL,NULL,0,1,310,1,NULL,NULL),
  ('Vulnerability','vulnerability_group','Vulnerability Group','Tick every group that applies.','multiselect','vulnerability_group',NULL,NULL,NULL,0,1,400,1,NULL,NULL)
ON DUPLICATE KEY UPDATE
  `section`=VALUES(`section`), `label`=VALUES(`label`), `help_text`=VALUES(`help_text`),
  `input_type`=VALUES(`input_type`), `lookup_key`=VALUES(`lookup_key`), `unit`=VALUES(`unit`),
  `min_value`=VALUES(`min_value`), `max_value`=VALUES(`max_value`),
  `is_required`=VALUES(`is_required`), `feeds_eligibility`=VALUES(`feeds_eligibility`),
  `sort_order`=VALUES(`sort_order`), `is_active`=1,
  `depends_on_key`=VALUES(`depends_on_key`), `depends_on_value`=VALUES(`depends_on_value`);

-- The questionnaire supersedes two narrower questions that asked the same
-- thing in a different form. They are hidden, not deleted, so existing
-- answers and any scheme rule written against them still resolve.
UPDATE `assessment_questions` SET `is_active` = 0
 WHERE `question_key` IN (
   'current_class',
   -- Retired from the Livelihood section: the questionnaire's own
   -- Occupation question covers this ground. Deactivated rather than
   -- deleted so answers already recorded, and the scheme rules written
   -- against them, still resolve for existing beneficiaries.
   'employment_type', 'farmer_category', 'worker_category',
   'handloom_weaver', 'tea_garden_worker', 'has_job_card', 'has_eshram',
   -- Retired from Financial Inclusion and Vulnerability.
   'bank_aadhaar_linked',
   'widow_status', 'is_orphan', 'single_woman_household',
   'children_under_6', 'violence_survivor',
   -- Whole sections retired: Socio-Economic, Health & Disability,
   -- Housing & Assets and Existing Coverage. The registration form now
   -- asks exactly what the questionnaire document asks, and no more.
   'religion', 'education_level', 'is_student',
   'disability_status', 'disability_percent', 'has_disability_certificate',
   'pregnancy_status', 'lactating_status', 'chronic_illness', 'has_abha',
   'house_type', 'owns_house',
   'receives_pension', 'has_health_insurance', 'income_tax_payer',
   'govt_employee', 'existing_benefits'
 );

-- ---------------------------------------------------------------------
-- 2b. Section order.
--
--   Sections are presented in the order their first question appears, so
--   each section is given a thousand-block and questions keep their
--   relative order inside it. MOD() makes a second run a no-op.
-- ---------------------------------------------------------------------
UPDATE `assessment_questions` SET `sort_order` = 1000 + MOD(`sort_order`, 1000) WHERE `section` = 'Identity & Citizenship';
UPDATE `assessment_questions` SET `sort_order` = 2000 + MOD(`sort_order`, 1000) WHERE `section` = 'Household';
UPDATE `assessment_questions` SET `sort_order` = 3000 + MOD(`sort_order`, 1000) WHERE `section` = 'Livelihood';
UPDATE `assessment_questions` SET `sort_order` = 4000 + MOD(`sort_order`, 1000) WHERE `section` = 'Document Checklist';
UPDATE `assessment_questions` SET `sort_order` = 5000 + MOD(`sort_order`, 1000) WHERE `section` = 'Financial Inclusion';
UPDATE `assessment_questions` SET `sort_order` = 6000 + MOD(`sort_order`, 1000) WHERE `section` = 'Vulnerability';

-- The questionnaire leads each section; the platform's own additional
-- questions follow it. Absolute values, so a second run is a no-op.

-- The household income question was retired: monthly income is now asked
-- once, in the Livelihood section. Neither it nor the earlier "Annual
-- Household Income" key ever carried an answer, so both are removed rather
-- than left lying around to mislead whoever writes the next scheme rule.
DELETE FROM `assessment_questions`   WHERE `question_key`  IN ('annual_income_band','household_income_band');
DELETE FROM `eligibility_attributes` WHERE `attribute_key` IN ('annual_income_band','household_income_band');

-- ---------------------------------------------------------------------
-- 3. Eligibility attributes (the vocabulary the rule builder offers)
-- ---------------------------------------------------------------------

INSERT INTO `eligibility_attributes`
  (`attribute_key`,`label`,`data_type`,`source`,`source_column`,`lookup_key`,`unit`,
   `group_label`,`description`,`sort_order`,`is_active`) VALUES
  ('is_indian_citizen','Indian Citizen','bool','assessment',NULL,NULL,NULL,'Identity','Whether the beneficiary reports Indian citizenship.',301,1),
  ('monthly_income_band','Monthly Income Band','enum','assessment',NULL,'income_band','INR','Economic','Personal monthly income band, recorded only for earners.',302,1),
  ('earning_status','Earning Status','enum','assessment',NULL,'earning_status',NULL,'Livelihood','Whether the beneficiary earns, does not earn, or is unemployed.',303,1),
  ('occupation_registered','Trade / Business Registered','bool','assessment',NULL,NULL,NULL,'Livelihood','Whether a farmer, trader, artisan, weaver or business is formally registered.',304,1),
  ('has_children','Has Children','bool','assessment',NULL,NULL,NULL,'Household','Whether the beneficiary has any children.',305,1),
  ('boy_child_count','Number of Boy Children','int','assessment',NULL,NULL,NULL,'Household','Count of male children in the household.',306,1),
  ('children_under_18','Children Under 18','int','assessment',NULL,NULL,NULL,'Household','Count of children below eighteen years.',307,1),
  ('children_in_school','Children Enrolled in School','int','assessment',NULL,NULL,NULL,'Household','Count of children currently enrolled in school.',308,1),
  ('phone_aadhaar_linked','Phone Linked to Aadhaar','bool','assessment',NULL,NULL,NULL,'Financial Inclusion','Whether the registered mobile number is linked to the Aadhaar record.',309,1),
  ('vulnerability_group','Vulnerability Group','set','assessment',NULL,'vulnerability_group',NULL,'Vulnerability','One or more vulnerability groups the beneficiary belongs to. Use Any Of / Contains in rules.',310,1),
  ('doc_aadhaar','Holds Aadhaar Card','bool','assessment',NULL,NULL,NULL,'Documents','Document checklist: Aadhaar card in hand.',311,1),
  ('doc_pan','Holds PAN Card','bool','assessment',NULL,NULL,NULL,'Documents','Document checklist: PAN card in hand.',312,1),
  ('doc_birth_certificate','Holds Birth Certificate','bool','assessment',NULL,NULL,NULL,'Documents','Document checklist: birth certificate in hand.',313,1),
  ('doc_voter_id','Holds Voter ID Card','bool','assessment',NULL,NULL,NULL,'Documents','Document checklist: voter ID card in hand.',314,1),
  ('doc_death_certificate','Holds Death Certificate of Primary Earner','enum','assessment',NULL,'yes_no_na',NULL,'Documents','Document checklist: death certificate of husband, father or primary earner.',315,1),
  ('doc_ration_aay','Holds AAY Ration Card','enum','assessment',NULL,'yes_no_na',NULL,'Documents','Document checklist: Antyodaya Anna Yojana ration card.',316,1),
  ('doc_ration_phh','Holds PHH Ration Card','enum','assessment',NULL,'yes_no_na',NULL,'Documents','Document checklist: Priority Household ration card.',317,1),
  ('doc_disability_card','Holds Disability Card','enum','assessment',NULL,'yes_no_na',NULL,'Documents','Document checklist: disability card.',318,1)
ON DUPLICATE KEY UPDATE
  `label`=VALUES(`label`), `data_type`=VALUES(`data_type`), `lookup_key`=VALUES(`lookup_key`),
  `unit`=VALUES(`unit`), `group_label`=VALUES(`group_label`),
  `description`=VALUES(`description`), `is_active`=1;
