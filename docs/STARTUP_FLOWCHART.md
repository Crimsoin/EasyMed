# 🗺️ EasyMed Startup Flowchart

```
┌─────────────────────────────────────────────────────────────────┐
│                    🚀 START HERE                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │  XAMPP Installed? │
                    └──────────────────┘
                         │         │
                    Yes  │         │ No
                         │         └──────────────────────────┐
                         ▼                                    ▼
              ┌──────────────────┐              ┌────────────────────┐
              │  Apache Running?  │              │ Download & Install │
              └──────────────────┘              │  XAMPP from:       │
                   │         │                  │ apachefriends.org  │
              Yes  │         │ No               └────────────────────┘
                   │         └────────────┐                  │
                   ▼                      ▼                  ▼
         ┌──────────────────┐    ┌──────────────┐    ┌──────────┐
         │ Open Browser     │    │ Start Apache │    │ Restart  │
         │ localhost/       │    │ from XAMPP   │    │ Process  │
         │ Project_EasyMed  │    │ Control Panel│    └──────────┘
         └──────────────────┘    └──────────────┘
                   │                      │
                   │                      ▼
                   │              ┌──────────────┐
                   │              │ Wait for     │
                   │              │ Apache to    │
                   │              │ turn GREEN   │
                   │              └──────────────┘
                   │                      │
                   └──────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │  Page Loads OK?   │
                    └──────────────────┘
                         │         │
                    Yes  │         │ No
                         │         └─────────────────────────┐
                         ▼                                   ▼
              ┌──────────────────┐           ┌──────────────────────┐
              │ See Login/       │           │ TROUBLESHOOTING:     │
              │ Register Buttons?│           │                      │
              └──────────────────┘           │ 1. Check Apache log  │
                         │                   │ 2. Enable PHP errors │
                    Yes  │                   │ 3. Verify project    │
                         ▼                   │    location          │
              ┌──────────────────┐           │ 4. Check file        │
              │ Click Login      │           │    permissions       │
              └──────────────────┘           └──────────────────────┘
                         │
                         ▼
              ┌──────────────────┐
              │ Login Modal      │
              │ Appears?         │
              └──────────────────┘
                         │
                    Yes  │
                         ▼
              ┌──────────────────┐
              │ Enter Credentials:│
              │ admin@easymed.com │
              │ admin123         │
              └──────────────────┘
                         │
                         ▼
              ┌──────────────────┐
              │ Login Successful? │
              └──────────────────┘
                   │         │
              Yes  │         │ No
                   │         └────────────────────────┐
                   ▼                                  ▼
         ┌──────────────────┐         ┌──────────────────────┐
         │ Redirected to    │         │ User doesn't exist?  │
         │ Dashboard?       │         │                      │
         └──────────────────┘         │ 1. Check database    │
                   │                  │ 2. Create admin user │
              Yes  │                  │ 3. Try again         │
                   ▼                  └──────────────────────┘
         ┌──────────────────┐
         │ See Statistics & │
         │ Navigation Menu? │
         └──────────────────┘
                   │
              Yes  │
                   ▼
         ┌──────────────────────────────┐
         │    ✅ SUCCESS!                │
         │                               │
         │ Your EasyMed system is ready!│
         │                               │
         │ You can now:                  │
         │ • Add doctors                 │
         │ • Add patients                │
         │ • Book appointments           │
         │ • Manage clinic               │
         └──────────────────────────────┘
```

---

## 🔍 Common Decision Points

### At "Apache Running?" 

**How to check:**
1. Open XAMPP Control Panel
2. Look at Apache row
3. If button is RED → Click "Start"
4. If button is GREEN → Apache is running ✅

**If Apache won't start:**
- Check if port 80 is busy
- Try stopping Skype or other apps
- Or change Apache to port 8080

---

### At "Page Loads OK?"

**What you should see:**
- ✅ EasyMed homepage with hero section
- ✅ Navigation menu (Home, About, Doctors, etc.)
- ✅ Login and Register buttons
- ✅ Footer with clinic info

**If you see errors:**
- Check Apache error log
- Verify PHP is working
- Check file permissions
- See full troubleshooting guide

---

### At "Login Successful?"

**Expected behavior:**
- ✅ Modal closes automatically
- ✅ Page redirects based on role:
  - Admin → `/admin/Dashboard/dashboard.php`
  - Doctor → `/doctor/dashboard_doctor.php`
  - Patient → `/patient/dashboard_patients.php`
- ✅ See dashboard with statistics

**If login fails:**
- Verify credentials are correct
- Check if user exists in database
- Check browser console for errors
- Verify session is working

---

## 🎯 Success Indicators

At each stage, you should see:

1. **XAMPP Stage:** Green button next to Apache
2. **Browser Stage:** Homepage loads with images
3. **Login Stage:** Modal appears with form
4. **Dashboard Stage:** Statistics and navigation visible

---

## 🆘 Quick Fixes

| Problem | Quick Fix |
|---------|-----------|
| XAMPP not found | Install from apachefriends.org |
| Apache won't start | Stop Skype, or use port 8080 |
| Page not found | Check URL: `localhost/Project_EasyMed` |
| Blank page | Enable PHP errors in config.php |
| Can't login | Create admin user with script |
| Database error | Check file permissions |

---

## 📞 Get Help

1. **Quick Start:** `QUICK_START.md`
2. **Full Guide:** `docs/HOW_TO_RUN.md`
3. **README:** `README.md`
4. **Database:** `docs/EasyMed_ERD.html`

---

**Follow this flowchart step by step for a smooth setup! 🚀**