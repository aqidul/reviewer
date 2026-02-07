# Production Server पर Deployment कैसे करें
# How to Deploy to Production Server

---

## 📌 आपकी समस्या / Your Issue

आपने GitHub पर merge कर लिया है और अब आप production server पर pull करना चाहते हैं।

**Project Location / प्रोजेक्ट लोकेशन:** `/var/www/palians/reviewer`

---

## 🚀 सबसे आसान तरीका / Easiest Method

### विकल्प 1: Automated Script (सबसे आसान)

```bash
# 1. Script को server पर upload करें
# Upload deploy_production.sh to your server

# 2. Project directory में जाएं
cd /var/www/palians/reviewer

# 3. Script को copy करें (अगर project में है)
# If the script is already in the project:
sudo chmod +x deploy_production.sh
sudo bash deploy_production.sh

# 4. बस! Script सब कुछ automatically करेगा
# Done! The script will do everything automatically
```

**Script क्या करेगा / What the script does:**
- ✅ Automatic backup बनाएगा
- ✅ नए changes download करेगा
- ✅ Permissions ठीक करेगा
- ✅ Cache clear करेगा
- ✅ Apache restart करेगा
- ✅ सब कुछ verify करेगा

---

### विकल्प 2: Manual Commands (तेज़)

```bash
# बस ये 6 commands चलाएं:

cd /var/www/palians/reviewer
sudo cp -r /var/www/palians/reviewer /var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)
sudo git pull origin main
sudo chown -R www-data:www-data /var/www/palians/reviewer
sudo chmod -R 777 /var/www/palians/reviewer/logs /var/www/palians/reviewer/uploads
sudo systemctl restart apache2
```

**बस इतना ही! / That's it!**

---

## 📚 Documents Available / उपलब्ध दस्तावेज़

मैंने आपके लिए 3 documents बनाए हैं:

### 1. **QUICK_DEPLOYMENT_GUIDE.md** ⭐ (सबसे उपयोगी)
- बिल्कुल simple commands
- Hindi + English में
- Common problems और solutions
- **यहाँ से शुरू करें!**

### 2. **DEPLOYMENT_GUIDE.md** (पूरी जानकारी)
- Complete detailed guide
- Step-by-step instructions
- Troubleshooting guide
- Monitoring tips
- अगर कोई problem आए तो यह पढ़ें

### 3. **deploy_production.sh** (Automatic script)
- एक command में पूरा deployment
- Automatic backup
- Safety checks
- सबसे safe तरीका

---

## ⚠️ Important / महत्वपूर्ण

### Deployment से पहले:

1. **Backup जरूर लें!** (Script automatically करता है)
2. **MySQL चालू है check करें:** `sudo systemctl status mysql`
3. **Disk space check करें:** `df -h`

### Deployment के बाद:

1. **Website खोलें:** https://palians.com/reviewer/
2. **Login test करें**
3. **Dashboard check करें:** https://palians.com/reviewer/user/dashboard.php
4. **Error logs देखें:** `sudo tail -f /var/www/palians/reviewer/logs/error.log`

---

## 🆘 अगर Problem आए / If Problems Occur

### Problem 1: Permission Error
```bash
sudo chown -R www-data:www-data /var/www/palians/reviewer
sudo chmod -R 777 /var/www/palians/reviewer/logs
```

### Problem 2: Git Pull नहीं हो रहा
```bash
sudo git status  # Check status
sudo git stash   # अगर local changes हैं
sudo git pull origin main
```

### Problem 3: Website काम नहीं कर रही
```bash
sudo systemctl restart mysql
sudo systemctl restart apache2
sudo tail -f /var/www/palians/reviewer/logs/error.log
```

### Problem 4: Changes दिख नहीं रहे
```bash
sudo rm -rf /var/www/palians/reviewer/cache/*
sudo systemctl restart apache2
# Browser में Ctrl+Shift+R (hard refresh)
```

---

## 🔄 Rollback / वापस जाने के लिए

अगर कुछ गलत हो जाए:

```bash
# Backup से restore करें
sudo rm -rf /var/www/palians/reviewer
sudo cp -r /var/www/palians/reviewer_backup_YYYYMMDD_HHMMSS /var/www/palians/reviewer
sudo systemctl restart apache2
```

---

## 📞 Help / सहायता

### Documents को कैसे access करें:

```bash
# Quick guide देखें
cat QUICK_DEPLOYMENT_GUIDE.md

# Full guide देखें
cat DEPLOYMENT_GUIDE.md

# Script चलाएं
sudo bash deploy_production.sh
```

### Error logs देखें:
```bash
# Application logs
sudo tail -100 /var/www/palians/reviewer/logs/error.log

# Apache logs
sudo tail -100 /var/log/apache2/error.log
```

---

## ✅ Quick Checklist / जल्दी चेकलिस्ट

Deploy करने से पहले:
- [ ] MySQL चालू है
- [ ] Backup बना लिया
- [ ] Disk space है

Deploy करने के बाद:
- [ ] Website खुल रही है
- [ ] Login काम कर रहा है
- [ ] Dashboard दिख रहा है
- [ ] Errors नहीं आ रहे

---

## 🎯 Summary / सारांश

### सबसे आसान तरीका:

1. **Automated script use करें:**
   ```bash
   cd /var/www/palians/reviewer
   sudo bash deploy_production.sh
   ```

2. **या फिर manual commands:**
   ```bash
   cd /var/www/palians/reviewer
   sudo git pull origin main
   sudo systemctl restart apache2
   ```

3. **Website check करें:**
   - https://palians.com/reviewer/

---

## 📝 Notes / नोट्स

- सभी commands **sudo** के साथ चलाएं
- हमेशा **backup** लें
- Deployment के बाद **logs check** करें
- अगर problem हो तो **DEPLOYMENT_GUIDE.md** पढ़ें

---

**यह guide Hindi और English दोनों में है ताकि आसानी से समझ आए।**

**This guide is in both Hindi and English for easier understanding.**

---

**Questions? / सवाल?**
- Troubleshooting के लिए: `DEPLOYMENT_GUIDE.md` देखें
- Quick reference के लिए: `QUICK_DEPLOYMENT_GUIDE.md` देखें
- Automatic deployment के लिए: `deploy_production.sh` चलाएं

---

**Last Updated:** February 6, 2026
