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
  price_max DECIMAL(10,2) NULL,
  pricing_type ENUM('fixed','per_nail','range') NOT NULL DEFAULT 'fixed',
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
  amount_is_estimate TINYINT(1) NOT NULL DEFAULT 0,
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
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
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

INSERT INTO services
(service_name,category,description,price,price_max,pricing_type,duration_minutes,status,booking_availability,display_order)
VALUES
('Ultimate Manicure','Nails','Nail care service from the salon menu.',199.00,NULL,'fixed',0,'active','available',1),
('Ultimate Pedicure','Nails','Foot and nail care service from the salon menu.',249.00,NULL,'fixed',0,'active','available',2),
('Gel Manicure','Nails','Gel manicure service.',599.00,NULL,'fixed',0,'active','available',3),
('Gel Pedicure','Nails','Gel pedicure service.',749.00,NULL,'fixed',0,'active','available',4),
('Soft Gel Manicure','Nails','Soft gel manicure service.',999.00,NULL,'fixed',0,'active','available',5),
('Soft Gel Pedicure','Nails','Soft gel pedicure service.',1249.00,NULL,'fixed',0,'active','available',6),
('Poly Gel Extension','Nails','Poly gel nail extension service.',1499.00,NULL,'fixed',0,'active','available',7),
('Hand Spa','Nails','Hand spa service.',349.00,NULL,'fixed',0,'active','available',8),
('Foot Spa','Nails','Foot spa service.',499.00,NULL,'fixed',0,'active','available',9),
('Paraffin Wax','Nails','Paraffin wax service.',299.00,NULL,'fixed',0,'active','available',10),
('Full Refill','Nails','Full nail refill.',499.00,NULL,'fixed',0,'active','available',11),
('Repair / Refill Per Nail','Nails','Repair or refill priced per nail.',100.00,NULL,'per_nail',0,'active','available',12),
('Gel Removal with Cleaning','Nail Removal','Gel removal with cleaning.',250.00,NULL,'fixed',0,'active','available',13),
('Gel Removal with Cleaning (OMG Work)','Nail Removal','Gel removal with cleaning for OMG work.',120.00,NULL,'fixed',0,'active','available',14),
('Nail Extension Removal with Cleaning','Nail Removal','Nail extension removal with cleaning.',450.00,NULL,'fixed',0,'active','available',15),
('Nail Extension Removal with Cleaning (OMG Work)','Nail Removal','Nail extension removal with cleaning for OMG work.',200.00,NULL,'fixed',0,'active','available',16),
('Full Chrome','Nail Art Add-ons','Nail art add-on priced per nail.',100.00,NULL,'per_nail',0,'active','available',17),
('Any Designs with Chrome','Nail Art Add-ons','Nail art add-on priced per nail.',100.00,NULL,'per_nail',0,'active','available',18),
('French Tip','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',19),
('Cat-eye Nail Art','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',20),
('Emboss Nail Art','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',21),
('Ombre Nail Art','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',22),
('Marble Nail Art','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',23),
('Blooming Gel Art','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',24),
('Glitters Design','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',25),
('Hand Drawn Art','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',26),
('3D Design','Nail Art Add-ons','Nail art add-on priced per nail.',50.00,NULL,'per_nail',0,'active','available',27),
('Simple Line','Nail Art Add-ons','Nail art add-on priced per nail.',30.00,NULL,'per_nail',0,'active','available',28),
('Charms','Nail Art Add-ons','Nail art add-on priced per nail.',15.00,NULL,'per_nail',0,'active','available',29),
('Rhinestone','Nail Art Add-ons','Nail art add-on priced per nail.',10.00,NULL,'per_nail',0,'active','available',30),
('Sticker','Nail Art Add-ons','Nail art add-on priced per nail.',15.00,NULL,'per_nail',0,'active','available',31),
('Lash Lift','Lashes','Lash lift service.',400.00,NULL,'fixed',0,'active','available',32),
('Lash Lift with Tint','Lashes','Lash lift with tint.',600.00,NULL,'fixed',0,'active','available',33),
('Lash Extension - Classic','Lashes','Classic eyelash extension style.',599.00,NULL,'fixed',0,'active','available',34),
('Lash Extension - Hybrid','Lashes','Hybrid eyelash extension style.',649.00,NULL,'fixed',0,'active','available',35),
('Lash Extension - Cat-eye','Lashes','Cat-eye eyelash extension style.',649.00,NULL,'fixed',0,'active','available',36),
('Lash Extension - Wispy','Lashes','Wispy eyelash extension style.',649.00,NULL,'fixed',0,'active','available',37),
('Lash Extension - Volume','Lashes','Volume eyelash extension style.',699.00,NULL,'fixed',0,'active','available',38),
('Lash Extension - OMG Signature','Lashes','OMG Signature eyelash extension style.',749.00,NULL,'fixed',0,'active','available',39),
('Lash Refill','Lashes','Price varies based on the required refill.',150.00,299.00,'range',0,'active','available',40),
('Lash Removal','Lashes','Lash removal service.',150.00,NULL,'fixed',0,'active','available',41),
('Brow Laminate','Brows','Brow lamination service.',400.00,NULL,'fixed',0,'active','available',42),
('Brow Laminate with Tint','Brows','Brow lamination with tint.',650.00,NULL,'fixed',0,'active','available',43),
('Threading','Brows','Threading service.',199.00,NULL,'fixed',0,'active','available',44),
('Hair Cut','Hair','Hair cut service.',300.00,NULL,'fixed',0,'active','available',45),
('Shampoo & Blowdry','Hair','Shampoo and blow-dry service.',250.00,NULL,'fixed',0,'active','available',46),
('Roots','Hair','Roots service.',800.00,NULL,'fixed',0,'active','available',47),
('Brazilian','Hair','Brazilian hair service.',1500.00,NULL,'fixed',0,'active','available',48),
('Basic Hair Color','Hair','Basic hair color service.',1000.00,NULL,'fixed',0,'active','available',49),
('Highlights','Hair','Hair highlights service.',1500.00,NULL,'fixed',0,'active','available',50),
('Hair Rebond','Hair','Hair rebond service.',1500.00,NULL,'fixed',0,'active','available',51),
('Streaks','Hair','Hair streaks service.',800.00,NULL,'fixed',0,'active','available',52),
('Korean Perm','Hair','Korean perm service.',3500.00,NULL,'fixed',0,'active','available',53),
('Rebond L''Oréal','Hair','L''Oréal rebond service.',6000.00,NULL,'fixed',0,'active','available',54),
('Fashion Color - Short','Premium Hair Color','Premium fashion color for short hair.',1500.00,NULL,'fixed',0,'active','available',55),
('Fashion Color - Medium','Premium Hair Color','Premium fashion color for medium hair.',2000.00,NULL,'fixed',0,'active','available',56),
('Fashion Color - Long','Premium Hair Color','Premium fashion color for long hair.',2500.00,NULL,'fixed',0,'active','available',57),
('Zero Ammonia - Short','Premium Hair Color','Zero ammonia hair color for short hair.',2000.00,NULL,'fixed',0,'active','available',58),
('Zero Ammonia - Medium','Premium Hair Color','Zero ammonia hair color for medium hair.',2500.00,NULL,'fixed',0,'active','available',59),
('Zero Ammonia - Long','Premium Hair Color','Zero ammonia hair color for long hair.',3000.00,NULL,'fixed',0,'active','available',60),
('Rebonding Color - Short','Premium Hair Color','Rebonding color for short hair.',2000.00,NULL,'fixed',0,'active','available',61),
('Rebonding Color - Medium','Premium Hair Color','Rebonding color for medium hair.',2500.00,NULL,'fixed',0,'active','available',62),
('Rebonding Color - Long','Premium Hair Color','Rebonding color for long hair.',3000.00,NULL,'fixed',0,'active','available',63),
('Balayage - Short','Premium Hair Color','Balayage for short hair.',4000.00,NULL,'fixed',0,'active','available',64),
('Balayage - Medium','Premium Hair Color','Balayage for medium hair.',5000.00,NULL,'fixed',0,'active','available',65),
('Balayage - Long','Premium Hair Color','Balayage for long hair.',6000.00,NULL,'fixed',0,'active','available',66),
('Cysteine - Short','Hair Treatments','Cysteine treatment for short hair.',2500.00,NULL,'fixed',0,'active','available',67),
('Cysteine - Medium','Hair Treatments','Cysteine treatment for medium hair.',3000.00,NULL,'fixed',0,'active','available',68),
('Cysteine - Long','Hair Treatments','Cysteine treatment for long hair.',3500.00,NULL,'fixed',0,'active','available',69),
('Metal DX L''Oréal - Short','Hair Treatments','Metal DX L''Oréal treatment for short hair.',1500.00,NULL,'fixed',0,'active','available',70),
('Metal DX L''Oréal - Medium','Hair Treatments','Metal DX L''Oréal treatment for medium hair.',2000.00,NULL,'fixed',0,'active','available',71),
('Metal DX L''Oréal - Long','Hair Treatments','Metal DX L''Oréal treatment for long hair.',2500.00,NULL,'fixed',0,'active','available',72),
('Absolut Repair Molecular - Short','Hair Treatments','Absolut Repair Molecular for short hair.',2500.00,NULL,'fixed',0,'active','available',73),
('Absolut Repair Molecular - Medium','Hair Treatments','Absolut Repair Molecular for medium hair.',3000.00,NULL,'fixed',0,'active','available',74),
('Absolut Repair Molecular - Long','Hair Treatments','Absolut Repair Molecular for long hair.',3500.00,NULL,'fixed',0,'active','available',75),
('Collagen - Short','Hair Treatments','Collagen treatment for short hair.',2000.00,NULL,'fixed',0,'active','available',76),
('Collagen - Medium','Hair Treatments','Collagen treatment for medium hair.',2500.00,NULL,'fixed',0,'active','available',77),
('Collagen - Long','Hair Treatments','Collagen treatment for long hair.',3000.00,NULL,'fixed',0,'active','available',78),
('Wonder Smooth - Short','Hair Treatments','Wonder Smooth treatment for short hair.',2000.00,NULL,'fixed',0,'active','available',79),
('Wonder Smooth - Medium','Hair Treatments','Wonder Smooth treatment for medium hair.',2500.00,NULL,'fixed',0,'active','available',80),
('Wonder Smooth - Long','Hair Treatments','Wonder Smooth treatment for long hair.',3000.00,NULL,'fixed',0,'active','available',81),
('Power Duo - Cysteine + Collagen','Hair Treatments','Price range shown in the salon menu.',1500.00,2000.00,'range',0,'active','available',82),
('Power Duo - Wonder Smooth','Hair Treatments','Price range shown in the salon menu.',1500.00,2000.00,'range',0,'active','available',83),
('Aquatic Head Spa','Head Spa','Head massage, bed massage, and hair spa.',999.00,NULL,'fixed',0,'active','available',84),
('Korean Head Spa - Short','Head Spa','Korean Head Spa for short hair.',3500.00,NULL,'fixed',0,'active','available',85),
('Korean Head Spa - Medium','Head Spa','Korean Head Spa for medium hair.',4000.00,NULL,'fixed',0,'active','available',86),
('Korean Head Spa - Long','Head Spa','Korean Head Spa for long hair.',4500.00,NULL,'fixed',0,'active','available',87),
('Korean Head Spa + Scalp Treatment','Head Spa','Price range shown on the salon affordable price list.',3700.00,4500.00,'range',0,'active','available',88);

INSERT INTO booking_time_slots(slot_time,period,default_capacity,status,display_order) VALUES
('09:00:00','AM',5,'active',1),('10:00:00','AM',5,'active',2),('11:00:00','AM',5,'active',3),
('13:00:00','PM',5,'active',4),('14:00:00','PM',5,'active',5),('15:00:00','PM',5,'active',6),
('16:00:00','PM',5,'active',7),('17:00:00','PM',5,'active',8),('18:00:00','PM',5,'active',9),('19:00:00','PM',5,'active',10);

INSERT INTO system_settings(setting_key,setting_value) VALUES
('salon_name','Oh My Gracious'),('booking_days_ahead','90'),('default_slot_capacity','5'),('currency','PHP');

-- Select your EXISTING database before importing. Back up first.
CREATE TABLE IF NOT EXISTS payment_checkout_requests (
  booking_id INT PRIMARY KEY,
  request_key VARCHAR(80) NOT NULL UNIQUE,
  request_json MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  session_id VARCHAR(120) NULL UNIQUE,
  checkout_url TEXT NULL,
  UNIQUE KEY uq_request_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payment_webhook_events (
  event_key VARCHAR(190) PRIMARY KEY,
  booking_id INT NOT NULL,
  session_id VARCHAR(120) NOT NULL,
  payment_id VARCHAR(120) NOT NULL UNIQUE,
  amount_centavos BIGINT NOT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
