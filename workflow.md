# Lager032 — WordPress Direct SSH Development Workflow

A simple, reliable workflow for deploying the **Lager032** WordPress/WooCommerce site to its cPanel server over SSH, without GitHub.

> **This site is remote-only.** WordPress and the database live exclusively on the server. The local workspace (`Local Sites/lager`) holds only the custom theme source, which is pushed up with `rsync`. There is no local DB to export, so the database flow below is **remote → local** (backups), not local → remote.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [SSH Setup](#2-ssh-setup)
3. [Connection Methods](#3-connection-methods)
4. [Server Information](#4-server-information)
5. [File Deployment](#5-file-deployment)
6. [Database Backups](#6-database-backups)
7. [Complete Deployment Script](#7-complete-deployment-script)
8. [Common Commands](#8-common-commands)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Prerequisites

### Local Requirements

- A local working copy of the custom theme (`Local Sites/lager`)
- Git Bash or WSL (Windows) / Terminal (Mac/Linux)
- SSH access to the cPanel server
- Private SSH key (`~/.ssh/devkey`)

### Server Requirements

- cPanel with SSH access enabled
- WordPress + WooCommerce installed at `public_html/lager032.pixels2pixels.ch`
- WP-CLI installed (check with `wp --version`)

---

## 2. SSH Setup

### Step 1: Generate SSH Key in cPanel

1. Log into cPanel
2. Go to **SSH Access** → **Manage SSH Keys**
3. Click **Generate a New Key**
4. Settings:
   - Key Name: `devkey` (or your preferred name)
   - Password: Leave empty for passwordless (recommended) or set a password
   - Key Type: RSA
   - Key Size: 2048 or 4096
5. Click **Generate Key**
6. **IMPORTANT**: Click **Manage** → **Authorize** to activate the key

### Step 2: Download Private Key

1. In **SSH Access** → **Manage SSH Keys**
2. Find your key under "Private Keys"
3. Click **View/Download**
4. Download the private key
5. Save to `~/.ssh/` folder:
   - Windows: `C:\Users\YourName\.ssh\devkey`
   - Mac/Linux: `~/.ssh/devkey`

### Step 3: Set Key Permissions

```bash
# Set proper permissions (required for SSH to work)
chmod 600 ~/.ssh/devkey

# If key has password, remove it (optional, for automation):
ssh-keygen -p -P "YOUR_PASSWORD" -N "" -f ~/.ssh/devkey
```

---

## 3. Connection Methods

### Method A: Direct SSH Command

```bash
ssh -i ~/.ssh/devkey -p PORT USERNAME@HOST

# Example:
ssh -i ~/.ssh/devkey -p 22222 pixelspi@162.55.0.170
```

### Method B: SSH Config File (Recommended)

Create or edit `~/.ssh/config`:

```
# Lager032 Server
Host lager032
    HostName 162.55.0.170
    Port 22222
    User pixelspi
    IdentityFile ~/.ssh/devkey
```

Now you can connect simply with:

```bash
ssh lager032
```

### Method C: Using Key with Passphrase

```bash
# Add key to SSH agent (prompts for passphrase once)
ssh-add ~/.ssh/devkey

# Then connect without specifying key
ssh -p 22222 pixelspi@162.55.0.170
```

---

## 4. Server Information

```markdown
## Server Details

- **Server IP/Host:** 162.55.0.170
- **SSH Port:** 22222
- **Username:** pixelspi
- **SSH Key:** ~/.ssh/devkey

## WordPress Installation

- **Site URL:** https://lager032.pixels2pixels.ch
- **Hosting:** Remote-only (no local WordPress install)
- **Local Theme Source:** /c/Users/Harmonity/Local Sites/lager
- **Remote Path:** /home/pixelspi/public_html/lager032.pixels2pixels.ch/

## SSH Config Alias

Host lager032
HostName 162.55.0.170
Port 22222
User pixelspi
IdentityFile ~/.ssh/devkey
```

---

## 5. File Deployment

### Sync Theme Files

```bash
# Using SSH config alias
rsync -avz --delete \
  "/c/Users/Harmonity/Local Sites/lager/wp-content/themes/lager032/" \
  lager032:/home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/themes/lager032/

# Using full SSH command
rsync -avz --delete \
  -e "ssh -i ~/.ssh/devkey -p 22222" \
  "/c/Users/Harmonity/Local Sites/lager/wp-content/themes/lager032/" \
  pixelspi@162.55.0.170:/home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/themes/lager032/
```

### Install Plugins (directly on server)

```bash
# Install + activate a plugin on the remote
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp plugin install woocommerce --activate"
```

### Sync Custom Plugins (if developed locally)

```bash
rsync -avz --delete \
  "/c/Users/Harmonity/Local Sites/lager/wp-content/plugins/custom-plugin/" \
  lager032:/home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/plugins/custom-plugin/
```

---

## 6. Database Backups

Because the database lives only on the server, the primary DB operation is pulling
backups down to local. Run a search-replace **on the server** only when the domain
itself changes.

### Remote → Local (Backup / Pull)

```bash
# 1. Export remote database
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp db export remote-backup.sql"

# 2. Download SQL file
scp -i ~/.ssh/devkey -P 22222 \
  pixelspi@162.55.0.170:/home/pixelspi/public_html/lager032.pixels2pixels.ch/remote-backup.sql \
  ./

# 3. Cleanup remote copy
ssh lager032 "rm /home/pixelspi/public_html/lager032.pixels2pixels.ch/remote-backup.sql"
```

### Domain Change (search-replace on server)

```bash
# Dry run first
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp search-replace 'https://old-domain.tld' 'https://lager032.pixels2pixels.ch' --all-tables --dry-run"

# Apply, then flush
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp search-replace 'https://old-domain.tld' 'https://lager032.pixels2pixels.ch' --all-tables --precise"
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp rewrite flush && wp cache flush"
```

---

## 7. Complete Deployment Script

Create `deploy.sh` in your project root:

```bash
#!/bin/bash

# ============================================
# Lager032 Deployment Script
# Pushes theme/uploads to remote; pulls DB backups
# ============================================

# Configuration
LOCAL_PATH="/c/Users/Harmonity/Local Sites/lager"
REMOTE_HOST="162.55.0.170"
REMOTE_PORT="22222"
REMOTE_USER="pixelspi"
REMOTE_PATH="/home/pixelspi/public_html/lager032.pixels2pixels.ch"
SSH_KEY="~/.ssh/devkey"
THEME="lager032"
REMOTE_URL="https://lager032.pixels2pixels.ch"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Lager032 Deployment ===${NC}"

# SSH command helper
SSH_CMD="ssh -i $SSH_KEY -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST"

# Function: Deploy Theme Files
deploy_files() {
    echo -e "${BLUE}[1/3] Deploying theme files...${NC}"
    rsync -avz --delete \
        -e "ssh -i $SSH_KEY -p $REMOTE_PORT" \
        "$LOCAL_PATH/wp-content/themes/$THEME/" \
        "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/wp-content/themes/$THEME/"
    echo -e "${GREEN}✓ Theme deployed${NC}"
}

# Function: Sync Uploads (push)
sync_uploads() {
    echo -e "${BLUE}[2/3] Syncing uploads...${NC}"
    rsync -avz --progress \
        -e "ssh -i $SSH_KEY -p $REMOTE_PORT" \
        "$LOCAL_PATH/wp-content/uploads/" \
        "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/wp-content/uploads/"
    echo -e "${GREEN}✓ Uploads synced${NC}"
}

# Function: Backup remote DB to local
backup_db() {
    echo -e "${BLUE}[backup] Pulling remote database...${NC}"
    $SSH_CMD "cd $REMOTE_PATH && wp db export remote-backup.sql"
    scp -i $SSH_KEY -P $REMOTE_PORT \
        "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/remote-backup.sql" \
        "$LOCAL_PATH/"
    $SSH_CMD "rm $REMOTE_PATH/remote-backup.sql"
    echo -e "${GREEN}✓ Database backed up to $LOCAL_PATH/remote-backup.sql${NC}"
}

# Function: Post-deploy commands
post_deploy() {
    echo -e "${BLUE}[3/3] Running post-deploy commands...${NC}"
    $SSH_CMD "cd $REMOTE_PATH && wp rewrite flush && wp cache flush"
    echo -e "${GREEN}✓ Post-deploy complete${NC}"
}

# Menu
case "${1:-quick}" in
    files)
        deploy_files
        ;;
    uploads)
        sync_uploads
        ;;
    backup)
        backup_db
        ;;
    all)
        deploy_files
        sync_uploads
        post_deploy
        ;;
    quick)
        deploy_files
        post_deploy
        ;;
    *)
        echo "Usage: $0 {files|uploads|backup|all|quick}"
        echo "  files   - Deploy theme files only"
        echo "  uploads - Push uploads folder"
        echo "  backup  - Pull remote database to local"
        echo "  all     - Files + uploads + post-deploy"
        echo "  quick   - Files + post-deploy (default)"
        exit 1
        ;;
esac

echo -e "${GREEN}=== Deployment Complete ===${NC}"
echo "Visit: $REMOTE_URL"
```

### Usage

```bash
# Make executable
chmod +x deploy.sh

# Quick deployment (theme + flush, default)
./deploy.sh quick

# Deploy theme files only
./deploy.sh files

# Push uploads
./deploy.sh uploads

# Pull remote database backup
./deploy.sh backup

# Files + uploads + post-deploy
./deploy.sh all
```

---

## 8. Common Commands

### File Operations

```bash
# List remote theme files
ssh lager032 "ls -la /home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/themes/"

# Remove remote file/folder
ssh lager032 "rm -rf /home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/themes/old-theme"

# Check disk usage
ssh lager032 "du -sh /home/pixelspi/public_html/lager032.pixels2pixels.ch/"
```

### WP-CLI on Remote

```bash
# Shortcut: set the path once
SITE="/home/pixelspi/public_html/lager032.pixels2pixels.ch"

# Check WP-CLI version
ssh lager032 "cd $SITE && wp --version"

# List plugins
ssh lager032 "cd $SITE && wp plugin list"

# Activate theme
ssh lager032 "cd $SITE && wp theme activate lager032"

# WooCommerce status
ssh lager032 "cd $SITE && wp wc --version"

# Create admin user
ssh lager032 "cd $SITE && wp user create admin admin@email.com --role=administrator --user_pass=password"

# Export database
ssh lager032 "cd $SITE && wp db export backup.sql"

# Run SQL query
ssh lager032 "cd $SITE && wp db query \"SELECT * FROM wp_posts LIMIT 5\""

# Flush cache and rewrites
ssh lager032 "cd $SITE && wp cache flush && wp rewrite flush"
```

### Debugging

```bash
# Check PHP version
ssh lager032 "php -v"

# Check error logs
ssh lager032 "tail -50 /home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/debug.log"

# Enable WP_DEBUG
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp config set WP_DEBUG true --raw"

# Check file permissions
ssh lager032 "ls -la /home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/"
```

---

## 9. Troubleshooting

### SSH Connection Refused

```bash
# Try different ports
ssh -i ~/.ssh/devkey -p 22 pixelspi@162.55.0.170
ssh -i ~/.ssh/devkey -p 2222 pixelspi@162.55.0.170
ssh -i ~/.ssh/devkey -p 22222 pixelspi@162.55.0.170
```

### Permission Denied (Public Key)

1. **Key not authorized in cPanel:**
   - Go to cPanel → SSH Access → Manage SSH Keys
   - Find your key → Click Manage → Click Authorize

2. **Wrong key file:**

   ```bash
   # Verify key exists
   ls -la ~/.ssh/devkey

   # Check key format
   head -1 ~/.ssh/devkey
   # Should show: -----BEGIN OPENSSH PRIVATE KEY-----
   ```

3. **Wrong permissions:**
   ```bash
   chmod 600 ~/.ssh/devkey
   ```

### rsync Not Working

```bash
# Test basic SSH first
ssh -i ~/.ssh/devkey -p 22222 pixelspi@162.55.0.170 "echo test"

# Try rsync with verbose
rsync -avz --progress -e "ssh -i ~/.ssh/devkey -p 22222" \
  source/ pixelspi@162.55.0.170:/dest/
```

### Database Import Fails

```bash
# Check database connection
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp db check"

# Import with force
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp db import backup.sql --force"

# Check max packet size
ssh lager032 "mysql -u user -p -e 'SHOW VARIABLES LIKE \"max_allowed_packet\";'"
```

### White Screen After Deploy

```bash
# Enable debug mode
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp config set WP_DEBUG true --raw && wp config set WP_DEBUG_LOG true --raw && wp config set WP_DEBUG_DISPLAY false --raw"

# Check error log
ssh lager032 "tail -100 /home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/debug.log"

# Check PHP errors
ssh lager032 "tail -100 /home/pixelspi/public_html/error_log"
```

### Search Replace Not Working

```bash
# Dry run first (see what will change)
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp search-replace 'old.com' 'new.com' --all-tables --dry-run"

# With specific tables
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp search-replace 'old.com' 'new.com' wp_posts wp_postmeta wp_options"

# Serialized data safe replace
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp search-replace 'old.com' 'new.com' --all-tables --precise"
```

---

## Quick Reference Card

```bash
# Connect to server
ssh lager032
# or: ssh -i ~/.ssh/devkey -p 22222 pixelspi@162.55.0.170

# Deploy theme
rsync -avz -e "ssh -i ~/.ssh/devkey -p 22222" \
  "/c/Users/Harmonity/Local Sites/lager/wp-content/themes/lager032/" \
  pixelspi@162.55.0.170:/home/pixelspi/public_html/lager032.pixels2pixels.ch/wp-content/themes/lager032/

# Backup remote DB
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp db export backup.sql"
scp -i ~/.ssh/devkey -P 22222 pixelspi@162.55.0.170:/home/pixelspi/public_html/lager032.pixels2pixels.ch/backup.sql ./

# Search replace (on server)
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp search-replace 'old' 'new' --all-tables --precise"

# Flush everything
ssh lager032 "cd /home/pixelspi/public_html/lager032.pixels2pixels.ch && wp rewrite flush && wp cache flush"
```

---

_Project: Lager032 · Last updated: June 2026_
