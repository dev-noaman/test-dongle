<?php
/**
 * Dongle Manager Background Worker
 *
 * Run via cron every minute. Handles:
 * - Updating dongle statuses from AMI
 * - Processing SMS outbox queue
 * - Capturing incoming SMS/USSD events
 * - Handling send timeouts
 * - Auto-restarting error-state dongles
 * - Cleaning old logs
 *
 * Usage: php /path/to/worker.php
 * Cron: * * * * * php /var/www/html/admin/modules/donglemanager/cron/worker.php >> /var/log/dongle-worker.log 2>&1
 */

// Ensure CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line');
}

// Define paths
define('MODULE_PATH', dirname(__DIR__));
define('PID_FILE', '/tmp/dongle-worker.pid');
define('PID_TIMEOUT', 120); // Maximum worker runtime in seconds

// Load includes
require_once MODULE_PATH . '/includes/ConfigReader.php';
require_once MODULE_PATH . '/includes/AmiClient.php';

use FreePBX\modules\Donglemanager\ConfigReader;
use FreePBX\modules\Donglemanager\AmiClient;

/**
 * Main worker class
 */
class DongleWorker
{
    private $db;
    private $ami;
    private $config;
    private $startTime;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->startTime = time();
        $this->loadConfig();
        $this->connectDatabase();
    }

    /**
     * Load FreePBX configuration
     */
    private function loadConfig()
    {
        $this->config = ConfigReader::readConfig();
    }

    /**
     * Connect to database
     */
    private function connectDatabase()
    {
        try {
            $dsn = "mysql:host={$this->config['db_host']};dbname={$this->config['db_name']};charset=utf8mb4";
            $this->db = new PDO($dsn, $this->config['db_user'], $this->config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            $this->log('error', 'worker', null, 'Database connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Connect to AMI
     */
    private function connectAmi()
    {
        try {
            $this->ami = new AmiClient(
                $this->config['ami_host'],
                $this->config['ami_port'],
                $this->config['ami_user'],
                $this->config['ami_pass']
            );
            $this->ami->connect();
            $this->ami->login();
            return true;
        } catch (Exception $e) {
            $this->log('error', 'worker', null, 'AMI connection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect from AMI
     */
    private function disconnectAmi()
    {
        if ($this->ami) {
            $this->ami->disconnect();
            $this->ami = null;
        }
    }

    /**
     * Log a message
     */
    public function log($level, $category, $dongle, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] [{$category}]";

        if ($dongle) {
            $logLine .= " [{$dongle}]";
        }

        $logLine .= " {$message}";

        // Output to stdout (for cron log)
        echo $logLine . "\n";

        // Also log to database
        try {
            if ($this->db) {
                $stmt = $this->db->prepare(
                    "INSERT INTO donglemanager_logs (level, category, dongle, message, created_at)
                     VALUES (?, ?, ?, ?, NOW())"
                );
                $stmt->execute([$level, $category, $dongle, $message]);
            }
        } catch (PDOException $e) {
            // Silently fail if database logging doesn't work
            error_log("DongleWorker log error: " . $e->getMessage());
        }
    }

    /**
     * Run the worker
     */
    public function run()
    {
        // Connect to AMI
        if (!$this->connectAmi()) {
            $this->log('error', 'worker', null, 'Cannot proceed without AMI connection');
            return 1;
        }

        try {
            // Step 1: Update dongle statuses
            $this->updateDongleStatuses();

            // Step 2: Process SMS queue
            $this->processSmsQueue();

            // Step 3: Listen for events
            $this->listenForEvents();

            // Step 4: Handle timeouts
            $this->handleTimeouts();

            // Step 5: Auto-restart error dongles
            $this->autoRestartDongles();

            // Step 6: Clean old logs
            $this->cleanOldLogs();
        } catch (Exception $e) {
            $this->log('error', 'worker', null, 'Worker error: ' . $e->getMessage());
        } finally {
            $this->disconnectAmi();
        }

        return 0;
    }

    /**
     * Step 1: Update dongle statuses from AMI
     */
    private function updateDongleStatuses()
    {
        $this->log('info', 'worker', null, 'Updating dongle statuses');

        try {
            $response = $this->ami->sendAction('DongleShowDevices');

            // Read device entry events until DongleShowDevicesComplete
            $devices = [];
            $maxWait = PID_TIMEOUT - 10;

            while (time() - $this->startTime < $maxWait) {
                $event = $this->ami->readResponse();
                if (empty($event)) break;

                $eventType = $event['Event'] ?? '';

                if ($eventType === 'DongleDeviceEntry') {
                    $devices[] = $event;
                } elseif ($eventType === 'DongleShowDevicesComplete') {
                    break;
                }
            }

            // Get current devices from DB
            $stmt = $this->db->query("SELECT device FROM donglemanager_dongles");
            $existingDevices = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $seenDevices = [];

            // Process each device
            foreach ($devices as $device) {
                $deviceName = $device['Device'] ?? '';
                if (empty($deviceName)) continue;

                $seenDevices[] = $deviceName;

                // Calculate signal percentage
                $rssi = (int)($device['RSSI'] ?? 0);
                $signalPercent = $this->rssiToPercent($rssi);

                // Check if device exists
                if (in_array($deviceName, $existingDevices)) {
                    // Update existing
                    $sql = "UPDATE donglemanager_dongles SET
                            imei = ?, imsi = ?, phone_number = ?, operator = ?,
                            manufacturer = ?, model = ?, firmware = ?,
                            signal_rssi = ?, signal_percent = ?, gsm_status = ?,
                            state = ?, sms_in_count = ?, sms_out_count = ?,
                            tasks_in_queue = ?, last_seen = NOW(), updated_at = NOW()
                            WHERE device = ?";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $device['IMEI'] ?? '',
                        $device['IMSI'] ?? '',
                        $device['Number'] ?? '',
                        $device['Operator'] ?? '',
                        $device['Manufacturer'] ?? '',
                        $device['Model'] ?? '',
                        $device['Firmware'] ?? '',
                        $rssi,
                        $signalPercent,
                        $device['GSM'] ?? '',
                        $device['Status'] ?? '',
                        (int)($device['SMS_In_Count'] ?? 0),
                        (int)($device['SMS_Out_Count'] ?? 0),
                        (int)($device['Tasks_In_Queue'] ?? 0),
                        $deviceName,
                    ]);
                } else {
                    // Insert new
                    $sql = "INSERT INTO donglemanager_dongles
                            (device, imei, imsi, phone_number, operator, manufacturer, model, firmware,
                             signal_rssi, signal_percent, gsm_status, state, sms_in_count, sms_out_count,
                             tasks_in_queue, last_seen, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $deviceName,
                        $device['IMEI'] ?? '',
                        $device['IMSI'] ?? '',
                        $device['Number'] ?? '',
                        $device['Operator'] ?? '',
                        $device['Manufacturer'] ?? '',
                        $device['Model'] ?? '',
                        $device['Firmware'] ?? '',
                        $rssi,
                        $signalPercent,
                        $device['GSM'] ?? '',
                        $device['Status'] ?? '',
                        (int)($device['SMS_In_Count'] ?? 0),
                        (int)($device['SMS_Out_Count'] ?? 0),
                        (int)($device['Tasks_In_Queue'] ?? 0),
                    ]);

                    $this->log('info', 'dongle', $deviceName, 'New dongle detected');
                }
            }

            // Mark devices not seen as offline
            $placeholders = implode(',', array_fill(0, count($seenDevices), '?'));
            $sql = "UPDATE donglemanager_dongles SET state = 'Offline', updated_at = NOW()
                    WHERE device NOT IN ({$placeholders}) AND state != 'Offline'";

            if (!empty($seenDevices)) {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($seenDevices);
                $offlineCount = $stmt->rowCount();
                if ($offlineCount > 0) {
                    $this->log('info', 'worker', null, "Marked {$offlineCount} dongles as offline");
                }
            }

        } catch (Exception $e) {
            $this->log('error', 'worker', null, 'Failed to update dongle statuses: ' . $e->getMessage());
        }
    }

    /**
     * Step 2: Process SMS queue
     */
    private function processSmsQueue()
    {
        try {
            // Get queued messages (limit 5 per cycle)
            $sql = "SELECT id, dongle, destination, message
                    FROM donglemanager_sms_outbox
                    WHERE status = 'queued'
                    ORDER BY created_at ASC
                    LIMIT 5";

            $stmt = $this->db->query($sql);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages as $msg) {
                // Check rate limit (10/min per dongle)
                $rateSql = "SELECT COUNT(*) FROM donglemanager_sms_outbox
                           WHERE dongle = ? AND status = 'sending' AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)";
                $rateStmt = $this->db->prepare($rateSql);
                $rateStmt->execute([$msg['dongle']]);
                $recentCount = (int)$rateStmt->fetchColumn();

                if ($recentCount >= 10) {
                    $this->log('warning', 'sms', $msg['dongle'], "Rate limit exceeded, skipping message ID {$msg['id']}");
                    continue;
                }

                // Mark as sending
                $updateSql = "UPDATE donglemanager_sms_outbox SET status = 'sending' WHERE id = ?";
                $this->db->prepare($updateSql)->execute([$msg['id']]);

                // Send via AMI
                try {
                    $response = $this->ami->sendAction('DongleSendSMS', [
                        'Device' => $msg['dongle'],
                        'Number' => $msg['destination'],
                        'Message' => $msg['message'],
                    ]);

                    $this->log('info', 'sms', $msg['dongle'], "SMS sent to {$msg['destination']} (ID: {$msg['id']})");
                } catch (Exception $e) {
                    // Mark as failed
                    $failSql = "UPDATE donglemanager_sms_outbox SET status = 'failed', error = ? WHERE id = ?";
                    $this->db->prepare($failSql)->execute([$e->getMessage(), $msg['id']]);
                    $this->log('error', 'sms', $msg['dongle'], "SMS failed to {$msg['destination']}: " . $e->getMessage());
                }
            }

        } catch (Exception $e) {
            $this->log('error', 'worker', null, 'Failed to process SMS queue: ' . $e->getMessage());
        }
    }

    /**
     * Step 3: Listen for AMI events
     */
    private function listenForEvents()
    {
        $events = $this->ami->readEvents(5);

        foreach ($events as $event) {
            $eventType = $event['Event'] ?? '';

            switch ($eventType) {
                case 'DongleNewSMS':
                    $this->handleIncomingSms($event);
                    break;

                case 'DongleNewUSSD':
                    $this->handleUssdResponse($event);
                    break;

                case 'DongleStatus':
                    $this->handleDongleStatus($event);
                    break;
            }
        }

    }

    /**
     * Handle incoming SMS event
     */
    private function handleIncomingSms($event)
    {
        $dongle = $event['Device'] ?? '';
        $sender = $event['From'] ?? '';
        $message = $event['Message'] ?? '';

        if (empty($dongle) || empty($sender)) {
            return;
        }

        try {
            $sql = "INSERT INTO donglemanager_sms_inbox (dongle, sender, message, received_at, is_read, created_at)
                    VALUES (?, ?, ?, NOW(), 0, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dongle, $sender, $message]);

            $this->log('info', 'sms', $dongle, "Incoming SMS from {$sender}");
        } catch (PDOException $e) {
            $this->log('error', 'sms', $dongle, 'Failed to save incoming SMS: ' . $e->getMessage());
        }
    }

    /**
     * Handle USSD response event
     */
    private function handleUssdResponse($event)
    {
        $dongle = $event['Device'] ?? '';
        $response = $event['USSD'] ?? '';

        if (empty($dongle)) {
            return;
        }

        try {
            // Find the most recent USSD without a response
            $sql = "UPDATE donglemanager_ussd_history
                    SET response = ?, status = 'received'
                    WHERE dongle = ? AND status = 'sent'
                    ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$response, $dongle]);

            $this->log('info', 'ussd', $dongle, 'USSD response received');
        } catch (PDOException $e) {
            $this->log('error', 'ussd', $dongle, 'Failed to save USSD response: ' . $e->getMessage());
        }
    }

    /**
     * Handle dongle status change event
     */
    private function handleDongleStatus($event)
    {
        $dongle = $event['Device'] ?? '';
        $status = $event['Status'] ?? '';

        if (empty($dongle)) {
            return;
        }

        try {
            $sql = "UPDATE donglemanager_dongles SET state = ?, updated_at = NOW() WHERE device = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $dongle]);

            $this->log('info', 'dongle', $dongle, "Status changed to {$status}");
        } catch (PDOException $e) {
            $this->log('error', 'dongle', $dongle, 'Failed to update status: ' . $e->getMessage());
        }
    }

    /**
     * Step 4: Handle timeouts
     */
    private function handleTimeouts()
    {
        try {
            // SMS sending timeout (120 seconds)
            $smsSql = "UPDATE donglemanager_sms_outbox
                      SET status = 'failed', error = 'Send timeout'
                      WHERE status = 'sending' AND created_at < DATE_SUB(NOW(), INTERVAL 120 SECOND)";
            $stmt = $this->db->prepare($smsSql);
            $stmt->execute();
            $smsTimeouts = $stmt->rowCount();

            if ($smsTimeouts > 0) {
                $this->log('warning', 'sms', null, "Marked {$smsTimeouts} SMS as timed out");
            }

            // USSD timeout (30 seconds)
            $ussdSql = "UPDATE donglemanager_ussd_history
                       SET status = 'timeout'
                       WHERE status = 'sent' AND created_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)";
            $stmt = $this->db->prepare($ussdSql);
            $stmt->execute();
            $ussdTimeouts = $stmt->rowCount();

            if ($ussdTimeouts > 0) {
                $this->log('warning', 'ussd', null, "Marked {$ussdTimeouts} USSD as timed out");
            }
        } catch (PDOException $e) {
            $this->log('error', 'worker', null, 'Failed to handle timeouts: ' . $e->getMessage());
        }
    }

    /**
     * Step 5: Auto-restart error dongles
     */
    private function autoRestartDongles()
    {
        try {
            // Find dongles in error state for more than 5 minutes
            $sql = "SELECT device FROM donglemanager_dongles
                    WHERE state = 'Error' AND last_seen < DATE_SUB(NOW(), INTERVAL 300 SECOND)";
            $stmt = $this->db->query($sql);
            $errorDongles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($errorDongles as $device) {
                try {
                    $this->ami->sendAction('DongleRestart', ['Device' => $device]);
                    $this->log('info', 'dongle', $device, 'Auto-restarted');
                } catch (Exception $e) {
                    $this->log('error', 'dongle', $device, 'Auto-restart failed: ' . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            $this->log('error', 'worker', null, 'Failed to auto-restart dongles: ' . $e->getMessage());
        }
    }

    /**
     * Step 6: Clean old logs
     */
    private function cleanOldLogs()
    {
        try {
            $sql = "DELETE FROM donglemanager_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $deleted = $stmt->rowCount();

            if ($deleted > 0) {
                $this->log('info', 'worker', null, "Deleted {$deleted} old log entries");
            }
        } catch (PDOException $e) {
            $this->log('error', 'worker', null, 'Failed to clean old logs: ' . $e->getMessage());
        }
    }

    /**
     * Convert RSSI to percentage
     */
    private function rssiToPercent($rssi)
    {
        $rssi = (int)$rssi;
        if ($rssi === 99 || $rssi < 0) {
            return 0;
        }
        return min(100, max(0, round(($rssi / 31) * 100)));
    }

}

/**
 * PID file management
 */
function acquirePidLock()
{
    global $argv;

    // Check if PID file exists
    if (file_exists(PID_FILE)) {
        $pid = (int)file_get_contents(PID_FILE);

        // Check if process is still running
        if (posix_kill($pid, 0)) {
            // Check if it's been running too long
            $mtime = filemtime(PID_FILE);
            if (time() - $mtime > PID_TIMEOUT) {
                // Stale lock, remove it
                unlink(PID_FILE);
            } else {
                error_log("Worker already running (PID {$pid})");
                exit(0);
            }
        } else {
            // Dead process, remove stale lock
            unlink(PID_FILE);
        }
    }

    // Create PID file
    $pid = getmypid();
    file_put_contents(PID_FILE, $pid);

    return $pid;
}

function releasePidLock()
{
    if (file_exists(PID_FILE)) {
        unlink(PID_FILE);
    }
}

// Main execution
try {
    $pid = acquirePidLock();

    $worker = new DongleWorker();
    $exitCode = $worker->run();

    releasePidLock();
    exit($exitCode);
} catch (Exception $e) {
    error_log("Worker fatal error: " . $e->getMessage());
    releasePidLock();
    exit(1);
}
