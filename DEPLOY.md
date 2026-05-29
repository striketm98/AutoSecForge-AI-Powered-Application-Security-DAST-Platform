# 🚀 AutoSecForge Pro — Railway Deployment Guide

## One-Click Deploy Steps

### Step 1: Push to GitHub
```bash
cd autosecforge-railway
git init
git add .
git commit -m "Railway deployment"
git remote add origin https://github.com/YOUR_USERNAME/autosecforge-railway.git
git push -u origin main
```

### Step 2: Create Railway Project
1. Go to **https://railway.app** and sign up (free)
2. Click **"New Project"** → **"Deploy from GitHub repo"**
3. Select your repository

### Step 3: Add MySQL Database
1. In your Railway project, click **"New Service"** → **"Database"** → **"MySQL"**
2. Railway auto-injects these variables into your app:
   - `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`
3. The `start.sh` script reads these automatically — **no manual config needed**

### Step 4: Set Environment Variables
In Railway Dashboard → your app service → **Variables**, add:
```
APP_NAME=AutoSecForge Pro
```

### Step 5: Deploy
Railway auto-deploys on every git push. Watch the build logs.

---

## Default Login
After first deploy, the schema seeds a demo user:
- **Email:** `admin@autosecforge.local`  
- **Password:** `admin123`

> ⚠️ Change this immediately after first login!

---

## Free Tier Limits
| Resource | Free Allowance |
|----------|---------------|
| Compute  | $5 credit/month (~500 hours) |
| MySQL    | 100MB storage |
| Bandwidth| 100GB/month |

---

## Notes on External Security Tools
The live scanning tools (OWASP ZAP, SonarQube, MobSF, SQLMap) are **not deployed** on Railway — they require too much memory and Railway's ToS prohibits active scanning from their cloud.

The dashboard will work fully for:
✅ Project management  
✅ Finding tracking & triage  
✅ AI-powered summaries  
✅ Report generation (PDF/HTML/Excel)  
✅ Attack surface management  
✅ Import scan results from any tool  

To connect live tools: run them locally with Docker and set the endpoint URLs in Settings.
