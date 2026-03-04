# UBIDS Student ID Card Photo Portal

A secure, scalable university ID card photo submission and approval system for the University of Business and Integrated Development Studies (UBIDS).

## Features

### For Students
- **Dual Authentication**: Separate login paths for new and continuing students
- **Multi-Stage Upload**: Step-by-step photo submission with validation
- **Real-time Status Tracking**: Monitor submission progress with auto-refresh
- **Mobile Responsive**: Fully functional on all devices
- **Secure File Upload**: Protected upload with validation and processing

### For Administrators
- **TailAdmin-Inspired Interface**: Clean, modern admin panel
- **Bulk Operations**: Approve/reject multiple submissions at once
- **Advanced Filtering**: Search and filter by status, type, department
- **ID Card Generation**: Automated card creation with student data
- **Audit Trail**: Complete logging of all system actions

### Technical Features
- **Security-First**: CSRF protection, session management, input validation
- **Email Notifications**: Automated emails for submission status changes
- **Image Processing**: Automatic resizing, brightness analysis, validation
- **Database Logging**: Comprehensive audit trail and activity tracking
- **Performance Optimized**: Efficient queries, caching, and optimization

## Requirements

- **PHP**: 8.2 or higher
- **MySQL**: 8.0 or higher
- **Extensions**: PDO, GD, Fileinfo, OpenSSL
- **Web Server**: Apache (recommended) or Nginx

## Installation

### 1. Setup Database

```bash
# Create database
mysql -u root -p
CREATE DATABASE ubids_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configure Application

1. Copy the files to your web directory
2. Set proper permissions:
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   ```

### 3. Run Installation

Access `http://your-domain/install.php` in your browser and follow the installation wizard.

### 4. Security Setup

1. Delete `install.php` after installation
2. Configure HTTPS in production
3. Set up proper file permissions
4. Configure email settings in `includes/config.php`

## Configuration

### Database Settings

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ubids_portal');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

### Email Settings

```php
define('SMTP_HOST', 'your_smtp_host');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@domain.com');
define('SMTP_PASSWORD', 'your_email_password');
```

### File Upload Limits

```php
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('MIN_PHOTO_WIDTH', 390);
define('MIN_PHOTO_HEIGHT', 540);
```

## Directory Structure

```
ubids-portal/
├── admin/                  # Admin panel files
│   ├── index.php          # Admin dashboard
│   ├── login.php          # Admin login
│   ├── submissions.php    # Submissions management
│   └── review.php         # Individual submission review
├── includes/              # Core functionality
│   ├── config.php         # Configuration settings
│   ├── db.php            # Database connection
│   ├── auth.php          # Authentication functions
│   ├── functions.php     # Helper functions
│   ├── mailer.php        # Email system
│   └── id_card_generator.php # ID card generation
├── uploads/               # File storage
│   ├── photos/           # Student photos
│   ├── id_docs/          # Identity documents
│   └── generated_cards/  # Generated ID cards
├── assets/               # Frontend assets
│   ├── css/             # Stylesheets
│   └── js/              # JavaScript files
├── templates/            # Reusable templates
├── api/                 # AJAX endpoints
├── logs/                # Application logs
└── config/              # Environment configs
```

## Student Workflow

1. **Login**: Select student type (new/continuing) and enter credentials
2. **Dashboard**: View personal info and submission status
3. **Upload Photo**: Multi-stage upload with validation
   - Step 1: Upload headshot with real-time validation
   - Step 2: Preview ID card layout
   - Step 3: Upload identity document
4. **Status Tracking**: Monitor progress with automatic updates

## Admin Workflow

1. **Login**: Secure admin authentication
2. **Dashboard**: View statistics and recent submissions
3. **Review Submissions**: 
   - Filter and search submissions
   - Review photos and documents
   - Approve/reject with reasons
   - Generate ID cards
4. **Bulk Operations**: Process multiple submissions
5. **Export Data**: Download reports and statistics

## Security Features

- **CSRF Protection**: All forms protected with tokens
- **Session Management**: Secure session handling with timeout
- **Input Validation**: Comprehensive input sanitization
- **File Security**: Protected upload directories with .htaccess
- **SQL Injection Prevention**: PDO prepared statements only
- **XSS Protection**: Output escaping and CSP headers
- **Rate Limiting**: Login attempt protection
- **Audit Logging**: Complete activity tracking

## Email Notifications

The system automatically sends emails for:

- **Submission Received**: Confirmation when student submits
- **Approval Notification**: When submission is approved
- **Rejection Notice**: When submission is rejected with reason
- **ID Card Ready**: When card is ready for collection

## ID Card Generation

The system generates professional ID cards with:

- Student photo integration
- Personal information layout
- University branding
- Security features (watermarks, unique IDs)
- High-quality output (PNG format)

## Performance Optimization

- **Database Indexing**: Optimized queries with proper indexes
- **Image Processing**: Efficient resizing and compression
- **Caching**: Session-based caching for frequently accessed data
- **Compression**: Gzip compression for faster loading
- **Minified Assets**: Optimized CSS and JavaScript

## Maintenance

### Regular Tasks

1. **Database Backup**: Regular database backups
2. **Log Rotation**: Clean up old log files
3. **Email Queue**: Process queued emails
4. **File Cleanup**: Remove temporary files

### Monitoring

- Monitor error logs in `logs/` directory
- Check email queue status
- Monitor disk space for uploads
- Review audit logs for security

## Troubleshooting

### Common Issues

1. **Upload Failures**: Check file permissions and PHP upload limits
2. **Email Issues**: Verify SMTP configuration and credentials
3. **Database Errors**: Check connection settings and permissions
4. **Image Processing**: Ensure GD extension is installed

### Error Logs

Check `logs/php_errors.log` for PHP errors and database issues.

## Support

For technical support:

- **Email**: it@ubids.edu.gh
- **Phone**: 050-123-4567
- **Documentation**: Check the `docs/` directory for detailed guides

## License

© 2024 University of Business and Integrated Development Studies

## Version History

- **v1.0.0**: Initial release with core functionality
  - Student portal with dual authentication
  - Admin panel with TailAdmin interface
  - ID card generation system
  - Email notification system
  - Complete audit logging
