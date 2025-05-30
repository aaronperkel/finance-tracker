-- Initial data for the accounts table
-- These are the primary accounts to track assets and liabilities.

INSERT INTO accounts (name, type) VALUES ('Truist Checking', 'Asset');
INSERT INTO accounts (name, type) VALUES ('Truist Credit', 'Liability');
INSERT INTO accounts (name, type) VALUES ('Capital One Credit', 'Liability');
INSERT INTO accounts (name, type) VALUES ('Capital One Savings', 'Asset');
INSERT INTO accounts (name, type) VALUES ('Apple Credit', 'Liability');
INSERT INTO accounts (name, type) VALUES ('Apple Savings', 'Asset');
INSERT INTO accounts (name, type) VALUES ('AMEX Credit', 'Liability');
INSERT INTO accounts (name, type) VALUES ('Discover Credit', 'Liability');
INSERT INTO accounts (name, type) VALUES ('Chase Credit', 'Liability');
INSERT INTO accounts (name, type) VALUES ('Receivables', 'Asset'); -- For tracking money owed

-- Initial data for the app_settings table
-- These settings control application behavior, such as pay calculations.

INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_rate', '20.00'); -- Hourly pay rate
INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_day_1', '15');   -- First payday of the month
INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_day_2', '30');   -- Second payday of the month (use last day for months with < 30 days)
INSERT INTO app_settings (setting_key, setting_value) VALUES ('federal_tax_rate', '0.15'); -- Federal income tax rate (e.g., 0.15 for 15%)
INSERT INTO app_settings (setting_key, setting_value) VALUES ('state_tax_rate', '0.03');   -- State income tax rate (e.g., 0.03 for 3%)
