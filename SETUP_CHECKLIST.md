# FeedSpace Database Integration - Quick Setup Checklist

## ✅ COMPLETED - What I've Done For You

### Backend Integration
- ✅ Fixed `create-post.php` with AI moderation check
- ✅ Rewrote `add-comments.php` with proper DB integration
- ✅ Created `get-comments.php` to fetch comments
- ✅ Created `ban-check.php` helper function
- ✅ All endpoints use centralized `config/db.php` database connection

### Frontend Integration  
- ✅ Updated `feed.js` - comments now send to backend properly
- ✅ Updated `base.js` - toast notifications support different types
- ✅ Added CSS styles for warning/error toasts
- ✅ Added moderation badge styling for flagged comments
- ✅ Posts and comments show AI moderation warnings

### AI Moderation
- ✅ Both posts and comments go through Python toxic detection
- ✅ Moderation status stored in database
- ✅ Frontend shows warnings for flagged/rejected content

---

## 🚀 YOUR NEXT STEPS (4 Things To Do)

### 1. **Verify Database is Running**
```bash
# Open command prompt and run:
mysql -u root
```
If this connects, your database is ready. Type `exit;` to quit.

### 2. **Import/Verify Database Schema**
```bash
# Make sure db_feedspace exists with all tables
mysql -u root db_feedspace
# Inside MySQL:
SHOW TABLES;
# Should show: posts, comments, users, post_likes, user_bans, etc.
exit;
```

### 3. **Train AI Moderation Model (IMPORTANT!)**
```bash
# Navigate to project directory and run:
cd d:\xampp\htdocs\login-main-feedspace\main\api\hate-speech-detection
python3 toxic_detector.py
```
This trains the model on your labeled_data.csv file. The model files (model.pkl, vectorizer.pkl) will be created.

### 4. **Test the System**

**Test Posting:**
1. Open your FeedSpace application in browser
2. Log in as a user
3. Click "+ Create Post" button
4. Write a simple test message (e.g., "Hello FeedSpace!")
5. Click "Share"
6. ✅ Post should appear in feed

**Test Comments:**
1. Find the post you just created
2. Click on the post to expand comments section
3. Write a comment (e.g., "Great post!")
4. Click send button (the circle button with +)
5. ✅ Comment should appear immediately below post

**Test AI Moderation:**
1. Try creating a post with potentially offensive content
2. Check the toast notification - should show warning if flagged
3. Comments with flagged content should show ⚠️ badge

---

## 📋 What Each Component Does

### Posts
- **Created by**: `main/api/users/posts/create-post.php`
- **Fetched by**: `main/api/users/posts/get-posts.php`
- **AI Check**: Toxicity score determines ai_status
- **Stored**: posts table with moderation fields

### Comments  
- **Created by**: `main/api/users/interactions/add-comments.php`
- **Fetched by**: `main/api/users/interactions/get-comments.php`
- **AI Check**: Content checked before saving
- **Stored**: comments table with moderation_status

### AI Moderation
- **Model**: `main/api/hate-speech-detection/toxic_detector.py`
- **Scores**: 0-1 (0=safe, 1=toxic)
- **Thresholds**:
  - < 0.5 = Safe ✅
  - 0.5-0.7 = Review ⚠️
  - > 0.7 = Rejected ❌

---

## 🔗 Database Connection

Your system uses this configuration (in `config/db.php`):
```
Host: localhost
User: root
Password: (empty)
Database: db_feedspace
```

All PHP files now use this centralized config instead of hardcoded credentials.

---

## 📊 Monitoring

### Check if Posts are Saved
```sql
SELECT * FROM posts ORDER BY created_at DESC LIMIT 5;
```

### Check if Comments are Saved
```sql
SELECT * FROM comments ORDER BY created_at DESC LIMIT 5;
```

### View Moderation Status
```sql
SELECT post_id, ai_status, ai_score FROM posts WHERE ai_status != 'safe';
SELECT comment_id, moderation_status, toxicity_score FROM comments WHERE moderation_status != 'approved';
```

---

## ⚠️ Common Issues & Fixes

| Issue | Solution |
|-------|----------|
| Posts not saving | Verify MySQL running: `mysql -u root` |
| Comments not appear | Check moderation status isn't "removed" in database |
| AI model not found | Run `python3 toxic_detector.py` first |
| Session errors | Make sure you're logged in before posting |
| Image upload fails | Verify `uploads/posts/` folder exists |

---

## 📁 Key Files Modified

```
✅ main/api/users/posts/create-post.php          - With AI moderation
✅ main/api/users/interactions/add-comments.php  - Rewritten for DB integration
✅ main/api/users/interactions/get-comments.php  - Created new
✅ main/js/feed.js                               - Comments send to backend
✅ main/js/base.js                               - Enhanced toast notifications
✅ main/css/base.css                             - Added toast & badge styles
✅ config/ban-check.php                          - Created helper function
✅ INTEGRATION_GUIDE.md                          - Comprehensive documentation
```

---

## 🎯 Success Indicators

After setup, you should see:
- ✅ Posts appear in feed immediately after creation
- ✅ Comments appear in post after submission
- ✅ Toast notifications for moderation warnings
- ✅ Flagged comments show ⚠️ badge
- ✅ Database stores all content with moderation scores
- ✅ Users see warnings if content is flagged/rejected

---

## 💡 Tips

1. **Test with safe content first** - Then try edge cases
2. **Watch browser console** - Press F12 to see API responses
3. **Check database directly** - Use MySQL to verify data is saving
4. **Monitor moderation** - Review flagged content regularly
5. **Train model regularly** - Retrain with new labeled data as needed

---

**Status**: ✅ Ready to Test!

Once you complete the 4 steps above, your FeedSpace database and AI moderation system will be fully operational.
