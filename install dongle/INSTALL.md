# Dongle Manager – Installation (Fully Offline)

**No CDN. No internet on the server.** Download once, then copy the module.

---

## Step 1: Download vendor assets (run ONCE on a machine with internet)

From the project directory:

**Linux / macOS:**
```bash
cd donglemanager
chmod +x scripts/download-vendor.sh
./scripts/download-vendor.sh
```

**Windows (PowerShell):**
```powershell
cd donglemanager
.\scripts\download-vendor.ps1
```

This downloads Chart.js and Font Awesome into `donglemanager/assets/vendor/`.  
After this, the module is fully self-contained — **no internet needed on the server**.

---

## Step 2: Copy module to FreePBX server

Copy the entire `donglemanager` folder to the server (USB, SCP, etc.):

```bash
cp -r donglemanager /var/www/html/admin/modules/
chown -R asterisk:asterisk /var/www/html/admin/modules/donglemanager
```

---

## Step 3: Install via FreePBX

```bash
fwconsole ma install donglemanager
fwconsole reload
```

---

## Step 4: If tables are missing

If you see `Table 'asterisk.donglemanager_logs' doesn't exist`:

```bash
cd /var/www/html/admin && php modules/donglemanager/scripts/create-tables.php
fwconsole reload
```

---

## Step 5: (Optional) Sign the module

```bash
cd /usr/src && git clone https://github.com/FreePBX/devtools
KEY_ID=$(gpg --list-secret-keys --keyid-format SHORT 2>/dev/null | grep sec | head -1 | awk '{print $2}' | cut -d'/' -f2)
/usr/src/devtools/sign.php /var/www/html/admin/modules/donglemanager --local $KEY_ID
fwconsole reload
```

---

## Step 6: Background worker (cron)

```bash
(crontab -u asterisk -l 2>/dev/null; echo "* * * * * php /var/www/html/admin/modules/donglemanager/cron/worker.php >> /var/log/dongle-worker.log 2>&1") | crontab -u asterisk -
```

---

## Summary

1. **With internet:** `./scripts/download-vendor.sh` (or `.ps1` on Windows)
2. **Copy** `donglemanager` to the server
3. **On server (no internet):** `fwconsole ma install donglemanager && fwconsole reload`
