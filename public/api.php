<?php
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => true,
        'message' => 'Method Not Allowed. Only POST requests are accepted.'
    ]);
    exit;
}

// Load configuration
$config = require_once __DIR__ . '/../config/config.php';

// Get raw input for debugging
$rawInput = file_get_contents('php://input');
$requestData = json_decode($rawInput, true);

// If JSON decode fails, try to get from POST data
if (json_last_error() !== JSON_ERROR_NONE) {
    $requestData = $_POST;
}

// Debug information
if ($config['debug']['enabled']) {
    error_log("Raw input: " . $rawInput);
    error_log("Request data: " . print_r($requestData, true));
    error_log("Config API key: " . $config['webapp']['apiKey']);
    error_log("POST data: " . print_r($_POST, true));
}

// Check for API key in request
$providedApiKey = $requestData['api_key'] ?? null;

// If still no API key, try to get from headers
if (empty($providedApiKey)) {
    $providedApiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
}

// If no API key is provided or it doesn't match, return error
if (empty($providedApiKey) || trim($providedApiKey) !== trim($config['webapp']['apiKey'])) {
    if ($config['debug']['enabled']) {
        error_log("API key mismatch. Provided: '" . $providedApiKey . "', Expected: '" . $config['webapp']['apiKey'] . "'");
    }
    http_response_code(401);
    echo json_encode([
        'error' => true,
        'message' => 'Unauthorized. Valid API key is required.',
        'debug' => $config['debug']['enabled'] ? [
            'provided_key' => $providedApiKey,
            'expected_key' => $config['webapp']['apiKey'],
            'raw_input' => $rawInput,
            'post_data' => $_POST
        ] : null
    ]);
    exit;
}

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

    // Check if API key exists and generate one if needed
    if (empty($config['webapp']['apiKey']) || $config['webapp']['apiKey'] === '1234567890') {
        // Generate a secure API key
        $apiKey = bin2hex(random_bytes(32));
        
        // Update the config file
        $configFile = __DIR__ . '/../config/config.php';
        $configContent = file_get_contents($configFile);
        $configContent = preg_replace(
            "/'apiKey' => '.*?'/",
            "'apiKey' => '" . $apiKey . "'",
            $configContent
        );
        
        if (file_put_contents($configFile, $configContent) === false) {
            throw new Exception("Failed to update configuration file with new API key.");
        }
        
        // Update the config array
        $config['webapp']['apiKey'] = $apiKey;
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