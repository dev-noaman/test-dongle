# Quickstart: FreePBX Dongle Manager Module

**Branch**: `001-freepbx-dongle-module` | **Date**: 2026-03-17

## Prerequisites

- FreePBX 16+ installed and working
- chan_dongle compiled and installed (`/etc/asterisk/dongle.conf` configured)
- At least one Huawei USB GSM modem connected
- PHP 7.4+ with extensions: pdo_mysql, sockets, json, mbstring
- MariaDB running on localhost

## Module Structure

```
donglemanager/                        # → /var/www/html/admin/modules/donglemanager/
├── module.xml                        # Module manifest
├── Donglemanager.class.php           # Main BMO class
├── install.php                       # DB table creation on install
├── uninstall.php                     # DB table cleanup on uninstall
├── page.donglemanager.php            # FreePBX page entry point
├── Backup.php                        # Backup handler
├── Restore.php                       # Restore handler
├── functions.inc.php                 # Legacy functions file (required by framework)
├── views/
│   ├── main.php                      # Page router (dashboard, sms, ussd, etc.)
│   ├── dashboard.php                 # Dashboard overview
│   ├── sms_inbox.php                 # SMS inbox list
│   ├── sms_outbox.php                # SMS outbox list
│   ├── sms_send.php                  # Send SMS form
│   ├── ussd.php                      # USSD send + history
│   ├── dongles.php                   # Dongle manager cards
│   ├── reports.php                   # Reports + charts
│   └── logs.php                      # System logs viewer
├── assets/
│   ├── css/
│   │   └── donglemanager.css         # Custom styles (signals, badges, cards)
│   ├── js/
│   │   └── donglemanager.js          # AJAX logic, auto-refresh, SMS counter
│   └── vendor/
│       ├── chart.umd.min.js          # Chart.js 4
│       └── fontawesome/              # Font Awesome 6
│           ├── css/all.min.css
│           └── webfonts/
├── includes/
│   ├── AmiClient.php                 # Direct AMI socket client (for worker)
│   └── ConfigReader.php              # FreePBX config file reader
└── cron/
    └── worker.php                    # Background worker (cron every minute)
```

## Installation (Development)

```bash
# 1. Copy module to FreePBX modules directory
cp -r donglemanager /var/www/html/admin/modules/

# 2. Set ownership
chown -R asterisk:asterisk /var/www/html/admin/modules/donglemanager

# 3. Install via fwconsole
fwconsole ma install donglemanager
fwconsole reload

# 4. Set up cron job for background worker
echo "* * * * * php /var/www/html/admin/modules/donglemanager/cron/worker.php >> /var/log/dongle-worker.log 2>&1" | crontab -u asterisk -
```

## Key Patterns

### BMO Class (Donglemanager.class.php)

```php
namespace FreePBX\modules;

class Donglemanager extends \FreePBX_Helpers implements \BMO {
    private $db;

    public function __construct($freepbx = null) {
        if ($freepbx == null) throw new \Exception("Not given a FreePBX Object");
        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database();
    }

    // Framework calls this for form processing
    public function doConfigPageInit($page) { }

    // Framework calls this to render the page
    public function showPage($page) {
        // Route to appropriate view based on $_REQUEST['view']
    }

    // Whitelist AJAX commands
    public function ajaxRequest($req, &$setting) {
        $allowed = ['dashboard_stats', 'sms_send', 'sms_list_inbox', ...];
        return in_array($req, $allowed);
    }

    // Handle AJAX calls
    public function ajaxHandler() {
        $command = $_REQUEST['command'] ?? '';
        // Route to handler method, return array (auto-JSON-encoded)
    }
}
```

### Database Access

```php
// In BMO class methods:
$stmt = $this->db->prepare("SELECT * FROM donglemanager_dongles WHERE state = ?");
$stmt->execute(['Free']);
$dongles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
```

### AMI Access (Web Context)

```php
$astman = $this->FreePBX->astman();
if ($astman) {
    $response = $astman->send_request('DongleSendSMS', [
        'Device' => 'dongle0',
        'Number' => '+1234567890',
        'Message' => 'Hello'
    ]);
}
```

### AMI Access (Worker/Cron Context)

```php
// Worker bootstraps its own AMI connection
$ami = new AmiClient('127.0.0.1', 5038, $amiUser, $amiPass);
$ami->connect();
$devices = $ami->sendAction('DongleShowDevices');
```

### AJAX from JavaScript

```javascript
// FreePBX provides jQuery globally
$.ajax({
    url: 'ajax.php',
    data: { module: 'donglemanager', command: 'dashboard_stats' },
    dataType: 'json',
    success: function(response) {
        if (response.success) {
            updateDashboard(response.data);
        }
    }
});
```

## Development Workflow

1. Edit files in `/var/www/html/admin/modules/donglemanager/`
2. For schema changes: bump version in `module.xml`, update `install.php`, run `fwconsole ma install donglemanager`
3. For PHP/JS changes: just reload the browser (no build step)
4. Test worker: `php /var/www/html/admin/modules/donglemanager/cron/worker.php`
5. View worker logs: `tail -f /var/log/dongle-worker.log`

## Vendor Assets (Offline — run before copying to server)

Run once on a machine with internet:

```bash
cd donglemanager
./scripts/download-vendor.sh
```

This downloads Chart.js and Font Awesome into `assets/vendor/`. Then copy the whole `donglemanager` folder to the server — no internet required there.
