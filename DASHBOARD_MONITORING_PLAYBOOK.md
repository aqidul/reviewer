# Production Dashboard Monitoring & Troubleshooting Playbook

## Quick Reference Guide for Production Support

---

## 🚨 Emergency Response Checklist

### If Dashboard Returns HTTP 500

**Immediate Actions (< 5 minutes):**

1. **Check MySQL Status**
   ```bash
   sudo systemctl status mysql
   # If stopped, start it:
   sudo systemctl start mysql
   ```

2. **Check Error Logs**
   ```bash
   tail -50 /home/runner/work/reviewer/reviewer/logs/error.log
   ```

3. **Verify Database Connection**
   ```bash
   mysql -u reviewflow_user -p reviewflow -e "SELECT 1"
   ```

4. **Check Disk Space**
   ```bash
   df -h
   # If disk is full, clean up space
   ```

5. **Review Recent Changes**
   ```bash
   cd /home/runner/work/reviewer/reviewer
   git log --oneline -5
   ```

---

## 📊 Daily Monitoring Routine

### Morning Checks (5 minutes)

```bash
#!/bin/bash
# Daily health check script

echo "=== ReviewFlow Dashboard Health Check ==="
echo "Date: $(date)"
echo ""

# 1. Check MySQL
echo "1. MySQL Status:"
sudo systemctl status mysql | grep -E "Active|running"

# 2. Check Error Logs (last 24 hours)
echo ""
echo "2. Recent Errors (last 24 hours):"
find /home/runner/work/reviewer/reviewer/logs -name "*.log" -mtime -1 -exec tail -20 {} \; | grep -i "error\|critical\|fatal" | wc -l
echo "   errors found in last 24h"

# 3. Check Disk Space
echo ""
echo "3. Disk Space:"
df -h / | tail -1

# 4. Test Dashboard Accessibility
echo ""
echo "4. Dashboard Accessibility:"
curl -s -o /dev/null -w "%{http_code}" https://palians.com/reviewer/user/dashboard.php
echo ""

# 5. Database Connection Test
echo ""
echo "5. Database Connection:"
mysql -u reviewflow_user -p'Malik@241123' reviewflow -e "SELECT 'OK' as status" 2>&1 | grep OK

echo ""
echo "=== Health Check Complete ==="
```

Save as: `/usr/local/bin/dashboard-health-check.sh`

---

## 🔍 Diagnostic Commands

### 1. Quick Status Check

```bash
# One-liner to check overall system health
echo "MySQL: $(systemctl is-active mysql) | Disk: $(df -h / | tail -1 | awk '{print $5}') | Errors: $(tail -100 /home/runner/work/reviewer/reviewer/logs/error.log 2>/dev/null | grep -i error | wc -l)"
```

---

### 2. Database Performance Check

```sql
-- Check active connections
SHOW PROCESSLIST;

-- Check slow queries
SHOW STATUS LIKE '%slow%';

-- Check table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'reviewflow'
ORDER BY size_mb DESC;

-- Check for locked tables
SHOW OPEN TABLES WHERE In_use > 0;
```

---

### 3. Error Log Analysis

```bash
# Count errors by type in last hour
tail -1000 /home/runner/work/reviewer/reviewer/logs/error.log | \
grep "$(date +%Y-%m-%d\ %H)" | \
grep -oP 'Database Connection Failed|query error|initialization error' | \
sort | uniq -c

# Find most common errors today
grep "$(date +%Y-%m-%d)" /home/runner/work/reviewer/reviewer/logs/error.log | \
grep -oP '(Database.*?)\|' | \
sort | uniq -c | sort -rn | head -5

# Check for specific user errors
grep "user_id: [0-9]*" /home/runner/work/reviewer/reviewer/logs/error.log | \
tail -20
```

---

### 4. Performance Monitoring

```bash
# Monitor active PHP processes
ps aux | grep php-fpm | wc -l

# Check PHP-FPM status (if using PHP-FPM)
curl http://localhost/php-fpm-status

# Monitor real-time access
tail -f /var/log/nginx/access.log | grep dashboard.php

# Check response times
curl -w "@-" -o /dev/null -s https://palians.com/reviewer/user/dashboard.php <<'EOF'
time_namelookup:  %{time_namelookup}\n
time_connect:     %{time_connect}\n
time_starttransfer: %{time_starttransfer}\n
time_total:       %{time_total}\n
EOF
```

---

## 🔧 Common Issues & Solutions

### Issue 1: Database Connection Failures

**Symptoms:**
- HTTP 500 errors
- "Service Temporarily Unavailable" page
- Error log shows: `Database Connection Failed`

**Diagnosis:**
```bash
# Check MySQL status
sudo systemctl status mysql

# Check MySQL error log
sudo tail -50 /var/log/mysql/error.log

# Test connection
mysql -u reviewflow_user -p reviewflow
```

**Solutions:**

1. **MySQL is stopped:**
   ```bash
   sudo systemctl start mysql
   sudo systemctl enable mysql  # Ensure it starts on boot
   ```

2. **Too many connections:**
   ```sql
   SHOW VARIABLES LIKE 'max_connections';
   SET GLOBAL max_connections = 500;
   ```
   Make permanent in `/etc/mysql/my.cnf`:
   ```ini
   [mysqld]
   max_connections = 500
   ```

3. **Incorrect credentials:**
   - Verify in `/home/runner/work/reviewer/reviewer/includes/config.php`
   - Test: `mysql -u reviewflow_user -p'PASSWORD' reviewflow`

4. **Database doesn't exist:**
   ```sql
   CREATE DATABASE IF NOT EXISTS reviewflow;
   ```

---

### Issue 2: Slow Dashboard Performance

**Symptoms:**
- Dashboard takes > 5 seconds to load
- Users report timeouts
- High server load

**Diagnosis:**
```bash
# Check server load
uptime

# Check MySQL performance
mysqladmin -u root -p processlist

# Check slow queries
mysql -u root -p -e "SHOW FULL PROCESSLIST;"
```

**Solutions:**

1. **Add database indexes:**
   ```sql
   USE reviewflow;
   
   -- Task lookups
   ALTER TABLE tasks ADD INDEX idx_user_status (user_id, task_status);
   ALTER TABLE tasks ADD INDEX idx_user_assigned (user_id, assigned_date);
   
   -- Order lookups
   ALTER TABLE orders ADD INDEX idx_task_refund (task_id, refund_status);
   ALTER TABLE orders ADD INDEX idx_submitted (submitted_at);
   
   -- Announcement lookups
   ALTER TABLE announcements ADD INDEX idx_active_dates (is_active, start_date, end_date);
   ```

2. **Optimize tables:**
   ```sql
   OPTIMIZE TABLE tasks, orders, users, announcements;
   ```

3. **Enable query cache** (if using MySQL < 8.0):
   ```ini
   [mysqld]
   query_cache_type = 1
   query_cache_size = 64M
   ```

4. **Increase PHP memory:**
   ```ini
   # php.ini
   memory_limit = 256M
   max_execution_time = 60
   ```

---

### Issue 3: Missing Data on Dashboard

**Symptoms:**
- Dashboard loads but shows 0 tasks/orders
- Statistics show incorrect counts
- Sidebar badges missing

**Diagnosis:**
```bash
# Check if user has data
mysql -u reviewflow_user -p reviewflow -e "
SELECT 
    u.id, u.name, 
    COUNT(DISTINCT t.id) as task_count,
    COUNT(DISTINCT o.id) as order_count
FROM users u
LEFT JOIN tasks t ON u.id = t.user_id
LEFT JOIN orders o ON t.id = o.task_id
WHERE u.id = USER_ID_HERE
GROUP BY u.id;
"
```

**Solutions:**

1. **User has no assigned tasks:**
   - This is normal for new users
   - Admin needs to assign tasks

2. **Session issue:**
   ```bash
   # Clear PHP sessions
   sudo rm -rf /var/lib/php/sessions/sess_*
   ```

3. **Column name mismatch:**
   - Check if database uses `status` or `task_status`
   - Dashboard code handles both, but verify:
   ```sql
   SHOW COLUMNS FROM tasks;
   ```

---

### Issue 4: Sidebar Errors

**Symptoms:**
- Dashboard loads but sidebar is blank
- PHP warnings about undefined variables
- Badge counts show 0 when they shouldn't

**Diagnosis:**
```bash
# Check sidebar syntax
php -l /home/runner/work/reviewer/reviewer/user/includes/sidebar.php

# Check for sidebar-specific errors
grep "Sidebar" /home/runner/work/reviewer/reviewer/logs/error.log | tail -20
```

**Solutions:**

1. **Check PDO connection in sidebar context:**
   ```php
   // Verify in sidebar.php
   if (!isset($pdo)) {
       error_log("Sidebar: PDO connection not available");
   }
   ```

2. **Verify badge count queries:**
   ```sql
   -- Test pending tasks query
   SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status = 'pending';
   
   -- Test unread messages query
   SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND is_read = 0 AND sender = 'admin';
   ```

3. **Check session variables:**
   ```php
   // Verify user_id is set
   var_dump($_SESSION['user_id']);
   ```

---

## 📈 Performance Optimization Tips

### 1. Database Optimization

```sql
-- Analyze table usage
ANALYZE TABLE tasks, orders, users, announcements;

-- Update table statistics
OPTIMIZE TABLE tasks, orders, users, announcements;

-- Check for fragmentation
SELECT 
    table_name,
    round(data_free / 1024 / 1024, 2) as data_free_mb
FROM information_schema.tables
WHERE table_schema = 'reviewflow' AND data_free > 0;
```

---

### 2. Caching Strategy (Future Enhancement)

Consider implementing:
- **Redis/Memcached** for session storage
- **OPcache** for PHP bytecode caching
- **Query result caching** for badge counts

```php
// Example: Cache badge counts
$cache_key = "user_{$user_id}_badge_counts";
$cached = apcu_fetch($cache_key);
if ($cached === false) {
    // Query database
    $badge_counts = get_badge_counts($user_id);
    apcu_store($cache_key, $badge_counts, 300); // Cache for 5 minutes
}
```

---

### 3. PHP-FPM Configuration

Optimize `/etc/php-fpm.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

---

## 📱 Monitoring Alerts Setup

### 1. Email Alerts for Critical Errors

Create `/usr/local/bin/check-dashboard-errors.sh`:

```bash
#!/bin/bash

LOG_FILE="/home/runner/work/reviewer/reviewer/logs/error.log"
ALERT_EMAIL="admin@yourdomain.com"
ERROR_THRESHOLD=10

# Count errors in last 5 minutes
ERROR_COUNT=$(tail -1000 "$LOG_FILE" | grep "$(date +%Y-%m-%d\ %H:%M -d '5 minutes ago')" | grep -ci "error\|critical\|fatal")

if [ $ERROR_COUNT -gt $ERROR_THRESHOLD ]; then
    echo "ALERT: $ERROR_COUNT errors detected in last 5 minutes" | mail -s "ReviewFlow Dashboard Alert" $ALERT_EMAIL
    
    # Include recent errors
    tail -50 "$LOG_FILE" | mail -s "ReviewFlow Error Log" $ALERT_EMAIL
fi
```

Add to crontab:
```bash
# Run every 5 minutes
*/5 * * * * /usr/local/bin/check-dashboard-errors.sh
```

---

### 2. Uptime Monitoring

Use a service like **UptimeRobot** or create a simple monitor:

```bash
#!/bin/bash
# check-dashboard-uptime.sh

URL="https://palians.com/reviewer/user/dashboard.php"
ALERT_EMAIL="admin@yourdomain.com"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$URL")

if [ "$HTTP_CODE" != "200" ] && [ "$HTTP_CODE" != "302" ]; then
    echo "Dashboard returned HTTP $HTTP_CODE" | mail -s "Dashboard Down!" $ALERT_EMAIL
fi
```

---

### 3. Database Connection Monitoring

```bash
#!/bin/bash
# check-db-connection.sh

mysql -u reviewflow_user -p'Malik@241123' reviewflow -e "SELECT 1" > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "Database connection failed!" | mail -s "Database Alert" admin@yourdomain.com
    sudo systemctl restart mysql
fi
```

---

## 🎯 Key Metrics to Track

### Daily Metrics

1. **Uptime:** Target 99.9%
2. **Response Time:** Target < 2 seconds
3. **Error Rate:** Target < 0.1%
4. **Database Connections:** Monitor for connection pool exhaustion

### Weekly Metrics

1. **Average Load Time:** Trend over time
2. **Error Patterns:** Identify recurring issues
3. **User Complaints:** Track support tickets
4. **Database Growth:** Monitor table sizes

---

## 📞 Escalation Path

### Level 1: Automatic Recovery (0-5 minutes)
- Automated scripts restart services
- Health checks run
- Alerts sent to on-call engineer

### Level 2: On-Call Response (5-15 minutes)
- Engineer investigates error logs
- Applies known fixes from this playbook
- Monitors recovery

### Level 3: Escalation (15-30 minutes)
- Senior engineer engaged
- Consider rollback if issue persists
- Incident report created

### Level 4: Emergency Rollback (30+ minutes)
- Rollback to last known good state
- Communicate with users
- Schedule post-mortem

---

## 📚 Additional Resources

- **Fix Documentation:** `/home/runner/work/reviewer/reviewer/HTTP_500_FIX_SUMMARY.md`
- **Troubleshooting Guide:** `/home/runner/work/reviewer/reviewer/TROUBLESHOOTING.md`
- **Verification Checklist:** `/home/runner/work/reviewer/reviewer/DASHBOARD_VERIFICATION_CHECKLIST.md`
- **GitHub Repository:** https://github.com/aqidul/reviewer
- **Issue Tracker:** https://github.com/aqidul/reviewer/issues

---

## 🔄 Regular Maintenance Tasks

### Daily
- [ ] Check error logs
- [ ] Verify database connectivity
- [ ] Monitor disk space
- [ ] Review performance metrics

### Weekly
- [ ] Optimize database tables
- [ ] Review slow query log
- [ ] Update system packages
- [ ] Backup verification

### Monthly
- [ ] Security audit
- [ ] Performance review
- [ ] Capacity planning
- [ ] Documentation update

---

**Document Version:** 1.0
**Last Updated:** 2026-02-04
**Maintained By:** DevOps Team
**Review Frequency:** Quarterly
