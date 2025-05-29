-- finance_schema.sql
CREATE TABLE accounts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  type         ENUM('Asset','Liability') NOT NULL
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

-- After applying the schema, you'll need to add initial data.
-- Example for Receivables account:
-- INSERT INTO accounts (name, type) VALUES ('Receivables', 'Asset');
--
-- Example for app_settings:
-- INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_rate', '20.00');
-- INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_day_1', '15');
-- INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_day_2', '30');