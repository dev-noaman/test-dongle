<?php
/**
 * FreePBX Dongle Manager Module - Main BMO Class
 *
 * Provides web-based management interface for Huawei GSM USB dongles via chan_dongle.
 */

namespace FreePBX\modules;

use PDO;
use PDOException;
use Exception;

class Donglemanager extends \FreePBX_Helpers implements \BMO
{
    public $FreePBX;
    protected $db;

    /**
     * Constructor
     *
     * @param object $freepbx FreePBX framework object
     * @throws Exception If not given a FreePBX object
     */
    public function __construct($freepbx = null)
    {
        if ($freepbx === null) {
            throw new Exception('Not given a FreePBX Object');
        }

        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database();
    }

    // ============================================
    // BMO Interface Methods
    // ============================================

    /**
     * Install method called by framework on module install
     */
    public function install()
    {
        // Installation is handled by install.php
        // This method can contain post-install logic if needed
    }

    /**
     * Uninstall method called by framework on module uninstall
     */
    public function uninstall()
    {
        // Uninstallation is handled by uninstall.php
    }

    /**
     * Backup method - returns data for backup
     */
    public function backup($backup_definitions)
    {
        $backup = new DonglemanagerBackup($this->FreePBX);
        return $backup->dumpTables();
    }

    /**
     * Restore method - restores data from backup
     */
    public function restore($backup)
    {
        $restore = new DonglemanagerRestore($this->FreePBX);
        $restore->importTables($backup);
    }

    /**
     * Process form submissions
     */
    public function doConfigPageInit($page)
    {
        // Handle any form submissions here
    }

    /**
     * Render the module page
     */
    public function showPage()
    {
        // Include the view router
        include __DIR__ . '/views/main.php';
    }

    /**
     * Action bar - empty; we use the in-page horizontal navbar instead.
     */
    public function getActionBar($request)
    {
        return [];
    }

    /**
     * Get right sidebar navigation
     */
    public function getRightNav($request)
    {
        return '';
    }

    // ============================================
    // AJAX Handler Configuration
    // ============================================

    /**
     * Check if AJAX request is allowed
     *
     * @param string $req The AJAX command being requested
     * @param array $setting Settings array
     * @return bool True if allowed
     */
    public function ajaxRequest($req, &$setting)
    {
        // Whitelist of allowed AJAX commands
        $allowedCommands = [
            // Dashboard
            'dashboard_stats',
            'sidebar_counts',
            // SMS
            'sms_list_inbox',
            'sms_list_outbox',
            'sms_send',
            'sms_mark_read',
            'sms_mark_unread',
            'sms_delete_inbox',
            'sms_delete_outbox',
            'sms_retry',
            // USSD
            'ussd_send',
            'ussd_check',
            'ussd_history',
            // Dongle Control
            'dongle_list',
            'dongle_restart',
            'dongle_stop',
            'dongle_start',
            'dongle_refresh',
            // Reports
            'report_summary',
            'report_chart',
            'report_dongle_stats',
            // Logs
            'log_list',
            'log_export',
        ];

        return in_array($req, $allowedCommands);
    }

    /**
     * Generate a CSRF token for forms
     */
    public function getCsrfToken()
    {
        if (empty($_SESSION['donglemanager_csrf_token'])) {
            $_SESSION['donglemanager_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['donglemanager_csrf_token'];
    }

    /**
     * Validate CSRF token from POST request
     */
    private function validateCsrfToken()
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['donglemanager_csrf_token'] ?? '';

        if (empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            return false;
        }
        return true;
    }

    /**
     * Check if command is a write (POST) command
     */
    private function isWriteCommand($command)
    {
        $writeCommands = [
            'sms_send', 'sms_mark_read', 'sms_mark_unread',
            'sms_delete_inbox', 'sms_delete_outbox', 'sms_retry',
            'ussd_send',
            'dongle_restart', 'dongle_stop', 'dongle_start', 'dongle_refresh',
        ];
        return in_array($command, $writeCommands);
    }

    /**
     * Handle AJAX requests
     *
     * @return array Response data (auto-JSON-encoded by framework)
     */
    public function ajaxHandler()
    {
        $command = $_REQUEST['command'] ?? '';

        // CSRF validation for write commands
        if ($this->isWriteCommand($command) && !$this->validateCsrfToken()) {
            return ['success' => false, 'message' => 'Invalid security token. Please refresh the page.'];
        }

        try {
            switch ($command) {
                // Dashboard
                case 'dashboard_stats':
                    return $this->handleDashboardStats();
                case 'sidebar_counts':
                    return $this->handleSidebarCounts();

                // SMS
                case 'sms_list_inbox':
                    return $this->handleSmsListInbox();
                case 'sms_list_outbox':
                    return $this->handleSmsListOutbox();
                case 'sms_send':
                    return $this->handleSmsSend();
                case 'sms_mark_read':
                    return $this->handleSmsMarkRead();
                case 'sms_mark_unread':
                    return $this->handleSmsMarkUnread();
                case 'sms_delete_inbox':
                    return $this->handleSmsDeleteInbox();
                case 'sms_delete_outbox':
                    return $this->handleSmsDeleteOutbox();
                case 'sms_retry':
                    return $this->handleSmsRetry();

                // USSD
                case 'ussd_send':
                    return $this->handleUssdSend();
                case 'ussd_check':
                    return $this->handleUssdCheck();
                case 'ussd_history':
                    return $this->handleUssdHistory();

                // Dongle Control
                case 'dongle_list':
                    return $this->handleDongleList();
                case 'dongle_restart':
                    return $this->handleDongleRestart();
                case 'dongle_stop':
                    return $this->handleDongleStop();
                case 'dongle_start':
                    return $this->handleDongleStart();
                case 'dongle_refresh':
                    return $this->handleDongleRefresh();

                // Reports
                case 'report_summary':
                    return $this->handleReportSummary();
                case 'report_chart':
                    return $this->handleReportChart();
                case 'report_dongle_stats':
                    return $this->handleReportDongleStats();

                // Logs
                case 'log_list':
                    return $this->handleLogList();
                case 'log_export':
                    return $this->handleLogExport();

                default:
                    return ['success' => false, 'message' => 'Unknown command: ' . $command];
            }
        } catch (Exception $e) {
            $this->log('error', 'system', null, 'AJAX error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    // ============================================
    // Helper Methods
    // ============================================

    /**
     * Log an event to the database
     *
     * @param string $level Log level: info, warning, error
     * @param string $category Category: sms, ussd, dongle, system, worker
     * @param string|null $dongle Device name or null for system-wide
     * @param string $message Log message
     */
    public function log($level, $category, $dongle, $message)
    {
        $sql = "INSERT INTO donglemanager_logs (level, category, dongle, message, created_at)
                VALUES (?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$level, $category, $dongle, $message]);
    }

    /**
     * Get sidebar badge counts
     */
    private function getSidebarCounts()
    {
        $counts = [
            'unread_inbox' => 0,
            'dongles_active' => 0,
            'dongles_total' => 0,
        ];

        // Unread inbox count
        $stmt = $this->db->query("SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE is_read = 0");
        $counts['unread_inbox'] = (int)$stmt->fetchColumn();

        // Dongle counts
        $stmt = $this->db->query("SELECT COUNT(*) as total, SUM(CASE WHEN state IN ('Free', 'Busy') THEN 1 ELSE 0 END) as active FROM donglemanager_dongles");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $counts['dongles_total'] = (int)($row['total'] ?? 0);
        $counts['dongles_active'] = (int)($row['active'] ?? 0);

        return $counts;
    }

    /**
     * Get all dongles
     */
    public function getAllDongles()
    {
        $sql = "SELECT * FROM donglemanager_dongles ORDER BY device";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active dongles (Free or Busy state)
     */
    public function getActiveDongles()
    {
        $sql = "SELECT * FROM donglemanager_dongles WHERE state IN ('Free', 'Busy') ORDER BY device";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check rate limit for SMS sending (10/min per dongle)
     */
    private function checkRateLimit($dongle)
    {
        $sql = "SELECT COUNT(*) FROM donglemanager_sms_outbox
                WHERE dongle = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dongle]);
        $count = (int)$stmt->fetchColumn();

        return $count < 10;
    }

    /**
     * Extract and sanitize integer IDs from POST data
     */
    private function extractIds($key = 'ids')
    {
        $ids = $_POST[$key] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        return array_filter(array_map('intval', $ids));
    }

    /**
     * Parse pagination parameters from GET request
     */
    private function parsePagination($defaultPerPage = 25)
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? $defaultPerPage)));
        $offset = ($page - 1) * $perPage;
        return [$page, $perPage, $offset];
    }

    /**
     * Build standard paginated response envelope
     */
    private function paginatedResponse($rows, $total, $page, $perPage)
    {
        return [
            'success' => true,
            'data' => [
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => max(1, ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * Build WHERE clause and params for log filters (shared by list and export)
     */
    private function buildLogFilters()
    {
        $where = '1=1';
        $params = [];

        if (!empty($_GET['level']) && $_GET['level'] !== 'all') {
            $where .= ' AND level = ?';
            $params[] = $_GET['level'];
        }
        if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
            $where .= ' AND category = ?';
            $params[] = $_GET['category'];
        }
        if (!empty($_GET['dongle']) && $_GET['dongle'] !== 'all') {
            $where .= ' AND dongle = ?';
            $params[] = $_GET['dongle'];
        }
        if (!empty($_GET['search'])) {
            $where .= ' AND message LIKE ?';
            $params[] = '%' . $_GET['search'] . '%';
        }
        if (!empty($_GET['date_from'])) {
            $where .= ' AND created_at >= ?';
            $params[] = $_GET['date_from'] . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $where .= ' AND created_at <= ?';
            $params[] = $_GET['date_to'] . ' 23:59:59';
        }

        return [$where, $params];
    }

    /**
     * Handle dongle control command (restart/stop/start)
     */
    private function handleDongleControl($amiCommand, $verb)
    {
        $device = trim($_POST['device'] ?? '');

        if (empty($device)) {
            return ['success' => false, 'message' => 'Device is required'];
        }

        try {
            $this->sendAmiCommand($amiCommand, ['Device' => $device]);
            $this->log('info', 'dongle', $device, "{$verb} command sent");
            return ['success' => true, 'message' => "{$verb} command sent to {$device}"];
        } catch (Exception $e) {
            $this->log('error', 'dongle', $device, "{$verb} failed: " . $e->getMessage());
            return ['success' => false, 'message' => "Failed to " . lcfirst($verb) . ": " . $e->getMessage()];
        }
    }

    /**
     * Calculate signal percentage from RSSI
     */
    private function rssiToPercent($rssi)
    {
        $rssi = (int)$rssi;
        if ($rssi === 99 || $rssi < 0) {
            return 0;
        }
        return min(100, max(0, round(($rssi / 31) * 100)));
    }

    /**
     * Get AMI connection
     */
    private function getAmi()
    {
        return $this->FreePBX->astman();
    }

    /**
     * Send AMI command with error handling
     */
    private function sendAmiCommand($action, $params = [])
    {
        $astman = $this->getAmi();

        if (!$astman) {
            throw new Exception('AMI connection unavailable');
        }

        return $astman->send_request($action, $params);
    }

    // ============================================
    // AJAX Handlers - Dashboard
    // ============================================

    private function handleDashboardStats()
    {
        $data = [];

        // Dongle counts
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN state IN ('Free', 'Busy') THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN state = 'Offline' OR last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as offline
            FROM donglemanager_dongles
        ");
        $dongleStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $data['dongles_total'] = (int)($dongleStats['total'] ?? 0);
        $data['dongles_active'] = (int)($dongleStats['active'] ?? 0);
        $data['dongles_offline'] = (int)($dongleStats['offline'] ?? 0);

        // SMS stats for today (index-friendly)
        $stmt = $this->db->query("
            SELECT
                (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE created_at >= CURDATE()) as sent,
                (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE created_at >= CURDATE() AND status = 'failed') as failed,
                (SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE received_at >= CURDATE()) as received
        ");
        $smsStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $data['sms_sent_today'] = (int)($smsStats['sent'] ?? 0);
        $data['sms_failed_today'] = (int)($smsStats['failed'] ?? 0);
        $data['sms_received_today'] = (int)($smsStats['received'] ?? 0);

        // 7-day chart data
        $stmt = $this->db->query("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as sent
            FROM donglemanager_sms_outbox
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $sentData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmt = $this->db->query("
            SELECT
                DATE(received_at) as date,
                COUNT(*) as received
            FROM donglemanager_sms_inbox
            WHERE received_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(received_at)
            ORDER BY date
        ");
        $receivedData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Build chart data with all 7 days
        $chartData = ['labels' => [], 'sent' => [], 'received' => []];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chartData['labels'][] = $date;
            $chartData['sent'][] = (int)($sentData[$date] ?? 0);
            $chartData['received'][] = (int)($receivedData[$date] ?? 0);
        }
        $data['chart_7day'] = $chartData;

        // Dongle list
        $stmt = $this->db->query("SELECT device, phone_number, operator, signal_percent, state, last_seen FROM donglemanager_dongles ORDER BY device");
        $data['dongles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent inbox (5 messages)
        $stmt = $this->db->query("SELECT id, dongle, sender, message, received_at FROM donglemanager_sms_inbox ORDER BY received_at DESC LIMIT 5");
        $data['recent_inbox'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent USSD (5 entries)
        $stmt = $this->db->query("SELECT id, dongle, command, response, created_at FROM donglemanager_ussd_history ORDER BY created_at DESC LIMIT 5");
        $data['recent_ussd'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'data' => $data];
    }

    private function handleSidebarCounts()
    {
        return ['success' => true, 'data' => $this->getSidebarCounts()];
    }

    // ============================================
    // AJAX Handlers - SMS
    // ============================================

    private function handleSmsListInbox()
    {
        list($page, $perPage, $offset) = $this->parsePagination();

        $where = '1=1';
        $params = [];

        // Filters
        if (!empty($_GET['dongle']) && $_GET['dongle'] !== 'all') {
            $where .= ' AND dongle = ?';
            $params[] = $_GET['dongle'];
        }
        if (!empty($_GET['search'])) {
            $where .= ' AND (sender LIKE ? OR message LIKE ?)';
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($_GET['date_from'])) {
            $where .= ' AND received_at >= ?';
            $params[] = $_GET['date_from'] . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $where .= ' AND received_at <= ?';
            $params[] = $_GET['date_to'] . ' 23:59:59';
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Get rows
        $sql = "SELECT id, dongle, sender, message, received_at, is_read, created_at
                FROM donglemanager_sms_inbox
                WHERE {$where}
                ORDER BY received_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->paginatedResponse($rows, $total, $page, $perPage);
    }

    private function handleSmsListOutbox()
    {
        list($page, $perPage, $offset) = $this->parsePagination();

        $where = '1=1';
        $params = [];

        // Filters
        if (!empty($_GET['dongle']) && $_GET['dongle'] !== 'all') {
            $where .= ' AND dongle = ?';
            $params[] = $_GET['dongle'];
        }
        if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
            $where .= ' AND status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['search'])) {
            $where .= ' AND (destination LIKE ? OR message LIKE ?)';
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($_GET['date_from'])) {
            $where .= ' AND created_at >= ?';
            $params[] = $_GET['date_from'] . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $where .= ' AND created_at <= ?';
            $params[] = $_GET['date_to'] . ' 23:59:59';
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Get rows
        $sql = "SELECT id, dongle, destination, message, status, error, retry_count, sent_at, created_at
                FROM donglemanager_sms_outbox
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->paginatedResponse($rows, $total, $page, $perPage);
    }

    private function handleSmsSend()
    {
        $dongle = trim($_POST['dongle'] ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Validation
        if (empty($destination) || strlen($destination) > 30) {
            return ['success' => false, 'message' => 'Destination and message are required'];
        }
        if (empty($message)) {
            return ['success' => false, 'message' => 'Destination and message are required'];
        }
        if (empty($dongle)) {
            return ['success' => false, 'message' => 'Please select a dongle'];
        }

        // Verify dongle is active
        $stmt = $this->db->prepare("SELECT state FROM donglemanager_dongles WHERE device = ?");
        $stmt->execute([$dongle]);
        $dongleState = $stmt->fetchColumn();

        if (!$dongleState || !in_array($dongleState, ['Free', 'Busy'])) {
            return ['success' => false, 'message' => "Dongle {$dongle} is not active"];
        }

        // Check rate limit
        if (!$this->checkRateLimit($dongle)) {
            return ['success' => false, 'message' => 'Rate limit: max 10 SMS/min per dongle'];
        }

        // Insert into outbox
        $sql = "INSERT INTO donglemanager_sms_outbox (dongle, destination, message, status, created_at)
                VALUES (?, ?, ?, 'queued', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dongle, $destination, $message]);
        $id = $this->db->lastInsertId();

        // Log
        $this->log('info', 'sms', $dongle, "SMS queued to {$destination}");

        return ['success' => true, 'data' => ['id' => (int)$id], 'message' => 'SMS queued'];
    }

    private function handleSmsMarkRead()
    {
        $ids = $this->extractIds();

        if (empty($ids)) {
            return ['success' => true, 'data' => ['updated' => 0]];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE donglemanager_sms_inbox SET is_read = 1 WHERE id IN ({$placeholders}) AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);

        return ['success' => true, 'data' => ['updated' => $stmt->rowCount()]];
    }

    private function handleSmsMarkUnread()
    {
        $ids = $this->extractIds();

        if (empty($ids)) {
            return ['success' => true, 'data' => ['updated' => 0]];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE donglemanager_sms_inbox SET is_read = 0 WHERE id IN ({$placeholders}) AND is_read = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);

        return ['success' => true, 'data' => ['updated' => $stmt->rowCount()]];
    }

    private function handleSmsDeleteInbox()
    {
        $ids = $this->extractIds();

        if (empty($ids)) {
            return ['success' => true, 'data' => ['deleted' => 0]];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM donglemanager_sms_inbox WHERE id IN ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);

        return ['success' => true, 'data' => ['deleted' => $stmt->rowCount()]];
    }

    private function handleSmsDeleteOutbox()
    {
        $ids = $this->extractIds();

        if (empty($ids)) {
            return ['success' => true, 'data' => ['deleted' => 0]];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM donglemanager_sms_outbox WHERE id IN ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);

        return ['success' => true, 'data' => ['deleted' => $stmt->rowCount()]];
    }

    private function handleSmsRetry()
    {
        $ids = $this->extractIds();

        if (empty($ids)) {
            return ['success' => true, 'data' => ['retried' => 0]];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE donglemanager_sms_outbox
                SET status = 'queued', error = NULL, retry_count = retry_count + 1
                WHERE id IN ({$placeholders}) AND status = 'failed' AND retry_count < 3";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);

        return ['success' => true, 'data' => ['retried' => $stmt->rowCount()]];
    }

    // ============================================
    // AJAX Handlers - USSD
    // ============================================

    private function handleUssdSend()
    {
        $dongle = trim($_POST['dongle'] ?? '');
        $command = trim($_POST['ussd_command'] ?? '');

        if (empty($dongle)) {
            return ['success' => false, 'message' => 'Please select a dongle'];
        }
        if (empty($command) || strlen($command) > 255) {
            return ['success' => false, 'message' => 'USSD command is required'];
        }

        // Verify dongle is active
        $stmt = $this->db->prepare("SELECT state FROM donglemanager_dongles WHERE device = ?");
        $stmt->execute([$dongle]);
        $dongleState = $stmt->fetchColumn();

        if (!$dongleState || !in_array($dongleState, ['Free', 'Busy'])) {
            return ['success' => false, 'message' => "Dongle {$dongle} is not active"];
        }

        // Send USSD via AMI
        try {
            $response = $this->sendAmiCommand('DongleSendUSSD', [
                'Device' => $dongle,
                'USSD' => $command,
            ]);

            // Insert into history
            $sql = "INSERT INTO donglemanager_ussd_history (dongle, command, status, created_at)
                    VALUES (?, ?, 'sent', NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dongle, $command]);
            $id = $this->db->lastInsertId();

            $this->log('info', 'ussd', $dongle, "USSD sent: {$command}");

            return ['success' => true, 'data' => ['id' => (int)$id], 'message' => 'USSD sent'];
        } catch (Exception $e) {
            $this->log('error', 'ussd', $dongle, "USSD send failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send USSD: ' . $e->getMessage()];
        }
    }

    private function handleUssdCheck()
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid ID'];
        }

        $stmt = $this->db->prepare("SELECT id, dongle, command, response, status FROM donglemanager_ussd_history WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'message' => 'USSD record not found'];
        }

        return ['success' => true, 'data' => $row];
    }

    private function handleUssdHistory()
    {
        list($page, $perPage, $offset) = $this->parsePagination();

        $where = '1=1';
        $params = [];

        if (!empty($_GET['dongle']) && $_GET['dongle'] !== 'all') {
            $where .= ' AND dongle = ?';
            $params[] = $_GET['dongle'];
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM donglemanager_ussd_history WHERE {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Get rows
        $sql = "SELECT id, dongle, command, response, status, created_at
                FROM donglemanager_ussd_history
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->paginatedResponse($rows, $total, $page, $perPage);
    }

    // ============================================
    // AJAX Handlers - Dongle Control
    // ============================================

    private function handleDongleList()
    {
        $dongles = $this->getAllDongles();
        return ['success' => true, 'data' => $dongles];
    }

    private function handleDongleRestart()
    {
        return $this->handleDongleControl('DongleRestart', 'Restart');
    }

    private function handleDongleStop()
    {
        return $this->handleDongleControl('DongleStop', 'Stop');
    }

    private function handleDongleStart()
    {
        return $this->handleDongleControl('DongleStart', 'Start');
    }

    private function handleDongleRefresh()
    {
        try {
            // Send DongleShowDevices to refresh status
            $response = $this->sendAmiCommand('DongleShowDevices');

            // Return updated list
            $dongles = $this->getAllDongles();
            return ['success' => true, 'data' => $dongles];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to refresh: ' . $e->getMessage()];
        }
    }

    // ============================================
    // AJAX Handlers - Reports
    // ============================================

    private function handleReportSummary()
    {
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $dongle = $_GET['dongle'] ?? 'all';

        if (empty($dateFrom) || empty($dateTo)) {
            return ['success' => false, 'message' => 'Date range is required'];
        }

        $dongleFilter = $dongle !== 'all' ? ' AND dongle = ?' : '';
        $baseParams = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        if ($dongle !== 'all') $baseParams[] = $dongle;

        // Get sent + failed counts in one query (index-friendly)
        $sql = "SELECT COUNT(*) as total, SUM(status = 'failed') as failed
                FROM donglemanager_sms_outbox WHERE created_at >= ? AND created_at <= ? {$dongleFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($baseParams);
        $outboxStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $sent = (int)($outboxStats['total'] ?? 0);
        $failed = (int)($outboxStats['failed'] ?? 0);

        // Get received count (index-friendly)
        $sql = "SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE received_at >= ? AND received_at <= ? {$dongleFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($baseParams);
        $received = (int)$stmt->fetchColumn();

        $total = $sent + $received;
        $successRate = $sent > 0 ? round((($sent - $failed) / $sent) * 100, 1) : 0;

        return [
            'success' => true,
            'data' => [
                'total' => $total,
                'sent' => $sent,
                'received' => $received,
                'failed' => $failed,
                'success_rate' => $successRate,
            ],
        ];
    }

    private function handleReportChart()
    {
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $dongle = $_GET['dongle'] ?? 'all';

        if (empty($dateFrom) || empty($dateTo)) {
            return ['success' => false, 'message' => 'Date range is required'];
        }

        $dongleFilter = $dongle !== 'all' ? ' AND dongle = ?' : '';

        // Daily data (index-friendly)
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM donglemanager_sms_outbox
                WHERE created_at >= ? AND created_at <= ? {$dongleFilter}
                GROUP BY DATE(created_at)
                ORDER BY date";
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        if ($dongle !== 'all') $params[] = $dongle;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $sentByDate = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $sql = "SELECT DATE(received_at) as date, COUNT(*) as count
                FROM donglemanager_sms_inbox
                WHERE received_at >= ? AND received_at <= ? {$dongleFilter}
                GROUP BY DATE(received_at)
                ORDER BY date";
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        if ($dongle !== 'all') $params[] = $dongle;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $receivedByDate = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Build daily chart data
        $daily = ['labels' => [], 'sent' => [], 'received' => []];
        $period = new \DatePeriod(
            new \DateTime($dateFrom),
            new \DateInterval('P1D'),
            (new \DateTime($dateTo))->modify('+1 day')
        );

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $daily['labels'][] = $dateStr;
            $daily['sent'][] = (int)($sentByDate[$dateStr] ?? 0);
            $daily['received'][] = (int)($receivedByDate[$dateStr] ?? 0);
        }

        // Per-dongle data
        $sql = "SELECT d.device, d.phone_number,
                       COALESCE(s.sent_count, 0) as sent_count,
                       COALESCE(r.received_count, 0) as received_count
                FROM donglemanager_dongles d
                LEFT JOIN (
                    SELECT dongle, COUNT(*) as sent_count
                    FROM donglemanager_sms_outbox
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY dongle
                ) s ON d.device = s.dongle
                LEFT JOIN (
                    SELECT dongle, COUNT(*) as received_count
                    FROM donglemanager_sms_inbox
                    WHERE DATE(received_at) BETWEEN ? AND ?
                    GROUP BY dongle
                ) r ON d.device = r.dongle
                ORDER BY d.device";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
        $dongleRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $perDongle = [];
        foreach ($dongleRows as $row) {
            $total = $row['sent_count'] + $row['received_count'];
            if ($total > 0) {
                $perDongle[] = [
                    'device' => $row['device'],
                    'label' => $row['device'] . ($row['phone_number'] ? ' (' . $row['phone_number'] . ')' : ''),
                    'total' => $total,
                ];
            }
        }

        return [
            'success' => true,
            'data' => [
                'daily' => $daily,
                'per_dongle' => $perDongle,
            ],
        ];
    }

    private function handleReportDongleStats()
    {
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        if (empty($dateFrom) || empty($dateTo)) {
            return ['success' => false, 'message' => 'Date range is required'];
        }

        $sql = "SELECT
                    d.device,
                    d.phone_number,
                    d.operator,
                    COALESCE(s.sent_count, 0) as sent,
                    COALESCE(r.received_count, 0) as received,
                    COALESCE(f.failed_count, 0) as failed
                FROM donglemanager_dongles d
                LEFT JOIN (
                    SELECT dongle, COUNT(*) as sent_count
                    FROM donglemanager_sms_outbox
                    WHERE created_at >= ? AND created_at <= ?
                    GROUP BY dongle
                ) s ON d.device = s.dongle
                LEFT JOIN (
                    SELECT dongle, COUNT(*) as received_count
                    FROM donglemanager_sms_inbox
                    WHERE received_at >= ? AND received_at <= ?
                    GROUP BY dongle
                ) r ON d.device = r.dongle
                LEFT JOIN (
                    SELECT dongle, COUNT(*) as failed_count
                    FROM donglemanager_sms_outbox
                    WHERE created_at >= ? AND created_at <= ? AND status = 'failed'
                    GROUP BY dongle
                ) f ON d.device = f.dongle
                ORDER BY d.device";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate success rate for each
        foreach ($rows as &$row) {
            $sent = (int)$row['sent'];
            $failed = (int)$row['failed'];
            $row['success_rate'] = $sent > 0 ? round((($sent - $failed) / $sent) * 100, 1) : 0;
        }

        return ['success' => true, 'data' => $rows];
    }

    // ============================================
    // AJAX Handlers - Logs
    // ============================================

    private function handleLogList()
    {
        list($page, $perPage, $offset) = $this->parsePagination(50);
        list($where, $params) = $this->buildLogFilters();

        // Count total
        $countSql = "SELECT COUNT(*) FROM donglemanager_logs WHERE {$where}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Get rows
        $sql = "SELECT id, level, category, dongle, message, created_at
                FROM donglemanager_logs
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->paginatedResponse($rows, $total, $page, $perPage);
    }

    private function handleLogExport()
    {
        list($where, $params) = $this->buildLogFilters();

        $sql = "SELECT created_at as Time, level as Level, category as Category, dongle as Dongle, message as Message
                FROM donglemanager_logs
                WHERE {$where}
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Output CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="donglemanager_logs_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');

        // Header row
        if (!empty($rows)) {
            fputcsv($output, array_keys($rows[0]));
        }

        // Data rows
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
