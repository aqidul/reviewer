# ReviewFlow Version 2.0 - Implementation Guide

## 🎉 Welcome to Version 2.0!

This major update brings significant improvements to the ReviewFlow platform with bug fixes, new features, and a modern UI refresh.

## 📋 What's New in Version 2.0

### 🐛 Bug Fixes
- **Wallet Balance Updates** - Fixed wallet credit issues after admin approval
- **Review Request Display** - All review data now properly displayed to admins
- **Approved Requests Visibility** - Enhanced filtering and display of approved requests

### ✨ New Features

#### 1. AI Chatbot Assistant
- Self-learning chatbot on all dashboards (Admin, Seller, User)
- Context-aware quick actions based on user type
- FAQ integration with automatic learning
- Real-time responses

#### 2. Admin Login as Seller
- Impersonate any seller account for support purposes
- Clear visual indicator when impersonating
- Easy "Return to Admin" button
- Session tracking and logging

#### 3. Brand-wise Task Organization
- Tasks grouped by brand in collapsible folders
- Date-wise sorting (recent first)
- Count and total amount per brand
- Clean, organized view

#### 4. Data Export System
- Export review data to CSV/Excel format
- Filter by brand and date range
- Includes all task and reviewer details
- UTF-8 encoding for Excel compatibility

#### 5. Light/Dark Theme Toggle
- Beautiful theme switcher on all pages
- Automatic system preference detection
- localStorage persistence
- Smooth transitions

### 🎨 UI/UX Improvements
- Modern glassmorphism effects
- Smooth animations throughout
- Professional typography
- Consistent color scheme
- Responsive design improvements

### 📊 Version Display
- Version number shown on all dashboards
- Link to changelog
- Clean, unobtrusive design

## 🚀 Installation & Setup

### 1. Database Migration

Run the migration script to add brand and seller fields to tasks:

```bash
mysql -u reviewflow_user -p reviewflow < migrations/add_brand_seller_to_tasks.sql
```

### 2. Update Configuration

The version has been automatically updated in `includes/config.php`:
```php
const APP_VERSION = '2.0.0';
```

### 3. Clear Cache

Clear browser cache and any server-side caching to ensure new CSS/JS loads:

```bash
# If using opcache
php -r "opcache_reset();"

# Clear browser cache (Ctrl+Shift+Delete)
```

## 📁 New Files & Components

### Components (includes/)
- `chatbot-widget.php` - Reusable AI chatbot widget
- `theme-switcher.php` - Theme toggle component
- `impersonation-banner.php` - Admin impersonation indicator
- `version-display.php` - Version display component

### Admin Features (admin/)
- `login-as-seller.php` - Seller impersonation handler
- `export-data.php` - Data export functionality
- `task-completed-brandwise.php` - Brand-wise task view

### Assets
- `assets/css/themes.css` - Theme system with CSS variables
- `assets/js/theme.js` - Theme manager with localStorage

### API
- `chatbot/process.php` - Chatbot message processing endpoint

### Documentation
- `CHANGELOG.md` - Complete version history

## 🎯 Feature Usage Guide

### For Admins

#### Login as Seller
1. Go to "Sellers" page
2. Find the seller you want to login as
3. Click "🔐 Login as Seller" button
4. You'll be redirected to seller dashboard
5. Orange banner at top indicates impersonation mode
6. Click "Return to Admin" to go back

#### Export Data
1. Go to "Export Data" in sidebar
2. Select brand from dropdown
3. Optionally set date range
4. Click "Export to CSV"
5. File downloads automatically

#### Brand-wise Tasks
1. Go to "Completed Tasks (Brand-wise)" in sidebar
2. See tasks organized by brand folders
3. Click any folder to expand/collapse
4. View task details and amounts

### For Sellers

#### Wallet Recharge
1. Go to "Wallet" page
2. Click "Recharge Wallet"
3. Transfer money to provided bank account
4. Enter UTR number and upload screenshot
5. Wait for admin approval (usually 24 hours)

#### View Own Brand Data
- Your dashboard shows only data for your brands
- Export option available for your brand data
- Date-wise filtering available

### For Users (Reviewers)

#### Complete Tasks
1. Check "My Tasks" on dashboard
2. Follow 4-step process for each task
3. Upload required screenshots
4. Earn commission after admin approval

### For Everyone

#### Theme Toggle
- Click sun/moon icon in top-right corner
- Theme preference saved automatically
- Works across all pages

#### AI Chatbot
- Click chat icon in bottom-right corner
- Type your question or click quick action
- Get instant contextual help
- Available 24/7

## 🔧 Configuration Options

### Chatbot Customization
Edit `includes/chatbot-widget.php` to customize:
- Quick action buttons
- Welcome message
- Colors and styling

### Theme Customization
Edit `assets/css/themes.css` to customize:
- Color variables
- Animation speeds
- Glassmorphism effects

### Version Display
Edit `includes/version-display.php` to customize:
- Position
- Style
- Visibility

## 🐛 Troubleshooting

### Chatbot Not Responding
1. Check `chatbot/process.php` exists
2. Verify database tables (chatbot_unanswered, faq)
3. Check browser console for errors

### Theme Not Switching
1. Clear browser cache
2. Check if `theme.js` is loading
3. Verify localStorage is enabled

### Impersonation Issues
1. Check admin session is active
2. Verify seller account is active
3. Check `admin-return.php` permissions

### Export Not Working
1. Check PHP file permissions
2. Verify database connection
3. Check error logs in `logs/error.log`

## 📈 Performance Tips

1. **Database Optimization**: Index brand_name and seller_id in tasks table
2. **Caching**: Enable opcache for PHP files
3. **CDN**: Use CDN for Bootstrap and icons
4. **Minification**: Minify CSS/JS for production

## 🔒 Security Considerations

1. **Impersonation Logging**: All admin impersonations are logged
2. **Session Timeout**: 1 hour default, configurable
3. **CSRF Protection**: All forms include CSRF tokens
4. **Input Sanitization**: All user inputs sanitized

## 🤝 Support

For issues or questions:
- Check CHANGELOG.md for known issues
- Review error logs in `logs/error.log`
- Contact: support@palians.com

## 📝 Developer Notes

### Code Standards
- Follow PSR-12 coding standard
- Use prepared statements for SQL
- Sanitize all user inputs
- Comment complex logic

### Testing Checklist
- [ ] Test all user types (Admin, Seller, User)
- [ ] Test theme switching
- [ ] Test chatbot responses
- [ ] Test impersonation flow
- [ ] Test data export
- [ ] Test on mobile devices
- [ ] Test with different browsers

## 🎊 Upgrade Path

From v1.0 to v2.0:
1. Backup database and files
2. Run migration scripts
3. Update config.php
4. Clear all caches
5. Test all features
6. Monitor error logs

## 📜 License

Proprietary - All rights reserved to ReviewFlow/Palians

---

**Version**: 2.0.0  
**Release Date**: February 2026  
**Status**: Production Ready ✅

Thank you for using ReviewFlow! 🚀
