# Production Deployment Guide / प्रोडक्शन डिप्लॉयमेंट गाइड

**Project Directory / प्रोजेक्ट डायरेक्टरी:** `/var/www/palians/reviewer`

**Date:** February 6, 2026

---

## Quick Start / त्वरित शुरुआत

```bash
# Step 1: Go to project directory / प्रोजेक्ट डायरेक्टरी में जाएं
cd /var/www/palians/reviewer

# Step 2: Backup current version / वर्तमान वर्जन का बैकअप लें
sudo cp -r /var/www/palians/reviewer /var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)

# Step 3: Pull latest changes / नवीनतम बदलाव डाउनलोड करें
sudo git pull origin main

# Step 4: Set permissions / परमिशन सेट करें
sudo chown -R www-data:www-data /var/www/palians/reviewer
sudo chmod -R 755 /var/www/palians/reviewer
sudo chmod -R 777 /var/www/palians/reviewer/logs
sudo chmod -R 777 /var/www/palians/reviewer/uploads

# Step 5: Clear cache / कैश साफ़ करें
sudo rm -rf /var/www/palians/reviewer/cache/*

# Step 6: Restart web server / वेब सर्वर रीस्टार्ट करें
sudo systemctl restart apache2
# OR for nginx:
# sudo systemctl restart nginx php7.4-fpm
```

---

## Detailed Steps / विस्तृत चरण

### Pre-Deployment Checklist / डिप्लॉयमेंट से पहले

Before pulling changes, verify these / बदलाव डाउनलोड करने से पहले ये जांचें:

```bash
# 1. Check current Git status / वर्तमान Git स्टेटस देखें
cd /var/www/palians/reviewer
sudo git status

# 2. Check current branch / वर्तमान ब्रांच देखें
sudo git branch

# 3. Check MySQL is running / MySQL चालू है या नहीं देखें
sudo systemctl status mysql

# 4. Check disk space / डिस्क स्पेस देखें
df -h
```

---

### Step-by-Step Deployment / चरण-दर-चरण डिप्लॉयमेंट

#### Step 1: Navigate to Project Directory / प्रोजेक्ट डायरेक्टरी में जाएं

```bash
cd /var/www/palians/reviewer
pwd  # Verify you're in the correct directory / सही डायरेक्टरी में हैं जांचें
```

**Expected Output / अपेक्षित आउटपुट:**
```
/var/www/palians/reviewer
```

---

#### Step 2: Backup Current Version / वर्तमान वर्जन का बैकअप

**IMPORTANT / महत्वपूर्ण:** Always backup before deployment / हमेशा डिप्लॉयमेंट से पहले बैकअप लें

```bash
# Create timestamped backup / टाइमस्टैम्प के साथ बैकअप बनाएं
sudo cp -r /var/www/palians/reviewer /var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)

# Verify backup was created / बैकअप बना है जांचें
ls -la /var/www/palians/ | grep reviewer_backup
```

---

#### Step 3: Check for Local Changes / स्थानीय बदलाव जांचें

```bash
# Check if there are uncommitted changes / अनकमिट किए हुए बदलाव हैं जांचें
sudo git status

# If there are changes, stash them / अगर बदलाव हैं तो stash करें
sudo git stash save "Local changes before pull $(date +%Y%m%d_%H%M%S)"
```

---

#### Step 4: Fetch Latest Changes / नवीनतम बदलाव लाएं

```bash
# Update remote references / रिमोट रेफरेंस अपडेट करें
sudo git fetch origin

# Check what will be pulled / क्या डाउनलोड होगा देखें
sudo git log HEAD..origin/main --oneline

# Show files that will change / कौन सी फ़ाइलें बदलेंगी देखें
sudo git diff HEAD..origin/main --name-status
```

---

#### Step 5: Pull Changes / बदलाव डाउनलोड करें

```bash
# Pull from main branch / main ब्रांच से डाउनलोड करें
sudo git pull origin main

# If you see merge conflicts / अगर मर्ज कॉन्फ्लिक्ट आए:
# 1. Resolve conflicts manually / मैन्युअली resolve करें
# 2. Or restore backup / या बैकअप वापस लाएं
```

**Success Output / सफलता का आउटपुट:**
```
Already up to date.
# OR
Updating abc1234..def5678
Fast-forward
 includes/config.php        | 50 ++++++++++++++++++
 user/dashboard.php         | 30 +++++++++++
 user/includes/sidebar.php  | 40 ++++++++++----
 3 files changed, 120 insertions(+)
```

---

#### Step 6: Set Correct Permissions / सही परमिशन सेट करें

```bash
# Set owner to web server user / वेब सर्वर यूजर को ओनर बनाएं
sudo chown -R www-data:www-data /var/www/palians/reviewer

# Set directory permissions / डायरेक्टरी परमिशन सेट करें
sudo chmod -R 755 /var/www/palians/reviewer

# Set writable directories / राइटेबल डायरेक्टरी सेट करें
sudo chmod -R 777 /var/www/palians/reviewer/logs
sudo chmod -R 777 /var/www/palians/reviewer/uploads
sudo chmod -R 777 /var/www/palians/reviewer/cache

# Verify permissions / परमिशन जांचें
ls -la /var/www/palians/reviewer/logs
ls -la /var/www/palians/reviewer/uploads
```

---

#### Step 7: Clear Application Cache / ऐप्लिकेशन कैश साफ़ करें

```bash
# Clear cache directory / कैश डायरेक्टरी साफ़ करें
sudo rm -rf /var/www/palians/reviewer/cache/*

# Clear PHP OPcache (if enabled) / PHP OPcache साफ़ करें (अगर इनेबल है)
# You can do this by restarting PHP-FPM / PHP-FPM रीस्टार्ट करके कर सकते हैं
```

---

#### Step 8: Restart Web Server / वेब सर्वर रीस्टार्ट करें

```bash
# For Apache / Apache के लिए
sudo systemctl restart apache2

# Check Apache status / Apache स्टेटस देखें
sudo systemctl status apache2

# For Nginx + PHP-FPM / Nginx + PHP-FPM के लिए
sudo systemctl restart nginx
sudo systemctl restart php7.4-fpm  # या php8.0-fpm या आपका PHP version

# Check status / स्टेटस देखें
sudo systemctl status nginx
sudo systemctl status php7.4-fpm
```

---

### Post-Deployment Verification / डिप्लॉयमेंट के बाद जांच

#### 1. Check Website is Accessible / वेबसाइट एक्सेसिबल है जांचें

```bash
# Test with curl
curl -I https://palians.com/reviewer/

# Expected: HTTP 200 OK or 302 Found
```

**Open in browser / ब्राउज़र में खोलें:**
- https://palians.com/reviewer/
- https://palians.com/reviewer/user/dashboard.php

---

#### 2. Check Error Logs / एरर लॉग्स देखें

```bash
# Application error log / ऐप्लिकेशन एरर लॉग
sudo tail -f /var/www/palians/reviewer/logs/error.log

# Apache error log / Apache एरर लॉग
sudo tail -f /var/log/apache2/error.log

# Nginx error log / Nginx एरर लॉग
sudo tail -f /var/log/nginx/error.log
```

---

#### 3. Test Database Connection / डेटाबेस कनेक्शन टेस्ट करें

```bash
# Test from command line / कमांड लाइन से टेस्ट करें
mysql -u reviewflow_user -p reviewflow -e "SELECT 1;"

# Test from PHP / PHP से टेस्ट करें
cd /var/www/palians/reviewer
sudo php -r "require_once 'includes/config.php'; echo 'Database connected successfully!';"
```

---

#### 4. Check File Permissions / फ़ाइल परमिशन जांचें

```bash
# Check logs directory / logs डायरेक्टरी जांचें
ls -la /var/www/palians/reviewer/logs/

# Check uploads directory / uploads डायरेक्टरी जांचें
ls -la /var/www/palians/reviewer/uploads/

# Verify www-data can write / www-data लिख सकता है जांचें
sudo -u www-data touch /var/www/palians/reviewer/logs/test.txt
sudo -u www-data rm /var/www/palians/reviewer/logs/test.txt
```

---

#### 5. Test User Dashboard / यूजर डैशबोर्ड टेस्ट करें

**Manual Test / मैन्युअल टेस्ट:**
1. Open browser / ब्राउज़र खोलें
2. Go to https://palians.com/reviewer/user/dashboard.php
3. Login with test credentials / टेस्ट क्रेडेंशियल से लॉगिन करें
4. Verify dashboard loads / डैशबोर्ड लोड होता है जांचें
5. Check for any errors / कोई एरर है जांचें

---

## Troubleshooting / समस्या निवारण

### Problem 1: "Permission denied" Error / परमिशन डिनाइड एरर

```bash
# Fix: Set correct permissions / सही परमिशन सेट करें
sudo chown -R www-data:www-data /var/www/palians/reviewer
sudo chmod -R 755 /var/www/palians/reviewer
sudo chmod -R 777 /var/www/palians/reviewer/logs
sudo chmod -R 777 /var/www/palians/reviewer/uploads
```

---

### Problem 2: "fatal: not a git repository" / Git रिपॉजिटरी नहीं है

```bash
# Check if .git directory exists / .git डायरेक्टरी है जांचें
ls -la /var/www/palians/reviewer/ | grep .git

# If not, you need to clone the repository / अगर नहीं है तो रिपॉजिटरी क्लोन करें
cd /var/www/palians/
sudo mv reviewer reviewer_old
sudo git clone https://github.com/aqidul/reviewer.git
```

---

### Problem 3: Merge Conflicts / मर्ज कॉन्फ्लिक्ट

```bash
# Option 1: Keep remote version (discard local changes)
# विकल्प 1: रिमोट वर्जन रखें (स्थानीय बदलाव छोड़ें)
sudo git reset --hard origin/main

# Option 2: Restore from backup / विकल्प 2: बैकअप से वापस लाएं
sudo rm -rf /var/www/palians/reviewer
sudo cp -r /var/www/palians/reviewer_backup_YYYYMMDD_HHMMSS /var/www/palians/reviewer
```

---

### Problem 4: Website Shows 500 Error / वेबसाइट 500 एरर दिखाए

```bash
# 1. Check error logs / एरर लॉग्स देखें
sudo tail -100 /var/www/palians/reviewer/logs/error.log

# 2. Check MySQL is running / MySQL चालू है जांचें
sudo systemctl status mysql
sudo systemctl restart mysql

# 3. Check database credentials / डेटाबेस क्रेडेंशियल जांचें
sudo nano /var/www/palians/reviewer/includes/config.php
# Verify DB_HOST, DB_USER, DB_PASS, DB_NAME

# 4. Test database connection / डेटाबेस कनेक्शन टेस्ट करें
mysql -u reviewflow_user -p reviewflow -e "SELECT 1;"
```

---

### Problem 5: Changes Not Visible / बदलाव दिख नहीं रहे

```bash
# 1. Hard refresh browser / ब्राउज़र में हार्ड रिफ्रेश करें
# Ctrl + Shift + R (Chrome)
# Ctrl + F5 (Firefox)

# 2. Clear PHP OPcache / PHP OPcache साफ़ करें
sudo systemctl restart apache2
# OR
sudo systemctl restart php7.4-fpm

# 3. Clear application cache / ऐप्लिकेशन कैश साफ़ करें
sudo rm -rf /var/www/palians/reviewer/cache/*

# 4. Verify files were actually pulled / फ़ाइलें वाकई डाउनलोड हुईं जांचें
sudo git log -1  # Check latest commit / नवीनतम कमिट देखें
```

---

## Rollback Procedure / रोलबैक प्रक्रिया

If something goes wrong, restore from backup / अगर कुछ गलत हो जाए तो बैकअप से वापस लाएं:

```bash
# 1. Stop web server / वेब सर्वर बंद करें
sudo systemctl stop apache2

# 2. Restore from backup / बैकअप से वापस लाएं
sudo rm -rf /var/www/palians/reviewer
sudo cp -r /var/www/palians/reviewer_backup_YYYYMMDD_HHMMSS /var/www/palians/reviewer

# 3. Set permissions / परमिशन सेट करें
sudo chown -R www-data:www-data /var/www/palians/reviewer

# 4. Restart web server / वेब सर्वर शुरू करें
sudo systemctl start apache2
```

---

## Monitoring / मॉनिटरिंग

After deployment, monitor these / डिप्लॉयमेंट के बाद ये मॉनिटर करें:

```bash
# 1. Watch error logs / एरर लॉग्स देखें
sudo tail -f /var/www/palians/reviewer/logs/error.log

# 2. Watch Apache access logs / Apache एक्सेस लॉग्स देखें
sudo tail -f /var/log/apache2/access.log

# 3. Monitor MySQL / MySQL मॉनिटर करें
sudo mysqladmin -u root -p processlist

# 4. Check system resources / सिस्टम रिसोर्स देखें
htop
```

---

## Complete Deployment Script / पूर्ण डिप्लॉयमेंट स्क्रिप्ट

Save this as `deploy.sh` and run with `sudo bash deploy.sh`:

```bash
#!/bin/bash
# ReviewFlow Production Deployment Script

echo "🚀 Starting deployment..."

# Configuration / कॉन्फ़िगरेशन
PROJECT_DIR="/var/www/palians/reviewer"
BACKUP_DIR="/var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)"
WEB_USER="www-data"

# Step 1: Navigate to project / प्रोजेक्ट में जाएं
echo "📂 Navigating to project directory..."
cd $PROJECT_DIR || exit 1

# Step 2: Backup / बैकअप
echo "💾 Creating backup..."
sudo cp -r $PROJECT_DIR $BACKUP_DIR
echo "✅ Backup created at: $BACKUP_DIR"

# Step 3: Stash local changes / स्थानीय बदलाव stash करें
echo "📦 Stashing local changes..."
sudo git stash save "Auto-stash before deployment $(date +%Y%m%d_%H%M%S)"

# Step 4: Pull latest changes / नवीनतम बदलाव डाउनलोड करें
echo "⬇️ Pulling latest changes..."
sudo git fetch origin
sudo git pull origin main

if [ $? -ne 0 ]; then
    echo "❌ Git pull failed! Restoring backup..."
    sudo rm -rf $PROJECT_DIR
    sudo cp -r $BACKUP_DIR $PROJECT_DIR
    exit 1
fi

# Step 5: Set permissions / परमिशन सेट करें
echo "🔐 Setting permissions..."
sudo chown -R $WEB_USER:$WEB_USER $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR
sudo chmod -R 777 $PROJECT_DIR/logs
sudo chmod -R 777 $PROJECT_DIR/uploads
sudo chmod -R 777 $PROJECT_DIR/cache

# Step 6: Clear cache / कैश साफ़ करें
echo "🧹 Clearing cache..."
sudo rm -rf $PROJECT_DIR/cache/*

# Step 7: Restart web server / वेब सर्वर रीस्टार्ट करें
echo "🔄 Restarting web server..."
sudo systemctl restart apache2

# Step 8: Verify / जांच करें
echo "✅ Deployment completed!"
echo ""
echo "📋 Post-deployment checklist:"
echo "1. Check website: https://palians.com/reviewer/"
echo "2. Check logs: sudo tail -f $PROJECT_DIR/logs/error.log"
echo "3. Test dashboard: https://palians.com/reviewer/user/dashboard.php"
echo ""
echo "📁 Backup location: $BACKUP_DIR"
echo "To rollback: sudo rm -rf $PROJECT_DIR && sudo cp -r $BACKUP_DIR $PROJECT_DIR"
```

---

## Summary / सारांश

### What You Merged / क्या मर्ज किया
- HTTP 500 error fix for user dashboard
- Enhanced error handling and logging
- SQL parameter bug fix
- Security improvements

### How to Pull / कैसे डाउनलोड करें

**Quick Command / त्वरित कमांड:**
```bash
cd /var/www/palians/reviewer && sudo git pull origin main
```

**With Backup / बैकअप के साथ:**
```bash
cd /var/www/palians/reviewer
sudo cp -r /var/www/palians/reviewer /var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)
sudo git pull origin main
sudo systemctl restart apache2
```

---

## Important Notes / महत्वपूर्ण नोट्स

1. ⚠️ **Always backup before pulling** / हमेशा पुल से पहले बैकअप लें
2. ⚠️ **Check for local changes** / स्थानीय बदलाव जांचें
3. ⚠️ **Set correct permissions** / सही परमिशन सेट करें
4. ⚠️ **Restart web server** / वेब सर्वर रीस्टार्ट करें
5. ⚠️ **Monitor error logs** / एरर लॉग्स मॉनिटर करें

---

## Need Help? / मदद चाहिए?

If you encounter issues / अगर समस्या आए:

1. Check error logs / एरर लॉग्स देखें
2. Restore from backup / बैकअप से वापस लाएं
3. Contact development team / डेवलपमेंट टीम से संपर्क करें

**Documentation / दस्तावेज़ीकरण:**
- TROUBLESHOOTING.md
- HTTP_500_FIX_SUMMARY.md
- DASHBOARD_VERIFICATION_REPORT.md

---

**Last Updated / अंतिम अपडेट:** February 6, 2026
