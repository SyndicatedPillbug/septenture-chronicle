CREATE TABLE IF NOT EXISTS nova_publication_mail (
 story_id INT NOT NULL,
 recipient VARCHAR(254) NOT NULL,
 status VARCHAR(12) NOT NULL,
 attempted_at BIGINT NOT NULL,
 PRIMARY KEY (story_id,recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
