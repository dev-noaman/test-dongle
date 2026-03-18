<?php
/**
 * FreePBX Dongle Manager Module - Restore Handler
 *
 * Handles restoring all donglemanager_* tables from backup data.
 */

namespace FreePBX\modules;

use PDO;

class DonglemanagerRestore extends Donglemanager
{
    /**
     * Get list of tables to restore
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
     * Restore data from backup
     *
     * @param array $data Backup data array with table names as keys
     */
    public function importTables($data)
    {
        // Truncate and repopulate each table
        foreach ($this->getTables() as $table) {
            if (!isset($data[$table]) || !is_array($data[$table])) {
                continue;
            }

            // Truncate table first
            $this->db->exec("TRUNCATE TABLE {$table}");

            // Insert data row by row
            foreach ($data[$table] as $row) {
                if (empty($row)) {
                    continue;
                }

                $columns = array_keys($row);
                // Sanitize column names — allow only alphanumeric and underscore
                $columns = array_map(function ($col) {
                    return preg_replace('/[^a-zA-Z0-9_]/', '', $col);
                }, $columns);
                $placeholders = array_fill(0, count($columns), '?');

                $sql = "INSERT INTO {$table} (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array_values($row));
            }
        }
    }
}
