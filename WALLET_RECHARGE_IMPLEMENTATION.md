# Offline Wallet Recharge System - Implementation Guide

## Overview
This implementation replaces the online payment gateway with an offline bank transfer system for seller wallet recharges.

## Database Setup

### 1. Run Migration Scripts
Execute the following SQL scripts in order:

```bash
# Step 1: Create wallet_recharge_requests table
mysql -u reviewflow_user -p reviewflow < migrations/wallet_recharge_requests.sql

# Step 2: Update payment_gateway ENUM
mysql -u reviewflow_user -p reviewflow < migrations/update_payment_gateway_enum.sql
```

### 2. Database Structure
The system creates a new table `wallet_recharge_requests`:
- `id` - Auto-increment primary key
- `seller_id` - Foreign key to sellers table
- `amount` - Recharge amount (DECIMAL 10,2)
- `utr_number` - Bank UTR/Transaction reference (VARCHAR 100)
- `screenshot_path` - Path to payment screenshot (VARCHAR 255)
- `transfer_date` - Date of bank transfer (DATE)
- `status` - ENUM('pending', 'approved', 'rejected')
- `admin_remarks` - Admin comments (TEXT)
- `approved_by` - Admin ID who approved/rejected (INT)
- `approved_at` - Approval/rejection timestamp (DATETIME)
- `created_at` - Request creation timestamp
- `updated_at` - Last update timestamp

## Features

### Seller Side (seller/wallet.php)

#### Bank Account Details Display
The system shows these bank details for manual transfer:
- Bank: State Bank Of India
- Account Holder: THE PALIANS
- Account Number: 41457761629
- IFSC Code: SBIN0005362
- Branch: EKTA NAGAR, BAREILLY

#### Recharge Request Form
Fields:
- Amount (₹) - Min: ₹100, Max: ₹1,00,000
- UTR Number - Transaction reference from bank
- Payment Screenshot - Image upload (JPG/JPEG/PNG, max 5MB)
- Date of Transfer - Calendar date picker

#### Request Status Tracking
Sellers can view their recharge requests with:
- Request ID
- Submission date & time
- Amount requested
- UTR number
- Transfer date
- Screenshot preview link
- Status (Pending/Approved/Rejected)
- Admin remarks (if any)

### Admin Side (admin/wallet-requests.php)

#### Request Management Dashboard
Features:
- Filter tabs (All/Pending/Approved/Rejected)
- Badge counters for each status
- Comprehensive request listing

#### Request Details Display
For each request, admins see:
- Seller name, email, mobile
- Amount requested
- UTR number
- Transfer date
- Screenshot preview
- Submission timestamp
- Current status

#### Approval/Rejection Actions
- **Approve**: Adds amount to seller wallet, creates payment transaction
- **Reject**: Requires admin remarks, marks request as rejected
- Modal confirmation for both actions
- Admin remarks field (optional for approval, required for rejection)

#### Navigation Integration
- Added to admin dashboard sidebar as "💳 Wallet Recharges"
- Shows pending count badge
- Alert card on dashboard when requests are pending

## Security Features

### File Upload Security
1. **MIME Type Validation**: Only image/jpeg, image/jpg, image/png allowed
2. **File Extension Validation**: Only .jpg, .jpeg, .png allowed
3. **File Size Limit**: 5MB maximum
4. **Image Verification**: Uses `getimagesize()` to verify actual image content
5. **Unique Filename**: Format: `wallet_{seller_id}_{timestamp}_{uniqid}.{ext}`
6. **File Permissions**: Set to 0644 after upload
7. **.htaccess Protection**: Prevents PHP execution in upload directory

### Input Validation
- Amount: Server-side validation for min/max limits
- UTR Number: Required, trimmed, max 100 chars
- Transfer Date: Required, date format validation
- Form CSRF protection via session validation

### Database Security
- Prepared statements for all SQL queries
- Transaction support for approval process
- Foreign key constraints
- Proper error logging without exposing sensitive data

## User Flow

### Seller Workflow
1. Navigate to Wallet page
2. Click "Add Money" button
3. View bank account details
4. Transfer money via their bank
5. Fill recharge request form with UTR and screenshot
6. Submit request
7. View request status in wallet page
8. Receive notification when approved/rejected

### Admin Workflow
1. Navigate to Wallet Recharges page from dashboard
2. View pending requests (or filter by status)
3. Click on screenshot to verify payment
4. Click "Approve" or "Reject" button
5. Add remarks (required for rejection)
6. Confirm action
7. System automatically:
   - Updates seller wallet balance
   - Creates payment transaction record
   - Updates request status
   - Seller can see updated status

## File Structure
```
reviewer/
├── admin/
│   ├── wallet-requests.php         # New admin page
│   └── dashboard.php                # Updated with navigation
├── seller/
│   └── wallet.php                   # Updated with offline flow
├── uploads/
│   └── wallet_screenshots/
│       ├── .htaccess               # Security protection
│       └── .gitkeep                # Directory tracking
└── migrations/
    ├── wallet_recharge_requests.sql      # Create table
    └── update_payment_gateway_enum.sql   # Update ENUM
```

## Testing Checklist

### Seller Side
- [ ] Bank details display correctly
- [ ] Form validation (amount limits)
- [ ] UTR number validation
- [ ] Date picker works
- [ ] Image upload accepts valid images
- [ ] Image upload rejects invalid files
- [ ] Image upload respects size limit
- [ ] Success message after submission
- [ ] Request appears in list immediately
- [ ] Screenshot link works

### Admin Side
- [ ] Page loads without errors
- [ ] Filter tabs work correctly
- [ ] Badge counts are accurate
- [ ] Request details display correctly
- [ ] Screenshot link opens in new tab
- [ ] Approve modal works
- [ ] Reject modal works (remarks required)
- [ ] Approval updates wallet balance
- [ ] Approval creates transaction record
- [ ] Status updates immediately

### Security
- [ ] .htaccess prevents PHP execution
- [ ] Invalid file types are rejected
- [ ] Large files are rejected
- [ ] Non-image files are rejected
- [ ] SQL injection protection
- [ ] XSS protection in outputs

## Troubleshooting

### Issue: "Database connection error"
**Solution**: Verify database credentials in `includes/config.php`

### Issue: "Failed to upload screenshot"
**Solution**: Check uploads/wallet_screenshots/ directory permissions (should be 0755)

### Issue: "Payment gateway ENUM error"
**Solution**: Run the `update_payment_gateway_enum.sql` migration

### Issue: Screenshot not displaying
**Solution**: Verify the file exists in uploads/wallet_screenshots/ and path is correct in database

### Issue: Navigation link not showing
**Solution**: Clear cache and ensure admin dashboard has been updated

## Maintenance

### Regular Tasks
1. Monitor pending requests daily
2. Verify screenshots match UTR numbers
3. Check for duplicate UTR submissions
4. Archive old approved/rejected requests (>90 days)
5. Monitor upload directory size

### Log Monitoring
- Check `logs/error.log` for any errors
- Monitor failed upload attempts
- Track approval/rejection patterns

## Support
For issues or questions, contact the development team or refer to the main project documentation.
