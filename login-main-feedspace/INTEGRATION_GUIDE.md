# FeedSpace Database & AI Moderation Integration Guide

## ✅ What's Been Completed

### 1. **Database Connection** 
- ✅ Central database configuration in `config/db.php` (localhost, root, db_feedspace)
- ✅ Database schema includes:
  - `posts` table with AI moderation fields (ai_score, ai_status, ai_reason)
  - `comments` table with moderation fields (moderation_status, toxicity_score)
  - `users` table for user information
  - `user_bans` table for ban management
  - `post_likes`, `shares` for interactions

### 2. **Post Creation with AI Moderation** ✅
- **File**: `main/api/users/posts/create-post.php`
- **Features**:
  - User authentication check
  - Ban status verification
  - Image upload support
  - AI toxicity detection via Python model
  - Post saved with moderation status:
    - **safe** (score < 0.5)
    - **review** (score 0.5-0.7)
    - **rejected** (score > 0.7)
  - Frontend feedback with warnings for flagged/rejected content

### 3. **Comment Creation with AI Moderation** ✅
- **File**: `main/api/users/interactions/add-comments.php`
- **Features**:
  - Session-based authentication
  - Post existence verification
  - Comment length validation (1-500 chars)
  - AI toxicity detection
  - Comments stored with moderation status:
    - **approved** (safe)
    - **flagged** (needs review)
    - **removed** (rejected)
  - User info returned with response

### 4. **Comment Retrieval** ✅
- **File**: `main/api/users/interactions/get-comments.php`
- **Features**:
  - Fetch approved/flagged comments for a post
  - Pagination support
  - User details included
  - Excludes removed comments from display

### 5. **Frontend Integration** ✅
- **Post Submission** (`feed.js`):
  - Form validation
  - AI moderation warnings display
  - Automatic feed refresh after posting
  - Success/warning toast notifications

- **Comment Submission** (`feed.js`):
  - Async comment submission to backend
  - Moderation badge on flagged comments
  - Comment count updates
  - User avatar and name display
  - Toast notifications for moderation warnings

### 6. **Toast Notification System** ✅
- **File**: `main/js/base.js`
- **File**: `main/css/base.css`
- **Features**:
  - Success (green)
  - Warning (orange) - for moderation alerts
  - Error (red)

### 7. **Helper Functions** ✅
- `config/ban-check.php`: User ban verification
- `main/api/users/posts/create-post.php::checkToxicity()`: AI moderation call
- `main/api/users/interactions/add-comments.php::checkToxicity()`: AI moderation call

---

## 🔧 How to Use

### **Creating a Post**
```javascript
// Frontend calls this automatically when user clicks "Create Post"
fetch('../api/users/posts/create-post.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: 'content=' + encodeURIComponent(text)
})
```

**Response**:
```json
{
  "success": true,
  "post_id": 28,
  "ai_status": "safe|review|rejected",
  "ai_score": 0.15,
  "warning": "Optional message if flagged"
}
```

### **Adding a Comment**
```javascript
// Frontend sends this when user clicks comment send button
fetch(`../api/users/interactions/add-comments.php?id=${postId}`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ 
    postId: postId,
    text: commentText 
  })
})
```

**Response**:
```json
{
  "success": true,
  "comment_id": 42,
  "moderation_status": "approved|flagged|removed",
  "toxicity_score": 0.12,
  "author": "John Doe",
  "avatar": "http://...",
  "created_at": "May 14, 2026 10:30",
  "comment_count": 5,
  "warning": "Optional message if moderated"
}
```

### **Fetching Comments**
```javascript
fetch('../api/users/interactions/get-comments.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: 'post_id=' + postId + '&page=1'
})
```

---

## 📋 Database Setup

### Required Tables (Already Created)

1. **users** - User information
2. **posts** - Posts with AI moderation fields
3. **comments** - Comments with moderation status
4. **post_likes** - Post like tracking
5. **shares** - Share tracking
6. **user_bans** - Ban management
7. **post_reports** - Post reporting system
8. **moderation_logs** - Moderation history

### Import Database Schema
```bash
mysql -u root db_feedspace < config/db_feedspace-2\ \(1\).sql
```

---

## 🐍 Python AI Model Setup

The system uses a Python-based toxic content detector.

### Requirements
- Python 3.6+
- pandas
- scikit-learn
- joblib

### Install Dependencies
```bash
pip install pandas scikit-learn joblib
```

### Train Model (First Time)
```bash
cd main/api/hate-speech-detection/
python3 toxic_detector.py
```

This will:
1. Load `labeled_data.csv`
2. Train a TF-IDF + Logistic Regression model
3. Save model as `model.pkl` and vectorizer as `vectorizer.pkl`

### Model Output
```json
[
  {
    "text": "Content here",
    "is_toxic": false,
    "toxicity_score": 0.15,
    "confidence": 0.95
  }
]
```

---

## 🔐 Security Features

1. **Session Authentication**: All endpoints require valid user session
2. **Ban Check**: Posts/comments from banned users rejected
3. **Input Validation**: 
   - Content length checks
   - Post ID verification
   - User ID validation
4. **SQL Injection Prevention**: Prepared statements used everywhere
5. **AI Moderation**: Automatic content filtering
6. **File Upload Security**: Allowed types, size limits, secure naming

---

## 🚀 Testing

### Test Post Creation
1. Navigate to feed page
2. Click "+ Create Post"
3. Enter text like "This is a test post"
4. Click "Share"
5. Check browser console for response
6. Verify post appears in feed with correct moderation status

### Test Comment System
1. Find a post on the feed
2. Click on post to expand
3. Write a comment in the comment box
4. Click send button
5. Verify comment appears immediately with user info
6. Check moderation badge if flagged

### Test AI Moderation
1. Create post with innocuous text → Should be marked "safe"
2. Create post with potentially toxic content → Will be marked "review" or "rejected"
3. Try commenting with flagged content → Comment will show warning badge

### Test Database Connection
```php
<?php
include 'config/db.php';
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM posts");
$row = mysqli_fetch_assoc($result);
echo "Total posts: " . $row['total'];
?>
```

---

## 📁 File Structure

```
.
├── config/
│   ├── db.php                    # Database connection
│   ├── ban-check.php             # Ban verification
│   └── db_feedspace-2 (1).sql    # Database schema
├── main/
│   ├── api/
│   │   ├── users/
│   │   │   ├── posts/
│   │   │   │   ├── create-post.php        # Create post with AI check
│   │   │   │   ├── get-posts.php          # Fetch posts
│   │   │   │   └── ...
│   │   │   └── interactions/
│   │   │       ├── add-comments.php       # Add comment with AI check
│   │   │       ├── get-comments.php       # Fetch comments
│   │   │       └── ...
│   │   └── hate-speech-detection/
│   │       ├── toxic_detector.py          # AI model
│   │       ├── labeled_data.csv           # Training data
│   │       └── ...
│   ├── html/main-feed.html      # Main feed page
│   ├── js/
│   │   ├── feed.js              # Post/comment logic
│   │   └── base.js              # Toast notifications
│   └── css/
│       ├── feed.css             # Feed styling
│       └── base.css             # Toast & comment styles
```

---

## 🐛 Troubleshooting

### Posts not saving
- Check `config/db.php` database credentials
- Verify `posts` table exists: `SHOW TABLES;`
- Check browser console for API errors
- Verify user is logged in (session_id set)

### Comments not appearing
- Ensure `comments` table exists
- Check moderation status is not "removed"
- Verify post_id is correct
- Check user session is active

### AI moderation not working
- Ensure Python 3 is installed: `python3 --version`
- Verify model files exist in `hate-speech-detection/`
- Run model training: `python3 toxic_detector.py`
- Check error logs in browser console

### Image upload failing
- Verify `uploads/posts/` directory exists and is writable
- Check file size < 5MB
- Verify file type is image/* (jpeg, png, gif)
- Check file permissions: `chmod 777 uploads/posts/`

---

## 📊 Monitoring Moderation

### View Moderation Logs
```sql
SELECT * FROM moderation_logs ORDER BY created_at DESC LIMIT 10;
```

### Check Flagged Content
```sql
-- Flagged posts
SELECT * FROM posts WHERE ai_status = 'review' OR ai_status = 'rejected';

-- Flagged comments
SELECT * FROM comments WHERE moderation_status = 'flagged' OR moderation_status = 'removed';
```

### User Ban Status
```sql
SELECT * FROM user_bans WHERE expires_at > NOW() OR expires_at IS NULL;
```

---

## 🔄 Next Steps

1. **Test all endpoints** - Create posts, comments, verify moderation works
2. **Train AI model** - Run `toxic_detector.py` with your labeled data
3. **Monitor moderation logs** - Track what's being flagged
4. **Fine-tune thresholds** - Adjust toxicity scores (0.5, 0.7) as needed
5. **Add admin panel** - Review and approve flagged content
6. **Implement reporting** - Use existing `post_reports` table

---

## 📞 Support

For issues with:
- **Database**: Check MySQL service is running
- **Python AI**: Verify dependencies installed
- **Frontend**: Check browser console for errors
- **API endpoints**: Test with curl/Postman

Example test command:
```bash
curl -X POST http://localhost/main/api/users/posts/create-post.php \
  -d "content=Test%20post" \
  -H "Cookie: PHPSESSID=your_session_id"
```

---

**Last Updated**: May 14, 2026
**Status**: ✅ Integration Complete - Ready for Testing
