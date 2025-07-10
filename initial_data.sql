-- Initial data for the accounts table
-- These are the primary accounts to track assets and liabilities.

INSERT INTO accounts (name, type, sort_order) VALUES ('Roth IRA', 'Asset', 5);
INSERT INTO accounts (name, type, sort_order) VALUES ('Truist Checking', 'Asset', 10);
INSERT INTO accounts (name, type, sort_order) VALUES ('Capital One Savings', 'Asset', 20);
INSERT INTO accounts (name, type, sort_order) VALUES ('Apple Savings', 'Asset', 30);
INSERT INTO accounts (name, type, sort_order) VALUES ('Receivables', 'Asset', 40); -- For tracking money owed
INSERT INTO accounts (name, type, sort_order) VALUES ('Truist Credit', 'Liability', 100);
INSERT INTO accounts (name, type, sort_order) VALUES ('Capital One Credit', 'Liability', 110);
INSERT INTO accounts (name, type, sort_order) VALUES ('Apple Credit', 'Liability', 120);
INSERT INTO accounts (name, type, sort_order) VALUES ('AMEX Credit', 'Liability', 130);
INSERT INTO accounts (name, type, sort_order) VALUES ('Discover Credit', 'Liability', 140);
INSERT INTO accounts (name, type, sort_order) VALUES ('Chase Credit', 'Liability', 150);

-- Initial data for the app_settings table
-- These settings control application behavior, such as pay calculations.

INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_rate', '20.00'); -- Hourly pay rate
-- Remove old payday keys if they exist (optional, depends on migration strategy)
-- DELETE FROM app_settings WHERE setting_key IN ('pay_day_1', 'pay_day_2');
INSERT INTO app_settings (setting_key, setting_value) VALUES ('federal_tax_rate', '0.15'); -- Federal income tax rate (e.g., 0.15 for 15%)
INSERT INTO app_settings (setting_key, setting_value) VALUES ('state_tax_rate', '0.03');   -- State income tax rate (e.g., 0.03 for 3%)

-- Default pay schedule: Bi-weekly, with a placeholder reference Friday.
-- The user should update 'pay_schedule_detail1' to their actual reference Friday via the admin UI.
INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_schedule_type', 'bi-weekly');
INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_schedule_detail1', '2024-01-05'); -- Example: First Friday of 2024
INSERT INTO app_settings (setting_key, setting_value) VALUES ('pay_schedule_detail2', ''); -- Not used for bi-weekly
