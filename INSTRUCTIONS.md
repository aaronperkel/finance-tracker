# Financial Dashboard Instructions

This guide provides instructions on how to set up and use the Financial Dashboard application.

## I. Setup Guide

### 1. Server Requirements
To run this application, you will need:
*   PHP version 7.4 or later (a recent stable version is recommended).
*   MySQL database server (e.g., MySQL 5.7 or later, MariaDB 10.2 or later).
*   A web server (e.g., Apache, Nginx) configured to serve PHP applications.
*   Composer (PHP dependency manager) to install required libraries.

### 2. Database Setup

*   **Create Database:**
    *   First, you need to create a MySQL database where the application will store its data.
    *   You can do this using a database management tool like phpMyAdmin (look for an option like "Create new database").
    *   Alternatively, you can use the MySQL command line:
        ```sql
        CREATE DATABASE your_db_name;
        -- Replace "your_db_name" with your desired database name (e.g., finance_dashboard_db)
        ```

*   **Apply Schema:**
    *   The database schema defines the structure of the tables.
    *   Navigate to your project's `src` directory in your terminal or command prompt.
    *   Use a MySQL client to import the `finance_schema.sql` file into your newly created database. This will create all the necessary tables.
    *   Example using the MySQL command line:
        ```bash
        mysql -u your_username -p your_db_name < src/finance_schema.sql
        -- Replace "your_username" and "your_db_name" accordingly. You will be prompted for your password.
        ```
    *   If you are using phpMyAdmin:
        1.  Select your database.
        2.  Go to the "Import" tab.
        3.  Click "Choose File" and select `finance_schema.sql` from the `src` directory.
        4.  Click "Go" at the bottom of the page.

*   **Install Dependencies:**
    *   This project uses Composer to manage PHP dependencies, specifically the `vlucas/phpdotenv` library for environment variable management.
    *   Open your terminal or command prompt, navigate to the **root directory** of the project (the directory containing `composer.json`).
    *   Run the following command:
        ```bash
        composer install
        ```
        This will download and install the necessary libraries into a `vendor` directory.

### 3. Application Configuration

*   **Environment Variables:**
    *   The application uses a `.env` file to securely store database connection details and other sensitive configurations, keeping them separate from the main codebase.
    *   In the **root directory** of your project, create a new file named `.env`.
    *   Copy the following template into your new `.env` file:
        ```dotenv
        DBNAMEUTIL="your_db_name"
        DBUSER="your_db_username"
        DBPASS="your_db_password"
        ```
    *   Replace `your_db_name`, `your_db_username`, and `your_db_password` with the actual credentials for the database you created in the previous step.
    *   **Important:** Ensure that the `src/db.php` file correctly references the Composer autoloader. The typical path is `require_once __DIR__ . '/../vendor/autoload.php';`. This should already be set up correctly.

### 4. Initial Data Population

*   The file `initial_data.sql` (located in the project root or `src` directory, please ensure path is correct for command below) contains SQL commands to populate your database with essential starting data, such as default account names and application settings.
*   **Review and Customize (Recommended):**
    *   Before importing, open `initial_data.sql` with a text editor.
    *   **Accounts:** Review the list of accounts (e.g., 'Truist Checking', 'Capital One Credit'). Modify the names or add/remove accounts to accurately reflect your personal financial setup. Ensure the `type` ('Asset' or 'Liability') is correct for each.
    *   **App Settings:** Verify that the default `pay_rate` ('20.00'), `pay_day_1` ('15'), `pay_day_2` ('30'), `federal_tax_rate` ('0.15' for 15%), and `state_tax_rate` ('0.03' for 3%) are suitable for your needs. Remember that tax rates are stored as decimals (e.g., 0.15 represents 15%). Adjust these values if necessary.
*   **Import Data:**
    *   Once you've reviewed and (if necessary) customized the file, import `initial_data.sql` into your database using a MySQL client.
    *   Example using the MySQL command line (assuming the file is in the root):
        ```bash
        mysql -u your_username -p your_db_name < initial_data.sql
        ```
    *   Or, use the import feature in phpMyAdmin, similar to how you imported the schema.

## II. Usage Guide

### 1. Accessing the Application
*   Deploy the entire project folder (e.g., copy or clone it) to your web server's document root (often `htdocs`, `www`, or `public_html`) or a subdirectory within it.
*   Access the main dashboard by navigating to `src/dashboard.php` in your web browser.
    *   Example: `http://localhost/your_project_folder/src/dashboard.php`
    *   If you placed it in the root of your web server: `http://localhost/src/dashboard.php`

### 2. Adding Financial Snapshots
Financial snapshots record the state of your accounts on a specific date. This is crucial for tracking your net worth over time.
*   Navigate to `src/add_snapshot.php` in your browser.
    *   Example: `http://localhost/your_project_folder/src/add_snapshot.php`
*   **Select the Date:** Choose the date for which you are recording the balances.
*   **Enter Balances:** For each account listed:
    *   Enter the current balance as a numerical value (e.g., `1500.75`).
    *   **Assets** (e.g., checking accounts, savings accounts, value of investments, receivables) are positive values representing what you own.
    *   **Liabilities** (e.g., credit card debt, loans) should also be entered as **positive values** (e.g., `500` for a $500 credit card debt). The application automatically treats these as negative values in net worth calculations because their `type` is 'Liability'.
*   Click the **"Save balances"** button.

### 3. Using the Dashboard (`dashboard.php`)
The dashboard provides an overview of your financial situation.

*   **Financial Summary:** This section at the top gives you key figures:
    *   **Current Net Worth:** Calculated as (Total Assets - Total Liabilities) based on your most recent financial snapshot.
    *   **Total Cash on Hand:** The sum of balances from accounts specifically designated as primary cash/savings accounts. Currently, these are hardcoded in `src/api_financial_summary.php` as "Truist Checking", "Capital One Savings", and "Apple Savings". This may become configurable in a future update.
    *   **Receivables:** The balance of your "Receivables" account from the latest snapshot, representing money owed to you.
    *   **Total Owed:** The sum of all your liabilities from the latest snapshot.
    *   **Estimated Next Paycheck:** This is an *estimated net pay* after basic federal and state tax withholdings have been deducted from the gross pay. The gross pay is calculated from hours logged in the current pay period multiplied by your `pay_rate`. The tax rates used for this estimation are configurable in the Application Settings. The expected date of this paycheck is also displayed.
    *   **Future Net Worth:** Your Current Net Worth plus the Estimated Next Paycheck (net pay), giving a projection.
    *   *(You might also see "Debug Pay Period" start and end dates, which are for development and verification purposes, and the API also returns gross pay and estimated tax amounts, though they are not currently displayed on the dashboard UI).*
*   **Logging Work Hours:**
    *   Use the "Log Worked Hours" form on the dashboard.
    *   Select the `Date` you performed the work.
    *   Enter the number of `Hours Worked` (e.g., `8.5` for 8 and a half hours).
    *   Click **"Log Hours"**. The financial summary, particularly the "Estimated Next Paycheck" and "Future Net Worth", should update automatically.
*   **Net Worth Chart:**
    *   This chart visually displays your net worth history. Each point on the line graph corresponds to a financial snapshot you've saved, showing the trend of your net worth over time.

### 4. Managing Application Settings
Application settings such as your hourly pay rate, paydays, and tax rates are stored in the `app_settings` table in the database.

*   **Initial Setup:** These are initially set when you import `initial_data.sql`.
*   **Changing Settings After Setup:**
    *   **Using the Settings Page:** Navigate to `src/admin_settings.php` (link available in the navigation bar). Here you can update:
        *   `Pay Rate (per hour)`
        *   `First Pay Day of Month (1-31)`
        *   `Second Pay Day of Month (1-31)`
        *   `Federal Tax Rate (%)`: Enter as a percentage (e.g., 15 for 15%). It will be stored as a decimal (e.g., 0.15).
        *   `State Tax Rate (%)`: Enter as a percentage (e.g., 3 for 3%). It will be stored as a decimal (e.g., 0.03).
    *   **Direct Database Edit (Advanced):** You can also directly edit the values in the `app_settings` table using a database management tool (like phpMyAdmin), but using the settings page is recommended.

---
Remember to keep your `.env` file secure and never commit it to a public version control repository.
If you encounter issues, check your web server's error logs and PHP error logs for more detailed information.# Financial Dashboard Instructions

This guide provides instructions on how to set up and use the Financial Dashboard application.

## I. Setup Guide

### 1. Server Requirements
To run this application, you will need:
*   PHP version 7.4 or later (a recent stable version is recommended).
*   MySQL database server (e.g., MySQL 5.7 or later, MariaDB 10.2 or later).
*   A web server (e.g., Apache, Nginx) configured to serve PHP applications.
*   Composer (PHP dependency manager) to install required libraries.

### 2. Database Setup

*   **Create Database:**
    *   First, you need to create a MySQL database where the application will store its data.
    *   You can do this using a database management tool like phpMyAdmin (look for an option like "Create new database").
    *   Alternatively, you can use the MySQL command line:
        ```sql
        CREATE DATABASE your_db_name;
        -- Replace "your_db_name" with your desired database name (e.g., finance_dashboard_db)
        ```

*   **Apply Schema:**
    *   The database schema defines the structure of the tables.
    *   Navigate to your project's `src` directory in your terminal or command prompt.
    *   Use a MySQL client to import the `finance_schema.sql` file into your newly created database. This will create all the necessary tables.
    *   Example using the MySQL command line:
        ```bash
        mysql -u your_username -p your_db_name < src/finance_schema.sql
        -- Replace "your_username" and "your_db_name" accordingly. You will be prompted for your password.
        ```
    *   If you are using phpMyAdmin:
        1.  Select your database.
        2.  Go to the "Import" tab.
        3.  Click "Choose File" and select `finance_schema.sql` from the `src` directory.
        4.  Click "Go" at the bottom of the page.

*   **Install Dependencies:**
    *   This project uses Composer to manage PHP dependencies, specifically the `vlucas/phpdotenv` library for environment variable management.
    *   Open your terminal or command prompt, navigate to the **root directory** of the project (the directory containing `composer.json`).
    *   Run the following command:
        ```bash
        composer install
        ```
        This will download and install the necessary libraries into a `vendor` directory.

### 3. Application Configuration

*   **Environment Variables:**
    *   The application uses a `.env` file to securely store database connection details and other sensitive configurations, keeping them separate from the main codebase.
    *   In the **root directory** of your project, create a new file named `.env`.
    *   Copy the following template into your new `.env` file:
        ```dotenv
        DBNAMEUTIL="your_db_name"
        DBUSER="your_db_username"
        DBPASS="your_db_password"
        ```
    *   Replace `your_db_name`, `your_db_username`, and `your_db_password` with the actual credentials for the database you created in the previous step.
    *   **Important:** Ensure that the `src/db.php` file correctly references the Composer autoloader. The typical path is `require_once __DIR__ . '/../vendor/autoload.php';`. This should already be set up correctly.

### 4. Initial Data Population

*   The file `initial_data.sql` (located in the project root, ensure path is correct for command below) contains SQL commands to populate your database with essential starting data, such as default account names and application settings.
*   **Review and Customize (Recommended):**
    *   Before importing, open `initial_data.sql` with a text editor.
    *   **Accounts:** Review the list of accounts (e.g., 'Truist Checking', 'Capital One Credit'). Modify the names or add/remove accounts to accurately reflect your personal financial setup. Ensure the `type` ('Asset' or 'Liability') is correct for each.
    *   **App Settings:** Verify that the default `pay_rate` ('20.00'), `pay_day_1` ('15'), and `pay_day_2` ('30') are suitable for your needs. Adjust these values if necessary.
*   **Import Data:**
    *   Once you've reviewed and (if necessary) customized the file, import `initial_data.sql` into your database using a MySQL client.
    *   Example using the MySQL command line (assuming the file is in the root):
        ```bash
        mysql -u your_username -p your_db_name < initial_data.sql
        ```
    *   Or, use the import feature in phpMyAdmin, similar to how you imported the schema.

## II. Usage Guide

### 1. Accessing the Application
*   Deploy the entire project folder (e.g., copy or clone it) to your web server's document root (often `htdocs`, `www`, or `public_html`) or a subdirectory within it.
*   Access the main dashboard by navigating to `src/dashboard.php` in your web browser.
    *   Example: `http://localhost/your_project_folder/src/dashboard.php`
    *   If you placed it in the root of your web server: `http://localhost/src/dashboard.php`

### 2. Adding Financial Snapshots
Financial snapshots record the state of your accounts on a specific date. This is crucial for tracking your net worth over time.
*   Navigate to `src/add_snapshot.php` in your browser.
    *   Example: `http://localhost/your_project_folder/src/add_snapshot.php`
*   **Select the Date:** Choose the date for which you are recording the balances.
*   **Enter Balances:** For each account listed:
    *   Enter the current balance as a numerical value (e.g., `1500.75`).
    *   **Assets** (e.g., checking accounts, savings accounts, value of investments, receivables) are positive values representing what you own.
    *   **Liabilities** (e.g., credit card debt, loans) should also be entered as **positive values** (e.g., `500` for a $500 credit card debt). The application automatically treats these as negative values in net worth calculations because their `type` is 'Liability'.
*   Click the **"Save balances"** button.

### 3. Using the Dashboard (`dashboard.php`)
The dashboard provides an overview of your financial situation.

*   **Financial Summary:** This section at the top gives you key figures:
    *   **Current Net Worth:** Calculated as (Total Assets - Total Liabilities) based on your most recent financial snapshot.
    *   **Total Cash on Hand:** The sum of balances from accounts specifically designated as primary cash/savings accounts. Currently, these are hardcoded in `src/api_financial_summary.php` as "Truist Checking", "Capital One Savings", and "Apple Savings". This might become configurable in a future update.
    *   **Receivables:** The balance of your "Receivables" account from the latest snapshot, representing money owed to you.
    *   **Estimated Next Paycheck:** An estimation of your upcoming pay, calculated from hours logged in the current pay period multiplied by your `pay_rate`. The expected date of this paycheck is also displayed.
    *   **Future Net Worth:** Your Current Net Worth plus the Estimated Next Paycheck, giving a projection.
    *   *(You might also see "Debug Pay Period" start and end dates, which are for development and verification purposes).*
*   **Logging Work Hours:**
    *   Use the "Log Worked Hours" form on the dashboard.
    *   Select the `Date` you performed the work.
    *   Enter the number of `Hours Worked` (e.g., `8.5` for 8 and a half hours).
    *   Click **"Log Hours"**. The financial summary, particularly the "Estimated Next Paycheck" and "Future Net Worth", should update automatically.
*   **Net Worth Chart:**
    *   This chart visually displays your net worth history. Each point on the line graph corresponds to a financial snapshot you've saved, showing the trend of your net worth over time.

### 4. Managing Application Settings
Application settings like your hourly pay rate and paydays are stored in the `app_settings` table in the database.

*   **Initial Setup:** These are initially set when you import `initial_data.sql`.
*   **Changing Settings After Setup:**
    *   **Direct Database Edit (Recommended for now):** The simplest way to change these settings is to directly edit the rows in the `app_settings` table using a database management tool like phpMyAdmin.
        *   Open your database.
        *   Select the `app_settings` table.
        *   Edit the `setting_value` for `pay_rate`, `pay_day_1`, or `pay_day_2`.
    *   **API (Advanced):** The script `src/settings.php` provides an API to GET and POST settings. You can use a tool like Postman or `curl` to interact with this API if you are familiar with such tools. A user interface for these settings directly within the application is planned for a future update (this was Step 3 of the project plan).

---
Remember to keep your `.env` file secure and never commit it to a public version control repository.
If you encounter issues, check your web server's error logs and PHP error logs for more detailed information.
