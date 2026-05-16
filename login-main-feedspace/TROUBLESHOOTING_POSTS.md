# Post Creation Issues - Troubleshooting Guide

## 🚨 Your system failed to create posts. Here's how to fix it:

---

## ⚡ Quick Fix Steps (Try these first)

### Step 1: Test the Diagnostic Tool
```
1. Go to: http://localhost/login-main-feedspace/test-post-creation.php
2. Log in first if prompted
3. Check what tests pass/fail
4. Fix any ❌ items
```

### Step 2: Test Manual Post Creation
```
1. Go to: http://localhost/login-main-feedspace/simple-post-test.php
2. Write a test post
3. Click "Create Post"
4. If it works here, the database is fine
5. If it fails, check the error message
```

### Step 3: Check Browser Console
```
1. Open your app in browser
2. Press F12 (Developer Tools)
3. Click "Console" tab
4. Try to create a post
5. Look for red error messages
6. Screenshot the error and check below
```

---

## 🔴 Common Errors & Fixes

### Error: "Unauthorized"
**Problem**: Not logged in or session expired
**Fix**:
- Log out completely
- Log back in
- Try posting again

### Error: "Database connection failed"
**Problem**: MySQL not running
**Fix**:
```bash
# Windows - Open cmd and type:
mysql -u root
# If it connects, database is working
# Type: exit
```

### Error: "Failed to create post" (no details)
**Problem**: Database error but not shown
**Fix**:
- Run test-post-creation.php to see actual error
- Check if posts table exists
- Check database permissions

### Error: "Method not allowed"
**Problem**: POST request not working
**Fix**:
- Make sure you're on a login page (needs session)
- Check browser console for full error
- Verify create-post.php is in correct path

### Error: Timeout or Hang
**Problem**: Python AI moderation taking too long or Python not installed
**Fix**:
- Don't worry - I made AI optional
- Posts should still save without Python
- If hanging, clear browser and refresh

---

## 🔍 Step-by-Step Debugging

### 1. Verify Database Connection
```bash
# Open Command Prompt
mysql -u root
# Inside MySQL:
USE db_feedspace;
SHOW TABLES;
# You should see: posts, comments, users, etc.
DESCRIBE posts;
# Check if these columns exist: post_id, user_id, content
EXIT;
```

### 2. Verify File Permissions
```bash
# Navigate to project folder
cd d:\xampp\htdocs\login-main-feedspace

# Check if uploads folder exists and is writable
# For Windows, right-click uploads/posts folder:
# Properties → Security → Edit → Users → Full Control → OK
```

### 3. Check API Endpoint Directly
```bash
# Using curl or Postman:
curl -X POST http://localhost/login-main-feedspace/main/api/users/posts/create-post.php \
  -d "content=Test%20post" \
  -H "Cookie: PHPSESSID=your_session_id"

# Response should be JSON with success: true
```

### 4. Check Logs
```bash
# PHP error log location (Windows XAMPP):
# C:\xampp\apache\logs\error.log
# C:\xampp\php\logs\php_error_log

# Open with notepad and look for recent errors:
# "Post creation failed for user..."
```

---

## 📋 Checklist Before Posting

- ✅ User is logged in (check session)
- ✅ MySQL is running (test-post-creation.php shows connection)
- ✅ posts table exists with correct columns
- ✅ User has permission to write to database
- ✅ uploads/posts folder exists and is writable
- ✅ Browser console shows no errors (press F12)

---

## 🛠️ Changes I Made to Fix Issues

### 1. **Added Better Error Handling**
   - create-post.php now catches errors and shows messages
   - add-comments.php validates database prepare statement
   - Both return specific error messages for debugging

### 2. **Made AI Moderation Optional**
   - Posts still save even if Python isn't installed
   - Default to "safe" if AI check fails
   - No blocking timeouts

### 3. **Improved Frontend**
   - submitPost() now logs errors to console
   - Shows detailed error messages
   - Disabled button while saving
   - Clear user feedback

### 4. **Added Diagnostic Tools**
   - test-post-creation.php - Tests all components
   - simple-post-test.php - Direct database test
   - Console logging in JavaScript

---

## 🔗 API Endpoints

### Create Post
```
POST /main/api/users/posts/create-post.php
Content-Type: application/x-www-form-urlencoded

Parameters:
- content (required): Post text (max 1000 chars)

Response:
{
  "success": true,
  "post_id": 28,
  "ai_status": "safe|review|rejected",
  "ai_score": 0.15,
  "warning": "optional message"
}
```

### Get Posts
```
POST /main/api/users/posts/get-posts.php
Content-Type: application/x-www-form-urlencoded

Parameters:
- page: Page number (default 1)

Response:
{
  "success": true,
  "posts": [...],
  "pagination": {...}
}
```

### Add Comment
```
POST /main/api/users/interactions/add-comments.php?id=POST_ID
Content-Type: application/json

Body:
{
  "postId": 28,
  "text": "Comment text"
}

Response:
{
  "success": true,
  "comment_id": 42,
  "moderation_status": "approved|flagged|removed"
}
```

---

## 📊 Database Verification

### Check if posts are being saved:
```sql
SELECT * FROM posts ORDER BY created_at DESC LIMIT 5;
```

### Check user status:
```sql
SELECT * FROM users WHERE user_id = 'YOUR_USER_ID';
```

### Check for bans:
```sql
SELECT * FROM user_bans WHERE user_id = 'YOUR_USER_ID';
```

### Check comments:
```sql
SELECT * FROM comments ORDER BY created_at DESC LIMIT 5;
```

---

## 📞 If All Else Fails

1. **Take a screenshot** of:
   - The error message from test-post-creation.php
   - Browser console error (F12)
   - Any MySQL error

2. **Check log files**:
   - C:\xampp\apache\logs\error.log
   - C:\xampp\php\logs\php_error_log

3. **Try these commands**:
   ```bash
   # Clear PHP cache
   php -r "opcache_reset();"
   
   # Restart Apache
   # In XAMPP Control Panel: Click "Stop" then "Start" on Apache
   
   # Restart MySQL
   # In XAMPP Control Panel: Click "Stop" then "Start" on MySQL
   ```

---

## ✅ What Should Happen

When you create a post:

1. **Click "+ Create Post"** in main feed
2. **Write text** and click button
3. **Toast notification** appears: "Creating..."
4. **Post appears** in feed with your content
5. **Comment count** shows "0" initially
6. **Like button** appears (hollow heart)

If any step fails, check browser console (F12) for errors.

---

## 🎯 Testing After Fix

1. **Simple test**: Use simple-post-test.php
2. **Database test**: Use test-post-creation.php
3. **Full test**: Create post in main feed
4. **Comment test**: Try commenting on post
5. **Like test**: Try liking a post

All should work without errors.

---

**Last Updated**: May 14, 2026
**Status**: Troubleshooting Guide v1.0
