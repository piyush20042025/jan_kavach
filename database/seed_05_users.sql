-- =====================================================================
--  JAN KAVACH - SEED 05 : DEMONSTRATION USER ACCOUNTS
--  Change every password before any real deployment
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `jan_kavach`;

-- Demonstration credentials
--   admin        / Admin@123     Super Administrator (all States)
--   wb.admin     / State@123     State Administrator - West Bengal
--   wb.purulia   / District@123  District Coordinator - Purulia
--   wb.field1    / Field@123     Field User - Purulia
--   reviewer     / Viewer@123    Read-only Programme Viewer
-- (every State/District/Field account follows the same pattern)

DELETE FROM `users`;
ALTER TABLE `users` AUTO_INCREMENT = 1;

INSERT INTO `users` (`id`, `user_code`, `full_name`, `username`, `email`, `mobile`, `password_hash`, `role_id`, `state_id`, `district_id`, `block_id`, `designation`, `organization`, `is_active`, `must_change_password`, `created_by`) VALUES
  (1, 'JK-USR-00001', 'Programme Administrator', 'admin', 'admin@jankavach.local', '9810000001', '$2y$11$gPuB/gDhQ3IO/oiJCN36a.i31Tkrk8jXDe2jaFU.Kg2wTskG60DC.', 1, NULL, NULL, NULL, 'National Programme Manager', 'Social Protection Programme', 1, 0, 1),
  (2, 'JK-USR-00002', 'Ananya Chakraborty', 'wb.admin', 'wb.admin@jankavach.local', '9830000011', '$2y$11$PxUyW5TJhFOc8TmaRGzgxe97A1BDlKTbharbW6/Uxe0PACm/QLkuO', 2, 36, NULL, NULL, 'State Programme Manager', 'State Programme Unit - West Bengal', 1, 0, 1),
  (3, 'JK-USR-00003', 'Ramesh Chandra Verma', 'up.admin', 'up.admin@jankavach.local', '9450000012', '$2y$11$PxUyW5TJhFOc8TmaRGzgxe97A1BDlKTbharbW6/Uxe0PACm/QLkuO', 2, 34, NULL, NULL, 'State Programme Manager', 'State Programme Unit - Uttar Pradesh', 1, 0, 1),
  (4, 'JK-USR-00004', 'Nayanmoni Gogoi', 'as.admin', 'as.admin@jankavach.local', '9435000013', '$2y$11$PxUyW5TJhFOc8TmaRGzgxe97A1BDlKTbharbW6/Uxe0PACm/QLkuO', 2, 4, NULL, NULL, 'State Programme Manager', 'State Programme Unit - Assam', 1, 0, 1),
  (5, 'JK-USR-00005', 'Sanjay Kumar Oraon', 'jh.admin', 'jh.admin@jankavach.local', '9431000014', '$2y$11$PxUyW5TJhFOc8TmaRGzgxe97A1BDlKTbharbW6/Uxe0PACm/QLkuO', 2, 15, NULL, NULL, 'State Programme Manager', 'State Programme Unit - Jharkhand', 1, 0, 1),
  (6, 'JK-USR-00006', 'Wanda Kharkongor', 'ml.admin', 'ml.admin@jankavach.local', '9436000015', '$2y$11$PxUyW5TJhFOc8TmaRGzgxe97A1BDlKTbharbW6/Uxe0PACm/QLkuO', 2, 23, NULL, NULL, 'State Programme Manager', 'State Programme Unit - Meghalaya', 1, 0, 1),
  (7, 'JK-USR-00007', 'Thoibi Devi Laishram', 'mn.admin', 'mn.admin@jankavach.local', '9436000016', '$2y$11$PxUyW5TJhFOc8TmaRGzgxe97A1BDlKTbharbW6/Uxe0PACm/QLkuO', 2, 22, NULL, NULL, 'State Programme Manager', 'State Programme Unit - Manipur', 1, 0, 1),
  (8, 'JK-USR-00008', 'Lakshmi Prasanna Reddy', 'ap.admin', 'ap.admin@jankavach.local', '9440000017', '$2y$11$PxUyW5TJhFOc8TmaRGzgxe97A1BDlKTbharbW6/Uxe0PACm/QLkuO', 2, 2, NULL, NULL, 'State Programme Manager', 'State Programme Unit - Andhra Pradesh', 1, 0, 1),
  (9, 'JK-USR-00009', 'Debashis Mahato', 'wb.purulia', 'wb.purulia@jankavach.local', '9832000021', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 36, 767, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (10, 'JK-USR-00010', 'Rituparna Halder', 'wb.s24pgs', 'wb.s24pgs@jankavach.local', '9832000022', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 36, 768, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (11, 'JK-USR-00011', 'Shalini Singh', 'up.bahraich', 'up.bahraich@jankavach.local', '9452000023', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 34, 681, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (12, 'JK-USR-00012', 'Arun Kumar Mishra', 'up.shravasti', 'up.shravasti@jankavach.local', '9452000024', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 34, 740, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (13, 'JK-USR-00013', 'Rafiqul Islam', 'as.dhubri', 'as.dhubri@jankavach.local', '9435000025', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 4, 67, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (14, 'JK-USR-00014', 'Bhaskar Jyoti Saikia', 'as.sonitpur', 'as.sonitpur@jankavach.local', '9435000026', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 4, 86, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (15, 'JK-USR-00015', 'Priyanka Munda', 'jh.khunti', 'jh.khunti@jankavach.local', '9431000027', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 15, 259, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (16, 'JK-USR-00016', 'Mahesh Hembrom', 'jh.dumka', 'jh.dumka@jankavach.local', '9431000028', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 15, 251, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (17, 'JK-USR-00017', 'Banri Syiem', 'ml.ekh', 'ml.ekh@jankavach.local', '9436000029', '$2y$11$19/0WxYA8kw.kA/8xAcGdewCxDaT5Z/Seb7UApjbd/s.ivqeNZ3D6', 3, 23, 377, NULL, 'District Coordinator', 'District Programme Unit', 1, 0, 1),
  (18, 'JK-USR-00018', 'Sabitri Mahato', 'wb.field1', 'wb.field1@jankavach.local', '9832000031', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 36, 767, NULL, 'Community Mobiliser', 'Field Team - Purulia', 1, 0, 1),
  (19, 'JK-USR-00019', 'Kartik Bauri', 'wb.field2', 'wb.field2@jankavach.local', '9832000032', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 36, 767, NULL, 'Community Mobiliser', 'Field Team - Purulia', 1, 0, 1),
  (20, 'JK-USR-00020', 'Rekha Naskar', 'wb.field3', 'wb.field3@jankavach.local', '9832000033', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 36, 768, NULL, 'Community Mobiliser', 'Field Team - Sundarbans', 1, 0, 1),
  (21, 'JK-USR-00021', 'Sunita Devi', 'up.field1', 'up.field1@jankavach.local', '9452000034', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 34, 681, NULL, 'Community Mobiliser', 'Field Team - Bahraich', 1, 0, 1),
  (22, 'JK-USR-00022', 'Rajesh Yadav', 'up.field2', 'up.field2@jankavach.local', '9452000035', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 34, 681, NULL, 'Community Mobiliser', 'Field Team - Bahraich', 1, 0, 1),
  (23, 'JK-USR-00023', 'Kamla Prasad', 'up.field3', 'up.field3@jankavach.local', '9452000036', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 34, 740, NULL, 'Community Mobiliser', 'Field Team - Shravasti', 1, 0, 1),
  (24, 'JK-USR-00024', 'Junmoni Das', 'as.field1', 'as.field1@jankavach.local', '9435000037', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 4, 67, NULL, 'Community Mobiliser', 'Field Team - Dhubri', 1, 0, 1),
  (25, 'JK-USR-00025', 'Pranab Bora', 'as.field2', 'as.field2@jankavach.local', '9435000038', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 4, 86, NULL, 'Community Mobiliser', 'Field Team - Sonitpur', 1, 0, 1),
  (26, 'JK-USR-00026', 'Sushila Munda', 'jh.field1', 'jh.field1@jankavach.local', '9431000039', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 15, 259, NULL, 'Community Mobiliser', 'Field Team - Khunti', 1, 0, 1),
  (27, 'JK-USR-00027', 'Birsa Tirkey', 'jh.field2', 'jh.field2@jankavach.local', '9431000040', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 15, 251, NULL, 'Community Mobiliser', 'Field Team - Dumka', 1, 0, 1),
  (28, 'JK-USR-00028', 'Ibanroi Lyngdoh', 'ml.field1', 'ml.field1@jankavach.local', '9436000041', '$2y$11$rsRfaIRAXaisyBXxmlQopuMI.GKD08FnbvisCHDgrePmYr/QKI4cm', 4, 23, 377, NULL, 'Community Mobiliser', 'Field Team - Shillong', 1, 0, 1),
  (29, 'JK-USR-00029', 'Programme Reviewer', 'reviewer', 'reviewer@jankavach.local', '9810000050', '$2y$11$Q5kOLJmJRyqk1sm8VRsFOuNgw9GTIdvzZdKXsrvNy98dxmU3nqhWW', 5, 36, NULL, NULL, 'Monitoring & Evaluation Officer', 'Programme Management Unit', 1, 0, 1);

SET FOREIGN_KEY_CHECKS = 1;
