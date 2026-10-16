SET NAMES utf8mb4;
USE oh_my_gracious;

ALTER DATABASE oh_my_gracious CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE services CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bookings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE booking_items CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE services ADD COLUMN IF NOT EXISTS category VARCHAR(80) NOT NULL DEFAULT 'General' AFTER service_name;
ALTER TABLE services ADD COLUMN IF NOT EXISTS booking_availability ENUM('available','unavailable') NOT NULL DEFAULT 'available' AFTER status;
ALTER TABLE services ADD COLUMN IF NOT EXISTS unavailable_reason VARCHAR(255) NULL AFTER booking_availability;
ALTER TABLE services MODIFY COLUMN duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0;
UPDATE services SET category='Nails' WHERE category='General' AND service_name IN ('Manicure','Pedicure','Gel Polish','Nail Extension');
UPDATE services SET category='Nail Art' WHERE category='General' AND service_name='Nail Art';

-- Guest/walk-in support. Existing customer bookings retain their user_id.
ALTER TABLE bookings MODIFY COLUMN user_id INT NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS phone_number VARCHAR(25) NULL AFTER email;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_source ENUM('customer','frontdesk','walk_in') NOT NULL DEFAULT 'customer' AFTER status;
ALTER TABLE bookings MODIFY COLUMN total_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0;
UPDATE bookings b JOIN users u ON u.id=b.user_id SET b.phone_number=COALESCE(b.phone_number,u.phone_number) WHERE b.user_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS booking_time_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slot_time TIME NOT NULL,
  period ENUM('AM','PM') NOT NULL,
  default_capacity SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  display_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_time_slot (slot_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO booking_time_slots(slot_time,period,default_capacity,status,display_order) VALUES
('09:00:00','AM',5,'active',1),('10:00:00','AM',5,'active',2),('11:00:00','AM',5,'active',3),
('13:00:00','PM',5,'active',4),('14:00:00','PM',5,'active',5),('15:00:00','PM',5,'active',6),
('16:00:00','PM',5,'active',7),('17:00:00','PM',5,'active',8),('18:00:00','PM',5,'active',9),('19:00:00','PM',5,'active',10)
ON DUPLICATE KEY UPDATE period=VALUES(period),display_order=VALUES(display_order);

CREATE TABLE IF NOT EXISTS booking_slot_inventory (
  slot_date DATE NOT NULL,
  slot_time TIME NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  reason VARCHAR(255) NULL,
  reserved_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_by INT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (slot_date,slot_time),
  KEY idx_inventory_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convert old duration-based booking-slot claims to one capacity claim at the appointment start time.
DELETE bs FROM booking_slots bs JOIN bookings b ON b.id=bs.booking_id WHERE bs.slot_time<>b.booking_time;
ALTER TABLE booking_slots DROP INDEX IF EXISTS uq_booking_slot;
ALTER TABLE booking_slots DROP INDEX IF EXISTS uq_booking_slot_claim;
ALTER TABLE booking_slots ADD UNIQUE KEY uq_booking_slot_claim (booking_id,slot_date,slot_time);
ALTER TABLE booking_slots ADD INDEX IF NOT EXISTS idx_booking_slot_date_time (slot_date,slot_time);
INSERT IGNORE INTO booking_slots(booking_id,slot_date,slot_time)
SELECT id,booking_date,booking_time FROM bookings WHERE status IN ('Pending','Approved','Checked-in');

INSERT INTO booking_slot_inventory(slot_date,slot_time,capacity,status,reserved_count)
SELECT b.booking_date,b.booking_time,GREATEST(5,COUNT(*)),'open',COUNT(*)
FROM bookings b
WHERE b.status IN ('Pending','Approved','Checked-in')
GROUP BY b.booking_date,b.booking_time
ON DUPLICATE KEY UPDATE reserved_count=VALUES(reserved_count),capacity=GREATEST(capacity,VALUES(reserved_count));

INSERT INTO system_settings(setting_key,setting_value) VALUES
('booking_days_ahead','90'),('default_slot_capacity','5')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- Google is the only external authentication method in the application now.
-- User rows are retained; only deprecated Facebook/phone identity links are removed.
DELETE FROM auth_identities WHERE provider IN ('facebook','phone');
DELETE a1 FROM auth_identities a1 JOIN auth_identities a2 ON a1.user_id=a2.user_id AND a1.provider=a2.provider AND a1.id>a2.id;
ALTER TABLE auth_identities MODIFY COLUMN provider ENUM('password','google') NOT NULL;
ALTER TABLE auth_identities ADD UNIQUE KEY IF NOT EXISTS uq_user_provider (user_id,provider);

-- Keep manual payment data only. Existing gateway columns, if present, are harmless and ignored by this release.
UPDATE payments SET gateway='manual' WHERE gateway IS NULL OR gateway='';
UPDATE bookings SET payment_method='Cash' WHERE payment_method IN ('Physical','PayMongo');
UPDATE payments SET method='Cash',gateway='manual' WHERE method IN ('Physical','PayMongo');
