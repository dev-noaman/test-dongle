<?php
/**
 * AMI Socket Client
 *
 * Direct AMI socket client for use by the background worker.
 * The worker runs outside FreePBX framework context and cannot use \FreePBX::astman().
 */

namespace FreePBX\modules\Donglemanager;

class AmiClient
{
    private $host;
    private $port;
    private $user;
    private $pass;
    private $socket;
    private $connected = false;
    private $timeout = 10;

    /**
     * Constructor
     *
     * @param string $host AMI host (default: 127.0.0.1)
     * @param int $port AMI port (default: 5038)
     * @param string $user AMI username
     * @param string $pass AMI password
     */
    public function __construct(string $host = '127.0.0.1', int $port = 5038, string $user = '', string $pass = '')
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * Connect to AMI socket
     *
     * @return bool True on success
     * @throws \Exception On connection failure
     */
    public function connect(): bool
    {
        $errno = 0;
        $errstr = '';

        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new \Exception("AMI connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read the greeting
        $greeting = $this->readLine();
        if (strpos($greeting, 'Asterisk Call Manager') === false) {
            fclose($this->socket);
            throw new \Exception("Invalid AMI greeting: {$greeting}");
        }

        $this->connected = true;
        return true;
    }

    /**
     * Login to AMI
     *
     * @return bool True on success
     * @throws \Exception On login failure
     */
    public function login(): bool
    {
        if (!$this->connected) {
            throw new \Exception("Not connected to AMI");
        }

        $response = $this->sendAction('Login', [
            'Username' => $this->user,
            'Secret' => $this->pass,
        ]);

        if ($response['Response'] !== 'Success') {
            throw new \Exception("AMI login failed: " . ($response['Message'] ?? 'Unknown error'));
        }

        return true;
    }

    /**
     * Send an AMI action and return the response
     *
     * @param string $action Action name (e.g., DongleShowDevices)
     * @param array $params Action parameters
     * @return array Response as key-value pairs
     */
    public function sendAction(string $action, array $params = []): array
    {
        if (!$this->connected) {
            throw new \Exception("Not connected to AMI");
        }

        // Build action packet
        $packet = "Action: {$action}\r\n";
        foreach ($params as $key => $value) {
            $packet .= "{$key}: {$value}\r\n";
        }
        $packet .= "\r\n";

        // Send
        fwrite($this->socket, $packet);

        // Read response
        return $this->readResponse();
    }

    /**
     * Read a single AMI response (until blank line)
     *
     * @return array Response as key-value pairs
     */
    public function readResponse(): array
    {
        $response = [];
        $line = '';

        while (($line = $this->readLine()) !== false) {
            // Blank line marks end of response
            if (trim($line) === '') {
                break;
            }

            // Parse key: value
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $key = trim(substr($line, 0, $colonPos));
                $value = trim(substr($line, $colonPos + 1));
                $response[$key] = $value;
            }
        }

        return $response;
    }

    /**
     * Read events for a specified timeout period
     *
     * @param int $timeoutSec Seconds to listen for events
     * @return array Array of events, each event is a key-value array
     */
    public function readEvents(int $timeoutSec): array
    {
        $events = [];
        $endTime = time() + $timeoutSec;

        // Set non-blocking mode for event reading
        stream_set_blocking($this->socket, false);

        while (time() < $endTime) {
            $event = [];
            $line = '';

            // Read lines until we get a complete event
            while (($line = $this->readLineNonBlocking()) !== false) {
                // Blank line marks end of event
                if (trim($line) === '') {
                    if (!empty($event)) {
                        $events[] = $event;
                        $event = [];
                    }
                    continue;
                }

                // Parse key: value
                $colonPos = strpos($line, ':');
                if ($colonPos !== false) {
                    $key = trim(substr($line, 0, $colonPos));
                    $value = trim(substr($line, $colonPos + 1));
                    $event[$key] = $value;
                }
            }

            // Small sleep to prevent CPU spin
            usleep(100000); // 100ms
        }

        // Restore blocking mode
        stream_set_blocking($this->socket, true);

        return $events;
    }

    /**
     * Read a single line from socket (blocking)
     */
    private function readLine()
    {
        $line = fgets($this->socket);
        return $line !== false ? rtrim($line, "\r\n") : false;
    }

    /**
     * Read a single line from socket (non-blocking)
     */
    private function readLineNonBlocking()
    {
        $line = fgets($this->socket);
        if ($line === false) {
            return false;
        }
        return rtrim($line, "\r\n");
    }

    /**
     * Disconnect from AMI
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            // Send logoff
            if ($this->connected) {
                @fwrite($this->socket, "Action: Logoff\r\n\r\n");
            }
            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
        }
    }

    /**
     * Destructor - ensure cleanup
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
