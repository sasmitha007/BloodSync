# BloodSync

BloodSync is a PHP and PostgreSQL-based blood donation management system that connects blood donors, hospitals, and administrators. The platform supports donor registration, medical report verification, blood stock management, hospital blood requests, appointments, events, notifications, and urgent blood needs tracking.

## Features

### Donor

- Donor account registration and login
- Donor profile management
- Medical report upload for verification
- Verification status tracking
- Appointment viewing for medical report collection
- Donation history and dashboard overview
- Event registration
- Notifications for account, appointment, and verification updates

### Hospital

- Hospital registration and login
- Hospital profile management
- Blood request submission
- Request history and request status tracking
- Hospital dashboard for request management

### Admin

- Admin dashboard with system statistics
- Donor verification and medical report review
- Hospital verification
- Blood stock management by blood type
- Blood request approval, rejection, and fulfillment
- Blood transaction tracking
- Donor donation records
- Appointment creation and management
- Event creation, approval, and management
- Urgent blood needs management
- Admin notifications and audit-style records
- Blood request export functionality

## Tech Stack

- **Backend:** PHP
- **Database:** PostgreSQL
- **Database Access:** PDO
- **Frontend:** HTML, Tailwind CSS, JavaScript
- **Icons:** Remix Icon
- **Email:** PHPMailer with SMTP
- **File Uploads:** Medical report upload support for PDF and image files

## Project Structure

```text
proj/
├── api/                    # API endpoints
│   └── verify_donor.php
├── assets/                 # CSS, images, and uploaded event assets
│   ├── css/
│   ├── img/
│   └── uploads/
├── config/                 # Database and API key configuration
│   ├── api_keys.php
│   └── database.php
├── core/                   # Core application classes
│   ├── Auth.php
│   ├── Database.php
│   └── Notification.php
├── handlers/               # Form processing scripts
├── pages/                  # Public, donor, hospital, event, and admin pages
│   ├── admin/
│   ├── events/
│   ├── hospital/
│   └── includes/
├── uploads/                # Uploaded medical reports
├── vendor/phpmailer/       # PHPMailer source files
├── autoload.php            # Application bootstrap and autoloader
└── setup.php               # Database table creation and sample data seeding
```

## Requirements

Install these before running the project:

- PHP 8.x or newer
- PostgreSQL
- Apache, Nginx, XAMPP, WAMP, Laragon, or PHP built-in server
- PHP extensions:
  - `pdo`
  - `pdo_pgsql`
  - `pgsql`
  - `fileinfo`
  - `openssl` for SMTP email

## Installation

### 1. Clone or copy the project

Place the project inside your local web server directory.

Example for XAMPP on Windows:

```text
C:\xampp\htdocs\project
```

Example for Linux Apache:

```text
/var/www/html/project
```

The current application constant in `autoload.php` is:

```php
define('BASE_URL', '/project');
```

If your folder name is not `project`, update this value to match your local path.

### 2. Create the PostgreSQL database

Open PostgreSQL and create a database named:

```sql
CREATE DATABASE bloodsync;
```

### 3. Configure the database connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'bloodsync');
define('DB_USER', 'your_postgres_username');
define('DB_PASS', 'your_postgres_password');
```

Do not commit real database passwords to a public repository.

### 4. Configure API key settings

Edit `config/api_keys.php` and replace the default value with a strong secret key:

```php
define('ADMIN_API_KEY', 'replace-with-a-secure-key');
```

Also check `api/verify_donor.php`. It currently uses its own hard-coded API key value, so update it to use the value from `config/api_keys.php` before relying on the API endpoint.

### 5. Configure email sending

Appointment emails use PHPMailer through Gmail SMTP. Update the SMTP settings in:

```text
pages/admin/create_appointment.php
```

Replace the hard-coded sender email and app password with your own SMTP account details. For production, move these values into environment variables or a private config file.

### 6. Run the database setup script

Start your local server, then open:

```text
http://localhost/project/setup.php
```

This script creates the required tables and inserts sample data for testing.

After the setup completes, remove or restrict access to `setup.php`. Leaving it publicly accessible is unsafe.

### 7. Open the application

Visit:

```text
http://localhost/project/pages/index.php
```

## Default Test Accounts

The setup script creates demo accounts for local testing:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@bloodsync.com` | `admin123` |
| Donor | `donor@bloodsync.com` | `donor123` |
| Hospital | `hospital@bloodsync.com` | `hospital123` |

Change these credentials immediately if you deploy or share the application.

## Main Pages

| Area | Path |
|---|---|
| Home | `pages/index.php` |
| Login | `pages/login.php` |
| Donor Registration | `pages/register.php` |
| Hospital Registration | `pages/register_hospital.php` |
| Donor Dashboard | `pages/dashboard.php` |
| Hospital Dashboard | `pages/hospital/hospital_dashboard.php` |
| Admin Dashboard | `pages/admin/dashboard.php` |
| Admin Medical Report Review | `pages/admin/verify_reports.php` |
| Admin Blood Stocks | `pages/admin/blood_stocks.php` |
| Admin Blood Requests | `pages/admin/blood_requests.php` |
| Events | `pages/events.php` |
| Urgent Needs | `pages/urgent_needs.php` |

## Database Tables Created by `setup.php`

The setup script creates the main system tables, including:

- `users`
- `donors`
- `hospitals`
- `medical_reports`
- `appointments`
- `notifications`
- `blood_stocks`
- `blood_transactions`
- `blood_requests`
- `donor_donations`
- `admin_notifications`
- `uploaded_files`
- `donor_medical_history`
- `donor_eligibility_log`
- `urgent_needs`
- `events`
- `event_requirements`
- `event_registrations`
- `event_categories`
- `event_category_mapping`
- `admin_logs`

## API Endpoint

### Verify Donor

```text
POST /api/verify_donor.php
```

Expected JSON body:

```json
{
  "user_id": 1,
  "status": "approved",
  "note": "Medical report verified.",
  "admin_id": 1
}
```

Use the `X-API-Key` header for authentication.

Example:

```bash
curl -X POST http://localhost/project/api/verify_donor.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: replace-with-your-api-key" \
  -d '{"user_id":1,"status":"approved","note":"Verified","admin_id":1}'
```

## File Uploads

Medical reports are uploaded under:

```text
uploads/medical_reports/
```

Supported upload types include PDF, JPG, JPEG, and PNG. Some upload handlers also reference DOC support, but the implementation is inconsistent, so verify upload validation before depending on DOC files.

## Security Notes

Before making this project public or deploying it, fix these issues:

- Remove hard-coded database passwords from `config/database.php`.
- Remove hard-coded API keys from `config/api_keys.php` and `api/verify_donor.php`.
- Remove hard-coded Gmail SMTP credentials from `pages/admin/create_appointment.php`.
- Do not commit files inside `uploads/` that contain real medical reports or personal data.
- Delete or protect `setup.php` after initial setup.
- Use environment variables for secrets.
- Add CSRF protection to sensitive forms.
- Validate uploaded files using server-side MIME checks, file extension checks, and randomized filenames.
- Restrict direct access to uploaded medical reports.
- Review SQL queries and route permissions before deployment.

## Suggested `.gitignore`

Create a `.gitignore` file like this:

```gitignore
# Local config and secrets
.env
config/local.php

# Uploaded user files
uploads/*
assets/uploads/*
!uploads/.gitkeep
!assets/uploads/.gitkeep

# OS/editor files
.DS_Store
Thumbs.db
.vscode/
.idea/

# Logs
*.log
```

## Common Troubleshooting

### Database connection failed

Check:

- PostgreSQL is running
- Database name is correct
- Username and password are correct
- `pdo_pgsql` is enabled in PHP
- The database exists before opening `setup.php`

### Pages load but redirects break

Check the value in `autoload.php`:

```php
define('BASE_URL', '/project');
```

Make sure it matches your folder name under the server root.

### Emails are not sent

Check:

- SMTP email address is correct
- Gmail app password is valid
- `openssl` is enabled in PHP
- Less secure direct Gmail password login is not used; Gmail requires app passwords for SMTP

### File upload fails

Check:

- `uploads/medical_reports/` exists
- The folder is writable by the web server
- Uploaded file size is under the configured limit
- File type is allowed by the upload handler

## Development Notes

This project is functional, but it is not production-ready yet. The biggest problems are hard-coded secrets, mixed configuration styles, duplicated upload logic, and inconsistent API key usage. Clean those up before treating this as a serious portfolio or deployment project.

## License

No license file is included. Add a license before publishing the project publicly.
