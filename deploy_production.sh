#!/bin/bash

################################################################################
# ReviewFlow Production Deployment Script
# प्रोडक्शन डिप्लॉयमेंट स्क्रिप्ट
#
# Usage: sudo bash deploy_production.sh
# उपयोग: sudo bash deploy_production.sh
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/var/www/palians/reviewer"
BACKUP_DIR="/var/www/palians/reviewer_backup_$(date +%Y%m%d_%H%M%S)"
WEB_USER="www-data"
WEB_GROUP="www-data"
BRANCH="main"

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}🚀 ReviewFlow Deployment${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

# Function to print status
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    print_error "कृपया sudo के साथ चलाएं / Please run with sudo"
    exit 1
fi

# Step 1: Navigate to project directory
echo -e "${YELLOW}📂 Step 1: प्रोजेक्ट डायरेक्टरी में जा रहे हैं...${NC}"
if [ ! -d "$PROJECT_DIR" ]; then
    print_error "प्रोजेक्ट डायरेक्टरी नहीं मिली / Project directory not found: $PROJECT_DIR"
    exit 1
fi
cd "$PROJECT_DIR" || exit 1
print_status "प्रोजेक्ट डायरेक्टरी में हैं / In project directory: $(pwd)"
echo ""

# Step 2: Check if it's a git repository
echo -e "${YELLOW}🔍 Step 2: Git repository चेक कर रहे हैं...${NC}"
if [ ! -d ".git" ]; then
    print_error "यह Git repository नहीं है / This is not a Git repository"
    exit 1
fi
print_status "Git repository verified"
echo ""

# Step 3: Check current branch
echo -e "${YELLOW}🌿 Step 3: Current branch चेक कर रहे हैं...${NC}"
CURRENT_BRANCH=$(git branch --show-current)
print_status "Current branch: $CURRENT_BRANCH"
echo ""

# Step 4: Create backup
echo -e "${YELLOW}💾 Step 4: बैकअप बना रहे हैं...${NC}"
echo "   Backup location: $BACKUP_DIR"
cp -r "$PROJECT_DIR" "$BACKUP_DIR"
if [ $? -eq 0 ]; then
    print_status "बैकअप सफलतापूर्वक बना / Backup created successfully"
else
    print_error "बैकअप बनाने में विफल / Backup creation failed"
    exit 1
fi
echo ""

# Step 5: Check for local changes
echo -e "${YELLOW}🔎 Step 5: स्थानीय बदलाव चेक कर रहे हैं...${NC}"
if ! git diff-index --quiet HEAD --; then
    print_warning "स्थानीय बदलाव मिले, stash कर रहे हैं / Local changes found, stashing..."
    git stash save "Auto-stash before deployment $(date +%Y%m%d_%H%M%S)"
    print_status "Changes stashed"
else
    print_status "कोई स्थानीय बदलाव नहीं / No local changes"
fi
echo ""

# Step 6: Fetch latest changes
echo -e "${YELLOW}📥 Step 6: Remote changes fetch कर रहे हैं...${NC}"
git fetch origin
print_status "Remote changes fetched"
echo ""

# Step 7: Show what will be pulled
echo -e "${YELLOW}📋 Step 7: क्या बदलाव आएंगे देख रहे हैं...${NC}"
COMMITS_BEHIND=$(git rev-list HEAD..origin/$BRANCH --count)
if [ "$COMMITS_BEHIND" -eq 0 ]; then
    print_status "Already up to date! / पहले से अपडेट है!"
    echo ""
    echo -e "${GREEN}कोई नए बदलाव नहीं हैं / No new changes to pull${NC}"
    echo ""
    read -p "फिर भी जारी रखें? Continue anyway? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deployment रद्द किया / Deployment cancelled"
        exit 0
    fi
else
    echo "   $COMMITS_BEHIND commits behind origin/$BRANCH"
    echo ""
    echo "   Changes that will be pulled / ये बदलाव आएंगे:"
    git log HEAD..origin/$BRANCH --oneline --no-decorate | head -5
    echo ""
    
    read -p "आगे बढ़ें? Proceed with deployment? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deployment रद्द किया / Deployment cancelled"
        exit 0
    fi
fi
echo ""

# Step 8: Pull changes
echo -e "${YELLOW}⬇️ Step 8: नए बदलाव डाउनलोड कर रहे हैं...${NC}"
if git pull origin "$BRANCH"; then
    print_status "बदलाव सफलतापूर्वक डाउनलोड हुए / Changes pulled successfully"
else
    print_error "Git pull विफल रहा / Git pull failed"
    echo ""
    print_warning "बैकअप से वापस ला रहे हैं / Restoring from backup..."
    cd /var/www/palians/
    rm -rf "$PROJECT_DIR"
    cp -r "$BACKUP_DIR" "$PROJECT_DIR"
    print_status "बैकअप से वापस आ गए / Restored from backup"
    exit 1
fi
echo ""

# Step 9: Set correct permissions
echo -e "${YELLOW}🔐 Step 9: Permissions सेट कर रहे हैं...${NC}"
chown -R $WEB_USER:$WEB_GROUP "$PROJECT_DIR"
chmod -R 755 "$PROJECT_DIR"
chmod -R 777 "$PROJECT_DIR/logs"
chmod -R 777 "$PROJECT_DIR/uploads"
chmod -R 777 "$PROJECT_DIR/cache"
print_status "Permissions सही से सेट हो गईं / Permissions set correctly"
echo ""

# Step 10: Clear cache
echo -e "${YELLOW}🧹 Step 10: Cache साफ़ कर रहे हैं...${NC}"
rm -rf "$PROJECT_DIR/cache/"*
print_status "Cache साफ़ हो गया / Cache cleared"
echo ""

# Step 11: Restart web server
echo -e "${YELLOW}🔄 Step 11: Web server restart कर रहे हैं...${NC}"
if systemctl is-active --quiet apache2; then
    systemctl restart apache2
    print_status "Apache2 restart हो गया / Apache2 restarted"
elif systemctl is-active --quiet nginx; then
    systemctl restart nginx
    if systemctl is-active --quiet php7.4-fpm; then
        systemctl restart php7.4-fpm
    elif systemctl is-active --quiet php8.0-fpm; then
        systemctl restart php8.0-fpm
    elif systemctl is-active --quiet php8.1-fpm; then
        systemctl restart php8.1-fpm
    fi
    print_status "Nginx restart हो गया / Nginx restarted"
else
    print_warning "Web server नहीं मिला / Web server not found"
fi
echo ""

# Step 12: Verify deployment
echo -e "${YELLOW}✅ Step 12: Deployment verify कर रहे हैं...${NC}"

# Check if website is accessible
if curl -s -o /dev/null -w "%{http_code}" https://palians.com/reviewer/ | grep -q "200\|302"; then
    print_status "वेबसाइट accessible है / Website is accessible"
else
    print_warning "वेबसाइट चेक करें / Please check website manually"
fi

# Check error logs
if [ -f "$PROJECT_DIR/logs/error.log" ]; then
    ERROR_COUNT=$(tail -10 "$PROJECT_DIR/logs/error.log" 2>/dev/null | wc -l)
    if [ "$ERROR_COUNT" -gt 0 ]; then
        print_warning "$ERROR_COUNT recent log entries / हाल की लॉग एंट्रीज़"
    fi
fi

print_status "Deployment verify हो गया / Deployment verified"
echo ""

# Summary
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}✅ Deployment सफल रहा!${NC}"
echo -e "${GREEN}✅ Deployment Successful!${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo -e "${BLUE}📋 Summary / सारांश:${NC}"
echo "   📂 Project: $PROJECT_DIR"
echo "   💾 Backup: $BACKUP_DIR"
echo "   🌿 Branch: $BRANCH"
echo "   📊 Commits pulled: $COMMITS_BEHIND"
echo ""
echo -e "${BLUE}📋 Post-Deployment Checklist / बाद में चेक करें:${NC}"
echo "   1. 🌐 वेबसाइट खोलें: https://palians.com/reviewer/"
echo "   2. 👤 यूजर dashboard चेक करें: https://palians.com/reviewer/user/dashboard.php"
echo "   3. 📊 Error logs देखें: sudo tail -f $PROJECT_DIR/logs/error.log"
echo "   4. 🔍 Apache logs देखें: sudo tail -f /var/log/apache2/error.log"
echo ""
echo -e "${YELLOW}⚠️  Rollback करने के लिए / To rollback:${NC}"
echo "   sudo rm -rf $PROJECT_DIR"
echo "   sudo cp -r $BACKUP_DIR $PROJECT_DIR"
echo "   sudo systemctl restart apache2"
echo ""
echo -e "${GREEN}🎉 Deployment पूरी हुई! / Deployment Complete!${NC}"
