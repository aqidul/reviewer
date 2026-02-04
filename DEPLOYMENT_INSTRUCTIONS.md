# Deployment Instructions - HTTP 500 Error Fix

## Overview
This deployment fixes the HTTP 500 error on `/user/dashboard.php` by implementing comprehensive error handling and logging throughout the application.

## Pre-Deployment Checklist

### 1. Database Status
- [ ] Verify MySQL server is running
  ```bash
  sudo systemctl status mysql
  ```
- [ ] Test database connection
  ```bash
  mysql -u reviewflow_user -p -h localhost -e "SELECT 1;"
  ```

### 2. File Permissions
- [ ] Ensure logs directory is writable
  ```bash
  chmod 755 /home/runner/work/reviewer/reviewer/logs
  ```
- [ ] Verify uploads directory is writable
  ```bash
  chmod 755 /home/runner/work/reviewer/reviewer/uploads
  ```

### 3. Configuration
- [ ] Verify database credentials in `includes/config.php`
- [ ] Ensure DEBUG is set to `false` in production
  ```php
  const DEBUG = false;
  ```

## Deployment Steps

### 1. Backup Current Files
```bash
cd /home/runner/work/reviewer/reviewer
cp includes/config.php includes/config.php.backup
cp user/dashboard.php user/dashboard.php.backup
cp user/includes/sidebar.php user/includes/sidebar.php.backup
```

### 2. Deploy Changes
```bash
git pull origin copilot/fix-http-500-error-dashboard
```

### 3. Verify File Permissions
```bash
chmod 644 includes/config.php
chmod 644 user/dashboard.php
chmod 644 user/includes/sidebar.php
chmod 755 logs
```

### 4. Clear Any Cached Sessions (Optional)
```bash
# If using file-based sessions
sudo rm /var/lib/php/sessions/sess_*

# If using Redis
redis-cli FLUSHALL
```

### 5. Test the Deployment

#### Test 1: Verify Syntax
```bash
php -l includes/config.php
php -l user/dashboard.php
php -l user/includes/sidebar.php
```

#### Test 2: Check Error Page (with DB down)
```bash
sudo systemctl stop mysql
curl -I http://localhost/reviewer/user/dashboard.php
# Should return HTTP 500
sudo systemctl start mysql
```

#### Test 3: Check Error Logs
```bash
tail -f logs/error.log
```

#### Test 4: Access Dashboard (with DB up)
```bash
# Access the page via browser or curl
curl http://localhost/reviewer/user/dashboard.php
```

## Post-Deployment Verification

### 1. Monitor Error Logs
```bash
# Watch for new errors
tail -f logs/error.log

# Check for database connection errors
grep "Database Connection Failed" logs/error.log
```

### 2. Test User Access
- [ ] Login as a test user
- [ ] Access `/user/dashboard.php`
- [ ] Verify page loads correctly
- [ ] Check that badges display properly
- [ ] Verify tasks and orders display

### 3. Performance Check
- [ ] Page load time is acceptable (< 2 seconds)
- [ ] No excessive logging
- [ ] Database queries are efficient

## Rollback Procedure

If issues occur after deployment:

### Quick Rollback
```bash
cd /home/runner/work/reviewer/reviewer
cp includes/config.php.backup includes/config.php
cp user/dashboard.php.backup user/dashboard.php
cp user/includes/sidebar.php.backup user/includes/sidebar.php
```

### Git Rollback
```bash
git revert HEAD~2..HEAD
git push origin copilot/fix-http-500-error-dashboard
```

## Monitoring

### Key Metrics to Watch

1. **HTTP 500 Error Rate**
   - Should decrease significantly
   - Monitor via server logs or APM tools

2. **Error Log Growth**
   ```bash
   watch -n 60 'wc -l logs/error.log'
   ```

3. **Database Connection Status**
   ```bash
   mysqladmin -u reviewflow_user -p status
   ```

### Alert Thresholds
- HTTP 500 errors: > 5 per minute = Critical
- Database connection failures: Any occurrence = Alert
- Error log growth: > 100 lines/hour = Warning

## Troubleshooting

### Issue: Still Getting HTTP 500 Errors

1. **Check error logs:**
   ```bash
   tail -50 logs/error.log
   ```

2. **Verify database connection:**
   ```bash
   php -r "require_once 'includes/config.php'; echo 'Success';"
   ```

3. **Enable debug mode temporarily:**
   - Edit `includes/config.php`
   - Set `const DEBUG = true;`
   - Access the page to see detailed errors
   - **Remember to disable after debugging!**

### Issue: Error Page Not Displaying

1. Check PHP error display settings:
   ```bash
   php -i | grep display_errors
   ```

2. Verify HTTP response code:
   ```bash
   curl -I http://localhost/reviewer/user/dashboard.php
   ```

### Issue: Logs Not Being Written

1. Check log directory permissions:
   ```bash
   ls -la logs/
   ```

2. Test log writing:
   ```bash
   php -r "error_log('Test message');"
   tail -1 logs/error.log
   ```

## Documentation References

- **Troubleshooting Guide**: `TROUBLESHOOTING.md`
- **Implementation Details**: `HTTP_500_FIX_SUMMARY.md`
- **Code Changes**: View PR diff

## Support

If issues persist:
1. Collect error logs
2. Note the steps to reproduce
3. Check PHP version and extensions
4. Verify database connectivity
5. Contact the development team with collected information

## Success Criteria

- ✅ No HTTP 500 errors on dashboard access
- ✅ Professional error page displays when database is down
- ✅ Detailed error logs available for debugging
- ✅ Page continues to render with degraded functionality
- ✅ No performance degradation
- ✅ All user functions work correctly

---

**Deployment Date**: 2026-02-04  
**Version**: 3.0.0  
**Deployed By**: GitHub Copilot Agent
