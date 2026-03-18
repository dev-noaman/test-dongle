<?php
/**
 * Dongle Manager - Create Database Tables
 *
 * Run this if tables were not created during install (e.g. after a failed first install).
 * Uses FreePBX bootstrap to get the same DB connection as the framework.
 *
 * Usage: php create-tables.php
 *        (from module root: php scripts/create-tables.php)
 *
 * Run from admin dir (required for bootstrap):
 *        cd /var/www/html/admin && php modules/donglemanager/scripts/create-tables.php
 */

if (php_sapi_name() !== 'cli') {
    die('Run from command line: php create-tables.php');
}

// Bootstrap FreePBX: must load freepbx.conf first (sets $amp_conf with AMPDBUSER etc)
// then bootstrap.php. See: bootstrap.php "should always be called from freepbx.conf"
$freepbxConf = '/etc/freepbx.conf';
if (!file_exists($freepbxConf) || !is_readable($freepbxConf)) {
    die("FreePBX config not found at $freepbxConf. Run as root.\n");
}

$bootstrap_settings['freepbx_auth'] = false;
require_once $freepbxConf;

$db = \FreePBX::Database();
if (!$db) {
    die("Could not get database connection from FreePBX.\n");
}

// Same table definitions as install.php
$tables = [
    'donglemanager_dongles' => "
        CREATE TABLE IF NOT EXISTS `donglemanager_dongles` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `device` VARCHAR(50) NOT NULL,
            `imei` VARCHAR(20) DEFAULT '',
            `imsi` VARCHAR(20) DEFAULT '',
            `phone_number` VARCHAR(30) DEFAULT '',
            `operator` VARCHAR(100) DEFAULT '',
            `manufacturer` VARCHAR(100) DEFAULT '',
            `model` VARCHAR(100) DEFAULT '',
            `firmware` VARCHAR(100) DEFAULT '',
            `signal_rssi` TINYINT DEFAULT 0,
            `signal_percent` TINYINT DEFAULT 0,
            `gsm_status` VARCHAR(50) DEFAULT '',
            `state` VARCHAR(30) DEFAULT '',
            `enabled` TINYINT(1) DEFAULT 1,
            `sms_in_count` INT DEFAULT 0,
            `sms_out_count` INT DEFAULT 0,
            `tasks_in_queue` INT DEFAULT 0,
            `last_seen` DATETIME NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_device` (`device`),
            KEY `idx_state` (`state`),
            KEY `idx_last_seen` (`last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'donglemanager_sms_inbox' => "
        CREATE TABLE IF NOT EXISTS `donglemanager_sms_inbox` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `dongle` VARCHAR(50) NOT NULL,
            `sender` VARCHAR(30) NOT NULL,
            `message` TEXT NOT NULL,
            `received_at` DATETIME NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_dongle` (`dongle`),
            KEY `idx_sender` (`sender`),
            KEY `idx_received_at` (`received_at`),
            KEY `idx_is_read` (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'donglemanager_sms_outbox' => "
        CREATE TABLE IF NOT EXISTS `donglemanager_sms_outbox` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `dongle` VARCHAR(50) NOT NULL,
            `destination` VARCHAR(30) NOT NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('queued','sending','sent','failed') DEFAULT 'queued',
            `error` TEXT NULL,
            `retry_count` TINYINT DEFAULT 0,
            `sent_at` DATETIME NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_dongle` (`dongle`),
            KEY `idx_status` (`status`),
            KEY `idx_destination` (`destination`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'donglemanager_ussd_history' => "
        CREATE TABLE IF NOT EXISTS `donglemanager_ussd_history` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `dongle` VARCHAR(50) NOT NULL,
            `command` VARCHAR(255) NOT NULL,
            `response` TEXT NULL,
            `status` ENUM('sent','received','timeout','failed') DEFAULT 'sent',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_dongle` (`dongle`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'donglemanager_logs' => "
        CREATE TABLE IF NOT EXISTS `donglemanager_logs` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `level` ENUM('info','warning','error') DEFAULT 'info',
            `category` VARCHAR(50) NOT NULL,
            `dongle` VARCHAR(50) NULL,
            `message` TEXT NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_level` (`level`),
            KEY `idx_category` (`category`),
            KEY `idx_dongle` (`dongle`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
];

foreach ($tables as $name => $sql) {
    $db->query($sql);
    echo "Created table: $name\n";
}

echo "All Dongle Manager tables created successfully.\n";
