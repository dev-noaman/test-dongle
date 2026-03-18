<?php
/**
 * FreePBX Dongle Manager Module - Uninstallation Script
 *
 * Removes all database tables created by the module.
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/**
 * Called by FreePBX when module is uninstalled
 */
function donglemanager_uninstall() {
    global $db;

    $tables = [
        'donglemanager_logs',
        'donglemanager_ussd_history',
        'donglemanager_sms_outbox',
        'donglemanager_sms_inbox',
        'donglemanager_dongles',
    ];

    foreach ($tables as $table) {
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        $result = $db->query($sql);
        if (\DB::IsError($result)) {
            out(_("Failed to drop table {$table}: ") . $result->getMessage());
        } else {
            out(_("Dropped table: ") . $table);
        }
    }

    return true;
}
