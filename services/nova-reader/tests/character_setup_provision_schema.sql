CREATE TABLE `nova_user_prefs` (
  `pref_id` int(5) NOT NULL AUTO_INCREMENT,
  `pref_key` varchar(100) NOT NULL DEFAULT '',
  `pref_label` varchar(255) NOT NULL DEFAULT '',
  `pref_default` text DEFAULT NULL,
  PRIMARY KEY (`pref_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `nova_user_prefs_values` (
  `prefvalue_id` int(5) NOT NULL AUTO_INCREMENT,
  `prefvalue_user` int(8) NOT NULL,
  `prefvalue_key` varchar(100) NOT NULL DEFAULT '',
  `prefvalue_value` text DEFAULT NULL,
  PRIMARY KEY (`prefvalue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `nova_positions` (
  `pos_id` int(10) NOT NULL AUTO_INCREMENT,
  `pos_name` varchar(255) NOT NULL DEFAULT '',
  `pos_desc` text DEFAULT NULL,
  `pos_dept` int(10) NOT NULL,
  `pos_order` int(5) NOT NULL,
  `pos_open` int(5) NOT NULL,
  `pos_display` enum('y','n') NOT NULL DEFAULT 'y',
  `pos_type` enum('senior','officer','enlisted','other') NOT NULL DEFAULT 'officer',
  `pos_top_open` enum('y','n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (`pos_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `nova_ranks` (
  `rank_id` int(10) NOT NULL AUTO_INCREMENT,
  `rank_name` varchar(100) NOT NULL DEFAULT '',
  `rank_short_name` varchar(20) NOT NULL DEFAULT '',
  `rank_image` varchar(100) NOT NULL DEFAULT '',
  `rank_order` int(5) NOT NULL,
  `rank_display` enum('y','n') NOT NULL DEFAULT 'y',
  `rank_class` int(5) NOT NULL DEFAULT 1,
  PRIMARY KEY (`rank_id`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `nova_septenture_gm_cast` (
  `userid` int(11) NOT NULL,
  `charid` int(11) NOT NULL,
  PRIMARY KEY (`userid`),
  UNIQUE KEY `charid` (`charid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
