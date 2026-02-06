# Chatbot Fix - Final Validation Checklist

## Pre-Deployment Checklist

### Database Setup
- [ ] Run migration script: `mysql -u reviewflow_user -p reviewflow < migrations/chatbot_tables.sql`
- [ ] Verify `chatbot_unanswered` table exists
- [ ] Verify `faq` table exists
- [ ] Confirm 5 FAQs were inserted for sellers

### Configuration
- [ ] Verify `DEBUG = false` in `includes/config.php` (for production)
- [ ] Verify session cookie settings are correct in config.php
- [ ] Confirm APP_URL is set correctly

### File Permissions (if needed)
```bash
chmod 644 includes/chatbot-widget.php
chmod 644 chatbot/process.php
chmod 644 seller/includes/header.php
chmod 755 chatbot/
```

## Testing Checklist

### Seller Dashboard Testing
- [ ] Login as a seller
- [ ] Verify chatbot trigger button appears (bottom right)
- [ ] Click trigger button - widget should open with animation
- [ ] Verify welcome message shows seller's name
- [ ] Verify three quick action buttons appear:
  - [ ] "Request Reviews"
  - [ ] "Wallet Recharge"
  - [ ] "View Invoices"

### Functionality Testing
- [ ] Click "Request Reviews" button
  - [ ] Should auto-fill question and submit
  - [ ] Should receive detailed response about requesting reviews
- [ ] Type and send custom message: "How do I request reviews?"
  - [ ] Should show typing indicator
  - [ ] Should receive formatted response with steps
- [ ] Test wallet question: "How do I recharge my wallet?"
  - [ ] Should receive detailed wallet recharge guide
- [ ] Test invoice question: "How do I view my invoices?"
  - [ ] Should receive invoice viewing instructions
- [ ] Test generic question: "hello"
  - [ ] Should receive helpful response with topic list

### Session Testing
- [ ] Verify `$_SESSION['seller_id']` is set
- [ ] Verify `$_SESSION['seller_name']` is set
- [ ] Open browser console (F12)
- [ ] Check for any JavaScript errors
- [ ] Verify console shows API calls:
  ```
  Sending to chatbot API: https://palians.com/reviewer/chatbot/process.php
  Response status: 200
  Chatbot response: {success: true, response: "..."}
  ```

### UI/UX Testing
- [ ] Widget opens smoothly with animation
- [ ] Widget closes when clicking X button
- [ ] Widget minimizes when clicking - button
- [ ] Messages display correctly
- [ ] Scroll works when many messages
- [ ] Input field is functional
- [ ] Send button works
- [ ] Quick action buttons are clickable
- [ ] Formatting is correct (bold text, line breaks)

### Mobile Responsiveness
- [ ] Open on mobile device or use browser DevTools
- [ ] Widget adjusts to mobile screen size
- [ ] All buttons are touch-friendly
- [ ] Widget doesn't overflow screen
- [ ] Scrolling works properly

### Error Handling
- [ ] Test with database connection error (should show friendly error)
- [ ] Test with invalid input (should validate)
- [ ] Test with network disconnection (should show error message)
- [ ] Check server logs for any PHP errors

### Security Testing
- [ ] Verify no sensitive data logged to console (production)
- [ ] Verify no SQL injection vulnerabilities
- [ ] Verify HTML is properly escaped
- [ ] Verify CSRF tokens (if implemented)
- [ ] Verify session timeout works

## Browser Compatibility
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if applicable)
- [ ] Mobile browsers (Chrome, Safari)

## Performance Testing
- [ ] Page load time acceptable with chatbot
- [ ] No memory leaks when opening/closing multiple times
- [ ] AJAX requests complete quickly (< 2 seconds)
- [ ] No console warnings

## Code Quality
- [x] PHP syntax validated (no errors)
- [x] Code review completed
- [x] Security review completed
- [x] Debug logging protected by DEBUG flag
- [x] Documentation complete

## Known Limitations
- Database migration required before chatbot works
- FAQ matching is keyword-based (not AI-powered yet)
- No chat history persistence across sessions
- No file upload support
- No admin FAQ management UI yet

## Rollback Plan
If issues occur after deployment:

1. **Disable Chatbot Widget**
   - Remove/comment out chatbot include in `seller/includes/footer.php`
   ```php
   // <?php require_once __DIR__ . '/../../includes/chatbot-widget.php'; ?>
   ```

2. **Revert Changes**
   ```bash
   git checkout main -- includes/chatbot-widget.php
   git checkout main -- chatbot/process.php
   git checkout main -- seller/includes/header.php
   ```

3. **Remove Database Tables** (if needed)
   ```sql
   DROP TABLE IF EXISTS chatbot_unanswered;
   DROP TABLE IF EXISTS faq;
   ```

## Post-Deployment Monitoring

### First 24 Hours
- [ ] Monitor error logs: `tail -f logs/error.log`
- [ ] Check for JavaScript errors in browser console
- [ ] Monitor database for chatbot_unanswered entries
- [ ] Track user feedback
- [ ] Check server load/performance

### First Week
- [ ] Review unanswered questions in database
- [ ] Add new FAQs based on common questions
- [ ] Check for any reported issues
- [ ] Gather user feedback

### Metrics to Track
- Number of chatbot interactions
- Most common questions
- FAQ hit rate
- User satisfaction
- Response time performance

## Support Information

### Error Logs Location
- PHP errors: `logs/error.log`
- Browser console: F12 → Console tab
- Network requests: F12 → Network tab

### Key Files for Debugging
- `includes/chatbot-widget.php` - Frontend widget
- `chatbot/process.php` - Backend logic
- `seller/includes/header.php` - Session setup
- `includes/config.php` - Configuration

### Debug Mode
To enable debug logging:
1. Edit `includes/config.php`
2. Change `const DEBUG = false;` to `const DEBUG = true;`
3. Check `logs/error.log` for detailed session information

### Common Issues

**Issue**: Chatbot button not visible
**Fix**: Check if footer.php includes chatbot widget

**Issue**: Generic responses only
**Fix**: Verify session variables are set, check userType in API call

**Issue**: JavaScript errors
**Fix**: Ensure Bootstrap Icons CDN is loaded

**Issue**: Database errors
**Fix**: Run migration script to create tables

**Issue**: Session timeout
**Fix**: Check SESSION_TIMEOUT constant in config.php

## Sign-Off

After completing all tests above:

- [ ] Functional tests passed
- [ ] Security tests passed
- [ ] Performance acceptable
- [ ] No critical issues found
- [ ] Documentation reviewed
- [ ] Rollback plan tested
- [ ] Team notified of deployment

**Deployed by**: _________________
**Date**: _________________
**Environment**: Production / Staging
**Version**: ReviewFlow v3.0 + Chatbot v2.0

---

## Additional Notes

[Add any deployment-specific notes here]
