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