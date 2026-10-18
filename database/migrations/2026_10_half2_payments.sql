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
