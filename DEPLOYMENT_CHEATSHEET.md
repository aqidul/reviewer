# 📋 Deployment Cheat Sheet
# डिप्लॉयमेंट चीट शीट

**Server:** `/var/www/palians/reviewer`

---

## 🚀 Quick Deploy (6 Commands)

```bash
cd /var/www/palians/reviewer
sudo cp -r . ../reviewer_backup_$(date +%Y%m%d)
sudo git pull origin main
sudo chown -R www-data:www-data .
sudo chmod -R 777 logs uploads cache
sudo systemctl restart apache2
```

✅ Done! Website updated.

---

## 🤖 Automated Deploy (1 Command)

```bash
cd /var/www/palians/reviewer && sudo bash deploy_production.sh
```

---

## 🔍 Check Status

```bash
# Website
curl -I https://palians.com/reviewer/

# Logs
sudo tail -f /var/www/palians/reviewer/logs/error.log

# Services
sudo systemctl status mysql apache2
```

---

## ⚠️ Quick Fixes

### Permission Error:
```bash
sudo chown -R www-data:www-data /var/www/palians/reviewer
sudo chmod -R 777 /var/www/palians/reviewer/logs
```

### Git Error:
```bash
sudo git stash
sudo git pull origin main
```

### 500 Error:
```bash
sudo systemctl restart mysql apache2
sudo tail -f /var/www/palians/reviewer/logs/error.log
```

### Not Updating:
```bash
sudo rm -rf /var/www/palians/reviewer/cache/*
sudo systemctl restart apache2
# Ctrl+Shift+R in browser
```

---

## 🔄 Rollback

```bash
sudo rm -rf /var/www/palians/reviewer
sudo cp -r /var/www/palians/reviewer_backup_YYYYMMDD /var/www/palians/reviewer
sudo systemctl restart apache2
```

---

## 📚 Documentation

- **DEPLOY_README_HINDI.md** - Start here
- **QUICK_DEPLOYMENT_GUIDE.md** - Quick commands
- **DEPLOYMENT_GUIDE.md** - Full guide
- **deploy_production.sh** - Auto script

---

## ✅ Post-Deploy Checklist

- [ ] Website opens: https://palians.com/reviewer/
- [ ] Login works
- [ ] Dashboard loads
- [ ] No errors in logs

---

**Emergency:** Restore backup + restart services
