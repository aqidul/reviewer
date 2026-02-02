# Chatbot Migration Instructions

## Issue
The AI Assistant chatbot on the seller dashboard shows error: **"Failed to process message"**

## Root Cause
Missing database tables:
- `chatbot_faq` - Stores FAQ entries for the chatbot
- `chatbot_unanswered` - Logs unanswered questions

## Solution

### Step 1: Run the Migration

Visit the migration page in your browser:
```
https://palians.com/reviewer/migrate_chatbot.php
```

### Step 2: Click "Run Migration Now"

The page will:
1. Check if tables exist
2. Create missing tables
3. Insert default FAQ entries
4. Show success confirmation

### Step 3: Verify the Fix

1. Log in as a seller at: `https://palians.com/reviewer/seller`
2. Click the AI Assistant chat icon (bottom right)
3. Ask a question like: "How do I request reviews?"
4. Verify you get a proper response (no error)

### Step 4: Security Cleanup

**IMPORTANT:** After successful migration, delete the migration file for security:
```
https://palians.com/reviewer/migrate_chatbot.php
```

## What Was Fixed

1. **Updated Migration Script** (`migrations/chatbot_tables.sql`)
   - Renamed `faq` table to `chatbot_faq`
   - Added `usage_count` column for tracking FAQ usage
   - Added `keywords` column for better matching
   - Added columns to `chatbot_unanswered` table: `user_name`, `occurrence_count`, `asked_count`, `first_asked_at`, `last_asked_at`

2. **Updated Code** (`chatbot/process.php`)
   - Changed table reference from `faq` to `chatbot_faq`

3. **Created Migration Tools**
   - `migrate_chatbot.php` - Web UI for running migration
   - `run_chatbot_migration.php` - CLI tool (optional)

## Testing

After migration, test the following:
- ✅ Seller can open AI Assistant
- ✅ Seller can ask questions
- ✅ Chatbot responds without errors
- ✅ FAQs are matched correctly
- ✅ Unanswered questions are logged

## Rollback (if needed)

If something goes wrong, you can drop the tables and try again:
```sql
DROP TABLE IF EXISTS chatbot_faq;
DROP TABLE IF EXISTS chatbot_unanswered;
```

Then re-run the migration.
