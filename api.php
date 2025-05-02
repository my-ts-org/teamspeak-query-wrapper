<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'TeamSpeakQueryWrapper.php';

use TeamSpeakWrapper\TSQueryWrapper;

// Configuration
$config = [
    'host' => 'localhost', // Change this to your TeamSpeak server IP
    'queryPort' => 10011,  // Default TeamSpeak Query port
    'username' => 'serveradmin',      // Set your query username
    'password' => 'lfRYaFRf',      // Set your query password
    'webappEndpoint' => 'http://mitbringsel.local/installer/api.php', // Your webapp endpoint
    'apiKey' => '1234567890'         // Your API key
];

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timeout
set_time_limit(30); // 30 seconds timeout

try {
    $ts = new TSQueryWrapper(
        $config['host'],
        $config['queryPort'],
        $config['username'],
        $config['password'],
        $config['webappEndpoint'],
        $config['apiKey']
    );

    // Enable debug mode
    $ts->setDebugMode(true, 'ts_wrapper.log');

    // Try to connect with a timeout
    if (!$ts->connect()) {
        throw new Exception("Failed to connect to TeamSpeak server. Please check:\n" .
            "1. Is the TeamSpeak server running?\n" .
            "2. Is the query port (10011) open and accessible?\n" .
            "3. Are the username and password correct?\n" .
            "4. Does the query user have the required permissions?\n" .
            "Check the ts_wrapper.log file for more details.");
    }

    $response = [
        'server_info' => $ts->getServerInfo(),
        'channels' => $ts->getChannelList(),
        'clients' => $ts->getClientList()
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'details' => $e->getTraceAsString()
    ]);
} finally {
    if (isset($ts)) {
        $ts->disconnect();
    }
} 