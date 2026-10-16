SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE DATABASE IF NOT EXISTS oh_my_gracious CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oh_my_gracious;

CREATE TABLE technicians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  specialization VARCHAR(150) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  phone_number VARCHAR(25) NULL,
  password_hash VARCHAR(255) NULL,
  date_of_birth DATE NULL,
  preferred_technician_id INT NULL,
  nail_preferences TEXT NULL,
  allergy_notes TEXT NULL,
  role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
  account_status ENUM('pending_verification','active','suspended') NOT NULL DEFAULT 'pending_verification',
  email_verified_at DATETIME NULL,
  phone_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone_number),
  KEY idx_users_role_status (role, account_status),
  CONSTRAINT fk_users_preferred_technician FOREIGN KEY (preferred_technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(120) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  description VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  booking_availability ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  unavailable_reason VARCHAR(255) NULL,
  display_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_name (service_name),
  KEY idx_services_booking (status, booking_availability, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_designs (
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

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  phone_number VARCHAR(25) NULL,
  service_type VARCHAR(500) NOT NULL DEFAULT '',
  booking_date DATE NOT NULL,
  booking_time TIME NOT NULL,
  total_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash',
  payment_status VARCHAR(40) NOT NULL DEFAULT 'To Pay',
  payment_reference VARCHAR(120) NULL,
  notes TEXT NULL,
  selected_design_id INT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  booking_source ENUM('customer','frontdesk','walk_in') NOT NULL DEFAULT 'customer',
  approved_by INT NULL,
  checked_in_by INT NULL,
  completed_by INT NULL,
  check_in_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_booking_design FOREIGN KEY (selected_design_id) REFERENCES saved_designs(id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_checked_in_by FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_completed_by FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_booking_date_time (booking_date, booking_time),
  KEY idx_booking_user (user_id, created_at),
  KEY idx_booking_status (status, booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  service_id INT NULL,
  service_name VARCHAR(120) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_booking_item_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_item_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  KEY idx_booking_items_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_time_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slot_time TIME NOT NULL,
  period ENUM('AM','PM') NOT NULL,
  default_capacity SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  display_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_time_slot (slot_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_slot_inventory (
  slot_date DATE NOT NULL,
  slot_time TIME NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  reason VARCHAR(255) NULL,
  reserved_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_by INT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (slot_date, slot_time),
  CONSTRAINT fk_slot_inventory_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  slot_date DATE NOT NULL,
  slot_time TIME NOT NULL,
  CONSTRAINT fk_booking_slot_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  UNIQUE KEY uq_booking_slot_claim (booking_id, slot_date, slot_time),
  KEY idx_booking_slot_date_time (slot_date, slot_time),
  KEY idx_booking_slot_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'Cash',
  status VARCHAR(40) NOT NULL DEFAULT 'To Pay',
  amount DECIMAL(10,2) NOT NULL,
  reference_number VARCHAR(120) NULL,
  gateway VARCHAR(30) NOT NULL DEFAULT 'manual',
  paid_at DATETIME NULL,
  verified_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_payment_booking (booking_id),
  KEY idx_payment_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_identities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider ENUM('password','google') NOT NULL,
  provider_user_id VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auth_identity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_provider_identity (provider, provider_user_id),
  UNIQUE KEY uq_user_provider (user_id, provider),
  KEY idx_auth_identity_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verification_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_email_token_hash (token_hash),
  KEY idx_email_token_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_reset_token_hash (token_hash),
  KEY idx_reset_token_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_setting_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id INT NULL,
  details TEXT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_audit_created (created_at),
  KEY idx_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identifier_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempt (identifier_hash, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO technicians (full_name, specialization, status) VALUES
('Technician 1','Nail Services','active'),
('Technician 2','Salon Services','active');

INSERT INTO services (service_name,category,description,price,duration_minutes,status,booking_availability,display_order) VALUES
('Manicure','Nails','Classic nail care and shaping',250.00,60,'active','available',1),
('Pedicure','Nails','Classic foot and nail care',300.00,60,'active','available',2),
('Gel Polish','Nails','Long-lasting gel color application',500.00,60,'active','available',3),
('Nail Extension','Nails','Nail extension application and shaping',800.00,90,'active','available',4),
('Nail Art','Nail Art','Custom nail art design service',600.00,60,'active','available',5);

INSERT INTO booking_time_slots(slot_time,period,default_capacity,status,display_order) VALUES
('09:00:00','AM',5,'active',1),('10:00:00','AM',5,'active',2),('11:00:00','AM',5,'active',3),
('13:00:00','PM',5,'active',4),('14:00:00','PM',5,'active',5),('15:00:00','PM',5,'active',6),
('16:00:00','PM',5,'active',7),('17:00:00','PM',5,'active',8),('18:00:00','PM',5,'active',9),('19:00:00','PM',5,'active',10);

INSERT INTO system_settings(setting_key,setting_value) VALUES
('salon_name','Oh My Gracious'),('booking_days_ahead','90'),('default_slot_capacity','5'),('currency','PHP');
