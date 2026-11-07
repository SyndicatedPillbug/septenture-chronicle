CREATE TABLE IF NOT EXISTS septenture_scene (
 campaign_key VARCHAR(64) NOT NULL PRIMARY KEY,
 revision CHAR(32) NOT NULL,
 mode ENUM('board','scene','backdrop') NOT NULL DEFAULT 'board',
 title VARCHAR(120) NOT NULL DEFAULT '',
 mime VARCHAR(40) NOT NULL,
 image MEDIUMBLOB NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
