-- finance_schema.sql
CREATE TABLE accounts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  type         ENUM('Asset','Liability') NOT NULL,
  sort_order   INT
);

CREATE TABLE snapshots (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  snapshot_date DATE          NOT NULL,
  created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE balances (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  snapshot_id  INT             NOT NULL
    REFERENCES snapshots(id)   ON DELETE CASCADE,
  account_id   INT             NOT NULL
    REFERENCES accounts(id)    ON DELETE CASCADE,
  balance      DECIMAL(12,2)   NOT NULL
);

CREATE TABLE logged_hours (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  log_date     DATE          NOT NULL UNIQUE,
  hours_worked DECIMAL(4,2)   NOT NULL
);

CREATE TABLE app_settings (
  setting_key  VARCHAR(50)   PRIMARY KEY,
  setting_value VARCHAR(100) NOT NULL
);
-- Key app_settings examples:
-- ('pay_rate', '25.00')
-- ('federal_tax_rate', '0.15')
-- ('state_tax_rate', '0.05')
-- Pay schedule is now hardcoded to bi-weekly with a fixed reference date (2025-05-30).
-- Thus, no database settings are needed for pay schedule type or details.

-- After applying the schema, you'll need to add initial data.
-- Example for Receivables account:
-- INSERT INTO accounts (name, type) VALUES ('Receivables', 'Asset');
--
-- Example for app_settings:
-- INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_rate', '20.00');

CREATE TABLE rent_payments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  rent_month   DATE          NOT NULL COMMENT 'First day of the month for which rent is paid (e.g., YYYY-MM-01)',
  paid_date    DATE          NOT NULL COMMENT 'Actual date rent was paid',
  amount       DECIMAL(10,2) NOT NULL,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_rent_month` (`rent_month`) COMMENT 'Ensure only one payment record per rent month'
);