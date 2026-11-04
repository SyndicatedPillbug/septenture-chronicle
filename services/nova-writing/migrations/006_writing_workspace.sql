-- Personal notebooks are separate from publishable journals and character sheets.
CREATE TABLE IF NOT EXISTS nova_player_notebooks (
 subject CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 source MEDIUMTEXT NOT NULL,
 format VARCHAR(8) NOT NULL DEFAULT 'markdown',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 updated_at BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS nova_story_turns (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 story_id INT UNSIGNED NOT NULL,
 sender INT UNSIGNED NOT NULL,
 recipient INT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,
 read_at BIGINT UNSIGNED NULL,
 email_status VARCHAR(12) NOT NULL DEFAULT 'pending',
 UNIQUE KEY one_turn_per_revision(story_id,sender,recipient,revision),
 KEY recipient_inbox(recipient,created_at),
 KEY sender_rate(sender,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
