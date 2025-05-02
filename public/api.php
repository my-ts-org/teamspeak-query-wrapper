<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Load configuration
$config = require_once __DIR__ . '/../config/config.php';

// Load the wrapper
require_once __DIR__ . '/../src/TeamSpeakQueryWrapper.php';

use TeamSpeakWrapper\TSQueryWrapper;

try {
    $ts = new TSQueryWrapper($config);

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