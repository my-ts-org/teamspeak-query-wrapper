<?php
/**
 * TeamSpeak Query PHP Wrapper
 * 
 * A secure wrapper for TeamSpeak server owners to safely gather server information
 * and send it to an authorized application.
 * 
 * This wrapper requires the permissions:
 * - b_virtualserver_info_view
 * - b_virtualserver_channel_list
 * - b_virtualserver_client_list
 * - b_client_remoteaddress_view
 * - b_serverquery_login
 * - b_client_create_modify_serverquery_login
 */

namespace TeamSpeakWrapper;

class TSQueryWrapper {
    private $host;
    private $queryPort;
    private $username;
    private $password;
    private $webappEndpoint;
    private $apiKey;
    private $socket;
    private $connected = false;
    private $debugMode = false;
    private $logFile = 'ts_wrapper.log';
    private $dataFilterCallback = null;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config)
    {
        $this->host = $config['teamspeak']['host'];
        $this->queryPort = $config['teamspeak']['queryPort'];
        $this->username = $config['teamspeak']['username'];
        $this->password = $config['teamspeak']['password'];
        $this->webappEndpoint = $config['webapp']['endpoint'];
        $this->apiKey = $config['webapp']['apiKey'];
        $this->debugMode = $config['debug']['enabled'];
        $this->logFile = $config['debug']['logFile'];
    }
    /**
     * Enable debug mode
     * 
     * @param bool $enable Whether to enable debug mode
     * @param string $logFile Path to the log file
     * @return void
     */
    public function setDebugMode(bool $enable, string $logFile = null): void
    {
        $this->debugMode = $enable;
        if ($logFile !== null) {
            $this->logFile = $logFile;
        }
    }
    
    /**
     * Log a debug message
     * 
     * @param string $message The message to log
     * @return void
     */
    private function debug(string $message): void
    {
        if ($this->debugMode) {
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents(
                $this->logFile,
                "[$timestamp] $message" . PHP_EOL,
                FILE_APPEND
            );
        }
    }
    
    /**
     * Read the welcome message from the TeamSpeak server
     * 
     * @return string The welcome message
     */
    private function readWelcomeMessage(): string
    {
        $response = '';
        $timeout = 5;
        stream_set_timeout($this->socket, $timeout);
        
        // Read until we get the welcome message
        while (true) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);
                if ($meta['timed_out']) {
                    throw new \Exception("Timeout while reading welcome message");
                }
                break;
            }
            
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $response .= $line . "\n";
            
            // Welcome message ends with a line containing "TS3"
            if (strpos($line, 'TS3') !== false) {
                break;
            }
        }
        
        return $response;
    }
    
    /**
     * Read a response from the TeamSpeak server
     * 
     * @return string The response
     * @throws \Exception if the read operation times out
     */
    private function readResponse(): string
    {
        $response = '';
        $timeout = 5; // 5 seconds timeout for each read
        $startTime = time();
        
        while (true) {
            // Set socket timeout
            stream_set_timeout($this->socket, $timeout);
            
            $line = fgets($this->socket, 4096);
            
            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);
                if ($meta['timed_out']) {
                    $this->debug("Socket read timed out after {$timeout} seconds");
                    throw new \Exception("Socket read timed out. Check if the TeamSpeak server is running and accessible.");
                }
                break;
            }
            
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            $response .= $line . "\n";
            
            // Check for error response or command end
            if (strpos($line, 'error id=') !== false || strpos($line, 'msg=') !== false) {
                break;
            }
            
            // Check for overall timeout
            if (time() - $startTime > 30) {
                $this->debug("Overall read operation timed out after 30 seconds");
                throw new \Exception("Overall read operation timed out. Check if the TeamSpeak server is responding properly.");
            }
        }
        
        return $response;
    }
    
    /**
     * Connect to the TeamSpeak server
     * 
     * @return bool Whether the connection was successful
     */
    public function connect(): bool
    {
        $this->debug("Connecting to TeamSpeak server at {$this->host}:{$this->queryPort}");
        
        $this->socket = fsockopen($this->host, $this->queryPort, $errno, $errstr, 5);
        
        if (!$this->socket) {
            $this->debug("Connection failed: $errstr ($errno)");
            return false;
        }
        
        // Read welcome message
        try {
            $welcome = $this->readWelcomeMessage();
            $this->debug("Received welcome message: " . $welcome);
        } catch (\Exception $e) {
            $this->debug("Failed to read welcome message: " . $e->getMessage());
            fclose($this->socket);
            return false;
        }
        
        // Login
        $loginResult = $this->sendCommand("login {$this->username} {$this->password}");
        if (strpos($loginResult, 'error id=0') === false) {
            $this->debug("Login failed: $loginResult");
            fclose($this->socket);
            return false;
        }
        
        // Use the first virtual server
        $useResult = $this->sendCommand("use 1");
        if (strpos($useResult, 'error id=0') === false) {
            $this->debug("Use command failed: $useResult");
            fclose($this->socket);
            return false;
        }
        
        $this->connected = true;
        $this->debug("Successfully connected to TeamSpeak server");
        return true;
    }
    
    /**
     * Send a command to the TeamSpeak server
     * 
     * @param string $command The command to send
     * @return string The response
     */
    private function sendCommand(string $command): string
    {
        $this->debug("Sending command: $command");
        
        fwrite($this->socket, $command . "\n");
        $response = $this->readResponse();
        
        $this->debug("Response: $response");
        
        return $response;
    }
    
    /**
     * Disconnect from the TeamSpeak server
     * 
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->connected) {
            $this->sendCommand('quit');
            fclose($this->socket);
            $this->connected = false;
            $this->debug("Disconnected from TeamSpeak server");
        }
    }
    
    /**
     * Escape a TeamSpeak string
     * 
     * @param string $string The string to escape
     * @return string The escaped string
     */
    private function escapeTS(string $string): string
    {
        $replacements = [
            '\\' => '\\\\',
            '/' => '\\/',
            ' ' => '\\s',
            '|' => '\\p',
            "\a" => '\\a',
            "\b" => '\\b',
            "\f" => '\\f',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            "\v" => '\\v'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }
    
    /**
     * Unescape a TeamSpeak string
     * 
     * @param string $string The string to unescape
     * @return string The unescaped string
     */
    private function unescapeTS(string $string): string
    {
        $replacements = [
            '\\\\' => '\\',
            '\\/' => '/',
            '\\s' => ' ',
            '\\p' => '|',
            '\\a' => "\a",
            '\\b' => "\b",
            '\\f' => "\f",
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\v' => "\v"
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }
    
    /**
     * Parse a TeamSpeak response into an array
     * 
     * @param string $response The response to parse
     * @return array The parsed response
     */
    private function parseResponse(string $response): array
    {
        $result = [];
        $lines = explode("\n", trim($response));
        
        foreach ($lines as $line) {
            if (empty($line) || strpos($line, 'error id=') === 0) {
                continue;
            }
            
            $items = [];
            $parts = explode('|', $line);
            
            foreach ($parts as $part) {
                $item = [];
                $pairs = explode(' ', $part);
                
                foreach ($pairs as $pair) {
                    if (empty($pair)) {
                        continue;
                    }
                    
                    list($key, $value) = explode('=', $pair, 2) + [null, ''];
                    $item[$key] = $this->unescapeTS($value);
                }
                
                $items[] = $item;
            }
            
            $result = array_merge($result, $items);
        }
        
        return $result;
    }
    
    /**
     * Get server information
     * 
     * @return array Server information
     */
    public function getServerInfo(): array
    {
        if (!$this->connected) {
            $this->debug("Not connected to TeamSpeak server");
            return [];
        }
        
        $response = $this->sendCommand('serverinfo');
        return $this->parseResponse($response);
    }
    
    /**
     * Get channel list
     * 
     * @return array Channel list
     */
    public function getChannelList(): array
    {
        if (!$this->connected) {
            $this->debug("Not connected to TeamSpeak server");
            return [];
        }
        
        $response = $this->sendCommand('channellist');
        return $this->parseResponse($response);
    }
    
    /**
     * Get client list
     * 
     * @return array Client list
     */
    public function getClientList(): array
    {
        if (!$this->connected) {
            $this->debug("Not connected to TeamSpeak server");
            return [];
        }
        
        $response = $this->sendCommand('clientlist');
        $clients = $this->parseResponse($response);
        
        // Filter out server query clients
        return array_filter($clients, function($client) {
            return isset($client['client_type']) && $client['client_type'] == '0';
        });
    }
    
    /**
     * Get client details
     * 
     * @param int $clientId Client ID
     * @return array Client details
     */
    public function getClientInfo(int $clientId): array
    {
        if (!$this->connected) {
            $this->debug("Not connected to TeamSpeak server");
            return [];
        }
        
        $response = $this->sendCommand("clientinfo clid={$clientId}");
        return $this->parseResponse($response);
    }
    
    /**
     * Get all data needed for the webapp
     * 
     * @return array All data
     */
    public function collectAllData(): array
    {
        $serverInfo = $this->getServerInfo();
        $channelList = $this->getChannelList();
        $clientList = $this->getClientList();
        
        // Add client details to client list
        foreach ($clientList as &$client) {
            if (isset($client['clid'])) {
                $clientInfo = $this->getClientInfo((int) $client['clid']);
                if (!empty($clientInfo)) {
                    $client = array_merge($client, $clientInfo[0] ?? []);
                }
            }
        }
        
        return [
            'server_info' => $serverInfo,
            'channels' => $channelList,
            'clients' => $clientList,
            'timestamp' => time(),
            'server_id' => md5($this->host . ':' . $this->queryPort)
        ];
    }
    
    /**
     * Set a callback function to filter data before sending to webapp
     * 
     * @param callable $callback The callback function
     * @return void
     */
    public function setDataFilter(callable $callback): void
    {
        $this->dataFilterCallback = $callback;
    }
    
    /**
     * Send data to the webapp
     * 
     * @param array $data The data to send
     * @return bool Whether the data was sent successfully
     */
    public function sendDataToWebapp(array $data = null): bool
    {
        if ($data === null) {
            $data = $this->collectAllData();
        }
        
        // Allow server owner to filter data before sending
        if ($this->dataFilterCallback !== null) {
            $data = call_user_func($this->dataFilterCallback, $data);
        }
        
        $jsonData = json_encode($data);
        
        $this->debug("Sending data to webapp: " . substr($jsonData, 0, 100) . "...");
        
        $ch = curl_init($this->webappEndpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
            'Content-Length: ' . strlen($jsonData)
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->debug("Webapp response (HTTP $httpCode): $result");
        
        return $httpCode >= 200 && $httpCode < 300;
    }
}

/**
 * Sample usage:
 * 
 * // Create a function to filter data before sending to the webapp
 * function filterTeamSpeakData(array $data): array
 * {
 *     // Example: Remove client IP addresses for privacy
 *     foreach ($data['clients'] as &$client) {
 *         unset($client['connection_client_ip']);
 *     }
 *     
 *     // Example: Remove specific channels you don't want to expose
 *     $data['channels'] = array_filter($data['channels'], function($channel) {
 *         return $channel['channel_name'] !== 'Private Channel';
 *     });
 *     
 *     return $data;
 * }
 * 
 * // Create wrapper instance
 * $wrapper = new TeamSpeakWrapper\TSQueryWrapper(
 *     'localhost',             // TeamSpeak server host
 *     10011,                   // TeamSpeak query port
 *     'serveradmin',           // TeamSpeak query username
 *     'password',              // TeamSpeak query password
 *     'https://example.com/api/teamspeak/update', // Your webapp endpoint
 *     'your-api-key'           // Your API key
 * );
 * 
 * // Enable debug mode
 * $wrapper->setDebugMode(true);
 * 
 * // Connect to the TeamSpeak server
 * if ($wrapper->connect()) {
 *     // Collect and send data
 *     $wrapper->sendDataToWebapp();
 *     
 *     // Disconnect from the TeamSpeak server
 *     $wrapper->disconnect();
 * }
 */ 