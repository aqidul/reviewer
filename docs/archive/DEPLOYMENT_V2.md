# Version 2.0 - Deployment Instructions

## 📋 Pre-Deployment Checklist

### 1. Backup Current System
```bash
# Backup database
mysqldump -u reviewflow_user -p reviewflow > backup_pre_v2_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_files_pre_v2_$(date +%Y%m%d).tar.gz /var/www/palians/reviewer/
```

### 2. Verify Server Requirements
- PHP 7.4 or higher ✓
- MySQL 5.7 or higher ✓
- Apache/Nginx with mod_rewrite ✓
- Sufficient disk space (50MB+) ✓

## 🚀 Deployment Steps

### Step 1: Pull Changes from Repository

```bash
cd /var/www/palians/reviewer
git fetch origin
git checkout copilot/fix-wallet-balance-issue
git pull origin copilot/fix-wallet-balance-issue
```

### Step 2: Run Database Migration

```bash
mysql -u reviewflow_user -p'Malik@241123' reviewflow < migrations/add_brand_seller_to_tasks.sql
```

Verify migration success:
```bash
mysql -u reviewflow_user -p'Malik@241123' reviewflow -e "DESCRIBE tasks;"
```

Expected new columns:
- brand_name VARCHAR(100)
- seller_id INT
- review_request_id INT

### Step 3: Set File Permissions

```bash
# Make sure web server can read files
chmod 644 includes/*.php
chmod 644 assets/css/*.css
chmod 644 assets/js/*.js
chmod 644 chatbot/*.php

# Writable directories
chmod 755 logs/
chmod 755 uploads/
```

### Step 4: Clear All Caches

```bash
# Clear PHP opcache (if enabled)
sudo systemctl restart php7.4-fpm
# OR
sudo systemctl restart apache2

# Clear any application cache
rm -rf /var/www/palians/reviewer/cache/*
```

### Step 5: Verify Configuration

Check `includes/config.php`:
```php
const APP_VERSION = '2.0.0'; // Should be 2.0.0
const APP_URL = 'https://palians.com/reviewer'; // Verify correct
```

### Step 6: Test Key Features

#### A. Test Admin Dashboard
1. Login as admin
2. Verify version displays in bottom-left: "v2.0.0"
3. Click theme toggle (sun/moon icon) - should switch themes
4. Click chatbot icon - widget should open
5. Check "Export Data" menu item exists

#### B. Test Admin Login as Seller
1. Go to Admin > Sellers
2. Click "🔐 Login as Seller" on any active seller
3. Should redirect to seller dashboard
4. Orange banner should appear at top
5. Click "Return to Admin" - should go back to admin panel

#### C. Test Brand-wise Tasks
1. Go to Admin > Completed Tasks (Brand-wise)
2. Should see tasks grouped by brand folders
3. Click folder header - should expand/collapse
4. Verify task counts and amounts

#### D. Test Seller Dashboard
1. Login as seller (or use impersonation)
2. Verify version display
3. Test theme toggle
4. Test chatbot
5. If impersonating, verify orange banner appears

#### E. Test User Dashboard
1. Login as regular user
2. Verify version display
3. Test theme toggle
4. Test chatbot with user-specific quick actions

### Step 7: Monitor Error Logs

```bash
# Watch logs in real-time
tail -f /var/www/palians/reviewer/logs/error.log

# Check for any PHP errors
tail -f /var/log/apache2/error.log
# OR
tail -f /var/log/nginx/error.log
```

## 🔍 Post-Deployment Verification

### Automated Checks

```bash
# Check if all new files exist
cd /var/www/palians/reviewer

files=(
    "CHANGELOG.md"
    "README_V2.md"
    "includes/chatbot-widget.php"
    "includes/theme-switcher.php"
    "includes/impersonation-banner.php"
    "includes/version-display.php"
    "assets/css/themes.css"
    "assets/js/theme.js"
    "admin/login-as-seller.php"
    "admin/export-data.php"
    "admin/task-completed-brandwise.php"
    "chatbot/process.php"
    "migrations/add_brand_seller_to_tasks.sql"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ $file exists"
    else
        echo "✗ $file MISSING"
    fi
done
```

### Manual Verification Checklist

- [ ] Version 2.0.0 displays on all dashboards
- [ ] Theme toggle works on admin dashboard
- [ ] Theme toggle works on seller dashboard
- [ ] Theme toggle works on user dashboard
- [ ] Chatbot opens on admin dashboard
- [ ] Chatbot opens on seller dashboard
- [ ] Chatbot opens on user dashboard
- [ ] Admin can login as seller
- [ ] Impersonation banner shows correctly
- [ ] Admin can return from impersonation
- [ ] Brand-wise task page loads
- [ ] Brand folders expand/collapse
- [ ] Export data page accessible
- [ ] CSV export downloads successfully
- [ ] Theme preference persists after page reload
- [ ] No JavaScript console errors
- [ ] No PHP errors in logs

## 🐛 Troubleshooting

### Issue: Theme not switching

**Solution:**
```bash
# Clear browser cache
# Check browser console for errors
# Verify theme.js is loading:
curl -I https://palians.com/reviewer/assets/js/theme.js
```

### Issue: Chatbot not responding

**Solution:**
```bash
# Check chatbot endpoint
curl -X POST https://palians.com/reviewer/chatbot/process.php \
  -H "Content-Type: application/json" \
  -d '{"message":"test","userType":"admin","userId":0}'

# Check database tables
mysql -u reviewflow_user -p'Malik@241123' reviewflow \
  -e "SHOW TABLES LIKE 'chatbot%';"
```

### Issue: Impersonation not working

**Solution:**
```bash
# Check session directory permissions
ls -la /var/lib/php/sessions/

# Check admin-return.php permissions
ls -la /var/www/palians/reviewer/admin-return.php

# Verify sessions in database
mysql -u reviewflow_user -p'Malik@241123' reviewflow \
  -e "SELECT * FROM activity_logs WHERE action = 'admin_impersonation' ORDER BY created_at DESC LIMIT 5;"
```

### Issue: Export not working

**Solution:**
```bash
# Check PHP memory limit
php -i | grep memory_limit

# Verify database connection
mysql -u reviewflow_user -p'Malik@241123' reviewflow \
  -e "SELECT COUNT(*) FROM review_requests;"

# Check file permissions
ls -la admin/export-data.php
```

### Issue: Migration failed

**Solution:**
```bash
# Check if columns already exist
mysql -u reviewflow_user -p'Malik@241123' reviewflow \
  -e "SHOW COLUMNS FROM tasks LIKE '%brand%';"

# If exists but different, drop and recreate
mysql -u reviewflow_user -p'Malik@241123' reviewflow << EOF
ALTER TABLE tasks DROP COLUMN IF EXISTS brand_name;
ALTER TABLE tasks DROP COLUMN IF EXISTS seller_id;
ALTER TABLE tasks DROP COLUMN IF EXISTS review_request_id;
EOF

# Re-run migration
mysql -u reviewflow_user -p'Malik@241123' reviewflow < migrations/add_brand_seller_to_tasks.sql
```

## 📊 Performance Monitoring

### Key Metrics to Monitor

```bash
# Database query performance
mysql -u reviewflow_user -p'Malik@241123' reviewflow << EOF
SELECT 
    SUBSTRING(query,1,100) as query_start,
    exec_count,
    avg_timer_wait/1000000000000 as avg_time_sec
FROM performance_schema.events_statements_summary_by_digest 
WHERE schema_name = 'reviewflow'
ORDER BY avg_timer_wait DESC 
LIMIT 10;
EOF

# Page load times
# Use browser dev tools or New Relic

# Error rate
tail -100 logs/error.log | grep -c "ERROR"
```

## 🔐 Security Verification

```bash
# Check file permissions
find . -type f -perm 0777

# Check for exposed sensitive files
curl -I https://palians.com/reviewer/.git/config
curl -I https://palians.com/reviewer/.env
curl -I https://palians.com/reviewer/includes/config.php

# Verify session security
php -r "echo session_get_cookie_params()['httponly'] ? 'HttpOnly: OK' : 'HttpOnly: FAIL';"
```

## 📧 Notification to Users (Optional)

After successful deployment, consider notifying users:

**Subject:** ReviewFlow 2.0 - New Features Available!

**Body:**
```
Dear ReviewFlow Users,

We're excited to announce Version 2.0 is now live! 🎉

New Features:
✨ AI Chatbot for instant help
🌓 Light/Dark theme toggle
📊 Improved dashboards
📁 Brand-wise task organization
💾 Data export capabilities

Check out the new features today!

- ReviewFlow Team
```

## 📞 Support Contacts

- **Technical Issues**: tech@palians.com
- **User Support**: support@palians.com
- **Emergency**: +91-XXXX-XXXXXX

## ✅ Deployment Complete!

Once all checks pass, Version 2.0 is successfully deployed and ready for production use.

**Deployment Date**: ______________
**Deployed By**: ______________
**Verification Signed Off By**: ______________

---

**Document Version**: 1.0
**Last Updated**: February 2026
