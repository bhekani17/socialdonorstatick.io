# Social Donor Platform - PHP Backend Setup Guide

## Overview
This guide will help you set up the complete PHP backend for the Social Donor blood donation platform.

## Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher (or MariaDB 10.4+)
- Apache web server with mod_rewrite enabled
- PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`

## Database Setup

### 1. Create Database
```sql
CREATE DATABASE socialdonor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import Schema
Import the `database.sql` file:
```bash
mysql -u root -p socialdonor < database.sql
```

### 3. Create Database User (Recommended)
```sql
CREATE USER 'socialdonor'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON socialdonor.* TO 'socialdonor'@'localhost';
FLUSH PRIVILEGES;
```

## Configuration

### 1. Update Database Credentials
Edit `php/config.php` and update these lines:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'socialdonor');
define('DB_USER', 'socialdonor');  // Your database user
define('DB_PASS', 'your_secure_password');  // Your database password
```

### 2. Configure Email Settings
Update the SMTP settings in `php/config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('FROM_EMAIL', 'noreply@socialdonor.org');
define('FROM_NAME', 'Social Donor Platform');
```

### 3. Update Application URL
Set your application URL:
```php
define('APP_URL', 'http://localhost/socialdonorstatick.io');
```

## Directory Structure Setup

### 1. Create Required Directories
```bash
mkdir -p uploads/profiles
mkdir -p uploads/documents
mkdir -p logs
chmod 755 uploads
chmod 755 logs
```

### 2. Set Permissions
```bash
chmod 755 php/
chmod 644 php/*.php
```

## Web Server Configuration

### Apache (.htaccess is already included)
The `.htaccess` file provides:
- Security headers
- File upload protection
- Directory listing prevention
- PHP error handling
- URL rewriting

### Nginx Configuration (if using Nginx)
Add this to your Nginx config:
```nginx
location /php/ {
    try_files $uri $uri/ /php/index.php?$query_string;
    
    # PHP-FPM configuration
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    
    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
}

# Protect sensitive files
location ~ ^/(php/config\.php|database\.sql|\.env) {
    deny all;
}
```

## Frontend Integration

### 1. Update Form Actions
Update your HTML forms to use the new MySQL endpoints:

**Donor Login:**
```html
<form action="php/donor_login_mysql.php" method="post">
```

**Donor Signup:**
```html
<form action="php/donor_signup_mysql.php" method="post" enctype="multipart/form-data">
```

**Admin Login:**
```html
<form action="php/admin_login_mysql.php" method="post">
```

**Admin Signup:**
```html
<form action="php/admin_signup_mysql.php" method="post" enctype="multipart/form-data">
```

### 2. Add CSRF Protection
Include this in all your forms:
```html
<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
```

### 3. Add JavaScript for API Integration
```javascript
// Example: Fetch dashboard data
async function loadDashboard() {
    try {
        const response = await fetch('php/dashboard_api.php');
        const data = await response.json();
        
        if (data.success) {
            updateDashboardUI(data.data);
        }
    } catch (error) {
        console.error('Dashboard error:', error);
    }
}

// Example: Fetch blood requests
async function loadBloodRequests() {
    try {
        const response = await fetch('php/blood_requests_api.php');
        const data = await response.json();
        
        if (data.success) {
            updateRequestsTable(data.data);
        }
    } catch (error) {
        console.error('Requests error:', error);
    }
}
```

## Testing the Setup

### 1. Test Database Connection
Create a test file `test_db.php`:
```php
<?php
require 'php/config.php';
try {
    $pdo = get_db_connection();
    echo "Database connection successful!";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
```

### 2. Test Registration
1. Go to `donor signup.html`
2. Fill out the form
3. Check if data appears in the `donors` table

### 3. Test Login
1. Use the credentials you just created
2. Verify session is created
3. Check `user_sessions` table

## Security Features Implemented

### 1. Authentication & Authorization
- Secure password hashing (bcrypt)
- Session management with database storage
- CSRF protection
- Login attempt limiting
- Role-based access control

### 2. Input Validation
- Email validation
- South African phone number validation
- ID number validation
- Blood type validation
- File upload security

### 3. Database Security
- Prepared statements (SQL injection prevention)
- Input sanitization
- Error logging
- Audit trail

### 4. File Upload Security
- File type validation
- Size limits
- Secure storage
- Access control

## API Endpoints

### Authentication
- `POST php/donor_login_mysql.php` - Donor login
- `POST php/donor_signup_mysql.php` - Donor registration
- `POST php/admin_login_mysql.php` - Admin login
- `POST php/admin_signup_mysql.php` - Admin registration
- `GET php/logout.php` - Logout

### Dashboard & Data
- `GET php/dashboard_api.php` - Dashboard statistics
- `GET php/profile_management.php?type=donor` - Get donor profile
- `PUT php/profile_management.php?type=donor` - Update donor profile

### Blood Requests
- `GET php/blood_requests_api.php` - Get blood requests
- `POST php/blood_requests_api.php` - Create blood request (admin only)
- `PUT php/blood_requests_api.php` - Update blood request (admin only)
- `DELETE php/blood_requests_api.php` - Delete blood request (admin only)

### Alerts
- `GET php/alerts_api.php` - Get alerts
- `POST php/alerts_api.php` - Create alert (admin only)
- `PUT php/alerts_api.php` - Update alert (admin only)
- `DELETE php/alerts_api.php` - Delete alert (admin only)

## Default Admin Account
After database setup, you can login with:
- Email: `admin@socialdonor.org`
- Password: `Admin@12345`

**Important:** Change this password immediately after first login!

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check MySQL service is running
   - Verify database credentials
   - Ensure database exists

2. **File Upload Not Working**
   - Check directory permissions
   - Verify upload_max_filesize in php.ini
   - Ensure file_uploads is enabled

3. **Email Not Sending**
   - Check SMTP credentials
   - Verify firewall allows SMTP traffic
   - Check email server logs

4. **Session Issues**
   - Check session.save_path in php.ini
   - Verify directory permissions
   - Check cookie settings

### Error Logs
Check these logs for debugging:
- `logs/error.log` - Application errors
- Apache error log: `/var/log/apache2/error.log`
- PHP error log: `/var/log/php_errors.log`

## Production Deployment

### 1. Security Checklist
- [ ] Change all default passwords
- [ ] Enable HTTPS (SSL certificate)
- [ ] Set proper file permissions
- [ ] Configure firewall
- [ ] Enable database backups
- [ ] Set up monitoring
- [ ] Test all security features

### 2. Performance Optimization
- Enable PHP OPcache
- Configure MySQL query cache
- Enable Gzip compression
- Use CDN for static assets
- Implement database indexing

### 3. Backup Strategy
```bash
# Database backup
mysqldump -u socialdonor -p socialdonor > backup_$(date +%Y%m%d).sql

# File backup
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/
```

## Support
For issues and questions:
1. Check error logs
2. Verify database connection
3. Test with minimal configuration
4. Review this documentation

## License
This project is part of the Social Donor platform. See LICENSE file for details.
