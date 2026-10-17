SET NAMES utf8mb4;
USE oh_my_gracious;

-- Half 1 finalization: real salon catalog + safer pricing model.
-- Run this ONCE after 2026_10_pre_payment_capacity_google_only.sql.

ALTER TABLE services
  ADD COLUMN IF NOT EXISTS price_max DECIMAL(10,2) NULL AFTER price;

ALTER TABLE services
  ADD COLUMN IF NOT EXISTS pricing_type ENUM('fixed','per_nail','range') NOT NULL DEFAULT 'fixed' AFTER price_max;

ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS amount_is_estimate TINYINT(1) NOT NULL DEFAULT 0 AFTER amount;

ALTER TABLE booking_items
  ADD COLUMN IF NOT EXISTS quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER unit_price;

ALTER TABLE booking_items
  ADD COLUMN IF NOT EXISTS line_total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER quantity;

UPDATE booking_items
SET line_total = unit_price * quantity
WHERE line_total = 0;

-- Retire the old demo catalog without deleting it. This preserves any historical references.
UPDATE services
SET status='inactive', booking_availability='unavailable', unavailable_reason='Replaced by the real salon service catalog'
WHERE service_name IN ('Manicure','Pedicure','Gel Polish','Nail Extension','Nail Art');

-- Insert the real prices gathered from the salon menus.
-- INSERT IGNORE means an existing owner-edited service with the same name is not overwritten.
INSERT IGNORE INTO services
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

-- Existing services that predate this migration remain fixed-price unless explicitly changed by the owner.
UPDATE services
SET pricing_type='fixed'
WHERE pricing_type IS NULL OR pricing_type='';
