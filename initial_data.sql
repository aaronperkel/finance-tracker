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

-- Comma-separated IDs of inactive accounts (managed on the Manage Accounts page).
-- This row must exist: the app updates it but never creates it.
INSERT INTO app_settings (setting_key, setting_value) VALUES ('inactive_accounts', '');
