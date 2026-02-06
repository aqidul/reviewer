# Quick Deployment Guide / जल्दी डिप्लॉयमेंट गाइड

## सबसे आसान तरीका / Easiest Way

```bash
# 1. प्रोजेक्ट फोल्डर में जाएं / Go to project folder
cd /var/www/palians/reviewer

# 2. बैकअप बनाएं / Create backup
sudo cp -r /var/www/palians/reviewer /var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)

# 3. नए बदलाव डाउनलोड करें / Download new changes
sudo git pull origin main

# 4. परमिशन सही करें / Fix permissions
sudo chown -R www-data:www-data /var/www/palians/reviewer
sudo chmod -R 777 /var/www/palians/reviewer/logs
sudo chmod -R 777 /var/www/palians/reviewer/uploads

# 5. Apache restart करें / Restart Apache
sudo systemctl restart apache2

# 6. वेबसाइट खोलें / Open website
# https://palians.com/reviewer/
```

---

## अगर कुछ गलत हो जाए / If Something Goes Wrong

```bash
# बैकअप से वापस लाएं / Restore from backup
sudo rm -rf /var/www/palians/reviewer
sudo cp -r /var/www/palians/reviewer_backup_YYYYMMDD_HHMMSS /var/www/palians/reviewer
sudo systemctl restart apache2
```

---

## एरर देखने के लिए / To Check Errors

```bash
# एरर लॉग देखें / View error log
sudo tail -f /var/www/palians/reviewer/logs/error.log

# Apache log देखें / View Apache log
sudo tail -f /var/log/apache2/error.log
```

---

## पूरा डिप्लॉयमेंट स्क्रिप्ट / Complete Deployment Script

**File: `/home/deploy_reviewer.sh`**

```bash
#!/bin/bash

echo "🚀 ReviewFlow Deployment शुरू हो रहा है..."

# जाएं प्रोजेक्ट में
cd /var/www/palians/reviewer

# बैकअप बनाएं
echo "💾 बैकअप बना रहे हैं..."
sudo cp -r /var/www/palians/reviewer /var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)

# Git pull करें
echo "⬇️ नए बदलाव डाउनलोड कर रहे हैं..."
sudo git pull origin main

# Permissions ठीक करें
echo "🔐 Permissions सेट कर रहे हैं..."
sudo chown -R www-data:www-data /var/www/palians/reviewer
sudo chmod -R 755 /var/www/palians/reviewer
sudo chmod -R 777 /var/www/palians/reviewer/logs
sudo chmod -R 777 /var/www/palians/reviewer/uploads
sudo chmod -R 777 /var/www/palians/reviewer/cache

# Cache साफ़ करें
echo "🧹 Cache साफ़ कर रहे हैं..."
sudo rm -rf /var/www/palians/reviewer/cache/*

# Apache restart करें
echo "🔄 Apache restart कर रहे हैं..."
sudo systemctl restart apache2

echo "✅ Deployment पूरी हुई!"
echo "🌐 वेबसाइट खोलें: https://palians.com/reviewer/"
```

**कैसे चलाएं / How to Run:**
```bash
# Save करें
sudo nano /home/deploy_reviewer.sh

# Permission दें
sudo chmod +x /home/deploy_reviewer.sh

# चलाएं
sudo bash /home/deploy_reviewer.sh
```

---

## सामान्य समस्याएं / Common Problems

### 1. Permission Denied
```bash
sudo chown -R www-data:www-data /var/www/palians/reviewer
```

### 2. MySQL Error / Database Error
```bash
# MySQL चालू करें
sudo systemctl restart mysql

# टेस्ट करें
mysql -u reviewflow_user -p reviewflow -e "SELECT 1;"
```

### 3. 500 Error दिख रहा है
```bash
# Logs देखें
sudo tail -100 /var/www/palians/reviewer/logs/error.log

# Apache restart करें
sudo systemctl restart apache2
```

### 4. Changes दिख नहीं रहे
```bash
# Cache साफ़ करें
sudo rm -rf /var/www/palians/reviewer/cache/*
sudo systemctl restart apache2

# Browser में Ctrl+Shift+R दबाएं (hard refresh)
```

---

## चेकलिस्ट / Checklist

Deployment के बाद ये चेक करें / Check these after deployment:

- [ ] वेबसाइट खुल रही है / Website is opening
- [ ] Login काम कर रहा है / Login is working
- [ ] Dashboard दिख रहा है / Dashboard is visible
- [ ] कोई error नहीं आ रहा / No errors appearing
- [ ] Logs में error नहीं है / No errors in logs

---

## Important Commands / महत्वपूर्ण कमांड्स

```bash
# Git status देखें
sudo git status

# Latest commit देखें
sudo git log -1

# Branches देखें
sudo git branch -a

# Remote branches देखें
sudo git fetch origin
sudo git branch -r

# किस branch में हैं देखें
sudo git branch
```

---

## संपर्क / Contact

अगर कोई समस्या हो तो:
- Error logs check करें: `/var/www/palians/reviewer/logs/error.log`
- Documentation पढ़ें: `TROUBLESHOOTING.md`
- Development team से संपर्क करें

---

**Remember / याद रखें:**
- हमेशा backup लें! / Always take backup!
- Permissions जरूर ठीक करें / Always fix permissions
- Apache restart करें / Always restart Apache
- Logs check करें / Always check logs

---

**Updated / अपडेट किया:** February 6, 2026
