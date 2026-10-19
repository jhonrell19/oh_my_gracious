-- Back up first. Additive and safe to rerun; no bookings or users are removed.
CREATE TABLE IF NOT EXISTS user_security (
 user_id INT PRIMARY KEY, session_version INT NOT NULL DEFAULT 1,
 totp_secret TEXT NULL, last_totp_step BIGINT NOT NULL DEFAULT -1,
 recovery_hashes TEXT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS security_attempts (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, bucket VARCHAR(64) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX security_bucket_time(bucket,created_at)
) ENGINE=InnoDB;
