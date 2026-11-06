-- Apply each missing column once; installer checks information_schema before execution.
ALTER TABLE nova_story_turns ADD COLUMN email_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE nova_story_turns ADD COLUMN email_attempted_at BIGINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE nova_publication_mail ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 1;
