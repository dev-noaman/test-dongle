<?php
/**
 * FreePBX Dongle Manager Module - Backup Handler
 *
 * Handles backing up all donglemanager_* tables.
 */

namespace FreePBX\modules;

use PDO;

class DonglemanagerBackup extends Donglemanager
{
    /**
     * Get list of tables to backup
     */
    private function getTables()
    {
        return [
            'donglemanager_dongles',
            'donglemanager_sms_inbox',
            'donglemanager_sms_outbox',
            'donglemanager_ussd_history',
            'donglemanager_logs',
        ];
    }

    /**
     * Generate backup data for all module tables
     *
     * @return array Backup data
     */
    public function dumpTables()
    {
        $backup = [];

        foreach ($this->getTables() as $table) {
            $sql = "SELECT * FROM {$table}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $backup[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $backup;
    }
}
