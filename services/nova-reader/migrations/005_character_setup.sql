CREATE TABLE IF NOT EXISTS nova_septenture_character_setup (
 operation_id CHAR(36) PRIMARY KEY,
 actor_subject CHAR(36) NOT NULL,
 campaign_id VARCHAR(41) NOT NULL,
 owner_subject CHAR(36) NOT NULL,
 display_name VARCHAR(80) NOT NULL,
 character_kind VARCHAR(16) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 character_id INT NULL UNIQUE,
 profile_slug VARCHAR(41) NULL UNIQUE,
 created_at BIGINT NOT NULL,
 completed_at BIGINT NULL,
 INDEX actor_pending(actor_subject,completed_at)
) ENGINE=InnoDB;
