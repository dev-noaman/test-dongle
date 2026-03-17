# How to Build a FreePBX Module — Guide & Template

*Based on analysis of FreePBX modules: queues, ucp, recordings, and userman (release/17.0)*

---

## 1. Module Structure Overview

Every FreePBX module follows a consistent directory and file structure. Here's the common pattern found across all four repositories:

```
mymodule/
├── module.xml              # ★ REQUIRED — Module manifest (metadata, deps, hooks, menus)
├── Mymodule.class.php      # ★ REQUIRED — Main BMO class (PascalCase of module name)
├── functions.inc.php       # Legacy functions file (loaded by FreePBX framework)
├── functions.inc/          # Directory for additional include files (optional)
├── install.php             # Runs on module install (DB schema, settings)
├── uninstall.php           # Runs on module removal (cleanup)
├── page.mymodule.php       # Admin GUI page entry point
├── Backup.php              # Backup handler class
├── Restore.php             # Restore handler class
├── Api/
│   └── Rest/               # REST API controllers (optional)
│   └── Gql/                # GraphQL API (optional, seen in recordings)
├── assets/
│   ├── js/                 # JavaScript files
│   └── css/                # Stylesheets (or Less files)
├── views/                  # PHP view templates for the admin GUI
├── i18n/                   # Internationalization / translation files
├── agi-bin/                # AGI scripts (Asterisk Gateway Interface)
├── bin/                    # CLI helper scripts
├── utests/                 # PHPUnit tests
├── node/                   # Node.js components (seen in UCP module)
├── hooks/                  # Hook scripts
├── htdocs/                 # Public web-accessible files (seen in UCP)
├── drivers/                # Driver classes (seen in recordings)
├── ucp/                    # UCP integration files (seen in userman)
├── Console/                # Symfony console commands (seen in userman)
├── vendor/                 # Composer dependencies (seen in userman)
├── composer.json           # PHP dependency management (optional)
├── phpunit.xml             # Test configuration (optional)
├── LICENSE                 # License file
├── README.md               # Documentation
├── .gitignore
└── .gitattributes
```

### Key Observations per Module

| Module       | Main Class              | Unique Directories         | Language Mix         |
|-------------|------------------------|---------------------------|---------------------|
| **queues**    | `Queues.class.php`       | `operations/`, `bin/`       | 98.7% PHP           |
| **ucp**       | `Ucp.class.php`          | `hooks/`, `htdocs/`, `node/`, `includes/` | 61% JS, 30% PHP     |
| **recordings**| `Recordings.class.php`   | `drivers/`, `Api/Gql/`     | 58% PHP, 35% JS     |
| **userman**   | `Userman.class.php`      | `Console/`, `ucp/`, `vendor/` | 94% PHP             |

---

## 2. module.xml — The Module Manifest

This is the most critical file. It defines everything FreePBX needs to know about your module.

```xml
<module>
    <rawname>mymodule</rawname>
    <repo>standard</repo>
    <name>My Module Display Name</name>
    <version>17.0.1</version>
    <publisher>Your Name or Company</publisher>
    <license>GPLv3+</license>
    <licenselink>http://www.gnu.org/licenses/gpl-3.0.txt</licenselink>
    <category>Admin</category>
    <description>Short description of what your module does.</description>
    <more-info>https://wiki.freepbx.org/display/FPG/MyModule</more-info>

    <!-- Changelog entries, newest first -->
    <changelog>
        *17.0.1* Initial release
    </changelog>

    <!-- Menu items that appear in FreePBX admin -->
    <menuitems>
        <mymodule>My Module</mymodule>
        <!-- Add needsenginedb="yes" if you need the Asterisk DB connected -->
        <!-- <mymodule needsenginedb="yes">My Module</mymodule> -->
    </menuitems>

    <!-- Dependencies -->
    <depends>
        <phpversion>7.4</phpversion>
        <version>17.0.0</version>              <!-- Minimum FreePBX version -->
        <module>core ge 17.0.0</module>         <!-- Required modules with version -->
        <!-- <module>userman ge 17.0.0</module> -->
    </depends>

    <!-- Hooks: lets your module intercept methods from other modules -->
    <hooks>
        <!-- Hook into another module's class methods -->
        <core class="Core" namespace="FreePBX\modules">
            <method callingMethod="someMethod" class="Mymodule" namespace="FreePBX\modules">
                myHandlerMethod
            </method>
        </core>

        <!-- Bulk handler hooks (for import/export) -->
        <!--
        <bulkhandler class="Bulkhandler" namespace="FreePBX\modules">
            <method callingMethod="getHeaders" class="Mymodule" namespace="FreePBX\modules">
                bulkhandlerGetHeaders
            </method>
            <method callingMethod="getTypes" class="Mymodule" namespace="FreePBX\modules">
                bulkhandlerGetTypes
            </method>
        </bulkhandler>
        -->
    </hooks>

    <!-- Supported features -->
    <supported>
        <backup/>    <!-- Module supports backup -->
        <restore/>   <!-- Module supports restore -->
    </supported>
</module>
```

### Key module.xml Elements

| Element        | Purpose                                                   |
|---------------|----------------------------------------------------------|
| `<rawname>`    | Internal module ID — must match directory name and class prefix |
| `<category>`   | Where it shows in admin: `Admin`, `Applications`, `Connectivity`, `Reports`, `Settings` |
| `<menuitems>`  | Creates entries in FreePBX admin navigation              |
| `<depends>`    | Version requirements for PHP, FreePBX, and other modules |
| `<hooks>`      | Register callbacks into other modules' lifecycle methods |
| `<supported>`  | Declares backup/restore capability                       |

---

## 3. Main BMO Class — Mymodule.class.php

The main class uses FreePBX's **BMO (Big Module Object)** pattern. The namespace and class name must match what's declared in hooks.

```php
<?php
namespace FreePBX\modules;

class Mymodule extends \FreePBX_Helpers implements \BMO {

    // --- REQUIRED METHODS ---

    public function install() {
        // Runs during module installation
        // Create database tables, set default config values
    }

    public function uninstall() {
        // Runs during module removal
        // Drop tables, clean up files
    }

    public function backup() {
        // Return data for backup
    }

    public function restore($backup) {
        // Restore from backup data
    }

    public function doConfigPageInit($page) {
        // Process form submissions and $_REQUEST data
        // This replaces the old install.php page logic
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

        switch ($action) {
            case 'add':
                // Handle add
                break;
            case 'edit':
                // Handle edit
                break;
            case 'delete':
                // Handle delete
                break;
        }
    }

    // --- COMMON OPTIONAL METHODS ---

    public function showPage($page) {
        // Return HTML content for admin pages
        // Typically loads a view file from views/
    }

    public function getActionBar($request) {
        // Return action bar buttons (Save, Delete, etc.)
        return [
            'delete' => ['name' => 'delete', 'id' => 'delete', 'value' => _('Delete')],
            'reset'  => ['name' => 'reset',  'id' => 'reset',  'value' => _('Reset')],
            'submit' => ['name' => 'submit', 'id' => 'submit', 'value' => _('Submit')],
        ];
    }

    public function getRightNav($request) {
        // Return right-side navigation HTML
    }

    public function ajaxRequest($req, &$setting) {
        // Whitelist AJAX endpoints
        switch ($req) {
            case 'getJSON':
            case 'save':
                return true;
        }
        return false;
    }

    public function ajaxHandler() {
        // Handle AJAX calls
        $request = $_REQUEST;
        $command = $request['command'] ?? '';

        switch ($command) {
            case 'getJSON':
                return ['status' => true, 'data' => []];
            case 'save':
                return ['status' => true];
        }
        return ['status' => false];
    }

    // --- DIALPLAN GENERATION ---

    public function getDestinations() {
        // Return array of destinations this module provides
        // Used by other modules (e.g., IVR) to route calls here
    }

    public function getDestination($exten) {
        // Return human-readable description of a destination
    }

    // --- HOOK HANDLER METHODS ---
    // These match what you declared in module.xml <hooks>

    public function myHandlerMethod($data) {
        // Called when the hooked method in another module fires
        return $data;
    }

    // --- HELPER METHODS ---

    private function getDB() {
        return \FreePBX::Database();
    }
}
```

---

## 4. Lifecycle Scripts

### install.php
```php
<?php
// Runs when the module is installed or upgraded
// Commonly used to create/alter database tables

global $db; // PDO database handle

$sql = "CREATE TABLE IF NOT EXISTS mymodule_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL,
    `value` TEXT,
    UNIQUE KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$db->query($sql);

// You can also use FreePBX database helpers:
// $freepbx = \FreePBX::Create();
// $db = $freepbx->Database;
```

### uninstall.php
```php
<?php
// Runs when the module is removed
global $db;

$sql = "DROP TABLE IF EXISTS mymodule_settings";
$db->query($sql);
```

---

## 5. Page File — page.mymodule.php

This is the entry point that the FreePBX framework loads for your admin GUI.

```php
<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// $display is set by FreePBX framework to your menu item name
// $_REQUEST contains form data

$module = \FreePBX::Mymodule();  // Access your BMO class via magic method

// Typically delegates to the class:
echo $module->showPage($display);
```

---

## 6. Views

Views go in the `views/` directory and are typically plain PHP files that output HTML, using FreePBX's built-in form helpers and Bootstrap CSS.

```php
<!-- views/main.php -->
<div class="container-fluid">
    <h1><?php echo _("My Module")?></h1>
    <form method="post" id="mymodule-form" class="fpbx-submit">
        <input type="hidden" name="action" value="save">

        <div class="element-container">
            <div class="row">
                <div class="col-md-12">
                    <div class="row">
                        <div class="form-group">
                            <div class="col-md-3">
                                <label class="control-label" for="setting1">
                                    <?php echo _("Setting 1")?>
                                </label>
                            </div>
                            <div class="col-md-9">
                                <input type="text" class="form-control" id="setting1"
                                       name="setting1" value="<?php echo $setting1?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
```

---

## 7. Backup & Restore

Both repos (`Backup.php` and `Restore.php`) follow a standard pattern:

```php
<?php
// Backup.php
namespace FreePBX\modules\Mymodule;

use FreePBX\modules\Backup as Base;

class Backup extends Base\BackupBase {
    public function runBackup($id, $transaction) {
        $this->addDependency('mymodule');
        // Add database tables
        $this->dumpTables(['mymodule_settings']);
        // Add files if needed
        // $this->addFiles('/path/to/files');
    }
}
```

```php
<?php
// Restore.php
namespace FreePBX\modules\Mymodule;

use FreePBX\modules\Backup as Base;

class Restore extends Base\RestoreBase {
    public function runRestore() {
        $this->importTables(['mymodule_settings']);
        // Restore files if needed
        // $this->importFiles('/path/to/files');
    }
}
```

---

## 8. REST API (Optional)

If you want your module to expose a REST API, create files under `Api/Rest/`:

```php
<?php
// Api/Rest/Mymodule.php
namespace FreePBX\modules\Mymodule\Api\Rest;

use FreePBX\modules\Api\Rest\Base;

class Mymodule extends Base {
    public static function getScopes() {
        return [
            'read:mymodule' => ['description' => 'Read my module data'],
            'write:mymodule' => ['description' => 'Write my module data'],
        ];
    }

    public function setupRoutes($app) {
        $app->get('/mymodule/items', function ($request, $response, $args) {
            $items = \FreePBX::Mymodule()->getAllItems();
            return $response->withJson($items);
        });
    }
}
```

---

## 9. Hooks System

The hooks system is how modules communicate. Declared in `module.xml`, hooks let you:

- **Intercept** another module's method calls
- **Extend** functionality (e.g., add fields to bulk export)
- **React** to events (e.g., `postReload`, `fwcChownFiles`)

Common hook targets seen across modules:

| Hook Target        | Purpose                                    |
|-------------------|--------------------------------------------|
| `core`            | Core PBX events (reload, chown, etc.)      |
| `bulkhandler`     | Bulk import/export integration             |
| `userman`         | User management events                     |
| `fwconsole`       | CLI command registration                   |

---

## 10. Quick Start Checklist

1. **Create directory** under `/var/www/html/admin/modules/mymodule/` (production) or `/usr/src/freepbx/mymodule/` (dev)
2. **Write `module.xml`** — define rawname, version, category, dependencies, menu items
3. **Create `Mymodule.class.php`** — implement BMO interface with required methods
4. **Create `install.php`** — set up database tables
5. **Create `uninstall.php`** — clean up on removal
6. **Create `page.mymodule.php`** — admin page entry point
7. **Add views** in `views/` directory
8. **Add assets** (JS/CSS) in `assets/` directory
9. **Add `Backup.php` / `Restore.php`** if your module stores data
10. **Install**: `fwconsole ma install mymodule` then `fwconsole reload`

### Using the FreePBX Module Generator

FreePBX also provides an official scaffold tool:

```bash
cd /usr/src
wget https://git.freepbx.org/projects/FL/repos/freepbx-module-generator/raw/dist/freepbxgenerator.phar -O freepbxgenerator.phar
chmod +x freepbxgenerator.phar
./freepbxgenerator.phar
```

This interactive tool generates a skeleton module with all the required files pre-configured.

---

## 11. Naming Conventions

| Item                | Convention                         | Example            |
|--------------------|-----------------------------------|--------------------|
| Directory name      | All lowercase                     | `mymodule`         |
| `<rawname>`         | All lowercase, matches directory  | `mymodule`         |
| Main class file     | PascalCase + `.class.php`         | `Mymodule.class.php` |
| Class name          | PascalCase                        | `Mymodule`         |
| Namespace           | `FreePBX\modules`                 | —                  |
| Page file           | `page.<rawname>.php`              | `page.mymodule.php` |
| Views               | Descriptive names in `views/`     | `views/main.php`   |
| Menu item key       | Matches `<rawname>`               | `<mymodule>Label</mymodule>` |

---

## 12. Useful FreePBX Framework APIs

```php
// Access the database
$db = \FreePBX::Database();

// Access another module
$core = \FreePBX::Core();
$userman = \FreePBX::Userman();

// Get/set config values
$val = \FreePBX::Config()->get('SETTING_NAME');

// Get Asterisk manager connection
$ami = \FreePBX::AMI();

// Access the current module
$self = \FreePBX::Mymodule();

// Astman (Asterisk Manager Interface)
$astman = \FreePBX::astman();
```

---

*This guide was synthesized from the queues, ucp, recordings, and userman modules on the release/17.0 branch. For the latest patterns, always cross-reference the official FreePBX GitHub repos.*
