-- BACK UP YOUR DATABASE BEFORE RUNNING THIS FILE.
USE oh_my_gracious;

CREATE TABLE IF NOT EXISTS technicians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  specialization VARCHAR(150) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users CHANGE COLUMN IF EXISTS password password_hash VARCHAR(255) NULL;
ALTER TABLE users MODIFY COLUMN full_name VARCHAR(150) NOT NULL;
ALTER TABLE users MODIFY COLUMN email VARCHAR(190) NULL;
ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user';
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_number VARCHAR(25) NULL AFTER email;
ALTER TABLE users ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferred_technician_id INT NULL AFTER date_of_birth;
ALTER TABLE users ADD COLUMN IF NOT EXISTS nail_preferences TEXT NULL AFTER preferred_technician_id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS allergy_notes TEXT NULL AFTER nail_preferences;
ALTER TABLE users ADD COLUMN IF NOT EXISTS account_status ENUM('pending_verification','active','suspended') NOT NULL DEFAULT 'active' AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER account_status;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_verified_at DATETIME NULL AFTER email_verified_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER phone_verified_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uq_users_phone (phone_number);

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  display_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_name (service_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_designs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  design_name VARCHAR(120) NOT NULL DEFAULT 'Saved AR Design',
  nail_color VARCHAR(20) NOT NULL,
  nail_shape VARCHAR(40) NOT NULL,
  nail_finish VARCHAR(40) NOT NULL,
  nail_size DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  vertical_offset INT NOT NULL DEFAULT 0,
  depth_value INT NOT NULL DEFAULT 0,
  preview_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_saved_design_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_saved_design_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bookings MODIFY COLUMN full_name VARCHAR(150) NOT NULL;
ALTER TABLE bookings MODIFY COLUMN email VARCHAR(190) NULL;
ALTER TABLE bookings MODIFY COLUMN service_type VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE bookings MODIFY COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash';
ALTER TABLE bookings MODIFY COLUMN payment_status VARCHAR(40) NOT NULL DEFAULT 'To Pay';
ALTER TABLE bookings MODIFY COLUMN payment_reference VARCHAR(120) NULL;
ALTER TABLE bookings MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Pending';
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS total_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER booking_time;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS selected_design_id INT NULL AFTER notes;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER status;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS checked_in_by INT NULL AFTER approved_by;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS completed_by INT NULL AFTER checked_in_by;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS check_in_at DATETIME NULL AFTER completed_by;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER check_in_at;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_booking_date_time (booking_date, booking_time);
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_booking_status (status, booking_date);

CREATE TABLE IF NOT EXISTS booking_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  service_id INT NULL,
  service_name VARCHAR(120) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  CONSTRAINT fk_booking_item_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_item_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  KEY idx_booking_items_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  slot_date DATE NOT NULL,
  slot_time TIME NOT NULL,
  CONSTRAINT fk_booking_slot_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  UNIQUE KEY uq_booking_slot (slot_date, slot_time),
  KEY idx_booking_slot_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  method VARCHAR(30) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'To Pay',
  amount DECIMAL(10,2) NOT NULL,
  reference_number VARCHAR(120) NULL,
  paid_at DATETIME NULL,
  verified_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_payment_booking (booking_id),
  KEY idx_payment_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER reference_number;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway_session_id VARCHAR(120) NULL AFTER gateway;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway_payment_id VARCHAR(120) NULL AFTER gateway_session_id;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS checkout_url VARCHAR(600) NULL AFTER gateway_payment_id;

CREATE TABLE IF NOT EXISTS auth_identities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider ENUM('password','google','facebook','phone') NOT NULL,
  provider_user_id VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auth_identity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_provider_identity (provider, provider_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_email_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_reset_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id INT NULL,
  details TEXT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_created (created_at),
  KEY idx_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identifier_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempt (identifier_hash, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO technicians (full_name, specialization, status)
SELECT 'Technician 1','Nail Art','active'
WHERE NOT EXISTS (SELECT 1 FROM technicians LIMIT 1);
INSERT INTO technicians (full_name, specialization, status)
SELECT 'Technician 2','Gel & Extensions','active'
WHERE (SELECT COUNT(*) FROM technicians) = 1;

INSERT INTO services (service_name, description, price, duration_minutes, status, display_order) VALUES
('Manicure','Classic nail care and shaping',250.00,60,'active',1),
('Pedicure','Classic foot and nail care',300.00,60,'active',2),
('Gel Polish','Long-lasting gel color application',500.00,60,'active',3),
('Nail Extension','Nail extension application and shaping',800.00,90,'active',4),
('Nail Art','Custom nail art design service',600.00,60,'active',5)
ON DUPLICATE KEY UPDATE description=VALUES(description), price=VALUES(price), duration_minutes=VALUES(duration_minutes), status=VALUES(status), display_order=VALUES(display_order);

INSERT INTO system_settings (setting_key, setting_value) VALUES
('salon_name','Oh My Gracious'),('open_time','10:00'),('close_time','22:00'),('slot_interval_minutes','30'),('booking_days_ahead','90'),('currency','PHP')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


INSERT IGNORE INTO auth_identities (user_id, provider, provider_user_id)
SELECT id, 'password', email FROM users WHERE email IS NOT NULL AND email <> '' AND password_hash IS NOT NULL;

INSERT IGNORE INTO booking_items (booking_id, service_id, service_name, unit_price, duration_minutes)
SELECT b.id, s.id, b.service_type, b.amount, COALESCE(s.duration_minutes, 60)
FROM bookings b
LEFT JOIN services s ON s.service_name = b.service_type
WHERE NOT EXISTS (SELECT 1 FROM booking_items bi WHERE bi.booking_id = b.id);

INSERT IGNORE INTO payments (booking_id, method, status, amount, reference_number, paid_at)
SELECT b.id,
       CASE WHEN b.payment_method IN ('Physical','Cash') THEN 'Cash' ELSE b.payment_method END,
       CASE WHEN b.payment_status IN ('Paid') THEN 'Paid' ELSE 'To Pay' END,
       b.amount,
       NULLIF(b.payment_reference,''),
       CASE WHEN b.payment_status='Paid' THEN b.created_at ELSE NULL END
FROM bookings b;

-- Align legacy single-service duration with the new services catalog before claiming slots.
UPDATE bookings b
LEFT JOIN services s ON s.service_name = b.service_type
SET b.total_duration_minutes = COALESCE(s.duration_minutes, b.total_duration_minutes, 60)
WHERE s.id IS NOT NULL;

INSERT IGNORE INTO booking_slots (booking_id, slot_date, slot_time)
SELECT b.id, b.booking_date, b.booking_time FROM bookings b WHERE b.status IN ('Pending','Approved','Checked-in');
INSERT IGNORE INTO booking_slots (booking_id, slot_date, slot_time)
SELECT b.id, b.booking_date, ADDTIME(b.booking_time,'00:30:00') FROM bookings b WHERE b.status IN ('Pending','Approved','Checked-in') AND b.total_duration_minutes > 30;
INSERT IGNORE INTO booking_slots (booking_id, slot_date, slot_time)
SELECT b.id, b.booking_date, ADDTIME(b.booking_time,'01:00:00') FROM bookings b WHERE b.status IN ('Pending','Approved','Checked-in') AND b.total_duration_minutes > 60;
INSERT IGNORE INTO booking_slots (booking_id, slot_date, slot_time)
SELECT b.id, b.booking_date, ADDTIME(b.booking_time,'01:30:00') FROM bookings b WHERE b.status IN ('Pending','Approved','Checked-in') AND b.total_duration_minutes > 90;
INSERT IGNORE INTO booking_slots (booking_id, slot_date, slot_time)
SELECT b.id, b.booking_date, ADDTIME(b.booking_time,'02:00:00') FROM bookings b WHERE b.status IN ('Pending','Approved','Checked-in') AND b.total_duration_minutes > 120;
INSERT IGNORE INTO booking_slots (booking_id, slot_date, slot_time)
SELECT b.id, b.booking_date, ADDTIME(b.booking_time,'02:30:00') FROM bookings b WHERE b.status IN ('Pending','Approved','Checked-in') AND b.total_duration_minutes > 150;
INSERT IGNORE INTO booking_slots (booking_id, slot_date, slot_time)
SELECT b.id, b.booking_date, ADDTIME(b.booking_time,'03:00:00') FROM bookings b WHERE b.status IN ('Pending','Approved','Checked-in') AND b.total_duration_minutes > 180;
