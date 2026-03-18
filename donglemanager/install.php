<?php
/**
 * FreePBX Dongle Manager Module - Installation Script
 *
 * Creates all required database tables with donglemanager_ prefix
 * in the FreePBX asterisk database.
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/**
 * Called by FreePBX when module is installed
 */
function donglemanager_install() {
    global $db;

    $tables = [];

    // Table: donglemanager_dongles
    // One row per physical dongle device
    $tables['donglemanager_dongles'] = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Table: donglemanager_sms_inbox
    // Every incoming SMS
    $tables['donglemanager_sms_inbox'] = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Table: donglemanager_sms_outbox
    // Every outgoing SMS
    $tables['donglemanager_sms_outbox'] = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Table: donglemanager_ussd_history
    // USSD commands and responses
    $tables['donglemanager_ussd_history'] = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Table: donglemanager_logs
    // Application and dongle event logs
    $tables['donglemanager_logs'] = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Execute all table creation statements
    foreach ($tables as $name => $sql) {
        $result = $db->query($sql);
        if (\DB::IsError($result)) {
            die_freepbx("Failed to create table {$name}: " . $result->getMessage());
        }
    }

    // Log successful installation
    outn(_("Creating Dongle Manager tables..."));
    out(_("done"));

    return true;
}
