# Phase 6: Advanced Enterprise Features

## Quick Start

Phase 6 adds enterprise-level features to ReviewFlow including email marketing, support tickets, seller analytics, advanced notifications, SEO tools, API rate limiting, and mobile app APIs.

## Installation

### Automated Installation (Recommended)

```bash
chmod +x install_phase6.sh
./install_phase6.sh
```

### Manual Installation

1. **Run Database Migrations:**
```bash
cd /path/to/reviewer
mysql -u reviewflow_user -p reviewflow < migrations/phase6_email_marketing.sql
mysql -u reviewflow_user -p reviewflow < migrations/phase6_tickets.sql
mysql -u reviewflow_user -p reviewflow < migrations/phase6_seller_enhancements.sql
mysql -u reviewflow_user -p reviewflow < migrations/phase6_notifications.sql
mysql -u reviewflow_user -p reviewflow < migrations/phase6_seo.sql
mysql -u reviewflow_user -p reviewflow < migrations/phase6_api.sql
```

2. **Create Required Directories:**
```bash
mkdir -p uploads/tickets
mkdir -p cache/email_templates
mkdir -p cache/api
chmod 755 uploads/tickets cache/email_templates cache/api
```

3. **Update Configuration:**
Edit `includes/config.php` and update:
```php
// Change this in production!
define('JWT_SECRET', 'your-unique-secret-key-here');
```

## Features Overview

### 1. 📧 Email Marketing System
- Campaign management with scheduling
- Email templates library
- Audience segmentation
- Analytics and tracking
- Unsubscribe management

**Access:** `/admin/email-campaigns.php`

### 2. 🎫 Support Ticket System
- User-friendly ticket creation
- Priority and category management
- File attachments
- Admin assignment and responses
- SLA tracking

**User Access:** `/user/support-tickets.php`
**Admin Access:** `/admin/tickets.php`

### 3. 🏪 Seller Dashboard Enhancements
- Advanced analytics with charts
- Bulk order creation
- Reusable order templates
- Review tracking
- ROI calculator

**Access:** `/seller/analytics.php`, `/seller/bulk-orders.php`

### 4. 🔔 Advanced Notification Center
- Category-based filtering
- Bulk actions
- User preferences
- Push notification support

**User Access:** `/user/notification-center.php`
**Admin Access:** `/admin/notification-manager.php`

### 5. 📈 SEO & Social Sharing
- Meta tags management
- Open Graph tags
- Dynamic sitemap
- Schema.org markup

**Admin Access:** `/admin/seo-settings.php`
**Public:** `/sitemap.php`, `/robots.txt`

### 6. 🔄 API Rate Limiting
- Request throttling
- API key management
- Usage analytics
- Endpoint monitoring

**Admin Access:** `/admin/api-settings.php`

### 7. 📱 Mobile App API
RESTful API endpoints for mobile applications:

**Authentication:**
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/register` - Registration
- `POST /api/v1/auth/refresh` - Token refresh

**Tasks:**
- `GET /api/v1/tasks` - List tasks
- `GET /api/v1/tasks/{id}` - Task details
- `POST /api/v1/tasks/submit-*` - Submit proofs

**Wallet:**
- `GET /api/v1/wallet/balance` - Get balance
- `GET /api/v1/wallet/transactions` - Transactions
- `POST /api/v1/wallet/withdraw` - Request withdrawal

**Notifications:**
- `GET /api/v1/notifications` - List notifications
- `POST /api/v1/notifications/mark-read` - Mark as read

**Profile:**
- `GET /api/v1/profile` - Get profile
- `PUT /api/v1/profile` - Update profile

## API Usage Example

### Authentication
```bash
# Login
curl -X POST https://your-domain.com/reviewer/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'

# Response
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {...},
    "auth": {
      "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
      "refresh_token": "abc123...",
      "expires_at": "2024-01-01 12:00:00",
      "token_type": "Bearer"
    }
  }
}
```

### Using JWT Token
```bash
# Get tasks
curl -X GET https://your-domain.com/reviewer/api/v1/tasks \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

## Security Configuration

### Production Checklist

- [ ] Change `JWT_SECRET` in `includes/config.php`
- [ ] Enable HTTPS
- [ ] Configure CORS settings
- [ ] Set up firewall rules
- [ ] Configure rate limits
- [ ] Set up email service
- [ ] Configure push notifications
- [ ] Review file upload permissions
- [ ] Enable error logging
- [ ] Set up backup schedule

### Recommended Settings

**Rate Limits:**
- Authentication: 20 requests/hour
- General API: 100 requests/hour
- Heavy endpoints: 50 requests/hour

**JWT Settings:**
- Token expiry: 1 hour
- Refresh token expiry: 30 days

**File Uploads:**
- Max size: 5MB
- Allowed types: jpg, jpeg, png, gif, pdf, doc, docx, txt, zip

## Testing

### Quick Test
```bash
# Test API ping
curl https://your-domain.com/reviewer/api/ping.php

# Test sitemap
curl https://your-domain.com/reviewer/sitemap.php

# Test robots.txt
curl https://your-domain.com/reviewer/robots.txt
```

### Full Test Checklist

See `PHASE6_IMPLEMENTATION_COMPLETE.md` for comprehensive testing checklist.

## File Structure

```
/reviewer/
├── admin/
│   ├── email-campaigns.php       [NEW]
│   ├── email-templates.php       [NEW]
│   ├── tickets.php               [NEW]
│   ├── ticket-view.php           [NEW]
│   ├── notification-manager.php  [NEW]
│   ├── seo-settings.php          [NEW]
│   └── api-settings.php          [NEW]
├── user/
│   ├── support-tickets.php       [NEW]
│   ├── create-ticket.php         [NEW]
│   ├── view-ticket.php           [NEW]
│   └── notification-center.php   [NEW]
├── seller/
│   ├── bulk-orders.php           [NEW]
│   ├── order-templates.php       [NEW]
│   └── reviews-tracking.php      [NEW]
├── api/v1/
│   ├── auth.php                  [NEW]
│   ├── tasks.php                 [NEW]
│   ├── wallet.php                [NEW]
│   ├── notifications.php         [NEW]
│   └── profile.php               [NEW]
├── includes/
│   ├── email-marketing-functions.php     [NEW]
│   ├── ticket-functions.php              [NEW]
│   ├── seller-analytics-functions.php    [NEW]
│   ├── notification-center-functions.php [NEW]
│   ├── seo-functions.php                 [NEW]
│   ├── rate-limit-functions.php          [NEW]
│   ├── api-functions.php                 [NEW]
│   └── jwt-functions.php                 [NEW]
├── migrations/
│   ├── phase6_email_marketing.sql        [NEW]
│   ├── phase6_tickets.sql                [NEW]
│   ├── phase6_seller_enhancements.sql    [NEW]
│   ├── phase6_notifications.sql          [NEW]
│   ├── phase6_seo.sql                    [NEW]
│   └── phase6_api.sql                    [NEW]
├── sitemap.php                   [NEW]
├── robots.txt                    [NEW]
├── install_phase6.sh             [NEW]
└── PHASE6_IMPLEMENTATION_COMPLETE.md [NEW]
```

## Troubleshooting

### Common Issues

**Issue:** JWT token errors
**Solution:** Verify `JWT_SECRET` is set and system time is synchronized

**Issue:** Rate limit errors
**Solution:** Run `cleanupRateLimitRecords()` to clear old records

**Issue:** Email not sending
**Solution:** Configure SMTP settings in `includes/config.php`

**Issue:** File upload fails
**Solution:** Check directory permissions for `uploads/tickets/`

**Issue:** API returns 404
**Solution:** Verify `.htaccess` or server URL rewriting is configured

### Debug Mode

Enable debug mode in `includes/config.php`:
```php
const DEBUG = true;
```

**Note:** Disable in production!

## Performance Tips

1. **Database Indexing:** All Phase 6 tables have proper indexes
2. **Caching:** Use cache directory for frequently accessed data
3. **Rate Limiting:** Prevents API abuse and server overload
4. **Pagination:** All list endpoints support pagination
5. **Query Optimization:** Use prepared statements and limit result sets

## Maintenance

### Daily Tasks
- Monitor API usage logs
- Check error logs
- Review new support tickets

### Weekly Tasks
- Clean up expired JWT tokens
- Archive old tickets
- Review email campaign performance

### Monthly Tasks
- Database optimization
- Update SEO settings
- Review and renew API keys
- Check storage usage

## Support

For detailed documentation, see:
- `PHASE6_IMPLEMENTATION_COMPLETE.md` - Complete feature documentation
- Individual page comments - Inline documentation
- API documentation - Available at `/admin/api-settings.php`

## Version

**Phase 6 Version:** 1.0.0
**Release Date:** February 2026
**Compatibility:** ReviewFlow v2.0.0+

## License

Same as ReviewFlow main application

---

**Congratulations! Phase 6 is now installed and ready to use.** 🎉

For questions or issues, please create a support ticket at `/user/create-ticket.php`
