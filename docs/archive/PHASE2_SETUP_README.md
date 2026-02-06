# Phase 2 Setup Guide - ReviewFlow Advanced Features

This document provides complete instructions for setting up the Phase 2 advanced features for ReviewFlow.

## Table of Contents
1. [Overview](#overview)
2. [Database Setup](#database-setup)
3. [Feature Breakdown](#feature-breakdown)
4. [Configuration](#configuration)
5. [Testing Guide](#testing-guide)
6. [Troubleshooting](#troubleshooting)

---

## Overview

Phase 2 adds four major features to ReviewFlow:

1. **Referral & Affiliate System** - Multi-level commission system for user referrals
2. **Review Proof System** - AI-powered verification of task completion proofs
3. **Gamification & Rewards** - Points, levels, badges, and leaderboards
4. **In-App Chat & Support** - Real-time chat system between users and admins

---

## Database Setup

### Step 1: Run Migration Files

Execute the SQL migration files in the following order:

```bash
# Navigate to your database
mysql -u reviewflow_user -p reviewflow

# Run migrations
source /path/to/reviewer/migrations/phase2_referrals.sql
source /path/to/reviewer/migrations/phase2_proofs.sql
source /path/to/reviewer/migrations/phase2_gamification.sql
source /path/to/reviewer/migrations/phase2_chat.sql
```

Or use phpMyAdmin:
1. Login to phpMyAdmin
2. Select the `reviewflow` database
3. Go to "Import" tab
4. Upload and execute each SQL file in order

### Step 2: Verify Tables Created

Verify these tables exist:
- `referrals`, `referral_earnings`, `referral_settings`
- `task_proofs`, `proof_verification_logs`
- `user_points`, `point_transactions`, `badges`, `user_badges`, `level_settings`
- `chat_conversations`, `chat_messages`, `canned_responses`, `chat_typing_status`

### Step 3: Verify Directory Structure

Ensure these upload directories exist with proper permissions (755):
```
uploads/
├── proofs/
└── chat/
```

Create if missing:
```bash
mkdir -p uploads/proofs uploads/chat
chmod 755 uploads/proofs uploads/chat
```

---

## Feature Breakdown

### 1. Referral & Affiliate System

**User Features:**
- Unique referral code for each user
- Share referral link via WhatsApp, Facebook, Twitter
- Multi-level commission tracking (3 levels)
- Referral dashboard with statistics and earnings

**Admin Features:**
- Configure commission rates for each level
- View all referrals and earnings
- Monitor system statistics

**How It Works:**
1. User gets unique referral code on signup
2. Shares link with friends
3. When referee completes first task, referral becomes active
4. On every task completion, referrer earns commission based on level
5. Commission auto-credited to wallet

**Files:**
- User: `user/referral.php`
- Admin: `admin/referral-settings.php`
- Functions: `includes/referral-functions.php`

**Default Commission Rates:**
- Level 1 (Direct): 10%
- Level 2 (2nd Generation): 5%
- Level 3 (3rd Generation): 2%

---

### 2. Review Proof System with AI Verification

**User Features:**
- Submit proof after task completion
- Three proof types: Screenshot, Order ID, Review Link
- View proof status and AI verification score
- Proof history with timestamps

**Admin Features:**
- Verify pending proofs
- View AI confidence scores
- Approve or reject with reasons
- Bulk verification capabilities

**How It Works:**
1. User uploads proof (screenshot/text)
2. AI analyzes screenshot quality and content
3. If AI score ≥80%, auto-approve; otherwise manual review
4. Admin reviews and approves/rejects
5. User gets notification of decision

**Files:**
- User: `user/submit-proof.php`
- Admin: `admin/verify-proofs.php`, `admin/proof-view.php`
- Functions: `includes/proof-functions.php`, `includes/ai-verification.php`

**AI Verification:**
- Basic image quality check
- Screenshot detection
- OCR text extraction (simulated)
- Keyword matching
- Confidence scoring

---

### 3. Gamification & Rewards System

**User Features:**
- User levels: Bronze, Silver, Gold, Platinum, Diamond
- Points for activities (tasks, referrals, login streaks)
- Badges for achievements
- Leaderboards (daily, weekly, monthly, all-time)
- Progress tracking and level-up rewards

**Admin Features:**
- Configure level settings and requirements
- Manage badge criteria
- View system-wide statistics
- Award manual points/badges

**Point System:**
- Task completion: 10 points
- Referral: 50 points
- Daily login: 5 points (+ streak bonus)
- Profile completion: 20 points
- Level up: Bonus points

**Levels:**
- Bronze: 0-99 points
- Silver: 100-499 points
- Gold: 500-1,499 points
- Platinum: 1,500-4,999 points
- Diamond: 5,000+ points

**Files:**
- User: `user/rewards.php`, `user/leaderboard.php`
- Admin: `admin/gamification-settings.php`
- Functions: `includes/gamification-functions.php`

---

### 4. In-App Chat & Support System

**User Features:**
- Real-time chat with support team
- Send messages and attachments
- Chat history
- Unread message notifications
- Floating chat widget on all pages

**Admin Features:**
- Manage all conversations
- Canned responses for quick replies
- Assign conversations to admins
- Close/reopen conversations
- Search and filter chats

**How It Works:**
1. User clicks chat button
2. Conversation created automatically
3. Messages sent via AJAX
4. Auto-refresh every 3-5 seconds
5. Admin responds from dashboard
6. Real-time notifications

**Files:**
- User: `user/chat.php`
- Admin: `admin/support-chat.php`
- Widget: `includes/chat-widget.php`
- API: `api/chat.php`
- Functions: `includes/chat-functions.php`

**Canned Responses:**
Pre-configured quick replies for common queries

---

## Configuration

### 1. Enable Chat Widget

Add to your footer file (`includes/footer.php`):

```php
<?php
// Add chat widget before closing body tag
if (isLoggedIn()) {
    include __DIR__ . '/chat-widget.php';
}
?>
```

### 2. Update Navigation

Add new menu items to user sidebar:

```php
<a href="referral.php">
    <i class="bi bi-people"></i> Referrals
</a>
<a href="rewards.php">
    <i class="bi bi-trophy"></i> Rewards
</a>
<a href="leaderboard.php">
    <i class="bi bi-bar-chart"></i> Leaderboard
</a>
<a href="submit-proof.php">
    <i class="bi bi-file-earmark-check"></i> Submit Proof
</a>
<a href="chat.php">
    <i class="bi bi-chat-dots"></i> Chat Support
</a>
```

### 3. Integrate with Existing Task System

Add to your task completion logic:

```php
// After task is approved
require_once 'includes/referral-functions.php';
require_once 'includes/gamification-functions.php';

// Award points for task completion
awardTaskCompletionPoints($db, $user_id, $task_id);

// Credit referral commissions
creditReferralCommission($db, $user_id, $task_id, $task_amount);

// Check for badge achievements
checkBadgeAchievements($db, $user_id);
```

### 4. New User Registration

Add to signup process:

```php
require_once 'includes/referral-functions.php';
require_once 'includes/gamification-functions.php';

// Generate referral code
$referral_code = generateReferralCode($new_user_id);

// Check if referred by someone
if (isset($_GET['ref'])) {
    $referrer = getUserByReferralCode($db, $_GET['ref']);
    if ($referrer) {
        createReferral($db, $referrer['id'], $new_user_id);
    }
}

// Initialize points
initializeUserPoints($db, $new_user_id);
```

---

## Testing Guide

### Test Referral System

1. **Create Referral:**
   - Login as User A
   - Go to Referrals page
   - Copy referral link
   - Logout and signup as User B using the link
   - Verify referral created in database

2. **Test Commission:**
   - Complete a task as User B
   - Check User A's wallet for commission
   - Verify commission in `referral_earnings` table

3. **Multi-level Test:**
   - User A refers User B
   - User B refers User C
   - User C completes task
   - Check commissions for both A and B

### Test Proof System

1. **Submit Proof:**
   - Login as user
   - Go to Submit Proof page
   - Upload a screenshot
   - Verify AI score calculated

2. **Admin Verification:**
   - Login as admin
   - Go to Verify Proofs
   - View pending proof
   - Approve/Reject with reason
   - Check user notification

### Test Gamification

1. **Earn Points:**
   - Complete tasks
   - Refer users
   - Login daily
   - Check points credited

2. **Level Up:**
   - Accumulate points
   - Verify level changes
   - Check level-up notification

3. **Badges:**
   - Trigger badge criteria
   - Verify badge awarded
   - Check badge display

### Test Chat System

1. **User Chat:**
   - Login as user
   - Click chat button
   - Send message
   - Upload attachment

2. **Admin Response:**
   - Login as admin
   - Open Support Chat
   - View user message
   - Respond
   - Use canned response

3. **Real-time Updates:**
   - Keep both windows open
   - Verify messages appear automatically
   - Check unread count updates

---

## Troubleshooting

### Common Issues

**1. Referral Code Not Generated**
- Check if `referral_code` column exists in `users` table
- Run referral migration again
- Manually update: `UPDATE users SET referral_code = CONCAT('REF', LPAD(id, 6, '0')) WHERE referral_code IS NULL`

**2. Points Not Awarded**
- Verify `user_points` table exists
- Check if user record exists in `user_points`
- Initialize manually: `initializeUserPoints($db, $user_id)`

**3. Chat Not Loading**
- Check if `chat_conversations` and `chat_messages` tables exist
- Verify API endpoint is accessible: `/reviewer/api/chat.php`
- Check JavaScript console for errors
- Verify AJAX requests not blocked by firewall

**4. Proof Upload Fails**
- Check `uploads/proofs` directory exists
- Verify directory permissions (755)
- Check PHP upload_max_filesize setting
- Verify file type is allowed

**5. AI Verification Not Working**
- AI verification is simulated by default
- For production, integrate actual OCR service
- Check `ai-verification.php` for implementation

**6. Database Connection Errors**
- Verify database credentials in `includes/config.php`
- Check if all tables were created successfully
- Verify user has proper database permissions

**7. Navigation Links Not Working**
- Clear browser cache
- Check file paths are correct
- Verify all PHP files exist
- Check for PHP syntax errors

---

## Security Considerations

1. **CSRF Protection:**
   - All forms should include CSRF tokens
   - Validate tokens on form submission

2. **File Upload Security:**
   - Validate file types and sizes
   - Sanitize filenames
   - Store uploads outside web root if possible

3. **SQL Injection Prevention:**
   - All queries use prepared statements
   - Never concatenate user input into queries

4. **XSS Prevention:**
   - Always use `htmlspecialchars()` for output
   - Sanitize user input
   - Use Content Security Policy headers

5. **Access Control:**
   - Verify user permissions on every page
   - Check both authentication and authorization
   - Prevent users from accessing admin pages

---

## Performance Optimization

1. **Database Indexes:**
   - All foreign keys are indexed
   - Add indexes on frequently queried columns
   - Monitor slow query log

2. **Caching:**
   - Cache leaderboard results (5-10 minutes)
   - Cache user points and levels
   - Use Redis/Memcached for sessions

3. **Image Optimization:**
   - Compress uploaded images
   - Generate thumbnails for proofs
   - Use lazy loading for images

4. **AJAX Optimization:**
   - Implement proper polling intervals
   - Use WebSockets for real-time features (advanced)
   - Minimize payload size

---

## Future Enhancements

1. **Referral System:**
   - Referral contests and competitions
   - Tiered commission rates based on performance
   - Referral analytics dashboard

2. **Proof System:**
   - Integration with real AI/OCR services
   - Automated screenshot analysis
   - Video proof support

3. **Gamification:**
   - Seasonal events and challenges
   - Team competitions
   - Reward redemption system

4. **Chat System:**
   - WebSocket implementation for real-time
   - Voice/video call support
   - AI chatbot for common queries
   - Multi-language support

---

## Support

For issues or questions:
- Check troubleshooting section first
- Review code comments in PHP files
- Check database for data consistency
- Enable DEBUG mode in `includes/config.php` for detailed errors

---

## Changelog

### Version 2.0.0 - Phase 2 Release
- Added Referral & Affiliate System
- Added Review Proof System with AI Verification
- Added Gamification & Rewards System
- Added In-App Chat & Support System
- Database migrations for all new features
- Complete user and admin interfaces
- API endpoints for real-time functionality

---

**Last Updated:** February 3, 2026
**Authors:** Development Team
**Version:** 2.0.0
