# 🔧 Quick Fix Guide - AI Assistant Chatbot

## What's Wrong?
The AI Assistant on seller dashboard shows: **"Failed to process message"**

## What's the Fix?
Database tables are missing. Need to run a migration.

## How to Fix? (2 Steps)

### Step 1: Run Migration
```
Visit: https://palians.com/reviewer/migrate_chatbot.php
Click: "Run Migration Now" button
```

### Step 2: Delete Migration File (Security)
```
Delete: migrate_chatbot.php from server
```

## Done! 🎉

Test by asking: "How do I request reviews?" in the AI Assistant

---

## Need More Info?

- 📋 Quick Guide: `ACTION_REQUIRED.md`
- 📊 Visual Diagrams: `VISUAL_GUIDE.md`
- 📖 Detailed Steps: `CHATBOT_MIGRATION_INSTRUCTIONS.md`
- 🔧 Technical Docs: `CHATBOT_FIX_COMPLETE.md`

---

**Time Required:** 5 minutes  
**Risk:** Low  
**Impact:** Fixes critical seller feature
