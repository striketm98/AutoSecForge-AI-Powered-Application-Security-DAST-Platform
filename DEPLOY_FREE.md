# 🚀 AutoSecForge Free Deployment Guide

Deploy **AutoSecForge Pro** for **FREE** on cloud platforms. Choose your preferred option below.

---

## 📋 Quick Comparison

| Platform | Cost | Compute | Database | Uptime | Setup Time |
|----------|------|---------|----------|--------|-----------|
| **Oracle Cloud Always Free** ⭐ | FREE | 2x ARM VMs | MySQL/PostgreSQL | 99.9% | 15 min |
| **Render** | FREE (with limits) | 0.5 CPU | PostgreSQL | Spins down | 10 min |
| **Railway** | $5/month credit | Shared | PostgreSQL | Continuous | 10 min |
| **Heroku** | FREE (needs card) | Limited | ClearDB | 550 hrs/mo | 8 min |
| **Replit** | FREE | Shared | SQLite/PostgreSQL | Spins down | 5 min |

---

## 🏆 Option 1: Oracle Cloud Always Free (Recommended)

### Why Oracle Cloud?
✅ **Truly free forever** (no auto-upgrade)  
✅ **Always-on** (no spin-down)  
✅ **100GB storage**  
✅ **2x ARM Compute instances (always free)**  
✅ **Supports MySQL 8.4**  

### Step 1: Create Oracle Cloud Account
1. Go to https://www.oracle.com/cloud/free/
2. Click "Start for free"
3. Create account (requires credit card for verification, but no charges for free tier)

### Step 2: Create Compute Instance
1. **Compute** → **Instances** → **Create Instance**
2. **Image & Shape:**
   - Image: Ubuntu 22.04 (always free)
   - Shape: ARM.A1.Micro (4 OCPU, 24GB RAM) ✓ Always Free
3. **Networking:**
   - VCN: Default
   - Subnet: Public
4. **SSH Key:** Download and save
5. Click **Create**

### Step 3: Configure Security List
1. Go to **Virtual Cloud Networks** → **Subnets**
2. **Ingress Rules** → **Add:**
   - Port 80 (HTTP)
   - Port 443 (HTTPS)
   - Port 22 (SSH)

### Step 4: Connect via SSH
```bash
ssh -i /path/to/key.key ubuntu@<your-public-ip>
```

### Step 5: Install Docker
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
newgrp docker

# Install Docker Compose
sudo apt install -y docker-compose
```

### Step 6: Deploy AutoSecForge
```bash
# Clone repo
git clone https://github.com/striketm98/AutoSecForge-AI-Powered-Application-Security-DAST-Platform.git
cd AutoSecForge-AI-Powered-Application-Security-DAST-Platform

# Create .env file
cp .env.example .env

# Edit .env with strong passwords
nano .env

# Example (use `openssl rand -base64 32` for passwords):
# DB_ROOT_PASSWORD=<random-password-1>
# DB_PASSWORD=<random-password-2>
# ZAP_API_KEY=<random-key>
# APP_SECRET=<random-secret>

# Start services
docker compose -f docker/docker-compose.yml up -d --build

# Wait for startup (2-3 minutes)
docker compose logs -f app
```

### Step 7: Access Dashboard
Open your browser:
```
http://<your-oracle-public-ip>:8080
```

Default login:
- Email: `admin@cyber-security.local`
- Password: Check your setup.php output or reset via container

---

## 🚀 Option 2: Render.com (Zero-Click Deploy)

### Step 1: Connect to GitHub
1. Go to https://render.com
2. Sign up with GitHub
3. Grant repo access

### Step 2: Create New Web Service
1. **New** → **Web Service**
2. **Connect repository** → Select your fork
3. **Settings:**
   - **Name:** autosecforge
   - **Environment:** Docker
   - **Plan:** Free (0.5 CPU)
4. **Environment Variables:**
   ```
   DB_PASSWORD=<random-password>
   DB_ROOT_PASSWORD=<random-password>
   ZAP_API_KEY=<random-key>
   APP_SECRET=<random-secret>
   ```
5. Click **Deploy**

### Access Your App
```
https://autosecforge.onrender.com
```

**Note:** App will spin down after 15 min of inactivity (free tier).

---

## 🚃 Option 3: Railway.app ($5/month credit)

### Step 1: Connect GitHub
1. Go to https://railway.app
2. Sign in with GitHub
3. Create new project

### Step 2: Deploy
1. **New** → **GitHub repo** → Select fork
2. **Add environment variables:**
   ```
   DB_PASSWORD=<strong-password>
   ZAP_API_KEY=<random-key>
   ```
3. **Deploy**

### Step 3: Get Public URL
Railway auto-generates a public URL (visible in dashboard)

**Cost:** Free $5/month credit covers most small projects.

---

## ☁️ Option 4: Heroku (Legacy but works)

### Step 1: Install Heroku CLI
```bash
curl https://cli-assets.heroku.com/install.sh | sh
heroku login
```

### Step 2: Create App
```bash
heroku create autosecforge-<your-name>
```

### Step 3: Add ClearDB MySQL
```bash
heroku addons:create cleardb:ignite
```

### Step 4: Deploy
```bash
git push heroku main
```

### Step 5: View Logs
```bash
heroku logs -t
heroku open
```

---

## 🏃 Option 5: Replit (Quick Test)

### Step 1: Fork to Replit
1. Go to https://replit.com
2. Sign in with GitHub
3. **New Replit** → **Import from GitHub** → Select your fork

### Step 2: Set Environment
Create `.env` in Replit:
```
DB_PASSWORD=password123
ZAP_API_KEY=key123
```

### Step 3: Run
```bash
docker compose up -d
```

Access at: `https://<your-replit-url>:8080`

---

## 🔐 Security Checklist for Production

- [ ] Change all default passwords immediately
- [ ] Use strong passwords (32+ chars): `openssl rand -base64 32`
- [ ] Enable HTTPS with Let's Encrypt (Nginx reverse proxy)
- [ ] Restrict database access to app container only
- [ ] Set up firewall rules (allow 80, 443 only)
- [ ] Enable database backups
- [ ] Monitor logs for unauthorized access attempts
- [ ] Rotate API keys monthly

---

## 📊 Monitoring & Logs

### View Application Logs
```bash
docker compose logs -f app
```

### View Database Logs
```bash
docker compose logs -f db
```

### View All Services
```bash
docker compose ps
```

### Restart a Service
```bash
docker compose restart app
```

---

## 💾 Backup & Restore

### Backup Database
```bash
docker compose exec db mysqldump -u dashboard -p security_dashboard > backup.sql
```

### Restore Database
```bash
docker compose exec -T db mysql -u dashboard -p security_dashboard < backup.sql
```

### Backup Volumes
```bash
docker compose exec db tar -czf - /var/lib/mysql | gzip > db_backup.tar.gz
```

---

## 🎯 Performance Tips

### For Oracle Cloud (Always Free)
- Allocate 2GB swap space: `sudo fallocate -l 2G /swapfile`
- Enable swap: `sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile`
- Limit container memory: Edit `docker-compose.yml` with `mem_limit: 512m`

### For Render/Railway (Limited Resources)
- Disable resource-intensive services (disable MobSF, Semgrep)
- Use PostgreSQL instead of MySQL (lighter)
- Scale down SonarQube heap size

---

## 🐛 Troubleshooting

### Port Already in Use
```bash
# Find process using port 8080
sudo lsof -i :8080

# Kill process
sudo kill -9 <PID>
```

### Database Connection Failed
```bash
# Test connection from app container
docker compose exec app mysql -h db -u dashboard -p security_dashboard

# Check if db is healthy
docker compose ps db
```

### Out of Memory
```bash
# Free cache
sync && echo 3 | sudo tee /proc/sys/vm/drop_caches

# Check memory usage
docker stats
```

### Docker Compose Won't Start
```bash
# Clean up
docker compose down -v
docker system prune -a

# Rebuild
docker compose up -d --build
```

---

## 📞 Support & Next Steps

1. **Fork this repo** to enable automatic deployments
2. **Star the project** ⭐ to show support
3. **Report issues** via GitHub Issues
4. **Join community** for tips and updates

---

## 📝 Pricing Summary (Annual)

| Provider | Annual Cost | Best For |
|----------|-------------|----------|
| Oracle Cloud | **$0** | Production workloads |
| Render | **$0** (but limited) | Development/testing |
| Railway | **$60** | Small projects |
| Heroku | **Deprecated** | Legacy apps |
| Replit | **$0** | Prototyping |

---

**Happy Deploying! 🎉**

*AutoSecForge Pro – Unified Security Dashboard*
