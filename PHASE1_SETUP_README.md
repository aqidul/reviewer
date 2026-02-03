# Phase 1: Core Infrastructure - Setup Instructions

## Overview

This document provides complete setup instructions for the Phase 1 Core Infrastructure features that have been implemented:

1. **Email Notifications System** - Automated email and SMS notifications with template management
2. **KYC Verification System** - User identity verification with document upload
3. **Analytics Dashboard** - Comprehensive analytics for Admin, Seller, and User roles
4. **Bulk Task Upload** - CSV-based bulk task assignment system

---

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for dependency management)
- SMTP server credentials (for email notifications)
- Web server (Apache/Nginx)

---

## Installation Steps

### 1. Install Dependencies

```bash
cd /path/to/reviewer
composer install
```

This will install:
- PHPMailer 6.8+ (for email notifications)
- Razorpay SDK 2.9+ (existing dependency)

### 2. Run Database Migrations

Execute the following SQL migration files in order:

```bash
# Connect to MySQL
mysql -u reviewflow_user -p reviewflow

# Run migrations
mysql -u reviewflow_user -p reviewflow < migrations/notifications_tables.sql
mysql -u reviewflow_user -p reviewflow < migrations/kyc_table.sql
mysql -u reviewflow_user -p reviewflow < migrations/bulk_upload_table.sql
```

**What these migrations do:**
- Creates `notification_templates` table with 8 default templates
- Creates `notification_queue` table for queued notifications
- Creates `user_kyc` table for KYC data
- Adds `kyc_status` column to `users` table
- Creates `bulk_upload_history` table for tracking uploads

### 3. Configure Email Settings

Update `/includes/config.php` with your SMTP credentials:

```php
// Email Settings
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'your-email@gmail.com';
const SMTP_PASS = 'your-app-password';
const SMTP_FROM = 'noreply@yourdomain.com';
const SMTP_FROM_NAME = 'ReviewFlow';
```

**For Gmail:**
1. Enable 2-factor authentication
2. Generate an app-specific password
3. Use the app password in `SMTP_PASS`

### 4. Set Directory Permissions

```bash
# Create and set permissions for upload directories
mkdir -p uploads/kyc
chmod 755 uploads/kyc

# Ensure templates directory is readable
chmod 755 templates
chmod 644 templates/bulk-task-template.csv
```

### 5. Update .gitignore

Add the following to `.gitignore` to prevent sensitive uploads from being committed:

```
uploads/kyc/*
!uploads/kyc/.gitkeep
vendor/
logs/
```

---

## Feature Documentation

### 1. Email Notifications System

#### Admin Interface
- **URL:** `/admin/notification-templates.php`
- **Access:** Admin only
- **Features:**
  - View all notification templates
  - Edit email subject and body
  - Edit SMS message body
  - Enable/disable templates
  - View queue statistics

#### Using Notifications Programmatically

```php
require_once __DIR__ . '/includes/Notifications.php';

$notifications = new Notifications($pdo);

// Send using template
$notifications->sendTemplateNotification(
    'task_assigned',           // Template type
    $userId,                    // User ID
    'user@example.com',         // Recipient
    [                          // Variables
        'user_name' => 'John Doe',
        'task_name' => 'Review Product',
        'reward_amount' => 100
    ],
    'email',                   // Channel (email/sms)
    false                      // Queue (true/false)
);

// Queue for later processing
$notifications->queueNotification(
    $userId,
    'task_completed',
    'email',
    'user@example.com',
    'Task Completed',
    'Your task has been completed...',
    '2024-02-04 10:00:00'     // Scheduled time (optional)
);

// Process queued notifications (run via cron)
$processed = $notifications->processQueue(50);
```

#### Available Templates
1. `task_assigned` - New task assigned to user
2. `task_completed` - Task completed successfully
3. `payment_received` - Payment received confirmation
4. `welcome_email` - Welcome email for new users
5. `kyc_verified` - KYC approved
6. `kyc_rejected` - KYC rejected
7. `withdrawal_approved` - Withdrawal approved
8. `withdrawal_rejected` - Withdrawal rejected

#### Template Variables
Use `{{variable_name}}` syntax in templates:
- `{{user_name}}` - User's name
- `{{task_name}}` - Task name
- `{{reward_amount}}` - Reward amount
- `{{amount}}` - Transaction amount
- `{{transaction_id}}` - Transaction ID
- `{{rejection_reason}}` - Rejection reason
- And more...

---

### 2. KYC Verification System

#### User Interface
- **URL:** `/user/kyc.php`
- **Access:** Logged-in users
- **Features:**
  - Submit KYC with documents
  - View KYC status
  - Resubmit if rejected

#### Admin Interface
- **List URL:** `/admin/kyc-verification.php`
- **View URL:** `/admin/kyc-view.php?id={kyc_id}`
- **Access:** Admin only
- **Features:**
  - View all KYC applications
  - Filter by status (pending/verified/rejected)
  - Approve/reject applications
  - View uploaded documents
  - Add rejection reason
  - Automatic email notifications

#### Document Requirements
- **Aadhaar:** 12 digits, clear scan/photo
- **PAN:** 10 characters (ABCDE1234F format)
- **Bank Passbook/Cheque:** Clear photo showing account details
- **File formats:** JPG, PNG, PDF
- **Max size:** 5MB per file

#### Helper Functions

```php
require_once __DIR__ . '/includes/kyc-functions.php';

// Get user's KYC
$kyc = getUserKYC($pdo, $userId);

// Get KYC by ID
$kyc = getKYCById($pdo, $kycId);

// Get all pending KYC
$pending = getAllPendingKYC($pdo);

// Update KYC status
updateKYCStatus($pdo, $kycId, 'verified', null, $adminId);

// Validate inputs
if (validateAadhaar($aadhaar)) { /* valid */ }
if (validatePAN($pan)) { /* valid */ }
if (validateIFSC($ifsc)) { /* valid */ }

// Display masked data
echo maskAadhaar('123456789012');  // XXXX XXXX 9012
echo maskPAN('ABCDE1234F');        // ABXXXXXX4F

// Get statistics
$stats = getKYCStats($pdo);
```

---

### 3. Analytics Dashboard

#### Admin Analytics
- **URL:** `/admin/analytics.php`
- **Access:** Admin only
- **Features:**
  - Revenue trends (30 days)
  - User growth chart
  - Task completion pie chart
  - Top 10 performers table
  - Summary statistics

#### Seller Analytics
- **URL:** `/seller/analytics.php`
- **Access:** Sellers only
- **Features:**
  - Spending trends
  - Request statistics
  - Monthly spending chart
  - Performance metrics

#### User Analytics
- **URL:** `/user/analytics.php`
- **Access:** Users only
- **Features:**
  - Earnings trends
  - Task distribution
  - Monthly earnings chart
  - Success rate

#### Helper Functions

```php
require_once __DIR__ . '/includes/analytics-functions.php';

// Get revenue statistics
$revenue = getRevenueStats($pdo, 30); // Last 30 days

// Get user growth
$growth = getUserGrowthStats($pdo, 30);

// Get task completion stats
$completion = getTaskCompletionStats($pdo);

// Get top performers
$topUsers = getTopPerformers($pdo, 10);

// Get dashboard summary
$summary = getDashboardSummary($pdo);

// Get user analytics
$analytics = getUserAnalytics($pdo, $userId);

// Get seller analytics
$analytics = getSellerAnalytics($pdo, $sellerId);

// Get task distribution
$distribution = getTaskDistribution($pdo);

// Get withdrawal trends
$trends = getWithdrawalTrends($pdo, 30);
```

---

### 4. Bulk Task Upload

#### Admin Interface
- **URL:** `/admin/bulk-upload.php`
- **Access:** Admin only
- **Features:**
  - Drag-and-drop CSV upload
  - Download template button
  - Real-time preview
  - Progress bar
  - Error reporting
  - Upload history

#### CSV Template Format

Download template from: `/templates/bulk-task-template.csv`

**Required Columns:**
```
brand_name,product_name,product_url,amazon_link,order_id,reward_amount,seller_id,seller_name,reviewer_mobile,reviewer_email,task_description
```

**Example Row:**
```
Acme Brand,Premium Headphones,https://example.com/product,https://amazon.in/product,ORD-12345,150,1,Acme Seller,9876543210,reviewer@example.com,Purchase and review the product
```

#### Validation Rules
- **brand_name**: Required, max 100 characters
- **product_name**: Required, max 200 characters
- **product_url**: Required, valid URL
- **amazon_link**: Required, valid URL
- **order_id**: Required, max 50 characters
- **reward_amount**: Required, numeric, > 0
- **seller_id**: Required, numeric, must exist
- **seller_name**: Optional, max 100 characters
- **reviewer_mobile**: Required, 10 digits
- **reviewer_email**: Required, valid email
- **task_description**: Optional, text

#### User Matching
- Tasks are assigned to users based on email OR mobile number
- If user doesn't exist, the row is skipped (error reported)
- Admin can manually create users before bulk upload if needed

#### Upload History
- All uploads are tracked in `bulk_upload_history` table
- View past uploads with success/error counts
- Download error logs for failed uploads

---

## Testing

### 1. Test Email Notifications

```bash
# Test sending a notification
php -r "
require_once 'includes/config.php';
require_once 'includes/Notifications.php';
\$n = new Notifications(\$pdo);
\$result = \$n->sendEmail('test@example.com', 'Test', 'Test message');
var_dump(\$result);
"
```

### 2. Test KYC Upload

1. Login as a user
2. Navigate to `/user/kyc.php`
3. Fill form with test data
4. Upload sample documents
5. Submit and verify in admin panel

### 3. Test Analytics

1. Login as admin/seller/user
2. Navigate to respective analytics page
3. Verify charts load correctly
4. Check data accuracy

### 4. Test Bulk Upload

1. Login as admin
2. Go to `/admin/bulk-upload.php`
3. Download template
4. Fill with 2-3 test rows
5. Upload and verify results

---

## Troubleshooting

### Email Notifications Not Sending

**Problem:** Emails not being sent

**Solutions:**
1. Check SMTP credentials in `/includes/config.php`
2. Verify SMTP server allows connections
3. Check error logs at `/logs/error.log`
4. Test with Gmail app password
5. Ensure PHPMailer is installed: `composer install`

### KYC Documents Not Uploading

**Problem:** Document upload fails

**Solutions:**
1. Check directory permissions: `chmod 755 uploads/kyc`
2. Verify file size < 5MB
3. Check allowed file types: JPG, PNG, PDF
4. Ensure `upload_max_filesize` in php.ini
5. Check error logs

### Analytics Charts Not Loading

**Problem:** Charts show blank or error

**Solutions:**
1. Check browser console for JavaScript errors
2. Verify Chart.js CDN is accessible
3. Check database has data
4. Ensure `analytics-functions.php` is included
5. Test database queries directly

### Bulk Upload Fails

**Problem:** CSV upload fails or shows errors

**Solutions:**
1. Download and use the provided template
2. Verify CSV format (UTF-8 encoding)
3. Check all required columns are present
4. Ensure users exist in database (for email/mobile matching)
5. Verify seller_id exists
6. Check file size < 10MB
7. Review error log in upload history

---

## Security Considerations

### 1. CSRF Protection
All forms include CSRF tokens. Don't disable `verifyCSRFToken()` checks.

### 2. File Uploads
- Only allowed extensions: JPG, PNG, PDF
- Maximum file size: 5MB
- Files stored outside web root when possible
- Filename sanitization implemented

### 3. SQL Injection
All queries use prepared statements. Never use string concatenation.

### 4. XSS Prevention
All output is escaped using `htmlspecialchars()` or `escape()` function.

### 5. Authentication
All admin pages check `$_SESSION['admin_name']`
All user pages check `$_SESSION['user_id']`
All seller pages check `$_SESSION['seller_id']`

---

## Maintenance

### Cron Jobs

#### Process Notification Queue
Run every 5 minutes:
```bash
*/5 * * * * php /path/to/reviewer/includes/process-notifications.php
```

Create `/includes/process-notifications.php`:
```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Notifications.php';

$notifications = new Notifications($pdo);
$processed = $notifications->processQueue(50);
echo "Processed $processed notifications\n";
```

### Database Maintenance

#### Clean Old Notification Queue
```sql
-- Delete sent notifications older than 90 days
DELETE FROM notification_queue 
WHERE status = 'sent' 
AND sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

#### Clean Old Bulk Upload History
```sql
-- Archive old bulk upload history (> 1 year)
DELETE FROM bulk_upload_history 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

---

## API Integration (Future)

### Notification API Endpoint
For external systems to send notifications:

```php
// POST /api/notifications.php
{
    "user_id": 123,
    "type": "task_assigned",
    "channel": "email",
    "variables": {
        "user_name": "John Doe",
        "task_name": "Review Product",
        "reward_amount": 100
    }
}
```

### Webhook for KYC Status
Notify external systems when KYC status changes:

```php
// Configure in /includes/config.php
const KYC_WEBHOOK_URL = 'https://your-system.com/webhook/kyc';
```

---

## Support

For issues or questions:
1. Check error logs: `/logs/error.log`
2. Review this documentation
3. Contact system administrator
4. Check GitHub issues

---

## Version History

- **v1.0.0** (2024-02-03) - Initial release
  - Email Notifications System
  - KYC Verification System
  - Analytics Dashboard
  - Bulk Task Upload

---

## License

This is proprietary software. Unauthorized copying or distribution is prohibited.

---

## Credits

Developed as part of Phase 1: Core Infrastructure for ReviewFlow SaaS Platform.
