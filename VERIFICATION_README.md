# Dashboard Verification Guide

## Quick Start

This directory contains tools and documentation to verify that the dashboard HTTP 500 error fix is working correctly on production.

---

## 📋 Quick Validation (2 minutes)

```bash
# 1. Navigate to the repository
cd /home/runner/work/reviewer/reviewer

# 2. Run the automated verification script
php verify_dashboard.php

# 3. Check the output for any red ✗ marks
# - All green ✓ = Everything is working correctly
# - Yellow ⚠ = Warnings (usually non-blocking)
# - Red ✗ = Critical issues that need attention
```

---

## 📁 Available Resources

### 1. **PRODUCTION_CONFIRMATION.md** ⭐ START HERE
Complete answer to "Is the dashboard working correctly?"
- Executive summary of the fix
- Verification results
- Quick validation steps
- Troubleshooting tips
- Monitoring recommendations

### 2. **verify_dashboard.php** 🔧 AUTOMATED TOOL
Automated verification script with 25+ checks
- Run: `php verify_dashboard.php`
- Tests database connectivity
- Validates error handling
- Checks security configuration
- Exit code: 0 (pass) or 1 (fail)

### 3. **DASHBOARD_VERIFICATION_CHECKLIST.md** ✅ STEP-BY-STEP
Comprehensive production verification checklist
- Pre-deployment steps
- Database checks
- Functional testing
- Performance benchmarks
- Security verification
- Rollback procedures

### 4. **DASHBOARD_MONITORING_PLAYBOOK.md** 🚨 OPERATIONS
Production support and troubleshooting guide
- Emergency response procedures (< 5 min)
- Daily monitoring routines
- Diagnostic commands
- Common issues and solutions
- Alert setup scripts

### 5. **DASHBOARD_VERIFICATION_REPORT.md** 📊 DETAILED REPORT
Detailed technical verification report
- Complete test results
- Security assessment
- Performance metrics
- Future recommendations

---

## 🚀 Deployment Workflow

### Before Deployment
```bash
# Verify code locally
php verify_dashboard.php

# Review the checklist
less DASHBOARD_VERIFICATION_CHECKLIST.md
```

### During Deployment
1. Backup database
2. Pull latest code
3. Verify MySQL is running
4. Check file permissions

### After Deployment
```bash
# Run verification
php verify_dashboard.php

# Access dashboard URL
curl -I https://palians.com/reviewer/user/dashboard.php

# Check error logs
tail -50 logs/error.log
```

---

## 🔍 Common Scenarios

### Scenario 1: First Time Verification
**You want to confirm the fix is working**

1. Read: `PRODUCTION_CONFIRMATION.md` (5 min)
2. Run: `php verify_dashboard.php` (2 min)
3. Access dashboard in browser (2 min)
4. Result: Dashboard works ✅

### Scenario 2: Production Deployment
**You're about to deploy to production**

1. Follow: `DASHBOARD_VERIFICATION_CHECKLIST.md` (30 min)
2. Set up monitoring (see checklist)
3. Deploy code
4. Run: `php verify_dashboard.php`
5. Monitor for 24 hours

### Scenario 3: Dashboard is Broken
**Users report HTTP 500 errors**

1. Check: `DASHBOARD_MONITORING_PLAYBOOK.md` → "Emergency Response"
2. Run: `php verify_dashboard.php` (shows what's wrong)
3. Check MySQL: `sudo systemctl status mysql`
4. Review logs: `tail -50 logs/error.log`
5. Apply fixes from playbook

### Scenario 4: Setting Up Monitoring
**You want to monitor dashboard health**

1. Read: `DASHBOARD_MONITORING_PLAYBOOK.md` → "Monitoring Setup"
2. Set up UptimeRobot or similar service
3. Configure cron jobs for health checks
4. Set up log monitoring
5. Configure alerts

---

## 📞 Quick Reference Commands

### Check System Health
```bash
# Run verification script
php verify_dashboard.php

# Check MySQL
sudo systemctl status mysql

# Check error logs
tail -50 logs/error.log

# Test database connection
mysql -u reviewflow_user -p reviewflow
```

### Emergency Fixes
```bash
# Restart MySQL
sudo systemctl restart mysql

# Check disk space
df -h

# Clear PHP sessions
sudo rm /var/lib/php/sessions/sess_*

# Optimize database
mysql reviewflow -e "OPTIMIZE TABLE tasks, orders, users"
```

### Monitoring
```bash
# Watch error logs in real-time
tail -f logs/error.log

# Monitor MySQL
watch -n 5 'mysqladmin processlist'

# Check response time
curl -w "%{time_total}\n" -o /dev/null -s https://palians.com/reviewer/user/dashboard.php
```

---

## 🎯 Success Criteria

Your dashboard is working correctly if:

✅ `php verify_dashboard.php` shows 100% pass rate  
✅ Dashboard URL loads without HTTP 500 errors  
✅ Users can log in and see their tasks  
✅ Statistics display correctly  
✅ No critical errors in `logs/error.log`  
✅ Response time is < 3 seconds  

---

## 🆘 Getting Help

### Self-Service
1. Run: `php verify_dashboard.php`
2. Check: `TROUBLESHOOTING.md` (in root directory)
3. Review: `DASHBOARD_MONITORING_PLAYBOOK.md` → "Common Issues"

### Escalation
1. Collect verification output: `php verify_dashboard.php > verification.log`
2. Collect error logs: `tail -100 logs/error.log > errors.log`
3. Open GitHub issue with logs attached
4. Tag: `bug`, `production`, `dashboard`

---

## 📚 Document Overview

| Document | Purpose | When to Use |
|----------|---------|-------------|
| **PRODUCTION_CONFIRMATION.md** | Executive summary | First read, answering "Is it working?" |
| **verify_dashboard.php** | Automated testing | Every deployment, troubleshooting |
| **DASHBOARD_VERIFICATION_CHECKLIST.md** | Step-by-step guide | Production deployment |
| **DASHBOARD_MONITORING_PLAYBOOK.md** | Operations manual | Daily monitoring, troubleshooting |
| **DASHBOARD_VERIFICATION_REPORT.md** | Technical details | Deep dive, stakeholder reporting |

---

## 🔐 Security Notes

**Before Production:**
- [ ] Set `DEBUG = false` in `includes/config.php`
- [ ] Verify `display_errors = Off` in `php.ini`
- [ ] Check file permissions (no 777 on PHP files)
- [ ] Ensure SSL is enabled
- [ ] Review database credentials

**Run Security Check:**
```bash
# Check DEBUG mode
grep "const DEBUG" includes/config.php

# Check file permissions
find . -name "*.php" -perm 0777

# Verify SSL
curl -I https://palians.com/reviewer/user/dashboard.php
```

---

## ⏱️ Time Estimates

| Task | Time Required |
|------|---------------|
| Quick verification | 2 minutes |
| Read production confirmation | 5 minutes |
| Full deployment checklist | 30 minutes |
| Set up monitoring | 20 minutes |
| Troubleshooting (average) | 10 minutes |
| Emergency fix | 5 minutes |

---

## 🎓 Best Practices

1. **Always run verification before deployment**
   ```bash
   php verify_dashboard.php
   ```

2. **Monitor error logs daily**
   ```bash
   tail -50 logs/error.log
   ```

3. **Set up automated monitoring**
   - Use UptimeRobot or similar
   - Configure email alerts
   - Schedule health checks

4. **Keep documentation updated**
   - Update when new issues are discovered
   - Document solutions for future reference
   - Share knowledge with team

5. **Test in staging first**
   - If available, test on staging
   - Simulate failures (stop MySQL, etc.)
   - Verify error handling

---

## 📈 What Changed in PR #39

### Code Changes
- ✅ Enhanced error handling in `includes/config.php`
- ✅ Try-catch blocks in `user/dashboard.php`
- ✅ Fixed SQL bug in `user/includes/sidebar.php`
- ✅ Added session validation
- ✅ Improved error logging

### User Experience
- ✅ User-friendly error pages (not raw errors)
- ✅ Dashboard continues to work even with errors
- ✅ Better debugging information

### Operations
- ✅ Detailed error logs
- ✅ Health check capabilities
- ✅ Monitoring tools
- ✅ Troubleshooting guides

---

## 🚦 Status Indicators

### Green (Healthy) ✅
- Verification script passes all tests
- Dashboard loads in < 2 seconds
- No errors in logs
- Users report no issues

### Yellow (Warning) ⚠️
- Some verification warnings
- Occasional slow queries
- Minor errors in logs
- Performance could be better

### Red (Critical) ❌
- Verification script failures
- HTTP 500 errors
- Database connection failures
- Dashboard inaccessible

---

## 💡 Pro Tips

1. **Bookmark These Pages**
   - PRODUCTION_CONFIRMATION.md - Your go-to reference
   - DASHBOARD_MONITORING_PLAYBOOK.md - For emergencies

2. **Set Up Aliases**
   ```bash
   alias check-dashboard='cd /home/runner/work/reviewer/reviewer && php verify_dashboard.php'
   alias watch-logs='tail -f /home/runner/work/reviewer/reviewer/logs/error.log'
   ```

3. **Automation**
   ```bash
   # Add to crontab for daily health checks
   0 9 * * * cd /home/runner/work/reviewer/reviewer && php verify_dashboard.php | mail -s "Dashboard Health" admin@example.com
   ```

4. **Keep It Simple**
   - Start with `PRODUCTION_CONFIRMATION.md`
   - Run `php verify_dashboard.php`
   - Most issues are solved by restarting MySQL

---

## 📞 Support Channels

- **GitHub Issues:** https://github.com/aqidul/reviewer/issues
- **PR Discussion:** https://github.com/aqidul/reviewer/pull/39
- **Documentation:** All *.md files in this directory

---

**Last Updated:** 2026-02-04  
**Version:** 1.0  
**Maintained By:** DevOps Team  
**Status:** Active
